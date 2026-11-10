<?php
/**
 * Plugin Name: Post Quality directory preview (development only)
 * Description: Answers the Add Plugins screen for Post Quality Manager as the WordPress.org directory would, before the plugin is listed. Development only.
 *
 * Story 70. The WordPress.org directory builds the plugin's Add Plugins card
 * and its More Details view from the readme, the plugin header and the
 * directory images (`assets/` at the repository root). Until the plugin is
 * listed, this must-use plugin stands in for the directory on a Local site:
 * on `plugins_api` it answers a search for the plugin with its card and the
 * details request for its slug with the full record, both built from the
 * installed build's readme (Plugin Check's copy of the directory's readme
 * parser, with the directory's own Markdown) and header, as the directory's
 * API sends them for a new listing. Every other request keeps its incoming
 * result.
 *
 * Development only: it lives in bin/ and ships in no build. The recipe in
 * docs/local-verification.md, "Add Plugins preview (story 70)", fetches the
 * Markdown, copies this file into the site's mu-plugins folder as
 * postquality-directory-preview.php with the Markdown and the images in
 * postquality-directory-preview/ beside it, and removes them again.
 * tests/harness/directory-preview-harness.php checks the routing.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const POSTQUALITY_PREVIEW_SLUG = 'post-quality-manager';
const POSTQUALITY_PREVIEW_NAME = 'Post Quality Manager';
// The directory's Markdown class and the four Michelf files it loads, in the layout it includes them from.
const POSTQUALITY_PREVIEW_MARKDOWN = array(
	'class-markdown.php',
	'libs/michelf-php-markdown-1.6.0/Michelf/MarkdownExtra.inc.php',
	'libs/michelf-php-markdown-1.6.0/Michelf/MarkdownInterface.php',
	'libs/michelf-php-markdown-1.6.0/Michelf/Markdown.php',
	'libs/michelf-php-markdown-1.6.0/Michelf/MarkdownExtra.php',
);

/**
 * Which answer a `plugins_api` request gets: 'search' (the card), 'details'
 * (More Details) or '' (its incoming result).
 *
 * A search term counts when, decoded (the AJAX search passes `s` still
 * percent-encoded), lowercased and with `-` read as a space, it is the name
 * or the name's first two or more words; the slug reads as the name.
 *
 * @param string       $action The API action.
 * @param object|array $args   The API arguments.
 * @return string
 */
function postquality_directory_preview_route( $action, $args ) {
	$args = (array) $args;
	if ( 'plugin_information' === $action ) {
		return POSTQUALITY_PREVIEW_SLUG === ( $args['slug'] ?? null ) ? 'details' : '';
	}
	if ( 'query_plugins' !== $action || ! isset( $args['search'] ) || ! is_string( $args['search'] ) ) {
		return '';
	}
	$term  = trim( preg_replace( '/[\s-]+/', ' ', strtolower( rawurldecode( $args['search'] ) ) ) );
	$words = explode( ' ', strtolower( POSTQUALITY_PREVIEW_NAME ) );
	for ( $n = count( $words ); $n >= 2; $n-- ) {
		if ( implode( ' ', array_slice( $words, 0, $n ) ) === $term ) {
			return 'search';
		}
	}
	return '';
}

/**
 * The `plugins_api` filter: the plugin's card or record, or the incoming
 * result untouched.
 *
 * @param false|object|array $result The result so far.
 * @param string             $action The API action.
 * @param object             $args   The API arguments.
 * @return false|object|array
 */
function postquality_directory_preview_answer( $result, $action, $args ) {
	$route = postquality_directory_preview_route( $action, $args );
	if ( '' === $route ) {
		return $result;
	}
	$record = postquality_directory_preview_record( __DIR__ . '/postquality-directory-preview', WPMU_PLUGIN_URL . '/postquality-directory-preview' );
	if ( null === $record ) {
		return $result;
	}
	if ( 'details' === $route ) {
		return (object) $record;
	}
	return (object) array(
		'info'    => array(
			'page'    => 1,
			'pages'   => 1,
			'results' => 1,
		),
		'plugins' => array( $record ),
	);
}
// Last, so it overrides the Freemius SDK's updater (priority 10 for this slug, registered by the Pro build only).
add_filter( 'plugins_api', 'postquality_directory_preview_answer', PHP_INT_MAX, 3 );

/**
 * The record the directory's API sends for the installed build as a new
 * listing (plugin-directory/api/routes/class-plugin.php in
 * github.com/WordPress/wordpress.org): rating 0, no ratings, active installs
 * 0, last updated now. Null, after one error_log line, when the preview is
 * not set up.
 *
 * @param string $dir The folder holding the Markdown and the images.
 * @param string $url That folder's URL.
 * @return array|null
 */
