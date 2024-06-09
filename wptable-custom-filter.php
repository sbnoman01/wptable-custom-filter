<?php
/*
  Plugin Name: WPTable Custom filter
  Description: WPData table 
  Version: 1.0
  Requires at least: 4.0
  Requires PHP: 5.2
  Tested up to: 6.5
  Author: sbnoman01
  Text Domain: wptable-custom-filter
*/

// include only file
if (!defined('ABSPATH')) {
    die('Do not open this file directly.');
}
  
class WPTable_Custom_Filter{
    
    //
    public $cpt_slug;
    public $book_context_key;

    public function __construct(){
        // assign cpt slug
        $this->cpt_slug = 'book';
        $this->book_context_key = '_book_context_meta';

        // fire hook.
        add_action( 'init', [ $this, 'wpt_book_init' ] );

        // adding metabox
        add_action( 'add_meta_boxes', [ $this, 'wpt_register_meta_boxes' ] );

        // save the contexts
        add_action( 'save_post', [ $this, 'wpt_save_metabox'] );

        // adding custom columns
        add_filter( "manage_{$this->cpt_slug}_posts_columns", [ $this, 'set_custom_edit_book_columns' ] );
        add_action( "manage_{$this->cpt_slug}_posts_custom_column" , [ $this, 'custom_book_column' ], 10, 2 );

        /***
         * *************
         * Intigrating search with Meta
         */
        add_action( 'pre_get_posts', [ $this, 'manupulate_book_query'] );
    }

    /**
     * Register a custom post type called "book".
     *
     * @see get_post_type_labels() for label keys.
     */
    public function wpt_book_init() {
        $labels = array(
            'name'                  => _x( 'Books', 'Post type general name', 'textdomain' ),
            'singular_name'         => _x( 'Book', 'Post type singular name', 'textdomain' ),
            'menu_name'             => _x( 'Books', 'Admin Menu text', 'textdomain' ),
            'name_admin_bar'        => _x( 'Book', 'Add New on Toolbar', 'textdomain' ),
            'add_new'               => __( 'Add New', 'textdomain' ),
            'add_new_item'          => __( 'Add New Book', 'textdomain' ),
            'new_item'              => __( 'New Book', 'textdomain' ),
            'edit_item'             => __( 'Edit Book', 'textdomain' ),
            'view_item'             => __( 'View Book', 'textdomain' ),
            'all_items'             => __( 'All Books', 'textdomain' ),
            'search_items'          => __( 'Search Books', 'textdomain' ),
            'parent_item_colon'     => __( 'Parent Books:', 'textdomain' ),
            'not_found'             => __( 'No books found.', 'textdomain' ),
            'not_found_in_trash'    => __( 'No books found in Trash.', 'textdomain' ),
            'featured_image'        => _x( 'Book Cover Image', 'Overrides the “Featured Image” phrase for this post type. Added in 4.3', 'textdomain' ),
            'set_featured_image'    => _x( 'Set cover image', 'Overrides the “Set featured image” phrase for this post type. Added in 4.3', 'textdomain' ),
            'remove_featured_image' => _x( 'Remove cover image', 'Overrides the “Remove featured image” phrase for this post type. Added in 4.3', 'textdomain' ),
            'use_featured_image'    => _x( 'Use as cover image', 'Overrides the “Use as featured image” phrase for this post type. Added in 4.3', 'textdomain' ),
            'archives'              => _x( 'Book archives', 'The post type archive label used in nav menus. Default “Post Archives”. Added in 4.4', 'textdomain' ),
            'insert_into_item'      => _x( 'Insert into book', 'Overrides the “Insert into post”/”Insert into page” phrase (used when inserting media into a post). Added in 4.4', 'textdomain' ),
            'uploaded_to_this_item' => _x( 'Uploaded to this book', 'Overrides the “Uploaded to this post”/”Uploaded to this page” phrase (used when viewing media attached to a post). Added in 4.4', 'textdomain' ),
            'filter_items_list'     => _x( 'Filter books list', 'Screen reader text for the filter links heading on the post type listing screen. Default “Filter posts list”/”Filter pages list”. Added in 4.4', 'textdomain' ),
            'items_list_navigation' => _x( 'Books list navigation', 'Screen reader text for the pagination heading on the post type listing screen. Default “Posts list navigation”/”Pages list navigation”. Added in 4.4', 'textdomain' ),
            'items_list'            => _x( 'Books list', 'Screen reader text for the items list heading on the post type listing screen. Default “Posts list”/”Pages list”. Added in 4.4', 'textdomain' ),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array( 'slug' => $this->cpt_slug ),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => null,
            'supports'           => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments' ),
        );

        register_post_type( $this->cpt_slug , $args );
    }


    /**
     * Register meta box(es).
     */
    function wpt_register_meta_boxes() {
        add_meta_box( 
            'meta-box-id',
            __( 'Post Options', 'textdomain' ),
            [ $this, 'wpt_metabox_callback' ],
            $this->cpt_slug,
            'side'
        );
    }

    public function wpt_metabox_callback( $post ){

        // get context value
        $context_value = get_post_meta( $post->ID, $this->book_context_key, true );

        $form = '';
        $form .= '<label>Type Book Context</label>';

        $form .= '<input type="text" placeholder="Book context" value="'. $context_value .'" name="book_context">';

        $form .= wp_nonce_field( 'wpt_nonce_action', 'book_context_nonce' );

        echo $form;
    }


    /**
     * Save meta box content.
     *
     * @param int $post_id Post ID
     */
    function wpt_save_metabox( $post_id ) {
        
        // Save logic goes here.
        if ( isset( $_REQUEST['book_context_nonce'] ) && wp_verify_nonce( $_REQUEST['book_context_nonce'], 'wpt_nonce_action' ) ) {
            // GET THE CONTEXT VALUE
            $book_context = sanitize_text_field( $_REQUEST['book_context'] );
            if( !empty($book_context) ){
                add_post_meta( $post_id, $this->book_context_key, $book_context );

            }else{
                delete_post_meta( $post_id, $this->book_context_key );
                
            }
        }
        
    }

    function set_custom_edit_book_columns( $columns ){
        $columns['book_context'] = __( 'book context', 'your_text_domain' );
        return $columns;
    }

    function custom_book_column( $column, $post_id ){
        switch ( $column ) {

            case 'book_context' :
                echo get_post_meta( $post_id, $this->book_context_key, true );
        }
    }

    function manupulate_book_query( $query ){

        if ( is_admin()  && is_post_type_archive($this->cpt_slug) && !empty( $_REQUEST['s'] )){

            $query->set( 'meta_query', 
                [
                    'relation' => 'OR',
                    [
                        'key'     => $this->book_context_key,
                        'compare' => 'LIKE',
                        'value'   =>  $_REQUEST['s'],
                    ]
                ]
            );
        }
  
    }

}

new WPTable_Custom_Filter();


