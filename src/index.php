<?php
// begin output buffering
ob_start();

require_once __DIR__ . '/../conf/settings.php';
require_once __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/header.phtml';

if ( get_rulesets() ) {
    require __DIR__ . '/includes/ruleset.phtml';
} else {
    header( 'HTTP/1.1 500 Internal Server Error' );
    require __DIR__ . '/includes/error.phtml';
}

require __DIR__ . '/includes/footer.phtml';