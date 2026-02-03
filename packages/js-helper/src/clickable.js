import { uncloakLinks } from './helpers'

/**
 * transform an element containing a link (a href) or button in a clickable element
 *
 * @param {Object}  element
 */
export async function clickable(element) {
  var link = element.querySelector('a')

  if (!link && element.querySelector('span[data-rot]')) {
    await uncloakLinks('data-rot', false)
    link = element.querySelector('a')
  }

  if (link) {
    if (window.location.pathname.replace(/^\//, '') == link.pathname.replace(/^\//, '') && window.location.hostname == link.hostname) {
      if (typeof smoothScroll === 'function') {
        smoothScroll(link)
      }
      return false
    }
    window.location = link.getAttribute('href') === null ? '' : link.getAttribute('href')
    return false
  }

  var button = element.querySelector('button')
  if (button) {
    button.click()
    return false
  }

  return false
}

/**
 * allClickable(selector) transform all selected element in clickable element
 *
 * @param {string}  selector
 */
export function allClickable(selector) {
  document.querySelectorAll(selector).forEach(function (item) {
    item.addEventListener('click', function (event) {
      if (event.ctrlKey || event.metaKey) return
      clickable(item)
    })
  })
}

/**
 * smoothScroll(element)        Add a smooth effect during the scroll
 * Not working with IE but not worst than https://bundlephobia.com/result?p=smooth-scroll@15.0.0 ?
 *
 * @param {Object}  link
 */
export function smoothScroll(link, event = null) {
  if (location.pathname.replace(/^\//, '') == link.pathname.replace(/^\//, '') && location.hostname == link.hostname && link.hash != '') {
    var target = document.querySelector(link.hash)
    target = target !== null ? target : document.querySelector('[name=' + link.hash.slice(1) + ']')
    if (target !== null) {
      if (event !== null) event.preventDefault()
      window.scrollTo({
        behavior: 'smooth',
        left: 0,
        top: target.offsetTop,
      })
    }
  }
}
