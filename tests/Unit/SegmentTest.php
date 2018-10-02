<?php

namespace AwsXRayTests\Unit;

use AwsXRay\Header;
use AwsXRay\Plugins\PluginMetadata;
use AwsXRay\Segment;
use InvalidArgumentException;
use PHPUnit\Framework;
use AwsXRay\Emitters\Noop;

final class SegmentTest extends Framework\TestCase
{
    const NAME = 'test';
    const CHILD_NAME = 'test_child';
    const TRACE_ID = 'a1';
    const PARENT_ID = 'b2';

    public function testCreateSegmentWithoutHeader()
    {
        $segment = Segment::create(new Noop(), self::NAME);

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

        $segment = Segment::create(new Noop(), self::NAME, $header);

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
        $segment = Segment::create(new Noop(), self::NAME);
        $segment->addAnnotation('key', 'value');
        $this->assertEquals(['key' => 'value'], $segment->jsonSerialize()['annotations']);
    }

    public function testAddsAnnotationsFails()
    {
        $this->expectException(InvalidArgumentException::class);
        $segment = Segment::create(new Noop(), self::NAME);
        $segment->addAnnotation('key', ['a']);
    }

    public function testCloseSuccess()
    {
        $segment = Segment::create(new Noop(), self::NAME);
        $segment->close();
        $this->assertArrayHasKey('end_time', $segment->jsonSerialize());
        $this->assertArrayNotHasKey('in_progress', $segment->jsonSerialize());
    }

    public function testAddPlugin()
    {
        $metadata = [
            'elastic_beanstalk' => [
                'environment_name' => 'test_environment_name',
                'version_label' => 'test_version_label',
                'deployment_id' => 'test_deployment_id',
            ],
            'ec2' => [
                'instance_id' => 'test_instance_id',
                'availability_zone' => 'test_availability_zone',
            ],
            'ecs' => [
                'container' => 'test_container',
            ],
            'origin' => 'test_origin',
        ];
        $metadataPlugin = PluginMetadata::create($metadata);

        $segment = Segment::create(new Noop(), 'test_name');
        $segment->addPlugin($metadataPlugin);
        $this->assertEquals('test_origin', $segment->jsonSerialize()['origin']);
        $this->assertEquals($metadata['elastic_beanstalk'], $segment->jsonSerialize()['aws']['elastic_beanstalk']);
        $this->assertEquals($metadata['ec2'], $segment->jsonSerialize()['aws']['ec2']);
        $this->assertEquals($metadata['ecs'], $segment->jsonSerialize()['aws']['ecs']);
    }
}
