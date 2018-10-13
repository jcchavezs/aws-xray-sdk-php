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
     * @var Emitter|null
     */
    private $emitter;

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
        $segment = Segment::create($this->emitter, (string) $name, $header);

        if ($this->pluginMetadata !== null) {
            $segment->addPlugin($this->pluginMetadata);
        }

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
}
