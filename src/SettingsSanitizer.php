<?php

namespace alcamo\scan;

use alcamo\sanitize\Sanitizer;

class SettingsSanitizer extends Sanitizer
{
    public const RULES = [
        'autopdf' => [
            'default' => false,
            'bool'
        ],
        'dc:language' => [
            'regexp' => '/[a-z][a-z]/'
        ],
        'dc:type' => [
            'enum' => [ 'Image', 'Text' ]
        ]
    ];
}
