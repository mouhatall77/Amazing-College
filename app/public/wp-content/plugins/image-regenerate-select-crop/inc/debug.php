<?php
/**
 * Debug functions for SIRSC.
 *
 * @package sirsc
 */

declare( strict_types=1 );

namespace SIRSC\Debug;

add_action( 'admin_menu', __NAMESPACE__ . '\\admin_menu', 20 );
add_action( 'sirsc_action_after_image_delete', __NAMESPACE__ . '\\debug_sirsc_action_after_image_delete' );
add_action( 'sirsc_attachment_images_ready', __NAMESPACE__ . '\\debug_sirsc_attachment_images_ready', 10, 2 );
add_action( 'sirsc_attachment_images_processed', __NAMESPACE__ . '\\debug_sirsc_attachment_images_processed', 10, 2 );
add_action( 'sirsc_image_file_deleted', __NAMESPACE__ . '\\debug_sirsc_image_file_deleted', 10, 2 );
add_action( 'sirsc_image_processed', __NAMESPACE__ . '\\debug_sirsc_image_processed', 10, 2 );
add_filter( 'sirsc_custom_upload_rule', __NAMESPACE__ . '\\debug_sirsc_custom_upload_rule', 10, 5 );
add_filter( 'sirsc_computed_metadata_after_upload', __NAMESPACE__ . '\\debug_sirsc_computed_metadata_after_upload', 10, 2 );

/**
 * Add the debug menu.
 *
 * @return void
 */
function admin_menu() {
	if ( ! empty( \SIRSC::$settings['enable_debug_log'] ) ) {
		add_submenu_page(
			'image-regenerate-select-crop-settings',
			__( 'Debug', 'sirsc' ),
			'<span class="dashicons dashicons-admin-generic"></span>' . __( 'Debug', 'sirsc' ),
			'manage_options',
			'sirsc-debug',
			__NAMESPACE__ . '\\sirsc_debug'
		);
	}
}

/**
 * Debug screen content.
 *
 * @return void
 */
function sirsc_debug() {
	if ( ! current_user_can( 'manage_options' ) ) {
		// Verify user capabilities in order to deny the access if the user does not have the capabilities.
		wp_die( esc_html__( 'Action not allowed.', 'sirsc' ) );
	}

	?>
	<div class="wrap sirsc-settings-wrap sirsc-feature">
		<?php \SIRSC\Admin\show_plugin_top_info(); ?>
		<?php \SIRSC\Admin\maybe_all_features_tab(); ?>
		<div class="sirsc-tabbed-menu-content">
			<div class="rows bg-secondary no-top">
				<div>
					<?php esc_html_e( 'You will see here the information collected while executing regenerate and cleanup actions. Please reset the logs periodically.', 'sirsc' ); ?>
				</div>
			</div>
		</div>

		<div class="rows three-columns bg-secondary has-gaps breakable">
			<div>
				<a class="button button-neutral f-right" onclick="resetLog( 'bulk' )"><?php esc_html_e( 'Reset log', 'sirsc' ); ?></a>
				<h2>
					<a class="button button-primary" onclick="refreshLog( 'bulk' )"><span class="dashicons dashicons-update-alt"></span></a>
					<?php esc_html_e( 'Bulk Actions Log', 'sirsc' ); ?>
				</h2>
				<p><?php esc_html_e( 'The bulk actions execution results can be seen below, the most recent actions are shown at the top of the list.', 'sirsc' ); ?></p>
				<div id="sirsc-log-bulk" class="code">
					<ol><?php echo wp_kses_post( log_read( 'bulk' ) ); ?></ol>
				</div>
			</div>

			<div>
				<a class="button button-neutral f-right" onclick="resetLog( 'tracer' )"><?php esc_html_e( 'Reset log', 'sirsc' ); ?></a>
				<h2>
					<a class="button button-primary" onclick="refreshLog( 'tracer' )"><span class="dashicons dashicons-update-alt"></span></a>
					<?php esc_html_e( 'Tracer log', 'sirsc' ); ?>
				</h2>
				<p><?php esc_html_e( 'The tracer log can be seen below, the most recent events are shown at the top of the list.', 'sirsc' ); ?></p>
				<div id="sirsc-log-tracer" class="code">
					<ol><?php echo wp_kses_post( log_read( 'tracer' ) ); ?></ol>
				</div>
			</div>

			<?php status(); ?>
		</div>
	</div>
	<?php
}

/**
 * Outputs the system status.
 *
 * @return void
 */
