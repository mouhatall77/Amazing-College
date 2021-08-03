<?php
/**
 * SIRSC admin functionality.
 *
 * @package sirsc
 */

declare( strict_types=1 );

namespace SIRSC\Admin;

// Hook up the custom menu.
add_action( 'admin_menu', __NAMESPACE__ . '\\admin_menu' );
add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\load_assets' );
add_action( 'admin_notices', __NAMESPACE__ . '\\admin_notices' );

// Intitialize Gutenberg filters.
add_action( 'init', __NAMESPACE__ . '\\sirsc_block_init' );

// Hook up the custom media settings.
add_action( 'admin_init', __NAMESPACE__ . '\\media_settings_override' );
add_action( 'updated_option', __NAMESPACE__ . '\\on_update_sirsc_override_size', 30, 3 );
add_action( 'add_meta_boxes', __NAMESPACE__ . '\\register_image_meta', 10, 3 );
add_filter( 'manage_media_columns', __NAMESPACE__ . '\\register_media_columns', 5 );
add_action( 'manage_media_custom_column', __NAMESPACE__ . '\\media_column_value', 5, 2 );
add_action( 'wp_enqueue_media', __NAMESPACE__ . '\\add_media_overrides' );

/**
 * Add media overrides.
 *
 * @return void
 */
function add_media_overrides() { //phpcs:ignore
	add_action( 'admin_footer-upload.php', __NAMESPACE__ . '\\override_media_templates' );
}

/**
 * Media overrides.
 *
 * @return void
 */
