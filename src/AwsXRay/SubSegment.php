<?php

namespace AwsXRay;

use AwsXRay\Plugins\PluginMetadata;

final class SubSegment extends BaseSegment
{
    /**
     * @var Segment|null
     */
    private $parent;

    private $namespace;
    
    /**
     * @var array|string[][string]
     */
    private $aws = [];

    /**
     * @var string
     */
    private $origin;

    /**
     * @param Segment $parent
     * @param string $name
     * @param array $options
     * @return Segment
     */
    public static function createFromParent(Segment $parent, string $name, ?array $options = []): Segment
    {
        $segment = new self($parent->recorder, $name);
        $segment->id = self::newSegmentId();
        $segment->name = $name;
        $segment->parent = $parent;
        $segment->startTime = microtime(true);
        $segment->inProgress = true;

        if ($parent instanceof BaseSegment) {
            $parent->subsegments[] = $segment;
            $parent->openSubsegments++;
        }

        self::resolveOptions($segment, $options);

        return $segment;
    }

    protected static function resolveOptions(Segment $segment, array $options)
    {
        if (array_key_exists('namespace', $options)) {
            $segment->namespace = $options['namespace'];
        }
    }

    public function addPlugin(PluginMetadata $metadata): void
    {
        if ($metadata->getEC2() !== null) {
            $this->aws[PluginMetadata::EC2_SERVICE_NAME] = $metadata->getEC2();
        }

        if ($metadata->getECS() !== null) {
            $this->aws[PluginMetadata::ECS_SERVICE_NAME] = $metadata->getECS();
        }

        if ($metadata->getBeanstalk() !== null) {
            $this->aws[PluginMetadata::EB_SERVICE_NAME] = $metadata->getBeanstalk();
        }

        $this->origin = $metadata->getOrigin();
    }


    public function getRoot(): Segment
    {
        return $this->parent->getRoot();
    }


    public function close($error = null): void
    {
        parent::close($error);

        if ($this->parent !== null) {
            $this->parent->accuseClosedSubsegment();
        }
    }

    public function jsonSerialize()
    {
        $segment = parent::jsonSerialize();

        if ($this->namespace !== null) {
            $segment['namespace'] = $this->namespace;
        }

        if ($this->origin !== null) {
            $segment['origin'] = $this->origin;
        }

        if (!empty($this->aws)) {
            $segment['aws'] = $this->aws;
        }
        
        return $segment;
    }
}
