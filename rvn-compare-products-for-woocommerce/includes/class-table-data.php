<?php
/**
 * Подготовка публичных данных сравнения для REST и таблицы.
 *
 * @package RVN_Compare
 */

namespace RVN_Compare;

defined( 'ABSPATH' ) || exit;

/**
 * Извлекает характеристики товаров через CRUD WooCommerce, без прямого SQL.
 *
 * Персональные ID не должны попадать в кэшируемый HTML: этот класс вызывается
 * только через REST, ответ запрещает хранение в общих кэшах. Ни одно поле
 * метаданных не выводится, пока администратор не зарегистрировал его явно.
 */
final class Table_Data {

	/**
	 * Подготавливает таблицу из списка гостя или аккаунта.
	 *
	 * @param int[]                $ids        ID товаров из браузера или списка пользователя.
	 * @param array<string, mixed> $settings   Настройки сравнения.
	 * @param bool                 $single_tab Показывать все товары одной группой без вкладок
	 *                                         категорий — для статического шорткода, где админ
	 *                                         перечислил конкретные ID и ждёт их рядом.
	 * @return array<string, mixed>
	 */
	public static function build( array $ids, array $settings, bool $single_tab = false ): array {
		if ( $single_tab ) {
			// Вкладки категорий здесь вредны: товары разных категорий разъехались бы
			// по вкладкам и посетитель видел бы по одному товару за раз.
			$settings['compare_rule'] = 'all';
		}

		$ids = Storage::normalize_ids( $ids, min( Settings::MAX_TOTAL, (int) $settings['limit_total'] ) );
		$ids = array_values( array_filter( $ids, array( Compare_Service::class, 'product_available' ) ) );

		// Прогреваем кэш WordPress для всех товаров одним запросом, а не по полю.
		if ( array() !== $ids ) {
			_prime_post_caches( $ids, true, true );
		}

		$products = array();

		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );

			if ( ! $product instanceof \WC_Product ) {
				continue;
			}

