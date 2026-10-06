<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO((string) env('DB_DSN', ''), env('DB_USER'), env('DB_PASS'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET time_zone = '+00:00'");
    }
    return $pdo;
}

function db_all(string $sql, array $params = []): array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function db_one(string $sql, array $params = []): ?array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function db_run(string $sql, array $params = []): int
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

/** Apply any src/migrations/*.sql files that haven't run yet, in name order. */
function migrate(): void
{
    db()->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
        name VARCHAR(190) NOT NULL PRIMARY KEY,
        applied_at DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $done = array_flip(array_column(db_all('SELECT name FROM schema_migrations'), 'name'));
    $files = glob(APP_ROOT . '/src/migrations/*.sql') ?: [];
    sort($files);

    foreach ($files as $file) {
        $name = basename($file);
        if (isset($done[$name])) {
            continue;
        }
        $sql = (string) file_get_contents($file);
        foreach (preg_split('/;\s*$/m', $sql) as $statement) {
            if (trim(preg_replace('/^\s*--.*$/m', '', $statement)) !== '') {
                db()->exec($statement);
            }
        }
        db_run('INSERT INTO schema_migrations (name, applied_at) VALUES (?, ?)', [$name, utc_now()]);
    }
}