function override_media_templates() {
	// phpcs:disable
	?>
	<script type="text/html" id="tmpl-attachment_custom">
		<div class="attachment-preview js--select-attachment type-{{ data.type }} subtype-{{ data.subtype }} {{ data.orientation }}">
			<div class="thumbnail">
				<# if ( data.uploading ) { #>
					<div class="media-progress-bar"><div style="width: {{ data.percent }}%"></div></div>
				<# } else if ( 'image' === data.type && data.size && data.size.url ) { #>
					<div class="centered">
						<img src="{{ data.size.url }}" draggable="false" alt="" />
					</div>
				<# } else { #>
					<div class="centered">
						<# if ( data.image && data.image.src && data.image.src !== data.icon ) { #>
							<img src="{{ data.image.src }}" class="thumbnail" draggable="false" alt="" />
						<# } else if ( data.sizes && data.sizes.medium ) { #>
							<img src="{{ data.sizes.medium.url }}" class="thumbnail" draggable="false" alt="" />
						<# } else { #>
							<img src="{{ data.icon }}" class="icon" draggable="false" alt="" />
						<# } #>
					</div>
					<div class="filename">
						<div>{{ data.filename }}</div>
					</div>
				<# } #>
			</div>
			<# if ( data.buttons.close ) { #>
				<button type="button" class="button-link attachment-close media-modal-icon"><span class="screen-reader-text"><?php _e( 'Remove' ); ?></span></button>
			<# } #>
		</div>
		<# if ( data.buttons.check ) { #>
			<button type="button" class="check" tabindex="-1"><span class="media-modal-icon"></span><span class="screen-reader-text"><?php _e( 'Deselect' ); ?></span></button>
		<# } #>
		<#
		var maybeReadOnly = data.can.save || data.allowLocalEdits ? '' : 'readonly';
		if ( data.describe ) {
			if ( 'image' === data.type ) { #>
				<input type="text" value="{{ data.caption }}" class="describe" data-setting="caption"
					aria-label="<?php esc_attr_e( 'Caption' ); ?>"
					placeholder="<?php esc_attr_e( 'Caption&hellip;' ); ?>" {{ maybeReadOnly }} />
			<# } else { #>
				<input type="text" value="{{ data.title }}" class="describe" data-setting="title"
					<# if ( 'video' === data.type ) { #>
						aria-label="<?php esc_attr_e( 'Video title' ); ?>"
						placeholder="<?php esc_attr_e( 'Video title&hellip;' ); ?>"
					<# } else if ( 'audio' === data.type ) { #>
						aria-label="<?php esc_attr_e( 'Audio title' ); ?>"
						placeholder="<?php esc_attr_e( 'Audio title&hellip;' ); ?>"
					<# } else { #>
						aria-label="<?php esc_attr_e( 'Media title' ); ?>"
						placeholder="<?php esc_attr_e( 'Media title&hellip;' ); ?>"
					<# } #> {{ maybeReadOnly }} />
			<# }
		} #>

		<#
		if ( 'image' === data.type ) {
			#>
			<div id="sirsc-buttons-wrapper-{{ data.id }}" class="sirsc-feature as-target sirsc-buttons tiny">
				<div class="button-primary" onclick="sirscSingleDetails('{{ data.id }}')" title="{{ sirscSettings.button_options }}"><div class="dashicons dashicons-format-gallery"></div> {{ sirscSettings.button_details }}</div>
				<div class="button-primary" onclick="sirscSingleRegenerate('{{ data.id }}')" title="{{ sirscSettings.button_regenerate }}"><div class="dashicons dashicons-update"></div> {{ sirscSettings.button_regenerate }}</div>
				<div class="button-primary" onclick="sirscSingleCleanup('{{ data.id }}')" title="{{ sirscSettings.button_cleanup }}"><div class="dashicons dashicons-editor-removeformatting"></div> {{ sirscSettings.button_cleanup }}</div>
			</div>
			<#
		} #>
	</script>
	<script>
	document.addEventListener('DOMContentLoaded', function() {
		if( typeof wp.media.view.Attachment != 'undefined' ) {
			wp.media.view.Attachment.prototype.template = wp.media.template( 'attachment_custom' );
		}
	} );
	</script>
	<?php
	// phpcs:enable
}

/**
 * Return the sgv logo of the plugin.
 *
 * @return string
 */
function get_sirsc_logo() : string {
	$svg = '<svg xmlns="http://www.w3.org/2000/svg" xml:space="preserve" width="16px" height="16px" version="1.1" style="shape-rendering:geometricPrecision; text-rendering:geometricPrecision; image-rendering:optimizeQuality; fill-rule:evenodd; clip-rule:evenodd" viewBox="0 0 2541 2541" xmlns:xlink="http://www.w3.org/1999/xlink"><g id="Layer_x0020_1"><metadata id="CorelCorpID_0Corel-Layer"/><path fill="#AAAAAA" d="M173 0l1399 0c-42,139 -50,303 7,479l228 66c-13,-39 -25,-91 -33,-134l-4 -131c91,90 334,354 406,386 61,27 177,23 245,0l-92 -92c-413,-415 -543,-494 -532,-574l175 0 569 569 0 173c-372,172 -744,-159 -1241,-199 -624,-50 -855,427 -729,944l220 68c-3,-67 -22,-100 -22,-171 108,104 300,374 556,292l-573 -574 65 -151 771 773c-360,288 -1029,-307 -1588,-150l0 -1401c0,-95 78,-173 173,-173zm2067 0l128 0c95,0 173,78 173,173l0 131 -301 -304zm301 970l0 1398c0,95 -78,173 -173,173l-1401 0c42,-139 50,-303 -8,-479l-227 -66c12,39 24,91 32,135l5 131c-264,-262 -379,-478 -651,-387l92 93c413,415 542,493 531,573l-175 0 -566 -566 0 -177c371,-169 743,160 1239,200 623,50 855,-426 729,-944l-220 -67c2,66 21,99 22,170 -109,-104 -300,-374 -557,-291l573 574 -64 150 -772 -772c142,-114 417,-68 576,-25 208,55 365,142 574,180 145,26 287,45 441,-3zm-2243 1571l-125 0c-95,0 -173,-78 -173,-173l0 -127 298 300z"/></g></svg>';

	return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore
}

/**
 * Maybe the custom plugin icon.
 *
 * @param  boolean $return True to return.
 * @return void|string
 */
function show_plugin_icon( $return = false ) { // phpcs:ignore
	if ( true === $return ) {
		ob_start();
	}
	?>
	<img src="<?php echo esc_url( SIRSC_PLUGIN_URL . 'assets/images/icon.svg?v=' . SIRSC_ASSETS_VER ); ?>" class="sirsc-icon-svg" width="32" height="32" alt="<?php esc_attr_e( 'Image Regenerate & Select Crop', 'sirsc' ); ?>">
	<?php
	if ( true === $return ) {
		return ob_get_clean();
	}
}

/**
 * Add the new menu in tools section that allows to configure the image sizes restrictions.
 */
function admin_menu() {
	add_menu_page(
		__( 'Image Regenerate & Select Crop', 'sirsc' ),
		'<font>' . __( 'Image Regenerate & Select Crop', 'sirsc' ) . '</font>',
		'manage_options',
		'image-regenerate-select-crop-settings',
		__NAMESPACE__ . '\\image_regenerate_select_crop_settings',
		get_sirsc_logo(),
		70
	);
	add_submenu_page(
		'image-regenerate-select-crop-settings',
		__( 'Advanced Rules', 'sirsc' ),
		__( 'Advanced Rules', 'sirsc' ),
		'manage_options',
		'image-regenerate-select-crop-rules',
		__NAMESPACE__ . '\\sirsc_custom_rules_settings'
	);
	add_submenu_page(
		'image-regenerate-select-crop-settings',
		__( 'Media Settings', 'sirsc' ),
		__( 'Media Settings', 'sirsc' ),
		'manage_options',
		admin_url( 'options-media.php#opt_new_crop' )
	);
	add_submenu_page(
		'image-regenerate-select-crop-settings',
		__( 'Additional Sizes', 'sirsc' ),
		__( 'Additional Sizes', 'sirsc' ),
		'manage_options',
		admin_url( 'options-media.php#opt_new_sizes' )
	);
}

/**
 * Registers the Gutenberg custom block assets.
 */
function sirsc_block_init() {
	if ( ! function_exists( 'register_block_type' ) ) {
		// Gutenberg is not active.
		return;
	}

	$uri = $_SERVER['REQUEST_URI']; //phpcs:ignore

	if ( ! substr_count( $uri, 'post.php' ) && ! substr_count( $uri, 'upload.php' )
		&& ! substr_count( $uri, 'page=image-regenerate-select-crop-' )
		&& ! substr_count( $uri, 'page=sirsc-adon-' )
		&& ! substr_count( $uri, 'page=sirsc-debug' )
		&& ! substr_count( $uri, 'options-media.php' ) ) {

		// Fail-fast, the assets should not be loaded.
		return;
	}

	wp_register_script(
		'sirsc-block-editor',
		SIRSC_PLUGIN_URL . 'sirsc-block/block.js',
		[
			'wp-blocks',
			'wp-editor',
			'wp-i18n',
			'wp-element',
		],
		filemtime( SIRSC_PLUGIN_FOLDER . 'sirsc-block/block.js' ),
		true
	);

	register_block_type(
		'image-regenerate-select-crop/sirsc-block',
		[
			'editor_script' => 'sirsc-block-editor',
		]
	);
}

/**
 * Register custom settings for overriding the medium image.
 */
function media_settings_override() {
	// Add the custom section to media.
	add_settings_section(
		'sirsc_override_section',
		'<a name="opt_new_crop" id="opt_new_crop"></a>',
		__NAMESPACE__ . '\\sirsc_override_section_callback',
		'media'
	);

	// Add the custom field to the new section.
	add_settings_field(
		'sirsc_override_medium_size',
		__( 'Medium size crop', 'sirsc' ),
		__NAMESPACE__ . '\\sirsc_override_medium_size_callback',
		'media',
		'sirsc_override_section'
	);

	// Add the custom field to the new section.
	add_settings_field(
		'sirsc_override_medium_large_size',
		__( 'Medium large size crop', 'sirsc' ),
		__NAMESPACE__ . '\\sirsc_override_medium_large_size_callback',
		'media',
		'sirsc_override_section'
	);

	// Add the custom field to the new section.
	add_settings_field(
		'sirsc_override_large_size',
		__( 'Large size crop', 'sirsc' ),
		__NAMESPACE__ . '\\sirsc_override_large_size_callback',
		'media',
		'sirsc_override_section'
	);

	// Add the custom field to the new section.
	add_settings_field(
		'sirsc_admin_featured_size',
		__( 'Featured image size in meta box', 'sirsc' ),
		__NAMESPACE__ . '\\sirsc_override_admin_featured_size_callback',
		'media',
		'sirsc_override_section'
	);

	// Register the custom settings.
	register_setting( 'media', 'sirsc_override_medium_size' );
	register_setting( 'media', 'sirsc_override_medium_large_size' );
	register_setting( 'media', 'sirsc_override_large_size' );
	register_setting( 'media', 'sirsc_admin_featured_size' );

	// Add the custom section to media.
	add_settings_section(
		'sirsc_custom_sizes_section',
		'<a name="opt_new_sizes" id="opt_new_sizes"></a>',
		__NAMESPACE__ . '\\sirsc_custom_sizes_section_callback',
		'media'
	);

	// Add the custom field to the new section.
	add_settings_field(
		'sirsc_use_custom_image_sizes',
		__( 'Use Custom Image Sizes', 'sirsc' ),
		__NAMESPACE__ . '\\sirsc_use_custom_image_sizes_callback',
		'media',
		'sirsc_custom_sizes_section'
	);

	// Register the custom settings.
	register_setting( 'media', 'sirsc_use_custom_image_sizes' );
}

/**
 * Enqueue the css and javascript files
 */
function load_assets() {
	$uri = $_SERVER['REQUEST_URI']; //phpcs:ignore

	if ( ! substr_count( $uri, 'post.php' ) && ! substr_count( $uri, 'upload.php' )
		&& ! substr_count( $uri, 'page=image-regenerate-select-crop-' )
		&& ! substr_count( $uri, 'page=sirsc-adon-' )
		&& ! substr_count( $uri, 'page=sirsc-debug' )
		&& ! substr_count( $uri, 'options-media.php' ) ) {

		// Fail-fast, the assets should not be loaded.
		return;
	}

	if ( file_exists( SIRSC_PLUGIN_DIR . 'build/index.asset.php' ) ) {
			$dependencies = require_once SIRSC_PLUGIN_DIR . 'build/index.asset.php';
	} else {
		$dependencies = [
			'dependencies' => [],
			'version'      => filemtime( SIRSC_PLUGIN_DIR . 'build/index.js' ),
		];
	}

	if ( file_exists( SIRSC_PLUGIN_DIR . 'build/index.js' ) ) {
		$upls = wp_upload_dir();

		wp_register_script(
			SIRSC_PLUGIN_SLUG,
			SIRSC_PLUGIN_URL . 'build/index.js',
			$dependencies['dependencies'],
			$dependencies['version'],
			true
		);
		wp_localize_script(
			SIRSC_PLUGIN_SLUG,
			str_replace( '-', '', SIRSC_PLUGIN_SLUG ) . 'Settings',
			[
				'ajaxUrl'                => admin_url( 'admin-ajax.php' ),
				'confirm_cleanup'        => __( 'Cleanup all?', 'sirsc' ),
				'confirm_regenerate'     => __( 'Regenerate all?', 'sirsc' ),
				'time_warning'           => __( 'This operation might take a while, depending on how many images you have.', 'sirsc' ),
				'irreversible_operation' => __( 'The operation is irreversible!', 'sirsc' ),
				'resolution'             => __( 'Resolution', 'sirsc' ),
				'button_options'         => __( 'Details/Options', 'sirsc' ),
				'button_details'         => __( 'Image Details', 'sirsc' ),
				'button_regenerate'      => __( 'Regenerate', 'sirsc' ),
				'button_cleanup'         => __( 'Raw Cleanup', 'sirsc' ),
				'regenerate_log_title'   => __( 'Regenerate Log', 'sirsc' ),
				'cleanup_log_title'      => __( 'Cleanup Log', 'sirsc' ),
				'upload_root_path'       => trailingslashit( $upls['basedir'] ),
				'display_small_buttons'  => ( ! empty( \SIRSC::$settings['listing_tiny_buttons'] ) ) ? ' tiny' : '',
				'admin_featured_size'    => get_option( 'sirsc_admin_featured_size' ),
				'confirm_raw_cleanup'    => __( 'This action will remove all images generated for this attachment, except for the original file. Are you sure you want proceed?', 'sirsc' ),
				'delay'                  => \SIRSC::BULK_PROCESS_DELAY,
				'settting_url'           => admin_url( 'admin.php?page=image-regenerate-select-crop-settings' ),
			]
		);
		wp_enqueue_script( SIRSC_PLUGIN_SLUG );
	}

	if ( file_exists( SIRSC_PLUGIN_DIR . 'build/style.css' ) ) {
		wp_enqueue_style(
			SIRSC_PLUGIN_SLUG,
			SIRSC_PLUGIN_URL . 'build/style.css',
			[],
			filemtime( SIRSC_PLUGIN_DIR . 'build/style.css' ),
			false
		);
	}
}

/**
 * Admin notices.
 *
 * @return void
 */
function admin_notices() {
	$maybe_trans = get_transient( \SIRSC::PLUGIN_TRANSIENT );
	if ( ! empty( $maybe_trans ) ) {
		$slug   = md5( SIRSC_PLUGIN_SLUG );
		$title  = __( 'Image Regenerate & Select Crop', 'sirsc' );
		$donate = 'https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=JJA37EHZXWUTJ&item_name=Support for development and maintenance (' . rawurlencode( $title ) . ')';

		$maybe_pro = sprintf(
			// Translators: %1$s - extensions URL.
			__( '<a href="%1$s" rel="noreferrer">%2$s Premium extensions</a> are available for this plugin. ', 'sirsc' ),
			esc_url( admin_url( 'admin.php?page=image-regenerate-select-crop-extensions' ) ),
			'<span class="dashicons dashicons-admin-plugins"></span>'
		);

		$other_notice = sprintf(
			// Translators: %1$s - extensions URL.
			__( '%5$sCheck out my other <a href="%1$s" target="_blank" rel="noreferrer">%2$s free plugins</a> on WordPress.org and the <a href="%3$s" target="_blank" rel="noreferrer">%4$s other extensions</a> available!', 'sirsc' ),
			'https://profiles.wordpress.org/iulia-cazan/#content-plugins',
			'<span class="dashicons dashicons-heart"></span>',
			'https://iuliacazan.ro/shop/',
			'<span class="dashicons dashicons-star-filled"></span>',
			$maybe_pro
		);
		?>

		<div id="item-<?php echo esc_attr( $slug ); ?>" class="updated notice">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=image-regenerate-select-crop-settings' ) ); ?>" class="icon"><img src="<?php echo esc_url( SIRSC_PLUGIN_URL . 'assets/images/icon-128x128.gif' ); ?>"></a>
			<div class="content">
				<div>
					<h3>
						<?php
						echo wp_kses_post( sprintf(
							// Translators: %1$s - plugin name.
							__( '%1$s plugin was activated!', 'sirsc' ),
							'<b>' . $title . '</b>'
						) );
						?>
					</h3>
					<div class="notice-other-items"><div><?php echo wp_kses_post( $other_notice ); ?></div></div>
				</div>

				<div>
					<?php
					echo wp_kses_post( sprintf(
						// Translators: %1$s - donate URL, %2$s - rating, %3$s - thanks.
						__( 'This plugin is free to use, but not to operate. Please consider supporting my services by making a <a href="%1$s" target="_blank" rel="noreferrer">donation</a>. It would make me very happy if you would leave a %2$s rating. %3$s', 'sirsc' ),
						$donate,
						'<a href="' . \SIRSC::PLUGIN_SUPPORT_URL . 'reviews/?rate=5#new-post" class="rating" target="_blank" rel="noreferrer" title="' . esc_attr__( 'A huge thanks in advance!', 'sirsc' ) . '">★★★★★</a>',
						__( 'A huge thanks in advance!', 'sirsc' )
					) );
					?>
					<a class="notice-plugin-donate" href="<?php echo esc_url( $donate ); ?>" target="_blank"><img src="<?php echo esc_url( SIRSC_PLUGIN_URL . 'assets/images/buy-me-a-coffee.png?v=' . SIRSC_ASSETS_VER ); ?>" width="280"></a>
				</div>
			</div>
			<span class="dashicons dashicons-no" onclick="dismiss_notice_for_<?php echo esc_attr( $slug ); ?>()"></span>
		</div>
		<style>
			<?php
			$style = '#trans123super{--color-bg:rgba(144,202,233,0.2);--color-border:#90cae9;--color-border-left:rgb(144,202,233);align-items:stretch;display:inline-flex;flex-direction:row;flex-wrap:nowrap;flex:0;margin:0;margin-bottom:20px;padding:0;gap:20px;max-width:100%;overflow-x:hidden;width:100%;border-left-color:var(--color-border-left);background:var(--color-bg);border-color:var(--color-border);box-sizing:border-box;padding:0;border-left-width:20px;} #trans123super .dashicons-no{flex:0 0 32px;font-size:32px;cursor:pointer;} #trans123super .icon{position:relative;align-content:stretch;flex:0 0 128px;} #trans123super .icon img{position:absolute;object-fit:cover;object-position:center;height:100%;width:100%;} #trans123super .content{align-items:stretch;align-items:center;display:inline-flex;flex-direction:row;flex-wrap:nowrap;gap:0;max-width:100%;overflow-x:hidden;width:100%;} #trans123super .content .dashicons{color:var(--color-border);} #trans123super .content > *{color:#666; padding:20px;width:50%;}@media screen and (max-width:600px){ #trans123super{flex-wrap:wrap;} #trans123super .icon{flex:0 0 100%; display:none;} #trans123super .content{flex-wrap:wrap;} #trans123super .content > *{width:100%;}} #trans123super h3{margin:0 0 10px 0;color:#666} #trans123super h3 b{color:#000} #trans123super a{color:#000;text-decoration:none;} #trans123super .notice-plugin-donate{display:block;margin-top:10px;text-align:right;}';

			$style = str_replace( '#trans123super', '#item-' . esc_attr( $slug ), $style );
			echo $style; //phpcs:ignore
			?>
		</style>
		<script>function dismiss_notice_for_<?php echo esc_attr( $slug ); ?>() { document.getElementById( 'item-<?php echo esc_attr( $slug ); ?>' ).style='display:none'; fetch( '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>?action=plugin-deactivate-notice-<?php echo esc_attr( SIRSC_PLUGIN_SLUG ); ?>' ); }</script>
		<?php
	}

	$maybe_errors = assess_collected_errors();
	if ( ! empty( $maybe_errors ) ) {
		?>
		<div class="updated error is-dismissible">
			<p>
				<?php echo wp_kses_post( $maybe_errors ); ?>
			</p>
		</div>
		<?php
		delete_option( 'sirsc_monitor_errors' );
	}
}

/**
 * Maybe all features tab.
 *
 * @return void
 */
function maybe_all_features_tab() {
	$tab = filter_input( INPUT_GET, 'page', FILTER_DEFAULT );
	?>
	<div class="sirsc-tabbed-menu-buttons">
		<?php
		foreach ( \SIRSC::$menu_items as $item ) {
			$class = ( $item['slug'] === $tab ) ? 'button-primary on' : 'button-secondary';
			?>
			<a href="<?php echo esc_url( $item['url'] ); ?>" class="button <?php echo esc_attr( $class ); ?>" >
				<?php if ( ! empty( $item['icon'] ) ) : ?>
					<?php echo wp_kses_post( $item['icon'] ); ?>
				<?php endif; ?>
				<?php echo esc_html( $item['title'] ); ?>
			</a>
			<?php
		}
		?>
	</div>
	<?php
}

/**
 * Show info icon.
 *
 * @param  string $id Element id.
 * @return void
 */
function show_info_icon( $id ) { // phpcs:ignore
	?>
	<a class="dashicons dashicons-info" title="<?php esc_attr_e( 'Details', 'sirsc' ); ?>" data-sirsc-toggle="<?php echo esc_attr( $id ); ?>"></a>
	<?php
}

/**
 * Show info text.
 *
 * @param  string $id   Element id.
 * @param  string $text Element text.
 * @return void
 */
function show_info_text( $id, $text ) { // phpcs:ignore
	?>
	<div id="<?php echo esc_attr( $id ); ?>" class="sirsc_info_box" data-sirsc-toggle="<?php echo esc_attr( $id ); ?>">
		<div><?php echo wp_kses_post( $text ); ?></div>
	</div>

	<?php
}

/**
 * Show plugin top info
 *
 * @return void
 */
function show_plugin_top_info() {
	?>
	<h1 class="plugin-title">
		<span>
			<?php show_plugin_icon(); ?>
			<span class="h1"><?php esc_html_e( 'Image Regenerate & Select Crop', 'sirsc' ); ?></span>
		</span>
		<span><?php show_donate_text(); ?></span>
	</h1>

	<?php
	if ( false === \SIRSC::$is_configured ) {
		?>
		<div class="sirsc-warning">
			<?php esc_html_e( 'Image Regenerate & Select Crop Settings are not configured yet.', 'sirsc' ); ?>
		</div>
		<br>
		<?php
	}
}

/**
 * The setting is readonly.
 *
 * @param  string $slug Setting slug.
 * @return void
 */
function setting_is_readonly( $slug ) { //phpcs:ignore
	$cpt = filter_input( INPUT_GET, '_sirsc_post_types', FILTER_DEFAULT );
	if ( empty( $cpt ) ) {
		// Fail-fast.
		return;
	}

	$list = \SIRSC::common_settings();
	if ( in_array( $slug, $list['list'], true ) ) {
		echo ' readonly="readonly" disabled="disabled" ';
	}
}

/**
 * The setting has custom background.
 *
 * @return void
 */
function has_custom_color() { //phpcs:ignore
	$cpt = filter_input( INPUT_GET, '_sirsc_post_types', FILTER_DEFAULT );
	if ( empty( $cpt ) ) {
		// Fail-fast.
		return;
	}

	$color = \SIRSC\Helper\string2color( $cpt, 'hex', 0.55 );
	echo ' style="background-color: ' . esc_attr( $color ) . ' !important;"'; //phpcs:ignore
}

/**
 * Functionality to manage the image regenerate & select crop settings.
 */
function image_regenerate_select_crop_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		// Verify user capabilities in order to deny the access if the user does not have the capabilities.
		wp_die( esc_html__( 'Action not allowed.', 'sirsc' ) );
	}

	$allow_html = [
		'table' => [
			'class'       => [],
			'cellspacing' => [],
			'cellpadding' => [],
			'title'       => [],
		],
		'tbody' => [],
		'tr'    => [],
		'td'    => [ 'title' => [] ],
		'label' => [],
		'input' => [
			'type'              => [],
			'name'              => [],
			'id'                => [],
			'value'             => [],
			'checked'           => [],
			'onchange'          => [],
			'onclick'           => [],
			'data-sirsc-toggle' => [],
		],
	];

	$post_types        = \SIRSC\Helper\get_all_post_types_plugin();
	$_sirsc_post_types = filter_input( INPUT_GET, '_sirsc_post_types', FILTER_DEFAULT );
	\SIRSC::$settings  = \SIRSC::prepare_settings_list();
	$settings          = \SIRSC::$settings;

	$default_plugin_settings = $settings;
	if ( ! empty( $_sirsc_post_types ) ) {
		$settings = \SIRSC::prepare_settings_list( $_sirsc_post_types );
	}

	// Display the form and the next digests contents.
	?>
	<div class="wrap sirsc-settings-wrap sirsc-feature">
		<?php show_plugin_top_info(); ?>
		<?php maybe_all_features_tab(); ?>
		<div class="sirsc-tabbed-menu-content">
			<div class="rows bg-secondary no-top">
				<div>
					<?php esc_html_e( 'Please make sure you visit and update your settings here whenever you activate a new theme or plugins, so that the new image size registered, adjusted or removed to be reflected also here, and in this way to assure the optimal behavior for the features of this plugin.', 'sirsc' ); ?>
					<span class="dashicons dashicons-image-crop"></span> <a href="<?php echo esc_url( admin_url( 'options-media.php' ) ); ?>#opt_new_crop"><?php esc_html_e( 'Images Custom Settings', 'sirsc' ); ?></a>
					<span class="dashicons dashicons-format-gallery"></span> <a href="<?php echo esc_url( admin_url( 'options-media.php' ) ); ?>#opt_new_sizes"><?php esc_html_e( 'Define Custom Image Sizes', 'sirsc' ); ?></a>
				</div>
			</div>

			<div class="sirsc-image-generate-functionality">
				<form id="sirsc_settings_frm" name="sirsc_settings_frm" action="" method="post">
					<?php wp_nonce_field( '_sirsc_settings_save', '_sirsc_settings_nonce' ); ?>

					<div class="rows bg-secondary breakable has-gaps">
						<div class="span5">
							<button type="button"
								class="button button-primary f-right"
								name="sirsc-settings-submit"
								value="submit"
								data-sirsc-autosubmit="click">
								<?php esc_html_e( 'Save Settings', 'sirsc' ); ?>
							</button>

							<h2><?php show_info_icon( 'info_developer_mode' ); ?><?php esc_html_e( 'Option to Enable Placeholders', 'sirsc' ); ?></h2>
							<?php show_info_text( 'info_developer_mode', __( 'If you activate the "force global" option, all the images on the front-side that are related to posts will be replaced with the placeholders that mention the image size required. This is useful for debugging, to quickly identify the image sizes used for each layout and perhaps to help you regenerate the mission ones or decide what to keep or what to remove.', 'sirsc' ) . '<hr>' . __( 'If you activate the "only missing images" option, all the programmatically called images on the front-side that are related to posts and do not have the requested image size generated will be replaced with the placeholders that mention the image size required. This is useful for showing smaller images instead of the full-size images (as WordPress does by default), hence for speeding up the pages loading.', 'sirsc' ) ); ?>

							<p>
								<?php esc_html_e( 'This option allows you to display placeholders for the front-side images called programmatically (the images that are not embedded in the content with their src, but exposed using WordPress native functions). If there is no placeholder set, then the WordPress default behavior would be to display the full-size image instead of a missing image size, hence your pages might load slower, and when using grids, the items would not look even.', 'sirsc' ); ?>
								<?php
								if ( ! wp_is_writable( SIRSC_PLACEHOLDER_FOLDER ) ) {
									esc_html_e( 'This feature might not work properly, your placeholders folder is not writtable.', 'sirsc' );
								}
								?>
							</p>

							<label>
								<input type="radio" name="sirsc[placeholders]" id="sirsc_placeholders_none" value=""
									<?php checked( true, ( empty( $settings['placeholders'] ) ) ); ?>
									<?php setting_is_readonly( 'placeholders' ); ?> data-sirsc-autosubmit="change">
								<?php esc_html_e( 'no placeholder', 'sirsc' ); ?>
							</label>,
							<label>
								<input type="radio" name="sirsc[placeholders]" id="sirsc_placeholders_force_global"
								value="force_global" <?php checked( true, ( ! empty( $settings['placeholders']['force_global'] ) ) ); ?> <?php setting_is_readonly( 'placeholders' ); ?>data-sirsc-autosubmit="change">
								<?php esc_html_e( 'force global', 'sirsc' ); ?>
							</label>,
							<label>
								<input type="radio" name="sirsc[placeholders]" id="sirsc_placeholders_only_missing"
								value="only_missing" <?php checked( true, ( ! empty( $settings['placeholders']['only_missing'] ) ) ); ?> <?php setting_is_readonly( 'placeholders' ); ?> data-sirsc-autosubmit="change">
								<?php esc_html_e( 'only missing images', 'sirsc' ); ?>
							</label>
						</div>
						<div class="span5">
							<h2><?php esc_html_e( 'Option to Exclude Image Sizes', 'sirsc' ); ?></h2>
							<p>
								<?php esc_html_e( 'This plugin provides the option to select image sizes that will be excluded from the generation of the new images. By default, all image sizes defined in the system will be allowed (these are programmatically registered by the themes and plugins you activate in your site, without you even knowing about these). You can set up a global configuration, or more specific configuration for all images attached to a particular post type. If no particular settings are made for a post type, then the default general settings will be used.', 'sirsc' ); ?>
							</p>
						</div>
						<div class="span2">
							<button type="button"
								class="button  sirsc-button-icon tiny f-right"
								name="sirsc-settings-reset"
								value="submit"
								data-sirsc-autosubmit="click"
								title="<?php esc_attr_e( 'Reset', 'sirsc' ); ?>">
								<span class="dashicons dashicons-trash"></span>
								<?php esc_html_e( 'Reset', 'sirsc' ); ?>
							</button>
							<h2><?php esc_html_e( 'Reset', 'sirsc' ); ?></h2>
							<p>
								<?php esc_html_e( 'Click the button to reset all settings for this plugin. The reset will not remove the custom registered image sizes, but only the settings.', 'sirsc' ); ?>
							</p>
						</div>
					</div>

					<div class="rows bg-secondary no-shadow no-gaps breakable">
						<div class="span9"<?php has_custom_color(); ?>>
							<h2><?php esc_html_e( 'Apply the settings below for the selected option', 'sirsc' ); ?></h2>
							<?php esc_html_e( 'The options for which you made some settings are marked with * in the dropdown below.', 'sirsc' ); ?>
							<?php esc_html_e( 'When you select a post type the general options will not be editable, only these that can apply to the images attached to a post will be available for updates.', 'sirsc' ); ?>
						</div>
						<div class="span3"<?php has_custom_color(); ?>>
							<?php esc_html_e( 'Select the general settings that applies to all, or only one of the post types if necessary.', 'sirsc' ); ?>
							<?php
							if ( ! empty( $post_types ) ) {
								$ptypes = [];
								$has    = ( ! empty( $default_plugin_settings ) ) ? '* ' : '';
								?>
								<select name="sirsc[post_types]" id="sirsc_post_type">
									<option value=""><?php echo esc_html( $has . esc_html__( 'General settings (used as default for all images)', 'sirsc' ) ); ?></option>
								<?php
								foreach ( $post_types as $pt => $obj ) {
									array_push( $ptypes, $pt );
									$is_sel = ( $_sirsc_post_types === $pt ) ? 1 : 0;
									$extra  = ( ! empty( $obj->_builtin ) ) ? '' : ' (custom post type)';
									$pt_s   = maybe_unserialize( get_option( 'sirsc_settings_' . $pt ) );
									$has    = ( ! empty( $pt_s ) ) ? '* ' : '';
									?>
									<option value="<?php echo esc_attr( $pt ); ?>"<?php selected( 1, $is_sel ); ?>><?php echo esc_html( $has . esc_html__( 'Settings for images attached to a ', 'sirsc' ) . ' ' . $pt . $extra ); ?></option>
									<?php
								}
								?>
								</select>
								<?php
								update_option( 'sirsc_types_options', $ptypes );
							}
							?>
						</div>
					</div>

					<?php
					$cron_span = ( ! empty( $settings['cron_bulk_execution'] ) ) ? 'span5' : 'span4';
					$gene_span = ( ! empty( $settings['cron_bulk_execution'] ) ) ? 'span7' : 'span8';
					?>

					<div class="rows dense bg-secondary">
						<div class="<?php echo esc_attr( $gene_span ); ?>">
							<button type="button"
								class="button button-primary f-right"
								name="sirsc-settings-submit"
								value="submit"
								data-sirsc-autosubmit="click">
								<?php esc_html_e( 'Save Settings', 'sirsc' ); ?>
							</button>

							<h2><?php esc_html_e( 'General Settings', 'sirsc' ); ?></h2>
							<p></p>

							<?php $readonly = ( ! empty( $_sirsc_post_types ) ) ? 'readonly="readonly" disabled="disabled"' : ''; ?>
							<div class="rows no-shadow bg-trans breakable mini-gaps">
								<div>
									<label class="settings">
										<input type="checkbox" name="sirsc[listing_tiny_buttons]"
											id="sirsc_listing_tiny_buttons"
											<?php checked( true, $settings['listing_tiny_buttons'] ); ?>
											<?php setting_is_readonly( 'listing_tiny_buttons' ); ?>
											data-sirsc-autosubmit="change">
										<?php esc_html_e( 'show small buttons in the media screen', 'sirsc' ); ?>
									</label>
								</div>
								<div>
									<label class="settings">
										<input type="checkbox" name="sirsc[listing_show_summary]"
											id="sirsc_listing_show_summary"
											<?php checked( true, $settings['listing_show_summary'] ); ?>
											<?php setting_is_readonly( 'listing_show_summary' ); ?>
											data-sirsc-autosubmit="change">
										<?php esc_html_e( 'show attachment image sizes summary in the media screen', 'sirsc' ); ?>
									</label>
								</div>
								<div>
									<label class="settings">
										<input type="checkbox" name="sirsc[force_size_choose]" id="sirsc_force_size_choose"
											<?php checked( true, $settings['force_size_choose'] ); ?>
											<?php setting_is_readonly( 'force_size_choose' ); ?>
											data-sirsc-autosubmit="change">
										<?php esc_html_e( 'filter and expose the image sizes available for the attachment display settings in the media dialog (any registered available size, even when there is no explicit filter applied)', 'sirsc' ); ?>
									</label>
								</div>

								<?php if ( class_exists( 'WooCommerce' ) ) : ?>
									<div>
										<label class="settings">
											<input type="checkbox" name="sirsc[disable_woo_thregen]"
												id="sirsc_disable_woo_thregen"
												<?php checked( true, $settings['disable_woo_thregen'] ); ?>
												<?php setting_is_readonly( 'disable_woo_thregen' ); ?>
												data-sirsc-autosubmit="change">
											<?php esc_html_e( 'turn off the WooCommerce background thumbnails regenerate', 'sirsc' ); ?>
										</label>
									</div>
								<?php endif; ?>

								<?php if ( defined( 'EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH' ) ) : ?>
									<div>
										<label class="settings">
											<input type="checkbox" name="sirsc[sync_settings_ewww]"
												id="sirsc_sync_settings_ewww"
												<?php checked( true, $settings['sync_settings_ewww'] ); ?>
												<?php setting_is_readonly( 'sync_settings_ewww' ); ?>
												data-sirsc-autosubmit="change">
											<?php esc_html_e( 'sync ignored image sizes with EWWW Image Optimizer plugin', 'sirsc' ); ?>
											<?php show_info_icon( 'info_sync_settings_ewww' ); ?>
										</label>
										<?php show_info_text( 'info_sync_settings_ewww', __( 'This option allows you to sync <em>disable creation</em> image sizes from <b>EWWW Image Optimizer</b> plugin with the <em>global ignore</em> image sizes from <b>Image Regenerate & Select Crop</b>. In this way, when you update the settings in one of the plugins, the settings will be synced in the other plugin.', 'sirsc' ) ); ?>
									</div>
								<?php endif; ?>

								<div>
									<label class="settings">
										<input type="checkbox" name="sirsc[bulk_actions_descending]"
											id="sirsc_bulk_actions_descending"
											<?php checked( true, $settings['bulk_actions_descending'] ); ?>
											<?php setting_is_readonly( 'bulk_actions_descending' ); ?>
											data-sirsc-autosubmit="change">
										<?php esc_html_e( 'bulk regenerate/cleanup execution starts from the most recent files', 'sirsc' ); ?>
										<?php show_info_icon( 'info_bulk_actions_descending' ); ?>
									</label>
									<?php show_info_text( 'info_bulk_actions_descending', __( 'This option allows you to run the bulk cleanup and bulk regenerate actions starting from the most recent files you have in the media library until the most old image is found. This is useful if you know when you can pause/stop the bulk actions, for example when you already run the bulk actions for older files and you only need to run this for more recent uploads.<hr>By default, the bulk actions will run from the oldest to the newest files.', 'sirsc' ) ); ?>
								</div>
								<div>
									<label class="settings">
										<input type="checkbox" name="sirsc[leave_settings_behind]"
											id="sirsc_leave_settings_behind"
											<?php checked( true, $settings['leave_settings_behind'] ); ?>
											<?php setting_is_readonly( 'leave_settings_behind' ); ?>
											data-sirsc-autosubmit="change">
										<?php esc_html_e( 'do not cleanup the settings after the plugin is deactivated', 'sirsc' ); ?>
									</label>
								</div>

								<div>
									<label class="settings">
										<input type="checkbox" name="sirsc[enable_debug_log]"
											id="sirsc_enable_debug_log"
											<?php checked( true, $settings['enable_debug_log'] ); ?>
											<?php setting_is_readonly( 'enable_debug_log' ); ?>
											data-sirsc-autosubmit="change">
										<?php esc_html_e( 'turn on the custom debug log for monitoring the events execution', 'sirsc' ); ?>
									</label>
								</div>
							</div>

						</div>

						<div class="<?php echo esc_attr( $cron_span ); ?>">
							<h2><?php esc_html_e( 'Cron Tasks', 'sirsc' ); ?></h2>
							<p></p>
							<label class="settings">
								<input type="checkbox" name="sirsc[cron_bulk_execution]"
									id="sirsc_cron_bulk_execution"
									<?php checked( true, $settings['cron_bulk_execution'] ); ?>
									<?php setting_is_readonly( 'cron_bulk_execution' ); ?>
									data-sirsc-autosubmit="change">
								<?php esc_html_e( 'execute bulk actions using the WordPress cron tasks instead of the default interface', 'sirsc' ); ?>
								<?php show_info_icon( 'info_cron_bulk_execution' ); ?>
							</label>

							<?php show_info_text( 'info_cron_bulk_execution', __( 'This option allows you to offload the execution of bulk actions to the WordPress cron tasks. This will run the intended actions as background tasks, instead of using the plugin default interface.', 'sirsc' ) . '<hr><b>' . __( 'You will have to adjust the batches size based on your server settings and limitations.', 'sirsc' ) . '</b><hr>' . __( 'If the batch size is big, the whole processing will finish faster, but it can fail if your server runs out of resources. Setting a small size for the batch will encrease the time required for the whole processing to finish, but is less resource intensive.', 'sirsc' ) ); ?>

							<?php
							if ( ! empty( $settings['cron_bulk_execution'] ) ) {
								?>
								<hr>
								<p>
									<label class="settings">
										<span><input type="number" size="3" name="sirsc[cron_batch_regenerate]"
											id="sirsc_cron_batch_regenerate"
											value="<?php echo (int) $settings['cron_batch_regenerate']; ?>"
											<?php setting_is_readonly( 'cron_batch_regenerate' ); ?>
											data-sirsc-autosubmit="change"></span>
										<?php esc_html_e( 'the number of images to be regenerated at each cron task iteration', 'sirsc' ); ?>
									</label>
								</p>
								<p>
									<label class="settings">
										<span><input type="number" size="3" name="sirsc[cron_batch_cleanup]"
											id="sirsc_cron_batch_cleanup"
											value="<?php echo (int) $settings['cron_batch_cleanup']; ?>"
											<?php setting_is_readonly( 'cron_batch_cleanup' ); ?>
											data-sirsc-autosubmit="change"></span>
										<?php esc_html_e( 'the number of images to be cleanup at each cron task iteration', 'sirsc' ); ?>
									</label>
								</p>
								<hr>
								<label class="settings">
									<?php esc_html_e( 'Cancel all currently scheduled tasks that aim to regenerate or cleanup the images.', 'sirsc' ); ?>
									<span><button type="button"
										class="button sirsc-button-icon tiny f-right"
										name="sirsc-settings-cancel-crons"
										value="submit"
										data-sirsc-autosubmit="click">
										<span class="dashicons dashicons-trash"></span>
										<?php esc_html_e( 'Cancel', 'sirsc' ); ?>
									</button></span>
								</label>

								<?php
							}
							?>
						</div>

						<div class="span4"<?php has_custom_color(); ?>>
							<button type="button"
								class="button button-primary f-right"
								name="sirsc-settings-submit"
								value="submit"
								data-sirsc-autosubmit="click">
								<?php esc_html_e( 'Save Settings', 'sirsc' ); ?>
							</button>

							<h2><?php esc_html_e( 'Other Settings', 'sirsc' ); ?></h2>

							<p><p>
							<?php
							if ( ! empty( $_sirsc_post_types ) ) {
								esc_html_e( 'These settings are targetting only the post type you selected above.', 'sirsc' );
							}
							?>

							<div class="rows no-shadow bg-trans breakable mini-gaps">
								<div>
									<label class="settings">
										<input type="checkbox" name="sirsc[enable_perfect]"
											id="sirsc_enable_perfect"
											<?php checked( true, $settings['enable_perfect'] ); ?>
											data-sirsc-autosubmit="change">
										<?php esc_html_e( 'generate only perfect fit sizes', 'sirsc' ); ?>
										<?php show_info_icon( 'info_perfect_fit' ); ?>
									</label>
									<?php show_info_text( 'info_perfect_fit', __( 'This option allows you to generate only images that match exactly the width and height of the crop/resize requirements, when the option is enabled. Otherwise, the script will generate anything possible for smaller images.', 'sirsc' ) ); ?>
								</div>
								<div>
									<label class="settings">
										<input type="checkbox" name="sirsc[enable_upscale]"
											id="sirsc_enable_upscale"
											<?php checked( true, $settings['enable_upscale'] ); ?>
											data-sirsc-autosubmit="change">
										<?php esc_html_e( 'attempt to upscale when generating only perfect fit sizes', 'sirsc' ); ?>
										<?php show_info_icon( 'info_perfect_fit_upscale' ); ?>
									</label>
									<?php show_info_text( 'info_perfect_fit_upscale', __( 'This option allows you to upscale the images when using the perfect fit option. This allows that images that have at least the original width close to the expected width or the original height close to the expected height (for example, the original image has 800x600 and the crop size 700x700) to be generated from a upscaled image.', 'sirsc' ) ); ?>
								</div>
								<div>
									<label class="settings">
										<input type="checkbox" name="sirsc[regenerate_missing]"
											id="sirsc_regenerate_missing"
											<?php checked( true, $settings['regenerate_missing'] ); ?>
											data-sirsc-autosubmit="change">
										<?php esc_html_e( 'regenerate only missing files', 'sirsc' ); ?>
										<?php show_info_icon( 'info_regenerate_missing' ); ?>
									</label>
									<?php show_info_text( 'info_regenerate_missing', __( 'This option allows you to regenerate only the images that do not exist, without overriding the existing ones.', 'sirsc' ) ); ?>
								</div>
								<div>
									<label class="settings">
										<input type="checkbox" name="sirsc[regenerate_only_featured]"
											id="sirsc_regenerate_only_featured"
											<?php checked( true, $settings['regenerate_only_featured'] ); ?>
											data-sirsc-autosubmit="change">
										<?php esc_html_e( 'regenerate/cleanup only featured images', 'sirsc' ); ?>
										<?php show_info_icon( 'info_regenerate_only_featured' ); ?>
									</label>
									<?php show_info_text( 'info_regenerate_only_featured', __( 'This option allows you to regenerate/cleanup only the images that are set as featured image for any of the posts.', 'sirsc' ) ); ?>
								</div>
							</div>
						</div>

						<div class="span4"<?php has_custom_color(); ?>>
							<h2><?php esc_html_e( 'General Cleanup', 'sirsc' ); ?></h2>

							<p><b><?php esc_html_e( 'It is recommended to run the cleanup using the command line tools.', 'sirsc' ); ?></b><br><?php esc_html_e( 'However, if you do not have access to wp-cli on your server, you could run the cleanup actions by using the cron tasks, or, if you have a small set of images to cleanup, by using the plugin dialog.', 'sirsc' ); ?></p>

							<hr>

							<label class="settings">
								<?php \SIRSC\Helper\settings_button_raw_cleanup( $_sirsc_post_types, 'unused' ); ?>
								<?php esc_html_e( 'Cleanup unused files and keep currently registered sizes files', 'sirsc' ); ?>
								<?php show_info_icon( 'info_current_cleanup' ); ?>
							</label>
							<?php show_info_text( 'info_current_cleanup', __( 'This type of cleanup is performed for all the attachments, and it removes any attachment unused file and keeps only the files associated with the currently registered image sizes. This action is also changing the attachment metadata in the database, and it is irreversible.', 'sirsc' ) ); ?>

							<p></p>

							<label class="settings">
								<?php \SIRSC\Helper\settings_button_raw_cleanup( $_sirsc_post_types, 'raw' ); ?>
								<?php esc_html_e( 'Keep only the original/full size files', 'sirsc' ); ?>
								<?php show_info_icon( 'info_raw_cleanup' ); ?>
							</label>
							<?php show_info_text( 'info_raw_cleanup', __( 'This type of cleanup is performed for all the attachments, and it keeps only the file associated with the original/full size. This action is also changing the attachment metadata in the database, and it is irreversible. After this process is done, you need to regenerate the files for the desired image sizes.', 'sirsc' ) ); ?>
						</div>
					</div>

					<?php
					show_info_text( 'info_global_ignore', __( 'This option allows you to exclude globally from the application some of the image sizes that are registered through various plugins and themes options, that you perhaps do not need at all in your application (these are just stored in your folders and database but not actually used/visible on the front-end).', 'sirsc' ) . '<hr>' . __( 'By excluding these, the unnecessary image sizes will not be generated at all.', 'sirsc' ) );

					show_info_text( 'info_default_quality', __( 'The quality option is allowing you to control the quality of the images that are generated for each of the image sizes, starting from the quality of the image you upload. This can be useful for performance.', 'sirsc' ) . '<hr><b>' . __( 'However, please be careful not to change the quality of the full image or the quality of the image size that you set as the forced original.', 'sirsc' ) . '</b><hr>' . __( 'Setting a lower quality is recommended for smaller images sizes, that are generated from the full/original file.', 'sirsc' ) );

					show_info_text( 'info_force_original', __( 'This option means that when uploading an image, the original image will be replaced completely by the image size you select (the image generated, scaled or cropped to a specific width and height will become the full size for that image going further).', 'sirsc' ) . '<hr>' . __( 'This can be very useful if you do not use the original image in any of the layouts at the full size, and this might save some storage space.', 'sirsc' ) . '<hr>' . __( 'Leave "nothing selected" to keep the full/original image as the file you upload (default WordPress behavior).', 'sirsc' ) );

					show_info_text( 'info_exclude', __( 'This option allows you to hide from the "Image Regenerate & Select Crop Settings" lightbox the details and options for the selected image sizes (when you or other admins are checking the image details, the hidden image sizes will not be shown).', 'sirsc' ) . '<hr>' . __( 'This is useful when you want to restrict from other users the functionality of crop or resize for particular image sizes, or to just hide the image sizes you added to global ignore.', 'sirsc' ) . '<hr>' . __( 'If you set the image size as ignored or unavailable, this will not be listed in the media screen when the dropdown of image sizes will be shown.', 'sirsc' ) );

					show_info_text( 'info_default_crop', __( 'This option allows you to set a default crop position for the images generated for a particular image size. This option will be applied when you chose to regenerate an individual image or all of these, and also when a new image is uploaded.', 'sirsc' ) );

					show_info_text( 'info_clean_up', __( 'This option allows you to clean up all the image generated for a particular image size you already have in the application, and that you do not use or do not want to use anymore on the front-end.', 'sirsc' ) . '<hr><b>' . __( 'Please be careful, once you click to remove the images for a selected image size, the action is irreversible, the images generated up this point will be deleted from your folders and database records.', 'sirsc' ) . '</b><hr>' . __( 'You can regenerate these later if you click the Regenerate button.', 'sirsc' ) );

					show_info_text( 'info_regenerate', __( 'This option allows you to regenerate the images for the selected image size.', 'sirsc' ) . '<hr><b>' . __( 'Please be careful, once you click the button to regenerate the selected image size, the action is irreversible, the images already generated will be overwritten.', 'sirsc' ) . '</b>' );
					?>

					<div class="sirsc-sticky">
						<div class="rows bg-secondary dense small-pad no-shadow no-gaps heading">
							<div class="span2"<?php has_custom_color(); ?>>
								<h3>
									<?php esc_html_e( 'Ignore', 'sirsc' ); ?>
									<?php show_info_icon( 'info_global_ignore' ); ?>
								</h3>
							</div>
							<div class="span4"<?php has_custom_color(); ?>>
								<h3>
									<?php esc_html_e( 'Image Size Info', 'sirsc' ); ?>
								</h3>
							</div>
							<div class="span2 a-right"<?php has_custom_color(); ?>>
								<h3>
									<?php esc_html_e( 'Quality', 'sirsc' ); ?>
									<?php show_info_icon( 'info_default_quality' ); ?>
								</h3>
								<div class="row-hint">
									<a onclick="sirscResetAllQuality('<?php echo (int) \SIRSC::DEFAULT_QUALITY; ?>')">
									<?php esc_html_e( 'reset to default quality', 'sirsc' ); ?></a>
								</div>
							</div>
							<div class="span4"<?php has_custom_color(); ?>>
								<h3>
									<?php esc_html_e( 'Force Original', 'sirsc' ); ?>
									<?php show_info_icon( 'info_force_original' ); ?>
								</h3>
								<label>
									<?php esc_html_e( 'nothing selected (keep the full/original file uploaded)', 'sirsc' ); ?>
									<input type="radio" name="sirsc[force_original]"
										id="sirsc_force_original_0"
										value="0" <?php checked( 1, 1 ); ?>
										data-sirsc-autosubmit="change"
										onchange="sirscToggleRowClass( 'sirsc-settings-for-0', 'row-original' );">
								</label>
							</div>
							<div class="span2"<?php has_custom_color(); ?>>
								<h3>
									<?php esc_html_e( 'Hide Preview', 'sirsc' ); ?>
									<?php show_info_icon( 'info_exclude' ); ?>
								</h3>
							</div>
							<div class="span2 a-center"<?php has_custom_color(); ?>>
								<h3>
									<?php esc_html_e( 'Default Crop', 'sirsc' ); ?>
									<?php show_info_icon( 'info_default_crop' ); ?>
								</h3>
							</div>
							<div class="span2 a-right"<?php has_custom_color(); ?>>
								<h3>
									<?php esc_html_e( 'Cleanup', 'sirsc' ); ?>
									<?php show_info_icon( 'info_clean_up' ); ?>
								</h3>
							</div>
							<div class="span2 a-right"<?php has_custom_color(); ?>>
								<h3>
									<?php esc_html_e( 'Regenerate', 'sirsc' ); ?>
									<?php show_info_icon( 'info_regenerate' ); ?>
								</h3>
							</div>
						</div>
					</div>

					<?php
					$all_sizes = \SIRSC::get_all_image_sizes();
					if ( ! empty( $all_sizes ) ) :
						foreach ( $all_sizes as $k => $v ) :
							$use  = get_usable_info( $k, $settings );
							$clon = '';
							if ( ! substr_count( $use['line_class'], '_sirsc_included' ) ) {
								$clon .= ' row-hide';
							}
							if ( substr_count( $use['line_class'], '_sirsc_ignored' ) ) {
								$clon .= ' row-ignore';
							}
							if ( substr_count( $use['line_class'], '_sirsc_force_original' ) ) {
								$clon .= ' row-original';
							}

							$tr_id = 'sirsc-settings-for-' . esc_attr( $k );
							?>

							<div id="<?php echo esc_attr( $tr_id ); ?>" class="rows dense small-pad bg-alternate settings-rows no-gaps no-shadow no-top <?php echo esc_attr( $clon ); ?>">
								<div class="span2 option-ignore" data-title="<?php esc_attr_e( 'Ignore', 'sirsc' ); ?>">
									<label>
										<input type="checkbox"
											name="sirsc[global_ignore][<?php echo esc_attr( $k ); ?>]"
											id="sirsc_global_ignore_<?php echo esc_attr( $k ); ?>"
											value="<?php echo esc_attr( $k ); ?>"
											<?php checked( 1, $use['is_ignored'] ); ?>
											data-sirsc-autosubmit="change"
											onchange="sirscToggleRowClass( '<?php echo esc_attr( $tr_id ); ?>', 'row-ignore' );" />
										<?php esc_html_e( 'global ignore', 'sirsc' ); ?>
									</label>
								</div>
								<div class="span4 option-main" data-title="<?php esc_attr_e( 'Image Size Info', 'sirsc' ); ?>">
									<?php
									if ( ! empty( $settings['placeholders'] ) ) {
										?>
										<span class="f-right sirsc-placeholder" id="sirsc-placeholder-<?php echo esc_attr( $k ); ?>">
											<?php \SIRSC\Helper\placeholder_preview( $k ); ?>
										</span>
										<?php
									}
									?>
									<h3><?php echo esc_html( $k ); ?></h3>
									<div><?php echo wp_kses_post( \SIRSC\Helper\size_to_text( $v ) ); ?></div>
								</div>

								<div class="span2 option-quality a-right" data-title="<?php esc_attr_e( 'Quality', 'sirsc' ); ?>">
									<div class="sirsc-size-quality-wrap">
										<label>
											<?php esc_html_e( 'Quality', 'sirsc' ); ?>
											<input type="number"
												name="sirsc[default_quality][<?php echo esc_attr( $k ); ?>]"
												id="sirsc_default_quality_<?php echo esc_attr( $k ); ?>"
												max="100" min="1" size="2"
												value="<?php echo (int) $use['quality']; ?>"
												data-sirsc-autosubmit="change"
												onchange="alert('<?php esc_attr_e( 'Please be aware that your are changing the quality of the images going further for this images size!', 'sirsc' ); ?>');"
												class="sirsc-size-quality">
										</label>
									</div>

								</div>
								<div class="span4 option-original" data-title="<?php esc_attr_e( 'Force Original', 'sirsc' ); ?>">
									<label>
										<input type="radio" name="sirsc[force_original]"
											id="sirsc_force_original_<?php echo esc_attr( $k ); ?>"
											value="<?php echo esc_attr( $k ); ?>"
											<?php checked( 1, $use['is_forced'] ); ?>
											data-sirsc-autosubmit="change"
											onchange="sirscToggleRowClass( '<?php echo esc_attr( $tr_id ); ?>', 'row-original' );">
										<?php esc_html_e( 'force original', 'sirsc' ); ?>
									</label>
								</div>
								<div class="span2 option-exclude" data-title="<?php esc_attr_e( 'Hide Preview', 'sirsc' ); ?>">
									<p>
										<label>
											<input type="checkbox"
												name="sirsc[exclude_size][<?php echo esc_attr( $k ); ?>]"
												id="sirsrc_exclude_size_<?php echo esc_attr( $k ); ?>"
												value="<?php echo esc_attr( $k ); ?>"
												<?php checked( 1, $use['is_checked'] ); ?>
												data-sirsc-autosubmit="change"
												onchange="sirscToggleRowClass( '<?php echo esc_attr( $tr_id ); ?>', 'row-hide' );">
											<?php esc_html_e( 'hide', 'sirsc' ); ?>
										</label>
									</p>
									<label>
										<input type="checkbox"
											name="sirsc[unavailable_size][<?php echo esc_attr( $k ); ?>]"
											id="sirsrc_unavailable_size_<?php echo esc_attr( $k ); ?>"
											value="<?php echo esc_attr( $k ); ?>"
											<?php checked( 1, $use['is_unavailable'] ); ?>
											data-sirsc-autosubmit="change" />
										<?php esc_html_e( 'unavailable', 'sirsc' ); ?>
									</label>
								</div>
								<div class="span2 option-crop a-center"
									data-title="<?php esc_attr_e( 'Default Crop', 'sirsc' ); ?>">
									<div class="crop settings">
										<?php
										if ( ! empty( $v['crop'] ) ) {
											echo \SIRSC\Helper\make_generate_images_crop( 0, $k, false, $use['has_crop'] ); // phpcs:ignore
										}
										?>
									</div>
								</div>
								<div class="span2 option-cleanup a-right"
									data-title="<?php esc_attr_e( 'Cleanup', 'sirsc' ); ?>">
									<?php \SIRSC\Helper\settings_button_size_cleanup( $_sirsc_post_types, $k ); ?>
								</div>
								<div class="span2 option-regenerate a-right"
									data-title="<?php esc_attr_e( 'Regenerate', 'sirsc' ); ?>">
									<?php \SIRSC\Helper\settings_button_size_regenerate( $_sirsc_post_types, $k ); ?>
								</div>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>

				</form>
			</div>
		</div>
	</div>
	<?php
}

