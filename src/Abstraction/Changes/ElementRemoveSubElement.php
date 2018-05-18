<?php

namespace Datahouse\Elements\Abstraction\Changes;

use stdClass;
use RuntimeException;

use Datahouse\Elements\Abstraction\Element;
use Datahouse\Elements\Abstraction\IStorageAdapter;
use Datahouse\Elements\Abstraction\TransactionResult;

/**
 * A change that removes a sub-element from a given element.
 *
 * @package Datahouse\Elements\Abstraction\Changes
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
class ElementRemoveSubElement extends BaseElementVersionChange implements IChange
{
    protected $language;
    protected $subName;
    protected $subIndex;

    /**
     * @param Element $element       affected
     * @param int     $versionNumber to change
     * @param string  $language      to change
     * @param string  $subName       name of the sub element collection to
     *                               remove from
     * @param int     $subIndex      of the sub element to remove
     */
    public function __construct(
        Element $element,
        int $versionNumber,
        string $language,
        string $subName,
        int $subIndex
    ) {
        parent::__construct($element, $versionNumber);
        $this->language = $language;
        $this->subName = $subName;
        $this->subIndex = $subIndex;
    }

    /**
     * @param IChange[] $precedingChanges previous changes in transaction
     * @return TransactionResult
     */
    public function validate(array $precedingChanges) : TransactionResult
    {
        $result = new TransactionResult();

        $versionExists = !is_null($this->getAffectedVersion());
        $versionAdded = $this->txnAddsVersion($precedingChanges);

        if (!$versionExists and !$versionAdded) {
            $result->addErrorMessage('version to change does not exist');
        }

        $newestVersions = $this->element->getNewestVersionNumberByLanguage();
        $newestLangVno = $newestVersions[$this->language] ?? 0;
        if ($newestLangVno > $this->versionNumber) {
            $result->addErrorMessage(
                "a newer version exists for language '$this->language'"
            );
        }

        return $result;
    }

    /**
     * @inheritdoc
     * @return array associative, containing all data necessary to undo
     * this change.
     */
    public function collectRollbackInfo()
    {
        $ev = $this->getAffectedVersion();
        assert(isset($ev));
        $ec = $ev->getContentsFor($this->language);
        assert(isset($ec));

        $subs = $ec->getSubs($this->subName);
        return [
            'element_id' => $this->element->getId(),
            'version_number' => $this->versionNumber,
            'language' => $this->language,
            'subName' => $this->subName,
            'subIndex' => $this->subIndex,
            'subContents' => $subs[$this->subIndex]->serialize()
        ];
    }

    /**
     * @inheritdoc
     * @return TransactionResult result of application
     */
    public function apply()
    {
        $ev = $this->getAffectedVersion();
        assert(isset($ev));
        $ec = $ev->getContentsFor($this->language);
        assert(isset($ec));

        $ec->removeSub($this->subName, $this->subIndex);

        $result = new TransactionResult();
        $result->addTouchedStorable($this->element);

        // FIXME: should not really by part of the Abstraction layer, but...
        $result->appendClientInfo($this->element->getId(), 'reload_now', true);

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
        // FIXME: implement rollback
        throw new RuntimeException("not implemented, yet");
    }
}
