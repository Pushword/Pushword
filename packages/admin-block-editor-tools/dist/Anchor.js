!function(e,t){if("object"==typeof exports&&"object"==typeof module)module.exports=t();else if("function"==typeof define&&define.amd)define([],t);else{var n=t();for(var r in n)("object"==typeof exports?exports:e)[r]=n[r]}}(self,(()=>(()=>{"use strict";var e={2049:(e,t,n)=>{n.d(t,{A:()=>c});var r=n(1601),o=n.n(r),a=n(6314),i=n.n(a)()(o());i.push([e.id,".cdx-anchor-tune-wrapper {\n  display: flex;\n  background: #f8f8f8;\n  border: 1px solid rgba(226, 226, 229, 0.2);\n  border-radius: 6px;\n  padding: 2px;\n  margin: 2px 0;\n}\n\n.cdx-anchor-tune-icon {\n  width: 14px;\n  height: 20px;\n  padding-top: 5px;\n  margin-left: 3px;\n  margin-right: 3px;\n  display: flex;\n}\n\n.cdx-anchor-tune-icon svg {\n  vertical-align: inherit;\n}\n\n.cdx-anchor-tune-input {\n  background-color: transparent;\n  border: 0;\n  height: 26px;\n  width: 100%;\n  max-width: calc(100% - 14px);\n  font-size: 14px;\n  box-sizing: border-box;\n  outline: none;\n}\n",""]);const c=i},6314:e=>{e.exports=function(e){var t=[];return t.toString=function(){return this.map((function(t){var n="",r=void 0!==t[5];return t[4]&&(n+="@supports (".concat(t[4],") {")),t[2]&&(n+="@media ".concat(t[2]," {")),r&&(n+="@layer".concat(t[5].length>0?" ".concat(t[5]):""," {")),n+=e(t),r&&(n+="}"),t[2]&&(n+="}"),t[4]&&(n+="}"),n})).join("")},t.i=function(e,n,r,o,a){"string"==typeof e&&(e=[[null,e,void 0]]);var i={};if(r)for(var c=0;c<this.length;c++){var s=this[c][0];null!=s&&(i[s]=!0)}for(var u=0;u<e.length;u++){var l=[].concat(e[u]);r&&i[l[0]]||(void 0!==a&&(void 0===l[5]||(l[1]="@layer".concat(l[5].length>0?" ".concat(l[5]):""," {").concat(l[1],"}")),l[5]=a),n&&(l[2]?(l[1]="@media ".concat(l[2]," {").concat(l[1],"}"),l[2]=n):l[2]=n),o&&(l[4]?(l[1]="@supports (".concat(l[4],") {").concat(l[1],"}"),l[4]=o):l[4]="".concat(o)),t.push(l))}},t}},1601:e=>{e.exports=function(e){return e[1]}},7990:(e,t,n)=>{var r=n(5072),o=n.n(r),a=n(7825),i=n.n(a),c=n(7659),s=n.n(c),u=n(5056),l=n.n(u),p=n(540),d=n.n(p),f=n(1113),v=n.n(f),h=n(2049),m={};m.styleTagTransform=v(),m.setAttributes=l(),m.insert=s().bind(null,"head"),m.domAPI=i(),m.insertStyleElement=d(),o()(h.A,m),h.A&&h.A.locals&&h.A.locals},5072:e=>{var t=[];function n(e){for(var n=-1,r=0;r<t.length;r++)if(t[r].identifier===e){n=r;break}return n}function r(e,r){for(var a={},i=[],c=0;c<e.length;c++){var s=e[c],u=r.base?s[0]+r.base:s[0],l=a[u]||0,p="".concat(u," ").concat(l);a[u]=l+1;var d=n(p),f={css:s[1],media:s[2],sourceMap:s[3],supports:s[4],layer:s[5]};if(-1!==d)t[d].references++,t[d].updater(f);else{var v=o(f,r);r.byIndex=c,t.splice(c,0,{identifier:p,updater:v,references:1})}i.push(p)}return i}function o(e,t){var n=t.domAPI(t);return n.update(e),function(t){if(t){if(t.css===e.css&&t.media===e.media&&t.sourceMap===e.sourceMap&&t.supports===e.supports&&t.layer===e.layer)return;n.update(e=t)}else n.remove()}}e.exports=function(e,o){var a=r(e=e||[],o=o||{});return function(e){e=e||[];for(var i=0;i<a.length;i++){var c=n(a[i]);t[c].references--}for(var s=r(e,o),u=0;u<a.length;u++){var l=n(a[u]);0===t[l].references&&(t[l].updater(),t.splice(l,1))}a=s}}},7659:e=>{var t={};e.exports=function(e,n){var r=function(e){if(void 0===t[e]){var n=document.querySelector(e);if(window.HTMLIFrameElement&&n instanceof window.HTMLIFrameElement)try{n=n.contentDocument.head}catch(e){n=null}t[e]=n}return t[e]}(e);if(!r)throw new Error("Couldn't find a style target. This probably means that the value for the 'insert' parameter is invalid.");r.appendChild(n)}},540:e=>{e.exports=function(e){var t=document.createElement("style");return e.setAttributes(t,e.attributes),e.insert(t,e.options),t}},5056:(e,t,n)=>{e.exports=function(e){var t=n.nc;t&&e.setAttribute("nonce",t)}},7825:e=>{e.exports=function(e){if("undefined"==typeof document)return{update:function(){},remove:function(){}};var t=e.insertStyleElement(e);return{update:function(n){!function(e,t,n){var r="";n.supports&&(r+="@supports (".concat(n.supports,") {")),n.media&&(r+="@media ".concat(n.media," {"));var o=void 0!==n.layer;o&&(r+="@layer".concat(n.layer.length>0?" ".concat(n.layer):""," {")),r+=n.css,o&&(r+="}"),n.media&&(r+="}"),n.supports&&(r+="}");var a=n.sourceMap;a&&"undefined"!=typeof btoa&&(r+="\n/*# sourceMappingURL=data:application/json;base64,".concat(btoa(unescape(encodeURIComponent(JSON.stringify(a))))," */")),t.styleTagTransform(r,e,t.options)}(t,e,n)},remove:function(){!function(e){if(null===e.parentNode)return!1;e.parentNode.removeChild(e)}(t)}}}},1113:e=>{e.exports=function(e,t){if(t.styleSheet)t.styleSheet.cssText=e;else{for(;t.firstChild;)t.removeChild(t.firstChild);t.appendChild(document.createTextNode(e))}}}},t={};function n(r){var o=t[r];if(void 0!==o)return o.exports;var a=t[r]={id:r,exports:{}};return e[r](a,a.exports,n),a.exports}n.n=e=>{var t=e&&e.__esModule?()=>e.default:()=>e;return n.d(t,{a:t}),t},n.d=(e,t)=>{for(var r in t)n.o(t,r)&&!n.o(e,r)&&Object.defineProperty(e,r,{enumerable:!0,get:t[r]})},n.o=(e,t)=>Object.prototype.hasOwnProperty.call(e,t),n.nc=void 0;var r={};function o(e){return o="function"==typeof Symbol&&"symbol"==typeof Symbol.iterator?function(e){return typeof e}:function(e){return e&&"function"==typeof Symbol&&e.constructor===Symbol&&e!==Symbol.prototype?"symbol":typeof e},o(e)}function a(e,t){for(var n=0;n<t.length;n++){var r=t[n];r.enumerable=r.enumerable||!1,r.configurable=!0,"value"in r&&(r.writable=!0),Object.defineProperty(e,i(r.key),r)}}function i(e){var t=function(e){if("object"!=o(e)||!e)return e;var t=e[Symbol.toPrimitive];if(void 0!==t){var n=t.call(e,"string");if("object"!=o(n))return n;throw new TypeError("@@toPrimitive must return a primitive value.")}return String(e)}(e);return"symbol"==o(t)?t:t+""}n.d(r,{default:()=>c}),n(7990).toString();var c=function(){return e=function e(t){var n=t.api,r=t.data,o=(t.config,t.block);!function(e,t){if(!(e instanceof t))throw new TypeError("Cannot call a class as a function")}(this,e),this.api=n,this.data=r||"",this.block=o,this._CSS={classWrapper:"cdx-anchor-tune-wrapper",classIcon:"cdx-anchor-tune-icon",classInput:"cdx-anchor-tune-input"}},t=[{key:"getAnchor",value:function(){return this.data||""}},{key:"render",value:function(){var e=this,t=arguments.length>0&&void 0!==arguments[0]?arguments[0]:null,n=document.createElement("div");n.classList.add(this._CSS.classWrapper);var r=document.createElement("div");r.classList.add(this._CSS.classIcon),r.innerHTML='<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" data-slot="icon" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 8.25h15m-16.5 7.5h15m-1.8-13.5-3.9 19.5m-2.1-19.5-3.9 19.5" /></svg>';var o=document.createElement("input");return o.placeholder=this.api.i18n.t("Anchor"),o.classList.add(this._CSS.classInput),o.value=t||this.getAnchor(),o.addEventListener("input",(function(t){var n,r=t.target.value.replace(/[^a-z0-9_-]/gi,"");r.length>0?e.data=r:e.data="",null===(n=e.block)||void 0===n||n.dispatchChange()})),this.input=o,n.appendChild(r),n.appendChild(o),n}},{key:"save",value:function(){return this.data}}],n=[{key:"isTune",get:function(){return!0}}],t&&a(e.prototype,t),n&&a(e,n),Object.defineProperty(e,"prototype",{writable:!1}),e;var e,t,n}();return r.default})()));