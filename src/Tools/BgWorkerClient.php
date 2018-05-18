<?php

namespace Datahouse\Elements\Tools;

use RuntimeException;

use Datahouse\Elements\Presentation\Exceptions\ConfigurationError;

/**
 * Client for the general purpose background implementation.
 *
 * @package Datahouse\Elements\Tools
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
class BgWorkerClient
{
    private $addr;
    private $port;

    /**
     * @param string|null $addr to connect to, or null for docker autoconf
     * @param int|null    $port to connect to, or null for docker autoconf
     */
    public function __construct(string $addr = null, int $port = null)
    {
        $varNamePrefix = 'BGWORKER_PORT_' . BgWorkerServer::DEFAULT_PORT . '_TCP';
        if (is_null($addr)) {
            $addrEnv = getenv($varNamePrefix . '_ADDR');
            $this->addr = $addrEnv === false ? null : $addrEnv;
        } else {
            $this->addr = $addr;
        }
        if (is_null($port)) {
            $portEnv = getenv($varNamePrefix . '_PORT');
            $this->port = $portEnv === false ? null : intval($portEnv);
        } else {
            $this->port = $port;
        }
    }

    /**
     * @return bool whether or not a bgworker container is configured
     */
    public function isConfigured() : bool
    {
        return !!$this->addr && !!$this->port;
    }

    /**
     * Opens a connection to the background worker.
     *
     * @throws ConfigurationError
     * @throws RuntimeException
     * @return resource
     */
    private function openBgworkerSocket()
    {
        if (!$this->isConfigured()) {
            throw new ConfigurationError("bgworker container not linked");
        }
        $fp = fsockopen($this->addr, $this->port, $errno, $errstr, 30);
        if ($fp) {
            return $fp;
        } else {
            throw new RuntimeException(
                "failed enqueing a job for the background worker: "
                . "$errstr ($errno)"
            );
        }
    }

    /**
     * Entry point from the client: enqueue a job to process in the
     * background.
     *
     * @param BgJob $job to enqueue
     * @return void
     * @throws ConfigurationError
     */
    public function enqueueJob(BgJob $job)
    {
        $payload = json_encode([
            'class' => get_class($job),
            'data' => $job->serialize()
        ], false);
        $data = strlen($payload) . ':' . $payload;

        $fp = $this->openBgworkerSocket();
        fwrite($fp, $data);
        // FIXME: for now, this is fire and forget...
        /*
        while (!feof($fp)) {
            echo fgets($fp, 128);
        }
        */
        fclose($fp);
    }
}
