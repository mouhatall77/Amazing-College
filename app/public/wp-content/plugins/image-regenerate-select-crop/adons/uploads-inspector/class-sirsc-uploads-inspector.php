<?php
/**
 * Uploads inspector extension.
 *
 * @package sirsc
 * @version 1.0
 */

/**
 * Class for Image Regenerate & Select Crop plugin adon Uploads Inspector.
 */
class SIRSC_Adons_Uploads_Inspector {

	const PLUGIN_VER        = 6.0;
	const PLUGIN_ASSETS_VER = '20210515.1527';
	const PLUGIN_TRANS      = 'sirsc_adon_uploads_inspector';
	const PLUGIN_TABLE      = 'sirsc_adon_uploads_inspector';
	const PLUGIN_BATCH_SIZE = 20;
	const ADON_PAGE_SLUG    = 'sirsc-adon-uploads-inspector';
	const ADON_SLUG         = 'uploads-inspector';

	/**
	 * Class instance.
	 *
	 * @var object
	 */
	private static $instance;

	/**
	 * Plugin settings.
	 *
	 * @var array
	 */
	public static $settings;

	/**
	 * Plugin identified and filtered post types.
	 *
	 * @var array
	 */
	public static $post_types;

	/**
	 * Get active object instance
	 *
	 * @return object
	 */
	public static function get_instance() { //phpcs:ignore
		if ( ! self::$instance ) {
			self::$instance = new SIRSC_Adons_Uploads_Inspector();
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

		$called = get_called_class();
		if ( is_admin() ) {
			add_action( 'admin_menu', [ get_called_class(), 'adon_admin_menu' ], 20 );
			add_action( 'plugins_loaded', [ $called, 'load_textdomain' ] );
			add_action( 'admin_enqueue_scripts', [ $called, 'load_assets' ] );
			add_action( 'sirsc_folder_assess_images_button', [ $called, 'folder_assess_images_button' ] );
			add_action( 'sirsc_folder_refresh_button', [ $called, 'folder_refresh_button' ] );
			add_action( 'wp_ajax_sirsc_adon_ui_display_summary', [ $called, 'display_summary' ] );
			add_action( 'wp_ajax_sirsc_adon_ui_display_filesinfo', [ $called, 'display_filesinfo' ] );
			add_action( 'wp_ajax_sirsc_adon_ui_display_listing', [ $called, 'display_listing' ] );
			add_action( 'wp_ajax_sirsc_adon_ui_execute_refresh', [ $called, 'execute_refresh' ] );
			add_action( 'wp_ajax_sirsc_adon_ui_execute_assess', [ $called, 'execute_assess' ] );
			add_action( 'sirsc_folder_assess_images_stats', [ $called, 'folder_assess_images_stats' ] );

			// Check extension version.
			add_action( 'init', [ $called, 'adon_ver_check' ], 30 );
			self::init_buttons();
		}
	}

	/**
	 * Load text domain for internalization.
	 */
	public static function load_textdomain() {
		load_plugin_textdomain( 'sirsc', false, basename( dirname( __FILE__ ) ) . '/langs/' );
	}

	/**
	 * Enqueue the css and javascript files
	 */
	public static function load_assets() {
		$uri = $_SERVER['REQUEST_URI']; //phpcs:ignore
		if ( ! substr_count( $uri, 'page=sirsc-adon-uploads-inspector' ) ) {
			// Fail-fast, the assets should not be loaded.
			return;
		}

		wp_register_script(
			'sirsc-adons-improf',
			SIRSC_PLUGIN_URL . 'adons/uploads-inspector/src/index.js',
			[ 'sirsc-iterator' ],
			filemtime( SIRSC_PLUGIN_DIR . 'adons/uploads-inspector/src/index.js' ),
			true
		);
		wp_localize_script(
			'sirsc-adons-improf',
			'SIRSC_Adons_Improf',
			[
				'ajaxUrl'      => esc_url( admin_url( 'admin-ajax.php' ) ),
				'listBoxTitle' => __( 'List', 'sirsc' ),
				'delay'        => 2000,
			]
		);
		wp_enqueue_script( 'sirsc-adons-improf' );

		wp_enqueue_style(
			'sirsc-adons-improf',
			SIRSC_PLUGIN_URL . 'adons/uploads-inspector/src/style.css',
			[],
			filemtime( SIRSC_PLUGIN_DIR . 'adons/uploads-inspector/src/style.css' ),
			false
		);
	}

	/**
	 * The actions to be executed when the plugin is updated.
	 *
	 * @return void
	 */
	public static function adon_ver_check() {
		$opt = str_replace( '-', '_', self::PLUGIN_TRANS ) . '_db_ver';
		$dbv = get_option( $opt, 0 );
		if ( self::PLUGIN_VER !== (float) $dbv ) {
			self::maybe_upgrade_db();
			set_transient( self::PLUGIN_TRANS, true );
		}
	}

	/**
	 * Maybe upgrade the table structure.
	 *
	 * @return void
	 */
	public static function maybe_upgrade_db() {
		global $wpdb;
		$opt = str_replace( '-', '_', self::PLUGIN_TRANS ) . '_db_ver';
		$dbv = get_option( $opt, 0 );
		if ( self::PLUGIN_VER !== (float) $dbv ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			$sql = ' CREATE TABLE ' . self::PLUGIN_TABLE . ' (
				`id` bigint(20) AUTO_INCREMENT,
				`date` bigint(20),
				`type` varchar(15),
				`path` varchar(255),
				`attachment_id` bigint(20),
				`size_name` varchar(255),
				`size_width` int(11),
				`size_height` int(11),
				`mimetype` varchar(32),
				`filesize` bigint(20),
				`in_option` varchar(255),
				`valid` tinyint(1) default 0,
				`assessed` tinyint(1) default 0,
				`count_files` bigint(20),
				UNIQUE KEY `id` (id),
				KEY `type` (`type`),
				KEY `size_name` (`size_name`),
				KEY `mimetype` (`mimetype`),
				KEY `path` (`path`),
				KEY `date` (`date`),
				KEY `valid` (`valid`)
			) CHARACTER SET utf8 COLLATE utf8_general_ci COMMENT = \'Table created by Image Regenerate & Select Crop Adon for Uploads Inspector\'';
			dbDelta( $sql );
			update_option( $opt, (float) self::PLUGIN_VER );
		}
	}

