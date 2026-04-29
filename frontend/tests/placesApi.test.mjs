import test from 'node:test'
import assert from 'node:assert/strict'

const originalFetch = globalThis.fetch
const originalWindow = globalThis.window
const originalDocument = globalThis.document
const originalFormData = globalThis.FormData

class MockFormData
{
  constructor() {
    this.entries = []
  }

  append(key, value) {
    this.entries.push([key, value])
  }
}

test('placesApi csv methods use expected endpoints', async (t) => {
  globalThis.FormData = MockFormData
  globalThis.document = { cookie: '' }
  globalThis.window = { location: { hostname: 'localhost' } }

  const calls = []
  globalThis.fetch = async (url, opts = {}) => {
    calls.push({ url: String(url), method: opts.method ?? 'GET' })
    return {
      ok: true,
      status: 200,
      async text() {
        return '{}'
      },
    }
  }

  const { downloadOffersCsvUrl, downloadRequirementsCsvUrl, uploadOffersCsv, uploadRequirementsCsv } =
    await import('../src/services/placesApi.js')

  assert.equal(downloadOffersCsvUrl(5), 'http://localhost:9122/api/places/5/offers/export.csv')
  assert.equal(downloadRequirementsCsvUrl(8), 'http://localhost:9122/api/places/8/requirements/export.csv')

  await uploadOffersCsv(3, { name: 'offers.csv' })
  await uploadRequirementsCsv(4, { name: 'requirements.csv' })

  const postUrls = calls.filter((c) => c.method === 'POST').map((c) => c.url)
  assert.ok(postUrls.includes('http://localhost:9122/api/places/3/offers/import.csv'))
  assert.ok(postUrls.includes('http://localhost:9122/api/places/4/requirements/import.csv'))

  t.after(() => {
    globalThis.fetch = originalFetch
    globalThis.window = originalWindow
    globalThis.document = originalDocument
    globalThis.FormData = originalFormData
  })
})
