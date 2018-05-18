<?php

namespace Datahouse\Elements\Abstraction;

/**
 * Holds the results of trying to apply a change - successfull or not.
 *
 * @package Datahouse\Elements\Abstraction
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
class TransactionResult
{
    private $success;
    private $xid;
    private $touchedStorables;
    private $touchedUrls;

    private $infoMessages;
    private $errorMessages;

    // FIXME: $elementVersionsAdded and $changes should be merged and extended
    // by the affected element id, as a page can easily display multiple
    // elements at once.
    private $elementVersionsAdded;
    private $changes;

    // Per-element information to send back to the client.
    private $clientInfo;

    /**
     * ChangeResult constructor.
     */
    public function __construct()
    {
        $this->success = true;
        $this->xid = null;
        $this->touchedStorables = [];
        $this->touchedUrls = [];
        $this->elementVersionsAdded = [];

        $this->infoMessages = [];
        $this->errorMessages = [];

        $this->elementVersionsAdded = [];
        $this->changes = [];
        $this->clientInfo = [];
    }

    /**
     * Merge another ChangeResult into the current one.
     *
     * @param TransactionResult $other to merge with
     * @return void
     */
    public function merge(TransactionResult $other)
    {
        $this->success = $this->success && $other->success;
        if (isset($other->xid)) {
            if (isset($this->xid)) {
                throw new \RuntimeException("xid must be assigned only once");
            } else {
                $this->xid = $other->xid;
            }
        }
        $this->touchedStorables = array_merge(
            $this->touchedStorables,
            $other->touchedStorables
        );
        $this->touchedUrls = array_merge(
            $this->touchedUrls,
            $other->touchedUrls
        );
        $this->elementVersionsAdded = array_merge(
            $this->elementVersionsAdded,
            $other->elementVersionsAdded
        );
        $this->infoMessages = array_merge(
            $this->infoMessages,
            $other->infoMessages
        );
        $this->errorMessages = array_merge(
            $this->errorMessages,
            $other->errorMessages
        );
        $this->changes = array_merge(
            $this->changes,
            $other->changes
        );
        foreach ($other->clientInfo as $elementId => $otherInfo) {
            $this->clientInfo[$elementId] = array_merge(
                $this->clientInfo[$elementId] ?? [],
                $otherInfo
            );
        }
    }

    /**
     * @return bool whether or not the covered operation was successful
     */
    public function isSuccess() : bool
    {
        return $this->success;
    }

    /**
     * @param string $xid to assign to this transaction
     * @return void
     */
    public function addTransactionId(string $xid)
    {
        // shouldn't ever be set twice
        if (isset($this->xid)) {
            throw new \RuntimeException("xid must be assigned only once");
        }
        $this->xid = $xid;
    }

    /**
     * @return string|null the id assigned to this transaction
     */
    public function getTransactionId()
    {
        return $this->xid;
    }

    /**
     * @param IStorable $obj that has been touched and needs to be stored
     * @return void
     */
    public function addTouchedStorable(IStorable $obj)
    {
        $this->touchedStorables[$obj->getId()] = $obj;
    }

    /**
     * Marks an URL of an element as invalidated. Triggers recreation of the
     * URL mapping cache.
     *
     * @param string $elementId of the element whose URL changed
     * @return void
     */
    public function addTouchedUrl(string $elementId)
    {
        $this->touchedUrls[] = $elementId;
    }

    /**
     * @param string $element_id to which a new version has been added
     * @param int    $vno        version that got added
     * @return void
     */
    public function addElementVersionAdded(string $element_id, int $vno)
    {
        $this->elementVersionsAdded[] = [$element_id, $vno];
    }

    /**
     * @param string $msg to add
     * @return void
     */
    public function addInfoMessage(string $msg)
    {
        $this->infoMessages[] = $msg;
    }

    /**
     * @return array of collected informational messages (strings)
     */
    public function getInfoMessages() : array
    {
        return $this->infoMessages;
    }

    /**
     * Adds an error message and marks the covered operation as failed.
     *
     * @param string $msg to add
     * @return void
     */
    public function addErrorMessage(string $msg)
    {
        $this->success = false;
        $this->errorMessages[] = $msg;
    }

    /**
     * @return array of collected error messages (strings)
     */
    public function getErrorMessages() : array
    {
        return $this->errorMessages;
    }

    /**
     * @return IStorable[] touched objects that need to be stored
     */
    public function getTouchedStorables()
    {
        return array_values($this->touchedStorables);
    }

    /**
     * @return string[] ids of elements whose URL got modified
     */
    public function getTouchedUrls() : array
    {
        return $this->touchedUrls;
    }

    /**
     * @return array of pairs ($element_id, $vno) of added versions
     */
    public function getElementVersionsAdded()
    {
        return $this->elementVersionsAdded;
    }

    /**
     * getChanges
     *
     * @return array of changed parameters
     */
    public function getChanges()
    {
        return $this->changes;
    }

    /**
     * setChanges
     *
     * @param array $changes changes
     * @return void
     */
    public function setChanges($changes)
    {
        $this->changes = $changes;
    }

    /**
     * addChange
     *
     * @param string $type  type of change like state/version
     * @param string $value of changes like new version number or new state
     *
     * @return void
     */
    public function addChange($type, $value)
    {
        $this->changes[$type] = $value;
    }


    /**
     * @param string       $elementId  changed
     * @param string       $actionName performed
     * @param string|array $value      describing the change
     * @return void
     */
    public function appendClientInfo(
        string $elementId,
        string $actionName,
        $value
    ) {
        assert(is_bool($value) || is_string($value) || is_array($value));
        $this->clientInfo[$elementId][] = [$actionName, $value];
    }

    /**
     * @return array all client info combined
     */
    public function getClientInfo() : array
    {
        return $this->clientInfo;
    }
}
