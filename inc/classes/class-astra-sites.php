<?php
/**
 * Astra Sites
 *
 * @since  1.0.0
 * @package Astra Sites
 */

defined( 'ABSPATH' ) or exit;

if ( ! class_exists( 'Astra_Sites' ) ) :

	/**
	 * Astra_Sites
	 */
	class Astra_Sites {

		/**
		 * API URL which is used to get the response from.
		 *
		 * @since  1.0.0
		 * @var (String) URL
		 */
		public $api_url;

		/**
		 * Instance of Astra_Sites
		 *
		 * @since  1.0.0
		 * @var (Object) Astra_Sites
		 */
		private static $_instance = null;

		/**
		 * Localization variable
		 *
		 * @since  x.x.x
		 * @var (Array) $local_vars
		 */
		public static $local_vars = array();

		/**
		 * Instance of Astra_Sites.
		 *
		 * @since  1.0.0
		 *
		 * @return object Class object.
		 */
		public static function get_instance() {
			if ( ! isset( self::$_instance ) ) {
				self::$_instance = new self;
			}

			return self::$_instance;
		}

		/**
		 * Constructor.
		 *
		 * @since  1.0.0
		 */
		private function __construct() {

			$this->set_api_url();

			$this->includes();

			add_action( 'admin_notices', array( $this, 'admin_notices' ) );
			add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue' ) );
			add_action( 'elementor/editor/footer', array( $this, 'insert_templates' ) );
			add_action( 'elementor/editor/footer', array( $this, 'register_widget_scripts' ) );
			add_action( 'elementor/editor/wp_head', array( $this, 'add_predefined_variables' ) );
			add_action( 'elementor/editor/before_enqueue_scripts', array( $this, 'popup_styles' ) );
			add_action( 'elementor/preview/enqueue_styles', array( $this, 'popup_styles' ) );

			// AJAX.
			add_action( 'wp_ajax_astra-required-plugins', array( $this, 'required_plugin' ) );
			add_action( 'wp_ajax_astra-required-plugin-activate', array( $this, 'required_plugin_activate' ) );
			add_action( 'wp_ajax_astra-sites-backup-settings', array( $this, 'backup_settings' ) );
			add_action( 'wp_ajax_astra-sites-set-reset-data', array( $this, 'set_reset_data' ) );
			add_action( 'wp_ajax_astra-sites-activate-theme', array( $this, 'activate_theme' ) );
			add_action( 'wp_ajax_astra-sites-create-page', array( $this, 'create_page' ) );
			add_action( 'wp_ajax_astra-sites-create-template', array( $this, 'create_template' ) );
			add_action( 'wp_ajax_astra-sites-getting-started-notice', array( $this, 'getting_started_notice' ) );
			add_action( 'wp_ajax_astra-sites-favorite', array( $this, 'add_to_favorite' ) );
			add_action( 'wp_ajax_astra-sites-elementor-start-batch-process', array( $this, 'start_batch_process' ) );

			add_action( 'wp_ajax_astra-sites-api-request', array( $this, 'api_request' ) );
		}

		/**
		 * API Request
		 *
		 * @since x.x.x
		 */
		public function api_request() {
			$url = isset( $_POST['url'] ) ? $_POST['url'] : '';

			if ( empty( $url ) ) {
				wp_send_json_error( __( 'Provided API URL is empty! Please try again!', 'astra-sites' ) );
			}

			$api_args = array(
				'timeout' => 30,
			);
			$request  = wp_remote_get( trailingslashit( Astra_Sites::get_instance()->get_api_domain() ) . '/wp-json/wp/v2/' . $url, $api_args );
			if ( ! is_wp_error( $request ) && 200 === (int) wp_remote_retrieve_response_code( $request ) ) {

				$demo_data = json_decode( wp_remote_retrieve_body( $request ), true );
				update_option( 'astra_sites_import_data', $demo_data );

				wp_send_json_success( $demo_data );
			} elseif ( is_wp_error( $request ) ) {
				wp_send_json_error( 'API Request is failed due to ' . $request->get_error_message() );
			} elseif ( 200 !== (int) wp_remote_retrieve_response_code( $request ) ) {
				wp_send_json_error( wp_remote_retrieve_body( $request ) );
			}
		}

		/**
		 * Add Predefined Variables
		 *
		 * @since x.x.x
		 */
		public function add_predefined_variables() {

			global $current_screen;
			?>
			<script type="text/javascript">
			addLoadEvent = function(func){if(typeof jQuery!="undefined")jQuery(document).ready(func);else if(typeof wpOnload!='function'){wpOnload=func;}else{var oldonload=wpOnload;wpOnload=function(){oldonload();func();}}};
			var ajaxurl = '<?php echo admin_url( 'admin-ajax.php', 'relative' ); ?>',
				pagenow = '<?php echo $current_screen->id; ?>',
				typenow = '<?php echo $current_screen->post_type; ?>';
			</script>
			<?php
		}

		/**
		 * Insert Template
		 *
		 * @return void
		 */
		function insert_templates() {
			ob_start();
			require_once ASTRA_SITES_DIR . 'inc/includes/templates.php';
			ob_end_flush();
		}

		/**
		 * Add/Remove Favorite.
		 *
		 * @since  x.x.x
		 */
		public function add_to_favorite() {

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( 'You can\'t access this action.' );
			}

			$new_favorites = array();
			$site_id       = isset( $_POST['site_id'] ) ? sanitize_key( $_POST['site_id'] ) : '';

			if ( empty( $site_id ) ) {
				wp_send_json_error();
			}

			$favorite_settings = get_option( 'astra-sites-favorites', array() );

			if ( false !== $favorite_settings && is_array( $favorite_settings ) ) {
				$new_favorites = $favorite_settings;
			}

			if ( 'false' === $_POST['is_favorite'] ) {
				if ( in_array( $site_id, $new_favorites, true ) ) {
					$key = array_search( $site_id, $new_favorites, true );
					unset( $new_favorites[ $key ] );
				}
			} else {
				if ( ! in_array( $site_id, $new_favorites, true ) ) {
					array_push( $new_favorites, $site_id );
				}
			}

			update_option( 'astra-sites-favorites', $new_favorites );

			wp_send_json_success(
				array(
					'all_favorites' => $new_favorites,
				)
			);
		}

		/**
		 * Start Batch Process after Elementor Page Import.
		 *
		 * @since  x.x.x
		 */
		public function start_batch_process() {

			$post_id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

			$post = $_POST['post'];

			if ( isset( $post['astra-page-options-data'] ) && isset( $post['astra-page-options-data']['elementor_load_fa4_shim'] ) ) {
				update_option( 'elementor_load_fa4_shim', $post['astra-page-options-data']['elementor_load_fa4_shim'] );
			}

			if ( 0 === $post_id ) {
				wp_send_json_success(
					array(
						'success' => false,
					)
				);
				exit;
			}

			do_action( 'astra_sites_process_single', $post_id );

			wp_send_json_success(
				array(
					'success' => true,
				)
			);
			exit;
		}

		/**
		 * Import Template.
		 *
		 * @since  x.x.x
		 */
		public function create_template() {

			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			$content = isset( $_POST['data']['content']['rendered'] ) ? $_POST['data']['content']['rendered'] : '';

			$data = isset( $_POST['data'] ) ? $_POST['data'] : array();

			if ( empty( $data ) ) {
				wp_send_json_error( 'Empty page data.' );
			}

			$page_id = isset( $_POST['data']['id'] ) ? $_POST['data']['id'] : '';

			$title = '';
			if ( isset( $_POST['data']['title']['rendered'] ) ) {
				if ( '' !== $_POST['title'] ) {
					$title = $_POST['title'] . ' - ' . $_POST['data']['title']['rendered'];
				} else {
					$title = $_POST['data']['title']['rendered'];
				}
			}

			$excerpt = isset( $_POST['data']['excerpt']['rendered'] ) ? $_POST['data']['excerpt']['rendered'] : '';

			$post_args = array(
				'post_type'    => 'elementor_library',
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_content' => $content,
				'post_excerpt' => $excerpt,
			);

			$new_page_id = wp_insert_post( $post_args );
			$post_meta   = isset( $_POST['data']['post-meta'] ) ? $_POST['data']['post-meta'] : array();

			if ( ! empty( $post_meta ) ) {
				$this->import_template_meta( $new_page_id, $post_meta );
			}

			if ( 'pages' === $_POST['type'] ) {
				update_post_meta( $new_page_id, '_elementor_template_type', 'page' );
				wp_set_object_terms( $new_page_id, 'page', 'elementor_library_type' );
			} else {
				update_post_meta( $new_page_id, '_elementor_template_type', 'section' );
				wp_set_object_terms( $new_page_id, 'section', 'elementor_library_type' );
			}

			do_action( 'astra_sites_process_single', $new_page_id );

			wp_send_json_success(
				array(
					'remove-page-id' => $page_id,
					'id'             => $new_page_id,
					'link'           => get_permalink( $new_page_id ),
				)
			);
		}

		/**
		 * Import Page.
		 *
		 * @since  x.x.x
		 */
		public function create_page() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			$default_page_builder = Astra_Sites_Page::get_instance()->get_setting( 'page_builder' );

			if ( 'gutenberg' === $default_page_builder ) {
				$content = isset( $_POST['data']['original_content'] ) ? $_POST['data']['original_content'] : '';
			} else {
				$content = isset( $_POST['data']['content']['rendered'] ) ? $_POST['data']['content']['rendered'] : '';
			}

			if ( 'elementor' === $default_page_builder ) {
				if ( isset( $_POST['data']['astra-page-options-data'] ) && isset( $_POST['data']['astra-page-options-data']['elementor_load_fa4_shim'] ) ) {
					update_option( 'elementor_load_fa4_shim', $_POST['data']['astra-page-options-data']['elementor_load_fa4_shim'] );
				}
			}

			$data = isset( $_POST['data'] ) ? $_POST['data'] : array();

			if ( empty( $data ) ) {
				wp_send_json_error( 'Empty page data.' );
			}

			$page_id = isset( $_POST['data']['id'] ) ? $_POST['data']['id'] : '';
			$title   = isset( $_POST['data']['title']['rendered'] ) ? $_POST['data']['title']['rendered'] : '';
			$excerpt = isset( $_POST['data']['excerpt']['rendered'] ) ? $_POST['data']['excerpt']['rendered'] : '';

			$post_args = array(
				'post_type'    => 'page',
				'post_status'  => 'draft',
				'post_title'   => $title,
				'post_content' => $content,
				'post_excerpt' => $excerpt,
			);

			$new_page_id = wp_insert_post( $post_args );
			$post_meta   = isset( $_POST['data']['post-meta'] ) ? $_POST['data']['post-meta'] : array();

			if ( ! empty( $post_meta ) ) {
				$this->import_post_meta( $new_page_id, $post_meta );
			}

			if ( isset( $_POST['data']['astra-page-options-data'] ) && ! empty( $_POST['data']['astra-page-options-data'] ) ) {

				foreach ( $_POST['data']['astra-page-options-data'] as $option => $value ) {
					update_option( $option, $value );
				}
			}

			do_action( 'astra_sites_process_single', $new_page_id );

			wp_send_json_success(
				array(
					'remove-page-id' => $page_id,
					'id'             => $new_page_id,
					'link'           => get_permalink( $new_page_id ),
				)
			);
		}

		/**
		 * Import Post Meta
		 *
		 * @since 2.0.0
		 *
		 * @param  integer $post_id  Post ID.
		 * @param  array   $metadata  Post meta.
		 * @return void
		 */
		public function import_post_meta( $post_id, $metadata ) {

			$metadata = (array) $metadata;

			$default_page_builder = Astra_Sites_Page::get_instance()->get_setting( 'page_builder' );

			if ( 'gutenberg' === $default_page_builder ) {
				return;
			}

			foreach ( $metadata as $meta_key => $meta_value ) {

				if ( $meta_value ) {

					if ( '_elementor_data' === $meta_key ) {

						$raw_data = json_decode( stripslashes( $meta_value ), true );

						if ( is_array( $raw_data ) ) {
							$raw_data = wp_slash( json_encode( $raw_data ) );
						} else {
							$raw_data = wp_slash( $raw_data );
						}
					} else {

						if ( is_serialized( $meta_value, true ) ) {
							$raw_data = maybe_unserialize( stripslashes( $meta_value ) );
						} elseif ( is_array( $meta_value ) ) {
							$raw_data = json_decode( stripslashes( $meta_value ), true );
						} else {
							$raw_data = $meta_value;
						}
					}

					if ( '_elementor_page_settings' === $meta_key ) {

						if ( is_array( $raw_data ) ) {

							if ( isset( $raw_data['astra_sites_page_setting_enable'] ) ) {
								$raw_data['astra_sites_page_setting_enable'] = 'yes';
							}

							if ( isset( $raw_data['astra_sites_body_font_family'] ) ) {
								$raw_data['astra_sites_body_font_family'] = str_replace( "'", '', $raw_data['astra_sites_body_font_family'] );
							}

							for ( $i = 1; $i < 7; $i++ ) {

								if ( isset( $raw_data[ 'astra_sites_heading_' . $i . '_font_family' ] ) ) {

									$raw_data[ 'astra_sites_heading_' . $i . '_font_family' ] = str_replace( "'", '', $raw_data[ 'astra_sites_heading_' . $i . '_font_family' ] );
								}
							}
						}
					}

					update_post_meta( $post_id, $meta_key, $raw_data );
				}
			}
		}

		/**
		 * Import Post Meta
		 *
		 * @since x.x.x
		 *
		 * @param  integer $post_id  Post ID.
		 * @param  array   $metadata  Post meta.
		 * @return void
		 */
		public function import_template_meta( $post_id, $metadata ) {

			$metadata = (array) $metadata;

			foreach ( $metadata as $meta_key => $meta_value ) {

				if ( $meta_value ) {

					if ( '_elementor_data' === $meta_key ) {

						$raw_data = json_decode( stripslashes( $meta_value ), true );

						if ( is_array( $raw_data ) ) {
							$raw_data = wp_slash( json_encode( $raw_data ) );
						} else {
							$raw_data = wp_slash( $raw_data );
						}
					} else {

						if ( is_serialized( $meta_value, true ) ) {
							$raw_data = maybe_unserialize( stripslashes( $meta_value ) );
						} elseif ( is_array( $meta_value ) ) {
							$raw_data = json_decode( stripslashes( $meta_value ), true );
						} else {
							$raw_data = $meta_value;
						}
					}

					if ( '_elementor_page_settings' === $meta_key ) {

						if ( is_array( $raw_data ) ) {

							if ( isset( $raw_data['astra_sites_page_setting_enable'] ) ) {
								$raw_data['astra_sites_page_setting_enable'] = 'yes';
							}

							if ( isset( $raw_data['astra_sites_body_font_family'] ) ) {
								$raw_data['astra_sites_body_font_family'] = str_replace( "'", '', $raw_data['astra_sites_body_font_family'] );
							}

							for ( $i = 1; $i < 7; $i++ ) {

								if ( isset( $raw_data[ 'astra_sites_heading_' . $i . '_font_family' ] ) ) {

									$raw_data[ 'astra_sites_heading_' . $i . '_font_family' ] = str_replace( "'", '', $raw_data[ 'astra_sites_heading_' . $i . '_font_family' ] );
								}
							}
						}
					}

					update_post_meta( $post_id, $meta_key, $raw_data );
				}
			}
		}

		/**
		 * Close getting started notice for current user
		 *
		 * @since 1.3.5
		 * @return void
		 */
		function getting_started_notice() {
			update_user_meta( get_current_user_id(), '_astra_sites_gettings_started', true );
			wp_send_json_success();
		}

		/**
		 * Activate theme
		 *
		 * @since 1.3.2
		 * @return void
		 */
		function activate_theme() {

			switch_theme( 'astra' );

			wp_send_json_success(
				array(
					'success' => true,
					'message' => __( 'Theme Activated', 'astra-sites' ),
				)
			);
		}

		/**
		 * Set reset data
		 */
		function set_reset_data() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			global $wpdb;

			$post_ids = $wpdb->get_col( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_astra_sites_imported_post'" );
			$form_ids = $wpdb->get_col( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_astra_sites_imported_wp_forms'" );
			$term_ids = $wpdb->get_col( "SELECT term_id FROM {$wpdb->termmeta} WHERE meta_key='_astra_sites_imported_term'" );

			wp_send_json_success(
				array(
					'reset_posts'    => $post_ids,
					'reset_wp_forms' => $form_ids,
					'reset_terms'    => $term_ids,
				)
			);
		}

		/**
		 * Backup our existing settings.
		 */
		function backup_settings() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			$file_name    = 'astra-sites-backup-' . date( 'd-M-Y-h-i-s' ) . '.json';
			$old_settings = get_option( 'astra-settings', array() );
			$upload_dir   = Astra_Sites_Importer_Log::get_instance()->log_dir();
			$upload_path  = trailingslashit( $upload_dir['path'] );
			$log_file     = $upload_path . $file_name;
			$file_system  = Astra_Sites_Importer_Log::get_instance()->get_filesystem();

			// If file system fails? Then take a backup in site option.
			if ( false === $file_system->put_contents( $log_file, json_encode( $old_settings ), FS_CHMOD_FILE ) ) {
				update_option( 'astra_sites_' . $file_name, $old_settings );
			}

			wp_send_json_success();
		}

		/**
		 * Get theme install, active or inactive status.
		 *
		 * @since 1.3.2
		 *
		 * @return string Theme status
		 */
		function get_theme_status() {

			$theme = wp_get_theme();

			// Theme installed and activate.
			if ( 'Astra' === $theme->name || 'Astra' === $theme->parent_theme ) {
				return 'installed-and-active';
			}

			// Theme installed but not activate.
			foreach ( (array) wp_get_themes() as $theme_dir => $theme ) {
				if ( 'Astra' === $theme->name || 'Astra' === $theme->parent_theme ) {
					return 'installed-but-inactive';
				}
			}

			return 'not-installed';
		}

		/**
		 * Loads textdomain for the plugin.
		 *
		 * @since 1.0.1
		 */
		function load_textdomain() {
			load_plugin_textdomain( 'astra-sites' );
		}

		/**
		 * Admin Notices
		 *
		 * @since 1.0.5
		 * @return void
		 */
		function admin_notices() {

			if ( ! defined( 'ASTRA_THEME_SETTINGS' ) ) {
				return;
			}

			add_action( 'plugin_action_links_' . ASTRA_SITES_BASE, array( $this, 'action_links' ) );
		}

		/**
		 * Show action links on the plugin screen.
		 *
		 * @param   mixed $links Plugin Action links.
		 * @return  array
		 */
		function action_links( $links ) {
			$action_links = array(
				'settings' => '<a href="' . admin_url( 'themes.php?page=astra-sites' ) . '" aria-label="' . esc_attr__( 'See Library', 'astra-sites' ) . '">' . esc_html__( 'See Library', 'astra-sites' ) . '</a>',
			);

			return array_merge( $action_links, $links );
		}

		/**
		 * Get the API URL.
		 *
		 * @since  1.0.0
		 */
		public static function get_api_domain() {
			return apply_filters( 'astra_sites_api_domain', 'https://websitedemos.net/' );
		}

		/**
		 * Setter for $api_url
		 *
		 * @since  1.0.0
		 */
		public function set_api_url() {
			$this->api_url = apply_filters( 'astra_sites_api_url', trailingslashit( self::get_api_domain() ) . '/wp-json/wp/v2/' );
		}

		/**
		 * Enqueue admin scripts.
		 *
		 * @since  1.3.2    Added 'install-theme.js' to install and activate theme.
		 * @since  1.0.5    Added 'getUpgradeText' and 'getUpgradeURL' localize variables.
		 *
		 * @since  1.0.0
		 *
		 * @param  string $hook Current hook name.
		 * @return void
		 */
		public function admin_enqueue( $hook = '' ) {

			wp_enqueue_script( 'astra-sites-install-theme', ASTRA_SITES_URI . 'inc/assets/js/install-theme.js', array( 'jquery', 'updates' ), ASTRA_SITES_VER, true );
			wp_enqueue_style( 'astra-sites-install-theme', ASTRA_SITES_URI . 'inc/assets/css/install-theme.css', null, ASTRA_SITES_VER, 'all' );

			$data = apply_filters(
				'astra_sites_install_theme_localize_vars',
				array(
					'installed'  => __( 'Installed! Activating..', 'astra-sites' ),
					'activating' => __( 'Activating..', 'astra-sites' ),
					'activated'  => __( 'Activated!', 'astra-sites' ),
					'installing' => __( 'Installing..', 'astra-sites' ),
					'ajaxurl'    => esc_url( admin_url( 'admin-ajax.php' ) ),
				)
			);
			wp_localize_script( 'astra-sites-install-theme', 'AstraSitesInstallThemeVars', $data );

			if ( 'appearance_page_astra-sites' !== $hook ) {
				return;
			}

			global $is_IE, $is_edge;

			if ( $is_IE || $is_edge ) {
				wp_enqueue_script( 'astra-sites-eventsource', ASTRA_SITES_URI . 'inc/assets/js/eventsource.min.js', array( 'jquery', 'wp-util', 'updates' ), ASTRA_SITES_VER, true );
			}

			// Fetch.
			wp_register_script( 'astra-sites-fetch', ASTRA_SITES_URI . 'inc/assets/js/fetch.umd.js', array( 'jquery' ), ASTRA_SITES_VER, true );

			// History.
			wp_register_script( 'astra-sites-history', ASTRA_SITES_URI . 'inc/assets/js/history.js', array( 'jquery' ), ASTRA_SITES_VER, true );

			// API.
			wp_register_script( 'astra-sites-api', ASTRA_SITES_URI . 'inc/assets/js/astra-sites-api.js', array( 'jquery', 'astra-sites-fetch' ), ASTRA_SITES_VER, true );

			// Admin Page.
			wp_enqueue_style( 'astra-sites-admin', ASTRA_SITES_URI . 'inc/assets/css/admin.css', ASTRA_SITES_VER, true );
			wp_enqueue_script( 'astra-sites-admin-page', ASTRA_SITES_URI . 'inc/assets/js/admin-page.js', array( 'jquery', 'wp-util', 'updates', 'jquery-ui-autocomplete', 'astra-sites-api', 'astra-sites-history' ), ASTRA_SITES_VER, true );

			$data = $this->get_local_vars();

			wp_localize_script( 'astra-sites-admin-page', 'astraSitesVars', $data );
		}

		/**
		 * Returns Localization Variables.
		 *
		 * @since x.x.x
		 */
		public function get_local_vars() {

			$stored_data = array(
				'astra-site-category'        => array(),
				'astra-site-page-builder'    => array(),
				'astra-sites'                => array(),
				'site-pages-category'        => array(),
				'site-pages-page-builder'    => array(),
				'site-pages-parent-category' => array(),
				'site-pages'                 => array(),
				'favorites'                  => get_option( 'astra-sites-favorites' ),
			);

			$favorite_data = get_option( 'astra-sites-favorites' );

			// Use this for premium demos.
			$request_params = apply_filters(
				'astra_sites_api_params',
				array(
					'purchase_key' => '',
					'site_url'     => '',
					'per-page'     => 15,
				)
			);

			$license_status = false;
			if ( is_callable( 'BSF_License_Manager::bsf_is_active_license' ) ) {
				$license_status = BSF_License_Manager::bsf_is_active_license( 'astra-pro-sites' );
			}

			$default_page_builder = Astra_Sites_Page::get_instance()->get_setting( 'page_builder' );

			$data = apply_filters(
				'astra_sites_localize_vars',
				array(
					'debug'                      => ( ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || isset( $_GET['debug'] ) ) ? true : false,
					'isPro'                      => defined( 'ASTRA_PRO_SITES_NAME' ) ? true : false,
					'isWhiteLabeled'             => Astra_Sites_White_Label::get_instance()->is_white_labeled(),
					'ajaxurl'                    => esc_url( admin_url( 'admin-ajax.php' ) ),
					'siteURL'                    => site_url(),
					'docUrl'                     => 'https://wpastra.com/',
					'getProText'                 => __( 'Get Agency Bundle', 'astra-sites' ),
					'getProURL'                  => esc_url( 'https://wpastra.com/agency/?utm_source=demo-import-panel&utm_campaign=astra-sites&utm_medium=wp-dashboard' ),
					'getUpgradeText'             => __( 'Upgrade', 'astra-sites' ),
					'getUpgradeURL'              => esc_url( 'https://wpastra.com/agency/?utm_source=demo-import-panel&utm_campaign=astra-sites&utm_medium=wp-dashboard' ),
					'_ajax_nonce'                => wp_create_nonce( 'astra-sites' ),
					'requiredPlugins'            => array(),
					'XMLReaderDisabled'          => ! class_exists( 'XMLReader' ) ? true : false,
					'strings'                    => array(
						/* translators: %s are HTML tags. */
						'warningXMLReader'         => sprintf( __( '%1$sRequired XMLReader PHP extension is missing on your server!%2$sAstra Sites import requires XMLReader extension to be installed. Please contact your web hosting provider and ask them to install and activate the XMLReader PHP extension.', 'astra-sites' ), '<div class="notice astra-sites-xml-notice notice-error"><p><b>', '</b></p><p>', '</p></div>' ),
						'warningBeforeCloseWindow' => __( 'Warning! Astra Site Import process is not complete. Don\'t close the window until import process complete. Do you still want to leave the window?', 'astra-sites' ),
						'importFailedBtnSmall'     => __( 'Error!', 'astra-sites' ),
						'importFailedBtnLarge'     => __( 'Error! Read Possibilities.', 'astra-sites' ),
						'viewSite'                 => __( 'Done! View Site', 'astra-sites' ),
						'importFailBtn'            => __( 'Import failed.', 'astra-sites' ),
						'importFailBtnLarge'       => __( 'Import failed. See error log.', 'astra-sites' ),
						'importDemo'               => __( 'Import This Site', 'astra-sites' ),
						'importingDemo'            => __( 'Importing..', 'astra-sites' ),
					),
					'log'                        => array(
						'bulkInstall'          => __( 'Installing Required Plugins..', 'astra-sites' ),
						'serverConfiguration'  => esc_url( 'https://wpastra.com/docs/?p=1314&utm_source=demo-import-panel&utm_campaign=import-error&utm_medium=wp-dashboard' ),
						'importWidgetsSuccess' => __( 'Imported Widgets!', 'astra-sites' ),
					),
					'default_page_builder'       => $default_page_builder,
					'default_page_builder_sites' => Astra_Sites_Page::get_instance()->get_sites_by_page_builder( $default_page_builder ),
					'sites'                      => $request_params,
					'settings'                   => array(),
					'page-builders'              => array(),
					'categories'                 => array(),
					'parent_categories'          => array(),
					'default_page_builder'       => $default_page_builder,
					'api_sites_and_pages'        => (array) $this->get_all_sites(),
					'api_sites_and_pages_tags'   => get_option( 'astra-sites-tags', array() ),
					'license_status'             => $license_status,

					'ApiURL'                     => $this->api_url,
					'stored_data'                => $stored_data,
					'favorite_data'              => $favorite_data,
					'category_slug'              => 'astra-site-category',
					'page_builder'               => 'astra-site-page-builder',
					'cpt_slug'                   => 'astra-sites',
					'parent_category'            => '',
				)
			);

			return $data;
		}

		/**
		 * Register module required js on elementor's action.
		 *
		 * @since x.x.x
		 */
		function register_widget_scripts() {

			wp_enqueue_script( 'astra-sites-helper', ASTRA_SITES_URI . 'inc/assets/js/helper.js', array( 'jquery' ), ASTRA_SITES_VER, true );

			wp_enqueue_script( 'masonry' );
			wp_enqueue_script( 'imagesloaded' );

			wp_enqueue_script( 'astra-sites-elementor-admin-page', ASTRA_SITES_URI . 'inc/assets/js/elementor-admin-page.js', array( 'jquery', 'astra-sites-helper', 'wp-util', 'updates', 'masonry', 'imagesloaded' ), ASTRA_SITES_VER, true );

			wp_enqueue_style( 'astra-sites-admin', ASTRA_SITES_URI . 'inc/assets/css/admin.css', ASTRA_SITES_VER, true );

			// Use this for premium demos.
			$request_params = apply_filters(
				'astra_sites_api_params',
				array(
					'purchase_key' => '',
					'site_url'     => '',
					'per-page'     => 15,
				)
			);

			$license_status = false;
			if ( is_callable( 'BSF_License_Manager::bsf_is_active_license' ) ) {
				$license_status = BSF_License_Manager::bsf_is_active_license( 'astra-pro-sites' );
			}

			/* translators: %s are link. */
			$license_msg = sprintf( __( 'This is a premium website demo available only with the Agency Bundles you can purchase it from <a href="%s" target="_blank">here</a>.', 'astra-sites' ), 'https://wpastra.com/pricing/' );

			if ( defined( 'ASTRA_PRO_SITES_NAME' ) ) {
				/* translators: %s are link. */
				$license_msg = sprintf( __( 'This is a premium template available with Astra \'Agency\' packages. <a href="%s" target="_blank">Validate Your License</a> Key to import this template.', 'astra-sites' ), esc_url( admin_url( 'plugins.php?bsf-inline-license-form=astra-pro-sites' ) ) );
			}

			$data = apply_filters(
				'astra_sites_render_localize_vars',
				array(
					'sites'                      => $request_params,
					'settings'                   => array(),
					'page-builders'              => array(),
					'categories'                 => array(),
					'parent_categories'          => array(),
					'default_page_builder'       => 'elementor',
					'api_sites_and_pages'        => $this->get_all_sites(),
					'astra_blocks'               => $this->get_all_blocks(),
					'license_status'             => $license_status,
					'ajaxurl'                    => esc_url( admin_url( 'admin-ajax.php' ) ),
					'api_sites_and_pages_tags'   => get_option( 'astra-sites-tags', array() ),
					'default_page_builder_sites' => Astra_Sites_Page::get_instance()->get_sites_by_page_builder( 'elementor' ),
					'ApiURL'                     => $this->api_url,
					'_ajax_nonce'                => wp_create_nonce( 'astra-sites' ),
					'isPro'                      => defined( 'ASTRA_PRO_SITES_NAME' ) ? true : false,
					'license_msg'                => $license_msg,
					'isWhiteLabeled'             => Astra_Sites_White_Label::get_instance()->is_white_labeled(),
					'getProText'                 => __( 'Get Agency Bundle', 'astra-sites' ),
					'getProURL'                  => esc_url( 'https://wpastra.com/agency/?utm_source=demo-import-panel&utm_campaign=astra-sites&utm_medium=wp-dashboard' ),
				)
			);

			wp_localize_script( 'astra-sites-elementor-admin-page', 'astraElementorSites', $data );
		}

		/**
		 * Register module required js on elementor's action.
		 *
		 * @since x.x.x
		 */
		function popup_styles() {

			wp_enqueue_style( 'astra-sites-elementor-admin-page', ASTRA_SITES_URI . 'inc/assets/css/elementor-admin.css', ASTRA_SITES_VER, true );

		}

		/**
		 * Get all sites
		 *
		 * @since 2.0.0
		 * @return array All sites.
		 */
		function get_all_sites() {
			$sites_and_pages = array();
			$total_requests  = (int) get_option( 'astra-sites-requests', 0 );
			for ( $page = 1; $page <= $total_requests; $page++ ) {
				$current_page_data = get_option( 'astra-sites-and-pages-page-' . $page, array() );
				if ( ! empty( $current_page_data ) ) {
					foreach ( $current_page_data as $page_id => $page_data ) {
						$sites_and_pages[ $page_id ] = $page_data;
					}
				}
			}

			return $sites_and_pages;
		}

		/**
		 * Get all blocks
		 *
		 * @since x.x.x
		 * @return array All Elementor Blocks.
		 */
		function get_all_blocks() {
			$blocks = array();
			// $total_requests  = (int) get_option( 'astra-sites-requests', 0 );
			for ( $page = 1; $page <= 2; $page++ ) {
				$current_page_data = get_option( 'astra-blocks-' . $page, array() );
				if ( ! empty( $current_page_data ) ) {
					foreach ( $current_page_data as $page_id => $page_data ) {
						$blocks[ $page_id ] = $page_data;
					}
				}
			}

			return $blocks;
		}

		/**
		 * Load all the required files in the importer.
		 *
		 * @since  1.0.0
		 */
		private function includes() {

			require_once ASTRA_SITES_DIR . 'inc/lib/astra-notices/class-astra-notices.php';
			require_once ASTRA_SITES_DIR . 'inc/classes/class-astra-sites-white-label.php';
			require_once ASTRA_SITES_DIR . 'inc/classes/class-astra-sites-page.php';
			require_once ASTRA_SITES_DIR . 'inc/classes/compatibility/class-astra-sites-compatibility.php';
			require_once ASTRA_SITES_DIR . 'inc/classes/class-astra-sites-importer.php';
		}

		/**
		 * Required Plugin Activate
		 *
		 * @since 1.0.0
		 */
		public function required_plugin_activate() {

			if ( ! current_user_can( 'install_plugins' ) || ! isset( $_POST['init'] ) || ! $_POST['init'] ) {
				wp_send_json_error(
					array(
						'success' => false,
						'message' => __( 'No plugin specified', 'astra-sites' ),
					)
				);
			}

			$data               = array();
			$plugin_init        = ( isset( $_POST['init'] ) ) ? esc_attr( $_POST['init'] ) : '';
			$astra_site_options = ( isset( $_POST['options'] ) ) ? json_decode( stripslashes( $_POST['options'] ) ) : '';
			$enabled_extensions = ( isset( $_POST['enabledExtensions'] ) ) ? json_decode( stripslashes( $_POST['enabledExtensions'] ) ) : '';

			$data['astra_site_options'] = $astra_site_options;
			$data['enabled_extensions'] = $enabled_extensions;

			$activate = activate_plugin( $plugin_init, '', false, true );

			if ( is_wp_error( $activate ) ) {
				wp_send_json_error(
					array(
						'success' => false,
						'message' => $activate->get_error_message(),
					)
				);
			}

			do_action( 'astra_sites_after_plugin_activation', $plugin_init, $data );

			wp_send_json_success(
				array(
					'success' => true,
					'message' => __( 'Plugin Activated', 'astra-sites' ),
				)
			);

		}

		/**
		 * Required Plugins
		 *
		 * @since x.x.x
		 *
		 * @return void
		 */
		public function required_plugin() {

			// Verify Nonce.
			check_ajax_referer( 'astra-sites', '_ajax_nonce' );

			$response = array(
				'active'       => array(),
				'inactive'     => array(),
				'notinstalled' => array(),
			);

			if ( ! current_user_can( 'customize' ) ) {
				wp_send_json_error( $response );
			}

			$required_plugins             = ( isset( $_POST['required_plugins'] ) ) ? $_POST['required_plugins'] : array();
			$third_party_required_plugins = array();
			$third_party_plugins          = array(
				'learndash-course-grid' => array(
					'init' => 'learndash-course-grid/learndash_course_grid.php',
					'name' => 'LearnDash Course Grid',
					'link' => 'https://www.learndash.com/add-on/course-grid/',
				),
				'sfwd-lms'              => array(
					'init' => 'sfwd-lms/sfwd_lms.php',
					'name' => 'LearnDash LMS',
					'link' => 'https://www.learndash.com/',
				),
				'learndash-woocommerce' => array(
					'init' => 'learndash-woocommerce/learndash_woocommerce.php',
					'name' => 'LearnDash WooCommerce Integration',
					'link' => 'https://www.learndash.com/add-on/woocommerce/',
				),
			);

			if ( count( $required_plugins ) > 0 ) {
				foreach ( $required_plugins as $key => $plugin ) {

					/**
					 * Has Pro Version Support?
					 * And
					 * Is Pro Version Installed?
					 */
					$plugin_pro = $this->pro_plugin_exist( $plugin['init'] );
					if ( $plugin_pro ) {

						// Pro - Active.
						if ( is_plugin_active( $plugin_pro['init'] ) ) {
							$response['active'][] = $plugin_pro;

							// Pro - Inactive.
						} else {
							$response['inactive'][] = $plugin_pro;
						}
					} else {

						// Lite - Installed but Inactive.
						if ( file_exists( WP_PLUGIN_DIR . '/' . $plugin['init'] ) && is_plugin_inactive( $plugin['init'] ) ) {

							$response['inactive'][] = $plugin;

							// Lite - Not Installed.
						} elseif ( ! file_exists( WP_PLUGIN_DIR . '/' . $plugin['init'] ) ) {

							$response['notinstalled'][] = $plugin;

							// Added premium plugins which need to install first.
							if ( array_key_exists( $plugin['slug'], $third_party_plugins ) ) {
								$third_party_required_plugins[] = $third_party_plugins[ $plugin['slug'] ];
							}

							// Lite - Active.
						} else {
							$response['active'][] = $plugin;
						}
					}
				}
			}

			// Send response.
			wp_send_json_success(
				array(
					'required_plugins'             => $response,
					'third_party_required_plugins' => $third_party_required_plugins,
				)
			);
		}

		/**
		 * Has Pro Version Support?
		 * And
		 * Is Pro Version Installed?
		 *
		 * Check Pro plugin version exist of requested plugin lite version.
		 *
		 * Eg. If plugin 'BB Lite Version' required to import demo. Then we check the 'BB Agency Version' is exist?
		 * If yes then we only 'Activate' Agency Version. [We couldn't install agency version.]
		 * Else we 'Activate' or 'Install' Lite Version.
		 *
		 * @since 1.0.1
		 *
		 * @param  string $lite_version Lite version init file.
		 * @return mixed               Return false if not installed or not supported by us
		 *                                    else return 'Pro' version details.
		 */
		public function pro_plugin_exist( $lite_version = '' ) {

			// Lite init => Pro init.
			$plugins = apply_filters(
				'astra_sites_pro_plugin_exist',
				array(
					'beaver-builder-lite-version/fl-builder.php' => array(
						'slug' => 'bb-plugin',
						'init' => 'bb-plugin/fl-builder.php',
						'name' => 'Beaver Builder Plugin',
					),
					'ultimate-addons-for-beaver-builder-lite/bb-ultimate-addon.php' => array(
						'slug' => 'bb-ultimate-addon',
						'init' => 'bb-ultimate-addon/bb-ultimate-addon.php',
						'name' => 'Ultimate Addon for Beaver Builder',
					),
					'wpforms-lite/wpforms.php' => array(
						'slug' => 'wpforms',
						'init' => 'wpforms/wpforms.php',
						'name' => 'WPForms',
					),
				),
				$lite_version
			);

			if ( isset( $plugins[ $lite_version ] ) ) {

				// Pro plugin directory exist?
				if ( file_exists( WP_PLUGIN_DIR . '/' . $plugins[ $lite_version ]['init'] ) ) {
					return $plugins[ $lite_version ];
				}
			}

			return false;
		}

		/**
		 * Get Default Page Builders
		 *
		 * @since x.x.x
		 * @return array
		 */
		function get_default_page_builders() {
			return array(
				array(
					'id'   => 33,
					'slug' => 'elementor',
					'name' => 'Elementor',
				),
				array(
					'id'   => 34,
					'slug' => 'beaver-builder',
					'name' => 'Beaver Builder',
				),
				array(
					'id'   => 41,
					'slug' => 'brizy',
					'name' => 'Brizy',
				),
				array(
					'id'   => 42,
					'slug' => 'gutenberg',
					'name' => 'Gutenberg',
				),
			);
		}

		/**
		 * Get Page Builders
		 *
		 * @since x.x.x
		 * @return array
		 */
		function get_page_builders() {

			$stored_page_builders = get_option( 'astra-sites-page-builders', array() );

			if ( ! empty( $stored_page_builders ) ) {
				return $stored_page_builders;
			}

			return $this->get_default_page_builders();
		}

		/**
		 * Get License Key
		 *
		 * @since x.x.x
		 * @return array
		 */
		function get_license_key() {

			if ( class_exists( 'BSF_License_Manager' ) ) {
				if ( BSF_License_Manager::bsf_is_active_license( 'astra-pro-sites' ) ) {
					return BSF_License_Manager::instance()->bsf_get_product_info( 'astra-pro-sites', 'purchase_key' );
				}
			}

			return '';
		}

	}

	/**
	 * Kicking this off by calling 'get_instance()' method
	 */
	Astra_Sites::get_instance();

endif;
