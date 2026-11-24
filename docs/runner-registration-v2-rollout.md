# Runner registration v2 — rollout runbook (Wave D)

Operational guide for rolling out the agreed **runner registration v2**
contract on the backend. This document is intentionally additive: it does not
change code, environment values, README, `plans/runner-multi-runner.md`, or
runner/frontend sources. Source of truth for the contract itself remains
[`plans/runner-multi-runner.md`](../plans/runner-multi-runner.md); this file
turns it into an ordered, checkable deployment procedure.

> Scope reminder: everything below is **breaking** for existing runners.
> There is no compatibility mode. Every runner must be re-initialised.

---

## 1. Contract summary (short)

### Registration

- `runner init` asks **only for the backend host**.
- Registration is `POST http://<host>:8082/api/runner/register` with body
  `{"hostname": "<name>", "version": "<version>"}`.
- The endpoint is **public**: no JWT and **no bootstrap signature**.
- Response `201 Created`:

  ```json
  {
    "runnerId": "550e8400-e29b-41d4-a716-446655440000",
    "taskQueue": "550e8400-e29b-41d4-a716-446655440000",
    "secret": "<64 hex>",
    "registryHost": "https://registry.example.com",
    "ports": { "api": 8082, "temporal": 7233, "registry": 5000 }
  }
  ```

  - `taskQueue` is **always exactly equal** to `runnerId`.
  - `secret` is a 64-char lowercase hex string.
  - `ports` comes from `UNITMAN_API_PORT`, `UNITMAN_TEMPORAL_PUBLIC_PORT`,
    `UNITMAN_REGISTRY_PUBLIC_PORT` (defaults `8082` / `7233` / `5000`).
  - `registryHost` comes from `UNITMAN_REGISTRY_PUBLIC_ADDRESS`.

### Per-runner secret

```
secret = hash_hmac('sha256', runnerId, RUNNER_SECRET_MASTER_KEY ?: APP_SECRET)
```

- Deterministic from `runnerId` and the master key.
- Returned **once**, at registration.
- **Not stored** in the database, **not logged**, and **never exposed** in
  `GET /api/runner/list` or OpenAPI.
- Runner persists it as `RunnerSecret` in `config.json` with mode `0600`.

### Callback authentication

The runner sends two headers on every authenticated callback:

| Header | Value |
|--------|-------|
| `X-Runner-Id` | the registered `runnerId` |
| `X-Runner-Signature` | `hex(HMAC-SHA256(payload, secret))` |

Signed payloads:

| Callback | Signed payload |
|----------|----------------|
| health | raw request body |
| `unitStateNotifications` | raw request body |
| `saveSteps` | `responseId . stepsContent` (concatenated) |
| build-state `GET` | empty string |
| build-state `PUT` | raw request body |

Error semantics: unknown `X-Runner-Id` → **404 `runner.not_found`**; bad
signature → **401**. For build-state the `X-Runner-Id` must also equal the
`runnerId` in the path.

### Public address

- Set by an **admin** only:
  - `PUT /api/runner/{runnerId}/public-address`
  - `DELETE /api/runner/{runnerId}/public-address`
  - `POST /api/runner/{runnerId}/public-address/check`
- Validation: absolute `http`/`https` URL, non-empty host, no userinfo.
- Runners **without** a public address remain in the registry and can send
  health, but are **not selectable** for builds or link construction.

---

## 2. Pre-deploy backend

### 2.1 Set a stable master key

For the compose-based deployment, generate the key once from the repository
root:

```sh
make runner-secret
```

- Writes a 64-hex value (`openssl rand -hex 32`, with a PHP fallback) to `.env`.
  That file is gitignored and is read by both Docker Compose (for
  `${RUNNER_SECRET_MASTER_KEY}` substitution) and Symfony Dotenv.
- **Idempotent:** if `RUNNER_SECRET_MASTER_KEY` is already non-empty, nothing is
  changed. Rotation requires `make runner-secret-rotate` (or `FORCE=1`).
- Safe test run without touching the real file:
  `make runner-secret ENV_FILE=/tmp/runner-secret-test.env`.
