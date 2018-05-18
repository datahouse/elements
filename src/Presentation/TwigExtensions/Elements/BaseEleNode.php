<?php

namespace Datahouse\Elements\Presentation\TwigExtensions\Elements;

use Twig_Compiler;
use Twig_Node;

/**
 * Sports some useful helper methods used in multiple nodes.
 *
 * @package Datahouse\Elements\Presentation\TwigExtensions\Elements
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class BaseEleNode extends Twig_Node
{
    /**
     * Emits php code to lookup a linked element by name. Stores the result in
     * the local variable ref.
     *
     * @param Twig_Compiler $compiler to output php code to
     * @param string        $type     'ref' or 'sub'
     * @param Twig_Node     $name     reference name
     * @return void
     */
    public function getLinkedElement(
        Twig_Compiler $compiler,
        string $type,
        Twig_Node $name
    ) {
        $compiler
            ->write("\$ref = \$context['$type" . "s'][")
            ->subcompile($name)
            ->write("] ?? null;\n");
    }

    /**
     * Emits php code to pop a child element.
     *
     * @param Twig_Compiler $compiler to use to emit php code to
     * @return void
     */
    public function popNextChild(Twig_Compiler $compiler)
    {
        $compiler->write("\$ref = array_shift(\$context['_loop_elements']);\n");
    }

    /**
     * Emits php code to adjusts all of the context for the element in
     * variable ref, as populated with getLinkedElement or popNextChild
     *
     * @param Twig_Compiler $compiler to use to emit php code to
     * @return void
     */
    public function setElementContextFromRef(Twig_Compiler $compiler)
    {
        $compiler
            ->write(
                "\$context['_ref_element'] = \$ref;\n" .
                "\$context['element_id'] = \$ref['element_id'] ?? null;\n" .
                "\$context['fields'] = \$ref['fields'] ?? [];\n" .
                "\$context['fieldInfo'] = \$ref['fieldInfo'] ?? [];\n" .
                "\$context['children'] = \$ref['children'] ?? [];\n" .
                "\$context['subs'] = \$ref['subs'] ?? [];\n" .
                "\$context['refs'] = \$ref['refs'] ?? [];\n"
            );
    }

    /**
     * Emits php code to save the current element's context onto the stack so
     * it can freely be modified and adjusted to another element's context.
     *
     * @param Twig_Compiler $compiler to output php code to
     * @return void
     */
    public function saveParentContext(Twig_Compiler $compiler)
    {
        // FIXME: rather than replacing the entire context and copying only
        // individual fields, this should default to keep all fields by default
        // and save only individual fields to the parent_context.

        $compiler
            ->write(
                "\$perms = \$context['permissions'] ?? [];\n" .
                "\$ref_names = \$context['_ref_names'] ?? [];\n" .
                "unset(\$context['_ref_names']);\n" .
                "\$context = ['_parent_context' => \$context];\n" .
                "\$context['_ref_names'] = \$ref_names;\n" .
                "\$context['permissions'] = \$perms;\n"
            );

        $compiler
            ->write("if (array_key_exists('_ref_type', \$context['_parent_context'])) {\n")
            ->indent()
            ->write("\$context['_ref_type'] = \$context['_parent_context']['_ref_type'];\n")
            ->outdent()
            ->write("}");
    }

    /**
     * Emits php code for the twig template to restore a previously saved
     * context of the parent element.
     *
     * @param Twig_Compiler $compiler to output php code to
     * @return void
     */
    public function restoreParentContext(Twig_Compiler $compiler)
    {
        $compiler->write(
            "\$ref_names = \$context['_ref_names'] ?? [];\n" .
            "\$context = \$context['_parent_context'];\n" .
            "\$context['_ref_names'] = \$ref_names;\n"
        );
    }

    /**
     * @param Twig_Compiler $compiler to output php code to
     * @return void
     */
    protected function getLoopElements(Twig_Compiler $compiler)
    {
        // For sub elements, we simply get an array of elements. For
        // referenced elements, we allow loops over the children of the
        // referenced element.
        $compiler
            ->write("assert(is_array(\$context['_ref_element']));\n")
            ->write(
                "if (array_key_exists('children', " .
                "\$context['_ref_element'])) {\n"
            )
            ->indent()
            ->write("\$loop_elements = \$context['_ref_element']['children'];\n")
            ->write("if (\$context['_ref_needs_loop_indices'] ?? false) {\n")
            ->indent()
            ->write("\$ref_names_idx = count(\$context['_ref_names']);\n")
            ->outdent()
            ->write("} else {\n")
            ->indent()
            ->write("\$ref_names_idx = -1;\n")
            ->outdent()
            ->write("}\n")
            ->outdent()
            ->write("} else {\n")
            ->indent()
            ->write("\$loop_elements = \$context['_ref_element'];\n")
            ->write("\$ref_names_idx = count(\$context['_ref_names']);\n")
            ->outdent()
            ->write("}\n");
    }

    /**
     * @param Twig_Compiler $compiler to output php code to
     * @return void
     */
    protected function writeEleLoopHead(Twig_Compiler $compiler)
    {
        $le_var = "\$context['_loop_elements']";

        // Given this is a loop, we expect multiple sub elements. To be able
        // to properly reference those from Froala, we add an index to
        // ref_names.
        $compiler
            ->write("if (\$ref_names_idx > 0) {\n")
            ->indent()
            ->write(
                "\$context['_ref_names'][] = " .
                "\$context['_parent_context']['_ref_loop_offset'] " .
                "?? \$context['_loop_offset'];\n"
            )
            ->outdent()
            ->write("}\n");

        $compiler->write(
            "while (count($le_var) > 0) {\n"
        );
        $compiler->indent();

        $this->popNextChild($compiler);
        $this->setElementContextFromRef($compiler);

        // Also set the eleadmin context field
        $compiler
            ->write("assert(array_key_exists('_ref_type', \$context));\n")
            ->write("if (\$context['_ref_type'] == 'sub') {\n")
            ->indent()
            ->write(
                "\$context['eleadmin'] = '<div class=\"elements__admin "
                . "elements__panel elements__inactive\""
                . "data-sub-name=\"' . \$context['_ref_names'][count(\$context['_ref_names']) - 2] . '\" "
                . "data-sub-index=\"' . \$context['_ref_names'][count(\$context['_ref_names']) - 1] . '\" "
                . "data-element-id=\"' . (\$context['_parent_context']['element_id'] ?? '') . '\"/>"
                . "<div class=\"elements__buttons\">"
                . "<div class=\"elements__btn\" data-action=\"delete\">"
                . "<i class=\"fa fa-trash-o\" aria-hidden=\"true\"></i>" .
                "</div></div></div>';"
            )
            ->outdent()
            ->write("}\n");
    }

    /**
     * @param Twig_Compiler $compiler to output php code to
     * @return void
     */
    protected function writeEleLoopTail(Twig_Compiler $compiler)
    {
        // increment the index for sub elements
        $compiler
            ->write("if (\$ref_names_idx >= 0) {\n")
            ->indent()
            ->write("\$context['_ref_names'][\$ref_names_idx] += 1;\n")
            ->outdent()
            ->write("}\n");

        $compiler->outdent();
        $compiler->write("}\n");  // end of the loop
    }
}
