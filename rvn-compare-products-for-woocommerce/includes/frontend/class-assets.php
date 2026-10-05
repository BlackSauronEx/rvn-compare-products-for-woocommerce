<?php
/**
 * Подключение стилей и скриптов плагина на сайте.
 *
 * @package RVN_Compare
 */

namespace RVN_Compare\Frontend;

use RVN_Compare\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Регистрирует и подключает ассеты, передаёт скрипту данные о настройках.
 *
 * Подключение выполняется на штатном хуке вывода скриптов: до него очередь
 * ещё не готова, а рендер блоков и карточек может запросить ассеты раньше.
 * Файлы плагина подключаются только там, где есть элементы сравнения, — на
 * остальных страницах не загружается ни одного лишнего байта.
 */
final class Assets {

	/**
	 * Handle скрипта сравнения.
	 *
	 * @var string
	 */
	const SCRIPT = 'rvn-compare';

	/**
	 * Handle стилей сравнения.
	 *
	 * @var string
	 */
	const STYLE = 'rvn-compare';

	/**
	 * Ассеты, нужные только странице сравнения.
	 *
	 * @var string
	 */
	private const TABLE = 'rvn-compare-table';

	/**
	 * Ассеты подключены в текущем запросе.
	 *
	 * @var bool
	 */
	private static bool $enqueued = false;

	/**
	 * Данные для скрипта добавлены.
	 *
	 * @var bool
	 */
	private static bool $data_added = false;

	/**
	 * Ассеты нужны на этой странице: их запросил рендер кнопки или шорткод.
	 *
	 * @var bool
	 */
	private static bool $needed = false;

	/**
	 * Подключает обработчики.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'wp_enqueue_scripts', array( self::class, 'register' ), 5 );
		add_action( 'wp_enqueue_scripts', array( self::class, 'maybe_enqueue' ), 10 );
		add_action( 'wp_enqueue_scripts', array( self::class, 'finalize' ), 99 );
	}

	/**
	 * Регистрирует файлы, но не подключает их.
	 *
	 * @return void
	 */
	public static function register(): void {
		wp_register_style( self::STYLE, RVN_COMPARE_URL . 'assets/css/compare.css', array(), RVN_COMPARE_VERSION );
		wp_register_script( self::SCRIPT, RVN_COMPARE_URL . 'assets/js/compare.js', array(), RVN_COMPARE_VERSION, true );
		wp_register_style( self::TABLE, RVN_COMPARE_URL . 'assets/css/table.css', array( self::STYLE ), RVN_COMPARE_VERSION );
		wp_register_script( self::TABLE, RVN_COMPARE_URL . 'assets/js/table.js', array( self::SCRIPT ), RVN_COMPARE_VERSION, true );
	}

	/**
	 * Подключает ассеты, если на текущей странице есть элементы сравнения.
	 *
	 * @return void
	 */
	public static function maybe_enqueue(): void {
		if ( self::$needed || self::page_needs_assets() ) {
			self::enqueue();
		}

		if ( Comparison_Page::is_current() || self::page_has_shortcode( 'rvn-compare-table' ) ) {
			self::enqueue_table();
		}
	}

	/**
	 * Сообщает плагину, что на странице нужны стили и скрипт.
	 *
	 * Вызывается рендером кнопок и шорткодами. Если очередь скриптов уже
	 * сформирована, подключение происходит сразу; если ещё нет — флаг
	 * передаст его методу maybe_enqueue().
	 *
	 * @return void
	 */
	public static function enqueue(): void {
		self::$needed = true;

		if ( self::$enqueued ) {
			return;
		}

		if ( ! did_action( 'wp_enqueue_scripts' ) ) {
			return;
		}

		self::$enqueued = true;

		wp_enqueue_style( self::STYLE );
		wp_enqueue_script( self::SCRIPT );

		self::attach_data( Settings::all() );
	}

