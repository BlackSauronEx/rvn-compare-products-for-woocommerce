<?php
/**
 * Проверка минимальных версий WordPress и WooCommerce.
 *
 * @package RVN_Compare
 */

namespace RVN_Compare;

defined( 'ABSPATH' ) || exit;

/**
 * Проверяет окружение и объясняет администратору, что нужно исправить.
 *
 * Версия PHP проверяется раньше — в главном файле плагина.
 */
final class Requirements {

	/**
	 * Главный файл WooCommerce относительно папки плагинов.
	 *
	 * @var string
	 */
	private const WOOCOMMERCE_FILE = 'woocommerce/woocommerce.php';

	/**
	 * Нарушенные требования.
	 *
	 * @var array<int, array{type: string, required: string, current: string}>
	 */
	private array $problems = array();

	/**
	 * Проверяет версии WordPress и WooCommerce.
	 *
	 * @return bool True, если все требования выполнены.
	 */
	public function are_met(): bool {
		$this->problems = array();

		if ( ! is_wp_version_compatible( RVN_COMPARE_MIN_WP ) ) {
			$this->problems[] = array(
				'type'     => 'wp_version',
				'required' => RVN_COMPARE_MIN_WP,
				'current'  => (string) get_bloginfo( 'version' ),
			);
		}

		if ( ! class_exists( 'WooCommerce', false ) || ! defined( 'WC_VERSION' ) ) {
			$this->problems[] = array(
				'type'     => 'woocommerce_missing',
				'required' => RVN_COMPARE_MIN_WC,
				'current'  => '',
			);
		} elseif ( version_compare( (string) WC_VERSION, RVN_COMPARE_MIN_WC, '<' ) ) {
			$this->problems[] = array(
				'type'     => 'woocommerce_version',
				'required' => RVN_COMPARE_MIN_WC,
				'current'  => (string) WC_VERSION,
			);
		}

		return array() === $this->problems;
	}

	/**
	 * Подключает вывод уведомления о нарушенных требованиях.
	 *
	 * @return void
	 */
	public function register_notice(): void {
		add_action( 'admin_notices', array( $this, 'render_notice' ) );
		add_action( 'network_admin_notices', array( $this, 'render_notice' ) );
	}

	/**
	 * Выводит уведомление со списком проблем и ссылками для их исправления.
	 *
	 * Уведомление исчезает само, как только требования выполнены.
	 *
	 * @return void
	 */
	public function render_notice(): void {
		if ( array() === $this->problems || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		echo '<div class="notice notice-error rvn-compare-requirements-notice"><p><strong>';
		echo esc_html(
			sprintf(
				/* translators: %s: Plugin name. */
				__( '%s is inactive until the following requirements are met:', 'rvn-compare-products-for-woocommerce' ),
				'RVN Compare Products for WooCommerce'
			)
		);
		echo '</strong></p><ul class="ul-disc">';

		foreach ( $this->problems as $problem ) {
			echo '<li>' . esc_html( $this->describe( $problem ) ) . '</li>';
		}

		echo '</ul>';

		$links = $this->resolution_links();

		if ( array() !== $links ) {
			echo '<p>';

			foreach ( $links as $index => $link ) {
				if ( $index > 0 ) {
					echo ' | ';
				}

				printf( '<a href="%1$s">%2$s</a>', esc_url( $link['url'] ), esc_html( $link['label'] ) );
			}

			echo '</p>';
		}

		echo '</div>';
	}

	/**
	 * Формирует понятное описание нарушенного требования.
	 *
	 * @param array{type: string, required: string, current: string} $problem Нарушенное требование.
	 * @return string
	 */
	private function describe( array $problem ): string {
		if ( 'wp_version' === $problem['type'] ) {
			return sprintf(
				/* translators: 1: Required WordPress version. 2: Current WordPress version. */
				__( 'WordPress %1$s or newer is required. Current version: %2$s.', 'rvn-compare-products-for-woocommerce' ),
				$problem['required'],
				$problem['current']
			);
		}

		if ( 'woocommerce_version' === $problem['type'] ) {
			return sprintf(
				/* translators: 1: Required WooCommerce version. 2: Current WooCommerce version. */
				__( 'WooCommerce %1$s or newer is required. Current version: %2$s.', 'rvn-compare-products-for-woocommerce' ),
				$problem['required'],
				$problem['current']
			);
		}

		return sprintf(
			/* translators: %s: Required WooCommerce version. */
			__( 'WooCommerce %s or newer must be installed and active.', 'rvn-compare-products-for-woocommerce' ),
			$problem['required']
		);
	}

	/**
	 * Собирает ссылки, которые помогают исправить нарушенные требования.
	 *
	 * Ссылка активации WooCommerce подписана ключом (nonce) ядра WordPress
	 * и показывается только пользователям с соответствующими правами:
	 * URL без валидного ключа подделать нельзя.
	 *
	 * @return array<int, array{url: string, label: string}>
	 */
	private function resolution_links(): array {
		$links = array();
		$types = wp_list_pluck( $this->problems, 'type' );

		if ( in_array( 'woocommerce_missing', $types, true ) ) {
			$installed = file_exists( trailingslashit( WP_PLUGIN_DIR ) . self::WOOCOMMERCE_FILE );

			if ( $installed && current_user_can( 'activate_plugin', self::WOOCOMMERCE_FILE ) ) {
				$links[] = array(
					'url'   => wp_nonce_url(
						self_admin_url( 'plugins.php?action=activate&plugin=' . rawurlencode( self::WOOCOMMERCE_FILE ) ),
						'activate-plugin_' . self::WOOCOMMERCE_FILE
					),
					'label' => __( 'Activate WooCommerce', 'rvn-compare-products-for-woocommerce' ),
				);
			} elseif ( ! $installed && current_user_can( 'install_plugins' ) ) {
				$links[] = array(
					'url'   => self_admin_url( 'plugin-install.php?s=woocommerce&tab=search&type=term' ),
					'label' => __( 'Install WooCommerce', 'rvn-compare-products-for-woocommerce' ),
				);
			}
		}

		$needs_update = in_array( 'wp_version', $types, true ) || in_array( 'woocommerce_version', $types, true );

		if ( $needs_update && current_user_can( 'update_plugins' ) ) {
			$links[] = array(
				'url'   => self_admin_url( 'update-core.php' ),
				'label' => __( 'Go to updates', 'rvn-compare-products-for-woocommerce' ),
			);
		}

		return $links;
	}
}
