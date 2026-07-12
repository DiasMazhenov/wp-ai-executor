<?php

defined( 'ABSPATH' ) || exit;

function wpae_elementor_visual_audit( WP_REST_Request $request ): WP_REST_Response {
    $post_id = absint( $request->get_param( 'post_id' ) );
    $has_payload = $request->get_param( 'elementor_data' ) !== null;
    $context = [
        'source' => $has_payload ? 'request.elementor_data' : 'post_meta',
    ];

    if ( $has_payload ) {
        $elementor_data = wpae_get_elementor_data_from_request( $request );
        if ( is_wp_error( $elementor_data ) ) {
            return new WP_REST_Response( [ 'ok' => false, 'error' => $elementor_data->get_error_message() ], 400 );
        }
    } else {
        if ( $post_id <= 0 ) {
            return new WP_REST_Response( [ 'ok' => false, 'error' => 'post_id or elementor_data is required.' ], 400 );
        }

        $post = get_post( $post_id );
        if ( ! $post ) {
            return new WP_REST_Response( [ 'ok' => false, 'error' => 'Post not found.' ], 404 );
        }

        $raw_data = (string) get_post_meta( $post_id, '_elementor_data', true );
        if ( $raw_data === '' ) {
            return new WP_REST_Response( [ 'ok' => false, 'error' => '_elementor_data is empty.', 'post_id' => $post_id ], 422 );
        }

        $elementor_data = json_decode( $raw_data, true );
        if ( ! is_array( $elementor_data ) ) {
            return new WP_REST_Response( [ 'ok' => false, 'error' => '_elementor_data is not valid JSON array data.', 'post_id' => $post_id ], 422 );
        }

        $context['post_id'] = $post_id;
        $context['post_status'] = get_post_status( $post_id );
        $context['url'] = get_permalink( $post_id );
    }

    $audit = wpae_build_elementor_visual_audit( $elementor_data, $context );
    $status = $audit['level'] === 'blocked' ? 422 : 200;

    return new WP_REST_Response( $audit, $status );
}

function wpae_visual_audit_page( WP_REST_Request $request ): WP_REST_Response {
    $target = wpae_resolve_public_visual_audit_target( $request );
    if ( is_wp_error( $target ) ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => $target->get_error_message(),
            'code' => $target->get_error_code(),
        ], 400 );
    }

    $response = wp_safe_remote_get( $target['url'], [
        'timeout' => 15,
        'redirection' => 3,
        'limit_response_size' => 4 * 1024 * 1024,
        'reject_unsafe_urls' => true,
        'user-agent' => 'WP AI Executor Visual Audit/' . WPAE_VERSION,
    ] );

    if ( is_wp_error( $response ) ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => 'Failed to fetch public page.',
            'message' => $response->get_error_message(),
            'target' => $target,
        ], 502 );
    }

    $status_code = (int) wp_remote_retrieve_response_code( $response );
    $html = (string) wp_remote_retrieve_body( $response );
    $content_type = wp_remote_retrieve_header( $response, 'content-type' );
    if ( is_array( $content_type ) ) {
        $content_type = implode( ', ', $content_type );
    }

    $audit = wpae_build_public_html_visual_audit( $html, [
        'source' => 'public_html',
        'status_code' => $status_code,
        'target' => $target,
        'content_type' => (string) $content_type,
    ] );

    $response_status = $status_code >= 200 && $status_code < 400
        ? ( $audit['level'] === 'blocked' ? 422 : 200 )
        : 502;

    return new WP_REST_Response( $audit, $response_status );
}

