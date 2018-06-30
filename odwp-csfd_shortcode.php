<?php
/**
 * Plugin Name: ČSFD.cz shortcode
 * Description: Plugin, který umožňuje snadno vložit shortcode s URL na <a href="https://www.csfd.cz/">ČSFD.cz</a>, který pak bude zobrazen jako snippet s informacemi o filmu na vašich stránkách. Od verze 0.3.0 pak přidává i vlastní typ příspěvků, kdy je možno vytvořit nový příspěvek z URL odkazující na <a href="https://www.csfd.cz/">ČSFD.cz</a>.
 * Version: 0.3.0
 * Author: Ondřej Doněk
 * Author URI: https://ondrejd.com/
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.en.html
 * Requires at least: 4.9
 * Requires PHP: 5.6
 * Tested up to: 4.9.6
 * Tags: users, credits
 * Text Domain: odwpcs
 * Domain Path: /languages/
 *
 * WordPress plugin that brings shortcode that displays movie info grabbed from CSFD.cz.
 * 
 * Copyright (C) 2018 Ondřej Doněk
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Ondřej Doněk <ondrejd@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-3.0.en.html GNU General Public License 3.0
 * @link https://bitbucket.com/ondrejd/odwp-csfd_shortcode for the canonical source repository
 * @package odwp-csfd_shortcode
 * @since 0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

defined( 'ODWPCS_CACHE_DIR' ) || define( 'ODWPCS_CACHE_DIR', WP_CONTENT_DIR . '/uploads/odwpcs-cache' );
defined( 'ODWPCS_CACHE_TIME' ) || define( 'ODWPCS_CACHE_TIME', 60 * 60 * 24 * 5 );
defined( 'ODWPCS_CPT' ) || define( 'ODWPCS_CPT', 'csfd_item' );
defined( 'ODWPCS_OPT_ENABLE_CPT' ) || define( 'ODWPCS_OPT_ENABLE_CPT', 'odwpcs_options_enable_cpt' );
defined( 'ODWPCS_NONCE' ) || define( 'ODWPCS_NONCE', 'odwpcs-nonce' );

/**
 * @param \DOMElement $movie_elm
 * @return array
 * @since 0.1.0
 */
function odwpcs_get_csfd_movie_title( \DOMElement $movie_elm ) {
    $title = '';

    $h1s = $movie_elm->getElementsByTagName( 'h1' );
    if ( $h1s->length > 0 ) {
        $title = trim( $h1s->item( 0 )->nodeValue );
    }

    return $title;
}

/**
 * @param \DOMElement $movie_elm
 * @return array
 * @since 0.1.0
 */
function odwpcs_get_csfd_movie_category( \DOMElement $movie_elm ) {
    $cat = '';

    $paras = $movie_elm->getElementsByTagName( 'p' );
    for ( $i = 0; $i < $paras->length; $i++ ) {
        $para = $paras->item( 0 );
        if ( $para->hasAttribute( 'class' ) && $para->getAttribute( 'class' ) == 'genre' ) {
            $cat = $para->nodeValue;
        }
    }

    return $cat;
}

/**
 * @param \DOMDocument $dom
 * @return array
 * @since 0.1.0
 */
function odwpcs_get_csfd_movie_plot( \DOMDocument $dom ) {
    $desc = '';

    $plots = $dom->getElementById( 'plots' );
    if ( ( $plots instanceof \DOMElement ) ) {
        if ( $plots->hasChildNodes() ) {
            foreach ( $plots->childNodes as $child ) {
                if ( ( $child instanceof \DOMElement ) ) {
                    if ( $child->hasAttribute( 'class' ) && $child->getAttribute( 'class' ) == 'content' ) {
                        $uls = $child->getElementsByTagName( 'ul' );
                        if ( $uls->length > 0 ) {
                            $lis = $uls->item( 0 )->getElementsByTagName( 'li' );
                            if ( $lis->length > 0 ) {
                                $desc = trim( $lis->item( 0 )->nodeValue );
                            }
                        }
                    }
                }
            }
        }
    }

    return $desc;
}

/**
 * @param \DOMDocument $dom
 * @return array
 * @since 0.1.0
 */
