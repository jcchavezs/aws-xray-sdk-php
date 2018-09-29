<?php

namespace AwsXRayTests\Integration;

use AwsXRay\Emitter;
use AwsXRay\Segment;
use PHPUnit\Framework;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class EmitterTest extends Framework\TestCase
{
    public function testEmitterSuccess()
    {
        $segment = Segment::create('test');

        $emitter = new Emitter('127.0.0.1', 2000, new NullLogger());
        $emitter->__invoke($segment);

        $this->assertTrue(true);
    }
}
