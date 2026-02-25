<?php

namespace Core;

use PDO;
use PDOException;
use App\Exceptions\DatabaseException;

/**
 * Multi-connection database manager.
 *
 * Master DB  → healthcare_master (tenants, platform_admins, refresh_tokens)
 * Tenant DBs → healthcare_{subdomain}  (all operational data)
 *
 * TenantMiddleware calls setCurrentTenant($dbName) after resolving the
 * tenant from the request, switching getInstance() to the tenant DB.
 */
class Database
{
    /** @var array<string, PDO> Connection pool keyed by db name */
    private static array $pool = [];

    /** Active tenant DB name for the current request */
    private static ?string $currentTenantDb = null;

    private function __construct() {}
    private function __clone() {}

    // ─── Public API ──────────────────────────────────────────────────

    /**
     * Always returns the master DB connection.
     * Use for: tenants, platform_admins, refresh_tokens.
     */
    public static function getMaster(): PDO
    {
        $config   = require ROOT_PATH . '/config/database.php';
        $masterDb = $config['master_database'];

        if (!isset(self::$pool[$masterDb])) {
            self::$pool[$masterDb] = self::createConnection(
                $config['host'],
                $masterDb,
                $config['username'],
                (string) ($config['password'] ?? ''),
                $config['charset']
            );
        }

        return self::$pool[$masterDb];
    }

    /**
     * Returns a connection to a specific tenant DB by name.
     * Creates and caches the connection on first use.
     */
    public static function getTenant(string $dbName): PDO
    {
        if (!isset(self::$pool[$dbName])) {
            $config = require ROOT_PATH . '/config/database.php';
            self::$pool[$dbName] = self::createConnection(
                $config['host'],
                $dbName,
                $config['username'],
                (string) ($config['password'] ?? ''),
                $config['charset']
            );
        }

        return self::$pool[$dbName];
    }

    /**
     * Set the active tenant DB for this request.
     * Called by TenantMiddleware after resolving tenant.
     * Also called by AuthService::login() (login route skips TenantMiddleware).
     */
    public static function setCurrentTenant(string $dbName): void
    {
        self::$currentTenantDb = $dbName;
        // Warm up the connection immediately
        self::getTenant($dbName);
    }

    /**
     * Backward-compatible getInstance().
     * Returns the current tenant DB if set, otherwise the master DB.
     * All tenant models use this — they automatically get the right DB
     * once TenantMiddleware (or AuthService) calls setCurrentTenant().
     */
    public static function getInstance(): PDO
    {
        if (self::$currentTenantDb !== null) {
            return self::getTenant(self::$currentTenantDb);
        }

        return self::getMaster();
    }

    /**
     * Create a new tenant database and initialise it with tenant_schema.sql.
     * Called by RegisterService when a new tenant registers.
     */
    public static function createTenantDatabase(string $dbName): void
    {
        self::validateDbName($dbName);

        $master = self::getMaster();

        // Create the database
        $master->exec(
            "CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        );

        // Connect and run the tenant schema
        $tenantPdo  = self::getTenant($dbName);
        $schemaPath = ROOT_PATH . '/database/tenant_schema.sql';
        $schema     = file_get_contents($schemaPath);

        if ($schema === false) {
            throw new DatabaseException('Failed to read tenant schema file: ' . $schemaPath);
        }

        // Split on semicolons; strip -- comments from each segment, skip empties
        $statements = array_filter(
            array_map(static function (string $s): string {
                // Remove single-line SQL comments, then trim whitespace
                return trim(preg_replace('/--[^\n]*/', '', $s));
            }, explode(';', $schema)),
            static fn(string $s): bool => $s !== ''
        );

        foreach ($statements as $sql) {
            $tenantPdo->exec($sql);
        }
    }

    /**
     * Drop a tenant database (used for rollback on failed registration).
     */
    public static function dropTenantDatabase(string $dbName): void
    {
        self::validateDbName($dbName);

        $master = self::getMaster();
        $master->exec("DROP DATABASE IF EXISTS `{$dbName}`");

        unset(self::$pool[$dbName]);

        if (self::$currentTenantDb === $dbName) {
            self::$currentTenantDb = null;
        }
    }

    // ─── Private helpers ─────────────────────────────────────────────

    private static function createConnection(
        string $host,
        string $dbName,
        string $username,
        string $password,
        string $charset
    ): PDO {
        $dsn = "mysql:host={$host};dbname={$dbName};charset={$charset}";

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_FOUND_ROWS   => true,
        ];

        try {
            return new PDO($dsn, $username, $password, $options);
        } catch (PDOException $e) {
            throw new DatabaseException(
                "Database connection failed for '{$dbName}': " . $e->getMessage()
            );
        }
    }

    private static function validateDbName(string $dbName): void
    {
        if (!preg_match('/^[a-z0-9_]+$/', $dbName)) {
            throw new DatabaseException("Invalid database name: '{$dbName}'");
        }
    }
}
