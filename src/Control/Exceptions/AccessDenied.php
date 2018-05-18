<?php

namespace Datahouse\Elements\Control\Exceptions;

/**
 * Class ResourceNotFoundError, which eventually turns into a HTTP 404
 *
 * @package Datahouse\Elements\Control\Exceptions
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class AccessDenied extends RouterException
{
    /**
     * @return int HTTP status ccode
     */
    public function getStatusCode() : int
    {
        return 403;
    }
}
