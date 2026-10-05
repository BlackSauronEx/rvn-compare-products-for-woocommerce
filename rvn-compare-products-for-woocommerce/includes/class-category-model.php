<?php
/**
 * Модель категорий: группы вкладок для товаров списка сравнения.
 *
 * @package RVN_Compare
 */

namespace RVN_Compare;

defined( 'ABSPATH' ) || exit;

/**
 * Считает, в какие вкладки попадает товар.
 *
 * Ключ вкладки — это её идентификатор:
 * - `cat:12`  — категория 12;
 * - `grp:key` — группа категорий из настроек;
 * - `other`   — группа «Прочее»;
 * - `all`     — единая вкладка при правиле «без разделения».
 *
 * Товар считается только по своим прямым категориям (без наследования).
 * Игнорируемые категории и категория WooCommerce по умолчанию вкладок
 * не образуют: такие товары уходят в «Прочее».
 */
final class Category_Model {

	/**
	 * Ключ вкладки «Прочее».
	 *
	 * @var string
	 */
	const TAB_OTHER = 'other';

	/**
	 * Ключ единой вкладки при правиле «без разделения».
	 *
	 * @var string
	 */
	const TAB_ALL = 'all';

	/**
	 * ID категории WooCommerce по умолчанию (обычно «Без категории»).
	 *
	 * @return int
	 */
	public static function default_category_id(): int {
		return (int) get_option( 'default_product_cat', 0 );
	}

	/**
	 * Прямые категории товара без родительских.
	 *
	 * @param int $product_id ID товара.
	 * @return int[]
	 */
	public static function direct_category_ids( int $product_id ): array {
		$terms = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );

		return is_array( $terms ) ? array_map( 'absint', $terms ) : array();
	}

	/**
	 * Карта «категория → группа» из настроек (первая группа побеждает).
	 *
	 * @param array<string, mixed> $settings Настройки плагина.
	 * @return array<int, string>
	 */
	public static function group_map( array $settings ): array {
		$map = array();

		foreach ( (array) $settings['category_groups'] as $group ) {
			if ( ! is_array( $group ) ) {
				continue;
			}

			$key = isset( $group['key'] ) ? sanitize_key( (string) $group['key'] ) : '';

			if ( '' === $key ) {
				continue;
			}

			foreach ( (array) ( $group['category_ids'] ?? array() ) as $category_id ) {
				$category_id = absint( $category_id );

				if ( $category_id > 0 && ! isset( $map[ $category_id ] ) ) {
					$map[ $category_id ] = $key;
				}
			}
		}

		return $map;
	}

	/**
	 * Вкладки, в которых присутствует товар.
	 *
	 * @param int                  $product_id ID товара.
	 * @param array<string, mixed> $settings   Настройки плагина.
	 * @return string[]
	 */
	public static function membership( int $product_id, array $settings ): array {
		$rule = isset( $settings['compare_rule'] ) ? (string) $settings['compare_rule'] : 'assigned';

		if ( 'all' === $rule ) {
			return array( self::TAB_ALL );
		}

		$ignored = array_map( 'absint', (array) $settings['ignored_categories'] );
		$default = self::default_category_id();
		$map     = self::group_map( $settings );
		$tabs    = array();

		foreach ( self::direct_category_ids( $product_id ) as $category_id ) {
			// Категория WooCommerce по умолчанию и игнорируемые категории вкладок не создают.
			if ( $category_id === $default || in_array( $category_id, $ignored, true ) ) {
				continue;
			}

			if ( isset( $map[ $category_id ] ) ) {
				$tabs[] = 'grp:' . $map[ $category_id ];
				continue;
			}

			$tab_category = 'top_level' === $rule ? self::root_category_id( $category_id ) : $category_id;
			$tabs[]       = 'cat:' . $tab_category;
		}

		if ( array() === $tabs ) {
			return array( self::TAB_OTHER );
		}

		return array_values( array_unique( $tabs ) );
	}

	/**
	 * Верхняя категория ветки, к которой принадлежит категория.
	 *
	 * @param int $category_id ID категории.
	 * @return int
	 */
	public static function root_category_id( int $category_id ): int {
		$current = $category_id;

		for ( $level = 0; $level < 20; $level++ ) {
			$term = get_term( $current, 'product_cat' );

			if ( is_wp_error( $term ) || ! isset( $term->parent ) || 0 === (int) $term->parent ) {
				return $current;
			}

			$current = (int) $term->parent;
		}

		return $current;
	}

	/**
	 * Раскладывает список товаров по вкладкам.
	 *
	 * Порядок вкладок — по порядку появления товаров; вкладка сначала показывает
	 * те товары, которые встретились раньше.
	 *
	 * @param int[]                $product_ids ID товаров.
	 * @param array<string, mixed> $settings    Настройки плагина.
	 * @return array<int, array{key: string, label: string, ids: int[]}>
	 */
	public static function tabs_for( array $product_ids, array $settings ): array {
		$tabs = array();

		foreach ( $product_ids as $product_id ) {
			foreach ( self::membership( (int) $product_id, $settings ) as $key ) {
				if ( ! isset( $tabs[ $key ] ) ) {
					$tabs[ $key ] = array(
						'key'   => $key,
						'label' => self::label( $key, $settings ),
						'ids'   => array(),
					);
				}

				$tabs[ $key ]['ids'][] = (int) $product_id;
			}
		}

		return array_values( $tabs );
	}

	/**
	 * Название вкладки для интерфейса.
	 *
	 * @param string               $key     Ключ вкладки.
	 * @param array<string, mixed> $settings Настройки плагина.
	 * @return string
	 */
	public static function label( string $key, array $settings ): string {
		if ( self::TAB_OTHER === $key ) {
			$custom = isset( $settings['other_label'] ) ? trim( (string) $settings['other_label'] ) : '';

			return '' !== $custom ? $custom : __( 'Other', 'rvn-compare-products-for-woocommerce' );
		}

		if ( self::TAB_ALL === $key ) {
			return __( 'All products', 'rvn-compare-products-for-woocommerce' );
		}

		if ( 0 === strpos( $key, 'grp:' ) ) {
			$group_key = substr( $key, 4 );

			foreach ( (array) $settings['category_groups'] as $group ) {
				if ( is_array( $group ) && isset( $group['key'] ) && sanitize_key( (string) $group['key'] ) === $group_key ) {
					$name = isset( $group['name'] ) ? trim( (string) $group['name'] ) : '';

					return '' !== $name ? $name : __( 'Group', 'rvn-compare-products-for-woocommerce' );
				}
			}

			return __( 'Group', 'rvn-compare-products-for-woocommerce' );
		}

		$category_id = (int) substr( $key, 4 );
		$term        = get_term( $category_id, 'product_cat' );

		if ( is_wp_error( $term ) || ! $term instanceof \WP_Term ) {
			return sprintf(
				/* translators: %d: Product category ID. */
				__( 'Category %d', 'rvn-compare-products-for-woocommerce' ),
				$category_id
			);
		}

		return $term->name;
	}
}
