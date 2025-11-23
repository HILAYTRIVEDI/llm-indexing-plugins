<?php
/**
 * Settings Page Class
 *
 * Handles the plugin settings page.
 *
 * @package Bulk_Yoast_Meta_Updater
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Bulk_Yoast_Meta_Updater_Settings_Page
 */
class Bulk_Yoast_Meta_Updater_Settings_Page {

	/**
	 * Render the settings page.
	 */
	public function render() {
		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'bulk-yoast-meta-updater' ) );
		}

		// Handle form submission.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in save_settings().
		if ( isset( $_POST['bymu_save_settings'] ) ) {
			$this->save_settings();
		}

		// Get current tab.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Just checking tab parameter for display logic.
		$current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'settings';

		// Get current settings.
		$settings = bymu_get_settings();
		$stats    = Bulk_Yoast_Meta_Updater_DB_Manager::get_table_stats();

		$models_error         = '';
		$gemini_models_result = bymu_get_or_fetch_gemini_models( $settings['gemini_api_key'] ?? '' );

		if ( is_wp_error( $gemini_models_result ) ) {
			$models_error  = $gemini_models_result->get_error_message();
			$models_source = bymu_get_default_gemini_models();
		} else {
			if ( empty( $gemini_models_result ) ) {
				$models_source = bymu_get_default_gemini_models();
			} else {
				// Merge API models with defaults (API takes precedence, defaults fill gaps).
				$models_source = bymu_merge_models_with_defaults( $gemini_models_result );
			}
		}

		$models_split = bymu_split_gemini_models_by_category( $models_source );

		$text_models  = $models_split['text'];
		$image_models = $models_split['image'];

		$current_text_model  = $settings['gemini_text_model'] ?? 'gemini-2.5-flash';
		$current_image_model = $settings['gemini_image_model'] ?? 'gemini-2.5-flash-image';

		// Validate current models - if not found, reset to recommended 2.5 defaults.
		if ( $current_text_model && ! bymu_model_exists( $text_models, $current_text_model ) ) {
			// Check if it's a valid-looking model ID (allow custom models).
			$normalized_current = strtolower( trim( $current_text_model ) );
			if ( preg_match( '/^gemini-[0-9a-z\.\-]+$/', $normalized_current ) ) {
				// Add as custom model option.
				$text_models[] = [
					'id'           => $current_text_model,
					'display_name' => $current_text_model . ' (custom)',
					'category'     => 'text',
				];
			} else {
				// Invalid model, reset to default.
				$current_text_model = 'gemini-2.5-flash';
			}
		}

		if ( $current_image_model && ! bymu_model_exists( $image_models, $current_image_model ) ) {
			// Check if it's a valid-looking model ID (allow custom models).
			$normalized_current = strtolower( trim( $current_image_model ) );
			if ( preg_match( '/^gemini-[0-9a-z\.\-]+$/', $normalized_current ) ) {
				// Add as custom model option.
				$image_models[] = [
					'id'           => $current_image_model,
					'display_name' => $current_image_model . ' (custom)',
					'category'     => 'image',
				];
			} else {
				// Invalid model, reset to default.
				$current_image_model = 'gemini-2.5-flash-image';
			}
		}

		$test_mode_notice = null;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Just checking testmode parameter for enabling test features.
		if ( isset( $_GET['testmode'] ) ) {
			$test_mode_value = sanitize_text_field( wp_unslash( $_GET['testmode'] ) );
			$current_user_id = get_current_user_id();

			if ( '1' === $test_mode_value ) {
				bymu_enable_test_mode_for_user( $current_user_id );

				if ( bymu_is_test_mode_enabled( $current_user_id ) ) {
					$test_mode_notice = [
						'type'    => 'success',
						'message' => __( 'Test tools enabled for the next 15 minutes in this session. Return to the dashboard to access them.', 'bulk-yoast-meta-updater' ),
					];
				}
			} elseif ( '0' === $test_mode_value ) {
				bymu_disable_test_mode_for_user( $current_user_id );
				$test_mode_notice = [
					'type'    => 'info',
					'message' => __( 'Test tools disabled for your account.', 'bulk-yoast-meta-updater' ),
				];
			}
		}

		?>
		<div class="wrap bymu-wrap">
			<!-- Header -->
			<div class="bymu-header">
				<div class="bymu-header-content">
					<div class="bymu-header-title">
						<?php bymu_render_mode_badge(); ?>
						<div>
							<h1><?php echo esc_html( bymu_get_brand_name() ); ?></h1>
							<h2 class="bymu-page-title"><?php esc_html_e( 'Settings', 'bulk-yoast-meta-updater' ); ?></h2>
							<p class="bymu-page-subtitle"><?php esc_html_e( 'Configure retention, validation, and AI defaults.', 'bulk-yoast-meta-updater' ); ?></p>
						</div>
					</div>
					<div class="bymu-header-actions">
						<a class="button bymu-button-ghost bymu-hero-button" href="<?php echo esc_url( admin_url( 'admin.php?page=bulk-yoast-meta-settings&tab=documentation' ) ); ?>">
							<?php esc_html_e( 'Documentation', 'bulk-yoast-meta-updater' ); ?>
						</a>
					</div>
				</div>
			</div>
			<?php bymu_render_admin_nav( 'settings' ); ?>

			<?php if ( $test_mode_notice ) : ?>
			<div class="notice notice-<?php echo esc_attr( $test_mode_notice['type'] ); ?> is-dismissible">
				<p><strong><?php echo esc_html( $test_mode_notice['message'] ); ?></strong></p>
			</div>
			<?php endif; ?>

			<!-- Tab Navigation -->
			<nav class="bymu-tabs">
				<a href="?page=bulk-yoast-meta-settings&tab=settings" 
					class="bymu-tab <?php echo 'settings' === $current_tab ? 'active' : ''; ?>">
					<?php esc_html_e( 'Settings', 'bulk-yoast-meta-updater' ); ?>
				</a>
				<a href="?page=bulk-yoast-meta-settings&tab=documentation" 
					class="bymu-tab <?php echo 'documentation' === $current_tab ? 'active' : ''; ?>">
					<?php esc_html_e( 'Documentation', 'bulk-yoast-meta-updater' ); ?>
				</a>
			</nav>

			<?php if ( 'settings' === $current_tab ) : ?>
			<!-- Settings Tab -->
			<div class="bymu-section bymu-section-compact">
				<div class="bymu-section-header">
					<h2><?php esc_html_e( 'Plugin Settings', 'bulk-yoast-meta-updater' ); ?></h2>
					<p><?php esc_html_e( 'Adjust how the plugin processes and validates your CSV imports.', 'bulk-yoast-meta-updater' ); ?></p>
				</div>
				<div class="bymu-section-body">
			<form method="post" action="">
				<?php wp_nonce_field( 'bymu_settings', 'bymu_settings_nonce' ); ?>

				<div class="bymu-settings-grid">
					<table class="form-table bymu-settings-table" role="presentation">
						<tbody>
							<tr>
								<th colspan="2">
									<h2><?php esc_html_e( 'General Settings', 'bulk-yoast-meta-updater' ); ?></h2>
								</th>
							</tr>

							<tr>
								<th scope="row">
									<label for="log_retention"><?php esc_html_e( 'Job Log Retention', 'bulk-yoast-meta-updater' ); ?></label>
								</th>
								<td>
									<input type="number" name="log_retention" id="log_retention" 
										value="<?php echo esc_attr( $settings['log_retention'] ); ?>" 
										min="1" max="100" class="small-text" />
									<p class="description"><?php esc_html_e( 'Keep last N jobs per user (1-100).', 'bulk-yoast-meta-updater' ); ?></p>
								</td>
							</tr>

							<tr>
								<th scope="row"><?php esc_html_e( 'Processing Defaults', 'bulk-yoast-meta-updater' ); ?></th>
								<td>
									<p><strong><?php esc_html_e( 'Batch Size:', 'bulk-yoast-meta-updater' ); ?></strong> <?php esc_html_e( '15 rows per batch (fixed for VIP stability).', 'bulk-yoast-meta-updater' ); ?></p>
									<p><strong><?php esc_html_e( 'CSV Upload Limit:', 'bulk-yoast-meta-updater' ); ?></strong> <?php esc_html_e( 'Up to 5 MB per file (enforced at 4 MB for headroom).', 'bulk-yoast-meta-updater' ); ?></p>
								</td>
							</tr>
						</tbody>
					</table>

					<table class="form-table bymu-settings-table" role="presentation">
						<tbody>
							<tr>
								<th colspan="2">
									<h2><?php esc_html_e( 'Validation Settings', 'bulk-yoast-meta-updater' ); ?></h2>
								</th>
							</tr>

							<tr>
								<th scope="row">
									<label for="title_warning_chars"><?php esc_html_e( 'Meta Title Warning (chars)', 'bulk-yoast-meta-updater' ); ?></label>
								</th>
								<td>
									<input type="number" name="title_warning_chars" id="title_warning_chars" 
										value="<?php echo esc_attr( $settings['title_warning_chars'] ); ?>" 
										min="10" max="200" class="small-text" />
									<p class="description"><?php esc_html_e( 'Warn if title exceeds this character count.', 'bulk-yoast-meta-updater' ); ?></p>
								</td>
							</tr>

							<tr>
								<th scope="row">
									<label for="desc_warning_chars"><?php esc_html_e( 'Meta Description Warning (chars)', 'bulk-yoast-meta-updater' ); ?></label>
								</th>
								<td>
									<input type="number" name="desc_warning_chars" id="desc_warning_chars" 
										value="<?php echo esc_attr( $settings['desc_warning_chars'] ); ?>" 
										min="10" max="300" class="small-text" />
									<p class="description"><?php esc_html_e( 'Warn if description exceeds this character count.', 'bulk-yoast-meta-updater' ); ?></p>
								</td>
							</tr>

							<tr>
								<th scope="row">
									<label for="keyword_warning_chars"><?php esc_html_e( 'Focus Keyword Warning (chars)', 'bulk-yoast-meta-updater' ); ?></label>
								</th>
								<td>
									<input type="number" name="keyword_warning_chars" id="keyword_warning_chars" 
										value="<?php echo esc_attr( $settings['keyword_warning_chars'] ); ?>" 
										min="10" max="200" class="small-text" />
									<p class="description"><?php esc_html_e( 'Warn if keyword exceeds this character count.', 'bulk-yoast-meta-updater' ); ?></p>
								</td>
							</tr>
						</tbody>
					</table>
				</div>

				<table class="form-table" role="presentation">
					<tbody>

						<!-- URL Resolution Settings -->
						<tr>
							<th colspan="2">
								<h2><?php esc_html_e( 'URL Resolution Settings', 'bulk-yoast-meta-updater' ); ?></h2>
							</th>
						</tr>

						<tr>
							<th scope="row">
								<?php esc_html_e( 'URL Matching Mode', 'bulk-yoast-meta-updater' ); ?>
							</th>
							<td>
								<fieldset>
									<label style="display: block; margin-bottom: 10px;">
										<input type="radio" name="url_mode" value="lenient" 
											<?php checked( $settings['url_mode'], 'lenient' ); ?> />
										<strong><?php esc_html_e( 'Path Only (Recommended)', 'bulk-yoast-meta-updater' ); ?></strong>
										<span class="bymu-badge info" style="font-size: 10px; padding: 2px 6px;">Default</span>
										<br>
										<span class="description" style="margin-left: 24px; display: block;">
											<?php esc_html_e( 'Matches URLs by path only, ignoring hostname. Works across staging/production sites.', 'bulk-yoast-meta-updater' ); ?>
											<br>
											<em><?php esc_html_e( 'Example: Matches /blog/post-name/ regardless of domain', 'bulk-yoast-meta-updater' ); ?></em>
										</span>
									</label>
									<label style="display: block;">
										<input type="radio" name="url_mode" value="strict" 
											<?php checked( $settings['url_mode'], 'strict' ); ?> />
										<strong><?php esc_html_e( 'Full URL Match (Strict)', 'bulk-yoast-meta-updater' ); ?></strong>
										<br>
										<span class="description" style="margin-left: 24px; display: block;">
											<?php esc_html_e( 'Requires exact domain match. Use only if importing URLs from the same domain.', 'bulk-yoast-meta-updater' ); ?>
											<br>
											<em><?php esc_html_e( 'Example: https://example.com/blog/post/ must match exactly', 'bulk-yoast-meta-updater' ); ?></em>
										</span>
									</label>
								</fieldset>
							</td>
						</tr>

						<!-- AI Generation Settings -->
						<tr>
							<th colspan="2">
								<h2><?php esc_html_e( 'AI Generation Settings', 'bulk-yoast-meta-updater' ); ?></h2>
							</th>
						</tr>

						<tr>
							<th scope="row">
								<label for="gemini_api_key"><?php esc_html_e( 'Google Gemini API Key', 'bulk-yoast-meta-updater' ); ?></label>
							</th>
							<td>
								<input type="password" name="gemini_api_key" id="gemini_api_key" 
									value="<?php echo esc_attr( $settings['gemini_api_key'] ?? '' ); ?>" 
									class="regular-text" 
									autocomplete="off" />
								<button type="button" class="button button-small" id="bymu-toggle-api-key" 
									style="margin-left: 8px;">
									<?php esc_html_e( 'Show', 'bulk-yoast-meta-updater' ); ?>
								</button>
								<p class="description">
									<?php
									printf(
										/* translators: %s: Link to Google AI Studio */
										esc_html__( 'Get your API key from %s. Required for AI-powered SEO suggestions.', 'bulk-yoast-meta-updater' ),
										'<a href="https://aistudio.google.com/app/apikey" target="_blank">Google AI Studio</a>'
									);
									?>
								</p>
						<?php if ( ! empty( $settings['gemini_api_key'] ) ) : ?>
							<p style="color: var(--bymu-success); font-weight: 600; margin-top: 8px;">
								✓ <?php esc_html_e( 'API Key is configured', 'bulk-yoast-meta-updater' ); ?>
							</p>
						<?php endif; ?>

						<div class="bymu-model-refresh" style="margin-top: 10px;">
							<button type="button" class="button" id="bymu-refresh-gemini-models">
								<?php esc_html_e( 'Refresh Gemini Models', 'bulk-yoast-meta-updater' ); ?>
							</button>
							<span id="bymu-model-refresh-status" class="description" style="margin-left: 10px;"></span>
						</div>

						<?php if ( $models_error ) : ?>
							<p class="description" style="color: #d63638; margin-top: 6px;">
								<?php echo esc_html( $models_error ); ?>
							</p>
						<?php endif; ?>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="gemini_text_model"><?php esc_html_e( 'Gemini Text Model', 'bulk-yoast-meta-updater' ); ?></label>
					</th>
					<td>
						<select name="gemini_text_model" id="gemini_text_model" data-current="<?php echo esc_attr( $current_text_model ); ?>">
							<?php foreach ( $text_models as $model ) : ?>
								<?php
								$label = $model['display_name'] && $model['display_name'] !== $model['id']
									? sprintf( '%1$s (%2$s)', $model['display_name'], $model['id'] )
									: $model['id'];
								
								// Mark recommended models.
								$is_recommended = ( false !== strpos( strtolower( $model['id'] ), '2.5-flash' ) && false === strpos( strtolower( $model['id'] ), 'lite' ) && false === strpos( strtolower( $model['id'] ), 'preview' ) );
								if ( $is_recommended ) {
									$label = '⭐ ' . $label;
								}
								?>
								<option value="<?php echo esc_attr( $model['id'] ); ?>" <?php selected( $current_text_model, $model['id'] ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							<?php esc_html_e( 'Model used for titles, descriptions, and focus keyphrase generation. Recommended: gemini-2.5-flash (1M-token context, fast reasoning).', 'bulk-yoast-meta-updater' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="gemini_image_model"><?php esc_html_e( 'Gemini Image Model', 'bulk-yoast-meta-updater' ); ?></label>
					</th>
					<td>
						<select name="gemini_image_model" id="gemini_image_model" data-current="<?php echo esc_attr( $current_image_model ); ?>">
							<?php foreach ( $image_models as $model ) : ?>
								<?php
								$label = $model['display_name'] && $model['display_name'] !== $model['id']
									? sprintf( '%1$s (%2$s)', $model['display_name'], $model['id'] )
									: $model['id'];
								
								// Mark recommended models.
								$is_recommended = ( false !== strpos( strtolower( $model['id'] ), '2.5-flash-image' ) && false === strpos( strtolower( $model['id'] ), 'preview' ) );
								if ( $is_recommended ) {
									$label = '⭐ ' . $label;
								}
								?>
								<option value="<?php echo esc_attr( $model['id'] ); ?>" <?php selected( $current_image_model, $model['id'] ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							<?php esc_html_e( 'Model used for image alt text and other vision tasks. Recommended: gemini-2.5-flash-image (stable vision).', 'bulk-yoast-meta-updater' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="ai_brand_name"><?php esc_html_e( 'Brand Name', 'bulk-yoast-meta-updater' ); ?></label>
					</th>
					<td>
						<input type="text" name="ai_brand_name" id="ai_brand_name" 
							value="<?php echo esc_attr( $settings['ai_brand_name'] ?? get_bloginfo( 'name' ) ); ?>" 
							class="regular-text" 
							placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" />
						<p class="description">
							<?php
							printf(
								/* translators: %s: Site title */
								esc_html__( 'Your brand name used in AI prompts. Use {{BRAND}} in your custom prompts to include this value. Defaults to: %s', 'bulk-yoast-meta-updater' ),
								'<strong>' . esc_html( get_bloginfo( 'name' ) ) . '</strong>'
							);
							?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="ai_title_prompt"><?php esc_html_e( 'Title Generation Prompt', 'bulk-yoast-meta-updater' ); ?></label>
					</th>
					<td>
						<div style="margin-bottom: 12px;">
							<label for="bymu-title-pattern" style="font-weight: 600; display: block; margin-bottom: 4px;">
								<?php esc_html_e( 'B2B Title Patterns (Optional Templates):', 'bulk-yoast-meta-updater' ); ?>
							</label>
							<select id="bymu-title-pattern" class="regular-text">
								<option value=""><?php esc_html_e( '-- Select a B2B pattern to load --', 'bulk-yoast-meta-updater' ); ?></option>
								<option value="solution-focused"><?php esc_html_e( 'Solution-Focused (Problem → Solution)', 'bulk-yoast-meta-updater' ); ?></option>
								<option value="roi-driven"><?php esc_html_e( 'ROI-Driven (Outcome → Benefit)', 'bulk-yoast-meta-updater' ); ?></option>
								<option value="thought-leadership"><?php esc_html_e( 'Thought Leadership (Insight → Authority)', 'bulk-yoast-meta-updater' ); ?></option>
								<option value="feature-benefit"><?php esc_html_e( 'Feature-to-Benefit (What → Why)', 'bulk-yoast-meta-updater' ); ?></option>
								<option value="industry-specific"><?php esc_html_e( 'Industry-Specific (Vertical Focus)', 'bulk-yoast-meta-updater' ); ?></option>
								<option value="enterprise"><?php esc_html_e( 'Enterprise (Scale → Security)', 'bulk-yoast-meta-updater' ); ?></option>
								<option value="comparison"><?php esc_html_e( 'Comparison/Alternative (vs Competitor)', 'bulk-yoast-meta-updater' ); ?></option>
								<option value="case-study"><?php esc_html_e( 'Case Study (Results → Proof)', 'bulk-yoast-meta-updater' ); ?></option>
							</select>
							<button type="button" class="button button-secondary" id="bymu-load-title-pattern" style="margin-left: 8px;">
								<?php esc_html_e( 'Load Pattern', 'bulk-yoast-meta-updater' ); ?>
							</button>
							<p class="description" style="margin-top: 4px;">
								<?php esc_html_e( 'Choose a B2B-optimized template to populate the prompt below. You can customize it after loading.', 'bulk-yoast-meta-updater' ); ?>
							</p>
						</div>
						<textarea name="ai_title_prompt" id="ai_title_prompt" 
							rows="12" class="large-text bymu-ai-prompt" 
							placeholder="<?php esc_attr_e( 'Enter custom instructions for title generation...', 'bulk-yoast-meta-updater' ); ?>">
							<?php 
							echo esc_textarea( 
								$settings['ai_title_prompt'] ?? 'Generate an SEO-optimized title tag for this content. Requirements: 1) Maximum 60 characters, 2) Include primary keyword naturally, 3) Be compelling and click-worthy, 4) Accurately reflect the content, 5) Use title case, 6) Avoid keyword stuffing. Return ONLY the title text, nothing else.' 
							);
							?>
						</textarea>
						<p class="description">
							<?php esc_html_e( 'Instructions for AI when generating meta titles. Use {{BRAND}} to include your brand name.', 'bulk-yoast-meta-updater' ); ?>
						</p>
					</td>
				</tr>

						<tr>
							<th scope="row">
								<label for="ai_description_prompt"><?php esc_html_e( 'Meta Description Prompt', 'bulk-yoast-meta-updater' ); ?></label>
							</th>
							<td>
								<textarea name="ai_description_prompt" id="ai_description_prompt" 
									rows="10" class="large-text bymu-ai-prompt" 
									placeholder="<?php esc_attr_e( 'Enter custom instructions for meta description generation...', 'bulk-yoast-meta-updater' ); ?>">
									<?php 
									echo esc_textarea( 
										$settings['ai_description_prompt'] ?? 'Generate an SEO-optimized meta description for this content. Requirements: 1) Maximum 155 characters, 2) Include primary keyword naturally, 3) Be compelling with clear call-to-action, 4) Accurately summarize the content, 5) Use active voice, 6) Avoid duplicate content. Return ONLY the meta description text, nothing else.' 
									);
									?>
								</textarea>
								<p class="description">
									<?php esc_html_e( 'Instructions for AI when generating meta descriptions. Best practices: specify 155 character limit, include CTA, active voice.', 'bulk-yoast-meta-updater' ); ?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="ai_keyphrase_prompt"><?php esc_html_e( 'Focus Keyphrase Prompt', 'bulk-yoast-meta-updater' ); ?></label>
							</th>
							<td>
								<textarea name="ai_keyphrase_prompt" id="ai_keyphrase_prompt" 
									rows="8" class="large-text bymu-ai-prompt" 
									placeholder="<?php esc_attr_e( 'Enter custom instructions for keyphrase generation...', 'bulk-yoast-meta-updater' ); ?>">
									<?php 
									echo esc_textarea( 
										$settings['ai_keyphrase_prompt'] ?? 'Identify the primary focus keyphrase for this content. Requirements: 1) 1-4 words maximum, 2) Match user search intent, 3) Appear naturally in the content, 4) High search relevance, 5) Avoid generic terms. Return ONLY the keyphrase, nothing else.' 
									);
									?>
								</textarea>
								<p class="description">
									<?php esc_html_e( 'Instructions for AI when identifying focus keyphrases. Best practices: 1-4 words, search intent match, content relevance.', 'bulk-yoast-meta-updater' ); ?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="ai_image_alt_prompt"><?php esc_html_e( 'Image Alt Text Prompt', 'bulk-yoast-meta-updater' ); ?></label>
							</th>
							<td>
								<textarea name="ai_image_alt_prompt" id="ai_image_alt_prompt" 
									rows="10" class="large-text bymu-ai-prompt" 
									placeholder="<?php esc_attr_e( 'Enter custom instructions for image alt text generation...', 'bulk-yoast-meta-updater' ); ?>">
									<?php 
									echo esc_textarea( 
										$settings['ai_image_alt_prompt'] ?? 'Please provide a functional, objective description of the provided image in no more than around 15 words so that someone who could not see it would be able to imagine it. If possible, follow an "object-action-context" framework: The object is the main focus. The action describes what\'s happening, usually what the object is doing. The context describes the surrounding environment. If there is no text found in the image, then there is no need to mention it. You should not begin the description with any variation of "The image". Include the website name as a brand and connect the topic to the brand in a relevant way. Return ONLY the alt text, nothing else.' 
									);
									?>
								</textarea>
								<p class="description">
									<?php esc_html_e( 'Instructions for AI when generating image alt text. Best practices: ~15 words, object-action-context framework, accessibility-focused.', 'bulk-yoast-meta-updater' ); ?>
								</p>
							</td>
						</tr>

						<!-- Performance Settings -->
						<tr>
							<th colspan="2">
								<h2><?php esc_html_e( 'Performance Settings', 'bulk-yoast-meta-updater' ); ?></h2>
							</th>
						</tr>

						<tr>
							<th scope="row">
								<?php esc_html_e( 'Throttle Delay', 'bulk-yoast-meta-updater' ); ?>
							</th>
							<td>
								<p class="description">
									<?php esc_html_e( 'Fixed at 180 ms between updates to respect host and API limits.', 'bulk-yoast-meta-updater' ); ?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<?php esc_html_e( 'WP-CLI Recommendations', 'bulk-yoast-meta-updater' ); ?>
							</th>
							<td>
								<label>
									<input type="checkbox" name="enable_cli_notices" value="1" 
										<?php checked( $settings['enable_cli_notices'], true ); ?> />
									<?php esc_html_e( 'Show WP-CLI recommendations for large CSVs', 'bulk-yoast-meta-updater' ); ?>
								</label>
							</td>
						</tr>
					</tbody>
				</table>

				<p class="submit" style="text-align: center; margin-top: 30px;">
					<button type="submit" name="bymu_save_settings" class="button button-primary bymu-save-settings-btn">
						<span class="dashicons dashicons-cloud-saved" aria-hidden="true"></span>
						<span class="bymu-save-settings-label"><?php esc_html_e( 'Save Settings', 'bulk-yoast-meta-updater' ); ?></span>
					</button>
				</p>
			</form>
				</div>
			</div>

			<!-- Maintenance Section -->
			<div class="bymu-section">
				<div class="bymu-section-header">
					<h2>🔧 <?php esc_html_e( 'Maintenance', 'bulk-yoast-meta-updater' ); ?></h2>
					<p><?php esc_html_e( 'Database statistics and maintenance tools.', 'bulk-yoast-meta-updater' ); ?></p>
				</div>
				<div class="bymu-section-body">
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Database Statistics', 'bulk-yoast-meta-updater' ); ?></th>
					<td>
						<?php
						printf(
							/* translators: 1: Job count, 2: Action count */
							esc_html__( 'Total Jobs: %1$d | Total Actions Logged: %2$d', 'bulk-yoast-meta-updater' ),
							absint( $stats['total_jobs'] ),
							esc_html( number_format_i18n( $stats['total_actions'] ) ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- number_format_i18n returns a string that is already safe for HTML output.
						);
						?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Clear Old Logs', 'bulk-yoast-meta-updater' ); ?></th>
					<td>
						<button type="button" id="bymu-clear-logs" class="button">
							<?php esc_html_e( 'Delete Old Logs', 'bulk-yoast-meta-updater' ); ?>
						</button>
						<p class="description">
							<?php esc_html_e( 'Removes jobs older than retention setting.', 'bulk-yoast-meta-updater' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Optimize Database', 'bulk-yoast-meta-updater' ); ?></th>
					<td>
						<button type="button" id="bymu-optimize-db" class="button">
							<?php esc_html_e( 'Optimize Tables', 'bulk-yoast-meta-updater' ); ?>
						</button>
						<p class="description">
							<?php esc_html_e( 'Optimize plugin database tables for better performance.', 'bulk-yoast-meta-updater' ); ?>
						</p>
					</td>
				</tr>
			</table>
				</div>
			</div>

			<?php elseif ( 'documentation' === $current_tab ) : ?>
			<!-- Documentation Tab -->
			<div class="bymu-section bymu-section-compact">
				<div class="bymu-section-body">
					<?php $this->render_documentation(); ?>
				</div>
			</div>

			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render documentation content.
	 */
	private function render_documentation() {
		$readme_file = BYMU_PLUGIN_DIR . 'README.md';
		
		if ( ! file_exists( $readme_file ) ) {
			echo '<div class="bymu-alert error">';
			echo '<strong>' . esc_html__( 'Documentation Not Found', 'bulk-yoast-meta-updater' ) . '</strong>';
			echo '<p>' . esc_html__( 'The README.md file could not be found.', 'bulk-yoast-meta-updater' ) . '</p>';
			echo '</div>';
			return;
		}
		
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$readme_content = file_get_contents( $readme_file );
		
		if ( false === $readme_content ) {
			echo '<div class="bymu-alert error">';
			echo '<strong>' . esc_html__( 'Error Loading Documentation', 'bulk-yoast-meta-updater' ) . '</strong>';
			echo '<p>' . esc_html__( 'Could not read the README.md file.', 'bulk-yoast-meta-updater' ) . '</p>';
			echo '</div>';
			return;
		}

		// Generate TOC and convert Markdown to HTML.
		$toc  = $this->extract_toc( $readme_content );
		$html = $this->markdown_to_html( $readme_content );
		
		// Create two-column layout with sticky TOC on right.
		echo '<div class="bymu-doc-layout">';
		
		// Main content on left.
		echo '<div class="bymu-doc-content">';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $html;
		echo '</div>';
		
		// TOC on right (sticky).
		if ( ! empty( $toc ) ) {
			echo '<aside class="bymu-doc-sidebar">';
			echo '<div class="bymu-doc-toc">';
			echo '<h3 class="bymu-doc-toc-title">📑 ' . esc_html__( 'Table of Contents', 'bulk-yoast-meta-updater' ) . '</h3>';
			echo '<nav class="bymu-doc-toc-nav">';
			echo $toc; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '</nav>';
			echo '</div>';
			echo '</aside>';
		}
		
		echo '</div>'; // End layout.
	}

	/**
	 * Extract table of contents from markdown.
	 *
	 * @param string $markdown Markdown content.
	 * @return string HTML TOC.
	 */
	private function extract_toc( $markdown ) {
		$toc       = '<ul class="bymu-doc-toc-list">';
		$lines     = explode( "\n", $markdown );
		$has_items = false;
		
		foreach ( $lines as $line ) {
			// Match only H2 headers (main sections).
			if ( preg_match( '/^## (.+)$/m', $line, $matches ) ) {
				$title     = trim( $matches[1] );
				$id        = sanitize_title( $title );
				$toc      .= '<li><a href="#' . esc_attr( $id ) . '">' . esc_html( $title ) . '</a></li>';
				$has_items = true;
			}
		}
		
		$toc .= '</ul>';
		
		return $has_items ? $toc : '';
	}

	/**
	 * Convert Markdown to HTML (enhanced implementation).
	 *
	 * @param string $markdown Markdown content.
	 * @return string HTML content.
	 */
	private function markdown_to_html( $markdown ) {
		// Protect code blocks first.
		$code_blocks = [];
		$markdown    = preg_replace_callback(
			'/```([a-z]*)\n(.+?)\n```/s',
			function ( $matches ) use ( &$code_blocks ) {
				$index                 = count( $code_blocks );
				$code_blocks[ $index ] = '<pre><code class="language-' . esc_attr( $matches[1] ) . '">' . esc_html( $matches[2] ) . '</code></pre>';
				return '{{CODE_BLOCK_' . $index . '}}';
			},
			$markdown
		);
		
		// Convert headers with IDs for anchor links.
		$markdown = preg_replace_callback(
			'/^### (.+)$/m',
			function ( $matches ) {
				$title = trim( $matches[1] );
				$id    = sanitize_title( $title );
				return '<h3 id="' . esc_attr( $id ) . '">' . esc_html( $title ) . '</h3>';
			},
			$markdown
		);
		
		$markdown = preg_replace_callback(
			'/^## (.+)$/m',
			function ( $matches ) {
				$title = trim( $matches[1] );
				$id    = sanitize_title( $title );
				return '<h2 id="' . esc_attr( $id ) . '">' . esc_html( $title ) . '</h2>';
			},
			$markdown
		);
		
		$markdown = preg_replace_callback(
			'/^# (.+)$/m',
			function ( $matches ) {
				$title = trim( $matches[1] );
				return '<h1>' . esc_html( $title ) . '</h1>';
			},
			$markdown
		);
		
		// Convert bold.
		$markdown = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $markdown );
		
		// Convert italic (but not inside bold).
		$markdown = preg_replace( '/(?<!\*)\*([^\*]+?)\*(?!\*)/', '<em>$1</em>', $markdown );
		
		// Convert inline code.
		$markdown = preg_replace( '/`([^`]+?)`/', '<code>$1</code>', $markdown );
		
		// Convert links.
		$markdown = preg_replace_callback(
			'/\[([^\]]+?)\]\(([^\)]+?)\)/',
			function ( $matches ) {
				return '<a href="' . esc_url( $matches[2] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $matches[1] ) . '</a>';
			},
			$markdown
		);
		
		// Convert unordered lists (improved).
		$markdown = preg_replace( '/^[\*\-\+] (.+)$/m', '<li>$1</li>', $markdown );
		$markdown = preg_replace( '/(<li>.*?<\/li>\s*)+/s', '<ul>$0</ul>', $markdown );
		
		// Convert ordered lists.
		$markdown = preg_replace( '/^\d+\. (.+)$/m', '<li>$1</li>', $markdown );
		$markdown = preg_replace( '/(<li>.*?<\/li>\s*)+/s', '<ol>$0</ol>', $markdown );
		
		// Convert tables (basic).
		$lines    = explode( "\n", $markdown );
		$in_table = false;
		$html     = '';
		
		foreach ( $lines as $line ) {
			// Table header detection.
			if ( preg_match( '/^\|(.+)\|$/', $line ) && ! $in_table ) {
				$in_table = true;
				$html    .= '<table class="bymu-doc-table">';
				$html    .= '<thead><tr>';
				$cells    = explode( '|', trim( $line, '|' ) );
				foreach ( $cells as $cell ) {
					$html .= '<th>' . trim( $cell ) . '</th>';
				}
				$html .= '</tr></thead><tbody>';
				continue;
			}
			
			// Skip separator row.
			if ( preg_match( '/^\|[\s\-:]+\|$/', $line ) && $in_table ) {
				continue;
			}
			
			// Table row.
			if ( preg_match( '/^\|(.+)\|$/', $line ) && $in_table ) {
				$html .= '<tr>';
				$cells = explode( '|', trim( $line, '|' ) );
				foreach ( $cells as $cell ) {
					$html .= '<td>' . trim( $cell ) . '</td>';
				}
				$html .= '</tr>';
				continue;
			}
			
			// End table.
			if ( $in_table && ! preg_match( '/^\|/', $line ) ) {
				$html    .= '</tbody></table>';
				$in_table = false;
			}
			
			$html .= $line . "\n";
		}
		
		if ( $in_table ) {
			$html .= '</tbody></table>';
		}
		
		// Convert horizontal rules.
		$html = is_string( $html ) ? $html : '';
		$html = preg_replace( '/^---+$/m', '<hr>', $html );
		
		// Convert line breaks to paragraphs.
		$html = '<p>' . preg_replace( '/\n\n+/', '</p><p>', $html ) . '</p>';
		$html = str_replace( '<p></p>', '', (string) $html );
		
		// Clean up paragraph tags around block elements.
		$html = preg_replace( '/<p>(<h[1-6][^>]*>.*?<\/h[1-6]>)<\/p>/', '$1', $html );
		$html = preg_replace( '/<p>(<table.*?<\/table>)<\/p>/s', '$1', $html );
		$html = preg_replace( '/<p>(<ul>.*?<\/ul>)<\/p>/s', '$1', $html );
		$html = preg_replace( '/<p>(<ol>.*?<\/ol>)<\/p>/s', '$1', $html );
		$html = preg_replace( '/<p>(<pre>.*?<\/pre>)<\/p>/s', '$1', $html );
		$html = preg_replace( '/<p>(<hr>)<\/p>/', '$1', $html );
		$html = preg_replace( '/<p>({{CODE_BLOCK_\d+}})<\/p>/', '$1', $html );
		
		// Restore code blocks.
		foreach ( $code_blocks as $index => $block ) {
			$html = str_replace( '{{CODE_BLOCK_' . $index . '}}', (string) $block, (string) $html );
		}
		
		return $html;
	}

	/**
	 * Save settings.
	 */
	private function save_settings() {
		// Verify nonce.
		if ( ! isset( $_POST['bymu_settings_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bymu_settings_nonce'] ) ), 'bymu_settings' ) ) {
			add_settings_error( 'bymu_settings', 'nonce_failed', __( 'Security check failed.', 'bulk-yoast-meta-updater' ) );
			return;
		}

		$current_settings = bymu_get_settings();

		// Sanitize and validate settings.
			$new_settings = [
				'batch_size'            => 15,
				'log_retention'         => isset( $_POST['log_retention'] ) ? absint( $_POST['log_retention'] ) : 10,
				'max_upload_size_mb'    => 4,
				'title_warning_chars'   => isset( $_POST['title_warning_chars'] ) ? absint( $_POST['title_warning_chars'] ) : 60,
				'desc_warning_chars'    => isset( $_POST['desc_warning_chars'] ) ? absint( $_POST['desc_warning_chars'] ) : 160,
				'keyword_warning_chars' => isset( $_POST['keyword_warning_chars'] ) ? absint( $_POST['keyword_warning_chars'] ) : 100,
				'url_mode'              => isset( $_POST['url_mode'] ) && 'lenient' === $_POST['url_mode'] ? 'lenient' : 'strict',
				'throttle_delay_ms'     => 180,
				'enable_cli_notices'    => isset( $_POST['enable_cli_notices'] ),
				// AI Generation settings.
				'gemini_api_key'        => isset( $_POST['gemini_api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['gemini_api_key'] ) ) : '',
				'gemini_text_model'     => isset( $_POST['gemini_text_model'] ) ? sanitize_text_field( wp_unslash( $_POST['gemini_text_model'] ) ) : 'gemini-2.5-flash',
				'gemini_image_model'    => isset( $_POST['gemini_image_model'] ) ? sanitize_text_field( wp_unslash( $_POST['gemini_image_model'] ) ) : 'gemini-2.5-flash-image',
				'ai_brand_name'         => isset( $_POST['ai_brand_name'] ) ? sanitize_text_field( wp_unslash( $_POST['ai_brand_name'] ) ) : get_bloginfo( 'name' ),
				'ai_title_prompt'       => isset( $_POST['ai_title_prompt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ai_title_prompt'] ) ) : '',
				'ai_description_prompt' => isset( $_POST['ai_description_prompt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ai_description_prompt'] ) ) : '',
				'ai_keyphrase_prompt'   => isset( $_POST['ai_keyphrase_prompt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ai_keyphrase_prompt'] ) ) : '',
				'ai_image_alt_prompt'   => isset( $_POST['ai_image_alt_prompt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ai_image_alt_prompt'] ) ) : '',
			];

			// phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.defaultFound -- Parameter name is descriptive and clear in context.
			$sanitize_model = static function ( $model, $default ) {
				$model = strtolower( trim( $model ) );
				$model = preg_replace( '/[^a-z0-9\-\._]/', '', $model );
				return $model ? $model : $default;
			};

		$new_settings['gemini_text_model']  = $sanitize_model( $new_settings['gemini_text_model'], 'gemini-2.5-flash' );
		$new_settings['gemini_image_model'] = $sanitize_model( $new_settings['gemini_image_model'], 'gemini-2.5-flash-image' );

		// Validate ranges.
		$new_settings['log_retention'] = max( 1, min( 100, $new_settings['log_retention'] ) );

		$api_changed = ( $current_settings['gemini_api_key'] ?? '' ) !== $new_settings['gemini_api_key'];

		// Update settings.
		bymu_update_settings( $new_settings );

		if ( $api_changed ) {
			delete_transient( 'bymu_gemini_models' );
		}

		// Show success message.
		add_settings_error(
			'bymu_settings',
			'settings_saved',
			__( 'Settings saved successfully.', 'bulk-yoast-meta-updater' ),
			'success'
		);
	}
}

