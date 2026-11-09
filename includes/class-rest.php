<?php
/**
 * REST API endpoints.
 *
 * @package NSW_AI_Visibility
 */

defined( 'ABSPATH' ) || exit;

/**
 * Exposes AI bot stats over the WordPress REST API, for dashboards such as
 * Looker Studio or a monthly client report.
 *
 * GET /wp-json/nsw-aiv/v1/stats?days=30
 */
class NSW_AIV_Rest {

	const NAMESPACE_V1 = 'nsw-aiv/v1';

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Register routes.
	 */
	public static function register() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/stats',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'stats' ),
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
				'args'                => array(
					'days' => array(
						'type'              => 'integer',
						'default'           => 30,
						'minimum'           => 1,
						'maximum'           => 365,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Stats response.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function stats( $request ) {
		$days = (int) $request->get_param( 'days' );
		$bots = NSW_AIV_Bots::all();

		$by_bot = array_map(
			static function ( $row ) use ( $bots ) {
				$token          = $row['bot'];
				$row['vendor']  = isset( $bots[ $token ] ) ? $bots[ $token ]['vendor'] : '';
				$row['purpose'] = isset( $bots[ $token ] ) ? $bots[ $token ]['purpose'] : '';
				return $row;
			},
			NSW_AIV_Tracker::by_bot( $days )
		);

		return rest_ensure_response(
			array(
				'days'      => $days,
				'blocked'   => NSW_AIV_Settings::get()['blocked_bots'],
				'by_bot'    => $by_bot,
				'top_paths' => NSW_AIV_Tracker::top_paths( $days ),
			)
		);
	}
}
