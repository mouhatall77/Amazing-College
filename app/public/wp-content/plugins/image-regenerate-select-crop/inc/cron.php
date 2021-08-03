<?php
/**
 * Cron functions for SIRSC.
 *
 * @package sirsc
 */

declare( strict_types=1 );

namespace SIRSC\Cron;

define( 'SIRSC_JOBS_DB_VER', 1.0 );

add_filter( 'cron_schedules', __NAMESPACE__ . '\\custom_cron_frequency' ); // phpcs:ignore
add_action( 'shutdown', __NAMESPACE__ . '\\cron_sanity_check' );
add_action( 'init', __NAMESPACE__ . '\\check_cron_scheduled_tasks' );
add_action( 'init', __NAMESPACE__ . '\\hookup_tasks', 60 );

/**
 * Hookup the custom tasks.
 *
 * @return void
 */
function hookup_tasks() {
	$tasks = custom_list_of_tasks();
	if ( ! empty( $tasks ) ) {
		foreach ( $tasks as $hook => $args ) {
			add_action( $hook, function() use ( $hook, $args ) { //phpcs:ignore
				$args = filter_the_args( $args );
				run_task( $hook, $args );
			} );
		}
	}
}

/**
 * Run a custom task.
 *
 * @param  string $hook Task hook.
 * @param  array  $args The cron task arguments.
 * @return void
 */
function run_task( $hook = '', $args = [] ) { //phpcs:ignore
	ob_start();

	switch ( $args['name'] ) {
		case 'regenerate_image_sizes_on_request':
			\SIRSC\Helper\regenerate_image_sizes_on_request( 'continue', $args['args']['size'], $args['args']['cpt'] );
			break;

		case 'cleanup_image_sizes_on_request':
			\SIRSC\Helper\cleanup_image_sizes_on_request( 'continue', $args['args']['size'], $args['args']['cpt'] );
			break;

		case 'raw_cleanup_on_request':
			\SIRSC\Helper\raw_cleanup_on_request( 'continue', $args['args']['type'], $args['args']['cpt'] );
			break;

		case 'sirsc_adon_ui_execute_assess':
			if ( class_exists( '\SIRSC_Adons_Uploads_Inspector' ) ) {
				if ( ! defined( 'DOING_CRON' ) ) {
					define( 'DOING_CRON', true );
				}
				\SIRSC_Adons_Uploads_Inspector::execute_assess();
			}
			break;

		default:
			break;
	}

	$info = get_hook_info( $hook );
	if ( empty( $info['info']['total'] ) ) {
		trigger_task_unschedule( $hook );
	}
	ob_get_clean();
}

/**
 * Register the custom frequency for tasks.
 *
 * @param  array $schedules The avalable frequencies of tasks.
 * @return array
 */
function custom_cron_frequency( array $schedules ) : array {
	if ( ! isset( $schedules['every_minute'] ) ) {
		$schedules['every_minute'] = [
			'interval' => 1 * 60,
			'display'  => __( 'Every minute', 'sirsc' ),
		];
	}

	return $schedules;
}

/**
 * Cron sanity check.
 *
 * @return void
 */
function cron_sanity_check() {
	$checked = get_transient( 'sirsc_cron_sanity_check' );
	if ( false === $checked ) {
		maybe_schedule_tasks();
		set_transient( 'sirsc_cron_sanity_check', time(), 1 * HOUR_IN_SECONDS );
	}
}

/**
 * This method is intended for scheduling the cron task only once, not at each runtime.
 * To do so, we set in the database an option we check against, and only schedule the daily
 * cron task if that is different.
 *
 * Doing so, we can then force the engine to re-create the cron task later, if needed,
 * by changing the option value.
 *
 * @param  bool $force True to force the reschedule of the cron job regardless of the context.
 * @return void
 */
function check_cron_scheduled_tasks( bool $force = false ) {
	$db_version = (float) get_site_option( 'sirsc_jobs_db_ver', '' );
	if ( (float) SIRSC_JOBS_DB_VER !== $db_version || true === $force ) {
		// Update the database option, so that the rest of the execution to take place only if needed.
		update_site_option( 'sirsc_jobs_db_ver', SIRSC_JOBS_DB_VER );
		maybe_remove_tasks();
		maybe_schedule_tasks();
	}
}

/**
 * Remove the custom event from the cron task.
 *
 * @return void
 */
