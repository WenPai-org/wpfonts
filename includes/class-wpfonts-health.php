<?php
/**
 * Server-side Windfonts API key check.
 *
 * The key is sent only as a request header to the fixed status URL.
 * It is not written into the URL, the response we store, or the HTML.
 *
 * @package WPFonts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interprets GET /api/key/status.
 */
class WPFonts_Health {

	/**
	 * Map an HTTP result to valid, invalid, or unreachable.
	 *
	 * A transport or server failure is unreachable, not an invalid key.
	 * missing_api_key means the header did not arrive; that is also not a
	 * verdict that the saved key itself is wrong.
	 *
	 * @param int    $code HTTP status. 0 when there was no response.
	 * @param string $body Response body. May be empty or not JSON.
	 * @return string valid|invalid|unreachable
	 */
	public static function interpret( $code, $body ) {
		$code  = (int) $code;
		$error = '';
		if ( is_string( $body ) && '' !== $body ) {
			$json = json_decode( $body, true );
			if ( is_array( $json ) && isset( $json['errorCode'] ) ) {
				$error = (string) $json['errorCode'];
			}
		}

		if ( in_array( $error, array( 'invalid_api_key', 'invalid_api_key_format' ), true ) ) {
			return 'invalid';
		}
		if ( 'missing_api_key' === $error ) {
			return 'unreachable';
		}
		if ( $code >= 200 && $code < 300 ) {
			return 'valid';
		}
		if ( 401 === $code || 403 === $code ) {
			return 'invalid';
		}
		return 'unreachable';
	}

	/**
	 * Ask Windfonts whether this key is accepted.
	 *
	 * @param string $key API key. Not logged.
	 * @return string valid|invalid|unreachable
	 */
	public static function query( $key ) {
		$key = (string) $key;
		if ( '' === $key || ! function_exists( 'wp_remote_get' ) ) {
			return 'unreachable';
		}

		$response = wp_remote_get(
			WPFonts_Link::KEY_STATUS_URL,
			array(
				'timeout'             => 8,
				'redirection'         => 0,
				'limit_response_size' => 8192,
				'headers'             => array(
					'Accept'    => 'application/json',
					'X-API-Key' => $key,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return 'unreachable';
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = (string) wp_remote_retrieve_body( $response );
		return self::interpret( $code, $body );
	}
}
