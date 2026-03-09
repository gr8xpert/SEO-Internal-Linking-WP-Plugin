<?php
/**
 * Settings Class
 *
 * Handles admin settings page with tabs.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SPM_Interlinker_Settings {

    /**
     * Settings page slug
     */
    const PAGE_SLUG = 'spm-interlinker';

    /**
     * Option name
     */
    const OPTION_NAME = 'spm_interlinker_options';

    /**
     * Encryption method
     */
    const ENCRYPTION_METHOD = 'aes-256-cbc';

    /**
     * Indexer instance
     */
    private $indexer;

    /**
     * Processor instance
     */
    private $processor;

    /**
     * Constructor
     */
    public function __construct( $indexer, $processor ) {
        $this->indexer   = $indexer;
        $this->processor = $processor;
    }

    /**
     * Encrypt a value using WordPress AUTH_KEY
     *
     * @param string $value Value to encrypt
     * @return string Encrypted value (base64 encoded)
     */
    private function encrypt_value( $value ) {
        if ( empty( $value ) ) {
            return '';
        }

        if ( ! function_exists( 'openssl_encrypt' ) ) {
            // Fallback if OpenSSL not available - return as-is
            return $value;
        }

        $key = $this->get_encryption_key();
        $iv_length = openssl_cipher_iv_length( self::ENCRYPTION_METHOD );
        $iv = openssl_random_pseudo_bytes( $iv_length );

        $encrypted = openssl_encrypt( $value, self::ENCRYPTION_METHOD, $key, OPENSSL_RAW_DATA, $iv );

        if ( $encrypted === false ) {
            return $value;
        }

        // Prepend IV to encrypted data and base64 encode
        return base64_encode( $iv . $encrypted );
    }

    /**
     * Decrypt a value using WordPress AUTH_KEY
     *
     * @param string $encrypted_value Encrypted value (base64 encoded)
     * @return string Decrypted value
     */
    private function decrypt_value( $encrypted_value ) {
        if ( empty( $encrypted_value ) ) {
            return '';
        }

        if ( ! function_exists( 'openssl_decrypt' ) ) {
            // Fallback if OpenSSL not available - return as-is
            return $encrypted_value;
        }

        // Check if value is base64 encoded (encrypted)
        $decoded = base64_decode( $encrypted_value, true );
        if ( $decoded === false ) {
            // Not encrypted, return as-is (backwards compatibility)
            return $encrypted_value;
        }

        $key = $this->get_encryption_key();
        $iv_length = openssl_cipher_iv_length( self::ENCRYPTION_METHOD );

        // Ensure we have enough data for IV + encrypted content
        if ( strlen( $decoded ) <= $iv_length ) {
            return $encrypted_value;
        }

        $iv = substr( $decoded, 0, $iv_length );
        $encrypted = substr( $decoded, $iv_length );

        $decrypted = openssl_decrypt( $encrypted, self::ENCRYPTION_METHOD, $key, OPENSSL_RAW_DATA, $iv );

        if ( $decrypted === false ) {
            // Decryption failed - might be unencrypted value
            return $encrypted_value;
        }

        return $decrypted;
    }

    /**
     * Get encryption key derived from WordPress AUTH_KEY
     *
     * @return string 32-byte encryption key
     */
    private function get_encryption_key() {
        $auth_key = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'rs-interlinker-default-key';
        return hash( 'sha256', $auth_key, true );
    }

    /**
     * Get decrypted API key
     *
     * @return string Decrypted API key
     */
    public function get_api_key() {
        $options = get_option( self::OPTION_NAME, array() );
        $encrypted_key = isset( $options['api_key'] ) ? $options['api_key'] : '';
        return $this->decrypt_value( $encrypted_key );
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __( 'SPM Interlinker', 'spm-interlinker' ),
            __( 'SPM Interlinker', 'spm-interlinker' ),
            'manage_options',
            self::PAGE_SLUG,
            array( $this, 'render_settings_page' ),
            'dashicons-admin-links',
            80
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting(
            'spm_interlinker_settings',
            self::OPTION_NAME,
            array( $this, 'sanitize_options' )
        );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets( $hook ) {
        if ( strpos( $hook, self::PAGE_SLUG ) === false ) {
            return;
        }

        wp_enqueue_style(
            'rs-interlinker-admin',
            SPM_INTERLINKER_PLUGIN_URL . 'assets/admin.css',
            array(),
            SPM_INTERLINKER_VERSION
        );

        wp_enqueue_script(
            'rs-interlinker-admin',
            SPM_INTERLINKER_PLUGIN_URL . 'assets/admin.js',
            array( 'jquery' ),
            SPM_INTERLINKER_VERSION,
            true
        );

        wp_localize_script( 'rs-interlinker-admin', 'spmInterlinker', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'spm_interlinker_nonce' ),
            'strings' => array(
                'processing'  => __( 'Processing...', 'spm-interlinker' ),
                'processed'   => __( 'Processed', 'spm-interlinker' ),
                'removing'    => __( 'Removing...', 'spm-interlinker' ),
                'removed'     => __( 'Removed', 'spm-interlinker' ),
                'error'       => __( 'Error occurred', 'spm-interlinker' ),
                'confirm_remove' => __( 'Are you sure you want to remove all interlinks from this post?', 'spm-interlinker' ),
                'confirm_process_all' => __( 'Process all unprocessed posts? This may take a while.', 'spm-interlinker' ),
            ),
        ) );
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
        $options    = get_option( self::OPTION_NAME, array() );
        ?>
        <div class="wrap rs-interlinker-wrap">
            <h1><?php esc_html_e( 'SPM Interlinker', 'spm-interlinker' ); ?></h1>

            <nav class="nav-tab-wrapper">
                <a href="?page=<?php echo esc_attr( self::PAGE_SLUG ); ?>&tab=general"
                   class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'General', 'spm-interlinker' ); ?>
                </a>
                <a href="?page=<?php echo esc_attr( self::PAGE_SLUG ); ?>&tab=post-types"
                   class="nav-tab <?php echo $active_tab === 'post-types' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Post Types', 'spm-interlinker' ); ?>
                </a>
                <a href="?page=<?php echo esc_attr( self::PAGE_SLUG ); ?>&tab=keyword-sources"
                   class="nav-tab <?php echo $active_tab === 'keyword-sources' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Keyword Sources', 'spm-interlinker' ); ?>
                </a>
                <a href="?page=<?php echo esc_attr( self::PAGE_SLUG ); ?>&tab=advanced"
                   class="nav-tab <?php echo $active_tab === 'advanced' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Advanced', 'spm-interlinker' ); ?>
                </a>
                <a href="?page=<?php echo esc_attr( self::PAGE_SLUG ); ?>&tab=process"
                   class="nav-tab <?php echo $active_tab === 'process' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Process Posts', 'spm-interlinker' ); ?>
                </a>
            </nav>

            <?php if ( $active_tab === 'process' ) : ?>
                <?php $this->render_process_tab( $options ); ?>
            <?php else : ?>
                <form method="post" action="options.php">
                    <?php settings_fields( 'spm_interlinker_settings' ); ?>
                    <input type="hidden" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[current_tab]" value="<?php echo esc_attr( $active_tab ); ?>">

                    <?php
                    switch ( $active_tab ) {
                        case 'general':
                            $this->render_general_tab( $options );
                            break;
                        case 'post-types':
                            $this->render_post_types_tab( $options );
                            break;
                        case 'keyword-sources':
                            $this->render_keyword_sources_tab( $options );
                            break;
                        case 'advanced':
                            $this->render_advanced_tab( $options );
                            break;
                    }

                    submit_button();
                    ?>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render General tab
     */
    private function render_general_tab( $options ) {
        $stats = $this->indexer->get_stats();
        ?>
        <div class="rs-interlinker-section">
            <h2><?php esc_html_e( 'Status Dashboard', 'spm-interlinker' ); ?></h2>
            <table class="rs-interlinker-stats">
                <tr>
                    <th><?php esc_html_e( 'Total Pages Indexed', 'spm-interlinker' ); ?></th>
                    <td><?php echo esc_html( $stats['total_indexed'] ); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Total Keywords', 'spm-interlinker' ); ?></th>
                    <td><?php echo esc_html( $stats['total_keywords'] ); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Active Post Types', 'spm-interlinker' ); ?></th>
                    <td><?php echo esc_html( implode( ', ', $stats['post_types'] ) ); ?></td>
                </tr>
            </table>
        </div>

        <div class="rs-interlinker-section">
            <h2><?php esc_html_e( 'API Settings', 'spm-interlinker' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="api_key"><?php esc_html_e( 'OpenRouter API Key', 'spm-interlinker' ); ?></label>
                    </th>
                    <td>
                        <input type="password"
                               id="api_key"
                               name="<?php echo esc_attr( self::OPTION_NAME ); ?>[api_key]"
                               value="<?php echo esc_attr( $this->get_api_key() ); ?>"
                               class="regular-text">
                        <button type="button" id="rs-test-api" class="button button-secondary">
                            <?php esc_html_e( 'Test API', 'spm-interlinker' ); ?>
                        </button>
                        <a href="https://openrouter.ai/keys" target="_blank" class="button button-secondary" style="margin-left: 5px;">
                            <?php esc_html_e( 'Get API Key', 'spm-interlinker' ); ?> &#8599;
                        </a>
                        <span id="rs-test-api-status"></span>
                        <p class="description">
                            <?php esc_html_e( 'Required for AI-powered contextual linking.', 'spm-interlinker' ); ?>
                            <a href="https://openrouter.ai/keys" target="_blank"><?php esc_html_e( 'Sign up at OpenRouter.ai', 'spm-interlinker' ); ?></a>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="ai_model"><?php esc_html_e( 'AI Model', 'spm-interlinker' ); ?></label>
                    </th>
                    <td>
                        <?php
                        $current_model = isset( $options['ai_model'] ) ? $options['ai_model'] : 'google/gemini-2.0-flash-001';
                        $models = array(
                            'google/gemini-2.0-flash-001' => 'Gemini 2.0 Flash (Recommended - Very Cheap)',
                            'google/gemini-flash-1.5'     => 'Gemini 1.5 Flash (Very Cheap)',
                            'anthropic/claude-3-haiku'    => 'Claude 3 Haiku (Cheap)',
                            'anthropic/claude-3.5-haiku'  => 'Claude 3.5 Haiku (Moderate)',
                            'openai/gpt-4o-mini'          => 'GPT-4o Mini (Cheap)',
                            'anthropic/claude-sonnet-4'   => 'Claude Sonnet 4 (Expensive)',
                            'anthropic/claude-sonnet-4.5' => 'Claude Sonnet 4.5 (Very Expensive)',
                        );
                        ?>
                        <select id="ai_model" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[ai_model]" class="regular-text">
                            <?php foreach ( $models as $model_id => $model_name ) : ?>
                                <option value="<?php echo esc_attr( $model_id ); ?>" <?php selected( $current_model, $model_id ); ?>>
                                    <?php echo esc_html( $model_name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description" style="color: #d63638; font-weight: bold;">
                            <?php esc_html_e( 'Cost Warning: Gemini Flash costs ~$0.001/post. Claude Sonnet 4.5 costs ~$0.25/post (250x more expensive!)', 'spm-interlinker' ); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="rs-interlinker-section">
            <h2><?php esc_html_e( 'Link Settings', 'spm-interlinker' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="max_internal_links"><?php esc_html_e( 'Max Internal Links Per Page', 'spm-interlinker' ); ?></label>
                    </th>
                    <td>
                        <input type="number"
                               id="max_internal_links"
                               name="<?php echo esc_attr( self::OPTION_NAME ); ?>[max_internal_links]"
                               value="<?php echo esc_attr( isset( $options['max_internal_links'] ) ? $options['max_internal_links'] : 3 ); ?>"
                               min="1"
                               max="20"
                               class="small-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="max_external_links"><?php esc_html_e( 'Max External Links Per Page', 'spm-interlinker' ); ?></label>
                    </th>
                    <td>
                        <input type="number"
                               id="max_external_links"
                               name="<?php echo esc_attr( self::OPTION_NAME ); ?>[max_external_links]"
                               value="<?php echo esc_attr( isset( $options['max_external_links'] ) ? $options['max_external_links'] : 1 ); ?>"
                               min="0"
                               max="5"
                               class="small-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <?php esc_html_e( 'Enable External Linking', 'spm-interlinker' ); ?>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   name="<?php echo esc_attr( self::OPTION_NAME ); ?>[enable_external]"
                                   value="1"
                                   <?php checked( ! empty( $options['enable_external'] ) ); ?>>
                            <?php esc_html_e( 'Allow AI to add authoritative external links', 'spm-interlinker' ); ?>
                        </label>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    /**
     * Render Post Types tab
     */
    private function render_post_types_tab( $options ) {
        $post_types          = get_post_types( array( 'public' => true ), 'objects' );
        $selected_post_types = isset( $options['post_types'] ) ? $options['post_types'] : array();
        ?>
        <div class="rs-interlinker-section">
            <h2><?php esc_html_e( 'Select Post Types', 'spm-interlinker' ); ?></h2>
            <p><?php esc_html_e( 'Select which post types should participate in the interlinking pool.', 'spm-interlinker' ); ?></p>

            <table class="form-table">
                <?php foreach ( $post_types as $post_type ) : ?>
                    <?php if ( $post_type->name === 'attachment' ) continue; ?>
                    <tr>
                        <th scope="row">
                            <?php echo esc_html( $post_type->labels->singular_name ); ?>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="<?php echo esc_attr( self::OPTION_NAME ); ?>[post_types][]"
                                       value="<?php echo esc_attr( $post_type->name ); ?>"
                                       <?php checked( in_array( $post_type->name, $selected_post_types, true ) ); ?>>
                                <code><?php echo esc_html( $post_type->name ); ?></code>
                            </label>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php
    }

    /**
     * Render Keyword Sources tab
     */
    private function render_keyword_sources_tab( $options ) {
        ?>
        <div class="rs-interlinker-section">
            <h2><?php esc_html_e( 'Keyword Extraction Sources', 'spm-interlinker' ); ?></h2>
            <p><?php esc_html_e( 'Configure which sources to use for extracting keywords from posts.', 'spm-interlinker' ); ?></p>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <?php esc_html_e( 'Post Title', 'spm-interlinker' ); ?>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   name="<?php echo esc_attr( self::OPTION_NAME ); ?>[source_title]"
                                   value="1"
                                   <?php checked( ! empty( $options['source_title'] ) ); ?>>
                            <?php esc_html_e( 'Extract keywords from post titles', 'spm-interlinker' ); ?>
                        </label>
                        <br><br>
                        <label for="title_prefix_strip">
                            <?php esc_html_e( 'Prefix to strip:', 'spm-interlinker' ); ?>
                        </label>
                        <input type="text"
                               id="title_prefix_strip"
                               name="<?php echo esc_attr( self::OPTION_NAME ); ?>[title_prefix_strip]"
                               value="<?php echo esc_attr( isset( $options['title_prefix_strip'] ) ? $options['title_prefix_strip'] : '' ); ?>"
                               class="regular-text"
                               placeholder="Properties for sale in ">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Tags', 'spm-interlinker' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   name="<?php echo esc_attr( self::OPTION_NAME ); ?>[source_tags]"
                                   value="1"
                                   <?php checked( ! empty( $options['source_tags'] ) ); ?>>
                            <?php esc_html_e( 'Extract keywords from post tags', 'spm-interlinker' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Categories', 'spm-interlinker' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   name="<?php echo esc_attr( self::OPTION_NAME ); ?>[source_categories]"
                                   value="1"
                                   <?php checked( ! empty( $options['source_categories'] ) ); ?>>
                            <?php esc_html_e( 'Extract keywords from categories', 'spm-interlinker' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Custom Field', 'spm-interlinker' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   name="<?php echo esc_attr( self::OPTION_NAME ); ?>[source_custom_field]"
                                   value="1"
                                   <?php checked( ! empty( $options['source_custom_field'] ) ); ?>>
                            <?php esc_html_e( 'Extract keywords from a custom field', 'spm-interlinker' ); ?>
                        </label>
                        <br><br>
                        <label for="custom_field_key">
                            <?php esc_html_e( 'Custom field key:', 'spm-interlinker' ); ?>
                        </label>
                        <input type="text"
                               id="custom_field_key"
                               name="<?php echo esc_attr( self::OPTION_NAME ); ?>[custom_field_key]"
                               value="<?php echo esc_attr( isset( $options['custom_field_key'] ) ? $options['custom_field_key'] : '' ); ?>"
                               class="regular-text">
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    /**
     * Render Advanced tab
     */
    private function render_advanced_tab( $options ) {
        ?>
        <div class="rs-interlinker-section">
            <h2><?php esc_html_e( 'Link Attributes', 'spm-interlinker' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="external_link_rel"><?php esc_html_e( 'External Link Rel', 'spm-interlinker' ); ?></label>
                    </th>
                    <td>
                        <select id="external_link_rel" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[external_link_rel]">
                            <option value="dofollow" <?php selected( isset( $options['external_link_rel'] ) ? $options['external_link_rel'] : '', 'dofollow' ); ?>>
                                <?php esc_html_e( 'Dofollow', 'spm-interlinker' ); ?>
                            </option>
                            <option value="nofollow" <?php selected( isset( $options['external_link_rel'] ) ? $options['external_link_rel'] : '', 'nofollow' ); ?>>
                                <?php esc_html_e( 'Nofollow', 'spm-interlinker' ); ?>
                            </option>
                        </select>
                    </td>
                </tr>
            </table>
        </div>

        <div class="rs-interlinker-section">
            <h2><?php esc_html_e( 'Maintenance', 'spm-interlinker' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Rebuild Index', 'spm-interlinker' ); ?></th>
                    <td>
                        <button type="button" id="rs-rebuild-index" class="button button-secondary">
                            <?php esc_html_e( 'Rebuild Keyword Index', 'spm-interlinker' ); ?>
                        </button>
                        <span id="rs-rebuild-index-status"></span>
                        <p class="description">
                            <?php esc_html_e( 'Re-scans all posts and rebuilds the keyword-to-URL map.', 'spm-interlinker' ); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    /**
     * Render Process Posts tab
     */
    private function render_process_tab( $options ) {
        $posts_status = $this->processor->get_posts_status();
        $processed_count = 0;
        $unprocessed_count = 0;

        foreach ( $posts_status as $post ) {
            if ( $post['processed'] ) {
                $processed_count++;
            } else {
                $unprocessed_count++;
            }
        }
        ?>
        <div class="rs-interlinker-section">
            <h2><?php esc_html_e( 'Process Posts', 'spm-interlinker' ); ?></h2>
            <p><?php esc_html_e( 'Process posts to add internal links directly to their content. Links are saved to the database.', 'spm-interlinker' ); ?></p>

            <div class="rs-process-stats">
                <span class="stat">
                    <strong><?php echo esc_html( $processed_count ); ?></strong>
                    <?php esc_html_e( 'Processed', 'spm-interlinker' ); ?>
                </span>
                <span class="stat">
                    <strong><?php echo esc_html( $unprocessed_count ); ?></strong>
                    <?php esc_html_e( 'Not Processed', 'spm-interlinker' ); ?>
                </span>
                <span class="stat">
                    <strong><?php echo esc_html( count( $posts_status ) ); ?></strong>
                    <?php esc_html_e( 'Total', 'spm-interlinker' ); ?>
                </span>
            </div>

            <!-- Batch Size Setting -->
            <div style="margin-bottom: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
                <label for="rs-batch-size" style="font-weight: bold; display: block; margin-bottom: 8px;">
                    <?php esc_html_e( 'Posts per Cron Run:', 'spm-interlinker' ); ?>
                </label>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <input type="number"
                           id="rs-batch-size"
                           min="1"
                           max="20"
                           value="<?php echo esc_attr( isset( $options['batch_size'] ) ? $options['batch_size'] : 2 ); ?>"
                           style="width: 80px;">
                    <button type="button" id="rs-save-batch-size" class="button button-secondary">
                        <?php esc_html_e( 'Save', 'spm-interlinker' ); ?>
                    </button>
                    <span id="rs-batch-size-status"></span>
                </div>
                <p class="description" style="margin-top: 8px;">
                    <?php esc_html_e( 'Number of posts to process every 2 minutes. Higher = faster but uses more server resources. (1-20)', 'spm-interlinker' ); ?>
                </p>
            </div>

            <?php if ( $unprocessed_count > 0 ) : ?>
                <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                    <button type="button" id="rs-start-queue" class="button button-primary">
                        <?php esc_html_e( 'Start Background Processing', 'spm-interlinker' ); ?>
                    </button>
                    <button type="button" id="rs-stop-queue" class="button button-secondary" style="display:none;">
                        <?php esc_html_e( 'Stop Processing', 'spm-interlinker' ); ?>
                    </button>
                    <button type="button" id="rs-process-all" class="button">
                        <?php esc_html_e( 'Process All (Browser)', 'spm-interlinker' ); ?>
                    </button>
                    <span id="rs-process-all-status"></span>
                </div>
                <p class="description" style="margin-top: 10px;">
                    <?php esc_html_e( 'Background Processing: Runs via WP Cron every 2 minutes. Safe for large sites.', 'spm-interlinker' ); ?>
                </p>
            <?php endif; ?>

            <!-- Queue Status Box -->
            <div id="rs-queue-status-box" style="margin-top: 20px; padding: 15px; background: #f0f6fc; border: 1px solid #c3d5e8; border-radius: 4px; display: none;">
                <h3 style="margin: 0 0 10px 0;">
                    <span class="dashicons dashicons-update" style="animation: rs-spin 2s linear infinite;"></span>
                    <?php esc_html_e( 'Background Processing Status', 'spm-interlinker' ); ?>
                </h3>
                <div style="display: flex; gap: 30px; flex-wrap: wrap;">
                    <div>
                        <strong><?php esc_html_e( 'Progress:', 'spm-interlinker' ); ?></strong>
                        <span id="rs-queue-processed">0</span> / <span id="rs-queue-total">0</span>
                        (<span id="rs-queue-percent">0</span>%)
                    </div>
                    <div>
                        <strong><?php esc_html_e( 'Remaining:', 'spm-interlinker' ); ?></strong>
                        <span id="rs-queue-remaining">0</span>
                    </div>
                    <div>
                        <strong><?php esc_html_e( 'Errors:', 'spm-interlinker' ); ?></strong>
                        <span id="rs-queue-errors">0</span>
                    </div>
                    <div>
                        <strong><?php esc_html_e( 'Last Run:', 'spm-interlinker' ); ?></strong>
                        <span id="rs-queue-lastrun">-</span>
                    </div>
                </div>
                <div style="margin-top: 10px;">
                    <div style="background: #ddd; border-radius: 3px; height: 20px; overflow: hidden;">
                        <div id="rs-queue-progress-bar" style="background: #2271b1; height: 100%; width: 0%; transition: width 0.5s;"></div>
                    </div>
                </div>
                <p id="rs-queue-eta" style="margin: 10px 0 0 0; color: #666;"></p>
            </div>

            <!-- Completed Status Box -->
            <div id="rs-queue-completed-box" style="margin-top: 20px; padding: 15px; background: #d4edda; border: 1px solid #28a745; border-radius: 4px; display: none;">
                <h3 style="margin: 0; color: #155724;">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php esc_html_e( 'Background Processing Completed!', 'spm-interlinker' ); ?>
                </h3>
                <p style="margin: 10px 0 0 0;">
                    <?php esc_html_e( 'Processed:', 'spm-interlinker' ); ?> <strong id="rs-queue-final-processed">0</strong>
                    | <?php esc_html_e( 'Errors:', 'spm-interlinker' ); ?> <strong id="rs-queue-final-errors">0</strong>
                </p>
            </div>
        </div>

        <div class="rs-interlinker-section">
            <h2><?php esc_html_e( 'Posts List', 'spm-interlinker' ); ?></h2>

            <?php if ( empty( $posts_status ) ) : ?>
                <p><?php esc_html_e( 'No posts found. Make sure you have selected post types in the Post Types tab.', 'spm-interlinker' ); ?></p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped" id="rs-posts-table">
                    <thead>
                        <tr>
                            <th class="column-title"><?php esc_html_e( 'Title', 'spm-interlinker' ); ?></th>
                            <th class="column-type"><?php esc_html_e( 'Type', 'spm-interlinker' ); ?></th>
                            <th class="column-status"><?php esc_html_e( 'Status', 'spm-interlinker' ); ?></th>
                            <th class="column-links"><?php esc_html_e( 'Links', 'spm-interlinker' ); ?></th>
                            <th class="column-actions"><?php esc_html_e( 'Actions', 'spm-interlinker' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $posts_status as $post ) : ?>
                            <tr data-post-id="<?php echo esc_attr( $post['id'] ); ?>">
                                <td class="column-title">
                                    <strong>
                                        <a href="<?php echo esc_url( $post['edit_url'] ); ?>" target="_blank">
                                            <?php echo esc_html( $post['title'] ); ?>
                                        </a>
                                    </strong>
                                    <div class="row-actions">
                                        <a href="<?php echo esc_url( $post['view_url'] ); ?>" target="_blank">
                                            <?php esc_html_e( 'View', 'spm-interlinker' ); ?>
                                        </a>
                                    </div>
                                </td>
                                <td class="column-type">
                                    <code><?php echo esc_html( $post['post_type'] ); ?></code>
                                </td>
                                <td class="column-status">
                                    <?php if ( $post['processed'] ) : ?>
                                        <span class="rs-status rs-status-processed">
                                            <?php esc_html_e( 'Processed', 'spm-interlinker' ); ?>
                                        </span>
                                    <?php else : ?>
                                        <span class="rs-status rs-status-pending">
                                            <?php esc_html_e( 'Not Processed', 'spm-interlinker' ); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="column-links">
                                    <?php if ( $post['processed'] ) : ?>
                                        <?php echo esc_html( $post['links_count'] ); ?>
                                    <?php else : ?>
                                        &mdash;
                                    <?php endif; ?>
                                </td>
                                <td class="column-actions">
                                    <?php if ( $post['processed'] ) : ?>
                                        <button type="button"
                                                class="button button-small rs-remove-links"
                                                data-post-id="<?php echo esc_attr( $post['id'] ); ?>">
                                            <?php esc_html_e( 'Remove Links', 'spm-interlinker' ); ?>
                                        </button>
                                    <?php else : ?>
                                        <button type="button"
                                                class="button button-small button-primary rs-process-post"
                                                data-post-id="<?php echo esc_attr( $post['id'] ); ?>">
                                            <?php esc_html_e( 'Process', 'spm-interlinker' ); ?>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Sanitize options
     */
    public function sanitize_options( $input ) {
        $sanitized = get_option( self::OPTION_NAME, array() );
        $current_tab = isset( $input['current_tab'] ) ? sanitize_key( $input['current_tab'] ) : 'general';

        switch ( $current_tab ) {
            case 'general':
                if ( isset( $input['api_key'] ) ) {
                    $api_key = sanitize_text_field( $input['api_key'] );
                    // Encrypt API key before storing
                    $sanitized['api_key'] = $this->encrypt_value( $api_key );
                }
                if ( isset( $input['ai_model'] ) ) {
                    $sanitized['ai_model'] = sanitize_text_field( $input['ai_model'] );
                }
                $sanitized['max_internal_links'] = isset( $input['max_internal_links'] )
                    ? absint( $input['max_internal_links'] ) : 3;
                $sanitized['max_external_links'] = isset( $input['max_external_links'] )
                    ? absint( $input['max_external_links'] ) : 1;
                $sanitized['enable_external'] = ! empty( $input['enable_external'] ) ? 1 : 0;
                break;

            case 'post-types':
                $sanitized['post_types'] = isset( $input['post_types'] ) && is_array( $input['post_types'] )
                    ? array_map( 'sanitize_key', $input['post_types'] ) : array();
                break;

            case 'keyword-sources':
                $sanitized['source_title']        = ! empty( $input['source_title'] ) ? 1 : 0;
                $sanitized['source_tags']         = ! empty( $input['source_tags'] ) ? 1 : 0;
                $sanitized['source_categories']   = ! empty( $input['source_categories'] ) ? 1 : 0;
                $sanitized['source_custom_field'] = ! empty( $input['source_custom_field'] ) ? 1 : 0;
                $sanitized['title_prefix_strip']  = isset( $input['title_prefix_strip'] )
                    ? sanitize_text_field( $input['title_prefix_strip'] ) : '';
                $sanitized['custom_field_key']    = isset( $input['custom_field_key'] )
                    ? sanitize_key( $input['custom_field_key'] ) : '';
                break;

            case 'advanced':
                $sanitized['external_link_rel'] = isset( $input['external_link_rel'] ) && $input['external_link_rel'] === 'nofollow'
                    ? 'nofollow' : 'dofollow';
                break;
        }

        return $sanitized;
    }

    /**
     * AJAX: Clear cache
     */
    public function clear_cache() {
        check_ajax_referer( 'spm_interlinker_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied', 'spm-interlinker' ) );
        }
        // No cache to clear in v2
        wp_send_json_success( array( 'message' => __( 'Done.', 'spm-interlinker' ) ) );
    }

    /**
     * AJAX: Rebuild index
     */
    public function rebuild_index() {
        check_ajax_referer( 'spm_interlinker_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied', 'spm-interlinker' ) );
        }
        $count = $this->indexer->rebuild_index();
        wp_send_json_success( array(
            'message' => sprintf( __( 'Index rebuilt. %d posts indexed.', 'spm-interlinker' ), $count ),
        ) );
    }

    /**
     * AJAX: Test API
     */
    public function test_api() {
        check_ajax_referer( 'spm_interlinker_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied', 'spm-interlinker' ) );
        }

        $options = get_option( self::OPTION_NAME, array() );
        $api_key = $this->get_api_key();
        $model   = isset( $options['ai_model'] ) ? $options['ai_model'] : 'google/gemini-2.0-flash-001';

        if ( empty( $api_key ) ) {
            wp_send_json_error( __( 'API key is not configured.', 'spm-interlinker' ) );
        }

        $response = wp_remote_post( 'https://openrouter.ai/api/v1/chat/completions', array(
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ),
            'body' => wp_json_encode( array(
                'model'      => $model,
                'messages'   => array( array( 'role' => 'user', 'content' => 'Say OK' ) ),
                'max_tokens' => 10,
            ) ),
        ) );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            $msg  = isset( $body['error']['message'] ) ? $body['error']['message'] : "HTTP $code";
            wp_send_json_error( $msg );
        }

        wp_send_json_success( array( 'message' => __( 'API connection successful!', 'spm-interlinker' ) ) );
    }

    /**
     * AJAX: Save batch size
     */
    public function save_batch_size() {
        check_ajax_referer( 'spm_interlinker_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied', 'spm-interlinker' ) );
        }

        $batch_size = isset( $_POST['batch_size'] ) ? absint( $_POST['batch_size'] ) : 2;
        $batch_size = max( 1, min( 20, $batch_size ) ); // Clamp between 1-20

        $options = get_option( self::OPTION_NAME, array() );
        $options['batch_size'] = $batch_size;
        update_option( self::OPTION_NAME, $options );

        wp_send_json_success( array(
            'message'    => sprintf( __( 'Batch size saved: %d posts per cron run.', 'spm-interlinker' ), $batch_size ),
            'batch_size' => $batch_size,
        ) );
    }
}
