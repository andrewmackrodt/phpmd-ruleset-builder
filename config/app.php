<?php

error_reporting( E_ALL | E_STRICT );

ini_set( 'display_errors', env( 'DEBUG', false ) );
ini_set( 'html_errors', env( 'DEBUG', false ) );

define( 'COMPRESS_ASSETS', env( 'COMPRESS_ASSETS', false ) );
define( 'MINIFY_ASSETS', env( 'MINIFY_ASSETS', false ) );
define( 'PHPCS_PATH', '/usr/share/pear/PHP' );
