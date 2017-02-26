<?php

namespace Violet\StreamingJsonEncoder;

/**
 * AbstractJsonEncoder.
 *
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2016, Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
abstract class AbstractJsonEncoder implements \Iterator
{
    /** @var \Generator[] Current value stack in encoding */
    private $stack;

    /** @var bool[] True for every object in the stack, false for an array */
    private $stackType;

    /** @var bool Whether the next value is the first value in an array or an object */
    private $first;

    /** @var int The JSON encoding options */
    private $options;

    /** @var bool Whether next token should be preceded by new line or not */
    private $newLine;

    /** @var string Indent to use for indenting JSON output */
    private $indent;

    /** @var string[] Errors that occurred in encoding */
    private $errors;

    /** @var int Number of the current line in output */
    private $line;

    /** @var int Number of the current column in output */
    private $column;

    /** @var mixed The initial value to encode as JSON */
    private $initialValue;

    /** @var int|null The current step of the encoder */
    private $step;

    /**
     * AbstractJsonEncoder constructor.
     * @param mixed $value The value to encode as JSON
     */
    public function __construct($value)
    {
        $this->initialValue = $value;
        $this->options = 0;
        $this->errors = [];
        $this->indent = '    ';
        $this->step = null;
    }

    /**
     * Sets the JSON encoding options
     * @param int $options The JSON encoding options that are used by json_encode
     * @return $this Returns self for call chaining
     */
    public function setOptions($options)
    {
        if ($this->step !== null) {
            throw new \RuntimeException('Cannot change encoding options during encoding');
        }

        $this->options = (int) $options;
        return $this;
    }

    /**
     * Sets the indent for the JSON output.
     * @param string|int $indent A string to use as indent or the number of spaces
     * @return $this Returns self for call chaining
     */
    public function setIndent($indent)
    {
        if ($this->step !== null) {
            throw new \RuntimeException('Cannot change indent during encoding');
        }

        $this->indent = is_int($indent) ? str_repeat(' ', $indent) : (string) $indent;
        return $this;
    }

    /**
     * Returns the list of errors that occurred during the last encoding process.
     * @return string[] List of errors that occurred during encoding
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Initializes the iterator if it has not been initialized yet.
     */
    private function initialize()
    {
        if (!isset($this->stack)) {
            $this->rewind();
        }
    }

    /**
     * Returns the current number of step in the encoder.
     * @return int|null The current step number or null if the current state is not valid
     */
    public function key()
    {
        $this->initialize();

        return $this->step;
    }

    /**
     * Tells if the encoder has a valid current state.
     * @return bool True if the iterator has a valid state, false if not
     */
    public function valid()
    {
        $this->initialize();

        return $this->step !== null;
    }

    /**
     * Returns the current value or state from the encoder.
     * @return mixed The current value or state from the encoder.
     */
    abstract public function current();

    /**
     * Returns the JSON encoding to the beginning.
     */
    public function rewind()
    {
        if ($this->step === 0) {
            return;
        }

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

    /**
     * Iterates the next token or tokens to the output stream.
     */
    public function next()
    {
        $this->initialize();

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

    /**
     * Handles the next value from the generator to be encoded as JSON
     * @param \Generator $generator The generator used to generate the next value
     * @param bool $isObject True if the generator is being handled as an object, false if not
     */
    private function processStack(\Generator $generator, $isObject)
    {
        if ($isObject) {
            $key = $generator->key();

            if (!is_int($key) && !is_string($key)) {
                $this->addError('Only string or integer keys are supported');
                return;
            }

            if (!$this->first) {
                $this->outputLine(',', JsonToken::T_COMMA);
            }

            $this->outputJson((string) $key, JsonToken::T_NAME);
            $this->output(':', JsonToken::T_COLON);

            if ($this->options & JSON_PRETTY_PRINT) {
                $this->output(' ', JsonToken::T_WHITESPACE);
            }
        } elseif (!$this->first) {
            $this->outputLine(',', JsonToken::T_COMMA);
        }

        $this->first = false;
        $this->processValue($generator->current());
    }

    /**
     * Handles the given JSON value appropriately depending on it's type.
     * @param mixed $value The value that should be encoded as JSON
     */
    private function processValue($value)
    {
        while ($value instanceof \JsonSerializable) {
            $value = $value->jsonSerialize();
        }

        if (is_array($value) || is_object($value)) {
            $this->pushStack($value);
        } else {
            $this->outputJson($value, JsonToken::T_VALUE);
        }
    }

    /**
     * Adds an JSON encoding error to the list of errors.
     * @param string $message The error message to add
     * @throws EncodingException If the encoding should not continue due to the error
     */
    private function addError($message)
    {
        $errorMessage = sprintf('Line %d, column %d: %s', $this->line, $this->column, $message);
        $this->errors[] = $errorMessage;

        if ($this->options & JSON_PARTIAL_OUTPUT_ON_ERROR) {
            return;
        }

        throw new EncodingException($errorMessage);
    }

    /**
     * Pushes the given iterable to the value stack.
     * @param object|array $iterable The iterable value to push to the stack
     */
    private function pushStack($iterable)
    {
        $generator = $this->getIterator($iterable);
        $isObject = $this->isObject($iterable, $generator);

        if ($isObject) {
            $this->outputLine('{', JsonToken::T_LEFT_BRACE);
        } else {
            $this->outputLine('[', JsonToken::T_LEFT_BRACKET);
        }

        $this->first = true;
        $this->stack[] = $generator;
        $this->stackType[] = $isObject;
    }

    /**
     * Creates a generator from the given iterable using a foreach loop.
     * @param object|array $iterable The iterable value to iterate
     * @return \Generator The generator using the given iterable
     */
    private function getIterator($iterable)
    {
        foreach ($iterable as $key => $value) {
            yield $key => $value;
        }
    }

    /**
     * Tells if the given iterable should be handled as a JSON object or not.
     * @param object|array $iterable The iterable value to test
     * @param \Generator $generator Generator created from the iterable value
     * @return bool True if the given iterable should be treated as object, false if not
     */
    private function isObject($iterable, \Generator $generator)
    {
        if ($this->options & JSON_FORCE_OBJECT) {
            return true;
        } elseif (is_array($iterable)) {
            return $iterable !== [] && array_keys($iterable) !== range(0, count($iterable) - 1);
        }

        return $generator->valid() && $generator->key() !== 0;
    }

    /**
     * Removes the top element of the value stack.
     */
    private function popStack()
    {
        if (!$this->first) {
            $this->newLine = true;
        }

        $this->first = false;
        array_pop($this->stack);

        if (array_pop($this->stackType)) {
            $this->output('}', JsonToken::T_RIGHT_BRACE);
        } else {
            $this->output(']', JsonToken::T_RIGHT_BRACKET);
        }
    }

    /**
     * Encodes the given value as JSON and passes it to output stream.
     * @param mixed $value The value to output as JSON
     * @param int $token The token type of the value
     */
    private function outputJson($value, $token)
    {
        $encoded = json_encode($value, $this->options);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->addError(json_last_error_msg());
        }

        $this->output($encoded, $token);
    }

    /**
     * Passes the given token to the output stream and ensures the next token is preceded by a newline.
     * @param string $string The token to write to the output stream
     * @param int $token The type of the token
     */
    private function outputLine($string, $token)
    {
        $this->output($string, $token);
        $this->newLine = true;
    }

    /**
     * Passes the given token to the output stream.
     * @param string $string The token to write to the output stream
     * @param int $token The type of the token
     */
    private function output($string, $token)
    {
        if ($this->newLine && $this->options & JSON_PRETTY_PRINT) {
            $indent = str_repeat($this->indent, count($this->stack));
            $this->write("\n", JsonToken::T_WHITESPACE);

            if ($indent !== '') {
                $this->write($indent, JsonToken::T_WHITESPACE);
            }

            $this->line += 1;
            $this->column = strlen($indent) + 1;
        }

        $this->newLine = false;
        $this->write($string, $token);
        $this->column += strlen($string);
    }

    /**
     * Actually handles the writing of the given token to the output stream.
     * @param string $string The given token to write
     * @param int $token The type of the token
     * @return void
     */
    abstract protected function write($string, $token);
}
