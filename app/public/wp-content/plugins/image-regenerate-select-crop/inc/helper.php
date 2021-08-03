<?php
/**
 * Helper functions for SIRSC.
 *
 * @package sirsc
 */

declare( strict_types=1 );

namespace SIRSC\Helper;

const SIRSC_ICON_CROP    = '<div class="dashicons dashicons-image-crop"></div>';
const SIRSC_ICON_SCALE   = '<div class="dashicons dashicons-editor-expand"></div>';
const SIRSC_ICON_LINK    = '<div class="dashicons dashicons-admin-links"></div>';
const SIRSC_ICON_DETAILS = '<div class="dashicons dashicons-format-gallery"></div>';
const SIRSC_ICON_REFRESH = '<div class="dashicons dashicons-update"></div>';
const SIRSC_ICON_CLEANUP = '<div class="dashicons dashicons-editor-removeformatting"></div>';
const SIRSC_ICON_SUCCESS = '<div class="dashicons dashicons-yes-alt"></div>';
const SIRSC_ICON_ERROR   = '<div class="dashicons dashicons-dismiss"></div>';
const SIRSC_ICON_INFO    = '<div class="dashicons dashicons-info"></div>';

/**
 * Maybe indicate to other scrips/threads that SIRSC is processing.
 *
 * @param string $extra Maybe extra hints.
 */
function notify_doing_sirsc( $extra = '' ) { //phpcs:ignore
	if ( ! defined( 'DOING_SIRSC' ) ) {
		define( 'DOING_SIRSC', true );
		do_action( 'sirsc_doing_sirsc', $extra );
	}
}

/**
 * Load the post type settings if available.
 *
 * @param string|array|object $ob   The item to be exposed.
 * @param boolean             $time Show microtime.
 * @param boolean             $log  Write to error log.
 */
function debug( $ob = '', $time = true, $log = false ) { // phpcs:ignore
	if ( ! empty( \SIRSC::$settings['enable_debug_log'] ) ) {
		\SIRSC\Debug\tracer_log_write( $ob );
		return;
	}

	if ( true === \SIRSC::$debug && ! empty( $ob ) ) {
		$debug  = PHP_EOL . ( true === $time ) ? '---' . microtime( true ) . PHP_EOL : '';
		$debug .= ( ! is_scalar( $ob ) ) ? print_r( $ob, 1 ) : $ob; //phpcs:ignore
		\SIRSC\Debug\main_log_write( $debug );
		if ( true === $log ) {
			error_log( str_replace( PHP_EOL, ' ', $debug ) ); //phpcs:ignore
		}
	}
}

/**
 * Return hmain readable files size.
 *
 * @param  integer $bytes    Bytes.
 * @param  integer $decimals Decimals.
 * @return string
 */
function human_filesize( $bytes, $decimals = 2 ) { //phpcs:ignore
	if ( empty( $bytes ) ) {
		return esc_html__( 'N/A', 'sirsc' );
	}
	$sz = 'KMGTP';
	$fa = floor( ( strlen( (string) $bytes ) - 1 ) / 3 );
	return sprintf( "%.{$decimals}f&nbsp;", $bytes / pow( 1024, $fa ) ) . ( ( 0 == $fa ) ? 'B' : @$sz[ $fa - 1 ] . 'B' ); //phpcs:ignore
}

/**
 * Return a custom callback after AJAX actions.
 *
 * @param  string  $extra Content.
 * @param  boolean $delay Delay or not.
 * @return string
 */
function document_ready_js( $extra, $delay = false ) { //phpcs:ignore
	return '<div id="sirsc-data-callback' . ( true === $delay ? '-delay' : '' ) . '" data-response="' . esc_js( stripslashes( $extra ) ) . '"></div>';
}

/**
 * Show single image size info.
 *
 * @param  int    $id       Attachment ID.
 * @param  string $size     Image size slug.
 * @param  string $filename Filename.
 * @param  array  $all_size All sizes list.
 * @param  int    $count    Item count.
 * @return void
 */
function show_image_single_size_info( $id, $size, $filename = '', $all_size = [], $count = 0 ) { //phpcs:ignore
	global $good;
	if ( empty( $id ) ) {
		// Fail-fast.
		return;
	}

	$id   = (int) $id;
	$post = get_post( $id );
	if ( empty( $post ) ) {
		// Fail-fast.
		return;
	}

	\SIRSC::load_settings_for_post_id( $id );
	if ( empty( $filename ) ) {
		$filename = get_attached_file( $id );
	}
	if ( empty( $filename ) ) {
		// Fail-fast.
		return;
	}

	if ( empty( $all_size ) ) {
		$all_size = \SIRSC::get_all_image_sizes_plugin();
	}

	if ( empty( $all_size[ $size ] ) || empty( $size ) || 'unknown' === $size || substr_count( $size, ',' ) ) {
		// Fail-fast.
		return;
	}

	$upldir   = wp_upload_dir();
	$computed = compute_image_details( $id, $size, $upldir, $all_size );
	$image    = $computed->metadata;
	$size     = $computed->assessed_size;
	$settings = \SIRSC::$settings;
	$idd      = $id . $size;
	$info     = $all_size[ $size ];

	$size_quality = ( empty( $settings['default_quality'][ $size ] ) )
		? \SIRSC::DEFAULT_QUALITY
		: (int) $settings['default_quality'][ $size ];

	$action     = '';
	$title      = '';
	$ratio      = ( $computed->info->is_crop ) ? 'crop' : 'scale';
	$extra_text = '';

	if ( $computed->info->is_native_crop_type ) {
		$title = SIRSC_ICON_CROP . ' ' . esc_html__( 'Crop image', 'sirsc' );
	} else {
		$title = SIRSC_ICON_SCALE . ' ' . esc_html__( 'Scale image', 'sirsc' );
	}

	$maybelink = '';
	if ( $computed->info->is_found ) {
		$im        = '<span class="image-wrap" id="idsrc' . $idd . '"><img src="' . $computed->size->url . '?v=' . time() . '" border="0" loading="lazy" /></span>';
		$maybelink = '<a href="' . $computed->size->url . '?v=' . time() . '" target="_blank" class="f-right">' . SIRSC_ICON_LINK . '</a>';
		$good[]    = $computed->size->url;
	} else {
		$im = '<span class="image-wrap empty" id="idsrc' . $idd . '">' . esc_html__( 'NOT FOUND', 'sirsc' ) . '</span>';
	}

	if ( $computed->info->is_crop ) {
		if ( $computed->info->can_be_cropped ) {
			$title   = SIRSC_ICON_CROP . ' ' . esc_html__( 'Crop image', 'sirsc' );
			$action .= make_generate_images_crop( $id, $size );
		} else {
			$title = '<div class="dashicons dashicons-image-crop disabled"></div> ' . esc_html__( 'Crop image', 'sirsc' );
		}
	}

	$regenerate_button = '<a class="sirsc-regenerate-size f-right" onclick="sirscSingleRegenerateSize(\'' . $id . '\', \'' . $size . '\');" title="' . esc_attr__( 'Regenerate', 'sirsc' ) . '"><b class="dashicons dashicons-update"></b></a>';

	$regenerate_input = '<div class="sirsc-small-info-secondary rows no-shadow quality"><div class="span3">' . esc_html__( 'Custom quality', 'sirsc' ) . '</div></div><div class="sirsc-small-info-secondary rows no-top no-shadow quality"><div class="span2"><input type="number" name="selected_quality" id="selected_quality' . $idd . '" value="' . $size_quality . '" class="sirsc-size-quality"></div><div>' . $regenerate_button . '</div></div>';

	if ( $computed->info->can_be_generated ) {
		if ( $computed->info->is_native_crop_type ) {
			$title = SIRSC_ICON_CROP . ' ' . esc_html__( 'Crop image', 'sirsc' );
		} else {
			$title = SIRSC_ICON_SCALE . ' ' . esc_html__( 'Scale image', 'sirsc' );
		}

		$extra_text = ( $computed->info->must_scale_up ) ? ' <em>(' . __( 'must scale up to generate the expected size', 'sirsc' ) . ')</em>' : '';
		$action    .= $regenerate_input;
	} else {
		if ( $computed->info->is_native_crop_type ) {
			$title = '<div class="dashicons dashicons-image-crop disabled"></div> ' . esc_html__( 'Crop image', 'sirsc' );
		} else {
			$title = '<div class="dashicons dashicons-editor-expand disabled"></div> ' . esc_html__( 'Scale image', 'sirsc' );
		}

		$action .= '<table class="wp-list-table widefat fixed"><tr><td class="sirsc-small-info sirsc-message warning">' . esc_html__( 'The width and height of the original image are smaller than the requested image size.', 'sirsc' ) . '</td></tr></table>';

		if ( $computed->info->can_be_generated ) {
			if ( $computed->info->is_crop ) {
				$action .= make_generate_images_crop( $id, $size );
			}

			$action .= $regenerate_input;
			$action  = '<div class="sirsc-message warning">' . $action . '</div>';
		}
	}

	$del = '';
	if ( ! empty( $computed->size->filesize ) ) {
		if ( $computed->info->is_original ) {
			// The size is the not the original file.
			$del = '<a class="sirsc-delete-size f-right" onclick="sirscStartDelete(\'' . $id . '\', \'' . $size . '\');" title="' . esc_attr__( 'Cleanup the metadata', 'sirsc' ) . '"><b class="dashicons dashicons-dismiss"></b></a>';

			$action = '<table class="wp-list-table widefat fixed"><tr><td class="sirsc-small-info sirsc-message warning">' . esc_html__( 'This image size shares the same file as the full size (the original image) and it cannot be altered.', 'sirsc' ) . '</td></tr></table>';
		} elseif ( $computed->info->can_be_deleted ) {
			// The size is the not the original file.
			$del = '<a class="sirsc-delete-size f-right" onclick="sirscStartDelete(\'' . $id . '\', \'' . $size . '\');" title="' . esc_attr__( 'Delete', 'sirsc' ) . '"><b class="dashicons dashicons-trash"></b></a>';
		}
	}
	?>
	<div class="sirsc-size infobox">
		<div class="rows bg-secondary no-top no-shadow">
			<div class="first text-icon">
				<?php echo $maybelink; //phpcs:ignore ?>
				<b title="<?php echo esc_attr( $size ); ?>"><?php echo esc_attr( $size ); ?></b>

			</div>
			<div class="second icon-text">
				<?php echo $title; //phpcs:ignore ?>
			</div>
		</div>
		<div class="rows no-top no-shadow bg-white">
			<div class="first">
				<span class="sirsc-small-info">
					<?php esc_html_e( 'Info', 'sirsc' ); ?>:
					<b><?php echo size_to_text( $info ); //phpcs:ignore ?></b>
					<?php echo $extra_text; //phpcs:ignore ?>
				</span>
				<div id="sirsc-size-details-<?php echo esc_attr( $idd ); ?>"
					class="sirsc-size-details <?php echo esc_attr( $ratio ); ?>">
					<?php echo $im; //phpcs:ignore ?>
					<?php if ( ! empty( $computed->size->resolution ) ) : ?>
						<div><?php esc_html_e( 'Resolution', 'sirsc' ); ?>:
							<?php echo $computed->size->resolution; //phpcs:ignore ?>
						</div>
						<span class="image-size-column">
							<?php esc_html_e( 'File size', 'sirsc' ); ?>:
							<b class="image-file-size"><?php echo $computed->size->filesize_text; //phpcs:ignore ?></b>
						</span>
					<?php endif; ?>
				</div>
			</div>
			<div class="second bg-neutral">
				<div class="sirsc-small-info-secondary rows no-shadow">
					<div class="span2">
						<?php esc_html_e( 'Default quality', 'sirsc' ); ?>:
						<b><?php echo $size_quality; //phpcs:ignore ?></b>
					</div>
					<div><?php echo $del; //phpcs:ignore ?></div>
				</div>
				<?php echo $action; //phpcs:ignore ?>
			</div>
		</div>
	</div>
	<?php
}

/**
 * Compute image paths.
 *
 * @param  integer $id        Attachment ID.
 * @param  string  $size      Size name.
 * @param  array   $image     Maybe image size metadata.
 * @param  array   $uplinfo   Maybe upload paths details.
 * @param  array   $all_sizes Maybe all sized pre computed.
 * @return array
 */
