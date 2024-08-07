<?php

namespace Swaggest\JsonDiff\JsonPatch;

abstract class OpPathFrom extends OpPath
{
    public $from;

    public function __construct($path = null, $from = null)
    {
        parent::__construct($path);
        $this->from = $from;
    }
}