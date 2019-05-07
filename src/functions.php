<?php
/**
 * @param string $asset
 * @param bool   $absolute
 * @return string
 */
function asset_url( $asset, $absolute = true )
{
    $asset    = ltrim( $asset, '/' );
    $srcpath  = PUBLIC_PATH . "/{$asset}";
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

        $destpath = PUBLIC_PATH . "/{$asset}";

        if ( !file_exists( $destpath ) ) {
            if ( !is_dir( dirname( $destpath ) ) ) {
                mkdir( dirname( $destpath ), 0775, true );
            }
            $hnd = fopen( $destpath, 'ab+' );
            flock( $hnd, LOCK_EX );
            clearstatcache( true, $destpath );
            if ( filesize( $destpath ) === 0 ) {
                if ( !$minified && MINIFY_ASSETS ) {
                    $minify = new Minify( new Minify_Cache_Null );
                    $contents = $minify->combine( [$srcpath] );
                } else {
                    $contents = file_get_contents( $srcpath );
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
            ? rtrim( BASE_URL, '/' ) . "/{$asset}"
            : $asset;
}

/**
 * Automatically detect the PHPMD rulesets path
 * @return string The path if it's found or else FALSE
 */
function autodetect_rulesets_path()
{
    $ruleSetFactory = new \PHPMD\RuleSetFactory();
    $ruleSets = $ruleSetFactory->listAvailableRuleSets();

    return dirname( $ruleSetFactory->createSingleRuleSet( $ruleSets[0] )->getFileName() );
}

/**
 * Automatically detect the PHPCS path
 * @return string The path if it's found or else FALSE
 */
function autodetect_phpcs_path()
{
    foreach ( explode( PATH_SEPARATOR, get_include_path() ) as $path ) {
        $path = realpath( "{$path}/PHP/CodeSniffer.php" );
        if ( $path ) {
            return $path;
        }
    }

    return false;
}

function env( $name, $default = '' )
{
    $value = getenv( $name );

    return $value !== false
        ? ( $value === 'true'
            ? true
            : ( $value === 'false'
                ? false
                : $value) )
        : $default;
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

    $rulesdir = autodetect_rulesets_path();

    if ( !is_dir( $rulesdir ) ) {
        trigger_error( 'PHPMD rulesets not found', E_USER_WARNING );
        return array();
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
 * @return array
 */
function get_standards()
{
    global $cache;

    if ( isset( $cache['standards'] )) {
        return $cache['standards'];
    }

    $phpcsPath = realpath( PHPCS_PATH . '/CodeSniffer.php' ) ?: autodetect_phpcs_path();
    $standardsPath = realpath( dirname( $phpcsPath ) . '/CodeSniffer/Standards' );

    if ( !( is_file( $phpcsPath ) && is_dir( $standardsPath ) ) ) {
        trigger_error( 'PHP CodeSniffer not found', E_USER_WARNING );
        return array();
    }

    // return array
    $standards = array();

    /** @noinspection PhpIncludeInspection */
    require_once $phpcsPath;
    $codeSniffer = new PHP_CodeSniffer();
    $sniffs = $codeSniffer->getSniffFiles( $standardsPath );

    $fnExtractDescription = function( $docComment, &$var = null ) {
        $description = preg_replace( '/^ *\* */m', '', str_replace( "\t", ' ', $docComment ) );
        $description = explode( "\n", str_replace( "\r", '', $description ) );
        $description = array_filter( $description, function( $line ) use ( &$var ) {
            if ( $line ) {
                if ( substr_compare( $line, '@var', 0, 4 ) === 0 ) {
                    $var = trim( substr( $line, 4 ) );
                    return false;
                } else {
                    return $line[0] != '@' && $line[0] != '/';
                }
            }
            return false;
        } );
        $description = trim( preg_replace( '/ {2,}/', ' ', strip_tags( implode( ' ', $description ) ) ) );
        return $description;
    };

    foreach ( $sniffs as $sniff ) {
        $class = str_replace( '\\', '_', substr( $sniff, strlen( $standardsPath ) + 1, -4 ) );
        $name = str_replace( '_', '.', $class );

        if ( $off = strpos( $class, '_' ) ) {
            $standardName = substr( $class, 0, $off );
        } else {
            continue;
        }

        $class = new ReflectionClass( $class );

        // get the description for the sniff
        $description = $fnExtractDescription( $class->getDocComment() );
        $description = preg_replace( '/^([a-z0-9]+_)+[a-z0-9]+\. /i', '', $description );

        // append the parsed standard
        $standard = array(
                'description' => $description,
                'properties'  => array()
            );

        $instance = $class->newInstance();

        foreach ( $class->getProperties( ReflectionProperty::IS_PUBLIC ) as $property ) {
            $description = $fnExtractDescription( $property->getDocComment(), $var );
            $value = $property->getValue( $instance );
            if ( $var != 'array' && !is_array( $value ) ) {
                $standard['properties'][$property->getName()] = array(
                        'description' => $description,
                        'type'        => $var,
                        'value'       => $value
                    );
            }
        }

        $standards[$standardName][$name] = $standard;
    }

    $cache['standards'] = escape( $standards );
    return $cache['standards'];
}

/**
 * @return void
 */
function unlink_expired_assets()
{
    $itr = new RecursiveDirectoryIterator( PUBLIC_PATH );
    $itr = new RecursiveIteratorIterator( $itr );

    foreach ( $itr as $path => $splFileInfo ) {
        /** @var $splFileInfo SplFileInfo */
        if ( !preg_match( '#^(.+?)\.([a-z0-9]+)(\.min)?\.(css|js)\.gz$#i', $splFileInfo->getFilename(), $match ) ) {
            continue;
        }

        $folder = str_replace( '\\', '/', dirname( substr( $path, strlen( PUBLIC_PATH ) + 1 ) ) );
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

        if ( filemtime( PUBLIC_PATH . "/{$asset}" ) != $mtime ) {
            // delete the expired cache file
            unlink( $path );
        }
    }
}
//EOF functions.php