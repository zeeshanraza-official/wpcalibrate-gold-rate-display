/**
 * Gutenberg Block Editor Script: wpcalibrate/gold-rate
 */
(function(wp) {
	'use strict';

	const { registerBlockType } = wp.blocks;
	const { createElement: el, Fragment } = wp.element;
	const { InspectorControls } = wp.blockEditor || wp.editor;
	const { PanelBody, SelectControl, ToggleControl, TextControl } = wp.components;
	const ServerSideRender = wp.serverSideRender || wp.components.ServerSideRender;
	const { __ } = wp.i18n;

	registerBlockType('wpcalibrate/gold-rate', {
		edit: function(props) {
			const { attributes, setAttributes } = props;

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __('Display Options', 'wpcalibrate-gold-rate-display'), initialOpen: true },
						el(TextControl, {
							label: __('Custom Heading', 'wpcalibrate-gold-rate-display'),
							value: attributes.heading || '',
							onChange: function(val) { setAttributes({ heading: val }); }
						}),
						el(SelectControl, {
							label: __('Currency', 'wpcalibrate-gold-rate-display'),
							value: attributes.currency,
							options: [
								{ label: 'USD ($)', value: 'USD' },
								{ label: 'GBP (£)', value: 'GBP' },
								{ label: 'EUR (€)', value: 'EUR' },
								{ label: 'PKR (₨)', value: 'PKR' },
								{ label: 'INR (₹)', value: 'INR' },
								{ label: 'AED (د.إ)', value: 'AED' },
								{ label: 'SAR (﷼)', value: 'SAR' },
								{ label: 'QAR (﷼)', value: 'QAR' },
								{ label: 'KWD (د.ك)', value: 'KWD' },
								{ label: 'CAD (CA$)', value: 'CAD' },
								{ label: 'AUD (AU$)', value: 'AUD' },
								{ label: 'SGD (SG$)', value: 'SGD' }
							],
							onChange: function(val) { setAttributes({ currency: val }); }
						}),
						el(SelectControl, {
							label: __('Weight Unit', 'wpcalibrate-gold-rate-display'),
							value: attributes.unit,
							options: [
								{ label: __('Gram (g)', 'wpcalibrate-gold-rate-display'), value: 'gram' },
								{ label: __('Troy Ounce (oz t)', 'wpcalibrate-gold-rate-display'), value: 'ounce' },
								{ label: __('Tola', 'wpcalibrate-gold-rate-display'), value: 'tola' }
							],
							onChange: function(val) { setAttributes({ unit: val }); }
						}),
						el(SelectControl, {
							label: __('Layout', 'wpcalibrate-gold-rate-display'),
							value: attributes.layout,
							options: [
								{ label: __('Cards (Visual Grid)', 'wpcalibrate-gold-rate-display'), value: 'cards' },
								{ label: __('Table', 'wpcalibrate-gold-rate-display'), value: 'table' },
								{ label: __('Compact Ribbon', 'wpcalibrate-gold-rate-display'), value: 'compact' }
							],
							onChange: function(val) { setAttributes({ layout: val }); }
						}),
						el(ToggleControl, {
							label: __('Show Spot Price', 'wpcalibrate-gold-rate-display'),
							checked: !!attributes.show_spot,
							onChange: function(val) { setAttributes({ show_spot: val }); }
						}),
						el(ToggleControl, {
							label: __('Show Price Change', 'wpcalibrate-gold-rate-display'),
							checked: !!attributes.show_change,
							onChange: function(val) { setAttributes({ show_change: val }); }
						}),
						el(ToggleControl, {
							label: __('Show Last Updated Time', 'wpcalibrate-gold-rate-display'),
							checked: !!attributes.show_updated,
							onChange: function(val) { setAttributes({ show_updated: val }); }
						})
					)
				),
				el(
					'div',
					{ className: 'wpcalibrate-grd-editor-preview' },
					ServerSideRender
						? el(ServerSideRender, {
								block: 'wpcalibrate/gold-rate',
								attributes: attributes
						  })
						: el('div', { className: 'wpcalibrate-gold-rate-display' }, __('Loading live preview...', 'wpcalibrate-gold-rate-display'))
				)
			);
		},
		save: function() {
			// Fully server-rendered block.
			return null;
		}
	});
})(window.wp);
