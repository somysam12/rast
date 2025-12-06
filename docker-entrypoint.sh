#!/bin/bash
set -e

echo "Waiting for database connection..."
max_attempts=30
attempt=0

while [ $attempt -lt $max_attempts ]; do
    if php -r "
        \$url = getenv('DATABASE_URL');
        if (!\$url) exit(1);
        \$parsed = parse_url(\$url);
        try {
            \$dsn = 'pgsql:host=' . \$parsed['host'] . ';port=' . (\$parsed['port'] ?? 5432) . ';dbname=' . ltrim(\$parsed['path'], '/') . ';sslmode=require';
            \$pdo = new PDO(\$dsn, urldecode(\$parsed['user']), urldecode(\$parsed['pass']));
            \$pdo->query('SELECT 1');
            echo 'Database connected!';
            exit(0);
        } catch (Exception \$e) {
            exit(1);
        }
    " 2>/dev/null; then
        break
    fi
    attempt=$((attempt + 1))
    echo "Attempt $attempt/$max_attempts: Database not ready, waiting..."
    sleep 2
done

if [ $attempt -eq $max_attempts ]; then
    echo "Error: Could not connect to database after $max_attempts attempts"
    exit 1
fi

echo "Initializing database..."
php /var/www/html/setup.php

echo "Starting Apache..."
exec apache2-foreground
