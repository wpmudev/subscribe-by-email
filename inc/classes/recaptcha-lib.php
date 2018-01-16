<?php

/**
* From: https://github.com/google/recaptcha/blob/1.0.0/php/example-captcha.php
*/


class SBE_ReCaptchaResponse
{
    public $success;
    public $errorCodes;
}


class SBE_ReCaptcha
{
    private static $_signupUrl = "https://www.google.com/recaptcha/admin";
    private static $_siteVerifyUrl = "https://www.google.com/recaptcha/api/siteverify?";
    private $_secret;
    private static $_version = "php_1.0";
    /**
     * Constructor.
     *
     * @param string $secret shared secret between site and ReCAPTCHA server.
     */
    function __construct($secret)
    {
        if ($secret == null || $secret == "") {
            die("To use reCAPTCHA you must get an API key from <a href='"
                . self::$_signupUrl . "'>" . self::$_signupUrl . "</a>");
        }
        $this->_secret=$secret;
    }
    /**
     * Encodes the given data into a query string format.
     *
     * @param array $data array of string elements to be encoded.
     *
     * @return string - encoded request.
     */
    private function _encodeQS($data)
    {
        $req = "";
        foreach ($data as $key => $value) {
            $req .= $key . '=' . urlencode(stripslashes($value)) . '&';
        }
        // Cut the last '&'
        $req=substr($req, 0, strlen($req)-1);
        return $req;
    }
 
    /**
     * Calls the reCAPTCHA siteverify API to verify whether the user passes
     * CAPTCHA test.
     *
     * @param string $remoteIp   IP address of end user.
     * @param string $response   response string from recaptcha verification.
     *
     * @return ReCaptchaResponse
     */
    public function verifyResponse( $remoteIp, $response )
    {
        // Discard empty solution submissions
        if ( $response == null || strlen( $response ) == 0 ) {

            $recaptchaResponse = new SBE_ReCaptchaResponse();
            $recaptchaResponse->success = false;
            $recaptchaResponse->errorCodes = 'missing-input';

            return $recaptchaResponse;
            
        }

        $response = wp_remote_post( 
            self::$_siteVerifyUrl, 
            array( 
                'body' => array (
                    'secret' => $this->_secret,
                    'remoteip' => $remoteIp,
                    'v' => self::$_version,
                    'response' => $response
                )
            ) 
        );

        if ( is_wp_error( $response ) ) {

            $error_message = $response->get_error_message();
            $recaptchaResponse->success = false;
            $recaptchaResponse->errorCodes = $error_message;

            return $recaptchaResponse;

        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = json_decode( wp_remote_retrieve_body( $response ), true );

        $recaptchaResponse = new SBE_ReCaptchaResponse();

        if ( 200 == $response_code && isset( $response_body['success'] ) && trim( $response_body['success'] ) == true ) {
            $recaptchaResponse->success = true;
        } else {
            $recaptchaResponse->success = false;
            $recaptchaResponse->errorCodes = isset( $response_body['error-codes'] ) ? $response_body['error-codes'] : 'Invalid response code';
        }

        return $recaptchaResponse;
    }
}