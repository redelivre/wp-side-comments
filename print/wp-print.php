<?php
/*
Plugin Name: WP-Print
Plugin URI: http://lesterchan.net/portfolio/programming/php/
Description: Displays a printable version of your WordPress blog's post/page.
Version: 2.50
Author: Lester 'GaMerZ' Chan
Author URI: http://lesterchan.net
*/


/*  
	Copyright 2009  Lester Chan  (email : lesterchan@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

### Function: Print Public Variables
add_filter('query_vars', 'print_variables');
function print_variables($public_query_vars) {
	$public_query_vars[] = 'wp_side_comments_print';
	$public_query_vars[] = 'wp_side_comments_printpage';
	$public_query_vars[] = 'wp_side_comments_print_csv';
    $public_query_vars[] = 'number-options';
    $public_query_vars[] = 'wp_side_comments_print_parent';
	return $public_query_vars;
}

### Function: Load WP-Print
function wp_side_comments_print()
{
	if(intval(get_query_var('wp_side_comments_print')) == 1 || intval(get_query_var('wp_side_comments_printpage')) == 1 || intval(get_query_var('wp_side_comments_print_csv')) == 1  )
	{
		global $wp_query;
		
		if(intval(get_query_var('wp_side_comments_print_parent')) == 1)
		{
        	$post = get_post();
        	
        	$current_post_id = get_the_ID();
        	
        	$parent = $post->post_parent;
        	
        	if( $parent == 0 )
        		$parent = $current_post_id;
        	
        	$pages = get_pages( array( 'parent' => $parent, 'sort_column' => 'title', 'sort_order' => 'asc', 'number' => '6' ) );
        	$wp_query = new WP_Query( array(
        			'post_parent' => $parent,
        			'orderby' => 'title',
        			'order' => 'ASC',
        			'post_type' => get_post_type(),
					'post_status' => 'publish'
        	));
		}
		
		//TODO FIX posts_per_page
		/*$args = array_merge( $wp_query->query_vars, array( 'post_type' => 'product' ) );
		query_posts( $args );
		
		$wp_query->set('posts_per_page', get_query_var('number-options'));
		query_posts($wp_query->query_vars);*/
        
		include(WP_PLUGIN_DIR.'/wp-side-comments/print/print.php');
		exit();
	}
}
add_action('template_redirect', 'wp_side_comments_print', 5);

### Function: Print Content
function print_content($display = true) {
	global $links_text, $link_number, $max_link_number, $matched_links,  $pages, $multipage, $numpages, $post;
    if (!isset($link_text) && isset($link_url)) {
        $link_text = $link_url;
    }

	if (!isset($matched_links)) {
		$matched_links = array();
	}
	if(!empty($post->post_password) && stripslashes($_COOKIE['wp-postpass_'.COOKIEHASH]) != $post->post_password) {
		$content = get_the_password_form();
	} else {
		if($multipage) {
			for($page = 0; $page < $numpages; $page++) {
				$content .= $pages[$page];
			}
		} else {
			$content = $pages[0];
		}
		if(function_exists('email_rewrite')) {
			remove_shortcode('donotemail');
			add_shortcode('donotemail', 'email_donotemail_shortcode2');
		}
		$content = apply_filters('the_content', $content);
		$content = str_replace(']]>', ']]&gt;', $content);
		if(!print_can('images')) {
			$content = remove_image($content);
		}
		if(!print_can('videos')) {
			$content = remove_video($content);
		}
		if(print_can('links')) {
			preg_match_all('/<a(.+?)href=[\"\'](.+?)[\"\'](.*?)>(.+?)<\/a>/', $content, $matches);
			for ($i=0; $i < count($matches[0]); $i++) {
				$link_match = $matches[0][$i];
				$link_url = $matches[2][$i];
				if(stristr($link_url, 'https://')) {
					 $link_url =(strtolower(substr($link_url,0,8)) != 'https://') ?get_option('home') . $link_url : $link_url;
				} else if( stristr($link_url, 'mailto:')) {
					$link_url =(strtolower(substr($link_url,0,7)) != 'mailto:') ?get_option('home') . $link_url : $link_url;
				} else if( $link_url[0] == '#' ) {
					$link_url = $link_url; 
				} else {
					$link_url =(strtolower(substr($link_url,0,7)) != 'http://') ?get_option('home') . $link_url : $link_url;
				}
				$link_text = $matches[4][$i];+				
				$new_link = true;
				$link_url_hash = md5($link_url);
				if (!isset($matched_links[$link_url_hash])) {
					$link_number = ++$max_link_number;
					$matched_links[$link_url_hash] = $link_number;
				} else {
					$new_link = false;
					$link_number = $matched_links[$link_url_hash];
				}
				$content = str_replace_one($link_match, "<a href=\"$link_url\" rel=\"external\">".$link_text.'</a> <sup>['.number_format_i18n($link_number).']</sup>', $content);
				if ($new_link) {
					if(preg_match('/<img(.+?)src=[\"\'](.+?)[\"\'](.*?)>/',$link_text)) {
						$links_text .= '<p style="margin: 2px 0;">['.number_format_i18n($link_number).'] '.__('Image', 'wp-print').': <b><span dir="ltr">'.$link_url.'</span></b></p>';
					} else {
						$links_text .= '<p style="margin: 2px 0;">['.number_format_i18n($link_number).'] '.$link_text.': <b><span dir="ltr">'.$link_url.'</span></b></p>';
					}
				}
			}
		}
	}
	if($display) {
		echo $content;
	} else {
		return $content;
	}
}


