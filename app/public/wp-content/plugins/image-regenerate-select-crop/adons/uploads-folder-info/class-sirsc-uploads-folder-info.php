<?php
/**
 * Uploads folder info extension.
 *
 * @package sirsc
 * @version 1.0
 */

/**
 * Class for Image Regenerate & Select Crop plugin adon Upload Folder Info.
 */
class SIRSC_Adons_Uploads_Folder_Info {

	const ADON_PAGE_SLUG = 'sirsc-adon-uploads-folder-info';
	const ADON_SLUG      = 'uploads-folder-info';

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
			self::$instance = new SIRSC_Adons_Uploads_Folder_Info();
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
			add_action( 'admin_menu', [ get_called_class(), 'adon_admin_menu' ], 20 );
			add_action( 'admin_enqueue_scripts', [ get_called_class(), 'load_assets' ] );
			add_action( 'wp_ajax_sirsc_adon_ufi_execute_refresh', [ get_called_class(), 'display_filesinfo' ] );
			add_action( 'wp_ajax_sirsc_adon_ufi_display_summary', [ get_called_class(), 'display_summary' ] );

			self::init_buttons();
		}
	}

	/**
	 * Enqueue the css and javascript files
	 */
	public static function load_assets() {
		$uri = $_SERVER['REQUEST_URI']; //phpcs:ignore
		if ( ! substr_count( $uri, 'page=sirsc-adon-uploads-folder-info' ) ) {
			// Fail-fast, the assets should not be loaded.
			return;
		}

		wp_enqueue_script(
			'sirsc-adons-ufi',
			SIRSC_PLUGIN_URL . 'adons/uploads-folder-info/src/index.js',
			[ 'sirsc-iterator' ],
			filemtime( SIRSC_PLUGIN_DIR . 'adons/uploads-folder-info/src/index.js' ),
			true
		);

		wp_enqueue_style(
			'sirsc-adons-ufi',
			SIRSC_PLUGIN_URL . 'adons/uploads-folder-info/src/style.css',
			[],
			filemtime( SIRSC_PLUGIN_DIR . 'adons/uploads-folder-info/src/style.css' ),
			false
		);
	}

	/**
	 * Init the adon main buttons.
	 *
	 * @return void
	 */
	public static function init_buttons() {
		do_action(
			'sirsc/iterator/setup_buttons',
			'sirsc-ufi',
			[
				'refresh' => [
					'icon'     => '<span class="dashicons dashicons-image-rotate"></span>',
					'text'     => __( 'refresh', 'sirsc' ),
					'callback' => 'sirscUfiStartRefresh()',
					'class'    => 'auto f-right',
				],
			]
		);
	}

	/**
	 * Add the plugin menu.
	 *
	 * @return void
	 */
	public static function adon_page() {
		SIRSC_Adons::check_adon_valid( self::ADON_SLUG );
		$desc = SIRSC_Adons::get_adon_details( self::ADON_SLUG, 'description' );
		?>

		<div class="wrap sirsc-settings-wrap sirsc-feature">
			<?php \SIRSC\Admin\show_plugin_top_info(); ?>
			<?php \SIRSC\Admin\maybe_all_features_tab(); ?>
			<div class="rows bg-secondary no-top">
				<div class="min-height-130">
					<img src="<?php echo esc_url( SIRSC_PLUGIN_URL . 'assets/images/adon-uploads-folder-info-image.png' ); ?>" loading="lazy" class="negative-margins has-left">
					<h2>
						<span class="dashicons dashicons-admin-plugins"></span>
						<?php esc_html_e( 'Uploads Folder Info', 'sirsc' ); ?>
					</h2>
					<?php echo wp_kses_post( $desc ); ?>
				</div>
			</div>

			<div class="rows bg-secondary has-gaps breakable">
				<div class="span3">
					<div id="sirsc-summary-wrap" class="sirsc-feature sirsc-target">
						<?php self::display_summary(); ?>
					</div>

					<p></p>

					<?php self::folder_refresh_button(); ?>
				</div>
				<div class="span9">
					<div id="sirsc-filesinfo-wrap" class="sirsc-feature sirsc-folders-info sirsc-target">
						<?php self::display_filesinfo(); ?>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Show an refresh trigger button markup.
	 *
	 * @return void
	 */
	public static function folder_refresh_button() {
		?>
		<br>
		<h2><?php esc_html_e( 'Refresh Summary', 'sirsc' ); ?></h2>
		<p>
			<?php \SIRSC\Iterator\button_display( 'sirsc-ufi-refresh' ); ?>
			<?php esc_html_e( 'Click to refresh summary & folder details. This will refresh the totals and counts if something was updated in the meanwhile.', 'sirsc' ); ?>
		</p>
		<?php
	}

	/**
	 * Display folders summary.
	 *
	 * @param  array $info Computed info.
	 * @return void
	 */
	public static function display_summary( $info = '' ) { //phpcs:ignore
		?>
		<h2>
			<span class="dashicons dashicons-info-outline"></span>
			<?php esc_html_e( 'Folder Summary', 'sirsc' ); ?>
		</h2>

		<?php
		$info = ( empty( $info ) ) ? get_transient( 'sirsc_adon_uploads_folder_summary' ) : $info;
		if ( ! empty( $info ) ) {
			$root = $info[0];
			?>
			<div class="sirsc-folders-info-wrap">
				<table>
					<tr>
						<td><?php esc_html_e( 'Upload folder', 'sirsc' ); ?>: </td>
						<td><b><?php echo esc_html( $root['name'] ); ?></b></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Size', 'sirsc' ); ?>: </td>
						<td>
							<b><?php echo esc_html( \SIRSC\Helper\human_filesize( $root['totals']['files_size'] ) ); ?></b>
							(<?php echo (int) $root['totals']['files_size']; ?>
							<?php esc_html_e( 'bytes', 'sirsc' ); ?>)
						</td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Total folders', 'sirsc' ); ?>: </td>
						<td><b><?php echo (int) $root['totals']['folders_count']; ?></b></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Total files', 'sirsc' ); ?>: </td>
						<td><b><?php echo (int) $root['totals']['files_count']; ?></b></td>
					</tr>
				</table>
			</div>
			<?php
		} else {
			?>
			<div class="sirsc-folders-info-wrap">
				<?php esc_html_e( 'Currenty, there is no info.', 'sirsc' ); ?>
			</div>
			<?php
		}

		$act = filter_input( INPUT_GET, 'action', FILTER_DEFAULT );
		if ( 'sirsc_adon_ufi_display_summary' === $act ) {
			wp_die();
			die();
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
			__( 'Uploads Folder Info', 'sirsc' ),
			'<span class="dashicons dashicons-admin-plugins sirsc-mini"></span> ' . __( 'Uploads Folder Info', 'sirsc' ),
			'manage_options',
			self::ADON_PAGE_SLUG,
			[ get_called_class(), 'adon_page' ]
		);
	}

	/**
	 * Output folders details from info.
	 *
	 * @param  array $info Folders computed info.
	 * @return void
	 */
	public static function output_folders_details( $info ) { //phpcs:ignore
		if ( ! empty( $info ) ) {
			$root = $info[0];
			?>
			<div class="sirsc-folders-info-wrap">

				<div class="rows settings-rows bg-secondary dense small-pad no-shadow no-gaps heading">
					<div class="span8">
						<h3 class="heading"><?php esc_html_e( 'Folder', 'sirsc' ); ?></h3>
					</div>
					<div class="span3 a-right">
						<h3 class="heading"><?php esc_html_e( 'Total Folders', 'sirsc' ); ?></h3>
					</div>
					<div class="span3 a-right">
						<h3 class="heading"><?php esc_html_e( 'Total Files', 'sirsc' ); ?></h3>
					</div>
					<div class="span3 a-right">
						<h3 class="heading"><?php esc_html_e( 'Total Size', 'sirsc' ); ?></h3>
					</div>
					<div class="span3 a-right">
						<h3 class="heading"><?php esc_html_e( 'Total Bytes', 'sirsc' ); ?></h3>
					</div>
				</div>

				<?php
				$k = 0;
				foreach ( $info as $folder ) :
					++ $k;
					$s  = 'padding-left: ' . ( ( $folder['level'] * 32 ) + 48 ) . 'px';
					$cl = ( 0 === $k % 2 ) ? 'bg-trans50' : 'bg-dark';
					?>
					<div class="rows settings-rows dense <?php echo esc_attr( $cl ); ?> no-gaps no-shadow no-top">
						<div class="span8 name-wrap"
							style="<?php echo esc_attr( $s ); ?>"
							data-title="<?php esc_attr_e( 'Folder', 'sirsc' ); ?>">
							<span class="name">
								<b><?php echo esc_html( $folder['name'] ); ?></b>
							</span>
						</div>
						<div class="span3 a-right" data-title="<?php esc_attr_e( 'Total Folders', 'sirsc' ); ?>">
							<?php if ( ! empty( $folder['totals']['folders_count'] ) ) : ?>
								<?php echo (int) $folder['totals']['folders_count']; ?>
							<?php endif; ?>
						</div>
						<div class="span3 a-right" data-title="<?php esc_attr_e( 'Total Files', 'sirsc' ); ?>">
							<?php if ( ! empty( $folder['totals']['files_count'] ) ) : ?>
								<?php echo (int) $folder['totals']['files_count']; ?>
							<?php endif; ?>
						</div>
						<div class="span3 a-right" data-title="<?php esc_attr_e( 'Total Size', 'sirsc' ); ?>">
							<?php if ( ! empty( $folder['totals']['files_size'] ) ) : ?>
								<b><?php echo esc_html( \SIRSC\Helper\human_filesize( $folder['totals']['files_size'] ) ); ?></b>
							<?php endif; ?>
						</div>
						<div class="span3 a-right last" data-title="<?php esc_attr_e( 'Total Bytes', 'sirsc' ); ?>">
							<?php if ( ! empty( $folder['totals']['files_size'] ) ) : ?>
								<?php echo (int) $folder['totals']['files_size']; ?>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
			<?php
		}
	}
	/**
	 * Compute size.
	 *
	 * @return void
	 */
	public static function display_filesinfo() {
		$is_ajax = false;
		$act     = filter_input( INPUT_GET, 'action', FILTER_DEFAULT );
		if ( ! empty( $act ) && 'sirsc_adon_ufi_execute_refresh' === $act ) {
			$is_ajax = true;
		}

		$upls = wp_upload_dir();
		$base = trailingslashit( $upls['basedir'] );
		$trid = 'sirsc_adon_uploads_folder_summary';

		if ( true === $is_ajax ) {
			// Force recompute the transient on ajax too.
			$info = false;
		} else {
			$info = get_transient( $trid );
		}
		if ( false === $info ) {
			$info = \SIRSC\Helper\get_folders_list( $base );
			set_transient( $trid, $info, 1 * HOUR_IN_SECONDS );
			update_option( 'sirsc_adon_uploads_files_count', $info[0]['totals']['files_count'] );
		}

		?>
		<h2><?php esc_html_e( 'Folder Details', 'sirsc' ); ?></h2>
		<?php self::output_folders_details( $info ); ?>
		<?php
		if ( true === $is_ajax ) {
			if ( class_exists( 'SIRSC_Adons_Images_Profiler' ) ) {
				update_option( SIRSC_Adons_Images_Profiler::PLUGIN_TABLE . '_proc_dir', '' );
				update_option( SIRSC_Adons_Images_Profiler::PLUGIN_TABLE . '_proc_item', '' );
			}

			echo \SIRSC\Helper\document_ready_js( \SIRSC\Iterator\button_callback( 'sirsc-ufi-refresh', 'reset' ) . ' sirscUfiDisplaySummary();' ); //phpcs:ignore

			wp_die();
			die();
		}
	}
}

// Instantiate the class.
SIRSC_Adons_Uploads_Folder_Info::get_instance();
