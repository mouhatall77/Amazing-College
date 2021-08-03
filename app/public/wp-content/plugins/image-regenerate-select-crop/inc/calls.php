<?php
/**
 * AJAX handlers for SIRSC actions.
 *
 * @package sirsc
 */

declare( strict_types=1 );

namespace SIRSC\AJAX;

add_action( 'wp_ajax_sirsc_single_details', __NAMESPACE__ . '\\single_details' );
add_action( 'wp_ajax_sirsc_single_cleanup', __NAMESPACE__ . '\\single_cleanup' );
add_action( 'wp_ajax_sirsc_single_regenerate', __NAMESPACE__ . '\\single_regenerate' );
add_action( 'wp_ajax_sirsc_crop_position', __NAMESPACE__ . '\\single_regenerate' );
add_action( 'wp_ajax_sirsc_start_delete', __NAMESPACE__ . '\\delete_image_size' );
add_action( 'wp_ajax_sirsc_refresh_summary', __NAMESPACE__ . '\\refresh_summary' );
add_action( 'wp_ajax_sirsc_start_delete_file', __NAMESPACE__ . '\\delete_image_file' );
add_action( 'wp_ajax_sirsc_show_image_size_info', __NAMESPACE__ . '\\get_image_single_size_info' );
add_action( 'wp_ajax_sirsc_start_regenerate_size', __NAMESPACE__ . '\\bulk_regenerate' );
add_action( 'wp_ajax_sirsc_start_cleanup_size', __NAMESPACE__ . '\\bulk_cleanup' );
add_action( 'wp_ajax_sirsc_start_raw_cleanup', __NAMESPACE__ . '\\bulk_raw_cleanup' );
add_action( 'wp_ajax_sirsc_refresh_log', __NAMESPACE__ . '\\refresh_log' );
add_action( 'wp_ajax_sirsc_reset_log', __NAMESPACE__ . '\\reset_log' );
add_action( 'wp_ajax_sirsc_cancel_cron_task', __NAMESPACE__ . '\\cancel_cron_task' );
add_action( 'wp_ajax_sirsc_refresh_placeholder', __NAMESPACE__ . '\\refresh_placeholder' );

/**
 * End the AJAX request.
 *
 * @return void
 */
function sirsc_call_end() {
	wp_die();
	die();
}

/**
 * AJAX handler for regenerating all the files for an image size.
 */
function bulk_regenerate() {
	$start = filter_input( INPUT_GET, 'start', FILTER_DEFAULT );
	$size  = filter_input( INPUT_GET, 'size', FILTER_DEFAULT );
	$cpt   = filter_input( INPUT_GET, 'cpt', FILTER_DEFAULT );
	if ( ! empty( $size ) ) {
		if ( ! empty( \SIRSC::$use_cron ) ) {
			\SIRSC\Cron\assess_task( 'regenerate_image_sizes_on_request', [
				'size' => $size,
				'cpt'  => (string) $cpt,
			] );
		} else {
			\SIRSC\Helper\regenerate_image_sizes_on_request( $start, $size, $cpt );
		}
	}
	sirsc_call_end();
}

/**
 * AJAX handler for canceling the cron tasks.
 */
function cancel_cron_task() {
	$hook = filter_input( INPUT_GET, 'hook', FILTER_DEFAULT );
	if ( ! empty( $hook ) ) {
		\SIRSC\Cron\cancel_task( $hook );
	}
	sirsc_call_end();
}

/**
 * AJAX handler for cleanup all the files for an image size.
 */
function bulk_cleanup() {
	$start = filter_input( INPUT_GET, 'start', FILTER_DEFAULT );
	$size  = filter_input( INPUT_GET, 'size', FILTER_DEFAULT );
	$cpt   = filter_input( INPUT_GET, 'cpt', FILTER_DEFAULT );
	if ( ! empty( $size ) ) {
		if ( ! empty( \SIRSC::$use_cron ) ) {
			\SIRSC\Cron\assess_task( 'cleanup_image_sizes_on_request', [
				'size' => $size,
				'cpt'  => (string) $cpt,
			] );
		} else {
			\SIRSC\Helper\cleanup_image_sizes_on_request( $start, $size, $cpt );
		}
	}
	sirsc_call_end();
}

