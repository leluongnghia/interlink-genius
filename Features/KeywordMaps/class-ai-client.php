<?php
/**
 * AI Client - Bộ kết nối đa nhà cung cấp AI cho tính năng sinh biến thể từ khóa.
 *
 * Hỗ trợ các nhà cung cấp:
 *  - Google Gemini (gemini-1.5-flash, gemini-2.0-flash, ...)
 *  - OpenAI (gpt-4o, gpt-4o-mini, gpt-3.5-turbo, ...)
 *  - Grok / xAI (grok-3, grok-3-mini, ...)
 *
 * @package    InterLinkGenius
 * @subpackage InterLinkGenius\KeywordMaps
 */

namespace InterLinkGenius\Features\KeywordMaps;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * AI_Client class.
 *
 * Bộ kết nối đa nhà cung cấp AI để sinh biến thể từ khóa.
 */
class AI_Client {

	/**
	 * Tên tùy chọn lưu cài đặt AI trong WordPress options.
	 */
	const OPTION_KEY = 'interlink_genius_ai_settings';

	/**
	 * Danh sách nhà cung cấp AI được hỗ trợ và model mặc định.
	 */
	const PROVIDERS = [
		'gemini' => [
			'label'         => 'Google Gemini',
			'default_model' => 'gemini-2.0-flash',
			'models'        => [ 'gemini-2.0-flash', 'gemini-2.0-flash-lite', 'gemini-1.5-flash', 'gemini-1.5-pro' ],
		],
		'openai' => [
			'label'         => 'OpenAI (ChatGPT)',
			'default_model' => 'gpt-4o-mini',
			'models'        => [ 'gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo', 'gpt-3.5-turbo' ],
		],
		'grok'   => [
			'label'         => 'Grok (xAI)',
			'default_model' => 'grok-4.3',
			'models'        => [ 'grok-4.3', 'grok-3', 'grok-3-mini', 'grok-2-1212' ],
		],
	];

	/**
	 * Cài đặt AI hiện tại.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->settings = $this->get_settings();
	}

	// =========================================================================
	// Public API
	// =========================================================================

	/**
	 * Sinh biến thể từ khóa thông qua nhà cung cấp AI đã cấu hình.
	 *
	 * @param string $keyword Từ khóa gốc.
	 * @param string $context Ngữ cảnh tùy chọn (mô tả, tiêu đề bài viết, ...).
	 * @return array|WP_Error Mảng phân loại các biến thể hoặc WP_Error.
	 */
	public function generate_keyword_variations( $keyword, $context = '' ) {
		$keyword = trim( $keyword );
		if ( empty( $keyword ) ) {
			return new WP_Error( 'empty_keyword', __( 'Từ khóa không được để trống.', 'interlink-genius' ) );
		}

		$provider = $this->settings['provider'] ?? 'gemini';
		$api_key  = $this->settings['api_key'] ?? '';
		$model    = $this->settings['model'] ?? self::PROVIDERS[ $provider ]['default_model'];

		if ( empty( $api_key ) ) {
			return new WP_Error(
				'no_api_key',
				sprintf(
					/* translators: %s: tên nhà cung cấp AI */
					__( 'Chưa nhập API Key cho %s. Vui lòng vào Cài đặt InterLink Genius để cấu hình.', 'interlink-genius' ),
					self::PROVIDERS[ $provider ]['label'] ?? $provider
				)
			);
		}

		$prompt = $this->build_prompt( $keyword, $context );

		switch ( $provider ) {
			case 'openai':
				$raw = $this->call_openai( $api_key, $model, $prompt );
				break;
			case 'grok':
				$raw = $this->call_grok( $api_key, $model, $prompt );
				break;
			case 'gemini':
			default:
				$raw = $this->call_gemini( $api_key, $model, $prompt );
				break;
		}

		if ( is_wp_error( $raw ) ) {
			return $raw;
		}

		return $this->parse_response( $raw );
	}

	/**
	 * Kiểm tra xem AI có sẵn sàng hay không.
	 *
	 * @return bool
	 */
	public function is_available() {
		return ! empty( $this->settings['api_key'] );
	}

	/**
	 * Lấy danh sách nhà cung cấp và model có sẵn.
	 *
	 * @return array
	 */
	public static function get_providers() {
		return self::PROVIDERS;
	}

