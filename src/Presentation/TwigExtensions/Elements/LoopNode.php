<?php

namespace Datahouse\Elements\Presentation\TwigExtensions\Elements;

use Twig_Compiler;
use Twig_Node;

/**
 * Somewhat like the for loop of twig itself, but much simpler and
 * automatically looping over children of the reference, selected from the
 * context.
 *
 * @package Datahouse\Elements\Presentation\TwigExtensions\Elements
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class LoopNode extends BaseEleNode
{
    /**
     * @param Twig_Node      $body   to render per referenced element
     * @param Twig_Node|null $every  argument 'every'
     * @param Twig_Node|null $limit  limit on the number of elements
     * @param Twig_Node|null $offset into the array
     * @param int            $lineno line number
     * @param string         $tag    tag name associated with the Node
     */
    public function __construct(
        Twig_Node $body,
        $every,
        $limit,
        $offset,
        int $lineno,
        $tag = null
    ) {
        $nodes = [
            'body' => $body,
            'every' => $every,
            'limit' => $limit,
            'offset' => $offset
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

        $this->getLoopElements($compiler);
        $this->saveParentContext($compiler);

        $le_var = "\$context['_loop_elements']";
        $compiler
            ->write("\$offset = ")
            ->subcompile($this->getNode('offset'))
            ->write(";\n")
            ->write("\$offset = \$offset < 0 ? 0 : \$offset;\n")
            ->write("\$limit = ")
            ->subcompile($this->getNode('limit'))
            ->write(";\n")
            ->write("\$limit = \$limit <= 0 ? null : \$limit;\n")
            ->write("$le_var = array_slice(\$loop_elements, \$offset, \$limit);\n");

        $every = $this->getNode('every');
        if (isset($every)) {
            // Assemble a nested array of children
            $compiler
                ->write("\$groups = [];\n\$step = ")
                ->subcompile($every)
                ->write(";\n")
                ->write(
                    "for (\$i = 0; " .
                    "\$i < count($le_var); " .
                    "\$i += \$step) {\n"
                )
                ->indent()
                ->write("\$groups[] = [\$i + \$offset, array_slice($le_var, \$i, \$step)];\n")
                ->outdent()
                ->write("}\n");

            // For each group of children, compile the contained body.
            $compiler
                ->write("foreach (\$groups as list (\$off, \$group)) {\n")
                ->indent()
                ->write("\$context['_ref_element'] = \$group;\n")
                ->write("\$context['_ref_type'] = \$context['_parent_context']['_ref_type'];\n")
                ->write("\$context['element_id'] = \$context['_parent_context']['element_id'] ?? null;\n")
                ->write("\$context['_ref_loop_offset'] = \$off;\n")
                ->subcompile($this->getNode('body'))
                ->outdent()
                ->write("}\n");
        } else {
            $compiler->write("\$context['_loop_offset'] = \$offset;\n");
            $this->writeEleLoopHead($compiler);

            // compile the contained body with the _ref_element.
            $compiler->subcompile($this->getNode('body'));

            $this->writeEleLoopTail($compiler);
        }

        // Restore the original context.
        $compiler->write("array_pop(\$context['_ref_names']);\n");
        $this->restoreParentContext($compiler);
    }
}