function odwpcs_get_csfd_movie_image( \DOMDocument $dom ) {
    $img = '';
    $poster = $dom->getElementById( 'poster' );

    if ( ( $poster instanceof \DOMElement ) ) {
        foreach ( $poster->childNodes as $child ) {
            if ( strtolower( $child->nodeName ) == 'img' ) {
                $poster_url = $child->getAttribute( 'src' );
                $poster_url_parts = explode( '?', $poster_url );
                $img = ( is_array( $poster_url_parts ) && count( $poster_url_parts ) > 0 ) ? $poster_url_parts[0] : $poster_url;
                break;
            }
        }
    }

    return $img;
}

/**
 * @param \DOMDocument $dom
 * @return array
 * @since 0.1.0
 */
function odwpcs_get_csfd_movie_video( \DOMDocument $dom ) {
    $video_html = '';

    $player = $dom->getElementById( 'videoPlayer1' );
    if ( ( $player instanceof \DOMElement ) ) {
        $videos = $player->getElementsByTagName( 'video' );

        if ( $videos->length > 0 ) {
            $video_elm = $videos->item( 0 );
            $video_html .= '<video';

            for ( $i=0; $i<$video_elm->attributes->length; $i++ ) {
                $attr = $video_elm->attributes->item( $i );
                $video_html .= ' ' . $attr->name . '="' . $attr->value . '"';
            }

            $video_html .= '><source';

            foreach ( $video_elm->childNodes as $child ) {
                if ( ( $child instanceof \DOMElement ) ) {
                    for ( $i=0; $i<$child->attributes->length; $i++ ) {
                        $child_attr = $child->attributes->item( $i );
                        $video_html .= ' ' . $child_attr->name . '="' . $child_attr->value . '"';
                    }
                }
            }

            $video_html .= '>' . __( 'Váš prohlížeč nepodporuje HTML5 video.', 'odwpcs' ) . '</video>';
        }
    }

    return $video_html;
}

/**
 * @param \DOMDocument $dom
 * @return array
 * @since 0.1.0
 */
function odwpcs_get_csfd_movie_details( \DOMDocument $dom ) {
    $movie_elm = $dom->getElementById( 'pg-web-film' );
    $movie_arr = array(
        'category' => '',
        'description' => '',
        'image' => '',
        'title' => '',
        'video' => '',
    );

    if ( ( $movie_elm instanceof \DOMElement ) ) {
        $movie_arr['title'] = odwpcs_get_csfd_movie_title( $movie_elm );
        $movie_arr['category'] = odwpcs_get_csfd_movie_category( $movie_elm );
        $movie_arr['description'] = odwpcs_get_csfd_movie_plot( $dom );
        $movie_arr['image'] = odwpcs_get_csfd_movie_image( $dom );
        $movie_arr['video'] = odwpcs_get_csfd_movie_video( $dom );
    }

    return $movie_arr;
}

/**
 * Render shortcode error message.
 * @param string $msg
 * @return string
 * @since 0.1.0
 */
function odwpcs_render_shortcode_error( $msg ) {
    return '<div class="odwpcs-shortcode odwpcs-shortcode-error">' . $msg . '</div>';
}

/**
 * Render shortcode with given movie details.
 * @param array $movie
 * @return string
 * @since 0.1.0
 */
function odwpcs_render_shortcode( $movie ) {
    $out = ''
        . '<div class="odwpcs-shortcode">'
        .   '<div class="odwpcs-shortcode-header">'
        .     '<h3>' . $movie['title'] . '</h3>'
        .   '</div>'
        .       '<div class="odwpcs-shortcode-category">'
        .         '<span class="label">' . __( 'Kategorie:', 'odwpcs' ) . '</span> '
        .         '<span class="value">' . $movie['category'] . '</span>'
        .       '</div>'
        .   '<div class="odwpcs-shortcode-content">'
        .       '<div class="odwpcs-shortcode-content-description">'
        .         '<p>' . $movie['description'] . '</p>'
        .       '</div>';
    
    if ( ! empty( $movie['image'] ) ) {
        $out .= ''
        .     '<div class="odwpcs-shortcode-content-image">'
        .       '<img src="' . $movie['image'] . '" />'
        .     '</div>';
    }
    
    if ( ! empty( $movie['video'] ) ) {
        $out .= ''
        .     '<div class="odwpcs-shortcode-content-video">' . $movie['video'] . '</div>';
    }

    $out .= ''
        .   '</div>'
        .   '<div class="odwpcs-shortcode-footer">'
        .     '<p>' . __( 'Data jsou převzatá ze serveru ČSFD.cz', 'odwpcs' ) . '</p>'
        .   '</div>'
        . '</div>';

    return $out;
}