/**
 * Assess the collected regenerate results and returns the errors if found.
 *
 * @return string
 */
function assess_collected_errors() { // phpcs:ignore
	$message = '';
	$errors  = get_option( 'sirsc_monitor_errors' );
	if ( ! empty( $errors['schedule'] ) ) {
		foreach ( $errors['schedule'] as $id => $filename ) {
			if ( empty( $errors['error'][ $id ] ) ) {
				$errors['error'][ $id ] = '<em>' . $filename . '</em> - ' . esc_html__( 'The original filesize is too big and the server does not have enough resources to process it.', 'sirsc' );
			}
		}
	}

	if ( ! empty( $errors['error'] ) ) {
		if ( ! empty( $errors['initiator'] ) && 'cleanup' === $errors['initiator'] ) {
			$sep     = '<b class="dashicons dashicons-dismiss"></b> ';
			$message = wp_kses_post(
				sprintf(
					// Translators: %1$s - separator, %2$s - server side error.
					__( '<b>Unfortunately, there was an error</b>. Some of the execution might not have been successful. This can happen when: <br>&bull; the image you were trying to delete is <b>the original</b> file,<br>&bull; the image size was pointing to the <b>the original</b> and it should not be removed,<br>&bull; the <b>file is missing</b>.%1$sSee the details: %2$s', 'sirsc' ),
					'</div><div class="info-reason">',
					'<div class="info-list sirsc-errors">
						<div class="info-item">' . $sep . implode( '</div><div class="info-item">' . $sep, $errors['error'] ) . '</div>
					</div>'
				)
			);
		} else {
			$sep     = '<b class="dashicons dashicons-dismiss"></b> ';
			$message = wp_kses_post(
				sprintf(
					// Translators: %1$s - server side error.
					__( '<b>Unfortunately, there was an error</b>. Some of the execution might not have been successful. This can happen in when: <br>&bull; the image from which the script is generating the specified image size does not have the <b>proper size</b> for resize/crop to a specific width and height,<br>&bull; the attachment <b>metadata is broken</b>,<br>&bull; the original <b>file is missing</b>,<br>&bull; the image that is processed is <b>very big</b> (rezolution or size) and the <b>allocated memory</b> on the server is not enough to handle the request,<br>&bull; the overall processing on your site is <b>too intensive</b>.%1$sSee the details: %2$s', 'sirsc' ),
					'</div><div class="info-reason">',
					'<div class="info-list sirsc-errors">
						<div class="info-item">' . $sep . implode( '</div><div class="info-item">' . $sep, $errors['error'] ) . '</div>
					</div>'
				)
			);
		}

		$upls    = wp_upload_dir();
		$message = '<div class="info-message">' . str_replace( trailingslashit( $upls['basedir'] ), '', $message ) . '</div>';
		$message = str_replace( trailingslashit( $upls['baseurl'] ), '', $message );
	}
	return $message;
}

