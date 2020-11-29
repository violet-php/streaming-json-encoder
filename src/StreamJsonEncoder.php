<?php

namespace Violet\StreamingJsonEncoder;

/**
 * Encodes value into JSON and directly echoes it or passes it to a stream.
 *
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2016-2020 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class StreamJsonEncoder extends AbstractJsonEncoder
{
    /** @var callable|null The stream callable */
    private $stream;

    /** @var int Number of bytes written in the current step */
    private $bytes;

    /**
     * StreamJsonEncoder constructor.
     *
     * If a callable is given as the second argument, the callable will be
     * called with two arguments. The first argument is the JSON string to
     * output and the second argument is the type of the token being outputted.
     *
     * If no second parameter is passed to the constructor, then the encoder
     * will simply output the json using an echo statement.
     *
     * @param mixed $value The value to encode as JSON
     * @param callable|null $stream An optional stream to pass the output or null to echo it
     */
    public function __construct($value, callable $stream = null)
    {
        parent::__construct($value);

        $this->stream = $stream;
    }

    /**
     * Encodes the entire value into JSON and returns the number bytes.
     * @return int Returned the number of bytes outputted
     */
    public function encode()
    {
        $total = 0;

        foreach ($this as $bytes) {
            $total += $bytes;
        }

        return $total;
    }

    /** {@inheritdoc} */
    public function rewind()
    {
        $this->bytes = 0;

        parent::rewind();
    }

    /** {@inheritdoc} */
    public function next()
    {
        $this->bytes = 0;

        parent::next();
    }

    /**
     * Returns the bytes written in the last step or null if the encoder is not in valid state.
     * @return int|null The number of bytes written or null when invalid
     */
    public function current()
    {
        return $this->valid() ? $this->bytes : null;
    }

    /**
     * Echoes to given string or passes it to the stream callback.
     * @param string $string The string to output
     * @param int $token The type of the string
     */
    protected function write($string, $token)
    {
        if ($this->stream === null) {
            echo $string;
        } else {
            $callback = $this->stream;
            $callback($string, $token);
        }

        $this->bytes += strlen($string);
    }
}
