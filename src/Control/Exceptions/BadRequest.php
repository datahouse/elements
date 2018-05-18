<?php

namespace Datahouse\Elements\Control\Exceptions;

/**
 * BadRequest exception, turns into a 400.
 *
 * @package Datahouse\Elements\Control\Exceptions
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class BadRequest extends RouterException
{
    /**
     * @return int HTTP status ccode
     */
    public function getStatusCode() : int
    {
        return 400;
    }
}
