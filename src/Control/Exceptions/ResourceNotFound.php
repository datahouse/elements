<?php

namespace Datahouse\Elements\Control\Exceptions;

/**
 * Class ResourceNotFoundError, which eventually turns into a HTTP 404
 *
 * @package Datahouse\Elements\Control\Exceptions
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class ResourceNotFound extends RouterException
{
    private $redirectionUrl;

    /**
     * ResourceNotFoundError constructor.
     *
     * @param string|null $msg            additional information to present to
     *                                    the end user
     * @param string|null $redirectionUrl the last URL the system redirected
     *                                    the requset to, if any
     */
    public function __construct($msg = null, $redirectionUrl = null)
    {
        parent::__construct($msg);
        $this->redirectionUrl = $redirectionUrl;
    }

    /**
     * @return int HTTP status ccode
     */
    public function getStatusCode() : int
    {
        return 404;
    }

    /**
     * @return string the original URL requested
     */
    public function getRedirectionUrl()
    {
        return $this->redirectionUrl;
    }
}
