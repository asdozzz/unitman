// POST /api/runner/saveSteps — три сценария из baseline-отчёта:
//   small  — 10 шагов,               SLO p95 ≤ 120 мс (baseline 43 мс);
//   medium — 500 шагов,              SLO p95 ≤ 150 мс (baseline 50 мс);
//   max    — 400 шагов × 6 КБ (≈2,4 МиБ stepsContent),
//                                    SLO p95 ≤ 350 мс (baseline 219 мс).
//
// По умолчанию сценарии идут последовательно (small → medium → max),
// чтобы замеры не искажали друг друга. Один сценарий:
//   -e SAVE_STEPS_SCENARIO=small
// Параметры нагрузки (имена не начинаются с K6_, иначе k6 воспримет их
// как собственные опции и перезапишет scenarios):
//   -e LOAD_VUS=5 -e LOAD_DURATION=30s
// Пороги можно переопределить:
//   -e SLO_SAVESTEPS_SMALL_P95_MS=150
//   -e SLO_SAVESTEPS_MEDIUM_P95_MS=180
//   -e SLO_SAVESTEPS_MAX_P95_MS=400
//
// Запуск одного сценария:
//   docker run --rm -i --network unitman_default \
//     -v "$PWD/tests/load:/scripts" \
//     -e UNITMAN_BASE_URL=http://web:8080 \
//     -e UNITMAN_USER=... -e UNITMAN_PASSWORD=... \
//     -e RUNNER_PROGRESS_TOKEN=loadtest-k6 \
//     -e SAVE_STEPS_SCENARIO=max -e LOAD_VUS=1 -e LOAD_DURATION=30s \
//     grafana/k6 run /scripts/k6/save-steps.js

import http from 'k6/http';
import { check } from 'k6';
import { Trend } from 'k6/metrics';

import {
  BASE_URL,
  buildSaveStepsRequest,
  buildStepsContent,
  login,
  requireProgressToken,
  runnerSignature,
  saveStepsHeaders,
  thresholdMs,
} from './lib/common.js';

const VUS = Number(__ENV.LOAD_VUS || 5);
const DURATION = __ENV.LOAD_DURATION || '30s';
const SELECTED = (__ENV.SAVE_STEPS_SCENARIO || 'all').toLowerCase();

const SLO = {
  small: thresholdMs('SLO_SAVESTEPS_SMALL_P95_MS', 120),
  medium: thresholdMs('SLO_SAVESTEPS_MEDIUM_P95_MS', 150),
  max: thresholdMs('SLO_SAVESTEPS_MAX_P95_MS', 350),
};

// stepsContent собирается один раз на VU, а не на каждую итерацию:
// для max это ~2,4 МиБ JSON, повторная сборка исказила бы замер.
const CACHE = {
  small: { body: null, signature: null },
  medium: { body: null, signature: null },
  max: { body: null, signature: null },
};

const TRENDS = {
  small: new Trend('savesteps_small_duration', true),
  medium: new Trend('savesteps_medium_duration', true),
  max: new Trend('savesteps_max_duration', true),
};

const PAYLOAD = {
  small: { steps: 10, responseBytes: 64 },
  medium: { steps: 500, responseBytes: 64 },
  max: { steps: 400, responseBytes: 6144 },
};

function durationToSeconds(value) {
  const match = /^(\d+(?:\.\d+)?)(ms|s|m|h)$/.exec(value);

  if (match === null) {
    return 30;
  }

  const amount = Number(match[1]);

  switch (match[2]) {
    case 'ms':
      return amount / 1000;
    case 'm':
      return amount * 60;
    case 'h':
      return amount * 3600;
    default:
      return amount;
  }
}

const SCENARIO = {
  small: { executor: 'constant-vus', exec: 'saveStepsSmall', vus: VUS, duration: DURATION, gracefulStop: '5s' },
  medium: { executor: 'constant-vus', exec: 'saveStepsMedium', vus: VUS, duration: DURATION, gracefulStop: '5s' },
  max: { executor: 'constant-vus', exec: 'saveStepsMax', vus: VUS, duration: DURATION, gracefulStop: '5s' },
};

let scenarios = {};
let thresholds = {
  checks: ['rate==1'],
  'http_req_failed{endpoint:save_steps}': ['rate==0'],
};

if (SELECTED === 'all') {
  const step = durationToSeconds(DURATION);

  scenarios = {
    small: { ...SCENARIO.small, startTime: '0s' },
    medium: { ...SCENARIO.medium, startTime: `${step}s` },
    max: { ...SCENARIO.max, startTime: `${step * 2}s` },
  };

  thresholds['savesteps_small_duration{scenario:small}'] = [`p(95)<${SLO.small}`];
  thresholds['savesteps_medium_duration{scenario:medium}'] = [`p(95)<${SLO.medium}`];
  thresholds['savesteps_max_duration{scenario:max}'] = [`p(95)<${SLO.max}`];
} else if (Object.prototype.hasOwnProperty.call(SCENARIO, SELECTED)) {
  scenarios = { [SELECTED]: SCENARIO[SELECTED] };
  thresholds[`savesteps_${SELECTED}_duration{scenario:${SELECTED}}`] = [`p(95)<${SLO[SELECTED]}`];
} else {
  throw new Error(`Unknown SAVE_STEPS_SCENARIO="${SELECTED}"; expected small, medium, max or all`);
}

export const options = {
  scenarios,
  thresholds,
};

export function setup() {
  const token = login();
  requireProgressToken();

  return { token };
}

function payloadFor(name) {
  const cached = CACHE[name];

  if (cached.body === null) {
    const config = PAYLOAD[name];
    const stepsContent = buildStepsContent(config.steps, config.responseBytes);

    cached.body = buildSaveStepsRequest(stepsContent);
    cached.signature = runnerSignature(stepsContent);
  }

  return cached;
}

function runSaveSteps(name) {
  const payload = payloadFor(name);
  const response = http.post(`${BASE_URL}/api/runner/saveSteps`, payload.body, {
    headers: saveStepsHeaders(payload.signature),
    tags: {
      endpoint: 'save_steps',
      name: `POST /api/runner/saveSteps (${name})`,
    },
  });

  TRENDS[name].add(response.timings.duration, { scenario: name });

  check(response, {
    [`saveSteps ${name}: HTTP 200`]: (r) => r.status === 200,
    [`saveSteps ${name}: true`]: (r) => r.body === 'true',
  });
}

export function saveStepsSmall() {
  runSaveSteps('small');
}

export function saveStepsMedium() {
  runSaveSteps('medium');
}

export function saveStepsMax() {
  runSaveSteps('max');
}
