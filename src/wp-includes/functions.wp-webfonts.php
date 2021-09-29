<?php
/**
 * Dependencies API: Webfonts functions
 *
 * @since 5.9.0
 *
 * @package WordPress
 * @subpackage Dependencies
 */

/**
 * Register a webfont's stylesheet and generate CSS rules for it.
 *
 * @see WP_Dependencies::add()
 * @link https://www.w3.org/TR/CSS2/media.html#media-types List of CSS media types.
 *
 * @since 5.9.0
 *
 * @param string           $handle Name of the webfont. Should be unique.
 * @param string|bool      $src    Full URL of the stylesheet, or path of the stylesheet relative to the WordPress root directory.
 * @param array            $params Optional. An array of parameters. Default empty array.
 * @param string|bool|null $ver    Optional. String specifying stylesheet version number, if it has one, which is added to the URL
 *                                 as a query string for cache busting purposes. If version is set to false, a version
 *                                 number is automatically added equal to current installed WordPress version.
 *                                 If set to null, no version is added.
 * @param string           $media  Optional. The media for which this stylesheet has been defined.
 *                                 Default 'screen'. Accepts media types like 'all', 'print' and 'screen', or media queries like
 *                                 '(orientation: portrait)' and '(max-width: 640px)'.
 * @return bool Whether the style has been registered. True on success, false on failure.
 */
function wp_register_webfont( $handle, $src, $params = array(), $ver = null, $media = 'screen' ) {

	// If $src is an array, then we're using $params in its place
	// so move all args into their new positions.
	if ( is_array( $src ) ) {
		$media  = $ver;
		$ver    = $params;
		$params = $src;
		$src    = '';
	}

	$provider = isset( $params['provider'] ) ? $params['provider'] : new WP_Webfonts_Provider_Local();
	$provider->set_params( $params );

	// Register the stylesheet.
	$result = wp_register_style( "webfont-$handle", $src, array(), $ver, $media );

	// Preload the webfont if needed.
	_wp_maybe_preload_webfont( $params );

	// Add inline styles for generated @font-face styles.
	wp_add_inline_style( "webfont-$handle", $provider->get_css() );

	return $result;
}

/**
 * Remove a registered webfont.
 *
 * @see WP_Dependencies::remove()
 *
 * @since 5.9.0
 *
 * @param string $handle Name of the webfont to be removed.
 */
function wp_deregister_webfont( $handle ) {
	wp_deregister_style( "webfont-$handle" );
}

/**
 * Enqueue a webfont's CSS stylesheet and generate CSS rules for it.
 *
 * Registers the style if source provided (does NOT overwrite) and enqueues.
 *
 * @see WP_Dependencies::add()
 * @see WP_Dependencies::enqueue()
 * @link https://www.w3.org/TR/CSS2/media.html#media-types List of CSS media types.
 *
 * @since 5.9.0
 *
 * @param string           $handle Name of the webfont. Should be unique.
 * @param string           $src    Full URL of the stylesheet, or path of the stylesheet relative to the WordPress root directory.
 *                                 Default empty.
 * @param array            $params Optional. An array of parameters. Default empty array.
 * @param string|bool|null $ver    Optional. String specifying stylesheet version number, if it has one, which is added to the URL
 *                                 as a query string for cache busting purposes. If version is set to false, a version
 *                                 number is automatically added equal to current installed WordPress version.
 *                                 If set to null, no version is added.
 * @param string           $media  Optional. The media for which this stylesheet has been defined.
 *                                 Default 'screen'. Accepts media types like 'all', 'print' and 'screen', or media queries like
 *                                 '(orientation: portrait)' and '(max-width: 640px)'.
 */
function wp_enqueue_webfont( $handle, $src = '', $params = array(), $ver = null, $media = 'screen' ) {
	if ( $src || ! empty( $params ) ) {
		wp_register_webfont( $handle, $src, $params, $ver, $media );
	}
	return wp_enqueue_style( "webfont-$handle" );
}

/**
 * Remove a previously enqueued webfont.
 *
 * @see WP_Dependencies::dequeue()
 *
 * @since 5.9.0
 *
 * @param string $handle Name of the webfont to be removed.
 */
function wp_dequeue_webfont( $handle ) {
	wp_dequeue_style( "webfont-$handle" );
}

/**
 * Check whether a webfont's CSS stylesheet has been added to the queue.
 *
 * @since 5.9.0
 *
 * @param string $handle Name of the webfont.
 * @param string $list   Optional. Status of the webfont to check. Default 'enqueued'.
 *                       Accepts 'enqueued', 'registered', 'queue', 'to_do', and 'done'.
 * @return bool Whether style is queued.
 */
function wp_webfont_is( $handle, $list = 'enqueued' ) {
	return wp_style_is( "webfont-$handle", $list );
}

/**
 * Add metadata to a CSS stylesheet.
 *
 * Works only if the stylesheet has already been added.
 *
 * Possible values for $key and $value:
 * 'conditional' string      Comments for IE 6, lte IE 7 etc.
 * 'rtl'         bool|string To declare an RTL stylesheet.
 * 'suffix'      string      Optional suffix, used in combination with RTL.
 * 'alt'         bool        For rel="alternate stylesheet".
 * 'title'       string      For preferred/alternate stylesheets.
 *
 * @see WP_Dependencies::add_data()
 *
 * @since 5.9.0
 *
 * @param string $handle Name of the stylesheet.
 * @param string $key    Name of data point for which we're storing a value.
 *                       Accepts 'conditional', 'rtl' and 'suffix', 'alt' and 'title'.
 * @param mixed  $value  String containing the CSS data to be added.
 * @return bool True on success, false on failure.
 */
function wp_webfont_add_data( $handle, $key, $value ) {
	return wp_style_add_data( "webfont-$handle", $key, $value );
}

/**
 * Pre-loads the webfont if needed.
 *
 * @since 5.9.0
 *
 * @param string $src    The webfont URL.
 * @param array  $params The webfont parameters.
 * @return void
 */
function _wp_maybe_preload_webfont( $params ) {

	// Early exit if not using explicit font-files, or if "preload" is not true.
	if ( empty( $params['preload'] ) || true !== $params['preload'] || empty( $params['src'] ) ) {
		return;
	}

	// Hook in "wp_head" to print the preload link.
	// Using a closure here is acceptable because the function includes a filter.
	add_action(
		'wp_head',
		function() use ( $params ) {

			// Early return if the webfont is a data link.
			if ( 0 === strpos( $params['src'][0]['format'], 'data' ) ) {
				return;
			}

			// Build the link markup.
			$link = sprintf(
				'<link rel="preload" href="%1$s" as="font" type="%2$s" crossorigin>',
				esc_url( $params['src'][0]['url'] ),
				wp_get_mime_types()[ pathinfo( $params['src'][0]['url'], PATHINFO_EXTENSION ) ]
			);
			/**
			 * Filters the preload link for a webfont.
			 * This filter is only applied if the webfont is preloaded.
			 *
			 * @since 5.9.0
			 *
			 * @param string $link   The preload link.
			 * @param array  $params The webfont parameters.
			 *
			 * @return string The preload link.
			 */
			echo apply_filters( 'wp_preload_webfont', $link, $params );
		}
	);
}
