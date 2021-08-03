<?php
/**
 * Actions functions for SIRSC placeholder.
 *
 * @package sirsc
 */

declare( strict_types=1 );

namespace SIRSC\Action;

/**
 * Compute list of original items from the meta.
 *
 * @param  array $meta Image metadata.
 * @return array
 */
function compute_original_list_from_meta( $meta = [] ) { //phpcs:ignore
	$result = [
		'source'    => '',
		'folder'    => '',
		'path'      => '',
		'originals' => [],
	];

	if ( ! empty( $meta['file'] ) ) {
		$upld = wp_upload_dir();

		$result['folder'] = str_replace( basename( $meta['file'] ), '', $meta['file'] );
		$result['path']   = trailingslashit( trailingslashit( $upld['basedir'] ) . $result['folder'] );

		$result['originals'][] = $meta['file'];
		$result['originals'][] = basename( $meta['file'] );
		if ( ! empty( $meta['original_image'] ) ) {
			$result['originals'][] = trailingslashit( $result['folder'] ) . $meta['original_image'];
		}
	}

	return $result;
}

/**
 * Handle cleanup removable file.
 *
 * @param  int    $id        Attachment ID.
 * @param  string $removable File to be removed.
 * @param  bool   $wpcli   WP-CLI running.
 * @param  bool   $verbose WP-CLI verbose.
 * @return void
 */
function handle_cleanup_removable_file( $id, $removable, $wpcli = false, $verbose = false ) { //phpcs:ignore
	if ( empty( $removable ) || is_dir( $removable ) ) {
		return;
	}

	if ( file_exists( $removable ) ) {
		@unlink( $removable ); //phpcs:ignore

		if ( $wpcli && $verbose ) {
			\WP_CLI::success( $removable . ' ' . esc_html__( 'was removed', 'sirsc' ) );
		}

		// Notify other scripts that the file was deleted.
		do_action( 'sirsc_image_file_deleted', $id, $removable );
	} else {
		$text = $removable . ' <em>' . esc_html__( 'Could not remove', 'sirsc' ) . '. ' . esc_html__( 'The image is missing or it is the original file.', 'sirsc' ) . '</em>';

		if ( $wpcli ) {
			\SIRSC\Debug\bulk_log_write( 'WP-CLI * ' . $text );
			\WP_CLI::line( wp_strip_all_tags( $text ) );
		}
	}
}

/**
 * Cleanup attachment all sizes.
 *
 * @param  int  $id      Attachment ID.
 * @param  bool $wpcli   WP-CLI running.
 * @param  bool $verbose WP-CLI verbose.
 * @return void
 */
function cleanup_attachment_all_sizes( $id, $wpcli = false, $verbose = false ) { //phpcs:ignore
	if ( empty( $id ) ) {
		return;
	}

	$id   = (int) $id;
	$meta = wp_get_attachment_metadata( $id );
	$list = \SIRSC::assess_files_for_attachment_original( $id, $meta );

	$update_metadata = false;
	if ( ! empty( $list['paths']['generated'] ) ) {
		foreach ( $list['paths']['generated'] as $c => $removable ) {
			handle_cleanup_removable_file( $id, $removable, $wpcli, $verbose );
		}
		$update_metadata = true;
	} else {
		if ( ! empty( $meta['sizes'] ) ) {
			$info = compute_original_list_from_meta( $meta );
			foreach ( $meta['sizes'] as $sname => $sinfo ) {
				if ( ! in_array( $sinfo['file'], $info['originals'], true ) ) {
					handle_cleanup_removable_file( $id, $info['path'] . $sinfo['file'], $wpcli, $verbose );
				}
			}
			$update_metadata = true;
		}
	}

	if ( true === $update_metadata ) {
		// Update the cleaned meta.
		$meta['sizes'] = [];
		\wp_update_attachment_metadata( $id, $meta );

		// Re-fetch the meta.
		$image = \wp_get_attachment_metadata( $id );
		\do_action( 'sirsc_attachment_images_ready', $image, $id );
	}
}

/**
 * Cleanup attachment all sizes.
 *
 * @param  int    $id      Attachment ID.
 * @param  string $size    Image size name.
 * @param  bool   $wpcli   WP-CLI running.
 * @param  bool   $verbose WP-CLI verbose.
 * @return void
 */
function cleanup_attachment_one_size( $id, $size, $wpcli = false, $verbose = false ) { //phpcs:ignore
	if ( empty( $id ) || empty( $size ) ) {
		return;
	}

	$id   = (int) $id;
	$meta = wp_get_attachment_metadata( $id );
	$info = compute_original_list_from_meta( $meta );
	$list = wp_json_encode( $meta );
	$list = stripslashes( $list );

	$update_metadata = false;
	if ( ! empty( $meta['sizes'][ $size ] ) ) {
		if ( ! in_array( $meta['sizes'][ $size ]['file'], $info['originals'], true ) ) {
			if ( substr_count( $list, '"file":"' . $meta['sizes'][ $size ]['file'] . '"' ) <= 1 ) {
				handle_cleanup_removable_file( $id, $info['path'] . $meta['sizes'][ $size ]['file'], $wpcli, $verbose );
			}
		}
		unset( $meta['sizes'][ $size ] );
		$update_metadata = true;
	}

	if ( true === $update_metadata ) {
		// Update the cleaned meta.
		\wp_update_attachment_metadata( $id, $meta );

		// Re-fetch the meta.
		$image = \wp_get_attachment_metadata( $id );
		\do_action( 'sirsc_attachment_images_ready', $image, $id );
	}
}

/**
 * Cleanup attachment all sizes.
 *
 * @param  int    $id      Attachment ID.
 * @param  string $size    Image size name.
 * @param  string $fname   Filename.
 * @return void
 */
function cleanup_attachment_one_size_file( $id, $size, $fname = '' ) { //phpcs:ignore
	if ( empty( $id ) || empty( $size ) || empty( $fname ) ) {
		return;
	}
	$update_metadata = false;

	$id   = (int) $id;
	$meta = wp_get_attachment_metadata( $id );
	$info = compute_original_list_from_meta( $meta );
	$list = wp_json_encode( $meta );
	if ( ! in_array( basename( $fname ), $info['originals'], true ) ) {
		handle_cleanup_removable_file( $id, $info['path'] . basename( $fname ) );
	}
	if ( ! empty( $meta['sizes'][ $size ] ) ) {
		unset( $meta['sizes'][ $size ] );
		$update_metadata = true;
	}

	if ( true === $update_metadata ) {
		// Update the cleaned meta.
		\wp_update_attachment_metadata( $id, $meta );

		// Re-fetch the meta.
		$image = \wp_get_attachment_metadata( $id );
		\do_action( 'sirsc_attachment_images_ready', $image, $id );
	}
}
