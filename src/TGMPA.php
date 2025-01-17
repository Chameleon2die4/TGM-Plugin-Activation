<?php /** @noinspection DuplicatedCode */

/** @noinspection PhpUndefinedFunctionInspection */

namespace TGMPA;

use Plugin_Installer_Skin;
use Plugin_Upgrader;
use Plugin_Upgrader_Skin;
use stdClass;
use WP_Error;
use WP_Upgrader;

class TGMPA
{
	/**
	 * TGMPA version number.
	 *
	 * @since 2.5.0
	 *
	 * @const string Version number.
	 */
	const TGMPA_VERSION = '2.6.1';

	/**
	 * Regular expression to test if a URL is a WP plugin repo URL.
	 *
	 * @const string Regex.
	 *
	 * @since 2.5.0
	 */
	const WP_REPO_REGEX = '|^http[s]?://wordpress\.org/(?:extend/)?plugins/|';

	/**
	 * Arbitrary regular expression to test if a string starts with a URL.
	 *
	 * @const string Regex.
	 *
	 * @since 2.5.0
	 */
	const IS_URL_REGEX = '|^http[s]?://|';

	/**
	 * Holds a copy of itself, so it can be referenced by the class name.
	 *
	 * @since 1.0.0
	 *
	 * @var TGMPA
	 */
	public static $instance;

	/**
	 * Holds arrays of plugin details.
	 *
	 * @since 1.0.0
	 * @since 2.5.0 the array has the plugin slug as an associative key.
	 *
	 * @var array
	 */
	public $plugins = array();

	/**
	 * Holds arrays of plugin names to use to sort the plugins array.
	 *
	 * @since 2.5.0
	 *
	 * @var array
	 */
	protected $sort_order = array();

	/**
	 * Whether any plugins have the 'force_activation' setting, set to true.
	 *
	 * @since 2.5.0
	 *
	 * @var bool
	 */
	protected $has_forced_activation = false;

	/**
	 * Whether any plugins have the 'force_deactivation' setting, set to true.
	 *
	 * @since 2.5.0
	 *
	 * @var bool
	 */
	protected $has_forced_deactivation = false;

	/**
	 * Name of the unique ID to hash notices.
	 *
	 * @since 2.4.0
	 *
	 * @var string
	 */
	public $id = 'tgmpa';

	/**
	 * Name of the query-string argument for the admin page.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $menu = 'tgmpa-install-plugins';

	/**
	 * Parent menu file slug.
	 *
	 * @since 2.5.0
	 *
	 * @var string
	 */
	public $parent_slug = 'themes.php';

	/**
	 * Capability needed to view the plugin installation menu item.
	 *
	 * @since 2.5.0
	 *
	 * @var string
	 */
	public $capability = 'edit_theme_options';

	/**
	 * Default absolute path to folder containing bundled plugin zip files.
	 *
	 * @since 2.0.0
	 *
	 * @var string Absolute path prefix to zip file location for bundled plugins. Default is empty string.
	 */
	public $default_path = '';

	/**
	 * Flag to show admin notices or not.
	 *
	 * @since 2.1.0
	 *
	 * @var boolean
	 */
	public $has_notices = true;

	/**
	 * Flag to determine if the user can dismiss the notice nag.
	 *
	 * @since 2.4.0
	 *
	 * @var boolean
	 */
	public $dismissable = true;

	/**
	 * Message to be output above nag notice if dismissable is false.
	 *
	 * @since 2.4.0
	 *
	 * @var string
	 */
	public $dismiss_msg = '';

	/**
	 * Flag to set automatic activation of plugins. Off by default.
	 *
	 * @since 2.2.0
	 *
	 * @var boolean
	 */
	public $is_automatic = false;

	/**
	 * Optional message to display before the plugins table.
	 *
	 * @since 2.2.0
	 *
	 * @var string Message filtered by wp_kses_post(). Default is empty string.
	 */
	public $message = '';

	/**
	 * Holds configurable array of strings.
	 *
	 * Default values are added in the constructor.
	 *
	 * @since 2.0.0
	 *
	 * @var array
	 */
	public $strings = array();

	/**
	 * Holds the version of WordPress.
	 *
	 * @since 2.4.0
	 *
	 * @var int
	 */
	public $wp_version;

	/**
	 * Holds the hook name for the admin page.
	 *
	 * @since 2.5.0
	 *
	 * @var string
	 */
	public $page_hook;

	/**
	 * Adds a reference of this object to $instance, populates default strings,
	 * does the tgmpa_init action hook, and hooks in the interactions to init.
	 *
	 * {@internal This method should be `protected`, but as too many TGMPA implementations
	 * haven't upgraded beyond v2.3.6 yet, this gives backward compatibility issues.
	 * Reverted to public for the time being.}}
	 *
	 * @since 1.0.0
	 *
	 * @see TGM_Plugin_Activation::init()
	 */
	public function __construct()
	{
		// Set the current WordPress version.
		$this->wp_version = $GLOBALS['wp_version'];

		// Announce that the class is ready, and pass the object (for advanced use).
		do_action_ref_array('tgmpa_init', array($this));

		/*
					 * Load our text domain and allow for overloading the fall-back file.
					 *
					 * {@internal IMPORTANT! If this code changes, review the regex in the custom TGMPA
					 * generator on the website.}}
					 */
		add_action('init', array($this, 'load_textdomain'), 5);
		add_filter('load_textdomain_mofile', array($this, 'overload_textdomain_mofile'), 10, 2);

		// When the rest of WP has loaded, kick-start the rest of the class.
		add_action('init', array($this, 'init'));
	}

	/**
	 * Magic method to (not) set protected properties from outside this class.
	 *
	 * {@internal hackedihack... There is a serious bug in v2.3.2 - 2.3.6  where the `menu` property
	 * is being assigned rather than tested in a conditional, effectively rendering it useless.
	 * This 'hack' prevents this from happening.}}
	 *
	 * @see https://github.com/TGMPA/TGM-Plugin-Activation/blob/2.3.6/tgm-plugin-activation/class-tgm-plugin-activation.php#L1593
	 *
	 * @since 2.5.2
	 *
	 * @param string $name Name of an inaccessible property.
	 * @param mixed $value Value to assign to the property.
	 * @return void  Silently fail to set the property when this is tried from outside this class context.
	 *               (Inside this class context, the __set() method if not used as there is direct access.)
	 */
	public function __set(string $name, $value)
	{
		// phpcs:ignore Squiz.PHP.NonExecutableCode.ReturnNotRequired -- See explanation above.
	}

	/**
	 * Magic method to get the value of a protected property outside this class context.
	 *
	 * @param string $name Name of an inaccessible property.
	 * @return mixed The property value.
	 * @since 2.5.2
	 *
	 */
	public function __get(string $name)
	{
		return $this->{$name};
	}

