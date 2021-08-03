<?php
/**
 * Helper functions for SIRSC placeholder.
 *
 * @package sirsc
 */

declare( strict_types=1 );

namespace SIRSC\Placeholder;

if ( ! defined( 'SIRSC_PLACEHOLDER_FOLDER' ) ) {
	$dest_url  = SIRSC_PLUGIN_URL . 'assets/placeholders';
	$dest_path = SIRSC_PLUGIN_FOLDER . 'assets/placeholders';
	if ( ! file_exists( $dest_path ) ) {
		@wp_mkdir_p( $dest_path ); //phpcs:ignore
	}
	define( 'SIRSC_PLACEHOLDER_FOLDER', $dest_path );
	define( 'SIRSC_PLACEHOLDER_URL', esc_url( $dest_url ) );
}

if ( ! is_admin() && class_exists( 'SIRSC' ) && ! empty( \SIRSC::$settings['placeholders'] ) ) {
	// For the front side, let's use placeolders if the case.
	if ( ! empty( \SIRSC::$settings['placeholders']['force_global'] ) ) {
		add_filter( 'image_downsize', __NAMESPACE__ . '\\image_downsize_placeholder_force_global', 10, 3 );
	} elseif ( ! empty( \SIRSC::$settings['placeholders']['only_missing'] ) ) {
		add_filter( 'image_downsize', __NAMESPACE__ . '\\image_downsize_placeholder_only_missing', 10, 3 );
	}
}

/**
 * Replace all the front side images retrieved programmatically with wp function with the placeholders instead of the full size image.
 *
 * @param string  $f  The file.
 * @param integer $id The post ID.
 * @param string  $s  The size slug.
 */
function image_downsize_placeholder_force_global( $f, $id, $s ) { //phpcs:ignore
	if ( is_array( $s ) ) {
		$s = implode( 'x', $s );
	}
	$img_url = image_placeholder_for_image_size( $s );
	$size    = \SIRSC::get_all_image_sizes( $s );
	$width   = ( ! empty( $size['width'] ) ) ? $size['width'] : 0;
	$height  = ( ! empty( $size['height'] ) ) ? $size['height'] : 0;
	return [ $img_url, $width, $height, true ];
}

/**
 * Replace the missing images sizes with the placeholders instead of the full size image. As the "image size name" is specified, we know what width and height the resulting image should have. Hence, first, the potential image width and height are matched against the entire set of image sizes defined in order to identify if there is the exact required image either an alternative file with the specific required width and height already generated for that width and height but with another "image size name" in the database or not. Basically, the first step is to identify if there is an image with the required width and height. If that is identified, it will be presented, regardless of the fact that the "image size name" is the requested one or it is not even yet defined for this specific post (due to a later definition of the image in the project development). If the image to be presented is not identified at any level, then the code is trying to identify the appropriate theme placeholder for the requested "image size name". For that we are using the placeholder function with the requested "image size name". If the placeholder exists, then this is going to be presented, else we are logging the missing placeholder alternative that can be added in the image_placeholder_for_image_size function.
 *
 * @param string  $f  The file.
 * @param integer $id The pot ID.
 * @param string  $s  The size slug.
 */
