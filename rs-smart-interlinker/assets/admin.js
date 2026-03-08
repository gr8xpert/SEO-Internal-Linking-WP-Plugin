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

        queueStatusInterval: null,

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

            // Queue controls
            $('#rs-start-queue').on('click', this.startQueue.bind(this));
            $('#rs-stop-queue').on('click', this.stopQueue.bind(this));

            // Check queue status on page load
            this.checkQueueStatus();
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
        },

        /**
         * Start background queue processing
         */
        startQueue: function(e) {
            e.preventDefault();

            var self = this;
            var $button = $('#rs-start-queue');

            if (!confirm('Start background processing? This will process all unprocessed posts automatically.')) {
                return;
            }

            $button.prop('disabled', true).text('Starting...');

            $.ajax({
                url: rsInterlinker.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'rs_interlinker_start_queue',
                    nonce: rsInterlinker.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showQueueStatus(true);
                        self.startStatusPolling();
                    } else {
                        alert(response.data || 'Failed to start queue');
                        $button.prop('disabled', false).text('Start Background Processing');
                    }
                },
                error: function() {
                    alert('Error starting queue');
                    $button.prop('disabled', false).text('Start Background Processing');
                }
            });
        },

        /**
         * Stop background queue processing
         */
        stopQueue: function(e) {
            e.preventDefault();

            var self = this;

            if (!confirm('Stop background processing?')) {
                return;
            }

            $.ajax({
                url: rsInterlinker.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'rs_interlinker_stop_queue',
                    nonce: rsInterlinker.nonce
                },
                success: function(response) {
                    self.stopStatusPolling();
                    self.showQueueStatus(false);
                    $('#rs-start-queue').prop('disabled', false).text('Start Background Processing');
                },
                error: function() {
                    alert('Error stopping queue');
                }
            });
        },

        /**
         * Check queue status
         */
        checkQueueStatus: function() {
            var self = this;

            $.ajax({
                url: rsInterlinker.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'rs_interlinker_queue_status',
                    nonce: rsInterlinker.nonce
                },
                success: function(response) {
                    if (response.success && response.data.running) {
                        self.showQueueStatus(true);
                        self.updateQueueDisplay(response.data);
                        self.startStatusPolling();
                    } else if (response.success && response.data.completed_at && response.data.processed > 0) {
                        // Show completed status
                        self.showCompletedStatus(response.data);
                    }
                }
            });
        },

        /**
         * Start polling for status updates
         */
        startStatusPolling: function() {
            var self = this;

            if (this.queueStatusInterval) {
                clearInterval(this.queueStatusInterval);
            }

            this.queueStatusInterval = setInterval(function() {
                $.ajax({
                    url: rsInterlinker.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'rs_interlinker_queue_status',
                        nonce: rsInterlinker.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            self.updateQueueDisplay(response.data);

                            if (!response.data.running) {
                                self.stopStatusPolling();
                                self.showCompletedStatus(response.data);
                            }
                        }
                    }
                });
            }, 10000); // Poll every 10 seconds
        },

        /**
         * Stop polling
         */
        stopStatusPolling: function() {
            if (this.queueStatusInterval) {
                clearInterval(this.queueStatusInterval);
                this.queueStatusInterval = null;
            }
        },

        /**
         * Show/hide queue status box
         */
        showQueueStatus: function(show) {
            if (show) {
                $('#rs-queue-status-box').show();
                $('#rs-queue-completed-box').hide();
                $('#rs-start-queue').hide();
                $('#rs-stop-queue').show();
            } else {
                $('#rs-queue-status-box').hide();
                $('#rs-start-queue').show().prop('disabled', false).text('Start Background Processing');
                $('#rs-stop-queue').hide();
            }
        },

        /**
         * Update queue display
         */
        updateQueueDisplay: function(data) {
            var percent = data.total > 0 ? Math.round((data.processed / data.total) * 100) : 0;

            $('#rs-queue-processed').text(data.processed);
            $('#rs-queue-total').text(data.total);
            $('#rs-queue-percent').text(percent);
            $('#rs-queue-remaining').text(data.remaining);
            $('#rs-queue-errors').text(data.errors);
            $('#rs-queue-lastrun').text(data.last_run || '-');
            $('#rs-queue-progress-bar').css('width', percent + '%');

            // Calculate ETA
            if (data.remaining > 0 && data.processed > 0) {
                var postsPerMin = 1; // ~2 posts per 2 minutes
                var minsRemaining = Math.ceil(data.remaining / postsPerMin);
                var hours = Math.floor(minsRemaining / 60);
                var mins = minsRemaining % 60;
                var eta = hours > 0 ? hours + 'h ' + mins + 'm' : mins + ' minutes';
                $('#rs-queue-eta').text('Estimated time remaining: ~' + eta);
            } else {
                $('#rs-queue-eta').text('');
            }
        },

        /**
         * Show completed status
         */
        showCompletedStatus: function(data) {
            $('#rs-queue-status-box').hide();
            $('#rs-queue-completed-box').show();
            $('#rs-queue-final-processed').text(data.processed);
            $('#rs-queue-final-errors').text(data.errors);
            $('#rs-start-queue').show().prop('disabled', false).text('Start Background Processing');
            $('#rs-stop-queue').hide();
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        RSInterlinker.init();
    });

})(jQuery);