	/**
	 * Initialise the interactions between this class and WordPress.
	 *
	 * Hooks in three new methods for the class: admin_menu, notices and styles.
	 *
	 * @since 2.0.0
	 *
	 * @see TGM_Plugin_Activation::admin_menu()
	 * @see TGM_Plugin_Activation::notices()
	 * @see TGM_Plugin_Activation::styles()
	 */
	public function init()
	{
		/**
		 * By default, TGMPA only loads on the WP back-end and not in an Ajax call. Using this filter
		 * you can overrule that behaviour.
		 *
		 * @param bool $load Whether TGMPA should load.
		 *                   Defaults to the return of `is_admin() && ! defined( 'DOING_AJAX' )`.
		 * @since 2.5.0
		 *
		 */
		if (true !== apply_filters('tgmpa_load', (is_admin() && !defined('DOING_AJAX')))) {
			return;
		}

		// Load class strings.
		$this->strings = array(
				'page_title'                      => __('Install Required Plugins', 'tgmpa'),
				'menu_title'                      => __('Install Plugins', 'tgmpa'),
				/* translators: %s: plugin name. */
				'installing'                      => __('Installing Plugin: %s', 'tgmpa'),
				/* translators: %s: plugin name. */
				'updating'                        => __('Updating Plugin: %s', 'tgmpa'),
				'oops'                            => __('Something went wrong with the plugin API.', 'tgmpa'),
				/* translators: 1: plugin name(s). */
				'notice_can_install_required'     => _n_noop(
						'This theme requires the following plugin: %1$s.',
						'This theme requires the following plugins: %1$s.',
						'tgmpa'
				),
				/* translators: 1: plugin name(s). */
				'notice_can_install_recommended'  => _n_noop(
						'This theme recommends the following plugin: %1$s.',
						'This theme recommends the following plugins: %1$s.',
						'tgmpa'
				),
				/* translators: 1: plugin name(s). */
				'notice_ask_to_update'            => _n_noop(
						'The following plugin needs to be updated to its latest version to ensure maximum compatibility with this theme: %1$s.',
						'The following plugins need to be updated to their latest version to ensure maximum compatibility with this theme: %1$s.',
						'tgmpa'
				),
				/* translators: 1: plugin name(s). */
				'notice_ask_to_update_maybe'      => _n_noop(
						'There is an update available for: %1$s.',
						'There are updates available for the following plugins: %1$s.',
						'tgmpa'
				),
				/* translators: 1: plugin name(s). */
				'notice_can_activate_required'    => _n_noop(
						'The following required plugin is currently inactive: %1$s.',
						'The following required plugins are currently inactive: %1$s.',
						'tgmpa'
				),
				/* translators: 1: plugin name(s). */
				'notice_can_activate_recommended' => _n_noop(
						'The following recommended plugin is currently inactive: %1$s.',
						'The following recommended plugins are currently inactive: %1$s.',
						'tgmpa'
				),
				'install_link'                    => _n_noop(
						'Begin installing plugin',
						'Begin installing plugins',
						'tgmpa'
				),
				'update_link'                     => _n_noop(
						'Begin updating plugin',
						'Begin updating plugins',
						'tgmpa'
				),
				'activate_link'                   => _n_noop(
						'Begin activating plugin',
						'Begin activating plugins',
						'tgmpa'
				),
				'return'                          => __('Return to Required Plugins Installer', 'tgmpa'),
				'dashboard'                       => __('Return to the Dashboard', 'tgmpa'),
				'plugin_activated'                => __('Plugin activated successfully.', 'tgmpa'),
				'activated_successfully'          => __('The following plugin was activated successfully:', 'tgmpa'),
				/* translators: 1: plugin name. */
				'plugin_already_active'           => __('No action taken. Plugin %1$s was already active.', 'tgmpa'),
				/* translators: 1: plugin name. */
				'plugin_needs_higher_version'     => __('Plugin not activated. A higher version of %s is needed for this theme. Please update the plugin.',
						'tgmpa'),
				/* translators: 1: dashboard link. */
				'complete'                        => __('All plugins installed and activated successfully. %1$s', 'tgmpa'),
				'dismiss'                         => __('Dismiss this notice', 'tgmpa'),
				'notice_cannot_install_activate'  => __('There are one or more required or recommended plugins to install, update or activate.',
						'tgmpa'),
				'contact_admin'                   => __('Please contact the administrator of this site for help.', 'tgmpa'),
		);

		do_action('tgmpa_register');

		/* After this point, the plugins should be registered and the configuration set. */

		// Proceed only if we have plugins to handle.
		if (empty($this->plugins) || !is_array($this->plugins)) {
			return;
		}

		// Set up the menu and notices if we still have outstanding actions.
		if (true !== $this->is_tgmpa_complete()) {
			// Sort the plugins.
			array_multisort($this->sort_order, SORT_ASC, $this->plugins);

			add_action('admin_menu', array($this, 'admin_menu'));
			add_action('admin_head', array($this, 'dismiss'));

			// Prevent the normal links from showing underneath a single install/update page.
			add_filter('install_plugin_complete_actions', array($this, 'actions'));
			add_filter('update_plugin_complete_actions', array($this, 'actions'));

			if ($this->has_notices) {
				add_action('admin_notices', array($this, 'notices'));
				add_action('admin_init', array($this, 'admin_init'), 1);
				add_action('admin_enqueue_scripts', array($this, 'thickbox'));
			}
		}

		// If needed, filter plugin action links.
		add_action('load-plugins.php', array($this, 'add_plugin_action_link_filters'), 1);

		// Make sure things get reset on switch theme.
		add_action('switch_theme', array($this, 'flush_plugins_cache'));

		if ($this->has_notices) {
			add_action('switch_theme', array($this, 'update_dismiss'));
		}

		// Setup the force activation hook.
		if (true === $this->has_forced_activation) {
			add_action('admin_init', array($this, 'force_activation'));
		}

		// Setup the force deactivation hook.
		if (true === $this->has_forced_deactivation) {
			add_action('switch_theme', array($this, 'force_deactivation'));
		}

		// Add CSS for the TGMPA admin page.
		add_action('admin_head', array($this, 'admin_css'));
	}

	/**
	 * Load translations.
	 *
	 * @since 2.6.0
	 *
	 * (@internal Uses `load_theme_textdomain()` rather than `load_plugin_textdomain()` to
	 * get round the different ways of handling the path and deprecated notices being thrown
	 * and such. For plugins, the actual file name will be corrected by a filter.}}
	 *
	 * {@internal IMPORTANT! If this function changes, review the regex in the custom TGMPA
	 * generator on the website.}}
	 */
	public function load_textdomain()
	{
		if (is_textdomain_loaded('tgmpa')) {
			return;
		}

		if (false !== strpos(__FILE__, WP_PLUGIN_DIR) || false !== strpos(__FILE__, WPMU_PLUGIN_DIR)) {
			// Plugin, we'll need to adjust the file name.
			add_action('load_textdomain_mofile', array($this, 'correct_plugin_mofile'), 10, 2);
			load_theme_textdomain('tgmpa', dirname(__FILE__) . '/languages');
			remove_action('load_textdomain_mofile', array($this, 'correct_plugin_mofile'));
		} else {
			load_theme_textdomain('tgmpa', dirname(__FILE__) . '/languages');
		}
	}

	/**
	 * Correct the .mo file name for (must-use) plugins.
	 *
	 * Theme use `/path/{locale}.mo` while plugins use `/path/{text-domain}-{locale}.mo`.
	 *
	 * {@internal IMPORTANT! If this function changes, review the regex in the custom TGMPA
	 * generator on the website.}}
	 *
	 * @param string $mofile Full path to the target mofile.
	 * @param string $domain The domain for which a language file is being loaded.
	 * @return string $mofile
	 * @since 2.6.0
	 *
	 */
	public function correct_plugin_mofile(string $mofile, string $domain)
	{
		// Exit early if not our domain (just in case).
		if ('tgmpa' !== $domain) {
			return $mofile;
		}
		return preg_replace('`/([a-z]{2}_[A-Z]{2}.mo)$`', '/tgmpa-$1', $mofile);
	}

	/**
	 * Potentially overload the fall-back translation file for the current language.
	 *
	 * WP, by default since WP 3.7, will load a local translation first and if none
	 * can be found, will try and find a translation in the /wp-content/languages/ directory.
	 * As this library is theme/plugin agnostic, translation files for TGMPA can exist both
	 * in the WP_LANG_DIR /plugins/ subdirectory and in the /themes/ subdirectory.
	 *
	 * This method makes sure both directories are checked.
	 *
	 * {@internal IMPORTANT! If this function changes, review the regex in the custom TGMPA
	 * generator on the website.}}
	 *
	 * @param string $mofile Full path to the target mofile.
	 * @param string $domain The domain for which a language file is being loaded.
	 * @return string $mofile
	 * @since 2.6.0
	 *
	 */
	public function overload_textdomain_mofile(string $mofile, string $domain)
	{
		// Exit early if not our domain, not a WP_LANG_DIR load or if the file exists and is readable.
		if ('tgmpa' !== $domain || false === strpos($mofile, WP_LANG_DIR) || @is_readable($mofile)) {
			return $mofile;
		}

		// Current fallback file is not valid, let's try the alternative option.
		if (false !== strpos($mofile, '/themes/')) {
			return str_replace('/themes/', '/plugins/', $mofile);
		} elseif (false !== strpos($mofile, '/plugins/')) {
			return str_replace('/plugins/', '/themes/', $mofile);
		} else {
			return $mofile;
		}
	}

	/**
	 * Hook in plugin action link filters for the WP native plugins page.
	 *
	 * - Prevent activation of plugins which don't meet the minimum version requirements.
	 * - Prevent deactivation of force-activated plugins.
	 * - Add update notice if update available.
	 *
	 * @since 2.5.0
	 */
	public function add_plugin_action_link_filters()
	{
		foreach ($this->plugins as $slug => $plugin) {
			if (false === $this->can_plugin_activate($slug)) {
				add_filter('plugin_action_links_' . $plugin['file_path'], array($this, 'filter_plugin_action_links_activate'),
						20);
			}

			if (true === $plugin['force_activation']) {
				add_filter('plugin_action_links_' . $plugin['file_path'], array($this, 'filter_plugin_action_links_deactivate'),
						20);
			}

			if (false !== $this->does_plugin_require_update($slug)) {
				add_filter('plugin_action_links_' . $plugin['file_path'], array($this, 'filter_plugin_action_links_update'),
						20);
			}
		}
	}

