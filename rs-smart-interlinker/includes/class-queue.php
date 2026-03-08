<?php
/**
 * Queue Processor Class
 *
 * Handles background processing of posts via WP Cron.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RS_Interlinker_Queue {

    /**
     * Option name for queue
     */
    const QUEUE_OPTION = 'rs_interlinker_queue';

    /**
     * Option name for queue status
     */
    const STATUS_OPTION = 'rs_interlinker_queue_status';

    /**
     * Cron hook name
     */
    const CRON_HOOK = 'rs_interlinker_process_queue';

    /**
     * Posts to process per cron run
     */
    const BATCH_SIZE = 2;

    /**
     * Processor instance
     */
    private $processor;

    /**
     * Constructor
     */
    public function __construct( $processor ) {
        $this->processor = $processor;

        // Register cron hook
        add_action( self::CRON_HOOK, array( $this, 'process_queue' ) );

        // AJAX handlers
        add_action( 'wp_ajax_rs_interlinker_start_queue', array( $this, 'ajax_start_queue' ) );
        add_action( 'wp_ajax_rs_interlinker_stop_queue', array( $this, 'ajax_stop_queue' ) );
        add_action( 'wp_ajax_rs_interlinker_queue_status', array( $this, 'ajax_queue_status' ) );
    }

    /**
     * Start background queue processing
     */
    public function start_queue() {
        // Get all unprocessed posts
        $posts_status = $this->processor->get_posts_status();
        $queue = array();

        foreach ( $posts_status as $post ) {
            if ( ! $post['processed'] ) {
                $queue[] = $post['id'];
            }
        }

        if ( empty( $queue ) ) {
            return array(
                'success' => false,
                'message' => __( 'No unprocessed posts found.', 'rs-smart-interlinker' ),
            );
        }

        // Save queue
        update_option( self::QUEUE_OPTION, $queue );

        // Save status
        update_option( self::STATUS_OPTION, array(
            'running'    => true,
            'total'      => count( $queue ),
            'processed'  => 0,
            'errors'     => 0,
            'started_at' => current_time( 'mysql' ),
            'last_run'   => null,
        ) );

        // Schedule cron event (every 2 minutes)
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time(), 'rs_interlinker_interval', self::CRON_HOOK );
        }

        return array(
            'success' => true,
            'message' => sprintf( __( 'Started processing %d posts in background.', 'rs-smart-interlinker' ), count( $queue ) ),
            'total'   => count( $queue ),
        );
    }

    /**
     * Stop background queue processing
     */
    public function stop_queue() {
        // Clear scheduled event
        wp_clear_scheduled_hook( self::CRON_HOOK );

        // Update status
        $status = get_option( self::STATUS_OPTION, array() );
        $status['running'] = false;
        $status['stopped_at'] = current_time( 'mysql' );
        update_option( self::STATUS_OPTION, $status );

        return array(
            'success' => true,
            'message' => __( 'Background processing stopped.', 'rs-smart-interlinker' ),
        );
    }

    /**
     * Process queue (called by cron)
     */
    public function process_queue() {
        $queue = get_option( self::QUEUE_OPTION, array() );
        $status = get_option( self::STATUS_OPTION, array() );

        if ( empty( $queue ) || empty( $status['running'] ) ) {
            // Queue empty or stopped, clear cron
            wp_clear_scheduled_hook( self::CRON_HOOK );
            $status['running'] = false;
            $status['completed_at'] = current_time( 'mysql' );
            update_option( self::STATUS_OPTION, $status );
            return;
        }

        // Process batch
        $batch = array_splice( $queue, 0, self::BATCH_SIZE );
        $processed = 0;
        $errors = 0;

        foreach ( $batch as $post_id ) {
            $result = $this->processor->process_post( $post_id );

            if ( $result['success'] ) {
                $processed++;
            } else {
                $errors++;
                error_log( 'RS Interlinker Queue: Failed to process post ' . $post_id . ': ' . $result['message'] );
            }

            // Small delay between posts
            sleep( 5 );
        }

        // Update queue
        update_option( self::QUEUE_OPTION, $queue );

        // Update status
        $status['processed'] += $processed;
        $status['errors'] += $errors;
        $status['last_run'] = current_time( 'mysql' );
        $status['remaining'] = count( $queue );

        if ( empty( $queue ) ) {
            $status['running'] = false;
            $status['completed_at'] = current_time( 'mysql' );
            wp_clear_scheduled_hook( self::CRON_HOOK );
        }

        update_option( self::STATUS_OPTION, $status );
    }

    /**
     * Get queue status
     */
    public function get_status() {
        $status = get_option( self::STATUS_OPTION, array() );
        $queue = get_option( self::QUEUE_OPTION, array() );

        return array(
            'running'    => ! empty( $status['running'] ),
            'total'      => isset( $status['total'] ) ? $status['total'] : 0,
            'processed'  => isset( $status['processed'] ) ? $status['processed'] : 0,
            'errors'     => isset( $status['errors'] ) ? $status['errors'] : 0,
            'remaining'  => count( $queue ),
            'started_at' => isset( $status['started_at'] ) ? $status['started_at'] : null,
            'last_run'   => isset( $status['last_run'] ) ? $status['last_run'] : null,
            'completed_at' => isset( $status['completed_at'] ) ? $status['completed_at'] : null,
        );
    }

    /**
     * AJAX: Start queue
     */
    public function ajax_start_queue() {
        check_ajax_referer( 'rs_interlinker_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'rs-smart-interlinker' ) );
        }

        $result = $this->start_queue();

        if ( $result['success'] ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result['message'] );
        }
    }

    /**
     * AJAX: Stop queue
     */
    public function ajax_stop_queue() {
        check_ajax_referer( 'rs_interlinker_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'rs-smart-interlinker' ) );
        }

        $result = $this->stop_queue();
        wp_send_json_success( $result );
    }

    /**
     * AJAX: Get queue status
     */
    public function ajax_queue_status() {
        check_ajax_referer( 'rs_interlinker_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'rs-smart-interlinker' ) );
        }

        $status = $this->get_status();
        wp_send_json_success( $status );
    }

    /**
     * Register custom cron interval
     */
    public static function register_cron_interval( $schedules ) {
        $schedules['rs_interlinker_interval'] = array(
            'interval' => 120, // 2 minutes
            'display'  => __( 'Every 2 Minutes', 'rs-smart-interlinker' ),
        );
        return $schedules;
    }
}
