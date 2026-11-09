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

	const SLUG = 'nsw-ai-visibility';

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
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
	 * Render the page.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = NSW_AIV_Settings::get();
		$bots     = NSW_AIV_Bots::all();
		$name     = NSW_AIV_OPTION;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'AI Visibility', 'nsw-ai-visibility' ); ?></h1>
			<p><?php esc_html_e( 'Choose which AI crawlers may read your site, publish an llms.txt file, and see which pages AI bots visit.', 'nsw-ai-visibility' ); ?></p>

			<?php self::notices(); ?>

			<h2><?php esc_html_e( 'AI bot activity, last 30 days', 'nsw-ai-visibility' ); ?></h2>
			<?php self::render_stats(); ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'nsw_aiv' ); ?>

				<h2><?php esc_html_e( 'AI crawlers', 'nsw-ai-visibility' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Blocking an AI search bot can remove your pages from its answers. Blocking a training bot only stops your content from being used to train models.', 'nsw-ai-visibility' ); ?></p>
				<table class="widefat striped" style="max-width:900px">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Block', 'nsw-ai-visibility' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Bot', 'nsw-ai-visibility' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Company', 'nsw-ai-visibility' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Purpose', 'nsw-ai-visibility' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $bots as $token => $bot ) : ?>
						<?php $id = 'nsw-aiv-bot-' . sanitize_html_class( $token ); ?>
						<tr>
							<td>
								<input type="checkbox" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>[blocked_bots][]" value="<?php echo esc_attr( $token ); ?>" <?php checked( in_array( $token, (array) $settings['blocked_bots'], true ) ); ?>>
							</td>
							<td><label for="<?php echo esc_attr( $id ); ?>"><code><?php echo esc_html( $token ); ?></code></label></td>
							<td><?php echo esc_html( $bot['vendor'] ); ?></td>
							<td><?php echo esc_html( NSW_AIV_Bots::purpose_label( $bot['purpose'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>

				<h2><?php esc_html_e( 'llms.txt', 'nsw-ai-visibility' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Publish llms.txt', 'nsw-ai-visibility' ); ?></th>
						<td>
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
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="nsw-aiv-intro"><?php esc_html_e( 'Introduction', 'nsw-ai-visibility' ); ?></label></th>
						<td>
							<textarea id="nsw-aiv-intro" class="large-text" rows="4" name="<?php echo esc_attr( $name ); ?>[llms_intro]"><?php echo esc_textarea( $settings['llms_intro'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'A few sentences on who you are, what you offer and where. This is what AI assistants will read first.', 'nsw-ai-visibility' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="nsw-aiv-posts"><?php esc_html_e( 'Latest articles to list', 'nsw-ai-visibility' ); ?></label></th>
						<td><input type="number" id="nsw-aiv-posts" min="0" max="50" class="small-text" name="<?php echo esc_attr( $name ); ?>[llms_posts]" value="<?php echo esc_attr( $settings['llms_posts'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="nsw-aiv-manual"><?php esc_html_e( 'Write the file yourself', 'nsw-ai-visibility' ); ?></label></th>
						<td>
							<textarea id="nsw-aiv-manual" class="large-text code" rows="6" name="<?php echo esc_attr( $name ); ?>[llms_manual]"><?php echo esc_textarea( $settings['llms_manual'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Optional. When filled in, this Markdown replaces the generated file. Pages marked noindex in Rank Math or Yoast are never listed in the generated file.', 'nsw-ai-visibility' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Tracking', 'nsw-ai-visibility' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Count AI bot visits', 'nsw-ai-visibility' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[track_enabled]" value="1" <?php checked( $settings['track_enabled'] ); ?>>
								<?php esc_html_e( 'Only known AI bots are counted. No IP address or visitor data is stored.', 'nsw-ai-visibility' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="nsw-aiv-retention"><?php esc_html_e( 'Keep data for', 'nsw-ai-visibility' ); ?></label></th>
						<td>
							<input type="number" id="nsw-aiv-retention" min="7" max="365" class="small-text" name="<?php echo esc_attr( $name ); ?>[retention]" value="<?php echo esc_attr( $settings['retention'] ); ?>">
							<?php esc_html_e( 'days', 'nsw-ai-visibility' ); ?>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Warn when a physical file overrides ours.
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
	}

	/**
	 * Activity tables.
	 */
	private static function render_stats() {
		$by_bot = NSW_AIV_Tracker::by_bot( 30 );
		$paths  = NSW_AIV_Tracker::top_paths( 30, 10 );

		if ( empty( $by_bot ) ) {
			echo '<p>' . esc_html__( 'No AI bot visit recorded yet. Data appears as soon as a known AI crawler reads one of your pages.', 'nsw-ai-visibility' ) . '</p>';
			return;
		}
		?>
		<div style="display:flex;gap:24px;flex-wrap:wrap;align-items:flex-start">
			<table class="widefat striped" style="max-width:440px">
				<thead><tr>
					<th scope="col"><?php esc_html_e( 'Bot', 'nsw-ai-visibility' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Visits', 'nsw-ai-visibility' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Pages', 'nsw-ai-visibility' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Last seen', 'nsw-ai-visibility' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $by_bot as $row ) : ?>
					<tr>
						<td><code><?php echo esc_html( $row['bot'] ); ?></code></td>
						<td><?php echo esc_html( number_format_i18n( $row['hits'] ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $row['pages'] ) ); ?></td>
						<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $row['last_seen'] ) ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<table class="widefat striped" style="max-width:560px">
				<thead><tr>
					<th scope="col"><?php esc_html_e( 'Most visited page', 'nsw-ai-visibility' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Visits', 'nsw-ai-visibility' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Bots', 'nsw-ai-visibility' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $paths as $row ) : ?>
					<tr>
						<td><a href="<?php echo esc_url( home_url( $row['path'] ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $row['path'] ); ?></a></td>
						<td><?php echo esc_html( number_format_i18n( $row['hits'] ) ); ?></td>
						<td><?php echo esc_html( $row['bots'] ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
