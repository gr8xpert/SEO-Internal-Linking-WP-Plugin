<?php
/**
 * AI Engine Class
 *
 * Handles OpenRouter API calls for Claude AI integration.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RS_Interlinker_AI_Engine {

    /**
     * OpenRouter API endpoint
     */
    const API_ENDPOINT = 'https://openrouter.ai/api/v1/chat/completions';

    /**
     * Encryption method (must match class-settings.php)
     */
    const ENCRYPTION_METHOD = 'aes-256-cbc';

    /**
     * URL Validator instance
     */
    private $url_validator;

    /**
     * Constructor
     */
    public function __construct( $url_validator ) {
        $this->url_validator = $url_validator;
    }

    /**
     * Decrypt API key from stored options
     *
     * @param string $encrypted_value Encrypted value (base64 encoded)
     * @return string Decrypted value
     */
    private function decrypt_api_key( $encrypted_value ) {
        if ( empty( $encrypted_value ) ) {
            return '';
        }

        if ( ! function_exists( 'openssl_decrypt' ) ) {
            return $encrypted_value;
        }

        $decoded = base64_decode( $encrypted_value, true );
        if ( $decoded === false ) {
            return $encrypted_value;
        }

        $key = $this->get_encryption_key();
        $iv_length = openssl_cipher_iv_length( self::ENCRYPTION_METHOD );

        if ( strlen( $decoded ) <= $iv_length ) {
            return $encrypted_value;
        }

        $iv = substr( $decoded, 0, $iv_length );
        $encrypted = substr( $decoded, $iv_length );

        $decrypted = openssl_decrypt( $encrypted, self::ENCRYPTION_METHOD, $key, OPENSSL_RAW_DATA, $iv );

        if ( $decrypted === false ) {
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
     * Generate AI top-up links for a post
     *
     * @param int    $post_id       Post ID
     * @param string $post_title    Post title
     * @param array  $keyword_index Keyword index (keyword => URL)
     * @param int    $links_needed  Number of internal links needed
     * @return array|false Array with 'html' key or false on failure
     */
    public function generate_topup_links( $post_id, $post_title, $keyword_index, $links_needed ) {
        $options = get_option( 'rs_interlinker_options', array() );

        // Check if API key is configured and decrypt it
        $encrypted_key = isset( $options['api_key'] ) ? $options['api_key'] : '';
        $api_key = $this->decrypt_api_key( $encrypted_key );
        if ( empty( $api_key ) ) {
            return false;
        }

        $model           = isset( $options['ai_model'] ) ? $options['ai_model'] : 'anthropic/claude-sonnet-4.5';
        $enable_external = ! empty( $options['enable_external'] );

        // Build the prompt
        $prompt = $this->build_prompt( $post_title, $keyword_index, $links_needed, $enable_external );

        // Make API call
        $response = $this->call_api( $api_key, $model, $prompt );

        if ( ! $response ) {
            return false;
        }

        // Parse response
        $parsed = $this->parse_response( $response );

        if ( ! $parsed ) {
            return false;
        }

        // Validate external URL if present
        if ( $enable_external && ! empty( $parsed['external_link'] ) ) {
            $external_valid = $this->url_validator->validate( $parsed['external_link']['url'] );

            if ( ! $external_valid ) {
                // Re-prompt for alternative URL
                $retry_prompt   = $this->build_retry_prompt( $post_title, $parsed['external_link']['url'] );
                $retry_response = $this->call_api( $api_key, $model, $retry_prompt );

                if ( $retry_response ) {
                    $retry_parsed = $this->parse_response( $retry_response );
                    if ( $retry_parsed && ! empty( $retry_parsed['external_link'] ) ) {
                        $retry_valid = $this->url_validator->validate( $retry_parsed['external_link']['url'] );
                        if ( $retry_valid ) {
                            $parsed['external_link'] = $retry_parsed['external_link'];
                        } else {
                            $parsed['external_link'] = null;
                        }
                    }
                } else {
                    $parsed['external_link'] = null;
                }
            }
        }

        // Build final HTML
        $html = $this->build_html( $parsed, $options );

        return array(
            'html'           => $html,
            'internal_links' => $parsed['internal_links'],
            'external_link'  => $parsed['external_link'],
            'sentence'       => $parsed['sentence'],
        );
    }

    /**
     * Build the AI prompt
     */
    private function build_prompt( $post_title, $keyword_index, $links_needed, $include_external ) {
        $keyword_list = '';
        foreach ( $keyword_index as $keyword => $url ) {
            $keyword_list .= "{$keyword} | {$url}\n";
        }

        $external_instruction = '';
        if ( $include_external ) {
            $external_instruction = '
2. Include exactly 1 external link to an authoritative, non-competitor website relevant to "' . $post_title . '". Good sources: official municipality/government sites, Wikipedia, established tourism or travel sites. NEVER link to real estate agency websites.';
        }

        $prompt = 'I have a webpage about "' . $post_title . '".

I need you to write ONE natural sentence that I can append to the page content. This sentence must:

1. Mention exactly ' . $links_needed . ' of the following related pages by using their exact keyword name from the list below. Choose the most geographically or topically relevant ones:

' . $keyword_list . '
' . $external_instruction . '

3. Sound natural and fit within a real estate or property website context.

Return your response in this exact JSON format:
{
  "sentence": "Your natural sentence here with {keyword} placeholders for internal links",
  "internal_links": [
    {"keyword": "exact keyword", "url": "URL from the list"},
    ...
  ]' . ( $include_external ? ',
  "external_link": {
    "anchor_text": "descriptive anchor text",
    "url": "https://..."
  }' : '' ) . '
}

Return ONLY the JSON, no markdown fences, no explanation.';

        return $prompt;
    }

    /**
     * Build retry prompt for failed external URL
     */
    private function build_retry_prompt( $post_title, $failed_url ) {
        return 'The external URL you suggested (' . $failed_url . ') is not accessible. Please provide an alternative authoritative external link for a page about "' . $post_title . '".

Good sources: official municipality/government sites, Wikipedia, established tourism or travel sites. NEVER link to real estate agency websites.

Return your response in this exact JSON format:
{
  "external_link": {
    "anchor_text": "descriptive anchor text",
    "url": "https://..."
  }
}

Return ONLY the JSON, no markdown fences, no explanation.';
    }

    /**
     * Make API call to OpenRouter
     */
    private function call_api( $api_key, $model, $prompt ) {
        $body = array(
            'model'      => $model,
            'messages'   => array(
                array(
                    'role'    => 'system',
                    'content' => 'You are an SEO content assistant. Generate natural, human-readable sentences for internal linking on websites.',
                ),
                array(
                    'role'    => 'user',
                    'content' => $prompt,
                ),
            ),
            'max_tokens' => 300,
        );

        $response = wp_remote_post( self::API_ENDPOINT, array(
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
                'HTTP-Referer'  => home_url(),
                'X-Title'       => get_bloginfo( 'name' ),
            ),
            'body'    => wp_json_encode( $body ),
        ) );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        if ( $status_code !== 200 ) {
            return false;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['choices'][0]['message']['content'] ) ) {
            return $body['choices'][0]['message']['content'];
        }

        return false;
    }

    /**
     * Parse AI response JSON
     */
    private function parse_response( $response ) {
        // Clean up response - remove markdown fences if present
        $response = trim( $response );
        $response = preg_replace( '/^```json\s*/i', '', $response );
        $response = preg_replace( '/^```\s*/i', '', $response );
        $response = preg_replace( '/\s*```$/i', '', $response );

        $data = json_decode( $response, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return false;
        }

        // Validate structure
        if ( ! isset( $data['sentence'] ) || ! isset( $data['internal_links'] ) ) {
            return false;
        }

        return array(
            'sentence'       => $data['sentence'],
            'internal_links' => $data['internal_links'],
            'external_link'  => isset( $data['external_link'] ) ? $data['external_link'] : null,
        );
    }

    /**
     * Build final HTML from parsed response
     */
    private function build_html( $parsed, $options ) {
        $sentence = $parsed['sentence'];

        // Replace internal link placeholders
        foreach ( $parsed['internal_links'] as $link ) {
            if ( empty( $link['keyword'] ) || empty( $link['url'] ) ) {
                continue;
            }

            // Validate URL is safe
            if ( ! $this->is_safe_url( $link['url'] ) ) {
                continue;
            }

            $keyword     = sanitize_text_field( $link['keyword'] );
            $url         = esc_url( $link['url'] );
            $anchor      = esc_html( $keyword );
            $replacement = '<a href="' . $url . '">' . $anchor . '</a>';

            // Replace {keyword} placeholder
            $sentence = str_replace( '{' . $keyword . '}', $replacement, $sentence );

            // Also try direct keyword replacement if placeholder not used
            if ( strpos( $sentence, $replacement ) === false ) {
                $sentence = preg_replace(
                    '/\b' . preg_quote( $keyword, '/' ) . '\b/i',
                    $replacement,
                    $sentence,
                    1
                );
            }
        }

        // Replace external link if present
        if ( ! empty( $parsed['external_link'] ) &&
             ! empty( $parsed['external_link']['url'] ) &&
             ! empty( $parsed['external_link']['anchor_text'] ) &&
             $this->is_safe_url( $parsed['external_link']['url'] ) ) {

            $ext_url    = esc_url( $parsed['external_link']['url'] );
            $ext_anchor = esc_html( sanitize_text_field( $parsed['external_link']['anchor_text'] ) );
            $rel        = isset( $options['external_link_rel'] ) && $options['external_link_rel'] === 'nofollow'
                          ? 'noopener nofollow'
                          : 'noopener';

            $ext_replacement = '<a href="' . $ext_url . '" target="_blank" rel="' . $rel . '">' . $ext_anchor . '</a>';

            // Replace placeholder or direct text
            $sentence = str_replace( '{external_link}', $ext_replacement, $sentence );

            // Try to replace anchor text directly if not using placeholder
            if ( strpos( $sentence, $ext_replacement ) === false ) {
                $sentence = preg_replace(
                    '/\b' . preg_quote( $parsed['external_link']['anchor_text'], '/' ) . '\b/i',
                    $ext_replacement,
                    $sentence,
                    1
                );
            }
        }

        // Sanitize final HTML to prevent XSS
        return $this->sanitize_html( $sentence );
    }

    /**
     * Sanitize HTML output to prevent XSS attacks
     * Only allows safe tags: links, basic formatting
     */
    private function sanitize_html( $html ) {
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
     * Validate URL is safe (http/https only, no javascript:, data:, etc.)
     */
    private function is_safe_url( $url ) {
        if ( empty( $url ) ) {
            return false;
        }

        $parsed = wp_parse_url( $url );

        if ( ! isset( $parsed['scheme'] ) ) {
            return false;
        }

        return in_array( strtolower( $parsed['scheme'] ), array( 'http', 'https' ), true );
    }
}
