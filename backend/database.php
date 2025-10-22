<?php

function getDatabase() {
    $dbPath = __DIR__ . '/data/cache.db';
    $dataDir = dirname($dbPath);

    // Create data directory if it doesn't exist
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0755, true);
    }

    $db = new SQLite3($dbPath);

    // Enable foreign keys
    $db->exec('PRAGMA foreign_keys = ON;');

    // Initialize schema if needed
    initializeSchema($db);

    return $db;
}

function initializeSchema($db) {
    // Create repos table
    $db->exec('
        CREATE TABLE IF NOT EXISTS repos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            full_name TEXT NOT NULL UNIQUE,
            fetched_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ');

    // Create index on full_name for faster lookups
    $db->exec('CREATE INDEX IF NOT EXISTS idx_repos_full_name ON repos(full_name)');

    // Create index on updated_at for cache invalidation
    $db->exec('CREATE INDEX IF NOT EXISTS idx_repos_updated_at ON repos(updated_at)');
}

function getCachedRepos($maxAgeHours = 24) {
    $db = getDatabase();

    // Check if we have any repos cached within the max age
    $stmt = $db->prepare('
        SELECT COUNT(*) as count
        FROM repos
        WHERE updated_at >= datetime("now", "-' . intval($maxAgeHours) . ' hours")
    ');
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);

    // If no recent data, return null to trigger API fetch
    if ($row['count'] == 0) {
        $db->close();
        return null;
    }

    // Get all cached repos
    $stmt = $db->prepare('SELECT full_name FROM repos ORDER BY full_name ASC');
    $result = $stmt->execute();

    $repos = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $repos[] = $row['full_name'];
    }

    $db->close();
    return $repos;
}

function cacheRepos($repos) {
    $db = getDatabase();

    // Begin transaction for better performance
    $db->exec('BEGIN TRANSACTION');

    // Clear existing repos
    $db->exec('DELETE FROM repos');

    // Insert new repos
    $stmt = $db->prepare('INSERT INTO repos (full_name, fetched_at, updated_at) VALUES (:full_name, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)');

    foreach ($repos as $repo) {
        $stmt->bindValue(':full_name', $repo, SQLITE3_TEXT);
        $stmt->execute();
        $stmt->reset();
    }

    // Commit transaction
    $db->exec('COMMIT');
    $db->close();
}

function clearReposCache() {
    $db = getDatabase();
    $db->exec('DELETE FROM repos');
    $db->close();
}
