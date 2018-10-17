<?php

namespace AwsXRay\Emitters;

use AwsXRay\Segment;
use AwsXRay\Emitter;

final class InMemory implements Emitter
{
    /**
     * @var Segment[]
     */
    private $segments = [];

    /**
     * {@inheritdoc}
     */
    public function __invoke(Segment $segment)
    {
        $this->segments[] = $segment;
    }

    /**
     * @return Segment[]
     */
    public function flush()
    {
        $segments = $this->segments;
        $this->segments = [];
        return $segments;
    }
}
