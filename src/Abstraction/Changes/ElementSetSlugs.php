<?php

namespace Datahouse\Elements\Abstraction\Changes;

use stdClass;

use Datahouse\Elements\Abstraction\Element;
use Datahouse\Elements\Abstraction\IStorageAdapter;
use Datahouse\Elements\Abstraction\Slug;
use Datahouse\Elements\Abstraction\TransactionResult;

/**
 * A change that set or replaces the set of active slugs of an element (which
 * define the urls on which the page element is visible).
 *
 * @package Datahouse\Elements\Abstraction\Changes
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
class ElementSetSlugs extends BaseElementVersionChange implements IChange
{
    protected $slugs;

    /**
     * @param Element $element       to set slugs for
     * @param int     $versionNumber affected
     * @param Slug[]  $slugs         to set
     */
    public function __construct(
        Element $element,
        int $versionNumber,
        array $slugs
    ) {
        parent::__construct($element, $versionNumber);
        $this->slugs = $slugs;
    }

    /**
     * @param IChange[] $precedingChanges previous changes in transaction
     * @return TransactionResult
     * @SuppressWarnings(PHPMD.UnusedFormalParameter("precedingChanges"))
     */
    public function validate(array $precedingChanges) : TransactionResult
    {
        $result = new TransactionResult();
        if (count($this->slugs) == 0) {
            $result->addErrorMessage('at least one slug is required');
        }

        $defaultLanguages = [];
        foreach ($this->slugs as $slug) {
            assert($slug instanceof Slug);
            if (!$slug->isValid()) {
                $result->addErrorMessage('invalid slug: ' . $slug->url);
            }
            if ($slug->default) {
                $defaultLanguages[$slug->language] = true;
            }
        }

        // Also check if there are default URLs for all available languages.
        $availableLanguages = array_unique(array_keys(
            $this->element->getNewestVersionNumberByLanguage()
        ));
        foreach ($availableLanguages as $language) {
            if (!array_key_exists($language, $defaultLanguages)) {
                $result->addErrorMessage(
                    "language '$language' needs a default URL"
                );
            }
        }

        return $result;
    }

    /**
     * Remember the old slugs and the exact element version.
     *
     * @return array associative, containing all data necessary to undo
     * this change.
     */
    public function collectRollbackInfo()
    {
        $ev = $this->getAffectedVersion();
        return [
            'element_id' => $this->element->getId(),
            'version_number' => $this->versionNumber,
            'old_slugs' => array_map(
                function (Slug $v) {
                    return $v->serialize();
                },
                $ev->getSlugs()
            )
        ];
    }

    /**
     * @param array $existingSlugs from the affected element version
     * @return array
     */
    protected function mergeSlugs(array $existingSlugs)
    {
        $newSlugs = array_values($this->slugs);

        // Generate a map of existing slugs by their url.
        $getSlugUrl = function (Slug $slug) {
            return $slug->url;
        };
        $newSlugUrls = array_flip(array_map($getSlugUrl, $newSlugs));

        // Add all former URLs as deprecated ones.
        foreach ($existingSlugs as $existingSlug) {
            if (!array_key_exists($existingSlug->url, $newSlugUrls)) {
                $existingSlug->deprecated = true;
                $newSlugs[] = $existingSlug;
            }
        }

        return $newSlugs;
    }

    /**
     * Stores the new slugs on the element.
     *
     * @return TransactionResult result of application
     */
    public function apply() : TransactionResult
    {
        $result = new TransactionResult();
        $ev = $this->getAffectedVersion();
        $ev->setSlugs($this->mergeSlugs($ev->getSlugs()));

        $result->addTouchedStorable($this->element);
        $result->addTouchedUrl($this->element->getId());

        foreach (array_keys($this->slugs) as $slugKey) {
            if (substr($slugKey, 0, 10) != 'deprecated') {
                $result->appendClientInfo(
                    $this->element->getId(),
                    'slug_good',
                    [$slugKey, null]
                );
            }
        }

        return $result;
    }

    /**
     * revert change
     *
     * @param IStorageAdapter $adapter storage adapter to use
     * @param stdClass        $rbi     the rollback information
     * @return array of changed IStorable objects
     */
    public static function revert(
        IStorageAdapter $adapter,
        stdClass $rbi
    ) : array {
        $element = $adapter->loadElement($rbi->{'element_id'});
        $ev = $element->getVersion($rbi->{'version_number'});

        $ev->setSlugs(array_map(
            function ($v) {
                $slug = new Slug();
                $slug->deserialize($v);
                return $slug;
            },
            $rbi->{'old_slugs'}
        ));
        return [$element];
    }
}
