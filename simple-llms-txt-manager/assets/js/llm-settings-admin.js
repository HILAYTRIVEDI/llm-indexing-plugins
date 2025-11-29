(function ($) {
	const config = window.mdLlmsSettings || {};
	if (!config.ajaxUrl || !config.nonce) {
		return;
	}

	const labels = config.labels || {};
	const getLabel = (key, fallback) => (labels[key] ? labels[key] : fallback);

	$(document).on('click', '.md-llms-refresh-models', function (event) {
		event.preventDefault();
		const button = $(this);
		const provider = button.data('provider');
		const targetSelector = button.data('target');
		const keySelector = button.data('key');
		const select = targetSelector ? $(targetSelector) : $();
		const keyInput = keySelector ? $(keySelector) : $();

		if (!provider || !select.length) {
			return;
		}

		const originalText = button.text();
		button.prop('disabled', true).text(getLabel('refreshing', 'Refreshing…'));

		let apiKey = keyInput.length ? keyInput.val().trim() : '';
		if (!apiKey) {
			apiKey = '__use_stored__';
		}

		$.post(
			config.ajaxUrl,
			{
				action: 'md_llms_fetch_models',
				provider,
				apiKey,
				_wpnonce: config.nonce,
			}
		)
			.done((response) => {
				if (response && response.success && response.data && response.data.models) {
					select.empty();
					response.data.models.forEach((model) => {
						select.append(`<option value="${model}">${model}</option>`);
					});
					if (response.data.default) {
						select.val(response.data.default);
					}
					window.alert(getLabel('updated', 'Model list updated.'));
				} else {
					const message = response && response.data && response.data.message ? response.data.message : getLabel('error', 'Unable to refresh models.');
					window.alert(message);
				}
			})
			.fail(() => {
				window.alert(getLabel('error', 'Unable to refresh models.'));
			})
			.always(() => {
				button.prop('disabled', false).text(originalText);
			});
	});

	const providerRadios = $('input[name="md_llms_default_provider"]');
	if (providerRadios.length) {
		const syncRadios = () => {
			providerRadios.each(function () {
				const wrapper = $(this).closest('.md-llms-radio');
				if (wrapper.length) {
					wrapper.toggleClass('is-active', this.checked);
				}
			});
		};

		providerRadios.on('change', syncRadios);
		syncRadios();
	}
}(jQuery));

