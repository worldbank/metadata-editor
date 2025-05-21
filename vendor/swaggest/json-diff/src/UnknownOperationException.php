<?php

namespace Swaggest\JsonDiff;


use Swaggest\JsonDiff\JsonPatch\OpPath;
use Throwable;

class UnknownOperationException extends Exception
{
    /** @var OpPath|object */
    private $operation;

    /**
     * @param OpPath|object $operation
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct(
        $operation,
        $code = 0,
        ?Throwable $previous = null
    )
    {
        // @phpstan-ignore-next-line MissingFieldOperation will be thrown if op is not set
        parent::__construct('Unknown "op": ' . $operation->op, $code, $previous);
        $this->operation = $operation;
    }

    /**
     * @return OpPath|object
     */
    public function getOperation()
    {
        return $this->operation;
    }
}
