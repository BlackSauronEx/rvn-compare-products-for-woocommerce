<?php
/**
 * Интеграция плагина с консолью WordPress.
 *
 * @package RVN_Compare
 */

namespace RVN_Compare\Admin;

use RVN_Core\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Регистрирует страницу «Сравнение» в меню RVN, страницу диагностики
 * и ссылку «Настройки» в списке плагинов.
 */
final class Admin {

	/**
	 * Идентификатор страницы настроек.
	 *
	 * @var string
	 */
	const PAGE_SLUG = 'rvn-compare';

	/**
	 * Право, необходимое для доступа к настройкам (администратор и менеджер магазина).
	 *
	 * @var string
	 */
	const CAPABILITY = 'manage_woocommerce';

	/**
	 * Подключает обработчики консоли.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( self::class, 'register_product' ) );
		add_filter( 'plugin_action_links_' . RVN_COMPARE_BASENAME, array( self::class, 'add_action_links' ) );

		Diagnostics_Page::init();
		Page_Settings::init();
		Table_Settings::init();
	}

	/**
	 * Добавляет страницу «Сравнение» в общее меню линейки RVN.
	 *
	 * Вызывается на admin_menu с приоритетом по умолчанию: ядро строит меню
	 * позже (приоритет 90), а переводы к этому моменту уже доступны.
	 *
	 * @return void
	 */
	public static function register_product(): void {
		if ( ! class_exists( Core::class ) ) {
			return;
		}

		Core::add_product(
			self::PAGE_SLUG,
			array(
				'page_title' => __( 'Compare settings', 'rvn-compare-products-for-woocommerce' ),
				'menu_title' => __( 'Compare', 'rvn-compare-products-for-woocommerce' ),
				'capability' => self::CAPABILITY,
				'callback'   => array( Settings_Page::class, 'render' ),
				'position'   => 10,
			)
		);
	}

	/**
	 * Добавляет ссылку «Настройки» в строку плагина на экране «Плагины».
	 *
	 * @param mixed $links Ссылки действий плагина.
	 * @return array<int|string, string>
	 */
	public static function add_action_links( $links ): array {
		if ( ! is_array( $links ) ) {
			$links = array();
		}

		if ( ! current_user_can( self::CAPABILITY ) ) {
			return $links;
		}

		$settings_link = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ),
			esc_html__( 'Settings', 'rvn-compare-products-for-woocommerce' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}
}
