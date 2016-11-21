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

    /** @var int */
    private $traversedBytes;

    public function __construct()
    {
        $this->indent = '    ';
        $this->newLine = false;
    }

    public function getErrors()
    {
        return $this->encodingErrors;
    }

    private function pushError($message)
    {
        $errorMessage = sprintf('Line %d, column %d: %s', $this->line, $this->column, $message);
        $this->encodingErrors[] = $errorMessage;

        if ($this->options & JSON_PARTIAL_OUTPUT_ON_ERROR) {
            return;
        }

        throw new EncodingException($errorMessage);
    }

    public function encode($value, $options = 0)
    {
        $this->encodingErrors = [];
        $this->options = $options;
        $this->newLine = false;
        $this->line = 1;
        $this->column = 1;

        return $this->processValue($value);
    }

    private function resolveValue($value)
    {
        while ($value instanceof \JsonSerializable) {
            $value = $value->jsonSerialize();
        }

        return $value;
    }

    private function processValue($value)
    {
        $value = $this->resolveValue($value);

        if (is_array($value) || is_object($value)) {
            if (empty($this->generatorStack)) {
                return $this->traverse($value);
            }

            return $this->pushIterable($value);
        }

        return $this->output($this->encodeValue($value));
    }

    private function traverse($traversable)
    {
        $this->traversedBytes = 0;
        $this->generatorStack = [];
        $this->typeStack = [];
        $this->first = true;

        $bytes = $this->pushIterable($traversable);
        $keySeparator = $this->options & JSON_PRETTY_PRINT ? ': ' : ':';

        foreach ($this->traverseStack() as $key => $value) {
            if (!is_int($key) && !is_string($key)) {
                $this->pushError('Only string or integer keys are supported');
                continue;
            }

            if (!$this->first) {
                $bytes += $this->outputLine(',');
            }

            $this->first = false;

            if (end($this->typeStack)) {
                $bytes += $this->output($this->encodeValue((string) $key) . $keySeparator);
            }

            $bytes += $this->processValue($value);
        }

        return $bytes + $this->traversedBytes;
    }

    private function pushIterable($iterable)
    {
        $this->generatorStack[] = $this->iterate($iterable);
        $this->first = true;

        $isObject = $this->isObject($iterable);
        $bytes = $this->outputLine($isObject ? '{' : '[');
        $this->typeStack[] = $isObject;

        return $bytes;
    }

    private function isObject($iterable)
    {
        if ($this->options & JSON_FORCE_OBJECT) {
            return true;
        } elseif (is_array($iterable)) {
            return $iterable !== [] && array_keys($iterable) !== range(0, count($iterable) - 1);
        }

        $generator = end($this->generatorStack);
        return $generator->valid() && $generator->key() !== 0;
    }

    private function popIterable()
    {
        if (!$this->first) {
            $this->outputLine('');
        }

        $this->first = false;
        array_pop($this->generatorStack);
        $object = array_pop($this->typeStack);
        return $this->output($object ? '}' : ']');
    }

    public function traverseStack()
    {
        while ($this->generatorStack) {
            $active = end($this->generatorStack);

            if ($active->valid()) {
                yield $active->key() => $active->current();
                $active->next();
            } else {
                $this->traversedBytes += $this->popIterable();
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
        $bytes = 0;

        if ($this->newLine && $this->options & JSON_PRETTY_PRINT) {
            $bytes += $this->write("\n");
            $this->line++;
            $this->column = 1;
            $bytes += $this->write(str_repeat($this->indent, count($this->typeStack)));
        }

        $this->newLine = false;
        $bytes += $this->write($string);

        return $bytes;
    }

    private function outputLine($string)
    {
        $bytes = $this->output($string);
        $this->newLine = true;

        return $bytes;
    }

    private function write($string)
    {
        echo $string;

        $this->column += strlen($string);
        return strlen($string);
    }

    private function encodeValue($value)
    {
        $encoded = json_encode($value, $this->options);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->pushError(json_last_error_msg());
            return $encoded === false ? json_encode(null) : $encoded;
        }

        return $encoded;
    }
}
