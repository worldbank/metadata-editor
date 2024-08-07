<?php

namespace Swaggest\JsonDiff;


class JsonPointer
{
    /**
     * Create intermediate keys if they don't exist
     */
    const RECURSIVE_KEY_CREATION = 1;

    /**
     * Disallow converting empty array to object for key creation
     */
    const STRICT_MODE = 2;

    /**
     * Skip action if holder already has a non-null value at path
     */
    const SKIP_IF_ISSET = 4;

    /**
     * Allow associative arrays to mimic JSON objects (not recommended)
     */
    const TOLERATE_ASSOCIATIVE_ARRAYS = 8;

    /**
     * @param string $key
     * @param bool $isURIFragmentId
     * @return string
     */
    public static function escapeSegment($key, $isURIFragmentId = false)
    {
        if ($isURIFragmentId) {
            return str_replace(array('%7E', '%2F'), array('~0', '~1'), urlencode($key));
        } else {
            return str_replace(array('~', '/'), array('~0', '~1'), $key);
        }
    }

    /**
     * @param string[] $pathItems
     * @param bool $isURIFragmentId
     * @return string
     */
    public static function buildPath(array $pathItems, $isURIFragmentId = false)
    {
        $result = $isURIFragmentId ? '#' : '';
        foreach ($pathItems as $pathItem) {
            $result .= '/' . self::escapeSegment($pathItem, $isURIFragmentId);
        }
        return $result;
    }

    /**
     * @param string $path
     * @return string[]
     * @throws Exception
     */
    public static function splitPath($path)
    {
        $pathItems = explode('/', $path);
        $first = array_shift($pathItems);
        if ($first === '#') {
            return self::splitPathURIFragment($pathItems);
        } else {
            if ($first !== '') {
                throw new JsonPointerException('Path must start with "/": ' . $path);
            }
            return self::splitPathJsonString($pathItems);
        }
    }

    private static function splitPathURIFragment(array $pathItems)
    {
        $result = array();
        foreach ($pathItems as $key) {
            $key = str_replace(array('~1', '~0'), array('/', '~'), urldecode($key));
            $result[] = $key;
        }
        return $result;
    }

    private static function splitPathJsonString(array $pathItems)
    {
        $result = array();
        foreach ($pathItems as $key) {
            $key = str_replace(array('~1', '~0'), array('/', '~'), $key);
            $result[] = $key;
        }
        return $result;
    }

    /**
     * @param mixed $holder
     * @param string[] $pathItems
     * @param mixed $value
     * @param int $flags
     * @throws Exception
     */
    public static function add(&$holder, $pathItems, $value, $flags = self::RECURSIVE_KEY_CREATION)
    {
        $ref = &$holder;
        while (null !== $key = array_shift($pathItems)) {
            if ($ref instanceof \stdClass || is_object($ref)) {
                if (PHP_VERSION_ID < 70100 && '' === $key) {
                    throw new JsonPointerException('Empty property name is not supported by PHP <7.1',
                        Exception::EMPTY_PROPERTY_NAME_UNSUPPORTED);
                }

                if ($flags & self::RECURSIVE_KEY_CREATION) {
                    $ref = &$ref->$key;
                } else {
                    if (!isset($ref->$key) && count($pathItems)) {
                        throw new JsonPointerException('Non-existent path item: ' . $key);
                    } else {
                        $ref = &$ref->$key;
                    }
                }
            } else { // null or array
                $intKey = filter_var($key, FILTER_VALIDATE_INT);
                if ($ref === null && (false === $intKey || $intKey !== 0)) {
                    $key = (string)$key;
                    if ($flags & self::RECURSIVE_KEY_CREATION) {
                        $ref = new \stdClass();
                        $ref = &$ref->{$key};
                    } else {
                        throw new JsonPointerException('Non-existent path item: ' . $key);
                    }
                } elseif ([] === $ref && 0 === ($flags & self::STRICT_MODE) && false === $intKey && '-' !== $key) {
                    $ref = new \stdClass();
                    $ref = &$ref->{$key};
                } else {
                    if ($flags & self::RECURSIVE_KEY_CREATION && $ref === null) $ref = array();
                    if ('-' === $key) {
                        $ref = &$ref[count($ref)];
                    } else {
                        if (false === $intKey) {
                            if (0 === ($flags & self::TOLERATE_ASSOCIATIVE_ARRAYS)) {
                                throw new JsonPointerException('Invalid key for array operation');
                            }
                            $ref = &$ref[$key];
                            continue;
                        }
                        if (is_array($ref) && array_key_exists($key, $ref) && empty($pathItems)) {
                            array_splice($ref, $intKey, 0, array($value));
                        }
                        if (0 === ($flags & self::TOLERATE_ASSOCIATIVE_ARRAYS)) {
                            if ($intKey > count($ref) && 0 === ($flags & self::RECURSIVE_KEY_CREATION)) {
                                throw new JsonPointerException('Index is greater than number of items in array');
                            } elseif ($intKey < 0) {
                                throw new JsonPointerException('Negative index');
                            }
                        }

                        $ref = &$ref[$intKey];
                    }
                }
            }
        }
        if ($ref !== null && $flags & self::SKIP_IF_ISSET) {
            return;
        }
        $ref = $value;
    }

