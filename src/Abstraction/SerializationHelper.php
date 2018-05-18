<?php

namespace Datahouse\Elements\Abstraction;

use stdClass;
use ReflectionClass;

use Datahouse\Elements\Abstraction\Exceptions\SerDesException;

/**
 * A helper trait that implements serialization and deserialization routines
 * based on the constant FIELDS of the class using this.
 *
 * @package Datahouse\Elements\Abstraction
 * @author  Markus Wanner (mwa) <markus.wanner@datahouse.ch>
 * @license (c) 2016 by Datahouse AG
 */
trait SerializationHelper
{
    /**
     * @param string $varName to convert
     * @return string variable name converted to upper case and multiple
     *                underlines eliminated.
     */
    public static function toCamelCase(string $varName)
    {
        $result = $varName;
        $idx = strpos($result, '_');
        while ($idx !== false && $idx < strlen($result)) {
            $next = $idx + 1;
            if ($next < strlen($result) && $result[$next] === '_') {
                $result = substr($result, 0, $next) . substr($result, $idx + 2);
            } else {
                $result = ($next > 0 ? (substr($result, 0, $idx)) : '')
                    . strtoupper($result[$next] ?? '')
                    . substr($result, $idx + 2);
            }
            $idx = strpos($result, '_', $idx);
        }
        return $result;
    }

    /**
     * Generic field deserializer for fields for which the derived class
     * doesn't implement a custom deserialize method.
     *
     * @param string $field_name of the field to deserialize
     * @param array  $field_def  for the field to deserialize
     * @param mixed  $value      loaded from storage
     * @return void
     * @throws SerDesException
     */
    public function genericFieldDeserialize(
        string $field_name,
        array $field_def,
        $value
    ) {
        switch ($field_def['type']) {
            case 'value':
                if (is_array($value) || $value instanceof stdClass) {
                    throw new SerDesException(
                        "deserialize",
                        "expected a value for field '$field_name'"
                    );
                }
                break;
            case 'list':
                if (!is_array($value)) {
                    throw new SerDesException(
                        "deserialize",
                        "expected a list for field '$field_name'"
                    );
                } elseif (empty($value)) {
                    // be liberal in what we accept: an empty list can be
                    // treated as a null value.
                    $value = null;
                }
                break;
            case 'map':
                if (!$value instanceof stdClass) {
                    throw new SerDesException(
                        "deserialize",
                        "expected a map for field '$field_name', got a value "
                        . "of type '" . get_class($value) . "'"
                    );
                } elseif (empty(get_object_vars($value))) {
                    // be liberal in what we accept: an empty map can be
                    // treated as a null value.
                    $value = null;
                }
                break;
            default:
                assert(false);
        }
        $this->{$field_name} = $value;
    }

    /**
     * Deserialization and (trivial) validation of data from a storage
     * adapter into this object.
     *
     * @param stdClass $data validated fields loaded
     * @return void
     * @throws SerDesException
     */
    public function genericDeserialize(stdClass $data)
    {
        $rc = new ReflectionClass($this);
        foreach (static::FIELDS as $field_name => $field_def) {
            if (array_key_exists('required', $field_def)
                && $field_def['required']
                && !array_key_exists($field_name, $data)
            ) {
                throw new SerDesException(
                    "deserialize",
                    "missing field '$field_name'"
                );
            }

            $is_implicit = array_key_exists('implicit', $field_def)
                && $field_def['implicit'];

            if ($is_implicit) {
                // no-op, leave as is
            } else {
                $method = static::toCamelCase('deserialize_' . $field_name);
                $value = $data->{$field_name} ?? null;
                if ($rc->hasMethod($method)) {
                    $this->{$method}($value);
                } elseif (array_key_exists($field_name, $data)) {
                    $this->genericFieldDeserialize(
                        $field_name,
                        $field_def,
                        $value
                    );
                } else {
                    $this->{$field_name} = null;
                }
            }
        }

        foreach (get_object_vars($data) as $field_name => $field_value) {
            if (array_key_exists($field_name, static::FIELDS)) {
                continue;
            } elseif (static::ALLOW_ARBITRARY_FIELDS) {
                $this->{$field_name} = $field_value;
            } else {
                // FIXME: rethink this!
                if ($field_name != 'attributes') {
                    throw new SerDesException(
                        "deserialize",
                        "unexpected field to deserialize: '$field_name'"
                    );
                }
            }
        }
    }


    /**
     * Generic field serializer for fields for which the derived class doesn't
     * implement a custom serialize method.
     *
     * @param string $field_name of the field to serialize
     * @param array  $field_def  definition of the field to serialize
     * @return mixed|array|stdClass that's further serializable
     * @throws SerDesException
     */
    public function genericFieldSerialize(string $field_name, array $field_def)
    {
        $val = $this->{$field_name};
        switch ($field_def['type']) {
            case 'value':
                if (is_array($val) || is_object($val)) {
                    throw new SerDesException(
                        "serialize",
                        "expected a value for field '$field_name'"
                    );
                }
                break;
            case 'list':
                if (!is_array($val) || !array_key_exists(0, $val)) {
                    throw new SerDesException(
                        "serialize",
                        "expected a list for field '$field_name'"
                    );
                }
                if (empty($val)) {
                    throw new SerDesException(
                        "serialize",
                        "expected a non-empty list for field '$field_name'"
                    );
                }
                break;
            case 'map':
                if (!$val instanceof stdClass) {
                    throw new SerDesException(
                        "serialize",
                        "expected a map for field '$field_name', got a "
                        . "value of type '" . get_class($val) . "'"
                    );
                }
                if (empty(get_object_vars($val))) {
                    throw new SerDesException(
                        "serialize",
                        "expected a non-empty map for field '$field_name'"
                    );
                }
                break;
            default:
                assert(false);
                return $val;
        }
        return $val;
    }

    /**
     * Serializes this object according to its FIELDS definition.
     *
     * @return stdClass that's further serializable
     * @throws SerDesException
     */
    public function genericSerialize() : stdClass
    {
        $rc = new ReflectionClass($this);
        $obj = new stdClass();
        foreach (static::FIELDS as $field_name => $field_def) {
            if (array_key_exists('required', $field_def)
                && $field_def['required']
                && is_null($this->{$field_name})
            ) {
                throw new SerDesException(
                    "serialize",
                    "missing field '$field_name'"
                );
            }

            $is_implicit = array_key_exists('implicit', $field_def)
                && $field_def['implicit'];

            $method = static::toCamelCase('serialize_' . $field_name);
            if ($rc->hasMethod($method)) {
                $serValue = $this->{$method}();
                if (isset($serValue)) {
                    $obj->{$field_name} = $serValue;
                }
            } elseif (!empty($this->{$field_name}) && !$is_implicit) {
                assert($field_name != '__subs');
                $obj->{$field_name} = $this->genericFieldSerialize(
                    $field_name,
                    $field_def
                );
                assert(!is_null($obj->{$field_name}));
            }
        }

        if (static::ALLOW_ARBITRARY_FIELDS) {
            foreach (get_object_vars($this) as $field_name => $field_value) {
                if (!array_key_exists($field_name, static::FIELDS)) {
                    if (!empty($field_value)) {
                        $obj->{$field_name} = $field_value;
                    }
                }
            }
        }

        return $obj;
    }
}