	/**
	 * Remove the 'Activate' link on the WP native plugins page if the plugin does not meet the
	 * minimum version requirements.
	 *
	 * @param array $actions Action links.
	 * @return array
	 * @since 2.5.0
	 *
	 */
	public function filter_plugin_action_links_activate(array $actions)
	{
		unset($actions['activate']);

		return $actions;
	}

	/**
	 * Remove the 'Deactivate' link on the WP native plugins page if the plugin has been set to force activate.
	 *
	 * @param array $actions Action links.
	 * @return array
	 * @since 2.5.0
	 *
	 */
	public function filter_plugin_action_links_deactivate(array $actions)
	{
		unset($actions['deactivate']);

		return $actions;
	}

	/**
	 * Add a 'Requires update' link on the WP native plugins page if the plugin does not meet the
	 * minimum version requirements.
	 *
	 * @param array $actions Action links.
	 * @return array
	 * @since 2.5.0
	 *
	 */
	public function filter_plugin_action_links_update(array $actions)
	{
		$actions['update'] = sprintf(
		/** @lang text */ '<a href="%1$s" title="%2$s" class="edit">%3$s</a>',
				esc_url($this->get_tgmpa_status_url('update')),
				esc_attr__('This plugin needs to be updated to be compatible with your theme.', 'tgmpa'),
				esc_html__('Update Required', 'tgmpa')
		);

		return $actions;
	}

	/**
	 * Handles calls to show plugin information via links in the notices.
	 *
	 * We get the links in the admin notices to point to the TGMPA page, rather
	 * than the typical plugin-install.php file, so we can prepare everything
	 * beforehand.
	 *
	 * WP does not make it easy to show the plugin information in the thickbox -
	 * here we have to require a file that includes a function that does the
	 * main work of displaying it, enqueue some styles, set up some globals and
	 * finally call that function before exiting.
	 *
	 * Down right easy once you know how...
	 *
	 * Returns early if not the TGMPA page.
	 *
	 * @return null Returns early if not the TGMPA page.
	 * @global string $tab Used as iframe div class names, helps with styling
	 * @global string $body_id Used as the iframe body ID, helps with styling
	 *
	 * @since 2.1.0
	 *
	 */
	public function admin_init()
	{
		if (!$this->is_tgmpa_page()) {
			return;
		}

		if (isset($_REQUEST['tab']) && 'plugin-information' === $_REQUEST['tab']) {
			// Needed for install_plugin_information().
			require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

			wp_enqueue_style('plugin-install');

			global $tab, $body_id;
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WP requirement.
			$body_id = 'plugin-information';

			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Overriding the WP global is the point.
			$tab = 'plugin-information';

			install_plugin_information();

			exit;
		}

		return null;
	}

	/**
	 * Enqueue thickbox scripts/styles for plugin info.
	 *
	 * Thickbox is not automatically included on all admin pages, so we must
	 * manually enqueue it for those pages.
	 *
	 * Thickbox is only loaded if the user has not dismissed the admin
	 * notice or if there are any plugins left to install and activate.
	 *
	 * @since 2.1.0
	 */
	public function thickbox()
	{
		if (!get_user_meta(get_current_user_id(), 'tgmpa_dismissed_notice_' . $this->id, true)) {
			add_thickbox();
		}
	}

	/**
	 * Adds submenu page if there are plugin actions to take.
	 *
	 * This method adds the submenu page letting users know that a required
	 * plugin needs to be installed.
	 *
	 * This page disappears once the plugin has been installed and activated.
	 *
	 * @return null Return early if user lacks capability to install a plugin.
	 * @see TGM_Plugin_Activation::init()
	 * @see TGM_Plugin_Activation::install_plugins_page()
	 *
	 * @since 1.0.0
	 *
	 */
	public function admin_menu()
	{
		// Make sure privileges are correct to see the page.
		if (!current_user_can('install_plugins')) {
			return;
		}

		$args = apply_filters(
				'tgmpa_admin_menu_args',
				array(
						'parent_slug' => $this->parent_slug,                     // Parent Menu slug.
						'page_title'  => $this->strings['page_title'],           // Page title.
						'menu_title'  => $this->strings['menu_title'],           // Menu title.
						'capability'  => $this->capability,                      // Capability.
						'menu_slug'   => $this->menu,                            // Menu slug.
						'function'    => array($this, 'install_plugins_page'), // Callback.
				)
		);

		$this->add_admin_menu($args);

		return null;
	}

	/**
	 * Add the menu item.
	 *
	 * {@internal IMPORTANT! If this function changes, review the regex in the custom TGMPA
	 * generator on the website.}}
	 *
	 * @param array $args Menu item configuration.
	 * @since 2.5.0
	 *
	 */
	protected function add_admin_menu(array $args)
	{
		if (has_filter('tgmpa_admin_menu_use_add_theme_page')) {
			_deprecated_function('The "tgmpa_admin_menu_use_add_theme_page" filter', '2.5.0',
					esc_html__('Set the parent_slug config variable instead.', 'tgmpa'));
		}

		if ('themes.php' === $this->parent_slug) {
			$this->page_hook = call_user_func('add_theme_page', $args['page_title'], $args['menu_title'], $args['capability'],
					$args['menu_slug'], $args['function']);
		} else {
			$this->page_hook = call_user_func('add_submenu_page', $args['parent_slug'], $args['page_title'],
					$args['menu_title'], $args['capability'], $args['menu_slug'], $args['function']);
		}
	}

	/**
	 * Echoes plugin installation form.
	 *
	 * This method is the callback for the admin_menu method function.
	 * This displays the admin page and form area where the user can select to install and activate the plugin.
	 * Aborts early if we're processing a plugin installation action.
	 *
	 * @return null Aborts early if we're processing a plugin installation action.
	 * @since 1.0.0
	 *
	 */
	public function install_plugins_page()
	{
		// Store new instance of plugin table in object.
		$plugin_table = new ListTable();

		// Return early if processing a plugin installation action.
		if ((('tgmpa-bulk-install' === $plugin_table->current_action() || 'tgmpa-bulk-update' === $plugin_table->current_action()) && $plugin_table->process_bulk_actions()) || $this->do_plugin_install()) {
			return;
		}

		// Force refresh of available plugin information, so we'll know about manual updates/deletes.
		wp_clean_plugins_cache(false);

		?>
		<div class="tgmpa wrap">
			<h1><?php echo esc_html(get_admin_page_title()); ?></h1>
			<?php $plugin_table->prepare_items(); ?>

			<?php
			if (!empty($this->message) && is_string($this->message)) {
				echo wp_kses_post($this->message);
			}
			?>
			<?php $plugin_table->views(); ?>

			<form id="tgmpa-plugins" action="" method="post">
				<input type="hidden" name="tgmpa-page" value="<?php echo esc_attr($this->menu); ?>" />
				<input type="hidden" name="plugin_status" value="<?php echo esc_attr($plugin_table->view_context); ?>" />
				<?php $plugin_table->display(); ?>
			</form>
		</div>
		<?php

		return null;
	}

