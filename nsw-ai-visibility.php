<?php
/**
 * Plugin Name:       NSW AI Visibility
 * Plugin URI:        https://github.com/khalil-laajine/nsw-ai-visibility
 * Description:       Control which AI crawlers can read your site, publish an llms.txt file and see which pages AI bots actually visit.
 * Version:           1.1.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Khalil Laajine
 * Author URI:        https://novasiteweb.fr
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       nsw-ai-visibility
 * Domain Path:       /languages
 *
 * @package NSW_AI_Visibility
 */

defined( 'ABSPATH' ) || exit;

define( 'NSW_AIV_VERSION', '1.1.0' );
define( 'NSW_AIV_FILE', __FILE__ );
define( 'NSW_AIV_DIR', plugin_dir_path( __FILE__ ) );
define( 'NSW_AIV_URL', plugin_dir_url( __FILE__ ) );
define( 'NSW_AIV_OPTION', 'nsw_aiv_settings' );

require_once NSW_AIV_DIR . 'includes/class-bots.php';
require_once NSW_AIV_DIR . 'includes/class-settings.php';
require_once NSW_AIV_DIR . 'includes/class-robots.php';
require_once NSW_AIV_DIR . 'includes/class-llms.php';
require_once NSW_AIV_DIR . 'includes/class-tracker.php';
require_once NSW_AIV_DIR . 'includes/class-rest.php';
require_once NSW_AIV_DIR . 'includes/class-report.php';
require_once NSW_AIV_DIR . 'includes/class-admin.php';
require_once NSW_AIV_DIR . 'includes/class-dashboard.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once NSW_AIV_DIR . 'includes/class-cli.php';
	WP_CLI::add_command( 'ai-visibility', 'NSW_AIV_CLI' );
}

/**
 * Boot every module once WordPress is loaded.
 */
function nsw_aiv_boot() {
	load_plugin_textdomain( 'nsw-ai-visibility', false, dirname( plugin_basename( NSW_AIV_FILE ) ) . '/languages' );

	NSW_AIV_Robots::init();
	NSW_AIV_Llms::init();
	NSW_AIV_Tracker::init();
	NSW_AIV_Rest::init();

	if ( is_admin() ) {
		NSW_AIV_Admin::init();
		NSW_AIV_Dashboard::init();
	}
}
add_action( 'plugins_loaded', 'nsw_aiv_boot' );

/**
 * Activation: create the stats table and register the llms.txt rewrite rule.
 */
function nsw_aiv_activate() {
	NSW_AIV_Tracker::create_table();
	NSW_AIV_Tracker::schedule();
	NSW_AIV_Llms::add_rewrite_rule();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'nsw_aiv_activate' );

/**
 * Deactivation: remove our rewrite rule. Data is kept until uninstall.
 */
function nsw_aiv_deactivate() {
	wp_clear_scheduled_hook( NSW_AIV_Tracker::CRON_HOOK );
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'nsw_aiv_deactivate' );
