<?php
/**
 * Export/Import extension.
 *
 * @package sirsc
 * @version 1.0
 */

/**
 * Class for Image Regenerate & Select Crop plugin adon Export/Import.
 */
class SIRSC_Adons_Import_Export {

	const ADON_PAGE_SLUG = 'sirsc-adon-import-export';
	const ADON_SLUG      = 'import-export';

	/**
	 * Class options.
	 *
	 * @var array
	 */
	public static $options = [
		'sirsc_settings',
		'sirsc_user_custom_rules',
		'sirsc_user_custom_rules_usable',
		'sirsc_use_custom_image_sizes',
		'sirsc_override_large_size',
		'sirsc_override_medium_size',
	];

	/**
	 * Class instance.
	 *
	 * @var object
	 */
	private static $instance;

	/**
	 * Get active object instance
	 *
	 * @return object
	 */
	public static function get_instance() { //phpcs:ignore
		if ( ! self::$instance ) {
			self::$instance = new SIRSC_Adons_Import_Export();
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
		if ( ! class_exists( 'SIRSC_Image_Regenerate_Select_Crop' ) ) {
			return;
		}

		if ( is_admin() ) {
			add_action( 'init', [ get_called_class(), 'maybe_import_settings' ], 60 );
			add_action( 'admin_menu', [ get_called_class(), 'adon_admin_menu' ], 20 );
		}
	}

	/**
	 * Get options.
	 *
	 * @return void
	 */
	public static function get_options() {
		$post_types = \SIRSC\Helper\get_all_post_types_plugin();
		if ( ! empty( $post_types ) ) {
			foreach ( $post_types as $key => $value ) {
				self::$options[] = 'sirsc_settings_' . $key;
			}
		}
	}

	/**
	 * Prepate the options export string.
	 *
	 * @return string
	 */
	public static function prepare_export_string() { //phpcs:ignore
		self::get_options();
		$export = [];
		foreach ( self::$options as $key ) {
			$export[ $key ] = get_option( $key, '' );
		}

		return serialize( $export ); //phpcs:ignore
	}

	/**
	 * Maybe import settings.
	 *
	 * @return void
	 */
	public static function maybe_import_settings() {
		$nonce = filter_input( INPUT_POST, '_sirsc_adon_export_settings_nonce', FILTER_DEFAULT );
		if ( ! empty( $nonce ) && wp_verify_nonce( $nonce, '_sirsc_adon_export_settings_action' ) ) {
			$error = 0;
			if ( current_user_can( 'manage_options' ) ) {
				// Maybe update settings.
				$set = filter_input( INPUT_POST, 'sirsc-import-settings', FILTER_DEFAULT );
				if ( ! empty( $set ) ) {
					$array = maybe_unserialize( $set );
					if ( is_array( $array ) && ! empty( $array ) ) {
						foreach ( $array as $key => $value ) {
							if ( empty( $value ) ) {
								delete_option( $key );
							} else {
								update_option( $key, $value );
							}
						}
						add_action( 'admin_notices', function() {
							printf(
								'<div class="%1$s"><p>%2$s</p></div>',
								esc_attr( 'notice notice-success is-dismissible' ),
								esc_html( __( 'The plugin settings have been imported successfully.', 'sirsc' ) )
							);
						} );
					} else {
						++ $error;
					}
				} else {
					++ $error;
				}
			} else {
				++ $error;
			}

			if ( ! empty( $error ) ) {
				add_action( 'admin_notices', function() {
					printf(
						'<div class="%1$s"><p>%2$s</p></div>',
						esc_attr( 'notice notice-error is-dismissible' ),
						esc_html( __( 'The plugin settings were not imported, something went wrong.', 'sirsc' ) )
					);
				} );
			}
		}
	}

	/**
	 * Maybe register the image sizes.
	 */
	public static function maybe_register_custom_image_sizes_snippet() { //phpcs:ignore
		$all = maybe_unserialize( get_option( 'sirsc_use_custom_image_sizes' ) );
		if ( empty( $all['sizes'] ) ) {
			// Fail-fast, no custom image sizes registered.
			return;
		} else {
			$snippet = '';
			foreach ( $all['sizes'] as $i => $value ) {
				if ( ! empty( $value['name'] ) && is_scalar( $value['name'] )
					&& ( ! empty( $value['width'] ) || ! empty( $value['height'] ) ) ) {
					$crop     = ( ! empty( $value['crop'] ) ) ? 'true' : 'false';
					$snippet .= PHP_EOL . '			add_image_size( \'' . $value['name'] . '\', ' . (int) $value['width'] . ', ' . (int) $value['height'] . ', ' . $crop . ' );';
				}
			}

			if ( ! empty( $snippet ) ) {
				$text  = '<?php';
				$text .= PHP_EOL . 'add_action( \'after_setup_theme\', \'sirsc_legacy_image_sizes\' );';
				$text .= PHP_EOL . 'if ( ! function_exists( \'sirsc_legacy_image_sizes\' ) ) {';
				$text .= PHP_EOL . '	/**';
				$text .= PHP_EOL . '	 * Register the image sizes that were defined with the';
				$text .= PHP_EOL . '	 * Image Regenerate & Select Crop plugin.';
				$text .= PHP_EOL . '	 */';
				$text .= PHP_EOL . '	function sirsc_legacy_image_sizes() {';
				$text .= PHP_EOL . '		if ( ! class_exists( \'SIRSC_Image_Regenerate_Select_Crop\' ) ) {';
				$text .= $snippet;
				$text .= PHP_EOL . '		}';
				$text .= PHP_EOL . '	}';
				$text .= PHP_EOL . '}';

				$snippet = $text;
			}

			return $snippet;
		}
	}

	/**
	 * Add the plugin menu.
	 *
	 * @return void
	 */
	public static function adon_admin_menu() {
		add_submenu_page(
			'image-regenerate-select-crop-settings',
			__( 'Import/Export', 'sirsc' ),
			'<span class="dashicons dashicons-admin-plugins sirsc-mini"></span> ' . __( 'Import/Export', 'sirsc' ),
			'manage_options',
			self::ADON_PAGE_SLUG,
			[ get_called_class(), 'adon_page' ]
		);
	}

	/**
	 * Add the plugin menu.
	 *
	 * @return void
	 */
	public static function adon_page() {
		$export  = self::prepare_export_string();
		$import  = maybe_unserialize( $export );
		$snippet = self::maybe_register_custom_image_sizes_snippet();

		SIRSC_Adons::check_adon_valid( self::ADON_SLUG );
		$desc = SIRSC_Adons::get_adon_details( self::ADON_SLUG, 'description' );
		?>

		<div class="wrap sirsc-settings-wrap sirsc-feature">
			<?php \SIRSC\Admin\show_plugin_top_info(); ?>
			<?php \SIRSC\Admin\maybe_all_features_tab(); ?>
			<div class="sirsc-tabbed-menu-content">
				<div class="rows bg-secondary no-top">
					<div class="min-height-130">
						<img src="<?php echo esc_url( SIRSC_PLUGIN_URL . 'assets/images/adon-import-export-image.png' ); ?>" loading="lazy" class="negative-margins has-left">
						<h2>
							<span class="dashicons dashicons-admin-plugins"></span>
							<?php esc_html_e( 'Import/Export', 'sirsc' ); ?>
						</h2>
						<?php echo wp_kses_post( $desc ); ?>
						<b><?php esc_html_e( 'Please note that the import/export of the settings is in relation with the image sizes that are found on the instance, through the plugins that are activated and also the theme settings. You might need to partially adjust these manually after an import.', 'sirsc' ); ?></b>
					</div>
				</div>

				<form action="" method="post" autocomplete="off" id="js-sirsc_adon_import_frm">
					<?php wp_nonce_field( '_sirsc_adon_export_settings_action', '_sirsc_adon_export_settings_nonce' ); ?>

					<div class="rows three-columns bg-secondary has-gaps breakable">
						<div class="span6">
							<h2><?php esc_html_e( 'Export Settings', 'sirsc' ); ?> - JSON</h2>
							<p><?php esc_html_e( 'Copy the settings and import these into another instance.', 'sirsc' ); ?></p>
							<textarea rows="16" class="code"><?php echo esc_html( $export ); ?></textarea>
						</div>

						<div class="span6">
							<button type="submit"
								class="button button-primary f-right"
								name="submit"
								value="submit"
								onclick="sirscToggleAdon( 'sirsc-import-settings' );">
								<?php esc_html_e( 'Import Settings', 'sirsc' ); ?>
							</button>
							<h2><?php esc_html_e( 'Import Settings', 'sirsc' ); ?>  - JSON</h2>

							<p><?php esc_html_e( 'Paste here the settings and import these into the current instance.', 'sirsc' ); ?></p>
							<textarea name="sirsc-import-settings" rows="16" class="code"></textarea>
						</div>

						<?php if ( ! empty( $snippet ) ) : ?>
							<div class="span6">
								<h2><?php esc_html_e( 'Registered Image Sizes', 'sirsc' ); ?></h2>
								<p><?php esc_html_e( 'If you deactivate the plugin but still want to keep the image sizes you registered with this plugin, you can copy the snippet in your theme functions.php file or in a plugin.', 'sirsc' ); ?></p>
								<textarea rows="14" class="code"><?php echo esc_html( $snippet ); ?></textarea>
							</div>
						<?php endif; ?>
					</div>
				</form>
			</div>
		</div>
		<?php
	}
}

// Instantiate the class.
SIRSC_Adons_Import_Export::get_instance();
