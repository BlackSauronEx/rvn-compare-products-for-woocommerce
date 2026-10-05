<?php
/**
 * Установка и обновление данных плагина.
 *
 * @package RVN_Compare
 */

namespace RVN_Compare;

use RVN_Compare\Frontend\Comparison_Page;

defined( 'ABSPATH' ) || exit;

/**
 * Хранит версию данных плагина и запускает установку для каждого сайта.
 *
 * При сетевой активации в мультисайте установка не перебирает все сайты сразу
 * (это может быть очень долго), а выполняется на каждом сайте при первом входе в консоль.
 */
final class Installer {

	/**
	 * Опция с версией данных плагина.
	 *
	 * @var string
	 */
	public const VERSION_OPTION = 'rvn_compare_version';

	/**
	 * Подключает проверку версии данных.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'admin_init', array( self::class, 'maybe_install' ) );
	}

	/**
	 * Обработчик активации плагина.
	 *
	 * @param bool $network_wide Плагин активирован для всей сети сайтов.
	 * @return void
	 */
	public static function activate( bool $network_wide = false ): void {
		if ( is_multisite() && $network_wide ) {
			return;
		}

		self::install();
		self::flush_page_cache();
	}

	/**
	 * Сбрасывает кэш страниц, если активен один из популярных плагинов кэширования.
	 *
	 * Страницы, закэшированные до активации плагина, продолжали бы отдаваться
	 * без кнопок сравнения до истечения срока кэша. Сброс выполняется «наилучшим
	 * образом»: функции конкретного плагина могут отсутствовать.
	 *
	 * @return void
	 */
	private static function flush_page_cache(): void {
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			// WP Super Cache.
			wp_cache_clear_cache();
		}

		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all();
		}

		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
		}

		if ( function_exists( 'litespeed_purge_all' ) ) {
			litespeed_purge_all();
		}
	}

	/**
	 * Выполняет установку, если данные отсутствуют или устарели.
	 *
	 * @return void
	 */
	public static function maybe_install(): void {
		$installed = get_option( self::VERSION_OPTION, '' );

		if ( is_string( $installed ) && '' !== $installed && version_compare( $installed, RVN_COMPARE_VERSION, '>=' ) ) {
			return;
		}

		// Обновление: старая HTML-страница может не содержать новых кнопок и ассетов.
		// Сбрасываем кэш до записи версии, чтобы не помечать обновление завершённым
		// в случае сбоя при очистке. На первом входе в админку это выполнится один раз.
		self::flush_page_cache();
		self::install();
	}

	/**
	 * Создаёт или обновляет данные плагина.
	 *
	 * @return void
	 */
	private static function install(): void {
		// Версию записываем только после успешного создания страницы. В противном
		// случае повторим операцию при следующем входе в консоль, не ломая сайт.
		if ( Comparison_Page::ensure() > 0 ) {
			update_option( self::VERSION_OPTION, RVN_COMPARE_VERSION, true );
		}
	}
}
