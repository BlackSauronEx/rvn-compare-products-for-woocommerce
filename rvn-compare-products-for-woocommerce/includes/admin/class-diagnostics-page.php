<?php
/**
 * Страница «Инструменты → Диагностика RVN».
 *
 * @package RVN_Compare
 */

namespace RVN_Compare\Admin;

use RVN_Compare\Settings;
use RVN_Compare\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * Собирает сведения об окружении сайта для отчёта о проверке плагина.
 *
 * Страница только читает данные и ничего не изменяет. Отчёт приватный:
 * он показывается пользователю с правом manage_woocommerce и не уходит
 * никуда за пределы сайта.
 */
final class Diagnostics_Page {

	/**
	 * Идентификатор страницы диагностики.
	 *
	 * @var string
	 */
	const PAGE_SLUG = 'rvn-compare-diagnostics';

	/**
	 * Подключает регистрацию страницы.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( self::class, 'register' ) );
	}

	/**
	 * Регистрирует страницу в разделе «Инструменты».
	 *
	 * @return void
	 */
	public static function register(): void {
		add_submenu_page(
			'tools.php',
			__( 'RVN Diagnostics', 'rvn-compare-products-for-woocommerce' ),
			__( 'RVN Diagnostics', 'rvn-compare-products-for-woocommerce' ),
			Admin::CAPABILITY,
			self::PAGE_SLUG,
			array( self::class, 'render' )
		);
	}

	/**
	 * Выводит страницу диагностики.
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( Admin::CAPABILITY ) ) {
			wp_die(
				esc_html__( 'Sorry, you are not allowed to access this page.', 'rvn-compare-products-for-woocommerce' ),
				'',
				array( 'response' => 403 )
			);
		}

		$rows   = self::rows();
		$report = self::report_text( $rows );
		?>
		<div class="wrap rvn-compare-diagnostics" data-rvn-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>">
			<h1><?php esc_html_e( 'RVN Diagnostics', 'rvn-compare-products-for-woocommerce' ); ?></h1>
			<p><?php esc_html_e( 'Read-only information about this site. Copy the report below and send it to the plugin developer. Nothing is sent anywhere automatically.', 'rvn-compare-products-for-woocommerce' ); ?></p>

			<table class="widefat striped rvn-compare-status">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Component', 'rvn-compare-products-for-woocommerce' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Current', 'rvn-compare-products-for-woocommerce' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<th scope="row"><?php echo esc_html( $row['label'] ); ?></th>
							<td><?php echo esc_html( $row['value'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Report', 'rvn-compare-products-for-woocommerce' ); ?></h2>
			<p><?php esc_html_e( 'Copy this text and paste it into your message.', 'rvn-compare-products-for-woocommerce' ); ?></p>
			<textarea class="large-text code" rows="14" readonly aria-label="<?php echo esc_attr__( 'Diagnostics report', 'rvn-compare-products-for-woocommerce' ); ?>"><?php echo esc_textarea( $report ); ?></textarea>
		</div>
		<?php
	}

	/**
	 * Собирает строки отчёта об окружении.
	 *
	 * @return array<int, array{label: string, value: string}>
	 */
	private static function rows(): array {
		$settings = Settings::all();
		$user_id  = (int) get_current_user_id();

		return array(
			array(
				'label' => __( 'Plugin', 'rvn-compare-products-for-woocommerce' ),
				'value' => RVN_COMPARE_VERSION,
			),
			array(
				'label' => 'WordPress',
				'value' => (string) get_bloginfo( 'version' ),
			),
			array(
				'label' => 'WooCommerce',
				'value' => defined( 'WC_VERSION' ) ? (string) WC_VERSION : __( 'not active', 'rvn-compare-products-for-woocommerce' ),
			),
			array(
				'label' => 'PHP',
				'value' => PHP_VERSION,
			),
			array(
				'label' => __( 'Memory limit', 'rvn-compare-products-for-woocommerce' ),
				'value' => self::memory_limit(),
			),
			array(
				'label' => __( 'Theme', 'rvn-compare-products-for-woocommerce' ),
				'value' => wp_get_theme()->get( 'Name' ) . ' ' . wp_get_theme()->get( 'Version' ),
			),
			array(
				'label' => __( 'Active plugins', 'rvn-compare-products-for-woocommerce' ),
				'value' => self::active_plugins(),
			),
			array(
				'label' => __( 'Order storage', 'rvn-compare-products-for-woocommerce' ),
				'value' => self::uses_hpos()
					? __( 'High-Performance Order Storage', 'rvn-compare-products-for-woocommerce' )
					: __( 'WordPress posts storage (legacy)', 'rvn-compare-products-for-woocommerce' ),
			),
			array(
				'label' => __( 'Object cache', 'rvn-compare-products-for-woocommerce' ),
				'value' => wp_using_ext_object_cache()
					? __( 'external', 'rvn-compare-products-for-woocommerce' )
					: __( 'built-in', 'rvn-compare-products-for-woocommerce' ),
			),
			array(
				'label' => __( 'Page cache', 'rvn-compare-products-for-woocommerce' ),
				'value' => self::page_cache(),
			),
			array(
				'label' => __( 'REST endpoint', 'rvn-compare-products-for-woocommerce' ),
				'value' => rest_url( 'rvn-compare/v1/list' ),
			),
			array(
				'label' => __( 'Comparison settings', 'rvn-compare-products-for-woocommerce' ),
				'value' => sprintf(
					/* translators: 1: Total products limit. 2: Per category limit. 3: Comparison rule. */
					__( '%1$d in total, %2$d per category, rule: %3$s', 'rvn-compare-products-for-woocommerce' ),
					(int) $settings['limit_total'],
					(int) $settings['limit_per_category'],
					(string) $settings['compare_rule']
				),
			),
			array(
				'label' => __( 'Your comparison list', 'rvn-compare-products-for-woocommerce' ),
				'value' => self::list_summary( $user_id ),
			),
		);
	}

