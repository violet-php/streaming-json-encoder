<?php

namespace Violet\StreamingJsonEncoder;

/**
 * Encodes the given value as JSON and returns encoding result step by step.
 *
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2016-2020 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class BufferJsonEncoder extends AbstractJsonEncoder
{
    /** @var string The encoded JSON in the current step */
    private $buffer;

    /**
     * Encodes the entire value as JSON and returns the value as a string.
     * @return string The encoded JSON
     */
    public function encode()
    {
        $json = [];

        foreach ($this as $string) {
            $json[] = $string;
        }

        return implode($json);
    }

    /** {@inheritdoc} */
    public function rewind()
    {
        $this->buffer = '';

        parent::rewind();
    }

    /** {@inheritdoc} */
    public function next()
    {
        $this->buffer = '';

        parent::next();
    }

    /**
     * Returns the JSON encoded in the current step.
     * @return string|null The currently encoded JSON or null if the state is not valid
     */
    public function current()
    {
        return $this->valid() ? $this->buffer : null;
    }

    /**
     * Writes the JSON output to the step buffer.
     * @param string $string The JSON string to write
     * @param int $token The type of the token
     */
    protected function write($string, $token)
    {
        $this->buffer .= $string;
    }
}
