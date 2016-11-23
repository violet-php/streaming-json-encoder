<?php

namespace Violet\StreamingJsonEncoder;

use PHPUnit\Framework\TestCase;
use Symfony\CS\Tokenizer\Token;
use Violet\StreamingJsonEncoder\Test\SerializableData;

/**
 * StreamingJsonEncoderTest.
 *
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2016, Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class JsonEncoderTest extends TestCase
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
            "key 2" => $generator(),
        ];
        $result = ['key 1' => null, "key 2" => ['one' => 'two']];

        $encoder = $this->assertEncodingResult($expectedJson, $result, $array, JSON_PARTIAL_OUTPUT_ON_ERROR);
        $this->assertSame([
            'Line 1, column 10: Type is not supported',
            'Line 1, column 35: Only string or integer keys are supported',
        ], $encoder->getErrors());
    }

    /**
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
            [['value 1', 'value 2', 'value 3']]
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
            Tokens::OPEN_OBJECT,
            Tokens::WHITESPACE,
            Tokens::WHITESPACE,
            Tokens::KEY,
            Tokens::SEPARATOR,
            Tokens::WHITESPACE,
            Tokens::VALUE,
            Tokens::COMMA,
            Tokens::WHITESPACE,
            Tokens::WHITESPACE,
            Tokens::KEY,
            Tokens::SEPARATOR,
            Tokens::WHITESPACE,
            Tokens::OPEN_ARRAY,
            Tokens::WHITESPACE,
            Tokens::WHITESPACE,
            Tokens::VALUE,
            Tokens::COMMA,
            Tokens::WHITESPACE,
            Tokens::WHITESPACE,
            Tokens::VALUE,
            Tokens::WHITESPACE,
            Tokens::WHITESPACE,
            Tokens::CLOSE_ARRAY,
            Tokens::WHITESPACE,
            Tokens::CLOSE_OBJECT,
        ];

        $gatherTokens = function ($string, $token) use (& $actualTokens) {
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
            return $value !== Tokens::WHITESPACE;
        }));

        $this->assertSame($realTokens, $actualTokens);
    }

    public function testEncoderIterationKey()
    {
        $encoder = new BufferJsonEncoder('value');

        $this->assertSame(null, $encoder->key());
        $this->assertSame(false, $encoder->valid());

        $encoder->rewind();
        $this->assertSame(0, $encoder->key());
        $this->assertSame(true, $encoder->valid());

        $encoder->next();
        $this->assertSame(null, $encoder->key());
        $this->assertSame(false, $encoder->valid());
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
