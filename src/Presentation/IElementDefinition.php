<?php

namespace Datahouse\Elements\Presentation;

/**
 * IElementDefinition interface that defines user-visible elements (formerly
 * called page templates, but that often led to confusion with the twig
 * templates).
 *
 * @package Datahouse\Elements\Presentation
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
interface IElementDefinition
{
    /**
     * @return array actually a map of field_names to.. an empty array, fo
     * now, might need to be extended in the future.
     */
    public function getKnownContentFields() : array;

    /**
     * @return array actually a map of sub element names to a definition
     */
    public function getKnownSubElements() : array;

    /**
     * @return array actually a map of reference names to a definition
     */
    public function getKnownReferences() : array;

    /**
     * @return array of required menu names to their definition.
     */
    public function getRequiredMenus() : array;
}
