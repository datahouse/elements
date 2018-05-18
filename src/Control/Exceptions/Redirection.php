<?php

namespace Datahouse\Elements\Control\Exceptions;

/**
 * Class Redirection, which eventually turns into a HTTP redirect.
 *
 * @package Datahouse\Elements\Control\Exceptions
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class Redirection extends RouterException
{
    /** @var int $statusCode */
    private $statusCode;
    /** @var string $targetUrl target for redirection */
    private $targetUrl;

    /**
     * Redirection constructor.
     *
     * @param int    $code      HTTP status code for redirection (must be one
     *                          of 301, 303, 307 or 308)
     * @param string $targetUrl redirection target
     */
    public function __construct($code, $targetUrl)
    {
        // Please don't use 302.
        assert($code === 301 || $code === 303
            || $code === 307 || $code === 308);
        $this->statusCode = $code;
        $this->targetUrl = $targetUrl;
        parent::__construct('redirection: ' . $code . ' to ' . $targetUrl);
    }

    /**
     * @return int HTTP status code
     */
    public function getStatusCode() : int
    {
        return $this->statusCode;
    }

    /**
     * @return string
     */
    public function getTargetUrl()
    {
        return $this->targetUrl;
    }
}
