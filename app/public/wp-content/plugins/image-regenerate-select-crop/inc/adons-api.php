<?php
/**
 * Description: The adons API component of the Image Regenerate & Select Crop plugin.
 *
 * @package sirsc
 */

/**
 * Adons API class for SIRSC plugin.
 */
class SIRSC_Adons_API {
	const PLUGIN_PREFIX     = 'sirsc-adons-api';
	const PLUGIN_API_URL    = 'https://iuliacazan.ro/wp-admin/admin-ajax.php';
	const PLUGIN_STORE_CODE = 'g8e27lE005UMhzK';

	/**
	 * Class instance
	 *
	 * @var object
	 */
	private static $instance;

	/**
	 * Get the class current instance.
	 *
	 * @return object
	 */
	public static function get_instance() { //phpcs:ignore
		if ( ! self::$instance ) {
			self::$instance = new SIRSC_Adons_API();
		}
		return self::$instance;
	}

	/**
	 * Class constructor
	 *
	 * @return void
	 */
	public function __construct() {
		self::init();
	}

	/**
	 * The class init.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'http_request_args', [ get_called_class(), 'bypass_curl_args_local' ], 10, 2 );
	}

	/**
	 * Bypass cUrl certificate check.
	 *
	 * @param  array  $r   Arguments.
	 * @param  string $url Url.
	 * @return array
	 */
	public static function bypass_curl_args_local( $r, $url ) { //phpcs:ignore
		if ( substr_count( $url, self::PLUGIN_API_URL ) ) {
			$r['sslverify'] = false;
		}
		return $r;
	}

	/**
	 * Make an API call.
	 *
	 * @param  string $action API action.
	 * @param  array  $args   Call arguments.
	 * @return boolean|object
	 */
	public static function do_api_call( $action, $args = [] ) { //phpcs:ignore
		$result  = false;
		$default = [
			'action'     => $action,
			'store_code' => self::PLUGIN_STORE_CODE,
			'domain'     => get_site_url(),
		];

		$response = wp_remote_post(
			self::PLUGIN_API_URL . '?action=' . $action,
			[
				'method'  => 'POST',
				'timeout' => 120,
				'body'    => array_merge( $default, $args ),
			]
		);
		if ( ! is_wp_error( $response ) ) {
			$data = wp_remote_retrieve_body( $response );
			if ( is_wp_error( $data ) ) {
				$result = false;
			} else {
				$result = (object) json_decode( $data );
			}
		}
		return $result;
	}

	/**
	 * Deal with errors.
	 *
	 * @param  string $slug   The extension slug.
	 * @param  mixed  $errors Maybe errors.
	 * @return void
	 */
	public static function deal_with_errors( $slug, $errors ) { //phpcs:ignore
		$error = '';
		if ( ! empty( $errors ) ) {
			$error  = 'ERROR: ';
			$error .= ( ! empty( $errors->license_key ) ) ? implode( ' ', $errors->license_key ) : '';
			$error .= ( ! empty( $error ) ) ? ' ' : '';
			$error .= ( ! empty( $errors->activation_id ) ) ? implode( ' ', $errors->activation_id ) : '';
			$error  = '<div class="sirsc-message error">' . trim( $error ) . '</div>';
		}
		self::update_adon_property( $slug, 'key_message', trim( $error ) );
		self::update_adon_property( $slug, 'activation_id', '' );
		self::update_adon_property( $slug, 'activation_response', '' );
		self::update_adon_property( $slug, 'available', false );
		self::update_adon_property( $slug, 'active', false );
		delete_transient( 'sirsc-adon-check-' . $slug );
	}

	/**
	 * Activate a license key.
	 *
	 * @param  string $slug The extension slug.
	 * @param  string $sku  The extension SKU.
	 * @param  string $key  The extension license_key.
	 * @return void
	 */
	public static function activate_license_key( $slug, $sku, $key ) { //phpcs:ignore
		self::update_adon_property( $slug, 'license_key', $key );
		$rez = self::do_api_call(
			'license_key_activate',
			[
				'sku'         => $sku,
				'license_key' => $key,
			]
		);
		if ( ! empty( $rez->data ) ) {
			if ( ! empty( $rez->data->activation_id ) ) {
				self::update_adon_property( $slug, 'activation_id', $rez->data->activation_id );
				self::update_adon_property( $slug, 'activation_response', $rez->data );
				if ( ! empty( $rez->data->status ) && 'active' === $rez->data->status ) {
					self::update_adon_property( $slug, 'available', true );
					self::update_adon_property( $slug, 'key_message', '<div class="sirsc-message success">' . $rez->message . '</div>' );
				} else {
					self::update_adon_property( $slug, 'key_message', '<div class="sirsc-message warning">' . $rez->message . '</div>' );
				}
			}
		}

		if ( ! empty( $rez->errors ) ) {
			self::deal_with_errors( $slug, $rez->errors );
		}

		delete_transient( 'sirsc-adon-check-' . $slug );
	}

	/**
	 * Validate a license key.
	 *
	 * @param  string $slug The extension slug.
	 * @param  string $sku  The extension SKU.
	 * @param  string $key  The extension license_key.
	 * @param  string $id   The activation ID.
	 * @return void
	 */
	public static function validate_license_key( $slug, $sku, $key, $id ) { //phpcs:ignore
		if ( ! empty( $id ) ) {
			$rez = self::do_api_call(
				'license_key_validate',
				[
					'sku'           => $sku,
					'license_key'   => $key,
					'activation_id' => $id,
				]
			);
			if ( ! empty( $rez->data ) ) {
				if ( ! empty( $rez->data->status ) && 'active' === $rez->data->status ) {
					self::update_adon_property( $slug, 'available', true );
					self::update_adon_property( $slug, 'key_message', '<div class="sirsc-message success">' . $rez->message . '</div>' );
					return;
				}
			}
			if ( ! empty( $rez->errors ) ) {
				self::deal_with_errors( $slug, $rez->errors );
			}
		}
	}

	/**
	 * Deactivate a license key.
	 *
	 * @param  string $slug The extension slug.
	 * @param  string $sku  The extension SKU.
	 * @param  string $key  The extension license_key.
	 * @param  string $id   The activation ID.
	 * @return void
	 */
	public static function deactivate_license_key( $slug, $sku, $key, $id ) { //phpcs:ignore
		$rez = self::do_api_call(
			'license_key_deactivate',
			[
				'sku'           => $sku,
				'license_key'   => $key,
				'activation_id' => $id,
			]
		);
	}

	/**
	 * Update adon property.
	 *
	 * @param  string $slug  The extension slug.
	 * @param  string $prop  The extension property.
	 * @param  string $value The extension property value.
	 * @return void
	 */
	public static function update_adon_property( $slug, $prop, $value ) { //phpcs:ignore
		$all = SIRSC_Adons::$adons;
		if ( ! empty( $all[ $slug ] ) ) {
			$all[ $slug ][ $prop ] = $value;
		}
		update_option( 'sirsc_adons_list', $all );
		SIRSC_Adons::detect_adons();
	}
}

// Initialize the class.
SIRSC_Adons_API::get_instance();
