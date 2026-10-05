<?php
/**
 * Настройки плагина: значения по умолчанию, чтение и безопасное сохранение.
 *
 * @package RVN_Compare
 */

namespace RVN_Compare;

defined( 'ABSPATH' ) || exit;

/**
 * Единое хранилище настроек сравнения.
 *
 * Все входящие значения проходят очистку: числа ограничиваются сверху,
 * строки очищаются, массивы ключей приводятся к спискам целых чисел,
 * флаги — к булевым значениям. Правила нарушить нельзя: например,
 * лимит категории никогда не превысит общий лимит.
 */
final class Settings {

	/**
	 * Опция с настройками плагина.
	 *
	 * @var string
	 */
	const OPTION = 'rvn_compare_settings';

	/**
	 * Наибольшее допустимое число товаров в списке сравнения.
	 *
	 * @var int
	 */
	const MAX_TOTAL = 50;

	/**
	 * Допустимые значения правила сравнения.
	 *
	 * @var string[]
	 */
	const COMPARE_RULES = array( 'assigned', 'top_level', 'all' );

	/**
	 * Позиции автоматической кнопки сравнения.
	 *
	 * Значения `image_*` размещают кнопку поверх изображения товара; `off`
	 * отключает авто-вывод — тогда кнопка ставится шорткодом.
	 *
	 * @var string[]
	 */
	const POSITIONS = array(
		'after_cart',
		'before_cart',
		'above_title',
		'below_title',
		'image_tl',
		'image_tr',
		'image_bl',
		'image_br',
		'off',
	);

	/**
	 * Позиции поверх изображения.
	 *
	 * @var string[]
	 */
	const IMAGE_POSITIONS = array( 'image_tl', 'image_tr', 'image_bl', 'image_br' );

	/**
	 * Режимы отображения кнопки: иконка, текст, их сочетания и расположение.
	 *
	 * @var string[]
	 */
	const BUTTON_MODES = array( 'text', 'icon', 'text_icon', 'icon_text', 'text_over_icon', 'icon_over_text' );

	/**
	 * Позиции уведомлений на экране.
	 *
	 * @var string[]
	 */
	const TOAST_POSITIONS = array(
		'top_left',
		'top_center',
		'top_right',
		'bottom_left',
		'bottom_center',
		'bottom_right',
		'center',
	);

	/**
	 * Настройки с учётом значений по умолчанию.
	 *
	 * @return array<string, mixed>
	 */
	public static function all(): array {
		$saved = get_option( self::OPTION, array() );

		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		return array_merge( self::defaults(), $saved );
	}

	/**
	 * Возвращает одно значение настройки.
	 *
	 * @param string $key Ключ настройки.
	 * @return mixed
	 */
	public static function get( string $key ) {
		$all = self::all();

		return array_key_exists( $key, $all ) ? $all[ $key ] : null;
	}

	/**
	 * Сохраняет настройки после очистки и возвращает сохранённый набор.
	 *
	 * @param array<string, mixed> $input Входные данные (например, из формы или REST).
	 * @return array<string, mixed>
	 */
	public static function update( array $input ): array {
		$clean = self::sanitize( $input, self::all() );

		update_option( self::OPTION, $clean, true );

		return $clean;
	}

