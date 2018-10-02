<?php

namespace AwsXRay\Emitters;

use AwsXRay\Segment;
use AwsXRay\Emitter;

final class Noop implements Emitter
{
    public function __invoke(Segment $segment)
    {
    }
}
