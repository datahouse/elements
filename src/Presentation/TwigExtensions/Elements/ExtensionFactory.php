<?php

namespace Datahouse\Elements\Presentation\TwigExtensions\Elements;

use Twig_Environment;

/**
 * Helper class to register all pieces of elements that extend twig.
 *
 * @package Datahouse\Elements\Presentation\TwigExtensions\Elements
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class ExtensionFactory
{
    /**
     * @param Twig_Environment $twig to register the extension with
     * @return void
     */
    public static function registerExtensions(Twig_Environment $twig)
    {
        $twig->addTokenParser(new FieldTokenParser());
        $twig->addTokenParser(new LoopTokenParser());
        $twig->addTokenParser(new RefTokenParser());
        $twig->addTokenParser(new SubTokenParser());
    }
}
