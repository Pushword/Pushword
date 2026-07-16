import './Quiz.css'
import make from '../utils/make'
import ToolboxIcon from './toolbox-icon.svg?raw'
import SelectIcon from '../Abstract/icon/folder.svg?raw'
import UploadIcon from '../Abstract/icon/upload.svg?raw'
import { MediaUtils } from '../utils/media'
import { API, BlockToolData } from '@editorjs/editorjs'
import { BaseTool } from '../Abstract/BaseTool'

interface QuizAnswer {
  a?: string
  correct?: boolean
  weights?: Record<string, number>
  profile?: string
  media?: string
  alt?: string
}

interface QuizQuestion {
  q?: string
  media?: string
  video?: string
  alt?: string
  explanation?: string
  answers?: QuizAnswer[]
}

interface QuizResultBand {
  min?: number
  msg?: string
}

interface QuizProfile {
  key?: string
  title?: string
  msg?: string
  media?: string
  alt?: string
}

export interface QuizData extends BlockToolData {
  mode?: string
  title?: string
  difficulty?: string
  label?: string
  pass?: number
  feedback?: string
  cta?: string
  ctaTitle?: string
  numbering?: string
  labels?: Record<string, string>
  results?: QuizResultBand[]
  profiles?: QuizProfile[]
  questions?: QuizQuestion[]
  levels?: QuizData[]
}

/** The three shapes a quiz block can take; `levels`/`profile` are mutually exclusive by construction. */
type QuizMode = 'quiz' | 'levels' | 'profile'

/**
 * Every UI word a quiz may override, with the English default it otherwise falls
 * back to, and the modes it means anything in. All of them get an input: `save()`
 * rebuilds `labels` from these alone, so a key without one would be dropped.
 */
const LABELS: { key: string; placeholder: string; only?: QuizMode[] }[] = [
  { key: 'question', placeholder: 'Question' },
  { key: 'questions', placeholder: 'questions' },
  { key: 'explanation', placeholder: 'Explanation' },
  { key: 'score', placeholder: 'Your score:', only: ['quiz', 'levels'] },
  { key: 'better', placeholder: 'Better than {p}% of participants', only: ['quiz', 'levels'] },
  { key: 'level', placeholder: 'Level', only: ['levels'] },
  { key: 'nextLevel', placeholder: 'Next level', only: ['levels'] },
  { key: 'profile', placeholder: 'Your profile:', only: ['profile'] },
  { key: 'share', placeholder: '{p}% got the same profile', only: ['profile'] },
]

/**
 * Editor for the quiz block: add/remove questions and answers, flag the correct
 * answer(s), attach an image or video, write the explanation. Exports a
 * `{% quiz %}…{% endquiz %}` block with pretty-printed raw JSON, and imports both
 * that and the legacy `{{ quiz('…') }}` form.
 *
 * In `profile` mode the profiles are declared *above* the questions — they are the
 * vocabulary the answers then point at, through chips rather than typed keys.
 */
export default class Quiz extends BaseTool {
  declare public data: QuizData
  private conversationTypes: string[] = []
  private ctaListId = ''
  private wrapper!: HTMLElement
  private singleSection!: HTMLElement
  private levelsSection!: HTMLElement
  private levelsList!: HTMLElement
  private profilesSection!: HTMLElement
  private profilesList!: HTMLElement
  private modeSelect!: HTMLSelectElement
  private mode: QuizMode = 'quiz'
  private pidSeq = 0
  private mediaPickerMessageHandler: ((event: MessageEvent) => void) | null = null

  /**
   * A chip cycles through 1…W_MAX points. Heavier weights stay reachable from
   * hand-written JSON for the odd answer that must count double: they render as
   * their own `×N` chip and survive an edit untouched.
   */
  private static readonly W_MAX = 3

  /** Each block needs its own datalist to point at. */
  private static listSeq = 0

  private get profileMode(): boolean {
    return 'profile' === this.mode
  }

  private get levelsMode(): boolean {
    return 'levels' === this.mode
  }

  public static toolbox = {
    title: 'Quiz',
    icon: ToolboxIcon,
  }

  constructor({
    data,
    api,
    readOnly,
    config,
  }: { data: QuizData; api: API; readOnly: boolean; config?: { conversationTypes?: string[] } }) {
    super({ data, api, readOnly })

    // Contributed by the quiz bundle's editor tool provider (the form types this
    // site actually declares). Absent when the conversation bundle is not installed.
    this.conversationTypes = Array.isArray(config?.conversationTypes) ? config.conversationTypes : []
    this.ctaListId = 'cdx-quiz-cta-' + String(++Quiz.listSeq)

    const questions =
      Array.isArray(data.questions) && data.questions.length > 0
        ? data.questions
        : [{ q: '', answers: [{ a: '', correct: true }, { a: '' }] }]

    this.data = {
      mode: 'profile' === data.mode ? 'profile' : 'quiz',
      title: data.title || '',
      difficulty: data.difficulty || '',
      feedback: data.feedback || 'immediate',
      cta: data.cta || '',
      ctaTitle: data.ctaTitle || '',
      numbering: data.numbering || '',
      labels: data.labels || {},
      results: Array.isArray(data.results) ? data.results : [],
      profiles: Array.isArray(data.profiles) ? data.profiles : [],
      questions,
      levels: Array.isArray(data.levels) ? data.levels : [],
    }
    this.mode = 'profile' === this.data.mode ? 'profile' : ((this.data.levels || []).length > 0 ? 'levels' : 'quiz')
  }

