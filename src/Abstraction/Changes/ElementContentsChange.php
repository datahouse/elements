<?php

namespace Datahouse\Elements\Abstraction\Changes;

use stdClass;
use RuntimeException;

use Datahouse\Elements\Abstraction\Element;
use Datahouse\Elements\Abstraction\ElementContents;
use Datahouse\Elements\Abstraction\IStorageAdapter;
use Datahouse\Elements\Abstraction\TransactionResult;

/**
 * A change that modifies a single field within the contents an element
 * version.
 *
 * @package Datahouse\Elements\Abstraction\Changes
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
class ElementContentsChange extends BaseElementVersionChange implements IChange
{
    /** @var string $language where the change took place */
    protected $language;
    /** @var string $fieldNameParts to change */
    private $fieldNameParts;
    /** @var string $fieldValue featuring the new contents */
    private $fieldValue;

    /**
     * ElementChangeContents constructor.
     *
     * @param Element $element        specifying the contents to modify
     * @param int     $versionNumber  specifying the contents to modify
     * @param string  $language       specifying the contents to modify
     * @param array   $fieldNameParts specifying the contents to modify
     * @param string  $fieldValue     new value to store
     */
    public function __construct(
        Element $element,
        int $versionNumber,
        string $language,
        array $fieldNameParts,
        string $fieldValue
    ) {
        $this->language = $language;
        $this->fieldNameParts = $fieldNameParts;
        $this->fieldValue = $fieldValue;
        parent::__construct($element, $versionNumber);
    }

    /**
     * @param IChange[] $precedingChanges previous changes in transaction
     * @return TransactionResult
     */
    public function validate(array $precedingChanges) : TransactionResult
    {
        $result = new TransactionResult();

        // scan preceding changes for version and translation additions
        $addsNewVersion = $this->txnAddsVersion($precedingChanges);
        $addsLang = $this->txnAddsLanguage($precedingChanges, $this->language);

        // check if the version to edit really exists
        $ev = $this->getAffectedVersion();
        if (is_null($ev) && !$addsNewVersion) {
            $result->addErrorMessage('cannot edit inexistent element version');
        }

        // check if the language to edit really exists
        if (!is_null($ev)) {
            $contents = $ev->getContentsFor($this->language);
            if (is_null($contents)) {
                if (!$addsLang) {
                    $result->addErrorMessage('cannot edit inexistent language');
                }
            } else {
                // check if the existing value matches what we're trying to save
                $curValue = $contents->getField($this->fieldNameParts);
                // FIXME: what if empty($this->fieldValue)?
                if (!is_null($curValue) && $curValue == $this->fieldValue) {
                    $result->addErrorMessage('nothing to save');
                }
            }
        }

        return $result;
    }

    /**
     * collect rollback information
     *
     * @return array
     */
    public function collectRollbackInfo()
    {
        $ev = $this->getAffectedVersion();
        $contents = $ev ? $ev->getContentsFor($this->language) : null;
        // If the version didn't exist prior to this change, we can simply
        // set its contents back to the empty string. Most likely, the add
        // translation or add version change will drop the entire translation
        // or version anyways.
        return [
            'element_id' => $this->element->getId(),
            'version_number' => $this->versionNumber,
            'lang' => $this->language,
            'field_name' => implode('-', $this->fieldNameParts),
            'old_field_value' => $contents
                ? $contents->getField($this->fieldNameParts) : ''
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
        $contents = $ev->getContentsFor($this->language);
        if (is_null($contents)) {
            $contents = new ElementContents();
            $ev->addLanguage($this->language, $contents);
        }
        assert(isset($contents));

        $contents->setField($this->fieldNameParts, $this->fieldValue);

        $result = new TransactionResult();
        $result->addTouchedStorable($this->element);
        $result->addInfoMessage("content changed");
        return $result;
    }

    /**
     * revert changes
     *
     * @param IStorageAdapter $adapter storage adapter in use
     * @param stdClass        $rbi     the rollback information
     * @return array of changed IStorable objects
     */
    public static function revert(
        IStorageAdapter $adapter,
        stdClass $rbi
    ) : array {
        $element = $adapter->loadElement($rbi->{'element_id'});
        $ev = $element->getVersion($rbi->{'version_number'});
        $contents = $ev->getContentsFor($rbi->{'lang'});
        assert(!is_null($contents));
        $parts = explode('-', $rbi->{'field_name'});
        if (count($parts) == 1) {
            $contents->{$parts[0]} = $rbi->{'old_field_value'};
        } elseif (count($parts)) {
            $subEleIndex = intval($parts[1]);
            // FIXME: create the sub element, if not existent
            $subEle = $contents->getSubs($parts[0])[$subEleIndex];
            if (!empty($rbi->{'old_field_value'})) {
                $subEle->{$parts[2]} = $rbi->{'old_field_value'};
            }
            $contents->setSub($parts[0], $parts[1], $subEle);
        } else {
            throw new RuntimeException(
                "ElementContentsChange cannot handle fieldName: " .
                implode('-', $parts)
            );
        }
        return [$element];
    }
}
