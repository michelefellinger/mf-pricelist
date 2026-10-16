<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
add_shortcode( 'preisliste', static function (): string {
    wp_enqueue_style( 'mfpl-public', MFPL_URL . 'assets/public.css', array(), MFPL_VERSION );
    wp_enqueue_script( 'mfpl-public', MFPL_URL . 'assets/public.js', array(), MFPL_VERSION, true );
    return '<div class="mfpl" data-endpoint="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '" aria-busy="true"><p role="status">Preisliste wird geladen …</p><noscript>Bitte JavaScript aktivieren, um die aktuell gültigen Preise zu laden.</noscript></div>';
} );
add_action( 'wp_ajax_mfpl_public', 'mfpl_public_response' );
add_action( 'wp_ajax_nopriv_mfpl_public', 'mfpl_public_response' );
function mfpl_public_response(): void {
    nocache_headers();
    header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
    $data = mfpl_data();
    $now = time();
    $next = null;
    foreach ( $data['services'] as $service ) {
        foreach ( $service['prices'] as $price ) {
            if ( $price['timestamp'] > $now && ( null === $next || $price['timestamp'] < $next ) ) { $next = $price['timestamp']; }
        }
    }
    wp_send_json_success( array( 'html' => mfpl_render( $data, $now ), 'refresh_after' => null === $next ? 30 : max( 1, min( 30, $next - $now ) ) ) );
}
function mfpl_render( array $data, int $now ): string {
    $instance = wp_unique_id( 'mfpl-' );
    ob_start();
    $has_services = false;
    foreach ( $data['categories'] as $parent ) {
        if ( '' !== $parent['parent'] ) { continue; }
        $children_html = '';
        foreach ( $data['categories'] as $category ) {
            if ( $category['parent'] !== $parent['id'] ) { continue; }
            $services = array_filter( $data['services'], static fn( $s ) => $s['category'] === $category['id'] );
            if ( ! $services ) { continue; }
            $has_services = true;
            $notes = array();
            ob_start();
            ?>
            <section class="mfpl-category"><h3><?php echo esc_html( $category['name'] ); ?></h3>
            <dl class="mfpl-services">
            <?php foreach ( $services as $service ) :
                ?>
                <div class="mfpl-service"><dt><?php echo esc_html( $service['name'] ); ?><?php if ( '' !== $service['description'] ) : ?><span class="mfpl-description"><?php echo nl2br( esc_html( $service['description'] ) ); ?></span><?php endif; ?></dt>
                <dd class="mfpl-prices"><?php foreach ( mfpl_service_variants( $service['prices'] ) as $variant => $label ) :
                    $price = mfpl_current_price( $service['prices'], $now, $variant );
                    if ( null === $price ) { continue; }
                    $number = null;
                    if ( '' !== $price['note'] ) {
                        $key = array_search( $price['note'], $notes, true );
                        if ( false === $key ) { $notes[] = $price['note']; $key = count( $notes ) - 1; }
                        $number = $key + 1;
                    }
                    $note_id = $instance . '-' . $category['id'] . '-note-' . $number;
                    ?><span class="mfpl-price"><?php if ( '' !== $variant ) : ?><span class="mfpl-length"><?php echo esc_html( $label ); ?></span> <?php endif;
                    if ( 'request' === $price['type'] ) { echo 'Auf Anfrage'; }
                    else { echo esc_html( ( 'from' === $price['type'] ? 'ab ' : '' ) . number_format_i18n( (float) $price['amount'], 2 ) . ' €' ); }
                    if ( null !== $number ) : ?><sup><a href="#<?php echo esc_attr( $note_id ); ?>" aria-label="<?php echo esc_attr( 'Preishinweis ' . $number ); ?>"><?php echo esc_html( (string) $number ); ?></a></sup><?php endif; ?></span>
                <?php endforeach; ?></dd></div>
            <?php endforeach; ?></dl>
            <?php if ( $notes ) : ?><ol class="mfpl-notes" aria-label="Preishinweise"><?php foreach ( $notes as $index => $note ) : ?><li tabindex="-1" id="<?php echo esc_attr( $instance . '-' . $category['id'] . '-note-' . ( $index + 1 ) ); ?>"><?php echo esc_html( $note ); ?></li><?php endforeach; ?></ol><?php endif; ?>
            </section>
            <?php
            $children_html .= ob_get_clean();
        }
        if ( '' !== $children_html ) { echo '<section class="mfpl-group"><h2>' . esc_html( $parent['name'] ) . '</h2>' . $children_html . '</section>'; } // Inner HTML is escaped above.
    }
    if ( ! $has_services ) { echo '<p>Derzeit sind keine Leistungen hinterlegt.</p>'; }
    return ob_get_clean();
}
