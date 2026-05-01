import test from 'node:test'
import assert from 'node:assert/strict'

test('withCommunityPath prefixes slug when provided', async () => {
  const { withCommunityPath } = await import('../src/utils/communityPath.js')

  assert.equal(withCommunityPath('/communities', 'northside'), '/northside/communities')
  assert.equal(withCommunityPath('dashboard', 'northside'), '/northside/dashboard')
  assert.equal(withCommunityPath('/dashboard', null), '/dashboard')
})
