<?php
/**
 * Шорткоды плагина.
 *
 * @package RVN_Compare
 */

namespace RVN_Compare\Frontend;

use RVN_Compare\Compare_Service;
use RVN_Compare\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Регистрирует шорткоды сравнения.
 *
 * Все шорткоды принимают аргумент `class` для собственных классов, значения
 * проверяются, а вывод экранируется внутри рендерера.
 */
final class Shortcodes {

	/**
	 * Подключает шорткоды.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_shortcode( 'rvn-compare-table', array( self::class, 'table' ) );
		add_shortcode( 'rvn-compare-button', array( self::class, 'button' ) );
		add_shortcode( 'rvn-compare-counter', array( self::class, 'counter' ) );
		add_shortcode( 'rvn-compare-counter-button', array( self::class, 'counter_button' ) );
		add_shortcode( 'rvn-compare-clear', array( self::class, 'clear' ) );
		add_shortcode( 'rvn-compare-progress', array( self::class, 'progress' ) );
	}

	/**
	 * `[rvn-compare-table]` — таблица сравнения или фиксированный список товаров.
	 *
	 * Персональные ID в кэшируемый HTML не вставляются: браузер загрузит список
	 * через REST. У фиксированной таблицы products=12,34 все ID предварительно
	 * проверяются и не добавляются в список сравнения покупателя.
	 *
	 * @param array<string, mixed>|string $atts Аргументы: products, class.
	 * @return string
	 */
	public static function table( $atts ): string {
		$args  = shortcode_atts(
			array(
				'products' => '',
				'class'    => '',
			),
			$atts,
			'rvn-compare-table'
		);
		$fixed = array();

		if ( is_string( $args['products'] ) && '' !== trim( $args['products'] ) ) {
			foreach ( \RVN_Compare\Storage::normalize_ids( $args['products'] ) as $id ) {
				if ( Compare_Service::product_available( $id ) ) {
					$fixed[] = $id;
				}
			}
		}

		Assets::enqueue_table();

		$is_static = '' !== trim( (string) $args['products'] );

		return Table_View::render( (string) $args['class'], $fixed, $is_static );
	}

	/**
	 * `[rvn-compare-button]` — кнопка сравнения для товара.
	 *
	 * Без аргументов работает только внутри карточки товара: ID берётся из
	 * текущего товара. Вне карточки укажите `id`.
	 *
	 * @param array<string, mixed>|string $atts Аргументы шорткода.
	 * @return string
	 */
	public static function button( $atts ): string {
		$args = shortcode_atts(
			array(
				'id'    => 0,
				'mode'  => '',
				'class' => '',
			),
			$atts,
			'rvn-compare-button'
		);

		$settings   = Settings::all();
		$product_id = absint( $args['id'] );

		if ( $product_id <= 0 ) {
			$product_id = self::current_product_id();
		}

		if ( $product_id <= 0 ) {
			// Вне карточки товара без ID кнопку вывести невозможно — сообщаем об этом разработчику.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				do_action( 'rvn_compare_shortcode_notice', 'rvn-compare-button', 'id' );
			}

			return '';
		}

		if ( ! Compare_Service::product_available( $product_id ) ) {
			return '';
		}

		Assets::enqueue();

		return Button_Renderer::button(
			$product_id,
			$settings,
			array(
				'mode'        => '' !== (string) $args['mode'] ? (string) $args['mode'] : (string) $settings['card_mode'],
				'mode_mobile' => '' !== (string) $args['mode'] ? 'inherit' : (string) $settings['card_mode_mobile'],
				'class'       => (string) $args['class'],
			)
		);
	}

	/**
	 * `[rvn-compare-counter]` — число товаров в списке.
	 *
	 * @param array<string, mixed>|string $atts Аргументы шорткода.
	 * @return string
	 */
	public static function counter( $atts ): string {
		$args = shortcode_atts(
			array(
				'text'          => '',
				'text_position' => 'after',
				'url'           => '',
				'class'         => '',
			),
			$atts,
			'rvn-compare-counter'
		);

		Assets::enqueue();

		return Button_Renderer::counter(
			Settings::all(),
			array(
				'text'          => (string) $args['text'],
				'text_position' => (string) $args['text_position'],
				'href'          => self::safe_url( (string) $args['url'] ),
				'class'         => (string) $args['class'],
			)
		);
	}

	/**
	 * `[rvn-compare-counter-button]` — кнопка «Сравнение» со счётчиком.
	 *
	 * @param array<string, mixed>|string $atts Аргументы шорткода.
	 * @return string
	 */
	public static function counter_button( $atts ): string {
		$args = shortcode_atts(
			array(
				'label' => '',
				'mode'  => 'text_icon',
				'url'   => '',
				'link'  => 'yes',
				'class' => '',
			),
			$atts,
			'rvn-compare-counter-button'
		);

		Assets::enqueue();

		return Button_Renderer::counter_button(
			Settings::all(),
			array(
				'label' => (string) $args['label'],
				'mode'  => (string) $args['mode'],
				'href'  => self::safe_url( (string) $args['url'] ),
				'link'  => 'no' !== strtolower( (string) $args['link'] ),
				'class' => (string) $args['class'],
			)
		);
	}

	/**
	 * `[rvn-compare-clear]` — кнопка очистки списка.
	 *
	 * @param array<string, mixed>|string $atts Аргументы шорткода.
	 * @return string
	 */
	public static function clear( $atts ): string {
		$args = shortcode_atts(
			array(
				'label' => '',
				'scope' => 'all',
				'class' => '',
			),
			$atts,
			'rvn-compare-clear'
		);

		Assets::enqueue();

		return Button_Renderer::clear_button(
			Settings::all(),
			array(
				'label' => (string) $args['label'],
				'scope' => (string) $args['scope'],
				'class' => (string) $args['class'],
			)
		);
	}

	/**
	 * `[rvn-compare-progress]` — «3 из 12» по текущей вкладке.
	 *
	 * @param array<string, mixed>|string $atts Аргументы шорткода.
	 * @return string
	 */
	public static function progress( $atts ): string {
		$args = shortcode_atts(
			array( 'class' => '' ),
			$atts,
			'rvn-compare-progress'
		);

		Assets::enqueue();

		return Button_Renderer::progress( Settings::all(), array( 'class' => (string) $args['class'] ) );
	}

	/**
	 * Текущий товар из глобального объекта WooCommerce.
	 *
	 * @return int
	 */
	private static function current_product_id(): int {
		global $product;

		if ( $product instanceof \WC_Product ) {
			return (int) $product->get_id();
		}

		$post_id = get_the_ID();

		return is_int( $post_id ) ? $post_id : 0;
	}

	/**
	 * Проверяет адрес из аргументов шорткода.
	 *
	 * Разрешены только адреса этого сайта: чужие ссылки в разметке не нужны.
	 *
	 * @param string $url Адрес из аргументов.
	 * @return string
	 */
	private static function safe_url( string $url ): string {
		if ( '' === $url ) {
			return '';
		}

		$clean = esc_url_raw( $url );

		if ( '' === $clean ) {
			return '';
		}

		if ( '/' === substr( $clean, 0, 1 ) && '//' !== substr( $clean, 0, 2 ) ) {
			return $clean;
		}

		$site   = wp_parse_url( home_url( '/' ) );
		$target = wp_parse_url( $clean );

		$same_host = is_array( $site )
			&& is_array( $target )
			&& ! empty( $site['host'] )
			&& ! empty( $target['host'] )
			&& strtolower( (string) $site['host'] ) === strtolower( (string) $target['host'] );

		return $same_host ? $clean : '';
	}
}
