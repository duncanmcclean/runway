<?php

namespace StatamicRadPack\Runway\Support;

class Json
{
    public static function isJson($value): bool
    {
        if (
            $value === null
            || is_array($value)
            || is_object($value)
            || (is_string($value) && ! str_starts_with($value, '[') && ! str_starts_with($value, '{'))
        ) {
            return false;
        }

        return json_validate($value);
    }
}
