<?php

declare(strict_types=1);

namespace System\Database;

use PDO;
use PDOException;

class DatabaseTransaction {
   private $progress;

   public function __construct(
      protected Database $database
   ) {
      $this->database = $database;
   }

   public function begin(): bool {
      try {
         if (!$this->progress++) {
            $this->database->pdo()->setAttribute(PDO::ATTR_AUTOCOMMIT, false);
            return $this->database->pdo()->beginTransaction();
         }

         $this->database->pdo()->exec('SAVEPOINT trans' . $this->progress);
         return $this->progress >= 0;
      } catch (PDOException $e) {
         throw new DatabaseException('Transaction ' . $e->getMessage());
      }
   }

   public function commit(): bool {
      try {
         if (!--$this->progress) {
            return $this->database->pdo()->commit();
         }

         return $this->progress >= 0;
      } catch (PDOException $e) {
         throw new DatabaseException('Commit ' . $e->getMessage());
      }
   }

   public function rollback(): bool {
      try {
         if (--$this->progress) {
            $this->database->pdo()->exec('ROLLBACK TO trans' . ($this->progress + 1));
            return true;
         }

         return $this->database->pdo()->rollBack();
      } catch (PDOException $e) {
         throw new DatabaseException('Rollback ' . $e->getMessage());
      }
   }
}


