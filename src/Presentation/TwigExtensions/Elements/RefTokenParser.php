<?php

namespace Datahouse\Elements\Presentation\TwigExtensions\Elements;

use Twig_Error_Syntax;
use Twig_Token;
use Twig_Node;

/**
 * @package Datahouse\Elements\Presentation\TwigExtensions\Elements
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class RefTokenParser extends BaseEleTokenParser
{
    /**
     * Parses a 'eleref' token and returns a ReferenceNode
     *
     * @param Twig_Token $token token to parse
     * @return Twig_Node parser result
     * @throws Twig_Error_Syntax
     */
    public function parse(Twig_Token $token)
    {
        return $this->parseSubOrRef($token, RefNode::class);
    }

    /**
     * @param Twig_Token $token to check
     * @return bool
     */
    public function decideForFork(Twig_Token $token)
    {
        return $token->test(array('else', 'endeleref'));
    }

    /**
     * @param Twig_Token $token to check
     * @return bool
     */
    public function decideForEnd(Twig_Token $token)
    {
        return $token->test('endeleref');
    }

    /**
     * @return string the tag name: 'eleref'
     */
    public function getTag()
    {
        return 'eleref';
    }
}
