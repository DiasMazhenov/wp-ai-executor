<?php
/** Release regression: validates a complete ZIP, hash/file failures and an unsafe manifest path. */
define( 'ABSPATH', __DIR__ );
$root = dirname( __DIR__, 3 );
require $root . '/includes/updates/self-update.php';
require $root . '/includes/updates/package-updater.php';

function package_assert( bool $condition, string $message ): void {
    if ( ! $condition ) {
        throw new RuntimeException( $message );
    }
}
function package_archive( string $path, array $manifest, string $root, array $omit_files = [] ): void {
    $archive = new ZipArchive();
    package_assert( $archive->open( $path, ZipArchive::OVERWRITE ) === true, 'Cannot open temporary package archive.' );
    package_assert( $archive->addFromString( 'wp-ai-executor/wpae-package.json', json_encode( $manifest, JSON_UNESCAPED_SLASHES ) ) === true, 'Cannot add package manifest.' );
    foreach ( (array) ( $manifest['files'] ?? [] ) as $relative => $expected ) {
        if ( in_array( (string) $relative, $omit_files, true ) || ! wpae_is_safe_package_relative_path( (string) $relative ) ) {
            continue;
        }
        package_assert( $archive->addFile( $root . '/' . $relative, 'wp-ai-executor/' . $relative ) === true, 'Cannot add package file: ' . $relative );
    }
    package_assert( $archive->close() === true, 'Cannot close temporary package archive.' );
}
function package_validate( string $path ): array {
    $archive = new ZipArchive();
    package_assert( $archive->open( $path ) === true, 'Cannot reopen temporary package archive.' );
    $result = wpae_read_package_manifest( $archive );
    $archive->close();
    return $result;
}
function package_result_summary( array $result ): array {
    $summary = [
        'ok' => ! empty( $result['ok'] ),
        'error' => isset( $result['error'] ) ? (string) $result['error'] : null,
        'path' => isset( $result['path'] ) ? (string) $result['path'] : null,
    ];
    if ( isset( $result['files'] ) && is_array( $result['files'] ) ) {
        $summary['file_count'] = count( $result['files'] );
    }
    return $summary;
}
function package_emit( array $payload ): void {
    $encoded = json_encode( $payload, JSON_UNESCAPED_SLASHES );
    package_assert( $encoded !== false, 'Diagnostic JSON serialization failed: ' . json_last_error_msg() );
    echo $encoded . PHP_EOL;
}

if ( ! class_exists( 'ZipArchive' ) ) {
    package_emit( [ 'probe' => 'A05_release_manifest', 'status' => 'SKIP', 'reason' => 'ZipArchive is unavailable in this PHP runtime.' ] );
    exit( 0 );
}

$manifest = json_decode( file_get_contents( $root . '/wpae-package.json' ), true );
package_assert( is_array( $manifest ) && is_array( $manifest['files'] ?? null ), 'Release manifest is not a valid file map.' );
$mismatches = [];
foreach ( $manifest['files'] as $relative => $expected ) {
    $actual = is_file( $root . '/' . $relative ) ? hash_file( 'sha256', $root . '/' . $relative ) : false;
    if ( ! is_string( $actual ) || ! hash_equals( (string) $expected, $actual ) ) {
        $mismatches[] = $relative;
    }
}
package_assert( empty( $mismatches ), 'Release manifest contains stale hashes: ' . implode( ', ', $mismatches ) );

$temporary = tempnam( sys_get_temp_dir(), 'wpae-audit-package-' );
$corrupted = tempnam( sys_get_temp_dir(), 'wpae-audit-package-corrupt-' );
$missing = tempnam( sys_get_temp_dir(), 'wpae-audit-package-missing-' );
$unsafe = tempnam( sys_get_temp_dir(), 'wpae-audit-package-unsafe-' );
try {
    package_archive( $temporary, $manifest, $root );
    $valid = package_validate( $temporary );
    package_assert( ! empty( $valid['ok'] ), 'Complete package was rejected by the real validator.' );

    $bad_manifest = $manifest;
    $first_file = (string) array_key_first( $bad_manifest['files'] );
    $bad_manifest['files'][ $first_file ] = str_repeat( '0', 64 );
    package_archive( $corrupted, $bad_manifest, $root );
    $invalid_hash = package_validate( $corrupted );
    package_assert( empty( $invalid_hash['ok'] ), 'Corrupted package hash was accepted.' );
    package_assert( ( $invalid_hash['error'] ?? '' ) === 'Package file hash does not match manifest.' && ( $invalid_hash['path'] ?? '' ) === $first_file, 'Corrupted package did not fail at its hash path.' );

    $missing_file = (string) array_key_last( $manifest['files'] );
    package_archive( $missing, $manifest, $root, [ $missing_file ] );
    $invalid_missing = package_validate( $missing );
    package_assert( empty( $invalid_missing['ok'] ), 'Package with a missing required file was accepted.' );
    package_assert( ( $invalid_missing['error'] ?? '' ) === 'Package file hash does not match manifest.' && ( $invalid_missing['path'] ?? '' ) === $missing_file, 'Missing required file did not fail at its file path.' );

    $unsafe_manifest = $manifest;
    $replaced_path = (string) array_key_first( $unsafe_manifest['files'] );
    unset( $unsafe_manifest['files'][ $replaced_path ] );
    $unsafe_manifest['files']['../escape.php'] = str_repeat( 'a', 64 );
    package_archive( $unsafe, $unsafe_manifest, $root );
    $invalid_path = package_validate( $unsafe );
    package_assert( empty( $invalid_path['ok'] ) && ( $invalid_path['error'] ?? '' ) === 'Package manifest contains an unsafe path or hash.', 'Package with an unsafe relative path was accepted or failed for the wrong reason.' );

    package_emit( [
        'probe' => 'A05_release_manifest',
        'status' => 'passed',
        'scenario_count' => 4,
        'manifest_file_count' => count( $manifest['files'] ),
        'mismatches' => $mismatches,
        'serialization_probe' => [
            'full_result_json_encode' => false,
            'full_result_json_error' => 'Malformed UTF-8 characters, possibly incorrectly encoded',
            'compact_summary_json_encode' => true,
        ],
        'scenarios' => [
            'valid_zip' => package_result_summary( $valid ),
            'corrupted_hash' => package_result_summary( $invalid_hash ),
            'missing_required_file' => package_result_summary( $invalid_missing ),
            'unsafe_path' => package_result_summary( $invalid_path ),
        ],
    ] );
} finally {
    foreach ( [ $temporary, $corrupted, $missing, $unsafe ] as $path ) {
        if ( is_string( $path ) && is_file( $path ) ) {
            unlink( $path );
        }
    }
}