/**
 * AJAX handler for cleanup all the files for an image size.
 */
function bulk_raw_cleanup() {
	$start = filter_input( INPUT_GET, 'start', FILTER_DEFAULT );
	$type  = filter_input( INPUT_GET, 'type', FILTER_DEFAULT );
	$cpt   = filter_input( INPUT_GET, 'cpt', FILTER_DEFAULT );
	if ( ! empty( $type ) ) {
		if ( ! empty( \SIRSC::$use_cron ) ) {
			\SIRSC\Cron\assess_task( 'raw_cleanup_on_request', [
				'type' => $type,
				'cpt'  => (string) $cpt,
			] );
		} else {
			\SIRSC\Helper\raw_cleanup_on_request( $start, $type, $cpt );
		}
	}
	sirsc_call_end();
}

/**
 * AJAX handler for showing all image size for an attachment.
 */
function single_details() {
	$id = filter_input( INPUT_GET, 'post-id', FILTER_VALIDATE_INT );
	if ( ! empty( $id ) ) {
		\SIRSC\Helper\attachment_sizes_lightbox( $id );
	}
	sirsc_call_end();
}

/**
 * AJAX handler for raw cleanup of all image sizes for an attachment.
 */
function single_cleanup() {
	$id = filter_input( INPUT_GET, 'post-id', FILTER_VALIDATE_INT );
	if ( ! empty( $id ) ) {
		\SIRSC\Helper\single_attachment_raw_cleanup( $id );
		echo \SIRSC\Helper\make_buttons( $id, true ); //phpcs:ignore
		echo \SIRSC\Helper\document_ready_js( 'sirscRefreshSummary( \'' . $id . '\' );' ); //phpcs:ignore
	}
	sirsc_call_end();
}

/**
 * AJAX handler for regeneration of an image size (or all) for an attachment.
 */
function single_regenerate() {
	$id      = filter_input( INPUT_GET, 'post-id', FILTER_VALIDATE_INT );
	$pos     = filter_input( INPUT_GET, 'position', FILTER_DEFAULT );
	$size    = filter_input( INPUT_GET, 'size', FILTER_DEFAULT );
	$quality = filter_input( INPUT_GET, 'quality', FILTER_DEFAULT );
	$count   = filter_input( INPUT_GET, 'count', FILTER_VALIDATE_INT );
	if ( ! empty( $id ) ) {
		\SIRSC\Helper\process_image_sizes_on_request( $id, $size, $pos, $quality );
		if ( 'all' === $size ) {
			echo \SIRSC\Helper\make_buttons( $id, true ); //phpcs:ignore
			echo \SIRSC\Helper\document_ready_js( 'sirscRefreshSummary( \'' . $id . '\' );' ); //phpcs:ignore
		} else {
			\SIRSC\Helper\show_image_single_size_info( $id, $size, '', [], $count );
			echo \SIRSC\Helper\document_ready_js( 'sirscRefreshSrc( \'' . $id . '\', \'' . $size . '\' ); sirscRefreshSummary( \'' . $id . '\' );' ); //phpcs:ignore
		}
	}
	sirsc_call_end();
}

/**
 * AJAX handler for showing all image size for an attachment.
 *
 * @return void
 */
function refresh_summary() {
	$id   = filter_input( INPUT_GET, 'post-id', FILTER_VALIDATE_INT );
	$wrap = filter_input( INPUT_GET, 'wrap', FILTER_DEFAULT );
	if ( ! empty( $id ) ) {
		if ( ! empty( $wrap ) ) {
			\SIRSC\Helper\attachment_listing_summary( $id, [], $wrap );
		} else {
			\SIRSC\Helper\attachment_summary( $id );
		}
	}
	sirsc_call_end();
}

