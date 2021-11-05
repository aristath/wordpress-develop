<?php
/**
 * Webfonts API: Webfonts functions
 *
 * @since 5.9.0
 *
 * @package WordPress
 * @subpackage Webfonts
 */

/**
 * Instantiates the webfonts controller, if not already set, and returns it.
 *
 * @since 5.9.0
 *
 * @return WP_Webfonts_Controller Instance of the controller.
 */
function wp_webfonts() {
	static $instance;

	if ( ! $instance instanceof WP_Webfonts ) {
		$instance = new WP_Webfonts_Controller(
			new WP_Webfonts_Registry(
				new WP_Webfonts_Schema_Validator()
			),
			new WP_Webfonts_Provider_Registry()
		);
		$instance->init();
	}

	return $instance;
}

/**
 * Registers a webfont collection.
 *
 * @since 5.9.0
 *
 * @param array $webfonts Webfonts to be registered.
 *                        This contains ar array of webfonts to be registered. Each webfont is an array.
 *                        See {@see WP_Webfonts_Registry::register()} for a list of supported arguments for each webfont.
 */
function wp_register_webfonts( array $webfonts ) {
	// Bail out if the webfonts collection is empty.
	if ( empty( $webfonts ) ) {
		return;
	}

	foreach ( $webfonts as $webfont ) {
		wp_webfonts()->webfonts()->register( $webfont );
	}
}

/**
 * Registers a single webfont.
 *
 * @since 5.9.0
 *
 * @param array $webfont Webfont to be registered.
 *                       See {@see WP_Webfonts_Registry::register()} for a list of supported arguments.
 * @return string Registration key.
 */
function wp_register_webfont( array $webfont ) {
	return wp_webfonts()->webfonts()->register( $webfont );
}

/**
 * Register a webfont provider.
 *
 * @since 5.9.0
 *
 * @param string $classname The provider's class name.
 *                          The class should be a child of `WP_Webfonts_Provider`.
 *                          See {@see WP_Webfonts_Provider}.
 * @return bool True when registered. False when provider does not exist.
 */
function wp_register_webfont_provider( $classname ) {
	return wp_webfonts()->providers()->register( $classname );
}

/**
 * Get webfonts providers.
 *
 * @since 5.9.0
 *
 * @return WP_Webfonts_Provider[] Array of registered providers.
 */
function wp_get_webfont_providers() {
	return wp_webfonts()->providers()->get_all_registered();
}