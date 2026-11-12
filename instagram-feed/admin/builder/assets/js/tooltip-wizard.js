/**
 * Custom Facebook Tooltip Manager
 *
 * @since 4.0
 */
'use strict';

// Custom Twitter Feeds also declares window.SBITooltipWizard, so a shared name would hand us its object.
var SBIInstagramTooltipWizard = window.SBIInstagramTooltipWizard || (function (document, window, $) {

    /**
     * Public functions and properties.
     *
     * @since 4.0
     *
     * @type {object}
     */
    var app = {

        /**
         * Start the engine.
         *
         * @since 4.0
         */
        init: function () {

            //$( app.ready );
            $(window).on('load', function () {
                if ($.isFunction($.ready.then)) {
                    $.ready.then(app.load);
                } else {
                    app.load();
                }
            });
        },


        /**
         * Window load.
         *
         * @since 4.0
         */
        load: function () {
            if (sbi_admin_tooltip_wizard.sbi_wizard_gutenberg) {
                app.insertFeedBlock();
            }
        },

        /**
         * Insert the Instagram Feed block for the feed chosen in "Add to Page".
         * Falls back to the "Add a Block" tooltip when the block cannot be inserted.
         */
        insertFeedBlock: function () {
            // wp_localize_script turns 0 into "0", so parse it to fall back to the tooltip.
            var feedId = String(parseInt(sbi_admin_tooltip_wizard.sbi_wizard_feed_id, 10) || '');
            var name = 'smashballoon/instagram-feed';
            if (!feedId || !app.isGutenberg() || !wp.data || !wp.blocks.getBlockType(name)) {
                app.showTooltip();
                return;
            }
            var editor = wp.data.select('core/editor');
            var done = false;
            var unsubscribe;
            var blocksWaitOver = false;
            var waitTimer;
            var tryInsert = function () {
                // __unstableIsEditorReady exists since WP 5.3, older editors are set up before window load.
                if (done || (editor.__unstableIsEditorReady && !editor.__unstableIsEditorReady())) {
                    return;
                }
                // Start the 5s cap when the editor first reports ready, so a slow editor still gets the wait.
                if (!waitTimer) {
                    waitTimer = setTimeout(function () {
                        blocksWaitOver = true;
                        tryInsert();
                    }, 5000);
                }
                // On WP 6.1 the editor reports ready before core loads the saved blocks, and that load
                // overwrites an earlier insert. So also wait until the block list holds the saved content,
                // for up to 5s once ready: saved content that parses to no blocks would otherwise hold the insert forever.
                var post = editor.getCurrentPost();
                var saved = post && post.content;
                saved = String((saved && saved.raw !== undefined ? saved.raw : saved) || '').trim();
                if (saved !== '' && !blocksWaitOver && editor.getEditorBlocks && !editor.getEditorBlocks().length) {
                    return;
                }
                done = true;
                if (unsubscribe) {
                    unsubscribe();
                }
                var block = wp.blocks.createBlock(name, {feedId: feedId});
                if (wp.data.dispatch('core/editor').resetEditorBlocks) {
                    // WP 5.3+: write to the post itself, the block-editor store re-syncs from it after setup.
                    var blocks = editor.getEditorBlocks();
                    if (!app.hasFeedBlock(blocks, name, feedId)) {
                        wp.data.dispatch('core/editor').resetEditorBlocks(blocks.concat(block));
                    }
                } else {
                    // core/block-editor exists since WP 5.2, core/editor holds the blocks on 5.0/5.1.
                    var store = wp.data.select('core/block-editor') ? 'core/block-editor' : 'core/editor';
                    if (!app.hasFeedBlock(wp.data.select(store).getBlocks(), name, feedId)) {
                        wp.data.dispatch(store).insertBlocks(block);
                    }
                }
                // Drop sbi_wizard so a reload does not insert again.
                try {
                    var url = new URL(window.location.href);
                    url.searchParams.delete('sbi_wizard');
                    window.history.replaceState(window.history.state, '', url.toString());
                } catch (e) {} // URL or replaceState unsupported: the duplicate check still prevents a second insert.
            };
            tryInsert();
            if (!done) {
                unsubscribe = wp.data.subscribe(tryInsert);
            }
        },

        showTooltip: function () {
            app.waitForInserterButton(function () {
                app.initGutenbergTooltip();
            });
        },

        // The editor header renders after window load on WP 6.9+, so wait for the inserter toggle.
        waitForInserterButton: function (callback, attempts) {
            attempts = attempts || 0;
            var maxAttempts = 20;
            if (document.querySelector('.editor-document-tools__inserter-toggle, .edit-post-header-toolbar__inserter-toggle')) {
                callback();
                return;
            }
            if (attempts < maxAttempts) {
                setTimeout(function () {
                    app.waitForInserterButton(callback, attempts + 1);
                }, 500);
            } else {
                console.warn('SBIInstagramTooltipWizard: Inserter button not found after ' + maxAttempts + ' attempts.');
            }
        },

        hasFeedBlock: function (blocks, name, feedId) {
            return blocks.some(function (b) {
                return (b.name === name && String(b.attributes.feedId) === feedId)
                    || app.hasFeedBlock(b.innerBlocks || [], name, feedId);
            });
        },


        initGutenbergTooltip: function () {
            if (typeof $.fn.tooltipster === 'undefined') {
                return;
            }
            var $dot = $('<span class="wpforms-admin-form-embed-wizard-dot">&nbsp;</span>');
            // WP 7.x renamed the inserter toggle class, keep the old one for older WP.
            var anchor = $('.editor-document-tools__inserter-toggle, .edit-post-header-toolbar__inserter-toggle').first();
            var tooltipsterArgs = {
                content: $('#sbi-gutenberg-tooltip-content'),
                trigger: 'custom',
                interactive: true,
                animationDuration: 0,
                delay: 0,
                theme: ['tooltipster-default', 'sbi-tooltip-wizard'],
                side: 'bottom',
                distance: 3,
                functionReady: function (instance, helper) {
                    instance._$tooltip.on('click', '.sbi-tlp-wizard-close', function () {
                        instance.close();
                    });

                    instance.reposition();
                },
            };

            anchor.on('click', function () {
                $('.sbi-tooltip-wizard.tooltipster-sidetip').hide();
            });

            $dot.insertAfter(anchor).tooltipster(tooltipsterArgs).tooltipster('open');
        },

        /**
         * Check if we're in Gutenberg editor.
         *
         * @since 4.0
         *
         * @returns {boolean} Is Gutenberg or not.
         */
        isGutenberg: function () {

            return typeof wp !== 'undefined' && Object.prototype.hasOwnProperty.call(wp, 'blocks');
        },
    }

    return app;
}(document, window, jQuery));

SBIInstagramTooltipWizard.init();
