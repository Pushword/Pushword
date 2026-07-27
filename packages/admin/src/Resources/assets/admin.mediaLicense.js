/**
 * Clear / apply-the-site-license buttons for the media license fieldset.
 *
 * Both are client-side until save: they write to the inputs and let the normal
 * submit persist, which is what the `mapped => false` + form-subscriber pairing
 * needs. Pressing "apply" IS the ownership assertion — it is how an editor says the
 * site licenses an image whose file claims somebody else's rights.
 *
 * digitalSourceType is left alone by both: it records what the file is, not who
 * licenses it.
 */
const SEED_PREFIX = 'pwLicenseSeed'
const PROVENANCE_FIELD = 'digitalSourceType'
const CREATOR_FIELD = 'creator'

function licenseFields(scope) {
  return Array.from(scope.querySelectorAll('[data-pw-license-field]')).filter(
    (field) => field.dataset.pwLicenseField !== PROVENANCE_FIELD,
  )
}

function readSeed(fields) {
  const seed = {}
  fields.forEach((field) => {
    Object.keys(field.dataset).forEach((key) => {
      if (!key.startsWith(SEED_PREFIX)) return
      const name = key.substring(SEED_PREFIX.length).toLowerCase()
      if (name) seed[name] = field.dataset[key]
    })
  })
  return seed
}

function setValue(field, value) {
  if (field.value === value) return
  field.value = value
  field.dispatchEvent(new Event('change', { bubbles: true }))
}

/** The creator field is a collection of {name, type} rows, not an input. */
function isCreator(field) {
  return field.dataset.pwLicenseField === CREATOR_FIELD
}

function creatorRows(collection) {
  return Array.from(collection.querySelectorAll('.field-collection-item'))
}

function clearCreators(collection) {
  // EasyAdmin's own delete button, not row.remove(): it is what keeps the rest of
  // the collection's bookkeeping (indexes, empty placeholder) consistent.
  collection.querySelectorAll('.field-collection-delete-button').forEach((button) => button.click())
}

/**
 * Adds a row per seeded creator through EasyAdmin's "add" button. Cloning the
 * data-prototype by hand would duplicate markup that belongs to the form theme;
 * pressing their button is the same thing an editor does.
 */
function applyCreators(collection, creators) {
  clearCreators(collection)

  const addButton = collection.querySelector('.field-collection-add-button')
  if (!addButton) return

  creators.forEach((creator) => {
    addButton.click()

    const row = creatorRows(collection).pop()
    if (!row) return

    const name = row.querySelector('input[type="text"]')
    const type = row.querySelector('select')
    if (name) setValue(name, creator.name ?? '')
    if (type) setValue(type, creator.type ?? 'Person')
  })
}

function parseCreators(raw) {
  if (!raw) return []
  try {
    const parsed = JSON.parse(raw)
    return Array.isArray(parsed) ? parsed : []
  } catch {
    return []
  }
}

function buildButton(label, className, onClick) {
  const button = document.createElement('button')
  button.type = 'button'
  button.className = `btn btn-sm ${className} me-2 mt-2`
  button.textContent = label
  button.addEventListener('click', onClick)
  return button
}

export function initMediaLicense() {
  const fields = licenseFields(document)
  if (fields.length === 0) return

  // The fieldset body, not the first field's form-group: the buttons act on the whole
  // block, so they belong under it rather than between two of its inputs.
  const container = fields[0].closest('.form-fieldset-body, .form-fieldset, fieldset') ?? fields[0].parentElement
  if (!container || container.querySelector('[data-pw-license-actions]')) return

  const seed = readSeed(fields)
  const labels = document.getElementById('pw-license-labels')?.dataset ?? {}

  const actions = document.createElement('div')
  actions.setAttribute('data-pw-license-actions', '')

  // A third-party file keeps its own attribution and gets no site license: say so,
  // otherwise the empty license fields look like a bug.
  if (fields[0].dataset.pwLicenseState === 'thirdParty' && labels.thirdParty) {
    const note = document.createElement('p')
    note.className = 'form-help mt-2'
    note.textContent = labels.thirdParty
    actions.appendChild(note)
  }

  if (Object.keys(seed).length > 0) {
    actions.appendChild(
      buildButton(labels.apply ?? 'Apply the site license', 'btn-outline-primary', () => {
        fields.forEach((field) => {
          const key = field.dataset.pwLicenseField
          if (isCreator(field)) {
            applyCreators(field, parseCreators(seed[key.toLowerCase()]))
            return
          }
          setValue(field, seed[key.toLowerCase()] ?? '')
        })
      }),
    )
  }

  actions.appendChild(
    buildButton(labels.clear ?? 'Clear', 'btn-outline-secondary', () => {
      fields.forEach((field) => (isCreator(field) ? clearCreators(field) : setValue(field, '')))
    }),
  )

  container.appendChild(actions)
}
