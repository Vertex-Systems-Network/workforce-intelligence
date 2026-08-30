import { build } from 'esbuild'
import { copyFileSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { spawnSync } from 'node:child_process'

const here = dirname(fileURLToPath(import.meta.url))
const agent = resolve(here, '../native-agent.mjs')
const out = resolve(here, 'dist')
mkdirSync(out, { recursive: true })

// Node 22 SEA executes one injected CommonJS script. The native agent is an ESM
// entrypoint that intentionally uses import.meta.url and top-level await, so the
// standalone builder applies only the two runtime-shape adaptations required by
// SEA before esbuild bundles it as CommonJS. The source agent itself is unchanged.
const source = readFileSync(agent, 'utf8')
const rootNeedle = "const root = dirname(new URL(import.meta.url).pathname.replace(/^\\/(.:)/, '$1'))"
const rootReplacement = 'const root = dirname(process.execPath)'
const commandNeedle = 'const [, , command, a, b]=process.argv\ntry{'

if (!source.includes(rootNeedle)) {
  throw new Error('Standalone transform contract drift: agent root expression changed.')
}
if (!source.includes(commandNeedle)) {
  throw new Error('Standalone transform contract drift: agent command dispatcher changed.')
}

const withSeaRoot = source.replace(rootNeedle, rootReplacement)
const commandIndex = withSeaRoot.indexOf(commandNeedle)
const standaloneSource = `${withSeaRoot.slice(0, commandIndex)}async function main(){\n${withSeaRoot.slice(commandIndex)}\n}\nvoid main()\n`

const bundle = resolve(out, 'agent.cjs')
await build({
  stdin: {
    contents: standaloneSource,
    resolveDir: dirname(agent),
    sourcefile: agent,
    loader: 'js',
  },
  bundle: true,
  platform: 'node',
  format: 'cjs',
  target: 'node22',
  outfile: bundle,
  banner: { js: '/* WorkIntel Agent standalone bundle */' },
})

const blob = resolve(out, 'sea-prep.blob')
writeFileSync(
  resolve(out, 'sea-config.json'),
  JSON.stringify(
    {
      main: bundle,
      output: blob,
      disableExperimentalSEAWarning: true,
      useSnapshot: false,
      useCodeCache: false,
    },
    null,
    2,
  ),
)

/** Runs one external build command and fails closed on any non-zero exit. */
function run(cmd, args) {
  const result = spawnSync(cmd, args, {
    stdio: 'inherit',
    shell: process.platform === 'win32',
  })
  if (result.status !== 0) process.exit(result.status ?? 1)
}

run(process.execPath, ['--experimental-sea-config', resolve(out, 'sea-config.json')])

const executable = resolve(out, process.platform === 'win32' ? 'WorkIntelAgent.exe' : 'WorkIntelAgent')
copyFileSync(process.execPath, executable)

if (process.platform === 'darwin') run('codesign', ['--remove-signature', executable])

const postject = process.platform === 'win32'
  ? resolve(here, 'node_modules/.bin/postject.cmd')
  : resolve(here, 'node_modules/.bin/postject')
const args = [
  executable,
  'NODE_SEA_BLOB',
  blob,
  '--sentinel-fuse',
  'NODE_SEA_FUSE_fce680ab2cc467b6e072b8b5df1996b2',
]
if (process.platform === 'darwin') args.push('--macho-segment-name', 'NODE_SEA')
run(postject, args)

if (process.platform === 'darwin') run('codesign', ['--sign', '-', executable])

console.log(`Standalone agent: ${executable}`)
