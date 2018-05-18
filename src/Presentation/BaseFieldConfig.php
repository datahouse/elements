<?php

namespace Datahouse\Elements\Presentation;

/**
 * Very basic Twig-based IPageTemplate implementation for website pages (as
 * opposed to the admin frontend) that does neither define links nor menus.
 * Use as a basis for custom twig page templates.
 *
 * @package Datahouse\Elements\Presentation
 * @author  Dena Moshfegh (dmo) <dena.moshfegh@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class BaseFieldConfig
{
    const ENTER_P = 0;
    const ENTER_DIV = 1;
    const ENTER_BR = 2;

    /**
     * @return array default froala with all options
     */
    public static function allOptions()
    {
        return [];
    }

    /**
     * @return array default froala config with all features except char counter
     */
    public static function defaultWithoutCount()
    {
        return [
            'froalaConfig' => [
                'charCounterCount' => false
            ],
            'type' => 'text'
        ];
    }

    /**
     * @return array very simple single line of text configuration
     */
    public static function singleLineOnlyText()
    {
        return [
            'froalaConfig' => [
                'toolbarButtons' => [
                    'subscript', 'superscript', '|',
                    'undo', 'redo', 'clearFormatting', 'selectAll'
                ],
                'charCounterCount' => false,
                'multiLine' => false,
                'linkStyles' => false,
                'shortcutsEnabled' => ['show', 'undo', 'redo'],
                'enter' => self::ENTER_BR
            ],
            'type' => 'text'
        ];
    }

    /**
     * @return array a very limited Froala configuration allowing multiple
     * lines but only very little formatting.
     */
    public static function multiLineOnlyText()
    {
        $config = self::singleLineOnlyText();
        $config['froalaConfig']['multiLine'] = true;
        $config['froalaConfig']['toolbarButtons'][] = 'insertLink';
        $config['froalaConfig']['enter'] = self::ENTER_P;
        return $config;
    }

    /**
     * @return array only image replacing and linking
     */
    public static function imageReplaceOnly()
    {
        return [
            'froalaConfig' => [
                'imageEditButtons' => [
                    'imageReplace', '|', 'imageAlt'
                ],
                'charCounterCount' => false,
                'shortcutsEnabled' => ['insertImage']
            ],
            'type' => 'image'
        ];
    }
}
