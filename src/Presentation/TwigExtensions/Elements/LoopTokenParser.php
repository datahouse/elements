<?php

namespace Datahouse\Elements\Presentation\TwigExtensions\Elements;

use Twig_Error_Syntax;
use Twig_Node_Expression_Constant;
use Twig_Token;

/**
 * @package Datahouse\Elements\Presentation\TwigExtensions\Elements
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class LoopTokenParser extends BaseEleTokenParser
{
    /**
     * @param Twig_Token $token current token
     * @return RefNode
     * @throws Twig_Error_Syntax
     */
    public function parse(Twig_Token $token)
    {
        $lineno = $token->getLine();
        $stream = $this->parser->getStream();

        $args = $this->parseKeyValuePairs($stream, [
            'every' => false,
            'limit' => false,
            'offset' => false
        ]);

        $stream->expect(Twig_Token::BLOCK_END_TYPE);

        $body = $this->parser->subparse([$this, 'decideForEnd'], true);

        $stream->expect(Twig_Token::BLOCK_END_TYPE);

        return new LoopNode(
            $body,
            $args['every'] ?? null,
            $args['limit'] ?? new Twig_Node_Expression_Constant(-1, $lineno),
            $args['offset'] ?? new Twig_Node_Expression_Constant(0, $lineno),
            $lineno,
            $this->getTag()
        );
    }

    /**
     * @param Twig_Token $token to check
     * @return bool
     */
    public function decideForEnd(Twig_Token $token)
    {
        return $token->test(['endeleloop']);
    }

    /**
     * @return string token that ends the block
     */
    public function getTag()
    {
        return 'eleloop';
    }
}
