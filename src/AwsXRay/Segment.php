<?php

namespace AwsXRay;

use Exception;
use AwsXRay\Plugins\PluginMetadata;
use InvalidArgumentException;

final class Segment implements \JsonSerializable
{
    /**
     * A 64-bit identifier for the segment, unique among segments in the same trace, in 16 hexadecimal digits.
     *
     * @var string
     */
    private $id;

    /**
     * A unique identifier that connects all segments and subsegments originating from a single client request
     *
     * @var string
     */
    private $traceId;

    /**
     * @var bool
     */
    private $sampled;

    /**
     * The logical name of the service that handled the request, up to 200 characters
     *
     * @var string
     */
    private $name;

    /**
     * Number that is the time the segment was created, in floating point seconds in epoch time
     *
     * @var float
     */
    private $startTime;

    /**
     * Number that is the time the segment was closed
     *
     * @var float
     */
    private $endTime;

    /**
     * @var bool
     */
    private $inProgress;

    /**
     * @var Exception
     */
    private $error;

    /**
     * @var array|string[string]|bool[string]|int[string]|float[string]
     */
    private $annotations;

    /**
     * @var array
     */
    private $metadata;

    /**
     * @var array
     */
    private $cause;

    /**
     * @var bool indicating that a server error occurred (response status code was 5XX Server Error)
     */
    private $fault = false;

    /**
     * @var array|Segment[]
     */
    private $subsegments = [];

    /**
     * @var Segment|null
     */
    private $parent;

    /**
     * @var string
     */
    private $origin;

    private $namespace;

    /**
     * @var array|string[][string]
     */
    private $aws = [];

    /**
     * @var Recorder|null
     */
    private $recorder;

    /**
     * @var int
     */
    private $openSegments = 0;

    private function __construct()
    {
    }

    /**
     * @param Recorder $recorder
     * @param string $name
     * @param Header|null $header
     * @return Segment
     */
    public static function create(Recorder $recorder, string $name, ?Header $header = null): Segment
    {
        $segment = new self();
        $segment->recorder = $recorder;

        $name = substr($name, 0, 200);

        $segment->name = $name;
        $segment->startTime = microtime(true);
        $segment->inProgress = true;

        $segment->cause = [
            'working_directory' => getcwd(),
            'exceptions' => [],
        ];

        if ($header === null) {
            $segment->traceId = self::newTraceId();
            $segment->id = self::newSegmentId();
            $segment->sampled = true;
        } else {
            $segment->traceId = $header->getTraceId() ?: self::newTraceId();
            $segment->id = $header->getParentId() ?: self::newSegmentId();
            $segment->sampled = $header->isSampled();
        }

        return $segment;
    }

    /**
     * @param Segment $parent
     * @param string $name
     * @param array $options
     * @return Segment
     */
    public static function createFromParent(Segment $parent, string $name, array $options = []): Segment
    {
        $segment = new self();
        $segment->id = self::newSegmentId();
        $segment->name = $name;
        $segment->parent = $parent;
        $segment->startTime = microtime(true);
        $segment->inProgress = true;

        $parent->subsegments[] = $segment;
        $parent->openSegments++;

        self::resolveOptions($segment, $options);

        return $segment;
    }

    private static function resolveOptions(Segment $segment, array $options)
    {
        if (array_key_exists('namespace', $options)) {
            $segment->namespace = $options['namespace'];
        }
    }

    /**
     * @param string $key
     * @param string|int|float|bool $value
     * @throws \InvalidArgumentException if $value is not scalar
     */
    public function addAnnotation(string $key, $value): void
    {
        if (!is_scalar($value)) {
            throw new InvalidArgumentException(sprintf(
                'Failed to add annotation key: "%s" with value type: "%s" to subsegment "%s". value must be scalar',
                (string) $key,
                gettype($value),
                $this->name
            ));
        }

        $this->annotations[$key] = $value;
    }

