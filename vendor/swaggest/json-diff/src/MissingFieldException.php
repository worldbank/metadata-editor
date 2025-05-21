<?php

namespace Swaggest\JsonDiff;

use Swaggest\JsonDiff\JsonPatch\OpPath;
use Throwable;

class MissingFieldException extends Exception
{
    /** @var string */
    private $missingField;
    /** @var OpPath|object */
    private $operation;

    /**
     * @param string $missingField
     * @param OpPath|object $operation
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct(
        $missingField,
        $operation,
        $code = 0,
        ?Throwable $previous = null
    )
    {
        parent::__construct('Missing "' . $missingField . '" in operation data', $code, $previous);
        $this->missingField = $missingField;
        $this->operation = $operation;
    }

    /**
     * @return string
     */
    public function getMissingField()
    {
        return $this->missingField;
    }

    /**
     * @return OpPath|object
     */
    public function getOperation()
    {
        return $this->operation;
    }
}
