<?php

namespace Datahouse\Elements\Abstraction\Changes;

use stdClass;
use RuntimeException;

use Datahouse\Elements\Abstraction\ElementContents;
use Datahouse\Elements\Abstraction\Element;
use Datahouse\Elements\Abstraction\IStorageAdapter;
use Datahouse\Elements\Abstraction\TransactionResult;

/**
 * A change that extends a certain sub-element set with a new sub-element.
 *
 * @package Datahouse\Elements\Abstraction\Changes
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
class ElementAddSubElement extends BaseElementVersionChange implements IChange
{
    protected $language;
    protected $subName;

    /**
     * @param Element $element       affected
     * @param int     $versionNumber to change
     * @param string  $language      to change
     * @param string  $subName       name of the sub element collection to
     *                               apppend to
     */
    public function __construct(
        Element $element,
        int $versionNumber,
        string $language,
        string $subName
    ) {
        parent::__construct($element, $versionNumber);
        $this->language = $language;
        $this->subName = $subName;
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
            $result->addErrorMessage('version to extend does not exist');
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

        $existingSubs = $ec->getSubs($this->subName);
        $insertPosition = count($existingSubs);

        return [
            'element_id' => $this->element->getId(),
            'version_number' => $this->versionNumber,
            'language' => $this->language,
            'subName' => $this->subName,
            'insertPosition' => $insertPosition
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

        // append an empty ElementContents instance at the end
        $ec->setSub($this->subName, -1, new ElementContents());

        $result = new TransactionResult();
        $result->addTouchedStorable($this->element);
        $result->addInfoMessage("added sub element");

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
