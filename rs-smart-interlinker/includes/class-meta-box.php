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

        $keywords = get_post_meta( $post->ID, self::META_KEY, true );
        ?>
        <p>
            <label for="rs-interlinker-keywords">
                <?php esc_html_e( 'Custom Keywords (comma-separated):', 'rs-smart-interlinker' ); ?>
            </label>
        </p>
        <textarea
            id="rs-interlinker-keywords"
            name="rs_interlinker_keywords"
            rows="4"
            style="width: 100%;"
            placeholder="<?php esc_attr_e( 'e.g., Marbella, Costa del Sol, Spain', 'rs-smart-interlinker' ); ?>"
        ><?php echo esc_textarea( $keywords ); ?></textarea>
        <p class="description">
            <?php esc_html_e( 'Override auto-extracted keywords. Leave empty to use automatic extraction from title, tags, etc.', 'rs-smart-interlinker' ); ?>
        </p>
        <?php
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

        // Save or delete meta
        if ( isset( $_POST['rs_interlinker_keywords'] ) ) {
            $keywords = sanitize_textarea_field( $_POST['rs_interlinker_keywords'] );

            if ( ! empty( trim( $keywords ) ) ) {
                update_post_meta( $post_id, self::META_KEY, $keywords );
            } else {
                delete_post_meta( $post_id, self::META_KEY );
            }
        }
    }
}
