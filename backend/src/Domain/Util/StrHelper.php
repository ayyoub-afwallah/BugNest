<?php

namespace App\Domain\Util;

class StrHelper
{
    static function toCamelCase($string) {
        return lcfirst(str_replace('_', '', ucwords($string, '_')));
    }
}
