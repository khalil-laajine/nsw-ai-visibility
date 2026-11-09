<?php
/**
 * Known AI crawlers and how to recognise them.
 *
 * @package NSW_AI_Visibility
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registry of AI user agents.
 */
class NSW_AIV_Bots {

	/**
	 * Known AI crawlers, keyed by the token used in robots.txt.
	 *
	 * Each entry: vendor, purpose ("search" bots cite and link to the site,
	 * "training" bots collect content for model training, "agent" bots fetch
	 * a page on behalf of a user).
	 *
	 * @return array<string, array{vendor: string, purpose: string}>
	 */
	public static function all() {
		$bots = array(
			'GPTBot'             => array( 'vendor' => 'OpenAI', 'purpose' => 'training' ),
			'OAI-SearchBot'      => array( 'vendor' => 'OpenAI', 'purpose' => 'search' ),
			'ChatGPT-User'       => array( 'vendor' => 'OpenAI', 'purpose' => 'agent' ),
			'ClaudeBot'          => array( 'vendor' => 'Anthropic', 'purpose' => 'training' ),
			'Claude-SearchBot'   => array( 'vendor' => 'Anthropic', 'purpose' => 'search' ),
			'Claude-User'        => array( 'vendor' => 'Anthropic', 'purpose' => 'agent' ),
			'PerplexityBot'      => array( 'vendor' => 'Perplexity', 'purpose' => 'search' ),
			'Perplexity-User'    => array( 'vendor' => 'Perplexity', 'purpose' => 'agent' ),
			'Google-Extended'    => array( 'vendor' => 'Google', 'purpose' => 'training' ),
			'Applebot-Extended'  => array( 'vendor' => 'Apple', 'purpose' => 'training' ),
			'meta-externalagent' => array( 'vendor' => 'Meta', 'purpose' => 'training' ),
			'Bytespider'         => array( 'vendor' => 'ByteDance', 'purpose' => 'training' ),
			'CCBot'              => array( 'vendor' => 'Common Crawl', 'purpose' => 'training' ),
			'MistralAI-User'     => array( 'vendor' => 'Mistral', 'purpose' => 'agent' ),
		);

		/**
		 * Filter the list of AI crawlers handled by the plugin.
		 *
		 * @param array $bots Bots keyed by robots.txt token.
		 */
		return apply_filters( 'nsw_aiv_bots', $bots );
	}

	/**
	 * Find which known AI bot sent a request, from its user agent.
	 *
	 * Google-Extended and Applebot-Extended are robots.txt tokens only: they
	 * never appear in a user agent, so they are skipped here.
	 *
	 * @param string $user_agent Raw user agent string.
	 * @return string|null Bot token, or null when the request is not from a known AI bot.
	 */
	public static function detect( $user_agent ) {
		if ( '' === $user_agent ) {
			return null;
		}

		foreach ( array_keys( self::all() ) as $token ) {
			if ( in_array( $token, array( 'Google-Extended', 'Applebot-Extended' ), true ) ) {
				continue;
			}
			if ( false !== stripos( $user_agent, $token ) ) {
				return $token;
			}
		}

		return null;
	}

	/**
	 * Human label for a purpose key.
	 *
	 * @param string $purpose Purpose key.
	 * @return string
	 */
	public static function purpose_label( $purpose ) {
		$labels = array(
			'search'   => __( 'AI search (cites your pages)', 'nsw-ai-visibility' ),
			'agent'    => __( 'Fetches a page for a user', 'nsw-ai-visibility' ),
			'training' => __( 'Model training', 'nsw-ai-visibility' ),
		);

		return isset( $labels[ $purpose ] ) ? $labels[ $purpose ] : $purpose;
	}
}
