<?php

namespace Violet\StreamingJsonEncoder;

use PHPUnit\Framework\TestCase;

/**
 * JsonStreamTest.
 *
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2016-2020 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class JsonStreamTest extends TestCase
{
    public function testExactReads()
    {
        $stream = new JsonStream(['key' => 'value']);

        $this->assertFalse($stream->eof());
        $this->assertSame(0, $stream->tell());
        $this->assertSame('{', $stream->read(1));
        $this->assertSame(1, $stream->tell());
        $this->assertFalse($stream->eof());
        $this->assertSame('"key":"value"}', $stream->read(14));
        $this->assertSame(15, $stream->tell());
        $this->assertTrue($stream->eof());
        $this->assertSame('', $stream->read(1));
    }

    public function testSeek()
    {
        $stream = new JsonStream(['key' => 'value']);

        $stream->seek(8);
        $this->assertSame('value', $stream->read(5));
        $this->assertSame(13, $stream->tell());

        $stream->seek(-6, SEEK_CUR);
        $this->assertSame('"', $stream->read(1));
        $this->assertSame(8, $stream->tell());

        $stream->seek(100);
        $this->assertSame(15, $stream->tell());
        $this->assertTrue($stream->eof());
        $this->assertSame('', $stream->read(1));

        $stream->seek(9);
        $this->assertFalse($stream->eof());
        $this->assertSame(9, $stream->tell());
        $this->assertSame('a', $stream->read(1));
        $stream->seek(11);
        $this->assertSame(11, $stream->tell());
        $this->assertSame('u', $stream->read(1));
    }

    public function testReadAfterClose()
    {
        $stream = new JsonStream('value');
        $stream->close();

        $this->expectException(\RuntimeException::class);
        $stream->read(1);
    }

    public function testToString()
    {
        $stream = new JsonStream(['key' => 'value']);
        $this->assertSame('{"key":"value"}', (string) $stream);
        $this->assertTrue($stream->eof());
    }

    public function testToStringAfterClose()
    {
        $stream = new JsonStream('value');
        $stream->close();
        $this->assertSame('', (string) $stream);
    }

    public function testConstantValueMethods()
    {
        $stream = new JsonStream('value');

        $this->assertNull($stream->detach());
        $this->assertNull($stream->getSize());
        $this->assertTrue($stream->isSeekable());
        $this->assertFalse($stream->isWritable());
        $this->assertTrue($stream->isReadable());
    }

    public function testMetaData()
    {
        $stream = new JsonStream('value');

        $this->assertNull($stream->getMetadata('key_that_does_not_exist'));
        $this->assertTrue($stream->getMetadata('seekable'));
        $this->assertSame(['timed_out',
            'blocked',
            'eof',
            'unread_bytes',
            'stream_type',
            'wrapper_type',
            'wrapper_data',
            'mode',
            'seekable',
            'uri',
        ], array_keys($stream->getMetadata()));
    }

    public function testWriting()
    {
        $stream = new JsonStream('value');

        $this->expectException(\RuntimeException::class);
        $stream->write('string');
    }

    public function testSeekFromEnd()
    {
        $stream = new JsonStream('value');

        $this->expectException(\RuntimeException::class);
        $stream->seek(2, SEEK_END);
    }

    public function testInvalidSeekWhence()
    {
        $stream = new JsonStream('value');

        $this->expectException(\InvalidArgumentException::class);
        $stream->seek(2, -1);
    }

    public function testPrettyPrintStream()
    {
        $encoder = (new BufferJsonEncoder(['value']))
            ->setOptions(JSON_PRETTY_PRINT);

        $stream = new JsonStream($encoder);
        $this->assertSame("[\n    \"value\"\n]", $stream->getContents());
    }

    public function testGetRemainingContents()
    {
        $encoder = (new BufferJsonEncoder(['value']));
        $stream = new JsonStream($encoder);

        $this->assertSame('["val', $stream->read(5));
        $this->assertSame('ue"]', $stream->getContents());
    }

    public function testGetContentOnEof()
    {
        $encoder = (new BufferJsonEncoder(['value']));
        $stream = new JsonStream($encoder);

        $this->assertSame('["value"]', $stream->getContents());
        $this->assertSame('', $stream->getContents());
    }
}
