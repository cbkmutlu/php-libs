<?php

declare(strict_types=1);

namespace System\Database;

use PDO;
use PDOException;
use System\Exception\ExceptionHandler;

class Database {
   private $pdo;
   private $state;
   private $query = null;
   private $total = 0;
   private $positional = false;
   private $progress = false;
   private $prefix = null;

   public function __construct() {
      $this->connect();
   }

   public function connect(?string $connection = null): self {
      $config = import_config('defines.database');
      $attr = [
         PDO::ATTR_PERSISTENT => $config['persistent'],
         PDO::ATTR_EMULATE_PREPARES => $config['prepares'],
         PDO::ATTR_ERRMODE => $config['error_mode'],
         PDO::ATTR_DEFAULT_FETCH_MODE => $config['fetch_mode']
      ];
      $connection = is_null($connection) ? $config['default'] : $connection;
      $config = $config['connections'][$connection];
      $this->prefix = $config['db_prefix'];

      if ($config['db_driver'] === 'mysql' || $config['db_driver'] === 'pgsql') {
         $dsn = $config['db_driver'] . ':host=' . $config['db_host'] . ';dbname=' . $config['db_name'];
      } elseif ($config['db_driver'] === 'sqlite') {
         $dsn = 'sqlite:' . $config['db_name'];
      } elseif ($config['db_driver'] === 'oracle') {
         $dsn = 'oci:dbname=' . $config['db_host'] . '/' . $config['db_name'];
      } elseif ($config['db_driver'] === 'mssql') {
         $dsn = 'sqlsrv:Server=' . $config['db_host'] . ';Database=' . $config['db_name'];
      }

      try {
         $this->pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], $attr);
         $this->pdo->exec("SET NAMES '" . $config['db_charset'] . "' COLLATE '" . $config['db_collation'] . "'");
         $this->pdo->exec("SET CHARACTER SET '" . $config['db_charset'] . "'");
         $this->pdo->exec("SET CHARACTER_SET_CONNECTION='" . $config['db_charset'] . "'");
      } catch (PDOException $e) {
         throw new ExceptionHandler('Connect ' . $e->getMessage());
      }

      return $this;
   }

   public function query(string $query, array $params = []): self {
      $this->state = $this->pdo->prepare($query);
      try {
         $this->state->execute($params);
         $this->query = $query;
         $this->total++;

         return $this;
      } catch (PDOException $e) {
         if ($this->progress) {
            $this->rollback();
         }

         throw new ExceptionHandler('Query ' . $e->getMessage());
      }
   }

   public function execute(array $params = []): self {
      try {
         if ($this->positional) {
            $this->state->execute();
         } else {
            $this->state->execute($params);
         }
         $this->total++;

         return $this;
      } catch (PDOException $e) {
         if ($this->progress) {
            $this->rollback();
         }

         throw new ExceptionHandler('Execute ' . $e->getMessage());
      }
   }

   public function prepare(string $query): self {
      try {
         $this->positional = false;
         $this->state = $this->pdo->prepare($query);
         $this->query = $query;
         return $this;
      } catch (PDOException $e) {
         throw new ExceptionHandler('Prepare ' . $e->getMessage());
      }
   }

   public function bind(mixed $parameter, mixed $variable, mixed $data_type = PDO::PARAM_STR, $length = 0): self {
      try {
         $this->positional = true;

         if ($length) {
            $this->state->bindParam($parameter, $variable, $data_type, $length);
         } else {
            $this->state->bindParam($parameter, $variable, $data_type);
         }
         return $this;
      } catch (PDOException $e) {
         throw new ExceptionHandler('Bind ' . $e->getMessage());
      }
   }

   public function escape(string $data): string {
      try {
         return $this->pdo->quote($data);
      } catch (PDOException $e) {
         throw new ExceptionHandler('Escape ' . $e->getMessage());
      }
   }

   public function transaction(): self {
      try {
         $this->pdo->beginTransaction();
         $this->pdo->setAttribute(PDO::ATTR_AUTOCOMMIT, false);
         $this->progress = true;
         return $this;
      } catch (PDOException $e) {
         throw new ExceptionHandler('Transaction ' . $e->getMessage());
      }
   }

   public function commit(): self {
      try {
         $this->pdo->commit();
         $this->progress = false;
         return $this;
      } catch (PDOException $e) {
         throw new ExceptionHandler('Commit ' . $e->getMessage());
      }
   }

   public function rollback(): self {
      try {
         $this->pdo->rollBack();
         $this->progress = false;
         return $this;
      } catch (PDOException $e) {
         throw new ExceptionHandler('Rollback ' . $e->getMessage());
      }
   }

   public function prefix(string $table): string {
      return $this->prefix . $table;
   }

   public function getAll(?int $fetch = null): mixed {
      try {
         if (is_null($fetch)) {
            return $this->state->fetchAll();
         }

         return $this->state->fetchAll($fetch);
      } catch (PDOException $e) {
         throw new ExceptionHandler('Get All ' . $e->getMessage());
      }
   }

   public function getRow(?int $fetch = null): mixed {
      try {
         if (is_null($fetch)) {
            return $this->state->fetch();
         }

         return $this->state->fetch($fetch);
      } catch (PDOException $e) {
         throw new ExceptionHandler('Get Row ' . $e->getMessage());
      }
   }

   public function getLastId(): string {
      try {
         return $this->pdo->lastInsertId();
      } catch (PDOException $e) {
         throw new ExceptionHandler('Get Last Id ' . $e->getMessage());
      }
   }

   public function getLastRow(string $table): mixed {
      try {
         $result = $this->query("SELECT MAX(id) FROM " . $this->prefix($table));
         return $result->getRow();
      } catch (PDOException $e) {
         throw new ExceptionHandler('Get Last Row ' . $e->getMessage());
      }
   }

   public function getLastQuery(): string {
      return $this->query;
   }

   public function getTotalQuery(): int {
      return $this->total;
   }

   public function getAffectedRows(): int {
      try {
         return $this->state->rowCount();
      } catch (PDOException $e) {
         throw new ExceptionHandler('Get Affected Rows ' . $e->getMessage());
      }
   }

   public function __destruct() {
      if ($this->state) {
         $this->state->closeCursor();
      }
      $this->pdo = null;
   }
}
