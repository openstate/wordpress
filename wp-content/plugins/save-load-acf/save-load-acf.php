<?php
/**
* @package save-load-acf
* @version 1.0.0
* 
* Plugin Name: Save Load ACF
* Description: A custom Open State Foundation plugin to save and load ACF (Advanced Custom Fields) settings in JSON in order to add this information to the Git repository of the theme.
* Author: Open State Foundation developers
* Version: 1.0.0
* Author URI: https://openstate.eu/
**/

/**
* Place ACF JSON in acf-field-groups directory
*/
add_filter('acf/settings/save_json', function($path) {
    return get_stylesheet_directory() . '/acf-field-groups';
});

add_filter('acf/settings/load_json', function($paths) {
    unset($paths[0]);
    $paths[] = get_stylesheet_directory() . '/acf-field-groups';
    return $paths;
});
?>