function assess_expected_image( $id, $size, $image = [], $uplinfo = [], $all_sizes = [] ) { //phpcs:ignore
	if ( empty( $uplinfo ) ) {
		$uplinfo = wp_upload_dir();
	}
	if ( empty( $image ) ) {
		$image = wp_get_attachment_metadata( $id );
	}

	$result = [
		'file'   => '',
		'dir'    => '',
		'path'   => '',
		'url'    => '',
		'exists' => false,
		'meta'   => [],
	];

	if ( empty( $image ) ) {
		return $result;
	}

	if ( empty( $all_sizes ) ) {
		$all_sizes = \SIRSC::get_all_image_sizes_plugin();
	}

	$filename = get_attached_file( $id );
	$source   = str_replace( trailingslashit( $uplinfo['basedir'] ), '', $filename );
	if ( substr_count( $filename, '-scaled.' ) ) {
		$filename2 = str_replace( '-scaled.', '.', $filename );
		if ( file_exists( $filename2 ) ) {
			$filename = $filename2;
			$source   = str_replace( trailingslashit( $uplinfo['basedir'] ), '', $filename2 );
		}
	}

	// Compute the expected if the size does not exist.
	if ( ! empty( $size ) && ! empty( $all_sizes[ $size ] ) ) {
		$maybe = \image_resize_dimensions(
			(int) $image['width'],
			(int) $image['height'],
			$all_sizes[ $size ]['width'],
			$all_sizes[ $size ]['height'],
			$all_sizes[ $size ]['crop']
		);

		$suffix   = ( ! empty( $maybe ) ) ? '-' . $maybe[4] . 'x' . $maybe[5] : '';
		$ext      = '.' . strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		$expected = str_replace( $ext, $suffix . $ext, $source );

		$result = [
			'file'   => $expected,
			'dir'    => trailingslashit( dirname( $source ) ),
			'path'   => trailingslashit( $uplinfo['basedir'] ) . $expected,
			'url'    => trailingslashit( $uplinfo['baseurl'] ) . $expected,
			'exists' => false,
			'meta'   => [],
		];

		if ( ! empty( $result['path'] ) && file_exists( $result['path'] ) && ! is_dir( $result['path'] ) ) {
			$result['exists'] = true;
			$filetype         = \wp_check_filetype( $result['path'] );
			$result['meta']   = [
				'file'      => basename( $result['path'] ),
				'width'     => ( ! empty( $maybe[4] ) ) ? (int) $maybe[4] : (int) $image['width'],
				'height'    => ( ! empty( $maybe[5] ) ) ? (int) $maybe[5] : (int) $image['height'],
				'mime-type' => $filetype['type'],
			];
		}
	}

	return $result;
}

/**
 * Compute image size info.
 *
 * @param  integer $id        Attachment ID.
 * @param  string  $size      Size name.
 * @param  array   $uplinfo   Maybe upload paths details.
 * @param  array   $all_sizes Maybe precomputed sizes.
 * @param  bool    $bulk      Info about bulk processing.
 * @return array
 */
function compute_image_details( $id, $size, $uplinfo = [], $all_sizes = [], $bulk = false ) { //phpcs:ignore
	$result = [
		'id'            => $id,
		'image_size'    => $size,
		'assessed_size' => $size,
		'source'        => [
			'exists'        => false,
			'file'          => '',
			'dir'           => '',
			'path'          => '',
			'url'           => '',
			'name'          => '',
			'width'         => 0,
			'height'        => 0,
			'filesize'      => 0,
			'filesize_text' => '',
			'resolution'    => '',
		],
		'size'          => [
			'exists'        => false,
			'file'          => '',
			'dir'           => '',
			'path'          => '',
			'url'           => '',
			'name'          => '',
			'width'         => 0,
			'height'        => 0,
			'filesize'      => 0,
			'filesize_text' => '',
			'resolution'    => '',
		],
		'info'          => [
			'expected'             => [
				'exists'    => false,
				'file'      => '',
				'dir'       => '',
				'path'      => '',
				'url'       => '',
				'name'      => '',
				'width'     => 0,
				'height'    => 0,
				'mime_type' => '',
			],
			'is_found'             => false,
			'is_original'          => false,
			'is_crop'              => false,
			'is_resize'            => false,
			'is_identical_size'    => false,
			'is_proportional_size' => false,
			'is_native_crop_type'  => false,
			'is_featured'          => false,
			'must_scale_up'        => false,
			'can_be_generated'     => false,
			'can_be_cropped'       => false,
			'can_be_deleted'       => false,
			'bulk_skip_regenerate' => false,
			'bulk_skip_cleanup'    => false,
			'bulk_regenerate'      => false,
			'bulk_cleanup'         => false,
			'bulk_regenerate_text' => '',
			'bulk_cleanup_text'    => '',
		],
		'metadata'      => [],
	];

	$result = json_decode( wp_json_encode( $result ), false );

	if ( empty( $uplinfo ) ) {
		$uplinfo = wp_upload_dir();
	}
	if ( empty( $all_sizes ) ) {
		$all_sizes = \SIRSC::get_all_image_sizes(); // Prev get all image sizes plugin.
	}

	$filename = get_attached_file( $id );
	$source   = str_replace( trailingslashit( $uplinfo['basedir'] ), '', $filename );
	$metadata = wp_get_attachment_metadata( $id );

	if ( empty( $metadata ) && ! empty( $source ) && function_exists( 'wp_create_image_subsizes' ) ) {
		notify_doing_sirsc();
		debug( 'The image metadata is empty and the source exists ' . $filename . '. Force regenerate for attachment ' . $id . '.', true, true );
		wp_create_image_subsizes( $filename, $id );
		$metadata = attempt_to_create_metadata( $id, $filename );
		$metadata = wp_get_attachment_metadata( $id );
	}

	$result->metadata = $metadata;

	// Compute the original/default source file.
	$orig         = $result->source;
	$orig->name   = $source;
	$orig->file   = basename( $source );
	$orig->dir    = str_replace( $orig->file, '', $orig->name );
	$orig->path   = $uplinfo['basedir'] . '/' . $orig->name;
	$orig->url    = $uplinfo['baseurl'] . '/' . $orig->name;
	$orig->exists = ( ! empty( $orig->name ) ) ? file_exists( $orig->path ) : false;
	$orig->width  = $metadata['width'];
	$orig->height = $metadata['height'];
	if ( true === $orig->exists ) {
		$orig->filesize      = @filesize( $orig->path ); //phpcs:ignore
		$orig->filesize_text = human_filesize( $orig->filesize );
		$orig->resolution    = ( isset( $metadata['width'] ) ) ? '<b>' . $metadata['width'] . '</b>x<b>' . $metadata['height'] . '</b>px' : '';
	}
	$result->source = $orig;

	$file = '';
	if ( ! empty( $size ) ) {
		$sizes = substr_count( $size, ',' ) ? explode( ',', str_replace( ' ', '', $size ) ) : [];
		if ( 'original' === $size || 'full' === $size
			|| in_array( 'original', $sizes, true )
			|| in_array( 'full', $sizes, true ) ) {
			// Original or full.
			$size = 'full';
			$file = $result->source->file;
		} else {
			if ( count( $sizes ) ) {
				$size = reset( $sizes );
			}
			if ( 'full' === $size || 'original' === $size ) {
				$size = 'full';
				$file = $result->source->file;
			} else {
				if ( ! empty( $metadata['sizes'][ $size ]['file'] ) ) {
					$file = $metadata['sizes'][ $size ]['file'];
				}
			}
		}
	}

	$result->assessed_size = $size;

	// Initialte the temp.
	$temp        = clone $result->size;
	$temp->file  = $file;
	$is_original = ( $result->source->file === $temp->file ) ? true : false;

	if ( true === $is_original ) {
		$temp = clone $result->source;
	} else {
		$temp->dir = $result->source->dir;
		if ( ! empty( $temp->file ) ) {
			$temp->name   = $temp->dir . $temp->file;
			$temp->path   = $uplinfo['basedir'] . '/' . $temp->name;
			$temp->url    = $uplinfo['baseurl'] . '/' . $temp->name;
			$temp->exists = ( ! empty( $temp->name ) ) ? file_exists( $temp->path ) : false;
		}
		if ( true === $temp->exists ) {
			$temp->filesize      = @filesize( $temp->path ); //phpcs:ignore
			$temp->filesize_text = human_filesize( $temp->filesize );
			$temp->resolution    = ( isset( $metadata['sizes'][ $size ]['width'] ) ) ? '<b>' . $metadata['sizes'][ $size ]['width'] . '</b>x<b>' . $metadata['sizes'][ $size ]['height'] . '</b>px' : '';
		}
	}

	$allowed  = allow_resize_from_original( $filename, $metadata, $all_sizes, $size );
	$expected = assess_expected_image( $id, $size, $metadata, $uplinfo, $all_sizes );

	if ( true !== $is_original ) {
		$temp->width  = $allowed['width'];
		$temp->height = $allowed['height'];
	}
	$result->size = $temp;

	// Initialte the info.
	$info = $result->info;

	$info->is_original          = $is_original;
	$info->is_found             = ! empty( $allowed['found'] );
	$info->is_crop              = ! empty( $allowed['is_crop'] );
	$info->is_resize            = ! empty( $allowed['is_resize'] );
	$info->is_identical_size    = ! empty( $allowed['is_identical_size'] );
	$info->is_proportional_size = ! empty( $allowed['is_proportional_size'] );
	$info->is_native_crop_type  = ! empty( $allowed['native_crop_type'] );
	$info->must_scale_up        = ! empty( $allowed['must_scale_up'] );
	$info->can_be_generated     = ! empty( $allowed['can_be_generated'] );
	$info->can_be_cropped       = ! empty( $allowed['can_be_cropped'] );
	$info->can_be_deleted       = ( ! $info->is_original && $result->size->exists && $info->is_found );
	$info->expected->exists     = ! empty( $expected['exists'] );
	$info->expected->file       = basename( $expected['file'] );
	$info->expected->dir        = $expected['dir'];
	$info->expected->path       = $expected['path'];
	$info->expected->url        = $expected['url'];
	$info->expected->name       = $expected['file'];
	$info->expected->width      = ! empty( $expected['meta']['width'] ) ? $expected['meta']['width'] : 0;
	$info->expected->height     = ! empty( $expected['meta']['height'] ) ? $expected['meta']['height'] : 0;
	$info->expected->mime_type  = ! empty( $expected['meta']['mime-type'] ) ? $expected['meta']['mime-type'] : '';

	if ( $info->can_be_generated ) {
		if ( ! empty( \SIRSC::$settings['regenerate_missing'] ) ) {
			if ( $info->expected->exists ) {
				$info->bulk_skip_regenerate = true;
				$info->bulk_regenerate_text = '<span>' . $info->expected->name . '</span> <em>' . __( 'Skipping the regeneration of this file, the file already exists (as per settings).', 'sirsc' ) . '</em>';
			}
		}
	}

	if ( $info->can_be_deleted ) {
		$info->bulk_cleanup = true;
	} else {
		$info->bulk_skip_cleanup = true;
	}

	$result->info = $info;

	return $result;
}

/**
 * Attempts to create metadata from file if that exists for an id.
 *
 * @param integer $id       Attachment post id.
 * @param string  $filename Maybe a filename.
 * @return array
 */
function attempt_to_create_metadata( $id, $filename = '' ) { //phpcs:ignore
	notify_doing_sirsc();
	if ( empty( $filename ) ) {
		$fname = get_attached_file( $id );
	} else {
		$fname = $filename;
	}
	$image_meta = [];
	if ( ! empty( $fname ) && file_exists( $fname ) ) {
		$image_size = @getimagesize( $fname ); //phpcs:ignore
		$image_meta = [
			'width'          => ! empty( $image_size[0] ) ? (int) $image_size[0] : 0,
			'height'         => ! empty( $image_size[1] ) ? (int) $image_size[1] : 0,
			'file'           => _wp_relative_upload_path( $fname ),
			'path'           => _wp_relative_upload_path( $fname ),
			'sizes'          => [],
			'original_image' => wp_basename( $fname ),
		];

		$exif_meta = wp_read_image_metadata( $fname );
		if ( $exif_meta ) {
			$image_meta['image_meta'] = $exif_meta;
		}

		wp_update_attachment_metadata( $id, $image_meta );
	}
	return $image_meta;
}

/**
 * Return the details about an image size for an image.
 *
 * @param string  $filename  The file name.
 * @param array   $image     The image attributes.
 * @param string  $all_sizes The image size slug.
 * @param integer $size      The selected image size.
 */
