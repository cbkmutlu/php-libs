<?php

declare(strict_types=1);

namespace System\Starter;

use System\Router\Router;
use Whoops\Run as WhoopsRun;
use Whoops\Handler\PrettyPageHandler as WhoopsPrettyPageHandler;

class Starter {
   private static $router = null;

   public function __construct(Router $router) {
      $whoops = new WhoopsRun;
      $whoops->pushHandler(new WhoopsPrettyPageHandler);
      $whoops->register();
      self::$router = $router;
   }

   public function env(string $file): void {
      import_env($file);
   }

   public function routes(array $routes): void {
      foreach ($routes as $route) {
         import_file($route);
      }
      self::$router->run();
   }

   public static function router(): Router {
      return self::$router;
   }
}
