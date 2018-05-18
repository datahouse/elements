<?php

namespace Datahouse\Elements\Presentation;

/**
 * Additional requirements for page definitions.
 *
 * @package Datahouse\Elements\Presentation
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
abstract class BasePageDefinition extends BaseElementDefinition
{
    /**
     * @return string name of the page definition shown to the admin user
     */
    abstract public function getDisplayName() : string;

    /**
     * @return IRenderer to use for this page
     */
    abstract public function getRenderer() : IRenderer;
}
