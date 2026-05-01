import test from 'node:test'
import assert from 'node:assert/strict'

const originalFetch = globalThis.fetch
const originalWindow = globalThis.window
const originalDocument = globalThis.document

test('communityApi communities endpoints use expected urls', async (t) => {
  globalThis.document = { cookie: '' }
  globalThis.window = { location: { hostname: 'localhost' } }

  const calls = []
  globalThis.fetch = async (url, opts = {}) => {
    calls.push({ url: String(url), method: opts.method ?? 'GET' })
    return {
      ok: true,
      status: 200,
      async text() {
        return '{"data":[]}'
      },
    }
  }

  const { fetchCommunities, createCommunity, patchCommunity } = await import('../src/services/communityApi.js')

  await fetchCommunities()
  await createCommunity({ name: 'Northside' })
  await patchCommunity(7, { name: 'Northside Updated' })

  assert.ok(calls.some((c) => c.url.endsWith('/api/communities') && c.method === 'GET'))
  assert.ok(calls.some((c) => c.url.endsWith('/api/communities') && c.method === 'POST'))
  assert.ok(calls.some((c) => c.url.endsWith('/api/communities/7') && c.method === 'PATCH'))

  t.after(() => {
    globalThis.fetch = originalFetch
    globalThis.window = originalWindow
    globalThis.document = originalDocument
  })
})
