<?php
/**
 * Admin Page Class
 *
 * Handles the admin settings page for managing theme rules.
 *
 * @package Osom_Multi_Theme_Switcher
 * @since   1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OMTS_Admin_Page
 *
 * @since 1.0.0
 */
class OMTS_Admin_Page {

	/**
	 * Theme switcher instance.
	 *
	 * @var OMTS_Theme_Switcher
	 */
	private $theme_switcher;

	/**
	 * Page hook suffix.
	 *
	 * @var string
	 */
	private $hook_suffix = '';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param OMTS_Theme_Switcher $theme_switcher Theme switcher instance.
	 */
	public function __construct( $theme_switcher ) {
		$this->theme_switcher = $theme_switcher;

		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	/**
	 * Add admin menu.
	 *
	 * @since 1.0.0
	 */
	public function add_admin_menu() {
		$this->hook_suffix = add_theme_page(
			__( 'Osom Multi Theme Switcher', 'osom-multi-theme-switcher' ),
			__( 'Theme Switcher', 'osom-multi-theme-switcher' ),
			'manage_options',
			'osom-multi-theme-switcher',
			array( $this, 'render_admin_page' )
		);

		add_action( 'load-' . $this->hook_suffix, array( $this, 'add_help_tabs' ) );
	}

	/**
	 * Add contextual help, so the rules are explained where they are used.
	 *
	 * @since 1.3.0
	 */
	public function add_help_tabs() {
		$screen = get_current_screen();

		if ( ! $screen ) {
			return;
		}

		$screen->add_help_tab(
			array(
				'id'      => 'omts-help-basics',
				'title'   => __( 'How it works', 'osom-multi-theme-switcher' ),
				'content' => '<p>' . esc_html__( 'Your site runs on one main theme. Every rule below is an exception: it points a page, a post type, a term or an address at a different theme.', 'osom-multi-theme-switcher' ) . '</p>'
					. '<p>' . esc_html__( 'Rules are checked from top to bottom and the first one that matches wins. Drag rules to change that order.', 'osom-multi-theme-switcher' ) . '</p>',
			)
		);

		$screen->add_help_tab(
			array(
				'id'      => 'omts-help-dashboard',
				'title'   => __( 'Dashboard theme', 'osom-multi-theme-switcher' ),
				'content' => '<p>' . esc_html__( 'The switcher in the top admin bar changes the theme your dashboard runs in — for you only. Visitors are never affected by it; they always get the main theme or the theme a rule points to.', 'osom-multi-theme-switcher' ) . '</p>'
					. '<p>' . esc_html__( 'Use it to reach the settings pages of a theme that is not active site-wide.', 'osom-multi-theme-switcher' ) . '</p>',
			)
		);

		$screen->add_help_tab(
			array(
				'id'      => 'omts-help-urls',
				'title'   => __( 'Address rules', 'osom-multi-theme-switcher' ),
				'content' => '<p>' . esc_html__( 'An address rule can match the exact address only, or the address together with everything below it. "With sub-pages" means /promo also covers /promo/summer.', 'osom-multi-theme-switcher' ) . '</p>'
					. '<p>' . esc_html__( 'Not sure what a rule will do? Paste an address into "Test an address" and the answer comes back with the rule that matched.', 'osom-multi-theme-switcher' ) . '</p>',
			)
		);
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_scripts( $hook ) {
		if ( 'appearance_page_osom-multi-theme-switcher' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'omts-admin-css',
			plugin_dir_url( dirname( __FILE__ ) ) . 'assets/admin-style.css',
			array(),
			OMTS_VERSION
		);

		wp_enqueue_script(
			'omts-admin-js',
			plugin_dir_url( dirname( __FILE__ ) ) . 'assets/admin-script.js',
			array( 'jquery', 'jquery-ui-sortable' ),
			OMTS_VERSION,
			true
		);

		$themes = array();

		foreach ( wp_get_themes() as $slug => $theme ) {
			$themes[] = array(
				'value' => $slug,
				'label' => $theme->get( 'Name' ),
			);
		}

		wp_localize_script(
			'omts-admin-js',
			'omtsAjax',
			array(
				'ajaxurl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'omts_nonce' ),
				'selectLabel' => __( '-- Select --', 'osom-multi-theme-switcher' ),
				'themes'      => $themes,
				'labels'      => $this->get_field_labels(),
				'i18n'        => array(
					'errorObjects'   => __( 'Could not load the list. Please try again.', 'osom-multi-theme-switcher' ),
					'errorItems'     => __( 'Could not load the items. Please try again.', 'osom-multi-theme-switcher' ),
					'errorSave'      => __( 'The rule could not be saved. Please try again.', 'osom-multi-theme-switcher' ),
					'errorDelete'    => __( 'The rule could not be deleted. Please try again.', 'osom-multi-theme-switcher' ),
					'errorGeneric'   => __( 'Something went wrong. Please try again.', 'osom-multi-theme-switcher' ),
					'needType'       => __( 'Choose what the rule applies to.', 'osom-multi-theme-switcher' ),
					'needTheme'      => __( 'Choose the theme this rule should use.', 'osom-multi-theme-switcher' ),
					'needUrl'        => __( 'Enter an address or slug.', 'osom-multi-theme-switcher' ),
					'needObject'     => __( 'Choose a content type.', 'osom-multi-theme-switcher' ),
					'needItem'       => __( 'Choose an item.', 'osom-multi-theme-switcher' ),
					'ruleAdded'      => __( 'Rule added.', 'osom-multi-theme-switcher' ),
					'ruleDeleted'    => __( 'Rule deleted.', 'osom-multi-theme-switcher' ),
					'ruleUpdated'    => __( 'Rule updated.', 'osom-multi-theme-switcher' ),
					'orderSaved'     => __( 'Rule order saved.', 'osom-multi-theme-switcher' ),
					'undo'           => __( 'Undo', 'osom-multi-theme-switcher' ),
					'save'           => __( 'Save', 'osom-multi-theme-switcher' ),
					'cancel'         => __( 'Cancel', 'osom-multi-theme-switcher' ),
					'noRules'        => __( 'No rules yet. The whole site uses the main theme.', 'osom-multi-theme-switcher' ),
					'testEmpty'      => __( 'Enter an address to test.', 'osom-multi-theme-switcher' ),
					'testDefault'    => __( 'No rule matches this address — it uses the main theme.', 'osom-multi-theme-switcher' ),
					/* translators: 1: Rule position, 2: Rule description */
					'matchedRule'    => __( 'Matched rule %1$d: %2$s', 'osom-multi-theme-switcher' ),
					'matchExact'     => __( 'this address only', 'osom-multi-theme-switcher' ),
					'matchPrefix'    => __( 'with sub-pages', 'osom-multi-theme-switcher' ),
					'enabled'        => __( 'Active', 'osom-multi-theme-switcher' ),
					'disabled'       => __( 'Paused', 'osom-multi-theme-switcher' ),
					'prefixDeleted'  => __( 'REST prefix removed.', 'osom-multi-theme-switcher' ),
					'prefixSaved'    => __( 'REST prefix saved.', 'osom-multi-theme-switcher' ),
					'cleanupDone'    => __( 'Rules that could not work have been removed.', 'osom-multi-theme-switcher' ),
				),
			)
		);
	}

	/**
	 * Labels for the cascading selectors, per rule type.
	 *
	 * The generic "Rule Object" / "Rule Item" wording told nobody anything.
	 *
	 * @since 1.3.0
	 *
	 * @return array Map of rule type => labels.
	 */
	private function get_field_labels() {
		return array(
			'page'             => array(
				'item' => __( 'Which page', 'osom-multi-theme-switcher' ),
				'help' => __( 'Drafts, scheduled and private pages can be selected too.', 'osom-multi-theme-switcher' ),
			),
			'post'             => array(
				'item' => __( 'Which post', 'osom-multi-theme-switcher' ),
				'help' => __( 'Drafts, scheduled and private posts can be selected too.', 'osom-multi-theme-switcher' ),
			),
			'custom_post_type' => array(
				'object' => __( 'Content type', 'osom-multi-theme-switcher' ),
				'item'   => __( 'Which items', 'osom-multi-theme-switcher' ),
				'help'   => __( 'Pick "All" to cover every item of this type, including its archive.', 'osom-multi-theme-switcher' ),
			),
			'taxonomy'         => array(
				'object' => __( 'Taxonomy', 'osom-multi-theme-switcher' ),
				'item'   => __( 'Which term', 'osom-multi-theme-switcher' ),
				'help'   => __( 'Covers the term archive and every item assigned to that term.', 'osom-multi-theme-switcher' ),
			),
			'url'              => array(
				'item' => __( 'Address or slug', 'osom-multi-theme-switcher' ),
			),
		);
	}

	/**
	 * Render admin page.
	 *
	 * @since 1.0.0
	 */
	public function render_admin_page() {
		$rules         = $this->theme_switcher->get_rules();
		$rest_prefixes = $this->theme_switcher->get_theme_rest_prefixes();
		$themes        = wp_get_themes();
		$main_theme    = $this->theme_switcher->get_theme_name( $this->theme_switcher->get_site_default_theme() );
		?>
		<div class="wrap omts-admin-wrap">
			<h1><?php esc_html_e( 'Osom Multi Theme Switcher', 'osom-multi-theme-switcher' ); ?></h1>

			<p class="omts-summary">
				<?php
				printf(
					/* translators: 1: Main theme name, 2: Number of rules */
					esc_html(
						_n(
							'Main theme: %1$s. %2$d rule points selected content at a different theme.',
							'Main theme: %1$s. %2$d rules point selected content at a different theme.',
							count( $rules ),
							'osom-multi-theme-switcher'
						)
					),
					'<strong>' . esc_html( $main_theme ) . '</strong>', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					count( $rules )
				);
				?>
			</p>

			<details class="omts-howto" id="omts-howto">
				<summary><?php esc_html_e( 'How this plugin works', 'osom-multi-theme-switcher' ); ?></summary>
				<ol>
					<li><?php esc_html_e( 'Your whole site runs on the main theme. Each rule below is an exception for a page, a content type, a term or an address.', 'osom-multi-theme-switcher' ); ?></li>
					<li><?php esc_html_e( 'Rules are checked from top to bottom and the first match wins. Drag a rule to move it up or down.', 'osom-multi-theme-switcher' ); ?></li>
					<li><?php esc_html_e( 'The theme switcher in the admin bar changes your dashboard view only — it never changes what visitors see.', 'osom-multi-theme-switcher' ); ?></li>
					<li><?php esc_html_e( 'When you open a page in the editor, the sidebar panel "Theme for this item" tells you which theme it will render in.', 'osom-multi-theme-switcher' ); ?></li>
				</ol>
			</details>

			<div id="omts-notices" class="omts-notices" aria-live="polite"></div>

			<div class="omts-container">
				<div class="omts-add-rule-section">
					<h2><?php esc_html_e( 'Add a rule', 'osom-multi-theme-switcher' ); ?></h2>
					<form id="omts-add-rule-form">
						<table class="form-table">
							<tr>
								<th scope="row">
									<label for="omts-rule-type"><?php esc_html_e( 'Applies to', 'osom-multi-theme-switcher' ); ?></label>
								</th>
								<td>
									<select id="omts-rule-type" name="rule_type">
										<option value=""><?php esc_html_e( '-- Choose --', 'osom-multi-theme-switcher' ); ?></option>
										<option value="page"><?php esc_html_e( 'A page', 'osom-multi-theme-switcher' ); ?></option>
										<option value="post"><?php esc_html_e( 'A post', 'osom-multi-theme-switcher' ); ?></option>
										<option value="custom_post_type"><?php esc_html_e( 'A custom post type', 'osom-multi-theme-switcher' ); ?></option>
										<option value="taxonomy"><?php esc_html_e( 'A category, tag or term', 'osom-multi-theme-switcher' ); ?></option>
										<option value="url"><?php esc_html_e( 'An address (URL or slug)', 'osom-multi-theme-switcher' ); ?></option>
									</select>
								</td>
							</tr>
							<tr id="omts-rule-object-row" class="omts-rule-row" style="display:none;">
								<th scope="row">
									<label for="omts-rule-object" id="omts-rule-object-label"><?php esc_html_e( 'Content type', 'osom-multi-theme-switcher' ); ?></label>
								</th>
								<td>
									<select id="omts-rule-object" name="object_type">
										<option value=""><?php esc_html_e( '-- Select --', 'osom-multi-theme-switcher' ); ?></option>
									</select>
									<span class="spinner" id="omts-object-spinner"></span>
								</td>
							</tr>
							<tr id="omts-rule-item-row" class="omts-rule-row" style="display:none;">
								<th scope="row">
									<label for="omts-rule-item" id="omts-rule-item-label"><?php esc_html_e( 'Which item', 'osom-multi-theme-switcher' ); ?></label>
								</th>
								<td>
									<select id="omts-rule-item" name="item_id">
										<option value=""><?php esc_html_e( '-- Select --', 'osom-multi-theme-switcher' ); ?></option>
									</select>
									<span class="spinner" id="omts-item-spinner"></span>
									<p class="description" id="omts-item-help"></p>
								</td>
							</tr>
							<tr id="omts-url-row" class="omts-rule-row" style="display:none;">
								<th scope="row">
									<label for="omts-url-input"><?php esc_html_e( 'Address or slug', 'osom-multi-theme-switcher' ); ?></label>
								</th>
								<td>
									<input type="text" id="omts-url-input" name="custom_url" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. /promo or promo', 'osom-multi-theme-switcher' ); ?>">
									<fieldset class="omts-match-mode">
										<label>
											<input type="radio" name="url_match" value="exact" checked>
											<?php esc_html_e( 'This address only', 'osom-multi-theme-switcher' ); ?>
										</label>
										<label>
											<input type="radio" name="url_match" value="prefix">
											<?php esc_html_e( 'This address and everything below it', 'osom-multi-theme-switcher' ); ?>
										</label>
									</fieldset>
									<p class="description">
										<?php esc_html_e( '"Everything below it" means /promo also covers /promo/summer.', 'osom-multi-theme-switcher' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="omts-theme-select"><?php esc_html_e( 'Theme to use', 'osom-multi-theme-switcher' ); ?></label>
								</th>
								<td>
									<select id="omts-theme-select" name="theme">
										<option value=""><?php esc_html_e( '-- Choose a theme --', 'osom-multi-theme-switcher' ); ?></option>
										<?php foreach ( $themes as $theme_slug => $theme_obj ) : ?>
											<option value="<?php echo esc_attr( $theme_slug ); ?>">
												<?php echo esc_html( $theme_obj->get( 'Name' ) ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
						</table>
						<p class="submit">
							<button type="submit" class="button button-primary">
								<?php esc_html_e( 'Add rule', 'osom-multi-theme-switcher' ); ?>
							</button>
						</p>
					</form>
				</div>

				<div class="omts-rules-list-section">
					<h2><?php esc_html_e( 'Rules', 'osom-multi-theme-switcher' ); ?></h2>
					<p class="description">
						<?php esc_html_e( 'Checked from top to bottom — the first rule that matches wins. Drag a row to change the order.', 'osom-multi-theme-switcher' ); ?>
					</p>
					<table class="wp-list-table widefat fixed striped omts-rules-table">
						<thead>
							<tr>
								<th class="omts-col-order"><?php esc_html_e( 'Order', 'osom-multi-theme-switcher' ); ?></th>
								<th><?php esc_html_e( 'Applies to', 'osom-multi-theme-switcher' ); ?></th>
								<th><?php esc_html_e( 'Target', 'osom-multi-theme-switcher' ); ?></th>
								<th><?php esc_html_e( 'Theme', 'osom-multi-theme-switcher' ); ?></th>
								<th class="omts-col-status"><?php esc_html_e( 'Status', 'osom-multi-theme-switcher' ); ?></th>
								<th class="omts-col-actions"><?php esc_html_e( 'Actions', 'osom-multi-theme-switcher' ); ?></th>
							</tr>
						</thead>
						<tbody id="omts-rules-tbody">
							<?php if ( empty( $rules ) ) : ?>
								<tr class="omts-no-rules">
									<td colspan="6">
										<?php esc_html_e( 'No rules yet. The whole site uses the main theme.', 'osom-multi-theme-switcher' ); ?>
									</td>
								</tr>
							<?php else : ?>
								<?php foreach ( $rules as $index => $rule ) : ?>
									<?php $this->render_rule_row( $index, $rule ); ?>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>

				<div class="omts-test-section">
					<h2><?php esc_html_e( 'Test an address', 'osom-multi-theme-switcher' ); ?></h2>
					<p class="description">
						<?php esc_html_e( 'Paste any address from this site to see which theme it renders in and which rule decided that.', 'osom-multi-theme-switcher' ); ?>
					</p>
					<form id="omts-test-form">
						<input type="text" id="omts-test-url" class="regular-text" placeholder="<?php echo esc_attr( home_url( '/about-us/' ) ); ?>">
						<button type="submit" class="button"><?php esc_html_e( 'Check', 'osom-multi-theme-switcher' ); ?></button>
						<span class="spinner" id="omts-test-spinner"></span>
					</form>
					<div id="omts-test-result" class="omts-test-result"></div>
				</div>

				<details class="omts-advanced">
					<summary><?php esc_html_e( 'Advanced: REST API prefixes', 'osom-multi-theme-switcher' ); ?></summary>

					<div class="omts-rest-prefix-section">
						<p class="description">
							<?php esc_html_e( 'Only needed when two themes register REST routes that clash. A theme with its own prefix answers at /your-prefix/ instead of /wp-json/. Saving rebuilds the permalink rules.', 'osom-multi-theme-switcher' ); ?>
						</p>

						<div class="omts-add-prefix-section">
							<form id="omts-add-prefix-form">
								<table class="form-table">
									<tr>
										<th scope="row">
											<label for="omts-prefix-theme-select"><?php esc_html_e( 'Theme', 'osom-multi-theme-switcher' ); ?></label>
										</th>
										<td>
											<select id="omts-prefix-theme-select" name="prefix_theme">
												<option value=""><?php esc_html_e( '-- Choose a theme --', 'osom-multi-theme-switcher' ); ?></option>
												<?php foreach ( $themes as $theme_slug => $theme_obj ) : ?>
													<option value="<?php echo esc_attr( $theme_slug ); ?>">
														<?php echo esc_html( $theme_obj->get( 'Name' ) ); ?>
													</option>
												<?php endforeach; ?>
											</select>
										</td>
									</tr>
									<tr>
										<th scope="row">
											<label for="omts-rest-prefix-input"><?php esc_html_e( 'REST API prefix', 'osom-multi-theme-switcher' ); ?></label>
										</th>
										<td>
											<input type="text" id="omts-rest-prefix-input" name="rest_prefix" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. wp-json-2', 'osom-multi-theme-switcher' ); ?>">
											<p class="description">
												<?php
												printf(
													/* translators: 1: Default prefix, 2: Custom prefix, 3: Resulting URL */
													esc_html__( 'No slashes. Leave empty for the default (%1$s). With %2$s, endpoints answer at %3$s', 'osom-multi-theme-switcher' ),
													'<code>wp-json</code>', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
													'<code>wp-json-2</code>', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
													'<code>/wp-json-2/namespace/endpoint</code>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
												);
												?>
											</p>
										</td>
									</tr>
								</table>
								<p class="submit">
									<button type="submit" class="button">
										<?php esc_html_e( 'Save prefix', 'osom-multi-theme-switcher' ); ?>
									</button>
								</p>
							</form>
						</div>

						<div class="omts-prefixes-list">
							<h3><?php esc_html_e( 'Active prefixes', 'osom-multi-theme-switcher' ); ?></h3>
							<table class="wp-list-table widefat fixed striped">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Theme', 'osom-multi-theme-switcher' ); ?></th>
										<th><?php esc_html_e( 'Prefix', 'osom-multi-theme-switcher' ); ?></th>
										<th><?php esc_html_e( 'Example', 'osom-multi-theme-switcher' ); ?></th>
										<th><?php esc_html_e( 'Actions', 'osom-multi-theme-switcher' ); ?></th>
									</tr>
								</thead>
								<tbody id="omts-prefixes-tbody">
									<?php if ( empty( $rest_prefixes ) ) : ?>
										<tr class="omts-no-prefixes">
											<td colspan="4">
												<?php esc_html_e( 'No custom prefixes. Every theme answers at /wp-json/.', 'osom-multi-theme-switcher' ); ?>
											</td>
										</tr>
									<?php else : ?>
										<?php foreach ( $rest_prefixes as $index => $prefix_map ) : ?>
											<tr data-index="<?php echo esc_attr( $index ); ?>">
												<td><?php echo esc_html( $this->theme_switcher->get_theme_name( $prefix_map['theme'] ) ); ?></td>
												<td><code><?php echo esc_html( ! empty( $prefix_map['prefix'] ) ? $prefix_map['prefix'] : 'wp-json' ); ?></code></td>
												<td>
													<code><?php echo esc_html( home_url( '/' . ( ! empty( $prefix_map['prefix'] ) ? $prefix_map['prefix'] : 'wp-json' ) . '/namespace/endpoint' ) ); ?></code>
												</td>
												<td>
													<button class="button omts-delete-prefix" data-index="<?php echo esc_attr( $index ); ?>">
														<?php esc_html_e( 'Delete', 'osom-multi-theme-switcher' ); ?>
													</button>
												</td>
											</tr>
										<?php endforeach; ?>
									<?php endif; ?>
								</tbody>
							</table>
						</div>
					</div>
				</details>
			</div>
		</div>
		<?php
	}

	/**
	 * Render one row of the rules table.
	 *
	 * @since 1.3.0
	 *
	 * @param int   $index Rule position.
	 * @param array $rule  Rule data.
	 */
	public function render_rule_row( $index, $rule ) {
		$enabled = $this->theme_switcher->is_rule_enabled( $rule );
		$is_url  = 'url' === $rule['type'];
		$match   = isset( $rule['match'] ) ? $rule['match'] : 'prefix';
		?>
		<tr data-index="<?php echo esc_attr( $index ); ?>"
			data-theme="<?php echo esc_attr( $rule['theme'] ); ?>"
			data-type="<?php echo esc_attr( $rule['type'] ); ?>"
			data-value="<?php echo esc_attr( $rule['value'] ); ?>"
			data-match="<?php echo esc_attr( $match ); ?>"
			data-enabled="<?php echo $enabled ? '1' : '0'; ?>"
			class="<?php echo $enabled ? '' : 'omts-rule-disabled'; ?>">
			<td class="omts-col-order">
				<span class="omts-drag-handle dashicons dashicons-menu" title="<?php esc_attr_e( 'Drag to reorder', 'osom-multi-theme-switcher' ); ?>"></span>
				<span class="omts-position"><?php echo esc_html( $index + 1 ); ?></span>
			</td>
			<td class="omts-cell-type"><?php echo esc_html( OMTS_Theme_Switcher::get_rule_type_display( $rule['type'] ) ); ?></td>
			<td class="omts-cell-target">
				<?php echo esc_html( OMTS_Theme_Switcher::get_rule_target_display( $rule ) ); ?>
				<?php if ( $is_url ) : ?>
					<span class="omts-match-badge">
						<?php
						echo esc_html(
							'exact' === $match
								? __( 'this address only', 'osom-multi-theme-switcher' )
								: __( 'with sub-pages', 'osom-multi-theme-switcher' )
						);
						?>
					</span>
				<?php endif; ?>
			</td>
			<td class="omts-cell-theme"><?php echo esc_html( $this->theme_switcher->get_theme_name( $rule['theme'] ) ); ?></td>
			<td class="omts-cell-status">
				<label class="omts-toggle">
					<input type="checkbox" class="omts-toggle-rule" data-index="<?php echo esc_attr( $index ); ?>" <?php checked( $enabled ); ?>>
					<span><?php echo esc_html( $enabled ? __( 'Active', 'osom-multi-theme-switcher' ) : __( 'Paused', 'osom-multi-theme-switcher' ) ); ?></span>
				</label>
			</td>
			<td class="omts-cell-actions">
				<button class="button button-small omts-edit-rule" data-index="<?php echo esc_attr( $index ); ?>">
					<?php esc_html_e( 'Edit', 'osom-multi-theme-switcher' ); ?>
				</button>
				<button class="button button-small omts-delete-rule" data-index="<?php echo esc_attr( $index ); ?>">
					<?php esc_html_e( 'Delete', 'osom-multi-theme-switcher' ); ?>
				</button>
			</td>
		</tr>
		<?php
	}

	/**
	 * Get human-readable display name for rule type.
	 *
	 * @since 1.2.0
	 *
	 * @param string $type Rule type.
	 * @return string Display name.
	 */
	private function get_rule_type_display( $type ) {
		return OMTS_Theme_Switcher::get_rule_type_display( $type );
	}

	/**
	 * Get display name for rule target.
	 *
	 * @since 1.0.0
	 *
	 * @param array $rule Rule array.
	 * @return string Display name.
	 */
	private function get_rule_target_display( $rule ) {
		return OMTS_Theme_Switcher::get_rule_target_display( $rule );
	}
}
