<?php

namespace alcamo\scan;

use alcamo\collection\ReadonlyCollection;

class Settings extends ReadonlyCollection
{
    private $sanitizer_; ///< SettingsSanitizer

    public function __construct(?array $data = null)
    {
        $this->sanitizer_ =
            new SettingsSanitizer(SettingsSanitizer::THROW_ON_INVALID);

        parent::__construct($this->sanitizer_->sanitize((array)$data));
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
