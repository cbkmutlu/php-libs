<?php

declare(strict_types=1);

namespace System\Validation;

use System\Language\Language;

class Validation {
   private $error;
   private $labels;
   private $rules;
   private $data;

   public function __construct(
      private Language $language
   ) {
   }

   public function handle(): bool {
      foreach ($this->rules as $key => $value) {
         $rules = explode('|', $value);

         if (in_array('nullable', $rules)) {
            $nullableFieldKey = array_search('nullable', $rules);
            unset($rules[$nullableFieldKey]);

            $nullable = true;
         } else {
            $nullable = false;
         }

         foreach ($rules as $rule) {
            if (strpos($rule, ',')) {
               $group = explode(',', $rule);
               $filter = $group[0];
               $params = $group[1];

               if ($filter === 'matches') {
                  if (!$this->matches($this->data[$key], $this->data[$params])) {
                     $this->error[$key]['err_matches'] = $this->language->system('validation.err_matches', [$this->labels[$key], $this->labels[$params]]);
                  }
               } else {
                  if ($nullable) {
                     if (is_array($this->data[$key])) {
                        foreach ($this->data[$key] as $k => $v) {
                           if (!$this->nullable($v) && !$this->$filter($v, $params)) {
                              $this->error[$key][$k]['err_' . $filter] = $this->language->system('validation.err_' . $filter, [$k, $params]);
                           }
                        }
                     }

                     if (!$this->nullable($this->data[$key]) && !$this->$filter($this->data[$key], $params)) {
                        $this->error[$key]['err_' . $filter] = $this->language->system('validation.err_' . $filter, [$this->labels[$key], $params]);
                     }
                  } else {
                     if (is_array($this->data[$key])) {
                        foreach ($this->data[$key] as $k => $v) {
                           if (!$this->$filter($v, $params)) {
                              $this->error[$key][$k]['err_' . $filter] = $this->language->system('validation.err_' . $filter, [$k, $params]);
                           }
                        }
                     }

                     if (!$this->$filter($this->data[$key], $params)) {
                        $this->error[$key]['err_' . $filter] = $this->language->system('validation.err_' . $filter, [$this->labels[$key], $params]);
                     }
                  }
               }
            } else {
               if ($nullable) {
                  if (is_array($this->data[$key])) {
                     foreach ($this->data[$key] as $k => $v) {
                        if (!$this->nullable($v) && !$this->$rule($v)) {
                           $this->error[$key][$k]['err_' . $rule] = $this->language->system('validation.err_' . $rule, [$k]);
                        }
                     }
                  }

                  if (!$this->nullable($this->data[$key]) && !$this->$rule($this->data[$key])) {
                     $this->error[$key]['err_' . $rule] = $this->language->system('validation.err_' . $rule, [$this->labels[$key]]);
                  }
               } else {
                  if (is_array($this->data[$key])) {
                     foreach ($this->data[$key] as $k => $v) {
                        if (!$this->$rule($v)) {
                           $this->error[$key][$k]['err_' . $rule] = $this->language->system('validation.err_' . $rule, [$k]);
                        }
                     }
                  }

                  if (!$this->$rule($this->data[$key])) {
                     $this->error[$key]['err_' . $rule] = $this->language->system('validation.err_' . $rule, [$this->labels[$key]]);
                  }
               }
            }
         }
      }

      return empty($this->error);
   }

   public function error(): array {
      return $this->error;
   }

   public function rules(array $params): void {
      foreach ($params as $rule) {
         [$key, $rules, $label] = $rule;
         $this->labels[$key] = $label;
         $this->rules[$key] = $rules;
      }
   }

   public function data(array $data): void {
      foreach ($data as $key => $value) {
         $this->data[$key] = $value;
      }
   }

   private function nullable(mixed $data): bool {
      return is_array($data) ? (empty($data)) : (trim($data) === '');
   }

   protected function required(mixed $data): bool {
      return is_array($data) ? (!empty($data)) : (trim($data) !== '');
   }

   protected function numeric(mixed $data): bool {
      return is_numeric($data);
   }

   protected function email(mixed $email): bool {
      return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
   }

   protected function min_len(mixed $data, mixed $length): bool {
      return (strlen(trim($data)) >= $length);
   }

   protected function max_len(mixed $data, mixed $length): bool {
      return (strlen(trim($data)) <= $length);
   }

   protected function exact_len(mixed $data, mixed $length): bool {
      return (strlen(trim($data)) === $length);
   }

   protected function alpha(mixed $data): bool {
      if (!is_string($data)) {
         return false;
      }

      return ctype_alpha($data);
   }

   protected function alpha_num(mixed $data): bool {
      return ctype_alnum($data);
   }

   protected function alpha_dash(mixed $data): bool {
      return (!preg_match("/^([-a-z0-9_-])+$/i", $data)) ? false : true;
   }

   protected function alpha_space(mixed $data): bool {
      return (!preg_match("/^([A-Za-z0-9- ])+$/i", $data)) ? false : true;
   }

   protected function integer(mixed $data): bool {
      return filter_var($data, FILTER_VALIDATE_INT) !== false;
   }

   protected function boolean(mixed $data): bool {
      $acceptable = [true, false, 0, 1, '0', '1'];

      return in_array($data, $acceptable, true);
   }

   protected function float(mixed $data): bool {
      return filter_var($data, FILTER_VALIDATE_FLOAT) !== false;
   }

   protected function valid_url(mixed $data): bool {
      return filter_var($data, FILTER_VALIDATE_URL) !== false;
   }

   protected function valid_ip(mixed $data): bool {
      return filter_var($data, FILTER_VALIDATE_IP) !== false;
   }

   protected function valid_ipv4(mixed $data): bool {
      return filter_var($data, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
   }

   protected function valid_ipv6(mixed $data): bool {
      return filter_var($data, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
   }

   protected function valid_cc(mixed $data): bool {
      if (filter_var($data, FILTER_VALIDATE_INT) === false) {
         return false;
      }

      $number = preg_replace('/\D/', '', $data);
      $length = strlen($number);
      $parity = $length % 2;
      $total = 0;

      for ($i = 0; $i < $length; $i++) {
         $digit = (int) $number[$i];

         if ($i % 2 === $parity) {
            $digit = ($digit * 2 > 9) ? $digit * 2 - 9 : $digit * 2;
         }

         $total += $digit;
      }

      return $total % 10 === 0;
   }


   protected function contains(mixed $data, mixed $value): bool {
      if (is_array($data)) {
         return in_array($value, $data, true);
      }

      return strpos($data, $value);
   }

   protected function min_numeric(mixed $data, mixed $value): bool {
      return (is_numeric($data) && is_numeric($value) && $data >= $value);
   }

   protected function max_numeric(mixed $data, mixed $value): bool {
      return (is_numeric($data) && is_numeric($value) && $data <= $value);
   }

   protected function matches(mixed $data, mixed $value): bool {
      return ($data === $value);
   }
}
