import { onBeforeUnmount, ref, watch } from 'vue'
import { apiJson, ensureCsrfCookie } from '../services/api.js'

/** @type {Map<string, Set<(payload: { chat_id: number, message: unknown }) => void>>} */
const handlers = new Map()

/** @type {Set<() => void>} */
const httpOkSubscribers = new Set()

let abortController = null
let loopRunning = false
let csrfReady = false
let sinceId = 0
const activeThreadIds = new Set()

const FAST_POLL_MS = 2000
const BACKGROUND_POLL_MS = 10000
const MAX_BACKOFF_MS = 30000

function notifyHttpOk() {
  for (const cb of httpOkSubscribers) {
    try {
      cb()
    } catch {
      /* ignore */
    }
  }
}

/** Fires after each successful polling request (including reconnects). */
export function subscribeChatSseHttpOk(cb) {
  httpOkSubscribers.add(cb)
  return () => {
    httpOkSubscribers.delete(cb)
  }
}

function handlerCount() {
  let n = 0
  for (const set of handlers.values()) {
    n += set.size
  }
  return n
}

function dispatchPayload(payload) {
  if (payload == null || typeof payload !== 'object' || payload.message == null) {
    return
  }
  const chatId = payload.chat_id
  if (chatId == null) {
    return
  }
  const set = handlers.get(String(chatId))
  if (!set) {
    return
  }
  for (const fn of set) {
    try {
      fn(payload)
    } catch {
      /* ignore subscriber errors */
    }
  }
}

function sleep(ms) {
  return new Promise((r) => setTimeout(r, ms))
}

function withBackoffJitter(ms) {
  const jitterFactor = 0.75 + (Math.random() * 0.5)
  return Math.max(250, Math.round(ms * jitterFactor))
}

function currentPollIntervalMs() {
  return activeThreadIds.size > 0 ? FAST_POLL_MS : BACKGROUND_POLL_MS
}

async function readOneSession(signal) {
  if (!csrfReady) {
    await ensureCsrfCookie()
    csrfReady = true
  }
  const query = new URLSearchParams({
    since_id: String(sinceId),
    limit: '100',
  })
  const result = await apiJson('GET', `/api/chats/updates?${query.toString()}`)
  if (!result.ok) {
    if (result.status === 401 || result.status === 419) {
      csrfReady = false
    }
    throw new Error(`poll_http_${result.status}`)
  }
  notifyHttpOk()

  const payload = result.data && typeof result.data === 'object' ? result.data : null
  const nextSinceIdRaw = payload && 'next_since_id' in payload ? Number(payload.next_since_id) : sinceId
  const nextSinceId = Number.isFinite(nextSinceIdRaw) ? Math.max(sinceId, nextSinceIdRaw) : sinceId
  const rows = payload && Array.isArray(payload.data) ? payload.data : []

  for (const row of rows) {
    if (signal.aborted) {
      break
    }
    dispatchPayload(row)
  }
  sinceId = nextSinceId
}

async function runLoop() {
  loopRunning = true
  let backoffMs = 1000
  try {
    while (handlerCount() > 0) {
      abortController = new AbortController()
      const signal = abortController.signal
      try {
        await readOneSession(signal)
        backoffMs = 1000
      } catch {
        if (signal.aborted && handlerCount() === 0) {
          return
        }
        if (signal.aborted) {
          await sleep(200)
          continue
        }
        const delay = withBackoffJitter(backoffMs)
        backoffMs = Math.min(MAX_BACKOFF_MS, backoffMs * 2)
        await sleep(delay)
        continue
      }
      if (handlerCount() > 0) {
        await sleep(currentPollIntervalMs())
      }
    }
  } finally {
    loopRunning = false
    abortController = null
  }
}

function ensureLoop() {
  if (handlerCount() === 0) {
    return
  }
  if (!loopRunning) {
    void runLoop()
  }
}

/**
 * @param {string|number} chatId
 * @param {(payload: { chat_id: number, message: unknown }) => void} callback
 * @returns {() => void}
 */
export function registerChatSseListener(chatId, callback) {
  const key = String(chatId)
  if (!handlers.has(key)) {
    handlers.set(key, new Set())
  }
  handlers.get(key).add(callback)
  ensureLoop()
  return () => {
    const set = handlers.get(key)
    if (set) {
      set.delete(callback)
      if (set.size === 0) {
        handlers.delete(key)
      }
    }
    if (handlerCount() === 0) {
      abortController?.abort()
    }
  }
}

/**
 * @param {string|number} chatId
 * @param {(payload: { chat_id: number, message: unknown }) => void} callback
 * @returns {() => void}
 */
export function registerChatPollListener(chatId, callback) {
  return registerChatSseListener(chatId, callback)
}

/**
 * @param {import('vue').Ref<string|number|null|undefined>} chatIdRef
 * @param {(message: unknown) => void} onMessage
 */
export function useChatSseForThread(chatIdRef, onMessage) {
  const connected = ref(false)
  let unregister = null
  let offHttpOk = null
  let trackedThreadId = null

  function attach(chatId) {
    offHttpOk?.()
    unregister?.()
    offHttpOk = null
    unregister = null
    connected.value = false
    if (trackedThreadId) {
      activeThreadIds.delete(trackedThreadId)
      trackedThreadId = null
    }
    if (!chatId) {
      return
    }
    trackedThreadId = String(chatId)
    activeThreadIds.add(trackedThreadId)
    offHttpOk = subscribeChatSseHttpOk(() => {
      connected.value = true
    })
    unregister = registerChatSseListener(String(chatId), (payload) => {
      if (payload?.message) {
        onMessage(payload.message)
      }
    })
  }

  watch(
    chatIdRef,
    (id) => {
      attach(id)
    },
    { immediate: true },
  )

  onBeforeUnmount(() => {
    if (trackedThreadId) {
      activeThreadIds.delete(trackedThreadId)
      trackedThreadId = null
    }
    offHttpOk?.()
    offHttpOk = null
    unregister?.()
    unregister = null
    connected.value = false
  })

  return { connected }
}
