FROM ghcr.io/roadrunner-server/roadrunner:2023.3 AS roadrunner
FROM php:8.1-alpine

ARG CURRENT_USER_ID=1000
ARG CURRENT_USER_GROUP=1000

RUN addgroup --g ${CURRENT_USER_GROUP} groupcontainer
RUN adduser -u ${CURRENT_USER_ID} -G groupcontainer -h /home/containeruser -D containeruser
RUN adduser containeruser root

COPY --from=roadrunner /usr/bin/rr /usr/local/bin/rr
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=temporalio/admin-tools /usr/local/bin/tctl /usr/local/bin/tctl

RUN install-php-extensions bcmath intl opcache zip sockets grpc pdo pdo_pgsql pgsql xdebug

RUN apk add --no-cache git docker docker-compose

RUN mkdir www

COPY wait-for-temporal.sh /usr/local/bin
RUN chmod +x /usr/local/bin/wait-for-temporal.sh

WORKDIR /home/containeruser/www

COPY --chown=containeruser:groupcontainer . .

RUN composer dump-autoload --optimize && \
    composer check-platform-reqs && \
    php bin/console cache:warmup

CMD ["/usr/local/bin/wait-for-temporal.sh", "temporal", "rr", "serve","-c",".rr.yaml"]
