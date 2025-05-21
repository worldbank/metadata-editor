<?php

namespace Swaggest\JsonDiff;

use Swaggest\JsonDiff\JsonPatch\Add;
use Swaggest\JsonDiff\JsonPatch\Remove;
use Swaggest\JsonDiff\JsonPatch\Replace;
use Swaggest\JsonDiff\JsonPatch\Test;

class JsonDiff
{
    /**
     * REARRANGE_ARRAYS is an option to enable arrays rearrangement to minimize the difference.
     */
    const REARRANGE_ARRAYS = 1;

    /**
     * STOP_ON_DIFF is an option to improve performance by stopping comparison when a difference is found.
     */
    const STOP_ON_DIFF = 2;

    /**
     * JSON_URI_FRAGMENT_ID is an option to use URI Fragment Identifier Representation (example: "#/c%25d").
     * If not set default JSON String Representation (example: "/c%d").
     */
    const JSON_URI_FRAGMENT_ID = 4;

    /**
     * SKIP_JSON_PATCH is an option to improve performance by not building JsonPatch for this diff.
     */
    const SKIP_JSON_PATCH = 8;

    /**
     * SKIP_JSON_MERGE_PATCH is an option to improve performance by not building JSON Merge Patch value for this diff.
     */
    const SKIP_JSON_MERGE_PATCH = 16;

    /**
     * TOLERATE_ASSOCIATIVE_ARRAYS is an option to allow associative arrays to mimic JSON objects (not recommended)
     */
    const TOLERATE_ASSOCIATIVE_ARRAYS = 32;

    /**
     * COLLECT_MODIFIED_DIFF is an option to enable getModifiedDiff.
     */
    const COLLECT_MODIFIED_DIFF = 64;

    /**
     * SKIP_TEST_OPS is an option to skip the generation of test operations for JsonPatch.
     */
    const SKIP_TEST_OPS = 128;


    private $options = 0;

    /**
     * @var array Skip included paths
     */
    private $skipPaths = [];

    /**
     * @var mixed Merge patch container
     */
    private $merge;

    private $added;
    private $addedCnt = 0;
    private $addedPaths = array();

    private $removed;
    private $removedCnt = 0;
    private $removedPaths = array();

    private $modifiedOriginal;
    private $modifiedNew;
    private $modifiedCnt = 0;
    private $modifiedPaths = array();
    /**
     * @var ModifiedPathDiff[]
     */
    private $modifiedDiff = array();

    private $path = '';
    private $pathItems = array();

    private $rearranged;

    /** @var JsonPatch */
    private $jsonPatch;

    /** @var JsonHash */
    private $jsonHash;

    /**
     * @param mixed $original
     * @param mixed $new
     * @param int $options
     * @param array $skipPaths
     * @throws Exception
     */
    public function __construct($original, $new, $options = 0, $skipPaths = [])
    {
        if (!($options & self::SKIP_JSON_PATCH)) {
            $this->jsonPatch = new JsonPatch();
        }

        $this->options = $options;

        $this->skipPaths = $skipPaths;

        if ($options & self::JSON_URI_FRAGMENT_ID) {
            $this->path = '#';
        }

        $this->rearranged = $this->process($original, $new);
        if (($new !== null) && $this->merge === null) {
            $this->merge = new \stdClass();
        }
    }

    /**
     * Returns total number of differences
     * @return int
     */
    public function getDiffCnt()
    {
        return $this->addedCnt + $this->modifiedCnt + $this->removedCnt;
    }

    /**
     * Returns removals as partial value of original.
     * @return mixed
     */
    public function getRemoved()
    {
        return $this->removed;
    }

    /**
     * Returns list of `JSON` paths that were removed from original.
     * @return array
     */
    public function getRemovedPaths()
    {
        return $this->removedPaths;
    }

    /**
     * Returns number of removals.
     * @return int
     */
    public function getRemovedCnt()
    {
        return $this->removedCnt;
    }

    /**
     * Returns additions as partial value of new.
     * @return mixed
     */
    public function getAdded()
    {
        return $this->added;
    }

    /**
     * Returns number of additions.
     * @return int
     */
    public function getAddedCnt()
    {
        return $this->addedCnt;
    }

    /**
     * Returns list of `JSON` paths that were added to new.
     * @return array
     */
    public function getAddedPaths()
    {
        return $this->addedPaths;
    }

    /**
     * Returns changes as partial value of original.
     * @return mixed
     */
    public function getModifiedOriginal()
    {
        return $this->modifiedOriginal;
    }

    /**
     * Returns changes as partial value of new.
     * @return mixed
     */
    public function getModifiedNew()
    {
        return $this->modifiedNew;
    }

    /**
     * Returns number of changes.
     * @return int
     */
    public function getModifiedCnt()
    {
        return $this->modifiedCnt;
    }

