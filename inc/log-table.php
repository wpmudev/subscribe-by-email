<?php

if(!class_exists('WP_List_Table')){
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class Incsub_Subscribe_By_Email_Log_Table extends WP_List_Table {

	function __construct(){
        global $status, $page;
                
        parent::__construct( array(
            'singular'  => 'email', 
            'plural'    => 'emails',
            'ajax'      => false
        ) );
        
    }

    function column_subject( $item ) {        
        return $item['mail_subject'];
    }

    function column_date( $item ) { 
        return date_i18n( get_option( 'date_format' ), (int)$item['mail_date'] );
    }

    function column_recipients( $item ) {
        return $item['mail_recipients'];
    }

    function get_columns(){
        $columns = array(
            'recipients'  	=> __( 'Recipients no.', INCSUB_SBE_LANG_DOMAIN ),
            'date'   => __( 'Date', INCSUB_SBE_LANG_DOMAIN )
        );
        return $columns;
    }


    function get_sortable_columns() {
    	$sortable_columns = array(
            'date'   => array( 'mail_date', isset( $_GET['orderby'] ) && isset( $_GET['order'] ) && 'mail_date' == $_GET['orderby'] ? $_GET['order'] : false )
        );
        return $sortable_columns;
    }

    function prepare_items() {
        global $wpdb, $page;

        $per_page = 15;
      
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = array($columns, $hidden, $sortable);

        $current_page = $this->get_pagenum();

        $model = Incsub_Subscribe_By_Email_Model::get_instance();

        $search = false;
        if ( isset( $_POST['s'] ) )
            $search = $_POST['s'];

        $logs = $model->get_log( $current_page, $per_page, $sortable, $search );

        $this->items = $logs['logs'];               

        $this->set_pagination_args( array(
            'total_items' => $logs['total'],                 
            'per_page'    => $per_page,              
            'total_pages' => ceil( $logs['total'] / $per_page )  
        ) );
    }
}