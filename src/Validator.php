<?php

namespace alcamo\scan;

class Validator
{
    public const FORMATS = [ 'jpeg', 'png', 'pnm', 'tiff' ];

    public const TYPES = [ 'image', 'text' ];

    public static function validateFormat($value): bool
    {
        return in_array($value, static::FORMATS);
    }

    public static function validateGeometry($value): bool
    {
        return preg_match('\d+x\d+(\+\d+\+\d+)?|a[456]r?', $value);
    }

    public static function validateLanguage($value): bool
    {
        return preg_match('[a-z][a-z]', $value);
    }

    public static function validateResolution($value): bool
    {
        return is_numeric($value) && $value > 0 && $value < 2400;
    }

    public static function validateType($value): bool
    {
        return in_array($value, static::TYPES);
    }
}
