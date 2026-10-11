// Durable progress state survives the branch/PR merge transaction.
// Exact live check, PR and merge status belongs in GitHub issue/PR evidence instead.
const volatilePatterns = [
  ['live pull-request identifier', /\b(?:PR|pull request)\s*#\s*\d+\b/i],
  ['resulting-main check lifecycle', /\b(?:resulting[- ]main|post[- ]merge)\b.{0,36}\b(?:CI|check(?:s)?|certification|verification)\b/i],
  ['in-flight exact-head lifecycle', /\b(?:verify|verifying|certify|certifying|awaiting|pending|under)\b.{0,72}\b(?:exact[- ]head|current\s+(?:PR|pull request))\b/i],
]

export function assertDurableProgressStatus(fields) {
  for (const [name, value] of Object.entries(fields)) {
    if (typeof value !== 'string' || value.trim() === '') {
      throw new Error('durable progress field is missing: ' + name)
    }
    for (const [label, pattern] of volatilePatterns) {
      if (pattern.test(value)) {
        throw new Error('durable progress field ' + name + ' contains ' + label + '; record live transaction status on GitHub instead')
      }
    }
  }
}
