<?php

declare(strict_types=1);

namespace System\Secure;

use System\Exception\SystemException;

class Hash {
   private $hash_cost;
   private $hash_algorithm;

   public function __construct() {
      $config = import_config('defines.secure');
      $this->hash_cost = $config['hash_cost'];
      $this->hash_algorithm = $config['hash_algorithm'];
   }

   public function create(string $value, array $options = []): string {
      if (!isset($options['cost'])) {
         $options['cost'] = $this->hash_cost;
      }

      $hash = password_hash($value, $this->hash_algorithm, $options);

      if (!$hash) {
         throw new SystemException('Hash not supported');
      }

      return $hash;
   }

   public function verify(string $value, string $hash): bool {
      return password_verify($value, $hash);
   }

   public function refresh(string $hash, array $options = []): bool {
      if (!isset($options['cost'])) {
         $options['cost'] = $this->hash_cost;
      }

      return password_needs_rehash($hash, $this->hash_algorithm, $options);
   }
}
