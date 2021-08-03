<?php
/**
 * Plugin Name: Image Regenerate & Select Crop
 * Plugin URI: https://iuliacazan.ro/image-regenerate-select-crop/
 * Description: Regenerate and crop images, details and actions for image sizes registered and image sizes generated, clean up, placeholders, custom rules, register new image sizes, crop medium settings, WP-CLI commands, optimize images.
 * Text Domain: sirsc
 * Domain Path: /langs
 * Version: 6.0.2
 * Author: Iulia Cazan
 * Author URI: https://profiles.wordpress.org/iulia-cazan
 * Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=JJA37EHZXWUTJ
 * License: GPL2
 *
 * @package ic-devops
 *
 * Copyright (C) 2014-2021 Iulia Cazan
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

define( 'SIRSC_PLUGIN_VER', 6.02 );
define( 'SIRSC_PLUGIN_FOLDER', plugin_dir_path( __FILE__ ) );
define( 'SIRSC_PLUGIN_DIR', SIRSC_PLUGIN_FOLDER );
define( 'SIRSC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SIRSC_PLUGIN_SLUG', 'sirsc' );
define( 'SIRSC_ASSETS_VER', '20210726.1858' );
define( 'SIRSC_ADONS_FOLDER', SIRSC_PLUGIN_DIR . 'adons/' );

require_once SIRSC_PLUGIN_FOLDER . 'inc/debug.php';
require_once SIRSC_PLUGIN_FOLDER . 'inc/action.php';
require_once SIRSC_PLUGIN_FOLDER . 'inc/helper.php';
require_once SIRSC_PLUGIN_FOLDER . 'inc/admin.php';
require_once SIRSC_PLUGIN_FOLDER . 'inc/iterator.php';
require_once SIRSC_PLUGIN_FOLDER . 'inc/integration.php';
require_once SIRSC_PLUGIN_FOLDER . 'inc/calls.php';
require_once SIRSC_PLUGIN_FOLDER . 'inc/adons.php';
require_once SIRSC_PLUGIN_FOLDER . 'inc/wp-cli.php';
require_once SIRSC_PLUGIN_FOLDER . 'inc/cron.php';

/**
 * Class for Image Regenerate & Select Crop.
 */
class SIRSC_Image_Regenerate_Select_Crop {
	const PLUGIN_NAME        = 'Image Regenerate & Select Crop';
	const PLUGIN_SUPPORT_URL = 'https://wordpress.org/support/plugin/image-regenerate-select-crop/';
	const PLUGIN_TRANSIENT   = 'sirsc-plugin-notice';
	const BULK_PROCESS_DELAY = 800;
	const BULK_CLEANUP_ITEMS = 10;
	const PLUGIN_PAGE_SLUG   = 'image-regenerate-select-crop-settings';
	const DEFAULT_QUALITY    = 82;

	/**
	 * Class instance.
	 *
	 * @var object
	 */
	private static $instance;
	/**
	 * The plugin is configured.
	 *
	 * @var boolean
	 */
	public static $is_configured = false;
	/**
	 * Plugin settings.
	 *
	 * @var array
	 */
	public static $settings;
	/**
	 * Plugin user custom rules.
	 *
	 * @var array
	 */
	public static $user_custom_rules;
	/**
	 * Plugin user custom usable rules.
	 *
	 * @var array
	 */
	public static $user_custom_rules_usable;
	/**
	 * Excluded post types.
	 *
	 * @var array
	 */
	public static $exclude_post_type = [];
	/**
	 * Limit the posts.
	 *
	 * @var integer
	 */
	public static $limit9999 = 300;
	/**
	 * Crop positions.
	 *
	 * @var array
	 */
	public static $crop_positions = [];
	/**
	 * Plugin URL.
	 *
	 * @var string
	 */
	public static $plugin_url = '';
	/**
	 * Plugin native sizes.
	 *
	 * @var array
	 */
	private static $wp_native_sizes = [ 'thumbnail', 'medium', 'medium_large', 'large' ];
	/**
	 * Plugin debug to file.
	 *
	 * @var boolean
	 */
	public static $debug = false;
	/**
	 * Plugin adons list.
	 *
	 * @var array
	 */
	public static $adons;
	/**
	 * Plugin menu items.
	 *
	 * @var array
	 */
	public static $menu_items;
	/**
	 * Upscale width value.
	 *
	 * @var integer
	 */
	public static $upscale_new_w;
	/**
	 * Upscale height value.
	 *
	 * @var array
	 */
	public static $upscale_new_h;
	/**
	 * Core version.
	 *
	 * @var float
	 */
	public static $wp_ver = 5.24;
	/**
	 * Use cron tasks.
	 *
	 * @var bool
	 */
	public static $use_cron = false;
	/**
	 * Is cron running.
	 *
	 * @var bool
	 */
	public static $is_cron = false;
	/**
	 * Get active object instance
	 *
	 * @return object
	 */
	public static function get_instance() { //phpcs:ignore
		if ( ! self::$instance ) {
			self::$instance = new SIRSC_Image_Regenerate_Select_Crop();
		}
		return self::$instance;
	}

	/**
	 * Class constructor. Includes constants, includes and init method.
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Run action and filter hooks.
	 */
	private function init() {
		$called = get_called_class();

		self::$settings = get_option( 'sirsc_settings' );
		self::$use_cron = ( ! empty( self::$settings['cron_bulk_execution'] ) ) ? true : false;
		self::$is_cron  = ( defined( 'DOING_CRON' ) && DOING_CRON ) ? true : false;

		self::get_default_user_custom_rules();
		self::$is_configured     = ( ! empty( self::$settings ) ) ? true : false;
		self::$exclude_post_type = [ 'nav_menu_item', 'revision', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request', 'attachment', 'wp_block', 'scheduled-action', 'shop_order', 'shop_order_refund', 'shop_coupon', 'wpcf7_contact_form', 'wp_template' ];

		self::$wp_ver = (float) get_bloginfo( 'version', 'display' );
		if ( is_admin() ) {
			if ( true === self::$debug && file_exists( SIRSC_PLUGIN_FOLDER . '/sirsc-hooks-tester.php' ) ) {
				include_once SIRSC_PLUGIN_FOLDER . '/sirsc-hooks-tester.php';
			}

			add_action( 'init', [ $called, 'maybe_save_settings' ], 0 );
			add_action( 'wp_ajax_sirsc_autosubmit_save', [ $called, 'maybe_save_settings' ] );

			if ( self::$wp_ver >= 5.0 ) {
				add_filter( 'admin_post_thumbnail_html', '\SIRSC\Admin\append_image_generate_button_tiny', 60, 3 );
			} else {
				add_action( 'image_regenerate_select_crop_button', [ $called, 'image_regenerate_select_crop_button' ] );
				// The init action that is used with older core versions.
				add_action( 'init', [ $called, 'register_image_button' ] );
			}

			add_action( 'wp_ajax_sirsc_show_actions_result', [ $called, 'show_actions_result' ] );
			add_action( 'plugins_loaded', [ $called, 'load_textdomain' ] );

			self::$crop_positions = [
				'lt' => __( 'Left/Top', 'sirsc' ),
				'ct' => __( 'Center/Top', 'sirsc' ),
				'rt' => __( 'Right/Top', 'sirsc' ),
				'lc' => __( 'Left/Center', 'sirsc' ),
				'cc' => __( 'Center/Center', 'sirsc' ),
				'rc' => __( 'Right/Center', 'sirsc' ),
				'lb' => __( 'Left/Bottom', 'sirsc' ),
				'cb' => __( 'Center/Bottom', 'sirsc' ),
				'rb' => __( 'Right/Bottom', 'sirsc' ),
			];

			self::$plugin_url = admin_url( 'admin.php?page=image-regenerate-select-crop-settings' );
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), [ $called, 'plugin_action_links' ] );
			add_action( 'wp_ajax_plugin-deactivate-notice-sirsc', [ $called, 'admin_notices_cleanup' ] );
			add_action( 'sirsc_action_after_image_delete', [ $called, 'refresh_extra_info_footer' ] );
			add_filter( 'admin_post_thumbnail_size', [ $called, 'admin_featured_size' ], 60, 3 );
		}

