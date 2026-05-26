<?php
/**
 * The Link Genius module editor assets.
 *
 * @since      1.0.71
 * @package    RankMath
 * @subpackage RankMath
 * @author     Rank Math <support@rankmath.com>
 */

namespace InterLinkGenius\Admin;

use InterLinkGenius\Compatibility\Helper;
use InterLinkGenius\Compatibility\Arr;
use InterLinkGenius\Compatibility\Param;
use InterLinkGenius\Compatibility\Admin_Helper;
use InterLinkGenius\Compatibility\PRO_Admin_Helper as PRO_Admin_Helper;
use InterLinkGenius\Compatibility\Hooker;
use InterLinkGenius\Services\Utils;
use InterLinkGenius\Background\Export_Processor;
use InterLinkGenius\Background\Regenerate_Links;
use InterLinkGenius\Features\BulkUpdate\Preview_Processor;
use InterLinkGenius\Features\BulkUpdate\Processor;
use InterLinkGenius\Features\KeywordMaps\Keyword_Map_Processor;

defined( 'ABSPATH' ) || exit;

/**
 * Admin class.
 */
class Admin {

	use Hooker;

	/**
	 * Constructor.
	 */
	public function __construct() {
		if ( ! is_admin() ) {
			return;
		}

		$this->action( 'admin_menu', 'add_admin_menu' );
		$this->action( 'admin_enqueue_scripts', 'register_compatibility_scripts' );
		$this->action( 'admin_enqueue_scripts', 'override_links_page_assets' );
		$this->action( 'enqueue_block_editor_assets', 'enqueue' );
	}

	/**
	 * Add menu page for InterLink Genius.
	 */
	public function add_admin_menu() {
		add_menu_page(
			esc_html__( 'InterLink Genius', 'interlink-genius' ),
			esc_html__( 'InterLink Genius', 'interlink-genius' ),
			'manage_options',
			'interlink-genius',
			[ $this, 'render_admin_page' ],
			'dashicons-admin-links',
			30
		);
	}

	/**
	 * Render the React page container.
	 */
	public function render_admin_page() {
		?>
		<div class="wrap">
			<div id="rank-math-links-page-container"></div>
		</div>
		<?php
	}

	/**
	 * Register compatibility scripts to prevent dependency errors when Rank Math is not active.
	 */
	public function register_compatibility_scripts() {
		if ( ! wp_script_is( 'rank-math-components', 'registered' ) ) {
			wp_register_script( 'rank-math-components', false, [ 'wp-components', 'wp-element', 'lodash' ] );
		}
		if ( ! wp_script_is( 'rank-math-pro-editor', 'registered' ) ) {
			wp_register_script( 'rank-math-pro-editor', false, [ 'wp-data', 'wp-components', 'wp-element', 'wp-i18n' ] );
		}
	}

	/**
	 * Override the Free plugin's Links page assets with PRO's full React app.
	 */
	public function override_links_page_assets() {
		if ( Param::get( 'page' ) !== 'interlink-genius' ) {
			return;
		}

		wp_enqueue_script(
			'rank-math-links-page',
			INTERLINK_GENIUS_URL . 'assets/js/links-page.js',
			[ 'lodash', 'wp-components', 'wp-element', 'rank-math-components' ],
			INTERLINK_GENIUS_VERSION,
			true
		);

		$link_genius_data = [
			'exportLimit' => Export_Processor::get_export_limit(),
		];
		wp_add_inline_script(
			'rank-math-links-page',
			'var rankMath = rankMath || {}; rankMath.linkGenius = ' . wp_json_encode( $link_genius_data ) . ';',
			'before'
		);
	}

	/**
	 * Enqueue scripts for the editor screens.
	 */
	public function enqueue() {
		if ( ! Admin_Helper::is_post_edit() ) {
			return;
		}

		wp_enqueue_script(
			'rank-math-link-genius-editor',
			INTERLINK_GENIUS_URL . 'assets/js/editor.js',
			[
				'rank-math-pro-editor',
				'jquery',
				'wp-hooks',
				'wp-data',
				'wp-components',
				'wp-element',
				'wp-i18n',
				'wp-plugins',
				'wp-api-fetch',
			],
			INTERLINK_GENIUS_VERSION,
			true
		);

		// Provide initial data from postmeta so the panel can render on load (no HTML here).
		$post_id = get_the_ID();
		if ( $post_id ) {
			$link_genius_data = [
				'relatedItems'        => Utils::map_post_ids_to_items( get_post_meta( $post_id, 'interlink_genius_related_posts', true ) ),
				'aiItems'             => get_post_meta( $post_id, 'interlink_genius_ai_link_suggestions', true ),
				'autoLinkingDisabled' => '1' === get_post_meta( $post_id, 'interlink_genius_auto_linking_disabled', true ),
			];
			wp_add_inline_script(
				'rank-math-link-genius-editor',
				'var rankMath = rankMath || {}; rankMath.linkGenius = ' . wp_json_encode( $link_genius_data ) . ';',
				'before'
			);
		}
	}

	/**
	 * Get exclude terms for Link Genius settings.
	 *
	 * @return array
	 */
	private function get_link_genius_exclude_terms() {
		// Get excluded post types from settings.
		$excluded_post_types = Helper::get_settings( 'general.keyword_maps_excluded_post_types', [] );

		// Pass excluded post types to helper, which will show taxonomy terms for NON-excluded post types.
		return PRO_Admin_Helper::get_exclude_terms_for_settings( 'keyword_maps', $excluded_post_types, 'general' );
	}
}