  public render(): HTMLElement {
    this.wrapper = make.element('div', 'cdx-quiz')
    this.wrapper.appendChild(
      make.element('div', 'cdx-quiz__header', {}, ToolboxIcon + '<span>Quiz</span>'),
    )

    const meta = make.element('div', 'cdx-quiz__meta')
    meta.appendChild(this.inputEl('cdx-quiz__title', 'Title', this.data.title || ''))
    const feedback = make.element('select', ['cdx-quiz__feedback'], {
      'aria-label': 'When to reveal the answer',
    }) as HTMLSelectElement
    make.option(feedback, 'immediate', 'immediate')
    make.option(feedback, 'end', 'end')
    feedback.value = this.data.feedback || 'immediate'
    meta.appendChild(feedback)
    const numbering = make.element('select', ['cdx-quiz__numbering'], {
      'aria-label': 'Answer numbering',
    }) as HTMLSelectElement
    make.option(numbering, '', 'No numbering')
    make.option(numbering, 'A', 'A, B, C…')
    make.option(numbering, 'a', 'a, b, c…')
    make.option(numbering, '1', '1, 2, 3…')
    numbering.value = this.data.numbering || ''
    meta.appendChild(numbering)
    this.wrapper.appendChild(meta)

    // One shape at a time: a plain quiz, difficulty tabs, or a personality test.
    const modeWrap = make.element('div', 'cdx-quiz__mode')
    modeWrap.appendChild(make.element('span', 'cdx-quiz__mode-label', {}, 'Type'))
    this.modeSelect = make.element('select', ['cdx-quiz__input', 'cdx-quiz__mode-select'], {
      'aria-label': 'Quiz type',
    }) as HTMLSelectElement
    make.option(this.modeSelect, 'quiz', 'Knowledge quiz')
    make.option(this.modeSelect, 'levels', 'Knowledge quiz with difficulty levels (tabs)')
    make.option(this.modeSelect, 'profile', 'Personality test (answers weigh profiles)')
    this.modeSelect.value = this.mode
    this.modeSelect.addEventListener('change', () => {
      this.mode = this.modeSelect.value as QuizMode
      if (this.levelsMode && 0 === this.levelsList.children.length) {
        this.levelsList.appendChild(this.buildLevel({}))
      }

      // A personality test is meaningless below two outcomes: seed the pair.
      if (this.profileMode) {
        while (this.profilesList.children.length < 2) this.profilesList.appendChild(this.buildProfile({}))
      }

      this.applyMode()
    })
    modeWrap.appendChild(this.modeSelect)
    this.wrapper.appendChild(modeWrap)

    // Personality-test outcomes. Declared *before* the questions on purpose: they
    // are the vocabulary the answers below then point at, so they must exist first.
    this.profilesSection = this.buildProfilesBlock(this.data.profiles || [])
    this.wrapper.appendChild(this.profilesSection)

    // Single-quiz editor (difficulty + questions + score bands). Its questions are
    // reused by the personality test — only their answers read weights, not a flag.
    this.singleSection = make.element('div', 'cdx-quiz__single')
    this.singleSection.appendChild(this.inputEl('cdx-quiz__difficulty', 'Difficulty', this.data.difficulty || ''))
    this.singleSection.appendChild(this.buildQuestionsBlock(this.data.questions || []))
    this.singleSection.appendChild(this.buildResultsBlock(this.data.results || []))
    this.wrapper.appendChild(this.singleSection)

    // Multi-level editor: one full sub-quiz per level.
    this.levelsSection = make.element('div', 'cdx-quiz__levels')
    this.levelsList = make.element('div', 'cdx-quiz__levels-list')
    ;(this.data.levels || []).forEach((level) => this.levelsList.appendChild(this.buildLevel(level)))
    this.levelsSection.appendChild(this.levelsList)
    this.levelsSection.appendChild(
      make.element('button', 'cdx-quiz__add', { type: 'button' }, '+ Level', () => {
        this.levelsList.appendChild(this.buildLevel({}))
      }),
    )
    this.wrapper.appendChild(this.levelsSection)

    // The end form renders after the last question, so its config sits after the
    // questions too. Folded away until it holds something.
    this.wrapper.appendChild(this.buildCtaBlock())

    // Author-defined UI words (no i18n). Empty = English default (the placeholder).
    this.wrapper.appendChild(
      make.element('div', 'cdx-quiz__subtitle', {}, 'Labels (optional — override the default words)'),
    )
    const labels = make.element('div', 'cdx-quiz__labels')
    LABELS.forEach((label) => {
      const input = this.inputEl('cdx-quiz__label', label.placeholder, (this.data.labels || {})[label.key] || '')
      input.dataset.label = label.key
      // A label the current mode never shows stays in the DOM (and so keeps its
      // value through a save) but is hidden — see applyMode().
      if (undefined !== label.only) input.dataset.only = label.only.join(' ')
      labels.appendChild(input)
    })
    this.wrapper.appendChild(labels)

    this.applyMode()

    return this.wrapper
  }

  /**
   * The optional lead form shown once the quiz is over: which conversation form to
   * serve, and the heading above it. Folded shut until it carries something, since
   * most quizzes never set one and it was crowding the top of the block.
   */
  private buildCtaBlock(): HTMLElement {
    const cta = this.data.cta || ''
    const ctaTitle = this.data.ctaTitle || ''

    const block = make.element('details', 'cdx-quiz__cta-block') as HTMLDetailsElement
    block.open = '' !== cta || '' !== ctaTitle
    block.appendChild(make.element('summary', 'cdx-quiz__cta-summary', {}, 'End form (optional)'))

    const input = this.inputEl('cdx-quiz__cta', 'Conversation form type', cta)
    block.appendChild(input)
    block.appendChild(
      this.inputEl('cdx-quiz__cta-title', 'Heading above the form (call to action)', ctaTitle),
    )

    // Suggestions, never a closed list: a type the site does not declare still
    // resolves through conversation's `App\Form\{type}` fallback, so a <select>
    // would silently rewrite a working custom type into something else.
    if (this.conversationTypes.length > 0) {
      input.setAttribute('list', this.ctaListId)
      const list = make.element('datalist', null, { id: this.ctaListId })
      this.conversationTypes.forEach((type) => list.appendChild(make.element('option', null, { value: type })))
      block.appendChild(list)
    }

    return block
  }

