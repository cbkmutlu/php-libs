<?php

declare(strict_types=1);

namespace System\Secure;

use System\Session\Session;

class Csrf {
   public function __construct(
      private Session $session
   ) {
   }

   public function create(): string {
      $token = base64_encode(random_bytes(32));
      if ($this->session->status()) {
         $this->session->save('session_csrf', $token);
      }

      return $token;
   }

   public function verify(string $token): bool {
      if ($this->session->exist('session_csrf') && hash_equals($this->session->read('session_csrf'), $token)) {
         $this->session->delete('session_csrf');
         return true;
      }

      return false;
   }

   public function input(string $name = 'csrf', bool $id = false): string {
      if ($id) {
         return '<input type="hidden" name="' . htmlspecialchars($name) . '" id="' . ($id ? htmlspecialchars($name) : '') . '" value="' . $this->create() . '">';
      } else {
         return '<input type="hidden" name="' . htmlspecialchars($name) . '" value="' . $this->create() . '">';
      }
   }
}
