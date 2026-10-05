<?php
/**
 * Автозагрузчик классов плагина.
 *
 * @package RVN_Compare
 */

namespace RVN_Compare;

defined( 'ABSPATH' ) || exit;

/**
 * Подключает классы пространства имён RVN_Compare по соглашению WordPress.
 *
 * Пример: RVN_Compare\Admin\Settings_Page → includes/admin/class-settings-page.php.
 */
final class Autoloader {

	/**
	 * Префикс пространства имён, которое обслуживает автозагрузчик.
	 *
	 * @var string
	 */
	private const NAMESPACE_PREFIX = __NAMESPACE__ . '\\';

	/**
	 * Регистрирует автозагрузчик в PHP.
	 *
	 * @return void
	 */
	public static function register(): void {
		spl_autoload_register( array( self::class, 'load' ) );
	}

	/**
	 * Подключает файл класса, если класс принадлежит плагину.
	 *
	 * @param string $class_name Полное имя класса с пространством имён.
	 * @return void
	 */
	public static function load( string $class_name ): void {
		if ( ! str_starts_with( $class_name, self::NAMESPACE_PREFIX ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( self::NAMESPACE_PREFIX ) );

		// Допустимы только буквы, цифры, подчёркивание и разделитель пространств имён: путь вида ../ невозможен.
		if ( '' === $relative || 1 !== preg_match( '/^[A-Za-z0-9_\\\\]+$/', $relative ) ) {
			return;
		}

		$parts     = explode( '\\', $relative );
		$class     = (string) array_pop( $parts );
		$directory = '';

		foreach ( $parts as $part ) {
			$directory .= self::to_file_part( $part ) . '/';
		}

		$file = RVN_COMPARE_PATH . 'includes/' . $directory . 'class-' . self::to_file_part( $class ) . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}

	/**
	 * Превращает часть имени класса в часть имени файла: Settings_Page → settings-page.
	 *
	 * @param string $name Часть имени класса.
	 * @return string
	 */
	private static function to_file_part( string $name ): string {
		return strtolower( str_replace( '_', '-', $name ) );
	}
}
