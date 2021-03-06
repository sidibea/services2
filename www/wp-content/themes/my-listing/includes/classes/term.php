<?php

namespace CASE27\Classes;

class Term {
	private $data;

	public function __construct( \WP_Term $term ) {
		$this->data = $term;
	}

	public function get_id() {
		return $this->data->term_id;
	}

	public function get_name() {
		return $this->data->name;
	}

	public function get_slug() {
		return $this->data->slug;
	}

	public function get_full_name( $object = null, $name = null ) {
		if ( ! $object ) {
			$object = $this->data;
		}

		if ( ! $name ) {
			$name = $object->name;
		}

		if ( $object->parent && ( $parent = get_term( $object->parent, $object->taxonomy ) ) ) {
			return $this->get_full_name( $parent, "{$parent->name} &#9656; {$name}" );
		}

		return $name;
	}

	public function get_data( $key = null ) {
		if ( $key ) {
			return isset( $this->data->$key ) ? $this->data->$key : false;
		}

		return $this->data;
	}

	public function is_active() {
		return isset( $this->active ) && $this->active;
	}

	public function get_icon() {
		if ( $icon = $this->get_recursive_field( 'icon' ) ) {
			return $icon;
		}

		if ( $this->data->taxonomy == 'region' ) {
			$default_icon = 'icon-location-pin-4';
		} else {
			$default_icon = 'mi bookmark_border';
		}

		return apply_filters( 'case27\classes\term\default_icon', $default_icon );
	}

	public function get_image() {
		if ( $image = $this->get_recursive_field( 'image' ) ) {
			return $image;
		}

		return apply_filters( 'case27\classes\term\default_image', '' );
	}

	public function get_color() {
		if ( $color = $this->get_recursive_field( 'color' ) ) {
			return $color;
		}

		return apply_filters( 'case27\classes\term\default_color', c27()->get_setting( 'general_brand_color', '#f24286' ) );
	}

	public function get_text_color() {
		if ( $text_color = $this->get_recursive_field( 'text_color' ) ) {
			return $text_color;
		}

		return apply_filters( 'case27\classes\term\default_text_color', '#fff' );
	}

	public function get_link() {
		return get_term_link( $this->data );
	}

	public function get_count() {
		if ( ! $count = get_term_meta( $this->data->term_id, 'listings_full_count', true ) ) {
			$count = $this->data->count;
		}

		if ( $count ) {
			return sprintf(
				_n( '%s listing', '%s listings', $count, 'my-listing' ),
				number_format_i18n( $count )
			);
		}

		return __( 'No listings', 'my-listing' );
	}


	/*
	 * Check if field value exists for this term.
	 * If not, recursively check the term parent, until a value is found.
	 * Otherwise, return false.
	 */
	public function get_recursive_field( $field_name, $term = null ) {
		if ( ! $term ) {
			$term = $this->data;
		}

		if ( $field = get_field( $field_name, $term->taxonomy . '_' . $term->term_id ) ) {
			return $field;
		}

		if ( $term->parent && ( $parent = get_term( $term->parent, $term->taxonomy ) ) ) {
			return $this->get_recursive_field( $field_name, $parent );
		}

		return false;
	}

	public static function get_term_tree( $terms = [], $parent = 0 ) {
		$result = [];

		foreach( $terms as $term ) {
			if ( $parent == $term->parent ) {
				$term->children = self::get_term_tree( $terms, $term->term_id );
				$result[] = $term;
			}
		}

		return $result;
	}

	public static function iterate_recursively( $callback, $terms, $depth = 0 ) {
		$depth++;
		foreach ( $terms as $term ) {
			$callback( $term, $depth );

			if ( ! empty( $term->children ) && is_array( $term->children ) ) {
				self::iterate_recursively( $callback, $term->children, $depth );
			}
		}
	}
}

add_action( 'edited_term_taxonomy', function( $term, $tax ) {
	if ( in_array( $tax, [ 'job_listing_category', 'case27_job_listing_tags', 'region' ] ) ) {
		$query = new \WP_Query([
			'posts_per_page' => -1,
			'post_type' => 'job_listing',
			'post_status' => 'publish',
			'tax_query' => [[
				'taxonomy' => $tax,
				'field' => 'id',
				'terms' => $term,
			]],
			'fields' => 'ids',
			'no_found_rows' => true,
		]);

		update_term_meta( $term, 'listings_full_count', $query->post_count );
		update_option( 'listings_tax_' . $tax . '_version', time() );
	}
}, 50, 2 );