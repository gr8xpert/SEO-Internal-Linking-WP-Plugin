<?php
/**
 * Meta Box Class
 *
 * Handles per-post keyword override meta box.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RS_Interlinker_Meta_Box {

    /**
     * Meta key for custom keywords
     */
    const META_KEY = '_rs_interlinker_keywords';

    /**
     * Meta key for generated HTML (from processor)
     */
    const META_ADDED_HTML = '_rs_interlinker_added_html';

    /**
     * Meta key for processed status (from processor)
     */
    const META_PROCESSED = '_rs_interlinker_processed';

    /**
     * Add meta box to selected post types
     */
    public function add_meta_box() {
        $options    = get_option( 'rs_interlinker_options', array() );
        $post_types = isset( $options['post_types'] ) ? $options['post_types'] : array();

        foreach ( $post_types as $post_type ) {
            add_meta_box(
                'rs-interlinker-keywords',
                __( 'RS Smart Interlinker — Custom Keywords', 'rs-smart-interlinker' ),
                array( $this, 'render_meta_box' ),
                $post_type,
                'side',
                'default'
            );
        }
    }

    /**
     * Render meta box content
     *
     * @param WP_Post $post Post object
     */
    public function render_meta_box( $post ) {
        wp_nonce_field( 'rs_interlinker_meta_box', 'rs_interlinker_meta_nonce' );

        $keywords      = get_post_meta( $post->ID, self::META_KEY, true );
        $added_html    = get_post_meta( $post->ID, self::META_ADDED_HTML, true );
        $processed     = get_post_meta( $post->ID, self::META_PROCESSED, true );

        // Debug info (can be removed later)
        if ( current_user_can( 'manage_options' ) ) {
            echo '<p style="background:#fffbcc;padding:5px;font-size:11px;margin-bottom:10px;">';
            echo '<strong>Debug:</strong> Processed: ' . ( $processed ? 'Yes (' . esc_html( $processed ) . ')' : 'No' );
            echo ' | Has HTML: ' . ( $added_html ? 'Yes (' . strlen( $added_html ) . ' chars)' : 'No' );
            echo '</p>';
        }
        ?>
        <p>
            <label for="rs-interlinker-keywords">
                <strong><?php esc_html_e( 'Custom Keywords (comma-separated):', 'rs-smart-interlinker' ); ?></strong>
            </label>
        </p>
        <textarea
            id="rs-interlinker-keywords"
            name="rs_interlinker_keywords"
            rows="3"
            style="width: 100%;"
            placeholder="<?php esc_attr_e( 'e.g., Marbella, Costa del Sol, Spain', 'rs-smart-interlinker' ); ?>"
        ><?php echo esc_textarea( $keywords ); ?></textarea>
        <p class="description">
            <?php esc_html_e( 'Override auto-extracted keywords. Leave empty for automatic extraction.', 'rs-smart-interlinker' ); ?>
        </p>

        <?php if ( $processed ) : ?>
            <hr style="margin: 15px 0;">
            <p>
                <label for="rs-interlinker-generated-html">
                    <strong><?php esc_html_e( 'Generated Content (editable):', 'rs-smart-interlinker' ); ?></strong>
                </label>
            </p>
            <textarea
                id="rs-interlinker-generated-html"
                name="rs_interlinker_generated_html"
                rows="5"
                style="width: 100%;"
            ><?php echo esc_textarea( $added_html ); ?></textarea>
            <p class="description">
                <?php esc_html_e( 'Edit the AI-generated sentence. HTML links are allowed.', 'rs-smart-interlinker' ); ?>
            </p>
            <p style="margin-top: 10px; padding: 8px; background: #f0f0f1; border-radius: 3px;">
                <strong><?php esc_html_e( 'Preview:', 'rs-smart-interlinker' ); ?></strong><br>
                <?php echo wp_kses( $added_html, $this->get_allowed_html() ); ?>
            </p>
        <?php else : ?>
            <hr style="margin: 15px 0;">
            <p style="color: #666; font-style: italic;">
                <?php esc_html_e( 'No content generated yet. Use "Process" button in RS Interlinker settings to generate AI content.', 'rs-smart-interlinker' ); ?>
            </p>
        <?php endif; ?>
        <?php
    }

    /**
     * Get allowed HTML tags for generated content
     *
     * @return array Allowed HTML tags
     */
    private function get_allowed_html() {
        return array(
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
    }

    /**
     * Save meta box data
     *
     * @param int $post_id Post ID
     */
    public function save_meta_box( $post_id ) {
        // Verify nonce
        if ( ! isset( $_POST['rs_interlinker_meta_nonce'] ) ||
             ! wp_verify_nonce( $_POST['rs_interlinker_meta_nonce'], 'rs_interlinker_meta_box' ) ) {
            return;
        }

        // Check autosave
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        // Check permissions
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Check if this post type is selected
        $options    = get_option( 'rs_interlinker_options', array() );
        $post_types = isset( $options['post_types'] ) ? $options['post_types'] : array();

        if ( ! in_array( get_post_type( $post_id ), $post_types, true ) ) {
            return;
        }

        // Save or delete keywords meta
        if ( isset( $_POST['rs_interlinker_keywords'] ) ) {
            $keywords = sanitize_textarea_field( $_POST['rs_interlinker_keywords'] );

            if ( ! empty( trim( $keywords ) ) ) {
                update_post_meta( $post_id, self::META_KEY, $keywords );
            } else {
                delete_post_meta( $post_id, self::META_KEY );
            }
        }

        // Save edited generated HTML (sanitize to prevent XSS)
        if ( isset( $_POST['rs_interlinker_generated_html'] ) ) {
            $html = wp_kses( $_POST['rs_interlinker_generated_html'], $this->get_allowed_html() );

            if ( ! empty( trim( $html ) ) ) {
                update_post_meta( $post_id, self::META_ADDED_HTML, $html );
            }
        }
    }
}
