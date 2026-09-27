<?php
/** Exercises the plugin-local imported-template loader and real Elementor normalizer. */
define( 'ABSPATH', __DIR__ . '/' );

class WP_Error {
    public $code;
    public $message;
    public $data;
    public function __construct( $code, $message, $data = [] ) { $this->code = $code; $this->message = $message; $this->data = $data; }
}

class WP_REST_Request implements ArrayAccess {
    private $params;
    private $route;
    public function __construct( array $params = [], array $route = [] ) { $this->params = $params; $this->route = $route; }
    public function get_param( $key ) { return $this->params[ $key ] ?? null; }
    public function offsetExists( $offset ): bool { return isset( $this->route[ $offset ] ); }
    public function offsetGet( $offset ): mixed { return $this->route[ $offset ] ?? null; }
    public function offsetSet( $offset, $value ): void { $this->route[ $offset ] = $value; }
    public function offsetUnset( $offset ): void { unset( $this->route[ $offset ] ); }
}

class WP_REST_Response {
    public $data;
    public $status;
    public function __construct( $data, $status = 200 ) { $this->data = $data; $this->status = $status; }
    public function get_data() { return $this->data; }
}

function add_action( ...$args ): void {}
function is_wp_error( $value ): bool { return $value instanceof WP_Error; }
function sanitize_key( $value ): string { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ) ?? ''; }
function sanitize_html_class( $value ): string { return preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $value ) ?? ''; }
function sanitize_text_field( $value ): string { return trim( strip_tags( (string) $value ) ); }
function sanitize_textarea_field( $value ): string { return trim( preg_replace( '/\r\n?/', "\n", strip_tags( (string) $value ) ) ?? '' ); }
function wp_strip_all_tags( $value ): string { return strip_tags( (string) $value ); }
function esc_url_raw( $value ): string { return trim( (string) $value ); }
function absint( $value ): int { return abs( (int) $value ); }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function get_posts( $args = [] ): array { return []; }
function wpae_get_design_system_id(): string { return 'test'; }
function wpae_get_design_system_required_classes(): array { return [ 'wpae-system-test' ]; }
function wpae_get_enforceable_skill_rules(): array { return []; }
function wp_insert_post( $post, $wp_error = false ) { $GLOBALS['template_db_write_attempts'] = (int) ( $GLOBALS['template_db_write_attempts'] ?? 0 ) + 1; return new WP_Error( 'unexpected_db_write', 'Plugin-local template loading must not insert WordPress posts.' ); }
function imported_template_check( bool $condition, string $message ): void {
    if ( ! $condition ) {
        throw new RuntimeException( $message );
    }
}
function imported_template_element_ids( array $nodes ): array {
    $ids = [];
    foreach ( $nodes as $node ) {
        if ( ! is_array( $node ) ) {
            continue;
        }
        $ids[] = (string) ( $node['id'] ?? '' );
        $ids = array_merge( $ids, imported_template_element_ids( (array) ( $node['elements'] ?? [] ) ) );
    }
    return $ids;
}

require __DIR__ . '/../includes/elementor/normalize.php';
require __DIR__ . '/../includes/elementor/validation-rules.php';
require __DIR__ . '/../includes/elementor/data.php';
require __DIR__ . '/../includes/elementor/compose.php';
require __DIR__ . '/../includes/elementor/block-library.php';

$manifest = wpae_block_library_imported_template_manifest();
imported_template_check( count( $manifest ) === 157, 'The plugin manifest must include all 157 imported JSON files.' );
$records = wpae_block_library_imported_template_records( '', [] );
imported_template_check( count( $records ) === 155, 'Only the 155 exports with Elementor content trees should enter retrieval.' );

$ids = [];
foreach ( $records as $record ) {
    imported_template_check( (string) ( $record['source'] ?? '' ) === 'plugin_template', 'Imported templates must be served from the plugin source catalog.' );
    imported_template_check( wpae_block_library_is_trusted_bundled_fixture( $record ), 'A manifest-verified template was not accepted by the bundled-source trust check.' );
    imported_template_check( ! empty( $record['compatibility']['raw_valid'] ) && ! empty( $record['compatibility']['normalizable'] ), 'An imported template did not pass structural validation and normalization.' );
    $element_ids = [];
    $walk = static function ( array $nodes ) use ( &$walk, &$element_ids ): void {
        foreach ( $nodes as $node ) {
            if ( ! is_array( $node ) ) {
                continue;
            }
            imported_template_check( ! in_array( (string) ( $node['elType'] ?? '' ), [ 'section', 'column' ], true ), 'Legacy layout node remains after native normalization.' );
            imported_template_check( ! array_key_exists( 'widget_type', $node ), 'Legacy snake-case widget_type remains after normalization.' );
            $id = (string) ( $node['id'] ?? '' );
            imported_template_check( $id !== '' && ! isset( $element_ids[ $id ] ), 'A normalized template has a missing or duplicate Elementor ID.' );
            $element_ids[ $id ] = true;
            $walk( (array) ( $node['elements'] ?? [] ) );
        }
    };
    $walk( (array) ( $record['elementor_data'] ?? [] ) );
    $ids[] = (string) ( $record['bundled_fixture_id'] ?? '' );
}
imported_template_check( count( array_unique( $ids ) ) === 155, 'Imported source IDs must be unique.' );

