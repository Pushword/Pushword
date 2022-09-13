require('fslightbox');
import {
    uncloakLinks,
    addClassForNormalUser,
    readableEmail,
    convertImageLinkToWebPLink,
    replaceOn,
    liveBlock,
} from '@pushword/js-helper/src/helpers.js';
import { allClickable } from '@pushword/js-helper/src/clickable.js';

function onDomChanged() {
    liveBlock();
    convertImageLinkToWebPLink();
    uncloakLinks();
    readableEmail('.cea');
    replaceOn();
    refreshFsLightbox();
    allClickable('.clickable');
    addClassForNormalUser();
}

function onPageLoaded() {
    onDomChanged();
    new FsLightbox();
}

document.addEventListener('DOMContentLoaded', onPageLoaded());
document.addEventListener('DOMChanged', onDomChanged);
