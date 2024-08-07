<?php

namespace Swaggest\JsonDiff;


use Swaggest\JsonDiff\JsonPatch\OpPath;
use Throwable;

class InvalidFieldTypeException extends Exception
{
    /** @var string */
    private $field;
    /** @var string */
    private $expectedType;
    /** @var OpPath|object */
    private $operation;

    /**
     * @param string $field
     * @param string $expectedType
     * @param OpPath|object $operation
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct(
        $field,
        $expectedType,
        $operation,
        $code = 0,
        Throwable $previous = null
    )
    {
        parent::__construct(
            'Invalid field type - "' . $field . '" should be of type: ' . $expectedType,
            $code,
            $previous
        );
        $this->field = $field;
        $this->expectedType = $expectedType;
        $this->operation = $operation;
    }

    /**
     * @return string
     */
    public function getField()
    {
        return $this->field;
    }

    /**
     * @return string
     */
    public function getExpectedType()
    {
        return $this->expectedType;
    }

    /**
     * @return OpPath|object
     */
    public function getOperation()
    {
        return $this->operation;
    }
}
