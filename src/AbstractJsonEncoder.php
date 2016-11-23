<?php

namespace Violet\StreamingJsonEncoder;

use SebastianBergmann\CodeCoverage\RuntimeException;

/**
 * AbstractJsonEncoder.
 *
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2016, Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
abstract class AbstractJsonEncoder implements \Iterator
{
    /** @var \Generator[] */
    private $stack;

    /** @var bool[] */
    private $stackType;

    /** @var bool */
    private $first;

    /** @var int */
    private $options;

    /** @var bool */
    private $newLine;

    /** @var string */
    private $indent;

    /** @var string[] */
    private $errors;

    /** @var int */
    private $line;

    /** @var int */
    private $column;

    /** @var mixed */
    private $initialValue;

    /** @var int|null */
    private $step;

    public function __construct($value)
    {
        $this->initialValue = $value;
        $this->options = 0;
        $this->errors = [];
        $this->indent = '    ';
        $this->step = null;
    }

    public function setOptions($options)
    {
        if ($this->step !== null) {
            throw new RuntimeException('Cannot change encoding options during encoding');
        }

        $this->options = (int) $options;
    }

    public function setIndent($indent)
    {
        if ($this->step !== null) {
            throw new RuntimeException('Cannot change indent during encoding');
        }

        $this->indent = is_int($indent) ? str_repeat(' ', $indent) : (string) $indent;
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function key()
    {
        return $this->step;
    }

    public function valid()
    {
        return $this->step !== null;
    }

    abstract public function current();

    public function rewind()
    {
        $this->stack = [];
        $this->stackType = [];
        $this->errors = [];
        $this->newLine = false;
        $this->first = true;
        $this->line = 1;
        $this->column = 1;
        $this->step = 0;

        $this->processValue($this->initialValue);
    }

    public function next()
    {
        if (!empty($this->stack)) {
            $this->step++;
            $generator = end($this->stack);

            if ($generator->valid()) {
                $this->processStack($generator, end($this->stackType));
                $generator->next();
            } else {
                $this->popStack();
            }
        } else {
            $this->step = null;
        }
    }

    private function processStack(\Generator $generator, $isObject)
    {
        if ($isObject) {
            $key = $generator->key();

            if (!is_int($key) && !is_string($key)) {
                $this->addError('Only string or integer keys are supported');
                return;
            }

            if (!$this->first) {
                $this->outputLine(',', Tokens::COMMA);
            }

            $this->outputJson((string) $key, Tokens::KEY);
            $this->output(':', Tokens::SEPARATOR);

            if ($this->options & JSON_PRETTY_PRINT) {
                $this->output(' ', Tokens::WHITESPACE);
            }
        } elseif (!$this->first) {
            $this->outputLine(',', Tokens::COMMA);
        }

        $this->first = false;
        $this->processValue($generator->current());
    }

    private function processValue($value)
    {
        while ($value instanceof \JsonSerializable) {
            $value = $value->jsonSerialize();
        }

        if (is_array($value) || is_object($value)) {
            $this->pushStack($value);
        } else {
            $this->outputJson($value, Tokens::VALUE);
        }
    }

    private function addError($message)
    {
        $errorMessage = sprintf('Line %d, column %d: %s', $this->line, $this->column, $message);
        $this->errors[] = $errorMessage;

        if ($this->options & JSON_PARTIAL_OUTPUT_ON_ERROR) {
            return;
        }

        throw new EncodingException($errorMessage);
    }

    private function pushStack($iterable)
    {
        $generator = $this->getIterator($iterable);
        $isObject = $this->isObject($iterable, $generator);

        if ($isObject) {
            $this->outputLine('{', Tokens::OPEN_OBJECT);
        } else {
            $this->outputLine('[', Tokens::OPEN_ARRAY);
        }

        $this->first = true;
        $this->stack[] = $generator;
        $this->stackType[] = $isObject;
    }

    private function getIterator($iterable)
    {
        foreach ($iterable as $key => $value) {
            yield $key => $value;
        }
    }

    private function isObject($iterable, \Generator $generator)
    {
        if ($this->options & JSON_FORCE_OBJECT) {
            return true;
        } elseif (is_array($iterable)) {
            return $iterable !== [] && array_keys($iterable) !== range(0, count($iterable) - 1);
        }

        return $generator->valid() && $generator->key() !== 0;
    }

    private function popStack()
    {
        if (!$this->first) {
            $this->newLine = true;
        }

        $this->first = false;
        array_pop($this->stack);

        if (array_pop($this->stackType)) {
            $this->output('}', Tokens::CLOSE_OBJECT);
        } else {
            $this->output(']', Tokens::CLOSE_ARRAY);
        }
    }

    private function outputJson($value, $token)
    {
        $encoded = json_encode($value, $this->options);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->addError(json_last_error_msg());
        }

        $this->output($encoded, $token);
    }

    private function outputLine($string, $token)
    {
        $this->output($string, $token);
        $this->newLine = true;
    }

    private function output($string, $token)
    {
        if ($this->newLine && $this->options & JSON_PRETTY_PRINT) {
            $indent = str_repeat($this->indent, count($this->stack));
            $this->write("\n", Tokens::WHITESPACE);

            if ($indent !== '') {
                $this->write($indent, Tokens::WHITESPACE);
            }

            $this->line += 1;
            $this->column = strlen($indent) + 1;
        }

        $this->newLine = false;
        $this->write($string, $token);
        $this->column += strlen($string);
    }

    abstract protected function write($string, $token);
}
