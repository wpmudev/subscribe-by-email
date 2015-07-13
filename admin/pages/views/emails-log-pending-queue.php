<?php if ( $next_scheduled ): ?>
	<h3>
		<strong><?php _e( 'Next batch will be sent on:', INCSUB_SBE_LANG_DOMAIN ); ?></strong> 
		<code><?php echo $next_scheduled; ?></code>
		<?php if ( incsub_sbe_is_user_allowed_send_batch() ): ?>
			<a class="button-secondary" href="<?php echo esc_url( add_query_arg( 'sbe_send_batch_now', 'true' ) ); ?>"><?php _e( 'Send batch now', INCSUB_SBE_LANG_DOMAIN ); ?></a>
		<?php endif; ?>
	</h3>
<?php endif; ?>

<form action="" method="POST">
	<?php $the_table->display(); ?>
</form>