	/**
	 * Значения по умолчанию.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'limit_total'                => 50,
			'limit_per_category'         => 12,
			'compare_rule'               => 'assigned',
			'ignored_categories'         => array(),
			'category_groups'            => array(),
			'excluded_products'          => array(),
			'excluded_categories'        => array(),
			'other_label'                => '',
			'delete_data_on_uninstall'   => true,

			// Кнопки сравнения (итерация 2).
			'card_position'              => 'after_cart',
			'single_position'            => 'after_cart',
			'card_mode'                  => 'icon_text',
			'card_mode_mobile'           => 'icon',
			'single_mode'                => 'icon_text',
			'single_mode_mobile'         => 'inherit',
			'button_label'               => '',
			'button_in_label'            => '',

			// Уведомления и общий вид.
			'toast_position'             => 'bottom_right',
			'toast_position_mobile'      => 'bottom_center',
			'toast_duration'             => 3200,
			'animate_ms'                 => 300,
			'accent_color'               => '#2563eb',
			'scroll_offset_top'          => '0px',
			'scroll_offset_bottom'       => '0px',

			// Таблица: настройка полей и групп хранит только явные изменения.
			'table_groups'               => Table_Fields::default_groups(),
			'table_fields'               => array(),
			'meta_fields'                => array(),
			'new_attributes_enabled'     => true,
			'groups_enabled'             => true,
			'hide_unassigned_attributes' => true,
			'hide_empty_rows'            => false,
			'highlight_differences'      => true,
			'show_difference_toggle'     => true,
			'term_tooltips'              => true,
			'smart_normalize'            => true,
			'auto_insert_table'          => true,
			'noindex_compare_page'       => true,
			'attribute_values_layout'    => 'auto',
			'difference_color'           => '#ffcfcc',
			'columns_desktop'            => 5,
			'columns_tablet'             => 3,
			'columns_phone'              => 2,
			'breakpoint_tablet'          => 1024,
			'breakpoint_phone'           => 768,
			'scrollbar_mode'             => 'hidden',
		);
	}

	/**
	 * Очищает входные данные, сохраняя текущие значения отсутствующих ключей.
	 *
	 * @param array<string, mixed> $input   Входные данные.
	 * @param array<string, mixed> $current Текущие настройки.
	 * @return array<string, mixed>
	 */
	public static function sanitize( array $input, array $current ): array {
		$clean = array();

		// Общий лимит: 1…50, по умолчанию 50.
		$total                = isset( $input['limit_total'] ) ? absint( $input['limit_total'] ) : (int) $current['limit_total'];
		$clean['limit_total'] = min( self::MAX_TOTAL, max( 1, $total ) );

		// Лимит категории: 1…общий лимит, поэтому превысить общий невозможно.
		$per                         = isset( $input['limit_per_category'] ) ? absint( $input['limit_per_category'] ) : (int) $current['limit_per_category'];
		$clean['limit_per_category'] = min( $clean['limit_total'], max( 1, $per ) );

		$rule                  = isset( $input['compare_rule'] ) ? sanitize_key( (string) $input['compare_rule'] ) : (string) $current['compare_rule'];
		$clean['compare_rule'] = in_array( $rule, self::COMPARE_RULES, true ) ? $rule : 'assigned';

		$clean['ignored_categories'] = self::int_list( $input['ignored_categories'] ?? $current['ignored_categories'] );

		$clean['category_groups'] = self::group_list( $input['category_groups'] ?? $current['category_groups'] );

		$clean['excluded_products'] = self::exclusion_map( $input['excluded_products'] ?? $current['excluded_products'] );

		$clean['excluded_categories'] = self::exclusion_map( $input['excluded_categories'] ?? $current['excluded_categories'] );

		$label                = isset( $input['other_label'] ) ? (string) $input['other_label'] : (string) $current['other_label'];
		$clean['other_label'] = sanitize_text_field( mb_substr( $label, 0, 80 ) );

		$clean['delete_data_on_uninstall'] = self::to_bool( $input['delete_data_on_uninstall'] ?? $current['delete_data_on_uninstall'] );

		$clean['card_position']    = self::choice( $input, 'card_position', $current, self::POSITIONS, 'after_cart' );
		$clean['single_position']  = self::choice( $input, 'single_position', $current, self::POSITIONS, 'after_cart' );
		$clean['card_mode']        = self::choice( $input, 'card_mode', $current, self::BUTTON_MODES, 'icon_text' );
		$clean['card_mode_mobile'] = self::choice( $input, 'card_mode_mobile', $current, self::BUTTON_MODES, 'icon' );
		$clean['single_mode']      = self::choice( $input, 'single_mode', $current, self::BUTTON_MODES, 'icon_text' );

		// Наследование режима телефона: пустое значение означает «как на компьютере».
		$mobile                      = isset( $input['single_mode_mobile'] ) ? sanitize_key( (string) $input['single_mode_mobile'] ) : (string) $current['single_mode_mobile'];
		$clean['single_mode_mobile'] = ( 'inherit' === $mobile || in_array( $mobile, self::BUTTON_MODES, true ) ) ? $mobile : 'inherit';

		$clean['toast_position']        = self::choice( $input, 'toast_position', $current, self::TOAST_POSITIONS, 'bottom_right' );
		$clean['toast_position_mobile'] = self::choice( $input, 'toast_position_mobile', $current, self::TOAST_POSITIONS, 'bottom_center' );

		$clean['toast_duration'] = min( 15000, max( 0, isset( $input['toast_duration'] ) ? absint( $input['toast_duration'] ) : (int) $current['toast_duration'] ) );
		$clean['animate_ms']     = min( 3000, max( 0, isset( $input['animate_ms'] ) ? absint( $input['animate_ms'] ) : (int) $current['animate_ms'] ) );

		$clean['accent_color'] = self::color( $input['accent_color'] ?? $current['accent_color'], '#2563eb' );

		$clean['scroll_offset_top']    = self::css_length( $input['scroll_offset_top'] ?? $current['scroll_offset_top'] );
		$clean['scroll_offset_bottom'] = self::css_length( $input['scroll_offset_bottom'] ?? $current['scroll_offset_bottom'] );

		// Тексты кнопок: пустое значение означает перевод, поэтому строку не подставляем.
		$label                 = isset( $input['button_label'] ) ? (string) $input['button_label'] : (string) $current['button_label'];
		$clean['button_label'] = sanitize_text_field( mb_substr( $label, 0, 60 ) );

		$in_label                 = isset( $input['button_in_label'] ) ? (string) $input['button_in_label'] : (string) $current['button_in_label'];
		$clean['button_in_label'] = sanitize_text_field( mb_substr( $in_label, 0, 60 ) );

		$clean['new_attributes_enabled']     = self::to_bool( $input['new_attributes_enabled'] ?? $current['new_attributes_enabled'] );
		$clean['groups_enabled']             = self::to_bool( $input['groups_enabled'] ?? $current['groups_enabled'] );
		$clean['hide_unassigned_attributes'] = self::to_bool( $input['hide_unassigned_attributes'] ?? $current['hide_unassigned_attributes'] );
		$clean['hide_empty_rows']            = self::to_bool( $input['hide_empty_rows'] ?? $current['hide_empty_rows'] );
		$clean['highlight_differences']      = self::to_bool( $input['highlight_differences'] ?? $current['highlight_differences'] );
		$clean['show_difference_toggle']     = self::to_bool( $input['show_difference_toggle'] ?? $current['show_difference_toggle'] );
		$clean['term_tooltips']              = self::to_bool( $input['term_tooltips'] ?? $current['term_tooltips'] );
		$clean['smart_normalize']            = self::to_bool( $input['smart_normalize'] ?? $current['smart_normalize'] );
		$clean['auto_insert_table']          = self::to_bool( $input['auto_insert_table'] ?? $current['auto_insert_table'] );
		$clean['noindex_compare_page']       = self::to_bool( $input['noindex_compare_page'] ?? $current['noindex_compare_page'] );

		$clean['attribute_values_layout'] = self::choice( $input, 'attribute_values_layout', $current, array( 'auto', 'inline', 'lines' ), 'auto' );
		$clean['scrollbar_mode']          = self::choice( $input, 'scrollbar_mode', $current, array( 'hidden', 'thin', 'system' ), 'hidden' );
		$clean['difference_color']        = self::color( $input['difference_color'] ?? $current['difference_color'], '#ffcfcc' );

		foreach ( array(
			'columns_desktop' => 5,
			'columns_tablet'  => 3,
			'columns_phone'   => 2,
		) as $key => $default ) {
			$number        = isset( $input[ $key ] ) ? absint( $input[ $key ] ) : (int) ( $current[ $key ] ?? $default );
			$clean[ $key ] = min( 8, max( 1, $number ) );
		}

		foreach ( array(
			'breakpoint_tablet' => 1024,
			'breakpoint_phone'  => 768,
		) as $key => $default ) {
			$number        = isset( $input[ $key ] ) ? absint( $input[ $key ] ) : (int) ( $current[ $key ] ?? $default );
			$clean[ $key ] = min( 2400, max( 320, $number ) );
		}

		if ( $clean['breakpoint_phone'] >= $clean['breakpoint_tablet'] ) {
			$clean['breakpoint_phone'] = max( 320, $clean['breakpoint_tablet'] - 1 );
		}

		$table = Table_Fields::sanitize_options(
			$input['table_fields'] ?? $current['table_fields'],
			$input['table_groups'] ?? $current['table_groups'],
			$input['meta_fields'] ?? $current['meta_fields'],
			$clean
		);

		$clean = array_merge( $clean, $table );

		return $clean;
	}

