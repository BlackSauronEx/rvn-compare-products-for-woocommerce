<?php
/**
 * Создание и безопасное обслуживание страницы сравнения товаров.
 *
 * @package RVN_Compare
 */

namespace RVN_Compare\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Создаёт страницу без перезаписи чужого контента и следит за выводом таблицы.
 */
final class Comparison_Page {

	/**
	 * Опция с ID выбранной страницы.
	 *
	 * @var string
	 */
	public const OPTION = 'rvn_compare_page_id';

	/**
	 * Резервная копия содержимого после ручного сброса администратором.
	 *
	 * @var string
	 */
	public const BACKUP_OPTION = 'rvn_compare_page_backup';

	/**
	 * Шорткод таблицы.
	 *
	 * @var string
	 */
	public const SHORTCODE = 'rvn-compare-table';

	/**
	 * Подключает фильтры контента, метки и индексации страницы.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_filter( 'display_post_states', array( self::class, 'display_state' ), 10, 2 );
		add_filter( 'wp_robots', array( self::class, 'robots' ) );
		add_filter( 'the_content', array( self::class, 'append_table' ), 8 );
		add_action( 'admin_post_rvn_compare_create_page', array( self::class, 'handle_create' ) );
		add_action( 'admin_post_rvn_compare_reset_page', array( self::class, 'handle_reset' ) );
		add_action( 'admin_post_rvn_compare_restore_page', array( self::class, 'handle_restore' ) );
		add_action( 'admin_post_rvn_compare_select_page', array( self::class, 'handle_select' ) );
	}

	/**
	 * Возвращает ID выбранной опубликованной страницы или ноль.
	 *
	 * @return int
	 */
	public static function id(): int {
		$id = (int) get_option( self::OPTION, 0 );

		return $id > 0 && 'page' === get_post_type( $id ) && 'publish' === get_post_status( $id ) ? $id : 0;
	}

	/**
	 * Возвращает ссылку на страницу сравнения.
	 *
	 * @return string
	 */
	public static function url(): string {
		$id = self::id();

		if ( $id > 0 ) {
			$url = get_permalink( $id );

			if ( is_string( $url ) && '' !== $url ) {
				return $url;
			}
		}

		/**
		 * Фильтрует ссылку при отсутствии выбранной страницы.
		 *
		 * @since 0.3.0
		 * @param string $url Адрес страницы по умолчанию.
		 */
		return (string) apply_filters( 'rvn_compare_page_url', home_url( '/compare/' ) );
	}

	/**
	 * Проверяет, просматривается ли выбранная страница сравнения.
	 *
	 * @return bool
	 */
	public static function is_current(): bool {
		$id = self::id();

		return $id > 0 && is_page( $id );
	}

