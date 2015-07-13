	<table style="width: 100%;">
		<tbody>
			<tr>
				<td></td>
				<td style="display: block!important; max-width: 600px!important; margin: 0 auto!important; clear: both!important;" bgcolor="#EFEFEF">
					<div style="padding: 15px; max-width: 600px; margin: 0 auto; display: block;">
						<table style="width: 100%;">
							<tbody>
								<tr>
									<td style="font-size:11px;color:#666 !important;">
										<p>
											<?php printf( __( 'You are subscribed to email updates from <a href="%s">%s</a>', INCSUB_SBE_LANG_DOMAIN ), get_home_url(), get_bloginfo( 'name' ) ); ?>  <br/>
											<?php if ( sbe_get_manage_subscriptions_page_id() ): ?>
												<?php printf( __( 'To manage your subscriptions, <a href="%s">click here</a>.', INCSUB_SBE_LANG_DOMAIN ), esc_url( sbe_email_template_get_manage_subscriptions_url() ) ); ?> <br/>	
											<?php endif; ?>
											<?php printf( __( 'To stop receiving these emails, <a href="%s">click here</a>.', INCSUB_SBE_LANG_DOMAIN ), esc_url( sbe_email_template_get_unsubscribe_url() ) ); ?> 
										</p>
										<p><?php echo sbe_get_footer_text(); ?></p>
									</td>
								</tr>
							</tbody>
						</table>
					</div>
				</td>
				<td></td>
			</tr>	
		</tbody>
	</table>
</div>