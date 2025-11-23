<?php
/**
 * Setup Wizard Page.
 *
 * Provides a guided setup experience on first activation.
 *
 * @package Bulk_Yoast_Meta_Updater
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Bulk_Yoast_Meta_Updater_Setup_Page
 */
class Bulk_Yoast_Meta_Updater_Setup_Page {

	/**
	 * Register hidden setup page.
	 */
	public function register_page() {
		add_submenu_page(
			null,
			__( 'Bulk SEO Meta Setup', 'bulk-yoast-meta-updater' ),
			__( 'Bulk SEO Meta Setup', 'bulk-yoast-meta-updater' ),
			'manage_options',
			'bulk-yoast-meta-setup',
			[ $this, 'render' ]
		);
	}

	/**
	 * Handle redirects into setup when pending.
	 */
	public function maybe_redirect_to_setup() {
		$this->maybe_handle_skip();

		if ( ! get_option( 'bymu_setup_pending' ) ) {
			return;
		}

		if ( get_option( 'bymu_setup_completed' ) ) {
			delete_option( 'bymu_setup_pending' );
			return;
		}

		if ( wp_doing_ajax() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Just checking page parameter for redirect logic.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		// Only redirect if trying to access the main plugin dashboard.
		// This prevents locking users out of other admin pages.
		if ( 'bulk-yoast-meta-updater' !== $page ) {
			return;
		}

		$url = add_query_arg(
			[
				'page' => 'bulk-yoast-meta-setup',
			],
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Mark onboarding complete.
	 */
	private function mark_complete() {
		update_option( 'bymu_setup_completed', true );
		delete_option( 'bymu_setup_pending' );
	}

	/**
	 * Handle skip requests.
	 */
	private function maybe_handle_skip() {
		if ( ! isset( $_GET['bymu-setup-skip'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'bymu_setup_skip' ) ) {
			return;
		}

		$this->mark_complete();

		wp_safe_redirect( admin_url( 'admin.php?page=bulk-yoast-meta-updater' ) );
		exit;
	}

	/**
	 * Render setup page content.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'bulk-yoast-meta-updater' ) );
		}

		$message = '';

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in handle_form_submission().
		if ( isset( $_POST['bymu_setup_submit'] ) ) {
			$message = $this->handle_form_submission();
		}

		$settings = bymu_get_settings();
		$skip_url = wp_nonce_url(
			add_query_arg(
				[
					'page'            => 'bulk-yoast-meta-setup',
					'bymu-setup-skip' => 1,
				],
				admin_url( 'admin.php' )
			),
			'bymu_setup_skip'
		);
		?>
		<div class="wrap bymu-wrap bymu-setup-page">
			<div class="bymu-header">
				<div class="bymu-header-content">
					<div class="bymu-header-title">
						<?php bymu_render_mode_badge(); ?>
						<div>
							<h1><?php echo esc_html( bymu_get_brand_name() ); ?></h1>
							<h2 class="bymu-page-title"><?php esc_html_e( 'Welcome Setup', 'bulk-yoast-meta-updater' ); ?></h2>
							<p class="bymu-page-subtitle"><?php esc_html_e( 'Connect Gemini, set your brand name, and review the default prompts. You can change these later in Settings.', 'bulk-yoast-meta-updater' ); ?></p>
						</div>
					</div>
				</div>
			</div>

			<div class="bymu-section bymu-section-compact">
				<div class="bymu-section-body">
					<?php if ( $message ) : ?>
						<div class="notice notice-success is-dismissible">
							<p><strong><?php echo esc_html( $message ); ?></strong></p>
						</div>
					<?php endif; ?>

					<form method="post">
						<?php wp_nonce_field( 'bymu_setup_form', 'bymu_setup_nonce' ); ?>
						<table class="form-table">
							<tr>
								<th scope="row">
									<label for="setup_gemini_api"><?php esc_html_e( 'Google Gemini API Key', 'bulk-yoast-meta-updater' ); ?></label>
								</th>
								<td>
									<input type="password" id="setup_gemini_api" name="gemini_api_key" class="regular-text" autocomplete="off" value="<?php echo esc_attr( $settings['gemini_api_key'] ?? '' ); ?>" />
									<p class="description"><?php esc_html_e( 'Required for AI-powered titles, descriptions, and image alt text.', 'bulk-yoast-meta-updater' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="setup_brand_name"><?php esc_html_e( 'Brand Name', 'bulk-yoast-meta-updater' ); ?></label>
								</th>
								<td>
									<input type="text" id="setup_brand_name" name="ai_brand_name" class="regular-text" value="<?php echo esc_attr( $settings['ai_brand_name'] ?? get_bloginfo( 'name' ) ); ?>" />
									<p class="description"><?php esc_html_e( 'Used to personalize AI prompts via the {{BRAND}} placeholder.', 'bulk-yoast-meta-updater' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="setup_title_prompt"><?php esc_html_e( 'Title Prompt', 'bulk-yoast-meta-updater' ); ?></label>
								</th>
								<td>
									<textarea id="setup_title_prompt" name="ai_title_prompt" class="large-text" rows="4"><?php echo esc_textarea( $settings['ai_title_prompt'] ); ?></textarea>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="setup_description_prompt"><?php esc_html_e( 'Meta Description Prompt', 'bulk-yoast-meta-updater' ); ?></label>
								</th>
								<td>
									<textarea id="setup_description_prompt" name="ai_description_prompt" class="large-text" rows="4"><?php echo esc_textarea( $settings['ai_description_prompt'] ); ?></textarea>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="setup_keyphrase_prompt"><?php esc_html_e( 'Focus Keyphrase Prompt', 'bulk-yoast-meta-updater' ); ?></label>
								</th>
								<td>
									<textarea id="setup_keyphrase_prompt" name="ai_keyphrase_prompt" class="large-text" rows="3"><?php echo esc_textarea( $settings['ai_keyphrase_prompt'] ); ?></textarea>
								</td>
							</tr>
						</table>

						<p class="submit">
							<button type="submit" name="bymu_setup_submit" class="button button-primary button-hero">
								<?php esc_html_e( 'Save & Continue', 'bulk-yoast-meta-updater' ); ?>
							</button>
							<a href="<?php echo esc_url( $skip_url ); ?>" class="button button-link">
								<?php esc_html_e( 'Skip for now', 'bulk-yoast-meta-updater' ); ?>
							</a>
						</p>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle form submission.
	 *
	 * @return string Success message.
	 */
	private function handle_form_submission() {
		if ( ! isset( $_POST['bymu_setup_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bymu_setup_nonce'] ) ), 'bymu_setup_form' ) ) {
			return __( 'Security check failed. Please try again.', 'bulk-yoast-meta-updater' );
		}

		$new_settings = [
			'gemini_api_key'        => isset( $_POST['gemini_api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['gemini_api_key'] ) ) : '',
			'ai_brand_name'         => isset( $_POST['ai_brand_name'] ) ? sanitize_text_field( wp_unslash( $_POST['ai_brand_name'] ) ) : get_bloginfo( 'name' ),
			'ai_title_prompt'       => isset( $_POST['ai_title_prompt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ai_title_prompt'] ) ) : '',
			'ai_description_prompt' => isset( $_POST['ai_description_prompt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ai_description_prompt'] ) ) : '',
			'ai_keyphrase_prompt'   => isset( $_POST['ai_keyphrase_prompt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ai_keyphrase_prompt'] ) ) : '',
		];

		bymu_update_settings( $new_settings );
		delete_transient( 'bymu_gemini_models' );
		$this->mark_complete();

		return __( 'Setup preferences saved. You can revisit these under Settings at any time.', 'bulk-yoast-meta-updater' );
	}
}

