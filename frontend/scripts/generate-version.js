#!/usr/bin/env node
/**
 * Generate version.json file with build timestamp
 * This file is used to detect when a new version has been deployed
 */
import { writeFileSync, existsSync, mkdirSync } from 'fs'
import { join, dirname } from 'path'
import { fileURLToPath } from 'url'

const __dirname = dirname(fileURLToPath(import.meta.url))

// Parse arguments: support both `--outDir dist-deploy` and positional `dist-deploy`
let outputDir = join(__dirname, '../dist-deploy')
const args = process.argv.slice(2)

for (let i = 0; i < args.length; i++) {
  if (args[i] === '--outDir' && args[i + 1]) {
    outputDir = join(__dirname, '..', args[i + 1])
    break
  } else if (!args[i].startsWith('--')) {
    outputDir = join(__dirname, '..', args[i])
    break
  }
}

// Ensure output directory exists
if (!existsSync(outputDir)) {
  mkdirSync(outputDir, { recursive: true })
}

const version = {
  buildTime: new Date().toISOString(),
  timestamp: Date.now(),
  version: process.env.npm_package_version || '0.0.0',
}

const outputPath = join(outputDir, 'version.json')
writeFileSync(outputPath, JSON.stringify(version, null, 2))

console.log(`✓ Generated ${outputPath}`)
console.log(`  Build time: ${version.buildTime}`)