	/**
	 * Init the adon main buttons.
	 *
	 * @return void
	 */
	public static function init_buttons() {
		do_action(
			'sirsc/iterator/setup_buttons',
			'sirsc-ui',
			[
				'assess'  => [
					'icon'       => '<span class="dashicons dashicons-image-rotate"></span>',
					'text'       => __( 'assess', 'sirsc' ),
					'callback'   => 'sirscUiStartAssess()',
					'attributes' => [ 'data-path' => '*' ],
					'buttons'    => [ 'stop', 'resume', 'cancel' ],
					'class'      => 'auto f-right',
				],

				'refresh' => [
					'icon'     => '<span class="dashicons dashicons-image-rotate"></span>',
					'text'     => __( 'refresh', 'sirsc' ),
					'callback' => 'sirscUiStartRefresh()',
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
	public static function adon_admin_menu() {
		add_submenu_page(
			'image-regenerate-select-crop-settings',
			__( 'Uploads Inspector', 'sirsc' ),
			'<span class="dashicons dashicons-admin-plugins sirsc-mini"></span> ' . __( 'Uploads Inspector', 'sirsc' ),
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
		SIRSC_Adons::check_adon_valid( self::ADON_SLUG );
		$desc = SIRSC_Adons::get_adon_details( self::ADON_SLUG, 'description' );
		?>
		<div class="wrap sirsc-settings-wrap sirsc-feature">
			<?php \SIRSC\Admin\show_plugin_top_info(); ?>
			<?php \SIRSC\Admin\maybe_all_features_tab(); ?>
			<div class="rows bg-secondary no-top">
				<div class="min-height-130">
					<img src="<?php echo esc_url( SIRSC_PLUGIN_URL . 'assets/images/adon-uploads-inspector-image.png' ); ?>" loading="lazy" class="negative-margins has-left">
					<h2>
						<span class="dashicons dashicons-admin-plugins"></span>
						<?php esc_html_e( 'Uploads Inspector', 'sirsc' ); ?>
					</h2>
					<?php echo wp_kses_post( $desc ); ?>
				</div>
			</div>

			<div class="rows bg-secondary has-gaps breakable">
				<div class="span4">
					<div id="sirsc-summary-wrap" class="sirsc-feature sirsc-target">
						<?php self::display_summary(); ?>
					</div>
				</div>
				<div class="span4">
					<div>
						<?php do_action( 'sirsc_folder_assess_images_button', '*' ); ?>
					</div>
				</div>
				<div class="span4">
					<div>
						<?php do_action( 'sirsc_folder_refresh_button' ); ?>
					</div>
				</div>
				<div class="span12">
					<div id="sirsc-filesinfo-wrap" class="sirsc-feature"><?php self::display_filesinfo(); ?></div>
				</div>
			</div>

			<div id="sirsc-listing-wrap" class="sirsc-feature sirsc-target"></div>
		</div>
		<?php
	}

	/**
	 * Show an images assess trigger button markup.
	 *
	 * @param  string $path Path of a folder.
	 * @return void
	 */
	public static function folder_assess_images_button( $path ) { //phpcs:ignore
		?>
		<h2><?php esc_html_e( 'Assess Uploads', 'sirsc' ); ?></h2>
		<p>
			<?php
			//phpcs:disable
			/*
			if ( ! empty( \SIRSC::$use_cron ) ) {
				?>
				<button type="button"
					class="button sirsc-iterator-wrap bg-neutral-dark sirsc-button-icon tiny f-right"
					name="sirsc-assess-submit"
					id="sirsc-assess-button-cron"
					value="submit"
					title="<?php esc_attr_e( 'Cron task', 'sirsc' ); ?>"
					onclick="sirscUiStartAssessCron()">
					<span class="dashicons dashicons-admin-generic"></span>
				</button>
				<?php
			} else {
				\SIRSC\Iterator\button_display( 'sirsc-ui-assess' );
			}
			*/
			//phpcs:enable
			\SIRSC\Iterator\button_display( 'sirsc-ui-assess' );
			?>
			<?php esc_html_e( 'Click to assess the files from uploads folder & refresh the info', 'sirsc' ); ?>.
			<?php esc_html_e( 'This option will initiate the assessment of the uploads structure and contents, and will collect information that can be checked later.', 'sirsc' ); ?>
		</p>
		<?php
	}

	/**
	 * Show an refresh trigger button markup.
	 *
	 * @return void
	 */
	public static function folder_refresh_button() {
		?>
		<h2><?php esc_html_e( 'Refresh Summary', 'sirsc' ); ?></h2>
		<p>
			<?php \SIRSC\Iterator\button_display( 'sirsc-ui-refresh' ); ?>
			<?php esc_html_e( 'Click to refresh summary. This will refresh the totals and counts if something was updated in the meanwhile.', 'sirsc' ); ?>
			<?php esc_html_e( 'This option will also stop the assessment, if that is currently in progress.', 'sirsc' ); ?>
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
		$info = ( empty( $info ) ) ? get_transient( 'sirsc_adon_uploads_folder_summary' ) : $info;
		?>
		<h2>
			<span class="dashicons dashicons-info-outline"></span>
			<?php esc_html_e( 'Folder Summary', 'sirsc' ); ?>
		</h2>

		<?php
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
						<td><b><?php echo esc_html( \SIRSC\Helper\human_filesize( $root['totals']['files_size'] ) ); ?></b>
							(<?php echo (int) $root['totals']['files_size']; ?> <?php esc_html_e( 'bytes', 'sirsc' ); ?>)</td>
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
		if ( 'sirsc_adon_ui_display_summary' === $act ) {
			wp_die();
			die();
		}
	}

	/**
	 * Display files info.
	 *
	 * @return void
	 */
	public static function display_filesinfo() {
		?>
		<?php do_action( 'sirsc_folder_assess_images_stats' ); ?>
		<?php
		$act = filter_input( INPUT_GET, 'action', FILTER_DEFAULT );
		if ( 'sirsc_adon_ui_display_filesinfo' === $act ) {
			wp_die();
			die();
		}
	}

	/**
	 * Reset counters.
	 *
	 * @retur void
	 */
	public static function reset_assess_counters() { //phpcs:ignore
		self::cleanup_not_found();
		update_option( self::PLUGIN_TABLE . '_last_proc', current_time( 'timestamp' ) ); //phpcs:ignore
		update_option( self::PLUGIN_TABLE . '_proc_dir', 0 );
		update_option( self::PLUGIN_TABLE . '_proc_item', '' );
		update_option( self::PLUGIN_TABLE . '_proc_time', 0 );
	}

	/**
	 * Start over.
	 *
	 * @retur void
	 */
	public static function start_over() {
		$upls = wp_upload_dir();
		$base = trailingslashit( $upls['basedir'] );
		$trid = 'sirsc_adon_uploads_folder_summary';
		$info = \SIRSC\Helper\get_folders_list( $base );
		set_transient( $trid, $info, 1 * HOUR_IN_SECONDS );
		update_option( 'sirsc_adon_uploads_files_count', $info[0]['totals']['files_count'] );
	}

	/**
	 * Execute the summary and info refresh.
	 *
	 * @return void
	 */
	public static function execute_refresh() {
		self::start_over();
		self::reset_assess_counters();

		echo \SIRSC\Helper\document_ready_js( //phpcs:ignore
			\SIRSC\Iterator\button_callback( 'sirsc-ui-assess', 'stop' )
			. ' ' . \SIRSC\Iterator\button_callback( 'sirsc-ui-assess', 'reset' )
			. ' ' . \SIRSC\Iterator\button_callback( 'sirsc-ui-refresh', 'reset' )
			. ' sirscUiFinishUp();' ); //phpcs:ignore

		wp_die();
		die();
	}

	/**
	 * Execute files assessment.
	 *
	 * @return void
	 */
	public static function execute_assess() {
		//phpcs:disable
		/*
		if ( ! empty( \SIRSC::$use_cron ) ) {
			if ( ! defined( 'DOING_CRON' ) ) {
				$total = self::compute_remaining_to_process();
				\SIRSC\Cron\assess_task( 'sirsc_adon_ui_execute_assess' );
				wp_die();
				die();
			}
		}
		*/
		//phpcs:enable

		$iterator = filter_input( INPUT_GET, 'iterator', FILTER_DEFAULT );
		if ( 'start' === $iterator ) {
			self::reset_assess_counters();
		}

		$last_item = get_option( self::PLUGIN_TABLE . '_proc_item', '' );
		$dir_id    = get_option( self::PLUGIN_TABLE . '_proc_dir', 0 );
		$upls      = wp_upload_dir();
		$base      = trailingslashit( $upls['basedir'] );

		if ( empty( $dir_id ) ) {
			$trid = 'sirsc_adon_uploads_folder_summary';
			$info = get_transient( $trid );
			if ( ! empty( $info ) ) {
				foreach ( $info as $k => $folder ) {
					if ( $k > 0 ) {
						$p = str_replace( $base, '', $folder['path'] );
						self::record_item( 'folder', $p, $folder['totals']['files_size'], $folder['totals']['files_count'] );
					}
				}
			}
		}

		$time = get_option( self::PLUGIN_TABLE . '_proc_time', 0 );
		if ( empty( $time ) ) {
			update_option( self::PLUGIN_TABLE . '_proc_time', current_time( 'timestamp' ) ); //phpcs:ignore
		}

		$maybe_dir = self::get_assessed_folders( (int) $dir_id, true );
		if ( ! empty( $maybe_dir->path ) ) {
			?>

			<div class="rows four-columns no-top bg-trans">
				<h2 class="span2">
					<?php esc_html_e( 'Processing the request for', 'sirsc' ); ?>
					<b><?php echo esc_html( $maybe_dir->path ); ?></b>
				</h2>
				<div class="span2">
					<?php self::compute_progress_bar(); ?>
				</div>
			</div>

			<div class="rows two-columns no-shadow has-top mini-gaps bg-trans files-info-wrap small">
				<?php
				$dir       = $base . $maybe_dir->path;
				$last_path = $base . $last_item;
				$search    = true;
				$record    = ( empty( $last_item ) ) ? true : false;
				$count     = 0;
				$all       = 0;
				foreach ( glob( rtrim( $dir, '/' ) . '/*', GLOB_NOSORT ) as $each ) { // | GLOB_NOSORT
					if ( is_file( $each ) ) {
						if ( true === $search ) {
							if ( $each === $last_path ) {
								$record = true;
								$search = false; // This was found, rely only on the counts.
							}
						}
						if ( true === $record ) {
							if ( $count < self::PLUGIN_BATCH_SIZE ) {
								$p = str_replace( $base, '', $each );
								self::record_item( 'file', $p, filesize( $each ) );
								echo wp_kses_post( '<div class="file-info"><b>▪</b> ' . esc_html( $p ) . '</div>' );

								++ $count;
							} else {
								break 1;
							}
						}

						++ $all;
					}
				}
				?>
			</div>

			<?php
			if ( $count <= 1 && ! empty( $record ) ) {
				// This means that maybe the folder was all processed.
				update_option( self::PLUGIN_TABLE . '_proc_dir', (int) $maybe_dir->id );
				update_option( self::PLUGIN_TABLE . '_proc_item', '' );
			}
		}

		if ( ! empty( $maybe_dir ) ) {
			echo \SIRSC\Helper\document_ready_js( \SIRSC\Iterator\button_callback( 'sirsc-ui-assess', 'continue' ), true ); //phpcs:ignore
		} else {
			echo \SIRSC\Helper\document_ready_js( \SIRSC\Iterator\button_callback( 'sirsc-ui-assess', 'reset' ) . ' sirscUiFinishUp();' ); //phpcs:ignore
		}

		wp_die();
		die();
	}

	/**
	 * Stats load list page.
	 *
	 * @return void
	 */
	public static function display_listing() {
		$page  = filter_input( INPUT_GET, 'page', FILTER_VALIDATE_INT );
		$page  = ( empty( $page ) ) ? 1 : abs( $page );
		$max   = filter_input( INPUT_GET, 'maxpage', FILTER_VALIDATE_INT );
		$size  = urlencode( filter_input( INPUT_GET, 'sizename', FILTER_DEFAULT ) ); //phpcs:ignore
		$mime  = urlencode( filter_input( INPUT_GET, 'mimetype', FILTER_DEFAULT ) ); //phpcs:ignore
		$valid = filter_input( INPUT_GET, 'valid', FILTER_VALIDATE_INT );
		$aid   = filter_input( INPUT_GET, 'aid', FILTER_DEFAULT );
		$title = filter_input( INPUT_GET, 'title', FILTER_DEFAULT );
		$args  = [
			'base'               => '%_%',
			'format'             => '?page=%#%',
			'total'              => $max,
			'current'            => $page,
			'show_all'           => false,
			'end_size'           => 1,
			'mid_size'           => 2,
			'prev_next'          => false,
			'prev_text'          => __( '&laquo;' ),
			'next_text'          => __( '&raquo;' ),
			'before_page_number' => '<span class="page-item button sirsc-listing-wrap-item" data-parentaid="' . $aid . '">',
			'after_page_number'  => '</span>',
			'add_args'           => false,
		];

		$pagination = '<div class="pagination">' . paginate_links( $args ) . '</div>';
		$pagination = preg_replace( '/\s+/', ' ', $pagination );
		$pagination = str_replace( '<span aria-current=\'page\' class=\'page-numbers current\'><span class="page-item button ', '<span aria-current="page" class="page-numbers current"><span class="page-item button button-primary ', $pagination );
		$pagination = str_replace( '<span aria-current="page" class="page-numbers current"><span class="page-item button ', '<span aria-current="page" class="page-numbers current"><span class="page-item button button-primary ', $pagination );
		$pagination = strip_tags( $pagination, '<span>' );
		?>

		<br>
		<div class="rows bg-trans no-gaps no-shadow no-top no-padd pags">
			<div class="span4"><b><?php echo esc_html( $title ); ?></b></div>
			<div class="span4">
				<?php
				echo wp_kses_post(
					sprintf(
						// Translators: %1$s - current page, %2$s - total pages.
						__( 'Page %1$s of %2$s', 'sirsc' ),
						'<b>' . $page . '</b>',
						'<b>' . $max . '</b>'
					)
				);
				?>
			</div>
			<div class="span4 a-right"><?php echo wp_kses_post( $pagination ); ?></div>
		</div>

		<?php
		global $wpdb;
		$perpag = get_option( 'posts_per_page' );
		$perpag = ( empty( $perpag ) ) ? 10 : abs( $perpag );
		$offset = ( $page - 1 ) * $perpag;
		$args   = [];
		$tquery = ' SELECT * FROM ' . self::PLUGIN_TABLE . ' WHERE type = %s ';
		$args[] = 'file';
		if ( ! empty( $valid ) ) {
			$tquery .= ' and valid = %d ';
			$args[]  = 1;
		}
		if ( ! empty( $size ) ) {
			$tquery .= ' and size_name = %s ';
			$args[]  = ( 'na' !== $size ) ? $size : '';
			$tquery .= ' and mimetype like %s ';
			$args[]  = 'image/%';
		} elseif ( ! empty( $mime ) ) {
			$tquery .= ' and mimetype like %s ';
			$args[]  = ( 'na' !== $mime ) ? '%/' . $mime : '';
		}
		$tquery .= ' order by id limit %d,%d ';
		$args[]  = $offset;
		$args[]  = $perpag;
		$query   = $wpdb->prepare( $tquery, $args ); // phpcs:ignore
		$items   = $wpdb->get_results( $query ); // phpcs:ignore
		if ( ! empty( $items ) ) {
			$upls = wp_upload_dir();
			$base = trailingslashit( $upls['baseurl'] );
			?>

			<div class="rows settings-rows bg-secondary dense small-pad no-shadow no-gaps heading">
				<div class="span1"></div>
				<div class="span10"><h3 class="heading"><?php esc_html_e( 'File', 'sirsc' ); ?></h3></div>
				<div class="span2"><h3 class="heading"><?php esc_html_e( 'MIME Type', 'sirsc' ); ?></h3></div>
				<div class="span2 a-right"><h3 class="heading"><?php esc_html_e( 'File size', 'sirsc' ); ?></h3></div>
				<div class="span3 a-right"><h3 class="heading"><?php esc_html_e( 'Size', 'sirsc' ); ?></h3></div>
				<div class="span2"><h3 class="heading"><?php esc_html_e( 'Attachment', 'sirsc' ); ?></h3></div>
			</div>

			<?php
			foreach ( $items as $item ) {
				$url      = $base . $item->path;
				$sizename = ( empty( $item->size_name ) ) ? '~' . __( 'unknown', 'sirsc' ) . '~' : $item->size_name;
				$mimetype = ( empty( $item->mimetype ) ) ? '~' . __( 'unknown', 'sirsc' ) . '~' : $item->mimetype;
				?>
				<div class="rows bg-alternate dense small-pad no-top no-shadow no-gaps" style="margin-top: -5px;">
					<div class="span1 first"
						data-title="<?php esc_attr_e( 'Counter', 'sirsc' ); ?>"><?php echo ++ $offset; // phpcs:ignore ?></div>
					<div class="span10"
						data-title="<?php esc_attr_e( 'File', 'sirsc' ); ?>">
						<div class="thumb">
							<?php if ( substr_count( $item->mimetype, 'image/' ) ) : ?>
								<img src="<?php echo esc_url( $url ); ?>" loading="lazy">
							<?php endif; ?>
						</div>
						<a href="<?php echo esc_url( $url ); ?>" target="_blank" class="button sirsc-button-icon tiny">
							<div class="dashicons dashicons-admin-links"></div>
						</a>
						<?php echo esc_html( $item->path ); ?>
						<?php if ( ! empty( $item->in_option ) ) : ?>
							<div class="sirsc-small-font"><?php esc_html_e( 'In option', 'sirsc' ); ?> <?php echo esc_html( $item->in_option ); ?></div>
						<?php endif; ?>
					</div>

					<div class="span2"
						data-title="<?php esc_attr_e( 'MIME Type', 'sirsc' ); ?>"><?php echo esc_html( $mimetype ); ?></div>
					<div class="span2 a-right"
						data-title="<?php esc_attr_e( 'File Size', 'sirsc' ); ?>">
						<b><?php echo esc_html( \SIRSC\Helper\human_filesize( $item->filesize ) ); ?></b>
						<div class="sirsc-small-font">
							(<?php echo esc_html( $item->filesize ); ?>
							<?php esc_html_e( 'bytes', 'sirsc' ); ?>)
						</div>
					</div>
					<div class="span3 a-right"
						data-title="<?php esc_attr_e( 'Size', 'sirsc' ); ?>">
						<?php
						if ( ! substr_count( $item->mimetype, 'image/' ) ) {
							esc_html_e( 'N/A', 'sirsc' );
						} else {
							if ( ! empty( $item->size_width ) ) {
								?>
								<b><?php echo esc_html( $item->size_width ); ?></b><span class="sirsc-small-font">x</span><b><?php echo esc_html( $item->size_height ); ?></b><span class="sirsc-small-font">px</span>
								<div class="sirsc-small-font">(<?php echo esc_html( $sizename ); ?>)</div>
								<?php
							} else {
								echo esc_html( $sizename );
							}
						}
						?>
					</div>
					<div class="span2 last"
						data-title="<?php esc_attr_e( 'Attachment', 'sirsc' ); ?>">
						<?php if ( ! empty( $item->attachment_id ) ) : ?>
							<a href="<?php echo esc_url( admin_url( 'post.php?post=' . (int) $item->attachment_id . '&action=edit' ) ); ?>" target="_blank" class="button sirsc-button-icon tiny">
								<div class="dashicons dashicons-admin-links"></div>
							</a>
							<?php echo (int) $item->attachment_id; ?>
						<?php else : ?>
							<div class="sirsc-small-font">~<?php esc_html_e( 'unknown', 'sirsc' ); ?>~</div>
						<?php endif; ?>
					</div>
				</div>
				<?php
			}
		}

		wp_die();
		die();
	}

	/**
	 * Show an images assess profile stats.
	 *
	 * @return void
	 */
	public static function folder_assess_images_stats() {
		global $wpdb;
		$perpag = get_option( 'posts_per_page' );
		$perpag = ( empty( $perpag ) ) ? 10 : abs( $perpag );

		$last_proc = get_option( self::PLUGIN_TABLE . '_last_proc', 0 );
		if ( empty( $last_proc ) ) {
			return;
		}
		?>
		<div class="rows two-columns bg-trans no-shadow">
			<h2><?php esc_html_e( 'Files Info', 'sirsc' ); ?></h2>
			<div class="a-right">
				<?php
				echo wp_kses_post(
					sprintf(
						// Translators: %1$s - current page, %2$s - total pages.
						__( 'The most recent files assessment was executed on %1$s.', 'sirsc' ),
						'<b>' . date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_proc, true ) . '</b>'
					)
				);
				?>
			</div>
		</div>
		<p><?php esc_html_e( 'Click the files counts below to open the list of assessed items.', 'sirsc' ); ?></p>
		<div class="rows three-columns bg-trans no-shadow">
			<?php
			$query = $wpdb->prepare( ' SELECT mimetype, COUNT(id) as total_files FROM ' . self::PLUGIN_TABLE . ' WHERE type = %s GROUP BY mimetype ', 'file' ); //phpcs:ignore
			$items = $wpdb->get_results( $query ); //phpcs:ignore
			if ( ! empty( $items ) ) {
				?>
				<div>
					<h3 class="heading"><?php esc_html_e( 'MIME Type', 'sirsc' ); ?></h3>
					<div class="files-info-wrap small">
						<?php foreach ( $items as $item ) : ?>
							<?php
							$max_page = ceil( (int) $item->total_files / $perpag );
							$mimetype = ( empty( $item->mimetype ) ) ? '~' . __( 'unknown', 'sirsc' ) . '~' : $item->mimetype;
							$v_mtype  = ( empty( $item->mimetype ) ) ? 'na' : ltrim( strstr( $item->mimetype, '/', false ), '/' );
							?>
							<div class="file-info">
								<b>▪</b>
								<?php if ( empty( $item->mimetype ) ) : ?>
									<em>~<?php esc_html_e( 'unknown', 'sirsc' ); ?>~</em>
								<?php else : ?>
									<span><?php echo esc_html( $item->mimetype ); ?></span>
								<?php endif; ?>
								<a id="js-sirsc-adon-improf-list-mime-<?php echo esc_attr( $v_mtype . '-0' ); ?>"
									class="sirsc-listing-wrap-item"
									data-page="1"
									data-maxpage="<?php echo (int) $max_page; ?>"
									data-sizename=""
									data-mimetype="<?php echo esc_attr( $v_mtype ); ?>"
									data-valid="0"
									data-title="<?php echo esc_attr( __( 'MIME Type', 'sirsc' ) . ': ' . $mimetype ); ?>"
									>(<?php echo (int) $item->total_files; ?> <?php esc_html_e( 'files', 'sirsc' ); ?>)</a>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
				<?php
			}

			$query = $wpdb->prepare( ' SELECT size_name, COUNT(id) as total_files FROM ' . self::PLUGIN_TABLE . ' WHERE type = %s and mimetype like %s  GROUP BY size_name', 'file', 'image/%' ); //phpcs:ignore
			$items = $wpdb->get_results( $query ); //phpcs:ignore
			if ( ! empty( $items ) ) {
				?>
				<div>
					<h3 class="heading"><?php esc_html_e( 'Images Sizes', 'sirsc' ); ?></h3>
					<div class="files-info-wrap small">
						<?php foreach ( $items as $item ) : ?>
							<?php
							$max_page  = ceil( (int) $item->total_files / $perpag );
							$size_name = ( empty( $item->size_name ) ) ? '~' . __( 'unknown', 'sirsc' ) . '~' : $item->size_name;
							$v_sname   = ( empty( $item->size_name ) ) ? 'na' : $item->size_name;
							?>
							<div class="file-info">
								<b>▪</b>
								<?php if ( empty( $item->size_name ) ) : ?>
									<em>~<?php esc_html_e( 'unknown', 'sirsc' ); ?>~</em>
								<?php else : ?>
									<span><?php echo esc_html( $item->size_name ); ?></span>
								<?php endif; ?>
								<a id="js-sirsc-adon-improf-list-size-<?php echo esc_attr( $v_sname . '-0' ); ?>"
									class="sirsc-listing-wrap-item"
									data-page="1"
									data-maxpage="<?php echo (int) $max_page; ?>"
									data-sizename="<?php echo esc_attr( $v_sname ); ?>"
									data-mimetype=""
									data-valid="0"
									data-title="<?php echo esc_attr( __( 'Images Sizes', 'sirsc' ) . ': ' . $size_name ); ?>"
									>(<?php echo (int) $item->total_files; ?> <?php esc_html_e( 'files', 'sirsc' ); ?>)</a>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
				<?php
			}

			$query = $wpdb->prepare( ' SELECT size_name, COUNT(id) as total_files FROM ' . self::PLUGIN_TABLE . ' WHERE type = %s AND valid = 1 and mimetype like %s  GROUP BY size_name', 'file', 'image/%' ); //phpcs:ignore
			$items = $wpdb->get_results( $query ); //phpcs:ignore
			if ( ! empty( $items ) ) {
				?>
				<div>
					<h3 class="heading"><?php esc_html_e( 'Valid Images', 'sirsc' ); ?></h3>
					<div class="files-info-wrap small">
						<?php foreach ( $items as $item ) : ?>
							<?php
							$max_page  = ceil( (int) $item->total_files / $perpag );
							$size_name = ( empty( $item->size_name ) ) ? '~' . __( 'unknown', 'sirsc' ) . '~' : $item->size_name;
							$v_sname   = ( empty( $item->size_name ) ) ? 'na' : $item->size_name;
							?>
							<div class="file-info">
								<b>▪</b>
								<?php if ( empty( $item->size_name ) ) : ?>
									<em>~<?php esc_html_e( 'unknown', 'sirsc' ); ?>~</em>
								<?php else : ?>
									<span><?php echo esc_html( $item->size_name ); ?></span>
								<?php endif; ?>
								<a id="js-sirsc-adon-improf-list-size-<?php echo esc_attr( $v_sname . '-1' ); ?>"
									class="sirsc-listing-wrap-item"
									data-page="1"
									data-maxpage="<?php echo (int) $max_page; ?>"
									data-sizename="<?php echo esc_attr( $v_sname ); ?>"
									data-mimetype=""
									data-valid="0"
									data-title="<?php echo esc_attr( __( 'Valid Images', 'sirsc' ) . ': ' . $size_name ); ?>"
									>(<?php echo (int) $item->total_files; ?> <?php esc_html_e( 'files', 'sirsc' ); ?>)</a>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
				<?php
			}
			?>
		</div>

		<?php
	}

	/**
	 * Get the size of the directory.
	 *
	 * @param string  $type        Item type (folder|file).
	 * @param string  $path        The item path.
	 * @param integer $size        The item size.
	 * @param integer $count_files The items count.
	 * @return void
	 */
	public static function record_item( $type, $path, $size, $count_files = 0 ) { //phpcs:ignore
		global $wpdb;

		$attachment_id = 0;
		$mimetype      = '';
		$size_name     = '';
		$size_width    = 0;
		$size_height   = 0;
		$valid         = 0;
		$in_option     = '';

		if ( 'file' === $type ) {
			update_option( self::PLUGIN_TABLE . '_proc_item', $path );

			$original  = $path;
			$size_file = basename( $original );
			$tmp_query = $wpdb->prepare(
				' SELECT a.ID, group_concat( concat( am.meta_key, \'[#$#]\', am.meta_value ) separator \'[#@#]\' ) as str_meta FROM ' . $wpdb->posts . ' as a
				LEFT JOIN ' . $wpdb->postmeta . ' as am ON(am.post_id = a.ID)
				WHERE (am.meta_key like %s OR am.meta_key like %s ) AND ( am.meta_value like %s OR am.meta_value like %s )
				GROUP BY a.id
				ORDER BY a.ID LIMIT 0,1',
				'_wp_attachment_metadata',
				'_wp_attached_file',
				'%' . $original . '%',
				'%' . $size_file . '%'
			);
			$row = $wpdb->get_row( $tmp_query ); //phpcs:ignore
			if ( ! empty( $row ) ) {
				$attachment_id = $row->ID;
				if ( ! empty( $row->str_meta ) ) {
					$meta = '';
					if ( substr_count( $row->str_meta, '_wp_attachment_metadata' ) ) {
						// Potential image.
						$p = explode( '[#@#]', $row->str_meta );
						if ( ! empty( $p[0] ) && substr_count( $p[0], '_wp_attachment_metadata' ) ) {
							$meta = $p[0];
						} elseif ( ! empty( $p[1] ) && substr_count( $p[1], '_wp_attachment_metadata' ) ) {
							$meta = $p[1];
						}

						if ( ! empty( $meta ) ) {
							$meta = trim( str_replace( '_wp_attachment_metadata[#$#]', '', $meta ) );
							$meta = maybe_unserialize( trim( $meta ) );
							if ( ! is_array( $meta ) ) {
								// Fallback to the wp function.
								$meta = wp_get_attachment_metadata( $attachment_id );
							}
						}
					}

					if ( ! empty( $meta ) && is_array( $meta ) ) {
						$mt       = wp_check_filetype( $size_file );
						$mimetype = $mt['type'];
						if ( ! empty( $meta['file'] ) && $original === $meta['file'] ) {
							$size_name   = 'full';
							$size_width  = $meta['width'];
							$size_height = $meta['height'];
							$maybe_type  = wp_check_filetype( $meta['file'] );
							$mimetype    = ( ! empty( $maybe_type['type'] ) ) ? $maybe_type['type'] : $mimetype;
							$valid       = 1;
						} elseif ( ! empty( $meta['sizes'] ) ) {
							foreach ( $meta['sizes'] as $key => $value ) {
								if ( $size_file === $value['file'] ) {
									$size_name   = $key;
									$size_width  = $value['width'];
									$size_height = $value['height'];
									$mimetype    = $value['mime-type'];
									$valid       = 1;
									break;
								}
							}
						}
					} else {
						$mt = wp_check_filetype( $path );

						$mimetype = $mt['type'];
					}
				}
			} else {
				$mt = wp_check_filetype( $path );

				$mimetype = $mt['type'];
			}

			$in_option  = '';
			$tmp_query2 = $wpdb->prepare(
				' SELECT group_concat(option_name separator \', \') FROM ' . $wpdb->options . '
				WHERE option_value like %s AND option_name not like %s
				GROUP BY option_name
				ORDER BY option_name LIMIT 0,1',
				'%' . $path . '%',
				'%sirsc_adon%'
			);

			$row2 = $wpdb->get_var( $tmp_query2 ); //phpcs:ignore
			if ( ! empty( $row2 ) ) {
				$in_option = $row2;
			}
		}

		$array_data = [
			'date'          => current_time( 'timestamp' ), //phpcs:ignore
			'type'          => $type,
			'path'          => $path,
			'filesize'      => $size,
			'attachment_id' => $attachment_id,
			'mimetype'      => $mimetype,
			'size_name'     => $size_name,
			'size_width'    => $size_width,
			'size_height'   => $size_height,
			'valid'         => $valid,
			'count_files'   => $count_files,
			'in_option'     => $in_option,
		];
		$array_type = [ '%d', '%s', '%s', '%d', '%d', '%s', '%s', '%d', '%d', '%d', '%d', '%s' ];
		$tmp_query  = $wpdb->prepare(
			' SELECT id FROM ' . self::PLUGIN_TABLE . ' WHERE type = %s AND path = %s ORDER BY id LIMIT 0,1', //phpcs:ignore
			$type,
			$path
		); //phpcs:ignore

		$id = $wpdb->get_var( $tmp_query ); //phpcs:ignore
		if ( ! empty( $id ) ) {
			$wpdb->update( self::PLUGIN_TABLE, $array_data, array( 'id' => $id ), $array_type, array( '%d' ) ); //phpcs:ignore
		} else {
			$wpdb->insert( self::PLUGIN_TABLE, $array_data, $array_type ); //phpcs:ignore
		}
	}

	/**
	 * Get assessed folders.
	 *
	 * @param  integer $id     Folder id.
	 * @param  boolean $use_id True to use the id for compare.
	 * @return array|object
	 */
	public static function get_assessed_folders( $id = 0, $use_id = false ) { //phpcs:ignore
		global $wpdb;
		$folders = [];
		if ( true === $use_id ) {
			$query = $wpdb->prepare( ' SELECT * FROM ' . self::PLUGIN_TABLE . ' WHERE type = %s and id > %d ORDER BY id ASC LIMIT 0,1', 'folder', $id ); //phpcs:ignore
			$rows = $wpdb->get_row( $query ); //phpcs:ignore
		} else {
			$query = $wpdb->prepare( ' SELECT * FROM ' . self::PLUGIN_TABLE . ' WHERE type = %s ORDER BY id ASC ', 'folder' ); //phpcs:ignore
			$rows  = $wpdb->get_results( $query ); //phpcs:ignore
		}

		if ( ! empty( $rows ) ) {
			$folders = $rows;
		}
		return $folders;
	}

	/**
	 * Compute progress bar.
	 *
	 * @return void
	 */
	public static function compute_progress_bar() {
		global $wpdb;

		$total = get_option( 'sirsc_adon_uploads_files_count', 0 );
		$info  = get_transient( 'sirsc_adon_uploads_folder_summary' );
		if ( ! empty( $info[0]['totals']['folders_count'] ) ) {
			$total += $info[0]['totals']['folders_count'];
		}

		$time      = get_option( self::PLUGIN_TABLE . '_proc_time', 0 );
		$processed = $wpdb->get_var( $wpdb->prepare( ' SELECT COUNT(id) as total_files FROM ' . self::PLUGIN_TABLE . ' WHERE type = %s and date >= %d', 'file', $time ) ); // phpcs:ignore

		if ( $processed >= $total ) {
			update_option( 'sirsc_adon_uploads_files_count', $processed );
		}

		$text = esc_html(
			sprintf(
				// Translators: %1$d - count products, %2$d - total.
				__( 'There are %1$d items assessed out of %2$d.', 'sirsc' ),
				$processed,
				$total
			)
		);

		\SIRSC\Helper\progress_bar( $total, $processed, true, $text );
	}

	/**
	 * Compute number of items remaining.
	 *
	 * @return int
	 */
	public static function compute_remaining_to_process() : int {
		global $wpdb;

		$total = get_option( 'sirsc_adon_uploads_files_count', 0 );
		$info  = get_transient( 'sirsc_adon_uploads_folder_summary' );
		$time  = get_option( self::PLUGIN_TABLE . '_proc_time', 0 );
		if ( ! empty( $info[0]['totals']['folders_count'] ) ) {
			$total += $info[0]['totals']['folders_count'];
		}

		$processed = $wpdb->get_var( $wpdb->prepare( ' SELECT COUNT(id) as total_files FROM ' . self::PLUGIN_TABLE . ' WHERE type = %s and date >= %d', 'file', $time ) ); // phpcs:ignore

		$diff = (int) $total - (int) $processed;
		$diff = ( $diff <= 0 ) ? 0 : $diff;
		return $diff;
	}

	/**
	 * Cleanup not found.
	 *
	 * @return void
	 */
	public static function cleanup_not_found() {
		$time = get_option( self::PLUGIN_TABLE . '_proc_time', 0 );
		if ( ! empty( $time ) ) {
			global $wpdb;
			$wpdb->query( $wpdb->prepare( ' DELETE FROM ' . self::PLUGIN_TABLE . ' WHERE date < %d ', $time ) ); // phpcs:ignore
		}
	}
}

// Instantiate the class.
SIRSC_Adons_Uploads_Inspector::get_instance();
