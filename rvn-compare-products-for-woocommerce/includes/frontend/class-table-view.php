<?php
/**
 * Безопасная оболочка страницы сравнения для shortcode.
 *
 * @package RVN_Compare
 */

namespace RVN_Compare\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Создаёт кэшируемую оболочку таблицы без персональных данных посетителя.
 */
final class Table_View {

	/**
	 * Строит HTML-оболочку таблицы; колонки загрузит JavaScript через REST.
	 *
	 * @param string $classes   Дополнительные очищенные CSS-классы.
	 * @param int[]  $fixed     Фиксированные товары статического шорткода.
	 * @param bool   $is_static Передан аргумент products, даже если все ID недоступны.
	 * @return string
	 */
	public static function render( string $classes = '', array $fixed = array(), bool $is_static = false ): string {
		$extra   = Button_Renderer::class_list( $classes );
		$classes = implode( ' ', array_merge( array( 'rvn-compare-table' ), $extra ) );
		$ids     = implode( ',', array_map( 'absint', $fixed ) );
		$shop    = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );

		$html  = '<section class="' . esc_attr( $classes ) . '" data-rvn-table data-static="' . ( $is_static ? '1' : '0' ) . '" data-products="' . esc_attr( $ids ) . '" aria-label="' . esc_attr__( 'Compare products', 'rvn-compare-products-for-woocommerce' ) . '">';
		$html .= '<div class="rvn-table-head"><p class="rvn-table-eyebrow">' . esc_html__( 'YOUR SHORTLIST', 'rvn-compare-products-for-woocommerce' ) . '</p>';
		$html .= '<div class="rvn-table-title-line"><div><h2>' . esc_html__( 'Product comparison', 'rvn-compare-products-for-woocommerce' ) . '</h2>';
		$html .= '<p class="rvn-table-subtitle">' . esc_html__( 'Find what sets your products apart.', 'rvn-compare-products-for-woocommerce' ) . '</p></div>';
		$html .= '<span class="rvn-table-count" data-rvn-table-count aria-live="polite"></span></div></div>';
		$html .= '<div class="rvn-table-loading" data-rvn-loading role="status">' . esc_html__( 'Loading your comparison…', 'rvn-compare-products-for-woocommerce' ) . '</div>';
		$html .= '<div class="rvn-table-empty" data-rvn-table-empty hidden><span class="rvn-table-empty-mark" aria-hidden="true">⇄</span><h3>' . esc_html__( 'Nothing to compare yet', 'rvn-compare-products-for-woocommerce' ) . '</h3>';
		$html .= '<p>' . esc_html__( 'Choose products in the shop and they will appear here.', 'rvn-compare-products-for-woocommerce' ) . '</p>';
		$html .= '<a class="rvn-table-shop-link" href="' . esc_url( (string) $shop ) . '">' . esc_html__( 'Explore the shop', 'rvn-compare-products-for-woocommerce' ) . ' →</a></div>';
		$html .= '<div class="rvn-table-content" data-rvn-table-content hidden>';
		$html .= '<nav class="rvn-table-tabs" data-rvn-tabs aria-label="' . esc_attr__( 'Comparison categories', 'rvn-compare-products-for-woocommerce' ) . '"></nav>';
		$html .= '<div class="rvn-table-actions" data-rvn-actions><label class="rvn-table-diff-toggle"><input type="checkbox" data-rvn-differences> <span>' . esc_html__( 'Only differences', 'rvn-compare-products-for-woocommerce' ) . '</span></label>';
		$html .= '<div class="rvn-table-clear-actions"><button type="button" data-rvn-clear-tab></button><button type="button" data-rvn-clear-all>' . esc_html__( 'Clear all', 'rvn-compare-products-for-woocommerce' ) . '</button></div></div>';
		$html .= '<div class="rvn-table-comparison" data-rvn-comparison><div class="rvn-table-header-row">';
		$html .= '<div class="rvn-table-arrows"><button type="button" class="rvn-table-arrow rvn-table-arrow-prev" data-rvn-arrow="prev" aria-label="' . esc_attr__( 'Previous product', 'rvn-compare-products-for-woocommerce' ) . '" disabled><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"></polyline></svg></button><button type="button" class="rvn-table-arrow rvn-table-arrow-next" data-rvn-arrow="next" aria-label="' . esc_attr__( 'Next product', 'rvn-compare-products-for-woocommerce' ) . '" disabled><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"></polyline></svg></button></div>';
		$html .= '<div class="rvn-table-strip rvn-table-products" data-rvn-product-strip tabindex="0" role="group" aria-label="' . esc_attr__( 'Compared products', 'rvn-compare-products-for-woocommerce' ) . '"></div></div>';
		$html .= '<div class="rvn-table-rows" data-rvn-rows></div><div class="rvn-table-buy-row rvn-table-strip" data-rvn-buy-strip></div></div></div>';
		$html .= '<div class="rvn-table-tooltip" data-rvn-tooltip hidden role="tooltip"></div>';
		$html .= '<noscript><p>' . esc_html__( 'Enable JavaScript to view your comparison list.', 'rvn-compare-products-for-woocommerce' ) . '</p></noscript></section>';

		return $html;
	}
}