	/**
	 * Подключает CSS и скрипт таблицы только там, где она действительно показана.
	 *
	 * @return void
	 */
	public static function enqueue_table(): void {
		self::enqueue();

		if ( ! did_action( 'wp_enqueue_scripts' ) || wp_script_is( self::TABLE, 'enqueued' ) ) {
			return;
		}

		wp_enqueue_style( self::TABLE );
		wp_enqueue_script( self::TABLE );

		$settings = Settings::all();
		$data     = array(
			'restUrl'       => esc_url_raw( rest_url( 'rvn-compare/v1/table' ) ),
			'columns'       => array(
				'desktop' => (int) $settings['columns_desktop'],
				'tablet'  => (int) $settings['columns_tablet'],
				'phone'   => (int) $settings['columns_phone'],
			),
			'breakpoints'   => array(
				'tablet' => (int) $settings['breakpoint_tablet'],
				'phone'  => (int) $settings['breakpoint_phone'],
			),
			'scrollbar'     => (string) $settings['scrollbar_mode'],
			'animation'     => (int) $settings['animate_ms'],
			'groupsEnabled' => ! empty( $settings['groups_enabled'] ),
			'i18n'          => array(
				/* translators: %s: Name of the active category. */
				'clearTab'       => __( 'Clear %s', 'rvn-compare-products-for-woocommerce' ),
				'progress'       => __( 'products in this category', 'rvn-compare-products-for-woocommerce' ),
				'noDifferences'  => __( 'The selected products have no differing values in the visible rows.', 'rvn-compare-products-for-woocommerce' ),
				'selectCategory' => __( 'Select a comparison category', 'rvn-compare-products-for-woocommerce' ),
				'loadError'      => __( 'Could not load products. Please try again.', 'rvn-compare-products-for-woocommerce' ),
				'confirmAll'     => __( 'Remove all products from comparison?', 'rvn-compare-products-for-woocommerce' ),
				'confirmTab'     => __( 'Remove all products from this category?', 'rvn-compare-products-for-woocommerce' ),
				'remove'         => __( 'Remove from comparison', 'rvn-compare-products-for-woocommerce' ),
				'tooltip'        => __( 'More information', 'rvn-compare-products-for-woocommerce' ),
			),
		);

		$json = wp_json_encode( $data );

		if ( is_string( $json ) ) {
			wp_add_inline_script( self::TABLE, 'window.rvnCompareTableData = ' . $json . ';', 'before' );
		}
	}

	/**
	 * Добавляет данные скрипту, если он подключён на этой странице.
	 *
	 * @return void
	 */
	public static function finalize(): void {
		if ( ! self::$enqueued ) {
			return;
		}

		$settings = Settings::all();
		$css      = self::style_variables( $settings );

		if ( '' !== $css ) {
			wp_add_inline_style( self::STYLE, $css );
		}

		self::attach_data( $settings );
	}

	/**
	 * Нужны ли на текущей странице наши элементы.
	 *
	 * @return bool
	 */
	private static function page_needs_assets(): bool {
		if ( self::page_has_shortcode() ) {
			return true;
		}

		$settings = Settings::all();

		if ( 'off' === (string) $settings['card_position'] && 'off' === (string) $settings['single_position'] ) {
			return false;
		}

		if ( ! function_exists( 'is_woocommerce' ) ) {
			return false;
		}

		return is_shop()
			|| is_product()
			|| is_product_taxonomy()
			|| is_cart()
			|| is_search()
			|| is_home()
			|| is_front_page();
	}

