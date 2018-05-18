<?php

namespace Datahouse\Elements\Control;

use Datahouse\Elements\Presentation\IRenderer;

/**
 * Simple structure encapsulating an HTTP response.
 *
 * @package Datahouse\Elements\Control
 * @author      Helmar Tröller (htr) <helmar.troeller@datahouse.ch>
 * @license (c) 2014 - 2016 by Datahouse AG
 */
class HttpResponse
{
    /* @var int $statusCode */
    protected $statusCode;
    /* @var IRenderer $renderer */
    protected $renderer;

    /**
     * getStatusCode
     *
     * @return mixed
     */
    public function getStatusCode() : int
    {
        return $this->statusCode;
    }

    /**
     * setStatusCode
     *
     * @param mixed $statusCode statusCode
     * @return void
     */
    public function setStatusCode(int $statusCode)
    {
        $this->statusCode = $statusCode;
    }

    /**
     * @return IRenderer
     */
    public function getRenderer() : IRenderer
    {
        return $this->renderer;
    }

    /**
     * @param IRenderer $renderer renderer to set
     * @return void
     */
    public function setRenderer(IRenderer $renderer)
    {
        $this->renderer = $renderer;
    }
}
