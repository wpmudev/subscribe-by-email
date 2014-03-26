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
            	$item->get_subscription_ID()
        );
    }

    function column_email( $item ) {

        $edit_link = add_query_arg( array( 'action' => 'edit', 'sid' => absint( $item->get_subscription_ID() ) ) );
    	$actions = array(
            'edit'    => sprintf( __( '<a href="%s">%s</a>', INCSUB_SBE_LANG_DOMAIN ), 
                esc_url( $edit_link ),
                __( 'Edit', INCSUB_SBE_LANG_DOMAIN )
            ),
            'cancel'    => sprintf( __( '<span class="trash"><a class="trash" href="%s">%s</a></span>', INCSUB_SBE_LANG_DOMAIN ), 
            	esc_url( add_query_arg( array( 'action' => 'cancel', 'sid' => absint( $item->get_subscription_ID() ) ) ) ),
            	__( 'Cancel subscription', INCSUB_SBE_LANG_DOMAIN )
            ),
            
        );

        if ( $item->get_confirmation_flag() == 0 ) {
            $actions['send_confirmation'] = sprintf( __( '<span><a class="trash" href="%s">%s</a></span>', INCSUB_SBE_LANG_DOMAIN ), 
                esc_url( add_query_arg( array( 'action' => 'send_confirmation', 'sid' => absint( $item->get_subscription_ID() ) ) ) ),
                __( 'Resend confirmation mail', INCSUB_SBE_LANG_DOMAIN )
            );
            $actions['confirm_subscription'] = sprintf( __( '<span><a class="trash" href="%s">%s</a></span>', INCSUB_SBE_LANG_DOMAIN ), 
                esc_url( add_query_arg( array( 'action' => 'confirm_subscription', 'sid' => absint( $item->get_subscription_ID() ) ) ) ),
                __( 'Confirm Subscription', INCSUB_SBE_LANG_DOMAIN )
            );
        }
        
        return '<a href="' . esc_url( $edit_link ) . '">' . $item->get_subscription_email() . '</a>' . $this->row_actions( $actions );
    }

    function column_created( $item ) { 
        return date_i18n( get_option( 'date_format' ), (int)$item->get_subscription_created() );
    }

    function column_note( $item ) {
        $confirmation_flag_captions = incsub_sbe_get_confirmation_flag_captions();
        return isset( $confirmation_flag_captions[ $item->get_confirmation_flag() ] ) ? $confirmation_flag_captions[ $item->get_confirmation_flag() ] : '';
    }

    function column_subscription_type( $item ) {
        return $item->get_subscription_note();
    }

    function column_subscribed_to( $item ) {
        $result = array();

        if ( $item->get_confirmation_flag() ) {
            $post_types = $item->get_post_types();
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
        
        return implode( ', ', $result );
    }

    function get_columns(){
        $columns = array(
            'cb'                    => '<input type="checkbox" />', //Render a checkbox instead of text
            'email'                 => __( 'Email', INCSUB_SBE_LANG_DOMAIN ),
            'created'               => __( 'Created', INCSUB_SBE_LANG_DOMAIN ),
            'note'                  => __( 'Note', INCSUB_SBE_LANG_DOMAIN ),
            'subscription_type'     => __( 'Subscription Type', INCSUB_SBE_LANG_DOMAIN ),
            'subscribed_to'  	    => __( 'Subscribed to', INCSUB_SBE_LANG_DOMAIN )
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
        	if ( ! isset( $_POST['subscriptor'] ) && isset( $_GET['sid'] ) ) {
                $subscriber = $model->get_subscriber( absint( $_GET['sid'] ) );

                if ( $subscriber )
        			$model->cancel_subscription( $subscriber->user_key );
        	}
        	else {
        		$subscriptions = $_POST['subscriptor'];
        		if ( ! empty( $subscriptions ) ) {
        			foreach ( $subscriptions as $subscription ) {
                        $subscriber = $model->get_subscriber( $subscription );
                        if ( $subscriber )
                        	$model->cancel_subscription( $subscriber->user_key );
					}
        		}
        	}

        	?>
				<div class="updated">
					<p><?php _e( 'Subscription(s) deleted', INCSUB_SBE_LANG_DOMAIN ); ?></p>
				</div>
        	<?php
        }

        if ( 'send_confirmation' == $this->current_action() && isset( $_GET['sid'] ) ) {
            Incsub_Subscribe_By_Email::send_confirmation_mail( absint( $_GET['sid'] ) );
            ?>
                <div class="updated">
                    <p><?php _e( 'Confirmation mail sent', INCSUB_SBE_LANG_DOMAIN ); ?></p>
                </div>
            <?php
        }

        if ( 'confirm_subscription' == $this->current_action() && isset( $_GET['sid'] ) ) {
            $model = Incsub_Subscribe_By_Email_Model::get_instance();
            $subscriber = $model->get_subscriber( absint( $_GET['sid'] ) );

            if ( ! empty( $subscriber ) ) {
                $key = $model->get_user_key( $subscriber->subscription_email );
                $model->confirm_subscription( $key );
                ?>
                    <div class="updated">
                        <p><?php _e( 'Subscription confirmed', INCSUB_SBE_LANG_DOMAIN ); ?></p>
                    </div>
                <?php
            }
            
        }

        
    }

    function get_sortable_columns() {
    	$sortable_columns = array(
            'email'     => array( 'subscription_email', isset( $_GET['orderby'] ) && isset( $_GET['order'] ) && 'subscription_email' == $_GET['orderby'] ? $_GET['order'] : false ),
            'created'   => array( 'subscription_created', isset( $_GET['orderby'] ) && isset( $_GET['order'] ) && 'subscription_created' == $_GET['orderby'] ? $_GET['order'] : false ),
            'subscription_type'   => array( 'subscription_note', isset( $_GET['orderby'] ) && isset( $_GET['order'] ) && 'subscription_note' == $_GET['orderby'] ? $_GET['order'] : false ),
        );
        return $sortable_columns;
    }

    function prepare_items() {
        global $wpdb, $page;

        $user = get_current_user_id();

        $current_screen = get_current_screen();
        $screen_option = $current_screen->get_option( 'per_page', 'option' );

        $per_page = get_user_meta( $user, $screen_option, true );
        if ( empty ( $per_page) || $per_page < 1 ) {
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
            'current_page' => $current_page,
            'sort' => $sortable,
            'sort_type' => 'ASC',
        );

        foreach ( $sortable as $value ) {
            if ( $value[1] ) {
                $args['sort'] = $value[0];
                $args['sort_type'] = $value[1];
                break;
            }
        }

        if ( isset( $_POST['s'] ) )
            $args['s'] = stripslashes_deep( $_POST['s'] );

        $this->items = incsub_sbe_get_subscribers( $args );
        $total_subscribers = $model->get_all_subscribers( true );

        $this->set_pagination_args( array(
            'total_items' => $total_subscribers,                 
            'per_page'    => $per_page,              
            'total_pages' => ceil( $total_subscribers / $per_page )  
        ) );
    }
}