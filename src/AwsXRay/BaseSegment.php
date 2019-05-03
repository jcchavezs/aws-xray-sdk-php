<?php

namespace AwsXRay;

use Exception;
use AwsXRay\Plugins\PluginMetadata;
use InvalidArgumentException;

abstract class BaseSegment implements Segment
{
    /**
     * A 64-bit identifier for the segment, unique among segments in the same trace, in 16 hexadecimal digits.
     *
     * @var string
     */
    protected $id;

    /**
     * A unique identifier that connects all segments and subsegments originating from a single client request
     *
     * @var string
     */
    protected $traceId;

    /**
     * @var bool
     */
    protected $sampled;

    /**
     * The logical name of the service that handled the request, up to 200 characters
     *
     * @var string
     */
    protected $name;

    /**
     * Number that is the time the segment was created, in floating point seconds in epoch time
     *
     * @var float
     */
    protected $startTime;

    /**
     * Number that is the time the segment was closed
     *
     * @var float
     */
    protected $endTime;

    /**
     * @var bool
     */
    protected $inProgress;

    /**
     * @var Exception
     */
    protected $error;

    /**
     * @var array|string[string]|bool[string]|int[string]|float[string]
     */
    protected $annotations;

    /**
     * @var array
     */
    protected $metadata;

    /**
     * @var array
     */
    protected $cause;

    /**
     * @var bool indicating that a server error occurred (response status code was 5XX Server Error)
     */
    protected $fault = false;

    /**
     * @var array|Subsegments[]
     */
    protected $subsegments = [];

    /**
     * @var Recorder|null
     */
    protected $recorder;

    /**
     * @var int
     */
    protected $openSubsegments = 0;

    protected function __construct(Recorder $recorder, string $name)
    {
        $this->recorder = $recorder;

        $name = substr($name, 0, 200);

        $this->name = $name;
        $this->startTime = microtime(true);
        $this->inProgress = true;

        $this->cause = [
            'working_directory' => getcwd(),
            'exceptions' => [],
        ];
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
    }

    protected static function newTraceId(): string
    {
        $random = \random_bytes(12);
        return sprintf('1-%8s-%24s', dechex(time()), bin2hex($random));
    }

    protected static function newSegmentId(): string
    {
        $random = \random_bytes(8);
        return bin2hex($random);
    }

    protected function addError($error): void
    {
        $this->fault = true;
        $this->cause['exceptions'][] = $error;
    }

    /**
     * @return Segment
     */
    abstract public function getRoot(): Segment;

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
                $this->parent->openSubsegments--;
                return;
            }
        }
    }

    public function jsonSerialize(): array
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

    protected function accuseClosedSubsegment(): void
    {
        $this->openSubsegments--;
    }
}