/**
 * Load HTML from given ČSFD.cz URL and returns array with movie details.
 * @param string $url
 * @return array
 * @since 0.3.0
 */
function odwpcs_load_csfd_url( $url ) {
    $html_gzip = file_get_contents( $url );
    $html_raw = gzdecode( $html_gzip );
    libxml_use_internal_errors( true );

    try {
        $html_dom = new DOMDocument();

        if ( ! $html_dom->loadHTML( $html_raw ) ) {
            foreach ( libxml_get_errors() as $err ) {
                //...
            }
        }

        $movie_details = odwpcs_get_csfd_movie_details( $html_dom );
        $movie_details['url'] = $url;
    } catch ( \Exception $e ) {
        //...
    }

    libxml_clear_errors();

    return $movie_details;
}

/**
 * Create new shortcode `[csfd url="https://www.csfd.cz/film/426009-deadpool-2/prehled/"]`.
 * @param array $atts
 * @return string
 * @since 0.1.0
 */
function odwpcs_add_shortcode( $atts ) {
    $a = shortcode_atts( array(
        'url' => '',
    ), $atts );

    if ( filter_var( $a['url'], FILTER_VALIDATE_URL ) === false ) {
        return odwpcs_render_shortcode_error( __( 'Nebylo poskytnuto správné ČSFD.cz URL!', 'odwpcs' ) );
    }

    $cache = odwpcs_get_cache_item( $a['url'] );

    if ( ! empty( $cache ) ) {
        return $cache;
    }

    $movie_details = odwpcs_load_csfd_url( $a['url'] );

    if ( ! array_key_exists( 'title', $movie_details ) || ! array_key_exists( 'url', $movie_details ) ) {
        return odwpcs_render_shortcode_error( __( 'Při parsování dat z webu ČSFD.cz došlo k chybě!', 'odwpcs' ) );
    }

    $html = odwpcs_render_shortcode( $movie_details );
    odwpcs_set_cache_item( $a['url'], $html );

    return $html;
}

/**
 * Register our public CSS.
 * @return void
 * @since 0.1.0
 */
function odwpcs_register_styles() {
    wp_register_style( 'odwp-csfd_shortcode', plugin_dir_url( __FILE__ ) . 'assets/css/public.css', array() );
    wp_enqueue_style( 'odwp-csfd_shortcode' );
}

/**
 * @return void
 * @since 0.2.0
 */
function odwpcs_init() {

    // Create cache directory if required
    if ( ! file_exists( ODWPCS_CACHE_DIR ) ) {
        wp_mkdir_p( ODWPCS_CACHE_DIR );
    }
}

/**
 * @param string $key
 * @return string
 * @since 0.2.0
 */
function odwpcs_get_cache_item_path( $key ) {
    return ODWPCS_CACHE_DIR . '/' . md5( $key ) . '.html';
}

/**
 * @param string $key
 * @return string
 * @since 0.2.0
 */
function odwpcs_get_cache_item( $key ) {
    $path = odwpcs_get_cache_item_path( $key );

    // Check if cache item exists
    if ( ! file_exists( $path ) ) {
        return '';
    }

    // Check if is not older then certain time
    if ( time() - filemtime( $path ) >= ODWPCS_CACHE_TIME ) {
        return '';
    }

    return file_get_contents( $path );
}

/**
 * @param string $key
 * @param string $data
 * @return boolean
 * @since 0.2.0
 */
function odwpcs_set_cache_item( $key, $data ) {
    return ( file_put_contents( odwpcs_get_cache_item_path( $key ), $data ) > 0 );
}

/**
 * Return plugin's icon.
 * @return string
 * @since 0.3.0
 * @uses plugins_url()
 */
function odwpcs_get_csfd_icon() {
    return plugins_url( 'assets/images/csfd-icon-20x20.png', __FILE__ );
}

/**
 * Register options of the plugin.
 * @return void
 * @since 0.3.0
 * @uses register_setting()
 * @uses add_settings_section()
 * @uses add_settings_field()
 */
