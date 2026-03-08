<?php
/**
 * Processor Class
 *
 * Handles processing posts and saving interlinks to post meta.
 * Content is displayed via the_content filter (non-destructive).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RS_Interlinker_Processor {

    /**
     * Meta keys
     */
    const META_PROCESSED = '_rs_interlinker_processed';
    const META_ADDED_HTML = '_rs_interlinker_added_html';
    const META_LINKS_COUNT = '_rs_interlinker_links_count';

    /**
     * Indexer instance
     */
    private $indexer;

    /**
     * AI Engine instance
     */
    private $ai_engine;

    /**
     * Constructor
     */
    public function __construct( $indexer, $ai_engine ) {
        $this->indexer   = $indexer;
        $this->ai_engine = $ai_engine;

        // Add the_content filter to display stored sentences
        add_filter( 'the_content', array( $this, 'display_interlinks' ), 9999 );
    }

    /**
     * Display stored interlinks via the_content filter
     */
    public function display_interlinks( $content ) {
        if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
            return $content;
        }

        $post_id = get_the_ID();
        if ( ! $post_id ) {
            return $content;
        }

        // Check if post has been processed
        $processed = get_post_meta( $post_id, self::META_PROCESSED, true );
        if ( ! $processed ) {
            return $content;
        }

        // Get stored HTML
        $added_html = get_post_meta( $post_id, self::META_ADDED_HTML, true );
        if ( empty( $added_html ) ) {
            return $content;
        }

        // Sanitize HTML before output (defense in depth)
        $safe_html = $this->sanitize_output_html( $added_html );

        // Append the sentence
        $wrapped = '<p class="rs-interlinker-added">' . $safe_html . '</p>';

        return $content . "\n" . $wrapped;
    }

    /**
     * Sanitize HTML for safe output
     */
    private function sanitize_output_html( $html ) {
        $allowed_tags = array(
            'a' => array(
                'href'   => array(),
                'title'  => array(),
                'target' => array(),
                'rel'    => array(),
            ),
            'strong' => array(),
            'em'     => array(),
            'b'      => array(),
            'i'      => array(),
        );

        return wp_kses( $html, $allowed_tags );
    }

    /**
     * Process a single post
     *
     * @param int $post_id Post ID
     * @return array Result with success status and message
     */
    public function process_post( $post_id ) {
        $post = get_post( $post_id );

        if ( ! $post ) {
            return array(
                'success' => false,
                'message' => __( 'Post not found.', 'rs-smart-interlinker' ),
            );
        }

        $options    = get_option( 'rs_interlinker_options', array() );
        $post_types = isset( $options['post_types'] ) ? $options['post_types'] : array();

        if ( ! in_array( $post->post_type, $post_types ) ) {
            return array(
                'success' => false,
                'message' => __( 'Post type not enabled for interlinking.', 'rs-smart-interlinker' ),
            );
        }

        // Check if already processed
        $already_processed = get_post_meta( $post_id, self::META_PROCESSED, true );
        if ( $already_processed ) {
            return array(
                'success' => false,
                'message' => __( 'Post already processed. Remove existing links first.', 'rs-smart-interlinker' ),
            );
        }

        $max_links   = isset( $options['max_internal_links'] ) ? (int) $options['max_internal_links'] : 3;

        // Generate AI sentence with links
        $ai_result = $this->generate_ai_links( $post_id, $post->post_title, $max_links );

        if ( ! $ai_result || empty( $ai_result['html'] ) ) {
            return array(
                'success' => false,
                'message' => __( 'Failed to generate AI links. Check your API key.', 'rs-smart-interlinker' ),
            );
        }

        $links_count = count( $ai_result['internal_links'] );
        if ( ! empty( $ai_result['external_link'] ) ) {
            $links_count++;
        }

        // Store in post meta (NOT in post_content)
        update_post_meta( $post_id, self::META_PROCESSED, current_time( 'mysql' ) );
        update_post_meta( $post_id, self::META_ADDED_HTML, $ai_result['html'] );
        update_post_meta( $post_id, self::META_LINKS_COUNT, $links_count );

        return array(
            'success'     => true,
            'message'     => sprintf( __( 'Successfully added %d links.', 'rs-smart-interlinker' ), $links_count ),
            'links_count' => $links_count,
        );
    }

    /**
     * Generate AI links for a post
     */
    private function generate_ai_links( $post_id, $post_title, $max_links ) {
        $keyword_index = $this->indexer->get_index_for_ai( $post_id );

        if ( empty( $keyword_index ) ) {
            return null;
        }

        return $this->ai_engine->generate_topup_links(
            $post_id,
            $post_title,
            $keyword_index,
            $max_links
        );
    }

    /**
     * Remove interlinks from a post
     */
    public function remove_links( $post_id ) {
        // Check if post was processed
        $processed = get_post_meta( $post_id, self::META_PROCESSED, true );
        if ( ! $processed ) {
            return array(
                'success' => false,
                'message' => __( 'Post has not been processed.', 'rs-smart-interlinker' ),
            );
        }

        // Clean up any content that was added to post_content by old version
        $post = get_post( $post_id );
        if ( $post ) {
            $content = $post->post_content;
            $original_content = $content;

            // Remove any rs-interlinker-added paragraphs from post_content
            $content = preg_replace( '/<p class="rs-interlinker-added">.*?<\/p>\s*/is', '', $content );

            // Only update if content changed
            if ( $content !== $original_content ) {
                wp_update_post( array(
                    'ID'           => $post_id,
                    'post_content' => $content,
                ) );
            }
        }

        // Remove metadata
        delete_post_meta( $post_id, self::META_PROCESSED );
        delete_post_meta( $post_id, self::META_ADDED_HTML );
        delete_post_meta( $post_id, self::META_LINKS_COUNT );

        return array(
            'success' => true,
            'message' => __( 'Links removed successfully.', 'rs-smart-interlinker' ),
        );
    }

    /**
     * Get processing status for all posts
     */
    public function get_posts_status() {
        $options    = get_option( 'rs_interlinker_options', array() );
        $post_types = isset( $options['post_types'] ) ? $options['post_types'] : array();

        if ( empty( $post_types ) ) {
            return array();
        }

        $posts = get_posts( array(
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ) );

        $status = array();

        foreach ( $posts as $post ) {
            $processed   = get_post_meta( $post->ID, self::META_PROCESSED, true );
            $links_count = get_post_meta( $post->ID, self::META_LINKS_COUNT, true );

            $status[] = array(
                'id'          => $post->ID,
                'title'       => $post->post_title,
                'post_type'   => $post->post_type,
                'processed'   => ! empty( $processed ),
                'processed_date' => $processed ? $processed : null,
                'links_count' => $links_count ? (int) $links_count : 0,
                'edit_url'    => get_edit_post_link( $post->ID, 'raw' ),
                'view_url'    => get_permalink( $post->ID ),
            );
        }

        return $status;
    }

    /**
     * AJAX handler: Process single post
     */
    public function ajax_process_post() {
        check_ajax_referer( 'rs_interlinker_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'rs-smart-interlinker' ) );
        }

        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

        if ( ! $post_id ) {
            wp_send_json_error( __( 'Invalid post ID.', 'rs-smart-interlinker' ) );
        }

        $result = $this->process_post( $post_id );

        if ( $result['success'] ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result['message'] );
        }
    }

    /**
     * AJAX handler: Remove links from post
     */
    public function ajax_remove_links() {
        check_ajax_referer( 'rs_interlinker_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'rs-smart-interlinker' ) );
        }

        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

        if ( ! $post_id ) {
            wp_send_json_error( __( 'Invalid post ID.', 'rs-smart-interlinker' ) );
        }

        $result = $this->remove_links( $post_id );

        if ( $result['success'] ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result['message'] );
        }
    }

    /**
     * AJAX handler: Get posts status
     */
    public function ajax_get_posts_status() {
        check_ajax_referer( 'rs_interlinker_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'rs-smart-interlinker' ) );
        }

        $status = $this->get_posts_status();
        wp_send_json_success( $status );
    }
}
