<?php

namespace AwsXRayTests\Unit;

use AwsXRay\Segment;
use AwsXRay\Recorder;
use PHPUnit\Framework;
use AwsXRay\SubSegment;
use AwsXRay\Plugins\PluginMetadata;
use AwsXRay\ParentSegment;

final class SegmentTest extends Framework\TestCase
{
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

        $segment = SubSegment::createFromParent(ParentSegment::create(new Recorder(), 'parent_name'), 'test_name');
        $segment->addPlugin($metadataPlugin);
        $this->assertEquals('test_origin', $segment->jsonSerialize()['origin']);
        $this->assertEquals($metadata['elastic_beanstalk'], $segment->jsonSerialize()['aws']['elastic_beanstalk']);
        $this->assertEquals($metadata['ec2'], $segment->jsonSerialize()['aws']['ec2']);
        $this->assertEquals($metadata['ecs'], $segment->jsonSerialize()['aws']['ecs']);
    }
}
