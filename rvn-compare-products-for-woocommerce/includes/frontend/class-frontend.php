<?php
/**
 * Запуск компонентов сайта.
 *
 * @package RVN_Compare
 */

namespace RVN_Compare\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Подключает всё, что нужно покупателю: ассеты, кнопки и шорткоды.
 */
final class Frontend {

	/**
	 * Подключает обработчики сайта.
	 *
	 * @return void
	 */
	public static function init(): void {
		Assets::init();
		Buttons::init();
		Shortcodes::init();
		Comparison_Page::init();

		add_action( 'wp_head', array( self::class, 'print_safe_area_meta' ), 1 );
	}

	/**
	 * Сообщает о позиции «на изображении» вне карточки товара в режиме отладки.
	 *
	 * @param string $shortcode Имя шорткода.
	 * @param string $argument  Отсутствующий аргумент.
	 * @return void
	 */
	public static function log_shortcode_notice( string $shortcode, string $argument ): void {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error -- только при включённом WP_DEBUG.
		trigger_error(
			esc_html(
				sprintf(
					/* translators: 1: Shortcode name. 2: Missing argument name. */
					__( 'RVN Compare: shortcode %1$s outside a product loop needs the "%2$s" argument.', 'rvn-compare-products-for-woocommerce' ),
					$shortcode,
					$argument
				)
			),
			E_USER_NOTICE
		);
	}

	/**
	 * Добавляет поддержку безопасных зон экрана.
	 *
	 * Свой метатег viewport WordPress не выводит, а без `viewport-fit=cover`
	 * браузер не сообщает размеры вырезов и системной полоски. Поэтому вместо
	 * правки темы скрипт аккуратно дописывает параметр к существующему метатегу.
	 *
	 * @return void
	 */
	public static function print_safe_area_meta(): void {
		?>
		<script>
		/* Дополняет viewport темой поддержкой безопасных зон: без этого браузер не сообщает размеры вырезов. */
		(function () {
			var meta = document.querySelector('meta[name="viewport"]');

			if (meta && meta.content.indexOf('viewport-fit') === -1) {
				meta.content = meta.content + ', viewport-fit=cover';
			}
		})();
		</script>
		<?php
	}
}
