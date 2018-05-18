<?php

namespace Datahouse\Elements\Control\Exceptions;

/**
 * NoUrlPointer associated with the given element.
 *
 * @package Datahouse\Elements\Control\Exceptions
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class NoUrlPointer extends \RuntimeException
{
    /**
     * @param string $elementId requested
     */
    public function __construct($elementId)
    {
        parent::__construct("No UrlPointer for element $elementId");
    }
}
