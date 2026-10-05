<?php
/**
 * Разметка кнопок и счётчиков сравнения.
 *
 * @package RVN_Compare
 */

namespace RVN_Compare\Frontend;

use RVN_Compare\Compare_Service;
use RVN_Compare\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Собирает HTML кнопок, счётчиков и прогресса лимита.
 *
 * Каждое значение проходит экранирование в момент вывода. Классы и атрибуты
 * собираются только из проверенных значений: произвольные строки не попадают
 * в разметку без очистки.
 */
final class Button_Renderer {

	/**
	 * Иконки кнопок по умолчанию (из требований: весы, столбцы, галочка).
	 *
	 * @var array<string, string>
	 */
	const ICONS = array(
		'compare'     => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>',
		'compare_alt' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3" y="3" width="7" height="18" rx="1.5"></rect><rect x="14" y="3" width="7" height="18" rx="1.5"></rect></svg>',
		'in_list'     => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><polyline points="20 6 9 17 4 12"></polyline></svg>',
	);

	/**
	 * Текст кнопки «Сравнить» с учётом настройки.
	 *
	 * @param array<string, mixed> $settings Настройки плагина.
	 * @return string
	 */
	public static function label_compare( array $settings ): string {
		$custom = isset( $settings['button_label'] ) ? trim( (string) $settings['button_label'] ) : '';

		// Пустое значение означает «использовать перевод», поэтому строка не записывается в настройки.
		return '' !== $custom ? $custom : __( 'Compare', 'rvn-compare-products-for-woocommerce' );
	}

	/**
	 * Текст кнопки «Уже в сравнении» с учётом настройки.
	 *
	 * @param array<string, mixed> $settings Настройки плагина.
	 * @return string
	 */
	public static function label_in_list( array $settings ): string {
		$custom = isset( $settings['button_in_label'] ) ? trim( (string) $settings['button_in_label'] ) : '';

		return '' !== $custom ? $custom : __( 'In comparison', 'rvn-compare-products-for-woocommerce' );
	}

	/**
	 * HTML кнопки сравнения для конкретного товара.
	 *
	 * Кнопка содержит оба состояния; видимое выбирает скрипт по состоянию
	 * списка, поэтому разметка одинакова для кэшируемой страницы.
	 *
	 * @param int                  $product_id ID товара.
	 * @param array<string, mixed> $settings   Настройки плагина.
	 * @param array<string, mixed> $args       Аргументы: mode, class, show_count.
	 * @return string
	 */
	public static function button( int $product_id, array $settings, array $args = array() ): string {
		if ( $product_id <= 0 ) {
			return '';
		}

		$mode       = self::mode( (string) ( $args['mode'] ?? $settings['card_mode'] ) );
		$mode_phone = isset( $args['mode_mobile'] ) ? self::mode( (string) $args['mode_mobile'] ) : 'inherit';
		$classes    = array( 'rvn-compare-button', 'rvn-mode-' . $mode );

		if ( ! empty( $args['class'] ) ) {
			$classes = array_merge( $classes, self::class_list( (string) $args['class'] ) );
		}

		$label   = self::label_compare( $settings );
		$in_list = self::label_in_list( $settings );

		$html = sprintf(
			'<button type="button" class="%1$s" data-rvn-compare-button data-product-id="%2$d" aria-pressed="false" data-label-compare="%3$s" data-label-in-list="%4$s" data-mode-desktop="%5$s" data-mode-mobile="%6$s">',
			esc_attr( implode( ' ', $classes ) ),
			absint( $product_id ),
			esc_attr( $label ),
			esc_attr( $in_list ),
			esc_attr( $mode ),
			esc_attr( 'inherit' === $mode_phone ? 'inherit' : $mode_phone )
		);

		$html .= '<span class="rvn-button-icon rvn-icon-compare">' . self::icon( 'compare' ) . '</span>';
		$html .= '<span class="rvn-button-icon rvn-icon-in-list">' . self::icon( 'in_list' ) . '</span>';
		$html .= '<span class="rvn-button-text rvn-text-compare">' . esc_html( $label ) . '</span>';
		$html .= '<span class="rvn-button-text rvn-text-in-list">' . esc_html( $in_list ) . '</span>';
		$html .= '</button>';

		/**
		 * Фильтрует HTML кнопки сравнения.
		 *
		 * @since 0.3.0
		 *
		 * @param string               $html       Готовая разметка кнопки.
		 * @param int                  $product_id ID товара.
		 * @param array<string, mixed> $settings   Настройки плагина.
		 */
		return (string) apply_filters( 'rvn_compare_button_html', $html, $product_id, $settings );
	}

