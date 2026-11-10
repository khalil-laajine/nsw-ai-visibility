<?php
/**
 * Dashboard widget: AI bot visits over the last 7 days.
 *
 * @package NSW_AI_Visibility
 */

defined( 'ABSPATH' ) || exit;

/**
 * Small summary on the WordPress dashboard.
 */
class NSW_AIV_Dashboard {

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'register' ) );
	}

	/**
	 * Register the widget for administrators.
	 */
	public static function register() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		wp_add_dashboard_widget(
			'nsw_aiv_dashboard',
			__( 'AI bots, last 7 days', 'nsw-ai-visibility' ),
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Widget content.
	 */
	public static function render() {
		$by_bot   = NSW_AIV_Tracker::by_bot( 7 );
		$settings = NSW_AIV_Settings::get();
		$summary  = NSW_AIV_Report::summary( $by_bot, $settings['blocked_bots'] );
		$url      = admin_url( 'options-general.php?page=' . NSW_AIV_Admin::SLUG );

		if ( 0 === $summary['total'] ) {
			echo '<p>' . esc_html__( 'No AI bot visit recorded this week.', 'nsw-ai-visibility' ) . '</p>';
		} else {
			echo '<p>';
			printf(
				/* translators: 1: number of visits, 2: number of bots. */
				esc_html__( '%1$s visits from %2$s different AI bots.', 'nsw-ai-visibility' ),
				'<strong>' . esc_html( number_format_i18n( $summary['total'] ) ) . '</strong>',
				'<strong>' . esc_html( number_format_i18n( $summary['bots'] ) ) . '</strong>'
			);
			echo '</p><ul>';
			foreach ( array_slice( $by_bot, 0, 3 ) as $row ) {
				echo '<li><code>' . esc_html( $row['bot'] ) . '</code> ' . esc_html( number_format_i18n( $row['hits'] ) ) . '</li>';
			}
			echo '</ul>';
		}

		echo '<p><a href="' . esc_url( $url ) . '">' . esc_html__( 'See details and settings', 'nsw-ai-visibility' ) . '</a></p>';
	}
}
