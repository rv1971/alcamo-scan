<?php

namespace alcamo\scan;

use alcamo\collection\ReadonlyCollection;
use alcamo\conf\{IniFileParser, Loader};

class IniFile extends ReadonlyCollection
{
    public const DEFAULT_FILENAME = 'scan.ini';

    public static function newFromFilename(?string $filename = null): self
    {
        return new static(
            (new Loader(null, new IniFileParser(true)))
                ->load($filename ?? static::DEFAULT_FILENAME)
        );
    }

    public function getDevice($id): Device
    {
        return new Device(
            ($this["device:$id"] ?? [ 'name' => $id ])
            + ($this['device:defaults'] ?? []),
            $this['geometries']
        );
    }

    public function createExample(): string
    {
        return <<<EOT
[settings]

; Default value for dc:language
;dc:language=en

; Default value for dc:type
;dc:type=Text

; If true, automatically create PDF for for multipage documents
;autopdf=true

[geometries]
a4=210x297
a5=148x210
a5r=210x148
a6=105x148
a6r=148x105
a7=74x105
a7r=105x74

[device:defaults]

; default settings that apply to all devices unless overridden in
; a device-specific section

bufferSize=64
geometry=a4
mode=color
resolution=150

;[device:epkowa]
;
;name=epkowa:net:192.168.1.42:1865

EOT;
    }
}