	/**
	 * Installs, updates or activates a plugin depending on the action link clicked by the user.
	 *
	 * Checks the $_GET variable to see which actions have been
	 * passed and responds with the appropriate method.
	 *
	 * Uses WP_Filesystem to process and handle the plugin installation
	 * method.
	 *
	 * @return boolean True on success, false on failure.
	 * @uses WP_Filesystem
	 * @uses WP_Error
	 * @uses WP_Upgrader
	 * @uses Plugin_Upgrader
	 * @uses Plugin_Installer_Skin
	 * @uses Plugin_Upgrader_Skin
	 *
	 * @since 1.0.0
	 *
	 */
	protected function do_plugin_install()
	{
		if (empty($_GET['plugin'])) {
			return false;
		}

		// All plugin information will be stored in an array for processing.
		$slug = $this->sanitize_key(urldecode($_GET['plugin']));

		if (!isset($this->plugins[$slug])) {
			return false;
		}

		// Was an install or upgrade action link clicked?
		if ((isset($_GET['tgmpa-install']) && 'install-plugin' === $_GET['tgmpa-install']) || (isset($_GET['tgmpa-update']) && 'update-plugin' === $_GET['tgmpa-update'])) {

			$install_type = 'install';
			if (isset($_GET['tgmpa-update']) && 'update-plugin' === $_GET['tgmpa-update']) {
				$install_type = 'update';
			}

			check_admin_referer('tgmpa-' . $install_type, 'tgmpa-nonce');

			// Pass necessary information via URL if WP_Filesystem is needed.
			$url = wp_nonce_url(
					add_query_arg(
							array(
									'plugin'                 => urlencode($slug),
									'tgmpa-' . $install_type => $install_type . '-plugin',
							),
							$this->get_tgmpa_url()
					),
					'tgmpa-' . $install_type,
					'tgmpa-nonce'
			);

			$method = ''; // Leave blank so WP_Filesystem can populate it as necessary.

			$creds = request_filesystem_credentials(esc_url_raw($url), $method, false, false, array());
			if (false === $creds) {
				return true;
			}

			if (!WP_Filesystem($creds)) {
				request_filesystem_credentials(esc_url_raw($url), $method, true, false, array()); // Setup WP_Filesystem.
				return true;
			}

			/* If we arrive here, we have the filesystem. */

			// Prep variables for Plugin_Installer_Skin class.
			$extra = array();
			$extra['slug'] = $slug; // Needed for potentially renaming of directory name.
			$source = $this->get_download_url($slug);
			$api = ('repo' === $this->plugins[$slug]['source_type']) ? $this->get_plugins_api($slug) : null;
			$api = (false !== $api) ? $api : null;

			$url = add_query_arg(
					array(
							'action' => $install_type . '-plugin',
							'plugin' => urlencode($slug),
					),
					'update.php'
			);

			if (!class_exists('Plugin_Upgrader', false)) {
				require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			}

			$title = ('update' === $install_type) ? $this->strings['updating'] : $this->strings['installing'];
			$skin_args = array(
					'type'   => ('bundled' !== $this->plugins[$slug]['source_type']) ? 'web' : 'upload',
					'title'  => sprintf($title, $this->plugins[$slug]['name']),
					'url'    => esc_url_raw($url),
					'nonce'  => $install_type . '-plugin_' . $slug,
					'plugin' => '',
					'api'    => $api,
					'extra'  => $extra,
			);
			unset($title);

			if ('update' === $install_type) {
				$skin_args['plugin'] = $this->plugins[$slug]['file_path'];

				$skin = new Plugin_Upgrader_Skin($skin_args);
			} else {
				$skin = new Plugin_Installer_Skin($skin_args);
			}

			// Create a new instance of Plugin_Upgrader.
			$upgrader = new Plugin_Upgrader($skin);

			// Perform the action and install the plugin from the $source urldecode().
			add_filter('upgrader_source_selection', array($this, 'maybe_adjust_source_dir'), 1, 3);

			if ('update' === $install_type) {
				// Inject our info into the update transient.
				$to_inject = array(
						$slug => $this->plugins[$slug],
				);
				$to_inject[$slug]['source'] = $source;
				$this->inject_update_info($to_inject);

				$upgrader->upgrade($this->plugins[$slug]['file_path']);
			} else {
				$upgrader->install($source);
			}

			remove_filter('upgrader_source_selection', array($this, 'maybe_adjust_source_dir'), 1);

			// Make sure we have the correct file path now the plugin is installed/updated.
			$this->populate_file_path($slug);

			// Only activate plugins if the config option is set to true and the plugin isn't
			// already active (upgrade).
			if ($this->is_automatic && !$this->is_plugin_active($slug)) {
				$plugin_activate = $upgrader->plugin_info(); // Grab the plugin info from the Plugin_Upgrader method.
				if (false === $this->activate_single_plugin($plugin_activate, $slug, true)) {
					return true; // Finish execution of the function early as we encountered an error.
				}
			}

			$this->show_tgmpa_version();

			// Display message based on if all plugins are now active or not.
			if ($this->is_tgmpa_complete()) {
				echo '<p>', sprintf(esc_html($this->strings['complete']),
						'<a href="' . esc_url(self_admin_url()) . '">' . esc_html($this->strings['dashboard']) . '</a>'), '</p>';
				echo '<style>#adminmenu .wp-submenu li.current { display: none !important; }</style>';
			} else {
				echo '<p><a href="', esc_url($this->get_tgmpa_url()), '" target="_parent">', esc_html($this->strings['return']), '</a></p>';
			}

			return true;
		} elseif (isset($this->plugins[$slug]['file_path'], $_GET['tgmpa-activate']) && 'activate-plugin' === $_GET['tgmpa-activate']) {
			// Activate action link was clicked.
			check_admin_referer('tgmpa-activate', 'tgmpa-nonce');

			if (false === $this->activate_single_plugin($this->plugins[$slug]['file_path'], $slug)) {
				return true; // Finish execution of the function early as we encountered an error.
			}
		}

		return false;
	}

	/**
	 * Inject information into the 'update_plugins' site transient as WP checks that before running an update.
	 *
	 * @param array $plugins The plugin information for the plugins which are to be updated.
	 * @since 2.5.0
	 *
	 */
	public function inject_update_info(array $plugins)
	{
		$repo_updates = get_site_transient('update_plugins');

		if (!is_object($repo_updates)) {
			$repo_updates = new stdClass();
		}

		foreach ($plugins as $slug => $plugin) {
			$file_path = $plugin['file_path'];

			if (empty($repo_updates->response[$file_path])) {
				$repo_updates->response[$file_path] = new stdClass();
			}

			// We only really need to set package, but let's do all we can in case WP changes something.
			$repo_updates->response[$file_path]->slug = $slug;
			$repo_updates->response[$file_path]->plugin = $file_path;
			$repo_updates->response[$file_path]->new_version = $plugin['version'];
			$repo_updates->response[$file_path]->package = $plugin['source'];
			if (empty($repo_updates->response[$file_path]->url) && !empty($plugin['external_url'])) {
				$repo_updates->response[$file_path]->url = $plugin['external_url'];
			}
		}

		set_site_transient('update_plugins', $repo_updates);
	}

	/**
	 * Adjust the plugin directory name if necessary.
	 *
	 * The final destination directory of a plugin is based on the subdirectory name found in the
	 * (un)zipped source. In some cases - most notably GitHub repository plugin downloads -, this
	 * subdirectory name is not the same as the expected slug and the plugin will not be recognized
	 * as installed. This is fixed by adjusting the temporary unzipped source subdirectory name to
	 * the expected plugin slug.
	 *
	 * @param string $source Path to upgrade/zip-file-name.tmp/subdirectory/.
	 * @param string $remote_source Path to upgrade/zip-file-name.tmp.
	 * @param WP_Upgrader $upgrader Instance of the upgrader which installs the plugin.
	 * @return string|WP_Error
	 * @since 2.5.0
	 *
	 * @noinspection PhpPossiblePolymorphicInvocationInspection
	 */
	public function maybe_adjust_source_dir(string $source, string $remote_source, WP_Upgrader $upgrader)
	{
		if (!$this->is_tgmpa_page() || !is_object($GLOBALS['wp_filesystem'])) {
			return $source;
		}

		// Check for single file plugins.
		$source_files = array_keys($GLOBALS['wp_filesystem']->dirlist($remote_source));
		if (1 === count($source_files) && false === $GLOBALS['wp_filesystem']->is_dir($source)) {
			return $source;
		}

		// Multi-file plugin, let's see if the directory is correctly named.
		$desired_slug = '';

		// Figure out what the slug is supposed to be.
		if (false === $upgrader->bulk && !empty($upgrader->skin->options['extra']['slug'])) {
			$desired_slug = $upgrader->skin->options['extra']['slug'];
		} else {
			// Bulk installer contains less info, so fall back on the info registered here.
			foreach ($this->plugins as $slug => $plugin) {
				if (!empty($upgrader->skin->plugin_names[$upgrader->skin->i]) && $plugin['name'] === $upgrader->skin->plugin_names[$upgrader->skin->i]) {
					$desired_slug = $slug;
					break;
				}
			}
			unset($slug, $plugin);
		}

		if (!empty($desired_slug)) {
			$subdir_name = untrailingslashit(str_replace(trailingslashit($remote_source), '', $source));

			if (!empty($subdir_name) && $subdir_name !== $desired_slug) {
				$from_path = untrailingslashit($source);
				$to_path = trailingslashit($remote_source) . $desired_slug;

				if (true === $GLOBALS['wp_filesystem']->move($from_path, $to_path)) {
					return trailingslashit($to_path);
				} else {
					return new WP_Error(
							'rename_failed',
							esc_html__('The remote plugin package does not contain a folder with the desired slug and renaming did not work.',
									'tgmpa') . ' ' . esc_html__('Please contact the plugin provider and ask them to package their plugin according to the WordPress guidelines.',
									'tgmpa'),
							array(
									'found'    => $subdir_name,
									'expected' => $desired_slug,
							)
					);
				}
			} elseif (empty($subdir_name)) {
				return new WP_Error(
						'packaged_wrong',
						esc_html__('The remote plugin package consists of more than one file, but the files are not packaged in a folder.',
								'tgmpa') . ' ' . esc_html__('Please contact the plugin provider and ask them to package their plugin according to the WordPress guidelines.',
								'tgmpa'),
						array(
								'found'    => $subdir_name,
								'expected' => $desired_slug,
						)
				);
			}
		}

		return $source;
	}

