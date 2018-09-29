<?php

namespace AwsXRay;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Emitter
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
        $this->address = $address;
        $this->port = $port;
        $this->logger = $logger ?: new NullLogger();
    }

    public function __invoke(Segment $segment)
    {
        if (!$segment->getRoot()->isSampled()) {
            return;
        }
    
        $this->sendSegments(json_encode($segment));
    }

    private function sendSegments($serializedSegments)
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
                throw new \RuntimeException("Could not send the segments.");
            }

            socket_close($socket);
        } else {
            throw new \RuntimeException("Could not create the socket.");
        }
    }
}
