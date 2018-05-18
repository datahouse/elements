<?php

namespace Datahouse\Elements\Presentation\TwigExtensions\Elements;

use Twig_Compiler;
use Twig_Node;

/**
 * Given a referenced element, this node allows switching contexts to that
 * element, making subsequent elefield tags emit data from that referenced
 * element.
 *
 * In theory, this allows unlimited nesting. In practice, only a single level
 * of references has ever been tested.
 *
 * @package Datahouse\Elements\Presentation\TwigExtensions\Elements
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
class SubNode extends BaseEleNode
{
    /**
     * @param Twig_Node      $subName name of the reference
     * @param Twig_Node      $body    to render per referenced element
     * @param Twig_Node      $else    to render if there's no element
     * @param Twig_Node      $htmlTag used to wrap contents for froala
     * @param Twig_Node|null $class   for styling
     * @param int            $lineno  line number
     * @param string         $tag     tag name associated with the Node
     */
    public function __construct(
        Twig_Node $subName,
        Twig_Node $body,
        Twig_Node $else,
        Twig_Node $htmlTag,
        $class,
        int $lineno,
        $tag = null
    ) {
        $nodes = [
            'subName' => $subName,
            'body' => $body,
            'else' => $else,
            'tag' => $htmlTag,
            'class' => $class
        ];
        parent::__construct($nodes, [], $lineno, $tag);
    }

    /**
     * Compiles the node to PHP code.
     *
     * @param Twig_Compiler $compiler A Twig_Compiler instance
     * @return void
     */
    public function compile(Twig_Compiler $compiler)
    {
        $compiler->addDebugInfo($this);

        $this->getLinkedElement($compiler, 'sub', $this->getNode('subName'));

        // Line of PHP code to emit the opening tag...
        $compiler
            ->write("echo '<' . ")
            ->subcompile($this->getNode('tag'))
            ->write(" . ' class=\"' . ");
        if (null !== $this->getNode('class')) {
            $compiler
                ->subcompile($this->getNode('class'))
                ->write(" . ' ' . ");
        }

        // Emit code to write proper css class
        $compiler
            ->write("'elesub-' .")
            ->subcompile($this->getNode('subName'))
            ->write(";\n")
            ->write("if (\$context['permissions']['admin'] ?? false) {\n")
            ->indent()
            ->write("echo ' admin elements__sub';\n")
            ->outdent()
            ->write("}\n")
            ->write("echo '\">';\n");

        // Emit code to check if there is a referenced element.
        $compiler
            ->write("if (isset(\$ref)) {\n")
            ->indent();

        $this->saveParentContext($compiler);
        $this->setElementContextFromRef($compiler);

        // The 'collection' for sub elements basically is the parent
        // element. Assign its element id to the current context, as the above
        // setter won't have any element_id for the collection, otherwise.
        $compiler->write(
            "\$context['element_id'] =\n"
            . " \$context['_parent_context']['element_id'] ?? null;\n"
        );

        $compiler
            ->write("\$context['_ref_type'] = 'sub';\n")
            ->write("\$context['_ref_names'][] = ")
            ->subcompile($this->getNode('subName'))
            ->write(";\n");

        // compile the contained body with the _ref_element.
        $compiler->subcompile($this->getNode('body'));

        $compiler->write("array_pop(\$context['_ref_names']);\n");
        $this->restoreParentContext($compiler);

        // close the condition on 'referenced-element-exists'
        $compiler
            ->outdent()
            ->write("}\n");

        // emit code for the else block, if any - no context switching in this
        // case
        if ($this->getNode('else') !== null) {
            $compiler
                ->write("else {\n")
                ->indent()
                ->subcompile($this->getNode('else'))
                ->outdent()
                ->write("}\n");
        }

        // .. and the closing tag.
        $compiler
            ->write("echo '</' . ")
            ->subcompile($this->getNode('tag'))
            ->write(" . '>';\n");

        // Depending on admin rights, we add another div for the admin button
        $compiler
            ->write("if (\$context['permissions']['admin'] ?? false) {\n")
            ->indent()
            ->write(" echo '<div data-sub-name=\"' . ")
            ->subcompile($this->getNode('subName'))
            ->write(" . '\" data-element-id=\"' . (\$context['element_id'] ?? null) . '\" "
            . "class=\"elements__admin elements__add-button\"></div>';\n")
            ->outdent()
            ->write("}\n");
    }
}
