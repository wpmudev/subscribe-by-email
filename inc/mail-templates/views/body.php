	<table style="width: 100%;">
		<tbody>
			<tr>
				<td></td>
				<td style="display: block!important; max-width: 600px!important; margin: 0 auto!important; clear: both!important;" bgcolor="#FFFFFF">
					<div style="padding: 15px; max-width: 600px; margin: 0 auto; display: block;">
						<table style="width: 100%;">
							<tbody>
								<tr>
									<td>
										<h2 style="font-weight: 500; font-size: 27px;line-height: 1.1; margin-bottom: 15px; color: #000 !important;">
											<?php echo sbe_get_email_template_subject(); ?>
										</h2>
										<p style="font-size: 17px;margin-bottom: 10px; font-weight: normal; font-size: 14px; line-height: 1.6;">
											<?php echo sbe_get_header_text(); ?>
										</p>
										<hr/>
										<?php sbe_email_template_loop(); ?>												
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