# Libre Breadcrumb Permalinks

A WordPress plugin that removes taxonomy base prefixes and builds breadcrumb-style permalinks from term hierarchies.

## How it works

By default, WordPress adds a base prefix to taxonomy URLs — `/category/`, `/tag/`, or any custom taxonomy base. This plugin removes those prefixes and replaces them with breadcrumb-style paths built directly from the term hierarchy:

| Before | After |
|--------|-------|
| `/category/news/` | `/news/` |
| `/category/news/local/` | `/news/local/` |
| `/category/news/local/my-post/` | `/news/local/my-post/` |

For non-hierarchical taxonomies, only the term slug is used:

| Before | After |
|--------|-------|
| `/tag/wordpress/` | `/wordpress/` |

The plugin works at the permalink and rewrite layer and does not modify stored slugs or post content.

## Installation

### WordPress

1. Download the latest release from the [releases page](https://github.com/focuslibre/libre-breadcrumb-permalinks/releases).
2. Extract the `libre-breadcrumb-permalinks` directory into `/wp-content/plugins/`.
3. Activate the plugin from the WordPress admin panel.

### Git

```bash
git clone https://github.com/focuslibre/libre-breadcrumb-permalinks.git wp-content/plugins/libre-breadcrumb-permalinks
```

Then activate the plugin from the WordPress admin panel.

## Configuration

By default, the plugin auto-detects all public taxonomies (including `category` and `post_tag`) and CPTs, associating each CPT with its first matching taxonomy.

For fine-grained control, use the `lbp_config` filter:

```php
add_filter( 'lbp_config', function() {
    return [
        'category',                 // strip base from this taxonomy
        'post_tag',                 // strip base from this taxonomy
        'my_cpt' => 'my_taxonomy',  // strip base + build CPT permalinks from term path
    ];
} );
```

Plain values declare a taxonomy whose base will be stripped. String keys declare a CPT => taxonomy association — the taxonomy base is also stripped, and the CPT permalinks are built from the term path.

## How rewrite rules are generated

Rather than using catch-all patterns, the plugin generates specific rewrite rules for each term. This avoids conflicts with other permalink structures but means rules must be regenerated whenever terms are added, renamed, or deleted. This is handled automatically via the `saved_{$taxonomy}` and `deleted_{$taxonomy}` action hooks.

## Requirements

- WordPress 4.5+
- PHP 7.0+

## License

[GPL v3](https://www.gnu.org/licenses/gpl-3.0.html)
