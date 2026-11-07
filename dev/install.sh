#!/bin/sh
set -eu
cd "$(dirname "$0")/.."
docker compose -f dev/compose.yaml up -d db web speech
docker compose -f dev/compose.yaml exec -T web sh -c 'mkdir -p /var/moodledata && chown www-data:www-data /var/moodledata'
docker compose -f dev/compose.yaml exec -T web sh -c 'php admin/cli/install.php --non-interactive --agree-license --wwwroot=http://127.0.0.1:8088 --dataroot=/var/moodledata --dbtype=mysqli --dbhost=db --dbname=moodle --dbuser=moodle --dbpass="$MOODLE_DB_PASSWORD" --fullname="Microlearning Local" --shortname="Microlearning" --adminuser=admin --adminpass="$MOODLE_ADMIN_PASSWORD" --adminemail=admin@example.invalid'
docker compose -f dev/compose.yaml exec -T web php local/microlearning/dev/bootstrap.php
docker compose -f dev/compose.yaml exec -T web chown -R www-data:www-data /var/moodledata
docker compose -f dev/compose.yaml up -d cron
