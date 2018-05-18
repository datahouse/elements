<?php

namespace Datahouse\Elements\Abstraction\Cache;

use stdClass;
use RuntimeException;

use Datahouse\Elements\Abstraction\BaseStorageAdapter;
use Datahouse\Elements\Abstraction\Element;
use Datahouse\Elements\Abstraction\ElementVersion;
use Datahouse\Elements\Abstraction\Exceptions\SerDesException;
use Datahouse\Elements\Abstraction\ISerializable;
use Datahouse\Elements\Abstraction\IStorageAdapter;
use Datahouse\Elements\Abstraction\Slug;
use Datahouse\Elements\Abstraction\UrlPointer;
use Datahouse\Elements\Constants;

/**
 * A helper class for element url resolving and regenerating the url mappings
 * (which should be considered a cache).
 *
 * @package Datahouse\Elements\Abstraction
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
class ElementUrlCache implements ISerializable
{
    protected $adapter;
    /* @var array $perElementUrls object-local cache of an element's pointers */
    protected $perElementUrls;
    /* @var stdClass $urlMapping of urls to element ids */
    protected $urlMapping;

    /**
     * Helper class for generating portions or all of the elements-urls-
     * mapping.
     *
     * @param IStorageAdapter $adapter to use
     */
    public function __construct(IStorageAdapter $adapter)
    {
        $this->adapter = $adapter;

        // This forward mapping is only cached in APCU, not on persistent
        // storage. It's too trivial to build from Elements.
        $this->perElementUrls = apcu_fetch('element-url-mapping');
        if (!$this->perElementUrls) {
            $this->perElementUrls = [];
        }

        // Query the APCU cache, first.
        $this->urlMapping = apcu_fetch('url-element-mapping');
        if ($this->urlMapping === false) {
            // If that fails, query the adapter for a mapping stored in
            // persistent cache.
            $ser_data = $this->adapter->loadUrlMapping();
            if (isset($ser_data) && property_exists($ser_data, 'urlMapping')) {
                $this->deserialize($ser_data);
                $this->storeToAPCU();
            } else {
                $this->createUrlMapping();
                assert($this->urlMapping instanceof stdClass);
                assert(get_class($this->urlMapping) == 'stdClass');
            }
        }

        $mapping = $this->urlMapping;
        assert(is_object($mapping));
        assert($mapping instanceof stdClass);
        assert(get_class($mapping) == 'stdClass');
    }

    /**
     * Stores the current url to element mapping to ACPU.
     *
     * @return void
     */
    private function storeToAPCU()
    {
        $retries = 5;
        do {
            $url_element_mapping = $this->urlMapping;
            assert(get_class($url_element_mapping) == 'stdClass');
            apcu_store('url-element-mapping', $url_element_mapping);

            $checkMapping = apcu_fetch('url-element-mapping');
            if (get_class($checkMapping) != 'stdClass') {
                sleep(0.5);
                apcu_delete('url-element-mapping');
                apcu_clear_cache();
                $retries -= 1;
            }
        } while ($retries > 0 && get_class($checkMapping) != 'stdClass');

        if ($retries == 0) {
            error_log("FATAL: saving to APCU failed");
        }
    }

    /**
     * @return stdClass serialized or at least a serializable representation
     * of the object
     * @throws SerDesException
     */
    public function serialize() : stdClass
    {
        $ser_data = new stdClass();
        $ser_data->{'urlMapping'} = new stdClass();
        /* @var UrlPointer $urlp */
        foreach (get_object_vars($this->urlMapping) as $url => $urlp) {
            $ser_data->{'urlMapping'}->{$url} = $urlp->serialize();
        }
        return $ser_data;
    }

    /**
     * @param stdClass $data to deserialize from
     * @return void
     * @throws SerDesException
     */
    public function deserialize(stdClass $data)
    {
        $mapping = new stdClass();
        foreach (get_object_vars($data->{'urlMapping'}) as $url => $ptr_data) {
            $urlp = new UrlPointer($url, ['en']);
            $urlp->deserialize($ptr_data);
            $mapping->{$url} = $urlp;
        }
        $this->urlMapping = $mapping;
    }

    /**
     * Resolves a slug to an absolute URL. No side effects.
     *
     * @param string|null $parentId to use (may not be set for the element)
     * @param Slug        $slug     to resolve
     * @return array of resolved urls
     */
    protected function resolveSlug(
        $parentId,
        Slug $slug
    ) : array {
        assert(is_array($this->perElementUrls));
        // The caller must ensure this element's parent has already been
        // loaded.
        assert(array_key_exists($parentId, $this->perElementUrls));
        $url = $slug->url;
        if ($url[0] === '/') {
            // absolute url
            return [[
                strtolower($url),
                $slug->language,
                $slug->default ?? false,
                $slug->deprecated ?? false
            ]];
        } else {
            if (is_null($parentId)) {
                // a top-level element
                $absUrl = '/' . $url;
                return [[
                    strtolower($absUrl),
                    $slug->language,
                    $slug->default ?? false,
                    $slug->deprecated ?? false
                ]];
            } else {
                // an url relative to some parent

                // resolve references current or parent directory
                // references
                while (substr($url, 0, 2) === './' ||
                    substr($url, 0, 3) === '../'
                ) {
                    if (substr($url, 0, 2) === './') {
                        $url = substr($url, 2);
                    } else {
                        $parent = $this->adapter->loadElement($parentId);
                        $parentId = $parent->getParentId();
                        $url = substr($url, 3);
                    }
                }

                // Assemble absolute urls for this element - note that we
                // only ever base on a parent url that matches the slug's
                // language.
                assert(array_key_exists($parentId, $this->perElementUrls));
                $matchingParentUrls = array_filter(
                    $this->perElementUrls[$parentId],
                    function ($entry) use ($slug) {
                        list (, $language,,) = $entry;
                        return $language == $slug->language;
                    }
                );

                // Note that matchingParentUrls may well be empty, here.

                /*
                if (count($matchingParentUrls) == 0) {
                    throw new RuntimeException(
                        "no parent url for parent $parentId and language " .
                        $slug->language . ", resolving url '$url'"
                    );
                }
                assert(count($matchingParentUrls) > 0);
                */

                $urls = [];
                foreach ($matchingParentUrls as $entry) {
                    list ($parentUrl,, $default, $deprecated) = $entry;
                    assert(is_bool($default));
                    $absUrl = $parentUrl . '/' . strtolower($url);
                    $absUrl = str_replace('//', '/', $absUrl);
                    $urls[] = [
                        $absUrl,
                        $slug->language,
                        $default && $slug->default,
                        $deprecated || $slug->deprecated
                    ];
                }
                return $urls;
            }
        }
    }

    /**
     * @param Element $element to check
     * @return bool
     */
    private function isChildOfParent(Element $element)
    {
        $parentId = $element->getParentId();
        if (isset($parentId)) {
            $parent = $this->adapter->loadElement($parentId);
            /* @var ElementVersion $ev */
            foreach ($parent->getVersions() as $ev) {
                if (in_array($element->getId(), $ev->getChildren())) {
                    return true;
                }
            }
            return false;
        } else {
            // If there's no parent, we still return true;
            return true;
        }
    }

    /**
     * Generates and populates the perElementUrls for the given list of
     * elements, including its parents, if not already existent.
     *
     * @param string[] $stack of element ids to process
     * @return array
     */
    protected function generatePerElementUrlsFor(array $stack)
    {
        assert(is_array($this->perElementUrls));
        $perUrlSlugs = [];
        while (!empty($stack)) {
            $elementId = array_pop($stack);
            // skip elements already processed
            if (array_key_exists($elementId, $this->perElementUrls)) {
                continue;
            }

            // Make sure we scan all parents, first.
            $element = $this->adapter->loadElement($elementId);
            assert(isset($element), "missing element $elementId");
            $parentId = $element->getParentId();
            if (isset($parentId) &&
                !BaseStorageAdapter::isValidElementId($parentId)
            ) {
                throw new RuntimeException(
                    "Corruption: element '$elementId' references parent " .
                    "'$parentId' which is not a valid id."
                );
            }
            while (isset($parentId) &&
                !array_key_exists($parentId, $this->perElementUrls)
            ) {
                // Push the element on the stack again and instead process the
                // parent, first.
                array_push($stack, $elementId);
                $elementId = $parentId;

                // Load the parent and replace $element and $parentId.
                $element = $this->adapter->loadElement($parentId);
                if (is_null($element)) {
                    throw new RuntimeException(
                        "Corruption: element '$elementId' references parent " .
                        "'$parentId' which cannot be found"
                    );
                }
                $parentId = $element->getParentId();

                if (!is_null($parentId) &&
                    !BaseStorageAdapter::isValidElementId($parentId)
                ) {
                    throw new RuntimeException(
                        "Corruption: element '$elementId' references parent " .
                        "'$parentId' which is not a valid id."
                    );
                }
            }

            assert(!array_key_exists($elementId, $this->perElementUrls));

            // Collect all slugs.
            $ev = $element->getVersion($element->getNewestVersionNumber());
            $slugs = $ev->getSlugs();

            // If the element has already been deleted, skip its slugs.
            //
            // FIXME: change process should configure this
            if ($ev->getState() == 'deleted') {
                $this->perElementUrls[$elementId] = [];
                continue;
            }

            // As another check, we lookup the parent element and test if the
            // element is in the list of children. It's unreachable, otherwise,
            // and should probably be marked deleted.
            if (!$this->isChildOfParent($element)) {
                // See #5633. Note that this check is sufficient for now, but
                // not thorough: If the parent (or grandparent) has been
                // deleted, but this element has not, it's also unreachable.
                error_log(
                    "WARNING: Element $elementId is not a child of its parent " .
                    "and therefore unreachable, should probably have been " .
                    "marked deleted."
                );
                $this->perElementUrls[$elementId] = [];
                continue;
            }

            if (count($slugs) == 0 && in_array(
                $element->getType(),
                Constants::ELEMENT_TYPES_WITH_SLUGS
            )) {
                error_log(
                    "Warning: page $elementId is unreachable " .
                    "(no slugs defined)"
                );
            }
            $elementUrls = [];
            foreach ($slugs as $slug) {
                $slugUrls = $this->resolveSlug($element->getParentId(), $slug);
                foreach ($slugUrls as list($url, , $default, $deprecated)) {
                    assert(is_string($url));
                    assert(is_bool($default));
                    assert(is_bool($deprecated));
                    $perUrlSlugs[$url][$element->getId()][] = [
                        $slug,
                        $default,
                        $deprecated
                    ];
                }
                $elementUrls = array_merge($elementUrls, $slugUrls);
            }
            $this->perElementUrls[$elementId] = $elementUrls;
        }

        // Store a copy in the cache for more efficient lookups. Note that
        // this forward-map may be incomplete and only cover a part of the
        // elements. But it's relatively trivial to recreate.
        apcu_store('element-url-mapping', $this->perElementUrls);

        return $perUrlSlugs;
    }

    /**
     * @param array $perUrlSlugs info assembled by collectPerElementUrls
     * @return stdClass
     */
    protected function assembleInvertedUrlMapping(
        array $perUrlSlugs
    ) : stdClass {
        $result = new stdClass();
        foreach ($perUrlSlugs as $absUrl => $elementSlugs) {
            $elementIds = array_keys($elementSlugs);
            $activeElementIds = array_filter(
                $elementIds,
                function ($elementId) use ($elementSlugs) {
                    $allDeprecated = true;
                    foreach ($elementSlugs[$elementId] as $entry) {
                        list (,, $deprecated) = $entry;
                        if (!$deprecated) {
                            $allDeprecated = false;
                        }
                    }
                    return !$allDeprecated;
                }
            );

            if (count($activeElementIds) == 0) {
                assert(count($elementIds) >= 1);
                if (count($elementIds) > 1) {
                    error_log("too many elements for url $absUrl");
                    // We cannot uniquely map this deprecated URL to any
                    // element, therefore we punt and ignore the URL.
                    continue;
                }
                // use the first slug for the one deprecated element
                $elementId = reset($elementIds);
                $deprecatedUrlp = true;
            } elseif (count($activeElementIds) == 1) {
                // use the one active element
                $elementId = reset($activeElementIds);
                $deprecatedUrlp = false;
            } else {
                throw new \RuntimeException(
                    "multiple non-deprecated elements defined for: $absUrl: "
                    . implode(', ', $activeElementIds)
                );
            }

            // At this point, we have a unique $elementId that's reachable
            // under $absUrl. Collect matching languages and determine whether
            // it's a default url for the element.
            assert(array_key_exists($elementId, $elementSlugs));
            $matchingDefaultLanguages = [];
            $matchingAliasLanguages = [];
            /* @var Slug $slug */
            foreach ($elementSlugs[$elementId] as list (
                $slug,
                $default,
                $deprecated
            )) {
                if ($default && !$deprecated) {
                    $matchingDefaultLanguages[$slug->language] = true;
                } else {
                    $matchingAliasLanguages[$slug->language] = true;
                }
            }

            if (!$deprecatedUrlp && count($matchingDefaultLanguages) > 0) {
                $languages = array_keys($matchingDefaultLanguages);
                $defaultUrlp = true;
            } else {
                $languages = array_keys($matchingAliasLanguages);
                $defaultUrlp = false;
            }
            $ptr = new UrlPointer(
                $absUrl,
                $languages,
                $defaultUrlp,
                $deprecatedUrlp
            );
            $ptr->setElementId($elementId);
            $result->{$absUrl} = $ptr;
        }
        return $result;
    }

    /**
     * @return void
     */
    public function createUrlMapping()
    {
        $t_start = time();

        // Flush this cache, so generatePerElementUrls will re-process all
        // elements.
        $this->perElementUrls = [];

        $stack = $this->adapter->enumAllElementIds();
        $perUrlSlugs = $this->generatePerElementUrlsFor($stack);
        $this->urlMapping = $this->assembleInvertedUrlMapping($perUrlSlugs);

        $t_diff = time() - $t_start;
        if ($t_diff >= 2) {
            error_log(
                "regenerating url to element mapping took $t_diff seconds."
            );
        }

        $this->storeToAPCU();
        $this->adapter->storeUrlMapping($this->serialize());
    }

    /**
     * @return stdClass|null current url mapping, if any
     */
    public function getUrlMapping()
    {
        return $this->urlMapping;
    }

    /**
     * @param string $elementId to lookup
     * @return UrlPointer[]
     */
    public function getUrlPointersByElement(string $elementId) : array
    {
        assert(BaseStorageAdapter::isValidElementId($elementId));

        assert(is_array($this->perElementUrls));
        if (!array_key_exists($elementId, $this->perElementUrls)) {
            $this->generatePerElementUrlsFor([$elementId]);
        }
        $existingUrls = array_filter(
            $this->perElementUrls[$elementId],
            function ($entry) {
                list ($url,,,) = $entry;
                return property_exists($this->urlMapping, $url);
            }
        );
        return array_filter(array_values(array_map(
            function ($entry) {
                list($url,,,) = $entry;
                if (!property_exists($this->urlMapping, $url)) {
                    throw new RuntimeException(
                        "missing url '$url' in urlMapping !?!"
                    );
                }
                assert(property_exists($this->urlMapping, $url));
                return $this->urlMapping->{$url};
            },
            $existingUrls
        )), function (UrlPointer $urlp) use ($elementId) {
            return $urlp->getElementId() == $elementId;
        });
    }

    /**
     * Check the proposed slugs for the given Element to avoid duplicate URLs.
     *
     * Note that this function is supposed to be used during change
     * validation, where previous changes are not applied, yet. So in general
     * we cannot rely on the IStorageAdapter to provide the data we're
     * supposed to work on.
     *
     * FIXME: let this method use and respect data from previous changes.
     *
     * @param string  $parentId          of the element (may be different when
     *                                   changed within the same transaction)
     * @param [Slug]  $slugs             new set of slugs for the given element
     * @param string  $existingElementId for which to check and change slugs
     * @return array
     */
    public function checkSlugs(
        string $parentId,
        array $slugs,
        string $existingElementId = ''
    ) : array {
        // Ensure we know the URLs of the parent of the given element, as
        // required by the following calls to resolveSlug.
        assert(BaseStorageAdapter::isValidElementId($parentId));
        $this->generatePerElementUrlsFor([$parentId]);

        $result = [];
        /* @var Slug $slug */
        foreach ($slugs as $key => $slug) {
            if (array_key_exists($key, $result)) {
                $result[$key] = ['duplicate', 'duplicate url'];
            }

            if (!$slug->isValid()) {
                $result[$key] = ['invalid', 'invalid characters in url'];
                continue;
            }

            $slugUrls = $this->resolveSlug($parentId, $slug);
            $found = false;
            foreach ($slugUrls as list($url, , ,)) {
                assert(is_string($url));
                if (property_exists($this->urlMapping, $url)) {
                    /* @var UrlPointer $urlp */
                    $urlp = $this->urlMapping->{$url};
                    if ($urlp->getElementId() != $existingElementId
                        && !$urlp->isDeprecated()
                    ) {
                        // Absolute url already points to a different element
                        // and is not deprecated, deny the update.
                        $found = true;
                        break;
                    }
                }
            }

            $result[$key] = $found
                ? ['taken', 'taken by another element']
                : ['good', null];
        }
        return $result;
    }

    /**
     * Updates the URL mapping for just one changed element
     *
     * @param string[] $modElementIds ids of changed elements
     * @return void
     */
    public function updateUrlMappingFor(array $modElementIds)
    {
        // Drop all existing perElementUrls, so these get recreated.
        foreach ($modElementIds as $elementId) {
            assert(BaseStorageAdapter::isValidElementId($elementId));
            unset($this->perElementUrls[$elementId]);
        }

        // Drop all existing UrlPointers pointing to a modified element.
        /* @var UrlPointer $urlp */
        $flippedElementIds = array_flip($modElementIds);
        $urlsToDelete = [];
        foreach (get_object_vars($this->urlMapping) as $url => $urlp) {
            if (array_key_exists($urlp->getElementId(), $flippedElementIds)) {
                $urlsToDelete[] = $url;
            }
        }
        foreach ($urlsToDelete as $url) {
            unset($this->urlMapping->{$url});
        }

        // (Re)generate the perElementUrls for the modified elemnts.
        $perUrlSlugs = $this->generatePerElementUrlsFor($modElementIds);

        // Eliminate elements not modified, but only re-generated.
        foreach ($perUrlSlugs as $url => &$slugs) {
            foreach (array_keys($slugs) as $elementId) {
                if (!array_key_exists($elementId, $flippedElementIds)) {
                    unset($slugs[$elementId]);
                }
            }
            if (empty($slugs)) {
                unset($perUrlSlugs[$url]);
            }
        }

        // Double-check if we're only adjusting modified elements.
        foreach ($perUrlSlugs as $url => $slugs) {
            foreach (array_keys($slugs) as $elementId) {
                assert(array_key_exists($elementId, $flippedElementIds));
            }
        }

        // (Re)assemble the inverted url mapping for just these elements.
        $partialUrlMapping = $this->assembleInvertedUrlMapping($perUrlSlugs);
        $this->urlMapping = (object) array_merge(
            (array) $this->urlMapping,
            (array) $partialUrlMapping
        );
        assert($this->urlMapping instanceof stdClass);
        assert(get_class($this->urlMapping) == 'stdClass');

        $this->storeToAPCU();

        // Invalidate the disk cache variant. Updating it on the fly would
        // take way too long.
        //
        // FIXME: we should probably do it in the background.
        $this->adapter->invalidateUrlMapping();
    }
}
