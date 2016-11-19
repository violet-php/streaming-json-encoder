<?php

namespace Violet\StreamingJsonEncoder;

use PHPUnit\Framework\TestCase;

/**
 * StreamingJsonEncoderTest.
 *
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2016, Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class StreamingJsonEncoderTest extends TestCase
{
    public function testPrettyObjectArray()
    {
        $expectedJson = <<<'JSON'
{
    "key 1": "value 1",
    "key 2": "value 2"
}
JSON;

        $array = [
            'key 1' => 'value 1',
            'key 2' => 'value 2',
        ];

        $this->assertEncodingResult($expectedJson, $array, $array, JSON_PRETTY_PRINT);
    }

    public function testObjectArray()
    {
        $expectedJson = '{"key 1":"value 1","key 2":"value 2"}';
        $array = [
            'key 1' => 'value 1',
            'key 2' => 'value 2',
        ];

        $this->assertEncodingResult($expectedJson, $array, $array);
    }

    public function assertEncodingResult($expectedJson, $expectedData, $initialData, $options = 0)
    {
        $encoder = new StreamingJsonEncoder();
        $this->expectOutputString($expectedJson);
        $encoder->encode($initialData, $options);
        $this->assertSame($expectedData, json_decode($expectedJson, true));
    }
}
