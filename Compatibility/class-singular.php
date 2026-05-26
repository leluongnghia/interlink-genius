<?php
namespace InterLinkGenius\Compatibility;

defined( 'ABSPATH' ) || exit;

class Singular {
	private $post;

	public function set_object( $post ) {
		$this->post = $post;
	}

	public function title() {
		if ( class_exists( 'RankMath\Paper\Singular' ) ) {
			$paper = new \RankMath\Paper\Singular();
			$paper->set_object( $this->post );
			return $paper->title();
		}
		if ( ! empty( $this->post ) ) {
			return $this->post->post_title;
		}
		return '';
	}

	public function description() {
		if ( class_exists( 'RankMath\Paper\Singular' ) ) {
			$paper = new \RankMath\Paper\Singular();
			$paper->set_object( $this->post );
			return $paper->description();
		}
		if ( ! empty( $this->post ) ) {
			if ( ! empty( $this->post->post_excerpt ) ) {
				return wp_strip_all_tags( $this->post->post_excerpt );
			}
			return wp_strip_all_tags( wp_html_excerpt( $this->post->post_content, 160 ) );
		}
		return '';
	}
}
