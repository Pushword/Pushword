/**
 * List of all functions
 *
 * - liveBlock(attr)
 * - liveForm(attr)
 *
 * -  seasonedBackground
 * - responsiveImage(string)  Relative to Liip filters
 * - uncloakLinks(attr)
 * - convertFormFromRot13(attr)
 * - readableEmail(attr)
 * - convertImageLinkToWebPLink()
 */

// Media-query gates re-evaluate when the viewport changes: one change listener
// per distinct query re-runs the scan through the DOMChanged contract.
const mediaWatched = new Set()
// Trigger bindings are tracked outside the DOM (WeakSet, not dataset): a
// cloned or re-serialized block must arrive unbound, like a fresh node.
const boundTriggers = new WeakSet()
let htmxBridgeInstalled = false

function htmx4() {
  return window.htmx && parseInt(window.htmx.version) >= 4 ? window.htmx : null
}

// When htmx 4 is on the page it owns all swaps; this bridge keeps the two
// worlds in sync. Installed once, with dynamic window.htmx lookups so it stays
// inert when htmx is absent.
function installHtmxBridge() {
  if (htmxBridgeInstalled || !htmx4()) return
  htmxBridgeInstalled = true
  // On document, not body: when a fragment swaps its own trigger away, htmx 4
  // dispatches after:swap directly on document (detached-source fallback).
  document.addEventListener('htmx:after:swap', () =>
    document.dispatchEvent(new Event('DOMChanged')),
  )
  // Content added outside a swap (an Alpine x-teleport clone) becomes
  // discoverable; re-processing unchanged nodes is a no-op for htmx.
  document.addEventListener('DOMChanged', () => {
    const htmx = htmx4()
    if (htmx) htmx.process(document.body)
  })
  // Aliased blocks keep the legacy failure contract.
  document.addEventListener('htmx:response:error', (event) => {
    const el = event.detail && event.detail.ctx && event.detail.ctx.sourceElement
    if (el && el.hasAttribute && el.hasAttribute('data-live-alias')) {
      el.dispatchEvent(
        new CustomEvent('live-block-forbidden', {
          bubbles: true,
          detail: {
            status: event.detail.ctx.response.status,
            url: el.getAttribute('data-live-alias'),
          },
        }),
      )
    }
  })
}

/**
 * Live Block Watcher (and button)
 *
 * Fetch (ajax) function permitting to get block via a POST request
 *
 * @param {string} attribute
 */
