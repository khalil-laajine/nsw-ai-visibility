<?php
/**
 * WP-CLI commands.
 *
 * @package NSW_AI_Visibility
 */

defined( 'ABSPATH' ) || exit;

/**
 * Inspect AI crawler activity from the command line.
 *
 * ## EXAMPLES
 *
 *     wp ai-visibility stats --days=7
 *     wp ai-visibility pages --format=csv > ai-pages.csv
 *     wp ai-visibility llms
 *     wp ai-visibility block GPTBot CCBot
 */
class NSW_AIV_CLI {

	/**
	 * Visits per AI bot.
	 *
	 * ## OPTIONS
	 *
	 * [--days=<days>]
	 * : Period length in days.
	 * ---
	 * default: 30
	 * ---
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 * ---
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function stats( $args, $assoc_args ) {
		$days = isset( $assoc_args['days'] ) ? absint( $assoc_args['days'] ) : 30;
		$rows = NSW_AIV_Tracker::by_bot( $days );

		if ( empty( $rows ) ) {
			WP_CLI::log( sprintf( 'No AI bot visits in the last %d days.', $days ) );
			return;
		}

		WP_CLI\Utils\format_items( $this->format( $assoc_args ), $rows, array( 'bot', 'hits', 'pages', 'last_seen' ) );
	}

	/**
	 * Pages most visited by AI bots.
	 *
	 * ## OPTIONS
	 *
	 * [--days=<days>]
	 * : Period length in days.
	 * ---
	 * default: 30
	 * ---
	 *
	 * [--limit=<limit>]
	 * : Number of pages.
	 * ---
	 * default: 20
	 * ---
	 *
	 * [--format=<format>]
	 * : Output format (table, csv, json).
	 * ---
	 * default: table
	 * ---
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function pages( $args, $assoc_args ) {
		$days  = isset( $assoc_args['days'] ) ? absint( $assoc_args['days'] ) : 30;
		$limit = isset( $assoc_args['limit'] ) ? absint( $assoc_args['limit'] ) : 20;
		$rows  = NSW_AIV_Tracker::top_paths( $days, $limit );

		WP_CLI\Utils\format_items( $this->format( $assoc_args ), $rows, array( 'path', 'hits', 'bots' ) );
	}

	/**
	 * Print the generated llms.txt.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function llms( $args, $assoc_args ) {
		NSW_AIV_Llms::flush_cache();
		WP_CLI::log( NSW_AIV_Llms::content() );
	}

	/**
	 * Block one or more AI bots in robots.txt.
	 *
	 * ## OPTIONS
	 *
	 * <bot>...
	 * : Bot tokens, for example GPTBot or CCBot.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function block( $args, $assoc_args ) {
		$this->set_blocked( $args, true );
	}

	/**
	 * Allow one or more AI bots again.
	 *
	 * ## OPTIONS
	 *
	 * <bot>...
	 * : Bot tokens.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function allow( $args, $assoc_args ) {
		$this->set_blocked( $args, false );
	}

	/**
	 * Update the blocked list.
	 *
	 * @param string[] $tokens Bot tokens.
	 * @param bool     $block  True to block, false to allow.
	 */
	private function set_blocked( $tokens, $block ) {
		$known    = array_keys( NSW_AIV_Bots::all() );
		$settings = NSW_AIV_Settings::get();
		$blocked  = (array) $settings['blocked_bots'];

		foreach ( $tokens as $token ) {
			if ( ! in_array( $token, $known, true ) ) {
				WP_CLI::warning( sprintf( 'Unknown bot: %s', $token ) );
				continue;
			}
			$blocked = $block ? array_unique( array_merge( $blocked, array( $token ) ) ) : array_diff( $blocked, array( $token ) );
		}

		$settings['blocked_bots'] = array_values( $blocked );
		update_option( NSW_AIV_OPTION, NSW_AIV_Settings::sanitize( $settings ) );
		WP_CLI::success( 'Blocked bots: ' . ( $settings['blocked_bots'] ? implode( ', ', $settings['blocked_bots'] ) : 'none' ) );
	}

	/**
	 * Validated output format.
	 *
	 * @param array $assoc_args Named arguments.
	 * @return string
	 */
	private function format( $assoc_args ) {
		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';
		return in_array( $format, array( 'table', 'csv', 'json' ), true ) ? $format : 'table';
	}
}
