<?php

namespace AwsXRay;

use Exception;
use AwsXRay\Emitter;
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
     * @var Emitter|null
     */
    private $emitter;

    /**
     * @var int
     */
    private $openSegments = 0;

    private function __construct()
    {
    }

    /**
     * @param Emitter $emitter
     * @param string $name
     * @param Header|null $header
     * @return Segment
     */
    public static function create(Emitter $emitter, $name, Header $header = null)
    {
        $segment = new self();
        $segment->emitter = $emitter;

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
    public static function createFromParent(Segment $parent, $name, array $options = [])
    {
        $segment = new self();
        $segment->id = self::newSegmentId();
        $segment->name = $name;
        $segment->parent = $parent;
        $segment->startTime = microtime(true);
        $segment->inProgress = true;

        $parent->subsegments[] = $segment;

        self::resolveOptions($segment, $options);

        return $segment;
    }

    private static function resolveOptions(Segment $segment, $options)
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
    public function addAnnotation($key, $value)
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException(sprintf(
                'Failed to add annotation with key type: "%s" to subsegment "%s". key must be of type string.',
                gettype($key),
                $this->name
            ));
        }

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

    public function close($error = null)
    {
        $this->endTime = microtime(true);
        $this->inProgress = false;

        if ($error !== null) {
            $this->addError($error);
        }

        if ($this->parent !== null) {
            $this->parent->accuseClosedSubsegment();
        }

        $this->flush();
    }

    public function addPlugin(PluginMetadata $metadata)
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

    private static function newTraceId()
    {
        $random = \random_bytes(12);
        return sprintf('1-%8s-%24s', dechex(time()), bin2hex($random));
    }

    private static function newSegmentId()
    {
        $random = \random_bytes(8);
        return bin2hex($random);
    }

    private function addError($error)
    {
        $this->fault = true;
        $this->cause['exceptions'][] = $error;
    }

    private function flush()
    {
        if ($this->openSegments !== 0 || $this->endTime === null) {
            return;
        }

        if ($this->parent !== null) {
            $this->parent->flush();
        }

        $this->emitter->__invoke($this);
    }

    /**
     * @return Segment
     */
    public function getRoot()
    {
        return $this->parent === null ? $this : $this->parent->getRoot();
    }

    /**
     * @return bool
     */
    public function isSampled()
    {
        return $this->sampled;
    }

    /**
     * @return Segment[]|array;
     */
    public function getSubsegments()
    {
        return $this->subsegments;
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getTraceId()
    {
        return $this->traceId;
    }

    public function jsonSerialize()
    {
        $segment = [
            'id' => $this->id,
            'name' => $this->name,
            'start_time' => $this->startTime,
        ];

        if ($this->parent !== null) {
            $segment['trace_id'] = $this->traceId;
        }

        if ($this->inProgress) {
            $segment['in_progress'] = true;
        } else {
            $segment['end_time'] = $this->endTime;
        }

        if ($this->endTime !== null) {
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


    private function accuseClosedSubsegment()
    {
        $this->openSegments--;
    }
}
