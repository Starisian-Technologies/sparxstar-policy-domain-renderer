<?php
/**
 * Central plugin controller.
 *
 * @package Starisian\Sparxstar\PolicyRenderer
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\PolicyRenderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Starisian\Sparxstar\PolicyRenderer\Admin\PlaceholderReferencePage;
use Starisian\Sparxstar\PolicyRenderer\Admin\ProfileAdmin;
use Starisian\Sparxstar\PolicyRenderer\Cache\RenderCache;
use Starisian\Sparxstar\PolicyRenderer\Content\PlaceholderRegistry;
use Starisian\Sparxstar\PolicyRenderer\Content\PlaceholderRenderer;
use Starisian\Sparxstar\PolicyRenderer\Content\PolicyContentFilter;
use Starisian\Sparxstar\PolicyRenderer\Content\PolicyResolver;
use Starisian\Sparxstar\PolicyRenderer\Domain\HostNormalizer;
use Starisian\Sparxstar\PolicyRenderer\Domain\ProfileResolver;
use Starisian\Sparxstar\PolicyRenderer\PostTypes\PolicyProfilePostType;
use Starisian\Sparxstar\PolicyRenderer\Taxonomies\PolicyKeyTaxonomy;

/**
 * Singleton plugin controller.
 *
 * Responsibilities:
 *  - Boot the plugin on `plugins_loaded`.
 *  - Wire all services and register WordPress hooks.
 *  - Provide activation / deactivation callbacks.
 *  - Register plugin settings.
 */
final class Plugin {

	private static ?self $instance = null;

	private function __construct() {}

	/**
	 * Returns the singleton instance.
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Boots the plugin — registers all CPTs, taxonomies, admin screens, and content filters.
	 */
	public function boot(): void {
		$this->register_post_types();
		$this->register_taxonomies();
		$this->register_content_filters();
		$this->register_admin_hooks();
		$this->load_textdomain();
	}

	/**
	 * Activation callback — registers types and flushes rewrite rules.
	 */
	public function activate(): void {
		( new PolicyProfilePostType() )->register();
		( new PolicyKeyTaxonomy() )->register();
		flush_rewrite_rules();
	}

	/**
	 * Deactivation callback.
	 */
	public function deactivate(): void {
		flush_rewrite_rules();
	}

	// -------------------------------------------------------------------------
	// Private: registration methods
	// -------------------------------------------------------------------------

	private function register_post_types(): void {
		$cpt = new PolicyProfilePostType();
		add_action( 'init', [ $cpt, 'register' ] );
	}

	private function register_taxonomies(): void {
		$tax = new PolicyKeyTaxonomy();
		add_action( 'init', [ $tax, 'register' ] );
	}

	private function register_content_filters(): void {
		$host_normalizer      = new HostNormalizer();
		$profile_resolver     = new ProfileResolver( $host_normalizer );
		$placeholder_registry = new PlaceholderRegistry();
		$placeholder_renderer = new PlaceholderRenderer( $placeholder_registry );
		$policy_resolver      = new PolicyResolver();
		$render_cache         = new RenderCache();

		$content_filter = new PolicyContentFilter(
			$host_normalizer,
			$profile_resolver,
			$placeholder_renderer,
			$policy_resolver,
			$render_cache
		);

		// Content replacement filters.
		add_filter( 'the_content',            [ $content_filter, 'filter_content' ],        20 );
		add_filter( 'the_excerpt',            [ $content_filter, 'filter_excerpt' ],        20 );
		add_filter( 'wp_title',               [ $content_filter, 'filter_title' ],          20 );
		add_filter( 'pre_get_document_title', [ $content_filter, 'filter_document_title' ], 20 );

		// 404 for unknown hosts and inactive profiles.
		add_action( 'template_redirect', [ $content_filter, 'handle_unknown_host' ] );

		// Robots meta tag.
		add_action( 'wp_head', [ $content_filter, 'output_robots_meta' ] );

		// Cache-Control response headers.
		add_action( 'template_redirect', [ $content_filter, 'send_cache_headers' ] );

		// Cache invalidation.
		add_action( 'save_post_spx_policy_profile', [ $render_cache, 'invalidate_on_profile_save' ], 10, 2 );
		add_action( 'save_post_page',               [ $render_cache, 'invalidate_on_policy_save' ],  10, 2 );
		add_action( 'save_post_post',               [ $render_cache, 'invalidate_on_policy_save' ],  10, 2 );
		add_action( 'edited_spx_policy_key',        [ $render_cache, 'invalidate_all' ] );
		add_action( 'update_option_spx_policy_settings', [ $render_cache, 'invalidate_all' ] );
	}

