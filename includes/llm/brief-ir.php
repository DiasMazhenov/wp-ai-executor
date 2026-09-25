<?php

/**
 * Small, lossless prompt representation used before any design decision.
 *
 * The parser deliberately extracts only evidence present in the prompt. It
 * does not invent audience, claims, metrics, media, or copy for missing slots.
 */

defined( 'ABSPATH' ) || exit;

const WPAE_BRIEF_IR_SCHEMA = 'wpae-brief-v1';
const WPAE_BRIEF_IR_PARSER_VERSION = 'wpae-brief-parser-v2';

function wpae_brief_ir_source_text( string $source_text ): string {
	$source_text = str_replace( [ "\r\n", "\r" ], "\n", $source_text );
	if ( function_exists( 'sanitize_textarea_field' ) ) {
		$source_text = sanitize_textarea_field( $source_text );
	}
	return trim( $source_text );
}

function wpae_brief_ir_normalize_text( string $text ): string {
	$text = trim( preg_replace( '/\s+/u', ' ', $text ) ?? $text );
	return trim( $text, " \t\n\r\0\x0B.,;:" );
}

function wpae_brief_ir_normalize_url( $value ): string {
	$value = trim( (string) $value );
	$value = trim( $value, " \t\n\r\0\x0B.,;)]}" );
	if ( $value === '' || preg_match( '/[\x00-\x1F\x7F]/', $value ) ) {
		return '';
	}
	if ( preg_match( '/^#[A-Za-z][A-Za-z0-9_:\-]*$/', $value ) || preg_match( '#^/(?!/)[^\s<>"\']+$#', $value ) ) {
		return $value;
	}
	if ( ! preg_match( '#^(?:https?://|mailto:|tel:)#i', $value ) ) {
		return '';
	}
	$safe = function_exists( 'esc_url_raw' ) ? esc_url_raw( $value ) : $value;
	$parts = function_exists( 'wp_parse_url' ) ? wp_parse_url( $safe ) : parse_url( $safe );
	if ( ! is_array( $parts ) || ! in_array( strtolower( (string) ( $parts['scheme'] ?? '' ) ), [ 'http', 'https', 'mailto', 'tel' ], true ) ) {
		return '';
	}
	if ( in_array( strtolower( (string) $parts['scheme'] ), [ 'http', 'https' ], true ) && empty( $parts['host'] ) ) {
		return '';
	}
	return $safe;
}

function wpae_brief_ir_locale( string $source_text ): string {
	if ( preg_match( '/[А-Яа-яЁё]/u', $source_text ) ) {
		return 'ru';
	}
	if ( preg_match( '/[A-Za-z]/', $source_text ) ) {
		return 'en';
	}
	return 'und';
}

function wpae_brief_ir_archetype( string $source_text ): string {
	if ( function_exists( 'wpae_llm_detect_block_archetype' ) ) {
		$detected = sanitize_key( (string) wpae_llm_detect_block_archetype( $source_text ) );
		if ( in_array( $detected, [ 'hero', 'process', 'pricing', 'faq', 'benefits' ], true ) ) {
			return $detected;
		}
	}
	if ( preg_match( '/^\s*(?:faq|accordion|аккордеон|частые\s+вопрос\w*|вопрос\w*\s+и\s+ответ\w*)\b/iu', $source_text ) ) {
		return 'faq';
	}
	if ( preg_match( '/^\s*(?:benefits?|features?|преимуществ\w*|выгод\w*)\b/iu', $source_text ) ) {
		return 'benefits';
	}
	if ( preg_match( '/\b(?:pricing|price|тариф\w*|пакет\w*|цен\w*|стоимост\w*)\b/iu', $source_text ) ) {
		return 'pricing';
	}
	if ( preg_match( '/\b(?:hero|хиро|обложк\w*|перв\w*\s+экран|главн\w*\s+экран)\b/iu', $source_text ) ) {
		return 'hero';
	}
	if ( preg_match( '/\b(?:process|steps?|timeline|процесс\w*|этап\w*|шаг\w*|таймлайн\w*)\b/iu', $source_text ) ) {
		return 'process';
	}
	if ( preg_match( '/\b(?:faq|accordion|аккордеон|частые\s+вопрос\w*|вопрос\w*\s+и\s+ответ\w*)\b/iu', $source_text ) ) {
		return 'faq';
	}
	if ( preg_match( '/\b(?:benefits?|features?|преимуществ\w*|выгод\w*)\b/iu', $source_text ) ) {
		return 'benefits';
	}
	return 'unknown';
}

