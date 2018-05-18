<?php

namespace Datahouse\Elements\Abstraction;

use stdClass;

use Datahouse\Elements\Abstraction\Exceptions\SerDesException;

/**
 * A relative or absulute part of an URL, aka a slug.
 *
 * @package Datahouse\Elements\Abstraction
 * @author Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016-2017 by Datahouse AG
 */
class Slug implements ISerializable
{
    const FIELDS = [
        'url' => ['type' => 'value', 'required' => true],
        'language' => ['type' => 'value', 'required' => true],
        'default' => ['type' => 'value'],
        'deprecated' => ['type' => 'value']
    ];
    const ALLOW_ARBITRARY_FIELDS = false;
    use SerializationHelper;

    public $url;
    public $language;
    public $default;
    public $deprecated;

    /**
     * Slug constructor.
     */
    public function __construct()
    {
        $this->url = null;
        $this->language = '';
        $this->default = false;
        $this->deprecated = false;
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
            $e->addContext("Slug " . $this->url);
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
            $e->addContext("Slug " . $this->url);
            throw $e;
        }
    }

    /**
     * Check if the url (part) of the slug consists of valid characters for
     * URLs exclusively.
     *
     * @return bool
     */
    public function isValid() : bool
    {
        // an empty url or language is considered invalid
        if (empty($this->url) || empty($this->language)) {
            return false;
        }

        // disallow slashes in escaped form
        if (stripos('%2f', $this->url) !== false) {
            return false;
        }

        // re-assemble an encoded variant
        $rawUrl = implode('/', array_map(function ($v) {
            return rawurldecode($v);
        }, explode('/', $this->url)));

        // Prevent some characters that have a dedicated function in URLs.
        // Pretty permissive (i.e. exclamation mark and asterisks are reserved,
        // but still allowed when encoded), but these are probably not a good
        // idea to include in slugs even when escaped.
        foreach (str_split($rawUrl) as $c) {
            if (ord($c) < 0x20 || $c == '%' || $c == '#' || $c == '?') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param string $urlPart to modify
     * @return string
     */
    public static function incrementSlugPostfixNumber($urlPart)
    {
        $res = preg_match(
            '/(_)(\\d+)$/',
            $urlPart,
            $matches,
            PREG_OFFSET_CAPTURE
        );
        if ($res) {
            list ($postfix, $offset) = $matches[2];
            $number_inc = intval($postfix) + 1;
            return mb_substr($urlPart, 0, $offset) . $number_inc;
        } else {
            return $urlPart . '_2';
        }
    }
}
