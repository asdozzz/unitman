local:
	docker compose up -d --build
	docker compose exec web php bin/console app:jobs start
local-stop:
	docker compose down
local-start:
	docker compose exec web php bin/console app:jobs start

mvp:
	docker compose -f dc.mvp.yml up -d
	docker compose -f dc.mvp.yml exec web php bin/console app:jobs start
mvp-stop:
	docker compose -f dc.mvp.yml down
mvp-start:
	docker compose -f dc.mvp.yml exec web php bin/console app:jobs start

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
	docker compose -f dc.mvp.yml exec web php bin/console doctrine:migrations:migrate
	docker compose -f dc.mvp.yml exec web php bin/console app:jobs start
