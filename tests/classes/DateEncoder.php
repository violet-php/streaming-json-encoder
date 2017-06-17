<?php

namespace Violet\StreamingJsonEncoder\Test;

use Violet\StreamingJsonEncoder\BufferJsonEncoder;
use Violet\StreamingJsonEncoder\JsonToken;

class DateEncoder extends BufferJsonEncoder
{
    protected function resolveValue($value)
    {
        $value = parent::resolveValue($value);

        if ($value instanceof \DateTimeInterface) {
            $value = $value->format('r');
        }

        return $value;
    }

    protected function write($string, $token)
    {
        if ($token === JsonToken::T_VALUE) {
            $stack = $this->getValueStack();
            $value = end($stack);

            if ($value instanceof \DateTimeInterface) {
                $string = sprintf('"<time datetime="%s">%s</time>"', $value->format('c'), substr($string, 1, -1));
            }
        }

        parent::write($string, $token);
    }
}