    /**
     * Returns list of `JSON` paths that were changed from original to new.
     * @return array
     */
    public function getModifiedPaths()
    {
        return $this->modifiedPaths;
    }

    /**
     * Returns list of paths with original and new values.
     * @return ModifiedPathDiff[]
     */
    public function getModifiedDiff()
    {
        return $this->modifiedDiff;
    }

    /**
     * Returns new value, rearranged with original order.
     * @return array|object
     */
    public function getRearranged()
    {
        return $this->rearranged;
    }

    /**
     * Returns JsonPatch of difference
     * @return JsonPatch
     */
    public function getPatch()
    {
        return $this->jsonPatch;
    }

    /**
     * Returns JSON Merge Patch value of difference
     */
    public function getMergePatch()
    {
        return $this->merge;

    }


    /**
     * @param mixed $original
     * @param mixed $new
     * @return array|null|object|\stdClass
     * @throws Exception
     */
    private function process($original, $new)
    {
        $merge = !($this->options & self::SKIP_JSON_MERGE_PATCH);
        
        $addTestOps = !($this->options & self::SKIP_TEST_OPS);

        if ($this->options & self::TOLERATE_ASSOCIATIVE_ARRAYS) {
            if (is_array($original) && !empty($original) && !array_key_exists(0, $original)) {
                $original = (object)$original;
            }

            if (is_array($new) && !empty($new) && !array_key_exists(0, $new)) {
                $new = (object)$new;
            }
        }

        if (
            (!$original instanceof \stdClass && !is_array($original))
            || (!$new instanceof \stdClass && !is_array($new))
        ) {
            if ($original !== $new && !in_array($this->path, $this->skipPaths)) {
                $this->modifiedCnt++;
                if ($this->options & self::STOP_ON_DIFF) {
                    return null;
                }
                $this->modifiedPaths [] = $this->path;

                if ($this->jsonPatch !== null) {
                    if ($addTestOps) {
                        $this->jsonPatch->op(new Test($this->path, $original));
                    }
                    $this->jsonPatch->op(new Replace($this->path, $new));
                }

                JsonPointer::add($this->modifiedOriginal, $this->pathItems, $original);
                JsonPointer::add($this->modifiedNew, $this->pathItems, $new);

                if ($merge) {
                    JsonPointer::add($this->merge, $this->pathItems, $new, JsonPointer::RECURSIVE_KEY_CREATION);
                }

                if ($this->options & self::COLLECT_MODIFIED_DIFF) {
                    $this->modifiedDiff[] = new ModifiedPathDiff($this->path, $original, $new);
                }
            }
            return $new;
        }

        if (
            ($this->options & self::REARRANGE_ARRAYS)
            && is_array($original) && is_array($new)
        ) {
            $new = $this->rearrangeArray($original, $new);
        }

        $newArray = $new instanceof \stdClass ? get_object_vars($new) : $new;
        $newOrdered = array();

        $originalKeys = $original instanceof \stdClass ? get_object_vars($original) : $original;
        $isArray = is_array($original);
        $removedOffset = 0;

        if ($merge && is_array($new) && !is_array($original)) {
            $merge = false;
            JsonPointer::add($this->merge, $this->pathItems, $new);
        } elseif ($merge && $new instanceof \stdClass && !$original instanceof \stdClass) {
            $merge = false;
            JsonPointer::add($this->merge, $this->pathItems, $new);
        }

        $isUriFragment = (bool)($this->options & self::JSON_URI_FRAGMENT_ID);
        $diffCnt = $this->addedCnt + $this->modifiedCnt + $this->removedCnt;
        foreach ($originalKeys as $key => $originalValue) {
            if ($this->options & self::STOP_ON_DIFF) {
                if ($this->modifiedCnt || $this->addedCnt || $this->removedCnt) {
                    return null;
                }
            }

            $path = $this->path;
            $pathItems = $this->pathItems;
            $actualKey = $key;
            if ($isArray && is_int($actualKey)) {
                $actualKey -= $removedOffset;
            }
            $this->path .= '/' . JsonPointer::escapeSegment((string)$actualKey, $isUriFragment);
            $this->pathItems[] = $actualKey;

            if (array_key_exists($key, $newArray)) {
                $newOrdered[$key] = $this->process($originalValue, $newArray[$key]);
                unset($newArray[$key]);
            } else {
                $this->removedCnt++;
                if ($this->options & self::STOP_ON_DIFF) {
                    return null;
                }
                $this->removedPaths [] = $this->path;
                if ($isArray) {
                    $removedOffset++;
                }

                if ($this->jsonPatch !== null) {
                    $this->jsonPatch->op(new Remove($this->path));
                }

                JsonPointer::add($this->removed, $this->pathItems, $originalValue);
                if ($merge) {
                    JsonPointer::add($this->merge, $this->pathItems, null);
                }

            }
            $this->path = $path;
            $this->pathItems = $pathItems;
        }

        if ($merge && $isArray && $this->addedCnt + $this->modifiedCnt + $this->removedCnt > $diffCnt) {
            JsonPointer::add($this->merge, $this->pathItems, $new);
        }

        // additions
        foreach ($newArray as $key => $value) {
            $this->addedCnt++;
            if ($this->options & self::STOP_ON_DIFF) {
                return null;
            }
            $newOrdered[$key] = $value;
            $path = $this->path . '/' . JsonPointer::escapeSegment($key, $isUriFragment);
            $pathItems = $this->pathItems;
            $pathItems[] = $key;
            JsonPointer::add($this->added, $pathItems, $value);
            if ($merge) {
                JsonPointer::add($this->merge, $pathItems, $value);
            }

            $this->addedPaths [] = $path;

            if ($this->jsonPatch !== null) {
                $this->jsonPatch->op(new Add($path, $value));
            }

        }

        return is_array($new) ? $newOrdered : (object)$newOrdered;
    }

