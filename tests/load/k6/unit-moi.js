// POST /api/unit/moi — SLO p95 ≤ 200 мс (baseline: медиана p95 = 132 мс).
//
// Запуск (5 VU, 30 с):
//   docker run --rm -i --network unitman_default \
//     -v "$PWD/tests/load:/scripts" \
//     -e UNITMAN_BASE_URL=http://web:8080 \
//     -e UNITMAN_USER=... -e UNITMAN_PASSWORD=... \
//     -e RUNNER_PROGRESS_TOKEN=loadtest-k6 \
//     grafana/k6 run --vus 5 --duration 30s /scripts/k6/unit-moi.js
//
// Порог можно переопределить: -e SLO_UNIT_MOI_P95_MS=250

import http from 'k6/http';
import { check, sleep } from 'k6';

import {
  BASE_URL,
  authHeaders,
  isSuccessEnvelope,
  login,
  requireProgressToken,
  thresholdMs,
} from './lib/common.js';

const P95_MS = thresholdMs('SLO_UNIT_MOI_P95_MS', 200);

export const options = {
  thresholds: {
    checks: ['rate==1'],
    'http_req_failed{endpoint:unit_moi}': ['rate==0'],
    'http_req_duration{endpoint:unit_moi}': [`p(95)<${P95_MS}`],
  },
};

const BODY = JSON.stringify({ limit: 10, offset: 0 });

export function setup() {
  const token = login();
  requireProgressToken();

  return { token };
}

export default function (data) {
  const response = http.post(`${BASE_URL}/api/unit/moi`, BODY, {
    headers: authHeaders(data.token),
    tags: { endpoint: 'unit_moi', name: 'POST /api/unit/moi' },
  });

  check(response, {
    'unit/moi: HTTP 200': (r) => r.status === 200,
    'unit/moi: success envelope': (r) => isSuccessEnvelope(r),
  });

  sleep(0.1);
}
