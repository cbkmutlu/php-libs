<?php

declare(strict_types=1);

namespace System\Secure;

use System\Exception\ExceptionHandler;

class Crypt {
   private $crypt_algorithm;
   private $crypt_phrase;
   private $crypt_key;

   public function __construct() {
      $config = import_config('defines.secure');
      $this->crypt_algorithm = $config['crypt_algorithm'];
      $this->crypt_phrase = $config['crypt_phrase'];
      $this->crypt_key = $config['crypt_key'];
   }

   public function encode(string $value, ?string $key = null): string {
      if (is_null($key)) {
         $key = $this->crypt_key;
      }

      $iv = random_bytes(openssl_cipher_iv_length($this->crypt_algorithm));
      $encrypted = openssl_encrypt($value, $this->crypt_algorithm, hash($this->crypt_phrase, $key, true), 0, $iv);

      return strtr(base64_encode($iv . $encrypted), '+/=', '-,');
   }

   public function decode(string $value, ?string $key = null): string {
      if (is_null($key)) {
         $key = $this->crypt_key;
      }

      $data = base64_decode(strtr($value, '-,', '+/='));
      $iv_length = openssl_cipher_iv_length($this->crypt_algorithm);
      $iv = substr($data, 0, $iv_length);
      $encrypted = substr($data, $iv_length);
      $decrypted = openssl_decrypt($encrypted, $this->crypt_algorithm, hash($this->crypt_phrase, $key, true), 0, $iv);

      if (!$decrypted) {
         throw new ExceptionHandler('Decoding failed');
      }

      return trim($decrypted);
   }
}
