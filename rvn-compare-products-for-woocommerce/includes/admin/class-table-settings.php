<?php
/**
 * Безопасное управление полями и группами характеристик в админке.
 *
 * @package RVN_Compare
 */

namespace RVN_Compare\Admin;

use RVN_Compare\Settings;
use RVN_Compare\Table_Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Выводит и сохраняет первую рабочую версию вкладки полей сравнения.
 */
final class Table_Settings {

	/**
	 * Подключает обработчики форм (только авторизованные администраторы магазина).
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'admin_post_rvn_compare_save_fields', array( self::class, 'save' ) );
		add_action( 'admin_post_rvn_compare_add_meta', array( self::class, 'add_meta' ) );
	}

	/**
	 * Выводит таблицу полей, группы, режимы скрытия и добавление мета-поля.
	 *
	 * @return void
	 */
	public static function render(): void {
		$settings = Settings::all();
		$fields   = Table_Fields::fields( $settings );
		$groups   = Table_Fields::groups( $settings );
		?>
		<h2><?php esc_html_e( 'Comparison fields', 'rvn-compare-products-for-woocommerce' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Enable fields, edit their names and hints, assign groups and change their order. Newly discovered WooCommerce attributes appear at the bottom.', 'rvn-compare-products-for-woocommerce' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="rvn_compare_save_fields">
			<?php wp_nonce_field( 'rvn_compare_save_fields' ); ?>
			<h3><?php esc_html_e( 'Field groups', 'rvn-compare-products-for-woocommerce' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Groups become section headings in the comparison table. Enter a custom name or leave it blank to use the current site language.', 'rvn-compare-products-for-woocommerce' ); ?></p>
			<?php foreach ( $groups as $index => $group ) : ?>
				<?php
				$saved_groups = (array) $settings['table_groups'];
				$saved_group  = isset( $saved_groups[ $index ] ) && is_array( $saved_groups[ $index ] ) ? $saved_groups[ $index ] : array();
				?>
				<p class="rvn-admin-group">
					<input type="hidden" name="rvn_groups[<?php echo esc_attr( (string) $index ); ?>][key]" value="<?php echo esc_attr( $group['key'] ); ?>">
					<label for="rvn-group-<?php echo esc_attr( (string) $index ); ?>"><?php echo esc_html( $group['label'] ); ?></label>
					<input type="text" id="rvn-group-<?php echo esc_attr( (string) $index ); ?>" name="rvn_groups[<?php echo esc_attr( (string) $index ); ?>][label]" value="<?php echo esc_attr( $saved_group['label'] ?? '' ); ?>" maxlength="80" placeholder="<?php echo esc_attr( $group['label'] ); ?>">
					<label><input type="checkbox" name="rvn_groups[<?php echo esc_attr( (string) $index ); ?>][enabled]" value="1" <?php checked( ! empty( $group['enabled'] ) ); ?>> <?php esc_html_e( 'Show', 'rvn-compare-products-for-woocommerce' ); ?></label>
					<label><input type="checkbox" name="rvn_groups[<?php echo esc_attr( (string) $index ); ?>][collapsed]" value="1" <?php checked( ! empty( $group['collapsed'] ) ); ?>> <?php esc_html_e( 'Collapsed initially', 'rvn-compare-products-for-woocommerce' ); ?></label>
				</p>
			<?php endforeach; ?>

			<h3><?php esc_html_e( 'Fields', 'rvn-compare-products-for-woocommerce' ); ?></h3>
			<div style="overflow-x:auto"><table class="widefat striped"><thead><tr>
				<th scope="col"><?php esc_html_e( 'Order', 'rvn-compare-products-for-woocommerce' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Show', 'rvn-compare-products-for-woocommerce' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Name and technical key', 'rvn-compare-products-for-woocommerce' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Hint (optional)', 'rvn-compare-products-for-woocommerce' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Group', 'rvn-compare-products-for-woocommerce' ); ?></th>
			</tr></thead><tbody>
			<?php foreach ( $fields as $index => $field ) : ?>
				<?php
				$key       = (string) $field['key'];
				$overrides = $settings['table_fields'][ $key ] ?? array();
				?>
				<tr>
					<td><label class="screen-reader-text" for="rvn-order-<?php echo esc_attr( (string) $index ); ?>"><?php esc_html_e( 'Order', 'rvn-compare-products-for-woocommerce' ); ?></label><input type="number" id="rvn-order-<?php echo esc_attr( (string) $index ); ?>" name="rvn_fields[<?php echo esc_attr( $key ); ?>][order]" value="<?php echo esc_attr( (string) ( $index + 1 ) ); ?>" min="1" max="999" style="width:5em"></td>
					<td><label class="screen-reader-text" for="rvn-enabled-<?php echo esc_attr( (string) $index ); ?>"><?php echo esc_html( $field['label'] ); ?></label><input type="checkbox" id="rvn-enabled-<?php echo esc_attr( (string) $index ); ?>" name="rvn_fields[<?php echo esc_attr( $key ); ?>][enabled]" value="1" <?php checked( ! empty( $field['enabled'] ) ); ?>></td>
					<td><label class="screen-reader-text" for="rvn-label-<?php echo esc_attr( (string) $index ); ?>"><?php esc_html_e( 'Custom field name', 'rvn-compare-products-for-woocommerce' ); ?></label><input type="text" id="rvn-label-<?php echo esc_attr( (string) $index ); ?>" name="rvn_fields[<?php echo esc_attr( $key ); ?>][label]" value="<?php echo esc_attr( $overrides['label'] ?? '' ); ?>" placeholder="<?php echo esc_attr( $field['fallback'] ); ?>" maxlength="120"><br><code><?php echo esc_html( $key ); ?></code></td>
					<td><label class="screen-reader-text" for="rvn-hint-<?php echo esc_attr( (string) $index ); ?>"><?php esc_html_e( 'Field hint', 'rvn-compare-products-for-woocommerce' ); ?></label><textarea id="rvn-hint-<?php echo esc_attr( (string) $index ); ?>" name="rvn_fields[<?php echo esc_attr( $key ); ?>][hint]" rows="2" maxlength="800" placeholder="<?php echo esc_attr__( 'Hint text (optional)', 'rvn-compare-products-for-woocommerce' ); ?>"><?php echo esc_textarea( $field['hint'] ); ?></textarea></td>
					<td><label class="screen-reader-text" for="rvn-field-group-<?php echo esc_attr( (string) $index ); ?>"><?php esc_html_e( 'Group', 'rvn-compare-products-for-woocommerce' ); ?></label><select id="rvn-field-group-<?php echo esc_attr( (string) $index ); ?>" name="rvn_fields[<?php echo esc_attr( $key ); ?>][group]">
						<?php
						foreach ( $groups as $group ) :
							?>
							<option value="<?php echo esc_attr( $group['key'] ); ?>" <?php selected( $field['group'], $group['key'] ); ?>><?php echo esc_html( $group['label'] ); ?></option><?php endforeach; ?>
					</select></td>
				</tr>
			<?php endforeach; ?>
			</tbody></table></div>

			<h3><?php esc_html_e( 'Table behavior', 'rvn-compare-products-for-woocommerce' ); ?></h3>
			<p><label><input type="checkbox" name="new_attributes_enabled" value="1" <?php checked( ! empty( $settings['new_attributes_enabled'] ) ); ?>> <?php esc_html_e( 'Enable newly created WooCommerce attributes automatically', 'rvn-compare-products-for-woocommerce' ); ?></label></p>
			<p><label><input type="checkbox" name="hide_unassigned_attributes" value="1" <?php checked( ! empty( $settings['hide_unassigned_attributes'] ) ); ?>> <?php esc_html_e( 'Hide attributes not assigned to any product in the active category', 'rvn-compare-products-for-woocommerce' ); ?></label></p>
			<p><label><input type="checkbox" name="hide_empty_rows" value="1" <?php checked( ! empty( $settings['hide_empty_rows'] ) ); ?>> <?php esc_html_e( 'Hide rows whose values are all empty', 'rvn-compare-products-for-woocommerce' ); ?></label></p>
			<p><label><input type="checkbox" name="highlight_differences" value="1" <?php checked( ! empty( $settings['highlight_differences'] ) ); ?>> <?php esc_html_e( 'Highlight rows with different values', 'rvn-compare-products-for-woocommerce' ); ?></label></p>
			<p><label><input type="checkbox" name="show_difference_toggle" value="1" <?php checked( ! empty( $settings['show_difference_toggle'] ) ); ?>> <?php esc_html_e( 'Show the Only differences switch to customers', 'rvn-compare-products-for-woocommerce' ); ?></label></p>
			<p><label><input type="checkbox" name="term_tooltips" value="1" <?php checked( ! empty( $settings['term_tooltips'] ) ); ?>> <?php esc_html_e( 'Show descriptions of global attribute values in tooltips', 'rvn-compare-products-for-woocommerce' ); ?></label></p>
			<p><label><input type="checkbox" name="smart_normalize" value="1" <?php checked( ! empty( $settings['smart_normalize'] ) ); ?>> <?php esc_html_e( 'Normalize whitespace and case when finding differences', 'rvn-compare-products-for-woocommerce' ); ?></label></p>
			<p class="description"><?php esc_html_e( 'Normalization affects highlighting, not displayed values. Turn it off if it hides an important distinction between products.', 'rvn-compare-products-for-woocommerce' ); ?></p>
			<p><label for="rvn-difference-color"><?php esc_html_e( 'Difference highlight color', 'rvn-compare-products-for-woocommerce' ); ?></label> <input type="color" id="rvn-difference-color" name="difference_color" value="<?php echo esc_attr( $settings['difference_color'] ); ?>"></p>
			<?php submit_button( __( 'Save table settings', 'rvn-compare-products-for-woocommerce' ) ); ?>
		</form>

		<h3><?php esc_html_e( 'Add a product meta field', 'rvn-compare-products-for-woocommerce' ); ?></h3>
		<p class="description"><?php esc_html_e( 'Only add public scalar text, number or yes/no fields. Meta fields added here become visible to every customer. Private keys (starting with an underscore) and sensitive names are refused.', 'rvn-compare-products-for-woocommerce' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="rvn_compare_add_meta">
			<?php wp_nonce_field( 'rvn_compare_add_meta' ); ?>
			<p><label><?php esc_html_e( 'Meta key', 'rvn-compare-products-for-woocommerce' ); ?> <input type="text" name="meta_key" required pattern="[a-z][a-z0-9_-]*" maxlength="55" placeholder="battery_capacity"></label>
			<label><?php esc_html_e( 'Display name', 'rvn-compare-products-for-woocommerce' ); ?> <input type="text" name="meta_label" maxlength="120" placeholder="<?php echo esc_attr__( 'Battery capacity', 'rvn-compare-products-for-woocommerce' ); ?>"></label>
			<label><?php esc_html_e( 'Type', 'rvn-compare-products-for-woocommerce' ); ?> <select name="meta_type"><option value="text"><?php esc_html_e( 'Text', 'rvn-compare-products-for-woocommerce' ); ?></option><option value="number"><?php esc_html_e( 'Number', 'rvn-compare-products-for-woocommerce' ); ?></option><option value="boolean"><?php esc_html_e( 'Yes / No', 'rvn-compare-products-for-woocommerce' ); ?></option></select></label></p>
			<?php submit_button( __( 'Add field', 'rvn-compare-products-for-woocommerce' ), 'secondary' ); ?>
		</form>
		<?php
	}

	/**
	 * Проверяет права и nonce и сохраняет настройки таблицы.
	 *
	 * @return void
	 */
	public static function save(): void {
		self::authorize();
		check_admin_referer( 'rvn_compare_save_fields' );
		$fields = isset( $_POST['rvn_fields'] ) && is_array( $_POST['rvn_fields'] ) ? map_deep( wp_unslash( $_POST['rvn_fields'] ), 'sanitize_textarea_field' ) : array();
		$groups = isset( $_POST['rvn_groups'] ) && is_array( $_POST['rvn_groups'] ) ? map_deep( wp_unslash( $_POST['rvn_groups'] ), 'sanitize_text_field' ) : array();

		// Поле порядка влияет только на сортировку уже разрешённых технических ключей.
		uasort(
			$fields,
			static function ( $a, $b ) {
				return absint( $a['order'] ?? 0 ) <=> absint( $b['order'] ?? 0 );
			}
		);

		$flags = array(
			'new_attributes_enabled',
			'hide_unassigned_attributes',
			'hide_empty_rows',
			'highlight_differences',
			'show_difference_toggle',
			'term_tooltips',
			'smart_normalize',
		);
		$input = array(
			'table_fields' => $fields,
			'table_groups' => $groups,
		);

		foreach ( $flags as $key ) {
			$input[ $key ] = isset( $_POST[ $key ] ) && '1' === sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
		}

		$input['difference_color'] = isset( $_POST['difference_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['difference_color'] ) ) : '#ffcfcc';
		Settings::update( $input );
		self::redirect( 'saved' );
	}

	/**
	 * Проверяет права и nonce и добавляет скалярное мета-поле.
	 *
	 * @return void
	 */
	public static function add_meta(): void {
		self::authorize();
		check_admin_referer( 'rvn_compare_add_meta' );
		$key = isset( $_POST['meta_key'] ) ? sanitize_key( wp_unslash( $_POST['meta_key'] ) ) : '';

		if ( ! Table_Fields::valid_meta_key( $key ) ) {
			self::redirect( 'invalid' );
		}

		$settings = Settings::all();
		$meta     = (array) $settings['meta_fields'];
		$type     = isset( $_POST['meta_type'] ) ? sanitize_key( wp_unslash( $_POST['meta_type'] ) ) : 'text';
		$label    = isset( $_POST['meta_label'] ) ? sanitize_text_field( wp_unslash( $_POST['meta_label'] ) ) : '';
		$meta[]   = array(
			'key'   => $key,
			'label' => $label,
			'type'  => $type,
		);
		Settings::update( array( 'meta_fields' => $meta ) );
		self::redirect( 'added' );
	}

	/**
	 * Проверяет право доступа перед чтением защищённой формы.
	 *
	 * @return void
	 */
	private static function authorize(): void {
		if ( ! current_user_can( Admin::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to change comparison settings.', 'rvn-compare-products-for-woocommerce' ), '', array( 'response' => 403 ) );
		}
	}

	/**
	 * Возвращает пользователя в настройки таблицы с коротким кодом результата.
	 *
	 * @param string $code Код исхода операции.
	 * @return void
	 */
	private static function redirect( string $code ): void {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => 'rvn-compare',
					'tab'        => 'table',
					'rvn_result' => $code,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
