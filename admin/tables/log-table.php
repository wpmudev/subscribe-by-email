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

    function column_cb( $item ){
        return sprintf(
            '<input type="checkbox" name="%1$s[]" value="%2$s" />',
                'campaign_id',  
                $item->id
        );
    }


    function column_date( $item ) { 
        return date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int)$item->mail_date );
    }

    function column_recipients( $item ) {
        $link = add_query_arg( 'log_id', $item->id, Incsub_Subscribe_By_Email::$admin_sent_emails_page->get_permalink() );
        return $item->mail_recipients . ' <a href="' . $link . '">' . __( 'Details &rarr;', INCSUB_SBE_LANG_DOMAIN ) . '</a>';
    }

    function column_status( $item ) {
        $status = $item->get_status();

        switch( $status ) {
            case 'empty': {
                $html = '<span style="color:#DF2929; font-weight:bold;">' . __( 'No subscribers in list', INCSUB_SBE_LANG_DOMAIN ) . '</span>';
                break;
            }
            case 'pending': {
                return '<span style="color:#DF2929; font-weight:bold;">' . __( 'Pending', INCSUB_SBE_LANG_DOMAIN ) . '</span>';
                break;
            }
            case 'finished': {
                return '<span style="color:#2DA62F; font-weight:bold;">' . __( 'Finished', INCSUB_SBE_LANG_DOMAIN ) . '</span>';
                break;
            }
        }

        return $html;
        
    }

    function column_subject( $item ) {
        return stripslashes_deep( $item->mail_subject );
    }

    function get_columns() {
        $columns = array(
            'cb'      => '<input type="checkbox" />', //Render a checkbox instead of text
            'date'   => __( 'Date', INCSUB_SBE_LANG_DOMAIN ),
            'recipients'    => __( 'Recipients no.', INCSUB_SBE_LANG_DOMAIN ),
            'status'   => __( 'Status', INCSUB_SBE_LANG_DOMAIN )
        );
        return $columns;
    }

    function get_bulk_actions() {
        $actions = array(
            'finish'    => __( 'Finish campaign', INCSUB_SBE_LANG_DOMAIN )
        );
        return $actions;
    }

    function process_bulk_action() {
        if ( 'finish' == $this->current_action() && ! empty( $_POST['campaign_id' ] ) ) {
            foreach ( $_POST['campaign_id'] as $id ) {
                $campaign = incsub_sbe_get_campaign( absint( $id ) );
                if ( ! $campaign )
                    continue;
                
                incsub_sbe_finish_campaign( $id );
            }
            ?>
                <div class="updated">
                    <p><?php _e( 'Queue items deleted', INCSUB_SBE_LANG_DOMAIN ); ?></p>
                </div>
            <?php

        }

        
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

        $this->process_bulk_action();

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

        $args = compact( 'current_page', 'per_page', 'sortable', 'search' );
        $campaigns = incsub_sbe_get_campaigns( $args );

        $this->items = $campaigns['items'];               

        $this->set_pagination_args( array(
            'total_items' => $campaigns['count'],                 
            'per_page'    => $per_page,              
            'total_pages' => ceil( $campaigns['count'] / $per_page )  
        ) );
    }
}