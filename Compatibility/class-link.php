<?php
namespace InterLinkGenius\Compatibility;

defined( 'ABSPATH' ) || exit;

class Link {
	private $url;
	private $target_post_id;
	private $type;

	public $anchor_text;
	public $is_nofollow;
	public $url_hash;
	public $created_at;
	public $anchor_type;
	public $target_blank;

	public function __construct( $url, $target_post_id, $type ) {
		$this->url            = $url;
		$this->target_post_id = $target_post_id;
		$this->type           = $type;
	}

	public function get_url() {
		return $this->url;
	}

	public function get_target_post_id() {
		return $this->target_post_id;
	}

	public function get_type() {
		return $this->type;
	}
}
