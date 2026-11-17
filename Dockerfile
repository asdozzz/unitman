# syntax=docker/dockerfile:1
FROM asdozzz/roadrunner:0.0.10

COPY --chown=containeruser:groupcontainer composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader --prefer-dist

COPY --chown=containeruser:groupcontainer . .
RUN composer dump-autoload --optimize && \
    composer check-platform-reqs

CMD ["rr", "serve","-c",".rr.yaml"]
#ENTRYPOINT ["tail", "-f", "/dev/null"]
