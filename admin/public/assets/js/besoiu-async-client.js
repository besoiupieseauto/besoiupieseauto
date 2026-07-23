/**
 * Besoiu Async Client — SSE + optimistic UI + cache + AbortController.
 * ES2022+, fără dependențe externe.
 */
(function initBesoiuAsyncClient(global) {
  'use strict';

  const DEFAULTS = {
    createUrl: '/admin/api/jobs_endpoint.php',
    sseUrlBase: '/admin/api/jobs_sse_endpoint.php',
    fetchTimeoutMs: 15000,
    sseMaxReconnects: 4,
    sseBackoffStartMs: 1000,
    sseBackoffMaxMs: 10000,
    pollFallbackMaxAttempts: 5,
    debounceMs: 300,
    cacheTtlMs: 60000,
  };

  /** @type {Set<{close: function():void}>} */
  const activeWatchers = new Set();

  /** @type {Map<string, {value:any,expires:number}>} */
  const memoryCache = new Map();

  if (typeof window !== 'undefined') {
    window.addEventListener('pagehide', () => {
      activeWatchers.forEach((watcher) => {
        try {
          watcher.close();
        } catch (_) { /* ignore */ }
      });
      activeWatchers.clear();
    });
  }

  function sleep(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
  }

  function fetchWithTimeout(url, options = {}, timeoutMs = DEFAULTS.fetchTimeoutMs) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), timeoutMs);
    const extSignal = options.signal;
    if (extSignal) {
      if (extSignal.aborted) controller.abort();
      extSignal.addEventListener('abort', () => controller.abort(), { once: true });
    }

    return fetch(url, { ...options, signal: controller.signal })
      .finally(() => clearTimeout(timer));
  }

  async function fetchJsonWithRetry(url, options = {}, maxAttempts = 3) {
    let attempt = 0;
    let delay = DEFAULTS.sseBackoffStartMs;

    while (attempt < maxAttempts) {
      attempt += 1;
      try {
        const res = await fetchWithTimeout(url, options);
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
          throw new Error(data.message || `HTTP ${res.status}`);
        }
        return { response: res, data };
      } catch (err) {
        if (attempt >= maxAttempts) throw err;
        await sleep(delay);
        delay = Math.min(delay * 2, DEFAULTS.sseBackoffMaxMs);
      }
    }

    throw new Error('fetchJsonWithRetry: epuizat');
  }

  function cacheGet(key) {
    const row = memoryCache.get(key);
    if (!row) return null;
    if (Date.now() > row.expires) {
      memoryCache.delete(key);
      return null;
    }
    return row.value;
  }

  function cacheSet(key, value, ttlMs = DEFAULTS.cacheTtlMs) {
    memoryCache.set(key, { value, expires: Date.now() + ttlMs });
  }

  /**
   * @param {object} params
   * @param {string} params.type
   * @param {string} params.queue
   * @param {object} params.payload
   * @param {string} [params.priority]
   * @param {string} [params.idempotencyKey]
   * @param {function(object):void} [params.onOptimistic]
   * @param {function(object):void} [params.onRollback]
   * @param {AbortSignal} [params.signal]
   */
  async function submitJob(params) {
    const {
      type,
      queue,
      payload,
      priority = 'normal',
      idempotencyKey = '',
      onOptimistic,
      onRollback,
      signal,
    } = params;

    const optimisticToken = { type, queue, at: Date.now() };
    if (typeof onOptimistic === 'function') onOptimistic(optimisticToken);

    try {
      const headers = { 'Content-Type': 'application/json' };
      if (idempotencyKey) headers['Idempotency-Key'] = idempotencyKey;

      const { data } = await fetchJsonWithRetry(
        params.createUrl || DEFAULTS.createUrl,
        {
          method: 'POST',
          headers,
          body: JSON.stringify({ type, queue, payload, priority, idempotency_key: idempotencyKey }),
          credentials: 'same-origin',
          signal,
        },
        3
      );

      return data;
    } catch (err) {
      if (typeof onRollback === 'function') onRollback(optimisticToken, err);
      throw err;
    }
  }

  /**
   * Ascultă SSE cu reconectare limitată (nu infinită).
   */
  function watchJob(jobId, handlers = {}) {
    const {
      onStatus,
      onDone,
      onFailed,
      onError,
      signal,
      sseUrl = `${DEFAULTS.sseUrlBase}?job_id=${encodeURIComponent(jobId)}`,
    } = handlers;

    let reconnects = 0;
    let closed = false;
    let source = null;

    const close = () => {
      closed = true;
      if (source) {
        source.close();
        source = null;
      }
    };

    if (signal) {
      signal.addEventListener('abort', close, { once: true });
    }

    const connect = () => {
      if (closed) return;
      source = new EventSource(sseUrl, { withCredentials: true });

      source.addEventListener('status', (ev) => {
        const snap = JSON.parse(ev.data || '{}');
        if (typeof onStatus === 'function') onStatus(snap);
        if (snap.status === 'done' && typeof onDone === 'function') {
          onDone(snap);
          close();
        }
        if (snap.status === 'failed' && typeof onFailed === 'function') {
          onFailed(snap);
          close();
        }
      });

      source.addEventListener('timeout', () => {
        close();
        if (reconnects < DEFAULTS.sseMaxReconnects) {
          reconnects += 1;
          const delay = Math.min(
            DEFAULTS.sseBackoffStartMs * (2 ** (reconnects - 1)),
            DEFAULTS.sseBackoffMaxMs
          );
          setTimeout(connect, delay);
        } else {
          fallbackToPolling('SSE reconectări epuizate');
        }
      });

      source.addEventListener('close', () => close());

      source.onerror = () => {
        close();
        if (reconnects < DEFAULTS.sseMaxReconnects) {
          reconnects += 1;
          const delay = Math.min(
            DEFAULTS.sseBackoffStartMs * (2 ** (reconnects - 1)),
            DEFAULTS.sseBackoffMaxMs
          );
          setTimeout(connect, delay);
          return;
        }
        fallbackToPolling('SSE error');
      };
    };

    const fallbackToPolling = (reason) => {
      if (closed) return;
      closed = true;
      if (typeof handlers.onStatus === 'function') {
        handlers.onStatus({
          status: 'processing',
          message: 'SSE indisponibil, folosesc polling limitat.',
          progress: 0,
        });
      }
      pollJobStatus(jobId, handlers).catch((err) => {
        if (typeof onError === 'function') onError(err);
      });
      if (typeof onError === 'function') {
        onError(new Error(reason + ' — fallback polling'));
      }
    };

    connect();

    const watcher = { close };
    activeWatchers.add(watcher);

    return {
      close: () => {
        close();
        activeWatchers.delete(watcher);
      },
    };
  }

  /**
   * Fallback polling cu backoff exponențial — DOAR dacă SSE indisponibil.
   */
  async function pollJobStatus(jobId, handlers = {}) {
    const { onStatus, onDone, onFailed, signal } = handlers;
    let attempt = 0;
    let delay = DEFAULTS.sseBackoffStartMs;

    while (attempt < DEFAULTS.pollFallbackMaxAttempts) {
      if (signal?.aborted) return;
      attempt += 1;

      const cacheKey = `job:${jobId}`;
      let snap = cacheGet(cacheKey);
      if (!snap) {
        const res = await fetchWithTimeout(
          `${DEFAULTS.sseUrlBase}?job_id=${encodeURIComponent(jobId)}&format=json`,
          { credentials: 'same-origin', signal },
          8000
        );
        if (res.ok) {
          snap = await res.json();
          cacheSet(cacheKey, snap, 500);
        }
      }

      if (snap && typeof onStatus === 'function') onStatus(snap);
      if (snap?.status === 'done') {
        if (typeof onDone === 'function') onDone(snap);
        return;
      }
      if (snap?.status === 'failed') {
        if (typeof onFailed === 'function') onFailed(snap);
        return;
      }

      await sleep(delay);
      delay = Math.min(delay * 2, DEFAULTS.sseBackoffMaxMs);
    }
  }

  function debounce(fn, waitMs = DEFAULTS.debounceMs) {
    let timer = 0;
    return (...args) => {
      clearTimeout(timer);
      timer = window.setTimeout(() => fn(...args), waitMs);
    };
  }

  global.BesoiuAsync = {
    DEFAULTS,
    submitJob,
    watchJob,
    pollJobStatus,
    fetchWithTimeout,
    debounce,
    cacheGet,
    cacheSet,
  };
})(window);
