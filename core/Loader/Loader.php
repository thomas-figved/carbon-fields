<?php

namespace Carbon_Fields\Loader;

use Carbon_Fields\Container\Repository as ContainerRepository;
use Carbon_Fields\Exception\Incorrect_Syntax_Exception;
use Carbon_Fields\Helper\Helper;
use Carbon_Fields\Libraries\Sidebar_Manager\Sidebar_Manager;
use Carbon_Fields\Pimple\Container as PimpleContainer;
use Carbon_Fields\Service\Legacy_Storage_Service_v_1_5;
use Carbon_Fields\Service\Meta_Query_Service;
use Carbon_Fields\Service\REST_API_Service;

/**
 * Loader and main initialization
 */
class Loader {

	protected $sidebar_manager;

	protected $container_repository;

	public function __construct( Sidebar_Manager $sidebar_manager, ContainerRepository $container_repository ) {
		$this->sidebar_manager = $sidebar_manager;
		$this->container_repository = $container_repository;
	}

	/**
	 * Hook the main Carbon Fields initialization functionality.
	 */
	public function boot() {
		if ( ! defined( 'ABSPATH' ) ) {
			throw new \Exception( 'Carbon Fields cannot be booted outside of a WordPress environment.' );
		}

		if ( did_action( 'init' ) ) {
			throw new \Exception( 'Carbon Fields must be booted before the "init" WordPress action has fired.' );
		}

		include_once( dirname( dirname( __DIR__ ) ) . '/config.php' );
		include_once( \Carbon_Fields\DIR . '/core/functions.php' );

		add_action( 'after_setup_theme', array( $this, 'load_textdomain' ), 9999 );
		add_action( 'init', array( $this, 'trigger_fields_register' ), 0 );
		add_action( 'carbon_fields_fields_registered', array( $this, 'initialize_containers' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_media_browser' ), 0 );
		add_action( 'admin_print_footer_scripts', array( $this, 'enqueue_scripts' ), 0 );
		add_action( 'admin_print_footer_scripts', array( $this, 'print_json_data_script' ), 9 );
		add_action( 'admin_print_footer_scripts', array( $this, 'print_bootstrap_js' ), 100 );
		add_action( 'edit_form_after_title', array( $this, 'add_carbon_fields_meta_box_contexts' ) );
		add_action( 'wp_ajax_carbon_fields_fetch_association_options', array( $this, 'fetch_association_options' ) );

		# Enable the legacy storage service
		\Carbon_Fields\Carbon_Fields::service( 'legacy_storage' )->enable();

		# Enable the meta query service
		\Carbon_Fields\Carbon_Fields::service( 'meta_query' )->enable();

		# Enable the REST API service
		\Carbon_Fields\Carbon_Fields::service( 'rest_api' )->enable();

		# Initialize sidebar manager
		$this->sidebar_manager->boot();
	}

	/**
	 * Load the plugin textdomain.
	 */
	public function load_textdomain() {
		$dir = \Carbon_Fields\DIR . '/languages/';
		$domain = 'carbon-fields';
		$domain_ui = 'carbon-fields-ui';
		$locale = get_locale();
		$path = $dir . $domain . '-' . $locale . '.mo';
		$path_ui = $dir . $domain_ui . '-' . $locale . '.mo';
		load_textdomain( $domain, $path );
		load_textdomain( $domain_ui, $path_ui );
	}

	/**
	 * Load the ui textdomain
	 */
	public function get_ui_translations() {
		$domain ='carbon-fields-ui';
		$translations = get_translations_for_domain( $domain );

		$locale = array(
			'' => array(
				'domain' => $domain,
				'lang'   => is_admin() ? get_user_locale() : get_locale(),
			),
		);

		if ( ! empty( $translations->headers['Plural-Forms'] ) ) {
			$locale['']['plural_forms'] = $translations->headers['Plural-Forms'];
		}

		foreach ( $translations->entries as $msgid => $entry ) {
			$locale[ $msgid ] = $entry->translations;
		}

		return $locale;
	}

	/**
	 * Register containers and fields.
	 */
	public function trigger_fields_register() {
		try {
			do_action( 'carbon_fields_register_fields' );
			do_action( 'carbon_fields_fields_registered' );
		} catch ( Incorrect_Syntax_Exception $e ) {
			$callback = '';
			foreach ( $e->getTrace() as $trace ) {
				$callback .= '<br/>' . ( isset( $trace['file'] ) ? $trace['file'] . ':' . $trace['line'] : $trace['function'] . '()' );
			}
			wp_die( '<h3>' . $e->getMessage() . '</h3><small>' . $callback . '</small>' );
		}
	}

	/**
	 * Initialize containers.
	 */
	public function initialize_containers() {
		$this->container_repository->initialize_containers();
	}

	/**
	 * Initialize the media browser.
	 */
	public function enqueue_media_browser() {
		wp_enqueue_media();
	}

	/**
	 * Initialize main scripts
	 */
	public function enqueue_scripts() {
		global $pagenow;

		$locale = get_locale();
		$short_locale = substr( $locale, 0, 2 );
		$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
		$context = wp_script_is( 'wp-element' ) ? 'gutenberg' : 'classic';

		wp_enqueue_style( 'carbon-fields-core', \Carbon_Fields\URL . '/build/' . $context . '/core' . $suffix . '.css', array(), \Carbon_Fields\VERSION );
		wp_enqueue_style( 'carbon-fields-metaboxes', \Carbon_Fields\URL . '/build/' . $context . '/metaboxes' . $suffix . '.css', array(), \Carbon_Fields\VERSION );

		wp_enqueue_script( 'carbon-fields-vendor', \Carbon_Fields\URL . '/build/' . $context . '/vendor' . $suffix . '.js', array( 'jquery' ), \Carbon_Fields\VERSION );
		wp_enqueue_script( 'carbon-fields-new-core', \Carbon_Fields\URL . '/build/' . $context . '/core' . $suffix . '.js', array(), \Carbon_Fields\VERSION );
		wp_enqueue_script( 'carbon-fields-metaboxes', \Carbon_Fields\URL . '/build/' . $context . '/metaboxes' . $suffix . '.js', array(), \Carbon_Fields\VERSION );

		if ( $context === 'gutenberg' ) {
			wp_enqueue_style( 'carbon-fields-blocks-css', \Carbon_Fields\URL . '/build/' . $context . '/blocks' . $suffix . '.css', array(), \Carbon_Fields\VERSION );

			wp_enqueue_script( 'carbon-fields-blocks', \Carbon_Fields\URL . '/build/' . $context . '/blocks' . $suffix . '.js', array( 'carbon-fields-vendor' ), \Carbon_Fields\VERSION );
		}


		// wp_enqueue_style( 'carbon-fields-core', \Carbon_Fields\URL . '/assets/dist/carbon.css', array(), \Carbon_Fields\VERSION );

		// wp_enqueue_script( 'carbon-fields-vendor', \Carbon_Fields\URL . '/assets/dist/carbon.vendor' . $suffix . '.js', array( 'jquery' ), \Carbon_Fields\VERSION );
		// wp_enqueue_script( 'carbon-fields-core', \Carbon_Fields\URL . '/assets/dist/carbon.core' . $suffix . '.js', array( 'carbon-fields-vendor', 'quicktags', 'editor' ), \Carbon_Fields\VERSION );
		// wp_enqueue_script( 'carbon-fields-boot', \Carbon_Fields\URL . '/assets/dist/carbon.boot' . $suffix . '.js', array( 'carbon-fields-core' ), \Carbon_Fields\VERSION );

		wp_localize_script( 'carbon-fields-vendor', 'cf', apply_filters( 'carbon_fields_config', array(
			'config' => array(
				'locale' => $this->get_ui_translations(),
				'pagenow' => $pagenow,
			)
		) ) );

		wp_localize_script( 'carbon-fields-vendor', 'carbonFieldsConfig', apply_filters( 'carbon_fields_config', array(
			'compactInput' => \Carbon_Fields\COMPACT_INPUT,
			'compactInputKey' => \Carbon_Fields\COMPACT_INPUT_KEY,
		) ) );

	}

	/**
	 * Add custom meta box contexts
	 */
	public function add_carbon_fields_meta_box_contexts() {
		global $post, $wp_meta_boxes;

		$context = 'carbon_fields_after_title';
		do_meta_boxes( get_current_screen(), $context, $post );
	}

	/**
	 * Retrieve containers and sidebars for use in the JS.
	 *
	 * @return array $carbon_data
	 */
	public function get_json_data() {
		$carbon_data = array(
			'blocks' => array(),
			'containers' => array(),
			'sidebars' => array(),
		);

		$containers = $this->container_repository->get_active_containers();

		foreach ( $containers as $container ) {
			$container_data = $container->to_json( true );

			if ( is_a($container, '\\Carbon_Fields\\Container\\Block_Container', true ) ) {
				$carbon_data['blocks'][] = $container_data;
			} else {
				$carbon_data['containers'][] = $container_data;
			}
		}

		$carbon_data['sidebars'] = Helper::get_active_sidebars();

		return $carbon_data;
	}

	/**
	 * Print the carbon JSON data script.
	 */
	public function print_json_data_script() {
		wp_add_inline_script( 'carbon-fields-vendor', 'window.cf = window.cf || {}', 'before' );
		wp_add_inline_script( 'carbon-fields-vendor', sprintf( 'window.cf.preloaded = %s', wp_json_encode( $this->get_json_data() ) ), 'before' );
		?>
<script type="text/javascript">
<!--//--><![CDATA[//><!--
var carbon_json = <?php echo wp_json_encode( $this->get_json_data() ); ?>;
//--><!]]>
</script>
		<?php
	}

	/**
	 * Print the bootstrap code for the fields.
	 */
	public function print_bootstrap_js() {
		?>
		<script type="text/javascript">
			if (window['carbon.boot'] && typeof window['carbon.boot'].default === 'function') {
				window['carbon.boot'].default();
			}
		</script>
		<?php
	}

	/**
	 * Handle association field options fetch.
	 *
	 * @access public
	 *
	 * @return array
	 */
	public function fetch_association_options() {
		$page = isset( $_GET['page'] ) ? absint( $_GET['page'] )              : 1;
		$term = isset( $_GET['term'] ) ? sanitize_text_field( $_GET['term'] ) : '';

		$container_id = $_GET['container_id'];
		$field_name   = $_GET['field_name'];

		$field = Helper::get_field( null, $container_id, $field_name );

		return wp_send_json_success( $field->get_options( array(
			'page' => $page,
			'term' => $term,
		) ) );
	}
}
