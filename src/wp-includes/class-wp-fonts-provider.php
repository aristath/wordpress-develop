<?php
/**
 * Webfonts API provider abstract class.
 *
 * Individual webfonts providers should extend this class and implement.
 *
 * @package    WordPress
 * @subpackage WebFonts
 * @since      5.9.0
 */

/**
 * Abstract class for webfonts API providers.
 */
abstract class WP_Fonts_Provider {

	/**
	 * An array of URLs to preconnect to.
	 *
	 * @access protected
	 * @since 5.9.0
	 * @var array
	 */
	protected $preconnect_urls = array();

	/**
	 * The provider's root URL.
	 *
	 * @access protected
	 * @since 5.9.0
	 * @var string
	 */
	protected $root_url = '';

	/**
	 * Webfont parameters.
	 *
	 * @access protected
	 * @since 5.9.0
	 * @var array
	 */
	protected $params = array();

	/**
	 * An array of valid CSS properties for @font-face.
	 *
	 * @access protected
	 * @since 5.9.0
	 * @var array
	 */
	protected $valid_font_face_properties = array(
		'ascend-override',
		'descend-override',
		'font-display',
		'font-family',
		'font-stretch',
		'font-style',
		'font-weight',
		'font-variant',
		'font-feature-settings',
		'font-variation-settings',
		'line-gap-override',
		'size-adjust',
		'src',
		'unicode-range',
	);

	/**
	 * An array of API parameters which will not be added to the @font-face.
	 *
	 * @access protected
	 * @since 5.9.0
	 * @var array
	 */
	protected $api_params = array();

	/**
	 * Get the root URL for the provider.
	 *
	 * @access public
	 * @return string
	 */
	public function get_root_url() {
		return $this->root_url;
	}

	/**
	 * Get the array of URLs to preconnect to.
	 *
	 * @access public
	 * @return array
	 */
	public function get_preconnect_urls() {
		return $this->preconnect_urls;
	}

	/**
	 * Get validated params.
	 *
	 * @access public
	 * @since 5.9.0
	 * @param array $params The webfont's parameters.
	 * @return array
	 */
	public function get_validated_params( $params ) {
		// Default values.
		$defaults = array(
			'font-weight'  => '400',
			'font-style'   => 'normal',
			'font-display' => 'fallback',
			'src'          => array(),
		);

		// Merge defaults with passed params.
		$params = wp_parse_args( $params, $defaults );

		// Whitelisted params.
		$whitelist = array_merge( $this->valid_font_face_properties, $this->api_params );

		// Only allow whitelisted properties.
		foreach ( $params as $key => $value ) {
			if ( ! in_array( $key, $whitelist, true ) ) {
				unset( $params[ $key ] );
			}
		}

		// Order $src items to optimize for browser support.
		if ( ! empty( $params['src'] ) ) {
			$params['src'] = (array) $params['src'];
			$src           = array();
			$src_ordered   = array();

			foreach ( $params['src'] as $url ) {
				// Add data URIs first.
				if ( 0 === strpos( trim( $url ), 'data:' ) ) {
					$src_ordered[] = array(
						'url'    => $url,
						'format' => 'data',
					);
					continue;
				}
				$format         = pathinfo( $url, PATHINFO_EXTENSION );
				$src[ $format ] = $url;
			}

			// Add woff2.
			if ( ! empty( $src['woff2'] ) ) {
				$src_ordered[] = array(
					'url'    => $src['woff2'],
					'format' => 'woff2',
				);
			}

			// Add woff.
			if ( ! empty( $src['woff'] ) ) {
				$src_ordered[] = array(
					'url'    => $src['woff'],
					'format' => 'woff',
				);
			}

			// Add ttf.
			if ( ! empty( $src['ttf'] ) ) {
				$src_ordered[] = array(
					'url'    => $src['ttf'],
					'format' => 'truetype',
				);
			}

			// Add eot.
			if ( ! empty( $src['eot'] ) ) {
				$src_ordered[] = array(
					'url'    => $src['eot'],
					'format' => 'embedded-opentype',
				);
			}

			// Add otf.
			if ( ! empty( $src['otf'] ) ) {
				$src_ordered[] = array(
					'url'    => $src['otf'],
					'format' => 'opentype',
				);
			}
			$params['src'] = $src_ordered;
		}

		// Only allow valid font-display values.
		if (
			! empty( $params['font-display'] ) &&
			! in_array( $params['font-display'], array( 'auto', 'block', 'swap', 'fallback' ), true )
		) {
			$params['font-display'] = 'fallback';
		}

		// Only allow valid font-style values.
		if (
			! empty( $params['font-style'] ) &&
			! in_array( $params['font-style'], array( 'normal', 'italic', 'oblique' ), true ) &&
			! preg_match( '/^oblique\s+(\d+)%/', $params['font-style'], $matches )
		) {
			$params['font-style'] = 'normal';
		}

		// Only allow valid font-weight values.
		if (
			! empty( $params['font-weight'] ) &&
			! in_array( $params['font-weight'], array( 'normal', 'bold', 'bolder', 'lighter', 'inherit' ), true ) &&
			! preg_match( '/^(\d+)$/', $params['font-weight'], $matches ) &&
			! preg_match( '/^(\d+)\s+(\d+)$/', $params['font-weight'], $matches )
		) {
			$params['font-weight'] = 'normal';
		}

		return $params;
	}

