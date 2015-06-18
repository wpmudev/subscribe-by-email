<?php

global $wp_version;

$strings = 'tinyMCE.addI18n({' . _WP_Editors::$mce_locale . ': {
	sbe_l10n: {
		title: "' . esc_js( __( 'Insert Subscribe By Email Form', INCSUB_SBE_LANG_DOMAIN ) ) . '",
		auto_opt_field_title: "' . esc_js( __( 'Auto Opt-In subscribers', INCSUB_SBE_LANG_DOMAIN ) ) . '"
	}
}});';