function wpae_resolve_public_visual_audit_target( WP_REST_Request $request ) {
    $post_id = absint( $request->get_param( 'post_id' ) );
    $url = trim( (string) $request->get_param( 'url' ) );

    if ( $post_id > 0 ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return new WP_Error( 'post_not_found', 'Post not found.' );
        }

        $permalink = get_permalink( $post_id );
        if ( ! $permalink ) {
            return new WP_Error( 'missing_permalink', 'Post permalink is unavailable.' );
        }

        if ( ! wpae_is_safe_visual_audit_url( $permalink ) ) {
            return new WP_Error( 'unsafe_target', 'The page URL resolves to a private, reserved, or unsafe network address.' );
        }

        return [
            'type' => 'post',
            'post_id' => $post_id,
            'post_status' => get_post_status( $post_id ),
            'url' => $permalink,
        ];
    }

    if ( $url === '' ) {
        return new WP_Error( 'missing_target', 'post_id or url is required.' );
    }

    if ( strpos( $url, '/' ) === 0 ) {
        $url = home_url( $url );
    }

    $url = esc_url_raw( $url );
    $parts = wp_parse_url( $url );
    $home_parts = wp_parse_url( home_url() );
    if ( ! is_array( $parts ) || ! in_array( $parts['scheme'] ?? '', [ 'http', 'https' ], true ) ) {
        return new WP_Error( 'invalid_url', 'URL must be a valid http or https URL.' );
    }

    if ( ! is_array( $home_parts ) || strtolower( (string) ( $parts['host'] ?? '' ) ) !== strtolower( (string) ( $home_parts['host'] ?? '' ) ) ) {
        return new WP_Error( 'external_url_forbidden', 'Only same-site URLs can be audited.' );
    }

    if ( ! wpae_is_safe_visual_audit_url( $url ) ) {
        return new WP_Error( 'unsafe_target', 'The page URL resolves to a private, reserved, or unsafe network address.' );
    }

    return [
        'type' => 'url',
        'url' => $url,
    ];
}

function wpae_is_safe_visual_audit_url( string $url ): bool {
    $parts = wp_parse_url( $url );
    $home_parts = wp_parse_url( home_url() );

    if ( ! is_array( $parts ) || ! is_array( $home_parts ) ) {
        return false;
    }

    if ( ! in_array( strtolower( (string) ( $parts['scheme'] ?? '' ) ), [ 'http', 'https' ], true ) ) {
        return false;
    }

    if ( ! empty( $parts['user'] ) || ! empty( $parts['pass'] ) || ! empty( $parts['port'] ) ) {
        return false;
    }

    $host = strtolower( rtrim( (string) ( $parts['host'] ?? '' ), '.' ) );
    $home_host = strtolower( rtrim( (string) ( $home_parts['host'] ?? '' ), '.' ) );
    if ( $host === '' || $host !== $home_host ) {
        return false;
    }

    if ( function_exists( 'wp_http_validate_url' ) && ! wp_http_validate_url( $url ) ) {
        return false;
    }

    $ips = [];
    if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
        $ips[] = $host;
    } else {
        $ips = array_merge( $ips, (array) gethostbynamel( $host ) );
        if ( function_exists( 'dns_get_record' ) ) {
            $dns_types = 0;
            if ( defined( 'DNS_A' ) ) {
                $dns_types |= DNS_A;
            }
            if ( defined( 'DNS_AAAA' ) ) {
                $dns_types |= DNS_AAAA;
            }

            if ( $dns_types !== 0 ) {
                $records = dns_get_record( $host, $dns_types );
                foreach ( (array) $records as $record ) {
                    if ( ! empty( $record['ip'] ) ) {
                        $ips[] = $record['ip'];
                    }
                    if ( ! empty( $record['ipv6'] ) ) {
                        $ips[] = $record['ipv6'];
                    }
                }
            }
        }
    }

    $ips = array_values( array_unique( array_filter( array_map( 'strval', $ips ) ) ) );
    if ( empty( $ips ) ) {
        return false;
    }

    foreach ( $ips as $ip ) {
        if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) === false ) {
            return false;
        }
    }

    return true;
}

function wpae_count_regex_matches( string $pattern, string $subject ): int {
    $matches = [];
    return preg_match_all( $pattern, $subject, $matches ) ?: 0;
}

function wpae_public_html_text_length( string $html ): int {
    $html = preg_replace( '#<(script|style|noscript|svg)\b[^>]*>.*?</\1>#is', ' ', $html );
    $text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $html ) ) );
    return function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );
}

function wpae_count_wide_fixed_width_risks( string $html ): int {
    $matches = [];
    preg_match_all( '/(?:^|[;"\s])(width|min-width)\s*:\s*(\d{3,5})px/i', $html, $matches, PREG_SET_ORDER );
    $risks = 0;
    foreach ( $matches as $match ) {
        if ( (int) ( $match[2] ?? 0 ) > 430 ) {
            $risks++;
        }
    }
    return $risks;
}

