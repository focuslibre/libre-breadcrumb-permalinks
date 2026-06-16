=== Libre Breadcrumb Permalinks ===
Contributors: focuslibre
Tags: permalink, taxonomy, breadcrumb, url, cpt
Requires at least: 4.5
Tested up to: 7.0
Requires PHP: 7.0
Stable tag: 1.0.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Removes taxonomy base prefixes and builds breadcrumb-style permalinks from term hierarchies.

== Description ==

By default, WordPress adds a base prefix to taxonomy URLs — `/category/`, `/tag/`, or any custom taxonomy base. This plugin removes those prefixes and replaces them with breadcrumb-style paths built directly from the term hierarchy, giving clean, readable URLs that reflect the content structure:

`/parent-term/child-term/post-slug/`

For custom post types associated with a taxonomy, the full term path is used as the permalink base. For non-hierarchical taxonomies, only the term slug is used.

It supports:

* Hierarchical and non-hierarchical taxonomies
* Custom post type permalinks built from associated taxonomy term paths
* Automatic detection of public taxonomies (including `category` and `post_tag`) and CPTs
* Configuration via filter for fine-grained control

The plugin works at the permalink and rewrite layer and does not modify stored slugs or post content.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin

The plugin works out of the box and has no administration interface. For custom configuration, use the `lbp_config` filter (see FAQ).

== Frequently Asked Questions ==

= Does it create database tables or store plugin data? =

No. The plugin only generates rewrite rules and filters URLs. Nothing is stored beyond WordPress's own rewrite rules cache.

= Can I choose which taxonomies and CPTs are affected? =

Yes, via the `lbp_config` filter. Plain values declare a taxonomy whose base will be stripped. String keys declare a CPT => taxonomy association — the taxonomy base is also stripped, and the CPT permalinks are built from the term path.

`add_filter( 'lbp_config', function() {
    return [
        'category',                 // strip base from this taxonomy
        'post_tag',                 // strip base from this taxonomy
        'my_cpt' => 'my_taxonomy',  // strip base + build CPT permalinks from term path
    ];
} );`

Without this filter, the plugin auto-detects all public custom taxonomies, including `category` and `post_tag`, and CPTs.

== Changelog ==

= 1.0.0 =
* Initial release
