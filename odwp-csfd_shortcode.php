<?php
/**
 * Plugin Name: ČSFD.cz shortcode
 * Description: Plugin, který umožňuje snadno vložit shortcode s URL na ČSFD.cz, který pak bude zobrazen jako snippet s informacemi o filmu na vašich stránkách.
 * Version: 0.1.0
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
 * 
 * @todo Přidat cache - prostě jen MD5 url (tak získáme název souboru), 
 *       do něj to uložíme a pak při dalším použití jen zjistíme, zda 
 *       existuje a není příliš starý (dle nastavení) a případně ho rovnou 
 *       použijeme, v opačném případě ho vygenerujeme znovu.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * @param \DOMElement $movie_elm
 * @return array
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
 */
function odwpcs_render_shortcode_error( $msg ) {
    return '<div class="odwpcs-shortcode odwpcs-shortcode-error">' . $msg . '</div>';
}

/**
 * Render shortcode with given movie details.
 * @param array $movie
 * @return string
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

    // TODO Remove this
    //$out .= '<!-- ' . print_r( $movie, true ) . ' -->';

    return $out;
}

/**
 * Create new shortcode `[csfd url="https://www.csfd.cz/film/426009-deadpool-2/prehled/"]`.
 * @param array $atts
 * @return string
 */
function odwpcs_add_shortcode( $atts ) {
    $a = shortcode_atts( array(
        'url' => '',
    ), $atts );

    if ( filter_var( $a['url'], FILTER_VALIDATE_URL ) === false ) {
        return odwpcs_render_shortcode_error( __( 'Nebylo poskytnuto správné ČSFD.cz URL!', 'odwpcs' ) );
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

    return odwpcs_render_shorcode( $movie_details );
}

/**
 * Register our public CSS.
 */
function odwpcs_register_styles() {
    wp_register_style( 'odwp-csfd_shortcode', plugin_dir_url( __FILE__ ) . 'assets/css/public.css', array() );
    wp_enqueue_style( 'odwp-csfd_shortcode' );
}

// Register all
add_shortcode( 'csfd', 'odwpcs_add_shortcode' );
add_action( 'wp_enqueue_scripts', 'odwpcs_register_styles' );
