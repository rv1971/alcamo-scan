<?php

namespace alcamo\scan;

use alcamo\collection\ReadonlyCollection;

class Device extends ReadonlyCollection
{
    private $sanitizer_; ///< DeviceSettingsSanitizer

    public function __construct(array $data, ?array $geometries = null)
    {
        $this->sanitizer_ = new DeviceSettingsSanitizer(
            DeviceSettingsSanitizer::THROW_ON_INVALID,
            $geometries
        );

        parent::__construct($this->sanitizer_->sanitize($data));
    }

    public function addSettings(array $data): void
    {
        foreach ($data as $key => $value) {
            if (isset($value)) {
                $this->data_[$key] = $value;
            }
        }

        $this->data_ = $this->sanitizer_->sanitize($this->data_);
    }
}
