<?php

class Subscribe_By_Email_Logger {

	private $filename = null;

	public function __construct( $log_id ) {
		$this->filename = self::get_filename( $log_id );
		$this->create_logs_folder();
	}

	public function write( $email, $message ) {
		global $wp_filesystem;

		$date = current_time( 'timestamp' );
		$line = $email . '|' . $date . '|' . $message;
		$fp = @fopen( $this->filename, 'a+' );

		if ( ! ( $fp ) )
			return false;
		@fwrite( $fp, $line . "\n" );
		@fclose( $fp );
		return true;
	}

	public function touch() {
		if ( $fh = @fopen( $this->filename, 'w' ) ) {
			fwrite( $fh, '' );
			fclose( $fh );
		}
	}


	public function create_logs_folder() {
		global $wp_filesystem;

		$is_dir = is_dir( INCSUB_SBE_LOGS_DIR );

		if ( ! $is_dir ) {
			wp_mkdir_p( INCSUB_SBE_LOGS_DIR );

			// .htaccess
			if ( $fh = @fopen( INCSUB_SBE_LOGS_DIR . '/.htaccess', 'w' ) ) {
				fwrite( $fh, 'deny from all' );
				fclose( $fh );
			}

			// index.html
			if ( $fh = @fopen( INCSUB_SBE_LOGS_DIR . '/index.html', 'w' ) ) {
				fwrite( $fh, '' );
				fclose( $fh );
			}
		}
	}

	public static function get_filename( $log_id ) {
		return INCSUB_SBE_LOGS_DIR . '/sbe_log_' . $log_id . '.log';
	}

	public static function open_log( $log_id ) {
		$filename = self::get_filename( $log_id );
		return @fopen( $filename, 'r' );
	}

	public static function read_line( $fp ) {
		return fgets( $fp );
	}

	public static function delete_log( $log_id ) {
		$filename = self::get_filename( $log_id );
		unlink( $filename );
	}
}