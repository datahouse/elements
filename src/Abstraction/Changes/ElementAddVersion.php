<?php

namespace Datahouse\Elements\Abstraction\Changes;

use stdClass;

use Datahouse\Elements\Abstraction\Element;
use Datahouse\Elements\Abstraction\IStorageAdapter;
use Datahouse\Elements\Abstraction\TransactionResult;

/**
 * A change that adds a new version of an existing element.
 *
 * @package Datahouse\Elements\Abstraction\Changes
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
class ElementAddVersion extends BaseElementVersionChange implements IChange
{
    protected $state;

    /**
     * @param Element $element       to which to add a new version
     * @param int     $versionNumber version to add
     * @param string  $state         state in which the element is
     */
    public function __construct(
        Element $element,
        int $versionNumber,
        string $state
    ) {
        parent::__construct($element, $versionNumber);
        $this->state = $state;
    }

    /**
     * @param IChange[] $precedingChanges previous changes in transaction
     * @return TransactionResult
     */
    public function validate(array $precedingChanges) : TransactionResult
    {
        $result = new TransactionResult();

        // Check if we're trying to add a valid version number.
        $newestVno = $this->element->getNewestVersionNumber();
        if ($newestVno >= $this->versionNumber) {
            $result->addErrorMessage('a newer version already exists');
        }

        // Check if the source version exists or gets added
        $sourceVno = $this->versionNumber - 1;
        $ev = $this->element->getVersion($sourceVno);
        $sourceAdded = $this->txnAddsVersion($precedingChanges, $sourceVno);
        if (is_null($ev) && !$sourceAdded) {
            $result->addErrorMessage("source version does not exist");
        }

        $ev = $this->getAffectedVersion();
        if (!is_null($ev) || $this->txnAddsVersion($precedingChanges)) {
            $result->addErrorMessage("version to add already exists");
        }

        return $result;
    }

    /**
     * @param int $elementNumber version number of an element
     * @return bool
     */
    public function checkAddsElementVersion(int $elementNumber) : bool
    {
        return $this->versionNumber == $elementNumber;
    }

    /**
     * @inheritdoc
     * @return array associative, containing all data necessary to undo
     * this change.
     */
    public function collectRollbackInfo()
    {
        return [
            'element_id' => $this->element->getId(),
            'version_number' => $this->versionNumber,
        ];
    }

    /**
     * @inheritdoc
     * @return TransactionResult result of application
     */
    public function apply()
    {
        $sourceVno = $this->versionNumber - 1;
        $baseEv = $this->element->getVersion($sourceVno);

        // Copy version without contents info to the target version number.
        $ev = $baseEv->deepCopyWithoutContents();
        $ev->setState($this->state);

        $this->element->addVersion($this->versionNumber, $ev);

        $result = new TransactionResult();
        $result->addTouchedStorable($this->element);
        $result->addInfoMessage("created new version $this->versionNumber");

        // These should vanish...
        $result->addChange('version', $this->versionNumber);
        $result->addChange('state', $this->state);

        // ..in favor of this newer, more universal client info structure
        $elementId = $this->element->getId();
        $result->appendClientInfo($elementId, 'state', $this->state);
        $result->appendClientInfo(
            $elementId,
            'version',
            strval($this->versionNumber)
        );

        return $result;
    }

    /**
     * @inheritdoc
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
        $versions = $element->getVersions();
        unset($versions[$rbi->{'version_number'}]);
        return [$element];
    }
}