function allow_resize_from_original( $filename, $image, $all_sizes = [], $size = '' ) { //phpcs:ignore
	if ( empty( $all_sizes ) ) {
		$all_sizes = \SIRSC::get_all_image_sizes_plugin();
	}

	$result = [
		'found'                => 0,
		'is_crop'              => 0,
		'is_identical_size'    => 0,
		'is_resize'            => 0,
		'is_proportional_size' => 0,
		'width'                => 0,
		'height'               => 0,
		'path'                 => '',
		'url'                  => '',
		'can_be_cropped'       => 0,
		'can_be_generated'     => 0,
		'must_scale_up'        => 0,
		'native_crop_type'     => ( ! empty( $all_sizes[ $size ]['crop'] ) ? true : false ),
	];

	$original_w = ( ! empty( $image['width'] ) ) ? $image['width'] : 0;
	$original_h = ( ! empty( $image['height'] ) ) ? $image['height'] : 0;

	$w = ( ! empty( $all_sizes[ $size ]['width'] ) ) ? intval( $all_sizes[ $size ]['width'] ) : 0;
	$h = ( ! empty( $all_sizes[ $size ]['height'] ) ) ? intval( $all_sizes[ $size ]['height'] ) : 0;
	$c = ( ! empty( $all_sizes[ $size ]['crop'] ) ) ? $all_sizes[ $size ]['crop'] : false;

	if ( empty( $image['sizes'][ $size ]['file'] ) ) {
		// Not generated probably.
		if ( ! empty( $c ) ) {
			if ( $original_w >= $w && $original_h >= $h ) {
				$result['can_be_generated'] = 1;
			} else {
				if ( $original_w >= $w || $original_h >= $h ) {
					// At least one size seems big enough to scale up.
					if ( ! empty( \SIRSC::$settings['enable_perfect'] ) && ! empty( \SIRSC::$settings['enable_upscale'] ) ) {
						$result['can_be_generated'] = 1;
						$result['can_be_cropped']   = 1;
						$result['must_scale_up']    = 1;
					}
				}
			}
		} else {
			if ( ( 0 === $w && $original_h >= $h ) || ( 0 === $h && $original_w >= $w )
				|| ( 0 !== $w && 0 !== $h && ( $original_w >= $w || $original_h >= $h ) ) ) {
				$result['can_be_generated'] = 1;
			}
		}
	} else {
		$file = str_replace( basename( $filename ), $image['sizes'][ $size ]['file'], $filename );
		if ( file_exists( $file ) ) {
			$c_image_size     = getimagesize( $file );
			$ciw              = intval( $c_image_size[0] );
			$cih              = intval( $c_image_size[1] );
			$result['found']  = 1;
			$result['width']  = $ciw;
			$result['height'] = $cih;
			$result['path']   = $file;

			if ( $ciw === $w && $cih === $h ) {
				$result['is_identical_size'] = 1;
				$result['can_be_cropped']    = 1;
				$result['can_be_generated']  = 1;
			}

			if ( ! empty( $c ) ) {
				$result['is_crop'] = 1;
				if ( $original_w >= $w && $original_h >= $h ) {
					$result['can_be_cropped']   = 1;
					$result['can_be_generated'] = 1;
				} elseif ( $original_w >= $w || $original_h >= $h ) {
					// At least one size seems big enough to scale up.
					if ( ! empty( \SIRSC::$settings['enable_perfect'] ) && ! empty( \SIRSC::$settings['enable_upscale'] ) ) {
						$result['can_be_generated'] = 1;
						$result['can_be_cropped']   = 1;
						$result['must_scale_up']    = 1;
					}
				}
			} else {
				$result['is_resize'] = 1;
				if ( ( 0 === $w && $cih === $h ) || ( $ciw === $w && 0 === $h ) ) {
					$result['is_proportional_size'] = 1;
					$result['can_be_generated']     = 1;
				} elseif ( 0 !== $w && 0 !== $h && ( $ciw === $w || $cih === $h ) ) {
					$result['is_proportional_size'] = 1;
					$result['can_be_generated']     = 1;
				}
				if ( $original_w >= $w && $original_h >= $h ) {
					$result['can_be_generated'] = 1;
				}
			}
		} else {
			// To do the not exists but size exists.
			if ( ! empty( $c ) ) {
				if ( $original_w >= $w && $original_h >= $h ) {
					$result['can_be_generated'] = 1;
				} elseif ( $original_w >= $w || $original_h >= $h ) {
					// At least one size seems big enough to scale up.
					$result['can_be_generated'] = 1;
					$result['can_be_cropped']   = 1;
					$result['must_scale_up']    = 1;
				}
			} else {
				if ( ( 0 === $w && $original_h >= $h ) || ( 0 === $h && $original_w >= $w )
					|| ( 0 !== $w && 0 !== $h && ( $original_w >= $w || $original_h >= $h ) ) ) {
					$result['can_be_generated'] = 1;
				}
			}
		}
	}

	return $result;
}

/**
 * Delete the image sizes for a specified image size.
 *
 * @param  int    $id   Attachment ID.
 * @param  string $size Image size slug.
 * @return void
 */
function delete_image_sizes_on_request( $id, $size ) { //phpcs:ignore
	if ( ! empty( $id ) && ! empty( $size ) ) {
		$image = wp_get_attachment_metadata( $id );
		\SIRSC::execute_specified_attachment_file_delete( $id, $size, '', $image );
		do_action( 'sirsc_action_after_image_delete', $id );
	}
}

/**
 * Delete image file on request handler.
 *
 * @param  int    $id   Attachment ID.
 * @param  string $file Maybe a file path.
 * @param  string $size Image sizes list.
 * @param  string $wrap Element wrap is present or not.
 * @return void
 */
function delete_image_file_on_request( $id, $file, $size, $wrap = '' ) { //phpcs:ignore
	if ( empty( $id ) ) {
		// Fail-fast.
		return;
	}
	if ( ! empty( $id ) && ! empty( $size ) ) {
		$image      = wp_get_attachment_metadata( $id );
		$registered = \SIRSC::size_is_registered( $size );

		if ( ! empty( $size ) && substr_count( $size, ',' ) ) {
			$s = explode( ',', $size );
			foreach ( $s as $_size ) {
				$res = \SIRSC::execute_specified_attachment_file_delete( $id, $_size, $file, $image );
				if ( ! empty( $wrap ) && true === $res ) {
					if ( true === $registered ) {
						echo document_ready_js( 'sirscShowImageSizeInfo( \'' . esc_attr( $id ) . '\', \'' . esc_attr( $_size ) . '\' ); ' ); //phpcs:ignore
					} else {
						echo document_ready_js( 'sirscSingleDetails( \'' . esc_attr( $id ) . '\' ); sirscRefreshSummary( \'' . esc_attr( $id ) . '\' ); ' ); //phpcs:ignore
					}
				}
			}
			return;
		} else {
			$res = \SIRSC::execute_specified_attachment_file_delete( $id, $size, $file, $image );
			if ( true === $res ) {
				if ( true === $registered ) {
					echo document_ready_js( 'sirscShowImageSizeInfo( \'' . esc_attr( $id ) . '\', \'' . esc_attr( $size ) . '\' ); ' ); //phpcs:ignore
				} else {
					echo document_ready_js( 'sirscSingleDetails( \'' . esc_attr( $id ) . '\' ); sirscRefreshSummary( \'' . esc_attr( $id ) . '\' ); ' ); //phpcs:ignore
				}
			}
			return;
		}

		// Notify other scripts that the file was deleted.
		do_action( 'sirsc_image_file_deleted', $id, $file );

		if ( ! empty( $twra ) ) {
			if ( true === $res ) {
				if ( true === $registered ) {
					echo document_ready_js( 'sirscShowImageSizeInfo( \'' . esc_attr( $id ) . '\', \'' . esc_attr( $size ) . '\' ); ' ); //phpcs:ignore
				} else {
					echo document_ready_js( 'sirscSingleDetails( \'' . esc_attr( $id ) . '\' ); sirscRefreshSummary( \'' . esc_attr( $id ) . '\' ); ' ); //phpcs:ignore
				}
			} else {
				echo document_ready_js( 'sirscSingleDetails( \'' . esc_attr( $id ) . '\' ); sirscRefreshSummary( \'' . esc_attr( $id ) . '\' ); ' ); //phpcs:ignore
			}
		}
	}
}

/**
 * Regenerate the image sizes for a specified image.
 *
 * @param  integer $id       The attachment ID.
 * @param  string  $size     The image size.
 * @param  string  $position Maybe a specific crop position.
 * @param  integer $quality  Perhaps a quality.
 * @return void
 */
function process_image_sizes_on_request( $id, $size, $position, $quality ) { //phpcs:ignore
	if ( ! empty( $id ) ) {
		$post = get_post( $id );
		if ( ! empty( $post ) ) {
			notify_doing_sirsc();
			$size = ( ! empty( $size ) ) ? $size : 'all';
			$crop = ( ! empty( $position ) ) ? $position : '';
			$qual = ( ! empty( $quality ) ) ? $quality : 0;
			expose_image_after_processing( $id, $size, true, $crop, $qual );
			do_action( 'sirsc_action_after_image_delete', $id );
		}
	} else {
		response_message( __( 'Something went wrong!', 'sirsc' ), 'error' );
	}
}

/**
 * Expose image after processing.
 *
 * @param  integer $id       The attachment ID.
 * @param  string  $size     The image size.
 * @param  boolean $generate True if the size should be regenerated.
 * @param  string  $position Maybe a specific crop position.
 * @param  integer $quality  Perhaps a quality.
 * @return void
 */
function expose_image_after_processing( $id, $size, $generate = false, $position = '', $quality = 0 ) { //phpcs:ignore
	if ( empty( $id ) ) {
		// Fail-fast.
		response_message( __( 'Something went wrong!', 'sirsc' ), 'error' );
		return;
	}

	$id    = (int) $id;
	$sizes = ( ! empty( $size ) ) ? trim( $size ) : 'all';
	if ( true === $generate ) {
		\SIRSC::load_settings_for_post_id( $id );
		debug( 'AJAX - Processing regenerate for ' . $id . '|' . $sizes, true, true );
		make_images_if_not_exists( $id, $sizes, $position, $quality );
	}

	$image = wp_get_attachment_metadata( $id );
	clean_post_cache( $id );

	if ( 'all' !== $sizes ) {
		do_action( 'sirsc_image_processed', $id, $sizes );
		do_action( 'sirsc_attachment_images_ready', $image, $id );
	} else {
		do_action( 'sirsc_attachment_images_processed', $image, $id );
		do_action( 'sirsc_attachment_images_ready', $image, $id );
	}
	response_message( __( 'Done!', 'sirsc' ), 'success' );
}

/**
 * Create the image for a specified attachment and image size if that does not exist and update the image metadata. This is useful for example in the cases when the server configuration does not permit to generate many images from a single uploaded image (timeouts or image sizes defined after images have been uploaded already). This should be called before the actual call of wp_get_attachment_image_src with a specified image size
 *
 * @param integer $id            Id of the attachment.
 * @param array   $selected_size The set of defined image sizes used by the site.
 * @param array   $small_crop    The position of a potential crop (lt = left/top, lc = left/center, etc.).
 * @param integer $force_quality Maybe force a specific custom quality, not the default.
 */
function make_images_if_not_exists( $id, $selected_size = 'all', $small_crop = '', $force_quality = 0 ) { //phpcs:ignore
	try {
		notify_doing_sirsc();
		debug( 'MAKE IMAGE ' . $id . '|' . $selected_size . '|' . $small_crop . '|' . $force_quality, true, true );
		if ( 'all' === $selected_size ) {
			$allowed_sizes = \SIRSC::get_all_image_sizes_plugin( '', true );
			if ( ! empty( $allowed_sizes ) ) {
				foreach ( $allowed_sizes as $size_name => $size_info ) {
					\SIRSC::process_single_size_from_file( $id, $size_name, $size_info, $small_crop, $force_quality );
				}
			}
		} else {
			$res = \SIRSC::process_single_size_from_file( $id, $selected_size, [], $small_crop, $force_quality );
			if ( 'error-too-small' === $res ) {
				return $res;
			}
		}
	} catch ( ErrorException $e ) {
		error_log( 'sirsc exception ' . print_r( $e, 1 ) ); //phpcs:ignore
	}
}

/**
 * Compute all generated like images.
 *
 * @param  integer $id      Attachment ID.
 * @param  array   $image   Maybe metadata.
 * @param  array   $compute Maybe extra computed info.
 * @param  array   $good    Maybe a list of good images.
 * @return void
 */
