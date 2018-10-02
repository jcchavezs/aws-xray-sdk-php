<?php

namespace AwsXRay;

use AwsXRay\Segment;

interface Emitter
{
    public function __invoke(Segment $segment);
}
