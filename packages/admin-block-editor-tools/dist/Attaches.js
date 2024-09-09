!function(e,t){if("object"==typeof exports&&"object"==typeof module)module.exports=t();else if("function"==typeof define&&define.amd)define([],t);else{var n=t();for(var r in n)("object"==typeof exports?exports:e)[r]=n[r]}}(self,(()=>(()=>{var e={3850:e=>{window,e.exports=function(e){var t={};function n(r){if(t[r])return t[r].exports;var o=t[r]={i:r,l:!1,exports:{}};return e[r].call(o.exports,o,o.exports,n),o.l=!0,o.exports}return n.m=e,n.c=t,n.d=function(e,t,r){n.o(e,t)||Object.defineProperty(e,t,{enumerable:!0,get:r})},n.r=function(e){"undefined"!=typeof Symbol&&Symbol.toStringTag&&Object.defineProperty(e,Symbol.toStringTag,{value:"Module"}),Object.defineProperty(e,"__esModule",{value:!0})},n.t=function(e,t){if(1&t&&(e=n(e)),8&t)return e;if(4&t&&"object"==typeof e&&e&&e.__esModule)return e;var r=Object.create(null);if(n.r(r),Object.defineProperty(r,"default",{enumerable:!0,value:e}),2&t&&"string"!=typeof e)for(var o in e)n.d(r,o,function(t){return e[t]}.bind(null,o));return r},n.n=function(e){var t=e&&e.__esModule?function(){return e.default}:function(){return e};return n.d(t,"a",t),t},n.o=function(e,t){return Object.prototype.hasOwnProperty.call(e,t)},n.p="",n(n.s=3)}([function(e,t){var n;n=function(){return this}();try{n=n||new Function("return this")()}catch(e){"object"==typeof window&&(n=window)}e.exports=n},function(e,t,n){"use strict";(function(e){var r=n(2),o=setTimeout;function i(){}function a(e){if(!(this instanceof a))throw new TypeError("Promises must be constructed via new");if("function"!=typeof e)throw new TypeError("not a function");this._state=0,this._handled=!1,this._value=void 0,this._deferreds=[],f(e,this)}function s(e,t){for(;3===e._state;)e=e._value;0!==e._state?(e._handled=!0,a._immediateFn((function(){var n=1===e._state?t.onFulfilled:t.onRejected;if(null!==n){var r;try{r=n(e._value)}catch(e){return void l(t.promise,e)}c(t.promise,r)}else(1===e._state?c:l)(t.promise,e._value)}))):e._deferreds.push(t)}function c(e,t){try{if(t===e)throw new TypeError("A promise cannot be resolved with itself.");if(t&&("object"==typeof t||"function"==typeof t)){var n=t.then;if(t instanceof a)return e._state=3,e._value=t,void u(e);if("function"==typeof n)return void f((r=n,o=t,function(){r.apply(o,arguments)}),e)}e._state=1,e._value=t,u(e)}catch(t){l(e,t)}var r,o}function l(e,t){e._state=2,e._value=t,u(e)}function u(e){2===e._state&&0===e._deferreds.length&&a._immediateFn((function(){e._handled||a._unhandledRejectionFn(e._value)}));for(var t=0,n=e._deferreds.length;t<n;t++)s(e,e._deferreds[t]);e._deferreds=null}function d(e,t,n){this.onFulfilled="function"==typeof e?e:null,this.onRejected="function"==typeof t?t:null,this.promise=n}function f(e,t){var n=!1;try{e((function(e){n||(n=!0,c(t,e))}),(function(e){n||(n=!0,l(t,e))}))}catch(e){if(n)return;n=!0,l(t,e)}}a.prototype.catch=function(e){return this.then(null,e)},a.prototype.then=function(e,t){var n=new this.constructor(i);return s(this,new d(e,t,n)),n},a.prototype.finally=r.a,a.all=function(e){return new a((function(t,n){if(!e||void 0===e.length)throw new TypeError("Promise.all accepts an array");var r=Array.prototype.slice.call(e);if(0===r.length)return t([]);var o=r.length;function i(e,a){try{if(a&&("object"==typeof a||"function"==typeof a)){var s=a.then;if("function"==typeof s)return void s.call(a,(function(t){i(e,t)}),n)}r[e]=a,0==--o&&t(r)}catch(e){n(e)}}for(var a=0;a<r.length;a++)i(a,r[a])}))},a.resolve=function(e){return e&&"object"==typeof e&&e.constructor===a?e:new a((function(t){t(e)}))},a.reject=function(e){return new a((function(t,n){n(e)}))},a.race=function(e){return new a((function(t,n){for(var r=0,o=e.length;r<o;r++)e[r].then(t,n)}))},a._immediateFn="function"==typeof e&&function(t){e(t)}||function(e){o(e,0)},a._unhandledRejectionFn=function(e){"undefined"!=typeof console&&console&&console.warn("Possible Unhandled Promise Rejection:",e)},t.a=a}).call(this,n(5).setImmediate)},function(e,t,n){"use strict";t.a=function(e){var t=this.constructor;return this.then((function(n){return t.resolve(e()).then((function(){return n}))}),(function(n){return t.resolve(e()).then((function(){return t.reject(n)}))}))}},function(e,t,n){"use strict";function r(e){return(r="function"==typeof Symbol&&"symbol"==typeof Symbol.iterator?function(e){return typeof e}:function(e){return e&&"function"==typeof Symbol&&e.constructor===Symbol&&e!==Symbol.prototype?"symbol":typeof e})(e)}n(4);var o,i,a,s,c,l,u,d=n(8),f=(i=function(e){return new Promise((function(t,n){e=s(e),(e=c(e)).beforeSend&&e.beforeSend();var r=window.XMLHttpRequest?new window.XMLHttpRequest:new window.ActiveXObject("Microsoft.XMLHTTP");r.open(e.method,e.url),r.setRequestHeader("X-Requested-With","XMLHttpRequest"),Object.keys(e.headers).forEach((function(t){var n=e.headers[t];r.setRequestHeader(t,n)}));var o=e.ratio;r.upload.addEventListener("progress",(function(t){var n=Math.round(t.loaded/t.total*100),r=Math.ceil(n*o/100);e.progress(Math.min(r,100))}),!1),r.addEventListener("progress",(function(t){var n=Math.round(t.loaded/t.total*100),r=Math.ceil(n*(100-o)/100)+o;e.progress(Math.min(r,100))}),!1),r.onreadystatechange=function(){if(4===r.readyState){var e=r.response;try{e=JSON.parse(e)}catch(e){}var o=d.parseHeaders(r.getAllResponseHeaders()),i={body:e,code:r.status,headers:o};u(r.status)?t(i):n(i)}},r.send(e.data)}))},a=function(e){return e.method="POST",i(e)},s=function(){var e=arguments.length>0&&void 0!==arguments[0]?arguments[0]:{};if(e.url&&"string"!=typeof e.url)throw new Error("Url must be a string");if(e.url=e.url||"",e.method&&"string"!=typeof e.method)throw new Error("`method` must be a string or null");if(e.method=e.method?e.method.toUpperCase():"GET",e.headers&&"object"!==r(e.headers))throw new Error("`headers` must be an object or null");if(e.headers=e.headers||{},e.type&&("string"!=typeof e.type||!Object.values(o).includes(e.type)))throw new Error("`type` must be taken from module's «contentType» library");if(e.progress&&"function"!=typeof e.progress)throw new Error("`progress` must be a function or null");if(e.progress=e.progress||function(e){},e.beforeSend=e.beforeSend||function(e){},e.ratio&&"number"!=typeof e.ratio)throw new Error("`ratio` must be a number");if(e.ratio<0||e.ratio>100)throw new Error("`ratio` must be in a 0-100 interval");if(e.ratio=e.ratio||90,e.accept&&"string"!=typeof e.accept)throw new Error("`accept` must be a string with a list of allowed mime-types");if(e.accept=e.accept||"*/*",e.multiple&&"boolean"!=typeof e.multiple)throw new Error("`multiple` must be a true or false");if(e.multiple=e.multiple||!1,e.fieldName&&"string"!=typeof e.fieldName)throw new Error("`fieldName` must be a string");return e.fieldName=e.fieldName||"files",e},c=function(e){switch(e.method){case"GET":var t=l(e.data,o.URLENCODED);delete e.data,e.url=/\?/.test(e.url)?e.url+"&"+t:e.url+"?"+t;break;case"POST":case"PUT":case"DELETE":case"UPDATE":var n=function(){return(arguments.length>0&&void 0!==arguments[0]?arguments[0]:{}).type||o.JSON}(e);(d.isFormData(e.data)||d.isFormElement(e.data))&&(n=o.FORM),e.data=l(e.data,n),n!==f.contentType.FORM&&(e.headers["content-type"]=n)}return e},l=function(){var e=arguments.length>0&&void 0!==arguments[0]?arguments[0]:{};switch(arguments.length>1?arguments[1]:void 0){case o.URLENCODED:return d.urlEncode(e);case o.JSON:return d.jsonEncode(e);case o.FORM:return d.formEncode(e);default:return e}},u=function(e){return e>=200&&e<300},{contentType:o={URLENCODED:"application/x-www-form-urlencoded; charset=utf-8",FORM:"multipart/form-data",JSON:"application/json; charset=utf-8"},request:i,get:function(e){return e.method="GET",i(e)},post:a,transport:function(e){return e=s(e),d.selectFiles(e).then((function(t){for(var n=new FormData,r=0;r<t.length;r++)n.append(e.fieldName,t[r],t[r].name);d.isObject(e.data)&&Object.keys(e.data).forEach((function(t){var r=e.data[t];n.append(t,r)}));var o=e.beforeSend;return e.beforeSend=function(){return o(t)},e.data=n,a(e)}))},selectFiles:function(e){return delete(e=s(e)).beforeSend,d.selectFiles(e)}});e.exports=f},function(e,t,n){"use strict";n.r(t);var r=n(1);window.Promise=window.Promise||r.a},function(e,t,n){(function(e){var r=void 0!==e&&e||"undefined"!=typeof self&&self||window,o=Function.prototype.apply;function i(e,t){this._id=e,this._clearFn=t}t.setTimeout=function(){return new i(o.call(setTimeout,r,arguments),clearTimeout)},t.setInterval=function(){return new i(o.call(setInterval,r,arguments),clearInterval)},t.clearTimeout=t.clearInterval=function(e){e&&e.close()},i.prototype.unref=i.prototype.ref=function(){},i.prototype.close=function(){this._clearFn.call(r,this._id)},t.enroll=function(e,t){clearTimeout(e._idleTimeoutId),e._idleTimeout=t},t.unenroll=function(e){clearTimeout(e._idleTimeoutId),e._idleTimeout=-1},t._unrefActive=t.active=function(e){clearTimeout(e._idleTimeoutId);var t=e._idleTimeout;t>=0&&(e._idleTimeoutId=setTimeout((function(){e._onTimeout&&e._onTimeout()}),t))},n(6),t.setImmediate="undefined"!=typeof self&&self.setImmediate||void 0!==e&&e.setImmediate||this&&this.setImmediate,t.clearImmediate="undefined"!=typeof self&&self.clearImmediate||void 0!==e&&e.clearImmediate||this&&this.clearImmediate}).call(this,n(0))},function(e,t,n){(function(e,t){!function(e){"use strict";if(!e.setImmediate){var n,r,o,i,a,s=1,c={},l=!1,u=e.document,d=Object.getPrototypeOf&&Object.getPrototypeOf(e);d=d&&d.setTimeout?d:e,"[object process]"==={}.toString.call(e.process)?n=function(e){t.nextTick((function(){p(e)}))}:function(){if(e.postMessage&&!e.importScripts){var t=!0,n=e.onmessage;return e.onmessage=function(){t=!1},e.postMessage("","*"),e.onmessage=n,t}}()?(i="setImmediate$"+Math.random()+"$",a=function(t){t.source===e&&"string"==typeof t.data&&0===t.data.indexOf(i)&&p(+t.data.slice(i.length))},e.addEventListener?e.addEventListener("message",a,!1):e.attachEvent("onmessage",a),n=function(t){e.postMessage(i+t,"*")}):e.MessageChannel?((o=new MessageChannel).port1.onmessage=function(e){p(e.data)},n=function(e){o.port2.postMessage(e)}):u&&"onreadystatechange"in u.createElement("script")?(r=u.documentElement,n=function(e){var t=u.createElement("script");t.onreadystatechange=function(){p(e),t.onreadystatechange=null,r.removeChild(t),t=null},r.appendChild(t)}):n=function(e){setTimeout(p,0,e)},d.setImmediate=function(e){"function"!=typeof e&&(e=new Function(""+e));for(var t=new Array(arguments.length-1),r=0;r<t.length;r++)t[r]=arguments[r+1];var o={callback:e,args:t};return c[s]=o,n(s),s++},d.clearImmediate=f}function f(e){delete c[e]}function p(e){if(l)setTimeout(p,0,e);else{var t=c[e];if(t){l=!0;try{!function(e){var t=e.callback,n=e.args;switch(n.length){case 0:t();break;case 1:t(n[0]);break;case 2:t(n[0],n[1]);break;case 3:t(n[0],n[1],n[2]);break;default:t.apply(undefined,n)}}(t)}finally{f(e),l=!1}}}}}("undefined"==typeof self?void 0===e?this:e:self)}).call(this,n(0),n(7))},function(e,t){var n,r,o=e.exports={};function i(){throw new Error("setTimeout has not been defined")}function a(){throw new Error("clearTimeout has not been defined")}function s(e){if(n===setTimeout)return setTimeout(e,0);if((n===i||!n)&&setTimeout)return n=setTimeout,setTimeout(e,0);try{return n(e,0)}catch(t){try{return n.call(null,e,0)}catch(t){return n.call(this,e,0)}}}!function(){try{n="function"==typeof setTimeout?setTimeout:i}catch(e){n=i}try{r="function"==typeof clearTimeout?clearTimeout:a}catch(e){r=a}}();var c,l=[],u=!1,d=-1;function f(){u&&c&&(u=!1,c.length?l=c.concat(l):d=-1,l.length&&p())}function p(){if(!u){var e=s(f);u=!0;for(var t=l.length;t;){for(c=l,l=[];++d<t;)c&&c[d].run();d=-1,t=l.length}c=null,u=!1,function(e){if(r===clearTimeout)return clearTimeout(e);if((r===a||!r)&&clearTimeout)return r=clearTimeout,clearTimeout(e);try{r(e)}catch(t){try{return r.call(null,e)}catch(t){return r.call(this,e)}}}(e)}}function h(e,t){this.fun=e,this.array=t}function m(){}o.nextTick=function(e){var t=new Array(arguments.length-1);if(arguments.length>1)for(var n=1;n<arguments.length;n++)t[n-1]=arguments[n];l.push(new h(e,t)),1!==l.length||u||s(p)},h.prototype.run=function(){this.fun.apply(null,this.array)},o.title="browser",o.browser=!0,o.env={},o.argv=[],o.version="",o.versions={},o.on=m,o.addListener=m,o.once=m,o.off=m,o.removeListener=m,o.removeAllListeners=m,o.emit=m,o.prependListener=m,o.prependOnceListener=m,o.listeners=function(e){return[]},o.binding=function(e){throw new Error("process.binding is not supported")},o.cwd=function(){return"/"},o.chdir=function(e){throw new Error("process.chdir is not supported")},o.umask=function(){return 0}},function(e,t,n){function r(e,t){for(var n=0;n<t.length;n++){var r=t[n];r.enumerable=r.enumerable||!1,r.configurable=!0,"value"in r&&(r.writable=!0),Object.defineProperty(e,r.key,r)}}var o=n(9);e.exports=function(){function e(){!function(e,t){if(!(e instanceof t))throw new TypeError("Cannot call a class as a function")}(this,e)}var t,n;return t=e,n=[{key:"urlEncode",value:function(e){return o(e)}},{key:"jsonEncode",value:function(e){return JSON.stringify(e)}},{key:"formEncode",value:function(e){if(this.isFormData(e))return e;if(this.isFormElement(e))return new FormData(e);if(this.isObject(e)){var t=new FormData;return Object.keys(e).forEach((function(n){var r=e[n];t.append(n,r)})),t}throw new Error("`data` must be an instance of Object, FormData or <FORM> HTMLElement")}},{key:"isObject",value:function(e){return"[object Object]"===Object.prototype.toString.call(e)}},{key:"isFormData",value:function(e){return e instanceof FormData}},{key:"isFormElement",value:function(e){return e instanceof HTMLFormElement}},{key:"selectFiles",value:function(){var e=arguments.length>0&&void 0!==arguments[0]?arguments[0]:{};return new Promise((function(t,n){var r=document.createElement("INPUT");r.type="file",e.multiple&&r.setAttribute("multiple","multiple"),e.accept&&r.setAttribute("accept",e.accept),r.style.display="none",document.body.appendChild(r),r.addEventListener("change",(function(e){var n=e.target.files;t(n),document.body.removeChild(r)}),!1),r.click()}))}},{key:"parseHeaders",value:function(e){var t=e.trim().split(/[\r\n]+/),n={};return t.forEach((function(e){var t=e.split(": "),r=t.shift(),o=t.join(": ");r&&(n[r]=o)})),n}}],null&&r(t.prototype,null),n&&r(t,n),e}()},function(e,t){var n=function(e){return encodeURIComponent(e).replace(/[!'()*]/g,escape).replace(/%20/g,"+")},r=function(e,t,o,i){return t=t||null,o=o||"&",i=i||null,e?function(e){for(var t=new Array,n=0;n<e.length;n++)e[n]&&t.push(e[n]);return t}(Object.keys(e).map((function(a){var s,c,l=a;if(i&&(l=i+"["+l+"]"),"object"==typeof e[a]&&null!==e[a])s=r(e[a],null,o,l);else{t&&(c=l,l=!isNaN(parseFloat(c))&&isFinite(c)?t+Number(l):l);var u=e[a];u=(u=0===(u=!1===(u=!0===u?"1":u)?"0":u)?"0":u)||"",s=n(l)+"="+n(u)}return s}))).join(o).replace(/[!'()*]/g,""):""};e.exports=r}])},304:(e,t,n)=>{"use strict";n.d(t,{A:()=>f});var r=n(1601),o=n.n(r),i=n(6314),a=n.n(i),s=n(4417),c=n.n(s),l=new URL(n(8074),n.b),u=a()(o()),d=c()(l);u.push([e.id,`.cdx-attaches {\n  --color-line: #EFF0F1;\n  --color-bg: #fff;\n  --color-bg-secondary: #F8F8F8;\n  --color-bg-secondary--hover: #f2f2f2;\n  --color-text-secondary: #707684;\n}\n\n  .cdx-attaches--with-file {\n    display: flex;\n    align-items: center;\n    padding: 10px 12px;\n    border: 1px solid var(--color-line);\n    border-radius: 7px;\n    background: var(--color-bg);\n  }\n\n  .cdx-attaches--with-file .cdx-attaches__file-info {\n      display: grid;\n      grid-gap: 4px;\n      max-width: calc(100% - 80px);\n      margin: auto 0;\n      flex-grow: 2;\n    }\n\n  .cdx-attaches--with-file .cdx-attaches__download-button {\n      display: flex;\n      align-items: center;\n      background: var(--color-bg-secondary);\n      padding: 6px;\n      border-radius: 6px;\n      margin: auto 0 auto auto;\n    }\n\n  .cdx-attaches--with-file .cdx-attaches__download-button:hover {\n        background: var(--color-bg-secondary--hover);\n      }\n\n  .cdx-attaches--with-file .cdx-attaches__download-button svg {\n        width: 20px;\n        height: 20px;\n        fill: none;\n      }\n\n  .cdx-attaches--with-file .cdx-attaches__file-icon {\n      position: relative;\n    }\n\n  .cdx-attaches--with-file .cdx-attaches__file-icon-background {\n        background-color: #333;\n\n        width: 27px;\n        height: 30px;\n        margin-right: 12px;\n        border-radius: 8px;\n        display: flex;\n        align-items: center;\n        justify-content: center;\n      }\n\n  @supports(-webkit-mask-box-image: url('')){\n\n  .cdx-attaches--with-file .cdx-attaches__file-icon-background {\n          border-radius: 0;\n          -webkit-mask-box-image: url(${d}) 48% 41% 37.9% 53.3%\n      };\n        }\n\n  .cdx-attaches--with-file .cdx-attaches__file-icon-label {\n        position: absolute;\n        left: 3px;\n        top: 11px;\n        background: inherit;\n        text-transform: uppercase;\n        line-height: 1em;\n        color: #fff;\n        padding: 1px 2px;\n        border-radius: 3px;\n        font-size: 10px;\n        font-weight: bold;\n        /* box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.22); */\n        font-family: ui-monospace,SFMono-Regular,SF Mono,Menlo,Consolas,Liberation Mono,monospace;\n        letter-spacing: 0.02em;\n      }\n\n  .cdx-attaches--with-file .cdx-attaches__file-icon svg {\n        width: 20px;\n        height: 20px;\n      }\n\n  .cdx-attaches--with-file .cdx-attaches__file-icon path {\n        stroke: #fff;\n      }\n\n  .cdx-attaches--with-file .cdx-attaches__size {\n      color: var(--color-text-secondary);\n      font-size: 12px;\n      line-height: 1em;\n    }\n\n  .cdx-attaches--with-file .cdx-attaches__size::after {\n        content: attr(data-size);\n        margin-left: 0.2em;\n      }\n\n  .cdx-attaches--with-file .cdx-attaches__title {\n      white-space: nowrap;\n      text-overflow: ellipsis;\n      overflow: hidden;\n      outline: none;\n      max-width: 90%;\n      font-size: 14px;\n      font-weight: 500;\n      line-height: 1em;\n    }\n\n  .cdx-attaches--with-file .cdx-attaches__title:empty::before {\n      content: attr(data-placeholder);\n      color: #7b7e89;\n    }\n\n  .cdx-attaches--loading .cdx-attaches__title,\n    .cdx-attaches--loading .cdx-attaches__file-icon,\n    .cdx-attaches--loading .cdx-attaches__size,\n    .cdx-attaches--loading .cdx-attaches__download-button,\n    .cdx-attaches--loading .cdx-attaches__button {\n      opacity: 0;\n      font-size: 0;\n    }\n\n  .cdx-attaches__button {\n    display: flex;\n    align-items: center;\n    justify-content: center;\n    color: #000;\n    border-radius: 7px;\n    font-weight: 500;\n  }\n\n  .cdx-attaches__button svg {\n      margin-top: 0;\n    }\n`,""]);const f=u},2612:(e,t,n)=>{"use strict";n.d(t,{A:()=>f});var r=n(1601),o=n.n(r),i=n(6314),a=n.n(i),s=n(4417),c=n.n(s),l=new URL(n(2012),n.b),u=a()(o()),d=c()(l);u.push([e.id,`.cdx-input-labeled-attaches-file {\n    background-image: url(${d});\n}\n`,""]);const f=u},6314:e=>{"use strict";e.exports=function(e){var t=[];return t.toString=function(){return this.map((function(t){var n="",r=void 0!==t[5];return t[4]&&(n+="@supports (".concat(t[4],") {")),t[2]&&(n+="@media ".concat(t[2]," {")),r&&(n+="@layer".concat(t[5].length>0?" ".concat(t[5]):""," {")),n+=e(t),r&&(n+="}"),t[2]&&(n+="}"),t[4]&&(n+="}"),n})).join("")},t.i=function(e,n,r,o,i){"string"==typeof e&&(e=[[null,e,void 0]]);var a={};if(r)for(var s=0;s<this.length;s++){var c=this[s][0];null!=c&&(a[c]=!0)}for(var l=0;l<e.length;l++){var u=[].concat(e[l]);r&&a[u[0]]||(void 0!==i&&(void 0===u[5]||(u[1]="@layer".concat(u[5].length>0?" ".concat(u[5]):""," {").concat(u[1],"}")),u[5]=i),n&&(u[2]?(u[1]="@media ".concat(u[2]," {").concat(u[1],"}"),u[2]=n):u[2]=n),o&&(u[4]?(u[1]="@supports (".concat(u[4],") {").concat(u[1],"}"),u[4]=o):u[4]="".concat(o)),t.push(u))}},t}},4417:e=>{"use strict";e.exports=function(e,t){return t||(t={}),e?(e=String(e.__esModule?e.default:e),/^['"].*['"]$/.test(e)&&(e=e.slice(1,-1)),t.hash&&(e+=t.hash),/["'() \t\n]|(%20)/.test(e)||t.needQuotes?'"'.concat(e.replace(/"/g,'\\"').replace(/\n/g,"\\n"),'"'):e):e}},1601:e=>{"use strict";e.exports=function(e){return e[1]}},5072:e=>{"use strict";var t=[];function n(e){for(var n=-1,r=0;r<t.length;r++)if(t[r].identifier===e){n=r;break}return n}function r(e,r){for(var i={},a=[],s=0;s<e.length;s++){var c=e[s],l=r.base?c[0]+r.base:c[0],u=i[l]||0,d="".concat(l," ").concat(u);i[l]=u+1;var f=n(d),p={css:c[1],media:c[2],sourceMap:c[3],supports:c[4],layer:c[5]};if(-1!==f)t[f].references++,t[f].updater(p);else{var h=o(p,r);r.byIndex=s,t.splice(s,0,{identifier:d,updater:h,references:1})}a.push(d)}return a}function o(e,t){var n=t.domAPI(t);return n.update(e),function(t){if(t){if(t.css===e.css&&t.media===e.media&&t.sourceMap===e.sourceMap&&t.supports===e.supports&&t.layer===e.layer)return;n.update(e=t)}else n.remove()}}e.exports=function(e,o){var i=r(e=e||[],o=o||{});return function(e){e=e||[];for(var a=0;a<i.length;a++){var s=n(i[a]);t[s].references--}for(var c=r(e,o),l=0;l<i.length;l++){var u=n(i[l]);0===t[u].references&&(t[u].updater(),t.splice(u,1))}i=c}}},7659:e=>{"use strict";var t={};e.exports=function(e,n){var r=function(e){if(void 0===t[e]){var n=document.querySelector(e);if(window.HTMLIFrameElement&&n instanceof window.HTMLIFrameElement)try{n=n.contentDocument.head}catch(e){n=null}t[e]=n}return t[e]}(e);if(!r)throw new Error("Couldn't find a style target. This probably means that the value for the 'insert' parameter is invalid.");r.appendChild(n)}},540:e=>{"use strict";e.exports=function(e){var t=document.createElement("style");return e.setAttributes(t,e.attributes),e.insert(t,e.options),t}},5056:(e,t,n)=>{"use strict";e.exports=function(e){var t=n.nc;t&&e.setAttribute("nonce",t)}},7825:e=>{"use strict";e.exports=function(e){if("undefined"==typeof document)return{update:function(){},remove:function(){}};var t=e.insertStyleElement(e);return{update:function(n){!function(e,t,n){var r="";n.supports&&(r+="@supports (".concat(n.supports,") {")),n.media&&(r+="@media ".concat(n.media," {"));var o=void 0!==n.layer;o&&(r+="@layer".concat(n.layer.length>0?" ".concat(n.layer):""," {")),r+=n.css,o&&(r+="}"),n.media&&(r+="}"),n.supports&&(r+="}");var i=n.sourceMap;i&&"undefined"!=typeof btoa&&(r+="\n/*# sourceMappingURL=data:application/json;base64,".concat(btoa(unescape(encodeURIComponent(JSON.stringify(i))))," */")),t.styleTagTransform(r,e,t.options)}(t,e,n)},remove:function(){!function(e){if(null===e.parentNode)return!1;e.parentNode.removeChild(e)}(t)}}}},1113:e=>{"use strict";e.exports=function(e,t){if(t.styleSheet)t.styleSheet.cssText=e;else{for(;t.firstChild;)t.removeChild(t.firstChild);t.appendChild(document.createTextNode(e))}}},8074:e=>{"use strict";e.exports="data:image/svg+xml,%3Csvg width=%2724%27 height=%2724%27 viewBox=%270 0 24 24%27 fill=%27none%27 xmlns=%27http://www.w3.org/2000/svg%27%3E%3Cpath d=%27M0 10.3872C0 1.83334 1.83334 0 10.3872 0H13.6128C22.1667 0 24 1.83334 24 10.3872V13.6128C24 22.1667 22.1667 24 13.6128 24H10.3872C1.83334 24 0 22.1667 0 13.6128V10.3872Z%27 fill=%27black%27/%3E%3C/svg%3E%0A"},2012:e=>{"use strict";e.exports='data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-file-earmark-arrow-down" viewBox="0 0 16 16" fill="%23707684"><path d="M8.5 6.5a.5.5 0 0 0-1 0v3.793L6.354 9.146a.5.5 0 1 0-.708.708l2 2a.5.5 0 0 0 .708 0l2-2a.5.5 0 0 0-.708-.708L8.5 10.293V6.5z"/><path d="M14 14V4.5L9.5 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2zM9.5 3A1.5 1.5 0 0 0 11 4.5h2V14a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h5.5v2z"/></svg>'}},t={};function n(r){var o=t[r];if(void 0!==o)return o.exports;var i=t[r]={id:r,exports:{}};return e[r](i,i.exports,n),i.exports}n.m=e,n.n=e=>{var t=e&&e.__esModule?()=>e.default:()=>e;return n.d(t,{a:t}),t},n.d=(e,t)=>{for(var r in t)n.o(t,r)&&!n.o(e,r)&&Object.defineProperty(e,r,{enumerable:!0,get:t[r]})},n.o=(e,t)=>Object.prototype.hasOwnProperty.call(e,t),n.b=document.baseURI||self.location.href,n.nc=void 0;var r={};return(()=>{"use strict";n.d(r,{default:()=>D});var e=n(5072),t=n.n(e),o=n(7825),i=n.n(o),a=n(7659),s=n.n(a),c=n(5056),l=n.n(c),u=n(540),d=n.n(u),f=n(1113),p=n.n(f),h=n(304),m={};m.styleTagTransform=p(),m.setAttributes=l(),m.insert=s().bind(null,"head"),m.domAPI=i(),m.insertStyleElement=d(),t()(h.A,m),h.A&&h.A.locals&&h.A.locals;var v=n(3850),y=n.n(v);class g{constructor({config:e,onUpload:t,onError:n}){this.config=e,this.onUpload=t,this.onError=n}uploadSelectedFile({onPreview:e}){let t;t=this.config.uploader&&"function"==typeof this.config.uploader.uploadByFile?y().selectFiles({accept:this.config.types}).then((t=>{e();const n=this.config.uploader.uploadByFile(t[0]);var r;return(r=n)&&"function"==typeof r.then||console.warn("Custom uploader method uploadByFile should return a Promise"),n})):y().transport({url:this.config.endpoint,accept:this.config.types,beforeSend:()=>e(),fieldName:this.config.field,headers:this.config.additionalRequestHeaders||{}}).then((e=>e.body)),t.then((e=>{this.onUpload(e)})).catch((e=>{const t=e.body,n=t&&t.message?t.message:this.config.errorMessage;this.onError(n)}))}}function b(e,t=null,n={}){const r=document.createElement(e);Array.isArray(t)?r.classList.add(...t):t&&r.classList.add(t);for(const e in n)r[e]=n[e];return r}function w(e){return 0===Object.keys(e).length}const x='<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.3236 8.43554L9.49533 12.1908C9.13119 12.5505 8.93118 13.043 8.9393 13.5598C8.94741 14.0767 9.163 14.5757 9.53862 14.947C9.91424 15.3182 10.4191 15.5314 10.9422 15.5397C11.4653 15.5479 11.9637 15.3504 12.3279 14.9908L16.1562 11.2355C16.8845 10.5161 17.2845 9.53123 17.2682 8.4975C17.252 7.46376 16.8208 6.46583 16.0696 5.72324C15.3184 4.98066 14.3086 4.55425 13.2624 4.53782C12.2162 4.52138 11.2193 4.91627 10.4911 5.63562L6.66277 9.39093C5.57035 10.4699 4.97032 11.9473 4.99467 13.4979C5.01903 15.0485 5.66578 16.5454 6.79264 17.6592C7.9195 18.7731 9.43417 19.4127 11.0034 19.4374C12.5727 19.462 14.068 18.8697 15.1604 17.7907L18.9887 14.0354"/></svg>';class _{constructor({data:e,config:t,api:n,readOnly:r}){this.api=n,this.readOnly=r,this.nodes={wrapper:null,button:null,title:null},this._data={file:{},title:""},this.config={endpoint:t.endpoint||"",field:t.field||"file",types:t.types||"*",buttonText:t.buttonText||"Select file to upload",errorMessage:t.errorMessage||"File upload failed",uploader:t.uploader||void 0,additionalRequestHeaders:t.additionalRequestHeaders||{}},void 0===e||w(e)||(this.data=e),this.uploader=new g({config:this.config,onUpload:e=>this.onUpload(e),onError:e=>this.uploadingFailed(e)}),this.enableFileUpload=this.enableFileUpload.bind(this)}static get toolbox(){return{icon:x,title:"Attachment"}}static get isReadOnlySupported(){return!0}get CSS(){return{baseClass:this.api.styles.block,apiButton:this.api.styles.button,loader:this.api.styles.loader,wrapper:"cdx-attaches",wrapperWithFile:"cdx-attaches--with-file",wrapperLoading:"cdx-attaches--loading",button:"cdx-attaches__button",title:"cdx-attaches__title",size:"cdx-attaches__size",downloadButton:"cdx-attaches__download-button",fileInfo:"cdx-attaches__file-info",fileIcon:"cdx-attaches__file-icon",fileIconBackground:"cdx-attaches__file-icon-background",fileIconLabel:"cdx-attaches__file-icon-label"}}get EXTENSIONS(){return{doc:"#1483E9",docx:"#1483E9",odt:"#1483E9",pdf:"#DB2F2F",rtf:"#744FDC",tex:"#5a5a5b",txt:"#5a5a5b",pptx:"#E35200",ppt:"#E35200",mp3:"#eab456",mp4:"#f676a6",xls:"#11AE3D",html:"#2988f0",htm:"#2988f0",png:"#AA2284",jpg:"#D13359",jpeg:"#D13359",gif:"#f6af76",zip:"#4f566f",rar:"#4f566f",exe:"#e26f6f",svg:"#bf5252",key:"#00B2FF",sketch:"#FFC700",ai:"#FB601D",psd:"#388ae5",dmg:"#e26f6f",json:"#2988f0",csv:"#11AE3D"}}validate(e){return!w(e.file)}save(e){if(this.pluginHasData()){const t=e.querySelector(`.${this.CSS.title}`);t&&Object.assign(this.data,{title:t.innerHTML})}return this.data}render(){const e=b("div",this.CSS.baseClass);return this.nodes.wrapper=b("div",this.CSS.wrapper),this.pluginHasData()?this.showFileData():this.prepareUploadButton(),e.appendChild(this.nodes.wrapper),e}prepareUploadButton(){this.nodes.button=b("div",[this.CSS.apiButton,this.CSS.button]),this.nodes.button.innerHTML=`${x} ${this.config.buttonText}`,this.readOnly||this.nodes.button.addEventListener("click",this.enableFileUpload),this.nodes.wrapper.appendChild(this.nodes.button)}appendCallback(){this.nodes.button.click()}pluginHasData(){return""!==this.data.title||Object.values(this.data.file).some((e=>void 0!==e))}enableFileUpload(){this.uploader.uploadSelectedFile({onPreview:()=>{this.nodes.wrapper.classList.add(this.CSS.wrapperLoading,this.CSS.loader)}})}onUpload(e){const t=e;try{t.success&&void 0!==t.file&&!w(t.file)?(this.data={file:t.file,title:t.file.title||""},this.nodes.button.remove(),this.showFileData(),function(e){const t=document.createRange(),n=window.getSelection();t.selectNodeContents(e),t.collapse(!1),n.removeAllRanges(),n.addRange(t)}(this.nodes.title),this.removeLoader()):this.uploadingFailed(this.config.errorMessage)}catch(e){console.error("Attaches tool error:",e),this.uploadingFailed(this.config.errorMessage)}this.api.blocks.getBlockByIndex(this.api.blocks.getCurrentBlockIndex()).dispatchChange()}appendFileIcon(e){const t=e.extension||(void 0===(n=e.name)?"":n.split(".").pop());var n;const r=this.EXTENSIONS[t],o=b("div",this.CSS.fileIcon),i=b("div",this.CSS.fileIconBackground);if(r&&(i.style.backgroundColor=r),o.appendChild(i),t){let e=t;t.length>4&&(e=t.substring(0,4)+"…");const n=b("div",this.CSS.fileIconLabel,{textContent:e,title:t});r&&(n.style.backgroundColor=r),o.appendChild(n)}else i.innerHTML=x;this.nodes.wrapper.appendChild(o)}removeLoader(){setTimeout((()=>this.nodes.wrapper.classList.remove(this.CSS.wrapperLoading,this.CSS.loader)),500)}showFileData(){this.nodes.wrapper.classList.add(this.CSS.wrapperWithFile);const{file:e,title:t}=this.data;this.appendFileIcon(e);const n=b("div",this.CSS.fileInfo);if(this.nodes.title=b("div",this.CSS.title,{contentEditable:!1===this.readOnly}),this.nodes.title.dataset.placeholder=this.api.i18n.t("File title"),this.nodes.title.textContent=t||"",n.appendChild(this.nodes.title),e.size){let t,r;const o=b("div",this.CSS.size);Math.log10(+e.size)>=6?(t="MiB",r=e.size/Math.pow(2,20)):(t="KiB",r=e.size/Math.pow(2,10)),o.textContent=r.toFixed(1),o.setAttribute("data-size",t),n.appendChild(o)}if(this.nodes.wrapper.appendChild(n),void 0!==e.url){const t=b("a",this.CSS.downloadButton,{innerHTML:'<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M7 10L11.8586 14.8586C11.9367 14.9367 12.0633 14.9367 12.1414 14.8586L17 10"/></svg>',href:e.url,target:"_blank",rel:"nofollow noindex noreferrer"});this.nodes.wrapper.appendChild(t)}}uploadingFailed(e){this.api.notifier.show({message:e,style:"error"}),this.removeLoader()}get data(){return this._data}set data({file:e,title:t}){this._data={file:e,title:t}}}var S=n(2612),E={};function C(e){return C="function"==typeof Symbol&&"symbol"==typeof Symbol.iterator?function(e){return typeof e}:function(e){return e&&"function"==typeof Symbol&&e.constructor===Symbol&&e!==Symbol.prototype?"symbol":typeof e},C(e)}function T(e){return function(e){if(Array.isArray(e))return F(e)}(e)||function(e){if("undefined"!=typeof Symbol&&null!=e[Symbol.iterator]||null!=e["@@iterator"])return Array.from(e)}(e)||function(e,t){if(e){if("string"==typeof e)return F(e,t);var n={}.toString.call(e).slice(8,-1);return"Object"===n&&e.constructor&&(n=e.constructor.name),"Map"===n||"Set"===n?Array.from(e):"Arguments"===n||/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n)?F(e,t):void 0}}(e)||function(){throw new TypeError("Invalid attempt to spread non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.")}()}function F(e,t){(null==t||t>e.length)&&(t=e.length);for(var n=0,r=Array(t);n<t;n++)r[n]=e[n];return r}function k(e,t){for(var n=0;n<t.length;n++){var r=t[n];r.enumerable=r.enumerable||!1,r.configurable=!0,"value"in r&&(r.writable=!0),Object.defineProperty(e,j(r.key),r)}}function j(e){var t=function(e){if("object"!=C(e)||!e)return e;var t=e[Symbol.toPrimitive];if(void 0!==t){var n=t.call(e,"string");if("object"!=C(n))return n;throw new TypeError("@@toPrimitive must return a primitive value.")}return String(e)}(e);return"symbol"==C(t)?t:t+""}E.styleTagTransform=p(),E.setAttributes=l(),E.insert=s().bind(null,"head"),E.domAPI=i(),E.insertStyleElement=d(),t()(S.A,E),S.A&&S.A.locals&&S.A.locals;var O=function(){function e(){!function(e,t){if(!(e instanceof t))throw new TypeError("Cannot call a class as a function")}(this,e)}return t=e,n=[{key:"element",value:function(e){var t,n=arguments.length>1&&void 0!==arguments[1]?arguments[1]:null,r=arguments.length>2&&void 0!==arguments[2]?arguments[2]:{},o=document.createElement(e);for(var i in Array.isArray(n)?(t=o.classList).add.apply(t,T(n)):n&&o.classList.add(n),r)o.setAttribute(i,r[i]);return o}},{key:"input",value:function(t,n,r){var o=arguments.length>3&&void 0!==arguments[3]?arguments[3]:"",i=e.element("div",n,{contentEditable:!t.readOnly});return i.dataset.placeholder=t.api.i18n.t(r),o&&(i.textContent=o),i}},{key:"option",value:function(e,t){var n=arguments.length>2&&void 0!==arguments[2]?arguments[2]:null,r=arguments.length>3&&void 0!==arguments[3]?arguments[3]:{},o=document.createElement("option");for(var i in o.text=n||t,o.value=t,r)o.setAttribute(i,r[i]);e.add(o)}},{key:"options",value:function(t,n){n.forEach((function(n){return e.option(t,n)}))}},{key:"fileButtons",value:function(t){var n=arguments.length>1&&void 0!==arguments[1]?arguments[1]:[],r=e.element("div",["flex","cdx-input-labeled-preview","cdx-input-labeled","cdx-input","cdx-input-editable"].concat(T(n))),o=e.element("div",[t.api.styles.button]);if(o.innerHTML='<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">  <path d="M1 3.5A1.5 1.5 0 0 1 2.5 2h2.764c.958 0 1.76.56 2.311 1.184C7.985 3.648 8.48 4 9 4h4.5A1.5 1.5 0 0 1 15 5.5v.64c.57.265.94.876.856 1.546l-.64 5.124A2.5 2.5 0 0 1 12.733 15H3.266a2.5 2.5 0 0 1-2.481-2.19l-.64-5.124A1.5 1.5 0 0 1 1 6.14V3.5zM2 6h12v-.5a.5.5 0 0 0-.5-.5H9c-.964 0-1.71-.629-2.174-1.154C6.374 3.334 5.82 3 5.264 3H2.5a.5.5 0 0 0-.5.5V6zm-.367 1a.5.5 0 0 0-.496.562l.64 5.124A1.5 1.5 0 0 0 3.266 14h9.468a1.5 1.5 0 0 0 1.489-1.314l.64-5.124A.5.5 0 0 0 14.367 7H1.633z"/></svg>\n '+t.api.i18n.t("Select"),o.addEventListener("click",(function(e){return t.onSelectFile(t,e)})),r.appendChild(o),t.onUploadFile){var i=e.element("div",[t.api.styles.button]);i.innerHTML="".concat('<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">  <path d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z"/>  <path d="M7.646 1.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1-.708.708L8.5 2.707V11.5a.5.5 0 0 1-1 0V2.707L5.354 4.854a.5.5 0 1 1-.708-.708l3-3z"/></svg>\n'," ").concat(t.api.i18n.t("Upload")),i.style.marginLeft="-2px",i.addEventListener("click",(function(e){return t.onUploadFile(t,e)})),r.appendChild(i)}return r}},{key:"switchInput",value:function(t,n){var r=e.element("div","editor-switch"),o=e.element("input",null,{type:"checkbox",id:t}),i=e.element("label","label-default",{for:t}),a=e.element("label","",{for:t});return a.innerHTML=n,r.append(o,i,a),r}}],n&&k(t,n),Object.defineProperty(t,"prototype",{writable:!1}),t;var t,n}();function M(e){return M="function"==typeof Symbol&&"symbol"==typeof Symbol.iterator?function(e){return typeof e}:function(e){return e&&"function"==typeof Symbol&&e.constructor===Symbol&&e!==Symbol.prototype?"symbol":typeof e},M(e)}function L(e,t){for(var n=0;n<t.length;n++){var r=t[n];r.enumerable=r.enumerable||!1,r.configurable=!0,"value"in r&&(r.writable=!0),Object.defineProperty(e,A(r.key),r)}}function A(e){var t=function(e){if("object"!=M(e)||!e)return e;var t=e[Symbol.toPrimitive];if(void 0!==t){var n=t.call(e,"string");if("object"!=M(n))return n;throw new TypeError("@@toPrimitive must return a primitive value.")}return String(e)}(e);return"symbol"==M(t)?t:t+""}function I(e,t,n){return t=U(t),function(e,t){if(t&&("object"==M(t)||"function"==typeof t))return t;if(void 0!==t)throw new TypeError("Derived constructors may only return object or undefined");return function(e){if(void 0===e)throw new ReferenceError("this hasn't been initialised - super() hasn't been called");return e}(e)}(e,P()?Reflect.construct(t,n||[],U(e).constructor):t.apply(e,n))}function P(){try{var e=!Boolean.prototype.valueOf.call(Reflect.construct(Boolean,[],(function(){})))}catch(e){}return(P=function(){return!!e})()}function R(){return R="undefined"!=typeof Reflect&&Reflect.get?Reflect.get.bind():function(e,t,n){var r=function(e,t){for(;!{}.hasOwnProperty.call(e,t)&&null!==(e=U(e)););return e}(e,t);if(r){var o=Object.getOwnPropertyDescriptor(r,t);return o.get?o.get.call(arguments.length<3?e:n):o.value}},R.apply(null,arguments)}function U(e){return U=Object.setPrototypeOf?Object.getPrototypeOf.bind():function(e){return e.__proto__||Object.getPrototypeOf(e)},U(e)}function H(e,t){return H=Object.setPrototypeOf?Object.setPrototypeOf.bind():function(e,t){return e.__proto__=t,e},H(e,t)}var D=function(e){function t(e){var n,r=e.data,o=e.config,i=e.api,a=e.readOnly;return function(e,t){if(!(e instanceof t))throw new TypeError("Cannot call a class as a function")}(this,t),(n=I(this,t,[{data:r,config:o,api:i,readOnly:a}])).onSelectFile=o.onSelectFile,n.onUploadFile=o.onUploadFile||"",n}return function(e,t){if("function"!=typeof t&&null!==t)throw new TypeError("Super expression must either be null or a function");e.prototype=Object.create(t&&t.prototype,{constructor:{value:e,writable:!0,configurable:!0}}),Object.defineProperty(e,"prototype",{writable:!1}),t&&H(e,t)}(t,e),n=t,(r=[{key:"enableFileUpload",value:function(){}},{key:"onUpload",value:function(e){var n,r;e.file.title=e.file.name,(n=this,"function"==typeof(r=R(U(t.prototype),"onUpload",n))?function(e){return r.apply(n,e)}:r)([e]),e.success&&e.file&&this.nodes.buttonWrapper.remove()}},{key:"prepareUploadButton",value:function(){this.nodes.buttonWrapper=O.fileButtons(this),this.nodes.button=this.nodes.buttonWrapper.childNodes[0],this.nodes.wrapper.appendChild(this.nodes.buttonWrapper)}},{key:"fileConvertSize",value:function(e){e=Math.abs(parseInt(e,10));for(var t=[[1,"octets"],[1024,"ko"],[1048576,"Mo"],[1073741824,"Go"],[1099511627776,"To"]],n=0;n<t.length;n++)if(e<t[n][0])return(e/t[n-1][0]).toFixed(2)+" "+t[n-1][1];return e}}])&&L(n.prototype,r),Object.defineProperty(n,"prototype",{writable:!1}),n;var n,r}(_)})(),r.default})()));