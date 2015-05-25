<?php

class SBE_Mail_Template extends Abstract_SBE_Mail_Template {

	public function excerpt_more() {
		return ' <a href="'. get_permalink( get_the_ID() ) . '">' . __( 'Read more...', INCSUB_SBE_LANG_DOMAIN ) . '</a>';
	}

	public function excerpt_length() {
		return 25;
	}

	
}