export function liveBlock(liveBlockAttribute = 'live', liveFormSelector = '.live-form') {
  installHtmxBridge()
  var btnToBlock = function (event, btn) {
    const liveBlockUrl = btn.getAttribute('data-src-' + liveBlockAttribute)
    if (btn.hasAttribute('data-target') && btn.getAttribute('data-target') == 'parent') {
      btn = btn.parentElement ?? btn
    }
    btn.setAttribute('data-' + liveBlockAttribute, liveBlockUrl)
    getLiveBlock(btn)
  }

  var liveUrl = function (item) {
    var url = item.getAttribute('data-' + liveBlockAttribute)
    return url.startsWith('e:')
      ? convertShortchutForLink(rot13ToText(url.substring(2)))
      : url
  }

  var getLiveBlock = function (item, keepContainer) {
    var url = liveUrl(item)
    fetch(url, {
      //headers: { "Content-Type": "application/json", Accept: "text/plain" },
      method: 'POST',
      credentials: 'include',
    })
      .then(function (response) {
        if (!response.ok) {
          // Drop the trigger: liveBlock() re-runs on every DOMChanged, so a kept
          // data-live would re-fetch the failed block forever (e.g. on a static
          // host where the endpoint 404s). The event detail keeps the url for
          // listeners that want to handle or retry it deliberately.
          // Repeat mode keeps it: its fetch only ever runs on an explicit
          // event, so there is no refetch loop and the block stays retryable.
          if (!keepContainer) item.removeAttribute('data-' + liveBlockAttribute)
          item.dispatchEvent(
            new CustomEvent('live-block-forbidden', {
              bubbles: true,
              detail: { status: response.status, url: url },
            }),
          )
          return null
        }
        return response.text()
      })
      .then(function (body) {
        if (body === null) return
        if (keepContainer) {
          item.innerHTML = body
        } else {
          item.removeAttribute('data-' + liveBlockAttribute)
          item.outerHTML = body
        }
        document.dispatchEvent(new Event('DOMChanged'))
      })
  }

  const spinner =
    '<span style="border-top-color: transparent" class="inline-block w-5 h-5 border-4 border-gray-50 border-solid rounded-full animate-spin"></span>'
  const htmlLoader =
    '<div>' + spinner.replace('border-gray-50', 'border-gray-800') + '</div>'

  var setLoader = function (form) {
    var $submitButton = getSubmitButton(form)
    if ($submitButton !== undefined) {
      //var initialButton = $submitButton.outerHTML;
      $submitButton.innerHTML = ''
      $submitButton.outerHTML = htmlLoader
    }
  }

  var sendForm = function (form, liveFormBlock) {
    if (liveFormBlock.dataset.submitting) return
    liveFormBlock.dataset.submitting = '1'
    setLoader(form)

    var formData = new FormData(form.srcElement)
    fetch(form.srcElement.action, {
      method: 'POST',
      body: formData,
      credentials: 'include',
    })
      .then(function (response) {
        if (!response.ok) {
          liveFormBlock.dispatchEvent(
            new CustomEvent('live-block-forbidden', {
              bubbles: true,
              detail: { status: response.status, url: form.srcElement.action },
            }),
          )
          delete liveFormBlock.dataset.submitting
          return null
        }
        return response.text()
      })
      .then(function (body) {
        if (body === null) return
        liveFormBlock.outerHTML = body
        document.dispatchEvent(new Event('DOMChanged'))
      })
  }

  var getSubmitButton = function (form) {
    if (form.srcElement.querySelector('[type=submit]') !== null) {
      return form.srcElement.querySelector('[type=submit]')
    }
    if (form.srcElement.getElementsByTagName('button') !== null) {
      return form.srcElement.getElementsByTagName('button')[0]
    }
    return null
  }

  // Gates for data-live-if="<kind>:<rest>". All string-matched, never eval'd —
  // a strict-CSP site can use every one of them.
  var gates = {
    cookie: function (rest) {
      var eq = rest.indexOf('=')
      var name = eq === -1 ? rest : rest.substring(0, eq)
      var value = eq === -1 ? '1' : rest.substring(eq + 1)
      return document.cookie.split('; ').indexOf(name + '=' + value) !== -1
    },
    media: function (rest) {
      if (!window.matchMedia) return false
      var mql = window.matchMedia(rest)
      if (!mediaWatched.has(rest)) {
        mediaWatched.add(rest)
        // Re-scan when the query flips, so a widened window or a phone
        // rotated to landscape still loads its gated block.
        mql.addEventListener('change', function () {
          document.dispatchEvent(new Event('DOMChanged'))
        })
      }
      return mql.matches
    },
  }

  // Unknown or empty gates fail closed: the block is skipped (and re-evaluated
  // on the next pass), never fetched by accident.
  var evalLiveIf = function (expr) {
    var i = expr.indexOf(':')
    var kind = i === -1 ? expr : expr.substring(0, i)
    var rest = i === -1 ? '' : expr.substring(i + 1)
    var gate = gates[kind]
    return gate !== undefined && rest !== '' ? gate(rest) : false
  }

  // data-live-trigger="<event>": defer the fetch until the event fires on
  // window. Once by default; data-live-repeat refetches on every occurrence
  // into a surviving container (inner swap).
  var bindTrigger = function (item, eventName, repeat) {
    if (boundTriggers.has(item)) return
    boundTriggers.add(item)
    var handler = function () {
      if (!repeat) window.removeEventListener(eventName, handler)
      getLiveBlock(item, repeat)
    }
    window.addEventListener(eventName, handler)
  }

  // Under htmx 4, a data-live block becomes a plain htmx element: one request
  // engine on the page, and htmx's process passes discover it everywhere
  // (x-teleport clones included). Gates stay ours — evaluated before this.
  var aliasToHtmx = function (item, trig, repeat, htmx) {
    var url = liveUrl(item)
    item.removeAttribute('data-' + liveBlockAttribute)
    item.setAttribute('data-' + liveBlockAttribute + '-alias', url)
    item.setAttribute('hx-post', url)
    item.setAttribute(
      'hx-trigger',
      trig ? trig + ' from:window' + (repeat ? '' : ' once') : 'load',
    )
    item.setAttribute('hx-swap', repeat ? 'innerHTML' : 'outerHTML')
    item.setAttribute('hx-config', 'credentials:"include"')
    // Legacy semantics: an error page never replaces the block.
    item.setAttribute('hx-status:4xx', 'swap:none')
    item.setAttribute('hx-status:5xx', 'swap:none')
    htmx.process(item)
  }

  // Listen data-live
  document.querySelectorAll('[data-' + liveBlockAttribute + ']').forEach((item) => {
    var cond = item.getAttribute('data-' + liveBlockAttribute + '-if')
    if (cond && !evalLiveIf(cond)) return
    var trig = item.getAttribute('data-' + liveBlockAttribute + '-trigger')
    var repeat = item.hasAttribute('data-' + liveBlockAttribute + '-repeat')
    var htmx = htmx4()
    if (htmx) {
      aliasToHtmx(item, trig, repeat, htmx)
      return
    }
    if (trig) {
      bindTrigger(item, trig, repeat)
      return
    }
    getLiveBlock(item)
  })

  // Listen button src-data-live / data-src-live
  document.querySelectorAll('[data-src-' + liveBlockAttribute + ']').forEach((item) => {
    if (item.dataset.liveButtonBound) return
    item.dataset.liveButtonBound = '1'
    item.addEventListener('click', (event) => {
      if (item.tagName == 'BUTTON') {
        item.innerHTML = spinner
        item.setAttribute('disabled', true)
      }
      btnToBlock(event, item)
    })
  })

  // Listen live-form (guard against duplicate listeners on re-init or nesting)
  document.querySelectorAll(liveFormSelector).forEach((item) => {
    if (item.dataset.liveFormBound) return
    item.dataset.liveFormBound = '1'
    var form = item.querySelector('form')
    if (form !== null && form.closest(liveFormSelector) === item) {
      form.addEventListener('submit', (e) => {
        e.preventDefault()
        sendForm(e, item)
      })
    }
  })
}

