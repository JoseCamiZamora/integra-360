#!/usr/bin/env bash
# Sail: creates the Pest database (integra360_testing, see phpunit.xml).

mysql --user=root --password="$MYSQL_ROOT_PASSWORD" <<-EOSQL
    CREATE DATABASE IF NOT EXISTS integra360_testing;
EOSQL

if [ -n "$MYSQL_USER" ]; then
mysql --user=root --password="$MYSQL_ROOT_PASSWORD" <<-EOSQL
    GRANT ALL PRIVILEGES ON \`integra360_testing\`.* TO '$MYSQL_USER'@'%';
EOSQL
fi
