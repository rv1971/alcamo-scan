<?php

namespace alcamo\scan;

use alcamo\exception\InvalidEnumerator;
use PHPUnit\Framework\TestCase;

class DeviceTest extends TestCase
{
    public const GEOMETRIES = [
        'a4' => '210x297',
        'a5' => '148x210',
        'a6' => '105x148'
    ];

    public function testBasics(): void
    {
        $device = new Device(
            [ 'name' => 'bar:net:192.168.1.7:4242', 'geometry' => 'a6' ],
            self::GEOMETRIES
        );

        $this->assertFalse($device['adf']);
        $this->assertSame('105x148', $device['geometry']);

        $device->addSettings([ 'geometry' => null ]);

        $this->assertSame('105x148', $device['geometry']);
    }
}
