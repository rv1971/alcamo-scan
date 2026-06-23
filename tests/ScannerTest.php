<?php

namespace alcamo\scan;

use alcamo\dom\DocumentValidator;
use alcamo\xml_conf\Loader;
use PHPUnit\Framework\TestCase;

class ScannerTest extends TestCase
{
    /**
     * @dataProvider createCmdLineProvider
     */
    public function testCreateCmdLine(
        $deviceId,
        $options,
        $filenamePrefix,
        $totalPageCount,
        $batchStart,
        $batchIncrement,
        $expectedCmdLine
    ): void {
        $configHome = __DIR__;

        putenv("XDG_CONFIG_HOME=$configHome");

        $conf = Loader::newFromDocumentClass(ConfDocument::class)
            ->load('conf.xml');

        $conf->chooseDevice($deviceId);

        foreach ($options as $key => $value) {
            $conf->$key = $value;
        }

        (new DocumentValidator())->validate($conf);

        $scanner = new Scanner($conf);

        $this->assertSame(
            $expectedCmdLine,
            $scanner->createCmdLine(
                $filenamePrefix,
                $totalPageCount,
                $batchStart,
                $batchIncrement
            )
        );
    }

    public function createCmdLineProvider(): array
    {
        return [
            [
                'epkowa',
                [],
                'scan',
                null,
                null,
                null,
                '/usr/bin/scanimage '
                . '--batch=scan.%04d.jpeg '
                . '--device-name=epkowa:net:192.168.178.42:1865 '
                . '--format=jpeg '
                . '--progress '
                . "--source='Automatic Document Feeder' "
                . '--buffer-size=64 '
                . '--mode=Color '
                . '--x-resolution=150 '
                . '--y-resolution=150 '
            ],
            [
                'fooscan',
                [
                    'bufferSize' => 256,
                    'format' => 'png',
                    'geometry' => 'a6',
                    'mode' => 'binary',
                    'resolution' => 300
                ],
                'scan42',
                10,
                3,
                2,
                '/usr/bin/scanimage '
                . '--batch=scan42.%04d.png '
                . '--device-name= '
                . '--format=png '
                . '--progress '
                . '--buffer-size=256 '
                . '-x 105 -y 148 '
                . '--mode=Lineart '
                . '--resolution=300 '
                . '--batch-prompt '
                . '--batch-count=10 '
                . '--batch-start=3 '
                . '--batch-increment=2 '
            ]
        ];
    }
}
