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
    public const T_LEFT_BRACKET = 1;

    /** Represents the ] character the ends an array */
    public const T_RIGHT_BRACKET = 2;

    /** Represents the { character that begins an object */
    public const T_LEFT_BRACE = 3;

    /** Represents the } character that ends an object */
    public const T_RIGHT_BRACE = 4;

    /** Represents a name in an object name/value pair */
    public const T_NAME = 5;

    /** Represent the : character that separates a name and a value */
    public const T_COLON = 6;

    /** Represents all values */
    public const T_VALUE = 7;

    /** Represents the , character that separates array values and object name/value pairs */
    public const T_COMMA = 8;

    /** Represents all whitespace */
    public const T_WHITESPACE = 9;
}
