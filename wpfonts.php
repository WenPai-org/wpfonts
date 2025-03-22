<?php
/*
Plugin Name: WP Fonts
Plugin URI: https://wenpai.org/plugins/wpfonts
Description: Add optimized system font stacks for Chinese, English, Japanese, and Korean to your WordPress Font Library.
Version: 1.1.0
Author: WPFonts.com
Author URI: https://wpfonts.com
License: GPLv2 or later
Text Domain: wpfonts
*/

if ( function_exists( 'wp_register_font_collection' ) ) {
    function wenpai_register_wp_fonts() {
        $categories = array(
            array(
                'slug' => 'sans-serif',
                'name' => __('Sans Serif', 'wpfonts' )
            ),
            array(
                'slug' => 'serif',
                'name' => __('Serif', 'wpfonts' )
            ),
            array(
                'slug' => 'monospace',
                'name' => __('Monospace', 'wpfonts' )
            ),
            array(
                'slug' => 'handwriting',
                'name' => __('Handwriting', 'wpfonts' )
            )
        );

        $font_collection_cn = array(
            'name'          => __('Chinese', 'wpfonts'),
            'description'   => __('Cross-platform Chinese system font stacks. Automatically adapt to Windows/macOS/Linux fonts.', 'wpfonts'),
            'categories'    => $categories,
            'font_families' => path_join(__DIR__, 'collection/chinese.json'),
        );
        wp_register_font_collection('wp-fonts-stacks-cn', $font_collection_cn);

        $font_collection_en = array(
            'name'          => __('English', 'wpfonts'),
            'description'   => __('Stacks of modern systems fonts, no font files needed. The look will vary on each system.', 'wpfonts'),
            'categories'    => $categories,
            'font_families' => path_join(__DIR__, 'collection/english.json'),
        );
        wp_register_font_collection('wp-fonts-stacks-en', $font_collection_en);

        $font_collection_jp = array(
            'name' => __('Japanese', 'wpfonts'),
            'description' => __('Japanese system font stacks with optimized kana rendering', 'wpfonts'),
            'categories' => $categories,
            'font_families' => path_join(__DIR__, 'collection/japanese.json'),
        );
        wp_register_font_collection('wp-fonts-stacks-jp', $font_collection_jp);

        $font_collection_kr = array(
            'name' => __('Korean', 'wpfonts'),
            'description' => __('Hangul-optimized font stacks for Korean content', 'wpfonts'),
            'categories' => $categories,
            'font_families' => path_join(__DIR__, 'collection/korean.json'),
        );
        wp_register_font_collection('wp-fonts-stacks-kr', $font_collection_kr);
    }

    add_action('init', 'wenpai_register_wp_fonts');
}
