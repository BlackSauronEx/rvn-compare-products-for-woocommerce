<?php
/**
 * Настройки страницы сравнения в меню RVN.
 *
 * @package RVN_Compare
 */

namespace RVN_Compare\Admin;

use RVN_Compare\Frontend\Comparison_Page;
use RVN_Compare\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Показывает страницу сравнения и формы её безопасного обслуживания.
 */
final class Page_Settings {

	/**
	 * Подключает обработчик сохранения настроек страницы.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'admin_post_rvn_compare_page_options', array( self::class, 'save' ) );
	}

	/**
	 * Показывает текущее назначение, переключатели и действия над страницей.
	 *
	 * @return void
	 */
	public static function render(): void {
		$id       = Comparison_Page::id();
		$settings = Settings::all();
		$pages    = get_pages(
			array(
				'post_status' => 'publish',
				'sort_column' => 'post_title',
			)
		);
		$backup   = get_option( Comparison_Page::BACKUP_OPTION, false );
		?>
		<h2><?php esc_html_e( 'Comparison page', 'rvn-compare-products-for-woocommerce' ); ?></h2>
		<p class="description"><?php esc_html_e( 'The comparison table is displayed on the selected page. A page created by the plugin starts with the table shortcode. Your other pages are never overwritten automatically.', 'rvn-compare-products-for-woocommerce' ); ?></p>

		<?php if ( $id > 0 ) : ?>
			<p><?php esc_html_e( 'Current page:', 'rvn-compare-products-for-woocommerce' ); ?> <strong><?php echo esc_html( get_the_title( $id ) ); ?></strong> — <a href="<?php echo esc_url( get_permalink( $id ) ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( get_permalink( $id ) ); ?></a></p>
		<?php else : ?>
			<p class="notice notice-warning inline"><?php esc_html_e( 'No published comparison page is selected. Create one or choose an existing page.', 'rvn-compare-products-for-woocommerce' ); ?></p>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="rvn_compare_select_page">
			<?php wp_nonce_field( 'rvn_compare_select_page' ); ?>
			<p><label for="rvn-compare-page-id"><strong><?php esc_html_e( 'Page used for comparison', 'rvn-compare-products-for-woocommerce' ); ?></strong></label><br>
			<select id="rvn-compare-page-id" name="page_id" required>
				<option value=""><?php esc_html_e( 'Select a page', 'rvn-compare-products-for-woocommerce' ); ?></option>
				<?php foreach ( $pages as $page ) : ?>
					<option value="<?php echo esc_attr( (string) $page->ID ); ?>" <?php selected( $id, $page->ID ); ?>><?php echo esc_html( $page->post_title ); ?></option>
				<?php endforeach; ?>
			</select></p>
			<p class="description"><?php esc_html_e( 'Choosing an existing page does not change its content. Enable automatic insertion below if it has no table shortcode.', 'rvn-compare-products-for-woocommerce' ); ?></p>
			<?php submit_button( __( 'Save page', 'rvn-compare-products-for-woocommerce' ), 'secondary' ); ?>
		</form>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="rvn_compare_create_page">
			<?php wp_nonce_field( 'rvn_compare_create_page' ); ?>
			<?php submit_button( __( 'Create comparison page', 'rvn-compare-products-for-woocommerce' ), 'secondary' ); ?>
		</form>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="rvn_compare_page_options">
			<?php wp_nonce_field( 'rvn_compare_page_options' ); ?>
			<p><label><input type="checkbox" name="auto_insert_table" value="1" <?php checked( ! empty( $settings['auto_insert_table'] ) ); ?>> <?php esc_html_e( 'Automatically append the table if the selected page has no table shortcode', 'rvn-compare-products-for-woocommerce' ); ?></label></p>
			<p class="description"><?php esc_html_e( 'This does not edit the page in the database and never creates a duplicate when the shortcode is present.', 'rvn-compare-products-for-woocommerce' ); ?></p>
			<p><label><input type="checkbox" name="noindex_compare_page" value="1" <?php checked( ! empty( $settings['noindex_compare_page'] ) ); ?>> <?php esc_html_e( 'Keep the personal comparison page out of search engine results', 'rvn-compare-products-for-woocommerce' ); ?></label></p>
			<?php submit_button( __( 'Save options', 'rvn-compare-products-for-woocommerce' ) ); ?>
		</form>

		<?php if ( $id > 0 ) : ?>
			<h3><?php esc_html_e( 'Reset page content', 'rvn-compare-products-for-woocommerce' ); ?></h3>
			<p class="description"><?php esc_html_e( 'This removes the selected page content and leaves only the table shortcode. A copy of the old content is saved for restoration.', 'rvn-compare-products-for-woocommerce' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return window.confirm(<?php echo esc_attr( wp_json_encode( __( 'Replace the page content with the comparison table?', 'rvn-compare-products-for-woocommerce' ) ) ); ?>);">
				<input type="hidden" name="action" value="rvn_compare_reset_page">
				<?php wp_nonce_field( 'rvn_compare_reset_page' ); ?>
				<?php submit_button( __( 'Reset page to table', 'rvn-compare-products-for-woocommerce' ), 'secondary' ); ?>
			</form>
		<?php endif; ?>

		<?php if ( is_array( $backup ) && isset( $backup['id'], $backup['content'] ) ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="rvn_compare_restore_page">
				<?php wp_nonce_field( 'rvn_compare_restore_page' ); ?>
				<?php submit_button( __( 'Restore content saved before reset', 'rvn-compare-products-for-woocommerce' ), 'secondary' ); ?>
			</form>
		<?php endif; ?>
		<?php
	}

	/**
	 * Проверяет права и nonce, затем сохраняет два флага страницы.
	 *
	 * @return void
	 */
	public static function save(): void {
		if ( ! current_user_can( Admin::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to change comparison settings.', 'rvn-compare-products-for-woocommerce' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( 'rvn_compare_page_options' );

		Settings::update(
			array(
				'auto_insert_table'    => isset( $_POST['auto_insert_table'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['auto_insert_table'] ) ),
				'noindex_compare_page' => isset( $_POST['noindex_compare_page'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['noindex_compare_page'] ) ),
			)
		);

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => 'rvn-compare',
					'tab'        => 'page',
					'rvn_result' => 'saved',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
