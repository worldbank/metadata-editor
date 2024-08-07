<?php

namespace Swaggest\JsonDiff;


class JsonValueReplace
{
    private $search;
    private $replace;
    private $pathFilterRegex;
    private $path = '';
    private $pathItems = array();

    public $affectedPaths = array();

    /**
     * JsonReplace constructor.
     * @param mixed $search
     * @param mixed $replace
     * @param null|string $pathFilter Regular expression to check path
     */
    public function __construct($search, $replace, $pathFilter = null)
    {
        $this->search = $search;
        $this->replace = $replace;
        $this->pathFilterRegex = $pathFilter;
    }

    /**
     * Recursively replaces all nodes equal to `search` value with `replace` value.
     * @param mixed $data
     * @return mixed
     * @throws Exception
     */
    public function process($data)
    {
        $check = true;
        if ($this->pathFilterRegex && !preg_match($this->pathFilterRegex, $this->path)) {
            $check = false;
        }

        if (!is_array($data) && !is_object($data)) {
            if ($check && $data === $this->search) {
                $this->affectedPaths[] = $this->path;
                return $this->replace;
            } else {
                return $data;
            }
        }

        /** @var string[] $originalKeys */
        $originalKeys = $data instanceof \stdClass ? get_object_vars($data) : $data;

        if ($check) {
            $diff = new JsonDiff($data, $this->search, JsonDiff::STOP_ON_DIFF);
            if ($diff->getDiffCnt() === 0) {
                $this->affectedPaths[] = $this->path;
                return $this->replace;
            }
        }

        $result = array();

        foreach ($originalKeys as $key => $originalValue) {
            $path = $this->path;
            $pathItems = $this->pathItems;
            $actualKey = $key;
            $this->path .= '/' . JsonPointer::escapeSegment($actualKey);
            $this->pathItems[] = $actualKey;

            $result[$key] = $this->process($originalValue);

            $this->path = $path;
            $this->pathItems = $pathItems;
        }

        return $data instanceof \stdClass ? (object)$result : $result;
    }
}