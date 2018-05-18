<?php

namespace Datahouse\Elements\Control;

use RuntimeException;

use Datahouse\Elements\Abstraction\Changes\AddFileMeta;
use Datahouse\Elements\Abstraction\Changes\ElementAttachChildElement;
use Datahouse\Elements\Abstraction\Changes\ElementAddSubElement;
use Datahouse\Elements\Abstraction\Changes\ElementAddVersion;
use Datahouse\Elements\Abstraction\Changes\ElementContentsChange;
use Datahouse\Elements\Abstraction\Changes\ElementCopyContents;
use Datahouse\Elements\Abstraction\Changes\ElementCreate;
use Datahouse\Elements\Abstraction\Changes\ElementDetachChildElement;
use Datahouse\Elements\Abstraction\Changes\ElementRemoveSubElement;
use Datahouse\Elements\Abstraction\Changes\ElementSetParent;
use Datahouse\Elements\Abstraction\Changes\ElementSetReference;
use Datahouse\Elements\Abstraction\Changes\ElementSetSlugs;
use Datahouse\Elements\Abstraction\Changes\ElementStateChange;
use Datahouse\Elements\Abstraction\Changes\ElementDefinitionChange;
use Datahouse\Elements\Abstraction\Changes\IChange;
use Datahouse\Elements\Abstraction\Changes\Transaction;
use Datahouse\Elements\Abstraction\Element;
use Datahouse\Elements\Abstraction\ElementVersion;
use Datahouse\Elements\Abstraction\FileMeta;
use Datahouse\Elements\Abstraction\Slug;
use Datahouse\Elements\Abstraction\User;
use Datahouse\Elements\Constants;
use Datahouse\Elements\Control\Exceptions\BadRequest;
use Datahouse\Elements\Control\Exceptions\NoOpException;

