<?php
/**
 * Adds AI crawler rules to the virtual robots.txt.
 *
 * @package NSW_AI_Visibility
 */

defined( 'ABSPATH' ) || exit;

/**
 * Robots.txt integration.
 *
 * Works with the virtual robots.txt WordPress generates. If a physical
 * robots.txt file exists at the site root, WordPress never runs this filter:
 * the admin screen warns about it.
 */
class NSW_AIV_Robots {

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_filter( 'robots_txt', array( __CLASS__, 'filter' ), 20, 2 );
	}

	/**
	 * Append a Disallow block for every blocked AI crawler.
	 *
	 * @param string $output Current robots.txt content.
	 * @param bool   $public Whether the site is set to be visible to search engines.
	 * @return string
	 */
	public static function filter( $output, $public ) {
		if ( ! $public ) {
			return $output;
		}

		$rules = self::rules();
		if ( '' === $rules ) {
			return $output;
		}

		return rtrim( $output ) . "\n\n" . $rules;
	}

	/**
	 * Build the AI crawler block of robots.txt.
	 *
	 * @return string
	 */
	public static function rules() {
		$settings = NSW_AIV_Settings::get();
		$blocked  = (array) $settings['blocked_bots'];

		if ( empty( $blocked ) ) {
			return '';
		}

		$lines = array( '# AI crawlers (NSW AI Visibility)' );
		foreach ( $blocked as $token ) {
			$lines[] = 'User-agent: ' . $token;
			$lines[] = 'Disallow: /';
			$lines[] = '';
		}

		return trim( implode( "\n", $lines ) ) . "\n";
	}

	/**
	 * Whether a physical robots.txt file overrides the virtual one.
	 *
	 * @return bool
	 */
	public static function has_physical_file() {
		return file_exists( trailingslashit( ABSPATH ) . 'robots.txt' );
	}
}
