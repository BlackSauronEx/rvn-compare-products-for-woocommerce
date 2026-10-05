<?php
/**
 * Сервис списков сравнения: чтение, добавление, удаление и слияние.
 *
 * @package RVN_Compare
 */

namespace RVN_Compare;

defined( 'ABSPATH' ) || exit;

/**
 * Единственная точка изменения списков сравнения.
 *
 * Для авторизованного пользователя источник истины — база данных, для гостя —
 * список браузера, который клиент передаёт в запросе; сервер его нормализует,
 * проверяет и возвращает вычисленное состояние. Так страницы остаются
 * кешируемыми, а персональные данные не попадают в кэш.
 */
final class Compare_Service {

	/**
	 * Состояние списка пользователя.
	 *
	 * @param int|null             $user_id    ID пользователя или null для гостя.
	 * @param int[]                $client_ids Список гостя из браузера.
	 * @param array<string, mixed> $settings   Настройки плагина.
	 * @return array<string, mixed>
	 */
	public static function get_state( ?int $user_id, array $client_ids = array(), ?array $settings = null ): array {
		$settings = $settings ?? Settings::all();
		$ids      = self::current_ids( $user_id, $client_ids, $settings );

		return self::state( 'list', $user_id, $ids, $settings );
	}

	/**
	 * Добавляет товар в список.
	 *
	 * @param int|null $user_id    ID пользователя или null для гостя.
	 * @param int      $product_id ID товара.
	 * @param int[]    $client_ids Список гостя из браузера.
	 * @return array<string, mixed>
	 */
	public static function add( ?int $user_id, int $product_id, array $client_ids = array() ): array {
		$settings = Settings::all();
		$ids      = self::current_ids( $user_id, $client_ids, $settings );

		if ( in_array( $product_id, $ids, true ) ) {
			return self::state( 'already', $user_id, $ids, $settings, array( 'product_id' => $product_id ) );
		}

		if ( ! self::product_available( $product_id ) ) {
			return self::state( 'not_found', $user_id, $ids, $settings, array( 'product_id' => $product_id ) );
		}

		$check = Limits::check_add( $ids, $product_id, $settings );

		if ( ! $check['ok'] ) {
			return self::state(
				$check['code'],
				$user_id,
				$ids,
				$settings,
				array(
					'product_id' => $product_id,
					'tab'        => self::tab_info( $check, $settings ),
					'reason'     => self::reason( $check, $settings ),
				)
			);
		}

		$ids[] = $product_id;

		self::persist( $user_id, $ids );

		return self::state( 'added', $user_id, $ids, $settings, array( 'product_id' => $product_id ) );
	}

	/**
	 * Удаляет товар из списка.
	 *
	 * @param int|null $user_id    ID пользователя или null для гостя.
	 * @param int      $product_id ID товара.
	 * @param int[]    $client_ids Список гостя из браузера.
	 * @return array<string, mixed>
	 */
	public static function remove( ?int $user_id, int $product_id, array $client_ids = array() ): array {
		$settings = Settings::all();
		$ids      = self::current_ids( $user_id, $client_ids, $settings );
		$ids      = array_values( array_diff( $ids, array( $product_id ) ) );

		self::persist( $user_id, $ids );

		return self::state( 'removed', $user_id, $ids, $settings, array( 'product_id' => $product_id ) );
	}

	/**
	 * Очищает весь список или товары активной вкладки.
	 *
	 * @param int|null $user_id    ID пользователя или null для гостя.
	 * @param int[]    $client_ids Список гостя из браузера.
	 * @param string   $tab_key    Ключ вкладки либо пустая строка для всего списка.
	 * @return array<string, mixed>
	 */
	public static function clear( ?int $user_id, array $client_ids = array(), string $tab_key = '' ): array {
		$settings  = Settings::all();
		$original  = self::current_ids( $user_id, $client_ids, $settings );
		$remaining = array();

		if ( '' !== $tab_key ) {
			$removing = array();

			foreach ( Category_Model::tabs_for( $original, $settings ) as $tab ) {
				if ( $tab_key === $tab['key'] ) {
					$removing = $tab['ids'];
					break;
				}
			}

			// Несуществующая вкладка не должна случайно превратиться в «очистить всё».
			$remaining = array_values( array_diff( $original, $removing ) );
		}

		self::persist( $user_id, $remaining );

		return self::state( 'cleared', $user_id, $remaining, $settings, array( 'removed' => count( $original ) - count( $remaining ) ) );
	}

	/**
	 * Добавляет гостевой список к списку пользователя при входе в аккаунт.
	 *
	 * Дубли и недоступные товары отбрасываются; товары, не вмещающиеся в лимиты,
	 * пропускаются, а при превышении общего лимита лишнее отсекается с конца.
	 * Операция идемпотентна: повторный вход ничего не добавит.
	 *
	 * @param int   $user_id   ID пользователя (для гостя слияние не выполняется).
	 * @param int[] $guest_ids Список из браузера.
	 * @return array<string, mixed>
	 */
	public static function merge( ?int $user_id, array $guest_ids ): array {
		$settings = Settings::all();

		if ( ! $user_id || $user_id <= 0 ) {
			return self::state( 'not_user', null, array(), $settings );
		}

		$base    = self::current_ids( $user_id, array(), $settings );
		$added   = 0;
		$skipped = 0;

		foreach ( Storage::normalize_ids( $guest_ids, Settings::MAX_TOTAL ) as $product_id ) {
			if ( in_array( $product_id, $base, true ) ) {
				continue;
			}

			if ( ! self::product_available( $product_id ) ) {
				++$skipped;
				continue;
			}

			$check = Limits::check_add( $base, $product_id, $settings );

			if ( ! $check['ok'] ) {
				++$skipped;
				continue;
			}

			$base[] = $product_id;
			++$added;
		}

		self::persist( $user_id, $base );

		return self::state(
			'merged',
			$user_id,
			$base,
			$settings,
			array(
				'added'   => $added,
				'skipped' => $skipped,
			)
		);
	}

