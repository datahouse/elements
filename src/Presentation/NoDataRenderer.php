<?php

namespace Datahouse\Elements\Presentation;

use Datahouse\Elements\Control\AssetHandler;

/**
 * Renderer for AJAX requests that need or must nor return data (PUT or
 * DELETE come to mind).
 *
 * @package Datahouse\Elements\Presentation
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class NoDataRenderer implements IRenderer
{
    /**
     * Just a sanity check to make sure we're not accidentally trying to
     * emit data. Shouldn't be called at all.
     *
     * @param array $data for the template to render
     * @return void
     */
    public function setTemplateData(array $data)
    {
        assert(count($data) == 0);
    }

    /**
     * Use the given data and the template this class represents and render
     * it, returning the result. Returning null means there's no data to
     * send back to the client.
     *
     * @param AssetHandler $assetHandler to use for assets
     * @return null
     * @SuppressWarnings(PHPMD.UnusedFormalParameter('assetHandler')
     */
    public function render(AssetHandler $assetHandler)
    {
        return null;
    }
}
