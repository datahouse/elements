<?php

namespace Datahouse\Elements\Abstraction\Changes;

use stdClass;
use RuntimeException;

use Datahouse\Elements\Abstraction\BaseStorageAdapter;
use Datahouse\Elements\Abstraction\ElementContents;
use Datahouse\Elements\Abstraction\ElementVersion;
use Datahouse\Elements\Abstraction\Element;
use Datahouse\Elements\Abstraction\IStorageAdapter;
use Datahouse\Elements\Abstraction\TransactionResult;

/**
 * A change that creates a new element.
 *
 * @package Datahouse\Elements\Abstraction\Changes
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
class ElementCreate extends BaseChange implements IChange
{
    protected $language;
    protected $newName;
    protected $newElement;
    protected $newType;
    protected $newEleDefName;

    /**
     * @param string $language   current language
     * @param string $name       of the new element
     * @param string $type       of the new element (only page supported)
     * @param string $eleDefName name of the definition for the child
     * @param string $fixedEleId optional hard-coded element id
     */
    public function __construct(
        string $language,
        string $name,
        string $type,
        string $eleDefName,
        string $fixedEleId = null
    ) {
        $this->language = $language;
        $this->newName = $name;
        $this->newType = $type;
        $this->newElement = new Element(
            $fixedEleId ?? BaseStorageAdapter::genRandomId()
        );
        $this->newEleDefName = $eleDefName;
    }

    /**
     * @param IChange[] $precedingChanges previous changes in transaction
     * @return TransactionResult
     * @SuppressWarnings(PHPMD.UnusedFormalParameter("precedingChanges"))
     */
    public function validate(array $precedingChanges) : TransactionResult
    {
        $result = new TransactionResult();
        if (!isset($this->newName) || empty($this->newName)) {
            $result->addErrorMessage('New element needs a name');
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
        return ['element_id' => $this->newElement->getId()];
    }

    /**
     * apply change
     *
     * @return TransactionResult result of application
     */
    public function apply()
    {
        $ev = new ElementVersion();
        $ec = new ElementContents();
        // Set a name, Element::getDisplayName uses this.
        $ec->name = $this->newName;

        // FIXME: state must be defined by process
        $ev->setState('editing');
        $ev->setDefinition($this->newEleDefName);
        $ev->addLanguage($this->language, $ec);

        $vno = 1;
        $this->newElement->addVersion($vno, $ev);
        $this->newElement->setType($this->newType);

        $result = new TransactionResult();
        $result->addTouchedStorable($this->newElement);
        $result->addInfoMessage("created element");
        $result->addChange('element_id', $this->newElement->getId());
        $result->addChange('label', $this->newName);
        $result->addChange('vno', $vno);

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

    /**
     * @param int $elementNumber to check
     * @return bool
     */
    public function checkAddsElementVersion(int $elementNumber) : bool
    {
        return $elementNumber == 1;
    }

    /**
     * @return Element generated
     */
    public function getGeneratedElement() : Element
    {
        return $this->newElement;
    }
}
