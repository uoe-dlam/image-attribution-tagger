<?php 
	/**
	* Plugin Name: Image Attribution Tagger
	* Plugin URI: http://red8interactive.com
	* Description: A plugin that adds image credits to licensed images
	* Version: 1.0
	* Author: Red8 Interactive
	* Author URI: http://red8interactive.com
	* License: GPL2
	*/
 
	/*  
		Copyright 2015 Red8 Interactive  (email : james@red8interactive.com) 
	
		This program is free software; you can redistribute it and/or
		modify it under the terms of the GNU General Public License
		as published by the Free Software Foundation; either version 2
		of the License, or (at your option) any later version.
		
		This program is distributed in the hope that it will be useful,
		but WITHOUT ANY WARRANTY; without even the implied warranty of
		MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
		GNU General Public License for more details.
		
		You should have received a copy of the GNU General Public License
		along with this program; if not, write to the Free Software
		Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
	*/
	
	
	if ( ! defined( 'ABSPATH' ) ) { 
    	exit; // Exit if accessed directly
	}
	
	
	if( !class_exists('Image_Attribution_Tagger') ) {
		
		class Image_Attribution_Tagger {
		
			// Create an instance of our class
			function __construct() {
				self::define_constants();
				self::load_hooks();
			}
			
			
			// Define our constants
			public static function define_constants() {
				define('IMAGE_ATTRIBUTION_TAGGER_TEXT_DOMAIN', 'image-attribution-tagger-text-domain');
				define('IMAGE_ATTRIBUTION_TAGGER_ADMIN_NOTICE', 'image_attribution_tagger_show_warnings');
				define('IMAGE_ATTRIBUTION_TEXT_NAME', 'image_attribution_tagger_text_save');
				define('IMAGE_ATTRIBUTION_TEXT_FIELD_NAME', 'image-attribution-tagger-text');
				define('IMAGE_ATTRIBUTION_AS_CAPTION', 'image_attribution_tagger_text_save_caption');
				define('IMAGE_ATTRIBUTION_AS_CAPTION_FIELD_NAME', 'image-attribution-tagger-caption');
				define('IMAGE_ATTRIBUTION_NONCE_NAME', 'image_attribution_tagger_notice');
			}
			
			
			// Add our filter and action hooks
			public static function load_hooks() {
				add_filter( 'add_attachment', array(__CLASS__, 'display_notices'), 10, 2 );
				
				add_action( 'admin_notices', array(__CLASS__, 'show_admin_notice') );
				
				add_filter( 'attachment_fields_to_edit', array(__CLASS__, 'iat_image_text'), 10, 2 );
				
				add_filter( 'image_send_to_editor', array(__CLASS__, 'iat_image_add_to_editor'), 10, 8);
				
				add_filter( 'attachment_fields_to_save', array(__CLASS__, 'iat_image_text_save'), 10, 2 );
				
				add_filter( 'the_content', array(__CLASS__, 'add_attributed_image_text_to_content') );
				
				add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_scripts' ) );
				
				add_action( 'wp_ajax_image_attribution_tagger_dismissible_notice', array( __CLASS__, 'ajax_warnings' ) );
			}
			
			
			// Display notice after image upload
			public static function display_notices($post_ID) {
				$type = get_post_mime_type($post_ID);
				$image_types = array('image/jpeg', 'image/png', 'image/gif');
				if(in_array($type, $image_types)) {
			    	update_user_meta(get_current_user_id(), IMAGE_ATTRIBUTION_TAGGER_ADMIN_NOTICE, 1);
			    }
			    return $post_ID;
			}
			
			
			// Create the HTML for our notice if it needs to be displayed
			public static function show_admin_notice() {
				if(get_user_meta(get_current_user_id(), IMAGE_ATTRIBUTION_TAGGER_ADMIN_NOTICE, true) == 1) {
					$class = "image_attribution_tagger_upload error notice is-dismissible";
                    $message = __( "You just uploaded an image. Was it a Creative Commons image that you would like to add an image credit to? Head to the <a href=\"" . get_bloginfo( 'url' ) . "/wp-admin/upload.php\">Media Library</a> to add it.&nbsp;", IMAGE_ATTRIBUTION_TAGGER_TEXT_DOMAIN );
					echo"<div class=\"$class\"> <p>$message</p></div>"; 
				}
			}
			
			
			// Add our field to the attachment details settings
			public static function iat_image_text( $form_fields, $post ) {
				$form_fields[IMAGE_ATTRIBUTION_TEXT_FIELD_NAME] = array(
					'label' => __('Attribution Tag', IMAGE_ATTRIBUTION_TAGGER_TEXT_DOMAIN),
					'input' => 'textarea',
					'value' => get_post_meta( $post->ID, IMAGE_ATTRIBUTION_TEXT_NAME, true ),
					'helps' => __('(Will appear below content unless "Attribution Tag as Caption" is selected. HTML, links for example, is allowed.)', IMAGE_ATTRIBUTION_TAGGER_TEXT_DOMAIN)
				);
				
				$is_caption = (bool)get_post_meta( $post->ID, IMAGE_ATTRIBUTION_AS_CAPTION, true );
				$form_fields[IMAGE_ATTRIBUTION_AS_CAPTION_FIELD_NAME] = array(
					'label' => __('Attribution Tag as Caption', IMAGE_ATTRIBUTION_TAGGER_TEXT_DOMAIN),
					'input' => 'html',
					'html' => '<input type="checkbox" id="attachments-'.$post->ID.'-'.IMAGE_ATTRIBUTION_AS_CAPTION_FIELD_NAME.'" name="attachments['.$post->ID.']['.IMAGE_ATTRIBUTION_AS_CAPTION_FIELD_NAME.']" value="1"'.($is_caption ? ' checked="checked"' : '').' />  ',
					'value' => $is_caption,
				);
			
				return $form_fields;
			}
			
			
			public static function iat_image_add_to_editor($html, $id, $caption, $title, $align, $url, $size, $alt ) {
				$text = get_post_meta($id, IMAGE_ATTRIBUTION_TEXT_NAME, true);
				$is_caption = (bool)get_post_meta($id, IMAGE_ATTRIBUTION_AS_CAPTION, true);
				if($text && $is_caption && !$caption) {
					$html = "[caption id=\"attachment_$id\" align=\"$align\"]".$html." ".$text."[/caption]";
				}
				return $html;
	       	}
	
	
			// Save our new field
			public static function iat_image_text_save( $post, $attachment ) {
				if( isset( $attachment[IMAGE_ATTRIBUTION_TEXT_FIELD_NAME] ) )
					update_post_meta( $post['ID'], IMAGE_ATTRIBUTION_TEXT_NAME, $attachment[IMAGE_ATTRIBUTION_TEXT_FIELD_NAME] );
					
				if( isset( $attachment[IMAGE_ATTRIBUTION_AS_CAPTION_FIELD_NAME] ) ) {
					update_post_meta( $post['ID'], IMAGE_ATTRIBUTION_AS_CAPTION, $attachment[IMAGE_ATTRIBUTION_AS_CAPTION_FIELD_NAME] );
				} else {
					update_post_meta( $post['ID'], IMAGE_ATTRIBUTION_AS_CAPTION, 0 );
				}
			
				return $post;
			}
			
			
			// Add our field to the end of the content
			public static function add_attributed_image_text_to_content($content) {
				if(is_single()) {
					$post_thumbnail_id = get_post_thumbnail_id();
					
					if($post_thumbnail_id && get_post_meta($post_thumbnail_id, IMAGE_ATTRIBUTION_TEXT_NAME, true)) {
						$text = get_post_meta($post_thumbnail_id, IMAGE_ATTRIBUTION_TEXT_NAME, true);
						$content .= '<p style="font-style: italic">('.$text.')</p>';
					}
					
					$images = get_attached_media('image');
					if($images) {
						foreach($images as $image) {
							$text = get_post_meta($image->ID, IMAGE_ATTRIBUTION_TEXT_NAME, true);
							$is_caption = (bool)get_post_meta($image->ID, IMAGE_ATTRIBUTION_AS_CAPTION, true);
							if($text && !$is_caption) {
								$content .= '<p style="font-style: italic">('.$text.')</p>';
							}
						}
					}
				}
				
				return $content;
			}
			
			
			// Enqueue our JS and CSS scripts
			public static function admin_scripts() {
				wp_enqueue_script( 'image-attribution-tagger', plugin_dir_url( __FILE__ ) . '/js/admin.js' );
				wp_localize_script( 'image-attribution-tagger', 'image_attribution_tagger', array(
					'nonce'      => wp_create_nonce( IMAGE_ATTRIBUTION_NONCE_NAME ),
				) );
			}
			
			
			// Dismiss our notification and prevent it from showing again
			public static function ajax_warnings() {
				if (  ! isset( $_POST[ 'nonce' ] ) || ! wp_verify_nonce( $_POST[ 'nonce' ], IMAGE_ATTRIBUTION_NONCE_NAME ) ) {
					return false;
				}
				
				update_user_meta(get_current_user_id(), IMAGE_ATTRIBUTION_TAGGER_ADMIN_NOTICE, 0);
			}
		}
		
		$class['Image_Attribution_Tagger'] = new Image_Attribution_Tagger();	
	}
			
?>