//import 'alpinejs';

require('fslightbox');

import {
  uncloakLinks,
  readableEmail,
  convertImageLinkToWebPLink,
  replaceOn,
  liveBlock,
} from '@pushword/js-helper/src/helpers.js';

function onPageLoaded() {
  onDomChanged();
  new FsLightbox();
}

function onDomChanged() {
  liveBlock();
  convertImageLinkToWebPLink();
  uncloakLinks();
  readableEmail('.cea');
  replaceOn();
  refreshFsLightbox();
  allClickable('.clickable');
}

document.addEventListener('DOMContentLoaded', onPageLoaded());

document.addEventListener('DOMChanged', onDomChanged);
