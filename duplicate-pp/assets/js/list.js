/**
 * Duplicate PP — "Copy multiple" dialog on post list screens.
 *
 * - Row action "Copy multiple": asks how many copies, then follows the
 *   nonce-protected link with &count=N (&numbered=1).
 * - Bulk action "Duplicate multiple times…": asks the same question, adds
 *   dpp_count / dpp_numbered to the bulk form and lets core submit it.
 *
 * Data comes from window.dppList (see Row_Actions::enqueue_dialog()).
 */
( function ( data ) {
    'use strict';

    if ( ! data || ! window.HTMLDialogElement ) {
        return;
    }

    var i18n = data.i18n;
    var max = Math.max( 1, parseInt( data.max, 10 ) || 1 );
    var STORE_KEY = 'dppCopyMultiple';

    function remembered() {
        try {
            return JSON.parse( window.localStorage.getItem( STORE_KEY ) ) || {};
        } catch ( e ) {
            return {};
        }
    }

    function remember( value ) {
        try {
            window.localStorage.setItem( STORE_KEY, JSON.stringify( value ) );
        } catch ( e ) {}
    }

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

    function format( template, value ) {
        return template.replace( /%[sd]/, value );
    }

    // Mirrors Duplicator::numbered_suffix(): " (Copy)" -> " (Copy 3)".
    function numberedSuffix( suffix, n ) {
        var match = suffix.match( /^([\s\S]*?)\s*([)\]])\s*$/ );
        return match ? match[ 1 ] + ' ' + n + match[ 2 ] : suffix.replace( /\s+$/, '' ) + ' ' + n;
    }

    // ---- Build the dialog once. --------------------------------------

    var dialog = make( 'dialog', 'dpp-dialog' );
    dialog.setAttribute( 'aria-labelledby', 'dpp-dialog-title' );

    var form = make( 'form', 'dpp-dialog__form' );
    form.method = 'dialog';

    var head = make( 'div', 'dpp-dialog__head' );
    var icon = make( 'span', 'dpp-dialog__icon dashicons dashicons-admin-page' );
    icon.setAttribute( 'aria-hidden', 'true' );
    var headText = make( 'div' );
    var title = make( 'h2', 'dpp-dialog__title', i18n.title );
    title.id = 'dpp-dialog-title';
    var subtitle = make( 'p', 'dpp-dialog__subtitle' );
    headText.appendChild( title );
    headText.appendChild( subtitle );
    head.appendChild( icon );
    head.appendChild( headText );

    var label = make( 'label', 'dpp-dialog__label', i18n.count );
    label.htmlFor = 'dpp-copy-count';

    var stepper = make( 'div', 'dpp-stepper' );
    var less = make( 'button', 'dpp-stepper__btn', '−' );
    less.type = 'button';
    less.setAttribute( 'aria-label', i18n.less );
    var input = make( 'input', 'dpp-stepper__input' );
    input.type = 'number';
    input.id = 'dpp-copy-count';
    input.min = '1';
    input.max = String( max );
    input.step = '1';
    input.required = true;
    var more = make( 'button', 'dpp-stepper__btn', '+' );
    more.type = 'button';
    more.setAttribute( 'aria-label', i18n.more );
    stepper.appendChild( less );
    stepper.appendChild( input );
    stepper.appendChild( more );

    var presets = make( 'div', 'dpp-presets' );
    ( data.presets || [] ).forEach( function ( n ) {
        var chip = make( 'button', 'dpp-preset', String( n ) );
        chip.type = 'button';
        chip.setAttribute( 'data-value', String( n ) );
        chip.addEventListener( 'click', function () {
            setCount( n );
            input.focus();
        } );
        presets.appendChild( chip );
    } );

    var hint = make( 'p', 'dpp-dialog__hint', format( i18n.limit, max ) );

    var numberedWrap = make( 'label', 'dpp-dialog__check' );
    var numbered = make( 'input' );
    numbered.type = 'checkbox';
    numberedWrap.appendChild( numbered );
    numberedWrap.appendChild( make( 'span', '', i18n.numbered ) );

    var preview = make( 'div', 'dpp-dialog__preview' );
    preview.setAttribute( 'aria-live', 'polite' );

    var actions = make( 'div', 'dpp-dialog__actions' );
    var cancel = make( 'button', 'button', i18n.cancel );
    cancel.type = 'button';
    var submit = make( 'button', 'button button-primary' );
    submit.type = 'submit';
    actions.appendChild( cancel );
    actions.appendChild( submit );

    [ head, label, stepper, presets, hint, numberedWrap, preview, actions ].forEach( function ( node ) {
        form.appendChild( node );
    } );
    dialog.appendChild( form );
    document.body.appendChild( dialog );

    // ---- State. --------------------------------------------------------

    var state = { mode: 'row', url: '', title: '', items: 0, onConfirm: null };

    function count() {
        var n = parseInt( input.value, 10 );
        return isNaN( n ) ? 0 : Math.min( Math.max( n, 1 ), max );
    }

    function setCount( n ) {
        input.value = String( Math.min( Math.max( n, 1 ), max ) );
        refresh();
    }

    function refresh() {
        var n = count();
        less.disabled = n <= 1;
        more.disabled = n >= max;
        presets.querySelectorAll( '.dpp-preset' ).forEach( function ( chip ) {
            chip.classList.toggle( 'is-active', parseInt( chip.getAttribute( 'data-value' ), 10 ) === n );
        } );

        var total = state.mode === 'bulk' ? n * state.items : n;
        submit.textContent = 1 === total ? i18n.submitOne : format( i18n.submit, total );
        submit.disabled = n < 1;

        // Show what the titles will look like.
        preview.textContent = '';
        var base = state.mode === 'row' && state.title ? state.title : i18n.example;
        var shown = Math.min( n, 3 );
        for ( var k = 1; k <= shown; k++ ) {
            preview.appendChild( make( 'span', 'dpp-dialog__chip', base + ( numbered.checked ? numberedSuffix( data.suffix, k ) : data.suffix ) ) );
        }
        if ( n > shown ) {
            preview.appendChild( make( 'span', 'dpp-dialog__more', '+' + ( n - shown ) ) );
        }
        if ( state.mode === 'bulk' && state.items > 1 ) {
            preview.appendChild( make( 'p', 'dpp-dialog__total', format( i18n.total, total ) ) );
        }
    }

    function open( options ) {
        var saved = remembered();
        state = options;
        subtitle.textContent = options.mode === 'bulk'
            ? format( i18n.ofSelected, options.items )
            : format( i18n.of, options.title );
        numbered.checked = saved.numbered !== false;
        submit.classList.remove( 'is-busy' );
        cancel.disabled = false;
        setCount( saved.count || ( data.presets && data.presets[ 0 ] ) || 2 );
        dialog.showModal();
        input.select();
    }

    less.addEventListener( 'click', function () {
        setCount( count() - 1 );
    } );
    more.addEventListener( 'click', function () {
        setCount( count() + 1 );
    } );
    input.addEventListener( 'input', refresh );
    input.addEventListener( 'blur', function () {
        setCount( count() || 1 );
    } );
    numbered.addEventListener( 'change', refresh );
    cancel.addEventListener( 'click', function () {
        dialog.close();
    } );
    dialog.addEventListener( 'click', function ( event ) {
        if ( event.target === dialog ) {
            dialog.close();
        }
    } );

    form.addEventListener( 'submit', function ( event ) {
        event.preventDefault();
        var n = count();
        if ( ! n ) {
            return;
        }
        remember( { count: n, numbered: numbered.checked } );
        submit.disabled = true;
        cancel.disabled = true;
        submit.classList.add( 'is-busy' );
        submit.textContent = i18n.working;
        state.onConfirm( n, numbered.checked );
    } );

    // ---- Row action. ---------------------------------------------------

    document.addEventListener( 'click', function ( event ) {
        var link = event.target.closest && event.target.closest( 'a.dpp-copy-multiple' );
        if ( ! link ) {
            return;
        }
        event.preventDefault();
        open( {
            mode: 'row',
            url: link.href,
            title: link.getAttribute( 'data-title' ) || '',
            items: 1,
            onConfirm: function ( n, isNumbered ) {
                var url = new URL( link.href );
                url.searchParams.set( 'count', String( n ) );
                if ( isNumbered ) {
                    url.searchParams.set( 'numbered', '1' );
                }
                window.location.href = url.toString();
            }
        } );
    } );

    // ---- Bulk action. --------------------------------------------------

    var listForm = document.getElementById( 'posts-filter' );
    if ( ! listForm ) {
        return;
    }
    var confirmed = false;

    listForm.addEventListener( 'submit', function ( event ) {
        var submitter = event.submitter;
        if ( confirmed || ! submitter || ( 'doaction' !== submitter.id && 'doaction2' !== submitter.id ) ) {
            return;
        }
        var select = listForm.querySelector( 'doaction2' === submitter.id ? 'select[name="action2"]' : 'select[name="action"]' );
        if ( ! select || select.value !== data.bulkAction ) {
            return;
        }

        var selected = listForm.querySelectorAll( 'input[name="post[]"]:checked' ).length;
        if ( ! selected ) {
            return; // Nothing selected: core handles it as a no-op.
        }
        event.preventDefault();

        open( {
            mode: 'bulk',
            items: selected,
            title: '',
            onConfirm: function ( n, isNumbered ) {
                [ [ 'dpp_count', String( n ) ], [ 'dpp_numbered', isNumbered ? '1' : '' ] ].forEach( function ( pair ) {
                    var field = listForm.querySelector( 'input[name="' + pair[ 0 ] + '"]' ) || make( 'input' );
                    field.type = 'hidden';
                    field.name = pair[ 0 ];
                    field.value = pair[ 1 ];
                    listForm.appendChild( field );
                } );
                confirmed = true;
                submitter.click();
            }
        } );
    } );
}( window.dppList ) );
