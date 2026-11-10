<?php
/**
 * Counts visits from AI crawlers.
 *
 * @package NSW_AI_Visibility
 */

defined( 'ABSPATH' ) || exit;

/**
 * Lightweight AI bot visit tracker.
 *
 * Stores one row per day, bot and path with a hit counter. No IP address
 * and no personal data are stored: only bots are counted.
 */
class NSW_AIV_Tracker {

	const DB_VERSION = '1';
	const CRON_HOOK  = 'nsw_aiv_purge';

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_action( 'wp', array( __CLASS__, 'record' ), 1 );
		add_action( self::CRON_HOOK, array( __CLASS__, 'purge' ) );

		// Upgrades and a lost cron event are repaired from the admin only, never on front-end requests.
		if ( is_admin() ) {
			if ( get_option( 'nsw_aiv_db_version' ) !== self::DB_VERSION ) {
				self::create_table();
			}
			self::schedule();
		}
	}

	/**
	 * Schedule the daily purge if it is missing.
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Full table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'nsw_aiv_hits';
	}

	/**
	 * Create or upgrade the stats table.
	 */
	public static function create_table() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		dbDelta(
			"CREATE TABLE {$table} (
				day date NOT NULL,
				bot varchar(40) NOT NULL,
				path varchar(191) NOT NULL,
				hits bigint(20) unsigned NOT NULL DEFAULT 0,
				PRIMARY KEY  (day,bot,path),
				KEY bot_day (bot,day)
			) {$charset};"
		);

		update_option( 'nsw_aiv_db_version', self::DB_VERSION, false );
	}

	/**
	 * Record the current request if it comes from a known AI bot.
	 */
	public static function record() {
		$settings = NSW_AIV_Settings::get();
		if ( empty( $settings['track_enabled'] ) || is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$bot        = NSW_AIV_Bots::detect( $user_agent );
		if ( null === $bot ) {
			return;
		}

		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		$path = wp_parse_url( $uri, PHP_URL_PATH );
		$path = substr( is_string( $path ) && '' !== $path ? $path : '/', 0, 191 );

		self::increment( $bot, $path );
	}

	/**
	 * Add one hit for a bot and path today.
	 *
	 * @param string $bot  Bot token.
	 * @param string $path Request path.
	 */
	public static function increment( $bot, $path ) {
		global $wpdb;
		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom table, single upsert per bot request.
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table} (day, bot, path, hits) VALUES (%s, %s, %s, 1) ON DUPLICATE KEY UPDATE hits = hits + 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				current_time( 'Y-m-d' ),
				$bot,
				$path
			)
		);
	}

	/**
	 * Delete rows older than the retention period.
	 */
	public static function purge() {
		global $wpdb;
		$settings = NSW_AIV_Settings::get();
		$table    = self::table();
		$limit    = gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' -' . (int) $settings['retention'] . ' days' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table, table name is not user input.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE day < %s", $limit ) );
	}

	/**
	 * Visits per bot over the last N days.
	 *
	 * @param int $days Period length.
	 * @return array<int, array{bot: string, hits: int, pages: int, last_seen: string}>
	 */
	public static function by_bot( $days = 30 ) {
		global $wpdb;
		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT bot, SUM(hits) AS hits, COUNT(DISTINCT path) AS pages, MAX(day) AS last_seen FROM {$table} WHERE day >= %s GROUP BY bot ORDER BY hits DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				self::since( $days )
			),
			ARRAY_A
		);

		return array_map(
			static function ( $row ) {
				$row['hits']  = (int) $row['hits'];
				$row['pages'] = (int) $row['pages'];
				return $row;
			},
			(array) $rows
		);
	}

	/**
	 * Most visited paths by AI bots over the last N days.
	 *
	 * @param int $days  Period length.
	 * @param int $limit Max rows.
	 * @return array<int, array{path: string, hits: int, bots: string}>
	 */
	public static function top_paths( $days = 30, $limit = 20 ) {
		global $wpdb;
		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT path, SUM(hits) AS hits, GROUP_CONCAT(DISTINCT bot ORDER BY bot SEPARATOR ', ') AS bots FROM {$table} WHERE day >= %s GROUP BY path ORDER BY hits DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				self::since( $days ),
				$limit
			),
			ARRAY_A
		);

		return array_map(
			static function ( $row ) {
				$row['hits'] = (int) $row['hits'];
				return $row;
			},
			(array) $rows
		);
	}

	/**
	 * First day of a period ending today.
	 *
	 * @param int $days Period length.
	 * @return string Y-m-d date.
	 */
	private static function since( $days ) {
		$days = max( 1, (int) $days );
		return gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' -' . ( $days - 1 ) . ' days' ) );
	}

	/**
	 * Total AI bot visits per day over the last N days, oldest first.
	 *
	 * Days without any visit are included with 0 so the series has no gaps.
	 *
	 * @param int $days Period length.
	 * @return array<string, int> Visits keyed by Y-m-d date.
	 */
	public static function daily( $days = 30 ) {
		global $wpdb;
		$table = self::table();
		$since = self::since( $days );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT day, SUM(hits) AS hits FROM {$table} WHERE day >= %s GROUP BY day", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$since
			),
			ARRAY_A
		);

		$series = array();
		$today  = strtotime( current_time( 'Y-m-d' ) );
		for ( $cursor = strtotime( $since ); $cursor <= $today; $cursor += DAY_IN_SECONDS ) {
			$series[ gmdate( 'Y-m-d', $cursor ) ] = 0;
		}
		foreach ( (array) $rows as $row ) {
			if ( isset( $series[ $row['day'] ] ) ) {
				$series[ $row['day'] ] = (int) $row['hits'];
			}
		}

		return $series;
	}

	/**
	 * Raw rows for the CSV export, newest first.
	 *
	 * @param int $days Period length.
	 * @return array<int, array{day: string, bot: string, path: string, hits: int}>
	 */
	public static function rows( $days = 30 ) {
		global $wpdb;
		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT day, bot, path, hits FROM {$table} WHERE day >= %s ORDER BY day DESC, hits DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				self::since( $days )
			),
			ARRAY_A
		);
	}
}
