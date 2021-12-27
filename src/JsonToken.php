<?php

namespace Violet\StreamingJsonEncoder;

/**
 * List of JSON tokens outputted by the encoder.
 *
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2017-2020 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
final class JsonToken
{
    /** Represents the [ character that begins an array */
    const T_LEFT_BRACKET = 1;

    /** Represents the ] character the ends an array */
    const T_RIGHT_BRACKET = 2;

    /** Represents the { character that begins an object */
    const T_LEFT_BRACE = 3;

    /** Represents the } character that ends an object */
    const T_RIGHT_BRACE = 4;

    /** Represents a name in an object name/value pair */
    const T_NAME = 5;

    /** Represent the : character that separates a name and a value */
    const T_COLON = 6;

    /** Represents all values */
    const T_VALUE = 7;

    /** Represents the , character that separates array values and object name/value pairs */
    const T_COMMA = 8;

    /** Represents all whitespace */
    const T_WHITESPACE = 9;
}