	/**
	 * Activate a single plugin and send feedback about the result to the screen.
	 *
	 * @param string $file_path Path within wp-plugins/ to main plugin file.
	 * @param string $slug Plugin slug.
	 * @param bool $automatic Whether this is an automatic activation after an install. Defaults to false.
	 *                          This determines the styling of the output messages.
	 * @return bool False if an error was encountered, true otherwise.
	 * @since 2.5.0
	 *
	 */
	protected function activate_single_plugin(string $file_path, string $slug, bool $automatic = false)
	{
		if ($this->can_plugin_activate($slug)) {
			$activate = activate_plugin($file_path);

			if (is_wp_error($activate)) {
				echo '<div id="message" class="error"><p>', wp_kses_post($activate->get_error_message()), '</p></div>',
				'<p><a href="', esc_url($this->get_tgmpa_url()), '" target="_parent">', esc_html($this->strings['return']), '</a></p>';

				return false; // End it here if there is an error with activation.
			} else {
				if (!$automatic) {
					// Make sure message doesn't display again if bulk activation is performed
					// immediately after a single activation.
					// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Not using the superglobal.
					if (!isset($_POST['action'])) {
						echo '<div id="message" class="updated"><p>', esc_html($this->strings['activated_successfully']), ' <strong>', esc_html($this->plugins[$slug]['name']), '.</strong></p></div>';
					}
				} else {
					// Simpler message layout for use on the plugin install page.
					echo '<p>', esc_html($this->strings['plugin_activated']), '</p>';
				}
			}
		} elseif ($this->is_plugin_active($slug)) {
			// No simpler message format provided as this message should never be encountered
			// on the plugin install page.
			echo '<div id="message" class="error"><p>',
			sprintf(
					esc_html($this->strings['plugin_already_active']),
					'<strong>' . esc_html($this->plugins[$slug]['name']) . '</strong>'
			),
			'</p></div>';
		} elseif ($this->does_plugin_require_update($slug)) {
			if (!$automatic) {
				// Make sure message doesn't display again if bulk activation is performed
				// immediately after a single activation.
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Not using the superglobal.
				if (!isset($_POST['action'])) {
					echo '<div id="message" class="error"><p>',
					sprintf(
							esc_html($this->strings['plugin_needs_higher_version']),
							'<strong>' . esc_html($this->plugins[$slug]['name']) . '</strong>'
					),
					'</p></div>';
				}
			} else {
				// Simpler message layout for use on the plugin install page.
				echo '<p>', sprintf(esc_html($this->strings['plugin_needs_higher_version']),
						esc_html($this->plugins[$slug]['name'])), '</p>';
			}
		}

		return true;
	}

	/**
	 * Echoes required plugin notice.
	 *
	 * Outputs a message telling users that a specific plugin is required for
	 * their theme. If appropriate, it includes a link to the form page where
	 * users can install and activate the plugin.
	 *
	 * Returns early if we're on the Installation page.
	 *
	 * @return null Returns early if we're on the Installation page.
	 * @global object $current_screen
	 *
	 * @since 1.0.0
	 *
	 */
	public function notices()
	{
		// Remove nag on the install page / Return early if the nag message has been dismissed or user < author.
		if (($this->is_tgmpa_page() || $this->is_core_update_page()) || get_user_meta(get_current_user_id(),
						'tgmpa_dismissed_notice_' . $this->id,
						true) || !current_user_can(apply_filters('tgmpa_show_admin_notice_capability', 'publish_posts'))) {
			return;
		}

		// Store for the plugin slugs by message type.
		$message = array();

		// Initialize counters used to determine plurality of action link texts.
		$install_link_count = 0;
		$update_link_count = 0;
		$activate_link_count = 0;
		$total_required_action_count = 0;

		foreach ($this->plugins as $slug => $plugin) {
			if ($this->is_plugin_active($slug) && false === $this->does_plugin_have_update($slug)) {
				continue;
			}

			if (!$this->is_plugin_installed($slug)) {
				if (current_user_can('install_plugins')) {
					$install_link_count++;

					if (true === $plugin['required']) {
						$message['notice_can_install_required'][] = $slug;
					} else {
						$message['notice_can_install_recommended'][] = $slug;
					}
				}
				if (true === $plugin['required']) {
					$total_required_action_count++;
				}
			} else {
				if (!$this->is_plugin_active($slug) && $this->can_plugin_activate($slug)) {
					if (current_user_can('activate_plugins')) {
						$activate_link_count++;

						if (true === $plugin['required']) {
							$message['notice_can_activate_required'][] = $slug;
						} else {
							$message['notice_can_activate_recommended'][] = $slug;
						}
					}
					if (true === $plugin['required']) {
						$total_required_action_count++;
					}
				}

				if ($this->does_plugin_require_update($slug) || false !== $this->does_plugin_have_update($slug)) {

					if (current_user_can('update_plugins')) {
						$update_link_count++;

						if ($this->does_plugin_require_update($slug)) {
							$message['notice_ask_to_update'][] = $slug;
						} elseif (false !== $this->does_plugin_have_update($slug)) {
							$message['notice_ask_to_update_maybe'][] = $slug;
						}
					}
					if (true === $plugin['required']) {
						$total_required_action_count++;
					}
				}
			}
		}
		unset($slug, $plugin);

		// If we have notices to display, we move forward.
		if (!empty($message) || $total_required_action_count > 0) {
			krsort($message); // Sort messages.
			$rendered = '';

			// As add_settings_error() wraps the final message in a <p> and as the final message can't be
			// filtered, using <p>'s in our html would render invalid html output.
			$line_template = '<span style="display: block; margin: 0.5em 0.5em 0 0; clear: both;">%s</span>' . "\n";

			if (!current_user_can('activate_plugins') && !current_user_can('install_plugins') && !current_user_can('update_plugins')) {
				$rendered = esc_html($this->strings['notice_cannot_install_activate']) . ' ' . esc_html($this->strings['contact_admin']);
				$rendered .= $this->create_user_action_links_for_notice(0, 0, 0, $line_template);
			} else {

				// If dismissable is false and a message is set, output it now.
				if (!$this->dismissable && !empty($this->dismiss_msg)) {
					$rendered .= sprintf($line_template, wp_kses_post($this->dismiss_msg));
				}

				// Render the individual message lines for the notice.
				foreach ($message as $type => $plugin_group) {
					$linked_plugins = array();

					// Get the external info link for a plugin if one is available.
					foreach ($plugin_group as $plugin_slug) {
						$linked_plugins[] = $this->get_info_link($plugin_slug);
					}
					unset($plugin_slug);

					$count = count($plugin_group);
					$linked_plugins = array_map(array('Utils', 'wrap_in_em'), $linked_plugins);
					$last_plugin = array_pop($linked_plugins); // Pop off last name to prep for readability.
					$imploded = empty($linked_plugins) ? $last_plugin : (implode(', ', $linked_plugins) . ' ' . esc_html_x('and',
									'plugin A *and* plugin B', 'tgmpa') . ' ' . $last_plugin);

					$rendered .= sprintf(
							$line_template,
							sprintf(
									translate_nooped_plural($this->strings[$type], $count, 'tgmpa'),
									$imploded,
									$count
							)
					);

				}
				unset($type, $plugin_group, $linked_plugins, $count, $last_plugin, $imploded);

				$rendered .= $this->create_user_action_links_for_notice($install_link_count, $update_link_count,
						$activate_link_count, $line_template);
			}

			// Register the nag messages and prepare them to be processed.
			add_settings_error('tgmpa', 'tgmpa', $rendered, $this->get_admin_notice_class());
		}

		// Admin options pages already output settings_errors, so this is to avoid duplication.
		if ('options-general' !== $GLOBALS['current_screen']->parent_base) {
			$this->display_settings_errors();
		}

		return null;
	}

