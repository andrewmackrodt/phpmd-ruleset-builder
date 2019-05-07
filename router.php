<?php

if (php_sapi_name() !== 'cli-server') {
    die('router.php must only be used with the php built-in server');
}

if (!call_user_func(static function () {
    // https://developer.mozilla.org/en-US/docs/Web/HTTP/Basics_of_HTTP/MIME_types/Complete_list_of_MIME_types
    static $mimeTypes = [
        '.aac' => 'audio/aac',
        '.abw' => 'application/x-abiword',
        '.arc' => 'application/x-freearc',
        '.avi' => 'video/x-msvideo',
        '.azw' => 'application/vnd.amazon.ebook',
        '.bin' => 'application/octet-stream',
        '.bmp' => 'image/bmp',
        '.bz' => 'application/x-bzip',
        '.bz2' => 'application/x-bzip2',
        '.csh' => 'application/x-csh',
        '.css' => 'text/css',
        '.css.map' => 'application/json',
        '.csv' => 'text/csv',
        '.doc' => 'application/msword',
        '.docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        '.eot' => 'application/vnd.ms-fontobject',
        '.epub' => 'application/epub+zip',
        '.gif' => 'image/gif',
        '.htm' => 'text/html',
        '.html' => 'text/html',
        '.ico' => 'image/vnd.microsoft.icon',
        '.ics' => 'text/calendar',
        '.jar' => 'application/java-archive',
        '.jpeg' => 'image/jpeg',
        '.jpg' => 'image/jpeg',
        '.js' => 'application/javascript',
        '.json' => 'application/json',
        '.jsonld' => 'application/ld+json',
        '.mid' => 'audio/x-midi',
        '.midi' => 'audio/x-midi',
        '.mjs' => 'text/javascript',
        '.mp3' => 'audio/mpeg',
        '.mpeg' => 'video/mpeg',
        '.mpkg' => 'application/vnd.apple.installer+xml',
        '.odp' => 'application/vnd.oasis.opendocument.presentation',
        '.ods' => 'application/vnd.oasis.opendocument.spreadsheet',
        '.odt' => 'application/vnd.oasis.opendocument.text',
        '.oga' => 'audio/ogg',
        '.ogv' => 'video/ogg',
        '.ogx' => 'application/ogg',
        '.otf' => 'font/otf',
        '.png' => 'image/png',
        '.pdf' => 'application/pdf',
        '.ppt' => 'application/vnd.ms-powerpoint',
        '.pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        '.rar' => 'application/x-rar-compressed',
        '.rtf' => 'application/rtf',
        '.sh' => 'application/x-sh',
        '.svg' => 'image/svg+xml',
        '.swf' => 'application/x-shockwave-flash',
        '.tar' => 'application/x-tar',
        '.tif' => 'image/tiff',
        '.tiff' => 'image/tiff',
        '.ttf' => 'font/ttf',
        '.txt' => 'text/plain',
        '.vsd' => 'application/vnd.visio',
        '.wav' => 'audio/wav',
        '.weba' => 'audio/webm',
        '.webm' => 'video/webm',
        '.webp' => 'image/webp',
        '.woff' => 'font/woff',
        '.woff2' => 'font/woff2',
        '.xhtml' => 'application/xhtml+xml',
        '.xls' => 'application/vnd.ms-excel',
        '.xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        '.xml' => 'application/xml',
        '.xul' => 'application/vnd.mozilla.xul+xml',
        '.zip' => 'application/zip',
        '.3gp' => 'video/3gpp',
        '.3g2' => 'video/3gpp2',
        '.7z' => 'application/x-7z-compressed',
    ];

    $requestPath = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
    $publicPath = realpath( __DIR__ . '/docs' );
    $filepath = realpath( "$publicPath/$requestPath" );

    if (strpos($filepath, $publicPath) !== 0) {
        return false;
    }

    $extension = preg_match( '/(?:\.[a-z0-9]+)+$/i', $filepath, $match )
        ? strtolower( $match[0] )
        : '';

    $contentEncoding = '';

    if ( substr( $extension, -3 ) === '.gz' ) {
        $contentEncoding = 'gzip';
        $extension = substr( $extension, 0, -3 );
    }

    while ( $extension && ! isset( $mimeTypes[$extension] ) ) {
        $extension = preg_replace( '/^\.[^.]+/', '', $extension );
    }

    if ( !$extension || preg_match( '/^\.php(?:s|[0-9]+)$/i', $extension ) ) {
        return false;
    }

    if ( $contentEncoding ) {
        header( "Content-Encoding: $contentEncoding" );
    }

    $contentType = isset($mimeTypes[$extension])
        ? $mimeTypes[$extension]
        : 'application/octet-stream';

    header( "Content-Type: $contentType" );

    readfile( $filepath );

    return true;
})) {
    require __DIR__ . '/docs/index.php';
}
