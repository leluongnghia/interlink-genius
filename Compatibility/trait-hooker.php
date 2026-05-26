<?php
namespace InterLinkGenius\Compatibility;

defined( 'ABSPATH' ) || exit;

trait Hooker {
	public function action( $hook, $method, $priority = 10, $accepted_args = 1 ) {
		add_action( $hook, [ $this, $method ], $priority, $accepted_args );
	}

	public function filter( $hook, $method, $priority = 10, $accepted_args = 1 ) {
		add_filter( $hook, [ $this, $method ], $priority, $accepted_args );
	}

	public function remove_action( $hook, $method, $priority = 10 ) {
		remove_action( $hook, [ $this, $method ], $priority );
	}

	public function remove_filter( $hook, $method, $priority = 10 ) {
		remove_filter( $hook, [ $this, $method ], $priority );
	}
}
