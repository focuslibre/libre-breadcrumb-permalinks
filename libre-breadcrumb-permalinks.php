<?php
/**
 * Plugin Name:       Libre Breadcrumb Permalinks
 * Plugin URI:        https://github.com/focuslibre/libre-breadcrumb-permalinks
 * Description:       Removes taxonomy base prefixes and builds breadcrumb-style permalinks from term hierarchies.
 * Version:           1.0.0
 * Requires at least: 4.5
 * Requires PHP:      5.4
 * Author:            Luc André
 * Author URI:        https://focuslibre.fr.eu.org
 * License:           GPL v3
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Libre_Breadcrumb_Permalinks {

	/**
	 * Taxonomy names managed by this plugin.
	 *
	 * @var array
	 */
	private static $taxonomies = [];

	/**
	 * CPT name => taxonomy name associations, used to build CPT permalinks.
	 *
	 * @var array
	 */
	private static $cpt = [];

	/**
	 * Rewrite-rules closures indexed by taxonomy name.
	 * Stored so each closure can be targeted precisely by remove_filter() on deactivation.
	 *
	 * @var array
	 */
	private static $rewrite_callbacks = [];

	/**
	 * Reads config via the 'lbp_config' filter and registers all hooks.
	 * Falls back to auto-detecting all public custom taxonomies, including category and post_tag, and CPTs.
	 *
	 * Filter format: [ 'taxonomy_name', 'cpt_name' => 'taxonomy_name', ... ]
	 * Plain values strip the base from that taxonomy. String keys also associate
	 * the CPT with the taxonomy for breadcrumb permalink building.
	 */
	public static function init() {
		$config = apply_filters( 'lbp_config', [] );

		if ( $config ) {
			foreach ( $config as $key => $tax ) {
				if ( is_string( $key ) ) {
					// String key: 'cpt_name' => 'taxonomy_name'.
					self::$cpt[ $key ] = $tax;
					add_filter( "{$key}_rewrite_rules", '__return_empty_array', 327 );
				}
				self::$taxonomies[] = $tax;
				self::register_hooks( $tax );
			}
		} else {
			// Auto-detection: scan all public non-builtin post types and taxonomies.
			$args        = [ 'public' => true, '_builtin' => false, 'publicly_queryable' => true ];
			$cpt_names   = get_post_types( $args, 'names' );
			$tax_objects = get_taxonomies( $args, 'objects' );

			// Associate each CPT with its first matching taxonomy, then discard CPT-own rules.
			foreach ( $cpt_names as $post_type ) {
				foreach ( $tax_objects as $tax => $tax_obj ) {
					if ( in_array( $post_type, (array) $tax_obj->object_type, true ) ) {
						self::$cpt[ $post_type ] = $tax;
						add_filter( "{$post_type}_rewrite_rules", '__return_empty_array', 327 );
						continue 2;
					}
				}
			}

			// Handle built-in taxonomies alongside all discovered custom taxonomies.
			// Note: array_keys() is required here because get_taxonomies( ..., 'objects' )
			// returns an associative array of WP_Taxonomy objects, not an array of names.
			self::$taxonomies = array_merge( [ 'category', 'post_tag' ], array_keys( $tax_objects ) );
			foreach ( self::$taxonomies as $tax ) {
				self::register_hooks( $tax );
			}
		}

		add_filter( 'term_link', [ __CLASS__, 'term_link' ], 10, 3 );
		if ( self::$cpt ) {
			add_filter( 'post_type_link', [ __CLASS__, 'post_link' ], 10, 3 );
		}
	}

	/**
	 * Registers flush and rewrite-rules hooks for a single taxonomy.
	 *
	 * @param string $tax Taxonomy name.
	 */
	private static function register_hooks( $tax ) {
		add_action( "saved_{$tax}",  [ __CLASS__, 'flush' ] );
		add_action( "delete_{$tax}", [ __CLASS__, 'flush' ] );

		$callback = static function ( $_ ) use ( $tax ) {
			return self::build_rewrite_rules( $tax );
		};
		self::$rewrite_callbacks[ $tax ] = $callback;
		add_filter( "{$tax}_rewrite_rules", $callback, 327 );
	}

	/**
	 * Called on plugin activation.
	 *
	 * Bootstraps the plugin directly (the 'init' action may have already fired
	 * when the activation hook runs) then flushes rewrite rules.
	 */
	public static function activation() {
		self::init();
		self::flush();
	}

	/**
	 * Called on plugin deactivation.
	 *
	 * Removes all rewrite-rules hooks registered by this plugin, then flushes
	 * so WordPress regenerates clean default rules.
	 */
	public static function deactivation() {
		foreach ( self::$taxonomies as $tax ) {
			remove_filter( "{$tax}_rewrite_rules", self::$rewrite_callbacks[ $tax ], 327 );
		}
		foreach ( array_keys( self::$cpt ) as $post_type ) {
			remove_filter( "{$post_type}_rewrite_rules", '__return_empty_array', 327 );
		}
		self::flush();
	}

	/**
	 * Flushes rewrite rules to the database only (does not rewrite .htaccess).
	 */
	public static function flush() {
		global $wp_rewrite;
		$wp_rewrite->flush_rules( false );
	}

	/**
	 * Filters term_link to produce a base-free URL for managed taxonomies.
	 *
	 * @param string  $termlink Original term URL.
	 * @param WP_Term $term     Term object.
	 * @param string  $taxonomy Taxonomy name.
	 * @return string Modified URL, or the original if the taxonomy is not managed.
	 */
	public static function term_link( $termlink, $term, $taxonomy ) {
		return in_array( $taxonomy, self::$taxonomies, true )
			? self::build_term_url( $term, $taxonomy )
			: $termlink;
	}

	/**
	 * Filters post_type_link to build CPT URLs as {term-path}/{post-slug}.
	 *
	 * @param string  $post_link Original post URL.
	 * @param WP_Post $post      Post object.
	 * @param bool    $leavename Whether to keep the %postname% placeholder (sample permalink).
	 * @return string Modified URL, or original if the post type is not managed or has no term.
	 */
	public static function post_link( $post_link, $post, $leavename ) {
		$post_type = $post->post_type;
		if ( ! isset( self::$cpt[ $post_type ] ) ) return $post_link;

		$tax   = self::$cpt[ $post_type ];
		$terms = wp_get_object_terms( $post->ID, $tax );

		if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
			$slug = $leavename ? '%postname%' : $post->post_name;
			return self::build_term_url( $terms[0], $tax, $slug );
		}

		return $post_link;
	}

	/**
	 * Builds a base-free permalink for a term, optionally appending a post slug.
	 *
	 * Called by term_link() (no slug — term archive URL) and post_link() (with slug).
	 *
	 * @param WP_Term $term     Term object.
	 * @param string  $taxonomy Taxonomy name.
	 * @param string  $slug     Optional slug to append: post name, attachment sub-path,
	 *                          or %postname% placeholder.
	 * @return string Full permalink without the taxonomy base prefix.
	 */
	private static function build_term_url( $term, $taxonomy, $slug = '' ) {
		$tax_obj      = get_taxonomy( $taxonomy );
		$hierarchical = ! empty( $tax_obj->rewrite['hierarchical'] );
		$term_path    = $hierarchical ? self::build_term_path( $term, $term->slug, $taxonomy ) : $term->slug;
		return user_trailingslashit( home_url( $term_path . '/' . $slug ) );
	}

	/**
	 * Generates slug-based rewrite rules for a taxonomy (and its associated CPT, if any).
	 *
	 * Rules are term-specific rather than catch-all, to avoid conflicts with other
	 * permalink structures. A rewrite flush is needed whenever terms are added, edited,
	 * or deleted (handled via the saved_{$tax} and delete_{$tax} action hooks).
	 *
	 * @param string $tax Taxonomy name.
	 * @return array Associative array of regex pattern => rewrite query-string rules.
	 */
	private static function build_rewrite_rules( $tax ) {
		$tax_obj   = get_taxonomy( $tax );
		$query_var = $tax_obj->query_var;
		if ( ! $query_var ) return [];

		global $wp_rewrite;
		$page         = $wp_rewrite->pagination_base;
		$rules        = $paths = [];
		$hierarchical = ! empty( $tax_obj->rewrite['hierarchical'] );
		$terms        = get_terms( [ 'taxonomy' => $tax, 'hide_empty' => false ] );
		if ( is_wp_error( $terms ) ) return [];

		foreach ( $terms as $term ) {
			$slug    = $term->slug;
			$paths[] = $p = $hierarchical ? self::build_term_path( $term, $slug, $tax ) : $slug;

			$rules[ "$p/(?:feed/)?(rss2|rss|atom|rdf|feed)/?$" ] = "index.php?$query_var=$slug&feed=\$matches[1]";
			$rules[ "$p/embed/?$" ]                               = "index.php?$query_var=$slug&embed=true";
			$rules[ "$p/$page/(\d+)/?$" ]                         = "index.php?$query_var=$slug&paged=\$matches[1]";
			$rules[ "$p/?$" ]                                     = "index.php?$query_var=$slug";
		}

		// Add post rules nested under each term path.
		if ( $post_type = array_search( $tax, self::$cpt, true ) ) {
			// Sort longest paths first so more-specific (hierarchical) rules take precedence.
			if ( $hierarchical ) arsort( $paths );
			foreach ( $paths as $p ) {
				$rules[ "$p/([^/]+)/?$" ] = "index.php?post_type=$post_type&name=\$matches[1]";
			}
		}

		return $rules;
	}

	/**
	 * Recursively builds the full hierarchical slug path for a term.
	 *
	 * @param WP_Term $term Term object to start from.
	 * @param string  $path Accumulated path (initialise with $term->slug).
	 * @param string  $tax  Taxonomy name.
	 * @return string Full slug path, e.g. "grandparent/parent/child".
	 */
	private static function build_term_path( $term, $path, $tax ) {
		while ( ! empty( $parent = $term->parent ) ) {
			$term = get_term( $parent, $tax );
			$path = $term->slug . '/' . $path;
		}
		return $path;
	}
}

add_action( 'init', [ 'Libre_Breadcrumb_Permalinks', 'init' ], 99 );
register_activation_hook( __FILE__, [ 'Libre_Breadcrumb_Permalinks', 'activation' ] );
register_deactivation_hook( __FILE__, [ 'Libre_Breadcrumb_Permalinks', 'deactivation' ] );
