<?php
/**
 * Страница «Сравнение» в меню RVN.
 *
 * В сборке 0.1.0 это заглушка с таблицей состояния системы;
 * полноценные настройки появятся в следующих итерациях.
 *
 * @package RVN_Compare
 */

namespace RVN_Compare\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Выводит страницу настроек плагина.
 */
final class Settings_Page {

	/**
	 * Выводит страницу.
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
		?>
		<div class="wrap rvn-compare-settings">
			<h1><?php esc_html_e( 'Compare settings', 'rvn-compare-products-for-woocommerce' ); ?></h1>
			<?php
			// Это чтение вкладки ничего не меняет. Изменения проходят отдельный POST с nonce.
			$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'page'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$tab = in_array( $tab, array( 'page', 'table', 'system' ), true ) ? $tab : 'page';
			?>
			<nav class="nav-tab-wrapper" aria-label="<?php echo esc_attr__( 'Compare settings sections', 'rvn-compare-products-for-woocommerce' ); ?>">
				<a class="nav-tab <?php echo 'page' === $tab ? 'nav-tab-active' : ''; ?>" href="
				<?php
				echo esc_url(
					add_query_arg(
						array(
							'page' => Admin::PAGE_SLUG,
							'tab'  => 'page',
						),
						admin_url( 'admin.php' )
					)
				);
				?>
									"><?php esc_html_e( 'Page', 'rvn-compare-products-for-woocommerce' ); ?></a>
				<a class="nav-tab <?php echo 'table' === $tab ? 'nav-tab-active' : ''; ?>" href="
				<?php
				echo esc_url(
					add_query_arg(
						array(
							'page' => Admin::PAGE_SLUG,
							'tab'  => 'table',
						),
						admin_url( 'admin.php' )
					)
				);
				?>
									"><?php esc_html_e( 'Comparison fields', 'rvn-compare-products-for-woocommerce' ); ?></a>
				<a class="nav-tab <?php echo 'system' === $tab ? 'nav-tab-active' : ''; ?>" href="
				<?php
				echo esc_url(
					add_query_arg(
						array(
							'page' => Admin::PAGE_SLUG,
							'tab'  => 'system',
						),
						admin_url( 'admin.php' )
					)
				);
				?>
									"><?php esc_html_e( 'System status', 'rvn-compare-products-for-woocommerce' ); ?></a>
			</nav>
			<?php if ( 'page' === $tab ) : ?>
				<?php Page_Settings::render(); ?>
			<?php elseif ( 'table' === $tab ) : ?>
				<?php Table_Settings::render(); ?>
			<?php else : ?>
			<h2><?php esc_html_e( 'System status', 'rvn-compare-products-for-woocommerce' ); ?></h2>
			<table class="widefat striped rvn-compare-status">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Component', 'rvn-compare-products-for-woocommerce' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Current', 'rvn-compare-products-for-woocommerce' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Required', 'rvn-compare-products-for-woocommerce' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'rvn-compare-products-for-woocommerce' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( self::status_rows() as $row ) : ?>
						<tr>
							<th scope="row"><?php echo esc_html( $row['label'] ); ?></th>
							<td><?php echo esc_html( $row['current'] ); ?></td>
							<td><?php echo esc_html( $row['required'] ); ?></td>
							<td><?php echo esc_html( $row['status'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Собирает строки таблицы состояния системы.
	 *
	 * @return array<int, array{label: string, current: string, required: string, status: string}>
	 */
	private static function status_rows(): array {
		$wp_version = (string) get_bloginfo( 'version' );
		$wc_version = defined( 'WC_VERSION' ) ? (string) WC_VERSION : '';

		return array(
			array(
				'label'    => __( 'Plugin', 'rvn-compare-products-for-woocommerce' ),
				'current'  => RVN_COMPARE_VERSION,
				'required' => '—',
				'status'   => __( 'OK', 'rvn-compare-products-for-woocommerce' ),
			),
			self::version_row( 'WordPress', $wp_version, RVN_COMPARE_MIN_WP, is_wp_version_compatible( RVN_COMPARE_MIN_WP ) ),
			self::version_row( 'WooCommerce', $wc_version, RVN_COMPARE_MIN_WC, '' !== $wc_version && version_compare( $wc_version, RVN_COMPARE_MIN_WC, '>=' ) ),
			self::version_row( 'PHP', PHP_VERSION, RVN_COMPARE_MIN_PHP, version_compare( PHP_VERSION, RVN_COMPARE_MIN_PHP, '>=' ) ),
			array(
				'label'    => __( 'Order storage', 'rvn-compare-products-for-woocommerce' ),
				'current'  => self::uses_hpos()
					? __( 'High-Performance Order Storage', 'rvn-compare-products-for-woocommerce' )
					: __( 'WordPress posts storage (legacy)', 'rvn-compare-products-for-woocommerce' ),
				'required' => __( 'Not required', 'rvn-compare-products-for-woocommerce' ),
				'status'   => __( 'Compatible', 'rvn-compare-products-for-woocommerce' ),
			),
		);
	}

	/**
	 * Формирует строку таблицы для компонента с минимальной версией.
	 *
	 * @param string $label    Название компонента.
	 * @param string $current  Установленная версия.
	 * @param string $required Минимальная версия.
	 * @param bool   $is_ok    Требование выполнено.
	 * @return array{label: string, current: string, required: string, status: string}
	 */
	private static function version_row( string $label, string $current, string $required, bool $is_ok ): array {
		return array(
			'label'    => $label,
			'current'  => $current,
			'required' => sprintf(
				/* translators: %s: Minimum version number. */
				__( '%s or newer', 'rvn-compare-products-for-woocommerce' ),
				$required
			),
			'status'   => $is_ok
				? __( 'OK', 'rvn-compare-products-for-woocommerce' )
				: __( 'Update required', 'rvn-compare-products-for-woocommerce' ),
		);
	}

	/**
	 * Определяет, включено ли высокопроизводительное хранение заказов (HPOS).
	 *
	 * @return bool
	 */
	private static function uses_hpos(): bool {
		return class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' )
			&& \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
	}
}
