<?php

namespace Swaggest\JsonDiff;


class JsonMergePatch
{
    public static function apply(&$original, $patch)
    {
        if (null === $patch) {
            $original = null;
        } elseif (is_object($patch)) {
            foreach (get_object_vars($patch) as $key => $val) {
                if ($val === null) {
                    unset($original->$key);
                } else {
                    if (!is_object($original)) {
                        $original = new \stdClass();
                    }
                    $branch = &$original->$key;
                    if (null === $branch) {
                        $branch = new \stdClass();
                    }
                    self::apply($branch, $val);
                }
            }
        } else {
            $original = $patch;
        }
    }
}