  /** Reflect the active mode (single quiz / difficulty levels / personality test). */
  private applyMode(): void {
    // A CSS class swaps the per-answer control (correct flag <-> profile chips)
    // and hides the score bands / difficulty in personality mode.
    this.wrapper.classList.toggle('cdx-quiz--profile', this.profileMode)
    // The questions live in the single section, reused by the personality test.
    this.singleSection.hidden = this.levelsMode
    this.levelsSection.hidden = !this.levelsMode
    this.profilesSection.hidden = !this.profileMode
    // Only offer the words the active mode actually renders.
    this.wrapper.querySelectorAll('.cdx-quiz__label').forEach((el) => {
      const only = (el as HTMLElement).dataset.only
      ;(el as HTMLElement).hidden = undefined !== only && !only.split(' ').includes(this.mode)
    })
    if (this.profileMode) this.refreshChips()
  }

  /** A profiles list plus its "+ Profile" button (personality-test outcomes). */
  private buildProfilesBlock(profiles: QuizProfile[]): HTMLElement {
    const block = make.element('div', 'cdx-quiz__profiles-block')
    block.appendChild(make.element('div', 'cdx-quiz__subtitle', {}, 'Profiles (personality results)'))
    this.profilesList = make.element('div', 'cdx-quiz__profiles')
    profiles.forEach((p) => this.profilesList.appendChild(this.buildProfile(p)))
    block.appendChild(this.profilesList)
    block.appendChild(
      make.element('button', 'cdx-quiz__add', { type: 'button' }, '+ Profile', () => {
        this.profilesList.appendChild(this.buildProfile({}))
        this.refreshChips()
      }),
    )
    return block
  }

  /**
   * One personality outcome: its key, title, description, image. Answers never
   * name the key again — they point at this card through `pid`, so renaming the
   * key can no longer orphan a weight.
   */
  private buildProfile(p: QuizProfile): HTMLElement {
    const el = make.element('div', 'cdx-quiz__profile')
    el.dataset.pid = 'p' + String(++this.pidSeq)

    const head = make.element('div', 'cdx-quiz__level-head')
    head.appendChild(make.element('span', null, {}, 'Profile'))
    head.appendChild(make.element('span', 'cdx-quiz__balance'))
    head.appendChild(
      make.element('button', 'cdx-quiz__del', { type: 'button', title: 'Remove profile' }, '✕', () => {
        el.remove()
        this.refreshChips()
      }),
    )
    el.appendChild(head)

    const row = make.element('div', 'cdx-quiz__row')
    // The key stays author-chosen: it is the concept ("naxos"), while the title is
    // the sentence shown to the reader ("Votre île, c'est Naxos") — not a slug of it.
    const key = this.inputEl('cdx-quiz__profile-key', 'Key (short handle, e.g. naxos)', p.key || '')
    const title = this.inputEl('cdx-quiz__profile-title', 'Title (shown on the result card)', p.title || '')
    key.addEventListener('input', () => this.refreshChips())
    title.addEventListener('input', () => this.refreshChips())
    row.appendChild(key)
    row.appendChild(title)
    el.appendChild(row)

    el.appendChild(this.textareaEl('cdx-quiz__profile-msg', 'Description (shown on the result card)', p.msg || ''))
    el.appendChild(
      this.buildMediaWithAlt(
        'cdx-quiz__profile-media',
        'Result image (optional)',
        p.media || '',
        'cdx-quiz__profile-alt',
        p.alt || '',
      ),
    )

    return el
  }

  /** The profiles currently declared, in declaration order (ties resolve to the first). */
  private profileHeads(): { pid: string; key: string; label: string }[] {
    const heads: { pid: string; key: string; label: string }[] = []
    this.profilesList?.querySelectorAll('.cdx-quiz__profile').forEach((pEl) => {
      const pid = (pEl as HTMLElement).dataset.pid || ''
      const key = Quiz.val('.cdx-quiz__profile-key', pEl)
      const title = Quiz.val('.cdx-quiz__profile-title', pEl)
      if (pid) heads.push({ pid, key, label: title || key || 'Untitled profile' })
    })
    return heads
  }

  /** A questions list plus its "+ Question" button. Reused per level. */
  private buildQuestionsBlock(questions: QuizQuestion[]): HTMLElement {
    const block = make.element('div', 'cdx-quiz__questions-block')
    const list = make.element('div', 'cdx-quiz__questions')
    questions.forEach((q) => list.appendChild(this.buildQuestion(q)))
    block.appendChild(list)
    block.appendChild(
      make.element('button', 'cdx-quiz__add', { type: 'button' }, '+ Question', () => {
        list.appendChild(this.buildQuestion(this.blankQuestion()))
        this.refreshBalance()
      }),
    )
    return block
  }

  /**
   * A personality test's natural shape is one answer per profile, each designating
   * its own — seed that instead of the quiz's two blank propositions.
   */
  private blankQuestion(): QuizQuestion {
    if (!this.profileMode) return { q: '', answers: [{ a: '', correct: true }, { a: '' }] }

    const keys = this.profileHeads().map((head) => head.key).filter((key) => '' !== key)
    if (0 === keys.length) return { q: '', answers: [{ a: '' }, { a: '' }] }

    return { q: '', answers: keys.map((key) => ({ a: '', weights: { [key]: 1 } })) }
  }