	/**
	 * HTML кнопки-счётчика для меню и шапки.
	 *
	 * @param array<string, mixed> $settings Настройки плагина.
	 * @param array<string, mixed> $args     Аргументы: mode, href, class, link, count.
	 * @return string
	 */
	public static function counter_button( array $settings, array $args = array() ): string {
		$mode    = self::mode( (string) ( $args['mode'] ?? 'text_icon' ) );
		$link    = ! isset( $args['link'] ) || true === $args['link'];
		$href    = isset( $args['href'] ) && '' !== (string) $args['href'] ? (string) $args['href'] : Comparison_Page::url();
		$classes = array( 'rvn-compare-counter-button', 'rvn-mode-' . $mode );

		if ( ! empty( $args['class'] ) ) {
			$classes = array_merge( $classes, self::class_list( (string) $args['class'] ) );
		}

		$label = isset( $args['label'] ) && '' !== (string) $args['label']
			? (string) $args['label']
			: __( 'Compare', 'rvn-compare-products-for-woocommerce' );

		$inner  = '<span class="rvn-button-icon"><span class="rvn-icon">' . self::icon( 'compare_alt' ) . '</span></span>';
		$inner .= '<span class="rvn-button-text">' . esc_html( $label ) . '</span>';
		$inner .= '<span class="rvn-counter" data-rvn-counter aria-hidden="true">0</span>';

		$tag  = $link ? 'a' : 'span';
		$html = sprintf(
			'<%1$s class="%2$s"%3$s data-rvn-counter-button>%4$s</%1$s>',
			$tag,
			esc_attr( implode( ' ', $classes ) ),
			$link ? ' href="' . esc_url( $href ) . '"' : '',
			$inner
		);

		return $html;
	}

	/**
	 * HTML счётчика: только число, при необходимости с подписью.
	 *
	 * @param array<string, mixed> $settings Настройки плагина.
	 * @param array<string, mixed> $args     Аргументы: text, text_position, href, class.
	 * @return string
	 */
	public static function counter( array $settings, array $args = array() ): string {
		unset( $settings );

		$text     = isset( $args['text'] ) ? sanitize_text_field( (string) $args['text'] ) : '';
		$position = isset( $args['text_position'] ) && 'before' === $args['text_position'] ? 'before' : 'after';
		$classes  = array( 'rvn-compare-counter' );

		if ( ! empty( $args['class'] ) ) {
			$classes = array_merge( $classes, self::class_list( (string) $args['class'] ) );
		}

		$number = '<span class="rvn-counter-value" data-rvn-counter>0</span>';
		$label  = '' !== $text ? '<span class="rvn-counter-text">' . esc_html( $text ) . '</span>' : '';
		$inner  = 'before' === $position ? $label . $number : $number . $label;

		if ( ! empty( $args['href'] ) ) {
			return sprintf(
				'<a class="%1$s" href="%2$s" data-rvn-counter-link>%3$s</a>',
				esc_attr( implode( ' ', $classes ) ),
				esc_url( (string) $args['href'] ),
				$inner
			);
		}

		return sprintf( '<span class="%1$s">%2$s</span>', esc_attr( implode( ' ', $classes ) ), $inner );
	}