    private static function arrayKeyExists($key, array $a)
    {
        if (array_key_exists($key, $a)) {
            return true;
        }
        $key = (string)$key;
        foreach ($a as $k => $v) {
            if ((string)$k === $key) {
                return true;
            }
        }
        return false;
    }

    private static function arrayGet($key, array $a)
    {
        $key = (string)$key;
        foreach ($a as $k => $v) {
            if ((string)$k === $key) {
                return $v;
            }
        }
        return false;
    }


    /**
     * @param mixed $holder
     * @param string[] $pathItems
     * @return bool|mixed
     * @throws Exception
     */
    public static function get($holder, $pathItems)
    {
        $ref = $holder;
        while (null !== $key = array_shift($pathItems)) {
            if ($ref instanceof \stdClass) {
                if (PHP_VERSION_ID < 70100 && '' === $key) {
                    throw new JsonPointerException('Empty property name is not supported by PHP <7.1',
                        Exception::EMPTY_PROPERTY_NAME_UNSUPPORTED);
                }

                $vars = (array)$ref;
                if (self::arrayKeyExists($key, $vars)) {
                    $ref = self::arrayGet($key, $vars);
                } else {
                    throw new JsonPointerException('Key not found: ' . $key);
                }
            } elseif (is_array($ref)) {
                if (self::arrayKeyExists($key, $ref)) {
                    $ref = $ref[$key];
                } else {
                    throw new JsonPointerException('Key not found: ' . $key);
                }
            } elseif (is_object($ref)) {
                if (isset($ref->$key)) {
                    $ref = $ref->$key;
                } else {
                    throw new JsonPointerException('Key not found: ' . $key);
                }
            } else {
                throw new JsonPointerException('Key not found: ' . $key);
            }
        }
        return $ref;
    }

    /**
     * @param mixed $holder
     * @param string $pointer
     * @return bool|mixed
     * @throws Exception
     */
    public static function getByPointer($holder, $pointer)
    {
        return self::get($holder, self::splitPath($pointer));
    }

    /**
     * @param mixed $holder
     * @param string[] $pathItems
     * @param int $flags
     * @return mixed
     * @throws Exception
     */
    public static function remove(&$holder, $pathItems, $flags = 0)
    {
        $ref = &$holder;
        while (null !== $key = array_shift($pathItems)) {
            $parent = &$ref;
            $refKey = $key;
            if ($ref instanceof \stdClass) {
                if (property_exists($ref, $key)) {
                    $ref = &$ref->$key;
                } else {
                    throw new JsonPointerException('Key not found: ' . $key);
                }
            } elseif (is_object($ref)) {
                if (isset($ref->$key)) {
                    $ref = &$ref->$key;
                } else {
                    throw new JsonPointerException('Key not found: ' . $key);
                }
            } else {
                if (array_key_exists($key, $ref)) {
                    $ref = &$ref[$key];
                } else {
                    throw new JsonPointerException('Key not found: ' . $key);
                }
            }
        }

        if (isset($parent) && isset($refKey)) {
            if ($parent instanceof \stdClass || is_object($parent)) {
                unset($parent->$refKey);
            } else {
                $isAssociative = false;
                if ($flags & self::TOLERATE_ASSOCIATIVE_ARRAYS) {
                    $i = 0;
                    foreach ($parent as $index => $value) {
                        if ($i !== $index) {
                            $isAssociative = true;
                            break;
                        }
                        $i++;
                    }
                }

                unset($parent[$refKey]);
                if (!$isAssociative && (int)$refKey !== count($parent)) {
                    $parent = array_values($parent);
                }
            }
        }

        return $ref;
    }

}
