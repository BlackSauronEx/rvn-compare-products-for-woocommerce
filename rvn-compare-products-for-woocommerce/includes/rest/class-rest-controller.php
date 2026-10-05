<?php
/**
 * REST API списков сравнения.
 *
 * @package RVN_Compare
 */

namespace RVN_Compare\Rest;

use RVN_Compare\Compare_Service;
use RVN_Compare\Settings;
use RVN_Compare\Storage;
use RVN_Compare\Table_Data;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Маршруты rvn-compare/v1 для работы со списком сравнения.
 *
 * Чтение доступно всем; изменяющие запросы требуют одноразовый ключ (nonce)
 * действия `wp_rest` в заголовке `X-WP-Nonce`. Для гостя список живёт в
 * браузере и передаётся в запросе, для авторизованного пользователя —
 * в базе данных и изменяется сервером.
 */
final class Rest_Controller {

	/**
	 * Пространство имён маршрутов.
	 *
	 * @var string
	 */
	const NAMESPACE = 'rvn-compare/v1';

	/**
	 * Допустимое число запросов слияния в минуту с одного клиента.
	 *
	 * @var int
	 */
	const MERGE_RATE_LIMIT = 20;

	/**
	 * Подключает регистрацию маршрутов.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
	}

	/**
	 * Регистрирует маршруты API.
	 *
	 * @return void
	 */
	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/list',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'get_list' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'ids' => array(
						'type'              => 'string',
						'sanitize_callback' => array( self::class, 'sanitize_ids_arg' ),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/table',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'get_table' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'ids'      => array(
						'type'              => 'string',
						'sanitize_callback' => array( self::class, 'sanitize_ids_arg' ),
					),
					'products' => array(
						'type'              => 'string',
						'sanitize_callback' => array( self::class, 'sanitize_ids_arg' ),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/items',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'add_item' ),
				'permission_callback' => array( self::class, 'require_nonce' ),
				'args'                => self::mutation_args(),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/items/remove',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'remove_item' ),
				'permission_callback' => array( self::class, 'require_nonce' ),
				'args'                => self::mutation_args(),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/clear',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'clear_list' ),
				'permission_callback' => array( self::class, 'require_nonce' ),
				'args'                => array_merge(
					self::mutation_args( false ),
					array(
						'tab' => array(
							'type'      => 'string',
							'maxLength' => 100,
						),
					)
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/merge',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'merge_lists' ),
				'permission_callback' => array( self::class, 'require_nonce' ),
				'args'                => self::mutation_args( false ),
			)
		);
	}

	/**
	 * Параметры изменяющих запросов.
	 *
	 * @param bool $product_id_required Требовать ли ID товара.
	 * @return array<string, array<string, mixed>>
	 */
	private static function mutation_args( bool $product_id_required = true ): array {
		$args = array(
			'ids' => array(
				'type'              => 'string',
				'sanitize_callback' => array( self::class, 'sanitize_ids_arg' ),
			),
		);

		if ( $product_id_required ) {
			$args['product_id'] = array(
				'required' => true,
				'type'     => 'integer',
				'minimum'  => 1,
			);
		}

		return $args;
	}

	/**
	 * Приводит параметр со списком ID к массиву целых чисел.
	 *
	 * @param mixed $value Значение параметра: строка через запятую или массив.
	 * @return int[]
	 */
	public static function sanitize_ids_arg( $value ): array {
		if ( is_string( $value ) ) {
			$value = explode( ',', $value );
		}

		return Storage::normalize_ids( $value );
	}

	/**
	 * Проверяет временный ключ (nonce) действия `wp_rest`.
	 *
	 * Для авторизованных запросов WordPress дополнительно проверяет ключ
	 * своей cookie-авторизацией; здесь проверка выполняется и для гостей.
	 *
	 * @param WP_REST_Request $request Текущий запрос.
	 * @return bool|WP_Error
	 */
	public static function require_nonce( WP_REST_Request $request ) {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );

		if ( '' === $nonce ) {
			$nonce = (string) $request->get_param( '_wpnonce' );
		}

		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'rvn_compare_rest_forbidden',
				__( 'Invalid security token. Reload the page and try again.', 'rvn-compare-products-for-woocommerce' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Возвращает состояние списка.
	 *
	 * @param WP_REST_Request $request Текущий запрос.
	 * @return WP_REST_Response
	 */
	public static function get_list( WP_REST_Request $request ): WP_REST_Response {
		$user_id  = self::user_id();
		$ids      = $user_id > 0 ? array() : (array) $request->get_param( 'ids' );
		$response = rest_ensure_response( Compare_Service::get_state( self::user_or_null(), $ids ) );

		$response->header( 'Cache-Control', 'private, no-store, no-cache, max-age=0' );
		$response->header( 'Vary', 'Cookie' );

		return $response;
	}

	/**
	 * Возвращает только публично доступные характеристики для выбранных товаров.
	 *
	 * Гость передаёт ID из браузера; аккаунт получает свой список с сервера.
	 * Для статической таблицы products=ID,ID список фиксирован и не меняет профиль.
	 * Ответ всегда помечен no-store: в общем кэше не может оказаться список
	 * вошедшего пользователя или конфигурация гостя.
	 *
	 * @param WP_REST_Request $request Текущий запрос.
	 * @return WP_REST_Response
	 */
	public static function get_table( WP_REST_Request $request ): WP_REST_Response {
		$settings = Settings::all();
		$state    = Compare_Service::get_state( self::user_or_null(), (array) $request->get_param( 'ids' ) );
		$static   = $request->has_param( 'products' );
		$ids      = $static ? (array) $request->get_param( 'products' ) : (array) $state['ids'];

		$response = rest_ensure_response( Table_Data::build( $ids, $settings, $static ) );
		$response->header( 'Cache-Control', 'private, no-store, no-cache, max-age=0' );
		$response->header( 'Vary', 'Cookie' );

		return $response;
	}

	/**
	 * Добавляет товар в список.
	 *
	 * @param WP_REST_Request $request Текущий запрос.
	 * @return WP_REST_Response
	 */
	public static function add_item( WP_REST_Request $request ): WP_REST_Response {
		return rest_ensure_response(
			Compare_Service::add(
				self::user_or_null(),
				(int) $request->get_param( 'product_id' ),
				(array) $request->get_param( 'ids' )
			)
		);
	}

	/**
	 * Удаляет товар из списка.
	 *
	 * @param WP_REST_Request $request Текущий запрос.
	 * @return WP_REST_Response
	 */
	public static function remove_item( WP_REST_Request $request ): WP_REST_Response {
		return rest_ensure_response(
			Compare_Service::remove(
				self::user_or_null(),
				(int) $request->get_param( 'product_id' ),
				(array) $request->get_param( 'ids' )
			)
		);
	}

	/**
	 * Очищает список.
	 *
	 * @param WP_REST_Request $request Текущий запрос.
	 * @return WP_REST_Response
	 */
	public static function clear_list( WP_REST_Request $request ): WP_REST_Response {
		return rest_ensure_response(
			Compare_Service::clear(
				self::user_or_null(),
				(array) $request->get_param( 'ids' ),
				is_string( $request->get_param( 'tab' ) ) ? substr( (string) $request->get_param( 'tab' ), 0, 100 ) : ''
			)
		);
	}

	/**
	 * Сливает гостевой список со списком пользователя.
	 *
	 * Запрос ограничивает частоту: слияние выполняется при входе, повторные
	 * запросы в течение минуты отклоняются.
	 *
	 * @param WP_REST_Request $request Текущий запрос.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function merge_lists( WP_REST_Request $request ) {
		if ( self::too_many_requests( $request ) ) {
			return new WP_Error(
				'rvn_compare_rest_throttled',
				__( 'Too many requests. Try again in a minute.', 'rvn-compare-products-for-woocommerce' ),
				array( 'status' => 429 )
			);
		}

		return rest_ensure_response(
			Compare_Service::merge(
				self::user_or_null(),
				(array) $request->get_param( 'ids' )
			)
		);
	}

	/**
	 * ID текущего пользователя или 0 для гостя.
	 *
	 * @return int
	 */
	private static function user_id(): int {
		return (int) get_current_user_id();
	}

	/**
	 * ID текущего пользователя или null для гостя.
	 *
	 * @return int|null
	 */
	private static function user_or_null(): ?int {
		$user_id = self::user_id();

		return $user_id > 0 ? $user_id : null;
	}

	/**
	 * Превысил ли клиент допустимую частоту запросов слияния.
	 *
	 * @param WP_REST_Request $request Текущий запрос.
	 * @return bool
	 */
	private static function too_many_requests( WP_REST_Request $request ): bool {
		$key   = 'rvn_compare_rl_' . md5( self::client_key( $request ) );
		$count = (int) get_transient( $key );

		if ( $count >= self::MERGE_RATE_LIMIT ) {
			return true;
		}

		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );

		return false;
	}

	/**
	 * Ключ клиента для ограничения частоты: пользователь или хеш адреса.
	 *
	 * Адрес не сохраняется — в ключе только его хеш.
	 *
	 * @param WP_REST_Request $request Текущий запрос.
	 * @return string
	 */
	private static function client_key( WP_REST_Request $request ): string {
		unset( $request );

		$user_id = self::user_id();

		if ( $user_id > 0 ) {
			return 'u' . $user_id;
		}

		$address = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		return 'g' . md5( $address );
	}
}