    /**
     * @param array $original
     * @param array $new
     * @return array
     */
    private function rearrangeArray(array $original, array $new)
    {
        $first = reset($original);
        if (!$first instanceof \stdClass) {
            return $this->rearrangeEqualItems($original, $new);
        }

        $uniqueKey = false;
        $uniqueIdx = array();

        // find unique key for all items
        /** @var mixed[string]  $f */
        $f = get_object_vars($first);
        foreach ($f as $key => $value) {
            if (is_array($value)) {
                continue;
            }

            $keyIsUnique = true;
            $uniqueIdx = array();
            foreach ($original as $idx => $item) {
                if (!$item instanceof \stdClass) {
                    return $new;
                }
                if (!isset($item->$key)) {
                    $keyIsUnique = false;
                    break;
                }
                $value = $item->$key;
                if (is_array($value)) {
                    $keyIsUnique = false;
                    break;
                }

                if ($value instanceof \stdClass) {
                    if ($this->jsonHash === null) {
                        $this->jsonHash = new JsonHash($this->options);
                    }

                    $value = $this->jsonHash->xorHash($value);
                }

                if (isset($uniqueIdx[$value])) {
                    $keyIsUnique = false;
                    break;
                }
                $uniqueIdx[$value] = $idx;
            }

            if ($keyIsUnique) {
                $uniqueKey = $key;
                break;
            }
        }

        if (!$uniqueKey) {
            return $this->rearrangeEqualItems($original, $new);
        }

        $newRearranged = [];
        $changedItems = [];

        foreach ($new as $item) {
            if (!$item instanceof \stdClass) {
                return $new;
            }

            if (!property_exists($item, $uniqueKey)) {
                return $new;
            }

            $value = $item->$uniqueKey;

            if (is_array($value)) {
                return $new;
            }

            if ($value instanceof \stdClass) {
                if ($this->jsonHash === null) {
                    $this->jsonHash = new JsonHash($this->options);
                }

                $value = $this->jsonHash->xorHash($value);
            }


            if (isset($uniqueIdx[$value])) {
                $idx = $uniqueIdx[$value];
                // Abandon rearrangement if key is not unique in new array.
                if (isset($newRearranged[$idx])) {
                    return $new;
                }

                $newRearranged[$idx] = $item;
            } else {
                $changedItems[] = $item;
            }

            $newIdx[$value] = $item;
        }

        $idx = 0;
        foreach ($changedItems as $item) {
            while (array_key_exists($idx, $newRearranged)) {
                $idx++;
            }
            $newRearranged[$idx] = $item;
        }

        ksort($newRearranged);
        return $newRearranged;
    }

    private function rearrangeEqualItems(array $original, array $new)
    {
        if ($this->jsonHash === null) {
            $this->jsonHash = new JsonHash($this->options);
        }

        $origIdx = [];
        foreach ($original as $i => $item) {
            $hash = $this->jsonHash->xorHash($item);
            $origIdx[$hash][] = $i;
        }

        $newIdx = [];
        foreach ($new as $i => $item) {
            $hash = $this->jsonHash->xorHash($item);
            $newIdx[$i] = $hash;
        }

        $newRearranged = [];
        $changedItems = [];
        foreach ($newIdx as $i => $hash) {
            if (!empty($origIdx[$hash])) {
                $j = array_shift($origIdx[$hash]);

                $newRearranged[$j] = $new[$i];
            } else {
                $changedItems []= $new[$i];
            }

        }

        $idx = 0;
        foreach ($changedItems as $item) {
            while (array_key_exists($idx, $newRearranged)) {
                $idx++;
            }
            $newRearranged[$idx] = $item;
        }

        ksort($newRearranged);

        return $newRearranged;
    }
}