function wpae_brief_ir_label_role( string $prefix ): string {
	$prefix = trim( $prefix );
	if ( preg_match( '/(?:вопрос\w*|question\w*)\s*(?:\#?\d+)?\s*[:\-]?\s*$/iu', $prefix ) ) {
		return 'faq_question';
	}
	if ( preg_match( '/(?:ответ\w*|answer\w*)\s*(?:\#?\d+)?\s*[:\-]?\s*$/iu', $prefix ) ) {
		return 'faq_answer';
	}
	if ( preg_match( '/(?:описани\w*\s+(?:преимуществ\w*|выгод\w*|features?|benefits?)|(?:description(?:\s+of)?\s+(?:features?|benefits?)|(?:features?|benefits?)\s+description))\s*(?:\#?\d+)?\s*[:\-]?\s*$/iu', $prefix ) ) {
		return 'feature_body';
	}
	if ( preg_match( '/(?:преимуществ\w*|выгод\w*|features?|benefits?)\s*(?:\#?\d+)?\s*[:\-]?\s*$/iu', $prefix ) ) {
		return 'feature_title';
	}
	if ( preg_match( '/(?:надзаголов\w*|eyebrow|overline|kicker|надпис\w*|слоган)\s*[:\-]?\s*$/iu', $prefix ) ) {
		return 'eyebrow';
	}
	if ( preg_match( '/(?:заголов\w*|heading|title|headline)\s*[:\-]?\s*$/iu', $prefix ) ) {
		return 'title';
	}
	if ( preg_match( '/(?:описани\w*|подзаголов\w*|текст|body|description|subtitle|copy)\s*[:\-]?\s*$/iu', $prefix ) ) {
		return 'body';
	}
	if ( preg_match( '/(?:архитектурн\w*\s+студи\w*|бренд|brand|studio)\s*[:\-]?\s*$/iu', $prefix ) ) {
		return 'brand';
	}
	if ( preg_match( '/(?:основн\w*\s+)?(?:кнопк\w*|cta|button)\s*[:\-]?\s*$/iu', $prefix ) ) {
		return 'cta';
	}
	return 'text';
}

function wpae_brief_ir_id_for_role( string $role, int $index = 0 ): string {
	if ( preg_match( '/^cta(?:_(\d+))?$/', $role, $match ) ) {
		$number = isset( $match[1] ) && $match[1] !== '' ? (int) $match[1] : $index + 1;
		return $number > 1 ? 'hero_cta_' . $number : 'hero_cta';
	}
	$base = [
		'brand' => 'hero_brand',
		'eyebrow' => 'hero_eyebrow',
		'title' => 'hero_title',
		'body' => 'hero_body',
		'faq_question' => 'faq_question',
		'faq_answer' => 'faq_answer',
		'feature_title' => 'feature_title',
		'feature_body' => 'feature_body',
		'label' => 'label',
		'text' => 'text',
	];
	$base = $base[ $role ] ?? 'content';
	return $index > 0 ? $base . '_' . ( $index + 1 ) : $base;
}

