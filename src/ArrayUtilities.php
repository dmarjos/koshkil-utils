<?php
namespace Koshkil\Utilities;

class ArrayUtilities {
    
	public static function sanitizeStrings($array = array(), $cdata = true) {
		foreach ( $array as $key => $value ) {
			if (is_array ( $value ) || is_object ( $value )) {
				$array [$key] = self::sanitizeStrings ( $value, $cdata );
			} else {
				/*
				 * $entitied=htmlentities($value);
				 * if ($value!=$entitied)
				 * $value=($cdata?array("@cdata"=>htmlentities($value)):utf8_encode($value));
				 */
				$array [$key] = $value;
			}
		}
		
		return $array;
	}
}