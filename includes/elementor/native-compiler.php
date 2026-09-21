<?php

/** Stable compiler boundary for DesignPlan -> native Elementor data. */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/elementor-ir.php';

function wpae_native_elementor_compile( array $ir, array $brief, array $tokens = [], array $options = [] ): array {
	return wpae_elementor_ir_compile( $ir, $brief, $tokens, $options );
}