	/**
	 * Приводит значение к списку уникальных положительных целых чисел.
	 *
	 * @param mixed $raw Исходное значение.
	 * @return int[]
	 */
	private static function int_list( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$list = array();

		foreach ( $raw as $value ) {
			$id = (int) $value;

			if ( $id > 0 && ! in_array( $id, $list, true ) ) {
				$list[] = $id;
			}
		}

		return $list;
	}

	/**
	 * Очищает список групп категорий.
	 *
	 * Каждая группа: ключ, название и список ID категорий.
	 * Категория, попавшая в две группы, остаётся в первой — модель категорий
	 * тоже применяет это правило, так что настройки и поведение не расходятся.
	 *
	 * @param mixed $raw Исходное значение.
	 * @return array<int, array{key: string, name: string, category_ids: int[]}>
	 */
	private static function group_list( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$groups   = array();
		$assigned = array();

		foreach ( array_slice( array_values( $raw ), 0, 50 ) as $group ) {
			if ( ! is_array( $group ) ) {
				continue;
			}

			$key  = isset( $group['key'] ) ? sanitize_key( (string) $group['key'] ) : '';
			$name = isset( $group['name'] ) ? sanitize_text_field( mb_substr( (string) $group['name'], 0, 80 ) ) : '';

			if ( '' === $key ) {
				continue;
			}

			$category_ids = array();

			foreach ( self::int_list( $group['category_ids'] ?? array() ) as $category_id ) {
				// Одна категория может состоять только в одной группе: побеждает первая.
				if ( in_array( $category_id, $assigned, true ) ) {
					continue;
				}

				$assigned[]     = $category_id;
				$category_ids[] = $category_id;
			}

			$groups[] = array(
				'key'          => $key,
				'name'         => $name,
				'category_ids' => $category_ids,
			);
		}

		return $groups;
	}