	/**
	 * Generate the user action links for the admin notice.
	 *
	 * @param int $install_count Number of plugins to install.
	 * @param int $update_count Number of plugins to update.
	 * @param int $activate_count Number of plugins to activate.
	 * @param int $line_template Template for the HTML tag to output a line.
	 * @return string Action links.
	 * @since 2.6.0
	 *
	 */
	protected function create_user_action_links_for_notice(
			int $install_count,
			int $update_count,
			int $activate_count,
			int $line_template
	) {
		// Setup action links.
		$action_links = array(
				'install'  => '',
				'update'   => '',
				'activate' => '',
				'dismiss'  => $this->dismissable ? '<a href="' . esc_url(wp_nonce_url(add_query_arg('tgmpa-dismiss',
								'dismiss_admin_notices'),
								'tgmpa-dismiss-' . get_current_user_id())) . '" class="dismiss-notice" target="_parent">' . esc_html($this->strings['dismiss']) . '</a>' : '',
		);

		$link_template = /** @lang text */
				'<a href="%2$s">%1$s</a>';

		if (current_user_can('install_plugins')) {
			if ($install_count > 0) {
				$action_links['install'] = sprintf(
						$link_template,
						translate_nooped_plural($this->strings['install_link'], $install_count, 'tgmpa'),
						esc_url($this->get_tgmpa_status_url('install'))
				);
			}
			if ($update_count > 0) {
				$action_links['update'] = sprintf(
						$link_template,
						translate_nooped_plural($this->strings['update_link'], $update_count, 'tgmpa'),
						esc_url($this->get_tgmpa_status_url('update'))
				);
			}
		}

		if (current_user_can('activate_plugins') && $activate_count > 0) {
			$action_links['activate'] = sprintf(
					$link_template,
					translate_nooped_plural($this->strings['activate_link'], $activate_count, 'tgmpa'),
					esc_url($this->get_tgmpa_status_url('activate'))
			);
		}

		$action_links = apply_filters('tgmpa_notice_action_links', $action_links);

		$action_links = array_filter((array)$action_links); // Remove any empty array items.

		if (!empty($action_links)) {
			$action_links = sprintf($line_template, implode(' | ', $action_links));
			return apply_filters('tgmpa_notice_rendered_action_links', $action_links);
		} else {
			return '';
		}
	}

	/**
	 * Get admin notice class.
	 *
	 * Work around all the changes to the various admin notice classes between WP 4.4 and 3.7
	 * (the lowest supported version by TGMPA).
	 *
	 * @return string
	 * @since 2.6.0
	 *
	 */
	protected function get_admin_notice_class()
	{
		if (!empty($this->strings['nag_type'])) {
			return sanitize_html_class(strtolower($this->strings['nag_type']));
		} else {
			if (version_compare($this->wp_version, '4.2', '>=')) {
				return 'notice-warning';
			} elseif (version_compare($this->wp_version, '4.1', '>=')) {
				return 'notice';
			} else {
				return 'updated';
			}
		}
	}

	/**
	 * Display settings errors and remove those which have been displayed to avoid duplicate messages showing
	 *
	 * @since 2.5.0
	 */
	protected function display_settings_errors()
	{
		global $wp_settings_errors;

		settings_errors('tgmpa');

		foreach ((array)$wp_settings_errors as $key => $details) {
			if ('tgmpa' === $details['setting']) {
				unset($wp_settings_errors[$key]);
				break;
			}
		}
	}

	/**
	 * Register dismissal of admin notices.
	 *
	 * Acts on the dismiss link in the admin nag messages.
	 * If clicked, the admin notice disappears and will no longer be visible to this user.
	 *
	 * @since 2.1.0
	 */
	public function dismiss()
	{
		if (isset($_GET['tgmpa-dismiss']) && check_admin_referer('tgmpa-dismiss-' . get_current_user_id())) {
			update_user_meta(get_current_user_id(), 'tgmpa_dismissed_notice_' . $this->id, 1);
		}
	}

	/**
	 * Add individual plugin to our collection of plugins.
	 *
	 * If the required keys are not set or the plugin has already
	 * been registered, the plugin is not added.
	 *
	 * @param array|null $plugin Array of plugin arguments or null if invalid argument.
	 * @return null Return early if incorrect argument.
	 * @since 2.0.0
	 *
	 */
	public function register(array $plugin = [])
	{
		if (empty($plugin['slug']) || empty($plugin['name'])) {
			return;
		}

		if (!is_string($plugin['slug']) || isset($this->plugins[$plugin['slug']])) {
			return;
		}

		$defaults = array(
				'name'               => '',      // String.
				'slug'               => '',      // String.
				'source'             => 'repo',  // String.
				'required'           => false,   // Boolean.
				'version'            => '',      // String.
				'force_activation'   => false,   // Boolean.
				'force_deactivation' => false,   // Boolean.
				'external_url'       => '',      // String.
				'is_callable'        => '',      // String or array.
		);

		// Prepare the received data.
		$plugin = wp_parse_args($plugin, $defaults);

		// Standardize the received slug.
		$plugin['slug'] = $this->sanitize_key($plugin['slug']);

		// Forgive users for using string versions of booleans or floats for version number.
		$plugin['version'] = (string)$plugin['version'];
		$plugin['source'] = empty($plugin['source']) ? 'repo' : $plugin['source'];
		$plugin['required'] = Utils::validate_bool($plugin['required']);
		$plugin['force_activation'] = Utils::validate_bool($plugin['force_activation']);
		$plugin['force_deactivation'] = Utils::validate_bool($plugin['force_deactivation']);

		// Enrich the received data.
		$plugin['file_path'] = $this->_get_plugin_basename_from_slug($plugin['slug']);
		$plugin['source_type'] = $this->get_plugin_source_type($plugin['source']);

		// Set the class properties.
		$this->plugins[$plugin['slug']] = $plugin;
		$this->sort_order[$plugin['slug']] = $plugin['name'];

		// Should we add the force activation hook ?
		if (true === $plugin['force_activation']) {
			$this->has_forced_activation = true;
		}

		// Should we add the force deactivation hook ?
		if (true === $plugin['force_deactivation']) {
			$this->has_forced_deactivation = true;
		}

		return null;
	}

	/**
	 * Determine what type of source the plugin comes from.
	 *
	 * @param string $source The source of the plugin as provided, either empty (= WP repo), a file path
	 *                       (= bundled) or an external URL.
	 * @return string 'repo', 'external', or 'bundled'
	 * @since 2.5.0
	 *
	 */
	protected function get_plugin_source_type(string $source)
	{
		if ('repo' === $source || preg_match(self::WP_REPO_REGEX, $source)) {
			return 'repo';
		} elseif (preg_match(self::IS_URL_REGEX, $source)) {
			return 'external';
		} else {
			return 'bundled';
		}
	}

	/**
	 * Sanitizes a string key.
	 *
	 * Near duplicate of WP Core `sanitize_key()`. The difference is that uppercase characters *are*
	 * allowed, so as not to break upgrade paths from non-standard bundled plugins using uppercase
	 * characters in the plugin directory path/slug. Silly them.
	 *
	 * @see https://developer.wordpress.org/reference/hooks/sanitize_key/
	 *
	 * @since 2.5.0
	 *
	 * @param string $key String key.
	 * @return string Sanitized key
	 */
	public function sanitize_key(string $key)
	{
		$raw_key = $key;
		$key = preg_replace('`[^A-Za-z0-9_-]`', '', $key);

		/**
		 * Filter a sanitized key string.
		 *
		 * @param string $key Sanitized key.
		 * @param string $raw_key The key prior to sanitization.
		 * @since 2.5.0
		 *
		 */
		return apply_filters('tgmpa_sanitize_key', $key, $raw_key);
	}

	/**
	 * Amend default configuration settings.
	 *
	 * @param array $config Array of config options to pass as class properties.
	 * @since 2.0.0
	 *
	 */
	public function config(array $config)
	{
		$keys = array(
				'id',
				'default_path',
				'has_notices',
				'dismissable',
				'dismiss_msg',
				'menu',
				'parent_slug',
				'capability',
				'is_automatic',
				'message',
				'strings',
		);

		foreach ($keys as $key) {
			if (isset($config[$key])) {
				if (is_array($config[$key])) {
					$this->$key = array_merge($this->$key, $config[$key]);
				} else {
					$this->$key = $config[$key];
				}
			}
		}
	}

	/**
	 * Amend action link after plugin installation.
	 *
	 * @param array $install_actions Existing array of actions.
	 * @return false|array Amended array of actions.
	 * @since 2.0.0
	 *
	 */
	public function actions(array $install_actions)
	{
		// Remove action links on the TGMPA install page.
		if ($this->is_tgmpa_page()) {
			return false;
		}

		return $install_actions;
	}

	/**
	 * Flushes the plugins cache on theme switch to prevent stale entries
	 * from remaining in the plugin table.
	 *
	 * @param bool $clear_update_cache Optional. Whether to clear the Plugin updates cache.
	 *                                 Parameter added in v2.5.0.
	 * @since 2.4.0
	 *
	 */
	public function flush_plugins_cache(bool $clear_update_cache = true)
	{
		wp_clean_plugins_cache($clear_update_cache);
	}

