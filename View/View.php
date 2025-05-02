<?php

declare(strict_types=1);

namespace System\View;

use System\Exception\SystemException;

class View {
	private $theme = null;

	public function render(string $theme, array $vars = [], bool $cache = false): void {
		[$module, $file] = explode('@', $theme);

		// COMBAK cache
		if (is_null($this->theme)) {
			$path = APP_DIR . 'Modules/' . ucfirst($module) . '/Views/' . $file . '.php';
		} else {
			$path = APP_DIR . 'Modules/' . ucfirst($module) . '/Views/' . $this->theme . '/' . $file . '.php';
		}

		$this->import($path, $vars);
	}

	public function theme(string $theme): self {
		$this->theme = $theme;
		return $this;
	}

	private function import(string $file, array $data = []): void {
		if (!file_exists($file)) {
			throw new SystemException("View file not found [{$file}]");
		}

		extract($data);
		require_once $file;
	}
}
