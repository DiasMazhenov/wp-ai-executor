<?php
/** Behavioral check for the production patch route's optimistic before-hash guard. */
define( 'ABSPATH', __DIR__ );

class WP_REST_Request {
	private array $params;
	public function __construct( array $params ) { $this->params = $params; }
	public function get_param( string $key ) { return $this->params[ $key ] ?? null; }
}
class WP_REST_Response {
	public function __construct( private array $data, private int $status = 200 ) {}
	public function get_data(): array { return $this->data; }
	public function get_status(): int { return $this->status; }
}
function absint( $value ): int { return abs( (int) $value ); }
function sanitize_key( $value ): string { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
function sanitize_text_field( $value ): string { return trim( strip_tags( (string) $value ) ); }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function is_wp_error( $value ): bool { return false; }
function get_post( $post_id ) { return (object) [ 'ID' => (int) $post_id ]; }
function get_post_meta( $post_id, $key, $single ) { return wp_json_encode( $GLOBALS['elementor_data'] ); }
function wpae_get_enforceable_skill_rules(): array { return []; }
function wpae_validate_design_system_contract( array $data, array $context = [] ): array { return [ 'ok' => true ]; }
function wpae_build_elementor_preflight( array $data, WP_REST_Request $request, array $context = [] ): array { return [ 'ok' => true ]; }

require __DIR__ . '/../includes/elementor/validation-rules.php';
require __DIR__ . '/../includes/elementor/data.php';
require __DIR__ . '/../includes/elementor/page-update.php';

$GLOBALS['elementor_data'] = [
	[
		'id' => 'service-root',
		'elType' => 'container',
		'settings' => [],
		'elements' => [ [ 'id' => 'service-title', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => [ 'title' => 'До' ], 'elements' => [] ] ],
	],
];
$patch = [ 'element_id' => 'service-title', 'path' => 'settings.title', 'op' => 'set', 'value' => 'После' ];
$params = [ 'post_id' => 5214, 'patches' => [ $patch ], 'dry_run' => true ];
$before = $GLOBALS['elementor_data'];

$stale_request = new WP_REST_Request( $params + [ 'expected_before_hash' => str_repeat( '0', 64 ) ] );
$stale = wpae_elementor_patch( $stale_request );
if ( $stale->get_status() !== 409 || ( $stale->get_data()['code'] ?? '' ) !== 'wpae_patch_before_hash_mismatch' || $GLOBALS['elementor_data'] !== $before ) {
	throw new RuntimeException( 'Stale preview hash was not rejected before patching.' );
}

$current_hash = hash( 'sha256', (string) wp_json_encode( $before ) );
$current_request = new WP_REST_Request( $params + [ 'expected_before_hash' => $current_hash ] );
$current = wpae_elementor_patch( $current_request );
if ( $current->get_status() !== 200 || empty( $current->get_data()['ok'] ) || ( $current->get_data()['elementor_data'][0]['elements'][0]['settings']['title'] ?? '' ) !== 'После' || $GLOBALS['elementor_data'] !== $before ) {
	throw new RuntimeException( 'Current preview hash did not permit a non-mutating patch preview.' );
}

echo "production Elementor patch before-hash guard: OK\n";
