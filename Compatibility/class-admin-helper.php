<?php
namespace InterLinkGenius\Compatibility;

defined( 'ABSPATH' ) || exit;

class Admin_Helper {
	public static function get_registration_data() {
		if ( class_exists( 'RankMath\Admin\Admin_Helper' ) ) {
			return \RankMath\Admin\Admin_Helper::get_registration_data();
		}
		return get_option( 'rank_math_connect_data', [] );
	}

	public static function is_post_edit() {
		if ( class_exists( 'RankMath\Admin\Admin_Helper' ) ) {
			return \RankMath\Admin\Admin_Helper::is_post_edit();
		}
		global $pagenow;
		return in_array( $pagenow, [ 'post.php', 'post-new.php' ], true );
	}
}
