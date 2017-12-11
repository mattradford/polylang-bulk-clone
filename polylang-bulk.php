<?php
/*
	Plugin Name:    Polylang Bulk Clone
	Plugin URI:     https://github.com/mattradford/polylang-bulk-clone
	Version:        0.0.2
	Author:         Matt Radford
	Author URI:     https://mattrad.uk
	Description:    Clone all untranslated posts with a bulk action
	License:        GPLv2 or later
	License URI:    http://www.gnu.org/licenses/gpl-2.0.html
	Text Domain:    polylang-bulk-clone
 */

add_filter('bulk_actions-edit-post', 'mattrad_pll_bulk_actions');
add_filter('bulk_actions-edit-page', 'mattrad_pll_bulk_actions');
add_filter('handle_bulk_actions-edit-post', 'mattrad_pll_bulk_action_handler', 10, 3);
add_filter('handle_bulk_actions-edit-page', 'mattrad_pll_bulk_action_handler', 10, 3);
add_action('admin_notices', 'mattrad_pll_bulk_action_notices');

/*
 * Add the bulk action
 * 
 * Adds dropdown bulk action option to posts and pages only.
 */
function mattrad_pll_bulk_actions($bulk_array)
{

    $bulk_array['pll_clone_untranslated'] = __('Clone in all available languages', 'polylang-bulk-clone');
    return $bulk_array;

}

/*
 * Bulk action handler
 * 
 * Clones posts to all languages that do not have a translation.
 */
function mattrad_pll_bulk_action_handler($redirect, $doaction, $object_ids)
{

    /*
     * Remove query args
     */
    $redirect = remove_query_arg(array('pll_clone_untranslated'), $redirect);

    /*
     * Bulk action
     */
    if ($doaction == 'pll_clone_untranslated') {

        /*
         * Get default language
         */
        $default_lang = pll_default_language();
        $default_lang_array[] = $default_lang;

        /*
         * Get all languages
         */
        $args = [
            'fields' => 'slug'
        ];
        $languages = pll_languages_list($args);

        /*
         * Remove default language from list of new languages
         */
        $new_langs = array_diff($languages, $default_lang_array);

        /*
         * Interate through posts, cloning all taxonomies and meta data
         */
        foreach ($object_ids as $post_id) {

            $from_post = get_post($post_id);

            foreach ($new_langs as $new_lang) {

                $has_translation = pll_get_post($post_id, $new_lang);

                if ($has_translation) return;
                $new_post = clone $from_post;

                /*
                 * Prepare post
                 */
                $new_post->ID = null;
                $new_post->post_status = 'draft';
                $new_post->post_title = $new_post->post_title . '-' . strtoupper($new_lang);
                $new_post_id = wp_insert_post($new_post);

                /*
                 * Set language & translation relation
                 */
                pll_set_post_language($new_post_id, $new_lang);
                pll_save_post_translations(array(
                    pll_get_post_language($from_post->ID) => $from_post->ID,
                    $new_lang => $new_post_id
                ));
                
                /*
                 * Copy relevant extra data
                 */
                PLL()->sync->copy_taxonomies($from_post->ID, $new_post_id, $new_lang);
                PLL()->sync->copy_post_metas($from_post->ID, $new_post_id, $new_lang);
                wp_update_post($new_post_id);
            }

        }

        $redirect = add_query_arg(
            'pll_clone_untranslated_done',
            count($object_ids),
            $redirect
        );

    }

    return $redirect;

}


/*
 * Add admin notice
 */
function mattrad_pll_bulk_action_notices()
{

    if (!empty($_REQUEST['pll_clone_untranslated_done'])) {

        printf('<div id="message" class="updated notice is-dismissible"><p>' .
            _n(
            'Cloned %s untranslated post.',
            'Cloned %s untranslated posts.',
            intval($_REQUEST['pll_clone_untranslated_done'])
        ) . '</p></div>', intval($_REQUEST['pll_clone_untranslated_done']));

    }

}