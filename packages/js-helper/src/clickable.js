/**
 * transform an element containing a link (a href) in a clickable element
 *
 * @param {Object}  element
 */
export function clickable(element) {
  if (!element.querySelector('a')) return false;
  var link = element.querySelectorAll('a')[0];
  if (
    window.location.pathname.replace(/^\//, '') ==
      link.pathname.replace(/^\//, '') &&
    window.location.hostname == link.hostname
  ) {
    if (typeof smoothScroll === 'function') {
      smoothScroll(link);
    }
    return false;
  }
  window.location =
    link.getAttribute('href') === null ? '' : link.getAttribute('href');
  return false;
}

/**
 * allClickable(selector) transform all selected element in clickable element
 *
 * @param {string}  selector
 */
export function allClickable(selector) {
  document.querySelectorAll(selector).forEach(function (item) {
    item.addEventListener('click', function () {
      clickable(item);
    });
  });
}

/**
 * smoothScroll(element)        Add a smooth effect during the scroll
 * Not working with IE but not worst than https://bundlephobia.com/result?p=smooth-scroll@15.0.0 ?
 *
 * @param {Object}  link
 */
export function smoothScroll(link, event = null) {
  if (
    location.pathname.replace(/^\//, '') == link.pathname.replace(/^\//, '') &&
    location.hostname == link.hostname &&
    link.hash != ''
  ) {
    var target = document.querySelector(link.hash);
    target =
      target !== null
        ? target
        : document.querySelector('[name=' + link.hash.slice(1) + ']');
    if (target !== null) {
      if (event !== null) event.preventDefault();
      window.scrollTo({
        behavior: 'smooth',
        left: 0,
        top: target.offsetTop,
      });
    }
  }
}
