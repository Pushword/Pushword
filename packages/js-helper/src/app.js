import Glightbox from 'glightbox'
import {
  uncloakLinks,
  addClassForNormalUser,
  readableEmail,
  convertImageLinkToWebPLink,
  replaceOn,
  liveBlock,
  convertFormFromRot13,
} from './helpers.js'
import { allClickable } from './clickable.js'
import { initShowMore } from './ShowMore.js'

//import { HorizontalScroll } from '@pushword/js-helper/src/horizontalScroll.js';
//window.HorizontalScroll = HorizontalScroll;

// Initialize ShowMore (exposes window.ShowMore and sets up event listeners)
initShowMore()

let lightbox
function onDomChanged() {
  liveBlock()
  convertImageLinkToWebPLink()
  uncloakLinks()
  readableEmail('.cea')
  replaceOn()
  if (lightbox) {
    lightbox.reload()
  }
  allClickable('.clickable')
  addClassForNormalUser()
  convertFormFromRot13()
}

function onPageLoaded() {
  lightbox = new Glightbox()
  onDomChanged()
}

document.addEventListener('DOMContentLoaded', onPageLoaded)
document.addEventListener('DOMChanged', onDomChanged)
