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

   public function success(string $message, mixed $data = null, int $code = 200): void {
      $this->json($message, $data, null, $code);
   }

   public function error(string $message, mixed $data = null, int $code = 417): void {
      $this->json($message, $data, null, $code);
   }

   public function json(string $message, mixed $data = null, mixed $errors = null, int $code = 200, array $meta = []): void {
      header_remove();
      $this->status($code);

      header("Cache-Control: no-transform,public,max-age=300,s-maxage=900");
      header('Content-type: application/json');
      header($_SERVER['SERVER_PROTOCOL'] . ' ' . $code . ' ' . $this->codes[$code], true, $code);

      $response = [
         'success' => $code < 300,
         'message' => $message,
      ];

      if (!is_null($data)) {
         $response['data'] = $data;
      } elseif (!is_null($errors)) {
         $response['errors'] = $errors;
      }

      if (!empty($meta)) {
         $response['meta'] = $meta;
      }

      print(json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
   }
}
