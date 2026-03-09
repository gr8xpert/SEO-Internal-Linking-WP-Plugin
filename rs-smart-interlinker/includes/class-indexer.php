<?php
/**
 * Keyword Indexer Class
 *
 * Handles keyword extraction and index building for the interlinker.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SPM_Interlinker_Indexer {

    /**
     * Option name for storing the index
     */
    const INDEX_OPTION = 'spm_interlinker_index';

    /**
     * Meta key for custom keywords
     */
    const META_KEY = '_spm_interlinker_keywords';

    /**
     * Get the keyword index
     *
     * @return array Keyword index array
     */
    public function get_index() {
        return get_option( self::INDEX_OPTION, array() );
    }

    /**
     * Rebuild the entire keyword index
     *
     * @return int Number of posts indexed
     */
    public function rebuild_index() {
        $options    = get_option( 'spm_interlinker_options', array() );
        $post_types = isset( $options['post_types'] ) ? $options['post_types'] : array( 'post', 'page' );

        if ( empty( $post_types ) ) {
            update_option( self::INDEX_OPTION, array() );
            return 0;
        }

        $posts = get_posts( array(
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ) );

        $index = array();

        foreach ( $posts as $post_id ) {
            $keywords = $this->extract_keywords( $post_id );
            $url      = get_permalink( $post_id );

            foreach ( $keywords as $keyword ) {
                $key = strtolower( trim( $keyword ) );
                if ( ! empty( $key ) ) {
                    $index[ $key ] = array(
                        'url'     => $url,
                        'post_id' => $post_id,
                    );
                }
            }
        }

        update_option( self::INDEX_OPTION, $index );

        return count( $posts );
    }

    /**
     * Extract keywords from a post
     *
     * @param int $post_id Post ID
     * @return array Array of keywords
     */
    public function extract_keywords( $post_id ) {
        // Check for manual override first
        $manual_keywords = get_post_meta( $post_id, self::META_KEY, true );

        if ( ! empty( $manual_keywords ) ) {
            return array_map( 'trim', explode( ',', $manual_keywords ) );
        }

        // Auto-extract from enabled sources
        $options  = get_option( 'spm_interlinker_options', array() );
        $keywords = array();

        // Source: Post Title
        if ( ! empty( $options['source_title'] ) ) {
            $title = get_the_title( $post_id );

            // Strip prefix if configured
            if ( ! empty( $options['title_prefix_strip'] ) ) {
                $prefix = $options['title_prefix_strip'];
                if ( stripos( $title, $prefix ) === 0 ) {
                    $title = trim( substr( $title, strlen( $prefix ) ) );
                }
            }

            if ( ! empty( $title ) ) {
                $keywords[] = $title;
            }
        }

        // Source: Tags
        if ( ! empty( $options['source_tags'] ) ) {
            $tags = get_the_tags( $post_id );
            if ( $tags && ! is_wp_error( $tags ) ) {
                foreach ( $tags as $tag ) {
                    $keywords[] = $tag->name;
                }
            }
        }

        // Source: Categories
        if ( ! empty( $options['source_categories'] ) ) {
            $categories = get_the_category( $post_id );
            if ( $categories && ! is_wp_error( $categories ) ) {
                foreach ( $categories as $category ) {
                    // Skip "Uncategorized"
                    if ( strtolower( $category->name ) !== 'uncategorized' ) {
                        $keywords[] = $category->name;
                    }
                }
            }
        }

        // Source: Custom Field
        if ( ! empty( $options['source_custom_field'] ) && ! empty( $options['custom_field_key'] ) ) {
            $custom_value = get_post_meta( $post_id, $options['custom_field_key'], true );
            if ( ! empty( $custom_value ) ) {
                // Support comma-separated values in custom field
                $custom_keywords = array_map( 'trim', explode( ',', $custom_value ) );
                $keywords        = array_merge( $keywords, $custom_keywords );
            }
        }

        return array_unique( array_filter( $keywords ) );
    }

    /**
     * Update index when a post is saved
     *
     * @param int     $post_id Post ID
     * @param WP_Post $post    Post object
     */
    public function update_index_on_save( $post_id, $post ) {
        // Skip autosaves and revisions
        if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
            return;
        }

        // Check if this post type is in our selected types
        $options    = get_option( 'spm_interlinker_options', array() );
        $post_types = isset( $options['post_types'] ) ? $options['post_types'] : array();

        if ( ! in_array( $post->post_type, $post_types, true ) ) {
            return;
        }

        // Get current index
        $index = $this->get_index();

        // Remove old entries for this post
        $index = $this->remove_post_from_index( $post_id, $index );

        // If published, add new entries
        if ( $post->post_status === 'publish' ) {
            $keywords = $this->extract_keywords( $post_id );
            $url      = get_permalink( $post_id );

            foreach ( $keywords as $keyword ) {
                $key = strtolower( trim( $keyword ) );
                if ( ! empty( $key ) ) {
                    $index[ $key ] = array(
                        'url'     => $url,
                        'post_id' => $post_id,
                    );
                }
            }
        }

        update_option( self::INDEX_OPTION, $index );
    }

    /**
     * Remove a post from the index
     *
     * @param int $post_id Post ID
     */
    public function remove_from_index( $post_id ) {
        $index = $this->get_index();
        $index = $this->remove_post_from_index( $post_id, $index );
        update_option( self::INDEX_OPTION, $index );
    }

    /**
     * Remove all entries for a specific post from index array
     *
     * @param int   $post_id Post ID
     * @param array $index   Current index
     * @return array Updated index
     */
    private function remove_post_from_index( $post_id, $index ) {
        foreach ( $index as $keyword => $data ) {
            if ( isset( $data['post_id'] ) && $data['post_id'] == $post_id ) {
                unset( $index[ $keyword ] );
            }
        }
        return $index;
    }

    /**
     * Get index for use in AI prompts (excludes specific post)
     * OPTIMIZED: Limits keywords to reduce API costs
     *
     * @param int $exclude_post_id Post ID to exclude
     * @param int $limit Max keywords to return (default 30 for cost efficiency)
     * @return array Filtered index
     */
    public function get_index_for_ai( $exclude_post_id, $limit = 30 ) {
        $index    = $this->get_index();
        $filtered = array();

        foreach ( $index as $keyword => $data ) {
            if ( $data['post_id'] != $exclude_post_id ) {
                $filtered[ $keyword ] = $data['url'];
            }
        }

        // If we have more keywords than the limit, randomly sample to reduce token usage
        // This dramatically reduces API costs while still providing good link variety
        if ( count( $filtered ) > $limit ) {
            $keys = array_keys( $filtered );
            shuffle( $keys );
            $selected_keys = array_slice( $keys, 0, $limit );
            $limited = array();
            foreach ( $selected_keys as $key ) {
                $limited[ $key ] = $filtered[ $key ];
            }
            return $limited;
        }

        return $filtered;
    }

    /**
     * Get keywords sorted by length (longest first)
     *
     * @param int $exclude_post_id Post ID to exclude
     * @return array Sorted keywords with their data
     */
    public function get_sorted_keywords( $exclude_post_id = 0 ) {
        $index = $this->get_index();

        // Filter out excluded post
        if ( $exclude_post_id > 0 ) {
            $index = array_filter( $index, function( $data ) use ( $exclude_post_id ) {
                return $data['post_id'] != $exclude_post_id;
            } );
        }

        // Sort by keyword length (longest first)
        uksort( $index, function( $a, $b ) {
            return strlen( $b ) - strlen( $a );
        } );

        return $index;
    }

    /**
     * Get statistics for the dashboard
     *
     * @return array Statistics
     */
    public function get_stats() {
        $index   = $this->get_index();
        $options = get_option( 'spm_interlinker_options', array() );
        $post_types = isset( $options['post_types'] ) ? $options['post_types'] : array();

        // Count unique posts in index
        $post_ids = array();
        foreach ( $index as $data ) {
            if ( isset( $data['post_id'] ) ) {
                $post_ids[ $data['post_id'] ] = true;
            }
        }

        // Count cached AI responses
        global $wpdb;
        $cached_count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like( '_transient_spm_interlinker_ai_' ) . '%'
            )
        );

        return array(
            'total_indexed'   => count( $post_ids ),
            'total_keywords'  => count( $index ),
            'cached_responses' => (int) $cached_count,
            'post_types'      => $post_types,
        );
    }
}
