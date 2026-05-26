<?php
/**
 * The Link Genius module.
 *
 * @since      1.0.71
 * @package    RankMath
 * @subpackage RankMath
 * @author     Rank Math <support@rankmath.com>
 */

namespace InterLinkGenius;

use InterLinkGenius\Compatibility\Hooker;
use InterLinkGenius\Admin\Admin;
use InterLinkGenius\Api\Operations_Rest;
use InterLinkGenius\Background\Export_Processor;
use InterLinkGenius\Background\Regenerate_Links;
use InterLinkGenius\Background\Link_Status_Crawler;
use InterLinkGenius\Background\Bulk_Link_Modifier;
use InterLinkGenius\Data\Table_Extension;
use InterLinkGenius\Services\Content_Processor;
use InterLinkGenius\Shortcodes\Related_Posts_Shortcode;
use InterLinkGenius\Blocks\Related\Block_Related_Posts;
use InterLinkGenius\Features\BulkUpdate;
use InterLinkGenius\Features\KeywordMaps;
use InterLinkGenius\Compatibility\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Link_Genius class.
 */
class Link_Genius {
	use Hooker;

	/**
	 * Class constructor.
	 */
	public function __construct() {
		// Register REST endpoints for this module.
		$this->action( 'rest_api_init', 'init_rest_api' );

		// Register cleanup hook for old export files.
		$this->action( 'interlink_genius_cleanup_exports', 'cleanup_old_exports' );

		// Register cron hook to start crawler after regeneration completes.
		$this->action( 'interlink_genius_start_crawler', 'start_link_crawler' );

		// Register cron hook to dispatch existing crawler queue.
		$this->action( 'interlink_genius_dispatch_crawler', 'dispatch_link_crawler' );

		// Register cron hook to process pending link queues from async post saves.
		$this->action( 'interlink_genius_queue_pending_links', 'process_pending_link_queues' );

		// Register cron hook for orphaned link status cleanup.
		$this->action( 'interlink_genius_cleanup_orphaned_link_status', 'cleanup_orphaned_link_status' );

		$this->action( 'rank_math/admin_bar/items', 'admin_bar_items' );

		// Schedule weekly orphaned status cleanup if not already scheduled.
		if ( ! wp_next_scheduled( 'interlink_genius_cleanup_orphaned_link_status' ) ) {
			wp_schedule_event( time(), 'weekly', 'interlink_genius_cleanup_orphaned_link_status' );
		}

		// Schedule daily export file cleanup if not already scheduled.
		if ( ! wp_next_scheduled( 'interlink_genius_cleanup_exports' ) ) {
			wp_schedule_event( time(), 'daily', 'interlink_genius_cleanup_exports' );
		}

		// Initialize Link Genius components.
		new Content_Processor();  // Enhanced link extraction.
		Export_Processor::get();  // Background export processor.
		Regenerate_Links::get();  // Background regeneration processor.
		Link_Status_Crawler::get(); // Background link status crawler.
		Bulk_Link_Modifier::get(); // Consolidated background processor for delete, mark_safe, and restore.
		new Table_Extension();    // Extend FREE tables with PRO columns.
		new Related_Posts_Shortcode();
		new Block_Related_Posts();

		if ( Helper::has_cap( 'link_builder' ) ) {
			new Admin();
		}

		// Initialize Bulk Update feature.
		BulkUpdate\Processor::get();
		BulkUpdate\Preview_Processor::get();

		// Initialize Keyword Maps feature.
		KeywordMaps\Preview_Processor::get();
		KeywordMaps\Keyword_Map_Processor::get();
		KeywordMaps\Keyword_Maps::get();
	}

	/**
	 * Initialize REST API routes.
	 */
	public function init_rest_api() {
		$rest = new Rest();
		$rest->register_routes();

		// Initialize unified Operations REST endpoints.
		new Operations_Rest();

		// Initialize Bulk Update REST endpoints.
		new Features\BulkUpdate\Rest();

		// Initialize Keyword Maps REST endpoints.
		KeywordMaps\Keyword_Maps::init_rest_api();
	}

	/**
	 * Cleanup old export files.
	 */
	public function cleanup_old_exports() {
		Export_Processor::cleanup_old_exports();
	}

	/**
	 * Start link status crawler.
	 *
	 * Called via cron after regeneration completes.
	 * Must be decoupled from regeneration background process to work properly.
	 */
	public function start_link_crawler() {
		// Start crawler with unchecked_only filter to check all new links.
		Link_Status_Crawler::get()->start( [ 'unchecked_only' => true ] );
	}

	/**
	 * Dispatch existing link crawler queue.
	 *
	 * Called via cron after links are queued from post save.
	 * Triggers processing of already-queued items without creating new queue.
	 */
	public function dispatch_link_crawler() {
		Link_Status_Crawler::get()->dispatch();
	}

	/**
	 * Process pending link queues stored in options.
	 *
	 * Called via cron after post saves to process link data asynchronously.
	 * This prevents blocking HTTP responses when saving posts with many links.
	 */
	public function process_pending_link_queues() {
		Link_Status_Crawler::process_pending_link_queues();
	}

	/**
	 * Clean up orphaned link status records.
	 *
	 * Called weekly via cron to remove status records for links that no longer exist.
	 * This handles edge cases like post deletions where our reconcile logic doesn't run.
	 */
	public function cleanup_orphaned_link_status() {
		Link_Status_Crawler::cleanup_orphaned_status();
	}

	/**
	 * Updates update admin bar items.
	 *
	 * @param Admin_Bar_Menu $menu The Admin Bar Menu object.
	 *
	 * @return void
	 */
	public function admin_bar_items( $menu ) {
		// Early bail if current user doesn't have access to Link Genius.
		if ( ! Helper::has_cap( 'link_builder' ) ) {
			return;
		}

		$url = Helper::get_admin_url( 'links-page' );

		$menu->add_sub_menu(
			'link-genius',
			[
				'title'    => esc_html__( 'Link Genius', 'interlink-genius' ),
				'href'     => $url,
				'priority' => 60,
			]
		);

		$items = [
			'link-genius-overview'     => [
				'title' => esc_html__( 'Overview', 'interlink-genius' ),
				'href'  => $url . '#overview',
			],
			'link-genius-posts'        => [
				'title' => esc_html__( 'Posts', 'interlink-genius' ),
				'href'  => $url . '#posts',
			],
			'link-genius-links'        => [
				'title' => esc_html__( 'Links', 'interlink-genius' ),
				'href'  => $url . '#links',
			],
			'link-genius-bulk-update'  => [
				'title' => esc_html__( 'Bulk Update', 'interlink-genius' ),
				'href'  => $url . '#bulk-update',
			],
			'link-genius-keyword-maps' => [
				'title' => esc_html__( 'Keyword Maps', 'interlink-genius' ),
				'href'  => $url . '#keyword-maps',
			],
		];

		foreach ( $items as $id => $args ) {
			$menu->add_sub_menu( $id, $args, 'link-genius' );
		}
	}
}
