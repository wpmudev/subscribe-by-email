<?php

class Subscribe_By_Email_Debugger {
	public $file = null;
	public static $instance = null;

	public static function get_instance() {
		if ( self::$instance ==  null )
			self::$instance = new self();
		return self::$instance;
	}

	public function __construct() {
		$this->file = INCSUB_SBE_PLUGIN_DIR . 'sbe_debug.log';
	}

	public function debug( $message ) {

		$bTrace = debug_backtrace(); // assoc array

	    /* Build the string containing the complete log line. */
	    $line = PHP_EOL.sprintf('[%s, <%s>, (%d)]==> %s', 
	                            date("Y/m/d h:i:s", time()),
	                            basename($bTrace[0]['file']), 
	                            $bTrace[0]['line'], 
	                            $message );
	    
	    // log to file
	    $result = file_put_contents($this->file,$line,FILE_APPEND);
	    
	    return true;
	}
}