  /** A score-bands list plus its "+ Band" button. Reused per level. */
  private buildResultsBlock(results: QuizResultBand[]): HTMLElement {
    const block = make.element('div', 'cdx-quiz__results-block')
    block.appendChild(make.element('div', 'cdx-quiz__subtitle', {}, 'Score bands (optional)'))
    const list = make.element('div', 'cdx-quiz__results')
    results.forEach((r) => list.appendChild(this.buildResult(r)))
    block.appendChild(list)
    block.appendChild(
      make.element('button', 'cdx-quiz__add', { type: 'button' }, '+ Band', () => {
        list.appendChild(this.buildResult({ min: 0, msg: '' }))
      }),
    )
    return block
  }

  /** One difficulty level: its label/difficulty/pass plus a full sub-quiz. */
  private buildLevel(level: QuizData): HTMLElement {
    const el = make.element('div', 'cdx-quiz__level')

    const head = make.element('div', 'cdx-quiz__level-head')
    head.appendChild(make.element('span', null, {}, 'Level'))
    head.appendChild(
      make.element('button', 'cdx-quiz__del', { type: 'button', title: 'Remove level' }, '✕', () => el.remove()),
    )
    el.appendChild(head)

    const row = make.element('div', 'cdx-quiz__row')
    row.appendChild(this.inputEl('cdx-quiz__level-label', 'Tab label (defaults to difficulty)', level.label || ''))
    row.appendChild(this.inputEl('cdx-quiz__level-difficulty', 'Difficulty', level.difficulty || ''))
    const pass = make.element('input', ['cdx-quiz__input', 'cdx-quiz__level-pass'], {
      type: 'number',
      min: '0',
      max: '100',
      placeholder: 'Pass % (default 50)',
    }) as HTMLInputElement
    if (level.pass !== undefined && level.pass !== null) pass.value = String(level.pass)
    row.appendChild(pass)
    el.appendChild(row)

    el.appendChild(this.buildQuestionsBlock(level.questions || []))
    el.appendChild(this.buildResultsBlock(level.results || []))

    return el
  }

  private buildQuestion(q: QuizQuestion): HTMLElement {
    const el = make.element('div', 'cdx-quiz__q')

    const head = make.element('div', 'cdx-quiz__q-head')
    head.appendChild(make.element('span', null, {}, 'Question'))
    // Says out loud what the amber wash means, so the flag does not rely on colour.
    head.appendChild(make.element('span', 'cdx-quiz__q-warn'))
    head.appendChild(
      make.element('button', 'cdx-quiz__del', { type: 'button', title: 'Remove question' }, '✕', () => {
        el.remove()
        this.refreshBalance()
      }),
    )
    el.appendChild(head)

    el.appendChild(this.textareaEl('cdx-quiz__q-text', 'Question text', q.q || ''))

    el.appendChild(
      this.buildMediaField('cdx-quiz__q-media', 'Image filename — also the video poster', q.media || ''),
    )

    const row = make.element('div', 'cdx-quiz__row')
    row.appendChild(this.inputEl('cdx-quiz__q-video', 'Video URL (optional)', q.video || ''))
    row.appendChild(this.inputEl('cdx-quiz__q-alt', 'Media alt text (required for video)', q.alt || ''))
    el.appendChild(row)

    const answers = make.element('div', 'cdx-quiz__answers')
    ;(q.answers || []).forEach((a) => answers.appendChild(this.buildAnswer(a)))
    el.appendChild(answers)
    el.appendChild(
      make.element('button', 'cdx-quiz__add-a', { type: 'button' }, '+ Answer', () => {
        answers.appendChild(this.buildAnswer({ a: '' }))
      }),
    )

    el.appendChild(
      this.textareaEl('cdx-quiz__q-explanation', 'Explanation (shown after answering)', q.explanation || ''),
    )

    return el
  }

  private buildAnswer(a: QuizAnswer): HTMLElement {
    const el = make.element('div', 'cdx-quiz__a')

    const main = make.element('div', 'cdx-quiz__a-main')
    const correct = make.element('input', 'cdx-quiz__a-correct', {
      type: 'checkbox',
      title: 'Mark as a correct answer',
    }) as HTMLInputElement
    correct.checked = !!a.correct
    main.appendChild(correct)
    main.appendChild(this.inputEl('cdx-quiz__a-text', 'Answer', a.a || ''))
    main.appendChild(
      make.element('button', 'cdx-quiz__del', { type: 'button', title: 'Remove answer' }, '✕', () => {
        el.remove()
        this.refreshBalance()
      }),
    )
    el.appendChild(main)

    // Personality mode only (a CSS class shows it in place of the correct flag):
    // one chip per declared profile. Weights arrive keyed by profile key (from the
    // JSON) and are re-keyed to the live cards, so an unknown key cannot survive.
    const chips = make.element('div', 'cdx-quiz__chips')
    const byKey: Record<string, number> = { ...(a.weights || {}) }
    if (a.profile && !(a.profile in byKey)) byKey[a.profile] = 1
    const byPid: Record<string, number> = {}
    this.profileHeads().forEach((head) => {
      const weight = byKey[head.key]
      if ('' !== head.key && undefined !== weight) byPid[head.pid] = weight
    })
    this.renderChips(chips, byPid)
    el.appendChild(chips)

    el.appendChild(
      this.buildMediaWithAlt('cdx-quiz__a-media', 'Answer image (optional)', a.media || '', 'cdx-quiz__a-alt', a.alt || ''),
    )

    return el
  }

