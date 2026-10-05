<?php
/**
 * Лимиты списка сравнения.
 *
 * @package RVN_Compare
 */

namespace RVN_Compare;

defined( 'ABSPATH' ) || exit;

/**
 * Проверяет, можно ли добавить товар, не нарушая лимиты.
 *
 * Правило железное: добавление блокируется, если заполнена любая из вкладок
 * товара (категория или группа) — тогда покупатель сразу понимает причину,
 * а не получает тихое переполнение одной из вкладок.
 */
final class Limits {

	/**
	 * Проверяет добавление товара в список.
	 *
	 * @param int[]                $current_ids Текущий список пользователя.
	 * @param int                  $product_id  ID добавляемого товара.
	 * @param array<string, mixed> $settings    Настройки плагина.
	 * @return array{ok: bool, code: string, tab: string|null, count: int, limit: int}
	 */
	public static function check_add( array $current_ids, int $product_id, array $settings ): array {
		$total = (int) $settings['limit_total'];
		$per   = (int) $settings['limit_per_category'];
		$count = count( $current_ids );
		$fail  = array(
			'ok'    => true,
			'code'  => 'ok',
			'tab'   => null,
			'count' => $count,
			'limit' => $total,
		);

		if ( $count >= $total ) {
			return array_merge(
				$fail,
				array(
					'ok'    => false,
					'code'  => 'limit_total',
					'limit' => $total,
				)
			);
		}

		$membership = Category_Model::membership( $product_id, $settings );

		foreach ( Category_Model::tabs_for( $current_ids, $settings ) as $tab ) {
			if ( ! in_array( $tab['key'], $membership, true ) ) {
				continue;
			}

			if ( count( $tab['ids'] ) >= $per ) {
				return array_merge(
					$fail,
					array(
						'ok'    => false,
						'code'  => 'limit_category',
						'tab'   => $tab['key'],
						'count' => count( $tab['ids'] ),
						'limit' => $per,
					)
				);
			}
		}

		return $fail;
	}
}