$catalog_response = wpae_block_library_list( new WP_REST_Request( [ 'include_imported' => 1, 'limit' => 200 ] ) );
$catalog = $catalog_response->get_data();
imported_template_check( (int) ( $catalog['count'] ?? 0 ) === 155, 'The editor library must expose all 155 plugin-local templates.' );
$catalog_ids = array_column( (array) ( $catalog['items'] ?? [] ), 'id' );
imported_template_check( count( array_unique( $catalog_ids ) ) === 155 && ! in_array( 0, $catalog_ids, true ), 'Editor catalog template identities must be unique and non-database IDs.' );

$instantiated = 0;
foreach ( $records as $record ) {
    $template_id = (string) $record['bundled_fixture_id'];
    $preview_id = 'template-' . $template_id;
    $preview_response = wpae_block_library_instantiate(
        new WP_REST_Request( [ 'mode' => 'preserve', 'instance_id' => 'editor-preview-' . $template_id ], [ 'id' => $preview_id ] )
    );
    $preview = $preview_response->get_data();
    imported_template_check( $preview_response->status === 200 && ! empty( $preview['ok'] ), 'A plugin-local template failed the editor instantiate route: ' . $template_id );
    imported_template_check( (string) ( $preview['block_id'] ?? '' ) === $preview_id && (string) ( $preview['source'] ?? '' ) === 'plugin_template', 'Template preview lost its plugin-local identity: ' . $template_id );
    $source_ids = imported_template_element_ids( (array) $record['elementor_data'] );
    $preview_ids = imported_template_element_ids( (array) ( $preview['elementor_data'] ?? [] ) );
    imported_template_check( count( $preview_ids ) === count( $source_ids ) && count( array_unique( $preview_ids ) ) === count( $preview_ids ), 'A preview instance has a missing or duplicate Elementor ID: ' . $template_id );
    imported_template_check( empty( array_intersect( $source_ids, $preview_ids ) ), 'A preview instance retained a source Elementor ID: ' . $template_id );
    $instantiated++;
}

$faq = wpae_block_library_imported_template_records( 'faq', [ 'faq' ] );
imported_template_check( count( $faq ) === 5, 'Archetype lookup should load only the five FAQ-tagged source files.' );
imported_template_check( count( array_filter( $faq, static fn( array $record ): bool => (string) ( $record['category'] ?? '' ) !== 'faq' ) ) === 0, 'Archetype lookup leaked a non-FAQ template.' );
$service_records = wpae_block_library_imported_template_records( 'services', [ 'services' ] );
$service_candidates = array_values( array_filter( $service_records, static fn( array $record ): bool => wpae_block_library_has_service_card_groups( (array) ( $record['elementor_data'] ?? [] ) ) ) );
imported_template_check( count( $service_candidates ) === 1 && ( $service_candidates[0]['bundled_fixture_id'] ?? '' ) === 'template-a40df0dcc7c5642d', 'Services retrieval should offer the imported native card section with repeated heading/body cards, while excluding unadaptable roots and whole pages.' );
imported_template_check( ( $service_candidates[0]['template_type'] ?? '' ) === 'section-services', 'Services candidate metadata lost its section archetype before the model decision.' );
imported_template_check( (int) ( $GLOBALS['template_db_write_attempts'] ?? 0 ) === 0, 'Plugin-local source loading attempted to create WordPress database records.' );

echo wp_json_encode( [ 'status' => 'passed', 'manifest_files' => count( $manifest ), 'retrievable_trees' => count( $records ), 'instantiated_previews' => $instantiated, 'faq_candidates' => count( $faq ), 'services_candidates' => count( $service_candidates ), 'source' => 'plugin files; no WordPress records created' ] ) . PHP_EOL;
