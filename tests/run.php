<?php
/**
 * Smoke tests: php tests/run.php
 *
 * @package NSW_AI_Visibility
 */

// phpcs:disable

require __DIR__ . '/bootstrap.php';

$failures = 0;
$count    = 0;

function check( $label, $condition ) {
	global $failures, $count;
	$count++;
	if ( $condition ) {
		echo "  ok   $label\n";
	} else {
		$failures++;
		echo "  FAIL $label\n";
	}
}

echo "Bot detection\n";
check( 'detects GPTBot', 'GPTBot' === NSW_AIV_Bots::detect( 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GPTBot/1.2; +https://openai.com/gptbot)' ) );
check( 'detects ClaudeBot', 'ClaudeBot' === NSW_AIV_Bots::detect( 'Mozilla/5.0 (compatible; ClaudeBot/1.0; +claudebot@anthropic.com)' ) );
check( 'detects PerplexityBot case-insensitively', 'PerplexityBot' === NSW_AIV_Bots::detect( 'mozilla/5.0 (compatible; perplexitybot/1.0)' ) );
check( 'ignores a normal browser', null === NSW_AIV_Bots::detect( 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) Safari/605.1.15' ) );
check( 'ignores Googlebot (not an AI crawler token)', null === NSW_AIV_Bots::detect( 'Mozilla/5.0 (compatible; Googlebot/2.1)' ) );
check( 'ignores empty user agent', null === NSW_AIV_Bots::detect( '' ) );

echo "Settings\n";
$clean = NSW_AIV_Settings::sanitize( array( 'blocked_bots' => array( 'GPTBot', 'NotABot', '<b>CCBot</b>' ), 'llms_posts' => 999, 'retention' => 1 ) );
check( 'keeps only known bots', array( 'GPTBot', 'CCBot' ) === $clean['blocked_bots'] );
check( 'caps llms_posts at 50', 50 === $clean['llms_posts'] );
check( 'retention minimum is 7 days', 7 === $clean['retention'] );
check( 'unchecked boxes become 0', 0 === $clean['llms_enabled'] && 0 === $clean['track_enabled'] );

echo "robots.txt\n";
update_option( NSW_AIV_OPTION, array( 'blocked_bots' => array() ) );
check( 'no rules when nothing is blocked', "User-agent: *\n" === NSW_AIV_Robots::filter( "User-agent: *\n", true ) );
update_option( NSW_AIV_OPTION, array( 'blocked_bots' => array( 'GPTBot', 'CCBot' ) ) );
$robots = NSW_AIV_Robots::filter( "User-agent: *\nDisallow: /wp-admin/\n", true );
check( 'adds a Disallow block per blocked bot', false !== strpos( $robots, "User-agent: GPTBot\nDisallow: /" ) && false !== strpos( $robots, "User-agent: CCBot\nDisallow: /" ) );
check( 'keeps the original rules', 0 === strpos( $robots, "User-agent: *\nDisallow: /wp-admin/" ) );
check( 'adds nothing when the site is not public', "X\n" === NSW_AIV_Robots::filter( "X\n", false ) );

echo "llms.txt\n";
update_option( NSW_AIV_OPTION, array( 'llms_intro' => 'Dépannage 7j/7 à Paris.', 'llms_posts' => 5 ) );
nsw_test_post( 1, 'page', 'Accueil', 'accueil', '<p>Bienvenue chez nous.</p>' );
nsw_test_post( 2, 'page', 'Serrurier Paris 15', 'serrurier-paris-15', 'Texte', '' );
nsw_test_post( 3, 'page', 'Mentions légales', 'mentions-legales', 'Texte légal' );
nsw_test_post( 4, 'post', 'Changer un cylindre', 'changer-cylindre', 'Un article [gallery ids="1"] utile sur les cylindres.' );
$GLOBALS['nsw_test_meta'][2]['rank_math_description'] = 'Intervention en 20 minutes dans le 15e.';
$GLOBALS['nsw_test_meta'][3]['rank_math_robots']      = array( 'noindex' );
$GLOBALS['nsw_test_meta'][1]['_yoast_wpseo_metadesc'] = '%%excerpt%%';

NSW_AIV_Llms::flush_cache();
$llms = NSW_AIV_Llms::content();
check( 'starts with the decoded site name as H1', 0 === strpos( $llms, "# Atelier Démo & Fils\n" ) );
check( 'includes the tagline as a quote', false !== strpos( $llms, '> Serrurier à Paris' ) );
check( 'includes the intro', false !== strpos( $llms, 'Dépannage 7j/7 à Paris.' ) );
check( 'uses the Rank Math description', false !== strpos( $llms, '- [Serrurier Paris 15](https://example.test/serrurier-paris-15/): Intervention en 20 minutes dans le 15e.' ) );
check( 'skips noindex pages', false === strpos( $llms, 'Mentions légales' ) );
check( 'skips unrendered SEO variables and falls back to content', false !== strpos( $llms, '- [Accueil](https://example.test/accueil/): Bienvenue chez nous.' ) );
check( 'strips shortcodes from summaries', false === strpos( $llms, '[gallery' ) );
check( 'lists articles in their own section', false !== strpos( $llms, "## Latest articles\n- [Changer un cylindre]" ) );
check( 'result is cached', $llms === get_transient( NSW_AIV_Llms::CACHE_KEY ) );

update_option( NSW_AIV_OPTION, array( 'llms_manual' => "# Mon site\n\nTexte écrit à la main." ) );
NSW_AIV_Llms::flush_cache();
check( 'manual content replaces the generated file', "# Mon site\n\nTexte écrit à la main.\n" === NSW_AIV_Llms::content() );

echo "\n$count checks, $failures failure(s)\n";
exit( $failures > 0 ? 1 : 0 );
