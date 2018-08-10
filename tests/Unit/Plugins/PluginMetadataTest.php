<?php

namespace AwsXRayTests\Unit\Plugins;

use AwsXRay\Plugins\PluginMetadata;
use PHPUnit\Framework;

final class PluginMetadataTest extends Framework\TestCase
{
    public function testPluginMetadataCreationSuccess()
    {
        $expectedEB = [
            'environment_name' => 'test_environment_name',
            'version_label' => 'test_version_label',
            'deployment_id' => 'test_deployment_id',
        ];

        $expectedEC2 = [
            'instance_id' => 'test_instance_id',
            'availability_zone' => 'test_availability_zone',
        ];

        $expectedECS = [
            'container' => 'test_container',
        ];

        $metadata = [
            'origin' => 'test_origin',
            'elastic_beanstalk' => $expectedEB + ['unnecessary' => 'value'],
            'ec2' => $expectedEC2 + ['unnecessary' => 'value'],
            'ecs' => $expectedECS + ['unnecessary' => 'value'],
        ];
        $pluginMetadata = PluginMetadata::create($metadata);
        $this->assertEquals($metadata['origin'], $pluginMetadata->getOrigin());
        $this->assertEquals($expectedEB, $pluginMetadata->getBeanstalk());
        $this->assertEquals($expectedEC2, $pluginMetadata->getEC2());
        $this->assertEquals($expectedECS, $pluginMetadata->getECS());
    }
}