### Function: Print Categories
function print_categories($before = '', $after = '', $parents = '')
{
	
	$temp_cat = strip_tags(get_the_category_list(',', $parents));
	$temp_cat = explode(', ', $temp_cat);
	$temp_cat = implode($after.__(',', 'wp-print').' '.$before, $temp_cat);
	echo $before.$temp_cat.$after;
}


### Function: Print Comments Content
function print_comments_content($display = true) {
	global $links_text, $link_number, $max_link_number, $matched_links;
    if (!isset($link_text) && isset($link_url)) {
        $link_text = $link_url;
    }

	if (!isset($matched_links)) {
		$matched_links = array();
	}
	$content  = get_comment_text();
	$content = apply_filters('comment_text', $content);
	if(!print_can('images')) {
		$content = remove_image($content);
	}
	if(!print_can('videos')) {
		$content = remove_video($content);
	}
	if(print_can('links')) {
		preg_match_all('/<a(.+?)href=[\"\'](.+?)[\"\'](.*?)>(.+?)<\/a>/', $content, $matches);
		for ($i=0; $i < count($matches[0]); $i++) {
			$link_match = $matches[0][$i];
			$link_url = $matches[2][$i];
			if(stristr($link_url, 'https://')) {
				 $link_url =(strtolower(substr($link_url,0,8)) != 'https://') ?get_option('home') . $link_url : $link_url;
			} else if(stristr($link_url, 'mailto:')) {
				$link_url =(strtolower(substr($link_url,0,7)) != 'mailto:') ?get_option('home') . $link_url : $link_url;
			} else if($link_url[0] == '#') {
				$link_url = $link_url; 
			} else {
				$link_url =(strtolower(substr($link_url,0,7)) != 'http://') ?get_option('home') . $link_url : $link_url;
			}
			$new_link = true;
			$link_url_hash = md5($link_url);
			if (!isset($matched_links[$link_url_hash])) {
				$link_number = ++$max_link_number;
				$matched_links[$link_url_hash] = $link_number;
			} else {
				$new_link = false;
				$link_number = $matched_links[$link_url_hash];
			}
			$content = str_replace_one($link_match, "<a href=\"$link_url\" rel=\"external\">".$link_text.'</a> <sup>['.number_format_i18n($link_number).']</sup>', $content);
			if ($new_link) {
				if(preg_match('/<img(.+?)src=[\"\'](.+?)[\"\'](.*?)>/',$link_text)) {
					$links_text .= '<p style="margin: 2px 0;">['.number_format_i18n($link_number).'] '.__('Image', 'wp-print').': <b><span dir="ltr">'.$link_url.'</span></b></p>';
				} else {
					$links_text .= '<p style="margin: 2px 0;">['.number_format_i18n($link_number).'] '.$link_text.': <b><span dir="ltr">'.$link_url.'</span></b></p>';
				}
			}
		}
	}
	if($display) {
		echo $content;
	} else {
		return $content;
	}
}

