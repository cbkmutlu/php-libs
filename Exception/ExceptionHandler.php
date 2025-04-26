<?php

declare(strict_types=1);

namespace System\Exception;

use Exception;

class ExceptionHandler extends Exception {
   public function __construct(string $body, int $code = 417) {
      $content = isset($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'application/json');
      $accept = isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json');

      if ($content || $accept) {
         header_remove();
         http_response_code($code);
         header("Cache-Control: no-transform,public,max-age=300,s-maxage=900");
         header('Content-type: application/json');

         print(json_encode([
            'status' => false,
            'code' => $code,
            'message' => $body,
            'data' => null
         ]));
         exit();
      }

      parent::__construct(strip_tags($body), $code);
   }
}
