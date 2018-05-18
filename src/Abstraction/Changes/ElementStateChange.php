<?php

namespace Datahouse\Elements\Abstraction\Changes;

use stdClass;

use Datahouse\Elements\Abstraction\Element;
use Datahouse\Elements\Abstraction\IStorageAdapter;
use Datahouse\Elements\Abstraction\TransactionResult;

/**
 * A change that switches an ElementVersion's state.
 *
 * @package Datahouse\Elements\Abstraction\Changes
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
class ElementStateChange extends BaseElementVersionChange implements IChange
{
    /** @var string $newState to be assigned to the element */
    private $newState;

    /**
     * ElementVersionChangeState constructor.
     *
     * @param Element $element       to be modified
     * @param int     $versionNumber to be modified
     * @param string  $newState      to be assigned to the ElementVersion
     */
    public function __construct(
        Element $element,
        int $versionNumber,
        string $newState
    ) {
        parent::__construct($element, $versionNumber);
        $this->newState = $newState;
    }

    /**
     * @return string the new state
     */
    public function getNewState()
    {
        return $this->newState;
    }

    /**
     * @param IChange[] $precedingChanges previous changes in transaction
     * @return TransactionResult
     * @SuppressWarnings(PHPMD.UnusedFormalParameter("precedingChanges"))
     */
    public function validate(array $precedingChanges) : TransactionResult
    {
        $result = new TransactionResult();
        $ev = $this->getAffectedVersion();
        if (count($ev->getLanguages()) == 0) {
            $result->addErrorMessage('cannot publish a version without content');
        }
        $old_value = $ev->getState();
        if ($old_value == $this->newState) {
            $result->addErrorMessage(
                'element version is already at the given state'
            );
        }

        return $result;
    }

    /**
     * After validating and just before applying a change, this method is
     * called by the storage adapter to collect all information necessary to
     * undo the change.
     *
     * @return array associative, containing all data necessary to undo
     * this change.
     */
    public function collectRollbackInfo()
    {
        $ev = $this->getAffectedVersion();
        $oldValue = $ev->getState();
        return [
            'element_id' => $this->element->getId(),
            'version_number' => $this->versionNumber,
            'old_state' => $oldValue
        ];
    }

    /**
     * @inheritdoc
     * @return TransactionResult result of application
     */
    public function apply()
    {
        $ev = $this->getAffectedVersion();
        $oldState = $ev->getState();
        $ev->setState($this->newState);
        $result = new TransactionResult();
        $result->addTouchedStorable($this->element);
        $result->addInfoMessage(
            'changed state from ' . $oldState . ' to ' . $this->newState
        );
        $result->appendClientInfo(
            $this->element->getId(),
            'state',
            $this->newState
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
        $ev = $element->getVersion($rbi->{'version_number'});
        $ev->setState($rbi->{'old_state'});
        return [$element];
    }
}
