<?php
/**
 * RVN Compare Products for WooCommerce.
 *
 * Главный файл плагина: заголовок, константы, проверка версии PHP,
 * загрузка переводов, декларации совместимости с WooCommerce и запуск.
 *
 * Файл намеренно написан без синтаксиса PHP 7.1+: если хостинг понизит
 * версию PHP, администратор увидит понятное уведомление, а не фатальную
 * ошибку разбора. Код с синтаксисом PHP 8.1 подключается только после проверки.
 *
 * @package RVN_Compare
 *
 * @wordpress-plugin
 * Plugin Name:          RVN Compare Products for WooCommerce
 * Description:          Product comparison for WooCommerce: compare buttons, a live counter and a clean side-by-side comparison table.
 * Version:              0.4.1
 * Requires at least:    6.4
 * Requires PHP:         8.1
 * Requires Plugins:     woocommerce
 * Author:               Revolen
 * License:              GPL-2.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          rvn-compare-products-for-woocommerce
 * Domain Path:          /languages
 * WC requires at least: 8.2
 * WC tested up to:      11.1
 */

namespace RVN_Compare;

defined( 'ABSPATH' ) || exit;

define( 'RVN_COMPARE_VERSION', '0.4.1' );
define( 'RVN_COMPARE_FILE', __FILE__ );
define( 'RVN_COMPARE_PATH', plugin_dir_path( __FILE__ ) );
define( 'RVN_COMPARE_URL', plugin_dir_url( __FILE__ ) );
define( 'RVN_COMPARE_BASENAME', plugin_basename( __FILE__ ) );
define( 'RVN_COMPARE_MIN_PHP', '8.1' );
define( 'RVN_COMPARE_MIN_WP', '6.4' );
define( 'RVN_COMPARE_MIN_WC', '8.2' );

/**
 * Регистрирует папку со встроенными переводами плагина.
 *
 * Языковые пакеты WordPress.org (wp-content/languages/plugins) имеют приоритет;
 * встроенные файлы используются, пока такого пакета нет.
 *
 * @return void
 */
function register_translations() {
	// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- The plugin ships a bundled ru_RU translation; WordPress.org language packs still take precedence.
	load_plugin_textdomain( 'rvn-compare-products-for-woocommerce', false, dirname( RVN_COMPARE_BASENAME ) . '/languages' );
}
add_action( 'init', __NAMESPACE__ . '\\register_translations' );

/**
 * Объявляет совместимость плагина с функциями WooCommerce.
 *
 * Плагин не читает и не изменяет заказы (совместим с HPOS), а также
 * не затрагивает блоки корзины и оформления заказа и редактор товаров.
 *
 * @return void
 */
function declare_woocommerce_compatibility() {
	if ( ! class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		return;
	}

	$features = array( 'custom_order_tables', 'cart_checkout_blocks', 'product_block_editor' );

	foreach ( $features as $feature ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( $feature, RVN_COMPARE_FILE, true );
	}
}
add_action( 'before_woocommerce_init', __NAMESPACE__ . '\\declare_woocommerce_compatibility' );

/**
 * Показывает администратору уведомление о слишком старой версии PHP.
 *
 * @return void
 */
function render_php_version_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		esc_html(
			sprintf(
				/* translators: 1: Plugin name. 2: Required PHP version. 3: Current PHP version. */
				__( '%1$s requires PHP %2$s or newer. Your server runs PHP %3$s. Please ask your hosting provider to update PHP.', 'rvn-compare-products-for-woocommerce' ),
				'RVN Compare Products for WooCommerce',
				RVN_COMPARE_MIN_PHP,
				PHP_VERSION
			)
		)
	);
}

if ( version_compare( PHP_VERSION, RVN_COMPARE_MIN_PHP, '<' ) ) {
	add_action( 'admin_notices', __NAMESPACE__ . '\\render_php_version_notice' );
	add_action( 'network_admin_notices', __NAMESPACE__ . '\\render_php_version_notice' );
	return;
}

require_once RVN_COMPARE_PATH . 'rvn-core/bootstrap.php';
require_once RVN_COMPARE_PATH . 'includes/class-autoloader.php';

Autoloader::register();

register_activation_hook( RVN_COMPARE_FILE, array( Installer::class, 'activate' ) );

add_action( 'plugins_loaded', array( Plugin::class, 'boot' ) );