function odwpcs_options_register() {
    register_setting( 'writing', ODWPCS_OPT_ENABLE_CPT );

    // TODO Move image & inline styles into `assets/css/admin.css`!
    add_settings_section(
        'odwpcs_settings_section',
        sprintf(
            __( 'Plugin %1$sČSFD.cz%2$s shortcode%3$s', 'odwpcs' ),
            '<em><img src="' . odwpcs_get_csfd_icon() . '" style="position:relative; top:5px;" /> ' .
            '<a href="https://www.csfd.cz/" target="_blank">', '</a>', '</em>'
        ),
        'odwpcs_settings_section_cb',
        'writing'
    );

    add_settings_field(
        'odwpcs_settings_field',
        __( 'Nový typ příspěvků', 'odwpcs' ),
        'odwpcs_settings_field_cb',
        'writing',
        'odwpcs_settings_section'
    );
}

/**
 * @return void
 * @since 0.3.0
 */
function odwpcs_settings_section_cb() {
?>
    <p><?php printf(
        __( 'Zde můžete povolit či zakázat speciální typ příspěvků, které můžete vytvořit vložením %3$sURL%4$s na detail filmu či jiných položek na %1$sČSFD.cz%2$s. Pokud jej zakážete, stále budete mít k dispozici <em>shortcode</em>, který budete moci vložit do vašich příspěvků či stránek.', 'odwpcs' ),
        '<a href="https://www.csfd.cz/" target="_blank">',
        '</a>', '<em>', '</em>'
    ); ?></p>
<?php
}

/**
 * @return void
 * @since 0.3.0
 * @uses get_option()
 * @uses checked()
 */
function odwpcs_settings_field_cb() {
    $enable_cpt = get_option( ODWPCS_OPT_ENABLE_CPT );
?>
    <label for="<?php echo ODWPCS_OPT_ENABLE_CPT; ?>">
        <input id="<?php echo ODWPCS_OPT_ENABLE_CPT; ?>" name="<?php echo ODWPCS_OPT_ENABLE_CPT; ?>" type="checkbox" value="1" <?php checked( $enable_cpt, '1' ); ?> />
        <?php printf( __( 'Povolit typ příspěvků %1$scsfd_item%2$s?', 'odwpcs' ), '<code>', '</code>' ); ?>
    </label>
<?php
}

/**
 * Initialize CPT "csfd_item".
 * @return void
 * @since 0.3.0
 * @uses odwpcs_init_cpt()
 */
function odwpcs_init_cpt() {
    register_post_type( ODWPCS_CPT, array(
        'labels' => array(
            'name' => __( 'Záznamy ČSFD.cz' ),
            'singular_name' => __( 'Záznam ČSFD.cz' )
        ),
        'public' => true,
        'has_archive' => true,
        'position' => 21,
        'menu_icon' => odwpcs_get_csfd_icon(),
        'supports' => array( 'title' ),
    ) );
}

/**
 * @return void
 * @since 0.3.0
 * @uses flush_rewrite_rules()
 */
function odwpcs_rewrite_flush() {
    odwpcs_init_cpt();
    flush_rewrite_rules();
}

/**
 * @param string $contextual_help
 * @param string $screen_id
 * @param WP_Screen $screen
 * @return string
 * @since 0.3.0
 */
function odwpcs_add_help_text( $contextual_help, $screen_id, $screen ) {
    if ( 'csfd_item' == $screen->id ) {
        $contextual_help =
            '<p>' . __( 'Ma této stránce můžete vytvořit nový ČSFD.cz záznam.', 'odwpcs' ) . '</p>';
    } elseif ( 'edit-csfd_item' == $screen->id ) {
        $contextual_help =
            '<p>' . __( 'Tabulka na této stránce zobrazuje již vytvořené ČSFD.cz záznamy.', 'odwpcs' ) . '</p>' ;
    }
    return $contextual_help;
}

/**
 * Add meta box with CSFD.cz URL for CPT "csfd_item".
 * @return void
 * @since 0.3.0
 * @uses add_meta_box()
 */
function odwpcs_add_csfd_url_metabox() {
    add_meta_box(
        'odwpcs-csfd_url',
        __( 'ČSFD.cz URL', 'odwpcs' ),
        'odwpcs_render_csfd_url_metabox',
        ODWPCS_CPT,
        'normal',
        'high'
    );
}

