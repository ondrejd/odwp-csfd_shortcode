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
function odwpcs_render_shorcode( $movie ) {
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

    // Use cache if it's available
    $cache = odwpcs_get_cache_item( $a['url'] );
    if ( ! empty( $cache ) ) {
        return $cache;
    }

    $html_gzip = file_get_contents( $a['url'] );
    $html_raw = gzdecode( $html_gzip );

    try {
        $html_dom = new DOMDocument();
        $html_dom->loadHTML( $html_raw );
        
        $movie_details = odwpcs_get_csfd_movie_details( $html_dom );
        $movie_details['url'] = $a['url'];
    } catch ( \Exception $e ) {}

    if ( empty( $movie_details['title'] ) ) {
        return odwpcs_render_shortcode_error( __( 'Při parsování dat z webu ČSFD.cz došlo k chybě!' ) );
    }

    $html = odwpcs_render_shorcode( $movie_details );
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

//.............................................
// TODO:
// 1. Přidat možnosti pluginu, kde jde povolit/zakázat CPT "csfd_item"
add_action( 'admin_init', 'odwpcs_options_register' );

/**
 * @return void
 * @since 0.3.0
 * @uses register_setting()
 * @uses add_settings_section()
 * @uses add_settings_field()
 */
function odwpcs_options_register() {
    $csfd_icon = plugins_url( 'assets/images/csfd-icon-114x114.png', __FILE__ );

    register_setting( 'writing', ODWPCS_OPT_ENABLE_CPT );

    // TODO Move image & inline styles into `assets/css/admin.css`!
    add_settings_section(
        'odwpcs_settings_section',
        sprintf(
            __( '%1$sČSFD.cz%2$s shortcode', 'odwpcs' ),
            '<img src="' . $csfd_icon . '" style="width:22px; position:relative; top:5px;" /> ' .
            '<a href="https://www.csfd.cz/" target="_blank">', '</a>'
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
 * @uses register_setting()
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
    <p>
        <label for="<?php echo ODWPCS_OPT_ENABLE_CPT; ?>">
            <input id="<?php echo ODWPCS_OPT_ENABLE_CPT; ?>" name="<?php echo ODWPCS_OPT_ENABLE_CPT; ?>" type="checkbox" value="1" <?php checked( $enable_cpt, 1 ); ?> />
            <?php printf( __( 'Povolit typ příspěvků %1$scsfd_item%2$s?', 'odwpcs' ), '<code>', '</code>' ); ?>
        </label>
    </p>
<?php
}

// 2. Přidat CPT "csfd_item"
// 3. Vyzkoušet to a otestovat
// 4. Aktualizovat všechny README a Git
// 5. Napsat na ondrejd.com

//.............................................

// Register all
add_shortcode( 'csfd', 'odwpcs_add_shortcode' );
add_action( 'wp_enqueue_scripts', 'odwpcs_register_styles' );
add_action( 'init', 'odwpcs_init' );
