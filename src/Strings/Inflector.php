<?php
namespace Koshkil\Utilities\Strings;

use Koshkil\Utilities\StringUtilities;

class Inflector extends StringUtilities {

    public static function camelize($string) {
        return implode("",explode(" ",ucwords(implode(" ",explode("_",$string)))));
    }
}
