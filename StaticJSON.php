<?php
/*
Plugin Name: Static JSON
Description: Static JSON outputs WordPress data as a JSON Object, used by Static Generators
Version: 0.1.1
Text Domain: static-json
*/

require (__DIR__ . '/vendor/autoload.php');
require_once 'lib/filters.php';
require_once 'lib/StaticJSONObject.php';
require_once 'lib/StaticJSONConfig.php';

class StaticJSON {

	protected $plugin_path;
	protected $plugin_url;
	protected $plugin_uri;
	protected $json_path;
	protected $json_uri;

	// added for testing purposes
	public $languages = [];

	/*--------------------------------------------*
	 * Constructor
	 *--------------------------------------------*/

	/**
	 * Initializes the plugin by setting localization, filters, and administration functions.
	 */
	public function __construct() {

		// Set the plugin path
		$this->plugin_path = dirname( __FILE__ );

		// Set the plugin path
		$this->plugin_uri = plugin_dir_url( __FILE__ );

		// Set the plugin url
		$this->plugin_url = WP_PLUGIN_URL . DIRECTORY_SEPARATOR . plugin_basename( __DIR__ ) . DIRECTORY_SEPARATOR;

    // Set the JSON path
    $this->json_path = wp_upload_dir()['path'] . DIRECTORY_SEPARATOR . 'static-json' . DIRECTORY_SEPARATOR;
    $this->json_uri = wp_upload_dir()['url'] . DIRECTORY_SEPARATOR . 'static-json' . DIRECTORY_SEPARATOR;

		load_plugin_textdomain( 'static-json', false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );

    // Include 3rd Party Plugins
    $this->include_plugins();

    // Enqueue Scripts
		add_action( 'admin_enqueue_scripts', [$this, 'register_admin_scripts']);
		add_action( 'wp_enqueue_scripts', [$this, 'register_scripts']);

		// Create Options Page
		add_action('acf/init', [$this, 'create_options_page']);

		// Save Post Action
		add_action('save_post', [$this, 'write_to_json']);

		// Add Languages
		if (function_exists('icl_object_id')) {
			$this->languages = icl_get_languages('skip_missing=N&orderby=KEY&order=DIR&link_empty_to=str');
		}
	}


	/*--------------------------------------------*
	 * Core Functions
	 *---------------------------------------------*/

	/**
	 * Upon activation, create and show the options_page with default options.
	 */
	public function activate() {

	}

	/**
	 * Upon deactivation, removes the options page.
	 */
	public function deactivate() {

	}

  /**
   * Include Plugins
   */
  public function include_plugins() {
    $pluginUri = $this->plugin_uri;
    $pluginPath = $this->plugin_path;

		// ----- UNCOMMENT THIS WHEN BUNDLING ACF -----
    // 1. customize ACF path
    add_filter('acf/settings/path', function($path) use ($pluginPath) {
      return $pluginPath . '/acf/';
    });

    // 2. customize ACF dir
    add_filter('acf/settings/dir', function($path) use ($pluginUri) {
      return $pluginUri . '/acf/';
    });

    include_once( $pluginPath . '/acf/acf.php' );
		// ----- UNCOMMENT THIS WHEN BUNDLING ACF -----

    include_once( $pluginPath . '/lib/acf-post-type-selector.php' );

    // Include OTF Regenrate thumbanils
    include_once( $pluginPath . '/lib/otf-regenerate-thumbnails.php' );
  }

	/**
	 * Registers and enqueues admin-specific minified JavaScript.
	 */
	public function register_scripts() {
		// Enqueue Plugin's Frontend Styles
		// wp_register_style( 'static-json-style-frontend', $this->plugin_url . 'assets/css/main.css' );
		// wp_enqueue_style( 'static-json-style-frontend' );

		// Enqueue Plugin's Frontend Script
		// wp_register_script( 'static-json-script-main', $this->plugin_url . 'assets/js/main.js', array( 'jquery' ), false, true );
		// wp_enqueue_script( 'static-json-script-main' );
	}

	/**
	 * Registers and enqueues admin-specific JavaScript.
	 */
	public function register_admin_scripts() {

	}

	public function create_options_page() {
		if(function_exists('acf_add_options_page')) {
			$option_page = acf_add_options_page([
				'page_title' 	=> __('Static JSON', 'static-json'),
				'menu_title' 	=> __('Static JSON', 'static-json'),
				'menu_slug' 	=> 'static-json',
			]);
		}
	}

  /**
   * Write to JSON
   */
  public function write_to_json() {
		if (wp_is_post_revision(1)) return;
		$globalOptions = $this->getOptions();

		// Quit if Static JSON is not enabled
		if(!$globalOptions['enabled']) return;

    if (sizeof($this->languages) > 0) {
			global $sitepress;

			foreach($this->languages as $language) {
				$sitepress->switch_lang($language['code']);

				add_filter('acf/settings/current_language', function() use ($language) {
					return $language['code'];
				});

				$options = $this->getOptions();

	      $json = new StaticJSONObject(1, [
	        'existing_data' => "",
	        'language' => $language['code'],
		   		'options' => $options,
					'globalOptions' => $globalOptions
	      ]);

				$json->outputJSON();
			}
		} else {
		   $existingData = "";

	     $json = new StaticJSONObject(1, [
				 'existing_data' => $existingData,
				 'options' => $globalOptions
	     ]);

			 $json->outputJSON();
	  }

			$config = new StaticJSONConfig([
				'options' => $globalOptions
			]);

			$config->outputJSON();

			// Generate Website
			if($options['generate_website']) {
				$this->generate_website();
			};
	  }

	/**
	 * Generate Website
	 */
	public function generate_website($port = 7070) {
		$loop = new React\EventLoop\StreamSelectLoop();
	  $dnode = new DNode\DNode($loop);

	  $dnode->connect($port, function ($remote, $connection) {
	    $remote->compile(function ($status) use($connection) { $connection->end(); });
	  });

	  $loop->run();
	}

	/**
	* Get the JSON Data Object from existing data files
	*/
	private function getJsonForLanguage($language=null) {
		$filename = $language ? $this->json_path . 'site_data-' . $language . '.json' : $this->json_path . 'site_data.json';
		$data = file_get_contents($filename);

		return $data ? json_decode($data) : [];
	}

	/**
	 * Parse Options
	 */
	public function getOptions() {
		$options = [
			'enabled' => get_field('static_json_enabled', 'options'),
			'generate_website' => get_field('static_json_generate_website', 'options'),
			'post_types' => get_field('static_json_post_types', 'options'),
			'pages' => get_field('static_json_pages', 'options'),
			'header' => get_field('static_json_header', 'options'),
			'footer' => get_field('static_json_footer', 'options'),
			'translations' => get_field('translations', 'options'),
			'base_dir' => get_field('base_dir', 'options'),
			'html_dir' => get_field('html_dir', 'options'),
			'config' => get_field('static_json_config', 'options'),
		];

		return $options;
	}

	public function return_en() {
		return 'en';
	}
}

new StaticJSON;