	/**
	 * Есть ли на текущей записи наш шорткод.
	 *
	 * @param string $only Если задано, проверяет только один шорткод.
	 * @return bool
	 */
	private static function page_has_shortcode( string $only = '' ): bool {
		$post = get_post();

		if ( ! $post instanceof \WP_Post ) {
			return false;
		}

		$shortcodes = '' !== $only ? array( $only ) : self::shortcodes();

		foreach ( $shortcodes as $shortcode ) {
			if ( has_shortcode( (string) $post->post_content, $shortcode ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Названия шорткодов плагина.
	 *
	 * @return string[]
	 */
	private static function shortcodes(): array {
		return array(
			'rvn-compare-button',
			'rvn-compare-counter',
			'rvn-compare-counter-button',
			'rvn-compare-clear',
			'rvn-compare-progress',
			'rvn-compare-table',
		);
	}

	/**
	 * Передаёт скрипту настройки и адреса.
	 *
	 * Данные добавляются явным inline-скриптом: результат виден в разметке
	 * и не зависит от порядка очереди.
	 *
	 * @param array<string, mixed> $settings Настройки плагина.
	 * @return void
	 */
	private static function attach_data( array $settings ): void {
		if ( self::$data_added ) {
			return;
		}

		/**
		 * Фильтрует данные, которые плагин передаёт своему скрипту.
		 *
		 * @since 0.3.0
		 *
		 * @param array<string, mixed> $data Данные скрипта.
		 */
		$data = apply_filters( 'rvn_compare_script_data', self::script_data( $settings ) );
		$json = wp_json_encode( $data );

		if ( ! is_string( $json ) ) {
			return;
		}

		self::$data_added = true;

		wp_add_inline_script( self::SCRIPT, 'window.rvnCompareData = ' . $json . ';', 'before' );
	}

	/**
	 * Собирает данные для скрипта.
	 *
	 * @param array<string, mixed> $settings Настройки плагина.
	 * @return array<string, mixed>
	 */
	private static function script_data( array $settings ): array {
		return array(
			'restUrl'             => esc_url_raw( rest_url( 'rvn-compare/v1' ) ),
			'nonceRefreshUrl'     => esc_url_raw( admin_url( 'admin-ajax.php?action=' . \RVN_Compare\Nonce_Recovery::ACTION ) ),
			'nonce'               => wp_create_nonce( 'wp_rest' ),
			'storageKey'          => self::storage_key(),
			'isUser'              => is_user_logged_in(),
			'comparePage'         => Comparison_Page::url(),
			'limits'              => array(
				'total'       => (int) $settings['limit_total'],
				'perCategory' => (int) $settings['limit_per_category'],
			),
			'animateMs'           => (int) $settings['animate_ms'],
			'toastPosition'       => (string) $settings['toast_position'],
			'toastPositionMobile' => (string) $settings['toast_position_mobile'],
			'toastDuration'       => (int) $settings['toast_duration'],
			'i18n'                => Toasts::labels(),
		);
	}

	/**
	 * Формирует набор CSS-переменных из настроек.
	 *
	 * Переменные содержат только проверенные значения (цвет, длина, число),
	 * поэтому подставлять их в стиль безопасно.
	 *
	 * @param array<string, mixed> $settings Настройки плагина.
	 * @return string
	 */
	private static function style_variables( array $settings ): string {
		$colors = Colors::palette( (string) $settings['accent_color'] );

		$variables = array(
			'--rvn-accent'        => $colors['accent'],
			'--rvn-accent-hover'  => $colors['hover'],
			'--rvn-accent-soft'   => $colors['soft'],
			'--rvn-animate'       => max( 0, (int) $settings['animate_ms'] ) . 'ms',
			'--rvn-offset-top'    => (string) $settings['scroll_offset_top'],
			'--rvn-offset-bottom' => (string) $settings['scroll_offset_bottom'],
		);

		$css = ':root{';

		foreach ( $variables as $name => $value ) {
			$css .= $name . ':' . $value . ';';
		}

		return $css . '}';
	}

	/**
	 * Ключ списка в localStorage: отдельный для каждого сайта сети.
	 *
	 * @return string
	 */
	private static function storage_key(): string {
		return 'rvn-compare:list:' . substr( md5( home_url( '/' ) ), 0, 8 );
	}
}