/**
 * Render our CSFD.cz URL meta box.
 * @param WP_Post $post
 * @return void
 * @since 0.3.0
 * @uses wp_nonce_field()
 */
function odwpcs_render_csfd_url_metabox( $post ) {
    $csfd_url = get_post_meta( $post->ID, 'csfd_item_url', true );

    wp_nonce_field( basename( __FILE__ ), ODWPCS_NONCE );
?>
    <table class="form-table">
        <tr>
            <th scope="row">
                <label for="odwpcs-csfd_url"><?php _e( 'Odkaz na ČSFD.cz', 'odwpcs' ); ?></label>
            </th>
            <td>
                <input class="regular-text" id="odwpcs-csfd_url" name="odwpcs-csfd_url" style="width:100%;" tabindex="1" type="url" value="<?php echo $csfd_url; ?>">
                <p class="description"><?php printf(
                    __( 'Zadejte URL na zdrojovou stránku na serveru %1$sČSFD.cz%2$s a klikněte na tlačítko %3$sPublikovat%4$s nebo %3$sUložit koncept%4$s.', 'odwpcs' ),
                    '<a href="https://www.csfd.cz/" target="_blank">', '</a>', '<strong>', '</strong>'
                ); ?></p>
            </td>
        </tr>
    </table>
    <pre><?php var_dump( $csfd_url ); ?></pre>
<?php

    if ( ! empty( $csfd_url ) ) {
        $movie_details = odwpcs_load_csfd_url( $csfd_url );

        if ( ! array_key_exists( 'title', $movie_details ) || ! array_key_exists( 'url', $movie_details ) ) {
            echo '<p style="color:#f30; font-weight:bold;">' . __( 'Při parsování dat z webu ČSFD.cz došlo k chybě!', 'odwpcs' ) . '</p>';
        } else {
            echo odwpcs_render_shortcode( $movie_details );
        }
    }
}

/**
 * Save value inserted using CSFD.cz URL meta box.
 * @global wpdb $wpdb
 * @param integer $post_id
 * @return string|void
 * @since 0.3.0
 * @uses add_action()
 * @uses current_user_can()
 * @uses is_wp_error()
 * @uses sanitize_title()
 * @uses update_post_meta()
 * @uses wp_is_post_autosave()
 * @uses wp_is_post_revision()
 * @uses wp_verify_nonce()
 * @uses wp_update_post()
 */
function odwpcs_save_csfd_url_metabox( $post_id ) {

    // verify nonce
    if ( ! isset( $_POST[ODWPCS_NONCE] ) || ! wp_verify_nonce( $_POST[ODWPCS_NONCE], basename( __FILE__ ) ) ) {
        return 'nonce not verified';
    }

    // check autosave
    if ( wp_is_post_autosave( $post_id ) ) {
        return 'autosave';
    }

    // check post revision
    if ( wp_is_post_revision( $post_id ) ) {
        return 'revision';
    }

    // check permissions
    if ( 'csfd_item' == $_POST['post_type'] ) {
        if ( ! current_user_can( 'edit_page', $post_id ) ) {
            return 'cannot edit page';
        }
    }

    // CSFD.cz URL (an item's detail page)
    $csfd_url = $_POST['odwpcs-csfd_url'];
    if ( filter_var( $csfd_url, FILTER_VALIDATE_URL ) === false ) {
        $out = sprintf( '<div class="notice notice-error is-dismissible"><p>%1$s</p></div>', __( 'Nebylo poskytnuto správné ČSFD.cz URL!', 'odwpcs' ) );
        add_action( 'admin_notices', function() use ( $out ) { printf( $out ); } );
        return 'url is not valid';
    }

    // load movie details
    $movie_details = odwpcs_load_csfd_url( $csfd_url );
    if ( ! array_key_exists( 'title', $movie_details ) || ! array_key_exists( 'url', $movie_details ) ) {
        $out = sprintf( '<div class="notice notice-error is-dismissible"><p>%1$s</p></div>', __( 'Při parsování dat z webu ČSFD.cz došlo k chybě!', 'odwpcs' ) );
        add_action( 'admin_notices', function() use ( $out ) { printf( $out ); } );
        return 'parsing error';
    }

    // update post meta
    update_post_meta( $post_id, 'csfd_item_url', $csfd_url );
    update_post_meta( $post_id, 'csfd_item_category', $movie_details['category'] );
    update_post_meta( $post_id, 'csfd_item_description', $movie_details['description'] );
    update_post_meta( $post_id, 'csfd_item_image', $movie_details['image'] );
    update_post_meta( $post_id, 'csfd_item_title', $movie_details['title'] );
    update_post_meta( $post_id, 'csfd_item_video', $movie_details['video'] );

    // update post self
    $_post_id = wp_update_post( array(
        'ID'           => $post_id,
        'post_title'   => $movie_details['title'],
        'post_name'    => sanitize_title( $movie_details['title'] ),
        'post_content' => odwpcs_render_shortcode( $movie_details['title'] )
    ) );

    // print errors
    if ( is_wp_error( $_post_id ) ) {
        $errors = $_post_id->get_error_messages();
        if ( count( $errors ) > 0 ) {
            $out = sprintf( '<div class="notice notice-error is-dismissible"><p>%2$s</p></div>', __( 'Při aktualizaci příspěvku došlo k chybě!', 'odwpcs' ) );
            add_action( 'admin_notices', function() use ( $out ) { printf( $out ); } );
        }
    }

    return $post_id;
}

