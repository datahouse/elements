<?php

namespace Datahouse\Elements\Presentation;

/**
 * Empty implementations for references and menus of @see IElementDefinition
 *
 * @package Datahouse\Elements\Presentation
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
abstract class BaseElementDefinition implements IElementDefinition
{
    /**
     * @return array empty, i.e. no sub elements by default
     */
    public function getKnownSubElements() : array
    {
        return [];
    }

    /**
     * @return array empty, i.e. no references by default
     */
    public function getKnownReferences() : array
    {
        return [];
    }

    /**
     * @return array empty, i.e. no menus by default
     */
    public function getRequiredMenus() : array
    {
        return [];
    }
}