	/**
	 * Lấy cài đặt AI từ database.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$defaults = [
			'provider' => 'gemini',
			'model'    => 'gemini-2.0-flash',
			'api_key'  => '',
		];

		$saved = get_option( self::OPTION_KEY, [] );
		return wp_parse_args( $saved, $defaults );
	}

	/**
	 * Lưu cài đặt AI vào database.
	 *
	 * @param array $settings Cài đặt cần lưu.
	 * @return bool
	 */
	public static function save_settings( $settings ) {
		$provider = sanitize_text_field( $settings['provider'] ?? 'gemini' );

		// Đảm bảo provider hợp lệ.
		if ( ! array_key_exists( $provider, self::PROVIDERS ) ) {
			$provider = 'gemini';
		}

		$provider_config = self::PROVIDERS[ $provider ];
		$model           = sanitize_text_field( $settings['model'] ?? $provider_config['default_model'] );

		// Đảm bảo model hợp lệ.
		if ( ! in_array( $model, $provider_config['models'], true ) ) {
			$model = $provider_config['default_model'];
		}

		$data = [
			'provider' => $provider,
			'model'    => $model,
			'api_key'  => sanitize_text_field( $settings['api_key'] ?? '' ),
		];

		return update_option( self::OPTION_KEY, $data );
	}

	// =========================================================================
	// Prompt Builder
	// =========================================================================

	/**
	 * Xây dựng prompt gửi cho AI.
	 *
	 * @param string $keyword Từ khóa gốc.
	 * @param string $context Ngữ cảnh tùy chọn.
	 * @return string
	 */
	private function build_prompt( $keyword, $context = '' ) {
		$context_line = ! empty( $context )
			? "Context/Description: {$context}\n"
			: '';

		return <<<PROMPT
You are an SEO expert helping to generate keyword variations for internal linking.

Given the keyword: "{$keyword}"
{$context_line}
Generate keyword variations in the following categories. Return ONLY a valid JSON object with no extra text.

Required JSON format:
{
  "synonyms": ["variation1", "variation2", ...],
  "related_phrases": ["phrase1", "phrase2", ...],
  "long_tail_variations": ["long tail 1", "long tail 2", ...],
  "common_misspellings": ["mispeling1", "mispeling2", ...]
}

Rules:
- Generate 3-5 items per category.
- Keep variations relevant to the original keyword.
- Long-tail variations should be 3+ words.
- Common misspellings should be realistic typos people make.
- Return ONLY the JSON object, no markdown, no explanation.
PROMPT;
	}

	// =========================================================================
	// Provider Callers
	// =========================================================================

	/**
	 * Gọi Google Gemini API.
	 *
	 * @param string $api_key API Key.
	 * @param string $model   Model ID.
	 * @param string $prompt  Prompt.
	 * @return string|WP_Error Nội dung phản hồi hoặc lỗi.
	 */
	private function call_gemini( $api_key, $model, $prompt ) {
		$endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$api_key}";

		$body = wp_json_encode( [
			'contents' => [
				[
					'parts' => [
						[ 'text' => $prompt ],
					],
				],
			],
			'generationConfig' => [
				'responseMimeType' => 'application/json',
				'temperature'      => 0.7,
				'maxOutputTokens'  => 1024,
			],
		] );

