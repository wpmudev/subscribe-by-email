<?php if ( $next_scheduled ): ?>
	<h3><strong><?php _e( 'Next digest will be sent on:', INCSUB_SBE_LANG_DOMAIN ); ?></strong> <code><?php echo $next_scheduled; ?></code></h3>
<?php endif; ?>

<form action="" method="POST">
	<?php $the_table->display(); ?>
</form>