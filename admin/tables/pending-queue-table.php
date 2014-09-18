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
        return date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int)$item['mail_date'] );
    }

    function column_posts( $item ) {
        $log_file = Subscribe_By_Email_Logger::open_log( $item['id'] );
        if ( absint( $item['mail_recipients'] ) != 0 && is_resource( $log_file ) ) {
            $link = add_query_arg( 'log_id', $item['id'], Incsub_Subscribe_By_Email::$admin_sent_emails_page->get_permalink() );
            return $item['mail_recipients'] . ' <a href="' . $link . '">' . __( 'Details &rarr;', INCSUB_SBE_LANG_DOMAIN ) . '</a>';
        }
        return $item['mail_recipients'];
    }


    function get_columns(){
        $columns = array(
            'email'   => __( 'Email', INCSUB_SBE_LANG_DOMAIN ),
            'posts'    => __( 'Posts', INCSUB_SBE_LANG_DOMAIN )
        );
        return $columns;
    }


    function prepare_items() {
        global $wpdb, $page;

        $per_page = 15;
      
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = array();

        $this->_column_headers = array($columns, $hidden, $sortable);

        $current_page = $this->get_pagenum();

        $model = incsub_sbe_get_model();

        $pending_queue = $model->get_queue_items( $current_page, $per_page, $sortable, $search );

        $this->items = $logs['logs'];               

        $this->set_pagination_args( array(
            'total_items' => $logs['total'],                 
            'per_page'    => $per_page,              
            'total_pages' => ceil( $logs['total'] / $per_page )  
        ) );
    }
}