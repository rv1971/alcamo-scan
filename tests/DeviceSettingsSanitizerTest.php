<?php

namespace alcamo\scan;

use PHPUnit\Framework\TestCase;

class DeviceSettingsSanitizerTest extends TestCase
{
    public const GEOMETRIES = [
        'a4' => '210x297',
        'a5' => '148x210'
    ];

    /**
     * @dataProvider basicsProvider
     */
    public function testBasics(array $input, array $expectedOutput): void
    {
        $sanitizer = new DeviceSettingsSanitizer(null, self::GEOMETRIES);

        $this->assertSame($expectedOutput, $sanitizer->sanitize($input));
    }

    public function basicsProvider(): array
    {
        return [
            [
                [
                    'geometry' => 'a5',
                    'resolution' => '600',
                    'name' => 'foo:net:42.42.42.42:7'
                ],
                [
                    'geometry' => '148x210',
                    'resolution' => 600,
                    'name' => 'foo:net:42.42.42.42:7',
                    'adf' => false,
                    'bufferSize' => 32,
                    'format' => 'jpeg',
                    'mode' => 'color'
                ]
            ],
            [
                [
                    'name' => 'foo:net:42.42.42.42:8'
                ],
                [
                    'name' => 'foo:net:42.42.42.42:8',
                    'geometry' => '210x297',
                    'adf' => false,
                    'bufferSize' => 32,
                    'format' => 'jpeg',
                    'mode' => 'color',
                    'resolution' => 300
                ]
            ],
            [
                [
                    'name' => 'foo:net:42.42.42.42:9',
                    'foo' => 'bar'
                ],
                [
                    'name' => 'foo:net:42.42.42.42:9',
                    'geometry' => '210x297',
                    'adf' => false,
                    'bufferSize' => 32,
                    'format' => 'jpeg',
                    'mode' => 'color',
                    'resolution' => 300
                ]
            ]
        ];
    }
}
