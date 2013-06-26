<?php
/**
 * The main plugin logic is in Styles, not this supporting plugin.
 *
 * In case users think this plugin is all they need, 
 * this class display guidance to install, update, or activate Styles.
 *
 * @see http://wordpress.org/plugins/styles
 */
class Styles_Child_Notices {

	/**
	 * Version of this Styles_Child_Notices class
	 */
	var $version = '1.0';

	/**
	 * Store array of plugin requirements, each containing keys:
	 *   plugin_file, required_slug, required_file
	 */
	var $requirements = array();

	/**
	 * Array associating plugin names with plugin files.
	 */
	var $plugins_files_by_name = array();

	/**
	 * Admin notices
	 */
	var $notices = array();

	/**
	 * Regex to match version number at end of string
	 */
	var $regex_version_number = '/\d+(:?\.\d+)+$/';

	/**
	 * Construct called before init. Don't do anything except hook.
	 */
	public function __construct() {
		if ( !is_admin() ) { return; }

		add_filter( 'extra_plugin_headers', array( $this, 'extra_plugin_headers') );
		add_action( 'admin_init', array( $this, 'get_plugins' ) );

		// Notices
		add_action( 'admin_init', array( $this, 'install_notice' ), 20 );
		add_action( 'admin_init', array( $this, 'activate_notice' ), 30 );
		add_action( 'admin_init', array( $this, 'upgrade_notice' ), 40 );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'customize_controls_enqueue_scripts',  array( $this, 'customize_notices' ) );

	}

	/**
	 * Return the plugin file/slug when given the plugin name
	 *
	 * @return string
	 */
	public function get_plugin_meta_by_name( $name, $meta_lookup='file' ) {

		if ( empty( $this->plugins_files_by_name ) ) {
			foreach ( get_plugins() as $plugin_file => $meta ) {
				$meta['file'] = $plugin_file;
				$this->plugins_files_by_name[ $meta['Name'] ] = $meta;
			}
		}

		if ( array_key_exists( $name, $this->plugins_files_by_name ) ) {
			return $this->plugins_files_by_name[ $name ][ $meta_lookup ];
		}else {
			return false;
		}

	}

	/**
	 * Additional headers
	 *
	 * @return array plugin header search terms
	 */
	public function extra_plugin_headers( $headers ) {
		$headers['Require'] = 'require';
		return $headers;
	}

	/**
	 * Cycle through plugins and identify those with "Require" declared in the header
	 */
	public function get_plugins() {
		
		foreach ( get_plugins() as $plugin_file => $meta ) {

			// Skip if there is no requirement or if the requesting plugin isn't active
			if ( empty( $meta['require'] ) || !is_plugin_active( $plugin_file ) ) {
				continue;
			}

			$required_name = $this->required_name( $meta );
			$required_file = $this->get_plugin_meta_by_name( $required_name, 'file' );
			$actual_version = $this->get_plugin_meta_by_name( $required_name, 'Version' );

			// Setup values
			$values = array(
				'child_name' => $meta['Name'],
				'child_slug' => basename( dirname( $plugin_file ) ),
				'child_file' => $plugin_file,
				'required_name' => $required_name,
				'required_slug' => sanitize_title( $required_name ),
				'required_file' => $required_file,
				'required_version' => $this->required_version( $meta ),
				'actual_version' => $actual_version,
			);

			$this->requirements[] = $values;
		}
	}

	public function required_name( $meta ) {
		$name = preg_replace( $this->regex_version_number, '', $meta['require'] );

		return trim( $name );
	}

	public function required_version( $meta ) {
		$found = preg_match( $this->regex_version_number, $meta['require'], $version );

		if ( !$found ){
			return false;
		}else {
			return $version[0];
		}
	}

	/**
	 * Display notice if required plugin needs to be installed.
	 */
	public function install_notice() {
		if ( 'update.php' == basename( $_SERVER['PHP_SELF'] )
			|| !current_user_can('install_plugins')
		) {
			return false;
		}

		foreach ( $this->requirements as $values ) {
			$child_name = $child_file = $required_name = $required_slug = $required_file = '';
			extract( $values, EXTR_IF_EXISTS );

			if ( !is_dir( WP_PLUGIN_DIR . '/' . $required_slug ) ) {
				// Plugin not installed
				$url = wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=' . $required_slug), 'install-plugin_' . $required_slug );
				$this->notices[] = "<p>To add theme options with <strong>$child_name</strong>, please <a href='$url'>install $required_name</a>.</p>";
			}
		}
	}

	/**
	 * Display notice if plugin for this theme is installed, but not activated.
	 */
	public function activate_notice() {
		foreach ( $this->requirements as $plugin ) {
			$child_name = $child_file = $required_name = $required_slug = $required_file = '';
			extract( $plugin, EXTR_IF_EXISTS );

			if ( is_dir( WP_PLUGIN_DIR . '/' . $required_slug ) ) {
				if ( is_plugin_inactive( $required_file ) ) {
					$url = wp_nonce_url(self_admin_url('plugins.php?action=activate&plugin=' . $required_file ), 'activate-plugin_' . $required_file );
					$this->notices[] = "<p><strong>$required_name</strong> is installed, but not active. Please <a href='$url'>activate $required_name</a>.</p>";
				}
			}

		}
	}

	/**
	 * Display notice if required plugin needs to be upgraded.
	 */
	public function upgrade_notice() {
		if ( 'update.php' == basename( $_SERVER['PHP_SELF'] )
			|| !current_user_can('install_plugins')
		) {
			return false;
		}

		foreach ( $this->requirements as $values ) {
			$child_name = $child_file = $required_name = $required_slug = $required_file = $required_version = $actual_version ='';
			extract( $values, EXTR_IF_EXISTS );

			if ( empty( $actual_version ) ) {
				continue;
			}

			if ( version_compare( $actual_version, $required_version, '<') ) {
				$url = wp_nonce_url(self_admin_url('update.php?action=upgrade-plugin&plugin=' . $required_file ), 'upgrade-plugin_' . $required_file );
				$this->notices[] = "<p><strong>$child_name</strong> requires $required_name version <strong>$required_version</strong> for all features to work correctly. You are running version <strong>$actual_version</strong>. Please <a href='$url'>upgrade $required_name</a>.</p>";
			}
		}

	}

	/**
	 * Output all notices in WP Admin
	 */
	public function admin_notices() {
		foreach( $this->notices as $key => $message ) {
			echo "<div class='updated fade' id='styles-$key'>$message</div>";
		}
	}

	/**
	 * Output all notices in WP Customizer
	 */
	public function customize_notices() {

		// Don't load these files if there aren't notices or Styles is active.
		if ( empty( $this->notices ) || class_exists( 'Styles_Plugin' ) ) {
			return;
		}

		// Stylesheets
		wp_enqueue_style(  'styles-child-customize-notices', plugins_url( '/styles-child-customize-notices.css', __FILE__ ), array(), $this->version );

		// Javascript
		wp_enqueue_script( 'styles-child-customize-notices', plugins_url( '/styles-child-customize-notices.js', __FILE__ ), array(), $this->version );

		// Send notices to Javascript
		wp_localize_script( 'styles-child-customize-notices', 'wp_styles_child_notices', $this->notices );

	}
}

/**
 * Instantiate the class once.
 * It reads headers from all plugins.
 *
 * @see Styles_Child_Notices::extra_plugin_headers
 */
if ( !isset( $Styles_Child_Notices ) ) {
	$Styles_Child_Notices = new Styles_Child_Notices();
}
