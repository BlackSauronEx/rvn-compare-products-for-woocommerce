<?php
/**
 * Список и настройки характеристик таблицы сравнения.
 *
 * @package RVN_Compare
 */

namespace RVN_Compare;

defined( 'ABSPATH' ) || exit;

/**
 * Строит упорядоченный список базовых полей, атрибутов и явных мета-полей.
 *
 * Новые глобальные атрибуты WooCommerce добавляются в конец и включаются
 * или отключаются в зависимости от настройки магазина. Группы хранят
 * стабильные ключи, а их названия берутся из перевода либо переопределения.
 */
final class Table_Fields {

	/**
	 * Допустимые базовые поля в порядке первого отображения.
	 *
	 * @var string[]
	 */
	private const BASE_KEYS = array(
		'price',
		'sku',
		'availability',
		'rating',
		'brand',
		'weight',
		'dimensions',
		'short_description',
		'description',
	);

	/**
	 * Названия групп по умолчанию без записи переводимого текста в базу.
	 *
	 * @return array<int, array{key: string, label: string, enabled: bool, collapsed: bool}>
	 */
	public static function default_groups(): array {
		return array(
			array(
				'key'       => 'basic',
				'label'     => '',
				'enabled'   => true,
				'collapsed' => false,
			),
			array(
				'key'       => 'size',
				'label'     => '',
				'enabled'   => true,
				'collapsed' => false,
			),
			array(
				'key'       => 'specifications',
				'label'     => '',
				'enabled'   => true,
				'collapsed' => false,
			),
		);
	}

	/**
	 * Фиксированные определения встроенных характеристик.
	 *
	 * @return array<string, array{label: string, group: string, enabled: bool}>
	 */
	private static function builtins(): array {
		return array(
			'price'             => array(
				'label'   => __( 'Price', 'rvn-compare-products-for-woocommerce' ),
				'group'   => 'basic',
				'enabled' => true,
			),
			'sku'               => array(
				'label'   => __( 'SKU', 'rvn-compare-products-for-woocommerce' ),
				'group'   => 'basic',
				'enabled' => true,
			),
			'availability'      => array(
				'label'   => __( 'Availability', 'rvn-compare-products-for-woocommerce' ),
				'group'   => 'basic',
				'enabled' => true,
			),
			'rating'            => array(
				'label'   => __( 'Rating', 'rvn-compare-products-for-woocommerce' ),
				'group'   => 'basic',
				'enabled' => true,
			),
			'brand'             => array(
				'label'   => __( 'Brand', 'rvn-compare-products-for-woocommerce' ),
				'group'   => 'basic',
				'enabled' => true,
			),
			'weight'            => array(
				'label'   => __( 'Weight', 'rvn-compare-products-for-woocommerce' ),
				'group'   => 'size',
				'enabled' => true,
			),
			'dimensions'        => array(
				'label'   => __( 'Dimensions', 'rvn-compare-products-for-woocommerce' ),
				'group'   => 'size',
				'enabled' => true,
			),
			'short_description' => array(
				'label'   => __( 'Short description', 'rvn-compare-products-for-woocommerce' ),
				'group'   => 'specifications',
				'enabled' => true,
			),
			'description'       => array(
				'label'   => __( 'Description', 'rvn-compare-products-for-woocommerce' ),
				'group'   => 'specifications',
				'enabled' => false,
			),
		);
	}

