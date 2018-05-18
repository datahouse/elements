<?php

namespace Datahouse\Elements\Presentation\TwigExtensions\Elements;

use Twig_Error_Syntax;
use Twig_Node_Expression_Constant;
use Twig_Token;
use Twig_Node;

/**
 * @package Datahouse\Elements\Presentation\TwigExtensions\Elements
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class FieldTokenParser extends BaseEleTokenParser
{
    /**
     * Parses a token and returns a node.
     *
     * @param Twig_Token $token token to parse
     * @return Twig_Node parser result
     * @throws Twig_Error_Syntax
     */
    public function parse(Twig_Token $token)
    {
        $lineno = $token->getLine();
        $stream = $this->parser->getStream();

        $staticFieldName = $stream->expect(Twig_Token::NAME_TYPE)->getValue();
        $args = $this->parseKeyValuePairs($stream, [
            'tag' => false,
            'class' => false,
            'fieldname' => false
        ]);
        $stream->expect(Twig_Token::BLOCK_END_TYPE);

        if (array_key_exists('fieldname', $args)) {
            // A bit of special magic for the snippetEditor template, where
            // we need to set fieldNames dynamically.
            assert($staticFieldName == '__dynamic');
            $fieldName = $args['fieldname'];
        } else {
            $fieldName = new Twig_Node_Expression_Constant(
                $staticFieldName,
                $lineno
            );
        }

        $defaultTag = new Twig_Node_Expression_Constant("div", $lineno);
        return new FieldNode(
            $fieldName,
            $args['tag'] ?? $defaultTag,
            $args['class'] ?? null,
            $token->getLine(),
            $this->getTag()
        );
    }

    /**
     * @return string the tag name: 'elements'
     */
    public function getTag()
    {
        return 'elefield';
    }
}
