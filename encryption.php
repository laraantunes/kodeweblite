<?php
// encryption.php - Encryption utility for KodeWeb Lite credentials

class KodeWebEncryption {
    private static $key_file = __DIR__ . '/.key';
    private static $cipher = 'aes-256-cbc';

    /**
     * Retrieve or generate the encryption key.
     */
    private static function getKey() {
        if (!file_exists(self::$key_file)) {
            // Generate a secure random 32-byte key (256 bits)
            $key = openssl_random_pseudo_bytes(32);
            if ($key === false) {
                // Fallback if openssl_random_pseudo_bytes fails
                $key = random_bytes(32);
            }
            file_put_contents(self::$key_file, $key);
            @chmod(self::$key_file, 0600);
        } else {
            $key = file_get_contents(self::$key_file);
        }
        return $key;
    }

    /**
     * Encrypt plain text using AES-256-CBC.
     */
    public static function encrypt($data) {
        $key = self::getKey();
        $iv_length = openssl_cipher_iv_length(self::$cipher);
        $iv = openssl_random_pseudo_bytes($iv_length);
        if ($iv === false) {
            $iv = random_bytes($iv_length);
        }
        
        $encrypted = openssl_encrypt($data, self::$cipher, $key, 0, $iv);
        
        // Return base64 encoded IV + encrypted data
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt AES-256-CBC encrypted text.
     */
    public static function decrypt($data) {
        $key = self::getKey();
        $decoded = base64_decode($data);
        if ($decoded === false) {
            return false;
        }
        
        $iv_length = openssl_cipher_iv_length(self::$cipher);
        if (strlen($decoded) < $iv_length) {
            return false;
        }
        
        $iv = substr($decoded, 0, $iv_length);
        $encrypted = substr($decoded, $iv_length);
        
        return openssl_decrypt($encrypted, self::$cipher, $key, 0, $iv);
    }
}
