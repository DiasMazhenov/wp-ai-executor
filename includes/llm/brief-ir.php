<?php

/**
 * Small, lossless prompt representation used before any design decision.
 *
 * The parser deliberately extracts only evidence present in the prompt. It
 * does not invent audience, claims, metrics, media, or copy for missing slots.
 */

defined( 'ABSPATH' ) || exit;

const WPAE_BRIEF_IR_SCHEMA = 'wpae-brief-v1';
const WPAE_BRIEF_IR_PARSER_VERSION = 'wpae-brief-parser-v7';

function wpae_brief_ir_source_text( string $source_text ): string {
	$source_text = str_replace( [ "\r\n", "\r" ], "\n", $source_text );
	if ( function_exists( 'sanitize_textarea_field' ) ) {
		$source_text = sanitize_textarea_field( $source_text );
	}
	return trim( $source_text );
}

function wpae_brief_ir_utf8_slice( string $text, int $offset, int $length ): string {
	$offset = max( 0, min( strlen( $text ), $offset ) );
	$end = min( strlen( $text ), $offset + max( 0, $length ) );
	while ( $offset < $end && preg_match( '//u', substr( $text, 0, $offset ) ) !== 1 ) {
		$offset++;
	}
	while ( $end > $offset && preg_match( '//u', substr( $text, 0, $end ) ) !== 1 ) {
		$end--;
	}
	return substr( $text, $offset, $end - $offset );
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

function wpae_brief_ir_is_services_request( string $source_text ): bool {
	$intent_head = trim( (string) ( preg_split( '/\R/u', $source_text, 2 )[0] ?? '' ) );
	return (bool) preg_match( '/(?:^|\b)(?:блок|секци\w*|section|block)\s+(?:услуг\w*|services?)\b|^\s*(?:услуги|services?)\s*:|^\s*(?:услуга|service)\s*#?\d+\s*:/iu', $intent_head );
}

function wpae_brief_ir_archetype( string $source_text ): string {
	$intent_head = trim( (string) ( preg_split( '/\R/u', $source_text, 2 )[0] ?? '' ) );
	if ( wpae_brief_ir_is_services_request( $source_text ) ) {
		return 'services';
	}
	$explicit = [
		'hero' => '/^\s*(?:(?:создай|сделай|добавь|create|make)\s+)?(?:новый\s+)?(?:hero|хиро|обложк\w*|перв\w*\s+экран|главн\w*\s+экран)\b/iu',
		'team' => '/(?:^|\b)(?:блок|секци\w*|section|block)\s+(?:команд\w*|team)\b|^\s*(?:команда|team)\s*:/iu',
		'testimonials' => '/(?:^|\b)(?:блок|секци\w*|section|block)\s+(?:отзыв\w*|testimonials?|reviews?)\b|^\s*(?:отзывы|testimonials?|reviews?)\s*:/iu',
		'cta' => '/(?:\b(?:cta|call\s+to\s+action)\s*[-–—]?\s*(?:блок|секци\w*|block|section)\b|(?:^|\b)(?:блок|секци\w*|section|block)\s+(?:cta|call\s+to\s+action|призыв\w*\s+к\s+действи\w*)\b|^\s*(?:cta|call\s+to\s+action)\s*(?:[.:\-–—]|$)|^\s*(?:самостоятельн\w*|standalone)[^\n]{0,50}\b(?:cta|call\s+to\s+action|призыв\w*\s+к\s+действи\w*)\b)/iu',
	];
	foreach ( $explicit as $candidate => $pattern ) {
		if ( preg_match( $pattern, $intent_head ) ) {
			return $candidate;
		}
	}
	if ( function_exists( 'wpae_llm_detect_block_archetype' ) ) {
		$detected = sanitize_key( (string) wpae_llm_detect_block_archetype( $source_text ) );
		if ( in_array( $detected, [ 'hero', 'process', 'pricing', 'faq', 'benefits', 'services', 'team', 'testimonials', 'cta' ], true ) ) {
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
	$repeated = wpae_brief_ir_repeated_slot( $prefix );
	if ( $repeated['role'] !== '' ) {
		return $repeated['role'];
	}
	if ( preg_match( '/(?:заголов\w*\s+(?:секци\w*|раздел\w*)|(?:section|block)\s+(?:heading|title))\s*[:\-]?\s*$/iu', $prefix ) ) {
		return 'title';
	}
	if ( preg_match( '/призыв\w*\s+к\s+действи\w*\s*[:\-]?\s*$/iu', $prefix ) ) {
		return 'title';
	}
	if ( preg_match( '/(?:(?:описани\w*|подзаголов\w*)\s+(?:секци\w*|раздел\w*)|(?:section|block)\s+(?:description|subtitle))\s*[:\-]?\s*$/iu', $prefix ) ) {
		return 'body';
	}
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
	if ( preg_match( '/(?:alt(?:\s+text)?|альт(?:\s*текст)?)\s*[:\-]?\s*$/iu', $prefix ) ) {
		return 'media_alt';
	}
	if ( preg_match( '/(?:архитектурн\w*\s+студи\w*|бренд|brand|studio)\s*[:\-]?\s*$/iu', $prefix ) ) {
		return 'brand';
	}
	if ( preg_match( '/(?:вторичн\w*\s+)?(?:кнопк\w*|cta|button)\s*(?:\#?2)?\s*[:\-]?\s*$/iu', $prefix ) ) {
		return preg_match( '/(?:вторичн\w*|\#?2)\s+(?:кнопк\w*|cta|button)|(?:кнопк\w*|cta|button)\s*\#?2\s*[:\-]?\s*$/iu', $prefix ) ? 'cta_2' : 'cta';
	}
	if ( preg_match( '/(?:основн\w*\s+)?(?:кнопк\w*|cta|button)\s*[:\-]?\s*$/iu', $prefix ) ) {
		return 'cta';
	}
	return 'text';
}

function wpae_brief_ir_repeated_slot( string $prefix ): array {
	$prefix = trim( $prefix );
	$patterns = [
		[ 'service', 'service_title', '/(?:услуг\w*|services?)\s*\#?(\d+)\s*(?:[—–:\-]\s*)?(?:назван\w*|title|name)\s*[:\-]?\s*$/iu' ],
		[ 'service', 'service_body', '/(?:услуг\w*|services?)\s*\#?(\d+)\s*(?:[—–:\-]\s*)?(?:описан\w*|description|details?)\s*[:\-]?\s*$/iu' ],
		[ 'service', 'service_body', '/(?:услуг\w*|services?)\s*\#?(\d+)\s*(?:[—–:\-]\s*)?(?:назван\w*|title|name)\s*[:\-]?\s*(?:«[^»\r\n]{1,240}»|“[^”\r\n]{1,240}”|"[^"\r\n]{1,240}")\s*[.!]?\s*(?:описан\w*|description|details?)\s*[:\-]?\s*$/iu' ],
		[ 'service', 'service_cta', '/(?:услуг\w*|services?)\s*\#?(\d+)\s*(?:[—–:\-]\s*)?(?:кнопк\w*|ссылк\w*|cta|link)\s*[:\-]?\s*$/iu' ],
		[ 'team', 'team_name', '/(?:участник\w*|сотрудник\w*|team\s+member)\s*\#?(\d+)\s*(?:[—–:\-]\s*)?(?:имя|name)\s*[:\-]?\s*$/iu' ],
		[ 'team', 'team_position', '/(?:участник\w*|сотрудник\w*|team\s+member)\s*\#?(\d+)\s*(?:[—–:\-]\s*)?(?:(?:имя|name)\s*[:\-]?\s*(?:«[^»\r\n]{1,240}»|“[^”\r\n]{1,240}”|"[^"\r\n]{1,240}")\s*[,;—–-]\s*)?(?:должност\w*|роль|position|role)\s*[:\-]?\s*$/iu' ],
		[ 'team', 'team_bio', '/(?:участник\w*|сотрудник\w*|team\s+member)\s*\#?(\d+)\s*(?:[—–:\-]\s*)?(?:описан\w*|биограф\w*|bio|description)\s*[:\-]?\s*$/iu' ],
		[ 'testimonial', 'testimonial_quote', '/(?:отзыв|testimonial|review)\s*\#?(\d+)\s*(?:[—–:\-]\s*)?(?:текст|цитат\w*|quote|text)\s*[:\-]?\s*$/iu' ],
		[ 'testimonial', 'testimonial_author', '/(?:отзыв|testimonial|review)\s*\#?(\d+)\s*(?:[—–:\-]\s*)?(?:автор|имя|author|name)\s*[:\-]?\s*$/iu' ],
		[ 'testimonial', 'testimonial_meta', '/(?:отзыв|testimonial|review)\s*\#?(\d+)\s*(?:[—–:\-]\s*)?(?:должност\w*|компан\w*|position|company)\s*[:\-]?\s*$/iu' ],
		[ 'testimonial', 'testimonial_rating', '/(?:отзыв|testimonial|review)\s*\#?(\d+)\s*(?:[—–:\-]\s*)?(?:рейтинг|оценка|rating|score)\s*[:\-]?\s*$/iu' ],
	];
	foreach ( $patterns as [ $group_prefix, $role, $pattern ] ) {
		if ( preg_match( $pattern, $prefix, $match ) ) {
			return [ 'role' => $role, 'group_id' => $group_prefix . '_' . (int) $match[1] ];
		}
	}
	return [ 'role' => '', 'group_id' => '' ];
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
	$add_content = static function ( string $role, string $exact_text, int $start, int $length, ?string $url = null, float $confidence = 0.8, bool $required = false, bool $url_requested = false, string $id_override = '', bool $deduplicate = true, string $group_id = '' ) use ( &$content, &$seen, &$role_counts ): string {
		$exact_text = trim( $exact_text );
		if ( $exact_text === '' ) {
			return '';
		}
		$key = $role . '|' . wpae_brief_ir_normalize_text( $exact_text );
		if ( $deduplicate && isset( $seen[ $key ] ) ) {
			if ( $url !== null && $content[ $seen[ $key ] ]['url'] === null ) {
				$content[ $seen[ $key ] ]['url'] = $url;
			}
			$content[ $seen[ $key ] ]['url_requested'] = ! empty( $content[ $seen[ $key ] ]['url_requested'] ) || $url_requested;
			return (string) $content[ $seen[ $key ] ]['id'];
		}
		$index = (int) ( $role_counts[ $role ] ?? 0 );
		$role_counts[ $role ] = $index + 1;
		$id = $id_override !== '' ? sanitize_key( $id_override ) : wpae_brief_ir_id_for_role( $role, $index );
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
			'group_id' => $group_id !== '' ? sanitize_key( $group_id ) : null,
			'provenance' => [
				'source' => 'prompt',
				'source_span' => [ $start, $start + $length ],
				'parser' => WPAE_BRIEF_IR_PARSER_VERSION,
				'item_id' => $group_id !== '' ? sanitize_key( $group_id ) : null,
			],
		];
		if ( $deduplicate ) {
			$seen[ $key ] = count( $content ) - 1;
		}
		return $id;
	};

	$service_quote_spans = [];
	if ( $archetype === 'services' ) {
		$service_line_pattern = '/(?<![\p{L}\p{N}_])Услуга[ \t]+#?(\d+)[ \t]*:[ \t]*(?<title_quote>«(?<title_angle>[^»\r\n]{1,240})»|“(?<title_curly>[^”\r\n]{1,240})”|"(?<title_plain>[^"\r\n]{1,240})")[ \t]*[—–-][ \t]*(?<body_quote>«(?<body_angle>[^»\r\n]{1,400})»|“(?<body_curly>[^”\r\n]{1,400})”|"(?<body_plain>[^"\r\n]{1,400})")[.!]?(?=[ \t]*(?:Услуга[ \t]+#?\d+[ \t]*:|$)|\r?\n)/iu';
		$service_lines = [];
		preg_match_all( $service_line_pattern, $source_text, $service_lines, PREG_SET_ORDER | PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL );
		$service_numbers = [];
		foreach ( $service_lines as $service_line ) {
			$number = (int) ( $service_line[1][0] ?? 0 );
			$line_start = (int) ( $service_line[0][1] ?? 0 );
			$line_length = strlen( (string) ( $service_line[0][0] ?? '' ) );
			$title_capture = $body_capture = null;
			foreach ( [ 'title_angle', 'title_curly', 'title_plain' ] as $key ) {
				if ( isset( $service_line[ $key ][1] ) && $service_line[ $key ][1] >= 0 ) {
					$title_capture = $service_line[ $key ];
					break;
				}
			}
			foreach ( [ 'body_angle', 'body_curly', 'body_plain' ] as $key ) {
				if ( isset( $service_line[ $key ][1] ) && $service_line[ $key ][1] >= 0 ) {
					$body_capture = $service_line[ $key ];
					break;
				}
			}
			foreach ( [ 'title_quote', 'body_quote' ] as $key ) {
				if ( isset( $service_line[ $key ][1] ) && $service_line[ $key ][1] >= 0 ) {
					$quote = $service_line[ $key ];
					$service_quote_spans[] = [ (int) $quote[1], (int) $quote[1] + strlen( (string) $quote[0] ) ];
				}
			}
			if ( ! $number || ! is_array( $title_capture ) || ! is_array( $body_capture ) ) {
				continue;
			}
			if ( isset( $service_numbers[ $number ] ) ) {
				$ambiguities[] = [
					'kind' => 'duplicate_service_index',
					'value' => 'service_' . $number,
					'source_span' => [ $line_start, $line_start + $line_length ],
					'provenance' => [ 'source' => 'prompt', 'parser' => WPAE_BRIEF_IR_PARSER_VERSION ],
				];
				continue;
			}
			$service_numbers[ $number ] = true;
			$group_id = 'service_' . $number;
			$add_content( 'service_title', (string) $title_capture[0], (int) $title_capture[1], strlen( (string) $title_capture[0] ), null, 0.98, true, false, $group_id . '_title', false, $group_id );
			$add_content( 'service_body', (string) $body_capture[0], (int) $body_capture[1], strlen( (string) $body_capture[0] ), null, 0.98, true, false, $group_id . '_body', false, $group_id );
		}
		if ( preg_match_all( '/(?<![\p{L}\p{N}_])Услуга[ \t]+#?\d+[ \t]*:/iu', $source_text, $service_like_lines, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $service_like_lines[0] as $service_like_line ) {
				$line = (string) $service_like_line[0];
				$line_start = (int) $service_like_line[1];
				$complete = false;
				foreach ( $service_lines as $service_line ) {
					if ( (int) ( $service_line[0][1] ?? -1 ) === $line_start ) {
						$complete = true;
						break;
					}
				}
				if ( ! $complete ) {
					$ambiguities[] = [
						'kind' => 'incomplete_service_pair',
						'value' => trim( $line ),
						'source_span' => [ $line_start, $line_start + strlen( $line ) ],
						'provenance' => [ 'source' => 'prompt', 'parser' => WPAE_BRIEF_IR_PARSER_VERSION ],
					];
				}
			}
		}
	}

	$quote_pattern = '~«([^»]{1,500})»|“([^”]{1,500})”|"([^"]{1,500})"~su';
	$quote_matches = [];
	preg_match_all( $quote_pattern, $source_text, $quote_matches, PREG_OFFSET_CAPTURE );
	$cta_index = 0;
	$previous_quote_inner = null;
	$previous_quote_end = null;
	foreach ( $quote_matches[0] ?? [] as $match_index => $full_match ) {
		$full = (string) ( $full_match[0] ?? '' );
		$start = (int) ( $full_match[1] ?? 0 );
		$claimed_service_quote = false;
		foreach ( $service_quote_spans as [ $service_quote_start, $service_quote_end ] ) {
			if ( $start >= $service_quote_start && $start < $service_quote_end ) {
				$claimed_service_quote = true;
				break;
			}
		}
		if ( $claimed_service_quote ) {
			continue;
		}
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
		// A leading "CTA:" names the section; it is not itself a button label.
		if ( $archetype === 'cta' && $cta_index === 0 && $role === 'cta' && preg_match( '/^\s*(?:cta|call\s+to\s+action)\s*[:\-]?\s*$/iu', $prefix ) ) {
			$role = 'title';
		}
		if ( $archetype === 'cta' && $role === 'text' && $cta_index === 0 && preg_match( '/(?:\bcta\b|call\s+to\s+action|призыв\w*\s+к\s+действи\w*|cta[-\s]+секци\w*|секци\w*\s+cta)/iu', $prefix ) ) {
			$role = 'title';
		}
		$gap_from_previous = $previous_quote_end !== null ? substr( $source_text, $previous_quote_end, max( 0, $start - $previous_quote_end ) ) : '';
		$after_quote_end = $start + strlen( $full );
		$next_quote_start_for_role = isset( $quote_matches[0][ $match_index + 1 ][1] )
			? (int) $quote_matches[0][ $match_index + 1 ][1]
			: strlen( $source_text );
		$gap_to_next = substr( $source_text, $after_quote_end, max( 0, $next_quote_start_for_role - $after_quote_end ) );
		if ( $archetype === 'faq' && $role === 'text' ) {
			if ( is_string( $previous_quote_inner ) && preg_match( '/[?؟]\s*$/u', $previous_quote_inner ) && preg_match( '/^\s*[—–-]\s*$/u', $gap_from_previous ) ) {
				$role = 'faq_answer';
			} elseif ( preg_match( '/[?؟]\s*$/u', $inner ) && preg_match( '/^\s*[—–-]\s*$/u', $gap_to_next ) ) {
				$role = 'faq_question';
			}
		}
		if ( $archetype === 'benefits' && $role === 'text' ) {
			if ( preg_match( '/^\s*[—–-]\s*$/u', $gap_to_next ) ) {
				$role = 'feature_title';
			} elseif ( is_string( $previous_quote_inner ) && preg_match( '/^\s*[—–-]\s*$/u', $gap_from_previous ) ) {
				$role = 'feature_body';
			}
		}
		$repeated = wpae_brief_ir_repeated_slot( $prefix );
		$group_id = (string) ( $repeated['group_id'] ?? '' );
		$id_override = $group_id !== '' ? $group_id . '_' . preg_replace( '/^(?:service|team|testimonial)_/', '', $role ) : '';
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
		if ( $archetype === 'cta' && $role === 'text' && $url_requested && $cta_index > 0 && $cta_index < 2 ) {
			$role = 'cta';
		}
		if ( $role === 'cta' ) {
			$role = $cta_index === 0 ? 'cta' : 'cta_' . ( $cta_index + 1 );
			$cta_index++;
		}
		$required = in_array( $role, [ 'title', 'body', 'cta' ], true ) || str_starts_with( $role, 'cta_' ) || in_array( $role, [ 'service_title', 'service_body', 'team_name', 'team_position', 'testimonial_quote', 'testimonial_author' ], true );
		$confidence = $role === 'text' ? 0.62 : 0.98;
		$add_content( $role, $inner, $start, strlen( $full ), $url_requested ? $url : null, $confidence, $required, $url_requested, $id_override, $group_id === '' , $group_id );
		if ( $role === 'text' ) {
			$ambiguities[] = [
				'kind' => 'unlabeled_quote',
				'value' => trim( $inner ),
				'source_span' => [ $start, $start + strlen( $full ) ],
				'provenance' => [ 'source' => 'prompt', 'parser' => WPAE_BRIEF_IR_PARSER_VERSION ],
			];
		}
		$previous_quote_inner = $inner;
		$previous_quote_end = $start + strlen( $full );
	}
	if ( $archetype === 'cta' ) {
		$title = null;
	$first_button = null;
		foreach ( $content as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			if ( ( $item['role'] ?? '' ) === 'title' && $title === null ) {
				$title = $item;
			}
			if ( in_array( (string) ( $item['role'] ?? '' ), [ 'cta', 'cta_2' ], true ) && ( $first_button === null || (int) ( $item['source_span'][0] ?? PHP_INT_MAX ) < (int) ( $first_button['source_span'][0] ?? PHP_INT_MAX ) ) ) {
				$first_button = $item;
			}
		}
		$has_body = (bool) array_filter( $content, static fn( $item ): bool => is_array( $item ) && ( $item['role'] ?? '' ) === 'body' );
		if ( $title !== null && $first_button !== null && ! $has_body ) {
			$body_start = (int) ( $title['source_span'][1] ?? 0 );
			$body_end = (int) ( $first_button['source_span'][0] ?? $body_start );
			$body_source = substr( $source_text, $body_start, max( 0, $body_end - $body_start ) );
			$body_source = preg_replace( '/\s*(?:(?:основн\w*|вторичн\w*)\s+)?(?:кнопк\w*|button|cta)\s*[:\-]?\s*$/iu', '', $body_source ) ?? $body_source;
			$body_text = ltrim( $body_source, " \t\n\r\0\x0B.!?—–-" );
			$leading_trim = strlen( $body_source ) - strlen( $body_text );
			$body_text = rtrim( $body_text );
			if ( $body_text !== '' ) {
				$add_content( 'body', $body_text, $body_start + $leading_trim, strlen( $body_text ), null, 0.9, false );
			}
		}
	}
	if ( $archetype === 'benefits' && ! array_filter( $content, static fn( $item ): bool => ( $item['role'] ?? '' ) === 'feature_title' ) ) {
		// Accept compact unquoted lists such as "Title — description; Title — description"
		// when the brief has no typed/quoted feature fields yet.
		$feature_index = 0;
		foreach ( preg_split( '/[;\r\n]+/u', $source_text ) ?: [] as $segment ) {
			$segment = trim( (string) $segment );
			if ( preg_match( '/^(?:создай|сделай|добавь|create|make)\b[^:]{0,120}:\s*/iu', $segment ) ) {
				$segment = preg_replace( '/^(?:создай|сделай|добавь|create|make)\b[^:]{0,120}:\s*/iu', '', $segment ) ?? $segment;
			}
			if ( ! preg_match( '/^(.{2,120}?)\s+[—–-]\s+(.{2,500}?)\s*[.!]?$/u', $segment, $feature_match ) ) {
				continue;
			}
			$title = trim( (string) $feature_match[1], " \t\n\r\0\x0B:.-" );
			$body = trim( (string) $feature_match[2], " \t\n\r\0\x0B. " );
			if ( $title === '' || $body === '' ) {
				continue;
			}
			$feature_index++;
			$title_offset = strpos( $source_text, $title );
			$body_offset = strpos( $source_text, $body, $title_offset === false ? 0 : $title_offset + strlen( $title ) );
			$group_id = 'feature_' . $feature_index;
			$add_content( 'feature_title', $title, $title_offset === false ? 0 : $title_offset, strlen( $title ), null, 0.9, true, false, $group_id . '_title', false, $group_id );
			$add_content( 'feature_body', $body, $body_offset === false ? 0 : $body_offset, strlen( $body ), null, 0.9, true, false, $group_id . '_body', false, $group_id );
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

	$pricing_items = [];
	if ( $archetype === 'pricing' && function_exists( 'wpae_llm_extract_pricing_content' ) ) {
		$pricing_contract = wpae_llm_extract_pricing_content( $source_text );
		$occurrences = [];
		$find_span = static function ( string $value ) use ( $source_text, &$occurrences ): array {
			if ( $value === '' ) {
				return [ 0, 0 ];
			}
			$cursor = (int) ( $occurrences[ $value ] ?? 0 );
			$position = strpos( $source_text, $value, $cursor );
			if ( $position === false ) {
				$position = strpos( $source_text, $value );
			}
			if ( $position === false ) {
				return [ 0, 0 ];
			}
			$occurrences[ $value ] = $position + strlen( $value );
			return [ $position, $position + strlen( $value ) ];
		};
		foreach ( array_values( (array) ( $pricing_contract['items'] ?? [] ) ) as $tier_index => $tier ) {
			if ( ! is_array( $tier ) ) {
				continue;
			}
			$tier_number = $tier_index + 1;
			$label = trim( (string) ( $tier['label'] ?? '' ) );
			$price_text = trim( (string) ( $tier['price_text'] ?? '' ) );
			$description = trim( (string) ( $tier['description'] ?? '' ) );
			$cta_text = trim( (string) ( $tier['cta_text'] ?? '' ) );
			$cta_url = wpae_brief_ir_normalize_url( $tier['cta_url'] ?? '' );
			$price_amount = $price_text;
			$period_text = '';
			if ( preg_match( '/^(.*?)(\s*\/\s*[\p{L}\w]+)$/u', $price_text, $period_match ) ) {
				$price_amount = trim( (string) $period_match[1] );
				$period_text = trim( (string) $period_match[2] );
			}
			$label_span = $find_span( $label );
			$price_span = $find_span( $price_text );
			$description_span = $find_span( $description );
			$cta_span = $find_span( $cta_text );
			$refs = [
				'label_ref' => $add_content( 'pricing_label', $label, $label_span[0], $label_span[1] - $label_span[0], null, 0.98, true, false, 'pricing_' . $tier_number . '_label', false ),
				'price_ref' => $add_content( 'pricing_price', $price_amount, $price_span[0], min( strlen( $price_amount ), max( 0, $price_span[1] - $price_span[0] ) ), null, 0.98, true, false, 'pricing_' . $tier_number . '_price', false ),
				'period_ref' => $period_text !== '' ? $add_content( 'pricing_period', $period_text, $price_span[0] + max( 0, strpos( $price_text, $period_text ) ), strlen( $period_text ), null, 0.98, false, false, 'pricing_' . $tier_number . '_period', false ) : '',
				'description_ref' => $add_content( 'pricing_description', $description, $description_span[0], $description_span[1] - $description_span[0], null, 0.98, false, false, 'pricing_' . $tier_number . '_description', false ),
				'cta_ref' => $cta_text !== '' ? $add_content( 'pricing_cta', $cta_text, $cta_span[0], $cta_span[1] - $cta_span[0], $cta_url !== '' ? $cta_url : null, 0.98, false, $cta_url !== '', 'pricing_' . $tier_number . '_cta', false ) : '',
			];
			if ( $label !== '' && $price_amount !== '' ) {
				$pricing_items[] = $refs + [
					'price_text' => $price_text,
					'provenance' => [ 'source' => 'prompt', 'source_spans' => [ 'label' => $label_span, 'price' => $price_span, 'description' => $description_span, 'cta' => $cta_span ], 'parser' => WPAE_BRIEF_IR_PARSER_VERSION ],
				];
			}
		}
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
	$surface_found = (bool) preg_match( '/(?:фон|background(?:[-\s]?color)?|surface)[^\n]{0,120}?(#[0-9a-f]{6}(?:[0-9a-f]{2})?)(?![0-9a-f])/iu', $source_text, $surface_color, PREG_OFFSET_CAPTURE );
	if ( ! $surface_found && preg_match( '/(?:бел(?:ая|ый|ое)\s+поверхност\w*|чист\w*\s+бел\w*\s+поверхност\w*|white\s+surface)/iu', $source_text, $surface_color, PREG_OFFSET_CAPTURE ) ) {
		$surface_color[1] = [ '#ffffff', (int) $surface_color[0][1] ];
		$surface_found = true;
	}
	if ( $surface_found ) {
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
	if ( preg_match( '/(?:скругление|скругления|радиус|border[\s-]*radius)\D{0,40}(\d+(?:\.\d+)?)\s*(px|rem|em)?/iu', $source_text, $radius_match, PREG_OFFSET_CAPTURE ) ) {
		$radius = (float) $radius_match[1][0];
		$unit = strtolower( (string) ( $radius_match[2][0] ?? 'px' ) );
		$radius_span = [ (int) $radius_match[0][1], (int) $radius_match[0][1] + strlen( (string) $radius_match[0][0] ) ];
		$constraints[] = [ 'id' => 'border_radius', 'kind' => 'border_radius', 'value' => $radius . $unit, 'source_span' => $radius_span, 'provenance' => [ 'source' => 'prompt', 'source_span' => $radius_span, 'parser' => WPAE_BRIEF_IR_PARSER_VERSION ] ];
	}
	if ( preg_match( '/(?:тонк\w*\s+)?(?:светло[-\s]?сер\w*\s+)?(?:обводк\w*|границ\w*)|(?:light[-\s]?gr[ae]y\s+border)/iu', $source_text, $border_match, PREG_OFFSET_CAPTURE ) ) {
		$border_span = [ (int) $border_match[0][1], (int) $border_match[0][1] + strlen( (string) $border_match[0][0] ) ];
		$constraints[] = [ 'id' => 'border_color_token', 'kind' => 'border_color_token', 'value' => 'color.border', 'source_span' => $border_span, 'provenance' => [ 'source' => 'prompt', 'source_span' => $border_span, 'parser' => WPAE_BRIEF_IR_PARSER_VERSION ] ];
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
	if ( preg_match( '/(?:изображен\w*|фото|image|photo|визуальн\w*)[^\.\n]{0,60}(?:слева|left)(?:\b|\s|$)/iu', $source_text, $match, PREG_OFFSET_CAPTURE ) ) {
		$constraints[] = [ 'id' => 'media_side_left', 'kind' => 'media_side', 'value' => 'left', 'source_span' => [ (int) $match[0][1], (int) $match[0][1] + strlen( (string) $match[0][0] ) ], 'provenance' => [ 'source' => 'prompt', 'parser' => WPAE_BRIEF_IR_PARSER_VERSION ] ];
	} elseif ( preg_match( '/(?:изображен\w*|фото|image|photo|визуальн\w*)[^\.\n]{0,60}(?:справа|right)(?:\b|\s|$)/iu', $source_text, $match, PREG_OFFSET_CAPTURE ) ) {
		$constraints[] = [ 'id' => 'media_side_right', 'kind' => 'media_side', 'value' => 'right', 'source_span' => [ (int) $match[0][1], (int) $match[0][1] + strlen( (string) $match[0][0] ) ], 'provenance' => [ 'source' => 'prompt', 'parser' => WPAE_BRIEF_IR_PARSER_VERSION ] ];
	}
	if ( preg_match( '/(?:выравнив\w*|align\w*|alignment)[^\.\n]{0,40}(?:по\s+центру|центр\w*|center\w*)/iu', $source_text, $match, PREG_OFFSET_CAPTURE ) ) {
		$constraints[] = [ 'id' => 'cta_alignment_center', 'kind' => 'cta_alignment', 'value' => 'center', 'source_span' => [ (int) $match[0][1], (int) $match[0][1] + strlen( (string) $match[0][0] ) ], 'provenance' => [ 'source' => 'prompt', 'parser' => WPAE_BRIEF_IR_PARSER_VERSION ] ];
	} elseif ( preg_match( '/(?:выравнив\w*|align\w*|alignment)[^\.\n]{0,40}(?:справа|right)/iu', $source_text, $match, PREG_OFFSET_CAPTURE ) ) {
		$constraints[] = [ 'id' => 'cta_alignment_right', 'kind' => 'cta_alignment', 'value' => 'right', 'source_span' => [ (int) $match[0][1], (int) $match[0][1] + strlen( (string) $match[0][0] ) ], 'provenance' => [ 'source' => 'prompt', 'parser' => WPAE_BRIEF_IR_PARSER_VERSION ] ];
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
			if ( preg_match( '/\.(?:jpe?g|png|webp|gif|svg)(?:\?.*)?$/i', $url ) || preg_match( '#^https?://images\.unsplash\.com/photo-#i', $url ) ) {
				$prefix = wpae_brief_ir_utf8_slice( $source_text, max( 0, (int) $url_match[1] - 180 ), 180 );
				$group_id = '';
				$role = $archetype === 'hero' ? 'hero' : 'decorative';
				if ( preg_match( '/(?:участник\w*|сотрудник\w*|team\s+member)\s*\#?(\d+)[^\n]*?(?:фото|image|photo)[^\n]*$/iu', $prefix, $group_match ) ) {
					$role = 'portrait';
					$group_id = 'team_' . (int) $group_match[1];
				} elseif ( preg_match( '/(?:услуг\w*|service)\s*\#?(\d+)[^\n]*?(?:иконк\w*|изображен\w*|фото|image|icon|photo)[^\n]*$/iu', $prefix, $group_match ) ) {
					$role = 'card_image';
					$group_id = 'service_' . (int) $group_match[1];
				} elseif ( preg_match( '/(?:отзыв\w*|testimonial|review)\s*\#?(\d+)[^\n]*?(?:фото|image|photo)[^\n]*$/iu', $prefix, $group_match ) ) {
					$role = 'portrait';
					$group_id = 'testimonial_' . (int) $group_match[1];
				} elseif ( preg_match( '/(?:hero|хиро|обложк\w*|главн\w*\s+экран)[^\n]*$/iu', $prefix ) ) {
					$role = 'hero';
				}
				$alt = '';
				$local_media_text = wpae_brief_ir_utf8_slice( $source_text, max( 0, (int) $url_match[1] - 180 ), 700 );
				if ( preg_match( '/(?:alt(?:\s+text)?|альт(?:\s*текст)?)\s*[:\-]?\s*(?:«([^»]{1,300})»|"([^"]{1,300})"|“([^”]{1,300})”)/iu', $local_media_text, $alt_match ) ) {
					$alt = trim( (string) ( $alt_match[1] ?: ( $alt_match[2] ?: $alt_match[3] ) ) );
				}
				$license = '';
				if ( preg_match( '/(?:лицензи\w*|license)\s*[:\-]?\s*(?:«([^»]{1,120})»|"([^"]{1,120})"|([^\n,;]+))/iu', $local_media_text, $license_match ) ) {
					$license = trim( (string) ( $license_match[1] ?: ( $license_match[2] ?: $license_match[3] ) ) );
				}
				$attribution = '';
				if ( preg_match( '/(?:автор(?:\s+фото)?|photo\s+by|photographer)\s*[:\-]?\s*(?:«([^»]{1,160})»|"([^"]{1,160})"|([^\n,;]+))/iu', $local_media_text, $attribution_match ) ) {
					$attribution = trim( (string) ( $attribution_match[1] ?: ( $attribution_match[2] ?: $attribution_match[3] ) ) );
				}
				$allowed_reuse = $license !== '' && preg_match( '/unsplash\s+license/i', $license ) && preg_match( '#^https?://images\.unsplash\.com/#i', $url );
				$media_references[] = [
					'asset_id' => 'prompt_media_' . count( $media_references ),
					'attachment_id' => null,
					'source_url' => $url,
					'role' => $role,
					'group_id' => $group_id,
					'alt' => $alt,
					'focal_point' => null,
					'crop' => null,
					'object_fit' => 'cover',
					'license' => $license,
					'attribution' => $attribution,
					'allowed_reuse' => (bool) $allowed_reuse,
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
		'pricing_items' => array_values( $pricing_items ),
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
