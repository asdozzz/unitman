#!/bin/sh
set -e

php bin/console lexik:jwt:generate-keypair
php bin/console app:jobs start
php bin/console app:projection:rebuild all
