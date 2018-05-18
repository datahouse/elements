<?php

namespace Datahouse\Elements\Tools;

use stdClass;

/**
 * A general purpose background worker that's capable of processing jobs and
 * reporting feedback on progress or termination.
 *
 * @package Datahouse\Elements\Tools
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
class BgWorkerServer
{
    // Some relatively random, unassigned TCP port, should match with the
    // port exposed by the docker image.
    const DEFAULT_PORT = 41233;
    const DEFAULT_NUM_CHILDREN = 4;

    private $numChildren;
    private $terminate;
    private $exitCode;
    private $childPids;

    /**
     * BgWorker constructor.
     */
    public function __construct()
    {
        $this->pid = posix_getpid();
        $this->exitCode = 0;
        $this->terminate = false;
        $this->childPids = [];
        $this->acceptorSocket = null;
    }

    /**
     * @param int $signo number of the signal received
     * @return void
     */
    private function signalHandlerParent($signo)
    {
        switch ($signo) {
            case SIGTERM:
            case SIGINT:
                $this->shutdownParent(0);
                break;

            case SIGCHLD:
                // Handle terminated children, remove their pids.
                while (count($this->childPids) > 0 &&
                    ($pid = pcntl_waitpid(0, $status, WNOHANG)) != 0
                ) {
                    if ($pid == -1) {
                        $errno = pcntl_get_last_error();
                        error_log(
                            "FATAL: waitpid failed: " . pcntl_strerror($errno)
                        );
                        $this->shutdownParent(1);
                        break;
                    }

                    $childCode = pcntl_wexitstatus($status);
                    if ($childCode != 0) {
                        error_log(
                            "WARNING: worker process " . $pid .
                            " exited with code " . $childCode
                        );
                    }
                    if (array_key_exists($pid, $this->childPids)) {
                        unset($this->childPids[$pid]);
                    } else {
                        error_log(
                            "WARNING: got SIGCHLD for an unknown pid: $pid"
                        );
                    }
                }
                break;

            default:
                assert(false);
                // handle all other signals
        }
    }

    /**
     * @param int $signo number of the signal received
     * @return void
     */
    private function signalHandlerChild($signo)
    {
        switch ($signo) {
            case SIGTERM:
            case SIGINT:
                $this->terminate = true;
                break;
            default:
                break;
        }
    }

    /**
     * @param stdClass $jobDescription of the job to perform
     * @return void
     */
    protected function processJob(stdClass $jobDescription)
    {
        $className = $jobDescription->{'class'};
        /* @var BgJob $job */
        $job = new $className();
        $job->deserialize($jobDescription->{'data'});
        $job->execute();
    }

    /**
     * Handle initial connection handshake and all client requests, until
     * the connection terminates.
     *
     * @param resource $socket of the client connection
     * @return void
     */
    protected function handleClient($socket)
    {
        assert(is_resource($socket));
        $msg = '';
        $receivedFullMessage = false;
        while (!$receivedFullMessage) {
            $data = socket_read($socket, 2048);
            $msg .= $data;

            if ($msg == "") {
                socket_close($socket);
                break;
            } if (preg_match('/^(\d+):/', $msg, $matches) === 1) {
                $prefixLength = strlen($matches[1]) + 1;
                $msgLength = intval($matches[1]);
                if (strlen($msg) >= $prefixLength + $msgLength) {
                    $payload = substr($msg, $prefixLength, $msgLength);
                    $remainderOffset = $prefixLength + $msgLength;
                    // skip netstring's trailing comma
                    if ($remainderOffset < strlen($msg) &&
                        $msg[$remainderOffset] == ','
                    ) {
                        $remainderOffset += 1;
                    }
                    $this->processJob(json_decode($payload, false));
                    $msg = substr($msg, $remainderOffset);
                } else {
                    // continue reading data from the socket
                    continue;
                }
            } else {
                error_log(
                    "WARNING: invalid netstring, closing connection"
                );
                error_log("remaining string is: " . print_r($msg, true));
                socket_close($socket);
                break;
            }
        }
    }

    /**
     * Main event loop of the child process(es) performing the actual
     * background tasks.
     *
     * @return int exit code of the child process
     */
    protected function runWorker() : int
    {
        while (!$this->terminate) {
            sleep(1);
            pcntl_signal_dispatch();

            printf("Worker with pid %d is ready.\n", $this->pid);
            $socket = socket_accept($this->acceptorSocket);
            if ($socket === false) {
                error_log(
                    "WARNING: failed accepting a connection: " .
                    socket_strerror(socket_last_error($socket))
                );
                continue;
            } else {
                $this->handleClient($socket);
            }
        }

        if ($this->acceptorSocket) {
            socket_close($this->acceptorSocket);
        }

        return $this->exitCode;
    }

    /**
     * @param int $signo to send to the children
     * @return void
     */
    private function signalChildren(int $signo)
    {
        foreach (array_keys($this->childPids) as $pid) {
            posix_kill($pid, $signo);
        }
    }

    /**
     * @return void
     */
    protected function initParent()
    {
        // setup signal handlers
        $sigHandlerFunc = function (int $signo) {
            $this->signalHandlerParent($signo);
        };
        pcntl_signal(SIGTERM, $sigHandlerFunc);
        pcntl_signal(SIGINT, $sigHandlerFunc);
        pcntl_signal(SIGCHLD, $sigHandlerFunc);

        $envNumChildren = getenv('NUM_CHILDREN');
        $this->numChildren = $envNumChildren === false
            ? static::DEFAULT_NUM_CHILDREN : intval($envNumChildren);

        // setup tcp socket for communication with clients
        $port = static::DEFAULT_PORT;
        $socket = $this->acceptorSocket = socket_create(
            AF_INET,
            SOCK_STREAM,
            getprotobyname('tcp')
        );
        if ($socket === false) {
            error_log(
                "FATAL: failed to create socket: " .
                socket_strerror(socket_last_error($socket))
            );
            $this->shutdownParent(1);
            return;
        }
        if (socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1) !== true) {
            error_log(
                "FATAL: failed to set socket options: " .
                socket_strerror(socket_last_error($socket))
            );
            $this->shutdownParent(1);
            return;
        }
        if (socket_bind($socket, '0.0.0.0', $port) !== true) {
            error_log(
                "FATAL: failed to bind to port $port: " .
                socket_strerror(socket_last_error($socket))
            );
            $this->shutdownParent(1);
            return;
        }
        // Note that unlike documented, socket_listen seems to return 1 and
        // not true for success. PHP's type unsafety feature.
        if (socket_listen($socket, 2 * $this->numChildren) != true) {
            error_log(
                "FATAL: failed to listen on port $port: " .
                socket_strerror(socket_last_error($socket))
            );
            $this->shutdownParent(1);
            return;
        }
    }

    /**
     * Initializes a newly forked child process
     *
     * @return void
     */
    protected function initChild()
    {
        // setup signal handlers
        $sigHandlerFunc = function (int $signo) {
            $this->signalHandlerChild($signo);
        };
        pcntl_signal(SIGTERM, $sigHandlerFunc);
        pcntl_signal(SIGINT, $sigHandlerFunc);
        pcntl_signal(SIGCHLD, SIG_IGN);
    }

    /**
     * @return void
     */
    protected function forkChild()
    {
        $pid = pcntl_fork();
        switch ($pid) {
            case -1:
                $errno = pcntl_get_last_error();
                error_log("Failed to fork: " . pcntl_strerror($errno));
                $this->shutdownParent(1);
                break;
            case 0:
                // the child process
                $this->initChild();
                exit($this->runWorker());
            default:
                // in the parent process
                $this->childPids[$pid] = true;
        }
    }

    /**
     * Forks new children until the configured number of child processes is
     * reached.
     *
     * @return void
     */
    protected function manageChildren()
    {
        // (Re)start child processes until we have enough of them.
        while (!$this->terminate
            && count($this->childPids) < $this->numChildren
        ) {
            $this->forkChild();
        }
    }

    /**
     * The main event loop of the parent process.
     *
     * @return int the exit code
     */
    protected function runParent()
    {
        $this->manageChildren();

        // During normal operation, the parent only acts upon termination of
        // a child, signaled by SIGCHLD, which interrupts the sleep.
        while (!$this->terminate || count($this->childPids) > 0) {
            sleep(3600);
            pcntl_signal_dispatch();
            $this->manageChildren();
        }

        return $this->exitCode;
    }

    /**
     * Marks the parent process terminated and signals all children. The main
     * event loop will eventually terminate after this call.
     *
     * @param int $exitCode to return
     * @return void
     */
    protected function shutdownParent(int $exitCode)
    {
        $this->terminate = true;
        $this->exitCode = $exitCode;
        $this->signalChildren(SIGTERM);

        if ($this->acceptorSocket) {
            socket_close($this->acceptorSocket);
        }
    }

    /**
     * @return int exit code of the process (child or parent)
     */
    public function main() : int
    {
        $this->initParent();
        return $this->runParent();
    }
}
