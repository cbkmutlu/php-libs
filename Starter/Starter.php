<?php

declare(strict_types=1);

namespace System\Starter;

use System\Router\Router;

class Starter {
   private static Router $router;

   public function __construct(
      Router $router
   ) {
      self::$router = $router;
   }

   public function run(): void {
      $config = import_config('defines.app');
      import_env($config['env']);

      foreach (glob(ROOT_DIR . '/' .  $config['routes'] . '/*.php') as $route) {
         import_file($route);
      }

      self::$router->run();
   }

   public static function router(): Router {
      return self::$router;
   }
}
