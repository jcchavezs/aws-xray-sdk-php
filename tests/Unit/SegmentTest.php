<?php

namespace AwsXRayTests\Unit;

use AwsXRay\Header;
use AwsXRay\Segment;
use BadMethodCallException;
use PHPUnit\Framework;

final class SegmentTest extends Framework\TestCase
{
    const NAME = 'test';
    const CHILD_NAME = 'test_child';
    const TRACE_ID = 'a1';
    const PARENT_ID = 'b2';

    public function testCreateSegmentWithoutHeader()
    {
        $segment = Segment::create(self::NAME);

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

        $segment = Segment::create(self::NAME, $header);

        $this->assertArraySubset(
            [
                'name' => self::NAME,
                'trace_id' => self::TRACE_ID,
                'id' => self::PARENT_ID,
                'in_progress' => true,
            ],
            $segment->jsonSerialize()
        );
    }

    public function testAddsAnnotationsSuccess()
    {
        $segment = Segment::create(self::NAME);
        $segment->addAnnotation('key', 'value');
        $this->assertEquals(['key' => 'value'], $segment->jsonSerialize()['annotations']);
    }

    public function testAddsAnnotationsFails()
    {
        $this->expectException(BadMethodCallException::class);
        $segment = Segment::create(self::NAME);
        $segment->addAnnotation('key', ['a']);
    }

    public function testCloseSuccess()
    {
        $segment = Segment::create(self::NAME);
        $segment->close();
        $this->assertArrayHasKey('end_time', $segment->jsonSerialize());
        $this->assertArrayNotHasKey('in_progress', $segment->jsonSerialize());
    }
}