function attachment_summary( $id, $image = [], $compute = [], $good = [] ) { //phpcs:ignore
	if ( empty( $id ) ) {
		// Fail-fast.
		return;
	}

	if ( is_array( $id ) ) {
		$id = ( ! empty( $id['id'] ) ) ? (int) $id['id'] : 0;
	}
	if ( empty( $id ) ) {
		// Fail-fast.
		return;
	}

	$all_sizes  = \SIRSC::get_all_image_sizes_plugin();
	$upload_dir = wp_upload_dir();
	if ( empty( $compute ) ) {
		$compute = compute_image_details( $id, '', $upload_dir, $all_sizes );
	}

	if ( empty( $image ) && ! empty( $compute->info->metadata ) ) {
		$image = $compute->info->metadata;
	}
	if ( empty( $image ) ) {
		$image = wp_get_attachment_metadata( $id );
		if ( empty( $image ) ) {
			$filename = get_attached_file( $id );
			$image    = \SIRSC::attempt_to_create_metadata( $id, $filename );
		}
	}

	$summary = \SIRSC::general_sizes_and_files_match( $id, $image, $compute );
	$count   = 0;
	?>
	<div class="rows bg-secondary">
		<div class="summary-intro">
			<?php esc_html_e( 'You can see below some additional information about the files generated, recorded in the database, also files left behind from image sizes that are no longer registered in your site, or other image processing (but not linked in the database anymore, hence, probably no used anymore).', 'sirsc' ); ?>
		</div>
		<table width="100%" cellpadding="0" cellpadding="0" class="striped fixed sirsc-small-info-table">
			<?php foreach ( $summary as $k => $v ) : ?>
				<?php
				$trid  = 'trsirsc-' . intval( $id ) . md5( '-' . $k . '-' . $v['size'] );
				$fsize = ( empty( $v['fsize'] ) || 'N/A' === $v['filesize'] )
					? '<span class="missing-file">' . __( 'The file is missing!', 'sirsc' ) . '</span>'
					: '';
				$hint  = ( ! empty( $fsize ) ) ? ' missing-file' : '';
				$delt  = ( ! empty( $fsize ) ) ? __( 'Cleanup the metadata', 'sirsc' ) : __( 'Delete', 'sirsc' );
				?>
				<tr id="<?php echo esc_attr( $trid ); ?>" class="<?php echo esc_attr( $hint ); ?>">
					<td width="60" align="center">
						<span class="dashicons <?php echo esc_attr( $v['icon'] ); ?>"></span>
						<?php echo intval( ++ $count ); ?>.
					</td>
					<td width="65"><?php echo esc_attr( $v['hint'] ); ?></td>
					<td>
						<b><?php echo esc_attr( $k ); ?></b>
						<br><?php echo esc_attr( str_replace( ',', ', ', $v['size'] ) ); ?> <?php echo wp_kses_post( $fsize ); ?>
						<div id="<?php echo esc_attr( $trid ); ?>_rez"></div>
					</td>
					<td width="65" align="right">
						<?php esc_html_e( 'file size', 'sirsc' ); ?>
						<br><?php echo esc_attr( $v['filesize'] ); ?>
					</td>
					<td width="40" align="center">
						<?php if ( ! substr_count( $v['icon'], 'is-full' ) && ! substr_count( $v['icon'], 'is-original' ) ) : ?>
							<a onclick="sirscStartDeleteFile('<?php echo (int) $id; ?>', '<?php echo esc_attr( $k ); ?>', '<?php echo esc_attr( $v['size'] ); ?>', '<?php echo esc_attr( $trid ); ?>');" title="<?php echo esc_attr( $delt ); ?>"><b class="dashicons dashicons-trash"></b></a>
						<?php else : ?>
							<b class="dashicons dashicons-trash"></b>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
	</div>
	<?php
}

/**
 * Compute the images generated summary for a specified attachment.
 *
 * @param  integer $id    The attachment ID.
 * @param  array   $image Maybe an attachment metadata array.
 * @param  string  $wrap  The element wrap is present or not.
 * @return void
 */
function attachment_listing_summary( $id, $image = [], $wrap = '' ) { //phpcs:ignore
	if ( empty( $id ) ) {
		return;
	}

	$use_wrapper = true;
	if ( ! empty( $wrap ) ) {
		$use_wrapper = false;
	}

	if ( empty( $id ) ) {
		// Fail-fast.
		return;
	}

	if ( is_array( $id ) ) {
		$id = ( ! empty( $id['id'] ) ) ? (int) $id['id'] : 0;
	}
	if ( empty( $id ) ) {
		// Fail-fast.
		return;
	}

	$all_sizes  = \SIRSC::get_all_image_sizes_plugin();
	$upload_dir = wp_upload_dir();
	if ( empty( $compute ) ) {
		$compute = compute_image_details( $id, '', $upload_dir, $all_sizes );
	}

	if ( empty( $image ) && ! empty( $compute->info->metadata ) ) {
		$image = $compute->info->metadata;
	}
	if ( empty( $image ) ) {
		$image = wp_get_attachment_metadata( $id );
		if ( empty( $image ) ) {
			$filename = get_attached_file( $id );
			$image    = \SIRSC::attempt_to_create_metadata( $id, $filename );
		}
	}

	$summary = \SIRSC::general_sizes_and_files_match( $id, $image, $compute );
	$count   = 0;

	if ( $use_wrapper ) {
		?>
		<div id="sirsc-column-summary-<?php echo (int) $id; ?>" class="sirsc-feature as-target">
		<?php
	}
	?>
	<table class="striped fixed sirsc-small-info-table sirsc-column-summary">
		<?php foreach ( $summary as $k => $v ) : ?>
			<?php
			$fsize     = ( empty( $v['fsize'] ) || 'N/A' === $v['filesize'] ) ? '<span class="missing-file">' . __( 'The file is missing!', 'sirsc' ) . '</span>' : '';
			$hint      = ( ! empty( $fsize ) ) ? ' missing-file' : '';
			$v['size'] = str_replace( ',', ', ', $v['size'] );
			?>
			<tr class="<?php echo esc_attr( $hint ); ?>">
				<td width="60" align="right" title="<?php echo esc_attr( $v['hint'] ); ?>">
					<?php echo intval( ++ $count ); ?>.
					<span class="dashicons <?php echo esc_attr( $v['icon'] ); ?>"></span>
				</td>
				<td>
					<?php if ( empty( $fsize ) ) : ?>
						<a href="<?php echo esc_url( trailingslashit( $upload_dir['baseurl'] ) . $k ); ?>" target="_blank"><?php echo esc_attr( $v['size'] ); ?></a>
					<?php else : ?>
						<?php echo esc_attr( $v['size'] ); ?>
						<?php echo $fsize; //phpcs:ignore ?>
					<?php endif; ?>
					<b><?php echo esc_attr( $v['width'] ); ?></b>x<b><?php echo esc_attr( $v['height'] ); ?></b>
				</td>
				<td width="70" align="right" nowrap="nowrap">
					<?php echo esc_attr( $v['filesize'] ); ?>
				</td>
			</tr>
		<?php endforeach; ?>
	</table>
	<?php
	if ( $use_wrapper ) {
		?>
		</div>
		<?php
	}
}

/**
 * Return the html code that contains the description of the images sizes
 * defined in the application and provides details about the image sizes
 * of an uploaded image.
 *
 * @param  int $id Attachment ID.
 * @return void
 */
function attachment_sizes_lightbox( $id ) { //phpcs:ignore
	if ( empty( $id ) ) {
		// Fail-fast.
		return;
	}
	$id   = (int) $id;
	$post = get_post( $id );
	if ( empty( $post ) ) {
		// Fail-fast.
		return;
	}

	\SIRSC::load_settings_for_post_id( $id );
	$all_size = \SIRSC::get_all_image_sizes_plugin();
	$compute  = compute_image_details( $id, '', wp_upload_dir(), $all_size );

	if ( ! empty( $compute->source ) && ! empty( $compute->source->name ) ) {
		$source     = $compute->source;
		$main_class = ( true === $source->exists ) ? '' : ' sirsc-message warning';
		?>

		<div class="sirsc_options-title">
			<a onclick="sirscSingleDetails('<?php echo (int) $id; ?>');" class="sirsc-button open-button">
				<span class="dashicons dashicons-update"></span>
			</a>
			<h2><?php esc_html_e( 'Image Details & Options', 'sirsc' ); ?></h2>
			<a class="sirsc-button close-button" onclick="sirscCloseLightbox();">
				<span class="dashicons dashicons-dismiss"></span>
			</a>
		</div>

		<div class="inside">
			<?php
			if ( ! empty( $all_size ) ) {
				?>
				<div class="original-info original<?php echo esc_attr( $main_class ); ?>">
					<?php esc_html_e( 'The original image', 'sirsc' ); ?>:
					<?php echo wp_kses_post( $source->resolution ); ?>,
					<?php esc_html_e( 'file size', 'sirsc' ); ?>: <b><?php echo esc_attr( $source->filesize_text ); ?></b>.
					<br>
					<?php esc_html_e( 'File', 'sirsc' ); ?>:
					<a href="<?php echo esc_url( $source->url ); ?>" target="_blank"><div class="dashicons dashicons-admin-links"></div> <?php echo esc_html( $source->name ); ?></a>
				</div>

				<div class="original-info rows no-top no-shadow main bg-white">
					<b class="first"><?php esc_html_e( 'Image size details & generated image', 'sirsc' ); ?></b>
					<b class="second a-right"><?php esc_html_e( 'Actions', 'sirsc' ); ?></b>
				</div>
				<?php
				$count = 0;
				$good  = [];
				foreach ( $all_size as $k => $v ) {
					++ $count;
					?>
					<div id="sirsc-single-size-info-<?php echo esc_attr( $id ); ?>-<?php echo esc_attr( $k ); ?>"
						data-count="<?php echo esc_attr( $count ); ?>"
						class="as-target">
						<?php show_image_single_size_info( $id, $k, $source->path, $all_size, $count ); ?>
					</div>
					<?php
				}

				++ $count;
				$cl = ( 1 === $count % 2 ) ? 'alternate' : '';
				?>
				<div id="sirsc-extra-info-footer-<?php echo (int) $id; ?>" class="as-target">
					<?php echo attachment_summary( $id, $compute->metadata, $compute, $good ); //phpcs:ignore ?>
				</div>
				<?php
			}
			?>
		</div>
		<?php
	} else {
		response_message( __( 'The file is missing!', 'sirsc' ), 'error' );
	}
}

/**
 * Output response message element.
 *
 * @param  string $text Message.
 * @param  string $type Message type.
 * @return void
 */
function response_message( $text, $type = 'success' ) { //phpcs:ignore
	echo wp_kses_post( '<span class="sirsc-response-message ' . $type . '">' . $text . '</span>' );
}

/**
 * Raw cleanup the image sizes for a specified image.
 *
 * @param  int $id Attachment ID.
 * @return void
 */
function single_attachment_raw_cleanup( $id ) { //phpcs:ignore
	if ( empty( $id ) ) {
		// Fail-fast.
		response_message( __( 'Something went wrong!', 'sirsc' ), 'error' );
		return;
	}

	\SIRSC\Action\cleanup_attachment_all_sizes( $id );
	response_message( __( 'Done!', 'sirsc' ), 'success' );
}

/**
 * Return the html code for a button that triggers the image sizes generator.
 *
 * @param  int  $id           Attachment ID.
 * @param  bool $show_cleanup True to output also the raw cleanup button.
 * @return string
 */
function make_buttons( $id = 0, $show_cleanup = false ) { //phpcs:ignore
	global $sirsc_column_summary;
	$id      = (int) $id;
	$buttons = '
		<div class="button-primary" onclick="sirscSingleDetails(\'' . $id . '\')" title="' . esc_attr__( 'Details/Options', 'sirsc' ) . '">' . SIRSC_ICON_DETAILS . ' ' . esc_html__( 'Image Details', 'sirsc' ) . '</div>
		<div class="button-primary" onclick="sirscSingleRegenerate(\'' . $id . '\')" title="' . esc_attr__( 'Regenerate', 'sirsc' ) . '">' . SIRSC_ICON_REFRESH . ' ' . esc_html__( 'Regenerate', 'sirsc' ) . '</div>';

	if ( ! empty( $sirsc_column_summary ) || true === $show_cleanup ) {
		$buttons .= '
		<div class="button-primary" onclick="sirscSingleCleanup(\'' . $id . '\')" title="' . esc_attr__( 'Raw Cleanup', 'sirsc' ) . '">' . SIRSC_ICON_CLEANUP . ' ' . esc_html__( 'Raw Cleanup', 'sirsc' ) . '</div>';
	}

	$buttons = apply_filters( 'sirsc/buttons', $buttons, $id );

	return $buttons;
}

/**
 * Maybe identify and alternative to match the image size.
 *
 * @param  string|array $maybe_size Maybe an image size name or an image size array.
 * @return string
 */
