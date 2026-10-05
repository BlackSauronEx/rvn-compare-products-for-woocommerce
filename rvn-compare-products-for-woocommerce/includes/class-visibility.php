<?php
/**
 * Исключения товаров и категорий для автоматических кнопок.
 *
 * @package RVN_Compare
 */

namespace RVN_Compare;

defined( 'ABSPATH' ) || exit;

/**
 * Решает, скрыта ли кнопка сравнения у товара.
 *
 * Исключение имеет приоритет над настройками позиций: если товар прямо
 * отмечен в исключённой категории или сам исключён для этого места показа,
 * авто-кнопка не выводится. Шорткод с явным ID работает независимо от
 * исключений — это ручное решение владельца магазина.
 */
final class Visibility {

	/**
	 * Контекст показа: карточка товара.
	 *
	 * @var string
	 */
	const CONTEXT_CARD = 'card';

	/**
	 * Контекст показа: страница товара.
	 *
	 * @var string
	 */
	const CONTEXT_SINGLE = 'single';

	/**
	 * Заблокирована ли авто-кнопка у товара в заданном месте.
	 *
	 * @param int                  $product_id ID товара.
	 * @param string               $context    Контекст показа (card или single).
	 * @param array<string, mixed> $settings   Настройки плагина.
	 * @return bool
	 */
	public static function product_blocked( int $product_id, string $context, array $settings ): bool {
		$context = in_array( $context, array( self::CONTEXT_CARD, self::CONTEXT_SINGLE ), true ) ? $context : self::CONTEXT_CARD;

		$products = (array) $settings['excluded_products'];

		if ( isset( $products[ $product_id ][ $context ] ) && true === $products[ $product_id ][ $context ] ) {
			return true;
		}

		$categories = (array) $settings['excluded_categories'];

		foreach ( Category_Model::direct_category_ids( $product_id ) as $category_id ) {
			if ( isset( $categories[ $category_id ][ $context ] ) && true === $categories[ $category_id ][ $context ] ) {
				// Исключение побеждает: хватает одной исключённой категории товара.
				return true;
			}
		}

		return false;
	}
}