function wpae_brief_ir_parse( string $source_text, array $context = [] ): array {
	$source_text = wpae_brief_ir_source_text( $source_text );
	$locale = wpae_brief_ir_locale( $source_text );
	$archetype = wpae_brief_ir_archetype( $source_text );
	$content = [];
	$warnings = [];
	$ambiguities = [];
	$seen = [];
	$role_counts = [];
	$add_content = static function ( string $role, string $exact_text, int $start, int $length, ?string $url = null, float $confidence = 0.8, bool $required = false, bool $url_requested = false ) use ( &$content, &$seen, &$role_counts ): void {
		$exact_text = trim( $exact_text );
		if ( $exact_text === '' ) {
			return;
		}
		$key = $role . '|' . wpae_brief_ir_normalize_text( $exact_text );
		if ( isset( $seen[ $key ] ) ) {
			if ( $url !== null && $content[ $seen[ $key ] ]['url'] === null ) {
				$content[ $seen[ $key ] ]['url'] = $url;
			}
			$content[ $seen[ $key ] ]['url_requested'] = ! empty( $content[ $seen[ $key ] ]['url_requested'] ) || $url_requested;
			return;
		}
		$index = (int) ( $role_counts[ $role ] ?? 0 );
		$role_counts[ $role ] = $index + 1;
		$id = wpae_brief_ir_id_for_role( $role, $index );
		$content[] = [
			'id' => $id,
			'role' => $role,
			'exact_text' => $exact_text,
			'normalized_text' => wpae_brief_ir_normalize_text( $exact_text ),
			'url' => $url,
			'url_requested' => $url_requested,
			'source_span' => [ $start, $start + $length ],
			'confidence' => max( 0.0, min( 1.0, $confidence ) ),
			'required' => $required,
			'provenance' => [
				'source' => 'prompt',
				'source_span' => [ $start, $start + $length ],
				'parser' => WPAE_BRIEF_IR_PARSER_VERSION,
			],
		];
		$seen[ $key ] = count( $content ) - 1;
	};

	$quote_pattern = '~«([^»]{1,500})»|“([^”]{1,500})”|"([^"]{1,500})"~su';
	$quote_matches = [];
	preg_match_all( $quote_pattern, $source_text, $quote_matches, PREG_OFFSET_CAPTURE );
	$cta_index = 0;
	foreach ( $quote_matches[0] ?? [] as $match_index => $full_match ) {
		$full = (string) ( $full_match[0] ?? '' );
		$start = (int) ( $full_match[1] ?? 0 );
		$inner = '';
		foreach ( [ 1, 2, 3 ] as $capture_index ) {
			if ( ! empty( $quote_matches[ $capture_index ][ $match_index ][0] ) ) {
				$inner = (string) $quote_matches[ $capture_index ][ $match_index ][0];
				break;
			}
		}
		$before = substr( $source_text, 0, $start );
		$prefix = function_exists( 'mb_substr' ) ? mb_substr( $before, -100 ) : $before;
		$role = wpae_brief_ir_label_role( $prefix );
		$url = null;
		$url_requested = false;
		// Scope URL association to the structural segment between this quoted
		// value and the next quoted value. A fixed look-ahead can steal a CTA
		// target from the next card when the current description is long.
		$after_start = $start + strlen( $full );
		$next_quote_start = isset( $quote_matches[0][ $match_index + 1 ][1] )
			? (int) $quote_matches[0][ $match_index + 1 ][1]
			: strlen( $source_text );
		$after = substr( $source_text, $after_start, max( 0, $next_quote_start - $after_start ) );
		$url_pattern = '([A-Za-z][A-Za-z0-9+.\-]*:[^\s,;]+|#[A-Za-z][A-Za-z0-9_:\-]*|\/(?!\/)[^\s,;]+)';
		if ( preg_match( '/(?:ссылк\w*|url|link)\s*[:\-]?\s*' . $url_pattern . '/iu', $after, $url_match ) ) {
			$url_requested = true;
			$url = wpae_brief_ir_normalize_url( (string) $url_match[1] );
		} elseif ( preg_match( '/^\s*(?:->|→|—|-|:)\s*' . $url_pattern . '/u', $after, $url_match ) ) {
			$url_requested = true;
			$url = wpae_brief_ir_normalize_url( (string) $url_match[1] );
		}
		if ( $role === 'cta' ) {
			$role = $cta_index === 0 ? 'cta' : 'cta_' . ( $cta_index + 1 );
			$cta_index++;
		}
		$required = in_array( $role, [ 'title', 'body', 'cta' ], true ) || str_starts_with( $role, 'cta_' );
		$confidence = $role === 'text' ? 0.62 : 0.98;
		$add_content( $role, $inner, $start, strlen( $full ), $url_requested ? $url : null, $confidence, $required, $url_requested );
		if ( $role === 'text' ) {
			$ambiguities[] = [
				'kind' => 'unlabeled_quote',
				'value' => trim( $inner ),
				'source_span' => [ $start, $start + strlen( $full ) ],
				'provenance' => [ 'source' => 'prompt', 'parser' => WPAE_BRIEF_IR_PARSER_VERSION ],
			];
		}
	}

	$plain_line_offset = 0;
	foreach ( preg_split( '/\n/', $source_text ) ?: [] as $line ) {
		$trimmed = trim( preg_replace( '/^(?:[-*•]\s*)/u', '', $line ) ?? $line );
		$line_length = strlen( $line );
		if ( $trimmed !== '' && strlen( $trimmed ) <= 80 && preg_match( '/^(?:FAQ|О\s+нас|About\s+us|\d+\s+шаг\w*|[A-ZА-ЯЁ][A-ZА-ЯЁ0-9 _-]{1,40})$/u', $trimmed ) ) {
			$add_content( 'label', $trimmed, $plain_line_offset, $line_length, null, 0.76, false );
			$ambiguities[] = [
				'kind' => 'short_label',
				'value' => $trimmed,
				'source_span' => [ $plain_line_offset, $plain_line_offset + $line_length ],
				'provenance' => [ 'source' => 'prompt', 'parser' => WPAE_BRIEF_IR_PARSER_VERSION ],
			];
		}
		$plain_line_offset += $line_length + 1;
	}

	$constraints = [];
	$media_forbidden_match = [];
	$media_required_match = [];
	$media_forbidden = preg_match( '/\b(?:без\s+(?:любых?\s+)?(?:изображен\w*|фото)|(?:не\s+)?(?:добавляй|добавлять|используй|использовать|нужн\w*|требу\w*)\s+(?:изображен\w*|фото)|(?:изображен\w*|фото)\s+не\s+(?:добавляй|добавлять|используй|использовать)|no\s+(?:image|images|photo|photos)|without\s+(?:an?\s+)?(?:image|photo)|do\s+not\s+(?:add|use|include)\s+(?:an?\s+)?(?:image|photo)|don.t\s+(?:add|use|include)\s+(?:an?\s+)?(?:image|photo))\b/iu', $source_text, $media_forbidden_match, PREG_OFFSET_CAPTURE );
	$media_required = preg_match( '/\b(?:обязательн\w*\s+(?:изображен\w*|фото)|добавь\s+(?:изображен\w*|фото)|с\s+(?:изображен\w*|фото)|изображен\w*\s+(?:обязательн\w*|нужн\w*)|include\s+(?:an?\s+)?(?:image|photo)|with\s+(?:an?\s+)?(?:image|photo)|(?:image|photo)\s+required)\b/iu', $source_text, $media_required_match, PREG_OFFSET_CAPTURE );
	$media_intent = $media_forbidden && $media_required ? 'conflict' : ( $media_forbidden ? 'forbidden' : ( $media_required ? 'required' : 'unspecified' ) );
	$media_match = $media_forbidden ? $media_forbidden_match : $media_required_match;
	$media_match_span = isset( $media_match[0][0][1] ) ? [ (int) $media_match[0][0][1], (int) $media_match[0][0][1] + strlen( (string) $media_match[0][0][0] ) ] : [ 0, 0 ];
	$constraints[] = [
		'id' => 'media_intent',
		'kind' => 'media_intent',
		'value' => $media_intent,
		'source_span' => $media_match_span,
		'provenance' => [ 'source' => 'prompt', 'source_span' => $media_match_span, 'parser' => WPAE_BRIEF_IR_PARSER_VERSION ],
	];
	if ( preg_match( '/\b(?:pill(?:[-\s]?badge)?|бейдж|пилюл\w*)\b/iu', $source_text, $badge_match, PREG_OFFSET_CAPTURE ) ) {
		$badge_span = [ (int) $badge_match[0][1], (int) $badge_match[0][1] + strlen( (string) $badge_match[0][0] ) ];
		$constraints[] = [
			'id' => 'eyebrow_presentation_pill',
			'kind' => 'eyebrow_presentation',
			'value' => 'pill',
			'source_span' => $badge_span,
			'provenance' => [ 'source' => 'prompt', 'source_span' => $badge_span, 'parser' => WPAE_BRIEF_IR_PARSER_VERSION ],
		];
	}
	if ( $media_intent === 'conflict' ) {
		$ambiguities[] = [ 'kind' => 'conflicting_media_intent', 'source_span' => $media_match_span, 'provenance' => [ 'source' => 'prompt', 'parser' => WPAE_BRIEF_IR_PARSER_VERSION ] ];
	}
	$ratio_matches = [];
	if ( preg_match_all( '~\b(\d{2})\s*[/\:]\s*(\d{2})\b~u', $source_text, $ratio_matches, PREG_OFFSET_CAPTURE ) ) {
		foreach ( $ratio_matches[0] as $ratio_match ) {
			$ratio = (string) $ratio_match[0];
			$start = (int) $ratio_match[1];
			$constraints[] = [
				'id' => 'composition_' . str_replace( '/', '_', preg_replace( '/\s+/', '', $ratio ) ),
				'kind' => 'composition',
				'value' => 'split_' . str_replace( [ '/', ':' ], '_', preg_replace( '/\s+/', '', $ratio ) ),
				'source_span' => [ $start, $start + strlen( $ratio ) ],
				'provenance' => [ 'source' => 'prompt', 'parser' => WPAE_BRIEF_IR_PARSER_VERSION ],
			];
		}
	}
	$semantic_ratio = [];
	if ( preg_match( '/(?:текст|copy|контент)[^\.\n]{0,100}?(\d{2})\s*%[^\.\n]{0,100}?(?:визуаль\w*|visual|media|изображен\w*)[^\.\n]{0,100}?(\d{2})\s*%/iu', $source_text, $semantic_ratio, PREG_OFFSET_CAPTURE ) ) {
		$first = (int) ( $semantic_ratio[1][0] ?? 0 );
		$second = (int) ( $semantic_ratio[2][0] ?? 0 );
		if ( $first + $second === 100 ) {
			$start = (int) ( $semantic_ratio[0][1] ?? 0 );
			$constraints[] = [
				'id' => 'composition_semantic_' . $first . '_' . $second,
				'kind' => 'composition',
				'value' => 'split_' . $first . '_' . $second,
				'source_span' => [ $start, $start + strlen( (string) $semantic_ratio[0][0] ) ],
				'provenance' => [ 'source' => 'prompt', 'parser' => WPAE_BRIEF_IR_PARSER_VERSION ],
			];
		}
	}
	if ( empty( $semantic_ratio ) && preg_match( '/(?:визуаль\w*|visual|media|изображен\w*)[^\.\n]{0,100}?(\d{2})\s*%[^\.\n]{0,100}?(?:текст|copy|контент)[^\.\n]{0,100}?(\d{2})\s*%/iu', $source_text, $semantic_ratio, PREG_OFFSET_CAPTURE ) ) {
		$visual = (int) ( $semantic_ratio[1][0] ?? 0 );
		$copy = (int) ( $semantic_ratio[2][0] ?? 0 );
		if ( $visual + $copy === 100 ) {
			$start = (int) ( $semantic_ratio[0][1] ?? 0 );
			$constraints[] = [
				'id' => 'composition_semantic_' . $copy . '_' . $visual,
				'kind' => 'composition',
				'value' => 'split_' . $copy . '_' . $visual,
				'source_span' => [ $start, $start + strlen( (string) $semantic_ratio[0][0] ) ],
				'provenance' => [ 'source' => 'prompt', 'parser' => WPAE_BRIEF_IR_PARSER_VERSION ],
			];
		}
	}
	$surface_color = [];
	if ( preg_match( '/(?:фон|background(?:[-\s]?color)?|surface)[^\n]{0,120}?(#[0-9a-f]{6}(?:[0-9a-f]{2})?)(?![0-9a-f])/iu', $source_text, $surface_color, PREG_OFFSET_CAPTURE ) ) {
		$surface = strtolower( (string) ( $surface_color[1][0] ?? '' ) );
		$surface_start = (int) ( $surface_color[1][1] ?? 0 );
		if ( preg_match( '/^#[0-9a-f]{6}(?:[0-9a-f]{2})?$/i', $surface ) ) {
			$constraints[] = [
				'id' => 'surface_color_' . ltrim( $surface, '#' ),
				'kind' => 'surface_color',
				'value' => $surface,
				'source_span' => [ $surface_start, $surface_start + strlen( $surface ) ],
				'provenance' => [ 'source' => 'prompt', 'source_span' => [ $surface_start, $surface_start + strlen( $surface ) ], 'parser' => WPAE_BRIEF_IR_PARSER_VERSION ],
			];
		}
	}
	if ( preg_match( '/(?:мобильн\w*|mobile)[^\.\n]{0,120}(?:сначала|first)[^\.\n]{0,120}(?:текст|copy|контент)/iu', $source_text, $match, PREG_OFFSET_CAPTURE ) ) {
		$constraints[] = [
			'id' => 'responsive_copy_first',
			'kind' => 'responsive_strategy',
			'value' => 'copy_first_stack',
			'source_span' => [ (int) $match[0][1], (int) $match[0][1] + strlen( (string) $match[0][0] ) ],
			'provenance' => [ 'source' => 'prompt', 'parser' => WPAE_BRIEF_IR_PARSER_VERSION ],
		];
	}
	$style_references = [];
	if ( preg_match_all( '/(?:стиль|style|в\s+стиле|reference)\s*[:\-]?\s*([^\.\n]{2,160})/iu', $source_text, $style_matches, PREG_OFFSET_CAPTURE ) ) {
		foreach ( $style_matches[1] as $style_match ) {
			$style_references[] = [
				'value' => wpae_brief_ir_normalize_text( (string) $style_match[0] ),
				'source_span' => [ (int) $style_match[1], (int) $style_match[1] + strlen( (string) $style_match[0] ) ],
				'provenance' => [ 'source' => 'prompt', 'parser' => WPAE_BRIEF_IR_PARSER_VERSION ],
			];
		}
	}
	$media_references = [];
	if ( preg_match_all( '~https?://[^\s)\]>]+~i', $source_text, $url_matches, PREG_OFFSET_CAPTURE ) ) {
		foreach ( $url_matches[0] as $url_match ) {
			$url = trim( (string) $url_match[0], " \t\n\r.,;" );
			if ( preg_match( '/\.(?:jpe?g|png|webp|gif|svg)(?:\?.*)?$/i', $url ) ) {
				$media_references[] = [
					'asset_id' => 'prompt_media_' . count( $media_references ),
					'attachment_id' => null,
					'source_url' => $url,
					'role' => preg_match( '/hero|обложк/iu', substr( $source_text, max( 0, (int) $url_match[1] - 40 ), 40 ) ) ? 'hero' : 'decorative',
					'alt' => '',
					'focal_point' => null,
					'crop' => null,
					'object_fit' => 'cover',
					'license' => '',
					'allowed_reuse' => false,
					'provenance' => [ 'source' => 'prompt', 'source_span' => [ (int) $url_match[1], (int) $url_match[1] + strlen( $url ) ], 'parser' => WPAE_BRIEF_IR_PARSER_VERSION ],
				];
			}
		}
	}
	if ( ! empty( $media_references ) ) {
		$media_intent = $media_intent === 'forbidden' ? 'conflict' : ( $media_intent === 'unspecified' ? 'required' : $media_intent );
		foreach ( $constraints as &$constraint ) {
			if ( ( $constraint['kind'] ?? '' ) === 'media_intent' ) {
				$constraint['value'] = $media_intent;
				$constraint['provenance']['asset_refs'] = array_column( $media_references, 'asset_id' );
				break;
			}
		}
		unset( $constraint );
		if ( $media_intent === 'conflict' && ! in_array( 'conflicting_media_intent', array_column( $ambiguities, 'kind' ), true ) ) {
			$ambiguities[] = [ 'kind' => 'conflicting_media_intent', 'provenance' => [ 'source' => 'prompt', 'parser' => WPAE_BRIEF_IR_PARSER_VERSION ] ];
		}
	}
	if ( $source_text === '' ) {
		$warnings[] = 'empty_source_text';
	}
	if ( $archetype === 'unknown' ) {
		$warnings[] = 'archetype_ambiguous';
	}
	if ( empty( $content ) && $source_text !== '' ) {
		$warnings[] = 'no_explicit_content_slots';
	}
	if ( count( array_filter( $constraints, static fn( array $item ): bool => ( $item['kind'] ?? '' ) === 'composition' ) ) > 1 ) {
		$ambiguities[] = [ 'kind' => 'conflicting_compositions', 'provenance' => [ 'source' => 'prompt', 'parser' => WPAE_BRIEF_IR_PARSER_VERSION ] ];
	}
	if ( function_exists( 'wpae_reference_set_normalize' ) ) {
		$media_references = array_map( 'wpae_reference_set_normalize', $media_references );
	}

	return [
		'schema' => WPAE_BRIEF_IR_SCHEMA,
		'source_text' => $source_text,
		'locale' => $locale,
		'intent' => [
			'archetype' => $archetype,
			'goal' => sanitize_text_field( (string) ( $context['goal'] ?? '' ) ),
			'audience' => sanitize_text_field( (string) ( $context['audience'] ?? '' ) ),
		],
		'content' => array_values( $content ),
		'style_references' => array_values( $style_references ),
		'layout_constraints' => array_values( $constraints ),
		'media_references' => array_values( $media_references ),
		'ambiguities' => array_values( $ambiguities ),
		'warnings' => array_values( array_unique( $warnings ) ),
		'parser_version' => WPAE_BRIEF_IR_PARSER_VERSION,
		'provenance' => [
			'source' => 'prompt',
			'source_span' => [ 0, strlen( $source_text ) ],
			'parser' => WPAE_BRIEF_IR_PARSER_VERSION,
		],
	];
}

function wpae_brief_ir_validate( array $brief ): array {
	$errors = [];
	if ( ( $brief['schema'] ?? '' ) !== WPAE_BRIEF_IR_SCHEMA ) {
		$errors[] = 'schema';
	}
	if ( ! is_string( $brief['source_text'] ?? null ) || ! is_string( $brief['locale'] ?? null ) ) {
		$errors[] = 'source_or_locale';
	}
	foreach ( (array) ( $brief['content'] ?? [] ) as $index => $item ) {
		if ( ! is_array( $item ) || trim( (string) ( $item['exact_text'] ?? '' ) ) === '' || ! is_array( $item['source_span'] ?? null ) || ! is_array( $item['provenance'] ?? null ) ) {
			$errors[] = 'content_' . (int) $index;
		}
		if ( is_array( $item ) && ! empty( $item['url_requested'] ) && trim( (string) ( $item['url'] ?? '' ) ) === '' ) {
			$errors[] = 'content_' . (int) $index . '_invalid_explicit_url';
		}
	}
	return [ 'ok' => empty( $errors ), 'errors' => array_values( $errors ) ];
}

function wpae_brief_ir_hash( array $brief ): string {
	return hash( 'sha256', (string) wp_json_encode( $brief, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION ) );
}