function wpae_build_public_html_visual_audit( string $html, array $context = [] ): array {
    $checks = [];
    $status_code = (int) ( $context['status_code'] ?? 0 );
    $html_bytes = strlen( $html );
    $text_length = wpae_public_html_text_length( $html );
    $has_viewport = (bool) preg_match( '/<meta[^>]+name=["\']viewport["\']/i', $html );
    $has_title = (bool) preg_match( '/<title\b[^>]*>[^<]{3,}<\/title>/i', $html );
    $has_elementor = stripos( $html, 'elementor' ) !== false;
    $has_cta = (bool) preg_match( '/<(a|button)\b[^>]*(?:href=|type=|class=)[^>]*>[^<]{2,}<\/\1>/i', $html );
    $wide_width_risks = wpae_count_wide_fixed_width_risks( $html );
    $overflow_hidden_count = wpae_count_regex_matches( '/overflow-x\s*:\s*hidden/i', $html );
    $invisible_text_risks = wpae_count_regex_matches( '/(?:color\s*:\s*transparent|rgba\([^)]*,\s*0\s*\)|opacity\s*:\s*0\b|visibility\s*:\s*hidden\b)/i', $html );
    $empty_block_risks = wpae_count_regex_matches( '/<(section|div|article|main|header|footer)\b[^>]*(?:elementor|wpae)[^>]*>\s*<\/\1>/i', $html );
    $desktop_only_media = wpae_count_regex_matches( '/@media\s*\([^)]*min-width\s*:\s*(?:7\d\d|8\d\d|9\d\d|1\d{3,})px/i', $html );
    $mobile_media = wpae_count_regex_matches( '/@media\s*\([^)]*max-width\s*:\s*(?:7\d\d|6\d\d|5\d\d|4\d\d|3\d\d)px/i', $html );

    wpae_visual_audit_add_check(
        $checks,
        'public_fetch',
        $status_code >= 200 && $status_code < 400 && $html_bytes > 0 ? 'pass' : 'fail',
        $status_code >= 200 && $status_code < 400 && $html_bytes > 0 ? 14 : 0,
        14,
        $status_code >= 200 && $status_code < 400 && $html_bytes > 0 ? 'Public page HTML was fetched successfully.' : 'Public page HTML could not be fetched successfully.',
        [ 'status_code' => $status_code, 'html_bytes' => $html_bytes ],
        'Check that the page is published and reachable without WP Admin authentication.'
    );

    wpae_visual_audit_add_check(
        $checks,
        'html_structure',
        $has_viewport && $has_title && $text_length >= 300 ? 'pass' : 'warn',
        $has_viewport && $has_title && $text_length >= 300 ? 14 : 7,
        14,
        $has_viewport && $has_title && $text_length >= 300 ? 'Basic public HTML structure is present.' : 'Public HTML structure or visible copy looks weak.',
        [
            'has_viewport_meta' => $has_viewport,
            'has_title' => $has_title,
            'visible_text_length' => $text_length,
            'contains_elementor_markup' => $has_elementor,
        ],
        'Ensure the public page has viewport meta, a title, and enough visible page copy.'
    );

    wpae_visual_audit_add_check(
        $checks,
        'horizontal_overflow_risk',
        $wide_width_risks === 0 ? 'pass' : 'warn',
        $wide_width_risks === 0 ? 14 : 6,
        14,
        $wide_width_risks === 0 ? 'No obvious fixed-width mobile overflow risks were found in public HTML/CSS.' : 'Fixed width/min-width styles may cause mobile horizontal overflow.',
        [
            'wide_fixed_width_risks' => $wide_width_risks,
            'overflow_x_hidden_count' => $overflow_hidden_count,
        ],
        'Replace large fixed width/min-width values with %, max-width, flex-basis, rem, or responsive Elementor settings.'
    );

    wpae_visual_audit_add_check(
        $checks,
        'invisible_text_risk',
        $invisible_text_risks <= 8 ? 'pass' : 'warn',
        $invisible_text_risks <= 8 ? 12 : 5,
        12,
        $invisible_text_risks <= 8 ? 'No excessive invisible-text patterns were detected.' : 'Public HTML contains many invisible-text patterns.',
        [ 'invisible_text_pattern_count' => $invisible_text_risks ],
        'Inspect hidden/transparent text and ensure important headings, body copy, and CTAs are visible.'
    );

    wpae_visual_audit_add_check(
        $checks,
        'empty_block_risk',
        $empty_block_risks === 0 ? 'pass' : 'warn',
        $empty_block_risks === 0 ? 10 : 4,
        10,
        $empty_block_risks === 0 ? 'No obvious empty Elementor/WPAE blocks were found in public HTML.' : 'Public HTML contains suspicious empty Elementor/WPAE blocks.',
        [ 'empty_block_risk_count' => $empty_block_risks ],
        'Remove empty blocks or populate them with native Elementor content/settings.'
    );

    wpae_visual_audit_add_check(
        $checks,
        'cta_presence',
        $has_cta ? 'pass' : 'warn',
        $has_cta ? 10 : 4,
        10,
        $has_cta ? 'A public link/button CTA is detectable.' : 'No clear public link/button CTA was detected.',
        [ 'has_cta' => $has_cta ],
        'Add a visible native button/link CTA and verify it is tappable on mobile.'
    );

    wpae_visual_audit_add_check(
        $checks,
        'mobile_first_css_signal',
        $mobile_media >= $desktop_only_media || $mobile_media > 0 ? 'pass' : 'warn',
        $mobile_media >= $desktop_only_media || $mobile_media > 0 ? 10 : 5,
        10,
        $mobile_media >= $desktop_only_media || $mobile_media > 0 ? 'Mobile responsive CSS signals are present.' : 'Mobile responsive CSS signals are weak in public HTML.',
        [
            'max_width_media_queries' => $mobile_media,
            'large_min_width_media_queries' => $desktop_only_media,
        ],
        'Design mobile first and add explicit mobile Elementor responsive settings before desktop polish.'
    );

    wpae_visual_audit_add_check(
        $checks,
        'server_side_limitations',
        'warn',
        0,
        0,
        'Server-side audit does not create screenshots or compute full CSS cascade/contrast.',
        [
            'unsupported' => [
                'desktop/mobile screenshot metrics',
                'true rendered overflow',
                'computed contrast after CSS cascade',
                'animation timing',
            ],
        ],
        'Use browser/public-page verification after REST writes for screenshots, clickable state, rendered contrast, and real overflow.'
    );

    $points = 0;
    $max = 0;
    $has_failures = false;
    $recommendations = [];
    foreach ( $checks as $check ) {
        $points += (int) ( $check['points'] ?? 0 );
        $max += (int) ( $check['max'] ?? 0 );
        if ( ( $check['status'] ?? '' ) === 'fail' ) {
            $has_failures = true;
        }
        if ( ( $check['status'] ?? '' ) !== 'pass' && ! empty( $check['recommendation'] ) ) {
            $recommendations[] = $check['recommendation'];
        }
    }

    $score = $max > 0 ? (int) round( ( $points / $max ) * 100 ) : 100;
    $level = $has_failures ? 'blocked' : ( $score >= 90 ? 'strong' : ( $score >= 75 ? 'acceptable' : ( $score >= 50 ? 'weak' : 'blocked' ) ) );

    return [
        'ok' => ! $has_failures,
        'visual_audit_version' => 'v01.03.00',
        'audit_type' => 'public_html',
        'score' => $score,
        'level' => $level,
        'points' => $points,
        'max_points' => $max,
        'context' => $context,
        'stats' => [
            'html_bytes' => $html_bytes,
            'visible_text_length' => $text_length,
            'has_viewport_meta' => $has_viewport,
            'has_title' => $has_title,
            'contains_elementor_markup' => $has_elementor,
            'wide_fixed_width_risks' => $wide_width_risks,
            'invisible_text_risks' => $invisible_text_risks,
            'empty_block_risks' => $empty_block_risks,
            'has_cta' => $has_cta,
            'max_width_media_queries' => $mobile_media,
            'large_min_width_media_queries' => $desktop_only_media,
        ],
        'checks' => $checks,
        'recommendations' => array_values( array_unique( $recommendations ) ),
        'contract' => [
            'scope' => 'Read-only public HTML audit for same-site pages.',
            'use_after' => 'Use after /elementor/page or /elementor/update writes, alongside /elementor/visual-audit and /audit.',
            'no_browser_claim' => 'This endpoint intentionally avoids server-side screenshots because typical WordPress hosting is not a reliable browser-rendering environment.',
        ],
    ];
}