function maybe_remove_tasks() {
	$tasks = custom_list_of_tasks();
	foreach ( $tasks as $task => $info ) {
		\wp_unschedule_hook( $task );
		\wp_clear_scheduled_hook( $task, $info['args'] );
	}

	$all = \_get_cron_array();
	if ( ! empty( $all ) ) {
		foreach ( $all as $time => $event ) {
			$hook = ( is_array( $event ) ) ? array_keys( $event ) : [];
			$hook = ( is_array( $hook ) ) ? reset( $hook ) : '';
			if ( substr_count( $hook, 'SIRSC-' ) ) {
				\wp_unschedule_hook( $hook );
				\wp_clear_scheduled_hook( $hook );
			}
		}
	}
}

/**
 * Filter the usable args.
 *
 * @param  array $args The cron task arguments.
 * @return array
 */
function filter_the_args( $args = [] ) { // phpcs:ignore
	if ( isset( $args['remaining'] ) ) {
		unset( $args['remaining'] );
	}

	return $args;
}

/**
 * Assess if the custom event is scheduled in the cron taks.
 *
 * @param  string $type The cron task type.
 * @param  array  $args The cron task arguments.
 * @return int
 */
function is_scheduled( string $type = '', array $args = [] ) : int {
	$args = filter_the_args( $args );
	return (int) wp_next_scheduled( $type, $args );
}

/**
 * Schedule a custom task.
 *
 * @param  string $hook Hook handle.
 * @param  string $name Function to run.
 * @param  array  $args Function arguments.
 * @return void
 */
function trigger_task_schedule( $hook, $name = '', $args = [] ) { //phpcs:ignore
	$opt = get_option( 'sirsc_jobs_list', [] );
	if ( empty( $opt[ $hook ] ) ) {
		$opt[ $hook ] = [
			'name'  => $name,
			'args'  => $args,
			'start' => time(),
		];
		update_option( 'sirsc_jobs_list', $opt );
		\wp_schedule_event( time(), 'every_minute', $hook, $args );
		\SIRSC\Debug\bulk_log_write( 'CRON TASK <b>' . $hook . '</b> ' . $name . ' (' . wp_json_encode( $args ) . ' ) <div>' . __( 'the cron task has been scheduled' ) . '</div>' );

		get_hook_info( $hook, true );
	}
}

/**
 * Unschedule a custom task.
 *
 * @param  string $hook Hook handle.
 * @return void
 */
function trigger_task_unschedule( string $hook ) {
	$opt = get_option( 'sirsc_jobs_list', [] );
	if ( ! empty( $opt[ $hook ] ) ) {
		get_hook_info( $hook, true );

		$name = ( isset( $opt[ $hook ]['name'] ) ) ? $opt[ $hook ]['name'] : '';
		$args = ( isset( $opt[ $hook ]['args'] ) ) ? $opt[ $hook ]['args'] : [];
		$diff = time() - $opt[ $hook ]['start'];
		$h    = floor( $diff / 3600 );
		$m    = (int) gmdate( 'i', $diff % 3600 );
		$s    = (int) gmdate( 's', $diff % 3600 );
		$time = [];
		if ( ! empty( $h ) ) {
			$time[] = $h . ' ' . __( 'hours', 'sirsc' );
		}
		if ( ! empty( $m ) ) {
			$time[] = $m . ' ' . __( 'minutes', 'sirsc' );
		}
		if ( ! empty( $s ) ) {
			$time[] = $s . ' ' . __( 'seconds', 'sirsc' );
		}

		$time = implode( ', ', $time );
		\SIRSC\Debug\bulk_log_write( 'CRON TASK <b>' . $hook . '</b> ' . $name
			. ' (' . wp_json_encode( $args ) . ' ) <div>'
			// Translators: %s - duration.
			. sprintf( __( 'the cron task has been unscheduled and run for %s.' ), $time )
			. '</div>'
		);

		\wp_unschedule_hook( $hook );
		if ( empty( $args ) ) {
			\wp_clear_scheduled_hook( $hook );
		} else {
			\wp_clear_scheduled_hook( $hook, $args );
		}

		unset( $opt[ $hook ] );
		update_option( 'sirsc_jobs_list', $opt );
	}
}

/**
 * Get hook for action.
 *
 * @param  string $name Function name.
 * @param  array  $args Function arguments.
 * @return string
 */
function get_hook( string $name = '', array $args = [] ) : string {
	$args  = filter_the_args( $args );
	$info  = ( substr_count( $name, 'raw' ) ) ? 'RAW' : 'BULK';
	$info .= ( substr_count( $name, 'regenerate' ) ) ? 'R' : 'C';
	$hook  = 'SIRSC-' . $info . md5( wp_json_encode( $args ) );
	return $hook;
}

/**
 * Get hook info.
 *
 * @param  string $hook  Hook handle.
 * @param  bool   $reset Reset counters.
 * @return array
 */