/**
 * Block to replace Watcher
 * On $event on element find via $attribute, set attribute's content in element.innerHTML
 */
export function replaceOn(attribute = 'data-replaceBy', eventName = 'click') {
  var loadVideo = function (element) {
    var content = element.getAttribute(attribute)
    if (
      element.classList.contains('hero-banner-overlay-lg') &&
      element.querySelector('picture') &&
      window.innerWidth < 992
    ) {
      element.querySelector('picture').outerHTML = content
      element.querySelector('.btn-play').outerHTML = ' '
    } else {
      element.innerHTML = content
    }
    if (element.classList.contains('hero-banner-overlay-lg')) {
      element.style.zIndex = '2000'
    }
    element.removeAttribute(attribute)
    document.dispatchEvent(new Event('DOMChanged'))
  }

  document
    .querySelectorAll('[' + attribute + ']:not([listen])')
    .forEach(function (element) {
      element.setAttribute('listen', '')
      element.addEventListener(
        eventName,
        function (event) {
          loadVideo(event.currentTarget) //event.currentTarget;
          element.removeAttribute('listen')
        },
        { once: true },
      )
    })
}

/**
 *
 *
 */
export function seasonedBackground() {
  document.querySelectorAll('[x-hash]').forEach(function (element) {
    if (window.location.hash) {
      if (element.getAttribute('x-hash') == window.location.hash.substring(1)) {
        element.parentNode.parentNode.querySelectorAll('img').forEach(function (img) {
          img.style = 'display:none'
        })
        element.style = 'display:block'
      }
    }
  })
}

/**
 * Transform image's path (src) produce with Liip to responsive path
 *
 * @param {string} src
 */
export function responsiveImage(src) {
  var screenWidth = window.innerWidth
  if (screenWidth <= 576) {
    src = src.replace('/default/', '/xs/')
  } else if (screenWidth <= 768) {
    src = src.replace('/default/', '/sm/')
  } else if (screenWidth <= 992) {
    src = src.replace('/default/', '/md/')
  } else if (screenWidth <= 1200) {
    src = src.replace('/default/', '/lg/')
  } else {
    // 1200+
    src = src.replace('/default/', '/xl/')
  }

  return src
}

// The scroll-triggered hash correction below is a one-time page-load concern
// (the browser's native jump to #anchor can land wrong when content lazily
// reveals). addClassForNormalUser() re-runs on every DOMChanged and each run
// registers a fresh scroll watcher, so without this guard the correction
// re-fires on the next 4th scroll event — yanking the user back to the anchor
// whenever an unrelated programmatic scroll happens later (e.g. a quiz
// smooth-scrolling to its result box). Apply it at most once per page.
let hashNavApplied = false

export function addClassForNormalUser(attribute = 'data-acinb') {
  var startScrolling = 0
  function scrollEventAddClassHandler() {
    if (startScrolling < 5) {
      startScrolling++
    }
    if (startScrolling === 4) {
      document.removeEventListener('scroll', scrollEventAddClassHandler)
      ;[].forEach.call(
        document.querySelectorAll('[' + attribute + ']'),
        function (element) {
          var classToAddRaw = element.getAttribute(attribute)
          var classToAdd = classToAddRaw.split(' ')
          element.removeAttribute(attribute)
          element.classList.add(...classToAdd)
          if (classToAdd.includes('block')) {
            element.classList.remove('hidden')
          }
        },
      )
      // One-time hash navigation - delegate to ShowMore if available.
      if (!hashNavApplied && window.location.hash && window.ShowMore) {
        hashNavApplied = true
        window.ShowMore.scrollToHash(window.location.hash)
      }
    }
  }
  document.addEventListener('scroll', scrollEventAddClassHandler, { passive: true })
}
/**
 * Convert elements wich contain attribute (data-href) in normal link (a href)
 * You can use a callback function to decrypt the link (eg: rot13ToText ;-))
 *
 * @param {string}  attribute
 */
