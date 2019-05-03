<?php

namespace AwsXRay;

final class FacadeSegment implements Segment
{
    private const MUTATION_UNSUPPORTED_MESSAGE = 'FacadeSegments cannot be mutated.';

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

    public static function create(string $name, ?Header $header = null): Segment
    {
        $segment = new self();
        $segment->name = $name;
        $segment->traceId = $header->getTraceId() ?: self::newTraceId();
        $segment->id = $header->getParentId() ?: self::newSegmentId();
        $segment->sampled = $header->isSampled();
        return $segment;
    }

     /**
     * @param string $key
     * @param string|int|float|bool $value
     * @throws InvalidArgumentException if $value is not scalar
     */
    public function addAnnotation(string $key, $value): void
    {
        $this->throwMutationException();
    }

    public function close($error = null): void
    {
        $this->throwMutationException();
    }

    /**
     * @return Segment
     */
    public function getRoot(): Segment
    {
        return $this;
    }

    /**
     * @return bool
     */
    public function isSampled(): bool
    {
        return $this->isSampled;
    }

    /**
     * @return Segment[]|array;
     */
    public function getSubsegments(): array
    {
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
        throw new Exception(self::MUTATION_UNSUPPORTED_MESSAGE);
    }

    public function throwMutationException()
    {
        throw new Exception(self::MUTATION_UNSUPPORTED_MESSAGE);
    }
}
