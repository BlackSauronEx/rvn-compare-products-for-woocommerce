<?php
/**
 * Автоматический вывод кнопки сравнения.
 *
 * @package RVN_Compare
 */

namespace RVN_Compare\Frontend;

use RVN_Compare\Limits;
use RVN_Compare\Settings;
use RVN_Compare\Visibility;

defined( 'ABSPATH' ) || exit;

/**
 * Ставит кнопку сравнения на карточки товаров и на страницу товара.
 *
 * Поддерживаются две разметки: классические шаблоны WooCommerce (хуки) и
 * блочные шаблоны (фильтр вывода блоков). Позиции «на изображении» выносятся
 * поверх картинки: положение уточняет скрипт, а если нужного контейнера нет,
 * кнопка остаётся «после кнопки Купить» — так она не теряется ни в одной теме.
 */
final class Buttons {

	/**
	 * Подключает обработчики вывода.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'woocommerce_after_shop_loop_item', array( self::class, 'loop_button' ), 20 );

		// Классический шаблон страницы товара: одна позиция — один обработчик.
		add_action( 'woocommerce_single_product_summary', array( self::class, 'single_above_title' ), 5 );
		add_action( 'woocommerce_single_product_summary', array( self::class, 'single_below_title' ), 15 );
		add_action( 'woocommerce_before_add_to_cart_button', array( self::class, 'single_before_cart' ), 10 );
		add_action( 'woocommerce_after_add_to_cart_button', array( self::class, 'single_after_cart' ), 10 );
		add_action( 'woocommerce_before_single_product_summary', array( self::class, 'single_image_slot' ), 21 );

		add_filter( 'render_block', array( self::class, 'block_button' ), 10, 2 );
	}

	/**
	 * Кнопка над заголовком товара.
	 *
	 * @return void
	 */
	public static function single_above_title(): void {
		self::render_single( 'above_title' );
	}

	/**
	 * Кнопка под заголовком товара.
	 *
	 * @return void
	 */
	public static function single_below_title(): void {
		self::render_single( 'below_title' );
	}

	/**
	 * Кнопка перед кнопкой «Купить».
	 *
	 * @return void
	 */
	public static function single_before_cart(): void {
		self::render_single( 'before_cart' );
	}

	/**
	 * Кнопка после кнопки «Купить».
	 *
	 * @return void
	 */
	public static function single_after_cart(): void {
		self::render_single( 'after_cart' );
	}

	/**
	 * Слот для позиции поверх изображения товара.
	 *
	 * Точное положение задаёт скрипт: слот переносится в контейнер изображения.
	 *
	 * @return void
	 */
	public static function single_image_slot(): void {
		self::render_single( 'image_tl' );
		self::render_single( 'image_tr' );
		self::render_single( 'image_bl' );
		self::render_single( 'image_br' );
	}

