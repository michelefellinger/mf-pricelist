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
function mfpl_render_services( array $data, array $category, int $now, string $instance ): string {
    $services = array_filter( $data['services'], static fn( $s ) => $s['category'] === $category['id'] );
    if ( ! $services ) { return ''; }
    $notes = array();
    ob_start();
    ?>
            <dl class="mfpl-services">
            <?php foreach ( $services as $service ) :
                $variants = mfpl_service_variants( $service['prices'] );
                $stacked = ( $service['length_layout'] ?? 'columns' ) === 'stacked' && ! isset( $variants[''] );
                ?>
                <div class="mfpl-service<?php echo $stacked ? ' mfpl-service-stacked' : ''; ?>"><dt><?php echo esc_html( $service['name'] ); ?><?php if ( '' !== $service['description'] ) : ?><span class="mfpl-description"><?php echo nl2br( esc_html( $service['description'] ) ); ?></span><?php endif; ?></dt>
                <dd class="mfpl-prices"><?php foreach ( $variants as $variant => $label ) :
                    $price = mfpl_current_price( $service['prices'], $now, $variant );
                    if ( null === $price ) { continue; }
                    $number = null;
                    if ( '' !== $price['note'] ) {
                        $key = array_search( $price['note'], $notes, true );
                        if ( false === $key ) { $notes[] = $price['note']; $key = count( $notes ) - 1; }
                        $number = $key + 1;
                    }
                    $note_id = $instance . '-' . $category['id'] . '-note-' . $number;
                    ?><span class="mfpl-price"><?php if ( '' !== $variant ) : ?><span class="mfpl-length"><?php echo esc_html( $label ); ?></span> <?php endif; ?><span class="mfpl-price-value"><?php
                    if ( 'request' === $price['type'] ) { echo 'Auf Anfrage'; }
                    else { echo esc_html( ( 'from' === $price['type'] ? 'ab ' : '' ) . number_format_i18n( (float) $price['amount'], 2 ) . ' €' ); }
                    if ( null !== $number ) : ?><sup><a href="#<?php echo esc_attr( $note_id ); ?>" aria-label="<?php echo esc_attr( 'Preishinweis ' . $number ); ?>"><?php echo esc_html( (string) $number ); ?></a></sup><?php endif; ?></span></span>
                <?php endforeach; ?></dd></div>
            <?php endforeach; ?></dl>
            <?php if ( $notes ) : ?><ol class="mfpl-notes" aria-label="Preishinweise"><?php foreach ( $notes as $index => $note ) : ?><li tabindex="-1" id="<?php echo esc_attr( $instance . '-' . $category['id'] . '-note-' . ( $index + 1 ) ); ?>"><?php echo esc_html( $note ); ?></li><?php endforeach; ?></ol><?php endif; ?>
    <?php
    return ob_get_clean();
}

function mfpl_render_description( array $category ): string {
    return empty( $category['description'] ) ? '' : '<p class="mfpl-category-description">' . nl2br( esc_html( $category['description'] ) ) . '</p>';
}

function mfpl_render( array $data, int $now ): string {
    $instance = wp_unique_id( 'mfpl-' );
    $html = '';
    foreach ( $data['categories'] as $parent ) {
        if ( '' !== $parent['parent'] ) { continue; }
        $content = mfpl_render_services( $data, $parent, $now, $instance );
        foreach ( $data['categories'] as $category ) {
            if ( $category['parent'] !== $parent['id'] ) { continue; }
            $services = mfpl_render_services( $data, $category, $now, $instance );
            if ( '' === $services && empty( $category['description'] ) ) { continue; }
            $content .= '<section class="mfpl-category"><h3>' . esc_html( $category['name'] ) . '</h3>' . mfpl_render_description( $category ) . $services . '</section>';
        }
        if ( '' !== $content || ! empty( $parent['description'] ) ) {
            $html .= '<section class="mfpl-group"><h2>' . esc_html( $parent['name'] ) . '</h2>' . mfpl_render_description( $parent ) . $content . '</section>';
        }
    }
    return '' !== $html ? $html : '<p>Derzeit sind keine Leistungen hinterlegt.</p>';
}
