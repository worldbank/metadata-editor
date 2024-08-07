<?php

namespace Swaggest\JsonDiff\JsonPatch;


abstract class OpPath
{
    const OP = null;

    public $op;
    public $path;

    public function __construct($path = null)
    {
        $this->op = static::OP;
        $this->path = $path;
    }
}