<?php
/*
Plugin Name: OM4 Custom CSS
Plugin URI: http://om4.com.au/wordpress-plugins/
Description: Add custom CSS rules using WordPress Dashboard.
Version: 1.0.1
Author: OM4
Author URI: http://om4.com.au/
Text Domain: om4-custom-css
Git URI: https://github.com/OM4/om4-custom-css
Git Branch: release
License: GPLv2
*/

/*

   Copyright 2012-2013 OM4 (email: info@om4.com.au    web: http://om4.com.au/)

   This program is free software; you can redistribute it and/or modify
   it under the terms of the GNU General Public License as published by
   the Free Software Foundation; either version 2 of the License, or
   (at your option) any later version.

   This program is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.

   You should have received a copy of the GNU General Public License
   along with this program; if not, write to the Free Software
   Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/


if ( ! class_exists( 'OM4_Plugin_Appearance' ) )
	require_once('includes/OM4_Plugin_Appearance.php');


/**
 * Custom CSS feature implementation:
 * - Adds Dashboard -> Appearance -> Custom CSS screen, which is accessible to any WordPress Administrator
 * - Outputs the Custom CSS rule stylesheet into any theme that has the 'wp_head' hook
 *
 * Should work with OM4 Theme, any WooTheme, and (hopefully) any other WordPress theme.
 */
class OM4_Custom_CSS extends OM4_Plugin_Appearance {

	public function __construct() {

		$this->screen_title = 'Custom CSS';
		$this->screen_name = 'customcss';

		$this->wp_editor_defaults['textarea_rows'] = 30;

		if ( is_admin() ) {
			$this->AddLoadDashboardPageHook( 'add_thickbox' );
			add_action( 'admin_post_update_custom_css', array($this, 'DashboardScreenSave') );
		} else {
			add_action('init', array($this, 'InitFrontend'), 100000 );
		}

		add_action('om4_new_site_initialised', array($this, 'NewSiteInitialised'));

		parent::__construct();
	}

	/**
	 * Tasks to perform when a new website is created/initialised.
	 * @return bool
	 */
	public function NewSiteInitialised() {
		// Generate the initial Custom CSS file
		return $this->SaveCustomCSSToFile();
	}

	public function InitFrontend() {

		// Attempt to ensure that our Custom CSS rules are the last thing output before </head>
		$hook = 'wp_head';
		if ( function_exists( 'om4_generated_css_rules' ) ) {
			// OM4 Theme
			// Maintain backwards-compatibility with OM4 theme
			$hook = 'om4_theme_end_head';
		} else if ( function_exists('woo_head') ) {
			// WooTheme
			$hook = 'woo_head';
		}
		add_action( $hook, array($this, 'OutputCustomCSSStylesheet'), 100000 );
	}

	public function GetCustomCSS() {
		return get_option( 'om4_freeform_css', '' );
	}

	private function SetCustomCSS( $css ) {
		return update_option( 'om4_freeform_css', $css );
	}

	/**
	 * Save the specified Custom CSS rules.
	 *
	 * Save them to the database (for easy retrieval), and save them to the filesystem (for easy display via the frontend)
	 *
	 * @param string $css
	 *
	 * @return bool True on success, false on failure
	 */
	private function SaveCustomCSS( $css ) {
		$this->SetCustomCSSLastSavedTimestamp();
		$this->SetCustomCSS( $css );
		return $this->SaveCustomCSSToFile();
	}

	private function GetCustomCSSLastSavedTimestamp() {
		return get_option( 'om4_freeform_css_last_saved_timestamp', 1 );
	}

	private function SetCustomCSSLastSavedTimestamp( $timestamp = null ) {
		if ( is_null( $timestamp) )
			$timestamp = time();
		return update_option( 'om4_freeform_css_last_saved_timestamp', $timestamp );
	}

	private function GetCustomCSSFileURL() {
		return $this->UploadUrl( $this->GetCustomCSSFileName() );
	}

	/**
	 * Obtain the file name to the file where the custom CSS rules are saved to.
	 * This is relative to the uploads directory.
	 * Examples:
	 * /custom-122323232.css
	 * /2012/02/custom-1329690974.css
	 *
	 * @return string
	 */
	private function GetCustomCSSFileName() {
		return get_option( 'om4_freeform_css_filename', '' );
	}

	private function SetCustomCSSFileName( $filename ) {
		return update_option( 'om4_freeform_css_filename', $filename );
	}

