<?php

/**
 * @param string $asset
 * @return string
 */
function asset_url( $asset )
{
    return rtrim( ASSETS_URL, '/' ) . '/' . ltrim( $asset, '/' );
}

/**
 * Automatically detect the PHPMD rulesets path
 * @return string The path if it's found or else FALSE
 */
function autodetect_rulesets_dir()
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
            // recursively escape the error if true
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

    $rulesdir = RULESETS_DIR;

    if ( !( is_dir( $rulesdir ) && is_readable( $rulesdir ) ) ) {
        trigger_error( 'RULESETS_DIR not found', E_USER_WARNING );
        // try auto detecting the rulesets path
        if ( !( $rulesdir = autodetect_rulesets_dir() ) ) {
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
//EOF functions.php