	/**
	 * Set file_path key for each installed plugin.
	 *
	 * @param string $plugin_slug Optional. If set, only (re-)populates the file path for that specific plugin.
	 *                            Parameter added in v2.5.0.
	 * @since 2.1.0
	 *
	 */
	public function populate_file_path(string $plugin_slug = '')
	{
		if (!empty($plugin_slug) && is_string($plugin_slug) && isset($this->plugins[$plugin_slug])) {
			$this->plugins[$plugin_slug]['file_path'] = $this->_get_plugin_basename_from_slug($plugin_slug);
		} else {
			// Add file_path key for all plugins.
			foreach ($this->plugins as $slug => $values) {
				$this->plugins[$slug]['file_path'] = $this->_get_plugin_basename_from_slug($slug);
			}
		}
	}

	/**
	 * Helper function to extract the file path of the plugin file from the
	 * plugin slug, if the plugin is installed.
	 *
	 * @param string $slug Plugin slug (typically folder name) as provided by the developer.
	 * @return string Either file path for plugin if installed, or just the plugin slug.
	 * @since 2.0.0
	 *
	 */
	protected function _get_plugin_basename_from_slug(string $slug)
	{
		$keys = array_keys($this->get_plugins());

		foreach ($keys as $key) {
			if (preg_match('|^' . $slug . '/|', $key)) {
				return $key;
			}
		}

		return $slug;
	}

	/**
	 * Retrieve plugin data, given the plugin name.
	 *
	 * Loops through the registered plugins looking for $name. If it finds it,
	 * it returns the $data from that plugin. Otherwise, returns false.
	 *
	 * @param string $name Name of the plugin, as it was registered.
	 * @param string $data Optional. Array key of plugin data to return. Default is slug.
	 * @return string|boolean Plugin slug if found, false otherwise.
	 * @since 2.1.0
	 *
	 * @noinspection PhpUnused
	 */
	public function _get_plugin_data_from_name(string $name, string $data = 'slug')
	{
		foreach ($this->plugins as $values) {
			if ($name === $values['name'] && isset($values[$data])) {
				return $values[$data];
			}
		}

		return false;
	}

	/**
	 * Retrieve the download URL for a package.
	 *
	 * @param string $slug Plugin slug.
	 * @return string Plugin download URL or path to local file or empty string if undetermined.
	 * @since 2.5.0
	 *
	 */
	public function get_download_url(string $slug)
	{
		$dl_source = '';

		switch ($this->plugins[$slug]['source_type']) {
			case 'repo':
				return $this->get_wp_repo_download_url($slug);
			case 'external':
				return $this->plugins[$slug]['source'];
			case 'bundled':
				return $this->default_path . $this->plugins[$slug]['source'];
		}

		return $dl_source; // Should never happen.
	}

	/**
	 * Retrieve the download URL for a WP repo package.
	 *
	 * @param string $slug Plugin slug.
	 * @return string Plugin download URL.
	 * @since 2.5.0
	 *
	 */
	protected function get_wp_repo_download_url(string $slug)
	{
		$source = '';
		$api = $this->get_plugins_api($slug);

		if (false !== $api && isset($api->download_link)) {
			$source = $api->download_link;
		}

		return $source;
	}

	/**
	 * Try to grab information from WordPress API.
	 *
	 * @param string $slug Plugin slug.
	 * @return false Plugins_api response object on success, WP_Error on failure.
	 * @since 2.5.0
	 *
	 */
	protected function get_plugins_api(string $slug)
	{
		$api = []; // Cache received responses.

			if (!function_exists('plugins_api')) {
				require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
			}

			$response = plugins_api(
					'plugin_information',
					array(
							'slug'   => $slug,
							'fields' => array(
									'sections' => false,
							),
					)
			);

			$api[$slug] = false;

			if (is_wp_error($response)) {
				wp_die(esc_html($this->strings['oops']));
			} else {
				$api[$slug] = $response;
			}

		return $api[$slug];
	}

	/**
	 * Retrieve a link to a plugin information page.
	 *
	 * @param string $slug Plugin slug.
	 * @return string Fully formed html link to a plugin information page if available
	 *                or the plugin name if not.
	 * @since 2.5.0
	 *
	 */
	public function get_info_link(string $slug)
	{
		if (!empty($this->plugins[$slug]['external_url']) && preg_match(self::IS_URL_REGEX,
						$this->plugins[$slug]['external_url'])) {
			$link = sprintf(
			/** @lang text */ '<a href="%1$s" target="_blank">%2$s</a>',
					esc_url($this->plugins[$slug]['external_url']),
					esc_html($this->plugins[$slug]['name'])
			);
		} elseif ('repo' === $this->plugins[$slug]['source_type']) {
			$url = add_query_arg(
					array(
							'tab'       => 'plugin-information',
							'plugin'    => urlencode($slug),
							'TB_iframe' => 'true',
							'width'     => '640',
							'height'    => '500',
					),
					self_admin_url('plugin-install.php')
			);

			$link = sprintf(
			/** @lang text */ '<a href="%1$s" class="thickbox">%2$s</a>',
					esc_url($url),
					esc_html($this->plugins[$slug]['name'])
			);
		} else {
			$link = esc_html($this->plugins[$slug]['name']); // No hyperlink.
		}

		return $link;
	}

	/**
	 * Determine if we're on the TGMPA Install page.
	 *
	 * @return boolean True when on the TGMPA page, false otherwise.
	 * @since 2.1.0
	 *
	 */
	protected function is_tgmpa_page()
	{
		return isset($_GET['page']) && $this->menu === $_GET['page'];
	}

