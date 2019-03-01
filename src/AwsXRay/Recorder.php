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
        PluginMetadata $pluginMetadata = null,
        Emitter $emitter = null
    ) {
        $this->pluginMetadata = $pluginMetadata;
        $this->emitter = $emitter ?: new Noop();
    }

    /**
     * @param string $name
     * @param Header|null $header
     * @return Segment
     */
    public function beginSegment($name, Header $header = null)
    {
        $segment = Segment::create($this, (string) $name, $header);

        if ($this->pluginMetadata !== null) {
            $segment->addPlugin($this->pluginMetadata);
        }

        $this->segments[] = $segment;

        return $segment;
    }

    /**
     * @param Segment $segment
     * @param string $name
     * @return Segment
     */
    public function beginSubsegment(Segment $segment, $name)
    {
        return Segment::createFromParent($segment, (string) $name);
    }

    /**
     * @return Segment|null
     */
    public function getCurrentSegment()
    {
        return count($this->segments) === 0 ? null : end($this->segments);
    }

    /**
     * @return Segment|null
     */
    public function getCurrentSubsegment()
    {
        $currentSubsegment = $this->getCurrentSegment();
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
