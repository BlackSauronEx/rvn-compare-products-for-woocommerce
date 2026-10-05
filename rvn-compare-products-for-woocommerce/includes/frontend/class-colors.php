<?php
/**
 * Производные цвета для кнопок и уведомлений.
 *
 * @package RVN_Compare
 */

namespace RVN_Compare\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Считает оттенки основного цвета из настроек.
 *
 * Администратор задаёт один акцент; насыщенность, оттенок наведения и мягкий
 * фон вычисляются из него, чтобы дизайн оставался цельным при смене цвета.
 */
final class Colors {

	/**
	 * Возвращает набор цветов для акцента.
	 *
	 * @param string $accent Шестнадцатеричный цвет из настроек.
	 * @return array{accent: string, hover: string, soft: string, contrast: string}
	 */
	public static function palette( string $accent ): array {
		$rgb = self::to_rgb( $accent );

		if ( null === $rgb ) {
			$rgb = array( 37, 99, 235 );
		}

		return array(
			'accent'   => self::to_hex( $rgb ),
			'hover'    => self::to_hex( self::adjust( $rgb, -0.18 ) ),
			'soft'     => self::to_hex( self::mix( $rgb, array( 255, 255, 255 ), 0.9 ) ),
			'contrast' => self::luminance( $rgb ) > 0.6 ? '#1f2937' : '#ffffff',
		);
	}

	/**
	 * Разбирает шестнадцатеричный цвет в массив RGB.
	 *
	 * @param string $hex Цвет вида #rgb или #rrggbb.
	 * @return int[]|null
	 */
	private static function to_rgb( string $hex ): ?array {
		$hex = ltrim( trim( $hex ), '#' );

		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
			return null;
		}

		return array(
			(int) hexdec( substr( $hex, 0, 2 ) ),
			(int) hexdec( substr( $hex, 2, 2 ) ),
			(int) hexdec( substr( $hex, 4, 2 ) ),
		);
	}

	/**
	 * Собирает шестнадцатеричный цвет из компонентов.
	 *
	 * @param int[] $rgb Компоненты цвета.
	 * @return string
	 */
	private static function to_hex( array $rgb ): string {
		return sprintf( '#%02x%02x%02x', $rgb[0], $rgb[1], $rgb[2] );
	}

	/**
	 * Делает цвет темнее или светлее на долю яркости.
	 *
	 * @param int[] $rgb   Компоненты цвета.
	 * @param float $ratio Доля от -1 до 1.
	 * @return int[]
	 */
	private static function adjust( array $rgb, float $ratio ): array {
		$target = $ratio < 0 ? 0 : 255;
		$amount = abs( $ratio );

		return array(
			(int) round( $rgb[0] + ( $target - $rgb[0] ) * $amount ),
			(int) round( $rgb[1] + ( $target - $rgb[1] ) * $amount ),
			(int) round( $rgb[2] + ( $target - $rgb[2] ) * $amount ),
		);
	}

	/**
	 * Смешивает два цвета.
	 *
	 * @param int[] $from Компоненты исходного цвета.
	 * @param int[] $to   Компоненты целевого цвета.
	 * @param float $ratio Доля целевого цвета от 0 до 1.
	 * @return int[]
	 */
	private static function mix( array $from, array $to, float $ratio ): array {
		return array(
			(int) round( $from[0] + ( $to[0] - $from[0] ) * $ratio ),
			(int) round( $from[1] + ( $to[1] - $from[1] ) * $ratio ),
			(int) round( $from[2] + ( $to[2] - $from[2] ) * $ratio ),
		);
	}

	/**
	 * Относительная яркость цвета по формуле WCAG.
	 *
	 * @param int[] $rgb Компоненты цвета.
	 * @return float
	 */
	private static function luminance( array $rgb ): float {
		$channels = array();

		foreach ( $rgb as $value ) {
			$channel    = $value / 255;
			$channels[] = $channel <= 0.03928 ? $channel / 12.92 : pow( ( $channel + 0.055 ) / 1.055, 2.4 );
		}

		return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
	}
}