    public function close($error = null): void
    {
        $this->endTime = microtime(true);
        $this->inProgress = false;

        if ($error !== null) {
            $this->addError($error);
        }

        if ($this->recorder !== null) {
            $this->recorder->closeSegment($this);
        }

        if ($this->parent !== null) {
            $this->parent->accuseClosedSubsegment();
        }
    }

    public function addPlugin(PluginMetadata $metadata): void
    {
        if ($metadata->getEC2() !== null) {
            $this->aws[PluginMetadata::EC2_SERVICE_NAME] = $metadata->getEC2();
        }

        if ($metadata->getECS() !== null) {
            $this->aws[PluginMetadata::ECS_SERVICE_NAME] = $metadata->getECS();
        }

        if ($metadata->getBeanstalk() !== null) {
            $this->aws[PluginMetadata::EB_SERVICE_NAME] = $metadata->getBeanstalk();
        }

        $this->origin = $metadata->getOrigin();
    }

    private static function newTraceId(): string
    {
        $random = \random_bytes(12);
        return sprintf('1-%8s-%24s', dechex(time()), bin2hex($random));
    }

    private static function newSegmentId(): string
    {
        $random = \random_bytes(8);
        return bin2hex($random);
    }

    private function addError($error): void
    {
        $this->fault = true;
        $this->cause['exceptions'][] = $error;
    }

    /**
     * @return Segment
     */
    public function getRoot(): Segment
    {
        return $this->parent === null ? $this : $this->parent->getRoot();
    }

    /**
     * @return bool
     */
    public function isSampled(): bool
    {
        return $this->sampled;
    }

    /**
     * @return Segment[]|array;
     */
    public function getSubsegments(): array
    {
        return $this->subsegments;
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getTraceId(): string
    {
        return $this->traceId;
    }

    public function removeFromParent(): void
    {
        if ($this->parent === null) {
            return;
        }

        foreach ($this->parent->subsegments as $i => $segment) {
            if ($segment->id === $this->id) {
                unset($this->parent->subsegments[$i]);
                $this->parent->openSegments--;
                return;
            }
        }
    }

    private function toArrayBasicSegment(): array
    {
        $segment = [
            'id' => $this->id,
            'name' => $this->name,
            'start_time' => $this->startTime,
        ];

        if ($this->inProgress) {
            $segment['in_progress'] = true;
        } else {
            $segment['end_time'] = $this->endTime;
        }

        if ($this->endTime !== null && $this->error !== null) {
            $segment['error'] = $this->error;
        }

        if ($this->fault) {
            $segment['fault'] = true;
        }

        if (!empty($this->annotations)) {
            $segment['annotations'] = $this->annotations;
        }

        if (!empty($this->metadata)) {
            $segment['metadata'] = $this->metadata;
        }

        if ($this->origin !== null) {
            $segment['origin'] = $this->origin;
        }

        if (!empty($this->aws)) {
            $segment['aws'] = $this->aws;
        }

        if ($this->namespace !== null) {
            $segment['namespace'] = $this->namespace;
        }

        if (count($this->subsegments) > 0) {
            $segment['subsegments'] = array_map(function (Segment $segment) {
                return $segment->toArrayBasicSegment();
            }, $this->subsegments);
        }

        if (!empty($this->cause['exceptions'])) {
            $segment['cause'] = [
                'working_directory' => $this->cause['working_directory'],
                'exceptions' => array_map(function (Exception $e) {
                    return [
                        'message' => $e->getMessage(),
                    ];
                }, $this->cause['exceptions']),
            ];
        }

        return $segment;
    }

    private function toArraySegment(): array
    {
        return ['trace_id' => $this->traceId] + $this->toArrayBasicSegment();
    }

    public function jsonSerialize()
    {
        return $this->toArraySegment();
    }


    private function accuseClosedSubsegment(): void
    {
        $this->openSegments--;
    }
}
