<?php

namespace Datahouse\Elements\Control;

/**
 * A base class for all JSON responses.
 *
 * @package Datahouse\Elements\Control
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
abstract class BaseJsonResponse implements IJsonResponse
{
    protected $code;

    /**
     * @param int $code http response code
     */
    public function __construct(int $code)
    {
        $this->code = $code;
    }

    /**
     * @return bool whether or not this can be considered a successful response
     */
    public function isSuccess() : bool
    {
        return $this->code >= 200 && $this->code < 300;
    }

    /**
     * @return int http response code
     */
    public function getCode() : int
    {
        return $this->code;
    }

    /**
     * @param int $code http response code
     * @return void
     */
    public function setCode(int $code)
    {
        $this->code = $code;
    }
}
