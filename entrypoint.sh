#!/bin/sh
set -e

php bin/console --overwrite lexik:jwt:generate-keypair
php bin/console --no-interaction doctrine:migrations:migrate
php bin/console app:jobs start
php bin/console app:projection:rebuild all
