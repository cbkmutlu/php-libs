<?php

declare(strict_types=1);

namespace System\Cli;

class Cli {
   private $params;
   private $colors;

   public function __construct() {
      $config = import_config('defines.app');
      import_env($config['env']);
      $this->colors['black']         = '0;30';
      $this->colors['dark_gray']     = '1;30';
      $this->colors['blue']          = '0;34';
      $this->colors['light_blue']    = '1;34';
      $this->colors['green']         = '0;32';
      $this->colors['light_green']   = '1;32';
      $this->colors['cyan']          = '0;36';
      $this->colors['light_cyan']    = '1;36';
      $this->colors['red']           = '0;31';
      $this->colors['light_red']     = '1;31';
      $this->colors['purple']        = '0;35';
      $this->colors['light_purple']  = '1;35';
      $this->colors['brown']         = '0;33';
      $this->colors['yellow']        = '1;33';
      $this->colors['light_gray']    = '0;37';
      $this->colors['white']         = '1;37';
   }

   public function run(array $params): string {
      $this->params = $params;

      if (!$this->params) {
         return $this->help();
      } else if ($params[0] === 'serve') {
         $oldPath = getcwd();
         chdir(getcwd());
         $output = shell_exec('php -S 127.0.0.1:8000');
         chdir($oldPath);
         return print_r($output);
      } else {
         if (isset($params[1])) {
            switch ($params[0]) {
               case 'controller':
                  return $this->createController($params[1], $params[2] ?? null);
               case 'service':
                  return $this->createService($params[1]);
               case 'repository':
                  return $this->createRepository($params[1]);
               case 'model':
                  return $this->createModel($params[1]);
               case 'middleware':
                  return $this->createMiddleware($params[1]);
               case 'listener':
                  return $this->createListener($params[1]);
               case 'migration':
                  return $this->createMigration($params[1]);
               case 'migrate':
                  return $this->migrate($params[1]);
               case 'hash':
                  return $this->createHash($params[1]);
               default:
                  return $this->help();
            }
         } else {
            if ($params[0] === 'key') {
               return $this->createKey();
            }
            return $this->error('Invalid command: ' . $params[0]);
         }
      }
   }

   private function help(): string {
      return $this->info('[controller]', 'light_blue') . "\t" . 'controller User/RegisterController' . "\n" .
         $this->info('[service]', 'light_blue') . "\t" . 'service User/RegisterService' . "\n" .
         $this->info('[repository]', 'light_blue') . "\t" . 'repository User/RegisterRepository' . "\n" .
         $this->info('[model]', 'light_blue') . "\t\t" . 'model User/Register' . "\n" .
         $this->info('[migration]', 'light_blue') . "\t" . 'migration User/Migration' . "\n" .
         $this->info('[middleware]', 'light_blue') . "\t" . 'middleware MyMiddleware' . "\n" .
         $this->info('[listener]', 'light_blue') . "\t" . 'listener MyListener' . "\n\n" .
         $this->info('[hash]', 'light_blue') . "\t\t" . 'hash Password' . "\n" .
         $this->info('[key]', 'light_blue') . "\t\t" . 'key' . "\n" .
         $this->info('[migrate --run]', 'light_blue') . "\t\t" . '? run created migrations' . "\n" .
         $this->info('[migrate --rollback]', 'light_blue') . "\t" . '? rollback last migration' . "\n" .
         $this->info('[migrate --reset]', 'light_blue') . "\t" . '? reset all migrations' . "\n" .
         $this->info('[migrate --refresh]', 'light_blue') . "\t" . '? refresh all migrations' . "\n";
   }

   private function createHash(string $value): string {
      $hash = password_hash($value, PASSWORD_ARGON2ID, ['cost' => 10]);

      if (!$hash) {
         return $this->error('Bcrypt hash not supported');
      }

      return $this->success('Hash: ' . $hash);
   }

   private function createKey(): string {
      $data = base64_encode(random_bytes(32));
      return $this->success('256bit key: ' . $data);
   }