/**
 * Compute image size readable info from settings.
 *
 * @param string $k    Image size slug.
 * @param array  $info Settings array.
 */
function get_usable_info( $k, $info ) { // phpcs:ignore
	$data = [
		'is_ignored'     => ( ! empty( $info['complete_global_ignore'] ) && in_array( $k, $info['complete_global_ignore'], true ) ) ? 1 : 0,
		'is_checked'     => ( ! empty( $info['exclude'] ) && in_array( $k, $info['exclude'], true ) ) ? 1 : 0,
		'is_unavailable' => ( ! empty( $info['unavailable'] ) && in_array( $k, $info['unavailable'], true ) ) ? 1 : 0,
		'is_forced'      => ( ! empty( $info['force_original_to'] ) && $k === $info['force_original_to'] ) ? 1 : 0,
		'has_crop'       => ( ! empty( $info['default_crop'][ $k ] ) ) ? $info['default_crop'][ $k ] : 'cc',
		'quality'        => ( ! empty( $info['default_quality'][ $k ] ) ) ? (int) $info['default_quality'][ $k ] : \SIRSC::DEFAULT_QUALITY,
		'line_class'     => '',
	];

	$data['quality']     = ( empty( $data['quality'] ) ) ? \SIRSC::DEFAULT_QUALITY : $data['quality'];
	$data['line_class'] .= ( ! empty( $data['is_ignored'] ) ) ? ' _sirsc_ignored' : '';
	$data['line_class'] .= ( ! empty( $data['is_forced'] ) ) ? ' _sirsc_force_original' : '';
	$data['line_class'] .= ( empty( $data['is_checked'] ) ) ? ' _sirsc_included' : '';
	return $data;
}

