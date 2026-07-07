import { describe, it, expect } from 'vitest'
import Quiz, { QuizData } from './Quiz'
import { API } from '@editorjs/editorjs'

/**
 * Round-trip tests for the difficulty-levels mode: the editor must render N full
 * sub-quizzes, save them back as a `levels` array (with no root questions), and
 * export/import them through the `{% quiz %}` block without drift. A quiz without
 * levels must keep behaving as a single quiz.
 */

const api = { notifier: { show: () => {} }, i18n: { t: (k: string) => k } } as unknown as API

function mounted(data: QuizData): Quiz {
  const tool = new Quiz({ data, api, readOnly: false })
  tool.render() // build the DOM so save() can read it back
  return tool
}

function parseBlock(markdown: string): QuizData {
  const tag = markdown.match(/\{%\s*quiz\s*%\}([\s\S]*?)\{%\s*endquiz\s*%\}/)
  if (!tag) throw new Error('not a quiz block: ' + markdown)
  return JSON.parse(tag[1] ?? '{}')
}

const leveled: QuizData = {
  title: 'Mountains',
  feedback: 'immediate',
  cta: 'newsletter',
  levels: [
    { difficulty: 'Easy', pass: 50, questions: [{ q: 'Q1', answers: [{ a: 'A', correct: true }, { a: 'B' }] }] },
    { difficulty: 'Hard', questions: [{ q: 'Q2', answers: [{ a: 'C', correct: true }, { a: 'D' }] }] },
  ],
}

describe('Quiz difficulty levels', () => {
  it('exports levels to a quiz block and drops the root questions', () => {
    const json = parseBlock(Quiz.exportToMarkdown(leveled))

    expect(json.title).toBe('Mountains')
    expect(json.cta).toBe('newsletter')
    expect(json.questions).toBeUndefined()
    expect(json.levels).toHaveLength(2)
    expect(json.levels![0].difficulty).toBe('Easy')
    expect(json.levels![0].pass).toBe(50)
    expect(json.levels![0].questions).toHaveLength(1)
  })

  it('renders in levels mode and saves the levels back', () => {
    const saved = mounted(leveled).save()

    expect(saved.levels).toHaveLength(2)
    expect(saved.levels![0].difficulty).toBe('Easy')
    expect(saved.levels![0].pass).toBe(50)
    expect(saved.levels![0].questions![0].answers!.find((a) => a.correct)?.a).toBe('A')
    expect(saved.levels![1].difficulty).toBe('Hard')
    // The root keeps no questions of its own once levels carry them.
    expect(saved.questions).toEqual([])
  })

  it('stays a single quiz when no levels are declared', () => {
    const single: QuizData = { questions: [{ q: 'Solo', answers: [{ a: 'X', correct: true }, { a: 'Y' }] }] }
    const saved = mounted(single).save()

    expect(saved.questions).toHaveLength(1)
    expect(saved.levels).toBeUndefined()
  })

  it('round-trips export → parse → export without drift', () => {
    const markdown = Quiz.exportToMarkdown(leveled)
    expect(Quiz.exportToMarkdown(parseBlock(markdown))).toBe(markdown)
  })

  it('exports the {% quiz %} block with pretty-printed, unescaped JSON', () => {
    const md = Quiz.exportToMarkdown({
      questions: [{ q: "L'eau bout à ?", answers: [{ a: '100°C', correct: true }, { a: '0°C' }] }],
    })

    expect(md.startsWith('{% quiz %}\n')).toBe(true)
    expect(md.endsWith('\n{% endquiz %}')).toBe(true)
    expect(md).toContain('\n  "questions"') // beautified (2-space indent)
    expect(md).toContain("L'eau bout à ?") // apostrophe left raw, not \'-escaped
    expect(md).not.toContain('\\') // no Twig-string escaping at all
    expect(md).not.toMatch(/\n\s*\n/) // blank-line free, so the Markdown split keeps it whole
  })

  it('imports the legacy {{ quiz(\'…\') }} form', () => {
    let inserted: QuizData | undefined
    const importApi = {
      blocks: { insert: (_t: string, d: QuizData) => ({ id: 'x', data: (inserted = d) }), update: () => {} },
    } as unknown as API

    Quiz.importFromMarkdown(importApi, "{{ quiz('{\"questions\":[{\"q\":\"L\\'eau\",\"answers\":[{\"a\":\"A\",\"correct\":true},{\"a\":\"B\"}]}]}') }}")

    expect(inserted?.questions?.[0]?.q).toBe("L'eau")
  })

  it('detects both block forms', () => {
    expect(Quiz.isItMarkdownExported('{% quiz %}{}{% endquiz %}')).toBe(true)
    expect(Quiz.isItMarkdownExported("{{ quiz('{}') }}")).toBe(true)
    expect(Quiz.isItMarkdownExported('just a paragraph')).toBe(false)
  })
})

const personality: QuizData = {
  mode: 'profile',
  title: 'Which explorer are you?',
  profiles: [
    { key: 'sommet', title: 'The Summiteer', msg: 'Higher, always.' },
    { key: 'calm', title: 'The Contemplative' },
  ],
  questions: [
    {
      q: 'A free weekend, you…',
      answers: [
        { a: 'climb a peak', weights: { sommet: 2 } },
        { a: 'walk by a lake', profile: 'calm' },
      ],
    },
  ],
}

describe('Quiz personality test (profile mode)', () => {
  it('exports profiles + weights, forces feedback:end, and drops score bands', () => {
    const json = parseBlock(Quiz.exportToMarkdown(personality))

    expect(json.mode).toBe('profile')
    expect(json.feedback).toBe('end')
    expect(json.results).toBeUndefined()
    expect(json.profiles).toHaveLength(2)
    expect(json.profiles![0].key).toBe('sommet')
    expect(json.questions![0].answers![0].weights).toEqual({ sommet: 2 })
    // The `profile` shorthand is normalised to a {key: 1} weight on export.
    expect(json.questions![0].answers![1].weights).toEqual({ calm: 1 })
    expect(json.questions![0].answers![1].correct).toBeUndefined()
  })

  it('renders in profile mode and saves profiles + weighted answers back', () => {
    const saved = mounted(personality).save()

    expect(saved.mode).toBe('profile')
    expect(saved.profiles).toHaveLength(2)
    expect(saved.profiles![1].key).toBe('calm')
    expect(saved.questions![0].answers![0].weights).toEqual({ sommet: 2 })
    expect(saved.questions![0].answers![0].correct).toBeUndefined()
  })

  it('round-trips export → parse → export without drift', () => {
    const markdown = Quiz.exportToMarkdown(personality)
    expect(Quiz.exportToMarkdown(parseBlock(markdown))).toBe(markdown)
  })
})
