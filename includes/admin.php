<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
add_action( 'admin_menu', static function () {
    add_menu_page( 'MF PriceList', 'MF PriceList', 'manage_options', 'mf-pricelist', 'mfpl_admin', 'dashicons-list-view' );
} );
add_action( 'admin_enqueue_scripts', static function ( $hook ) {
    if ( 'toplevel_page_mf-pricelist' !== $hook ) { return; }
    wp_enqueue_style( 'mfpl-admin', MFPL_URL . 'assets/admin.css', array(), MFPL_VERSION );
    wp_enqueue_script( 'mfpl-admin', MFPL_URL . 'assets/admin.js', array(), MFPL_VERSION, true );
    wp_add_inline_script( 'mfpl-admin', 'window.mfplData = ' . wp_json_encode( mfpl_data(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . ';', 'before' );
} );
add_action( 'admin_post_mfpl_save', static function () {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Keine Berechtigung.', '', array( 'response' => 403 ) ); }
    check_admin_referer( 'mfpl_save' );
    $raw = isset( $_POST['document'] ) && is_string( $_POST['document'] ) ? wp_unslash( $_POST['document'] ) : '';
    if ( strlen( $raw ) > 2000000 ) { wp_die( 'Die Preisliste ist zu groß.' ); }
    try {
        $input = json_decode( $raw, true, 32, JSON_THROW_ON_ERROR );
        if ( ! is_array( $input ) ) { throw new InvalidArgumentException( 'Ungültiges Dokument.' ); }
        $data = mfpl_validate( $input );
        update_option( 'mfpl_data', $data, false );
    } catch ( Throwable $error ) {
        wp_die( esc_html( $error->getMessage() ) . ' Zurück im Browser: Eingaben korrigieren und erneut speichern.', 'Preisliste nicht gespeichert', array( 'response' => 400, 'back_link' => true ) );
    }
    wp_safe_redirect( admin_url( 'admin.php?page=mf-pricelist&saved=1' ) );
    exit;
} );
function mfpl_admin(): void {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    ?>
    <div class="wrap mfpl-admin"><h1>MF PriceList</h1>
    <?php if ( isset( $_GET['saved'] ) ) : ?><div class="notice notice-success"><p>Preisliste gespeichert.</p></div><?php endif; ?>
    <p>Zwei Ebenen: Hauptkategorie → Unterkategorie → Leistungen. Reihenfolge durch Ziehen am Griff oder mit ↑/↓ ändern. Anschließend speichern.</p>
    <p>Preise in Euro. Ein Grundpreis ohne Datum ist Pflicht; weitere Preise gelten ab dem angegebenen Zeitpunkt in der WordPress-Zeitzone <strong><?php echo esc_html( wp_timezone_string() ); ?></strong>. Hinweise gehören zum jeweiligen Preis.</p>
    <p>Öffentliche Ausgabe: <code>[preisliste]</code>. Änderungen werden erst mit „Speichern“ übernommen.</p>
    <noscript><p>Für den Preislisten-Editor muss JavaScript aktiviert sein.</p></noscript>
    <form id="mfpl-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
    <input type="hidden" name="action" value="mfpl_save"><?php wp_nonce_field( 'mfpl_save' ); ?>
    <input type="hidden" id="mfpl-document" name="document">
    <h2>Kategorien</h2><div id="mfpl-categories"></div><button type="button" class="button" id="mfpl-add-category">Kategorie hinzufügen</button>
    <h2>Leistungen</h2><div id="mfpl-services"></div><button type="button" class="button" id="mfpl-add-service">Leistung hinzufügen</button>
    <p id="mfpl-status" role="status" aria-live="polite"></p>
    <p><button type="submit" class="button button-primary" id="mfpl-save" disabled>Speichern</button></p>
    </form></div>
    <?php
}
