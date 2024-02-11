require('fslightbox')
import { uncloakLinks, addClassForNormalUser, readableEmail, convertImageLinkToWebPLink, replaceOn, liveBlock, convertFormFromRot13 } from './helpers.js'
import { allClickable } from './clickable.js'

//import { HorizontalScroll } from '@pushword/js-helper/src/horizontalScroll.js';
//window.HorizontalScroll = HorizontalScroll;

function onDomChanged() {
  liveBlock()
  convertImageLinkToWebPLink()
  uncloakLinks()
  readableEmail('.cea')
  replaceOn()
  refreshFsLightbox()
  allClickable('.clickable')
  addClassForNormalUser()
  convertFormFromRot13()
}

function onPageLoaded() {
  onDomChanged()
  new FsLightbox()
}

document.addEventListener('DOMContentLoaded', onPageLoaded())
document.addEventListener('DOMChanged', onDomChanged)
