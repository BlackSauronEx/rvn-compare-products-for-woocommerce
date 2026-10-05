<?php
/**
 * Тексты уведомлений и их подстановки.
 *
 * @package RVN_Compare
 */

namespace RVN_Compare\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Отдаёт скрипту тексты уведомлений и позицию показа.
 *
 * Тексты формируются здесь, а не в JavaScript: так строки попадают в файл
 * переводов и работают любые языки. Скрипт только подставляет названия
 * товаров и категорий в готовые шаблоны.
 */
final class Toasts {

	/**
	 * Тексты уведомлений и значения подстановок для скрипта.
	 *
	 * @return array<string, string>
	 */
	public static function labels(): array {
		return array(
			'added'        => __( 'Product added to the comparison list', 'rvn-compare-products-for-woocommerce' ),
			'removed'      => __( 'Product removed from the comparison list', 'rvn-compare-products-for-woocommerce' ),
			'cleared'      => __( 'Comparison list cleared', 'rvn-compare-products-for-woocommerce' ),
			/* translators: 1: Number of products in the comparison list. 2: Total limit of products. */
			'limitTotal'   => __( 'Comparison list is full: %1$d of %2$d products.', 'rvn-compare-products-for-woocommerce' ),
			/* translators: 1: Category or group name. 2: Number of products in it. 3: Limit of products in a category. */
			'limitTab'     => __( 'Category "%1$s" already has %2$d of %3$d products. Remove one to add another.', 'rvn-compare-products-for-woocommerce' ),
			/* translators: %d: Number of products added when merging the guest list with the account list. */
			'merged'       => __( 'Comparison list merged: %d products added', 'rvn-compare-products-for-woocommerce' ),
			'undo'         => __( 'Undo', 'rvn-compare-products-for-woocommerce' ),
			'close'        => __( 'Close notification', 'rvn-compare-products-for-woocommerce' ),
			'unavailable'  => __( 'This product is no longer available', 'rvn-compare-products-for-woocommerce' ),
			'requestError' => __( 'Could not update your comparison list. Please try again.', 'rvn-compare-products-for-woocommerce' ),
			'confirmAll'   => __( 'Remove all products from comparison?', 'rvn-compare-products-for-woocommerce' ),
			'confirmTab'   => __( 'Remove all products from this category?', 'rvn-compare-products-for-woocommerce' ),
			'emptyTable'   => __( 'Your comparison list is empty', 'rvn-compare-products-for-woocommerce' ),
		);
	}
}