  /** Paint a chip from its `data-w`: off, or N points (the count shows past 1). */
  private static paintChip(chip: HTMLElement): void {
    const weight = Number(chip.dataset.w) || 0
    const label = chip.dataset.label || ''
    chip.classList.toggle('is-on', weight > 0)
    chip.classList.toggle('is-heavy', weight > Quiz.W_MAX)
    chip.textContent = weight > 1 ? `${label} ×${weight}` : label
    // A toggle whose state is only painted is invisible to assistive tech, and the
    // `×2` glyph does not read as "2 points" — spell both out.
    chip.setAttribute('aria-pressed', String(weight > 0))
    chip.setAttribute('aria-label', 0 === weight ? label : `${label}, ${Quiz.plural(weight, 'point')}`)
  }

  /** off → 1 → 2 → 3 → off. A hand-authored heavier weight drops straight back to off. */
  private static nextWeight(current: number): number {
    if (current <= 0) return 1
    if (current >= Quiz.W_MAX) return 0

    return current + 1
  }

  /** Read one answer's chips into a `{pid: points}` map. */
  private static readChips(scope: Element): Record<string, number> {
    const weights: Record<string, number> = {}
    scope.querySelectorAll('.cdx-quiz__chip').forEach((chip) => {
      const el = chip as HTMLElement
      const weight = Number(el.dataset.w) || 0
      const pid = el.dataset.pid || ''
      if (pid && weight) weights[pid] = weight
    })
    return weights
  }

  /** Render one chip per declared profile, carrying over the weights already set. */
  private renderChips(container: HTMLElement, weights: Record<string, number>): void {
    container.textContent = ''
    const heads = this.profileHeads()
    if (0 === heads.length) {
      container.appendChild(make.element('span', 'cdx-quiz__chips-empty', {}, 'Declare a profile above first'))
      return
    }

    heads.forEach((head) => {
      const chip = make.element('button', 'cdx-quiz__chip', {
        type: 'button',
        title: 'Click to add a point, again to raise it, past ×' + String(Quiz.W_MAX) + ' to clear it',
      }) as HTMLButtonElement
      chip.dataset.pid = head.pid
      chip.dataset.label = head.label
      chip.dataset.w = String(weights[head.pid] ?? 0)
      chip.addEventListener('click', () => {
        chip.dataset.w = String(Quiz.nextWeight(Number(chip.dataset.w) || 0))
        Quiz.paintChip(chip)
        this.refreshBalance()
      })
      Quiz.paintChip(chip)
      container.appendChild(chip)
    })
  }

  /** Re-render every answer's chips against the current profiles, keeping the weights set. */
  private refreshChips(): void {
    this.singleSection?.querySelectorAll('.cdx-quiz__a').forEach((aEl) => {
      const box = aEl.querySelector('.cdx-quiz__chips') as HTMLElement | null
      if (box) this.renderChips(box, Quiz.readChips(aEl))
    })
    this.refreshBalance()
  }

  /**
   * Live tally per profile, and a flag on any question that votes for nobody — the
   * two authoring slips a personality test otherwise only reveals when played.
   */
  private refreshBalance(): void {
    if (!this.profileMode || !this.singleSection) return

    const tally: Record<string, { answers: number; points: number }> = {}
    this.singleSection.querySelectorAll('.cdx-quiz__q').forEach((qEl) => {
      let voted = false
      // Per answer, not per question: two answers weighing the same profile must
      // count twice, and a question-wide read would collapse them into one.
      qEl.querySelectorAll('.cdx-quiz__a').forEach((aEl) => {
        Object.entries(Quiz.readChips(aEl)).forEach(([pid, weight]) => {
          voted = true
          tally[pid] = tally[pid] ?? { answers: 0, points: 0 }
          tally[pid].answers++
          tally[pid].points += weight
        })
      })
      qEl.classList.toggle('is-mute', !voted)
      const warn = qEl.querySelector('.cdx-quiz__q-warn')
      if (null !== warn) warn.textContent = voted ? '' : 'No answer weighs a profile'
    })

    this.profilesList.querySelectorAll('.cdx-quiz__profile').forEach((pEl) => {
      const box = pEl.querySelector('.cdx-quiz__balance') as HTMLElement | null
      if (null === box) return
      const hit = tally[(pEl as HTMLElement).dataset.pid || '']
      box.textContent = undefined === hit
        ? 'No answer leads here'
        : `${Quiz.plural(hit.answers, 'answer')} · ${Quiz.plural(hit.points, 'point')}`
      box.classList.toggle('is-warn', undefined === hit)
    })
  }

  private static plural(count: number, word: string): string {
    return `${count} ${word}${1 === count ? '' : 's'}`
  }

  private buildResult(r: QuizResultBand): HTMLElement {
    const el = make.element('div', 'cdx-quiz__result-row')
    const min = make.element('input', ['cdx-quiz__input', 'cdx-quiz__result-min'], {
      type: 'number',
      min: '0',
      max: '100',
      placeholder: 'min %',
    }) as HTMLInputElement
    min.value = String(r.min ?? 0)
    el.appendChild(min)
    el.appendChild(this.inputEl('cdx-quiz__result-msg', 'Message', r.msg || ''))
    el.appendChild(
      make.element('button', 'cdx-quiz__del', { type: 'button' }, '✕', () => el.remove()),
    )
    return el
  }

  // The whole block labels its fields by placeholder alone, which leaves them
  // nameless once filled in: mirror it into an aria-label. No visual change.
  private inputEl(cls: string, placeholder: string, value: string): HTMLInputElement {
    const input = make.element('input', ['cdx-quiz__input', cls], {
      type: 'text',
      placeholder,
      'aria-label': placeholder,
    }) as HTMLInputElement
    input.value = value
    return input
  }

