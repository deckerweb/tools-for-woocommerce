<?php
/** Shared direct-distribution components. */
defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/deckerweb-plugin-library/bootstrap.php';
deckerweb_library_register_v2( DDW_TWC_FILE, array(), __DIR__ . '/deckerweb-plugin-library' );
require_once __DIR__ . '/class-github-updates.php';
( new \Deckerweb\ToolsForWooCommerce\GitHubUpdates() )->register();
