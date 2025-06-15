<?php

namespace alcamo\scan;

use alcamo\exception\InvalidEnumerator;
use PHPUnit\Framework\TestCase;

class SettingsTest extends TestCase
{
    public function testBasics(): void
    {
        $settings = new Settings(
            [ 'autopdf' => true, 'dc:language' => 'fo' ]
        );

        $this->assertTrue($settings['autopdf']);
        $this->assertSame('fo', $settings['dc:language']);

        $settings->addSettings(
            [
                'autopdf' => false,
                'dc:language' => null,
                'dc:type' => 'Image' ]
        );

        $this->assertFalse($settings['autopdf']);
        $this->assertSame('fo', $settings['dc:language']);
        $this->assertSame('Image', $settings['dc:type']);
    }

    public function testException1(): void
    {
        $this->expectException(InvalidEnumerator::class);

        new Settings([ 'foo' => 'bar' ]);
    }

    public function testException2(): void
    {
        $settings = new Settings();

        $this->expectException(InvalidEnumerator::class);

        $settings->addSettings([ 'baz' => true ]);
    }
}
