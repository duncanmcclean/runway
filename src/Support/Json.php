<?php

namespace StatamicRadPack\Runway\Support;

class Json
{
    public static function isJson($value): bool
    {
        if (is_array($value) || is_object($value)) {
            return false;
        }

        return is_array(json_decode($value, true));
    }
}
