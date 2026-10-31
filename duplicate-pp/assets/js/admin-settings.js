/**
 * Duplicate PP settings page.
 *
 * - Switches tabs without a reload and keeps the URL (?tab=) and the
 *   form's referer in sync, so saving returns to the same tab.
 * - Live preview of the title/slug prefix and suffix.
 * - "All / None" helpers for checkbox groups.
 * - Confirmation before restoring the defaults.
 */
( function () {
    'use strict';

    var FORM_TABS = [ 'copy', 'permissions', 'display' ];

    /**
     * Create an element with a class and optional text (never HTML).
     */
    function make( tag, className, text ) {
        var node = document.createElement( tag );
        if ( className ) {
            node.className = className;
        }
        if ( text ) {
            node.textContent = text;
        }
        return node;
    }

    /**
     * Dashboard quick search: find an item and copy it from the results.
     * Arrow keys move through results, Enter opens "New Draft", Esc closes,
     * and "/" anywhere on the page focuses the field.
     */
    function initQuickSearch( wrap ) {
        var search = wrap.querySelector( '.dpp-search' );
        if ( ! search || ! window.ajaxurl ) {
            return;
        }

        var input = search.querySelector( '.dpp-search__input' );
        var box = search.querySelector( '.dpp-search__results' );
        var i18n = {};
        try {
            i18n = JSON.parse( search.getAttribute( 'data-i18n' ) || '{}' );
        } catch ( e ) {}

        var timer = null;
        var seq = 0;
        var current = -1;

        function open() {
            box.hidden = false;
            input.setAttribute( 'aria-expanded', 'true' );
        }

        function close() {
            box.hidden = true;
            input.setAttribute( 'aria-expanded', 'false' );
            current = -1;
        }

        function note( text ) {
            box.textContent = '';
            box.appendChild( make( 'p', 'dpp-search__note', text ) );
            open();
        }

        function action( url, label, variant ) {
            var link = make( 'a', 'dpp-btn dpp-btn--sm dpp-btn--' + variant, label );
            link.href = url;
            return link;
        }

        function render( items, term ) {
            box.textContent = '';
            current = -1;
            if ( ! items.length ) {
                note( i18n.none );
                return;
            }

            box.appendChild( make( 'p', 'dpp-search__group', term ? i18n.results : i18n.recent ) );

            items.forEach( function ( item ) {
                var row = make( 'div', 'dpp-result' );
                row.appendChild( make( 'span', 'dpp-result__icon dashicons ' + item.icon ) ).setAttribute( 'aria-hidden', 'true' );

                var main = make( 'div', 'dpp-result__main' );
                var title = make( item.editUrl ? 'a' : 'span', 'dpp-result__title', item.title );
                if ( item.editUrl ) {
                    title.href = item.editUrl;
                }
                main.appendChild( title );
                main.appendChild( make( 'span', 'dpp-result__meta', item.type + ' · ' + item.statusLb ) );
                row.appendChild( main );

                var actions = make( 'div', 'dpp-result__actions' );
                actions.appendChild( action( item.clone, i18n.duplicate, 'ghost' ) );
                if ( item.rewrite ) {
                    actions.appendChild( action( item.rewrite, i18n.rewrite, 'ghost' ) );
                }
                actions.appendChild( action( item.draft, i18n.draft, 'primary' ) );
                row.appendChild( actions );

                row.addEventListener( 'mouseenter', function () {
                    highlight( Array.prototype.indexOf.call( box.querySelectorAll( '.dpp-result' ), row ) );
                } );
                box.appendChild( row );
            } );
            open();
        }

        function highlight( index ) {
            var rows = box.querySelectorAll( '.dpp-result' );
            if ( ! rows.length ) {
                return;
            }
            current = ( index + rows.length ) % rows.length;
            rows.forEach( function ( row, i ) {
                row.classList.toggle( 'is-current', i === current );
            } );
            rows[ current ].scrollIntoView( { block: 'nearest' } );
        }

        function run() {
            var term = input.value.trim();
            var mine = ++seq;
            var body = new FormData();
            body.append( 'action', search.getAttribute( 'data-action' ) );
            body.append( 'nonce', search.getAttribute( 'data-nonce' ) );
            body.append( 'term', term );

            if ( box.hidden || ! box.querySelector( '.dpp-result' ) ) {
                note( i18n.loading );
            }

            window.fetch( window.ajaxurl, { method: 'POST', credentials: 'same-origin', body: body } )
                .then( function ( response ) {
                    return response.json();
                } )
                .then( function ( result ) {
                    if ( mine !== seq ) {
                        return;
                    }
                    if ( ! result || ! result.success ) {
                        throw new Error( 'search' );
                    }
                    render( result.data, term );
                } )
                .catch( function () {
                    if ( mine === seq ) {
                        note( i18n.error );
                    }
                } );
        }

        input.addEventListener( 'input', function () {
            window.clearTimeout( timer );
            timer = window.setTimeout( run, 220 );
        } );

        input.addEventListener( 'focus', function () {
            if ( box.hidden ) {
                run();
            }
        } );

        input.addEventListener( 'keydown', function ( event ) {
            if ( 'Escape' === event.key ) {
                close();
                input.blur();
            } else if ( 'ArrowDown' === event.key || 'ArrowUp' === event.key ) {
                event.preventDefault();
                highlight( current + ( 'ArrowDown' === event.key ? 1 : -1 ) );
            } else if ( 'Enter' === event.key ) {
                var rows = box.querySelectorAll( '.dpp-result' );
                var row = rows[ current >= 0 ? current : 0 ];
                if ( row ) {
                    event.preventDefault();
                    row.querySelector( '.dpp-btn--primary' ).click();
                }
            }
        } );

        document.addEventListener( 'click', function ( event ) {
            if ( ! search.contains( event.target ) ) {
                close();
            }
        } );

        document.addEventListener( 'keydown', function ( event ) {
            var tag = event.target && event.target.tagName;
            if ( '/' === event.key && ! /^(INPUT|TEXTAREA|SELECT)$/.test( tag ) && ! event.target.isContentEditable && search.offsetParent ) {
                event.preventDefault();
                input.focus();
            }
        } );
    }

    document.addEventListener( 'DOMContentLoaded', function () {
        var wrap = document.querySelector( '.dpp-wrap' );
        if ( ! wrap ) {
            return;
        }

        var tabs = wrap.querySelectorAll( '.dpp-tab' );
        var panels = wrap.querySelectorAll( '.dpp-panel' );
        var form = wrap.querySelector( '.dpp-form' );
        var referer = form ? form.querySelector( 'input[name="_wp_http_referer"]' ) : null;

        function activate( key, push ) {
            var found = false;
            panels.forEach( function ( panel ) {
                var on = panel.getAttribute( 'data-panel' ) === key;
                panel.classList.toggle( 'is-active', on );
                found = found || on;
            } );
            if ( ! found ) {
                return false;
            }

            tabs.forEach( function ( tab ) {
                var on = tab.getAttribute( 'data-tab' ) === key;
                tab.classList.toggle( 'is-active', on );
                if ( on ) {
                    tab.setAttribute( 'aria-current', 'page' );
                } else {
                    tab.removeAttribute( 'aria-current' );
                }
            } );

            if ( form ) {
                form.classList.toggle( 'is-active', FORM_TABS.indexOf( key ) !== -1 );
            }

            var url = new URL( window.location.href );
            url.searchParams.set( 'tab', key );
            [ 'settings-updated', 'dpp_reset' ].forEach( function ( arg ) {
                url.searchParams.delete( arg );
            } );
            if ( push && window.history && window.history.pushState ) {
                window.history.pushState( { dppTab: key }, '', url.toString() );
            }
            if ( referer ) {
                referer.value = url.pathname + url.search;
            }
            return true;
        }

        function onTabClick( event ) {
            var key = this.getAttribute( 'data-tab' ) || this.getAttribute( 'data-tab-link' );
            if ( key && activate( key, true ) ) {
                event.preventDefault();
                window.scrollTo( { top: 0, behavior: 'smooth' } );
            }
        }

        tabs.forEach( function ( tab ) {
            tab.addEventListener( 'click', onTabClick );
        } );
        wrap.querySelectorAll( '[data-tab-link]' ).forEach( function ( link ) {
            link.addEventListener( 'click', onTabClick );
        } );

        window.addEventListener( 'popstate', function () {
            var key = new URL( window.location.href ).searchParams.get( 'tab' ) || 'dashboard';
            activate( key, false );
        } );

        // Sync the referer with the tab the page was opened on.
        var initial = wrap.querySelector( '.dpp-tab.is-active' );
        if ( initial && referer ) {
            var start = new URL( window.location.href );
            start.searchParams.set( 'tab', initial.getAttribute( 'data-tab' ) );
            start.searchParams.delete( 'settings-updated' );
            referer.value = start.pathname + start.search;
        }

        // Title / slug preview.
        var titleOut = wrap.querySelector( '.dpp-preview__title' );
        var slugOut = wrap.querySelector( '.dpp-preview__slug' );

        function field( key ) {
            var input = wrap.querySelector( '[data-preview="' + key + '"]' );
            return input ? input.value : '';
        }

        function slugify( value ) {
            return value.toLowerCase().replace( /[^a-z0-9_-]+/g, '-' ).replace( /-{2,}/g, '-' );
        }

        function updatePreview() {
            if ( titleOut ) {
                titleOut.textContent = field( 'title_prefix' ) + titleOut.getAttribute( 'data-sample' ) + field( 'title_suffix' );
            }
            if ( slugOut ) {
                slugOut.textContent = '/' + slugify( field( 'slug_prefix' ) + slugOut.getAttribute( 'data-sample' ) + field( 'slug_suffix' ) ).replace( /^-+|-+$/g, '' ) + '/';
            }
        }

        wrap.querySelectorAll( '[data-preview]' ).forEach( function ( input ) {
            input.addEventListener( 'input', updatePreview );
        } );
        updatePreview();

        // All / None.
        wrap.querySelectorAll( '.dpp-bulk-toggle' ).forEach( function ( group ) {
            var target = document.getElementById( group.getAttribute( 'data-target' ) );
            if ( ! target ) {
                return;
            }
            group.querySelectorAll( 'button[data-state]' ).forEach( function ( button ) {
                button.addEventListener( 'click', function () {
                    var on = '1' === button.getAttribute( 'data-state' );
                    target.querySelectorAll( 'input[type="checkbox"]:not(:disabled)' ).forEach( function ( box ) {
                        box.checked = on;
                    } );
                } );
            } );
        } );

        initQuickSearch( wrap );

        // Reset confirmation.
        var reset = wrap.querySelector( '.dpp-savebar__reset' );
        if ( reset ) {
            reset.addEventListener( 'click', function ( event ) {
                // eslint-disable-next-line no-alert
                if ( ! window.confirm( reset.getAttribute( 'data-confirm' ) ) ) {
                    event.preventDefault();
                }
            } );
        }
    } );
}() );
