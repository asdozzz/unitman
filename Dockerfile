FROM spacetabio/roadrunner-alpine:8.1-base-xdebug-1.11.0

ARG CURRENT_USER_ID=1000
ARG CURRENT_USER_GROUP=1000

RUN addgroup --g ${CURRENT_USER_GROUP} groupcontainer
RUN adduser -u ${CURRENT_USER_ID} -G groupcontainer -h /home/containeruser -D containeruser
RUN adduser containeruser root

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
COPY --from=temporalio/admin-tools:1.23.0 /usr/local/bin/tctl /usr/local/bin/tctl

RUN mkdir www

COPY wait-for-temporal.sh /usr/local/bin
RUN chmod +x /usr/local/bin/wait-for-temporal.sh

WORKDIR /home/containeruser/www

COPY --chown=containeruser:groupcontainer . .

ENV COMPOSER_ALLOW_SUPERUSER=1

RUN composer dump-autoload --optimize && \
    composer check-platform-reqs && \
    php bin/console cache:warmup

CMD ["/usr/local/bin/wait-for-temporal.sh", "temporal", "rr", "serve","-c",".rr.yaml"]