function postquality_directory_preview_record( $dir, $url ) {
	$plugin_dir = WP_PLUGIN_DIR . '/' . POSTQUALITY_PREVIEW_SLUG;
	$missing    = array();
	if ( ! class_exists( 'WordPress\Plugin_Check\Lib\Readme\Parser' ) ) {
		$missing[] = 'Plugin Check, active (its readme parser)';
	}
	// All five, not the class alone: a missing Michelf file would be a fatal on the Add Plugins screen.
	$markdown = array_filter( POSTQUALITY_PREVIEW_MARKDOWN, static fn( $file ) => ! is_readable( $dir . '/' . $file ) );
	if ( $markdown ) {
		$missing[] = "the directory's Markdown in $dir (" . implode( ', ', $markdown ) . ')';
	}
	if ( ! is_readable( $plugin_dir . '/readme.txt' ) ) {
		$missing[] = "the plugin's readme in $plugin_dir";
	}
	if ( $missing ) {
		error_log( 'Post Quality directory preview: missing ' . implode( '; ', $missing ) . '. The request keeps its own answer.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- development-only tool, the recipe reads debug.log
		return null;
	}

	// Loaded here only: once the class exists, Plugin Check's own readme checks render Markdown too.
	require_once $dir . '/class-markdown.php';
	if ( ! function_exists( 'get_plugin_data' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$readme = new \WordPress\Plugin_Check\Lib\Readme\Parser( $plugin_dir . '/readme.txt' );
	$header = get_plugin_data( $plugin_dir . '/' . POSTQUALITY_PREVIEW_SLUG . '.php', false, false );

	// The directory prefers the header's well-formed requirements (cli/class-import.php).
	$requires     = preg_match( '!^[\d.]{3,}$!', $header['RequiresWP'] ) ? $header['RequiresWP'] : $readme->requires;
	$requires_php = preg_match( '!^[\d.]{3,}$!', $header['RequiresPHP'] ) ? $header['RequiresPHP'] : $readme->requires_php;
	// It sends the latest x.y.z of the readme's x.y; the card compares the site's full version with it.
	$site   = get_bloginfo( 'version' );
	$tested = ( '' !== $readme->tested && preg_match( '/^' . preg_quote( $readme->tested, '/' ) . '\.\d+$/', $site ) ) ? $site : $readme->tested;

	// The parser keeps only contributors who are users of this site: the readme's own line instead. A
	// profile's display name is its username until the owner sets one on WordPress.org; the listing's
	// avatar is the profile's Gravatar (d=monsterid), the preview's the site's default avatar.
	$contributors = array();
	if ( preg_match( '/^[ \t*-]*contributors[ \t*-]*:(.*)$/mi', $readme->raw_contents, $m ) ) {
		foreach ( array_filter( array_map( 'trim', explode( ',', $m[1] ) ) ) as $user ) {
			$user                  = ltrim( $user, '@' );
			$contributors[ $user ] = array(
				'profile'      => 'https://profiles.wordpress.org/' . rawurlencode( $user ) . '/',
				'avatar'       => get_avatar_url( 0 ),
				'display_name' => $user,
			);
		}
	}
	// The directory's author is the submitting WordPress.org account (its display name linked to its
	// profile), which docs/distribution.md names as the readme's Contributors account: the first one.
	// The header's Author and Author URI feed neither the card nor the sidebar.
	$submitter = array_key_first( $contributors );
	$author    = null === $submitter ? '' : sprintf( '<a href="%s">%s</a>', esc_url( $contributors[ $submitter ]['profile'] ), esc_html( $contributors[ $submitter ]['display_name'] ) );

	$sections = $readme->sections;
	if ( ! empty( $sections['faq'] ) ) {
		// The dialog allows no dl, dt or dd: the API sends each question as h4 and its answer as p.
		$sections['faq'] = str_replace(
			array( '<dl>', '</dl>', '</dt>', '<h3>', '</h3>', '<dd>', '</dd>' ),
			array( '', '', '</h4>', '', '', '<p>', '</p>' ),
			preg_replace( '/<dt\b[^>]*>/', '<h4>', $sections['faq'] )
		);
	}

	// The images drive the screenshots, the readme gives their captions by number.
	$screenshots = array();
	foreach ( glob( $dir . '/screenshot-*.png' ) ?: array() as $file ) {
		if ( preg_match( '/^screenshot-(\d+)\.png$/', basename( $file ), $m ) ) {
			$screenshots[ (int) $m[1] ] = array(
				'src'     => $url . '/' . basename( $file ),
				'caption' => $readme->screenshots[ (int) $m[1] ] ?? '',
			);
		}
	}
	ksort( $screenshots );
	if ( $screenshots ) {
		$markup = '<ol>';
		foreach ( $screenshots as $shot ) {
			$markup .= $shot['caption']
				? sprintf( '<li><a href="%1$s"><img src="%1$s" alt="%2$s"></a><p>%3$s</p></li>', esc_attr( $shot['src'] ), esc_attr( $shot['caption'] ), $shot['caption'] )
				: sprintf( '<li><a href="%1$s"><img src="%1$s" alt=""></a></li>', esc_attr( $shot['src'] ) );
		}
		$sections['screenshots'] = $markup . '</ol>';
	}

	return array(
		'name'              => $readme->name,
		'slug'              => POSTQUALITY_PREVIEW_SLUG,
		'version'           => $header['Version'],
		'author'            => $author,
		'contributors'      => $contributors,
		'requires'          => $requires,
		'tested'            => $tested,
		'requires_php'      => $requires_php,
		'requires_plugins'  => array(),
		'rating'            => 0,
		'ratings'           => array(),
		'num_ratings'       => 0,
		'active_installs'   => 0,
		'last_updated'      => gmdate( 'Y-m-d g:ia \G\M\T' ),
		'added'             => gmdate( 'Y-m-d' ),
		'homepage'          => $header['PluginURI'],
		'sections'          => $sections,
		'short_description' => $readme->short_description,
		'download_link'     => 'https://downloads.wordpress.org/plugin/' . POSTQUALITY_PREVIEW_SLUG . '.' . $header['Version'] . '.zip',
		'screenshots'       => $screenshots,
		'banners'           => array(
			'low'  => $url . '/banner-772x250.png',
			'high' => $url . '/banner-1544x500.png',
		),
		'icons'             => array(
			'1x'  => $url . '/icon.svg',
			'svg' => $url . '/icon.svg',
		),
	);
}
