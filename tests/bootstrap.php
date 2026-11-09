<?php
/**
 * Minimal WordPress stubs so the plugin logic can be tested without a full
 * WordPress install. Only the functions the tested code paths call are stubbed.
 *
 * @package NSW_AI_Visibility
 */

// phpcs:disable

define( 'ABSPATH', sys_get_temp_dir() . '/nsw-aiv-fake-root/' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );

$GLOBALS['nsw_test_options']    = array();
$GLOBALS['nsw_test_transients'] = array();
$GLOBALS['nsw_test_posts']      = array();
$GLOBALS['nsw_test_meta']       = array();

function __( $text ) { return $text; }
function apply_filters( $hook, $value ) { return $value; }
function get_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['nsw_test_options'] ) ? $GLOBALS['nsw_test_options'][ $key ] : $default; }
function update_option( $key, $value ) { $GLOBALS['nsw_test_options'][ $key ] = $value; return true; }
function wp_parse_args( $args, $defaults ) { return array_merge( $defaults, $args ); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_textarea_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function absint( $n ) { return abs( (int) $n ); }
function trailingslashit( $s ) { return rtrim( $s, '/' ) . '/'; }
function get_transient( $k ) { return isset( $GLOBALS['nsw_test_transients'][ $k ] ) ? $GLOBALS['nsw_test_transients'][ $k ] : false; }
function set_transient( $k, $v ) { $GLOBALS['nsw_test_transients'][ $k ] = $v; return true; }
function delete_transient( $k ) { unset( $GLOBALS['nsw_test_transients'][ $k ] ); return true; }
function get_bloginfo( $what ) { return 'name' === $what ? 'Atelier Démo &amp; Fils' : 'Serrurier à Paris'; }
function get_posts( $args ) { return array_values( array_filter( $GLOBALS['nsw_test_posts'], function ( $p ) use ( $args ) { return $p->post_type === $args['post_type']; } ) ); }
function get_post_meta( $id, $key ) { return isset( $GLOBALS['nsw_test_meta'][ $id ][ $key ] ) ? $GLOBALS['nsw_test_meta'][ $id ][ $key ] : ''; }
function get_the_title( $p ) { return $p->post_title; }
function get_permalink( $p ) { return 'https://example.test/' . $p->post_name . '/'; }
function esc_url_raw( $u ) { return $u; }
function wp_strip_all_tags( $s ) { return strip_tags( $s ); }
function strip_shortcodes( $s ) { return preg_replace( '/\[[^\]]*\]/', '', $s ); }
function wp_trim_words( $text, $num, $more ) { $w = preg_split( '/\s+/', trim( $text ) ); return count( $w ) > $num ? implode( ' ', array_slice( $w, 0, $num ) ) . $more : implode( ' ', $w ); }

define( 'NSW_AIV_OPTION', 'nsw_aiv_settings' );
require __DIR__ . '/../includes/class-bots.php';
require __DIR__ . '/../includes/class-settings.php';
require __DIR__ . '/../includes/class-robots.php';
require __DIR__ . '/../includes/class-llms.php';

/**
 * Create a fake post.
 */
function nsw_test_post( $id, $type, $title, $name, $content = '', $excerpt = '' ) {
	$p               = new stdClass();
	$p->ID           = $id;
	$p->post_type    = $type;
	$p->post_title   = $title;
	$p->post_name    = $name;
	$p->post_content = $content;
	$p->post_excerpt = $excerpt;
	$GLOBALS['nsw_test_posts'][] = $p;
	return $p;
}
