const marker = '```newsletter-block'

export function encodeBlock(data) {
  return `${marker}\n${JSON.stringify(data)}\n\`\`\``
}

export function decodeBlock(markdown) {
  const match = markdown.trim().match(/^```newsletter-block\s*\n([\s\S]*?)\n```$/)
  if (!match) throw new Error('Invalid newsletter block')
  const data = JSON.parse(match[1])
  if (!data || ['provider', 'reference', 'label', 'markdown'].some((key) => typeof data[key] !== 'string')) {
    throw new Error('Invalid newsletter block')
  }
  return data
}
