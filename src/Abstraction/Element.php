<?php

namespace Datahouse\Elements\Abstraction;

use stdClass;

use Datahouse\Elements\Abstraction\Exceptions\SerDesException;

/**
 * Base of all content: the Element class.
 *
 * @package Datahouse\Elements\Abstraction
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
class Element implements ISerializable, IStorable
{
    const FIELDS = [
        'id' => ['type' => 'value', 'required' => true, 'implicit' => true],
        'type' => ['type' => 'value', 'required' => true],
        'parent' => ['type' => 'value'],
        'versions' => ['type' => 'map', 'required' => true],
        'permissions' => ['type' => 'map', 'required' => false]
    ];
    const ALLOW_ARBITRARY_FIELDS = false;
    use SerializationHelper;

    private $id;
    private $type;
    private $parent;
    private $versions;
    private $permissions;

    /**
     * Empty Element constructor: every element needs a type and a location
     * in the tree, however, $parent may well be null.
     *
     * @param string|null $id       of the  element itself
     * @param string|null $parentId element id of the parent element
     */
    public function __construct($id = null, $parentId = null)
    {
        assert(is_null($id) || BaseStorageAdapter::isValidElementId($id));
        assert(is_null($parentId) ||
            BaseStorageAdapter::isValidElementId($parentId));

        $this->id = is_null($id) ? BaseStorageAdapter::genRandomId() : $id;
        $this->parent = $parentId;
        $this->versions = [];
        $this->permissions = null;
    }

    /**
     * @return stdClass serialized or at least a serializable representation
     * of the object
     * @throws SerDesException
     */
    public function serialize() : stdClass
    {
        try {
            return $this->genericSerialize();
        } catch (SerDesException $e) {
            $e->addContext("Element " . $this->getId());
            throw $e;
        }
    }

    /**
     * @param stdClass $data to deserialize from
     * @return void
     * @throws SerDesException
     */
    public function deserialize(stdClass $data)
    {
        try {
            $this->genericDeserialize($data);
        } catch (SerDesException $e) {
            $e->addContext("Element " . $this->getId());
            throw $e;
        }
    }

    /**
     * Serializes the versions of this element into a stdClass.
     *
     * @return stdClass
     */
    public function serializeVersions()
    {
        assert(count($this->versions) > 0);
        $obj = new stdClass();
        array_walk(
            $this->versions,
            /** @var ElementVersion $element_version */
            function (&$elementVersion, $vno) use (&$obj) {
                $obj->{$vno} = $elementVersion->serialize();
            }
        );
        return $obj;
    }

    /**
     * Deserializes the versions of this element.
     *
     * @param stdClass $data to deserialized versions from
     * @return void
     */
    public function deserializeVersions(stdClass $data)
    {
        // Load the element's versions
        $this->versions = [];
        array_walk(
            $data,
            function (&$obj, $vno) {
                $ev = new ElementVersion();
                $ev->deserialize($obj);
                $this->versions[intval($vno)] = $ev;
            }
        );
        assert(count($this->versions) > 0);
    }

    /**
     * @inheritdoc
     * @param IStorageAdapter $adapter used to store the element
     * @return void
     */
    public function storeVia(IStorageAdapter $adapter)
    {
        $adapter->storeElement($this);
    }

    /**
     * Returns an element name, not necessarily language specific, to be
     * displayed in the admin area. Falls back to the element id.
     *
     * @return string admin display name of the element
     */
    public function getDisplayName() : string
    {
        $vno = $this->getNewestVersionNumber();
        $ev = $this->getVersion($vno);

        // If there's a 'name' in any of the ElementContents, use it.
        foreach ($ev->getContents() as $lang => $ec) {
            if (property_exists($ec, 'name')) {
                return $ec->name;
            }
        }

        // Fall back to using the 'title' field, if available.
        foreach ($ev->getContents() as $lang => $ec) {
            if (property_exists($ec, 'title')) {
                return $ec->title;
            }
        }

        // As a last resort, use the element id.
        return $this->getId();
    }

    /**
     * Returns the unique identifier of this element.
     *
     * @return string identifier
     */
    public function getId() : string
    {
        return $this->id;
    }

    /**
     * @inheritdoc
     * @return string
     */
    public static function getStorageScope() : string
    {
        return "elements";
    }

    /**
     * Returns all versions of this element.
     *
     * @return array actually a map of version number to ElementVersion
     * objects
     */
    public function getVersions()
    {
        return $this->versions;
    }

    /**
     * Returns a specific version of this element.
     *
     * @param int $version_number to fetch
     * @return ElementVersion|null found for the given number, or null
     */
    public function getVersion($version_number)
    {
        return array_key_exists($version_number, $this->versions)
            ? $this->versions[$version_number] : null;
    }

    /**
     * Ignoring authentication, this simply returns the highest version number
     * of this element.
     *
     * @return int version number
     */
    public function getNewestVersionNumber() : int
    {
        return $this->versions ? max(array_keys($this->versions)) : 0;
    }

    /**
     * Ignoring authentication, this simply returns the highest version number
     * of this element for each language.
     *
     * @return array map of language to version number
     */
    public function getNewestVersionNumberByLanguage() : array
    {
        $result = [];
        if ($this->versions) {
            /* @var ElementVersion $v */
            foreach ($this->versions as $vno => $v) {
                foreach (array_keys($v->getLanguages()) as $language) {
                    if (!array_key_exists($language, $result) ||
                        $vno >= $result[$language]
                    ) {
                        $result[$language] = $vno;
                    }
                }
            }
        }
        return $result;
    }

    /**
     * Returns permissions defined on just this element itself. Does not take
     * parent's permissions into account. Note that elements itself does not
     * care about any of the contents of the contained permissions object,
     * only the IAuthorizationHandler does. And an application may well
     * provide a custom implementation of that one.
     *
     * @return stdClass permissions set on this specific element
     */
    public function getPermissions() : stdClass
    {
        return $this->permissions ?? new stdClass();
    }

    /**
     * Get the parent of this element, if applicable.
     *
     * @return string|null element id of the parent
     */
    public function getParentId()
    {
        return $this->parent;
    }

    /**
     * @param string $parentId new parent to set
     * @return void
     */
    public function setParentId(string $parentId)
    {
        assert(BaseStorageAdapter::isValidElementId($parentId));
        $this->parent = $parentId;
    }

    /**
     * Get the type of this element
     *
     * @return string type of the element
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * setType
     *
     * @param string $type type of element
     * @return void
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * Adds a new version of an element.
     *
     * @param int            $vno         version number
     * @param ElementVersion $new_version to add
     * @return void
     */
    public function addVersion($vno, ElementVersion $new_version)
    {
        assert(is_int($vno));
        assert(!array_key_exists($vno, $this->versions));
        $this->versions[$vno] = $new_version;
    }

    /**
     * Removes an (unreachable) element version.
     *
     * @param int $vno version number to remove
     * @return void
     */
    public function removeVersion($vno)
    {
        unset($this->versions[$vno]);
    }
}
