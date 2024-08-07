<?php


namespace Swaggest\JsonDiff;


class ModifiedPathDiff
{
    public function __construct($path, $original, $new)
    {
        $this->path = $path;
        $this->original = $original;
        $this->new = $new;
    }

    /**
     * @var string
     */
    public $path;

    /**
     * @var mixed
     */
    public $original;

    /**
     * @var mixed
     */
    public $new;
}