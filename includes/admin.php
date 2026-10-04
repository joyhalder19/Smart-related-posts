<?php
/** Native settings and shared validation. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function srp_defaults() {
	return array(
		'heading'         => __( 'Related articles', 'smart-related-posts' ),
		'count'           => 3,
		'match'           => 'both',
		'layout'          => 'grid',
		'show_image'      => true,
		'show_excerpt'    => true,
		'excerpt_length'  => 20,
		'post_types'      => array( 'post' ),
		'order'           => 'relevance',
		'accent_color'    => '#175cd3',
		'card_background' => '#ffffff',
		'corner_radius'   => 12,
	);
}

/** Public types only; attachments are never sources or recommendations. */
function srp_public_post_types() {
	$types = get_post_types( array( 'public' => true ), 'objects' );
	unset( $types['attachment'] );
	return $types;
}

/** Null means invalid; false is a valid checkbox value. */
function srp_validate_setting( $key, $value ) {
	if ( 'post_types' === $key ) {
		if ( ! is_array( $value ) ) {
			return null;
		}
		$types = srp_public_post_types();
		foreach ( $value as $type ) {
			if ( ! is_string( $type ) || ( '' !== $type && ! isset( $types[ $type ] ) ) ) {
				return null;
			}
		}
		return array_values( array_unique( array_filter( $value, 'strlen' ) ) );
	}
	if ( ! is_scalar( $value ) ) {
		return null;
	}
	if ( 'heading' === $key ) {
		$text = sanitize_text_field( (string) $value );
		$length = preg_match_all( '/./us', $text );
		return false !== $length && $length <= 120 ? $text : null;
	}
	if ( in_array( $key, array( 'count', 'excerpt_length', 'corner_radius' ), true ) ) {
		$min = 'corner_radius' === $key ? 0 : ( 'count' === $key ? 1 : 5 );
		$max = 'corner_radius' === $key ? 40 : ( 'count' === $key ? 6 : 60 );
		return false !== filter_var( $value, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => $min, 'max_range' => $max ) ) ) && ! is_bool( $value ) ? (int) $value : null;
	}
	if ( 'match' === $key ) {
		return in_array( $value, array( 'categories', 'tags', 'both' ), true ) ? $value : null;
	}
	if ( 'layout' === $key ) {
		return in_array( $value, array( 'grid', 'list' ), true ) ? $value : null;
	}
	if ( 'order' === $key ) {
		return in_array( $value, array( 'relevance', 'random' ), true ) ? $value : null;
	}
	if ( in_array( $key, array( 'accent_color', 'card_background' ), true ) ) {
		return is_string( $value ) && preg_match( '/^#[0-9a-fA-F]{6}$/D', $value ) ? strtolower( $value ) : null;
	}
	if ( in_array( $key, array( 'show_image', 'show_excerpt' ), true ) ) {
		if ( in_array( $value, array( true, 1, '1', 'true' ), true ) ) {
			return true;
		}
		if ( in_array( $value, array( false, 0, '0', 'false' ), true ) ) {
			return false;
		}
	}
	return null;
}

function srp_get_settings() {
	$settings = srp_defaults();
	$saved    = get_option( 'srp_settings', array() );
	foreach ( $settings as $key => $default ) {
		if ( is_array( $saved ) && array_key_exists( $key, $saved ) ) {
			// A disabled type must not enable a different, unselected type.
			if ( 'post_types' === $key && is_array( $saved[ $key ] ) ) {
				$settings[ $key ] = array_values( array_unique( array_intersect( array_filter( $saved[ $key ], 'is_string' ), array_keys( srp_public_post_types() ) ) ) );
				continue;
			}
			$value = srp_validate_setting( $key, $saved[ $key ] );
			if ( null !== $value ) {
				$settings[ $key ] = $value;
			}
		}
	}
	return $settings;
}

function srp_sanitize_settings( $input ) {
	$settings = srp_get_settings();
	if ( ! is_array( $input ) ) {
		add_settings_error( 'srp_settings', 'invalid', __( 'Settings must be submitted using the settings form.', 'smart-related-posts' ) );
		return $settings;
	}
	foreach ( $settings as $key => $previous ) {
		$value = $input[ $key ] ?? null;
		if ( in_array( $key, array( 'show_image', 'show_excerpt' ), true ) && ! array_key_exists( $key, $input ) ) {
			$value = false;
		}
		$valid = srp_validate_setting( $key, $value );
		if ( null === $valid ) {
			/* translators: %s is the setting key. */
			add_settings_error( 'srp_settings', 'invalid_' . $key, sprintf( __( 'Invalid value for %s. The previous value was kept.', 'smart-related-posts' ), $key ) );
		} else {
			$settings[ $key ] = $valid;
		}
	}
	return $settings;
}

