/**
 * A condition builder that grows over a criteria textarea.
 *
 * It knows no field and no operator of its own: everything it offers comes from
 * the vocabulary endpoint the textarea points at, so a language the server adds
 * — a trigger source a bundle registered — is editable here without a line
 * changing in this file.
 *
 * The textarea stays the form field. Every edit is written back into it, which
 * is what gets submitted and what the server validates; the builder is only a
 * way of typing. It can be dismissed at any time ("edit as text"), and a rule it
 * cannot show — a pages_list search, or JSON someone is halfway through — leaves
 * it out of the way rather than rewriting it.
 */

const DURATION_PATTERN = /^(\d+)([mhdw])$/

// The units the segment language reads. Written here because they are how a
// duration is spelled rather than what a language declares; anything else the
// user types is refused server-side, like any other malformed value.
const DURATION_UNITS = [
  ['m', 'unitMinutes'],
  ['h', 'unitHours'],
  ['d', 'unitDays'],
  ['w', 'unitWeeks'],
]

/**
 * Read the stored rule into the tree the builder edits.
 *
 * Returns null when the text is not something it can show — the caller keeps
 * the raw editor open instead of guessing.
 */
export function parseRule(text) {
  const trimmed = (text || '').trim()

  if (trimmed === '') return { any: false, children: [] }
  // A rule may also be written as a search string. It is a legal spelling, and
  // not one that has rows to show.
  if (!trimmed.startsWith('[') && !trimmed.startsWith('{')) return null

  let decoded
  try {
    decoded = JSON.parse(trimmed)
  } catch {
    return null
  }

  return toGroup(decoded)
}

function toGroup(node) {
  if (Array.isArray(node)) {
    const children = toChildren(node)

    return children === null ? null : { any: false, children }
  }

  if (node === null || typeof node !== 'object') return null

  const operator = 'any' in node ? 'any' : 'all' in node ? 'all' : null
  if (operator === null || !Array.isArray(node[operator])) return null

  const children = toChildren(node[operator])

  return children === null ? null : { any: operator === 'any', children }
}

function toChildren(list) {
  const children = []

  for (const child of list) {
    if (child === null || typeof child !== 'object' || Array.isArray(child)) return null

    if ('any' in child || 'all' in child) {
      const group = toGroup(child)
      if (group === null) return null
      children.push(group)
      continue
    }

    if (typeof child.field !== 'string' || typeof child.op !== 'string') return null

    children.push({
      field: child.field,
      op: child.op,
      value: child.value === undefined || child.value === null ? '' : String(child.value),
    })
  }

  return children
}

/** The tree, back in the shape the server stores and reads. */
export function serializeRule(group) {
  const children = childrenToJson(group)

  if (children.length === 0) return ''

  return JSON.stringify(group.any ? { any: children } : children, null, 4)
}

function childrenToJson(group) {
  return group.children.map((child) => {
    if (!isGroup(child)) {
      // A valueless operator drops its value, as the server does when it reads
      // the rule back.
      return child.value === '' ? { field: child.field, op: child.op } : { field: child.field, op: child.op, value: child.value }
    }

    // A nested group always names its operator: coming back as a bare list it
    // would be read as a condition.
    return child.any ? { any: childrenToJson(child) } : { all: childrenToJson(child) }
  })
}

function isGroup(node) {
  return Object.prototype.hasOwnProperty.call(node, 'children')
}

/** Only ever spent on datalist ids, which have to be unique across the page. */
let uid = 0

class CriteriaBuilder {
  constructor(textarea) {
    this.textarea = textarea
    this.form = textarea.closest('form')
    this.side = textarea.dataset.pwCriteria
    this.model = parseRule(textarea.value)
    this.raw = this.model === null
    this.sinceAll = false
    this.vocabulary = null
    this.previewTimer = null
  }

  async mount() {
    this.container = document.createElement('div')
    this.container.className = 'pw-criteria'
    this.textarea.after(this.container)

    if (!(await this.loadVocabulary())) return

    // Only now: until the vocabulary answers, the textarea is the editor, and a
    // server that cannot describe the language leaves it that way.
    this.sourceField()?.addEventListener('change', () => this.sourceChanged())
    this.render()
  }

