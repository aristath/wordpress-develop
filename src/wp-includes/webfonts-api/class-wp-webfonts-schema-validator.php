<?php
/**
 * Webfonts API: Webfonts Schema Validator
 *
 * @package WordPress
 * @subpackage Webfonts
 * @since 5.9.0
 */

/**
 * Webfonts Schema Validator.
 *
 * Validates the webfont schema.
 */
class WP_Webfonts_Schema_Validator {

	/**
	 * Valid font styles.
	 *
	 * @since 5.9.0
	 *
	 * @var string[]
	 */
	const VALID_FONT_STYLES = array(
		'normal',
		'italic',
		'oblique',
		// Global values.
		'inherit',
		'initial',
		'revert',
		'unset',
	);

	/**
	 * Valid font-display values.
	 *
	 * @since 5.9.0
	 *
	 * @var string[]
	 */
	const VALID_FONT_DISPLAY = array(
		'auto',
		'block',
		'fallback',
		'swap',
	);

	/**
	 * Valid font-weight values.
	 *
	 * @since 5.9.0
	 *
	 * @var string[]
	 */
	const VALID_FONT_WEIGHTS = array(
		'normal',
		'bold',
		'bolder',
		'lighter',
		'inherit',
	);

	/**
	 * Webfont being validated.
	 *
	 * @var string[]
	 */
	private $webfont = array();

	/**
	 * Checks if the given webfont schema is validate.
	 *
	 * @since 5.9.0
	 *
	 * @param string[] $webfont Webfont definition.
	 * @return bool True when valid. False when invalid.
	 */
	public function is_schema_valid( array $webfont ) {
		$this->webfont = $webfont;

		$is_valid = (
			$this->is_provider_valid() &&
			$this->is_font_family_valid() &&
			$this->is_src_valid() &&
			$this->is_font_display_valid() &&
			$this->is_font_style_valid() &&
			$this->is_font_weight_valid() &&
			$this->is_ascend_override_valid()
		);

		$this->webfont = array();

		return $is_valid;
	}

	/**
	 * Checks if the provider is validate.
	 *
	 * @since 5.9.0
	 *
	 * @return bool True if valid. False if invalid.
	 */
	private function is_provider_valid() {
		// @todo check if provider is registered.

		if ( empty( $this->webfont['provider'] ) || ! is_string( $this->webfont['provider'] ) ) {
			trigger_error( __( 'Webfont provider must be a non-empty string.' ) );

			return false;
		}

		return true;
	}

	/**
	 * Checks if the font family is validate.
	 *
	 * @since 5.9.0
	 *
	 * @return bool True if valid. False if invalid.
	 */
	private function is_font_family_valid() {
		if ( empty( $this->webfont['fontFamily'] ) || ! is_string( $this->webfont['fontFamily'] ) ) {
			trigger_error( __( 'Webfont font family must be a non-empty string.' ) );

			return false;
		}

		return true;
	}

	/**
	 * Checks if the given font-display is valid.
	 *
	 * @since 5.9.0
	 *
	 * @return bool True if valid. False if invalid.
	 */
	private function is_font_display_valid() {
		if (
			! empty( $this->webfont['fontDisplay'] ) &&
			! in_array( $this->webfont['fontDisplay'], self::VALID_FONT_DISPLAY, true )
		) {
			trigger_error( __( 'Webfont font-display is not a valid value.' ) );

			return false;
		}

		return true;
	}

	/**
	 * Checks if the font style is validate.
	 *
	 * @since 5.9.0
	 *
	 * @return bool True if valid. False if invalid.
	 */
	private function is_font_style_valid() {
		if ( empty( $this->webfont['fontStyle'] ) || ! is_string( $this->webfont['fontStyle'] ) ) {
			trigger_error( __( 'Webfont font style must be a non-empty string.' ) );
			return false;
		}

		if ( ! $this->is_font_style_value_valid( $this->webfont['fontStyle'] ) ) {
			trigger_error(
				sprintf(
					/* translators: 1: Slant angle, 2: Given font style. */
					__( 'Webfont font style must be normal, italic, oblique, or oblique %1$s. Given: %2$s.' ),
					'<angle>',
					$this->webfont['fontStyle']
				)
			);

			return false;
		}

		return true;
	}

