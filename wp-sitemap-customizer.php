<?php
/**
 * Plugin Name: Sitemap Customizer
 * Plugin URI: https://github.com/Azanumer/wp-sitemap-customizer
 * Description: Take control of WordPress core sitemaps — exclude post types, taxonomies and individual posts, add custom URLs, and set how many URLs each sitemap holds.
 * Version: 1.0.0
 * Author: Azan Umer
 * Author URI: https://azanumer.cv
 * License: MIT
 * Text Domain: wp-sitemap-customizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WSC_VERSION', '1.0.0' );
define( 'WSC_OPTION', 'wsc_settings' );

/**
 * Default settings.
 *
 * @return array
 */
function wsc_default_settings() {
	return array(
		'excluded_post_types' => array(),
		'excluded_taxonomies' => array(),
		'excluded_post_ids'   => '',
		'custom_urls'         => '',
		'max_urls'            => 2000,
	);
}

/**
 * Get merged settings.
 *
 * @return array
 */
function wsc_get_settings() {
	$saved = get_option( WSC_OPTION, array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}
	return array_merge( wsc_default_settings(), $saved );
}

/**
 * Parse the custom-URLs textarea into entries.
 *
 * One URL per line, optionally followed by " | YYYY-MM-DD" for lastmod:
 *   https://example.com/landing-page
 *   https://example.com/promo | 2026-11-01
 *
 * @return array[] Each entry: array( 'url' => string, 'lastmod' => string ).
 */
function wsc_custom_urls() {
	$settings = wsc_get_settings();
	$entries  = array();
	foreach ( preg_split( "/\r\n|\r|\n/", (string) $settings['custom_urls'] ) as $line ) {
		$line = trim( $line );
		if ( '' === $line ) {
			continue;
		}
		$parts   = array_map( 'trim', explode( '|', $line, 2 ) );
		$url     = esc_url_raw( $parts[0], array( 'http', 'https' ) );
		$lastmod = '';
		if ( isset( $parts[1] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $parts[1] ) ) {
			$lastmod = $parts[1];
		}
		if ( '' === $url ) {
			continue;
		}
		$entries[] = array(
			'url'     => $url,
			'lastmod' => $lastmod,
		);
	}
	return apply_filters( 'wsc_custom_urls', $entries );
}

/**
 * Sitemap provider for the custom URLs.
 */
class WSC_Custom_Urls_Provider extends WP_Sitemaps_Provider {

	/**
	 * Set up the provider.
	 */
	public function __construct() {
		$this->name        = 'custom';
		$this->object_type = 'custom';
	}

	/**
	 * Get the URL list for a page of the sitemap.
	 *
	 * @param int    $page_num       Page number.
	 * @param string $object_subtype Unused.
	 * @return array[]
	 */
	public function get_url_list( $page_num, $object_subtype = '' ) {
		$per_page  = max( 1, (int) apply_filters( 'wp_sitemaps_max_urls', 2000 ) );
		$urls      = wsc_custom_urls();
		$page_urls = array_slice( $urls, ( $page_num - 1 ) * $per_page, $per_page );
		$entries   = array();
		foreach ( $page_urls as $entry ) {
			$item = array( 'loc' => $entry['url'] );
			if ( '' !== $entry['lastmod'] ) {
				$item['lastmod'] = $entry['lastmod'];
			}
			$entries[] = $item;
		}
		return $entries;
	}

	/**
	 * How many sitemap pages the custom URLs need.
	 *
	 * @param string $object_subtype Unused.
	 * @return int
	 */
	public function get_max_num_pages( $object_subtype = '' ) {
		$per_page = max( 1, (int) apply_filters( 'wp_sitemaps_max_urls', 2000 ) );
		$total    = count( wsc_custom_urls() );
		return $total > 0 ? (int) ceil( $total / $per_page ) : 0;
	}

	/**
	 * Sitemap type data for the index.
	 *
	 * @return array[]
	 */
	public function get_sitemap_type_data() {
		$pages = $this->get_max_num_pages();
		if ( $pages < 1 ) {
			return array();
		}
		return array(
			array(
				'name'  => $this->name,
				'pages' => $pages,
			),
		);
	}
}

