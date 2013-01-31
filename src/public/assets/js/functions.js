/**
 * @param {string?} name
 * @param {string?} description
 * @param {Array?} rules
 * @return {string}
 */
function createXmlDocument( name, description, rules )
{
    if ( typeof name == 'undefined' ) {
        name = $( 'input[name="name"]' ).val();
    }

    if ( ( name = $.trim( name ) ).length == 0 ) {
        name = 'untitled';
    }

    if ( typeof description == 'undefined' ) {
        description = $( 'textarea[name="description"]' ).val();
    }

    if ( ( description = $.trim( description ) ).length == 0 ) {
        description = 'untitled';
    }

    if ( typeof rules == 'undefined' ) {
        rules = getRules();
    }

    // create a string buffer to store the XML formatted PHPMD file
    var buffer = [ '<?xml version="1.0"?>', '\n<ruleset name="' + name + '"' ];

    // xml namespace attributes
    var attributes = {
            'xmlns': 'http://pmd.sf.net/ruleset/1.0.0',
            'xmlns:xsi': 'http://www.w3.org/2001/XMLSchema-instance',
            'xsi:schemaLocation': 'http://pmd.sf.net/ruleset/1.0.0 http://pmd.sf.net/ruleset_xml_schema.xsd',
            'xsi:noNamespaceSchemaLocation': 'http://pmd.sf.net/ruleset_xml_schema.xsd'
        };

    // append the xml namespace attributes to the string buffer
    for ( var k in attributes ) {
        buffer.push( '\n        ' + k + '="' + attributes[k] + '"' );
    }

    buffer.push( '>' );
    buffer.push( '\n    <description>' + description + '</description>' );

    // append the rules to the string buffer
    for ( var i = 0; i < rules.length; i++ ) {
        buffer.push( '\n    <rule ref="' + rules[i] + '"/>' );
    }

    // close the document
    buffer.push( '\n</ruleset>' );

    // return the joined contents of the string buffer
    return buffer.join( '' );
}

/**
 * @returns {Array}
 */
function getRules()
{
    var rules = [];

    $( 'input[type="checkbox"]:checked' ).each( function() {
        rules.push( this.name );
    } );

    return rules;
}

/**
 * regenerate the output
 */
function outputXmlDocument()
{
    $( '#phpmd' ).text( createXmlDocument() );
}

/**
 * call outputXmlDocument whenever an input is changed
 */
( function() {
    // name and description text inputs
    $( 'input[name="name"], textarea[name="description"]' ).blur( outputXmlDocument );
    // checkboxes
    $( 'input:checkbox' ).change( outputXmlDocument );
    // handle first time page load
    outputXmlDocument();
    // reset the scrolling position otherwise the navmenu can get messed up
    if ( location.hash ) {
        var scrollY = window.scrollY;
        $( window ).scrollTop( 0 );
        setTimeout( function() {
            $( window ).scrollTop( scrollY );
        }, 100 );
    }
} )();