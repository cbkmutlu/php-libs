<?php

declare(strict_types=1);

namespace System\Migration;

use System\Database\Database;

class Migration {
   public function __construct(
      protected Database $database = new Database()
   ) {
   }

   protected function defaults(): string {
      return "
         `is_deleted` BOOLEAN NOT NULL DEFAULT 0,
         `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
         `created_by` INT NOT NULL DEFAULT 0,
         `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
         `updated_by` INT NOT NULL DEFAULT 0
      ";
   }
}