function image_downsize_placeholder_only_missing( $f, $id, $s ) { //phpcs:ignore
	$all_sizes = \SIRSC::get_all_image_sizes();
	if ( 'full' !== $s && is_scalar( $s ) && ! empty( $all_sizes[ $s ] ) ) {
		try {
			$execute    = false;
			$image      = \wp_get_attachment_metadata( $id );
			$filename   = \get_attached_file( $id );
			$rez_img    = \SIRSC\Helper\allow_resize_from_original( $filename, $image, $all_sizes, $s );
			$upload_dir = wp_upload_dir();
			if ( ! empty( $rez_img['found'] ) ) {
				$url         = str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $rez_img['path'] );
				$crop        = ( ! empty( $rez_img['is_crop'] ) ) ? true : false;
				$alternative = [ $url, $rez_img['width'], $rez_img['height'], $crop ];
				return $alternative;
			}
			$request_w   = (int) $all_sizes[ $s ]['width'];
			$request_h   = (int) $all_sizes[ $s ]['height'];
			$alternative = [
				'name'         => $s,
				'file'         => $f,
				'width'        => $request_w,
				'height'       => $request_h,
				'intermediate' => true,
			];
			$found_match = false;
			if ( empty( $image ) ) {
				$image = [];
			}
			$image['width']  = ( ! empty( $image['width'] ) ) ? (int) $image['width'] : 0;
			$image['height'] = ( ! empty( $image['height'] ) ) ? (int) $image['height'] : 0;
			if ( $request_w === (int) $image['width'] && $request_h === (int) $image['height'] && ! empty( $image['file'] ) ) {
				$tmp_file = str_replace( basename( $filename ), basename( $image['file'] ), $filename );
				if ( file_exists( $tmp_file ) ) {
					$folder      = str_replace( $upload_dir['basedir'], '', $filename );
					$old_file    = basename( str_replace( $upload_dir['basedir'], '', $filename ) );
					$folder      = str_replace( $old_file, '', $folder );
					$alternative = [
						'name'         => 'full',
						'file'         => $upload_dir['baseurl'] . $folder . basename( $image['file'] ),
						'width'        => (int) $image['width'],
						'height'       => (int) $image['height'],
						'intermediate' => false,
					];
					$found_match = true;
				}
			}
			if ( ! empty( $image['sizes'] ) ) {
				foreach ( $image['sizes'] as $name => $var ) {
					if ( $found_match ) {
						break;
					}
					if ( $request_w === (int) $var['width'] && $request_h === (int) $var['height'] && ! empty( $var['file'] ) ) {
						$tmp_file = str_replace( basename( $filename ), $var['file'], $filename );
						if ( file_exists( $tmp_file ) ) {
							$folder      = str_replace( $upload_dir['basedir'], '', $filename );
							$old_file    = basename( str_replace( $upload_dir['basedir'], '', $filename ) );
							$folder      = str_replace( $old_file, '', $folder );
							$alternative = [
								'name'         => $name,
								'file'         => $upload_dir['baseurl'] . $folder . $var['file'],
								'width'        => (int) $var['width'],
								'height'       => (int) $var['height'],
								'intermediate' => true,
							];
							$found_match = true;
							break;
						}
					}
				}
			}
			if ( ! empty( $alternative ) && $found_match ) {
				$placeholder = [ $alternative['file'], $alternative['width'], $alternative['height'], $alternative['intermediate'] ];
				return $placeholder;
			} else {
				$img_url = image_placeholder_for_image_size( $s );
				if ( ! empty( $img_url ) ) {
					$width           = (int) $request_w;
					$height          = (int) $request_w;
					$is_intermediate = true;
					$placeholder     = [ $img_url, $width, $height, $is_intermediate ];
					return $placeholder;
				} else {
					return;
				}
			}
		} catch ( ErrorException $e ) {
			error_log( 'sirsc exception ' . print_r( $e, 1 ) ); //phpcs:ignore
		}
	}
}

/**
 * Generate a placeholder image for a specified image size name.
 *
 * @param string  $selected_size The selected image size slug.
 * @param boolean $force_update  True is the update is forced, to clear the cache.
 */
function image_placeholder_for_image_size( $selected_size, $force_update = false ) { //phpcs:ignore
	if ( empty( $selected_size ) ) {
		$selected_size = 'full';
	}

	$alternative = \SIRSC\Helper\maybe_match_size_name_by_width_height( $selected_size );
	if ( ! is_scalar( $selected_size ) ) {
		if ( ! empty( $alternative ) ) {
			$selected_size = $alternative;
		} else {
			$selected_size = implode( 'x', $selected_size );
		}
	}

	$dest     = realpath( SIRSC_PLACEHOLDER_FOLDER ) . '/' . $selected_size . '.png';
	$dest_url = esc_url( SIRSC_PLACEHOLDER_URL . '/' . $selected_size . '.png' );

	if ( file_exists( $dest ) && ! $force_update ) {
		// Return the found image url.
		return $dest_url;
	}

	if ( file_exists( $dest ) && $force_update ) {
		@unlink( $dest ); //phpcs:ignore
	}

	$alls     = \SIRSC::get_all_image_sizes_plugin();
	$dest_url = url( $alls, $dest, $dest_url, $selected_size, $alternative );

	return $dest_url;
}

/**
 * Compute placeholder url.
 *
 * @param  array  $alls          All image sizes.
 * @param  string $dest          The destination path.
 * @param  string $dest_url      The destination url.
 * @param  string $selected_size The intended image size.
 * @param  string $alternative   The alternative image size.
 * @return string
 */
