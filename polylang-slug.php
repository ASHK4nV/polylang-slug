<?php
/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * Dashboard. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              http://example.com
 * @since             1.0.0
 * @package           Polylang_Slug
 *
 * @wordpress-plugin
 * Plugin Name:       Polylang Slug
 * Plugin URI:        https://github.com/grappler/polylang-slug
 * GitHub Plugin URI: https://github.com/grappler/polylang-slug
 * Description:       Allows same slug for multiple languages in Polylang
 * Version:           1.0.0
 * Author:            Ulrich Pogson
 * Author URI:        http://ulrich.pogson.ch/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       polylang-slug
 * Domain Path:       /languages
 */


// Built using code from: https://wordpress.org/support/topic/plugin-polylang-identical-page-names-in-different-languages?replies=8#post-2669927

// Check if Polylang_Base exists and if $polylang is the right object
if ( ! is_admin() && ! class_exists( 'PLL_Model' ) ) {
	return;
}

/**
 * Checks if the slug is unique within language.
 *
 * @since 1.0.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 * @global WP_Rewrite $wp_rewrite
 *
 * @param string $slug        The desired slug (post_name).
 * @param int    $post_ID     Post ID.
 * @param string $post_status No uniqueness checks are made if the post is still draft or pending.
 * @param string $post_type   Post type.
 * @param int    $post_parent Post parent ID.
 * @return string Unique slug for the post within language, based on $post_name (with a -1, -2, etc. suffix)
 */
function polylang_slug_unique_slug_in_language( $slug, $post_ID, $post_status, $post_type, $post_parent, $original_slug ){

	// Return slug if it was not changed
	if ( $original_slug === $slug ) {
		return $slug;
	}

	global $wpdb, $polylang;

	// Get language of a post
	$lang = $polylang->model->get_post_language( $post_ID );
	$options = get_option( 'polylang' );

	// return the slug if Polylang does not return post language or has incompatable redirect setting or is not translated post type
	if ( empty( $lang ) || 0 === $options['force_lang'] || ! $polylang->model->is_translated_post_type( $post_type ) ) {
		return $slug;
	}

	$join_clause  = $polylang->model->join_clause('post');
	$where_clause = $polylang->model->where_clause( $lang, 'post');

	// Polylang does not translate attachements - skip if it is one
	if ( 'attachment' == $post_type ) {

		// Attachment slugs must be unique across all types.
		$check_sql = "SELECT post_name FROM $wpdb->posts $join_clause WHERE post_name = %s AND ID != %d $where_clause LIMIT 1";
		$post_name_check = $wpdb->get_var( $wpdb->prepare( $check_sql, $original_slug, $post_ID ) );

	} elseif ( is_post_type_hierarchical( $post_type ) ) {

		// Page slugs must be unique within their own trees. Pages are in a separate
		// namespace than posts so page slugs are allowed to overlap post slugs.
		$check_sql = "SELECT ID FROM $wpdb->posts $join_clause WHERE post_name = %s AND post_type IN ( %s, 'attachment' ) AND ID != %d AND post_parent = %d $where_clause LIMIT 1";
		$post_name_check = $wpdb->get_var( $wpdb->prepare( $check_sql, $original_slug, $post_type, $post_ID, $post_parent ) );

	} else {

		// Post slugs must be unique across all posts.
		$check_sql = "SELECT post_name FROM $wpdb->posts $join_clause WHERE post_name = %s AND post_type = %s AND ID != %d $where_clause LIMIT 1";
		$post_name_check = $wpdb->get_var( $wpdb->prepare( $check_sql, $original_slug, $post_type, $post_ID ) );

	}

	if ( ! $post_name_check ) {
		return $original_slug;
	} else {
		return $slug;
	}

}
add_filter( 'wp_unique_post_slug', 'polylang_slug_unique_slug_in_language', 10, 6 );