	/**
	 * HTML кнопки очистки списка.
	 *
	 * @param array<string, mixed> $settings Настройки плагина.
	 * @param array<string, mixed> $args     Аргументы: label, class, scope.
	 * @return string
	 */
	public static function clear_button( array $settings, array $args = array() ): string {
		unset( $settings );

		$label = isset( $args['label'] ) && '' !== (string) $args['label']
			? (string) $args['label']
			: __( 'Clear all', 'rvn-compare-products-for-woocommerce' );

		$scope   = isset( $args['scope'] ) && 'tab' === $args['scope'] ? 'tab' : 'all';
		$classes = array( 'rvn-compare-clear' );

		if ( ! empty( $args['class'] ) ) {
			$classes = array_merge( $classes, self::class_list( (string) $args['class'] ) );
		}

		return sprintf(
			'<button type="button" class="%1$s" data-rvn-clear data-scope="%2$s" data-label="%3$s">%3$s</button>',
			esc_attr( implode( ' ', $classes ) ),
			esc_attr( $scope ),
			esc_html( $label )
		);
	}

	/**
	 * HTML прогресса лимита категории: «3 из 12».
	 *
	 * @param array<string, mixed> $settings Настройки плагина.
	 * @param array<string, mixed> $args     Аргументы: class.
	 * @return string
	 */
	public static function progress( array $settings, array $args = array() ): string {
		$classes = array( 'rvn-compare-progress' );

		if ( ! empty( $args['class'] ) ) {
			$classes = array_merge( $classes, self::class_list( (string) $args['class'] ) );
		}

		return sprintf(
			'<span class="%1$s" data-rvn-progress data-template="%2$s">%3$s</span>',
			esc_attr( implode( ' ', $classes ) ),
			esc_attr(
				sprintf(
					/* translators: 1: Number of products in the current tab. 2: Limit of products per category. */
					__( '%1$d of %2$d', 'rvn-compare-products-for-woocommerce' ),
					0,
					(int) $settings['limit_per_category']
				)
			),
			esc_html(
				sprintf(
					/* translators: 1: Number of products in the current tab. 2: Limit of products per category. */
					__( '%1$d of %2$d', 'rvn-compare-products-for-woocommerce' ),
					0,
					(int) $settings['limit_per_category']
				)
			)
		);
	}

	/**
	 * Возвращает разметку иконки по имени.
	 *
	 * Разметка иконок фиксирована в коде плагина, поэтому выводится как есть.
	 *
	 * @param string $name Имя иконки.
	 * @return string
	 */
	public static function icon( string $name ): string {
		return self::ICONS[ $name ] ?? self::ICONS['compare'];
	}

	/**
	 * Проверяет режим отображения кнопки.
	 *
	 * @param string $mode Запрошенный режим.
	 * @return string
	 */
	private static function mode( string $mode ): string {
		$mode = sanitize_key( $mode );

		return in_array( $mode, Settings::BUTTON_MODES, true ) ? $mode : 'icon_text';
	}

	/**
	 * Разбирает строку с CSS-классами в список допустимых классов.
	 *
	 * Произвольные символы не попадают в разметку: класс должен состоять
	 * из букв, цифр, дефисов и подчёркиваний.
	 *
	 * @param string $raw Классы через пробел.
	 * @return string[]
	 */
	public static function class_list( string $raw ): array {
		$classes = array();

		$parts = preg_split( '/\s+/', trim( $raw ) );

		foreach ( is_array( $parts ) ? $parts : array() as $class ) {
			$class = sanitize_html_class( $class );

			if ( '' !== $class && strlen( $class ) < 60 ) {
				$classes[] = $class;
			}
		}

		return array_slice( array_unique( $classes ), 0, 5 );
	}

	/**
	 * Данные о товаре для скрипта: ID и текст названия для уведомлений.
	 *
	 * @param int $product_id ID товара.
	 * @return array<string, mixed>
	 */
	public static function product_data( int $product_id ): array {
		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : false;

		if ( ! $product ) {
			return array(
				'id'    => $product_id,
				'title' => '',
			);
		}

		return array(
			'id'    => $product_id,
			'title' => wp_strip_all_tags( $product->get_name() ),
		);
	}

	/**
	 * Проверяет, что товар доступен для сравнения.
	 *
	 * @param int $product_id ID товара.
	 * @return bool
	 */
	public static function is_available( int $product_id ): bool {
		return Compare_Service::product_available( $product_id );
	}
}