  async loadVocabulary() {
    const url = new URL(this.textarea.dataset.pwCriteriaVocabulary, window.location.origin)
    url.searchParams.set('side', this.side)
    url.searchParams.set('source', this.sourceField()?.value || '')
    this.hosts().forEach((host) => url.searchParams.append('hosts[]', host))

    try {
      const response = await fetch(url, { headers: { Accept: 'application/json' } })
      if (!response.ok) return false
      this.vocabulary = await response.json()

      return true
    } catch {
      return false
    }
  }

  /**
   * The source decides which vocabulary the trigger rule is written in, so
   * changing it invalidates every row: the fields of the language just left do
   * not exist in the one just picked.
   */
  async sourceChanged() {
    if (!(await this.loadVocabulary())) return

    if (!this.raw) this.model = pruneGroup(this.model, this.vocabulary)
    this.write()
    this.render()
  }

  render() {
    this.container.replaceChildren()

    if (this.raw) {
      this.renderRaw()
    } else {
      this.textarea.classList.add('pw-criteria-hidden')
      this.container.append(this.renderGroup(this.model, 0))
    }

    this.container.append(this.renderFoot())
    this.preview()
  }

  renderRaw() {
    this.textarea.classList.remove('pw-criteria-hidden')

    const hint = element('p', 'pw-criteria-hint form-help')
    hint.textContent = this.model === null ? this.label('rawInvalid') : this.label('rawHint')
    if (this.vocabulary.acceptsSearch) hint.append(element('br'), text(this.label('searchHint')))
    this.container.append(hint)
  }

  renderGroup(group, depth) {
    const node = element('div', 'pw-criteria-group')
    node.dataset.depth = String(depth)

    node.append(this.renderConjunction(group), this.renderRows(group, depth), this.renderGroupActions(group, depth))

    return node
  }

  /** All or any, as two buttons: a rule reads as a sentence, not as a dropdown. */
  renderConjunction(group) {
    const node = element('div', 'pw-criteria-conjunction btn-group btn-group-sm')

    for (const any of [false, true]) {
      const button = element('button', `btn btn-sm ${group.any === any ? 'btn-secondary' : 'btn-outline-secondary'}`)
      button.type = 'button'
      button.textContent = this.label(any ? 'any' : 'all')
      button.addEventListener('click', () => {
        group.any = any
        this.commit()
      })
      node.append(button)
    }

    return node
  }

  renderRows(group, depth) {
    const rows = element('div', 'pw-criteria-rows')

    if (group.children.length === 0) {
      const empty = element('p', 'pw-criteria-empty form-help')
      empty.textContent = this.label('empty')
      rows.append(empty)
    }

    group.children.forEach((child, index) => {
      const node = isGroup(child) ? this.renderGroup(child, depth + 1) : this.renderCondition(child)
      node.append(this.renderRemove(group, index))
      rows.append(node)
    })

    return rows
  }

  renderCondition(condition) {
    const row = element('div', 'pw-criteria-row')
    const field = this.field(condition.field)

    row.append(this.renderFieldSelect(condition))

    if (field?.property) row.append(this.renderPropertyKey(condition))

    row.append(this.renderOperatorSelect(condition, field))

    const operator = this.operator(field, condition.op)
    if (operator && !operator.valueless) {
      row.append(operator.duration ? this.renderDuration(condition) : this.renderValue(condition, field))
    }

    return row
  }

  renderFieldSelect(condition) {
    const select = element('select', 'form-select form-select-sm pw-criteria-field')
    select.setAttribute('aria-label', this.label('field'))

    for (const [name, field] of Object.entries(this.vocabulary.fields)) {
      const option = element('option')
      option.value = name
      option.textContent = field.property ? `${name}…` : name
      option.selected = name === this.fieldName(condition.field)
      select.append(option)
    }

    select.addEventListener('change', () => {
      const field = this.vocabulary.fields[select.value]
      // The operators of the field just left rarely apply to the one just
      // picked, and a value written for a duration means nothing to a tag.
      condition.field = field.property ? this.vocabulary.propertyPrefix : select.value
      condition.op = field.operators[0]?.name || ''
      condition.value = ''
      this.commit()
    })

    return select
  }

