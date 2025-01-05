/*
 * ATTENTION: The "eval" devtool has been used (maybe by default in mode: "development").
 * This devtool is neither made for production nor for readable output files.
 * It uses "eval()" calls to create a separate source file in the browser devtools.
 * If you are trying to read the output file, select a different devtool (https://webpack.js.org/configuration/devtool/)
 * or disable the default devtool with "devtool: false".
 * If you are looking for production-ready output files, see mode: "production" (https://webpack.js.org/configuration/mode/).
 */
(function webpackUniversalModuleDefinition(root, factory) {
	if(typeof exports === 'object' && typeof module === 'object')
		module.exports = factory();
	else if(typeof define === 'function' && define.amd)
		define([], factory);
	else {
		var a = factory();
		for(var i in a) (typeof exports === 'object' ? exports : root)[i] = a[i];
	}
})(self, () => {
return /******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./src/Class/Class.js":
/*!****************************!*\
  !*** ./src/Class/Class.js ***!
  \****************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

eval("__webpack_require__.r(__webpack_exports__);\n/* harmony export */ __webpack_require__.d(__webpack_exports__, {\n/* harmony export */   \"default\": () => (/* binding */ Class)\n/* harmony export */ });\nfunction _typeof(o) { \"@babel/helpers - typeof\"; return _typeof = \"function\" == typeof Symbol && \"symbol\" == typeof Symbol.iterator ? function (o) { return typeof o; } : function (o) { return o && \"function\" == typeof Symbol && o.constructor === Symbol && o !== Symbol.prototype ? \"symbol\" : typeof o; }, _typeof(o); }\nfunction _classCallCheck(a, n) { if (!(a instanceof n)) throw new TypeError(\"Cannot call a class as a function\"); }\nfunction _defineProperties(e, r) { for (var t = 0; t < r.length; t++) { var o = r[t]; o.enumerable = o.enumerable || !1, o.configurable = !0, \"value\" in o && (o.writable = !0), Object.defineProperty(e, _toPropertyKey(o.key), o); } }\nfunction _createClass(e, r, t) { return r && _defineProperties(e.prototype, r), t && _defineProperties(e, t), Object.defineProperty(e, \"prototype\", { writable: !1 }), e; }\nfunction _toPropertyKey(t) { var i = _toPrimitive(t, \"string\"); return \"symbol\" == _typeof(i) ? i : i + \"\"; }\nfunction _toPrimitive(t, r) { if (\"object\" != _typeof(t) || !t) return t; var e = t[Symbol.toPrimitive]; if (void 0 !== e) { var i = e.call(t, r || \"default\"); if (\"object\" != _typeof(i)) return i; throw new TypeError(\"@@toPrimitive must return a primitive value.\"); } return (\"string\" === r ? String : Number)(t); }\nvar Class = /*#__PURE__*/function () {\n  /**\n   * Constructor\n   *\n   * @param api - Editor.js API\n   * @param data — previously saved data\n   */\n  function Class(_ref) {\n    var api = _ref.api,\n      data = _ref.data,\n      config = _ref.config,\n      block = _ref.block;\n    _classCallCheck(this, Class);\n    this.api = api;\n    this.data = data || '';\n    this.block = block;\n    this._CSS = {\n      classWrapper: 'cdx-anchor-tune-wrapper',\n      classIcon: 'cdx-anchor-tune-icon',\n      classInput: 'cdx-anchor-tune-input'\n    };\n  }\n\n  /**\n   * Rendering tune wrapper\n   * @returns {*}\n   */\n  return _createClass(Class, [{\n    key: \"getClass\",\n    value: function getClass() {\n      return this.data || '';\n    }\n  }, {\n    key: \"render\",\n    value: function render() {\n      var _this = this;\n      var value = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : null;\n      var wrapper = document.createElement('div');\n      wrapper.classList.add(this._CSS.classWrapper);\n      var wrapperIcon = document.createElement('div');\n      wrapperIcon.classList.add(this._CSS.classIcon);\n      wrapperIcon.innerHTML = '<svg xmlns=\"http://www.w3.org/2000/svg\" fill=\"none\" viewBox=\"0 0 24 24\" stroke-width=\"1.5\" stroke=\"currentColor\" data-slot=\"icon\" class=\"w-6 h-6\"> <path stroke-linecap=\"round\" stroke-linejoin=\"round\" d=\"M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z\" /><path stroke-linecap=\"round\" stroke-linejoin=\"round\" d=\"M6 6h.008v.008H6V6Z\" /></svg>';\n      var wrapperInput = document.createElement('textarea');\n      wrapperInput.placeholder = this.api.i18n.t('Class');\n      wrapperInput.classList.add(this._CSS.classInput);\n      wrapperInput.value = value ? value : this.getClass();\n      wrapperInput.addEventListener('input', function (event) {\n        var _this$block;\n        var value = event.target.value;\n\n        // Save value\n        if (value.length > 0) {\n          _this.data = value;\n        } else {\n          _this.data = '';\n        }\n        (_this$block = _this.block) === null || _this$block === void 0 || _this$block.dispatchChange();\n      });\n      this.input = wrapperInput;\n      wrapper.appendChild(wrapperIcon);\n      wrapper.appendChild(wrapperInput);\n      return wrapper;\n    }\n    /**\n     * Save\n     * @returns {*}\n     */\n  }, {\n    key: \"save\",\n    value: function save() {\n      return this.data;\n    }\n  }], [{\n    key: \"isTune\",\n    get:\n    /**\n     * @returns {bool}\n     */\n    function get() {\n      return true;\n    }\n  }]);\n}();\n\n\n//# sourceURL=webpack://@pushword/editorjs-tools/./src/Class/Class.js?");

/***/ })

/******/ 	});
/************************************************************************/
/******/ 	// The require scope
/******/ 	var __webpack_require__ = {};
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/************************************************************************/
/******/ 	
/******/ 	// startup
/******/ 	// Load entry module and return exports
/******/ 	// This entry module can't be inlined because the eval devtool is used.
/******/ 	var __webpack_exports__ = {};
/******/ 	__webpack_modules__["./src/Class/Class.js"](0, __webpack_exports__, __webpack_require__);
/******/ 	__webpack_exports__ = __webpack_exports__["default"];
/******/ 	
/******/ 	return __webpack_exports__;
/******/ })()
;
});