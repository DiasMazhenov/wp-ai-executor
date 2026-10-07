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
imported_template_check( count( $manifest ) === 158, 'The plugin manifest must include all 158 imported JSON files.' );
$records = wpae_block_library_imported_template_records( '', [] );
imported_template_check( count( $records ) === 156, 'Only the 156 exports with Elementor content trees should enter retrieval.' );
$profile = wpae_block_library_prompt_structure( [
	[ 'elType' => 'container', 'settings' => [ 'flex_direction' => 'row', '_css_classes' => 'must-not-leak' ], 'id' => 'private-root-id', 'elements' => [
		[ 'elType' => 'widget', 'widgetType' => 'image', 'id' => 'private-image-id', 'settings' => [ 'title' => 'Private template copy' ] ],
		[ 'elType' => 'container', 'settings' => [ 'flex_direction' => 'column' ], 'elements' => [ [ 'elType' => 'widget', 'widgetType' => 'heading' ] ] ],
	] ],
] );
imported_template_check( ( $profile[0]['container'] ?? '' ) === 'row' && ( $profile[0]['children'][0] ?? '' ) === 'image' && ( $profile[0]['children'][1]['container'] ?? '' ) === 'column' && ! str_contains( wp_json_encode( $profile ), 'private-root-id' ) && ! str_contains( wp_json_encode( $profile ), 'Private template copy' ), 'The selection profile summarizes widget/layout topology without copying IDs, classes, or template text.' );

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
            if ( ( $node['elType'] ?? '' ) === 'container' ) {
                imported_template_check( ( $node['settings']['container_type'] ?? '' ) === 'flex', 'A saved library recipe must be exposed as a native Flex container after normalization.' );
            }
            foreach ( array_keys( (array) ( $node['settings'] ?? [] ) ) as $setting_key ) {
                imported_template_check( ! preg_match( '/^_?grid(?:_|$)/', (string) $setting_key ), 'A saved library recipe retained a Grid-only control after normalization.' );
            }
            $id = (string) ( $node['id'] ?? '' );
            imported_template_check( $id !== '' && ! isset( $element_ids[ $id ] ), 'A normalized template has a missing or duplicate Elementor ID.' );
            $element_ids[ $id ] = true;
            $walk( (array) ( $node['elements'] ?? [] ) );
        }
    };
    $walk( (array) ( $record['elementor_data'] ?? [] ) );
    $ids[] = (string) ( $record['bundled_fixture_id'] ?? '' );
}
imported_template_check( count( array_unique( $ids ) ) === 156, 'Imported source IDs must be unique.' );

