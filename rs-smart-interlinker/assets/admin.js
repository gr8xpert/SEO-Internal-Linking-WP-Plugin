/**
 * RS Smart Interlinker - Admin JavaScript
 */

(function($) {
    'use strict';

    var RSInterlinker = {
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Settings buttons
            $('#rs-rebuild-index').on('click', this.rebuildIndex.bind(this));
            $('#rs-test-api').on('click', this.testApi.bind(this));

            // Process posts
            $(document).on('click', '.rs-process-post', this.processPost.bind(this));
            $(document).on('click', '.rs-remove-links', this.removeLinks.bind(this));
            $('#rs-process-all').on('click', this.processAll.bind(this));
        },

        /**
         * Test API connection
         */
        testApi: function(e) {
            e.preventDefault();

            var $button = $('#rs-test-api');
            var $status = $('#rs-test-api-status');

            $button.prop('disabled', true);
            $status.removeClass('success error').html('Testing... <span class="rs-loading"></span>');

            $.ajax({
                url: rsInterlinker.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'rs_interlinker_test_api',
                    nonce: rsInterlinker.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $status.addClass('success').text(response.data.message);
                    } else {
                        $status.addClass('error').text(response.data || rsInterlinker.strings.error);
                    }
                },
                error: function() {
                    $status.addClass('error').text(rsInterlinker.strings.error);
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        },

        /**
         * Rebuild keyword index
         */
        rebuildIndex: function(e) {
            e.preventDefault();

            var $button = $('#rs-rebuild-index');
            var $status = $('#rs-rebuild-index-status');

            $button.prop('disabled', true);
            $status.removeClass('success error').html('Rebuilding... <span class="rs-loading"></span>');

            $.ajax({
                url: rsInterlinker.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'rs_interlinker_rebuild_index',
                    nonce: rsInterlinker.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $status.addClass('success').text(response.data.message);
                    } else {
                        $status.addClass('error').text(response.data || rsInterlinker.strings.error);
                    }
                },
                error: function() {
                    $status.addClass('error').text(rsInterlinker.strings.error);
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        },

        /**
         * Process a single post
         */
        processPost: function(e) {
            e.preventDefault();

            var $button = $(e.currentTarget);
            var postId = $button.data('post-id');
            var $row = $button.closest('tr');

            $button.prop('disabled', true).text(rsInterlinker.strings.processing);

            $.ajax({
                url: rsInterlinker.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'rs_interlinker_process_post',
                    nonce: rsInterlinker.nonce,
                    post_id: postId
                },
                success: function(response) {
                    if (response.success) {
                        // Update row
                        $row.find('.column-status').html(
                            '<span class="rs-status rs-status-processed">' + rsInterlinker.strings.processed + '</span>'
                        );
                        $row.find('.column-links').text(response.data.links_count);
                        $row.find('.column-actions').html(
                            '<button type="button" class="button button-small rs-remove-links" data-post-id="' + postId + '">Remove Links</button>'
                        );
                    } else {
                        alert(response.data || rsInterlinker.strings.error);
                        $button.prop('disabled', false).text('Process');
                    }
                },
                error: function() {
                    alert(rsInterlinker.strings.error);
                    $button.prop('disabled', false).text('Process');
                }
            });
        },

        /**
         * Remove links from a post
         */
        removeLinks: function(e) {
            e.preventDefault();

            if (!confirm(rsInterlinker.strings.confirm_remove)) {
                return;
            }

            var $button = $(e.currentTarget);
            var postId = $button.data('post-id');
            var $row = $button.closest('tr');

            $button.prop('disabled', true).text(rsInterlinker.strings.removing);

            $.ajax({
                url: rsInterlinker.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'rs_interlinker_remove_links',
                    nonce: rsInterlinker.nonce,
                    post_id: postId
                },
                success: function(response) {
                    if (response.success) {
                        // Update row
                        $row.find('.column-status').html(
                            '<span class="rs-status rs-status-pending">Not Processed</span>'
                        );
                        $row.find('.column-links').text('—');
                        $row.find('.column-actions').html(
                            '<button type="button" class="button button-small button-primary rs-process-post" data-post-id="' + postId + '">Process</button>'
                        );
                    } else {
                        alert(response.data || rsInterlinker.strings.error);
                        $button.prop('disabled', false).text('Remove Links');
                    }
                },
                error: function() {
                    alert(rsInterlinker.strings.error);
                    $button.prop('disabled', false).text('Remove Links');
                }
            });
        },

        /**
         * Process all unprocessed posts
         */
        processAll: function(e) {
            e.preventDefault();

            if (!confirm(rsInterlinker.strings.confirm_process_all)) {
                return;
            }

            var $button = $('#rs-process-all');
            var $status = $('#rs-process-all-status');
            var $unprocessedButtons = $('.rs-process-post');
            var total = $unprocessedButtons.length;
            var processed = 0;
            var errors = 0;

            if (total === 0) {
                alert('No unprocessed posts found.');
                return;
            }

            $button.prop('disabled', true);
            $status.html('Processing 0/' + total + '... <span class="rs-loading"></span>');

            // Process posts one by one
            var processNext = function(index) {
                if (index >= total) {
                    $status.html('Done! Processed: ' + processed + ', Errors: ' + errors);
                    $button.prop('disabled', false);
                    return;
                }

                var $btn = $unprocessedButtons.eq(index);
                var postId = $btn.data('post-id');
                var $row = $btn.closest('tr');

                $btn.prop('disabled', true).text('...');

                $.ajax({
                    url: rsInterlinker.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'rs_interlinker_process_post',
                        nonce: rsInterlinker.nonce,
                        post_id: postId
                    },
                    success: function(response) {
                        if (response.success) {
                            processed++;
                            $row.find('.column-status').html(
                                '<span class="rs-status rs-status-processed">Processed</span>'
                            );
                            $row.find('.column-links').text(response.data.links_count);
                            $row.find('.column-actions').html(
                                '<button type="button" class="button button-small rs-remove-links" data-post-id="' + postId + '">Remove Links</button>'
                            );
                        } else {
                            errors++;
                            $btn.prop('disabled', false).text('Process');
                        }
                    },
                    error: function() {
                        errors++;
                        $btn.prop('disabled', false).text('Process');
                    },
                    complete: function() {
                        $status.html('Processing ' + (index + 1) + '/' + total + '... <span class="rs-loading"></span>');
                        // Delay to avoid OpenRouter rate limiting (3 seconds between requests)
                        setTimeout(function() {
                            processNext(index + 1);
                        }, 3000);
                    }
                });
            };

            processNext(0);
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        RSInterlinker.init();
    });

})(jQuery);
