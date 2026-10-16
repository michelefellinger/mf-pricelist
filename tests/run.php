<?php
/** Standalone domain/render tests. No assertion.ini dependency; no WordPress bootstrap. */
define( 'ABSPATH', __DIR__ );
function sanitize_text_field( $v ) { return trim( strip_tags( $v ) ); }
function sanitize_textarea_field( $v ) { return trim( strip_tags( $v ) ); }
function wp_timezone() { return new DateTimeZone( 'Europe/Berlin' ); }
function add_shortcode( ...$args ) {}
function add_action( ...$args ) {}
function esc_html( $s ) { return htmlspecialchars( $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return esc_html( $s ); }
function wp_unique_id( $prefix ) { static $i = 0; return $prefix . ++$i; }
function number_format_i18n( $n, $d ) { return number_format( $n, $d, ',', '.' ); }
require __DIR__ . '/../includes/model.php';
require __DIR__ . '/../includes/public.php';
$count = 0;
function check( $condition, $message ) { global $count; ++$count; if ( ! $condition ) { throw new RuntimeException( $message ); } }
function rejected( $input, $message ) { try { mfpl_validate( $input ); } catch ( InvalidArgumentException $e ) { check( true, $message ); return; } check( false, $message ); }
$input = array( 'categories' => array( array( 'id' => 'a', 'name' => 'Damen', 'parent' => '' ), array( 'id' => 'b', 'name' => 'Farbe', 'parent' => 'a' ) ), 'services' => array( array( 'id' => 's', 'name' => 'Strähnen', 'description' => 'Kurz', 'category' => 'b', 'prices' => array( array( 'type' => 'fixed', 'amount' => '49,50', 'valid_from' => '', 'note' => 'Je nach Haarlänge' ), array( 'type' => 'from', 'amount' => '59', 'valid_from' => '2026-10-20T10:00', 'note' => 'Je nach Haarlänge' ) ) ) ) );
$data = mfpl_validate( $input );
$prices = $data['services'][0]['prices'];
$boundary = ( new DateTimeImmutable( '2026-10-20T10:00:00', wp_timezone() ) )->getTimestamp();
check( $prices[0]['amount'] === '49.50', 'Decimal comma normalized' );
check( $prices[1]['timestamp'] === $boundary, 'WP timezone used' );
check( mfpl_current_price( $prices, $boundary - 1 )['type'] === 'fixed', 'Before boundary' );
check( mfpl_current_price( $prices, $boundary )['type'] === 'from', 'Inclusive boundary' );
check( mfpl_current_price( array_reverse( $prices ), $boundary + 1 )['type'] === 'from', 'Order independent selection' );
foreach ( array( 'third', 'cycle', 'orphan', 'service-parent', 'duplicate-id', 'negative', 'duplicate-date', 'no-base', 'bad-date', 'dst-gap', 'type' ) as $case ) {
    $bad = $input;
    switch ( $case ) {
        case 'third': $bad['categories'][] = array( 'id' => 'c', 'name' => 'X', 'parent' => 'b' ); break;
        case 'cycle': $bad['categories'][0]['parent'] = 'b'; break;
        case 'orphan': $bad['categories'][1]['parent'] = 'missing'; break;
        case 'service-parent': $bad['services'][0]['category'] = 'a'; break;
        case 'duplicate-id': $bad['categories'][] = $bad['categories'][0]; break;
        case 'negative': $bad['services'][0]['prices'][0]['amount'] = '-1'; break;
        case 'duplicate-date': $bad['services'][0]['prices'][] = $bad['services'][0]['prices'][0]; break;
        case 'no-base': array_shift( $bad['services'][0]['prices'] ); break;
        case 'bad-date': $bad['services'][0]['prices'][1]['valid_from'] = '2026-02-30T10:00'; break;
        case 'dst-gap': $bad['services'][0]['prices'][1]['valid_from'] = '2026-03-29T02:30'; break;
        case 'type': $bad['services'][0]['prices'][0]['type'] = 'unknown'; break;
    }
    rejected( $bad, $case );
}
$data['services'][] = $data['services'][0];
$data['services'][1]['name'] = '<script>alert(1)</script>';
$html = mfpl_render( $data, $boundary );
check( substr_count( $html, '<li ' ) === 1, 'Identical footnotes deduplicated per subcategory' );
check( ! str_contains( $html, '<script>' ) && str_contains( $html, '&lt;script&gt;' ), 'Render escapes stored values' );
check( str_contains( $html, 'ab 59,00 €' ), 'Localized from price' );
check( ! str_contains( $html, '49,50' ), 'Old price absent' );
$data['services'][0]['prices'] = array( array( 'timestamp' => 0, 'type' => 'request', 'amount' => '', 'note' => '' ) );
check( str_contains( mfpl_render( $data, $boundary ), 'Auf Anfrage' ), 'Request price' );
check( str_contains( mfpl_render( array( 'categories' => array(), 'services' => array() ), $boundary ), 'keine Leistungen' ), 'Empty state' );
check( mfpl_validate( array( 'categories' => array(), 'services' => array() ) )['services'] === array(), 'Empty document allowed' );
echo "OK: $count checks\n";