$catalog_response = wpae_block_library_list( new WP_REST_Request( [ 'include_imported' => 1, 'limit' => 200 ] ) );
$catalog = $catalog_response->get_data();
imported_template_check( (int) ( $catalog['count'] ?? 0 ) === 156, 'The editor library must expose all 156 plugin-local templates.' );
$catalog_ids = array_column( (array) ( $catalog['items'] ?? [] ), 'id' );
imported_template_check( count( array_unique( $catalog_ids ) ) === 156 && ! in_array( 0, $catalog_ids, true ), 'Editor catalog template identities must be unique and non-database IDs.' );

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
$mega_menu_categories = wpae_block_library_retrieval_categories( 'mega_menu' );
$mega_menu = wpae_block_library_imported_template_records( 'mega_menu', $mega_menu_categories );
imported_template_check( in_array( 'mega_menu', $mega_menu_categories, true ) && count( $mega_menu ) === 8 && count( array_filter( $mega_menu, static fn( array $record ): bool => (string) ( $record['category'] ?? '' ) !== 'mega_menu' ) ) === 0, 'Mega Menu retrieval should load all eight imported templates with the canonical underscore category.' );
$service_records = wpae_block_library_imported_template_records( 'services', [ 'services' ] );
$service_candidates = array_values( array_filter( $service_records, static fn( array $record ): bool => wpae_block_library_has_service_card_groups( (array) ( $record['elementor_data'] ?? [] ) ) ) );
imported_template_check( count( $service_candidates ) === 2 && in_array( 'template-a40df0dcc7c5642d', array_column( $service_candidates, 'bundled_fixture_id' ), true ) && in_array( 'template-services-photo-cards-v1', array_column( $service_candidates, 'bundled_fixture_id' ), true ), 'Services retrieval should offer both compatible plugin-local card sections while excluding unadaptable roots and whole pages.' );
$photo_services = null;
foreach ( $service_candidates as $candidate ) {
    if ( ( $candidate['bundled_fixture_id'] ?? '' ) === 'template-services-photo-cards-v1' ) {
        $photo_services = $candidate;
        break;
    }
}
imported_template_check( is_array( $photo_services ) && ( $photo_services['template_type'] ?? '' ) === 'section-services', 'User-corrected Services template lost its section archetype before the model decision.' );
$photo_widgets = [];
$photo_copy = [];
$photo_images = [];
$collect_photo_services = static function ( array $nodes ) use ( &$collect_photo_services, &$photo_widgets, &$photo_copy, &$photo_images ): void {
    foreach ( $nodes as $node ) {
        if ( ! is_array( $node ) ) {
            continue;
        }
        $widget_type = (string) ( $node['widgetType'] ?? '' );
        if ( $widget_type !== '' ) {
            $photo_widgets[] = $widget_type;
        }
        $settings = (array) ( $node['settings'] ?? [] );
        if ( $widget_type === 'heading' ) {
            $photo_copy[] = (string) ( $settings['title'] ?? '' );
        } elseif ( $widget_type === 'text-editor' ) {
            $photo_copy[] = trim( wp_strip_all_tags( (string) ( $settings['editor'] ?? '' ) ) );
        } elseif ( $widget_type === 'image' ) {
            $photo_images[] = (array) ( $settings['image'] ?? [] );
        }
        $collect_photo_services( (array) ( $node['elements'] ?? [] ) );
    }
};
$collect_photo_services( (array) ( $photo_services['elementor_data'] ?? [] ) );
imported_template_check( count( array_filter( $photo_widgets, static fn( string $type ): bool => $type === 'image' ) ) === 3 && count( array_filter( $photo_widgets, static fn( string $type ): bool => $type === 'icon' ) ) === 3 && count( array_filter( $photo_widgets, static fn( string $type ): bool => $type === 'heading' ) ) === 5 && count( array_filter( $photo_widgets, static fn( string $type ): bool => $type === 'text-editor' ) ) === 3, 'User-corrected Services recipe keeps the native badge/title, three image/icon/title/body cards, and no extra widgets.' );
imported_template_check( in_array( 'УСЛУГИ', $photo_copy, true ) && in_array( 'Наши услуги', $photo_copy, true ) && count( array_filter( $photo_copy, static fn( string $value ): bool => $value === 'Название услуги' ) ) === 3 && count( array_filter( $photo_copy, static fn( string $value ): bool => $value === 'Описание услуги' ) ) === 3, 'User-corrected Services recipe preserves its badge and heading with reusable empty-content slots.' );
imported_template_check( count( $photo_images ) === 3 && count( array_filter( $photo_images, static fn( array $image ): bool => str_contains( (string) ( $image['url'] ?? '' ), 'images.unsplash.com/' ) && trim( (string) ( $image['alt'] ?? '' ) ) !== '' ) ) === 3, 'All three bundled photo slots retain their Unsplash URLs and alt text.' );
imported_template_check( (int) ( $GLOBALS['template_db_write_attempts'] ?? 0 ) === 0, 'Plugin-local source loading attempted to create WordPress database records.' );

echo wp_json_encode( [ 'status' => 'passed', 'manifest_files' => count( $manifest ), 'retrievable_trees' => count( $records ), 'instantiated_previews' => $instantiated, 'faq_candidates' => count( $faq ), 'services_candidates' => count( $service_candidates ), 'source' => 'plugin files; no WordPress records created' ] ) . PHP_EOL;
