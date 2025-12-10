local:
	docker compose up -d

local-stop:
	docker compose down

mvp:
	docker compose -f dc.mvp.yml up -d
mvp-stop:
	docker compose -f dc.mvp.yml down

tools:
	docker compose -f dc.tools.yml up -d
tools-stop:
	docker compose -f dc.tools.yml down
