<?php
/**
 * AI Engine Class - v2.3.0 Complete Rewrite
 *
 * Handles OpenRouter API calls for AI-powered internal linking.
 * Uses a robust approach: AI writes naturally, we handle all linking.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SPM_Interlinker_AI_Engine {

    const API_ENDPOINT = 'https://openrouter.ai/api/v1/chat/completions';
    const ENCRYPTION_METHOD = 'aes-256-cbc';

    private $url_validator;

    public function __construct( $url_validator ) {
        $this->url_validator = $url_validator;
    }

    /**
     * Generate AI top-up links for a post
     */
    public function generate_topup_links( $post_id, $post_title, $keyword_index, $links_needed ) {
        $options = get_option( 'spm_interlinker_options', array() );

        $encrypted_key = isset( $options['api_key'] ) ? $options['api_key'] : '';
        $api_key = $this->decrypt_api_key( $encrypted_key );
        if ( empty( $api_key ) ) {
            return array( 'error' => 'API key not configured.' );
        }

        $model           = isset( $options['ai_model'] ) ? $options['ai_model'] : 'google/gemini-2.0-flash-001';
        $enable_external = ! empty( $options['enable_external'] );

        // Build and send prompt
        $prompt = $this->build_prompt( $post_title, $keyword_index, $links_needed, $enable_external );
        $response = $this->call_api( $api_key, $model, $prompt );

        if ( is_array( $response ) && isset( $response['error'] ) ) {
            return $response;
        }

        if ( ! $response ) {
            return array( 'error' => 'API returned empty response.' );
        }

        // Parse the response
        $parsed = $this->parse_response( $response, $keyword_index );

        if ( ! $parsed ) {
            return array( 'error' => 'Failed to parse AI response. Preview: ' . esc_html( substr( $response, 0, 200 ) ) );
        }

        // Validate and retry external link if needed
        if ( $enable_external ) {
            $parsed = $this->handle_external_link( $parsed, $api_key, $model, $post_title );
        }

        // Build final HTML by finding keywords in sentence and linking them
        $html = $this->build_html( $parsed, $keyword_index, $options );

        return array(
            'html'           => $html,
            'internal_links' => $parsed['internal_links'],
            'external_link'  => isset( $parsed['external_link'] ) ? $parsed['external_link'] : null,
            'sentence'       => $parsed['sentence'],
        );
    }

    /**
     * Build the AI prompt - simplified approach
     * AI writes naturally, we handle linking
     */
    private function build_prompt( $post_title, $keyword_index, $links_needed, $include_external ) {
        // Only send keyword names, not URLs (AI doesn't need URLs)
        $keywords = array_keys( $keyword_index );
        $keyword_list = implode( ', ', array_slice( $keywords, 0, 30 ) );

        $external_part = '';
        if ( $include_external ) {
            $external_part = '
Also mention a nearby city/region that readers might want to learn about.

For external_link: provide a Wikipedia URL for that location.';
        }

        $prompt = 'Write ONE natural sentence for a real estate page about "' . $post_title . '".

The sentence must naturally mention EXACTLY ' . $links_needed . ' of these related properties:
' . $keyword_list . '
' . $external_part . '

Return ONLY this JSON (no markdown, no explanation):
{
  "sentence": "Your natural sentence mentioning the keywords exactly as written above",
  "keywords_used": ["keyword1", "keyword2", "keyword3"]' . ($include_external ? ',
  "external_link": {"text": "city name mentioned in sentence", "url": "https://en.wikipedia.org/wiki/CityName"}' : '') . '
}';

        return $prompt;
    }

    /**
     * Parse AI response - extract JSON and validate
     */
    private function parse_response( $response, $keyword_index ) {
        // Clean markdown fences
        $response = trim( $response );
        $response = preg_replace( '/^```[a-z]*\s*/i', '', $response );
        $response = preg_replace( '/\s*```$/i', '', $response );
        $response = trim( $response );

        // Extract JSON - find outermost braces
        $start = strpos( $response, '{' );
        $end = strrpos( $response, '}' );

        if ( $start === false || $end === false || $end <= $start ) {
            error_log( 'SPM Interlinker: No JSON found in response' );
            return false;
        }

        $json_str = substr( $response, $start, $end - $start + 1 );
        $data = json_decode( $json_str, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            error_log( 'SPM Interlinker JSON Error: ' . json_last_error_msg() );
            error_log( 'SPM Interlinker Response: ' . substr( $response, 0, 500 ) );
            return false;
        }

        if ( empty( $data['sentence'] ) ) {
            error_log( 'SPM Interlinker: No sentence in response' );
            return false;
        }

        // Build internal_links from keywords_used
        $internal_links = array();
        $keywords_used = isset( $data['keywords_used'] ) ? $data['keywords_used'] : array();

        foreach ( $keywords_used as $keyword ) {
            $keyword_lower = strtolower( trim( $keyword ) );
            // Find matching keyword in index (case-insensitive)
            foreach ( $keyword_index as $index_keyword => $url ) {
                if ( strtolower( $index_keyword ) === $keyword_lower ) {
                    $internal_links[] = array(
                        'keyword' => $index_keyword,
                        'url'     => $url,
                    );
                    break;
                }
            }
        }

        // Parse external link
        $external_link = null;
        if ( ! empty( $data['external_link'] ) && ! empty( $data['external_link']['url'] ) ) {
            $ext_url = $data['external_link']['url'];
            if ( preg_match( '/^https?:\/\//', $ext_url ) ) {
                $external_link = array(
                    'anchor_text' => ! empty( $data['external_link']['text'] ) ? $data['external_link']['text'] : 'Learn more',
                    'url'         => $ext_url,
                );
            }
        }

        return array(
            'sentence'       => $data['sentence'],
            'internal_links' => $internal_links,
            'external_link'  => $external_link,
        );
    }

    /**
     * Handle external link validation with retries
     */
    private function handle_external_link( $parsed, $api_key, $model, $post_title ) {
        if ( empty( $parsed['external_link'] ) || empty( $parsed['external_link']['url'] ) ) {
            return $parsed;
        }

        $max_retries = 3;
        $retry = 0;

        while ( $retry < $max_retries ) {
            $url = $parsed['external_link']['url'];

            if ( $this->url_validator->validate( $url ) ) {
                return $parsed; // Valid URL found
            }

            $retry++;
            error_log( "SPM Interlinker: External URL invalid (attempt $retry/$max_retries): $url" );

            // Request new external link
            $retry_prompt = 'Provide a Wikipedia URL for a location near "' . $post_title . '".
Return ONLY JSON: {"text": "location name", "url": "https://en.wikipedia.org/wiki/..."}';

            $retry_response = $this->call_api( $api_key, $model, $retry_prompt );

            if ( $retry_response && ! is_array( $retry_response ) ) {
                $retry_response = trim( $retry_response );
                $retry_response = preg_replace( '/^```[a-z]*\s*/i', '', $retry_response );
                $retry_response = preg_replace( '/\s*```$/i', '', $retry_response );

                $start = strpos( $retry_response, '{' );
                $end = strrpos( $retry_response, '}' );

                if ( $start !== false && $end !== false && $end > $start ) {
                    $retry_data = json_decode( substr( $retry_response, $start, $end - $start + 1 ), true );

                    if ( ! empty( $retry_data['url'] ) && preg_match( '/^https?:\/\//', $retry_data['url'] ) ) {
                        $parsed['external_link'] = array(
                            'anchor_text' => ! empty( $retry_data['text'] ) ? $retry_data['text'] : 'Learn more',
                            'url'         => $retry_data['url'],
                        );
                    }
                }
            }
        }

        // All retries failed
        error_log( 'SPM Interlinker: External link skipped after ' . $max_retries . ' failed attempts' );
        $parsed['external_link'] = null;
        return $parsed;
    }

    /**
     * Build HTML by finding keywords in sentence and creating links
     */
    private function build_html( $parsed, $keyword_index, $options ) {
        $sentence = $parsed['sentence'];

        // Remove any placeholder syntax the AI might have used
        $sentence = preg_replace( '/\{([^}]+)\}/', '$1', $sentence );

        // Remove any markdown links the AI might have used [text](url)
        $sentence = preg_replace( '/\[([^\]]+)\]\([^)]+\)/', '$1', $sentence );

        // Track which parts have been linked to avoid double-linking
        $linked_positions = array();

        // Link internal keywords - sort by length (longest first) to avoid partial matches
        $links_to_apply = $parsed['internal_links'];
        usort( $links_to_apply, function( $a, $b ) {
            return strlen( $b['keyword'] ) - strlen( $a['keyword'] );
        });

        foreach ( $links_to_apply as $link ) {
            if ( empty( $link['keyword'] ) || empty( $link['url'] ) ) {
                continue;
            }
            if ( ! $this->is_safe_url( $link['url'] ) ) {
                continue;
            }

            $keyword = $link['keyword'];
            $url     = esc_url( $link['url'] );
            $anchor  = esc_html( $keyword );
            $html_link = '<a href="' . $url . '">' . $anchor . '</a>';

            // Find keyword in sentence (case-insensitive, word boundary)
            $pattern = '/\b(' . preg_quote( $keyword, '/' ) . ')\b/i';

            if ( preg_match( $pattern, $sentence, $matches, PREG_OFFSET_CAPTURE ) ) {
                $match_pos = $matches[0][1];
                $match_text = $matches[0][0];

                // Check if this position overlaps with existing link
                $overlaps = false;
                foreach ( $linked_positions as $pos ) {
                    if ( $match_pos >= $pos['start'] && $match_pos < $pos['end'] ) {
                        $overlaps = true;
                        break;
                    }
                }

                if ( ! $overlaps ) {
                    $sentence = substr_replace( $sentence, $html_link, $match_pos, strlen( $match_text ) );
                    $linked_positions[] = array(
                        'start' => $match_pos,
                        'end'   => $match_pos + strlen( $html_link ),
                    );
                }
            }
        }

        // Handle external link
        if ( ! empty( $parsed['external_link'] ) && ! empty( $parsed['external_link']['url'] ) ) {
            $ext_url = $parsed['external_link']['url'];

            if ( $this->is_safe_url( $ext_url ) ) {
                $ext_anchor = esc_html( $parsed['external_link']['anchor_text'] );
                $rel = isset( $options['external_link_rel'] ) && $options['external_link_rel'] === 'nofollow'
                       ? 'noopener nofollow' : 'noopener';

                $ext_html = '<a href="' . esc_url( $ext_url ) . '" target="_blank" rel="' . $rel . '">' . $ext_anchor . '</a>';

                // Try to find anchor text in sentence and link it
                $ext_pattern = '/\b(' . preg_quote( $parsed['external_link']['anchor_text'], '/' ) . ')\b/i';

                if ( preg_match( $ext_pattern, $sentence ) && strpos( $sentence, $ext_url ) === false ) {
                    $sentence = preg_replace( $ext_pattern, $ext_html, $sentence, 1 );
                } else {
                    // Append external link at end if not found in sentence
                    $sentence = rtrim( $sentence, '.' ) . ' (' . $ext_html . ').';
                }
            }
        }

        return $this->sanitize_html( $sentence );
    }

    /**
     * Make API call to OpenRouter
     */
    private function call_api( $api_key, $model, $prompt, $retry_count = 0 ) {
        $body = array(
            'model'      => $model,
            'messages'   => array(
                array(
                    'role'    => 'system',
                    'content' => 'You are a helpful assistant. Return only valid JSON, no markdown formatting.',
                ),
                array(
                    'role'    => 'user',
                    'content' => $prompt,
                ),
            ),
            'max_tokens' => 400,
        );

        $response = wp_remote_post( self::API_ENDPOINT, array(
            'timeout' => 60,
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
                'HTTP-Referer'  => home_url(),
                'X-Title'       => get_bloginfo( 'name' ),
            ),
            'body' => wp_json_encode( $body ),
        ) );

        if ( is_wp_error( $response ) ) {
            return array( 'error' => 'Connection error: ' . $response->get_error_message() );
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $response_body = json_decode( wp_remote_retrieve_body( $response ), true );

        // Retry on rate limit or server error
        if ( ( $status_code === 429 || $status_code >= 500 ) && $retry_count < 2 ) {
            sleep( $status_code === 429 ? 5 : 3 );
            return $this->call_api( $api_key, $model, $prompt, $retry_count + 1 );
        }

        if ( $status_code !== 200 ) {
            $error = isset( $response_body['error']['message'] ) ? $response_body['error']['message'] : "HTTP $status_code";
            return array( 'error' => 'API Error: ' . $error );
        }

        if ( isset( $response_body['choices'][0]['message']['content'] ) ) {
            return $response_body['choices'][0]['message']['content'];
        }

        return array( 'error' => 'Unexpected API response structure.' );
    }

    /**
     * Decrypt API key
     */
    private function decrypt_api_key( $encrypted_value ) {
        if ( empty( $encrypted_value ) ) {
            return '';
        }

        if ( preg_match( '/^sk-[a-zA-Z]/', $encrypted_value ) ) {
            return $encrypted_value;
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

        return $decrypted !== false ? $decrypted : $encrypted_value;
    }

    private function get_encryption_key() {
        $auth_key = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'rs-interlinker-default-key';
        return hash( 'sha256', $auth_key, true );
    }

    private function sanitize_html( $html ) {
        return wp_kses( $html, array(
            'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ),
        ) );
    }

    private function is_safe_url( $url ) {
        if ( empty( $url ) ) return false;
        $parsed = wp_parse_url( $url );
        if ( ! isset( $parsed['scheme'] ) ) return false;
        return in_array( strtolower( $parsed['scheme'] ), array( 'http', 'https' ), true );
    }
}