/**
 * Returns the number if images of "image size name" that can be clean up for a specified post type if is set, or the global number of images that can be clean up for the "image size name".
 *
 * @param string  $post_type       The post type.
 * @param string  $image_size_name The size slug.
 * @param integer $next_post_id    The next post to be processed.
 */
function calculate_total_to_cleanup( $post_type = '', $image_size_name = '', $next_post_id = 0 ) { // phpcs:ignore
	global $wpdb;
	$total_to_delete = 0;
	if ( ! empty( $image_size_name ) ) {
		$cond_join  = '';
		$cond_where = '';
		if ( ! empty( $post_type ) ) {
			$cond_join  = ' LEFT JOIN ' . $wpdb->posts . ' as parent ON( parent.ID = p.post_parent )';
			$cond_where = $wpdb->prepare( ' AND parent.post_type = %s ', $post_type );
		}
		if ( ! empty( \SIRSC::$settings['regenerate_only_featured'] ) ) {
			$cond_join .= ' INNER JOIN ' . $wpdb->postmeta . ' as pm2 ON (pm2.meta_value = p.ID and pm2.meta_key = \'_thumbnail_id\' ) ';
		}
		$tmp_query = $wpdb->prepare( ' SELECT count( distinct p.ID ) as total_to_delete FROM ' . $wpdb->posts . ' as p LEFT JOIN ' . $wpdb->postmeta . ' as pm ON(pm.post_id = p.ID) ' . $cond_join . ' WHERE pm.meta_key like %s AND pm.meta_value like %s AND p.ID > %d AND ( p.post_mime_type like %s and p.post_mime_type not like %s ) ' . $cond_where, // phpcs:ignore
			'_wp_attachment_metadata',
			'%' . $wpdb->esc_like( '"' . $image_size_name . '"' ) . '%',
			intval( $next_post_id ),
			$wpdb->esc_like( 'image/' ) . '%',
			$wpdb->esc_like( 'image/svg' ) . '%'
		); // phpcs:ignore

		$rows = $wpdb->get_results( $tmp_query, ARRAY_A ); // phpcs:ignore
		if ( ! empty( $rows ) && is_array( $rows ) ) {
			$total_to_delete = $rows[0]['total_to_delete'];
		}
	}
	return $total_to_delete;
}