/**
 * Enqueue admin scripts and styles.
 * @param string $hook
 * @return void
 * @since 0.3.0
 * @uses plugins_url()
 * @uses wp_enqueue_style()
 */
function odwpcs_admin_scripts( $hook ) {
    wp_enqueue_style( 'odwpec_admin_styles', plugins_url( 'assets/css/admin.css', __FILE__ ) );
}

/**
 * Add the custom columns to the "csfd_item" post type.
 * @param array $columns
 * @return array
 * @since 0.3.0
 */
function odwpcs_custom_csfd_item_columns( $columns ) {
    unset( $columns['gadwp_stats'] );
    $columns['csfd_url'] = __( 'ČSFD.cz', 'odwpcs' );
    $columns['cache_status'] = __( 'Cache', 'odwpcs' );
    return $columns;
}

/**
 * Render content of our table columns.
 * @param string $column
 * @param integer $post_id
 * @return void
 */
function odwpcs_custom_csfd_item_column( $column, $post_id ) {
    switch ( $column ) {
        case 'csfd_url':
            $csfd_url = get_post_meta( $post_id, 'csfd_item_url', true );
            if ( ! $csfd_url ) {
                echo '<code>&ndash;&ndash;&ndash;</code>';
            } else {
                printf( '<a href="%1$s" target="_blank">%1$s</a>', $csfd_url );
            }
            break;

        case 'cache_status':
            $cache_status = 0;
            $cache_status_msg = '<span style="color:%1$s;">%2$s</span>';
            switch( $cache_status ) {
                case 0: printf( $cache_status_msg, 'red', __( 'není uloženo', 'odwpcs' ) ); break;
                case 1: printf( $cache_status_msg, 'green', __( 'uloženo', 'odwpcs' ) ); break;
            }
            break;
    }
}

// Register all
add_action( 'wp_enqueue_scripts', 'odwpcs_register_styles' );
add_action( 'init', 'odwpcs_init' );
add_action( 'init', 'odwpcs_init_cpt' );
add_action( 'admin_init', 'odwpcs_options_register' );
add_action( 'contextual_help', 'odwpcs_add_help_text', 10, 3 );
add_action( 'add_meta_boxes', 'odwpcs_add_csfd_url_metabox' );
add_action( 'save_post', 'odwpcs_save_csfd_url_metabox' );
add_action( 'new_to_publish', 'odwpcs_save_csfd_url_metabox' );
add_action( 'admin_enqueue_scripts', 'odwpcs_admin_scripts', 99 );
//add_filter( 'manage_csfd_item_posts_columns', 'odwpcs_custom_csfd_item_columns' );
//add_action( 'manage_csfd_item_posts_custom_column' , 'odwpcs_custom_csfd_item_column', 10, 2 );
add_shortcode( 'csfd', 'odwpcs_add_shortcode' );
register_activation_hook( __FILE__, 'odwpcs_rewrite_flush' );
