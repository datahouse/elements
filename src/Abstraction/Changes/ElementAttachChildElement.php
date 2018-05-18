<?php

namespace Datahouse\Elements\Abstraction\Changes;

use stdClass;
use RuntimeException;

use Datahouse\Elements\Abstraction\ElementVersion;
use Datahouse\Elements\Abstraction\Element;
use Datahouse\Elements\Abstraction\IStorageAdapter;
use Datahouse\Elements\Abstraction\TransactionResult;

/**
 * A change that establishes a parent-child relation between two elements.
 *
 * @package Datahouse\Elements\Abstraction\Changes
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
class ElementAttachChildElement extends BaseChange implements IChange
{
    protected $parentElement;
    protected $firstParentVno;
    protected $child;
    protected $insertBefore;

    /**
     * @param Element  $parent          the parent for new child
     * @param int      $firstParentVno  first version number of the parent
     *                                  element to which to add the child
     * @param Element  $newChild        to add
     * @param string|null $insertBefore insertion point
     */
    public function __construct(
        Element $parent,
        int $firstParentVno,
        Element $newChild,
        $insertBefore = null
    ) {
        $this->parentElement = $parent;
        $this->firstParentVno = $firstParentVno;
        $this->child = $newChild;
        $this->insertBefore = $insertBefore;
    }

    /**
     * @param IChange[] $precedingChanges previous changes in transaction
     * @return TransactionResult
     */
    public function validate(array $precedingChanges) : TransactionResult
    {
        $currentParentId = $this->txnGetElementParent(
            $precedingChanges,
            $this->child
        );
        $result = new TransactionResult();
        if (is_null($currentParentId)) {
            $result->addErrorMessage('Must set the child\'s parent, first');
        }
        if ($currentParentId != $this->parentElement->getId()) {
            $result->addErrorMessage('Cannot attach to different parent');
        }
        return $result;
    }

    /**
     * collectRollbackInfo
     *
     * @return array associative containing all data necessary to undo this
     * change
     */
    public function collectRollbackInfo()
    {
        return [
            'element_id' => $this->parentElement->getId(),
            'child_element_id' => $this->child->getId()
        ];
    }

    /**
     * Adds the new child element to all parent versions from firstParentVno
     * to the newest one.
     *
     * @return void
     */
    private function appendChildToParent()
    {
        $first = $this->firstParentVno;
        $last = $this->parentElement->getNewestVersionNumber();
        for ($vno = $first; $vno <= $last; $vno += 1) {
            /* @var ElementVersion $parentEV */
            $parentEV = $this->parentElement->getVersion($vno);
            // The version may have vanished due to element pruning, so we
            // double-check and only (try to) add the child to active element
            // versions (addChild would fail otherwise).
            if (isset($parentEV)) {
                $parentEV->insertChild(
                    $this->child->getId(),
                    $this->insertBefore
                );
            }
        }
    }

    /**
     * apply change
     *
     * @return TransactionResult result of application
     */
    public function apply()
    {
        $this->appendChildToParent();

        $result = new TransactionResult();
        $result->addTouchedStorable($this->parentElement);
        $result->addInfoMessage("added child element");
        return $result;
    }

    /**
     * @param IStorageAdapter $adapter storage adapater
     * @param stdClass        $rbi     roll back information
     * @return array of changed IStorable objects
     */
    public static function revert(
        IStorageAdapter $adapter,
        stdClass $rbi
    ) : array {
        // TODO: Implement revert() method.
        //
        // This should just invalidate the child element. No need to remove
        // the child pointers.

        throw new RuntimeException("not implemented, yet");
    }
}
