<?php

global $wp_version;

$strings = 'tinyMCE.addI18n({' . _WP_Editors::$mce_locale . ': {
	sbe_l10n: {
		title: "' . esc_js( __( 'Insert Subscribe By Email Form', INCSUB_SBE_LANG_DOMAIN ) ) . '"
	}
}});';