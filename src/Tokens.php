<?php

namespace Violet\StreamingJsonEncoder;

/**
 * Tokens.
 *
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2016, Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
final class Tokens
{
    const OPEN_ARRAY = 'token.open_array';
    const CLOSE_ARRAY = 'token.close_array';
    const OPEN_OBJECT = 'token.open_object';
    const CLOSE_OBJECT = 'token.close_object';
    const KEY = 'token.key';
    const SEPARATOR = 'token.separator';
    const VALUE = 'token.value';
    const COMMA = 'token.comma';
    const WHITESPACE = 'token.whitespace';
}
