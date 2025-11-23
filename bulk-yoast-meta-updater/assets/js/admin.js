/**
 * Admin JavaScript for Bulk SEO Meta Updater
 */

(function($) {
	'use strict';

	const BYMU = {
		currentJobHash: null,
		currentFileName: null,
		totalRows: 0,
		batchSize: 50,
		postTypes: [],
		previewData: null,
		bulkGenerateActive: false,
		bulkGenerateJobId: null,
		bulkGenerateSequence: 0,
		bulkGenerateTotals: { success: 0, errors: 0, total: 0 },
		bulkAltRunnerActive: false,
		bulkAltQueue: [],
		bulkAltInFlight: 0,
		bulkAltTotals: { processed: 0, total: 0, success: 0, errors: 0 },
		bulkAltConcurrency: 1,
		bulkAltSyncActive: false,
		exportCounts: null,
		bulkGenerateStopRequested: false,
		/**
		 * Initialize
		 */
		init: function() {
			if (typeof window.bymuAdmin === 'undefined') {
				if (window.console && typeof window.console.warn === 'function') {
					window.console.warn('[Bulk SEO Meta] Skipping admin.js init: window.bymuAdmin is undefined.');
				}
				return;
			}
			this.bindEvents();
			this.initDocumentation();
			this.initExportEstimate();
			this.updateInlineSaveControls();
			this.loadAttachmentReferences();
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function() {
			// Test button
			this.bindEvent('#bymu-test-btn', 'click', 'handleTestSEO');
			
			// Export button
			this.bindEvent('#bymu-export-btn', 'click', 'handleExport');
			
			// AI generation
			this.bindEvent('#bymu-ai-load-posts', 'click', 'handleLoadAIPosts');
			// Allow Enter key in "Number of Posts" field to trigger Load Posts
			$('#bymu-ai-limit').on('keypress', function(e) {
				if (e.which === 13 || e.keyCode === 13) { // Enter key
					e.preventDefault();
					$('#bymu-ai-load-posts').trigger('click');
				}
			});
			this.bindEvent(document, 'click', 'handleAIGenerate', '.bymu-ai-generate-btn');
			this.bindEvent(document, 'click', 'handleAISave', '.bymu-ai-save-btn');
			this.bindEvent('#bymu-ai-save-all', 'click', 'handleAISaveAll');
			this.bindEvent('#bymu-ai-generate-all', 'click', 'handleAIGenerateAll');
			this.bindEvent('#bymu-ai-stop-bulk', 'click', 'handleAIGenerateStop');
			this.bindEvent(document, 'click', 'handleSyncAlt', '.bymu-sync-alt-btn');
			this.bindEvent(document, 'click', 'handleModalGenerateAlt', '.bymu-inline-generate-alt-btn');
			this.bindEvent(document, 'click', 'handleInlineSaveAlt', '.bymu-inline-save-alt');
			this.bindEvent(document, 'click', 'handleInlineSaveAll', '#bymu-inline-save-all');
			this.bindEvent(document, 'click', 'handleInlineCancelSave', '#bymu-inline-cancel-save');
			this.bindEvent(document, 'click', 'handleInlineCancelSave', '.bymu-inline-cancel-alt');
			this.bindEvent('#bymu-bulk-generate-alt', 'click', 'handleBulkAltGenerate');
			this.bindEvent('#bymu-bulk-sync-alt', 'click', 'handleBulkAltSync');
			
			// Image alt text generation
			this.bindEvent(document, 'click', 'handleGenerateImageAlt', '.bymu-media-generate-alt-btn');
			
			// Uninstall from Plugins page
			this.bindEvent(document, 'click', 'handleUninstallFromPlugins', '.bymu-uninstall-link');
			
			// Settings page - Toggle API key visibility
			this.bindEvent('#bymu-toggle-api-key', 'click', 'toggleAPIKey');
			
			// Settings page - B2B Title Pattern loader
			this.bindEvent('#bymu-load-title-pattern', 'click', 'loadTitlePattern');
			
			if ($('#bymu-refresh-gemini-models').length) {
				this.populateModelSelects(bymuAdmin.models || {});
				this.bindEvent('#bymu-refresh-gemini-models', 'click', 'handleRefreshGeminiModels');
				$('#gemini_text_model, #gemini_image_model').on('change', function() {
					$(this).data('current', $(this).val());
				});
			}
			
		// CSV Upload - Drag and Drop
		this.initDragDrop();
		// Browse button click handler (primary method)
		$('#bymu-browse-btn').on('click', (e) => {
			e.preventDefault();
			e.stopPropagation();
			$('#csv_file')[0].click(); // Use native click to avoid jQuery event bubbling
		});
		// Prevent file input clicks from bubbling to avoid recursion
		$('#csv_file').on('click', (e) => {
			e.stopPropagation();
		});
		// File selection handler
		this.bindEvent('#csv_file', 'change', 'handleFileSelect');
		// Remove file handler
		this.bindEvent('#bymu-remove-file', 'click', 'handleFileRemove');
			
		// Upload form
		this.bindEvent('#bymu-upload-form', 'submit', 'handleUpload');
		
		// Preview actions (using event delegation for dynamically created buttons)
		this.bindEvent(document, 'click', 'handleApply', '#bymu-apply-btn');
		this.bindEvent(document, 'click', 'handleCancel', '#bymu-cancel-btn');
			
			// Result actions
			this.bindEvent('#bymu-new-job-btn', 'click', 'handleNewJob');
			$('#bymu-download-csv-btn').on('click', (e) => this.handleDownloadLog(e, 'csv'));
			$('#bymu-download-txt-btn').on('click', (e) => this.handleDownloadLog(e, 'txt'));
			
			// Recent jobs actions
			this.bindEvent(document, 'click', 'handleViewLog', '.bymu-view-log-btn');
			this.bindEvent(document, 'click', 'handleRecentJobDownload', '.bymu-download-log-btn');
			
			// Modal close
			this.bindEvent(document, 'click', 'closeModal', '.bymu-modal-close');
			this.bindEvent(document, 'click', 'closeModal', '.bymu-modal-overlay');
			
			// Settings page maintenance buttons
			this.bindEvent('#bymu-clear-logs', 'click', 'handleClearLogs');
			this.bindEvent('#bymu-optimize-db', 'click', 'handleOptimizeDB');
			
			// ESC key to close modal
			$(document).on('keydown', function(e) {
				if (e.key === 'Escape' && $('#bymu-log-modal').is(':visible')) {
					this.closeModal();
				}
			}.bind(this));
		},

		/**
		 * Set global status for Image Alt Text bulk operations.
		 * @param {string} text
		 * @param {'info'|'success'|'error'} variant
		 */
		setBulkAltStatus: function(text, variant = 'info') {
			const box = $('#bymu-bulk-alt-status');
			if (!box.length) return;
			const color =
				variant === 'success' ? '#00a32a' :
				variant === 'error' ? '#d63638' : '#1e293b';
			box.html('<span style="color:' + color + ';">' + this.escapeHtml(text) + '</span>');
		},

		/**
		 * Developer-friendly console logger for tracing runtime actions.
		 */
		log: function() {
			if (typeof window !== 'undefined' && window.bymuDebug === false) {
				return;
			}

			if (typeof console === 'undefined' || typeof console.log !== 'function') {
				return;
			}

			const args = Array.prototype.slice.call(arguments);
			args.unshift('%c[Bulk SEO Meta]', 'color:#2271b1;font-weight:bold;');
			console.log.apply(console, args);
		},

		/**
		 * Safely retrieve and bind a handler on the BYMU object.
		 *
		 * @param {string} methodName Method name on BYMU.
		 * @return {Function|null} Bound function or null when missing.
		 */
		getBoundHandler: function(methodName) {
			const fn = this[methodName];
			if (typeof fn === 'function') {
				return fn.bind(this);
			}
			if (window.console && typeof window.console.warn === 'function') {
				window.console.warn('[Bulk SEO Meta] Missing handler:', methodName);
			}
			return null;
		},

		/**
		 * Utility to attach events only when the handler exists.
		 *
		 * @param {jQuery|string|Document|Window} target Target selector or element.
		 * @param {string} eventName Event name(s).
		 * @param {string} handlerName Method name on BYMU to invoke.
		 * @param {string} [delegateSelector] Optional delegate selector.
		 */
		bindEvent: function(target, eventName, handlerName, delegateSelector) {
			const handler = this.getBoundHandler(handlerName);
			if (!handler) {
				return;
			}
			const $target = target instanceof jQuery ? target : $(target);
			if (!$target.length && !delegateSelector) {
				return;
			}

			if (delegateSelector) {
				$target.on(eventName, delegateSelector, handler);
			} else {
				$target.on(eventName, handler);
			}
		},

		/**
		 * Handle test SEO updates
		 */
		handleTestSEO: function() {
			// Get selected options
			const postTypes = $('#test_post_types').val() || [];
			const limit = parseInt($('#test_limit').val()) || 5;

			// Validate
			if (postTypes.length === 0) {
				alert('Please select at least one post type to test.');
				return;
			}

			// Confirm action
			if (!confirm('WARNING: This will overwrite existing Yoast SEO data with test data.\n\n' +
				'Apply test SEO data to ' + limit + ' posts?\n\n' +
				'Use this only on test posts.')) {
				return;
			}

			// Show loading
			$('#bymu-test-loading').addClass('is-active');
			$('#bymu-test-btn').prop('disabled', true);
			$('#bymu-test-status').html('<span style="color: #2271b1;">Applying test data...</span>');

			// AJAX request
			$.ajax({
				url: bymuAdmin.ajaxurl,
				type: 'POST',
				data: {
					action: 'bymu_test_seo_updates',
					nonce: bymuAdmin.nonces.test_seo,
					post_types: postTypes,
					limit: limit
				},
				success: (response) => {
					$('#bymu-test-loading').removeClass('is-active');
					$('#bymu-test-btn').prop('disabled', false);

					if (response.success) {
						let statusHTML = '<span style="color: #00a32a; font-weight: 600;">✓ ' + this.escapeHtml(response.data.message) + '</span>';
						
						// Show post IDs that were updated
						if (response.data.post_ids && response.data.post_ids.length > 0) {
							statusHTML += '<br><small style="color: var(--bymu-text-secondary);">Updated Post IDs: ' + 
								response.data.post_ids.join(', ') + '</small>';
						}
						
						// Add view log button if job was created
						if (response.data.job_id) {
							statusHTML += '<br><button type="button" class="button button-small bymu-view-log-btn" ' +
								'data-job-id="' + response.data.job_id + '" ' +
								'style="margin-top: 8px;">View Test Logs</button>';
						}
						
						// Show errors if any
						if (response.data.errors && response.data.errors.length > 0) {
							statusHTML += '<br><span style="color: #d63638; font-size: 13px;">Errors: ' + 
								response.data.errors.map(e => this.escapeHtml(e)).join(', ') + 
								'</span>';
						}
						
						$('#bymu-test-status').html(statusHTML);
						
						// Scroll to Recent Jobs section to see the new entry
						if (response.data.job_id) {
							setTimeout(() => {
								$('html, body').animate({
									scrollTop: $('.bymu-section:has(h2:contains("Recent Jobs"))').offset().top - 32
								}, 500);
							}, 1000);
							
							// Reload page after 5 seconds to show updated Recent Jobs table
							setTimeout(() => {
								location.reload();
							}, 5000);
						}
					} else {
						$('#bymu-test-status').html('<span style="color: #d63638;">✗ ' + 
							this.escapeHtml(response.data) + '</span>');
					}
				},
				error: (xhr, status, error) => {
					$('#bymu-test-loading').removeClass('is-active');
					$('#bymu-test-btn').prop('disabled', false);
					$('#bymu-test-status').html('<span style="color: #d63638;">✗ AJAX error: ' + error + '</span>');
					
					setTimeout(() => {
						$('#bymu-test-status').html('');
					}, 10000);
				}
			});
		},

	/**
	 * Toggle API key visibility
	 */
	toggleAPIKey: function() {
		const input = $('#gemini_api_key');
		const btn = $('#bymu-toggle-api-key');
		
		if (input.attr('type') === 'password') {
			input.attr('type', 'text');
			btn.text('Hide');
		} else {
			input.attr('type', 'password');
			btn.text('Show');
		}
	},

	/**
	 * Load B2B title pattern into textarea
	 */
	loadTitlePattern: function() {
		const pattern = $('#bymu-title-pattern').val();
		
		if (!pattern) {
			alert('Please select a pattern from the dropdown.');
			return;
		}
		
		// B2B Title Prompt Patterns
		const patterns = {
			'solution-focused': `Generate a solution-focused B2B title for {{BRAND}}. Requirements:
1) Maximum 60 characters
2) Format: [Problem Solved] | [Solution] by {{BRAND}}
3) Lead with the business problem or pain point
4) Include the solution or outcome
5) Use professional, results-oriented language
6) Focus on business value, not features
7) Include primary keyword naturally
8) Use title case
9) Return ONLY the title text, no quotes or formatting`,

			'roi-driven': `Generate an ROI-focused B2B title for {{BRAND}}. Requirements:
1) Maximum 60 characters
2) Format: [Outcome/Result] - [How] | {{BRAND}}
3) Start with measurable business outcomes (save time, reduce costs, increase revenue)
4) Emphasize tangible benefits and ROI
5) Use action words and metrics when possible
6) Include decision-maker keywords
7) Professional, executive tone
8) Use title case
9) Return ONLY the title text, no quotes or formatting`,

			'thought-leadership': `Generate a thought leadership B2B title for {{BRAND}}. Requirements:
1) Maximum 60 characters
2) Format: [Insight/Trend] - [Expertise] | {{BRAND}}
3) Position content as industry expertise or unique insight
4) Use authoritative, educational tone
5) Include industry-specific terminology
6) Focus on strategic value, not tactics
7) Establish {{BRAND}} as thought leader
8) Use title case
9) Return ONLY the title text, no quotes or formatting`,

			'feature-benefit': `Generate a feature-to-benefit B2B title for {{BRAND}}. Requirements:
1) Maximum 60 characters
2) Format: [Key Feature] for [Business Benefit] | {{BRAND}}
3) Start with the most important product/service feature
4) Connect it directly to business benefit
5) Answer "so what?" for decision-makers
6) Use professional, benefit-driven language
7) Include B2B keywords naturally
8) Use title case
9) Return ONLY the title text, no quotes or formatting`,

			'industry-specific': `Generate an industry-specific B2B title for {{BRAND}}. Requirements:
1) Maximum 60 characters
2) Format: [Solution] for [Industry/Vertical] | {{BRAND}}
3) Include the target industry or vertical prominently
4) Use industry-specific terminology and keywords
5) Address industry-specific challenges or needs
6) Show specialization and expertise
7) Professional, sector-appropriate tone
8) Use title case
9) Return ONLY the title text, no quotes or formatting`,

			'enterprise': `Generate an enterprise-focused B2B title for {{BRAND}}. Requirements:
1) Maximum 60 characters
2) Format: [Enterprise Solution] - [Scale/Security/Compliance]
3) Emphasize enterprise capabilities (scale, security, integration)
4) Include enterprise-level concerns (compliance, governance, support)
5) Use enterprise-appropriate keywords
6) Professional, technical credibility
7) Focus on enterprise buyer needs
8) Use title case
9) Return ONLY the title text, no quotes or formatting`,

			'comparison': `Generate a comparison/alternative B2B title for {{BRAND}}. Requirements:
1) Maximum 60 characters
2) Format: [Your Solution] vs [Competitor/Alternative] | {{BRAND}}
3) Position as alternative to competitor or traditional approach
4) Highlight differentiation without being negative
5) Focus on unique value proposition
6) Use competitive, confident tone
7) Include comparison keywords naturally
8) Use title case
9) Return ONLY the title text, no quotes or formatting`,

			'case-study': `Generate a case study B2B title for {{BRAND}}. Requirements:
1) Maximum 60 characters
2) Format: How [Company] [Achieved Result] with {{BRAND}}
3) Include specific results or outcomes (numbers if available)
4) Show proof and credibility through real examples
5) Use social proof and success story framing
6) Professional, evidence-based tone
7) Include relevant industry or company size
8) Use title case
9) Return ONLY the title text, no quotes or formatting`
		};
		
		const promptText = patterns[pattern];
		
		if (promptText) {
			if ($('#ai_title_prompt').val() && !confirm('This will replace your current prompt. Continue?')) {
				return;
			}
			$('#ai_title_prompt').val(promptText).trigger('change');
			
			// Visual feedback
			$('#ai_title_prompt').css('background-color', '#d4edda');
			setTimeout(() => {
				$('#ai_title_prompt').css('background-color', '');
			}, 1000);
			
			// Reset dropdown
			$('#bymu-title-pattern').val('');
		}
	},

	/**
	 * Initialize drag and drop for CSV upload
	 */
		initDragDrop: function() {
			const dropZone = $('#bymu-drop-zone');
			
			if (!dropZone.length) {
				return; // Not on import page
			}

			dropZone.on('dragover', (e) => {
				e.preventDefault();
				e.stopPropagation();
				dropZone.addClass('dragging');
			});

			dropZone.on('dragleave', (e) => {
				e.preventDefault();
				e.stopPropagation();
				dropZone.removeClass('dragging');
			});

			dropZone.on('drop', (e) => {
				e.preventDefault();
				e.stopPropagation();
				dropZone.removeClass('dragging');

				const files = e.originalEvent.dataTransfer.files;
				
				if (files.length > 0) {
					const file = files[0];
					
					// Validate file type
					if (!file.name.endsWith('.csv')) {
						alert('Please upload a CSV file.');
						return;
					}
					
					// Set file to input
					const input = $('#csv_file')[0];
					const dataTransfer = new DataTransfer();
					dataTransfer.items.add(file);
					input.files = dataTransfer.files;
					
					// Trigger change event
					$('#csv_file').trigger('change');
				}
			});
		},

		/**
		 * Handle file selection
		 */
		handleFileSelect: function(e) {
			const input = e.target;
			const file = input.files[0];
			
			if (file) {
				// Show file info
				$('#bymu-selected-file').text(file.name);
				$('#bymu-file-size').text('(' + this.formatFileSize(file.size) + ')');
				$('#bymu-file-info').show();
				$('#bymu-drop-zone').hide();
				
				// Enable parse button
				$('#bymu-parse-btn').prop('disabled', false);
			}
		},

		/**
		 * Handle file removal
		 */
		handleFileRemove: function() {
			$('#csv_file').val('');
			$('#bymu-file-info').hide();
			$('#bymu-drop-zone').show();
			$('#bymu-parse-btn').prop('disabled', true);
		},

		/**
		 * Format file size
		 */
		formatFileSize: function(bytes) {
			if (bytes === 0) return '0 Bytes';
			const k = 1024;
			const sizes = ['Bytes', 'KB', 'MB'];
			const i = Math.floor(Math.log(bytes) / Math.log(k));
			return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
		},

		/**
		 * Handle load AI posts
		 */
		handleLoadAIPosts: function() {
			const postTypes = $('#bymu-ai-post-types').val() || [];
			const limit = parseInt($('#bymu-ai-limit').val()) || 20;
			const blankOnly = $('#bymu-ai-blank-only').is(':checked') ? '1' : '0';

			if (postTypes.length === 0) {
				alert('Please select at least one post type.');
				return;
			}

			const btn = $('#bymu-ai-load-posts');
			btn.prop('disabled', true).text('Loading…');
			this.log('AI Load Posts: requesting', { postTypes, limit, blankOnly });

			$.ajax({
				url: bymuAdmin.ajaxurl,
				type: 'POST',
				data: {
					action: 'bymu_load_ai_posts',
					nonce: bymuAdmin.nonces.ai_generate,
					post_types: postTypes,
					limit: limit,
					blank_only: blankOnly
				},
				success: (response) => {
					btn.prop('disabled', false).text('Load Posts');

					if (response.success) {
						this.log('AI Load Posts: success', { count: response.data.posts.length });
						this.renderAIPostsTable(response.data.posts);
						
						if (response.data.posts.length === 0) {
							const blankOnly = $('#bymu-ai-blank-only').is(':checked');
							const msg = blankOnly ? 
								'No posts found with short or blank meta descriptions.' : 
								'No posts found matching the selected criteria.';
							$('#bymu-ai-posts-container').html(`
								<div class="bymu-alert info">
									<div class="bymu-alert-icon dashicons dashicons-info"></div>
									<div class="bymu-alert-content">
										<p>${msg}</p>
									</div>
								</div>
							`);
						}

						const hasRows = response.data.posts.length > 0;
						$('#bymu-ai-generate-all')[hasRows ? 'show' : 'hide']();
					} else {
						this.log('AI Load Posts: server error', response);
						alert('Error: ' + response.data);
					}

					// Determine if "Generate All" button should be shown.
					const hasRows = $('#bymu-ai-posts-container').find('tbody tr').length > 0;
					if (hasRows) {
						$('#bymu-ai-generate-all').show();
					} else {
						$('#bymu-ai-generate-all').hide();
					}
				},
				error: (xhr, status, error) => {
					btn.prop('disabled', false).text('Load Posts');
					this.log('AI Load Posts: ajax error', { status, error, responseText: xhr.responseText });
					alert('AJAX error: ' + error);
				}
			});
		},

		/**
		 * Render AI posts table
		 */
		renderAIPostsTable: function(posts) {
			if (!posts || posts.length === 0) {
				$('#bymu-ai-posts-container').html('<p>No posts found.</p>');
				return;
			}

			let html = `
				<table class="wp-list-table widefat fixed striped bymu-ai-table">
					<thead>
						<tr>
							<th style="width: 250px;">Post Title</th>
							<th style="width: 200px;">Current Title</th>
							<th style="width: 200px;">Current Description</th>
							<th style="width: 120px;">Current Keyphrase</th>
							<th style="width: 200px;">AI Suggested Title</th>
							<th style="width: 200px;">AI Suggested Description</th>
							<th style="width: 120px;">AI Suggested Keyphrase</th>
							<th style="width: 150px;">Actions</th>
						</tr>
					</thead>
					<tbody>
			`;

			posts.forEach((post) => {
				html += `
					<tr class="bymu-ai-row" data-post-id="${post.id}">
						<td>
							<strong><a href="${post.url}" target="_blank">${this.escapeHtml(post.title)}</a></strong>
							<br><small class="bymu-text-muted">${post.type} | ID: ${post.id}</small>
						</td>
						<td class="bymu-current-title">
							<div class="bymu-text-truncate" title="${this.escapeHtml(post.meta_title || '')}">${this.escapeHtml(post.meta_title || '—')}</div>
						</td>
						<td class="bymu-current-desc">
							<div class="bymu-text-truncate" title="${this.escapeHtml(post.meta_desc || '')}">${this.escapeHtml(post.meta_desc || '—')}</div>
						</td>
						<td class="bymu-current-keyphrase">
							<div class="bymu-text-truncate" title="${this.escapeHtml(post.keyphrase || '')}">${this.escapeHtml(post.keyphrase || '—')}</div>
						</td>
						<td class="bymu-ai-title">
							<div class="bymu-ai-placeholder">—</div>
						</td>
						<td class="bymu-ai-desc">
							<div class="bymu-ai-placeholder">—</div>
						</td>
						<td class="bymu-ai-keyphrase">
							<div class="bymu-ai-placeholder">—</div>
						</td>
						<td class="bymu-ai-actions">
							<button type="button" class="button button-small bymu-ai-generate-btn" data-post-id="${post.id}">
								Generate
							</button>
							<button type="button" class="button button-small button-primary bymu-ai-save-btn" data-post-id="${post.id}" style="display: none;">
								Save
							</button>
							<div class="bymu-ai-status" style="margin-top:6px;">
								<div class="bymu-ai-placeholder">—</div>
							</div>
						</td>
					</tr>
				`;
			});

			html += '</tbody></table>';

			$('#bymu-ai-posts-container').html(html);
			$('#bymu-ai-save-all').show();
			$('#bymu-ai-generate-all').show();
		},

		/**
		 * Handle AI generate for single post
		 */
		handleAIGenerate: function(e) {
			const btn = $(e.currentTarget);
			const postId = btn.data('post-id');
			const row = btn.closest('tr');
			const bulkJobId = btn.data('bulk-job-id') ? parseInt(btn.data('bulk-job-id'), 10) : null;
			const bulkSequence = btn.data('bulk-seq') ? parseInt(btn.data('bulk-seq'), 10) : null;

			btn.prop('disabled', true).text('Generating…');
			row.find('.bymu-ai-status').html('<span style="color: #2271b1;">Generating…</span>');

			const requestData = {
				action: 'bymu_generate_ai',
				nonce: bymuAdmin.nonces.ai_generate,
				post_id: postId
			};

			if (bulkJobId) {
				requestData.job_id = bulkJobId;
			}

			if (bulkSequence) {
				requestData.sequence = bulkSequence;
			}

			this.log('AI Generate: sending request', { postId, bulkJobId, bulkSequence });

			$.ajax({
				url: bymuAdmin.ajaxurl,
				type: 'POST',
				data: requestData,
				success: (response) => {
					btn.prop('disabled', false).text('Generate');

					if (response.success) {
						// Update table with AI suggestions
						const title = response.data.title || '';
						const desc = response.data.description || '';
						const keyphrase = response.data.keyphrase || '';

						// Store in data attributes for later save
						row.data('ai-title', title);
						row.data('ai-desc', desc);
						row.data('ai-keyphrase', keyphrase);

						// Ensure row has reference class
						row.addClass('bymu-ai-row');

						// Display editable suggestions
						row.find('.bymu-ai-title').html(this.renderAIEditableField('title', title));
						row.find('.bymu-ai-desc').html(this.renderAIEditableField('desc', desc));
						row.find('.bymu-ai-keyphrase').html(this.renderAIEditableField('keyphrase', keyphrase));

						// Bind change handlers for live updates
						this.bindAIFieldEvents(row);

						// Show save button
						row.find('.bymu-ai-save-btn').show();
						
						// Add highlight
						row.addClass('bymu-has-suggestions');
						row.removeClass('bymu-saved');

						row.find('.bymu-ai-status').html('<span style="color: #00a32a;">✓ Suggestions ready</span>');
						row.trigger('bymu:aiGenerateSuccess');
						this.log('AI Generate: success', { postId, hasTitle: !!title, hasDesc: !!desc, hasKeyphrase: !!keyphrase });
					} else {
						const errorMsg = response.data && response.data.message ? response.data.message : response.data;
						const rawDetails = response.data && response.data.errors ? response.data.errors : null;
						const detailList = rawDetails ? Object.values(rawDetails) : null;
						const codes = response.data && Array.isArray(response.data.error_codes) ? response.data.error_codes : [];
						const statuses = response.data && Array.isArray(response.data.error_status) ? response.data.error_status : [];
						const hint = {
							rateLimit: Array.isArray(codes) && codes.includes('rate_limit'),
							overloaded: (Array.isArray(codes) && codes.includes('service_unavailable')) || (Array.isArray(statuses) && statuses.includes(503)),
							statuses: statuses
						};

						this.handleAIGenerateError(row, btn, errorMsg, detailList, null, this.bulkGenerateActive === true, hint);
						this.log('AI Generate: server error', { postId, response });
					}
				},
				error: (xhr, status, error) => {
					this.handleAIGenerateError(row, btn, error, null, xhr, this.bulkGenerateActive === true);
					this.log('AI Generate: ajax error', { postId, status, error, responseText: xhr && xhr.responseText });
					console.error('AI generation error:', xhr.responseText);
				}
			}).always(() => {
				btn.removeData('bulk-job-id')
					.removeData('bulk-seq')
					.removeData('bulk-total')
					.removeData('bulk-run');
			});
		},

		/**
		 * Render editable AI field markup.
		 */
		renderAIEditableField: function(field, value) {
			const safeValue = this.escapeHtml(value || '');
			const charCount = (value || '').length;
			const charCountHTML = `<small class="bymu-text-muted"><span class="bymu-ai-char-count" data-field="${field}">${charCount}</span> chars</small>`;

			if (field === 'desc') {
				return `
					<textarea class="bymu-ai-field" data-field="${field}" rows="4" style="width: 100%;">${safeValue}</textarea>
					${charCountHTML}
				`;
			}

			return `
				<input type="text" class="regular-text bymu-ai-field" data-field="${field}" value="${safeValue}" style="width: 100%;" />
				${charCountHTML}
			`;
		},

		/**
		 * Bind change handlers to AI editable fields for live updates.
		 */
		bindAIFieldEvents: function(row) {
			row.find('.bymu-ai-field').off('input').on('input', (event) => {
				const input = $(event.currentTarget);
				const field = input.data('field');
				const value = input.val();

				row.data('ai-' + field, value);

				const countEl = row.find(`.bymu-ai-char-count[data-field="${field}"]`);
				if (countEl.length) {
					countEl.text(value.length);
				}
			});
		},

		/**
		 * Convert AI editable fields to static text after save.
		 */
		finalizeAISuggestions: function(row, values) {
			const fields = {
				title: '.bymu-ai-title',
				desc: '.bymu-ai-desc',
				keyphrase: '.bymu-ai-keyphrase'
			};

			Object.keys(fields).forEach((field) => {
				const selector = fields[field];
				const value = values[field] || '';

				if (value) {
					row.find(selector).html(
						`<div class="bymu-ai-suggestion" title="${this.escapeHtml(value)}">${this.escapeHtml(value)}</div>
						<small class="bymu-text-muted">${value.length} chars</small>`
					);
				} else {
					row.find(selector).html('<div class="bymu-ai-placeholder">—</div>');
				}

				row.removeData('ai-' + field);
			});

			row.removeClass('bymu-has-suggestions');
		},

		/**
		 * Handle AI save for single post
		 */
		handleAISave: function(e) {
			const btn = $(e.currentTarget);
			const postId = btn.data('post-id');
			const row = btn.closest('tr');

			const titleInput = row.find('.bymu-ai-field[data-field="title"]');
			const descInput = row.find('.bymu-ai-field[data-field="desc"]');
			const keyInput = row.find('.bymu-ai-field[data-field="keyphrase"]');

			const title = titleInput.length ? titleInput.val().trim() : (row.data('ai-title') || '');
			const desc = descInput.length ? descInput.val().trim() : (row.data('ai-desc') || '');
			const keyphrase = keyInput.length ? keyInput.val().trim() : (row.data('ai-keyphrase') || '');

			row.data('ai-title', title);
			row.data('ai-desc', desc);
			row.data('ai-keyphrase', keyphrase);

			if (!title && !desc && !keyphrase) {
				alert('No AI suggestions to save. Please generate suggestions first.');
				return;
			}

			btn.prop('disabled', true).text('Saving…');
			this.log('AI Save: submitting', { postId, hasTitle: !!title, hasDesc: !!desc, hasKeyphrase: !!keyphrase });

			$.ajax({
				url: bymuAdmin.ajaxurl,
				type: 'POST',
				data: {
					action: 'bymu_save_ai',
					nonce: bymuAdmin.nonces.ai_generate,
					post_id: postId,
					title: title,
					description: desc,
					keyphrase: keyphrase
				},
				success: (response) => {
					if (response.success) {
						this.log('AI Save: success', { postId });
						btn.prop('disabled', false);
						// Show success feedback
						row.addClass('bymu-saved');
						btn.html('✓ Saved').addClass('button-primary');
						
						// Update current values
						if (title) row.find('.bymu-current-title div').text(title);
						if (desc) row.find('.bymu-current-desc div').text(desc);
						if (keyphrase) row.find('.bymu-current-keyphrase div').text(keyphrase);

						// Convert editable fields to static text
						this.finalizeAISuggestions(row, { title, desc, keyphrase });
						
						// Show temporary success message
						setTimeout(() => {
							btn.html('Save').removeClass('button-primary').hide();
						}, 2000);
						
					} else {
						this.log('AI Save: server error', { postId, response });
						btn.prop('disabled', false).html('Save');
						alert('Error saving: ' + response.data);
					}
				},
				error: (xhr, status, error) => {
					btn.prop('disabled', false).html('Save');
					this.log('AI Save: ajax error', { postId, status, error, responseText: xhr && xhr.responseText });
					alert('AJAX error: ' + error);
				}
			});
		},

		/**
		 * Handle save all AI suggestions
		 */
		handleAISaveAll: function() {
			const rows = $('.bymu-has-suggestions:not(.bymu-saved)');
			
			if (rows.length === 0) {
				alert('No unsaved suggestions found. Generate suggestions first.');
				return;
			}

			if (!confirm(`Save AI suggestions for ${rows.length} post(s)?`)) {
				return;
			}

			const btn = $('#bymu-ai-save-all');
			btn.prop('disabled', true).text('Saving...');

			let completed = 0;
			let errors = 0;
			this.log('AI Save All: queued rows', rows.length);

			rows.each((index, row) => {
				const $row = $(row);
				const postId = $row.data('post-id');
				
				setTimeout(() => {
					$row.find('.bymu-ai-save-btn').trigger('click');
					
					completed++;
					if (completed === rows.length) {
						btn.prop('disabled', false).text('Save All Changes');
						const savedCount = Math.max(0, completed - errors);
						this.log('AI Save All: completed', { attempted: completed, errors, saved: savedCount });

						const targetUrl = new URL(window.location.href);
						targetUrl.searchParams.set('bymu_meta_saved', savedCount);
						window.location.href = targetUrl.toString();
					}
				}, index * 500); // Stagger saves by 500ms
			});
		},

		/**
		 * Handle generate AI suggestions for all rows on screen.
		 */
		handleAIGenerateAll: function() {
			const rows = $('#bymu-ai-posts-container').find('tbody tr');

			if (!rows.length) {
				alert('No posts loaded. Please load posts first.');
				return;
			}

			if (!confirm(`Generate AI suggestions for ${rows.length} post(s)? This will process each row sequentially.`)) {
				return;
			}

			const totalRows = rows.length;
			const btn = $('#bymu-ai-generate-all');
			const stopBtn = $('#bymu-ai-stop-bulk');
			const self = this;

			btn.prop('disabled', true).text('Generating...');
			stopBtn.hide();
			this.log('AI Generate All: starting', { totalRows });

			this.startAIBulkJob(totalRows)
				.done(function(response) {
					if (!response || !response.success || !response.data || !response.data.job_id) {
						btn.prop('disabled', false).text('Generate All On Screen');
						stopBtn.hide();
						alert('Unable to start logging job. Please try again.');
						return;
					}

					self.bulkGenerateJobId = response.data.job_id;
					self.bulkGenerateSequence = 0;
					self.bulkGenerateTotals = { success: 0, errors: 0, total: totalRows };
					self.bulkGenerateActive = true;
					self.bulkGenerateStopRequested = false;
					stopBtn.show().prop('disabled', false).text('Stop Bulk Generation');
					self.log('AI Generate All: bulk job started', { jobId: self.bulkGenerateJobId, totalRows });

					let index = 0;
					const baseDelay = 1500;
					let rateDelay = baseDelay;
					let failures = 0;
					let successCount = 0;
					let retryQueue = [];

					const finalizeRun = function() {
						const finishUI = function() {
							self.bulkGenerateActive = false;
							self.bulkGenerateJobId = null;
							self.bulkGenerateSequence = 0;
							self.bulkGenerateTotals = { success: 0, errors: 0, total: 0 };
							btn.prop('disabled', false).text('Generate All On Screen');
								stopBtn.hide().prop('disabled', false).text('Stop Bulk Generation');
								self.bulkGenerateStopRequested = false;
							self.log('AI Generate All: finished', { success: successCount, failures });
						};

						if (!self.bulkGenerateJobId) {
							finishUI();
							return;
						}

						self.finishAIBulkJob(self.bulkGenerateJobId, {
							total: totalRows,
							processed: successCount + failures,
							success: successCount,
							errors: failures
						}).always(finishUI);
					};

					const processNext = function() {
							if (self.bulkGenerateStopRequested) {
								retryQueue = [];
								index = rows.length;
								finalizeRun();
								return;
							}

						if (index >= rows.length && retryQueue.length === 0) {
							finalizeRun();
							return;
						}

						let row;

						if (index < rows.length) {
							row = $(rows[index]);
							index++;
						} else {
							row = retryQueue.shift();
						}

						const generateBtn = row.find('.bymu-ai-generate-btn');

						if (!generateBtn.length) {
							processNext();
							return;
						}

						if (generateBtn.prop('disabled')) {
							retryQueue.push(row);
							setTimeout(processNext, 300);
							return;
						}

						row.find('.bymu-ai-status').html('<span style="color: #2271b1;">Generating…</span>');

						let localFailure = false;
						let localRateLimit = false;
						let advanced = false;

						const proceed = (delay) => {
							if (advanced) {
								return;
							}
							advanced = true;
							setTimeout(() => {
								if (self.bulkGenerateStopRequested) {
									retryQueue = [];
									index = rows.length;
								}
								processNext();
							}, delay);
						};

						row.one('bymu:aiGenerateError', (event, data) => {
							if (data && data.rateLimit) {
								localRateLimit = true;
								retryQueue.push(row);
								rateDelay = Math.min(4000, rateDelay + 500);
								proceed(rateDelay);
								self.log('AI Generate All: rate limited, retrying', { postId: row.data('post-id'), rateDelay });
							} else {
								failures++;
								self.bulkGenerateTotals.errors = failures;
								localFailure = true;
								rateDelay = baseDelay;
								proceed(rateDelay);
								self.log('AI Generate All: row failed', { postId: row.data('post-id'), failures });
							}
						});

						row.one('bymu:aiGenerateSuccess', () => {
							successCount++;
							self.bulkGenerateTotals.success = successCount;
							localFailure = false;
							localRateLimit = false;
							rateDelay = baseDelay;
							proceed(rateDelay);
							self.log('AI Generate All: row success', { postId: row.data('post-id'), successCount });
						});

						const sequence = self.bulkGenerateSequence + 1;
						self.bulkGenerateSequence = sequence;

						generateBtn
							.data('bulk-job-id', self.bulkGenerateJobId)
							.data('bulk-seq', sequence)
							.data('bulk-run', true)
							.data('bulk-total', totalRows);

						generateBtn.trigger('click');
					};

					processNext();
				})
				.fail(function(xhr, status, error) {
					btn.prop('disabled', false).text('Generate All On Screen');
					stopBtn.hide().prop('disabled', false).text('Stop Bulk Generation');
					alert('Unable to start logging job. Please try again.');
					self.log('AI Generate All: failed to start job', { status, error, responseText: xhr && xhr.responseText });
				});
		},

		handleAIGenerateStop: function() {
			const stopBtn = $('#bymu-ai-stop-bulk');

			if (!this.bulkGenerateActive) {
				alert('Bulk generation is not currently running.');
				return;
			}

			if (this.bulkGenerateStopRequested) {
				alert('Stop request already queued. Please wait…');
				return;
			}

			this.bulkGenerateStopRequested = true;
			stopBtn.prop('disabled', true).text('Stopping…');
			this.log('AI Generate All: stop requested');
		},

		/**
		 * Start a bulk AI generation job in the logger.
		 *
		 * @param {number} totalRows Total rows to process.
		 * @return {jqXHR} Ajax promise.
		 */
		startAIBulkJob: function(totalRows) {
			this.log('AI Bulk Job: start request', { totalRows });
			return $.ajax({
				url: bymuAdmin.ajaxurl,
				type: 'POST',
				data: {
					action: 'bymu_start_ai_bulk_job',
					nonce: bymuAdmin.nonces.ai_generate,
					total: totalRows
				}
			});
		},

		/**
		 * Finalize a bulk AI generation job with summary data.
		 *
		 * @param {number} jobId Job ID.
		 * @param {Object} summary Summary totals.
		 * @return {jqXHR} Ajax promise.
		 */
		finishAIBulkJob: function(jobId, summary) {
			if (!jobId) {
				return $.Deferred().resolve().promise();
			}

			const payload = summary || {};
			this.log('AI Bulk Job: finish request', { jobId, summary: payload });

			return $.ajax({
				url: bymuAdmin.ajaxurl,
				type: 'POST',
				data: {
					action: 'bymu_finish_ai_bulk_job',
					nonce: bymuAdmin.nonces.ai_generate,
					job_id: jobId,
					total: payload.total || 0,
					processed: payload.processed || 0,
					success: payload.success || 0,
					errors: payload.errors || 0
				}
			});
		},

		/**
		 * Handle syncing attachment alt text across posts.
		 */
		handleSyncAlt: function(e) {
			const btn = $(e.currentTarget);
			const attachmentId = parseInt(btn.data('attachment-id'), 10);
			const refCount = parseInt(btn.data('ref-count'), 10) || 0;
			const statusEl = btn.siblings('.bymu-sync-alt-status');
			const row = btn.closest('tr');

			if (!attachmentId) {
				return;
			}

			if (refCount === 0) {
				statusEl.html('<span style="color: #2271b1;">' + this.escapeHtml(bymuAdmin.strings.syncAltNoRefs) + '</span>');
				return;
			}

			if (!confirm(bymuAdmin.strings.syncAltConfirm.replace('%d', refCount))) {
				return;
			}

			this.performSyncAlt({
				attachmentId,
				refCount,
				row,
				statusEl,
				button: btn,
				silent: false
			});
		},

		performSyncAlt: function(options) {
			const deferred = $.Deferred();
			const attachmentId = parseInt(options.attachmentId, 10);

			if (!attachmentId) {
				deferred.reject('invalid_attachment');
				return deferred.promise();
			}

			const refCount = parseInt(options.refCount, 10) || 0;
			const row = options.row ? $(options.row) : $();
			const statusEl = options.statusEl ? $(options.statusEl) : row.find('.bymu-sync-alt-status');
			const button = options.button ? $(options.button) : row.find('.bymu-sync-alt-btn');
			const silent = !!options.silent;

			let originalText = '';
			if (button && button.length) {
				if (!silent) {
					originalText = button.text();
					button.prop('disabled', true).text('Syncing…');
				} else {
					button.prop('disabled', true);
				}
			}

			if (statusEl && statusEl.length) {
				const msg = silent ? this.escapeHtml('Syncing alt text across linked posts…') :
					this.escapeHtml('Updating alt text across ' + refCount + ' post(s)…');
				statusEl.html('<span style="color: #2271b1;">' + msg + '</span>');
			}

			$.ajax({
				url: bymuAdmin.ajaxurl,
				type: 'POST',
				data: {
					action: 'bymu_sync_image_alt',
					nonce: bymuAdmin.nonces.sync_alt,
					attachment_id: attachmentId
				}
			}).done((response) => {
				if (button && button.length) {
					button.prop('disabled', false);
					if (!silent && originalText) {
						button.text(originalText);
					}
				}

				if (response.success) {
					const updated = response.data.updated || 0;
					if (statusEl && statusEl.length) {
						statusEl.html('<span style="color: #00a32a;">' + this.escapeHtml(response.data.message) + '</span><br><small>' +
							this.escapeHtml(updated + ' post(s) updated') + '</small>');
					}
					if (row && row.length) {
						row.removeClass('bymu-ready-to-sync');
					}
					deferred.resolve(response);
				} else {
					if (statusEl && statusEl.length) {
						statusEl.html('<span style="color: #d63638;">' + this.escapeHtml(response.data) + '</span>');
					}
					deferred.reject(response);
				}
			}).fail((xhr, status, error) => {
				if (button && button.length) {
					button.prop('disabled', false);
					if (!silent && originalText) {
						button.text(originalText);
					}
				}
				const errorMsg = 'AJAX error: ' + error;
				if (statusEl && statusEl.length) {
					statusEl.html('<span style="color: #d63638;">' + this.escapeHtml(errorMsg) + '</span>');
				}
				deferred.reject(errorMsg);
			}).always(() => {
				if (silent && button && button.length) {
					button.prop('disabled', false);
				}
				this.updateBulkAltButtons();
			});

			return deferred.promise();
		},

		handleBulkAltSync: function() {
			const btn = $('#bymu-bulk-sync-alt');
			if (!btn.length) {
				return;
			}

			const rows = $('.bymu-ready-to-sync');

			if (!rows.length) {
				this.setBulkAltStatus('No images are marked for syncing. Save your changes first.', 'info');
				return;
			}

			if (!confirm(`Sync alt text in content for ${rows.length} image(s)?`)) {
				return;
			}

			this.bulkAltSyncActive = true;
			btn.prop('disabled', true).text('Syncing…');
			this.updateBulkAltButtons();

			const queue = $.makeArray(rows);
			let index = 0;
			let success = 0;
			let errors = 0;

			const processNext = () => {
				if (index >= queue.length) {
					this.bulkAltSyncActive = false;
					btn.prop('disabled', false).text('Sync Alt Text In Content');
					this.updateBulkAltButtons();
					const msg = errors > 0
						? `Completed ${success} image alt text syncs. ${errors} error(s), review below.`
						: `Completed ${success} image alt text syncs.`;
					this.setBulkAltStatus(msg, errors > 0 ? 'error' : 'success');
					return;
				}

				const row = $(queue[index]);
				const attachmentId = parseInt(row.data('attachment-id'), 10);
				const syncBtn = row.find('.bymu-sync-alt-btn');
				const refCount = parseInt(syncBtn.data('ref-count'), 10) || 0;
				const statusEl = row.find('.bymu-sync-alt-status');

				this.performSyncAlt({
					attachmentId,
					refCount,
					row,
					statusEl,
					button: syncBtn,
					silent: true
				}).done(() => {
					success++;
				}).fail(() => {
					errors++;
				}).always(() => {
					index++;
					setTimeout(processNext, 250);
				});
			};

			processNext();
		},

		/**
		 * Generate alt text via AI for a specific attachment.
		 */
		handleModalGenerateAlt: function(e) {
			const btn = $(e.currentTarget);
			const attachmentId = parseInt(btn.data('attachment-id'), 10);
			const statusEl = btn.siblings('.bymu-sync-alt-status');
			const row = btn.closest('tr');

			if (!attachmentId) {
				return;
			}

			btn.prop('disabled', true).text('Generating…');
			statusEl.html('<span style="color: #2271b1;">Generating alt text…</span>');
			this.log('Image Alt (modal): generate request', { attachmentId });

			$.ajax({
				url: bymuAdmin.ajaxurl,
				type: 'POST',
				data: {
					action: 'bymu_generate_image_alt',
					nonce: bymuAdmin.nonces.generate_image_alt,
					attachment_id: attachmentId
				},
				success: (response) => {
					btn.prop('disabled', false).text('Generate Alt Text');

					if (response.success) {
						this.log('Image Alt (modal): success', { attachmentId });
						this.populateGeneratedAlt(row, response.data.alt_text || '');
						statusEl.html('<span style="color: #00a32a;">' + this.escapeHtml(bymuAdmin.strings.generateAltSaving) + '</span>');
						if (row && row.length) {
							row.trigger('bymu:altGenerateComplete', { success: true });
						}
					} else {
						this.log('Image Alt (modal): server error', { attachmentId, response });
						statusEl.html('<span style="color: #d63638;">' + this.escapeHtml(response.data) + '</span>');
						if (row && row.length) {
							row.trigger('bymu:altGenerateComplete', { success: false });
						}
					}
				},
				error: (xhr, status, error) => {
					btn.prop('disabled', false).text('Generate Alt Text');
					this.log('Image Alt (modal): ajax error', { attachmentId, status, error, responseText: xhr && xhr.responseText });
					statusEl.html('<span style="color: #d63638;">' + this.escapeHtml('AJAX error: ' + error) + '</span>');
					if (row && row.length) {
						row.trigger('bymu:altGenerateComplete', { success: false });
					}
				}
			});
		},

		/**
		 * Populate generated alt text into the inline editor.
		 *
		 * @param {jQuery} row Table row element.
		 * @param {string} altText Generated alt text.
		 */
		populateGeneratedAlt: function(row, altText) {
			const wrapper = row.find('.bymu-alt-wrapper');
			const generated = wrapper.find('.bymu-generated-alt');
			const textarea = generated.find('textarea');

			textarea.val(altText);
			generated.show();
			wrapper.addClass('bymu-has-generated');
			wrapper.find('.bymu-inline-save-alt').prop('disabled', false).text('Save');
			row.removeClass('bymu-ready-to-sync');

			this.updateInlineSaveControls();
		},

		/**
		 * Save alt text for a single attachment (from inline editor).
		 */
		handleInlineSaveAlt: function(e) {
			const btn = $(e.currentTarget);
			const row = btn.closest('tr');
			const textarea = row.find('.bymu-generated-alt textarea');
			const altText = textarea.val().trim();

			if (!altText) {
				row.find('.bymu-sync-alt-status').html('<span style="color:#d63638;">Alt text cannot be empty.</span>');
				return;
			}

			this.saveAttachmentAlt(row, altText, btn);
		},

		/**
		 * Cancel inline alt text editing (single row or all rows).
		 */
		handleInlineCancelSave: function(e) {
			const target = $(e.currentTarget);

			if (target.is('#bymu-inline-cancel-save')) {
				$('.bymu-alt-wrapper.bymu-has-generated').each((_, wrapperEl) => {
					const wrapper = $(wrapperEl);
					wrapper.removeClass('bymu-has-generated');
					wrapper.find('.bymu-generated-alt textarea').val('');
					wrapper.find('.bymu-generated-alt').hide();
					const row = wrapper.closest('tr');
					row.removeClass('bymu-ready-to-sync');
					row.find('.bymu-inline-save-alt').prop('disabled', false).text('Save');
				});
				this.updateInlineSaveControls();
				return;
			}

			const wrapper = target.closest('.bymu-alt-wrapper');

			if (!wrapper.length) {
				return;
			}

			wrapper.removeClass('bymu-has-generated');
			wrapper.find('.bymu-generated-alt textarea').val('');
			wrapper.find('.bymu-generated-alt').hide();
			const row = wrapper.closest('tr');
			row.removeClass('bymu-ready-to-sync');
			row.find('.bymu-inline-save-alt').prop('disabled', false).text('Save');
			this.updateInlineSaveControls();
		},

		/**
		 * Save all generated alt text rows.
		 */
		handleInlineSaveAll: function() {
			const rows = $('.bymu-alt-wrapper.bymu-has-generated').closest('tr');

			if (!rows.length) {
				this.setBulkAltStatus('No generated alt text pending save.', 'info');
			 return;
			}

			if (!confirm(`Save generated alt text for ${rows.length} image(s)?`)) {
				return;
			}

			const btn = $('#bymu-inline-save-all');
			btn.prop('disabled', true).text('Saving…');

			let index = 0;
			let failures = 0;

			const processNext = () => {
				if (index >= rows.length) {
					btn.prop('disabled', false).text('Save Generated Alt Text');
					const successes = Math.max(0, rows.length - failures);
					const msg = failures > 0
						? `Successfully saved ${successes} item(s). ${failures} error(s), review below.`
						: `Successfully saved ${successes} item(s).`;
					this.setBulkAltStatus(msg, failures > 0 ? 'error' : 'success');
					this.updateInlineSaveControls();
					return;
				}

				const row = $(rows[index]);
				const textarea = row.find('.bymu-generated-alt textarea');
				const altText = textarea.val().trim();

				if (!altText) {
					failures++;
					index++;
					processNext();
					return;
				}

				const inlineBtn = row.find('.bymu-inline-save-alt');

				this.saveAttachmentAlt(
					row,
					altText,
					inlineBtn,
					true,
					(success) => {
						if (!success) {
							failures++;
						}
						index++;
						setTimeout(processNext, 200);
					}
				);
			};

			processNext();
		},

		/**
		 * Save attachment alt text via AJAX.
		 *
		 * @param {jQuery} row Table row.
		 * @param {string} altText Alt text to save.
		 * @param {jQuery} button Button initiating the save.
		 * @param {boolean} silent Whether to suppress alerts.
		 * @param {Function|null} callback Optional callback receiving success boolean.
		 */
		saveAttachmentAlt: function(row, altText, button, silent = false, callback = null) {
			const attachmentId = parseInt(row.data('attachment-id'), 10);
			const statusEl = row.find('.bymu-sync-alt-status');

			if (!attachmentId) {
				if (callback) {
					callback(false);
				}
				return;
			}

			const targetButton = button && button.length ? button : row.find('.bymu-inline-save-alt');

			targetButton.prop('disabled', true).text('Saving…');
			statusEl.html('<span style="color: #2271b1;">Saving alt text…</span>');

			$.ajax({
				url: bymuAdmin.ajaxurl,
				type: 'POST',
				data: {
					action: 'bymu_save_attachment_alt',
					nonce: bymuAdmin.nonces.save_alt,
					attachment_id: attachmentId,
					alt_text: altText
				},
				success: (response) => {
					targetButton.prop('disabled', false).text('Save');

					if (response.success) {
						const wrapper = row.find('.bymu-alt-wrapper');
						wrapper.removeClass('bymu-has-generated');
						wrapper.find('.bymu-generated-alt').hide();
						row.find('.bymu-current-alt').text(response.data.alt_text || altText);

						statusEl.html('<span style="color: #00a32a;">' + this.escapeHtml(response.data.message) + '</span>');
						const refCount = parseInt(row.find('.bymu-sync-alt-btn').data('ref-count'), 10) || 0;
						if (refCount > 0) {
							row.addClass('bymu-ready-to-sync');
						} else {
							row.removeClass('bymu-ready-to-sync');
						}
						this.updateInlineSaveControls();

						if (callback) {
							callback(true);
						}
					} else {
						statusEl.html('<span style="color: #d63638;">' + this.escapeHtml(response.data) + '</span>');
						if (callback) {
							callback(false);
						}
					}
				},
				error: (xhr, status, error) => {
					targetButton.prop('disabled', false).text('Save');
					statusEl.html('<span style="color: #d63638;">' + this.escapeHtml('AJAX error: ' + error) + '</span>');
					if (callback) {
						callback(false);
					}
				}
			});
		},

		/**
		 * Update visibility of global inline save controls.
		 */
		updateInlineSaveControls: function() {
			const hasPending = $('.bymu-alt-wrapper.bymu-has-generated').length > 0;
			if (hasPending) {
				$('.bymu-inline-save-controls').addClass('is-visible');
			} else {
				$('.bymu-inline-save-controls').removeClass('is-visible');
			}
			$('#bymu-inline-save-all').prop('disabled', !hasPending || this.bulkAltRunnerActive);
			$('#bymu-inline-cancel-save').prop('disabled', !hasPending);
			this.updateBulkAltButtons();
		},

		updateBulkAltButtons: function() {
			const generateBtn = $('#bymu-bulk-generate-alt');
			if (generateBtn.length) {
				generateBtn.prop('disabled', !!this.bulkAltRunnerActive);
			}

			const saveBtn = $('#bymu-inline-save-all');
			if (saveBtn.length) {
				const hasPending = $('.bymu-alt-wrapper.bymu-has-generated').length > 0;
				saveBtn.prop('disabled', !hasPending || this.bulkAltRunnerActive);
			}

			const syncBtn = $('#bymu-bulk-sync-alt');
			if (syncBtn.length) {
				const syncTargets = $('.bymu-ready-to-sync').length > 0;
				syncBtn.prop('disabled', !syncTargets || this.bulkAltRunnerActive || this.bulkAltSyncActive);
			}
		},

		/**
		 * Initialize export estimate listeners/data.
		 */
		initExportEstimate: function() {
			const dataNode = document.getElementById('bymu-export-counts');

			if (!dataNode) {
				return;
			}

			try {
				this.exportCounts = JSON.parse(dataNode.textContent || dataNode.innerText || '{}');
			} catch (error) {
				console.error('Failed to parse export counts payload', error);
				this.exportCounts = null;
				return;
			}

			this.updateExportEstimate();

			this.bindEvent('#export_post_types, #export_post_status', 'change', 'updateExportEstimate');
			this.bindEvent('#export_limit', 'input', 'updateExportEstimate');
			this.bindEvent('#export_limit', 'change', 'updateExportEstimate');
			this.bindEvent('#export_short_only', 'change', 'updateExportEstimate');
		},

		/**
		 * Update export estimate text based on current filters.
		 */
		updateExportEstimate: function() {
			if (!this.exportCounts || !this.exportCounts.post_types) {
				return;
			}

			const estimateEl = $('#bymu-export-estimate');
			if (!estimateEl.length) {
				return;
			}

			const shortOnly = $('#export_short_only').is(':checked');
			if (shortOnly) {
				estimateEl.text(bymuAdmin.strings.exportEstimateFiltered || '');
				return;
			}

			const postTypes = $('#export_post_types').val() || [];
			const statuses = $('#export_post_status').val() || [];

			if (!postTypes.length || !statuses.length) {
				estimateEl.text(bymuAdmin.strings.exportEstimateSelect || '');
				return;
			}

			let total = 0;
			postTypes.forEach((type) => {
				const typeData = this.exportCounts.post_types[type];
				if (!typeData) {
					return;
				}
				statuses.forEach((status) => {
					total += typeData.statuses && typeData.statuses[status] ? typeData.statuses[status] : 0;
				});
			});

			const limit = parseInt($('#export_limit').val(), 10);
			if (!isNaN(limit) && limit > 0) {
				total = Math.min(total, limit);
			}

			const template = bymuAdmin.strings.exportEstimateLabel || '%d rows will export.';
			estimateEl.text(template.replace('%d', this.formatNumber(total)));
		},

		/**
		 * Format number with locale fallback.
		 */
		formatNumber: function(value) {
			const num = Number(value) || 0;
			if (typeof Intl !== 'undefined' && Intl.NumberFormat) {
				return new Intl.NumberFormat().format(num);
			}

			return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
		},

		/**
		 * Start bulk AI generation for image alt text.
		 */
		handleBulkAltGenerate: function() {
			const bulkBtn = $('#bymu-bulk-generate-alt');

			if (!bulkBtn.length) {
				return;
			}

			if (this.bulkAltRunnerActive) {
				alert('Bulk processing already running. Please wait for it to finish.');
				return;
			}

			const candidates = [];
			$('.bymu-inline-generate-alt-btn').each((_, el) => {
				const btn = $(el);
				const row = btn.closest('tr');
				const wrapper = row.find('.bymu-alt-wrapper');

				if (!row.length || !btn.length) {
					return;
				}

				if (btn.prop('disabled')) {
					return;
				}

				if (wrapper.hasClass('bymu-has-generated')) {
					return;
				}

				candidates.push(btn);
			});

			if (!candidates.length) {
				alert('No images need AI generation right now.');
				return;
			}

			if (!confirm(`Generate new alt text for ${candidates.length} image(s)?`)) {
				return;
			}

			const concurrencyInput = parseInt($('#bymu-bulk-alt-concurrency').val(), 10);
			this.bulkAltConcurrency = Math.min(8, Math.max(1, isNaN(concurrencyInput) ? 1 : concurrencyInput));

			this.bulkAltRunnerActive = true;
			this.bulkAltQueue = candidates;
			this.bulkAltInFlight = 0;
			this.bulkAltTotals = { processed: 0, total: candidates.length, success: 0, errors: 0 };
			this.updateBulkAltButtons();

			bulkBtn.data('label', bulkBtn.text()).prop('disabled', true).text('Processing…');
			this.log('Bulk Alt: starting', { total: candidates.length, concurrency: this.bulkAltConcurrency });
			this.updateBulkAltStatus();
			this.processBulkAltQueue();
		},

		/**
		 * Process the bulk alt queue respecting concurrency limits.
		 */
		processBulkAltQueue: function() {
			if (!this.bulkAltRunnerActive) {
				return;
			}

			while (this.bulkAltInFlight < this.bulkAltConcurrency && this.bulkAltQueue.length) {
				const btn = this.bulkAltQueue.shift();

				if (!btn || !btn.length) {
					continue;
				}

				const row = btn.closest('tr');

				if (!row.length) {
					continue;
				}

				if (btn.prop('disabled')) {
					// Re-queue and retry shortly.
					this.bulkAltQueue.push(btn);
					setTimeout(() => this.processBulkAltQueue(), 500);
					return;
				}

				this.bulkAltInFlight++;
				row.addClass('bymu-bulk-alt-running');

				row.one('bymu:altGenerateComplete', (event, payload) => {
					row.removeClass('bymu-bulk-alt-running');
					this.bulkAltInFlight = Math.max(0, this.bulkAltInFlight - 1);
					this.bulkAltTotals.processed++;
					if (payload && payload.success) {
						this.bulkAltTotals.success++;
					} else {
						this.bulkAltTotals.errors++;
					}
					this.updateBulkAltStatus();

					if (this.bulkAltQueue.length === 0 && this.bulkAltInFlight === 0) {
						this.finishBulkAltRun();
					} else {
						this.processBulkAltQueue();
					}
				});

				btn.trigger('click');
			}

			if (this.bulkAltQueue.length === 0 && this.bulkAltInFlight === 0 && this.bulkAltRunnerActive) {
				this.finishBulkAltRun();
			}
		},

		/**
		 * Update bulk alt status text.
		 */
		updateBulkAltStatus: function() {
			const statusBox = $('#bymu-bulk-alt-status');

			if (!statusBox.length) {
				return;
			}

			if (!this.bulkAltRunnerActive) {
				if (this.bulkAltTotals.total > 0) {
					statusBox.text(`Last run: ${this.bulkAltTotals.success}/${this.bulkAltTotals.total} successes • ${this.bulkAltTotals.errors} error(s)`);
				} else {
					statusBox.text('');
				}
				return;
			}

			const { processed, total, success, errors } = this.bulkAltTotals;
			statusBox.text(`Processing ${processed}/${total} images • Success: ${success} • Errors: ${errors}`);
		},

		/**
		 * Finish bulk alt run and reset state.
		 */
		finishBulkAltRun: function() {
			const bulkBtn = $('#bymu-bulk-generate-alt');
			const finalTotals = Object.assign({}, this.bulkAltTotals);

			if (bulkBtn.length) {
				const originalLabel = bulkBtn.data('label') || 'Generate Image Alt Text';
				bulkBtn.prop('disabled', false).text(originalLabel);
			}

			this.bulkAltRunnerActive = false;
			this.bulkAltQueue = [];
			this.bulkAltInFlight = 0;
			this.updateBulkAltStatus();
			this.updateBulkAltButtons();

			this.log('Bulk Alt: finished', finalTotals);

			if (finalTotals.total > 0) {
				setTimeout(() => {
					alert(`Bulk process complete.\nSuccess: ${finalTotals.success}\nErrors: ${finalTotals.errors}`);
				}, 200);
			}
		},

		/**
		 * Refresh Gemini models via AJAX.
		 */
		handleRefreshGeminiModels: function() {
			const apiKeyField = $('#gemini_api_key');
			const apiKey = apiKeyField.length ? apiKeyField.val().trim() : '';
			const statusEl = $('#bymu-model-refresh-status');
			const button = $('#bymu-refresh-gemini-models');

			if (!button.length) {
				return;
			}

			if (!apiKey) {
				statusEl.text(bymuAdmin.modelMessages.missingKey || 'Enter your API key first.');
				return;
			}

			statusEl.text(bymuAdmin.modelMessages.refreshing || 'Fetching models…');
			button.prop('disabled', true);

			$.ajax({
				url: bymuAdmin.ajaxurl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'bymu_fetch_gemini_models',
					nonce: bymuAdmin.nonces.fetch_models,
					api_key: apiKey
				},
				success: (response) => {
					button.prop('disabled', false);

					if (response && response.success && response.data && response.data.models) {
						bymuAdmin.models = response.data.models;
						this.populateModelSelects(response.data.models);
						statusEl.text(bymuAdmin.modelMessages.updated || 'Model list updated.');
					} else if (response && response.data) {
						statusEl.text(response.data);
					} else {
						statusEl.text(bymuAdmin.modelMessages.error || 'Unable to load models.');
					}
				},
				error: (xhr, status, error) => {
					button.prop('disabled', false);
					statusEl.text((bymuAdmin.modelMessages.error || 'Unable to load models.') + ' ' + error);
				}
			});
		},

		/**
		 * Populate Gemini model dropdowns.
		 *
		 * @param {Object} modelsData Categorized models.
		 */
		populateModelSelects: function(modelsData) {
			const textSelect = $('#gemini_text_model');
			const imageSelect = $('#gemini_image_model');

			if (!textSelect.length && !imageSelect.length) {
				return;
			}

			const textModels = (modelsData && modelsData.text) ? modelsData.text : [];
			const imageModels = (modelsData && modelsData.image) ? modelsData.image : [];

			if (textSelect.length) {
				const current = textSelect.data('current') || textSelect.val();
				this.buildModelOptions(textSelect, textModels, current);
			}

			if (imageSelect.length) {
				const current = imageSelect.data('current') || imageSelect.val();
				this.buildModelOptions(imageSelect, imageModels, current);
			}
		},

		/**
		 * Build dropdown options from models list.
		 *
		 * @param {jQuery} selectEl Select element.
		 * @param {Array} models Models list.
		 * @param {string} current Current selection.
		 */
		buildModelOptions: function(selectEl, models, current) {
			const existingValue = current || '';
			const seen = {};

			selectEl.empty();

			if (models && models.length) {
				models.forEach((model) => {
					if (!model || !model.id) {
						return;
					}
					let label = model.display_name && model.display_name !== model.id
						? `${model.display_name} (${model.id})`
						: model.id;
					
					// Mark recommended 2.5 models with star.
					const modelIdLower = model.id.toLowerCase();
					const isRecommended = (modelIdLower.includes('2.5-flash') && 
						!modelIdLower.includes('lite') && 
						!modelIdLower.includes('preview'));
					if (isRecommended) {
						label = '⭐ ' + label;
					}
					
					seen[model.id] = true;
					selectEl.append(new Option(label, model.id, false, existingValue === model.id));
				});
			}

			if (existingValue && !seen[existingValue]) {
				const customLabel = `${existingValue} (custom)`;
				selectEl.append(new Option(customLabel, existingValue, true, true));
			}

			// If no value selected and we have models, auto-select recommended 2.5 model.
			if (!existingValue && models && models.length) {
				const recommended = models.find(m => {
					const id = (m.id || '').toLowerCase();
					return id.includes('2.5-flash') && !id.includes('lite') && !id.includes('preview');
				});
				if (recommended) {
					selectEl.val(recommended.id);
					existingValue = recommended.id;
				}
			}

			selectEl.data('current', selectEl.val() || existingValue);
		},

		/**
		 * Handle AI generation errors with retries and user-friendly messages.
		 *
		 * @param {jQuery} row Table row element.
		 * @param {jQuery} button Generate button element.
		 * @param {string} message Error message.
		 * @param {Array|null} details Optional additional error details array.
		 * @param {XMLHttpRequest|null} xhr Optional XHR object for status checks.
		 * @param {boolean} silent When true, skip alert popups.
		 */
		handleAIGenerateError: function(row, button, message, details = null, xhr = null, silent = false, hint = null) {
			const statusCell = row.find('.bymu-ai-status');
			const hintRateLimit = hint && hint.rateLimit;
			const hintOverloaded = hint && hint.overloaded;
			const isRateLimitFromXhr = xhr && (xhr.status === 429 || (xhr.responseText && xhr.responseText.toLowerCase().includes('rate limit')));
			const isRateLimit = !!(hintRateLimit || isRateLimitFromXhr);
			const isOverloaded = !!hintOverloaded;
			const friendlyMessage = isRateLimit
				? 'Rate limit hit. Pausing before retry.'
				: (isOverloaded ? 'Gemini is temporarily overloaded. Please try again shortly.' : (message || 'Error generating suggestions.'));

			if (statusCell.length) {
				statusCell.html('<span style="color: #d63638;">' + this.escapeHtml(friendlyMessage) + '</span>');

				// Don't show individual field error details for service-wide issues (overload/rate limit).
				if (!isOverloaded && !isRateLimit) {
					const detailList = Array.isArray(details) ? details : (details ? Object.values(details) : null);
					if (Array.isArray(detailList) && detailList.length) {
						statusCell.append('<br><small>' + this.escapeHtml(detailList.join(', ')) + '</small>');
					}
				}
			}

			if (isRateLimit) {
				button.prop('disabled', true).text('Rate Limited');
				setTimeout(() => {
					button.prop('disabled', false).text('Generate');
					if (!silent) {
						alert('Google Gemini rate limit reached. Please wait a couple of seconds and try again.');
					}
				}, 2500);
			} else if (isOverloaded) {
				button.prop('disabled', true).text('Service Busy');
				setTimeout(() => {
					button.prop('disabled', false).text('Generate');
				}, 2500);
			} else {
				button.prop('disabled', false).text('Generate');
				if (!silent) {
					alert('Error generating suggestions: ' + friendlyMessage);
				}
			}

			row.trigger('bymu:aiGenerateError', {
				rateLimit: isRateLimit,
				overloaded: isOverloaded,
				statuses: hint && Array.isArray(hint.statuses) ? hint.statuses : []
			});
		},

		/**
		 * Handle export meta data
		 */
		handleExport: function() {
			// Get selected options
			const postTypes = $('#export_post_types').val() || [];
			const postStatus = $('#export_post_status').val() || [];
			const limit = parseInt($('#export_limit').val()) || 0;
			const shortOnly = $('#export_short_only').is(':checked');

			// Validate
			if (postTypes.length === 0) {
				alert('Please select at least one post type to export.');
				return;
			}

			if (postStatus.length === 0) {
				alert('Please select at least one post status to export.');
				return;
			}

			// Show loading
			$('#bymu-export-loading').addClass('is-active');
			$('#bymu-export-btn').prop('disabled', true);
			$('#bymu-export-status').html('<span style="color: #2271b1;">Generating export...</span>');

			// Build URL with parameters
			let url = bymuAdmin.ajaxurl + 
				'?action=bymu_export_meta' +
				'&nonce=' + bymuAdmin.nonces.export_meta +
				'&limit=' + limit;

			// Add post types
			postTypes.forEach(type => {
				url += '&post_types[]=' + encodeURIComponent(type);
			});

			// Add post statuses
			postStatus.forEach(status => {
				url += '&post_status[]=' + encodeURIComponent(status);
			});

			if (shortOnly) {
				url += '&short_only=1';
			}

			// Fetch CSV via AJAX and trigger download in-place.
			const fetchOptions = {
				method: 'GET',
				credentials: 'same-origin',
				headers: {
					'X-Requested-With': 'XMLHttpRequest'
				}
			};

			fetch(url, fetchOptions)
				.then((response) => {
					if (!response.ok) {
						throw new Error(`Export failed (${response.status})`);
					}

					const disposition = response.headers.get('Content-Disposition') || '';
					let filename = 'bulk-seo-meta-export.csv';
					const match = disposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/i);
					if (match && match[1]) {
						filename = match[1].replace(/['"]/g, '').trim();
					}

					return response.blob().then((blob) => ({ blob, filename }));
				})
				.then(({ blob, filename }) => {
					const downloadUrl = window.URL.createObjectURL(blob);
					const a = document.createElement('a');
					a.href = downloadUrl;
					a.download = filename;
					document.body.appendChild(a);
					a.click();
					setTimeout(() => {
						window.URL.revokeObjectURL(downloadUrl);
						a.remove();
					}, 100);

					$('#bymu-export-status').html('<span style="color: #00a32a;">✓ Export complete. Check your downloads.</span>');
				})
				.catch((error) => {
					console.error('Export error:', error);
					$('#bymu-export-status').html('<span style="color: #d63638;">' + this.escapeHtml(error.message || 'Export failed. Please try again.') + '</span>');
				})
				.finally(() => {
					$('#bymu-export-loading').removeClass('is-active');
					$('#bymu-export-btn').prop('disabled', false);
					setTimeout(() => {
						$('#bymu-export-status').html('');
					}, 4000);
				});
		},

		/**
		 * Handle CSV upload and validation
		 */
		handleUpload: function(e) {
			e.preventDefault();

			const fileInput = $('#csv_file')[0];
			if (!fileInput.files.length) {
				alert(bymuAdmin.strings.error + ': No file selected');
				return;
			}

			// Show progress on parse button
			const btn = $('#bymu-parse-btn');
			btn.prop('disabled', true).text('Parsing…');
			$('#bymu-upload-spinner').addClass('is-active').show();

			// Prepare form data
			const formData = new FormData();
			formData.append('action', 'bymu_parse_csv');
			formData.append('nonce', bymuAdmin.nonces.parse_csv);
			formData.append('csv_file', fileInput.files[0]);
			
			// Get selected post types
			const postTypes = $('#post_types').val() || [];
			postTypes.forEach(type => {
				formData.append('post_types[]', type);
			});

			// Get batch size
			this.batchSize = parseInt(bymuAdmin.settings.batchSize, 10) || 15;

			// AJAX request
			$.ajax({
				url: bymuAdmin.ajaxurl,
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				success: this.getBoundHandler('handleUploadSuccess'),
				error: this.getBoundHandler('handleUploadError')
			});
		},

		/**
		 * Handle upload success
		 */
		handleUploadSuccess: function(response) {
			$('#bymu-upload-spinner').removeClass('is-active').hide();
			$('#bymu-parse-btn').prop('disabled', false).html('🔍 Parse & Preview');

			if (!response.success) {
				alert(bymuAdmin.strings.error + ': ' + response.data);
				return;
			}

			// Store job data
			this.currentJobHash = response.data.job_hash;
			this.currentFileName = response.data.file_name;
			this.previewData = response.data.preview;
			this.postTypes = response.data.post_types || [];
			this.totalRows = response.data.preview.stats.total;

			// Show CSV parsing warnings if any
			if (response.data.warnings && response.data.warnings.length > 0) {
				let warningHTML = '<div class="bymu-alert warning"><div class="bymu-alert-icon dashicons dashicons-warning"></div><div class="bymu-alert-content"><ul>';
				response.data.warnings.forEach(warning => {
					warningHTML += '<li>' + this.escapeHtml(warning) + '</li>';
				});
				warningHTML += '</ul></div></div>';
				$('#bymu-preview-section').html(warningHTML);
			}

			// Render preview
			this.renderPreview(response.data.preview);

			// Scroll to preview
			$('html, body').animate({
				scrollTop: $('#bymu-preview-section').offset().top - 20
			}, 400);
		},

		/**
		 * Handle upload error
		 */
		handleUploadError: function(xhr, status, error) {
			$('#bymu-upload-spinner').removeClass('is-active').hide();
			$('#bymu-parse-btn').prop('disabled', false).html('🔍 Parse & Preview');
			alert(bymuAdmin.strings.error + ': ' + error);
		},

		/**
		 * Render preview table
		 */
		renderPreview: function(preview) {
			const stats = preview.stats;

		// Create preview section with header
		let html = `
			<div class="bymu-section bymu-section-compact">
				<div class="bymu-section-header">
					<h2>Preview Changes</h2>
					<p>Review changes before applying. Only rows with changes are shown below. Skipped rows (no changes) are hidden.</p>
				</div>
				<div class="bymu-section-body">
		`;

			// Render summary
			let summaryClass = 'info';
			if (stats.error > 0) summaryClass = 'error';
			else if (stats.warning > 0) summaryClass = 'warning';
			else if (stats.ok > 0) summaryClass = 'success';

			html += `
				<div class="bymu-alert ${summaryClass}">
					<div class="bymu-alert-icon ${summaryClass === 'success' ? 'dashicons dashicons-yes-alt' : summaryClass === 'error' ? 'dashicons dashicons-dismiss' : 'dashicons dashicons-warning'}"></div>
					<div class="bymu-alert-content">
						<p><strong>Preview Summary</strong></p>
						<ul style="margin: 8px 0 0 20px; padding: 0;">
							<li><strong>Total Rows:</strong> ${stats.total}</li>
							<li><strong>OK to Update:</strong> ${stats.ok}</li>
							<li><strong>Skipped:</strong> ${stats.skip}</li>
							<li><strong>Warnings:</strong> ${stats.warning}</li>
							<li><strong>Errors:</strong> ${stats.error}</li>
						</ul>
			`;

			if (stats.error > 0) {
				html += '<p style="margin-top: 8px;"><strong>Some rows have errors and will not be processed.</strong></p>';
			}

			if (stats.ok === 0) {
				html += '<p style="margin-top: 8px;"><strong>No rows will be updated. Please check your CSV data.</strong></p>';
			}

			html += '</div></div>';

		// Filter preview table to only show rows that will be changed (exclude 'skip' status)
		const rowsToChange = preview.results.filter(r => r.status !== 'skip');
		
		let tableHTML = `
			<table class="wp-list-table widefat fixed striped bymu-preview-table">
				<thead>
					<tr>
						<th>Row</th>
						<th>Post ID</th>
						<th>Post Title</th>
						<th>Field</th>
						<th>Current</th>
						<th>New</th>
						<th>Status</th>
						<th>Message</th>
					</tr>
				</thead>
				<tbody>
		`;

		const displayRows = rowsToChange.slice(0, 50);
			displayRows.forEach(row => {
				const statusClass = `bymu-status-${row.status}`;
				const rowNum = row.csv_row || row.row_number || 0; // Support both old and new
				
				// Display each field change
				if (row.changes && Object.keys(row.changes).length > 0) {
					Object.values(row.changes).forEach((change, idx) => {
						if (idx === 0) {
							// First field row
							tableHTML += `<tr>`;
							tableHTML += `<td rowspan="${Object.keys(row.changes).length}">${rowNum}</td>`;
							tableHTML += `<td rowspan="${Object.keys(row.changes).length}">${row.post_id}</td>`;
							tableHTML += `<td rowspan="${Object.keys(row.changes).length}">
								${row.post_info ? this.escapeHtml(row.post_info.title) : 'N/A'}
							</td>`;
						} else {
							tableHTML += `<tr>`;
						}
						
						tableHTML += `<td>${change.label}</td>`;
						tableHTML += `<td class="bymu-truncated" title="${this.escapeHtml(change.current)}">${this.escapeHtml(this.truncate(change.current, 50))}</td>`;
						tableHTML += `<td class="bymu-truncated" title="${this.escapeHtml(change.new)}">${this.escapeHtml(this.truncate(change.new, 50))}</td>`;
						tableHTML += `<td><span class="bymu-status ${statusClass}">${row.status}</span></td>`;
						tableHTML += `<td>${row.error || (row.warnings && row.warnings.length ? row.warnings.join(', ') : '')}</td>`;
						tableHTML += `</tr>`;
					});
				} else {
					// Row with error or no changes
					tableHTML += `<tr>`;
					tableHTML += `<td>${rowNum}</td>`;
					tableHTML += `<td>${row.post_id}</td>`;
					tableHTML += `<td>${row.post_info ? this.escapeHtml(row.post_info.title) : 'N/A'}</td>`;
					tableHTML += `<td colspan="3">-</td>`;
					tableHTML += `<td><span class="bymu-status ${statusClass}">${row.status}</span></td>`;
					tableHTML += `<td>${row.error || ''}</td>`;
					tableHTML += `</tr>`;
				}
			});

		if (rowsToChange.length > 50) {
			tableHTML += `
				<tr>
					<td colspan="8" style="text-align: center; background: #f0f0f1;">
						<em>Showing first 50 rows of ${rowsToChange.length} that will be changed. Skipped rows are hidden.</em>
					</td>
				</tr>
			`;
		}
		
		if (rowsToChange.length === 0 && preview.results.length > 0) {
			tableHTML += `
				<tr>
					<td colspan="8" style="text-align: center; padding: 30px;">
						<strong>No rows will be changed.</strong><br>
						All ${preview.results.length} rows were skipped (no changes detected).
					</td>
				</tr>
			`;
		}

			tableHTML += `</tbody></table>`;
			html += tableHTML;
			
			// Add action buttons
			const isDisabled = stats.ok === 0;
			html += `
				<div style="margin-top: 20px; display: flex; gap: 12px;">
					<button type="button" class="button button-primary button-large" id="bymu-apply-btn" ${isDisabled ? 'disabled' : ''}>
						✓ Apply Changes
					</button>
					<button type="button" class="button button-large" id="bymu-cancel-btn">
						Cancel
					</button>
				</div>
			`;
			
			// Close section
			html += '</div></div>';
			
			// Render to page
			$('#bymu-preview-section').html(html).show();
		},

		/**
		 * Handle apply changes
		 */
		handleApply: function() {
			if (!confirm('Are you sure you want to apply these changes? This will update your Yoast SEO meta fields.')) {
				return;
			}

			// Hide preview, show processing
			$('#bymu-preview-section').hide();
			
			// Create processing section
			const processingHTML = `
				<div class="bymu-section bymu-section-compact">
					<div class="bymu-section-header">
						<h2>Processing Updates</h2>
						<p>Applying changes in batches. Please wait...</p>
					</div>
					<div class="bymu-section-body">
						<div class="bymu-progress-container">
							<div class="bymu-progress-bar" id="bymu-progress-bar" style="width: 0%;">0%</div>
						</div>
						<p id="bymu-progress-text" style="margin-top: 12px; text-align: center;">Starting...</p>
					</div>
				</div>
			`;
			$('#bymu-processing-section').html(processingHTML).show();

			// Start batch processing
			this.processBatches();
		},

		/**
		 * Process batches
		 */
		processBatches: function() {
			const okRows = this.previewData.results.filter(r => r.status === 'ok' || r.status === 'warning');
			const totalBatches = Math.ceil(okRows.length / this.batchSize);
			let currentBatch = 0;
			let totalProcessed = 0;
			let totalUpdated = 0;
			let totalSkipped = 0;
			let totalErrors = 0;

			const processBatch = () => {
				if (currentBatch >= totalBatches) {
					// All batches done
					this.handleProcessingComplete(totalProcessed, totalUpdated, totalSkipped, totalErrors);
					return;
				}

				const start = currentBatch * this.batchSize;
				const end = start + this.batchSize;
				const batchRows = okRows.slice(start, end);

				currentBatch++;
				const progress = Math.round((currentBatch / totalBatches) * 100);

				// Update UI
				$('#bymu-progress-bar').css('width', progress + '%').text(progress + '%');
				$('#bymu-progress-text').text(`Processing batch ${currentBatch} of ${totalBatches}...`);

				// AJAX request
				$.ajax({
					url: bymuAdmin.ajaxurl,
					type: 'POST',
					data: {
						action: 'bymu_process_batch',
						nonce: bymuAdmin.nonces.process_batch,
						job_hash: this.currentJobHash,
						file_name: this.currentFileName,
						batch_rows: JSON.stringify(batchRows),
						batch_number: currentBatch,
						total_rows: this.totalRows,
						post_types: this.postTypes
					},
					success: (response) => {
						if (response.success) {
							totalProcessed += response.data.processed;
							totalUpdated += response.data.updated;
							totalSkipped += response.data.skipped;
							totalErrors += response.data.errors;
							
							// Process next batch
							setTimeout(processBatch, 100);
						} else {
							alert('Error processing batch: ' + response.data);
						}
					},
					error: (xhr, status, error) => {
						alert('AJAX error: ' + error);
					}
				});
			};

			// Start processing
			processBatch();
		},

		/**
		 * Handle processing complete
		 */
		handleProcessingComplete: function(processed, updated, skipped, errors) {
			// Hide processing
			$('#bymu-processing-section').hide();

			// Render results summary
			let summaryClass = 'success';
			if (errors > 0) summaryClass = 'error';

			const resultsHTML = `
				<div class="bymu-section bymu-section-compact">
					<div class="bymu-section-header">
						<h2>🎉 Results</h2>
						<p>Processing complete! Download logs for detailed audit trail.</p>
					</div>
					<div class="bymu-section-body">
						<div class="bymu-alert ${summaryClass}">
							<div class="bymu-alert-icon">${summaryClass === 'success' ? '✓' : '✗'}</div>
							<div class="bymu-alert-content">
								<p><strong>Processing Complete</strong></p>
								<ul style="margin: 8px 0 0 20px; padding: 0;">
									<li><strong>Rows Processed:</strong> ${processed}</li>
									<li><strong>Posts Updated:</strong> ${updated}</li>
									<li><strong>Skipped:</strong> ${skipped}</li>
									<li><strong>Errors:</strong> ${errors}</li>
								</ul>
							</div>
						</div>
						
						<div style="display: flex; gap: 12px; margin-top: 20px;">
							<button type="button" class="button" id="bymu-download-csv-btn">
								Download CSV Log
							</button>
							<button type="button" class="button" id="bymu-download-txt-btn">
								📝 Download TXT Log
							</button>
							<button type="button" class="button button-primary" id="bymu-new-job-btn">
								➕ Start New Job
							</button>
						</div>
						
						<div class="bymu-info-box" style="margin-top: 20px;">
							<div class="bymu-info-box-icon dashicons dashicons-info"></div>
							<div class="bymu-info-box-content">
								<p>Yoast SEO will reindex affected posts automatically. For very large updates (1000+ posts), you may also run: <strong>SEO → Tools → SEO Data Optimization</strong></p>
							</div>
						</div>
					</div>
				</div>
			`;

			$('#bymu-results-section').html(resultsHTML).show();

			// Mark job as complete
			$.ajax({
				url: bymuAdmin.ajaxurl,
				type: 'POST',
				data: {
					action: 'bymu_process_batch',
					nonce: bymuAdmin.nonces.process_batch,
					job_hash: this.currentJobHash,
					complete: true
				},
				success: (response) => {
					if (response.success) {
						console.log('Job marked as completed successfully');
					} else {
						console.error('Failed to mark job complete:', response.data);
					}
				},
				error: (xhr, status, error) => {
					console.error('Error marking job complete:', error);
				}
			});
		},

		/**
		 * Handle cancel
		 */
		handleCancel: function() {
			if (confirm('Are you sure you want to cancel? Preview data will be lost.')) {
				this.reset();
			}
		},

		/**
		 * Handle new job
		 */
		handleNewJob: function() {
			location.reload();
		},

		/**
		 * Handle download log
		 */
		handleDownloadLog: function(format) {
			if (!this.currentJobHash) return;

			const url = bymuAdmin.ajaxurl + 
				'?action=bymu_download_log' +
				'&nonce=' + bymuAdmin.nonces.download_log +
				'&job_hash=' + this.currentJobHash +
				'&format=' + format;

			window.open(url, '_blank');
		},

		/**
		 * Handle recent job download
		 */
		handleRecentJobDownload: function(e) {
			const btn = $(e.currentTarget);
			const jobId = btn.data('job-id');
			const format = btn.data('format');

			const url = bymuAdmin.ajaxurl +
				'?action=bymu_download_log' +
				'&nonce=' + bymuAdmin.nonces.download_log +
				'&job_id=' + jobId +
				'&format=' + format;

			window.open(url, '_blank');
		},

		/**
		 * Handle view log in modal
		 */
		handleViewLog: function(e) {
			e.preventDefault();
			
			const btn = $(e.currentTarget);
			const jobId = btn.data('job-id');

			// Show loading state
			btn.prop('disabled', true);
			const originalText = btn.html();
			btn.text('Loading…');

			// AJAX request
			$.ajax({
				url: bymuAdmin.ajaxurl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'bymu_view_log',
					nonce: bymuAdmin.nonces.view_log,
					job_id: jobId
				},
				success: (response) => {
					btn.prop('disabled', false);
					btn.html(originalText);

					console.log('View Log Response:', response);

					if (response.success) {
						// Debug logging
						console.log('Actions Count:', response.data.action_count);
						console.log('Content Length:', response.data.content ? response.data.content.length : 0);
						console.log('Content Preview:', response.data.content ? response.data.content.substring(0, 200) : 'EMPTY');
						
						// Update modal content
						$('#bymu-modal-title').text('Job Log: ' + response.data.file_name);
						
						if (response.data.content) {
							$('#bymu-log-content').text(response.data.content);
						} else {
							$('#bymu-log-content').text('No log content available. Actions count: ' + (response.data.action_count || 0));
						}
						
						// Show modal
						this.openModal();
					} else {
						alert('Error: ' + (response.data || 'Unknown error'));
						console.error('View log error:', response);
					}
				},
				error: (xhr, status, error) => {
					btn.prop('disabled', false);
					btn.html(originalText);
					
					// Log the actual response for debugging
					console.error('AJAX error details:', {
						status: status,
						error: error,
						responseText: xhr.responseText,
						statusCode: xhr.status
					});
					
					// Show user-friendly error with debug info
					let errorMsg = 'AJAX error: ' + error;
					if (xhr.responseText) {
						// Show first 200 chars of response for debugging
						errorMsg += '\n\nResponse preview:\n' + xhr.responseText.substring(0, 200);
					}
					alert(errorMsg + '\n\nCheck browser console for full details.');
				}
			});
		},

		/**
		 * Open log viewer modal
		 */
		openModal: function() {
			$('#bymu-log-modal').fadeIn(200);
			$('body').css('overflow', 'hidden');
		},

		/**
		 * Close log viewer modal
		 */
		closeModal: function() {
			$('#bymu-log-modal').fadeOut(200);
			$('body').css('overflow', '');
		},

		/**
		 * Handle clear logs
		 */
		handleClearLogs: function() {
			const retention = parseInt(bymuAdmin.settings && bymuAdmin.settings.logRetention ? bymuAdmin.settings.logRetention : 0, 10);
			let confirmMessage = 'Delete old job logs?\n\nThis will remove logs older than your retention setting.';

			if (retention > 0) {
				confirmMessage += `\n\nYour current retention keeps the most recent ${retention} job(s) per user.`;
			}

			if (!confirm(confirmMessage)) {
				return;
			}

			let forceAll = false;
			if (retention > 0) {
				forceAll = confirm('Do you also want to delete ALL job logs, including the most recent ones?\n\nClick "OK" to delete everything or "Cancel" to keep the newest logs within your retention limit.');
			}

			// Disable button and show loading
			const btn = $('#bymu-clear-logs');
			btn.prop('disabled', true);
			const originalText = btn.text();
			btn.text('Deleting...');

			$.ajax({
				url: bymuAdmin.ajaxurl,
				type: 'POST',
				data: {
					action: 'bymu_clear_old_logs',
					nonce: bymuAdmin.nonces.clear_logs,
					force_all: forceAll ? '1' : '0'
				},
				success: function(response) {
					btn.prop('disabled', false);
					btn.text(originalText);

					if (response.success) {
						alert(response.data.message);
						// Reload to show updated counts
						location.reload();
					} else {
						alert('Error: ' + (response.data || 'Unknown error occurred'));
					}
				},
				error: function(xhr, status, error) {
					btn.prop('disabled', false);
					btn.text(originalText);
					alert('AJAX error: ' + error + '\n\nPlease check if the function is properly registered.');
					console.error('Clear logs error:', xhr, status, error);
				}
			});
		},

		/**
		 * Handle optimize database
		 */
		handleOptimizeDB: function() {
			if (!confirm('Optimize plugin database tables?')) return;

			// Disable button and show loading
			const btn = $('#bymu-optimize-db');
			btn.prop('disabled', true);
			const originalText = btn.text();
			btn.text('Optimizing...');

			$.ajax({
				url: bymuAdmin.ajaxurl,
				type: 'POST',
				data: {
					action: 'bymu_optimize_database',
					nonce: bymuAdmin.nonces.optimize_db
				},
				success: function(response) {
					btn.prop('disabled', false);
					btn.text(originalText);

					if (response.success) {
						alert(response.data.message);
					} else {
						alert('Error: ' + response.data);
					}
				},
				error: function(xhr, status, error) {
					btn.prop('disabled', false);
					btn.text(originalText);
					alert('AJAX error: ' + error);
				}
			});
		},

		/**
		 * Handle uninstall from Plugins page
		 */
		handleUninstallFromPlugins: function(e) {
			e.preventDefault();
			
			const link = $(e.currentTarget);
			const jobCount = link.data('job-count');
			const actionCount = link.data('action-count');

			// First confirmation
			if (!confirm(`WARNING: This will permanently delete:\n\n` +
				`• ${jobCount} job logs\n` +
				`• ${actionCount} action records\n` +
				`• All plugin settings\n` +
				`• Database tables (bymu_jobs, bymu_actions)\n\n` +
				`Your Yoast SEO data will NOT be affected.\n\n` +
				`This action CANNOT be undone!\n\n` +
				`Are you absolutely sure?`)) {
				return;
			}

			// Second confirmation with typed DELETE
			const confirmText = prompt(
				'Type DELETE (in capital letters) to confirm permanent data deletion:',
				''
			);

			if (confirmText !== 'DELETE') {
				alert('Uninstall cancelled. You must type DELETE exactly.');
				return;
			}

			// Show loading on the link
			link.text('Uninstalling…');

			// Perform uninstall
			$.ajax({
				url: bymuAdmin.ajaxurl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'bymu_manual_uninstall',
					nonce: bymuAdmin.nonces.uninstall
				},
				success: (response) => {
					console.log('Uninstall response:', response);
					
					if (response.success) {
						// Redirect to plugins page - admin notice will show there
						window.location.href = response.data.redirect || 'plugins.php';
					} else {
						alert('Error: ' + (response.data || 'Unknown error'));
						link.html('<span style="color: #d63638; font-weight: 600;">Uninstall</span>');
					}
				},
				error: (xhr, status, error) => {
					console.error('Uninstall AJAX Error Details:');
					console.error('Status:', status);
					console.error('Error:', error);
					console.error('Response Text:', xhr.responseText);
					console.error('Status Code:', xhr.status);
					
					let errorMsg = 'AJAX error: ' + error;
					if (xhr.responseText) {
						errorMsg += '\n\nServer response: ' + xhr.responseText.substring(0, 200);
					}
					
					alert(errorMsg);
					link.html('<span style="color: #d63638; font-weight: 600;">Uninstall</span>');
				}
			});
		},

		/**
		 * Reset to initial state
		 */
		reset: function() {
			this.currentJobHash = null;
			this.currentFileName = null;
			this.previewData = null;
			
			$('#bymu-preview-section').hide().html('');
			$('#bymu-processing-section').hide().html('');
			$('#bymu-results-section').hide().html('');
			
			// Reset file upload
			$('#csv_file').val('');
			$('#bymu-file-info').hide();
			$('#bymu-drop-zone').show();
			$('#bymu-parse-btn').prop('disabled', true);
			
			// Scroll to top
			$('html, body').animate({ scrollTop: 0 }, 300);
		},

		/**
		 * Escape HTML
		 */
		escapeHtml: function(text) {
			if (!text) return '';
			const div = document.createElement('div');
			div.textContent = text;
			return div.innerHTML;
		},

		/**
		 * Truncate text
		 */
		truncate: function(text, length) {
			if (!text) return '';
			if (text.length <= length) return text;
			return text.substring(0, length) + '...';
		},

		/**
		 * Handle generate image alt text
		 */
		handleGenerateImageAlt: function(e) {
			e.preventDefault();
			
			const btn = $(e.currentTarget);
			const attachmentId = btn.data('attachment-id');
			const statusSpan = btn.siblings('.bymu-alt-status');
			
		// Find the alt text input field (multiple selectors for different contexts)
		let altInput = btn.closest('td').find('input[id^="attachments"][id$="image_alt"]');
		
		// Fallback 1: Attachment edit screen (post.php)
		if (!altInput.length) {
			altInput = $('#attachment_alt, input[name="_wp_attachment_image_alt"]');
		}
		
		// Fallback 2: Try within the same container
		if (!altInput.length) {
			altInput = btn.closest('.compat-field-image_alt').find('input[type="text"]');
		}
		
		// Fallback 3: Try by name attribute
		if (!altInput.length) {
			altInput = btn.closest('td, .compat-field-image_alt').find('input[name*="image_alt"]');
		}
		
		// Fallback 4: Look for alt text input anywhere near the button
		if (!altInput.length) {
			altInput = btn.closest('tr, .compat-attachment-fields').find('input[id*="alt"]');
		}
		
		// Fallback 5: Check previous sibling element
		if (!altInput.length) {
			altInput = btn.parent().prev('input[type="text"]');
		}
			
			// Show loading state
			btn.prop('disabled', true).text('Generating…');
			statusSpan.html('<span style="color: #2271b1;">Analyzing image with AI...</span>');
			this.log('Image Alt (media): generate request', { attachmentId, hasInput: !!altInput.length });
			
			$.ajax({
				url: bymuAdmin.ajaxurl,
				type: 'POST',
				data: {
					action: 'bymu_generate_image_alt',
					nonce: bymuAdmin.nonces.generate_image_alt,
					attachment_id: attachmentId
				},
				success: (response) => {
					btn.prop('disabled', false).text('Generate with AI');
					
					if (response.success) {
						this.log('Image Alt (media): success', { attachmentId });
						// Set the alt text in the input field
						if (altInput.length) {
							altInput.val(response.data.alt_text);
							// Trigger change event for WordPress to detect
							altInput.trigger('change');
							
							// Also trigger input event for better compatibility
							altInput.trigger('input');
							
							// Flash the field to show it was updated
							altInput.css('background-color', '#d4edda');
							setTimeout(() => {
								altInput.css('background-color', '');
							}, 1000);
						} else {
							console.warn('Alt text input field not found');
							console.log('Button:', btn);
							console.log('Attachment ID:', attachmentId);
						}
						
						statusSpan.html('<span style="color: #00a32a; font-weight: 600;">✓ ' + 
							this.escapeHtml(response.data.message) + '</span>');
						
						// Clear success message after 5 seconds
						setTimeout(() => {
							statusSpan.html('');
						}, 5000);
					} else {
						this.log('Image Alt (media): server error', { attachmentId, response });
						statusSpan.html('<span style="color: #d63638;">✗ ' + 
							this.escapeHtml(response.data) + '</span>');
						
						// Clear error message after 10 seconds
						setTimeout(() => {
							statusSpan.html('');
						}, 10000);
					}
				},
				error: (xhr, status, error) => {
					btn.prop('disabled', false).text('Generate with AI');
					statusSpan.html('<span style="color: #d63638;">✗ AJAX error: ' + error + '</span>');
					this.log('Image Alt (media): ajax error', { attachmentId, status, error, responseText: xhr && xhr.responseText });
					console.error('Image alt generation error:', xhr.responseText);
					
					// Clear error message after 10 seconds
					setTimeout(() => {
						statusSpan.html('');
					}, 10000);
				}
			});
		},

		/**
		 * Initialize documentation features
		 */
		initDocumentation: function() {
			// Smooth scroll for TOC links
			$('.bymu-doc-toc-list a').on('click', function(e) {
				e.preventDefault();
				const targetId = $(this).attr('href');
				const targetElement = $(targetId);
				
				if (targetElement.length) {
					// Scroll to target with offset for fixed headers
					$('html, body').animate({
						scrollTop: targetElement.offset().top - 20
					}, 400);
					
					// Update URL hash without jumping
					if (history.pushState) {
						history.pushState(null, null, targetId);
					}
					
					// Highlight the target briefly
					targetElement.css('background', '#fff3cd');
					setTimeout(function() {
						targetElement.css('background', '');
					}, 1500);
				}
			});
			
			// Highlight current section in TOC while scrolling
			if ($('.bymu-doc-toc-list').length) {
				this.bindEvent($(window), 'scroll', 'updateTOCHighlight');
			}
		},

		/**
		 * Update TOC highlight based on scroll position
		 */
		updateTOCHighlight: function() {
			const scrollTop = $(window).scrollTop();
			const headers = $('.bymu-doc-content h2[id]');
			
			let current = '';
			headers.each(function() {
				const top = $(this).offset().top - 50;
				if (scrollTop >= top) {
					current = '#' + $(this).attr('id');
				}
			});
			
			if (current) {
				$('.bymu-doc-toc-list a').removeClass('active');
				$('.bymu-doc-toc-list a[href="' + current + '"]').addClass('active');
			}
		},

		/**
		 * Lazy-load attachment reference counts after page load (performance optimization).
		 */
		loadAttachmentReferences: function() {
			// Only run on image alt page.
			if (!$('.bymu-loading-refs').length) {
				return;
			}

			// Collect all attachment IDs that need reference data loaded.
			const attachmentIds = [];
			$('.bymu-loading-refs').each(function() {
				const $cell = $(this).closest('td[data-attachment-ref-id]');
				if ($cell.length) {
					const attachmentId = $cell.data('attachment-ref-id');
					if (attachmentId && attachmentIds.indexOf(attachmentId) === -1) {
						attachmentIds.push(attachmentId);
					}
				}
			});

			if (attachmentIds.length === 0) {
				return;
			}

			// Load reference counts via AJAX (batch request).
			$.ajax({
				url: window.bymuAdmin.ajaxurl,
				type: 'POST',
				data: {
					action: 'bymu_load_attachment_refs',
					nonce: window.bymuAdmin.nonces.load_attachment_refs,
					attachment_ids: attachmentIds
				},
				success: function(response) {
					if (response.success && response.data) {
						// Update each row with reference data.
						$.each(response.data, function(attachmentId, refData) {
							const $cell = $('td[data-attachment-ref-id="' + attachmentId + '"]');
							if ($cell.length) {
								const count = parseInt(refData.count, 10) || 0;
								const posts = refData.posts || [];

								// Remove loading indicator.
								$cell.find('.bymu-loading-refs').remove();

								// Render reference data.
								if (count > 0) {
									let html = '<p class="bymu-reference-count">' + count + ' post(s)</p>';
									html += '<ul class="bymu-reference-list">';
									
									// Limit to first 10 posts for display.
									const displayPosts = posts.slice(0, 10);
									$.each(displayPosts, function(i, post) {
										// Post can be an object with id/title/url or just an ID (backward compat).
										const postId = post.id || post;
										const postTitle = $('<div>').text(post.title || ('Post #' + postId)).html();
										const postUrl = post.url || '';
										
										if (postUrl) {
											// Use jQuery to properly create the link element to avoid double-escaping.
											// This ensures & remains as & in the href attribute, not &amp;
											const $link = $('<a></a>').attr('href', postUrl).text(post.title || ('Post #' + postId));
											html += '<li>' + $link[0].outerHTML + '</li>';
										} else {
											html += '<li>' + postTitle + '</li>';
										}
									});
									
									if (posts.length > 10) {
										html += '<li><em>... and ' + (posts.length - 10) + ' more</em></li>';
									}
									
									html += '</ul>';
									$cell.html(html);
								} else {
									$cell.html('<span class="dashicons dashicons-minus"></span>');
								}
							}
						});
					}
				},
				error: function() {
					// On error, just remove loading indicators.
					$('.bymu-loading-refs').each(function() {
						$(this).closest('td[data-attachment-ref-id]').html('<span class="dashicons dashicons-minus"></span>');
					});
				}
			});
		}
	};

	// Initialize on document ready
	$(document).ready(function() {
		BYMU.init();
	});

})(jQuery);