function status() {
	if ( ! class_exists( 'WP_Debug_Data' ) && file_exists( ABSPATH . 'wp-admin/includes/class-wp-debug-data.php' ) ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-debug-data.php';
	}

	if ( class_exists( 'WP_Debug_Data' ) ) {
		$info = \WP_Debug_Data::debug_data();
	}
	$allow = [
		'wp-core'           => [ 'version', 'site_language', 'timezone', 'home_url', 'site_url', 'permalink', 'https_status', 'multisite', 'environment_type', 'dotorg_communication' ],
		'wp-paths-sizes'    => [ 'wordpress_path', 'uploads_path', 'themes_path', 'plugins_path' ],
		'wp-active-theme'   => [ 'name', 'version', 'author', 'author_website', 'parent_theme', 'theme_features', 'theme_path', 'auto_update' ],
		'wp-parent-theme'   => [ 'name', 'version' ],
		'wp-plugins-active' => '*',
		'wp-media'          => '*',
		'wp-server'         => '*',
		'wp-database'       => [ 'extension', 'server_version', 'client_version' ],
		'wp-constants'      => '*',
		'wp-filesystem'     => '*',
	];

	$details = '';
	if ( ! empty( $info ) ) {
		foreach ( $info as $section => $item ) {
			if ( ! empty( $allow[ $section ] ) && ! empty( $item['fields'] ) ) {
				$details .= PHP_EOL . '### ' . esc_html( $item['label'] );
				if ( '*' === $allow[ $section ] ) {
					$keys = array_keys( $item['fields'] );
				} else {
					$keys = $allow[ $section ];
				}
				foreach ( $keys as $key ) {
					$details .= PHP_EOL . '- ' . esc_html( $item['fields'][ $key ]['label'] ) . ': ' . esc_html( $item['fields'][ $key ]['value'] );
				}
				$details .= PHP_EOL;
			}
		}

		$details = str_replace( $info['wp-paths-sizes']['fields']['wordpress_path']['value'], '{{ROOT}}', $details );
	}

	if ( ! empty( $details ) ) {
		?>
		<div class="span6">
			<h2><?php esc_html_e( 'Status/Debug', 'sirsc' ); ?></h2>
			<p><?php esc_html_e( 'Here are some details about your current instance and the services versions. These are useful for troubleshooting.', 'sirsc' ); ?></p>
			<textarea id="sirsc-sistem-status" class="code"><?php echo $details; //phpcs:ignore ?></textarea>
		</div>
		<?php
	}
}

/**
 * Debug action.
 *
 * @param  int $id Attachment id.
 * @return void
 */
function debug_sirsc_action_after_image_delete( $id ) { //phpcs:ignore
	tracer_log_write( 'DO ACTION <b>sirsc_action_after_image_delete</b> ( ' . (int) $id . ' )' );
}

/**
 * Debug action.
 *
 * @param  array $meta Attachment meta.
 * @param  int   $id   Attachment ID.
 * @return void
 */
function debug_sirsc_attachment_images_ready( $meta, $id ) { //phpcs:ignore
	tracer_log_write( 'DO ACTION <b>sirsc_attachment_images_ready</b> ( ' . wp_json_encode( [
		'meta' => '...',
		'id'   => $id,
	], true ) . ' )' );
}

/**
 * Debug action.
 *
 * @param  array $meta Attachment meta.
 * @param  int   $id   Attachment ID.
 * @return void
 */
function debug_sirsc_attachment_images_processed( $meta, $id ) { //phpcs:ignore
	tracer_log_write( 'DO ACTION <b>sirsc_attachment_images_processed</b> ( ' . wp_json_encode( [
		'meta' => '...',
		'id'   => $id,
	], true ) . ' )' );
}

/**
 * Debug action.
 *
 * @param  mixed $extra Extra info.
 * @return void
 */
function debug_sirsc_doing_sirsc( $extra ) { //phpcs:ignore
	if ( ! empty( $extra ) ) {
		tracer_log_write( 'DO ACTION <b>sirsc_doing_sirsc</b> ( ' . wp_json_encode( $extra ) . ' )' );
	} else {
		tracer_log_write( 'DO ACTION <b>sirsc_doing_sirsc</b>' );
	}
}

/**
 * Debug action.
 *
 * @param  int    $id   Attachment ID.
 * @param  string $file Attachment file.
 * @return void
 */
function debug_sirsc_image_file_deleted( $id, $file ) { //phpcs:ignore
	tracer_log_write( 'DO ACTION <b>sirsc_image_file_deleted</b> ( ' . wp_json_encode( [
		'id'   => $id,
		'file' => $file,
	], true ) . ' )' );
}

/**
 * Debug action.
 *
 * @param  int    $id        Attachment ID.
 * @param  string $size_name Image size name.
 * @return void
 */
function debug_sirsc_image_processed( $id, $size_name ) { //phpcs:ignore
	tracer_log_write( 'DO ACTION <b>sirsc_image_processed</b> ( ' . wp_json_encode( [
		'id'        => $id,
		'size_name' => $size_name,
	], true ) . ' )' );
}

/**
 * Debug filter.
 *
 * @param  array  $settings    Custom settings.
 * @param  int    $id          Attachment ID.
 * @param  string $type        Post type.
 * @param  int    $parent_id   Parent ID.
 * @param  string $parent_type Parent type.
 * @return array
 */
