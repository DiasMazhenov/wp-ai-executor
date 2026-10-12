<?php
/** Re-run a bounded, explicitly retained claim-grounding audit bundle without a provider request. */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 2 );
}

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
function sanitize_key( $key ): string {
	$key = strtolower( (string) $key );
	return preg_replace( '/[^a-z0-9_\-]/', '', $key );
}

require dirname( __DIR__ ) . '/includes/llm/brief-ir-structured.php';

$path = (string) ( $argv[1] ?? '' );
if ( $path === '' || ! is_file( $path ) ) {
	fwrite( STDERR, "Usage: php tests/replay-claim-grounding.php <audit-bundle.json>\n" );
	exit( 2 );
}
$bytes = file_get_contents( $path );
if ( ! is_string( $bytes ) || strlen( $bytes ) > 32768 ) {
	fwrite( STDERR, "Replay bundle is missing or exceeds 32 KiB.\n" );
	exit( 2 );
}
$bundle = json_decode( $bytes, true );
if ( ! is_array( $bundle ) || ( $bundle['schema'] ?? '' ) !== 'wpae-claim-grounding-replay-v1' || ( $bundle['mode'] ?? '' ) !== 'explicit_test_fixture' ) {
	fwrite( STDERR, "Unsupported replay bundle.\n" );
	exit( 2 );
}
$facts = is_array( $bundle['approved_facts'] ?? null ) ? $bundle['approved_facts'] : [];
$results = [];
foreach ( (array) ( $bundle['cases'] ?? [] ) as $case ) {
	if ( ! is_array( $case ) || ! is_string( $case['text'] ?? null ) || strlen( $case['text'] ) > 2000 ) {
		fwrite( STDERR, "Invalid or overlong case.\n" );
		exit( 2 );
	}
	$result = wpae_brief_ir_generated_claims_grounding_result(
		$case['text'],
		array_values( (array) ( $case['fact_refs'] ?? [] ) ),
		$facts,
		false,
		(string) ( $case['slot_id'] ?? '' )
	);
	$expected_status = (string) ( $case['expected']['status'] ?? '' );
	$expected_rule = (string) ( $case['expected']['rule_id'] ?? '' );
	if ( $result['status'] !== $expected_status || $result['rule_id'] !== $expected_rule ) {
		fwrite( STDERR, 'Replay mismatch for ' . sanitize_key( (string) ( $case['id'] ?? '' ) ) . ': ' . $result['status'] . '/' . $result['rule_id'] . "\n" );
		exit( 1 );
	}
	$diagnostic = wpae_brief_ir_generated_claims_safe_diagnostic( $result );
	$json = json_encode( $diagnostic, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	$round_trip = is_string( $json ) ? json_decode( $json, true ) : null;
	if ( ! is_array( $round_trip ) || strlen( (string) $json ) > 8192 || array_key_exists( 'claims', $round_trip ) ) {
		fwrite( STDERR, 'Unsafe diagnostic serialization for ' . sanitize_key( (string) ( $case['id'] ?? '' ) ) . ".\n" );
		exit( 1 );
	}
	$results[] = [
		'id' => sanitize_key( (string) ( $case['id'] ?? '' ) ),
		'status' => $result['status'],
		'rule_id' => $result['rule_id'],
		'policy_version' => $result['policy_version'],
		'diagnostic_json_bytes' => strlen( $json ),
		'write_count' => 0,
	];
}
echo json_encode( [ 'status' => 'passed', 'case_count' => count( $results ), 'cases' => $results ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . PHP_EOL;
