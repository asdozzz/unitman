// Smoke-сценарий: логин в setup() и по одному запросу на каждый
// нагрузочный эндпоинт. Нужен для проверки скриптов и доступов,
// а не для измерения задержек.
//
// Запуск:
//   docker run --rm -i --network unitman_default \
//     -v "$PWD/tests/load:/scripts" \
//     -e UNITMAN_BASE_URL=http://web:8080 \
//     -e UNITMAN_USER=... -e UNITMAN_PASSWORD=... \
//     -e RUNNER_PROGRESS_TOKEN=loadtest-k6 \
//     grafana/k6 run --vus 1 --duration 5s /scripts/k6/smoke.js

import http from 'k6/http';
import { check, sleep } from 'k6';

import {
  BASE_URL,
  authHeaders,
  buildSaveStepsRequest,
  buildStepsContent,
  isSuccessEnvelope,
  login,
  requireProgressToken,
  runnerSignature,
  saveStepsHeaders,
} from './lib/common.js';

// smoke не проверяет SLO: только корректность ответов.
export const options = {
  thresholds: {
    checks: ['rate==1'],
    http_req_failed: ['rate==0'],
  },
};

const SMOKE_STEPS = buildStepsContent(10, 64);
const SMOKE_SAVE_STEPS_BODY = buildSaveStepsRequest(SMOKE_STEPS);
const SMOKE_SIGNATURE = runnerSignature(SMOKE_STEPS);

export function setup() {
  const token = login();
  requireProgressToken();

  return { token };
}

export default function (data) {
  const list = http.post(
    `${BASE_URL}/api/unit/list`,
    JSON.stringify({ limit: 100, offset: 0 }),
    {
      headers: authHeaders(data.token),
      tags: { name: 'POST /api/unit/list' },
    },
  );

  check(list, {
    'unit/list: HTTP 200': (response) => response.status === 200,
    'unit/list: success envelope': (response) => isSuccessEnvelope(response),
  });

  const moi = http.post(
    `${BASE_URL}/api/unit/moi`,
    JSON.stringify({ limit: 10, offset: 0 }),
    {
      headers: authHeaders(data.token),
      tags: { name: 'POST /api/unit/moi' },
    },
  );

  check(moi, {
    'unit/moi: HTTP 200': (response) => response.status === 200,
    'unit/moi: success envelope': (response) => isSuccessEnvelope(response),
  });

  // saveSteps — публичный эндпоинт без JWT, авторизация не нужна.
  const saveSteps = http.post(
    `${BASE_URL}/api/runner/saveSteps`,
    SMOKE_SAVE_STEPS_BODY,
    {
      headers: saveStepsHeaders(SMOKE_SIGNATURE),
      tags: { name: 'POST /api/runner/saveSteps' },
    },
  );

  check(saveSteps, {
    'saveSteps: HTTP 200': (response) => response.status === 200,
    'saveSteps: true': (response) => response.body === 'true',
  });

  sleep(0.2);
}
