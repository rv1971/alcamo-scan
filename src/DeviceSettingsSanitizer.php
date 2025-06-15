<?php

namespace alcamo\scan;

use alcamo\sanitize\Sanitizer;

class DeviceSettingsSanitizer extends Sanitizer
{
    public const RULES = [
        'adf' => [
            'default' => false,
            'bool'
        ],
        'bufferSize' => [
            'default' => 32,
            'int'
        ],
        'format' => [
            'default' => 'jpeg',
            'enum' => [ 'jpeg', 'png', 'pnm', 'tiff' ]
        ],
        'geometry' => [
            'default' => 'a4',
            'regexp' => '/\d+x\d+(\+\d+\+\d+)?/'
        ],
        'mode' => [
            'default' => 'color',
            'enum' => [ 'color', 'gray', 'binary' ]
        ],
        'name' => [
            'string',
            'required'
        ],
        'resolution' => [
            'default' => 300,
            'int',
            'min' => 50,
            'max' => 4800
        ]
    ];

    private $geometries_; ///< array

    public function __construct(
        ?int $flags = null,
        ?array $geometries = null
    ) {
        parent::__construct($flags);

        $this->geometries_ = (array)$geometries;
    }

    public function sanitize(array $data): array
    {
        if (!isset($data['geometry'])) {
            $data['geometry'] = static::RULES['geometry']['default'];
        }

        if (!ctype_digit($data['geometry'][0])) {
            $data['geometry'] = $this->geometries_[$data['geometry']];
        }

        return parent::sanitize($data);
    }
}
