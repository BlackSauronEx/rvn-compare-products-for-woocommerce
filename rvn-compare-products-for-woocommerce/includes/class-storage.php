<?php
/**
 * Хранилище списков сравнения авторизованных пользователей.
 *
 * @package RVN_Compare
 */

namespace RVN_Compare;

defined( 'ABSPATH' ) || exit;

/**
 * Список сравнения — упорядоченный массив ID товаров.
 *
 * Гость хранит список в localStorage браузера, сервер его не пишет;
 * для авторизованного пользователя список лежит в пользовательской опции
 * в формате JSON с номером версии схемы — чтобы в будущем можно было
 * переводить старые форматы без потери данных.
 */
final class Storage {

	/**
	 * Пользовательская опция со списком (отдельно для каждого сайта сети).
	 *
	 * @var string
	 */
	const USER_OPTION = 'rvn_compare_list';

	/**
	 * Версия схемы хранения списка.
	 *
	 * @var int
	 */
	const SCHEMA_VERSION = 1;

	/**
	 * Приводит произвольные данные к списку уникальных положительных ID.
	 *
	 * Порядок сохраняется; список обрезается до допустимой длины с конца.
	 *
	 * @param mixed    $raw Исходные данные (массив, JSON-строка или пустое значение).
	 * @param int|null $cap Наибольшая длина списка; по умолчанию — общий лимит настроек.
	 * @return int[]
	 */
	public static function normalize_ids( $raw, ?int $cap = null ): array {
		if ( is_string( $raw ) ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				$raw = $decoded;
			} else {
				// Извлекает все положительные целые числа из строк любого формата:
				// «214,217», "214, 217", 214;217 и т.д.
				preg_match_all( '/\d+/', $raw, $matches );
				$raw = ! empty( $matches[0] ) ? $matches[0] : array();
			}
		}

		if ( ! is_array( $raw ) ) {
			return array();
		}

		if ( null === $cap ) {
			$cap = (int) Settings::get( 'limit_total' );
		}

		$cap = max( 1, $cap );
		$ids = array();

		foreach ( $raw as $value ) {
			// Приведение к int, а не absint: отрицательные значения — мусор, а не их модуль.
			$id = (int) $value;

			if ( $id > 0 && ! in_array( $id, $ids, true ) ) {
				$ids[] = $id;
			}

			if ( count( $ids ) >= $cap ) {
				break;
			}
		}

		return $ids;
	}

	/**
	 * Читает список пользователя из базы данных.
	 *
	 * Повреждённая запись удаляется: вместо неё пользователь получает
	 * пустой список, а не ошибку на каждом экране.
	 *
	 * @param int $user_id ID пользователя.
	 * @return int[]
	 */
	public static function get_ids( int $user_id ): array {
		if ( $user_id <= 0 ) {
			return array();
		}

		$raw = get_user_option( self::USER_OPTION, $user_id );

		if ( ! is_string( $raw ) || '' === $raw ) {
			return array();
		}

		$data = json_decode( $raw, true );

		// Страховка на случай, если данные вернулись с обратными слэшами.
		if ( ! is_array( $data ) ) {
			$data = json_decode( wp_unslash( $raw ), true );
		}

		if ( ! is_array( $data ) || ! isset( $data['ids'] ) || ! is_array( $data['ids'] ) ) {
			self::delete( $user_id );
			return array();
		}

		return self::normalize_ids( $data['ids'] );
	}

	/**
	 * Сохраняет список пользователя.
	 *
	 * @param int   $user_id ID пользователя.
	 * @param int[] $ids     Список ID товаров.
	 * @return void
	 */
	public static function save_ids( int $user_id, array $ids ): void {
		if ( $user_id <= 0 ) {
			return;
		}

		$json = wp_json_encode(
			array(
				'v'   => self::SCHEMA_VERSION,
				'ids' => self::normalize_ids( $ids ),
				'ts'  => time(),
			)
		);

		if ( ! is_string( $json ) ) {
			return;
		}

		// update_user_option ожидает слэшированные данные: иначе обратные слэши JSON пострадают.
		update_user_option( $user_id, self::USER_OPTION, wp_slash( $json ) );
	}

	/**
	 * Удаляет список пользователя.
	 *
	 * @param int $user_id ID пользователя.
	 * @return void
	 */
	public static function delete( int $user_id ): void {
		if ( $user_id <= 0 ) {
			return;
		}

		delete_user_option( $user_id, self::USER_OPTION );
	}
}
