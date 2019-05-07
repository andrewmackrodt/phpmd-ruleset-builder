/**
 * @param {string} selector
 * @param {function} callback
 * @param {string?} filename
 * @param {string?} mime
 * @returns {jQuery}
 */
function createDownloadButton( selector, callback, filename, mime )
{
    if ( typeof filename == 'undefined' || !filename ) {
        filename = 'download';
    }

    if ( typeof mime == 'undefined' || !mime ) {
        mime = 'application/xml';
    }

    /**
     * @returns {string}
     */
    var getDataUri = function() {

        var uri  = 'data:' + mime + ';content-disposition=attachment;filename=' + filename;
        var data = callback();

        if ( typeof window.btoa == 'function' ) {
            uri += ';base64';
            data = window.btoa( data );
        } else {
            data = encodeURIComponent( data );
        }

        return uri + ',' + data;
    };

    /**
     * @param {element} el
     * @returns {boolean}
     */
    var isDownloadifyEnabled = function( el ) {
        return $( el ).find( 'object[id^="downloadify_"]' ).length > 0;
    };

    var el = $( selector );
    return el
        .downloadify( {
            'append': true,
            'data': callback,
            'downloadImage': null,
            'filename': filename,
            'height': el.height(),
            'swf': 'assets/swf/downloadify.swf',
            'transparent': true,
            'width': el.width()
        } )
        .each( function() {
            if ( this.nodeName.toLowerCase() == 'a' ) {
                // links are handled differently to other elements
                $( this ).attr( 'download', filename )
                    .click( function() {
                        // prevent the page from loading if downloadify is enabled
                        return !isDownloadifyEnabled( this );
                    } )
                    .hover( function() {
                        $( this ).attr( 'href', getDataUri() );
                    } );
            } else {
                $( this ).click( function() {
                    if ( isDownloadifyEnabled( this ) ) {
                        // abort as downloadify will handle the save action
                        return false;
                    }
                    window.open( getDataUri() );
                    return true;
                } );
            }
        } );
}

/**
 * @param {string?} name
 * @param {string?} description
 * @param {{}} rules
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

    // are advanced settings enabled
    var advanced = $( 'input[name="advanced"]' ).is( ':checked' );

    // append the rules to the string buffer
    for ( var ref in rules ) {
        buffer.push( '\n    <rule ref="' + ref + '"' );

        // the rule has no properties
        if ( !advanced || $.isEmptyObject( rules[ref] ) ) {
            buffer.push( ' />' );
            continue;
        }

        buffer.push( '>' );

        // the rule has a custom priority
        if ( rules[ref].priority ) {
            buffer.push( '\n        <priority>' + rules[ref].priority + '</priority>' );
            delete rules[ref].priority;
        }

        if ( !$.isEmptyObject( rules[ref] ) ) {
            // the rule has properties
            buffer.push( '\n        <properties>' );

            for ( var property in rules[ref] ) {
                buffer.push( '\n            <property name="' + property + '" value="' + rules[ref][property] + '" />' );
            }

            buffer.push( '\n        </properties>' );
        }

        buffer.push( '\n    </rule>' );
    }

    // close the document
    buffer.push( '\n</ruleset>' );

    // return the joined contents of the string buffer
    return buffer.join( '' );
}

/**
 * @returns {{}}
 */
function getRules()
{
    var rules = {};

    $( '.rule input[type="checkbox"]:checked' ).each( function() {
        var rule = {};

        var parents = $( this ).parents( 'div.control-group' );

        var $priority       = parents.find( 'select[name*="priority"]' );
        var defaultPriority = $priority.attr( 'data-default' );
        var priority        = $priority.val();

        if ( priority != defaultPriority ) {
            rule['priority'] = priority;
        }

        parents.find( 'input[type="text"]' ).each( function() {
            var $input      = $( this );
            var placeholder = $input.attr( 'placeholder' );
            var value       = $.trim( $input.val() ) || placeholder;

            if ( value != placeholder ) {
                var property = this.name.replace( /^.+\[(.+)\]$/, '$1' );
                rule[property] = value;
            }
        } );

        var name = this.name.replace( /^([^[]+).+$/, '$1' );
        rules[name] = rule;
    } );

    return rules;
}

/**
 * regenerate the output
 */
function outputXmlDocument()
{
    $( '#phpmd' ).find( 'pre' ).text( createXmlDocument() );
}

/**
 * document load function
 */
( function() {
    $( 'input[type="text"], textarea[name="description"], .rule select[name*="priority"]' )
            .blur( outputXmlDocument ) // call outputXmlDocument whenever an input is changed
            .placeholder();            // support placeholder in older browsers

    // checkbox changed
    $( 'input:checkbox' ).change( outputXmlDocument );

    // show or hide advanced settings
    var toggleAdvancedMode = function() {
        var options = $( 'form .priority, form .options' );
        var i = options.length;

        if ( $( this ).is( ':checked' ) ) {
            options.fadeIn( 400, function () {
                if (--i == 0) {
                    $( 'body' ).scrollspy( 'refresh' );
                }
            });
        } else {
            options.fadeOut( 400, function () {
                if (--i == 0) {
                    $( 'body' ).scrollspy( 'refresh' );
                }
            });
        }
    };

    $( 'input[name="advanced"]' ).change( toggleAdvancedMode ).each( toggleAdvancedMode );

    // save the generated phpmd.xml file
    createDownloadButton( '.downloadify', function () {
        return $( '#phpmd' ).find( 'pre' ).text();
    }, 'phpmd.xml', 'application/xml' );

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