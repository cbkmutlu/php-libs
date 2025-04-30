<?php

declare(strict_types=1);

namespace System\Exception;

use Exception;

class ExceptionHandler extends Exception {
   public function __construct(string $message, int $code = 500, bool $json = false) {
      $content = isset($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'application/json');
      $accept = isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json');

      if (($content || $accept) && $json) {
         header_remove();
         http_response_code($code);

         header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
         header('Pragma: no-cache');
         header('Content-type: application/json');

         print(json_encode([
            'status' => false,
            'message' => $message
         ]));
         exit();
      }

      parent::__construct(strip_tags($message), $code);
   }
}