function srp_register_settings() {
	register_setting( 'srp_settings_group', 'srp_settings', array( 'type' => 'array', 'sanitize_callback' => 'srp_sanitize_settings', 'default' => srp_defaults() ) );
	add_settings_section( 'srp_display', '', '__return_false', 'smart-related-posts' );
	$labels = array(
		'heading' => __( 'Section heading', 'smart-related-posts' ),
		'count' => __( 'Number of posts', 'smart-related-posts' ),
		'match' => __( 'Match using', 'smart-related-posts' ),
		'layout' => __( 'Layout', 'smart-related-posts' ),
		'show_image' => __( 'Show image', 'smart-related-posts' ),
		'show_excerpt' => __( 'Show excerpt', 'smart-related-posts' ),
		'excerpt_length' => __( 'Excerpt length (words)', 'smart-related-posts' ),
		'post_types' => __( 'Eligible post types', 'smart-related-posts' ),
		'order' => __( 'Order', 'smart-related-posts' ),
		'accent_color' => __( 'Accent color', 'smart-related-posts' ),
		'card_background' => __( 'Card background color', 'smart-related-posts' ),
		'corner_radius' => __( 'Corner radius (px)', 'smart-related-posts' ),
	);
	foreach ( $labels as $key => $label ) {
		add_settings_field( $key, $label, 'srp_settings_field', 'smart-related-posts', 'srp_display', array( 'key' => $key, 'label_for' => 'srp-' . $key ) );
	}
}

function srp_settings_field( $args ) {
	$key = $args['key'];
	$value = srp_get_settings()[ $key ];
	$id = 'srp-' . $key;
	$name = 'srp_settings[' . $key . ']';
	$choices = array(
		'match' => array( 'both' => __( 'Categories and tags', 'smart-related-posts' ), 'categories' => __( 'Categories', 'smart-related-posts' ), 'tags' => __( 'Tags', 'smart-related-posts' ) ),
		'layout' => array( 'grid' => __( 'Grid', 'smart-related-posts' ), 'list' => __( 'List', 'smart-related-posts' ) ),
		'order' => array( 'relevance' => __( 'Relevance', 'smart-related-posts' ), 'random' => __( 'Random', 'smart-related-posts' ) ),
	);
	if ( 'post_types' === $key ) {
		echo '<input type="hidden" name="' . esc_attr( $name ) . '[]" value="">';
		echo '<select multiple id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '[]" aria-describedby="srp-post-types-help">';
		foreach ( srp_public_post_types() as $type => $object ) {
			echo '<option value="' . esc_attr( $type ) . '" ' . selected( in_array( $type, $value, true ), true, false ) . '>' . esc_html( $object->labels->name ) . '</option>';
		}
		echo '</select><p class="description" id="srp-post-types-help">' . esc_html__( 'Applies to sources and recommendations. Hold Ctrl (Windows) or Command (Mac) to select multiple types. Types must use categories or tags to match. Selecting none disables recommendations.', 'smart-related-posts' ) . '</p>';
	} elseif ( isset( $choices[ $key ] ) ) {
		echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
		foreach ( $choices[ $key ] as $option => $label ) {
			echo '<option value="' . esc_attr( $option ) . '" ' . selected( $value, $option, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
	} elseif ( is_bool( $value ) ) {
		echo '<input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1" ' . checked( $value, true, false ) . '>';
	} elseif ( 'heading' === $key ) {
		echo '<input type="text" class="regular-text" maxlength="120" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">';
	} elseif ( in_array( $key, array( 'accent_color', 'card_background' ), true ) ) {
		echo '<input type="color" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">';
		if ( 'accent_color' === $key ) {
			echo '<p class="description">' . esc_html__( 'Keyboard focus outline. Choose a color visible against the surrounding page.', 'smart-related-posts' ) . '</p>';
		}
	} else {
		$min = 'corner_radius' === $key ? 0 : ( 'count' === $key ? 1 : 5 );
		$max = 'corner_radius' === $key ? 40 : ( 'count' === $key ? 6 : 60 );
		echo '<input type="number" min="' . esc_attr( $min ) . '" max="' . esc_attr( $max ) . '" step="1" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">';
	}
}

function srp_admin_menu() {
	add_options_page( __( 'Smart Related Posts', 'smart-related-posts' ), __( 'Smart Related Posts', 'smart-related-posts' ), 'manage_options', 'smart-related-posts', 'srp_settings_page' );
}

function srp_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Smart Related Posts', 'smart-related-posts' ); ?></h1>
		<p><?php esc_html_e( 'Recommend published articles using shared categories (2 points each) and tags (3 points each). The default category is ignored.', 'smart-related-posts' ); ?></p>
		<form action="options.php" method="post">
			<?php settings_fields( 'srp_settings_group' ); ?>
			<?php do_settings_sections( 'smart-related-posts' ); ?>
			<?php submit_button(); ?>
		</form>
		<h2><?php esc_html_e( 'Shortcode usage', 'smart-related-posts' ); ?></h2>
		<p><?php esc_html_e( 'Add a Shortcode block to eligible content, or specify an eligible source with post_id. No matches means no output.', 'smart-related-posts' ); ?></p>
		<p><code>[related_posts]</code></p>
		<p><code>[related_posts count="4" layout="list" match="tags"]</code></p>
		<p><code>[srp_related_posts post_id="123" show_excerpt="false"]</code></p>
	</div>
	<?php
}