	/**
	 * Выводит кнопку на странице товара, если выбрана заданная позиция.
	 *
	 * Обработчики позиций вызываются по очереди, поэтому совпадение проверяется
	 * внутри: лишние вызовы ничего не выводят.
	 *
	 * @param string $expected Позиция, для которой вызван обработчик.
	 * @return void
	 */
	private static function render_single( string $expected ): void {
		$settings = Settings::all();

		if ( $expected !== (string) $settings['single_position'] ) {
			return;
		}

		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		$product_id = self::current_product_id();

		if ( ! Button_Renderer::is_available( $product_id ) || Visibility::product_blocked( $product_id, Visibility::CONTEXT_SINGLE, $settings ) ) {
			return;
		}

		// Без подключения стилей и скрипта кнопка была бы нерабочей.
		Assets::enqueue();

		// Разметка кнопки уже безопасна: значения экранированы в рендерере, иконки — из кода плагина.
		echo self::button_markup( $product_id, $settings, $expected, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Выводит кнопку на карточке товара в выбранной позиции.
	 *
	 * @return void
	 */
	public static function loop_button(): void {
		$settings = Settings::all();
		$position = (string) $settings['card_position'];

		if ( 'off' === $position ) {
			return;
		}

		$product_id = self::current_product_id();

		if ( ! Button_Renderer::is_available( $product_id ) || Visibility::product_blocked( $product_id, Visibility::CONTEXT_CARD, $settings ) ) {
			return;
		}

		// Без подключения стилей и скрипта кнопка была бы нерабочей: файлы грузятся только там, где она есть.
		Assets::enqueue();

		$html = Button_Renderer::button(
			$product_id,
			$settings,
			array(
				'mode'        => (string) $settings['card_mode'],
				'mode_mobile' => (string) $settings['card_mode_mobile'],
			)
		);

		// Позиции «на изображении» размечаются отдельной обёрткой: точное место задаёт скрипт.
		if ( in_array( $position, Settings::IMAGE_POSITIONS, true ) ) {
			$html = sprintf(
				'<span class="rvn-compare-overlay rvn-overlay-%1$s" data-rvn-overlay="%1$s">%2$s</span>',
				esc_attr( str_replace( 'image_', '', $position ) ),
				$html
			);
		}

		// Разметка кнопки уже безопасна: значения экранированы в рендерере, иконки — из кода плагина.
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- см. комментарий выше.
	}

	/**
	 * Добавляет кнопку в блочные шаблоны карточек и страницы товара.
	 *
	 * @param string               $content Разметка блока.
	 * @param array<string, mixed> $block   Данные блока.
	 * @return string
	 */
	public static function block_button( string $content, array $block ): string {
		$name = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';

		if ( ! self::is_product_block( $name ) ) {
			return $content;
		}

		$settings   = Settings::all();
		$product_id = self::current_product_id( $block );

		if ( ! Button_Renderer::is_available( $product_id ) ) {
			return $content;
		}

		$is_single = self::is_single_product_template();
		$position  = (string) ( $is_single ? $settings['single_position'] : $settings['card_position'] );
		$context   = $is_single ? Visibility::CONTEXT_SINGLE : Visibility::CONTEXT_CARD;

		if ( 'off' === $position || Visibility::product_blocked( $product_id, $context, $settings ) ) {
			return $content;
		}

		Assets::enqueue();

		$before = array(
			'woocommerce/product-title'  => 'above_title',
			'woocommerce/product-button' => 'before_cart',
			'woocommerce/product-image'  => 'image_tl',
		);

		$after = array(
			'woocommerce/product-image'  => array( 'image_br', 'image_tl', 'image_tr', 'image_bl' ),
			'woocommerce/product-title'  => 'below_title',
			'woocommerce/product-button' => 'after_cart',
		);

		if ( isset( $before[ $name ] ) && $before[ $name ] === $position ) {
			return self::button_markup( $product_id, $settings, $position, $is_single ) . $content;
		}

		$expected = $after[ $name ] ?? array();

		if ( in_array( $position, (array) $expected, true ) ) {
			return $content . self::button_markup( $product_id, $settings, $position, $is_single );
		}

		return $content;
	}

	/**
	 * Готовая разметка кнопки с обёрткой для позиций поверх изображения.
	 *
	 * @param int                  $product_id ID товара.
	 * @param array<string, mixed> $settings   Настройки плагина.
	 * @param string               $position   Позиция кнопки.
	 * @param bool                 $is_single  Это страница товара.
	 * @return string
	 */
	private static function button_markup( int $product_id, array $settings, string $position, bool $is_single ): string {
		$mode       = $is_single ? (string) $settings['single_mode'] : (string) $settings['card_mode'];
		$mode_phone = $is_single ? (string) $settings['single_mode_mobile'] : (string) $settings['card_mode_mobile'];

		$html = Button_Renderer::button(
			$product_id,
			$settings,
			array(
				'mode'        => $mode,
				'mode_mobile' => $mode_phone,
			)
		);

		if ( in_array( $position, Settings::IMAGE_POSITIONS, true ) ) {
			return sprintf(
				'<span class="rvn-compare-overlay rvn-overlay-%1$s" data-rvn-overlay="%1$s">%2$s</span>',
				esc_attr( str_replace( 'image_', '', $position ) ),
				$html
			);
		}

		return '<span class="rvn-compare-slot rvn-slot-' . esc_attr( $position ) . '">' . $html . '</span>';
	}

	/**
	 * Блок относится к товарам WooCommerce.
	 *
	 * @param string $name Имя блока.
	 * @return bool
	 */
	private static function is_product_block( string $name ): bool {
		return in_array(
			$name,
			array(
				'woocommerce/product-image',
				'woocommerce/product-title',
				'woocommerce/product-button',
			),
			true
		);
	}

	/**
	 * Текущий шаблон — страница одного товара.
	 *
	 * @return bool
	 */
	private static function is_single_product_template(): bool {
		return function_exists( 'is_product' ) && is_product();
	}

	/**
	 * Определяет товар текущего вывода.
	 *
	 * В блочных шаблонах ID берётся из контекста блока, в классических —
	 * из глобального объекта товара.
	 *
	 * @param array<string, mixed> $block Данные блока.
	 * @return int
	 */
	private static function current_product_id( array $block = array() ): int {
		if ( isset( $block['context']['postId'] ) ) {
			return absint( $block['context']['postId'] );
		}

		if ( isset( $block['attrs']['postId'] ) ) {
			return absint( $block['attrs']['postId'] );
		}

		global $product;

		if ( $product instanceof \WC_Product ) {
			return (int) $product->get_id();
		}

		$post_id = get_the_ID();

		return is_int( $post_id ) ? $post_id : 0;
	}

	/**
	 * Нужно ли ограничение по лимиту для текущего списка (используется в скрипте).
	 *
	 * @param int[]                $ids      Текущий список.
	 * @param int                  $product_id Товар.
	 * @param array<string, mixed> $settings Настройки.
	 * @return array<string, mixed>
	 */
	public static function limit_state( array $ids, int $product_id, array $settings ): array {
		return Limits::check_add( $ids, $product_id, $settings );
	}
}