/**
 * Set regenerate last processed id.
 *
 * @param string  $name Image size name.
 * @param integer $id   Post ID.
 */
function set_regenerate_last_processed_id( $name = '', $id = 0 ) { // phpcs:ignore
	update_option( 'sirsc_regenerate_most_recent_' . esc_attr( $name ), $id );
}

/**
 * Remove regenerate last processed id.
 *
 * @param string $name Image size name.
 */
function remove_regenerate_last_processed_id( $name = '' ) { // phpcs:ignore
	delete_option( 'sirsc_regenerate_most_recent_' . esc_attr( $name ) );
}

/**
 * Maybe donate or rate.
 *
 * @return void
 */
function show_donate_text() {
	?>
	<div>
		<?php
		echo wp_kses_post(
			sprintf(
				// Translators: %1$s - donate URL, %2$s - rating.
				__( 'If you find the plugin useful and would like to support my work, please consider making a <a href="%1$s" target="_blank" rel="noreferrer">donation</a>.<br>It would make me very happy if you would leave a %2$s rating.', 'sirsc' ) . ' ' . __( 'A huge thanks in advance!', 'sirsc' ),
				'https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=JJA37EHZXWUTJ&item_name=Support for development and maintenance (' . urlencode( \SIRSC::PLUGIN_NAME ) . ')', // phpcs:ignore
				'<a href="' . \SIRSC::PLUGIN_SUPPORT_URL . 'reviews/?rate=5#new-post" class="rating" target="_blank" rel="noreferrer" title="' . esc_attr__( 'A huge thanks in advance!', 'sirsc' ) . '">★★★★★</a>'
			)
		);
		?>
	</div>
	<img src="<?php echo esc_url( SIRSC_PLUGIN_URL . 'assets/images/icon-128x128.gif' ); ?>" width="32" height="32" alt="">
	<?php
}

/**
 * Output the admin success message for email test sent.
 *
 * @return void
 */
function on_settings_update_notice() { // phpcs:ignore
	$class   = 'notice notice-success is-dismissible';
	$message = __( 'The plugin settings have been updated successfully.', 'sirsc' );
	printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
}

/**
 * Append the image sizes generator button to the edit media page.
 */
function register_image_meta() {
	global $post;
	if ( ! empty( $post->post_type ) && 'attachment' === $post->post_type ) {
		add_action( 'edit_form_top', __NAMESPACE__ . '\\append_image_generate_button_tiny', 10, 2 );
	}
}

/**
 * Append or display the button for generating the missing image sizes and request individual crop of images.
 *
 * @param string  $content      The button content.
 * @param integer $post_id      The main post ID.
 * @param integer $thumbnail_id The attachemnt ID.
 * @param string  $extra_class  The wrapper extra class.
 */
function append_image_generate_button_tiny( $content, $post_id = 0, $thumbnail_id = 0, $extra_class = '' ) { // phpcs:ignore
	return append_image_generate_button( $content, $post_id, $thumbnail_id, 'tiny' );
}

/**
 * Append or display the button for generating the missing image sizes and request individual crop of images.
 *
 * @param string  $content      The button content.
 * @param integer $post_id      The main post ID.
 * @param integer $thumbnail_id The attachemnt ID.
 * @param string  $extra_class  The wrapper extra class.
 */
function append_image_generate_button( $content, $post_id = 0, $thumbnail_id = 0, $extra_class = '' ) { // phpcs:ignore
	$content_button    = '';
	$display           = false;
	$is_the_attachment = false;
	if ( is_object( $content ) ) {
		$thumbnail_id      = $content->ID;
		$display           = true;
		$is_the_attachment = true;
	}

	if ( ! empty( $post_id ) || ! empty( $thumbnail_id ) ) {
		if ( ! empty( $thumbnail_id ) ) {
			$thumb_id = (int) $thumbnail_id;
		} else {
			$thumb_id = (int) get_post_thumbnail_id( $post_id );
		}
		\SIRSC::load_settings_for_post_id( $thumb_id );
		if ( ! empty( $thumb_id ) ) {
			$extra_class   .= ( ! empty( \SIRSC::$settings['listing_tiny_buttons'] ) ) ? ' tiny' : '';
			$extra_class    = str_replace( 'tiny tiny', 'tiny', $extra_class );
			$content_button = '<div id="sirsc-buttons-wrapper-' . $thumb_id . '" class="sirsc-feature as-target sirsc-buttons ' . $extra_class . '">' . \SIRSC\Helper\make_buttons( $thumb_id, true ) . '</div>';
		}

		if ( ! $is_the_attachment && empty( $thumbnail_id ) ) {
			$content_button = '';
		}

		if ( ! $is_the_attachment ) {
			$content = $content_button . $content;
		}
	}

	if ( true === $display && true === $is_the_attachment ) {
		// When the button is in the attachment edit screen, we display the buttons.
		echo '<div class="sirsc_button-regenerate-wrap">' . $content_button . '</div>'; // phpcs:ignore
	}

	return $content;
}

/**
 * Describe the override settings section.
 */
function sirsc_override_section_callback() {
	?>
	<div class="sirsc-feature">
		<div class="sirsc-tabbed-menu-buttons">
			<a class="button button-primary">
				<div class="dashicons dashicons-image-crop"></div>
				<?php esc_html_e( 'Images Custom Settings', 'sirsc' ); ?>
			</a>
			<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=image-regenerate-select-crop-settings' ) ); ?>">
				<?php esc_html_e( 'Image Regenerate & Select Crop', 'sirsc' ); ?>
			</a>
		</div>
		<div class="rows bg-secondary no-top">
			<div>
				<?php esc_html_e( 'You can override the default crop for the medium and large size of the images. Please note that the crop will apply to the designated image size only if it has both with and height defined (as you know, when you set 0 to one of the sizes, the image will be scaled proportionally, hence, the crop cannot be applied).', 'sirsc' ); ?>
			</div>
		</div>
	</div>
	<?php
}

/**
 * Expose the custom media settings.
 */
function sirsc_override_medium_size_callback() {
	$checked     = get_option( 'sirsc_override_medium_size' );
	$medium_crop = get_option( 'medium_crop' );
	$checked     = ( 1 === (int) $medium_crop && 1 === (int) $checked ) ? 1 : 0;
	?>
	<label><input name="sirsc_override_medium_size" id="sirsc_override_medium_size"
		type="checkbox" value="1" class="code"
		<?php checked( 1, $checked ); ?>/> <?php esc_html_e( 'Crop medium image to exact dimensions (normally medium images are proportional)', 'sirsc' ); ?></label>
	<?php
}

/**
 * Expose the custom media settings.
 */
function sirsc_override_medium_large_size_callback() {
	$checked     = get_option( 'sirsc_override_medium_large_size' );
	$medium_crop = get_option( 'medium_large_crop' );
	$checked     = ( 1 === (int) $medium_crop && 1 === (int) $checked ) ? 1 : 0;
	?>
	<label><input name="sirsc_override_medium_large_size" id="sirsc_override_medium_large_size"
		type="checkbox" value="1" class="code"
		<?php checked( 1, $checked ); ?>/> <?php esc_html_e( 'Crop medium large image to exact dimensions (normally medium large images are proportional)', 'sirsc' ); ?></label>
	<?php
}

/**
 * Expose the custom media settings.
 */
