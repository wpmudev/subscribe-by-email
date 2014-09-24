<?php $next_scheduled = Incsub_Subscribe_By_Email::get_next_scheduled_date(); ?>
<?php if ( $next_scheduled ): ?>
	<h3><strong><?php _e( 'Next digest will be sent on:', INCSUB_SBE_LANG_DOMAIN ); ?></strong> <code><?php echo $next_scheduled; ?></code></h3>
<?php endif; ?>
<?php $the_table->display(); ?>