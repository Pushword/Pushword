!function(e,t){if("object"==typeof exports&&"object"==typeof module)module.exports=t();else if("function"==typeof define&&define.amd)define([],t);else{var n=t();for(var r in n)("object"==typeof exports?exports:e)[r]=n[r]}}(window,(function(){return function(e){var t={};function n(r){if(t[r])return t[r].exports;var o=t[r]={i:r,l:!1,exports:{}};return e[r].call(o.exports,o,o.exports,n),o.l=!0,o.exports}return n.m=e,n.c=t,n.d=function(e,t,r){n.o(e,t)||Object.defineProperty(e,t,{enumerable:!0,get:r})},n.r=function(e){"undefined"!=typeof Symbol&&Symbol.toStringTag&&Object.defineProperty(e,Symbol.toStringTag,{value:"Module"}),Object.defineProperty(e,"__esModule",{value:!0})},n.t=function(e,t){if(1&t&&(e=n(e)),8&t)return e;if(4&t&&"object"==typeof e&&e&&e.__esModule)return e;var r=Object.create(null);if(n.r(r),Object.defineProperty(r,"default",{enumerable:!0,value:e}),2&t&&"string"!=typeof e)for(var o in e)n.d(r,o,function(t){return e[t]}.bind(null,o));return r},n.n=function(e){var t=e&&e.__esModule?function(){return e.default}:function(){return e};return n.d(t,"a",t),t},n.o=function(e,t){return Object.prototype.hasOwnProperty.call(e,t)},n.p="/",n(n.s=28)}([function(t,n,r){"use strict";r.d(n,"a",(function(){return c}));var o='<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">  <path d="M1 3.5A1.5 1.5 0 0 1 2.5 2h2.764c.958 0 1.76.56 2.311 1.184C7.985 3.648 8.48 4 9 4h4.5A1.5 1.5 0 0 1 15 5.5v.64c.57.265.94.876.856 1.546l-.64 5.124A2.5 2.5 0 0 1 12.733 15H3.266a2.5 2.5 0 0 1-2.481-2.19l-.64-5.124A1.5 1.5 0 0 1 1 6.14V3.5zM2 6h12v-.5a.5.5 0 0 0-.5-.5H9c-.964 0-1.71-.629-2.174-1.154C6.374 3.334 5.82 3 5.264 3H2.5a.5.5 0 0 0-.5.5V6zm-.367 1a.5.5 0 0 0-.496.562l.64 5.124A1.5 1.5 0 0 0 3.266 14h9.468a1.5 1.5 0 0 0 1.489-1.314l.64-5.124A.5.5 0 0 0 14.367 7H1.633z"/></svg>\n',i='<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">  <path d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z"/>  <path d="M7.646 1.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1-.708.708L8.5 2.707V11.5a.5.5 0 0 1-1 0V2.707L5.354 4.854a.5.5 0 1 1-.708-.708l3-3z"/></svg>\n';function a(e){return function(e){if(Array.isArray(e))return l(e)}(e)||function(e){if("undefined"!=typeof Symbol&&Symbol.iterator in Object(e))return Array.from(e)}(e)||function(e,t){if(!e)return;if("string"==typeof e)return l(e,t);var n=Object.prototype.toString.call(e).slice(8,-1);"Object"===n&&e.constructor&&(n=e.constructor.name);if("Map"===n||"Set"===n)return Array.from(e);if("Arguments"===n||/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n))return l(e,t)}(e)||function(){throw new TypeError("Invalid attempt to spread non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.")}()}function l(e,t){(null==t||t>e.length)&&(t=e.length);for(var n=0,r=new Array(t);n<t;n++)r[n]=e[n];return r}function s(e,t){for(var n=0;n<t.length;n++){var r=t[n];r.enumerable=r.enumerable||!1,r.configurable=!0,"value"in r&&(r.writable=!0),Object.defineProperty(e,r.key,r)}}var c=function(){function t(){!function(e,t){if(!(e instanceof t))throw new TypeError("Cannot call a class as a function")}(this,t)}var n,r,l;return n=t,l=[{key:"element",value:function(e){var t,n=arguments.length>1&&void 0!==arguments[1]?arguments[1]:null,r=arguments.length>2&&void 0!==arguments[2]?arguments[2]:{},o=document.createElement(e);for(var i in Array.isArray(n)?(t=o.classList).add.apply(t,a(n)):n&&o.classList.add(n),r)o.setAttribute(i,r[i]);return o}},{key:"input",value:function(e,n,r){var o=arguments.length>3&&void 0!==arguments[3]?arguments[3]:"",i=t.element("div",n,{contentEditable:!e.readOnly});return i.dataset.placeholder=e.api.i18n.t(r),o&&(i.textContent=o),i}},{key:"option",value:function(e,t){var n=arguments.length>2&&void 0!==arguments[2]?arguments[2]:null,r=document.createElement("option");r.text=n||t,r.value=t,e.add(r)}},{key:"options",value:function(e,n){n.forEach((function(n){return t.option(e,n)}))}},{key:"fileButtons",value:function(e){var n=arguments.length>1&&void 0!==arguments[1]?arguments[1]:[],r=t.element("div",["flex","cdx-input-labeled-preview","cdx-input-labeled","cdx-input","cdx-input-editable"].concat(a(n))),l=t.element("div",[e.api.styles.button]);if(l.innerHTML=o+" "+e.api.i18n.t("Select"),l.addEventListener("click",(function(t){return e.onSelectFile(e,t)})),r.appendChild(l),e.onUploadFile){var s=t.element("div",[e.api.styles.button]);s.innerHTML="".concat(i," ").concat(e.api.i18n.t("Upload")),s.style.marginLeft="-2px",s.addEventListener("click",(function(t){return e.onUploadFile(e,t)})),r.appendChild(s)}return r}}],(r=[{key:"createPaginateCheckbox",value:function(){var n=t.element("div",["checkbox","cdx-checkbox"]),r=e.element("label");return r.textContent="Paginate",this.nodes.paginateCheckbox=document.createElement("input"),this.nodes.paginateCheckbox.type="checkbox",this.nodes.paginateCheckbox.value=this._data.display||0,n.appendChild(this.nodes.paginateCheckbox),n.appendChild(r),n}}])&&s(n.prototype,r),l&&s(n,l),t}()},function(e,t,n){window,e.exports=function(e){var t={};function n(r){if(t[r])return t[r].exports;var o=t[r]={i:r,l:!1,exports:{}};return e[r].call(o.exports,o,o.exports,n),o.l=!0,o.exports}return n.m=e,n.c=t,n.d=function(e,t,r){n.o(e,t)||Object.defineProperty(e,t,{enumerable:!0,get:r})},n.r=function(e){"undefined"!=typeof Symbol&&Symbol.toStringTag&&Object.defineProperty(e,Symbol.toStringTag,{value:"Module"}),Object.defineProperty(e,"__esModule",{value:!0})},n.t=function(e,t){if(1&t&&(e=n(e)),8&t)return e;if(4&t&&"object"==typeof e&&e&&e.__esModule)return e;var r=Object.create(null);if(n.r(r),Object.defineProperty(r,"default",{enumerable:!0,value:e}),2&t&&"string"!=typeof e)for(var o in e)n.d(r,o,function(t){return e[t]}.bind(null,o));return r},n.n=function(e){var t=e&&e.__esModule?function(){return e.default}:function(){return e};return n.d(t,"a",t),t},n.o=function(e,t){return Object.prototype.hasOwnProperty.call(e,t)},n.p="",n(n.s=3)}([function(e,t){var n;n=function(){return this}();try{n=n||new Function("return this")()}catch(e){"object"==typeof window&&(n=window)}e.exports=n},function(e,t,n){"use strict";(function(e){var r=n(2),o=setTimeout;function i(){}function a(e){if(!(this instanceof a))throw new TypeError("Promises must be constructed via new");if("function"!=typeof e)throw new TypeError("not a function");this._state=0,this._handled=!1,this._value=void 0,this._deferreds=[],f(e,this)}function l(e,t){for(;3===e._state;)e=e._value;0!==e._state?(e._handled=!0,a._immediateFn((function(){var n=1===e._state?t.onFulfilled:t.onRejected;if(null!==n){var r;try{r=n(e._value)}catch(e){return void c(t.promise,e)}s(t.promise,r)}else(1===e._state?s:c)(t.promise,e._value)}))):e._deferreds.push(t)}function s(e,t){try{if(t===e)throw new TypeError("A promise cannot be resolved with itself.");if(t&&("object"==typeof t||"function"==typeof t)){var n=t.then;if(t instanceof a)return e._state=3,e._value=t,void u(e);if("function"==typeof n)return void f((r=n,o=t,function(){r.apply(o,arguments)}),e)}e._state=1,e._value=t,u(e)}catch(t){c(e,t)}var r,o}function c(e,t){e._state=2,e._value=t,u(e)}function u(e){2===e._state&&0===e._deferreds.length&&a._immediateFn((function(){e._handled||a._unhandledRejectionFn(e._value)}));for(var t=0,n=e._deferreds.length;t<n;t++)l(e,e._deferreds[t]);e._deferreds=null}function d(e,t,n){this.onFulfilled="function"==typeof e?e:null,this.onRejected="function"==typeof t?t:null,this.promise=n}function f(e,t){var n=!1;try{e((function(e){n||(n=!0,s(t,e))}),(function(e){n||(n=!0,c(t,e))}))}catch(e){if(n)return;n=!0,c(t,e)}}a.prototype.catch=function(e){return this.then(null,e)},a.prototype.then=function(e,t){var n=new this.constructor(i);return l(this,new d(e,t,n)),n},a.prototype.finally=r.a,a.all=function(e){return new a((function(t,n){if(!e||void 0===e.length)throw new TypeError("Promise.all accepts an array");var r=Array.prototype.slice.call(e);if(0===r.length)return t([]);var o=r.length;function i(e,a){try{if(a&&("object"==typeof a||"function"==typeof a)){var l=a.then;if("function"==typeof l)return void l.call(a,(function(t){i(e,t)}),n)}r[e]=a,0==--o&&t(r)}catch(e){n(e)}}for(var a=0;a<r.length;a++)i(a,r[a])}))},a.resolve=function(e){return e&&"object"==typeof e&&e.constructor===a?e:new a((function(t){t(e)}))},a.reject=function(e){return new a((function(t,n){n(e)}))},a.race=function(e){return new a((function(t,n){for(var r=0,o=e.length;r<o;r++)e[r].then(t,n)}))},a._immediateFn="function"==typeof e&&function(t){e(t)}||function(e){o(e,0)},a._unhandledRejectionFn=function(e){"undefined"!=typeof console&&console&&console.warn("Possible Unhandled Promise Rejection:",e)},t.a=a}).call(this,n(5).setImmediate)},function(e,t,n){"use strict";t.a=function(e){var t=this.constructor;return this.then((function(n){return t.resolve(e()).then((function(){return n}))}),(function(n){return t.resolve(e()).then((function(){return t.reject(n)}))}))}},function(e,t,n){"use strict";function r(e){return(r="function"==typeof Symbol&&"symbol"==typeof Symbol.iterator?function(e){return typeof e}:function(e){return e&&"function"==typeof Symbol&&e.constructor===Symbol&&e!==Symbol.prototype?"symbol":typeof e})(e)}n(4);var o,i,a,l,s,c,u,d=n(8),f=(i=function(e){return new Promise((function(t,n){e=l(e),(e=s(e)).beforeSend&&e.beforeSend();var r=window.XMLHttpRequest?new window.XMLHttpRequest:new window.ActiveXObject("Microsoft.XMLHTTP");r.open(e.method,e.url),r.setRequestHeader("X-Requested-With","XMLHttpRequest"),Object.keys(e.headers).forEach((function(t){var n=e.headers[t];r.setRequestHeader(t,n)}));var o=e.ratio;r.upload.addEventListener("progress",(function(t){var n=Math.round(t.loaded/t.total*100),r=Math.ceil(n*o/100);e.progress(Math.min(r,100))}),!1),r.addEventListener("progress",(function(t){var n=Math.round(t.loaded/t.total*100),r=Math.ceil(n*(100-o)/100)+o;e.progress(Math.min(r,100))}),!1),r.onreadystatechange=function(){if(4===r.readyState){var e=r.response;try{e=JSON.parse(e)}catch(e){}var o=d.parseHeaders(r.getAllResponseHeaders()),i={body:e,code:r.status,headers:o};u(r.status)?t(i):n(i)}},r.send(e.data)}))},a=function(e){return e.method="POST",i(e)},l=function(){var e=arguments.length>0&&void 0!==arguments[0]?arguments[0]:{};if(e.url&&"string"!=typeof e.url)throw new Error("Url must be a string");if(e.url=e.url||"",e.method&&"string"!=typeof e.method)throw new Error("`method` must be a string or null");if(e.method=e.method?e.method.toUpperCase():"GET",e.headers&&"object"!==r(e.headers))throw new Error("`headers` must be an object or null");if(e.headers=e.headers||{},e.type&&("string"!=typeof e.type||!Object.values(o).includes(e.type)))throw new Error("`type` must be taken from module's «contentType» library");if(e.progress&&"function"!=typeof e.progress)throw new Error("`progress` must be a function or null");if(e.progress=e.progress||function(e){},e.beforeSend=e.beforeSend||function(e){},e.ratio&&"number"!=typeof e.ratio)throw new Error("`ratio` must be a number");if(e.ratio<0||e.ratio>100)throw new Error("`ratio` must be in a 0-100 interval");if(e.ratio=e.ratio||90,e.accept&&"string"!=typeof e.accept)throw new Error("`accept` must be a string with a list of allowed mime-types");if(e.accept=e.accept||"*/*",e.multiple&&"boolean"!=typeof e.multiple)throw new Error("`multiple` must be a true or false");if(e.multiple=e.multiple||!1,e.fieldName&&"string"!=typeof e.fieldName)throw new Error("`fieldName` must be a string");return e.fieldName=e.fieldName||"files",e},s=function(e){switch(e.method){case"GET":var t=c(e.data,o.URLENCODED);delete e.data,e.url=/\?/.test(e.url)?e.url+"&"+t:e.url+"?"+t;break;case"POST":case"PUT":case"DELETE":case"UPDATE":var n=function(){return(arguments.length>0&&void 0!==arguments[0]?arguments[0]:{}).type||o.JSON}(e);(d.isFormData(e.data)||d.isFormElement(e.data))&&(n=o.FORM),e.data=c(e.data,n),n!==f.contentType.FORM&&(e.headers["content-type"]=n)}return e},c=function(){var e=arguments.length>0&&void 0!==arguments[0]?arguments[0]:{};switch(arguments.length>1?arguments[1]:void 0){case o.URLENCODED:return d.urlEncode(e);case o.JSON:return d.jsonEncode(e);case o.FORM:return d.formEncode(e);default:return e}},u=function(e){return e>=200&&e<300},{contentType:o={URLENCODED:"application/x-www-form-urlencoded; charset=utf-8",FORM:"multipart/form-data",JSON:"application/json; charset=utf-8"},request:i,get:function(e){return e.method="GET",i(e)},post:a,transport:function(e){return e=l(e),d.selectFiles(e).then((function(t){for(var n=new FormData,r=0;r<t.length;r++)n.append(e.fieldName,t[r],t[r].name);d.isObject(e.data)&&Object.keys(e.data).forEach((function(t){var r=e.data[t];n.append(t,r)}));var o=e.beforeSend;return e.beforeSend=function(){return o(t)},e.data=n,a(e)}))},selectFiles:function(e){return delete(e=l(e)).beforeSend,d.selectFiles(e)}});e.exports=f},function(e,t,n){"use strict";n.r(t);var r=n(1);window.Promise=window.Promise||r.a},function(e,t,n){(function(e){var r=void 0!==e&&e||"undefined"!=typeof self&&self||window,o=Function.prototype.apply;function i(e,t){this._id=e,this._clearFn=t}t.setTimeout=function(){return new i(o.call(setTimeout,r,arguments),clearTimeout)},t.setInterval=function(){return new i(o.call(setInterval,r,arguments),clearInterval)},t.clearTimeout=t.clearInterval=function(e){e&&e.close()},i.prototype.unref=i.prototype.ref=function(){},i.prototype.close=function(){this._clearFn.call(r,this._id)},t.enroll=function(e,t){clearTimeout(e._idleTimeoutId),e._idleTimeout=t},t.unenroll=function(e){clearTimeout(e._idleTimeoutId),e._idleTimeout=-1},t._unrefActive=t.active=function(e){clearTimeout(e._idleTimeoutId);var t=e._idleTimeout;t>=0&&(e._idleTimeoutId=setTimeout((function(){e._onTimeout&&e._onTimeout()}),t))},n(6),t.setImmediate="undefined"!=typeof self&&self.setImmediate||void 0!==e&&e.setImmediate||this&&this.setImmediate,t.clearImmediate="undefined"!=typeof self&&self.clearImmediate||void 0!==e&&e.clearImmediate||this&&this.clearImmediate}).call(this,n(0))},function(e,t,n){(function(e,t){!function(e,n){"use strict";if(!e.setImmediate){var r,o,i,a,l,s=1,c={},u=!1,d=e.document,f=Object.getPrototypeOf&&Object.getPrototypeOf(e);f=f&&f.setTimeout?f:e,"[object process]"==={}.toString.call(e.process)?r=function(e){t.nextTick((function(){h(e)}))}:function(){if(e.postMessage&&!e.importScripts){var t=!0,n=e.onmessage;return e.onmessage=function(){t=!1},e.postMessage("","*"),e.onmessage=n,t}}()?(a="setImmediate$"+Math.random()+"$",l=function(t){t.source===e&&"string"==typeof t.data&&0===t.data.indexOf(a)&&h(+t.data.slice(a.length))},e.addEventListener?e.addEventListener("message",l,!1):e.attachEvent("onmessage",l),r=function(t){e.postMessage(a+t,"*")}):e.MessageChannel?((i=new MessageChannel).port1.onmessage=function(e){h(e.data)},r=function(e){i.port2.postMessage(e)}):d&&"onreadystatechange"in d.createElement("script")?(o=d.documentElement,r=function(e){var t=d.createElement("script");t.onreadystatechange=function(){h(e),t.onreadystatechange=null,o.removeChild(t),t=null},o.appendChild(t)}):r=function(e){setTimeout(h,0,e)},f.setImmediate=function(e){"function"!=typeof e&&(e=new Function(""+e));for(var t=new Array(arguments.length-1),n=0;n<t.length;n++)t[n]=arguments[n+1];var o={callback:e,args:t};return c[s]=o,r(s),s++},f.clearImmediate=p}function p(e){delete c[e]}function h(e){if(u)setTimeout(h,0,e);else{var t=c[e];if(t){u=!0;try{!function(e){var t=e.callback,n=e.args;switch(n.length){case 0:t();break;case 1:t(n[0]);break;case 2:t(n[0],n[1]);break;case 3:t(n[0],n[1],n[2]);break;default:t.apply(void 0,n)}}(t)}finally{p(e),u=!1}}}}}("undefined"==typeof self?void 0===e?this:e:self)}).call(this,n(0),n(7))},function(e,t){var n,r,o=e.exports={};function i(){throw new Error("setTimeout has not been defined")}function a(){throw new Error("clearTimeout has not been defined")}function l(e){if(n===setTimeout)return setTimeout(e,0);if((n===i||!n)&&setTimeout)return n=setTimeout,setTimeout(e,0);try{return n(e,0)}catch(t){try{return n.call(null,e,0)}catch(t){return n.call(this,e,0)}}}!function(){try{n="function"==typeof setTimeout?setTimeout:i}catch(e){n=i}try{r="function"==typeof clearTimeout?clearTimeout:a}catch(e){r=a}}();var s,c=[],u=!1,d=-1;function f(){u&&s&&(u=!1,s.length?c=s.concat(c):d=-1,c.length&&p())}function p(){if(!u){var e=l(f);u=!0;for(var t=c.length;t;){for(s=c,c=[];++d<t;)s&&s[d].run();d=-1,t=c.length}s=null,u=!1,function(e){if(r===clearTimeout)return clearTimeout(e);if((r===a||!r)&&clearTimeout)return r=clearTimeout,clearTimeout(e);try{r(e)}catch(t){try{return r.call(null,e)}catch(t){return r.call(this,e)}}}(e)}}function h(e,t){this.fun=e,this.array=t}function m(){}o.nextTick=function(e){var t=new Array(arguments.length-1);if(arguments.length>1)for(var n=1;n<arguments.length;n++)t[n-1]=arguments[n];c.push(new h(e,t)),1!==c.length||u||l(p)},h.prototype.run=function(){this.fun.apply(null,this.array)},o.title="browser",o.browser=!0,o.env={},o.argv=[],o.version="",o.versions={},o.on=m,o.addListener=m,o.once=m,o.off=m,o.removeListener=m,o.removeAllListeners=m,o.emit=m,o.prependListener=m,o.prependOnceListener=m,o.listeners=function(e){return[]},o.binding=function(e){throw new Error("process.binding is not supported")},o.cwd=function(){return"/"},o.chdir=function(e){throw new Error("process.chdir is not supported")},o.umask=function(){return 0}},function(e,t,n){function r(e,t){for(var n=0;n<t.length;n++){var r=t[n];r.enumerable=r.enumerable||!1,r.configurable=!0,"value"in r&&(r.writable=!0),Object.defineProperty(e,r.key,r)}}var o=n(9);e.exports=function(){function e(){!function(e,t){if(!(e instanceof t))throw new TypeError("Cannot call a class as a function")}(this,e)}var t,n;return t=e,(n=[{key:"urlEncode",value:function(e){return o(e)}},{key:"jsonEncode",value:function(e){return JSON.stringify(e)}},{key:"formEncode",value:function(e){if(this.isFormData(e))return e;if(this.isFormElement(e))return new FormData(e);if(this.isObject(e)){var t=new FormData;return Object.keys(e).forEach((function(n){var r=e[n];t.append(n,r)})),t}throw new Error("`data` must be an instance of Object, FormData or <FORM> HTMLElement")}},{key:"isObject",value:function(e){return"[object Object]"===Object.prototype.toString.call(e)}},{key:"isFormData",value:function(e){return e instanceof FormData}},{key:"isFormElement",value:function(e){return e instanceof HTMLFormElement}},{key:"selectFiles",value:function(){console.trace();var e=arguments.length>0&&void 0!==arguments[0]?arguments[0]:{};return new Promise((function(t,n){var r=document.createElement("INPUT");r.type="file",e.multiple&&r.setAttribute("multiple","multiple"),e.accept&&r.setAttribute("accept",e.accept),r.style.display="none",document.body.appendChild(r),r.addEventListener("change",(function(e){var n=e.target.files;t(n),document.body.removeChild(r)}),!1),r.click()}))}},{key:"parseHeaders",value:function(e){var t=e.trim().split(/[\r\n]+/),n={};return t.forEach((function(e){var t=e.split(": "),r=t.shift(),o=t.join(": ");r&&(n[r]=o)})),n}}])&&r(t,n),e}()},function(e,t){var n=function(e){return encodeURIComponent(e).replace(/[!'()*]/g,escape).replace(/%20/g,"+")},r=function(e,t,o,i){return t=t||null,o=o||"&",i=i||null,e?function(e){for(var t=new Array,n=0;n<e.length;n++)e[n]&&t.push(e[n]);return t}(Object.keys(e).map((function(a){var l,s,c=a;if(i&&(c=i+"["+c+"]"),"object"==typeof e[a]&&null!==e[a])l=r(e[a],null,o,c);else{t&&(s=c,c=!isNaN(parseFloat(s))&&isFinite(s)?t+Number(c):c);var u=e[a];u=(u=0===(u=!1===(u=!0===u?"1":u)?"0":u)?"0":u)||"",l=n(c)+"="+n(u)}return l}))).join(o).replace(/[!'()*]/g,""):""};e.exports=r}])},function(e,t,n){"use strict";var r,o=function(){return void 0===r&&(r=Boolean(window&&document&&document.all&&!window.atob)),r},i=function(){var e={};return function(t){if(void 0===e[t]){var n=document.querySelector(t);if(window.HTMLIFrameElement&&n instanceof window.HTMLIFrameElement)try{n=n.contentDocument.head}catch(e){n=null}e[t]=n}return e[t]}}(),a=[];function l(e){for(var t=-1,n=0;n<a.length;n++)if(a[n].identifier===e){t=n;break}return t}function s(e,t){for(var n={},r=[],o=0;o<e.length;o++){var i=e[o],s=t.base?i[0]+t.base:i[0],c=n[s]||0,u="".concat(s," ").concat(c);n[s]=c+1;var d=l(u),f={css:i[1],media:i[2],sourceMap:i[3]};-1!==d?(a[d].references++,a[d].updater(f)):a.push({identifier:u,updater:y(f,t),references:1}),r.push(u)}return r}function c(e){var t=document.createElement("style"),r=e.attributes||{};if(void 0===r.nonce){var o=n.nc;o&&(r.nonce=o)}if(Object.keys(r).forEach((function(e){t.setAttribute(e,r[e])})),"function"==typeof e.insert)e.insert(t);else{var a=i(e.insert||"head");if(!a)throw new Error("Couldn't find a style target. This probably means that the value for the 'insert' parameter is invalid.");a.appendChild(t)}return t}var u,d=(u=[],function(e,t){return u[e]=t,u.filter(Boolean).join("\n")});function f(e,t,n,r){var o=n?"":r.media?"@media ".concat(r.media," {").concat(r.css,"}"):r.css;if(e.styleSheet)e.styleSheet.cssText=d(t,o);else{var i=document.createTextNode(o),a=e.childNodes;a[t]&&e.removeChild(a[t]),a.length?e.insertBefore(i,a[t]):e.appendChild(i)}}function p(e,t,n){var r=n.css,o=n.media,i=n.sourceMap;if(o?e.setAttribute("media",o):e.removeAttribute("media"),i&&"undefined"!=typeof btoa&&(r+="\n/*# sourceMappingURL=data:application/json;base64,".concat(btoa(unescape(encodeURIComponent(JSON.stringify(i))))," */")),e.styleSheet)e.styleSheet.cssText=r;else{for(;e.firstChild;)e.removeChild(e.firstChild);e.appendChild(document.createTextNode(r))}}var h=null,m=0;function y(e,t){var n,r,o;if(t.singleton){var i=m++;n=h||(h=c(t)),r=f.bind(null,n,i,!1),o=f.bind(null,n,i,!0)}else n=c(t),r=p.bind(null,n,t),o=function(){!function(e){if(null===e.parentNode)return!1;e.parentNode.removeChild(e)}(n)};return r(e),function(t){if(t){if(t.css===e.css&&t.media===e.media&&t.sourceMap===e.sourceMap)return;r(e=t)}else o()}}e.exports=function(e,t){(t=t||{}).singleton||"boolean"==typeof t.singleton||(t.singleton=o());var n=s(e=e||[],t);return function(e){if(e=e||[],"[object Array]"===Object.prototype.toString.call(e)){for(var r=0;r<n.length;r++){var o=l(n[r]);a[o].references--}for(var i=s(e,t),c=0;c<n.length;c++){var u=l(n[c]);0===a[u].references&&(a[u].updater(),a.splice(u,1))}n=i}}}},function(e,t,n){"use strict";e.exports=function(e){var t=[];return t.toString=function(){return this.map((function(t){var n=function(e,t){var n=e[1]||"",r=e[3];if(!r)return n;if(t&&"function"==typeof btoa){var o=(a=r,l=btoa(unescape(encodeURIComponent(JSON.stringify(a)))),s="sourceMappingURL=data:application/json;charset=utf-8;base64,".concat(l),"/*# ".concat(s," */")),i=r.sources.map((function(e){return"/*# sourceURL=".concat(r.sourceRoot||"").concat(e," */")}));return[n].concat(i).concat([o]).join("\n")}var a,l,s;return[n].join("\n")}(t,e);return t[2]?"@media ".concat(t[2]," {").concat(n,"}"):n})).join("")},t.i=function(e,n,r){"string"==typeof e&&(e=[[null,e,""]]);var o={};if(r)for(var i=0;i<this.length;i++){var a=this[i][0];null!=a&&(o[a]=!0)}for(var l=0;l<e.length;l++){var s=[].concat(e[l]);r&&o[s[0]]||(n&&(s[2]?s[2]="".concat(n," and ").concat(s[2]):s[2]=n),t.push(s))}},t}},,,,,,,,,,,,,,,,function(e,t,n){var r=n(2),o=n(20);"string"==typeof(o=o.__esModule?o.default:o)&&(o=[[e.i,o,""]]);var i={insert:"head",singleton:!1};r(o,i);e.exports=o.locals||{}},function(e,t,n){(t=n(3)(!1)).push([e.i,'.cdxcarousel-addImage {\n    height: 225px;\n    display: flex;\n    justify-content: center;\n    align-items: center;\n    padding: 5px;\n    position: relative;\n    flex-direction: row;\n    margin-top: 5px;\n}\n\n.cdxcarousel-item--empty .image-tool__image-preloader{\n  display: none;\n}\n\n.cdxcarousel-item img {\n    margin-bottom: 15px;\n    display: block;\n    object-fit: cover;\n    width: 100%;\n    height: 180px;\n    order: 1;\n}\n\n.cdxcarousel-inputUrl {\n    order: 0;\n    display: none;\n}\n\n.cdxcarousel-caption {\n    order: 3;\n}\n\n\n.cdxcarousel-block {\n    width: 50%;\n}\n\n.cdxcarousel-list {\n    display: flex;\n    flex-wrap: wrap;\n    flex-direction: row;\n    justify-content: flex-start;\n    align-items: flex-start;\n}\n\n.cdxcarousel-wrapper {\n    margin-top: 10px;\n    margin-bottom: 15px;\n}\n\n.cdxcarousel-item {\n    height: 235px;\n    padding: 5px;\n    display: flex;\n    position: relative;\n    flex-direction: column;\n}\n\n\n.cdxcarousel-removeBtn {\n    position: absolute;\n    padding: 3px;\n    right: 10px;\n    top: 10px;\n    display: inline-flex;\n    width: 34px;\n    height: 34px;\n    line-height: 34px;\n    justify-content: center;\n    align-items: center;\n    border-radius: 3px;\n    cursor: pointer;\n    border: 0;\n    outline: none;\n    background-color: white;\n    color: #000;\n    transition: background 200ms;\n}\n\n.cdxcarousel-removeBtn:hover {\n    background: #eff2f5;\n}\n\n.image-tool__image {\n    border-radius: 3px;\n    overflow: hidden;\n    margin-bottom: 10px;\n  }\n\n.image-tool__image-picture {\n      max-width: 100%;\n      vertical-align: bottom;\n      display: block;\n    }\n\n.image-tool__image-preloader {\n      width: 50px;\n      height: 50px;\n      border-radius: 50%;\n      background-size: cover;\n      margin: auto;\n      position: relative;\n      background-color: #cdd1e0;\n      background-position: center center;\n    }\n\n.image-tool__image-preloader::after {\n        content: "";\n        position: absolute;\n        z-index: 3;\n        width: 60px;\n        height: 60px;\n        border-radius: 50%;\n        border: 2px solid #cdd1e0;\n        border-top-color: #388ae5;\n        left: 50%;\n        top: 50%;\n        margin-top: -30px;\n        margin-left: -30px;\n        animation: image-preloader-spin 2s infinite linear;\n        box-sizing: border-box;\n      }\n',""]),e.exports=t},function(e,t,n){var r=n(2),o=n(22);"string"==typeof(o=o.__esModule?o.default:o)&&(o=[[e.i,o,""]]);var i={insert:"head",singleton:!1};r(o,i);e.exports=o.locals||{}},function(e,t,n){(t=n(3)(!1)).push([e.i,".cdx-input-gallery,\n.cdxcarousel-block {\n    margin-top: 20px !important;\n    width: 50%;\n}\n\n.cdxcarousel-item img {\n    margin-bottom: 10px !important;\n}\n",""]),e.exports=t},,,,,,function(e,t,n){"use strict";n.r(t),n.d(t,"default",(function(){return b}));var r=n(1),o=n.n(r);class i{constructor({config:e,onUpload:t,onError:n}){this.config=e,this.onUpload=t,this.onError=n}uploadSelectedFile({onPreview:e}){const t=function(t){const n=new window.FileReader;n.readAsDataURL(t),n.onload=t=>{e(t.target.result)}};let n;n=this.config.uploader&&"function"==typeof this.config.uploader.uploadByFile?o.a.selectFiles({accept:this.config.types}).then(e=>{t(e[0]);const n=this.config.uploader.uploadByFile(e[0]);return a(n)||console.warn("Custom uploader method uploadByFile should return a Promise"),n}):o.a.transport({url:this.config.endpoints.byFile,data:this.config.additionalRequestData,accept:this.config.types,headers:this.config.additionalRequestHeaders,beforeSend:e=>{t(e[0])},fieldName:this.config.field}).then(e=>e.body),n.then(e=>{this.onUpload(e)}).catch(e=>{this.onError(e)})}uploadByUrl(e){let t;this.config.uploader&&"function"==typeof this.config.uploader.uploadByUrl?(t=this.config.uploader.uploadByUrl(e),a(t)||console.warn("Custom uploader method uploadByUrl should return a Promise")):t=o.a.post({url:this.config.endpoints.byUrl,data:Object.assign({url:e},this.config.additionalRequestData),type:o.a.contentType.JSON,headers:this.config.additionalRequestHeaders}).then(e=>e.body),t.then(e=>{this.onUpload(e)}).catch(e=>{this.onError(e)})}uploadByFile(e,{onPreview:t}){const n=new window.FileReader;let r;if(n.readAsDataURL(e),n.onload=e=>{t(e.target.result)},this.config.uploader&&"function"==typeof this.config.uploader.uploadByFile)r=this.config.uploader.uploadByFile(e),a(r)||console.warn("Custom uploader method uploadByFile should return a Promise");else{const t=new window.FormData;t.append(this.config.field,e),this.config.additionalRequestData&&Object.keys(this.config.additionalRequestData).length&&Object.entries(this.config.additionalRequestData).forEach(([e,n])=>{t.append(e,n)}),r=o.a.post({url:this.config.endpoints.byFile,data:t,type:o.a.contentType.JSON,headers:this.config.additionalRequestHeaders}).then(e=>e.body)}r.then(e=>{this.onUpload(e)}).catch(e=>{this.onError(e)})}}function a(e){return window.Promise.resolve(e)===e}n(19).toString();class l{constructor({data:e,config:t,api:n}){this.api=n,this.data=e,this.IconClose='<svg class="icon icon--cross" width="12px" height="12px"><use xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#cross"></use></svg>',this.config={endpoints:t.endpoints||"",additionalRequestData:t.additionalRequestData||{},additionalRequestHeaders:t.additionalRequestHeaders||{},field:t.field||"image",types:t.types||"image/*",captionPlaceholder:this.api.i18n.t("Caption"),buttonContent:t.buttonContent||"",uploader:t.uploader||void 0},this.uploader=new i({config:this.config,onUpload:e=>this.onUpload(e),onError:e=>this.uploadingFailed(e)})}get CSS(){return{baseClass:this.api.styles.block,loading:this.api.styles.loader,input:this.api.styles.input,button:this.api.styles.button,wrapper:"cdxcarousel-wrapper",addButton:"cdxcarousel-addImage",block:"cdxcarousel-block",item:"cdxcarousel-item",removeBtn:"cdxcarousel-removeBtn",inputUrl:"cdxcarousel-inputUrl",caption:"cdxcarousel-caption",list:"cdxcarousel-list",imagePreloader:"image-tool__image-preloader"}}static get toolbox(){return{title:"Carousel",icon:'<svg width="38" height="18" viewBox="0 0 38 18" fill="none" xmlns="http://www.w3.org/2000/svg"><mask id="mask0" mask-type="alpha" maskUnits="userSpaceOnUse" x="10" y="0" width="18" height="18"><path fill-rule="evenodd" clip-rule="evenodd" d="M28 16V2C28 0.9 27.1 0 26 0H12C10.9 0 10 0.9 10 2V16C10 17.1 10.9 18 12 18H26C27.1 18 28 17.1 28 16V16ZM15.5 10.5L18 13.51L21.5 9L26 15H12L15.5 10.5V10.5Z"  /></mask><g mask="url(#mask0)"><rect x="10" width="18" height="18"  /></g><mask id="mask1" mask-type="alpha" maskUnits="userSpaceOnUse" x="0" y="3" width="7" height="12"><path fill-rule="evenodd" clip-rule="evenodd" d="M7 13.59L2.67341 9L7 4.41L5.66802 3L0 9L5.66802 15L7 13.59Z" fill="white"/></mask><g mask="url(#mask1)"><rect y="3" width="7.55735" height="12"  /></g><mask id="mask2" mask-type="alpha" maskUnits="userSpaceOnUse" x="31" y="3" width="7" height="12"><path fill-rule="evenodd" clip-rule="evenodd" d="M31 13.59L35.3266 9L31 4.41L32.332 3L38 9L32.332 15L31 13.59Z" fill="white"/></mask><g mask="url(#mask2)"><rect x="30.4426" y="2.25" width="7.55735" height="13" /></g></svg>'}}render(){if(this.wrapper=s("div",[this.CSS.wrapper]),this.list=s("div",[this.CSS.list]),this.addButton=this.createAddButton(),this.list.appendChild(this.addButton),this.wrapper.appendChild(this.list),this.data.length>0){console.log("load_item render",this.data);for(const e of this.data){const t=this.creteNewItem(e.url,e.caption);this.list.insertBefore(t,this.addButton)}}return this.wrapper}save(e){const t=e.getElementsByClassName(this.CSS.item),n=[];if(t.length>0)for(const e of t)e.firstChild.value&&n.push({url:e.firstChild.value,caption:e.lastChild.value});return n}creteNewItem(e,t){const n=s("div",[this.CSS.block]),r=s("div",[this.CSS.item]),o=s("div",[this.CSS.removeBtn]),i=s("input",[this.CSS.inputUrl]),a=s("div",[this.CSS.imagePreloader]);return i.value=e,o.innerHTML=this.IconClose,o.addEventListener("click",()=>{n.remove()}),o.style.display="none",r.appendChild(i),r.appendChild(o),n.appendChild(r),e?this._createImage(e,r,t,o):r.appendChild(a),n}_createImage(e,t,n,r){const o=document.createElement("img"),i=s("input",[this.CSS.caption,this.CSS.input]);o.src=e,n&&(i.value=n),i.placeholder=this.config.captionPlaceholder,r.style.display="flex",t.appendChild(o),t.appendChild(i)}onUpload(e){e.success&&e.file?(console.log(this.list),console.log(this.list.childNodes.length),console.log(this.list.childNodes.length-1),this._createImage(e.file.url,this.list.childNodes[this.list.childNodes.length-2].firstChild,"",this.list.childNodes[this.list.childNodes.length-2].firstChild.childNodes[1]),this.list.childNodes[this.list.childNodes.length-2].firstChild.childNodes[2].style.backgroundImage="",this.list.childNodes[this.list.childNodes.length-2].firstChild.firstChild.value=e.file.url,this.list.childNodes[this.list.childNodes.length-2].firstChild.classList.add("cdxcarousel-item--empty")):this.uploadingFailed("incorrect response: "+JSON.stringify(e))}uploadingFailed(e){console.log("Gallery : uploading failed because of",e),this.api.notifier.show({message:this.api.i18n.t("Can not upload an image, try another"),style:"error"})}showPreloader(e){this.nodes.imagePreloader.style.backgroundImage=`url(${e})`}onSelectFile(){this.uploader.uploadSelectedFile({onPreview:e=>{const t=this.creteNewItem("","");t.firstChild.lastChild.style.backgroundImage=`url(${e})`,console.log("preload",t.firstChild.lastChild),this.list.insertBefore(t,this.addButton),console.log(e)}})}createAddButton(){const e=s("div",[this.CSS.button,this.CSS.addButton]),t=s("div",[this.CSS.block]);return e.innerHTML='<svg width="20" height="20" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">\n    <path d="M3.15 13.628A7.749 7.749 0 0 0 10 17.75a7.74 7.74 0 0 0 6.305-3.242l-2.387-2.127-2.765 2.244-4.389-4.496-3.614 3.5zm-.787-2.303l4.446-4.371 4.52 4.63 2.534-2.057 3.533 2.797c.23-.734.354-1.514.354-2.324a7.75 7.75 0 1 0-15.387 1.325zM10 20C4.477 20 0 15.523 0 10S4.477 0 10 0s10 4.477 10 10-4.477 10-10 10z"/>\n</svg> Add Image',e.addEventListener("click",()=>{this.onSelectFile()}),t.appendChild(e),t}}const s=function(e,t=null,n={}){const r=document.createElement(e);Array.isArray(t)?r.classList.add(...t):t&&r.classList.add(t);for(const e in n)r[e]=n[e];return r};var c=n(0);n(21);function u(e){return(u="function"==typeof Symbol&&"symbol"==typeof Symbol.iterator?function(e){return typeof e}:function(e){return e&&"function"==typeof Symbol&&e.constructor===Symbol&&e!==Symbol.prototype?"symbol":typeof e})(e)}function d(e,t){var n;if("undefined"==typeof Symbol||null==e[Symbol.iterator]){if(Array.isArray(e)||(n=function(e,t){if(!e)return;if("string"==typeof e)return f(e,t);var n=Object.prototype.toString.call(e).slice(8,-1);"Object"===n&&e.constructor&&(n=e.constructor.name);if("Map"===n||"Set"===n)return Array.from(e);if("Arguments"===n||/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n))return f(e,t)}(e))||t&&e&&"number"==typeof e.length){n&&(e=n);var r=0,o=function(){};return{s:o,n:function(){return r>=e.length?{done:!0}:{done:!1,value:e[r++]}},e:function(e){throw e},f:o}}throw new TypeError("Invalid attempt to iterate non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.")}var i,a=!0,l=!1;return{s:function(){n=e[Symbol.iterator]()},n:function(){var e=n.next();return a=e.done,e},e:function(e){l=!0,i=e},f:function(){try{a||null==n.return||n.return()}finally{if(l)throw i}}}}function f(e,t){(null==t||t>e.length)&&(t=e.length);for(var n=0,r=new Array(t);n<t;n++)r[n]=e[n];return r}function p(e,t){for(var n=0;n<t.length;n++){var r=t[n];r.enumerable=r.enumerable||!1,r.configurable=!0,"value"in r&&(r.writable=!0),Object.defineProperty(e,r.key,r)}}function h(e,t,n){return(h="undefined"!=typeof Reflect&&Reflect.get?Reflect.get:function(e,t,n){var r=function(e,t){for(;!Object.prototype.hasOwnProperty.call(e,t)&&null!==(e=v(e)););return e}(e,t);if(r){var o=Object.getOwnPropertyDescriptor(r,t);return o.get?o.get.call(n):o.value}})(e,t,n||e)}function m(e,t){return(m=Object.setPrototypeOf||function(e,t){return e.__proto__=t,e})(e,t)}function y(e){var t=function(){if("undefined"==typeof Reflect||!Reflect.construct)return!1;if(Reflect.construct.sham)return!1;if("function"==typeof Proxy)return!0;try{return Date.prototype.toString.call(Reflect.construct(Date,[],(function(){}))),!0}catch(e){return!1}}();return function(){var n,r=v(e);if(t){var o=v(this).constructor;n=Reflect.construct(r,arguments,o)}else n=r.apply(this,arguments);return g(this,n)}}function g(e,t){return!t||"object"!==u(t)&&"function"!=typeof t?function(e){if(void 0===e)throw new ReferenceError("this hasn't been initialised - super() hasn't been called");return e}(e):t}function v(e){return(v=Object.setPrototypeOf?Object.getPrototypeOf:function(e){return e.__proto__||Object.getPrototypeOf(e)})(e)}var b=function(e){!function(e,t){if("function"!=typeof t&&null!==t)throw new TypeError("Super expression must either be null or a function");e.prototype=Object.create(t&&t.prototype,{constructor:{value:e,writable:!0,configurable:!0}}),t&&m(e,t)}(i,e);var t,n,r,o=y(i);function i(e){var t,n=e.data,r=e.config,a=e.api,l=e.readOnly;return function(e,t){if(!(e instanceof t))throw new TypeError("Cannot call a class as a function")}(this,i),(t=o.call(this,{data:n,config:r,api:a,readOnly:l})).onSelectFile=r.onSelectFile||t.defaultOnSelectFile,t.onUploadFile=r.onUploadFile||null,t.nodes={},t}return t=i,r=[{key:"toolbox",get:function(){return{title:"Gallery",icon:'<svg width="38" height="18" viewBox="0 0 38 18" fill="none" xmlns="http://www.w3.org/2000/svg"><mask id="mask0" mask-type="alpha" maskUnits="userSpaceOnUse" x="10" y="0" width="18" height="18"><path fill-rule="evenodd" clip-rule="evenodd" d="M28 16V2C28 0.9 27.1 0 26 0H12C10.9 0 10 0.9 10 2V16C10 17.1 10.9 18 12 18H26C27.1 18 28 17.1 28 16V16ZM15.5 10.5L18 13.51L21.5 9L26 15H12L15.5 10.5V10.5Z"  /></mask><g mask="url(#mask0)"><rect x="10" width="18" height="18"  /></g><mask id="mask1" mask-type="alpha" maskUnits="userSpaceOnUse" x="0" y="3" width="7" height="12"><path fill-rule="evenodd" clip-rule="evenodd" d="M7 13.59L2.67341 9L7 4.41L5.66802 3L0 9L5.66802 15L7 13.59Z" fill="white"/></mask><g mask="url(#mask1)"><rect y="3" width="7.55735" height="12"  /></g><mask id="mask2" mask-type="alpha" maskUnits="userSpaceOnUse" x="31" y="3" width="7" height="12"><path fill-rule="evenodd" clip-rule="evenodd" d="M31 13.59L35.3266 9L31 4.41L32.332 3L38 9L32.332 15L31 13.59Z" fill="white"/></mask><g mask="url(#mask2)"><rect x="30.4426" y="2.25" width="7.55735" height="13" /></g></svg>\n'}}}],(n=[{key:"createAddButton",value:function(){return this.createImageInput()}},{key:"createImageInput",value:function(){return this.nodes.imagePreloader=c.a.element("div","image-tool__image-preloader"),this.nodes.imagePreloader.style.display="none",this.nodes.fileButton=c.a.fileButtons(this,["cdx-input-gallery"]),this.nodes.fileButton.appendChild(this.nodes.imagePreloader),this.nodes.fileButton}},{key:"onUpload",value:function(e){h(v(i.prototype),"onUpload",this).call(this,e),this.list.childNodes[this.list.childNodes.length-2].firstChild.firstChild.dataset.file=JSON.stringify(e.file),this.list.childNodes[this.list.childNodes.length-2].firstChild.lastChild.value=e.file.name}},{key:"render",value:function(){var e=this;if(h(v(i.prototype),"render",this).call(this),this.data.length>0){var t,n=d(this.data);try{var r=function(){var n=t.value;e.list.querySelectorAll(".cdxcarousel-inputUrl").forEach((function(e){e.dataset.file=JSON.stringify(n.file)}))};for(n.s();!(t=n.n()).done;)r()}catch(e){n.e(e)}finally{n.f()}}return this.wrapper}},{key:"isJson",value:function(e){try{JSON.parse(e)}catch(e){return!1}return!0}},{key:"save",value:function(e){var t=e.getElementsByClassName(this.CSS.item),n=[];if(t.length>0){var r,o=d(t);try{for(o.s();!(r=o.n()).done;){var i=r.value;i.firstChild.value&&n.push({file:i.firstChild.dataset.file&&this.isJson(i.firstChild.dataset.file)?JSON.parse(i.firstChild.dataset.file):{},url:i.firstChild.value,caption:i.lastChild.value})}}catch(e){o.e(e)}finally{o.f()}}return n}},{key:"onFileLoading",value:function(){var e=this.creteNewItem("","");this.list.insertBefore(e,this.addButton)}}])&&p(t.prototype,n),r&&p(t,r),i}(l)}]).default}));