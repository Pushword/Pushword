//import 'alpinejs';

require("fslightbox");

require("simple-jekyll-search");

import {
    uncloakLinks,
    readableEmail,
    convertImageLinkToWebPLink,
    replaceOn,
    liveBlock,
} from "@pushword/js-helper/src/helpers.js";

function onPageLoaded() {
    onDomChanged();
    new FsLightbox();
    SimpleJekyllSearch({
        searchInput: document.getElementById("search"),
        resultsContainer: document.getElementById("search-results"),
        json: "/search.json",
        searchResultTemplate:
            '<a href="{url}" class="block py-2 px-1 m-1 hover:bg-primary hover:text-white rounded"><span class="block">{title}</span><span class="text-xs font-light block mt-1">pushword.piedweb.com › {slug}</span></a>',
    });
}

function onDomChanged() {
    liveBlock();
    convertImageLinkToWebPLink();
    uncloakLinks();
    readableEmail(".cea");
    replaceOn();
    refreshFsLightbox();
}

document.addEventListener("DOMContentLoaded", onPageLoaded());

document.addEventListener("DOMChanged", onDomChanged);

function initHighlight() {
    document.querySelectorAll("pre code").forEach((block) => {
        hljs.highlightBlock(block);
    });
}