			$products[ $id ] = $product;
		}

		$ids    = array_map( 'intval', array_keys( $products ) );
		$fields = Table_Fields::fields( $settings );
		$tabs   = array();

		foreach ( Category_Model::tabs_for( $ids, $settings ) as $tab ) {
			$columns = array();

			foreach ( $tab['ids'] as $id ) {
				$columns[] = self::column( $products[ $id ] );
			}

			$rows = array();

			foreach ( $fields as $field ) {
				if ( empty( $field['enabled'] ) ) {
					continue;
				}

				$cells      = array();
				$normalized = array();
				$assigned   = false;
				$all_empty  = true;

				foreach ( $tab['ids'] as $id ) {
					$cell         = self::cell( $products[ $id ], $field, $settings );
					$cells[]      = $cell;
					$normalized[] = $cell['normalized'];
					$assigned     = $assigned || $cell['assigned'];
					$all_empty    = $all_empty && $cell['empty'];
				}

				// Эти два переключателя независимы: «не назначен ни одному» ≠ «пуст у всех».
				if ( 'attribute' === $field['type'] && ! $assigned && ! empty( $settings['hide_unassigned_attributes'] ) ) {
					continue;
				}

				if ( $all_empty && ! empty( $settings['hide_empty_rows'] ) ) {
					continue;
				}

				$rows[] = array(
					'key'       => (string) $field['key'],
					'label'     => (string) $field['label'],
					'hint'      => (string) $field['hint'],
					'group'     => (string) $field['group'],
					'different' => count( $tab['ids'] ) > 1 && count( array_unique( $normalized ) ) > 1,
					'cells'     => $cells,
				);
			}

			$tabs[] = array(
				'key'     => (string) $tab['key'],
				'label'   => (string) $tab['label'],
				'ids'     => $tab['ids'],
				'count'   => count( $tab['ids'] ),
				'columns' => $columns,
				'rows'    => $rows,
			);
		}

		return array(
			'ids'     => $ids,
			'count'   => count( $ids ),
			'groups'  => Table_Fields::groups( $settings ),
			'tabs'    => $tabs,
			'options' => array(
				'highlight_differences' => ! empty( $settings['highlight_differences'] ),
				'show_difference_only'  => ! empty( $settings['show_difference_toggle'] ),
				'term_tooltips'         => ! empty( $settings['term_tooltips'] ),
				'attribute_values'      => (string) $settings['attribute_values_layout'],
			),
		);
	}

	/**
	 * Публичные данные одного товара для шапки и нижней кнопки.
	 *
	 * @param \WC_Product $product Опубликованный видимый товар.
	 * @return array<string, mixed>
	 */
	private static function column( \WC_Product $product ): array {
		$id        = $product->get_id();
		$image     = $product->get_image_id() ? wp_get_attachment_image_url( (int) $product->get_image_id(), 'woocommerce_thumbnail' ) : false;
		$permalink = get_permalink( $id );
		$type      = $product->get_type();
		$buy_url   = $product->add_to_cart_url();
		$can_buy   = $product->is_purchasable() && $product->is_in_stock();
		$label     = __( 'Buy', 'rvn-compare-products-for-woocommerce' );

		if ( 'variable' === $type || 'grouped' === $type ) {
			$label   = __( 'Choose an option', 'rvn-compare-products-for-woocommerce' );
			$buy_url = $permalink;
		} elseif ( 'external' === $type ) {
			$label = __( 'View product', 'rvn-compare-products-for-woocommerce' );
		} elseif ( ! $can_buy ) {
			$label = __( 'Unavailable', 'rvn-compare-products-for-woocommerce' );
		}

		return array(
			'id'        => $id,
			'title'     => wp_strip_all_tags( $product->get_name() ),
			'url'       => esc_url_raw( (string) $permalink ),
			'image'     => esc_url_raw( (string) ( $image ? $image : wc_placeholder_img_src( 'woocommerce_thumbnail' ) ) ),
			'price'     => self::plain_text( $product->get_price_html() ),
			'buy_url'   => $can_buy ? esc_url_raw( (string) $buy_url ) : '',
			'buy_label' => $label,
			'can_buy'   => $can_buy,
		);
	}

	/**
	 * Значение характеристики товара: текст, ключ сравнения и описания терминов.
	 *
	 * @param \WC_Product          $product  Товар WooCommerce.
	 * @param array<string, mixed> $field    Описание характеристики.
	 * @param array<string, mixed> $settings Настройки плагина.
	 * @return array{ text: string, normalized: string, empty: bool, assigned: bool, terms: array<int, array{label: string, description: string}> }
	 */
	private static function cell( \WC_Product $product, array $field, array $settings ): array {
		$key      = (string) $field['key'];
		$type     = (string) $field['type'];
		$text     = '';
		$compare  = '';
		$assigned = false;
		$terms    = array();

		if ( 'attribute' === $type ) {
			$taxonomy = (string) $field['technical'];
			$attr     = $product->get_attributes()[ $taxonomy ] ?? null;

			if ( $attr instanceof \WC_Product_Attribute && $attr->is_taxonomy() ) {
				$assigned = true;
				$terms    = self::attribute_terms( $attr );
				$text     = implode( ', ', array_column( $terms, 'label' ) );
				$ids      = array_map( 'intval', $attr->get_options() );
				sort( $ids, SORT_NUMERIC );
				$compare = implode( ',', $ids );
			}
		} elseif ( 'meta' === $type ) {
			$meta_key = (string) $field['technical'];

			if ( Table_Fields::valid_meta_key( $meta_key ) && metadata_exists( 'post', $product->get_id(), $meta_key ) ) {
				$raw = $product->get_meta( $meta_key, true );

				if ( is_scalar( $raw ) ) {
					$assigned = true;
					$text     = self::plain_text( (string) $raw );

					if ( 'boolean' === ( $field['meta_type'] ?? '' ) ) {
						$truthy = in_array( strtolower( trim( (string) $raw ) ), array( '1', 'true', 'yes', 'on' ), true );
						$text   = $truthy ? __( 'Yes', 'rvn-compare-products-for-woocommerce' ) : __( 'No', 'rvn-compare-products-for-woocommerce' );
					}

					$compare = 'number' === ( $field['meta_type'] ?? '' ) && is_numeric( $text ) ? (string) (float) $text : $text;
				}
			}
		} else {
			list( $text, $compare, $assigned ) = self::core_value( $product, $key );
		}

		$text    = self::plain_text( $text );
		$compare = '' !== $compare ? $compare : $text;

		return array(
			'text'       => '' !== $text ? $text : '—',
			'normalized' => self::normalise( $compare, $settings ),
			'empty'      => '' === $text,
			'assigned'   => $assigned,
			'terms'      => $terms,
		);
	}

	/**
	 * Значение встроенного поля и ключ его сравнения.
	 *
	 * @param \WC_Product $product Товар.
	 * @param string      $key     Ключ характеристики.
	 * @return array{0: string, 1: string, 2: bool}
	 */
	private static function core_value( \WC_Product $product, string $key ): array {
		$text = '';
		$raw  = '';

		switch ( $key ) {
			case 'price':
				$text = self::plain_text( $product->get_price_html() );
				$raw  = (string) $product->get_price();
				break;
			case 'sku':
				$text = (string) $product->get_sku();
				break;
			case 'availability':
				$text = $product->is_in_stock() ? __( 'In stock', 'rvn-compare-products-for-woocommerce' ) : __( 'Out of stock', 'rvn-compare-products-for-woocommerce' );
				$raw  = (string) $product->get_stock_status();
				break;
			case 'rating':
				$text = $product->get_rating_count() > 0 ? sprintf(
					/* translators: 1: Average rating. 2: Rating count. */
					__( '%1$s of 5 (%2$d reviews)', 'rvn-compare-products-for-woocommerce' ),
					wc_format_decimal( $product->get_average_rating(), 1 ),
					$product->get_rating_count()
				) : '';
				$raw = (string) $product->get_average_rating();
				break;
			case 'brand':
				$brands = get_the_terms( $product->get_id(), 'product_brand' );
				$text   = is_array( $brands ) ? implode(
					', ',
					array_map(
						static function ( $term ) {
							return $term->name;
						},
						$brands
					)
				) : '';
				break;
			case 'weight':
				$weight = (string) $product->get_weight();
				$text   = '' !== $weight ? self::plain_text( wc_format_weight( (float) $weight ) ) : '';
				$raw    = $weight;
				break;
			case 'dimensions':
				$dims = array( (string) $product->get_length(), (string) $product->get_width(), (string) $product->get_height() );
				$text = array_filter(
					$dims,
					static function ( $value ) {
						return '' !== $value;
					}
				) ? self::plain_text( wc_format_dimensions( $product->get_dimensions( false ) ) ) : '';
				$raw  = implode( 'x', $dims );
				break;
			case 'short_description':
				$text = (string) $product->get_short_description();
				break;
			case 'description':
				$text = (string) $product->get_description();
				break;
		}

		return array( $text, $raw, '' !== $text );
	}

	/**
	 * Тексты и описания терминов глобального атрибута.
	 *
	 * @param \WC_Product_Attribute $attribute Атрибут товара.
	 * @return array<int, array{label: string, description: string}>
	 */
	private static function attribute_terms( \WC_Product_Attribute $attribute ): array {
		$result = array();
		$terms  = $attribute->get_terms();

		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			if ( ! $term instanceof \WP_Term ) {
				continue;
			}

			$result[] = array(
				'label'       => self::plain_text( $term->name ),
				'description' => self::plain_text( $term->description ),
			);
		}

		return $result;
	}

	/**
	 * Превращает WC-строку в безопасный обычный текст (без HTML-разметки).
	 *
	 * @param string $value Значение поля.
	 * @return string
	 */
	private static function plain_text( string $value ): string {
		return trim( html_entity_decode( wp_strip_all_tags( $value ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	}

	/**
	 * Нормализует значение исключительно для подсветки различий.
	 *
	 * Текст товара покупателю не меняется. Умная нормализация устраняет
	 * повторные пробелы, регистр и незначащие пробелы между числами и единицами.
	 *
	 * @param string               $value    Значение для сравнения.
	 * @param array<string, mixed> $settings Настройки магазина.
	 * @return string
	 */
	private static function normalise( string $value, array $settings ): string {
		$value = trim( wp_strip_all_tags( $value ) );
		$value = preg_replace( '/\s+/u', ' ', $value ) ?? $value;

		if ( ! empty( $settings['smart_normalize'] ) ) {
			$value = preg_replace( '/(?<=\d)\s+(?=\d)/u', '', $value ) ?? $value;
		}

		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );
	}
}
