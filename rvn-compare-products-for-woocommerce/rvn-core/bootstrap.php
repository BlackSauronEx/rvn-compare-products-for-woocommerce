<?php
/**
 * Регистрация копии общего ядра линейки RVN.
 *
 * Каждый плагин линейки RVN содержит свою копию ядра (меню «RVN» и страница
 * «Поддержка»). Все копии регистрируются здесь, а на хуке plugins_loaded
 * подключается только самая новая — так в консоли не появляется дублей и не
 * возникает ошибок повторного объявления классов.
 *
 * Функции регистрации должны оставаться неизменными во всех версиях ядра:
 * их объявляет копия, подключённая первой. Файл совместим с PHP 7.0, так как
 * его могут подключать и другие плагины линейки.
 *
 * @package RVN_Compare
 */

namespace RVN_Core;

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( __NAMESPACE__ . '\\register_candidate' ) ) {

	/**
	 * Запоминает копию ядра и возвращает список всех зарегистрированных копий.
	 *
	 * @param string $version Версия копии ядра.
	 * @param string $file    Полный путь к файлу класса ядра этой копии.
	 * @return array<string, string> Список копий: версия => путь к файлу.
	 */
	function register_candidate( $version = '', $file = '' ) {
		static $candidates = array();

		if ( '' !== $version && '' !== $file ) {
			$candidates[ $version ] = $file;
		}

		return $candidates;
	}

	/**
	 * Подключает самую новую зарегистрированную копию ядра, один раз за запрос.
	 *
	 * @return void
	 */
	function load_latest() {
		if ( class_exists( __NAMESPACE__ . '\\Core', false ) ) {
			return;
		}

		$candidates = register_candidate();

		if ( array() === $candidates ) {
			return;
		}

		uksort(
			$candidates,
			static function ( $a, $b ) {
				return version_compare( (string) $a, (string) $b );
			}
		);

		require_once end( $candidates );

		Core::init();
	}

	add_action( 'plugins_loaded', __NAMESPACE__ . '\\load_latest', 1 );
}

register_candidate( '1.0.0', __DIR__ . '/class-core.php' );
