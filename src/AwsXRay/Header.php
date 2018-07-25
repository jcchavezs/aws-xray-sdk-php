<?php

namespace AwsXRay;

class Header
{
    /**
     * RootPrefix is the prefix for
     * Root attribute in X-Amzn-Trace-Id.
     */
    const ROOT_PREFIX = 'Root=';

    /**
     * ParentPrefix is the prefix for
     * Parent attribute in X-Amzn-Trace-Id.
     */
    const PARENT_PREFIX = 'Parent=';

    /**
     * SampledPrefix is the prefix for
     * Sampled attribute in X-Amzn-Trace-Id.
     */
    const SAMPLED_PREFIX = 'Sampled=';

    /**
     * SelfPrefix is the prefix for
     * Self attribute in X-Amzn-Trace-Id.
     */
    const SELF_PREFIX = 'Self=';

    /**
     * Sampled indicates the current segment has been
     * sampled and will be sent to the X-Ray daemon.
     */
    const SAMPLED = '1';

    /**
     * NotSampled indicates the current segment has
     * not been sampled.
     */
    const NOT_SAMPLED = '0';

    /**
     * Requested indicates sampling decision will be
     * made by the downstream service and propagated
     * back upstream in the response.
     */
    const REQUESTED_SAMPLING = '?';

    /**
     * Unknown indicates no sampling decision will be made.
     */
    const UNKNOWN_SAMPLING = '';

    /**
     * @var string
     */
    private $traceId;

    /**
     * @var string
     */
    private $parentId;

    /**
     * @var string
     */
    private $samplingDecision;

    /**
     * @var array
     */
    private $additionalData;


    public function __construct($traceId = null, $parentId = null, $sampledDecision = null, array $additionalData = [])
    {
        $this->traceId = $traceId;
        $this->parentId = $parentId;
        $this->samplingDecision = $sampledDecision;
        $this->additionalData = $additionalData;
    }

    public static function fromString($s)
    {
        $pieces = explode(';', $s);

        $traceId = $parentId = $samplingDecision = null;
        $additionalData = [];

        foreach ($pieces as $piece) {
            $piece = trim($piece);
            if (strpos($piece, self::ROOT_PREFIX) === 0) {
                $traceId = substr($piece, strlen(self::ROOT_PREFIX));
            } elseif (strpos($piece, self::PARENT_PREFIX) === 0) {
                $parentId = substr($piece, strlen(self::PARENT_PREFIX));
            } elseif (strpos($piece, self::SAMPLED_PREFIX) === 0) {
                $samplingDecision = substr($piece, strlen(self::SAMPLED_PREFIX));
            } elseif (strpos($piece, self::SELF_PREFIX) !== 0) {
                list($key, $value) = explode('=', $piece);
                $additionalData[$key] = $value;
            }
        }

        return new self($traceId, $parentId, $samplingDecision, $additionalData);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        $s = [];

        if ($this->traceId !== null) {
            $s[] = self::ROOT_PREFIX . $this->traceId;
        }

        if ($this->parentId !== null) {
            $s[] = self::PARENT_PREFIX . $this->parentId;
        }

        if ($this->samplingDecision !== null) {
            $s[] = self::SAMPLED_PREFIX . $this->samplingDecision;
        }

        foreach ($this->additionalData as $key => $value) {
            $s[] = $key . '=' . $value;
        }

        return implode(';', $s);
    }

    public function getTraceId()
    {
        return $this->traceId;
    }

    public function getParentId()
    {
        return $this->parentId;
    }

    public function isSampled()
    {
        if ($this->samplingDecision === self::SAMPLED) {
            return true;
        }

        if ($this->samplingDecision === self::NOT_SAMPLED) {
            return false;
        }

        return null;
    }

    public function isSampledRequested()
    {
        return $this->samplingDecision === self::REQUESTED_SAMPLING;
    }

    public function getAdditionalData()
    {
        return $this->additionalData;
    }

    public function getAdditionalValue($key)
    {
        return array_key_exists($key, $this->additionalData)
            ? $this->additionalData[$key] : null;
    }
}
