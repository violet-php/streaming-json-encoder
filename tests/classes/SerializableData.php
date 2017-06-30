<?php

namespace Violet\StreamingJsonEncoder\Test;

/**
 * SerializableData.
 *
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2016-2017 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class SerializableData implements \JsonSerializable
{
    /** @var mixed */
    private $data;

    /**
     * SerializableData constructor.
     * @param mixed $data
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    public function jsonSerialize()
    {
        return $this->data;
    }
}
