<?php
/**
 * Helper functions for SIRSC integration.
 *
 * @package sirsc
 */

declare( strict_types=1 );

namespace SIRSC\Integration;

// WooCommerce integration hooks.
add_action( 'woocommerce_admin_after_product_gallery_item', __NAMESPACE__ . '\\maybe_show_wc_gallery_buttons', 60, 2 );
add_filter( 'woocommerce_background_image_regeneration', __NAMESPACE__ . '\\maybe_disable_wc_regeneration', 30 );

// EWWW plugin integration hooks.
add_action( 'update_option_ewww_image_optimizer_disable_resizes', __NAMESPACE__ . '\\maybe_sync_sirsc_with_ewww', 10, 3 );
add_action( 'update_option_sirsc_settings', __NAMESPACE__ . '\\maybe_sync_ewww_with_sirsc', 10, 3 );

/**
 * Support for WooCommerce product gallery. Append or display the button for
 * generating the missing image sizes and request individual crop of images.
 *
 * @param integer $post_id      The main post ID.
 * @param integer $thumbnail_id The attachemnt ID.
 * @return void
 */
function maybe_show_wc_gallery_buttons( $post_id = 0, $thumbnail_id = 0 ) { //phpcs:ignore
	$content = str_replace( '&nbsp;', '', \SIRSC\Admin\append_image_generate_button( '', $post_id, $thumbnail_id, 'tiny' ) );
	echo $content; // phpcs:ignore
}

/**
 * Maybe disable the background images processing from WooCommerce.
 *
 * @param  bool $allow Initial value for the filter.
 * @return bool
 */
function maybe_disable_wc_regeneration( bool $allow ) : bool {
	if ( ! empty( \SIRSC::$settings['disable_woo_thregen'] ) ) {
		return false;
	}
	return $allow;
}

/**
 * Sync SIRSC settings with EWWW.
 *
 * @param  mixed  $old_value Old option value.
 * @param  mixed  $value     New option value.
 * @param  string $option    Option name.
 * @return void
 */
function maybe_sync_sirsc_with_ewww( $old_value, $value, $option ) { //phpcs:ignore
	if ( ! empty( \SIRSC::$settings['sync_settings_ewww'] ) ) {
		$val = ( ! empty( $value ) ) ? array_keys( $value ) : [];

		\SIRSC::$settings['complete_global_ignore'] = $val;
		update_option( 'sirsc_settings', \SIRSC::$settings );
	}
}

/**
 * Sync EWWW settings with SIRSC.
 *
 * @param  mixed  $old_value Old option value.
 * @param  mixed  $value     New option value.
 * @param  string $option    Option name.
 * @return void
 */
function maybe_sync_ewww_with_sirsc( $old_value, $value, $option ) { //phpcs:ignore
	if ( ! empty( \SIRSC::$settings['sync_settings_ewww'] ) ) {
		$val  = $value['complete_global_ignore'];
		$list = [];
		if ( ! empty( $val ) ) {
			foreach ( $val as $size ) {
				$list[ $size ] = true;
			}
		}
		update_option( 'ewww_image_optimizer_disable_resizes', $list );
	}
}
