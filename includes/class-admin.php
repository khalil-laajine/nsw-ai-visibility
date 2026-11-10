<?php
/**
 * Admin screen: Settings > AI Visibility.
 *
 * @package NSW_AI_Visibility
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings page with AI bot activity.
 */
class NSW_AIV_Admin {

	const SLUG          = 'nsw-ai-visibility';
	const EXPORT_ACTION = 'nsw_aiv_export';

	/**
	 * Periods offered on the stats tab, in days.
	 *
	 * @var int[]
	 */
	const PERIODS = array( 7, 30, 90 );

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_' . self::EXPORT_ACTION, array( __CLASS__, 'export' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( NSW_AIV_FILE ), array( __CLASS__, 'action_link' ) );
	}

	/**
	 * Add the page under Settings.
	 */
	public static function menu() {
		add_options_page(
			__( 'AI Visibility', 'nsw-ai-visibility' ),
			__( 'AI Visibility', 'nsw-ai-visibility' ),
			'manage_options',
			self::SLUG,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Register the option with its sanitizer.
	 */
	public static function register() {
		register_setting(
			'nsw_aiv',
			NSW_AIV_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'NSW_AIV_Settings', 'sanitize' ),
				'default'           => NSW_AIV_Settings::defaults(),
			)
		);
	}

	/**
	 * Styles and script, on our page only.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function assets( $hook ) {
		if ( 'settings_page_' . self::SLUG !== $hook ) {
			return;
		}
		wp_enqueue_style( 'nsw-aiv-admin', NSW_AIV_URL . 'assets/admin.css', array(), NSW_AIV_VERSION );
		wp_enqueue_script( 'nsw-aiv-admin', NSW_AIV_URL . 'assets/admin.js', array(), NSW_AIV_VERSION, true );
	}

	/**
	 * "Settings" link on the plugins screen.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public static function action_link( $links ) {
		$url = admin_url( 'options-general.php?page=' . self::SLUG );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'nsw-ai-visibility' ) . '</a>' );
		return $links;
	}

	/**
	 * Period selected on the stats tab.
	 *
	 * @return int
	 */
	private static function days() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display filter.
		$days = isset( $_GET['days'] ) ? absint( $_GET['days'] ) : 30;
		return in_array( $days, self::PERIODS, true ) ? $days : 30;
	}

	/**
	 * Render the page.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tabs = array(
			'stats'    => __( 'Statistics', 'nsw-ai-visibility' ),
			'bots'     => __( 'AI crawlers', 'nsw-ai-visibility' ),
			'llms'     => __( 'llms.txt', 'nsw-ai-visibility' ),
			'tracking' => __( 'Tracking', 'nsw-ai-visibility' ),
		);
		?>
		<div class="wrap nsw-aiv">
			<h1><?php esc_html_e( 'AI Visibility', 'nsw-ai-visibility' ); ?></h1>
			<p class="nsw-aiv-lead"><?php esc_html_e( 'See which AI bots visit your site, choose which ones may read it, and publish an llms.txt file that presents your content to AI assistants.', 'nsw-ai-visibility' ); ?></p>

			<?php self::notices(); ?>

			<nav class="nav-tab-wrapper nsw-aiv-tabs" aria-label="<?php esc_attr_e( 'Sections', 'nsw-ai-visibility' ); ?>">
				<?php foreach ( $tabs as $key => $label ) : ?>
					<a href="#nsw-aiv-<?php echo esc_attr( $key ); ?>" class="nav-tab" data-tab="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>

			<section id="nsw-aiv-stats" class="nsw-aiv-panel" data-panel="stats">
				<?php self::render_stats(); ?>
			</section>

			<form method="post" action="options.php" class="nsw-aiv-form">
				<?php settings_fields( 'nsw_aiv' ); ?>

				<section id="nsw-aiv-bots" class="nsw-aiv-panel" data-panel="bots">
					<?php self::render_bots(); ?>
				</section>

				<section id="nsw-aiv-llms" class="nsw-aiv-panel" data-panel="llms">
					<?php self::render_llms(); ?>
				</section>

				<section id="nsw-aiv-tracking" class="nsw-aiv-panel" data-panel="tracking">
					<?php self::render_tracking(); ?>
				</section>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Warn when something stops the plugin from working.
	 */
	private static function notices() {
		if ( NSW_AIV_Robots::has_physical_file() ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'A robots.txt file exists at the root of your site, so WordPress does not generate one and the AI crawler rules below are not applied. Add them to that file by hand, or remove it.', 'nsw-ai-visibility' ) . '</p></div>';
		}
		if ( NSW_AIV_Llms::has_physical_file() ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'An llms.txt file exists at the root of your site and is served instead of the generated one.', 'nsw-ai-visibility' ) . '</p></div>';
		}
		if ( ! get_option( 'blog_public' ) ) {
			echo '<div class="notice notice-info"><p>' . esc_html__( 'Your site asks search engines not to index it, so no AI crawler rules are added to robots.txt.', 'nsw-ai-visibility' ) . '</p></div>';
		}
		if ( ! get_option( 'permalink_structure' ) ) {
			echo '<div class="notice notice-info"><p>' . esc_html__( 'Plain permalinks are enabled: /llms.txt only works with pretty permalinks (Settings > Permalinks).', 'nsw-ai-visibility' ) . '</p></div>';
		}
	}

	/**
	 * Statistics tab.
	 */
	private static function render_stats() {
		$days     = self::days();
		$settings = NSW_AIV_Settings::get();
		$known    = NSW_AIV_Bots::all();
		$by_bot   = NSW_AIV_Tracker::by_bot( $days );
		$paths    = NSW_AIV_Tracker::top_paths( $days, 10 );
		$daily    = NSW_AIV_Tracker::daily( $days );
		$summary  = NSW_AIV_Report::summary( $by_bot, $settings['blocked_bots'] );
		$base     = admin_url( 'options-general.php?page=' . self::SLUG );
		?>
		<div class="nsw-aiv-periods">
			<span><?php esc_html_e( 'Period:', 'nsw-ai-visibility' ); ?></span>
			<?php foreach ( self::PERIODS as $period ) : ?>
				<a href="<?php echo esc_url( add_query_arg( 'days', $period, $base ) . '#nsw-aiv-stats' ); ?>" class="button<?php echo $period === $days ? ' button-primary' : ''; ?>" <?php echo $period === $days ? 'aria-current="true"' : ''; ?>>
					<?php
					/* translators: %d: number of days. */
					echo esc_html( sprintf( _n( '%d day', '%d days', $period, 'nsw-ai-visibility' ), $period ) );
					?>
				</a>
			<?php endforeach; ?>
			<a class="button nsw-aiv-export" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=' . self::EXPORT_ACTION . '&days=' . $days ), self::EXPORT_ACTION ) ); ?>"><?php esc_html_e( 'Export CSV', 'nsw-ai-visibility' ); ?></a>
		</div>

		<div class="nsw-aiv-cards">
			<div class="nsw-aiv-card">
				<span class="nsw-aiv-card-label"><?php esc_html_e( 'AI bot visits', 'nsw-ai-visibility' ); ?></span>
				<span class="nsw-aiv-card-value"><?php echo esc_html( number_format_i18n( $summary['total'] ) ); ?></span>
			</div>
			<div class="nsw-aiv-card">
				<span class="nsw-aiv-card-label"><?php esc_html_e( 'Different bots', 'nsw-ai-visibility' ); ?></span>
				<span class="nsw-aiv-card-value"><?php echo esc_html( number_format_i18n( $summary['bots'] ) ); ?></span>
			</div>
			<div class="nsw-aiv-card">
				<span class="nsw-aiv-card-label"><?php esc_html_e( 'From AI search engines', 'nsw-ai-visibility' ); ?></span>
				<span class="nsw-aiv-card-value"><?php echo esc_html( number_format_i18n( $summary['by_purpose']['search'] + $summary['by_purpose']['agent'] ) ); ?></span>
			</div>
			<div class="nsw-aiv-card">
				<span class="nsw-aiv-card-label"><?php esc_html_e( 'Blocked bots', 'nsw-ai-visibility' ); ?></span>
				<span class="nsw-aiv-card-value"><?php echo esc_html( number_format_i18n( $summary['blocked'] ) ); ?></span>
			</div>
		</div>

		<?php if ( 0 === $summary['total'] ) : ?>
			<div class="nsw-aiv-box nsw-aiv-empty">
				<p><?php esc_html_e( 'No AI bot visit recorded for this period. Data appears as soon as a known AI crawler reads one of your pages.', 'nsw-ai-visibility' ); ?></p>
			</div>
			<?php
			return;
		endif;

		$max_day = max( $daily );
		?>
		<div class="nsw-aiv-box">
			<h2><?php esc_html_e( 'Visits per day', 'nsw-ai-visibility' ); ?></h2>
			<div class="nsw-aiv-daily" role="img" aria-label="<?php esc_attr_e( 'AI bot visits per day', 'nsw-ai-visibility' ); ?>">
				<?php foreach ( $daily as $day => $hits ) : ?>
					<span class="nsw-aiv-daily-bar" style="height:<?php echo esc_attr( NSW_AIV_Report::bar( $hits, $max_day ) ); ?>%" title="<?php echo esc_attr( date_i18n( get_option( 'date_format' ), strtotime( $day ) ) . ' : ' . number_format_i18n( $hits ) ); ?>"></span>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="nsw-aiv-columns">
			<div class="nsw-aiv-box">
				<h2><?php esc_html_e( 'Visits per bot', 'nsw-ai-visibility' ); ?></h2>
				<table class="nsw-aiv-bars">
					<thead class="screen-reader-text"><tr>
						<th scope="col"><?php esc_html_e( 'Bot', 'nsw-ai-visibility' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Share', 'nsw-ai-visibility' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Visits', 'nsw-ai-visibility' ); ?></th>
					</tr></thead>
					<tbody>
					<?php
					$max = $by_bot[0]['hits'];
					foreach ( $by_bot as $row ) :
						$blocked = in_array( $row['bot'], (array) $settings['blocked_bots'], true );
						$bot     = isset( $known[ $row['bot'] ] ) ? $known[ $row['bot'] ] : array(
							'vendor'  => '',
							'purpose' => '',
						);
						?>
						<tr>
							<th scope="row">
								<code><?php echo esc_html( $row['bot'] ); ?></code>
								<span class="nsw-aiv-muted"><?php echo esc_html( trim( $bot['vendor'] . ' · ' . NSW_AIV_Bots::purpose_label( $bot['purpose'] ), ' ·' ) ); ?></span>
								<?php if ( $blocked ) : ?>
									<span class="nsw-aiv-tag"><?php esc_html_e( 'blocked', 'nsw-ai-visibility' ); ?></span>
								<?php endif; ?>
							</th>
							<td class="nsw-aiv-bar-cell"><span class="nsw-aiv-bar<?php echo $blocked ? ' is-blocked' : ''; ?>" style="width:<?php echo esc_attr( NSW_AIV_Report::bar( $row['hits'], $max ) ); ?>%"></span></td>
							<td class="nsw-aiv-num"><?php echo esc_html( number_format_i18n( $row['hits'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p class="description"><?php esc_html_e( 'A blocked bot can still appear here: robots.txt is a request, and some bots read it a few days late or ignore it.', 'nsw-ai-visibility' ); ?></p>
			</div>

			<div class="nsw-aiv-box">
				<h2><?php esc_html_e( 'Pages most read by AI bots', 'nsw-ai-visibility' ); ?></h2>
				<table class="widefat striped">
					<thead><tr>
						<th scope="col"><?php esc_html_e( 'Page', 'nsw-ai-visibility' ); ?></th>
						<th scope="col" class="nsw-aiv-num"><?php esc_html_e( 'Visits', 'nsw-ai-visibility' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $paths as $row ) : ?>
						<tr>
							<td>
								<a href="<?php echo esc_url( home_url( $row['path'] ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $row['path'] ); ?></a>
								<span class="nsw-aiv-muted nsw-aiv-block"><?php echo esc_html( $row['bots'] ); ?></span>
							</td>
							<td class="nsw-aiv-num"><?php echo esc_html( number_format_i18n( $row['hits'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * AI crawlers tab.
	 */
	private static function render_bots() {
		$settings = NSW_AIV_Settings::get();
		$name     = NSW_AIV_OPTION;
		?>
		<div class="nsw-aiv-columns">
			<div class="nsw-aiv-box">
				<div class="nsw-aiv-box-head">
					<h2><?php esc_html_e( 'Bots to block', 'nsw-ai-visibility' ); ?></h2>
					<span class="nsw-aiv-presets">
						<button type="button" class="button" data-preset="training"><?php esc_html_e( 'Block training only', 'nsw-ai-visibility' ); ?></button>
						<button type="button" class="button" data-preset="none"><?php esc_html_e( 'Allow all', 'nsw-ai-visibility' ); ?></button>
					</span>
				</div>
				<p class="description"><?php esc_html_e( 'Blocking a training bot keeps your texts out of future models without removing you from the answers of ChatGPT Search, Perplexity or Claude. Blocking a search bot can remove your pages from its answers.', 'nsw-ai-visibility' ); ?></p>
				<table class="widefat striped nsw-aiv-bot-table">
					<thead>
						<tr>
							<th scope="col" class="check-column"><span class="screen-reader-text"><?php esc_html_e( 'Block', 'nsw-ai-visibility' ); ?></span></th>
							<th scope="col"><?php esc_html_e( 'Bot', 'nsw-ai-visibility' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Company', 'nsw-ai-visibility' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Purpose', 'nsw-ai-visibility' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( NSW_AIV_Bots::all() as $token => $bot ) : ?>
						<?php $id = 'nsw-aiv-bot-' . sanitize_html_class( $token ); ?>
						<tr>
							<th scope="row" class="check-column">
								<input type="checkbox" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>[blocked_bots][]" value="<?php echo esc_attr( $token ); ?>" data-purpose="<?php echo esc_attr( $bot['purpose'] ); ?>" <?php checked( in_array( $token, (array) $settings['blocked_bots'], true ) ); ?>>
							</th>
							<td><label for="<?php echo esc_attr( $id ); ?>"><code><?php echo esc_html( $token ); ?></code></label></td>
							<td><?php echo esc_html( $bot['vendor'] ); ?></td>
							<td><span class="nsw-aiv-purpose nsw-aiv-purpose-<?php echo esc_attr( $bot['purpose'] ); ?>"><?php echo esc_html( NSW_AIV_Bots::purpose_label( $bot['purpose'] ) ); ?></span></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<div class="nsw-aiv-box">
				<h2><?php esc_html_e( 'Added to robots.txt', 'nsw-ai-visibility' ); ?></h2>
				<pre class="nsw-aiv-code" id="nsw-aiv-robots-preview" aria-live="polite" data-empty="<?php esc_attr_e( '# No AI crawler blocked', 'nsw-ai-visibility' ); ?>"><?php echo esc_html( '' !== NSW_AIV_Robots::rules() ? NSW_AIV_Robots::rules() : __( '# No AI crawler blocked', 'nsw-ai-visibility' ) ); ?></pre>
				<p class="description">
					<?php
					printf(
						/* translators: %s: robots.txt URL. */
						esc_html__( 'The preview follows your ticks. Save, then check the live file: %s', 'nsw-ai-visibility' ),
						'<a href="' . esc_url( home_url( '/robots.txt' ) ) . '" target="_blank" rel="noopener">robots.txt</a>'
					);
					?>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Tab for the llms.txt file.
	 */
	private static function render_llms() {
		$settings = NSW_AIV_Settings::get();
		$name     = NSW_AIV_OPTION;
		?>
		<div class="nsw-aiv-columns">
			<div class="nsw-aiv-box">
				<h2><?php esc_html_e( 'llms.txt file', 'nsw-ai-visibility' ); ?></h2>
				<p>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[llms_enabled]" value="1" <?php checked( $settings['llms_enabled'] ); ?>>
						<?php
						printf(
							/* translators: %s: llms.txt URL. */
							esc_html__( 'Serve the file at %s', 'nsw-ai-visibility' ),
							'<a href="' . esc_url( home_url( '/llms.txt' ) ) . '" target="_blank" rel="noopener">' . esc_html( home_url( '/llms.txt' ) ) . '</a>'
						);
						?>
					</label>
				</p>
				<p>
					<label for="nsw-aiv-intro" class="nsw-aiv-label"><?php esc_html_e( 'Introduction', 'nsw-ai-visibility' ); ?></label>
					<textarea id="nsw-aiv-intro" class="large-text" rows="4" name="<?php echo esc_attr( $name ); ?>[llms_intro]"><?php echo esc_textarea( $settings['llms_intro'] ); ?></textarea>
					<span class="description"><?php esc_html_e( 'A few sentences on who you are, what you offer and where. This is what AI assistants will read first.', 'nsw-ai-visibility' ); ?></span>
				</p>
				<p>
					<label for="nsw-aiv-posts" class="nsw-aiv-label"><?php esc_html_e( 'Latest articles to list', 'nsw-ai-visibility' ); ?></label>
					<input type="number" id="nsw-aiv-posts" min="0" max="50" class="small-text" name="<?php echo esc_attr( $name ); ?>[llms_posts]" value="<?php echo esc_attr( $settings['llms_posts'] ); ?>">
				</p>
				<details <?php echo '' !== $settings['llms_manual'] ? 'open' : ''; ?>>
					<summary><?php esc_html_e( 'Write the file yourself', 'nsw-ai-visibility' ); ?></summary>
					<textarea id="nsw-aiv-manual" class="large-text code" rows="8" name="<?php echo esc_attr( $name ); ?>[llms_manual]" aria-label="<?php esc_attr_e( 'Manual llms.txt content', 'nsw-ai-visibility' ); ?>"><?php echo esc_textarea( $settings['llms_manual'] ); ?></textarea>
					<span class="description"><?php esc_html_e( 'Optional. When filled in, this Markdown replaces the generated file.', 'nsw-ai-visibility' ); ?></span>
				</details>
				<p class="description"><?php esc_html_e( 'Summaries come from the Rank Math or Yoast meta description, then the excerpt. Pages marked noindex are never listed.', 'nsw-ai-visibility' ); ?></p>
			</div>

			<div class="nsw-aiv-box">
				<h2><?php esc_html_e( 'Current file', 'nsw-ai-visibility' ); ?></h2>
				<?php if ( $settings['llms_enabled'] ) : ?>
					<pre class="nsw-aiv-code nsw-aiv-code-tall"><?php echo esc_html( NSW_AIV_Llms::content() ); ?></pre>
					<p class="description"><?php esc_html_e( 'Updated when you save these settings or publish content.', 'nsw-ai-visibility' ); ?></p>
				<?php else : ?>
					<p><?php esc_html_e( 'The file is turned off: /llms.txt returns a 404 error.', 'nsw-ai-visibility' ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Tracking tab.
	 */
	private static function render_tracking() {
		$settings = NSW_AIV_Settings::get();
		$name     = NSW_AIV_OPTION;
		?>
		<div class="nsw-aiv-box nsw-aiv-narrow">
			<h2><?php esc_html_e( 'Visit tracking', 'nsw-ai-visibility' ); ?></h2>
			<p>
				<label>
					<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[track_enabled]" value="1" <?php checked( $settings['track_enabled'] ); ?>>
					<?php esc_html_e( 'Count AI bot visits', 'nsw-ai-visibility' ); ?>
				</label>
			</p>
			<p>
				<label for="nsw-aiv-retention" class="nsw-aiv-label"><?php esc_html_e( 'Keep data for', 'nsw-ai-visibility' ); ?></label>
				<input type="number" id="nsw-aiv-retention" min="7" max="365" class="small-text" name="<?php echo esc_attr( $name ); ?>[retention]" value="<?php echo esc_attr( $settings['retention'] ); ?>">
				<?php esc_html_e( 'days', 'nsw-ai-visibility' ); ?>
			</p>
			<p class="description"><?php esc_html_e( 'One row per day, bot and page. Only known AI bots are counted: no IP address, no cookie, no visitor data. Older rows are deleted every day.', 'nsw-ai-visibility' ); ?></p>
			<div class="nsw-aiv-dev">
				<strong><?php esc_html_e( 'Also available from the command line and the REST API', 'nsw-ai-visibility' ); ?></strong>
				<code>wp ai-visibility stats --days=30</code>
				<code>wp ai-visibility block GPTBot CCBot</code>
				<code>GET <?php echo esc_html( rest_url( 'nsw-aiv/v1/stats?days=30' ) ); ?></code>
			</div>
		</div>
		<?php
	}

	/**
	 * Send the CSV export.
	 */
	public static function export() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to export these stats.', 'nsw-ai-visibility' ), 403 );
		}
		check_admin_referer( self::EXPORT_ACTION );

		$days = self::days();
		$file = sprintf( 'ai-visibility-%s-%dd.csv', current_time( 'Y-m-d' ), $days );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $file . '"' );

		echo NSW_AIV_Report::csv( NSW_AIV_Tracker::rows( $days ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV body, cells escaped by NSW_AIV_Report::cell().
		exit;
	}
}
