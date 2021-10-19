<?php
/**
 * Webfonts API: Webfonts Controller
 *
 * @package WordPress
 * @subpackage Webfonts
 * @since 5.9.0
 */

/**
 * Webfonts Controller.
 *
 * Receives the incoming requests and handles the processing.
 */
class WP_Webfonts_Downloader {

	/**
	 * Get the CSS with local paths instead of remote where possible.
	 *
	 * @access public
	 *
	 * @since 5.9.0
	 *
	 * @param string $css The CSS to parse.
	 *
	 * @return string
	 */
	public function get_css( $css ) {
		// Get an array of locally-hosted files.
		$files = $this->get_local_files_from_css( $css );

		// Convert paths to URLs.
		foreach ( $files as $remote => $local ) {
			$files[ $remote ] = str_replace( trailingslashit( WP_CONTENT_DIR ), content_url(), $local );
		}

		return str_replace( array_keys( $files ), array_values( $files ), $css );
	}

	/**
	 * Get an array of font-files from the CSS.
	 *
	 * @access public
	 *
	 * @param string $css The CSS to parse.
	 *
	 * @return array Returns an array of files per-font-family.
	 *               The array keys are the font-family names, and the values are the font-files URLs.
	 */
	public function get_font_files( $css ) {
		$font_faces = explode( '@font-face', $css );

		$font_files = array();

		// Loop all our font-face declarations.
		foreach ( $font_faces as $font_face ) {

			// Make sure we only process styles inside this declaration.
			$style = explode( '}', $font_face )[0];

			// Sanity check.
			if ( false === strpos( $style, 'font-family' ) ) {
				continue;
			}

			// Get an array of our font-families.
			preg_match_all( '/font-family.*?\;/', $style, $matched_font_families );

			// Get an array of our font-files.
			preg_match_all( '/url\(.*?\)/i', $style, $matched_font_files );

			// Get the font-family name.
			$font_family = 'unknown';
			if ( isset( $matched_font_families[0] ) && isset( $matched_font_families[0][0] ) ) {
				$font_family = rtrim( ltrim( $matched_font_families[0][0], 'font-family:' ), ';' );
				$font_family = trim( str_replace( array( "'", ';' ), '', $font_family ) );
				$font_family = sanitize_key( strtolower( str_replace( ' ', '-', $font_family ) ) );
			}

			// Make sure the font-family is set in our array.
			if ( ! isset( $font_files[ $font_family ] ) ) {
				$font_files[ $font_family ] = array();
			}

			// Get files for this font-family and add them to the array.
			foreach ( $matched_font_files as $match ) {

				// Sanity check.
				if ( ! isset( $match[0] ) ) {
					continue;
				}

				// Add the file URL.
				$font_files[ $font_family ][] = rtrim( ltrim( $match[0], 'url(' ), ')' );
			}

			// Make sure we have unique items.
			// We're using array_flip here instead of array_unique for improved performance.
			$font_files[ $font_family ] = array_flip( array_flip( $font_files[ $font_family ] ) );
		}

		return $font_files;
	}

	/**
	 * Download files mentioned in our CSS locally.
	 *
	 * @access public
	 *
	 * @since 5.9.0
	 *
	 * @return array Returns an array of remote URLs and their local counterparts.
	 */
	public function get_local_files_from_css( $css ) {
		$font_files  = $this->get_font_files( $css );
		$stored     = get_site_option( 'downloaded_font_files', array() );
		$change     = false; // If in the end this is true, we need to update the cache option.
		$filesystem  = $this->get_filesystem();

		// If the fonts folder doesn't exist, create it.
		// Bailout if the folder doesn't exist and can't be created.
		if ( ! $this->create_dir( trailingslashit( WP_CONTENT_DIR ) . '/fonts' ) ) {
			return array();
		}

		foreach ( $font_files as $font_family => $files ) {

			// The folder path for this font-family.
			$folder_path = trailingslashit( WP_CONTENT_DIR ) . "/fonts/$font_family";

			// If the folder doesn't exist, create it.
			// Skip if the folder doesn't exist and can't be created.
			if ( ! $this->create_dir( $folder_path ) ) {
				continue;
			}

			foreach ( $files as $url ) {

				// Get the filename.
				$filename = basename( wp_parse_url( $url, PHP_URL_PATH ) );

				// Check if the file already exists.
				if ( file_exists( "$folder_path/$filename" ) ) {

					// Skip if already cached.
					if ( isset( $stored[ $url ] ) ) {
						continue;
					}

					// Add file to the cache and change the $changed var to indicate we need to update the option.
					$stored[ $url ] = "$folder_path/$filename";
					$change         = true;

					// Since the file exists we don't need to proceed with downloading it.
					continue;
				}

				// If we got this far, we need to download the file.
				// Perform the download on shutdown to avoid delaying this page load.
				add_action(
					'shutdown',
					function() use ( $url, $folder_path, $filename ) {
						$this->download_file( $url, "$folder_path/$filename" );
					}
				);
			}
		}

		// If there were changes, update the option.
		if ( $change ) {

			// Cleanup the option and then save it.
			foreach ( $stored as $url => $path ) {
				if ( ! file_exists( $path ) ) {
					unset( $stored[ $url ] );
				}
			}
			update_site_option( 'downloaded_font_files', $stored );
		}

		return $stored;
	}

	/**
	 * Download a file locally.
	 *
	 * @access private
	 *
	 * @param string $url  The URL of the file to download.
	 * @param string $path The path to save the file to.
	 *
	 * @return void
	 */
	private function download_file( $url, $path ) {
		// require file.php if the download_url function doesn't exist.
		if ( ! function_exists( 'download_url' ) ) {
			require_once wp_normalize_path( ABSPATH . '/wp-admin/includes/file.php' );
		}

		// Download file to temporary location.
		$tmp_path = download_url( $url );

		// Make sure there were no errors.
		if ( is_wp_error( $tmp_path ) ) {
			return;
		}

		// Move temp file to final destination.
		$this->get_filesystem()->move( $tmp_path, $path, true );
	}

	/**
	 * Creates a folder if it doesn't already exist.
	 *
	 * @access private
	 *
	 * @since 5.9.0
	 *
	 * @param string $path The path to the folder.
	 *
	 * @return bool True on success, false on failure.
	 */
	private function create_dir( $path ) {
		$filesystem = $this->get_filesystem();
		// If the folder doesn't exist, create it.
		if ( ! file_exists( $path ) ) {
			return $filesystem->mkdir( $path, FS_CHMOD_DIR );
		}
		return $filesystem->is_dir( $path );
	}

	/**
	 * Get the filesystem.
	 *
	 * @access private
	 *
	 * @since 5.9.0
	 *
	 * @return \WP_Filesystem_Base
	 */
	private function get_filesystem() {
		global $wp_filesystem;

		// If the filesystem has not been instantiated yet, do it here.
		if ( ! $wp_filesystem ) {
			if ( ! function_exists( 'WP_Filesystem' ) ) {
				require_once wp_normalize_path( ABSPATH . '/wp-admin/includes/file.php' );
			}
			WP_Filesystem();
		}

		if ( ! defined( 'FS_CHMOD_DIR' ) ) {
			define( 'FS_CHMOD_DIR', ( 0755 & ~ umask() ) );
		}

		return $wp_filesystem;
	}
}