	/**
	 * Доступен ли товар для сравнения: обычный опубликованный товар.
	 *
	 * Вариации, черновики и приватные товары отсеиваются: у них нет собственной
	 * карточки для сравнения либо они недоступны покупателю.
	 *
	 * @param int $product_id ID товара.
	 * @return bool
	 */
	public static function product_available( int $product_id ): bool {
		if ( $product_id <= 0 ) {
			return false;
		}

		if ( 'product' !== get_post_type( $product_id ) || 'publish' !== get_post_status( $product_id ) ) {
			return false;
		}

		$product = wc_get_product( $product_id );

		return $product instanceof \WC_Product && $product->is_visible();
	}

	/**
	 * Текущий нормализованный список пользователя или гостя.
	 *
	 * @param int|null             $user_id    ID пользователя или null для гостя.
	 * @param int[]                $client_ids Список гостя.
	 * @param array<string, mixed> $settings   Настройки плагина.
	 * @return int[]
	 */
	private static function current_ids( ?int $user_id, array $client_ids, array $settings ): array {
		$raw = ( $user_id && $user_id > 0 ) ? Storage::get_ids( $user_id ) : Storage::normalize_ids( $client_ids );

		return self::sanitize_list( $raw, $settings );
	}

	/**
	 * Убирает недоступные товары и отсекает лишнее с конца при снижении лимита.
	 *
	 * @param int[]                $ids      Исходный список.
	 * @param array<string, mixed> $settings Настройки плагина.
	 * @return int[]
	 */
	private static function sanitize_list( array $ids, array $settings ): array {
		$available = array();

		foreach ( $ids as $product_id ) {
			$product_id = (int) $product_id;

			if ( self::product_available( $product_id ) ) {
				$available[] = $product_id;
			}
		}

		return Storage::normalize_ids( $available, (int) $settings['limit_total'] );
	}

	/**
	 * Сохраняет список авторизованного пользователя.
	 *
	 * @param int|null $user_id ID пользователя.
	 * @param int[]    $ids     Список ID товаров.
	 * @return void
	 */
	private static function persist( ?int $user_id, array $ids ): void {
		if ( $user_id && $user_id > 0 ) {
			Storage::save_ids( $user_id, $ids );
		}
	}

	/**
	 * Собирает ответ о состоянии списка.
	 *
	 * @param string               $status  Код результата действия.
	 * @param int|null             $user_id ID пользователя.
	 * @param int[]                $ids     Список ID товаров.
	 * @param array<string, mixed> $settings Настройки плагина.
	 * @param array<string, mixed> $extra   Дополнительные поля ответа.
	 * @return array<string, mixed>
	 */
	private static function state( string $status, ?int $user_id, array $ids, array $settings, array $extra = array() ): array {
		return array_merge(
			array(
				'status' => $status,
				'user'   => ( $user_id && $user_id > 0 ) ? 'registered' : 'guest',
				'ids'    => $ids,
				'count'  => count( $ids ),
				'tabs'   => Category_Model::tabs_for( $ids, $settings ),
				'limits' => array(
					'total'        => (int) $settings['limit_total'],
					'per_category' => (int) $settings['limit_per_category'],
				),
			),
			$extra
		);
	}

	/**
	 * Информация о переполненной вкладке для ответа клиенту.
	 *
	 * @param array<string, mixed> $check   Результат Limits::check_add.
	 * @param array<string, mixed> $settings Настройки плагина.
	 * @return array<string, mixed>|null
	 */
	private static function tab_info( array $check, array $settings ): ?array {
		if ( empty( $check['tab'] ) ) {
			return null;
		}

		return array(
			'key'   => (string) $check['tab'],
			'label' => Category_Model::label( (string) $check['tab'], $settings ),
			'count' => (int) $check['count'],
			'limit' => (int) $check['limit'],
		);
	}

	/**
	 * Понятная покупателю причина отказа.
	 *
	 * @param array<string, mixed> $check   Результат Limits::check_add.
	 * @param array<string, mixed> $settings Настройки плагина.
	 * @return string
	 */
	private static function reason( array $check, array $settings ): string {
		if ( 'limit_category' === $check['code'] && ! empty( $check['tab'] ) ) {
			return sprintf(
				/* translators: 1: Category or group name. 2: Number of products in it. 3: Limit of products in a category. */
				__( 'Category "%1$s" already has %2$d of %3$d products. Remove one to add another.', 'rvn-compare-products-for-woocommerce' ),
				Category_Model::label( (string) $check['tab'], $settings ),
				(int) $check['count'],
				(int) $check['limit']
			);
		}

		return sprintf(
			/* translators: 1: Number of products in the comparison list. 2: Total limit of products. */
			__( 'Comparison list is full: %1$d of %2$d products.', 'rvn-compare-products-for-woocommerce' ),
			(int) $check['count'],
			(int) $check['limit']
		);
	}
}