	/**
	 * Стабильный перечень доступных полей для текущего магазина.
	 *
	 * Скрытые и приватные ключи метаданных в перечень не попадают; владелец
	 * добавляет только скалярный публичный ключ без ведущего подчёркивания.
	 *
	 * @param array<string, mixed> $settings Настройки магазина.
	 * @return array<string, array<string, mixed>>
	 */
	public static function registry( array $settings ): array {
		$registry = array();
		$builtin  = self::builtins();

		foreach ( self::BASE_KEYS as $key ) {
			if ( 'brand' === $key && ! taxonomy_exists( 'product_brand' ) ) {
				continue;
			}

			$registry[ $key ] = array_merge(
				$builtin[ $key ],
				array(
					'key'       => $key,
					'type'      => 'core',
					'technical' => $key,
				)
			);
		}

		if ( function_exists( 'wc_get_attribute_taxonomies' ) ) {
			foreach ( (array) wc_get_attribute_taxonomies() as $attribute ) {
				if ( ! is_object( $attribute ) || empty( $attribute->attribute_name ) ) {
					continue;
				}

				$taxonomy = wc_attribute_taxonomy_name( (string) $attribute->attribute_name );

				if ( ! taxonomy_exists( $taxonomy ) ) {
					continue;
				}

				$key              = 'attribute:' . $taxonomy;
				$registry[ $key ] = array(
					'key'       => $key,
					'type'      => 'attribute',
					'technical' => $taxonomy,
					'label'     => (string) $attribute->attribute_label,
					'group'     => 'specifications',
					'enabled'   => ! empty( $settings['new_attributes_enabled'] ),
				);
			}
		}

		foreach ( (array) ( $settings['meta_fields'] ?? array() ) as $meta ) {
			if ( ! is_array( $meta ) || ! isset( $meta['key'] ) || ! self::valid_meta_key( (string) $meta['key'] ) ) {
				continue;
			}

			$key              = 'meta:' . $meta['key'];
			$registry[ $key ] = array(
				'key'       => $key,
				'type'      => 'meta',
				'technical' => $meta['key'],
				'label'     => (string) ( $meta['label'] ?? $meta['key'] ),
				'group'     => 'specifications',
				'enabled'   => true,
				'meta_type' => (string) ( $meta['type'] ?? 'text' ),
			);
		}

		return $registry;
	}

	/**
	 * Упорядоченные поля с переопределениями администратора.
	 *
	 * Новые атрибуты, которых нет в сохранённом порядке, добавляются в хвост.
	 *
	 * @param array<string, mixed> $settings Настройки магазина.
	 * @return array<int, array<string, mixed>>
	 */
	public static function fields( array $settings ): array {
		$registry = self::registry( $settings );
		$saved    = is_array( $settings['table_fields'] ?? null ) ? $settings['table_fields'] : array();
		$fields   = array();

		foreach ( $saved as $key => $overrides ) {
			if ( ! isset( $registry[ $key ] ) || ! is_array( $overrides ) ) {
				continue;
			}

			$fields[] = self::configured( $registry[ $key ], $overrides, $settings );
			unset( $registry[ $key ] );
		}

		foreach ( $registry as $field ) {
			$fields[] = self::configured( $field, array(), $settings );
		}

		return $fields;
	}

	/**
	 * Список групп с переведёнными заголовками.
	 *
	 * @param array<string, mixed> $settings Настройки магазина.
	 * @return array<int, array<string, mixed>>
	 */
	public static function groups( array $settings ): array {
		$raw    = is_array( $settings['table_groups'] ?? null ) ? $settings['table_groups'] : self::default_groups();
		$groups = array();

		foreach ( $raw as $group ) {
			if ( ! is_array( $group ) || empty( $group['key'] ) ) {
				continue;
			}

			$key      = (string) $group['key'];
			$custom   = trim( (string) ( $group['label'] ?? '' ) );
			$groups[] = array(
				'key'       => $key,
				'label'     => '' !== $custom ? $custom : self::default_group_label( $key ),
				'enabled'   => isset( $group['enabled'] ) ? (bool) $group['enabled'] : true,
				'collapsed' => ! empty( $group['collapsed'] ),
			);
		}

		return $groups;
	}

