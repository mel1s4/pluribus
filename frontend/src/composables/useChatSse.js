import { onBeforeUnmount, ref, watch } from 'vue'
import { apiBaseUrl, chatSseHeaders, ensureCsrfCookie } from '../services/api.js'

/** @type {Map<string, Set<(payload: { chat_id: number, message: unknown }) => void>>} */
const handlers = new Map()

/** @type {Set<() => void>} */
const httpOkSubscribers = new Set()

let abortController = null
let loopRunning = false
let csrfReady = false

function notifyHttpOk() {
  for (const cb of httpOkSubscribers) {
    try {
      cb()
    } catch {
      /* ignore */
    }
  }
}

/** Fires after each successful GET to `/api/chats/stream` (including reconnects). */
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

/**
 * @param {string} block
 */
function dispatchSseBlock(block) {
  const dataLines = []
  for (const line of block.split('\n')) {
    if (line.startsWith('data:')) {
      dataLines.push(line.slice(5).trimStart())
    }
  }
  if (dataLines.length === 0) {
    return
  }
  const json = dataLines.join('\n')
  let payload
  try {
    payload = JSON.parse(json)
  } catch {
    return
  }
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

/**
 * @param {string} buffer
 * @returns {string}
 */
function consumeCompleteBlocks(buffer) {
  const parts = buffer.split('\n\n')
  const tail = parts.pop() ?? ''
  for (const block of parts) {
    if (block.length > 0) {
      dispatchSseBlock(block)
    }
  }
  return tail
}

function sleep(ms) {
  return new Promise((r) => setTimeout(r, ms))
}

function withBackoffJitter(ms) {
  const jitterFactor = 0.75 + (Math.random() * 0.5)
  return Math.max(250, Math.round(ms * jitterFactor))
}

async function readOneSession(signal) {
  if (!csrfReady) {
    await ensureCsrfCookie()
    csrfReady = true
  }
  const res = await fetch(`${apiBaseUrl()}/api/chats/stream`, {
    method: 'GET',
    credentials: 'include',
    headers: chatSseHeaders(),
    signal,
  })
  if (!res.ok) {
    if (res.status === 401 || res.status === 419) {
      csrfReady = false
    }
    throw new Error(`sse_http_${res.status}`)
  }
  notifyHttpOk()
  const reader = res.body?.getReader()
  if (!reader) {
    throw new Error('sse_no_body')
  }
  const decoder = new TextDecoder()
  let buffer = ''
  while (!signal.aborted) {
    const { done, value } = await reader.read()
    if (done) {
      break
    }
    buffer += decoder.decode(value, { stream: true })
    buffer = consumeCompleteBlocks(buffer)
  }
}

async function runLoop() {
  loopRunning = true
  let backoffMs = 1000
  const maxBackoffMs = 30_000
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
        backoffMs = Math.min(maxBackoffMs, backoffMs * 2)
        await sleep(delay)
        continue
      }
      if (handlerCount() > 0) {
        await sleep(500)
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
 * @param {import('vue').Ref<string|number|null|undefined>} chatIdRef
 * @param {(message: unknown) => void} onMessage
 */
export function useChatSseForThread(chatIdRef, onMessage) {
  const connected = ref(false)
  let unregister = null
  let offHttpOk = null

  function attach(chatId) {
    offHttpOk?.()
    unregister?.()
    offHttpOk = null
    unregister = null
    connected.value = false
    if (!chatId) {
      return
    }
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
    offHttpOk?.()
    offHttpOk = null
    unregister?.()
    unregister = null
    connected.value = false
  })

  return { connected }
}
