#!/usr/bin/env node
//#region \0rolldown/runtime.js
var __create = Object.create;
var __defProp = Object.defineProperty;
var __getOwnPropDesc = Object.getOwnPropertyDescriptor;
var __getOwnPropNames = Object.getOwnPropertyNames;
var __getProtoOf = Object.getPrototypeOf;
var __hasOwnProp = Object.prototype.hasOwnProperty;
var __commonJSMin = (cb, mod) => () => (mod || (cb((mod = { exports: {} }).exports, mod), cb = null), mod.exports);
var __copyProps = (to, from, except, desc) => {
	if (from && typeof from === "object" || typeof from === "function") for (var keys = __getOwnPropNames(from), i = 0, n = keys.length, key; i < n; i++) {
		key = keys[i];
		if (!__hasOwnProp.call(to, key) && key !== except) __defProp(to, key, {
			get: ((k) => from[k]).bind(null, key),
			enumerable: !(desc = __getOwnPropDesc(from, key)) || desc.enumerable
		});
	}
	return to;
};
var __toESM = (mod, isNodeMode, target) => (target = mod != null ? __create(__getProtoOf(mod)) : {}, __copyProps(isNodeMode || !mod || !mod.__esModule || !__hasOwnProp.call(mod, "default") ? __defProp(target, "default", {
	value: mod,
	enumerable: true
}) : target, mod));
//#endregion
//#region node_modules/@codexteam/icons/dist/index.mjs
var t = "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" fill=\"none\" viewBox=\"0 0 24 24\"><path stroke=\"currentColor\" stroke-linecap=\"round\" stroke-width=\"2\" d=\"M6 7L6 12M6 17L6 12M6 12L12 12M12 7V12M12 17L12 12\"/><path stroke=\"currentColor\" stroke-linecap=\"round\" stroke-width=\"2\" d=\"M16 11C16 10 19 9.5 19 12C19 13.9771 16.0684 13.9997 16.0012 16.8981C15.9999 16.9533 16.0448 17 16.1 17L19.3 17\"/></svg>";
var r$1 = "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" fill=\"none\" viewBox=\"0 0 24 24\"><path stroke=\"currentColor\" stroke-linecap=\"round\" stroke-width=\"2\" d=\"M6 7L6 12M6 17L6 12M6 12L12 12M12 7V12M12 17L12 12\"/><path stroke=\"currentColor\" stroke-linecap=\"round\" stroke-width=\"2\" d=\"M16 11C16 10.5 16.8323 10 17.6 10C18.3677 10 19.5 10.311 19.5 11.5C19.5 12.5315 18.7474 12.9022 18.548 12.9823C18.5378 12.9864 18.5395 13.0047 18.5503 13.0063C18.8115 13.0456 20 13.3065 20 14.8C20 16 19.5 17 17.8 17C17.8 17 16 17 16 16.3\"/></svg>";
var e$1 = "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" fill=\"none\" viewBox=\"0 0 24 24\"><path stroke=\"currentColor\" stroke-linecap=\"round\" stroke-width=\"2\" d=\"M6 7L6 12M6 17L6 12M6 12L12 12M12 7V12M12 17L12 12\"/><path stroke=\"currentColor\" stroke-linecap=\"round\" stroke-width=\"2\" d=\"M18 10L15.2834 14.8511C15.246 14.9178 15.294 15 15.3704 15C16.8489 15 18.7561 15 20.2 15M19 17C19 15.7187 19 14.8813 19 13.6\"/></svg>";
var n$2 = "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" fill=\"none\" viewBox=\"0 0 24 24\"><path stroke=\"currentColor\" stroke-linecap=\"round\" stroke-width=\"2\" d=\"M6 7L6 12M6 17L6 12M6 12L12 12M12 7V12M12 17L12 12\"/><path stroke=\"currentColor\" stroke-linecap=\"round\" stroke-width=\"2\" d=\"M16 15.9C16 15.9 16.3768 17 17.8 17C19.5 17 20 15.6199 20 14.7C20 12.7323 17.6745 12.0486 16.1635 12.9894C16.094 13.0327 16 12.9846 16 12.9027V10.1C16 10.0448 16.0448 10 16.1 10H19.8\"/></svg>";
var s = "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" fill=\"none\" viewBox=\"0 0 24 24\"><path stroke=\"currentColor\" stroke-linecap=\"round\" stroke-width=\"2\" d=\"M6 7L6 12M6 17L6 12M6 12L12 12M12 7V12M12 17L12 12\"/><path stroke=\"currentColor\" stroke-linecap=\"round\" stroke-width=\"2\" d=\"M19.5 10C16.5 10.5 16 13.3285 16 15M16 15V15C16 16.1046 16.8954 17 18 17H18.3246C19.3251 17 20.3191 16.3492 20.2522 15.3509C20.0612 12.4958 16 12.6611 16 15Z\"/></svg>";
var l$1 = "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" fill=\"none\" viewBox=\"0 0 24 24\"><path stroke=\"currentColor\" stroke-linecap=\"round\" stroke-width=\"2\" d=\"M18 7L6 7\"/><path stroke=\"currentColor\" stroke-linecap=\"round\" stroke-width=\"2\" d=\"M18 17H6\"/><path stroke=\"currentColor\" stroke-linecap=\"round\" stroke-width=\"2\" d=\"M16 12L8 12\"/></svg>";
var d = "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" fill=\"none\" viewBox=\"0 0 24 24\"><path stroke=\"currentColor\" stroke-linecap=\"round\" stroke-width=\"2\" d=\"M17 7L5 7\"/><path stroke=\"currentColor\" stroke-linecap=\"round\" stroke-width=\"2\" d=\"M17 17H5\"/><path stroke=\"currentColor\" stroke-linecap=\"round\" stroke-width=\"2\" d=\"M13 12L5 12\"/></svg>";
var k$2 = "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" fill=\"none\" viewBox=\"0 0 24 24\"><path stroke=\"currentColor\" stroke-linecap=\"round\" stroke-width=\"2\" d=\"M19 7L7 7\"/><path stroke=\"currentColor\" stroke-linecap=\"round\" stroke-width=\"2\" d=\"M19 17H7\"/><path stroke=\"currentColor\" stroke-linecap=\"round\" stroke-width=\"2\" d=\"M19 12L11 12\"/></svg>";
var H$2 = "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" fill=\"none\" viewBox=\"0 0 24 24\"><path stroke=\"currentColor\" stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M7 9L10 12M10 12L7 15M10 12H4\"/><path stroke=\"currentColor\" stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M17 9L14 12M14 12L17 15M14 12H20\"/></svg>";
var B$2 = "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" fill=\"none\" viewBox=\"0 0 24 24\"><path stroke=\"currentColor\" stroke-linecap=\"round\" stroke-width=\"2\" d=\"M8 8L12 12M12 12L16 16M12 12L16 8M12 12L8 16\"/></svg>";
var j$2 = "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" fill=\"none\" viewBox=\"0 0 24 24\"><path stroke=\"currentColor\" stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M14.8833 9.16666L18.2167 12.5M18.2167 12.5L14.8833 15.8333M18.2167 12.5H10.05C9.16594 12.5 8.31809 12.1488 7.69297 11.5237C7.06785 10.8986 6.71666 10.0507 6.71666 9.16666\"/></svg>";
var y$2 = "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" fill=\"none\" viewBox=\"0 0 24 24\"><path stroke=\"currentColor\" stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M14.9167 14.9167L11.5833 18.25M11.5833 18.25L8.25 14.9167M11.5833 18.25L11.5833 10.0833C11.5833 9.19928 11.9345 8.35143 12.5596 7.72631C13.1848 7.10119 14.0326 6.75 14.9167 6.75\"/></svg>";
var Z$2 = "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" fill=\"none\" viewBox=\"0 0 24 24\"><path stroke=\"currentColor\" stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M9.13333 14.9167L12.4667 18.25M12.4667 18.25L15.8 14.9167M12.4667 18.25L12.4667 10.0833C12.4667 9.19928 12.1155 8.35143 11.4904 7.72631C10.8652 7.10119 10.0174 6.75 9.13333 6.75\"/></svg>";
var D$2 = "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" fill=\"none\" viewBox=\"0 0 24 24\"><path stroke=\"currentColor\" stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M14.8833 15.8333L18.2167 12.5M18.2167 12.5L14.8833 9.16667M18.2167 12.5L10.05 12.5C9.16595 12.5 8.31811 12.8512 7.69299 13.4763C7.06787 14.1014 6.71667 14.9493 6.71667 15.8333\"/></svg>";
var U$2 = "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" fill=\"none\" viewBox=\"0 0 24 24\"><path stroke=\"currentColor\" stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M13.3236 8.43554L9.49533 12.1908C9.13119 12.5505 8.93118 13.043 8.9393 13.5598C8.94741 14.0767 9.163 14.5757 9.53862 14.947C9.91424 15.3182 10.4191 15.5314 10.9422 15.5397C11.4653 15.5479 11.9637 15.3504 12.3279 14.9908L16.1562 11.2355C16.8845 10.5161 17.2845 9.53123 17.2682 8.4975C17.252 7.46376 16.8208 6.46583 16.0696 5.72324C15.3184 4.98066 14.3086 4.55425 13.2624 4.53782C12.2162 4.52138 11.2193 4.91627 10.4911 5.63562L6.66277 9.39093C5.57035 10.4699 4.97032 11.9473 4.99467 13.4979C5.01903 15.0485 5.66578 16.5454 6.79264 17.6592C7.9195 18.7731 9.43417 19.4127 11.0034 19.4374C12.5727 19.462 14.068 18.8697 15.1604 17.7907L18.9887 14.0354\"/></svg>";
var G$2 = "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" fill=\"none\" viewBox=\"0 0 24 24\"><path stroke=\"currentColor\" stroke-linecap=\"round\" stroke-width=\"2\" d=\"M9 7L9 12M9 17V12M9 12L15 12M15 7V12M15 17L15 12\"/></svg>";
var O$2 = "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" fill=\"none\" viewBox=\"0 0 24 24\"><path stroke=\"currentColor\" stroke-linecap=\"round\" stroke-width=\"2.6\" d=\"M9.41 9.66H9.4\"/><path stroke=\"currentColor\" stroke-linecap=\"round\" stroke-width=\"2.6\" d=\"M14.6 9.66H14.59\"/><path stroke=\"currentColor\" stroke-linecap=\"round\" stroke-width=\"2.6\" d=\"M9.31 14.36H9.3\"/><path stroke=\"currentColor\" stroke-linecap=\"round\" stroke-width=\"2.6\" d=\"M14.6 14.36H14.59\"/></svg>";
var _$1 = "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" fill=\"none\" viewBox=\"0 0 24 24\"><rect width=\"14\" height=\"14\" x=\"5\" y=\"5\" stroke=\"currentColor\" stroke-width=\"2\" rx=\"4\"/><path stroke=\"currentColor\" stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M5.13968 15.32L8.69058 11.5661C9.02934 11.2036 9.48873 11 9.96774 11C10.4467 11 10.9061 11.2036 11.2449 11.5661L15.3871 16M13.5806 14.0664L15.0132 12.533C15.3519 12.1705 15.8113 11.9668 16.2903 11.9668C16.7693 11.9668 17.2287 12.1705 17.5675 12.533L18.841 13.9634\"/><path stroke=\"currentColor\" stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M13.7778 9.33331H13.7867\"/></svg>";
var o1 = "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" fill=\"none\" viewBox=\"0 0 24 24\"><path stroke=\"currentColor\" stroke-linecap=\"round\" stroke-width=\"2\" d=\"M12 7V12M12 17V12M17 12H12M12 12H7\"/></svg>";
var l1 = "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" fill=\"none\" viewBox=\"0 0 24 24\"><path stroke=\"currentColor\" stroke-width=\"2\" d=\"M11.8197 6.04369C11.8924 5.8925 12.1076 5.8925 12.1803 6.04369L13.9776 9.78496C14.0068 9.84564 14.0645 9.88759 14.1312 9.89657L18.2448 10.4498C18.411 10.4722 18.4776 10.6769 18.3562 10.7927L15.3535 13.6582C15.3048 13.7047 15.2827 13.7726 15.2948 13.8388L16.0398 17.922C16.0699 18.087 15.8957 18.2136 15.7481 18.1339L12 16.1124L8.25192 18.1339C8.10429 18.2136 7.93012 18.087 7.96022 17.922L8.7052 13.8388C8.71728 13.7726 8.69523 13.7047 8.64652 13.6582L5.64378 10.7927C5.52244 10.6769 5.58896 10.4722 5.7552 10.4498L9.86876 9.89657C9.93549 9.88759 9.99322 9.84564 10.0224 9.78496L11.8197 6.04369Z\"/></svg>";
var w1 = "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" fill=\"none\" viewBox=\"0 0 24 24\"><path stroke=\"currentColor\" stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M17 9L20 12L17 15\"/><path stroke=\"currentColor\" stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M14 12H20\"/><path stroke=\"currentColor\" stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M7 9L4 12L7 15\"/><path stroke=\"currentColor\" stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M4 12H10\"/></svg>";
var k1 = "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" fill=\"none\" viewBox=\"0 0 24 24\"><path stroke=\"currentColor\" stroke-width=\"2\" d=\"M5 10H19\"/><rect width=\"14\" height=\"14\" x=\"5\" y=\"5\" stroke=\"currentColor\" stroke-width=\"2\" rx=\"4\"/></svg>";
var c1 = "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" fill=\"none\" viewBox=\"0 0 24 24\"><path stroke=\"currentColor\" stroke-width=\"2\" d=\"M10 5V18.5\"/><path stroke=\"currentColor\" stroke-width=\"2\" d=\"M14 5V18.5\"/><path stroke=\"currentColor\" stroke-width=\"2\" d=\"M5 10H19\"/><path stroke=\"currentColor\" stroke-width=\"2\" d=\"M5 14H19\"/><rect width=\"14\" height=\"14\" x=\"5\" y=\"5\" stroke=\"currentColor\" stroke-width=\"2\" rx=\"4\"/></svg>";
var C1 = "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" fill=\"none\" viewBox=\"0 0 24 24\"><path stroke=\"currentColor\" stroke-width=\"2\" d=\"M10 5V18.5\"/><path stroke=\"currentColor\" stroke-width=\"2\" d=\"M5 10H19\"/><rect width=\"14\" height=\"14\" x=\"5\" y=\"5\" stroke=\"currentColor\" stroke-width=\"2\" rx=\"4\"/></svg>";
//#endregion
//#region node_modules/he/he.js
var require_he = /* @__PURE__ */ __commonJSMin(((exports, module) => {
	/*! https://mths.be/he v1.2.0 by @mathias | MIT license */
	(function(root) {
		var freeExports = typeof exports == "object" && exports;
		var freeModule = typeof module == "object" && module && module.exports == freeExports && module;
		var freeGlobal = typeof global == "object" && global;
		if (freeGlobal.global === freeGlobal || freeGlobal.window === freeGlobal) root = freeGlobal;
		var regexAstralSymbols = /[\uD800-\uDBFF][\uDC00-\uDFFF]/g;
		var regexAsciiWhitelist = /[\x01-\x7F]/g;
		var regexBmpWhitelist = /[\x01-\t\x0B\f\x0E-\x1F\x7F\x81\x8D\x8F\x90\x9D\xA0-\uFFFF]/g;
		var regexEncodeNonAscii = /<\u20D2|=\u20E5|>\u20D2|\u205F\u200A|\u219D\u0338|\u2202\u0338|\u2220\u20D2|\u2229\uFE00|\u222A\uFE00|\u223C\u20D2|\u223D\u0331|\u223E\u0333|\u2242\u0338|\u224B\u0338|\u224D\u20D2|\u224E\u0338|\u224F\u0338|\u2250\u0338|\u2261\u20E5|\u2264\u20D2|\u2265\u20D2|\u2266\u0338|\u2267\u0338|\u2268\uFE00|\u2269\uFE00|\u226A\u0338|\u226A\u20D2|\u226B\u0338|\u226B\u20D2|\u227F\u0338|\u2282\u20D2|\u2283\u20D2|\u228A\uFE00|\u228B\uFE00|\u228F\u0338|\u2290\u0338|\u2293\uFE00|\u2294\uFE00|\u22B4\u20D2|\u22B5\u20D2|\u22D8\u0338|\u22D9\u0338|\u22DA\uFE00|\u22DB\uFE00|\u22F5\u0338|\u22F9\u0338|\u2933\u0338|\u29CF\u0338|\u29D0\u0338|\u2A6D\u0338|\u2A70\u0338|\u2A7D\u0338|\u2A7E\u0338|\u2AA1\u0338|\u2AA2\u0338|\u2AAC\uFE00|\u2AAD\uFE00|\u2AAF\u0338|\u2AB0\u0338|\u2AC5\u0338|\u2AC6\u0338|\u2ACB\uFE00|\u2ACC\uFE00|\u2AFD\u20E5|[\xA0-\u0113\u0116-\u0122\u0124-\u012B\u012E-\u014D\u0150-\u017E\u0192\u01B5\u01F5\u0237\u02C6\u02C7\u02D8-\u02DD\u0311\u0391-\u03A1\u03A3-\u03A9\u03B1-\u03C9\u03D1\u03D2\u03D5\u03D6\u03DC\u03DD\u03F0\u03F1\u03F5\u03F6\u0401-\u040C\u040E-\u044F\u0451-\u045C\u045E\u045F\u2002-\u2005\u2007-\u2010\u2013-\u2016\u2018-\u201A\u201C-\u201E\u2020-\u2022\u2025\u2026\u2030-\u2035\u2039\u203A\u203E\u2041\u2043\u2044\u204F\u2057\u205F-\u2063\u20AC\u20DB\u20DC\u2102\u2105\u210A-\u2113\u2115-\u211E\u2122\u2124\u2127-\u2129\u212C\u212D\u212F-\u2131\u2133-\u2138\u2145-\u2148\u2153-\u215E\u2190-\u219B\u219D-\u21A7\u21A9-\u21AE\u21B0-\u21B3\u21B5-\u21B7\u21BA-\u21DB\u21DD\u21E4\u21E5\u21F5\u21FD-\u2205\u2207-\u2209\u220B\u220C\u220F-\u2214\u2216-\u2218\u221A\u221D-\u2238\u223A-\u2257\u2259\u225A\u225C\u225F-\u2262\u2264-\u228B\u228D-\u229B\u229D-\u22A5\u22A7-\u22B0\u22B2-\u22BB\u22BD-\u22DB\u22DE-\u22E3\u22E6-\u22F7\u22F9-\u22FE\u2305\u2306\u2308-\u2310\u2312\u2313\u2315\u2316\u231C-\u231F\u2322\u2323\u232D\u232E\u2336\u233D\u233F\u237C\u23B0\u23B1\u23B4-\u23B6\u23DC-\u23DF\u23E2\u23E7\u2423\u24C8\u2500\u2502\u250C\u2510\u2514\u2518\u251C\u2524\u252C\u2534\u253C\u2550-\u256C\u2580\u2584\u2588\u2591-\u2593\u25A1\u25AA\u25AB\u25AD\u25AE\u25B1\u25B3-\u25B5\u25B8\u25B9\u25BD-\u25BF\u25C2\u25C3\u25CA\u25CB\u25EC\u25EF\u25F8-\u25FC\u2605\u2606\u260E\u2640\u2642\u2660\u2663\u2665\u2666\u266A\u266D-\u266F\u2713\u2717\u2720\u2736\u2758\u2772\u2773\u27C8\u27C9\u27E6-\u27ED\u27F5-\u27FA\u27FC\u27FF\u2902-\u2905\u290C-\u2913\u2916\u2919-\u2920\u2923-\u292A\u2933\u2935-\u2939\u293C\u293D\u2945\u2948-\u294B\u294E-\u2976\u2978\u2979\u297B-\u297F\u2985\u2986\u298B-\u2996\u299A\u299C\u299D\u29A4-\u29B7\u29B9\u29BB\u29BC\u29BE-\u29C5\u29C9\u29CD-\u29D0\u29DC-\u29DE\u29E3-\u29E5\u29EB\u29F4\u29F6\u2A00-\u2A02\u2A04\u2A06\u2A0C\u2A0D\u2A10-\u2A17\u2A22-\u2A27\u2A29\u2A2A\u2A2D-\u2A31\u2A33-\u2A3C\u2A3F\u2A40\u2A42-\u2A4D\u2A50\u2A53-\u2A58\u2A5A-\u2A5D\u2A5F\u2A66\u2A6A\u2A6D-\u2A75\u2A77-\u2A9A\u2A9D-\u2AA2\u2AA4-\u2AB0\u2AB3-\u2AC8\u2ACB\u2ACC\u2ACF-\u2ADB\u2AE4\u2AE6-\u2AE9\u2AEB-\u2AF3\u2AFD\uFB00-\uFB04]|\uD835[\uDC9C\uDC9E\uDC9F\uDCA2\uDCA5\uDCA6\uDCA9-\uDCAC\uDCAE-\uDCB9\uDCBB\uDCBD-\uDCC3\uDCC5-\uDCCF\uDD04\uDD05\uDD07-\uDD0A\uDD0D-\uDD14\uDD16-\uDD1C\uDD1E-\uDD39\uDD3B-\uDD3E\uDD40-\uDD44\uDD46\uDD4A-\uDD50\uDD52-\uDD6B]/g;
		var encodeMap = {
			"­": "shy",
			"‌": "zwnj",
			"‍": "zwj",
			"‎": "lrm",
			"⁣": "ic",
			"⁢": "it",
			"⁡": "af",
			"‏": "rlm",
			"​": "ZeroWidthSpace",
			"⁠": "NoBreak",
			"̑": "DownBreve",
			"⃛": "tdot",
			"⃜": "DotDot",
			"	": "Tab",
			"\n": "NewLine",
			" ": "puncsp",
			" ": "MediumSpace",
			" ": "thinsp",
			" ": "hairsp",
			" ": "emsp13",
			" ": "ensp",
			" ": "emsp14",
			" ": "emsp",
			" ": "numsp",
			"\xA0": "nbsp",
			"  ": "ThickSpace",
			"‾": "oline",
			"_": "lowbar",
			"‐": "dash",
			"–": "ndash",
			"—": "mdash",
			"―": "horbar",
			",": "comma",
			";": "semi",
			"⁏": "bsemi",
			":": "colon",
			"⩴": "Colone",
			"!": "excl",
			"¡": "iexcl",
			"?": "quest",
			"¿": "iquest",
			".": "period",
			"‥": "nldr",
			"…": "mldr",
			"·": "middot",
			"'": "apos",
			"‘": "lsquo",
			"’": "rsquo",
			"‚": "sbquo",
			"‹": "lsaquo",
			"›": "rsaquo",
			"\"": "quot",
			"“": "ldquo",
			"”": "rdquo",
			"„": "bdquo",
			"«": "laquo",
			"»": "raquo",
			"(": "lpar",
			")": "rpar",
			"[": "lsqb",
			"]": "rsqb",
			"{": "lcub",
			"}": "rcub",
			"⌈": "lceil",
			"⌉": "rceil",
			"⌊": "lfloor",
			"⌋": "rfloor",
			"⦅": "lopar",
			"⦆": "ropar",
			"⦋": "lbrke",
			"⦌": "rbrke",
			"⦍": "lbrkslu",
			"⦎": "rbrksld",
			"⦏": "lbrksld",
			"⦐": "rbrkslu",
			"⦑": "langd",
			"⦒": "rangd",
			"⦓": "lparlt",
			"⦔": "rpargt",
			"⦕": "gtlPar",
			"⦖": "ltrPar",
			"⟦": "lobrk",
			"⟧": "robrk",
			"⟨": "lang",
			"⟩": "rang",
			"⟪": "Lang",
			"⟫": "Rang",
			"⟬": "loang",
			"⟭": "roang",
			"❲": "lbbrk",
			"❳": "rbbrk",
			"‖": "Vert",
			"§": "sect",
			"¶": "para",
			"@": "commat",
			"*": "ast",
			"/": "sol",
			"undefined": null,
			"&": "amp",
			"#": "num",
			"%": "percnt",
			"‰": "permil",
			"‱": "pertenk",
			"†": "dagger",
			"‡": "Dagger",
			"•": "bull",
			"⁃": "hybull",
			"′": "prime",
			"″": "Prime",
			"‴": "tprime",
			"⁗": "qprime",
			"‵": "bprime",
			"⁁": "caret",
			"`": "grave",
			"´": "acute",
			"˜": "tilde",
			"^": "Hat",
			"¯": "macr",
			"˘": "breve",
			"˙": "dot",
			"¨": "die",
			"˚": "ring",
			"˝": "dblac",
			"¸": "cedil",
			"˛": "ogon",
			"ˆ": "circ",
			"ˇ": "caron",
			"°": "deg",
			"©": "copy",
			"®": "reg",
			"℗": "copysr",
			"℘": "wp",
			"℞": "rx",
			"℧": "mho",
			"℩": "iiota",
			"←": "larr",
			"↚": "nlarr",
			"→": "rarr",
			"↛": "nrarr",
			"↑": "uarr",
			"↓": "darr",
			"↔": "harr",
			"↮": "nharr",
			"↕": "varr",
			"↖": "nwarr",
			"↗": "nearr",
			"↘": "searr",
			"↙": "swarr",
			"↝": "rarrw",
			"↝̸": "nrarrw",
			"↞": "Larr",
			"↟": "Uarr",
			"↠": "Rarr",
			"↡": "Darr",
			"↢": "larrtl",
			"↣": "rarrtl",
			"↤": "mapstoleft",
			"↥": "mapstoup",
			"↦": "map",
			"↧": "mapstodown",
			"↩": "larrhk",
			"↪": "rarrhk",
			"↫": "larrlp",
			"↬": "rarrlp",
			"↭": "harrw",
			"↰": "lsh",
			"↱": "rsh",
			"↲": "ldsh",
			"↳": "rdsh",
			"↵": "crarr",
			"↶": "cularr",
			"↷": "curarr",
			"↺": "olarr",
			"↻": "orarr",
			"↼": "lharu",
			"↽": "lhard",
			"↾": "uharr",
			"↿": "uharl",
			"⇀": "rharu",
			"⇁": "rhard",
			"⇂": "dharr",
			"⇃": "dharl",
			"⇄": "rlarr",
			"⇅": "udarr",
			"⇆": "lrarr",
			"⇇": "llarr",
			"⇈": "uuarr",
			"⇉": "rrarr",
			"⇊": "ddarr",
			"⇋": "lrhar",
			"⇌": "rlhar",
			"⇐": "lArr",
			"⇍": "nlArr",
			"⇑": "uArr",
			"⇒": "rArr",
			"⇏": "nrArr",
			"⇓": "dArr",
			"⇔": "iff",
			"⇎": "nhArr",
			"⇕": "vArr",
			"⇖": "nwArr",
			"⇗": "neArr",
			"⇘": "seArr",
			"⇙": "swArr",
			"⇚": "lAarr",
			"⇛": "rAarr",
			"⇝": "zigrarr",
			"⇤": "larrb",
			"⇥": "rarrb",
			"⇵": "duarr",
			"⇽": "loarr",
			"⇾": "roarr",
			"⇿": "hoarr",
			"∀": "forall",
			"∁": "comp",
			"∂": "part",
			"∂̸": "npart",
			"∃": "exist",
			"∄": "nexist",
			"∅": "empty",
			"∇": "Del",
			"∈": "in",
			"∉": "notin",
			"∋": "ni",
			"∌": "notni",
			"϶": "bepsi",
			"∏": "prod",
			"∐": "coprod",
			"∑": "sum",
			"+": "plus",
			"±": "pm",
			"÷": "div",
			"×": "times",
			"<": "lt",
			"≮": "nlt",
			"<⃒": "nvlt",
			"=": "equals",
			"≠": "ne",
			"=⃥": "bne",
			"⩵": "Equal",
			">": "gt",
			"≯": "ngt",
			">⃒": "nvgt",
			"¬": "not",
			"|": "vert",
			"¦": "brvbar",
			"−": "minus",
			"∓": "mp",
			"∔": "plusdo",
			"⁄": "frasl",
			"∖": "setmn",
			"∗": "lowast",
			"∘": "compfn",
			"√": "Sqrt",
			"∝": "prop",
			"∞": "infin",
			"∟": "angrt",
			"∠": "ang",
			"∠⃒": "nang",
			"∡": "angmsd",
			"∢": "angsph",
			"∣": "mid",
			"∤": "nmid",
			"∥": "par",
			"∦": "npar",
			"∧": "and",
			"∨": "or",
			"∩": "cap",
			"∩︀": "caps",
			"∪": "cup",
			"∪︀": "cups",
			"∫": "int",
			"∬": "Int",
			"∭": "tint",
			"⨌": "qint",
			"∮": "oint",
			"∯": "Conint",
			"∰": "Cconint",
			"∱": "cwint",
			"∲": "cwconint",
			"∳": "awconint",
			"∴": "there4",
			"∵": "becaus",
			"∶": "ratio",
			"∷": "Colon",
			"∸": "minusd",
			"∺": "mDDot",
			"∻": "homtht",
			"∼": "sim",
			"≁": "nsim",
			"∼⃒": "nvsim",
			"∽": "bsim",
			"∽̱": "race",
			"∾": "ac",
			"∾̳": "acE",
			"∿": "acd",
			"≀": "wr",
			"≂": "esim",
			"≂̸": "nesim",
			"≃": "sime",
			"≄": "nsime",
			"≅": "cong",
			"≇": "ncong",
			"≆": "simne",
			"≈": "ap",
			"≉": "nap",
			"≊": "ape",
			"≋": "apid",
			"≋̸": "napid",
			"≌": "bcong",
			"≍": "CupCap",
			"≭": "NotCupCap",
			"≍⃒": "nvap",
			"≎": "bump",
			"≎̸": "nbump",
			"≏": "bumpe",
			"≏̸": "nbumpe",
			"≐": "doteq",
			"≐̸": "nedot",
			"≑": "eDot",
			"≒": "efDot",
			"≓": "erDot",
			"≔": "colone",
			"≕": "ecolon",
			"≖": "ecir",
			"≗": "cire",
			"≙": "wedgeq",
			"≚": "veeeq",
			"≜": "trie",
			"≟": "equest",
			"≡": "equiv",
			"≢": "nequiv",
			"≡⃥": "bnequiv",
			"≤": "le",
			"≰": "nle",
			"≤⃒": "nvle",
			"≥": "ge",
			"≱": "nge",
			"≥⃒": "nvge",
			"≦": "lE",
			"≦̸": "nlE",
			"≧": "gE",
			"≧̸": "ngE",
			"≨︀": "lvnE",
			"≨": "lnE",
			"≩": "gnE",
			"≩︀": "gvnE",
			"≪": "ll",
			"≪̸": "nLtv",
			"≪⃒": "nLt",
			"≫": "gg",
			"≫̸": "nGtv",
			"≫⃒": "nGt",
			"≬": "twixt",
			"≲": "lsim",
			"≴": "nlsim",
			"≳": "gsim",
			"≵": "ngsim",
			"≶": "lg",
			"≸": "ntlg",
			"≷": "gl",
			"≹": "ntgl",
			"≺": "pr",
			"⊀": "npr",
			"≻": "sc",
			"⊁": "nsc",
			"≼": "prcue",
			"⋠": "nprcue",
			"≽": "sccue",
			"⋡": "nsccue",
			"≾": "prsim",
			"≿": "scsim",
			"≿̸": "NotSucceedsTilde",
			"⊂": "sub",
			"⊄": "nsub",
			"⊂⃒": "vnsub",
			"⊃": "sup",
			"⊅": "nsup",
			"⊃⃒": "vnsup",
			"⊆": "sube",
			"⊈": "nsube",
			"⊇": "supe",
			"⊉": "nsupe",
			"⊊︀": "vsubne",
			"⊊": "subne",
			"⊋︀": "vsupne",
			"⊋": "supne",
			"⊍": "cupdot",
			"⊎": "uplus",
			"⊏": "sqsub",
			"⊏̸": "NotSquareSubset",
			"⊐": "sqsup",
			"⊐̸": "NotSquareSuperset",
			"⊑": "sqsube",
			"⋢": "nsqsube",
			"⊒": "sqsupe",
			"⋣": "nsqsupe",
			"⊓": "sqcap",
			"⊓︀": "sqcaps",
			"⊔": "sqcup",
			"⊔︀": "sqcups",
			"⊕": "oplus",
			"⊖": "ominus",
			"⊗": "otimes",
			"⊘": "osol",
			"⊙": "odot",
			"⊚": "ocir",
			"⊛": "oast",
			"⊝": "odash",
			"⊞": "plusb",
			"⊟": "minusb",
			"⊠": "timesb",
			"⊡": "sdotb",
			"⊢": "vdash",
			"⊬": "nvdash",
			"⊣": "dashv",
			"⊤": "top",
			"⊥": "bot",
			"⊧": "models",
			"⊨": "vDash",
			"⊭": "nvDash",
			"⊩": "Vdash",
			"⊮": "nVdash",
			"⊪": "Vvdash",
			"⊫": "VDash",
			"⊯": "nVDash",
			"⊰": "prurel",
			"⊲": "vltri",
			"⋪": "nltri",
			"⊳": "vrtri",
			"⋫": "nrtri",
			"⊴": "ltrie",
			"⋬": "nltrie",
			"⊴⃒": "nvltrie",
			"⊵": "rtrie",
			"⋭": "nrtrie",
			"⊵⃒": "nvrtrie",
			"⊶": "origof",
			"⊷": "imof",
			"⊸": "mumap",
			"⊹": "hercon",
			"⊺": "intcal",
			"⊻": "veebar",
			"⊽": "barvee",
			"⊾": "angrtvb",
			"⊿": "lrtri",
			"⋀": "Wedge",
			"⋁": "Vee",
			"⋂": "xcap",
			"⋃": "xcup",
			"⋄": "diam",
			"⋅": "sdot",
			"⋆": "Star",
			"⋇": "divonx",
			"⋈": "bowtie",
			"⋉": "ltimes",
			"⋊": "rtimes",
			"⋋": "lthree",
			"⋌": "rthree",
			"⋍": "bsime",
			"⋎": "cuvee",
			"⋏": "cuwed",
			"⋐": "Sub",
			"⋑": "Sup",
			"⋒": "Cap",
			"⋓": "Cup",
			"⋔": "fork",
			"⋕": "epar",
			"⋖": "ltdot",
			"⋗": "gtdot",
			"⋘": "Ll",
			"⋘̸": "nLl",
			"⋙": "Gg",
			"⋙̸": "nGg",
			"⋚︀": "lesg",
			"⋚": "leg",
			"⋛": "gel",
			"⋛︀": "gesl",
			"⋞": "cuepr",
			"⋟": "cuesc",
			"⋦": "lnsim",
			"⋧": "gnsim",
			"⋨": "prnsim",
			"⋩": "scnsim",
			"⋮": "vellip",
			"⋯": "ctdot",
			"⋰": "utdot",
			"⋱": "dtdot",
			"⋲": "disin",
			"⋳": "isinsv",
			"⋴": "isins",
			"⋵": "isindot",
			"⋵̸": "notindot",
			"⋶": "notinvc",
			"⋷": "notinvb",
			"⋹": "isinE",
			"⋹̸": "notinE",
			"⋺": "nisd",
			"⋻": "xnis",
			"⋼": "nis",
			"⋽": "notnivc",
			"⋾": "notnivb",
			"⌅": "barwed",
			"⌆": "Barwed",
			"⌌": "drcrop",
			"⌍": "dlcrop",
			"⌎": "urcrop",
			"⌏": "ulcrop",
			"⌐": "bnot",
			"⌒": "profline",
			"⌓": "profsurf",
			"⌕": "telrec",
			"⌖": "target",
			"⌜": "ulcorn",
			"⌝": "urcorn",
			"⌞": "dlcorn",
			"⌟": "drcorn",
			"⌢": "frown",
			"⌣": "smile",
			"⌭": "cylcty",
			"⌮": "profalar",
			"⌶": "topbot",
			"⌽": "ovbar",
			"⌿": "solbar",
			"⍼": "angzarr",
			"⎰": "lmoust",
			"⎱": "rmoust",
			"⎴": "tbrk",
			"⎵": "bbrk",
			"⎶": "bbrktbrk",
			"⏜": "OverParenthesis",
			"⏝": "UnderParenthesis",
			"⏞": "OverBrace",
			"⏟": "UnderBrace",
			"⏢": "trpezium",
			"⏧": "elinters",
			"␣": "blank",
			"─": "boxh",
			"│": "boxv",
			"┌": "boxdr",
			"┐": "boxdl",
			"└": "boxur",
			"┘": "boxul",
			"├": "boxvr",
			"┤": "boxvl",
			"┬": "boxhd",
			"┴": "boxhu",
			"┼": "boxvh",
			"═": "boxH",
			"║": "boxV",
			"╒": "boxdR",
			"╓": "boxDr",
			"╔": "boxDR",
			"╕": "boxdL",
			"╖": "boxDl",
			"╗": "boxDL",
			"╘": "boxuR",
			"╙": "boxUr",
			"╚": "boxUR",
			"╛": "boxuL",
			"╜": "boxUl",
			"╝": "boxUL",
			"╞": "boxvR",
			"╟": "boxVr",
			"╠": "boxVR",
			"╡": "boxvL",
			"╢": "boxVl",
			"╣": "boxVL",
			"╤": "boxHd",
			"╥": "boxhD",
			"╦": "boxHD",
			"╧": "boxHu",
			"╨": "boxhU",
			"╩": "boxHU",
			"╪": "boxvH",
			"╫": "boxVh",
			"╬": "boxVH",
			"▀": "uhblk",
			"▄": "lhblk",
			"█": "block",
			"░": "blk14",
			"▒": "blk12",
			"▓": "blk34",
			"□": "squ",
			"▪": "squf",
			"▫": "EmptyVerySmallSquare",
			"▭": "rect",
			"▮": "marker",
			"▱": "fltns",
			"△": "xutri",
			"▴": "utrif",
			"▵": "utri",
			"▸": "rtrif",
			"▹": "rtri",
			"▽": "xdtri",
			"▾": "dtrif",
			"▿": "dtri",
			"◂": "ltrif",
			"◃": "ltri",
			"◊": "loz",
			"○": "cir",
			"◬": "tridot",
			"◯": "xcirc",
			"◸": "ultri",
			"◹": "urtri",
			"◺": "lltri",
			"◻": "EmptySmallSquare",
			"◼": "FilledSmallSquare",
			"★": "starf",
			"☆": "star",
			"☎": "phone",
			"♀": "female",
			"♂": "male",
			"♠": "spades",
			"♣": "clubs",
			"♥": "hearts",
			"♦": "diams",
			"♪": "sung",
			"✓": "check",
			"✗": "cross",
			"✠": "malt",
			"✶": "sext",
			"❘": "VerticalSeparator",
			"⟈": "bsolhsub",
			"⟉": "suphsol",
			"⟵": "xlarr",
			"⟶": "xrarr",
			"⟷": "xharr",
			"⟸": "xlArr",
			"⟹": "xrArr",
			"⟺": "xhArr",
			"⟼": "xmap",
			"⟿": "dzigrarr",
			"⤂": "nvlArr",
			"⤃": "nvrArr",
			"⤄": "nvHarr",
			"⤅": "Map",
			"⤌": "lbarr",
			"⤍": "rbarr",
			"⤎": "lBarr",
			"⤏": "rBarr",
			"⤐": "RBarr",
			"⤑": "DDotrahd",
			"⤒": "UpArrowBar",
			"⤓": "DownArrowBar",
			"⤖": "Rarrtl",
			"⤙": "latail",
			"⤚": "ratail",
			"⤛": "lAtail",
			"⤜": "rAtail",
			"⤝": "larrfs",
			"⤞": "rarrfs",
			"⤟": "larrbfs",
			"⤠": "rarrbfs",
			"⤣": "nwarhk",
			"⤤": "nearhk",
			"⤥": "searhk",
			"⤦": "swarhk",
			"⤧": "nwnear",
			"⤨": "toea",
			"⤩": "tosa",
			"⤪": "swnwar",
			"⤳": "rarrc",
			"⤳̸": "nrarrc",
			"⤵": "cudarrr",
			"⤶": "ldca",
			"⤷": "rdca",
			"⤸": "cudarrl",
			"⤹": "larrpl",
			"⤼": "curarrm",
			"⤽": "cularrp",
			"⥅": "rarrpl",
			"⥈": "harrcir",
			"⥉": "Uarrocir",
			"⥊": "lurdshar",
			"⥋": "ldrushar",
			"⥎": "LeftRightVector",
			"⥏": "RightUpDownVector",
			"⥐": "DownLeftRightVector",
			"⥑": "LeftUpDownVector",
			"⥒": "LeftVectorBar",
			"⥓": "RightVectorBar",
			"⥔": "RightUpVectorBar",
			"⥕": "RightDownVectorBar",
			"⥖": "DownLeftVectorBar",
			"⥗": "DownRightVectorBar",
			"⥘": "LeftUpVectorBar",
			"⥙": "LeftDownVectorBar",
			"⥚": "LeftTeeVector",
			"⥛": "RightTeeVector",
			"⥜": "RightUpTeeVector",
			"⥝": "RightDownTeeVector",
			"⥞": "DownLeftTeeVector",
			"⥟": "DownRightTeeVector",
			"⥠": "LeftUpTeeVector",
			"⥡": "LeftDownTeeVector",
			"⥢": "lHar",
			"⥣": "uHar",
			"⥤": "rHar",
			"⥥": "dHar",
			"⥦": "luruhar",
			"⥧": "ldrdhar",
			"⥨": "ruluhar",
			"⥩": "rdldhar",
			"⥪": "lharul",
			"⥫": "llhard",
			"⥬": "rharul",
			"⥭": "lrhard",
			"⥮": "udhar",
			"⥯": "duhar",
			"⥰": "RoundImplies",
			"⥱": "erarr",
			"⥲": "simrarr",
			"⥳": "larrsim",
			"⥴": "rarrsim",
			"⥵": "rarrap",
			"⥶": "ltlarr",
			"⥸": "gtrarr",
			"⥹": "subrarr",
			"⥻": "suplarr",
			"⥼": "lfisht",
			"⥽": "rfisht",
			"⥾": "ufisht",
			"⥿": "dfisht",
			"⦚": "vzigzag",
			"⦜": "vangrt",
			"⦝": "angrtvbd",
			"⦤": "ange",
			"⦥": "range",
			"⦦": "dwangle",
			"⦧": "uwangle",
			"⦨": "angmsdaa",
			"⦩": "angmsdab",
			"⦪": "angmsdac",
			"⦫": "angmsdad",
			"⦬": "angmsdae",
			"⦭": "angmsdaf",
			"⦮": "angmsdag",
			"⦯": "angmsdah",
			"⦰": "bemptyv",
			"⦱": "demptyv",
			"⦲": "cemptyv",
			"⦳": "raemptyv",
			"⦴": "laemptyv",
			"⦵": "ohbar",
			"⦶": "omid",
			"⦷": "opar",
			"⦹": "operp",
			"⦻": "olcross",
			"⦼": "odsold",
			"⦾": "olcir",
			"⦿": "ofcir",
			"⧀": "olt",
			"⧁": "ogt",
			"⧂": "cirscir",
			"⧃": "cirE",
			"⧄": "solb",
			"⧅": "bsolb",
			"⧉": "boxbox",
			"⧍": "trisb",
			"⧎": "rtriltri",
			"⧏": "LeftTriangleBar",
			"⧏̸": "NotLeftTriangleBar",
			"⧐": "RightTriangleBar",
			"⧐̸": "NotRightTriangleBar",
			"⧜": "iinfin",
			"⧝": "infintie",
			"⧞": "nvinfin",
			"⧣": "eparsl",
			"⧤": "smeparsl",
			"⧥": "eqvparsl",
			"⧫": "lozf",
			"⧴": "RuleDelayed",
			"⧶": "dsol",
			"⨀": "xodot",
			"⨁": "xoplus",
			"⨂": "xotime",
			"⨄": "xuplus",
			"⨆": "xsqcup",
			"⨍": "fpartint",
			"⨐": "cirfnint",
			"⨑": "awint",
			"⨒": "rppolint",
			"⨓": "scpolint",
			"⨔": "npolint",
			"⨕": "pointint",
			"⨖": "quatint",
			"⨗": "intlarhk",
			"⨢": "pluscir",
			"⨣": "plusacir",
			"⨤": "simplus",
			"⨥": "plusdu",
			"⨦": "plussim",
			"⨧": "plustwo",
			"⨩": "mcomma",
			"⨪": "minusdu",
			"⨭": "loplus",
			"⨮": "roplus",
			"⨯": "Cross",
			"⨰": "timesd",
			"⨱": "timesbar",
			"⨳": "smashp",
			"⨴": "lotimes",
			"⨵": "rotimes",
			"⨶": "otimesas",
			"⨷": "Otimes",
			"⨸": "odiv",
			"⨹": "triplus",
			"⨺": "triminus",
			"⨻": "tritime",
			"⨼": "iprod",
			"⨿": "amalg",
			"⩀": "capdot",
			"⩂": "ncup",
			"⩃": "ncap",
			"⩄": "capand",
			"⩅": "cupor",
			"⩆": "cupcap",
			"⩇": "capcup",
			"⩈": "cupbrcap",
			"⩉": "capbrcup",
			"⩊": "cupcup",
			"⩋": "capcap",
			"⩌": "ccups",
			"⩍": "ccaps",
			"⩐": "ccupssm",
			"⩓": "And",
			"⩔": "Or",
			"⩕": "andand",
			"⩖": "oror",
			"⩗": "orslope",
			"⩘": "andslope",
			"⩚": "andv",
			"⩛": "orv",
			"⩜": "andd",
			"⩝": "ord",
			"⩟": "wedbar",
			"⩦": "sdote",
			"⩪": "simdot",
			"⩭": "congdot",
			"⩭̸": "ncongdot",
			"⩮": "easter",
			"⩯": "apacir",
			"⩰": "apE",
			"⩰̸": "napE",
			"⩱": "eplus",
			"⩲": "pluse",
			"⩳": "Esim",
			"⩷": "eDDot",
			"⩸": "equivDD",
			"⩹": "ltcir",
			"⩺": "gtcir",
			"⩻": "ltquest",
			"⩼": "gtquest",
			"⩽": "les",
			"⩽̸": "nles",
			"⩾": "ges",
			"⩾̸": "nges",
			"⩿": "lesdot",
			"⪀": "gesdot",
			"⪁": "lesdoto",
			"⪂": "gesdoto",
			"⪃": "lesdotor",
			"⪄": "gesdotol",
			"⪅": "lap",
			"⪆": "gap",
			"⪇": "lne",
			"⪈": "gne",
			"⪉": "lnap",
			"⪊": "gnap",
			"⪋": "lEg",
			"⪌": "gEl",
			"⪍": "lsime",
			"⪎": "gsime",
			"⪏": "lsimg",
			"⪐": "gsiml",
			"⪑": "lgE",
			"⪒": "glE",
			"⪓": "lesges",
			"⪔": "gesles",
			"⪕": "els",
			"⪖": "egs",
			"⪗": "elsdot",
			"⪘": "egsdot",
			"⪙": "el",
			"⪚": "eg",
			"⪝": "siml",
			"⪞": "simg",
			"⪟": "simlE",
			"⪠": "simgE",
			"⪡": "LessLess",
			"⪡̸": "NotNestedLessLess",
			"⪢": "GreaterGreater",
			"⪢̸": "NotNestedGreaterGreater",
			"⪤": "glj",
			"⪥": "gla",
			"⪦": "ltcc",
			"⪧": "gtcc",
			"⪨": "lescc",
			"⪩": "gescc",
			"⪪": "smt",
			"⪫": "lat",
			"⪬": "smte",
			"⪬︀": "smtes",
			"⪭": "late",
			"⪭︀": "lates",
			"⪮": "bumpE",
			"⪯": "pre",
			"⪯̸": "npre",
			"⪰": "sce",
			"⪰̸": "nsce",
			"⪳": "prE",
			"⪴": "scE",
			"⪵": "prnE",
			"⪶": "scnE",
			"⪷": "prap",
			"⪸": "scap",
			"⪹": "prnap",
			"⪺": "scnap",
			"⪻": "Pr",
			"⪼": "Sc",
			"⪽": "subdot",
			"⪾": "supdot",
			"⪿": "subplus",
			"⫀": "supplus",
			"⫁": "submult",
			"⫂": "supmult",
			"⫃": "subedot",
			"⫄": "supedot",
			"⫅": "subE",
			"⫅̸": "nsubE",
			"⫆": "supE",
			"⫆̸": "nsupE",
			"⫇": "subsim",
			"⫈": "supsim",
			"⫋︀": "vsubnE",
			"⫋": "subnE",
			"⫌︀": "vsupnE",
			"⫌": "supnE",
			"⫏": "csub",
			"⫐": "csup",
			"⫑": "csube",
			"⫒": "csupe",
			"⫓": "subsup",
			"⫔": "supsub",
			"⫕": "subsub",
			"⫖": "supsup",
			"⫗": "suphsub",
			"⫘": "supdsub",
			"⫙": "forkv",
			"⫚": "topfork",
			"⫛": "mlcp",
			"⫤": "Dashv",
			"⫦": "Vdashl",
			"⫧": "Barv",
			"⫨": "vBar",
			"⫩": "vBarv",
			"⫫": "Vbar",
			"⫬": "Not",
			"⫭": "bNot",
			"⫮": "rnmid",
			"⫯": "cirmid",
			"⫰": "midcir",
			"⫱": "topcir",
			"⫲": "nhpar",
			"⫳": "parsim",
			"⫽": "parsl",
			"⫽⃥": "nparsl",
			"♭": "flat",
			"♮": "natur",
			"♯": "sharp",
			"¤": "curren",
			"¢": "cent",
			"$": "dollar",
			"£": "pound",
			"¥": "yen",
			"€": "euro",
			"¹": "sup1",
			"½": "half",
			"⅓": "frac13",
			"¼": "frac14",
			"⅕": "frac15",
			"⅙": "frac16",
			"⅛": "frac18",
			"²": "sup2",
			"⅔": "frac23",
			"⅖": "frac25",
			"³": "sup3",
			"¾": "frac34",
			"⅗": "frac35",
			"⅜": "frac38",
			"⅘": "frac45",
			"⅚": "frac56",
			"⅝": "frac58",
			"⅞": "frac78",
			"𝒶": "ascr",
			"𝕒": "aopf",
			"𝔞": "afr",
			"𝔸": "Aopf",
			"𝔄": "Afr",
			"𝒜": "Ascr",
			"ª": "ordf",
			"á": "aacute",
			"Á": "Aacute",
			"à": "agrave",
			"À": "Agrave",
			"ă": "abreve",
			"Ă": "Abreve",
			"â": "acirc",
			"Â": "Acirc",
			"å": "aring",
			"Å": "angst",
			"ä": "auml",
			"Ä": "Auml",
			"ã": "atilde",
			"Ã": "Atilde",
			"ą": "aogon",
			"Ą": "Aogon",
			"ā": "amacr",
			"Ā": "Amacr",
			"æ": "aelig",
			"Æ": "AElig",
			"𝒷": "bscr",
			"𝕓": "bopf",
			"𝔟": "bfr",
			"𝔹": "Bopf",
			"ℬ": "Bscr",
			"𝔅": "Bfr",
			"𝔠": "cfr",
			"𝒸": "cscr",
			"𝕔": "copf",
			"ℭ": "Cfr",
			"𝒞": "Cscr",
			"ℂ": "Copf",
			"ć": "cacute",
			"Ć": "Cacute",
			"ĉ": "ccirc",
			"Ĉ": "Ccirc",
			"č": "ccaron",
			"Č": "Ccaron",
			"ċ": "cdot",
			"Ċ": "Cdot",
			"ç": "ccedil",
			"Ç": "Ccedil",
			"℅": "incare",
			"𝔡": "dfr",
			"ⅆ": "dd",
			"𝕕": "dopf",
			"𝒹": "dscr",
			"𝒟": "Dscr",
			"𝔇": "Dfr",
			"ⅅ": "DD",
			"𝔻": "Dopf",
			"ď": "dcaron",
			"Ď": "Dcaron",
			"đ": "dstrok",
			"Đ": "Dstrok",
			"ð": "eth",
			"Ð": "ETH",
			"ⅇ": "ee",
			"ℯ": "escr",
			"𝔢": "efr",
			"𝕖": "eopf",
			"ℰ": "Escr",
			"𝔈": "Efr",
			"𝔼": "Eopf",
			"é": "eacute",
			"É": "Eacute",
			"è": "egrave",
			"È": "Egrave",
			"ê": "ecirc",
			"Ê": "Ecirc",
			"ě": "ecaron",
			"Ě": "Ecaron",
			"ë": "euml",
			"Ë": "Euml",
			"ė": "edot",
			"Ė": "Edot",
			"ę": "eogon",
			"Ę": "Eogon",
			"ē": "emacr",
			"Ē": "Emacr",
			"𝔣": "ffr",
			"𝕗": "fopf",
			"𝒻": "fscr",
			"𝔉": "Ffr",
			"𝔽": "Fopf",
			"ℱ": "Fscr",
			"ﬀ": "fflig",
			"ﬃ": "ffilig",
			"ﬄ": "ffllig",
			"ﬁ": "filig",
			"fj": "fjlig",
			"ﬂ": "fllig",
			"ƒ": "fnof",
			"ℊ": "gscr",
			"𝕘": "gopf",
			"𝔤": "gfr",
			"𝒢": "Gscr",
			"𝔾": "Gopf",
			"𝔊": "Gfr",
			"ǵ": "gacute",
			"ğ": "gbreve",
			"Ğ": "Gbreve",
			"ĝ": "gcirc",
			"Ĝ": "Gcirc",
			"ġ": "gdot",
			"Ġ": "Gdot",
			"Ģ": "Gcedil",
			"𝔥": "hfr",
			"ℎ": "planckh",
			"𝒽": "hscr",
			"𝕙": "hopf",
			"ℋ": "Hscr",
			"ℌ": "Hfr",
			"ℍ": "Hopf",
			"ĥ": "hcirc",
			"Ĥ": "Hcirc",
			"ℏ": "hbar",
			"ħ": "hstrok",
			"Ħ": "Hstrok",
			"𝕚": "iopf",
			"𝔦": "ifr",
			"𝒾": "iscr",
			"ⅈ": "ii",
			"𝕀": "Iopf",
			"ℐ": "Iscr",
			"ℑ": "Im",
			"í": "iacute",
			"Í": "Iacute",
			"ì": "igrave",
			"Ì": "Igrave",
			"î": "icirc",
			"Î": "Icirc",
			"ï": "iuml",
			"Ï": "Iuml",
			"ĩ": "itilde",
			"Ĩ": "Itilde",
			"İ": "Idot",
			"į": "iogon",
			"Į": "Iogon",
			"ī": "imacr",
			"Ī": "Imacr",
			"ĳ": "ijlig",
			"Ĳ": "IJlig",
			"ı": "imath",
			"𝒿": "jscr",
			"𝕛": "jopf",
			"𝔧": "jfr",
			"𝒥": "Jscr",
			"𝔍": "Jfr",
			"𝕁": "Jopf",
			"ĵ": "jcirc",
			"Ĵ": "Jcirc",
			"ȷ": "jmath",
			"𝕜": "kopf",
			"𝓀": "kscr",
			"𝔨": "kfr",
			"𝒦": "Kscr",
			"𝕂": "Kopf",
			"𝔎": "Kfr",
			"ķ": "kcedil",
			"Ķ": "Kcedil",
			"𝔩": "lfr",
			"𝓁": "lscr",
			"ℓ": "ell",
			"𝕝": "lopf",
			"ℒ": "Lscr",
			"𝔏": "Lfr",
			"𝕃": "Lopf",
			"ĺ": "lacute",
			"Ĺ": "Lacute",
			"ľ": "lcaron",
			"Ľ": "Lcaron",
			"ļ": "lcedil",
			"Ļ": "Lcedil",
			"ł": "lstrok",
			"Ł": "Lstrok",
			"ŀ": "lmidot",
			"Ŀ": "Lmidot",
			"𝔪": "mfr",
			"𝕞": "mopf",
			"𝓂": "mscr",
			"𝔐": "Mfr",
			"𝕄": "Mopf",
			"ℳ": "Mscr",
			"𝔫": "nfr",
			"𝕟": "nopf",
			"𝓃": "nscr",
			"ℕ": "Nopf",
			"𝒩": "Nscr",
			"𝔑": "Nfr",
			"ń": "nacute",
			"Ń": "Nacute",
			"ň": "ncaron",
			"Ň": "Ncaron",
			"ñ": "ntilde",
			"Ñ": "Ntilde",
			"ņ": "ncedil",
			"Ņ": "Ncedil",
			"№": "numero",
			"ŋ": "eng",
			"Ŋ": "ENG",
			"𝕠": "oopf",
			"𝔬": "ofr",
			"ℴ": "oscr",
			"𝒪": "Oscr",
			"𝔒": "Ofr",
			"𝕆": "Oopf",
			"º": "ordm",
			"ó": "oacute",
			"Ó": "Oacute",
			"ò": "ograve",
			"Ò": "Ograve",
			"ô": "ocirc",
			"Ô": "Ocirc",
			"ö": "ouml",
			"Ö": "Ouml",
			"ő": "odblac",
			"Ő": "Odblac",
			"õ": "otilde",
			"Õ": "Otilde",
			"ø": "oslash",
			"Ø": "Oslash",
			"ō": "omacr",
			"Ō": "Omacr",
			"œ": "oelig",
			"Œ": "OElig",
			"𝔭": "pfr",
			"𝓅": "pscr",
			"𝕡": "popf",
			"ℙ": "Popf",
			"𝔓": "Pfr",
			"𝒫": "Pscr",
			"𝕢": "qopf",
			"𝔮": "qfr",
			"𝓆": "qscr",
			"𝒬": "Qscr",
			"𝔔": "Qfr",
			"ℚ": "Qopf",
			"ĸ": "kgreen",
			"𝔯": "rfr",
			"𝕣": "ropf",
			"𝓇": "rscr",
			"ℛ": "Rscr",
			"ℜ": "Re",
			"ℝ": "Ropf",
			"ŕ": "racute",
			"Ŕ": "Racute",
			"ř": "rcaron",
			"Ř": "Rcaron",
			"ŗ": "rcedil",
			"Ŗ": "Rcedil",
			"𝕤": "sopf",
			"𝓈": "sscr",
			"𝔰": "sfr",
			"𝕊": "Sopf",
			"𝔖": "Sfr",
			"𝒮": "Sscr",
			"Ⓢ": "oS",
			"ś": "sacute",
			"Ś": "Sacute",
			"ŝ": "scirc",
			"Ŝ": "Scirc",
			"š": "scaron",
			"Š": "Scaron",
			"ş": "scedil",
			"Ş": "Scedil",
			"ß": "szlig",
			"𝔱": "tfr",
			"𝓉": "tscr",
			"𝕥": "topf",
			"𝒯": "Tscr",
			"𝔗": "Tfr",
			"𝕋": "Topf",
			"ť": "tcaron",
			"Ť": "Tcaron",
			"ţ": "tcedil",
			"Ţ": "Tcedil",
			"™": "trade",
			"ŧ": "tstrok",
			"Ŧ": "Tstrok",
			"𝓊": "uscr",
			"𝕦": "uopf",
			"𝔲": "ufr",
			"𝕌": "Uopf",
			"𝔘": "Ufr",
			"𝒰": "Uscr",
			"ú": "uacute",
			"Ú": "Uacute",
			"ù": "ugrave",
			"Ù": "Ugrave",
			"ŭ": "ubreve",
			"Ŭ": "Ubreve",
			"û": "ucirc",
			"Û": "Ucirc",
			"ů": "uring",
			"Ů": "Uring",
			"ü": "uuml",
			"Ü": "Uuml",
			"ű": "udblac",
			"Ű": "Udblac",
			"ũ": "utilde",
			"Ũ": "Utilde",
			"ų": "uogon",
			"Ų": "Uogon",
			"ū": "umacr",
			"Ū": "Umacr",
			"𝔳": "vfr",
			"𝕧": "vopf",
			"𝓋": "vscr",
			"𝔙": "Vfr",
			"𝕍": "Vopf",
			"𝒱": "Vscr",
			"𝕨": "wopf",
			"𝓌": "wscr",
			"𝔴": "wfr",
			"𝒲": "Wscr",
			"𝕎": "Wopf",
			"𝔚": "Wfr",
			"ŵ": "wcirc",
			"Ŵ": "Wcirc",
			"𝔵": "xfr",
			"𝓍": "xscr",
			"𝕩": "xopf",
			"𝕏": "Xopf",
			"𝔛": "Xfr",
			"𝒳": "Xscr",
			"𝔶": "yfr",
			"𝓎": "yscr",
			"𝕪": "yopf",
			"𝒴": "Yscr",
			"𝔜": "Yfr",
			"𝕐": "Yopf",
			"ý": "yacute",
			"Ý": "Yacute",
			"ŷ": "ycirc",
			"Ŷ": "Ycirc",
			"ÿ": "yuml",
			"Ÿ": "Yuml",
			"𝓏": "zscr",
			"𝔷": "zfr",
			"𝕫": "zopf",
			"ℨ": "Zfr",
			"ℤ": "Zopf",
			"𝒵": "Zscr",
			"ź": "zacute",
			"Ź": "Zacute",
			"ž": "zcaron",
			"Ž": "Zcaron",
			"ż": "zdot",
			"Ż": "Zdot",
			"Ƶ": "imped",
			"þ": "thorn",
			"Þ": "THORN",
			"ŉ": "napos",
			"α": "alpha",
			"Α": "Alpha",
			"β": "beta",
			"Β": "Beta",
			"γ": "gamma",
			"Γ": "Gamma",
			"δ": "delta",
			"Δ": "Delta",
			"ε": "epsi",
			"ϵ": "epsiv",
			"Ε": "Epsilon",
			"ϝ": "gammad",
			"Ϝ": "Gammad",
			"ζ": "zeta",
			"Ζ": "Zeta",
			"η": "eta",
			"Η": "Eta",
			"θ": "theta",
			"ϑ": "thetav",
			"Θ": "Theta",
			"ι": "iota",
			"Ι": "Iota",
			"κ": "kappa",
			"ϰ": "kappav",
			"Κ": "Kappa",
			"λ": "lambda",
			"Λ": "Lambda",
			"μ": "mu",
			"µ": "micro",
			"Μ": "Mu",
			"ν": "nu",
			"Ν": "Nu",
			"ξ": "xi",
			"Ξ": "Xi",
			"ο": "omicron",
			"Ο": "Omicron",
			"π": "pi",
			"ϖ": "piv",
			"Π": "Pi",
			"ρ": "rho",
			"ϱ": "rhov",
			"Ρ": "Rho",
			"σ": "sigma",
			"Σ": "Sigma",
			"ς": "sigmaf",
			"τ": "tau",
			"Τ": "Tau",
			"υ": "upsi",
			"Υ": "Upsilon",
			"ϒ": "Upsi",
			"φ": "phi",
			"ϕ": "phiv",
			"Φ": "Phi",
			"χ": "chi",
			"Χ": "Chi",
			"ψ": "psi",
			"Ψ": "Psi",
			"ω": "omega",
			"Ω": "ohm",
			"а": "acy",
			"А": "Acy",
			"б": "bcy",
			"Б": "Bcy",
			"в": "vcy",
			"В": "Vcy",
			"г": "gcy",
			"Г": "Gcy",
			"ѓ": "gjcy",
			"Ѓ": "GJcy",
			"д": "dcy",
			"Д": "Dcy",
			"ђ": "djcy",
			"Ђ": "DJcy",
			"е": "iecy",
			"Е": "IEcy",
			"ё": "iocy",
			"Ё": "IOcy",
			"є": "jukcy",
			"Є": "Jukcy",
			"ж": "zhcy",
			"Ж": "ZHcy",
			"з": "zcy",
			"З": "Zcy",
			"ѕ": "dscy",
			"Ѕ": "DScy",
			"и": "icy",
			"И": "Icy",
			"і": "iukcy",
			"І": "Iukcy",
			"ї": "yicy",
			"Ї": "YIcy",
			"й": "jcy",
			"Й": "Jcy",
			"ј": "jsercy",
			"Ј": "Jsercy",
			"к": "kcy",
			"К": "Kcy",
			"ќ": "kjcy",
			"Ќ": "KJcy",
			"л": "lcy",
			"Л": "Lcy",
			"љ": "ljcy",
			"Љ": "LJcy",
			"м": "mcy",
			"М": "Mcy",
			"н": "ncy",
			"Н": "Ncy",
			"њ": "njcy",
			"Њ": "NJcy",
			"о": "ocy",
			"О": "Ocy",
			"п": "pcy",
			"П": "Pcy",
			"р": "rcy",
			"Р": "Rcy",
			"с": "scy",
			"С": "Scy",
			"т": "tcy",
			"Т": "Tcy",
			"ћ": "tshcy",
			"Ћ": "TSHcy",
			"у": "ucy",
			"У": "Ucy",
			"ў": "ubrcy",
			"Ў": "Ubrcy",
			"ф": "fcy",
			"Ф": "Fcy",
			"х": "khcy",
			"Х": "KHcy",
			"ц": "tscy",
			"Ц": "TScy",
			"ч": "chcy",
			"Ч": "CHcy",
			"џ": "dzcy",
			"Џ": "DZcy",
			"ш": "shcy",
			"Ш": "SHcy",
			"щ": "shchcy",
			"Щ": "SHCHcy",
			"ъ": "hardcy",
			"Ъ": "HARDcy",
			"ы": "ycy",
			"Ы": "Ycy",
			"ь": "softcy",
			"Ь": "SOFTcy",
			"э": "ecy",
			"Э": "Ecy",
			"ю": "yucy",
			"Ю": "YUcy",
			"я": "yacy",
			"Я": "YAcy",
			"ℵ": "aleph",
			"ℶ": "beth",
			"ℷ": "gimel",
			"ℸ": "daleth"
		};
		var regexEscape = /["&'<>`]/g;
		var escapeMap = {
			"\"": "&quot;",
			"&": "&amp;",
			"'": "&#x27;",
			"<": "&lt;",
			">": "&gt;",
			"`": "&#x60;"
		};
		var regexInvalidEntity = /&#(?:[xX][^a-fA-F0-9]|[^0-9xX])/;
		var regexInvalidRawCodePoint = /[\0-\x08\x0B\x0E-\x1F\x7F-\x9F\uFDD0-\uFDEF\uFFFE\uFFFF]|[\uD83F\uD87F\uD8BF\uD8FF\uD93F\uD97F\uD9BF\uD9FF\uDA3F\uDA7F\uDABF\uDAFF\uDB3F\uDB7F\uDBBF\uDBFF][\uDFFE\uDFFF]|[\uD800-\uDBFF](?![\uDC00-\uDFFF])|(?:[^\uD800-\uDBFF]|^)[\uDC00-\uDFFF]/;
		var regexDecode = /&(CounterClockwiseContourIntegral|DoubleLongLeftRightArrow|ClockwiseContourIntegral|NotNestedGreaterGreater|NotSquareSupersetEqual|DiacriticalDoubleAcute|NotRightTriangleEqual|NotSucceedsSlantEqual|NotPrecedesSlantEqual|CloseCurlyDoubleQuote|NegativeVeryThinSpace|DoubleContourIntegral|FilledVerySmallSquare|CapitalDifferentialD|OpenCurlyDoubleQuote|EmptyVerySmallSquare|NestedGreaterGreater|DoubleLongRightArrow|NotLeftTriangleEqual|NotGreaterSlantEqual|ReverseUpEquilibrium|DoubleLeftRightArrow|NotSquareSubsetEqual|NotDoubleVerticalBar|RightArrowLeftArrow|NotGreaterFullEqual|NotRightTriangleBar|SquareSupersetEqual|DownLeftRightVector|DoubleLongLeftArrow|leftrightsquigarrow|LeftArrowRightArrow|NegativeMediumSpace|blacktriangleright|RightDownVectorBar|PrecedesSlantEqual|RightDoubleBracket|SucceedsSlantEqual|NotLeftTriangleBar|RightTriangleEqual|SquareIntersection|RightDownTeeVector|ReverseEquilibrium|NegativeThickSpace|longleftrightarrow|Longleftrightarrow|LongLeftRightArrow|DownRightTeeVector|DownRightVectorBar|GreaterSlantEqual|SquareSubsetEqual|LeftDownVectorBar|LeftDoubleBracket|VerticalSeparator|rightleftharpoons|NotGreaterGreater|NotSquareSuperset|blacktriangleleft|blacktriangledown|NegativeThinSpace|LeftDownTeeVector|NotLessSlantEqual|leftrightharpoons|DoubleUpDownArrow|DoubleVerticalBar|LeftTriangleEqual|FilledSmallSquare|twoheadrightarrow|NotNestedLessLess|DownLeftTeeVector|DownLeftVectorBar|RightAngleBracket|NotTildeFullEqual|NotReverseElement|RightUpDownVector|DiacriticalTilde|NotSucceedsTilde|circlearrowright|NotPrecedesEqual|rightharpoondown|DoubleRightArrow|NotSucceedsEqual|NonBreakingSpace|NotRightTriangle|LessEqualGreater|RightUpTeeVector|LeftAngleBracket|GreaterFullEqual|DownArrowUpArrow|RightUpVectorBar|twoheadleftarrow|GreaterEqualLess|downharpoonright|RightTriangleBar|ntrianglerighteq|NotSupersetEqual|LeftUpDownVector|DiacriticalAcute|rightrightarrows|vartriangleright|UpArrowDownArrow|DiacriticalGrave|UnderParenthesis|EmptySmallSquare|LeftUpVectorBar|leftrightarrows|DownRightVector|downharpoonleft|trianglerighteq|ShortRightArrow|OverParenthesis|DoubleLeftArrow|DoubleDownArrow|NotSquareSubset|bigtriangledown|ntrianglelefteq|UpperRightArrow|curvearrowright|vartriangleleft|NotLeftTriangle|nleftrightarrow|LowerRightArrow|NotHumpDownHump|NotGreaterTilde|rightthreetimes|LeftUpTeeVector|NotGreaterEqual|straightepsilon|LeftTriangleBar|rightsquigarrow|ContourIntegral|rightleftarrows|CloseCurlyQuote|RightDownVector|LeftRightVector|nLeftrightarrow|leftharpoondown|circlearrowleft|SquareSuperset|OpenCurlyQuote|hookrightarrow|HorizontalLine|DiacriticalDot|NotLessGreater|ntriangleright|DoubleRightTee|InvisibleComma|InvisibleTimes|LowerLeftArrow|DownLeftVector|NotSubsetEqual|curvearrowleft|trianglelefteq|NotVerticalBar|TildeFullEqual|downdownarrows|NotGreaterLess|RightTeeVector|ZeroWidthSpace|looparrowright|LongRightArrow|doublebarwedge|ShortLeftArrow|ShortDownArrow|RightVectorBar|GreaterGreater|ReverseElement|rightharpoonup|LessSlantEqual|leftthreetimes|upharpoonright|rightarrowtail|LeftDownVector|Longrightarrow|NestedLessLess|UpperLeftArrow|nshortparallel|leftleftarrows|leftrightarrow|Leftrightarrow|LeftRightArrow|longrightarrow|upharpoonleft|RightArrowBar|ApplyFunction|LeftTeeVector|leftarrowtail|NotEqualTilde|varsubsetneqq|varsupsetneqq|RightTeeArrow|SucceedsEqual|SucceedsTilde|LeftVectorBar|SupersetEqual|hookleftarrow|DifferentialD|VerticalTilde|VeryThinSpace|blacktriangle|bigtriangleup|LessFullEqual|divideontimes|leftharpoonup|UpEquilibrium|ntriangleleft|RightTriangle|measuredangle|shortparallel|longleftarrow|Longleftarrow|LongLeftArrow|DoubleLeftTee|Poincareplane|PrecedesEqual|triangleright|DoubleUpArrow|RightUpVector|fallingdotseq|looparrowleft|PrecedesTilde|NotTildeEqual|NotTildeTilde|smallsetminus|Proportional|triangleleft|triangledown|UnderBracket|NotHumpEqual|exponentiale|ExponentialE|NotLessTilde|HilbertSpace|RightCeiling|blacklozenge|varsupsetneq|HumpDownHump|GreaterEqual|VerticalLine|LeftTeeArrow|NotLessEqual|DownTeeArrow|LeftTriangle|varsubsetneq|Intersection|NotCongruent|DownArrowBar|LeftUpVector|LeftArrowBar|risingdotseq|GreaterTilde|RoundImplies|SquareSubset|ShortUpArrow|NotSuperset|quaternions|precnapprox|backepsilon|preccurlyeq|OverBracket|blacksquare|MediumSpace|VerticalBar|circledcirc|circleddash|CircleMinus|CircleTimes|LessGreater|curlyeqprec|curlyeqsucc|diamondsuit|UpDownArrow|Updownarrow|RuleDelayed|Rrightarrow|updownarrow|RightVector|nRightarrow|nrightarrow|eqslantless|LeftCeiling|Equilibrium|SmallCircle|expectation|NotSucceeds|thickapprox|GreaterLess|SquareUnion|NotPrecedes|NotLessLess|straightphi|succnapprox|succcurlyeq|SubsetEqual|sqsupseteq|Proportion|Laplacetrf|ImaginaryI|supsetneqq|NotGreater|gtreqqless|NotElement|ThickSpace|TildeEqual|TildeTilde|Fouriertrf|rmoustache|EqualTilde|eqslantgtr|UnderBrace|LeftVector|UpArrowBar|nLeftarrow|nsubseteqq|subsetneqq|nsupseteqq|nleftarrow|succapprox|lessapprox|UpTeeArrow|upuparrows|curlywedge|lesseqqgtr|varepsilon|varnothing|RightFloor|complement|CirclePlus|sqsubseteq|Lleftarrow|circledast|RightArrow|Rightarrow|rightarrow|lmoustache|Bernoullis|precapprox|mapstoleft|mapstodown|longmapsto|dotsquare|downarrow|DoubleDot|nsubseteq|supsetneq|leftarrow|nsupseteq|subsetneq|ThinSpace|ngeqslant|subseteqq|HumpEqual|NotSubset|triangleq|NotCupCap|lesseqgtr|heartsuit|TripleDot|Leftarrow|Coproduct|Congruent|varpropto|complexes|gvertneqq|LeftArrow|LessTilde|supseteqq|MinusPlus|CircleDot|nleqslant|NotExists|gtreqless|nparallel|UnionPlus|LeftFloor|checkmark|CenterDot|centerdot|Mellintrf|gtrapprox|bigotimes|OverBrace|spadesuit|therefore|pitchfork|rationals|PlusMinus|Backslash|Therefore|DownBreve|backsimeq|backprime|DownArrow|nshortmid|Downarrow|lvertneqq|eqvparsl|imagline|imagpart|infintie|integers|Integral|intercal|LessLess|Uarrocir|intlarhk|sqsupset|angmsdaf|sqsubset|llcorner|vartheta|cupbrcap|lnapprox|Superset|SuchThat|succnsim|succneqq|angmsdag|biguplus|curlyvee|trpezium|Succeeds|NotTilde|bigwedge|angmsdah|angrtvbd|triminus|cwconint|fpartint|lrcorner|smeparsl|subseteq|urcorner|lurdshar|laemptyv|DDotrahd|approxeq|ldrushar|awconint|mapstoup|backcong|shortmid|triangle|geqslant|gesdotol|timesbar|circledR|circledS|setminus|multimap|naturals|scpolint|ncongdot|RightTee|boxminus|gnapprox|boxtimes|andslope|thicksim|angmsdaa|varsigma|cirfnint|rtriltri|angmsdab|rppolint|angmsdac|barwedge|drbkarow|clubsuit|thetasym|bsolhsub|capbrcup|dzigrarr|doteqdot|DotEqual|dotminus|UnderBar|NotEqual|realpart|otimesas|ulcorner|hksearow|hkswarow|parallel|PartialD|elinters|emptyset|plusacir|bbrktbrk|angmsdad|pointint|bigoplus|angmsdae|Precedes|bigsqcup|varkappa|notindot|supseteq|precneqq|precnsim|profalar|profline|profsurf|leqslant|lesdotor|raemptyv|subplus|notnivb|notnivc|subrarr|zigrarr|vzigzag|submult|subedot|Element|between|cirscir|larrbfs|larrsim|lotimes|lbrksld|lbrkslu|lozenge|ldrdhar|dbkarow|bigcirc|epsilon|simrarr|simplus|ltquest|Epsilon|luruhar|gtquest|maltese|npolint|eqcolon|npreceq|bigodot|ddagger|gtrless|bnequiv|harrcir|ddotseq|equivDD|backsim|demptyv|nsqsube|nsqsupe|Upsilon|nsubset|upsilon|minusdu|nsucceq|swarrow|nsupset|coloneq|searrow|boxplus|napprox|natural|asympeq|alefsym|congdot|nearrow|bigstar|diamond|supplus|tritime|LeftTee|nvinfin|triplus|NewLine|nvltrie|nvrtrie|nwarrow|nexists|Diamond|ruluhar|Implies|supmult|angzarr|suplarr|suphsub|questeq|because|digamma|Because|olcross|bemptyv|omicron|Omicron|rotimes|NoBreak|intprod|angrtvb|orderof|uwangle|suphsol|lesdoto|orslope|DownTee|realine|cudarrl|rdldhar|OverBar|supedot|lessdot|supdsub|topfork|succsim|rbrkslu|rbrksld|pertenk|cudarrr|isindot|planckh|lessgtr|pluscir|gesdoto|plussim|plustwo|lesssim|cularrp|rarrsim|Cayleys|notinva|notinvb|notinvc|UpArrow|Uparrow|uparrow|NotLess|dwangle|precsim|Product|curarrm|Cconint|dotplus|rarrbfs|ccupssm|Cedilla|cemptyv|notniva|quatint|frac35|frac38|frac45|frac56|frac58|frac78|tridot|xoplus|gacute|gammad|Gammad|lfisht|lfloor|bigcup|sqsupe|gbreve|Gbreve|lharul|sqsube|sqcups|Gcedil|apacir|llhard|lmidot|Lmidot|lmoust|andand|sqcaps|approx|Abreve|spades|circeq|tprime|divide|topcir|Assign|topbot|gesdot|divonx|xuplus|timesd|gesles|atilde|solbar|SOFTcy|loplus|timesb|lowast|lowbar|dlcorn|dlcrop|softcy|dollar|lparlt|thksim|lrhard|Atilde|lsaquo|smashp|bigvee|thinsp|wreath|bkarow|lsquor|lstrok|Lstrok|lthree|ltimes|ltlarr|DotDot|simdot|ltrPar|weierp|xsqcup|angmsd|sigmav|sigmaf|zeetrf|Zcaron|zcaron|mapsto|vsupne|thetav|cirmid|marker|mcomma|Zacute|vsubnE|there4|gtlPar|vsubne|bottom|gtrarr|SHCHcy|shchcy|midast|midcir|middot|minusb|minusd|gtrdot|bowtie|sfrown|mnplus|models|colone|seswar|Colone|mstpos|searhk|gtrsim|nacute|Nacute|boxbox|telrec|hairsp|Tcedil|nbumpe|scnsim|ncaron|Ncaron|ncedil|Ncedil|hamilt|Scedil|nearhk|hardcy|HARDcy|tcedil|Tcaron|commat|nequiv|nesear|tcaron|target|hearts|nexist|varrho|scedil|Scaron|scaron|hellip|Sacute|sacute|hercon|swnwar|compfn|rtimes|rthree|rsquor|rsaquo|zacute|wedgeq|homtht|barvee|barwed|Barwed|rpargt|horbar|conint|swarhk|roplus|nltrie|hslash|hstrok|Hstrok|rmoust|Conint|bprime|hybull|hyphen|iacute|Iacute|supsup|supsub|supsim|varphi|coprod|brvbar|agrave|Supset|supset|igrave|Igrave|notinE|Agrave|iiiint|iinfin|copysr|wedbar|Verbar|vangrt|becaus|incare|verbar|inodot|bullet|drcorn|intcal|drcrop|cularr|vellip|Utilde|bumpeq|cupcap|dstrok|Dstrok|CupCap|cupcup|cupdot|eacute|Eacute|supdot|iquest|easter|ecaron|Ecaron|ecolon|isinsv|utilde|itilde|Itilde|curarr|succeq|Bumpeq|cacute|ulcrop|nparsl|Cacute|nprcue|egrave|Egrave|nrarrc|nrarrw|subsup|subsub|nrtrie|jsercy|nsccue|Jsercy|kappav|kcedil|Kcedil|subsim|ulcorn|nsimeq|egsdot|veebar|kgreen|capand|elsdot|Subset|subset|curren|aacute|lacute|Lacute|emptyv|ntilde|Ntilde|lagran|lambda|Lambda|capcap|Ugrave|langle|subdot|emsp13|numero|emsp14|nvdash|nvDash|nVdash|nVDash|ugrave|ufisht|nvHarr|larrfs|nvlArr|larrhk|larrlp|larrpl|nvrArr|Udblac|nwarhk|larrtl|nwnear|oacute|Oacute|latail|lAtail|sstarf|lbrace|odblac|Odblac|lbrack|udblac|odsold|eparsl|lcaron|Lcaron|ograve|Ograve|lcedil|Lcedil|Aacute|ssmile|ssetmn|squarf|ldquor|capcup|ominus|cylcty|rharul|eqcirc|dagger|rfloor|rfisht|Dagger|daleth|equals|origof|capdot|equest|dcaron|Dcaron|rdquor|oslash|Oslash|otilde|Otilde|otimes|Otimes|urcrop|Ubreve|ubreve|Yacute|Uacute|uacute|Rcedil|rcedil|urcorn|parsim|Rcaron|Vdashl|rcaron|Tstrok|percnt|period|permil|Exists|yacute|rbrack|rbrace|phmmat|ccaron|Ccaron|planck|ccedil|plankv|tstrok|female|plusdo|plusdu|ffilig|plusmn|ffllig|Ccedil|rAtail|dfisht|bernou|ratail|Rarrtl|rarrtl|angsph|rarrpl|rarrlp|rarrhk|xwedge|xotime|forall|ForAll|Vvdash|vsupnE|preceq|bigcap|frac12|frac13|frac14|primes|rarrfs|prnsim|frac15|Square|frac16|square|lesdot|frac18|frac23|propto|prurel|rarrap|rangle|puncsp|frac25|Racute|qprime|racute|lesges|frac34|abreve|AElig|eqsim|utdot|setmn|urtri|Equal|Uring|seArr|uring|searr|dashv|Dashv|mumap|nabla|iogon|Iogon|sdote|sdotb|scsim|napid|napos|equiv|natur|Acirc|dblac|erarr|nbump|iprod|erDot|ucirc|awint|esdot|angrt|ncong|isinE|scnap|Scirc|scirc|ndash|isins|Ubrcy|nearr|neArr|isinv|nedot|ubrcy|acute|Ycirc|iukcy|Iukcy|xutri|nesim|caret|jcirc|Jcirc|caron|twixt|ddarr|sccue|exist|jmath|sbquo|ngeqq|angst|ccaps|lceil|ngsim|UpTee|delta|Delta|rtrif|nharr|nhArr|nhpar|rtrie|jukcy|Jukcy|kappa|rsquo|Kappa|nlarr|nlArr|TSHcy|rrarr|aogon|Aogon|fflig|xrarr|tshcy|ccirc|nleqq|filig|upsih|nless|dharl|nlsim|fjlig|ropar|nltri|dharr|robrk|roarr|fllig|fltns|roang|rnmid|subnE|subne|lAarr|trisb|Ccirc|acirc|ccups|blank|VDash|forkv|Vdash|langd|cedil|blk12|blk14|laquo|strns|diams|notin|vDash|larrb|blk34|block|disin|uplus|vdash|vBarv|aelig|starf|Wedge|check|xrArr|lates|lbarr|lBarr|notni|lbbrk|bcong|frasl|lbrke|frown|vrtri|vprop|vnsup|gamma|Gamma|wedge|xodot|bdquo|srarr|doteq|ldquo|boxdl|boxdL|gcirc|Gcirc|boxDl|boxDL|boxdr|boxdR|boxDr|TRADE|trade|rlhar|boxDR|vnsub|npart|vltri|rlarr|boxhd|boxhD|nprec|gescc|nrarr|nrArr|boxHd|boxHD|boxhu|boxhU|nrtri|boxHu|clubs|boxHU|times|colon|Colon|gimel|xlArr|Tilde|nsime|tilde|nsmid|nspar|THORN|thorn|xlarr|nsube|nsubE|thkap|xhArr|comma|nsucc|boxul|boxuL|nsupe|nsupE|gneqq|gnsim|boxUl|boxUL|grave|boxur|boxuR|boxUr|boxUR|lescc|angle|bepsi|boxvh|varpi|boxvH|numsp|Theta|gsime|gsiml|theta|boxVh|boxVH|boxvl|gtcir|gtdot|boxvL|boxVl|boxVL|crarr|cross|Cross|nvsim|boxvr|nwarr|nwArr|sqsup|dtdot|Uogon|lhard|lharu|dtrif|ocirc|Ocirc|lhblk|duarr|odash|sqsub|Hacek|sqcup|llarr|duhar|oelig|OElig|ofcir|boxvR|uogon|lltri|boxVr|csube|uuarr|ohbar|csupe|ctdot|olarr|olcir|harrw|oline|sqcap|omacr|Omacr|omega|Omega|boxVR|aleph|lneqq|lnsim|loang|loarr|rharu|lobrk|hcirc|operp|oplus|rhard|Hcirc|orarr|Union|order|ecirc|Ecirc|cuepr|szlig|cuesc|breve|reals|eDDot|Breve|hoarr|lopar|utrif|rdquo|Umacr|umacr|efDot|swArr|ultri|alpha|rceil|ovbar|swarr|Wcirc|wcirc|smtes|smile|bsemi|lrarr|aring|parsl|lrhar|bsime|uhblk|lrtri|cupor|Aring|uharr|uharl|slarr|rbrke|bsolb|lsime|rbbrk|RBarr|lsimg|phone|rBarr|rbarr|icirc|lsquo|Icirc|emacr|Emacr|ratio|simne|plusb|simlE|simgE|simeq|pluse|ltcir|ltdot|empty|xharr|xdtri|iexcl|Alpha|ltrie|rarrw|pound|ltrif|xcirc|bumpe|prcue|bumpE|asymp|amacr|cuvee|Sigma|sigma|iiint|udhar|iiota|ijlig|IJlig|supnE|imacr|Imacr|prime|Prime|image|prnap|eogon|Eogon|rarrc|mdash|mDDot|cuwed|imath|supne|imped|Amacr|udarr|prsim|micro|rarrb|cwint|raquo|infin|eplus|range|rangd|Ucirc|radic|minus|amalg|veeeq|rAarr|epsiv|ycirc|quest|sharp|quot|zwnj|Qscr|race|qscr|Qopf|qopf|qint|rang|Rang|Zscr|zscr|Zopf|zopf|rarr|rArr|Rarr|Pscr|pscr|prop|prod|prnE|prec|ZHcy|zhcy|prap|Zeta|zeta|Popf|popf|Zdot|plus|zdot|Yuml|yuml|phiv|YUcy|yucy|Yscr|yscr|perp|Yopf|yopf|part|para|YIcy|Ouml|rcub|yicy|YAcy|rdca|ouml|osol|Oscr|rdsh|yacy|real|oscr|xvee|andd|rect|andv|Xscr|oror|ordm|ordf|xscr|ange|aopf|Aopf|rHar|Xopf|opar|Oopf|xopf|xnis|rhov|oopf|omid|xmap|oint|apid|apos|ogon|ascr|Ascr|odot|odiv|xcup|xcap|ocir|oast|nvlt|nvle|nvgt|nvge|nvap|Wscr|wscr|auml|ntlg|ntgl|nsup|nsub|nsim|Nscr|nscr|nsce|Wopf|ring|npre|wopf|npar|Auml|Barv|bbrk|Nopf|nopf|nmid|nLtv|beta|ropf|Ropf|Beta|beth|nles|rpar|nleq|bnot|bNot|nldr|NJcy|rscr|Rscr|Vscr|vscr|rsqb|njcy|bopf|nisd|Bopf|rtri|Vopf|nGtv|ngtr|vopf|boxh|boxH|boxv|nges|ngeq|boxV|bscr|scap|Bscr|bsim|Vert|vert|bsol|bull|bump|caps|cdot|ncup|scnE|ncap|nbsp|napE|Cdot|cent|sdot|Vbar|nang|vBar|chcy|Mscr|mscr|sect|semi|CHcy|Mopf|mopf|sext|circ|cire|mldr|mlcp|cirE|comp|shcy|SHcy|vArr|varr|cong|copf|Copf|copy|COPY|malt|male|macr|lvnE|cscr|ltri|sime|ltcc|simg|Cscr|siml|csub|Uuml|lsqb|lsim|uuml|csup|Lscr|lscr|utri|smid|lpar|cups|smte|lozf|darr|Lopf|Uscr|solb|lopf|sopf|Sopf|lneq|uscr|spar|dArr|lnap|Darr|dash|Sqrt|LJcy|ljcy|lHar|dHar|Upsi|upsi|diam|lesg|djcy|DJcy|leqq|dopf|Dopf|dscr|Dscr|dscy|ldsh|ldca|squf|DScy|sscr|Sscr|dsol|lcub|late|star|Star|Uopf|Larr|lArr|larr|uopf|dtri|dzcy|sube|subE|Lang|lang|Kscr|kscr|Kopf|kopf|KJcy|kjcy|KHcy|khcy|DZcy|ecir|edot|eDot|Jscr|jscr|succ|Jopf|jopf|Edot|uHar|emsp|ensp|Iuml|iuml|eopf|isin|Iscr|iscr|Eopf|epar|sung|epsi|escr|sup1|sup2|sup3|Iota|iota|supe|supE|Iopf|iopf|IOcy|iocy|Escr|esim|Esim|imof|Uarr|QUOT|uArr|uarr|euml|IEcy|iecy|Idot|Euml|euro|excl|Hscr|hscr|Hopf|hopf|TScy|tscy|Tscr|hbar|tscr|flat|tbrk|fnof|hArr|harr|half|fopf|Fopf|tdot|gvnE|fork|trie|gtcc|fscr|Fscr|gdot|gsim|Gscr|gscr|Gopf|gopf|gneq|Gdot|tosa|gnap|Topf|topf|geqq|toea|GJcy|gjcy|tint|gesl|mid|Sfr|ggg|top|ges|gla|glE|glj|geq|gne|gEl|gel|gnE|Gcy|gcy|gap|Tfr|tfr|Tcy|tcy|Hat|Tau|Ffr|tau|Tab|hfr|Hfr|ffr|Fcy|fcy|icy|Icy|iff|ETH|eth|ifr|Ifr|Eta|eta|int|Int|Sup|sup|ucy|Ucy|Sum|sum|jcy|ENG|ufr|Ufr|eng|Jcy|jfr|els|ell|egs|Efr|efr|Jfr|uml|kcy|Kcy|Ecy|ecy|kfr|Kfr|lap|Sub|sub|lat|lcy|Lcy|leg|Dot|dot|lEg|leq|les|squ|div|die|lfr|Lfr|lgE|Dfr|dfr|Del|deg|Dcy|dcy|lne|lnE|sol|loz|smt|Cup|lrm|cup|lsh|Lsh|sim|shy|map|Map|mcy|Mcy|mfr|Mfr|mho|gfr|Gfr|sfr|cir|Chi|chi|nap|Cfr|vcy|Vcy|cfr|Scy|scy|ncy|Ncy|vee|Vee|Cap|cap|nfr|scE|sce|Nfr|nge|ngE|nGg|vfr|Vfr|ngt|bot|nGt|nis|niv|Rsh|rsh|nle|nlE|bne|Bfr|bfr|nLl|nlt|nLt|Bcy|bcy|not|Not|rlm|wfr|Wfr|npr|nsc|num|ocy|ast|Ocy|ofr|xfr|Xfr|Ofr|ogt|ohm|apE|olt|Rho|ape|rho|Rfr|rfr|ord|REG|ang|reg|orv|And|and|AMP|Rcy|amp|Afr|ycy|Ycy|yen|yfr|Yfr|rcy|par|pcy|Pcy|pfr|Pfr|phi|Phi|afr|Acy|acy|zcy|Zcy|piv|acE|acd|zfr|Zfr|pre|prE|psi|Psi|qfr|Qfr|zwj|Or|ge|Gg|gt|gg|el|oS|lt|Lt|LT|Re|lg|gl|eg|ne|Im|it|le|DD|wp|wr|nu|Nu|dd|lE|Sc|sc|pi|Pi|ee|af|ll|Ll|rx|gE|xi|pm|Xi|ic|pr|Pr|in|ni|mp|mu|ac|Mu|or|ap|Gt|GT|ii);|&(Aacute|Agrave|Atilde|Ccedil|Eacute|Egrave|Iacute|Igrave|Ntilde|Oacute|Ograve|Oslash|Otilde|Uacute|Ugrave|Yacute|aacute|agrave|atilde|brvbar|ccedil|curren|divide|eacute|egrave|frac12|frac14|frac34|iacute|igrave|iquest|middot|ntilde|oacute|ograve|oslash|otilde|plusmn|uacute|ugrave|yacute|AElig|Acirc|Aring|Ecirc|Icirc|Ocirc|THORN|Ucirc|acirc|acute|aelig|aring|cedil|ecirc|icirc|iexcl|laquo|micro|ocirc|pound|raquo|szlig|thorn|times|ucirc|Auml|COPY|Euml|Iuml|Ouml|QUOT|Uuml|auml|cent|copy|euml|iuml|macr|nbsp|ordf|ordm|ouml|para|quot|sect|sup1|sup2|sup3|uuml|yuml|AMP|ETH|REG|amp|deg|eth|not|reg|shy|uml|yen|GT|LT|gt|lt)(?!;)([=a-zA-Z0-9]?)|&#([0-9]+)(;?)|&#[xX]([a-fA-F0-9]+)(;?)|&([0-9a-zA-Z]+)/g;
		var decodeMap = {
			"aacute": "á",
			"Aacute": "Á",
			"abreve": "ă",
			"Abreve": "Ă",
			"ac": "∾",
			"acd": "∿",
			"acE": "∾̳",
			"acirc": "â",
			"Acirc": "Â",
			"acute": "´",
			"acy": "а",
			"Acy": "А",
			"aelig": "æ",
			"AElig": "Æ",
			"af": "⁡",
			"afr": "𝔞",
			"Afr": "𝔄",
			"agrave": "à",
			"Agrave": "À",
			"alefsym": "ℵ",
			"aleph": "ℵ",
			"alpha": "α",
			"Alpha": "Α",
			"amacr": "ā",
			"Amacr": "Ā",
			"amalg": "⨿",
			"amp": "&",
			"AMP": "&",
			"and": "∧",
			"And": "⩓",
			"andand": "⩕",
			"andd": "⩜",
			"andslope": "⩘",
			"andv": "⩚",
			"ang": "∠",
			"ange": "⦤",
			"angle": "∠",
			"angmsd": "∡",
			"angmsdaa": "⦨",
			"angmsdab": "⦩",
			"angmsdac": "⦪",
			"angmsdad": "⦫",
			"angmsdae": "⦬",
			"angmsdaf": "⦭",
			"angmsdag": "⦮",
			"angmsdah": "⦯",
			"angrt": "∟",
			"angrtvb": "⊾",
			"angrtvbd": "⦝",
			"angsph": "∢",
			"angst": "Å",
			"angzarr": "⍼",
			"aogon": "ą",
			"Aogon": "Ą",
			"aopf": "𝕒",
			"Aopf": "𝔸",
			"ap": "≈",
			"apacir": "⩯",
			"ape": "≊",
			"apE": "⩰",
			"apid": "≋",
			"apos": "'",
			"ApplyFunction": "⁡",
			"approx": "≈",
			"approxeq": "≊",
			"aring": "å",
			"Aring": "Å",
			"ascr": "𝒶",
			"Ascr": "𝒜",
			"Assign": "≔",
			"ast": "*",
			"asymp": "≈",
			"asympeq": "≍",
			"atilde": "ã",
			"Atilde": "Ã",
			"auml": "ä",
			"Auml": "Ä",
			"awconint": "∳",
			"awint": "⨑",
			"backcong": "≌",
			"backepsilon": "϶",
			"backprime": "‵",
			"backsim": "∽",
			"backsimeq": "⋍",
			"Backslash": "∖",
			"Barv": "⫧",
			"barvee": "⊽",
			"barwed": "⌅",
			"Barwed": "⌆",
			"barwedge": "⌅",
			"bbrk": "⎵",
			"bbrktbrk": "⎶",
			"bcong": "≌",
			"bcy": "б",
			"Bcy": "Б",
			"bdquo": "„",
			"becaus": "∵",
			"because": "∵",
			"Because": "∵",
			"bemptyv": "⦰",
			"bepsi": "϶",
			"bernou": "ℬ",
			"Bernoullis": "ℬ",
			"beta": "β",
			"Beta": "Β",
			"beth": "ℶ",
			"between": "≬",
			"bfr": "𝔟",
			"Bfr": "𝔅",
			"bigcap": "⋂",
			"bigcirc": "◯",
			"bigcup": "⋃",
			"bigodot": "⨀",
			"bigoplus": "⨁",
			"bigotimes": "⨂",
			"bigsqcup": "⨆",
			"bigstar": "★",
			"bigtriangledown": "▽",
			"bigtriangleup": "△",
			"biguplus": "⨄",
			"bigvee": "⋁",
			"bigwedge": "⋀",
			"bkarow": "⤍",
			"blacklozenge": "⧫",
			"blacksquare": "▪",
			"blacktriangle": "▴",
			"blacktriangledown": "▾",
			"blacktriangleleft": "◂",
			"blacktriangleright": "▸",
			"blank": "␣",
			"blk12": "▒",
			"blk14": "░",
			"blk34": "▓",
			"block": "█",
			"bne": "=⃥",
			"bnequiv": "≡⃥",
			"bnot": "⌐",
			"bNot": "⫭",
			"bopf": "𝕓",
			"Bopf": "𝔹",
			"bot": "⊥",
			"bottom": "⊥",
			"bowtie": "⋈",
			"boxbox": "⧉",
			"boxdl": "┐",
			"boxdL": "╕",
			"boxDl": "╖",
			"boxDL": "╗",
			"boxdr": "┌",
			"boxdR": "╒",
			"boxDr": "╓",
			"boxDR": "╔",
			"boxh": "─",
			"boxH": "═",
			"boxhd": "┬",
			"boxhD": "╥",
			"boxHd": "╤",
			"boxHD": "╦",
			"boxhu": "┴",
			"boxhU": "╨",
			"boxHu": "╧",
			"boxHU": "╩",
			"boxminus": "⊟",
			"boxplus": "⊞",
			"boxtimes": "⊠",
			"boxul": "┘",
			"boxuL": "╛",
			"boxUl": "╜",
			"boxUL": "╝",
			"boxur": "└",
			"boxuR": "╘",
			"boxUr": "╙",
			"boxUR": "╚",
			"boxv": "│",
			"boxV": "║",
			"boxvh": "┼",
			"boxvH": "╪",
			"boxVh": "╫",
			"boxVH": "╬",
			"boxvl": "┤",
			"boxvL": "╡",
			"boxVl": "╢",
			"boxVL": "╣",
			"boxvr": "├",
			"boxvR": "╞",
			"boxVr": "╟",
			"boxVR": "╠",
			"bprime": "‵",
			"breve": "˘",
			"Breve": "˘",
			"brvbar": "¦",
			"bscr": "𝒷",
			"Bscr": "ℬ",
			"bsemi": "⁏",
			"bsim": "∽",
			"bsime": "⋍",
			"bsol": "\\",
			"bsolb": "⧅",
			"bsolhsub": "⟈",
			"bull": "•",
			"bullet": "•",
			"bump": "≎",
			"bumpe": "≏",
			"bumpE": "⪮",
			"bumpeq": "≏",
			"Bumpeq": "≎",
			"cacute": "ć",
			"Cacute": "Ć",
			"cap": "∩",
			"Cap": "⋒",
			"capand": "⩄",
			"capbrcup": "⩉",
			"capcap": "⩋",
			"capcup": "⩇",
			"capdot": "⩀",
			"CapitalDifferentialD": "ⅅ",
			"caps": "∩︀",
			"caret": "⁁",
			"caron": "ˇ",
			"Cayleys": "ℭ",
			"ccaps": "⩍",
			"ccaron": "č",
			"Ccaron": "Č",
			"ccedil": "ç",
			"Ccedil": "Ç",
			"ccirc": "ĉ",
			"Ccirc": "Ĉ",
			"Cconint": "∰",
			"ccups": "⩌",
			"ccupssm": "⩐",
			"cdot": "ċ",
			"Cdot": "Ċ",
			"cedil": "¸",
			"Cedilla": "¸",
			"cemptyv": "⦲",
			"cent": "¢",
			"centerdot": "·",
			"CenterDot": "·",
			"cfr": "𝔠",
			"Cfr": "ℭ",
			"chcy": "ч",
			"CHcy": "Ч",
			"check": "✓",
			"checkmark": "✓",
			"chi": "χ",
			"Chi": "Χ",
			"cir": "○",
			"circ": "ˆ",
			"circeq": "≗",
			"circlearrowleft": "↺",
			"circlearrowright": "↻",
			"circledast": "⊛",
			"circledcirc": "⊚",
			"circleddash": "⊝",
			"CircleDot": "⊙",
			"circledR": "®",
			"circledS": "Ⓢ",
			"CircleMinus": "⊖",
			"CirclePlus": "⊕",
			"CircleTimes": "⊗",
			"cire": "≗",
			"cirE": "⧃",
			"cirfnint": "⨐",
			"cirmid": "⫯",
			"cirscir": "⧂",
			"ClockwiseContourIntegral": "∲",
			"CloseCurlyDoubleQuote": "”",
			"CloseCurlyQuote": "’",
			"clubs": "♣",
			"clubsuit": "♣",
			"colon": ":",
			"Colon": "∷",
			"colone": "≔",
			"Colone": "⩴",
			"coloneq": "≔",
			"comma": ",",
			"commat": "@",
			"comp": "∁",
			"compfn": "∘",
			"complement": "∁",
			"complexes": "ℂ",
			"cong": "≅",
			"congdot": "⩭",
			"Congruent": "≡",
			"conint": "∮",
			"Conint": "∯",
			"ContourIntegral": "∮",
			"copf": "𝕔",
			"Copf": "ℂ",
			"coprod": "∐",
			"Coproduct": "∐",
			"copy": "©",
			"COPY": "©",
			"copysr": "℗",
			"CounterClockwiseContourIntegral": "∳",
			"crarr": "↵",
			"cross": "✗",
			"Cross": "⨯",
			"cscr": "𝒸",
			"Cscr": "𝒞",
			"csub": "⫏",
			"csube": "⫑",
			"csup": "⫐",
			"csupe": "⫒",
			"ctdot": "⋯",
			"cudarrl": "⤸",
			"cudarrr": "⤵",
			"cuepr": "⋞",
			"cuesc": "⋟",
			"cularr": "↶",
			"cularrp": "⤽",
			"cup": "∪",
			"Cup": "⋓",
			"cupbrcap": "⩈",
			"cupcap": "⩆",
			"CupCap": "≍",
			"cupcup": "⩊",
			"cupdot": "⊍",
			"cupor": "⩅",
			"cups": "∪︀",
			"curarr": "↷",
			"curarrm": "⤼",
			"curlyeqprec": "⋞",
			"curlyeqsucc": "⋟",
			"curlyvee": "⋎",
			"curlywedge": "⋏",
			"curren": "¤",
			"curvearrowleft": "↶",
			"curvearrowright": "↷",
			"cuvee": "⋎",
			"cuwed": "⋏",
			"cwconint": "∲",
			"cwint": "∱",
			"cylcty": "⌭",
			"dagger": "†",
			"Dagger": "‡",
			"daleth": "ℸ",
			"darr": "↓",
			"dArr": "⇓",
			"Darr": "↡",
			"dash": "‐",
			"dashv": "⊣",
			"Dashv": "⫤",
			"dbkarow": "⤏",
			"dblac": "˝",
			"dcaron": "ď",
			"Dcaron": "Ď",
			"dcy": "д",
			"Dcy": "Д",
			"dd": "ⅆ",
			"DD": "ⅅ",
			"ddagger": "‡",
			"ddarr": "⇊",
			"DDotrahd": "⤑",
			"ddotseq": "⩷",
			"deg": "°",
			"Del": "∇",
			"delta": "δ",
			"Delta": "Δ",
			"demptyv": "⦱",
			"dfisht": "⥿",
			"dfr": "𝔡",
			"Dfr": "𝔇",
			"dHar": "⥥",
			"dharl": "⇃",
			"dharr": "⇂",
			"DiacriticalAcute": "´",
			"DiacriticalDot": "˙",
			"DiacriticalDoubleAcute": "˝",
			"DiacriticalGrave": "`",
			"DiacriticalTilde": "˜",
			"diam": "⋄",
			"diamond": "⋄",
			"Diamond": "⋄",
			"diamondsuit": "♦",
			"diams": "♦",
			"die": "¨",
			"DifferentialD": "ⅆ",
			"digamma": "ϝ",
			"disin": "⋲",
			"div": "÷",
			"divide": "÷",
			"divideontimes": "⋇",
			"divonx": "⋇",
			"djcy": "ђ",
			"DJcy": "Ђ",
			"dlcorn": "⌞",
			"dlcrop": "⌍",
			"dollar": "$",
			"dopf": "𝕕",
			"Dopf": "𝔻",
			"dot": "˙",
			"Dot": "¨",
			"DotDot": "⃜",
			"doteq": "≐",
			"doteqdot": "≑",
			"DotEqual": "≐",
			"dotminus": "∸",
			"dotplus": "∔",
			"dotsquare": "⊡",
			"doublebarwedge": "⌆",
			"DoubleContourIntegral": "∯",
			"DoubleDot": "¨",
			"DoubleDownArrow": "⇓",
			"DoubleLeftArrow": "⇐",
			"DoubleLeftRightArrow": "⇔",
			"DoubleLeftTee": "⫤",
			"DoubleLongLeftArrow": "⟸",
			"DoubleLongLeftRightArrow": "⟺",
			"DoubleLongRightArrow": "⟹",
			"DoubleRightArrow": "⇒",
			"DoubleRightTee": "⊨",
			"DoubleUpArrow": "⇑",
			"DoubleUpDownArrow": "⇕",
			"DoubleVerticalBar": "∥",
			"downarrow": "↓",
			"Downarrow": "⇓",
			"DownArrow": "↓",
			"DownArrowBar": "⤓",
			"DownArrowUpArrow": "⇵",
			"DownBreve": "̑",
			"downdownarrows": "⇊",
			"downharpoonleft": "⇃",
			"downharpoonright": "⇂",
			"DownLeftRightVector": "⥐",
			"DownLeftTeeVector": "⥞",
			"DownLeftVector": "↽",
			"DownLeftVectorBar": "⥖",
			"DownRightTeeVector": "⥟",
			"DownRightVector": "⇁",
			"DownRightVectorBar": "⥗",
			"DownTee": "⊤",
			"DownTeeArrow": "↧",
			"drbkarow": "⤐",
			"drcorn": "⌟",
			"drcrop": "⌌",
			"dscr": "𝒹",
			"Dscr": "𝒟",
			"dscy": "ѕ",
			"DScy": "Ѕ",
			"dsol": "⧶",
			"dstrok": "đ",
			"Dstrok": "Đ",
			"dtdot": "⋱",
			"dtri": "▿",
			"dtrif": "▾",
			"duarr": "⇵",
			"duhar": "⥯",
			"dwangle": "⦦",
			"dzcy": "џ",
			"DZcy": "Џ",
			"dzigrarr": "⟿",
			"eacute": "é",
			"Eacute": "É",
			"easter": "⩮",
			"ecaron": "ě",
			"Ecaron": "Ě",
			"ecir": "≖",
			"ecirc": "ê",
			"Ecirc": "Ê",
			"ecolon": "≕",
			"ecy": "э",
			"Ecy": "Э",
			"eDDot": "⩷",
			"edot": "ė",
			"eDot": "≑",
			"Edot": "Ė",
			"ee": "ⅇ",
			"efDot": "≒",
			"efr": "𝔢",
			"Efr": "𝔈",
			"eg": "⪚",
			"egrave": "è",
			"Egrave": "È",
			"egs": "⪖",
			"egsdot": "⪘",
			"el": "⪙",
			"Element": "∈",
			"elinters": "⏧",
			"ell": "ℓ",
			"els": "⪕",
			"elsdot": "⪗",
			"emacr": "ē",
			"Emacr": "Ē",
			"empty": "∅",
			"emptyset": "∅",
			"EmptySmallSquare": "◻",
			"emptyv": "∅",
			"EmptyVerySmallSquare": "▫",
			"emsp": " ",
			"emsp13": " ",
			"emsp14": " ",
			"eng": "ŋ",
			"ENG": "Ŋ",
			"ensp": " ",
			"eogon": "ę",
			"Eogon": "Ę",
			"eopf": "𝕖",
			"Eopf": "𝔼",
			"epar": "⋕",
			"eparsl": "⧣",
			"eplus": "⩱",
			"epsi": "ε",
			"epsilon": "ε",
			"Epsilon": "Ε",
			"epsiv": "ϵ",
			"eqcirc": "≖",
			"eqcolon": "≕",
			"eqsim": "≂",
			"eqslantgtr": "⪖",
			"eqslantless": "⪕",
			"Equal": "⩵",
			"equals": "=",
			"EqualTilde": "≂",
			"equest": "≟",
			"Equilibrium": "⇌",
			"equiv": "≡",
			"equivDD": "⩸",
			"eqvparsl": "⧥",
			"erarr": "⥱",
			"erDot": "≓",
			"escr": "ℯ",
			"Escr": "ℰ",
			"esdot": "≐",
			"esim": "≂",
			"Esim": "⩳",
			"eta": "η",
			"Eta": "Η",
			"eth": "ð",
			"ETH": "Ð",
			"euml": "ë",
			"Euml": "Ë",
			"euro": "€",
			"excl": "!",
			"exist": "∃",
			"Exists": "∃",
			"expectation": "ℰ",
			"exponentiale": "ⅇ",
			"ExponentialE": "ⅇ",
			"fallingdotseq": "≒",
			"fcy": "ф",
			"Fcy": "Ф",
			"female": "♀",
			"ffilig": "ﬃ",
			"fflig": "ﬀ",
			"ffllig": "ﬄ",
			"ffr": "𝔣",
			"Ffr": "𝔉",
			"filig": "ﬁ",
			"FilledSmallSquare": "◼",
			"FilledVerySmallSquare": "▪",
			"fjlig": "fj",
			"flat": "♭",
			"fllig": "ﬂ",
			"fltns": "▱",
			"fnof": "ƒ",
			"fopf": "𝕗",
			"Fopf": "𝔽",
			"forall": "∀",
			"ForAll": "∀",
			"fork": "⋔",
			"forkv": "⫙",
			"Fouriertrf": "ℱ",
			"fpartint": "⨍",
			"frac12": "½",
			"frac13": "⅓",
			"frac14": "¼",
			"frac15": "⅕",
			"frac16": "⅙",
			"frac18": "⅛",
			"frac23": "⅔",
			"frac25": "⅖",
			"frac34": "¾",
			"frac35": "⅗",
			"frac38": "⅜",
			"frac45": "⅘",
			"frac56": "⅚",
			"frac58": "⅝",
			"frac78": "⅞",
			"frasl": "⁄",
			"frown": "⌢",
			"fscr": "𝒻",
			"Fscr": "ℱ",
			"gacute": "ǵ",
			"gamma": "γ",
			"Gamma": "Γ",
			"gammad": "ϝ",
			"Gammad": "Ϝ",
			"gap": "⪆",
			"gbreve": "ğ",
			"Gbreve": "Ğ",
			"Gcedil": "Ģ",
			"gcirc": "ĝ",
			"Gcirc": "Ĝ",
			"gcy": "г",
			"Gcy": "Г",
			"gdot": "ġ",
			"Gdot": "Ġ",
			"ge": "≥",
			"gE": "≧",
			"gel": "⋛",
			"gEl": "⪌",
			"geq": "≥",
			"geqq": "≧",
			"geqslant": "⩾",
			"ges": "⩾",
			"gescc": "⪩",
			"gesdot": "⪀",
			"gesdoto": "⪂",
			"gesdotol": "⪄",
			"gesl": "⋛︀",
			"gesles": "⪔",
			"gfr": "𝔤",
			"Gfr": "𝔊",
			"gg": "≫",
			"Gg": "⋙",
			"ggg": "⋙",
			"gimel": "ℷ",
			"gjcy": "ѓ",
			"GJcy": "Ѓ",
			"gl": "≷",
			"gla": "⪥",
			"glE": "⪒",
			"glj": "⪤",
			"gnap": "⪊",
			"gnapprox": "⪊",
			"gne": "⪈",
			"gnE": "≩",
			"gneq": "⪈",
			"gneqq": "≩",
			"gnsim": "⋧",
			"gopf": "𝕘",
			"Gopf": "𝔾",
			"grave": "`",
			"GreaterEqual": "≥",
			"GreaterEqualLess": "⋛",
			"GreaterFullEqual": "≧",
			"GreaterGreater": "⪢",
			"GreaterLess": "≷",
			"GreaterSlantEqual": "⩾",
			"GreaterTilde": "≳",
			"gscr": "ℊ",
			"Gscr": "𝒢",
			"gsim": "≳",
			"gsime": "⪎",
			"gsiml": "⪐",
			"gt": ">",
			"Gt": "≫",
			"GT": ">",
			"gtcc": "⪧",
			"gtcir": "⩺",
			"gtdot": "⋗",
			"gtlPar": "⦕",
			"gtquest": "⩼",
			"gtrapprox": "⪆",
			"gtrarr": "⥸",
			"gtrdot": "⋗",
			"gtreqless": "⋛",
			"gtreqqless": "⪌",
			"gtrless": "≷",
			"gtrsim": "≳",
			"gvertneqq": "≩︀",
			"gvnE": "≩︀",
			"Hacek": "ˇ",
			"hairsp": " ",
			"half": "½",
			"hamilt": "ℋ",
			"hardcy": "ъ",
			"HARDcy": "Ъ",
			"harr": "↔",
			"hArr": "⇔",
			"harrcir": "⥈",
			"harrw": "↭",
			"Hat": "^",
			"hbar": "ℏ",
			"hcirc": "ĥ",
			"Hcirc": "Ĥ",
			"hearts": "♥",
			"heartsuit": "♥",
			"hellip": "…",
			"hercon": "⊹",
			"hfr": "𝔥",
			"Hfr": "ℌ",
			"HilbertSpace": "ℋ",
			"hksearow": "⤥",
			"hkswarow": "⤦",
			"hoarr": "⇿",
			"homtht": "∻",
			"hookleftarrow": "↩",
			"hookrightarrow": "↪",
			"hopf": "𝕙",
			"Hopf": "ℍ",
			"horbar": "―",
			"HorizontalLine": "─",
			"hscr": "𝒽",
			"Hscr": "ℋ",
			"hslash": "ℏ",
			"hstrok": "ħ",
			"Hstrok": "Ħ",
			"HumpDownHump": "≎",
			"HumpEqual": "≏",
			"hybull": "⁃",
			"hyphen": "‐",
			"iacute": "í",
			"Iacute": "Í",
			"ic": "⁣",
			"icirc": "î",
			"Icirc": "Î",
			"icy": "и",
			"Icy": "И",
			"Idot": "İ",
			"iecy": "е",
			"IEcy": "Е",
			"iexcl": "¡",
			"iff": "⇔",
			"ifr": "𝔦",
			"Ifr": "ℑ",
			"igrave": "ì",
			"Igrave": "Ì",
			"ii": "ⅈ",
			"iiiint": "⨌",
			"iiint": "∭",
			"iinfin": "⧜",
			"iiota": "℩",
			"ijlig": "ĳ",
			"IJlig": "Ĳ",
			"Im": "ℑ",
			"imacr": "ī",
			"Imacr": "Ī",
			"image": "ℑ",
			"ImaginaryI": "ⅈ",
			"imagline": "ℐ",
			"imagpart": "ℑ",
			"imath": "ı",
			"imof": "⊷",
			"imped": "Ƶ",
			"Implies": "⇒",
			"in": "∈",
			"incare": "℅",
			"infin": "∞",
			"infintie": "⧝",
			"inodot": "ı",
			"int": "∫",
			"Int": "∬",
			"intcal": "⊺",
			"integers": "ℤ",
			"Integral": "∫",
			"intercal": "⊺",
			"Intersection": "⋂",
			"intlarhk": "⨗",
			"intprod": "⨼",
			"InvisibleComma": "⁣",
			"InvisibleTimes": "⁢",
			"iocy": "ё",
			"IOcy": "Ё",
			"iogon": "į",
			"Iogon": "Į",
			"iopf": "𝕚",
			"Iopf": "𝕀",
			"iota": "ι",
			"Iota": "Ι",
			"iprod": "⨼",
			"iquest": "¿",
			"iscr": "𝒾",
			"Iscr": "ℐ",
			"isin": "∈",
			"isindot": "⋵",
			"isinE": "⋹",
			"isins": "⋴",
			"isinsv": "⋳",
			"isinv": "∈",
			"it": "⁢",
			"itilde": "ĩ",
			"Itilde": "Ĩ",
			"iukcy": "і",
			"Iukcy": "І",
			"iuml": "ï",
			"Iuml": "Ï",
			"jcirc": "ĵ",
			"Jcirc": "Ĵ",
			"jcy": "й",
			"Jcy": "Й",
			"jfr": "𝔧",
			"Jfr": "𝔍",
			"jmath": "ȷ",
			"jopf": "𝕛",
			"Jopf": "𝕁",
			"jscr": "𝒿",
			"Jscr": "𝒥",
			"jsercy": "ј",
			"Jsercy": "Ј",
			"jukcy": "є",
			"Jukcy": "Є",
			"kappa": "κ",
			"Kappa": "Κ",
			"kappav": "ϰ",
			"kcedil": "ķ",
			"Kcedil": "Ķ",
			"kcy": "к",
			"Kcy": "К",
			"kfr": "𝔨",
			"Kfr": "𝔎",
			"kgreen": "ĸ",
			"khcy": "х",
			"KHcy": "Х",
			"kjcy": "ќ",
			"KJcy": "Ќ",
			"kopf": "𝕜",
			"Kopf": "𝕂",
			"kscr": "𝓀",
			"Kscr": "𝒦",
			"lAarr": "⇚",
			"lacute": "ĺ",
			"Lacute": "Ĺ",
			"laemptyv": "⦴",
			"lagran": "ℒ",
			"lambda": "λ",
			"Lambda": "Λ",
			"lang": "⟨",
			"Lang": "⟪",
			"langd": "⦑",
			"langle": "⟨",
			"lap": "⪅",
			"Laplacetrf": "ℒ",
			"laquo": "«",
			"larr": "←",
			"lArr": "⇐",
			"Larr": "↞",
			"larrb": "⇤",
			"larrbfs": "⤟",
			"larrfs": "⤝",
			"larrhk": "↩",
			"larrlp": "↫",
			"larrpl": "⤹",
			"larrsim": "⥳",
			"larrtl": "↢",
			"lat": "⪫",
			"latail": "⤙",
			"lAtail": "⤛",
			"late": "⪭",
			"lates": "⪭︀",
			"lbarr": "⤌",
			"lBarr": "⤎",
			"lbbrk": "❲",
			"lbrace": "{",
			"lbrack": "[",
			"lbrke": "⦋",
			"lbrksld": "⦏",
			"lbrkslu": "⦍",
			"lcaron": "ľ",
			"Lcaron": "Ľ",
			"lcedil": "ļ",
			"Lcedil": "Ļ",
			"lceil": "⌈",
			"lcub": "{",
			"lcy": "л",
			"Lcy": "Л",
			"ldca": "⤶",
			"ldquo": "“",
			"ldquor": "„",
			"ldrdhar": "⥧",
			"ldrushar": "⥋",
			"ldsh": "↲",
			"le": "≤",
			"lE": "≦",
			"LeftAngleBracket": "⟨",
			"leftarrow": "←",
			"Leftarrow": "⇐",
			"LeftArrow": "←",
			"LeftArrowBar": "⇤",
			"LeftArrowRightArrow": "⇆",
			"leftarrowtail": "↢",
			"LeftCeiling": "⌈",
			"LeftDoubleBracket": "⟦",
			"LeftDownTeeVector": "⥡",
			"LeftDownVector": "⇃",
			"LeftDownVectorBar": "⥙",
			"LeftFloor": "⌊",
			"leftharpoondown": "↽",
			"leftharpoonup": "↼",
			"leftleftarrows": "⇇",
			"leftrightarrow": "↔",
			"Leftrightarrow": "⇔",
			"LeftRightArrow": "↔",
			"leftrightarrows": "⇆",
			"leftrightharpoons": "⇋",
			"leftrightsquigarrow": "↭",
			"LeftRightVector": "⥎",
			"LeftTee": "⊣",
			"LeftTeeArrow": "↤",
			"LeftTeeVector": "⥚",
			"leftthreetimes": "⋋",
			"LeftTriangle": "⊲",
			"LeftTriangleBar": "⧏",
			"LeftTriangleEqual": "⊴",
			"LeftUpDownVector": "⥑",
			"LeftUpTeeVector": "⥠",
			"LeftUpVector": "↿",
			"LeftUpVectorBar": "⥘",
			"LeftVector": "↼",
			"LeftVectorBar": "⥒",
			"leg": "⋚",
			"lEg": "⪋",
			"leq": "≤",
			"leqq": "≦",
			"leqslant": "⩽",
			"les": "⩽",
			"lescc": "⪨",
			"lesdot": "⩿",
			"lesdoto": "⪁",
			"lesdotor": "⪃",
			"lesg": "⋚︀",
			"lesges": "⪓",
			"lessapprox": "⪅",
			"lessdot": "⋖",
			"lesseqgtr": "⋚",
			"lesseqqgtr": "⪋",
			"LessEqualGreater": "⋚",
			"LessFullEqual": "≦",
			"LessGreater": "≶",
			"lessgtr": "≶",
			"LessLess": "⪡",
			"lesssim": "≲",
			"LessSlantEqual": "⩽",
			"LessTilde": "≲",
			"lfisht": "⥼",
			"lfloor": "⌊",
			"lfr": "𝔩",
			"Lfr": "𝔏",
			"lg": "≶",
			"lgE": "⪑",
			"lHar": "⥢",
			"lhard": "↽",
			"lharu": "↼",
			"lharul": "⥪",
			"lhblk": "▄",
			"ljcy": "љ",
			"LJcy": "Љ",
			"ll": "≪",
			"Ll": "⋘",
			"llarr": "⇇",
			"llcorner": "⌞",
			"Lleftarrow": "⇚",
			"llhard": "⥫",
			"lltri": "◺",
			"lmidot": "ŀ",
			"Lmidot": "Ŀ",
			"lmoust": "⎰",
			"lmoustache": "⎰",
			"lnap": "⪉",
			"lnapprox": "⪉",
			"lne": "⪇",
			"lnE": "≨",
			"lneq": "⪇",
			"lneqq": "≨",
			"lnsim": "⋦",
			"loang": "⟬",
			"loarr": "⇽",
			"lobrk": "⟦",
			"longleftarrow": "⟵",
			"Longleftarrow": "⟸",
			"LongLeftArrow": "⟵",
			"longleftrightarrow": "⟷",
			"Longleftrightarrow": "⟺",
			"LongLeftRightArrow": "⟷",
			"longmapsto": "⟼",
			"longrightarrow": "⟶",
			"Longrightarrow": "⟹",
			"LongRightArrow": "⟶",
			"looparrowleft": "↫",
			"looparrowright": "↬",
			"lopar": "⦅",
			"lopf": "𝕝",
			"Lopf": "𝕃",
			"loplus": "⨭",
			"lotimes": "⨴",
			"lowast": "∗",
			"lowbar": "_",
			"LowerLeftArrow": "↙",
			"LowerRightArrow": "↘",
			"loz": "◊",
			"lozenge": "◊",
			"lozf": "⧫",
			"lpar": "(",
			"lparlt": "⦓",
			"lrarr": "⇆",
			"lrcorner": "⌟",
			"lrhar": "⇋",
			"lrhard": "⥭",
			"lrm": "‎",
			"lrtri": "⊿",
			"lsaquo": "‹",
			"lscr": "𝓁",
			"Lscr": "ℒ",
			"lsh": "↰",
			"Lsh": "↰",
			"lsim": "≲",
			"lsime": "⪍",
			"lsimg": "⪏",
			"lsqb": "[",
			"lsquo": "‘",
			"lsquor": "‚",
			"lstrok": "ł",
			"Lstrok": "Ł",
			"lt": "<",
			"Lt": "≪",
			"LT": "<",
			"ltcc": "⪦",
			"ltcir": "⩹",
			"ltdot": "⋖",
			"lthree": "⋋",
			"ltimes": "⋉",
			"ltlarr": "⥶",
			"ltquest": "⩻",
			"ltri": "◃",
			"ltrie": "⊴",
			"ltrif": "◂",
			"ltrPar": "⦖",
			"lurdshar": "⥊",
			"luruhar": "⥦",
			"lvertneqq": "≨︀",
			"lvnE": "≨︀",
			"macr": "¯",
			"male": "♂",
			"malt": "✠",
			"maltese": "✠",
			"map": "↦",
			"Map": "⤅",
			"mapsto": "↦",
			"mapstodown": "↧",
			"mapstoleft": "↤",
			"mapstoup": "↥",
			"marker": "▮",
			"mcomma": "⨩",
			"mcy": "м",
			"Mcy": "М",
			"mdash": "—",
			"mDDot": "∺",
			"measuredangle": "∡",
			"MediumSpace": " ",
			"Mellintrf": "ℳ",
			"mfr": "𝔪",
			"Mfr": "𝔐",
			"mho": "℧",
			"micro": "µ",
			"mid": "∣",
			"midast": "*",
			"midcir": "⫰",
			"middot": "·",
			"minus": "−",
			"minusb": "⊟",
			"minusd": "∸",
			"minusdu": "⨪",
			"MinusPlus": "∓",
			"mlcp": "⫛",
			"mldr": "…",
			"mnplus": "∓",
			"models": "⊧",
			"mopf": "𝕞",
			"Mopf": "𝕄",
			"mp": "∓",
			"mscr": "𝓂",
			"Mscr": "ℳ",
			"mstpos": "∾",
			"mu": "μ",
			"Mu": "Μ",
			"multimap": "⊸",
			"mumap": "⊸",
			"nabla": "∇",
			"nacute": "ń",
			"Nacute": "Ń",
			"nang": "∠⃒",
			"nap": "≉",
			"napE": "⩰̸",
			"napid": "≋̸",
			"napos": "ŉ",
			"napprox": "≉",
			"natur": "♮",
			"natural": "♮",
			"naturals": "ℕ",
			"nbsp": "\xA0",
			"nbump": "≎̸",
			"nbumpe": "≏̸",
			"ncap": "⩃",
			"ncaron": "ň",
			"Ncaron": "Ň",
			"ncedil": "ņ",
			"Ncedil": "Ņ",
			"ncong": "≇",
			"ncongdot": "⩭̸",
			"ncup": "⩂",
			"ncy": "н",
			"Ncy": "Н",
			"ndash": "–",
			"ne": "≠",
			"nearhk": "⤤",
			"nearr": "↗",
			"neArr": "⇗",
			"nearrow": "↗",
			"nedot": "≐̸",
			"NegativeMediumSpace": "​",
			"NegativeThickSpace": "​",
			"NegativeThinSpace": "​",
			"NegativeVeryThinSpace": "​",
			"nequiv": "≢",
			"nesear": "⤨",
			"nesim": "≂̸",
			"NestedGreaterGreater": "≫",
			"NestedLessLess": "≪",
			"NewLine": "\n",
			"nexist": "∄",
			"nexists": "∄",
			"nfr": "𝔫",
			"Nfr": "𝔑",
			"nge": "≱",
			"ngE": "≧̸",
			"ngeq": "≱",
			"ngeqq": "≧̸",
			"ngeqslant": "⩾̸",
			"nges": "⩾̸",
			"nGg": "⋙̸",
			"ngsim": "≵",
			"ngt": "≯",
			"nGt": "≫⃒",
			"ngtr": "≯",
			"nGtv": "≫̸",
			"nharr": "↮",
			"nhArr": "⇎",
			"nhpar": "⫲",
			"ni": "∋",
			"nis": "⋼",
			"nisd": "⋺",
			"niv": "∋",
			"njcy": "њ",
			"NJcy": "Њ",
			"nlarr": "↚",
			"nlArr": "⇍",
			"nldr": "‥",
			"nle": "≰",
			"nlE": "≦̸",
			"nleftarrow": "↚",
			"nLeftarrow": "⇍",
			"nleftrightarrow": "↮",
			"nLeftrightarrow": "⇎",
			"nleq": "≰",
			"nleqq": "≦̸",
			"nleqslant": "⩽̸",
			"nles": "⩽̸",
			"nless": "≮",
			"nLl": "⋘̸",
			"nlsim": "≴",
			"nlt": "≮",
			"nLt": "≪⃒",
			"nltri": "⋪",
			"nltrie": "⋬",
			"nLtv": "≪̸",
			"nmid": "∤",
			"NoBreak": "⁠",
			"NonBreakingSpace": "\xA0",
			"nopf": "𝕟",
			"Nopf": "ℕ",
			"not": "¬",
			"Not": "⫬",
			"NotCongruent": "≢",
			"NotCupCap": "≭",
			"NotDoubleVerticalBar": "∦",
			"NotElement": "∉",
			"NotEqual": "≠",
			"NotEqualTilde": "≂̸",
			"NotExists": "∄",
			"NotGreater": "≯",
			"NotGreaterEqual": "≱",
			"NotGreaterFullEqual": "≧̸",
			"NotGreaterGreater": "≫̸",
			"NotGreaterLess": "≹",
			"NotGreaterSlantEqual": "⩾̸",
			"NotGreaterTilde": "≵",
			"NotHumpDownHump": "≎̸",
			"NotHumpEqual": "≏̸",
			"notin": "∉",
			"notindot": "⋵̸",
			"notinE": "⋹̸",
			"notinva": "∉",
			"notinvb": "⋷",
			"notinvc": "⋶",
			"NotLeftTriangle": "⋪",
			"NotLeftTriangleBar": "⧏̸",
			"NotLeftTriangleEqual": "⋬",
			"NotLess": "≮",
			"NotLessEqual": "≰",
			"NotLessGreater": "≸",
			"NotLessLess": "≪̸",
			"NotLessSlantEqual": "⩽̸",
			"NotLessTilde": "≴",
			"NotNestedGreaterGreater": "⪢̸",
			"NotNestedLessLess": "⪡̸",
			"notni": "∌",
			"notniva": "∌",
			"notnivb": "⋾",
			"notnivc": "⋽",
			"NotPrecedes": "⊀",
			"NotPrecedesEqual": "⪯̸",
			"NotPrecedesSlantEqual": "⋠",
			"NotReverseElement": "∌",
			"NotRightTriangle": "⋫",
			"NotRightTriangleBar": "⧐̸",
			"NotRightTriangleEqual": "⋭",
			"NotSquareSubset": "⊏̸",
			"NotSquareSubsetEqual": "⋢",
			"NotSquareSuperset": "⊐̸",
			"NotSquareSupersetEqual": "⋣",
			"NotSubset": "⊂⃒",
			"NotSubsetEqual": "⊈",
			"NotSucceeds": "⊁",
			"NotSucceedsEqual": "⪰̸",
			"NotSucceedsSlantEqual": "⋡",
			"NotSucceedsTilde": "≿̸",
			"NotSuperset": "⊃⃒",
			"NotSupersetEqual": "⊉",
			"NotTilde": "≁",
			"NotTildeEqual": "≄",
			"NotTildeFullEqual": "≇",
			"NotTildeTilde": "≉",
			"NotVerticalBar": "∤",
			"npar": "∦",
			"nparallel": "∦",
			"nparsl": "⫽⃥",
			"npart": "∂̸",
			"npolint": "⨔",
			"npr": "⊀",
			"nprcue": "⋠",
			"npre": "⪯̸",
			"nprec": "⊀",
			"npreceq": "⪯̸",
			"nrarr": "↛",
			"nrArr": "⇏",
			"nrarrc": "⤳̸",
			"nrarrw": "↝̸",
			"nrightarrow": "↛",
			"nRightarrow": "⇏",
			"nrtri": "⋫",
			"nrtrie": "⋭",
			"nsc": "⊁",
			"nsccue": "⋡",
			"nsce": "⪰̸",
			"nscr": "𝓃",
			"Nscr": "𝒩",
			"nshortmid": "∤",
			"nshortparallel": "∦",
			"nsim": "≁",
			"nsime": "≄",
			"nsimeq": "≄",
			"nsmid": "∤",
			"nspar": "∦",
			"nsqsube": "⋢",
			"nsqsupe": "⋣",
			"nsub": "⊄",
			"nsube": "⊈",
			"nsubE": "⫅̸",
			"nsubset": "⊂⃒",
			"nsubseteq": "⊈",
			"nsubseteqq": "⫅̸",
			"nsucc": "⊁",
			"nsucceq": "⪰̸",
			"nsup": "⊅",
			"nsupe": "⊉",
			"nsupE": "⫆̸",
			"nsupset": "⊃⃒",
			"nsupseteq": "⊉",
			"nsupseteqq": "⫆̸",
			"ntgl": "≹",
			"ntilde": "ñ",
			"Ntilde": "Ñ",
			"ntlg": "≸",
			"ntriangleleft": "⋪",
			"ntrianglelefteq": "⋬",
			"ntriangleright": "⋫",
			"ntrianglerighteq": "⋭",
			"nu": "ν",
			"Nu": "Ν",
			"num": "#",
			"numero": "№",
			"numsp": " ",
			"nvap": "≍⃒",
			"nvdash": "⊬",
			"nvDash": "⊭",
			"nVdash": "⊮",
			"nVDash": "⊯",
			"nvge": "≥⃒",
			"nvgt": ">⃒",
			"nvHarr": "⤄",
			"nvinfin": "⧞",
			"nvlArr": "⤂",
			"nvle": "≤⃒",
			"nvlt": "<⃒",
			"nvltrie": "⊴⃒",
			"nvrArr": "⤃",
			"nvrtrie": "⊵⃒",
			"nvsim": "∼⃒",
			"nwarhk": "⤣",
			"nwarr": "↖",
			"nwArr": "⇖",
			"nwarrow": "↖",
			"nwnear": "⤧",
			"oacute": "ó",
			"Oacute": "Ó",
			"oast": "⊛",
			"ocir": "⊚",
			"ocirc": "ô",
			"Ocirc": "Ô",
			"ocy": "о",
			"Ocy": "О",
			"odash": "⊝",
			"odblac": "ő",
			"Odblac": "Ő",
			"odiv": "⨸",
			"odot": "⊙",
			"odsold": "⦼",
			"oelig": "œ",
			"OElig": "Œ",
			"ofcir": "⦿",
			"ofr": "𝔬",
			"Ofr": "𝔒",
			"ogon": "˛",
			"ograve": "ò",
			"Ograve": "Ò",
			"ogt": "⧁",
			"ohbar": "⦵",
			"ohm": "Ω",
			"oint": "∮",
			"olarr": "↺",
			"olcir": "⦾",
			"olcross": "⦻",
			"oline": "‾",
			"olt": "⧀",
			"omacr": "ō",
			"Omacr": "Ō",
			"omega": "ω",
			"Omega": "Ω",
			"omicron": "ο",
			"Omicron": "Ο",
			"omid": "⦶",
			"ominus": "⊖",
			"oopf": "𝕠",
			"Oopf": "𝕆",
			"opar": "⦷",
			"OpenCurlyDoubleQuote": "“",
			"OpenCurlyQuote": "‘",
			"operp": "⦹",
			"oplus": "⊕",
			"or": "∨",
			"Or": "⩔",
			"orarr": "↻",
			"ord": "⩝",
			"order": "ℴ",
			"orderof": "ℴ",
			"ordf": "ª",
			"ordm": "º",
			"origof": "⊶",
			"oror": "⩖",
			"orslope": "⩗",
			"orv": "⩛",
			"oS": "Ⓢ",
			"oscr": "ℴ",
			"Oscr": "𝒪",
			"oslash": "ø",
			"Oslash": "Ø",
			"osol": "⊘",
			"otilde": "õ",
			"Otilde": "Õ",
			"otimes": "⊗",
			"Otimes": "⨷",
			"otimesas": "⨶",
			"ouml": "ö",
			"Ouml": "Ö",
			"ovbar": "⌽",
			"OverBar": "‾",
			"OverBrace": "⏞",
			"OverBracket": "⎴",
			"OverParenthesis": "⏜",
			"par": "∥",
			"para": "¶",
			"parallel": "∥",
			"parsim": "⫳",
			"parsl": "⫽",
			"part": "∂",
			"PartialD": "∂",
			"pcy": "п",
			"Pcy": "П",
			"percnt": "%",
			"period": ".",
			"permil": "‰",
			"perp": "⊥",
			"pertenk": "‱",
			"pfr": "𝔭",
			"Pfr": "𝔓",
			"phi": "φ",
			"Phi": "Φ",
			"phiv": "ϕ",
			"phmmat": "ℳ",
			"phone": "☎",
			"pi": "π",
			"Pi": "Π",
			"pitchfork": "⋔",
			"piv": "ϖ",
			"planck": "ℏ",
			"planckh": "ℎ",
			"plankv": "ℏ",
			"plus": "+",
			"plusacir": "⨣",
			"plusb": "⊞",
			"pluscir": "⨢",
			"plusdo": "∔",
			"plusdu": "⨥",
			"pluse": "⩲",
			"PlusMinus": "±",
			"plusmn": "±",
			"plussim": "⨦",
			"plustwo": "⨧",
			"pm": "±",
			"Poincareplane": "ℌ",
			"pointint": "⨕",
			"popf": "𝕡",
			"Popf": "ℙ",
			"pound": "£",
			"pr": "≺",
			"Pr": "⪻",
			"prap": "⪷",
			"prcue": "≼",
			"pre": "⪯",
			"prE": "⪳",
			"prec": "≺",
			"precapprox": "⪷",
			"preccurlyeq": "≼",
			"Precedes": "≺",
			"PrecedesEqual": "⪯",
			"PrecedesSlantEqual": "≼",
			"PrecedesTilde": "≾",
			"preceq": "⪯",
			"precnapprox": "⪹",
			"precneqq": "⪵",
			"precnsim": "⋨",
			"precsim": "≾",
			"prime": "′",
			"Prime": "″",
			"primes": "ℙ",
			"prnap": "⪹",
			"prnE": "⪵",
			"prnsim": "⋨",
			"prod": "∏",
			"Product": "∏",
			"profalar": "⌮",
			"profline": "⌒",
			"profsurf": "⌓",
			"prop": "∝",
			"Proportion": "∷",
			"Proportional": "∝",
			"propto": "∝",
			"prsim": "≾",
			"prurel": "⊰",
			"pscr": "𝓅",
			"Pscr": "𝒫",
			"psi": "ψ",
			"Psi": "Ψ",
			"puncsp": " ",
			"qfr": "𝔮",
			"Qfr": "𝔔",
			"qint": "⨌",
			"qopf": "𝕢",
			"Qopf": "ℚ",
			"qprime": "⁗",
			"qscr": "𝓆",
			"Qscr": "𝒬",
			"quaternions": "ℍ",
			"quatint": "⨖",
			"quest": "?",
			"questeq": "≟",
			"quot": "\"",
			"QUOT": "\"",
			"rAarr": "⇛",
			"race": "∽̱",
			"racute": "ŕ",
			"Racute": "Ŕ",
			"radic": "√",
			"raemptyv": "⦳",
			"rang": "⟩",
			"Rang": "⟫",
			"rangd": "⦒",
			"range": "⦥",
			"rangle": "⟩",
			"raquo": "»",
			"rarr": "→",
			"rArr": "⇒",
			"Rarr": "↠",
			"rarrap": "⥵",
			"rarrb": "⇥",
			"rarrbfs": "⤠",
			"rarrc": "⤳",
			"rarrfs": "⤞",
			"rarrhk": "↪",
			"rarrlp": "↬",
			"rarrpl": "⥅",
			"rarrsim": "⥴",
			"rarrtl": "↣",
			"Rarrtl": "⤖",
			"rarrw": "↝",
			"ratail": "⤚",
			"rAtail": "⤜",
			"ratio": "∶",
			"rationals": "ℚ",
			"rbarr": "⤍",
			"rBarr": "⤏",
			"RBarr": "⤐",
			"rbbrk": "❳",
			"rbrace": "}",
			"rbrack": "]",
			"rbrke": "⦌",
			"rbrksld": "⦎",
			"rbrkslu": "⦐",
			"rcaron": "ř",
			"Rcaron": "Ř",
			"rcedil": "ŗ",
			"Rcedil": "Ŗ",
			"rceil": "⌉",
			"rcub": "}",
			"rcy": "р",
			"Rcy": "Р",
			"rdca": "⤷",
			"rdldhar": "⥩",
			"rdquo": "”",
			"rdquor": "”",
			"rdsh": "↳",
			"Re": "ℜ",
			"real": "ℜ",
			"realine": "ℛ",
			"realpart": "ℜ",
			"reals": "ℝ",
			"rect": "▭",
			"reg": "®",
			"REG": "®",
			"ReverseElement": "∋",
			"ReverseEquilibrium": "⇋",
			"ReverseUpEquilibrium": "⥯",
			"rfisht": "⥽",
			"rfloor": "⌋",
			"rfr": "𝔯",
			"Rfr": "ℜ",
			"rHar": "⥤",
			"rhard": "⇁",
			"rharu": "⇀",
			"rharul": "⥬",
			"rho": "ρ",
			"Rho": "Ρ",
			"rhov": "ϱ",
			"RightAngleBracket": "⟩",
			"rightarrow": "→",
			"Rightarrow": "⇒",
			"RightArrow": "→",
			"RightArrowBar": "⇥",
			"RightArrowLeftArrow": "⇄",
			"rightarrowtail": "↣",
			"RightCeiling": "⌉",
			"RightDoubleBracket": "⟧",
			"RightDownTeeVector": "⥝",
			"RightDownVector": "⇂",
			"RightDownVectorBar": "⥕",
			"RightFloor": "⌋",
			"rightharpoondown": "⇁",
			"rightharpoonup": "⇀",
			"rightleftarrows": "⇄",
			"rightleftharpoons": "⇌",
			"rightrightarrows": "⇉",
			"rightsquigarrow": "↝",
			"RightTee": "⊢",
			"RightTeeArrow": "↦",
			"RightTeeVector": "⥛",
			"rightthreetimes": "⋌",
			"RightTriangle": "⊳",
			"RightTriangleBar": "⧐",
			"RightTriangleEqual": "⊵",
			"RightUpDownVector": "⥏",
			"RightUpTeeVector": "⥜",
			"RightUpVector": "↾",
			"RightUpVectorBar": "⥔",
			"RightVector": "⇀",
			"RightVectorBar": "⥓",
			"ring": "˚",
			"risingdotseq": "≓",
			"rlarr": "⇄",
			"rlhar": "⇌",
			"rlm": "‏",
			"rmoust": "⎱",
			"rmoustache": "⎱",
			"rnmid": "⫮",
			"roang": "⟭",
			"roarr": "⇾",
			"robrk": "⟧",
			"ropar": "⦆",
			"ropf": "𝕣",
			"Ropf": "ℝ",
			"roplus": "⨮",
			"rotimes": "⨵",
			"RoundImplies": "⥰",
			"rpar": ")",
			"rpargt": "⦔",
			"rppolint": "⨒",
			"rrarr": "⇉",
			"Rrightarrow": "⇛",
			"rsaquo": "›",
			"rscr": "𝓇",
			"Rscr": "ℛ",
			"rsh": "↱",
			"Rsh": "↱",
			"rsqb": "]",
			"rsquo": "’",
			"rsquor": "’",
			"rthree": "⋌",
			"rtimes": "⋊",
			"rtri": "▹",
			"rtrie": "⊵",
			"rtrif": "▸",
			"rtriltri": "⧎",
			"RuleDelayed": "⧴",
			"ruluhar": "⥨",
			"rx": "℞",
			"sacute": "ś",
			"Sacute": "Ś",
			"sbquo": "‚",
			"sc": "≻",
			"Sc": "⪼",
			"scap": "⪸",
			"scaron": "š",
			"Scaron": "Š",
			"sccue": "≽",
			"sce": "⪰",
			"scE": "⪴",
			"scedil": "ş",
			"Scedil": "Ş",
			"scirc": "ŝ",
			"Scirc": "Ŝ",
			"scnap": "⪺",
			"scnE": "⪶",
			"scnsim": "⋩",
			"scpolint": "⨓",
			"scsim": "≿",
			"scy": "с",
			"Scy": "С",
			"sdot": "⋅",
			"sdotb": "⊡",
			"sdote": "⩦",
			"searhk": "⤥",
			"searr": "↘",
			"seArr": "⇘",
			"searrow": "↘",
			"sect": "§",
			"semi": ";",
			"seswar": "⤩",
			"setminus": "∖",
			"setmn": "∖",
			"sext": "✶",
			"sfr": "𝔰",
			"Sfr": "𝔖",
			"sfrown": "⌢",
			"sharp": "♯",
			"shchcy": "щ",
			"SHCHcy": "Щ",
			"shcy": "ш",
			"SHcy": "Ш",
			"ShortDownArrow": "↓",
			"ShortLeftArrow": "←",
			"shortmid": "∣",
			"shortparallel": "∥",
			"ShortRightArrow": "→",
			"ShortUpArrow": "↑",
			"shy": "­",
			"sigma": "σ",
			"Sigma": "Σ",
			"sigmaf": "ς",
			"sigmav": "ς",
			"sim": "∼",
			"simdot": "⩪",
			"sime": "≃",
			"simeq": "≃",
			"simg": "⪞",
			"simgE": "⪠",
			"siml": "⪝",
			"simlE": "⪟",
			"simne": "≆",
			"simplus": "⨤",
			"simrarr": "⥲",
			"slarr": "←",
			"SmallCircle": "∘",
			"smallsetminus": "∖",
			"smashp": "⨳",
			"smeparsl": "⧤",
			"smid": "∣",
			"smile": "⌣",
			"smt": "⪪",
			"smte": "⪬",
			"smtes": "⪬︀",
			"softcy": "ь",
			"SOFTcy": "Ь",
			"sol": "/",
			"solb": "⧄",
			"solbar": "⌿",
			"sopf": "𝕤",
			"Sopf": "𝕊",
			"spades": "♠",
			"spadesuit": "♠",
			"spar": "∥",
			"sqcap": "⊓",
			"sqcaps": "⊓︀",
			"sqcup": "⊔",
			"sqcups": "⊔︀",
			"Sqrt": "√",
			"sqsub": "⊏",
			"sqsube": "⊑",
			"sqsubset": "⊏",
			"sqsubseteq": "⊑",
			"sqsup": "⊐",
			"sqsupe": "⊒",
			"sqsupset": "⊐",
			"sqsupseteq": "⊒",
			"squ": "□",
			"square": "□",
			"Square": "□",
			"SquareIntersection": "⊓",
			"SquareSubset": "⊏",
			"SquareSubsetEqual": "⊑",
			"SquareSuperset": "⊐",
			"SquareSupersetEqual": "⊒",
			"SquareUnion": "⊔",
			"squarf": "▪",
			"squf": "▪",
			"srarr": "→",
			"sscr": "𝓈",
			"Sscr": "𝒮",
			"ssetmn": "∖",
			"ssmile": "⌣",
			"sstarf": "⋆",
			"star": "☆",
			"Star": "⋆",
			"starf": "★",
			"straightepsilon": "ϵ",
			"straightphi": "ϕ",
			"strns": "¯",
			"sub": "⊂",
			"Sub": "⋐",
			"subdot": "⪽",
			"sube": "⊆",
			"subE": "⫅",
			"subedot": "⫃",
			"submult": "⫁",
			"subne": "⊊",
			"subnE": "⫋",
			"subplus": "⪿",
			"subrarr": "⥹",
			"subset": "⊂",
			"Subset": "⋐",
			"subseteq": "⊆",
			"subseteqq": "⫅",
			"SubsetEqual": "⊆",
			"subsetneq": "⊊",
			"subsetneqq": "⫋",
			"subsim": "⫇",
			"subsub": "⫕",
			"subsup": "⫓",
			"succ": "≻",
			"succapprox": "⪸",
			"succcurlyeq": "≽",
			"Succeeds": "≻",
			"SucceedsEqual": "⪰",
			"SucceedsSlantEqual": "≽",
			"SucceedsTilde": "≿",
			"succeq": "⪰",
			"succnapprox": "⪺",
			"succneqq": "⪶",
			"succnsim": "⋩",
			"succsim": "≿",
			"SuchThat": "∋",
			"sum": "∑",
			"Sum": "∑",
			"sung": "♪",
			"sup": "⊃",
			"Sup": "⋑",
			"sup1": "¹",
			"sup2": "²",
			"sup3": "³",
			"supdot": "⪾",
			"supdsub": "⫘",
			"supe": "⊇",
			"supE": "⫆",
			"supedot": "⫄",
			"Superset": "⊃",
			"SupersetEqual": "⊇",
			"suphsol": "⟉",
			"suphsub": "⫗",
			"suplarr": "⥻",
			"supmult": "⫂",
			"supne": "⊋",
			"supnE": "⫌",
			"supplus": "⫀",
			"supset": "⊃",
			"Supset": "⋑",
			"supseteq": "⊇",
			"supseteqq": "⫆",
			"supsetneq": "⊋",
			"supsetneqq": "⫌",
			"supsim": "⫈",
			"supsub": "⫔",
			"supsup": "⫖",
			"swarhk": "⤦",
			"swarr": "↙",
			"swArr": "⇙",
			"swarrow": "↙",
			"swnwar": "⤪",
			"szlig": "ß",
			"Tab": "	",
			"target": "⌖",
			"tau": "τ",
			"Tau": "Τ",
			"tbrk": "⎴",
			"tcaron": "ť",
			"Tcaron": "Ť",
			"tcedil": "ţ",
			"Tcedil": "Ţ",
			"tcy": "т",
			"Tcy": "Т",
			"tdot": "⃛",
			"telrec": "⌕",
			"tfr": "𝔱",
			"Tfr": "𝔗",
			"there4": "∴",
			"therefore": "∴",
			"Therefore": "∴",
			"theta": "θ",
			"Theta": "Θ",
			"thetasym": "ϑ",
			"thetav": "ϑ",
			"thickapprox": "≈",
			"thicksim": "∼",
			"ThickSpace": "  ",
			"thinsp": " ",
			"ThinSpace": " ",
			"thkap": "≈",
			"thksim": "∼",
			"thorn": "þ",
			"THORN": "Þ",
			"tilde": "˜",
			"Tilde": "∼",
			"TildeEqual": "≃",
			"TildeFullEqual": "≅",
			"TildeTilde": "≈",
			"times": "×",
			"timesb": "⊠",
			"timesbar": "⨱",
			"timesd": "⨰",
			"tint": "∭",
			"toea": "⤨",
			"top": "⊤",
			"topbot": "⌶",
			"topcir": "⫱",
			"topf": "𝕥",
			"Topf": "𝕋",
			"topfork": "⫚",
			"tosa": "⤩",
			"tprime": "‴",
			"trade": "™",
			"TRADE": "™",
			"triangle": "▵",
			"triangledown": "▿",
			"triangleleft": "◃",
			"trianglelefteq": "⊴",
			"triangleq": "≜",
			"triangleright": "▹",
			"trianglerighteq": "⊵",
			"tridot": "◬",
			"trie": "≜",
			"triminus": "⨺",
			"TripleDot": "⃛",
			"triplus": "⨹",
			"trisb": "⧍",
			"tritime": "⨻",
			"trpezium": "⏢",
			"tscr": "𝓉",
			"Tscr": "𝒯",
			"tscy": "ц",
			"TScy": "Ц",
			"tshcy": "ћ",
			"TSHcy": "Ћ",
			"tstrok": "ŧ",
			"Tstrok": "Ŧ",
			"twixt": "≬",
			"twoheadleftarrow": "↞",
			"twoheadrightarrow": "↠",
			"uacute": "ú",
			"Uacute": "Ú",
			"uarr": "↑",
			"uArr": "⇑",
			"Uarr": "↟",
			"Uarrocir": "⥉",
			"ubrcy": "ў",
			"Ubrcy": "Ў",
			"ubreve": "ŭ",
			"Ubreve": "Ŭ",
			"ucirc": "û",
			"Ucirc": "Û",
			"ucy": "у",
			"Ucy": "У",
			"udarr": "⇅",
			"udblac": "ű",
			"Udblac": "Ű",
			"udhar": "⥮",
			"ufisht": "⥾",
			"ufr": "𝔲",
			"Ufr": "𝔘",
			"ugrave": "ù",
			"Ugrave": "Ù",
			"uHar": "⥣",
			"uharl": "↿",
			"uharr": "↾",
			"uhblk": "▀",
			"ulcorn": "⌜",
			"ulcorner": "⌜",
			"ulcrop": "⌏",
			"ultri": "◸",
			"umacr": "ū",
			"Umacr": "Ū",
			"uml": "¨",
			"UnderBar": "_",
			"UnderBrace": "⏟",
			"UnderBracket": "⎵",
			"UnderParenthesis": "⏝",
			"Union": "⋃",
			"UnionPlus": "⊎",
			"uogon": "ų",
			"Uogon": "Ų",
			"uopf": "𝕦",
			"Uopf": "𝕌",
			"uparrow": "↑",
			"Uparrow": "⇑",
			"UpArrow": "↑",
			"UpArrowBar": "⤒",
			"UpArrowDownArrow": "⇅",
			"updownarrow": "↕",
			"Updownarrow": "⇕",
			"UpDownArrow": "↕",
			"UpEquilibrium": "⥮",
			"upharpoonleft": "↿",
			"upharpoonright": "↾",
			"uplus": "⊎",
			"UpperLeftArrow": "↖",
			"UpperRightArrow": "↗",
			"upsi": "υ",
			"Upsi": "ϒ",
			"upsih": "ϒ",
			"upsilon": "υ",
			"Upsilon": "Υ",
			"UpTee": "⊥",
			"UpTeeArrow": "↥",
			"upuparrows": "⇈",
			"urcorn": "⌝",
			"urcorner": "⌝",
			"urcrop": "⌎",
			"uring": "ů",
			"Uring": "Ů",
			"urtri": "◹",
			"uscr": "𝓊",
			"Uscr": "𝒰",
			"utdot": "⋰",
			"utilde": "ũ",
			"Utilde": "Ũ",
			"utri": "▵",
			"utrif": "▴",
			"uuarr": "⇈",
			"uuml": "ü",
			"Uuml": "Ü",
			"uwangle": "⦧",
			"vangrt": "⦜",
			"varepsilon": "ϵ",
			"varkappa": "ϰ",
			"varnothing": "∅",
			"varphi": "ϕ",
			"varpi": "ϖ",
			"varpropto": "∝",
			"varr": "↕",
			"vArr": "⇕",
			"varrho": "ϱ",
			"varsigma": "ς",
			"varsubsetneq": "⊊︀",
			"varsubsetneqq": "⫋︀",
			"varsupsetneq": "⊋︀",
			"varsupsetneqq": "⫌︀",
			"vartheta": "ϑ",
			"vartriangleleft": "⊲",
			"vartriangleright": "⊳",
			"vBar": "⫨",
			"Vbar": "⫫",
			"vBarv": "⫩",
			"vcy": "в",
			"Vcy": "В",
			"vdash": "⊢",
			"vDash": "⊨",
			"Vdash": "⊩",
			"VDash": "⊫",
			"Vdashl": "⫦",
			"vee": "∨",
			"Vee": "⋁",
			"veebar": "⊻",
			"veeeq": "≚",
			"vellip": "⋮",
			"verbar": "|",
			"Verbar": "‖",
			"vert": "|",
			"Vert": "‖",
			"VerticalBar": "∣",
			"VerticalLine": "|",
			"VerticalSeparator": "❘",
			"VerticalTilde": "≀",
			"VeryThinSpace": " ",
			"vfr": "𝔳",
			"Vfr": "𝔙",
			"vltri": "⊲",
			"vnsub": "⊂⃒",
			"vnsup": "⊃⃒",
			"vopf": "𝕧",
			"Vopf": "𝕍",
			"vprop": "∝",
			"vrtri": "⊳",
			"vscr": "𝓋",
			"Vscr": "𝒱",
			"vsubne": "⊊︀",
			"vsubnE": "⫋︀",
			"vsupne": "⊋︀",
			"vsupnE": "⫌︀",
			"Vvdash": "⊪",
			"vzigzag": "⦚",
			"wcirc": "ŵ",
			"Wcirc": "Ŵ",
			"wedbar": "⩟",
			"wedge": "∧",
			"Wedge": "⋀",
			"wedgeq": "≙",
			"weierp": "℘",
			"wfr": "𝔴",
			"Wfr": "𝔚",
			"wopf": "𝕨",
			"Wopf": "𝕎",
			"wp": "℘",
			"wr": "≀",
			"wreath": "≀",
			"wscr": "𝓌",
			"Wscr": "𝒲",
			"xcap": "⋂",
			"xcirc": "◯",
			"xcup": "⋃",
			"xdtri": "▽",
			"xfr": "𝔵",
			"Xfr": "𝔛",
			"xharr": "⟷",
			"xhArr": "⟺",
			"xi": "ξ",
			"Xi": "Ξ",
			"xlarr": "⟵",
			"xlArr": "⟸",
			"xmap": "⟼",
			"xnis": "⋻",
			"xodot": "⨀",
			"xopf": "𝕩",
			"Xopf": "𝕏",
			"xoplus": "⨁",
			"xotime": "⨂",
			"xrarr": "⟶",
			"xrArr": "⟹",
			"xscr": "𝓍",
			"Xscr": "𝒳",
			"xsqcup": "⨆",
			"xuplus": "⨄",
			"xutri": "△",
			"xvee": "⋁",
			"xwedge": "⋀",
			"yacute": "ý",
			"Yacute": "Ý",
			"yacy": "я",
			"YAcy": "Я",
			"ycirc": "ŷ",
			"Ycirc": "Ŷ",
			"ycy": "ы",
			"Ycy": "Ы",
			"yen": "¥",
			"yfr": "𝔶",
			"Yfr": "𝔜",
			"yicy": "ї",
			"YIcy": "Ї",
			"yopf": "𝕪",
			"Yopf": "𝕐",
			"yscr": "𝓎",
			"Yscr": "𝒴",
			"yucy": "ю",
			"YUcy": "Ю",
			"yuml": "ÿ",
			"Yuml": "Ÿ",
			"zacute": "ź",
			"Zacute": "Ź",
			"zcaron": "ž",
			"Zcaron": "Ž",
			"zcy": "з",
			"Zcy": "З",
			"zdot": "ż",
			"Zdot": "Ż",
			"zeetrf": "ℨ",
			"ZeroWidthSpace": "​",
			"zeta": "ζ",
			"Zeta": "Ζ",
			"zfr": "𝔷",
			"Zfr": "ℨ",
			"zhcy": "ж",
			"ZHcy": "Ж",
			"zigrarr": "⇝",
			"zopf": "𝕫",
			"Zopf": "ℤ",
			"zscr": "𝓏",
			"Zscr": "𝒵",
			"zwj": "‍",
			"zwnj": "‌"
		};
		var decodeMapLegacy = {
			"aacute": "á",
			"Aacute": "Á",
			"acirc": "â",
			"Acirc": "Â",
			"acute": "´",
			"aelig": "æ",
			"AElig": "Æ",
			"agrave": "à",
			"Agrave": "À",
			"amp": "&",
			"AMP": "&",
			"aring": "å",
			"Aring": "Å",
			"atilde": "ã",
			"Atilde": "Ã",
			"auml": "ä",
			"Auml": "Ä",
			"brvbar": "¦",
			"ccedil": "ç",
			"Ccedil": "Ç",
			"cedil": "¸",
			"cent": "¢",
			"copy": "©",
			"COPY": "©",
			"curren": "¤",
			"deg": "°",
			"divide": "÷",
			"eacute": "é",
			"Eacute": "É",
			"ecirc": "ê",
			"Ecirc": "Ê",
			"egrave": "è",
			"Egrave": "È",
			"eth": "ð",
			"ETH": "Ð",
			"euml": "ë",
			"Euml": "Ë",
			"frac12": "½",
			"frac14": "¼",
			"frac34": "¾",
			"gt": ">",
			"GT": ">",
			"iacute": "í",
			"Iacute": "Í",
			"icirc": "î",
			"Icirc": "Î",
			"iexcl": "¡",
			"igrave": "ì",
			"Igrave": "Ì",
			"iquest": "¿",
			"iuml": "ï",
			"Iuml": "Ï",
			"laquo": "«",
			"lt": "<",
			"LT": "<",
			"macr": "¯",
			"micro": "µ",
			"middot": "·",
			"nbsp": "\xA0",
			"not": "¬",
			"ntilde": "ñ",
			"Ntilde": "Ñ",
			"oacute": "ó",
			"Oacute": "Ó",
			"ocirc": "ô",
			"Ocirc": "Ô",
			"ograve": "ò",
			"Ograve": "Ò",
			"ordf": "ª",
			"ordm": "º",
			"oslash": "ø",
			"Oslash": "Ø",
			"otilde": "õ",
			"Otilde": "Õ",
			"ouml": "ö",
			"Ouml": "Ö",
			"para": "¶",
			"plusmn": "±",
			"pound": "£",
			"quot": "\"",
			"QUOT": "\"",
			"raquo": "»",
			"reg": "®",
			"REG": "®",
			"sect": "§",
			"shy": "­",
			"sup1": "¹",
			"sup2": "²",
			"sup3": "³",
			"szlig": "ß",
			"thorn": "þ",
			"THORN": "Þ",
			"times": "×",
			"uacute": "ú",
			"Uacute": "Ú",
			"ucirc": "û",
			"Ucirc": "Û",
			"ugrave": "ù",
			"Ugrave": "Ù",
			"uml": "¨",
			"uuml": "ü",
			"Uuml": "Ü",
			"yacute": "ý",
			"Yacute": "Ý",
			"yen": "¥",
			"yuml": "ÿ"
		};
		var decodeMapNumeric = {
			"0": "�",
			"128": "€",
			"130": "‚",
			"131": "ƒ",
			"132": "„",
			"133": "…",
			"134": "†",
			"135": "‡",
			"136": "ˆ",
			"137": "‰",
			"138": "Š",
			"139": "‹",
			"140": "Œ",
			"142": "Ž",
			"145": "‘",
			"146": "’",
			"147": "“",
			"148": "”",
			"149": "•",
			"150": "–",
			"151": "—",
			"152": "˜",
			"153": "™",
			"154": "š",
			"155": "›",
			"156": "œ",
			"158": "ž",
			"159": "Ÿ"
		};
		var invalidReferenceCodePoints = [
			1,
			2,
			3,
			4,
			5,
			6,
			7,
			8,
			11,
			13,
			14,
			15,
			16,
			17,
			18,
			19,
			20,
			21,
			22,
			23,
			24,
			25,
			26,
			27,
			28,
			29,
			30,
			31,
			127,
			128,
			129,
			130,
			131,
			132,
			133,
			134,
			135,
			136,
			137,
			138,
			139,
			140,
			141,
			142,
			143,
			144,
			145,
			146,
			147,
			148,
			149,
			150,
			151,
			152,
			153,
			154,
			155,
			156,
			157,
			158,
			159,
			64976,
			64977,
			64978,
			64979,
			64980,
			64981,
			64982,
			64983,
			64984,
			64985,
			64986,
			64987,
			64988,
			64989,
			64990,
			64991,
			64992,
			64993,
			64994,
			64995,
			64996,
			64997,
			64998,
			64999,
			65e3,
			65001,
			65002,
			65003,
			65004,
			65005,
			65006,
			65007,
			65534,
			65535,
			131070,
			131071,
			196606,
			196607,
			262142,
			262143,
			327678,
			327679,
			393214,
			393215,
			458750,
			458751,
			524286,
			524287,
			589822,
			589823,
			655358,
			655359,
			720894,
			720895,
			786430,
			786431,
			851966,
			851967,
			917502,
			917503,
			983038,
			983039,
			1048574,
			1048575,
			1114110,
			1114111
		];
		var stringFromCharCode = String.fromCharCode;
		var hasOwnProperty = {}.hasOwnProperty;
		var has = function(object, propertyName) {
			return hasOwnProperty.call(object, propertyName);
		};
		var contains = function(array, value) {
			var index = -1;
			var length = array.length;
			while (++index < length) if (array[index] == value) return true;
			return false;
		};
		var merge = function(options, defaults) {
			if (!options) return defaults;
			var result = {};
			var key;
			for (key in defaults) result[key] = has(options, key) ? options[key] : defaults[key];
			return result;
		};
		var codePointToSymbol = function(codePoint, strict) {
			var output = "";
			if (codePoint >= 55296 && codePoint <= 57343 || codePoint > 1114111) {
				if (strict) parseError("character reference outside the permissible Unicode range");
				return "�";
			}
			if (has(decodeMapNumeric, codePoint)) {
				if (strict) parseError("disallowed character reference");
				return decodeMapNumeric[codePoint];
			}
			if (strict && contains(invalidReferenceCodePoints, codePoint)) parseError("disallowed character reference");
			if (codePoint > 65535) {
				codePoint -= 65536;
				output += stringFromCharCode(codePoint >>> 10 & 1023 | 55296);
				codePoint = 56320 | codePoint & 1023;
			}
			output += stringFromCharCode(codePoint);
			return output;
		};
		var hexEscape = function(codePoint) {
			return "&#x" + codePoint.toString(16).toUpperCase() + ";";
		};
		var decEscape = function(codePoint) {
			return "&#" + codePoint + ";";
		};
		var parseError = function(message) {
			throw Error("Parse error: " + message);
		};
		var encode = function(string, options) {
			options = merge(options, encode.options);
			if (options.strict && regexInvalidRawCodePoint.test(string)) parseError("forbidden code point");
			var encodeEverything = options.encodeEverything;
			var useNamedReferences = options.useNamedReferences;
			var allowUnsafeSymbols = options.allowUnsafeSymbols;
			var escapeCodePoint = options.decimal ? decEscape : hexEscape;
			var escapeBmpSymbol = function(symbol) {
				return escapeCodePoint(symbol.charCodeAt(0));
			};
			if (encodeEverything) {
				string = string.replace(regexAsciiWhitelist, function(symbol) {
					if (useNamedReferences && has(encodeMap, symbol)) return "&" + encodeMap[symbol] + ";";
					return escapeBmpSymbol(symbol);
				});
				if (useNamedReferences) string = string.replace(/&gt;\u20D2/g, "&nvgt;").replace(/&lt;\u20D2/g, "&nvlt;").replace(/&#x66;&#x6A;/g, "&fjlig;");
				if (useNamedReferences) string = string.replace(regexEncodeNonAscii, function(string) {
					return "&" + encodeMap[string] + ";";
				});
			} else if (useNamedReferences) {
				if (!allowUnsafeSymbols) string = string.replace(regexEscape, function(string) {
					return "&" + encodeMap[string] + ";";
				});
				string = string.replace(/&gt;\u20D2/g, "&nvgt;").replace(/&lt;\u20D2/g, "&nvlt;");
				string = string.replace(regexEncodeNonAscii, function(string) {
					return "&" + encodeMap[string] + ";";
				});
			} else if (!allowUnsafeSymbols) string = string.replace(regexEscape, escapeBmpSymbol);
			return string.replace(regexAstralSymbols, function($0) {
				var high = $0.charCodeAt(0);
				var low = $0.charCodeAt(1);
				return escapeCodePoint((high - 55296) * 1024 + low - 56320 + 65536);
			}).replace(regexBmpWhitelist, escapeBmpSymbol);
		};
		encode.options = {
			"allowUnsafeSymbols": false,
			"encodeEverything": false,
			"strict": false,
			"useNamedReferences": false,
			"decimal": false
		};
		var decode = function(html, options) {
			options = merge(options, decode.options);
			var strict = options.strict;
			if (strict && regexInvalidEntity.test(html)) parseError("malformed character reference");
			return html.replace(regexDecode, function($0, $1, $2, $3, $4, $5, $6, $7, $8) {
				var codePoint;
				var semicolon;
				var decDigits;
				var hexDigits;
				var reference;
				var next;
				if ($1) {
					reference = $1;
					return decodeMap[reference];
				}
				if ($2) {
					reference = $2;
					next = $3;
					if (next && options.isAttributeValue) {
						if (strict && next == "=") parseError("`&` did not start a character reference");
						return $0;
					} else {
						if (strict) parseError("named character reference was not terminated by a semicolon");
						return decodeMapLegacy[reference] + (next || "");
					}
				}
				if ($4) {
					decDigits = $4;
					semicolon = $5;
					if (strict && !semicolon) parseError("character reference was not terminated by a semicolon");
					codePoint = parseInt(decDigits, 10);
					return codePointToSymbol(codePoint, strict);
				}
				if ($6) {
					hexDigits = $6;
					semicolon = $7;
					if (strict && !semicolon) parseError("character reference was not terminated by a semicolon");
					codePoint = parseInt(hexDigits, 16);
					return codePointToSymbol(codePoint, strict);
				}
				if (strict) parseError("named character reference was not terminated by a semicolon");
				return $0;
			});
		};
		decode.options = {
			"isAttributeValue": false,
			"strict": false
		};
		var escape = function(string) {
			return string.replace(regexEscape, function($0) {
				return escapeMap[$0];
			});
		};
		var he = {
			"version": "1.2.0",
			"encode": encode,
			"decode": decode,
			"escape": escape,
			"unescape": decode
		};
		if (typeof define == "function" && typeof define.amd == "object" && define.amd) define(function() {
			return he;
		});
		else if (freeExports && !freeExports.nodeType) {
			if (freeModule) freeModule.exports = he;
			else for (var key in he) has(he, key) && (freeExports[key] = he[key]);
		} else root.he = he;
	})(exports);
}));
//#endregion
//#region node_modules/jsonrepair/lib/esm/utils/JSONRepairError.js
var JSONRepairError = class extends Error {
	constructor(message, position) {
		super(`${message} at position ${position}`);
		this.position = position;
	}
};
//#endregion
//#region node_modules/jsonrepair/lib/esm/utils/stringUtils.js
var codeSpace = 32;
var codeNewline = 10;
var codeTab = 9;
var codeReturn = 13;
var codeNonBreakingSpace = 160;
var codeMongolianVowelSeparator = 6158;
var codeEnQuad = 8192;
var codeZeroWidthSpace = 8203;
var codeNarrowNoBreakSpace = 8239;
var codeMediumMathematicalSpace = 8287;
var codeIdeographicSpace = 12288;
var codeZeroWidthNoBreakSpace = 65279;
function isHex(char) {
	return /^[0-9A-Fa-f]$/.test(char);
}
function isDigit(char) {
	return char >= "0" && char <= "9";
}
function isValidStringCharacter(char) {
	return char >= " ";
}
function isDelimiter(char) {
	return ",:[]/{}()\n+".includes(char);
}
function isFunctionNameCharStart(char) {
	return char >= "a" && char <= "z" || char >= "A" && char <= "Z" || char === "_" || char === "$";
}
function isFunctionNameChar(char) {
	return char >= "a" && char <= "z" || char >= "A" && char <= "Z" || char === "_" || char === "$" || char >= "0" && char <= "9";
}
var regexUrlStart = /^(http|https|ftp|mailto|file|data|irc):\/\/$/;
var regexUrlChar = /^[A-Za-z0-9-._~:/?#@!$&'()*+;=]$/;
function isUnquotedStringDelimiter(char) {
	return ",[]/{}\n+".includes(char);
}
function isStartOfValue(char) {
	return isQuote(char) || regexStartOfValue.test(char);
}
var regexStartOfValue = /^[[{\w-]$/;
function isControlCharacter(char) {
	return char === "\n" || char === "\r" || char === "	" || char === "\b" || char === "\f";
}
/**
* Check if the given character is a whitespace character like space, tab, or
* newline
*/
function isWhitespace(text, index) {
	const code = text.charCodeAt(index);
	return code === codeSpace || code === codeNewline || code === codeTab || code === codeReturn;
}
/**
* Check if the given character is a whitespace character like space or tab,
* but NOT a newline
*/
function isWhitespaceExceptNewline(text, index) {
	const code = text.charCodeAt(index);
	return code === codeSpace || code === codeTab || code === codeReturn;
}
/**
* Check if the given character is a special whitespace character, some
* unicode variant
*/
function isSpecialWhitespace(text, index) {
	const code = text.charCodeAt(index);
	return code === codeNonBreakingSpace || code === codeMongolianVowelSeparator || code >= codeEnQuad && code <= codeZeroWidthSpace || code === codeNarrowNoBreakSpace || code === codeMediumMathematicalSpace || code === codeIdeographicSpace || code === codeZeroWidthNoBreakSpace;
}
/**
* Test whether the given character is a quote or double quote character.
* Also tests for special variants of quotes.
*/
function isQuote(char) {
	return isDoubleQuoteLike(char) || isSingleQuoteLike(char);
}
/**
* Test whether the given character is a double quote character.
* Also tests for special variants of double quotes.
*/
function isDoubleQuoteLike(char) {
	return char === "\"" || char === "“" || char === "”";
}
/**
* Test whether the given character is a double quote character.
* Does NOT test for special variants of double quotes.
*/
function isDoubleQuote(char) {
	return char === "\"";
}
/**
* Test whether the given character is a single quote character.
* Also tests for special variants of single quotes.
*/
function isSingleQuoteLike(char) {
	return char === "'" || char === "‘" || char === "’" || char === "`" || char === "´";
}
/**
* Test whether the given character is a single quote character.
* Does NOT test for special variants of single quotes.
*/
function isSingleQuote(char) {
	return char === "'";
}
/**
* Strip last occurrence of textToStrip from text
*/
function stripLastOccurrence(text, textToStrip) {
	let stripRemainingText = arguments.length > 2 && arguments[2] !== void 0 ? arguments[2] : false;
	const index = text.lastIndexOf(textToStrip);
	return index !== -1 ? text.substring(0, index) + (stripRemainingText ? "" : text.substring(index + 1)) : text;
}
function insertBeforeLastWhitespace(text, textToInsert) {
	let index = text.length;
	if (!isWhitespace(text, index - 1)) return text + textToInsert;
	while (isWhitespace(text, index - 1)) index--;
	return text.substring(0, index) + textToInsert + text.substring(index);
}
function removeAtIndex(text, start, count) {
	return text.substring(0, start) + text.substring(start + count);
}
/**
* Test whether a string ends with a newline or comma character and optional whitespace
*/
function endsWithCommaOrNewline(text) {
	return /[,\n][ \t\r]*$/.test(text);
}
var namedHtmlEntities = {
	"&quot;": "\"",
	"&amp;": "&",
	"&lt;": "<",
	"&gt;": ">",
	"&apos;": "'"
};
/**
* Try to match an HTML entity at the start of the given fragment. The fragment
* is a small slice of text that begins exactly at the candidate '&'. Returns the
* decoded character and the number of characters consumed, or null when there
* is no complete, valid entity (for example a truncated "&quot" without ';').
*/
function matchHtmlEntity(fragment) {
	if (fragment.charAt(0) !== "&") return null;
	const semicolon = fragment.indexOf(";");
	if (semicolon === -1) return null;
	const entity = fragment.substring(0, semicolon + 1);
	const named = namedHtmlEntities[entity];
	if (named !== void 0) return {
		char: named,
		length: entity.length
	};
	if (fragment.charAt(1) === "#") {
		const body = fragment.substring(2, semicolon);
		const hex = body.charAt(0) === "x" || body.charAt(0) === "X";
		const digits = hex ? body.substring(1) : body;
		if (digits.length > 0) {
			const code = Number.parseInt(digits, hex ? 16 : 10);
			if (!Number.isNaN(code) && code >= 0 && code <= 1114111) return {
				char: String.fromCodePoint(code),
				length: entity.length
			};
		}
	}
	return null;
}
/**
* Test whether a matched HTML entity decodes to a double quote character
*/
function isDoubleQuoteEntity(match) {
	return match !== null && match.char === "\"";
}
/**
* Test whether a matched HTML entity decodes to a single quote character
*/
function isSingleQuoteEntity(match) {
	return match !== null && match.char === "'";
}
/**
* Count the number of occurrences of a single character in a string
*/
function countOccurrences(text, char) {
	let count = 0;
	for (let i = 0; i < text.length; i++) if (text.charAt(i) === char) count++;
	return count;
}
/**
* Test whether `closeChar` is a closing bracket and `text` still contains an
* unmatched opening bracket of the same kind. This indicates that the end of
* `text` is located inside the brackets, for example the quote in
* `"a (b") c"` is followed by `)` while `(` is still unclosed.
*
* Note that the (potentially expensive) counting is only performed when
* `closeChar` actually is a closing bracket.
*/
function isInsideUnclosedBracket(text, closeChar) {
	switch (closeChar) {
		case ")": return countOccurrences(text, "(") > countOccurrences(text, ")");
		case "]": return countOccurrences(text, "[") > countOccurrences(text, "]");
		case "}": return countOccurrences(text, "{") > countOccurrences(text, "}");
		default: return false;
	}
}
//#endregion
//#region node_modules/jsonrepair/lib/esm/regular/jsonrepair.js
var controlCharacters = {
	"\b": "\\b",
	"\f": "\\f",
	"\n": "\\n",
	"\r": "\\r",
	"	": "\\t"
};
var escapeCharacters = {
	"\"": "\"",
	"\\": "\\",
	"/": "/",
	b: "\b",
	f: "\f",
	n: "\n",
	r: "\r",
	t: "	"
};
/**
* Repair a string containing an invalid JSON document.
* For example changes JavaScript notation into JSON notation.
*
* Example:
*
*     try {
*       const json = "{name: 'John'}"
*       const repaired = jsonrepair(json)
*       console.log(repaired)
*       // '{"name": "John"}'
*     } catch (err) {
*       console.error(err)
*     }
*
*/
function jsonrepair(text) {
	let i = 0;
	let output = "";
	parseMarkdownCodeBlock([
		"```",
		"[```",
		"{```"
	]);
	if (!parseValue()) throwUnexpectedEnd();
	parseMarkdownCodeBlock([
		"```",
		"```]",
		"```}"
	]);
	const processedComma = parseCharacter(",");
	if (processedComma) parseWhitespaceAndSkipComments();
	if (isStartOfValue(text[i]) && endsWithCommaOrNewline(output)) {
		if (!processedComma) output = insertBeforeLastWhitespace(output, ",");
		parseNewlineDelimitedJSON();
	} else if (processedComma) output = stripLastOccurrence(output, ",");
	while (text[i] === "}" || text[i] === "]") {
		i++;
		parseWhitespaceAndSkipComments();
	}
	if (i >= text.length) return output;
	throwUnexpectedCharacter();
	function parseValue() {
		parseWhitespaceAndSkipComments();
		const processed = parseObject() || parseArray() || parseString() || parseNumber() || parseKeywords() || parseUnquotedString(false) || parseRegex();
		parseWhitespaceAndSkipComments();
		return processed;
	}
	function parseWhitespaceAndSkipComments() {
		let skipNewline = arguments.length > 0 && arguments[0] !== void 0 ? arguments[0] : true;
		const start = i;
		let changed = parseWhitespace(skipNewline);
		do {
			changed = parseComment();
			if (changed) changed = parseWhitespace(skipNewline);
		} while (changed);
		return i > start;
	}
	function parseWhitespace(skipNewline) {
		const _isWhiteSpace = skipNewline ? isWhitespace : isWhitespaceExceptNewline;
		let whitespace = "";
		while (true) if (_isWhiteSpace(text, i)) {
			whitespace += text[i];
			i++;
		} else if (isSpecialWhitespace(text, i)) {
			whitespace += " ";
			i++;
		} else break;
		if (whitespace.length > 0) {
			output += whitespace;
			return true;
		}
		return false;
	}
	function parseComment() {
		if (text[i] === "/" && text[i + 1] === "*") {
			while (i < text.length && !atEndOfBlockComment(text, i)) i++;
			i += 2;
			return true;
		}
		if (text[i] === "/" && text[i + 1] === "/") {
			while (i < text.length && text[i] !== "\n") i++;
			return true;
		}
		return false;
	}
	function parseMarkdownCodeBlock(blocks) {
		if (skipMarkdownCodeBlock(blocks)) {
			if (isFunctionNameCharStart(text[i])) while (i < text.length && isFunctionNameChar(text[i])) i++;
			parseWhitespaceAndSkipComments();
			return true;
		}
		return false;
	}
	function skipMarkdownCodeBlock(blocks) {
		parseWhitespace(true);
		for (const block of blocks) {
			const end = i + block.length;
			if (text.slice(i, end) === block) {
				i = end;
				return true;
			}
		}
		return false;
	}
	function parseCharacter(char) {
		if (text[i] === char) {
			output += text[i];
			i++;
			return true;
		}
		return false;
	}
	function skipCharacter(char) {
		if (text[i] === char) {
			i++;
			return true;
		}
		return false;
	}
	function skipEscapeCharacter() {
		return skipCharacter("\\");
	}
	/**
	* Skip ellipsis like "[1,2,3,...]" or "[1,2,3,...,9]" or "[...,7,8,9]"
	* or a similar construct in objects.
	*/
	function skipEllipsis() {
		parseWhitespaceAndSkipComments();
		if (text[i] === "." && text[i + 1] === "." && text[i + 2] === ".") {
			i += 3;
			parseWhitespaceAndSkipComments();
			skipCharacter(",");
			return true;
		}
		return false;
	}
	/**
	* Parse an object like '{"key": "value"}'
	*/
	function parseObject() {
		if (text[i] === "{") {
			output += "{";
			i++;
			parseWhitespaceAndSkipComments();
			if (skipCharacter(",")) parseWhitespaceAndSkipComments();
			let initial = true;
			while (i < text.length && text[i] !== "}") {
				let processedComma;
				if (!initial) {
					processedComma = parseCharacter(",");
					if (!processedComma) output = insertBeforeLastWhitespace(output, ",");
					parseWhitespaceAndSkipComments();
				} else processedComma = true;
				skipEllipsis();
				if (!(parseString() || parseUnquotedString(true))) {
					if (text[i] === "}" || text[i] === "{" || text[i] === "]" || text[i] === "[" || text[i] === void 0) {
						if (!initial) output = stripLastOccurrence(output, ",");
					} else throwObjectKeyExpected();
					break;
				}
				parseWhitespaceAndSkipComments();
				const processedColon = parseCharacter(":");
				const truncatedText = i >= text.length;
				if (!processedColon) {
					if (isStartOfValue(text[i]) || truncatedText) output = insertBeforeLastWhitespace(output, ":");
					else throwColonExpected();
				}
				if (!parseValue()) {
					if (processedColon || truncatedText) output += "null";
					else throwColonExpected();
				}
				initial = false;
			}
			if (text[i] === "}") {
				output += "}";
				i++;
			} else output = insertBeforeLastWhitespace(output, "}");
			return true;
		}
		return false;
	}
	/**
	* Parse an array like '["item1", "item2", ...]'
	*/
	function parseArray() {
		if (text[i] === "[") {
			output += "[";
			i++;
			parseWhitespaceAndSkipComments();
			if (skipCharacter(",")) parseWhitespaceAndSkipComments();
			let initial = true;
			while (i < text.length && text[i] !== "]") {
				if (!initial) {
					if (!parseCharacter(",")) output = insertBeforeLastWhitespace(output, ",");
				}
				skipEllipsis();
				if (!parseValue()) {
					if (!initial) output = stripLastOccurrence(output, ",");
					break;
				}
				initial = false;
			}
			if (text[i] === "]") {
				output += "]";
				i++;
			} else output = insertBeforeLastWhitespace(output, "]");
			return true;
		}
		return false;
	}
	/**
	* Parse and repair Newline Delimited JSON (NDJSON):
	* multiple JSON objects separated by a newline character
	*/
	function parseNewlineDelimitedJSON() {
		let initial = true;
		let processedValue = true;
		while (processedValue) {
			if (!initial) {
				if (!parseCharacter(",")) output = insertBeforeLastWhitespace(output, ",");
			} else initial = false;
			processedValue = parseValue();
		}
		if (!processedValue) output = stripLastOccurrence(output, ",");
		output = `[\n${output}\n]`;
	}
	/**
	* Parse a string enclosed by double quotes "...". Can contain escaped quotes
	* Repair strings enclosed in single quotes or special quotes
	* Repair an escaped string
	*
	* The function can run in two stages:
	* - First, it assumes the string has a valid end quote
	* - If it turns out that the string does not have a valid end quote followed
	*   by a delimiter (which should be the case), the function runs again in a
	*   more conservative way, stopping the string at the first next delimiter
	*   and fixing the string by inserting a quote there, or stopping at a
	*   stop index detected in the first iteration.
	*/
	function parseString() {
		let stopAtDelimiter = arguments.length > 0 && arguments[0] !== void 0 ? arguments[0] : false;
		let stopAtIndex = arguments.length > 1 && arguments[1] !== void 0 ? arguments[1] : -1;
		const skipEscapeChars = text[i] === "\\";
		if (skipEscapeChars) {
			i++;
			if (!isQuote(text[i])) throwUnexpectedCharacter();
		}
		const openEntity = text[i] === "&" ? matchHtmlEntity(text.slice(i, i + 12)) : null;
		const openedByEntity = isDoubleQuoteEntity(openEntity) || isSingleQuoteEntity(openEntity);
		if (isQuote(text[i]) || openedByEntity) {
			const isEndQuote = isDoubleQuote(text[i]) ? isDoubleQuote : isSingleQuote(text[i]) ? isSingleQuote : isSingleQuoteLike(text[i]) ? isSingleQuoteLike : isDoubleQuoteLike;
			const iBefore = i;
			const oBefore = output.length;
			let str = "\"";
			i += openedByEntity && openEntity ? openEntity.length : 1;
			while (true) {
				if (i >= text.length) {
					const iPrev = prevNonWhitespaceIndex(i - 1);
					if (!stopAtDelimiter && isDelimiter(text.charAt(iPrev))) {
						i = iBefore;
						output = output.substring(0, oBefore);
						return parseString(true);
					}
					str = insertBeforeLastWhitespace(str, "\"");
					output += str;
					return true;
				}
				if (i === stopAtIndex) {
					str = insertBeforeLastWhitespace(str, "\"");
					output += str;
					return true;
				}
				const entity = openedByEntity && text[i] === "&" ? matchHtmlEntity(text.slice(i, i + 12)) : null;
				if (entity && openEntity ? entity.char === openEntity.char : isEndQuote(text[i])) {
					const iQuote = i;
					const oQuote = str.length;
					str += "\"";
					i += entity ? entity.length : 1;
					output += str;
					parseWhitespaceAndSkipComments(false);
					if (stopAtDelimiter || i >= text.length || isDelimiter(text[i]) && !isInsideUnclosedBracket(str, text[i]) || isQuote(text[i]) && !nextQuoteIsEndQuote(i) || isDigit(text[i])) {
						parseConcatenatedString();
						return true;
					}
					if (text[i] === "\\") throwUnexpectedCharacter();
					const iPrevChar = prevNonWhitespaceIndex(iQuote - 1);
					const prevChar = text.charAt(iPrevChar);
					if (prevChar === ",") {
						i = iBefore;
						output = output.substring(0, oBefore);
						return parseString(false, iPrevChar);
					}
					if (isDelimiter(prevChar)) {
						i = iBefore;
						output = output.substring(0, oBefore);
						return parseString(true);
					}
					output = output.substring(0, oBefore);
					i = iQuote + (entity ? entity.length : 1);
					str = `${str.substring(0, oQuote)}\\${str.substring(oQuote)}`;
				} else if (stopAtDelimiter && isUnquotedStringDelimiter(text[i])) {
					if (text[i - 1] === ":" && regexUrlStart.test(text.substring(iBefore + 1, i + 2))) while (i < text.length && regexUrlChar.test(text[i])) {
						str += text[i];
						i++;
					}
					str = insertBeforeLastWhitespace(str, "\"");
					output += str;
					parseConcatenatedString();
					return true;
				} else if (entity) {
					const char = entity.char;
					if (char === "\"") str += "\\\"";
					else if (isControlCharacter(char)) str += controlCharacters[char];
					else str += char;
					i += entity.length;
				} else if (text[i] === "\\") {
					const char = text.charAt(i + 1);
					if (escapeCharacters[char] !== void 0) {
						str += text.slice(i, i + 2);
						i += 2;
					} else if (char === "u") {
						let j = 2;
						while (j < 6 && isHex(text[i + j])) j++;
						if (j === 6) {
							str += text.slice(i, i + 6);
							i += 6;
						} else if (i + j >= text.length) i = text.length;
						else throwInvalidUnicodeCharacter();
					} else if (char === "\n") {
						str += "\\n";
						i += 2;
					} else {
						str += char;
						i += 2;
					}
				} else {
					const char = text.charAt(i);
					if (char === "\"" && text[i - 1] !== "\\") {
						str += `\\${char}`;
						i++;
					} else if (isControlCharacter(char)) {
						str += controlCharacters[char];
						i++;
					} else {
						if (!isValidStringCharacter(char)) throwInvalidCharacter(char);
						str += char;
						i++;
					}
				}
				if (skipEscapeChars) skipEscapeCharacter();
			}
		}
		return false;
	}
	/**
	* Repair concatenated strings like "hello" + "world", change this into "helloworld"
	*/
	function parseConcatenatedString() {
		let processed = false;
		parseWhitespaceAndSkipComments();
		while (text[i] === "+") {
			processed = true;
			i++;
			parseWhitespaceAndSkipComments();
			output = stripLastOccurrence(output, "\"", true);
			const start = output.length;
			if (parseString()) output = removeAtIndex(output, start, 1);
			else output = insertBeforeLastWhitespace(output, "\"");
		}
		return processed;
	}
	/**
	* Parse a number like 2.4 or 2.4e6
	*/
	function parseNumber() {
		const start = i;
		let num = "";
		let invalid = false;
		if (text[i] === "-") {
			num += text[i];
			i++;
			if (!isDigit(text[i]) && atEndOfNumber()) num += "0";
		}
		if (text[i] === "0" && isDigit(text[i + 1])) invalid = true;
		while (isDigit(text[i])) {
			num += text[i];
			i++;
		}
		if (text[i] === ".") {
			if (num === "" || num === "-") num += "0";
			num += text[i];
			i++;
			if (!isDigit(text[i])) num += "0";
			while (isDigit(text[i])) {
				num += text[i];
				i++;
			}
		}
		if (i > start) {
			if (text[i] === "e" || text[i] === "E") {
				if (num === "-") invalid = true;
				num += text[i];
				i++;
				if (text[i] === "-" || text[i] === "+") {
					num += text[i];
					i++;
				}
				if (!isDigit(text[i])) num += "0";
				while (isDigit(text[i])) {
					num += text[i];
					i++;
				}
			}
			if (!atEndOfNumber()) {
				i = start;
				return false;
			}
			output += invalid ? `"${text.substring(start, i)}"` : num;
			return true;
		}
		return false;
	}
	/**
	* Parse keywords true, false, null
	* Repair Python keywords True, False, None
	*/
	function parseKeywords() {
		return parseKeyword("true", "true") || parseKeyword("false", "false") || parseKeyword("null", "null") || parseKeyword("True", "true") || parseKeyword("False", "false") || parseKeyword("None", "null");
	}
	function parseKeyword(name, value) {
		if (text.slice(i, i + name.length) === name && !isFunctionNameChar(text[i + name.length])) {
			output += value;
			i += name.length;
			return true;
		}
		return false;
	}
	/**
	* Repair an unquoted string by adding quotes around it
	* Repair a MongoDB function call like NumberLong("2")
	* Repair a JSONP function call like callback({...});
	*/
	function parseUnquotedString(isKey) {
		const start = i;
		if (isFunctionNameCharStart(text[i])) {
			while (i < text.length && isFunctionNameChar(text[i])) i++;
			let j = i;
			while (isWhitespace(text, j)) j++;
			if (text[j] === "(") {
				i = j + 1;
				parseValue();
				if (text[i] === ")") {
					i++;
					if (text[i] === ";") i++;
				}
				return true;
			}
		}
		while (i < text.length && !isUnquotedStringDelimiter(text[i]) && !isQuote(text[i]) && (!isKey || text[i] !== ":")) i++;
		if (text[i - 1] === ":" && regexUrlStart.test(text.substring(start, i + 2))) while (i < text.length && regexUrlChar.test(text[i])) i++;
		if (i > start) {
			while (isWhitespace(text, i - 1) && i > 0) i--;
			const symbol = text.slice(start, i);
			output += symbol === "undefined" ? "null" : JSON.stringify(symbol);
			if (text[i] === "\"") i++;
			return true;
		}
	}
	function parseRegex() {
		if (text[i] === "/") {
			const start = i;
			i++;
			while (i < text.length && (text[i] !== "/" || text[i - 1] === "\\")) i++;
			i++;
			output += JSON.stringify(text.substring(start, i));
			return true;
		}
	}
	function prevNonWhitespaceIndex(start) {
		let prev = start;
		while (prev > 0 && isWhitespace(text, prev)) prev--;
		return prev;
	}
	function nextQuoteIsEndQuote(index) {
		let next = index + 1;
		while (next < text.length && isWhitespace(text, next)) next++;
		return next >= text.length || isDelimiter(text[next]);
	}
	function atEndOfNumber() {
		return i >= text.length || isDelimiter(text[i]) || isWhitespace(text, i);
	}
	function throwInvalidCharacter(char) {
		throw new JSONRepairError(`Invalid character ${JSON.stringify(char)}`, i);
	}
	function throwUnexpectedCharacter() {
		throw new JSONRepairError(`Unexpected character ${JSON.stringify(text[i])}`, i);
	}
	function throwUnexpectedEnd() {
		throw new JSONRepairError("Unexpected end of json string", text.length);
	}
	function throwObjectKeyExpected() {
		throw new JSONRepairError("Object key expected", i);
	}
	function throwColonExpected() {
		throw new JSONRepairError("Colon expected", i);
	}
	function throwInvalidUnicodeCharacter() {
		throw new JSONRepairError(`Invalid unicode character "${text.slice(i, i + 6)}"`, i);
	}
}
function atEndOfBlockComment(text, i) {
	return text[i] === "*" && text[i + 1] === "/";
}
//#endregion
//#region src/assets/tools/utils/MarkdownUtils.ts
var import_he = /* @__PURE__ */ __toESM(require_he());
/**
* Utilitaires pour l'export Markdown
*/
var MarkdownUtils = class MarkdownUtils {
	/**
	* Split markdown into its block chunks — the unit the editor round-trips on.
	* Chunk texts are byte-for-byte what the historical
	* `replace(/\n\s*\n+/g, '\n\n')` + `split('\n\n')` produced (the parser and
	* the outline panel MUST share this rule), with two exceptions. A blank line
	* inside a fenced code block does NOT split: splitting there handed the
	* editor half a fence, and a `## ` comment in the code then read as a real
	* heading — so the fence is atomic here, as it is to the renderer. Nor does
	* one followed by a line that still belongs to a list item (see
	* continuesListItem()).
	*
	* Each chunk keeps its line range in the source so chunk N maps back to
	* source lines, and the separator that followed it so a rewrite can put the
	* author's blank lines back instead of normalising them. Whitespace-only
	* input yields no chunk, mirroring the parser's empty-content guard.
	*/
	static chunkMarkdown(markdown) {
		if (markdown.trim() === "") return [];
		const fences = MarkdownUtils.fencedRanges(markdown);
		const insideFence = (index) => fences.some(([from, to]) => index >= from && index < to);
		const chunks = [];
		const separator = /\n\s*\n+/g;
		let start = 0;
		let startLine = 0;
		const pushChunk = (end, separatorAfter) => {
			const text = markdown.slice(start, end);
			chunks.push({
				text,
				startLine,
				endLine: startLine + MarkdownUtils.countLines(text),
				separatorAfter
			});
		};
		let match;
		while ((match = separator.exec(markdown)) !== null) {
			if (insideFence(match.index)) continue;
			const chunk = markdown.slice(start, match.index);
			const rest = markdown.slice(separator.lastIndex);
			if (MarkdownUtils.continuesListItem(chunk, rest)) continue;
			pushChunk(match.index, match[0]);
			startLine += MarkdownUtils.countLines(markdown.slice(start, separator.lastIndex));
			start = separator.lastIndex;
		}
		pushChunk(markdown.length, "");
		return chunks;
	}
	/**
	* Whether the text after a blank-line run still belongs to the list the chunk
	* opens: a line indented at least as deep as the first item's text continues
	* that list's items, whatever blank lines precede it — a sub-list of a "loose"
	* list, or a second paragraph in an item. Cut there, each indented item would
	* become a list of its own and, once edited, export back at the top level.
	*/
	static continuesListItem(chunk, rest) {
		const [first = "", second = ""] = chunk.split("\n", 2);
		const itemLine = MarkdownUtils.startWithAttribute(first) ? second : first;
		const item = /^( *)([-*+]|\d{1,9}[.)])( +)\S/.exec(itemLine);
		if (item === null) return false;
		const textColumn = item[1].length + item[2].length + item[3].length;
		const nextIndent = /^( *)\S/.exec(rest)?.[1];
		return nextIndent !== void 0 && nextIndent.length >= textColumn;
	}
	/**
	* Character ranges covered by fenced code blocks, `[from, to)` — `from` at the
	* opening fence's first character, `to` at the end of the closing fence line,
	* its newline excluded so the separator that follows the fence still splits.
	* An unclosed fence runs to the end of the document, which is what the
	* renderer does with it too. CommonMark rules: up to three spaces of indent,
	* a closing run at least as long as the opening one and nothing but space
	* after it, and no backtick in a backtick fence's info string.
	*/
	static fencedRanges(markdown) {
		const fence = /^ {0,3}(`{3,}|~{3,})(.*)$/;
		const ranges = [];
		let open = null;
		let offset = 0;
		for (const line of markdown.split("\n")) {
			const match = fence.exec(line);
			if (match !== null) {
				const marker = match[1];
				const info = match[2];
				if (open === null) {
					if (!(marker.startsWith("`") && info.includes("`"))) open = {
						marker,
						from: offset
					};
				} else if (marker[0] === open.marker[0] && marker.length >= open.marker.length && info.trim() === "") {
					ranges.push([open.from, offset + line.length]);
					open = null;
				}
			}
			offset += line.length + 1;
		}
		if (open !== null) ranges.push([open.from, markdown.length]);
		return ranges;
	}
	/**
	* Rejoin chunk texts with the source's own separators, positionally: the Nth
	* gap keeps the Nth blank-line run the document had. A rail edit then leaves
	* the spacing it did not touch byte-identical instead of collapsing every
	* blank-line run to one.
	*/
	static joinChunks(texts, separators) {
		return texts.map((text, index) => index === 0 ? text : (separators[index - 1] ?? "\n\n") + text).join("");
	}
	static countLines(text) {
		return (text.match(/\n/g) ?? []).length;
	}
	static wrapWithLink(markdown, tunes) {
		if (!tunes.linkTune) return MarkdownUtils.addAttributes(markdown, tunes);
		const linkTune = tunes.linkTune;
		if (!linkTune.url) return markdown;
		let link = `[${markdown}](${linkTune.url}){`;
		if (linkTune.targetBlank) link += `target="_blank"`;
		link += `}`;
		link = link.replace(/{}/g, "");
		if (linkTune.hideForBot) link = "#" + link;
		return link;
	}
	static getAttributes(tunes) {
		let result = "";
		const anchor = tunes?.anchor;
		if (anchor && anchor !== "") result += `#${anchor}`;
		const alignment = tunes?.textAlign;
		if (alignment && alignment !== "left") {
			const alignmentClass = alignment === "center" ? "text-center" : alignment === "right" ? "text-right" : "";
			if (alignmentClass) result += `.${alignmentClass}`;
		}
		const className = tunes?.class;
		if (className && className !== "") result += `.${className}`;
		return result;
	}
	static formatAttributes(tunes) {
		return MarkdownUtils.getAttributes(tunes).replace(/\s+/g, " ").trim();
	}
	static addAttributes(markdown, tunes) {
		const attrs = MarkdownUtils.formatAttributes(tunes);
		if (attrs !== "") return `{${attrs}}\n${markdown}`;
		return markdown;
	}
	static addInlineAttributes(markdown, tunes) {
		const attrs = MarkdownUtils.formatAttributes(tunes);
		if (attrs !== "") {
			const lines = markdown.split("\n");
			lines[0] = `${(lines[0] ?? "").trimEnd()} {${attrs}}`;
			return lines.join("\n");
		}
		return markdown;
	}
	static startWithAttribute(firstLine) {
		const line = firstLine.trim();
		if (line.startsWith("{#") && (line.endsWith("#}") || !line.endsWith("}"))) return false;
		return line.startsWith("{") && line.endsWith("}") && !line.startsWith("{{") && !line.startsWith("{%");
	}
	static parseAttributes(attributeLine) {
		const tunes = {};
		const anchorMatch = attributeLine.match(/#([a-zA-Z0-9_-]+)/) ?? attributeLine.match(/id=([a-zA-Z0-9_-]+)/);
		if (anchorMatch) tunes.anchor = anchorMatch[1];
		const alignmentMatch = attributeLine.match(/\.text-(left|center|right)/);
		if (alignmentMatch) {
			tunes.textAlign = alignmentMatch[1];
			attributeLine = attributeLine.replace(alignmentMatch[0], "");
		}
		const classMatch = attributeLine.match(/\.([a-zA-Z0-9_-]+)/g);
		if (classMatch) tunes.class = classMatch.join(" ");
		return tunes;
	}
	static retrieveMarkdownWithoutTunes(markdown) {
		markdown = markdown.trim();
		const lines = markdown.split("\n");
		const firstLine = lines[0] ?? "";
		if (MarkdownUtils.startWithAttribute(firstLine)) {
			lines[0] = "";
			return lines.join("\n").trim();
		}
		return markdown;
	}
	static parseTunesFromMarkdown(markdown) {
		markdown = markdown.trim();
		const lines = markdown.split("\n");
		const firstLine = lines[0] ?? "";
		let tunes = {};
		if (MarkdownUtils.startWithAttribute(firstLine)) {
			tunes = MarkdownUtils.parseAttributes(firstLine);
			lines[0] = "";
			markdown = lines.join("\n").trim();
		}
		return {
			tunes,
			markdown
		};
	}
	static extractTwigFunctionProperties(funcName, markdown) {
		const match = markdown.matchAll(/{{\s*([A-Za-z_]+)\((.*?)\)/g);
		if (!match) return null;
		const matches = [...match];
		if (matches[0]?.[1] !== funcName) return null;
		const argsString = matches[0]?.[0]?.substring(matches[0]?.[0]?.indexOf("(") + 1);
		return MarkdownUtils.extractTwigProperties(argsString);
	}
	static extractTwigProperties(argsString) {
		const properties = [];
		let current = "";
		let inQuote = false;
		let quoteChar = "";
		let escaped = false;
		for (const char of argsString) {
			if (char === ")" && !inQuote) break;
			if (escaped) {
				current += char === quoteChar ? char : "\\" + char;
				escaped = false;
				continue;
			}
			if (char === "\\") {
				escaped = true;
				continue;
			}
			if (["\"", "'"].includes(char) && !inQuote) {
				inQuote = true;
				quoteChar = char;
				continue;
			}
			if (char === quoteChar && inQuote) {
				inQuote = false;
				quoteChar = "";
				continue;
			}
			if (!inQuote && ![" ", ","].includes(char)) return null;
			if (!inQuote && char === ",") {
				properties.push(current.trim());
				current = "";
				continue;
			}
			current += char;
		}
		properties.push(current.trim());
		return properties;
	}
	/**
	* Parse a `snippet('name', { ...json... })` call.
	*
	* The first argument is a quoted snippet name; the optional second argument is
	* a JSON object of per-insertion params. Brace- and quote-aware so nested
	* objects/arrays and commas inside the JSON do not break parsing.
	*/
	static extractSnippetCall(markdown) {
		const open = markdown.match(/{{\s*snippet\s*\(/);
		if (!open || open.index === void 0) return null;
		let i = markdown.indexOf("(", open.index) + 1;
		while (i < markdown.length && /\s/.test(markdown[i] ?? "")) i++;
		const quote = markdown[i];
		if (quote !== "'" && quote !== "\"") return null;
		i++;
		let name = "";
		while (i < markdown.length && markdown[i] !== quote) {
			if (markdown[i] === "\\") {
				name += markdown[i + 1] ?? "";
				i += 2;
				continue;
			}
			name += markdown[i];
			i++;
		}
		i++;
		while (i < markdown.length && /\s/.test(markdown[i] ?? "")) i++;
		let params = {};
		if (markdown[i] === ",") {
			i++;
			while (i < markdown.length && /\s/.test(markdown[i] ?? "")) i++;
			if (markdown[i] === "{") params = MarkdownUtils.parseBalancedObject(markdown, i);
		}
		return {
			name,
			params
		};
	}
	/**
	* Read a brace-balanced object literal starting at `start` and JSON-parse it.
	* Tolerates single quotes / trailing commas via jsonrepair.
	*/
	static parseBalancedObject(input, start) {
		let depth = 0;
		let inStr = false;
		let strCh = "";
		let end = start;
		for (let i = start; i < input.length; i++) {
			const c = input[i];
			if (inStr) {
				if (c === "\\") {
					i++;
					continue;
				}
				if (c === strCh) inStr = false;
				continue;
			}
			if (c === "\"" || c === "'") {
				inStr = true;
				strCh = c;
				continue;
			}
			if (c === "{") depth++;
			else if (c === "}") {
				depth--;
				if (depth === 0) {
					end = i + 1;
					break;
				}
			}
		}
		const raw = input.substring(start, end);
		try {
			return JSON.parse(raw);
		} catch {
			try {
				return JSON.parse(jsonrepair(raw));
			} catch {
				return {};
			}
		}
	}
	/**
	* Build a `snippet('name', {json})` Twig call. The params object is omitted
	* when empty so plain content snippets stay terse.
	*/
	static buildSnippetCall(name, params) {
		if (Object.keys(params || {}).length === 0) return `{{ snippet(${MarkdownUtils.wrapInQuotes(name)}) }}`;
		return `{{ snippet(${MarkdownUtils.wrapInQuotes(name)}, ${JSON.stringify(params)}) }}`;
	}
	/** True when the block is exactly a standalone snippet() call (not an inline mention). */
	static isSnippetBlock(markdown) {
		const body = MarkdownUtils.retrieveMarkdownWithoutTunes(markdown).trim();
		if (!/^{{\s*snippet\s*\(/.test(body) || !/\)\s*}}$/.test(body)) return false;
		return MarkdownUtils.extractSnippetCall(body) !== null;
	}
	/**
	* Parse HTML attributes from a string and return them as a typed record
	*/
	static parseHtmlAttributes(attrString) {
		const attrs = {};
		attrString.replace(/(\w+)\s*=\s*"([^"]*?)"/gi, (_match, key, value) => {
			attrs[key.toLowerCase()] = value;
			return "";
		});
		return attrs;
	}
	static convertAnchorToMarkdown(attrString, text) {
		const attrs = MarkdownUtils.parseHtmlAttributes(attrString);
		const href = attrs.href || "#";
		const extras = [];
		let obfuscate = false;
		if (attrs.rel && attrs.rel === "obfuscate") obfuscate = true;
		else if (attrs.rel) extras.push(`rel="${attrs.rel}"`);
		if (attrs.target) extras.push(`target="${attrs.target}"`);
		if (attrs.class) extras.push(`class="${attrs.class}"`);
		const destination = attrs.title ? `${href} "${attrs.title}"` : href;
		const attributeBlock = extras.length ? `{${extras.join(" ")}}` : "";
		return (obfuscate ? "#" : "") + `[${text}](${destination})${attributeBlock}`;
	}
	static makeUrlRelative(text) {
		const host = globalThis.window.pageHost;
		const baseUrl = globalThis.window.location.origin;
		if (host === "") return text;
		[
			`"${baseUrl}/${host}/`,
			`"${baseUrl}/`,
			`"https://${host}/`,
			`"http://${host}/`,
			`"://${host}/`
		].forEach((replaceStr) => {
			text = text.split(replaceStr).join("\"/");
		});
		return text;
	}
	/**
	* Structural cleanup of the HTML about to be converted back to markdown.
	* Typography (smart quotes, ellipsis, non-breaking spaces, ×, ™…) is
	* applied at render time by core's Typographer so sources stay plain —
	* see the post-decode normalization in convertInlineHtmlToMarkdown().
	*/
	static fixer(text) {
		text = MarkdownUtils.makeUrlRelative(text);
		return text.split(/(<code(?:\s[^>]*)?>[\s\S]*?<\/code>|<pre(?:\s[^>]*)?>[\s\S]*?<\/pre>)/gi).map((part, index) => index % 2 === 1 ? part : MarkdownUtils.fixProse(part)).join("");
	}
	static fixProse(text) {
		const space = "(?:[^\\S\\r\\n]|\\u00AD)";
		return text.replace(/&nbsp;/gi, " ").replace(/ <\/([a-z]+)>/gi, "</$1> ").replace(/ ?<(b|i|strong|em|span)> ?<\/(b|i|strong|em|span)> ?/gi, " ").replace(/<(b|i|strong|em|span|a)\b[^>]*><\/\1>/gi, "").replace(new RegExp(`([^\\d\\s]+)${space}+,${space}+`, "gmu"), "$1, ").replace(new RegExp(`([^\\d\\s]+)${space}+\\.${space}+`, "gmu"), "$1. ").replace(/ &amp; /gi, " & ").replace(/&shy;/g, "").replace(new RegExp(`(\\S)${space}{2,}(?!\\n)`, "gmu"), "$1 ");
	}
	static convertInlineHtmlToMarkdown(html, cleanup = true) {
		if (cleanup) html = MarkdownUtils.fixer(html);
		html = import_he.default.decode(html);
		const markdown = html.replace(/<(b|strong|em|i|a[^>]*)> /gi, " <$1>").replace(/ <\/(b|strong|em|i|a[^>]*)>/gi, "</$1> ").replace(/<(b|strong)(?: [^>]*)?>([\s\S]+?)<\/(b|strong)>/gi, "**$2**").replace(/<(i|em)(?: [^>]*)?>([\s\S]+?)<\/(i|em)>/gi, "_$2_").replace(/<code(?: [^>]*)?>(.+?)<\/code>/gi, "`$1`").replace(/<s(?: [^>]*)?>([\s\S]+?)<\/s>/gi, "~~$1~~").replace(/<sup(?: [^>]*)?>([\s\S]+?)<\/sup>/gi, "^$1^").replace(/<sub(?: [^>]*)?>([\s\S]+?)<\/sub>/gi, "~$1~").replace(/<u(?: [^>]*)?>([\s\S]+?)<\/u>/gi, "<u>$1</u>").replace(/<small(?: [^>]*)?>([\s\S]+?)<\/small>/gi, "<small>$1</small>").replace(/<mark(?: [^>]*)?>([\s\S]+?)<\/mark>/gi, "<mark>$1</mark>").replace(/<a\s+([^>]+)>([\s\S]+?)<\/a>/gi, (_match, attrString, text) => MarkdownUtils.convertAnchorToMarkdown(attrString, text)).replace(/<br\s*\/?>/gi, "\n").replace(/<div>/gi, "\n").replace(/<\/div>/gi, "");
		return MarkdownUtils.normalizeTypography(markdown);
	}
	/**
	* Straighten typographic quotes, ellipsis and spaces so sources stay
	* plain: typography is re-created at render time by core's Typographer.
	* Same rules as PageFileSerializer::normalizeTypography() on flat export;
	* keep in sync. Dashes and multiplication/trademark/copyright signs stay:
	* the render does not re-create every author-typed one, so straightening
	* them would be lossy. Fenced code blocks and inline code spans keep
	* their bytes for the same reason: the Typographer never touches
	* pre/code, so a straightened code sample would render differently
	* forever.
	*/
	static normalizeTypography(markdown) {
		const ranges = MarkdownUtils.codeRanges(markdown);
		if (ranges.length === 0) return MarkdownUtils.straightenTypography(markdown);
		let result = "";
		let cursor = 0;
		for (const [from, to] of ranges) {
			result += MarkdownUtils.straightenTypography(markdown.slice(cursor, from));
			result += markdown.slice(from, to);
			cursor = to;
		}
		return result + MarkdownUtils.straightenTypography(markdown.slice(cursor));
	}
	static straightenTypography(text) {
		return text.replace(/[\u2018\u2019\u201A]/g, "'").replace(/[\u201C\u201D\u201E]/g, "\"").replace(/\u2026/g, "...").replace(/[\u00A0\u202F\u2009]/g, " ").replace(/[\u00AD\u200B\u2060\uFEFF]/g, "");
	}
	/**
	* Character ranges covered by code, `[from, to)`: fenced blocks first,
	* then inline code spans in the prose between them. Ordered,
	* non-overlapping.
	*/
	static codeRanges(markdown) {
		const ranges = [];
		let cursor = 0;
		for (const [from, to] of MarkdownUtils.fencedRanges(markdown)) {
			ranges.push(...MarkdownUtils.inlineCodeSpans(markdown, cursor, from));
			ranges.push([from, to]);
			cursor = to;
		}
		ranges.push(...MarkdownUtils.inlineCodeSpans(markdown, cursor, markdown.length));
		return ranges;
	}
	/**
	* Inline code spans in markdown[from, to): a backtick run closed by the
	* next run of the same length (CommonMark), never across a blank line.
	*/
	static inlineCodeSpans(markdown, from, to) {
		const segment = markdown.slice(from, to);
		if (!segment.includes("`")) return [];
		const runs = [...segment.matchAll(/`+/g)].map((match) => ({
			from: match.index,
			length: match[0].length
		}));
		const spans = [];
		let i = 0;
		while (i < runs.length) {
			const run = runs[i];
			let closed = false;
			for (let j = i + 1; j < runs.length; j++) {
				const closer = runs[j];
				const between = segment.slice(run.from + run.length, closer.from);
				if (/\n[ \t]*\n/.test(between)) break;
				if (closer.length === run.length) {
					spans.push([from + run.from, from + closer.from + closer.length]);
					i = j + 1;
					closed = true;
					break;
				}
			}
			if (!closed) i++;
		}
		return spans;
	}
	static anchorOpeningTag(href, title, attrsString, isObfuscated) {
		const attributes = [`href="${href}"`];
		if (title !== void 0) attributes.push(`title="${title}"`);
		if (isObfuscated) attributes.push("rel=\"obfuscate\"");
		if (attrsString) attributes.push(attrsString);
		return `<a ${attributes.join(" ")}>`;
	}
	/**
	* Code spans, images and link tags are held out while emphasis is converted:
	* an underscore in a URL, an alt or a code span must not open an <i>. An
	* image stays markdown text: the Image block owns images, and a paragraph
	* that carries one keeps it byte for byte. As in CommonMark, `_` only opens
	* or closes emphasis at a word boundary, and a line break is soft (a space,
	* kept as a newline) unless two trailing spaces or a backslash make it a
	* hard break (<br>).
	*/
	static convertInlineMarkdownToHtml(markdown) {
		const held = [];
		const hold = (html) => `\u0000${held.push(html) - 1}\u0000`;
		const restore = (text) => text.replace(/\u0000(\d+)\u0000/g, (_match, index) => restore(held[Number(index)] ?? ""));
		return restore(markdown.replace(/!\[[^\]]*\]\([^)]*\)/g, (image) => hold(image)).replace(/`(.+?)`/g, (_match, code) => hold(`<code class="inline-code">${code}</code>`)).replace(/(#?)\[([^\]]+)\]\(([^){]+?)(?:\s+"([^"]*)")?\)(?:\{([^}]+)\})?/g, (_match, hash, text, href, title, attrs) => hold(MarkdownUtils.anchorOpeningTag(href, title, attrs, hash === "#")) + text + hold("</a>")).replace(/\*\*([\s\S]+?)\*\*/g, "<b>$1</b>").replace(/(?<![\p{L}\p{N}_])_(?!\s)([\s\S]+?)(?<!\s)_(?![\p{L}\p{N}_])/gu, "<i>$1</i>").replace(/~~([\s\S]+?)~~/g, "<s class=\"cdx-strikethrough\">$1</s>").replace(/(?: {2,}|\\)\n/g, "<br>"));
	}
	static {
		this.prettierPromise = null;
	}
	static loadScript(src) {
		return new Promise((resolve, reject) => {
			if (globalThis.document.querySelector(`script[src="${src}"]`)) {
				resolve();
				return;
			}
			const script = globalThis.document.createElement("script");
			script.src = src;
			script.async = true;
			script.onload = () => resolve();
			script.onerror = () => reject(/* @__PURE__ */ new Error(`Failed to load ${src}`));
			globalThis.document.head.appendChild(script);
		});
	}
	static loadPrettier() {
		if (!MarkdownUtils.prettierPromise) MarkdownUtils.prettierPromise = Promise.all([MarkdownUtils.loadScript("/bundles/pushwordadminblockeditor/prettier/standalone.js"), MarkdownUtils.loadScript("/bundles/pushwordadminblockeditor/prettier/markdown.js")]).then(() => ({
			prettier: globalThis.window.prettier,
			plugin: globalThis.window.prettierPlugins?.markdown
		}));
		return MarkdownUtils.prettierPromise;
	}
	static async formatMarkdownWithPrettier(markdownContent) {
		try {
			const { prettier, plugin } = await MarkdownUtils.loadPrettier();
			return (await prettier.format(markdownContent, {
				parser: "markdown",
				plugins: [plugin],
				proseWrap: "preserve",
				tabWidth: 2,
				useTabs: false
			})).trim();
		} catch {
			console.error("Erreur lors du formatage Prettier du Markdown", { content: markdownContent });
			return markdownContent;
		}
	}
	static wrapInQuotes(text) {
		const escaped = text.replace(/\\/g, "\\\\");
		if (!text.includes("'")) return "'" + escaped + "'";
		return `"${escaped.replace(/"/g, "\\\"")}"`;
	}
};
function e(text) {
	return MarkdownUtils.wrapInQuotes(text);
}
//#endregion
//#region src/assets/tools/Header/Header.ts
var Header = class Header {
	constructor({ data, api }) {
		this._levelSelect = null;
		this.api = api;
		this._data = Header.normalizeData(data);
		this._element = this.getTag();
	}
	static normalizeData(data) {
		return {
			text: data.text || "",
			level: parseInt((data.level || 2).toString())
		};
	}
	render() {
		return this._element;
	}
	setLevel(level) {
		this.data = {
			level,
			text: this.data.text
		};
		if (this._levelSelect) this._levelSelect.value = level.toString();
	}
	merge(data) {
		const headerElement = this.getHeaderElement();
		if (headerElement) headerElement.insertAdjacentHTML("beforeend", data.text);
	}
	validate(blockData) {
		return blockData.text.trim() !== "";
	}
	save(toolsContent) {
		const headerElement = this.getHeaderElement();
		return {
			text: headerElement ? headerElement.innerHTML : toolsContent.innerHTML,
			level: this.currentLevel.number
		};
	}
	static get conversionConfig() {
		return {
			export: "text",
			import: "text"
		};
	}
	static get sanitize() {
		return {
			level: false,
			text: {
				br: true,
				small: true,
				a: true,
				u: true,
				i: true,
				b: true,
				s: true,
				sup: true,
				sub: true
			}
		};
	}
	get data() {
		const headerElement = this.getHeaderElement();
		if (!headerElement) return this._data;
		this._data.text = headerElement.innerHTML;
		this._data.level = this.currentLevel.number;
		return this._data;
	}
	set data(data) {
		this._data = Header.normalizeData(data);
		if (data.level !== void 0 && this._element.parentNode) {
			const newHeader = this.getTag();
			const newHeaderElement = this.getHeaderElement(newHeader);
			const oldHeaderElement = this.getHeaderElement();
			if (newHeaderElement && oldHeaderElement) newHeaderElement.innerHTML = oldHeaderElement.innerHTML;
			this._element.parentNode.replaceChild(newHeader, this._element);
			this._element = newHeader;
			this._levelSelect = this._element.querySelector(".ce-header-level-select");
			const levelLabel = this._element.querySelector(".ce-header-level-label");
			if (levelLabel) levelLabel.dataset.level = `H${this._data.level}`;
		}
		if (data.text !== void 0) {
			const headerElement = this.getHeaderElement();
			if (headerElement) headerElement.innerHTML = data.text || "";
		}
	}
	getHeaderElement(element) {
		const target = element || this._element;
		if (!target) return null;
		const header = target.querySelector("h1, h2, h3, h4, h5, h6");
		if (header) return header;
		if (target.tagName.match(/^H[1-6]$/)) return target;
		return null;
	}
	getTag() {
		const container = globalThis.document.createElement("div");
		container.classList.add("ce-header-container");
		const levelWrapper = globalThis.document.createElement("div");
		levelWrapper.classList.add("ce-header-level-wrapper");
		levelWrapper.contentEditable = "false";
		const levelLabel = globalThis.document.createElement("span");
		levelLabel.classList.add("ce-header-level-label");
		levelLabel.dataset.level = `H${this._data.level}`;
		const levelSelect = globalThis.document.createElement("select");
		levelSelect.classList.add("ce-header-level-select");
		levelSelect.contentEditable = "false";
		levelSelect.title = "Select heading level";
		levelSelect.setAttribute("aria-label", "Heading level");
		this.levels.forEach((level) => {
			const option = globalThis.document.createElement("option");
			option.value = level.number.toString();
			option.textContent = `H${level.number}`;
			option.selected = level.number === this._data.level;
			levelSelect.appendChild(option);
		});
		levelSelect.addEventListener("mousedown", (e) => {
			e.stopPropagation();
		});
		levelSelect.addEventListener("change", (e) => {
			e.preventDefault();
			e.stopPropagation();
			const newLevel = parseInt(e.target.value);
			levelLabel.dataset.level = `H${newLevel}`;
			this.setLevel(newLevel);
		});
		this._levelSelect = levelSelect;
		levelWrapper.appendChild(levelLabel);
		levelWrapper.appendChild(levelSelect);
		const tag = globalThis.document.createElement(this.currentLevel.tag);
		tag.innerHTML = this._data.text || "";
		tag.classList.add("ce-header");
		tag.contentEditable = "true";
		tag.dataset.placeholder = this.api.i18n.t("");
		container.appendChild(levelWrapper);
		container.appendChild(tag);
		return container;
	}
	get currentLevel() {
		return this.levels.find((levelItem) => levelItem.number === this._data.level) || this.defaultLevel;
	}
	get defaultLevel() {
		const defaultLevel = this.levels[0];
		if (!defaultLevel) throw new Error("Default level not found");
		return defaultLevel;
	}
	get levels() {
		return [
			{
				number: 2,
				tag: "H2",
				svg: t
			},
			{
				number: 3,
				tag: "H3",
				svg: r$1
			},
			{
				number: 4,
				tag: "H4",
				svg: e$1
			},
			{
				number: 5,
				tag: "H5",
				svg: n$2
			},
			{
				number: 6,
				tag: "H6",
				svg: s
			}
		];
	}
	onPaste(event) {
		const detail = event.detail;
		if ("data" in detail) {
			const content = detail.data;
			const level = {
				H2: 2,
				H3: 3,
				H4: 4,
				H5: 5,
				H6: 6
			}[content.tagName] || 2;
			this.data = {
				level,
				text: content.innerHTML
			};
		}
	}
	static get pasteConfig() {
		return { tags: [
			"H1",
			"H2",
			"H3",
			"H4",
			"H5",
			"H6"
		] };
	}
	static get toolbox() {
		return {
			icon: G$2,
			title: "Heading"
		};
	}
	static async exportToMarkdown(data, tunes) {
		if (!data || !data.text) return "";
		const level = data.level || 2;
		let markdown = `${"#".repeat(level)} ${data.text}`;
		markdown = MarkdownUtils.convertInlineHtmlToMarkdown(markdown);
		const formattedMarkdown = await MarkdownUtils.formatMarkdownWithPrettier(markdown);
		return MarkdownUtils.addInlineAttributes(formattedMarkdown, tunes);
	}
	static importFromMarkdown(editor, markdown) {
		let tunes;
		let markdownWithoutTunes = markdown.trim();
		const inlineAttrMatch = markdownWithoutTunes.match(/^(#{2,6}\s.+?)\s+\{([^}]+)\}\s*$/);
		if (inlineAttrMatch) {
			tunes = MarkdownUtils.parseAttributes(inlineAttrMatch[2]);
			markdownWithoutTunes = inlineAttrMatch[1];
		} else {
			const result = MarkdownUtils.parseTunesFromMarkdown(markdown);
			tunes = result.tunes;
			markdownWithoutTunes = result.markdown;
		}
		markdownWithoutTunes = MarkdownUtils.convertInlineMarkdownToHtml(markdownWithoutTunes);
		const levelMatch = markdownWithoutTunes.trim().match(/^#{2,6}\s/);
		if (!levelMatch) throw new Error("Invalid markdown format for header");
		const data = {
			text: markdownWithoutTunes.replace(/^#{2,6}\s/, "").trim(),
			level: levelMatch[0].trim().length
		};
		const block = editor.blocks.insert("header");
		editor.blocks.update(block.id, data, tunes);
	}
	static isItMarkdownExported(markdown) {
		return /^#{2,6}\s/.test(markdown.trim());
	}
};
//#endregion
//#region node_modules/@editorjs/paragraph/dist/paragraph.mjs
(function() {
	"use strict";
	try {
		if (typeof globalThis.document < "u") {
			var e = globalThis.document.createElement("style");
			e.appendChild(globalThis.document.createTextNode(".ce-paragraph{line-height:1.6em;outline:none}.ce-block:only-of-type .ce-paragraph[data-placeholder-active]:empty:before,.ce-block:only-of-type .ce-paragraph[data-placeholder-active][data-empty=true]:before{content:attr(data-placeholder-active)}.ce-paragraph p:first-of-type{margin-top:0}.ce-paragraph p:last-of-type{margin-bottom:0}")), globalThis.document.head.appendChild(e);
		}
	} catch (a) {
		console.error("vite-plugin-css-injected-by-js", a);
	}
})();
var a = "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" fill=\"none\" viewBox=\"0 0 24 24\"><path stroke=\"currentColor\" stroke-linecap=\"round\" stroke-width=\"2\" d=\"M8 9V7.2C8 7.08954 8.08954 7 8.2 7L12 7M16 9V7.2C16 7.08954 15.9105 7 15.8 7L12 7M12 7L12 17M12 17H10M12 17H14\"/></svg>";
function l(r) {
	const t = globalThis.document.createElement("div");
	t.innerHTML = r.trim();
	const e = globalThis.document.createDocumentFragment();
	return e.append(...Array.from(t.childNodes)), e;
}
/**
* Base Paragraph Block for the Editor.js.
* Represents a regular text block
*
* @author CodeX (team@codex.so)
* @copyright CodeX 2018
* @license The MIT License (MIT)
*/
var n$1 = class n$1 {
	/**
	* Default placeholder for Paragraph Tool
	*
	* @returns {string}
	* @class
	*/
	static get DEFAULT_PLACEHOLDER() {
		return "";
	}
	/**
	* Render plugin`s main Element and fill it with saved data
	*
	* @param {object} params - constructor params
	* @param {ParagraphData} params.data - previously saved data
	* @param {ParagraphConfig} params.config - user config for Tool
	* @param {object} params.api - editor.js api
	* @param {boolean} readOnly - read only mode flag
	*/
	constructor({ data: t, config: e, api: i, readOnly: s }) {
		this.api = i, this.readOnly = s, this._CSS = {
			block: this.api.styles.block,
			wrapper: "ce-paragraph"
		}, this.readOnly || (this.onKeyUp = this.onKeyUp.bind(this)), this._placeholder = e.placeholder ? e.placeholder : n$1.DEFAULT_PLACEHOLDER, this._data = t ?? {}, this._element = null, this._preserveBlank = e.preserveBlank ?? !1;
	}
	/**
	* Check if text content is empty and set empty string to inner html.
	* We need this because some browsers (e.g. Safari) insert <br> into empty contenteditanle elements
	*
	* @param {KeyboardEvent} e - key up event
	*/
	onKeyUp(t) {
		if (t.code !== "Backspace" && t.code !== "Delete" || !this._element) return;
		const { textContent: e } = this._element;
		e === "" && (this._element.innerHTML = "");
	}
	/**
	* Create Tool's view
	*
	* @returns {HTMLDivElement}
	* @private
	*/
	drawView() {
		const t = globalThis.document.createElement("DIV");
		return t.classList.add(this._CSS.wrapper, this._CSS.block), t.contentEditable = "false", t.dataset.placeholderActive = this.api.i18n.t(this._placeholder), this._data.text && (t.innerHTML = this._data.text), this.readOnly || (t.contentEditable = "true", t.addEventListener("keyup", this.onKeyUp)), t;
	}
	/**
	* Return Tool's view
	*
	* @returns {HTMLDivElement}
	*/
	render() {
		return this._element = this.drawView(), this._element;
	}
	/**
	* Method that specified how to merge two Text blocks.
	* Called by Editor.js by backspace at the beginning of the Block
	*
	* @param {ParagraphData} data
	* @public
	*/
	merge(t) {
		if (!this._element) return;
		this._data.text += t.text;
		const e = l(t.text);
		this._element.appendChild(e), this._element.normalize();
	}
	/**
	* Validate Paragraph block data:
	* - check for emptiness
	*
	* @param {ParagraphData} savedData — data received after saving
	* @returns {boolean} false if saved data is not correct, otherwise true
	* @public
	*/
	validate(t) {
		return !(t.text.trim() === "" && !this._preserveBlank);
	}
	/**
	* Extract Tool's data from the view
	*
	* @param {HTMLDivElement} toolsContent - Paragraph tools rendered view
	* @returns {ParagraphData} - saved data
	* @public
	*/
	save(t) {
		return { text: t.innerHTML };
	}
	/**
	* On paste callback fired from Editor.
	*
	* @param {HTMLPasteEvent} event - event with pasted data
	*/
	onPaste(t) {
		const e = { text: t.detail.data.innerHTML };
		this._data = e, globalThis.window.requestAnimationFrame(() => {
			this._element && (this._element.innerHTML = this._data.text || "");
		});
	}
	/**
	* Enable Conversion Toolbar. Paragraph can be converted to/from other tools
	* @returns {ConversionConfig}
	*/
	static get conversionConfig() {
		return {
			export: "text",
			import: "text"
		};
	}
	/**
	* Sanitizer rules
	* @returns {SanitizerConfig} - Edtior.js sanitizer config
	*/
	static get sanitize() {
		return { text: { br: !0 } };
	}
	/**
	* Returns true to notify the core that read-only mode is supported
	*
	* @returns {boolean}
	*/
	static get isReadOnlySupported() {
		return !0;
	}
	/**
	* Used by Editor paste handling API.
	* Provides configuration to handle P tags.
	*
	* @returns {PasteConfig} - Paragraph Paste Setting
	*/
	static get pasteConfig() {
		return { tags: ["P"] };
	}
	/**
	* Icon and title for displaying at the Toolbox
	*
	* @returns {ToolboxConfig} - Paragraph Toolbox Setting
	*/
	static get toolbox() {
		return {
			icon: a,
			title: "Text"
		};
	}
};
//#endregion
//#region src/assets/tools/Paragraph/Paragraph.ts
var Paragraph = class extends n$1 {
	static async exportToMarkdown(data, tunes) {
		if (!data || !data.text) return "";
		let markdown = data.text.replace(/(&nbsp;| |\u00A0)+ */g, " ").split("<br>").join("  \n");
		markdown = MarkdownUtils.convertInlineHtmlToMarkdown(markdown);
		const formattedMarkdown = await MarkdownUtils.formatMarkdownWithPrettier(markdown);
		return MarkdownUtils.addAttributes(formattedMarkdown, tunes);
	}
	static importFromMarkdown(editor, markdown) {
		const result = MarkdownUtils.parseTunesFromMarkdown(markdown);
		const tunes = result.tunes;
		const block = editor.blocks.insert("paragraph");
		editor.blocks.update(block.id, { text: MarkdownUtils.convertInlineMarkdownToHtml(result.markdown) }, tunes);
	}
	static isItMarkdownExported(markdown) {
		const trimmed = markdown.trim();
		return ![
			"<",
			"{",
			"-->",
			"#}"
		].some((prefix) => trimmed.startsWith(prefix));
	}
};
//#endregion
//#region node_modules/@editorjs/list/dist/editorjs-list.mjs
(function() {
	"use strict";
	try {
		if (typeof globalThis.document < "u") {
			var e = globalThis.document.createElement("style");
			e.appendChild(globalThis.document.createTextNode(".cdx-list{margin:0;padding:0;outline:none;display:grid;counter-reset:item;gap:var(--spacing-s);padding:var(--spacing-xs);--spacing-s: 8px;--spacing-xs: 6px;--list-counter-type: numeric;--radius-border: 5px;--checkbox-background: #fff;--color-border: #C9C9C9;--color-bg-checked: #369FFF;--line-height: 1.45em;--color-bg-checked-hover: #0059AB;--color-tick: #fff;--size-checkbox: 1.2em}.cdx-list__item{line-height:var(--line-height);display:grid;grid-template-columns:auto 1fr;grid-template-rows:auto auto;grid-template-areas:\"checkbox content\" \". child\"}.cdx-list__item-children{display:grid;grid-area:child;gap:var(--spacing-s);padding-top:var(--spacing-s)}.cdx-list__item [contenteditable]{outline:none}.cdx-list__item-content{word-break:break-word;white-space:pre-wrap;grid-area:content;padding-left:var(--spacing-s)}.cdx-list__item:before{counter-increment:item;white-space:nowrap}.cdx-list-ordered .cdx-list__item:before{content:counters(item,\".\",var(--list-counter-type)) \".\"}.cdx-list-ordered{counter-reset:item}.cdx-list-unordered .cdx-list__item:before{content:\"•\"}.cdx-list-checklist .cdx-list__item:before{content:\"\"}.cdx-list__settings .cdx-settings-button{width:50%}.cdx-list__checkbox{padding-top:calc((var(--line-height) - var(--size-checkbox)) / 2);grid-area:checkbox;width:var(--size-checkbox);height:var(--size-checkbox);display:flex;cursor:pointer}.cdx-list__checkbox svg{opacity:0;height:var(--size-checkbox);width:var(--size-checkbox);left:-1px;top:-1px;position:absolute}@media (hover: hover){.cdx-list__checkbox:not(.cdx-list__checkbox--no-hover):hover .cdx-list__checkbox-check svg{opacity:1}}.cdx-list__checkbox--checked{line-height:var(--line-height)}@media (hover: hover){.cdx-list__checkbox--checked:not(.cdx-list__checkbox--checked--no-hover):hover .cdx-checklist__checkbox-check{background:var(--color-bg-checked-hover);border-color:var(--color-bg-checked-hover)}}.cdx-list__checkbox--checked .cdx-list__checkbox-check{background:var(--color-bg-checked);border-color:var(--color-bg-checked)}.cdx-list__checkbox--checked .cdx-list__checkbox-check svg{opacity:1}.cdx-list__checkbox--checked .cdx-list__checkbox-check svg path{stroke:var(--color-tick)}.cdx-list__checkbox--checked .cdx-list__checkbox-check:before{opacity:0;visibility:visible;transform:scale(2.5)}.cdx-list__checkbox-check{cursor:pointer;display:inline-block;position:relative;margin:0 auto;width:var(--size-checkbox);height:var(--size-checkbox);box-sizing:border-box;border-radius:var(--radius-border);border:1px solid var(--color-border);background:var(--checkbox-background)}.cdx-list__checkbox-check:before{content:\"\";position:absolute;top:0;right:0;bottom:0;left:0;border-radius:100%;background-color:var(--color-bg-checked);visibility:hidden;pointer-events:none;transform:scale(1);transition:transform .4s ease-out,opacity .4s}.cdx-list__checkbox-check--disabled{pointer-events:none}.cdx-list-start-with-field{background:#F8F8F8;border:1px solid rgba(226,226,229,.2);border-radius:6px;padding:2px;display:grid;grid-template-columns:auto auto 1fr;grid-template-rows:auto}.cdx-list-start-with-field--invalid{background:#FFECED;border:1px solid #E13F3F}.cdx-list-start-with-field--invalid .cdx-list-start-with-field__input{color:#e13f3f}.cdx-list-start-with-field__input{font-size:14px;outline:none;font-weight:500;font-family:inherit;border:0;background:transparent;margin:0;padding:0;line-height:22px;min-width:calc(100% - var(--toolbox-buttons-size) - var(--icon-margin-right))}.cdx-list-start-with-field__input::placeholder{color:var(--grayText);font-weight:500}")), globalThis.document.head.appendChild(e);
		}
	} catch (c) {
		console.error("vite-plugin-css-injected-by-js", c);
	}
})();
var Ct$1 = "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" fill=\"none\" viewBox=\"0 0 24 24\"><path stroke=\"currentColor\" stroke-linecap=\"round\" stroke-width=\"2\" d=\"M7 12L10.4884 15.8372C10.5677 15.9245 10.705 15.9245 10.7844 15.8372L17 9\"/></svg>";
var Ae = "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" fill=\"none\" viewBox=\"0 0 24 24\"><path stroke=\"currentColor\" stroke-linecap=\"round\" stroke-width=\"2\" d=\"M9.2 12L11.0586 13.8586C11.1367 13.9367 11.2633 13.9367 11.3414 13.8586L14.7 10.5\"/><rect width=\"14\" height=\"14\" x=\"5\" y=\"5\" stroke=\"currentColor\" stroke-width=\"2\" rx=\"4\"/></svg>";
var $e = "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" fill=\"none\" viewBox=\"0 0 24 24\"><line x1=\"9\" x2=\"19\" y1=\"7\" y2=\"7\" stroke=\"currentColor\" stroke-linecap=\"round\" stroke-width=\"2\"/><line x1=\"9\" x2=\"19\" y1=\"12\" y2=\"12\" stroke=\"currentColor\" stroke-linecap=\"round\" stroke-width=\"2\"/><line x1=\"9\" x2=\"19\" y1=\"17\" y2=\"17\" stroke=\"currentColor\" stroke-linecap=\"round\" stroke-width=\"2\"/><path stroke=\"currentColor\" stroke-linecap=\"round\" stroke-width=\"2\" d=\"M5.00001 17H4.99002\"/><path stroke=\"currentColor\" stroke-linecap=\"round\" stroke-width=\"2\" d=\"M5.00001 12H4.99002\"/><path stroke=\"currentColor\" stroke-linecap=\"round\" stroke-width=\"2\" d=\"M5.00001 7H4.99002\"/></svg>";
var Be = "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" fill=\"none\" viewBox=\"0 0 24 24\"><line x1=\"12\" x2=\"19\" y1=\"7\" y2=\"7\" stroke=\"currentColor\" stroke-linecap=\"round\" stroke-width=\"2\"/><line x1=\"12\" x2=\"19\" y1=\"12\" y2=\"12\" stroke=\"currentColor\" stroke-linecap=\"round\" stroke-width=\"2\"/><line x1=\"12\" x2=\"19\" y1=\"17\" y2=\"17\" stroke=\"currentColor\" stroke-linecap=\"round\" stroke-width=\"2\"/><path stroke=\"currentColor\" stroke-linecap=\"round\" stroke-width=\"2\" d=\"M7.79999 14L7.79999 7.2135C7.79999 7.12872 7.7011 7.0824 7.63597 7.13668L4.79999 9.5\"/></svg>";
var St$1 = "<svg width=\"20\" height=\"20\" viewBox=\"0 0 20 20\" fill=\"none\" xmlns=\"http://www.w3.org/2000/svg\"><path d=\"M10 14.2L10 7.4135C10 7.32872 9.90111 7.28241 9.83598 7.33668L7 9.7\" stroke=\"black\" stroke-width=\"1.6\" stroke-linecap=\"round\"/><path d=\"M13.2087 14.2H13.2\" stroke=\"black\" stroke-width=\"1.6\" stroke-linecap=\"round\"/></svg>";
var Ot$1 = "<svg width=\"20\" height=\"20\" viewBox=\"0 0 20 20\" fill=\"none\" xmlns=\"http://www.w3.org/2000/svg\"><path d=\"M13.2087 14.2H13.2\" stroke=\"black\" stroke-width=\"1.6\" stroke-linecap=\"round\"/><path d=\"M10 14.2L10 9.5\" stroke=\"black\" stroke-width=\"1.6\" stroke-linecap=\"round\"/><path d=\"M10 7.01L10 7\" stroke=\"black\" stroke-width=\"1.8\" stroke-linecap=\"round\"/></svg>";
var kt$1 = "<svg width=\"20\" height=\"20\" viewBox=\"0 0 20 20\" fill=\"none\" xmlns=\"http://www.w3.org/2000/svg\"><path d=\"M13.2087 14.2H13.2\" stroke=\"black\" stroke-width=\"1.6\" stroke-linecap=\"round\"/><path d=\"M10 14.2L10 7.2\" stroke=\"black\" stroke-width=\"1.6\" stroke-linecap=\"round\"/></svg>";
var _t$1 = "<svg width=\"20\" height=\"20\" viewBox=\"0 0 20 20\" fill=\"none\" xmlns=\"http://www.w3.org/2000/svg\"><path d=\"M16.0087 14.2H16\" stroke=\"black\" stroke-width=\"1.6\" stroke-linecap=\"round\"/><path d=\"M7 14.2L7.78865 12M13 14.2L12.1377 12M7.78865 12C7.78865 12 9.68362 7 10 7C10.3065 7 12.1377 12 12.1377 12M7.78865 12L12.1377 12\" stroke=\"black\" stroke-width=\"1.6\" stroke-linecap=\"round\"/></svg>";
var Et$1 = "<svg width=\"20\" height=\"20\" viewBox=\"0 0 20 20\" fill=\"none\" xmlns=\"http://www.w3.org/2000/svg\"><path d=\"M14.2087 14.2H14.2\" stroke=\"black\" stroke-width=\"1.6\" stroke-linecap=\"round\"/><path d=\"M11.5 14.5C11.5 14.5 11 13.281 11 12.5M7 9.5C7 9.5 7.5 8.5 9 8.5C10.5 8.5 11 9.5 11 10.5L11 11.5M11 11.5L11 12.5M11 11.5C11 11.5 7 11 7 13C7 15.3031 11 15 11 12.5\" stroke=\"black\" stroke-width=\"1.6\" stroke-linecap=\"round\"/></svg>";
var It$1 = "<svg width=\"20\" height=\"20\" viewBox=\"0 0 20 20\" fill=\"none\" xmlns=\"http://www.w3.org/2000/svg\"><path d=\"M8 14.2L8 7.4135C8 7.32872 7.90111 7.28241 7.83598 7.33668L5 9.7\" stroke=\"black\" stroke-width=\"1.6\" stroke-linecap=\"round\"/><path d=\"M14 13L16.4167 10.7778M16.4167 10.7778L14 8.5M16.4167 10.7778H11.6562\" stroke=\"black\" stroke-width=\"1.6\" stroke-linecap=\"round\" stroke-linejoin=\"round\"/></svg>";
var A$1 = typeof globalThis < "u" ? globalThis : typeof globalThis.window < "u" ? globalThis.window : typeof global < "u" ? global : typeof self < "u" ? self : {};
function wt$1(e) {
	if (e.__esModule) return e;
	var t = e.default;
	if (typeof t == "function") {
		var n = function r() {
			return this instanceof r ? Reflect.construct(t, arguments, this.constructor) : t.apply(this, arguments);
		};
		n.prototype = t.prototype;
	} else n = {};
	return Object.defineProperty(n, "__esModule", { value: !0 }), Object.keys(e).forEach(function(r) {
		var i = Object.getOwnPropertyDescriptor(e, r);
		Object.defineProperty(n, r, i.get ? i : {
			enumerable: !0,
			get: function() {
				return e[r];
			}
		});
	}), n;
}
var c$1 = {};
var V$1 = {};
var Y$1 = {};
Object.defineProperty(Y$1, "__esModule", { value: !0 });
Y$1.allInputsSelector = Pt$1;
function Pt$1() {
	return "[contenteditable=true], textarea, input:not([type]), " + [
		"text",
		"password",
		"email",
		"number",
		"search",
		"tel",
		"url"
	].map(function(t) {
		return "input[type=\"".concat(t, "\"]");
	}).join(", ");
}
(function(e) {
	Object.defineProperty(e, "__esModule", { value: !0 }), e.allInputsSelector = void 0;
	var t = Y$1;
	Object.defineProperty(e, "allInputsSelector", {
		enumerable: !0,
		get: function() {
			return t.allInputsSelector;
		}
	});
})(V$1);
var k$1 = {};
var J$1 = {};
Object.defineProperty(J$1, "__esModule", { value: !0 });
J$1.isNativeInput = jt$1;
function jt$1(e) {
	return e && e.tagName ? ["INPUT", "TEXTAREA"].includes(e.tagName) : !1;
}
(function(e) {
	Object.defineProperty(e, "__esModule", { value: !0 }), e.isNativeInput = void 0;
	var t = J$1;
	Object.defineProperty(e, "isNativeInput", {
		enumerable: !0,
		get: function() {
			return t.isNativeInput;
		}
	});
})(k$1);
var Fe$1 = {};
var Q$1 = {};
Object.defineProperty(Q$1, "__esModule", { value: !0 });
Q$1.append = Tt$1;
function Tt$1(e, t) {
	Array.isArray(t) ? t.forEach(function(n) {
		e.appendChild(n);
	}) : e.appendChild(t);
}
(function(e) {
	Object.defineProperty(e, "__esModule", { value: !0 }), e.append = void 0;
	var t = Q$1;
	Object.defineProperty(e, "append", {
		enumerable: !0,
		get: function() {
			return t.append;
		}
	});
})(Fe$1);
var Z$1 = {};
var x$1 = {};
Object.defineProperty(x$1, "__esModule", { value: !0 });
x$1.blockElements = Lt$1;
function Lt$1() {
	return [
		"address",
		"article",
		"aside",
		"blockquote",
		"canvas",
		"div",
		"dl",
		"dt",
		"fieldset",
		"figcaption",
		"figure",
		"footer",
		"form",
		"h1",
		"h2",
		"h3",
		"h4",
		"h5",
		"h6",
		"header",
		"hgroup",
		"hr",
		"li",
		"main",
		"nav",
		"noscript",
		"ol",
		"output",
		"p",
		"pre",
		"ruby",
		"section",
		"table",
		"tbody",
		"thead",
		"tr",
		"tfoot",
		"ul",
		"video"
	];
}
(function(e) {
	Object.defineProperty(e, "__esModule", { value: !0 }), e.blockElements = void 0;
	var t = x$1;
	Object.defineProperty(e, "blockElements", {
		enumerable: !0,
		get: function() {
			return t.blockElements;
		}
	});
})(Z$1);
var Re$1 = {};
var ee$1 = {};
Object.defineProperty(ee$1, "__esModule", { value: !0 });
ee$1.calculateBaseline = Mt$1;
function Mt$1(e) {
	var t = globalThis.window.getComputedStyle(e), n = parseFloat(t.fontSize), r = parseFloat(t.lineHeight) || n * 1.2, i = parseFloat(t.paddingTop), a = parseFloat(t.borderTopWidth), l = parseFloat(t.marginTop), s = n * .8, o = (r - n) / 2;
	return l + a + i + o + s;
}
(function(e) {
	Object.defineProperty(e, "__esModule", { value: !0 }), e.calculateBaseline = void 0;
	var t = ee$1;
	Object.defineProperty(e, "calculateBaseline", {
		enumerable: !0,
		get: function() {
			return t.calculateBaseline;
		}
	});
})(Re$1);
var qe$1 = {};
var te$1 = {};
var ne$1 = {};
var re$1 = {};
Object.defineProperty(re$1, "__esModule", { value: !0 });
re$1.isContentEditable = Nt$1;
function Nt$1(e) {
	return e.contentEditable === "true";
}
(function(e) {
	Object.defineProperty(e, "__esModule", { value: !0 }), e.isContentEditable = void 0;
	var t = re$1;
	Object.defineProperty(e, "isContentEditable", {
		enumerable: !0,
		get: function() {
			return t.isContentEditable;
		}
	});
})(ne$1);
Object.defineProperty(te$1, "__esModule", { value: !0 });
te$1.canSetCaret = Bt$1;
var At$1 = k$1;
var $t$1 = ne$1;
function Bt$1(e) {
	var t = !0;
	if ((0, At$1.isNativeInput)(e)) switch (e.type) {
		case "file":
		case "checkbox":
		case "radio":
		case "hidden":
		case "submit":
		case "button":
		case "image":
		case "reset": t = !1;
	}
	else t = (0, $t$1.isContentEditable)(e);
	return t;
}
(function(e) {
	Object.defineProperty(e, "__esModule", { value: !0 }), e.canSetCaret = void 0;
	var t = te$1;
	Object.defineProperty(e, "canSetCaret", {
		enumerable: !0,
		get: function() {
			return t.canSetCaret;
		}
	});
})(qe$1);
var $$1 = {};
var ie$1 = {};
function Wt$1(e, t, n) {
	const r = n.value !== void 0 ? "value" : "get", i = n[r], a = `#${t}Cache`;
	if (n[r] = function(...l) {
		return this[a] === void 0 && (this[a] = i.apply(this, l)), this[a];
	}, r === "get" && n.set) {
		const l = n.set;
		n.set = function(s) {
			delete e[a], l.apply(this, s);
		};
	}
	return n;
}
function Ue$1() {
	const e = {
		win: !1,
		mac: !1,
		x11: !1,
		linux: !1
	}, t = Object.keys(e).find((n) => globalThis.window.navigator.appVersion.toLowerCase().indexOf(n) !== -1);
	return t !== void 0 && (e[t] = !0), e;
}
function ae$1(e) {
	return e != null && e !== "" && (typeof e != "object" || Object.keys(e).length > 0);
}
function Dt$1(e) {
	return !ae$1(e);
}
var Ht$1 = () => typeof globalThis.window < "u" && globalThis.window.navigator !== null && ae$1(globalThis.window.navigator.platform) && (/iP(ad|hone|od)/.test(globalThis.window.navigator.platform) || globalThis.window.navigator.platform === "MacIntel" && globalThis.window.navigator.maxTouchPoints > 1);
function Ft$1(e) {
	const t = Ue$1();
	return e = e.replace(/shift/gi, "⇧").replace(/backspace/gi, "⌫").replace(/enter/gi, "⏎").replace(/up/gi, "↑").replace(/left/gi, "→").replace(/down/gi, "↓").replace(/right/gi, "←").replace(/escape/gi, "⎋").replace(/insert/gi, "Ins").replace(/delete/gi, "␡").replace(/\+/gi, "+"), t.mac ? e = e.replace(/ctrl|cmd/gi, "⌘").replace(/alt/gi, "⌥") : e = e.replace(/cmd/gi, "Ctrl").replace(/windows/gi, "WIN"), e;
}
function Rt$1(e) {
	return e[0].toUpperCase() + e.slice(1);
}
function qt$1(e) {
	const t = globalThis.document.createElement("div");
	t.style.position = "absolute", t.style.left = "-999px", t.style.bottom = "-999px", t.innerHTML = e, globalThis.document.body.appendChild(t);
	const n = globalThis.window.getSelection(), r = globalThis.document.createRange();
	if (r.selectNode(t), n === null) throw new Error("Cannot copy text to clipboard");
	n.removeAllRanges(), n.addRange(r), globalThis.document.execCommand("copy"), globalThis.document.body.removeChild(t);
}
function Ut$1(e, t, n) {
	let r;
	return (...i) => {
		const a = this, l = () => {
			r = void 0, n !== !0 && e.apply(a, i);
		}, s = n === !0 && r !== void 0;
		globalThis.window.clearTimeout(r), r = globalThis.window.setTimeout(l, t), s && e.apply(a, i);
	};
}
function S$1(e) {
	return Object.prototype.toString.call(e).match(/\s([a-zA-Z]+)/)[1].toLowerCase();
}
function Kt$1(e) {
	return S$1(e) === "boolean";
}
function Ke$1(e) {
	return S$1(e) === "function" || S$1(e) === "asyncfunction";
}
function zt$1(e) {
	return Ke$1(e) && /^\s*class\s+/.test(e.toString());
}
function Xt$1(e) {
	return S$1(e) === "number";
}
function M$1(e) {
	return S$1(e) === "object";
}
function Gt$1(e) {
	return Promise.resolve(e) === e;
}
function Vt$1(e) {
	return S$1(e) === "string";
}
function Yt$1(e) {
	return S$1(e) === "undefined";
}
function X$1(e, ...t) {
	if (!t.length) return e;
	const n = t.shift();
	if (M$1(e) && M$1(n)) for (const r in n) M$1(n[r]) ? (e[r] === void 0 && Object.assign(e, { [r]: {} }), X$1(e[r], n[r])) : Object.assign(e, { [r]: n[r] });
	return X$1(e, ...t);
}
function Jt$1(e, t, n) {
	const r = `«${t}» is deprecated and will be removed in the next major release. Please use the «${n}» instead.`;
	e && console.warn(r);
}
function Qt$1(e) {
	try {
		return new URL(e).href;
	} catch {}
	return e.substring(0, 2) === "//" ? globalThis.window.location.protocol + e : globalThis.window.location.origin + e;
}
function Zt$1(e) {
	return e > 47 && e < 58 || e === 32 || e === 13 || e === 229 || e > 64 && e < 91 || e > 95 && e < 112 || e > 185 && e < 193 || e > 218 && e < 223;
}
var xt$1 = {
	BACKSPACE: 8,
	TAB: 9,
	ENTER: 13,
	SHIFT: 16,
	CTRL: 17,
	ALT: 18,
	ESC: 27,
	SPACE: 32,
	LEFT: 37,
	UP: 38,
	DOWN: 40,
	RIGHT: 39,
	DELETE: 46,
	META: 91,
	SLASH: 191
};
var en = {
	LEFT: 0,
	WHEEL: 1,
	RIGHT: 2,
	BACKWARD: 3,
	FORWARD: 4
};
var tn = class {
	constructor() {
		this.completed = Promise.resolve();
	}
	/**
	* Add new promise to queue
	* @param operation - promise should be added to queue
	*/
	add(t) {
		return new Promise((n, r) => {
			this.completed = this.completed.then(t).then(n).catch(r);
		});
	}
};
function nn(e, t, n = void 0) {
	let r, i, a, l = null, s = 0;
	n || (n = {});
	const o = function() {
		s = n.leading === !1 ? 0 : Date.now(), l = null, a = e.apply(r, i), l === null && (r = i = null);
	};
	return function() {
		const d = Date.now();
		!s && n.leading === !1 && (s = d);
		const u = t - (d - s);
		return r = this, i = arguments, u <= 0 || u > t ? (l && (clearTimeout(l), l = null), s = d, a = e.apply(r, i), l === null && (r = i = null)) : !l && n.trailing !== !1 && (l = setTimeout(o, u)), a;
	};
}
var le$1 = /* @__PURE__ */ wt$1(/* @__PURE__ */ Object.freeze(/* @__PURE__ */ Object.defineProperty({
	__proto__: null,
	PromiseQueue: tn,
	beautifyShortcut: Ft$1,
	cacheable: Wt$1,
	capitalize: Rt$1,
	copyTextToClipboard: qt$1,
	debounce: Ut$1,
	deepMerge: X$1,
	deprecationAssert: Jt$1,
	getUserOS: Ue$1,
	getValidUrl: Qt$1,
	isBoolean: Kt$1,
	isClass: zt$1,
	isEmpty: Dt$1,
	isFunction: Ke$1,
	isIosDevice: Ht$1,
	isNumber: Xt$1,
	isObject: M$1,
	isPrintableKey: Zt$1,
	isPromise: Gt$1,
	isString: Vt$1,
	isUndefined: Yt$1,
	keyCodes: xt$1,
	mouseButtons: en,
	notEmpty: ae$1,
	throttle: nn,
	typeOf: S$1
}, Symbol.toStringTag, { value: "Module" })));
Object.defineProperty(ie$1, "__esModule", { value: !0 });
ie$1.containsOnlyInlineElements = sn;
var an = le$1;
var ln = Z$1;
function sn(e) {
	var t;
	(0, an.isString)(e) ? (t = globalThis.document.createElement("div"), t.innerHTML = e) : t = e;
	var n = function(r) {
		return !(0, ln.blockElements)().includes(r.tagName.toLowerCase()) && Array.from(r.children).every(n);
	};
	return Array.from(t.children).every(n);
}
(function(e) {
	Object.defineProperty(e, "__esModule", { value: !0 }), e.containsOnlyInlineElements = void 0;
	var t = ie$1;
	Object.defineProperty(e, "containsOnlyInlineElements", {
		enumerable: !0,
		get: function() {
			return t.containsOnlyInlineElements;
		}
	});
})($$1);
var ze$1 = {};
var se$1 = {};
var B$1 = {};
var oe$1 = {};
Object.defineProperty(oe$1, "__esModule", { value: !0 });
oe$1.make = on;
function on(e, t, n) {
	var r;
	t === void 0 && (t = null), n === void 0 && (n = {});
	var i = globalThis.document.createElement(e);
	if (Array.isArray(t)) {
		var a = t.filter(function(s) {
			return s !== void 0;
		});
		(r = i.classList).add.apply(r, a);
	} else t !== null && i.classList.add(t);
	for (var l in n) Object.prototype.hasOwnProperty.call(n, l) && (i[l] = n[l]);
	return i;
}
(function(e) {
	Object.defineProperty(e, "__esModule", { value: !0 }), e.make = void 0;
	var t = oe$1;
	Object.defineProperty(e, "make", {
		enumerable: !0,
		get: function() {
			return t.make;
		}
	});
})(B$1);
Object.defineProperty(se$1, "__esModule", { value: !0 });
se$1.fragmentToString = cn;
var un = B$1;
function cn(e) {
	var t = (0, un.make)("div");
	return t.appendChild(e), t.innerHTML;
}
(function(e) {
	Object.defineProperty(e, "__esModule", { value: !0 }), e.fragmentToString = void 0;
	var t = se$1;
	Object.defineProperty(e, "fragmentToString", {
		enumerable: !0,
		get: function() {
			return t.fragmentToString;
		}
	});
})(ze$1);
var Xe$1 = {};
var ue$1 = {};
Object.defineProperty(ue$1, "__esModule", { value: !0 });
ue$1.getContentLength = fn;
var dn = k$1;
function fn(e) {
	var t, n;
	return (0, dn.isNativeInput)(e) ? e.value.length : e.nodeType === Node.TEXT_NODE ? e.length : (n = (t = e.textContent) === null || t === void 0 ? void 0 : t.length) !== null && n !== void 0 ? n : 0;
}
(function(e) {
	Object.defineProperty(e, "__esModule", { value: !0 }), e.getContentLength = void 0;
	var t = ue$1;
	Object.defineProperty(e, "getContentLength", {
		enumerable: !0,
		get: function() {
			return t.getContentLength;
		}
	});
})(Xe$1);
var ce$1 = {};
var de$1 = {};
var We$1 = A$1 && A$1.__spreadArray || function(e, t, n) {
	if (n || arguments.length === 2) for (var r = 0, i = t.length, a; r < i; r++) (a || !(r in t)) && (a || (a = Array.prototype.slice.call(t, 0, r)), a[r] = t[r]);
	return e.concat(a || Array.prototype.slice.call(t));
};
Object.defineProperty(de$1, "__esModule", { value: !0 });
de$1.getDeepestBlockElements = Ge$1;
var pn = $$1;
function Ge$1(e) {
	return (0, pn.containsOnlyInlineElements)(e) ? [e] : Array.from(e.children).reduce(function(t, n) {
		return We$1(We$1([], t, !0), Ge$1(n), !0);
	}, []);
}
(function(e) {
	Object.defineProperty(e, "__esModule", { value: !0 }), e.getDeepestBlockElements = void 0;
	var t = de$1;
	Object.defineProperty(e, "getDeepestBlockElements", {
		enumerable: !0,
		get: function() {
			return t.getDeepestBlockElements;
		}
	});
})(ce$1);
var Ve$1 = {};
var fe$1 = {};
var W$1 = {};
var pe$1 = {};
Object.defineProperty(pe$1, "__esModule", { value: !0 });
pe$1.isLineBreakTag = hn;
function hn(e) {
	return ["BR", "WBR"].includes(e.tagName);
}
(function(e) {
	Object.defineProperty(e, "__esModule", { value: !0 }), e.isLineBreakTag = void 0;
	var t = pe$1;
	Object.defineProperty(e, "isLineBreakTag", {
		enumerable: !0,
		get: function() {
			return t.isLineBreakTag;
		}
	});
})(W$1);
var D$1 = {};
var he$2 = {};
Object.defineProperty(he$2, "__esModule", { value: !0 });
he$2.isSingleTag = mn;
function mn(e) {
	return [
		"AREA",
		"BASE",
		"BR",
		"COL",
		"COMMAND",
		"EMBED",
		"HR",
		"IMG",
		"INPUT",
		"KEYGEN",
		"LINK",
		"META",
		"PARAM",
		"SOURCE",
		"TRACK",
		"WBR"
	].includes(e.tagName);
}
(function(e) {
	Object.defineProperty(e, "__esModule", { value: !0 }), e.isSingleTag = void 0;
	var t = he$2;
	Object.defineProperty(e, "isSingleTag", {
		enumerable: !0,
		get: function() {
			return t.isSingleTag;
		}
	});
})(D$1);
Object.defineProperty(fe$1, "__esModule", { value: !0 });
fe$1.getDeepestNode = Ye$1;
var gn = k$1;
var vn = W$1;
var bn = D$1;
function Ye$1(e, t) {
	t === void 0 && (t = !1);
	var n = t ? "lastChild" : "firstChild", r = t ? "previousSibling" : "nextSibling";
	if (e.nodeType === Node.ELEMENT_NODE && e[n]) {
		var i = e[n];
		if ((0, bn.isSingleTag)(i) && !(0, gn.isNativeInput)(i) && !(0, vn.isLineBreakTag)(i)) if (i[r]) i = i[r];
		else if (i.parentNode !== null && i.parentNode[r]) i = i.parentNode[r];
		else return i.parentNode;
		return Ye$1(i, t);
	}
	return e;
}
(function(e) {
	Object.defineProperty(e, "__esModule", { value: !0 }), e.getDeepestNode = void 0;
	var t = fe$1;
	Object.defineProperty(e, "getDeepestNode", {
		enumerable: !0,
		get: function() {
			return t.getDeepestNode;
		}
	});
})(Ve$1);
var Je$1 = {};
var me$1 = {};
var T$1 = A$1 && A$1.__spreadArray || function(e, t, n) {
	if (n || arguments.length === 2) for (var r = 0, i = t.length, a; r < i; r++) (a || !(r in t)) && (a || (a = Array.prototype.slice.call(t, 0, r)), a[r] = t[r]);
	return e.concat(a || Array.prototype.slice.call(t));
};
Object.defineProperty(me$1, "__esModule", { value: !0 });
me$1.findAllInputs = kn;
var yn = $$1;
var Cn = ce$1;
var Sn = V$1;
var On = k$1;
function kn(e) {
	return Array.from(e.querySelectorAll((0, Sn.allInputsSelector)())).reduce(function(t, n) {
		return (0, On.isNativeInput)(n) || (0, yn.containsOnlyInlineElements)(n) ? T$1(T$1([], t, !0), [n], !1) : T$1(T$1([], t, !0), (0, Cn.getDeepestBlockElements)(n), !0);
	}, []);
}
(function(e) {
	Object.defineProperty(e, "__esModule", { value: !0 }), e.findAllInputs = void 0;
	var t = me$1;
	Object.defineProperty(e, "findAllInputs", {
		enumerable: !0,
		get: function() {
			return t.findAllInputs;
		}
	});
})(Je$1);
var Qe$1 = {};
var ge$1 = {};
Object.defineProperty(ge$1, "__esModule", { value: !0 });
ge$1.isCollapsedWhitespaces = _n;
function _n(e) {
	return !/[^\t\n\r ]/.test(e);
}
(function(e) {
	Object.defineProperty(e, "__esModule", { value: !0 }), e.isCollapsedWhitespaces = void 0;
	var t = ge$1;
	Object.defineProperty(e, "isCollapsedWhitespaces", {
		enumerable: !0,
		get: function() {
			return t.isCollapsedWhitespaces;
		}
	});
})(Qe$1);
var ve$1 = {};
var be$1 = {};
Object.defineProperty(be$1, "__esModule", { value: !0 });
be$1.isElement = In;
var En = le$1;
function In(e) {
	return (0, En.isNumber)(e) ? !1 : !!e && !!e.nodeType && e.nodeType === Node.ELEMENT_NODE;
}
(function(e) {
	Object.defineProperty(e, "__esModule", { value: !0 }), e.isElement = void 0;
	var t = be$1;
	Object.defineProperty(e, "isElement", {
		enumerable: !0,
		get: function() {
			return t.isElement;
		}
	});
})(ve$1);
var Ze$1 = {};
var ye$1 = {};
var Ce = {};
var Se = {};
Object.defineProperty(Se, "__esModule", { value: !0 });
Se.isLeaf = wn;
function wn(e) {
	return e === null ? !1 : e.childNodes.length === 0;
}
(function(e) {
	Object.defineProperty(e, "__esModule", { value: !0 }), e.isLeaf = void 0;
	var t = Se;
	Object.defineProperty(e, "isLeaf", {
		enumerable: !0,
		get: function() {
			return t.isLeaf;
		}
	});
})(Ce);
var Oe = {};
var ke = {};
Object.defineProperty(ke, "__esModule", { value: !0 });
ke.isNodeEmpty = Mn;
var Pn = W$1;
var jn = ve$1;
var Tn = k$1;
var Ln = D$1;
function Mn(e, t) {
	var n = "";
	return (0, Ln.isSingleTag)(e) && !(0, Pn.isLineBreakTag)(e) ? !1 : ((0, jn.isElement)(e) && (0, Tn.isNativeInput)(e) ? n = e.value : e.textContent !== null && (n = e.textContent.replace("​", "")), t !== void 0 && (n = n.replace(new RegExp(t, "g"), "")), n.trim().length === 0);
}
(function(e) {
	Object.defineProperty(e, "__esModule", { value: !0 }), e.isNodeEmpty = void 0;
	var t = ke;
	Object.defineProperty(e, "isNodeEmpty", {
		enumerable: !0,
		get: function() {
			return t.isNodeEmpty;
		}
	});
})(Oe);
Object.defineProperty(ye$1, "__esModule", { value: !0 });
ye$1.isEmpty = $n;
var Nn = Ce;
var An = Oe;
function $n(e, t) {
	e.normalize();
	for (var n = [e]; n.length > 0;) {
		var r = n.shift();
		if (r) {
			if (e = r, (0, Nn.isLeaf)(e) && !(0, An.isNodeEmpty)(e, t)) return !1;
			n.push.apply(n, Array.from(e.childNodes));
		}
	}
	return !0;
}
(function(e) {
	Object.defineProperty(e, "__esModule", { value: !0 }), e.isEmpty = void 0;
	var t = ye$1;
	Object.defineProperty(e, "isEmpty", {
		enumerable: !0,
		get: function() {
			return t.isEmpty;
		}
	});
})(Ze$1);
var xe$1 = {};
var _e$1 = {};
Object.defineProperty(_e$1, "__esModule", { value: !0 });
_e$1.isFragment = Wn;
var Bn = le$1;
function Wn(e) {
	return (0, Bn.isNumber)(e) ? !1 : !!e && !!e.nodeType && e.nodeType === Node.DOCUMENT_FRAGMENT_NODE;
}
(function(e) {
	Object.defineProperty(e, "__esModule", { value: !0 }), e.isFragment = void 0;
	var t = _e$1;
	Object.defineProperty(e, "isFragment", {
		enumerable: !0,
		get: function() {
			return t.isFragment;
		}
	});
})(xe$1);
var et$1 = {};
var Ee$1 = {};
Object.defineProperty(Ee$1, "__esModule", { value: !0 });
Ee$1.isHTMLString = Hn;
var Dn = B$1;
function Hn(e) {
	var t = (0, Dn.make)("div");
	return t.innerHTML = e, t.childElementCount > 0;
}
(function(e) {
	Object.defineProperty(e, "__esModule", { value: !0 }), e.isHTMLString = void 0;
	var t = Ee$1;
	Object.defineProperty(e, "isHTMLString", {
		enumerable: !0,
		get: function() {
			return t.isHTMLString;
		}
	});
})(et$1);
var tt$1 = {};
var Ie = {};
Object.defineProperty(Ie, "__esModule", { value: !0 });
Ie.offset = Fn;
function Fn(e) {
	var t = e.getBoundingClientRect(), n = globalThis.window.pageXOffset || globalThis.document.documentElement.scrollLeft, r = globalThis.window.pageYOffset || globalThis.document.documentElement.scrollTop, i = t.top + r, a = t.left + n;
	return {
		top: i,
		left: a,
		bottom: i + t.height,
		right: a + t.width
	};
}
(function(e) {
	Object.defineProperty(e, "__esModule", { value: !0 }), e.offset = void 0;
	var t = Ie;
	Object.defineProperty(e, "offset", {
		enumerable: !0,
		get: function() {
			return t.offset;
		}
	});
})(tt$1);
var nt$1 = {};
var we = {};
Object.defineProperty(we, "__esModule", { value: !0 });
we.prepend = Rn;
function Rn(e, t) {
	Array.isArray(t) ? (t = t.reverse(), t.forEach(function(n) {
		return e.prepend(n);
	})) : e.prepend(t);
}
(function(e) {
	Object.defineProperty(e, "__esModule", { value: !0 }), e.prepend = void 0;
	var t = we;
	Object.defineProperty(e, "prepend", {
		enumerable: !0,
		get: function() {
			return t.prepend;
		}
	});
})(nt$1);
(function(e) {
	Object.defineProperty(e, "__esModule", { value: !0 }), e.prepend = e.offset = e.make = e.isLineBreakTag = e.isSingleTag = e.isNodeEmpty = e.isLeaf = e.isHTMLString = e.isFragment = e.isEmpty = e.isElement = e.isContentEditable = e.isCollapsedWhitespaces = e.findAllInputs = e.isNativeInput = e.allInputsSelector = e.getDeepestNode = e.getDeepestBlockElements = e.getContentLength = e.fragmentToString = e.containsOnlyInlineElements = e.canSetCaret = e.calculateBaseline = e.blockElements = e.append = void 0;
	var t = V$1;
	Object.defineProperty(e, "allInputsSelector", {
		enumerable: !0,
		get: function() {
			return t.allInputsSelector;
		}
	});
	var n = k$1;
	Object.defineProperty(e, "isNativeInput", {
		enumerable: !0,
		get: function() {
			return n.isNativeInput;
		}
	});
	var r = Fe$1;
	Object.defineProperty(e, "append", {
		enumerable: !0,
		get: function() {
			return r.append;
		}
	});
	var i = Z$1;
	Object.defineProperty(e, "blockElements", {
		enumerable: !0,
		get: function() {
			return i.blockElements;
		}
	});
	var a = Re$1;
	Object.defineProperty(e, "calculateBaseline", {
		enumerable: !0,
		get: function() {
			return a.calculateBaseline;
		}
	});
	var l = qe$1;
	Object.defineProperty(e, "canSetCaret", {
		enumerable: !0,
		get: function() {
			return l.canSetCaret;
		}
	});
	var s = $$1;
	Object.defineProperty(e, "containsOnlyInlineElements", {
		enumerable: !0,
		get: function() {
			return s.containsOnlyInlineElements;
		}
	});
	var o = ze$1;
	Object.defineProperty(e, "fragmentToString", {
		enumerable: !0,
		get: function() {
			return o.fragmentToString;
		}
	});
	var d = Xe$1;
	Object.defineProperty(e, "getContentLength", {
		enumerable: !0,
		get: function() {
			return d.getContentLength;
		}
	});
	var u = ce$1;
	Object.defineProperty(e, "getDeepestBlockElements", {
		enumerable: !0,
		get: function() {
			return u.getDeepestBlockElements;
		}
	});
	var p = Ve$1;
	Object.defineProperty(e, "getDeepestNode", {
		enumerable: !0,
		get: function() {
			return p.getDeepestNode;
		}
	});
	var g = Je$1;
	Object.defineProperty(e, "findAllInputs", {
		enumerable: !0,
		get: function() {
			return g.findAllInputs;
		}
	});
	var w = Qe$1;
	Object.defineProperty(e, "isCollapsedWhitespaces", {
		enumerable: !0,
		get: function() {
			return w.isCollapsedWhitespaces;
		}
	});
	var _ = ne$1;
	Object.defineProperty(e, "isContentEditable", {
		enumerable: !0,
		get: function() {
			return _.isContentEditable;
		}
	});
	var ut = ve$1;
	Object.defineProperty(e, "isElement", {
		enumerable: !0,
		get: function() {
			return ut.isElement;
		}
	});
	var ct = Ze$1;
	Object.defineProperty(e, "isEmpty", {
		enumerable: !0,
		get: function() {
			return ct.isEmpty;
		}
	});
	var dt = xe$1;
	Object.defineProperty(e, "isFragment", {
		enumerable: !0,
		get: function() {
			return dt.isFragment;
		}
	});
	var ft = et$1;
	Object.defineProperty(e, "isHTMLString", {
		enumerable: !0,
		get: function() {
			return ft.isHTMLString;
		}
	});
	var pt = Ce;
	Object.defineProperty(e, "isLeaf", {
		enumerable: !0,
		get: function() {
			return pt.isLeaf;
		}
	});
	var ht = Oe;
	Object.defineProperty(e, "isNodeEmpty", {
		enumerable: !0,
		get: function() {
			return ht.isNodeEmpty;
		}
	});
	var mt = W$1;
	Object.defineProperty(e, "isLineBreakTag", {
		enumerable: !0,
		get: function() {
			return mt.isLineBreakTag;
		}
	});
	var gt = D$1;
	Object.defineProperty(e, "isSingleTag", {
		enumerable: !0,
		get: function() {
			return gt.isSingleTag;
		}
	});
	var vt = B$1;
	Object.defineProperty(e, "make", {
		enumerable: !0,
		get: function() {
			return vt.make;
		}
	});
	var bt = tt$1;
	Object.defineProperty(e, "offset", {
		enumerable: !0,
		get: function() {
			return bt.offset;
		}
	});
	var yt = nt$1;
	Object.defineProperty(e, "prepend", {
		enumerable: !0,
		get: function() {
			return yt.prepend;
		}
	});
})(c$1);
var m$1 = "cdx-list";
var h$1 = {
	wrapper: m$1,
	item: `${m$1}__item`,
	itemContent: `${m$1}__item-content`,
	itemChildren: `${m$1}__item-children`
};
var v$1 = class v$1 {
	/**
	* Getter for all CSS classes used in unordered list rendering
	*/
	static get CSS() {
		return {
			...h$1,
			orderedList: `${m$1}-ordered`
		};
	}
	/**
	* Assign passed readonly mode and config to relevant class properties
	* @param readonly - read-only mode flag
	* @param config - user config for Tool
	*/
	constructor(t, n) {
		this.config = n, this.readOnly = t;
	}
	/**
	* Renders ol wrapper for list
	* @param isRoot - boolean variable that represents level of the wrappre (root or childList)
	* @returns - created html ol element
	*/
	renderWrapper(t) {
		let n;
		return t === !0 ? n = c$1.make("ol", [v$1.CSS.wrapper, v$1.CSS.orderedList]) : n = c$1.make("ol", [v$1.CSS.orderedList, v$1.CSS.itemChildren]), n;
	}
	/**
	* Redners list item element
	* @param content - content used in list item rendering
	* @param _meta - meta of the list item unused in rendering of the ordered list
	* @returns - created html list item element
	*/
	renderItem(t, n) {
		const r = c$1.make("li", v$1.CSS.item), i = c$1.make("div", v$1.CSS.itemContent, {
			innerHTML: t,
			contentEditable: (!this.readOnly).toString()
		});
		return r.appendChild(i), r;
	}
	/**
	* Return the item content
	* @param item - item wrapper (<li>)
	* @returns - item content string
	*/
	getItemContent(t) {
		const n = t.querySelector(`.${v$1.CSS.itemContent}`);
		return !n || c$1.isEmpty(n) ? "" : n.innerHTML;
	}
	/**
	* Returns item meta, for ordered list
	* @returns item meta object
	*/
	getItemMeta() {
		return {};
	}
	/**
	* Returns default item meta used on creation of the new item
	*/
	composeDefaultMeta() {
		return {};
	}
};
var b$1 = class b$1 {
	/**
	* Getter for all CSS classes used in unordered list rendering
	*/
	static get CSS() {
		return {
			...h$1,
			unorderedList: `${m$1}-unordered`
		};
	}
	/**
	* Assign passed readonly mode and config to relevant class properties
	* @param readonly - read-only mode flag
	* @param config - user config for Tool
	*/
	constructor(t, n) {
		this.config = n, this.readOnly = t;
	}
	/**
	* Renders ol wrapper for list
	* @param isRoot - boolean variable that represents level of the wrappre (root or childList)
	* @returns - created html ul element
	*/
	renderWrapper(t) {
		let n;
		return t === !0 ? n = c$1.make("ul", [b$1.CSS.wrapper, b$1.CSS.unorderedList]) : n = c$1.make("ul", [b$1.CSS.unorderedList, b$1.CSS.itemChildren]), n;
	}
	/**
	* Redners list item element
	* @param content - content used in list item rendering
	* @param _meta - meta of the list item unused in rendering of the unordered list
	* @returns - created html list item element
	*/
	renderItem(t, n) {
		const r = c$1.make("li", b$1.CSS.item), i = c$1.make("div", b$1.CSS.itemContent, {
			innerHTML: t,
			contentEditable: (!this.readOnly).toString()
		});
		return r.appendChild(i), r;
	}
	/**
	* Return the item content
	* @param item - item wrapper (<li>)
	* @returns - item content string
	*/
	getItemContent(t) {
		const n = t.querySelector(`.${b$1.CSS.itemContent}`);
		return !n || c$1.isEmpty(n) ? "" : n.innerHTML;
	}
	/**
	* Returns item meta, for unordered list
	* @returns Item meta object
	*/
	getItemMeta() {
		return {};
	}
	/**
	* Returns default item meta used on creation of the new item
	*/
	composeDefaultMeta() {
		return {};
	}
};
function O$1(e) {
	return e.nodeType === Node.ELEMENT_NODE;
}
var j$1 = {};
var Pe = {};
var H$1 = {};
var F$1 = {};
Object.defineProperty(F$1, "__esModule", { value: !0 });
F$1.getContenteditableSlice = Un;
var qn = c$1;
function Un(e, t, n, r, i) {
	var a;
	i === void 0 && (i = !1);
	var l = globalThis.document.createRange();
	if (r === "left" ? (l.setStart(e, 0), l.setEnd(t, n)) : (l.setStart(t, n), l.setEnd(e, e.childNodes.length)), i === !0) {
		var s = l.extractContents();
		return (0, qn.fragmentToString)(s);
	}
	var o = l.cloneContents(), d = globalThis.document.createElement("div");
	d.appendChild(o);
	return (a = d.textContent) !== null && a !== void 0 ? a : "";
}
Object.defineProperty(H$1, "__esModule", { value: !0 });
H$1.checkContenteditableSliceForEmptiness = Xn;
var Kn = c$1;
var zn = F$1;
function Xn(e, t, n, r) {
	var i = (0, zn.getContenteditableSlice)(e, t, n, r);
	return (0, Kn.isCollapsedWhitespaces)(i);
}
(function(e) {
	Object.defineProperty(e, "__esModule", { value: !0 }), e.checkContenteditableSliceForEmptiness = void 0;
	var t = H$1;
	Object.defineProperty(e, "checkContenteditableSliceForEmptiness", {
		enumerable: !0,
		get: function() {
			return t.checkContenteditableSliceForEmptiness;
		}
	});
})(Pe);
var rt$1 = {};
(function(e) {
	Object.defineProperty(e, "__esModule", { value: !0 }), e.getContenteditableSlice = void 0;
	var t = F$1;
	Object.defineProperty(e, "getContenteditableSlice", {
		enumerable: !0,
		get: function() {
			return t.getContenteditableSlice;
		}
	});
})(rt$1);
var it$1 = {};
var je = {};
Object.defineProperty(je, "__esModule", { value: !0 });
je.focus = Vn;
var Gn = c$1;
function Vn(e, t) {
	var n, r;
	if (t === void 0 && (t = !0), (0, Gn.isNativeInput)(e)) {
		e.focus();
		var i = t ? 0 : e.value.length;
		e.setSelectionRange(i, i);
	} else {
		var a = globalThis.document.createRange(), l = globalThis.window.getSelection();
		if (!l) return;
		var s = function(g, w) {
			w === void 0 && (w = !1);
			var _ = globalThis.document.createTextNode("");
			w ? g.insertBefore(_, g.firstChild) : g.appendChild(_), a.setStart(_, 0), a.setEnd(_, 0);
		}, o = function(g) {
			return g != null;
		}, d = e.childNodes, u = t ? d[0] : d[d.length - 1];
		if (o(u)) {
			for (; o(u) && u.nodeType !== Node.TEXT_NODE;) u = t ? u.firstChild : u.lastChild;
			if (o(u) && u.nodeType === Node.TEXT_NODE) {
				var p = (r = (n = u.textContent) === null || n === void 0 ? void 0 : n.length) !== null && r !== void 0 ? r : 0, i = t ? 0 : p;
				a.setStart(u, i), a.setEnd(u, i);
			} else s(e, t);
		} else s(e);
		l.removeAllRanges(), l.addRange(a);
	}
}
(function(e) {
	Object.defineProperty(e, "__esModule", { value: !0 }), e.focus = void 0;
	var t = je;
	Object.defineProperty(e, "focus", {
		enumerable: !0,
		get: function() {
			return t.focus;
		}
	});
})(it$1);
var Te = {};
var R$1 = {};
Object.defineProperty(R$1, "__esModule", { value: !0 });
R$1.getCaretNodeAndOffset = Yn;
function Yn() {
	var e = globalThis.window.getSelection();
	if (e === null) return [null, 0];
	var t = e.focusNode, n = e.focusOffset;
	return t === null ? [null, 0] : (t.nodeType !== Node.TEXT_NODE && t.childNodes.length > 0 && (t.childNodes[n] !== void 0 ? (t = t.childNodes[n], n = 0) : (t = t.childNodes[n - 1], t.textContent !== null && (n = t.textContent.length))), [t, n]);
}
(function(e) {
	Object.defineProperty(e, "__esModule", { value: !0 }), e.getCaretNodeAndOffset = void 0;
	var t = R$1;
	Object.defineProperty(e, "getCaretNodeAndOffset", {
		enumerable: !0,
		get: function() {
			return t.getCaretNodeAndOffset;
		}
	});
})(Te);
var at$1 = {};
var q$1 = {};
Object.defineProperty(q$1, "__esModule", { value: !0 });
q$1.getRange = Jn;
function Jn() {
	var e = globalThis.window.getSelection();
	return e && e.rangeCount ? e.getRangeAt(0) : null;
}
(function(e) {
	Object.defineProperty(e, "__esModule", { value: !0 }), e.getRange = void 0;
	var t = q$1;
	Object.defineProperty(e, "getRange", {
		enumerable: !0,
		get: function() {
			return t.getRange;
		}
	});
})(at$1);
var lt$1 = {};
var Le = {};
Object.defineProperty(Le, "__esModule", { value: !0 });
Le.isCaretAtEndOfInput = xn;
var De$1 = c$1;
var Qn = Te;
var Zn = Pe;
function xn(e) {
	var t = (0, De$1.getDeepestNode)(e, !0);
	if (t === null) return !0;
	if ((0, De$1.isNativeInput)(t)) return t.selectionEnd === t.value.length;
	var n = (0, Qn.getCaretNodeAndOffset)(), r = n[0], i = n[1];
	return r === null ? !1 : (0, Zn.checkContenteditableSliceForEmptiness)(e, r, i, "right");
}
(function(e) {
	Object.defineProperty(e, "__esModule", { value: !0 }), e.isCaretAtEndOfInput = void 0;
	var t = Le;
	Object.defineProperty(e, "isCaretAtEndOfInput", {
		enumerable: !0,
		get: function() {
			return t.isCaretAtEndOfInput;
		}
	});
})(lt$1);
var st$1 = {};
var Me = {};
Object.defineProperty(Me, "__esModule", { value: !0 });
Me.isCaretAtStartOfInput = nr;
var L$1 = c$1;
var er = R$1;
var tr = H$1;
function nr(e) {
	var t = (0, L$1.getDeepestNode)(e);
	if (t === null || (0, L$1.isEmpty)(e)) return !0;
	if ((0, L$1.isNativeInput)(t)) return t.selectionEnd === 0;
	if ((0, L$1.isEmpty)(e)) return !0;
	var n = (0, er.getCaretNodeAndOffset)(), r = n[0], i = n[1];
	return r === null ? !1 : (0, tr.checkContenteditableSliceForEmptiness)(e, r, i, "left");
}
(function(e) {
	Object.defineProperty(e, "__esModule", { value: !0 }), e.isCaretAtStartOfInput = void 0;
	var t = Me;
	Object.defineProperty(e, "isCaretAtStartOfInput", {
		enumerable: !0,
		get: function() {
			return t.isCaretAtStartOfInput;
		}
	});
})(st$1);
var ot$1 = {};
var Ne = {};
Object.defineProperty(Ne, "__esModule", { value: !0 });
Ne.save = ar;
var rr = c$1;
var ir = q$1;
function ar() {
	var e = (0, ir.getRange)(), t = (0, rr.make)("span");
	if (t.id = "cursor", t.hidden = !0, !!e) return e.insertNode(t), function() {
		var r = globalThis.window.getSelection();
		r && (e.setStartAfter(t), e.setEndAfter(t), r.removeAllRanges(), r.addRange(e), setTimeout(function() {
			t.remove();
		}, 150));
	};
}
(function(e) {
	Object.defineProperty(e, "__esModule", { value: !0 }), e.save = void 0;
	var t = Ne;
	Object.defineProperty(e, "save", {
		enumerable: !0,
		get: function() {
			return t.save;
		}
	});
})(ot$1);
(function(e) {
	Object.defineProperty(e, "__esModule", { value: !0 }), e.save = e.isCaretAtStartOfInput = e.isCaretAtEndOfInput = e.getRange = e.getCaretNodeAndOffset = e.focus = e.getContenteditableSlice = e.checkContenteditableSliceForEmptiness = void 0;
	var t = Pe;
	Object.defineProperty(e, "checkContenteditableSliceForEmptiness", {
		enumerable: !0,
		get: function() {
			return t.checkContenteditableSliceForEmptiness;
		}
	});
	var n = rt$1;
	Object.defineProperty(e, "getContenteditableSlice", {
		enumerable: !0,
		get: function() {
			return n.getContenteditableSlice;
		}
	});
	var r = it$1;
	Object.defineProperty(e, "focus", {
		enumerable: !0,
		get: function() {
			return r.focus;
		}
	});
	var i = Te;
	Object.defineProperty(e, "getCaretNodeAndOffset", {
		enumerable: !0,
		get: function() {
			return i.getCaretNodeAndOffset;
		}
	});
	var a = at$1;
	Object.defineProperty(e, "getRange", {
		enumerable: !0,
		get: function() {
			return a.getRange;
		}
	});
	var l = lt$1;
	Object.defineProperty(e, "isCaretAtEndOfInput", {
		enumerable: !0,
		get: function() {
			return l.isCaretAtEndOfInput;
		}
	});
	var s = st$1;
	Object.defineProperty(e, "isCaretAtStartOfInput", {
		enumerable: !0,
		get: function() {
			return s.isCaretAtStartOfInput;
		}
	});
	var o = ot$1;
	Object.defineProperty(e, "save", {
		enumerable: !0,
		get: function() {
			return o.save;
		}
	});
})(j$1);
var f = class f {
	/**
	* Getter for all CSS classes used in unordered list rendering
	*/
	static get CSS() {
		return {
			...h$1,
			checklist: `${m$1}-checklist`,
			itemChecked: `${m$1}__checkbox--checked`,
			noHover: `${m$1}__checkbox--no-hover`,
			checkbox: `${m$1}__checkbox-check`,
			checkboxContainer: `${m$1}__checkbox`,
			checkboxCheckDisabled: `${m$1}__checkbox-check--disabled`
		};
	}
	/**
	* Assign passed readonly mode and config to relevant class properties
	* @param readonly - read-only mode flag
	* @param config - user config for Tool
	*/
	constructor(t, n) {
		this.config = n, this.readOnly = t;
	}
	/**
	* Renders ul wrapper for list
	* @param isRoot - boolean variable that represents level of the wrappre (root or childList)
	* @returns - created html ul element
	*/
	renderWrapper(t) {
		let n;
		return t === !0 ? (n = c$1.make("ul", [f.CSS.wrapper, f.CSS.checklist]), n.addEventListener("click", (r) => {
			const i = r.target;
			if (i) {
				const a = i.closest(`.${f.CSS.checkboxContainer}`);
				a && a.contains(i) && this.toggleCheckbox(a);
			}
		})) : n = c$1.make("ul", [f.CSS.checklist, f.CSS.itemChildren]), n;
	}
	/**
	* Redners list item element
	* @param content - content used in list item rendering
	* @param meta - meta of the list item used in rendering of the checklist
	* @returns - created html list item element
	*/
	renderItem(t, n) {
		const r = c$1.make("li", [f.CSS.item, f.CSS.item]), i = c$1.make("div", f.CSS.itemContent, {
			innerHTML: t,
			contentEditable: (!this.readOnly).toString()
		}), a = c$1.make("span", f.CSS.checkbox), l = c$1.make("div", f.CSS.checkboxContainer);
		return n.checked === !0 && l.classList.add(f.CSS.itemChecked), this.readOnly && l.classList.add(f.CSS.checkboxCheckDisabled), a.innerHTML = Ct$1, l.appendChild(a), r.appendChild(l), r.appendChild(i), r;
	}
	/**
	* Return the item content
	* @param item - item wrapper (<li>)
	* @returns - item content string
	*/
	getItemContent(t) {
		const n = t.querySelector(`.${f.CSS.itemContent}`);
		return !n || c$1.isEmpty(n) ? "" : n.innerHTML;
	}
	/**
	* Return meta object of certain element
	* @param item - will be returned meta information of this item
	* @returns Item meta object
	*/
	getItemMeta(t) {
		const n = t.querySelector(`.${f.CSS.checkboxContainer}`);
		return { checked: n ? n.classList.contains(f.CSS.itemChecked) : !1 };
	}
	/**
	* Returns default item meta used on creation of the new item
	*/
	composeDefaultMeta() {
		return { checked: !1 };
	}
	/**
	* Toggle checklist item state
	* @param checkbox - checkbox element to be toggled
	*/
	toggleCheckbox(t) {
		t.classList.toggle(f.CSS.itemChecked), t.classList.add(f.CSS.noHover), t.addEventListener("mouseleave", () => this.removeSpecialHoverBehavior(t), { once: !0 });
	}
	/**
	* Removes class responsible for special hover behavior on an item
	* @param el - item wrapper
	*/
	removeSpecialHoverBehavior(t) {
		t.classList.remove(f.CSS.noHover);
	}
};
function U$1(e, t = "after") {
	const n = [];
	let r;
	function i(a) {
		switch (t) {
			case "after": return a.nextElementSibling;
			case "before": return a.previousElementSibling;
		}
	}
	for (r = i(e); r !== null;) n.push(r), r = i(r);
	return n.length !== 0 ? n : null;
}
function y$1(e, t = !0) {
	let n = e;
	return e.classList.contains(h$1.item) && (n = e.querySelector(`.${h$1.itemChildren}`)), n === null ? [] : t ? Array.from(n.querySelectorAll(`:scope > .${h$1.item}`)) : Array.from(n.querySelectorAll(`.${h$1.item}`));
}
function lr(e) {
	return e.nextElementSibling === null;
}
function sr(e) {
	return e.querySelector(`.${h$1.itemChildren}`) !== null;
}
function C$1(e) {
	return e.querySelector(`.${h$1.itemChildren}`);
}
function K$1(e) {
	let t = e;
	e.classList.contains(h$1.item) && (t = C$1(e)), t !== null && y$1(t).length === 0 && t.remove();
}
function N$1(e) {
	return e.querySelector(`.${h$1.itemContent}`);
}
function E$1(e, t = !0) {
	const n = N$1(e);
	n && j$1.focus(n, t);
}
var z$1 = class {
	/**
	* Getter method to get current item
	* @returns current list item or null if caret position is not undefined
	*/
	get currentItem() {
		const t = globalThis.window.getSelection();
		if (!t) return null;
		let n = t.anchorNode;
		return !n || (O$1(n) || (n = n.parentNode), !n) || !O$1(n) ? null : n.closest(`.${h$1.item}`);
	}
	/**
	* Method that returns nesting level of the current item, null if there is no selection
	*/
	get currentItemLevel() {
		const t = this.currentItem;
		if (t === null) return null;
		let n = t.parentNode, r = 0;
		for (; n !== null && n !== this.listWrapper;) O$1(n) && n.classList.contains(h$1.item) && (r += 1), n = n.parentNode;
		return r + 1;
	}
	/**
	* Assign all passed params and renderer to relevant class properties
	* @param params - tool constructor options
	* @param params.data - previously saved data
	* @param params.config - user config for Tool
	* @param params.api - Editor.js API
	* @param params.readOnly - read-only mode flag
	* @param renderer - renderer instance initialized in tool class
	*/
	constructor({ data: t, config: n, api: r, readOnly: i, block: a }, l) {
		this.config = n, this.data = t, this.readOnly = i, this.api = r, this.block = a, this.renderer = l;
	}
	/**
	* Function that is responsible for rendering list with contents
	* @returns Filled with content wrapper element of the list
	*/
	render() {
		return this.listWrapper = this.renderer.renderWrapper(!0), this.data.items.length ? this.appendItems(this.data.items, this.listWrapper) : this.appendItems([{
			content: "",
			meta: {},
			items: []
		}], this.listWrapper), this.readOnly || this.listWrapper.addEventListener("keydown", (t) => {
			switch (t.key) {
				case "Enter":
					t.shiftKey || this.enterPressed(t);
					break;
				case "Backspace":
					this.backspace(t);
					break;
				case "Tab": t.shiftKey ? this.shiftTab(t) : this.addTab(t);
			}
		}, !1), "start" in this.data.meta && this.data.meta.start !== void 0 && this.changeStartWith(this.data.meta.start), "counterType" in this.data.meta && this.data.meta.counterType !== void 0 && this.changeCounters(this.data.meta.counterType), this.listWrapper;
	}
	/**
	* Function that is responsible for list content saving
	* @param wrapper - optional argument wrapper
	* @returns whole list saved data if wrapper not passes, otherwise will return data of the passed wrapper
	*/
	save(t) {
		const n = t ?? this.listWrapper, r = (l) => y$1(l).map((o) => {
			const d = C$1(o);
			return {
				content: this.renderer.getItemContent(o),
				meta: this.renderer.getItemMeta(o),
				items: d ? r(d) : []
			};
		}), i = n ? r(n) : [];
		let a = {
			style: this.data.style,
			meta: {},
			items: i
		};
		return this.data.style === "ordered" && (a.meta = {
			start: this.data.meta.start,
			counterType: this.data.meta.counterType
		}), a;
	}
	/**
	* On paste sanitzation config. Allow only tags that are allowed in the Tool.
	* @returns - config that determines tags supposted by paste handler
	* @todo - refactor and move to list instance
	*/
	static get pasteConfig() {
		return { tags: [
			"OL",
			"UL",
			"LI"
		] };
	}
	/**
	* Method that specified hot to merge two List blocks.
	* Called by Editor.js by backspace at the beginning of the Block
	*
	* Content of the first item of the next List would be merged with deepest item in current list
	* Other items of the next List would be appended to the current list without any changes in nesting levels
	* @param data - data of the second list to be merged with current
	*/
	merge(t) {
		const n = this.block.holder.querySelectorAll(`.${h$1.item}`), r = n[n.length - 1], i = N$1(r);
		if (r === null || i === null || (i.insertAdjacentHTML("beforeend", t.items[0].content), this.listWrapper === void 0)) return;
		const a = y$1(this.listWrapper);
		if (a.length === 0) return;
		const l = a[a.length - 1];
		let s = C$1(l);
		const o = t.items.shift();
		o !== void 0 && (o.items.length !== 0 && (s === null && (s = this.renderer.renderWrapper(!1)), this.appendItems(o.items, s)), t.items.length > 0 && this.appendItems(t.items, this.listWrapper));
	}
	/**
	* On paste callback that is fired from Editor.
	* @param event - event with pasted data
	* @todo - refactor and move to list instance
	*/
	onPaste(t) {
		const n = t.detail.data;
		this.data = this.pasteHandler(n);
		const r = this.listWrapper;
		r && r.parentNode && r.parentNode.replaceChild(this.render(), r);
	}
	/**
	* Handle UL, OL and LI tags paste and returns List data
	* @param element - html element that contains whole list
	* @todo - refactor and move to list instance
	*/
	pasteHandler(t) {
		const { tagName: n } = t;
		let r = "unordered", i;
		switch (n) {
			case "OL":
				r = "ordered", i = "ol";
				break;
			case "UL":
			case "LI": r = "unordered", i = "ul";
		}
		const a = {
			style: r,
			meta: {},
			items: []
		};
		r === "ordered" && (this.data.meta.counterType = "numeric", this.data.meta.start = 1);
		const l = (s) => Array.from(s.querySelectorAll(":scope > li")).map((d) => {
			const u = d.querySelector(`:scope > ${i}`), p = u ? l(u) : [];
			return {
				content: d.innerHTML ?? "",
				meta: {},
				items: p
			};
		});
		return a.items = l(t), a;
	}
	/**
	* Changes ordered list start property value
	* @param index - new value of the start property
	*/
	changeStartWith(t) {
		this.listWrapper.style.setProperty("counter-reset", `item ${t - 1}`), this.data.meta.start = t;
	}
	/**
	* Changes ordered list counterType property value
	* @param counterType - new value of the counterType value
	*/
	changeCounters(t) {
		this.listWrapper.style.setProperty("--list-counter-type", t), this.data.meta.counterType = t;
	}
	/**
	* Handles Enter keypress
	* @param event - keydown
	*/
	enterPressed(t) {
		var s;
		const n = this.currentItem;
		if (t.stopPropagation(), t.preventDefault(), t.isComposing || n === null) return;
		const r = ((s = this.renderer) == null ? void 0 : s.getItemContent(n).trim().length) === 0, i = n.parentNode === this.listWrapper, a = n.previousElementSibling === null, l = this.api.blocks.getCurrentBlockIndex();
		if (i && r) if (lr(n) && !sr(n)) {
			a ? this.convertItemToDefaultBlock(l, !0) : this.convertItemToDefaultBlock();
			return;
		} else {
			this.splitList(n);
			return;
		}
		else if (r) {
			this.unshiftItem(n);
			return;
		} else this.splitItem(n);
	}
	/**
	* Handle backspace
	* @param event - keydown
	*/
	backspace(t) {
		var r;
		const n = this.currentItem;
		if (n !== null && j$1.isCaretAtStartOfInput(n) && ((r = globalThis.window.getSelection()) == null ? void 0 : r.isCollapsed) !== !1) {
			if (t.stopPropagation(), n.parentNode === this.listWrapper && n.previousElementSibling === null) {
				this.convertFirstItemToDefaultBlock();
				return;
			}
			t.preventDefault(), this.mergeItemWithPrevious(n);
		}
	}
	/**
	* Reduce indentation for current item
	* @param event - keydown
	*/
	shiftTab(t) {
		t.stopPropagation(), t.preventDefault(), this.currentItem !== null && this.unshiftItem(this.currentItem);
	}
	/**
	* Decrease indentation of the passed item
	* @param item - list item to be unshifted
	*/
	unshiftItem(t) {
		if (!t.parentNode || !O$1(t.parentNode)) return;
		const n = t.parentNode.closest(`.${h$1.item}`);
		if (!n) return;
		let r = C$1(t);
		if (t.parentElement === null) return;
		const i = U$1(t);
		i !== null && (r === null && (r = this.renderer.renderWrapper(!1)), i.forEach((a) => {
			r.appendChild(a);
		}), t.appendChild(r)), n.after(t), E$1(t, !1), K$1(n);
	}
	/**
	* Method that is used for list splitting and moving trailing items to the new separated list
	* @param item - current item html element
	*/
	splitList(t) {
		const n = y$1(t), r = this.block, i = this.api.blocks.getCurrentBlockIndex();
		if (n.length !== 0) {
			const o = n[0];
			this.unshiftItem(o), E$1(t, !1);
		}
		if (t.previousElementSibling === null && t.parentNode === this.listWrapper) {
			this.convertItemToDefaultBlock(i);
			return;
		}
		const a = U$1(t);
		if (a === null) return;
		const l = this.renderer.renderWrapper(!0);
		a.forEach((o) => {
			l.appendChild(o);
		});
		const s = this.save(l);
		s.meta.start = this.data.style == "ordered" ? 1 : void 0, this.api.blocks.insert(r == null ? void 0 : r.name, s, this.config, i + 1), this.convertItemToDefaultBlock(i + 1), l.remove();
	}
	/**
	* Method that is used for splitting item content and moving trailing content to the new sibling item
	* @param currentItem - current item html element
	*/
	splitItem(t) {
		const [n, r] = j$1.getCaretNodeAndOffset();
		if (n === null) return;
		const i = N$1(t);
		let a;
		i === null ? a = "" : a = j$1.getContenteditableSlice(i, n, r, "right", !0);
		const l = C$1(t), s = this.renderItem(a);
		t?.after(s), l && s.appendChild(l), E$1(s);
	}
	/**
	* Method that is used for merging current item with previous one
	* Content of the current item would be appended to the previous item
	* Current item children would not change nesting level
	* @param item - current item html element
	*/
	mergeItemWithPrevious(t) {
		const n = t.previousElementSibling, r = t.parentNode;
		if (r === null || !O$1(r)) return;
		const i = r.closest(`.${h$1.item}`);
		if (!n && !i || n && !O$1(n)) return;
		let a;
		if (n) {
			const p = y$1(n, !1);
			p.length !== 0 && p.length !== 0 ? a = p[p.length - 1] : a = n;
		} else a = i;
		const l = this.renderer.getItemContent(t);
		if (!a) return;
		E$1(a, !1);
		const s = N$1(a);
		if (s === null) return;
		s.insertAdjacentHTML("beforeend", l);
		const o = y$1(t);
		if (o.length === 0) {
			t.remove(), K$1(a);
			return;
		}
		const d = n || i, u = C$1(d) ?? this.renderer.renderWrapper(!1);
		n ? o.forEach((p) => {
			u.appendChild(p);
		}) : o.forEach((p) => {
			u.prepend(p);
		}), C$1(d) === null && a.appendChild(u), t.remove();
	}
	/**
	* Add indentation to current item
	* @param event - keydown
	*/
	addTab(t) {
		var a;
		t.stopPropagation(), t.preventDefault();
		const n = this.currentItem;
		if (!n) return;
		if (((a = this.config) == null ? void 0 : a.maxLevel) !== void 0) {
			const l = this.currentItemLevel;
			if (l !== null && l === this.config.maxLevel) return;
		}
		const r = n.previousSibling;
		if (r === null || !O$1(r)) return;
		const i = C$1(r);
		if (i) i.appendChild(n), y$1(n).forEach((s) => {
			i.appendChild(s);
		});
		else {
			const l = this.renderer.renderWrapper(!1);
			l.appendChild(n), y$1(n).forEach((o) => {
				l.appendChild(o);
			}), r.appendChild(l);
		}
		K$1(n), E$1(n, !1);
	}
	/**
	* Convert current item to default block with passed index
	* @param newBloxkIndex - optional parameter represents index, where would be inseted default block
	* @param removeList - optional parameter, that represents condition, if List should be removed
	*/
	convertItemToDefaultBlock(t, n) {
		let r;
		const i = this.currentItem, a = i !== null ? this.renderer.getItemContent(i) : "";
		n === !0 && this.api.blocks.delete(), t !== void 0 ? r = this.api.blocks.insert(void 0, { text: a }, void 0, t) : r = this.api.blocks.insert(), i?.remove(), this.api.caret.setToBlock(r, "start");
	}
	/**
	* Convert first item of the list to default block
	* This method could be called when backspace button pressed at start of the first item of the list
	* First item of the list would be converted to the paragraph and first item children would be unshifted
	*/
	convertFirstItemToDefaultBlock() {
		const t = this.currentItem;
		if (t === null) return;
		const n = y$1(t);
		if (n.length !== 0) {
			const l = n[0];
			this.unshiftItem(l), E$1(t);
		}
		const r = U$1(t), i = this.api.blocks.getCurrentBlockIndex(), a = r === null;
		this.convertItemToDefaultBlock(i, a);
	}
	/**
	* Method that calls render function of the renderer with a necessary item meta cast
	* @param itemContent - content to be rendered in new item
	* @param meta - meta used in list item rendering
	* @returns html element of the rendered item
	*/
	renderItem(t, n) {
		const r = n ?? this.renderer.composeDefaultMeta();
		switch (!0) {
			case this.renderer instanceof v$1: return this.renderer.renderItem(t, r);
			case this.renderer instanceof b$1: return this.renderer.renderItem(t, r);
			default: return this.renderer.renderItem(t, r);
		}
	}
	/**
	* Renders children list
	* @param items - list data used in item rendering
	* @param parentElement - where to append passed items
	*/
	appendItems(t, n) {
		t.forEach((r) => {
			var a;
			const i = this.renderItem(r.content, r.meta);
			if (n.appendChild(i), r.items.length) {
				const l = (a = this.renderer) == null ? void 0 : a.renderWrapper(!1);
				this.appendItems(r.items, l), i.appendChild(l);
			}
		});
	}
};
var I$1 = {
	wrapper: `${m$1}-start-with-field`,
	input: `${m$1}-start-with-field__input`,
	startWithElementWrapperInvalid: `${m$1}-start-with-field--invalid`
};
function or(e, { value: t, placeholder: n, attributes: r, sanitize: i }) {
	const a = c$1.make("div", I$1.wrapper), l = c$1.make("input", I$1.input, {
		placeholder: n,
		/**
		* Used to prevent focusing on the input by Tab key
		* (Popover in the Toolbar lays below the blocks,
		* so Tab in the last block will focus this hidden input if this property is not set)
		*/
		tabIndex: -1,
		/**
		* Value of the start property, if it is not specified, then it is set to one
		*/
		value: t
	});
	for (const s in r) l.setAttribute(s, r[s]);
	return a.appendChild(l), l.addEventListener("input", () => {
		i !== void 0 && (l.value = i(l.value));
		const s = l.checkValidity();
		!s && !a.classList.contains(I$1.startWithElementWrapperInvalid) && a.classList.add(I$1.startWithElementWrapperInvalid), s && a.classList.contains(I$1.startWithElementWrapperInvalid) && a.classList.remove(I$1.startWithElementWrapperInvalid), s && e(l.value);
	}), a;
}
var P$1 = /* @__PURE__ */ new Map([
	["Numeric", "numeric"],
	["Lower Roman", "lower-roman"],
	["Upper Roman", "upper-roman"],
	["Lower Alpha", "lower-alpha"],
	["Upper Alpha", "upper-alpha"]
]);
var He$1 = /* @__PURE__ */ new Map([
	["numeric", St$1],
	["lower-roman", Ot$1],
	["upper-roman", kt$1],
	["lower-alpha", Et$1],
	["upper-alpha", _t$1]
]);
function ur(e) {
	return e.replace(/\D+/g, "");
}
function cr(e) {
	return typeof e.items[0] == "string";
}
function dr(e) {
	return !("meta" in e);
}
function fr(e) {
	return typeof e.items[0] != "string" && "text" in e.items[0] && "checked" in e.items[0] && typeof e.items[0].text == "string" && typeof e.items[0].checked == "boolean";
}
function pr(e) {
	const t = [];
	return cr(e) ? (e.items.forEach((n) => {
		t.push({
			content: n,
			meta: {},
			items: []
		});
	}), {
		style: e.style,
		meta: {},
		items: t
	}) : fr(e) ? (e.items.forEach((n) => {
		t.push({
			content: n.text,
			meta: { checked: n.checked },
			items: []
		});
	}), {
		style: "checklist",
		meta: {},
		items: t
	}) : dr(e) ? {
		style: e.style,
		meta: {},
		items: e.items
	} : structuredClone(e);
}
var G$1 = class G$1 {
	/**
	* Notify core that read-only mode is supported
	*/
	static get isReadOnlySupported() {
		return !0;
	}
	/**
	* Allow to use native Enter behaviour
	*/
	static get enableLineBreaks() {
		return !0;
	}
	/**
	* Get Tool toolbox settings
	* icon - Tool icon's SVG
	* title - title to show in toolbox
	*/
	static get toolbox() {
		return [
			{
				icon: $e,
				title: "Unordered List",
				data: { style: "unordered" }
			},
			{
				icon: Be,
				title: "Ordered List",
				data: { style: "ordered" }
			},
			{
				icon: Ae,
				title: "Checklist",
				data: { style: "checklist" }
			}
		];
	}
	/**
	* On paste sanitzation config. Allow only tags that are allowed in the Tool.
	* @returns - paste config object used in editor
	*/
	static get pasteConfig() {
		return { tags: [
			"OL",
			"UL",
			"LI"
		] };
	}
	/**
	* Convert from text to list with import and export list to text
	*/
	static get conversionConfig() {
		return {
			export: (t) => G$1.joinRecursive(t),
			import: (t, n) => ({
				meta: {},
				items: [{
					content: t,
					meta: {},
					items: []
				}],
				style: (n == null ? void 0 : n.defaultStyle) !== void 0 ? n.defaultStyle : "unordered"
			})
		};
	}
	/**
	* Get list style name
	*/
	get listStyle() {
		return this.data.style || this.defaultListStyle;
	}
	/**
	* Set list style
	* @param style - new style to set
	*/
	set listStyle(t) {
		var r;
		this.data.style = t, this.changeTabulatorByStyle();
		const n = this.list.render();
		(r = this.listElement) == null || r.replaceWith(n), this.listElement = n;
	}
	/**
	* Render plugin`s main Element and fill it with saved data
	* @param params - tool constructor options
	* @param params.data - previously saved data
	* @param params.config - user config for Tool
	* @param params.api - Editor.js API
	* @param params.readOnly - read-only mode flag
	*/
	constructor({ data: t, config: n, api: r, readOnly: i, block: a }) {
		var s;
		this.api = r, this.readOnly = i, this.config = n, this.block = a, this.defaultListStyle = ((s = this.config) == null ? void 0 : s.defaultStyle) || "unordered", this.defaultCounterTypes = this.config.counterTypes || Array.from(P$1.values());
		const l = {
			style: this.defaultListStyle,
			meta: {},
			items: []
		};
		this.data = Object.keys(t).length ? pr(t) : l, this.listStyle === "ordered" && this.data.meta.counterType === void 0 && (this.data.meta.counterType = "numeric"), this.changeTabulatorByStyle();
	}
	/**
	* Convert from list to text for conversionConfig
	* @param data - current data of the list
	* @returns - string of the recursively merged contents of the items of the list
	*/
	static joinRecursive(t) {
		return t.items.map((n) => `${n.content} ${G$1.joinRecursive(n)}`).join("");
	}
	/**
	* Function that is responsible for content rendering
	* @returns rendered list wrapper with all contents
	*/
	render() {
		return this.listElement = this.list.render(), this.listElement;
	}
	/**
	* Function that is responsible for content saving
	* @returns formatted content used in editor
	*/
	save() {
		return this.data = this.list.save(), this.data;
	}
	/**
	* Function that is responsible for mergind two lists into one
	* @param data - data of the next standing list, that should be merged with current
	*/
	merge(t) {
		this.list.merge(t);
	}
	/**
	* Creates Block Tune allowing to change the list style
	* @returns array of tune configs
	*/
	renderSettings() {
		const t = [
			{
				label: this.api.i18n.t("Unordered"),
				icon: $e,
				closeOnActivate: !0,
				isActive: this.listStyle == "unordered",
				onActivate: () => {
					this.listStyle = "unordered";
				}
			},
			{
				label: this.api.i18n.t("Ordered"),
				icon: Be,
				closeOnActivate: !0,
				isActive: this.listStyle == "ordered",
				onActivate: () => {
					this.listStyle = "ordered";
				}
			},
			{
				label: this.api.i18n.t("Checklist"),
				icon: Ae,
				closeOnActivate: !0,
				isActive: this.listStyle == "checklist",
				onActivate: () => {
					this.listStyle = "checklist";
				}
			}
		];
		if (this.listStyle === "ordered") {
			const n = or((a) => this.changeStartWith(Number(a)), {
				value: String(this.data.meta.start ?? 1),
				placeholder: "",
				attributes: { required: "true" },
				sanitize: (a) => ur(a)
			}), r = [{
				label: this.api.i18n.t("Start with"),
				icon: It$1,
				children: { items: [{
					element: n,
					type: "html"
				}] }
			}], i = {
				label: this.api.i18n.t("Counter type"),
				icon: He$1.get(this.data.meta.counterType),
				children: { items: [] }
			};
			P$1.forEach((a, l) => {
				const s = P$1.get(l);
				this.defaultCounterTypes.includes(s) && i.children.items.push({
					title: this.api.i18n.t(l),
					icon: He$1.get(s),
					isActive: this.data.meta.counterType === P$1.get(l),
					closeOnActivate: !0,
					onActivate: () => {
						this.changeCounters(P$1.get(l));
					}
				});
			}), i.children.items.length > 1 && r.push(i), t.push({ type: "separator" }, ...r);
		}
		return t;
	}
	/**
	* On paste callback that is fired from Editor.
	* @param event - event with pasted data
	*/
	onPaste(t) {
		const { tagName: n } = t.detail.data;
		switch (n) {
			case "OL":
				this.listStyle = "ordered";
				break;
			case "UL":
			case "LI": this.listStyle = "unordered";
		}
		this.list.onPaste(t);
	}
	/**
	* Handle UL, OL and LI tags paste and returns List data
	* @param element - html element that contains whole list
	*/
	pasteHandler(t) {
		return this.list.pasteHandler(t);
	}
	/**
	* Changes ordered list counterType property value
	* @param counterType - new value of the counterType value
	*/
	changeCounters(t) {
		var n;
		(n = this.list) == null || n.changeCounters(t), this.data.meta.counterType = t;
	}
	/**
	* Changes ordered list start property value
	* @param index - new value of the start property
	*/
	changeStartWith(t) {
		var n;
		(n = this.list) == null || n.changeStartWith(t), this.data.meta.start = t;
	}
	/**
	* This method allows changing tabulator respectfully to passed style
	*/
	changeTabulatorByStyle() {
		switch (this.listStyle) {
			case "ordered":
				this.list = new z$1({
					data: this.data,
					readOnly: this.readOnly,
					api: this.api,
					config: this.config,
					block: this.block
				}, new v$1(this.readOnly, this.config));
				break;
			case "unordered":
				this.list = new z$1({
					data: this.data,
					readOnly: this.readOnly,
					api: this.api,
					config: this.config,
					block: this.block
				}, new b$1(this.readOnly, this.config));
				break;
			case "checklist": this.list = new z$1({
				data: this.data,
				readOnly: this.readOnly,
				api: this.api,
				config: this.config,
				block: this.block
			}, new f(this.readOnly, this.config));
		}
	}
};
//#endregion
//#region src/assets/tools/Raw/icon.svg?raw
var icon_default$1 = "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"16\" height=\"16\" fill=\"currentColor\" class=\"bi bi-code-square\" viewBox=\"0 0 16 16\">\n    <path d=\"M14 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1zM2 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2z\"/>\n    <path d=\"M6.854 4.646a.5.5 0 0 1 0 .708L4.207 8l2.647 2.646a.5.5 0 0 1-.708.708l-3-3a.5.5 0 0 1 0-.708l3-3a.5.5 0 0 1 .708 0m2.292 0a.5.5 0 0 0 0 .708L11.793 8l-2.647 2.646a.5.5 0 0 0 .708.708l3-3a.5.5 0 0 0 0-.708l-3-3a.5.5 0 0 0-.708 0\"/>\n</svg>";
//#endregion
//#region src/assets/tools/utils/logger.ts
var Logger = class {
	constructor() {
		this.level = 0;
		if (typeof globalThis.window !== "undefined") {
			this.isProduction = false;
			this.level = 0;
		} else {
			this.isProduction = true;
			if (this.isProduction) this.level = 3;
			else this.level = 0;
		}
	}
	setLevel(level) {
		this.level = level;
	}
	debug(message, ...args) {
		if (this.level <= 0) console.debug(`[DEBUG] ${message}`, ...args);
	}
	info(message, ...args) {
		if (this.level <= 1) console.info(`[INFO] ${message}`, ...args);
	}
	warn(message, ...args) {
		if (this.level <= 2) console.warn(`[WARN] ${message}`, ...args);
	}
	error(message, ...args) {
		if (this.level <= 3) console.error(`[ERROR] ${message}`, ...args);
	}
	logError(error, context, additionalInfo) {
		this.error(`Error in ${context}: ${error.message}`, {
			stack: error.stack,
			...additionalInfo
		});
	}
};
var logger = new Logger();
//#endregion
//#region src/assets/tools/Abstract/BaseTool.ts
var BaseTool = class {
	constructor({ data, api, readOnly }) {
		this.logger = logger;
		this.data = {};
		this.data = data;
		this.api = api;
		this.readOnly = readOnly;
	}
	handleError(error, context, additionalInfo) {
		this.logger.logError(error, context, additionalInfo);
		this.api.notifier.show({
			message: this.api.i18n.t("An error occurred"),
			style: "error"
		});
	}
	showNotification(message, style = "info") {
		this.api.notifier.show({
			message: this.api.i18n.t(message),
			style
		});
	}
};
//#endregion
//#region src/assets/tools/Raw/Raw.ts
var Raw = class Raw extends BaseTool {
	static {
		this.monacoLoaderPromise = null;
	}
	static {
		this.MONACO_SCRIPT_URL = "/bundles/pushwordadmin/monaco/app.js";
	}
	static {
		this.enableLineBreaks = true;
	}
	static get toolbox() {
		return {
			icon: icon_default$1,
			title: "Raw"
		};
	}
	constructor({ data, api, readOnly }) {
		super({
			data,
			api,
			readOnly
		});
		this._rawData = { html: "" };
		this.initialHtmlValue = "";
		this.api = api;
		const html = data?.html || "";
		this._rawData = { html };
		this.initialHtmlValue = html;
		Object.defineProperty(this, "data", {
			get: () => this._rawData,
			set: (newData) => {
				const htmlValue = newData?.html || "";
				this._rawData = { html: htmlValue };
				if (this.editorInstance && this.editorInstance.getValue() !== htmlValue) this.editorInstance.setValue(htmlValue);
			},
			configurable: true,
			enumerable: true
		});
	}
	instantiateEditor(editorElem) {
		const monaco = globalThis.window.monaco;
		const monacoHelper = globalThis.window.monacoHelper;
		if (!monaco || !monacoHelper) throw new Error("monaco is not defined");
		const htmlValue = this.initialHtmlValue || this.data.html || "";
		return monaco.editor.create(editorElem, {
			value: htmlValue,
			language: "twig",
			...monacoHelper.defaultSettings
		});
	}
	render() {
		this.wrapper = globalThis.document.createElement("div");
		this.wrapper.classList.add("editorjs-monaco-wrapper");
		this.initialHtmlValue = this.data.html || "";
		const editorElem = globalThis.document.createElement("div");
		editorElem.classList.add("editorjs-monaco-editor");
		editorElem.style.height = "100%";
		this.wrapper.appendChild(editorElem);
		this.initializeMonaco(editorElem);
		return this.wrapper;
	}
	initializeMonaco(editorElem) {
		this.ensureMonacoLoaded().then((ready) => {
			if (!ready || !this.wrapper) return;
			try {
				this.editorInstance = this.instantiateEditor(editorElem);
				const monacoHelperInstance = new globalThis.window.monacoHelper(this.editorInstance);
				monacoHelperInstance.updateHeight(this.wrapper);
				this.editorInstance.onDidContentSizeChange(() => {
					monacoHelperInstance.updateHeight(this.wrapper);
				});
				this.editorInstance.onDidChangeModelContent(() => {
					monacoHelperInstance.autocloseTag();
				});
			} catch (error) {
				console.error("Unable to initialize Monaco editor", error);
			}
		}).catch((error) => {
			console.error("Failed to load Monaco resources", error);
		});
	}
	async ensureMonacoLoaded() {
		if (globalThis.window.monaco && globalThis.window.monacoHelper) return true;
		if (!Raw.monacoLoaderPromise) Raw.monacoLoaderPromise = new Promise((resolve, reject) => {
			const script = globalThis.document.createElement("script");
			script.src = `${Raw.MONACO_SCRIPT_URL}?v=${Date.now()}`;
			script.async = true;
			script.defer = true;
			const cleanup = () => {
				script.removeEventListener("load", onLoad);
				script.removeEventListener("error", onError);
			};
			const onLoad = () => {
				cleanup();
				resolve();
			};
			const onError = (event) => {
				cleanup();
				reject(event);
			};
			script.addEventListener("load", onLoad);
			script.addEventListener("error", onError);
			globalThis.document.head.appendChild(script);
		});
		try {
			await Raw.monacoLoaderPromise;
		} catch (error) {
			Raw.monacoLoaderPromise = null;
			console.error("Error loading Monaco script", error);
			return false;
		}
		return typeof globalThis.window.monaco !== "undefined" && typeof globalThis.window.monacoHelper !== "undefined";
	}
	save() {
		if (this.editorInstance) this.data.html = this.editorInstance.getValue();
		return this.data;
	}
	static get conversionConfig() {
		return {
			export: "html",
			import: "html"
		};
	}
	static exportToMarkdown(data, _tunes) {
		if (!data || !data.html) return "";
		return data.html.replace(/\r\n/g, "\n").replace(/\n[ \t]+\n/g, "\n").replace(/\n{2,}/g, "\n").trim();
	}
	static importFromMarkdown(editor, markdown) {
		const block = editor.blocks.insert("raw");
		editor.blocks.update(block.id, { html: markdown }, {});
	}
	static isItMarkdownExported(_markdown) {
		return true;
	}
};
//#endregion
//#region src/assets/tools/List/List.ts
/** Two trailing spaces or a backslash: the line ends with a hard break. */
var HARD_BREAK_END = /(?: {2,}|\\)$/;
var List = class List extends G$1 {
	/**
	* @editorjs/list declares no rule of its own, so Editor.js would clean every
	* item with the inline tools' rules alone and strip the <br> of a Shift+Enter.
	*/
	static get sanitize() {
		return { items: { br: true } };
	}
	static async exportToMarkdown(data, tunes) {
		if (!data || !data.items) return "";
		const markdown = List._itemsToMarkdown(data.items, data.style ?? "unordered", 0);
		const formattedMarkdown = await MarkdownUtils.formatMarkdownWithPrettier(markdown);
		return MarkdownUtils.addAttributes(formattedMarkdown, tunes);
	}
	static _marker(style, item, index) {
		switch (style) {
			case "ordered": return `${index + 1}.`;
			case "checklist": return `- [${item.meta?.checked === true ? "x" : " "}]`;
			default: return "-";
		}
	}
	static _itemsToMarkdown(items, style, depth) {
		if (!items || items.length === 0) return "";
		const indent = "  ".repeat(depth);
		let markdown = "";
		items.forEach((item, index) => {
			const marker = List._marker(style, item, index);
			const content = typeof item === "string" ? item : item.content ?? "";
			const text = MarkdownUtils.convertInlineHtmlToMarkdown(content.replace(/<br\s*\/?>/gi, "  \n"));
			const textIndent = indent + " ".repeat(marker.length + 1);
			markdown += `${indent}${marker} ${text.replace(/\n/g, "\n" + textIndent)}\n`;
			if (item.items && item.items.length > 0) markdown += List._itemsToMarkdown(item.items, style, depth + 1);
		});
		return markdown;
	}
	static _style(hasCheckbox, isOrdered) {
		if (hasCheckbox) return "checklist";
		return isOrdered === true ? "ordered" : "unordered";
	}
	static importFromMarkdown(editor, markdown) {
		const result = MarkdownUtils.parseTunesFromMarkdown(markdown);
		const tunes = result.tunes;
		const lines = result.markdown.split("\n");
		const rootItems = [];
		const stack = [{
			items: rootItems,
			depth: -1
		}];
		let currentItem = null;
		let isOrdered = null;
		let hasCheckbox = false;
		for (const [index, line] of lines.entries()) {
			const trimmedLine = line.trim();
			if (!trimmedLine) continue;
			const orderedMatch = trimmedLine.match(/^(\d+)\.\s+(.*)/);
			const unorderedMatch = trimmedLine.match(/^[-*+]\s+(.*)/);
			if (!orderedMatch && !unorderedMatch) {
				if (currentItem === null) throw new Error("isItMarkdownExported not worked as expected");
				const previousLine = lines[index - 1] ?? "";
				if (previousLine.trim() === "") currentItem.content += "<br>";
				const hardBreak = HARD_BREAK_END.test(previousLine);
				if (hardBreak) currentItem.content = currentItem.content.replace(/\\$/, "");
				currentItem.content += (hardBreak ? "<br>" : "\n") + MarkdownUtils.convertInlineMarkdownToHtml(trimmedLine);
				continue;
			}
			const isCurrentOrdered = orderedMatch !== null;
			let rawContent = orderedMatch ? orderedMatch[2] : unorderedMatch[1];
			const checkboxMatch = rawContent.match(/^\[([ xX])\]\s+(.*)/);
			const meta = {};
			if (checkboxMatch && !isCurrentOrdered) {
				hasCheckbox = true;
				meta["checked"] = checkboxMatch[1].toLowerCase() === "x";
				rawContent = checkboxMatch[2];
			}
			const content = MarkdownUtils.convertInlineMarkdownToHtml(rawContent);
			if (isOrdered === null) isOrdered = isCurrentOrdered;
			else if (isOrdered !== isCurrentOrdered) return Raw.importFromMarkdown(editor, markdown);
			const leadingSpaces = line.length - line.trimStart().length;
			const currentDepth = Math.floor(leadingSpaces / 2);
			currentItem = {
				content,
				meta,
				items: []
			};
			while (stack.length > 1 && stack[stack.length - 1].depth >= currentDepth) stack.pop();
			const parent = stack[stack.length - 1];
			if (!parent) throw new Error("parent not found");
			parent.items.push(currentItem);
			stack.push({
				items: currentItem.items,
				depth: currentDepth
			});
		}
		const block = editor.blocks.insert("list");
		editor.blocks.update(block.id, {
			style: List._style(hasCheckbox, isOrdered),
			meta: {},
			items: rootItems
		}, tunes);
	}
	static isItMarkdownExported(markdown) {
		return markdown.trim().match(/^[-*+]\s/) !== null || markdown.trim().match(/^\d+\.\s/) !== null;
	}
};
//#endregion
//#region node_modules/@editorjs/quote/dist/quote.mjs
(function() {
	"use strict";
	try {
		if (typeof globalThis.document < "u") {
			var t = globalThis.document.createElement("style");
			t.appendChild(globalThis.document.createTextNode(".cdx-quote-icon svg{transform:rotate(180deg)}.cdx-quote{margin:0}.cdx-quote__text{min-height:158px;margin-bottom:10px}.cdx-quote [contentEditable=true][data-placeholder]:before{position:absolute;content:attr(data-placeholder);color:#707684;font-weight:400;opacity:0}.cdx-quote [contentEditable=true][data-placeholder]:empty:before{opacity:1}.cdx-quote [contentEditable=true][data-placeholder]:empty:focus:before{opacity:0}.cdx-quote-settings{display:flex}.cdx-quote-settings .cdx-settings-button{width:50%}")), globalThis.document.head.appendChild(t);
		}
	} catch (e) {
		console.error("vite-plugin-css-injected-by-js", e);
	}
})();
var De = "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" fill=\"none\" viewBox=\"0 0 24 24\"><path stroke=\"currentColor\" stroke-linecap=\"round\" stroke-width=\"2\" d=\"M18 7L6 7\"/><path stroke=\"currentColor\" stroke-linecap=\"round\" stroke-width=\"2\" d=\"M18 17H6\"/><path stroke=\"currentColor\" stroke-linecap=\"round\" stroke-width=\"2\" d=\"M16 12L8 12\"/></svg>";
var He = "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" fill=\"none\" viewBox=\"0 0 24 24\"><path stroke=\"currentColor\" stroke-linecap=\"round\" stroke-width=\"2\" d=\"M17 7L5 7\"/><path stroke=\"currentColor\" stroke-linecap=\"round\" stroke-width=\"2\" d=\"M17 17H5\"/><path stroke=\"currentColor\" stroke-linecap=\"round\" stroke-width=\"2\" d=\"M13 12L5 12\"/></svg>";
var Re = "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" fill=\"none\" viewBox=\"0 0 24 24\"><path stroke=\"currentColor\" stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M10 10.8182L9 10.8182C8.80222 10.8182 8.60888 10.7649 8.44443 10.665C8.27998 10.5651 8.15181 10.4231 8.07612 10.257C8.00043 10.0909 7.98063 9.90808 8.01922 9.73174C8.0578 9.55539 8.15304 9.39341 8.29289 9.26627C8.43275 9.13913 8.61093 9.05255 8.80491 9.01747C8.99889 8.98239 9.19996 9.00039 9.38268 9.0692C9.56541 9.13801 9.72159 9.25453 9.83147 9.40403C9.94135 9.55353 10 9.72929 10 9.90909L10 12.1818C10 12.664 9.78929 13.1265 9.41421 13.4675C9.03914 13.8084 8.53043 14 8 14\"/><path stroke=\"currentColor\" stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M16 10.8182L15 10.8182C14.8022 10.8182 14.6089 10.7649 14.4444 10.665C14.28 10.5651 14.1518 10.4231 14.0761 10.257C14.0004 10.0909 13.9806 9.90808 14.0192 9.73174C14.0578 9.55539 14.153 9.39341 14.2929 9.26627C14.4327 9.13913 14.6109 9.05255 14.8049 9.01747C14.9989 8.98239 15.2 9.00039 15.3827 9.0692C15.5654 9.13801 15.7216 9.25453 15.8315 9.40403C15.9414 9.55353 16 9.72929 16 9.90909L16 12.1818C16 12.664 15.7893 13.1265 15.4142 13.4675C15.0391 13.8084 14.5304 14 14 14\"/></svg>";
var b = typeof globalThis < "u" ? globalThis : typeof globalThis.window < "u" ? globalThis.window : typeof global < "u" ? global : typeof self < "u" ? self : {};
function Fe(e) {
	if (e.__esModule) return e;
	var t = e.default;
	if (typeof t == "function") {
		var n = function r() {
			return this instanceof r ? Reflect.construct(t, arguments, this.constructor) : t.apply(this, arguments);
		};
		n.prototype = t.prototype;
	} else n = {};
	return Object.defineProperty(n, "__esModule", { value: !0 }), Object.keys(e).forEach(function(r) {
		var i = Object.getOwnPropertyDescriptor(e, r);
		Object.defineProperty(n, r, i.get ? i : {
			enumerable: !0,
			get: function() {
				return e[r];
			}
		});
	}), n;
}
var v = {};
var P = {};
var j = {};
Object.defineProperty(j, "__esModule", { value: !0 });
j.allInputsSelector = We;
function We() {
	return "[contenteditable=true], textarea, input:not([type]), " + [
		"text",
		"password",
		"email",
		"number",
		"search",
		"tel",
		"url"
	].map(function(t) {
		return "input[type=\"".concat(t, "\"]");
	}).join(", ");
}
(function(e) {
	Object.defineProperty(e, "__esModule", { value: !0 }), e.allInputsSelector = void 0;
	var t = j;
	Object.defineProperty(e, "allInputsSelector", {
		enumerable: !0,
		get: function() {
			return t.allInputsSelector;
		}
	});
})(P);
var c = {};
var T = {};
Object.defineProperty(T, "__esModule", { value: !0 });
T.isNativeInput = Ue;
function Ue(e) {
	return e && e.tagName ? ["INPUT", "TEXTAREA"].includes(e.tagName) : !1;
}
(function(e) {
	Object.defineProperty(e, "__esModule", { value: !0 }), e.isNativeInput = void 0;
	var t = T;
	Object.defineProperty(e, "isNativeInput", {
		enumerable: !0,
		get: function() {
			return t.isNativeInput;
		}
	});
})(c);
var ie = {};
var C = {};
Object.defineProperty(C, "__esModule", { value: !0 });
C.append = qe;
function qe(e, t) {
	Array.isArray(t) ? t.forEach(function(n) {
		e.appendChild(n);
	}) : e.appendChild(t);
}
(function(e) {
	Object.defineProperty(e, "__esModule", { value: !0 }), e.append = void 0;
	var t = C;
	Object.defineProperty(e, "append", {
		enumerable: !0,
		get: function() {
			return t.append;
		}
	});
})(ie);
var L = {};
var S = {};
Object.defineProperty(S, "__esModule", { value: !0 });
S.blockElements = ze;
function ze() {
	return [
		"address",
		"article",
		"aside",
		"blockquote",
		"canvas",
		"div",
		"dl",
		"dt",
		"fieldset",
		"figcaption",
		"figure",
		"footer",
		"form",
		"h1",
		"h2",
		"h3",
		"h4",
		"h5",
		"h6",
		"header",
		"hgroup",
		"hr",
		"li",
		"main",
		"nav",
		"noscript",
		"ol",
		"output",
		"p",
		"pre",
		"ruby",
		"section",
		"table",
		"tbody",
		"thead",
		"tr",
		"tfoot",
		"ul",
		"video"
	];
}
(function(e) {
	Object.defineProperty(e, "__esModule", { value: !0 }), e.blockElements = void 0;
	var t = S;
	Object.defineProperty(e, "blockElements", {
		enumerable: !0,
		get: function() {
			return t.blockElements;
		}
	});
})(L);
var ae = {};
var M = {};
Object.defineProperty(M, "__esModule", { value: !0 });
M.calculateBaseline = Ge;
function Ge(e) {
	var t = globalThis.window.getComputedStyle(e), n = parseFloat(t.fontSize), r = parseFloat(t.lineHeight) || n * 1.2, i = parseFloat(t.paddingTop), a = parseFloat(t.borderTopWidth), l = parseFloat(t.marginTop), u = n * .8, d = (r - n) / 2;
	return l + a + i + d + u;
}
(function(e) {
	Object.defineProperty(e, "__esModule", { value: !0 }), e.calculateBaseline = void 0;
	var t = M;
	Object.defineProperty(e, "calculateBaseline", {
		enumerable: !0,
		get: function() {
			return t.calculateBaseline;
		}
	});
})(ae);
var le = {};
var k = {};
var w = {};
var N = {};
Object.defineProperty(N, "__esModule", { value: !0 });
N.isContentEditable = Ke;
function Ke(e) {
	return e.contentEditable === "true";
}
(function(e) {
	Object.defineProperty(e, "__esModule", { value: !0 }), e.isContentEditable = void 0;
	var t = N;
	Object.defineProperty(e, "isContentEditable", {
		enumerable: !0,
		get: function() {
			return t.isContentEditable;
		}
	});
})(w);
Object.defineProperty(k, "__esModule", { value: !0 });
k.canSetCaret = Qe;
var Xe = c;
var Ye = w;
function Qe(e) {
	var t = !0;
	if ((0, Xe.isNativeInput)(e)) switch (e.type) {
		case "file":
		case "checkbox":
		case "radio":
		case "hidden":
		case "submit":
		case "button":
		case "image":
		case "reset": t = !1;
	}
	else t = (0, Ye.isContentEditable)(e);
	return t;
}
(function(e) {
	Object.defineProperty(e, "__esModule", { value: !0 }), e.canSetCaret = void 0;
	var t = k;
	Object.defineProperty(e, "canSetCaret", {
		enumerable: !0,
		get: function() {
			return t.canSetCaret;
		}
	});
})(le);
var y = {};
var I = {};
function Ve(e, t, n) {
	const r = n.value !== void 0 ? "value" : "get", i = n[r], a = `#${t}Cache`;
	if (n[r] = function(...l) {
		return this[a] === void 0 && (this[a] = i.apply(this, l)), this[a];
	}, r === "get" && n.set) {
		const l = n.set;
		n.set = function(u) {
			delete e[a], l.apply(this, u);
		};
	}
	return n;
}
function ue() {
	const e = {
		win: !1,
		mac: !1,
		x11: !1,
		linux: !1
	}, t = Object.keys(e).find((n) => globalThis.window.navigator.appVersion.toLowerCase().indexOf(n) !== -1);
	return t !== void 0 && (e[t] = !0), e;
}
function A(e) {
	return e != null && e !== "" && (typeof e != "object" || Object.keys(e).length > 0);
}
function Ze(e) {
	return !A(e);
}
var Je = () => typeof globalThis.window < "u" && globalThis.window.navigator !== null && A(globalThis.window.navigator.platform) && (/iP(ad|hone|od)/.test(globalThis.window.navigator.platform) || globalThis.window.navigator.platform === "MacIntel" && globalThis.window.navigator.maxTouchPoints > 1);
function xe(e) {
	const t = ue();
	return e = e.replace(/shift/gi, "⇧").replace(/backspace/gi, "⌫").replace(/enter/gi, "⏎").replace(/up/gi, "↑").replace(/left/gi, "→").replace(/down/gi, "↓").replace(/right/gi, "←").replace(/escape/gi, "⎋").replace(/insert/gi, "Ins").replace(/delete/gi, "␡").replace(/\+/gi, "+"), t.mac ? e = e.replace(/ctrl|cmd/gi, "⌘").replace(/alt/gi, "⌥") : e = e.replace(/cmd/gi, "Ctrl").replace(/windows/gi, "WIN"), e;
}
function et(e) {
	return e[0].toUpperCase() + e.slice(1);
}
function tt(e) {
	const t = globalThis.document.createElement("div");
	t.style.position = "absolute", t.style.left = "-999px", t.style.bottom = "-999px", t.innerHTML = e, globalThis.document.body.appendChild(t);
	const n = globalThis.window.getSelection(), r = globalThis.document.createRange();
	if (r.selectNode(t), n === null) throw new Error("Cannot copy text to clipboard");
	n.removeAllRanges(), n.addRange(r), globalThis.document.execCommand("copy"), globalThis.document.body.removeChild(t);
}
function nt(e, t, n) {
	let r;
	return (...i) => {
		const a = this, l = () => {
			r = void 0, n !== !0 && e.apply(a, i);
		}, u = n === !0 && r !== void 0;
		globalThis.window.clearTimeout(r), r = globalThis.window.setTimeout(l, t), u && e.apply(a, i);
	};
}
function o(e) {
	return Object.prototype.toString.call(e).match(/\s([a-zA-Z]+)/)[1].toLowerCase();
}
function rt(e) {
	return o(e) === "boolean";
}
function oe(e) {
	return o(e) === "function" || o(e) === "asyncfunction";
}
function it(e) {
	return oe(e) && /^\s*class\s+/.test(e.toString());
}
function at(e) {
	return o(e) === "number";
}
function g(e) {
	return o(e) === "object";
}
function lt(e) {
	return Promise.resolve(e) === e;
}
function ut(e) {
	return o(e) === "string";
}
function ot(e) {
	return o(e) === "undefined";
}
function O(e, ...t) {
	if (!t.length) return e;
	const n = t.shift();
	if (g(e) && g(n)) for (const r in n) g(n[r]) ? (e[r] === void 0 && Object.assign(e, { [r]: {} }), O(e[r], n[r])) : Object.assign(e, { [r]: n[r] });
	return O(e, ...t);
}
function st(e, t, n) {
	const r = `«${t}» is deprecated and will be removed in the next major release. Please use the «${n}» instead.`;
	e && console.warn(r);
}
function ct(e) {
	try {
		return new URL(e).href;
	} catch {}
	return e.substring(0, 2) === "//" ? globalThis.window.location.protocol + e : globalThis.window.location.origin + e;
}
function dt(e) {
	return e > 47 && e < 58 || e === 32 || e === 13 || e === 229 || e > 64 && e < 91 || e > 95 && e < 112 || e > 185 && e < 193 || e > 218 && e < 223;
}
var ft = {
	BACKSPACE: 8,
	TAB: 9,
	ENTER: 13,
	SHIFT: 16,
	CTRL: 17,
	ALT: 18,
	ESC: 27,
	SPACE: 32,
	LEFT: 37,
	UP: 38,
	DOWN: 40,
	RIGHT: 39,
	DELETE: 46,
	META: 91,
	SLASH: 191
};
var pt = {
	LEFT: 0,
	WHEEL: 1,
	RIGHT: 2,
	BACKWARD: 3,
	FORWARD: 4
};
var vt = class {
	constructor() {
		this.completed = Promise.resolve();
	}
	/**
	* Add new promise to queue
	* @param operation - promise should be added to queue
	*/
	add(t) {
		return new Promise((n, r) => {
			this.completed = this.completed.then(t).then(n).catch(r);
		});
	}
};
function gt(e, t, n = void 0) {
	let r, i, a, l = null, u = 0;
	n || (n = {});
	const d = function() {
		u = n.leading === !1 ? 0 : Date.now(), l = null, a = e.apply(r, i), l === null && (r = i = null);
	};
	return function() {
		const s = Date.now();
		!u && n.leading === !1 && (u = s);
		const f = t - (s - u);
		return r = this, i = arguments, f <= 0 || f > t ? (l && (clearTimeout(l), l = null), u = s, a = e.apply(r, i), l === null && (r = i = null)) : !l && n.trailing !== !1 && (l = setTimeout(d, f)), a;
	};
}
var $ = /* @__PURE__ */ Fe(/* @__PURE__ */ Object.freeze(/* @__PURE__ */ Object.defineProperty({
	__proto__: null,
	PromiseQueue: vt,
	beautifyShortcut: xe,
	cacheable: Ve,
	capitalize: et,
	copyTextToClipboard: tt,
	debounce: nt,
	deepMerge: O,
	deprecationAssert: st,
	getUserOS: ue,
	getValidUrl: ct,
	isBoolean: rt,
	isClass: it,
	isEmpty: Ze,
	isFunction: oe,
	isIosDevice: Je,
	isNumber: at,
	isObject: g,
	isPrintableKey: dt,
	isPromise: lt,
	isString: ut,
	isUndefined: ot,
	keyCodes: ft,
	mouseButtons: pt,
	notEmpty: A,
	throttle: gt,
	typeOf: o
}, Symbol.toStringTag, { value: "Module" })));
Object.defineProperty(I, "__esModule", { value: !0 });
I.containsOnlyInlineElements = _t;
var bt = $;
var yt = L;
function _t(e) {
	var t;
	(0, bt.isString)(e) ? (t = globalThis.document.createElement("div"), t.innerHTML = e) : t = e;
	var n = function(r) {
		return !(0, yt.blockElements)().includes(r.tagName.toLowerCase()) && Array.from(r.children).every(n);
	};
	return Array.from(t.children).every(n);
}
(function(e) {
	Object.defineProperty(e, "__esModule", { value: !0 }), e.containsOnlyInlineElements = void 0;
	var t = I;
	Object.defineProperty(e, "containsOnlyInlineElements", {
		enumerable: !0,
		get: function() {
			return t.containsOnlyInlineElements;
		}
	});
})(y);
var se = {};
var B = {};
var _ = {};
var D = {};
Object.defineProperty(D, "__esModule", { value: !0 });
D.make = ht;
function ht(e, t, n) {
	var r;
	t === void 0 && (t = null), n === void 0 && (n = {});
	var i = globalThis.document.createElement(e);
	if (Array.isArray(t)) {
		var a = t.filter(function(u) {
			return u !== void 0;
		});
		(r = i.classList).add.apply(r, a);
	} else t !== null && i.classList.add(t);
	for (var l in n) Object.prototype.hasOwnProperty.call(n, l) && (i[l] = n[l]);
	return i;
}
(function(e) {
	Object.defineProperty(e, "__esModule", { value: !0 }), e.make = void 0;
	var t = D;
	Object.defineProperty(e, "make", {
		enumerable: !0,
		get: function() {
			return t.make;
		}
	});
})(_);
Object.defineProperty(B, "__esModule", { value: !0 });
B.fragmentToString = Ot;
var Et = _;
function Ot(e) {
	var t = (0, Et.make)("div");
	return t.appendChild(e), t.innerHTML;
}
(function(e) {
	Object.defineProperty(e, "__esModule", { value: !0 }), e.fragmentToString = void 0;
	var t = B;
	Object.defineProperty(e, "fragmentToString", {
		enumerable: !0,
		get: function() {
			return t.fragmentToString;
		}
	});
})(se);
var ce = {};
var H = {};
Object.defineProperty(H, "__esModule", { value: !0 });
H.getContentLength = jt;
var Pt = c;
function jt(e) {
	var t, n;
	return (0, Pt.isNativeInput)(e) ? e.value.length : e.nodeType === Node.TEXT_NODE ? e.length : (n = (t = e.textContent) === null || t === void 0 ? void 0 : t.length) !== null && n !== void 0 ? n : 0;
}
(function(e) {
	Object.defineProperty(e, "__esModule", { value: !0 }), e.getContentLength = void 0;
	var t = H;
	Object.defineProperty(e, "getContentLength", {
		enumerable: !0,
		get: function() {
			return t.getContentLength;
		}
	});
})(ce);
var R = {};
var F = {};
var re = b && b.__spreadArray || function(e, t, n) {
	if (n || arguments.length === 2) for (var r = 0, i = t.length, a; r < i; r++) (a || !(r in t)) && (a || (a = Array.prototype.slice.call(t, 0, r)), a[r] = t[r]);
	return e.concat(a || Array.prototype.slice.call(t));
};
Object.defineProperty(F, "__esModule", { value: !0 });
F.getDeepestBlockElements = de;
var Tt = y;
function de(e) {
	return (0, Tt.containsOnlyInlineElements)(e) ? [e] : Array.from(e.children).reduce(function(t, n) {
		return re(re([], t, !0), de(n), !0);
	}, []);
}
(function(e) {
	Object.defineProperty(e, "__esModule", { value: !0 }), e.getDeepestBlockElements = void 0;
	var t = F;
	Object.defineProperty(e, "getDeepestBlockElements", {
		enumerable: !0,
		get: function() {
			return t.getDeepestBlockElements;
		}
	});
})(R);
var fe = {};
var W = {};
var h = {};
var U = {};
Object.defineProperty(U, "__esModule", { value: !0 });
U.isLineBreakTag = Ct;
function Ct(e) {
	return ["BR", "WBR"].includes(e.tagName);
}
(function(e) {
	Object.defineProperty(e, "__esModule", { value: !0 }), e.isLineBreakTag = void 0;
	var t = U;
	Object.defineProperty(e, "isLineBreakTag", {
		enumerable: !0,
		get: function() {
			return t.isLineBreakTag;
		}
	});
})(h);
var E = {};
var q = {};
Object.defineProperty(q, "__esModule", { value: !0 });
q.isSingleTag = Lt;
function Lt(e) {
	return [
		"AREA",
		"BASE",
		"BR",
		"COL",
		"COMMAND",
		"EMBED",
		"HR",
		"IMG",
		"INPUT",
		"KEYGEN",
		"LINK",
		"META",
		"PARAM",
		"SOURCE",
		"TRACK",
		"WBR"
	].includes(e.tagName);
}
(function(e) {
	Object.defineProperty(e, "__esModule", { value: !0 }), e.isSingleTag = void 0;
	var t = q;
	Object.defineProperty(e, "isSingleTag", {
		enumerable: !0,
		get: function() {
			return t.isSingleTag;
		}
	});
})(E);
Object.defineProperty(W, "__esModule", { value: !0 });
W.getDeepestNode = pe;
var St = c;
var Mt = h;
var kt = E;
function pe(e, t) {
	t === void 0 && (t = !1);
	var n = t ? "lastChild" : "firstChild", r = t ? "previousSibling" : "nextSibling";
	if (e.nodeType === Node.ELEMENT_NODE && e[n]) {
		var i = e[n];
		if ((0, kt.isSingleTag)(i) && !(0, St.isNativeInput)(i) && !(0, Mt.isLineBreakTag)(i)) if (i[r]) i = i[r];
		else if (i.parentNode !== null && i.parentNode[r]) i = i.parentNode[r];
		else return i.parentNode;
		return pe(i, t);
	}
	return e;
}
(function(e) {
	Object.defineProperty(e, "__esModule", { value: !0 }), e.getDeepestNode = void 0;
	var t = W;
	Object.defineProperty(e, "getDeepestNode", {
		enumerable: !0,
		get: function() {
			return t.getDeepestNode;
		}
	});
})(fe);
var ve = {};
var z = {};
var p = b && b.__spreadArray || function(e, t, n) {
	if (n || arguments.length === 2) for (var r = 0, i = t.length, a; r < i; r++) (a || !(r in t)) && (a || (a = Array.prototype.slice.call(t, 0, r)), a[r] = t[r]);
	return e.concat(a || Array.prototype.slice.call(t));
};
Object.defineProperty(z, "__esModule", { value: !0 });
z.findAllInputs = $t;
var wt = y;
var Nt = R;
var It = P;
var At = c;
function $t(e) {
	return Array.from(e.querySelectorAll((0, It.allInputsSelector)())).reduce(function(t, n) {
		return (0, At.isNativeInput)(n) || (0, wt.containsOnlyInlineElements)(n) ? p(p([], t, !0), [n], !1) : p(p([], t, !0), (0, Nt.getDeepestBlockElements)(n), !0);
	}, []);
}
(function(e) {
	Object.defineProperty(e, "__esModule", { value: !0 }), e.findAllInputs = void 0;
	var t = z;
	Object.defineProperty(e, "findAllInputs", {
		enumerable: !0,
		get: function() {
			return t.findAllInputs;
		}
	});
})(ve);
var ge = {};
var G = {};
Object.defineProperty(G, "__esModule", { value: !0 });
G.isCollapsedWhitespaces = Bt;
function Bt(e) {
	return !/[^\t\n\r ]/.test(e);
}
(function(e) {
	Object.defineProperty(e, "__esModule", { value: !0 }), e.isCollapsedWhitespaces = void 0;
	var t = G;
	Object.defineProperty(e, "isCollapsedWhitespaces", {
		enumerable: !0,
		get: function() {
			return t.isCollapsedWhitespaces;
		}
	});
})(ge);
var K = {};
var X = {};
Object.defineProperty(X, "__esModule", { value: !0 });
X.isElement = Ht;
var Dt = $;
function Ht(e) {
	return (0, Dt.isNumber)(e) ? !1 : !!e && !!e.nodeType && e.nodeType === Node.ELEMENT_NODE;
}
(function(e) {
	Object.defineProperty(e, "__esModule", { value: !0 }), e.isElement = void 0;
	var t = X;
	Object.defineProperty(e, "isElement", {
		enumerable: !0,
		get: function() {
			return t.isElement;
		}
	});
})(K);
var me = {};
var Y = {};
var Q = {};
var V = {};
Object.defineProperty(V, "__esModule", { value: !0 });
V.isLeaf = Rt;
function Rt(e) {
	return e === null ? !1 : e.childNodes.length === 0;
}
(function(e) {
	Object.defineProperty(e, "__esModule", { value: !0 }), e.isLeaf = void 0;
	var t = V;
	Object.defineProperty(e, "isLeaf", {
		enumerable: !0,
		get: function() {
			return t.isLeaf;
		}
	});
})(Q);
var Z = {};
var J = {};
Object.defineProperty(J, "__esModule", { value: !0 });
J.isNodeEmpty = zt;
var Ft = h;
var Wt = K;
var Ut = c;
var qt = E;
function zt(e, t) {
	var n = "";
	return (0, qt.isSingleTag)(e) && !(0, Ft.isLineBreakTag)(e) ? !1 : ((0, Wt.isElement)(e) && (0, Ut.isNativeInput)(e) ? n = e.value : e.textContent !== null && (n = e.textContent.replace("​", "")), t !== void 0 && (n = n.replace(new RegExp(t, "g"), "")), n.trim().length === 0);
}
(function(e) {
	Object.defineProperty(e, "__esModule", { value: !0 }), e.isNodeEmpty = void 0;
	var t = J;
	Object.defineProperty(e, "isNodeEmpty", {
		enumerable: !0,
		get: function() {
			return t.isNodeEmpty;
		}
	});
})(Z);
Object.defineProperty(Y, "__esModule", { value: !0 });
Y.isEmpty = Xt;
var Gt = Q;
var Kt = Z;
function Xt(e, t) {
	e.normalize();
	for (var n = [e]; n.length > 0;) {
		var r = n.shift();
		if (r) {
			if (e = r, (0, Gt.isLeaf)(e) && !(0, Kt.isNodeEmpty)(e, t)) return !1;
			n.push.apply(n, Array.from(e.childNodes));
		}
	}
	return !0;
}
(function(e) {
	Object.defineProperty(e, "__esModule", { value: !0 }), e.isEmpty = void 0;
	var t = Y;
	Object.defineProperty(e, "isEmpty", {
		enumerable: !0,
		get: function() {
			return t.isEmpty;
		}
	});
})(me);
var be = {};
var x = {};
Object.defineProperty(x, "__esModule", { value: !0 });
x.isFragment = Qt;
var Yt = $;
function Qt(e) {
	return (0, Yt.isNumber)(e) ? !1 : !!e && !!e.nodeType && e.nodeType === Node.DOCUMENT_FRAGMENT_NODE;
}
(function(e) {
	Object.defineProperty(e, "__esModule", { value: !0 }), e.isFragment = void 0;
	var t = x;
	Object.defineProperty(e, "isFragment", {
		enumerable: !0,
		get: function() {
			return t.isFragment;
		}
	});
})(be);
var ye = {};
var ee = {};
Object.defineProperty(ee, "__esModule", { value: !0 });
ee.isHTMLString = Zt;
var Vt = _;
function Zt(e) {
	var t = (0, Vt.make)("div");
	return t.innerHTML = e, t.childElementCount > 0;
}
(function(e) {
	Object.defineProperty(e, "__esModule", { value: !0 }), e.isHTMLString = void 0;
	var t = ee;
	Object.defineProperty(e, "isHTMLString", {
		enumerable: !0,
		get: function() {
			return t.isHTMLString;
		}
	});
})(ye);
var _e = {};
var te = {};
Object.defineProperty(te, "__esModule", { value: !0 });
te.offset = Jt;
function Jt(e) {
	var t = e.getBoundingClientRect(), n = globalThis.window.pageXOffset || globalThis.document.documentElement.scrollLeft, r = globalThis.window.pageYOffset || globalThis.document.documentElement.scrollTop, i = t.top + r, a = t.left + n;
	return {
		top: i,
		left: a,
		bottom: i + t.height,
		right: a + t.width
	};
}
(function(e) {
	Object.defineProperty(e, "__esModule", { value: !0 }), e.offset = void 0;
	var t = te;
	Object.defineProperty(e, "offset", {
		enumerable: !0,
		get: function() {
			return t.offset;
		}
	});
})(_e);
var he$1 = {};
var ne = {};
Object.defineProperty(ne, "__esModule", { value: !0 });
ne.prepend = xt;
function xt(e, t) {
	Array.isArray(t) ? (t = t.reverse(), t.forEach(function(n) {
		return e.prepend(n);
	})) : e.prepend(t);
}
(function(e) {
	Object.defineProperty(e, "__esModule", { value: !0 }), e.prepend = void 0;
	var t = ne;
	Object.defineProperty(e, "prepend", {
		enumerable: !0,
		get: function() {
			return t.prepend;
		}
	});
})(he$1);
(function(e) {
	Object.defineProperty(e, "__esModule", { value: !0 }), e.prepend = e.offset = e.make = e.isLineBreakTag = e.isSingleTag = e.isNodeEmpty = e.isLeaf = e.isHTMLString = e.isFragment = e.isEmpty = e.isElement = e.isContentEditable = e.isCollapsedWhitespaces = e.findAllInputs = e.isNativeInput = e.allInputsSelector = e.getDeepestNode = e.getDeepestBlockElements = e.getContentLength = e.fragmentToString = e.containsOnlyInlineElements = e.canSetCaret = e.calculateBaseline = e.blockElements = e.append = void 0;
	var t = P;
	Object.defineProperty(e, "allInputsSelector", {
		enumerable: !0,
		get: function() {
			return t.allInputsSelector;
		}
	});
	var n = c;
	Object.defineProperty(e, "isNativeInput", {
		enumerable: !0,
		get: function() {
			return n.isNativeInput;
		}
	});
	var r = ie;
	Object.defineProperty(e, "append", {
		enumerable: !0,
		get: function() {
			return r.append;
		}
	});
	var i = L;
	Object.defineProperty(e, "blockElements", {
		enumerable: !0,
		get: function() {
			return i.blockElements;
		}
	});
	var a = ae;
	Object.defineProperty(e, "calculateBaseline", {
		enumerable: !0,
		get: function() {
			return a.calculateBaseline;
		}
	});
	var l = le;
	Object.defineProperty(e, "canSetCaret", {
		enumerable: !0,
		get: function() {
			return l.canSetCaret;
		}
	});
	var u = y;
	Object.defineProperty(e, "containsOnlyInlineElements", {
		enumerable: !0,
		get: function() {
			return u.containsOnlyInlineElements;
		}
	});
	var d = se;
	Object.defineProperty(e, "fragmentToString", {
		enumerable: !0,
		get: function() {
			return d.fragmentToString;
		}
	});
	var s = ce;
	Object.defineProperty(e, "getContentLength", {
		enumerable: !0,
		get: function() {
			return s.getContentLength;
		}
	});
	var f = R;
	Object.defineProperty(e, "getDeepestBlockElements", {
		enumerable: !0,
		get: function() {
			return f.getDeepestBlockElements;
		}
	});
	var Oe = fe;
	Object.defineProperty(e, "getDeepestNode", {
		enumerable: !0,
		get: function() {
			return Oe.getDeepestNode;
		}
	});
	var Pe = ve;
	Object.defineProperty(e, "findAllInputs", {
		enumerable: !0,
		get: function() {
			return Pe.findAllInputs;
		}
	});
	var je = ge;
	Object.defineProperty(e, "isCollapsedWhitespaces", {
		enumerable: !0,
		get: function() {
			return je.isCollapsedWhitespaces;
		}
	});
	var Te = w;
	Object.defineProperty(e, "isContentEditable", {
		enumerable: !0,
		get: function() {
			return Te.isContentEditable;
		}
	});
	var Ce = K;
	Object.defineProperty(e, "isElement", {
		enumerable: !0,
		get: function() {
			return Ce.isElement;
		}
	});
	var Le = me;
	Object.defineProperty(e, "isEmpty", {
		enumerable: !0,
		get: function() {
			return Le.isEmpty;
		}
	});
	var Se = be;
	Object.defineProperty(e, "isFragment", {
		enumerable: !0,
		get: function() {
			return Se.isFragment;
		}
	});
	var Me = ye;
	Object.defineProperty(e, "isHTMLString", {
		enumerable: !0,
		get: function() {
			return Me.isHTMLString;
		}
	});
	var ke = Q;
	Object.defineProperty(e, "isLeaf", {
		enumerable: !0,
		get: function() {
			return ke.isLeaf;
		}
	});
	var we = Z;
	Object.defineProperty(e, "isNodeEmpty", {
		enumerable: !0,
		get: function() {
			return we.isNodeEmpty;
		}
	});
	var Ne = h;
	Object.defineProperty(e, "isLineBreakTag", {
		enumerable: !0,
		get: function() {
			return Ne.isLineBreakTag;
		}
	});
	var Ie = E;
	Object.defineProperty(e, "isSingleTag", {
		enumerable: !0,
		get: function() {
			return Ie.isSingleTag;
		}
	});
	var Ae = _;
	Object.defineProperty(e, "make", {
		enumerable: !0,
		get: function() {
			return Ae.make;
		}
	});
	var $e = _e;
	Object.defineProperty(e, "offset", {
		enumerable: !0,
		get: function() {
			return $e.offset;
		}
	});
	var Be = he$1;
	Object.defineProperty(e, "prepend", {
		enumerable: !0,
		get: function() {
			return Be.prepend;
		}
	});
})(v);
var Ee = /* @__PURE__ */ ((e) => (e.Left = "left", e.Center = "center", e))(Ee || {});
var m = class m {
	/**
	* Render plugin`s main Element and fill it with saved data
	* @param params - Quote Tool constructor params
	* @param params.data - previously saved data
	* @param params.config - user config for Tool
	* @param params.api - editor.js api
	* @param params.readOnly - read only mode flag
	*/
	constructor({ data: t, config: n, api: r, readOnly: i, block: a }) {
		const { DEFAULT_ALIGNMENT: l } = m;
		this.api = r, this.readOnly = i, this.quotePlaceholder = r.i18n.t((n == null ? void 0 : n.quotePlaceholder) ?? m.DEFAULT_QUOTE_PLACEHOLDER), this.captionPlaceholder = r.i18n.t((n == null ? void 0 : n.captionPlaceholder) ?? m.DEFAULT_CAPTION_PLACEHOLDER), this.data = {
			text: t.text || "",
			caption: t.caption || "",
			alignment: Object.values(Ee).includes(t.alignment) ? t.alignment : (n == null ? void 0 : n.defaultAlignment) ?? l
		}, this.css = {
			baseClass: this.api.styles.block,
			wrapper: "cdx-quote",
			text: "cdx-quote__text",
			input: this.api.styles.input,
			caption: "cdx-quote__caption"
		}, this.block = a;
	}
	/**
	* Notify core that read-only mode is supported
	* @returns true
	*/
	static get isReadOnlySupported() {
		return !0;
	}
	/**
	* Get Tool toolbox settings
	* icon - Tool icon's SVG
	* title - title to show in toolbox
	* @returns icon and title of the toolbox
	*/
	static get toolbox() {
		return {
			icon: Re,
			title: "Quote"
		};
	}
	/**
	* Empty Quote is not empty Block
	* @returns true
	*/
	static get contentless() {
		return !0;
	}
	/**
	* Allow to press Enter inside the Quote
	* @returns true
	*/
	static get enableLineBreaks() {
		return !0;
	}
	/**
	* Default placeholder for quote text
	* @returns 'Enter a quote'
	*/
	static get DEFAULT_QUOTE_PLACEHOLDER() {
		return "Enter a quote";
	}
	/**
	* Default placeholder for quote caption
	* @returns 'Enter a caption'
	*/
	static get DEFAULT_CAPTION_PLACEHOLDER() {
		return "Enter a caption";
	}
	/**
	* Default quote alignment
	* @returns Alignment.Left
	*/
	static get DEFAULT_ALIGNMENT() {
		return "left";
	}
	/**
	* Allow Quote to be converted to/from other blocks
	* @returns conversion config object
	*/
	static get conversionConfig() {
		return {
			/**
			* To create Quote data from string, simple fill 'text' property
			*/
			import: "text",
			/**
			* To create string from Quote data, concatenate text and caption
			* @param quoteData - Quote data object
			* @returns string
			*/
			export: function(t) {
				return t.caption ? `${t.text} — ${t.caption}` : t.text;
			}
		};
	}
	/**
	* Tool`s styles
	* @returns CSS classes names
	*/
	get CSS() {
		return {
			baseClass: this.api.styles.block,
			wrapper: "cdx-quote",
			text: "cdx-quote__text",
			input: this.api.styles.input,
			caption: "cdx-quote__caption"
		};
	}
	/**
	* Tool`s settings properties
	* @returns settings properties
	*/
	get settings() {
		return [{
			name: "left",
			icon: He
		}, {
			name: "center",
			icon: De
		}];
	}
	/**
	* Create Quote Tool container with inputs
	* @returns blockquote DOM element - Quote Tool container
	*/
	render() {
		const t = v.make("blockquote", [this.css.baseClass, this.css.wrapper]), n = v.make("div", [this.css.input, this.css.text], {
			contentEditable: !this.readOnly,
			innerHTML: this.data.text
		}), r = v.make("div", [this.css.input, this.css.caption], {
			contentEditable: !this.readOnly,
			innerHTML: this.data.caption
		});
		return n.dataset.placeholder = this.quotePlaceholder, r.dataset.placeholder = this.captionPlaceholder, t.appendChild(n), t.appendChild(r), t;
	}
	/**
	* Extract Quote data from Quote Tool element
	* @param quoteElement - Quote DOM element to save
	* @returns Quote data object
	*/
	save(t) {
		const n = t.querySelector(`.${this.css.text}`), r = t.querySelector(`.${this.css.caption}`);
		return Object.assign(this.data, {
			text: (n == null ? void 0 : n.innerHTML) ?? "",
			caption: (r == null ? void 0 : r.innerHTML) ?? ""
		});
	}
	/**
	* Sanitizer rules
	* @returns sanitizer rules
	*/
	static get sanitize() {
		return {
			text: { br: !0 },
			caption: { br: !0 },
			alignment: {}
		};
	}
	/**
	* Create wrapper for Tool`s settings buttons:
	* 1. Left alignment
	* 2. Center alignment
	* @returns settings menu
	*/
	renderSettings() {
		const t = (n) => n && n[0].toUpperCase() + n.slice(1);
		return this.settings.map((n) => ({
			icon: n.icon,
			label: this.api.i18n.t(`Align ${t(n.name)}`),
			onActivate: () => this._toggleTune(n.name),
			isActive: this.data.alignment === n.name,
			closeOnActivate: !0
		}));
	}
	/**
	* Toggle quote`s alignment
	* @param tune - alignment
	*/
	_toggleTune(t) {
		this.data.alignment = t, this.block.dispatchChange();
	}
};
//#endregion
//#region src/assets/tools/Quote/Quote.ts
var Quote = class extends m {
	static exportToMarkdown(data, tunes) {
		if (!data || !data.text) return "";
		let markdown = "";
		const lines = data.text.split(/<br\s*\/?>/gi);
		for (const line of lines) markdown += `> ${line.trim()}
`;
		if (data.caption) markdown += `> — <cite>${data.caption}</cite>`;
		return MarkdownUtils.addAttributes(markdown, tunes);
	}
	static importFromMarkdown(editor, markdown) {
		const result = MarkdownUtils.parseTunesFromMarkdown(markdown);
		const tunes = result.tunes;
		const lines = result.markdown.split("\n");
		let caption = "";
		let quoteText = "";
		let inQuote = true;
		for (const line of lines) {
			if (line.trim().match(/^>\s*(—|-)/) || !inQuote) {
				inQuote = false;
				caption += line.trim().replace(/^>\s*(—|-)\s*(<cite>)?/, "").replace(/<\/cite>\s*$/, "");
				continue;
			}
			if (line.trim().startsWith(">")) quoteText += line.trim().replace(/^>\s?/, "") + "<br>";
		}
		caption = caption.trim();
		quoteText = quoteText.replace(/<br>$/, "").trim();
		const block = editor.blocks.insert("quote");
		editor.blocks.update(block.id, {
			text: quoteText,
			caption
		}, tunes);
	}
	static isItMarkdownExported(markdown) {
		return markdown.startsWith("> ");
	}
};
//#endregion
//#region src/assets/tools/CodeBlock/icon.svg?raw
var icon_default = "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"16\" height=\"16\" fill=\"currentColor\" class=\"bi bi-braces\" viewBox=\"0 0 16 16\">\n    <path d=\"M2.114 8.063V7.9c1.005-.102 1.497-.615 1.497-1.6V4.503c0-1.094.39-1.538 1.354-1.538h.273V2h-.376C3.25 2 2.49 2.759 2.49 4.352v1.524c0 1.094-.376 1.456-1.49 1.456v1.299c1.114 0 1.49.362 1.49 1.456v1.524c0 1.593.759 2.352 2.372 2.352h.376v-.964h-.273c-.964 0-1.354-.444-1.354-1.538V9.663c0-.984-.492-1.497-1.497-1.6M13.886 7.9v.163c-1.005.103-1.497.616-1.497 1.6v1.798c0 1.094-.39 1.538-1.354 1.538h-.273v.964h.376c1.613 0 2.372-.759 2.372-2.352v-1.524c0-1.094.376-1.456 1.49-1.456V7.332c-1.114 0-1.49-.362-1.49-1.456V4.352C13.51 2.759 12.75 2 11.138 2h-.376v.964h.273c.964 0 1.354.444 1.354 1.538V6.3c0 .984.492 1.497 1.497 1.6\"/>\n</svg>\n\n";
//#endregion
//#region src/assets/tools/utils/make.ts
var make$1 = class make$1 {
	static {
		this.switchCount = 0;
	}
	static element(tagName, classNames = null, attributes = {}, innerHTML = "", onclick = null) {
		const el = globalThis.document.createElement(tagName);
		if (Array.isArray(classNames)) el.classList.add(...classNames);
		else if (classNames) el.classList.add(classNames);
		for (const attrName in attributes) el.setAttribute(attrName, attributes[attrName]);
		if (innerHTML !== "") el.innerHTML = innerHTML;
		if (onclick) el.addEventListener("click", onclick);
		return el;
	}
	static input(Tool, classNames, placeholder, value = "") {
		const input = make$1.element("div", classNames, { contentEditable: !Tool.readOnly });
		input.dataset.placeholder = Tool.api.i18n.t(placeholder);
		if (value) input.textContent = value;
		return input;
	}
	static option(select, key, value = null, attributes = {}, selectedValue = null) {
		const option = globalThis.document.createElement("option");
		option.text = value || key;
		option.value = key;
		for (const attrName in attributes) option.setAttribute(attrName, attributes[attrName]);
		if (selectedValue !== null && selectedValue === value) option.selected = true;
		select.add(option);
	}
	static options(select, options, selectedValue = null) {
		options.forEach((option) => make$1.option(select, option, null, {}, selectedValue));
	}
	static switchInput(name, labelText, checked = false) {
		const id = `${name}-${++make$1.switchCount}`;
		const wrapper = make$1.element("div", "editor-switch");
		const checkbox = make$1.element("input", null, {
			type: "checkbox",
			id,
			role: "switch"
		});
		const switchElement = make$1.element("label", "label-default", { for: id });
		const label = make$1.element("label", "", { for: id });
		label.textContent = labelText;
		wrapper.append(checkbox, label, switchElement);
		if (checked) checkbox.checked = checked;
		return wrapper;
	}
	static selectionCollapseToEnd() {
		const sel = globalThis.window.getSelection();
		if (!sel || !sel.focusNode) return;
		const range = globalThis.document.createRange();
		range.selectNodeContents(sel.focusNode);
		range.collapse(false);
		sel.removeAllRanges();
		sel.addRange(range);
	}
	static moveCaretToTheEnd(element) {
		if (!element.focus) return;
		element.focus();
		const range = globalThis.document.createRange();
		range.selectNodeContents(element);
		range.collapse(false);
		const selection = globalThis.window.getSelection();
		if (!selection) return;
		selection.removeAllRanges();
		selection.addRange(range);
	}
};
//#endregion
//#region src/assets/tools/CodeBlock/CodeBlock.ts
/**
* The code is contains in html, but it could be whatever you want
*/
var CodeBlock = class extends Raw {
	constructor({ data, api, readOnly }) {
		super({
			data,
			api,
			readOnly
		});
		this._codeBlockData = {
			html: "",
			language: "html"
		};
		this._codeBlockData = {
			html: data?.html || "",
			language: data?.language || "html"
		};
		Object.defineProperty(this, "data", {
			get: () => this._codeBlockData,
			set: (newData) => {
				const html = newData?.html || "";
				const language = newData?.language || this._codeBlockData.language || "html";
				this._codeBlockData = {
					html,
					language
				};
				if (this.editorInstance && this.editorInstance.getValue() !== html) this.editorInstance.setValue(html);
			},
			configurable: true,
			enumerable: true
		});
	}
	render() {
		const wrapper = super.render();
		const select = make$1.element("select", this.api.styles.input, { style: "max-width: 100px;padding: 5px 6px;margin: auto; position: absolute; right: 5px; z-index: 5; background: white" });
		make$1.options(select, [
			"html",
			"twig",
			"javascript",
			"php",
			"json",
			"yaml"
		]);
		select.value = this._codeBlockData.language;
		select.addEventListener("change", (event) => {
			const target = event.target;
			this._codeBlockData.language = target.value;
			this.editorInstance.getModel().setLanguage(this._codeBlockData.language);
		});
		const editorWrapper = wrapper.firstChild;
		wrapper.insertBefore(select, editorWrapper);
		wrapper.style.marginBottom = "35px";
		wrapper.style.position = "relative";
		wrapper.classList.add("monaco-codeblock-wrapper");
		return wrapper;
	}
	/**
	* Extract Tool's data from the view
	*
	* @returns {RawData} - raw HTML code
	* @public
	*/
	save() {
		if (this.editorInstance) this._codeBlockData.html = this.editorInstance.getValue();
		return this._codeBlockData;
	}
	static get toolbox() {
		return {
			icon: icon_default,
			title: "Code"
		};
	}
	/**
	* Export block data to Markdown
	* @param {CodeBlockData} data - Block data
	* @param {BlockTuneData} tunes - Block tunes
	* @returns {string} Markdown representation
	*/
	static exportToMarkdown(data, _tunes) {
		if (!data || !data.html) return "";
		return `\`\`\`${data.language || ""}\n${data.html}\n\`\`\``;
	}
	static importFromMarkdown(editor, markdown) {
		const lines = markdown.split("\n");
		let i = 0;
		let tunes = {};
		let language = "";
		let html = "";
		let firstLineHasAttributes = false;
		for (const line of lines) {
			if (i === 0 && MarkdownUtils.startWithAttribute(line)) {
				tunes = MarkdownUtils.parseAttributes(line);
				firstLineHasAttributes = true;
				i++;
				continue;
			} else if (i === 0 || i === 1 && firstLineHasAttributes) {
				language = line.replace("```", "").trim();
				i++;
				continue;
			}
			if (i === lines.length - 1) break;
			html += lines[i] + "\n";
			i++;
		}
		const block = editor.blocks.insert("codeBlock");
		editor.blocks.update(block.id, {
			html: html.trim(),
			language: language || "html"
		}, tunes);
	}
	static isItMarkdownExported(markdown) {
		return markdown.trim().startsWith("```") && markdown.trim().endsWith("```");
	}
};
//#endregion
//#region src/assets/tools/Abstract/icon/folder.svg?raw
var folder_default = "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"16\" height=\"16\" fill=\"currentColor\" viewBox=\"0 0 16 16\">  <path d=\"M1 3.5A1.5 1.5 0 0 1 2.5 2h2.764c.958 0 1.76.56 2.311 1.184C7.985 3.648 8.48 4 9 4h4.5A1.5 1.5 0 0 1 15 5.5v.64c.57.265.94.876.856 1.546l-.64 5.124A2.5 2.5 0 0 1 12.733 15H3.266a2.5 2.5 0 0 1-2.481-2.19l-.64-5.124A1.5 1.5 0 0 1 1 6.14V3.5zM2 6h12v-.5a.5.5 0 0 0-.5-.5H9c-.964 0-1.71-.629-2.174-1.154C6.374 3.334 5.82 3 5.264 3H2.5a.5.5 0 0 0-.5.5V6zm-.367 1a.5.5 0 0 0-.496.562l.64 5.124A1.5 1.5 0 0 0 3.266 14h9.468a1.5 1.5 0 0 0 1.489-1.314l.64-5.124A.5.5 0 0 0 14.367 7H1.633z\"/></svg>\n";
//#endregion
//#region src/assets/tools/Abstract/icon/upload.svg?raw
var upload_default = "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"16\" height=\"16\" fill=\"currentColor\" viewBox=\"0 0 16 16\">  <path d=\"M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z\"/>  <path d=\"M7.646 1.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1-.708.708L8.5 2.707V11.5a.5.5 0 0 1-1 0V2.707L5.354 4.854a.5.5 0 1 1-.708-.708l3-3z\"/></svg>\n";
//#endregion
//#region src/assets/tools/utils/media.ts
/**
* Utilitaires pour la gestion des médias
*/
var MediaUtils = class {
	/**
	* Extrait le nom du fichier média depuis une URL
	* @param url - URL complète du média
	* @returns Le nom du fichier (dernière partie de l'URL après /)
	*/
	static extractMediaName(url) {
		if (!url) return "";
		const urlParts = url.split("/");
		const name = urlParts[urlParts.length - 1] || "";
		try {
			return decodeURIComponent(name);
		} catch {
			return name;
		}
	}
	/**
	* Détermine si une donnée est une URL complète ou juste un nom de média
	* @param data - Donnée à vérifier
	* @returns true si c'est une URL complète
	*/
	static isFullUrl(data) {
		if (!data || typeof data !== "string") return false;
		return data.startsWith("http://") || data.startsWith("https://") || data.startsWith("/") || data.includes("/");
	}
	/**
	* Construit l'URL complète à partir du nom du média ou retourne l'URL si déjà complète
	* @param mediaNameOrUrl - Nom du média ou URL complète
	* @param basePath - Chemin de base pour les médias (par défaut: /media/md/)
	* @returns URL complète
	*/
	static buildFullUrl(mediaNameOrUrl, basePath = "/media/md/") {
		if (this.isFullUrl(mediaNameOrUrl)) return mediaNameOrUrl;
		return `${basePath}${mediaNameOrUrl}`;
	}
	/**
	* Extrait le nom du média depuis un objet de données
	* @param dataItem - Objet de données qui peut contenir media, url, ou être une string
	* @returns Le nom du média
	*/
	static getMediaNameFromData(dataItem) {
		if (typeof dataItem === "string") return this.isFullUrl(dataItem) ? this.extractMediaName(dataItem) : dataItem;
		else if (dataItem && typeof dataItem === "object" && dataItem.media) return dataItem.media;
		else if (dataItem && typeof dataItem === "object" && dataItem.fileName) return dataItem.fileName;
		return "";
	}
	/**
	* Resolves a media name via the server-side fileNameHistory fallback.
	* Returns the current fileName if found, or null.
	*/
	static async resolveMediaName(mediaName) {
		try {
			const response = await fetch(`/admin/media/resolve/${encodeURIComponent(mediaName)}`);
			if (!response.ok) return null;
			return (await response.json()).fileName || null;
		} catch {
			return null;
		}
	}
	/**
	* Builds a human-readable message from a failed media upload response.
	* The endpoint answers `{ success: 0, error }` on failure; fall back to the
	* bare HTTP status when the body isn't that JSON (e.g. an HTML error page).
	*/
	static async uploadErrorMessage(response) {
		try {
			const data = await response.json();
			if (data && typeof data.error === "string" && data.error) return data.error;
		} catch {}
		return `HTTP ${response.status}`;
	}
	static buildFullUrlFromData(dataItem, basePath = "/media/md/") {
		if (typeof dataItem === "string") return this.buildFullUrl(dataItem, basePath);
		else if (dataItem && typeof dataItem === "object" && dataItem.url) return dataItem.url;
		else if (dataItem && typeof dataItem === "object" && dataItem.fileName) {
			const mediaName = dataItem.fileName;
			return this.buildFullUrl(mediaName, basePath);
		} else if (dataItem && typeof dataItem === "object" && dataItem.media) {
			const mediaName = dataItem.media;
			return this.buildFullUrl(mediaName, basePath);
		}
		return "";
	}
};
//#endregion
//#region src/assets/tools/Abstract/AbstractMediaTool.ts
var STATUS = {
	EMPTY: "empty",
	UPLOADING: "loading",
	FILLED: "filled"
};
/** Best-effort human string from an unknown throw value (Error, string, or neither). */
function toErrorMessage(error) {
	if (error instanceof Error) return error.message;
	if (typeof error === "string") return error;
	return "";
}
var AbstractMediaTool = class extends BaseTool {
	constructor({ api, config, readOnly, data }) {
		super({
			data,
			api,
			readOnly
		});
		this.uploadAccept = "";
		this.config = config;
		this.onSelectFile = config.onSelectFile;
		this.onUploadFile = config.onUploadFile;
		this.onMultiSelectFile = config.onMultiSelectFile;
		this.nodes = {
			wrapper: make$1.element("div", [this.api.styles.block, "image-tool"]),
			fileButton: this.createFileButton(),
			preloader: make$1.element("div", "image-tool__image-preloader")
		};
	}
	responsIsValid(response) {
		return response.success && !!response.file && !!response.file.media;
	}
	onFileLoading() {
		this.toggleStatus(STATUS.UPLOADING);
	}
	handleUploadError(error) {
		const toolName = this.constructor.name;
		logger.error(`${toolName}: uploading failed`, error);
		this.hidePreloader();
		const detail = toErrorMessage(error);
		const message = this.api.i18n.t("Échec du téléchargement de l'image. Veuillez réessayer.");
		this.api.notifier.show({
			message: detail ? `${message} (${detail})` : message,
			style: "error"
		});
	}
	showPreloader(src) {
		if (this.nodes.preloader && src) {
			this.nodes.preloader.style.backgroundImage = `url(${src})`;
			this.nodes.preloader.style.display = "block";
		}
		this.toggleStatus(STATUS.UPLOADING);
	}
	hidePreloader(status = STATUS.EMPTY) {
		if (this.nodes.preloader) {
			this.nodes.preloader.style.backgroundImage = "";
			this.nodes.preloader.style.display = "none";
		}
		this.toggleStatus(status);
	}
	/**
	* Utilitaire pour basculer le statut UI
	*/
	toggleStatus(status, baseClass = "image-tool", wrapper = null) {
		const wrapperElement = wrapper || this.nodes.wrapper;
		if (status === STATUS.UPLOADING) wrapperElement.classList.add(this.api.styles.loader);
		else wrapperElement.classList.remove(this.api.styles.loader);
		for (const statusValue of Object.values(STATUS)) wrapperElement.classList.toggle(`${baseClass}--${statusValue}`, status === statusValue);
	}
	createFileButton() {
		const buttonWrapper = make$1.element("div", [
			"flex",
			"cdx-input-labeled-preview",
			"cdx-input-labeled",
			"cdx-input",
			"cdx-input-editable",
			"cdx-input-gallery"
		]);
		const selectHandler = this.onMultiSelectFile ?? this.onSelectFile;
		const selectButton = make$1.element("div", [this.api.styles.button]);
		selectButton.innerHTML = folder_default + " " + this.api.i18n.t("Select");
		selectButton.addEventListener("click", (event) => {
			selectHandler(this, event);
		});
		buttonWrapper.appendChild(selectButton);
		const uploadButton = make$1.element("div", [this.api.styles.button]);
		uploadButton.innerHTML = `${upload_default} ${this.api.i18n.t("Upload")}`;
		uploadButton.style.marginLeft = "-2px";
		uploadButton.addEventListener("click", (event) => {
			this.onUploadFile(this, event);
		});
		buttonWrapper.appendChild(uploadButton);
		return buttonWrapper;
	}
	/**
	* Button that empties the block so another media can be picked: a filled block
	* hides its Select/Upload buttons, so without it the only way to change the
	* media is to delete the block and start over.
	*/
	createDeleteButton(onDelete) {
		return make$1.element("button", "media-tool__delete", {
			type: "button",
			title: this.api.i18n.t("Remove the media")
		}, B$2, (event) => {
			event.preventDefault();
			onDelete();
		});
	}
	/** Upload a file straight through the media endpoint, then fill the block. */
	async uploadFile(file) {
		this.onFileLoading();
		const formData = new FormData();
		formData.append("image", file);
		try {
			const response = await fetch("/admin/media/block", {
				method: "POST",
				body: formData
			});
			if (!response.ok) throw new Error(await MediaUtils.uploadErrorMessage(response));
			this.onUpload(await response.json());
		} catch (error) {
			this.handleUploadError(error);
		}
	}
};
//#endregion
//#region src/assets/tools/Image/Image.ts
var Image = class Image extends AbstractMediaTool {
	static get toolbox() {
		return {
			title: "Image",
			icon: _$1
		};
	}
	get media() {
		return this.data.media || this.data.file?.url || "";
	}
	constructor({ data, config, api, readOnly = false }) {
		super({
			api,
			config,
			readOnly,
			data
		});
		this.uploadAccept = "image/*";
		this.data = Image.normalizeData(data);
		this.nodes = {
			...this.nodes,
			imageContainer: make$1.element("div", "image-tool__image"),
			caption: make$1.element("div", [this.api.styles.input, "image-tool__caption"], { contentEditable: !this.readOnly }),
			deleteButton: this.createDeleteButton(() => this.removeMedia())
		};
	}
	/** Drop the picture, keeping the caption: the Select/Upload buttons come back. */
	removeMedia() {
		this.data.media = "";
		this.nodes.imageEl?.remove();
		delete this.nodes.imageEl;
		this.toggleStatus(STATUS.EMPTY);
	}
	static normalizeData(data) {
		return {
			media: data.media || MediaUtils.extractMediaName(data.file?.url || ""),
			caption: data.caption || data.file?.name || ""
		};
	}
	onUpload(response) {
		if (!this.responsIsValid(response)) return this.handleUploadError("incorrect response: " + JSON.stringify(response));
		this.data.media = response.file.media;
		if (response.file.name) this.data.caption = response.file.name;
		this.fillImage();
	}
	fillImage() {
		if (this.nodes.imageEl) this.nodes.imageEl.remove();
		const img = make$1.element("img", "image-tool__image-picture");
		img.src = MediaUtils.buildFullUrl(this.media);
		img.addEventListener("load", () => {
			this.hidePreloader(STATUS.FILLED);
		});
		img.addEventListener("error", async () => {
			const resolved = await MediaUtils.resolveMediaName(this.media);
			if (resolved && resolved !== this.media) {
				this.data.media = resolved;
				img.src = MediaUtils.buildFullUrl(resolved);
			}
		});
		this.nodes.imageEl = img;
		this.nodes.imageContainer.appendChild(img);
		this.fillCaption();
	}
	fillCaption() {
		this.nodes.caption.textContent = this.data.caption || "";
	}
	createImageInput() {
		/**
		* Create base structure comme dans AbstractUi
		*  <wrapper>
		*    <image-container>
		*      <image-preloader />
		*    </image-container>
		*    <caption />
		*    <select-file-button />
		*  </wrapper>
		*/
		this.nodes.caption.dataset.placeholder = this.api.i18n.t("Caption");
		this.nodes.imageContainer.appendChild(this.nodes.preloader);
		this.nodes.imageContainer.appendChild(this.nodes.deleteButton);
		this.nodes.wrapper.appendChild(this.nodes.imageContainer);
		this.nodes.wrapper.appendChild(this.nodes.caption);
		this.nodes.wrapper.appendChild(this.nodes.fileButton);
		return this.nodes.wrapper;
	}
	render() {
		const wrapper = this.createImageInput();
		if (!this.media) {
			this.toggleStatus(STATUS.EMPTY);
			return wrapper;
		}
		this.fillImage();
		return wrapper;
	}
	save(block) {
		if (!this.media) return {
			media: "",
			caption: ""
		};
		return {
			media: this.media,
			caption: this.nodes.caption.textContent?.trim() || block.querySelector(".image-tool__caption")?.textContent?.trim() || this.data.caption || ""
		};
	}
	validate() {
		return !!this.media;
	}
	static exportToMarkdown(data, tunes) {
		data = Image.normalizeData(data);
		if (!data.media) return "";
		let markdown = `![${data.caption || ""}](${data.media})`;
		if (tunes?.linkTune) markdown = MarkdownUtils.wrapWithLink(markdown, tunes);
		return tunes ? MarkdownUtils.addAttributes(markdown, tunes) : markdown;
	}
	/** Only a chunk that is the image alone: text around it would be dropped on import. */
	static isItMarkdownExported(markdown) {
		const chunk = markdown.trim();
		return /^!\[[^\]]*\]\([^)]+\)$/.test(chunk) || /^#?\[!\[[^\]]*\]\([^)]+\)\]\([^)]+\)(?:\{target="_blank"\})?$/.test(chunk);
	}
	static importFromMarkdown(editor, markdown) {
		let media = "";
		let caption = "";
		const result = MarkdownUtils.parseTunesFromMarkdown(markdown);
		const tunes = result.tunes;
		markdown = result.markdown;
		if (markdown.match(/#?\[!\[.*\]\(.+\)\]\(.+\)/)) {
			const imageAndLinkMatch = markdown.match(/(#?)\[!\[(.*)\]\((.*)\)]\((.*)\)({target="_blank"})?/);
			if (imageAndLinkMatch) {
				caption = imageAndLinkMatch[2] || "";
				media = imageAndLinkMatch[3] || "";
				tunes.linkTune = {
					url: imageAndLinkMatch[4] || "",
					targetBlank: imageAndLinkMatch[5] ? true : false,
					hideForBot: imageAndLinkMatch[1] ? true : false
				};
			}
		} else if (markdown.match(/!\[.*\]\(.+\)/)) {
			const imageMatch = markdown.match(/!\[(.*)\]\((.*)\)/);
			if (imageMatch) {
				caption = imageMatch[1] || "";
				media = imageMatch[2] || "";
			}
		}
		if (media.startsWith("/media/")) media = MediaUtils.extractMediaName(media);
		const block = editor.blocks.insert("image");
		editor.blocks.update(block.id, {
			media,
			caption
		}, tunes);
	}
	static get pasteConfig() {
		return {
			tags: ["img"],
			patterns: { image: /(https?:\/\/|\/media\/)\S+\.(gif|jpe?g|png|webp)$/i },
			files: { mimeTypes: ["image/*"] }
		};
	}
	onPaste(event) {
		if (event.type === "tag") {
			const img = event.detail.data;
			if (!img || !img.src) return;
			const url = img.src;
			this.data.media = url;
			this.data.caption = img.alt || "";
			this.fillImage();
			return;
		}
		if (event.type === "pattern") {
			const url = event.detail.data;
			if (!url) return;
			this.data.media = url;
			this.fillImage();
			return;
		}
		if (event.type === "file") {
			const file = event.detail?.file;
			if (file) this.uploadFile(file);
		}
	}
};
//#endregion
//#region src/assets/tools/Gallery/toolbox-icon.svg?raw
var toolbox_icon_default$1 = "<svg width=\"38\" height=\"18\" viewBox=\"0 0 38 18\" xmlns=\"http://www.w3.org/2000/svg\">\n    <mask id=\"mask0\" mask-type=\"alpha\" maskUnits=\"userSpaceOnUse\" x=\"10\" y=\"0\" width=\"18\" height=\"18\">\n        <path fill-rule=\"evenodd\" clip-rule=\"evenodd\" d=\"M28 16V2C28 0.9 27.1 0 26 0H12C10.9 0 10 0.9 10 2V16C10 17.1 10.9 18 12 18H26C27.1 18 28 17.1 28 16V16ZM15.5 10.5L18 13.51L21.5 9L26 15H12L15.5 10.5V10.5Z\" />\n    </mask>\n    <g mask=\"url(#mask0)\">\n        <rect x=\"10\" width=\"18\" height=\"18\" />\n    </g>\n    <mask id=\"mask1\" mask-type=\"alpha\" maskUnits=\"userSpaceOnUse\" x=\"0\" y=\"3\" width=\"7\" height=\"12\">\n        <path fill-rule=\"evenodd\" clip-rule=\"evenodd\" d=\"M7 13.59L2.67341 9L7 4.41L5.66802 3L0 9L5.66802 15L7 13.59Z\" fill=\"white\" />\n    </mask>\n    <g mask=\"url(#mask1)\">\n        <rect y=\"3\" width=\"7.55735\" height=\"12\" />\n    </g>\n    <mask id=\"mask2\" mask-type=\"alpha\" maskUnits=\"userSpaceOnUse\" x=\"31\" y=\"3\" width=\"7\" height=\"12\">\n        <path fill-rule=\"evenodd\" clip-rule=\"evenodd\" d=\"M31 13.59L35.3266 9L31 4.41L32.332 3L38 9L32.332 15L31 13.59Z\" fill=\"white\" />\n    </mask>\n    <g mask=\"url(#mask2)\">\n        <rect x=\"30.4426\" y=\"2.25\" width=\"7.55735\" height=\"13\" />\n    </g>\n</svg>";
//#endregion
//#region src/assets/tools/Gallery/Close.svg?raw
var Close_default = "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"16\" height=\"16\" fill=\"currentColor\"\n    class=\"bi bi-x-lg\" viewBox=\"0 0 16 16\">\n    <path\n        d=\"M2.146 2.854a.5.5 0 1 1 .708-.708L8 7.293l5.146-5.147a.5.5 0 0 1 .708.708L8.707 8l5.147 5.146a.5.5 0 0 1-.708.708L8 8.707l-5.146 5.147a.5.5 0 0 1-.708-.708L7.293 8 2.146 2.854Z\" />\n</svg>";
//#endregion
//#region src/assets/tools/Gallery/MoveLeft.svg?raw
var MoveLeft_default = "<svg class=\"icon \" viewBox=\"0 0 512 512\" xmlns=\"http://www.w3.org/2000/svg\">\n    <path\n        d=\"M351,9a15,15 0 01 19,0l29,29a15,15 0 01 0,19l-199,199l199,199a15,15 0 01 0,19l-29,29a15,15 0 01-19,0l-236-235a16,16 0 01 0-24z\" />\n</svg>";
//#endregion
//#region src/assets/tools/Gallery/MoveRight.svg?raw
var MoveRight_default = "<svg viewBox=\"0 0 512 512\" xmlns=\"http://www.w3.org/2000/svg\"><path d=\"M312,256l-199-199a15,15 0 01 0-19l29-29a15,15 0 01 19,0l236,235a16,16 0 01 0,24l-236,235a15,15 0 01-19,0l-29-29a15,15 0 01 0-19z\" /></svg>\n";
//#endregion
//#region src/assets/tools/Gallery/Gallery.ts
var Gallery = class Gallery extends AbstractMediaTool {
	static get toolbox() {
		return {
			title: "Gallery",
			icon: toolbox_icon_default$1
		};
	}
	constructor({ data, config, api, readOnly }) {
		super({
			api,
			config,
			readOnly,
			data
		});
		this.data = Gallery.normalizeData(data);
	}
	static normalizeData(data) {
		const normalizedItems = [];
		if (data && typeof data === "object" && "items" in data && Array.isArray(data.items)) {
			for (const item of data.items) {
				if (typeof item !== "object") continue;
				const media = item.media || (item.url ? MediaUtils.extractMediaName(item.url) : null) || item.file?.media;
				if (!media) continue;
				normalizedItems.push({
					media,
					caption: item.caption || ""
				});
			}
			return { items: normalizedItems };
		}
		if (!data || !Array.isArray(data)) return { items: [] };
		for (const item of data) if (typeof item === "string") normalizedItems.push({
			media: item,
			caption: ""
		});
		else if (typeof item === "object" && item !== null) {
			let media = null;
			if ("media" in item && item.media) media = item.media;
			else if ("url" in item && item.url) media = MediaUtils.extractMediaName(item.url);
			else if ("file" in item && item.file && "media" in item.file) media = item.file.media;
			if (media) normalizedItems.push({
				media,
				caption: item.caption || ""
			});
		}
		return { items: normalizedItems };
	}
	onMultiUpload(items) {
		this.save();
		for (const item of items) {
			if (this.data.items.some((existing) => existing.media === item.media)) continue;
			const fullUrl = item.url || MediaUtils.buildFullUrlFromData(item.media);
			const newBlock = this.createNewItem(fullUrl, item.name || "");
			this.nodeList.insertBefore(newBlock, this.nodes.fileButton);
			newBlock.querySelector(".cdxcarousel-item").style.setProperty("--bg-image-url", `url('${fullUrl}')`);
			this.data.items.push({
				media: item.media,
				caption: item.name || ""
			});
		}
	}
	onUpload(response) {
		if (!this.responsIsValid(response)) return this.handleUploadError("incorrect response: " + JSON.stringify(response));
		const mediaName = response.file.media || MediaUtils.extractMediaName(response.file.url);
		if (this.isMediaAlreadyInGallery(mediaName)) {
			this.handleDuplicateMediaError();
			return;
		}
		const itemElement = this.getLastGalleryItem();
		this._createImage(response.file.url || "", itemElement, response.file.name || "");
		this.data.items.push({
			media: mediaName,
			caption: response.file.name || ""
		});
		itemElement.classList.add("cdxcarousel-item--empty");
	}
	getLastGalleryItem() {
		if (!this.nodeList) throw new Error("nodeLis must be defined (render)");
		const lastItemIndex = this.nodeList.childNodes.length - 2;
		return this.nodeList.childNodes[lastItemIndex].firstChild;
	}
	/**
	* Vérifie si un média existe déjà dans la galerie
	*/
	isMediaAlreadyInGallery(mediaName) {
		this.save();
		return this.data.items.some((item) => item.media === mediaName);
	}
	/**
	* Gère l'erreur quand un média en double est ajouté
	*/
	handleDuplicateMediaError() {
		const block = this.getLastGalleryItem().closest(".cdxcarousel-block");
		if (block) block.remove();
		this.api.notifier.show({
			message: this.api.i18n.t("Ce média est déjà présent dans la galerie."),
			style: "error"
		});
		this.hidePreloader(STATUS.EMPTY);
	}
	updateData(data) {
		this.data = Gallery.normalizeData(data);
		this.render();
	}
	render() {
		this.nodes.wrapper.classList.add("cdxcarousel-wrapper");
		this.nodeList = make$1.element("div", ["cdxcarousel-list"]);
		this.nodeList.appendChild(this.nodes.fileButton);
		this.nodes.wrapper.appendChild(this.nodeList);
		for (const mediaData of this.data.items) {
			const fullUrl = MediaUtils.buildFullUrlFromData(mediaData.media);
			const loadItem = this.createNewItem(fullUrl, mediaData.caption);
			const imageContainer = loadItem.querySelector(".cdxcarousel-item");
			this.nodeList.insertBefore(loadItem, this.nodes.fileButton);
			imageContainer.style.setProperty("--bg-image-url", `url('${fullUrl}')`);
		}
		return this.nodes.wrapper;
	}
	createNewItem(url = "", caption = "") {
		const block = make$1.element("div", "cdxcarousel-block");
		const item = make$1.element("div", "cdxcarousel-item");
		const leftBtn = make$1.element("div", "cdxcarousel-leftBtn", { style: "padding: 8px" }, MoveLeft_default, () => {
			const parent = block.parentNode;
			if (!parent) return;
			const index = Array.from(parent.children).indexOf(block);
			if (index !== 0) {
				const previousSibling = parent.children[index - 1];
				if (previousSibling) parent.insertBefore(block, previousSibling);
			}
		});
		const rightBtn = make$1.element("div", "cdxcarousel-rightBtn", { style: "padding: 8px" }, MoveRight_default, () => {
			const parent = block.parentNode;
			if (!parent) return;
			const index = Array.from(parent.children).indexOf(block);
			if (index !== parent.children.length - 2) {
				const nextNextSibling = parent.children[index + 2];
				if (nextNextSibling) parent.insertBefore(block, nextNextSibling);
			}
		});
		const removeBtn = make$1.element("div", "cdxcarousel-removeBtn", { display: "none" }, Close_default, () => {
			block.remove();
		});
		item.appendChild(removeBtn);
		item.appendChild(leftBtn);
		item.appendChild(rightBtn);
		block.appendChild(item);
		if (url) this._createImage(url, item, caption);
		else {
			const imagePreloader = make$1.element("div", "image-tool__image-preloader");
			item.appendChild(imagePreloader);
		}
		return block;
	}
	/**
	* Create Image View
	*/
	_createImage(url, item, captionText = "") {
		const image = globalThis.document.createElement("img");
		image.src = url;
		image.addEventListener("error", async () => {
			const mediaName = MediaUtils.extractMediaName(image.src);
			const resolved = await MediaUtils.resolveMediaName(mediaName);
			if (resolved && resolved !== mediaName) {
				const newUrl = MediaUtils.buildFullUrl(resolved);
				image.src = newUrl;
				item.style.setProperty("--bg-image-url", `url('${newUrl}')`);
			}
		});
		const caption = make$1.element("div", ["image-tool__caption", this.api.styles.input], { contentEditable: true });
		if (captionText) caption.textContent = captionText;
		const placeholderText = this.api.i18n.t("Alternative text");
		caption.dataset.placeholder = placeholderText;
		const removeBtn = item.querySelector(".cdxcarousel-removeBtn");
		removeBtn.style.display = "flex";
		item.appendChild(image);
		item.appendChild(caption);
		item.style.setProperty("--bg-image-url", `url('${url}')`);
	}
	save() {
		if (!this.nodeList) return this.data;
		const newItems = [];
		this.nodeList.querySelectorAll(".cdxcarousel-block").forEach((item) => {
			const image = item.querySelector("img");
			const caption = item.querySelector(".image-tool__caption");
			if (image && image.src) {
				const mediaName = MediaUtils.extractMediaName(image.src);
				const captionText = caption?.textContent?.trim() || "";
				newItems.push({
					media: mediaName,
					caption: captionText
				});
			}
		});
		this.data = { items: newItems };
		return this.data;
	}
	onFileLoading() {
		super.onFileLoading();
		const newItem = this.createNewItem();
		this.nodeList.insertBefore(newItem, this.nodes.fileButton);
		this.hidePreloader(STATUS.EMPTY);
	}
	static exportToMarkdown(data, tunes) {
		data = Gallery.normalizeData(data);
		if (!data.items || data.items.length === 0) return "";
		const imagesObject = data.items.reduce((acc, item) => {
			acc[item.media] = item.caption || "";
			return acc;
		}, {});
		let markdown = `{{ gallery(${JSON.stringify(imagesObject)}`;
		if (tunes?.clickableTune?.value) markdown += `, clickable: true`;
		markdown += `) }}`;
		return MarkdownUtils.addAttributes(markdown, tunes);
	}
	static importFromMarkdown(editor, markdown) {
		const result = MarkdownUtils.parseTunesFromMarkdown(markdown);
		const tunes = result.tunes;
		const galleryMatch = result.markdown.match(/{{ gallery\(\s*(images:\s*)?(?<medias>\{.*?\})\s*(,\s*clickable:\s*(?<clickable>true|false))?\) }}/s);
		tunes.clickableTune = { value: [
			true,
			"true",
			"1"
		].includes(galleryMatch?.groups?.clickable || false) ? true : false };
		if (!galleryMatch || !Gallery.importGalleryFromJsonString(galleryMatch.groups?.medias || "{}", editor, tunes)) return Raw.importFromMarkdown(editor, markdown);
	}
	static parseGalleryData(jsonString) {
		try {
			return JSON.parse(jsonrepair(jsonString));
		} catch {
			return false;
		}
	}
	static importGalleryFromJsonString(jsonString, editor, tunes) {
		const galleryData = Gallery.parseGalleryData(jsonString);
		if (galleryData === false) return false;
		const galleryItems = Object.entries(galleryData).map(([media, caption]) => ({
			caption: String(caption),
			media: String(media)
		}));
		if (galleryItems.length > 0) {
			const block = editor.blocks.insert("gallery");
			const dataToUpdate = { items: galleryItems };
			editor.blocks.update(block.id, dataToUpdate, tunes);
			block.validate(dataToUpdate);
			block.dispatchChange();
			return true;
		}
		return false;
	}
	static isItMarkdownExported(markdown) {
		return markdown.trim().match(/{{ gallery\(\s*(images:\s*)?\{.*?\}\s*(,\s*clickable:\s*(true|false|0|1))?\) }}/s) !== null;
	}
};
//#endregion
//#region src/assets/tools/Table/utils/dom.ts
/**
* Helper for making Elements with attributes
*
* @param  {string} tagName           - new Element tag name
* @param  {string|string[]} classNames  - list or name of CSS classname(s)
* @param  {object} attributes        - any attributes
* @returns {Element}
*/
function make(tagName, classNames, attributes = {}) {
	const el = globalThis.document.createElement(tagName);
	if (Array.isArray(classNames)) el.classList.add(...classNames);
	else if (classNames) el.classList.add(classNames);
	for (const attrName in attributes) {
		if (!Object.prototype.hasOwnProperty.call(attributes, attrName)) continue;
		el[attrName] = attributes[attrName];
	}
	return el;
}
/**
* Get item position relative to document
*
* @param {HTMLElement} elem - item
* @returns {{x1: number, y1: number, x2: number, y2: number}} coordinates of the upper left (x1,y1) and lower right(x2,y2) corners
*/
function getCoords(elem) {
	const rect = elem.getBoundingClientRect();
	return {
		y1: Math.floor(rect.top + globalThis.window.pageYOffset),
		x1: Math.floor(rect.left + globalThis.window.pageXOffset),
		x2: Math.floor(rect.right + globalThis.window.pageXOffset),
		y2: Math.floor(rect.bottom + globalThis.window.pageYOffset)
	};
}
/**
* Calculate paddings of the first element relative to the second
*
* @param {HTMLElement} firstElem - outer element, if the second element is inside it, then all padding will be positive
* @param {HTMLElement} secondElem - inner element, if its borders go beyond the first, then the paddings will be considered negative
* @returns {{fromTopBorder: number, fromLeftBorder: number, fromRightBorder: number, fromBottomBorder: number}}
*/
function getRelativeCoordsOfTwoElems(firstElem, secondElem) {
	const firstCoords = getCoords(firstElem);
	const secondCoords = getCoords(secondElem);
	return {
		fromTopBorder: secondCoords.y1 - firstCoords.y1,
		fromLeftBorder: secondCoords.x1 - firstCoords.x1,
		fromRightBorder: firstCoords.x2 - secondCoords.x2,
		fromBottomBorder: firstCoords.y2 - secondCoords.y2
	};
}
/**
* Get the width and height of an element and the position of the cursor relative to it
*
* @param {HTMLElement} elem - element relative to which the coordinates will be calculated
* @param {Event} event - mouse event
*/
function getCursorPositionRelativeToElement(elem, event) {
	const { width, height, x, y } = elem.getBoundingClientRect();
	const { clientX, clientY } = event;
	return {
		width,
		height,
		x: clientX - x,
		y: clientY - y
	};
}
/**
* Insert element after the referenced
*
* @param {HTMLElement} newNode
* @param {HTMLElement} referenceNode
* @returns {HTMLElement}
*/
function insertBefore(newNode, referenceNode) {
	return referenceNode.parentNode.insertBefore(newNode, referenceNode);
}
/**
* Set focus to contenteditable or native input element
*
* @param {Element} element - element where to set focus
* @param {boolean} atStart - where to set focus: at the start or at the end
*
* @returns {void}
*/
function focus(element, atStart = true) {
	const range = globalThis.document.createRange();
	const selection = globalThis.window.getSelection();
	range.selectNodeContents(element);
	range.collapse(atStart);
	selection.removeAllRanges();
	selection.addRange(range);
}
//#endregion
//#region src/assets/tools/Table/utils/popover.ts
/**
* This cass provides a popover rendering
*/
var Popover = class Popover {
	/**
	* @param {object} options - constructor options
	* @param {PopoverItem[]} options.items - constructor options
	*/
	constructor({ items }) {
		this.items = items;
		this.wrapper = void 0;
		this.itemEls = [];
	}
	/**
	* Set of CSS classnames used in popover
	*
	* @returns {object}
	*/
	static get CSS() {
		return {
			popover: "tc-popover",
			popoverOpened: "tc-popover--opened",
			item: "tc-popover__item",
			itemHidden: "tc-popover__item--hidden",
			itemConfirmState: "tc-popover__item--confirm",
			itemIcon: "tc-popover__item-icon",
			itemLabel: "tc-popover__item-label"
		};
	}
	/**
	* Returns the popover element
	*
	* @returns {Element}
	*/
	render() {
		this.wrapper = make("div", Popover.CSS.popover);
		this.items.forEach((item, index) => {
			const itemEl = make("div", Popover.CSS.item);
			const icon = make("div", Popover.CSS.itemIcon, { innerHTML: item.icon });
			const label = make("div", Popover.CSS.itemLabel, { textContent: item.label });
			itemEl.dataset.index = String(index);
			itemEl.appendChild(icon);
			itemEl.appendChild(label);
			this.wrapper.appendChild(itemEl);
			this.itemEls.push(itemEl);
		});
		/**
		* Delegate click
		*/
		this.wrapper.addEventListener("click", (event) => {
			this.popoverClicked(event);
		});
		return this.wrapper;
	}
	/**
	* Popover wrapper click listener
	* Used to delegate clicks in items
	*
	* @returns {void}
	*/
	popoverClicked(event) {
		const clickedItem = event.target.closest(`.${Popover.CSS.item}`);
		/**
		* Clicks outside or between item
		*/
		if (!clickedItem) return;
		const clickedItemIndex = Number(clickedItem.dataset.index);
		const item = this.items[clickedItemIndex];
		if (!item) return;
		if (item.confirmationRequired && !this.hasConfirmationState(clickedItem)) {
			this.setConfirmationState(clickedItem);
			return;
		}
		item.onClick();
	}
	/**
	* Enable the confirmation state on passed item
	*
	* @returns {void}
	*/
	setConfirmationState(itemEl) {
		itemEl.classList.add(Popover.CSS.itemConfirmState);
	}
	/**
	* Disable the confirmation state on passed item
	*
	* @returns {void}
	*/
	clearConfirmationState(itemEl) {
		itemEl.classList.remove(Popover.CSS.itemConfirmState);
	}
	/**
	* Check if passed item has the confirmation state
	*
	* @returns {boolean}
	*/
	hasConfirmationState(itemEl) {
		return itemEl.classList.contains(Popover.CSS.itemConfirmState);
	}
	/**
	* Return an opening state
	*
	* @returns {boolean}
	*/
	get opened() {
		return this.wrapper.classList.contains(Popover.CSS.popoverOpened);
	}
	/**
	* Opens the popover
	*
	* @returns {void}
	*/
	open() {
		/**
		* If item provides 'hideIf()' method that returns true, hide item
		*/
		this.items.forEach((item, index) => {
			if (typeof item.hideIf === "function") this.itemEls[index]?.classList.toggle(Popover.CSS.itemHidden, item.hideIf());
		});
		this.wrapper.classList.add(Popover.CSS.popoverOpened);
	}
	/**
	* Closes the popover
	*
	* @returns {void}
	*/
	close() {
		this.wrapper.classList.remove(Popover.CSS.popoverOpened);
		this.itemEls.forEach((el) => {
			this.clearConfirmationState(el);
		});
	}
};
//#endregion
//#region src/assets/tools/Table/toolbox.ts
/**
* Toolbox is a menu for manipulation of rows/cols
*
* It contains toggler and Popover:
*   <toolbox>
*     <toolbox-toggler />
*     <popover />
*   <toolbox>
*/
var Toolbox = class Toolbox {
	/**
	* Creates toolbox buttons and toolbox menus
	*
	* @param {Object} config
	* @param {any} config.api - Editor.js api
	* @param {PopoverItem[]} config.items - Editor.js api
	* @param {function} config.onOpen - callback fired when the Popover is opening
	* @param {function} config.onClose - callback fired when the Popover is closing
	* @param {string} config.cssModifier - the modifier for the Toolbox. Allows to add some specific styles.
	*/
	constructor({ api, items, onOpen, onClose, cssModifier = "" }) {
		this.api = api;
		this.items = items;
		this.onOpen = onOpen;
		this.onClose = onClose;
		this.cssModifier = cssModifier;
		this.popover = null;
		this.wrapper = this.createToolbox();
		this.numberOfColumns = 0;
		this.numberOfRows = 0;
		this.currentColumn = 0;
		this.currentRow = 0;
	}
	/**
	* Style classes
	*/
	static get CSS() {
		return {
			toolbox: "tc-toolbox",
			toolboxShowed: "tc-toolbox--showed",
			toggler: "tc-toolbox__toggler"
		};
	}
	/**
	* Returns rendered Toolbox element
	*/
	get element() {
		return this.wrapper;
	}
	/**
	* Creating a toolbox to open menu for a manipulating columns
	*
	* @returns {Element}
	*/
	createToolbox() {
		const wrapper = make("div", [Toolbox.CSS.toolbox, this.cssModifier ? `${Toolbox.CSS.toolbox}--${this.cssModifier}` : ""]);
		wrapper.dataset.mutationFree = "true";
		const popover = this.createPopover();
		const toggler = this.createToggler();
		wrapper.appendChild(toggler);
		wrapper.appendChild(popover);
		return wrapper;
	}
	/**
	* Creates the Toggler
	*
	* @returns {Element}
	*/
	createToggler() {
		const toggler = make("div", Toolbox.CSS.toggler, { innerHTML: O$2 });
		toggler.addEventListener("click", () => {
			this.togglerClicked();
		});
		return toggler;
	}
	/**
	* Creates the Popover instance and render it
	*
	* @returns {Element}
	*/
	createPopover() {
		this.popover = new Popover({ items: this.items });
		return this.popover.render();
	}
	/**
	* Toggler click handler. Opens/Closes the popover
	*
	* @returns {void}
	*/
	togglerClicked() {
		const styles = {};
		if (this.currentColumn > Math.ceil(this.numberOfColumns / 2)) {
			styles.right = "var(--popover-margin)";
			styles.left = "auto";
		} else {
			styles.left = "var(--popover-margin)";
			styles.right = "auto";
		}
		if (this.currentRow > Math.ceil(this.numberOfRows / 2)) {
			styles.bottom = "0";
			styles.top = "auto";
		} else {
			styles.top = "0";
			styles.bottom = "auto";
		}
		/**
		* Set 'top','bottom' style
		* Set 'left','right' style
		*/
		Object.entries(styles).forEach(([prop, value]) => {
			this.popover.wrapper.style[prop] = value;
		});
		if (this.popover.opened) {
			this.popover.close();
			this.onClose();
		} else {
			this.popover.open();
			this.onOpen();
		}
	}
	/**
	* Shows the Toolbox
	*
	* @param {function} computePositionMethod - method that returns the position coordinate
	* @returns {void}
	*/
	show(computePositionMethod) {
		const position = computePositionMethod();
		/**
		* Set 'top' or 'left' style
		*/
		Object.entries(position.style).forEach(([prop, value]) => {
			this.wrapper.style[prop] = value;
		});
		if (this.cssModifier === "row") {
			this.numberOfRows = position.numberOfRows ?? 0;
			this.currentRow = position.currentRow ?? 0;
		} else if (this.cssModifier === "column") {
			this.numberOfColumns = position.numberOfColumns ?? 0;
			this.currentColumn = position.currentColumn ?? 0;
		}
		this.wrapper.classList.add(Toolbox.CSS.toolboxShowed);
	}
	/**
	* Hides the Toolbox
	*
	* @returns {void}
	*/
	hide() {
		this.popover.close();
		this.wrapper.classList.remove(Toolbox.CSS.toolboxShowed);
	}
};
//#endregion
//#region src/assets/tools/Table/utils/throttled.ts
/**
* Limits the frequency of calling a function
*
* @param {number} delay - delay between calls in milliseconds
* @param {function} fn - function to be throttled
*/
function throttled(delay, fn) {
	let lastCall = 0;
	return function(...args) {
		const now = (/* @__PURE__ */ new Date()).getTime();
		if (now - lastCall < delay) return;
		lastCall = now;
		return fn(...args);
	};
}
//#endregion
//#region src/assets/tools/Table/table.ts
var CSS = {
	wrapper: "tc-wrap",
	wrapperReadOnly: "tc-wrap--readonly",
	table: "tc-table",
	row: "tc-row",
	withHeadings: "tc-table--heading",
	rowSelected: "tc-row--selected",
	cell: "tc-cell",
	cellSelected: "tc-cell--selected",
	cellColspan: "tc-cell--colspan",
	addRow: "tc-add-row",
	addRowDisabled: "tc-add-row--disabled",
	addColumn: "tc-add-column",
	addColumnDisabled: "tc-add-column--disabled"
};
/**
* Generates and manages table contents.
*/
var Table = class {
	/**
	* Creates
	*
	* @constructor
	* @param {boolean} readOnly - read-only mode flag
	* @param {object} api - Editor.js API
	* @param {TableData} data - Editor.js API
	* @param {TableConfig} config - Editor.js API
	*/
	constructor(readOnly, api, data, config) {
		this.readOnly = readOnly;
		this.api = api;
		this.data = data;
		this.config = config;
		this.columnAlignments = [];
		/**
		* DOM nodes
		*/
		this.wrapper = null;
		this.table = null;
		/**
		* Toolbox for managing of columns
		*/
		this.toolboxColumn = this.createColumnToolbox();
		this.toolboxRow = this.createRowToolbox();
		/**
		* Create table and wrapper elements
		*/
		this.createTableWrapper();
		this.hoveredRow = 0;
		this.hoveredColumn = 0;
		this.selectedRow = 0;
		this.selectedColumn = 0;
		this.tunes = { withHeadings: false };
		/**
		* Resize table to match config/data size
		*/
		this.resize();
		/**
		* Fill the table with data
		*/
		this.fill();
		/**
		* The cell in which the focus is currently located, if 0 and 0 then there is no focus
		* Uses to switch between cells with buttons
		*/
		this.focusedCell = {
			row: 0,
			column: 0
		};
		/**
		* Global click listener allows to delegate clicks on some elements
		*/
		this.documentClicked = (event) => {
			const clickedInsideTable = event.target.closest(`.${CSS.table}`) !== null;
			const outsideTableClicked = event.target.closest(`.${CSS.wrapper}`) === null;
			if (clickedInsideTable || outsideTableClicked) this.hideToolboxes();
			const clickedOnAddRowButton = event.target.closest(`.${CSS.addRow}`);
			const clickedOnAddColumnButton = event.target.closest(`.${CSS.addColumn}`);
			/**
			* Also, check if clicked in current table, not other (because documentClicked bound to the whole document)
			*/
			if (clickedOnAddRowButton && clickedOnAddRowButton.parentNode === this.wrapper) {
				this.addRow(void 0, true);
				this.hideToolboxes();
			} else if (clickedOnAddColumnButton && clickedOnAddColumnButton.parentNode === this.wrapper) {
				this.addColumn(void 0, true);
				this.hideToolboxes();
			}
		};
		if (!this.readOnly) this.bindEvents();
	}
	/**
	* Returns the rendered table wrapper
	*
	* @returns {Element}
	*/
	getWrapper() {
		return this.wrapper;
	}
	/**
	* Hangs the necessary handlers to events
	*/
	bindEvents() {
		globalThis.document.addEventListener("click", this.documentClicked);
		this.table.addEventListener("mousemove", throttled(150, (event) => this.onMouseMoveInTable(event)), { passive: true });
		this.table.onkeypress = (event) => this.onKeyPressListener(event);
		this.table.addEventListener("keydown", (event) => this.onKeyDownListener(event));
		this.table.addEventListener("focusin", (event) => this.focusInTableListener(event));
		this.table.addEventListener("input", () => this.updateColspanMarkers());
	}
	/**
	* Configures and creates the toolbox for manipulating with columns
	*
	* @returns {Toolbox}
	*/
	createColumnToolbox() {
		return new Toolbox({
			api: this.api,
			cssModifier: "column",
			items: [
				{
					label: this.api.i18n.t("Add column to left"),
					icon: y$2,
					hideIf: () => {
						return this.numberOfColumns === this.config.maxCols;
					},
					onClick: () => {
						this.addColumn(this.selectedColumn, true);
						this.hideToolboxes();
					}
				},
				{
					label: this.api.i18n.t("Add column to right"),
					icon: Z$2,
					hideIf: () => {
						return this.numberOfColumns === this.config.maxCols;
					},
					onClick: () => {
						this.addColumn(this.selectedColumn + 1, true);
						this.hideToolboxes();
					}
				},
				{
					label: this.api.i18n.t("Align left"),
					icon: d,
					onClick: () => {
						this.setColumnAlignment(this.selectedColumn, "left");
						this.hideToolboxes();
					}
				},
				{
					label: this.api.i18n.t("Align center"),
					icon: l$1,
					onClick: () => {
						this.setColumnAlignment(this.selectedColumn, "center");
						this.hideToolboxes();
					}
				},
				{
					label: this.api.i18n.t("Align right"),
					icon: k$2,
					onClick: () => {
						this.setColumnAlignment(this.selectedColumn, "right");
						this.hideToolboxes();
					}
				},
				{
					label: this.api.i18n.t("Delete column"),
					icon: B$2,
					hideIf: () => {
						return this.numberOfColumns === 1;
					},
					confirmationRequired: true,
					onClick: () => {
						this.deleteColumn(this.selectedColumn);
						this.hideToolboxes();
					}
				}
			],
			onOpen: () => {
				this.selectColumn(this.hoveredColumn);
				this.hideRowToolbox();
			},
			onClose: () => {
				this.unselectColumn();
			}
		});
	}
	/**
	* Configures and creates the toolbox for manipulating with rows
	*
	* @returns {Toolbox}
	*/
	createRowToolbox() {
		return new Toolbox({
			api: this.api,
			cssModifier: "row",
			items: [
				{
					label: this.api.i18n.t("Add row above"),
					icon: D$2,
					hideIf: () => {
						return this.numberOfRows === this.config.maxRows;
					},
					onClick: () => {
						this.addRow(this.selectedRow, true);
						this.hideToolboxes();
					}
				},
				{
					label: this.api.i18n.t("Add row below"),
					icon: j$2,
					hideIf: () => {
						return this.numberOfRows === this.config.maxRows;
					},
					onClick: () => {
						this.addRow(this.selectedRow + 1, true);
						this.hideToolboxes();
					}
				},
				{
					label: this.api.i18n.t("Delete row"),
					icon: B$2,
					hideIf: () => {
						return this.numberOfRows === 1;
					},
					confirmationRequired: true,
					onClick: () => {
						this.deleteRow(this.selectedRow);
						this.hideToolboxes();
					}
				}
			],
			onOpen: () => {
				this.selectRow(this.hoveredRow);
				this.hideColumnToolbox();
			},
			onClose: () => {
				this.unselectRow();
			}
		});
	}
	/**
	* When you press enter it moves the cursor down to the next row
	* or creates it if the click occurred on the last one
	*/
	moveCursorToNextRow() {
		if (this.focusedCell.row !== this.numberOfRows) {
			this.focusedCell.row += 1;
			this.focusCell(this.focusedCell);
		} else {
			this.addRow();
			this.focusedCell.row += 1;
			this.focusCell(this.focusedCell);
			this.updateToolboxesPosition(0, 0);
		}
	}
	/**
	* Get table cell by row and col index
	*
	* @param {number} row - cell row coordinate
	* @param {number} column - cell column coordinate
	* @returns {HTMLElement}
	*/
	getCell(row, column) {
		return this.table.querySelectorAll(`.${CSS.row}:nth-child(${row}) .${CSS.cell}`)[column - 1];
	}
	/**
	* Get table row by index
	*
	* @param {number} row - row coordinate
	* @returns {HTMLElement}
	*/
	getRow(row) {
		return this.table.querySelector(`.${CSS.row}:nth-child(${row})`);
	}
	/**
	* The parent of the cell which is the row
	*
	* @param {HTMLElement} cell - cell element
	* @returns {HTMLElement}
	*/
	getRowByCell(cell) {
		return cell.parentElement;
	}
	/**
	* Ger row's first cell
	*
	* @param {Element} row - row to find its first cell
	* @returns {Element}
	*/
	getRowFirstCell(row) {
		return row.querySelector(`.${CSS.cell}:first-child`);
	}
	/**
	* Set the sell's content by row and column numbers
	*
	* @param {number} row - cell row coordinate
	* @param {number} column - cell column coordinate
	* @param {string} content - cell HTML content
	*/
	setCellContent(row, column, content) {
		const cell = this.getCell(row, column);
		cell.innerHTML = content;
	}
	/**
	* Add column in table on index place
	* Add cells in each row
	*
	* @param {number} columnIndex - number in the array of columns, where new column to insert, -1 if insert at the end
	* @param {boolean} [setFocus] - pass true to focus the first cell
	*/
	addColumn(columnIndex = -1, setFocus = false) {
		const numberOfColumns = this.numberOfColumns;
		/**
		* Check if the number of columns has reached the maximum allowed columns specified in the configuration,
		* and if so, exit the function to prevent adding more columns beyond the limit.
		*/
		if (this.config && this.config.maxCols && this.numberOfColumns >= this.config.maxCols) return;
		/**
		* Iterate all rows and add a new cell to them for creating a column
		*/
		for (let rowIndex = 1; rowIndex <= this.numberOfRows; rowIndex++) {
			const cellElem = this.createCell();
			if (columnIndex > 0 && columnIndex <= numberOfColumns) insertBefore(cellElem, this.getCell(rowIndex, columnIndex));
			else this.getRow(rowIndex).appendChild(cellElem);
			/**
			* Autofocus first cell
			*/
			if (rowIndex === 1) {
				const firstCell = this.getCell(rowIndex, columnIndex > 0 ? columnIndex : numberOfColumns + 1);
				if (firstCell && setFocus) focus(firstCell);
			}
		}
		const addColButton = this.wrapper.querySelector(`.${CSS.addColumn}`);
		if (this.config?.maxCols && this.numberOfColumns > this.config.maxCols - 1 && addColButton) addColButton.classList.add(CSS.addColumnDisabled);
		const alignmentInsertAt = columnIndex > 0 && columnIndex <= numberOfColumns ? columnIndex - 1 : numberOfColumns;
		this.columnAlignments.splice(alignmentInsertAt, 0, "");
		this.applyColumnAlignments();
		this.addHeadingAttrToFirstRow();
		this.updateColspanMarkers();
	}
	/**
	* Add row in table on index place
	*
	* @param {number} index - number in the array of rows, where new column to insert, -1 if insert at the end
	* @param {boolean} [setFocus] - pass true to focus the inserted row
	* @returns {HTMLElement} row
	*/
	addRow(index = -1, setFocus = false) {
		let insertedRow;
		const rowElem = make("div", CSS.row);
		if (this.tunes.withHeadings) this.removeHeadingAttrFromFirstRow();
		/**
		* We remember the number of columns, because it is calculated
		* by the number of cells in the first row
		* It is necessary that the first line is filled in correctly
		*/
		const numberOfColumns = this.numberOfColumns;
		/**
		* Check if the number of rows has reached the maximum allowed rows specified in the configuration,
		* and if so, exit the function to prevent adding more columns beyond the limit.
		*/
		if (this.config && this.config.maxRows && this.numberOfRows >= this.config.maxRows) return;
		if (index > 0 && index <= this.numberOfRows) insertedRow = insertBefore(rowElem, this.getRow(index));
		else insertedRow = this.table.appendChild(rowElem);
		this.fillRow(insertedRow, numberOfColumns);
		if (this.tunes.withHeadings) this.addHeadingAttrToFirstRow();
		const insertedRowFirstCell = this.getRowFirstCell(insertedRow);
		if (insertedRowFirstCell && setFocus) focus(insertedRowFirstCell);
		const addRowButton = this.wrapper.querySelector(`.${CSS.addRow}`);
		if (this.config && this.config.maxRows && this.numberOfRows >= this.config.maxRows && addRowButton) addRowButton.classList.add(CSS.addRowDisabled);
		this.applyColumnAlignments();
		return insertedRow;
	}
	/**
	* Delete a column by index
	*
	* @param {number} index
	*/
	deleteColumn(index) {
		for (let i = 1; i <= this.numberOfRows; i++) {
			const cell = this.getCell(i, index);
			if (!cell) return;
			cell.remove();
		}
		const addColButton = this.wrapper.querySelector(`.${CSS.addColumn}`);
		if (addColButton) addColButton.classList.remove(CSS.addColumnDisabled);
		this.columnAlignments.splice(index - 1, 1);
		this.applyColumnAlignments();
		this.updateColspanMarkers();
	}
	/**
	* Delete a row by index
	*
	* @param {number} index
	*/
	deleteRow(index) {
		this.getRow(index).remove();
		const addRowButton = this.wrapper.querySelector(`.${CSS.addRow}`);
		if (addRowButton) addRowButton.classList.remove(CSS.addRowDisabled);
		this.addHeadingAttrToFirstRow();
	}
	/**
	* Create a wrapper containing a table, toolboxes
	* and buttons for adding rows and columns
	*
	* @returns {HTMLElement} wrapper - where all buttons for a table and the table itself will be
	*/
	createTableWrapper() {
		this.wrapper = make("div", CSS.wrapper);
		this.table = make("div", CSS.table);
		if (this.readOnly) this.wrapper.classList.add(CSS.wrapperReadOnly);
		this.wrapper.appendChild(this.toolboxRow.element);
		this.wrapper.appendChild(this.toolboxColumn.element);
		this.wrapper.appendChild(this.table);
		if (!this.readOnly) {
			const addColumnButton = make("div", CSS.addColumn, { innerHTML: o1 });
			const addRowButton = make("div", CSS.addRow, { innerHTML: o1 });
			this.wrapper.appendChild(addColumnButton);
			this.wrapper.appendChild(addRowButton);
		}
	}
	/**
	* Returns the size of the table based on initial data or config "size" property
	*
	* @return {{rows: number, cols: number}} - number of cols and rows
	*/
	computeInitialSize() {
		const content = this.data && this.data.content;
		const isValidArray = Array.isArray(content);
		const isNotEmptyArray = isValidArray ? content.length : false;
		const contentRows = isValidArray ? content.length : void 0;
		const contentCols = isNotEmptyArray ? content[0].length : void 0;
		const parsedRows = Number.parseInt(this.config && this.config.rows);
		const parsedCols = Number.parseInt(this.config && this.config.cols);
		return {
			rows: contentRows || (!isNaN(parsedRows) && parsedRows > 0 ? parsedRows : void 0) || 2,
			cols: contentCols || (!isNaN(parsedCols) && parsedCols > 0 ? parsedCols : void 0) || 2
		};
	}
	/**
	* Resize table to match config size or transmitted data size
	*
	* @return {{rows: number, cols: number}} - number of cols and rows
	*/
	resize() {
		const { rows, cols } = this.computeInitialSize();
		for (let i = 0; i < rows; i++) this.addRow();
		for (let i = 0; i < cols; i++) this.addColumn();
	}
	/**
	* Fills the table with data passed to the constructor
	*
	* @returns {void}
	*/
	fill() {
		const data = this.data;
		if (data && data.content) for (let i = 0; i < data.content.length; i++) {
			const rowContent = data.content[i];
			for (let j = 0; j < rowContent.length; j++) this.setCellContent(i + 1, j + 1, rowContent[j] ?? "");
		}
		if (Array.isArray(data && data.columnAlignments)) {
			this.columnAlignments = [];
			for (let c = 0; c < this.numberOfColumns; c++) this.columnAlignments.push(data.columnAlignments[c] || "");
			this.applyColumnAlignments();
		}
		this.updateColspanMarkers();
	}
	/**
	* Renders `->` colspan markers as real spanning cells: a cell preceding one or
	* more `->` cells spans across them so its content fills the merged width, while
	* the (transparent) marker cells overlap the spanned area and stay clickable for
	* editing/removing the `->`. A marker in the first column is ignored — it has no
	* cell to merge into, matching the markdown renderer.
	*
	* Spanning needs an explicit, equal track count (`--table-cols`); `auto-fit`
	* would recompute the tracks per row once cells start spanning and misalign the
	* columns across rows.
	*
	* @returns {void}
	*/
	updateColspanMarkers() {
		if (!this.table) return;
		this.table.style.setProperty("--table-cols", String(this.numberOfColumns));
		this.table.querySelectorAll(`.${CSS.row}`).forEach((row) => {
			const cells = Array.from(row.querySelectorAll(`.${CSS.cell}`));
			cells.forEach((cell, index) => {
				const isMarker = index > 0 && cell.textContent.trim() === "->";
				cell.classList.toggle(CSS.cellColspan, isMarker);
				cell.style.gridColumnStart = String(index + 1);
				if (isMarker) {
					cell.style.gridColumnEnd = "";
					return;
				}
				let run = 0;
				for (let next = index + 1; next < cells.length && cells[next].textContent.trim() === "->"; next++) run++;
				cell.style.gridColumnEnd = run > 0 ? `span ${run + 1}` : "";
			});
		});
	}
	/**
	* Set (or toggle off) the GFM alignment of a column and re-render it.
	*
	* @param {number} column - 1-based column index
	* @param {string} alignment - 'left' | 'center' | 'right'
	*/
	setColumnAlignment(column, alignment) {
		if (column <= 0) return;
		const current = this.columnAlignments[column - 1] || "";
		this.columnAlignments[column - 1] = current === alignment ? "" : alignment;
		this.applyColumnAlignments();
	}
	/**
	* Apply each column's alignment to its cells as inline `text-align`.
	*
	* @returns {void}
	*/
	applyColumnAlignments() {
		for (let row = 1; row <= this.numberOfRows; row++) for (let column = 1; column <= this.numberOfColumns; column++) {
			const cell = this.getCell(row, column);
			if (cell) cell.style.textAlign = this.columnAlignments[column - 1] || "";
		}
	}
	/**
	* Collect per-column alignment normalized to the current column count.
	*
	* @returns {string[]}
	*/
	getColumnAlignments() {
		const alignments = [];
		for (let column = 0; column < this.numberOfColumns; column++) alignments.push(this.columnAlignments[column] || "");
		return alignments;
	}
	/**
	* Fills a row with cells
	*
	* @param {HTMLElement} row - row to fill
	* @param {number} numberOfColumns - how many cells should be in a row
	*/
	fillRow(row, numberOfColumns) {
		for (let i = 1; i <= numberOfColumns; i++) {
			const newCell = this.createCell();
			row.appendChild(newCell);
		}
	}
	/**
	* Creating a cell element
	*
	* @return {Element}
	*/
	createCell() {
		return make("div", CSS.cell, { contentEditable: !this.readOnly });
	}
	/**
	* Get number of rows in the table
	*/
	get numberOfRows() {
		return this.table.childElementCount;
	}
	/**
	* Get number of columns in the table
	*/
	get numberOfColumns() {
		if (this.numberOfRows) return this.table.querySelectorAll(`.${CSS.row}:first-child .${CSS.cell}`).length;
		return 0;
	}
	/**
	* Is the column toolbox menu displayed or not
	*
	* @returns {boolean}
	*/
	get isColumnMenuShowing() {
		return this.selectedColumn !== 0;
	}
	/**
	* Is the row toolbox menu displayed or not
	*
	* @returns {boolean}
	*/
	get isRowMenuShowing() {
		return this.selectedRow !== 0;
	}
	/**
	* Recalculate position of toolbox icons
	*
	* @param {Event} event - mouse move event
	*/
	onMouseMoveInTable(event) {
		const { row, column } = this.getHoveredCell(event);
		this.hoveredColumn = column;
		this.hoveredRow = row;
		this.updateToolboxesPosition();
	}
	/**
	* Prevents default Enter behaviors
	* Adds Shift+Enter processing
	*
	* @param {KeyboardEvent} event - keypress event
	*/
	onKeyPressListener(event) {
		if (event.key === "Enter") {
			if (event.shiftKey) return true;
			this.moveCursorToNextRow();
		}
		return event.key !== "Enter";
	}
	/**
	* Prevents tab keydown event from bubbling
	* so that it only works inside the table
	*
	* @param {KeyboardEvent} event - keydown event
	*/
	onKeyDownListener(event) {
		if (event.key === "Tab" || event.key === "Backspace") event.stopPropagation();
	}
	/**
	* Set the coordinates of the cell that the focus has moved to
	*
	* @param {FocusEvent} event - focusin event
	*/
	focusInTableListener(event) {
		const cell = event.target;
		const row = this.getRowByCell(cell);
		this.focusedCell = {
			row: Array.from(this.table.querySelectorAll(`.${CSS.row}`)).indexOf(row) + 1,
			column: Array.from(row.querySelectorAll(`.${CSS.cell}`)).indexOf(cell) + 1
		};
	}
	/**
	* Unselect row/column
	* Close toolbox menu
	* Hide toolboxes
	*
	* @returns {void}
	*/
	hideToolboxes() {
		this.hideRowToolbox();
		this.hideColumnToolbox();
		this.updateToolboxesPosition();
	}
	/**
	* Unselect row, close toolbox
	*
	* @returns {void}
	*/
	hideRowToolbox() {
		this.unselectRow();
		this.toolboxRow.hide();
	}
	/**
	* Unselect column, close toolbox
	*
	* @returns {void}
	*/
	hideColumnToolbox() {
		this.unselectColumn();
		this.toolboxColumn.hide();
	}
	/**
	* Set the cursor focus to the focused cell
	*
	* @returns {void}
	*/
	focusCell(..._args) {
		this.focusedCellElem.focus();
	}
	/**
	* Get current focused element
	*
	* @returns {HTMLElement} - focused cell
	*/
	get focusedCellElem() {
		const { row, column } = this.focusedCell;
		return this.getCell(row, column);
	}
	/**
	* Update toolboxes position
	*
	* @param {number} row - hovered row
	* @param {number} column - hovered column
	*/
	updateToolboxesPosition(row = this.hoveredRow, column = this.hoveredColumn) {
		if (!this.isColumnMenuShowing) {
			if (column > 0 && column <= this.numberOfColumns) this.toolboxColumn.show(() => {
				return {
					style: { left: `calc((100% - var(--cell-size)) / (${this.numberOfColumns} * 2) * (1 + (${column} - 1) * 2))` },
					numberOfColumns: this.numberOfColumns,
					currentColumn: column
				};
			});
		}
		if (!this.isRowMenuShowing) {
			if (row > 0 && row <= this.numberOfRows) this.toolboxRow.show(() => {
				const hoveredRowElement = this.getRow(row);
				const { fromTopBorder } = getRelativeCoordsOfTwoElems(this.table, hoveredRowElement);
				const { height } = hoveredRowElement.getBoundingClientRect();
				return {
					style: { top: `${Math.ceil(fromTopBorder + height / 2)}px` },
					numberOfRows: this.numberOfRows,
					currentRow: row
				};
			});
		}
	}
	/**
	* Makes the first row headings
	*
	* @param {boolean} withHeadings - use headings row or not
	*/
	setHeadingsSetting(withHeadings) {
		this.tunes.withHeadings = withHeadings;
		if (withHeadings) {
			this.table.classList.add(CSS.withHeadings);
			this.addHeadingAttrToFirstRow();
		} else {
			this.table.classList.remove(CSS.withHeadings);
			this.removeHeadingAttrFromFirstRow();
		}
	}
	/**
	* Adds an attribute for displaying the placeholder in the cell
	*/
	addHeadingAttrToFirstRow() {
		for (let cellIndex = 1; cellIndex <= this.numberOfColumns; cellIndex++) {
			const cell = this.getCell(1, cellIndex);
			if (cell) cell.setAttribute("heading", this.api.i18n.t("Heading"));
		}
	}
	/**
	* Removes an attribute for displaying the placeholder in the cell
	*/
	removeHeadingAttrFromFirstRow() {
		for (let cellIndex = 1; cellIndex <= this.numberOfColumns; cellIndex++) {
			const cell = this.getCell(1, cellIndex);
			if (cell) cell.removeAttribute("heading");
		}
	}
	/**
	* Add effect of a selected row
	*
	* @param {number} index
	*/
	selectRow(index) {
		const row = this.getRow(index);
		if (row) {
			this.selectedRow = index;
			row.classList.add(CSS.rowSelected);
		}
	}
	/**
	* Remove effect of a selected row
	*/
	unselectRow() {
		if (this.selectedRow <= 0) return;
		const row = this.table.querySelector(`.${CSS.rowSelected}`);
		if (row) row.classList.remove(CSS.rowSelected);
		this.selectedRow = 0;
	}
	/**
	* Add effect of a selected column
	*
	* @param {number} index
	*/
	selectColumn(index) {
		for (let i = 1; i <= this.numberOfRows; i++) {
			const cell = this.getCell(i, index);
			if (cell) cell.classList.add(CSS.cellSelected);
		}
		this.selectedColumn = index;
	}
	/**
	* Remove effect of a selected column
	*/
	unselectColumn() {
		if (this.selectedColumn <= 0) return;
		const cells = this.table.querySelectorAll(`.${CSS.cellSelected}`);
		Array.from(cells).forEach((column) => {
			column.classList.remove(CSS.cellSelected);
		});
		this.selectedColumn = 0;
	}
	/**
	* Calculates the row and column that the cursor is currently hovering over
	* The search was optimized from O(n) to O (log n) via bin search to reduce the number of calculations
	*
	* @param {Event} event - mousemove event
	* @returns hovered cell coordinates as an integer row and column
	*/
	getHoveredCell(event) {
		let hoveredRow = this.hoveredRow;
		let hoveredColumn = this.hoveredColumn;
		const { width, height, x, y } = getCursorPositionRelativeToElement(this.table, event);
		if (x >= 0) hoveredColumn = this.binSearch(this.numberOfColumns, (mid) => this.getCell(1, mid), (coords) => x < coords.fromLeftBorder, (coords) => x > width - coords.fromRightBorder);
		if (y >= 0) hoveredRow = this.binSearch(this.numberOfRows, (mid) => this.getCell(mid, 1), (coords) => y < coords.fromTopBorder, (coords) => y > height - coords.fromBottomBorder);
		return {
			row: hoveredRow || this.hoveredRow,
			column: hoveredColumn || this.hoveredColumn
		};
	}
	/**
	* Looks for the index of the cell the mouse is hovering over.
	* Cells can be represented as ordered intervals with left and
	* right (upper and lower for rows) borders inside the table, if the mouse enters it, then this is our index
	*
	* @param {number} numberOfCells - upper bound of binary search
	* @param {function} getCell - function to take the currently viewed cell
	* @param {function} beforeTheLeftBorder - determines the cursor position, to the left of the cell or not
	* @param {function} afterTheRightBorder - determines the cursor position, to the right of the cell or not
	* @returns {number}
	*/
	binSearch(numberOfCells, getCell, beforeTheLeftBorder, afterTheRightBorder) {
		let leftBorder = 0;
		let rightBorder = numberOfCells + 1;
		let totalIterations = 0;
		let mid;
		while (leftBorder < rightBorder - 1 && totalIterations < 10) {
			mid = Math.ceil((leftBorder + rightBorder) / 2);
			const cell = getCell(mid);
			const relativeCoords = getRelativeCoordsOfTwoElems(this.table, cell);
			if (beforeTheLeftBorder(relativeCoords)) rightBorder = mid;
			else if (afterTheRightBorder(relativeCoords)) leftBorder = mid;
			else break;
			totalIterations++;
		}
		return mid;
	}
	/**
	* Collects data from cells into a two-dimensional array
	*
	* @returns {string[][]}
	*/
	getData() {
		const data = [];
		for (let i = 1; i <= this.numberOfRows; i++) {
			const row = this.table.querySelector(`.${CSS.row}:nth-child(${i})`);
			const cells = Array.from(row.querySelectorAll(`.${CSS.cell}`));
			if (cells.every((cell) => !cell.textContent.trim())) continue;
			data.push(cells.map((cell) => cell.innerHTML));
		}
		return data;
	}
	/**
	* Remove listeners on the document
	*/
	destroy() {
		globalThis.document.removeEventListener("click", this.documentClicked);
	}
};
//#endregion
//#region src/assets/tools/utils/HtmlTableUtils.ts
/**
* Convert a simple HTML `<table>` into Table-block data, and detect when a table
* is too complex to be one.
*
* The Table block is a rectangular grid of inline-HTML cell strings: it can't
* hold merged cells (colspan/rowspan) or block-level cell content. `isSimpleTable`
* gates on exactly that, so complex tables are left untouched (the caller routes
* them to a Raw HTML block) while simple ones become editable Table blocks that
* round-trip losslessly to GFM. A table without a header row becomes one without
* headings, which the Table tool exports under an empty header (see its
* exportToMarkdown).
*/
var HtmlTableUtils = class HtmlTableUtils {
	static {
		this.BLOCK_CELL_SELECTOR = "p,div,ul,ol,table,blockquote,pre,figure,hr,h1,h2,h3,h4,h5,h6";
	}
	static {
		this.ALIGNMENTS = [
			"left",
			"center",
			"right"
		];
	}
	/** Parse an HTML string and return its first `<table>`, or null when absent. */
	static parseTable(html) {
		const holder = globalThis.document.createElement("div");
		holder.innerHTML = html;
		return holder.querySelector("table");
	}
	/**
	* True when the table maps to a Table block: at least one row, no merged cells,
	* a regular rectangular shape, and only inline content in every cell.
	*/
	static isSimpleTable(table) {
		if (table.querySelector("table") !== null) return false;
		const rows = Array.from(table.querySelectorAll("tr"));
		if (rows.length === 0) return false;
		let columns = -1;
		for (const row of rows) {
			const cells = Array.from(row.querySelectorAll("th,td"));
			if (cells.length === 0) return false;
			for (const cell of cells) {
				if (HtmlTableUtils.spanOf(cell, "colspan") > 1 || HtmlTableUtils.spanOf(cell, "rowspan") > 1) return false;
				if (cell.querySelector(HtmlTableUtils.BLOCK_CELL_SELECTOR) !== null) return false;
			}
			if (columns === -1) columns = cells.length;
			else if (cells.length !== columns) return false;
		}
		return true;
	}
	/** Build Table-block data from a simple `<table>` (assumes `isSimpleTable`). */
	static parse(table) {
		const rows = Array.from(table.querySelectorAll("tr"));
		const content = rows.map((row) => Array.from(row.querySelectorAll("th,td")).map((cell) => HtmlTableUtils.cellContent(cell)));
		const columns = Math.max(...content.map((row) => row.length));
		const headerRow = HtmlTableUtils.headerRow(table, rows);
		return {
			content,
			withHeadings: headerRow !== null,
			columnAlignments: HtmlTableUtils.alignments(headerRow ?? rows[0], columns)
		};
	}
	static spanOf(cell, attribute) {
		const value = Number.parseInt(cell.getAttribute(attribute) ?? "1", 10);
		return Number.isNaN(value) ? 1 : value;
	}
	/** The header `<tr>` (from `<thead>` or an all-`<th>` first row), or null. */
	static headerRow(table, rows) {
		const theadRow = table.querySelector("thead tr");
		if (theadRow !== null) return theadRow;
		const cells = Array.from(rows[0].querySelectorAll("th,td"));
		if (cells.length > 0 && cells.every((cell) => cell.tagName === "TH")) return rows[0];
		return null;
	}
	static cellContent(cell) {
		return cell.innerHTML.replace(/\s+/g, " ").trim();
	}
	static alignments(row, columns) {
		const cells = Array.from(row.querySelectorAll("th,td"));
		return Array.from({ length: columns }, (_unused, index) => HtmlTableUtils.alignOf(cells[index]));
	}
	static alignOf(cell) {
		if (cell === void 0) return "";
		const attribute = (cell.getAttribute("align") ?? "").toLowerCase();
		if (HtmlTableUtils.ALIGNMENTS.includes(attribute)) return attribute;
		const textAlign = cell.style?.textAlign ?? "";
		if (HtmlTableUtils.ALIGNMENTS.includes(textAlign)) return textAlign;
		return "";
	}
};
//#endregion
//#region src/assets/tools/Table/plugin.ts
/** GFM delimiter-cell markers for each column alignment. */
var ALIGNMENT_SEPARATORS = {
	"": "---",
	left: ":---",
	center: ":--:",
	right: "---:"
};
//#endregion
//#region src/assets/tools/Table/index.ts
var Table_default = class TableBlock {
	static {
		this.STICKY_CLASS = "table-sticky-header";
	}
	static {
		this.HTML_TABLE_START = /^<table[\s>]/i;
	}
	/**
	* Notify core that read-only mode is supported
	*
	* @returns {boolean}
	*/
	static get isReadOnlySupported() {
		return true;
	}
	/**
	* Allow to press Enter inside the CodeTool textarea
	*
	* @returns {boolean}
	* @public
	*/
	static get enableLineBreaks() {
		return true;
	}
	/**
	* Do not sanitize <br> and basic inline tags while inline toolbar enabled (upstream #144)
	*
	* The list must cover every tag the Markdown round-trip puts in a cell (see
	* MarkdownUtils.convertInlineMarkdownToHtml): editor.js applies these rules to
	* each cell string on save, so a tag missing here is silently dropped and its
	* Markdown marker lost.
	*
	* @returns {object}
	* @public
	*/
	static get sanitize() {
		return {
			br: true,
			u: true,
			b: true,
			strong: true,
			i: true,
			em: true,
			s: true,
			del: true,
			code: true,
			mark: true,
			sup: true,
			sub: true,
			small: true,
			p: true,
			a: true
		};
	}
	/**
	* Render plugin`s main Element and fill it with saved data
	*
	* @param {TableConstructor} init
	*/
	constructor({ data, config, api, readOnly, block }) {
		this.api = api;
		this.readOnly = readOnly;
		this.config = config;
		this.data = {
			withHeadings: this.getConfig("withHeadings", false, data),
			stickyHeadings: this.getConfig("stickyHeadings", false, data),
			stretched: this.getConfig("stretched", false, data),
			content: data && data.content ? data.content : [],
			columnAlignments: data && data.columnAlignments ? data.columnAlignments : []
		};
		this.table = null;
		this.block = block;
	}
	/**
	* Get Tool toolbox settings
	* icon - Tool icon's SVG
	* title - title to show in toolbox
	*
	* @returns {{icon: string, title: string}}
	*/
	static get toolbox() {
		return {
			icon: C1,
			title: "Table"
		};
	}
	/**
	* Return Tool's view
	*
	* @returns {HTMLDivElement}
	*/
	render() {
		/** creating table */
		this.table = new Table(this.readOnly, this.api, this.data, this.config);
		/** creating container around table */
		this.container = make("div", this.api.styles.block);
		this.container.appendChild(this.table.getWrapper());
		this.table.setHeadingsSetting(this.data.withHeadings);
		this.container.classList.toggle(TableBlock.STICKY_CLASS, !!this.data.stickyHeadings);
		return this.container;
	}
	/**
	* Returns plugin settings
	*
	* @returns {Array}
	*/
	renderSettings() {
		const settings = [{
			label: this.api.i18n.t("With headings"),
			icon: k1,
			isActive: this.data.withHeadings,
			closeOnActivate: true,
			toggle: true,
			onActivate: () => {
				this.data.withHeadings = true;
				this.table.setHeadingsSetting(this.data.withHeadings);
			}
		}, {
			label: this.api.i18n.t("Without headings"),
			icon: c1,
			isActive: !this.data.withHeadings,
			closeOnActivate: true,
			toggle: true,
			onActivate: () => {
				this.data.withHeadings = false;
				this.table.setHeadingsSetting(this.data.withHeadings);
				this.data.stickyHeadings = false;
				this.container.classList.remove(TableBlock.STICKY_CLASS);
			}
		}];
		if (this.data.withHeadings) settings.push({
			label: this.api.i18n.t("Sticky heading"),
			icon: l1,
			isActive: this.data.stickyHeadings,
			closeOnActivate: true,
			toggle: true,
			onActivate: () => {
				this.data.stickyHeadings = !this.data.stickyHeadings;
				this.container.classList.toggle(TableBlock.STICKY_CLASS, !!this.data.stickyHeadings);
			}
		});
		settings.push({
			label: this.data.stretched ? this.api.i18n.t("Collapse") : this.api.i18n.t("Stretch"),
			icon: this.data.stretched ? H$2 : w1,
			closeOnActivate: true,
			toggle: true,
			onActivate: () => {
				this.data.stretched = !this.data.stretched;
				this.block.stretched = this.data.stretched;
			}
		});
		return settings;
	}
	/**
	* Extract table data from the view
	*
	* @returns {TableData} - saved data
	*/
	save() {
		const tableContent = this.table.getData();
		return {
			withHeadings: this.data.withHeadings ?? false,
			stickyHeadings: this.data.stickyHeadings,
			stretched: this.data.stretched ?? false,
			content: tableContent,
			columnAlignments: this.table.getColumnAlignments()
		};
	}
	/**
	* Plugin destroyer
	*
	* @returns {void}
	*/
	destroy() {
		this.table.destroy();
	}
	/**
	* A helper to get config value.
	*
	* @param {string} configName - the key to get from the config.
	* @param {any} defaultValue - default value if config doesn't have passed key
	* @param {object} savedData - previously saved data. If passed, the key will be got from there, otherwise from the config
	* @returns {any} - config value.
	*/
	getConfig(configName, defaultValue = void 0, savedData = void 0) {
		const data = this.data || savedData;
		if (data && configName in data) return data[configName];
		return this.config && configName in this.config ? this.config[configName] : defaultValue;
	}
	/**
	* Table onPaste configuration
	*
	* @public
	*/
	static get pasteConfig() {
		return { tags: [
			"TABLE",
			"TR",
			"TH",
			"TD"
		] };
	}
	/**
	* On paste callback that is fired from Editor
	*
	* @param {PasteEvent} event - event with pasted data
	*/
	onPaste(event) {
		const table = event.detail.data;
		/** Check if the first row is a header */
		const firstRowHeading = table.querySelector(":scope > thead, tr:first-of-type th");
		/** Generate a content matrix */
		const content = Array.from(table.querySelectorAll("tr")).map((row) => {
			/** Get cells from row */
			const cells = Array.from(row.querySelectorAll("th, td"));
			/** Return cells content, expanding horizontal merges (adapted from upstream #80) */
			const rowData = [];
			cells.forEach((cell) => {
				rowData.push(cell.innerHTML);
				/**
				* Map a pasted colspan onto Pushword's `->` markers so merged cells survive
				* the paste and round-trip through markdown. Rowspan is not handled: the
				* markdown renderer has no rowspan concept.
				*/
				const colspan = parseInt(cell.getAttribute("colspan") || "1", 10);
				for (let i = 1; i < colspan; i++) rowData.push("->");
			});
			return rowData;
		});
		/**
		* Normalize ragged rows (e.g. partial table selections from Word/HTML) to a
		* rectangular matrix so the editor renders every column.
		*/
		const maxCols = content.reduce((max, row) => Math.max(max, row.length), 0);
		content.forEach((row) => {
			while (row.length < maxCols) row.push("");
		});
		/** Update Tool's data */
		this.data = {
			withHeadings: firstRowHeading !== null,
			content
		};
		/** Update table block */
		if (this.table.wrapper) this.table.wrapper.replaceWith(this.render());
	}
	/**
	* Export block data to Markdown.
	*
	* @param {TableData} data - block data
	* @param {BlockTuneData} tunes - block tunes
	* @returns {Promise<string>} Markdown representation
	*/
	static async exportToMarkdown(data, tunes) {
		if (!data || !data.content) return "";
		const rows = data.content;
		if (rows.length === 0) return "";
		const alignments = data.columnAlignments ?? [];
		const pipeRow = (cells) => "| " + cells.join(" | ") + " |\n";
		const toMarkdown = (row) => row.map((cell) => MarkdownUtils.convertInlineHtmlToMarkdown(cell, false).replace(/\n/g, "<br>").trim());
		const header = data.withHeadings ? toMarkdown(rows[0]) : rows[0].map(() => "");
		const body = data.withHeadings ? rows.slice(1) : rows;
		const markdown = [
			header,
			header.map((_, i) => ALIGNMENT_SEPARATORS[alignments[i] ?? ""] ?? "---"),
			...body.map(toMarkdown)
		].map(pipeRow).join("");
		const formattedMarkdown = await MarkdownUtils.formatMarkdownWithPrettier(markdown);
		let out = MarkdownUtils.addAttributes(formattedMarkdown, tunes);
		if (data.stickyHeadings && !out.includes(TableBlock.STICKY_CLASS)) {
			const attributeEnd = out.startsWith("{") ? out.indexOf("}") : -1;
			out = attributeEnd !== -1 ? `${out.slice(0, attributeEnd)} .${TableBlock.STICKY_CLASS}${out.slice(attributeEnd)}` : `{.${TableBlock.STICKY_CLASS}}\n${out}`;
		}
		return out;
	}
	/**
	* Build a table block from its Markdown representation.
	*
	* @param {API} editor - Editor.js API
	* @param {string} markdown - Markdown table (optionally prefixed with a block-attribute line)
	* @returns {any} the inserted block
	*/
	static importFromMarkdown(editor, markdown) {
		if (TableBlock.HTML_TABLE_START.test(MarkdownUtils.retrieveMarkdownWithoutTunes(markdown))) return TableBlock.importFromHtmlTable(editor, markdown);
		const lines = markdown.split("\n");
		let i = 0;
		let tunes = {};
		const content = [];
		let withHeadings = false;
		let stickyHeadings = false;
		let columnAlignments = [];
		while (i < lines.length) {
			if (!lines[i]) break;
			const line = lines[i];
			if (i === 0 && MarkdownUtils.startWithAttribute(line)) {
				tunes = MarkdownUtils.parseAttributes(line);
				if (typeof tunes.class === "string" && tunes.class.includes(TableBlock.STICKY_CLASS)) {
					stickyHeadings = true;
					tunes.class = tunes.class.split(/\s+/).filter((c) => c.replace(/^\./, "") !== TableBlock.STICKY_CLASS).join(" ");
					if (tunes.class === "") delete tunes.class;
				}
				i++;
				continue;
			}
			if (line.includes("|")) {
				content.push(TableBlock.splitPipeRow(line).map((cell) => MarkdownUtils.convertInlineMarkdownToHtml(cell)));
				if (i + 1 < lines.length && lines[i + 1]?.trim().match(/^\|[|\s\-:]+\|$/)) {
					withHeadings = true;
					columnAlignments = lines[i + 1].split("|").map((cell) => cell.trim()).filter((cell) => cell !== "").map((cell) => {
						const left = cell.startsWith(":");
						const right = cell.endsWith(":");
						if (left && right) return "center";
						if (right) return "right";
						if (left) return "left";
						return "";
					});
					i++;
				}
			} else break;
			i++;
		}
		if (withHeadings && content.length > 1 && content[0].every((cell) => cell === "")) {
			withHeadings = false;
			content.shift();
		}
		const block = editor.blocks.insert("table");
		editor.blocks.update(block.id, {
			content,
			withHeadings,
			stickyHeadings,
			columnAlignments
		}, tunes);
		return block;
	}
	/**
	* Split a GFM pipe row into its cells, keeping interior empty cells.
	*
	* A row is wrapped in pipes (`| a | b |` → ['', 'a', 'b', '']); only those
	* outer artifacts are dropped. Interior empties matter: they carry the empty
	* header row of a headerless table and any deliberately blank cell.
	*
	* @param {string} line - a single pipe-table row
	* @returns {string[]} trimmed cell values
	*/
	static splitPipeRow(line) {
		const cells = line.split("|").map((cell) => cell.trim());
		if (cells.length > 0 && cells[0] === "") cells.shift();
		if (cells.length > 0 && cells[cells.length - 1] === "") cells.pop();
		return cells;
	}
	/**
	* Build a table block from a simple HTML `<table>` (see {@link HtmlTableUtils}).
	*
	* @param {API} editor - Editor.js API
	* @param {string} markdown - HTML table (optionally prefixed with a block-attribute line)
	* @returns {any} the inserted block, or null when the HTML has no table
	*/
	static importFromHtmlTable(editor, markdown) {
		const { tunes, markdown: html } = MarkdownUtils.parseTunesFromMarkdown(markdown);
		const table = HtmlTableUtils.parseTable(html);
		if (table === null) return null;
		const { content, withHeadings, columnAlignments } = HtmlTableUtils.parse(table);
		const block = editor.blocks.insert("table");
		editor.blocks.update(block.id, {
			content,
			withHeadings,
			stickyHeadings: false,
			columnAlignments
		}, tunes);
		return block;
	}
	/**
	* Detect a fragment this tool can build: a GFM pipe table, or a simple HTML
	* `<table>` (merged/nested/block-content tables are left to the Raw tool).
	*
	* @param {string} markdown - candidate Markdown
	* @returns {boolean}
	*/
	static isItMarkdownExported(markdown) {
		const trimmed = markdown.trimStart();
		if (trimmed.startsWith("|")) return true;
		if (TableBlock.HTML_TABLE_START.test(trimmed)) {
			const table = HtmlTableUtils.parseTable(trimmed);
			return table !== null && HtmlTableUtils.isSimpleTable(table);
		}
		return false;
	}
};
//#endregion
//#region node_modules/@editorjs/delimiter/dist/delimiter.mjs
(function() {
	"use strict";
	try {
		if (typeof globalThis.document < "u") {
			var e = globalThis.document.createElement("style");
			e.appendChild(globalThis.document.createTextNode(".ce-delimiter{line-height:1.6em;width:100%;text-align:center}.ce-delimiter:before{display:inline-block;content:\"***\";font-size:30px;line-height:65px;height:30px;letter-spacing:.2em}")), globalThis.document.head.appendChild(e);
		}
	} catch (t) {
		console.error("vite-plugin-css-injected-by-js", t);
	}
})();
var r = "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" fill=\"none\" viewBox=\"0 0 24 24\"><line x1=\"6\" x2=\"10\" y1=\"12\" y2=\"12\" stroke=\"currentColor\" stroke-linecap=\"round\" stroke-width=\"2\"/><line x1=\"14\" x2=\"18\" y1=\"12\" y2=\"12\" stroke=\"currentColor\" stroke-linecap=\"round\" stroke-width=\"2\"/></svg>";
/**
* Delimiter Block for the Editor.js.
*
* @author CodeX (team@ifmo.su)
* @copyright CodeX 2018
* @license The MIT License (MIT)
* @version 2.0.0
*/
var n = class {
	/**
	* Notify core that read-only mode is supported
	* @return {boolean}
	*/
	static get isReadOnlySupported() {
		return !0;
	}
	/**
	* Allow Tool to have no content
	* @return {boolean}
	*/
	static get contentless() {
		return !0;
	}
	/**
	* Render plugin`s main Element and fill it with saved data
	*
	* @param {{data: DelimiterData, config: object, api: object}}
	*   data — previously saved data
	*   config - user config for Tool
	*   api - Editor.js API
	*/
	constructor({ data: t, config: s, api: e }) {
		this.api = e, this._CSS = {
			block: this.api.styles.block,
			wrapper: "ce-delimiter"
		}, this._element = this.drawView(), this.data = t;
	}
	/**
	* Create Tool's view
	* @return {HTMLDivElement}
	* @private
	*/
	drawView() {
		let t = globalThis.document.createElement("div");
		return t.classList.add(this._CSS.wrapper, this._CSS.block), t;
	}
	/**
	* Return Tool's view
	* @returns {HTMLDivElement}
	* @public
	*/
	render() {
		return this._element;
	}
	/**
	* Extract Tool's data from the view
	* @param {HTMLDivElement} toolsContent - Paragraph tools rendered view
	* @returns {DelimiterData} - saved data
	* @public
	*/
	save(t) {
		return {};
	}
	/**
	* Get Tool toolbox settings
	* icon - Tool icon's SVG
	* title - title to show in toolbox
	*
	* @return {{icon: string, title: string}}
	*/
	static get toolbox() {
		return {
			icon: r,
			title: "Delimiter"
		};
	}
	/**
	* Delimiter onPaste configuration
	*
	* @public
	*/
	static get pasteConfig() {
		return { tags: ["HR"] };
	}
	/**
	* On paste callback that is fired from Editor
	*
	* @param {PasteEvent} event - event with pasted data
	*/
	onPaste(t) {
		this.data = {};
	}
};
//#endregion
//#region src/assets/tools/Delimiter/Delimiter.ts
var Delimiter = class extends n {
	/**
	* Export block data to Markdown
	* @param {BlockToolData} data - Block data
	* @param {BlockTuneData} tunes - Block tunes
	* @returns {string} Markdown representation
	*/
	static exportToMarkdown(_data, _tunes) {
		return "<!--break-->";
	}
	static importFromMarkdown(editor) {
		editor.blocks.insert("delimiter");
	}
	static isItMarkdownExported(markdown) {
		return markdown.trim().match(/^-{3,}$/) !== null && markdown.split("\n").length === 1 || markdown.trim() === "<!--break-->";
	}
};
//#endregion
//#region src/assets/tools/Embed/toolbox-icon.svg?raw
var toolbox_icon_default = "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"16\" height=\"16\" fill=\"currentColor\" class=\"bi bi-play-fill\" viewBox=\"0 0 16 16\">\n    <path d=\"m11.596 8.697-6.363 3.692c-.54.313-1.233-.066-1.233-.697V4.308c0-.63.692-1.01 1.233-.696l6.363 3.692a.802.802 0 0 1 0 1.393\"/>\n</svg>";
//#endregion
//#region src/assets/tools/utils/StateBlock.ts
var BLOCK_STATE = {
	EDIT: 0,
	VIEW: 1
};
var StateBlock = class StateBlock {
	static showEditBtn(BlockTool, state = BLOCK_STATE.VIEW) {
		if (BlockTool.nodes.editBtn === void 0) throw new Error("must createEditBtn before");
		BlockTool.nodes.editInput.checked = state === BLOCK_STATE.VIEW ? true : false;
	}
	static createEditBtn(BlockTool) {
		const toggleId = StateBlock.generateRandomId("toggle");
		BlockTool.nodes.editBtn = make$1.element("div", "toggle-wrapper");
		BlockTool.nodes.editInput = make$1.element("input", ["toggle-input"], {
			type: "checkbox",
			id: toggleId
		});
		const label = make$1.element("label", ["toggle-label"], { for: toggleId });
		BlockTool.nodes.editBtn.appendChild(BlockTool.nodes.editInput);
		BlockTool.nodes.editBtn.appendChild(label);
		return BlockTool.nodes.editBtn;
	}
	static generateRandomId(prefix = "id") {
		return `${prefix}_${Math.random().toString(36).substring(2, 9)}`;
	}
	static show(BlockTool, state) {
		if (!BlockTool.nodes.preview) {
			BlockTool.nodes.preview = this.createPreview(BlockTool);
			if (BlockTool.validate()) BlockTool.updatePreview();
		}
		if (state === BLOCK_STATE.VIEW) {
			BlockTool.updatePreview();
			BlockTool.nodes.preview.classList.remove("hidden");
			BlockTool.nodes.inputs.classList.add("hidden");
			return this.showEditBtn(BlockTool);
		}
		BlockTool.nodes.preview.classList.add("hidden");
		BlockTool.nodes.inputs.classList.remove("hidden");
		this.showEditBtn(BlockTool, BLOCK_STATE.EDIT);
	}
	static render(BlockTool) {
		this.createEditBtn(BlockTool);
		BlockTool.nodes.wrapper = make$1.element("div", BlockTool.api.styles.block);
		BlockTool.nodes.preview = StateBlock.createPreview(BlockTool);
		BlockTool.updatePreview();
		BlockTool.nodes.wrapper.appendChild(BlockTool.nodes.preview);
		BlockTool.nodes.wrapper.appendChild(BlockTool.nodes.editBtn);
		BlockTool.nodes.inputs = BlockTool.createInputs();
		if (BlockTool.nodes.inputs !== BlockTool.nodes.wrapper) BlockTool.nodes.wrapper.appendChild(BlockTool.nodes.inputs);
		if (BlockTool.validate()) {
			BlockTool.save();
			StateBlock.show(BlockTool, BLOCK_STATE.VIEW);
		} else StateBlock.show(BlockTool, BLOCK_STATE.EDIT);
		BlockTool.nodes.editInput.addEventListener("change", () => StateBlock.onEditInputChange(BlockTool));
		return BlockTool.nodes.wrapper;
	}
	static onEditInputChange(BlockTool) {
		if (BlockTool.nodes.editInput.checked) {
			BlockTool.save();
			StateBlock.show(BlockTool, BLOCK_STATE.VIEW);
		} else StateBlock.show(BlockTool, BLOCK_STATE.EDIT);
	}
	static createPreview(BlockTool) {
		const previewWrapper = make$1.element("div", ["hidden", "preview-wrapper"]);
		previewWrapper.onclick = () => {
			BlockTool.nodes.editInput.checked = false;
			StateBlock.show(BlockTool, BLOCK_STATE.EDIT);
		};
		return previewWrapper;
	}
};
//#endregion
//#region src/assets/tools/Embed/Embed.ts
var Embed = class Embed extends AbstractMediaTool {
	static get toolbox() {
		return {
			title: "Embed",
			icon: toolbox_icon_default
		};
	}
	constructor({ data, config, api, readOnly }) {
		super({
			data,
			config,
			api,
			readOnly
		});
		this.data = Embed.normalizeData(data);
		this.nodes.inputAlternativeText = globalThis.document.createElement("div");
		this.nodes.inputServiceUrl = globalThis.document.createElement("div");
	}
	static normalizeData(data) {
		return {
			serviceUrl: data.serviceUrl || "",
			alternativeText: data.alternativeText || "",
			media: data.media || data.image?.media || ""
		};
	}
	render() {
		return StateBlock.render(this);
	}
	onUpload(response) {
		if (!this.responsIsValid(response)) return this.handleUploadError("incorrect response: " + JSON.stringify(response));
		this.data.media = response.file.media;
		if (!response.file.name) return;
		this.data.alternativeText = response.file.name;
		this.nodes.inputAlternativeText.textContent = response.file.name;
		this.fillImage();
	}
	createInputs() {
		this.nodes.inputAlternativeText = make$1.input(this, ["image-tool__caption", this.api.styles.input], "Alternative Text", this.data.alternativeText);
		this.nodes.inputServiceUrl = make$1.input(this, [
			"cdx-input-labeled",
			"cdx-input-labeled-embed-service-url",
			this.api.styles.input
		], "Service URL (eg: https://youtube.com/watch?v=...", this.data.serviceUrl);
		const wrapper = make$1.element("div", ["cdx-embed"]);
		wrapper.appendChild(this.nodes.inputServiceUrl);
		wrapper.appendChild(this.nodes.fileButton);
		wrapper.appendChild(this.nodes.inputAlternativeText);
		this.fillImage();
		return wrapper;
	}
	validate() {
		return !!(this.data.serviceUrl && this.data.alternativeText && this.data.media);
	}
	updatePreview() {
		if (!this.nodes.preview) throw new Error("must createPreview before");
		this.nodes.preview.innerHTML = "<div style=\"display:block;--aspect-ratio:16/9;background: center / cover no-repeat url('/media/md/" + this.data.media + "');\"><div style=\"display: flex;justify-content: center;align-items: center; width:100%;height:100%;color:#c4302b\">" + toolbox_icon_default.replace("width=\"16\"", "width=\"100\"").replace("height=\"16\"", "height=\"100\"") + "</div></div>";
	}
	show(state) {
		this.updatePreview();
		if (state !== BLOCK_STATE.VIEW) return StateBlock.show(this, state);
		if (!this.validate()) {
			this.api.notifier.show({
				message: this.api.i18n.t("Something is missing to properly render the embeded video."),
				style: "error"
			});
			return StateBlock.show(this, state);
		}
	}
	save() {
		this.updateData();
		return this.data;
	}
	updateData() {
		this.data.serviceUrl = this.nodes.inputServiceUrl?.textContent || this.data.serviceUrl;
		this.data.alternativeText = this.nodes.inputAlternativeText?.textContent || this.data.alternativeText;
	}
	fillImage() {
		if (this.nodes.imageEl) this.nodes.imageEl.remove();
		const src = this.data.media;
		if (!src) return;
		this.nodes.imageEl = make$1.element("img", "image-tool__image-picture", {
			src: MediaUtils.buildFullUrl(src),
			style: "max-height:47px;padding-left:1em"
		});
		this.showPreloader(src);
		this.nodes.imageEl.addEventListener("load", () => {
			this.hidePreloader(STATUS.EMPTY);
		});
		this.nodes.fileButton.appendChild(this.nodes.imageEl);
		if (this.validate() && this.nodes.inputs) this.show(BLOCK_STATE.VIEW);
	}
	static exportToMarkdown(dataToNormalize, tunes) {
		const data = Embed.normalizeData(dataToNormalize);
		if (!data.media || !data.serviceUrl) return "";
		const markdown = `{{ video(${e(data.serviceUrl)}, ${e(data.media)}, ${e(data.alternativeText)}) }}`;
		return MarkdownUtils.addAttributes(markdown, tunes);
	}
	static importFromMarkdown(editor, markdown) {
		const result = MarkdownUtils.parseTunesFromMarkdown(markdown);
		const tunes = result.tunes;
		markdown = result.markdown;
		const properties = MarkdownUtils.extractTwigFunctionProperties("video", markdown);
		if (!properties) return;
		const data = {
			serviceUrl: (properties[0] || "").trim(),
			media: (properties[1] || "").trim(),
			alternativeText: (properties[2] || "").trim()
		};
		const block = editor.blocks.insert("embed");
		editor.blocks.update(block.id, data, tunes);
	}
	static isItMarkdownExported(markdown) {
		return MarkdownUtils.extractTwigFunctionProperties("video", markdown) !== null;
	}
};
//#endregion
//#region src/assets/tools/Attaches/Attaches.ts
var Attaches = class Attaches extends AbstractMediaTool {
	static get toolbox() {
		return {
			icon: U$2,
			title: "Attachment"
		};
	}
	constructor({ data, config, api, readOnly, block }) {
		super({
			api,
			config,
			readOnly,
			data
		});
		this.block = block;
		this.nodes = {
			...this.nodes,
			deleteButton: this.createDeleteButton(() => this.removeMedia())
		};
		this.data = Attaches.normalizeData(data);
		this.onSelectFile = config.onSelectFile;
		this.onUploadFile = config.onUploadFile;
	}
	static normalizeData(data) {
		return {
			title: data.title || "",
			file: {
				media: data.file?.media || MediaUtils.extractMediaName(data.file?.url || ""),
				size: data.file?.size || 0
			}
		};
	}
	save(block) {
		if (this.pluginHasData()) {
			const titleElement = block.querySelector(`.cdx-attaches__title`);
			if (titleElement) this.data.title = titleElement.innerHTML;
		}
		return this.data;
	}
	get extension() {
		if (!this.media) return "";
		const parts = this.media.split(".");
		return parts.length > 1 ? parts[parts.length - 1]?.toLowerCase() : "";
	}
	render() {
		const holder = make$1.element("div", this.api.styles.block);
		this.nodes.wrapper.classList.add("cdx-attaches");
		if (this.pluginHasData()) this.showFileData();
		else this.nodes.wrapper.appendChild(this.nodes.fileButton);
		holder.appendChild(this.nodes.wrapper);
		return holder;
	}
	pluginHasData() {
		return this.data.title !== "" || this.data.file.media !== "";
	}
	onUpload(response) {
		if (!this.responsIsValid(response)) return this.handleUploadError("incorrect response: " + JSON.stringify(response));
		this.data.file.media = response.file.media;
		this.data.title = response.file.name || response.file.title || "";
		this.data.file.size = response.file.size ?? 0;
		this.showFileData();
		this.block.dispatchChange();
	}
	appendFileIcon() {
		const wrapper = make$1.element("a", "cdx-attaches__file-icon", {
			href: MediaUtils.buildFullUrlFromData(this.data.file),
			target: "_blank"
		});
		const background = make$1.element("div", "cdx-attaches__file-icon-background");
		wrapper.appendChild(background);
		background.title = this.extension || "";
		this.nodes.wrapper.appendChild(wrapper);
	}
	get media() {
		return this.data.file.media;
	}
	/** Drop the file and show the Select/Upload buttons back. */
	removeMedia() {
		this.data = {
			title: "",
			file: {
				media: "",
				size: 0
			}
		};
		this.nodes.wrapper.classList.remove("cdx-attaches--with-file");
		this.nodes.wrapper.replaceChildren(this.nodes.fileButton);
		this.hidePreloader(STATUS.EMPTY);
		this.block.dispatchChange();
	}
	showFileData() {
		this.nodes.wrapper.classList.add("cdx-attaches--with-file");
		const { file, title } = this.data;
		if (!this.media) {
			this.hidePreloader(STATUS.EMPTY);
			return;
		}
		this.nodes.wrapper.replaceChildren();
		this.appendFileIcon();
		const fileInfo = make$1.element("div", "cdx-attaches__file-info");
		this.nodes.title = make$1.element("div", "cdx-attaches__title", { contentEditable: this.readOnly === false });
		this.nodes.title.dataset.placeholder = this.api.i18n?.t("File title");
		this.nodes.title.textContent = title;
		fileInfo.appendChild(this.nodes.title);
		if (file?.size) {
			const fileSize = make$1.element("div", "cdx-attaches__size");
			fileSize.textContent = this.fileConvertSize(file.size);
			fileInfo.appendChild(fileSize);
		}
		this.nodes.wrapper.appendChild(fileInfo);
		this.nodes.wrapper.appendChild(this.nodes.deleteButton);
		this.hidePreloader(STATUS.FILLED);
	}
	fileConvertSize(size) {
		const sizeNum = Math.abs(parseInt(size, 10));
		const units = [
			[1, "octets"],
			[1024, "ko"],
			[1048576, "Mo"],
			[1073741824, "Go"],
			[1099511627776, "To"]
		];
		for (let n = 0; n < units.length; n++) {
			const currentUnit = units[n];
			const previousUnit = units[n - 1];
			if (currentUnit && previousUnit && sizeNum < currentUnit[0] && n > 0) return (sizeNum / previousUnit[0]).toFixed(2) + " " + previousUnit[1];
		}
		return sizeNum.toString();
	}
	static exportToMarkdown(data, tunes) {
		data = Attaches.normalizeData(data);
		if (!data || !data.file.media) return "";
		const fileUrl = MediaUtils.buildFullUrlFromData(data.file);
		const title = data.title || "";
		return `{{ attaches(${e(import_he.default.decode(title))}, ${e(fileUrl)}, '${data.file.size || 0}' ${tunes?.anchor ? ", " + e(tunes.anchor) : ""}) }}`;
	}
	static importFromMarkdown(editor, markdown) {
		const result = MarkdownUtils.parseTunesFromMarkdown(markdown);
		const tunes = result.tunes;
		markdown = result.markdown;
		const properties = MarkdownUtils.extractTwigFunctionProperties("attaches", markdown);
		if (!properties) return;
		const data = {
			title: properties[0] || "",
			file: {
				media: properties[1] || "",
				size: parseInt(properties[3] || "0", 10)
			}
		};
		if (properties[4] && properties[4] !== "") tunes.anchor = properties[4];
		const block = editor.blocks.insert("attaches");
		editor.blocks.update(block.id, data, tunes);
	}
	static isItMarkdownExported(markdown) {
		return MarkdownUtils.extractTwigFunctionProperties("attaches", markdown) !== null;
	}
};
//#endregion
//#region src/assets/tools/PagesList/PagesListExportToMarkdown.ts
/**
* Export PagesList block data to Markdown
* Cette fonction est extraite pour être utilisée sans dépendances au navigateur
*/
function exportPagesListToMarkdown(data, tunes) {
	if (!data || !data.kw) return "";
	const max = (data.max || "9").trim();
	const maxPages = (data.maxPages || "0").trim();
	const order = data.order || "publishedAt,weight";
	const display = data.display || "list";
	let markdown = `{{ pages_list(${e(data.kw)}, ${e(max)}, ${e(order)}, ${e(display)}`;
	markdown += maxPages !== "0" || tunes?.class || tunes?.anchor ? `, ${e(maxPages)}` : "";
	markdown += tunes?.class || tunes?.anchor ? `, ${e(tunes?.class || "")}` : "";
	markdown += tunes?.anchor ? `, ${e(tunes?.anchor)}` : "";
	markdown += `) }}`;
	return markdown;
}
//#endregion
//#region src/assets/tools/CardList/CardListExportToMarkdown.ts
/**
* Export CardList block data to Markdown
*/
function exportCardListToMarkdown(data, tunes) {
	if (!data || !data.items || data.items.length === 0) return "";
	const items = data.items.map((item) => {
		const obj = {};
		if (item.id) obj.id = item.id;
		if (item.page) obj.page = item.page;
		if (item.title) obj.title = item.title;
		if (item.image) obj.image = item.image;
		if (item.link) obj.link = item.link;
		if (item.obfuscateLink) obj.obfuscateLink = item.obfuscateLink;
		if (item.description) obj.description = item.description;
		if (item.showInfoButton) obj.showInfoButton = item.showInfoButton;
		if (item.infoLinkLabel) obj.infoLinkLabel = item.infoLinkLabel;
		if (item.buttonLink) obj.buttonLink = item.buttonLink;
		if (item.buttonLinkLabel) obj.buttonLinkLabel = item.buttonLinkLabel;
		return obj;
	});
	let markdown = `{{ card_list(${JSON.stringify(items, null, 2)}`;
	markdown += tunes?.class || tunes?.anchor ? `, ${e(tunes?.class || "")}` : "";
	markdown += tunes?.anchor ? `, ${e(tunes?.anchor)}` : "";
	markdown += `) }}`;
	return markdown;
}
//#endregion
//#region src/Command/convert-json-to-markdown.ts
globalThis.window = {
	pageHost: process.env.PAGE_HOST || "",
	location: { origin: process.env.PAGE_ORIGIN || "" },
	pagesUriList: [],
	Promise,
	...globalThis
};
globalThis.document = {
	querySelector: () => null,
	createElement: () => ({})
};
var TOOL_MAP = {
	header: Header,
	paragraph: Paragraph,
	list: List,
	quote: Quote,
	code: CodeBlock,
	codeBlock: CodeBlock,
	image: Image,
	gallery: Gallery,
	table: Table_default,
	delimiter: Delimiter,
	raw: Raw,
	embed: Embed,
	attaches: Attaches,
	pages_list: { exportToMarkdown: exportPagesListToMarkdown },
	card_list: { exportToMarkdown: exportCardListToMarkdown }
};
/**
* Convertit un bloc EditorJS en Markdown en utilisant la méthode exportToMarkdown du tool correspondant
*/
async function convertBlock(block) {
	const ToolClass = TOOL_MAP[block.type];
	if (!ToolClass) {
		console.error(`Warning: Block type "${block.type}" not supported`);
		return "";
	}
	if (typeof ToolClass.exportToMarkdown !== "function") {
		console.error(`Warning: Tool "${block.type}" does not have exportToMarkdown method`);
		return "";
	}
	try {
		return await ToolClass.exportToMarkdown(block.data || {}, block.tunes) || "";
	} catch (error) {
		console.error(`Error converting block type "${block.type}":`, error);
		return "";
	}
}
/**
* Fonction principale
*/
async function main() {
	let jsonContent;
	if (process.argv[2]) jsonContent = process.argv[2];
	else {
		const chunks = [];
		for await (const chunk of process.stdin) chunks.push(chunk);
		jsonContent = Buffer.concat(chunks).toString("utf-8");
	}
	if (!jsonContent || jsonContent.trim() === "") {
		console.error("Erreur: Aucun contenu JSON fourni");
		process.exit(1);
	}
	try {
		const editorData = JSON.parse(jsonContent);
		if (!editorData.blocks || !Array.isArray(editorData.blocks)) {
			console.error("Erreur: Format JSON invalide - blocks manquants ou invalides");
			process.exit(1);
		}
		const markdown = (await Promise.all(editorData.blocks.map((block) => convertBlock(block)))).filter((content) => content !== "").map((content) => content.trim()).join("\n\n");
		console.log(markdown);
		process.exit(0);
	} catch (error) {
		console.error("Erreur lors de la conversion:", error.message);
		if (error.stack) console.error(error.stack);
		process.exit(1);
	}
}
main();
//#endregion
