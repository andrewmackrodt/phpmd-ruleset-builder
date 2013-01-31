<?php
// begin output buffering
ob_start();

define( 'BASE_PATH'   , realpath( __DIR__ . '/../..' ) );
define( 'CONFIG_PATH' , BASE_PATH . '/conf' );
define( 'SRC_PATH'    , BASE_PATH . '/src' );
define( 'PRIVATE_PATH', SRC_PATH . '/private' );
define( 'PUBLIC_PATH' , SRC_PATH . '/public' );

require_once CONFIG_PATH  . '/settings.php';
require_once PRIVATE_PATH . '/functions.php';

require PRIVATE_PATH . '/views/header.phtml';

if ( get_rulesets() ) {
    require PRIVATE_PATH . '/views/ruleset.phtml';
} else {
    header( 'HTTP/1.1 500 Internal Server Error' );
    require PRIVATE_PATH . '/views/error.phtml';
}

require PRIVATE_PATH . '/views/footer.phtml';