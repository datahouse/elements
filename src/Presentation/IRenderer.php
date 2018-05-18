<?php

namespace Datahouse\Elements\Presentation;

use Datahouse\Elements\Control\AssetHandler;

/**
 * A trivial interface used by controllers to render data to some final
 * format.
 *
 * @package Datahouse\Elements\Presentation
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
interface IRenderer
{
    /**
     * @param array $data for the template to render
     * @return void
     */
    public function setTemplateData(array $data);

    /**
     * @param AssetHandler $assetHandler to use during rendering for assets.
     * @return string[]|null pair of string for type and content, if applicable
     * @throws \Exception
     */
    public function render(AssetHandler $assetHandler);
}
