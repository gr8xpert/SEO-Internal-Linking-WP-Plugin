<?php
/**
 * Plugin Name: RS Smart Interlinker
 * Plugin URI: https://example.com/rs-smart-interlinker
 * Description: Automatically interlinks content across post types using AI-powered contextual linking via Claude AI. Processes posts and saves links directly to database.
 * Version: 2.0.0
 * Author: RS Development
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: rs-smart-interlinker
 * Domain Path: /languages
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants
define( 'RS_INTERLINKER_VERSION', '2.0.0' );
define( 'RS_INTERLINKER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RS_INTERLINKER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'RS_INTERLINKER_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin class
 */
class RS_Smart_Interlinker {

    /**
     * Single instance of the class
     */
    private static $instance = null;

    /**
     * Plugin components
     */
    public $settings;
    public $indexer;
    public $processor;
    public $ai_engine;
    public $url_validator;
    public $meta_box;
    public $queue;

    /**
     * Get single instance of the class
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_components();
        $this->register_hooks();
    }

    /**
     * Load required files
     */
    private function load_dependencies() {
        require_once RS_INTERLINKER_PLUGIN_DIR . 'includes/class-settings.php';
        require_once RS_INTERLINKER_PLUGIN_DIR . 'includes/class-indexer.php';
        require_once RS_INTERLINKER_PLUGIN_DIR . 'includes/class-ai-engine.php';
        require_once RS_INTERLINKER_PLUGIN_DIR . 'includes/class-url-validator.php';
        require_once RS_INTERLINKER_PLUGIN_DIR . 'includes/class-meta-box.php';
        require_once RS_INTERLINKER_PLUGIN_DIR . 'includes/class-processor.php';
        require_once RS_INTERLINKER_PLUGIN_DIR . 'includes/class-queue.php';
    }

    /**
     * Initialize plugin components
     */
    private function init_components() {
        $this->url_validator = new RS_Interlinker_URL_Validator();
        $this->indexer       = new RS_Interlinker_Indexer();
        $this->ai_engine     = new RS_Interlinker_AI_Engine( $this->url_validator );
        $this->processor     = new RS_Interlinker_Processor( $this->indexer, $this->ai_engine );
        $this->settings      = new RS_Interlinker_Settings( $this->indexer, $this->processor );
        $this->meta_box      = new RS_Interlinker_Meta_Box();
        $this->queue         = new RS_Interlinker_Queue( $this->processor );

        // Register cron interval
        add_filter( 'cron_schedules', array( 'RS_Interlinker_Queue', 'register_cron_interval' ) );
    }

    /**
     * Register WordPress hooks
     */
    private function register_hooks() {
        // Activation/Deactivation
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

        // Admin hooks
        add_action( 'admin_menu', array( $this->settings, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this->settings, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this->settings, 'enqueue_admin_assets' ) );

        // Meta box hooks
        add_action( 'add_meta_boxes', array( $this->meta_box, 'add_meta_box' ) );
        add_action( 'save_post', array( $this->meta_box, 'save_meta_box' ) );

        // Index rebuild hooks
        add_action( 'save_post', array( $this->indexer, 'update_index_on_save' ), 20, 2 );
        add_action( 'delete_post', array( $this->indexer, 'remove_from_index' ) );

        // AJAX handlers
        add_action( 'wp_ajax_rs_interlinker_clear_cache', array( $this->settings, 'clear_cache' ) );
        add_action( 'wp_ajax_rs_interlinker_rebuild_index', array( $this->settings, 'rebuild_index' ) );
        add_action( 'wp_ajax_rs_interlinker_test_api', array( $this->settings, 'test_api' ) );
        add_action( 'wp_ajax_rs_interlinker_process_post', array( $this->processor, 'ajax_process_post' ) );
        add_action( 'wp_ajax_rs_interlinker_remove_links', array( $this->processor, 'ajax_remove_links' ) );
        add_action( 'wp_ajax_rs_interlinker_get_posts_status', array( $this->processor, 'ajax_get_posts_status' ) );
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Set default options
        $default_options = array(
            'api_key'              => '',
            'ai_model'             => 'anthropic/claude-sonnet-4.5',
            'max_internal_links'   => 3,
            'max_external_links'   => 1,
            'enable_external'      => 1,
            'post_types'           => array( 'post', 'page' ),
            'source_title'         => 1,
            'source_tags'          => 0,
            'source_categories'    => 0,
            'source_custom_field'  => 0,
            'title_prefix_strip'   => '',
            'custom_field_key'     => '',
            'external_link_rel'    => 'dofollow',
        );

        // Only set defaults if options don't exist
        if ( false === get_option( 'rs_interlinker_options' ) ) {
            add_option( 'rs_interlinker_options', $default_options );
        }

        // Build initial index
        $this->load_dependencies();
        $indexer = new RS_Interlinker_Indexer();
        $indexer->rebuild_index();

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled queue processing
        wp_clear_scheduled_hook( RS_Interlinker_Queue::CRON_HOOK );

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Get plugin option
     */
    public static function get_option( $key, $default = null ) {
        $options = get_option( 'rs_interlinker_options', array() );
        return isset( $options[ $key ] ) ? $options[ $key ] : $default;
    }
}

/**
 * Initialize the plugin
 */
function rs_smart_interlinker() {
    return RS_Smart_Interlinker::get_instance();
}

// Start the plugin
add_action( 'plugins_loaded', 'rs_smart_interlinker' );

/**
 * Uninstall hook (called from uninstall.php)
 */
function rs_interlinker_uninstall() {
    global $wpdb;

    // Delete plugin options
    delete_option( 'rs_interlinker_options' );
    delete_option( 'rs_interlinker_index' );

    // Delete all post meta
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
            $wpdb->esc_like( '_rs_interlinker_' ) . '%'
        )
    );
}