   private function createController(string $data, ?string $swagger = null): string {
      if (preg_match('#^[A-Za-z0-9_]+/[A-Za-z0-9_]+$#', $data)) {
         [$module, $class] = explode('/', $data);
         $location = "App/Modules/$module/Controllers";
         $namespace = "App\\Modules\\$module\\Controllers";
      } elseif (is_string($data)) {
         $class = $data;
         $location = "App/Controllers";
         $namespace = "App\\Controllers";
      } else {
         return $this->error('Invalid command: ' . $data);
      }

      $file = "$location/$class.php";
      if (file_exists($file)) {
         return $this->error('Controller already exists: ' . $file);
      }

      if ($swagger === '--swagger') {
         $template = file_get_contents('System/Cli/swagger.temp');
         $content = str_replace(['{namespace}', '{class}', '{module}'], [$namespace, $class, $module], $template);
      } else {
         $template = file_get_contents('System/Cli/controller.temp');
         $content = str_replace(['{namespace}', '{class}'], [$namespace, $class], $template);
      }

      $this->dir($location);
      file_put_contents($file, $content);
      return $this->success('Controller successfully created: ' . $file);
   }

   private function createService(string $data): string {
      if (preg_match('#^[A-Za-z0-9_]+/[A-Za-z0-9_]+$#', $data)) {
         [$module, $class] = explode('/', $data);
         $location = "App/Modules/$module/Services";
         $namespace = "App\\Modules\\$module\\Services";
      } elseif (is_string($data)) {
         $class = $data;
         $location = "App/Services";
         $namespace = "App\\Services";
      } else {
         return $this->error('Invalid command: ' . $data);
      }

      $file = "$location/$class.php";
      if (file_exists($file)) {
         return $this->error('Service already exists: ' . $file);
      }

      $template = file_get_contents('System/Cli/service.temp');
      $content = str_replace(['{namespace}', '{class}'], [$namespace, $class], $template);
      $this->dir($location);
      file_put_contents($file, $content);
      return $this->success('Service successfully created: ' . $file);
   }

   private function createRepository(string $data): string {
      if (preg_match('#^[A-Za-z0-9_]+/[A-Za-z0-9_]+$#', $data)) {
         [$module, $class] = explode('/', $data);
         $location = "App/Modules/$module/Repositories";
         $namespace = "App\\Modules\\$module\\Repositories";
      } elseif (is_string($data)) {
         $class = $data;
         $location = "App/Repositories";
         $namespace = "App\\Repositories";
      } else {
         return $this->error('Invalid command: ' . $data);
      }

      $file = "$location/$class.php";
      if (file_exists($file)) {
         return $this->error('Repository already exists: ' . $file);
      }

      $template = file_get_contents('System/Cli/repository.temp');
      $content = str_replace(['{namespace}', '{class}'], [$namespace, $class], $template);
      $this->dir($location);
      file_put_contents($file, $content);
      return $this->success('Repository successfully created: ' . $file);
   }

   private function createModel(string $data): string {
      if (preg_match('#^[A-Za-z0-9_]+/[A-Za-z0-9_]+$#', $data)) {
         [$module, $class] = explode('/', $data);
         $location = "App/Modules/$module/Models";
         $namespace = "App\\Modules\\$module\\Models";
      } elseif (is_string($data)) {
         $class = $data;
         $location = "App/Models";
         $namespace = "App\\Models";
      } else {
         return $this->error('Invalid command: ' . $data);
      }

      $file = "$location/$class.php";
      if (file_exists($file)) {
         return $this->error('Model already exists: ' . $file);
      }

      $template = file_get_contents('System/Cli/model.temp');
      $content = str_replace(['{namespace}', '{class}'], [$namespace, $class], $template);
      $this->dir($location);
      file_put_contents($file, $content);
      return $this->success('Model successfully created: ' . $file);
   }