function get_hook_info( string $hook = '', bool $reset = false ) : array {
	$info = [
		'info' => [],
		'text' => '',
		'data' => [],
	];

	$tasks = custom_list_of_tasks();
	if ( ! empty( $tasks[ $hook ] ) ) {
		$name = $tasks[ $hook ]['name'];
		$args = $tasks[ $hook ]['args'];

		$info['data'] = $tasks[ $hook ];

		switch ( $name ) {
			case 'regenerate_image_sizes_on_request':
				if ( true === $reset ) {
					// Start from the beginning of the list.
					\SIRSC\Helper\reset_bulk_action_last_id( $args['size'], $args['cpt'] );
				}
				$limit        = \SIRSC::$settings['cron_batch_regenerate'];
				$info['info'] = \SIRSC\Helper\bulk_action_query( $args['size'], $args['cpt'], $limit );
				$info['text'] = __( 'REMAINING TO REGENERATE', 'sirsc' ) . ': <b>' . (int) $info['info']['total'] . '</b>';
				break;

			case 'cleanup_image_sizes_on_request':
				if ( true === $reset ) {
					// Start from the beginning of the list.
					\SIRSC\Helper\reset_bulk_action_last_id( $args['size'], $args['cpt'], 'c-' );
				}
				$limit        = \SIRSC::$settings['cron_batch_cleanup'];
				$info['info'] = \SIRSC\Helper\bulk_action_query( $args['size'], $args['cpt'], $limit, 'c-' );
				$info['text'] = __( 'REMAINING TO CLEAN UP', 'sirsc' ) . ': <b>' . (int) $info['info']['total'] . '</b>';
				break;

			case 'raw_cleanup_on_request':
				if ( true === $reset ) {
					// Start from the beginning of the list.
					\SIRSC\Helper\reset_bulk_action_last_id( $args['type'], $args['cpt'], 'rc-' );
				}
				$limit        = \SIRSC::$settings['cron_batch_cleanup'];
				$info['info'] = \SIRSC\Helper\bulk_action_query( $args['type'], $args['cpt'], $limit, 'rc-' );
				$info['text'] = __( 'REMAINING TO CLEAN UP', 'sirsc' ) . ': <b>' . (int) $info['info']['total'] . '</b>';
				break;

			case 'sirsc_adon_ui_execute_assess':
				if ( class_exists( 'SIRSC_Adons_Uploads_Inspector' ) ) {
					if ( true === $reset ) {
						// Start from the beginning of the list.
						\SIRSC_Adons_Uploads_Inspector::start_over();
						\SIRSC_Adons_Uploads_Inspector::reset_assess_counters();
					}

					$total = \SIRSC_Adons_Uploads_Inspector::compute_remaining_to_process();

					$info['info'] = [ 'total' => $total ];

					ob_start();
					\SIRSC_Adons_Uploads_Inspector::compute_progress_bar();
					$text = ob_get_clean();
					$text = str_replace( 'span2"', 'first" style="padding: 0"', $text );
					$text = str_replace( 'span1"', 'second" style="line-height:16px; text-align: left; padding: 0 0 0 10px"', $text );
					$text = str_replace( ' three-columns', '', $text );

					$info['text'] = $text;
				}
				break;

			default:
				break;
		}
	}
	return $info;
}

/**
 * Assess custom task by name and arguments.
 *
 * @param  string $name Function name.
 * @param  array  $args Function arguments.
 * @return void
 */
