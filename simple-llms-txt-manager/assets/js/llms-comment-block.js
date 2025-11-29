'use strict';

(function registerMdLlmsSnippetBlock(blocks, element, blockEditor, components, i18n) {
	if (!blocks || !element || !blockEditor || !components || !i18n) {
		return;
	}

	const { registerBlockType } = blocks;
	const { createElement, Fragment } = element;
	const { useBlockProps, InspectorControls } = blockEditor;
	const { TextareaControl, Notice, PanelBody } = components;
	const { __, sprintf } = i18n;

	const mergeFieldMap = (window.mdLlmsTxtBlockData && window.mdLlmsTxtBlockData.mergeFields) || {};
	const mergeFieldEntries = Object.keys(mergeFieldMap).map(function mapEntry(key) {
		return [key, mergeFieldMap[key]];
	});

	registerBlockType('md-llms/llm-snippet', {
		apiVersion: 2,
		title: __('LLM Markdown Snippet', 'md-llms-txt'),
		description: __('Store Markdown guidance for LLM providers. Content stays hidden from visitors.', 'md-llms-txt'),
		icon: 'excerpt-view',
		category: 'widgets',
		supports: {
			html: false,
		},
		attributes: {
			content: {
				type: 'string',
				default: '',
			},
		},
		edit({ attributes, setAttributes }) {
			const blockProps = useBlockProps({ className: 'md-llms-txt-snippet-editor' });
			const value = attributes.content || '';

			const handleChange = function handleChange(nextValue) {
				setAttributes({ content: nextValue || '' });
			};

			const mergeFieldList = mergeFieldEntries.map(function mapField(entry) {
				return createElement(
					'li',
					{ key: entry[0] },
					createElement('code', null, entry[0]),
					' — ',
					entry[1]
				);
			});

			return createElement(
				Fragment,
				null,
				createElement(
					InspectorControls,
					null,
					mergeFieldList.length
						? createElement(
							PanelBody,
							{
								title: sprintf(
									__('Merge Fields (%d)', 'md-llms-txt'),
									mergeFieldList.length
								),
								initialOpen: false,
							},
							createElement(
								'p',
								{
									style: {
										fontSize: '12px',
										lineHeight: 1.5,
										marginBottom: '0.75em',
									},
								},
								__(
									'Insert these placeholders within your snippet to output dynamic page data.',
									'md-llms-txt'
								)
							),
							createElement(
								'ul',
								{
									style: {
										fontSize: '12px',
										lineHeight: 1.4,
										margin: 0,
										paddingLeft: '1.5em',
									},
								},
								mergeFieldList
							)
						)
						: null
				),
				createElement(
					'div',
					blockProps,
					createElement(
						Notice,
						{
							status: 'info',
							isDismissible: false,
							className: 'md-llms-txt-snippet-editor__notice',
						},
						__('This block stores Markdown that is shared with LLM providers as an HTML comment.', 'md-llms-txt')
					),
					createElement(TextareaControl, {
						label: __('LLM Markdown Snippet', 'md-llms-txt'),
						help: __('Use Markdown syntax. The content remains hidden from visitors and surfaces only in the HTML comment.', 'md-llms-txt'),
						value,
						onChange: handleChange,
						rows: 12,
					})
				)
			);
		},
		save() {
			return null;
		},
	});
}(window.wp && window.wp.blocks, window.wp && window.wp.element, window.wp && window.wp.blockEditor, window.wp && window.wp.components, window.wp && window.wp.i18n));