/**
 * Register the custom-URLs provider.
 *
 * @param WP_Sitemaps_Provider|null $provider Provider object.
 * @param string                    $name     Provider name.
 * @return WP_Sitemaps_Provider|null
 */
function wsc_register_custom_provider( $provider, $name ) {
	if ( 'custom' === $name ) {
		return new WSC_Custom_Urls_Provider();
	}
	return $provider;
}
add_filter( 'wp_sitemaps_add_provider', 'wsc_register_custom_provider', 10, 2 );

/**
 * Remove excluded post types from the sitemap.
 *
 * @param WP_Post_Type[] $post_types Post types in the sitemap.
 * @return WP_Post_Type[]
 */
function wsc_exclude_post_types( $post_types ) {
	$settings = wsc_get_settings();
	$excluded = isset( $settings['excluded_post_types'] ) && is_array( $settings['excluded_post_types'] ) ? $settings['excluded_post_types'] : array();
	foreach ( $excluded as $type ) {
		unset( $post_types[ $type ] );
	}
	return $post_types;
}
add_filter( 'wp_sitemaps_post_types', 'wsc_exclude_post_types' );

/**
 * Remove excluded taxonomies from the sitemap.
 *
 * @param WP_Taxonomy[] $taxonomies Taxonomies in the sitemap.
 * @return WP_Taxonomy[]
 */
function wsc_exclude_taxonomies( $taxonomies ) {
	$settings = wsc_get_settings();
	$excluded = isset( $settings['excluded_taxonomies'] ) && is_array( $settings['excluded_taxonomies'] ) ? $settings['excluded_taxonomies'] : array();
	foreach ( $excluded as $taxonomy ) {
		unset( $taxonomies[ $taxonomy ] );
	}
	return $taxonomies;
}
add_filter( 'wp_sitemaps_taxonomies', 'wsc_exclude_taxonomies' );

/**
 * Exclude individual posts from the posts sitemap.
 *
 * @param array $args WP_Query arguments for the sitemap query.
 * @return array
 */
function wsc_exclude_post_ids( $args ) {
	$settings = wsc_get_settings();
	$ids      = array();
	foreach ( preg_split( '/[\s,]+/', (string) $settings['excluded_post_ids'] ) as $part ) {
		$id = (int) $part;
		if ( $id > 0 ) {
			$ids[] = $id;
		}
	}
	if ( ! empty( $ids ) ) {
		$args['post__not_in'] = array_unique( array_merge( isset( $args['post__not_in'] ) ? (array) $args['post__not_in'] : array(), $ids ) );
	}
	return $args;
}
add_filter( 'wp_sitemaps_posts_query_args', 'wsc_exclude_post_ids' );

/**
 * Cap the number of URLs per sitemap.
 *
 * @param int $max_urls Default (2000).
 * @return int
 */
function wsc_max_urls( $max_urls ) {
	$settings = wsc_get_settings();
	$max      = (int) $settings['max_urls'];
	return $max > 0 ? $max : $max_urls;
}
add_filter( 'wp_sitemaps_max_urls', 'wsc_max_urls' );

/**
 * Settings page under Settings → Sitemap.
 */
function wsc_register_settings_page() {
	add_options_page(
		__( 'Sitemap', 'wp-sitemap-customizer' ),
		__( 'Sitemap', 'wp-sitemap-customizer' ),
		'manage_options',
		'wsc-settings',
		'wsc_render_settings_page'
	);
}
add_action( 'admin_menu', 'wsc_register_settings_page' );

/**
 * Render the settings page.
 */
