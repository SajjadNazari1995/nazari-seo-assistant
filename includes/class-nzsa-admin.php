<?php
declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Branded admin area: top-level menu, dashboard and settings (General / Rules / About).
 */
final class NZSA_Admin {

	public const SLUG       = 'seo-assistant-for-elementor';
	public const SETTINGS   = 'seo-assistant-for-elementor-settings';
	public const AUTHOR     = 'Nazari Technologies';
	public const AUTHOR_URL = 'https://github.com/SajjadNazari1995';

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 5 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'admin_head', array( __CLASS__, 'menu_icon_css' ) );
		add_action( 'in_admin_header', array( __CLASS__, 'brand_bar' ) );
		add_action( 'admin_post_nzsa_save_settings', array( __CLASS__, 'handle_settings' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( NZSA_FILE ), array( __CLASS__, 'action_links' ) );
		add_filter( 'admin_footer_text', array( __CLASS__, 'footer_text' ) );
	}

	/* ------------------------------------------------------------------ *
	 * Helpers
	 * ------------------------------------------------------------------ */

	private static function page(): string {
		return isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	}

	public static function is_plugin_screen(): bool {
		return str_starts_with( self::page(), self::SLUG );
	}

	public static function dashboard_url(): string {
		return admin_url( 'admin.php?page=' . self::SLUG );
	}

	public static function settings_url( string $tab = 'general', array $extra = array() ): string {
		return add_query_arg(
			array_merge(
				array(
					'page' => self::SETTINGS,
					'tab'  => $tab,
				),
				$extra
			),
			admin_url( 'admin.php' )
		);
	}

	private static function logo( bool $white = false ): string {
		return NZSA_URL . 'assets/images/' . ( $white ? 'logo-white.png' : 'logo.png' );
	}

	/* ------------------------------------------------------------------ *
	 * Menu, links, footer
	 * ------------------------------------------------------------------ */

	public static function menu(): void {
		add_menu_page(
			__( 'SEO Assistant', 'seo-assistant-for-elementor' ),
			__( 'SEO Assistant', 'seo-assistant-for-elementor' ),
			'manage_options',
			self::SLUG,
			array( __CLASS__, 'render_dashboard' ),
			'none',
			59
		);
		add_submenu_page( self::SLUG, __( 'Dashboard', 'seo-assistant-for-elementor' ), __( 'Dashboard', 'seo-assistant-for-elementor' ), 'manage_options', self::SLUG, array( __CLASS__, 'render_dashboard' ) );
		add_submenu_page( self::SLUG, __( 'Settings', 'seo-assistant-for-elementor' ), __( 'Settings', 'seo-assistant-for-elementor' ), 'manage_options', self::SETTINGS, array( __CLASS__, 'render_settings' ) );
	}

	/**
	 * @param string[] $links
	 * @return string[]
	 */
	public static function action_links( array $links ): array {
		array_unshift(
			$links,
			'<a href="' . esc_url( self::dashboard_url() ) . '">' . esc_html__( 'Dashboard', 'seo-assistant-for-elementor' ) . '</a>',
			'<a href="' . esc_url( self::settings_url() ) . '">' . esc_html__( 'Settings', 'seo-assistant-for-elementor' ) . '</a>'
		);
		return $links;
	}

	public static function footer_text( string $text ): string {
		if ( ! self::is_plugin_screen() ) {
			return $text;
		}
		return sprintf(
			/* translators: %s: author link */
			esc_html__( 'SEO Assistant — made by %s', 'seo-assistant-for-elementor' ),
			'<a href="' . esc_url( self::AUTHOR_URL ) . '" target="_blank" rel="noopener">' . esc_html( self::AUTHOR ) . '</a>'
		);
	}

	public static function menu_icon_css(): void {
		$url = esc_url( NZSA_URL . 'assets/images/menu-icon.png' );
		?>
		<style id="nzsa-menu-icon">
			#adminmenu .toplevel_page_seo-assistant-for-elementor div.wp-menu-image:before {
				content: "";
				display: block;
				width: 20px;
				height: 20px;
				margin: 0 auto;
				padding: 7px 0;
				box-sizing: content-box;
				background-color: currentColor;
				-webkit-mask: url( "<?php echo $url; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above. ?>" ) center / 20px 20px no-repeat content-box;
				mask: url( "<?php echo $url; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above. ?>" ) center / 20px 20px no-repeat content-box;
			}
		</style>
		<?php
	}

	public static function enqueue(): void {
		if ( ! self::is_plugin_screen() ) {
			return;
		}
		wp_enqueue_style( 'nzsa-admin', NZSA_URL . 'assets/admin.css', array(), NZSA_VERSION );
	}

	public static function brand_bar(): void {
		if ( ! self::is_plugin_screen() ) {
			return;
		}
		$page = self::page();
		$tabs = array(
			array( __( 'Dashboard', 'seo-assistant-for-elementor' ), self::dashboard_url(), self::SLUG === $page ),
			array( __( 'Settings', 'seo-assistant-for-elementor' ), self::settings_url(), self::SETTINGS === $page ),
		);
		?>
		<div class="nzsa-brandbar">
			<img class="nzsa-brandbar-logo" src="<?php echo esc_url( self::logo( true ) ); ?>" alt="">
			<div class="nzsa-brandbar-title">
				<strong><?php esc_html_e( 'SEO Assistant', 'seo-assistant-for-elementor' ); ?></strong>
				<span>Nazari Technologies</span>
			</div>
			<span class="nzsa-badge">v<?php echo esc_html( NZSA_VERSION ); ?></span>
			<nav class="nzsa-brandbar-nav">
				<?php foreach ( $tabs as $tab ) : ?>
					<a href="<?php echo esc_url( $tab[1] ); ?>" class="<?php echo $tab[2] ? 'is-active' : ''; ?>"><?php echo esc_html( $tab[0] ); ?></a>
				<?php endforeach; ?>
			</nav>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ *
	 * Dashboard
	 * ------------------------------------------------------------------ */

	public static function render_dashboard(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$elementor = did_action( 'elementor/loaded' );
		$cats      = NZSA_Category::cases();
		?>
		<div class="wrap nzsa-wrap">
			<h1 class="screen-reader-text"><?php esc_html_e( 'SEO Assistant', 'seo-assistant-for-elementor' ); ?></h1>

			<section class="nzsa-hero">
				<div class="nzsa-hero-text">
					<h2><?php esc_html_e( 'Design it right the first time. No separate SEO pass.', 'seo-assistant-for-elementor' ); ?></h2>
					<p><?php esc_html_e( 'Right-click any section in the Elementor editor and choose Review structure. You get an ordered list of what to fix: the HTML tag to use, image size and resolution, mobile fit and loading speed, with a score.', 'seo-assistant-for-elementor' ); ?></p>
					<p class="nzsa-hero-actions">
						<a class="nzsa-btn nzsa-btn-light" href="<?php echo esc_url( admin_url( 'edit.php?post_type=page' ) ); ?>"><?php esc_html_e( 'Open a page to review', 'seo-assistant-for-elementor' ); ?></a>
						<a class="nzsa-btn nzsa-btn-ghost" href="<?php echo esc_url( self::settings_url() ); ?>"><?php esc_html_e( 'Settings', 'seo-assistant-for-elementor' ); ?></a>
					</p>
				</div>
				<img class="nzsa-hero-logo" src="<?php echo esc_url( self::logo( true ) ); ?>" alt="Nazari Technologies">
			</section>

			<section class="nzsa-stats">
				<?php foreach ( $cats as $cat ) : ?>
					<div class="nzsa-stat">
						<span class="dashicons dashicons-yes-alt"></span>
						<strong><?php echo esc_html( number_format_i18n( $cat->max_points() ) ); ?></strong>
						<span><?php echo esc_html( $cat->label() ); ?></span>
					</div>
				<?php endforeach; ?>
			</section>

			<div class="nzsa-columns">
				<div class="nzsa-col-main">
					<section class="nzsa-card">
						<header class="nzsa-card-head"><h3><?php esc_html_e( 'How it works', 'seo-assistant-for-elementor' ); ?></h3></header>
						<ol class="nzsa-steps">
							<li>
								<strong><?php esc_html_e( 'Right-click', 'seo-assistant-for-elementor' ); ?></strong>
								<span><?php esc_html_e( 'In the Elementor editor, right-click a section, container or widget and pick Review structure.', 'seo-assistant-for-elementor' ); ?></span>
							</li>
							<li>
								<strong><?php esc_html_e( 'Read the guide', 'seo-assistant-for-elementor' ); ?></strong>
								<span><?php esc_html_e( 'A window lists every element in order with exactly what to change and where.', 'seo-assistant-for-elementor' ); ?></span>
							</li>
							<li>
								<strong><?php esc_html_e( 'Fix and check again', 'seo-assistant-for-elementor' ); ?></strong>
								<span><?php esc_html_e( 'The last item scores the page. Press Check again after your changes.', 'seo-assistant-for-elementor' ); ?></span>
							</li>
						</ol>
					</section>
				</div>

				<aside class="nzsa-col-side">
					<section class="nzsa-card">
						<header class="nzsa-card-head"><h3><?php esc_html_e( 'System status', 'seo-assistant-for-elementor' ); ?></h3></header>
						<ul class="nzsa-status">
							<li>
								<span><?php esc_html_e( 'Elementor', 'seo-assistant-for-elementor' ); ?></span>
								<b class="<?php echo $elementor ? 'is-ok' : 'is-warn'; ?>"><?php echo $elementor ? esc_html( defined( 'ELEMENTOR_VERSION' ) ? (string) ELEMENTOR_VERSION : '✓' ) : esc_html__( 'Not active', 'seo-assistant-for-elementor' ); ?></b>
							</li>
							<li><span>WordPress</span><b><?php echo esc_html( get_bloginfo( 'version' ) ); ?></b></li>
							<li><span>PHP</span><b><?php echo esc_html( PHP_VERSION ); ?></b></li>
							<li><span><?php esc_html_e( 'Plugin version', 'seo-assistant-for-elementor' ); ?></span><b><?php echo esc_html( NZSA_VERSION ); ?></b></li>
						</ul>
						<?php if ( ! $elementor ) : ?>
							<p class="nzsa-note is-warn"><?php esc_html_e( 'Elementor is not active. The review window lives inside the Elementor editor.', 'seo-assistant-for-elementor' ); ?></p>
						<?php endif; ?>
					</section>
				</aside>
			</div>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ *
	 * Settings
	 * ------------------------------------------------------------------ */

	public static function render_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tabs = array(
			'general' => array( __( 'General', 'seo-assistant-for-elementor' ), 'dashicons-admin-generic' ),
			'rules'   => array( __( 'Rules', 'seo-assistant-for-elementor' ), 'dashicons-list-view' ),
			'about'   => array( __( 'About', 'seo-assistant-for-elementor' ), 'dashicons-info-outline' ),
		);
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'general';
		}
		$saved = isset( $_GET['nzsa_notice'] ) && 'saved' === sanitize_key( wp_unslash( $_GET['nzsa_notice'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		?>
		<div class="wrap nzsa-wrap">
			<h1 class="screen-reader-text"><?php esc_html_e( 'Settings', 'seo-assistant-for-elementor' ); ?></h1>

			<?php if ( $saved ) : ?>
				<div class="nzsa-toast is-success"><?php esc_html_e( 'Settings saved.', 'seo-assistant-for-elementor' ); ?></div>
			<?php endif; ?>

			<nav class="nzsa-tabs">
				<?php foreach ( $tabs as $key => $info ) : ?>
					<a href="<?php echo esc_url( self::settings_url( $key ) ); ?>" class="<?php echo $key === $tab ? 'is-active' : ''; ?>">
						<span class="dashicons <?php echo esc_attr( $info[1] ); ?>"></span> <?php echo esc_html( $info[0] ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<?php
			match ( $tab ) {
				'rules' => self::render_rules_tab(),
				'about' => self::render_about_tab(),
				default => self::render_general_tab(),
			};
			?>
		</div>
		<?php
	}

	private static function number_field( string $name, string $label, string $help, int|float $value, int|float $min, int|float $max, string $step = '1' ): void {
		?>
		<div class="nzsa-form-row">
			<label for="nzsa-<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $label ); ?></label>
			<input type="number" id="nzsa-<?php echo esc_attr( $name ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( (string) $value ); ?>" min="<?php echo esc_attr( (string) $min ); ?>" max="<?php echo esc_attr( (string) $max ); ?>" step="<?php echo esc_attr( $step ); ?>" class="small-text">
			<p class="description"><?php echo esc_html( $help ); ?></p>
		</div>
		<?php
	}

	private static function render_general_tab(): void {
		$s = NZSA_Settings::all();
		?>
		<div class="nzsa-columns">
			<div class="nzsa-col-main">
				<section class="nzsa-card">
					<header class="nzsa-card-head"><h3><?php esc_html_e( 'Review thresholds', 'seo-assistant-for-elementor' ); ?></h3></header>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="nzsa_save_settings">
						<?php wp_nonce_field( 'nzsa_save_settings' ); ?>

						<div class="nzsa-form-grid">
							<?php
							self::number_field( 'image_warn_kb', __( 'Image size: improve above (KB)', 'seo-assistant-for-elementor' ), __( 'Images heavier than this get an Improve notice.', 'seo-assistant-for-elementor' ), $s['image_warn_kb'], 20, 5000 );
							self::number_field( 'image_error_kb', __( 'Image size: fix above (KB)', 'seo-assistant-for-elementor' ), __( 'Images heavier than this get a Fix now notice.', 'seo-assistant-for-elementor' ), $s['image_error_kb'], 20, 20000 );
							self::number_field( 'lcp_kb', __( 'Top image: improve above (KB)', 'seo-assistant-for-elementor' ), __( 'The first image on a page decides how fast it feels, so it gets a lower limit.', 'seo-assistant-for-elementor' ), $s['lcp_kb'], 30, 2000 );
							self::number_field( 'retina_factor', __( 'Sharpness factor', 'seo-assistant-for-elementor' ), __( 'How many times the displayed width an image may be. 2 suits retina screens.', 'seo-assistant-for-elementor' ), $s['retina_factor'], 1, 3, '0.1' );
							self::number_field( 'max_dom_depth', __( 'Maximum nesting depth', 'seo-assistant-for-elementor' ), __( 'Containers nested deeper than this are flagged.', 'seo-assistant-for-elementor' ), $s['max_dom_depth'], 3, 15 );
							self::number_field( 'viewport', __( 'Desktop width used for estimates (px)', 'seo-assistant-for-elementor' ), __( 'Used to work out how wide each element appears on desktop.', 'seo-assistant-for-elementor' ), $s['viewport'], 1024, 2560 );
							?>
						</div>

						<label class="nzsa-setting">
							<span class="nzsa-switch">
								<input type="checkbox" name="delete_on_uninstall" value="1" <?php checked( 1, (int) $s['delete_on_uninstall'] ); ?>>
								<span class="nzsa-slider"></span>
							</span>
							<span>
								<strong><?php esc_html_e( 'Delete data on uninstall', 'seo-assistant-for-elementor' ); ?></strong>
								<em><?php esc_html_e( 'When you delete the plugin, also remove its settings.', 'seo-assistant-for-elementor' ); ?></em>
							</span>
						</label>

						<p><button type="submit" class="nzsa-btn nzsa-btn-primary"><?php esc_html_e( 'Save settings', 'seo-assistant-for-elementor' ); ?></button></p>
					</form>
				</section>
			</div>
		</div>
		<?php
	}

	private static function render_rules_tab(): void {
		$labels = array(
			'tags'       => array( __( 'HTML tags', 'seo-assistant-for-elementor' ), __( 'Header, footer, main, section and heading tags in the right places.', 'seo-assistant-for-elementor' ) ),
			'images'     => array( __( 'Images', 'seo-assistant-for-elementor' ), __( 'File size, pixel size against displayed size, format and alt text.', 'seo-assistant-for-elementor' ) ),
			'weight'     => array( __( 'Page weight', 'seo-assistant-for-elementor' ), __( 'Total image weight, nesting depth and element count.', 'seo-assistant-for-elementor' ) ),
			'responsive' => array( __( 'Responsive fit', 'seo-assistant-for-elementor' ), __( 'Elements that overflow or shrink badly on tablet and mobile.', 'seo-assistant-for-elementor' ) ),
			'links'      => array( __( 'Links and buttons', 'seo-assistant-for-elementor' ), __( 'Empty links, vague link text and missing targets.', 'seo-assistant-for-elementor' ) ),
			'vitals'     => array( __( 'Core Web Vitals', 'seo-assistant-for-elementor' ), __( 'The first image on the page (LCP) and content that jumps while loading (CLS).', 'seo-assistant-for-elementor' ) ),
		);
		?>
		<div class="nzsa-columns">
			<div class="nzsa-col-main">
				<section class="nzsa-card">
					<header class="nzsa-card-head"><h3><?php esc_html_e( 'Active checks', 'seo-assistant-for-elementor' ); ?></h3></header>
					<ul class="nzsa-status">
						<?php foreach ( NZSA_Analyzer::rules() as $rule ) : ?>
							<?php $info = $labels[ $rule->id() ] ?? array( $rule->id(), '' ); ?>
							<li><span><strong><?php echo esc_html( $info[0] ); ?></strong><br><?php echo esc_html( $info[1] ); ?></span><b class="is-ok">✓</b></li>
						<?php endforeach; ?>
					</ul>
				</section>
				<section class="nzsa-card">
					<header class="nzsa-card-head"><h3><?php esc_html_e( 'For developers', 'seo-assistant-for-elementor' ); ?></h3></header>
					<p><?php esc_html_e( 'Add your own checks: extend NZSA_Rule and register it with the nzsa_rules filter.', 'seo-assistant-for-elementor' ); ?></p>
					<p><code class="nzsa-code">add_filter( 'nzsa_rules', fn( $rules ) =&gt; array_merge( $rules, array( new My_Rule() ) ) );</code></p>
				</section>
			</div>
		</div>
		<?php
	}

	private static function render_about_tab(): void {
		$changelog = array(
			'1.1.0' => array(
				'Page roles: the review knows if the page is the home page, a post, an archive, or a header, footer or popup template, and adjusts its checks.',
				'Real sizes: widths are measured in the editor preview instead of estimated, and in tablet and mobile view elements wider than the screen are reported.',
				'Core Web Vitals: LCP checks for the first image on the page, and CLS checks for iframes and images without a size.',
			),
			'1.0.1' => array(
				'Fix: the Review structure right-click item now appears on elements already on the page when the editor opens.',
			),
			'1.0.0' => array(
				__( 'First release: right-click Review structure in the Elementor editor.', 'seo-assistant-for-elementor' ),
				__( 'Ordered guidance per element, a scored summary and a Check again button.', 'seo-assistant-for-elementor' ),
				__( 'English and Persian, with RTL support.', 'seo-assistant-for-elementor' ),
			),
		);
		?>
		<div class="nzsa-columns">
			<div class="nzsa-col-main">
				<section class="nzsa-card nzsa-about">
					<img src="<?php echo esc_url( self::logo() ); ?>" alt="Nazari Technologies" class="nzsa-about-logo">
					<div>
						<h3><?php esc_html_e( 'SEO Assistant', 'seo-assistant-for-elementor' ); ?></h3>
						<p class="description"><?php echo esc_html( sprintf( /* translators: %s: version */ __( 'Version %s', 'seo-assistant-for-elementor' ), NZSA_VERSION ) ); ?></p>
						<p><?php esc_html_e( 'A design review inside the Elementor editor, so the page is built to standard from the start.', 'seo-assistant-for-elementor' ); ?></p>
					</div>
				</section>
				<section class="nzsa-card">
					<header class="nzsa-card-head"><h3><?php esc_html_e( 'Changelog', 'seo-assistant-for-elementor' ); ?></h3></header>
					<?php foreach ( $changelog as $version => $items ) : ?>
						<h4 class="nzsa-version">v<?php echo esc_html( (string) $version ); ?></h4>
						<ul class="nzsa-list">
							<?php foreach ( $items as $item ) : ?>
								<li><?php echo esc_html( $item ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endforeach; ?>
				</section>
			</div>
			<aside class="nzsa-col-side">
				<section class="nzsa-card">
					<header class="nzsa-card-head"><h3><?php esc_html_e( 'Author', 'seo-assistant-for-elementor' ); ?></h3></header>
					<ul class="nzsa-status">
						<li><span><?php esc_html_e( 'Name', 'seo-assistant-for-elementor' ); ?></span><b><?php echo esc_html( self::AUTHOR ); ?></b></li>
						<li><span><?php esc_html_e( 'GitHub', 'seo-assistant-for-elementor' ); ?></span><b><a href="<?php echo esc_url( self::AUTHOR_URL ); ?>" target="_blank" rel="noopener">github.com/SajjadNazari1995</a></b></li>
						<li><span><?php esc_html_e( 'License', 'seo-assistant-for-elementor' ); ?></span><b>GPL-2.0+</b></li>
					</ul>
				</section>
			</aside>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ *
	 * Handler
	 * ------------------------------------------------------------------ */

	public static function handle_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'seo-assistant-for-elementor' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'nzsa_save_settings' );

		$raw = array();
		foreach ( array( 'image_warn_kb', 'image_error_kb', 'lcp_kb', 'retina_factor', 'max_dom_depth', 'viewport', 'delete_on_uninstall' ) as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				$raw[ $key ] = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
			}
		}
		update_option( NZSA_Settings::OPTION, NZSA_Settings::sanitize( $raw ), false );

		wp_safe_redirect( self::settings_url( 'general', array( 'nzsa_notice' => 'saved' ) ) );
		exit;
	}
}
