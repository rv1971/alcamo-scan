<?php

namespace alcamo\scan;

use PHPUnit\Framework\TestCase;

class IniFileTest extends TestCase
{
    public function testBasics(): void
    {
        putenv('XDG_CONFIG_HOME=' . __DIR__);

        $iniFile = IniFile::newFromFilename('test.ini');

        $this->assertSame(
            'ig',
            $iniFile['settings']['dc:language']
        );

        $this->assertSame(
            '105x74',
            $iniFile['geometries']['a7r']
        );

        $unknownDevice = $iniFile->getDevice('baz');

        $this->assertSame('baz', $unknownDevice['name']);
        $this->assertSame(600, $unknownDevice['resolution']);

        $fooDevice = $iniFile->getDevice('foo');

        $this->assertSame('foo:net:192.168.1.42:1865', $fooDevice['name']);
        $this->assertSame(300, $fooDevice['resolution']);
        $this->assertSame(256, $fooDevice['bufferSize']);
        $this->assertTrue($fooDevice['adf']);
    }
}