  renderPropertyKey(condition) {
    const input = element('input', 'form-control form-control-sm pw-criteria-key')
    input.value = condition.field.slice(this.vocabulary.propertyPrefix.length)
    input.placeholder = this.label('property')
    input.setAttribute('aria-label', this.label('property'))
    this.suggest(input, this.vocabulary.fields[this.vocabulary.propertyPrefix].suggestions)

    input.addEventListener('input', () => {
      condition.field = this.vocabulary.propertyPrefix + input.value.trim()
      this.write()
    })

    return input
  }

  renderOperatorSelect(condition, field) {
    const select = element('select', 'form-select form-select-sm pw-criteria-op')
    select.setAttribute('aria-label', this.label('operator'))

    for (const operator of field?.operators || []) {
      const option = element('option')
      option.value = operator.name
      option.textContent = operator.name
      option.selected = operator.name === condition.op
      select.append(option)
    }

    select.addEventListener('change', () => {
      condition.op = select.value
      this.commit()
    })

    return select
  }

  renderValue(condition, field) {
    const input = element('input', 'form-control form-control-sm pw-criteria-value')
    input.value = condition.value
    input.setAttribute('aria-label', this.label('value'))
    this.suggest(input, field?.suggestions || [])

    input.addEventListener('input', () => {
      condition.value = input.value
      this.write()
    })

    return input
  }

  /** An amount and a unit, rather than asking for "7d" to be remembered. */
  renderDuration(condition) {
    const wrapper = element('div', 'pw-criteria-duration')
    const parsed = DURATION_PATTERN.exec(condition.value)

    const amount = element('input', 'form-control form-control-sm')
    amount.type = 'number'
    amount.min = '1'
    amount.value = parsed ? parsed[1] : ''
    amount.setAttribute('aria-label', this.label('duration'))

    const unit = element('select', 'form-select form-select-sm')
    unit.setAttribute('aria-label', this.label('duration'))
    for (const [value, label] of DURATION_UNITS) {
      const option = element('option')
      option.value = value
      option.textContent = this.label(label)
      option.selected = parsed ? value === parsed[2] : value === 'd'
      unit.append(option)
    }

    const update = () => {
      condition.value = amount.value === '' ? '' : amount.value + unit.value
      this.write()
    }
    amount.addEventListener('input', update)
    unit.addEventListener('change', update)

    wrapper.append(amount, unit)

    return wrapper
  }

  renderGroupActions(group, depth) {
    const actions = element('div', 'pw-criteria-actions')

    actions.append(
      this.button('addCondition', () => {
        const field = Object.entries(this.vocabulary.fields)[0]
        group.children.push({ field: field[0], op: field[1].operators[0]?.name || '', value: '' })
        this.commit()
      }),
    )

    // One level of nesting: `{"any": […]}` inside an `all` is the rule nobody
    // can write in one flat list, and a deeper tree is one nobody can read.
    if (depth === 0) {
      actions.append(
        this.button('addGroup', () => {
          group.children.push({ any: !group.any, children: [] })
          this.commit()
        }),
      )
    }

    return actions
  }

  renderRemove(group, index) {
    const button = element('button', 'btn btn-sm btn-link pw-criteria-remove')
    button.type = 'button'
    button.textContent = '×'
    button.setAttribute('aria-label', this.label('remove'))
    button.addEventListener('click', () => {
      group.children.splice(index, 1)
      this.commit()
    })

    return button
  }

  renderFoot() {
    const foot = element('div', 'pw-criteria-foot')

    foot.append(
      this.button(this.raw ? 'toBuilder' : 'raw', () => {
        // Coming back from the raw editor re-reads what was typed there: text
        // the builder cannot show keeps it out of the way.
        if (this.raw) {
          this.model = parseRule(this.textarea.value)
          this.raw = this.model === null
        } else {
          this.raw = true
        }

        this.render()
      }),
    )

    if (this.side === 'trigger') {
      const since = this.button('sinceAll', () => {
        this.sinceAll = !this.sinceAll
        since.classList.toggle('active', this.sinceAll)
        this.preview()
      })
      since.classList.toggle('active', this.sinceAll)
      foot.append(since)
    }

    this.previewNode = element('span', 'pw-criteria-count')
    foot.append(this.previewNode)

    return foot
  }

