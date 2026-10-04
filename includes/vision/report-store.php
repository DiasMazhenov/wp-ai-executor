<?php
defined( 'ABSPATH' ) || exit;
const WPAE_VISION_REPORTS_OPTION = 'wp_ai_executor_vision_reports';

function wpae_get_vision_report( string $report_id ): ?array {
    if ( $report_id === '' ) {
        return null;
    }
    foreach ( (array) get_option( WPAE_VISION_REPORTS_OPTION, [] ) as $report ) {
        if ( is_array( $report ) && hash_equals( (string) ( $report['report_id'] ?? '' ), $report_id ) ) {
            return $report;
        }
    }
    return null;
}
