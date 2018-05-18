<?php

namespace Datahouse\Elements\Abstraction\Changes;

use stdClass;

use Datahouse\Elements\Abstraction\Element;
use Datahouse\Elements\Abstraction\IStorageAdapter;
use Datahouse\Elements\Abstraction\TransactionResult;

/**
 * A change that modifies the parent of an element.
 *
 * @package Datahouse\Elements\Abstraction\Changes
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
class ElementSetParent extends BaseElementVersionChange implements IChange
{
    protected $parent;

    /**
     * @param Element $element       affected by this change
     * @param int     $versionNumber affected by this change
     * @param Element $parent        new parent to set
     */
    public function __construct(
        Element $element,
        int $versionNumber,
        Element $parent
    ) {
        parent::__construct($element, $versionNumber);
        $this->parent = $parent;
    }

    /**
     * @param IChange[] $precedingChanges previous changes in transaction
     * @return TransactionResult
     * @SuppressWarnings(PHPMD.UnusedFormalParameter("precedingChanges"))
     */
    public function validate(array $precedingChanges) : TransactionResult
    {
        $result = new TransactionResult();
        // FIXME: could check against cycles...
        return $result;
    }

    /**
     * @return array associative, containing all data necessary to undo
     * this change.
     */
    public function collectRollbackInfo()
    {
        return [
            'element_id' => $this->element->getId(),
            'version_number' => $this->versionNumber,
            'old_parent_id' => $this->element->getParentId()
        ];
    }

    /**
     * @inheritdoc
     * @return TransactionResult result of application
     */
    public function apply()
    {
        $this->element->setParentId($this->parent->getId());

        $result = new TransactionResult();
        $result->addTouchedStorable($this->element);
        $result->addTouchedUrl($this->element->getId());
        $result->appendClientInfo(
            $this->element->getId(),
            'parent',
            $this->parent->getId()
        );
        return $result;
    }

    /**
     * @param IStorageAdapter $adapter used to persist this change
     * @param stdClass        $rbi     rollback information originally
     * gathered via the @see collectRollbackInfo method.
     * @return array of changed IStorable objects
     */
    public static function revert(
        IStorageAdapter $adapter,
        stdClass $rbi
    ) : array {
        $element = $adapter->loadElement($rbi->{'element_id'});
        $element->setParentId($rbi->{'old_parent_id'});
        return [$element];
    }

    /**
     * @param string $elementId to check
     * @return array of tuple bool, string of the new parent id
     */
    public function checkSetsParentId(string $elementId) : array
    {
        if ($this->element->getId() == $elementId) {
            return [true, $this->parent->getId()];
        } else {
            return [false, null];
        }
    }
}
