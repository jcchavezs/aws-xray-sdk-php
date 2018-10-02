<?php

namespace AwsXRay\Emitters;

use AwsXRay\Emitter;
use AwsXRay\Segment;
use RuntimeException;
use Psr\Log\NullLogger;
use Psr\Log\LoggerInterface;

final class UDP implements Emitter
{
    private $address;
    private $port;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param string $address
     * @param int $port
     * @param LoggerInterface $logger
     */
    public function __construct($address, $port, LoggerInterface $logger = null)
    {
        $this->address = (string) $address;
        $this->port = (int) $port;
        $this->logger = $logger ?: new NullLogger();
    }

    public function __invoke(Segment $segment)
    {
        if (!$segment->getRoot()->isSampled()) {
            return;
        }
    
        register_shutdown_function([$this, 'sendSegments'], json_encode($segment));
    }

    public function sendSegments($serializedSegments)
    {
        if ($socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP)) {
            $isSent = socket_sendto(
                $socket,
                $serializedSegments,
                strlen($serializedSegments),
                0,
                $this->address,
                $this->port
            );
            
            if ($isSent === false) {
                $this->logger->error("Could not send the segments.");
                return;
            }

            socket_close($socket);
        } else {
            $this->logger->error("Could not create the socket.");
            return;
        }
    }
}