function wsc_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-sitemap-customizer' ) );
	}

	if ( isset( $_POST['wsc_save'] ) ) {
		check_admin_referer( 'wsc_save_settings' );
		$input = wp_unslash( $_POST );
		update_option(
			WSC_OPTION,
			array(
				'excluded_post_types' => isset( $input['excluded_post_types'] ) && is_array( $input['excluded_post_types'] ) ? array_map( 'sanitize_key', $input['excluded_post_types'] ) : array(),
				'excluded_taxonomies' => isset( $input['excluded_taxonomies'] ) && is_array( $input['excluded_taxonomies'] ) ? array_map( 'sanitize_key', $input['excluded_taxonomies'] ) : array(),
				'excluded_post_ids'   => isset( $input['excluded_post_ids'] ) ? sanitize_text_field( $input['excluded_post_ids'] ) : '',
				'custom_urls'         => isset( $input['custom_urls'] ) ? sanitize_textarea_field( $input['custom_urls'] ) : '',
				'max_urls'            => max( 1, (int) ( $input['max_urls'] ?? 2000 ) ),
			)
		);
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved. Flush the sitemap cache by visiting your sitemap index.', 'wp-sitemap-customizer' ) . '</p></div>';
	}

	$settings     = wsc_get_settings();
	$post_types   = get_post_types( array( 'public' => true ), 'objects' );
	$taxonomies   = get_taxonomies( array( 'public' => true ), 'objects' );
	$ex_types     = isset( $settings['excluded_post_types'] ) && is_array( $settings['excluded_post_types'] ) ? $settings['excluded_post_types'] : array();
	$ex_tax       = isset( $settings['excluded_taxonomies'] ) && is_array( $settings['excluded_taxonomies'] ) ? $settings['excluded_taxonomies'] : array();
	$sitemap_url  = home_url( '/wp-sitemap.xml' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Sitemap', 'wp-sitemap-customizer' ); ?></h1>
		<p><?php esc_html_e( 'Your sitemap index:', 'wp-sitemap-customizer' ); ?> <a href="<?php echo esc_url( $sitemap_url ); ?>" target="_blank"><?php echo esc_html( $sitemap_url ); ?></a></p>
		<form method="post">
			<?php wp_nonce_field( 'wsc_save_settings' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Exclude post types', 'wp-sitemap-customizer' ); ?></th>
					<td>
						<?php foreach ( $post_types as $type ) : ?>
							<label>
								<input type="checkbox" name="excluded_post_types[]" value="<?php echo esc_attr( $type->name ); ?>" <?php checked( in_array( $type->name, $ex_types, true ) ); ?> />
								<?php echo esc_html( $type->labels->singular_name ); ?> (<?php echo esc_html( $type->name ); ?>)
							</label><br />
						<?php endforeach; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Exclude taxonomies', 'wp-sitemap-customizer' ); ?></th>
					<td>
						<?php foreach ( $taxonomies as $taxonomy ) : ?>
							<label>
								<input type="checkbox" name="excluded_taxonomies[]" value="<?php echo esc_attr( $taxonomy->name ); ?>" <?php checked( in_array( $taxonomy->name, $ex_tax, true ) ); ?> />
								<?php echo esc_html( $taxonomy->labels->singular_name ); ?> (<?php echo esc_html( $taxonomy->name ); ?>)
							</label><br />
						<?php endforeach; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wsc-ids"><?php esc_html_e( 'Exclude post IDs', 'wp-sitemap-customizer' ); ?></label></th>
					<td>
						<input type="text" id="wsc-ids" name="excluded_post_ids" value="<?php echo esc_attr( $settings['excluded_post_ids'] ); ?>" class="regular-text" />
						<p class="description"><?php esc_html_e( 'Comma-separated post or page IDs to keep out of the sitemap (thank-you pages, landing pages, …).', 'wp-sitemap-customizer' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wsc-urls"><?php esc_html_e( 'Custom URLs', 'wp-sitemap-customizer' ); ?></label></th>
					<td>
						<textarea id="wsc-urls" name="custom_urls" rows="6" cols="60"><?php echo esc_textarea( $settings['custom_urls'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'One URL per line. Add " | YYYY-MM-DD" after a URL to set its lastmod date. These appear in their own sitemap (wp-sitemap-custom-1.xml).', 'wp-sitemap-customizer' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wsc-max"><?php esc_html_e( 'Max URLs per sitemap', 'wp-sitemap-customizer' ); ?></label></th>
					<td><input type="number" id="wsc-max" name="max_urls" value="<?php echo (int) $settings['max_urls']; ?>" min="1" max="50000" /> <span class="description"><?php esc_html_e( 'WordPress default is 2000.', 'wp-sitemap-customizer' ); ?></span></td>
				</tr>
			</table>
			<?php submit_button( __( 'Save Settings', 'wp-sitemap-customizer' ), 'primary', 'wsc_save' ); ?>
		</form>
	</div>
	<?php
}
