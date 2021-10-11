<?php
/**
 * Webfonts API provider for locally-hosted fonts.
 *
 * @package    WordPress
 * @subpackage WebFonts
 * @since      5.9.0
 */

/**
 * Webfonts API provider for locally-hosted fonts.
 */
final class WP_Fonts_Provider_Local extends WP_Fonts_Provider {

	/**
	 * Get validated params.
	 *
	 * @access public
	 * @since 5.9.0
	 * @param array $params The webfont's parameters.
	 * @return array
	 */
	public function get_validated_params( $params ) {
		$params = parent::get_validated_params( $params );
		if ( false !== strpos( $params['font-family'], ' ' ) && false === strpos( $params['font-family'], '"' ) && false === strpos( $params['font-family'], "'" ) ) {
			$params['font-family'] = '"' . $params['font-family'] . '"';
		}
		return $params;
	}

	/**
	 * Get the CSS for a collection of fonts.
	 *
	 * @access public
	 * @since 5.9.0
	 * @param array $fonts The fonts to get CSS for.
	 * @return string
	 */
	public function get_fonts_collection_css( $fonts ) {
		$css = '';
		foreach ( $fonts as $font ) {
			if ( empty( $font['font-family'] ) ) {
				continue;
			}

			// Validate font params.
			$font = $this->get_validated_params( $font );

			$css .= '@font-face{';
			foreach ( $font as $key => $value ) {

				// Compile the "src" parameter.
				if ( 'src' === $key ) {
					$src = "local({$font['font-family']})";
					foreach ( $value as $item ) {
						$src .= ( 'data' === $item['format'] )
							? ", url({$item['url']})"
							: ", url('{$item['url']}') format('{$item['format']}')";
					}
					$value = $src;
				}

				// If font-variation-settings is an array, convert it to a string.
				if ( 'font-variation-settings' === $key && is_array( $value ) ) {
					$variations = array();
					foreach ( $value as $key => $val ) {
						$variations[] = "$key $val";
					}
					$value = implode( ', ', $variations );
				}

				if ( ! empty( $value ) ) {
					$css .= "$key:$value;";
				}
			}
			$css .= '}';
		}

		return $css;
	}
}
