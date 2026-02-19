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
		add_theme_page(
			__( 'Osom Multi Theme Switcher', 'osom-multi-theme-switcher' ),
			__( 'Theme Switcher', 'osom-multi-theme-switcher' ),
			'manage_options',
			'osom-multi-theme-switcher',
			array( $this, 'render_admin_page' )
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
			'1.2.2'
		);

		wp_enqueue_script(
			'omts-admin-js',
			plugin_dir_url( dirname( __FILE__ ) ) . 'assets/admin-script.js',
			array( 'jquery' ),
			'1.2.2',
			true
		);

		wp_localize_script(
			'omts-admin-js',
			'omtsAjax',
			array(
				'ajaxurl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'omts_nonce' ),
				'selectLabel' => __( '-- Select --', 'osom-multi-theme-switcher' ),
			)
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
		?>
		<div class="wrap omts-admin-wrap">
			<h1><?php esc_html_e( 'Osom Multi Theme Switcher', 'osom-multi-theme-switcher' ); ?></h1>
			<p>
				<?php
				printf(
					/* translators: %s: Current theme name */
					esc_html__( 'Configure which pages, posts, or URLs use alternative themes. Your main theme is: %s', 'osom-multi-theme-switcher' ),
					'<strong>' . esc_html( wp_get_theme()->get( 'Name' ) ) . '</strong>'
				);
				?>
			</p>

			<div class="omts-container">
				<div class="omts-add-rule-section">
					<h2><?php esc_html_e( 'Add New Theme Rule', 'osom-multi-theme-switcher' ); ?></h2>
					<form id="omts-add-rule-form">
						<table class="form-table">
							<tr>
								<th scope="row">
									<label for="omts-rule-type"><?php esc_html_e( 'Rule Type', 'osom-multi-theme-switcher' ); ?></label>
								</th>
								<td>
									<select id="omts-rule-type" name="rule_type">
										<option value=""><?php esc_html_e( '-- Select Rule Type --', 'osom-multi-theme-switcher' ); ?></option>
										<option value="page"><?php esc_html_e( 'Page', 'osom-multi-theme-switcher' ); ?></option>
										<option value="post"><?php esc_html_e( 'Post', 'osom-multi-theme-switcher' ); ?></option>
										<option value="custom_post_type"><?php esc_html_e( 'Custom Post Type', 'osom-multi-theme-switcher' ); ?></option>
										<option value="taxonomy"><?php esc_html_e( 'Taxonomy', 'osom-multi-theme-switcher' ); ?></option>
										<option value="url"><?php esc_html_e( 'Custom URL/Slug', 'osom-multi-theme-switcher' ); ?></option>
									</select>
								</td>
							</tr>
							<tr id="omts-rule-object-row" class="omts-rule-row" style="display:none;">
								<th scope="row">
									<label for="omts-rule-object"><?php esc_html_e( 'Rule Object', 'osom-multi-theme-switcher' ); ?></label>
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
									<label for="omts-rule-item"><?php esc_html_e( 'Rule Item', 'osom-multi-theme-switcher' ); ?></label>
								</th>
								<td>
									<select id="omts-rule-item" name="item_id">
										<option value=""><?php esc_html_e( '-- Select --', 'osom-multi-theme-switcher' ); ?></option>
									</select>
									<span class="spinner" id="omts-item-spinner"></span>
								</td>
							</tr>
							<tr id="omts-url-row" class="omts-rule-row" style="display:none;">
								<th scope="row">
									<label for="omts-url-input"><?php esc_html_e( 'Custom URL/Slug', 'osom-multi-theme-switcher' ); ?></label>
								</th>
								<td>
									<input type="text" id="omts-url-input" name="custom_url" class="regular-text" placeholder="<?php esc_attr_e( 'e.g., /about-us or about-us', 'osom-multi-theme-switcher' ); ?>">
									<p class="description">
										<?php esc_html_e( 'Enter a URL path or slug (with or without leading slash)', 'osom-multi-theme-switcher' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="omts-theme-select"><?php esc_html_e( 'Theme', 'osom-multi-theme-switcher' ); ?></label>
								</th>
								<td>
									<select id="omts-theme-select" name="theme">
										<option value=""><?php esc_html_e( '-- Select Theme --', 'osom-multi-theme-switcher' ); ?></option>
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
								<?php esc_html_e( 'Add Rule', 'osom-multi-theme-switcher' ); ?>
							</button>
						</p>
					</form>
				</div>

				<div class="omts-rules-list-section">
					<h2><?php esc_html_e( 'Active Theme Rules', 'osom-multi-theme-switcher' ); ?></h2>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Type', 'osom-multi-theme-switcher' ); ?></th>
								<th><?php esc_html_e( 'Target', 'osom-multi-theme-switcher' ); ?></th>
								<th><?php esc_html_e( 'Theme', 'osom-multi-theme-switcher' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'osom-multi-theme-switcher' ); ?></th>
							</tr>
						</thead>
						<tbody id="omts-rules-tbody">
							<?php if ( empty( $rules ) ) : ?>
								<tr class="omts-no-rules">
									<td colspan="4">
										<?php esc_html_e( 'No rules configured yet. Add your first rule above.', 'osom-multi-theme-switcher' ); ?>
									</td>
								</tr>
							<?php else : ?>
								<?php foreach ( $rules as $index => $rule ) : ?>
									<tr data-index="<?php echo esc_attr( $index ); ?>">
										<td><?php echo esc_html( $this->get_rule_type_display( $rule['type'] ) ); ?></td>
										<td><?php echo esc_html( $this->get_rule_target_display( $rule ) ); ?></td>
										<td><?php echo esc_html( $this->theme_switcher->get_theme_name( $rule['theme'] ) ); ?></td>
										<td>
											<button class="button omts-delete-rule" data-index="<?php echo esc_attr( $index ); ?>">
												<?php esc_html_e( 'Delete', 'osom-multi-theme-switcher' ); ?>
											</button>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>

				<!-- Theme REST Prefix Configuration Section -->
				<div class="omts-rest-prefix-section">
					<h2><?php esc_html_e( 'Theme REST API Prefixes', 'osom-multi-theme-switcher' ); ?></h2>
					<p class="description">
						<?php esc_html_e( 'Assign custom REST API URL prefixes to themes. This allows different themes to have their own REST endpoints (e.g., /wp-json/ vs /wp-json-2/). Useful when themes have conflicting REST API routes.', 'osom-multi-theme-switcher' ); ?>
					</p>

					<div class="omts-add-prefix-section">
						<h3><?php esc_html_e( 'Configure Theme REST Prefix', 'osom-multi-theme-switcher' ); ?></h3>
						<form id="omts-add-prefix-form">
							<table class="form-table">
								<tr>
									<th scope="row">
										<label for="omts-prefix-theme-select"><?php esc_html_e( 'Theme', 'osom-multi-theme-switcher' ); ?></label>
									</th>
									<td>
										<select id="omts-prefix-theme-select" name="prefix_theme">
											<option value=""><?php esc_html_e( '-- Select Theme --', 'osom-multi-theme-switcher' ); ?></option>
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
										<label for="omts-rest-prefix-input"><?php esc_html_e( 'REST API Prefix', 'osom-multi-theme-switcher' ); ?></label>
									</th>
									<td>
										<input type="text" id="omts-rest-prefix-input" name="rest_prefix" class="regular-text" placeholder="<?php esc_attr_e( 'e.g., wp-json-2', 'osom-multi-theme-switcher' ); ?>">
										<p class="description">
											<?php
											printf(
												/* translators: 1: Default prefix example, 2: Custom prefix example */
												esc_html__( 'Enter a custom prefix without slashes. Leave empty to use default (%1$s). Example: %2$s becomes %3$s', 'osom-multi-theme-switcher' ),
												'<code>wp-json</code>',
												'<code>wp-json-2</code>',
												'<code>/wp-json-2/namespace/endpoint</code>'
											);
											?>
										</p>
									</td>
								</tr>
							</table>
							<p class="submit">
								<button type="submit" class="button button-primary">
									<?php esc_html_e( 'Set REST Prefix', 'osom-multi-theme-switcher' ); ?>
								</button>
							</p>
						</form>
					</div>

					<div class="omts-prefixes-list">
						<h3><?php esc_html_e( 'Active REST Prefix Mappings', 'osom-multi-theme-switcher' ); ?></h3>
						<table class="wp-list-table widefat fixed striped">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Theme', 'osom-multi-theme-switcher' ); ?></th>
									<th><?php esc_html_e( 'REST Prefix', 'osom-multi-theme-switcher' ); ?></th>
									<th><?php esc_html_e( 'Example URL', 'osom-multi-theme-switcher' ); ?></th>
									<th><?php esc_html_e( 'Actions', 'osom-multi-theme-switcher' ); ?></th>
								</tr>
							</thead>
							<tbody id="omts-prefixes-tbody">
								<?php if ( empty( $rest_prefixes ) ) : ?>
									<tr class="omts-no-prefixes">
										<td colspan="4">
											<?php esc_html_e( 'No custom REST prefixes configured. All themes use the default wp-json prefix.', 'osom-multi-theme-switcher' ); ?>
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
			</div>
		</div>
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
		switch ( $rule['type'] ) {
			case 'page':
				$page = get_post( $rule['value'] );
				return $page ? $page->post_title : sprintf(
					/* translators: %d: Page ID */
					__( 'Unknown Page (ID: %d)', 'osom-multi-theme-switcher' ),
					$rule['value']
				);

			case 'post':
				$post = get_post( $rule['value'] );
				return $post ? $post->post_title : sprintf(
					/* translators: %d: Post ID */
					__( 'Unknown Post (ID: %d)', 'osom-multi-theme-switcher' ),
					$rule['value']
				);

			case 'post_type':
				$post_type_obj = get_post_type_object( $rule['value'] );
				return $post_type_obj
					? sprintf(
						/* translators: %s: Post type label */
						__( 'All %s', 'osom-multi-theme-switcher' ),
						$post_type_obj->label
					)
					: $rule['value'];

			case 'url':
				return $rule['value'];

			case 'category':
				$category = get_category( $rule['value'] );
				return $category ? $category->name : __( 'Unknown Category', 'osom-multi-theme-switcher' );

			case 'tag':
				$tag = get_tag( $rule['value'] );
				return $tag ? $tag->name : __( 'Unknown Tag', 'osom-multi-theme-switcher' );

			case 'taxonomy':
				$taxonomy = isset( $rule['taxonomy'] ) ? $rule['taxonomy'] : '';
				$term     = get_term( $rule['value'], $taxonomy );
				if ( $term && ! is_wp_error( $term ) ) {
					$tax_obj   = get_taxonomy( $taxonomy );
					$tax_label = $tax_obj ? $tax_obj->label : $taxonomy;
					return $term->name . ' (' . $tax_label . ')';
				}
				return __( 'Unknown Term', 'osom-multi-theme-switcher' );

			case 'cpt_item':
				$post = get_post( $rule['value'] );
				return $post ? $post->post_title : sprintf(
					/* translators: %d: Post ID */
					__( 'Unknown Item (ID: %d)', 'osom-multi-theme-switcher' ),
					$rule['value']
				);

			case 'draft_page':
			case 'draft_post':
			case 'draft_cpt_item':
				$post = get_post( $rule['value'] );
				return $post ? '(Draft) ' . $post->post_title : sprintf(
					/* translators: %d: Post ID */
					__( 'Unknown Draft (ID: %d)', 'osom-multi-theme-switcher' ),
					$rule['value']
				);

			case 'pending_page':
			case 'pending_post':
			case 'pending_cpt_item':
				$post = get_post( $rule['value'] );
				return $post ? '(Pending) ' . $post->post_title : sprintf(
					/* translators: %d: Post ID */
					__( 'Unknown Pending (ID: %d)', 'osom-multi-theme-switcher' ),
					$rule['value']
				);

			case 'private_page':
			case 'private_post':
			case 'private_cpt_item':
				$post = get_post( $rule['value'] );
				return $post ? '(Private) ' . $post->post_title : sprintf(
					/* translators: %d: Post ID */
					__( 'Unknown Private (ID: %d)', 'osom-multi-theme-switcher' ),
					$rule['value']
				);

			case 'future_page':
			case 'future_post':
			case 'future_cpt_item':
				$post = get_post( $rule['value'] );
				return $post ? '(Scheduled) ' . $post->post_title : sprintf(
					/* translators: %d: Post ID */
					__( 'Unknown Scheduled (ID: %d)', 'osom-multi-theme-switcher' ),
					$rule['value']
				);

			default:
				return $rule['value'];
		}
	}
}
