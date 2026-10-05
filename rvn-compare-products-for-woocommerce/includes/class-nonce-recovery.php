<?php
/**
 * Некэшируемое получение свежего REST nonce для страниц из кэша.
 *
 * @package RVN_Compare
 */

namespace RVN_Compare;

defined( 'ABSPATH' ) || exit;

/**
 * Восстанавливает REST nonce гостя или вошедшего пользователя через admin-ajax.php.
 *
 * WordPress проверяет cookie и X-WP-Nonce до вызова любого REST-маршрута.
 * Поэтому маршрут REST не может выдать новый nonce, если HTML страницы
 * сохранил просроченный ключ. AJAX-обработчик не использует REST-авторизацию.
 */
final class Nonce_Recovery {

	/**
	 * Имя AJAX-действия.
	 *
	 * @var string
	 */
	public const ACTION = 'rvn_compare_refresh_nonce';

	/**
	 * Регистрирует обработчик для гостя и авторизованного пользователя.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'wp_ajax_' . self::ACTION, array( self::class, 'refresh' ) );
		add_action( 'wp_ajax_nopriv_' . self::ACTION, array( self::class, 'refresh' ) );
	}

	/**
	 * Возвращает свежий nonce и признак авторизации, не меняя данные сайта.
	 *
	 * Здесь намеренно нельзя проверять входящий nonce: задача обработчика —
	 * восстановить именно просроченный ключ. Метод только GET, а в ответе
	 * нет пользовательского списка, email или других приватных данных.
	 * Браузер получает ключ своей сессии через cookies; чтение с чужого
	 * origin ограничено политикой одного источника (Same Origin Policy).
	 *
	 * @return void
	 */
	public static function refresh(): void {
		// Чужой сайт не должен запрашивать ключ с cookies покупателя.
		if ( isset( $_SERVER['HTTP_SEC_FETCH_SITE'] ) && 'cross-site' === $_SERVER['HTTP_SEC_FETCH_SITE'] ) {
			wp_send_json_error( array( 'code' => 'rvn_compare_cross_site' ), 403 );
		}

		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'GET' !== $_SERVER['REQUEST_METHOD'] ) {
			wp_send_json_error( array( 'code' => 'rvn_compare_method_not_allowed' ), 405 );
		}

		// admin-ajax.php уже выставляет no-cache; явно запрещаем кэш любого промежуточного прокси.
		nocache_headers();
		header( 'Cache-Control: no-store, no-cache, must-revalidate, private, max-age=0' );
		header( 'Vary: Cookie', false );

		wp_send_json_success(
			array(
				'nonce' => wp_create_nonce( 'wp_rest' ),
				'user'  => is_user_logged_in() ? 'registered' : 'guest',
			)
		);
	}
}