/**
 * Something in between a generally useful example and a basis for any kind
 * of ChangeProcess that features a 'published' state.
 *
 * @package Datahouse\Elements\Control
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
abstract class BasePublishChangeProcess extends BaseChangeProcess
{
    /**
     * Allow specifying one or more states that count as 'published'.
     *
     * @param string $state to check
     * @return bool
     */
    abstract protected function isPublishState(string $state) : bool;

    /**
     * Check if an element version may be changed.
     *
     * @param ElementVersion $ev to check
     * @return bool
     */
    abstract protected function allowVersionContentChange(
        ElementVersion $ev
    ) : bool;

    /**
     * Ignoring authentication, this simply returns the highest version number
     * of this element.
     *
     * @param Element $element to check
     * @return array map of language to version number
     */
    public function getPublishedVersionNumberByLanguage(
        Element $element
    ) : array {
        $result = [];
        /* @var ElementVersion $ev */
        foreach ($element->getVersions() as $vno => $ev) {
            foreach (array_keys($ev->getLanguages()) as $language) {
                if ($this->isPublishState($ev->getState()) &&
                    (!array_key_exists($language, $result) ||
                        $vno >= $result[$language])
                ) {
                    $result[$language] = $vno;
                }
            }
        }
        return $result;
    }

    /**
     * Ignoring authentication, this returns all versions of an element that
     * are considered editable.
     *
     * @param Element $element to check
     * @return array map of language to editable version number
     */
    protected function getAllEditableVersions(Element $element)
    {
        $result = [];
        /* @var ElementVersion $ev */
        foreach ($element->getVersions() as $vno => $ev) {
            foreach (array_keys($ev->getLanguages()) as $language) {
                if ($this->allowVersionContentChange($ev)) {
                    if (!array_key_exists($language, $result)
                        || $vno > $result[$language]
                    ) {
                        $result[$language] = $vno;
                    }
                }
            }
        }
        return $result;
    }

    /**
     * Collect a list of all version that are published or editing
     *
     * @param Element $element to check
     * @return array reachable versions numbers
     */
    protected function getReachableVersions(Element $element) : array
    {
        $publishedVersions = $this->getPublishedVersionNumberByLanguage(
            $element
        );

        $result = [];
        /* @var ElementVersion $ev */
        foreach ($element->getVersions() as $vno => $ev) {
            foreach (array_keys($ev->getLanguages()) as $language) {
                if ($vno >= ($publishedVersions[$language] ?? 0)) {
                    $result[$vno] = true;
                }
            }
        }
        return $result;
    }

    /**
     * @param Element $element to mark
     * @return void
     */
    public function markUnreachableElementVersions(Element $element)
    {
        $reachableVersions = $this->getReachableVersions($element);

        /* @var ElementVersion $ev */
        foreach ($element->getVersions() as $vno => $ev) {
            if (!array_key_exists($vno, $reachableVersions)) {
                $ev->setUnreachable();
            }
        }
    }

    /**
     * @param Element $element      to generate changes for
     * @param int     $vno          origin version number
     * @param string  $copyFromLang origin language
     * @param int     $editableVno  target version number
     * @param string  $editLanguage target language
     * @return IChange[]
     */
    protected function genChangesForCopyingSlugs(
        Element $element,
        int $vno,
        string $copyFromLang,
        int $editableVno,
        string $editLanguage
    ) {
        // Skip elements that don't have slugs.
        $type = $element->getType();
        if (!in_array($type, Constants::ELEMENT_TYPES_WITH_SLUGS)) {
            return [];
        }

        $ev = $element->getVersion($vno);
        $slugs = $ev->getSlugs();

        // The following addition ensures there's a default slug for the
        // newly created language.
        if ($copyFromLang != $editLanguage) {
            $defaultSlug = null;
            foreach ($slugs as $slug) {
                if ($slug->default && $slug->language == $copyFromLang) {
                    $defaultSlug = $slug;
                    break;
                }
            }

            // All elements with slugs *must* have exactly one default slug
            // for the language that contents get copied from.
            assert(!is_null($defaultSlug));

            $newSlug = new Slug();
            $newSlug->default = true;
            $newSlug->language = $editLanguage;
            $newSlug->url = $defaultSlug->url;
            $slugs[] = $newSlug;

            // New array of slugs is now ready, adjust all reachable element
            // versions.
            $changes = $this->genChangesForSetSlugs($element, $slugs);
        } else {
            // No changes to the slugs, only the new element version needs to
            // have the existing slugs copied.
            $changes = [];
        }

        // Add a final change that sets the existing or modified slugs on
        // the newly created element version.
        $changes[] = new ElementSetSlugs($element, $editableVno, $slugs);

        return $changes;
    }

    /**
     * This gets the latest editable version for a specific language. If
     * no such editable version exists, it creates one and selects the proper
     * source language to copy contents from.
     *
     * @param Element $element      to modify
     * @param int     $vno          the basis for the modification
     * @param string  $pageLanguage the basis for the modification
     * @param string  $editLanguage the target language to store to
     * @return array a tuple with: changes for a transaction (array), the
     *               editable version (may be null), the conflicting version
     *               (may be null) and the language contents got copied from.
     */
    protected function getEditableVersion(
        Element $element,
        int $vno,
        string $pageLanguage,
        string $editLanguage
    ) : array {
        // Check the newest version for the language to edit and overall.
        $newestVersions = $element->getNewestVersionNumberByLanguage();
        $newestVno = $element->getNewestVersionNumber();
        $langExists = array_key_exists($editLanguage, $newestVersions);

        if ($langExists) {
            $langVno = $newestVersions[$editLanguage];

            if ($langVno != $vno) {
                // Not editable, a newer version exists. Return a $langVno as
                // the conflicting version number.
                return [[], null, $langVno, $editLanguage];
            }

            $ev = $element->getVersion($vno);
            $existingLanguages = $ev->getLanguages();
            $vnoHasEditLang = array_key_exists(
                $editLanguage,
                $existingLanguages
            );
        } else {
            // Language does not exist. A new translation is being created.
            $ev = null;
            $vnoHasEditLang = false;
        }

        $changes = [];
        if (isset($ev) &&
            $this->allowVersionContentChange($ev) &&
            $vnoHasEditLang
        ) {
            // The version exists and is editable. Note that we still check
            // if there's any change required at all.
            $copyFromLang = $editLanguage;
            $editableVno = $vno;
        } else {
            // generate a new element version
            $editableVno = $newestVno + 1;
            $changes[] = new ElementAddVersion(
                $element,
                $editableVno,
                $this->getInitialState()
            );

            // Determine the language to copy contents from.
            $copyFromLang = $vnoHasEditLang ? $editLanguage : $pageLanguage;
            // Copy from the best version and language combination.
            $changes[] = new ElementCopyContents(
                $element,
                $vno,
                $copyFromLang,
                $editableVno,
                $editLanguage
            );

            $slug_changes = $this->genChangesForCopyingSlugs(
                $element,
                $vno,
                $copyFromLang,
                $editableVno,
                $editLanguage
            );
            $changes = array_merge($changes, $slug_changes);
        }
        return [$changes, $editableVno, null, $copyFromLang];
    }

    /**
     * Collect a list of all editable versions which need to be modified for
     * language-independent changes. If no such version exists, this will
     * create a new version based on the newest one.
     *
     * @param Element $element to modify
     * @param int     $baseVno as a basis for the modification
     * @return array
     * @throws BadRequest
     */
    protected function getVersionsForLangIndependentChange(
        Element $element,
        int $baseVno
    ) : array {
        $changes = [];
        $editableVersions = $this->getAllEditableVersions($element);
        if (empty($editableVersions)) {
            // No editable version, check if we are based off a published
            // version at least.
            $publishedVersions = array_flip(
                $this->getPublishedVersionNumberByLanguage($element)
            );
            if (!array_key_exists($baseVno, $publishedVersions)) {
                throw new BadRequest("newer version exists");
            }

            // Generate an editable version from the given version - whatever
            // language(s) that one covers.
            $ev = $element->getVersion($baseVno);
            if (!$this->isPublishState($ev->getState())) {
                throw new BadRequest(
                    "cannot change element in state " . $ev->getState()
                );
            }
            $existingLanguages = array_keys($ev->getLanguages());

            $genVno = $element->getNewestVersionNumber() + 1;
            $changes[] = new ElementAddVersion(
                $element,
                $genVno,
                $this->getInitialState()
            );

            // Copy all languages of the last version.
            $editableVersions = [];
            foreach ($existingLanguages as $lang) {
                $changes[] = new ElementCopyContents(
                    $element,
                    $baseVno,
                    $lang,
                    $genVno,
                    $lang
                );

                $slug_changes = $this->genChangesForCopyingSlugs(
                    $element,
                    $baseVno,
                    $lang,
                    $genVno,
                    $lang
                );
                $changes = array_merge($changes, $slug_changes);
                $editableVersions[$lang] = $genVno;
            }
        } else {
            // One or more versions in editable state exist. We need modify
            // all of them for consistency. However, the base for the change
            // should be one of these editable versions.
            $publishedVersions = array_flip(
                $this->getPublishedVersionNumberByLanguage($element)
            );

            if (!array_key_exists($baseVno, array_flip($editableVersions))) {
                if (!array_key_exists($baseVno, $publishedVersions)) {
                    throw new BadRequest("newer version exists");
                } else {
                    // FIXME: in case there is an editable version for some
                    // language, but the admin currently is on a different
                    // langugage without an editing version, we have a problem.
                    throw new BadRequest(
                        "newer version exists in language: "
                        . implode(', ', array_keys($editableVersions))
                    );
                }
            }
        }
        return array($changes, $editableVersions);
    }

    /**
     * @param User    $user           for which to plan a transaction
     * @param Element $element        to modify
     * @param int     $baseVno        starting point for the modification
     * @param string  $pageLanguage   originally displayed to the editor
     * @param string  $editLanguage   target language to modify
     * @param array   $fieldNameParts specifying the field to modify
     * @param string  $newContent     to set
     * @return Transaction covering the element field modification
     * @throws NoOpException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter('user'))
     */
    public function planTxnForElementContentChange(
        User $user,
        Element $element,
        int $baseVno,
        string $pageLanguage,
        string $editLanguage,
        array $fieldNameParts,
        string $newContent
    ) : Transaction {
        $lastPart = $fieldNameParts[count($fieldNameParts) - 1];
        if (substr($lastPart, 0, 4) == 'tag:') {
            $pubVersions = $this->getPublishedVersionNumberByLanguage($element);
            /** @var ElementVersion $ev of the current element */
            $changes = [];
            foreach ($element->getVersions() as $vno => $ev) {
                foreach (array_keys($ev->getLanguages()) as $language) {
                    if ($vno >= ($pubVersions[$language] ?? 0)) {
                        $changes[] = new ElementContentsChange(
                            $element,
                            $vno,
                            $language,
                            $fieldNameParts,
                            $newContent
                        );
                    }
                }
            }
            return new Transaction($changes);
        } else {
            list($changes, $editableVno, $newerVno, $copyFromLang)
                = $this->getEditableVersion(
                    $element,
                    $baseVno,
                    $pageLanguage,
                    $editLanguage
                );

            // Check if there's a modification at all, raise a no-op, otherwise.
            //
            // Note that we compare with the conflicting version, if there is a
            // conflict with another edit (which may have originated from the
            // very same user - Froala trying to save twice. We don't want to
            // blame the user for that, see #5051).
            //
            // Otherwise we compare with the base version number. If there's no
            // change against that one, no changes are being applied, ever.
            $ev = $element->getVersion($editableVno ? $baseVno : $newerVno);
            $ec = $ev->getContentsFor($copyFromLang);
            $curValue = $ec->getField($fieldNameParts);
            if (!is_null($curValue) && $curValue == $newContent) {
                throw new NoOpException();
            }

            // Looks like either Froala has a bug or we are using it
            // incorrectly, but sometimes it tries to store an image field with
            // a bad image source (prefixed with blob:). Treat these attempts
            // to save the image as a NoOp, so the (hopefully correct)
            // follow-up save request can succeeed.
            if (strpos($newContent, "src=\"blob:http") !== false) {
                error_log(
                    "WARNING: refusing to save an intermediate Froala state"
                );
                throw new NoOpException();
            }

            // Only after that, check if check if there is an in-flight
            // collision.
            if (is_null($editableVno)) {
                throw new BadRequest('newer versions exist');
            }

            // Add the actual content change.
            $changes[] = new ElementContentsChange(
                $element,
                $editableVno,
                $editLanguage,
                $fieldNameParts,
                $newContent
            );

            return new Transaction($changes);
        }
    }

    /**
     * @param User    $user       for which to plan the transaction
     * @param Element $element    to modify
     * @param int     $vno        to modify
     * @param string  $newState   to store
     * @param array   $references to modify as well
     * @return Transaction covering the element state change
     * @SuppressWarnings(PHPMD.UnusedFormalParameter('user'))
     */
    public function planTxnForElementStateChange(
        User $user,
        Element $element,
        int $vno,
        string $newState,
        array $references
    ) : Transaction {
        // FIXME: logic for disallowing editors to publish

        $ev = $element->getVersion($vno);
        $linkedElements = array_flip($ev->getLinks());

        $changes = [];

        if ($ev->getState() != $newState) {
            $changes[] = new ElementStateChange($element, $vno, $newState);
            error_log("changing base page element " . $element->getId() . " to state $newState");
        } else {
            error_log("base page element " . $element->getId() . " already is in state $newState");
        }

        foreach ($references as $elementId => $info) {
            if (array_key_exists($elementId, $linkedElements)) {
                error_log("element $elementId not directly referenced");
            }

            /* @var Element $refElement */
            $refElement = $info['element'];

            // FIXME: check permissions for ref element and newest version of
            // it...

            /* @var ElementVersion $refEv */
            $refEv = $refElement->getVersion($info['version']);

            if (!array_key_exists($info['language'], $refEv->getLanguages())) {
                // The given version doesn't have the requested language, so
                // we better skip changing state of that referenced element.
                error_log(
                    "ref element $elementId version " . $info['version']
                    . " doesn't have language " . $info['language']
                );
                continue;
            }

            if ($refEv->getState() != $newState) {
                $changes[] = new ElementStateChange(
                    $info['element'],
                    $info['version'],
                    $newState
                );
                error_log("also changing element $elementId to state $newState");
            }
        }

        if (empty($changes)) {
            throw new NoOpException("no element to change");
        }

        return new Transaction($changes);
    }

    /**
     * @param User    $user       for which to plan the addition
     * @param Element $parent     the parent
     * @param string  $lang       the current language
     * @param string  $eleDefName default element definition
     * @param string  $name       of the new child element
     * @param array   $slugs      initial slugs for the new child element
     * @return Transaction covering the new element change
     * @SuppressWarnings(PHPMD.UnusedFormalParameter('user'))
     */
    public function planTxnForElementAddChildChange(
        User $user,
        Element $parent,
        string $lang,
        string $eleDefName,
        string $name,
        array $slugs
    ) : Transaction {
        // Note that the list of children is considered a cache of possible
        // children. They need to be re-evaluated for visibility depending on
        // the child page's status. Therefore, we need to add the hint for the
        // new child page on the oldest possibly visible published page.
        $pubVersions = array_values(
            $this->getPublishedVersionNumberByLanguage($parent)
        );
        if (count($pubVersions) == 0) {
            $firstVnoToModify = $parent->getNewestVersionNumber();
        } else {
            $firstVnoToModify = min($pubVersions);
        }

        // JavaScript doesn't know what type of element the user want to
        // create. We use some simple logic...
        $parentType = $parent->getType();
        if (!in_array($parentType, ['collection', 'page'])) {
            throw new RuntimeException(
                "Cannot create children for parents of type $parentType"
            );
        }
        $type = $parentType == 'collection' ? 'snippet' : 'page';

        $createChange = new ElementCreate($lang, $name, $type, $eleDefName);
        $element = $createChange->getGeneratedElement();
        $addChange = new ElementAttachChildElement(
            $parent,
            $firstVnoToModify,
            $element,
            null
        );
        $changes = [
            $createChange,
            new ElementSetParent($element, 1, $parent),
            $addChange
        ];
        if (!empty($slugs)) {
            $changes[] = new ElementSetSlugs($element, 1, $slugs);
        }
        return new Transaction($changes);
    }

    /**
     * @param User    $user         for which to plan a transaction
     * @param Element $element      the element to change
     * @param int     $vno          version number to change
     * @param string  $pageLanguage originally displayed to the editor
     * @param string  $editLanguage target language to modify
     * @param string  $subName      name of the sub element collection
     * @return Transaction covering the new element change
     * @SuppressWarnings(PHPMD.UnusedFormalParameter('user'))
     */
    public function planTxnForElementAddSubChange(
        User $user,
        Element $element,
        int $vno,
        string $pageLanguage,
        string $editLanguage,
        string $subName
    ) : Transaction {
        list($changes, $editableVno, $newerVno, ) = $this->getEditableVersion(
            $element,
            $vno,
            $pageLanguage,
            $editLanguage
        );

        if (is_null($editableVno)) {
            throw new BadRequest("newer versions $newerVno exist");
        }

        $changes[] = new ElementAddSubElement(
            $element,
            $editableVno,
            $editLanguage,
            $subName
        );

        return new Transaction($changes);
    }

    /**
     * @param User    $user         for which to plan the transaction
     * @param Element $element      the parent of the sub element to remove
     * @param int     $vno          version number to change
     * @param string  $pageLanguage originally displayed to the editor
     * @param string  $editLanguage target language to modify
     * @param string  $subName      name of the sub element collection
     * @param int     $subIndex     of the sub element to remove
     * @return Transaction covering the new element change
     * @SuppressWarnings(PHPMD.UnusedFormalParameter('user'))
     */
    public function planTxnForElementRemoveSub(
        User $user,
        Element $element,
        int $vno,
        string $pageLanguage,
        string $editLanguage,
        string $subName,
        int $subIndex
    ) : Transaction {
        list($changes, $editableVno, $newerVno, ) = $this->getEditableVersion(
            $element,
            $vno,
            $pageLanguage,
            $editLanguage
        );

        if (is_null($editableVno)) {
            throw new BadRequest("newer versions $newerVno exist");
        }

        $changes[] = new ElementRemoveSubElement(
            $element,
            $editableVno,
            $pageLanguage,
            $subName,
            $subIndex
        );

        return new Transaction($changes);
    }

    /**
     * For now, this will delete the entire element, not just a single
     * language. And it doesn't differentiate between editor and reviewer,
     * but still allows editors to remove entire (published) elements.
     *
     * @param User    $user    for which to plan the transaction
     * @param Element $element to remove
     * @param int     $vno     basis of the removal decision
     * @return Transaction executing the removal
     * @SuppressWarnings(PHPMD.UnusedFormalParameter('user'))
     */
    public function planTxnForElementRemove(
        User $user,
        Element $element,
        int $vno
    ) : Transaction {
        $availableLanguages = $element->getNewestVersionNumberByLanguage();

        $genVno = $element->getNewestVersionNumber() + 1;
        $changes[] = new ElementAddVersion(
            $element,
            $genVno,
            $this->getFinalState()
        );

        // Copy content from all available languages, so this last version
        // marked as deleted has an element name.
        foreach ($availableLanguages as $lang => $vno) {
            $changes[] = new ElementCopyContents(
                $element,
                $vno,
                $lang,
                $genVno,
                $lang
            );
        }

        return new Transaction($changes);
    }

    /**
     * @param User         $user        for which to plan the transaction
     * @param Element      $element     to move
     * @param int          $vno         of the element to move
     * @param Element      $oldParent   of the element to move
     * @param Element      $newParent   of the element to move
     * @param string|null  $insertBefore describes insertion point
     * @return Transaction
     * @SuppressWarnings(PHPMD.UnusedFormalParameter('user'))
     * @SuppressWarnings(PHPMD.UnusedFormalParameter('vno'))
     */
    public function planTxnForElementMove(
        User $user,
        Element $element,
        int $vno,
        Element $oldParent,
        Element $newParent,
        $insertBefore
    ) : Transaction {
        // Caller needs to load the proper old parent element.
        assert($oldParent->getId() == $element->getParentId());

        if ($oldParent->getId() == $newParent->getId()) {
            $parent = $oldParent;
            $pubVersions = array_values(
                $this->getPublishedVersionNumberByLanguage($parent)
            );

            $firstParentVno = min($pubVersions);
            $changes[] = new ElementDetachChildElement(
                $parent,
                $firstParentVno,
                $element
            );
            $changes[] = new ElementAttachChildElement(
                $oldParent,
                $firstParentVno,
                $element,
                $insertBefore
            );
            return new Transaction($changes);
        } else {
            // FIMXE: move between parents is not implemented, yet.
            throw new RuntimeException("general move not implemented");
        }
    }

    /**
     * @param User    $user    for which to plan the transaction
     * @param Element $element to edit
     * @param int     $vno     to edit
     * @param string  $refName to set
     * @param string  $target  new target for the reference
     * @return Transaction
     * @SuppressWarnings(PHPMD.UnusedFormalParameter('user'))
     */
    public function planTxnForElementSetReference(
        User $user,
        Element $element,
        int $vno,
        string $refName,
        string $target
    ) : Transaction {
        list($changes, $editableVersions)
            = $this->getVersionsForLangIndependentChange($element, $vno);

        // There's one or more editable version(s). Modify them all.
        foreach (array_values($editableVersions) as $editableVno) {
            $changes[] = new ElementSetReference(
                $element,
                $editableVno,
                $refName,
                $target
            );
        }
        return new Transaction($changes);
    }

    /**
     * @param User    $user         for which to plan the transaction
     * @param Element $element      the current element
     * @param int     $vno          the current version number
     * @param string  $templateName name of the template to be set
     * @return Transaction covering the new template
     * @SuppressWarnings(PHPMD.UnusedFormalParameter('user'))
     */
    public function planTxnForElementTemplateChange(
        User $user,
        Element $element,
        int $vno,
        string $templateName
    ) : Transaction {
        list($changes, $editableVersions)
            = $this->getVersionsForLangIndependentChange($element, $vno);

        // There's one or more editable version(s). Modify them all.
        foreach (array_values($editableVersions) as $editableVno) {
            $changes[] = new ElementDefinitionChange(
                $element,
                $editableVno,
                $templateName
            );
        }
        return new Transaction($changes);
    }

    /**
     * @param User     $user     for which to plan the transaction
     * @param FileMeta $fileMeta about the uploaded file
     * @param string   $type     image or document
     * @return Transaction covering the element field modification
     * @SuppressWarnings(PHPMD.UnusedFormalParameter('user'))
     */
    public function planTxnForFileUpload(
        User $user,
        FileMeta $fileMeta,
        string $type
    ) : Transaction {
        // FIXME: permission checks...
        return new Transaction([new AddFileMeta($fileMeta, $type)]);
    }

    /**
     * @param Element $element
     * @param array   $slugs
     * @return IChange[]
     */
    public function genChangesForSetSlugs(Element $element, array $slugs)
    {
        // There's one or more reachable version(s). Modify them all.
        $reachableVersions = $this->getReachableVersions($element);
        $changes = array_map(function ($reachableVno) use ($element, $slugs) {
            return new ElementSetSlugs(
                $element,
                $reachableVno,
                $slugs
            );
        }, array_keys($reachableVersions));
        return $changes;
    }

    /**
     * @param User    $user    for which to plan the addition
     * @param Element $element the parent
     * @param int     $vno     base for the change
     * @param Slug[]  $slugs   new slugs to set for this page
     * @return Transaction covering the new element change
     * @SuppressWarnings(PHPMD.UnusedFormalParameter('user'))
     * @SuppressWarnings(PHPMD.UnusedFormalParameter('vno'))
     */
    public function planTxnForElementSetUrls(
        User $user,
        Element $element,
        int $vno,
        array $slugs
    ) : Transaction {
        return new Transaction($this->genChangesForSetSlugs($element, $slugs));
    }
}
