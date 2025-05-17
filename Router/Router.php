<?php

declare(strict_types=1);

namespace System\Router;

use System\Container\Container;
use System\Router\RouterException;

class Router {
   private $routes = [];
   private $prefix = '/';
   private $middlewares = [];
   private $domain = [];
   private $ip = [];
   private $ssl = false;
   private $as = null;
   private $error = null;
   private $groups = [];
   private $length = 0;
   private $names = [];

   public function __construct(
      private Container $container
   ) {
   }

   public function group(callable $callback): void {
      $this->length++;
      $this->groups[] = [
         'prefix' => $this->prefix,
         'middlewares' => $this->middlewares,
         'domain' => $this->domain,
         'ip' => $this->ip,
         'ssl' => $this->ssl,
         'as' => $this->as,
      ];

      call_user_func($callback);
      if ($this->length > 0) {
         $this->prefix = $this->groups[$this->length - 1]['prefix'];
         $this->middlewares = $this->groups[$this->length - 1]['middlewares'];
         $this->domain = $this->groups[$this->length - 1]['domain'];
         $this->ip = $this->groups[$this->length - 1]['ip'];
         $this->ssl = $this->groups[$this->length - 1]['ssl'];
         $this->as = $this->groups[$this->length - 1]['as'];
      }

      $this->length--;
      if ($this->length <= 0) {
         $this->prefix = '/';
         $this->middlewares = [];
         $this->domain = [];
         $this->ip = [];
         $this->ssl = false;
         $this->as = null;
      }
   }

   public function prefix(string $prefix): self {
      $this->prefix = '/' . $prefix;
      return $this;
   }

   public function middleware(array $middlewares): self {
      $this->middlewares = array_merge($this->middlewares, $middlewares);
      return $this;
   }

   public function domain(array $domain): self {
      $this->domain = $domain;
      return $this;
   }

   public function ip(array $ip): self {
      $this->ip = $ip;
      return $this;
   }

   public function ssl(): self {
      $this->ssl = true;
      return $this;
   }

   public function as(string $as): self {
      $this->as = $as;
      return $this;
   }

   public function get(string $pattern, callable|array $callback): self {
      $this->add('GET', $pattern, $callback);
      return $this;
   }

   public function post(string $pattern, callable|array $callback): self {
      $this->add('POST', $pattern, $callback);
      return $this;
   }

   public function patch(string $pattern, callable|array $callback): self {
      $this->add('PATCH', $pattern, $callback);
      return $this;
   }

   public function delete(string $pattern, callable|array $callback): self {
      $this->add('DELETE', $pattern, $callback);
      return $this;
   }

   public function put(string $pattern, callable|array $callback): self {
      $this->add('PUT', $pattern, $callback);
      return $this;
   }

   public function options(string $pattern, callable|array $callback): self {
      $this->add('OPTIONS', $pattern, $callback);
      return $this;
   }

   public function match(array $methods, string $pattern, callable|array $callback) {
      foreach ($methods as $method) {
         $this->add(strtoupper($method), $pattern, $callback);
      }
   }

   public function where(array $expressions): self {
      $key = array_search(end($this->routes), $this->routes);
      $pattern = $this->parseUri($this->routes[$key]['uri'], $expressions);
      $pattern = '/' . implode('/', $pattern);
      $pattern = '/^' . str_replace('/', '\/', $pattern) . '$/';

      $this->routes[$key]['pattern'] = $pattern;
      return $this;
   }

   public function name(string $name): self {
      $key = array_search(end($this->routes), $this->routes);
      $name = ($this->as) ? $this->as . '.' . $name : $name;

      $this->routes[$key]['name'] = $name;

      $uri = $this->parseUri($this->routes[$key]['uri'], []);
      $uri = implode('/', $uri);

      $this->names[$name] = $uri;
      return $this;
   }

