<?php
namespace InterLinkGenius\Compatibility\Helpers;

defined( 'ABSPATH' ) || exit;

class DB {
	public static function check_table_exists( $table ) {
		global $wpdb;
		$table_name = $wpdb->prefix . $table;
		return (bool) $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) );
	}

	public static function query( $query ) {
		global $wpdb;
		return $wpdb->query( $query );
	}

	public static function get_results( $query, $output_type = OBJECT ) {
		global $wpdb;
		return $wpdb->get_results( $query, $output_type );
	}

	public static function get_var( $query ) {
		global $wpdb;
		return $wpdb->get_var( $query );
	}

	public static function get_col( $query ) {
		global $wpdb;
		return $wpdb->get_col( $query );
	}

	public static function get_row( $query, $output_type = OBJECT ) {
		global $wpdb;
		return $wpdb->get_row( $query, $output_type );
	}
}
