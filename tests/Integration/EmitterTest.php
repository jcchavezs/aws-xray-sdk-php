<?php

namespace AwsXRayTests\Integration;

use AwsXRay\Emitter;
use AwsXRay\Emitters\UDP;
use AwsXRay\Segment;
use PHPUnit\Framework;
use Psr\Log\NullLogger;
use Psr\Log\LoggerInterface;

final class EmitterTest extends Framework\TestCase
{
    public function testEmitterSuccess()
    {
        $emitter = new UDP('127.0.0.1', 2000, new NullLogger());
        $segment = Segment::create($emitter, 'test');
        $emitter->__invoke($segment);

        $this->assertTrue(true);
    }
}
