<?php

namespace AwsXRay\Emitters;

use AwsXRay\Emitter;
use AwsXRay\Segment;
use Psr\Log\NullLogger;
use Psr\Log\LoggerInterface;

final class UDP implements Emitter
{
    /**
     * @var string
     */
    private $address;

    /**
     * @var int
     */
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
    public function __construct(string $address, int $port, ?LoggerInterface $logger = null)
    {
        $this->address = $address;
        $this->port = $port;
        $this->logger = $logger ?: new NullLogger();
    }

    public function __invoke(Segment $segment)
    {
        if (!$segment->getRoot()->isSampled()) {
            $this->logger->debug('unsampled segment');
            return;
        }
        $this->sendSegments(json_encode($segment));
        //register_shutdown_function([$this, 'sendSegments'], json_encode($segment));
    }

    public function sendSegments(string $serializedSegments)
    {
        $this->logger->debug($serializedSegments);
        
        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if ($socket === false) {
            $this->logger->error(sprintf("Could not create the socket: %s", socket_last_error()));
            return;
        }

        $isSent = socket_sendto(
            $socket,
            $serializedSegments,
            strlen($serializedSegments),
            0,
            $this->address,
            $this->port
        );
        
        if ($isSent === false) {
            socket_close($socket);
            $this->logger->error("Could not send the segments.");
            return;
        }

        socket_close($socket);
    }
}
