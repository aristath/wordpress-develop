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
final class WP_Fonts_Provider_Google extends WP_Fonts_Provider {

	/**
	 * The provider's root URL.
	 *
	 * @var string
	 * @since 5.9.0
	 * @access protected
	 */
	protected $root_url = 'https://fonts.googleapis.com/css2';

	/**
	 * An array of API parameters which will not be added to the @font-face.
	 *
	 * @var array
	 * @since 5.9.0
	 * @access protected
	 */
	protected $api_params = array(
		'subset',
		'text',
		'effect',
	);

	/**
	 * Build the API URL from the query args.
	 *
	 * @access protected
	 * @since 5.9.0
	 * @return string
	 */
	protected function build_api_url() {
		$query_args = array(
			'family'  => $this->params['font-family'],
			'display' => $this->params['font-display'],
		);

		if ( 'italic' === $this->params['font-style'] ) {
			$query_args['family'] .= ':ital,wght@1,' . $this->params['font-weight'];
		} else {
			$query_args['family'] .= ':wght@' . $this->params['font-weight'];
		}

		if ( ! empty( $this->params['subset'] ) ) {
			$query_args['subset'] = implode( ',', (array) $this->params['subset'] );
		}

		if ( ! empty( $this->params['text'] ) ) {
			$query_args['text'] = $this->params['text'];
		}

		if ( ! empty( $this->params['effect'] ) ) {
			$query_args['effect'] = implode( '|', (array) $this->params['effect'] );
		}

		return add_query_arg( $query_args, $this->root_url );
	}

	/**
	 * Get the CSS for the font.
	 *
	 * @access public
	 * @since 5.9.0
	 * @return string
	 */
	public function get_css() {
		$remote_url     = $this->build_api_url();
		$transient_name = 'google_fonts_' . md5( $remote_url );
		$css            = get_site_transient( $transient_name );

		if ( false !== $css ) {
			return $css;
		}

		// Get the remote URL contents.
		$response = wp_remote_get(
			$remote_url,
			array(
				// Use a modern user-agent, to get woff2 files.
				'user-agent' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:73.0) Gecko/20100101 Firefox/73.0',
			)
		);

		// Early return if the request failed.
		if ( is_wp_error( $response ) ) {
			return '';
		}

		// Early return if the request did not return CSS.
		if ( 'text/css' !== wp_remote_retrieve_header( $response, 'content-type' ) ) {
			return '';
		}

		// Get the response body.
		$css = wp_remote_retrieve_body( $response );

		// Cache the CSS for a month.
		set_site_transient( $transient_name, $css, MONTH_IN_SECONDS );

		// If there are additional props not included in the CSS provided by the API, add them to the final CSS.
		$additional_props = array_diff(
			array_keys( $this->params ),
			array( 'font-family', 'font-style', 'font-weight', 'font-display', 'src', 'unicode-range' )
		);
		foreach ( $additional_props as $prop ) {
			$css = str_replace(
				'@font-face {',
				'@font-face {' . $prop . ':' . $this->params[ $prop ] . ';',
			);
		}

		return $css;
	}
}