  private textareaEl(cls: string, placeholder: string, value: string): HTMLTextAreaElement {
    const textarea = make.element('textarea', ['cdx-quiz__textarea', cls], {
      placeholder,
      'aria-label': placeholder,
    }) as HTMLTextAreaElement
    textarea.value = value
    return textarea
  }

  /**
   * The media field plus the alt text of that image, so both survive a round-trip.
   * The alt only surfaces once there is an image to describe.
   */
  private buildMediaWithAlt(
    cls: string,
    placeholder: string,
    value: string,
    altCls: string,
    altValue: string,
  ): HTMLElement {
    const box = make.element('div', 'cdx-quiz__media-box')
    const alt = this.inputEl(altCls, 'Image alt text', altValue)
    box.appendChild(this.buildMediaField(cls, placeholder, value, (current: string) => {
      alt.hidden = '' === current
    }))
    alt.hidden = '' === value
    box.appendChild(alt)
    return box
  }

  /**
   * A filename input paired with media-library "Select" and "Upload" buttons and
   * a thumbnail preview. The input keeps `cls` so save() still reads it. Reused
   * for the question image (which doubles as the video poster) and answer images.
   */
  private buildMediaField(
    cls: string,
    placeholder: string,
    value: string,
    onChange?: (value: string) => void,
  ): HTMLElement {
    const field = make.element('div', 'cdx-quiz__media')

    const thumb = make.element('img', 'cdx-quiz__media-thumb') as HTMLImageElement
    const syncThumb = (current: string): void => {
      if (current) {
        thumb.src = MediaUtils.buildFullUrl(current)
        thumb.style.display = 'block'
      } else {
        thumb.removeAttribute('src')
        thumb.style.display = 'none'
      }

      if (undefined !== onChange) onChange(current)
    }

    const input = this.inputEl(cls, placeholder, value)
    input.classList.add('cdx-quiz__media-input')
    input.addEventListener('input', () => syncThumb(input.value.trim()))

    const select = make.element(
      'button',
      'cdx-quiz__media-btn',
      { type: 'button', title: 'Browse the media library' },
      SelectIcon,
      () => this.openMediaPicker(input, syncThumb),
    )
    const upload = make.element(
      'button',
      'cdx-quiz__media-btn',
      { type: 'button', title: 'Upload a file' },
      UploadIcon,
      () => this.uploadMedia(input, syncThumb),
    )

    field.appendChild(thumb)
    field.appendChild(input)
    field.appendChild(select)
    field.appendChild(upload)
    syncThumb(value)

    return field
  }

  /** Open the shared admin media picker modal and write the chosen filename into `input`. */
  private openMediaPicker(input: HTMLInputElement, onSet: (value: string) => void): void {
    const picker = document.querySelector('select[id*="inline_image"]') as HTMLSelectElement | null
    const wrapper = picker ? (picker.closest('.pw-media-picker') as HTMLElement | null) : null
    const chooseButton = wrapper
      ? (wrapper.querySelector('[data-pw-media-picker-action="choose"]') as HTMLButtonElement | null)
      : null

    if (!picker || !chooseButton) {
      this.api.notifier.show({ message: 'Media picker not available', style: 'error' })
      return
    }

    this.cleanupMediaPickerHandler()
    this.mediaPickerMessageHandler = (event: MessageEvent): void => {
      if (event.origin !== window.location.origin) return
      const payload = event.data
      if (!payload || payload.type !== 'pw-media-picker-select' || payload.fieldId !== picker.id) return

      this.cleanupMediaPickerHandler()
      const media = payload.media
      if (!media) return

      const mediaName = media.fileName || String(media.id)
      input.value = mediaName
      onSet(mediaName)
    }

    window.addEventListener('message', this.mediaPickerMessageHandler)
    chooseButton.click()
  }

  /** Upload a local file through the shared media endpoint, then write its filename into `input`. */
  private uploadMedia(input: HTMLInputElement, onSet: (value: string) => void): void {
    const file = make.element('input', null, { type: 'file', accept: 'image/*' }) as HTMLInputElement
    file.style.display = 'none'
    file.addEventListener('change', async () => {
      const chosen = file.files?.[0]
      if (chosen) {
        const formData = new FormData()
        formData.append('image', chosen)
        try {
          const response = await fetch('/admin/media/block', { method: 'POST', body: formData })
          if (!response.ok) throw new Error(await MediaUtils.uploadErrorMessage(response))
          const data = await response.json()
          const mediaName: string | undefined = data?.file?.media
          if (mediaName) {
            input.value = mediaName
            onSet(mediaName)
          }
        } catch (error) {
          const detail = error instanceof Error ? error.message : ''
          this.api.notifier.show({
            message: detail ? `Upload failed (${detail})` : 'Upload failed',
            style: 'error',
          })
        }
      }
      file.remove()
    })
    document.body.appendChild(file)
    file.click()
  }

  private cleanupMediaPickerHandler(): void {
    if (this.mediaPickerMessageHandler) {
      window.removeEventListener('message', this.mediaPickerMessageHandler)
      this.mediaPickerMessageHandler = null
    }
  }

  public destroy(): void {
    this.cleanupMediaPickerHandler()
  }

  private static val(selector: string, scope: Element): string {
    const el = scope.querySelector(selector) as HTMLInputElement | HTMLTextAreaElement | null
    return el ? el.value.trim() : ''
  }