- The same value must be visible to `web`, `web-worker`, `web-temporal` and
  `edge-progress`; `edge-gateway` does not need it.

In production, set `RUNNER_SECRET_MASTER_KEY` explicitly in the deployment
environment / secret manager instead of relying on a local `.env`.

- Do **not** rely on the `APP_SECRET` fallback long-term.
- Rotating either `RUNNER_SECRET_MASTER_KEY` or the fallback `APP_SECRET`
  changes every per-runner secret, invalidating all existing runner configs and
  forcing **every runner to re-init**.
- Pick a high-entropy value and treat it as permanent deployment state.

### 2.2 Confirm public ports and registry address

Confirm (at minimum):

```
UNITMAN_TEMPORAL_PUBLIC_PORT=7233
UNITMAN_REGISTRY_PUBLIC_ADDRESS=https://registry.example.com
UNITMAN_REGISTRY_PUBLIC_PORT=5000
UNITMAN_API_PORT=8082
```

Also confirm that **no global registration or callback secrets are
configured**. There is no bootstrap/global callback secret in v2: only the
per-runner secret derived from `RUNNER_SECRET_MASTER_KEY` (fallback
`APP_SECRET`) exists. Do not add a homemade one.

### 2.3 Deploy and verify the backend

Deploy the backend (including the RoadRunner roles that serve the runner
callbacks), then run:

```sh
php bin/console lint:container
php bin/phpunit
```

Docker equivalent, if the project runs its tooling inside the `web` service:

```sh
docker compose exec -T web php bin/console lint:container
docker compose exec -T web php bin/phpunit
```

Both must pass before proceeding.

---

## 3. Edge / canary redeploy

The `edge-progress` canary **no longer validates a global secret**. The
already-running old container keeps the old behaviour until it is recreated.

Rebuild and recreate it:

```sh
docker compose --profile edge up -d --build edge-progress
```

Verify it is healthy:

```sh
curl -s http://localhost:${EDGE_PROGRESS_PORT:-18081}/healthz
# or via the gateway: http://<host>:${EDGE_GATEWAY_PORT:-8090}
```

### Security trade-off (explicit)

`edge-progress` now trusts the pre-registered `responseId` token instead of
the per-runner HMAC used by the PHP path. It does not store runner secrets.

- If `edge-progress` is reachable from untrusted networks, **retire it** or
  add backend verification before relying on it.
- Treat the `responseId` token as a bearer capability: protect it, and prefer
  the PHP path (`:8082`) where per-runner HMAC is enforced.

---

## 4. Remove old secrets

Delete all remnants of the pre-v2 bootstrap/callback secrets from:

- deployment environment variables,
- secret managers / vault entries,
- compose overrides and `.env` files that are actually deployed.

Remove any global registration/callback secret and any bootstrap-signature
header configuration. Do **not** replace them with a homemade bootstrap secret.
Registration is public by design (see §9); callback authentication uses the
per-runner secret (`X-Runner-Id` + `X-Runner-Signature`).

---

## 5. Re-init every runner (BREAKING, no compatibility)

Existing `config.json` files **without** `runnerSecret` fail validation.

For each runner:

1. Stop the runner process.
2. Run the init flow, providing **only the backend host**:

   ```sh
   runner init <backend-host>
   ```

3. The runner calls `POST /api/runner/register`, receives the per-runner
   secret and public ports, and writes `config.json` with mode `0600`.
4. Restart the runner process.

Verify the resulting `config.json` contains:

- `runnerId`
- `runnerSecret` (64 hex)
- `taskQueue` equal to `runnerId`
- `registryHost`
- `ports.api`, `ports.temporal`, `ports.registry`

Do not copy old `config.json` files between runners: each runner needs its own
`runnerId`/secret pair.

---

## 6. Assign public addresses

As an administrator, use the UI card or the API.

Set an address:

```sh
curl -s -X PUT "http://<host>:8082/api/runner/<runnerId>/public-address" \
  -H 'Content-Type: application/json' \
  -d '{"publicAddress":"https://runner-1.example.com"}'
# -> {"runnerId":"...","publicAddress":"https://runner-1.example.com"}
```

Optional advisory reachability check (does **not** block saving; nothing is
persisted by the check):

