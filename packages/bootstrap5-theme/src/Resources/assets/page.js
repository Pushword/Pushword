/**
 * Import CSS
 */
require('~/assets/main.scss');

/**
 * Create JS
 *
 * You can find two functions :
 * - onPageLoaded
 * - onDomLoaded
 * The second one is called each time we change something in the DOM (for example in getBlockFromSky)
 * // Todo: check if getBlockFromSky can call onDomLoaded
 */
//import BootstrapCookieConsent from "bootstrap-cookie-consent";

import baguetteBox from 'baguettebox.js';

var bsn = require('bootstrap.native/dist/bootstrap-native-v4');

import {
  getBlockFromSky,
  formToSky,
  uncloakLinks,
  responsiveImage,
  readableEmail,
  convertImageLinkToWebPLink,
} from '~/src/js-helper/src/helpers.js';

import {
  backgroundLazyLoad,
  applySmoothScroll,
  allClickable,
} from '~/node_modules/piedweb-tyrol-free-bootstrap-4-theme/src/js/helpers.js';

function onDomLoaded() {
  allClickable('.clickable');
  backgroundLazyLoad(function (src) {
    return responsiveImage(src);
  });
  uncloakLinks();
  baguetteBox.run('.mimg', {});
  addAClassOnScroll('.navbar', 'nostick', 50);
  readableEmail('.cea');
  applySmoothScroll();
  formToSky();
  getBlockFromSky();
  convertImageLinkToWebPLink();
  /**
    new BootstrapCookieConsent({
      services: ["StatistiquesAnonymes", "YouTube"],
      services_descr: {
        StatistiquesAnonymes:
          "Nous permet d'améliorer le site en fonction de son utilisation",
        YouTube: "Affiche les vidéos du service youtube.com"
      },
      method: "bsn"
    });
    /**/
}

function onDomChanged() {
  baguetteBox.run('.mimg', {});
  convertImageLinkToWebPLink();
}

document.addEventListener('DOMContentLoaded', onPageLoaded());

document.addEventListener('linksBuilt', onDomChanged);
