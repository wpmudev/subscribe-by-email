<?php

class Subscribe_By_Email_Debugger {
	public $file = null;

	public static $instance;

	public function get_instance() {
		if ( self::$instance ==  null )
			return new self();
		return $instance;
	}

	public function __construct() {
		$this->file = INCSUB_SBE_PLUGIN_DIR . 'sbe_debug.log';
	}

	public function debug( $message ) {

		if ( ! defined( 'INCSUB_SBE_DEBUG' ) )
			return false;

		if ( is_multisite() ) {
			if ( defined( 'INCSUB_SBE_DEBUG_BLOG_ID' ) && INCSUB_SBE_DEBUG_BLOG_ID != get_current_blog_id() )
				return false;
		}

		$bTrace = debug_backtrace(); // assoc array

	    /* Build the string containing the complete log line. */
	    $line = PHP_EOL.sprintf('[%s, <%s>, (%d)]==> %s', 
	                            date("Y/m/d h:i:s", mktime()),
	                            basename($bTrace[0]['file']), 
	                            $bTrace[0]['line'], 
	                            $message );
	    
	    // log to file
	    file_put_contents($this->file,$line,FILE_APPEND);
	    
	    return true;
	}
}