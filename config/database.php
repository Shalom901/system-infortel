<?php

declare(strict_types=1);

/**
 * =============================================================
 * CONFIGURACIÓN Y CLASE DE BASE DE DATOS
 * Sistema de Facturación - Empresa de Informática Pucallpa
 * =============================================================
 *
 * Clase singleton para manejo de conexión PDO con MySQL/MariaDB.
 * Proporciona métodos seguros para consultas con prepared statements.
 */

namespace Config;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

class Database
{
    /** @var Database|null Instancia única (Singleton) */
    private static ?Database $instance = null;

    /** @var PDO Conexión activa a la base de datos */
    private PDO $pdo;

    /** @var bool Indica si hay una transacción activa */
    private bool $inTransaction = false;

    /**
     * Constructor privado - inicializa la conexión PDO.
     * Se lanza RuntimeException si la conexión falla.
     */
    private function __construct()
    {
        $host    = $_ENV['DB_HOST']     ?? 'localhost';
        $port    = $_ENV['DB_PORT']     ?? '3306';
        $dbname  = $_ENV['DB_DATABASE'] ?? 'facturacion_pucallpa';
        $user    = $_ENV['DB_USERNAME'] ?? 'root';
        $pass    = $_ENV['DB_PASSWORD'] ?? '';
        $charset = $_ENV['DB_CHARSET']  ?? 'utf8mb4';

        // DSN de conexión PDO para MySQL/MariaDB
        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

        // Opciones de configuración PDO para máxima seguridad y compatibilidad
        $opciones = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // Lanzar excepciones en errores
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // Retornar arrays asociativos
            PDO::ATTR_EMULATE_PREPARES   => false,                    // Usar prepared statements nativos
            PDO::ATTR_PERSISTENT         => false,                    // Sin conexiones persistentes
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE {$charset}_unicode_ci",
        ];

        try {
            $this->pdo = new PDO($dsn, $user, $pass, $opciones);
        } catch (PDOException $e) {
            // Registrar el error sin exponer credenciales
            $this->registrarError('Error de conexión a base de datos', $e);
            throw new RuntimeException(
                'No se pudo conectar a la base de datos. Verifique la configuración.',
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Obtener la instancia única de Database (Singleton).
     *
     * @return Database
     */
    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Obtener la conexión PDO directa.
     *
     * @return PDO
     */
    public function getConnection(): PDO
    {
        return $this->pdo;
    }

    /**
     * Preparar y ejecutar una consulta SQL con parámetros.
     *
     * @param  string $sql    Consulta SQL con marcadores de posición (?)
     * @param  array  $params Parámetros a enlazar en la consulta
     * @return PDOStatement   Statement ejecutado
     * @throws RuntimeException Si la consulta falla
     */
    public function query(string $sql, array $params = []): PDOStatement
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            $this->registrarError("Error en consulta SQL: {$sql}", $e);
            throw new RuntimeException(
                'Error al ejecutar la consulta de base de datos.',
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Obtener todos los registros de una consulta.
     *
     * @param  string $sql    Consulta SQL SELECT
     * @param  array  $params Parámetros de la consulta
     * @return array          Arreglo de resultados asociativos
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * Obtener un único registro de una consulta.
     *
     * @param  string     $sql    Consulta SQL SELECT con LIMIT 1
     * @param  array      $params Parámetros de la consulta
     * @return array|bool         Registro asociativo o false si no existe
     */
    public function fetchOne(string $sql, array $params = []): array|bool
    {
        return $this->query($sql, $params)->fetch();
    }

    /**
     * Ejecutar una consulta INSERT, UPDATE o DELETE.
     * Retorna el último ID insertado para INSERT, o filas afectadas para otros.
     *
     * @param  string     $sql    Consulta SQL de modificación
     * @param  array      $params Parámetros de la consulta
     * @return string|int         Último ID insertado (INSERT) o filas afectadas
     */
    public function execute(string $sql, array $params = []): string|int
    {
        $stmt = $this->query($sql, $params);

        // Para INSERT, retornar el último ID generado
        $sqlTrim = strtoupper(ltrim($sql));
        if (str_starts_with($sqlTrim, 'INSERT')) {
            return $this->pdo->lastInsertId();
        }

        // Para UPDATE y DELETE, retornar filas afectadas
        return $stmt->rowCount();
    }

    /**
     * Iniciar una transacción de base de datos.
     *
     * @throws RuntimeException Si ya hay una transacción activa
     */
    public function beginTransaction(): void
    {
        if ($this->inTransaction) {
            throw new RuntimeException('Ya existe una transacción activa.');
        }

        $this->pdo->beginTransaction();
        $this->inTransaction = true;
    }

    /**
     * Confirmar (commit) la transacción actual.
     *
     * @throws RuntimeException Si no hay transacción activa
     */
    public function commit(): void
    {
        if (!$this->inTransaction) {
            throw new RuntimeException('No hay transacción activa para confirmar.');
        }

        $this->pdo->commit();
        $this->inTransaction = false;
    }

    /**
     * Revertir (rollback) la transacción actual.
     *
     * @throws RuntimeException Si no hay transacción activa
     */
    public function rollback(): void
    {
        if (!$this->inTransaction) {
            throw new RuntimeException('No hay transacción activa para revertir.');
        }

        $this->pdo->rollBack();
        $this->inTransaction = false;
    }

    /**
     * Verificar si hay una transacción activa.
     *
     * @return bool
     */
    public function isInTransaction(): bool
    {
        return $this->inTransaction;
    }

    /**
     * Obtener el último ID de auto-incremento insertado.
     *
     * @return string
     */
    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    /**
     * Escapar un valor para uso en LIKE (previene inyección en wildcards).
     *
     * @param  string $valor Valor a escapar
     * @return string        Valor escapado para LIKE
     */
    public function escaparLike(string $valor): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $valor);
    }

    /**
     * Registrar errores de base de datos en el log del sistema.
     *
     * @param string       $mensaje Descripción del error
     * @param PDOException $e       Excepción original
     */
    private function registrarError(string $mensaje, PDOException $e): void
    {
        $logPath = defined('STORAGE_PATH')
            ? STORAGE_PATH . '/logs/database.log'
            : __DIR__ . '/../storage/logs/database.log';

        $logDir = dirname($logPath);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $timestamp = date('Y-m-d H:i:s');
        $codigo    = $e->getCode();
        // Omitir la traza completa en producción para no exponer rutas
        $entorno   = $_ENV['APP_ENV'] ?? 'production';
        $traza     = ($entorno === 'development') ? "\nTraza: " . $e->getTraceAsString() : '';

        $linea = "[{$timestamp}] [ERROR DB] [{$codigo}] {$mensaje}: {$e->getMessage()}{$traza}" . PHP_EOL;

        file_put_contents($logPath, $linea, FILE_APPEND | LOCK_EX);
    }

    /**
     * Prevenir la clonación del Singleton.
     */
    private function __clone() {}

    /**
     * Prevenir la deserialización del Singleton.
     *
     * @throws RuntimeException
     */
    public function __wakeup(): void
    {
        throw new RuntimeException('No se puede deserializar el Singleton Database.');
    }
}
