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
		global $wp_filesystem;

		$wp_filesystem->touch( $this->filename );
		
		return true;
	}

	public static function set_direct_filesystem_method() {
		return 'direct';
	}

	public function create_logs_folder() {
		global $wp_filesystem;

		if ( null == $wp_filesystem ) {
			WP_Filesystem();
		}

		$is_dir = $wp_filesystem->is_dir( INCSUB_SBE_LOGS_DIR );

		if ( ! $is_dir ) {
			$result = $wp_filesystem->mkdir( INCSUB_SBE_LOGS_DIR );

			// .htaccess
			$file = INCSUB_SBE_LOGS_DIR . '/.htaccess';
			if ( $wp_filesystem->is_file( $file ) )
				$wp_filesystem->delete( $file );

			$wp_filesystem->touch( $file );
			$wp_filesystem->put_contents( INCSUB_SBE_LOGS_DIR . '/.htaccess', 'deny from all' );

			// index.html
			$file = INCSUB_SBE_LOGS_DIR . '/index.html';
			$wp_filesystem->touch( $file );
		}
	}

	public static function get_filename( $log_id ) {
		return INCSUB_SBE_LOGS_DIR . '/sbe_log_' . $log_id . '.log';
	}

	public static function open_log( $log_id ) {
		global $wp_filesystem;

		if ( null == $wp_filesystem ) {
			WP_Filesystem();
		}

		$filename = self::get_filename( $log_id );
		return @fopen( $filename, 'r' );
	}

	public static function read_line( $fp ) {
		return fgets( $fp );
	}

	public static function delete_log( $log_id ) {
		global $wp_filesystem;

		if ( null == $wp_filesystem ) {
			WP_Filesystem();
		}

		$filename = self::get_filename( $log_id );

		$wp_filesystem->delete( $filename );
	}
}