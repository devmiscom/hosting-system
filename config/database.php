<?php
/**
 * Database connection singleton using PDO
 */
class Database {
    private static ?Database $instance = null;
    private PDO $connection;

    private function __construct() {
        if (!defined('DB_HOST')) {
            throw new RuntimeException('Database configuration not loaded.');
        }
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ];
        try {
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            throw new RuntimeException('Database connection failed. Please check your configuration.');
        }
    }

    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection(): PDO {
        return $this->connection;
    }

    /**
     * Execute a prepared statement and return the statement object
     */
    public function query(string $sql, array $params = []): PDOStatement {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Fetch a single row
     */
    public function fetch(string $sql, array $params = []): array|false {
        return $this->query($sql, $params)->fetch();
    }

    /**
     * Fetch all rows
     */
    public function fetchAll(string $sql, array $params = []): array {
        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * Execute a statement and return affected rows
     */
    public function execute(string $sql, array $params = []): int {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Get the last inserted ID
     */
    public function lastInsertId(): string {
        return $this->connection->lastInsertId();
    }

    /**
     * Begin a transaction
     */
    public function beginTransaction(): bool {
        return $this->connection->beginTransaction();
    }

    /**
     * Commit a transaction
     */
    public function commit(): bool {
        return $this->connection->commit();
    }

    /**
     * Rollback a transaction
     */
    public function rollback(): bool {
        return $this->connection->rollBack();
    }

    // Prevent cloning and unserialization
    private function __clone() {}
    public function __wakeup(): void {
        throw new RuntimeException('Cannot unserialize singleton.');
    }
}
