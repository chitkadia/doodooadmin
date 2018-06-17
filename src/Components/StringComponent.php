<?php
/**
 * Library for string related operations
 */
namespace App\Components;

class StringComponent {

    /**
     * Generate random string of specific length
     *
     * @param $length (integer): Length of string to generate
     *
     * @return (string) Random generated string
     */
    public static function generateRandomString($length = 10) {
        $alphabet = "_0123456789@$*abcdefghijklmnopqrstuvwxyz";
        $alphabet_length = strlen($alphabet) - 1;

        $random_string = "";
        for ($i = 1; $i <= $length; $i++) {
            $random_string .= $alphabet[mt_rand(0, $alphabet_length)];
        }

        return $random_string;
    }

    /**
     * Generate saleshandy account number
     *
     * @return (string) Account number
     */
    public static function generateAccountNumber() {
        $random_string = "SHAPP-" . date("YmdHis") . "-" . rand("111", "999");

        return $random_string;
    }

    /**
     * Encode database ids to secured string values
     *
     * @param $val (misc): Id of row
     *
     * @return (string) Encoded string of row id
     */
    public static function encodeRowId($val) {
        $val = $val;

        $hashid_object = new \Hashids\Hashids(ENCODE_DECODE_SALT, ENCODE_DECODE_ID_LENGTH);
        return $hashid_object->encode($val);
    }

    /**
     * Decode string values to database ids
     *
     * @param $val (string): Encoded string of row id
     * @param $full (boolean): True - returns array of values, False - returns single value
     *
     * @return (misc) Decoded value row id
     */
    public static function decodeRowId($val, $full = false) {
        $val = (string) $val;

        $hashid_object = new \Hashids\Hashids(ENCODE_DECODE_SALT, ENCODE_DECODE_ID_LENGTH);
        $return_data = $hashid_object->decode($val);

        if ($full) {
            return $return_data;
        } else {
            if (count($return_data) > 1)
                return null;
            else if (count($return_data) == 1)
                return $return_data[0];
            else
                return null;
        }
    }

    /**
    * Function to decrypt string (used to convert long string to ids) (Reverse of encryptID()) 
    * Taken from v1 old app
    * 
    * @param $string: data to decrypt
    *
    * @return $return_string: decrypted string
    */
    public static function decryptID($string) {
        $return_string = $string;

        if(strlen($string) > 14) {
            $return_string = substr($return_string, 14);
        }

        return $return_string;
    }

    /**
    * Function to encryptor string (used to encrypt or decrypt id)
    * Taken from v1 old app
    *
    * @param $action: action to do encrypt or decrypt
    * @param $string: data to encrypt or decrypt as per action
    *
    * @return $return_string: decrypted string
    */    
    public static function encryptor($action, $string) {
        $output = false;
        $encrypt_method = "AES-128-CBC";
        $secret_key = 'bharat';
        $secret_iv = 'bharat';
        // hash
        $key = hash('sha256', $secret_key);
        $iv = substr(hash('sha256', $secret_iv), 8, 16);

        //do the encyption given text/string/number
        if ($action == 'encrypt') {
            $output = openssl_encrypt($string, $encrypt_method, $key, 0, $iv);
            $output = base64_encode($output);
        } else if ($action == 'decrypt') {
            //decrypt the given text/string/number
            $output = openssl_decrypt(base64_decode($string), $encrypt_method, $key, 0, $iv);
        }
        return $output;
    }

    /**
     * Generate salt values for password hashing
     * @courtsey: https://alias.io/2010/01/store-passwords-safely-with-php-and-mysql/
     *
     * @return (string) Salt value
     */
    public static function generatePasswordSalt() {
        // A higher "cost" is more secure but consumes more processing power
        $cost = 10;

        // Create a random salt
        $salt = strtr(base64_encode(mcrypt_create_iv(16, MCRYPT_DEV_URANDOM)), "+", ".");

        // Prefix information about the hash so PHP knows how to verify it later.
        // "$2a$" Means we're using the Blowfish algorithm. The following two digits are the cost parameter.
        $salt = sprintf("$2a$%02d$", $cost) . $salt;

        return $salt;
    }

    /**
     * Encrypt plain text password
     * @courtsey: https://alias.io/2010/01/store-passwords-safely-with-php-and-mysql/
     *
     * @param $input (string): Password to encrypt
     * @param $salt (string): Salt key to encrypt password
     *
     * @return (string) Encrypted password
     */
    public static function encryptPassword($input, $salt) {
        $hash = crypt($input, $salt);

        return $hash;
    }

    /**
     * Verify password validity
     * @courtsey: https://alias.io/2010/01/store-passwords-safely-with-php-and-mysql/
     *
     * @param $input (string): Password to encrypt
     * @param $salt (string): Salt key to encrypt password
     * @param $password (string): Encrypted password
     *
     * @return (boolean) True if password is correct, otherwise false
     */
    public static function decryptPassword($input, $salt, $password) {
        if (hash_equals($password, crypt($input, $salt))) {
            return true;
            
        } else {
            return false;
        }
    }

}