function wp_side_comments_comment_number($postID, $filter)
{
	return 0;
}

### Function: Print Comments
function print_comments_number($comments = false) {
	global $post;
	$comment_text = '';
	$comment_status = $post->comment_status;
	if($comment_status == 'open')
	{
		$num_comments = 0;
		if($comments)
			$num_comments = count($comments);
		else
			$num_comments = wp_side_comments_comment_number($post->ID, 'todos');
		
		if($num_comments == 0) {
			$comment_text = __('Sem Interações', 'wp-side-comments');
		} else {
			$comment_text = sprintf(_n('%s Interação', '%s Interações', $num_comments, 'wp-side-comments'), number_format_i18n($num_comments));
		}
	} else {
		$comment_text = __('Interações Desativadas', 'wp-side-comments');
	}
	if(!empty($post->post_password) && stripslashes($_COOKIE['wp-postpass_'.COOKIEHASH]) != $post->post_password) {
		_e('Interações Escondidas', 'wp-side-comments');
	} else {
		echo $comment_text;
	}
}


### Function: Print Links
function print_links($text_links = '') {
	global $links_text;
	if(empty($text_links)) {
		$text_links = __('URLs in this post:', 'wp-print');
	}
	if(!empty($links_text)) { 
		echo $text_links.$links_text; 
	}
}

### Function: Add Print Comments Template
function print_template_comments($file = '') {
	if(file_exists(TEMPLATEPATH.'/print-comments.php')) {
		$file = TEMPLATEPATH.'/print-comments.php';
	} else {
		$file = WP_PLUGIN_DIR.'/wp-side-comments/print/print-comments.php';
	}
	return $file;
}

### Function: Print Page Title
function print_pagetitle($page_title) {
	$page_title .= ' &raquo; '.__('Print', 'wp-print');
	return $page_title;
}


### Function: Can Print?
function print_can($type) {
	
	return true;
}


### Function: Remove Image From Text
function remove_image($content) {
	//$content= preg_replace('/<img(.+?)src=[\"\'](.+?)[\"\'](.*?)>/', '',$content);
	return $content;
}


### Function: Remove Video From Text
function remove_video($content) {
	$content= preg_replace('/<object[^>]*?>.*?<\/object>/', '',$content);
	$content= preg_replace('/<embed[^>]*?>.*?<\/embed>/', '',$content);
	return $content;
}


### Function: Replace One Time Only
function str_replace_one($search, $replace, $content){
	if ($pos = strpos($content, $search)) {
		return substr($content, 0, $pos).$replace.substr($content, $pos+strlen($search));
	} else {
		return $content;
	}
}

function wp_side_comments_get_print_link($texto = false, $imagem = false)
{
	if($texto == false) $texto = __("imprimir", 'wp-side-comments');
	$server = $_SERVER['SERVER_NAME'];
	$endereco = $_SERVER ['REQUEST_URI'];
	$url = "http".(array_key_exists('HTTPS', $_SERVER))."://".$server.$endereco;
	$e = strpos($url, '?') !== false ? '&' : '?';
	$html = '';
	if($imagem !== false)
	{
		$html = '
			<a href="'.$url.$e.'wp_side_comments_print=1" class="wp_side_comments-print-link"><img class="wp_side_comments-print-link-img" src="'.TEMPLATEPATH.DIRECTORY_SEPARATOR.$imagem.'" alternative="'.$texto.'" /></a>
		';
	}
	elseif($texto !== false && $imagem === false)
	{
		$html = '
			<a href="'.$url.$e.'wp_side_comments_print=1" class="wp_side_comments-print-link"><span class="wp_side_comments-print-link-label" >'.$texto.'</span></a>
		';
	}
	return $html;
}


?>
