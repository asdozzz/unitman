local:
	docker compose up -d
	docker compose exec web-worker php bin/console app:jobs start
local-stop:
	docker compose down
local-start:
	docker compose exec web-worker php bin/console app:jobs start

mvp:
	docker compose -f dc.mvp.yml up -d --build
	docker compose -f dc.mvp.yml exec web-worker php bin/console app:jobs start
mvp-stop:
	docker compose -f dc.mvp.yml down
mvp-start:
	docker compose -f dc.mvp.yml exec web-worker php bin/console app:jobs start

tools:
	docker compose -f dc.tools.yml up -d
tools-stop:
	docker compose -f dc.tools.yml down

tag:
	docker build -f Dockerfile.dist -t asdozzz/roadrunner:$(VERSION) .
	docker push asdozzz/roadrunner:$(VERSION)

update:
	git pull
	docker compose -f dc.mvp.yml up -d
	docker compose -f dc.mvp.yml exec web-worker php bin/console doctrine:migrations:migrate
	docker compose -f dc.mvp.yml exec web-worker php bin/console app:jobs start


tl:
	docker compose -f dc.temporal.yml up -d
tl-stop:
	docker compose -f dc.temporal.yml down


stop-all:
	make local-stop
	make tl-stop
	make tools-stop

# --- Runner secret -----------------------------------------------------------
# Один общий RUNNER_SECRET_MASTER_KEY для PHP-ролей (web/web-worker/
# web-temporal) и edge-progress. Ключ — 64 hex-символа (32 случайных байта).
#
# По умолчанию пишем в .env: этот файл игнорируется git и одновременно виден
# Docker Compose (подстановка ${RUNNER_SECRET_MASTER_KEY}) и Symfony (Dotenv).
# Секрет никогда не печатается в stdout.
#
# Идемпотентность: если ключ уже задан непустым — файл не меняется.
# Перезапись — только FORCE=1 (или make runner-secret-rotate).
# Для безопасной проверки: make runner-secret ENV_FILE=/tmp/test.env
ENV_FILE ?= .env

runner-secret:
	@set -e; \
	env_file='$(ENV_FILE)'; \
	force='$(FORCE)'; \
	if [ -z "$$env_file" ]; then \
	  echo 'ENV_FILE is empty' >&2; \
	  exit 1; \
	fi; \
	case "$$env_file" in \
	  */*) env_dir=$${env_file%/*}; [ -n "$$env_dir" ] || env_dir=.;; \
	  *) env_dir=.;; \
	esac; \
	if [ ! -d "$$env_dir" ]; then \
	  echo "Directory for ENV_FILE does not exist: $$env_dir" >&2; \
	  exit 1; \
	fi; \
	if [ ! -f "$$env_file" ]; then \
	  : > "$$env_file"; \
	fi; \
	current=$$(sed -n 's/^[[:space:]]*RUNNER_SECRET_MASTER_KEY[[:space:]]*=[[:space:]]*//p' "$$env_file" | tail -n 1 | tr -d '\r' | sed 's/[[:space:]]*$$//'); \
	if [ -n "$$current" ] && [ "$$force" != "1" ]; then \
	  echo "RUNNER_SECRET_MASTER_KEY already set in $$env_file; leaving unchanged."; \
	  echo "Use FORCE=1 or 'make runner-secret-rotate' to rotate the key."; \
	  exit 0; \
	fi; \
	if [ -n "$$current" ]; then \
	  echo "WARNING: rotating RUNNER_SECRET_MASTER_KEY invalidates every per-runner secret."; \
	  echo "All existing runners must be re-initialised (runner init)."; \
	fi; \
	if command -v openssl >/dev/null 2>&1; then \
	  key=$$(openssl rand -hex 32); \
	elif command -v php >/dev/null 2>&1; then \
	  key=$$(php -r 'echo bin2hex(random_bytes(32));'); \
	else \
	  echo 'Neither openssl nor php is available to generate the key' >&2; \
	  exit 1; \
	fi; \
	printf '%s\n' "$$key" | grep -Eq '^[0-9a-f]{64}$$' || { \
	  echo 'Generated key is not 64 lowercase hex characters' >&2; \
	  exit 1; \
	}; \
	umask 077; \
	tmp="$${env_file}.tmp.$$$$"; \
	trap 'rm -f "$$tmp"' 0 HUP INT TERM; \
	awk -v key="$$key" ' \
	  BEGIN { done = 0 } \
	  /^[[:space:]]*RUNNER_SECRET_MASTER_KEY[[:space:]]*=/ { \
	    print "RUNNER_SECRET_MASTER_KEY=" key; \
	    done = 1; \
	    next; \
	  } \
	  { print } \
	  END { if (!done) print "RUNNER_SECRET_MASTER_KEY=" key } \
	' "$$env_file" > "$$tmp"; \
	chmod 600 "$$tmp"; \
	mv "$$tmp" "$$env_file"; \
	echo "RUNNER_SECRET_MASTER_KEY written to $$env_file (mode 0600)."; \
	if [ -n "$$current" ]; then \
	  echo "Rotation complete: re-initialise every runner so they receive new secrets."; \
	else \
	  echo "Keep this key stable; backend and edge-progress must use the same value."; \
	fi

# Принудительная ротация: эквивалент FORCE=1.
# Инвалидирует все выданные per-runner секреты — все раннеры нужно
# переинициализировать (runner init).
runner-secret-rotate: FORCE=1
runner-secret-rotate: runner-secret

.PHONY: runner-secret runner-secret-rotate
