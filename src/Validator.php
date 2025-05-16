<?php

namespace alcamo\scan;

class Validator
{
    public const FORMATS = [ 'jpeg', 'png', 'pnm', 'tiff' ];

    public const MODES = [ 'color', 'gray', 'binary' ];

    public const TYPES = [ 'Image', 'Text' ];

    public static function validateFormat($value): bool
    {
        return in_array($value, static::FORMATS);
    }

    public static function validateDates($value): bool
    {
        return preg_match(
            '/(20|19)\d\d-\d\d-\d\d(,20\d\d-\d\d-\d\d)*/',
            $value
        );
    }

    public static function validateGeometry($value): bool
    {
        return preg_match('/\d+x\d+(\+\d+\+\d+)?|a4|a[567]r?/', $value);
    }

    public static function validateLanguage($value): bool
    {
        return preg_match('/[a-z][a-z]/', $value);
    }

    public static function validateMode($value): bool
    {
        return in_array($value, static::MODES);
    }

    public static function validatePageNumber($value): bool
    {
        return is_numeric($value) && $value > 0 && $value < 100;
    }

    public static function validatePageNumberIncrement($value): bool
    {
        return is_numeric($value) && $value >= -10 && $value <= 10;
    }

    public static function validateResolution($value): bool
    {
        return is_numeric($value) && $value > 0 && $value <= 2400;
    }

    public static function validateType($value): bool
    {
        return in_array($value, static::TYPES);
    }
}