	public function DashboardScreen () {
		// TODO: convert this screen to use wp_editor()
		?>
	<div class='wrap'>
		<div id="om4-header">
			<h2><?php echo esc_attr($this->screen_title); ?></h2>
			<?php
			if ( !$this->CanAccessDashboardScreen() ) {
				echo '<div class="error"><p>You do not have permission to access this feature.</p></div></div></div>';
				return;
			}

			if ( isset($_GET['updated']) && $_GET['updated'] == 'true' ) {
				?>
				<div id="message" class="updated fade"><p>Custom CSS rules saved. You can <a href="<?php echo site_url(); ?>">view your site by clicking here</a>.</p></div>
				<div id="message" class="updated fade"><p>It is recommended that you <?php echo $this->ValidateCSSLink('validate your CSS rules'); ?> to help you find errors, typos and incorrect uses of CSS.</p></div>
				<?php
			} else if ( isset($_GET['updated']) && $_GET['updated'] == 'false' ) {
				?>
				<div id="message" class="error fade"><p>There was an error saving your Custom CSS rules. Please try again.</p></div>
				<?php
			}

			?>
			<form action="<?php echo $this->FormAction(); ?>" method="post">
				<div style="float: right;"><?php echo $this->ValidateCSSButton(); ?></div>
				<p>To use <strong>Custom CSS</strong> rules to change the appearance of your site, enter them in this text box. Custom CSS rules will override your theme's CSS using the inheritance rules of CSS.<br />
				Rules must have a selector followed by rules in curly braces, for example <code>.mystyle { color: blue; }</code><br />
				Make sure you close all curly brace pairs to avoid errors.  CSS is powerful but hard to understand.  If interested, look at this <a href="http://www.w3schools.com/css/css_intro.asp">introduction</a>, or this <a href="http://www.w3.org/MarkUp/Guide/Style">one</a>.  </p>
				<div style="clear: both;"></div>
				<?php

				wp_editor( $this->GetCustomCSS(), 'css', $this->wp_editor_defaults );

				?>
				<input type="hidden" name="action" value="update_custom_css" />
				<?php
				wp_nonce_field('update_custom_css');
				?>
				<p class="submit"><input type="submit" name="submit" id="submit" class="button-primary" value="Save CSS Rules"></p>
				</form>
		</div>
	</div>
	<?php
	}

	/**
	 * Handler that saves the dashboard screen's options/values, then redirects back to the Dashboard Screen
	 */
	public function DashboardScreenSave() {

		$url = $this->DashboardURL();

		if ( $this->CanAccessDashboardScreen() ) {
			check_admin_referer('update_custom_css');
			$url = $this->SaveCustomCSS( stripslashes($_POST['css']) ) ? $this->DashboardURLSaved() : $this->DashboardURLSavedError();
		}

		wp_redirect( $url );
		exit;
	}

	/**
	 * Create a button that when clicked opens a thickbox window that shows the CSS validation results
	 * @param string $buttonText Button anchor text
	 */
	private function ValidateCSSButton($buttonText = 'Validate CSS Rules') {
		return '<input type="button" name="W3C CSS Validation Results" value="' . $buttonText . '" class="thickbox button-secondary" onclick="return false;" alt="' . $this->ValidateCSSUrl() . '" style="margin-left: 3em;" />';
	}

	/**
	 * Obtain the URL to the CSS validation service
	 * @return string
	 */
	private function ValidateCSSUrl() {
		return esc_url( 'http://jigsaw.w3.org/css-validator/validator?warning=no&uri=' . urlencode( $this->GetCustomCSSFileURL() ) . '&TB_iframe=true&width=900&height=600' );
	}

	/**
	 * Create a link that when clicked opens a thickbox window that shows the CSS validation results
	 * @param string $anchor Link anchor text
	 * @return string
	 */
	private function ValidateCSSLink($anchor) {
		return '<a onlick="return false;" class="thickbox"href="' . $this->ValidateCSSUrl() . '" name="W3C CSS Validation Results">' . $anchor . '</a>';
	}

	public function OutputCustomCSSStylesheet() {
		if ( ( '' != $this->GetCustomCSSFileName() ) ) {
			echo "\n" . '<link rel="stylesheet" href="' . $this->GetCustomCSSFileURL() . '" type="text/css" media="screen" />' . "\n";
		}
	}


	public function SaveCustomCSSToFile() {

		$css = "/* CSS Generated " . date('r') . " by User ID " . get_current_user_id() . " */\n\n" . $this->GetCustomCSS();

//      $random = time() . '-' . uniqid();
		$random = time();
		$filename = "custom-$random.css";

		// Save the CSS rules to a unique file

		// Tell WordPress temporarily that .css files can be uploaded
		add_filter('upload_mimes', array( $this, 'MimeTypes') );
		$result = wp_upload_bits( $filename, null, $css );
		remove_filter('upload_mimes', array( $this, 'MimeTypes') );

		if ( !$result['error'] ) {
			// Save the filename (and yyyy/mm folder names if applicable) to the newly generated stylesheet
			$dir = wp_upload_dir();
			$filename = str_replace($dir['baseurl'], '', $result['url']);

			$old_filename = $this->GetCustomCSSFileName();

			// Create the new CSS file
			$this->SetCustomCSSFileName( $filename );

			// Delete the previous CSS stylesheet if it exists
			if ( strlen($old_filename) ) {
				$old_filename = $dir['basedir'] . $old_filename;
				if ( file_exists($old_filename) && is_file($old_filename) ) {
					// Delete the previous .css file now that the new one has been created.
					unlink( $old_filename );
				}

			}
		} else {
			// Error saving css file. This really shouldn't happen, but just in case.
			trigger_error( sprintf( __( 'Error creating Custom CSS stylesheet: %s', 'om4-custom-css' ), $filename ) );
			return false;
		}
		return true;
	}

	public function MimeTypes($mimes) {
		$mimes['css'] = 'text/css';
		return $mimes;
	}

	/**
	 *
	 * @param string $path Optional. Path relative to the upload url.
	 * @return string full URL to the uploaded file
	 */
	private function UploadUrl( $path = '') {
		$dir = wp_upload_dir();
		return $dir['baseurl'] . $path;
	}


}
global $om4_custom_css;
$om4_custom_css = new OM4_Custom_CSS();


/** BEGIN GLOBAL FUNCTIONS - these are used outside of this plugin file **/

function om4_save_custom_css_to_file() {
	global $om4_custom_css;
	return $om4_custom_css->SaveCustomCSSToFile();
}

function om4_get_custom_css() {
	global $om4_custom_css;
	return $om4_custom_css->GetCustomCSS();
}

/** END GLOBAL FUNCTIONS **/