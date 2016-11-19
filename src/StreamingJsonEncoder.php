<?php

namespace Violet\StreamingJsonEncoder;

/**
 * StreamingJsonEncoder.
 *
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2016, Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class StreamingJsonEncoder
{
    /** @var \Generator[] */
    private $generatorStack;

    /** @var bool[] */
    private $typeStack;

    /** @var bool */
    private $first;

    /** @var int */
    private $options;

    /** @var bool */
    private $newLine;

    /** @var string */
    private $indent;

    /** @var string[] */
    private $encodingErrors;

    /** @var int */
    private $line;

    /** @var int */
    private $column;

    public function __construct()
    {
        $this->indent = '    ';
        $this->newLine = false;
    }

    public function encode($value, $options = 0)
    {
        $this->encodingErrors = [];
        $this->options = $options;
        $this->newLine = false;
        $this->line = 1;
        $this->column = 1;

        while ($value instanceof \JsonSerializable) {
            $value = $value->jsonSerialize();
        }

        if (is_array($value) || is_object($value)) {
            $this->traverse($value);
        } else {
            $this->output($this->encodeValue($value));
        }
    }

    private function traverse($traversable)
    {
        $this->generatorStack = [];
        $this->typeStack = [];
        $this->first = true;

        $this->pushIterable($traversable);
        $keySeparator = $this->options & JSON_PRETTY_PRINT ? ': ' : ':';
        $null = json_encode(null);

        foreach ($this->traverseStack() as $key => $value) {
            if (!$this->first) {
                $this->outputLine(',');
            }

            $this->first = false;

            if (end($this->typeStack)) {
                $encoded = $this->encodeValue((string) $key);

                if ($encoded === $null) {
                    continue;
                }

                $this->output($encoded . $keySeparator);
            }

            while ($value instanceof \JsonSerializable) {
                $value = $value->jsonSerialize();
            }

            if (is_array($value) || is_object($value)) {
                $this->pushIterable($value);
            } else {
                $this->output($this->encodeValue($value));
            }
        }
    }

    private function pushIterable($iterable)
    {
        $this->generatorStack[] = $this->iterate($iterable);
        $this->first = true;

        if ($this->options & JSON_FORCE_OBJECT) {
            $object = true;
        } elseif (is_array($iterable)) {
            $object = array_keys($iterable) !== range(0, count($iterable) - 1);
        } else {
            $generator = end($this->generatorStack);
            $object = $generator->valid() && $generator->key() === 0;
        }

        $this->outputLine($object ? '{' : '[');
        $this->typeStack[] = $object;
    }

    private function popIterable()
    {
        if (!$this->first) {
            $this->outputLine('');
        }

        $this->first = false;
        array_pop($this->generatorStack);
        $object = array_pop($this->typeStack);
        $this->output($object ? '}' : ']');
    }

    public function traverseStack()
    {
        while ($this->generatorStack) {
            $active = end($this->generatorStack);

            if ($active->valid()) {
                yield $active->key() => $active->current();
                $active->next();
            } else {
                $this->popIterable();
            }
        }
    }

    public function iterate($iterable)
    {
        foreach ($iterable as $key => $value) {
            yield $key => $value;
        }
    }

    private function output($string)
    {
        if ($this->newLine && $this->options & JSON_PRETTY_PRINT) {
            $this->write("\n");
            $this->line++;
            $this->column = 1;
            $this->write(str_repeat($this->indent, count($this->typeStack)));
        }

        $this->newLine = false;
        $this->write($string);
    }

    private function outputLine($string)
    {
        $this->output($string);
        $this->newLine = true;
    }

    private function write($string)
    {
        echo $string;
        $this->column += strlen($string);
    }

    private function encodeValue($value)
    {
        $encoded = json_encode($value, $this->options);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->encodingErrors[] =
                sprintf('Line %d, column %d: %s', $this->line, $this->column, json_last_error_msg());

            if ($this->options & JSON_PARTIAL_OUTPUT_ON_ERROR) {
                return $encoded === false ? json_encode(null) : $encoded;
            }

            throw new EncodingException(end($this->encodingErrors));
        }

        return $encoded;
    }
}
