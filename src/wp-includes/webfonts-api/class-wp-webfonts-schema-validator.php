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
	 * Valid font-variant values.
	 *
	 * @since 5.9.0
	 *
	 * @var string[]
	 */
	const VALID_FONT_VARIANTS = array(
		'normal',
		'none',
		'common-ligatures',
		'no-common-ligatures',
		'discretionary-ligatures',
		'no-discretionary-ligatures',
		'historical-ligatures',
		'no-historical-ligatures',
		'contextual',
		'no-contextual',
		'small-caps',
		'all-small-caps',
		'petite-caps',
		'all-petite-caps',
		'unicase',
		'titling-caps',
		'lining-nums',
		'oldstyle-nums',
		'proportional-nums',
		'tabular-nums',
		'diagonal-fractions',
		'stacked-fractions',
		'ordinal',
		'slashed-zero',
		'jis78',
		'jis83',
		'jis90',
		'jis04',
		'simplified',
		'traditional',
		'full-width',
		'proportional-width',
		'ruby',
	);

	/**
	 * Valid font-stretch values.
	 *
	 * @since 5.9.0
	 *
	 * @var string[]
	 */
	const VALID_FONT_STRETCH = array(
		'ultra-condensed',
		'extra-condensed',
		'condensed',
		'semi-condensed',
		'normal',
		'semi-expanded',
		'expanded',
		'extra-expanded',
		'ultra-expanded',
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
			$this->is_ascent_override_valid() &&
			$this->is_descent_override_valid() &&
			$this->is_font_stretch_valid() &&
			$this->is_font_variant_valid() &&
			$this->is_line_gap_override_valid() &&
			$this->is_unicode_range_valid()
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
	 * Check if ascent-override is valid.
	 *
	 * @since 5.9.0
	 *
	 * @return bool True if valid. False if invalid.
	 */
	private function is_ascent_override_valid() {

		// Value is optional.
		if ( empty( $this->webfont['ascentOverride'] ) ) {
			return true;
		}

		// Check if value is "normal".
		if ( 'normal' === $this->webfont['ascentOverride'] ) {
			return true;
		}

		// Check if value is a percentage.
		if ( $this->is_percentage( $this->webfont['ascentOverride'] ) ) {
			return true;
		}

		trigger_error( __( 'Webfont ascent-override must be "normal" or a percentage.' ) );

		return false;
	}

	/**
	 * Check if descent-override is valid.
	 *
	 * @since 5.9.0
	 *
	 * @return bool True if valid. False if invalid.
	 */
	private function is_descent_override_valid() {

		// Value is optional.
		if ( empty( $this->webfont['descentOverride'] ) ) {
			return true;
		}

		// Check if value is "normal".
		if ( 'normal' === $this->webfont['descentOverride'] ) {
			return true;
		}

		// Check if value is a percentage.
		if ( $this->is_percentage( $this->webfont['descentOverride'] ) ) {
			return true;
		}

		trigger_error( __( 'Webfont descent-override must be "normal" or a percentage.' ) );

		return false;
	}

	/**
	 * Check if font-stretch is valid.
	 *
	 * @since 5.9.0
	 *
	 * @return bool True if valid. False if invalid.
	 */
	private function is_font_stretch_valid() {

		// Value is optional.
		if ( empty( $this->webfont['fontStretch'] ) ) {
			return true;
		}

		// Value can be 1 or 2 parts separated by a space.
		// Split the value and check each part.
		$parts = explode( ' ', $this->webfont['fontStretch'] );

		// Make sure there are 1 or 2 parts.
		if ( count( $parts ) > 2 ) {
			trigger_error( __( 'Webfont font-stretch must be a single value or two values separated by a space.' ) );

			return false;
		}

		foreach ( $parts as $part ) {
			// Check if part is one of the default values, or a percentage.
			if ( ! in_array( $part, self::VALID_FONT_STRETCH, true ) && ! $this->is_percentage( $part ) ) {
				trigger_error( __( 'Webfont font-stretch value is invalid.' ) );

				return false;
			}
		}

		return true;
	}

	/**
	 * Check if font-variant is valid.
	 *
	 * @since 5.9.0
	 *
	 * @return bool True if valid. False if invalid.
	 */
	private function is_font_variant_valid() {

		// Value is optional.
		if ( empty( $this->webfont['fontVariant'] ) ) {
			return true;
		}

		// Value can be multiple parts separated by a space.
		// Split the value and check each part.
		$parts = explode( ' ', $this->webfont['fontVariant'] );
		foreach ( $parts as $part ) {
			// Check if part is one of the default values, or a percentage.
			if ( ! in_array( $part, self::VALID_FONT_VARIANTS, true ) ) {
				trigger_error( __( 'Webfont font-variant value is invalid.' ) );

				return false;
			}
		}

		return true;
	}

	/**
	 * Check if line-gap-override is valid.
	 *
	 * @since 5.9.0
	 *
	 * @return bool True if valid. False if invalid.
	 */
	private function is_line_gap_override_valid() {

		// Value is optional.
		if ( empty( $this->webfont['lineGapOverride'] ) ) {
			return true;
		}

		// Check if value is "normal".
		if ( 'normal' === $this->webfont['lineGapOverride'] ) {
			return true;
		}

		// Check if value is a percentage.
		if ( $this->is_percentage( $this->webfont['lineGapOverride'] ) ) {
			return true;
		}

		trigger_error( __( 'Webfont line-gap-override must be "normal" or a percentage.' ) );

		return false;
	}

	/**
	 * Check if unicode-range is valid.
	 *
	 * @since 5.9.0
	 *
	 * @return bool True if valid. False if invalid.
	 */
	private function is_unicode_range_valid() {

		// Value is optional.
		if ( empty( $this->webfont['unicodeRange'] ) ) {
			return true;
		}

		// Value can consist of multiple ranges separated by a comma.
		// Split the value and check each range.
		$ranges = explode( ',', $this->webfont['unicodeRange'] );
		foreach ( $parts as $part ) {
			// Trim the part to remove any spaces.
			$part = trim( $part );
			if (
				! preg_match( '/^U\+([0-9A-F])+$/', $part, $matches ) && // Check if value is a single codepoint.
				! preg_match( '/^U\+([0-9A-F])+-([0-9A-F])+$/', $part, $matches ) // Check if range.
			) {
				trigger_error( __( 'Webfont unicode-range value is invalid.' ) );

				return false;
			}
		}

		return true;
	}

	/**
	 * Check if value is percentage.
	 *
	 * @since 5.9.0
	 *
	 * @param string $value Value to check.
	 *
	 * @return bool True if percentage. False if not.
	 */
	private function is_percentage( $value ) {
		// Check if value is formatted like "10%", ".2%", or "10.2%".
		return preg_match( '/^(\d+)%|^\.(\d+)%|^(\d+)\.(\d+)%$/', $value, $matches );
	}
}
