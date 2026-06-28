<?php

/**
 * Migrador best-effort que corre al arrancar la app.
 * Aplica los .sql de database/migrations/ que aún no estén registrados en
 * la tabla schema_migrations. Las migraciones deben ser idempotentes
 * (CREATE TABLE IF NOT EXISTS, ADD COLUMN IF NOT EXISTS, etc.).
 *
 * Nunca lanza: cualquier fallo se registra en el log de errores y la
 * petición continúa con normalidad.
 */
class Migrator
{
    public static function run(): void
    {
        try {
            $pdo = Database::connection();
        } catch (\Throwable $e) {
            error_log('Migrator: sin conexión a DB: ' . $e->getMessage());
            return;
        }

        try {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS schema_migrations (
                    version VARCHAR(191) PRIMARY KEY,
                    applied_at DATETIME NOT NULL
                 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            $applied = $pdo->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN) ?: [];
            $applied = array_flip($applied);

            $files = glob(base_path('database/migrations') . '/*.sql') ?: [];
            sort($files);

            foreach ($files as $file) {
                $version = basename($file, '.sql');
                if (isset($applied[$version])) {
                    continue;
                }
                $sql = file_get_contents($file);
                if ($sql === false || trim($sql) === '') {
                    continue;
                }
                $pdo->exec($sql);
                $pdo->prepare('INSERT INTO schema_migrations (version, applied_at) VALUES (?, ?)')
                    ->execute([$version, now_utc()]);
                error_log('Migrator: aplicada migración ' . $version);
            }
        } catch (\Throwable $e) {
            error_log('Migrator: error aplicando migraciones: ' . $e->getMessage());
        }
    }
}
