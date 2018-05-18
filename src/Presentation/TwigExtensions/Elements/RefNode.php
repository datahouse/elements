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
class RefNode extends BaseEleNode
{
    /**
     * @param Twig_Node      $refName name of the reference
     * @param Twig_Node      $body    to render per referenced element
     * @param Twig_Node      $else    to render if there's no element
     * @param Twig_Node      $htmlTag used to wrap contents for froala
     * @param Twig_Node|null $class   for styling
     * @param int            $lineno  line number
     * @param string         $tag     tag name associated with the Node
     */
    public function __construct(
        Twig_Node $refName,
        Twig_Node $body,
        Twig_Node $else,
        Twig_Node $htmlTag,
        $class,
        int $lineno,
        $tag = null
    ) {
        $nodes = [
            'refName' => $refName,
            'body' => $body,
            'else' => $else,
            'tag' => $htmlTag,
            'class' => $class
        ];
        parent::__construct($nodes, [], $lineno, $tag);
    }

    /**
     * @param Twig_Compiler $compiler to write code to
     * @return void
     */
    protected function compileRefOpeningTag(Twig_Compiler $compiler)
    {
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
            ->write("'eleref-' .")
            ->subcompile($this->getNode('refName'))
            ->write(";\n")
            ->write("if (\$context['permissions']['admin'] ?? false) {\n")
            ->indent()
            ->write("echo ' elements__admin elements__reference';\n")
            ->write("if (\$selectable) {\n")
            ->indent()
            ->write("echo ' elements__selectable';\n")
            ->write(
                "echo \$selectedElementId == (\$ref['element_id'] ?? false) "
                . " ? ' elements__selected' : ' elements__inactive';\n"
            )
            ->outdent()
            ->write("}\n")
            ->outdent()
            ->write("}\n")
            ->write("echo '\"';\n")  // end of the class=".." attribute
            ->write("if (\$context['permissions']['admin'] ?? false) {\n")
            ->indent()
            ->write("echo ' data-ref-element-id=\"' . (\$ref['element_id'] ?? '') . '\"';\n")
            ->outdent()
            ->write("}\n")
            ->write("echo '>';\n");
    }

    /**
     * @param Twig_Compiler $compiler to write code to
     * @return void
     */
    protected function compileRefClosingTag(Twig_Compiler $compiler)
    {
        $this->writeAdminControlButtons($compiler);

        $compiler
            ->write("echo '</' . ")
            ->subcompile($this->getNode('tag'))
            ->write(" . '>';\n");
    }

