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

    function column_cb( $item ){
        return sprintf(
            '<input type="checkbox" name="%1$s[]" value="%2$s" />',
                'queue_id',  
                $item->id
        );
    }


    function column_email( $item ) { 
        return $item->subscriber_email;
    }

    function column_posts_list( $item ) {

        $posts = $item->get_subscriber_posts();
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

    function process_bulk_action() {
        if ( isset( $_POST['sbe-download-csv'] ) ) {
            return;           
        }

       
        if ( 'delete' == $this->current_action() && ! empty( $_POST['queue_id' ] ) ) {
            $model = incsub_sbe_get_model();
            foreach ( $_POST['queue_id'] as $id ) {
                $queue_item = incsub_sbe_get_queue_item( absint( $id ) );
                if ( $queue_item ) {
                    $campaign = incsub_sbe_get_campaign( $queue_item->campaign_id );
                    $model->delete_queue_item( absint( $id ) );
                    $campaign->refresh_campaign_status();
                }
            }
            ?>
                <div class="updated">
                    <p><?php _e( 'Queue items deleted', INCSUB_SBE_LANG_DOMAIN ); ?></p>
                </div>
            <?php

        }

        
    }

    function get_bulk_actions() {
        $actions = array(
            'delete'    => __( 'Delete', INCSUB_SBE_LANG_DOMAIN )
        );
        return $actions;
    }


    function get_columns(){
        $columns = array(
            'cb'      => '<input type="checkbox" />', //Render a checkbox instead of text
            'email'   => __( 'Email', INCSUB_SBE_LANG_DOMAIN ),
            'posts_list'    => __( 'Posts', INCSUB_SBE_LANG_DOMAIN )
        );
        return $columns;
    }


    function prepare_items() {
        global $wpdb;

        $this->process_bulk_action();

        $current_page = $this->get_pagenum();

        $current_screen = get_current_screen();
        $screen_option = $current_screen->get_option( 'per_page', 'option' );

        $per_page = get_user_meta( get_userdata( get_current_user_id() ), $screen_option, true );
        if ( empty ( $per_page) || $per_page < 1 ) {
            $per_page = $current_screen->get_option( 'per_page', 'default' );
        }
      
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = array();

        $this->_column_headers = array($columns, $hidden, $sortable);

        $current_page = $this->get_pagenum();

        $result = incsub_sbe_get_queue_items( array( 'count' => true, 'per_page' => $per_page, 'page' => $current_page ) );

        $this->items = $result['items'];

        $this->set_pagination_args( array(
            'total_items' => $result['count'],                 
            'per_page'    => $per_page,              
            'total_pages' => ceil( $result['count'] / $per_page )  
        ) );
    }
}