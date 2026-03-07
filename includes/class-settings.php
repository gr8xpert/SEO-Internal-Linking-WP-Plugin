<?php
/**
 * Settings Class
 *
 * Handles admin settings page with tabs.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RS_Interlinker_Settings {

    /**
     * Settings page slug
     */
    const PAGE_SLUG = 'rs-smart-interlinker';

    /**
     * Option name
     */
    const OPTION_NAME = 'rs_interlinker_options';

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
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __( 'RS Smart Interlinker', 'rs-smart-interlinker' ),
            __( 'RS Interlinker', 'rs-smart-interlinker' ),
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
            'rs_interlinker_settings',
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
            RS_INTERLINKER_PLUGIN_URL . 'assets/admin.css',
            array(),
            RS_INTERLINKER_VERSION
        );

        wp_enqueue_script(
            'rs-interlinker-admin',
            RS_INTERLINKER_PLUGIN_URL . 'assets/admin.js',
            array( 'jquery' ),
            RS_INTERLINKER_VERSION,
            true
        );

        wp_localize_script( 'rs-interlinker-admin', 'rsInterlinker', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'rs_interlinker_nonce' ),
            'strings' => array(
                'processing'  => __( 'Processing...', 'rs-smart-interlinker' ),
                'processed'   => __( 'Processed', 'rs-smart-interlinker' ),
                'removing'    => __( 'Removing...', 'rs-smart-interlinker' ),
                'removed'     => __( 'Removed', 'rs-smart-interlinker' ),
                'error'       => __( 'Error occurred', 'rs-smart-interlinker' ),
                'confirm_remove' => __( 'Are you sure you want to remove all interlinks from this post?', 'rs-smart-interlinker' ),
                'confirm_process_all' => __( 'Process all unprocessed posts? This may take a while.', 'rs-smart-interlinker' ),
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
            <h1><?php esc_html_e( 'RS Smart Interlinker', 'rs-smart-interlinker' ); ?></h1>

            <nav class="nav-tab-wrapper">
                <a href="?page=<?php echo esc_attr( self::PAGE_SLUG ); ?>&tab=general"
                   class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'General', 'rs-smart-interlinker' ); ?>
                </a>
                <a href="?page=<?php echo esc_attr( self::PAGE_SLUG ); ?>&tab=post-types"
                   class="nav-tab <?php echo $active_tab === 'post-types' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Post Types', 'rs-smart-interlinker' ); ?>
                </a>
                <a href="?page=<?php echo esc_attr( self::PAGE_SLUG ); ?>&tab=keyword-sources"
                   class="nav-tab <?php echo $active_tab === 'keyword-sources' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Keyword Sources', 'rs-smart-interlinker' ); ?>
                </a>
                <a href="?page=<?php echo esc_attr( self::PAGE_SLUG ); ?>&tab=advanced"
                   class="nav-tab <?php echo $active_tab === 'advanced' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Advanced', 'rs-smart-interlinker' ); ?>
                </a>
                <a href="?page=<?php echo esc_attr( self::PAGE_SLUG ); ?>&tab=process"
                   class="nav-tab <?php echo $active_tab === 'process' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Process Posts', 'rs-smart-interlinker' ); ?>
                </a>
            </nav>

            <?php if ( $active_tab === 'process' ) : ?>
                <?php $this->render_process_tab( $options ); ?>
            <?php else : ?>
                <form method="post" action="options.php">
                    <?php settings_fields( 'rs_interlinker_settings' ); ?>
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
            <h2><?php esc_html_e( 'Status Dashboard', 'rs-smart-interlinker' ); ?></h2>
            <table class="rs-interlinker-stats">
                <tr>
                    <th><?php esc_html_e( 'Total Pages Indexed', 'rs-smart-interlinker' ); ?></th>
                    <td><?php echo esc_html( $stats['total_indexed'] ); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Total Keywords', 'rs-smart-interlinker' ); ?></th>
                    <td><?php echo esc_html( $stats['total_keywords'] ); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Active Post Types', 'rs-smart-interlinker' ); ?></th>
                    <td><?php echo esc_html( implode( ', ', $stats['post_types'] ) ); ?></td>
                </tr>
            </table>
        </div>

        <div class="rs-interlinker-section">
            <h2><?php esc_html_e( 'API Settings', 'rs-smart-interlinker' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="api_key"><?php esc_html_e( 'OpenRouter API Key', 'rs-smart-interlinker' ); ?></label>
                    </th>
                    <td>
                        <input type="password"
                               id="api_key"
                               name="<?php echo esc_attr( self::OPTION_NAME ); ?>[api_key]"
                               value="<?php echo esc_attr( isset( $options['api_key'] ) ? $options['api_key'] : '' ); ?>"
                               class="regular-text">
                        <button type="button" id="rs-test-api" class="button button-secondary">
                            <?php esc_html_e( 'Test API', 'rs-smart-interlinker' ); ?>
                        </button>
                        <span id="rs-test-api-status"></span>
                        <p class="description">
                            <?php esc_html_e( 'Required for AI-powered contextual linking.', 'rs-smart-interlinker' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="ai_model"><?php esc_html_e( 'AI Model', 'rs-smart-interlinker' ); ?></label>
                    </th>
                    <td>
                        <input type="text"
                               id="ai_model"
                               name="<?php echo esc_attr( self::OPTION_NAME ); ?>[ai_model]"
                               value="<?php echo esc_attr( isset( $options['ai_model'] ) ? $options['ai_model'] : 'anthropic/claude-sonnet-4.5' ); ?>"
                               class="regular-text">
                        <p class="description">
                            <?php esc_html_e( 'OpenRouter model string (e.g., anthropic/claude-sonnet-4.5)', 'rs-smart-interlinker' ); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="rs-interlinker-section">
            <h2><?php esc_html_e( 'Link Settings', 'rs-smart-interlinker' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="max_internal_links"><?php esc_html_e( 'Max Internal Links Per Page', 'rs-smart-interlinker' ); ?></label>
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
                        <label for="max_external_links"><?php esc_html_e( 'Max External Links Per Page', 'rs-smart-interlinker' ); ?></label>
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
                        <?php esc_html_e( 'Enable External Linking', 'rs-smart-interlinker' ); ?>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   name="<?php echo esc_attr( self::OPTION_NAME ); ?>[enable_external]"
                                   value="1"
                                   <?php checked( ! empty( $options['enable_external'] ) ); ?>>
                            <?php esc_html_e( 'Allow AI to add authoritative external links', 'rs-smart-interlinker' ); ?>
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
            <h2><?php esc_html_e( 'Select Post Types', 'rs-smart-interlinker' ); ?></h2>
            <p><?php esc_html_e( 'Select which post types should participate in the interlinking pool.', 'rs-smart-interlinker' ); ?></p>

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
            <h2><?php esc_html_e( 'Keyword Extraction Sources', 'rs-smart-interlinker' ); ?></h2>
            <p><?php esc_html_e( 'Configure which sources to use for extracting keywords from posts.', 'rs-smart-interlinker' ); ?></p>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <?php esc_html_e( 'Post Title', 'rs-smart-interlinker' ); ?>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   name="<?php echo esc_attr( self::OPTION_NAME ); ?>[source_title]"
                                   value="1"
                                   <?php checked( ! empty( $options['source_title'] ) ); ?>>
                            <?php esc_html_e( 'Extract keywords from post titles', 'rs-smart-interlinker' ); ?>
                        </label>
                        <br><br>
                        <label for="title_prefix_strip">
                            <?php esc_html_e( 'Prefix to strip:', 'rs-smart-interlinker' ); ?>
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
                    <th scope="row"><?php esc_html_e( 'Tags', 'rs-smart-interlinker' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   name="<?php echo esc_attr( self::OPTION_NAME ); ?>[source_tags]"
                                   value="1"
                                   <?php checked( ! empty( $options['source_tags'] ) ); ?>>
                            <?php esc_html_e( 'Extract keywords from post tags', 'rs-smart-interlinker' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Categories', 'rs-smart-interlinker' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   name="<?php echo esc_attr( self::OPTION_NAME ); ?>[source_categories]"
                                   value="1"
                                   <?php checked( ! empty( $options['source_categories'] ) ); ?>>
                            <?php esc_html_e( 'Extract keywords from categories', 'rs-smart-interlinker' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Custom Field', 'rs-smart-interlinker' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   name="<?php echo esc_attr( self::OPTION_NAME ); ?>[source_custom_field]"
                                   value="1"
                                   <?php checked( ! empty( $options['source_custom_field'] ) ); ?>>
                            <?php esc_html_e( 'Extract keywords from a custom field', 'rs-smart-interlinker' ); ?>
                        </label>
                        <br><br>
                        <label for="custom_field_key">
                            <?php esc_html_e( 'Custom field key:', 'rs-smart-interlinker' ); ?>
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
            <h2><?php esc_html_e( 'Link Attributes', 'rs-smart-interlinker' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="external_link_rel"><?php esc_html_e( 'External Link Rel', 'rs-smart-interlinker' ); ?></label>
                    </th>
                    <td>
                        <select id="external_link_rel" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[external_link_rel]">
                            <option value="dofollow" <?php selected( isset( $options['external_link_rel'] ) ? $options['external_link_rel'] : '', 'dofollow' ); ?>>
                                <?php esc_html_e( 'Dofollow', 'rs-smart-interlinker' ); ?>
                            </option>
                            <option value="nofollow" <?php selected( isset( $options['external_link_rel'] ) ? $options['external_link_rel'] : '', 'nofollow' ); ?>>
                                <?php esc_html_e( 'Nofollow', 'rs-smart-interlinker' ); ?>
                            </option>
                        </select>
                    </td>
                </tr>
            </table>
        </div>

        <div class="rs-interlinker-section">
            <h2><?php esc_html_e( 'Maintenance', 'rs-smart-interlinker' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Rebuild Index', 'rs-smart-interlinker' ); ?></th>
                    <td>
                        <button type="button" id="rs-rebuild-index" class="button button-secondary">
                            <?php esc_html_e( 'Rebuild Keyword Index', 'rs-smart-interlinker' ); ?>
                        </button>
                        <span id="rs-rebuild-index-status"></span>
                        <p class="description">
                            <?php esc_html_e( 'Re-scans all posts and rebuilds the keyword-to-URL map.', 'rs-smart-interlinker' ); ?>
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
            <h2><?php esc_html_e( 'Process Posts', 'rs-smart-interlinker' ); ?></h2>
            <p><?php esc_html_e( 'Process posts to add internal links directly to their content. Links are saved to the database.', 'rs-smart-interlinker' ); ?></p>

            <div class="rs-process-stats">
                <span class="stat">
                    <strong><?php echo esc_html( $processed_count ); ?></strong>
                    <?php esc_html_e( 'Processed', 'rs-smart-interlinker' ); ?>
                </span>
                <span class="stat">
                    <strong><?php echo esc_html( $unprocessed_count ); ?></strong>
                    <?php esc_html_e( 'Not Processed', 'rs-smart-interlinker' ); ?>
                </span>
                <span class="stat">
                    <strong><?php echo esc_html( count( $posts_status ) ); ?></strong>
                    <?php esc_html_e( 'Total', 'rs-smart-interlinker' ); ?>
                </span>
            </div>

            <?php if ( $unprocessed_count > 0 ) : ?>
                <p>
                    <button type="button" id="rs-process-all" class="button button-primary">
                        <?php esc_html_e( 'Process All Unprocessed Posts', 'rs-smart-interlinker' ); ?>
                    </button>
                    <span id="rs-process-all-status"></span>
                </p>
            <?php endif; ?>
        </div>

        <div class="rs-interlinker-section">
            <h2><?php esc_html_e( 'Posts List', 'rs-smart-interlinker' ); ?></h2>

            <?php if ( empty( $posts_status ) ) : ?>
                <p><?php esc_html_e( 'No posts found. Make sure you have selected post types in the Post Types tab.', 'rs-smart-interlinker' ); ?></p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped" id="rs-posts-table">
                    <thead>
                        <tr>
                            <th class="column-title"><?php esc_html_e( 'Title', 'rs-smart-interlinker' ); ?></th>
                            <th class="column-type"><?php esc_html_e( 'Type', 'rs-smart-interlinker' ); ?></th>
                            <th class="column-status"><?php esc_html_e( 'Status', 'rs-smart-interlinker' ); ?></th>
                            <th class="column-links"><?php esc_html_e( 'Links', 'rs-smart-interlinker' ); ?></th>
                            <th class="column-actions"><?php esc_html_e( 'Actions', 'rs-smart-interlinker' ); ?></th>
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
                                            <?php esc_html_e( 'View', 'rs-smart-interlinker' ); ?>
                                        </a>
                                    </div>
                                </td>
                                <td class="column-type">
                                    <code><?php echo esc_html( $post['post_type'] ); ?></code>
                                </td>
                                <td class="column-status">
                                    <?php if ( $post['processed'] ) : ?>
                                        <span class="rs-status rs-status-processed">
                                            <?php esc_html_e( 'Processed', 'rs-smart-interlinker' ); ?>
                                        </span>
                                    <?php else : ?>
                                        <span class="rs-status rs-status-pending">
                                            <?php esc_html_e( 'Not Processed', 'rs-smart-interlinker' ); ?>
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
                                            <?php esc_html_e( 'Remove Links', 'rs-smart-interlinker' ); ?>
                                        </button>
                                    <?php else : ?>
                                        <button type="button"
                                                class="button button-small button-primary rs-process-post"
                                                data-post-id="<?php echo esc_attr( $post['id'] ); ?>">
                                            <?php esc_html_e( 'Process', 'rs-smart-interlinker' ); ?>
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
                    $sanitized['api_key'] = sanitize_text_field( $input['api_key'] );
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
        check_ajax_referer( 'rs_interlinker_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied', 'rs-smart-interlinker' ) );
        }
        // No cache to clear in v2
        wp_send_json_success( array( 'message' => __( 'Done.', 'rs-smart-interlinker' ) ) );
    }

    /**
     * AJAX: Rebuild index
     */
    public function rebuild_index() {
        check_ajax_referer( 'rs_interlinker_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied', 'rs-smart-interlinker' ) );
        }
        $count = $this->indexer->rebuild_index();
        wp_send_json_success( array(
            'message' => sprintf( __( 'Index rebuilt. %d posts indexed.', 'rs-smart-interlinker' ), $count ),
        ) );
    }

    /**
     * AJAX: Test API
     */
    public function test_api() {
        check_ajax_referer( 'rs_interlinker_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied', 'rs-smart-interlinker' ) );
        }

        $options = get_option( self::OPTION_NAME, array() );
        $api_key = isset( $options['api_key'] ) ? $options['api_key'] : '';
        $model   = isset( $options['ai_model'] ) ? $options['ai_model'] : 'anthropic/claude-sonnet-4.5';

        if ( empty( $api_key ) ) {
            wp_send_json_error( __( 'API key is not configured.', 'rs-smart-interlinker' ) );
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

        wp_send_json_success( array( 'message' => __( 'API connection successful!', 'rs-smart-interlinker' ) ) );
    }
}