function maybe_match_size_name_by_width_height( $maybe_size ) { //phpcs:ignore
	if ( empty( $maybe_size ) ) {
		// Fail-fast, no name specified.
		return 'full';
	}
	$all_sizes = \SIRSC::get_all_image_sizes();
	if ( empty( $all_sizes ) ) {
		// Fail-fast, no sizes computed.
		return 'full';
	}
	$w = 0;
	$h = 0;
	if ( is_scalar( $maybe_size ) ) {
		if ( ! empty( $all_sizes[ $maybe_size ] ) ) {
			// Fail-fast, the image size name exists.
			return $maybe_size;
		} else {
			// Check if there is any widtd and height available.
			$x = explode( 'x', $maybe_size );
			if ( ! empty( $x[0] ) ) {
				$w = (int) $x[0];
			}
			if ( ! empty( $x[1] ) ) {
				$h = (int) $x[1];
			}
		}
	} else {
		if ( ! empty( $maybe_size[0] ) ) {
			$w = (int) $maybe_size[0];
		}
		if ( ! empty( $maybe_size[1] ) ) {
			$h = (int) $maybe_size[1];
		}
	}
	if ( empty( $w ) && empty( $h ) ) {
		// Fail-fast, no width and no height to work with.
		return 'full';
	}

	foreach ( $all_sizes as $key => $value ) {
		if ( (int) $value['width'] === $w && (int) $value['height'] === $h ) {
			// Perfect match.
			return $key;
		}
	}

	foreach ( $all_sizes as $key => $value ) {
		if ( (int) $value['width'] === $w ) {
			// Partial match.
			return $key;
		} elseif ( (int) $value['height'] === $h ) {
			// Partial match.
			return $key;
		}
	}

	// Fallback to full size.
	return 'full';
}

/**
 * Returns a text description of an image size details.
 *
 * @param array $v Image size details.
 */
function size_to_text( $v ) { //phpcs:ignore
	if ( 0 === (int) $v['height'] ) {
		$size_text = '<b>' . esc_html__( 'scale', 'sirsc' ) . '</b> ' . esc_html__( 'to max width of', 'sirsc' ) . ' <b>' . $v['width'] . '</b>px';
	} elseif ( 0 === (int) $v['width'] ) {
		$size_text = '<b>' . esc_html__( 'scale', 'sirsc' ) . '</b> ' . esc_html__( 'to max height of', 'sirsc' ) . ' <b>' . $v['height'] . '</b>px';
	} else {
		if ( ! empty( $v['crop'] ) ) {
			$size_text = '<b>' . esc_html__( 'crop', 'sirsc' ) . '</b> ' . esc_html__( 'of', 'sirsc' ) . ' <b>' . $v['width'] . '</b>x<b>' . $v['height'] . '</b>px';
		} else {
			$size_text = '<b>' . esc_html__( 'scale', 'sirsc' ) . '</b> ' . esc_html__( 'to max width of', 'sirsc' ) . ' <b>' . $v['width'] . '</b>px ' . esc_html__( 'or to max height of', 'sirsc' ) . ' <b>' . $v['height'] . '</b>px';
		}
	}
	return $size_text;
}

/**
 * Return the html code for a button that triggers the image sizes generator.
 *
 * @param integer $attachment_id The attachment ID.
 * @param string  $size          The size slug.
 * @param boolean $click         True to append the onclick attribute.
 * @param string  $selected      Selected crop.
 * @return string
 */
function make_generate_images_crop( $attachment_id = 0, $size = 'thumbnail', $click = true, $selected = '' ) { //phpcs:ignore
	$id = intval( $attachment_id ) . $size;
	$c  = 0;

	$attachment_id = (int) $attachment_id;

	$button = '<div class="sirsc-crop-pos-wrap regenerate" title="' . esc_attr__( 'Click to generate a crop of the image from this position', 'sirsc' ) . '">';
	if ( ! empty( \SIRSC::$settings['default_crop'][ $size ] ) && empty( $selected ) ) {
		$selected = \SIRSC::$settings['default_crop'][ $size ];
	}

	$selected = ( empty( $selected ) ) ? 'cc' : $selected;
	$selected = trim( $selected );
	foreach ( \SIRSC::$crop_positions as $k => $v ) {
		$onclick = ( 0 === $attachment_id )
			? ' data-sirsc-autosubmit="click"'
			: ' onclick="sirscCropPosition(\'' . $attachment_id . '\', \'' . esc_attr( $size ) . '\', \'' . esc_attr( $k ) . '\');"';

		$name = ( 0 === $attachment_id )
			? 'sirsc[default_crop][' . esc_attr( $size ) . ']'
			: 'crop_small_type_' . esc_attr( $size );

		$title   = ( $k === $selected ) ? __( 'Default crop position from settings', 'sirsc' ) . ' - ' : '';
		$button .= '<label title="' . esc_attr( $title . $v ) . '"><input type="radio" name="' . $name . '" id="crop_small_type' . esc_attr( $size ) . $id . '_' . esc_attr( $k ) . '" value="' . esc_attr( $k ) . '"' . ( ( $k === $selected ) ? ' checked="checked"' : '' ) . $onclick . ' /></label> ';
		++ $c;
	}
	$button .= '</div>';
	return $button;
}

/**
 * Returns an array of all the post types allowed in the plugin filters.
 *
 * @return array;
 */
function get_all_post_types_plugin() { //phpcs:ignore
	$post_types = get_post_types( [], 'objects' );
	if ( ! empty( $post_types ) && ! empty( \SIRSC::$exclude_post_type ) ) {
		foreach ( \SIRSC::$exclude_post_type as $k ) {
			unset( $post_types[ $k ] );
		}
	}
	return $post_types;
}

/**
 * Get bulk action last id processed.
 *
 * @param  string $size   Image size slug.
 * @param  string $cpt    Custom post type.
 * @param  string $prefix Action prefix.
 * @return int
 */
function get_bulk_action_last_id( $size = '', $cpt = '', $prefix = '' ) { //phpcs:ignore
	return get_option( 'sirsc-bulk-action-' . esc_attr( $prefix . $size . '-' . $cpt ) . '-latest-id', 0 );
}

/**
 * Set bulk action last id processed.
 *
 * @param  string $size   Image size slug.
 * @param  string $cpt    Custom post type.
 * @param  int    $id     Attachment ID.
 * @param  string $prefix Action prefix.
 * @return void
 */
function set_bulk_action_last_id( $size = '', $cpt = '', $id = 0, $prefix = '' ) { //phpcs:ignore
	update_option( 'sirsc-bulk-action-' . esc_attr( $prefix . $size . '-' . $cpt ) . '-latest-id', $id );
}

/**
 * Set bulk action last id processed.
 *
 * @param  string $size   Image size slug.
 * @param  string $cpt    Custom post type.
 * @param  string $prefix Action prefix.
 * @return void
 */
function reset_bulk_action_last_id( $size = '', $cpt = '', $prefix = '' ) { //phpcs:ignore
	if ( ! empty( \SIRSC::$settings['bulk_actions_descending'] ) ) {
		$id = PHP_INT_MAX;
	} else {
		$id = 0;
	}
	update_option( 'sirsc-bulk-action-' . esc_attr( $prefix . $size . '-' . $cpt ) . '-latest-id', $id );
}

/**
 * Returns the bulk action total, latest processed id and rows to be processed.
 *
 * @param  string $size   Image size slug.
 * @param  string $cpt    Custom post type.
 * @param  int    $limit  Query limit.
 * @param  string $prefix Action prefix.
 * @return array
 */
function bulk_action_query( $size, $cpt, $limit = 1, $prefix = '' ) { //phpcs:ignore
	global $wpdb;

	if ( \SIRSC::$is_cron ) {
		if ( empty( $prefix ) ) {
			$limit = \SIRSC::$settings['cron_batch_regenerate'];
		} else {
			$limit = \SIRSC::$settings['cron_batch_cleanup'];
		}
	}

	$result = [
		'total'     => 0,
		'latest_id' => get_bulk_action_last_id( $prefix . $size, $cpt ),
		'rows'      => [],
	];

	$join    = '';
	$where   = '';
	$ord     = 'ASC';
	$compare = '>';

	if ( ! empty( \SIRSC::$settings['bulk_actions_descending'] ) ) {
		$ord     = 'DESC';
		$compare = '<';
	}

	if ( ! empty( $cpt ) ) {
		$join  = ' LEFT JOIN ' . $wpdb->posts . ' as parent ON( parent.ID = p.post_parent ) ';
		$where = $wpdb->prepare( ' AND parent.post_type = %s ', $cpt );
	}
	if ( ! empty( \SIRSC::$settings['regenerate_only_featured'] ) ) {
		$join .= ' INNER JOIN ' . $wpdb->postmeta . ' as pm ON( pm.meta_value = p.ID and pm.meta_key = \'_thumbnail_id\' ) ';
	}

	if ( ! empty( $prefix ) ) {
		$join  .= ' LEFT JOIN ' . $wpdb->postmeta . ' as pm2 ON( pm2.post_id = p.ID ) ';
		$where .= $wpdb->prepare( ' AND pm2.meta_key like %s ', '_wp_attachment_metadata' );

		if ( 'unused' === $size || 'raw' === $size ) {
			$where .= $wpdb->prepare( ' AND pm2.meta_value like %s ', '%' . $wpdb->esc_like( 'sizes' ) . '%' );
		} else {
			$where .= $wpdb->prepare( ' AND pm2.meta_value like %s ', '%' . $wpdb->esc_like( '"' . $size . '"' ) . '%' );
		}
	}

	$query = $wpdb->prepare( ' SELECT #[VAR]# FROM ' . $wpdb->posts . ' as p ' . $join . ' WHERE p.ID ' . $compare . ' %d AND ( p.post_mime_type like %s and p.post_mime_type not like %s )' . $where . ' ORDER BY p.ID ' . $ord . ' ', (int) $result['latest_id'], $wpdb->esc_like( 'image/' ) . '%', $wpdb->esc_like( 'image/svg' ) . '%' );//phpcs:ignore
	$total = $wpdb->get_var( str_replace( '#[VAR]#', 'count(distinct p.ID)', $query ) ); //phpcs:ignore

	$result['total'] = (int) $total;
	$result['rows']  = $wpdb->get_results( str_replace( '#[VAR]#', 'distinct p.ID', $query ) . ' LIMIT 0,' . (int) $limit ); //phpcs:ignore

	return $result;
}

/**
 * Cleanup all the images for the specified image size name.
 *
 * @param  string $start Action type.
 * @param  string $type  Cleanup type.
 * @param  string $cpt   Custom post type.
 * @return void
 */
