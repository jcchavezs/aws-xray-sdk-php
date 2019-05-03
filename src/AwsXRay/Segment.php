<?php

namespace AwsXRay;

use AwsXRay\Plugins\PluginMetadata;
use InvalidArgumentException;

interface Segment extends \JsonSerializable
{
    /**
     * @param string $key
     * @param string|int|float|bool $value
     * @throws InvalidArgumentException if $value is not scalar
     */
    public function addAnnotation(string $key, $value): void;
    public function close($error = null): void;

    /**
     * @return Segment
     */
    public function getRoot(): Segment;

    /**
     * @return bool
     */
    public function isSampled(): bool;

    /**
     * @return Segment[]|array;
     */
    public function getSubsegments(): array;

    /**
     * @return string
     */
    public function getId(): string;

    /**
     * @return string
     */
    public function getTraceId(): string;

    public function removeFromParent(): void;
}
