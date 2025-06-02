<?php

declare(strict_types=1);

namespace System\Database;

use PDO;
use PDOException;
use PDOStatement;
use System\Database\DatabaseException;

class Database {
   private ?PDO $pdo = null;
   private PDOStatement $state;
   private $query;
   private $total = 0;
   private $progress;
   private $prefix;
   private $positional;
   private $where;
   private $select;
   private $table;
   private $join;
   private $orderBy;
   private $groupBy;
   private $limit;
   private $having;
   private $debug;

   public function connect(?string $connection = null): self {
      $config = import_config('defines.database');
      $attr = [
         PDO::ATTR_PERSISTENT => $config['persistent'],
         PDO::ATTR_EMULATE_PREPARES => $config['prepares'],
         PDO::ATTR_ERRMODE => $config['error_mode'],
         PDO::ATTR_DEFAULT_FETCH_MODE => $config['fetch_mode'],
         PDO::MYSQL_ATTR_FOUND_ROWS => $config['update_rows']
      ];
      $connection = is_null($connection) ? $config['default'] : $connection;
      $config = $config['connections'][$connection];
      $this->prefix = $config['db_prefix'];

      if ($config['db_driver'] === 'mysql' || $config['db_driver'] === 'pgsql') {
         $port = $config['db_port'] !== '' ? "port={$config['db_port']};" : '';
         $dsn = "{$config['db_driver']}:host={$config['db_host']};{$port}dbname={$config['db_name']}";
      } elseif ($config['db_driver'] === 'sqlite') {
         $dsn = "sqlite:{$config['db_name']}";
      } elseif ($config['db_driver'] === 'oracle') {
         $dsn = "oci:dbname={$config['db_host']}:{$config['db_port']}/{$config['db_service_name']}";
      } elseif ($config['db_driver'] === 'mssql') {
         $dsn = "sqlsrv:Server={$config['db_host']},{$config['db_port']};Database={$config['db_name']}";
      }

      try {
         $this->pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], $attr);
         $this->pdo->exec("SET NAMES '{$config['db_charset']}' COLLATE '{$config['db_collation']}'");
         $this->pdo->exec("SET CHARACTER SET '{$config['db_charset']}'");
         $this->pdo->exec("SET CHARACTER_SET_CONNECTION='{$config['db_charset']}'");
      } catch (PDOException $e) {
         throw new DatabaseException('Connection ' . $e->getMessage());
      }

      return $this;
   }

   public function pdo(): PDO {
      if (!$this->pdo) {
         $this->connect();
      }

      return $this->pdo;
   }

   public function debug(): self {
      $this->debug = true;

      return $this;
   }

   public function query(string $query, array $params = []): self {
      try {
         $this->state = $this->pdo()->prepare($query);
         $this->state->execute($params);
         $this->query = $query;
         $this->total++;

         return $this;
      } catch (PDOException $e) {
         if ($this->progress) {
            $this->rollback();
         }

         throw new DatabaseException('Query ' . $e->getMessage());
      }
   }

   public function prepare(?string $query = null): self {
      if (is_null($query)) {
         $this->query .= $this->join ? $this->join : '';
         $this->query .= $this->where ? ' WHERE ' . $this->where : '';
         $this->query .= $this->groupBy ? ' GROUP BY ' . $this->groupBy : '';
         $this->query .= $this->having ? ' HAVING ' . $this->having : '';
         $this->query .= $this->orderBy ? ' ORDER BY ' . $this->orderBy : '';
         $this->query .= $this->limit ? ' LIMIT ' . $this->limit : '';
      } else {
         $this->query = $query;
      }

      if ($this->debug) {
         print_r($this->query . "\n");
         $this->debug = false;
         exit();
      }

      try {
         $this->positional = false;
         $this->state = $this->pdo()->prepare($this->query);
         return $this;
      } catch (PDOException $e) {
         throw new DatabaseException('Prepare ' . $e->getMessage());
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

         throw new DatabaseException('Execute ' . $e->getMessage());
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
         throw new DatabaseException('Bind ' . $e->getMessage());
      }
   }

   public function escape(string $data): string {
      try {
         return $this->pdo()->quote($data);
      } catch (PDOException $e) {
         throw new DatabaseException('Escape ' . $e->getMessage());
      }
   }

   public function transaction(): bool {
      try {
         if (!$this->progress++) {
            $this->pdo()->setAttribute(PDO::ATTR_AUTOCOMMIT, false);
            return $this->pdo()->beginTransaction();
         }

         $this->pdo()->exec('SAVEPOINT trans' . $this->progress);
         return $this->progress >= 0;
      } catch (PDOException $e) {
         throw new DatabaseException('Transaction ' . $e->getMessage());
      }
   }

   public function commit(): bool {
      try {
         if (!--$this->progress) {
            return $this->pdo()->commit();
         }

         return $this->progress >= 0;
      } catch (PDOException $e) {
         throw new DatabaseException('Commit ' . $e->getMessage());
      }
   }

   public function rollback(): bool {
      try {
         if (--$this->progress) {
            $this->pdo()->exec('ROLLBACK TO trans' . ($this->progress + 1));
            return true;
         }

         return $this->pdo()->rollBack();
      } catch (PDOException $e) {
         throw new DatabaseException('Rollback ' . $e->getMessage());
      }
   }

   public function prefix(string $table): string {
      return $this->prefix . $table;
   }

   public function fetchAll(?string $fetch = null, mixed $args = null, bool $all = true): mixed {
      try {
         if (!is_null($fetch)) {
            $mode = 'PDO::' . $fetch;
            if (!defined($mode)) {
               throw new DatabaseException("Invalid fetch mode: $mode");
            }

            $constant = constant($mode);

            if ($constant === PDO::FETCH_CLASS && $args) {
               $this->state->setFetchMode($constant, $args);
            } else {
               $this->state->setFetchMode($constant);
            }
         }

         return $all ? $this->state->fetchAll() : $this->state->fetch();
      } catch (PDOException $e) {
         throw new DatabaseException('Database Fetch Error: ' . $e->getMessage());
      }
   }

   public function fetchRow(?int $fetch = null, mixed $args = null): mixed {
      return $this->fetchAll($fetch, $args, false);
   }

   public function lastInsertId(): string {
      try {
         return $this->pdo()->lastInsertId();
      } catch (PDOException $e) {
         throw new DatabaseException('Get Last Id ' . $e->getMessage());
      }
   }

   public function lastInsertRow(?string $table = null): mixed {
      if (is_null($table)) {
         $table = $this->table;
      }

      $result = $this->query("SELECT * FROM " . $this->prefix($table) . " WHERE id=" . $this->lastInsertId());
      return $result->fetchRow();
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
         throw new DatabaseException('Get Affected Rows ' . $e->getMessage());
      }
   }

   public function __destruct() {
      if (isset($this->state)) {
         $this->state->closeCursor();
      }
      if ($this->pdo instanceof PDO) {
         $this->pdo = null;
      }
   }

   public function table(string $table): self {
      $this->pdo();
      $this->reset();
      $this->table = $this->prefix . $table;

      return $this;
   }

   public function where(string $where): self {
      if ($this->where) {
         $this->where = $this->where . ' ' . rtrim($where);
      } else {
         $this->where = rtrim($where);
      }

      return $this;
   }

   public function join(string $table, string $type = 'LEFT'): self {
      $table = $this->prefix . $table;
      $this->join .= " {$type} JOIN {$table}";

      return $this;
   }

   public function orderBy(string $data): self {
      if (stristr($data, ' ') || strtolower($data) === 'rand()') {
         $this->orderBy = $data;
      } else {
         $this->orderBy = $data . ' ASC';
      }

      return $this;
   }

   public function groupBy(string $data): self {
      $this->groupBy = $data;

      return $this;
   }

   public function limit(int $data): self {
      $this->limit = $data;

      return $this;
   }

   public function having(string $data): self {
      $this->having = $data;

      return $this;
   }

   public function select(array $data = ['*']): self {
      $data = rtrim(implode(', ', $data));

      if ($this->select) {
         $this->select = $this->select . ', ' . $data;
      } else {
         $this->select = $data;
      }

      $query = "SELECT {$this->select} FROM {$this->table}";
      $this->query = $query;

      return $this;
   }


   public function update(array $data): self {
      $clause = implode(', ', array_map(function ($key, $value) {
         if (is_int($key)) {
            return "`$value` = :$value";
         } elseif (is_array($value)) {
            return "`$key` = " . $value[0];
         } else {
            return "`$key` = " . $this->escape((string) $value);
         }
      }, array_keys($data), $data));

      $this->query = "UPDATE {$this->table} SET {$clause}";
      return $this;
   }

   public function insert(array $data): self {
      $columns = implode(', ', array_map(function ($key, $value) {
         return is_int($key) ? "`$value`" : "`$key`";
      }, array_keys($data), $data));

      $values = implode(', ', array_map(function ($key, $value) {
         if (is_int($key)) {
            return ":$value";
         } elseif (is_array($value)) {
            return $value[0];
         } else {
            return $this->escape((string) $value);
         }
      }, array_keys($data), $data));

      $this->query = "INSERT INTO {$this->table} ({$columns}) VALUES ({$values})";
      return $this;
   }

   public function delete(): self {
      $this->query = "DELETE FROM {$this->table}";
      return $this;
   }

   private function reset(): void {
      $this->positional = null;
      $this->where = null;
      $this->select = null;
      $this->table = null;
      $this->join = null;
      $this->orderBy = null;
      $this->groupBy = null;
      $this->limit = null;
      $this->having = null;
   }
}
