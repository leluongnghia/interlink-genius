<?php
namespace InterLinkGenius\Compatibility;

defined( 'ABSPATH' ) || exit;

class Helper {

	public static function has_cap( $cap ) {
		return current_user_can( 'manage_options' );
	}

	public static function get_admin_url( $page ) {
		if ( 'links-page' === $page ) {
			return admin_url( 'admin.php?page=interlink-genius' );
		}
		return admin_url( 'admin.php?page=' . $page );
	}

	public static function is_post_indexable( $post_id ) {
		return true;
	}

	public static function get_settings( $key, $default = null ) {
		$parts = explode( '.', $key );
		$group = $parts[0];
		$option_key = isset( $parts[1] ) ? $parts[1] : '';

		$settings = get_option( 'interlink_genius_settings', [] );

		if ( empty( $option_key ) ) {
			return isset( $settings[ $group ] ) ? $settings[ $group ] : $default;
		}

		return isset( $settings[ $group ][ $option_key ] ) ? $settings[ $group ][ $option_key ] : $default;
	}

	public static function get_accessible_post_types() {
		$post_types = get_post_types( [ 'public' => true ], 'names' );
		unset( $post_types['attachment'] );
		return array_values( $post_types );
	}

	public static function get_content_ai_plan() {
		return [
			'credits' => 1000,
			'plan'    => 'pro',
		];
	}

	public static function remove_notification( $notification_id ) {
		// No-op: Rank Math thông báo không cần thiết khi chạy độc lập.
		delete_option( 'rank_math_notifications_' . $notification_id );
	}

	public static function is_module_active( $module ) {
		// Kiểm tra xem Rank Math module có active không; mặc định false khi standalone.
		if ( function_exists( 'rank_math' ) && isset( rank_math()->manager ) ) {
			return rank_math()->manager->is_module_active( $module );
		}
		return false;
	}

	public static function update_feature_usage( $feature, $used, $remaining = null ) {
		// No-op or track in options if needed.
	}


	public static function get_object_taxonomies( $post_type, $output = 'names' ) {
		return get_object_taxonomies( $post_type, $output );
	}
}
