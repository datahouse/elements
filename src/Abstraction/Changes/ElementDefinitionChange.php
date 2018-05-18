<?php

namespace Datahouse\Elements\Abstraction\Changes;

use stdClass;

use Datahouse\Elements\Abstraction\Element;
use Datahouse\Elements\Abstraction\IStorageAdapter;
use Datahouse\Elements\Abstraction\TransactionResult;

/**
 * A change that switches an ElementVersion's state.
 *
 * @package Datahouse\Elements\Tests
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG (https://datahouse.ch/license.v1.txt)
 */
class ElementDefinitionChange extends BaseElementVersionChange implements IChange
{
    /** @var string $definitionId to be assigned to the element */
    private $definitionId;

    /**
     * @param Element $element       to be modified
     * @param int     $versionNumber to be modified
     * @param string  $definitionId  to be assigned to the ElementVersion
     */
    public function __construct(
        Element $element,
        int $versionNumber,
        string $definitionId
    ) {
        parent::__construct($element, $versionNumber);
        $this->definitionId = $definitionId;
    }

    /**
     * @param IChange[] $precedingChanges previous changes in transaction
     * @return TransactionResult
     * @SuppressWarnings(PHPMD.UnusedFormalParameter("precedingChanges"))
     */
    public function validate(array $precedingChanges) : TransactionResult
    {
        $result = new TransactionResult();
        //FIXME: implement some validation

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
        $oldDefinitionId = $ev->getDefinition();
        return [
            'element_id' => $this->element->getId(),
            'version_number' => $this->versionNumber,
            'old_definition_id' => $oldDefinitionId
        ];
    }

    /**
     * @inheritdoc
     * @return TransactionResult result of application
     */
    public function apply()
    {
        $ev = $this->getAffectedVersion();
        $oldDefinitionId = $ev->getDefinition();
        $ev->setDefinition($this->definitionId);
        $result = new TransactionResult();
        $result->addTouchedStorable($this->element);
        $result->addInfoMessage(
            'changed template from ' . $oldDefinitionId
            . ' to ' . $this->definitionId
        );
        // dont throw stuff away
        $result->addChange('template', $this->definitionId);

        // FIXME: should not really by part of the Abstraction layer, but...
        $result->appendClientInfo($this->element->getId(), 'reload_now', true);

        return $result;
    }

    /**
     * @param IStorageAdapter $adapter used to persist this change
     * @param \stdClass       $rbi     rollback information originally
     * gathered via the @see collectRollbackInfo method.
     * @return array of changed IStorable objects
     */
    public static function revert(
        IStorageAdapter $adapter,
        stdClass $rbi
    ) : array {
        $element = $adapter->loadElement($rbi->{'element_id'});
        $ev = $element->getVersion($rbi->{'version_number'});
        $ev->setDefinition($rbi->{'old_definition_id'});
        return [$element];
    }
}
