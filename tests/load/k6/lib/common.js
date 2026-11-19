// Общие helpers для нагрузочных сценариев Unitman.
//
// Все сценарии читают окружение из переменных:
//   UNITMAN_BASE_URL       — база API (по умолчанию http://web:8080);
//   UNITMAN_USER           — email тестового аккаунта с ROLE_ADMIN;
//   UNITMAN_PASSWORD       — пароль тестового аккаунта;
//   RUNNER_PROGRESS_TOKEN  — токен прогресса, зарегистрированный в Redis;
//   RUNNER_CALLBACK_SECRET — опционально, если на стенде включена HMAC-подпись.
//
// SLO-пороги можно ослабить/ужесточить через переменные SLO_*_P95_MS
// (см. thresholdMs), не меняя код сценариев.

import http from 'k6/http';
import { fail } from 'k6';
import { hmac } from 'k6/crypto';

export const BASE_URL = (__ENV.UNITMAN_BASE_URL || 'http://web:8080').replace(/\/+$/, '');
export const PROGRESS_TOKEN = (__ENV.RUNNER_PROGRESS_TOKEN || '').trim();
export const CALLBACK_SECRET = __ENV.RUNNER_CALLBACK_SECRET || '';

const USER = (__ENV.UNITMAN_USER || '').trim();
const PASSWORD = __ENV.UNITMAN_PASSWORD || '';

/**
 * Читает числовой SLO-порог из окружения, иначе возвращает значение по умолчанию.
 *
 * @param {string} name имя переменной окружения
 * @param {number} fallback порог из baseline-отчёта
 * @returns {number}
 */
export function thresholdMs(name, fallback) {
  const raw = __ENV[name];
  if (raw === undefined || raw === '') {
    return fallback;
  }

  const value = Number(raw);

  return Number.isFinite(value) && value > 0 ? value : fallback;
}

/**
 * Логин через json_login (`POST /api/login`).
 *
 * В setup() любая ошибка должна быть явной: иначе сценарий упадёт
 * неинформативным 401 на каждом запросе.
 *
 * @returns {string} JWT
 */
export function login() {
  if (USER === '' || PASSWORD === '') {
    fail('UNITMAN_USER and UNITMAN_PASSWORD must be set (test account with ROLE_ADMIN)');
  }

  const response = http.post(
    `${BASE_URL}/api/login`,
    JSON.stringify({ username: USER, password: PASSWORD }),
    {
      headers: { 'Content-Type': 'application/json' },
      tags: { name: 'POST /api/login' },
    },
  );

  if (response.status !== 200) {
    fail(`login failed: POST ${BASE_URL}/api/login -> HTTP ${response.status}: ${response.body}`);
  }

  let body;

  try {
    body = response.json();
  } catch (error) {
    fail(`login failed: response is not JSON: ${response.body}`);
  }

  if (!body || typeof body.token !== 'string' || body.token === '') {
    fail(`login failed: response has no token: ${response.body}`);
  }

  return body.token;
}

/**
 * Проверяет, что токен прогресса задан: без него saveSteps вернёт 400.
 */
export function requireProgressToken() {
  if (PROGRESS_TOKEN === '') {
    fail(
      'RUNNER_PROGRESS_TOKEN must be set; register it with '
        + 'tests/load/register-progress-token.sh <token>',
    );
  }
}

/**
 * Заголовки авторизованного JSON-запроса.
 *
 * @param {string} token JWT
 * @returns {Object<string, string>}
 */
export function authHeaders(token) {
  return {
    Authorization: `Bearer ${token}`,
    'Content-Type': 'application/json',
  };
}

/**
 * Собирает stepsContent — JSON-строку полного снапшота шагов.
 *
 * Формат шага совпадает с тем, что ожидает RunnerController::decodeSteps():
 * Command/Response/Success/Unixtime.
 *
 * @param {number} stepCount число шагов
 * @param {number} responseBytes размер поля Response одного шага
 * @returns {string}
 */
export function buildStepsContent(stepCount, responseBytes) {
  const response = 'x'.repeat(responseBytes);
  const steps = new Array(stepCount);

  for (let i = 0; i < stepCount; i += 1) {
    steps[i] = {
      Command: `loadtest step ${i + 1}`,
      Response: response,
      Success: true,
      Unixtime: 1730000000 + i,
    };
  }

  return JSON.stringify(steps);
}

/**
 * Тело запроса saveSteps: stepsContent — строка, а не вложенный JSON.
 *
 * @param {string} stepsContent
 * @returns {string}
 */
export function buildSaveStepsRequest(stepsContent) {
  return JSON.stringify({
    responseId: PROGRESS_TOKEN,
    stepsContent,
    finished: false,
    success: false,
  });
}

/**
 * HMAC-SHA256(responseId . stepsContent, secret), если секрет задан.
 *
 * @param {string} stepsContent
 * @returns {?string} hex-подпись либо null
 */
export function runnerSignature(stepsContent) {
  if (CALLBACK_SECRET === '') {
    return null;
  }

  return hmac('sha256', CALLBACK_SECRET, PROGRESS_TOKEN + stepsContent, 'hex');
}

/**
 * Заголовки saveSteps, включая подпись при включённом RUNNER_CALLBACK_SECRET.
 *
 * @param {?string} signature
 * @returns {Object<string, string>}
 */
export function saveStepsHeaders(signature) {
  const headers = { 'Content-Type': 'application/json' };

  if (signature !== null) {
    headers['X-Runner-Signature'] = signature;
  }

  return headers;
}

/**
 * Проверяет конверт App\Utils\Model\Reponse\Response::success().
 *
 * @param {Object} response k6 http response
 * @returns {boolean}
 */
export function isSuccessEnvelope(response) {
  try {
    return response.json('status') === 'success';
  } catch (error) {
    return false;
  }
}
