<?php
/** Exercises the plugin-local imported-template loader and real Elementor normalizer. */
define( 'ABSPATH', __DIR__ . '/' );

class WP_Error {
    public $code;
    public $message;
    public $data;
    public function __construct( $code, $message, $data = [] ) { $this->code = $code; $this->message = $message; $this->data = $data; }
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
function wpae_get_design_system_id(): string { return 'test'; }
function wpae_get_design_system_required_classes(): array { return [ 'wpae-system-test' ]; }
function wpae_get_enforceable_skill_rules(): array { return []; }
function wp_insert_post( $post, $wp_error = false ) { $GLOBALS['template_db_write_attempts'] = (int) ( $GLOBALS['template_db_write_attempts'] ?? 0 ) + 1; return new WP_Error( 'unexpected_db_write', 'Plugin-local template loading must not insert WordPress posts.' ); }
function imported_template_check( bool $condition, string $message ): void {
    if ( ! $condition ) {
        throw new RuntimeException( $message );
    }
}

require __DIR__ . '/../includes/elementor/normalize.php';
require __DIR__ . '/../includes/elementor/validation-rules.php';
require __DIR__ . '/../includes/elementor/data.php';
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

$faq = wpae_block_library_imported_template_records( 'faq', [ 'faq' ] );
imported_template_check( count( $faq ) === 5, 'Archetype lookup should load only the five FAQ-tagged source files.' );
imported_template_check( count( array_filter( $faq, static fn( array $record ): bool => (string) ( $record['category'] ?? '' ) !== 'faq' ) ) === 0, 'Archetype lookup leaked a non-FAQ template.' );
imported_template_check( (int) ( $GLOBALS['template_db_write_attempts'] ?? 0 ) === 0, 'Plugin-local source loading attempted to create WordPress database records.' );

echo wp_json_encode( [ 'status' => 'passed', 'manifest_files' => count( $manifest ), 'retrievable_trees' => count( $records ), 'faq_candidates' => count( $faq ), 'source' => 'plugin files; no WordPress records created' ] ) . PHP_EOL;
