<?php
/**
 * Plugin Name: MF PriceList
 * Description: Zweistufige Preislisten mit geplanten Preisen und Fußnoten.
 * Version: 1.0.0
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Author: Michele Fellinger
 * License: GPL-2.0-or-later
 * Text Domain: mf-pricelist
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
define( 'MFPL_VERSION', '1.0.0' );
define( 'MFPL_URL', plugin_dir_url( __FILE__ ) );
require_once __DIR__ . '/includes/model.php';
require_once __DIR__ . '/includes/admin.php';
require_once __DIR__ . '/includes/public.php';