	/**
	 * Очищает данные формы настроек характеристик и групп.
	 *
	 * @param mixed                $raw_fields   Поля, пришедшие из формы.
	 * @param mixed                $raw_groups   Группы из формы.
	 * @param mixed                $raw_meta     Ручные мета-поля.
	 * @param array<string, mixed> $settings     Исходные настройки.
	 * @return array{table_fields: array<string, array<string, mixed>>, table_groups: array<int, array<string, mixed>>, meta_fields: array<int, array<string, string>>}
	 */
	public static function sanitize_options( $raw_fields, $raw_groups, $raw_meta, array $settings ): array {
		$groups   = array();
		$used     = array();
		$incoming = is_array( $raw_groups ) ? $raw_groups : array();

		foreach ( array_slice( $incoming, 0, 30 ) as $group ) {
			if ( ! is_array( $group ) ) {
				continue;
			}

			$key = sanitize_key( (string) ( $group['key'] ?? '' ) );

			if ( '' === $key || isset( $used[ $key ] ) ) {
				continue;
			}

			$used[ $key ] = true;
			$groups[]     = array(
				'key'       => $key,
				'label'     => sanitize_text_field( (string) ( $group['label'] ?? '' ) ),
				'enabled'   => ! empty( $group['enabled'] ),
				'collapsed' => ! empty( $group['collapsed'] ),
			);
		}

		foreach ( self::default_groups() as $default ) {
			if ( ! isset( $used[ $default['key'] ] ) ) {
				$groups[] = $default;
			}
		}

		$meta = array();

		foreach ( array_slice( is_array( $raw_meta ) ? $raw_meta : array(), 0, 30 ) as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$key = sanitize_key( (string) ( $item['key'] ?? '' ) );

			if ( ! self::valid_meta_key( $key ) || isset( $meta[ $key ] ) ) {
				continue;
			}

			$type         = sanitize_key( (string) ( $item['type'] ?? 'text' ) );
			$meta[ $key ] = array(
				'key'   => $key,
				'label' => sanitize_text_field( (string) ( $item['label'] ?? '' ) ),
				'type'  => in_array( $type, array( 'text', 'number', 'boolean' ), true ) ? $type : 'text',
			);
		}

		$updated   = array_merge(
			$settings,
			array(
				'table_groups' => $groups,
				'meta_fields'  => array_values( $meta ),
			)
		);
		$registry  = self::registry( $updated );
		$fields    = array();
		$submitted = is_array( $raw_fields ) ? $raw_fields : array();

		foreach ( array_slice( $submitted, 0, 150, true ) as $key => $item ) {
			if ( ! isset( $registry[ $key ] ) || ! is_array( $item ) ) {
				continue;
			}

			$group          = sanitize_key( (string) ( $item['group'] ?? $registry[ $key ]['group'] ) );
			$fields[ $key ] = array(
				'enabled' => ! empty( $item['enabled'] ),
				'label'   => sanitize_text_field( (string) ( $item['label'] ?? '' ) ),
				'hint'    => sanitize_textarea_field( (string) ( $item['hint'] ?? '' ) ),
				'group'   => isset( $used[ $group ] ) ? $group : (string) $registry[ $key ]['group'],
			);
		}

		return array(
			'table_fields' => $fields,
			'table_groups' => $groups,
			'meta_fields'  => array_values( $meta ),
		);
	}

	/**
	 * Безопасен ли ключ метаданных для публичного вывода.
	 *
	 * @param string $key Ключ метаданных.
	 * @return bool
	 */
	public static function valid_meta_key( string $key ): bool {
		return 1 === preg_match( '/^[a-z][a-z0-9_-]{0,55}$/', $key )
			&& 0 === preg_match( '/(^|_)(password|secret|token|auth|api.?key|nonce|credential|session)(_|$)/i', $key );
	}

	/**
	 * Объединяет системное определение и безопасное переопределение администратора.
	 *
	 * @param array<string, mixed> $definition Базовое определение.
	 * @param array<string, mixed> $saved      Сохранённое переопределение.
	 * @param array<string, mixed> $settings   Настройки магазина.
	 * @return array<string, mixed>
	 */
	private static function configured( array $definition, array $saved, array $settings ): array {
		$group = (string) ( $saved['group'] ?? $definition['group'] );
		$keys  = array_column( self::groups( $settings ), 'key' );

		return array_merge(
			$definition,
			array(
				'label'    => ! empty( $saved['label'] ) ? (string) $saved['label'] : (string) $definition['label'],
				'fallback' => (string) $definition['label'],
				'hint'     => (string) ( $saved['hint'] ?? '' ),
				'enabled'  => isset( $saved['enabled'] ) ? (bool) $saved['enabled'] : (bool) $definition['enabled'],
				'group'    => in_array( $group, $keys, true ) ? $group : (string) $definition['group'],
			)
		);
	}

	/**
	 * Возвращает переведённое имя группы по умолчанию.
	 *
	 * @param string $key Ключ группы.
	 * @return string
	 */
	private static function default_group_label( string $key ): string {
		switch ( $key ) {
			case 'basic':
				return __( 'Main features', 'rvn-compare-products-for-woocommerce' );
			case 'size':
				return __( 'Weight and dimensions', 'rvn-compare-products-for-woocommerce' );
			case 'specifications':
				return __( 'Specifications', 'rvn-compare-products-for-woocommerce' );
			default:
				return __( 'Group', 'rvn-compare-products-for-woocommerce' );
		}
	}
}