	/**
	 * Determine if we're on a WP Core installation/upgrade page.
	 *
	 * @return boolean True when on a WP Core installation/upgrade page, false otherwise.
	 * @since 2.6.0
	 *
	 */
	protected function is_core_update_page()
	{
		// Current screen is not always available, most notably on the customizer screen.
		if (!function_exists('get_current_screen')) {
			return false;
		}

		$screen = get_current_screen();

		if ('update-core' === $screen->base) {
			// Core update screen.
			return true;
		} elseif ('plugins' === $screen->base && !empty($_POST['action'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			// Plugins bulk update screen.
			return true;
		} elseif ('update' === $screen->base && !empty($_POST['action'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			// Individual updates (ajax call).
			return true;
		}

		return false;
	}

	/**
	 * Retrieve the URL to the TGMPA Install page.
	 *
	 * I.e. depending on the config settings passed something along the lines of:
	 * http://example.com/wp-admin/themes.php?page=tgmpa-install-plugins
	 *
	 * @return string Properly encoded URL (not escaped).
	 * @since 2.5.0
	 *
	 */
	public function get_tgmpa_url()
	{
		static $url;

		if (!isset($url)) {
			$parent = $this->parent_slug;
			if (false === strpos($parent, '.php')) {
				$parent = 'admin.php';
			}
			$url = add_query_arg(
					array(
							'page' => urlencode($this->menu),
					),
					self_admin_url($parent)
			);
		}

		return $url;
	}

	/**
	 * Retrieve the URL to the TGMPA Install page for a specific plugin status (view).
	 *
	 * I.e. depending on the config settings passed something along the lines of:
	 * http://example.com/wp-admin/themes.php?page=tgmpa-install-plugins&plugin_status=install
	 *
	 * @param string $status Plugin status - either 'install', 'update' or 'activate'.
	 * @return string Properly encoded URL (not escaped).
	 * @since 2.5.0
	 *
	 */
	public function get_tgmpa_status_url(string $status)
	{
		return add_query_arg(
				array(
						'plugin_status' => urlencode($status),
				),
				$this->get_tgmpa_url()
		);
	}

	/**
	 * Determine whether there are open actions for plugins registered with TGMPA.
	 *
	 * @return bool True if complete, i.e. no outstanding actions. False otherwise.
	 * @since 2.5.0
	 *
	 */
	public function is_tgmpa_complete()
	{
		$complete = true;
		foreach ($this->plugins as $slug => $plugin) {
			if (!$this->is_plugin_active($slug) || false !== $this->does_plugin_have_update($slug)) {
				$complete = false;
				break;
			}
		}

		return $complete;
	}

	/**
	 * Check if a plugin is installed. Does not take must-use plugins into account.
	 *
	 * @param string $slug Plugin slug.
	 * @return bool True if installed, false otherwise.
	 * @since 2.5.0
	 *
	 */
	public function is_plugin_installed(string $slug)
	{
		$installed_plugins = $this->get_plugins(); // Retrieve a list of all installed plugins (WP cached).

		return (!empty($installed_plugins[$this->plugins[$slug]['file_path']]));
	}

	/**
	 * Check if a plugin is active.
	 *
	 * @param string $slug Plugin slug.
	 * @return bool True if active, false otherwise.
	 * @since 2.5.0
	 *
	 */
	public function is_plugin_active(string $slug)
	{
		return ((!empty($this->plugins[$slug]['is_callable']) && is_callable($this->plugins[$slug]['is_callable'])) || is_plugin_active($this->plugins[$slug]['file_path']));
	}

	/**
	 * Check if a plugin can be updated, i.e. if we have information on the minimum WP version required
	 * available, check whether the current install meets them.
	 *
	 * @param string $slug Plugin slug.
	 * @return bool True if OK to update, false otherwise.
	 * @since 2.5.0
	 *
	 */
	public function can_plugin_update(string $slug)
	{
		// We currently can't get reliable info on non-WP-repo plugins - issue #380.
		if ('repo' !== $this->plugins[$slug]['source_type']) {
			return true;
		}

		$api = $this->get_plugins_api($slug);

		if (false !== $api && isset($api->requires)) {
			return version_compare($this->wp_version, $api->requires, '>=');
		}

		// No usable info received from the plugins API, presume we can update.
		return true;
	}

	/**
	 * Check to see if the plugin is 'updatetable', i.e. installed, with an update available
	 * and no WP version requirements blocking it.
	 *
	 * @param string $slug Plugin slug.
	 * @return bool True if OK to proceed with update, false otherwise.
	 * @since 2.6.0
	 *
	 */
	public function is_plugin_updatetable(string $slug)
	{
		if (!$this->is_plugin_installed($slug)) {
			return false;
		} else {
			return (false !== $this->does_plugin_have_update($slug) && $this->can_plugin_update($slug));
		}
	}

	/**
	 * Check if a plugin can be activated, i.e. is not currently active and meets the minimum
	 * plugin version requirements set in TGMPA (if any).
	 *
	 * @param string $slug Plugin slug.
	 * @return bool True if OK to activate, false otherwise.
	 * @since 2.5.0
	 *
	 */
	public function can_plugin_activate(string $slug)
	{
		return (!$this->is_plugin_active($slug) && !$this->does_plugin_require_update($slug));
	}

	/**
	 * Retrieve the version number of an installed plugin.
	 *
	 * @param string $slug Plugin slug.
	 * @return string Version number than string or an empty string if the plugin is not installed
	 *                or version unknown (plugins which don't comply with the plugin header standard).
	 * @since 2.5.0
	 *
	 */
	public function get_installed_version(string $slug)
	{
		$installed_plugins = $this->get_plugins(); // Retrieve a list of all installed plugins (WP cached).

		if (!empty($installed_plugins[$this->plugins[$slug]['file_path']]['Version'])) {
			return $installed_plugins[$this->plugins[$slug]['file_path']]['Version'];
		}

		return '';
	}

	/**
	 * Check whether a plugin complies with the minimum version requirements.
	 *
	 * @param string $slug Plugin slug.
	 * @return bool True when a plugin needs to be updated, otherwise false.
	 * @since 2.5.0
	 *
	 */
	public function does_plugin_require_update(string $slug)
	{
		$installed_version = $this->get_installed_version($slug);
		$minimum_version = $this->plugins[$slug]['version'];

		return version_compare($minimum_version, $installed_version, '>');
	}

	/**
	 * Check whether there is an update available for a plugin.
	 *
	 * @param string $slug Plugin slug.
	 * @return false|string Version number string of the available update or false if no update available.
	 * @since 2.5.0
	 *
	 */
	public function does_plugin_have_update(string $slug)
	{
		// Presume bundled and external plugins will point to a package which meets the minimum required version.
		if ('repo' !== $this->plugins[$slug]['source_type']) {
			if ($this->does_plugin_require_update($slug)) {
				return $this->plugins[$slug]['version'];
			}

			return false;
		}

		$repo_updates = get_site_transient('update_plugins');

		if (isset($repo_updates->response[$this->plugins[$slug]['file_path']]->new_version)) {
			return $repo_updates->response[$this->plugins[$slug]['file_path']]->new_version;
		}

		return false;
	}

	/**
	 * Retrieve potential upgrade notice for a plugin.
	 *
	 * @param string $slug Plugin slug.
	 * @return string The upgrade notice or an empty string if no message was available or provided.
	 * @since 2.5.0
	 *
	 */
	public function get_upgrade_notice(string $slug)
	{
		// We currently can't get reliable info on non-WP-repo plugins - issue #380.
		if ('repo' !== $this->plugins[$slug]['source_type']) {
			return '';
		}

		$repo_updates = get_site_transient('update_plugins');

		if (!empty($repo_updates->response[$this->plugins[$slug]['file_path']]->upgrade_notice)) {
			return $repo_updates->response[$this->plugins[$slug]['file_path']]->upgrade_notice;
		}

		return '';
	}

	/**
	 * Wrapper around the core WP get_plugins function, making sure it's actually available.
	 *
	 * @param string $plugin_folder Optional. Relative path to single plugin folder.
	 * @return array Array of installed plugins with plugin information.
	 * @since 2.5.0
	 *
	 */
	public function get_plugins(string $plugin_folder = '')
	{
		if (!function_exists('get_plugins')) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return get_plugins($plugin_folder);
	}

	/**
	 * Delete dismissable nag option when theme is switched.
	 *
	 * This ensures that the user(s) is/are again reminded via nag of required
	 * and/or recommended plugins if they re-activate the theme.
	 *
	 * @since 2.1.1
	 */
	public function update_dismiss()
	{
		delete_metadata('user', null, 'tgmpa_dismissed_notice_' . $this->id, null, true);
	}

	/**
	 * Forces plugin activation if the parameter 'force_activation' is
	 * set to true.
	 *
	 * This allows theme authors to specify certain plugins that must be
	 * active at all times while using the current theme.
	 *
	 * Please take special care when using this parameter as it has the
	 * potential to be harmful if not used correctly. Setting this parameter
	 * to true will not allow the specified plugin to be deactivated unless
	 * the user switches themes.
	 *
	 * @since 2.2.0
	 */
	public function force_activation()
	{
		foreach ($this->plugins as $slug => $plugin) {
			if (true === $plugin['force_activation']) {
				if (!$this->is_plugin_installed($slug)) {
					// Oops, plugin isn't there so iterate to next condition.
					continue;
				} elseif ($this->can_plugin_activate($slug)) {
					// There we go, activate the plugin.
					activate_plugin($plugin['file_path']);
				}
			}
		}
	}

	/**
	 * Forces plugin deactivation if the parameter 'force_deactivation'
	 * is set to true and adds the plugin to the 'recently active' plugins list.
	 *
	 * This allows theme authors to specify certain plugins that must be
	 * deactivated upon switching from the current theme to another.
	 *
	 * Please take special care when using this parameter as it has the
	 * potential to be harmful if not used correctly.
	 *
	 * @since 2.2.0
	 */
	public function force_deactivation()
	{
		$deactivated = array();

		foreach ($this->plugins as $plugin) {
			/*
			 * Only proceed forward if the parameter is set to true and plugin is active
			 * as a 'normal' (not must-use) plugin.
			 */
			if (true === $plugin['force_deactivation'] && is_plugin_active($plugin['file_path'])) {
				deactivate_plugins($plugin['file_path']);
				$deactivated[$plugin['file_path']] = time();
			}
		}

		if (!empty($deactivated)) {
			update_option('recently_activated', $deactivated + (array)get_option('recently_activated'));
		}
	}

	/**
	 * Echo the current TGMPA version number to the page.
	 *
	 * @since 2.5.0
	 */
	public function show_tgmpa_version()
	{
		echo '<p style="float: right; padding: 0 1.5em 0.5em 0;"><strong><small>',
		esc_html(
				sprintf(
				/* translators: %s: version number */
						__('TGMPA v%s', 'tgmpa'),
						self::TGMPA_VERSION
				)
		),
		'</small></strong></p>';
	}

	/**
	 * Adds CSS to admin head.
	 *
	 * @since 2.6.2
	 */
	public function admin_css()
	{
		if (!$this->is_tgmpa_page()) {
			return;
		}

		echo '
			<style>
			#tgmpa-plugins .tgmpa-type-required > th {
				border-left: 3px solid #dc3232;
			}
			</style>';
	}

	/**
	 * Returns the singleton instance of the class.
	 *
	 * @return TGMPA The TGM_Plugin_Activation object.
	 * @since 2.4.0
	 *
	 */
	public static function get_instance()
	{
		if (!isset(self::$instance) && !(self::$instance instanceof self)) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public static function activation()
	{
		$GLOBALS['tgmpa'] = self::get_instance();
	}
}