   public function run(): void {
      $matched = false;

      foreach ($this->routes as $route) {
         if (preg_match($route['pattern'], $this->getUri(), $params)) {
            if ($this->checkIp($route) && $this->checkDomain($route) && $this->checkSSL($route) && $this->checkMethod($route)) {
               $matched = true;
               break;
            }
         }
      }

      if (!$matched) {
         if ($this->error && is_callable($this->error)) {
            call_user_func($this->error);
         } else {
            throw new RouterException("Route not found [{$this->getUri()}]", 404);
         }
      }

      array_shift($params);
      $callback = function () use ($route, $params) {
         if (is_callable($route['callback'])) {
            call_user_func_array($route['callback'], array_values($params));
         } elseif (is_array($route['callback'])) {
            [$controller, $method] = $route['callback'];
            if (!class_exists($controller)) {
               throw new RouterException("Controller not found [{$controller}::{$method}]");
            }
            if (!method_exists($controller, $method)) {
               throw new RouterException("Method not found [{$controller}::{$method}]");
            }
            $instance = $this->container->resolve($controller);
            call_user_func_array([$instance, $method], array_values($params));
         } else {
            throw new RouterException("Invalid route callback");
         }
      };

      $middlewares = array_merge(import_config('services.middlewares.default') ?? [], $route['middlewares'] ?? []);
      $next = $callback;

      foreach (array_reverse($middlewares) as $middleware) {
         if (class_exists($middleware)) {
            $instance = $this->container->resolve($middleware);
            $current = $next;
            $next = function () use ($instance, $current) {
               return $instance->handle($current);
            };
         }
      }

      $next();
   }

   public function url(string $name, array $params = []): mixed {
      if (isset($this->names[$name])) {
         $pattern = $this->parseUri($this->names[$name], $params);
         $pattern = implode('/', $pattern);
         return $pattern;
      }

      return null;
   }

   public function routes(): array {
      return $this->routes;
   }

   public function names(): array {
      return $this->names;
   }

   public function error(callable $callback): void {
      $this->error = function () use ($callback) {
         call_user_func($callback, $this->getUri());
      };
   }

   private function add(string $method, string $pattern, callable|array $callback): void {
      if ($pattern === '/') {
         $pattern = $this->prefix . trim($pattern, '/');
      } else {
         if ($this->prefix === '/') {
            $pattern = $this->prefix . trim($pattern, '/');
         } else {
            $pattern = $this->prefix . $pattern;
         }
      }

      $uri = $pattern;
      $pattern = preg_replace('/[\[{\(].*[\]}\)]/U', '([^/]+)', $pattern);
      $pattern = '/^' . str_replace('/', '\/', $pattern) . '$/';

      $this->routes[] = array_filter([
         'uri'         => $uri,
         'method'      => $method,
         'pattern'     => $pattern,
         'callback'    => $callback,
         'middlewares' => $this->middlewares,
         'domain'      => $this->domain,
         'ip'          => $this->ip,
         'ssl'         => $this->ssl,
      ]);
   }

   private function parseUri(string $uri, array $expressions): array {
      $segments = explode('/', ltrim($uri, '/'));

      return array_map(function ($segment) use ($expressions) {
         if (preg_match('/[\[{\(](.*)[\]}\)]/U', $segment, $match)) {
            $key = $match[1];
            return $expressions[$key] ?? $segment;
         }
         return $segment;
      }, $segments);
   }

   private function getUri(): string {
      $path = array_slice(explode('/', $_SERVER['SCRIPT_NAME']), 0, -1);
      $path = implode('/', $path) . '/';
      $uri = substr($_SERVER['REQUEST_URI'], strlen($path));

      if (strpos($uri, '?') !== false) {
         $uri = substr($uri, 0, strpos($uri, '?'));
      }

      return '/' . trim($uri, '/');
   }

   private function checkIp(array $route): bool {
      if (isset($route['ip'])) {
         if (is_array($route['ip'])) {
            if (!in_array($_SERVER['REMOTE_ADDR'], $route['ip'])) {
               return false;
            }
         }
      }

      return true;
   }

   private function checkDomain(array $route): bool {
      if (isset($route['domain'])) {
         if (is_array($route['domain'])) {
            if (!in_array($_SERVER['HTTP_HOST'], $route['domain'])) {
               return false;
            }
         }
      }

      return true;
   }

   private function checkSSL(array $route): bool {
      if (isset($route['ssl']) && $route['ssl']) {
         if ($_SERVER['REQUEST_SCHEME'] !== 'https') {
            return false;
         }
      }

      return true;
   }

   private function checkMethod(array $route): bool {
      $headers = getallheaders();
      $method = $_SERVER['REQUEST_METHOD'];

      if ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
         ob_start();
         $method = 'GET';
         ob_end_clean();
      } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
         if (isset($headers['X-HTTP-Method-Override']) && in_array($headers['X-HTTP-Method-Override'], ['PUT', 'DELETE', 'PATCH'])) {
            $method = $headers['X-HTTP-Method-Override'];
         }
      }

      return ($route['method'] !== $method) ? false : true;
   }
}