		$response = wp_remote_post( $endpoint, [
			'timeout' => 30,
			'headers' => [
				'Content-Type' => 'application/json',
			],
			'body'    => $body,
		] );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'api_request_failed', $response->get_error_message() );
		}

		$status = wp_remote_retrieve_response_code( $response );
		$data   = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 401 === $status || 403 === $status ) {
			return new WP_Error( 'auth_failed', __( 'API Key Gemini không hợp lệ hoặc không có quyền truy cập.', 'interlink-genius' ) );
		}

		if ( 429 === $status ) {
			return new WP_Error( 'rate_limited', __( 'Đã vượt quá giới hạn tần suất của Gemini API. Vui lòng thử lại sau.', 'interlink-genius' ) );
		}

		if ( $status < 200 || $status >= 300 ) {
			$msg = $data['error']['message'] ?? __( 'Lỗi không xác định từ Gemini API.', 'interlink-genius' );
			return new WP_Error( 'api_error', $msg );
		}

		return $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
	}

	/**
	 * Gọi OpenAI API.
	 *
	 * @param string $api_key API Key.
	 * @param string $model   Model ID.
	 * @param string $prompt  Prompt.
	 * @return string|WP_Error
	 */
	private function call_openai( $api_key, $model, $prompt ) {
		$endpoint = 'https://api.openai.com/v1/chat/completions';

		$body = wp_json_encode( [
			'model'    => $model,
			'messages' => [
				[
					'role'    => 'system',
					'content' => 'You are an SEO expert. Always respond with valid JSON only.',
				],
				[
					'role'    => 'user',
					'content' => $prompt,
				],
			],
			'temperature'      => 0.7,
			'max_tokens'       => 1024,
			'response_format'  => [ 'type' => 'json_object' ],
		] );

		$response = wp_remote_post( $endpoint, [
			'timeout' => 30,
			'headers' => [
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $api_key,
			],
			'body'    => $body,
		] );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'api_request_failed', $response->get_error_message() );
		}

		$status = wp_remote_retrieve_response_code( $response );
		$data   = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 401 === $status ) {
			return new WP_Error( 'auth_failed', __( 'API Key OpenAI không hợp lệ.', 'interlink-genius' ) );
		}

		if ( 429 === $status ) {
			return new WP_Error( 'rate_limited', __( 'Đã vượt quá giới hạn tần suất của OpenAI API. Vui lòng thử lại sau.', 'interlink-genius' ) );
		}

		if ( 402 === $status ) {
			return new WP_Error( 'insufficient_credits', __( 'Tài khoản OpenAI không đủ tín dụng.', 'interlink-genius' ) );
		}

		if ( $status < 200 || $status >= 300 ) {
			$msg = $data['error']['message'] ?? __( 'Lỗi không xác định từ OpenAI API.', 'interlink-genius' );
			return new WP_Error( 'api_error', $msg );
		}

		return $data['choices'][0]['message']['content'] ?? '';
	}

	/**
	 * Gọi Grok (xAI) API.
	 * Grok tương thích với định dạng OpenAI API.
	 *
	 * @param string $api_key API Key.
	 * @param string $model   Model ID.
	 * @param string $prompt  Prompt.
	 * @return string|WP_Error
	 */
	private function call_grok( $api_key, $model, $prompt ) {
		$endpoint = 'https://api.x.ai/v1/chat/completions';

		$body = wp_json_encode( [
			'model'    => $model,
			'messages' => [
				[
					'role'    => 'system',
					'content' => 'You are an SEO expert. Always respond with valid JSON only.',
				],
				[
					'role'    => 'user',
					'content' => $prompt,
				],
			],
			'temperature' => 0.7,
			'max_tokens'  => 1024,
		] );

		$response = wp_remote_post( $endpoint, [
			'timeout' => 30,
			'headers' => [
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $api_key,
			],
			'body'    => $body,
		] );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'api_request_failed', $response->get_error_message() );
		}

		$status = wp_remote_retrieve_response_code( $response );
		$data   = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 401 === $status || 403 === $status ) {
			return new WP_Error( 'auth_failed', __( 'API Key Grok (xAI) không hợp lệ.', 'interlink-genius' ) );
		}

		if ( 429 === $status ) {
			return new WP_Error( 'rate_limited', __( 'Đã vượt quá giới hạn tần suất của Grok API. Vui lòng thử lại sau.', 'interlink-genius' ) );
		}

		if ( $status < 200 || $status >= 300 ) {
			$msg = $data['error']['message'] ?? __( 'Lỗi không xác định từ Grok API.', 'interlink-genius' );
			return new WP_Error( 'api_error', $msg );
		}

		return $data['choices'][0]['message']['content'] ?? '';
	}

	// =========================================================================
	// Response Parser
	// =========================================================================

	/**
	 * Phân tích chuỗi JSON phản hồi từ AI thành mảng phân loại biến thể.
	 *
	 * @param string $raw_text Nội dung thô từ API.
	 * @return array|WP_Error
	 */
	private function parse_response( $raw_text ) {
		// Xóa markdown code block nếu có (```json ... ```).
		$cleaned = preg_replace( '/^```(?:json)?\s*/i', '', trim( $raw_text ) );
		$cleaned = preg_replace( '/\s*```$/i', '', $cleaned );

		$data = json_decode( $cleaned, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error(
				'invalid_response',
				sprintf(
					__( 'Phản hồi từ AI không hợp lệ (không phải JSON). Phản hồi nhận được: %s', 'interlink-genius' ),
					substr( $raw_text, 0, 200 )
				)
			);
		}

		$categories = [ 'synonyms', 'related_phrases', 'long_tail_variations', 'common_misspellings' ];
		$result     = [];

		foreach ( $categories as $category ) {
			if ( isset( $data[ $category ] ) && is_array( $data[ $category ] ) ) {
				$result[ $category ] = array_values( array_filter(
					array_map( 'sanitize_text_field', $data[ $category ] )
				) );
			} else {
				$result[ $category ] = [];
			}
		}

		// Kiểm tra ít nhất một danh mục có dữ liệu.
		$has_data = array_reduce( $result, fn( $carry, $items ) => $carry || ! empty( $items ), false );
		if ( ! $has_data ) {
			return new WP_Error( 'empty_response', __( 'AI không sinh ra biến thể nào. Vui lòng thử lại.', 'interlink-genius' ) );
		}

		return $result;
	}
}
