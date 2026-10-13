<?php
/**
 * Gutenberg Block Server-Side Renderer.
 *
 * @package WPCalibrate\GoldRateDisplay
 */

declare(strict_types=1);

namespace WPCalibrate\GoldRateDisplay\Display;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPCALIBRATE_TEST_MODE' ) ) {
	exit;
}

/**
 * Handles server-side registration and rendering for wpcalibrate/gold-rate block.
 */
final class BlockRenderer {

	/**
	 * Register the Gutenberg block.
	 */
	public function init(): void {
		add_action( 'init', array( $this, 'register_block' ) );
	}

	/**
	 * Register block type with metadata.
	 */
	public function register_block(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		// Register block editor assets.
		wp_register_script(
			'wpcalibrate-grd-block-editor',
			WPCALIBRATE_GRD_URL . 'blocks/gold-rate/index.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n' ),
			WPCALIBRATE_GRD_VERSION,
			true
		);

		wp_register_style(
			'wpcalibrate-grd-block-editor-style',
			WPCALIBRATE_GRD_URL . 'blocks/gold-rate/editor.css',
			array(),
			WPCALIBRATE_GRD_VERSION
		);

		$block_dir = WPCALIBRATE_GRD_PATH . 'blocks/gold-rate';
		if ( file_exists( $block_dir . '/block.json' ) ) {
			register_block_type(
				$block_dir,
				array(
					'render_callback' => array( $this, 'render_block' ),
				)
			);
		}
	}

	/**
	 * Server-side render callback for block.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $content    Block inner content.
	 * @return string HTML output.
	 */
	public function render_block( array $attributes = array(), string $content = '' ): string {
		$shortcode_renderer = new ShortcodeRenderer();
		return $shortcode_renderer->render_shortcode( $attributes );
	}
}
