<?php

namespace AwsXRay;

use AwsXRay\Emitter;
use AwsXRay\Plugins\PluginMetadata;
use AwsXRay\Emitters\Noop;

class Recorder
{
    /**
     * @var PluginMetadata|null
     */
    private $pluginMetadata;

    /**
     * @var Emitter
     */
    private $emitter;

    private $segments = [];

    public function __construct(
        ?PluginMetadata $pluginMetadata = null,
        ?Emitter $emitter = null
    ) {
        $this->pluginMetadata = $pluginMetadata;
        $this->emitter = $emitter ?: new Noop();
    }

    /**
     * @param string $name
     * @param Header|null $header
     * @return Segment
     */
    public function beginSegment(string $name, ?Header $header = null): Segment
    {
        $segment = Segment::create($this, (string) $name, $header);

        if ($this->pluginMetadata !== null) {
            $segment->addPlugin($this->pluginMetadata);
        }

        $this->segments[] = $segment;

        return $segment;
    }

    public function beginSubsegment(Segment $segment, string $name): SubSegment
    {
        return Segment::createFromParent($segment, (string) $name);
    }

    public function beginNextSegment(string $name): Segment
    {
        $currentSubsegment = $this->getCurrentSubsegment();
        if ($currentSubsegment === null) {
            return $this->beginSegment($name);
        }
        
        return $this->beginSubsegment($currentSubsegment, $name);
    }

    public function getCurrentSegment(): ?Segment
    {
        return count($this->segments) === 0 ? null : end($this->segments);
    }

    public function getCurrentSubsegment(): ?SubSegment
    {
        $currentSubsegment = $this->getCurrentSegment();
        if ($currentSubsegment === null) {
            return null;
        }
        
        while (true) {
            $subSegments = $currentSubsegment->getSubsegments();
            if (count($subSegments) == 0) {
                return $currentSubsegment;
            }

            $currentSubsegment = end($subSegments);
        }
    }

    public function closeSegment(Segment $segment)
    {
        $this->emitter->__invoke($segment);
    }
}
