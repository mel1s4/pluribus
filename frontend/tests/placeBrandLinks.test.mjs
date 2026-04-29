import test from 'node:test'
import assert from 'node:assert/strict'

test('normalizeBrandLinks keeps only complete and valid-ish rows', async () => {
  const { normalizeBrandLinks } = await import('../src/utils/placeBrandLinks.js')

  const normalized = normalizeBrandLinks([
    { title: ' Instagram ', url: ' https://instagram.com/acme ', icon: 'instagram' },
    { title: '', url: 'https://example.com', icon: 'website' },
    { title: 'No URL', url: '', icon: 'website' },
  ])

  assert.equal(normalized.length, 1)
  assert.deepEqual(normalized[0], {
    title: 'Instagram',
    url: 'https://instagram.com/acme',
    icon: 'instagram',
  })
})
