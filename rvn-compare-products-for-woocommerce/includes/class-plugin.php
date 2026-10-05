<?php
/**
 * Запуск компонентов плагина после проверки требований.
 *
 * @package RVN_Compare
 */

namespace RVN_Compare;

use RVN_Compare\Admin\Admin;
use RVN_Compare\Frontend\Frontend;
use RVN_Compare\Rest\Rest_Controller;

defined( 'ABSPATH' ) || exit;

/**
 * Точка входа плагина: проверяет окружение и подключает компоненты.
 */
final class Plugin {

	/**
	 * Плагин уже запущен в текущем запросе.
	 *
	 * @var bool
	 */
	private static bool $booted = false;

	/**
	 * Запускает плагин на хуке plugins_loaded.
	 *
	 * Если требования не выполнены, плагин ничего не подключает, а только
	 * объясняет администратору, что нужно исправить.
	 *
	 * @return void
	 */
	public static function boot(): void {
		if ( self::$booted ) {
			return;
		}

		self::$booted = true;

		$requirements = new Requirements();

		if ( ! $requirements->are_met() ) {
			$requirements->register_notice();
			return;
		}

		Installer::init();
		Rest_Controller::init();
		Nonce_Recovery::init();
		Frontend::init();
		add_action( 'rvn_compare_shortcode_notice', array( Frontend::class, 'log_shortcode_notice' ), 10, 2 );

		if ( is_admin() ) {
			Admin::init();
		}

		/**
		 * Срабатывает после запуска всех компонентов плагина.
		 *
		 * Подходящая точка, чтобы подключать собственные расширения плагина.
		 *
		 * @since 0.1.0
		 */
		do_action( 'rvn_compare_loaded' );
	}
}
