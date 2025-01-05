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

/***/ "./src/Small/Small.ts":
/*!****************************!*\
  !*** ./src/Small/Small.ts ***!
  \****************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

eval("__webpack_require__.r(__webpack_exports__);\n/* harmony export */ __webpack_require__.d(__webpack_exports__, {\n/* harmony export */   \"default\": () => (/* binding */ Small)\n/* harmony export */ });\nclass Small {\n    button;\n    tag = 'SMALL';\n    api;\n    constructor(options) {\n        this.api = options.api;\n    }\n    static isInline = true;\n    render() {\n        this.button = document.createElement('button');\n        this.button.type = 'button';\n        this.button.classList.add(this.api.styles.inlineToolButton);\n        this.button.innerHTML = 'Aa';\n        return this.button;\n    }\n    /**\n     * Wrap/Unwrap selected fragment\n     *\n     * @param {Range} range - selected fragment\n     */\n    surround(range) {\n        if (!range)\n            return;\n        const termWrapper = this.api.selection.findParentTag(this.tag);\n        // If start or end of selection is in the highlighted block\n        if (termWrapper) {\n            this.unwrap(termWrapper);\n        }\n        else {\n            this.wrap(range);\n        }\n    }\n    wrap(range) {\n        const u = document.createElement(this.tag);\n        u.appendChild(range.extractContents());\n        range.insertNode(u);\n        this.api.selection.expandToTag(u);\n    }\n    unwrap(termWrapper) {\n        this.api.selection.expandToTag(termWrapper);\n        const sel = window.getSelection();\n        if (!sel)\n            return;\n        const range = sel.getRangeAt(0);\n        if (!range)\n            return;\n        const unwrappedContent = range.extractContents();\n        if (!unwrappedContent)\n            return;\n        // Remove empty term-tag\n        termWrapper.parentNode?.removeChild(termWrapper);\n        range.insertNode(unwrappedContent);\n        sel.removeAllRanges();\n        sel.addRange(range);\n    }\n    /**\n     * Check and change Term's state for current selection\n     */\n    checkState() {\n        const termTag = this.api.selection.findParentTag(this.tag);\n        this.button?.classList.toggle(this.api.styles.inlineToolButtonActive, !!termTag);\n        return !!termTag;\n    }\n    static get sanitize() {\n        return {\n            u: {},\n        };\n    }\n}\n\n\n//# sourceURL=webpack://@pushword/editorjs-tools/./src/Small/Small.ts?");

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
/******/ 	__webpack_modules__["./src/Small/Small.ts"](0, __webpack_exports__, __webpack_require__);
/******/ 	__webpack_exports__ = __webpack_exports__["default"];
/******/ 	
/******/ 	return __webpack_exports__;
/******/ })()
;
});