    /**
     * Writes the sub block of php code that outpus the 'body', possibly
     * looping over all options for admins.
     *
     * @param Twig_Compiler $compiler to write code to
     * @return void
     */
    protected function emitSubBlockWriter(Twig_Compiler $compiler)
    {
        $compiler
            ->write("if (\$selectable || \$direct) {\n")
            ->indent();
        {
            $this->getLoopElements($compiler);

            $le_var = "\$context['_loop_elements']";
            $compiler
                ->write("$le_var = \$loop_elements;\n")
                ->write("if (\$context['permissions']['admin'] ?? false) {\n")
                ->indent()
                ->write("\$emitElseBlock = true;\n")
                ->outdent()
                ->write("} else {\n")
                ->indent()
                ->write("\$emitElseBlock = count($le_var) == 0;\n")
                ->outdent()
                ->write("}\n");

            // compile the contained body with the _ref_element.
            $this->writeEleLoopHead($compiler);
            $this->compileRefOpeningTag($compiler);

            // compile the contained body with the _ref_element.
            $compiler->subcompile($this->getNode('body'));

            $this->compileRefClosingTag($compiler);
            $this->writeEleLoopTail($compiler);

            // Restore the original context.
            $compiler->write("array_pop(\$context['_ref_names']);\n");
        }
        $compiler->outdent()->write("} else {\n")->indent();
        {
            $compiler
                ->write("if (isset(\$ref)) {\n")
                ->indent();

            $this->compileRefOpeningTag($compiler);
            $compiler
                ->write("\$context['_ref_needs_loop_indices'] = true;\n")
                ->subcompile($this->getNode('body'));
            $this->compileRefClosingTag($compiler);

            $compiler
                ->outdent()
                ->write("} else {\n")
                ->indent()
                ->write("\$emitElseBlock = true;")
                ->outdent()
                ->write("}\n");
        }
        $compiler->outdent()->write("}\n");
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

        $this->getLinkedElement($compiler, 'ref', $this->getNode('refName'));

        $compiler
            ->write("\$selectable = \$ref['selectable'] ?? false;\n")
            ->write("\$direct = \$ref['direct'] ?? false;\n")
            ->write("\$selectedElementId = \$ref['selected'] ?? '';\n");

        // Emit code to check if there is a referenced element.
        $compiler
            ->write("if (is_null(\$ref)) {\n")
            ->indent()
            ->write("error_log('reference ' .")
            ->subcompile($this->getNode('refName'))
            ->write(" . ' used in template but not defined');\n")
            ->write("\$emitElseBlock = true;\n")
            ->outdent()
            ->write("} else {\n")
            ->write("assert(array_key_exists('children', \$ref));\n")
            ->write("\$selectedElementId = \$ref['selected'] ?? '';\n");

        $this->saveParentContext($compiler);
        $this->setElementContextFromRef($compiler);

        $compiler
            ->write("\$context['_ref_names'][] = ")
            ->subcompile($this->getNode('refName'))
            ->write(";\n")
            ->write("\$context['_ref_type'] = 'ref';\n")
            ->write("\$context['_loop_offset'] = 0;\n")
            ->write("\$emitElseBlock = false;\n");

        $this->emitSubBlockWriter($compiler);

        $compiler->write("array_pop(\$context['_ref_names']);\n");
        $this->restoreParentContext($compiler);

        $compiler->write("}\n");

        // emit code for the else block, if any - no context switching in this
        // case
        if ($this->getNode('else') !== null) {
            $compiler
                ->write("if (\$emitElseBlock) {\n")
                ->indent();

            $this->saveParentContext($compiler);

            // Delete any possible element reference, mark this as the 'empty'
            // element, i.e. no reference defined. The admin might possibly
            // see this, but needs to be able to select another element.
            $compiler->write("\$ref['element_id'] = '';\n");

            $compiler
                ->write("if (\$selectable && !\$direct) {\n")
                ->indent();
            $this->compileRefOpeningTag($compiler);
            $compiler
                ->outdent()
                ->write("}\n");

            $compiler->subcompile($this->getNode('else'));

            $compiler
                ->write("if (\$selectable && !\$direct) {\n")
                ->indent();
            $this->compileRefClosingTag($compiler);
            $compiler
                ->outdent()
                ->write("}\n");

            $this->restoreParentContext($compiler);

            $compiler
                ->outdent()
                ->write("}\n");
        }
    }

    /**
     * @param Twig_Compiler $compiler to output php code to
     * @return void
     */
    protected function writeAdminControlButtons(Twig_Compiler $compiler)
    {
        // Depending on admin rights, we add another div for the admin button
        $compiler
            ->write("if (\$selectable && !\$direct &&\n")
            ->write("(\$context['permissions']['admin'] ?? false)\n")
            ->write(") {\n")
            ->indent()
            ->write(" echo '<div data-ref-name=\"' . ")
            ->subcompile($this->getNode('refName'))
            ->write(" . '\" data-element-id=\"' . (\$context['_parent_context']['element_id'] ?? '') . '\" "
                . "data-selected=\"' . \$selectedElementId . '\" "
                . "class=\"elements__admin elements__panel elements__inactive\">"
                . "<div class=\"elements__buttons\">"
                . "<div class=\"elements__btn\" data-action=\"prev\">"
                . "<i class=\"fa fa-chevron-left\" aria-hidden=\"true\"></i>"
                . "</div>"
                . "<div class=\"elements__btn\" data-action=\"next\">"
                . "<i class=\"fa fa-chevron-right\" aria-hidden=\"true\"></i>"
                . "</div></div></div>';\n")
            ->outdent()
            ->write("}\n");
    }
}
