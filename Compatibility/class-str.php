<?php
namespace InterLinkGenius\Compatibility\Helpers;

defined( 'ABSPATH' ) || exit;

class Str {
	public static function contains( $needle, $haystack ) {
		if ( '' === $needle ) {
			return true;
		}
		if ( function_exists( 'mb_strpos' ) ) {
			return false !== mb_strpos( $haystack, $needle );
		}
		return false !== strpos( $haystack, $needle );
	}

	public static function starts_with( $needle, $haystack ) {
		if ( '' === $needle ) {
			return true;
		}
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $haystack, 0, mb_strlen( $needle ) ) === $needle;
		}
		return substr( $haystack, 0, strlen( $needle ) ) === $needle;
	}

	public static function substr( $str, $start, $length = null ) {
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $str, $start, $length );
		}
		return substr( $str, $start, $length );
	}
}
