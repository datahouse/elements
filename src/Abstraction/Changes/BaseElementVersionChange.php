<?php

namespace Datahouse\Elements\Abstraction\Changes;

use Datahouse\Elements\Abstraction\Element;
use Datahouse\Elements\Abstraction\ElementVersion;

/**
 * A base class for all changes that affect one element (and one specific
 * language).
 *
 * @package Datahouse\Elements\Abstraction\Changes
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class BaseElementVersionChange extends BaseChange
{
    /** @var Element $element affected */
    protected $element;
    /** @var int $versionNumber added or changed */
    protected $versionNumber;

    /**
     * BaseElementChange constructor.
     *
     * @param Element $element       affected by this change
     * @param int     $versionNumber affected by this change
     */
    public function __construct(Element $element, int $versionNumber)
    {
        $this->element = $element;
        $this->versionNumber = $versionNumber;
    }

    /**
     * @return Element
     */
    public function getElement()
    {
        return $this->element;
    }

    /**
     * @return ElementVersion|null
     */
    public function getAffectedVersion()
    {
        return $this->element->getVersion($this->versionNumber);
    }

    /**
     * @return int
     */
    public function getAffectedVersionNumber()
    {
        return $this->versionNumber;
    }

    /**
     * @param IChange[] $precedingChanges to check
     * @param int       $vno              to check
     * @return bool whether or not any of the preceding changes add the
     * current version number
     */
    public function txnAddsVersion(
        array $precedingChanges,
        int $vno = -1
    ) : bool {
        $vno = $vno < 0 ? $this->versionNumber : $vno;
        return parent::txnAddsVersion($precedingChanges, $vno);
    }
}
