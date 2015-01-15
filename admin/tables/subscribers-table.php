<?php

if(!class_exists('WP_List_Table')){
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class Incsub_Subscribe_By_Email_Subscribers_Table extends WP_List_Table {

    function __construct(){
        global $status, $page;
                
        parent::__construct( array(
            'singular'  => 'subscriptor', 
            'plural'    => 'subscriptors',
            'ajax'      => false
        ) );
        
    }

    function column_cb($item){
        return sprintf(
            '<input type="checkbox" name="%1$s[]" value="%2$s" />',
                'subscriptor',  
                $item->ID
        );
    }

    function column_email( $item ) {

        $edit_link = add_query_arg( array( 'action' => 'edit', 'sid' => absint( $item->ID ) ) );
        $actions = array(
            'edit'    => sprintf( __( '<a href="%s">%s</a>', INCSUB_SBE_LANG_DOMAIN ), 
                esc_url( $edit_link ),
                __( 'Edit', INCSUB_SBE_LANG_DOMAIN )
            ),
            'cancel'    => sprintf( __( '<span class="trash"><a class="trash" href="%s">%s</a></span>', INCSUB_SBE_LANG_DOMAIN ), 
                esc_url( add_query_arg( array( 'action' => 'cancel', 'sid' => absint( $item->ID ) ) ) ),
                __( 'Cancel subscription', INCSUB_SBE_LANG_DOMAIN )
            ),
            
        );

        if ( ! $item->is_confirmed() ) {
            $actions['send_confirmation'] = sprintf( __( '<span><a class="trash" href="%s">%s</a></span>', INCSUB_SBE_LANG_DOMAIN ), 
                esc_url( add_query_arg( array( 'action' => 'send_confirmation', 'sid' => absint( $item->ID ) ) ) ),
                __( 'Resend confirmation mail', INCSUB_SBE_LANG_DOMAIN )
            );
            $actions['confirm_subscription'] = sprintf( __( '<span><a class="trash" href="%s">%s</a></span>', INCSUB_SBE_LANG_DOMAIN ), 
                esc_url( add_query_arg( array( 'action' => 'confirm_subscription', 'sid' => absint( $item->ID ) ) ) ),
                __( 'Confirm Subscription', INCSUB_SBE_LANG_DOMAIN )
            );
        }
        
        return '<a href="' . esc_url( $edit_link ) . '">' . $item->subscription_email . '</a>' . $this->row_actions( $actions );
    }

    function column_created( $item ) { 
        return $item->subscription_created;
    }

    function column_status( $item ) {
        if ( $item->is_confirmed() )
            return '<span class="dashicons-before dashicons-yes sbe-icon-confirmed-yes"> ' . __( 'Email confirmed', INCSUB_SBE_LANG_DOMAIN ) . '</span>';
        else
            return '<span class="dashicons-before dashicons-no sbe-icon-confirmed-no"> ' . __( 'Awaiting confirmation', INCSUB_SBE_LANG_DOMAIN ) . '</span>';
    }

    function column_subscription_type( $item ) {
        return $item->subscription_note;
    }

    function column_subscribed_to( $item ) {
        $result = array();

        if ( $item->is_confirmed() ) {
            $post_types = $item->subscription_post_types;

            if ( $post_types === false ) {
                $settings = incsub_sbe_get_settings();
                $post_types = $settings['post_types'];
            }

            
            if( is_array( $post_types ) ) {
                foreach ( $post_types as $post_type_slug ) {
                    $cpt = get_post_type_object( $post_type_slug );
                    if ( $cpt ) {
                        $result[] = $cpt->labels->name;
                    }
                }
            }
            else {
                $result = array();
            }
        }
        
        $return = implode( ', ', $result );

        $settings = incsub_sbe_get_settings();
        if ( current_user_can( 'manage_options' ) && $item->is_confirmed() && 'page' === get_post_type( $settings['manage_subs_page'] ) ) {
            $url = add_query_arg( 'sub_key', $item->subscription_key, get_permalink( $settings['manage_subs_page'] ) );
            $actions['set_subscriber_post_types'] = '<a target="_blank" href="' . esc_url( $url ) . '"> ' . __( 'Set subscriber post types', INCSUB_SBE_LANG_DOMAIN ) . ' <span class="dashicons dashicons-redo"></span></a>';
            $return .= $this->row_actions( $actions );
        }
        return $return;
    }

    function get_columns(){
        $columns = array(
            'cb'                    => '<input type="checkbox" />', //Render a checkbox instead of text
            'email'                 => __( 'Email', INCSUB_SBE_LANG_DOMAIN ),
            'created'               => __( 'Created', INCSUB_SBE_LANG_DOMAIN ),
            'status'                  => __( 'Status', INCSUB_SBE_LANG_DOMAIN ),
            'subscription_type'     => __( 'Subscription Type', INCSUB_SBE_LANG_DOMAIN ),
            'subscribed_to'         => __( 'Subscribed to', INCSUB_SBE_LANG_DOMAIN )
        );
        return $columns;
    }

    function get_bulk_actions() {
        $actions = array(
            'cancel'    => __( 'Cancel subscriptions', INCSUB_SBE_LANG_DOMAIN )
        );
        return $actions;
    }

    function display_tablenav( $which ) {

        if ( 'top' == $which )
            wp_nonce_field( 'bulk-' . $this->_args['plural'] );

        ?>
            <div class="tablenav <?php echo esc_attr( $which ); ?>">

                <div class="alignleft actions">
                    <?php $this->bulk_actions(); ?>
                </div>

                <?php if ( 'top' == $which ): ?>
                    <div class="alignleft actions">
                        <?php submit_button( __( 'Download CSV', INCSUB_SBE_LANG_DOMAIN ), 'secondary', 'sbe-download-csv', false ); ?>
                    </div>
                <?php endif; ?>

                <?php $this->pagination( $which ); ?>

                <br class="clear" />
            </div>
        <?php
    }


    function process_bulk_action() {
        if ( isset( $_POST['sbe-download-csv'] ) ) {
            return;           
        }

        if( 'cancel' === $this->current_action() ) {

            $model = Incsub_Subscribe_By_Email_Model::get_instance();
            if ( ! isset( $_POST['subscriptor'] ) && isset( $_GET['sid'] ) )
                incsub_sbe_cancel_subscription( absint( $_GET['sid'] ) );
            else {
                $subscriptions = $_POST['subscriptor'];
                if ( ! empty( $subscriptions ) ) {
                    foreach ( $subscriptions as $subscription )
                        incsub_sbe_cancel_subscription( absint( $subscription ) );
                }
            }

            ?>
                <div class="updated">
                    <p><?php _e( 'Subscription(s) deleted', INCSUB_SBE_LANG_DOMAIN ); ?></p>
                </div>
            <?php
        }

        if ( 'send_confirmation' == $this->current_action() && isset( $_GET['sid'] ) ) {
            incsub_sbe_send_confirmation_email( absint( $_GET['sid'] ), true );
            ?>
                <div class="updated">
                    <p><?php _e( 'Confirmation mail sent', INCSUB_SBE_LANG_DOMAIN ); ?></p>
                </div>
            <?php
        }

        if ( 'confirm_subscription' == $this->current_action() && isset( $_GET['sid'] ) ) {
            incsub_sbe_confirm_subscription( absint( $_GET['sid'] ) );

            ?>
                <div class="updated">
                    <p><?php _e( 'Subscription confirmed', INCSUB_SBE_LANG_DOMAIN ); ?></p>
                </div>
            <?php

        }

        
    }

    function get_sortable_columns() {
        $sortable_columns = array(
            'email'     => array( 'email', true, 'title' ),
            'created'   => array( 'created', false, 'post_date' )
        );
        return $sortable_columns;
    }

    function prepare_items() {
        global $wpdb, $page;

        $user = get_current_user_id();

        $current_screen = get_current_screen();
        $screen_option = $current_screen->get_option( 'per_page', 'option' );

        $per_page = get_user_meta( $user, $screen_option, true );

        if ( empty ( $per_page ) || $per_page < 1 ) {
            $per_page = $current_screen->get_option( 'per_page', 'default' );
        }
        
        $per_page = apply_filters( 'sbe_subscribers_per_page', $per_page );
      
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = array($columns, $hidden, $sortable);

        $this->process_bulk_action();
        $current_page = $this->get_pagenum();

        $model = incsub_sbe_get_model();

        $args = array(
            'per_page' => $per_page,
            'current_page' => $current_page
        );

        $order = ! empty( $_GET['order'] ) ? strtolower( $_GET['order'] ) : 'asc';
        if ( in_array( $order, array( 'asc', 'desc' ) ) )
            $args['order'] = $order;

        $orderby = ! empty( $_GET['orderby'] ) ? strtolower( $_GET['orderby'] ) : '';
        if ( in_array( $orderby, array_keys( $sortable ) ) )
            $args['orderby'] = $sortable[ $orderby ][2];

        if ( isset( $_POST['s'] ) )
            $args['s'] = stripslashes_deep( $_POST['s'] );

        if ( ! empty( $_GET['filter_status'] ) )
            $args['status'] = $_GET['filter_status'];

        $results = incsub_sbe_get_subscribers( $args );

        $this->items = $results->subscribers;
        $total_subscribers = $results->total;

        $this->set_pagination_args( array(
            'total_items' => $total_subscribers,                 
            'per_page'    => $per_page,              
            'total_pages' => ceil( $total_subscribers / $per_page )  
        ) );
    }
}