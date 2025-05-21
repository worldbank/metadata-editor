<?php

namespace Swaggest\JsonDiff;

class JsonHash
{
    private $options = 0;

    public function __construct($options = 0)
    {
        $this->options = $options;
    }

    /**
     * @param mixed $data
     * @param string $path
     * @return string
     */
    public function xorHash($data, $path = '')
    {
        $xorHash = '';

        if (!$data instanceof \stdClass && !is_array($data)) {
            $s = $path . (string)$data;
            if (strlen($xorHash) < strlen($s)) {
                $xorHash = str_pad($xorHash, strlen($s));
            }
            $xorHash ^= $s;

            return $xorHash;
        }

        if ($this->options & JsonDiff::TOLERATE_ASSOCIATIVE_ARRAYS) {
            if (is_array($data) && !empty($data) && !array_key_exists(0, $data)) {
                $data = (object)$data;
            }
        }

        if (is_array($data)) {
            if ($this->options & JsonDiff::REARRANGE_ARRAYS) {
                foreach ($data as $key => $item) {
                    $itemPath = $path . '/' . $key;
                    $itemHash = $path . $this->xorHash($item, $itemPath);
                    if (strlen($xorHash) < strlen($itemHash)) {
                        $xorHash = str_pad($xorHash, strlen($itemHash));
                    }
                    $xorHash ^= $itemHash;
                }
            } else {
                foreach ($data as $key => $item) {
                    $itemPath = $path . '/' . $key;
                    $itemHash = md5($itemPath . $this->xorHash($item, $itemPath), true);
                    if (strlen($xorHash) < strlen($itemHash)) {
                        $xorHash = str_pad($xorHash, strlen($itemHash));
                    }
                    $xorHash ^= $itemHash;
                }
            }

            return $xorHash;
        }

        $dataKeys = get_object_vars($data);
        foreach ($dataKeys as $key => $value) {
            $propertyPath = $path . '/' .
                JsonPointer::escapeSegment($key, (bool)($this->options & JsonDiff::JSON_URI_FRAGMENT_ID));
            $propertyHash = $propertyPath . md5($key, true) . $this->xorHash($value, $propertyPath);
            if (strlen($xorHash) < strlen($propertyHash)) {
                $xorHash = str_pad($xorHash, strlen($propertyHash));
            }
            $xorHash ^= $propertyHash;
        }

        return $xorHash;
    }
}