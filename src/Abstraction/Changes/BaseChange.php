<?php

namespace Datahouse\Elements\Abstraction\Changes;

use Datahouse\Elements\Abstraction\Element;

/**
 * A base class for changes, providing some defaults and helper methods.
 *
 * @package Datahouse\Elements\Abstraction\Changes
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class BaseChange
{
    /**
     * @param int $elementNumber version number of an element
     * @return bool
     * @SuppressWarnings(PHPMD.UnusedFormalParameter("elementNumber"))
     */
    public function checkAddsElementVersion(int $elementNumber) : bool
    {
        return false;
    }

    /**
     * @param string $language to check
     * @return bool
     * @SuppressWarnings(PHPMD.UnusedFormalParameter("language"))
     */
    public function checkAddsLanguage(string $language) : bool
    {
        return false;
    }

    /**
     * @param string $elementId to check
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter("elementId"))
     */
    public function checkSetsParentId(string $elementId) : array
    {
        return [false, null];
    }

    /**
     * @param IChange[] $precedingChanges to check
     * @param int       $vno              to check
     * @return bool whether or not any of the preceding changes add the
     * current version number
     */
    public function txnAddsVersion(array $precedingChanges, int $vno)
    {
        $versionAdded = false;
        /* @var IChange $change */
        foreach ($precedingChanges as $change) {
            if ($change->checkAddsElementVersion($vno)) {
                $versionAdded = true;
                break;
            }
        }
        return $versionAdded;
    }

    /**
     * @param IChange[] $precedingChanges to check
     * @param string    $language         to check for
     * @return bool whether or not any of the preceding changes add the
     * given language to the current element version
     */
    public function txnAddsLanguage(
        array $precedingChanges,
        string $language
    ) : bool {
        $languageAdded = false;
        /* @var IChange $change */
        foreach ($precedingChanges as $change) {
            if ($change->checkAddsLanguage($language)) {
                $languageAdded = true;
                break;
            }
        }
        return $languageAdded;
    }

    /**
     * @param IChange[] $precedingChanges to check
     * @param Element   $element          to check
     * @return string|null id of the parent element or null for the root
     */
    public function txnGetElementParent(
        array $precedingChanges,
        Element $element
    ) {
        $elementId = $element->getId();
        $parentId = $element->getParentId();

        /* @var IChange $change */
        foreach ($precedingChanges as $change) {
            list ($result, $newId) = $change->checkSetsParentId($elementId);
            if ($result) {
                $parentId = $newId;
            }
        }

        return $parentId;
    }
}
