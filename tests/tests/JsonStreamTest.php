<?php

namespace Violet\StreamingJsonEncoder;

use PHPUnit\Framework\TestCase;

/**
 * JsonStreamTest.
 *
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2016, Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class JsonStreamTest extends TestCase
{
    public function testExactReads()
    {
        $stream = new JsonStream(['key' => 'value']);

        $this->assertSame(false, $stream->eof());
        $this->assertSame(0, $stream->tell());
        $this->assertSame('{', $stream->read(1));
        $this->assertSame(1, $stream->tell());
        $this->assertSame(false, $stream->eof());
        $this->assertSame('"key":"value"}', $stream->read(14));
        $this->assertSame(15, $stream->tell());
        $this->assertSame(true, $stream->eof());
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

        $this->assertSame(null, $stream->detach());
        $this->assertSame(null, $stream->getSize());
        $this->assertSame(true, $stream->isSeekable());
        $this->assertSame(false, $stream->isWritable());
        $this->assertSame(true, $stream->isReadable());
        $this->assertSame([], $stream->getMetadata());
        $this->assertSame(null, $stream->getMetadata('seekable'));
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
}
