<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/functions.php';

$dotenv = Dotenv\Dotenv::create( __DIR__ . '/..' );
$dotenv->safeLoad();

// begin output buffering
ob_start( 'ob_gzhandler' );

/**
 * @param string $key     The array key
 * @param string $default The return value if $_SERVER[$key] is not defined
 * @return string
 */
$server = function( $key, $default = null ) {
    return array_key_exists( $key, $_SERVER ) ? $_SERVER[$key] : $default;
};

// automatically detect the sites root url
$scheme = strcasecmp( $server( 'HTTPS' ), 'on' ) === 0 ? 'https' : 'http';
$host = $server( 'HTTP_HOST' ) ?: gethostname();
$filename = basename( $server( 'SCRIPT_NAME' ) );
$parents = rtrim( substr( $server( 'SCRIPT_NAME' ), 0, -strlen( $filename ) ), '/' );

$requestUri = rtrim( $server( 'REQUEST_URI' ), '/' );
$requestUri = substr( $requestUri, strlen( $parents ) ) ?: '/';

$translated = false;

// adjust the request uri PATH_INFO, i.e. $_SERVER['SCRIPT_NAME'] != $_SERVER['PHP_SELF']
if ( strpos( $requestUri, "/{$filename}/" ) === 0 ) {
    $requestUri = substr( $requestUri, strlen( $filename ) + 1 );
    $translated = true;
}

define( 'BASE_PATH'   , realpath( __DIR__ . '/..' ) );
define( 'CONFIG_PATH' , BASE_PATH . DIRECTORY_SEPARATOR . 'config' );
define( 'PUBLIC_PATH' , BASE_PATH . DIRECTORY_SEPARATOR . 'public' );
define( 'VIEWS_PATH' , BASE_PATH . DIRECTORY_SEPARATOR . 'resources/views' );

define( 'BASE_URL'    , "{$scheme}://{$host}{$parents}" );

require_once CONFIG_PATH . '/app.php';

if ( !preg_match( '#^/(?:index\.php)?(?:\?.*)?$#', $requestUri ) ) {
    header( 'HTTP/1.1 404 Not Found' );
    echo 'File Not Found';
    return;
}

require VIEWS_PATH . '/header.phtml';

if ( get_rulesets() ) {
    require VIEWS_PATH . '/ruleset.phtml';
} else {
    header( 'HTTP/1.1 500 Internal Server Error' );
    require VIEWS_PATH . '/error.phtml';
}

require VIEWS_PATH . '/footer.phtml';
