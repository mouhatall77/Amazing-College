<?php
/**
 * Iterator handlers for SIRSC actions.
 *
 * @package sirsc
 */

declare( strict_types=1 );

namespace SIRSC\Iterator;

add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\load_assets', 20 );
add_action( 'sirsc/iterator/setup_buttons', __NAMESPACE__ . '\\setup_buttons', 10, 2 );

/**
 * Prepare iterator button.
 *
 * @param  array $button Button settings.
 * @return array
 */
function prepare_button( array $button ) : array {
	$name    = wp_rand( 1000, 9000 );
	$default = [
		'name'       => $name,
		'icons'      => '',
		'text'       => __( 'Button', 'sirsc' ) . ' ' . $name,
		'callback'   => '',
		'attributes' => [],
		'buttons'    => [],
		'class'      => '',
	];

	$button = wp_parse_args( $button, $default );
	return $button;
}

/**
 * Setup buttons.
 *
 * @param  string $source Button source.
 * @param  array  $list   List of variations.
 * @return void
 */
function setup_buttons( string $source, array $list ) {
	$option = get_option( 'sirsc-iterator-buttons', [] );
	foreach ( $list as $b_name => $button ) {
		$name = esc_attr( $source . '-' . $b_name );
		$hash = md5( $name . wp_json_encode( $button ) );
		if ( ! isset( $option[ $name ] )
			|| ( ! empty( $option[ $name ]['hash'] ) && $hash !== $option[ $name ]['hash'] ) ) {
			$b    = prepare_button( $button );
			$b_id = button_id( $b['text'], $b['callback'] );

			$option[ $name ] = [
				'id'   => $b_id,
				'hash' => $hash,
				'text' => generate_button( $b, true ),
				'attr' => $b,
			];
		}
	}
	update_option( 'sirsc-iterator-buttons', $option );
}

/**
 * Button display.
 *
 * @param  string $name Button name.
 * @return void
 */
function button_display( string $name ) {
	$option = get_option( 'sirsc-iterator-buttons', [] );
	if ( ! empty( $option[ $name ] ) ) {
		echo $option[ $name ]['text']; //phpcs:ignore
	}
}

/**
 * Button callback.
 *
 * @param  string $name   Button name.
 * @param  string $action Button action.
 * @return string
 */
function button_callback( string $name, string $action ) : string {
	$option = get_option( 'sirsc-iterator-buttons' );
	if ( ! empty( $option[ $name ] ) ) {
		return 'sirscIterator( \'' . $option[ $name ]['id'] . '\', \'' . $action . '\', \'' . $option[ $name ]['attr']['callback'] . '\' );';
	}
}

/**
 * Enqueue the css and javascript files
 */
function load_assets() {
	$uri = $_SERVER['REQUEST_URI']; //phpcs:ignore

	if ( ! substr_count( $uri, 'post.php' ) && ! substr_count( $uri, 'upload.php' ) && ! substr_count( $uri, 'admin.php?page=image-regenerate-select-crop-settings' ) && ! substr_count( $uri, 'admin.php?page=sirsc-' ) && ! substr_count( $uri, 'options-media.php' ) ) {
		// Fail-fast, the assets should not be loaded.
		return;
	}

	if ( file_exists( SIRSC_PLUGIN_DIR . 'build/iterator.js' ) ) {
		wp_register_script(
			'sirsc-iterator',
			SIRSC_PLUGIN_URL . 'build/iterator.js',
			[],
			filemtime( SIRSC_PLUGIN_DIR . 'build/iterator.js' ),
			false
		);
		wp_localize_script(
			'sirsc-iterator',
			'sirscIteratorSettings',
			[
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'delay'   => 500,
				'verify'  => wp_create_nonce( 'sirsc-iterator-ajax' ),
			]
		);
		wp_enqueue_script( 'sirsc-iterator' );
	}
}

/**
 * Is valid call.
 *
 * @return bool
 */
function is_valid_ajax() : bool {
	return check_ajax_referer( 'sirsc-iterator-ajax', 'verify' );
}

/**
 * Generate a button id.
 *
 * @param  string $text     Text on the button.
 * @param  string $callback Button callback.
 * @return string
 */
function button_id( string $text, string $callback = '' ) : string {
	return md5( $text . $callback );
}

/**
 * Generate an iterator button.
 * Custom additional buttons types (ex: [ 'stop', 'resume', 'cancel', 'finish' ] ).
 *
 * @param  array $attr   Custom attributes list.
 * @param  void  $return True to return insted of output.
 * @return void|string
 */
function generate_button( array $attr, bool $return = false ) { //phpcs:ignore
	if ( true === $return ) {
		ob_start();
	}

	$text       = $attr['text'];
	$icon       = $attr['icon'];
	$callback   = $attr['callback'];
	$attributes = $attr['attributes'];
	$buttons    = $attr['buttons'];
	$class      = $attr['class'];
	$id         = button_id( $text, $callback );
	?>
	<span id="<?php echo esc_attr( $id ); ?>"
		class="sirsc-iterator-wrap button button-primary sirsc-button-icon <?php echo esc_attr( $class ); ?>"
		data-callback="<?php echo esc_js( stripslashes( $callback ) ); ?>"
		<?php
		if ( ! empty( $attributes ) ) {
			foreach ( $attributes as $key => $value ) {
				?>
				<?php echo esc_attr( $key ); ?>="<?php echo esc_attr( $value ); ?>"
				<?php
			}
		}
		?>
		>
		<?php echo wp_kses_post( $icon ); ?>
		<span id="<?php echo esc_attr( $id ); ?>-start"
			title="<?php esc_attr_e( 'Start', 'sirsc' ); ?>"
			class="sirsc-iterator sirsc-iterator-start">
			<?php echo wp_kses_post( $text ); ?>
		</span>

		<?php if ( in_array( 'stop', $buttons, true ) ) : ?>
			<span id="<?php echo esc_attr( $id ); ?>-stop"
				title="<?php esc_attr_e( 'Stop', 'sirsc' ); ?>"
				class="dashicons dashicons-controls-pause sirsc-iterator sirsc-iterator-stop hidden"></span>
		<?php endif; ?>

		<?php if ( in_array( 'resume', $buttons, true ) ) : ?>
			<span id="<?php echo esc_attr( $id ); ?>-resume"
				title="<?php esc_attr_e( 'Resume', 'sirsc' ); ?>"
				class="dashicons dashicons-controls-play sirsc-iterator sirsc-iterator-resume hidden"></span>
		<?php endif; ?>

		<?php if ( in_array( 'cancel', $buttons, true ) ) : ?>
			<span id="<?php echo esc_attr( $id ); ?>-cancel"
				title="<?php esc_attr_e( 'Cancel', 'sirsc' ); ?>"
				class="dashicons dashicons-dismiss sirsc-iterator sirsc-iterator-cancel hidden"></span>
		<?php endif; ?>

		<?php if ( in_array( 'finish', $buttons, true ) ) : ?>
			<span id="<?php echo esc_attr( $id ); ?>-finish"
				title="<?php esc_attr_e( 'Finish', 'sirsc' ); ?>"
				class="dashicons dashicons-yes-alt sirsc-iterator sirsc-iterator-finish hidden"></span>
		<?php endif; ?>
	</span>

	<?php
	if ( true === $return ) {
		return ob_get_clean();
	}
}