	/**
	 * Краткая строка о текущем списке пользователя.
	 *
	 * @param int $user_id ID пользователя.
	 * @return string
	 */
	private static function list_summary( int $user_id ): string {
		$ids = Storage::get_ids( $user_id );

		if ( array() === $ids ) {
			return __( 'empty', 'rvn-compare-products-for-woocommerce' );
		}

		return sprintf(
			/* translators: %d: Number of products in the list. */
			__( '%d products', 'rvn-compare-products-for-woocommerce' ),
			count( $ids )
		);
	}

	/**
	 * Активные плагины одной строкой: название и версия.
	 *
	 * @return string
	 */
	private static function active_plugins(): string {
		$names = array();

		foreach ( (array) get_option( 'active_plugins', array() ) as $plugin ) {
			$data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin, false, false );

			if ( ! empty( $data['Name'] ) ) {
				$names[] = $data['Name'] . ( ! empty( $data['Version'] ) ? ' ' . $data['Version'] : '' );
			}
		}

		return array() === $names ? __( 'none', 'rvn-compare-products-for-woocommerce' ) : implode( ', ', $names );
	}

	/**
	 * Лимит памяти PHP в удобном виде.
	 *
	 * @return string
	 */
	private static function memory_limit(): string {
		$raw = (string) ini_get( 'memory_limit' );

		if ( '-1' === $raw ) {
			return __( 'unlimited', 'rvn-compare-products-for-woocommerce' );
		}

		$bytes = wp_convert_hr_to_bytes( $raw );

		return $bytes > 0
			? sprintf(
				/* translators: 1: Human-readable size. 2: Raw value from php.ini. */
				__( '%1$s (%2$s)', 'rvn-compare-products-for-woocommerce' ),
				size_format( $bytes ),
				$raw
			)
			: $raw;
	}

	/**
	 * Состояние кэша страниц: плагин и включённый файл раннего кэша.
	 *
	 * Важно для проверки плагина: персональный список сравнения обязан
	 * работать на сайте с включённым кэшем страниц.
	 *
	 * @return string
	 */
	private static function page_cache(): string {
		$plugins = array(
			'wp-super-cache/wp-cache.php'         => 'WP Super Cache',
			'w3-total-cache/w3-total-cache.php'   => 'W3 Total Cache',
			'litespeed-cache/litespeed-cache.php' => 'LiteSpeed Cache',
			'wp-rocket/wp-rocket.php'             => 'WP Rocket',
			'cache-enabler/cache-enabler.php'     => 'Cache Enabler',
		);

		$active = array();

		foreach ( $plugins as $file => $name ) {
			if ( is_plugin_active( $file ) ) {
				$active[] = $name;
			}
		}

		$early = defined( 'WP_CACHE' ) && WP_CACHE ? __( 'advanced-cache.php enabled', 'rvn-compare-products-for-woocommerce' ) : __( 'disabled', 'rvn-compare-products-for-woocommerce' );
		$list  = array() === $active ? __( 'no caching plugin', 'rvn-compare-products-for-woocommerce' ) : implode( ', ', $active );

		return $list . ' — ' . $early;
	}

	/**
	 * Включено ли высокопроизводительное хранение заказов.
	 *
	 * @return bool
	 */
	private static function uses_hpos(): bool {
		return class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' )
			&& \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
	}

	/**
	 * Формирует текст отчёта для копирования.
	 *
	 * @param array<int, array{label: string, value: string}> $rows Строки отчёта.
	 * @return string
	 */
	private static function report_text( array $rows ): string {
		$lines = array( 'RVN Compare diagnostics — ' . gmdate( 'Y-m-d' ) );

		foreach ( $rows as $row ) {
			$lines[] = $row['label'] . ': ' . $row['value'];
		}

		return implode( PHP_EOL, $lines );
	}
}