		// This is global, as the image sizes can be also registerd in the themes or other plugins.
		add_filter( 'intermediate_image_sizes_advanced', [ $called, 'filter_ignore_global_image_sizes' ], 10, 2 );
		add_filter( 'wp_generate_attachment_metadata', [ $called, 'wp_generate_attachment_metadata' ], 10, 2 );
		add_action( 'added_post_meta', [ $called, 'process_filtered_attachments' ], 10, 4 );
		add_filter( 'big_image_size_threshold', [ $called, 'big_image_size_threshold_forced' ], 20, 4 );
		add_action( 'delete_attachment', [ $called, 'on_delete_attachment' ] );
		add_action( 'after_setup_theme', [ $called, 'maybe_register_custom_image_sizes' ] );
		add_filter( 'image_size_names_choose', [ $called, 'custom_image_size_names_choose' ], 60 );
		add_action( 'plugins_loaded', [ $called, 'plugin_ver_check' ] );
		add_filter( 'wp_php_error_message', [ $called, 'assess_background_errors' ], 60, 2 );
	}

	/**
	 * Initiate the default structure for the custom rules.
	 *
	 * @return array
	 */
	public static function init_user_custom_rules() : array {
		$default = [];
		for ( $i = 1; $i <= 20; $i ++ ) {
			$default[ $i ] = [
				'type'     => '',
				'value'    => '',
				'original' => '',
				'only'     => [],
				'suppress' => '',
			];
		}
		return $default;
	}

	/**
	 * Load the user custom rules if available.
	 *
	 * @return void
	 */
	public static function get_default_user_custom_rules() {
		$default = self::init_user_custom_rules();
		$opt     = get_option( 'sirsc_user_custom_rules' );
		if ( ! empty( $opt ) ) {
			$opt = maybe_unserialize( $opt );
			if ( is_array( $opt ) ) {
				foreach ( $opt as $key => $value ) {
					if ( is_array( $value ) ) {
						$default[ $key ] = array_merge( $default[ $key ], $value );
					}
				}
			}
		}

		self::$user_custom_rules        = $default;
		self::$user_custom_rules_usable = get_option( 'sirsc_user_custom_rules_usable' );
	}

	/**
	 * The actions to be executed when the plugin is updated.
	 *
	 * @return void
	 */
	public static function plugin_ver_check() {
		$db_version = get_option( 'sirsc_db_version', 0 );
		if ( SIRSC_PLUGIN_VER !== (float) $db_version ) {
			update_option( 'sirsc_db_version', SIRSC_PLUGIN_VER );
			self::activate_plugin();
		}
	}

	/**
	 * The actions to be executed when the plugin is deactivated.
	 */
	public static function activate_plugin() {
		set_transient( self::PLUGIN_TRANSIENT, true );
		set_transient( self::PLUGIN_TRANSIENT . '_adons_notice', true );
	}

	/**
	 * Execute notices cleanup.
	 *
	 * @param  boolean $ajax Is AJAX call.
	 * @return void
	 */
	public static function admin_notices_cleanup( $ajax = true ) { //phpcs:ignore
		// Delete transient, only display this notice once.
		delete_transient( self::PLUGIN_TRANSIENT );

		if ( true === $ajax ) {
			// No need to continue.
			wp_die();
		}
	}

	/**
	 * The actions to be executed when the plugin is deactivated.
	 */
	public static function deactivate_plugin() {
		global $wpdb;

		if ( ! empty( self::$settings['leave_settings_behind'] ) ) {
			// Cleanup only the notifications.
			self::admin_notices_cleanup( false );
			return;
		}

		delete_option( 'sirsc_override_medium_size' );
		delete_option( 'sirsc_override_large_size' );
		delete_option( 'sirsc_admin_featured_size' );
		delete_option( 'medium_crop' );
		delete_option( 'medium_large_crop' );
		delete_option( 'large_crop' );
		delete_option( 'sirsc_use_custom_image_sizes' );
		delete_option( 'sirsc_monitor_errors' );

		$rows = $wpdb->get_results( //phpcs:ignore
			$wpdb->prepare( //phpcs:ignore
				'SELECT option_name FROM ' . $wpdb->options . ' WHERE option_name like %s OR option_name like %s OR option_name like %s ',
				$wpdb->esc_like( 'sirsc_settings' ) . '%',
				$wpdb->esc_like( 'sirsc_types' ) . '%',
				$wpdb->esc_like( 'sirsc_user_custom_rules' ) . '%'
			),
			ARRAY_A
		);

		if ( ! empty( $rows ) && is_array( $rows ) ) {
			foreach ( $rows as $v ) {
				delete_option( $v['option_name'] );
			}
		}
		self::admin_notices_cleanup( false );
	}

	/**
	 * Maybe register the image sizes.
	 */
	public static function maybe_register_custom_image_sizes() {
		$all = maybe_unserialize( get_option( 'sirsc_use_custom_image_sizes' ) );
		if ( empty( $all['sizes'] ) ) {
			// Fail-fast, no custom image sizes registered.
			return;
		} else {
			foreach ( $all['sizes'] as $i => $value ) {
				if ( ! empty( $value['name'] ) && is_scalar( $value['name'] )
					&& ( ! empty( $value['width'] ) || ! empty( $value['height'] ) ) ) {
					$crop = ( ! empty( $value['crop'] ) ) ? true : false;
					add_image_size( $value['name'], (int) $value['width'], (int) $value['height'], $crop );
				}
			}
		}
	}

	/**
	 * Exclude globally the image sizes selected in the settings from being generated on upload.
	 *
	 * @param array $sizes    The computed image sizes.
	 * @param array $metadata The image metadata.
	 * @return array
	 */
	public static function filter_ignore_global_image_sizes( $sizes, $metadata = [] ) { //phpcs:ignore
		if ( empty( $sizes ) ) {
			$sizes = get_intermediate_image_sizes();
		}
		if ( ! empty( self::$settings['complete_global_ignore'] ) ) {
			foreach ( self::$settings['complete_global_ignore'] as $s ) {
				if ( isset( $sizes[ $s ] ) ) {
					unset( $sizes[ $s ] );
				} else {
					$k = array_keys( $sizes, $s, true );
					if ( ! empty( $k[0] ) ) {
						unset( $sizes[ $k[0] ] );
					}
				}
			}
		}

		$check_size = serialize( $sizes ); //phpcs:ignore
		if ( substr_count( $check_size, 'width' ) && substr_count( $check_size, 'height' ) ) {
			// Fail-fast here.
			return [];
		}

		$sizes = self::filter_some_more_based_on_metadata( $sizes, $metadata );
		return $sizes;
	}

	/**
	 * Filter the sizes based on the metadata.
	 *
	 * @param array $sizes    Images sizes.
	 * @param array $metadata Uploaded image metadata.
	 * @return array
	 */
	public static function filter_some_more_based_on_metadata( $sizes, $metadata = [] ) { //phpcs:ignore
		if ( empty( $metadata['file'] ) ) {
			// Fail-fast, no upload.
			return $sizes;
		} else {
			if ( ! empty( $metadata['sizes'] ) ) {
				foreach ( $metadata['sizes'] as $key => $value ) {
					unset( $sizes[ $key ] );
					if ( in_array( $key, $sizes, true ) ) {
						$sizes = array_diff( $sizes, [ $key ] );
					}
				}
			}
			if ( empty( $sizes ) ) {
				return [];
			}
		}

		$args = [
			'meta_key'       => '_wp_attached_file', //phpcs:ignore
			'meta_value'     => $metadata['file'], //phpcs:ignore
			'post_status'    => 'any',
			'post_type'      => 'attachment',
			'posts_per_page' => 1,
			'fields'         => 'ids',
		];
		$post = new WP_Query( $args );
		if ( ! empty( $post->posts[0] ) ) {
			// The attachment was found.
			self::load_settings_for_post_id( $post->posts[0] );

			if ( ! empty( self::$settings['restrict_sizes_to_these_only'] ) ) {
				foreach ( $sizes as $s => $v ) {
					if ( ! in_array( $s, self::$settings['restrict_sizes_to_these_only'], true ) ) {
						unset( $sizes[ $s ] );
					}
				}
			}
		}
		wp_reset_postdata();

		return $sizes;
	}

	/**
	 * Returns an array of all the native image sizes.
	 *
	 * @return array
	 */
	public static function get_native_image_sizes() { //phpcs:ignore
		return self::$wp_native_sizes;
	}

	/**
	 * Returns an array of all the image sizes registered in the application.
	 *
	 * @param string $size Image size slug.
	 */
	public static function get_all_image_sizes( $size = '' ) { //phpcs:ignore
		global $_wp_additional_image_sizes;
		$sizes = [];

		$get_intermediate_image_sizes = get_intermediate_image_sizes();
		// Create the full array with sizes and crop info.
		foreach ( $get_intermediate_image_sizes as $_size ) {
			if ( in_array( $_size, self::$wp_native_sizes, true ) ) {
				$sizes[ $_size ]['width']  = get_option( $_size . '_size_w' );
				$sizes[ $_size ]['height'] = get_option( $_size . '_size_h' );
				$sizes[ $_size ]['crop']   = (bool) get_option( $_size . '_crop' );
			} elseif ( isset( $_wp_additional_image_sizes[ $_size ] ) ) {
				$sizes[ $_size ] = [
					'width'  => $_wp_additional_image_sizes[ $_size ]['width'],
					'height' => $_wp_additional_image_sizes[ $_size ]['height'],
					'crop'   => $_wp_additional_image_sizes[ $_size ]['crop'],
				];
			}
		}

		if ( ! empty( $sizes ) ) {
			$all = [];
			foreach ( $sizes as $name => $details ) {
				if ( ! empty( $name ) ) {
					$all[ $name ] = $details;
				}
			}
			$sizes = $all;
		}

		if ( ! empty( $size ) && is_scalar( $size ) ) { // Get only 1 size if found.
			if ( ! empty( $sizes ) && isset( $sizes[ $size ] ) ) {
				return $sizes[ $size ];
			} else {
				return false;
			}
		}

		return $sizes;
	}

	/**
	 * Returns an array of all the image sizes registered in the application filtered by the plugin settings and for a specified image size name.
	 *
	 * @param string  $size   Image size slug.
	 * @param boolean $strict True if needs to return only the strict available from settings.
	 * @return  array|boolean
	 */
	public static function get_all_image_sizes_plugin( $size = '', $strict = false ) { //phpcs:ignore
		$sizes = self::get_all_image_sizes( $size );
		if ( ! empty( self::$settings['exclude'] ) ) {
			$new_sizes = [];
			foreach ( $sizes as $k => $si ) {
				if ( ! in_array( $k, self::$settings['exclude'], true ) ) {
					$new_sizes[ $k ] = $si;
				}
			}
			$sizes = $new_sizes;
		}
		if ( true === $strict ) {
			if ( ! empty( self::$settings['complete_global_ignore'] ) ) {
				foreach ( self::$settings['complete_global_ignore'] as $ignored ) {
					unset( $sizes[ $ignored ] );
				}
			}
			if ( ! empty( self::$settings['restrict_sizes_to_these_only'] ) ) {
				foreach ( $sizes as $s => $v ) {
					if ( ! in_array( $s, self::$settings['restrict_sizes_to_these_only'], true ) ) {
						unset( $sizes[ $s ] );
					}
				}
			}
		}

		if ( $size ) { // Get only 1 size if found.
			if ( isset( $sizes[ $size ] ) ) {
				// Pick it from the list.
				return $sizes[ $size ];
			} elseif ( isset( $sizes['width'] ) && isset( $sizes['height'] ) && isset( $sizes['crop'] ) ) {
				// This must be the requested size.
				return $sizes;
			} else {
				return false;
			}
		}

		return $sizes;
	}

	/**
	 * Load text domain for internalization.
	 */
	public static function load_textdomain() {
		load_plugin_textdomain( 'sirsc', false, basename( dirname( __FILE__ ) ) . '/langs/' );
	}

	/**
	 * Assess the background errors.
	 *
	 * @param string $message Error message.
	 * @param array  $error   The error array.
	 * @return string
	 */
	public static function assess_background_errors( $message, $error ) { //phpcs:ignore
		if ( ! empty( $error ) || ! empty( $message ) ) {
			if ( ! empty( $error['message'] ) && substr_count( $error['message'], 'memor' ) ) {

				$monitor = get_option( 'sirsc_monitor_errors', [] );
				if ( empty( $monitor['error'] ) ) {
					$monitor['error'] = [];
				}
				if ( empty( $monitor['schedule'] ) ) {
					$monitor['schedule'] = [];
				}
				if ( ! empty( $monitor['schedule'] ) ) {
					$keys = array_keys( $monitor['schedule'] );
					$id   = $keys[ count( $keys ) - 1 ];

					$monitor['error'][ $id ] = $monitor['schedule'][ $id ] . ' ' . trim( $message . ' ' . $error['message'] );
				}

				update_option( 'sirsc_monitor_errors', $monitor );
			}
		}
		return $message;
	}

	/**
	 * Maybe execute the options update if the nonce is valid, then redirect.
	 *
	 * @return void
	 */
	public static function maybe_save_settings() {
		$notice = get_option( 'sirsc_settings_updated' );
		if ( ! empty( $notice ) ) {
			add_action( 'admin_notices', '\SIRSC\Admin\\on_settings_update_notice', 10 );
			delete_option( 'sirsc_settings_updated' );
		}

		$nonce = filter_input( INPUT_POST, '_sirsc_settings_nonce', FILTER_DEFAULT );
		if ( empty( $nonce ) ) {
			return;
		}
		if ( ! empty( $nonce ) ) {
			if ( ! wp_verify_nonce( $nonce, '_sirsc_settings_save' ) || ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'Action not allowed.', 'sirsc' ), esc_html__( 'Security Breach', 'sirsc' ) );
			}

			$data = filter_input( INPUT_POST, 'sirsc', FILTER_SANITIZE_STRING, FILTER_REQUIRE_ARRAY );
			if ( ! empty( $data['trigger'] ) ) {
				if ( 'sirsc-settings-advanced-rules' === $data['trigger'] ) {
					// Custom rules update.
					self::maybe_update_user_custom_rules();
					self::get_default_user_custom_rules();

				} else {
					// Save the general settings.
					self::maybe_save_general_settings();
					self::$settings = get_option( 'sirsc_settings' );
				}
			}

			$is_ajax = filter_input( INPUT_POST, 'sirsc_autosubmit_save', FILTER_DEFAULT );
			if ( ! empty( $is_ajax ) ) {
				wp_die();
				die();
			}
		}
	}

	/**
	 * Get settings list.
	 *
	 * @return array
	 */
	public static function get_settings_list() : array {
		$settings = [
			'exclude'                  => [],
			'unavailable'              => [],
			'force_original_to'        => '',
			'complete_global_ignore'   => [],
			'placeholders'             => [],
			'default_crop'             => [],
			'default_quality'          => [],
			'enable_perfect'           => false,
			'enable_upscale'           => false,
			'regenerate_missing'       => false,
			'disable_woo_thregen'      => false,
			'sync_settings_ewww'       => false,
			'listing_tiny_buttons'     => false,
			'force_size_choose'        => false,
			'leave_settings_behind'    => false,
			'listing_show_summary'     => false,
			'regenerate_only_featured' => false,
			'bulk_actions_descending'  => false,
			'enable_debug_log'         => false,
			'cron_bulk_execution'      => false,
			'cron_batch_regenerate'    => 30,
			'cron_batch_cleanup'       => 30,
		];

		return $settings;
	}

	/**
	 * Get settings list.
	 *
	 * @param string $cpt Post type.
	 * @return array
	 */
	public static function prepare_settings_list( $cpt = '' ) : array { //phpcs:ignore
		$list            = self::get_settings_list();
		$global_settings = maybe_unserialize( get_option( 'sirsc_settings' ) );
		$global_settings = wp_parse_args( $global_settings, $list );

		if ( ! empty( $cpt ) ) {
			$common       = self::common_settings();
			$cpt_settings = maybe_unserialize( get_option( 'sirsc_settings_' . $cpt ) );
			$cpt_settings = wp_parse_args( $cpt_settings, $list );
			$cpt_settings = array_merge( $cpt_settings, $common['values'] );
			return $cpt_settings;
		}

		return $global_settings;
	}

	/**
	 * Get common settings.
	 *
	 * @return array
	 */
	public static function common_settings() : array {
		$list = [
			'placeholders',
			'disable_woo_thregen',
			'sync_settings_ewww',
			'listing_tiny_buttons',
			'leave_settings_behind',
			'force_size_choose',
			'listing_show_summary',
			'enable_debug_log',
			'cron_bulk_execution',
			'cron_batch_regenerate',
			'cron_batch_cleanup',
			'bulk_actions_descending',
		];

		$settings = maybe_unserialize( get_option( 'sirsc_settings' ) );
		$common   = [];
		if ( ! empty( $list ) ) {
			foreach ( $list as $item ) {
				if ( isset( $settings[ $item ] ) ) {
					$common[ $item ] = $settings[ $item ];
				}
			}
		}

		return [
			'list'   => $list,
			'values' => $common,
		];
	}

	/**
	 * Execute the update of the general settings.
	 *
	 * @return void
	 */
	public static function maybe_save_general_settings() {
		$to_update = filter_input( INPUT_POST, '_sirsc_settings_submit', FILTER_DEFAULT );
		if ( ! empty( $to_update ) ) {
			$settings = self::get_settings_list();
			$data     = filter_input( INPUT_POST, 'sirsc', FILTER_SANITIZE_STRING, FILTER_REQUIRE_ARRAY );
			if ( ! empty( $data['trigger'] ) ) {
				if ( 'sirsc-settings-reset' === $data['trigger'] ) {
					$list = get_option( 'sirsc_types_options' );
					if ( ! empty( $list ) && is_array( $list ) ) {
						foreach ( $list as $item ) {
							delete_option( 'sirsc_settings_' . $item );
						}
					}
					delete_option( 'sirsc_settings' );
					delete_option( 'sirsc_user_custom_rules' );
					delete_option( 'sirsc_user_custom_rules_usable' );
					update_option( 'sirsc_settings_updated', current_time( 'timestamp' ) ); //phpcs:ignore
					self::$settings = get_option( 'sirsc_settings' );

					\SIRSC\Cron\maybe_remove_tasks();
					wp_die();
				} elseif ( 'sirsc-settings-cancel-crons' === $data['trigger'] ) {
					\SIRSC\Cron\maybe_remove_tasks();
					wp_die();
				}
			}

			if ( ! empty( $data['placeholders'] ) ) {
				if ( 'force_global' === $data['placeholders'] ) {
					$settings['placeholders']['force_global'] = 1;
				} elseif ( 'only_missing' === $data['placeholders'] ) {
					$settings['placeholders']['only_missing'] = 1;
				}

				if ( $settings['placeholders'] !== self::$settings['placeholders'] ) {
					\SIRSC\Placeholder\image_placeholder_for_image_size( 'full', true );
				}
			}

			$post_types = ( ! empty( $data['post_types'] ) ) ? $data['post_types'] : '';

			if ( ! empty( $data['global_ignore'] ) ) {
				$settings['complete_global_ignore'] = array_keys( $data['global_ignore'] );
			}
			if ( ! empty( $data['force_original'] ) ) {
				$settings['force_original_to'] = $data['force_original'];
			}
			if ( ! empty( $data['exclude_size'] ) ) {
				$settings['exclude'] = array_keys( $data['exclude_size'] );
			}
			if ( ! empty( $data['unavailable_size'] ) ) {
				$settings['unavailable'] = array_keys( $data['unavailable_size'] );
			}
			if ( ! empty( $data['default_crop'] ) ) {
				$settings['default_crop'] = $data['default_crop'];
			}
			if ( ! empty( $data['default_quality'] ) ) {
				$settings['default_quality'] = $data['default_quality'];
			}
			if ( ! empty( $data['enable_perfect'] ) ) {
				$settings['enable_perfect'] = true;
			}
			if ( ! empty( $data['enable_upscale'] ) ) {
				$settings['enable_upscale'] = true;
			}
			if ( ! empty( $data['regenerate_missing'] ) ) {
				$settings['regenerate_missing'] = true;
			}
			if ( ! empty( $data['regenerate_only_featured'] ) ) {
				$settings['regenerate_only_featured'] = true;
			}
			if ( ! empty( $data['bulk_actions_descending'] ) ) {
				$settings['bulk_actions_descending'] = true;
			}
			if ( ! empty( $data['disable_woo_thregen'] ) ) {
				$settings['disable_woo_thregen'] = true;
			}
			if ( ! empty( $data['sync_settings_ewww'] ) ) {
				$settings['sync_settings_ewww'] = true;
			}
			if ( ! empty( $data['listing_tiny_buttons'] ) ) {
				$settings['listing_tiny_buttons'] = true;
			}
			if ( ! empty( $data['listing_show_summary'] ) ) {
				$settings['listing_show_summary'] = true;
			}
			if ( ! empty( $data['force_size_choose'] ) ) {
				$settings['force_size_choose'] = true;
			}
			if ( ! empty( $data['leave_settings_behind'] ) ) {
				$settings['leave_settings_behind'] = true;
			}
			if ( ! empty( $data['enable_debug_log'] ) ) {
				$settings['enable_debug_log'] = true;
			}
			if ( ! empty( $data['cron_bulk_execution'] ) ) {
				$settings['cron_bulk_execution']   = true;
				$settings['cron_batch_regenerate'] = ( ! empty( $data['cron_batch_regenerate'] ) )
					? (int) $data['cron_batch_regenerate']
					: 30;
				$settings['cron_batch_cleanup']    = ( ! empty( $data['cron_batch_cleanup'] ) )
					? (int) $data['cron_batch_cleanup']
					: 30;
			} else {
				// Unset the current tasks.
				\SIRSC\Cron\maybe_remove_tasks();
			}

			if ( ! empty( $post_types ) ) { // Specific post type.
				update_option( 'sirsc_settings_' . $post_types, $settings );
			} else { // General settings.
				update_option( 'sirsc_settings', $settings );
			}

			self::$settings = get_option( 'sirsc_settings' );
			update_option( 'sirsc_settings_updated', current_time( 'timestamp' ) ); //phpcs:ignore

			\SIRSC\Placeholder\image_placeholder_for_image_size( 'full', true );
			$is_ajax = ( ! empty( $data['is-ajax'] ) ) ? true : false;
			if ( ! $is_ajax ) {
				wp_safe_redirect( self::$plugin_url );
				exit;
			} else {
				wp_die();
			}
		}
	}

	/**
	 * Maybe execute the update of custom rules.
	 *
	 * @return void
	 */
	public static function maybe_update_user_custom_rules() {
		self::get_default_user_custom_rules();
		$data    = filter_input( INPUT_POST, 'sirsc', FILTER_SANITIZE_STRING, FILTER_REQUIRE_ARRAY );
		$urules  = filter_input( INPUT_POST, '_user_custom_rule', FILTER_SANITIZE_STRING, FILTER_REQUIRE_ARRAY );
		$ucrules = [];
		foreach ( self::$user_custom_rules as $k => $v ) {
			if ( isset( $urules[ $k ] ) ) {
				$ucrules[ $k ] = ( ! empty( $urules[ $k ] ) ) ? $urules[ $k ] : '';
			}
		}

		foreach ( $ucrules as $k => $v ) {
			if ( ! empty( $v['type'] ) && ! empty( $v['original'] ) ) {
				if ( empty( $v['only'] ) || ! is_array( $v['only'] ) ) {
					$v['only'] = [];
				}
				if ( ! empty( $v['only'] ) ) {
					$ucrules[ $k ]['only'] = $v['only'];
				} else {
					if ( '**full**' !== $v['original'] ) {
						$ucrules[ $k ]['only'] = [ $v['original'] ];
					}
				}
				if ( '**full**' !== $v['original'] ) {
					$ucrules[ $k ]['only'] = array_merge( $ucrules[ $k ]['only'], [ $v['original'] ] );
				}
				if ( ! empty( $ucrules[ $k ]['only'] ) ) {
					$ucrules[ $k ]['only'] = array_diff( $ucrules[ $k ]['only'], [ '**full**' ] );
				}
			}
		}

		$ucrules = self::update_user_custom_rules_priority( $ucrules );
		update_option( 'sirsc_user_custom_rules', $ucrules );

		$usable_crules = [];
		foreach ( $ucrules as $key => $val ) {
			if ( ! empty( $val['type'] ) && ! empty( $val['value'] )
				&& ! empty( $val['original'] ) && ! empty( $val['only'] )
				&& empty( $val['suppress'] ) ) {
				$usable_crules[] = $val;
			}
		}
		$usable_crules = self::update_user_custom_rules_priority( $usable_crules );
		update_option( 'sirsc_user_custom_rules_usable', $usable_crules );

		self::$user_custom_rules_usable = $usable_crules;
		update_option( 'sirsc_settings_updated', current_time( 'timestamp' ) ); //phpcs:ignore

		$is_ajax = ( ! empty( $data['is-ajax'] ) ) ? true : false;
		if ( ! $is_ajax ) {
			wp_safe_redirect( admin_url( 'admin.php?page=image-regenerate-select-crop-rules' ) );
			exit;
		} else {
			wp_die();
		}
	}

	/**
	 * Maybe re-order the custom rules options as priorities.
	 *
	 * @access public
	 * @static
	 * @param array $usable_crules The rules to be prioritized.
	 * @return array
	 */
	public static function update_user_custom_rules_priority( $usable_crules = [] ) { //phpcs:ignore
		if ( ! empty( $usable_crules ) ) {
			// Put the rules in the priority order.
			$ucr = [];
			$c   = 0;

			// Collect the ID rules.
			foreach ( $usable_crules as $k => $rule ) {
				if ( 'ID' === $rule['type'] ) {
					$ucr[ ++ $c ] = $rule;
					unset( $usable_crules[ $k ] );
				}
			}
			// Collect the post type rules.
			if ( ! empty( $usable_crules ) ) {
				foreach ( $usable_crules as $k => $rule ) {
					if ( 'post_type' === $rule['type'] ) {
						$ucr[ ++ $c ] = $rule;
						unset( $usable_crules[ $k ] );
					}
				}
			}
			// Collect the post format rules.
			if ( ! empty( $usable_crules ) ) {
				foreach ( $usable_crules as $k => $rule ) {
					if ( 'post_format' === $rule['type'] ) {
						$ucr[ ++ $c ] = $rule;
						unset( $usable_crules[ $k ] );
					}
				}
			}
			// Collect the post parent rules.
			if ( ! empty( $usable_crules ) ) {
				foreach ( $usable_crules as $k => $rule ) {
					if ( 'post_parent' === $rule['type'] ) {
						$ucr[ ++ $c ] = $rule;
						unset( $usable_crules[ $k ] );
					}
				}
			}
			// Collect the tags rules.
			if ( ! empty( $usable_crules ) ) {
				foreach ( $usable_crules as $k => $rule ) {
					if ( 'post_tag' === $rule['type'] ) {
						$ucr[ ++ $c ] = $rule;
						unset( $usable_crules[ $k ] );
					}
				}
			}
			// Collect the categories rules.
			if ( ! empty( $usable_crules ) ) {
				foreach ( $usable_crules as $k => $rule ) {
					if ( 'category' === $rule['type'] ) {
						$ucr[ ++ $c ] = $rule;
						unset( $usable_crules[ $k ] );
					}
				}
			}
			// Collect the test of the taxonomies rules.
			if ( ! empty( $usable_crules ) ) {
				foreach ( $usable_crules as $k => $rule ) {
					$ucr[ ++ $c ] = $rule;
					unset( $usable_crules[ $k ] );
				}
			}

			$usable_crules = $ucr;
		}

		return $usable_crules;
	}

	/**
	 * Custom image size names list in the media screen.
	 *
	 * @param  array $list Initial list of sizes.
	 * @return array
	 */
	public static function custom_image_size_names_choose( $list ) { //phpcs:ignore
		$initial  = $list;
		$all_ims  = array_filter( get_intermediate_image_sizes() );
		$override = false;

		if ( ! empty( self::$settings['complete_global_ignore'] ) ) {
			$override = true;
			foreach ( self::$settings['complete_global_ignore'] as $rem ) {
				// Remove from check the ignored sizes.
				$all_ims = array_diff( $all_ims, [ $rem ] );
			}
		}
		if ( ! empty( self::$settings['unavailable'] ) ) {
			$override = true;
			foreach ( self::$settings['unavailable'] as $rem ) {
				// Remove from check the unavailable sizes.
				$all_ims = array_diff( $all_ims, [ $rem ] );
			}
		}
		if ( true === $override || ! empty( self::$settings['force_size_choose'] ) ) {
			if ( ! empty( $all_ims ) ) {
				$list = [];
				foreach ( $all_ims as $value ) {
					if ( ! empty( $value ) ) {
						if ( ! empty( $initial[ $value ] ) ) {
							// Re-use the title from the initial array.
							$list[ $value ] = $initial[ $value ];
						} else {
							// Add this to the list of available sizes in the media screen.
							$list[ $value ] = ucwords( str_replace( '-', ' ', str_replace( '_', ' ', $value ) ) );
						}
					}
				}
				if ( ! empty( $initial['full'] ) ) {
					$list['full'] = $initial['full'];
				}
			} else {
				// Fall-back to the minimal.
				$list = [ 'thumbnail' => $initial['thumbnail'] ];
				if ( ! empty( $initial['full'] ) ) {
					$list['full'] = $initial['full'];
				}
			}
		}

		return $list;
	}

	/**
	 * Load the settings for a post ID (by parent post type).
	 *
	 * @param integer $post_id The post ID.
	 */
	public static function load_settings_for_post_id( $post_id = 0 ) { //phpcs:ignore
		$post = get_post( $post_id );
		if ( ! empty( $post->post_parent ) ) {
			$pt = get_post_type( $post->post_parent );
			if ( ! empty( $pt ) && ! in_array( $post->post_type, self::$exclude_post_type, true ) ) {
				self::get_post_type_settings( $pt );
			}
			self::hook_upload_extra_rules( $post_id, $post->post_type, $post->post_parent, $pt );
		} elseif ( ! empty( $post->post_type )
			&& ! in_array( $post->post_type, self::$exclude_post_type, true ) ) {
			self::get_post_type_settings( $post->post_type );
			self::hook_upload_extra_rules( $post_id, $post->post_type, 0, '' );
		}

		if ( empty( self::$settings ) ) {
			// Get the general settings.
			self::get_post_type_settings( '' );
		}
	}

	/**
	 * Attempts to override the settings for a single media file.
	 *
	 * @param integer $id          Attachment post ID.
	 * @param string  $type        Attachment post type.
	 * @param integer $parent_id   Attachment post parent ID.
	 * @param string  $parent_type Attachment post parent type.
	 * @return void
	 */
	public static function hook_upload_extra_rules( $id, $type, $parent_id = 0, $parent_type = '' ) { //phpcs:ignore
		if ( ! isset( self::$settings['force_original_to'] ) ) {
			self::$settings['force_original_to'] = '';
		}
		if ( ! isset( self::$settings['complete_global_ignore'] ) ) {
			self::$settings['complete_global_ignore'] = [];
		}
		if ( ! isset( self::$settings['restrict_sizes_to_these_only'] ) ) {
			self::$settings['restrict_sizes_to_these_only'] = [];
		}

		// First, let's apply user custom rules if any are set.
		self::apply_user_custom_rules( $id, $type, $parent_id, $parent_type );

		// Allow to hook from external scripts and create your own upload rules.
		self::$settings = apply_filters( 'sirsc_custom_upload_rule', self::$settings, $id, $type, $parent_id, $parent_type );
	}

	/**
	 * Attempts to override the settings for a single media file.
	 *
	 * @param integer $id          Attachment post ID.
	 * @param string  $type        Attachment post type.
	 * @param integer $parent_id   Attachment post parent ID.
	 * @param string  $parent_type Attachment post parent type.
	 * @return void
	 */
	public static function apply_user_custom_rules( $id, $type, $parent_id = 0, $parent_type = '' ) { //phpcs:ignore
		if ( empty( self::$user_custom_rules_usable ) ) {
			// Fail-fast, no custom rule set.
			return;
		}
		foreach ( self::$user_custom_rules_usable as $key => $val ) {
			$apply        = false;
			$val['value'] = str_replace( ' ', '', $val['value'] );
			switch ( $val['type'] ) {
				case 'ID':
					// This is the attachment parent id.
					if ( in_array( $parent_id, explode( ',', $val['value'] ), true ) ) {
						$apply = true;
					}
					break;
				case 'post_parent':
					// This is the post parent.
					$par = wp_get_post_parent_id( $parent_id );
					if ( in_array( $par, explode( ',', $val['value'] ), true ) ) {
						$apply = true;
					}
					break;
				case 'post_type':
					// This is the attachment parent type.
					if ( in_array( $parent_type, explode( ',', $val['value'] ), true ) ) {
						$apply = true;
					} elseif ( in_array( $type, explode( ',', $val['value'] ), true ) ) {
						$apply = true;
					}
					break;
				case 'post_format':
					// This is the post format.
					$format = get_post_format( $parent_id );
					if ( in_array( $format, explode( ',', $val['value'] ), true ) ) {
						$apply = true;
					}
					break;
				case 'post_tag':
					// This is the post tag.
					if ( has_tag( explode( ',', $val['value'] ), $parent_id ) ) {
						$apply = true;
					}
					break;
				case 'category':
					// This is the post category.
					if ( has_term( explode( ',', $val['value'] ), 'category', $parent_id ) ) {
						$apply = true;
					}
					break;
				default:
					// This is a taxonomy.
					if ( has_term( explode( ',', $val['value'] ), $val['type'], $parent_id ) ) {
						$apply = true;
					}
					break;
			}

			if ( true === $apply ) {
				// The post matched the rule.
				self::$settings = self::custom_rule_to_settings_rules( self::$settings, $val );

				// Fail-fast, no need to iterate more through the rules to speed things up.
				return;
			}
		}

		// The post did not matched any of the cusom rule.
		self::$settings = self::get_post_type_settings( $type );
	}

	/**
	 * Load the post type settings if available.
	 *
	 * @param string $post_type The post type.
	 */
	public static function get_post_type_settings( $post_type ) { //phpcs:ignore
		$pt = '';
		if ( ! empty( $post_type ) && ! in_array( $post_type, self::$exclude_post_type, true ) ) {
			$pt = '_' . $post_type;
		}

		$tmp_set = get_option( 'sirsc_settings' . $pt );
		if ( ! empty( $tmp_set ) ) {
			self::$settings = $tmp_set;
		}
	}

	/**
	 * Override and returns the settings after apllying a rule.
	 *
	 * @param array $settings The settings.
	 * @param array $rule     The rule.
	 * @return array
	 */
	public static function custom_rule_to_settings_rules( $settings = [], $rule = [] ) { //phpcs:ignore
		if ( empty( $rule ) || ! is_array( $rule ) ) {
			// Fail-fast, no need to continue.
			return $settings;
		}

		if ( ! empty( $rule['original'] ) ) {
			if ( '**full**' === $rule['original'] ) {
				$settings['force_original_to'] = '';
			} else {
				// Force original.
				$settings['force_original_to'] = $rule['original'];

				// Let's remove it from the global ignore if it was previously set.
				$settings['complete_global_ignore'] = array_diff(
					$settings['complete_global_ignore'],
					[ $rule['original'] ]
				);
			}
		}
		if ( ! empty( $rule['only'] ) && is_array( $rule['only'] ) ) {
			// Make sure we only generate these image sizes.
			$rule['only'] = array_diff( $rule['only'], [ '**full**' ] );

			$settings['restrict_sizes_to_these_only'] = $rule['only'];
			$settings['restrict_sizes_to_these_only'] = array_unique( $settings['restrict_sizes_to_these_only'] );

			if ( ! empty( $settings['default_quality'] ) ) {
				foreach ( $settings['default_quality'] as $s => $q ) {
					if ( ! in_array( $s, $rule['only'], true ) ) {
						array_push( $settings['complete_global_ignore'], $s );
					}
				}
			}

			$settings['complete_global_ignore'] = array_unique( $settings['complete_global_ignore'] );
		}

		// Fail-fast, no need to continue.
		return $settings;
	}

	/**
	 * Collect regenerate results.
	 *
	 * @param  integer $id        Attachment ID.
	 * @param  string  $message   An intent or error message.
	 * @param  string  $type      The collect type (error|schedule).
	 * @param  string  $initiator The collect initiator.
	 * @return void
	 */
	public static function collect_regenerate_results( $id, $message = '', $type = 'schedule', $initiator = 'regenerate' ) { //phpcs:ignore
		$monitor = get_option( 'sirsc_monitor_errors', [] );
		if ( empty( $monitor['error'] ) ) {
			$monitor['error'] = [];
		}
		if ( empty( $monitor['schedule'] ) ) {
			$monitor['schedule'] = [];
		}

		if ( 'error' === $type ) {
			$monitor['error'][ $id ] = $message;
			\SIRSC\Debug\bulk_log_write( $message );
		} elseif ( 'success' === $type || 'info' === $type ) {
			if ( isset( $monitor['schedule'][ $id ] ) ) {
				unset( $monitor['schedule'][ $id ] );
			}
		} else {
			$monitor['schedule'][ $id ] = $message;
		}
		$monitor['initiator'] = $initiator;
		update_option( 'sirsc_monitor_errors', $monitor );
	}

	/**
	 * Output bulk message regenerate original too small.
	 *
	 * @param  string $name File name.
	 * @param  array  $upls Upload info array.
	 * @return void
	 */
	public static function output_bulk_message_regenerate_success( $name, $upls ) { //phpcs:ignore
		$fname = str_replace( trailingslashit( $upls['basedir'] ), '', $name );
		$fname = str_replace( trailingslashit( $upls['baseurl'] ), '', $fname );
		echo '<b class="dashicons dashicons-yes-alt"></b> ' . $fname; //phpcs:ignore
	}

	/**
	 * Assess original vs target.
	 *
	 * @param  array  $image The attachment meta.
	 * @param  array  $sval  The intended image size details.
	 * @param  string $sname The intended image size name.
	 * @return boolean
	 */
	public static function assess_original_vs_target( $image = [], $sval = [], $sname = '' ) { //phpcs:ignore
		if ( empty( $image ) || empty( $sval ) ) {
			return false;
		}
		if ( ! empty( $image ) && ! empty( $sval ) ) {
			if ( ! empty( $image['sizes'][ $sname ]['file'] ) || empty( self::$settings['enable_perfect'] ) ) {
				// For the images already created, bypasss the check.
				return true;
			}

			if ( ! empty( $sval['crop'] ) ) {
				// This should be a crop.
				if ( $image['width'] < $sval['width'] || $image['height'] < $sval['height'] ) {
					// The image is too small, return.
					return false;
				}
			} else {
				// This should be a resize.
				if ( ! empty( $sval['width'] ) && $image['width'] < $sval['width'] ) {
					// The image is too small, return.
					return false;
				}
				if ( empty( $sval['width'] ) && ! empty( $sval['height'] ) && $image['height'] < $sval['height'] ) {
					// The image is too small, return.
					return false;
				}
			}

			return true;
		}
	}

	/**
	 * Check if an image size should be generated or not for image meta.
	 *
	 * @param array   $image    The image metadata.
	 * @param string  $sname    Image size slug.
	 * @param array   $sval     The image size detail.
	 * @param string  $filename Image filename.
	 * @param boolean $force    True to force re-crop.
	 * @return boolean
	 */
	public static function check_if_execute_size( $image = [], $sname = '', $sval = [], $filename = '', $force = false ) { //phpcs:ignore
		$execute = false;
		if ( ! self::assess_original_vs_target( $image, $sval, $sname ) ) {
			// Fail-fast.
			return false;
		}

		if ( empty( $image['sizes'][ $sname ] ) ) {
			$execute = true;
		} else {
			// Check if the file does exist, else generate it.
			if ( empty( $image['sizes'][ $sname ]['file'] ) ) {
				$execute = true;
			} else {
				$file = str_replace( basename( $filename ), $image['sizes'][ $sname ]['file'], $filename );

				if ( ! file_exists( $file ) ) {
					$execute = true;
				} else {
					// Check if the file does exist and has the required width and height.
					$w = ( ! empty( $sval['width'] ) ) ? (int) $sval['width'] : 0;
					$h = ( ! empty( $sval['height'] ) ) ? (int) $sval['height'] : 0;
					$c = ( ! empty( $sval['crop'] ) ) ? $sval['crop'] : false;

					$c_image_size = getimagesize( $file );
					$ciw          = (int) $c_image_size[0];
					$cih          = (int) $c_image_size[1];
					if ( ! empty( $c ) ) {
						if ( $w !== $ciw || $h !== $cih ) {
							$execute = true;
						} elseif ( true === $force ) {
							$execute = true;
						}
					} else {
						if ( ( 0 === $w && $cih <= $h )
							|| ( 0 === $h && $ciw <= $w )
							|| ( 0 !== $w && 0 !== $h && $ciw <= $w && $cih <= $h ) ) {
							$execute = true;
						}
					}
				}
			}
		}
		return $execute;
	}

	/**
	 * Process a single image size for an attachment.
	 *
	 * @param  integer $id                 The Attachment ID.
	 * @param  string  $size_name          The image size name.
	 * @param  array   $size_info          Maybe a previously computed image size info.
	 * @param  string  $small_crop         Maybe a position for the content crop.
	 * @param  integer $force_quality      Maybe a specified quality loss.
	 * @param  boolean $first_time_replace Maybe it is the first time when the image is processed after upload.
	 * @return mixed
	 */
	public static function process_single_size_from_file( $id, $size_name = '', $size_info = [], $small_crop = '', $force_quality = 0, $first_time_replace = false ) { //phpcs:ignore
		if ( empty( $size_name ) ) {
			return;
		}

		if ( empty( $small_crop ) && ! empty( self::$settings['default_crop'][ $size_name ] ) ) {
			$small_crop = self::$settings['default_crop'][ $size_name ];
		}

		$from_file = '';
		$metadata  = wp_get_attachment_metadata( $id );
		if ( is_wp_error( $metadata ) ) {
			return;
		}

		$initial_m = $metadata;
		$filename  = get_attached_file( $id );
		$uploads   = wp_get_upload_dir();
		if ( ! empty( $filename ) ) {
			$file_full = $filename;
			$from_file = $file_full;
		}
		if ( self::$wp_ver >= 5.3 ) {
			if ( ! empty( $metadata['original_image'] ) && ! empty( $metadata['file'] ) ) {
				$file_orig = path_join( trailingslashit( $uploads['basedir'] ) . dirname( $metadata['file'] ), $metadata['original_image'] );
				$from_file = $file_orig;
			}
		}

		if ( true === $first_time_replace ) {
			// Do the switch.
			\SIRSC\Helper\debug( 'REPLACE ORIGINAL', true, true );
			if ( ! empty( self::$settings['force_original_to'] ) && $size_name === self::$settings['force_original_to'] ) {
				$maybe_new_meta = self::swap_full_with_another_size( $id, $from_file, $size_name, $small_crop, $force_quality );
				if ( ! empty( $maybe_new_meta ) ) {
					return $maybe_new_meta;
				}
			}
		}

		\SIRSC\Helper\debug( 'PROCESSING SINGLE ' . $id . '|WP' . self::$wp_ver . '|' . $size_name . '|' . $from_file, true, true );
		if ( ! empty( $from_file ) ) {
			if ( empty( $size_info ) ) {
				self::load_settings_for_post_id( $id );
				$size_info = self::get_all_image_sizes_plugin( $size_name );
			}
			if ( ! empty( $size_info ) ) {
				$assess = self::assess_original_vs_target( $metadata, $size_info, $size_name );
				if ( ! $assess ) {
					if ( empty( self::$settings['enable_perfect'] ) ) {
						// Fail-fast, the original is too small.
						\SIRSC\Helper\debug( 'ERROR TOO SMALL', true, true );
						return 'error-too-small';
					}
				}

				$allow_upscale = ( ! empty( self::$settings['enable_perfect'] ) && ! empty( self::$settings['enable_upscale'] ) ) ? true : false;

				$execute = self::check_if_execute_size( $metadata, $size_name, $size_info, $from_file, true );
				if ( ! empty( $execute ) || $allow_upscale ) {
					$saved = self::image_editor( $id, $from_file, $size_name, $size_info, $small_crop, $force_quality );

					if ( ! empty( $saved ) ) {
						if ( is_wp_error( $metadata ) ) {
							\SIRSC\Helper\debug( 'DO NOT UPDATE METADATA', true, true );
							return;
						}
						$is_reused = ( ! empty( $saved['reused'] ) ) ? true : false;
						\SIRSC\Helper\debug( 'EDITOR PROCESSED IMAGE', true, true );

						if ( empty( $metadata ) ) {
							$metadata = self::attempt_to_create_metadata( $id, $filename );
						}
						if ( empty( $metadata['sizes'] ) ) {
							$metadata['sizes'] = [];
						}
						if ( isset( $saved['path'] ) ) {
							unset( $saved['path'] );
						}
						if ( isset( $saved['reused'] ) ) {
							unset( $saved['reused'] );
						}
						$metadata['sizes'][ $size_name ] = $saved;
						wp_update_attachment_metadata( $id, $metadata );
						$initial_m = $metadata;

						if ( ! $is_reused ) {
							do_action( 'sirsc_image_processed', $id, $size_name );
						}
					}
				}
			}
		}

		if ( $initial_m !== $metadata ) {
			// If something changed, then save the metadata.
			wp_update_attachment_metadata( $id, $metadata );
		}
	}

	/**
	 * Swap full image with another image size.
	 *
	 * @param  integer $id            The attachment ID.
	 * @param  string  $file          The original file.
	 * @param  string  $size_name     The image size name.
	 * @param  string  $small_crop    Maybe some crop position.
	 * @param  integer $force_quality Maybe some forced quality.
	 * @return array|boolean
	 */
	public static function swap_full_with_another_size( $id, $file, $size_name, $small_crop, $force_quality ) { //phpcs:ignore
		$metadata  = wp_get_attachment_metadata( $id );
		$initial_m = $metadata;
		if ( empty( $metadata ) ) {
			// Fail-fast.
			return false;
		}

		// Make the image.
		self::load_settings_for_post_id( $id );
		$size_info = self::get_all_image_sizes_plugin( $size_name );
		$saved     = self::image_editor( $id, $file, $size_name, $size_info, $small_crop, 0 );

		// Maybe rename the full size with the original.
		$info     = self::assess_rename_original( $id );
		$metadata = wp_get_attachment_metadata( $id );

		if ( ! empty( $saved ) && ! empty( $info ) ) {
			if ( ! empty( $saved['path'] ) ) {
				unset( $saved['path'] );
			}

			\SIRSC\Helper\debug( 'FORCED SIZE EDITOR PROCESSED IMAGE', true, true );
			$saved_filename = $info['path'] . $saved['file'];
			if ( wp_basename( $saved_filename ) !== $info['name'] ) {
				// Remove the initial full.
				if ( file_exists( $info['filename'] ) ) {
					@unlink( $info['filename'] ); //phpcs:ignore
				}

				// Rename the new size as the full image.
				@copy( $saved_filename, $info['filename'] ); //phpcs:ignore

				// Remove the image size.
				@unlink( $saved_filename ); //phpcs:ignore

				// Adjust the metadata to match the new set.
				$metadata['width']  = $saved['width'];
				$metadata['height'] = $saved['height'];
				$saved['file']      = $info['name'];

				$metadata['sizes'][ $size_name ] = $saved;
			}

			if ( $initial_m !== $metadata ) {
				// If something changed, then save the metadata.
				update_post_meta( $id, '_wp_attachment_metadata', $metadata );
				update_post_meta( $id, '_wp_attached_file', $info['dir'] . $info['name'] );
				clean_attachment_cache( $id );

				if ( ! defined( 'SIRSC_REPLACED_ORIGINAL' ) ) {
					// Notify other scripts that the original file is now this one.
					define( 'SIRSC_REPLACED_ORIGINAL', $info['dir'] . $info['name'] );
				}
			}

			\SIRSC\Helper\debug( 'AFTER EDITOR PROCESSED IMAGE ' . print_r( $metadata, 1 ), true, true ); //phpcs:ignore
			return $metadata;
		}

		return $metadata;
	}

	/**
	 * Access directly the image editor to generate a specific image.
	 *
	 * @param  string  $id            The attachment ID.
	 * @param  string  $file          The original file.
	 * @param  string  $name          The image size name.
	 * @param  array   $info          The image size info.
	 * @param  string  $small_crop    Maybe some crop position.
	 * @param  integer $force_quality Maybe some forced quality.
	 * @return array|boolean
	 */
	public static function image_editor( $id, $file, $name = '', $info = [], $small_crop = '', $force_quality = 0 ) { //phpcs:ignore
		if ( empty( $file ) || ( ! empty( $file ) && ! file_exists( $file ) ) ) {
			// Fail-fast, the original is not found.
			return false;
		}

		$filetype   = wp_check_filetype( $file );
		$mime_type  = $filetype['type'];
		$image_size = getimagesize( $file );
		$estimated  = wp_constrain_dimensions( $image_size[0], $image_size[1], $info['width'], $info['height'] );
		if ( ! empty( $estimated ) && $estimated[0] === $image_size[0] && $estimated[1] === $image_size[1] ) {
			$meta = wp_get_attachment_metadata( $id );

			// Skip the editor, this is the same as the current file.
			if ( self::$wp_ver < 5.3 ) {
				// For older version, let's check the size in DB.
				if ( ! empty( $meta['sizes'][ $name ]['file'] ) ) {
					$maybe_size = trailingslashit( dirname( $file ) ) . $meta['sizes'][ $name ]['file'];
					if ( file_exists( $maybe_size ) ) {
						$image_size = getimagesize( $maybe_size );
						rename( $maybe_size, $file );
						$saved = [
							'file'   => wp_basename( $file ),
							'width'  => $image_size[0],
							'height' => $image_size[1],
							'mime'   => $mime_type,
							'reused' => true,
						];
						return $saved;
					}
				}
			} else {
				if ( ! empty( $meta['width'] ) && $estimated[0] === $meta['width']
					&& ! empty( $meta['height'] ) && $estimated[1] === $meta['height'] ) {
					// This matches the orginal.
					$saved = [
						'file'   => wp_basename( $file ),
						'width'  => $estimated[0],
						'height' => $estimated[1],
						'mime'   => $mime_type,
						'reused' => true,
					];
					return $saved;
				} elseif ( ! empty( $meta['sizes'][ $name ]['file'] ) ) {
					$maybe_size = trailingslashit( dirname( $file ) ) . $meta['sizes'][ $name ]['file'];
					if ( file_exists( $maybe_size ) ) {
						$image_size = getimagesize( $maybe_size );
						$saved      = [
							'file'   => wp_basename( $file ),
							'width'  => $image_size[0],
							'height' => $image_size[1],
							'mime'   => $mime_type,
							'reused' => true,
						];
						return $saved;
					}
				}
			}

			//phpcs:disable
			/*
			// Fall-back for newer version.
			$saved = array(
				'file'   => wp_basename( $file ),
				'width'  => $image_size[0],
				'height' => $image_size[1],
				'mime'   => $mime_type,
			);
			return $saved;
			*/
			//phpcs:enable
			return false;
		}

		$editor = @wp_get_image_editor( $file ); //phpcs:ignore
		if ( ! is_wp_error( $editor ) ) {
			$quality = self::editor_set_custom_quality( $name, $mime_type, $force_quality );
			add_filter( 'wp_editor_set_quality', function () use ( $quality, $mime_type ) { //phpcs:ignore
				return $quality;
			}, 99, 2 );
			$editor->set_quality( $quality );

			if ( ! empty( $info['crop'] ) ) {
				$crop = self::identify_crop_pos( $name, $small_crop );
				\SIRSC\Helper\debug( 'CROP ' . $info['width'] . 'x' . $info['height'] . '|' . print_r( $crop, 1 ), true, true ); //phpcs:ignore
				$editor->resize( $info['width'], $info['height'], $crop );

				if ( ! empty( self::$settings['enable_perfect'] ) && ! empty( self::$settings['enable_upscale'] ) ) {
					$result = $editor->get_size();
					if ( $result['width'] !== $info['width'] || $result['height'] !== $info['height'] ) {
						\SIRSC\Helper\debug( 'CROP failed, attempt to UPSCALE', true, true );
						if ( ! empty( self::$settings['enable_perfect'] ) ) {
							self::force_upscale_before_crop( $id, $file, $name, $image_size[0], $image_size[1], $editor );
						}
					}
				}
			} else {
				\SIRSC\Helper\debug( 'SCALE ' . $info['width'] . 'x' . $info['height'], true, true );
				$editor->resize( $info['width'], $info['height'] );
			}

			// Finally, let's store the image.
			$saved = $editor->save();
			return $saved;
		}
		return false;
	}

	/**
	 * Force native editor to upscale the image before applying the expected crop.
	 *
	 * @param  string  $id            The attachment ID.
	 * @param  string  $file          The original file.
	 * @param  string  $size_name     The image size name.
	 * @param  integer $original_w    The original image width.
	 * @param  integer $original_h    The original image height.
	 * @param  object  $editor        Editor instance.
	 */
	public static function force_upscale_before_crop( $id, $file, $size_name, $original_w, $original_h, $editor ) { //phpcs:ignore
		if ( ! empty( self::$settings['enable_perfect'] ) ) {
			$all_sizes = self::get_all_image_sizes();
			$meta      = wp_get_attachment_metadata( $id );
			$rez_img   = \SIRSC\Helper\allow_resize_from_original( $file, $meta, $all_sizes, $size_name );
			if ( ! empty( $rez_img['must_scale_up'] ) ) {
				$sw = $all_sizes[ $size_name ]['width'];
				$sh = $all_sizes[ $size_name ]['height'];

				$assess = self::upscale_match_sizes( $original_w, $original_h, $sw, $sh );
				if ( ! empty( $assess['scale'] ) ) {
					self::$upscale_new_w = $assess['width'];
					self::$upscale_new_h = $assess['height'];

					// Apply the filter here to override the private properties.
					add_filter( 'image_resize_dimensions', [ get_called_class(), 'sirsc_image_crop_dimensions_up' ], 10, 6 );

					// Make the editor resize the loaded resource.
					$editor->resize( self::$upscale_new_w, self::$upscale_new_h );

					// Remove the custom override, so that the editor to fallback to it's defaults.
					remove_filter( 'image_resize_dimensions', [ get_called_class(), 'sirsc_image_crop_dimensions_up' ], 10 );
				}

				// Make the editor crop the upscaled resource.
				$editor->resize( $sw, $sh, true );
			}
		}
	}

	/**
	 * Recompute the image size components that are used to override the private editor properties.
	 *
	 * @param  null|mixed $default Whether to preempt output of the resize dimensions.
	 * @param  integer    $orig_w  Original width in pixels.
	 * @param  integer    $orig_h  Original height in pixels.
	 * @param  integer    $new_w   New width in pixels.
	 * @param  integer    $new_h   New height in pixels.
	 * @param  bool|array $crop    Whether to crop image to specified width and height or resize.
	 * @return array               An array can specify positioning of the crop area. Default false.
	 */
	public static function sirsc_image_crop_dimensions_up( $default, $orig_w, $orig_h, $new_w, $new_h, $crop ) { //phpcs:ignore
		$new_w        = self::$upscale_new_w;
		$new_h        = self::$upscale_new_h;
		$aspect_ratio = $orig_w / $orig_h;
		$size_ratio   = max( $new_w / $orig_w, $new_h / $orig_h );
		$crop_w       = round( $new_w / $size_ratio );
		$crop_h       = round( $new_h / $size_ratio );
		$s_x          = floor( ( $orig_w - $crop_w ) / 2 );
		$s_y          = floor( ( $orig_h - $crop_h ) / 2 );
		return [ 0, 0, (int) $s_x, (int) $s_y, (int) $new_w, (int) $new_h, (int) $crop_w, (int) $crop_h ];
	}

	/**
	 * Attempt to scale the width and height to cover the expected size.
	 *
	 * @param  integer $initial_w  Initial image width.
	 * @param  integer $initial_h  Initial image height.
	 * @param  integer $expected_w Expected image width.
	 * @param  integer $expected_h Expected image height.
	 * @return array
	 */
	public static function upscale_match_sizes( $initial_w, $initial_h, $expected_w, $expected_h ) { //phpcs:ignore
		$new_w  = $initial_w;
		$new_h  = $initial_h;
		$result = [
			'width'  => $new_w,
			'height' => $new_h,
			'scale'  => false,
		];
		if ( $initial_w >= $expected_w && $initial_h >= $expected_h ) {
			// The original is bigger than the expected, no need to scale, no need to continue either.
			return [
				'width'  => $initial_w,
				'height' => $initial_h,
				'scale'  => false,
			];
		}

		if ( $initial_w >= $expected_w ) {
			// This means that the initial width is good, but the initial height is smaller than the expected height.
			$new_h = $expected_h;
			$new_w = ceil( $initial_w * $expected_h / $initial_h );
			return [
				'width'  => $new_w,
				'height' => $new_h,
				'scale'  => true,
			];
		}

		if ( $initial_w < $expected_w ) {
			// This means that the initial width is smaller than the expected width.
			$new_w = $expected_w;
			$new_h = ceil( $expected_w * $initial_h / $initial_w );
			if ( ! ( $new_h >= $expected_h ) ) {
				$new_h = $expected_h;
				$new_w = ceil( $initial_w * $expected_h / $initial_h );
			}

			return [
				'width'  => $new_w,
				'height' => $new_h,
				'scale'  => true,
			];
		}
	}

	/**
	 * Assess unique original.
	 *
	 * @param  integer $id     Attachment ID.
	 * @param  string  $folder The path.
	 * @param  string  $dir    File relative directory.
	 * @param  string  $name   File name.
	 * @return string
	 */
	public static function assess_unique_original( $id, $folder, $dir = '', $name = '' ) { //phpcs:ignore
		if ( ! file_exists( $folder . $name ) ) {
			return $name;
		}

		return $name;
	}

	/**
	 * Assess rename original.
	 *
	 * @param  integer $id Attachment ID.
	 * @return array
	 */
	public static function assess_rename_original( $id ) { //phpcs:ignore
		$metadata = wp_get_attachment_metadata( $id );
		if ( ! empty( $metadata ) ) {
			$orig_me = $metadata;
			$uploads = wp_get_upload_dir();
			if ( empty( $metadata['file'] ) ) {
				// Read the filename from the attachmed file, as this was not set in the metadata.
				$filename = get_attached_file( $id );
			} else {
				// Read the filename from the metadata.
				$filename = trailingslashit( $uploads['basedir'] ) . $metadata['file'];
			}

			$ext  = pathinfo( $filename, PATHINFO_EXTENSION );
			$name = pathinfo( $filename, PATHINFO_FILENAME );
			$path = pathinfo( $filename, PATHINFO_DIRNAME );
			if ( file_exists( $filename ) ) {
				$size = getimagesize( $filename );
			} else {
				// This means that the image was probably moved in the previous iteration.
				$size = [ $metadata['width'], $metadata['height'] ];
			}

			$filetype = wp_check_filetype( $filename );
			$info     = [
				'path'   => trailingslashit( $path ),
				'dir'    => trailingslashit( dirname( str_replace( trailingslashit( $uploads['basedir'] ), '', $filename ) ) ),
				'name'   => $name . '.' . $ext,
				'width'  => ( ! empty( $size[0] ) ) ? (int) $size[0] : 0,
				'height' => ( ! empty( $size[1] ) ) ? (int) $size[1] : 0,
				'mime'   => $filetype['type'],
			];

			$initial_unique = '';
			if ( ! empty( $metadata['original_image'] ) && $metadata['original_image'] !== $info['name'] ) {
				$initial_unique = $metadata['original_image'];
				$unique         = wp_unique_filename( $info['path'], $metadata['original_image'] );

				// Remove the initial original file id that is not used by another attachment.
				if ( file_exists( $info['path'] . $metadata['original_image'] )
					&& $metadata['original_image'] !== $unique ) {
					@unlink( $info['path'] . $metadata['original_image'] ); //phpcs:ignore
				}

				// Rename the full size as  the initial original file.
				if ( file_exists( $info['path'] . $info['name'] ) ) {
					@rename( $info['path'] . $info['name'], $info['path'] . $unique ); //phpcs:ignore
				}

				// Pass the new name.
				$info['name'] = $unique;

				$metadata['original_image'] = $unique;
			}

			$info['filename']   = $info['path'] . $info['name'];
			$metadata['file']   = $info['dir'] . $info['name'];
			$metadata['width']  = $info['width'];
			$metadata['height'] = $info['height'];

			if ( ! empty( self::$settings['force_original_to'] ) ) {
				$fo_orig = self::$settings['force_original_to'];
				if ( empty( $metadata['sizes'][ $fo_orig ] ) ) {
					$metadata['sizes'][ $fo_orig ] = [
						'file'      => $info['name'],
						'width'     => $info['width'],
						'height'    => $info['height'],
						'mime-type' => $info['mime'],
					];
				}
			}

			// Save this.
			update_post_meta( $id, '_wp_attachment_metadata', $metadata );
			update_post_meta( $id, '_wp_attached_file', $info['dir'] . $info['name'] );

			if ( ! empty( $initial_unique ) && $initial_unique !== $unique ) {
				$new = self::assess_unique_original( $id, $info['path'], $info['dir'], $initial_unique );
				if ( $new === $initial_unique ) {
					\SIRSC\Helper\debug( 'FOUND A POTENTIAL REVERT ' . $new, true, true );
					@rename( $info['path'] . wp_basename( $metadata['file'] ), $info['path'] . $new ); //phpcs:ignore
					$metadata['file']           = $info['dir'] . $new;
					$metadata['original_image'] = $new;

					if ( ! empty( $fo_orig ) && ! empty( $metadata['sizes'][ $fo_orig ] ) ) {
						$metadata['sizes'][ $fo_orig ]['file'] = $new;
					}
					update_post_meta( $id, '_wp_attachment_metadata', $metadata );
					update_post_meta( $id, '_wp_attached_file', $info['dir'] . $new );
					clean_attachment_cache( $id );

					$info['name']     = $new;
					$info['filename'] = $info['path'] . $info['name'];
				}
			}
			clean_attachment_cache( $id );
			return $info;
		}
		return [];
	}

	/**
	 * Assess the quality by mime-type.
	 *
	 * @param string  $sname         Size name.
	 * @param string  $mime          Mime-type.
	 * @param integer $force_quality Custom quality.
	 * @return integer
	 */
	public static function editor_set_custom_quality( $sname, $mime, $force_quality ) { //phpcs:ignore
		if ( ! empty( $force_quality ) ) {
			$quality = (int) $force_quality;
		} else {
			$quality = ( ! empty( self::$settings['default_quality'][ $sname ] ) ) ? (int) self::$settings['default_quality'][ $sname ] : self::DEFAULT_QUALITY;
		}
		$quality = ( $quality < 0 ) ? 0 : $quality;
		$quality = ( $quality > 100 ) ? self::DEFAULT_QUALITY : $quality;

		if ( ! empty( $quality ) && 'image/png' === $mime ) {
			$quality = abs( 10 - ceil( $quality / 10 ) );
			if ( $quality > 9 ) {
				$quality = 9;
			}
			if ( $quality < 0 ) {
				$quality = 0;
			}
		}

		if ( ! empty( $quality ) ) {
			add_filter(
				'wp_editor_set_quality',
				function( $m ) use ( $mime, $quality ) { //phpcs:ignore
					return $quality;
				},
				90
			);
		}

		return $quality;
	}

	/**
	 * Identify a crop position by the image size and return the crop array.
	 *
	 * @param string $size_name Image size slug.
	 * @param string $selcrop   Perhaps a selected crop string.
	 * @return array|boolean
	 */
	public static function identify_crop_pos( $size_name = '', $selcrop = '' ) { //phpcs:ignore
		if ( empty( $size_name ) ) {
			// Fail-fast.
			return false;
		}
		if ( ! empty( $selcrop ) ) {
			$sc = $selcrop;
		} else {
			$sc = ( ! empty( self::$settings['default_crop'][ $size_name ] ) )
				? self::$settings['default_crop'][ $size_name ] : 'cc';
		}

		$c_v = $sc[0];
		$c_h = $sc[1];

		$c_v = ( 'l' === $c_v ) ? 'left' : $c_v;
		$c_v = ( 'c' === $c_v ) ? 'center' : $c_v;
		$c_v = ( 'r' === $c_v ) ? 'right' : $c_v;
		$c_h = ( 't' === $c_h ) ? 'top' : $c_h;
		$c_h = ( 'c' === $c_h ) ? 'center' : $c_h;
		$c_h = ( 'b' === $c_h ) ? 'bottom' : $c_h;

		return [ $c_v, $c_h ];
	}

	/**
	 * Match all the files and the images sizes registered.
	 *
	 * @param  integer $id      Attachment ID.
	 * @param  array   $image   Maybe metadata.
	 * @param  object  $compute Maybe extra computed info.
	 * @return array|void
	 */
	public static function general_sizes_and_files_match( $id, $image = [], $compute = null ) { //phpcs:ignore
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

		$upload_dir = wp_upload_dir();
		if ( empty( $compute ) ) {
			$compute = self::compute_image_paths( $id, '', $upload_dir );
		} else {
			$compute = (array) $compute;
		}
		if ( empty( $image ) && ! empty( $compute['metadata'] ) ) {
			$image = $compute['metadata'];
		}
		if ( empty( $image ) ) {
			$image = wp_get_attachment_metadata( $id );
			if ( empty( $image ) ) {
				$filename = get_attached_file( $id );
				$image    = self::attempt_to_create_metadata( $id, $filename );
			}
		}

		$list       = [];
		$registered = get_intermediate_image_sizes();
		$basedir    = trailingslashit( $upload_dir['basedir'] );
		$baseurl    = trailingslashit( $upload_dir['baseurl'] );
		if ( ! empty( $image['file'] ) ) {
			$dir = trailingslashit( dirname( $image['file'] ) );
		} elseif ( ! empty( $compute['source'] ) ) {
			$dir = trailingslashit( dirname( $compute['source'] ) );
		}

		$gene_all = self::assess_files_for_attachment_original( $id, $image );
		if ( ! empty( $gene_all['names'] ) ) {
			$list = array_merge(
				$gene_all['names']['original'],
				$gene_all['names']['full'],
				$gene_all['names']['generated']
			);
		}

		// Start to gather data.
		$summary = [];
		if ( ! empty( $gene_all['names']['full'][0] ) ) {
			$file  = $gene_all['names']['full'][0];
			$fsize = ( file_exists( $basedir . $file ) ) ? filesize( $basedir . $file ) : 0;
			$info  = [
				'width'      => $compute['metadata']['width'],
				'height'     => $compute['metadata']['height'],
				'size'       => 'full',
				'registered' => true,
				'fsize'      => $fsize,
				'filesize'   => \SIRSC\Helper\human_filesize( $fsize ),
				'icon'       => 'dashicons-yes-alt is-full',
				'hint'       => __( 'currently registered', 'sirsc' ),
				'is_main'    => 1,
			];

			$summary[ $file ] = $info;
			$list             = array_diff( $list, [ $file ] );
		}

		if ( ! empty( $gene_all['names']['original'][0] ) ) {
			$file  = $gene_all['names']['original'][0];
			$s     = ( file_exists( $basedir . $file ) ) ? getimagesize( $basedir . $file ) : 0;
			$fsize = ( file_exists( $basedir . $file ) ) ? filesize( $basedir . $file ) : 0;
			$info  = [
				'width'      => ( ! empty( $s[0] ) ) ? $s[0] : 0,
				'height'     => ( ! empty( $s[1] ) ) ? $s[1] : 0,
				'size'       => ( $file === $gene_all['names']['full'][0] ) ? 'full,original' : 'original',
				'registered' => true,
				'fsize'      => $fsize,
				'filesize'   => \SIRSC\Helper\human_filesize( $fsize ),
				'icon'       => ( $file === $gene_all['names']['full'][0] ) ? 'dashicons-yes-alt is-full is-original' : 'dashicons-yes-alt is-original',
				'',
				'hint'       => __( 'currently registered', 'sirsc' ),
				'is_main'    => 2,
			];

			$summary[ $file ] = $info;
			$list             = array_diff( $list, [ $file ] );
		}

		if ( ! empty( $compute['metadata']['sizes'] ) ) {
			foreach ( $compute['metadata']['sizes'] as $k => $v ) {
				$file  = $dir . $v['file'];
				$fsize = ( file_exists( $basedir . $file ) ) ? filesize( $basedir . $file ) : 0;
				$info  = [
					'width'      => ( ! empty( $v['width'] ) ) ? $v['width'] : 0,
					'height'     => ( ! empty( $v['height'] ) ) ? $v['height'] : 0,
					'size'       => $k,
					'registered' => ( in_array( $k, $registered, true ) ),
					'fsize'      => $fsize,
					'filesize'   => \SIRSC\Helper\human_filesize( $fsize ),
					'icon'       => ( in_array( $k, $registered, true ) ) ? 'dashicons-yes-alt' : 'dashicons-marker',
					'hint'       => ( in_array( $k, $registered, true ) ) ? __( 'currently registered', 'sirsc' ) : __( 'not registered anymore', 'sirsc' ),
					'is_main'    => 0,
				];
				if ( ! isset( $summary[ $file ] ) ) {
					$summary[ $file ] = $info;
				} else {
					$summary[ $file ]['size'] .= ',' . $k;
				}
				$list = array_diff( $list, [ $file ] );
			}
		}

		if ( ! empty( $list ) ) {
			foreach ( $list as $k ) {
				$fsize = ( file_exists( $basedir . $k ) ) ? filesize( $basedir . $k ) : 0;
				$s     = ( file_exists( $basedir . $k ) ) ? getimagesize( $basedir . $k ) : [];

				$summary[ $k ] = [
					'width'      => ( ! empty( $s[0] ) ) ? $s[0] : 0,
					'height'     => ( ! empty( $s[1] ) ) ? $s[1] : 0,
					'size'       => __( 'unknown', 'sirsc' ),
					'registered' => false,
					'fsize'      => $fsize,
					'filesize'   => \SIRSC\Helper\human_filesize( $fsize ),
					'icon'       => 'dashicons-marker',
					'hint'       => __( 'never registered', 'sirsc' ),
					'is_main'    => 0,
				];
			}
		}
		if ( empty( $summary ) ) {
			return;
		}

		$sortable = wp_list_pluck( $summary, 'fsize' );
		arsort( $sortable );

		$sorted = [];
		foreach ( $sortable as $k => $v ) {
			$sorted[ $k ] = $summary[ $k ];
		}
		$summary = $sorted;

		// This attempts to matche the sizes and updates the summary.
		$summary = self::maybe_match_unknown_files_to_meta( $id, $summary );
		return $summary;
	}

	/**
	 * Size is registered.
	 *
	 * @param  string $size Size name.
	 * @return bool
	 */
	public static function size_is_registered( $size ) { //phpcs:ignore
		$registered = get_intermediate_image_sizes();

		if ( 'full' === $size || 'original' === $size ) {
			return true;
		}

		return ( in_array( $size, $registered, true ) ) ? true : false;
	}

	/**
	 * Attempt to match the unknown files and update the attachment metadata.
	 *
	 * @param  integer $id      Attachment ID.
	 * @param  array   $summary Identified generated files.
	 * @return array
	 */
	public static function maybe_match_unknown_files_to_meta( $id, $summary ) { //phpcs:ignore
		$assess = [];
		if ( ! empty( $summary ) ) {
			$image_meta   = wp_get_attachment_metadata( $id );
			$initial_meta = $image_meta;
			$sizes_info   = self::get_all_image_sizes_plugin();
			if ( ! empty( $image_meta['sizes'] ) ) {
				$direct = wp_list_pluck( $image_meta['sizes'], 'file' );
				if ( ! empty( $direct ) ) {
					$dir = trailingslashit( dirname( $image_meta['file'] ) );
					foreach ( $direct as $key => $value ) {
						$file = $dir . $value;
						if ( ! empty( $summary[ $file ] ) ) {
							if ( substr_count( $summary[ $file ]['size'], 'unknown' ) ) {
								$summary[ $file ]['size'] = $key;
							} else {
								if ( ! substr_count( $summary[ $file ]['size'], $key ) ) {
									$summary[ $file ]['size'] .= ',' . $key;
								}
							}
						}
					}
				}
			}

			foreach ( $summary as $file => $info ) {
				if ( 'unknown' === $info['size']
					&& ! empty( $info['width'] ) && ! empty( $info['height'] ) ) {
					$filetype = wp_check_filetype( $file );
					foreach ( $sizes_info as $name => $details ) {
						if ( (int) $details['width'] === (int) $info['width']
							&& (int) $details['height'] === (int) $info['height'] ) {
							if ( substr_count( $file, '-' . $info['width'] . 'x' . $info['height'] . '.' ) ) {
								// This is a perfect match.
								$image_meta['sizes'][ $name ] = [
									'file'      => wp_basename( $file ),
									'width'     => (int) $info['width'],
									'height'    => (int) $info['height'],
									'mime-type' => $filetype['type'],
								];

								$summary[ $file ]['size'] .= ',' . $name;
							}
						} else {
							if ( empty( $details['crop'] ) ) {
								// This can be a scale type.
								if ( (int) $details['width'] === $info['width']
									&& empty( $details['height'] ) ) {
									$image_meta['sizes'][ $name ] = [
										'file'      => wp_basename( $file ),
										'width'     => (int) $details['width'],
										'height'    => (int) $info['height'],
										'mime-type' => $filetype['type'],
									];

									$summary[ $file ]['size'] .= ',' . $name;
								} elseif ( (int) $details['height'] === (int) $info['height']
									&& empty( $details['width'] ) ) {
									$image_meta['sizes'][ $name ] = [
										'file'      => wp_basename( $file ),
										'width'     => (int) $info['width'],
										'height'    => (int) $height['height'],
										'mime-type' => $filetype['type'],
									];

									$summary[ $file ]['size'] .= ',' . $name;
								}
							}
						}
					}

					if ( substr_count( $summary[ $file ]['size'], ',' ) ) {
						$summary[ $file ]['size'] = str_replace( 'unknown,', '', $summary[ $file ]['size'] );
						$summary[ $file ]['icon'] = 'dashicons-yes-alt';
						$summary[ $file ]['hint'] = __( 'currently registered', 'sirsc' );
					}
				}
			}

			if ( ! empty( $summary ) ) {
				foreach ( $summary as $key => $value ) {
					$summary[ $key ]['match'] = explode( ',', $summary[ $key ]['size'] );
				}
			}

			if ( $image_meta !== $initial_meta ) {
				// Override the meta with matched images, to fix missing metadata.
				wp_update_attachment_metadata( $id, $image_meta );
			}
		}

		return $summary;
	}

	/**
	 * Assess the files generated for an attachment.
	 *
	 * @param  integer $id    Attachment ID.
	 * @param  array   $image Maybe the known attachment metadata.
	 * @return array
	 */
	public static function assess_files_for_attachment_original( $id, $image = [] ) { //phpcs:ignore
		if ( empty( $image ) ) {
			$image = wp_get_attachment_metadata( $id );
		}

		$dir        = '';
		$upload_dir = wp_upload_dir();
		$basedir    = trailingslashit( $upload_dir['basedir'] );
		$list       = [];
		$full       = [];
		$gene       = [];

		// Assess the original files.
		if ( ! empty( $image['file'] ) ) {
			$full[] = $basedir . $image['file'];
			$dir    = trailingslashit( dirname( $image['file'] ) );
		}
		$full = array_unique( $full );
		if ( ! empty( $image['original_image'] ) ) {
			$list[] = $basedir . $dir . $image['original_image'];
		}
		$list = array_unique( $list );
		if ( ! empty( $image['sizes'] ) ) {
			foreach ( $image['sizes'] as $key => $value ) {
				if ( ! empty( $value['file'] ) ) {
					$gene[] = $basedir . $dir . $value['file'];
				}
			}
		}

		// Assess the generated files.
		if ( ! empty( $list ) ) {
			foreach ( $list as $file ) {
				$ext    = pathinfo( $file, PATHINFO_EXTENSION );
				$name   = pathinfo( $file, PATHINFO_FILENAME );
				$path   = pathinfo( $file, PATHINFO_DIRNAME );
				$gene[] = $file;
				$extra  = glob( $path . '/' . $name . '-*x*.' . $ext, GLOB_BRACE );
				if ( ! empty( $extra ) ) {
					foreach ( $extra as $kglob => $tmp ) {
						$test = explode( $name, $tmp );
						$test = ( ! empty( $test[1] ) ) ? $test[1] : '';
						$pos  = strrpos( $test, '-' );
						if ( ! empty( $pos ) ) {
							$rest = substr( $test, 0, $pos );
							if ( ! empty( $rest ) ) {
								// Unregister images from other filenames re-iterations.
								unset( $extra[ $kglob ] );
							}
						}
					}
				}
				if ( ! empty( $extra ) ) {
					$gene = array_merge( $gene, $extra );
				}
			}
		}
		$gene = array_unique( $gene );
		$gene = array_diff( $gene, $full );
		$gene = array_diff( $gene, $list );

		// Process lists to see only names.
		$list_names = [];
		if ( ! empty( $list ) ) {
			foreach ( $list as $value ) {
				$list_names[] = str_replace( $basedir, '', $value );
			}
		}
		$full_names = [];
		if ( ! empty( $full ) ) {
			foreach ( $full as $value ) {
				$full_names[] = str_replace( $basedir, '', $value );
			}
		}
		$gene_names = [];
		if ( ! empty( $gene ) ) {
			foreach ( $gene as $value ) {
				$gene_names[] = str_replace( $basedir, '', $value );
			}
		}

		$result = [
			'names' => [
				'original'  => $list_names,
				'full'      => $full_names,
				'generated' => $gene_names,
			],
			'paths' => [
				'original'  => $list,
				'full'      => $full,
				'generated' => $gene,
			],
		];

		return $result;
	}

	/**
	 * Attempt to delete all generate files on delete attachment.
	 *
	 * @param  integer $post_id Attachment ID.
	 * @return void
	 */
	public static function on_delete_attachment( $post_id ) { //phpcs:ignore
		$gene_all = self::assess_files_for_attachment_original( $post_id );
		if ( ! empty( $gene_all['paths']['generated'] ) ) {
			foreach ( $gene_all['paths']['generated'] as $value ) {
				@unlink( $value ); //phpcs:ignore
			}
		}
	}

	/**
	 * Force the custom threshold for WP >= 5.3, when there is a forced original size in the settings.
	 *
	 * @param  integer $initial_value Maximum width.
	 * @param  integer $imagesize     Computed attributes for the file.
	 * @param  string  $file          The file.
	 * @param  integer $attachment_id The attachment ID.
	 * @return integer|boolean
	 */
	public static function big_image_size_threshold_forced( $initial_value, $imagesize, $file, $attachment_id ) { //phpcs:ignore
		if ( ! empty( self::$settings['force_original_to'] ) ) {
			self::load_settings_for_post_id( $attachment_id );
			$size = self::get_all_image_sizes( self::$settings['force_original_to'] );
			if ( empty( $size ) ) {
				return $initial_value;
			}

			$estimated = wp_constrain_dimensions( $imagesize[0], $imagesize[1], $size['width'], $size['height'] );
			\SIRSC\Helper\debug( 'Estimated before applying threshold ' . print_r( $estimated, 1 ), true, true ); //phpcs:ignore

			$relative = $estimated[0];
			if ( $estimated[0] < $estimated[1] ) {
				$relative = $estimated[1];
			}

			if ( $relative < $initial_value ) {
				\SIRSC\Helper\debug( 'Force the image threshold to ' . $relative, true, true );
				add_filter(
					'wp_editor_set_quality',
					function( $def, $mime = '' ) { //phpcs:ignore
						return self::DEFAULT_QUALITY;
					},
					10
				);
				return (int) $relative;
			}

			if ( ! empty( $size['width'] ) && $size['width'] < $initial_value ) {
				\SIRSC\Helper\debug( 'Force the image threshold to ' . $size['width'], true, true );
				add_filter(
					'wp_editor_set_quality',
					function( $def, $mime = '' ) { //phpcs:ignore
						return self::DEFAULT_QUALITY;
					},
					10
				);
				return (int) $size['width'];
			}
		}
		return $initial_value;
	}

	/**
	 * Maybe filter initial metadata.
	 *
	 * @param  array   $metadata      Computed metadata.
	 * @param  integer $attachment_id The attachment that is processing.
	 * @return array
	 */
	public static function wp_generate_attachment_metadata( $metadata, $attachment_id ) { //phpcs:ignore
		if ( self::$wp_ver >= 5.3 ) {
			// Metadata parameter is empty, let's fetch it from the database if existing.
			$metadata = wp_get_attachment_metadata( $attachment_id );
		} else {
			// Initially preserve it.
			update_post_meta( $attachment_id, '_wp_attachment_metadata', $metadata );
		}

		\SIRSC\Helper\debug( 'PREPARE AND RELEASE THE METADATA FOR ' . $attachment_id, true, true );
		$filter_out = self::cleanup_before_releasing_the_metadata_on_upload( $attachment_id );
		$filter_out = apply_filters( 'sirsc_computed_metadata_after_upload', $filter_out, $attachment_id );
		do_action( 'sirsc_attachment_images_ready', $filter_out, $attachment_id );
		return $filter_out;
	}

	/**
	 * Cleanup before releasing the atachment metadata on upload.
	 *
	 * @param  integer $attachment_id The attachment ID.
	 * @return array
	 */
	public static function cleanup_before_releasing_the_metadata_on_upload( $attachment_id ) { //phpcs:ignore
		$filter_out = wp_get_attachment_metadata( $attachment_id );
		if ( defined( 'SIRSC_BRUTE_RENAME' ) && SIRSC_BRUTE_RENAME !== $filter_out['file'] ) {
			$filter_out['file'] = SIRSC_BRUTE_RENAME;
			if ( ! empty( self::$settings['force_original_to'] )
				&& empty( $filter_out['sizes'][ self::$settings['force_original_to'] ] ) ) {
				$uploads  = wp_get_upload_dir();
				$filetype = wp_check_filetype( trailingslashit( $uploads['basedir'] ) . $filter_out['file'] );

				$filter_out['sizes'][ self::$settings['force_original_to'] ] = [
					'file'      => wp_basename( $filter_out['file'] ),
					'width'     => $filter_out['width'],
					'height'    => $filter_out['height'],
					'mime-type' => $filetype['type'],
				];
			}
			update_post_meta( $attachment_id, '_wp_attachment_metadata', $filter_out );
		}

		return $filter_out;
	}

	/**
	 * Execute the removal of an attachment image size and file.
	 *
	 * @param  integer $id    The attachment id.
	 * @param  string  $size  The specified image size.
	 * @param  string  $fname A specified filename.
	 * @param  array   $image Maybe the previously computed attachment metadata.
	 * @return boolean|string
	 */
	public static function execute_specified_attachment_file_delete( $id = 0, $size = '', $fname = '', &$image = [] ) { //phpcs:ignore
		if ( empty( $image ) ) {
			$image = wp_get_attachment_metadata( $id );
		}

		if ( ! empty( $fname ) ) {
			\SIRSC\Action\cleanup_attachment_one_size_file( $id, $size, $fname );
		} else {
			\SIRSC\Action\cleanup_attachment_one_size( $id, $size );
		}
		$image = wp_get_attachment_metadata( $id );

		return true;
	}

	/**
	 * Check if the attached image is required to be replaced with the "Force Original" from the settings.
	 *
	 * @param  integer $meta_id    Post meta id.
	 * @param  integer $post_id    Post ID.
	 * @param  string  $meta_key   Post meta key.
	 * @param  array   $meta_value Post meta value.
	 */
	public static function process_filtered_attachments( $meta_id = '', $post_id = '', $meta_key = '', $meta_value = '' ) { //phpcs:ignore
		if ( ! empty( $post_id ) && '_wp_attachment_metadata' === $meta_key && ! empty( $meta_value ) ) {
			\SIRSC\Helper\notify_doing_sirsc();
			self::load_settings_for_post_id( $post_id );
			\SIRSC\Helper\debug( 'FIRST METADATA SAVED ' . print_r( $meta_value, 1 ), true, true ); //phpcs:ignore

			if ( ! empty( self::$settings['force_original_to'] ) ) {
				if ( self::$wp_ver >= 5.3 ) {
					// Maybe rename the full size with the original.
					$info = self::assess_rename_original( $post_id );
				} else {
					// Maybe swap the forced size with the full.
					$file    = get_attached_file( $post_id );
					$fo_orig = self::$settings['force_original_to'];
					$size    = self::get_all_image_sizes( $fo_orig );
					self::swap_full_with_another_size( $post_id, $file, $fo_orig, $size['crop'], 0 );
				}
			}

			if ( ! empty( $info ) && $info['dir'] . $info['name'] !== $meta_value['file'] ) {
				// Brute update and notify other scripts of this.
				$meta         = wp_get_attachment_metadata( $post_id );
				$meta['file'] = $info['dir'] . $info['name'];
				update_post_meta( $post_id, '_wp_attachment_metadata', $meta );
				if ( ! defined( 'SIRSC_BRUTE_RENAME' ) ) {
					define( 'SIRSC_BRUTE_RENAME', $meta['file'] );
				}
			}

			\SIRSC\Helper\debug( 'START PROCESS ALL REMAINING SIZES FOR ' . $post_id, true, true );
			\SIRSC\Helper\make_images_if_not_exists( $post_id, 'all' );
		}
	}

	/**
	 * Admin featured size.
	 *
	 * @param  string  $size         Initial size.
	 * @param  integer $thumbnail_id Attachment ID.
	 * @param  integer $post         Post ID.
	 * @return string
	 */
	public static function admin_featured_size( $size, $thumbnail_id = 0, $post = 0 ) { //phpcs:ignore
		$override = get_option( 'sirsc_admin_featured_size' );
		if ( ! empty( $override ) ) {
			return $override;
		}
		return $size;
	}

	/**
	 * Attempt to refresh extra info in the footer of the image details lightbox.
	 *
	 * @return void
	 */
	public static function refresh_extra_info_footer() {
		if ( empty( $_REQUEST['sirsc_data'] ) ) { //phpcs:ignore
			// Fail-fast.
			return;
		}

		$data = self::has_sirsc_data();
		$id   = ( ! empty( $data['post_id'] ) ) ? (int) $data['post_id'] : 0;
		if ( empty( $id ) ) {
			// Fail-fast.
			return;
		}

		echo self::document_ready_js( 'sirsc_refresh_extra_info_footer( \'' . (int) $id . '\', \'#sirsc-extra-info-footer-' . (int) $id. '\' );' ); //phpcs:ignore
	}

	/**
	 * Add the plugin settings and plugin URL links.
	 *
	 * @param array $links The plugin links.
	 */
	public static function plugin_action_links( $links ) { //phpcs:ignore
		$all   = [];
		$all[] = '<a href="' . esc_url( self::$plugin_url ) . '">' . esc_html__( 'Settings', 'sirsc' ) . '</a>';
		$all[] = '<a href="https://iuliacazan.ro/image-regenerate-select-crop">' . esc_html__( 'Plugin URL', 'sirsc' ) . '</a>';
		$all   = array_merge( $all, $links );
		return $all;
	}
}

// Create the class name alias.
class_alias( 'SIRSC_Image_Regenerate_Select_Crop', 'SIRSC' );

$sirsc = SIRSC::get_instance();
add_action( 'wp_loaded', [ $sirsc, 'filter_ignore_global_image_sizes' ] );
register_activation_hook( __FILE__, [ $sirsc, 'activate_plugin' ] );
register_deactivation_hook( __FILE__, [ $sirsc, 'deactivate_plugin' ] );
require_once SIRSC_PLUGIN_FOLDER . 'inc/placeholder.php';
