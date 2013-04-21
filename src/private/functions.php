<?php
/**
 * @param string $asset
 * @param bool   $absolute
 * @return string
 */
function asset_url( $asset, $absolute = true )
{
    $asset    = ltrim( $asset, '/' );
    $srcpath  = ASSETS_PATH . "/{$asset}";
    $pathinfo = pathinfo( $asset );

    #region [converting a number base 10 to base 62 (a-zA-Z0-9)](http://stackoverflow.com/a/4964352/650329)

    $base = '0123456789abcdefghijklmnopqrstuvwxyz';
    $b = strlen( $base );
    $num = filemtime( $srcpath );
    $r = $num % $b;
    $mtime = $base[$r];
    $q = floor( $num / $b );

    while ( $q ) {
        $r = $q % $b;
        $q = floor( $q / $b );
        $mtime = $base[$r] . $mtime;
    }

    #endregion

    $gzip = COMPRESS_ASSETS
            && isset( $_SERVER['HTTP_ACCEPT_ENCODING'] )
            && strpos( $_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip' ) !== false
            && in_array( strtolower( $pathinfo['extension'] ), array( 'css', 'js' ) );

    // use compressed assets if the server configuration and client allow gzip encoding
    if ( $gzip ) {
        $filename = $pathinfo['filename'];
        if ( substr( $filename, -4 ) == '.min' ) {
            $filename = substr( $filename, 0, -4 );
            $asset = "{$pathinfo['dirname']}/{$filename}.{$mtime}.min.{$pathinfo['extension']}.gz";
            $minified = true;
        } else {
            $asset = "{$pathinfo['dirname']}/{$filename}.{$mtime}.{$pathinfo['extension']}.gz";
            $minified = false;
        }

        $destpath = ASSETS_PATH . "/{$asset}";

        if ( !file_exists( $destpath ) ) {
            if ( !is_dir( dirname( $destpath ) ) ) {
                mkdir( dirname( $destpath ), 0775, true );
            }
            $hnd = fopen( $destpath, 'ab+' );
            flock( $hnd, LOCK_EX );
            clearstatcache( true, $destpath );
            if ( filesize( $destpath ) === 0 ) {
                $contents = file_get_contents( $srcpath );
                if ( !$minified && MINIFY_ASSETS ) {
                    if ( file_exists( JSMIN_PATH . '/JSMin.php' ) ) {
                        require_once JSMIN_PATH . '/JSMin.php';
                        $contents = JSMin::minify( $contents );
                        $contents = str_replace( "\n", '', $contents );
                    } else {
                        trigger_error( 'JSMIN_PATH is invalid', E_USER_WARNING );
                    }
                }
                $contents = gzencode( $contents, 9 );
                fwrite( $hnd, $contents );
            }
            flock( $hnd, LOCK_UN );
            fclose( $hnd );
        }
    } else {
        $asset .= "?v={$mtime}";
    }

    return $absolute
            ? rtrim( ASSETS_URL, '/' ) . "/{$asset}"
            : $asset;
}

/**
 * Automatically detect the PHPMD rulesets path
 * @return string The path if it's found or else FALSE
 */
function autodetect_rulesets_path()
{
    foreach ( explode( PATH_SEPARATOR, get_include_path() ) as $path ) {
        $ruleSetFactory = "{$path}/PHP/PMD/RuleSetFactory.php";
        $match = null;

        // extract the value defined in the $location property
        if ( !( file_exists( $ruleSetFactory )
                && preg_match(
                    '/\$location[\r\n\s\t]*=[\r\n\s\t]*("[^"]+"|\'[^\']+\')/',
                    file_get_contents( $ruleSetFactory ),
                    $match ) ) ) {
            continue;
        }

        $rulesdir = substr( $match[1], 1, -1 ) . '/PHP_PMD/resources/rulesets';

        if ( is_dir( $rulesdir ) && is_readable( $rulesdir ) ) {
            return $rulesdir;
        }
    }

    return false;
}

/**
 * @param mixed $value
 * @param bool  $recursive
 * @return mixed
 */
function escape( $value, $recursive = true )
{
    // input is not an array, e.g. scalar values
    if ( !is_array( $value ) ) {
        return htmlspecialchars( $value, ENT_QUOTES );
    }

    // input is an array
    foreach( $value as $k => $v ) {
        if ( is_array( $v ) ) {
            // recursively escape the array if true
            if ( $recursive ) {
                $v = escape( $v );
            }
        } else {
            $v = htmlspecialchars( $v, ENT_QUOTES );
        }
        $value[$k] = $v;
    }

    return $value;
}

/**
 * @return array
 */
function get_navbar_contents()
{
    $rulesets = get_rulesets();

    if ( !$rulesets ) {
        return $rulesets;
    }

    $items = array( 'basicsettings' => 'Basic Settings' );

    foreach ( $rulesets as $filename => $ruleset ) {
        $id = substr( $filename, 0, -4 );
        $items[$id] = $ruleset['name'];
    }

    $items['phpmd'] = 'PHPMD Ruleset';
    return $items;
}

/**
 * @return array
 */
function get_rulesets()
{
    global $cache;

    if ( isset( $cache['rulesets'] )) {
        return $cache['rulesets'];
    }

    $rulesdir = RULESETS_PATH;

    if ( !( is_dir( $rulesdir ) && is_readable( $rulesdir ) ) ) {
        trigger_error( 'RULESETS_PATH not found', E_USER_WARNING );
        // try auto detecting the rulesets path
        if ( !( $rulesdir = autodetect_rulesets_path() ) ) {
            // failed - return an empty result set
            return array();
        }
    }

    // suppress errors when using libxml
    libxml_use_internal_errors( true );

    // return array
    $rulesets = array();

    // iterate over xml rulesets in the pmd project directory
    foreach ( scandir( $rulesdir ) as $filename ) {
        // skip non-xml files
        if ( substr_compare( strtolower( $filename ), '.xml', -4 ) !== 0 ) {
            continue;
        }

        // parse the ruleset
        $ruleset = simplexml_load_file( "{$rulesdir}/{$filename}" );
        $rules = array();
        foreach ( $ruleset->rule as $ruleXml ) {
            // append the parsed rule
            $rule = array(
                    'name'        => (string)$ruleXml['name'],
                    'description' => (string)$ruleXml->description,
                    'example'     => property_exists( $ruleXml, 'example' )
                                            ? (string)$ruleXml->example
                                            : null,
                    'id'          => "rulesets/{$filename}/{$ruleXml['name']}",
                    'priority'    => (int)$ruleXml->priority,
                    'properties'  => array()
                );

            $rules[$rule['name']] = &$rule;

            if ( property_exists( $ruleXml, 'properties' ) ) {
                foreach ( $ruleXml->properties->property as $propertyXml ) {
                    $rule['properties'][(string)$propertyXml['name']] = array(
                            'name'        => (string)$propertyXml['name'],
                            'description' => (string)$propertyXml['description'],
                            'value'       => (string)$propertyXml['value']
                        );
                }
            }

            unset( $rule );
        }

        $name = (string)$ruleset['name'];
        $desc = (string)$ruleset->description;

        // append the parsed ruleset
        $rulesets[$filename] = array(
                'description' => $desc,
                'name'        => $name,
                'rules'       => $rules
            );
    }

    $cache['rulesets'] = escape( $rulesets );
    return $cache['rulesets'];
};

/**
 * @return void
 */
function send_cache_manifest()
{
    header( 'Content-Type: text/cache-manifest' );

    $manifest = array( 'CACHE MANIFEST', '' );

    $scandir = function( $dir ) use ( &$scandir, &$manifest ) {
        foreach ( scandir( $dir ) as $file ) {
            if ( $file[0] == '.' ) {
                continue;
            }
            $file = "{$dir}/{$file}";
            if ( is_dir( $file ) ) {
                $scandir( $file );
            } else {
                // only add the file if it has an allowed file extension
                if ( !preg_match( '/\.(css|gif|js|png|swf)$/i', $file ) ) {
                    continue;
                }
                // process the file through asset_url so we can use the gzip version
                $asset = substr( $file, strlen( ASSETS_PATH ) + 1 );
                $asset = asset_url( $asset, false );
                if ( strpos( $asset, 'img/glyphicons' ) !== false
                        || strpos( $asset, '.swf' ) !== false ) {
                    $asset = substr( $asset, 0, strpos( $asset, '?' ) );
                }
                $manifest[] = "assets/{$asset}";
            }
        }
    };

    $scandir( ASSETS_PATH );

    if ( file_exists( CONFIG_PATH . '/head.ini' ) ) {
        $deployment = parse_ini_file( CONFIG_PATH . '/head.ini' );
        $modified = $deployment['timestamp'];
    } else {
        chdir( BASE_PATH );
        $modified = strtotime( `git log --pretty=format:"%ad" -1` );
    }

    $manifest[1] = '# Modified ' . date( DATE_ISO8601, $modified );
    $manifest[] = 'FALLBACK:';
    $manifest[] = 'index.php .';

    echo implode( "\n", $manifest );
}

/**
 * @return void
 */
function unlink_expired_assets()
{
    $itr = new RecursiveDirectoryIterator( ASSETS_PATH );
    $itr = new RecursiveIteratorIterator( $itr );

    foreach ( $itr as $path => $splFileInfo ) {
        /** @var $splFileInfo SplFileInfo */
        if ( !preg_match( '#^(.+?)\.([a-z0-9]+)(\.min)?\.(css|js)\.gz$#i', $splFileInfo->getFilename(), $match ) ) {
            continue;
        }

        $folder = str_replace( '\\', '/', dirname( substr( $path, strlen( ASSETS_PATH ) + 1 ) ) );
        $asset =  $folder. "/{$match[1]}{$match[3]}.{$match[4]}";

        #region [converting a number base 10 to base 62 (a-zA-Z0-9)](http://stackoverflow.com/a/4964352/650329)

        $base = '0123456789abcdefghijklmnopqrstuvwxyz';
        $b = strlen( $base );
        $num = $match[2];
        $limit = strlen( $num );
        $mtime = strpos( $base, $num[0] );

        for ( $i = 1; $i < $limit; $i++ ) {
            $mtime = $b * $mtime + strpos( $base, $num[$i] );
        }

        #endregion

        if ( filemtime( ASSETS_PATH . "/{$asset}" ) != $mtime ) {
            // delete the expired cache file
            unlink( $path );
        }
    }
}
//EOF functions.php