function debug_sirsc_custom_upload_rule( $settings, $id, $type, $parent_id, $parent_type ) { //phpcs:ignore
	tracer_log_write( 'APPLY FILTER <strong>sirsc_custom_upload_rule</strong> ( ' . wp_json_encode( [
		'settings'    => '...',
		'id'          => $id,
		'type'        => $type,
		'parent_id'   => $parent_id,
		'parent_type' => $parent_type,
	], true ) . ' )' );

	return $settings;
}

/**
 * Debug filter.
 *
 * @param  array $meta Image meta.
 * @param  int   $id   Attachment ID.
 * @return array
 */
function debug_sirsc_computed_metadata_after_upload( $meta, $id ) { //phpcs:ignore
	tracer_log_write( 'APPLY FILTER <strong>sirsc_computed_metadata_after_upload</strong> ( ' . wp_json_encode( [
		'meta' => '...',
		'id'   => $id,
	], true ) . ' )' );
	return $meta;
}

/**
 * File system instance.
 *
 * @return object
 */
function fs() { //phpcs:ignore
	global $wp_filesystem;
	require_once ABSPATH . '/wp-admin/includes/file.php';
	\WP_Filesystem();
	return $wp_filesystem;
}

/**
 * Init a log file.
 *
 * @param  string $name Log type.
 * @return void
 */
function log_init( string $name = 'tracer' ) : void {
	$fs   = fs();
	$path = SIRSC_PLUGIN_DIR . 'log';
	$file = $path . '/' . esc_attr( $name ) . '.log';
	if ( ! is_file( $file ) ) {
		$fs->mkdir( $path );
		$fs->touch( $file );
	}
}

/**
 * Get a log file content.
 *
 * @param  string $name Log type.
 * @return string
 */
function log_read( string $name = 'tracer' ) : string {
	$fs   = fs();
	$path = SIRSC_PLUGIN_DIR . 'log';
	$file = $path . '/' . esc_attr( $name ) . '.log';
	if ( is_file( $file ) ) {
		return $fs->get_contents( $file );
	}
	return '';
}

/**
 * Delete a log file content.
 *
 * @param string $name Log type.
 */
function log_delete( string $name = 'tracer' ) {
	$fs   = fs();
	$path = SIRSC_PLUGIN_DIR . 'log';
	$file = $path . '/' . esc_attr( $name ) . '.log';
	if ( is_file( $file ) ) {
		$fs->delete( $file );
	}
}

/**
 * Prepare content to be put to log.
 *
 * @param  mixed $ob Content to be put to log.
 * @return string
 */
function log_prepare_content( $ob ) { // phpcs:ignore
	if ( ! empty( $ob ) ) {
		$upl_dir  = wp_upload_dir();
		$path     = $upl_dir['basedir'];
		$path     = str_replace( chr( 93 ), '/', $path );
		$ob_text  = '<li><em>' . gmdate( 'Y-m-d H:i:s' ) . '</em>' . PHP_EOL;
		$ob_text .= ( ! is_scalar( $ob ) ) ? wp_json_encode( $ob ) : $ob; //phpcs:ignore
		$ob_text .= '</li>' . PHP_EOL;
		$ob_text  = str_replace( '|', ' | ', $ob_text );
		$ob_text  = str_replace( '\/', '/', $ob_text );
		$ob_text  = str_replace( $path, '{{UPLOADS}}', $ob_text );

		return $ob_text;
	}
	return '';
}

/**
 * Write content to log.
 *
 * @param mixed $ob Content to be put to log.
 */
function main_log_write( $ob ) { //phpcs:ignore
	if ( ! empty( $ob ) ) {
		log_init( 'main' );
		$fs   = fs();
		$file = SIRSC_PLUGIN_DIR . 'log/main.log';
		$text = log_read( 'main' );
		$fs->put_contents( $file, $ob . $text );
	}
}

/**
 * Write content to log.
 *
 * @param mixed $ob Content to be put to log.
 */
function bulk_log_write( $ob ) { //phpcs:ignore
	if ( ! empty( $ob ) ) {
		log_init( 'bulk' );
		$fs   = fs();
		$file = SIRSC_PLUGIN_DIR . 'log/bulk.log';
		$text = log_read( 'bulk' );
		$fs->put_contents( $file, log_prepare_content( $ob ) . $text );
	}
}

/**
 * Write content to log.
 *
 * @param mixed $ob Content to be put to log.
 */
function tracer_log_write( $ob ) { //phpcs:ignore
	if ( ! empty( $ob ) ) {
		log_init( 'tracer' );
		$fs   = fs();
		$file = SIRSC_PLUGIN_DIR . 'log/tracer.log';
		$text = log_read( 'tracer' );
		$fs->put_contents( $file, log_prepare_content( $ob ) . $text );
	}
}
