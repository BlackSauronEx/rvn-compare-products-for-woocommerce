<?php
/**
 * Общее ядро линейки RVN: пункт меню «RVN» и страница «Поддержка».
 *
 * Копия ядра входит в каждый плагин линейки, но подключается только самая
 * новая (см. bootstrap.php). Поэтому публичный интерфейс ядра — метод
 * Core::add_product() — должен оставаться обратно совместимым.
 *
 * При включении ядра в другой плагин линейки замените текст-домен
 * rvn-compare-products-for-woocommerce на текст-домен этого плагина.
 *
 * @package RVN_Compare
 */

namespace RVN_Core;

defined( 'ABSPATH' ) || exit;

/**
 * Регистрирует пункт «RVN» в блоке меню WooCommerce и страницы плагинов линейки.
 */
final class Core {

	/**
	 * Версия ядра.
	 *
	 * @var string
	 */
	public const VERSION = '1.0.0';

	/**
	 * Идентификатор пункта меню «RVN».
	 *
	 * @var string
	 */
	public const MENU_SLUG = 'rvn';

	/**
	 * Идентификатор страницы «Поддержка».
	 *
	 * @var string
	 */
	public const SUPPORT_SLUG = 'rvn-support';

	/**
	 * Право, необходимое для доступа к меню RVN.
	 *
	 * @var string
	 */
	public const CAPABILITY = 'manage_woocommerce';

	/**
	 * Позиция по умолчанию, если пункт «Маркетинг» не найден: внутри блока WooCommerce.
	 *
	 * @var float
	 */
	private const FALLBACK_POSITION = 58.9;

	/**
	 * Пункт меню зарегистрирован в текущем запросе.
	 *
	 * @var bool
	 */
	private static $menu_registered = false;

	/**
	 * Страницы плагинов линейки, добавленные через add_product().
	 *
	 * @var array<string, array{page_title: string, menu_title: string, capability: string, callback: callable, position: int}>
	 */
	private static $products = array();

	/**
	 * Подключает обработчики ядра.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( self::class, 'register_menu' ), 90 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_assets' ) );
	}

	/**
	 * Регистрирует пункт «RVN», страницы плагинов линейки и страницу «Поддержка».
	 *
	 * Клик по «RVN» открывает первую страницу линейки; автоматический дубль
	 * «RVN» в подменю удаляется.
	 *
	 * @return void
	 */
	public static function register_menu() {
		$products = self::products();

		if ( array() === $products ) {
			return;
		}

		$first = reset( $products );

		add_menu_page(
			$first['page_title'],
			'RVN',
			self::CAPABILITY,
			self::MENU_SLUG,
			$first['callback'],
			'none',
			self::menu_position()
		);

		foreach ( $products as $slug => $product ) {
			add_submenu_page(
				self::MENU_SLUG,
				$product['page_title'],
				$product['menu_title'],
				$product['capability'],
				$slug,
				$product['callback']
			);
		}

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Support', 'rvn-compare-products-for-woocommerce' ),
			esc_html__( 'Support', 'rvn-compare-products-for-woocommerce' ),
			self::CAPABILITY,
			self::SUPPORT_SLUG,
			array( self::class, 'render_support_page' )
		);

		remove_submenu_page( self::MENU_SLUG, self::MENU_SLUG );

		self::$menu_registered = true;
	}

	/**
	 * Подключает стиль значка «R» на экранах консоли, где виден пункт меню.
	 *
	 * @return void
	 */
	public static function enqueue_assets() {
		if ( ! self::$menu_registered ) {
			return;
		}

		wp_enqueue_style( 'rvn-core-admin-menu', plugins_url( 'assets/menu.css', __FILE__ ), array(), self::VERSION );
	}

	/**
	 * Выводит страницу «Поддержка».
	 *
	 * @return void
	 */
	public static function render_support_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die(
				esc_html__( 'Sorry, you are not allowed to access this page.', 'rvn-compare-products-for-woocommerce' ),
				'',
				array( 'response' => 403 )
			);
		}

		echo '<div class="wrap rvn-core-support"><h1>' . esc_html__( 'Support', 'rvn-compare-products-for-woocommerce' ) . '</h1>';
		echo '<p>' . esc_html__( 'This section will be available soon.', 'rvn-compare-products-for-woocommerce' ) . '</p></div>';
	}

	/**
	 * Добавляет страницу плагина линейки в меню «RVN».
	 *
	 * Вызывайте на хуке admin_menu с приоритетом меньше 90.
	 *
	 * @param string               $slug Идентификатор страницы.
	 * @param array<string, mixed> $args Параметры: page_title, menu_title, capability, callback, position.
	 * @return bool True, если страница добавлена.
	 */
	public static function add_product( $slug, array $args ) {
		$slug = sanitize_key( (string) $slug );

		if ( '' === $slug || self::MENU_SLUG === $slug || self::SUPPORT_SLUG === $slug ) {
			return false;
		}

		if ( ! isset( $args['callback'] ) || ! is_callable( $args['callback'] ) ) {
			return false;
		}

		$page_title = isset( $args['page_title'] ) && is_string( $args['page_title'] ) ? $args['page_title'] : $slug;
		$menu_title = isset( $args['menu_title'] ) && is_string( $args['menu_title'] ) ? $args['menu_title'] : $page_title;
		$capability = isset( $args['capability'] ) && is_string( $args['capability'] ) ? $args['capability'] : self::CAPABILITY;

		self::$products[ $slug ] = array(
			'page_title' => wp_strip_all_tags( $page_title ),
			'menu_title' => esc_html( $menu_title ),
			'capability' => $capability,
			'callback'   => $args['callback'],
			'position'   => isset( $args['position'] ) ? (int) $args['position'] : 100,
		);

		return true;
	}

	/**
	 * Возвращает страницы плагинов линейки, отсортированные по позиции.
	 *
	 * @return array<string, array{page_title: string, menu_title: string, capability: string, callback: callable, position: int}>
	 */
	private static function products() {
		$products = self::$products;

		uasort(
			$products,
			static function ( $a, $b ) {
				return $a['position'] <=> $b['position'];
			}
		);

		return $products;
	}

	/**
	 * Вычисляет позицию пункта «RVN»: сразу после «Маркетинга» WooCommerce.
	 *
	 * Позиция берётся посередине между «Маркетингом» и следующим пунктом меню,
	 * чтобы «RVN» оказался в самом низу блока WooCommerce, до разделителя.
	 *
	 * @return float
	 */
	private static function menu_position() {
		$menu      = isset( $GLOBALS['menu'] ) && is_array( $GLOBALS['menu'] ) ? $GLOBALS['menu'] : array();
		$marketing = null;

		foreach ( $menu as $position => $item ) {
			if ( is_array( $item ) && isset( $item[2] ) && 'woocommerce-marketing' === $item[2] ) {
				$marketing = (float) $position;
				break;
			}
		}

		$candidate = self::FALLBACK_POSITION;

		if ( null !== $marketing ) {
			$next = null;

			foreach ( array_keys( $menu ) as $position ) {
				$value = (float) $position;

				if ( $value > $marketing && ( null === $next || $value < $next ) ) {
					$next = $value;
				}
			}

			$candidate = null === $next ? $marketing + 0.5 : ( $marketing + $next ) / 2;
		}

		return $candidate;
	}
}