function raw_cleanup_on_request( $start, $type, $cpt ) {//phpcs:ignore
	if ( ! empty( $start ) ) {
		notify_doing_sirsc();

		if ( ! empty( $type ) ) {
			global $wpdb;

			if ( 'start' === $start ) {
				// Start from the beginning of the list.
				reset_bulk_action_last_id( $type, $cpt, 'rc-' );
			} elseif ( 'finish' === $start ) {
				// Start from the beginning of the list.
				reset_bulk_action_last_id( $type, $cpt, 'rc-' );
				$message = \SIRSC\Admin\assess_collected_errors();
				if ( empty( $message ) ) {
					// No errors collected.
					return;
				}
				?>
				<div class="sirsc_options-title">
					<h2><?php esc_html_e( 'Cleanup Log', 'sirsc' ); ?></h2>
					<a class="sirsc-button close-button" onclick="sirscStopBulkAction(); sirscCloseLightbox();">
						<span class="dashicons dashicons-dismiss"></span>
					</a>
				</div>
				<div class="inside as-target sirsc-bulk-action sirsc-bulk-clean">
					<?php echo $message; //phpcs:ignore ?>
				</div>
				<?php
				delete_option( 'sirsc_monitor_errors' );
				return;
			}

			$result = bulk_action_query( $type, $cpt, ceil( \SIRSC::BULK_CLEANUP_ITEMS / 2 ), 'rc-' );
			if ( empty( $result['total'] ) ) {
				reset_bulk_action_last_id( $type, $cpt, 'rc-' );
				$message = \SIRSC\Admin\assess_collected_errors();
				?>
				<div class="sirsc_options-title">
					<h2><?php esc_html_e( 'Finishing up', 'sirsc' ); ?></h2>
					<a class="sirsc-button close-button" onclick="sirscStopBulkAction(); sirscCloseLightbox();">
						<span class="dashicons dashicons-dismiss"></span>
					</a>
				</div>
				<div class="inside as-target sirsc-bulk-action sirsc-bulk-clean">
					<div class="info-list">
						<div class="info-item">...</div>
					</div>
				</div>
				<?php
				if ( empty( $message ) ) {
					// No errors collected.
					echo document_ready_js( 'sirscHideCleanupButton(\'' . $type . '\'); sirscCloseLightbox();' ); //phpcs:ignore
					return;
				}

				echo document_ready_js( 'sirscHideCleanupButton(\'' . $type . '\'); sirscStartRawCleanup( \'finish\', \'' . $type . '\', \'' . $cpt . '\' );' ); //phpcs:ignore
				return;
			}

			?>
			<div class="sirsc_options-title">
				<h2><?php esc_html_e( 'REMAINING TO CLEAN UP', 'sirsc' ); ?>: <?php echo (int) $result['total']; ?></h2>
				<a class="sirsc-button close-button" onclick="sirscStopBulkAction(); sirscCloseLightbox();">
					<span class="dashicons dashicons-dismiss"></span>
				</a>
			</div>
			<div class="inside as-target sirsc-bulk-action sirsc-bulk-clean">

				<div class="info-list">
				<?php
				if ( $result['total'] > 0 ) {
					if ( ! empty( $result['rows'] ) ) {
						$upls  = wp_upload_dir();
						$sizes = \SIRSC::get_all_image_sizes_plugin();
						foreach ( $result['rows'] as $k => $v ) {
							$id = (int) $v->ID;
							set_bulk_action_last_id( $type, $cpt, $id, 'rc-' );

							$image   = wp_get_attachment_metadata( $id );
							$initial = $image;
							$deleted = false;
							$message = [];
							$idd     = $id . $type;
							$compute = compute_image_details( $id, 'full', $upls, $sizes, true );
							$summary = \SIRSC::general_sizes_and_files_match( $id, $compute->metadata, $compute );

							if ( ! empty( $summary ) ) {
								foreach ( $summary as $name => $info ) {
									$to_delete = false;
									if ( ! empty( $info['match'] ) ) {
										if ( true === $info['is_main']
											|| in_array( 'full', $info['match'], true )
											|| in_array( 'original', $info['match'], true ) ) {
											// Not removable.
											$to_delete = false;

											\SIRSC::collect_regenerate_results( $id, '', 'info', 'cleanup' );
											$text = '<span>' . $name . '</span> <em>' . esc_html__( 'Skipping the cleanup or this file (it is the original file).', 'sirsc' ) . '</em>';

											$message[] = output_bulk_message( 'warning', $text, $id, true );
										} else {
											if ( 'unused' === $type ) {
												// This is unused cleanup.
												if ( ! empty( $info['registered'] ) ) {
													// Not removable.
													$to_delete = false;

													\SIRSC::collect_regenerate_results( $id, '', 'info', 'cleanup' );
													$text = '<span>' . $name . '</span> <em>' . esc_html__( 'Skipping the cleanup or this file (the size is registered).', 'sirsc' ) . '</em>';

													$message[] = output_bulk_message( 'warning', $text, $id, true );
												} else {
													$to_delete = true;
												}
											} else {
												// This is raw cleanup.
												$to_delete = true;
											}
										}
									}

									if ( true === $to_delete ) {
										$removable = $upls['basedir'] . '/' . $name;
										if ( file_exists( $removable ) ) {
											@unlink( $removable );//phpcs:ignore
											if ( ! file_exists( $removable ) ) {
												if ( ! empty( $info['match'] ) ) {
													foreach ( $info['match'] as $size_name ) {
														if ( isset( $image['sizes'][ $size_name ] ) ) {
															unset( $image['sizes'][ $size_name ] );
														}
													}
												}
												\SIRSC::collect_regenerate_results( $id, '', 'success', 'cleanup' );
												$text = '<span>' . $name . '</span> <em>' . esc_html__( 'has been deleted.', 'sirsc' ) . '</em>';

												$message[] = output_bulk_message( 'success', $text, $id, true );

												// Notify other scripts that the file was deleted.
												do_action( 'sirsc_image_file_deleted', $id, $removable );
											} else {
												if ( empty( $info['registered'] ) ) {
													$text = '<span>' . $name . '</span> <em>' . esc_html__( 'could not be deleted.', 'sirsc' ) . '</em>';
													\SIRSC::collect_regenerate_results( $id, $text, 'error', 'cleanup' );
													$message[] = output_bulk_message( 'error', $text, $id, true );
												} else {
													\SIRSC::collect_regenerate_results( $id, '', 'success', 'cleanup' );
													$text = '<span>' . $name . '</span> <em>' . esc_html__( 'has been deleted.', 'sirsc' ) . '</em>';

													$message[] = output_bulk_message( 'success', $text, $id, true );
												}
											}
										}
									}
								}
							} else {
								$text = '<span>' . esc_html__( 'No cleanup necessary for', 'sirsc' ) . ' ' . $id . '</span><em></em>';

								$message[] = output_bulk_message( 'info', $text, $id, true );
							}

							if ( $initial !== $image ) {
								// Update the cleaned meta.
								wp_update_attachment_metadata( $id, $image );
							}

							?>
							<div class="info-item bulk-cleanup-info">
								<?php
								echo wp_kses_post( implode( '</div><div class="info-item bulk-cleanup-info">', $message ) );
								?>
							</div>
							<?php
						}
					}
					echo document_ready_js( 'sirscStartRawCleanup( \'resume\', \'' . $type . '\', \'' . $cpt . '\' ); sirscShowResumeButton( \'' . $type . '\' );', true ); //phpcs:ignore
				} else {
					echo document_ready_js( 'sirscStartRawCleanup( \'finish\', \'' . $type . '\', \'' . $cpt . '\' ); sirscHideCleanupButton( \'' . $type . '\' );', true ); //phpcs:ignore
					response_message( __( 'Done!', 'sirsc' ), 'success' );
				}
				?>
				</div>
			</div>
			<?php
		} else {
			echo document_ready_js( 'sirscStartRawCleanup( \'finish\', \'' . $type . '\', \'' . $cpt . '\' ); ', true ); //phpcs:ignore
			response_message( __( 'Something went wrong!', 'sirsc' ), 'error' );
		}
	}
}

/**
 * Cleanup all the images for the specified image size name.
 *
 * @param  string $start Action type.
 * @param  string $size  Image size slug.
 * @param  string $cpt   Custom post type.
 * @return void
 */
function cleanup_image_sizes_on_request( $start, $size, $cpt ) { //phpcs:ignore
	if ( ! empty( $start ) ) {
		notify_doing_sirsc();

		if ( ! empty( $size ) ) {
			global $wpdb;

			if ( 'start' === $start ) {
				// Start from the beginning of the list.
				reset_bulk_action_last_id( $size, $cpt, 'c-' );
			} elseif ( 'finish' === $start ) {
				// Start from the beginning of the list.
				reset_bulk_action_last_id( $size, $cpt, 'c-' );
				$message = \SIRSC\Admin\assess_collected_errors();
				if ( empty( $message ) ) {
					// No errors collected.
					return;
				}
				?>
				<div class="sirsc_options-title">
					<h2><?php esc_html_e( 'Cleanup Log', 'sirsc' ); ?></h2>
					<a class="sirsc-button close-button" onclick="sirscStopBulkAction(); sirscCloseLightbox();">
						<span class="dashicons dashicons-dismiss"></span>
					</a>
				</div>
				<div class="inside as-target sirsc-bulk-action sirsc-bulk-clean">
					<?php echo $message; //phpcs:ignore ?>
				</div>
				<?php
				delete_option( 'sirsc_monitor_errors' );
				return;
			}

			$result = bulk_action_query( $size, $cpt, \SIRSC::BULK_CLEANUP_ITEMS, 'c-' );
			if ( empty( $result['total'] ) ) {
				reset_bulk_action_last_id( $size, $cpt, 'c-' );
				$message = \SIRSC\Admin\assess_collected_errors();
				?>
				<div class="sirsc_options-title">
					<h2><?php esc_html_e( 'Finishing up', 'sirsc' ); ?></h2>
					<a class="sirsc-button close-button" onclick="sirscStopBulkAction(); sirscCloseLightbox();">
						<span class="dashicons dashicons-dismiss"></span>
					</a>
				</div>
				<div class="inside as-target sirsc-bulk-action sirsc-bulk-clean">
					<div class="info-list">
						<div class="info-item">...</div>
					</div>
				</div>
				<?php
				if ( empty( $message ) ) {
					// No errors collected.
					echo document_ready_js( 'sirscHideCleanupButton(\'' . $size . '\'); sirscCloseLightbox();' ); //phpcs:ignore
					return;
				}

				echo document_ready_js( 'sirscHideCleanupButton(\'' . $size . '\'); sirscStartCleanupSize( \'finish\', \'' . $size . '\', \'' . $cpt . '\' );' ); //phpcs:ignore
				return;
			}

			?>
			<div class="sirsc_options-title">
				<h2><?php esc_html_e( 'REMAINING TO CLEAN UP', 'sirsc' ); ?>: <?php echo (int) $result['total']; ?></h2>
				<a class="sirsc-button close-button" onclick="sirscStopBulkAction(); sirscCloseLightbox();">
					<span class="dashicons dashicons-dismiss"></span>
				</a>
			</div>
			<div class="inside as-target sirsc-bulk-action sirsc-bulk-clean">

				<div class="info-list">
				<?php
				if ( $result['total'] > 0 ) {
					if ( ! empty( $result['rows'] ) ) {
						$upls  = wp_upload_dir();
						$sizes = \SIRSC::get_all_image_sizes_plugin();
						foreach ( $result['rows'] as $k => $v ) {
							$id = (int) $v->ID;
							set_bulk_action_last_id( $size, $cpt, $id, 'c-' );

							$deleted  = false;
							$unset    = true;
							$message  = '';
							$idd      = $id . $size;
							$image    = compute_image_details( $id, $size, $upls, $sizes, true );
							$metadata = $image->metadata;
							$info     = $image->info;
							?>
							<div class="info-item bulk-cleanup-info">
								<?php
								if ( ! $info->is_found ) {
									$text = '<span>' . $info->expected->name . '</span> <em>' . esc_html__( 'could not be found.', 'sirsc' ) . '</em>';

									$message = output_bulk_message( 'warning', $text, $id, true );
								} else {
									// The file exists.
									if ( ! $info->can_be_deleted ) {
										// The file cannot be deleted.
										if ( $info->is_original ) { //phpcs:ignore
											if ( $info->can_be_generated //phpcs:ignore
												&& $info->expected->file !== $image->source->file ) { //phpcs:ignore
												// This can be decoupled.
											} else {
												$unset = false;
												if ( ! empty( $metadata['sizes'][ $size ] ) ) {
													$unset = true;
												}
											}
											$text = '<span>' . $info->expected->name . '</span> <em>' . esc_html__( 'could not be deleted (it is the original file).', 'sirsc' ) . '</em>';

											\SIRSC::collect_regenerate_results( $id, $text, 'error', 'cleanup' );
											$message = output_bulk_message( 'error', $text, $id, true );
										}
									} else {
										if ( ! is_dir( $image->size->path ) ) {
											$text = '<span>' . $image->size->name . '</span> <em>' . esc_html__( 'has been deleted.', 'sirsc' ) . '</em>';

											// Make sure not to delete the original file.
											\SIRSC::collect_regenerate_results( $id, $text, 'success', 'cleanup' );
											$message = output_bulk_message( 'success', $text, $id, true );
											@unlink( $image->size->path ); //phpcs:ignore

											// Notify other scripts that the file was deleted.
											do_action( 'sirsc_image_file_deleted', $id, $image->size->path );
											$deleted = true;
										}
									}
								}

								if ( $unset || $deleted ) {
									if ( isset( $metadata['sizes'][ $size ] ) ) {
										unset( $metadata['sizes'][ $size ] );
										wp_update_attachment_metadata( $id, $metadata );
									}
								}
								echo wp_kses_post( $message );
								?>
							</div>
							<?php
						}
					}
					echo document_ready_js( 'sirscStartCleanupSize( \'resume\', \'' . $size . '\', \'' . $cpt . '\' ); sirscShowResumeButton( \'' . $size . '\' );', true ); //phpcs:ignore
				} else {
					echo document_ready_js( 'sirscStartCleanupSize( \'finish\', \'' . $size . '\', \'' . $cpt . '\' ); sirscHideCleanupButton( \'' . $size . '\' );', true ); //phpcs:ignore
					response_message( __( 'Done!', 'sirsc' ), 'success' );
				}
				?>
				</div>
			</div>
			<?php
		} else {
			echo document_ready_js( 'sirscStartCleanupSize( \'finish\', \'' . $size . '\', \'' . $cpt . '\' ); ', true ); //phpcs:ignore
			response_message( __( 'Something went wrong!', 'sirsc' ), 'error' );
		}
	}
}

