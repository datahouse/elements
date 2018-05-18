<?php

namespace Datahouse\Elements\Control\Admin;

use Datahouse\Elements\Control\BaseController;
use Datahouse\Elements\Presentation\TwigRenderer;

/**
 * Abstract class AdminPageController, the base for all pages of the admin
 * frontend.
 *
 * @package Datahouse\Elements\Control\Admin
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
abstract class AdminPageController extends BaseController
{
    /**
     * Directly resolves the class name to a template file name and
     * instantiates a TwigBaseTemplate with data from processAdminRequest.
     *
     * @return TwigRenderer
     */
    public function getAdminRenderer() : TwigRenderer
    {
        $class_name = strtolower(get_class($this));
        // strip the first 33 chars, which should always be the same.
        assert(substr($class_name, 0, 33)
            == 'datahouse\\elements\\control\\admin\\');
        $template_file = 'elements/' . substr($class_name, 33) . '.html';
        return new TwigRenderer($template_file);
    }
}