function url( $alls, $dest, $dest_url, $selected_size, $alternative ) { //phpcs:ignore
	if ( empty( $alls ) ) {
		if ( class_exists( 'SIRSC' ) ) {
			$alls = \SIRSC::get_all_image_sizes_plugin();
		}
	}

	$fallback = \SIRSC::$limit9999;

	$iw = 0;
	$ih = 0;
	$ew = 0;
	$eh = 0;

	$size_sel = $selected_size;
	if ( 'full' === $selected_size ) {
		// Compute the full fallback for a width and height.
		$size_sel = 'full';
		if ( ! empty( $alternative ) && 'full' !== $alternative ) {
			$size_sel = $alternative;
		} elseif ( ! empty( $alls['large'] ) ) {
			$size_sel = 'large';
		} elseif ( ! empty( $alls['medium_large'] ) ) {
			$size_sel = 'medium_large';
		}
	}
	if ( ! empty( $alls[ $size_sel ] ) ) {
		$size = $alls[ $size_sel ];
		$iw   = (int) $size['width'];
		$ih   = (int) $size['height'];
		if ( ! empty( $size['width'] ) && empty( $size['height'] ) ) {
			$ih = $iw;
		} elseif ( empty( $size['width'] ) && ! empty( $size['height'] ) ) {
			$iw = $ih;
		}
	} else {
		$s  = explode( 'x', $size_sel );
		$iw = ( ! empty( $s[0] ) ) ? (int) $s[0] : 0;
		$iw = ( empty( $iw ) ) ? $fallback : $iw;
		$ih = ( ! empty( $s[1] ) ) ? (int) $s[1] : 0;
		$ih = ( empty( $ih ) ) ? $fallback : $ih;
	}

	$ew = $iw;
	$eh = $ih;
	if ( $iw >= 9999 ) {
		$iw = $fallback;
		$ew = '0';
	}
	if ( $ih >= 9999 ) {
		$ih = $fallback;
		$eh = '0';
	}

	if ( ! wp_is_writable( SIRSC_PLACEHOLDER_FOLDER ) ) {
		// By default set the dummy, the folder is not writtable.
		$dest_url = make_placeholder_dummy( $dest, $iw, $ih, $ew, $eh, $selected_size );
		return $dest_url;
	} else {
		$dest_url = make_placeholder_dummy( $dest, $iw, $ih, $ew, $eh, $selected_size );
		if ( ! file_exists( $dest ) ) {
			if ( function_exists( 'imagettfbbox' ) ) {
				make_placeholder_imagettftext( $dest, $iw, $ih, $selected_size, $ew, $eh );
			} elseif ( class_exists( 'Imagick' ) ) {
				make_placeholder_imagick( $dest, $iw, $ih, $selected_size, $ew, $eh );
			} elseif ( function_exists( 'imagestring' ) ) {
				make_placeholder_imagestring( $dest, $iw, $ih, $selected_size, $ew, $eh );
			}
		}
	}

	return $dest_url;
}

/**
 * Make placeholder url.
 *
 * @param  string $dest Images destination.
 * @param  int    $iw   Image width.
 * @param  int    $ih   Image height.
 * @param  int    $sw   Width text.
 * @param  int    $sh   Height text.
 * @param  string $name Images size name.
 * @return string
 */
function make_placeholder_dummy( $dest, $iw, $ih, $sw, $sh, $name = '' ) { //phpcs:ignore
	$name = str_replace( '-', '+', $name );
	$name = str_replace( '_', '+', $name );
	$url  = 'https://dummyimage.com/' . $iw . 'x' . $ih . '/' . substr( str_shuffle( 'ABCDEF0123456789' ), 0, 6 ) . '/ffffff&fsize=7&size=7&text=++' . $name . '+' . $sw . 'x' . $sh . '+';

	if ( ! file_exists( $dest ) && wp_is_writable( SIRSC_PLACEHOLDER_FOLDER ) ) {
		// Let's fetch the remote image.
		$response = wp_safe_remote_get( $url );
		$url      = '';
		$code     = wp_remote_retrieve_response_code( $response );
		if ( 200 === $code ) {
			// Seems that we got a successful response from the remore URL.
			$content_type = wp_remote_retrieve_header( $response, 'content-type' );
			if ( ! empty( $content_type ) && substr_count( $content_type, 'image/' ) ) {
				// Seems that the content type is an image, let's get the body as the file content.
				$file_content = wp_remote_retrieve_body( $response );
			}
		}

		if ( ! empty( $file_content ) ) {
			if ( @file_put_contents( $dest, $file_content ) ) { //phpcs:ignore
				$url = str_replace( SIRSC_PLACEHOLDER_FOLDER, SIRSC_PLACEHOLDER_URL, $dest );
			}
		}
	}

	return $url;
}