  /** Read every `.cdx-quiz__q` within `scope` into question objects. */
  private readQuestions(scope: Element): QuizQuestion[] {
    const val = Quiz.val
    // Chips carry a `pid`; the key they export is whatever the profile card holds
    // right now, so a key renamed after the fact stays in sync for free.
    const keyByPid: Record<string, string> = {}
    this.profileHeads().forEach((head) => {
      if ('' !== head.key) keyByPid[head.pid] = head.key
    })

    const questions: QuizQuestion[] = []
    scope.querySelectorAll('.cdx-quiz__q').forEach((qEl) => {
      const question: QuizQuestion = { q: val('.cdx-quiz__q-text', qEl), answers: [] }
      const media = val('.cdx-quiz__q-media', qEl)
      if (media) question.media = media
      const video = val('.cdx-quiz__q-video', qEl)
      if (video) question.video = video
      const alt = val('.cdx-quiz__q-alt', qEl)
      if (alt) question.alt = alt
      const explanation = val('.cdx-quiz__q-explanation', qEl)
      if (explanation) question.explanation = explanation

      qEl.querySelectorAll('.cdx-quiz__a').forEach((aEl) => {
        const text = val('.cdx-quiz__a-text', aEl)
        if (!text) return
        const answer: QuizAnswer = { a: text }
        if (this.profileMode) {
          const weights: Record<string, number> = {}
          Object.entries(Quiz.readChips(aEl)).forEach(([pid, weight]) => {
            const key = keyByPid[pid]
            if (undefined !== key) weights[key] = weight
          })
          if (Object.keys(weights).length > 0) answer.weights = weights
        } else if ((aEl.querySelector('.cdx-quiz__a-correct') as HTMLInputElement)?.checked) {
          answer.correct = true
        }
        const answerMedia = val('.cdx-quiz__a-media', aEl)
        if (answerMedia) answer.media = answerMedia
        const answerAlt = val('.cdx-quiz__a-alt', aEl)
        if (answerAlt) answer.alt = answerAlt
        question.answers!.push(answer)
      })

      questions.push(question)
    })
    return questions
  }

  /** Read every `.cdx-quiz__profile` within `scope` into personality outcomes. */
  private readProfiles(scope: Element): QuizProfile[] {
    const profiles: QuizProfile[] = []
    scope.querySelectorAll('.cdx-quiz__profile').forEach((pEl) => {
      const key = Quiz.val('.cdx-quiz__profile-key', pEl)
      const title = Quiz.val('.cdx-quiz__profile-title', pEl)
      if (!key || !title) return
      const profile: QuizProfile = { key, title }
      const msg = Quiz.val('.cdx-quiz__profile-msg', pEl)
      if (msg) profile.msg = msg
      const media = Quiz.val('.cdx-quiz__profile-media', pEl)
      if (media) profile.media = media
      const alt = Quiz.val('.cdx-quiz__profile-alt', pEl)
      if (alt) profile.alt = alt
      profiles.push(profile)
    })
    return profiles
  }

  /** Read every `.cdx-quiz__result-row` within `scope` into score bands. */
  private readResults(scope: Element): QuizResultBand[] {
    const results: QuizResultBand[] = []
    scope.querySelectorAll('.cdx-quiz__result-row').forEach((rEl) => {
      const msg = Quiz.val('.cdx-quiz__result-msg', rEl)
      if (!msg) return
      results.push({ min: Number(Quiz.val('.cdx-quiz__result-min', rEl)) || 0, msg })
    })
    return results
  }

  public save(): QuizData {
    const root = this.wrapper
    const val = (selector: string): string => Quiz.val(selector, root)

    const data: QuizData = {
      feedback: (root.querySelector('.cdx-quiz__feedback') as HTMLSelectElement)?.value || 'immediate',
      questions: [],
    }

    const title = val('.cdx-quiz__title')
    if (title) data.title = title
    const cta = val('.cdx-quiz__cta')
    if (cta) data.cta = cta
    const ctaTitle = val('.cdx-quiz__cta-title')
    if (ctaTitle) data.ctaTitle = ctaTitle

    const numbering = (root.querySelector('.cdx-quiz__numbering') as HTMLSelectElement)?.value || ''
    if (numbering) data.numbering = numbering

    const labels: Record<string, string> = {}
    root.querySelectorAll('.cdx-quiz__label').forEach((labelEl) => {
      const key = (labelEl as HTMLElement).dataset.label || ''
      const value = (labelEl as HTMLInputElement).value.trim()
      if (key && value) labels[key] = value
    })
    if (Object.keys(labels).length > 0) data.labels = labels

    if (this.profileMode) {
      data.mode = 'profile'
      data.feedback = 'end' // no correct answer to reveal mid-way
      data.questions = this.readQuestions(this.singleSection)
      const profiles = this.readProfiles(this.profilesSection)
      if (profiles.length > 0) data.profiles = profiles
    } else if (this.levelsMode) {
      const levels: QuizData[] = []
      this.levelsList.querySelectorAll('.cdx-quiz__level').forEach((levelEl) => {
        const level: QuizData = { questions: this.readQuestions(levelEl) }
        const label = Quiz.val('.cdx-quiz__level-label', levelEl)
        if (label) level.label = label
        const difficulty = Quiz.val('.cdx-quiz__level-difficulty', levelEl)
        if (difficulty) level.difficulty = difficulty
        const passValue = Quiz.val('.cdx-quiz__level-pass', levelEl)
        if (passValue) level.pass = Number(passValue)
        const levelResults = this.readResults(levelEl)
        if (levelResults.length > 0) level.results = levelResults
        levels.push(level)
      })
      data.levels = levels
      // The root keeps no questions of its own once levels carry them.
    } else {
      const difficulty = val('.cdx-quiz__difficulty')
      if (difficulty) data.difficulty = difficulty
      data.questions = this.readQuestions(this.singleSection)
      const results = this.readResults(this.singleSection)
      if (results.length > 0) data.results = results
    }

    this.data = data
    return data
  }

  public validate(): boolean {
    return true
  }

