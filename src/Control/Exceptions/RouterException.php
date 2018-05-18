<?php

namespace Datahouse\Elements\Control\Exceptions;

/**
 * Class RouterException, just to classify these types of exceptions.
 *
 * @package Datahouse\Elements\Control\Exceptions
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
abstract class RouterException extends \RuntimeException
{
    /**
     * @return int HTTP status code to return to the browser
     */
    abstract public function getStatusCode() : int;
}
