<?php
/**
 * Удаление данных плагина при его деинсталляции.
 *
 * Данные удаляются, только если на сайте включена настройка
 * «Удалять данные при удалении плагина» (по умолчанию включена).
 * Страница сравнения не удаляется никогда: это контент владельца сайта.
 *
 * Каждый новый ключ данных плагина нужно добавить в списки ниже.
 * Файл совместим с PHP 7.0: он может выполняться, даже если плагин неактивен.
 *
 * @package RVN_Compare
 */

namespace RVN_Compare\Uninstall;

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Опции плагина на уровне сайта.
 */
const OPTIONS = array(
	'rvn_compare_version',
	'rvn_compare_settings',
	'rvn_compare_page_id',
	'rvn_compare_page_backup',
);

/**
 * Пользовательские опции (списки сравнения); хранятся отдельно для каждого сайта сети.
 */
const USER_OPTIONS = array(
	'rvn_compare_list',
);

/**
 * Проверяет, разрешил ли администратор удалять данные на текущем сайте.
 *
 * @return bool
 */
function should_delete_data(): bool {
	$settings = get_option( 'rvn_compare_settings', array() );

	if ( is_array( $settings ) && array_key_exists( 'delete_data_on_uninstall', $settings ) ) {
		return (bool) $settings['delete_data_on_uninstall'];
	}

	return true;
}

/**
 * Удаляет данные плагина на текущем сайте.
 *
 * Используются только функции API WordPress; прямых запросов к базе данных нет.
 *
 * @return void
 */
function cleanup_site() {
	global $wpdb;

	if ( ! should_delete_data() ) {
		return;
	}

	foreach ( OPTIONS as $option ) {
		delete_option( $option );
	}

	foreach ( USER_OPTIONS as $user_option ) {
		delete_metadata( 'user', 0, $wpdb->get_blog_prefix() . $user_option, '', true );
	}
}

/**
 * Удаляет данные на текущем сайте или на всех сайтах сети (порциями по 100).
 *
 * @return void
 */
function run() {
	if ( ! is_multisite() ) {
		cleanup_site();
		return;
	}

	$offset = 0;

	do {
		$site_ids = get_sites(
			array(
				'fields' => 'ids',
				'number' => 100,
				'offset' => $offset,
			)
		);

		foreach ( $site_ids as $site_id ) {
			switch_to_blog( (int) $site_id );
			cleanup_site();
			restore_current_blog();
		}

		$offset += 100;
		$batch   = count( $site_ids );
	} while ( 100 === $batch );
}

run();
