<?php

declare(strict_types=1);

namespace System\Exception;

use Throwable;
use ErrorException;

use System\Http\Response;

use Whoops\Run as WhoopsRun;
use Whoops\Handler\PrettyPageHandler as WhoopsPrettyPageHandler;

class ExceptionHandler {
   private static Response $response;

   public function __construct(
      Response $response
   ) {
      if (ENV === 'production') {
         error_reporting(0);
         ini_set('display_errors', 0);
         ini_set('display_startup_errors', 0);
      } else {
         error_reporting(E_ALL);
         ini_set('display_errors', 1);
         ini_set('display_startup_errors', 1);
      }
      self::$response = $response;
   }

   public static function handleError($errno, $errstr, $errfile, $errline) {
      $report = error_reporting();
      if ($report & $errno) {
         $exit = false;
         switch ($errno) {
            case E_USER_ERROR:
               $type = 'Fatal Error';
               $exit = true;
               break;
            case E_USER_WARNING:
            case E_WARNING:
               $type = 'Warning';
               break;
            case E_USER_NOTICE:
            case E_NOTICE:
               $type = 'Notice';
               break;
            case @E_RECOVERABLE_ERROR:
               $type = 'Catchable';
               break;
            default:
               $type = 'Unknown Error';
               $exit = true;
               break;
         }

         $exception = new ErrorException($type . ': ' . $errstr, 0, $errno, $errfile, $errline);

         if ($exit) {
            exit();
         } else {
            throw $exception;
         }
      }
      return false;
   }

   public static function handleException(Throwable $exception): void {
      $content = isset($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'application/json');
      $accept = isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json');

      if ($content || $accept) {
         self::resultApi($exception);
      } else {
         self::resultWeb($exception);
      }
   }

   public static function resultApi(Throwable $exception): void {
      $message = $exception->getMessage();
      $code = $exception->getCode();
      self::$response->json($message, null, null, $code);
   }

   public static function resultWeb(Throwable $exception): void {
      $whoops = new WhoopsRun;
      $whoops->pushHandler(new WhoopsPrettyPageHandler);
      $whoops->register();
      throw $exception;
   }
}