	/**
	 * Checks if the given font-style is valid.
	 *
	 * @since 5.9.0
	 *
	 * @param string $font_style Font style to validate.
	 * @return bool True when font-style is valid.
	 */
	private function is_font_style_value_valid( $font_style ) {
		if (
			in_array( $font_style, self::VALID_FONT_STYLES, true ) ||
			preg_match( '/^oblique\s+(\d+)%/', $font_style, $matches )
		) {
			return true;
		}
		return false;
	}

	/**
	 * Checks if the "src" value is valid.
	 *
	 * @since 5.9.0
	 *
	 * @return bool True if valid. False if invalid.
	 */
	private function is_src_valid() {
		if ( empty( $this->webfont['src'] ) ) {
			return true;
		}

		if ( ! is_string( $this->webfont['src'] ) && ! is_array( $this->webfont['src'] ) ) {
			trigger_error( __( 'Webfont src must be a non-empty string, or an array of strings.' ) );

			return false;
		}

		$this->webfont['src'] = (array) $this->webfont['src'];
		foreach ( $this->webfont['src'] as $src ) {
			if ( ! is_string( $src ) ) {
				trigger_error( __( 'Webfont src must be a non-empty string, or an array of strings.' ) );

				return false;
			}

			if ( ! $this->is_src_value_valid( $src ) ) {
				trigger_error( __( 'Webfont src must be a valid URL, or a data URI.' ) );

				return false;
			}
		}

		return true;
	}

	/**
	 * Checks if the given src value is valid.
	 *
	 * @since 5.9.0
	 *
	 * @param string $src Source to validate.
	 *
	 * @return bool True when valid. False when invalid.
	 */
	private function is_src_value_valid( $src ) {

		// Validate data URLs.
		if ( preg_match( '/^data:.+;base64/', $src ) ) {
			return true;
		}

		// Validate URLs.
		if ( filter_var( $src, FILTER_VALIDATE_URL ) ) {
			return true;
		}

		// Check if it's a URL starting with "//" (omitted protocol)
		if ( 0 === strpos( $src, '//' ) ) {
			return true;
		}

		// Check if it's a relative URL.
		if ( 0 === strpos( $src, 'file:./' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Checks if the font weight is validate.
	 *
	 * @since 5.9.0
	 *
	 * @return bool True if valid. False if invalid.
	 */
	private function is_font_weight_valid() {

		// Require a value.
		if ( empty( $this->webfont['fontWeight'] ) ) {
			trigger_error( __( 'Webfont font weight must be a non-empty string.' ) );

			return false;
		}

		// Check if it's one of the default values.
		if ( in_array( $this->webfont['fontWeight'], self::VALID_FONT_WEIGHTS, true ) ) {
			return true;
		}

		// Check if value is a single font-weight, formatted as a number.
		if ( preg_match( '/^(\d+)$/', $this->webfont['fontWeight'], $matches ) ) {
			return true;
		}

		// Check if value is a range of font-weights, formatted as a number range.
		if ( preg_match( '/^(\d+)\s+(\d+)$/', $this->webfont['fontWeight'], $matches ) ) {
			return true;
		}

		trigger_error( __( 'Webfont font weight must be a non-empty string.' ) );
		return false;
	}

	/**
	 * Check if ascend-override is valid.
	 *
	 * @since 5.9.0
	 *
	 * @return bool True if valid. False if invalid.
	 */
	private function is_ascend_override_valid() {

		// Value is optional.
		if ( empty( $this->webfont['ascendOverride'] ) ) {
			return true;
		}

		// Check if value is "normal".
		if ( 'normal' === $this->webfont['ascendOverride'] ) {
			return true;
		}

		// Check if value is a percentage.
		if ( preg_match( '/^(\d+)%$/', $this->webfont['ascendOverride'], $matches ) ) {
			return true;
		}

		trigger_error( __( 'Webfont ascend-override must be "normal" or a percentage.' ) );

		return false;
	}
}