/**
 * Make placeholder with imagettftext.
 *
 * @param  string  $dest Images destination.
 * @param  integer $iw   Image width.
 * @param  integer $ih   Image height.
 * @param  string  $name Image size name.
 * @param  integer $sw   Width text.
 * @param  integer $sh   Height text.
 * @return void
 */
function make_placeholder_imagettftext( $dest, $iw, $ih, $name, $sw, $sh ) { //phpcs:ignore
	// phpcs:disable
	$im    = @imagecreatetruecolor( $iw, $ih );
	$white = @imagecolorallocate( $im, 255, 255, 255 );
	$rand  = @imagecolorallocate( $im, wp_rand( 0, 150 ), wp_rand( 0, 150 ), wp_rand( 0, 150 ) );
	@imagefill( $im, 0, 0, $rand );
	$font = @realpath( SIRSC_PLUGIN_FOLDER . '/assets/fonts' ) . '/arial.ttf';
	@imagettftext( $im, 6.5, 0, 2, 10, $white, $font, 'placeholder' );
	@imagettftext( $im, 6.5, 0, 2, 20, $white, $font, $name );
	@imagettftext( $im, 6.5, 0, 2, 30, $white, $font, $sw . 'x' . $sh );
	@imagepng( $im, $dest, 9 );
	@imagedestroy( $im );
	// phpcs:enable
}

/**
 * Make placeholder with Imagick.
 *
 * @param  string  $dest Images destination.
 * @param  integer $iw   Image width.
 * @param  integer $ih   Image height.
 * @param  string  $name Image size name.
 * @param  integer $sw   Width text.
 * @param  integer $sh   Height text.
 * @return void
 */
function make_placeholder_imagick( $dest, $iw, $ih, $name, $sw, $sh ) { //phpcs:ignore
	$im    = new \Imagick();
	$draw  = new \ImagickDraw();
	$pixel = new \ImagickPixel( '#' . wp_rand( 10, 99 ) . wp_rand( 10, 99 ) . wp_rand( 10, 99 ) );
	$im->newImage( $iw, $ih, $pixel );
	$draw->setFillColor( '#FFFFFF' );
	$draw->setFont( SIRSC_PLUGIN_FOLDER . '/assets/fonts/arial.ttf' );
	$draw->setFontSize( 12 );
	$draw->setGravity( \Imagick::GRAVITY_CENTER );
	$im->annotateImage( $draw, 0, 0, 0, $sw . 'x' . $sh );
	$im->setImageFormat( 'png' );
	$im->writeimage( $dest );
}

/**
 * Make placeholder with imagestring.
 *
 * @param  string  $dest Images destination.
 * @param  integer $iw   Image width.
 * @param  integer $ih   Image height.
 * @param  string  $name Image size name.
 * @param  integer $sw   Width text.
 * @param  integer $sh   Height text.
 * @return void
 */
function make_placeholder_imagestring( $dest, $iw, $ih, $name, $sw, $sh ) { //phpcs:ignore
	$im    = imagecreatetruecolor( $iw, $ih );
	$white = imagecolorallocate( $im, 255, 255, 255 );
	$rand  = imagecolorallocate( $im, wp_rand( 0, 150 ), wp_rand( 0, 150 ), wp_rand( 0, 150 ) );
	imagefill( $im, 0, 0, $rand );
	imagestring( $im, 2, 2, 2, 'placeholder', $white );
	imagestring( $im, 2, 2, 12, $name, $white );
	if ( $name !== $sw . 'x' . $sh ) {
		imagestring( $im, 2, 2, 22, $sw . 'x' . $sh, $white );
	}
	imagepng( $im, $dest, 9 );
	imagedestroy( $im );
}
