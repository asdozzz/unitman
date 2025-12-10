# syntax=docker/dockerfile:1
FROM asdozzz/roadrunner:0.0.2

COPY --chown=containeruser:groupcontainer composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader

COPY --chown=containeruser:groupcontainer . .
RUN composer dump-autoload --optimize && \
    composer run-script post-install-cmd && \
    composer check-platform-reqs && \
    php bin/console cache:warmup

CMD ["/usr/local/bin/wait-for-temporal.sh", "temporal", "rr", "serve","-c",".rr.yaml"]
#ENTRYPOINT ["tail", "-f", "/dev/null"]
