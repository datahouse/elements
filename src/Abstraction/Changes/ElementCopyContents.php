<?php

namespace Datahouse\Elements\Abstraction\Changes;

use stdClass;

use Datahouse\Elements\Abstraction\Element;
use Datahouse\Elements\Abstraction\IStorageAdapter;
use Datahouse\Elements\Abstraction\TransactionResult;

/**
 * A change that copies contents between versions and languages of a single
 * element. Mainly of use for initializing new versions or variants
 * (translations) of an element
 *
 * Note that this requires the target version to exist, but the target language
 * must not exist for that version.
 *
 * @package Datahouse\Elements\Abstraction\Changes
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
class ElementCopyContents extends BaseElementVersionChange implements IChange
{
    protected $sourceLang;
    protected $destVno;
    protected $destLang;

    /**
     * @param Element $element    to modify
     * @param int     $sourceVno  version number to copy from
     * @param string  $sourceLang language to copy from
     * @param int     $destVno    target version to copy to
     * @param string  $destLang   target language to copy to
     */
    public function __construct(
        Element $element,
        int $sourceVno,
        string $sourceLang,
        int $destVno,
        string $destLang
    ) {
        parent::__construct($element, $sourceVno);
        $this->sourceLang = $sourceLang;
        $this->destVno = $destVno;
        $this->destLang = $destLang;
    }

    /**
     * @param string $language to check
     * @return bool
     */
    public function checkAddsLanguage(string $language) : bool
    {
        return $this->destLang == $language;
    }

    /**
     * @param IChange[] $precedingChanges previous changes in transaction
     * @return TransactionResult
     */
    public function validate(array $precedingChanges) : TransactionResult
    {
        $result = new TransactionResult();

        // Check if the source version and language exist.
        $ev = $this->getAffectedVersion();

        $versionAdded = $this->txnAddsVersion($precedingChanges);
        if (is_null($ev) && !$versionAdded) {
            $result->addErrorMessage('source version does not exist');
        }

        if (!array_key_exists($this->sourceLang, $ev->getLanguages())) {
            $result->addErrorMessage(
                "source language $this->sourceLang does not exist in version "
                . $this->versionNumber . " of element "
                . $this->element->getId()
            );
        }

        // Check if the target version exists or is being created in the
        // same transaction.
        $ev = $this->element->getVersion($this->destVno);
        if (is_null($ev)) {
            if (!$this->txnAddsVersion($precedingChanges, $this->destVno)) {
                $result->addErrorMessage('destination version does not exist');
            }
        } else {
            // Check if the target language already exists.
            if (array_key_exists($this->destLang, $ev->getLanguages())) {
                $result->addErrorMessage('target language already exists');
            }

            // Or will be created in the same transaction.
            foreach ($precedingChanges as $change) {
                if ($change->checkAddsLanguage($this->destLang)) {
                    $result->addErrorMessage(
                        'target language created in the same transaction'
                    );
                }
            }
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
        return [
            'element_id' => $this->element->getId(),
            'version_number' => $this->versionNumber,
            'language' => $this->destLang
        ];
    }

    /**
     * @inheritdoc
     * @return TransactionResult result of application
     */
    public function apply()
    {
        $ev = $this->getAffectedVersion();
        $contents = $ev->getContentsFor($this->sourceLang);

        // hack that performs a deep copy
        $contents = unserialize(serialize($contents));

        $ev = $this->element->getVersion($this->destVno);
        $ev->setContentsFor($contents, $this->destLang);

        $result = new TransactionResult();
        $result->addTouchedStorable($this->element);
        // considered an internal change, so this doesn't even add an
        // informational message.
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
        $ev = $element->getVersion($rbi->{'version_number'});
        $ev->removeLanguage($rbi->{'language'});
        return [$element];
    }
}