	/**
	 * Создаёт или находит страницу при активации/первом обновлении.
	 *
	 * Если slug compare занят чужой страницей, не изменяем её: WordPress
	 * назначит новой странице compare-products или уникальный вариант slug.
	 *
	 * @return int ID назначенной страницы или ноль при ошибке.
	 */
	public static function ensure(): int {
		$id = self::id();

		if ( $id > 0 ) {
			return $id;
		}

		$existing = get_page_by_path( 'compare', OBJECT, 'page' );

		if ( $existing instanceof \WP_Post && has_shortcode( (string) $existing->post_content, self::SHORTCODE ) ) {
			update_option( self::OPTION, (int) $existing->ID, false );
			return (int) $existing->ID;
		}

		$slug = $existing instanceof \WP_Post ? 'compare-products' : 'compare';
		$page = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => self::default_title(),
				'post_name'    => $slug,
				'post_content' => '[' . self::SHORTCODE . ']',
				'post_author'  => (int) get_current_user_id(),
			),
			true
		);

		if ( is_wp_error( $page ) || ! is_int( $page ) || $page <= 0 ) {
			return 0;
		}

		update_option( self::OPTION, $page, false );
		return $page;
	}

	/**
	 * Название созданной страницы по языку сайта, а не профиля администратора.
	 *
	 * @return string
	 */
	private static function default_title(): string {
		return __( 'Product comparison', 'rvn-compare-products-for-woocommerce' );
	}

	/**
	 * Добавляет метку «Страница сравнения» к выбранной странице в списке.
	 *
	 * @param array<string, string> $states Текущие метки.
	 * @param \WP_Post              $post   Страница из списка.
	 * @return array<string, string>
	 */
	public static function display_state( array $states, \WP_Post $post ): array {
		if ( self::id() === $post->ID ) {
			$states['rvn_compare'] = __( 'Comparison page', 'rvn-compare-products-for-woocommerce' );
		}

		return $states;
	}

	/**
	 * Исключает персональную страницу сравнения из индекса поисковиков.
	 *
	 * @param array<string, bool> $robots Директивы WordPress.
	 * @return array<string, bool>
	 */
	public static function robots( array $robots ): array {
		if ( self::is_current() && ! empty( \RVN_Compare\Settings::get( 'noindex_compare_page' ) ) ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
			unset( $robots['index'], $robots['nofollow'] );
		}

		return $robots;
	}

	/**
	 * Дописывает таблицу в конце страницы лишь при отсутствии шорткода.
	 *
	 * Фильтр выполняется до стандартной обработки шорткодов WordPress.
	 * Проверка делается по исходному post_content: в результирующем HTML
	 * шорткод уже исчез, а проверка по HTML создала бы дубли.
	 *
	 * @param string $content Содержимое страницы.
	 * @return string
	 */
	public static function append_table( string $content ): string {
		if ( ! self::is_current() || ! in_the_loop() || ! is_main_query() || empty( \RVN_Compare\Settings::get( 'auto_insert_table' ) ) ) {
			return $content;
		}

		$page = get_post( self::id() );

		if ( $page instanceof \WP_Post && has_shortcode( (string) $page->post_content, self::SHORTCODE ) ) {
			return $content;
		}

		return $content . "\n\n[" . self::SHORTCODE . ']';
	}

	/**
	 * Создаёт недостающую страницу по явному действию администратора.
	 *
	 * @return void
	 */
	public static function handle_create(): void {
		self::authorize();
		check_admin_referer( 'rvn_compare_create_page' );
		$id = self::ensure();
		self::redirect( $id > 0 ? 'created' : 'failed' );
	}

	/**
	 * Назначает существующую страницу страницей сравнения.
	 *
	 * @return void
	 */
	public static function handle_select(): void {
		self::authorize();
		check_admin_referer( 'rvn_compare_select_page' );
		$id = isset( $_POST['page_id'] ) ? absint( wp_unslash( $_POST['page_id'] ) ) : 0;

		if ( $id <= 0 || 'page' !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) ) {
			self::redirect( 'invalid' );
		}

		update_option( self::OPTION, $id, false );
		self::redirect( 'selected' );
	}

	/**
	 * Сохраняет старый контент и сбрасывает страницу к одному шорткоду.
	 *
	 * Никакого автоматического сброса: это отдельный POST с правом доступа,
	 * nonce и подтверждением пользователя в форме администратора.
	 *
	 * @return void
	 */
	public static function handle_reset(): void {
		self::authorize();
		check_admin_referer( 'rvn_compare_reset_page' );
		$id = self::id();

		if ( $id <= 0 || ! current_user_can( 'edit_post', $id ) ) {
			self::redirect( 'invalid' );
		}

		$page = get_post( $id );

		if ( ! $page instanceof \WP_Post ) {
			self::redirect( 'invalid' );
		}

		update_option(
			self::BACKUP_OPTION,
			array(
				'id'      => $id,
				'content' => (string) $page->post_content,
			),
			false
		);
		wp_save_post_revision( $id );
		$result = wp_update_post(
			array(
				'ID'           => $id,
				'post_content' => '[' . self::SHORTCODE . ']',
			),
			true
		);
		self::redirect( is_wp_error( $result ) ? 'failed' : 'reset' );
	}

	/**
	 * Восстанавливает страницу из копии, созданной ручным сбросом.
	 *
	 * @return void
	 */
	public static function handle_restore(): void {
		self::authorize();
		check_admin_referer( 'rvn_compare_restore_page' );
		$backup = get_option( self::BACKUP_OPTION, false );
		$id     = self::id();

		if ( ! is_array( $backup ) || ! isset( $backup['id'], $backup['content'] ) || (int) $backup['id'] !== $id || ! current_user_can( 'edit_post', $id ) ) {
			self::redirect( 'invalid' );
		}

		$result = wp_update_post(
			array(
				'ID'           => $id,
				'post_content' => (string) $backup['content'],
			),
			true
		);

		if ( ! is_wp_error( $result ) ) {
			delete_option( self::BACKUP_OPTION );
		}

		self::redirect( is_wp_error( $result ) ? 'failed' : 'restored' );
	}

	/**
	 * Проверяет право действия со страницей; nonce проверяет сам обработчик.
	 *
	 * @return void
	 */
	private static function authorize(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to change the comparison page.', 'rvn-compare-products-for-woocommerce' ), '', array( 'response' => 403 ) );
		}
	}

	/**
	 * Перенаправляет администратора обратно на вкладку настроек.
	 *
	 * @param string $result Короткий код исхода операции.
	 * @return void
	 */
	private static function redirect( string $result ): void {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => 'rvn-compare',
					'tab'        => 'page',
					'rvn_result' => $result,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