	/**
	 * Очищает карту исключений: ID => флаги контекста показа.
	 *
	 * @param mixed $raw Исходное значение.
	 * @return array<int, array{card: bool, single: bool}>
	 */
	private static function exclusion_map( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$map = array();

		foreach ( $raw as $key => $flags ) {
			$id = (int) $key;

			if ( $id <= 0 ) {
				continue;
			}

			$flags = is_array( $flags ) ? $flags : array();

			$map[ $id ] = array(
				'card'   => self::to_bool( $flags['card'] ?? false ),
				'single' => self::to_bool( $flags['single'] ?? false ),
			);
		}

		return $map;
	}

	/**
	 * Возвращает допустимое значение из списка или значение по умолчанию.
	 *
	 * @param array<string, mixed> $input    Входные данные.
	 * @param string               $key      Ключ настройки.
	 * @param array<string, mixed> $current  Текущие настройки.
	 * @param string[]             $allowed  Допустимые значения.
	 * @param string               $fallback Значение по умолчанию.
	 * @return string
	 */
	private static function choice( array $input, string $key, array $current, array $allowed, string $fallback ): string {
		$value = isset( $input[ $key ] ) ? sanitize_key( (string) $input[ $key ] ) : (string) ( $current[ $key ] ?? $fallback );

		return in_array( $value, $allowed, true ) ? $value : $fallback;
	}

	/**
	 * Проверяет шестнадцатеричный цвет; принимает также `transparent`.
	 *
	 * @param mixed  $raw      Исходное значение.
	 * @param string $fallback Значение по умолчанию.
	 * @return string
	 */
	private static function color( $raw, string $fallback ): string {
		$value = is_string( $raw ) ? trim( $raw ) : '';

		if ( 'transparent' === strtolower( $value ) ) {
			return 'transparent';
		}

		$hex = sanitize_hex_color( $value );

		return is_string( $hex ) ? $hex : $fallback;
	}

	/**
	 * Проверяет значение длины CSS: число с единицей измерения из допустимых.
	 *
	 * Произвольные строки не пропускаются — значение уходит прямо в стиль.
	 *
	 * @param mixed $raw Исходное значение.
	 * @return string
	 */
	private static function css_length( $raw ): string {
		$value = is_string( $raw ) ? trim( $raw ) : '';

		if ( 1 === preg_match( '/^(\d{1,4})(px|rem|em|vh|vw|%)?$/', $value, $matches ) ) {
			return $matches[1] . ( $matches[2] ?? 'px' );
		}

		return '0px';
	}

	/**
	 * Приводит значение к логическому.
	 *
	 * @param mixed $raw Исходное значение.
	 * @return bool
	 */
	private static function to_bool( $raw ): bool {
		return in_array( $raw, array( true, 1, '1', 'true', 'yes', 'on' ), true );
	}
}