function assess_task( string $name = '', array $args = [] ) {
	if ( isset( $args['start'] ) ) {
		unset( $args['start'] );
	}
	?>
	<div class="sirsc_options-title">
		<h2><?php esc_html_e( 'Cron Task', 'sirsc' ); ?></h2>
		<a class="sirsc-button close-button" onclick="sirscCloseLightbox();">
			<span class="dashicons dashicons-dismiss"></span>
		</a>
	</div>
	<?php
	$hook = get_hook( $name, $args );
	if ( ! is_scheduled( $hook, $args ) ) {
		trigger_task_schedule( $hook, $name, $args );
		$message = __( 'The action has been scheduled and the cron task will run in the background. You can close the dialog box.', 'sirsc' );
	} else {
		$message = __( 'The action is currently in progress, the cron task runs in the background. You can close the dialog box.', 'sirsc' );
	}

	$info = get_hook_info( $hook );
	?>
	<div class="inside as-target sirsc-bulk-action sirsc-bulk-regen">
		<p class="sirsc-message success"><?php echo wp_kses_post( $message ); ?></p>
		<div class="sirsc-message info"><?php echo wp_kses_post( $info['text'] ); ?></div>
		<p><?php esc_html_e( 'The cron task is scheduled to run every minute, please be patient until the task finishes.', 'sirsc' ); ?> <?php esc_html_e( 'If you want to cancel the remaining execution and unshedule the task, click the button below.', 'sirsc' ); ?></p>
		<a class="button" onclick="sirscCancelCronTask( '<?php echo esc_attr( $hook ); ?>' )"><?php esc_html_e( 'Cancel execution', 'sirsc' ); ?></a>
	</div>
	<?php
	if ( ! empty( $info['data']['name'] ) ) {
		if ( 'cleanup_image_sizes_on_request' === $info['data']['name'] ) {
			echo \SIRSC\Helper\document_ready_js( 'sirscHideCleanupButton( \'' . $info['data']['args']['size'] . '\' );' ); //phpcs:ignore
		} elseif ( 'regenerate_image_sizes_on_request' === $info['data']['name'] ) {
			echo \SIRSC\Helper\document_ready_js( 'sirscHideRegenerateButton( \'' . $info['data']['args']['size'] . '\' );' ); //phpcs:ignore
		} elseif ( 'raw_cleanup_on_request' === $info['data']['name'] ) {
			echo \SIRSC\Helper\document_ready_js( 'sirscHideRawButton( \'' . $info['data']['args']['type'] . '\' );' ); //phpcs:ignore
		}
	}
}

/**
 * Cancel custom task dialog.
 *
 * @param  string $hook Hook handle.
 * @return void
 */
function cancel_task( string $hook = '' ) {
	$info = get_hook_info( $hook );
	trigger_task_unschedule( $hook );
	?>
	<div class="sirsc_options-title">
		<h2><?php esc_html_e( 'Cron Task', 'sirsc' ); ?></h2>
		<a class="sirsc-button close-button" onclick="sirscCloseLightbox();">
			<span class="dashicons dashicons-dismiss"></span>
		</a>
	</div>
	<div class="inside as-target sirsc-bulk-action sirsc-bulk-regen">
		<p class="sirsc-message success"><?php esc_html_e( 'The scheduled task has been canceled.', 'sirsc' ); ?></p>
	</div>
	<?php
	if ( ! empty( $info['data']['name'] ) ) {
		if ( 'cleanup_image_sizes_on_request' === $info['data']['name'] ) {
			echo \SIRSC\Helper\document_ready_js( 'sirscShowCleanupButton( \'' . $info['data']['args']['size'] . '\' );' ); //phpcs:ignore
		} elseif ( 'regenerate_image_sizes_on_request' === $info['data']['name'] ) {
			echo \SIRSC\Helper\document_ready_js( 'sirscShowRegenerateButton( \'' . $info['data']['args']['size'] . '\' );' ); //phpcs:ignore
		} elseif ( 'raw_cleanup_on_request' === $info['data']['name'] ) {
			echo \SIRSC\Helper\document_ready_js( 'sirscShowRawButton( \'' . $info['data']['args']['type'] . '\' );' ); //phpcs:ignore
		}
	}
}

/**
 * Returns the cleaned list of custom cron tasks.
 *
 * @return array
 */
function custom_list_of_tasks() : array {
	$opt = get_option( 'sirsc_jobs_list', [] );
	if ( ! empty( $opt ) ) {
		$ini = $opt;
		foreach ( $opt as $hook => $info ) {
			if ( empty( $info['args']['size'] ) && empty( $info['args']['type'] ) ) {
				if ( 'sirsc_adon_ui_execute_assess' !== $info['name'] ) {
					unset( $opt[ $hook ] );
				}
			} else {
				if ( empty( $info['args'] ) ) {
					if ( ! is_scheduled( $hook ) ) {
						unset( $opt[ $hook ] );
					}
				} else {
					if ( ! is_scheduled( $hook, $info['args'] ) ) {
						unset( $opt[ $hook ] );
					}
				}
			}
		}
		if ( $ini !== $opt ) {
			update_option( 'sirsc_jobs_list', $opt );
		}
	}
	return $opt;
}

/**
 * Maybe schedule tasks.
 *
 * @return void
 */
function maybe_schedule_tasks() {
	$tasks = custom_list_of_tasks();
	if ( ! empty( $tasks ) ) {
		foreach ( $tasks as $hook => $attr ) {
			if ( empty( $attr['args'] ) ) {
				if ( ! is_scheduled( $hook ) ) {
					\wp_schedule_event( time(), 'every_minute', $hook );
				}
			} else {
				if ( ! is_scheduled( $hook, $attr['args'] ) ) {
					\wp_schedule_event( time(), 'every_minute', $hook, $attr['args'] );
				}
			}
		}
	}
}
