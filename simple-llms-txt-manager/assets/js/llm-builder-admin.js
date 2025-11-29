(function ($) {
	const form = $('#md-llms-builder-form');
	const logBox = $('#md-llms-builder-log');
	const spinner = $('#md-llms-builder-spinner');
	const messageBox = $('#md-llms-builder-messages');
	const providerRadios = $('input[name="provider"]');
	const modelSelect = $('#md_llms_model');
	const modal = $('#md-llms-modal');
	const modalBody = $('#md-llms-modal-body');
	const copyButton = $('#md-llms-modal-copy');
	const strings = (window.mdLlmsBuilder && mdLlmsBuilder.i18n) ? mdLlmsBuilder.i18n : {};
	let pollTimer = null;
	let activeJobId = null;
	let modalCopyBuffer = '';
	let copyResetTimer = null;

	const t = (key, fallback) => (strings && strings[key] ? strings[key] : fallback);

	function setMessage(type, text) {
		messageBox
			.removeClass('notice-error notice-success notice-info')
			.addClass(`notice notice-${type}`)
			.text(text)
			.show();
	}

	function resetMessage() {
		messageBox.hide().text('');
	}

	function startPolling(jobId) {
		activeJobId = jobId;
		if (pollTimer) {
			clearInterval(pollTimer);
		}

		const poll = () => {
			$.get(
				mdLlmsBuilder.ajaxUrl,
				{
					action: 'md_llms_builder_status',
					job_id: jobId,
					_wpnonce: mdLlmsBuilder.nonce,
				}
			)
				.done((response) => {
					if (!response.success || !response.data || !response.data.job) {
						return;
					}

					const job = response.data.job;
					renderLogs(job.logs || []);
					updateJobRow(job);

					if ('completed' === job.status) {
						setMessage('success', mdLlmsBuilder.i18n.complete || 'Job finished. Download is ready.');
						clearInterval(pollTimer);
						spinner.removeClass('is-active');
					} else if ('failed' === job.status) {
						setMessage('error', job.error || 'Job failed.');
						clearInterval(pollTimer);
						spinner.removeClass('is-active');
					}
				})
				.fail(() => {
					clearInterval(pollTimer);
					spinner.removeClass('is-active');
				});
		};

		poll();
		pollTimer = setInterval(poll, 5000);
	}

	function renderLogs(logs) {
		if (!Array.isArray(logs) || !logs.length) {
			return;
		}
		const lines = logs.map((entry) => `[${entry.timestamp}] ${entry.message}`);
		logBox.text(lines.join('\n'));
	}

	function updateJobRow(job) {
		const row = $(`.md-llms-table tr[data-job-id="${job.id}"]`);
		if (!row.length) {
			return;
		}

		const statusCell = row.find('.status');
		if (statusCell.length) {
			statusCell
				.attr('class', `status status-${job.status}`)
				.text(job.status.charAt(0).toUpperCase() + job.status.slice(1));
		}

		if (typeof job.page_count !== 'undefined') {
			const uniqueCell = row.find('td').eq(2);
			if (uniqueCell.length) {
				if (job.page_count > 0) {
					uniqueCell.text(`${job.page_count} ${job.page_count === 1 ? 'URL' : 'URLs'}`);
				} else if (job.status === 'completed') {
					uniqueCell.text(t('notCaptured', 'Not captured'));
				} else {
					uniqueCell.text('—');
				}
			}
		}

		const updatedCell = row.find('td').eq(3);
		if (updatedCell.length && job.updated_at) {
			updatedCell.text(job.updated_at);
		}

		if ('completed' === job.status) {
			const actionsCell = row.find('.md-llms-table__actions');
			if (actionsCell.length) {
				let actionsHtml = `<button class="button button-small md-llms-builder-download" data-job-id="${job.id}">${t('download', 'Download')}</button>`;
				if (job.page_count && job.page_count > 0) {
					actionsHtml += ` <button class="button button-small button-secondary md-llms-builder-view" data-job-id="${job.id}">${t('view', 'View')}</button>`;
				}
				actionsCell.html(actionsHtml);
			}
		}
	}

	form.on('submit', function (event) {
		event.preventDefault();
		resetMessage();
		logBox.text('');
		spinner.addClass('is-active');

		const payload = form.serialize() + '&action=md_llms_builder_create';

		$.post(mdLlmsBuilder.ajaxUrl, payload)
			.done((response) => {
				if (!response.success || !response.data || !response.data.jobId) {
					spinner.removeClass('is-active');
					const errorMessage = response && response.data && response.data.message ? response.data.message : 'Unable to start job.';
					setMessage('error', errorMessage);
					return;
				}

				setMessage('info', mdLlmsBuilder.i18n.starting);
				startPolling(response.data.jobId);
			})
			.fail(() => {
				spinner.removeClass('is-active');
				setMessage('error', 'Request failed. Please try again.');
			});
	});

	$(document).on('click', '.md-llms-builder-download', function (event) {
		event.preventDefault();
		const jobId = $(this).data('job-id');
		if (!jobId) {
			return;
		}
		const url = `${mdLlmsBuilder.ajaxUrl}?action=md_llms_builder_download&job_id=${jobId}&_wpnonce=${mdLlmsBuilder.nonce}`;
		window.location.href = url;
	});

	$(document).on('click', '.md-llms-builder-view', function (event) {
		event.preventDefault();
		const jobId = $(this).data('job-id');
		if (!jobId) {
			return;
		}
		openModal();
		modalBody.html(`<p>${t('loading', 'Loading…')}</p>`);
		modalCopyBuffer = '';
		copyButton.prop('disabled', true);

		$.get(
			mdLlmsBuilder.ajaxUrl,
			{
				action: 'md_llms_builder_job_pages',
				job_id: jobId,
				_wpnonce: mdLlmsBuilder.nonce,
			}
		)
			.done((response) => {
				if (!response.success || !response.data) {
					modalBody.html(`<p>${t('empty', 'No URLs available for this job.')}</p>`);
					return;
				}
				renderModalList(response.data.pages || []);
			})
			.fail(() => {
				modalBody.html(`<p>${t('empty', 'No URLs available for this job.')}</p>`);
			});
	});

	$(document).on('click', '[data-md-llms-close]', function (event) {
		event.preventDefault();
		closeModal();
	});

	$(document).on('keydown', function (event) {
		if ('Escape' === event.key && modal.hasClass('is-visible')) {
			closeModal();
		}
	});

	copyButton.on('click', function () {
		if (!modalCopyBuffer) {
			return;
		}

		const fallbackCopy = () => {
			const temp = $('<textarea>').css({ position: 'absolute', left: '-9999px' }).text(modalCopyBuffer);
			$('body').append(temp);
			temp[0].select();
			try {
				document.execCommand('copy');
				temp.remove();
				return true;
			} catch (err) {
				temp.remove();
				return false;
			}
		};

		const done = (success) => {
			clearTimeout(copyResetTimer);
			copyButton.text(success ? t('copied', 'Copied') : t('copyError', 'Unable to copy'));
			copyResetTimer = setTimeout(() => {
				copyButton.text(t('copyAll', 'Copy All'));
			}, 1500);
		};

		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(modalCopyBuffer).then(() => done(true)).catch(() => done(false));
		} else {
			done(fallbackCopy());
		}
	});

	function openModal() {
		modal.attr('aria-hidden', 'false').addClass('is-visible');
	}

	function closeModal() {
		modal.attr('aria-hidden', 'true').removeClass('is-visible');
	}

	function renderModalList(pages) {
		if (!pages.length) {
			modalBody.html(`<p>${t('empty', 'No URLs available for this job.')}</p>`);
			copyButton.prop('disabled', true);
			modalCopyBuffer = '';
			return;
		}

		const items = pages
			.map((entry) => {
				const title = entry.title || entry.url;
				return `<li><strong>${title}</strong><br/><code>${entry.url}</code></li>`;
			})
			.join('');

		modalBody.html(`<ol class="md-llms-link-list">${items}</ol>`);
		modalCopyBuffer = pages.map((item) => `${item.title || item.url} - ${item.url}`).join('\n');
		copyButton.prop('disabled', false).text(t('copyAll', 'Copy All'));
	}

	function initialiseProviderControls() {
		if (!providerRadios.length || !modelSelect.length || !window.mdLlmsBuilder) {
			return;
		}

		const defaults = mdLlmsBuilder.defaults || {};
		const modelsMap = mdLlmsBuilder.models || {};

		const syncModels = (provider) => {
			const providerModels = modelsMap[provider] || [];
			const selectedModel = defaults.models && defaults.models[provider] ? defaults.models[provider] : (providerModels[0] || 'gpt-4o-mini');

			if ('none' === provider) {
				const fallbackLabel = t('metaOnlyModel', 'Meta description fallback');
				modelSelect.html(`<option value="meta" selected>${fallbackLabel}</option>`);
				modelSelect.prop('disabled', true);
				return;
			}

			modelSelect.prop('disabled', false).empty();

			if (providerModels.length === 0) {
				modelSelect.append(`<option value="${selectedModel}">${selectedModel}</option>`);
			} else {
				providerModels.forEach((model) => {
					modelSelect.append(`<option value="${model}">${model}</option>`);
				});
			}

			modelSelect.val(selectedModel);
		};

		providerRadios.on('change', function (event) {
			const provider = event.currentTarget.value;
			providerRadios.closest('.md-llms-radio').removeClass('is-active');
			$(event.currentTarget).closest('.md-llms-radio').addClass('is-active');
			syncModels(provider);
		});

		const selectedRadio = providerRadios.filter(':checked');
		if (selectedRadio.length) {
			selectedRadio.closest('.md-llms-radio').addClass('is-active');
			syncModels(selectedRadio.val());
		} else {
			syncModels(defaults.provider || 'none');
		}
	}

	initialiseProviderControls();
}(jQuery));

