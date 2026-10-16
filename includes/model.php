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
        $result['categories'][] = array( 'id' => $id, 'name' => $name, 'parent' => (string) ( $row['parent'] ?? '' ) );
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
        if ( ! isset( $categories[ $category ] ) || '' === $categories[ $category ]['parent'] ) { $fail( 'Jede Leistung muss einer Unterkategorie zugeordnet sein.' ); }
        $ids[ $id ] = true;
        if ( ! isset( $row['prices'] ) || ! is_array( $row['prices'] ) || count( $row['prices'] ) > 100 ) { $fail( 'Ungültige Preisliste (maximal 100 Preise je Leistung).' ); }
        $prices = array();
        $dates = array();
        foreach ( $row['prices'] as $price ) {
            if ( ! is_array( $price ) ) { $fail( 'Ungültiger Preis.' ); }
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
            if ( isset( $dates[ $timestamp ] ) ) { $fail( 'Gültigkeitszeitpunkte dürfen je Leistung nicht doppelt vorkommen.' ); }
            $dates[ $timestamp ] = true;
            $prices[] = array( 'type' => $type, 'amount' => 'request' === $type ? '' : $amount, 'valid_from' => $local, 'timestamp' => $timestamp, 'note' => sanitize_text_field( $price['note'] ?? '' ) );
        }
        if ( ! isset( $dates[0] ) ) { $fail( 'Jede Leistung braucht einen Grundpreis ohne Datum.' ); }
        usort( $prices, static fn( $a, $b ) => $a['timestamp'] <=> $b['timestamp'] );
        $result['services'][] = array( 'id' => $id, 'name' => $name, 'description' => sanitize_textarea_field( $row['description'] ?? '' ), 'category' => $category, 'prices' => $prices );
    }
    return $result;
}

function mfpl_current_price( array $prices, int $now ): ?array {
    $current = null;
    foreach ( $prices as $price ) {
        if ( $price['timestamp'] <= $now && ( null === $current || $price['timestamp'] > $current['timestamp'] ) ) { $current = $price; }
    }
    return $current;
}