function sirsc_override_large_size_callback() {
	$checked    = get_option( 'sirsc_override_large_size' );
	$large_crop = get_option( 'large_crop' );
	$checked    = ( 1 === (int) $large_crop && 1 === (int) $checked ) ? 1 : 0;
	?>
	<label><input name="sirsc_override_large_size" id="sirsc_override_large_size"
		type="checkbox" value="1" class="code"
		<?php checked( 1, $checked ); ?>/> <?php esc_html_e( 'Crop large image to exact dimensions (normally large images are proportional)', 'sirsc' ); ?></label>
	<?php
}

/**
 * Expose the custom media settings.
 */
function sirsc_override_admin_featured_size_callback() {
	$checked   = get_option( 'sirsc_admin_featured_size' );
	$all_sizes = \SIRSC::get_all_image_sizes_plugin();
	?>
	<select name="sirsc_admin_featured_size" id="sirsc_admin_featured_size">
		<option value=""></option>
		<?php foreach ( $all_sizes as $size => $prop ) : ?>
			<option value="<?php echo esc_attr( $size ); ?>"<?php selected( esc_attr( $size ), $checked ); ?>><?php echo esc_attr( $size ); ?></option>
		<?php endforeach; ?>
	</select>
	<br><?php esc_html_e( 'This setting allows you to change the post thumbnail image size that is displayed in the meta box. Leave empty if you want to use the default image size that is set by WordPress and your theme.', 'sirsc' ); ?>
	<?php
}

/**
 * Expose the custom media settings.
 */
function sirsc_custom_sizes_section_callback() {
	?>
	<div class="sirsc-feature">
		<div class="sirsc-tabbed-menu-buttons">
			<a class="button button-primary">
				<div class="dashicons dashicons-format-gallery"></div>
				<?php esc_html_e( 'Define Custom Image Sizes', 'sirsc' ); ?>
			</a>
			<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=image-regenerate-select-crop-settings' ) ); ?>">
				<?php esc_html_e( 'Image Regenerate & Select Crop', 'sirsc' ); ?>
			</a>
		</div>

		<div class="rows bg-secondary no-top">
			<div>
				<?php esc_html_e( 'If you decided it is absolutely necessary to have new custom image sizes, you can make the setup below and these will be registered programmatically in your application if you configured these correctly (you have to input the size name and at least the width or height).', 'sirsc' ); ?>
				<b><?php esc_html_e( 'However, please make sure you only define these below if you are sure this is really necessary, as, any additional image size registered in your application is decreasing the performance on the images upload processing and also creates extra physical files on your hosting.', 'sirsc' ); ?></b>
				<?php esc_html_e( 'Also, please note that changing the image sizes names or width and height values is not recommended after these were defined and your application started to create images for these specifications.', 'sirsc' ); ?>
				</td>
			</div>
		</div>

		<div class="rows bg-trans">
			<div class="sirsc-message warning">
				<b><?php esc_html_e( 'Use this feature wisely.', 'sirsc' ); ?></b> <em><span class="dashicons dashicons-format-quote"></span> <?php esc_html_e( 'With great power comes great responsibility.', 'sirsc' ); ?></em>
				<br><?php esc_html_e( 'Please consult with a front-end developer before deciding to define more image sizes below (and in general in the application), as most of the times just updating the native image sizes settings and updating the front-end code (the theme) is enough.', 'sirsc' ); ?>
			</div>
		</div>
	</div>
	<?php
}

/**
 * Expose the custom media settings.
 */
function sirsc_use_custom_image_sizes_callback() {
	$def = [
		'number' => 0,
		'sizes'  => [],
	];
	$all = maybe_unserialize( get_option( 'sirsc_use_custom_image_sizes' ) );
	if ( empty( $all ) ) {
		$all = [];
	}
	$all = wp_parse_args( $all, $def );
	?>
	<table class="widefat fixed striped">
		<thead>
			<tr>
				<td width="20"></td>
				<td width="40%"><?php esc_html_e( 'Image Sizes Name', 'sirsc' ); ?></td>
				<td width="120"><?php esc_html_e( 'Max Width', 'sirsc' ); ?></td>
				<td width="120"><?php esc_html_e( 'Max Height', 'sirsc' ); ?></td>
				<td><?php esc_html_e( 'Crop', 'sirsc' ); ?></td>
			</tr>
		</thead>
		<tbody>
			<?php
			$counter = 0;
			if ( ! empty( $all['sizes'] ) ) {
				foreach ( $all['sizes'] as $i => $asize ) {
					$name = ( ! empty( $asize['name'] ) ) ? $asize['name'] : '';
					if ( empty( $name ) ) {
						continue;
					}

					++ $counter;

					$width  = ( ! empty( $asize['width'] ) ) ? (int) $asize['width'] : 0;
					$height = ( ! empty( $asize['height'] ) ) ? (int) $asize['height'] : 0;
					$crop   = ( ! empty( $asize['crop'] ) ) ? (int) $asize['crop'] : 0;
					?>
					<tr>
						<td><span class="dashicons dashicons-format-image"></span></td>
						<td>
							<input name="sirsc_use_custom_image_sizes[sizes][name][<?php echo (int) $counter; ?>]"
							id="sirsc_image_size_<?php echo (int) $counter; ?>_name"
							type="text" value="<?php echo esc_attr( $name ); ?>" class="code widefat"/>
							<?php esc_html_e( '(leave empty to remove this image size)', 'sirsc' ); ?>
						</td>
						<td>
							<input name="sirsc_use_custom_image_sizes[sizes][width][<?php echo (int) $counter; ?>]"
								id="sirsc_image_size_<?php echo (int) $counter; ?>_width"
								type="number" value="<?php echo esc_attr( $width ); ?>" class="code widefat"/>
								<?php esc_html_e( '(value in pixels)', 'sirsc' ); ?>
						</td>
						<td>
							<input name="sirsc_use_custom_image_sizes[sizes][height][<?php echo (int) $counter; ?>]"
								id="sirsc_image_size_<?php echo (int) $counter; ?>_height"
								type="number" value="<?php echo esc_attr( $height ); ?>" class="code widefat"/>
								<?php esc_html_e( '(value in pixels)', 'sirsc' ); ?>
						</td>
						<td>
							<label><input name="sirsc_use_custom_image_sizes[sizes][crop][<?php echo (int) $counter; ?>]" id="sirsc_image_size_<?php echo (int) $counter; ?>_crop" type="checkbox" value="1" class="code"
							<?php checked( 1, $crop ); ?>/> <?php esc_html_e( 'Crop the image to exact dimensions', 'sirsc' ); ?>.</label>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=image-regenerate-select-crop-rules' ) ); ?>#sirsc-settings-for-<?php echo esc_attr( $name ); ?>"><?php esc_html_e( 'See/manage other settings', 'sirsc' ); ?></a>
						</td>
					</tr>
					<?php
				}
			}
			++ $counter;
			?>
			<tr>
				<td><span class="dashicons dashicons-plus-alt"></span></td>
				<td>
					<input name="sirsc_use_custom_image_sizes[sizes][name][<?php echo (int) $counter; ?>]"
					id="sirsc_image_size_<?php echo (int) $counter; ?>_name"
					type="text" value="" class="code widefat"/>
				</td>
				<td>
					<input name="sirsc_use_custom_image_sizes[sizes][width][<?php echo (int) $counter; ?>]"
						id="sirsc_image_size_<?php echo (int) $counter; ?>_width"
						type="number" value="" class="code widefat"/>
				</td>
				<td>
					<input name="sirsc_use_custom_image_sizes[sizes][height][<?php echo (int) $counter; ?>]"
						id="sirsc_image_size_<?php echo (int) $counter; ?>_height"
						type="number" value="" class="code widefat"/>
				</td>
				<td>
					<label><input name="sirsc_use_custom_image_sizes[sizes][crop][<?php echo (int) $counter; ?>]" id="sirsc_image_size_<?php echo (int) $counter; ?>_crop" type="checkbox" value="1" class="code"/> <?php esc_html_e( 'Crop the image to exact dimensions', 'sirsc' ); ?> <?php esc_html_e( '(normally images are proportional)', 'sirsc' ); ?>.</label>
				</td>
			</tr>
		</tbody>
	</table>
	<?php
}

/**
 * Update the settings as expected.
 *
 * @param  string $option    Option name.
 * @param  string $old_value Option old value.
 * @param  string $value     Option new value.
 * @return void
 */
