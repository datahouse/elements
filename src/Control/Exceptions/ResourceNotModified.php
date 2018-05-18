<?php

namespace Datahouse\Elements\Control\Exceptions;

/**
 * Class ResourceNotModified, which eventually turns into a HTTP 304
 *
 * @package Datahouse\Elements\Control\Exceptions
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class ResourceNotModified extends RouterException
{
    private $ttl;

    /**
     * @param int         $ttl in seconds
     * @param string|null $msg optional message
     */
    public function __construct(int $ttl, string $msg = null)
    {
        parent::__construct($msg);
        $this->ttl = $ttl;
    }

    /**
     * @return int HTTP status ccode
     */
    public function getStatusCode() : int
    {
        return 304;
    }

    /**
     * @return int time to live (in browser cache) in seconds
     */
    public function getTimeToLive() : int
    {
        return $this->ttl;
    }
}