  private static cleanQuestions(questions: QuizQuestion[] | undefined): QuizQuestion[] {
    const out: QuizQuestion[] = []
    ;(questions || []).forEach((q) => {
      const cq: QuizQuestion = { q: q.q || '', answers: [] }
      if (q.media) cq.media = q.media
      if (q.video) cq.video = q.video
      if (q.alt) cq.alt = q.alt
      if (q.explanation) cq.explanation = q.explanation
      ;(q.answers || []).forEach((a) => {
        if (!a.a) return
        const ca: QuizAnswer = { a: a.a }
        if (a.correct) ca.correct = true
        // Canonicalise personality weights: an explicit map, or the `profile` shorthand (== {key: 1}).
        if (a.weights && Object.keys(a.weights).length > 0) ca.weights = a.weights
        else if (a.profile) ca.weights = { [a.profile]: 1 }
        if (a.media) ca.media = a.media
        if (a.alt) ca.alt = a.alt
        cq.answers!.push(ca)
      })
      out.push(cq)
    })
    return out
  }

  private static cleanBands(results: QuizResultBand[] | undefined): QuizResultBand[] {
    return (results || []).filter((r) => r.msg).map((r) => ({ min: Number(r.min) || 0, msg: r.msg ?? '' }))
  }

  private static cleanProfiles(profiles: QuizProfile[] | undefined): QuizProfile[] {
    return (profiles || [])
      .filter((p) => p.key && p.title)
      .map((p) => {
        const cp: QuizProfile = { key: p.key ?? '', title: p.title ?? '' }
        if (p.msg) cp.msg = p.msg
        if (p.media) cp.media = p.media
        if (p.alt) cp.alt = p.alt
        return cp
      })
  }

  private static cleanLevel(level: QuizData): QuizData {
    const out: QuizData = { questions: Quiz.cleanQuestions(level.questions) }
    if (level.label) out.label = level.label
    if (level.difficulty) out.difficulty = level.difficulty
    if (level.pass !== undefined && level.pass !== null && !Number.isNaN(Number(level.pass))) {
      out.pass = Number(level.pass)
    }

    const bands = Quiz.cleanBands(level.results)
    if (bands.length > 0) out.results = bands
    return out
  }

  /** Strip empty optional fields so the exported JSON stays compact. */
  private static clean(data: QuizData): QuizData {
    const isProfile = 'profile' === data.mode

    // Shared metadata, in one place for every mode. Personality tests always
    // score at the end (no correct answer to reveal).
    const out: QuizData = { feedback: isProfile || 'end' === data.feedback ? 'end' : 'immediate' }
    if (isProfile) out.mode = 'profile'
    if (data.title) out.title = data.title
    if (data.cta) out.cta = data.cta
    if (data.ctaTitle) out.ctaTitle = data.ctaTitle
    if (data.numbering) out.numbering = data.numbering
    if (data.labels && Object.keys(data.labels).length > 0) out.labels = data.labels

    // Personality test: profile cards + weighted-answer questions.
    if (isProfile) {
      const profiles = Quiz.cleanProfiles(data.profiles)
      if (profiles.length > 0) out.profiles = profiles
      out.questions = Quiz.cleanQuestions(data.questions)
      return out
    }

    // Levels own the questions; the root keeps only the shared metadata.
    if (Array.isArray(data.levels) && data.levels.length > 0) {
      out.levels = data.levels.map((level) => Quiz.cleanLevel(level))
      return out
    }

    if (data.difficulty) out.difficulty = data.difficulty
    out.questions = Quiz.cleanQuestions(data.questions)

    const bands = Quiz.cleanBands(data.results)
    if (bands.length > 0) out.results = bands

    return out
  }

  public static exportToMarkdown(data: QuizData): string {
    const clean = Quiz.clean(data)
    const hasQuestions = !!clean.questions && clean.questions.length > 0
    const hasLevels = !!clean.levels && clean.levels.length > 0
    if (!hasQuestions && !hasLevels) return ''

    // The `{% quiz %}` body is raw JSON: no Twig-string escaping (apostrophes stay
    // literal) and pretty-printed so the stored content reads and diffs cleanly.
    // JSON.stringify never emits blank lines, which the Markdown pipeline splits on.
    return `{% quiz %}\n${JSON.stringify(clean, null, 2)}\n{% endquiz %}`
  }

  static importFromMarkdown(editor: API, markdown: string): QuizData {
    const empty: QuizData = { feedback: 'immediate', questions: [] }
    const json = Quiz.extractJson(markdown)
    if (json === null) return empty

    let data: QuizData
    try {
      data = JSON.parse(json)
    } catch {
      return empty
    }

    const block = editor.blocks.insert('quiz', data)
    editor.blocks.update(block.id, data)
    return data
  }

  /**
   * Pull the JSON out of either the `{% quiz %}` block (raw body) or the legacy
   * `{{ quiz('…') }}` function (a single-quoted Twig string, so `\'`/`\\` are
   * unescaped back to JSON).
   */
  private static extractJson(markdown: string): string | null {
    const tag = markdown.match(/\{%\s*quiz\s*%\}([\s\S]*?)\{%\s*endquiz\s*%\}/)
    if (tag) return (tag[1] ?? '').trim()

    const fn = markdown.match(/\{\{\s*quiz\(\s*'((?:\\.|[^'\\])*)'\s*\)\s*\}\}/)
    if (fn) return (fn[1] ?? '').replace(/\\(['\\])/g, '$1')

    return null
  }

  static isItMarkdownExported(markdown: string): boolean {
    return /\{%\s*quiz\s*%\}/.test(markdown) || /\{\{\s*quiz\(/.test(markdown)
  }
}