export async function uncloakLinks(
  attribute = 'data-rot',
  onClickMouseoverOrTouchstart = true,
) {
  var convertLink = function (element) {
    // fix "bug" with img
    if (element.getAttribute(attribute) === null) {
      element = element.closest('[' + attribute + ']')
    }
    if (element === null || element.getAttribute(attribute) === null) return
    var link = document.createElement('a')
    var href = element.getAttribute(attribute)
    element.removeAttribute(attribute)
    for (var i = 0, n = element.attributes.length; i < n; i++) {
      const attr = element.attributes[i]
      if (attr.nodeName.startsWith('@') || attr.nodeName.startsWith(':')) {
        console.log("You can't use @alpine.js attribute on", element)
        continue
      }
      link.setAttribute(attr.nodeName, attr.nodeValue)
    }
    link.innerHTML = element.innerHTML
    link.setAttribute('href', responsiveImage(convertShortchutForLink(rot13ToText(href))))
    element.replaceWith(link)
    return link
  }

  var convertAll = function (attribute) {
    ;[].forEach.call(
      document.querySelectorAll('[' + attribute + ']'),
      function (element) {
        convertLink(element)
      },
    )
  }

  var fireEventLinksBuilt = async function (element, event) {
    await document.dispatchEvent(new Event('DOMChanged'))

    var clickEvent = new Event(event.type)
    element.dispatchEvent(clickEvent)
  }

  var convertLinkOnEvent = async function (event) {
    // convert them all if it's an image (thanks this bug), permit to use gallery (baguetteBox)
    if (event.target.tagName == 'IMG') {
      await convertAll(attribute)
      var element = event.target
    } else {
      var element = convertLink(event.target)
    }
    if (element) fireEventLinksBuilt(element, event)
  }

  if (onClickMouseoverOrTouchstart) {
    ;[].forEach.call(
      document.querySelectorAll('[' + attribute + ']'),
      function (element) {
        element.addEventListener(
          'touchstart',
          function (e) {
            convertLinkOnEvent(e)
          },
          { once: true, passive: true },
        )
        element.addEventListener(
          'click',
          function (e) {
            convertLinkOnEvent(e)
          },
          { once: true },
        )
        element.addEventListener(
          'mouseover',
          function (e) {
            convertLinkOnEvent(e)
          },
          { once: true },
        )
      },
    )
  } else convertAll(attribute)
}

/**
 * Convert action attr encoded in rot 13 to normal action with default attr `data-frot`
 *
 * @param {string}  attribute
 */
export function convertFormFromRot13(attribute = 'data-frot') {
  ;[].forEach.call(document.querySelectorAll('[' + attribute + ']'), function (element) {
    var action = element.getAttribute(attribute)
    element.removeAttribute(attribute)
    element.setAttribute('action', convertShortchutForLink(rot13ToText(action)))
  })
}

export function convertShortchutForLink(str) {
  if (str.charAt(0) == '-') {
    return 'http://' + str.substring(1)
  }
  if (str.charAt(0) == '_') {
    return 'https://' + str.substring(1)
  }
  if (str.charAt(0) == '@') {
    return 'mailto:' + str.substring(1)
  }
  return str
}

/**
 * readableEmail(selector) Transform an email encoded with rot13 in a readable mail (and add mailto:)
 *
 * @param {string}  text
 */
export function readableEmail(selector) {
  document.querySelectorAll(selector).forEach(function (item) {
    var mail = rot13ToText(item.textContent).trim()
    item.classList.remove('hidden')
    item.innerHTML = '<a href="mailto:' + mail + '">' + mail + '</a>'
    if (selector.charAt(0) == '.') {
      item.classList.remove(selector.substring(1))
    }
  })
}

/**
 * Decode rot13
 *
 * @param {string}  str
 */
export function rot13ToText(str) {
  return str.replace(/[a-zA-Z]/g, function (c) {
    return String.fromCharCode(
      (c <= 'Z' ? 90 : 122) >= (c = c.charCodeAt(0) + 13) ? c : c - 26,
    )
  })
}

export function testWebPSupport() {
  var elem = document.createElement('canvas')

  if (elem.getContext && elem.getContext('2d')) {
    return elem.toDataURL('image/webp').indexOf('data:image/webp') == 0
  }

  return false
}

/**
 * Used in ThemeComponent
 */
export function convertImageLinkToWebPLink() {
  var switchToWebP = function () {
    ;[].forEach.call(document.querySelectorAll('a[data-dwl]'), function (element) {
      var href = responsiveImage(element.getAttribute('data-dwl'))
      element.setAttribute('href', href)
      element.removeAttribute('data-dwl')
    })
  }

  if (testWebPSupport()) switchToWebP()
}
