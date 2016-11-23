<?php

namespace Violet\StreamingJsonEncoder;

/**
 * StreamJsonEncoder.
 *
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2016, Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class StreamJsonEncoder extends AbstractJsonEncoder
{
    /** @var callable|null */
    private $stream;

    /** @var int */
    private $bytes;

    public function __construct($value, callable $stream = null)
    {
        parent::__construct($value);

        $this->stream = $stream;
    }

    public function encode()
    {
        $total = 0;

        foreach ($this as $bytes) {
            $total += $bytes;
        }

        return $total;
    }

    public function rewind()
    {
        $this->bytes = 0;

        parent::rewind();
    }

    public function next()
    {
        $this->bytes = 0;

        parent::next();
    }

    public function current()
    {
        return $this->valid() ? $this->bytes : null;
    }

    public function write($string, $token)
    {
        if ($this->stream === null) {
            echo $string;
        } else {
            call_user_func($this->stream, $string, $token);
        }

        $this->bytes += strlen($string);
    }
}
