/**
 * Duplicate PP — block editor integration.
 *
 * - Adds "Copy to a new draft" / "Rewrite & Republish" buttons and the
 *   "Copy of" link to the post summary panel.
 * - Shows the notices that follow a copy action.
 * - On a Rewrite & Republish copy: relabels Publish as Republish and,
 *   once the copy is published (and meta boxes are saved), opens the
 *   finish action that deletes the copy and returns to the original.
 *
 * Data comes from window.dppEditor (see includes/Editor.php).
 */
( function ( wp, data ) {
    'use strict';

    if ( ! wp || ! data ) {
        return;
    }

    var el = wp.element.createElement;
    var __ = wp.i18n.__;
    var sprintf = wp.i18n.sprintf;
    var Button = wp.components.Button;

    function PostStatusInfo( props ) {
        var Slot = ( wp.editor && wp.editor.PluginPostStatusInfo ) || ( wp.editPost && wp.editPost.PluginPostStatusInfo );
        return Slot ? el( Slot, { className: 'dpp-status-info' }, props.children ) : null;
    }

    function RelatedLink( props ) {
        var url = props.item.editUrl || props.item.viewUrl;
        return el(
            'p',
            { className: 'dpp-status-info__related' },
            el( 'span', { className: 'dpp-status-info__label' }, props.label ),
            ' ',
            el( 'a', { href: url }, props.item.title )
        );
    }

    function Panel() {
        var children = [];

        if ( data.isRewriteCopy && data.rewriteOriginal ) {
            children.push( el( RelatedLink, { key: 'rewrite-of', label: __( 'Rewrite of:', 'duplicate-pp' ), item: data.rewriteOriginal } ) );
        } else if ( data.original ) {
            children.push( el( RelatedLink, { key: 'copy-of', label: __( 'Copy of:', 'duplicate-pp' ), item: data.original } ) );
        }

        var buttons = [];
        if ( data.newDraftUrl ) {
            buttons.push( el( Button, { key: 'draft', variant: 'secondary', href: data.newDraftUrl, __next40pxDefaultSize: true }, __( 'Copy to a new draft', 'duplicate-pp' ) ) );
        }
        if ( data.rewriteUrl ) {
            buttons.push( el( Button, { key: 'rewrite', variant: 'secondary', href: data.rewriteUrl, __next40pxDefaultSize: true }, __( 'Rewrite & Republish', 'duplicate-pp' ) ) );
        } else if ( data.rewriteCopyUrl ) {
            buttons.push( el( Button, { key: 'rewrite', variant: 'tertiary', href: data.rewriteCopyUrl, __next40pxDefaultSize: true }, __( 'Continue rewrite', 'duplicate-pp' ) ) );
        }
        if ( buttons.length ) {
            children.push( el( 'div', { key: 'buttons', className: 'dpp-status-info__buttons' }, buttons ) );
        }

        if ( ! children.length ) {
            return null;
        }
        return el( PostStatusInfo, null, el( 'div', { className: 'dpp-status-info__inner' }, children ) );
    }

    wp.plugins.registerPlugin( 'duplicate-pp', { render: Panel } );

    // Notices after a redirect.
    wp.domReady( function () {
        var notices = wp.data.dispatch( 'core/notices' );
        var messages = {
            new_draft: __( 'A new draft has been created. You are now editing the copy.', 'duplicate-pp' ),
            cloned: __( 'The item has been duplicated. You are now editing the copy.', 'duplicate-pp' ),
            republished: __( 'The original has been republished with your changes.', 'duplicate-pp' )
        };

        if ( data.notice && messages[ data.notice ] ) {
            notices.createSuccessNotice( messages[ data.notice ], { id: 'dpp-notice', type: 'snackbar' } );
        }

        if ( data.isRewriteCopy && data.rewriteOriginal ) {
            notices.createInfoNotice(
                sprintf(
                    /* translators: %s: original post title. */
                    __( 'You are editing a Rewrite & Republish copy of “%s”. When you republish, your changes replace the original and this copy is removed. Schedule it to republish later.', 'duplicate-pp' ),
                    data.rewriteOriginal.title
                ),
                {
                    id: 'dpp-rewrite-copy',
                    isDismissible: false,
                    actions: data.rewriteOriginal.viewUrl ? [ { label: __( 'View the original', 'duplicate-pp' ), url: data.rewriteOriginal.viewUrl } ] : []
                }
            );
        }

        // Strip the flag so a reload does not repeat the notice.
        if ( data.notice && window.history && window.history.replaceState ) {
            var url = new URL( window.location.href );
            url.searchParams.delete( 'dpp_notice' );
            window.history.replaceState( null, '', url.toString() );
        }
    } );

    if ( ! data.isRewriteCopy || ! data.finishUrl ) {
        return;
    }

    // Rewrite & Republish copy: say "Republish" where core says "Publish".
    var labels = {
        'Publish': __( 'Republish', 'duplicate-pp' ),
        'Publish:': __( 'Republish:', 'duplicate-pp' ),
        'Are you ready to publish?': __( 'Are you ready to republish your post?', 'duplicate-pp' ),
        'Double-check your settings before publishing.': __( 'After republishing, your changes replace the original and this copy is removed.', 'duplicate-pp' )
    };
    wp.hooks.addFilter( 'i18n.gettext', 'duplicate-pp/republish-labels', function ( translation, text, domain ) {
        if ( ( ! domain || 'default' === domain ) && Object.prototype.hasOwnProperty.call( labels, text ) ) {
            return labels[ text ];
        }
        return translation;
    } );

    // After a successful publish, wait for meta boxes, then finish.
    var wasSaving = false;
    var finishing = false;

    function savingMetaBoxes() {
        var editPost = wp.data.select( 'core/edit-post' );
        return !! ( editPost && editPost.isSavingMetaBoxes && editPost.isSavingMetaBoxes() );
    }

    function finishWhenIdle() {
        if ( savingMetaBoxes() ) {
            window.setTimeout( finishWhenIdle, 150 );
            return;
        }
        window.location.href = data.finishUrl;
    }

    wp.data.subscribe( function () {
        if ( finishing ) {
            return;
        }
        var editor = wp.data.select( 'core/editor' );
        var saving = editor.isSavingPost() && ! editor.isAutosavingPost();

        if ( saving ) {
            wasSaving = true;
            return;
        }
        if ( ! wasSaving ) {
            return;
        }
        wasSaving = false;

        if ( editor.didPostSaveRequestSucceed() && 'publish' === editor.getCurrentPostAttribute( 'status' ) ) {
            finishing = true;
            // Give core a tick to start the meta box request, if any.
            window.setTimeout( finishWhenIdle, 150 );
        }
    } );
}( window.wp, window.dppEditor ) );
