<?php

namespace Violet\StreamingJsonEncoder;

use Psr\Http\Message\StreamInterface;
use SebastianBergmann\CodeCoverage\RuntimeException;

/**
 * JsonStream.
 *
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2016, Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class JsonStream implements StreamInterface
{
    private $encoder;
    private $cursor;
    private $buffer;

    public function __construct($value, $options = 0)
    {
        $this->encoder = new BufferJsonEncoder($value);
        $this->encoder->setOptions($options);
        $this->rewind();
    }

    private function getEncoder()
    {
        if (!$this->encoder instanceof BufferJsonEncoder) {
            throw new RuntimeException('Cannot operate on a closed JSON stream');
        }

        return $this->encoder;
    }

    public function __toString()
    {
        try {
            $this->rewind();
            return $this->getContents();
        } catch (\Exception $exception) {
            return '';
        }
    }

    public function close()
    {
        $this->encoder = null;
    }

    public function detach()
    {
        return null;
    }

    public function getSize()
    {
        return null;
    }

    public function tell()
    {
        $this->getEncoder();
        return $this->cursor;
    }

    public function eof()
    {
        return $this->buffer === null;
    }

    public function isSeekable()
    {
        return true;
    }

    public function seek($offset, $whence = SEEK_SET)
    {
        if ($whence === SEEK_CUR) {
            $position = max(0, $this->cursor + (int) $offset);
        } elseif ($whence === SEEK_END) {
            throw new \RuntimeException('Cannot set cursor position from the end of a JSON stream');
        } else {
            $position = max(0, (int) $offset);
        }

        if (!isset($this->cursor) || $position < $this->cursor) {
            $this->getEncoder()->rewind();
            $this->buffer = '';
            $this->cursor = 0;
        }

        while ($this->cursor < $position && !$this->eof()) {
            $this->read(min($position - $this->cursor, 8 * 1024));
        }
    }

    public function rewind()
    {
        $this->seek(0);
    }

    public function isWritable()
    {
        return false;
    }

    public function write($string)
    {
        throw new \RuntimeException('Cannot write to a JSON stream');
    }

    public function isReadable()
    {
        return true;
    }

    public function read($length)
    {
        $length = (int) $length;
        $encoder = $this->getEncoder();

        while (strlen($this->buffer) < $length && $encoder->valid()) {
            $this->buffer .= $encoder->current();
            $encoder->next();
        }

        if (strlen($this->buffer) > $length || $encoder->valid()) {
            $output = substr($this->buffer, 0, $length);
            $this->buffer = substr($this->buffer, $length);
        } else {
            $output = (string) $this->buffer;
            $this->buffer = null;
        }

        $this->cursor += strlen($output);

        return $output;
    }

    public function getContents()
    {
        $output = '';

        while (!$this->eof()) {
            $output .= $this->read(8 * 1024);
        }

        return $output;
    }

    public function getMetadata($key = null)
    {
        return $key === null ? [] : null;
    }
}
