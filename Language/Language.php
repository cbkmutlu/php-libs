<?php

declare(strict_types=1);

namespace System\Language;

use System\Exception\ExceptionHandler;
use System\Session\Session;

class Language {
   private $locale;
   private $translations = [];

   public function __construct(
      private Session $session
   ) {
      $config = import_config('defines.language');

      if ($this->session->exist('session_locale')) {
         $this->locale = $this->session->read('session_locale');
      } else {
         $this->setLocale($config['default']);
      }
   }

   public function setLocale(string $locale): self {
      if ($this->session->status()) {
         $this->session->save('session_locale', $locale);
      }

      $this->locale = $locale;
      return $this;
   }

   public function getLocale(): string {
      return $this->locale;
   }

   public function module(string $params, ?array $printf = null, ?string $locale = null): string {
      if (is_null($locale)) {
         $locale = $this->locale;
      }

      [$file, $key] = explode('.', $params);
      $path = APP_DIR . 'Modules/' . strtolower($file) . '/Languages/' . $locale . '.php';
      $file = $locale . '_module_' . strtolower($file);
      return $this->checkFile($file, $path, $key, $printf, $locale);
   }

   public function system(string $params, ?array $printf = null, ?string $locale = null): string {
      if (is_null($locale)) {
         $locale = $this->locale;
      }

      [$file, $key] = explode('.', $params);
      $path = SYSTEM_DIR . 'Language/' . $locale . '/' . strtolower($file) . '.php';
      $file = $locale . '_system_' . strtolower($file);
      return $this->checkFile($file, $path, $key, $printf, $locale);
   }

   private function checkFile(string $file, string $path, string $key, ?array $printf, ?string $locale): string {
      if (!isset($this->translations[$file])) {
         if (!file_exists($path)) {
            throw new ExceptionHandler("Language file not found [{$path}]");
         }
         $this->translations[$file] = require_once $path;
      }

      if ($this->session->exist('session_locale')) {
         $session_locale = $this->session->read('session_locale');
         if ($session_locale !== $locale) {
            $this->locale = $session_locale;
         }
      }

      if (!isset($this->translations[$file][$key])) {
         return $key;
      }

      $message = $this->translations[$file][$key];
      if (is_array($printf)) {
         return vsprintf($message, $printf);
      }
      return $message;
   }
}
