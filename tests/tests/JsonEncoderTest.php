<?php

namespace Violet\StreamingJsonEncoder;

use PHPUnit\Framework\TestCase;
use Violet\StreamingJsonEncoder\Test\DateEncoder;
use Violet\StreamingJsonEncoder\Test\SerializableData;

/**
 * StreamingJsonEncoderTest.
 *
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2016-2017 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class JsonEncoderTest extends TestCase
{
    public function testEmptyEncoding()
    {
        $this->assertEncodingResult('[]', [], []);
        $this->assertEncodingResult('[]', [], new \ArrayObject([]));
        $this->assertEncodingResult('{}', [], (object) []);
    }

    public function testObjectTyping()
    {
        $this->assertEncodingResult('["foo"]', ['foo'], ['foo']);
        $this->assertEncodingResult('{"0":"foo"}', ['foo'], (object) ['foo']);
        $this->assertEncodingResult('{"1":"foo"}', [1 => 'foo'], [1 => 'foo']);

        $this->assertEncodingResult('["foo"]', ['foo'], new \ArrayObject(['foo']));
        $this->assertEncodingResult('{"1":"foo"}', [1 => 'foo'], new \ArrayObject([1 => 'foo']));
        $this->assertEncodingResult('{"0":"foo"}', ['foo'], function () {
            yield '0' => 'foo';
        });
    }

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
            ],
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
            ]),
        ]));

        $this->assertEncodingResult($expectedJson, ['key 1' => ['sub key 1' => 'sub value 1']], $serializable);
    }

    public function testInvalidDataType()
    {
        $encoder = new BufferJsonEncoder(fopen('php://memory', 'r'));

        $this->expectException(EncodingException::class);
        $encoder->encode();
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
            'key 2' => $generator(),
        ];
        $result = ['key 1' => null, 'key 2' => ['one' => 'two']];

        $encoder = $this->assertEncodingResult($expectedJson, $result, $array, JSON_PARTIAL_OUTPUT_ON_ERROR);
        $this->assertSame([
            'Line 1, column 10: Type is not supported (JSON_ERROR_UNSUPPORTED_TYPE)',
            'Line 1, column 35: Only string or integer keys are supported',
        ], $encoder->getErrors());
    }

    /**
     * @param mixed $value The value to encode
     * @dataProvider getSimpleTestValues
     */
    public function testSimpleValues($value)
    {
        $encoder = new StreamJsonEncoder($value);
        $json = json_encode($value);

        $this->expectOutputString($json);
        $bytes = $encoder->encode();

        $this->assertSame(strlen($json), $bytes);
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
            [['value 1', 'value 2', 'value 3']],
        ];
    }

    public function testOptionsDuringEncoding()
    {
        $encoder = new BufferJsonEncoder('string');
        $encoder->rewind();

        $this->expectException(\RuntimeException::class);
        $encoder->setOptions(0);
    }

    public function testIndentDuringEncoding()
    {
        $encoder = new BufferJsonEncoder('string');
        $encoder->rewind();

        $this->expectException(\RuntimeException::class);
        $encoder->setIndent(4);
    }

    public function testNumericIndent()
    {
        $encoder = new BufferJsonEncoder(['value']);
        $encoder->setIndent(2);
        $encoder->setOptions(JSON_PRETTY_PRINT);

        $this->assertSame(
            "[\n  \"value\"\n]",
            $encoder->encode()
        );
    }

    public function testStringIndent()
    {
        $encoder = new BufferJsonEncoder(['value']);
        $encoder->setIndent("\t");
        $encoder->setOptions(JSON_PRETTY_PRINT);

        $this->assertSame(
            "[\n\t\"value\"\n]",
            $encoder->encode()
        );
    }

    public function testTokenList()
    {
        $data = ['key 1' => 'value', 'key 2' => ['sub 1', 'sub 2']];
        $actualTokens = [];
        $expectedTokens = [
            JsonToken::T_LEFT_BRACE,
            JsonToken::T_WHITESPACE,
            JsonToken::T_WHITESPACE,
            JsonToken::T_NAME,
            JsonToken::T_COLON,
            JsonToken::T_WHITESPACE,
            JsonToken::T_VALUE,
            JsonToken::T_COMMA,
            JsonToken::T_WHITESPACE,
            JsonToken::T_WHITESPACE,
            JsonToken::T_NAME,
            JsonToken::T_COLON,
            JsonToken::T_WHITESPACE,
            JsonToken::T_LEFT_BRACKET,
            JsonToken::T_WHITESPACE,
            JsonToken::T_WHITESPACE,
            JsonToken::T_VALUE,
            JsonToken::T_COMMA,
            JsonToken::T_WHITESPACE,
            JsonToken::T_WHITESPACE,
            JsonToken::T_VALUE,
            JsonToken::T_WHITESPACE,
            JsonToken::T_WHITESPACE,
            JsonToken::T_RIGHT_BRACKET,
            JsonToken::T_WHITESPACE,
            JsonToken::T_RIGHT_BRACE,
        ];

        $gatherTokens = function ($string, $token) use (&$actualTokens) {
            $actualTokens[] = $token;
        };

        $encoder = new StreamJsonEncoder($data, $gatherTokens);
        $encoder->setOptions(JSON_PRETTY_PRINT);
        $encoder->encode();

        $this->assertSame($expectedTokens, $actualTokens);

        $actualTokens = [];
        $encoder = new StreamJsonEncoder($data, $gatherTokens);
        $encoder->encode();

        $realTokens = array_values(array_filter($expectedTokens, function ($value) {
            return $value !== JsonToken::T_WHITESPACE;
        }));

        $this->assertSame($realTokens, $actualTokens);
    }

    public function testEncoderIterationKey()
    {
        $encoder = new BufferJsonEncoder('value');

        $this->assertSame(0, $encoder->key());
        $this->assertTrue($encoder->valid());

        $encoder->rewind();
        $this->assertSame(0, $encoder->key());
        $this->assertTrue($encoder->valid());

        $encoder->next();
        $this->assertNull($encoder->key());
        $this->assertFalse($encoder->valid());
    }

    public function testInvalidStatusAfterError()
    {
        $encoder = new BufferJsonEncoder(['foo', fopen('php://memory', 'wr')]);
        $result = null;

        try {
            $encoder->encode();
        } catch (EncodingException $exception) {
            $result = $exception;
        }

        $this->assertInstanceOf(EncodingException::class, $result);
        $this->assertFalse($encoder->valid());
    }

    public function testClosureResolving()
    {
        $encoder = new BufferJsonEncoder(function () {
            foreach (range(0, 9) as $value) {
                yield $value;
            }
        });

        $this->assertSame('[0,1,2,3,4,5,6,7,8,9]', $encoder->encode());
    }

    public function testValueStackResolving()
    {
        $date = new \DateTime();
        $encoder = new DateEncoder([$date]);

        $this->assertSame(
            sprintf('["<time datetime="%s">%s</time>"]', $date->format('c'), $date->format('r')),
            $encoder->encode()
        );
    }

    public function assertEncodingResult($expectedJson, $expectedData, $initialData, $options = 0)
    {
        $encoder = new BufferJsonEncoder($initialData);
        $encoder->setOptions($options);
        $output = $encoder->encode();

        $this->assertSame($expectedJson, $output);
        $this->assertSame($expectedData, json_decode($output, true));

        return $encoder;
    }
}
