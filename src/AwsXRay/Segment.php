<?php

namespace AwsXRay;

use BadMethodCallException;
use Exception;

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
     * @var string
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
     * @var Segment
     */
    private $parent;

    private function __construct()
    {
    }

    /**
     * @param string $name
     * @param Header|null $header
     * @return Segment
     */
    public static function create($name, Header $header = null)
    {
        $segment = new self();

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
     * @return Segment
     */
    public static function createFromParent(Segment $parent, $name)
    {
        $segment = new self();
        $segment->id = self::newSegmentId();
        $segment->name = $name;
        $segment->parent = $parent;
        $segment->startTime = microtime(true);
        $segment->inProgress = true;

        $parent->subsegments[] = $segment;

        return $segment;
    }

    /**
     * @param string $key
     * @param string|int|float|bool $value
     * @throws \BadMethodCallException if $value is not scalar
     */
    public function addAnnotation($key, $value)
    {
        if (!is_string($value)) {
            throw new BadMethodCallException(sprintf(
                'Failed to add annotation with key type: "%s" to subsegment "%s". key must be of type string.',
                gettype($key),
                $this->name
            ));
        }

        if (!is_scalar($value)) {
            throw new BadMethodCallException(sprintf(
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

        $this->flush();
    }

    private static function newTraceId()
    {
        $random = \random_bytes(12);
        return sprintf('1-%08x-%02x', time(), $random);
    }

    private static function newSegmentId()
    {
        $random = \random_bytes(8);
        return sprintf('%02x', $random);
    }


    private function addError($error)
    {
        $this->fault = true;
        $this->cause['exceptions'][] = $error;
    }

    private function flush()
    {
    }

    /**
     * @return Segment|null
     */
    private function getRoot()
    {
        return $this->parent === null ? $this : $this->parent->getRoot();
    }

    public function jsonSerialize()
    {
        $segment = [
            'trace_id' => $this->traceId,
            'id' => $this->id,
            'name' => $this->name,
            'start_time' => $this->startTime,
        ];

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
}
