<?php
/**
 * URL Validator Class
 *
 * Validates external URLs using HEAD requests.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SPM_Interlinker_URL_Validator {

    /**
     * Request timeout in seconds
     */
    const TIMEOUT = 5;

    /**
     * Validate a URL via HEAD request
     *
     * @param string $url URL to validate
     * @return bool True if URL returns HTTP 200
     */
    public function validate( $url ) {
        if ( empty( $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
            return false;
        }

        $response = wp_remote_head( $url, array(
            'timeout'     => self::TIMEOUT,
            'redirection' => 5,
            'sslverify'   => false,
            'user-agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        ) );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $status_code = wp_remote_retrieve_response_code( $response );

        return $status_code === 200;
    }

    /**
     * Validate URL with fallback to GET request
     *
     * Some servers don't respond to HEAD requests properly
     *
     * @param string $url URL to validate
     * @return bool True if URL is valid
     */
    public function validate_with_fallback( $url ) {
        // Try HEAD first
        if ( $this->validate( $url ) ) {
            return true;
        }

        // Fallback to GET request
        $response = wp_remote_get( $url, array(
            'timeout'     => self::TIMEOUT,
            'redirection' => 5,
            'sslverify'   => false,
            'user-agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        ) );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $status_code = wp_remote_retrieve_response_code( $response );

        // Accept 200 and other success codes
        return $status_code >= 200 && $status_code < 300;
    }

    /**
     * Check if URL is from a blocked domain (competitors)
     *
     * @param string $url URL to check
     * @param array  $blocked_domains List of blocked domains
     * @return bool True if URL is from a blocked domain
     */
    public function is_blocked_domain( $url, $blocked_domains = array() ) {
        if ( empty( $blocked_domains ) ) {
            return false;
        }

        $parsed = wp_parse_url( $url );

        if ( ! isset( $parsed['host'] ) ) {
            return false;
        }

        $host = strtolower( $parsed['host'] );

        foreach ( $blocked_domains as $domain ) {
            $domain = strtolower( trim( $domain ) );
            if ( $host === $domain || strpos( $host, '.' . $domain ) !== false ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if URL is an external link
     *
     * @param string $url URL to check
     * @return bool True if external
     */
    public function is_external( $url ) {
        $site_host = wp_parse_url( home_url(), PHP_URL_HOST );
        $url_host  = wp_parse_url( $url, PHP_URL_HOST );

        if ( ! $url_host ) {
            return false;
        }

        return strtolower( $site_host ) !== strtolower( $url_host );
    }
}