  /** A structural change: the rows no longer match the model, so redraw them. */
  commit() {
    this.write()
    this.render()
  }

  /** The model, back into the field the form submits. */
  write() {
    if (!this.raw) this.textarea.value = serializeRule(this.model)
    this.schedulePreview()
  }

  schedulePreview() {
    window.clearTimeout(this.previewTimer)
    this.previewTimer = window.setTimeout(() => this.preview(), 500)
  }

  async preview() {
    if (!this.previewNode) return

    const audience = this.form?.querySelector(`[name="${this.prefix()}[audience]"]`)
    const automation = this.textarea.dataset.pwCriteriaAutomation

    let result
    try {
      const response = await fetch(this.textarea.dataset.pwCriteriaPreview, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({
          side: this.side,
          source: this.sourceField()?.value || '',
          hosts: this.hosts(),
          rule: this.textarea.value,
          automation: automation ? Number(automation) : null,
          audience: audience && audience.value ? Number(audience.value) : null,
          sinceAll: this.sinceAll,
        }),
      })
      result = await response.json()
    } catch {
      result = { error: this.label('previewFailed') }
    }

    this.renderPreview(result)
  }

  renderPreview(result) {
    this.previewNode.replaceChildren()
    this.previewNode.classList.toggle('pw-criteria-count-error', Boolean(result.error))

    if (result.error) {
      this.previewNode.textContent = result.error

      return
    }

    if (result.count === null || result.count === undefined) {
      this.previewNode.textContent = this.label(result.saveFirst ? 'previewSaveFirst' : 'previewNeedsAudience')

      return
    }

    const count = element('strong')
    count.textContent = String(result.count)
    this.previewNode.append(count, text(` ${this.label(this.side === 'trigger' ? 'previewTrigger' : 'previewContacts')}`))

    if (result.samples?.length) {
      this.previewNode.append(text(` — ${result.samples.join(', ')}`))
    }
  }

  /** A datalist rather than a select: a rule may name what does not exist yet. */
  suggest(input, suggestions) {
    if (suggestions.length === 0) return

    const list = element('datalist')
    list.id = `pw-criteria-list-${++uid}`
    suggestions.forEach((suggestion) => {
      const option = element('option')
      option.value = suggestion
      list.append(option)
    })

    input.setAttribute('list', list.id)
    input.after(list)
    this.container.append(list)
  }

  button(label, onClick) {
    const button = element('button', 'btn btn-sm btn-outline-secondary')
    button.type = 'button'
    button.textContent = this.label(label)
    button.addEventListener('click', onClick)

    return button
  }

  label(key) {
    return this.vocabulary?.labels[key] || key
  }

  /** The declared field a condition names — every `prop.<key>` shares one. */
  fieldName(name) {
    return name.startsWith(this.vocabulary.propertyPrefix) ? this.vocabulary.propertyPrefix : name
  }

  field(name) {
    return this.vocabulary.fields[this.fieldName(name)]
  }

  operator(field, name) {
    return field?.operators.find((operator) => operator.name === name)
  }

  /** The other fields of the same form, named as this one is. */
  prefix() {
    return this.textarea.name.slice(0, this.textarea.name.indexOf('['))
  }

  sourceField() {
    return this.form?.querySelector(`[name="${this.prefix()}[source]"]`)
  }

  hosts() {
    return Array.from(this.form?.querySelectorAll(`[name="${this.prefix()}[hosts][]"]:checked`) || []).map((input) => input.value)
  }
}

/** Drop what the language just picked has no field for, keeping the rest. */
export function pruneGroup(group, vocabulary) {
  return {
    any: group.any,
    children: group.children
      .map((child) => (isGroup(child) ? pruneGroup(child, vocabulary) : child))
      .filter((child) =>
        isGroup(child)
          ? child.children.length > 0
          : child.field.startsWith(vocabulary.propertyPrefix) || Object.hasOwn(vocabulary.fields, child.field),
      ),
  }
}

function element(tag, className) {
  const node = document.createElement(tag)
  if (className) node.className = className

  return node
}

function text(content) {
  return document.createTextNode(content)
}

export function initCriteriaBuilder() {
  document.querySelectorAll('textarea[data-pw-criteria]').forEach((textarea) => {
    new CriteriaBuilder(textarea).mount()
  })
}
