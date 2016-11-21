<?php

namespace Violet\StreamingJsonEncoder;

use PHPUnit\Framework\TestCase;
use Violet\StreamingJsonEncoder\Test\SerializableData;

/**
 * StreamingJsonEncoderTest.
 *
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2016, Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class StreamingJsonEncoderTest extends TestCase
{
    public function testPrettyPrint()
    {
        $expectedJson = <<<'JSON'
{
    "key 1": "value 1",
    "key 2": "value 2",
    "key 3": [
        {
            "1": "sub 1",
            "0": "sub 2"
        },
        [
        ],
        [
            "foo"
        ]
    ]
}
JSON;

        $array = [
            'key 1' => 'value 1',
            'key 2' => 'value 2',
            'key 3' => [
                [
                    1 => 'sub 1',
                    0 => 'sub 2',
                ],
                [],
                [
                    'foo',
                ],
            ]
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

    public function testEmptyArray()
    {
        $expectedJson = '[]';
        $array = [];

        $this->assertEncodingResult($expectedJson, $array, $array);
    }

    public function testEmptyIterable()
    {
        $expectedJson = '[]';
        $array = new \ArrayObject([]);

        $this->assertEncodingResult($expectedJson, [], $array);
    }

    public function testForceObject()
    {
        $expectedJson = '{"0":"value 1","1":"value 2"}';
        $array = [
            'value 1',
            'value 2',
        ];

        $this->assertEncodingResult($expectedJson, $array, $array, JSON_FORCE_OBJECT);
    }

    public function testIteratorAsArray()
    {
        $expectedJson = '["value 1","value 2"]';
        $array = new \ArrayObject([
            'value 1',
            'value 2',
        ]);

        $this->assertEncodingResult($expectedJson, ['value 1', 'value 2'], $array);
    }

    public function testJsonSerializable()
    {
        $expectedJson = '{"key 1":{"sub key 1":"sub value 1"}}';
        $serializable = new SerializableData(new SerializableData([
            'key 1' => new SerializableData([
                'sub key 1' => 'sub value 1',
            ])
        ]));

        $this->assertEncodingResult($expectedJson, ['key 1' => ['sub key 1' => 'sub value 1']], $serializable);
    }

    public function testInvalidDataType()
    {
        $encoder = new StreamingJsonEncoder();

        $this->expectException(EncodingException::class);
        $encoder->encode(fopen(__FILE__, 'r'));
    }

    public function testNullOnInvalid()
    {
        $generator = function () {
            yield 'one' => 'two';
            $array = ['foo'];
            yield $array => 'bar';
        };

        $expectedJson = '{"key 1":null,"key 2":{"one":"two"}}';
        $array = [
            'key 1' => fopen('php://memory', 'r'),
            "key 2" => $generator(),
        ];
        $result = ['key 1' => null, "key 2" => ['one' => 'two']];

        $errors = $this->assertEncodingResult($expectedJson, $result, $array, JSON_PARTIAL_OUTPUT_ON_ERROR);
        $this->assertSame([
            'Line 1, column 10: Type is not supported',
            'Line 1, column 35: Only string or integer keys are supported',
        ], $errors);
    }

    /**
     * @dataProvider getSimpleTestValues
     */
    public function testSimpleValues($value)
    {
        $encoder = new StreamingJsonEncoder();

        $this->expectOutputString(json_encode($value));
        $encoder->encode($value);
    }

    public function getSimpleTestValues()
    {
        return [
            [null],
            [true],
            [false],
            [10],
            [1.1],
            ['Test String'],
            [[
                'key 1' => 'value 1',
                'key 2' => 'value 2',
            ]],
            [['value 1', 'value 2', 'value 3']]
        ];
    }

    public function assertEncodingResult($expectedJson, $expectedData, $initialData, $options = 0)
    {
        $encoder = new StreamingJsonEncoder();
        $this->expectOutputString($expectedJson);
        $this->assertSame(strlen($expectedJson), $encoder->encode($initialData, $options));
        $this->assertSame($expectedData, json_decode($expectedJson, true));

        return $encoder->getErrors();
    }
}
