<?php
/**
 * Generates and serves /llms.txt.
 *
 * @package NSW_AI_Visibility
 */

defined( 'ABSPATH' ) || exit;

/**
 * Llms.txt endpoint.
 *
 * Follows the llms.txt proposal (https://llmstxt.org): a Markdown file at
 * the site root giving language models a short, curated map of the site.
 */
class NSW_AIV_Llms {

	const QUERY_VAR = 'nsw_aiv_llms';
	const CACHE_KEY = 'nsw_aiv_llms_cache';

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'add_rewrite_rule' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'serve' ), 0 );
		add_filter( 'redirect_canonical', array( __CLASS__, 'no_trailing_slash' ), 10, 2 );

		// Rebuild the file when content or settings change.
		add_action( 'save_post', array( __CLASS__, 'flush_cache' ) );
		add_action( 'deleted_post', array( __CLASS__, 'flush_cache' ) );
		add_action( 'update_option_' . NSW_AIV_OPTION, array( __CLASS__, 'flush_cache' ) );
	}

	/**
	 * Map /llms.txt to our query var.
	 */
	public static function add_rewrite_rule() {
		add_rewrite_rule( '^llms\.txt$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
	}

	/**
	 * Register the query var.
	 *
	 * @param array $vars Public query vars.
	 * @return array
	 */
	public static function query_vars( $vars ) {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * Stop WordPress from redirecting /llms.txt to /llms.txt/.
	 *
	 * @param string|false $redirect_url Redirect target.
	 * @param string       $requested_url Requested URL.
	 * @return string|false
	 */
	public static function no_trailing_slash( $redirect_url, $requested_url ) {
		if ( get_query_var( self::QUERY_VAR ) ) {
			return false;
		}
		return $redirect_url;
	}

	/**
	 * Output the file.
	 */
	public static function serve() {
		if ( ! get_query_var( self::QUERY_VAR ) ) {
			return;
		}

		$settings = NSW_AIV_Settings::get();
		if ( empty( $settings['llms_enabled'] ) ) {
			status_header( 404 );
			nocache_headers();
			exit;
		}

		status_header( 200 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		echo self::content(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain text Markdown, built from sanitized values.
		exit;
	}

	/**
	 * File content, cached for a day.
	 *
	 * @return string
	 */
	public static function content() {
		$cached = get_transient( self::CACHE_KEY );
		if ( false !== $cached ) {
			return $cached;
		}

		$content = self::build();
		set_transient( self::CACHE_KEY, $content, DAY_IN_SECONDS );
		return $content;
	}

	/**
	 * Clear the cached file.
	 */
	public static function flush_cache() {
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * Build the Markdown document.
	 *
	 * @return string
	 */
	public static function build() {
		$settings = NSW_AIV_Settings::get();

		if ( '' !== trim( $settings['llms_manual'] ) ) {
			return trim( $settings['llms_manual'] ) . "\n";
		}

		$out   = array();
		$out[] = '# ' . self::clean( get_bloginfo( 'name' ) );

		$tagline = self::clean( get_bloginfo( 'description' ) );
		if ( '' !== $tagline ) {
			$out[] = '';
			$out[] = '> ' . $tagline;
		}

		if ( '' !== trim( $settings['llms_intro'] ) ) {
			$out[] = '';
			$out[] = trim( $settings['llms_intro'] );
		}

		$pages = self::query( 'page', 30, array( 'menu_order' => 'ASC', 'title' => 'ASC' ) );
		if ( $pages ) {
			$out[] = '';
			$out[] = '## ' . __( 'Pages', 'nsw-ai-visibility' );
			$out   = array_merge( $out, self::lines( $pages ) );
		}

		$limit = (int) $settings['llms_posts'];
		if ( $limit > 0 ) {
			$posts = self::query( 'post', $limit, array( 'date' => 'DESC' ) );
			if ( $posts ) {
				$out[] = '';
				$out[] = '## ' . __( 'Latest articles', 'nsw-ai-visibility' );
				$out   = array_merge( $out, self::lines( $posts ) );
			}
		}

		/**
		 * Filter the generated llms.txt lines before they are joined.
		 *
		 * @param string[] $out Markdown lines.
		 */
		$out = apply_filters( 'nsw_aiv_llms_lines', $out );

		return implode( "\n", $out ) . "\n";
	}

	/**
	 * Published, indexable posts of a type.
	 *
	 * @param string $type    Post type.
	 * @param int    $limit   Max results.
	 * @param array  $orderby Order clause.
	 * @return WP_Post[]
	 */
	private static function query( $type, $limit, $orderby ) {
		$posts = get_posts(
			array(
				'post_type'        => $type,
				'post_status'      => 'publish',
				'posts_per_page'   => $limit * 2, // Room for skipped noindex entries.
				'orderby'          => $orderby,
				'has_password'     => false,
				'suppress_filters' => false,
			)
		);

		$posts = array_filter( $posts, array( __CLASS__, 'is_indexable' ) );
		return array_slice( array_values( $posts ), 0, $limit );
	}

	/**
	 * Skip pages marked noindex in Rank Math or Yoast SEO.
	 *
	 * @param WP_Post $post Post object.
	 * @return bool
	 */
	public static function is_indexable( $post ) {
		$rank_math = get_post_meta( $post->ID, 'rank_math_robots', true );
		if ( is_array( $rank_math ) && in_array( 'noindex', $rank_math, true ) ) {
			return false;
		}

		if ( '1' === (string) get_post_meta( $post->ID, '_yoast_wpseo_meta-robots-noindex', true ) ) {
			return false;
		}

		return (bool) apply_filters( 'nsw_aiv_llms_include_post', true, $post );
	}

	/**
	 * One Markdown list line per post: link and short summary.
	 *
	 * @param WP_Post[] $posts Posts.
	 * @return string[]
	 */
	private static function lines( $posts ) {
		$lines = array();
		foreach ( $posts as $post ) {
			$title   = self::clean( get_the_title( $post ) );
			$summary = self::summary( $post );
			$line    = '- [' . $title . '](' . esc_url_raw( get_permalink( $post ) ) . ')';
			if ( '' !== $summary ) {
				$line .= ': ' . $summary;
			}
			$lines[] = $line;
		}
		return $lines;
	}

	/**
	 * Short summary: SEO meta description first, then excerpt.
	 *
	 * @param WP_Post $post Post object.
	 * @return string
	 */
	private static function summary( $post ) {
		$candidates = array(
			get_post_meta( $post->ID, 'rank_math_description', true ),
			get_post_meta( $post->ID, '_yoast_wpseo_metadesc', true ),
			$post->post_excerpt,
			wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 25, '…' ),
		);

		foreach ( $candidates as $text ) {
			$text = self::clean( (string) $text );
			// Skip unrendered SEO plugin variables such as %excerpt%.
			if ( '' !== $text && false === strpos( $text, '%' ) ) {
				return $text;
			}
		}
		return '';
	}

	/**
	 * Flatten text to a single safe line.
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	private static function clean( $text ) {
		$text = html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES, 'UTF-8' );
		return trim( preg_replace( '/\s+/u', ' ', $text ) );
	}

	/**
	 * Whether a physical llms.txt file is served instead of ours.
	 *
	 * @return bool
	 */
	public static function has_physical_file() {
		return file_exists( trailingslashit( ABSPATH ) . 'llms.txt' );
	}
}
