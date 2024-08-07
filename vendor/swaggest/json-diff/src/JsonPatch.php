<?php

namespace Swaggest\JsonDiff;

use Swaggest\JsonDiff\JsonPatch\Add;
use Swaggest\JsonDiff\JsonPatch\Copy;
use Swaggest\JsonDiff\JsonPatch\Move;
use Swaggest\JsonDiff\JsonPatch\OpPath;
use Swaggest\JsonDiff\JsonPatch\OpPathFrom;
use Swaggest\JsonDiff\JsonPatch\OpPathValue;
use Swaggest\JsonDiff\JsonPatch\Remove;
use Swaggest\JsonDiff\JsonPatch\Replace;
use Swaggest\JsonDiff\JsonPatch\Test;

/**
 * JSON Patch is specified in [RFC 6902](http://tools.ietf.org/html/rfc6902) from the IETF.
 *
 * Class JsonPatch
 */
class JsonPatch implements \JsonSerializable
{
    /**
     * Disallow converting empty array to object for key creation
     * @see JsonPointer::STRICT_MODE
     */
    const STRICT_MODE = 2;

    /**
     * Allow associative arrays to mimic JSON objects (not recommended)
     */
    const TOLERATE_ASSOCIATIVE_ARRAYS = 8;


    private $flags = 0;

    /**
     * @param int $options
     * @return $this
     */
    public function setFlags($options)
    {
        $this->flags = $options;
        return $this;
    }

    /** @var OpPath[] */
    private $operations = array();

    /**
     * @param array $data
     * @return JsonPatch
     * @throws Exception
     */
    public static function import(array $data)
    {
        $result = new JsonPatch();
        foreach ($data as $operation) {
            /** @var OpPath|OpPathValue|OpPathFrom|array $operation */
            if (is_array($operation)) {
                $operation = (object)$operation;
            }
            if (!is_object($operation)) {
                throw new Exception('Invalid patch operation - should be a JSON object');
            }

            if (!isset($operation->op)) {
                throw new MissingFieldException('op', $operation);
            }
            if (!isset($operation->path)) {
                throw new MissingFieldException('path', $operation);
            }

            if (!is_string($operation->op)) {
                throw new InvalidFieldTypeException('op', 'string', $operation);
            }
            if (!is_string($operation->path)) {
                throw new InvalidFieldTypeException('path', 'string', $operation);
            }

            $op = null;
            switch ($operation->op) {
                case Add::OP:
                    $op = new Add();
                    break;
                case Copy::OP:
                    $op = new Copy();
                    break;
                case Move::OP:
                    $op = new Move();
                    break;
                case Remove::OP:
                    $op = new Remove();
                    break;
                case Replace::OP:
                    $op = new Replace();
                    break;
                case Test::OP:
                    $op = new Test();
                    break;
                default:
                    throw new UnknownOperationException($operation);
            }
            $op->path = $operation->path;
            if ($op instanceof OpPathValue) {
                if (property_exists($operation, 'value')) {
                    $op->value = $operation->value;
                } else {
                    throw new MissingFieldException('value', $operation);
                }
            } elseif ($op instanceof OpPathFrom) {
                if (!isset($operation->from)) {
                    throw new MissingFieldException('from', $operation);
                } elseif (!is_string($operation->from)) {
                    throw new InvalidFieldTypeException('from', 'string', $operation);
                }
                $op->from = $operation->from;
            }
            $result->operations[] = $op;
        }
        return $result;
    }

    public static function export(JsonPatch $patch)
    {
        $result = array();
        foreach ($patch->operations as $operation) {
            $result[] = (object)(array)$operation;
        }

        return $result;
    }

    public function op(OpPath $op)
    {
        $this->operations[] = $op;
        return $this;
    }

    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return self::export($this);
    }

    /**
     * @param mixed $original
     * @param bool $stopOnError
     * @return Exception[] array of errors
     * @throws Exception
     */
    public function apply(&$original, $stopOnError = true)
    {
        $errors = array();
        foreach ($this->operations as $opIndex => $operation) {
            try {
                // track the current pointer field so we can use it for a potential PathException
                $pointerField = 'path';
                $pathItems = JsonPointer::splitPath($operation->path);
                switch (true) {
                    case $operation instanceof Add:
                        JsonPointer::add($original, $pathItems, $operation->value, $this->flags);
                        break;
                    case $operation instanceof Copy:
                        $pointerField = 'from';
                        $fromItems = JsonPointer::splitPath($operation->from);
                        $value = JsonPointer::get($original, $fromItems);
                        $pointerField = 'path';
                        JsonPointer::add($original, $pathItems, $value, $this->flags);
                        break;
                    case $operation instanceof Move:
                        $pointerField = 'from';
                        $fromItems = JsonPointer::splitPath($operation->from);
                        $value = JsonPointer::get($original, $fromItems);
                        JsonPointer::remove($original, $fromItems, $this->flags);
                        $pointerField = 'path';
                        JsonPointer::add($original, $pathItems, $value, $this->flags);
                        break;
                    case $operation instanceof Remove:
                        JsonPointer::remove($original, $pathItems, $this->flags);
                        break;
                    case $operation instanceof Replace:
                        JsonPointer::get($original, $pathItems);
                        JsonPointer::remove($original, $pathItems, $this->flags);
                        JsonPointer::add($original, $pathItems, $operation->value, $this->flags);
                        break;
                    case $operation instanceof Test:
                        $value = JsonPointer::get($original, $pathItems);
                        $diff = new JsonDiff($operation->value, $value,
                            JsonDiff::STOP_ON_DIFF);
                        if ($diff->getDiffCnt() !== 0) {
                            throw new PatchTestOperationFailedException($operation, $value);
                        }
                        break;
                }
            } catch (JsonPointerException $jsonPointerException) {
                $pathException = new PathException(
                    $jsonPointerException->getMessage(),
                    $operation,
                    $pointerField,
                    $jsonPointerException->getCode()
                );
                $pathException->setOpIndex($opIndex);
                if ($stopOnError) {
                    throw $pathException;
                } else {
                    $errors[] = $pathException;
                }
            } catch (Exception $exception) {
                if ($stopOnError) {
                    throw $exception;
                } else {
                    $errors[] = $exception;
                }
            }
        }
        return $errors;
    }
}
