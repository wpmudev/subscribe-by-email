<?php

/**
 * Get the plugin settings
 * 
 * @return Array of settings
 */
function incsub_sbe_get_settings() {
	$settings_handler = Incsub_Subscribe_By_Email_Settings_Handler::get_instance();
	return $settings_handler->get_settings();
}

/**
 * Get the plugin default settings
 * 
 * @return Array of settings
 */
function incsub_sbe_get_default_settings() {
	$settings_handler = Incsub_Subscribe_By_Email_Settings_Handler::get_instance();
	return $settings_handler->get_default_settings();
}

/**
 * Update the plugin settings
 */
function incsub_sbe_update_settings( $settings ) {
	$settings_handler = Incsub_Subscribe_By_Email_Settings_Handler::get_instance();
	$settings_handler->update_settings( $settings );
}

/**
 * Get the settings slug
 * 
 * @return String
 */
function incsub_sbe_get_settings_slug() {
	$settings_handler = Incsub_Subscribe_By_Email_Settings_Handler::get_instance();
	return $settings_handler->get_settings_slug();
}

/**
 * Get the allowed frequency for the digests
 * 
 * @return Array
 */
function incsub_sbe_get_digest_frequency() {
	$settings_handler = Incsub_Subscribe_By_Email_Settings_Handler::get_instance();
	return $settings_handler->get_frequency();
}

/**
 * Get the allowed frequency times for the digests
 * 
 * @return Array
 */
function incsub_sbe_get_digest_times() {
	$settings_handler = Incsub_Subscribe_By_Email_Settings_Handler::get_instance();
	return $settings_handler->get_time();
}

/**
 * Get the allowed days of week for the digests
 * 
 * @return Array
 */
function incsub_sbe_get_digest_days_of_week() {
	$settings_handler = Incsub_Subscribe_By_Email_Settings_Handler::get_instance();
	return $settings_handler->get_day_of_week();
}

/**
 * Get the Captions for the confirmation Flags
 * 
 * @return Array
 */
function incsub_sbe_get_confirmation_flag_captions() {
	$settings_handler = Incsub_Subscribe_By_Email_Settings_Handler::get_instance();
	return $settings_handler->get_confirmation_flag();
}
