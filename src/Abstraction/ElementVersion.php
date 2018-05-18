<?php

namespace Datahouse\Elements\Abstraction;

use stdClass;
use RuntimeException;

use Datahouse\Elements\Abstraction\Exceptions\SerDesException;

/**
 * A single version of an element at a point in time, possibly including
 * contents for multiple languages: the ElementVersion.
 *
 * @package Datahouse\Elements\Abstraction
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
class ElementVersion implements ISerializable
{
    const FIELDS = [
        // all
        'state' => ['type' => 'value', 'required' => true],
        // should be reduced to only one value
        'languages' => ['type' => 'map', 'required' => true],
        // indep-only
        'children' => ['type' => 'list'],
        // or references, mostly indep, but could be either way...
        'links' => ['type' => 'map'],
        // mostly indep, but could be either way...
        'definition' => ['type' => 'value'],
        // indep (has language information embedded)
        'slugs' => ['type' => 'list'],
        // lang-dep, per-version info
        'reachable' => ['type' => 'value', 'implicit' => true]
    ];
    const ALLOW_ARBITRARY_FIELDS = false;
    use SerializationHelper;

    private $state;
    private $languages;
    private $children;
    private $links;
    private $definition;
    private $slugs;
    private $reachable;

    /**
     * ElementVersion constructor.
     */
    public function __construct()
    {
        $this->languages = [];
        $this->definition = null;
        $this->slugs = [];
        $this->reachable = true;
    }

    /**
     * @return stdClass serialized or at least a serializable representation
     * of the object
     * @throws SerDesException
     */
    public function serialize() : stdClass
    {
        return $this->genericSerialize();
    }

    /**
     * @param stdClass $data to deserialize from
     * @return void
     * @throws SerDesException
     */
    public function deserialize(stdClass $data)
    {
        $this->genericDeserialize($data);
        $this->reachable = true;
    }

    /**
     * @return stdClass serialized languages of the ElementVersion
     */
    public function serializeLanguages()
    {
        assert(is_array($this->languages));
        assert(count($this->languages) > 0);
        $obj = new stdClass();
        foreach ($this->languages as $lang => $ec) {
            try {
                assert($ec instanceof ElementContents);
                $obj->{$lang} = $ec->serialize();
            } catch (SerDesException $e) {
                $e->addContext("language $lang");
                throw $e;
            }
        }
        return $obj;
    }

    /**
     * @param mixed $data to deserialize from
     * @return void
     */
    public function deserializeLanguages(stdClass $data)
    {
        if (isset($data)) {
            // Load the element version's languages
            $this->languages = [];
            array_walk(
                $data,
                function (&$obj, $lang) {
                    $ec = new ElementContents();
                    try {
                        $ec->deserialize($obj);
                    } catch (SerDesException $e) {
                        $e->addContext("language $lang");
                        throw $e;
                    }
                    $this->languages[strval($lang)] = $ec;
                }
            );
        } else {
            $this->languages = null;
        }
    }

    /**
     * @return stdClass[]|null array of slugs serialized into a stdClass
     */
    public function serializeSlugs()
    {
        assert(is_array($this->slugs));
        if (empty($this->slugs)) {
            return null;
        } else {
            return array_map(function (Slug $slug) : stdClass {
                return $slug->serialize();
            }, $this->slugs);
        }
    }

    /**
     * @param mixed $data to deserialize
     * @return void
     */
    public function deserializeSlugs($data)
    {
        // PHP or its yaml parser is plain stupid and cannot properly
        // distinguish between lists and maps.
        if ($data instanceof stdClass && empty(get_object_vars($data))) {
            $data = [];
        }
        if (isset($data)) {
            assert(is_array($data));
            $this->slugs = array_map(function (stdClass $data) : Slug {
                $slug = new Slug();
                $slug->deserialize($data);
                return $slug;
            }, $data);
        } else {
            $this->slugs = [];
        }
        assert(is_array($this->slugs));
    }

    /**
     * @return ElementVersion a copy without any contents
     */
    public function deepCopyWithoutContents()
    {
        $copy = unserialize(serialize($this));
        $copy->languages = [];
        return $copy;
    }

    /**
     * Tries to get the most appropriate label to display for a given language.
     *
     * @param string $language to use
     * @return string the label to use
     * @throws RuntimeException
     */
    public function getMenuLabel(string $language)
    {
        $ec = $this->getContentsFor($language);
        $selectorFunc = function (ElementContents $ec) {
            if (property_exists($ec, 'menuLabel')) {
                return $ec->{'menuLabel'};
            } elseif (property_exists($ec, 'title')) {
                return $ec->{'title'};
            } elseif (property_exists($ec, 'name')) {
                return $ec->{'name'};
            } else {
                return false;
            }
        };
        if (isset($ec)) {
            $label = $selectorFunc($ec);
            if ($label !== false) {
                return $label;
            }
        } else {
            foreach ($this->getContents() as $language => $ec) {
                $label = $selectorFunc($ec);
                if ($label !== false) {
                    return $label;
                }
            }
        }

        throw new RuntimeException("Neither a title nor a menuLabel defined");
    }

    /**
     * Tries to get the most appropriate page title to display for a given
     * language.
     *
     * @param string $language to use
     * @return string the label to use
     * @throws RuntimeException
     */
    public function getPageTitle(string $language)
    {
        $ec = $this->getContentsFor($language);
        $selectorFunc = function (ElementContents $ec) {
            if (property_exists($ec, 'pageTitle')) {
                return $ec->{'pageTitle'};
            } elseif (property_exists($ec, 'title')) {
                return $ec->{'title'};
            } elseif (property_exists($ec, 'name')) {
                return $ec->{'name'};
            } else {
                return false;
            }
        };
        if (isset($ec)) {
            $label = $selectorFunc($ec);
            if ($label !== false) {
                return $label;
            }
        } else {
            foreach ($this->getContents() as $language => $ec) {
                $label = $selectorFunc($ec);
                if ($label !== false) {
                    return $label;
                }
            }
        }

        throw new RuntimeException("Neither a title nor a pageTitle defined");
    }

    /**
     * @param string          $lang     of the contents to add
     * @param ElementContents $contents to add
     * @return void
     */
    public function addLanguage(string $lang, ElementContents $contents)
    {
        assert(BaseStorageAdapter::isValidLanguage($lang));
        assert(!array_key_exists($lang, $this->languages));
        $this->languages[$lang] = $contents;
    }

    /**
     * @param string $lang to drop from the ElementVersion
     * @return void
     */
    public function removeLanguage(string $lang)
    {
        unset($this->languages[$lang]);
    }

    /**
     * Gets contents for all available languages of this element version.
     *
     * @return array or rather a map of all contained languages and their
     * ElementContents
     */
    public function getLanguages()
    {
        return $this->languages;
    }

    /**
     * Get the element's content given a specific language, if available.
     *
     * @param string $lang to fetch
     * @return ElementContents|null for the given language
     */
    public function getContentsFor($lang)
    {
        return array_key_exists($lang, $this->languages)
            ? $this->languages[$lang] : null;
    }

    /**
     * get all contents (in all languages) for a specific version
     *
     * @return array
     */
    public function getContents()
    {
        assert(isset($this->languages));
        return $this->languages;
    }

    /**
     * set all content for a specific element version
     *
     * @param array $content the content to be set
     * @return void
     */
    public function setContents(array $content)
    {
        $this->languages = $content;
    }

    /**
     * Set the element's content given a specific language.
     *
     * @param ElementContents $content to set
     * @param string          $lang    to set
     * @return void
     */
    public function setContentsFor($content, $lang)
    {
        $this->languages[$lang] = $content;
    }

    /**
     * Get the state of this element version.
     *
     * @return string state of this ElementVersion
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * @param string $newState to set
     * @return void
     */
    public function setState($newState)
    {
        $this->state = $newState;
    }

    /**
     * Lists element ids of all children of this element.
     *
     * @return array of children in the order given, may be empty
     */
    public function getChildren() : array
    {
        return isset($this->children) ? $this->children : [];
    }

    /**
     * @param string      $child        elementId of the child to be added
     * @param string|null $insertBefore insertion point
     * @return void
     */
    public function insertChild(string $child, $insertBefore)
    {
        if (!isset($this->children)) {
            $this->children = [];
        }
        if (is_null($insertBefore)) {
            array_push($this->children, $child);
        } else {
            $newIndex = array_search($insertBefore, $this->children);
            if ($newIndex === false) {
                // Fall-back to appending at the end.
                array_push($this->children, $child);
            } else {
                assert($newIndex >= 0);
                assert($newIndex <= count($this->children));
                array_splice($this->children, $newIndex, 0, [$child]);
            }
        }
    }

    /**
     * @param string $childId id of the child to remove
     * @return void
     */
    public function removeChild(string $childId)
    {
        assert(isset($this->children));

        $idx = array_search($childId, $this->children);
        assert($idx !== false);
        $removed = array_splice($this->children, $idx, 1);
        assert($removed == [$childId]);
    }

    /**
     * Get the element id of the link given by name, if provided.
     *
     * @param string $linkName to fetch
     * @return string id of the linked element id, or empty string
     */
    public function getLink(string $linkName) : string
    {
        return isset($this->links->{$linkName})
            ? $this->links->{$linkName} : '';
    }

    /**
     * Gets all links of the element.
     *
     * @return array a map of linkName to element id.
     */
    public function getLinks() : array
    {
        return $this->links ? get_object_vars($this->links) : [];
    }

    /**
     * Sets or unsets the given link.
     *
     * @param string $linkName     name of the link to change
     * @param string $refElementId target element id or an empty string to
     *                             unset the link.
     * @return void
     */
    public function setLink(string $linkName, string $refElementId)
    {
        if (empty($linkName)) {
            unset($this->links->{$linkName});
            // FIXME: maybe delete the 'links' property or set to null, if
            // there's no link defined, anymore.
        } else {
            if (!property_exists($this, 'links') || is_null($this->links)) {
                $this->links = new stdClass();
            }
            $this->links->{$linkName} = $refElementId;
        }
    }

    /**
     * Get the element definition for this element, if defined.
     *
     * @return string|null element definition id
     */
    public function getDefinition()
    {
        return $this->definition;
    }

    /**
     * set new template of on version of element
     *
     * @param string $definition id of the element definition to assign
     * @return void
     */
    public function setDefinition($definition)
    {
        $this->definition = $definition;
    }

    /**
     * @return Slug[] of slugs for this element version
     */
    public function getSlugs() : array
    {
        return $this->slugs;
    }

    /**
     * @param Slug[] $slugs list of slugs to set
     * @return void
     */
    public function setSlugs(array $slugs)
    {
        foreach ($slugs as $slug) {
            assert($slug instanceof Slug);
        }
        $this->slugs = $slugs;
    }

    /**
     * @return bool whether or not this version is still reachable.
     */
    public function isReachable() : bool
    {
        return $this->reachable;
    }

    /**
     * Marks the ElementVersion as no longer reachable.
     *
     * @return void
     */
    public function setUnreachable()
    {
        $this->reachable = false;
    }
}
