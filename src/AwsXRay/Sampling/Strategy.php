<?php

namespace AwsXRay\Sampling;

use Psr\Http\Message\RequestInterface;

/**
 * Strategy provides an interface for implementing trace sampling
 * strategies.
 */
interface Strategy
{
    public function shouldTrace(RequestInterface $request);
}
