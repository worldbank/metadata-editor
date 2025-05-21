<?php

namespace Swaggest\JsonDiff;


use Swaggest\JsonDiff\JsonPatch\Test;
use Throwable;

class PatchTestOperationFailedException extends Exception
{
    /** @var Test */
    private $operation;
    /** @var mixed */
    private $actualValue;

    /**
     * @param Test $operation
     * @param mixed $actualValue
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct(
        $operation,
        $actualValue,
        $code = 0,
        ?Throwable $previous = null
    )
    {
        parent::__construct('Test operation ' . json_encode($operation, JSON_UNESCAPED_SLASHES)
            . ' failed: ' . json_encode($actualValue), $code, $previous);
        $this->operation = $operation;
        $this->actualValue = $actualValue;
    }

    /**
     * @return Test
     */
    public function getOperation()
    {
        return $this->operation;
    }

    /**
     * @return mixed
     */
    public function getActualValue()
    {
        return $this->actualValue;
    }
}
