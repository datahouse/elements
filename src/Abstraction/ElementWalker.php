<?php

namespace Datahouse\Elements\Abstraction;

use Datahouse\Elements\Control\EleDefRegistry;
use Datahouse\Elements\Presentation\IElementDefinition;

/**
 * A helper class for traversing elements and their versions.
 *
 * @package Datahouse\Elements\Control\Admin
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2017 by Datahouse AG
 */
class ElementWalker
{
    private $adapter;

    /**
     * @param IStorageAdapter $adapter to use
     */
    public function __construct(IStorageAdapter $adapter)
    {
        $this->adapter = $adapter;
    }

    /**
     * Helper function for traversing the element tree.
     *
     * @param Element  $startElement element to start from
     * @param callable $visitorFn    callback function
     * @return array children of the $startElement, including itself
     */
    public function visitChildrenOf(
        Element $startElement,
        callable $visitorFn
    ) {
        $stack = [$startElement];
        $result = [];
        while (!empty($stack)) {
            /* @var Element $element */
            $element = array_pop($stack);
            /* @var ElementVersion $ev */
            $ev = $element->getVersion($element->getNewestVersionNumber());

            // add children to the stack
            foreach ($ev->getChildren() as $childId) {
                $child = $this->adapter->loadElement($childId);
                assert(isset($child));
                // Filter away elements that changed their parent.
                if ($child->getParentId() == $element->getId()) {
                    array_push($stack, $child);
                }
            }

            // handle the current element
            $result[$element->getId()] = $visitorFn($element);
        }
        return $result;
    }

    /**
     * @param Element        $element        to scan
     * @param EleDefRegistry $eleDefRegistry for the EleDef lookup
     * @param callable       $visitor        to invoke per version
     * @return array visitor result per version number
     */
    public function visitVersionsOf(
        Element $element,
        EleDefRegistry $eleDefRegistry,
        callable $visitor
    ) {
        $result = [];
        /* @var ElementVersion $ev */
        foreach ($element->getVersions() as $vno => $ev) {
            $eleDefName = $ev->getDefinition();
            if (!is_null($eleDefName)) {
                $eleDef = $eleDefRegistry->getEleDefById(
                    $eleDefName
                );
                $result[$vno] = $visitor($eleDef, $ev);
            }
        }
        return $result;
    }

    /**
     * @param IElementDefinition $startEleDef Definition of the startEc
     * @param ElementContents    $startEc     exact sub-element to process
     * @param callable           $visitorFn   callback function
     * @return array
     */
    public function visitSubElementContents(
        IElementDefinition $startEleDef,
        ElementContents $startEc,
        callable $visitorFn
    ) : array {
        $stack = [['', $startEc, $startEleDef]];
        $result = [];
        while (!empty($stack)) {
            /* @var ElementContents $ec */
            list ($subPath, $ec, $eleDef) = array_pop($stack);

            $result[$subPath] = $visitorFn($subPath, $ec, $eleDef);

            foreach ($eleDef->getKnownSubElements() as $subName => $subDef) {
                $subEleDef = $subDef['definition'];
                foreach ($ec->getSubs($subName) as $subIdx => $sub) {
                    $fullPath = $subPath . '/' . $subName . '-' . $subIdx;
                    array_push($stack, [$fullPath, $sub, $subEleDef]);
                }
            }
        }
        return $result;
    }
}
