<?php

namespace alcamo\scan;

use PHPUnit\Framework\TestCase;

class ExiftoolTest extends TestCase
{
    public function testApplyRdfaData(): void
    {
        $infile = __DIR__ . DIRECTORY_SEPARATOR . 'alcamo.png';

        $outfile = __DIR__ . DIRECTORY_SEPARATOR . 'result.png';

        if (file_exists($outfile)) {
            unlink($outfile);
        }

        $rdfaData = [
            [ 'dc:creator', 'Alice' ],
            [ 'dc:coverage', 'alcamo-scan' ],
            [ 'dc:date', '2026-06-22' ],
            [ 'dc:language', 'it' ],
            [ 'dc:title', 'Logo' ],
            [ 'dc:type', 'Text' ],
            [ 'dc:identifier', 'alcamo-logo' ],
            [ 'dc:publisher', 'Bob' ]
        ];

        $exiftool = new Exiftool();

        $this->assertSame('/usr/bin/exiftool', $exiftool->getExiftool());

        $exiftool->applyRdfaData($rdfaData, $infile, $outfile);

        $this->assertTrue(file_exists($outfile));

        exec("{$exiftool->getExiftool()} $outfile", $output, $resultCode);

        $this->assertSame(0, $resultCode);

        $exifData = [];

        foreach ($output as $line) {
            [ $key, $value ] = explode(':', $line, 2);

            $exifData[trim($key)] = trim($value);
        }

        $expectedExifData = [
            'Coverage'  => 'alcamo-scan',
            'Creator'   => 'Alice',
            'Date'      => '2026:06:22 00:00:00+00:00',
            'Language'  => 'it',
            'Publisher' => 'Bob',
            'Title'     => 'Logo',
            'Type'      => 'Text'
        ];

        foreach ($expectedExifData as $key => $value) {
            $this->assertSame($value, $exifData[$key]);
        }

        unlink($outfile);
    }
}