```sh
curl -s -X POST "http://<host>:8082/api/runner/<runnerId>/public-address/check" \
  -H 'Content-Type: application/json' \
  -d '{"publicAddress":"https://runner-1.example.com"}'
# -> {"reachable": true} or {"reachable": false, "error": "..."}
```

Clear an address:

```sh
curl -s -o /dev/null -w '%{http_code}\n' \
  -X DELETE "http://<host>:8082/api/runner/<runnerId>/public-address"
# -> 204
```

Runners remain **non-selectable** until a valid public address is set. Every
runner that should build units or serve links must be assigned an address.

---

## 7. Post-deploy verification checklist

### 7.1 Registry list

```sh
curl -s "http://<host>:8082/api/runner/list"
```

- Each entry shows `publicAddress`, `registeredAt`, `hasPublicAddress`.
- No `secret` field is present anywhere in the response.

### 7.2 Callback authentication

- Health callback with correct `X-Runner-Id` + `X-Runner-Signature` → `200`.
- Tampered signature → `401`.
- Unknown `X-Runner-Id` → `404`.

### 7.3 Progress and build-state

- `saveSteps` verifies the signature over `responseId . stepsContent`.
- build-state `PUT` verifies the signature over the raw body.
- build-state `GET` verifies the signature over the empty payload.
- build-state `GET`/`PUT` both return success for a valid runner and a known
  `projectId`.

### 7.4 Selection gate

- A registered runner with **no** public address is excluded from selection.
- After `PUT .../public-address`, the same runner becomes selectable.

### 7.5 Frontend runner card

- The card displays the public address or an explicit “not set” state.
- `Check`, `Save`, and `Delete` actions work.
- Loading / error / success states are visible.
- The list refreshes after each action.

---

## 8. Rollback and rotation notes

- **Rolling back is not a code-only operation.** The v2 contract has no
  compatibility with the old global-secret scheme.
- Rotate the shared key with `make runner-secret-rotate` (equivalent to
  `make runner-secret FORCE=1`). This rewrites `RUNNER_SECRET_MASTER_KEY` in
  `.env` and prints no secret value.
- Rotating `RUNNER_SECRET_MASTER_KEY` (or the `APP_SECRET` fallback) requires
  **re-initialising every runner**. All previously issued secrets become
  invalid immediately.
- The per-runner secret is returned **only once**. If it is lost:
  - re-register the runner (it receives a **new `runnerId`**), or
  - re-run `runner init` to obtain a fresh secret/identity.
- Because `runnerId` is also the Temporal task queue, changing it changes the
  queue name; ensure the old worker is stopped and the new one started.

---

## 9. Security risk: registration is open by design

`POST /api/runner/register` is intentionally **public**. Anyone who can reach
it can obtain a `runnerId` and a valid per-runner secret for that id. This is
an explicit design decision, not an oversight.

Recommended controls:

- Terminate **HTTPS** in front of the API / use a reverse proxy.
- Apply **rate limiting** to `/api/runner/register`.
- **Network allow-list** the registration endpoint to runner networks.
- Monitor for registration floods / unexpected `hostname` values and alert.
- Keep `RUNNER_SECRET_MASTER_KEY` stable and secret; it is the root of trust
  for all runner signatures.

**No bootstrap secret will be added.** Do not introduce one as a workaround;
use network and rate controls instead.

---

## Appendix A. Docs hygiene check

Run from the backend repository root and confirm that no legacy registration,
callback or proxy-source terminology is present in active config-bearing
files:

```sh
grep -rnE 'RUNNER_SECRET_MASTER_KEY|X-Runner-Id|publicAddress' \
  docs README.md plans src/Runner config 2>/dev/null
```

Notes for the Wave D rollout:

- Active operational docs must describe the v2 contract: registration is
  public, callback authentication uses the per-runner secret and
  `X-Runner-Id`, and links are built only from `publicAddress`.
- The only tolerated legacy concept is the read-only proxy-address field in
  `RunnerState`, kept solely so historical database payloads can be
  deserialized. It is never populated and never used to build links.
- Historical Doctrine migration files are not edited; they contain no active
  configuration instructions.
