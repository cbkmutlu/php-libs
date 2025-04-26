<?php

declare(strict_types=1);

namespace System\Controller;

class Controller {
	protected function middleware(array $middlewares = []): void {
		$services = import_config('services.middlewares.custom');
		foreach ($middlewares as $middleware) {
			$middleware = ucfirst($middleware);
			if (isset($services[$middleware]) && class_exists($services[$middleware])) {
				call_user_func_array([new $services[$middleware], 'handle'], []);
			}
		}
	}
}
