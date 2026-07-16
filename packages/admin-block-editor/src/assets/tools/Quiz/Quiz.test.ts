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

/**
 * The profile-mode editor points answers at profile *cards*, not at typed keys:
 * a chip per declared profile, cycling 1 → 2 → 3 → off. These cover what the old
 * free-text "explorer:2, builder" field could not guarantee — that a weight can
 * never name a profile that does not exist, and never drifts when a key is renamed.
 */
function chipsOf(tool: Quiz, question = 0, answer = 0): HTMLButtonElement[] {
  const el = document.body.querySelector('.cdx-quiz') as HTMLElement
  const aEl = el.querySelectorAll('.cdx-quiz__q')[question]!.querySelectorAll('.cdx-quiz__a')[answer]!
  return [...aEl.querySelectorAll('.cdx-quiz__chip')] as HTMLButtonElement[]
}

function mountedInDom(data: QuizData): Quiz {
  document.body.textContent = ''
  const tool = new Quiz({ data, api, readOnly: false })
  document.body.appendChild(tool.render())
  return tool
}

describe('Quiz personality chips', () => {
  it('renders one chip per declared profile, labelled by title', () => {
    const tool = mountedInDom(personality)
    const chips = chipsOf(tool)

    expect(chips).toHaveLength(2)
    expect(chips[0]!.dataset.label).toBe('The Summiteer')
    expect(chips[1]!.dataset.label).toBe('The Contemplative')
    // Weight 2 shows its count; the unweighted profile stays off.
    expect(chips[0]!.textContent).toBe('The Summiteer ×2')
    expect(chips[0]!.classList.contains('is-on')).toBe(true)
    expect(chips[1]!.classList.contains('is-on')).toBe(false)
  })

  it('cycles a chip off → 1 → 2 → 3 → off', () => {
    const tool = mountedInDom(personality)
    const chip = chipsOf(tool)[1]! // 'calm', currently off

    chip.click()
    expect(tool.save().questions![0]!.answers![0]!.weights).toEqual({ sommet: 2, calm: 1 })
    chip.click()
    expect(tool.save().questions![0]!.answers![0]!.weights).toEqual({ sommet: 2, calm: 2 })
    chip.click()
    expect(tool.save().questions![0]!.answers![0]!.weights).toEqual({ sommet: 2, calm: 3 })
    chip.click() // past ×3 → cleared
    expect(tool.save().questions![0]!.answers![0]!.weights).toEqual({ sommet: 2 })
  })

  it('keeps a heavier hand-authored weight instead of clamping it to 3', () => {
    const tool = mountedInDom({
      mode: 'profile',
      profiles: [{ key: 'sommet', title: 'The Summiteer' }],
      questions: [{ q: 'Q', answers: [{ a: 'A', weights: { sommet: 5 } }] }],
    })

    const chip = chipsOf(tool)[0]!
    expect(chip.textContent).toBe('The Summiteer ×5')
    expect(chip.classList.contains('is-heavy')).toBe(true)
    // Untouched, it survives the round-trip verbatim.
    expect(tool.save().questions![0]!.answers![0]!.weights).toEqual({ sommet: 5 })
  })

  it('follows a renamed profile key across every weight', () => {
    const tool = mountedInDom(personality)
    const key = document.body.querySelector('.cdx-quiz__profile-key') as HTMLInputElement
    expect(key.value).toBe('sommet')

    key.value = 'peak'
    key.dispatchEvent(new Event('input'))

    // The answer never named the key — it points at the card, so it just follows.
    expect(tool.save().questions![0]!.answers![0]!.weights).toEqual({ peak: 2 })
    expect(tool.save().profiles![0]!.key).toBe('peak')
  })

  it('drops the weights of a deleted profile rather than orphaning them', () => {
    const tool = mountedInDom(personality)
    const del = document.body
      .querySelector('.cdx-quiz__profile')!
      .querySelector('.cdx-quiz__del') as HTMLButtonElement
    del.click()

    expect(tool.save().profiles).toHaveLength(1)
    expect(tool.save().questions![0]!.answers![0]!.weights).toBeUndefined()
  })

  it('reports the tally per profile and flags an unreachable one', () => {
    mountedInDom(personality)
    const balances = [...document.body.querySelectorAll('.cdx-quiz__balance')] as HTMLElement[]

    expect(balances[0]!.textContent).toBe('1 answer · 2 points')
    expect(balances[0]!.classList.contains('is-warn')).toBe(false)
    // 'calm' is reached through the `profile` shorthand on the second answer.
    expect(balances[1]!.textContent).toBe('1 answer · 1 point')
  })

  it('warns in words, not only in colour, when a profile is unreachable', () => {
    mountedInDom({
      ...personality,
      profiles: [...personality.profiles!, { key: 'lost', title: 'The Unreachable' }],
    })
    const balances = [...document.body.querySelectorAll('.cdx-quiz__balance')] as HTMLElement[]

    expect(balances[2]!.textContent).toBe('No answer leads here')
    expect(balances[2]!.classList.contains('is-warn')).toBe(true)
  })

  it('flags a question that weighs nothing, in words', () => {
    const tool = mountedInDom({
      mode: 'profile',
      profiles: [{ key: 'sommet', title: 'The Summiteer' }],
      questions: [{ q: 'Weighs nothing', answers: [{ a: 'A' }, { a: 'B' }] }],
    })
    const q = document.body.querySelector('.cdx-quiz__q') as HTMLElement

    expect(q.classList.contains('is-mute')).toBe(true)
    expect(q.querySelector('.cdx-quiz__q-warn')!.textContent).toBe('No answer weighs a profile')

    // Weighing any profile clears both the wash and the wording.
    chipsOf(tool)[0]!.click()
    expect(q.classList.contains('is-mute')).toBe(false)
    expect(q.querySelector('.cdx-quiz__q-warn')!.textContent).toBe('')
  })

  it('exposes the chip toggle state and its weight to assistive tech', () => {
    const tool = mountedInDom(personality)
    const [main, off] = chipsOf(tool)

    expect(main!.getAttribute('aria-pressed')).toBe('true')
    expect(main!.getAttribute('aria-label')).toBe('The Summiteer, 2 points')
    expect(off!.getAttribute('aria-pressed')).toBe('false')
    expect(off!.getAttribute('aria-label')).toBe('The Contemplative')

    off!.click()
    expect(off!.getAttribute('aria-pressed')).toBe('true')
    expect(off!.getAttribute('aria-label')).toBe('The Contemplative, 1 point')
  })

  it('folds the end form away when empty and opens it when set', () => {
    mountedInDom(personality)
    expect((document.body.querySelector('.cdx-quiz__cta-block') as HTMLDetailsElement).open).toBe(false)

    mountedInDom({ ...personality, cta: 'newsletter' })
    expect((document.body.querySelector('.cdx-quiz__cta-block') as HTMLDetailsElement).open).toBe(true)

    // Folded is not dropped: save() still reads through a closed <details>.
    mountedInDom({ ...personality, ctaTitle: 'Stay in touch' })
    const block = document.body.querySelector('.cdx-quiz__cta-block') as HTMLDetailsElement
    expect(block.open).toBe(true)
    expect(block.contains(document.body.querySelector('.cdx-quiz__cta-title'))).toBe(true)
  })

  it('keeps the end form through a save even while folded shut', () => {
    const tool = mountedInDom(personality)
    ;(document.body.querySelector('.cdx-quiz__cta') as HTMLInputElement).value = 'newsletter'
    ;(document.body.querySelector('.cdx-quiz__cta-title') as HTMLInputElement).value = 'Stay in touch'

    const saved = tool.save()
    expect(saved.cta).toBe('newsletter')
    expect(saved.ctaTitle).toBe('Stay in touch')
  })

  it('suggests the conversation types the site declares, without closing the choice', () => {
    document.body.textContent = ''
    const tool = new Quiz({
      data: { ...personality, cta: 'custom_form' },
      api,
      readOnly: false,
      config: { conversationTypes: ['message', 'ms_message', 'newsletter'] },
    })
    document.body.appendChild(tool.render())

    const input = document.body.querySelector('.cdx-quiz__cta') as HTMLInputElement
    const list = document.getElementById(input.getAttribute('list')!)
    expect([...list!.querySelectorAll('option')].map((o) => o.getAttribute('value')))
      .toEqual(['message', 'ms_message', 'newsletter'])

    // It is an <input list>, not a <select>: a type resolved through conversation's
    // App\Form\{type} fallback is not in the list and must survive untouched.
    expect(input.tagName).toBe('INPUT')
    expect(tool.save().cta).toBe('custom_form')
  })

  it('offers no datalist when the conversation bundle contributes nothing', () => {
    mountedInDom(personality)
    expect(document.body.querySelector('.cdx-quiz__cta')!.getAttribute('list')).toBeNull()
    expect(document.body.querySelector('datalist')).toBeNull()
  })

  it('names every field, which the placeholder alone stops doing once filled', () => {
    mountedInDom(personality)
    const unnamed = [...document.body.querySelectorAll('.cdx-quiz input, .cdx-quiz textarea, .cdx-quiz select')]
      .filter((el) => null === el.getAttribute('aria-label') && 'checkbox' !== (el as HTMLInputElement).type)

    expect(unnamed).toEqual([])
  })

  it('keeps the mode-specific labels a plain quiz never shows', () => {
    const saved = mountedInDom({
      ...personality,
      labels: { profile: 'Votre profil :', share: '{p}% partagent votre profil' },
    }).save()

    expect(saved.labels).toEqual({ profile: 'Votre profil :', share: '{p}% partagent votre profil' })
  })

  it('keeps the levels labels too', () => {
    const saved = mountedInDom({ ...leveled, labels: { level: 'Niveau', nextLevel: 'Niveau suivant' } }).save()

    expect(saved.labels).toEqual({ level: 'Niveau', nextLevel: 'Niveau suivant' })
  })

  it('keeps the alt text of answer and profile images through a round-trip', () => {
    const withAlt: QuizData = {
      mode: 'profile',
      profiles: [{ key: 'sommet', title: 'The Summiteer', media: 'peak.jpg', alt: 'A snowy peak' }],
      questions: [{ q: 'Q', answers: [{ a: 'A', weights: { sommet: 2 }, media: 'a.jpg', alt: 'Climbing' }] }],
    }
    const saved = mountedInDom(withAlt).save()

    expect(saved.profiles![0]!.alt).toBe('A snowy peak')
    expect(saved.questions![0]!.answers![0]!.alt).toBe('Climbing')
  })
})
