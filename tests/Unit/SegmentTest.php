<?php

namespace AwsXRayTests\Unit;

use AwsXRay\Header;
use AwsXRay\Recorder;
use PHPUnit\Framework;
use AwsXRay\ParentSegment;
use InvalidArgumentException;

final class ParentSegmentTest extends Framework\TestCase
{
    const NAME = 'test';
    const CHILD_NAME = 'test_child';
    const TRACE_ID = 'a1';
    const PARENT_ID = 'b2';

    public function testCreateSegmentWithoutHeader()
    {
        $segment = ParentSegment::create(new Recorder(), self::NAME);

        $this->assertEquals(16, strlen($segment->getId()));
        $this->assertEquals(1 + 1 + 8 + 1 + 24, strlen($segment->getTraceId()));

        $this->assertArraySubset(
            [
                'name' => self::NAME,
                'in_progress' => true,
            ],
            $segment->jsonSerialize()
        );
    }

    public function testCreateSegmentFromHeader()
    {
        $header = new Header(self::TRACE_ID, self::PARENT_ID);

        $segment = ParentSegment::create(new Recorder(), self::NAME, $header);

        $this->assertArraySubset(
            [
                'name' => self::NAME,
                //'trace_id' => self::TRACE_ID,
                'id' => self::PARENT_ID,
                'in_progress' => true,
            ],
            $segment->jsonSerialize()
        );
    }

    public function testAddsAnnotationsSuccess()
    {
        $segment = ParentSegment::create(new Recorder(), self::NAME);
        $segment->addAnnotation('key', 'value');
        $this->assertEquals(['key' => 'value'], $segment->jsonSerialize()['annotations']);
    }

    public function testCloseSuccess()
    {
        $segment = ParentSegment::create(new Recorder(), self::NAME);
        $segment->close();
        $this->assertArrayHasKey('end_time', $segment->jsonSerialize());
        $this->assertArrayNotHasKey('in_progress', $segment->jsonSerialize());
    }
}
