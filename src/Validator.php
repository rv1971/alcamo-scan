<?php

namespace alcamo\scan;

class Validator
{
    public static function validateDates($value): bool
    {
        return preg_match(
            '/(20|19)\d\d-\d\d-\d\d(,20\d\d-\d\d-\d\d)*/',
            $value
        );
    }

    public static function validatePageNumber($value): bool
    {
        return is_numeric($value) && $value > 0 && $value < 100;
    }

    public static function validatePageNumberIncrement($value): bool
    {
        return is_numeric($value) && $value >= -10 && $value <= 10;
    }
}
