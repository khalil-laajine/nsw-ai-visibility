<?php
/**
 * Settings storage and defaults.
 *
 * @package NSW_AI_Visibility
 */

defined( 'ABSPATH' ) || exit;

/**
 * Read and sanitize the plugin settings.
 */
class NSW_AIV_Settings {

	/**
	 * Default settings.
	 *
	 * By default every bot is allowed: blocking is an explicit choice.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'blocked_bots'  => array(),
			'llms_enabled'  => 1,
			'llms_intro'    => '',
			'llms_posts'    => 10,
			'llms_manual'   => '',
			'track_enabled' => 1,
			'retention'     => 90,
		);
	}

	/**
	 * Current settings merged with defaults.
	 *
	 * @return array
	 */
	public static function get() {
		$saved = get_option( NSW_AIV_OPTION, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
	}

	/**
	 * Sanitize the settings form before saving.
	 *
	 * @param mixed $input Raw form input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		$known = array_keys( NSW_AIV_Bots::all() );
		$clean = self::defaults();

		$blocked               = isset( $input['blocked_bots'] ) ? (array) $input['blocked_bots'] : array();
		$clean['blocked_bots'] = array_values( array_intersect( $known, array_map( 'sanitize_text_field', $blocked ) ) );

		$clean['llms_enabled']  = empty( $input['llms_enabled'] ) ? 0 : 1;
		$clean['llms_intro']    = isset( $input['llms_intro'] ) ? sanitize_textarea_field( $input['llms_intro'] ) : '';
		$clean['llms_posts']    = isset( $input['llms_posts'] ) ? min( 50, max( 0, absint( $input['llms_posts'] ) ) ) : 10;
		$clean['llms_manual']   = isset( $input['llms_manual'] ) ? sanitize_textarea_field( $input['llms_manual'] ) : '';
		$clean['track_enabled'] = empty( $input['track_enabled'] ) ? 0 : 1;
		$clean['retention']     = isset( $input['retention'] ) ? min( 365, max( 7, absint( $input['retention'] ) ) ) : 90;

		return $clean;
	}
}
