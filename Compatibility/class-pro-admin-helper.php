<?php
namespace InterLinkGenius\Compatibility;

defined( 'ABSPATH' ) || exit;

class PRO_Admin_Helper {
	public static function get_taxonomy_terms( $taxonomy, $selected = [], $search = '' ) {
		$args = [
			'taxonomy' => $taxonomy,
			'search'   => $search,
			'fields'   => 'id=>name',
			'number'   => 10,
		];

		if ( ! empty( $selected ) ) {
			$args['include'] = $selected;
			unset( $args['number'] );
		}

		$terms = get_terms( $args );
		if ( empty( $terms ) ) {
			return [];
		}

		$data = [];
		foreach ( $terms as $id => $name ) {
			$data[] = [
				'value' => $id,
				'name'  => $name,
			];
		}

		return $data;
	}

	public static function get_exclude_terms_for_settings( $setting_prefix, $excluded_post_types = [], $settings_section = 'general' ) {
		$all_post_types = get_post_types( [ 'public' => true ], 'names' );
		$post_types     = array_diff( $all_post_types, $excluded_post_types, [ 'attachment' ] );

		if ( empty( $post_types ) ) {
			return [];
		}

		$exclude_terms = [];
		foreach ( $post_types as $post_type ) {
			$taxonomies = Helper::get_object_taxonomies( $post_type, 'objects' );
			if ( empty( $taxonomies ) ) {
				continue;
			}

			$terms = Helper::get_settings( "{$settings_section}.{$setting_prefix}_exclude_{$post_type}_terms", [] );

			$post_type_obj   = get_post_type_object( $post_type );
			if ( ! $post_type_obj ) {
				continue;
			}

			foreach ( $taxonomies as $taxonomy => $data ) {
				if ( empty( $data->show_ui ) ) {
					continue;
				}

				$selected = [];
				if ( isset( $terms[ $taxonomy ] ) ) {
					$selected = $terms[ $taxonomy ];
				}

				if ( isset( $terms[0] ) && isset( $terms[0][ $taxonomy ] ) ) {
					$selected = $terms[0][ $taxonomy ];
				}

				$taxonomy_terms = self::get_taxonomy_terms( $taxonomy, $selected );
				if ( empty( $taxonomy_terms ) ) {
					continue;
				}

				$exclude_terms[ $post_type ][ $taxonomy ] = $taxonomy_terms;
			}
		}

		return ! empty( $exclude_terms ) ? $exclude_terms : (object) [];
	}
}
