<?php

if(!class_exists('WP_List_Table')){
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class Incsub_Subscribe_By_Email_Pending_Queue_Table extends WP_List_Table {

	function __construct(){
        global $status, $page;
                
        parent::__construct( array(
            'singular'  => 'email', 
            'plural'    => 'emails',
            'ajax'      => false
        ) );
        
    }


    function column_email( $item ) { 
        return $item->subscriber_email;
    }

    function column_posts_list( $item ) {

        $subscriber = incsub_sbe_get_subscriber( $item->subscriber_email );

        $args = array(
            'posts_per_page' => -1,
            'ignore_sticky_posts' => true,
            'post__in' => $item->campaign_settings['posts_ids']
        );
        
        if ( ! empty( $subscriber->subscription_post_types ) )
            $args['post_type'] = $subscriber->subscription_post_types;

        $posts = get_posts( $args );

        if ( ! empty( $posts ) ) {
            foreach ( $posts as $post ) {
                $posts_titles[] = '<a href="' . get_edit_post_link( $post->ID ) . '">' . get_the_title( $post->ID ) . '</a>';
                
            }
            $return = implode( ' , ', $posts_titles );
        }
        else {
            $return = __( 'Nothing to send', INCSUB_SBE_LANG_DOMAIN );
        }
        
        return $return;
        
    }


    function get_columns(){
        $columns = array(
            'email'   => __( 'Email', INCSUB_SBE_LANG_DOMAIN ),
            'posts_list'    => __( 'Posts', INCSUB_SBE_LANG_DOMAIN )
        );
        return $columns;
    }


    function prepare_items() {
        global $wpdb, $page;

        $per_page = 2;
      
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = array();

        $this->_column_headers = array($columns, $hidden, $sortable);

        $current_page = $this->get_pagenum();

        $model = incsub_sbe_get_model();

        $result = $pending_queue = $model->get_queue_items( array( 'count' => true, 'per_page' => $per_page ) );

        $this->items = $result['items'];

        $this->set_pagination_args( array(
            'total_items' => $result['count'],                 
            'per_page'    => $per_page,              
            'total_pages' => ceil( $result['count'] / $per_page )  
        ) );
    }
}