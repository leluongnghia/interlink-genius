<?php
namespace InterLinkGenius\Compatibility;

defined( 'ABSPATH' ) || exit;

class Param {
	public static function get( $key, $default = '', $filter = FILTER_DEFAULT ) {
		if ( ! isset( $_REQUEST[ $key ] ) ) {
			return $default;
		}

		$value = $_REQUEST[ $key ];
		if ( is_string( $value ) ) {
			return sanitize_text_field( wp_unslash( $value ) );
		}

		return $value;
	}
}
