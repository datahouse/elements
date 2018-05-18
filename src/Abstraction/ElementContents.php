<?php

namespace Datahouse\Elements\Abstraction;

use stdClass;
use RuntimeException;

use Datahouse\Elements\Abstraction\Exceptions\SerDesException;

/**
 * Basically a structure storing per (version, language) contents of an
 * element, with arbitrary fields. Intentionally missing any getters or
 * setters, but sporting publicly accessible fields for simplicity.
 *
 * If you want or need logic, please consider implementing it elsewhere.
 *
 * @package Datahouse\Elements\Abstraction
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
class ElementContents implements ISerializable
{
    const FIELDS = [
        '__subs' => ['type' => 'map']
    ];
    const ALLOW_ARBITRARY_FIELDS = true;
    use SerializationHelper;

    protected $__subs;

    /**
     * ElementContents constructor.
     */
    public function __construct()
    {
        $this->__subs = [];
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
    }

    /**
     * @return stdClass|null serialized sub elements
     */
    public function serializeSubs()
    {
        assert(is_array($this->__subs));
        if (empty($this->__subs)) {
            return null;
        } else {
            $result = new stdClass();
            foreach ($this->__subs as $subName => $subs) {
                assert(is_array($subs) && !empty($subs));
                $arr = array_map(function (ElementContents $sub) : stdClass {
                    return $sub->serialize();
                }, $subs);
                assert(is_array($subs) && !empty($arr));
                $result->{$subName} = $arr;
            }
            return $result;
        }
    }

    /**
     * @param mixed $data to deserialize from
     * @return void
     */
    public function deserializeSubs($data)
    {
        if (isset($data)) {
            $this->__subs = [];
            foreach (get_object_vars($data) as $subName => $subs) {
                // PHP or its yaml parser is plain stupid and cannot properly
                // distinguish between lists and maps.
                if ($subs instanceof stdClass &&
                    empty(get_object_vars($subs))
                ) {
                    $subs = [];
                }
                if (!is_array($subs)) {
                    throw new SerDesException(
                        "deserialize",
                        "expected list of sub elements, got "
                        . get_class($subs) . " instead"
                    );
                }
                // Gracefully handle null values in sub elements. Emit a useful
                // error for type mismatches.
                $deserializedSubs = array_map(
                    function ($subData) use ($subName) {
                        if (!$subData instanceof stdClass) {
                            throw new SerDesException(
                                "deserialize",
                                "expected map for sub element in $subName, "
                                . "got " . get_class($subData) . " instead"
                            );
                        } else {
                            $ec = new ElementContents();
                            $ec->deserialize($subData);
                            return $ec;
                        }
                    },
                    $subs
                );

                $filteredSubs = array_filter($deserializedSubs, function ($v) {
                    return !is_null($v);
                });

                if (empty($filteredSubs)) {
                    error_log(
                        "warning: empty list of sub elements for $subName"
                    );
                } else {
                    $this->__subs[$subName] = $filteredSubs;
                }
            }
            assert(!empty($this->__subs));
        } else {
            $this->__subs = [];
        }
    }

    /**
     * Get all contained elements for a given set name.
     *
     * @param string $subName sub element collection name
     * @return ElementContents[]
     */
    public function getSubs(string $subName) : array
    {
        if (isset($this->__subs) &&
            array_key_exists($subName, $this->__subs)
        ) {
            return $this->__subs[$subName] ?? [];
        } else {
            return [];
        }
    }

    /**
     * Insert or replace a sub element. If the index is out of bounds, a new
     * sub element is appended at the end of the existing list of sub
     * elements.
     *
     * @param string          $subName sub-element collection name
     * @param int             $index   index of the sub to replace
     * @param ElementContents $ec      sub element contents to insert
     * @return void
     */
    public function setSub(string $subName, int $index, ElementContents $ec)
    {
        assert(is_array($this->__subs));
        if (!array_key_exists($subName, $this->__subs)) {
            $this->__subs[$subName] = [];
        }
        if ($index < 0 || $index >= count($this->__subs[$subName])) {
            $this->__subs[$subName][] = $ec;
        } else {
            $this->__subs[$subName][$index] = $ec;
        }
    }

    /**
     * @param string $subName sub-element collection name
     * @param int    $index   index of the sub-element to remove
     * @return ElementContents of the sub-element removed
     */
    public function removeSub(string $subName, int $index) : ElementContents
    {
        assert(is_array($this->__subs));
        assert(array_key_exists($subName, $this->__subs));
        $removed = array_splice($this->__subs[$subName], $index, 1);
        if (empty($this->__subs[$subName])) {
            unset($this->__subs[$subName]);
        }
        assert(count($removed) == 1);
        assert($removed[0] instanceof ElementContents);
        return $removed[0];
    }

    /**
     * @param array $parts of a combined field name
     * @return array
     * @throws RuntimeException
     */
    public function getFieldToEdit(array $parts)
    {
        if (count($parts) % 2 != 1) {
            throw new RuntimeException(
                "cannot handle fieldName: " . implode('-', $parts)
            );
        }
        $parent = null;
        $subName = null;
        $subIndex = null;
        $ec = $this;
        while (count($parts) > 1) {
            $parent = $ec;
            list($subName, $subIndex) = array_splice($parts, 0, 2);
            $subIndex = intval($subIndex);
            $subs = $ec->getSubs($subName);
            $ec = $subs[$subIndex];
        }
        assert(count($parts) == 1);
        $fieldName = $parts[0];
        return [$parent, $subName, $subIndex, $ec, $fieldName];
    }

    /**
     * @param array  $fieldNameParts field specifier, maybe of a sub-element
     *                               field
     * @param string $fieldValue     to set, or an empty string to remove the
     *                               field
     * @return void
     */
    public function setField(array $fieldNameParts, string $fieldValue)
    {
        /* @var ElementContents $parent */
        list ($parent, $subName, $subIndex, $ec, $fieldName)
            = $this->getFieldToEdit($fieldNameParts);
        if (empty($fieldValue)) {
            // FIXME: test removal
            unset($ec->{$fieldName});
        } else {
            $ec->{$fieldName} = $fieldValue;
        }

        // FIXME: these lines of code are awkward and must die some day.
        if (!is_null($parent)) {
            $parent->setSub($subName, $subIndex, $ec);
        }
    }

    /**
     * @param array $fieldNameParts field specifier, maybe of a sub-element
     *                              field
     * @return string contents of the field, or an empty string, if the field
     *                does not exist
     */
    public function getField(array $fieldNameParts) : string
    {
        list (,,, $ec, $fieldName) = $this->getFieldToEdit($fieldNameParts);
        return $ec->{$fieldName} ?? '';
    }

    /**
     * Returns the keys for ordinary fields contained in this ElementContents,
     * excluding all special things, like sub elements or slugs.
     *
     * @return array map of contained fieldNames to values
     */
    public function getOrdinaryFields() : array
    {
        return array_filter(
            array_keys(get_object_vars($this)),
            function ($fieldName) {
                return $fieldName !== '__subs' && $fieldName !== 'slugs';
            }
        );
    }
}