function on_update_sirsc_override_size( $option, $old_value, $value ) { // phpcs:ignore
	switch ( $option ) {
		case 'sirsc_override_medium_size':
			update_option( 'sirsc_override_medium_size', ! empty( $value ) ? 1 : 0 );
			update_option( 'medium_crop', ! empty( $value ) ? 1 : 0 );
			return;
			break; // phpcs:ignore

		case 'sirsc_override_medium_large_size':
			update_option( 'sirsc_override_medium_large_size', ! empty( $value ) ? 1 : 0 );
			update_option( 'medium_large_crop', ! empty( $value ) ? 1 : 0 );
			return;
			break; // phpcs:ignore

		case 'sirsc_override_large_size':
			update_option( 'sirsc_override_large_size', ! empty( $value ) ? 1 : 0 );
			update_option( 'large_crop', ! empty( $value ) ? 1 : 0 );
			return;
			break; // phpcs:ignore

		case 'sirsc_admin_featured_size':
			update_option( 'sirsc_admin_featured_size', $value );
			return;
			break; // phpcs:ignore

		case 'sirsc_use_custom_image_sizes':
			$native = \SIRSC::get_native_image_sizes();
			if ( empty( $native ) || ! is_array( $native ) ) {
				// Fail-fast.
				return;
			}
			$native = array_merge( $native, [ 'full', 'original', 'original_image', '1536x1536', '2048x2048' ] );
			$value  = filter_input( INPUT_POST, 'sirsc_use_custom_image_sizes', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
			$used   = [];
			$new    = [];
			if ( ! empty( $value['sizes'] ) ) {
				foreach ( $value['sizes']['name'] as $k => $item ) {
					if ( ! empty( $item ) ) {
						$item   = str_replace( '-', '_', sanitize_title( $item ) );
						$item   = strtolower( $item );
						$item   = str_replace( ' ', '_', $item );
						$item   = str_replace( '-', '_', $item );
						$width  = abs( (int) $value['sizes']['width'][ $k ] );
						$height = abs( (int) $value['sizes']['height'][ $k ] );

						if ( in_array( $item, $used, true )
							|| in_array( $item, $native, true )
							|| ( empty( $width ) && empty( $height ) ) ) {
							continue;
						} else {
							$used[] = $item;
						}

						$new[] = [
							'name'   => $item,
							'width'  => $width,
							'height' => $height,
							'crop'   => ( ! empty( $value['sizes']['crop'][ $k ] ) ) ? 1 : 0,
						];
					}
				}
			}

			$updates = [
				'sizes'  => $new,
				'number' => count( $new ),
			];
			update_option( 'sirsc_use_custom_image_sizes', $updates );

			return;
			break; // phpcs:ignore

		default:
			break;
	}
}

/**
 * Functionality to manage the image regenerate & select crop settings.
 */
function sirsc_custom_rules_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		// Verify user capabilities in order to deny the access if the user does not have the capabilities.
		wp_die( esc_html__( 'Action not allowed.', 'sirsc' ) );
	}

	$post_types              = \SIRSC\Helper\get_all_post_types_plugin();
	$_sirsc_post_types       = filter_input( INPUT_GET, '_sirsc_post_types', FILTER_DEFAULT );
	$settings                = maybe_unserialize( get_option( 'sirsc_settings' ) );
	$default_plugin_settings = $settings;
	if ( ! empty( $_sirsc_post_types ) ) {
		$settings = maybe_unserialize( get_option( 'sirsc_settings_' . $_sirsc_post_types ) );
	}

	$all_sizes = \SIRSC::get_all_image_sizes();
	?>

	<div class="wrap sirsc-settings-wrap sirsc-feature">
		<?php show_plugin_top_info(); ?>
		<?php maybe_all_features_tab(); ?>
		<div class="sirsc-tabbed-menu-content">
			<div class="rows bg-secondary no-top">
				<div>
					<?php esc_html_e( 'The advanced custom rules you configure below are global and will override all the other settings you set above.', 'sirsc' ); ?>
					<br>
					<b><?php esc_html_e( 'Please be aware that the custom rules will apply only if you actually set up the post to use one of the rules below, and only then upload images to that post.', 'sirsc' ); ?></b>
				</div>
			</div>

			<div class="sirsc-image-generate-functionality">
				<form id="sirsc_settings_frm" name="sirsc_settings_frm" action="" method="post">
					<?php wp_nonce_field( '_sirsc_settings_save', '_sirsc_settings_nonce' ); ?>

					<div class="rows bg-secondary">
						<div>
							<h3><?php esc_html_e( 'Advanced custom rules based on the post where the image will be uploaded', 'sirsc' ); ?></h3>
							<p>
								<?php esc_html_e( 'Very important: the order in which the rules are checked and have priority is: post ID, post type, post format, post parent, post tags, post categories, other taxonomies. Any of the rules that match first in this order will apply for the images that are generated when you upload images to that post (and the rest of the rules will be ignored). You can suppress at any time any of the rules and then enable these back as it suits you.', 'sirsc' ); ?>
							</p>
						</div>
					</div>

					<?php
					$select_ims = '';
					$checks_ims = '';
					if ( ! empty( $all_sizes ) ) {
						$select_ims .= '<option value="**full**">- ' . esc_attr( 'full/original' ) . ' -</option>';
						foreach ( $all_sizes as $k => $v ) {
							$select_ims .= '<option value="' . esc_attr( $k ) . '">' . esc_attr( $k ) . '</option>';
							$checks_ims .= ( ! empty( $checks_ims ) ) ? ' ' : '';
							$checks_ims .= '<label label-for="#ID#_' . esc_attr( $k ) . '"><input type="checkbox" name="#NAME#" id="#ID#_' . esc_attr( $k ) . '" value="' . esc_attr( $k ) . '">' . esc_attr( $k ) . '</label>';
						}
					}

					$taxonomies = get_taxonomies( [ 'public' => 1 ], 'objects' );
					$select_tax = '';
					if ( ! empty( $taxonomies ) ) {
						foreach ( $taxonomies as $k => $v ) {
							$select_tax .= '<option value="' . esc_attr( $k ) . '">' . esc_attr( $v->label ) . '</option>';
						}
					}
					$select_tax .= '<option value="ID">' . esc_html__( 'Post ID', 'sirsc' ) . '</option>';
					$select_tax .= '<option value="post_parent">' . esc_html__( 'Post Parent ID', 'sirsc' ) . '</option>';
					$select_tax .= '<option value="post_type">' . esc_html__( 'Post Type', 'sirsc' ) . '</option>';
					?>

					<div class="sirsc-sticky">
						<div class="rows bg-secondary dense small-pad no-shadow no-gaps heading">
							<div class="span2">
								<h3><span><?php esc_html_e( 'The post has', 'sirsc' ); ?></span></h3>
								<div class="row-hint"><?php esc_html_e( 'Ex: Categories', 'sirsc' ); ?></div>
							</div>
							<div class="span2">
								<h3><span><?php esc_html_e( 'Value', 'sirsc' ); ?></span></h3>
								<div class="row-hint"><?php esc_html_e( 'Ex: gallery,my-photos', 'sirsc' ); ?></div>
							</div>
							<div class="span3">
								<h3><span><?php esc_html_e( 'Force Original', 'sirsc' ); ?></span></h3>
								<div class="row-hint"><?php esc_html_e( 'Ex: large', 'sirsc' ); ?></div>
							</div>
							<div class="span11">
								<h3><span><?php esc_html_e( 'Generate only these image sizes for the rule', 'sirsc' ); ?></span></h3>
								<div class="row-hint"><?php esc_html_e( 'Ex: thumbnail, large', 'sirsc' ); ?></div>
							</div>
							<div class="span2 a-right">
								<h3><span><?php esc_html_e( 'Suppress', 'sirsc' ); ?></span></h3>
							</div>
						</div>
					</div>

					<?php for ( $i = 1; $i <= 10; $i ++ ) : ?>
						<?php
						$class = 'row-hide-rule';
						if ( ! empty( \SIRSC::$user_custom_rules[ $i ]['type'] )
							&& ! empty( \SIRSC::$user_custom_rules[ $i ]['value'] ) ) {
							$class = 'row-use-rule';
						}
						if ( ! empty( \SIRSC::$user_custom_rules[ $i ]['suppress'] )
							&& 'on' === \SIRSC::$user_custom_rules[ $i ]['suppress'] ) {
							$class .= ' row-ignore-rule';
						}

						$supp = ( ! empty( \SIRSC::$user_custom_rules[ $i ]['suppress'] ) && 'on' === \SIRSC::$user_custom_rules[ $i ]['suppress'] ) ? ' checked="checked"' : '';

						$row_class = ( substr_count( $class, 'row-ignore-rule' ) ) ? 'row-ignore-rule' : $class;
						$row_class = ( substr_count( $class, 'row-hide-rule' ) ) ? 'row-hide-rule' : $row_class;
						$row_class = ( substr_count( $class, 'row-use-rule' ) ) ? 'row-use-rule' : $row_class;

						if ( substr_count( $class, 'row-ignore-rule' ) ) {
							$row_class .= ' sirsc-message warning';
						} elseif ( substr_count( $class, 'row-use-rule' ) ) {
							$row_class .= ' sirsc-message success';
						}

						$extra = '';
						if ( substr_count( $class, 'row-ignore' ) ) {
							$extra .= ' sirsc-message warning';
						} else {
							$extra .= ' sirsc-message success';
						}
						?>

						<div class="rows dense small-pad no-shadow no-top no-gaps bg-alternate advanced-rules alternate <?php echo esc_attr( $row_class ); ?>">
							<div class="span2 option-has" data-title="<?php esc_attr_e( 'The post has', 'sirsc' ); ?>">
								<label>
									<select name="_user_custom_rule[<?php echo (int) $i; ?>][type]"
										id="user_custom_rule_<?php echo (int) $i; ?>_type">
										<option value=""><?php esc_html_e( 'N/A', 'sirsc' ); ?></option>
										<?php
										echo str_replace( // phpcs:ignore
											'value="' . esc_attr( \SIRSC::$user_custom_rules[ $i ]['type'] ) . '"',
											'value="' . esc_attr( \SIRSC::$user_custom_rules[ $i ]['type'] ) . '" selected="selected"',
											$select_tax
										);
										?>
									</select>
								</label>
							</div>
							<div class="span2 option-value" data-title="<?php esc_attr_e( 'Value', 'sirsc' ); ?>">
								<label>
									<input type="text"
									name="_user_custom_rule[<?php echo (int) $i; ?>][value]"
									name="user_custom_rule_<?php echo (int) $i; ?>_value"
									value="<?php echo esc_attr( \SIRSC::$user_custom_rules[ $i ]['value'] ); ?>"
									size="20">
								</label>
							</div>
							<div class="span3 option-original" data-title="<?php esc_attr_e( 'Force Original', 'sirsc' ); ?>">
								<label>
									<select name="_user_custom_rule[<?php echo (int) $i; ?>][original]"
										id="user_custom_rule_<?php echo (int) $i; ?>_original">
										<?php
										$sel = ( ! empty( \SIRSC::$user_custom_rules[ $i ]['original'] ) ) ? \SIRSC::$user_custom_rules[ $i ]['original'] : 'large';
										echo str_replace( // phpcs:ignore
											' value="' . $sel . '"',
											' value="' . $sel . '" selected="selected"',
											$select_ims
										);
										?>
									</select>
								</label>
							</div>
							<div class="span11 option-sizes" data-title="<?php esc_attr_e( 'Generate only these image sizes for the rule', 'sirsc' ); ?>">
								<?php
								if ( ! empty( $class ) && substr_count( $class, 'row-use' ) ) {

									echo '<div class="potential-rule ' . $class . $extra . '">'; // phpcs:ignore
									if ( substr_count( $class, 'row-ignore' ) ) {
										esc_html_e( 'This rule is SUPPRESSED', 'sirsc' );
									} else {
										esc_html_e( 'This rule is ACTIVE', 'sirsc' );
									}
									echo ': ';

									// phpcs:disable
									if ( '**full**' === \SIRSC::$user_custom_rules[ $i ]['original'] ) {
										echo sprintf(
											// Translators: %1$s type, %2$s value, %3$s only.
											esc_html__( 'uploading images to a post that has %1$s as %2$s will generate only the %3$s sizes.', 'sirsc' ),
											'<b>' . \SIRSC::$user_custom_rules[ $i ]['type'] . '</b>',
											'<b>' . \SIRSC::$user_custom_rules[ $i ]['value'] . '</b>',
											'<b>' . implode( ', ', array_unique( \SIRSC::$user_custom_rules[ $i ]['only'] ) ) . '</b>'
										);
									} else {
										echo sprintf(
											// Translators: %1$s type, %2$s value, %3$s original, %4$s only.
											esc_html__( 'uploading images to a post that has %1$s as %2$s will force the original image to %3$s size and will generate only the %4$s sizes.', 'sirsc' ),
											'<b>' . \SIRSC::$user_custom_rules[ $i ]['type'] . '</b>',
											'<b>' . \SIRSC::$user_custom_rules[ $i ]['value'] . '</b>',
											'<b>' . \SIRSC::$user_custom_rules[ $i ]['original'] . '</b>',
											'<b>' . implode( ', ', array_unique( \SIRSC::$user_custom_rules[ $i ]['only'] ) ) . '</b>'
										);
									}
									echo '</div><br>';
									// phpcs:enable
								}
								?>

								<div class="rows three-columns mini-gaps no-shadow bg-trans">
									<?php
									$only = str_replace( '#ID#', '_user_custom_rule_' . $i . '_only_', $checks_ims );
									$only = str_replace( '#NAME#', '_user_custom_rule[' . $i . '][only][]', $only );
									$sel  = ( ! empty( \SIRSC::$user_custom_rules[ $i ]['only'] ) ) ? \SIRSC::$user_custom_rules[ $i ]['only'] : [ 'thumbnail', 'large' ];
									foreach ( $sel as $is ) {
										if ( ! empty( $class ) && substr_count( $class, 'row-use' ) ) {
											$only = str_replace(
												' value="' . $is . '"',
												' value="' . $is . '" checked="checked" class="row-use"',
												$only
											);
											$only = str_replace(
												' label-for="' . $is . '"',
												' label-for="' . $is . '" class="' . $class . '"',
												$only
											);
										}
									}

									echo $only; // phpcs:ignore
									?>
								</div>
							</div>
							<div class="span2 option-supress a-right" data-title="<?php esc_attr_e( 'Suppress', 'sirsc' ); ?>">
								<label>
									<input type="checkbox"
									name="_user_custom_rule[<?php echo (int) $i; ?>][suppress]"
									id="user_custom_rule_<?php echo (int) $i; ?>_suppress" <?php echo $supp; // phpcs:ignore ?>>
								</label>
							</div>
						</div>
					<?php endfor; ?>
					<div class="sirsc-feature rows no-top bg-secondary sirsc-sticky-bottom">
						<div class="a-right">
							<button type="button"
								class="button button-primary"
								name="sirsc-settings-advanced-rules"
								value="submit"
								data-sirsc-autosubmit="click">
								<?php esc_html_e( 'Save Settings', 'sirsc' ); ?>
							</button>
						</div>
					</div>

				</form>
			</div>
		</div>
	</div>
	<?php
}

/**
 * Add the custom column.
 *
 * @param array $columns The defined columns.
 * @return array
 */
function register_media_columns( $columns ) { // phpcs:ignore
	if ( ! empty( $columns ) ) {
		$before  = array_slice( $columns, 0, 2, true );
		$after   = array_slice( $columns, 2, count( $columns ) - 1, true );
		$columns = array_merge( $before, [ 'sirsc_buttons' => esc_html__( 'Details/Options', 'sirsc' ) ], $after );
	}
	return $columns;
}

/**
 * Output the custom column value.
 *
 * @param string  $column The current column.
 * @param integer $value  The current column value.
 * @return void
 */
function media_column_value( $column, $value ) { // phpcs:ignore
	if ( 'sirsc_buttons' === $column ) {
		global $post, $sirsc_column_summary;
		if ( ! empty( \SIRSC::$settings['listing_show_summary'] ) ) {
			$sirsc_column_summary = true;
		}
		if ( ! empty( $post ) && ! empty( $post->post_mime_type ) && substr_count( $post->post_mime_type, 'image/' ) ) {
			$extra_class = ( ! empty( \SIRSC::$settings['listing_tiny_buttons'] ) ) ? 'tiny' : '';
			echo append_image_generate_button( '', '', $post->ID, $extra_class ); // phpcs:ignore
			if ( ! empty( \SIRSC::$settings['listing_show_summary'] ) ) {
				\SIRSC\Helper\attachment_listing_summary( $post->ID );
			}
		}
	}
}
