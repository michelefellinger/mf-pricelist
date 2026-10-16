<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function mfpl_data(): array {
    $data = get_option( 'mfpl_data', array() );
    return is_array( $data ) ? array_merge( array( 'categories' => array(), 'services' => array() ), $data ) : array( 'categories' => array(), 'services' => array() );
}

/** Validate the whole document before replacing the option: no partial saves. */
function mfpl_validate( array $input ): array {
    $fail = static function ( string $message ): void { throw new InvalidArgumentException( $message ); };
    $result = array( 'categories' => array(), 'services' => array() );
    if ( ! isset( $input['categories'], $input['services'] ) || ! is_array( $input['categories'] ) || ! is_array( $input['services'] ) ) { $fail( 'Ungültige Datenstruktur.' ); }
    if ( count( $input['categories'] ) > 200 || count( $input['services'] ) > 2000 ) { $fail( 'Maximal 200 Kategorien und 2000 Leistungen.' ); }
    $ids = array();
    foreach ( $input['categories'] as $row ) {
        if ( ! is_array( $row ) ) { $fail( 'Ungültige Kategorie.' ); }
        $id = (string) ( $row['id'] ?? '' );
        $name = sanitize_text_field( $row['name'] ?? '' );
        if ( ! preg_match( '/^[a-zA-Z0-9_-]{1,64}$/D', $id ) || isset( $ids[ $id ] ) || '' === $name ) { $fail( 'Kategorien brauchen eindeutige IDs und einen Namen.' ); }
        $ids[ $id ] = true;
        $result['categories'][] = array( 'id' => $id, 'name' => $name, 'description' => sanitize_textarea_field( $row['description'] ?? '' ), 'parent' => (string) ( $row['parent'] ?? '' ) );
    }
    $categories = array_column( $result['categories'], null, 'id' );
    foreach ( $categories as $row ) {
        if ( '' !== $row['parent'] && ( ! isset( $categories[ $row['parent'] ] ) || '' !== $categories[ $row['parent'] ]['parent'] || $row['id'] === $row['parent'] ) ) { $fail( 'Nur Hauptkategorie > Unterkategorie ist erlaubt.' ); }
    }
    $ids = array();
    foreach ( $input['services'] as $row ) {
        if ( ! is_array( $row ) ) { $fail( 'Ungültige Leistung.' ); }
        $id = (string) ( $row['id'] ?? '' );
        $name = sanitize_text_field( $row['name'] ?? '' );
        $category = (string) ( $row['category'] ?? '' );
        if ( ! preg_match( '/^[a-zA-Z0-9_-]{1,64}$/D', $id ) || isset( $ids[ $id ] ) || '' === $name ) { $fail( 'Leistungen brauchen eindeutige IDs und einen Namen.' ); }
        if ( ! isset( $categories[ $category ] ) ) { $fail( 'Jede Leistung muss einer vorhandenen Kategorie zugeordnet sein.' ); }
        $ids[ $id ] = true;
        if ( ! isset( $row['prices'] ) || ! is_array( $row['prices'] ) || count( $row['prices'] ) > 100 ) { $fail( 'Ungültige Preisliste (maximal 100 Preise je Leistung).' ); }
        $prices = array();
        $dates = array();
        $variants = array();
        foreach ( $row['prices'] as $price ) {
            if ( ! is_array( $price ) ) { $fail( 'Ungültiger Preis.' ); }
            $variant = (string) ( $price['variant'] ?? '' );
            if ( ! array_key_exists( $variant, mfpl_variant_labels() ) ) { $fail( 'Unbekannte Preisvariante.' ); }
            $variants[ $variant ] = true;
            $type = (string) ( $price['type'] ?? '' );
            if ( ! in_array( $type, array( 'fixed', 'from', 'request' ), true ) ) { $fail( 'Unbekannte Preisart.' ); }
            $amount = str_replace( ',', '.', trim( (string) ( $price['amount'] ?? '' ) ) );
            if ( 'request' !== $type && ! preg_match( '/^\d{1,7}(\.\d{1,2})?$/D', $amount ) ) { $fail( 'Preis als positive Zahl mit maximal zwei Nachkommastellen eingeben.' ); }
            $local = (string) ( $price['valid_from'] ?? '' );
            $timestamp = 0;
            if ( '' !== $local ) {
                $date = DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i', $local, wp_timezone() );
                if ( ! $date || $date->format( 'Y-m-d\TH:i' ) !== $local ) { $fail( 'Ungültiger Gültigkeitszeitpunkt.' ); }
                $timestamp = $date->getTimestamp();
                if ( $timestamp <= 0 ) { $fail( 'Der Gültigkeitszeitpunkt muss nach 1970 liegen.' ); }
            }
            if ( isset( $dates[ $variant ][ $timestamp ] ) ) { $fail( 'Gültigkeitszeitpunkte dürfen je Längenvariante nicht doppelt vorkommen.' ); }
            $dates[ $variant ][ $timestamp ] = true;
            $prices[] = array( 'variant' => $variant, 'type' => $type, 'amount' => 'request' === $type ? '' : $amount, 'valid_from' => $local, 'timestamp' => $timestamp, 'note' => sanitize_text_field( $price['note'] ?? '' ) );
        }
        if ( ! $variants || ( isset( $variants[''] ) && count( $variants ) > 1 ) ) { $fail( 'Entweder ein allgemeiner Preis oder bis zu drei Längenvarianten je Leistung.' ); }
        foreach ( $variants as $variant => $_ ) {
            if ( ! isset( $dates[ $variant ][0] ) ) { $fail( 'Jede verwendete Preisvariante braucht einen Grundpreis ohne Datum.' ); }
        }
        usort( $prices, static fn( $a, $b ) => $a['timestamp'] <=> $b['timestamp'] );
        $result['services'][] = array( 'id' => $id, 'name' => $name, 'description' => sanitize_textarea_field( $row['description'] ?? '' ), 'category' => $category, 'length_layout' => ( ( $row['length_layout'] ?? 'columns' ) === 'stacked' ? 'stacked' : 'columns' ), 'prices' => $prices );
    }
    return $result;
}

function mfpl_current_price( array $prices, int $now, string $variant = '' ): ?array {
    $current = null;
    foreach ( $prices as $price ) {
        if ( ( $price['variant'] ?? '' ) === $variant && $price['timestamp'] <= $now && ( null === $current || $price['timestamp'] > $current['timestamp'] ) ) { $current = $price; }
    }
    return $current;
}

function mfpl_variant_labels(): array {
    return array( '' => 'Allgemein', 'short' => 'kurz', 'medium' => 'mittel', 'long' => 'lang' );
}

function mfpl_service_variants( array $prices ): array {
    $used = array_column( $prices, 'variant' );
    if ( ! $used || in_array( '', $used, true ) ) { return array( '' => 'Allgemein' ); }
    return array_intersect_key( mfpl_variant_labels(), array_flip( $used ) );
}
