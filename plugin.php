<?php

/**
 * Plugin Name: WP REST API V2 CMS Methods
 * Description: Adds CMS Methods to WP REST API V2 JSON output.
 * Version: 0.1
 * Author: Gerhard sletten
 * Plugin URI: https://github.com/gerhardsletten/wp-rest-api-cms-methods/
 */

if (!class_exists("CMSMethodPlugin")) {
  class CMSMethodPlugin {
    var $prefix;

    public function __construct() {
      $this->prefix = 'wp_rest_cms_methods_';
      add_action('rest_api_init', array( $this, 'register_routes' ) );
      add_action( 'plugins_loaded', array( $this, 'init' ) );
    }

    function init () {
      add_action( 'customize_register', array( $this, 'plugin_customize_register' ));
      if ( is_admin() ) {
        add_filter( 'preview_post_link', array( $this, 'preview_link' ), 10, 2 );
        add_filter( 'page_link', array( $this, 'preview_link' ), 1, 2 );
      }
      add_action( 'init', array( $this, 'excerpts_to_pages' ) );
    }


    function excerpts_to_pages() {
      add_post_type_support( 'page', 'excerpt' );
    }

    function preview_link($link, $post = 0) {
      $url = parse_url($link);
      $domain = get_theme_mod($this->prefix . 'app_url');
      if ($domain) {
        // return sprintf("%s%s", $domain, $url['path'] );
      }
      return $link;
    }

    function plugin_customize_register($wp_customize) {
      $wp_customize->add_section($this->prefix . 'settings', array(
        'title' => __( 'REST CMS settings' ),
        'priority' => 35,
      ) );

      $wp_customize->add_setting( $this->prefix . 'app_url', array(
        'default' => '',
      ) );

      $wp_customize->add_control( $this->prefix . 'app_url', array(
        'label' => __( 'URL Frontend App' ),
        'section' => $this->prefix . 'settings',
        'type' => 'text',
      ) );
    }

    function register_routes () {
      $namespace = 'rest-cms-plugin/v1';
      register_rest_route( $namespace , '/page', array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => array( $this, 'fetch_page' ),
        'args' => array(
          'path' => array(
            'type' => 'string',
            'required' => true
          )
        )
      ));
      register_rest_route( $namespace, '/menu/(?P<location>[a-zA-Z0-9_-]+)', array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => array( $this, 'fetch_menu' ),
        'args' => array(
          'location' => array(
            'type' => 'string',
            'required' => true
          )
        )
      ));
      register_rest_route( $namespace, '/options', array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => array( $this, 'fetch_options' ),
        'args' => array(
          'options' => array(
            'type' => 'array',
            'required' => true,
            'items' => array(
              'type' => 'string',
              'required' => true
            )
          )
        )
      ));
    }

    function fetch_options ( $request ) {
      $params = $request->get_params();
      if ( ! isset( $params['options'] ) ) {
        return null;
      }
      $options = $params['options'];
      $return = array();
      foreach($options as $key) {
        $return[$key] = get_theme_mod($key, null );
      }
      return $return;
    }

    function fetch_menu( $request ) {
      $params = $request->get_params();
      $location = $params['location'];
      $locations = get_nav_menu_locations();

      if ( ! isset( $locations[ $location ] ) ) {
        return null;
      }

      $wp_menu = wp_get_nav_menu_object( $locations[ $location ] );
      $menu_items = wp_get_nav_menu_items( $wp_menu->term_id );
      $items = array();
      foreach ($menu_items as $item) {
        array_push($items, $this->util_format_menu_items($item));
      }
      $json = array(
        'name' => $wp_menu->name,
        'items' => $items
      );
      return apply_filters('rest_cms_menu_json', $json, $request);
    }

    function util_format_menu_items ($item) {
      $link = array(
        'title' => $item->title,
        'url' => ($item->type === 'custom') ? $item->url : $this->util_fix_url(get_page_uri( $item->object_id )),
      );
      $extra = array();
      if ( function_exists('get_fields') ) {
        $fields = get_fields($item);
        if (is_array($fields)) {
          $extra = get_fields($item);
        }
      }
      return apply_filters('rest_cms_menu_item', array_merge($link, $extra), $item);
    }

    function fetch_page ( $request ) {
      $params = $request->get_params();
      $path = $params['path'];
      if ($path) {
        $isHome = $path == '/';
        $pageFrontId = get_option( 'page_on_front' );
        $page = $isHome ? get_page( $pageFrontId ) : get_page_by_path( $path );
        if ( empty( $page ) || $page->post_status != 'publish' ) {
          return null;
        }
        if ( ($path != '/' && $page->ID == $pageFrontId) || $page->post_type != 'page') {
          return null;
        }
        $noIndex = get_post_meta($page->ID, '_yoast_wpseo_meta-robots-noindex', true);
        $json = array(
          'id' => $page->ID,
          'title' => get_the_title( $page->ID ),
          'meta' => array(
            'title' => get_post_meta($page->ID, '_yoast_wpseo_title', true),
            'description' => get_post_meta($page->ID, '_yoast_wpseo_metadesc', true),
            'noIndex' => $noIndex == '1' ? true : false
          ),
          'excerpt' => $page->post_excerpt,
          'content' => apply_filters( 'the_content', $page->post_content ),
          'image' => $this->util_post_feature_image( $page->ID, apply_filters('rest_cms_image_size', 'full', $page->ID) ),
          'type' => $this->util_page_template( $page->ID ),
          'url' => $isHome ? '/' : $this->util_fix_url( get_page_uri( $page->ID ) ),
          'path' => $isHome ? null : $this->util_path_trail( $page->ID )
        );
        if ( function_exists('get_fields') ) {
          $json['fields'] = get_fields( $page->ID );
        }
        if ( apply_filters('rest_cms_page_show_children', false, $json['type'], $page->ID) ) {
          $children = array();
          $children_options = apply_filters('rest_cms_page_children_options', array(), $json['type'], $page->ID);
          foreach(get_pages(array('sort_column' => 'menu_order', 'child_of' => $page->ID)) as $page) {
            array_push($children, $this->util_format_child_pages($page, $children_options));
          }
          $json['children'] = $children;
        }
        return apply_filters('rest_cms_page_json', $json, $request);
      }
      return null;
    }

    function util_format_child_pages ($page, $params = array()) {
      $options = array_merge(array(
        'fields' => array(),
        'image_size' => 'thumbnail'
      ), $params);
      $item = array(
        'id' => $page->ID,
        'title' => get_the_title( $page->ID ),
        'image' => $this->util_post_feature_image($page->ID, $options['image_size']),
        'excerpt' => $page->post_excerpt,
        'link' => $this->util_fix_url(get_page_uri( $page->ID )),
      );
      if ( count($options['fields']) > 0 && function_exists('get_fields') ) {
        $item['fields'] = array();
        foreach ($fields as $field) {
          $item['fields'][$field] = get_field($field, $page->ID);
        }
      }
      return apply_filters('rest_cms_subpage_item', $item);
    }

    function util_post_feature_image ($id, $size = 'full') {
      if( has_post_thumbnail($id) ) {
        $thumb_url_array = wp_get_attachment_image_src(get_post_thumbnail_id($id), $size, true);
        return $thumb_url_array[0];
      }
      return null;
    }

    function util_page_template ($id) {
      $type = str_replace(array('page-', '.php'), '', get_post_meta( $id, '_wp_page_template', true ));
      return apply_filters('rest_cms_page_type', $type, $id);
    }

    function util_fix_url ($str = '') {
      return sprintf('/%s', $str);
    }

    function util_path_trail ( $post_id ) {
      $path = array(
        array(
          'name' => get_bloginfo('name'),
          'path' => $this->util_fix_url()
      ));
      $parents = get_post_ancestors( $post_id );
      foreach (array_reverse($parents) as $value) {
        $path[] = array(
          'name' => get_the_title( $value ),
          'path' => $this->util_fix_url(get_page_uri( $value ))
        );
      }
      $path[] = array(
        'name' => get_the_title( $post_id ),
        'path' => $this->util_fix_url(get_page_uri( $post_id ))
      );
      return $path;
    }
  }
}

if (class_exists("CMSMethodPlugin")) {
  $instance = new CMSMethodPlugin();
}