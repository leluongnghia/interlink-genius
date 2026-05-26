<?php
namespace InterLinkGenius\Compatibility;

defined( 'ABSPATH' ) || exit;

class Arr {
	public static function insert( &$array, $pairs, $position = 0 ) {
		if ( $position === 0 ) {
			$array = array_merge( $pairs, $array );
		} else {
			$array = array_slice( $array, 0, $position, true ) +
				$pairs +
				array_slice( $array, $position, null, true );
		}
	}
}
