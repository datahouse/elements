<?php

namespace Datahouse\Elements\Control;

/**
 * Represents a JSON response for use with (admin) ajax requests, maybe others
 * as well.
 *
 * @package Datahouse\Elements\Control
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
interface IJsonResponse
{
    /**
     * @return bool whether or not this can be considered a successful response
     */
    public function isSuccess() : bool;

    /**
     * @return int http response code
     */
    public function getCode() : int;

    /**
     * @return array entire response as a serializable array
     */
    public function asArray() : array;
}
