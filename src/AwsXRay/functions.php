<?php

namespace AwsXRay;

/**
 * @param string $name
 * @return Segment
 */
function beginSegment($name)
{
    return Segment::create((string) $name);
}

/**
 * @param Segment $segment
 * @param string $name
 * @return Segment
 */
function beginSubsegment(Segment $segment, $name)
{
    return Segment::createFromParent($segment, (string) $name);
}
