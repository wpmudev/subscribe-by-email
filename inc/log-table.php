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


    function column_date( $item ) { 
        return date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int)$item['mail_date'] );
    }

    function column_recipients( $item ) {
        $log_file = Subscribe_By_Email_Logger::open_log( $item['id'] );
        if ( absint( $item['mail_recipients'] ) != 0 && is_resource( $log_file ) ) {
            $link = add_query_arg( 'log_id', $item['id'], Incsub_Subscribe_By_Email::$admin_sent_emails_page->get_permalink() );
            return $item['mail_recipients'] . ' <a href="' . $link . '">' . __( 'Details &rarr;', INCSUB_SBE_LANG_DOMAIN ) . '</a>';
        }
        return $item['mail_recipients'];
    }

    function column_status( $item ) {
        if ( absint( $item['mail_recipients'] ) == 0 && empty( $item['mail_settings'] ) )
            return '<span style="color:#DF2929; font-weight:bold;">' . __( 'Failed', INCSUB_SBE_LANG_DOMAIN ) . '</span>';
        if ( empty( $item['mail_settings'] ) && absint( $item['mail_recipients'] ) != 0 )
            return __( 'Finished', INCSUB_SBE_LANG_DOMAIN );
        else
            return '<span style="color:#DF2929; font-weight:bold;">' . __( 'Pending', INCSUB_SBE_LANG_DOMAIN ) . '</span>';
    }

    function column_subject( $item ) {
        return stripslashes_deep( $item['mail_subject'] );
    }

    function get_columns(){
        $columns = array(
            'date'   => __( 'Date', INCSUB_SBE_LANG_DOMAIN ),
            'recipients'    => __( 'Recipients no.', INCSUB_SBE_LANG_DOMAIN ),
            'status'   => __( 'Status', INCSUB_SBE_LANG_DOMAIN )
        );
        return $columns;
    }


    function get_sortable_columns() {
    	$sortable_columns = array(
            'date'   => array( 'mail_date', isset( $_GET['orderby'] ) && isset( $_GET['order'] ) && 'mail_date' == $_GET['orderby'] ? $_GET['order'] : 'DESC' ),
            'subject'   => array( 'mail_subject', isset( $_GET['orderby'] ) && isset( $_GET['order'] ) && 'mail_subject' == $_GET['orderby'] ? $_GET['order'] : false )
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