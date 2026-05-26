<?php
/**
 * ContentProcessor - Shim thay thế class xử lý link gốc của Rank Math Free.
 *
 * Khi plugin InterLink Genius chạy độc lập, lớp này cung cấp các phương thức
 * cơ bản để parse và lưu link từ nội dung bài viết.
 *
 * @package InterLinkGenius
 * @subpackage Compatibility
 */

namespace InterLinkGenius\Compatibility;

use InterLinkGenius\Compatibility\Helpers\DB;

defined( 'ABSPATH' ) || exit;

/**
 * Content_Processor class.
 *
 * Xử lý cơ bản: trích xuất link, lưu vào DB, update meta count.
 */
class Content_Processor {

	/**
	 * Singleton instance.
	 *
	 * @var self
	 */
	private static $instance;

	/**
	 * Lấy singleton instance.
	 *
	 * @return static
	 */
	public static function get() {
		if ( null === self::$instance ) {
			self::$instance = new static();
		}
		return self::$instance;
	}

	/**
	 * Xử lý nội dung bài viết: parse links và lưu vào DB.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $content Nội dung bài viết.
	 */
	public function process( $post_id, $content ) {
		if ( empty( $post_id ) || empty( $content ) ) {
			return;
		}

		// Cho phép các filter PRO override toàn bộ quá trình extraction.
		$data = apply_filters( 'interlink_genius/links/extract', null, $content, $post_id );

		if ( null === $data ) {
			// Fallback: parse cơ bản nếu không có filter nào override.
			$data = $this->parse_links_from_content( $content, $post_id );
		}

		// Cho phép các filter PRO override quá trình lưu link.
		$links  = $data['links'] ?? [];
		$counts = $data['counts'] ?? [];

		apply_filters( 'interlink_genius/links/save_links', null, $post_id, [ 'links' => $links, 'counts' => $counts ] );
	}

	/**
	 * Parse HTML content để trích xuất thông tin link cơ bản.
	 *
	 * @param string $content Nội dung HTML.
	 * @param int    $post_id Post ID.
	 * @return array Mảng ['links' => [...], 'counts' => [...]]
	 */
	public function parse_links_from_content( $content, $post_id ) {
		$links    = [];
		$internal = 0;
		$external = 0;
		$site_url = home_url();

		// Dùng regex đơn giản để trích xuất thẻ <a>.
		if ( ! preg_match_all( '/<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/si', $content, $matches ) ) {
			return [ 'links' => [], 'counts' => [ 'internal' => 0, 'external' => 0 ] ];
		}

		foreach ( $matches[1] as $index => $url ) {
			$url = trim( $url );
			if ( empty( $url ) || '#' === substr( $url, 0, 1 ) || 'javascript' === strtolower( substr( $url, 0, 10 ) ) ) {
				continue;
			}

			$is_internal = ( strpos( $url, $site_url ) === 0 || '/' === substr( $url, 0, 1 ) );

			if ( $is_internal ) {
				++$internal;
				$type = 'internal';
			} else {
				++$external;
				$type = 'external';
			}

			$links[] = [
				'url'         => $url,
				'post_id'     => $post_id,
				'type'        => $type,
				'anchor_text' => wp_strip_all_tags( $matches[2][ $index ] ),
				'is_nofollow' => ( strpos( $matches[0][ $index ], 'nofollow' ) !== false ) ? 1 : 0,
				'target_blank' => ( strpos( $matches[0][ $index ], '_blank' ) !== false ) ? 1 : 0,
			];
		}

		return [
			'links'  => $links,
			'counts' => [
				'internal' => $internal,
				'external' => $external,
			],
		];
	}

	/**
	 * Lấy danh sách link của một bài viết từ DB.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $type    Loại link: 'all', 'internal', 'external'.
	 * @return array
	 */
	public function get_links( $post_id, $type = 'all' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'interlink_genius_links';

		if ( 'all' === $type ) {
			$query = $wpdb->prepare( "SELECT * FROM `{$table}` WHERE post_id = %d", $post_id );
		} else {
			$query = $wpdb->prepare( "SELECT * FROM `{$table}` WHERE post_id = %d AND type = %s", $post_id, $type );
		}

		return $wpdb->get_results( $query ) ?: []; // phpcs:ignore WordPress.DB
	}
}
