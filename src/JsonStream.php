<?php

namespace Violet\StreamingJsonEncoder;

use Psr\Http\Message\StreamInterface;

/**
 * Provides a http stream interface for encoding JSON.
 *
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2016-2020 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class JsonStream implements StreamInterface
{
    /** @var BufferJsonEncoder|null The encoder used to produce the JSON stream or null once closed */
    private $encoder;

    /** @var int The current position of the cursor in the JSON stream */
    private $cursor;

    /** @var string|null Buffered output from encoding the value as JSON or null when at EOF */
    private $buffer;

    /**
     * JsonStream constructor.
     * @param BufferJsonEncoder|mixed $value A JSON encoder to use or a value to encode
     */
    public function __construct($value)
    {
        if (!$value instanceof BufferJsonEncoder) {
            $value = (new BufferJsonEncoder($value))
                ->setOptions(JSON_PARTIAL_OUTPUT_ON_ERROR)
                ->setIndent(0);
        }

        $this->encoder = $value;
        $this->rewind();
    }

    /**
     * Returns the JSON encoder used for the JSON stream.
     * @return BufferJsonEncoder The currently used JSON encoder
     * @throws \RuntimeException If the stream has been closed
     */
    private function getEncoder()
    {
        if (!$this->encoder instanceof BufferJsonEncoder) {
            throw new \RuntimeException('Cannot operate on a closed JSON stream');
        }

        return $this->encoder;
    }

    /**
     * Returns the entire JSON stream as a string.
     *
     * Note that this operation performs rewind operation on the JSON encoder. Whether
     * this works or not is dependant on the underlying value being encoded. An empty
     * string is returned if the value cannot be encoded.
     *
     * @return string The entire JSON stream as a string
     */
    public function __toString()
    {
        try {
            $this->rewind();
            return $this->getContents();
        } catch (\Exception $exception) {
            return '';
        }
    }

    /**
     * Frees the JSON encoder from memory and prevents further reading from the JSON stream.
     */
    public function close()
    {
        $this->encoder = null;
    }

    /**
     * Detaches the underlying PHP resource from the stream and returns it.
     * @return null Always returns null as no underlying PHP resource exists
     */
    public function detach()
    {
        return null;
    }

    /**
     * Returns the total size of the JSON stream.
     * @return null Always returns null as the total size cannot be determined
     */
    public function getSize()
    {
        return null;
    }

    /**
     * Returns the current position of the cursor in the JSON stream.
     * @return int Current position of the cursor
     */
    public function tell()
    {
        $this->getEncoder();
        return $this->cursor;
    }

    /**
     * Tells if there are no more bytes to read from the JSON stream.
     * @return bool True if there are no more bytes to read, false if there are
     */
    public function eof()
    {
        return $this->buffer === null;
    }

    /**
     * Tells if the JSON stream is seekable or not.
     * @return bool Always returns true as JSON streams as always seekable
     */
    public function isSeekable()
    {
        return true;
    }

    /**
     * Seeks the given cursor position in the JSON stream.
     *
     * If the provided seek position is less than the current cursor position, a rewind
     * operation is performed on the underlying JSON encoder. Whether this works or not
     * depends on whether the encoded value supports rewinding.
     *
     * Note that since it's not possible to determine the end of the JSON stream without
     * encoding the entire value, it's not possible to set the cursor using SEEK_END
     * constant and doing so will result in an exception.
     *
     * @param int $offset The offset for the cursor
     * @param int $whence Either SEEK_CUR or SEEK_SET to determine new cursor position
     * @throws \RuntimeException If SEEK_END is used to determine the cursor position
     */
    public function seek($offset, $whence = SEEK_SET)
    {
        $position = $this->calculatePosition($offset, $whence);

        if (!isset($this->cursor) || $position < $this->cursor) {
            $this->getEncoder()->rewind();
            $this->buffer = '';
            $this->cursor = 0;
        }

        $this->forward($position);
    }

    /**
     * Calculates new position for the cursor based on offset and whence.
     * @param int $offset The cursor offset
     * @param int $whence One of the SEEK_* constants
     * @return int The new cursor position
     * @throws \RuntimeException If SEEK_END is used to determine the cursor position
     */
    private function calculatePosition($offset, $whence)
    {
        if ($whence === SEEK_CUR) {
            return max(0, $this->cursor + (int) $offset);
        } elseif ($whence === SEEK_SET) {
            return max(0, (int) $offset);
        } elseif ($whence === SEEK_END) {
            throw new \RuntimeException('Cannot set cursor position from the end of a JSON stream');
        }

        throw new \InvalidArgumentException("Invalid cursor relative position '$whence'");
    }

    /**
     * Forwards the JSON stream reading cursor to the given position or to the end of stream.
     * @param int $position The new position of the cursor
     */
    private function forward($position)
    {
        $encoder = $this->getEncoder();

        while ($this->cursor < $position) {
            $length = strlen($this->buffer);

            if ($this->cursor + $length > $position) {
                $this->buffer = substr($this->buffer, $position - $this->cursor);
                $this->cursor = $position;
                break;
            }

            $this->cursor += $length;
            $this->buffer = '';

            if (!$encoder->valid()) {
                $this->buffer = null;
                break;
            }

            $this->buffer = $encoder->current();
            $encoder->next();
        }
    }

    /**
     * Seeks the beginning of the JSON stream.
     *
     * If the encoding has already been started, rewinding the encoder may not work,
     * if the underlying value being encoded does not support rewinding.
     */
    public function rewind()
    {
        $this->seek(0);
    }

    /**
     * Tells if the JSON stream is writable or not.
     * @return bool Always returns false as JSON streams are never writable
     */
    public function isWritable()
    {
        return false;
    }

    /**
     * Writes the given bytes to the JSON stream.
     *
     * As the JSON stream does not represent a writable stream, this method will
     * always throw a runtime exception.
     *
     * @param string $string The bytes to write
     * @return int The number of bytes written
     * @throws \RuntimeException Always throws a runtime exception
     */
    public function write($string)
    {
        throw new \RuntimeException('Cannot write to a JSON stream');
    }

    /**
     * Tells if the JSON stream is readable or not.
     * @return bool Always returns true as JSON streams are always readable
     */
    public function isReadable()
    {
        return true;
    }

    /**
     * Returns the given number of bytes from the JSON stream.
     *
     * The underlying value is encoded into JSON until enough bytes have been
     * generated to fulfill the requested number of bytes. The extraneous bytes are
     * then buffered for the next read from the JSON stream. The stream may return
     * fewer number of bytes if the entire value has been encoded and there are no
     * more bytes to return.
     *
     * @param int $length The number of bytes to return
     * @return string The bytes read from the JSON stream
     */
    public function read($length)
    {
        if ($this->eof()) {
            return '';
        }

        $length = max(0, (int) $length);
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

    /**
     * Returns the remaining bytes from the JSON stream.
     * @return string The remaining bytes from JSON stream
     */
    public function getContents()
    {
        if ($this->eof()) {
            return '';
        }

        $encoder = $this->getEncoder();
        $output = $this->buffer;

        while ($encoder->valid()) {
            $output .= $encoder->current();
            $encoder->next();
        }

        $this->cursor += strlen($output);
        $this->buffer = null;

        return $output;
    }

    /**
     * Returns the metadata related to the JSON stream.
     *
     * No underlying PHP resource exists for the stream, but this method will
     * still return relevant information regarding the stream that is similar
     * to PHP stream meta data.
     *
     * @param string|null $key The key of the value to return
     * @return array|mixed|null The meta data array, a specific value or null if not defined
     */
    public function getMetadata($key = null)
    {
        $meta = [
            'timed_out' => false,
            'blocked' => true,
            'eof' => $this->eof(),
            'unread_bytes' => strlen($this->buffer),
            'stream_type' => get_class($this->encoder),
            'wrapper_type' => 'OBJECT',
            'wrapper_data' => [
                'step' => $this->encoder->key(),
                'errors' => $this->encoder->getErrors(),
            ],
            'mode' => 'r',
            'seekable' => true,
            'uri' => '',
        ];

        return $key === null ? $meta : (isset($meta[$key]) ? $meta[$key] : null);
    }
}
