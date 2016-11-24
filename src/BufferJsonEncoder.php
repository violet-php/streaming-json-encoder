<?php

namespace Violet\StreamingJsonEncoder;

/**
 * BufferJsonEncoder.
 *
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2016, Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class BufferJsonEncoder extends AbstractJsonEncoder
{
    /** @var string */
    private $buffer;

    public function encode()
    {
        $json = [];

        foreach ($this as $string) {
            $json[] = $string;
        }

        return implode($json);
    }

    public function rewind()
    {
        $this->buffer = '';

        parent::rewind();
    }

    public function next()
    {
        $this->buffer = '';

        parent::next();
    }

    public function current()
    {
        return $this->valid() ? $this->buffer : null;
    }

    public function write($string, $token)
    {
        $this->buffer .= $string;
    }
}
