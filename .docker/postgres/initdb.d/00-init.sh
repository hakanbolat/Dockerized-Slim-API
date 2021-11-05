#!/bin/bash
set -e

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<-EOSQL
    CREATE USER example_user WITH LOGIN ENCRYPTED PASSWORD 'example_pass';
    CREATE DATABASE example_db WITH OWNER example_user;
    GRANT ALL PRIVILEGES ON DATABASE example_db TO example_user;
EOSQL
