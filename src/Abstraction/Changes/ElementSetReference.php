<?php

namespace Datahouse\Elements\Abstraction\Changes;

use stdClass;

use Datahouse\Elements\Abstraction\BaseStorageAdapter;
use Datahouse\Elements\Abstraction\Element;
use Datahouse\Elements\Abstraction\IStorageAdapter;
use Datahouse\Elements\Abstraction\TransactionResult;

/**
 * A change that set, replaces or remove a reference of the element to another
 * element.
 *
 * @package Datahouse\Elements\Abstraction\Changes
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
class ElementSetReference extends BaseElementVersionChange implements IChange
{
    protected $refName;
    protected $refElementId;

    /**
     * @param Element $element       to set slugs for
     * @param int     $versionNumber affected
     * @param string  $refName       to set or replace
     * @param string  $refElementId  target of the reference, or an empty
     *                               string to remove the reference
     */
    public function __construct(
        Element $element,
        int $versionNumber,
        string $refName,
        string $refElementId
    ) {
        parent::__construct($element, $versionNumber);
        $this->refName = $refName;
        $this->refElementId = $refElementId;
    }

    /**
     * @param IChange[] $precedingChanges previous changes in transaction
     * @return TransactionResult
     */
    public function validate(array $precedingChanges) : TransactionResult
    {
        $result = new TransactionResult();

        if (!empty($this->refElementId) &&
            !BaseStorageAdapter::isValidElementId($this->refElementId)
        ) {
            $result->addErrorMessage(
                "invalid target element id for reference " . $this->refName
            );
        }

        $versionExists = !is_null($this->getAffectedVersion());
        $versionAdded = $this->txnAddsVersion($precedingChanges);

        if (!$versionExists and !$versionAdded) {
            $result->addErrorMessage(
                "version $this->versionNumber does not exist for element " .
                $this->element->getId()
            );
        }

        // Maybe use the element definition to check if it's a valid link?

        return $result;
    }

    /**
     * Remember the old slugs and the exact element version.
     *
     * @return array associative, containing all data necessary to undo
     * this change.
     */
    public function collectRollbackInfo()
    {
        $ev = $this->getAffectedVersion();
        $oldTarget = $ev->getLink($this->refName);
        return [
            'element_id' => $this->element->getId(),
            'version_number' => $this->versionNumber,
            'ref_name' => $this->refName,
            'old_target' => $oldTarget
        ];
    }

    /**
     * Stores the new slugs on the element.
     *
     * @return TransactionResult result of application
     */
    public function apply() : TransactionResult
    {
        $result = new TransactionResult();
        $ev = $this->getAffectedVersion();
        $ev->setLink($this->refName, $this->refElementId);

        $result->addTouchedStorable($this->element);
        if (empty($this->refElementId)) {
            $result->addInfoMessage('removed reference');
        } else {
            $result->addInfoMessage('updated reference');
        }
        return $result;
    }

    /**
     * revert change
     *
     * @param IStorageAdapter $adapter storage adapter to use
     * @param stdClass        $rbi     the rollback information
     * @return array of changed IStorable objects
     */
    public static function revert(
        IStorageAdapter $adapter,
        stdClass $rbi
    ) : array {
        $element = $adapter->loadElement($rbi->{'element_id'});
        $ev = $element->getVersion($rbi->{'version_number'});
        $ev->setLink($rbi->{'ref_name'}, $rbi->{'old_target'});
        return [$element];
    }
}
