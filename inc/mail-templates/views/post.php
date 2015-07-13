<?php
	$date_format = get_option( 'date_format', get_site_option( 'date_format', 'Y-m-d' ) );
	$date_format = empty( $date_format ) ? 'Y-m-d' : $date_format;
?>

<?php if ( sbe_show_featured_image() && sbe_mail_template_has_post_thumbnail() ): ?>
	<?php sbe_mail_template_the_post_thumbnail( 'thumbnail' ); ?>	
<?php endif; ?>

<div <?php echo sbe_show_featured_image() ? 'style="float:left;width: 394px;"' : ''; ?>>
	<h3 style="margin-top:0;">
		<a style="font-weight: 500; font-size: 21px;line-height: 30px; margin-top:25px; margin-bottom: 10px;" href="<?php sbe_mail_template_the_permalink(); ?>" target="_blank">
			<?php the_title(); ?>
		</a> 
	</h3>
	<div style="margin:1em 0;font-size: 13px;color:#000 !important;line-height: 23px;">
		<?php if ( sbe_mail_template_send_full_post() ): ?>
			<?php sbe_mail_template_the_content(); ?>
		<?php else: ?>
			<?php @the_excerpt(); ?>
		<?php endif; ?>
	</div>
</div>

<div style="clear:both;"></div>

<div style="margin:0em 0 2.2em 0;font-size: 13px;color:#9E9E9E !important; <?php echo sbe_show_featured_image() ? 'float:right;' : 'float:none;'; ?>">
	<?php printf( __( 'by %s on %s', INCSUB_SBE_LANG_DOMAIN ), get_the_author(), get_the_date( $date_format ) ); ?>
</div>

<div style="clear:both;"></div>