   private function createMigration(string $data): string {
      if (preg_match('#^[A-Za-z0-9_]+/[A-Za-z0-9_]+$#', $data)) {
         [$module, $class] = explode('/', $data);
         $location = "App/Modules/$module/Migrations";
         $search = "App/Modules/*/Migrations";
      } elseif (is_string($data)) {
         $class = $data;
         $location = "App/Migrations";
         $search = "App/Migrations";
      } else {
         return $this->error('Invalid command: ' . $data);
      }
      $name =  date('Y_m_d_His') . '_' . $class;

      foreach (glob(ROOT_DIR . '/' .  $search . '/*.php') as $migration) {
         require_once $migration;
      }

      if (class_exists($class)) {
         return $this->info('Migration already exists: ' . $class);
      }

      $file = "$location/$name.php";
      $template = file_get_contents('System/Cli/migration.temp');
      $content = str_replace('{class}', $class, $template);
      $this->dir($location);
      file_put_contents($file, $content);
      return $this->success('Migration successfully created: ' . $file);
   }

   private function createMiddleware(string $middleware): string {
      $file = "App/Core/Middlewares/$middleware.php";

      $template = file_get_contents('System/Cli/middleware.temp');
      $content = str_replace('{middleware}', $middleware, $template);

      if (file_exists($file)) {
         return $this->info('Middleware already exists: ' . $file);
      }

      file_put_contents($file, $content);
      return $this->success('Middleware successfully created: ' . $file);
   }

   private function createListener(string $listener): string {
      $file = "App/Core/Listeners/$listener.php";

      $template = file_get_contents('System/Cli/listener.temp');
      $content = str_replace('{listener}', $listener, $template);

      if (file_exists($file)) {
         return $this->info('Listener already exists: ' . $file);
      }

      file_put_contents($file, $content);
      return $this->success('Listener successfully created: ' . $file);
   }

   public function migrate($param): string {
      $json = "App/Config/migration.json";
      if (!file_exists($json)) {
         file_put_contents($json, json_encode([], JSON_PRETTY_PRINT));
      }

      $config = import_config('defines.app');
      $location = $config['migrations'];
      $migrations = json_decode(file_get_contents($json), true);
      $maxValue = (count($migrations) > 0) ? max($migrations) : 0;
      $maxKeys = array_filter($migrations, fn($value) => $value === $maxValue);
      $migrate = false;

      foreach (glob(ROOT_DIR . '/' .  $location . '/*.php') as $migration) {
         require_once $migration;
         $class = substr(basename($migration), 0, -4);

         if (class_exists($class)) {
            $instance = new $class();

            if ($param === '--run') {
               if (!isset($migrations[$class])) {
                  $instance->up();
                  $migrations[$class] = $maxValue + 1;
                  $migrate = true;
               }
            } else if ($param === '--rollback') {
               if (isset($maxKeys[$class])) {
                  $instance->down();
                  unset($migrations[$class]);
                  $migrate = true;
               }
            } else if ($param === '--reset') {
               if (isset($migrations[$class])) {
                  $instance->down();
                  unset($migrations[$class]);
                  $migrate = true;
               }
            } else if ($param === '--refresh') {
               $this->migrate('--reset');
               return $this->migrate('--run');
            } else {
               return $this->error('Invalid command: ' . $param);
            }
         }
      }

      if ($migrate) {
         file_put_contents($json, json_encode($migrations, JSON_PRETTY_PRINT));
         return $this->info('Migration successfully completed');
      } else {
         return $this->error('No migration to run');
      }
   }

   private function success(string $message): string {
      return $this->write($message, 'light_green');
   }

   private function error(string $message): string {
      return $this->write($message, 'light_red');
   }

   private function info(string $message): string {
      return $this->write($message, 'light_blue');
   }

   private function write(string $string, ?string $color = null): string {
      $colored_string = "";

      if (isset($this->colors[$color])) {
         $colored_string .= "\e[" . $this->colors[$color] . "m";
      }

      $colored_string .= $string . "\e[0m";

      return $colored_string;
   }

   private function dir(string $path, int $permissions = 0755): bool {
      return is_dir($path) || mkdir($path, $permissions, true);
   }
}
