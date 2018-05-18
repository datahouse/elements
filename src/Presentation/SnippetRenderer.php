<?php

namespace Datahouse\Elements\Presentation;

/**
 * General purpose renderer for snippets, allowing the user (admin) to edit
 * all fields of a snippet, without WYSIWYG capabilities.
 *
 * @package Datahouse\Elements\Presentation
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class SnippetRenderer extends TwigRenderer
{
    /**
     * SnippetRenderer constructor, hard-wired to the snippetEditor template.
     */
    public function __construct()
    {
        parent::__construct('elements/snippetEditor.html');
    }
}
