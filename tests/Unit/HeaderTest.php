<?php

namespace AwsXRayTests\Unit;

use AwsXRay\Header;
use PHPUnit\Framework;

final class HeaderTest extends Framework\TestCase
{
    const TRACE_ID = '0-57ff426a-80c11c39b0c928905eb0828d';

    public function testSampledEqualsOneHeaderString()
    {
        $header = Header::fromString('Sampled=1');

        $this->assertTrue($header->isSampled());
        $this->assertEmpty($header->getTraceId());
        $this->assertEmpty($header->getParentId());
        $this->assertEmpty($header->getAdditionalData());
    }

    public function testLonghHeaderString()
    {
        $header = Header::fromString('Sampled=?;Root=' . self::TRACE_ID . ';Parent=foo;Self=2;Foo=bar');

        $this->assertTrue($header->isSamplingRequested());
        $this->assertEquals(self::TRACE_ID, $header->getTraceId());
        $this->assertEquals('foo', $header->getParentId());
        $this->assertNull($header->getAdditionalValue('Self'));
        $this->assertEquals('bar', $header->getAdditionalValue('Foo'));
    }

    public function testLonghHeaderStringWithSpaces()
    {
        $header = Header::fromString('Sampled=?; Root=' . self::TRACE_ID . '; Parent=foo; Self=2; Foo=bar');

        $this->assertTrue($header->isSamplingRequested());
        $this->assertEquals(self::TRACE_ID, $header->getTraceId());
        $this->assertEquals('foo', $header->getParentId());
        $this->assertEquals('bar', $header->getAdditionalValue('Foo'));
    }

    public function testSampledUnknownToString()
    {
        $header = new Header();
        $this->assertEquals('', $header->__toString());
    }

    public function testSampledEqualsOneToString()
    {
        $header = new Header(null, null, Header::SAMPLED);
        $this->assertEquals('Sampled=1', $header->__toString());
    }

    public function testSampledEqualsOneAndParentToString()
    {
        $header = new Header(null, 'foo', Header::SAMPLED);
        $this->assertEquals('Parent=foo;Sampled=1', $header->__toString());
    }

    public function testLonghToString()
    {
        $header = new Header(self::TRACE_ID, 'foo', Header::SAMPLED, ['Foo' => 'bar']);
        $this->assertEquals('Root=' . self::TRACE_ID . ';Parent=foo;Sampled=1;Foo=bar', $header->__toString());
    }
}
