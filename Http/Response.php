<?php

declare(strict_types=1);

namespace System\Http;

class Response {
   public $codes;

   public function __construct() {
      $this->codes = import_config('defines.status');
   }

   public function status(?int $code = null): int {
      if (is_null($code)) {
         return http_response_code();
      }
      return http_response_code($code);
   }

   public function message(?int $code = null): string {
      if (is_null($code)) {
         return $this->codes[$this->status()];
      }

      return $this->codes[$code];
   }

   public function success(mixed $data = null, ?string $message = null, int $code = 200): void {
      $this->json($message, $data, null, $code);
   }

   public function error(?string $message = null, mixed $error = null, int $code = 400): void {
      $this->json($message, null, $error, $code);
   }

   public function json(?string $message = null, mixed $data = null, mixed $error = null, int $code = 200, ?array $meta = null): void {
      header_remove();
      $this->status($code);

      if (ENV === 'production') {
         if ($code >= 200 && $code < 300) {
            header('Cache-Control: no-transform, public, max-age=300, s-maxage=900');
         } else {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
         }
      }

      header('Content-type: application/json');

      $response = [
         'status' => $code < 300,
         'message' => $message,
         'data' => $data,
         'error' => $error,
         'meta' => $meta
      ];

      print(json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
   }
}
