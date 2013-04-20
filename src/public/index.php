<?php
// begin output buffering
ob_start();

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

// enable only if using a rewrite rule from the base path to src/public
// e.g. RewriteRule .* src/public/$0
//if ( strrpos( $parents, '/src/public' ) == strlen( $parents ) - 11 ) {
//    $parents = substr( $parents, 0, -11 );
//}

$requestUri = rtrim( $server( 'REQUEST_URI' ), '/' );
$requestUri = substr( $requestUri, strlen( $parents ) ) ?: '/';

$translated = false;

// adjust the request uri PATH_INFO, i.e. $_SERVER['SCRIPT_NAME'] != $_SERVER['PHP_SELF']
if ( strpos( $requestUri, "/{$filename}/" ) === 0 ) {
    $requestUri = substr( $requestUri, strlen( $filename ) + 1 );
    $translated = true;
}

define( 'BASE_PATH'   , realpath( __DIR__ . '/../..' ) );
define( 'CONFIG_PATH' , BASE_PATH . DIRECTORY_SEPARATOR . 'conf' );
define( 'SRC_PATH'    , BASE_PATH . DIRECTORY_SEPARATOR . 'src' );
define( 'PRIVATE_PATH', SRC_PATH . DIRECTORY_SEPARATOR . 'private' );
define( 'PUBLIC_PATH' , SRC_PATH . DIRECTORY_SEPARATOR . 'public' );
define( 'ASSETS_PATH' , PUBLIC_PATH . DIRECTORY_SEPARATOR . 'assets' );

define( 'BASE_URL'    , "{$scheme}://{$host}{$parents}" );
define( 'ASSETS_URL'  , BASE_URL . '/assets' );

require_once CONFIG_PATH  . '/settings.php';
require_once PRIVATE_PATH . '/functions.php';

if ( $requestUri == '/cache.manifest' ) {
    send_cache_manifest();
    return;
} else if ( !preg_match( '#^/(?:index\.php)?(?:\?.*)?$#', $requestUri ) ) {
    header( 'HTTP/1.1 404 Not Found' );
    echo 'File Not Found';
    return;
}

require PRIVATE_PATH . '/views/header.phtml';

if ( get_rulesets() ) {
    require PRIVATE_PATH . '/views/ruleset.phtml';
} else {
    header( 'HTTP/1.1 500 Internal Server Error' );
    require PRIVATE_PATH . '/views/error.phtml';
}

require PRIVATE_PATH . '/views/footer.phtml';