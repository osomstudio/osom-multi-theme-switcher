<?php
/**
 * Admin Page Class
 *
 * Handles the admin settings page for managing theme rules.
 *
 * @package Multi_Theme_Switcher
 * @since   1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MTS_Admin_Page
 *
 * @since 1.0.0
 */
class MTS_Admin_Page {

	/**
	 * Theme switcher instance.
	 *
	 * @var MTS_Theme_Switcher
	 */
	private $theme_switcher;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param MTS_Theme_Switcher $theme_switcher Theme switcher instance.
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
			__( 'Multi Theme Switcher', 'multi-theme-switcher' ),
			__( 'Theme Switcher', 'multi-theme-switcher' ),
			'manage_options',
			'multi-theme-switcher',
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
		if ( 'appearance_page_multi-theme-switcher' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'mts-admin-css',
			plugin_dir_url( dirname( __FILE__ ) ) . 'assets/admin-style.css',
			array(),
			'1.0.0'
		);

		wp_enqueue_script(
			'mts-admin-js',
			plugin_dir_url( dirname( __FILE__ ) ) . 'assets/admin-script.js',
			array( 'jquery' ),
			'1.0.0',
			true
		);

		wp_localize_script(
			'mts-admin-js',
			'mtsAjax',
			array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'mts_nonce' ),
			)
		);
	}

	/**
	 * Render admin page.
	 *
	 * @since 1.0.0
	 */
	public function render_admin_page() {
		$rules          = $this->theme_switcher->get_rules();
		$rest_prefixes  = $this->theme_switcher->get_theme_rest_prefixes();
		$themes         = wp_get_themes();
		$current_theme  = wp_get_theme()->get_stylesheet();
		?>
		<div class="wrap mts-admin-wrap">
			<h1><?php esc_html_e( 'Multi Theme Switcher', 'multi-theme-switcher' ); ?></h1>
			<p>
				<?php
				printf(
					/* translators: %s: Current theme name */
					esc_html__( 'Configure which pages, posts, or URLs use alternative themes. Your main theme is: %s', 'multi-theme-switcher' ),
					'<strong>' . esc_html( wp_get_theme()->get( 'Name' ) ) . '</strong>'
				);
				?>
			</p>

			<div class="mts-container">
				<div class="mts-add-rule-section">
					<h2><?php esc_html_e( 'Add New Theme Rule', 'multi-theme-switcher' ); ?></h2>
					<form id="mts-add-rule-form">
						<table class="form-table">
							<tr>
								<th scope="row">
									<label for="mts-rule-type"><?php esc_html_e( 'Rule Type', 'multi-theme-switcher' ); ?></label>
								</th>
								<td>
									<select id="mts-rule-type" name="rule_type">
										<option value="page"><?php esc_html_e( 'Page', 'multi-theme-switcher' ); ?></option>
										<option value="post"><?php esc_html_e( 'Post', 'multi-theme-switcher' ); ?></option>
										<option value="post_type"><?php esc_html_e( 'Post Type', 'multi-theme-switcher' ); ?></option>
										<option value="draft_page"><?php esc_html_e( 'Draft Page', 'multi-theme-switcher' ); ?></option>
										<option value="draft_post"><?php esc_html_e( 'Draft Post', 'multi-theme-switcher' ); ?></option>
										<option value="pending_page"><?php esc_html_e( 'Pending Page', 'multi-theme-switcher' ); ?></option>
										<option value="pending_post"><?php esc_html_e( 'Pending Post', 'multi-theme-switcher' ); ?></option>
										<option value="private_page"><?php esc_html_e( 'Private Page', 'multi-theme-switcher' ); ?></option>
										<option value="private_post"><?php esc_html_e( 'Private Post', 'multi-theme-switcher' ); ?></option>
										<option value="future_page"><?php esc_html_e( 'Scheduled Page', 'multi-theme-switcher' ); ?></option>
										<option value="future_post"><?php esc_html_e( 'Scheduled Post', 'multi-theme-switcher' ); ?></option>
										<option value="url"><?php esc_html_e( 'Custom URL/Slug', 'multi-theme-switcher' ); ?></option>
										<option value="category"><?php esc_html_e( 'Category', 'multi-theme-switcher' ); ?></option>
										<option value="tag"><?php esc_html_e( 'Tag', 'multi-theme-switcher' ); ?></option>
									</select>
								</td>
							</tr>
							<?php $this->render_page_row(); ?>
							<?php $this->render_post_row(); ?>
							<?php $this->render_post_type_row(); ?>
							<?php $this->render_draft_page_row(); ?>
							<?php $this->render_draft_post_row(); ?>
							<?php $this->render_pending_page_row(); ?>
							<?php $this->render_pending_post_row(); ?>
							<?php $this->render_private_page_row(); ?>
							<?php $this->render_private_post_row(); ?>
							<?php $this->render_future_page_row(); ?>
							<?php $this->render_future_post_row(); ?>
							<?php $this->render_url_row(); ?>
							<?php $this->render_category_row(); ?>
							<?php $this->render_tag_row(); ?>
							<tr>
								<th scope="row">
									<label for="mts-theme-select"><?php esc_html_e( 'Alternative Theme', 'multi-theme-switcher' ); ?></label>
								</th>
								<td>
									<select id="mts-theme-select" name="theme">
										<option value=""><?php esc_html_e( '-- Select Theme --', 'multi-theme-switcher' ); ?></option>
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
								<?php esc_html_e( 'Add Rule', 'multi-theme-switcher' ); ?>
							</button>
						</p>
					</form>
				</div>

				<div class="mts-rules-list-section">
					<h2><?php esc_html_e( 'Active Theme Rules', 'multi-theme-switcher' ); ?></h2>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Type', 'multi-theme-switcher' ); ?></th>
								<th><?php esc_html_e( 'Target', 'multi-theme-switcher' ); ?></th>
								<th><?php esc_html_e( 'Theme', 'multi-theme-switcher' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'multi-theme-switcher' ); ?></th>
							</tr>
						</thead>
						<tbody id="mts-rules-tbody">
							<?php if ( empty( $rules ) ) : ?>
								<tr class="mts-no-rules">
									<td colspan="4">
										<?php esc_html_e( 'No rules configured yet. Add your first rule above.', 'multi-theme-switcher' ); ?>
									</td>
								</tr>
							<?php else : ?>
								<?php foreach ( $rules as $index => $rule ) : ?>
									<tr data-index="<?php echo esc_attr( $index ); ?>">
										<td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $rule['type'] ) ) ); ?></td>
										<td><?php echo esc_html( $this->get_rule_target_display( $rule ) ); ?></td>
										<td><?php echo esc_html( $this->theme_switcher->get_theme_name( $rule['theme'] ) ); ?></td>
										<td>
											<button class="button mts-delete-rule" data-index="<?php echo esc_attr( $index ); ?>">
												<?php esc_html_e( 'Delete', 'multi-theme-switcher' ); ?>
											</button>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>

				<!-- Theme REST Prefix Configuration Section -->
				<div class="mts-rest-prefix-section">
					<h2><?php esc_html_e( 'Theme REST API Prefixes', 'multi-theme-switcher' ); ?></h2>
					<p class="description">
						<?php esc_html_e( 'Assign custom REST API URL prefixes to themes. This allows different themes to have their own REST endpoints (e.g., /wp-json/ vs /wp-json-2/). Useful when themes have conflicting REST API routes.', 'multi-theme-switcher' ); ?>
					</p>

					<div class="mts-add-prefix-section">
						<h3><?php esc_html_e( 'Configure Theme REST Prefix', 'multi-theme-switcher' ); ?></h3>
						<form id="mts-add-prefix-form">
							<table class="form-table">
								<tr>
									<th scope="row">
										<label for="mts-prefix-theme-select"><?php esc_html_e( 'Theme', 'multi-theme-switcher' ); ?></label>
									</th>
									<td>
										<select id="mts-prefix-theme-select" name="prefix_theme">
											<option value=""><?php esc_html_e( '-- Select Theme --', 'multi-theme-switcher' ); ?></option>
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
										<label for="mts-rest-prefix-input"><?php esc_html_e( 'REST API Prefix', 'multi-theme-switcher' ); ?></label>
									</th>
									<td>
										<input type="text" id="mts-rest-prefix-input" name="rest_prefix" class="regular-text" placeholder="<?php esc_attr_e( 'e.g., wp-json-2', 'multi-theme-switcher' ); ?>">
										<p class="description">
											<?php
											printf(
												/* translators: 1: Default prefix example, 2: Custom prefix example */
												esc_html__( 'Enter a custom prefix without slashes. Leave empty to use default (%1$s). Example: %2$s becomes %3$s', 'multi-theme-switcher' ),
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
									<?php esc_html_e( 'Set REST Prefix', 'multi-theme-switcher' ); ?>
								</button>
							</p>
						</form>
					</div>

					<div class="mts-prefixes-list">
						<h3><?php esc_html_e( 'Active REST Prefix Mappings', 'multi-theme-switcher' ); ?></h3>
						<table class="wp-list-table widefat fixed striped">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Theme', 'multi-theme-switcher' ); ?></th>
									<th><?php esc_html_e( 'REST Prefix', 'multi-theme-switcher' ); ?></th>
									<th><?php esc_html_e( 'Example URL', 'multi-theme-switcher' ); ?></th>
									<th><?php esc_html_e( 'Actions', 'multi-theme-switcher' ); ?></th>
								</tr>
							</thead>
							<tbody id="mts-prefixes-tbody">
								<?php if ( empty( $rest_prefixes ) ) : ?>
									<tr class="mts-no-prefixes">
										<td colspan="4">
											<?php esc_html_e( 'No custom REST prefixes configured. All themes use the default wp-json prefix.', 'multi-theme-switcher' ); ?>
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
												<button class="button mts-delete-prefix" data-index="<?php echo esc_attr( $index ); ?>">
													<?php esc_html_e( 'Delete', 'multi-theme-switcher' ); ?>
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
	 * Render page selection row.
	 *
	 * @since 1.0.0
	 */
	private function render_page_row() {
		?>
		<tr id="mts-page-row" class="mts-rule-row">
			<th scope="row">
				<label for="mts-page-select"><?php esc_html_e( 'Select Page', 'multi-theme-switcher' ); ?></label>
			</th>
			<td>
				<select id="mts-page-select" name="page_id">
					<option value=""><?php esc_html_e( '-- Select Page --', 'multi-theme-switcher' ); ?></option>
					<?php
					$pages = get_pages();
					foreach ( $pages as $page ) {
						printf(
							'<option value="%d">%s</option>',
							esc_attr( $page->ID ),
							esc_html( $page->post_title )
						);
					}
					?>
				</select>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render post selection row.
	 *
	 * @since 1.0.0
	 */
	private function render_post_row() {
		?>
		<tr id="mts-post-row" class="mts-rule-row" style="display:none;">
			<th scope="row">
				<label for="mts-post-select"><?php esc_html_e( 'Select Post', 'multi-theme-switcher' ); ?></label>
			</th>
			<td>
				<select id="mts-post-select" name="post_id">
					<option value=""><?php esc_html_e( '-- Select Post --', 'multi-theme-switcher' ); ?></option>
					<?php
					$posts = get_posts( array( 'numberposts' => -1 ) );
					foreach ( $posts as $post ) {
						printf(
							'<option value="%d">%s</option>',
							esc_attr( $post->ID ),
							esc_html( $post->post_title )
						);
					}
					?>
				</select>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render post type selection row.
	 *
	 * @since 1.0.0
	 */
	private function render_post_type_row() {
		?>
		<tr id="mts-post-type-row" class="mts-rule-row" style="display:none;">
			<th scope="row">
				<label for="mts-post-type-select"><?php esc_html_e( 'Select Post Type', 'multi-theme-switcher' ); ?></label>
			</th>
			<td>
				<select id="mts-post-type-select" name="post_type">
					<option value=""><?php esc_html_e( '-- Select Post Type --', 'multi-theme-switcher' ); ?></option>
					<?php
					$post_types = get_post_types( array( 'public' => true ), 'objects' );
					foreach ( $post_types as $post_type ) {
						printf(
							'<option value="%s">%s</option>',
							esc_attr( $post_type->name ),
							esc_html( $post_type->label )
						);
					}
					?>
				</select>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render URL input row.
	 *
	 * @since 1.0.0
	 */
	private function render_url_row() {
		?>
		<tr id="mts-url-row" class="mts-rule-row" style="display:none;">
			<th scope="row">
				<label for="mts-url-input"><?php esc_html_e( 'Custom URL/Slug', 'multi-theme-switcher' ); ?></label>
			</th>
			<td>
				<input type="text" id="mts-url-input" name="custom_url" class="regular-text" placeholder="<?php esc_attr_e( 'e.g., /about-us or about-us', 'multi-theme-switcher' ); ?>">
				<p class="description">
					<?php esc_html_e( 'Enter a URL path or slug (with or without leading slash)', 'multi-theme-switcher' ); ?>
				</p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render category selection row.
	 *
	 * @since 1.0.0
	 */
	private function render_category_row() {
		?>
		<tr id="mts-category-row" class="mts-rule-row" style="display:none;">
			<th scope="row">
				<label for="mts-category-select"><?php esc_html_e( 'Select Category', 'multi-theme-switcher' ); ?></label>
			</th>
			<td>
				<select id="mts-category-select" name="category_id">
					<option value=""><?php esc_html_e( '-- Select Category --', 'multi-theme-switcher' ); ?></option>
					<?php
					$categories = get_categories( array( 'hide_empty' => false ) );
					foreach ( $categories as $category ) {
						printf(
							'<option value="%d">%s</option>',
							esc_attr( $category->term_id ),
							esc_html( $category->name )
						);
					}
					?>
				</select>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render tag selection row.
	 *
	 * @since 1.0.0
	 */
	private function render_tag_row() {
		?>
		<tr id="mts-tag-row" class="mts-rule-row" style="display:none;">
			<th scope="row">
				<label for="mts-tag-select"><?php esc_html_e( 'Select Tag', 'multi-theme-switcher' ); ?></label>
			</th>
			<td>
				<select id="mts-tag-select" name="tag_id">
					<option value=""><?php esc_html_e( '-- Select Tag --', 'multi-theme-switcher' ); ?></option>
					<?php
					$tags = get_tags( array( 'hide_empty' => false ) );
					foreach ( $tags as $tag ) {
						printf(
							'<option value="%d">%s</option>',
							esc_attr( $tag->term_id ),
							esc_html( $tag->name )
						);
					}
					?>
				</select>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render draft page selection row.
	 *
	 * @since 1.0.2
	 */
	private function render_draft_page_row() {
		?>
		<tr id="mts-draft-page-row" class="mts-rule-row" style="display:none;">
			<th scope="row">
				<label for="mts-draft-page-select"><?php esc_html_e( 'Select Draft Page', 'multi-theme-switcher' ); ?></label>
			</th>
			<td>
				<select id="mts-draft-page-select" name="page_id">
					<option value=""><?php esc_html_e( '-- Select Draft Page --', 'multi-theme-switcher' ); ?></option>
					<?php
					$pages = get_pages( array( 'post_status' => 'draft' ) );
					foreach ( $pages as $page ) {
						printf(
							'<option value="%d">%s (Draft)</option>',
							esc_attr( $page->ID ),
							esc_html( $page->post_title )
						);
					}
					?>
				</select>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render draft post selection row.
	 *
	 * @since 1.0.2
	 */
	private function render_draft_post_row() {
		?>
		<tr id="mts-draft-post-row" class="mts-rule-row" style="display:none;">
			<th scope="row">
				<label for="mts-draft-post-select"><?php esc_html_e( 'Select Draft Post', 'multi-theme-switcher' ); ?></label>
			</th>
			<td>
				<select id="mts-draft-post-select" name="post_id">
					<option value=""><?php esc_html_e( '-- Select Draft Post --', 'multi-theme-switcher' ); ?></option>
					<?php
					$posts = get_posts( array( 'numberposts' => -1, 'post_status' => 'draft' ) );
					foreach ( $posts as $post ) {
						printf(
							'<option value="%d">%s (Draft)</option>',
							esc_attr( $post->ID ),
							esc_html( $post->post_title )
						);
					}
					?>
				</select>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render pending page selection row.
	 *
	 * @since 1.0.2
	 */
	private function render_pending_page_row() {
		?>
		<tr id="mts-pending-page-row" class="mts-rule-row" style="display:none;">
			<th scope="row">
				<label for="mts-pending-page-select"><?php esc_html_e( 'Select Pending Page', 'multi-theme-switcher' ); ?></label>
			</th>
			<td>
				<select id="mts-pending-page-select" name="page_id">
					<option value=""><?php esc_html_e( '-- Select Pending Page --', 'multi-theme-switcher' ); ?></option>
					<?php
					$pages = get_pages( array( 'post_status' => 'pending' ) );
					foreach ( $pages as $page ) {
						printf(
							'<option value="%d">%s (Pending)</option>',
							esc_attr( $page->ID ),
							esc_html( $page->post_title )
						);
					}
					?>
				</select>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render pending post selection row.
	 *
	 * @since 1.0.2
	 */
	private function render_pending_post_row() {
		?>
		<tr id="mts-pending-post-row" class="mts-rule-row" style="display:none;">
			<th scope="row">
				<label for="mts-pending-post-select"><?php esc_html_e( 'Select Pending Post', 'multi-theme-switcher' ); ?></label>
			</th>
			<td>
				<select id="mts-pending-post-select" name="post_id">
					<option value=""><?php esc_html_e( '-- Select Pending Post --', 'multi-theme-switcher' ); ?></option>
					<?php
					$posts = get_posts( array( 'numberposts' => -1, 'post_status' => 'pending' ) );
					foreach ( $posts as $post ) {
						printf(
							'<option value="%d">%s (Pending)</option>',
							esc_attr( $post->ID ),
							esc_html( $post->post_title )
						);
					}
					?>
				</select>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render private page selection row.
	 *
	 * @since 1.0.2
	 */
	private function render_private_page_row() {
		?>
		<tr id="mts-private-page-row" class="mts-rule-row" style="display:none;">
			<th scope="row">
				<label for="mts-private-page-select"><?php esc_html_e( 'Select Private Page', 'multi-theme-switcher' ); ?></label>
			</th>
			<td>
				<select id="mts-private-page-select" name="page_id">
					<option value=""><?php esc_html_e( '-- Select Private Page --', 'multi-theme-switcher' ); ?></option>
					<?php
					$pages = get_pages( array( 'post_status' => 'private' ) );
					foreach ( $pages as $page ) {
						printf(
							'<option value="%d">%s (Private)</option>',
							esc_attr( $page->ID ),
							esc_html( $page->post_title )
						);
					}
					?>
				</select>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render private post selection row.
	 *
	 * @since 1.0.2
	 */
	private function render_private_post_row() {
		?>
		<tr id="mts-private-post-row" class="mts-rule-row" style="display:none;">
			<th scope="row">
				<label for="mts-private-post-select"><?php esc_html_e( 'Select Private Post', 'multi-theme-switcher' ); ?></label>
			</th>
			<td>
				<select id="mts-private-post-select" name="post_id">
					<option value=""><?php esc_html_e( '-- Select Private Post --', 'multi-theme-switcher' ); ?></option>
					<?php
					$posts = get_posts( array( 'numberposts' => -1, 'post_status' => 'private' ) );
					foreach ( $posts as $post ) {
						printf(
							'<option value="%d">%s (Private)</option>',
							esc_attr( $post->ID ),
							esc_html( $post->post_title )
						);
					}
					?>
				</select>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render scheduled page selection row.
	 *
	 * @since 1.0.2
	 */
	private function render_future_page_row() {
		?>
		<tr id="mts-future-page-row" class="mts-rule-row" style="display:none;">
			<th scope="row">
				<label for="mts-future-page-select"><?php esc_html_e( 'Select Scheduled Page', 'multi-theme-switcher' ); ?></label>
			</th>
			<td>
				<select id="mts-future-page-select" name="page_id">
					<option value=""><?php esc_html_e( '-- Select Scheduled Page --', 'multi-theme-switcher' ); ?></option>
					<?php
					$pages = get_pages( array( 'post_status' => 'future' ) );
					foreach ( $pages as $page ) {
						printf(
							'<option value="%d">%s (Scheduled)</option>',
							esc_attr( $page->ID ),
							esc_html( $page->post_title )
						);
					}
					?>
				</select>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render scheduled post selection row.
	 *
	 * @since 1.0.2
	 */
	private function render_future_post_row() {
		?>
		<tr id="mts-future-post-row" class="mts-rule-row" style="display:none;">
			<th scope="row">
				<label for="mts-future-post-select"><?php esc_html_e( 'Select Scheduled Post', 'multi-theme-switcher' ); ?></label>
			</th>
			<td>
				<select id="mts-future-post-select" name="post_id">
					<option value=""><?php esc_html_e( '-- Select Scheduled Post --', 'multi-theme-switcher' ); ?></option>
					<?php
					$posts = get_posts( array( 'numberposts' => -1, 'post_status' => 'future' ) );
					foreach ( $posts as $post ) {
						printf(
							'<option value="%d">%s (Scheduled)</option>',
							esc_attr( $post->ID ),
							esc_html( $post->post_title )
						);
					}
					?>
				</select>
			</td>
		</tr>
		<?php
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
					__( 'Unknown Page (ID: %d)', 'multi-theme-switcher' ),
					$rule['value']
				);

			case 'post':
				$post = get_post( $rule['value'] );
				return $post ? $post->post_title : sprintf(
					/* translators: %d: Post ID */
					__( 'Unknown Post (ID: %d)', 'multi-theme-switcher' ),
					$rule['value']
				);

			case 'post_type':
				$post_type_obj = get_post_type_object( $rule['value'] );
				return $post_type_obj ? $post_type_obj->label : $rule['value'];

			case 'url':
				return $rule['value'];

			case 'category':
				$category = get_category( $rule['value'] );
				return $category ? $category->name : __( 'Unknown Category', 'multi-theme-switcher' );

			case 'tag':
				$tag = get_tag( $rule['value'] );
				return $tag ? $tag->name : __( 'Unknown Tag', 'multi-theme-switcher' );

			case 'draft_page':
				$page = get_post( $rule['value'] );
				return $page ? $page->post_title . ' (Draft)' : sprintf(
					/* translators: %d: Page ID */
					__( 'Unknown Draft Page (ID: %d)', 'multi-theme-switcher' ),
					$rule['value']
				);

			case 'draft_post':
				$post = get_post( $rule['value'] );
				return $post ? $post->post_title . ' (Draft)' : sprintf(
					/* translators: %d: Post ID */
					__( 'Unknown Draft Post (ID: %d)', 'multi-theme-switcher' ),
					$rule['value']
				);

			case 'pending_page':
				$page = get_post( $rule['value'] );
				return $page ? $page->post_title . ' (Pending)' : sprintf(
					/* translators: %d: Page ID */
					__( 'Unknown Pending Page (ID: %d)', 'multi-theme-switcher' ),
					$rule['value']
				);

			case 'pending_post':
				$post = get_post( $rule['value'] );
				return $post ? $post->post_title . ' (Pending)' : sprintf(
					/* translators: %d: Post ID */
					__( 'Unknown Pending Post (ID: %d)', 'multi-theme-switcher' ),
					$rule['value']
				);

			case 'private_page':
				$page = get_post( $rule['value'] );
				return $page ? $page->post_title . ' (Private)' : sprintf(
					/* translators: %d: Page ID */
					__( 'Unknown Private Page (ID: %d)', 'multi-theme-switcher' ),
					$rule['value']
				);

			case 'private_post':
				$post = get_post( $rule['value'] );
				return $post ? $post->post_title . ' (Private)' : sprintf(
					/* translators: %d: Post ID */
					__( 'Unknown Private Post (ID: %d)', 'multi-theme-switcher' ),
					$rule['value']
				);

			case 'future_page':
				$page = get_post( $rule['value'] );
				return $page ? $page->post_title . ' (Scheduled)' : sprintf(
					/* translators: %d: Page ID */
					__( 'Unknown Scheduled Page (ID: %d)', 'multi-theme-switcher' ),
					$rule['value']
				);

			case 'future_post':
				$post = get_post( $rule['value'] );
				return $post ? $post->post_title . ' (Scheduled)' : sprintf(
					/* translators: %d: Post ID */
					__( 'Unknown Scheduled Post (ID: %d)', 'multi-theme-switcher' ),
					$rule['value']
				);

			default:
				return $rule['value'];
		}
	}
}
