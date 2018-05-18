<?php

namespace Datahouse\Elements\Presentation\TwigExtensions\Elements;

use RuntimeException;

use Twig_Compiler;
use Twig_Node;

/**
 * @package Datahouse\Elements\Presentation\TwigExtensions\Elements
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
class FieldNode extends Twig_Node
{
    /**
     * Generates a text field filled with contents from an element's field.
     * Editable with Froala.
     *
     * @param Twig_Node      $fieldName of the element
     * @param Twig_Node      $htmlTag   used to wrap contents for Froala
     * @param Twig_Node|null $class     for styling
     * @param int            $lineno    line number
     * @param string         $tag       tag name associated with the Node
     */
    public function __construct(
        Twig_Node $fieldName,
        Twig_Node $htmlTag,
        $class,
        int $lineno,
        $tag = null
    ) {
        $nodes = [
            'tag' => $htmlTag,
            'class' => $class,
            'fieldname' => $fieldName
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
        // Line of PHP code to emit the opening tag...
        $compiler->addDebugInfo($this);

        $compiler->write(
            "\$ref_names_add = empty(\$context['_ref_names']) ? '' : " .
            "(implode('-', \$context['_ref_names']) . '-');\n"
        );

        // Assemble classes to set for the outer tag, where Froala is added.
        $compiler
            ->write(
                "\$classes = ((\$context['permissions']['admin'] ?? false) "
                . "? 'admin ' : '') . "
                . "('__ele_field-' . \$ref_names_add . "
            )
            ->subcompile($this->getNode('fieldname'))
            ->write(")");
        if (null !== $this->getNode('class')) {
            $compiler
                ->write(" . ' ' . ")
                ->subcompile($this->getNode('class'));
        }
        $compiler->write(";\n");

        // .. and select the correct 'fields' for the content of the
        // element's field
        $compiler
            ->write("if (array_key_exists('_ref_element', \$context)) {\n")
            ->indent()
            ->write("\$fields = \$context['_ref_element']['fields'] ?? [];\n")
            ->outdent()
            ->write("} else {\n")
            ->indent()
            ->write("\$fields = \$context['fields'] ?? [];\n")
            ->outdent()
            ->write("}\n");

        $compiler
            ->write("\$fieldName = ")
            ->subcompile($this->getNode('fieldname'))
            ->write(";\n")
            ->write(
                "\$type = \$context['fieldInfo'][\$fieldName]['type']\n" .
                "?? 'text';\n"
            );

        $compiler
            ->write("if (\$type === 'text' || \$type === 'meta') {\n")
            ->indent()
            // Skip even the tag, if the field is empty
            ->write(
                "if (!empty(trim(\$fields[\$fieldName])) ||\n" .
                "    (\$context['permissions']['admin'] ?? false)\n" .
                ") {\n"
            )
            ->indent()
            // Emit code to write the opening tag..
            ->write("echo '<' . ")
            ->subcompile($this->getNode('tag'))
            ->write(" . ' class=\"' . \$classes . '\">';\n")
            // .. the content itself ..
            ->write("echo \$fields[\$fieldName] ?? '';\n")
            // .. and the closing tag.
            ->write("echo '</' . ")
            ->subcompile($this->getNode('tag'))
            ->write(" . '>';\n")
            ->outdent()
            ->write("}\n")
            ->outdent();

        // then check if it's an image type field
        $compiler
            ->write(
                "} elseif (\$type === 'image') {\n"
            )
            ->indent()
            // This writes PHP code to take appart the image tag, add the
            // class attribute, and then re-assemble the entire thing.
            ->write(
                "echo " . FieldNode::class .
                "::filterImageTag(\$fields[\$fieldName], \$classes);"
            )
            ->outdent();

        $compiler
            ->write(
                "} elseif (\$type === 'tag') {\n"
            )
            ->indent()
            ->write("assert(false, 'tag type fields are not allowed for elefield');\n")
            ->outdent();

        $compiler
            ->write("} else {\n")
            ->indent()
            ->write("assert(false, 'unknown field type: ' . \$type);\n")
            ->outdent()
            ->write("}\n");
    }

    /**
     * ATTENTION: Different Context: This function is run in the eval'd code
     * from Twig. Helps avoiding large batches of PHP writing PHP.
     *
     * @param string $value   to output
     * @param string $classes css class names to mix in
     * @return string to output directly
     */
    public static function filterImageTag(string $value, string $classes)
    {
        $pattern = '/<img([^\>]+)(?:\/>|>)/i';
        if (preg_match_all(
            $pattern,
            $value,
            $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        ) === false) {
            throw new RuntimeException("preg_match failed");
        }

        foreach (array_reverse($matches) as $match) {
            $startIdx = $match[0][1];
            $matchLen = strlen($match[0][0]);
            $imageTagAttributes = $match[1][0];

            $attributes = [];
            $pattern = '/([-\\w]+)=\\"([^\\"]*)\\"/';
            if (preg_match_all(
                $pattern,
                $imageTagAttributes,
                $matches,
                PREG_SET_ORDER
            ) === false) {
                throw new RuntimeException("preg_match failed");
            }

            // Collect all attributes of the tag.
            foreach ($matches as list(, $attrName, $attrValue)) {
                $attributes[$attrName] = $attrValue;
            }

            // Modify the class attribute, if any.
            if (array_key_exists('class', $attributes)) {
                $attributes['class'] .= ' ' . $classes;
            } else {
                $attributes['class'] = $classes;
            }

            $parts = [];
            foreach ($attributes as $attrName => $attrValue) {
                $parts[] = $attrName . '="' . $attrValue . '"';
            }
            $replacement = '<img ' . implode(' ', $parts) . '/>';
            $value = substr($value, 0, $startIdx)
                . $replacement
                . substr($value, $startIdx + $matchLen);
        }

        // As we're closing all image tags, we simply remove all separate
        // closing tags as well. This means we're also closing unclosed image
        // tags.
        return preg_replace('/\s*<\/img\s*>/', '', $value);
    }
}
