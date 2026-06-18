/**
 * Duplicate PP settings page — tab toggle behavior.
 *
 * Loaded only on the Duplicate PP settings page. Wires the two
 * nav tabs at the top of the page (Welcome / Settings) so that
 * clicking a tab reveals its panel and hides the other.
 */
( function () {
    'use strict';

    document.addEventListener( 'DOMContentLoaded', function () {
        var tabs     = document.querySelectorAll( '.nav-tab' );
        var contents = document.querySelectorAll( '.tab-content' );

        if ( ! tabs.length || ! contents.length ) {
            return;
        }

        tabs.forEach( function ( tab ) {
            tab.addEventListener( 'click', function ( event ) {
                event.preventDefault();

                // Reset.
                tabs.forEach( function ( t ) { t.classList.remove( 'nav-tab-active' ); } );
                contents.forEach( function ( c ) { c.classList.remove( 'active' ); } );

                // Activate.
                tab.classList.add( 'nav-tab-active' );
                var targetId = tab.getAttribute( 'data-tab' );
                if ( targetId ) {
                    var target = document.getElementById( targetId );
                    if ( target ) {
                        target.classList.add( 'active' );
                    }
                }
            } );
        } );

        // Show the first tab by default.
        if ( contents[ 0 ] ) {
            contents[ 0 ].classList.add( 'active' );
        }
    } );
}() );
