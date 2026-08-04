import Glightbox from 'glightbox'
import {
  uncloakLinks,
  resolveLightboxSources,
  addClassForNormalUser,
  readableEmail,
  convertImageLinkToWebPLink,
  replaceOn,
  liveBlock,
  convertFormFromRot13,
} from './helpers.js'
import { allClickable } from './clickable.js'
import { initShowMore } from './ShowMore.js'
import { restoreUnpublishedLinks } from './unpublishedLinks.js'
import { initVariantLinks } from './variantLinks.js'

//import { HorizontalScroll } from '@pushword/js-helper/src/horizontalScroll.js';
//window.HorizontalScroll = HorizontalScroll;

// Initialize ShowMore (exposes window.ShowMore and sets up event listeners)
initShowMore()

// Opt-in progressive enhancement for variant links (delegated, wired once).
initVariantLinks()

let lightbox
function onDomChanged() {
  liveBlock()
  // These three are ordered: the first consumes data-rot on lightbox nodes so
  // the second leaves them alone, and the third targets anchors, which only
  // exist once the second has un-cloaked them.
  resolveLightboxSources()
  uncloakLinks()
  convertImageLinkToWebPLink()
  readableEmail('.cea')
  replaceOn()
  if (lightbox) {
    lightbox.reload()
  }
  allClickable('.clickable')
  addClassForNormalUser()
  convertFormFromRot13()
  restoreUnpublishedLinks()
}

function onPageLoaded() {
  // Glightbox reads each node's config when it binds, so it must come after
  // the pass that gives the cloaked media spans their data-href.
  onDomChanged()
  lightbox = new Glightbox()
}

document.addEventListener('DOMContentLoaded', onPageLoaded)
document.addEventListener('DOMChanged', onDomChanged)
