<?php
namespace InterLinkGenius\Compatibility\Helpers;

defined( 'ABSPATH' ) || exit;

class Url {
	public static function is_url( $url ) {
		return (bool) filter_var( $url, FILTER_VALIDATE_URL );
	}
}