	private function register_admin_hooks(): void {
		if ( ! is_admin() ) {
			return;
		}

		// Profile CPT admin.
		$profile_admin = new ProfileAdmin();
		add_action( 'add_meta_boxes', [ $profile_admin, 'add_meta_boxes' ] );
		add_action( 'save_post_spx_policy_profile', [ $profile_admin, 'save_meta' ], 10, 2 );
		add_filter( 'manage_spx_policy_profile_posts_columns',        [ $profile_admin, 'list_columns' ] );
		add_action( 'manage_spx_policy_profile_posts_custom_column',  [ $profile_admin, 'render_column' ], 10, 2 );
		add_filter( 'manage_edit-spx_policy_profile_sortable_columns', [ $profile_admin, 'sortable_columns' ] );

		// Policy page/post admin.
		add_action( 'add_meta_boxes', [ $profile_admin, 'add_policy_meta_boxes' ] );
		add_action( 'save_post_page', [ $profile_admin, 'save_policy_meta' ], 10, 2 );
		add_action( 'save_post_post', [ $profile_admin, 'save_policy_meta' ], 10, 2 );

		// Placeholder reference screen.
		$placeholder_page = new PlaceholderReferencePage();
		add_action( 'admin_menu', [ $placeholder_page, 'register_menu' ] );

		// Plugin settings.
		add_action( 'admin_menu',  [ $this, 'register_settings_page' ] );
		add_action( 'admin_init',  [ $this, 'register_settings' ] );

		// Admin asset enqueueing.
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
	}

	private function load_textdomain(): void {
		load_plugin_textdomain(
			'sparxstar-policy-renderer',
			false,
			dirname( plugin_basename( SPX_POLICY_FILE ) ) . '/languages'
		);
	}

	// -------------------------------------------------------------------------
	// Public: settings page
	// -------------------------------------------------------------------------

	/**
	 * Registers the Settings page under Settings > Policy Renderer.
	 */
	public function register_settings_page(): void {
		add_options_page(
			__( 'Policy Domain Renderer Settings', 'sparxstar-policy-renderer' ),
			__( 'Policy Renderer', 'sparxstar-policy-renderer' ),
			'manage_options',
			'spx-policy-settings',
			[ $this, 'render_settings_page' ]
		);
	}

	/**
	 * Registers settings, sections, and fields.
	 */
	public function register_settings(): void {
		register_setting(
			'spx_policy_settings_group',
			'spx_policy_settings',
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_settings' ],
				'default'           => [],
			]
		);

		add_settings_section(
			'spx_policy_general',
			__( 'General Settings', 'sparxstar-policy-renderer' ),
			'__return_empty_string',
			'spx-policy-settings'
		);

		$fields = [
			[
				'id'       => 'primary_policy_host',
				'label'    => __( 'Primary Policy Site Host', 'sparxstar-policy-renderer' ),
				'callback' => [ $this, 'render_primary_host_field' ],
			],
			[
				'id'       => 'strip_www',
				'label'    => __( 'Strip www. prefix', 'sparxstar-policy-renderer' ),
				'callback' => [ $this, 'render_strip_www_field' ],
			],
			[
				'id'       => 'default_fallback_profile',
				'label'    => __( 'Default Fallback Profile', 'sparxstar-policy-renderer' ),
				'callback' => [ $this, 'render_default_fallback_field' ],
			],
			[
				'id'       => 'delete_on_uninstall',
				'label'    => __( 'Delete data on uninstall', 'sparxstar-policy-renderer' ),
				'callback' => [ $this, 'render_delete_on_uninstall_field' ],
			],
		];

