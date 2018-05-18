<?php

namespace Datahouse\Elements\Presentation\TwigExtensions\Elements;

use Twig_Error_Syntax;
use Twig_Node;
use Twig_Node_Expression_Constant;
use Twig_Token;
use Twig_TokenParser;
use Twig_TokenStream;

/**
 * Common base for elements token parsers extending Twig.
 *
 * @package Datahouse\Elements\Presentation\TwigExtensions\Elements
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
abstract class BaseEleTokenParser extends Twig_TokenParser
{
    /**
     * Process a simple list of key=value pairs.
     *
     * @param Twig_TokenStream $stream    to process
     * @param array            $knownKeys map of key name to mandatory (bool)
     * @return array map of key-value pairs
     * @throws Twig_Error_Syntax
     */
    public function parseKeyValuePairs(
        Twig_TokenStream $stream,
        array $knownKeys
    ) {
        $args = [];
        while ($stream->test(Twig_Token::NAME_TYPE)) {
            $key = $stream->expect(Twig_Token::NAME_TYPE)->getValue();
            $stream->expect(Twig_Token::OPERATOR_TYPE, '=');
            $value = $this->parser->getExpressionParser()->parseExpression();
            assert(is_string($key));
            if (array_key_exists($key, $knownKeys)) {
                $args[$key] = $value;
            } else {
                throw new Twig_Error_Syntax("unknown key: $key");
            }
        }
        foreach ($knownKeys as $key => $mandatory) {
            if ($mandatory && !array_key_exists($key, $args)) {
                throw new Twig_Error_Syntax("missing key: $key");
            }
        }
        return $args;
    }

    /**
     * Parse an entires elesub or eleref expression.
     *
     * @param Twig_Token $token     to start with
     * @param string     $nodeClass to create
     * @return BaseEleNode or rather a derived class of type $nodeClass
     * @throws Twig_Error_Syntax
     */
    protected function parseSubOrRef(Twig_Token $token, $nodeClass)
    {
        $lineno = $token->getLine();
        $stream = $this->parser->getStream();

        $staticName = $stream->expect(Twig_Token::NAME_TYPE)->getValue();

        $args = $this->parseKeyValuePairs($stream, [
            'tag' => false,
            'class' => false,
            'subname' => false,
            'refname' => false
        ]);
        $stream->expect(Twig_Token::BLOCK_END_TYPE);

        $hasSubName = array_key_exists('subname', $args);
        $hasRefName = array_key_exists('refname', $args);

        if ($staticName == '__dynamic') {
            // Only one of these should ever be set.
            assert($hasSubName ^ $hasRefName);
            if ($hasSubName) {
                $name = $args['subname'];
                $refElementId = null;
            } else {
                $name = $args['refname'];
                $refElementId = null;
            }
        } else {
            assert(!$hasSubName);
            assert(!$hasRefName);
            $name = new Twig_Node_Expression_Constant($staticName, $lineno);
        }

        $body = $this->parser->subparse(array($this, 'decideForFork'));
        if ($stream->next()->getValue() == 'else') {
            $stream->expect(Twig_Token::BLOCK_END_TYPE);
            $else = $this->parser->subparse(array($this, 'decideForEnd'), true);
        } else {
            $else = new Twig_Node();    // an empty node
        }
        $stream->expect(Twig_Token::BLOCK_END_TYPE);

        $defaultTag = new Twig_Node_Expression_Constant("div", $lineno);
        return new $nodeClass(
            $name,
            $body,
            $else,
            $args['tag'] ?? $defaultTag,
            $args['class'] ?? null,
            $lineno,
            $this->getTag()
        );
    }
}