/**
 * AJAX handler for deleting an image size for an attachment.
 *
 * @return void
 */
function delete_image_size() {
	$id    = filter_input( INPUT_GET, 'post-id', FILTER_VALIDATE_INT );
	$size  = filter_input( INPUT_GET, 'size', FILTER_DEFAULT );
	$count = filter_input( INPUT_GET, 'count', FILTER_VALIDATE_INT );
	if ( ! empty( $id ) ) {
		\SIRSC\Helper\delete_image_sizes_on_request( $id, $size );
		\SIRSC\Helper\show_image_single_size_info( $id, $size, '', [], $count );
		echo \SIRSC\Helper\document_ready_js( 'sirscRefreshSummary( \'' . $id . '\' );' ); //phpcs:ignore
	}
	sirsc_call_end();
}

/**
 * AJAX handler for deleting an image size for an attachment.
 *
 * @return void
 */
function delete_image_file() {
	$id       = filter_input( INPUT_GET, 'post-id', FILTER_VALIDATE_INT );
	$size     = filter_input( INPUT_GET, 'size', FILTER_DEFAULT );
	$filename = filter_input( INPUT_GET, 'filename', FILTER_DEFAULT );
	$wrap     = filter_input( INPUT_GET, 'wrap', FILTER_DEFAULT );
	$count    = filter_input( INPUT_GET, 'count', FILTER_VALIDATE_INT );
	if ( ! empty( $id ) ) {
		\SIRSC\Helper\delete_image_file_on_request( $id, $filename, $size, $wrap );
		if ( substr_count( $size, ',' ) ) {
			echo \SIRSC\Helper\document_ready_js( 'sirscExecuteGetRequest( \'action=sirsc_single_details&post-id=' . $id . '\', \'sirsc-lightbox\' );' ); //phpcs:ignore
		} else {
			\SIRSC\Helper\show_image_single_size_info( $id, $size, '', [], $count );
			echo \SIRSC\Helper\document_ready_js( 'sirscRefreshSummary( \'' . $id . '\', \'' . $wrap . '\' );' ); //phpcs:ignore
		}
	}
	sirsc_call_end();
}

/**
 * AJAX handler for showing an image size for an attachment.
 *
 * @return void
 */
function get_image_single_size_info() {
	$id    = filter_input( INPUT_GET, 'post-id', FILTER_VALIDATE_INT );
	$size  = filter_input( INPUT_GET, 'size', FILTER_DEFAULT );
	$count = filter_input( INPUT_GET, 'count', FILTER_VALIDATE_INT );
	if ( ! empty( $id ) && ! empty( $size ) ) {
		\SIRSC\Helper\show_image_single_size_info( $id, $size, '', [], $count );
	}
	sirsc_call_end();
}

/**
 * AJAX handler for real time logs view.
 *
 * @return void
 */
function refresh_log() {
	$type = filter_input( INPUT_GET, 'type', FILTER_DEFAULT );
	if ( ! empty( $type ) ) {
		echo wp_kses_post( '<ol>' . \SIRSC\Debug\log_read( $type ) . '<ol>' );
	}
	sirsc_call_end();
}

/**
 * AJAX handler for resetting the logs.
 *
 * @return void
 */
function reset_log() {
	$type = filter_input( INPUT_GET, 'type', FILTER_DEFAULT );
	if ( ! empty( $type ) ) {
		\SIRSC\Debug\log_delete( $type );
	}
	sirsc_call_end();
}

/**
 * Refresh image size placeholder.
 *
 * @return void
 */
function refresh_placeholder() {
	$size = filter_input( INPUT_GET, 'size', FILTER_DEFAULT );
	if ( ! empty( $size ) ) {
		\SIRSC\Helper\placeholder_preview( $size, true );
	}

	sirsc_call_end();
}