		foreach ( $fields as $field ) {
			add_settings_field(
				$field['id'],
				$field['label'],
				$field['callback'],
				'spx-policy-settings',
				'spx_policy_general'
			);
		}
	}

	/**
	 * Sanitizes the plugin settings array on save.
	 *
	 * @param mixed $input Raw input from the settings form.
	 *
	 * @return array<string, mixed>
	 */
	public function sanitize_settings( mixed $input ): array {
		if ( ! is_array( $input ) ) {
			return [];
		}

		$clean = [];

		if ( isset( $input['primary_policy_host'] ) ) {
			$clean['primary_policy_host'] = sanitize_text_field( strtolower( (string) $input['primary_policy_host'] ) );
		}

		$clean['strip_www']           = ! empty( $input['strip_www'] );
		$clean['delete_on_uninstall'] = ! empty( $input['delete_on_uninstall'] );

		if ( isset( $input['default_fallback_profile'] ) ) {
			$clean['default_fallback_profile'] = absint( $input['default_fallback_profile'] );
		}

		return $clean;
	}

	/**
	 * Renders the settings page.
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'spx_policy_settings_group' );
				do_settings_sections( 'spx-policy-settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Renders the Primary Policy Host settings field.
	 */
	public function render_primary_host_field(): void {
		$settings = get_option( 'spx_policy_settings', [] );
		$value    = is_array( $settings ) ? ( $settings['primary_policy_host'] ?? '' ) : '';

		printf(
			'<input type="text" name="spx_policy_settings[primary_policy_host]" value="%s" class="regular-text" placeholder="policy.sparxstar.com" />',
			esc_attr( (string) $value )
		);
		echo '<p class="description">' . esc_html__( 'The hostname of the canonical policy site (e.g. policy.sparxstar.com).', 'sparxstar-policy-renderer' ) . '</p>';
	}

	/**
	 * Renders the Strip www. settings field.
	 */
	public function render_strip_www_field(): void {
		$settings = get_option( 'spx_policy_settings', [] );
		$checked  = is_array( $settings ) && ! empty( $settings['strip_www'] );

		printf(
			'<input type="checkbox" name="spx_policy_settings[strip_www]" value="1" %s />',
			checked( $checked, true, false )
		);
		echo '<p class="description">' . esc_html__( 'Strip www. prefix before resolving domain profiles.', 'sparxstar-policy-renderer' ) . '</p>';
	}

	/**
	 * Renders the Default Fallback Profile settings field.
	 */
	public function render_default_fallback_field(): void {
		$settings = get_option( 'spx_policy_settings', [] );
		$selected = (int) ( is_array( $settings ) ? ( $settings['default_fallback_profile'] ?? 0 ) : 0 );

		$profiles = get_posts( [
			'post_type'      => 'spx_policy_profile',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'orderby'        => 'title',
			'order'          => 'ASC',
		] );

		echo '<select name="spx_policy_settings[default_fallback_profile]">';
		echo '<option value="0">' . esc_html__( '— None (return 404) —', 'sparxstar-policy-renderer' ) . '</option>';

		foreach ( $profiles as $profile ) {
			printf(
				'<option value="%d" %s>%s</option>',
				(int) $profile->ID,
				selected( $selected, (int) $profile->ID, false ),
				esc_html( $profile->post_title )
			);
		}

		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Profile to use when the request host is not registered. Leave empty to return 404.', 'sparxstar-policy-renderer' ) . '</p>';
	}

	/**
	 * Renders the Delete on Uninstall settings field.
	 */
	public function render_delete_on_uninstall_field(): void {
		$settings = get_option( 'spx_policy_settings', [] );
		$checked  = is_array( $settings ) && ! empty( $settings['delete_on_uninstall'] );

		printf(
			'<input type="checkbox" name="spx_policy_settings[delete_on_uninstall]" value="1" %s />',
			checked( $checked, true, false )
		);
		echo '<p class="description">' . esc_html__( 'Delete all plugin data (profiles, options, cache) when the plugin is uninstalled. Default: off.', 'sparxstar-policy-renderer' ) . '</p>';
	}

	/**
	 * Enqueues admin CSS and JS on relevant screens.
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public function enqueue_admin_assets( string $hook ): void {
		$screen = get_current_screen();

		$is_profile_screen = null !== $screen && (
			'spx_policy_profile' === $screen->post_type
			|| 'spx_policy_profile_page_spx-policy-placeholder-reference' === $hook
			|| 'settings_page_spx-policy-settings' === $hook
		);

		$is_policy_screen = null !== $screen && in_array( $screen->post_type, [ 'page', 'post' ], true );

		if ( ! $is_profile_screen && ! $is_policy_screen ) {
			return;
		}

		wp_enqueue_style(
			'spx-policy-admin',
			SPX_POLICY_URL . 'assets/admin/policy-admin.css',
			[],
			SPX_POLICY_VERSION
		);

		wp_enqueue_script(
			'spx-policy-admin',
			SPX_POLICY_URL . 'assets/admin/policy-admin.js',
			[ 'jquery' ],
			SPX_POLICY_VERSION,
			true
		);

		wp_localize_script(
			'spx-policy-admin',
			'spxPolicyAdmin',
			[
				'copied' => esc_html__( 'Copied!', 'sparxstar-policy-renderer' ),
			]
		);
	}
}
