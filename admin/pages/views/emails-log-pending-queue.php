<?php if ( $next_scheduled ): ?>
	<h3>
		<strong><?php _e( 'Next batch will be sent on:', INCSUB_SBE_LANG_DOMAIN ); ?></strong> 
		<code><?php echo $next_scheduled; ?></code>
		<a class="button-secondary" href="<?php echo esc_url( add_query_arg( 'sbe_send_batch_now', 'true' ) ); ?>"><?php _e( 'Send batch now', INCSUB_SBE_LANG_DOMAIN ); ?></a>
	</h3>
<?php endif; ?>

<form action="" method="POST">
	<?php $the_table->display(); ?>
</form>