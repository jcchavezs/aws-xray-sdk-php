<?php

namespace AwsXRay;

final class ParentSegment extends BaseSegment
{
    public static function create(Recorder $recorder, string $name, ?Header $header = null): Segment
    {
        $segment = new self($recorder, $name);
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

    public function getRoot(): Segment
    {
        return $this;
    }

    public function jsonSerialize()
    {
        return ['trace_id' => $this->traceId] + parent::jsonSerialize();
    }
}
