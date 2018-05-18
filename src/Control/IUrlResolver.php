<?php

namespace Datahouse\Elements\Control;

use Datahouse\Elements\Abstraction\Element;
use Datahouse\Elements\Abstraction\UrlPointer;

/**
 * Interface of the URL to Element resolver
 *
 * @package Datahouse\Elements\Control
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
interface IUrlResolver
{
    /**
     * @param string $startUrl to look up
     * @return array tuple of elementId (string|null) and a
     *               redirectUrl (again of type string|null)
     */
    public function lookupUrl(string $startUrl) : array;

    /**
     * @param Element $element to be linked to
     * @return UrlPointer pointing to the given element
     */
    public function getLinkForElement(Element $element) : UrlPointer;
}
