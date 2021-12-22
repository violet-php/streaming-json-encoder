<?php

namespace Violet\StreamingJsonEncoder;

/**
 * Abstract encoder for encoding JSON iteratively.
 *
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2016-2020 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
abstract class AbstractJsonEncoder implements \Iterator
{
    /** @var \Iterator[] Current value stack in encoding */
    private $stack;

    /** @var bool[] True for every object in the stack, false for an array */
    private $stackType;

    /** @var array Stack of values being encoded */
    private $valueStack;

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
    }

    /**
     * Sets the JSON encoding options.
     * @param int $options The JSON encoding options that are used by json_encode
     * @return $this Returns self for call chaining
     * @throws \RuntimeException If changing encoding options during encoding operation
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
     * @throws \RuntimeException If changing indent during encoding operation
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
     * Returns the current encoding value stack.
     * @return array The current encoding value stack
     */
    protected function getValueStack()
    {
        return $this->valueStack;
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
     * @return int|null The current step number as integer or null if the current state is not valid
     */
    #[\ReturnTypeWillChange]
    public function key()
    {
        $this->initialize();

        return $this->step;
    }

    /**
     * Tells if the encoder has a valid current state.
     * @return bool True if the iterator has a valid state, false if not
     */
    #[\ReturnTypeWillChange]
    public function valid()
    {
        $this->initialize();

        return $this->step !== null;
    }

    /**
     * Returns the current value or state from the encoder.
     * @return mixed The current value or state from the encoder
     */
    #[\ReturnTypeWillChange]
    abstract public function current();

    /**
     * Returns the JSON encoding to the beginning.
     */
    #[\ReturnTypeWillChange]
    public function rewind()
    {
        if ($this->step === 0) {
            return;
        }

        $this->stack = [];
        $this->stackType = [];
        $this->valueStack = [];
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
    #[\ReturnTypeWillChange]
    public function next()
    {
        $this->initialize();

        if (!empty($this->stack)) {
            $this->step++;
            $iterator = end($this->stack);

            if ($iterator->valid()) {
                $this->processStack($iterator, end($this->stackType));
                $iterator->next();
            } else {
                $this->popStack();
            }
        } else {
            $this->step = null;
        }
    }

    /**
     * Handles the next value from the iterator to be encoded as JSON.
     * @param \Iterator $iterator The iterator used to generate the next value
     * @param bool $isObject True if the iterator is being handled as an object, false if not
     */
    private function processStack(\Iterator $iterator, $isObject)
    {
        if ($isObject) {
            if (!$this->processKey($iterator->key())) {
                return;
            }
        } elseif (!$this->first) {
            $this->outputLine(',', JsonToken::T_COMMA);
        }

        $this->first = false;
        $this->processValue($iterator->current());
    }

    /**
     * Handles the given value key into JSON.
     * @param mixed $key The key to process
     * @return bool True if the key is valid, false if not
     */
    private function processKey($key)
    {
        if (!is_int($key) && !is_string($key)) {
            $this->addError('Only string or integer keys are supported');
            return false;
        }

        if (!$this->first) {
            $this->outputLine(',', JsonToken::T_COMMA);
        }

        $this->outputJson((string) $key, JsonToken::T_NAME);
        $this->output(':', JsonToken::T_COLON);

        if ($this->options & JSON_PRETTY_PRINT) {
            $this->output(' ', JsonToken::T_WHITESPACE);
        }

        return true;
    }

    /**
     * Handles the given JSON value appropriately depending on it's type.
     * @param mixed $value The value that should be encoded as JSON
     */
    private function processValue($value)
    {
        $this->valueStack[] = $value;
        $value = $this->resolveValue($value);

        if (is_array($value) || is_object($value)) {
            $this->pushStack($value);
        } else {
            $this->outputJson($value, JsonToken::T_VALUE);
            array_pop($this->valueStack);
        }
    }

    /**
     * Resolves the actual value of any given value that is about to be processed.
     * @param mixed $value The value to resolve
     * @return mixed The resolved value
     */
    protected function resolveValue($value)
    {
        do {
            if ($value instanceof \JsonSerializable) {
                $value = $value->jsonSerialize();
            } elseif ($value instanceof \Closure) {
                $value = $value();
            } else {
                break;
            }
        } while (true);

        return $value;
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

        $this->stack = [];
        $this->step = null;

        throw new EncodingException($errorMessage);
    }

    /**
     * Pushes the given iterable to the value stack.
     * @param object|array $iterable The iterable value to push to the stack
     */
    private function pushStack($iterable)
    {
        $iterator = $this->getIterator($iterable);
        $isObject = $this->isObject($iterable, $iterator);

        if ($isObject) {
            $this->outputLine('{', JsonToken::T_LEFT_BRACE);
        } else {
            $this->outputLine('[', JsonToken::T_LEFT_BRACKET);
        }

        $this->first = true;
        $this->stack[] = $iterator;
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
     * @param \Iterator $iterator An Iterator created from the iterable value
     * @return bool True if the given iterable should be treated as object, false if not
     */
    private function isObject($iterable, \Iterator $iterator)
    {
        if ($this->options & JSON_FORCE_OBJECT) {
            return true;
        }

        if ($iterable instanceof \Traversable) {
            return $iterator->valid() && $iterator->key() !== 0;
        }

        return is_object($iterable) || $this->isAssociative($iterable);
    }

    /**
     * Tells if the given array is an associative array.
     * @param array $array The array to test
     * @return bool True if the array is associative, false if not
     */
    private function isAssociative(array $array)
    {
        if ($array === []) {
            return false;
        }

        $expected = 0;

        foreach ($array as $key => $_) {
            if ($key !== $expected++) {
                return true;
            }
        }

        return false;
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

        array_pop($this->valueStack);
    }

    /**
     * Encodes the given value as JSON and passes it to output stream.
     * @param mixed $value The value to output as JSON
     * @param int $token The token type of the value
     */
    private function outputJson($value, $token)
    {
        $encoded = json_encode($value, $this->options);
        $error = json_last_error();

        if ($error !== JSON_ERROR_NONE) {
            $this->addError(sprintf('%s (%s)', json_last_error_msg(), $this->getJsonErrorName($error)));
        }

        $this->output($encoded, $token);
    }

    /**
     * Returns the name of the JSON error constant.
     * @param int $error The error code to find
     * @return string The name for the error code
     */
    private function getJsonErrorName($error)
    {
        $matches = array_keys(get_defined_constants(), $error, true);
        $prefix = 'JSON_ERROR_';
        $prefixLength = strlen($prefix);
        $name = 'UNKNOWN_ERROR';

        foreach ($matches as $match) {
            if (is_string($match) && strncmp($match, $prefix, $prefixLength) === 0) {
                $name = $match;
                break;
            }
        }

        return $name;
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

            $this->line++;
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
