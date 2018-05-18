<?php

namespace Datahouse\Elements\Control;

use Datahouse\Elements\Abstraction\Changes\Transaction;
use Datahouse\Elements\Abstraction\Element;
use Datahouse\Elements\Abstraction\FileMeta;
use Datahouse\Elements\Abstraction\IStorageAdapter;
use Datahouse\Elements\Abstraction\Slug;
use Datahouse\Elements\Abstraction\TransactionResult;
use Datahouse\Elements\Abstraction\User;
use Datahouse\Elements\Control\Exceptions\BadRequest;
use Datahouse\Elements\Control\Exceptions\NoOpException;

/**
 * This is the interface defining the change process of the CMS, defining the
 * valid states of element versions and their transition between states.
 *
 * @package Datahouse\Elements\Control
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
interface IChangeProcess
{
    /**
     * @return string name of the initial state
     */
    public function getInitialState() : string;

    /**
     * @return string name of the final, i.e. deleted state
     */
    public function getFinalState() : string;

    /**
     * @return array of strings with all allowed states
     */
    public function enumAllowedStates() : array;

    /**
     * @param Element $element scan for unreachable versions
     * @return void
     */
    public function markUnreachableElementVersions(Element $element);

    /**
     * Generates a valid transaction that modifies an element. Underneath
     * creating new versions as appropriate.
     *
     * @param User    $user           for which to plan a transaction
     * @param Element $element        to modify
     * @param int     $baseVno        to modify
     * @param string  $pageLanguage   to modify
     * @param string  $editLanguage   target language to modify
     * @param array   $fieldNameParts specifying the field to modify
     * @param string  $newContent     to set
     * @return Transaction covering the element field modification
     * @throws NoOpException
     */
    public function planTxnForElementContentChange(
        User $user,
        Element $element,
        int $baseVno,
        string $pageLanguage,
        string $editLanguage,
        array $fieldNameParts,
        string $newContent
    ) : Transaction;

    /**
     * @param User    $user       for which to plan the transaction
     * @param Element $element    to modify
     * @param int     $vno        to modify
     * @param string  $newState   to store
     * @param array   $references to modify as well
     * @return Transaction covering the element state change
     */
    public function planTxnForElementStateChange(
        User $user,
        Element $element,
        int $vno,
        string $newState,
        array $references
    ) : Transaction;

    /**
     * @param User    $user       for which to plan the transaction
     * @param Element $parent     the parent
     * @param string  $lang       the current language
     * @param string  $eleDefName default element definition
     * @param string  $name       of the new child element
     * @param array   $slugs      initial slugs for the new child element
     *
     * @return Transaction covering the new element change
     */
    public function planTxnForElementAddChildChange(
        User $user,
        Element $parent,
        string $lang,
        string $eleDefName,
        string $name,
        array $slugs
    ) : Transaction;

    /**
     * @param User    $user         for which to plan a transaction
     * @param Element $element      the element to change
     * @param int     $vno          version number to change
     * @param string  $pageLanguage originally displayed to the editor
     * @param string  $editLanguage target language to modify
     * @param string  $subName      name of the sub element collection
     * @return Transaction covering the new element change
     * @throws BadRequest
     */
    public function planTxnForElementAddSubChange(
        User $user,
        Element $element,
        int $vno,
        string $pageLanguage,
        string $editLanguage,
        string $subName
    ) : Transaction;

    /**
     * @param User    $user         for which to plan the transaction
     * @param Element $element      the parent of the sub element to remove
     * @param int     $vno          version number to change
     * @param string  $pageLanguage originally displayed to the editor
     * @param string  $editLanguage target language to modify
     * @param string  $subName      name of the sub element collection
     * @param int     $subIndex     of the sub element to remove
     * @return Transaction covering the new element change
     */
    public function planTxnForElementRemoveSub(
        User $user,
        Element $element,
        int $vno,
        string $pageLanguage,
        string $editLanguage,
        string $subName,
        int $subIndex
    ) : Transaction;

    /**
     * @param User    $user         for which to plan the transaction
     * @param Element $element      the current element
     * @param int     $vno          the current version number
     * @param string  $templateName name of the template to be set
     *
     * @return Transaction covering the new template
     */
    public function planTxnForElementTemplateChange(
        User $user,
        Element $element,
        int $vno,
        string $templateName
    ) : Transaction;

    /**
     * planTxnForFileUpload create file in storage
     *
     * @param User     $user     for which to plan the transaction
     * @param FileMeta $fileMeta about the uploaded file
     * @param string   $type     image or document
     * @return Transaction covering the element field modification
     */
    public function planTxnForFileUpload(
        User $user,
        FileMeta $fileMeta,
        string $type
    ) : Transaction;

    /**
     * @param User    $user    for which to plan the transaction
     * @param Element $element to remove
     * @param int     $vno     base of the invalidation decision
     * @return Transaction covering the element removal
     */
    public function planTxnForElementRemove(
        User $user,
        Element $element,
        int $vno
    ) : Transaction;

    /**
     * @param User         $user        for which to plan the transaction
     * @param Element      $element     to move
     * @param int          $vno         of the element to move
     * @param Element      $oldParent   of the element to move
     * @param Element      $newParent   of the element to move
     * @param string|null  $insertBefore describes insertion point
     * @return Transaction
     */
    public function planTxnForElementMove(
        User $user,
        Element $element,
        int $vno,
        Element $oldParent,
        Element $newParent,
        $insertBefore
    ) : Transaction;

    /**
     * @param User    $user    for which to plan the transaction
     * @param Element $element to edit
     * @param int     $vno     to edit
     * @param string  $refName to set
     * @param string  $target  new target for the reference
     * @return Transaction
     */
    public function planTxnForElementSetReference(
        User $user,
        Element $element,
        int $vno,
        string $refName,
        string $target
    ) : Transaction;

    /**
     * @param User     $user    for which to plan the addition
     * @param Element  $element the parent
     * @param int      $vno     base for the change
     * @param string[] $urls    new set of URLs to use for the page
     * @return Transaction covering the new element change
     */
    public function planTxnForElementSetUrls(
        User $user,
        Element $element,
        int $vno,
        array $urls
    ) : Transaction;
}
