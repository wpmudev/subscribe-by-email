
<div style="font-family: 'Helvetica Neue', 'Helvetica', Helvetica, Arial, sans-serif !important;">
	<table style="width: 100%;" class="header-bg-color" bgcolor="<?php echo sbe_get_header_color(); ?>">
		<tbody>
			<tr>
				<td></td>
				<td style="display: block!important; max-width: 600px!important; margin: 0 auto!important; clear: both!important;">
					<div style="padding: 15px; max-width: 600px; margin: 0 auto; display: block;">
						<table style="width: 100%;" class="header-bg-color" bgcolor="<?php echo sbe_get_header_color(); ?>">
							<tbody>
								<tr>
									<td><a href="<?php echo get_home_url(); ?>"><img style="max-width:<?php echo sbe_get_logo_width(); ?>px;" src="<?php echo sbe_get_logo(); ?>"></a></td>
									<td align="right">
										<?php if ( sbe_display_blog_name() ): ?>
											<h6><a style="text-decoration:none !important; margin: 0 !important; padding:0;font-weight: 900; font-size: 14px; text-transform: uppercase; color: <?php echo sbe_get_header_text_color(); ?>;" class="header-text-color" href="<?php echo get_home_url(); ?>"><?php echo sbe_get_from_sender(); ?></a></h6>
										<?php endif; ?>
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