	/**
	 * Get cached styles from a remote URL.
	 *
	 * @access public
	 * @since 5.9.0
	 *
	 * @param string $url              The URL to fetch.
	 * @param string $id               An ID used to cache the styles.
	 * @param array  $args             The arguments to pass to wp_remote_get().
	 * @param array  $additional_props Additional properties to add to the @font-face styles.
	 *
	 * @return string The styles.
	 */
	public function get_cached_remote_styles( $id, $url, $args = array(), $additional_props = array() ) {
		$css = get_site_transient( $id );

		// Get remote response and cache the CSS if it hasn't been cached already.
		if ( false === $css ) {
			$css = $this->get_remote_styles( $url, $args );

			// Early return if the request failed.
			// Cache an empty string for 60 seconds to avoid bottlenecks.
			if ( empty( $css ) ) {
				set_site_transient( $id, '', 60 );
				return '';
			}

			// Cache the CSS for a month.
			set_site_transient( $id, $css, MONTH_IN_SECONDS );
		}

		// If there are additional props not included in the CSS provided by the API, add them to the final CSS.
		foreach ( $additional_props as $prop ) {
			$css = str_replace(
				'@font-face {',
				'@font-face {' . $prop . ':' . $this->params[ $prop ] . ';',
				$css
			);
		}

		return $css;
	}

	/**
	 * Get styles from a remote URL.
	 *
	 * @access public
	 * @since 5.9.0
	 *
	 * @param string $url              The URL to fetch.
	 * @param string $id               An ID used to cache the styles.
	 * @param array  $args             The arguments to pass to wp_remote_get().
	 * @param array  $additional_props Additional properties to add to the @font-face styles.
	 *
	 * @return string The styles.
	 */
	public function get_remote_styles( $url, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				// Use a modern user-agent, to get woff2 files.
				'user-agent' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:73.0) Gecko/20100101 Firefox/73.0',
			)
		);

		// Get the remote URL contents.
		$response = wp_remote_get( $url, $args );

		// Early return if the request failed.
		// Cache an empty string for 60 seconds to avoid bottlenecks.
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return '';
		}

		// Get the response body.
		return wp_remote_retrieve_body( $response );
	}


	/**
	 * Set the object's params.
	 *
	 * @access public
	 * @since 5.9.0
	 * @param array $params The webfont's parameters.
	 * @return void
	 */
	public function set_params( $params ) {
		$this->params = $this->get_validated_params( $params );
	}

	/**
	 * Get the object's params.
	 *
	 * @access public
	 * @since 5.9.0
	 * @return array
	 */
	public function get_params() {
		return $this->params;
	}

	/**
	 * Get the CSS for a collection of fonts.
	 *
	 * @access public
	 * @since 5.9.0
	 * @param array $fonts The fonts to get CSS for.
	 * @return string
	 */
	abstract public function get_fonts_collection_css( $fonts );
}