/**
 * Regenerate all the images for the specified image size name.
 *
 * @param  string $start Action.
 * @param  string $size  Image size slug.
 * @param  string $cpt   Custom post type.
 * @return void
 */
function regenerate_image_sizes_on_request( $start, $size, $cpt ) { //phpcs:ignore
	if ( ! empty( $start ) ) {
		notify_doing_sirsc();

		if ( ! empty( $size ) ) {
			global $wpdb;

			if ( 'start' === $start ) {
				// Start from the beginning of the list.
				reset_bulk_action_last_id( $size, $cpt );
			} elseif ( 'finish' === $start ) {
				// Start from the beginning of the list.
				reset_bulk_action_last_id( $size, $cpt );
				$message = \SIRSC\Admin\assess_collected_errors();
				if ( empty( $message ) ) {
					// No errors collected.
					return;
				}
				?>
				<div class="sirsc_options-title">
					<h2><?php esc_html_e( 'Regenerate Log', 'sirsc' ); ?></h2>
					<a class="sirsc-button close-button" onclick="sirscStopBulkAction(); sirscCloseLightbox();">
						<span class="dashicons dashicons-dismiss"></span>
					</a>
				</div>
				<div class="inside as-target sirsc-bulk-action sirsc-bulk-regen">
					<?php echo $message; //phpcs:ignore ?>
				</div>
				<?php
				delete_option( 'sirsc_monitor_errors' );
				return;
			}
			$result = bulk_action_query( $size, $cpt );

			if ( empty( $result['total'] ) ) {
				reset_bulk_action_last_id( $size, $cpt );
				$message = \SIRSC\Admin\assess_collected_errors();
				?>
				<div class="sirsc_options-title">
					<h2><?php esc_html_e( 'Finishing up', 'sirsc' ); ?></h2>
					<a class="sirsc-button close-button" onclick="sirscStopBulkAction(); sirscCloseLightbox();">
						<span class="dashicons dashicons-dismiss"></span>
					</a>
				</div>
				<div class="inside as-target sirsc-bulk-action sirsc-bulk-regen">
					<div class="sirsc-rows">
						<div class="sirsc-log">
							<div>...</div>
						</div>
					</div>
				</div>
				<?php
				if ( empty( $message ) ) {
					// No errors collected.
					echo document_ready_js( 'sirscCloseLightbox(); sirscHideResumeButton(\'' . $size . '\');' ); //phpcs:ignore
					return;
				}

				echo document_ready_js( 'sirscHideResumeButton(\'' . $size . '\'); sirscStartRegenerateSize( \'finish\', \'' . $size . '\', \'' . $cpt . '\' );' ); //phpcs:ignore
				return;
			}
			?>

			<div class="sirsc_options-title">
				<h2><?php esc_html_e( 'REMAINING TO REGENERATE', 'sirsc' ); ?>: <?php echo (int) $result['total']; ?></h2>
				<a class="sirsc-button close-button" onclick="sirscStopBulkAction(); sirscCloseLightbox();">
					<span class="dashicons dashicons-dismiss"></span>
				</a>
			</div>
			<div class="inside as-target sirsc-bulk-action sirsc-bulk-regen">
				<?php
				if ( $result['total'] > 0 ) {
					$upls  = wp_upload_dir();
					$sizes = \SIRSC::get_all_image_sizes(); // Prev get all image sizes plugin.
					if ( ! empty( $result['rows'] ) ) {
						foreach ( $result['rows'] as $v ) {
							$id = (int) $v->ID;
							set_bulk_action_last_id( $size, $cpt, $id );

							$message = '';
							$idd     = $id . $size;
							$image   = compute_image_details( $id, $size, $upls, $sizes, true );
							$info    = $image->info;

							if ( $info->bulk_skip_regenerate ) {
								// Skip regenerate.
								if ( ! empty( $info->bulk_regenerate_text ) ) {
									$text = $info->bulk_regenerate_text;
								} else {
									$text = '<span>' . $info->expected->name . '</span> <em>' . __( 'Skipping the regeneration of this file (as per settings).', 'sirsc' ) . '</em>';
								}
								$message = output_bulk_message( 'warning', $text, $id, true );
							} else {
								$resp = make_images_if_not_exists( $id, $size );
								if ( 'error-too-small' === $resp ) {
									$text = '<span>' . $info->expected->name . '</span> <em>' . esc_html__( 'Could not generate, the original is too small.', 'sirsc' ) . '</em>';

									\SIRSC::collect_regenerate_results( $id, $text, 'error' );
									$message = output_bulk_message( 'error', $text, $id, true );
								} else {
									$image = compute_image_details( $id, $size, $upls, $sizes, true );

									if ( ! empty( $image->size->path ) ) {
										$text = '<span>' . $image->size->name . '</span> <em>' . __( 'The file was regenerated.', 'sirsc' ) . '</em>';

										\SIRSC::collect_regenerate_results( $id, '', 'success' );
										$message = output_bulk_message( 'success', $text, $id, true );
									} else {
										if ( ! $image->source->exists ) {
											$text = '<span>' . $info->expected->name . '</span> <em>' . esc_html__( 'Could not generate, the original file is missing.', 'sirsc' ) . '</em>';
										} else {
											$text = '<span>' . $info->expected->name . '</span> <em>' . esc_html__( 'Could not generate, the original is too small.', 'sirsc' ) . '</em>';
										}

										\SIRSC::collect_regenerate_results( $id, $text, 'error' );
										$message = output_bulk_message( 'error', $text, $id, true );
									}
								}

								// Notifiy other scripts that the image was regenerated.
								$image = wp_get_attachment_metadata( $id );
								do_action( 'sirsc_image_processed', $id, $size );
								do_action( 'sirsc_attachment_images_processed', $image, $id );

								// Recompute maybe.
								$image = compute_image_details( $id, $size, $upls, $sizes, true );
								$info  = $image->info;
							}

							if ( $info->is_found ) {
								$img = '<span class="image-wrap" id="idsrc' . $idd . '"><img src="' . $image->size->url . '?v=' . time() . '" border="0" loading="lazy" /></span>';
							} else {
								$img = '<span class="image-wrap empty" id="idsrc' . $idd . '">' . esc_html__( 'NOT FOUND', 'sirsc' ) . '</span>';
							}
							?>
							<div class="rows bg-white sirsc-regen-info">
								<div>
									<span class="sirsc-small-info">
										<?php esc_html_e( 'Info', 'sirsc' ); ?>:
										<b><?php echo size_to_text( $sizes[ $size ] ); //phpcs:ignore ?></b>
									</span>
									<div id="sirsc-size-details-<?php echo esc_attr( $idd ); ?>" class="sirsc-size-details">
										<?php echo $img; //phpcs:ignore ?>
										<?php if ( ! empty( $info->size->resolution ) ) : ?>
											<div><?php esc_html_e( 'Resolution', 'sirsc' ); ?>:
												<?php echo $info->size->resolution; //phpcs:ignore ?></div>
											<span class="image-size-column">
												<?php esc_html_e( 'File size', 'sirsc' ); ?>:
												<b class="image-file-size"><?php echo $info->size->filesize_text; //phpcs:ignore ?></b>
											</span>
										<?php endif; ?>
									</div>
								</div>
							</div>

							<div class="rows bg-trans min-height">
								<?php echo wp_kses_post( $message ); ?>
							</div>
							<?php
						}
					}

					echo document_ready_js( 'sirscShowCleanupButton(\'' . $size . '\'); sirscStartRegenerateSize( \'resume\', \'' . $size . '\', \'' . $cpt . '\' ); sirscShowResumeButton(\'' . $size . '\')', true ); //phpcs:ignore
				} else {
					echo document_ready_js( 'sirscStartRegenerateSize( \'finish\', \'' . $size . '\', \'' . $cpt . '\' );', true ); //phpcs:ignore
					response_message( __( 'Done!', 'sirsc' ), 'success' );
				}
				?>
			</div>
			<?php

		} else {
			response_message( __( 'Something went wrong!', 'sirsc' ), 'error' );
		}
	}
}

/**
 * Output bulk message regenerate original too small.
 *
 * @param  string $status Status.
 * @param  string $file   File name.
 * @param  int    $id     Attachment ID.
 * @param  bool   $return True to return.
 * @return string
 */
function output_bulk_message( $status, $file, $id, $return = false ) { //phpcs:ignore
	$message = '';

	switch ( $status ) {
		case 'success':
			$message = SIRSC_ICON_SUCCESS . $file;
			break;

		case 'error':
			$message = SIRSC_ICON_ERROR . $file;
			break;

		default:
			$message = SIRSC_ICON_INFO . $file;
			break;
	}

	$message = '<div class="sirsc-log status-' . esc_attr( $status ) . '">' . $message . '</div>';
	if ( true === $return ) {
		return $message;
	}

	echo wp_kses_post( $message );
}

/**
 * Get folders list.
 *
 * @param  string $base The root folder for computation.
 * @return array
 */
function get_folders_list( $base ) { //phpcs:ignore
	$dir = $base;
	$all = [];
	while ( $dirs = glob( rtrim( $dir, '/' ) . '/*', GLOB_ONLYDIR ) ) { //phpcs:ignore
		if ( is_array( $dirs ) ) {
			$all = array_merge( $all, $dirs );
		} else {
			array_push( $all, $dirs );
		}
		$dir = rtrim( $dir, '/' ) . '/*';
	}
	sort( $all );

	$diff       = substr_count( $base, '/' ) - 1;
	$sum_fsize  = 0;
	$sum_fcount = 0;

	// Parent and direct files count and files size and direct folders.
	foreach ( $all as $key => $value ) {
		$dinf        = folder_files_count( $value );
		$all[ $key ] = [
			'name'          => rtrim( $value ),
			'parent'        => dirname( $value ),
			'position'      => $key + 1,
			'level'         => substr_count( $value, '/' ) - $diff,
			'files_count'   => $dinf['count'],
			'files_size'    => $dinf['size'],
			'folders_count' => $dinf['folders'],
			'totals'        => [
				'files_count'   => 0,
				'files_size'    => 0,
				'folders_count' => 0,
				'all_size'      => 0,
			],
		];

		$sum_fsize  += $dinf['size'];
		$sum_fcount += $dinf['count'];
	}

	$seri   = serialize( $all ); //phpcs:ignore
	$info   = [];
	$dinf   = folder_files_count( $base );
	$info[] = [
		'name'          => rtrim( $base, '/' ),
		'parent'        => '',
		'position'      => 0,
		'level'         => 0,
		'files_count'   => $dinf['count'],
		'files_size'    => $dinf['size'],
		'folders_count' => $dinf['folders'],
		'totals'        => [
			'files_count'   => $sum_fsize,
			'files_size'    => $sum_fcount,
			'folders_count' => 0,
			'all_size'      => 0,
		],
	];
	foreach ( $all as $key => $value ) {
		$info[] = $value;
	}

	// This is the real trick to retro compute.
	$tmp = $info;
	usort( $tmp, function( $item1, $item2 ) { //phpcs:ignore
		return $item2['level'] <=> $item1['level'];
	} );

	foreach ( $tmp as $value ) {
		$v = get_folder_totals( $value['name'], $value['position'], $info );

		$info[ $value['position'] ]['totals'] = $v;
	}

	// Simplify paths.
	$root_base = dirname( $base );
	foreach ( $info as $key => $value ) {
		$info[ $key ]['path']   = $value['name'];
		$info[ $key ]['name']   = basename( $value['name'] );
		$info[ $key ]['parent'] = ltrim( str_replace( $root_base, '', $value['parent'] ), '/' );
	}

	if ( ! empty( $info[0] ) ) {
		$info[0]['totals']['files_count'] = $info[0]['totals']['files_count'] - $info[0]['totals']['folders_count'];
	}

	return $info;
}

/**
 * Get the count and size of the directory files.
 *
 * @param string $dir Directory path.
 * @return array
 */
function folder_files_count( $dir ) { //phpcs:ignore
	$info = [
		'count'   => 0,
		'size'    => 0,
		'folders' => 0,
	];
	foreach ( glob( rtrim( $dir, '/' ) . '/*', GLOB_NOSORT ) as $each ) {
		if ( is_file( $each ) ) {
			$info['count'] += 1;
			$info['size']  += filesize( $each );
		} else {
			$info['folders'] += 1;
		}
	}
	return $info;
}

/**
 * Get the count and size of the directory files.
 *
 * @param string  $dir Directory name.
 * @param integer $poz Position in the list.
 * @param array   $all All previousliy computed list.
 * @return array
 */
