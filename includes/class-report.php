<?php
/**
 * Turns raw stats into summaries and exports.
 *
 * Pure functions with no database access, so they are easy to test.
 *
 * @package NSW_AI_Visibility
 */

defined( 'ABSPATH' ) || exit;

/**
 * Stats summaries and CSV export.
 */
class NSW_AIV_Report {

	/**
	 * Headline figures for a period.
	 *
	 * @param array    $by_bot  Rows from NSW_AIV_Tracker::by_bot().
	 * @param string[] $blocked Blocked bot tokens.
	 * @return array{total: int, bots: int, blocked: int, top_bot: string, by_purpose: array<string, int>}
	 */
	public static function summary( $by_bot, $blocked ) {
		$known      = NSW_AIV_Bots::all();
		$total      = 0;
		$by_purpose = array(
			'search'   => 0,
			'agent'    => 0,
			'training' => 0,
		);

		foreach ( $by_bot as $row ) {
			$total += (int) $row['hits'];
			if ( isset( $known[ $row['bot'] ] ) ) {
				$by_purpose[ $known[ $row['bot'] ]['purpose'] ] += (int) $row['hits'];
			}
		}

		return array(
			'total'      => $total,
			'bots'       => count( $by_bot ),
			'blocked'    => count( (array) $blocked ),
			'top_bot'    => empty( $by_bot ) ? '' : (string) $by_bot[0]['bot'],
			'by_purpose' => $by_purpose,
		);
	}

	/**
	 * Width of a bar, in percent of the largest value. Never below 2 so a
	 * small but non-zero value stays visible.
	 *
	 * @param int $value Value.
	 * @param int $max   Largest value of the series.
	 * @return int
	 */
	public static function bar( $value, $max ) {
		if ( $max <= 0 || $value <= 0 ) {
			return 0;
		}
		return max( 2, (int) round( $value / $max * 100 ) );
	}

	/**
	 * CSV export of the visits per page and bot.
	 *
	 * Cells starting with = + - @ are prefixed with a quote so a spreadsheet
	 * never runs them as formulas (paths come from requests sent by bots).
	 *
	 * @param array $rows Rows with day, bot, path, hits.
	 * @return string
	 */
	public static function csv( $rows ) {
		$lines = array( 'day,bot,path,hits' );
		foreach ( $rows as $row ) {
			$lines[] = implode(
				',',
				array(
					self::cell( $row['day'] ),
					self::cell( $row['bot'] ),
					self::cell( $row['path'] ),
					(int) $row['hits'],
				)
			);
		}
		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * Escape one CSV cell.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private static function cell( $value ) {
		$value = (string) $value;
		if ( '' !== $value && false !== strpos( '=+-@', $value[0] ) ) {
			$value = "'" . $value;
		}
		if ( preg_match( '/[",\r\n]/', $value ) ) {
			$value = '"' . str_replace( '"', '""', $value ) . '"';
		}
		return $value;
	}
}
