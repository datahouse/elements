<?php

namespace Datahouse\Elements\Abstraction;

use stdClass;

use Datahouse\Elements\Abstraction\Exceptions\SerDesException;

/**
 * @package Datahouse\Elements\Abstraction
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
class UrlPointer implements ISerializable
{
    const FIELDS = [
        'url' => ['type' => 'value', 'implicit' => true],
        'languages' => ['type' => 'list', 'required' => true],
        'element' => ['type' => 'value', 'required' => false],
        'default' => ['type' => 'value', 'required' => false],
        'deprecated' => ['type' => 'value', 'required' => false]
    ];
    const ALLOW_ARBITRARY_FIELDS = false;
    use SerializationHelper;

    protected $url;
    /* @var string $element the *id* of the element pointed to */
    protected $element;
    protected $languages;
    protected $default;
    protected $deprecated;

    /**
     * @param string    $url        absolute url covered by this pointer
     * @param array     $languages  matching languages for this url
     * @param bool|null $default    flag: is the default url for the element
     * @param bool|null $deprecated flag: pointer is deprecated
     */
    public function __construct(
        string $url,
        array $languages,
        $default = null,
        $deprecated = null
    ) {
        assert(strlen($url) > 0);
        $this->url = $url;
        $this->element = null;
        assert(count($languages) > 0);
        foreach ($languages as $language) {
            assert(strlen($language) > 0);
        }
        $this->languages = $languages;
        // Using null rather than false avoids serialization of the entire
        // field.
        $this->default = $default === true ? true : null;
        $this->deprecated = $deprecated === true ? true : null;
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
            $e->addContext("UrlPointer " . $this->url);
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
            $e->addContext("UrlPointer " . $this->url);
            throw $e;
        }
    }

    /**
     * @param string $elementId the element pointed to, if any
     * @return void
     */
    public function setElementId(string $elementId)
    {
        assert(BaseStorageAdapter::isValidElementId($elementId));
        $this->element = $elementId;
    }

    /**
     * @return string|null the id of the element pointed to or null, if not
     * a direct element pointer.
     */
    public function getElementId()
    {
        return $this->element;
    }

    /**
     * @return string the url pointing to the element
     */
    public function getUrl() : string
    {
        return $this->url;
    }

    /**
     * @return string[] language of this pointer, if any
     */
    public function getLanguages() : array
    {
        return $this->languages;
    }

    /**
     * @return bool whether this is a default pointer for the element
     */
    public function isDefault() : bool
    {
        return $this->default ?? false;
    }

    /**
     * @return bool whether this is a deprecated pointer
     */
    public function isDeprecated() : bool
    {
        return $this->deprecated ?? false;
    }
}
