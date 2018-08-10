<?php

namespace AwsXRay\Plugins;

class PluginMetadata
{
    const EB_SERVICE_NAME = 'elastic_beanstalk';
    const EC2_SERVICE_NAME = 'ec2';
    const ECS_SERVICE_NAME = 'ecs';

    /**
     * EC2Metadata records the ec2 instance ID and availability zone.
     *
     * @var array
     */
    private $ec2;

    /**
     * BeanstalkMetadata records the Elastic Beanstalk
     * environment name, version label, and deployment ID.
     *
     * @var array
     */
    private $beanstalk;

    /**
     * ECSMetadata records the ECS container ID.
     *
     * @var array
     */
    private $ecs;

    /**
     * Origin records original service of the segment.
     *
     * @var string
     */
    private $origin;

    /**
     * @param array $metadata
     * @return PluginMetadata
     */
    public static function create(array $metadata)
    {
        $pluginMetadata = new self();

        if (array_key_exists(self::EC2_SERVICE_NAME, $metadata)) {
            $pluginMetadata->ec2 = array_filter($metadata[self::EC2_SERVICE_NAME], function ($key) {
                return $key === 'instance_id' || $key === 'availability_zone';
            }, ARRAY_FILTER_USE_KEY);
        }

        if (array_key_exists(self::EB_SERVICE_NAME, $metadata)) {
            $pluginMetadata->beanstalk = array_filter($metadata[self::EB_SERVICE_NAME], function ($key) {
                return $key === 'environment_name' || $key === 'version_label' || $key === 'deployment_id';
            }, ARRAY_FILTER_USE_KEY);
        }

        if (array_key_exists(self::ECS_SERVICE_NAME, $metadata)) {
            $pluginMetadata->ecs = array_filter($metadata[self::ECS_SERVICE_NAME], function ($key) {
                return $key === 'container';
            }, ARRAY_FILTER_USE_KEY);
        }

        if (array_key_exists('origin', $metadata)) {
            $pluginMetadata->origin = $metadata['origin'];
        }

        return $pluginMetadata;
    }

    public function getEC2()
    {
        return $this->ec2;
    }

    public function getBeanstalk()
    {
        return $this->beanstalk;
    }

    public function getECS()
    {
        return $this->ecs;
    }

    public function getOrigin()
    {
        return $this->origin;
    }
}