function get_folder_totals( $dir, $poz, $all ) { //phpcs:ignore
	$size   = $all[ $poz ]['files_size'];
	$countf = $all[ $poz ]['files_count'];
	$countd = $all[ $poz ]['folders_count'];
	foreach ( $all as $key => $value ) {
		if ( $dir === $value['parent'] ) {
			$size   += $value['totals']['files_size'];
			$countf += $value['totals']['files_count'];
			$countd += $value['totals']['folders_count'];
		}
	}
	return [
		'files_size'    => $size,
		'files_count'   => $countf,
		'folders_count' => $countd,
	];
}

/**
 * Progress bar.
 *
 * @param  int    $total     Total items.
 * @param  int    $processed Processed items.
 * @param  bool   $counter   Use counter.
 * @param  string $text      Extra text.
 * @return void
 */
function progress_bar( $total, $processed = 0, $counter = false, $text = '' ) { //phpcs:ignore
	$percent = 0;
	if ( ! empty( $total ) ) {
		$percent = ceil( $processed * 100 / $total );
	}
	$percent = ( $percent > 100 ) ? 100 : $percent;
	$class   = ( $percent >= 5 ) ? ' color' : '';

	if ( $processed > $total ) {
		$percent = 99;
	}
	?>
	<div class="rows three-columns no-top bg-trans no-shadow">
		<div class="span2">
			<div class="sirsc-progress-wrap">
				<div class="processed<?php echo esc_attr( $class ); ?>" style="width:<?php echo (int) $percent; ?>%">
					<?php echo (int) $percent; ?>%
				</div>
			</div>
		</div>
		<?php if ( true === $counter ) : ?>
			<div class="span1">
				<?php
				if ( ! empty( $text ) ) {
					echo esc_html( $text );
				} else {
					echo esc_html(
						sprintf(
							// Translators: %1$d - count products, %2$d - total.
							__( 'There are %1$d items assessed out of %2$d.', 'sirsc' ),
							$processed,
							$total
						)
					);
				}
				?>
			</div>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Cleanup button for the settings page, for an image size.
 *
 * @param  string $cpt  Maybe a post type.
 * @param  string $size Image size slug.
 * @return void
 */
function settings_button_size_cleanup( $cpt, $size ) { //phpcs:ignore
	$size_total = \SIRSC\Admin\calculate_total_to_cleanup( $cpt, $size );
	$size_class = ( ! empty( $size_total ) ) ? '' : ' is-hidden';
	$cron_class = ' is-hidden';
	if ( \SIRSC::$use_cron ) {
		$args = [
			'size' => $size,
			'cpt'  => (string) $cpt,
		];

		$hook = \SIRSC\Cron\get_hook( 'cleanup_image_sizes_on_request', $args );
		if ( \SIRSC\Cron\is_scheduled( $hook, $args ) ) {
			$size_class = ' is-hidden';
			$cron_class = '';
		}
		?>
		<button type="button"
			class="button sirsc-button-icon tiny f-right <?php echo esc_attr( $cron_class ); ?>"
			name="sirsc-settings-submit"
			id="sirsc-cleanup-button-<?php echo esc_attr( $size ); ?>-cron"
			value="submit"
			title="<?php esc_attr_e( 'Cron task is scheduled', 'sirsc' ); ?>: <?php echo (int) $size_total; ?>"
			onclick="sirscStartCleanupSize( 'start', '<?php echo esc_attr( $size ); ?>', '<?php echo esc_attr( $cpt ); ?>' );">
			<span class="dashicons dashicons-admin-generic"></span>
		</button>
		<?php
	}
	?>

	<button type="button"
		class="button sirsc-button-icon tiny f-right <?php echo esc_attr( $size_class ); ?>"
		name="sirsc-settings-submit"
		id="sirsc-cleanup-button-<?php echo esc_attr( $size ); ?>"
		value="submit"
		title="<?php esc_attr_e( 'Cleanup', 'sirsc' ); ?>: <?php echo (int) $size_total; ?>"
		onclick="sirscStartCleanupSize( 'start', '<?php echo esc_attr( $size ); ?>', '<?php echo esc_attr( $cpt ); ?>' );">
		<span class="dashicons dashicons-no"></span>
		<?php esc_html_e( 'Cleanup', 'sirsc' ); ?>
	</button>
	<?php
}

/**
 * Regenerate button for the settings page, for an image size.
 *
 * @param  string $cpt  Maybe a post type.
 * @param  string $size Image size slug.
 * @return void
 */
function settings_button_size_regenerate( $cpt, $size ) { //phpcs:ignore
	$last_id    = get_bulk_action_last_id( $size, $cpt );
	$cl_resume  = ( ! empty( $last_id ) && PHP_INT_MAX !== $last_id ) ? '' : ' is-hidden';
	$size_class = '';
	$cron_class = ' is-hidden';
	if ( \SIRSC::$use_cron ) {
		$cl_resume = ' is-hidden';
		$args      = [
			'size' => $size,
			'cpt'  => (string) $cpt,
		];

		$hook = \SIRSC\Cron\get_hook( 'regenerate_image_sizes_on_request', $args );
		if ( \SIRSC\Cron\is_scheduled( $hook, $args ) ) {
			$size_class = ' is-hidden';
			$cron_class = '';
		}
		?>
		<button type="button"
			class="button sirsc-button-icon tiny f-right <?php echo esc_attr( $cron_class ); ?> sirsc-wrap-regenerate-buttons"
			name="sirsc-settings-submit"
			id="sirsc-regenerate-button-<?php echo esc_attr( $size ); ?>-cron"
			value="submit"
			title="<?php esc_attr_e( 'Cron task is scheduled', 'sirsc' ); ?>"
			onclick="sirscStartRegenerateSize( 'start', '<?php echo esc_attr( $size ); ?>', '<?php echo esc_attr( $cpt ); ?>' );">
			<span class="dashicons dashicons-admin-generic"></span>
		</button>
		<?php
	}
	?>
	<div id="sirsc-wrap-regenerate-button-<?php echo esc_attr( $size ); ?>"
		class="<?php echo esc_attr( $size_class ); ?> sirsc-wrap-regenerate-buttons">
		<button type="button"
			class="button sirsc-button-icon tiny <?php echo esc_attr( $cl_resume ); ?>"
			id="sirsc-resume-button-<?php echo esc_attr( $size ); ?>"
			name="sirsc-settings-submit"
			value="submit"
			onclick="sirscStartRegenerateSize( 'resume', '<?php echo esc_attr( $size ); ?>', '<?php echo esc_attr( $cpt ); ?>' );">
			<span class="dashicons dashicons-controls-play"></span>
			<?php esc_html_e( 'Resume', 'sirsc' ); ?>
		</button>

		<button type="button"
			class="button sirsc-button-icon tiny button-primary"
			name="sirsc-settings-submit"
			id="sirsc-regenerate-button-<?php echo esc_attr( $size ); ?>"
			value="submit"
			onclick="sirscStartRegenerateSize( 'start', '<?php echo esc_attr( $size ); ?>', '<?php echo esc_attr( $cpt ); ?>' );">
			<span class="dashicons dashicons-update"></span>
			<?php esc_html_e( 'Regenerate All', 'sirsc' ); ?>
		</button>
	</div>
	<?php
}

/**
 * Raw cleanup button for the settings page.
 *
 * @param  string $cpt  Maybe a post type.
 * @param  string $type Cleanup type.
 * @return void
 */
function settings_button_raw_cleanup( $cpt, $type ) { //phpcs:ignore
	$size_class = '';
	$cron_class = ' is-hidden';
	$size_text  = ( 'unused' === $type ) ? __( 'Cleanup Unused', 'sirsc' ) : __( 'Cleanup Raw', 'sirsc' );
	if ( \SIRSC::$use_cron ) {
		$args = [
			'type' => $type,
			'cpt'  => (string) $cpt,
		];

		$hook = \SIRSC\Cron\get_hook( 'raw_cleanup_on_request', $args );
		if ( \SIRSC\Cron\is_scheduled( $hook, $args ) ) {
			$size_class = ' is-hidden';
			$cron_class = '';
		}
		?>
		<button type="button"
			class="button sirsc-button-icon tiny f-right <?php echo esc_attr( $cron_class ); ?>"
			name="sirsc-settings-submit"
			id="sirsc-raw-cleanup-button-<?php echo esc_attr( $type ); ?>-cron"
			value="submit"
			title="<?php esc_attr_e( 'Cron task is scheduled', 'sirsc' ); ?>"
			onclick="sirscStartRawCleanup( 'start', '<?php echo esc_attr( $type ); ?>', '<?php echo esc_attr( $cpt ); ?>' );">
			<span class="dashicons dashicons-admin-generic"></span>
		</button>
		<?php
	}
	?>
	<button type="button"
		name="sirsc-settings-submit"
		id="sirsc-raw-cleanup-button-<?php echo esc_attr( $type ); ?>"
		value="submit"
		class="button sirsc-button-icon tiny f-right <?php echo esc_attr( $size_class ); ?>"
		onclick="sirscStartRawCleanup( 'start', '<?php echo esc_attr( $type ); ?>', '<?php echo esc_attr( $cpt ); ?>' );"
		title="<?php echo esc_attr( $size_text ); ?>">
		<span class="dashicons dashicons-trash"></span>
		<?php echo esc_html( $size_text ); ?>
	</button>
	<?php
}

/**
 * Generate a color from a string.
 *
 * @param  string $string  Initial string.
 * @param  string $format  Color format.
 * @param  float  $percent Precent to alter the color (negative -> darken, positive -> lighten), number between -1,1.
 * @return string
 */
function string2color( $string, $format = 'hex', $percent = 0 ) { //phpcs:ignore
	$string = strtoupper( $string ) . 'ABCDEF0123456789';
	$string = preg_replace( '/[^ABCDEF0123456789]/', '', $string );
	$color  = substr( $string, 0, 6 );
	$r      = substr( $color, 0, 2 );
	$g      = substr( $color, 2, 2 );
	$b      = substr( $color, 4, 2 );
	$hr     = (int) hexdec( $r );
	$hg     = (int) hexdec( $g );
	$hb     = (int) hexdec( $b );

	if ( ! empty( $percent ) && ( 'hex' === $format || 'rgb' === $format ) ) {
		$al  = $percent < 0 ? $hr : 255 - $hr;
		$aa  = ceil( $al * $percent );
		$hrp = dechex( (int) $hr + (int) $aa );
		$hrp = str_pad( $hrp, 2, '0', STR_PAD_LEFT );
		$al  = $percent < 0 ? $hg : 255 - $hg;
		$aa  = ceil( $al * $percent );
		$hgp = dechex( (int) $hg + (int) $aa );
		$hgp = str_pad( $hgp, 2, '0', STR_PAD_LEFT );
		$al  = $percent < 0 ? $hb : 255 - $hb;
		$aa  = ceil( $al * $percent );
		$hbp = dechex( (int) $hb + (int) $aa );
		$hbp = str_pad( $hbp, 2, '0', STR_PAD_LEFT );
	}

	switch ( $format ) {
		case 'rgba':
			if ( empty( $percent ) ) {
				$color = 'rgba(' . $hr . ',' . $hg . ',' . $hb . ', 1)';
			} else {
				$color = 'rgba(' . $hr . ',' . $hg . ',' . $hb . ',' . abs( $percent ) . ')';
			}

			break;

		case 'rgb':
			if ( empty( $percent ) ) {
				$color = 'rgb(' . $hr . ',' . $hg . ',' . $hb . ')';
			} else {
				$color = 'rgb(' . hexdec( $hrp ) . ',' . hexdec( $hgp ) . ',' . hexdec( $hbp ) . ')';
			}
			break;

		case 'hex':
		default:
			if ( empty( $percent ) ) {
				$color = '#' . $r . $g . $b;
			} else {
				$color = '#' . $hrp . $hgp . $hbp;
			}
			break;

	}

	return $color;
}

/**
 * Placeholder preview for image size.
 *
 * @param  string $size  Image size name.
 * @param  bool   $force Force refresh the image.
 * @return void
 */
function placeholder_preview( $size = '', $force = false ) { //phpcs:ignore
	if ( ! empty( $size ) ) {
		$url = \SIRSC\Placeholder\image_placeholder_for_image_size( $size, $force ) . '?v=' . time();
		?>
		<a href="<?php echo esc_url( $url ); ?>" target="_blank">
			<img src="<?php echo esc_url( $url ); ?>" width="80" loading="lazy">
		</a>
		<a onclick="sirscExecuteGetRequest( 'action=sirsc_refresh_placeholder&size=<?php echo esc_attr( $size ); ?>', 'sirsc-placeholder-<?php echo esc_attr( $size ); ?>' );" class="refresh"><span class="dashicons dashicons-update-alt"></span></a>
		<?php
	}
}
