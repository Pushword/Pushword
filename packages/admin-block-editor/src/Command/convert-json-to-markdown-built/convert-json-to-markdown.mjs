#!/usr/bin/env node
const t = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M6 7L6 12M6 17L6 12M6 12L12 12M12 7V12M12 17L12 12"/><path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M16 11C16 10 19 9.5 19 12C19 13.9771 16.0684 13.9997 16.0012 16.8981C15.9999 16.9533 16.0448 17 16.1 17L19.3 17"/></svg>', r$1 = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M6 7L6 12M6 17L6 12M6 12L12 12M12 7V12M12 17L12 12"/><path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M16 11C16 10.5 16.8323 10 17.6 10C18.3677 10 19.5 10.311 19.5 11.5C19.5 12.5315 18.7474 12.9022 18.548 12.9823C18.5378 12.9864 18.5395 13.0047 18.5503 13.0063C18.8115 13.0456 20 13.3065 20 14.8C20 16 19.5 17 17.8 17C17.8 17 16 17 16 16.3"/></svg>', e$1 = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M6 7L6 12M6 17L6 12M6 12L12 12M12 7V12M12 17L12 12"/><path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M18 10L15.2834 14.8511C15.246 14.9178 15.294 15 15.3704 15C16.8489 15 18.7561 15 20.2 15M19 17C19 15.7187 19 14.8813 19 13.6"/></svg>', n$2 = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M6 7L6 12M6 17L6 12M6 12L12 12M12 7V12M12 17L12 12"/><path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M16 15.9C16 15.9 16.3768 17 17.8 17C19.5 17 20 15.6199 20 14.7C20 12.7323 17.6745 12.0486 16.1635 12.9894C16.094 13.0327 16 12.9846 16 12.9027V10.1C16 10.0448 16.0448 10 16.1 10H19.8"/></svg>', s$1 = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M6 7L6 12M6 17L6 12M6 12L12 12M12 7V12M12 17L12 12"/><path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M19.5 10C16.5 10.5 16 13.3285 16 15M16 15V15C16 16.1046 16.8954 17 18 17H18.3246C19.3251 17 20.3191 16.3492 20.2522 15.3509C20.0612 12.4958 16 12.6611 16 15Z"/></svg>', U$2 = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.3236 8.43554L9.49533 12.1908C9.13119 12.5505 8.93118 13.043 8.9393 13.5598C8.94741 14.0767 9.163 14.5757 9.53862 14.947C9.91424 15.3182 10.4191 15.5314 10.9422 15.5397C11.4653 15.5479 11.9637 15.3504 12.3279 14.9908L16.1562 11.2355C16.8845 10.5161 17.2845 9.53123 17.2682 8.4975C17.252 7.46376 16.8208 6.46583 16.0696 5.72324C15.3184 4.98066 14.3086 4.55425 13.2624 4.53782C12.2162 4.52138 11.2193 4.91627 10.4911 5.63562L6.66277 9.39093C5.57035 10.4699 4.97032 11.9473 4.99467 13.4979C5.01903 15.0485 5.66578 16.5454 6.79264 17.6592C7.9195 18.7731 9.43417 19.4127 11.0034 19.4374C12.5727 19.462 14.068 18.8697 15.1604 17.7907L18.9887 14.0354"/></svg>', G$2 = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M9 7L9 12M9 17V12M9 12L15 12M15 7V12M15 17L15 12"/></svg>', _$2 = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><rect width="14" height="14" x="5" y="5" stroke="currentColor" stroke-width="2" rx="4"/><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.13968 15.32L8.69058 11.5661C9.02934 11.2036 9.48873 11 9.96774 11C10.4467 11 10.9061 11.2036 11.2449 11.5661L15.3871 16M13.5806 14.0664L15.0132 12.533C15.3519 12.1705 15.8113 11.9668 16.2903 11.9668C16.7693 11.9668 17.2287 12.1705 17.5675 12.533L18.841 13.9634"/><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.7778 9.33331H13.7867"/></svg>';
var Jn = Object.create;
var Lt$1 = Object.defineProperty;
var Gn = Object.getOwnPropertyDescriptor;
var zn = Object.getOwnPropertyNames;
var Hn = Object.getPrototypeOf, Xn = Object.prototype.hasOwnProperty;
var qn = (e2, t2) => () => (t2 || e2((t2 = { exports: {} }).exports, t2), t2.exports), Mt$1 = (e2, t2) => {
  for (var u2 in t2) Lt$1(e2, u2, { get: t2[u2], enumerable: true });
}, Qn = (e2, t2, u2, r2) => {
  if (t2 && typeof t2 == "object" || typeof t2 == "function") for (let o2 of zn(t2)) !Xn.call(e2, o2) && o2 !== u2 && Lt$1(e2, o2, { get: () => t2[o2], enumerable: !(r2 = Gn(t2, o2)) || r2.enumerable });
  return e2;
};
var Zn = (e2, t2, u2) => (u2 = e2 != null ? Jn(Hn(e2)) : {}, Qn(Lt$1(u2, "default", { value: e2, enumerable: true }), e2));
var sn = qn((K0, an) => {
  var gt2, yt2, bt2, At2, _t2, We2, gu, Ve2, xt2, nn, Bt2, $e, Tt2, Nt2, St2, pe2, on, wt2, Ot2;
  Tt2 = /\/(?![*\/])(?:\[(?:[^\]\\\n\r\u2028\u2029]+|\\.)*\]|[^\/\\\n\r\u2028\u2029]+|\\.)*(\/[$_\u200C\u200D\p{ID_Continue}]*|\\)?/yu;
  $e = /--|\+\+|=>|\.{3}|\??\.(?!\d)|(?:&&|\|\||\?\?|[+\-%&|^]|\*{1,2}|<{1,2}|>{1,3}|!=?|={1,2}|\/(?![\/*]))=?|[?~,:;[\](){}]/y;
  gt2 = /(\x23?)(?=[$_\p{ID_Start}\\])(?:[$_\u200C\u200D\p{ID_Continue}]+|\\u[\da-fA-F]{4}|\\u\{[\da-fA-F]+\})+/yu;
  St2 = /(['"])(?:[^'"\\\n\r]+|(?!\1)['"]|\\(?:\r\n|[^]))*(\1)?/y;
  Bt2 = /(?:0[xX][\da-fA-F](?:_?[\da-fA-F])*|0[oO][0-7](?:_?[0-7])*|0[bB][01](?:_?[01])*)n?|0n|[1-9](?:_?\d)*n|(?:(?:0(?!\d)|0\d*[89]\d*|[1-9](?:_?\d)*)(?:\.(?:\d(?:_?\d)*)?)?|\.\d(?:_?\d)*)(?:[eE][+-]?\d(?:_?\d)*)?|0[0-7]+/y;
  pe2 = /[`}](?:[^`\\$]+|\\[^]|\$(?!\{))*(`|\$\{)?/y;
  Ot2 = /[\t\v\f\ufeff\p{Zs}]+/yu;
  Ve2 = /\r?\n|[\r\u2028\u2029]/y;
  xt2 = /\/\*(?:[^*]+|\*(?!\/))*(\*\/)?/y;
  Nt2 = /\/\/.*/y;
  bt2 = /[<>.:={}]|\/(?![\/*])/y;
  yt2 = /[$_\p{ID_Start}][$_\u200C\u200D\p{ID_Continue}-]*/yu;
  At2 = /(['"])(?:[^'"]+|(?!\1)['"])*(\1)?/y;
  _t2 = /[^<>{}]+/y;
  wt2 = /^(?:[\/+-]|\.{3}|\?(?:InterpolationIn(?:JSX|Template)|NoLineTerminatorHere|NonExpressionParenEnd|UnaryIncDec))?$|[{}([,;<>=*%&|^!~?:]$/;
  on = /^(?:=>|[;\]){}]|else|\?(?:NoLineTerminatorHere|NonExpressionParenEnd))?$/;
  We2 = /^(?:await|case|default|delete|do|else|instanceof|new|return|throw|typeof|void|yield)$/;
  gu = /^(?:return|throw|yield)$/;
  nn = RegExp(Ve2.source);
  an.exports = function* (e2, { jsx: t2 = false } = {}) {
    var u2, r2, o2, n3, a2, s2, i, D2, f3, l2, d, c2, p2, F2;
    for ({ length: s2 } = e2, n3 = 0, a2 = "", F2 = [{ tag: "JS" }], u2 = [], d = 0, c2 = false; n3 < s2; ) {
      switch (D2 = F2[F2.length - 1], D2.tag) {
        case "JS":
        case "JSNonExpressionParen":
        case "InterpolationInTemplate":
        case "InterpolationInJSX":
          if (e2[n3] === "/" && (wt2.test(a2) || We2.test(a2)) && (Tt2.lastIndex = n3, i = Tt2.exec(e2))) {
            n3 = Tt2.lastIndex, a2 = i[0], c2 = true, yield { type: "RegularExpressionLiteral", value: i[0], closed: i[1] !== void 0 && i[1] !== "\\" };
            continue;
          }
          if ($e.lastIndex = n3, i = $e.exec(e2)) {
            switch (p2 = i[0], f3 = $e.lastIndex, l2 = p2, p2) {
              case "(":
                a2 === "?NonExpressionParenKeyword" && F2.push({ tag: "JSNonExpressionParen", nesting: d }), d++, c2 = false;
                break;
              case ")":
                d--, c2 = true, D2.tag === "JSNonExpressionParen" && d === D2.nesting && (F2.pop(), l2 = "?NonExpressionParenEnd", c2 = false);
                break;
              case "{":
                $e.lastIndex = 0, o2 = !on.test(a2) && (wt2.test(a2) || We2.test(a2)), u2.push(o2), c2 = false;
                break;
              case "}":
                switch (D2.tag) {
                  case "InterpolationInTemplate":
                    if (u2.length === D2.nesting) {
                      pe2.lastIndex = n3, i = pe2.exec(e2), n3 = pe2.lastIndex, a2 = i[0], i[1] === "${" ? (a2 = "?InterpolationInTemplate", c2 = false, yield { type: "TemplateMiddle", value: i[0] }) : (F2.pop(), c2 = true, yield { type: "TemplateTail", value: i[0], closed: i[1] === "`" });
                      continue;
                    }
                    break;
                  case "InterpolationInJSX":
                    if (u2.length === D2.nesting) {
                      F2.pop(), n3 += 1, a2 = "}", yield { type: "JSXPunctuator", value: "}" };
                      continue;
                    }
                }
                c2 = u2.pop(), l2 = c2 ? "?ExpressionBraceEnd" : "}";
                break;
              case "]":
                c2 = true;
                break;
              case "++":
              case "--":
                l2 = c2 ? "?PostfixIncDec" : "?UnaryIncDec";
                break;
              case "<":
                if (t2 && (wt2.test(a2) || We2.test(a2))) {
                  F2.push({ tag: "JSXTag" }), n3 += 1, a2 = "<", yield { type: "JSXPunctuator", value: p2 };
                  continue;
                }
                c2 = false;
                break;
              default:
                c2 = false;
            }
            n3 = f3, a2 = l2, yield { type: "Punctuator", value: p2 };
            continue;
          }
          if (gt2.lastIndex = n3, i = gt2.exec(e2)) {
            switch (n3 = gt2.lastIndex, l2 = i[0], i[0]) {
              case "for":
              case "if":
              case "while":
              case "with":
                a2 !== "." && a2 !== "?." && (l2 = "?NonExpressionParenKeyword");
            }
            a2 = l2, c2 = !We2.test(i[0]), yield { type: i[1] === "#" ? "PrivateIdentifier" : "IdentifierName", value: i[0] };
            continue;
          }
          if (St2.lastIndex = n3, i = St2.exec(e2)) {
            n3 = St2.lastIndex, a2 = i[0], c2 = true, yield { type: "StringLiteral", value: i[0], closed: i[2] !== void 0 };
            continue;
          }
          if (Bt2.lastIndex = n3, i = Bt2.exec(e2)) {
            n3 = Bt2.lastIndex, a2 = i[0], c2 = true, yield { type: "NumericLiteral", value: i[0] };
            continue;
          }
          if (pe2.lastIndex = n3, i = pe2.exec(e2)) {
            n3 = pe2.lastIndex, a2 = i[0], i[1] === "${" ? (a2 = "?InterpolationInTemplate", F2.push({ tag: "InterpolationInTemplate", nesting: u2.length }), c2 = false, yield { type: "TemplateHead", value: i[0] }) : (c2 = true, yield { type: "NoSubstitutionTemplate", value: i[0], closed: i[1] === "`" });
            continue;
          }
          break;
        case "JSXTag":
        case "JSXTagEnd":
          if (bt2.lastIndex = n3, i = bt2.exec(e2)) {
            switch (n3 = bt2.lastIndex, l2 = i[0], i[0]) {
              case "<":
                F2.push({ tag: "JSXTag" });
                break;
              case ">":
                F2.pop(), a2 === "/" || D2.tag === "JSXTagEnd" ? (l2 = "?JSX", c2 = true) : F2.push({ tag: "JSXChildren" });
                break;
              case "{":
                F2.push({ tag: "InterpolationInJSX", nesting: u2.length }), l2 = "?InterpolationInJSX", c2 = false;
                break;
              case "/":
                a2 === "<" && (F2.pop(), F2[F2.length - 1].tag === "JSXChildren" && F2.pop(), F2.push({ tag: "JSXTagEnd" }));
            }
            a2 = l2, yield { type: "JSXPunctuator", value: i[0] };
            continue;
          }
          if (yt2.lastIndex = n3, i = yt2.exec(e2)) {
            n3 = yt2.lastIndex, a2 = i[0], yield { type: "JSXIdentifier", value: i[0] };
            continue;
          }
          if (At2.lastIndex = n3, i = At2.exec(e2)) {
            n3 = At2.lastIndex, a2 = i[0], yield { type: "JSXString", value: i[0], closed: i[2] !== void 0 };
            continue;
          }
          break;
        case "JSXChildren":
          if (_t2.lastIndex = n3, i = _t2.exec(e2)) {
            n3 = _t2.lastIndex, a2 = i[0], yield { type: "JSXText", value: i[0] };
            continue;
          }
          switch (e2[n3]) {
            case "<":
              F2.push({ tag: "JSXTag" }), n3++, a2 = "<", yield { type: "JSXPunctuator", value: "<" };
              continue;
            case "{":
              F2.push({ tag: "InterpolationInJSX", nesting: u2.length }), n3++, a2 = "?InterpolationInJSX", c2 = false, yield { type: "JSXPunctuator", value: "{" };
              continue;
          }
      }
      if (Ot2.lastIndex = n3, i = Ot2.exec(e2)) {
        n3 = Ot2.lastIndex, yield { type: "WhiteSpace", value: i[0] };
        continue;
      }
      if (Ve2.lastIndex = n3, i = Ve2.exec(e2)) {
        n3 = Ve2.lastIndex, c2 = false, gu.test(a2) && (a2 = "?NoLineTerminatorHere"), yield { type: "LineTerminatorSequence", value: i[0] };
        continue;
      }
      if (xt2.lastIndex = n3, i = xt2.exec(e2)) {
        n3 = xt2.lastIndex, nn.test(i[0]) && (c2 = false, gu.test(a2) && (a2 = "?NoLineTerminatorHere")), yield { type: "MultiLineComment", value: i[0], closed: i[1] !== void 0 };
        continue;
      }
      if (Nt2.lastIndex = n3, i = Nt2.exec(e2)) {
        n3 = Nt2.lastIndex, c2 = false, yield { type: "SingleLineComment", value: i[0] };
        continue;
      }
      r2 = String.fromCodePoint(e2.codePointAt(n3)), n3 += r2.length, a2 = r2, c2 = false, yield { type: D2.tag.startsWith("JSX") ? "JSXInvalid" : "Invalid", value: r2 };
    }
  };
});
var Wn = {};
Mt$1(Wn, { __debug: () => Qa, check: () => Xa, doc: () => Nu, format: () => Un, formatWithCursor: () => jn, getSupportInfo: () => qa, util: () => wu, version: () => In });
var X$1 = (e2, t2) => (u2, r2, ...o2) => u2 | 1 && r2 == null ? void 0 : (t2.call(r2) ?? r2[e2]).apply(r2, o2);
var eo = String.prototype.replaceAll ?? function(e2, t2) {
  return e2.global ? this.replace(e2, t2) : this.split(e2).join(t2);
}, to = X$1("replaceAll", function() {
  if (typeof this == "string") return eo;
}), oe$1 = to;
var Te = class {
  diff(t2, u2, r2 = {}) {
    let o2;
    typeof r2 == "function" ? (o2 = r2, r2 = {}) : "callback" in r2 && (o2 = r2.callback);
    let n3 = this.castInput(t2, r2), a2 = this.castInput(u2, r2), s2 = this.removeEmpty(this.tokenize(n3, r2)), i = this.removeEmpty(this.tokenize(a2, r2));
    return this.diffWithOptionsObj(s2, i, r2, o2);
  }
  diffWithOptionsObj(t2, u2, r2, o2) {
    var n3;
    let a2 = (m3) => {
      if (m3 = this.postProcess(m3, r2), o2) {
        setTimeout(function() {
          o2(m3);
        }, 0);
        return;
      } else return m3;
    }, s2 = u2.length, i = t2.length, D2 = 1, f3 = s2 + i;
    r2.maxEditLength != null && (f3 = Math.min(f3, r2.maxEditLength));
    let l2 = (n3 = r2.timeout) !== null && n3 !== void 0 ? n3 : 1 / 0, d = Date.now() + l2, c2 = [{ oldPos: -1, lastComponent: void 0 }], p2 = this.extractCommon(c2[0], u2, t2, 0, r2);
    if (c2[0].oldPos + 1 >= i && p2 + 1 >= s2) return a2(this.buildValues(c2[0].lastComponent, u2, t2));
    let F2 = -1 / 0, C2 = 1 / 0, y2 = () => {
      for (let m3 = Math.max(F2, -D2); m3 <= Math.min(C2, D2); m3 += 2) {
        let h2, E2 = c2[m3 - 1], g2 = c2[m3 + 1];
        E2 && (c2[m3 - 1] = void 0);
        let A2 = false;
        if (g2) {
          let Q2 = g2.oldPos - m3;
          A2 = g2 && 0 <= Q2 && Q2 < s2;
        }
        let z2 = E2 && E2.oldPos + 1 < i;
        if (!A2 && !z2) {
          c2[m3] = void 0;
          continue;
        }
        if (!z2 || A2 && E2.oldPos < g2.oldPos ? h2 = this.addToPath(g2, true, false, 0, r2) : h2 = this.addToPath(E2, false, true, 1, r2), p2 = this.extractCommon(h2, u2, t2, m3, r2), h2.oldPos + 1 >= i && p2 + 1 >= s2) return a2(this.buildValues(h2.lastComponent, u2, t2)) || true;
        c2[m3] = h2, h2.oldPos + 1 >= i && (C2 = Math.min(C2, m3 - 1)), p2 + 1 >= s2 && (F2 = Math.max(F2, m3 + 1));
      }
      D2++;
    };
    if (o2) (function m3() {
      setTimeout(function() {
        if (D2 > f3 || Date.now() > d) return o2(void 0);
        y2() || m3();
      }, 0);
    })();
    else for (; D2 <= f3 && Date.now() <= d; ) {
      let m3 = y2();
      if (m3) return m3;
    }
  }
  addToPath(t2, u2, r2, o2, n3) {
    let a2 = t2.lastComponent;
    return a2 && !n3.oneChangePerToken && a2.added === u2 && a2.removed === r2 ? { oldPos: t2.oldPos + o2, lastComponent: { count: a2.count + 1, added: u2, removed: r2, previousComponent: a2.previousComponent } } : { oldPos: t2.oldPos + o2, lastComponent: { count: 1, added: u2, removed: r2, previousComponent: a2 } };
  }
  extractCommon(t2, u2, r2, o2, n3) {
    let a2 = u2.length, s2 = r2.length, i = t2.oldPos, D2 = i - o2, f3 = 0;
    for (; D2 + 1 < a2 && i + 1 < s2 && this.equals(r2[i + 1], u2[D2 + 1], n3); ) D2++, i++, f3++, n3.oneChangePerToken && (t2.lastComponent = { count: 1, previousComponent: t2.lastComponent, added: false, removed: false });
    return f3 && !n3.oneChangePerToken && (t2.lastComponent = { count: f3, previousComponent: t2.lastComponent, added: false, removed: false }), t2.oldPos = i, D2;
  }
  equals(t2, u2, r2) {
    return r2.comparator ? r2.comparator(t2, u2) : t2 === u2 || !!r2.ignoreCase && t2.toLowerCase() === u2.toLowerCase();
  }
  removeEmpty(t2) {
    let u2 = [];
    for (let r2 = 0; r2 < t2.length; r2++) t2[r2] && u2.push(t2[r2]);
    return u2;
  }
  castInput(t2, u2) {
    return t2;
  }
  tokenize(t2, u2) {
    return Array.from(t2);
  }
  join(t2) {
    return t2.join("");
  }
  postProcess(t2, u2) {
    return t2;
  }
  get useLongestToken() {
    return false;
  }
  buildValues(t2, u2, r2) {
    let o2 = [], n3;
    for (; t2; ) o2.push(t2), n3 = t2.previousComponent, delete t2.previousComponent, t2 = n3;
    o2.reverse();
    let a2 = o2.length, s2 = 0, i = 0, D2 = 0;
    for (; s2 < a2; s2++) {
      let f3 = o2[s2];
      if (f3.removed) f3.value = this.join(r2.slice(D2, D2 + f3.count)), D2 += f3.count;
      else {
        if (!f3.added && this.useLongestToken) {
          let l2 = u2.slice(i, i + f3.count);
          l2 = l2.map(function(d, c2) {
            let p2 = r2[D2 + c2];
            return p2.length > d.length ? p2 : d;
          }), f3.value = this.join(l2);
        } else f3.value = this.join(u2.slice(i, i + f3.count));
        i += f3.count, f3.added || (D2 += f3.count);
      }
    }
    return o2;
  }
};
var Yt$1 = class Yt extends Te {
  tokenize(t2) {
    return t2.slice();
  }
  join(t2) {
    return t2;
  }
  removeEmpty(t2) {
    return t2;
  }
}, Pu = new Yt$1();
function jt$1(e2, t2, u2) {
  return Pu.diff(e2, t2, u2);
}
function Iu(e2) {
  let t2 = e2.indexOf("\r");
  return t2 !== -1 ? e2.charAt(t2 + 1) === `
` ? "crlf" : "cr" : "lf";
}
function Ne(e2) {
  switch (e2) {
    case "cr":
      return "\r";
    case "crlf":
      return `\r
`;
    default:
      return `
`;
  }
}
function Ut$1(e2, t2) {
  let u2;
  switch (t2) {
    case `
`:
      u2 = /\n/gu;
      break;
    case "\r":
      u2 = /\r/gu;
      break;
    case `\r
`:
      u2 = /\r\n/gu;
      break;
    default:
      throw new Error(`Unexpected "eol" ${JSON.stringify(t2)}.`);
  }
  let r2 = e2.match(u2);
  return r2 ? r2.length : 0;
}
function ku(e2) {
  return oe$1(0, e2, /\r\n?/gu, `
`);
}
function uo(e2) {
  return this[e2 < 0 ? this.length + e2 : e2];
}
var ro = X$1("at", function() {
  if (Array.isArray(this) || typeof this == "string") return uo;
}), b$2 = ro;
var no = () => {
}, P$1 = no;
var J$1 = "string", j$1 = "array", U$1 = "cursor", I$1 = "indent", k$2 = "align", v$2 = "trim", x$2 = "group", w$3 = "fill", B$2 = "if-break", R$2 = "indent-if-break", L$2 = "line-suffix", M$2 = "line-suffix-boundary", _$1 = "line", O$2 = "label", T$2 = "break-parent", Ge$1 = /* @__PURE__ */ new Set([U$1, I$1, k$2, v$2, x$2, w$3, B$2, R$2, L$2, M$2, _$1, O$2, T$2]);
function vu(e2) {
  let t2 = e2.length;
  for (; t2 > 0 && (e2[t2 - 1] === "\r" || e2[t2 - 1] === `
`); ) t2--;
  return t2 < e2.length ? e2.slice(0, t2) : e2;
}
function oo(e2) {
  if (typeof e2 == "string") return J$1;
  if (Array.isArray(e2)) return j$1;
  if (!e2) return;
  let { type: t2 } = e2;
  if (Ge$1.has(t2)) return t2;
}
var H$2 = oo;
var ao = (e2) => new Intl.ListFormat("en-US", { type: "disjunction" }).format(e2);
function io(e2) {
  let t2 = e2 === null ? "null" : typeof e2;
  if (t2 !== "string" && t2 !== "object") return `Unexpected doc '${t2}', 
Expected it to be 'string' or 'object'.`;
  if (H$2(e2)) throw new Error("doc is valid.");
  let u2 = Object.prototype.toString.call(e2);
  if (u2 !== "[object Object]") return `Unexpected doc '${u2}'.`;
  let r2 = ao([...Ge$1].map((o2) => `'${o2}'`));
  return `Unexpected doc.type '${e2.type}'.
Expected it to be ${r2}.`;
}
var Wt$1 = class Wt extends Error {
  name = "InvalidDocError";
  constructor(t2) {
    super(io(t2)), this.doc = t2;
  }
}, Z$1 = Wt$1;
var Ru = {};
function so(e2, t2, u2, r2) {
  let o2 = [e2];
  for (; o2.length > 0; ) {
    let n3 = o2.pop();
    if (n3 === Ru) {
      u2(o2.pop());
      continue;
    }
    u2 && o2.push(n3, Ru);
    let a2 = H$2(n3);
    if (!a2) throw new Z$1(n3);
    if (t2?.(n3) !== false) switch (a2) {
      case j$1:
      case w$3: {
        let s2 = a2 === j$1 ? n3 : n3.parts;
        for (let i = s2.length, D2 = i - 1; D2 >= 0; --D2) o2.push(s2[D2]);
        break;
      }
      case B$2:
        o2.push(n3.flatContents, n3.breakContents);
        break;
      case x$2:
        if (r2 && n3.expandedStates) for (let s2 = n3.expandedStates.length, i = s2 - 1; i >= 0; --i) o2.push(n3.expandedStates[i]);
        else o2.push(n3.contents);
        break;
      case k$2:
      case I$1:
      case R$2:
      case O$2:
      case L$2:
        o2.push(n3.contents);
        break;
      case J$1:
      case U$1:
      case v$2:
      case M$2:
      case _$1:
      case T$2:
        break;
      default:
        throw new Z$1(n3);
    }
  }
}
var Se = so;
function Oe(e2, t2) {
  if (typeof e2 == "string") return t2(e2);
  let u2 = /* @__PURE__ */ new Map();
  return r2(e2);
  function r2(n3) {
    if (u2.has(n3)) return u2.get(n3);
    let a2 = o2(n3);
    return u2.set(n3, a2), a2;
  }
  function o2(n3) {
    switch (H$2(n3)) {
      case j$1:
        return t2(n3.map(r2));
      case w$3:
        return t2({ ...n3, parts: n3.parts.map(r2) });
      case B$2:
        return t2({ ...n3, breakContents: r2(n3.breakContents), flatContents: r2(n3.flatContents) });
      case x$2: {
        let { expandedStates: a2, contents: s2 } = n3;
        return a2 ? (a2 = a2.map(r2), s2 = a2[0]) : s2 = r2(s2), t2({ ...n3, contents: s2, expandedStates: a2 });
      }
      case k$2:
      case I$1:
      case R$2:
      case O$2:
      case L$2:
        return t2({ ...n3, contents: r2(n3.contents) });
      case J$1:
      case U$1:
      case v$2:
      case M$2:
      case _$1:
      case T$2:
        return t2(n3);
      default:
        throw new Z$1(n3);
    }
  }
}
function ze$1(e2, t2, u2) {
  let r2 = u2, o2 = false;
  function n3(a2) {
    if (o2) return false;
    let s2 = t2(a2);
    s2 !== void 0 && (o2 = true, r2 = s2);
  }
  return Se(e2, n3), r2;
}
function Do(e2) {
  if (e2.type === x$2 && e2.break || e2.type === _$1 && e2.hard || e2.type === T$2) return true;
}
function Yu(e2) {
  return ze$1(e2, Do, false);
}
function Lu(e2) {
  if (e2.length > 0) {
    let t2 = b$2(0, e2, -1);
    !t2.expandedStates && !t2.break && (t2.break = "propagated");
  }
  return null;
}
function ju(e2) {
  let t2 = /* @__PURE__ */ new Set(), u2 = [];
  function r2(n3) {
    if (n3.type === T$2 && Lu(u2), n3.type === x$2) {
      if (u2.push(n3), t2.has(n3)) return false;
      t2.add(n3);
    }
  }
  function o2(n3) {
    n3.type === x$2 && u2.pop().break && Lu(u2);
  }
  Se(e2, r2, o2, true);
}
function co(e2) {
  return e2.type === _$1 && !e2.hard ? e2.soft ? "" : " " : e2.type === B$2 ? e2.flatContents : e2;
}
function Uu(e2) {
  return Oe(e2, co);
}
function Mu(e2) {
  for (e2 = [...e2]; e2.length >= 2 && b$2(0, e2, -2).type === _$1 && b$2(0, e2, -1).type === T$2; ) e2.length -= 2;
  if (e2.length > 0) {
    let t2 = we(b$2(0, e2, -1));
    e2[e2.length - 1] = t2;
  }
  return e2;
}
function we(e2) {
  switch (H$2(e2)) {
    case I$1:
    case R$2:
    case x$2:
    case L$2:
    case O$2: {
      let t2 = we(e2.contents);
      return { ...e2, contents: t2 };
    }
    case B$2:
      return { ...e2, breakContents: we(e2.breakContents), flatContents: we(e2.flatContents) };
    case w$3:
      return { ...e2, parts: Mu(e2.parts) };
    case j$1:
      return Mu(e2);
    case J$1:
      return vu(e2);
    case k$2:
    case U$1:
    case v$2:
    case M$2:
    case _$1:
    case T$2:
      break;
    default:
      throw new Z$1(e2);
  }
  return e2;
}
function He$1(e2) {
  return we(lo(e2));
}
function fo(e2) {
  switch (H$2(e2)) {
    case w$3:
      if (e2.parts.every((t2) => t2 === "")) return "";
      break;
    case x$2:
      if (!e2.contents && !e2.id && !e2.break && !e2.expandedStates) return "";
      if (e2.contents.type === x$2 && e2.contents.id === e2.id && e2.contents.break === e2.break && e2.contents.expandedStates === e2.expandedStates) return e2.contents;
      break;
    case k$2:
    case I$1:
    case R$2:
    case L$2:
      if (!e2.contents) return "";
      break;
    case B$2:
      if (!e2.flatContents && !e2.breakContents) return "";
      break;
    case j$1: {
      let t2 = [];
      for (let u2 of e2) {
        if (!u2) continue;
        let [r2, ...o2] = Array.isArray(u2) ? u2 : [u2];
        typeof r2 == "string" && typeof b$2(0, t2, -1) == "string" ? t2[t2.length - 1] += r2 : t2.push(r2), t2.push(...o2);
      }
      return t2.length === 0 ? "" : t2.length === 1 ? t2[0] : t2;
    }
    case J$1:
    case U$1:
    case v$2:
    case M$2:
    case _$1:
    case O$2:
    case T$2:
      break;
    default:
      throw new Z$1(e2);
  }
  return e2;
}
function lo(e2) {
  return Oe(e2, (t2) => fo(t2));
}
function Wu(e2, t2 = Xe$1) {
  return Oe(e2, (u2) => typeof u2 == "string" ? Pe(t2, u2.split(`
`)) : u2);
}
function po(e2) {
  if (e2.type === _$1) return true;
}
function $u(e2) {
  return ze$1(e2, po, false);
}
function Ee$1(e2, t2) {
  return e2.type === O$2 ? { ...e2, contents: t2(e2.contents) } : t2(e2);
}
var qe$1 = P$1;
function ae$1(e2) {
  return { type: I$1, contents: e2 };
}
function De$1(e2, t2) {
  return { type: k$2, contents: t2, n: e2 };
}
function Ju(e2) {
  return De$1(Number.NEGATIVE_INFINITY, e2);
}
function Qe$1(e2) {
  return De$1({ type: "root" }, e2);
}
function Gu(e2) {
  return De$1(-1, e2);
}
function Ze$1(e2, t2, u2) {
  let r2 = e2;
  if (t2 > 0) {
    for (let o2 = 0; o2 < Math.floor(t2 / u2); ++o2) r2 = ae$1(r2);
    r2 = De$1(t2 % u2, r2), r2 = De$1(Number.NEGATIVE_INFINITY, r2);
  }
  return r2;
}
var ce$1 = { type: T$2 };
var ee$1 = { type: U$1 };
function zu(e2) {
  return { type: w$3, parts: e2 };
}
function $t$1(e2, t2 = {}) {
  return qe$1(t2.expandedStates), { type: x$2, id: t2.id, contents: e2, break: !!t2.shouldBreak, expandedStates: t2.expandedStates };
}
function Hu(e2, t2) {
  return $t$1(e2[0], { ...t2, expandedStates: e2 });
}
function Xu(e2, t2 = "", u2 = {}) {
  return { type: B$2, breakContents: e2, flatContents: t2, groupId: u2.groupId };
}
function qu(e2, t2) {
  return { type: R$2, contents: e2, groupId: t2.groupId, negate: t2.negate };
}
function Pe(e2, t2) {
  let u2 = [];
  for (let r2 = 0; r2 < t2.length; r2++) r2 !== 0 && u2.push(e2), u2.push(t2[r2]);
  return u2;
}
function Qu(e2, t2) {
  return e2 ? { type: O$2, label: e2, contents: t2 } : t2;
}
var et$1 = { type: _$1 }, Zu = { type: _$1, soft: true }, Ie = { type: _$1, hard: true }, V$1 = [Ie, ce$1], Vt$1 = { type: _$1, hard: true, literal: true }, Xe$1 = [Vt$1, ce$1];
function ke(e2) {
  return { type: L$2, contents: e2 };
}
var er = { type: M$2 };
var tr = { type: v$2 };
function te$1(e2) {
  if (!e2) return "";
  if (Array.isArray(e2)) {
    let t2 = [];
    for (let u2 of e2) if (Array.isArray(u2)) t2.push(...te$1(u2));
    else {
      let r2 = te$1(u2);
      r2 !== "" && t2.push(r2);
    }
    return t2;
  }
  return e2.type === B$2 ? { ...e2, breakContents: te$1(e2.breakContents), flatContents: te$1(e2.flatContents) } : e2.type === x$2 ? { ...e2, contents: te$1(e2.contents), expandedStates: e2.expandedStates?.map(te$1) } : e2.type === w$3 ? { type: "fill", parts: e2.parts.map(te$1) } : e2.contents ? { ...e2, contents: te$1(e2.contents) } : e2;
}
function ur(e2) {
  let t2 = /* @__PURE__ */ Object.create(null), u2 = /* @__PURE__ */ new Set();
  return r2(te$1(e2));
  function r2(n3, a2, s2) {
    if (typeof n3 == "string") return JSON.stringify(n3);
    if (Array.isArray(n3)) {
      let i = n3.map(r2).filter(Boolean);
      return i.length === 1 ? i[0] : `[${i.join(", ")}]`;
    }
    if (n3.type === _$1) {
      let i = s2?.[a2 + 1]?.type === T$2;
      return n3.literal ? i ? "literalline" : "literallineWithoutBreakParent" : n3.hard ? i ? "hardline" : "hardlineWithoutBreakParent" : n3.soft ? "softline" : "line";
    }
    if (n3.type === T$2) return s2?.[a2 - 1]?.type === _$1 && s2[a2 - 1].hard ? void 0 : "breakParent";
    if (n3.type === v$2) return "trim";
    if (n3.type === I$1) return "indent(" + r2(n3.contents) + ")";
    if (n3.type === k$2) return n3.n === Number.NEGATIVE_INFINITY ? "dedentToRoot(" + r2(n3.contents) + ")" : n3.n < 0 ? "dedent(" + r2(n3.contents) + ")" : n3.n.type === "root" ? "markAsRoot(" + r2(n3.contents) + ")" : "align(" + JSON.stringify(n3.n) + ", " + r2(n3.contents) + ")";
    if (n3.type === B$2) return "ifBreak(" + r2(n3.breakContents) + (n3.flatContents ? ", " + r2(n3.flatContents) : "") + (n3.groupId ? (n3.flatContents ? "" : ', ""') + `, { groupId: ${o2(n3.groupId)} }` : "") + ")";
    if (n3.type === R$2) {
      let i = [];
      n3.negate && i.push("negate: true"), n3.groupId && i.push(`groupId: ${o2(n3.groupId)}`);
      let D2 = i.length > 0 ? `, { ${i.join(", ")} }` : "";
      return `indentIfBreak(${r2(n3.contents)}${D2})`;
    }
    if (n3.type === x$2) {
      let i = [];
      n3.break && n3.break !== "propagated" && i.push("shouldBreak: true"), n3.id && i.push(`id: ${o2(n3.id)}`);
      let D2 = i.length > 0 ? `, { ${i.join(", ")} }` : "";
      return n3.expandedStates ? `conditionalGroup([${n3.expandedStates.map((f3) => r2(f3)).join(",")}]${D2})` : `group(${r2(n3.contents)}${D2})`;
    }
    if (n3.type === w$3) return `fill([${n3.parts.map((i) => r2(i)).join(", ")}])`;
    if (n3.type === L$2) return "lineSuffix(" + r2(n3.contents) + ")";
    if (n3.type === M$2) return "lineSuffixBoundary";
    if (n3.type === O$2) return `label(${JSON.stringify(n3.label)}, ${r2(n3.contents)})`;
    if (n3.type === U$1) return "cursor";
    throw new Error("Unknown doc type " + n3.type);
  }
  function o2(n3) {
    if (typeof n3 != "symbol") return JSON.stringify(String(n3));
    if (n3 in t2) return t2[n3];
    let a2 = n3.description || "symbol";
    for (let s2 = 0; ; s2++) {
      let i = a2 + (s2 > 0 ? ` #${s2}` : "");
      if (!u2.has(i)) return u2.add(i), t2[n3] = `Symbol.for(${JSON.stringify(i)})`;
    }
  }
}
var rr = () => /[#*0-9]\uFE0F?\u20E3|[\xA9\xAE\u203C\u2049\u2122\u2139\u2194-\u2199\u21A9\u21AA\u231A\u231B\u2328\u23CF\u23ED-\u23EF\u23F1\u23F2\u23F8-\u23FA\u24C2\u25AA\u25AB\u25B6\u25C0\u25FB\u25FC\u25FE\u2600-\u2604\u260E\u2611\u2614\u2615\u2618\u2620\u2622\u2623\u2626\u262A\u262E\u262F\u2638-\u263A\u2640\u2642\u2648-\u2653\u265F\u2660\u2663\u2665\u2666\u2668\u267B\u267E\u267F\u2692\u2694-\u2697\u2699\u269B\u269C\u26A0\u26A7\u26AA\u26B0\u26B1\u26BD\u26BE\u26C4\u26C8\u26CF\u26D1\u26E9\u26F0-\u26F5\u26F7\u26F8\u26FA\u2702\u2708\u2709\u270F\u2712\u2714\u2716\u271D\u2721\u2733\u2734\u2744\u2747\u2757\u2763\u27A1\u2934\u2935\u2B05-\u2B07\u2B1B\u2B1C\u2B55\u3030\u303D\u3297\u3299]\uFE0F?|[\u261D\u270C\u270D](?:\uD83C[\uDFFB-\uDFFF]|\uFE0F)?|[\u270A\u270B](?:\uD83C[\uDFFB-\uDFFF])?|[\u23E9-\u23EC\u23F0\u23F3\u25FD\u2693\u26A1\u26AB\u26C5\u26CE\u26D4\u26EA\u26FD\u2705\u2728\u274C\u274E\u2753-\u2755\u2795-\u2797\u27B0\u27BF\u2B50]|\u26D3\uFE0F?(?:\u200D\uD83D\uDCA5)?|\u26F9(?:\uD83C[\uDFFB-\uDFFF]|\uFE0F)?(?:\u200D[\u2640\u2642]\uFE0F?)?|\u2764\uFE0F?(?:\u200D(?:\uD83D\uDD25|\uD83E\uDE79))?|\uD83C(?:[\uDC04\uDD70\uDD71\uDD7E\uDD7F\uDE02\uDE37\uDF21\uDF24-\uDF2C\uDF36\uDF7D\uDF96\uDF97\uDF99-\uDF9B\uDF9E\uDF9F\uDFCD\uDFCE\uDFD4-\uDFDF\uDFF5\uDFF7]\uFE0F?|[\uDF85\uDFC2\uDFC7](?:\uD83C[\uDFFB-\uDFFF])?|[\uDFC4\uDFCA](?:\uD83C[\uDFFB-\uDFFF])?(?:\u200D[\u2640\u2642]\uFE0F?)?|[\uDFCB\uDFCC](?:\uD83C[\uDFFB-\uDFFF]|\uFE0F)?(?:\u200D[\u2640\u2642]\uFE0F?)?|[\uDCCF\uDD8E\uDD91-\uDD9A\uDE01\uDE1A\uDE2F\uDE32-\uDE36\uDE38-\uDE3A\uDE50\uDE51\uDF00-\uDF20\uDF2D-\uDF35\uDF37-\uDF43\uDF45-\uDF4A\uDF4C-\uDF7C\uDF7E-\uDF84\uDF86-\uDF93\uDFA0-\uDFC1\uDFC5\uDFC6\uDFC8\uDFC9\uDFCF-\uDFD3\uDFE0-\uDFF0\uDFF8-\uDFFF]|\uDDE6\uD83C[\uDDE8-\uDDEC\uDDEE\uDDF1\uDDF2\uDDF4\uDDF6-\uDDFA\uDDFC\uDDFD\uDDFF]|\uDDE7\uD83C[\uDDE6\uDDE7\uDDE9-\uDDEF\uDDF1-\uDDF4\uDDF6-\uDDF9\uDDFB\uDDFC\uDDFE\uDDFF]|\uDDE8\uD83C[\uDDE6\uDDE8\uDDE9\uDDEB-\uDDEE\uDDF0-\uDDF7\uDDFA-\uDDFF]|\uDDE9\uD83C[\uDDEA\uDDEC\uDDEF\uDDF0\uDDF2\uDDF4\uDDFF]|\uDDEA\uD83C[\uDDE6\uDDE8\uDDEA\uDDEC\uDDED\uDDF7-\uDDFA]|\uDDEB\uD83C[\uDDEE-\uDDF0\uDDF2\uDDF4\uDDF7]|\uDDEC\uD83C[\uDDE6\uDDE7\uDDE9-\uDDEE\uDDF1-\uDDF3\uDDF5-\uDDFA\uDDFC\uDDFE]|\uDDED\uD83C[\uDDF0\uDDF2\uDDF3\uDDF7\uDDF9\uDDFA]|\uDDEE\uD83C[\uDDE8-\uDDEA\uDDF1-\uDDF4\uDDF6-\uDDF9]|\uDDEF\uD83C[\uDDEA\uDDF2\uDDF4\uDDF5]|\uDDF0\uD83C[\uDDEA\uDDEC-\uDDEE\uDDF2\uDDF3\uDDF5\uDDF7\uDDFC\uDDFE\uDDFF]|\uDDF1\uD83C[\uDDE6-\uDDE8\uDDEE\uDDF0\uDDF7-\uDDFB\uDDFE]|\uDDF2\uD83C[\uDDE6\uDDE8-\uDDED\uDDF0-\uDDFF]|\uDDF3\uD83C[\uDDE6\uDDE8\uDDEA-\uDDEC\uDDEE\uDDF1\uDDF4\uDDF5\uDDF7\uDDFA\uDDFF]|\uDDF4\uD83C\uDDF2|\uDDF5\uD83C[\uDDE6\uDDEA-\uDDED\uDDF0-\uDDF3\uDDF7-\uDDF9\uDDFC\uDDFE]|\uDDF6\uD83C\uDDE6|\uDDF7\uD83C[\uDDEA\uDDF4\uDDF8\uDDFA\uDDFC]|\uDDF8\uD83C[\uDDE6-\uDDEA\uDDEC-\uDDF4\uDDF7-\uDDF9\uDDFB\uDDFD-\uDDFF]|\uDDF9\uD83C[\uDDE6\uDDE8\uDDE9\uDDEB-\uDDED\uDDEF-\uDDF4\uDDF7\uDDF9\uDDFB\uDDFC\uDDFF]|\uDDFA\uD83C[\uDDE6\uDDEC\uDDF2\uDDF3\uDDF8\uDDFE\uDDFF]|\uDDFB\uD83C[\uDDE6\uDDE8\uDDEA\uDDEC\uDDEE\uDDF3\uDDFA]|\uDDFC\uD83C[\uDDEB\uDDF8]|\uDDFD\uD83C\uDDF0|\uDDFE\uD83C[\uDDEA\uDDF9]|\uDDFF\uD83C[\uDDE6\uDDF2\uDDFC]|\uDF44(?:\u200D\uD83D\uDFEB)?|\uDF4B(?:\u200D\uD83D\uDFE9)?|\uDFC3(?:\uD83C[\uDFFB-\uDFFF])?(?:\u200D(?:[\u2640\u2642]\uFE0F?(?:\u200D\u27A1\uFE0F?)?|\u27A1\uFE0F?))?|\uDFF3\uFE0F?(?:\u200D(?:\u26A7\uFE0F?|\uD83C\uDF08))?|\uDFF4(?:\u200D\u2620\uFE0F?|\uDB40\uDC67\uDB40\uDC62\uDB40(?:\uDC65\uDB40\uDC6E\uDB40\uDC67|\uDC73\uDB40\uDC63\uDB40\uDC74|\uDC77\uDB40\uDC6C\uDB40\uDC73)\uDB40\uDC7F)?)|\uD83D(?:[\uDC3F\uDCFD\uDD49\uDD4A\uDD6F\uDD70\uDD73\uDD76-\uDD79\uDD87\uDD8A-\uDD8D\uDDA5\uDDA8\uDDB1\uDDB2\uDDBC\uDDC2-\uDDC4\uDDD1-\uDDD3\uDDDC-\uDDDE\uDDE1\uDDE3\uDDE8\uDDEF\uDDF3\uDDFA\uDECB\uDECD-\uDECF\uDEE0-\uDEE5\uDEE9\uDEF0\uDEF3]\uFE0F?|[\uDC42\uDC43\uDC46-\uDC50\uDC66\uDC67\uDC6B-\uDC6D\uDC72\uDC74-\uDC76\uDC78\uDC7C\uDC83\uDC85\uDC8F\uDC91\uDCAA\uDD7A\uDD95\uDD96\uDE4C\uDE4F\uDEC0\uDECC](?:\uD83C[\uDFFB-\uDFFF])?|[\uDC6E-\uDC71\uDC73\uDC77\uDC81\uDC82\uDC86\uDC87\uDE45-\uDE47\uDE4B\uDE4D\uDE4E\uDEA3\uDEB4\uDEB5](?:\uD83C[\uDFFB-\uDFFF])?(?:\u200D[\u2640\u2642]\uFE0F?)?|[\uDD74\uDD90](?:\uD83C[\uDFFB-\uDFFF]|\uFE0F)?|[\uDC00-\uDC07\uDC09-\uDC14\uDC16-\uDC25\uDC27-\uDC3A\uDC3C-\uDC3E\uDC40\uDC44\uDC45\uDC51-\uDC65\uDC6A\uDC79-\uDC7B\uDC7D-\uDC80\uDC84\uDC88-\uDC8E\uDC90\uDC92-\uDCA9\uDCAB-\uDCFC\uDCFF-\uDD3D\uDD4B-\uDD4E\uDD50-\uDD67\uDDA4\uDDFB-\uDE2D\uDE2F-\uDE34\uDE37-\uDE41\uDE43\uDE44\uDE48-\uDE4A\uDE80-\uDEA2\uDEA4-\uDEB3\uDEB7-\uDEBF\uDEC1-\uDEC5\uDED0-\uDED2\uDED5-\uDED8\uDEDC-\uDEDF\uDEEB\uDEEC\uDEF4-\uDEFC\uDFE0-\uDFEB\uDFF0]|\uDC08(?:\u200D\u2B1B)?|\uDC15(?:\u200D\uD83E\uDDBA)?|\uDC26(?:\u200D(?:\u2B1B|\uD83D\uDD25))?|\uDC3B(?:\u200D\u2744\uFE0F?)?|\uDC41\uFE0F?(?:\u200D\uD83D\uDDE8\uFE0F?)?|\uDC68(?:\u200D(?:[\u2695\u2696\u2708]\uFE0F?|\u2764\uFE0F?\u200D\uD83D(?:\uDC8B\u200D\uD83D)?\uDC68|\uD83C[\uDF3E\uDF73\uDF7C\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D(?:[\uDC68\uDC69]\u200D\uD83D(?:\uDC66(?:\u200D\uD83D\uDC66)?|\uDC67(?:\u200D\uD83D[\uDC66\uDC67])?)|[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uDC66(?:\u200D\uD83D\uDC66)?|\uDC67(?:\u200D\uD83D[\uDC66\uDC67])?)|\uD83E(?:[\uDDAF\uDDBC\uDDBD](?:\u200D\u27A1\uFE0F?)?|[\uDDB0-\uDDB3]))|\uD83C(?:\uDFFB(?:\u200D(?:[\u2695\u2696\u2708]\uFE0F?|\u2764\uFE0F?\u200D\uD83D(?:\uDC8B\u200D\uD83D)?\uDC68\uD83C[\uDFFB-\uDFFF]|\uD83C[\uDF3E\uDF73\uDF7C\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D(?:[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uDC30\u200D\uD83D\uDC68\uD83C[\uDFFC-\uDFFF])|\uD83E(?:[\uDD1D\uDEEF]\u200D\uD83D\uDC68\uD83C[\uDFFC-\uDFFF]|[\uDDAF\uDDBC\uDDBD](?:\u200D\u27A1\uFE0F?)?|[\uDDB0-\uDDB3])))?|\uDFFC(?:\u200D(?:[\u2695\u2696\u2708]\uFE0F?|\u2764\uFE0F?\u200D\uD83D(?:\uDC8B\u200D\uD83D)?\uDC68\uD83C[\uDFFB-\uDFFF]|\uD83C[\uDF3E\uDF73\uDF7C\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D(?:[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uDC30\u200D\uD83D\uDC68\uD83C[\uDFFB\uDFFD-\uDFFF])|\uD83E(?:[\uDD1D\uDEEF]\u200D\uD83D\uDC68\uD83C[\uDFFB\uDFFD-\uDFFF]|[\uDDAF\uDDBC\uDDBD](?:\u200D\u27A1\uFE0F?)?|[\uDDB0-\uDDB3])))?|\uDFFD(?:\u200D(?:[\u2695\u2696\u2708]\uFE0F?|\u2764\uFE0F?\u200D\uD83D(?:\uDC8B\u200D\uD83D)?\uDC68\uD83C[\uDFFB-\uDFFF]|\uD83C[\uDF3E\uDF73\uDF7C\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D(?:[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uDC30\u200D\uD83D\uDC68\uD83C[\uDFFB\uDFFC\uDFFE\uDFFF])|\uD83E(?:[\uDD1D\uDEEF]\u200D\uD83D\uDC68\uD83C[\uDFFB\uDFFC\uDFFE\uDFFF]|[\uDDAF\uDDBC\uDDBD](?:\u200D\u27A1\uFE0F?)?|[\uDDB0-\uDDB3])))?|\uDFFE(?:\u200D(?:[\u2695\u2696\u2708]\uFE0F?|\u2764\uFE0F?\u200D\uD83D(?:\uDC8B\u200D\uD83D)?\uDC68\uD83C[\uDFFB-\uDFFF]|\uD83C[\uDF3E\uDF73\uDF7C\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D(?:[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uDC30\u200D\uD83D\uDC68\uD83C[\uDFFB-\uDFFD\uDFFF])|\uD83E(?:[\uDD1D\uDEEF]\u200D\uD83D\uDC68\uD83C[\uDFFB-\uDFFD\uDFFF]|[\uDDAF\uDDBC\uDDBD](?:\u200D\u27A1\uFE0F?)?|[\uDDB0-\uDDB3])))?|\uDFFF(?:\u200D(?:[\u2695\u2696\u2708]\uFE0F?|\u2764\uFE0F?\u200D\uD83D(?:\uDC8B\u200D\uD83D)?\uDC68\uD83C[\uDFFB-\uDFFF]|\uD83C[\uDF3E\uDF73\uDF7C\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D(?:[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uDC30\u200D\uD83D\uDC68\uD83C[\uDFFB-\uDFFE])|\uD83E(?:[\uDD1D\uDEEF]\u200D\uD83D\uDC68\uD83C[\uDFFB-\uDFFE]|[\uDDAF\uDDBC\uDDBD](?:\u200D\u27A1\uFE0F?)?|[\uDDB0-\uDDB3])))?))?|\uDC69(?:\u200D(?:[\u2695\u2696\u2708]\uFE0F?|\u2764\uFE0F?\u200D\uD83D(?:\uDC8B\u200D\uD83D)?[\uDC68\uDC69]|\uD83C[\uDF3E\uDF73\uDF7C\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D(?:[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uDC66(?:\u200D\uD83D\uDC66)?|\uDC67(?:\u200D\uD83D[\uDC66\uDC67])?|\uDC69\u200D\uD83D(?:\uDC66(?:\u200D\uD83D\uDC66)?|\uDC67(?:\u200D\uD83D[\uDC66\uDC67])?))|\uD83E(?:[\uDDAF\uDDBC\uDDBD](?:\u200D\u27A1\uFE0F?)?|[\uDDB0-\uDDB3]))|\uD83C(?:\uDFFB(?:\u200D(?:[\u2695\u2696\u2708]\uFE0F?|\u2764\uFE0F?\u200D\uD83D(?:[\uDC68\uDC69]|\uDC8B\u200D\uD83D[\uDC68\uDC69])\uD83C[\uDFFB-\uDFFF]|\uD83C[\uDF3E\uDF73\uDF7C\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D(?:[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uDC30\u200D\uD83D\uDC69\uD83C[\uDFFC-\uDFFF])|\uD83E(?:[\uDDAF\uDDBC\uDDBD](?:\u200D\u27A1\uFE0F?)?|[\uDDB0-\uDDB3]|\uDD1D\u200D\uD83D[\uDC68\uDC69]\uD83C[\uDFFC-\uDFFF]|\uDEEF\u200D\uD83D\uDC69\uD83C[\uDFFC-\uDFFF])))?|\uDFFC(?:\u200D(?:[\u2695\u2696\u2708]\uFE0F?|\u2764\uFE0F?\u200D\uD83D(?:[\uDC68\uDC69]|\uDC8B\u200D\uD83D[\uDC68\uDC69])\uD83C[\uDFFB-\uDFFF]|\uD83C[\uDF3E\uDF73\uDF7C\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D(?:[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uDC30\u200D\uD83D\uDC69\uD83C[\uDFFB\uDFFD-\uDFFF])|\uD83E(?:[\uDDAF\uDDBC\uDDBD](?:\u200D\u27A1\uFE0F?)?|[\uDDB0-\uDDB3]|\uDD1D\u200D\uD83D[\uDC68\uDC69]\uD83C[\uDFFB\uDFFD-\uDFFF]|\uDEEF\u200D\uD83D\uDC69\uD83C[\uDFFB\uDFFD-\uDFFF])))?|\uDFFD(?:\u200D(?:[\u2695\u2696\u2708]\uFE0F?|\u2764\uFE0F?\u200D\uD83D(?:[\uDC68\uDC69]|\uDC8B\u200D\uD83D[\uDC68\uDC69])\uD83C[\uDFFB-\uDFFF]|\uD83C[\uDF3E\uDF73\uDF7C\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D(?:[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uDC30\u200D\uD83D\uDC69\uD83C[\uDFFB\uDFFC\uDFFE\uDFFF])|\uD83E(?:[\uDDAF\uDDBC\uDDBD](?:\u200D\u27A1\uFE0F?)?|[\uDDB0-\uDDB3]|\uDD1D\u200D\uD83D[\uDC68\uDC69]\uD83C[\uDFFB\uDFFC\uDFFE\uDFFF]|\uDEEF\u200D\uD83D\uDC69\uD83C[\uDFFB\uDFFC\uDFFE\uDFFF])))?|\uDFFE(?:\u200D(?:[\u2695\u2696\u2708]\uFE0F?|\u2764\uFE0F?\u200D\uD83D(?:[\uDC68\uDC69]|\uDC8B\u200D\uD83D[\uDC68\uDC69])\uD83C[\uDFFB-\uDFFF]|\uD83C[\uDF3E\uDF73\uDF7C\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D(?:[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uDC30\u200D\uD83D\uDC69\uD83C[\uDFFB-\uDFFD\uDFFF])|\uD83E(?:[\uDDAF\uDDBC\uDDBD](?:\u200D\u27A1\uFE0F?)?|[\uDDB0-\uDDB3]|\uDD1D\u200D\uD83D[\uDC68\uDC69]\uD83C[\uDFFB-\uDFFD\uDFFF]|\uDEEF\u200D\uD83D\uDC69\uD83C[\uDFFB-\uDFFD\uDFFF])))?|\uDFFF(?:\u200D(?:[\u2695\u2696\u2708]\uFE0F?|\u2764\uFE0F?\u200D\uD83D(?:[\uDC68\uDC69]|\uDC8B\u200D\uD83D[\uDC68\uDC69])\uD83C[\uDFFB-\uDFFF]|\uD83C[\uDF3E\uDF73\uDF7C\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D(?:[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uDC30\u200D\uD83D\uDC69\uD83C[\uDFFB-\uDFFE])|\uD83E(?:[\uDDAF\uDDBC\uDDBD](?:\u200D\u27A1\uFE0F?)?|[\uDDB0-\uDDB3]|\uDD1D\u200D\uD83D[\uDC68\uDC69]\uD83C[\uDFFB-\uDFFE]|\uDEEF\u200D\uD83D\uDC69\uD83C[\uDFFB-\uDFFE])))?))?|\uDD75(?:\uD83C[\uDFFB-\uDFFF]|\uFE0F)?(?:\u200D[\u2640\u2642]\uFE0F?)?|\uDE2E(?:\u200D\uD83D\uDCA8)?|\uDE35(?:\u200D\uD83D\uDCAB)?|\uDE36(?:\u200D\uD83C\uDF2B\uFE0F?)?|\uDE42(?:\u200D[\u2194\u2195]\uFE0F?)?|\uDEB6(?:\uD83C[\uDFFB-\uDFFF])?(?:\u200D(?:[\u2640\u2642]\uFE0F?(?:\u200D\u27A1\uFE0F?)?|\u27A1\uFE0F?))?)|\uD83E(?:[\uDD0C\uDD0F\uDD18-\uDD1F\uDD30-\uDD34\uDD36\uDD77\uDDB5\uDDB6\uDDBB\uDDD2\uDDD3\uDDD5\uDEC3-\uDEC5\uDEF0\uDEF2-\uDEF8](?:\uD83C[\uDFFB-\uDFFF])?|[\uDD26\uDD35\uDD37-\uDD39\uDD3C-\uDD3E\uDDB8\uDDB9\uDDCD\uDDCF\uDDD4\uDDD6-\uDDDD](?:\uD83C[\uDFFB-\uDFFF])?(?:\u200D[\u2640\u2642]\uFE0F?)?|[\uDDDE\uDDDF](?:\u200D[\u2640\u2642]\uFE0F?)?|[\uDD0D\uDD0E\uDD10-\uDD17\uDD20-\uDD25\uDD27-\uDD2F\uDD3A\uDD3F-\uDD45\uDD47-\uDD76\uDD78-\uDDB4\uDDB7\uDDBA\uDDBC-\uDDCC\uDDD0\uDDE0-\uDDFF\uDE70-\uDE7C\uDE80-\uDE8A\uDE8E-\uDEC2\uDEC6\uDEC8\uDECD-\uDEDC\uDEDF-\uDEEA\uDEEF]|\uDDCE(?:\uD83C[\uDFFB-\uDFFF])?(?:\u200D(?:[\u2640\u2642]\uFE0F?(?:\u200D\u27A1\uFE0F?)?|\u27A1\uFE0F?))?|\uDDD1(?:\u200D(?:[\u2695\u2696\u2708]\uFE0F?|\uD83C[\uDF3E\uDF73\uDF7C\uDF84\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uD83E(?:[\uDDAF\uDDBC\uDDBD](?:\u200D\u27A1\uFE0F?)?|[\uDDB0-\uDDB3\uDE70]|\uDD1D\u200D\uD83E\uDDD1|\uDDD1\u200D\uD83E\uDDD2(?:\u200D\uD83E\uDDD2)?|\uDDD2(?:\u200D\uD83E\uDDD2)?))|\uD83C(?:\uDFFB(?:\u200D(?:[\u2695\u2696\u2708]\uFE0F?|\u2764\uFE0F?\u200D(?:\uD83D\uDC8B\u200D)?\uD83E\uDDD1\uD83C[\uDFFC-\uDFFF]|\uD83C[\uDF3E\uDF73\uDF7C\uDF84\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D(?:[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uDC30\u200D\uD83E\uDDD1\uD83C[\uDFFC-\uDFFF])|\uD83E(?:[\uDDAF\uDDBC\uDDBD](?:\u200D\u27A1\uFE0F?)?|[\uDDB0-\uDDB3\uDE70]|\uDD1D\u200D\uD83E\uDDD1\uD83C[\uDFFB-\uDFFF]|\uDEEF\u200D\uD83E\uDDD1\uD83C[\uDFFC-\uDFFF])))?|\uDFFC(?:\u200D(?:[\u2695\u2696\u2708]\uFE0F?|\u2764\uFE0F?\u200D(?:\uD83D\uDC8B\u200D)?\uD83E\uDDD1\uD83C[\uDFFB\uDFFD-\uDFFF]|\uD83C[\uDF3E\uDF73\uDF7C\uDF84\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D(?:[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uDC30\u200D\uD83E\uDDD1\uD83C[\uDFFB\uDFFD-\uDFFF])|\uD83E(?:[\uDDAF\uDDBC\uDDBD](?:\u200D\u27A1\uFE0F?)?|[\uDDB0-\uDDB3\uDE70]|\uDD1D\u200D\uD83E\uDDD1\uD83C[\uDFFB-\uDFFF]|\uDEEF\u200D\uD83E\uDDD1\uD83C[\uDFFB\uDFFD-\uDFFF])))?|\uDFFD(?:\u200D(?:[\u2695\u2696\u2708]\uFE0F?|\u2764\uFE0F?\u200D(?:\uD83D\uDC8B\u200D)?\uD83E\uDDD1\uD83C[\uDFFB\uDFFC\uDFFE\uDFFF]|\uD83C[\uDF3E\uDF73\uDF7C\uDF84\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D(?:[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uDC30\u200D\uD83E\uDDD1\uD83C[\uDFFB\uDFFC\uDFFE\uDFFF])|\uD83E(?:[\uDDAF\uDDBC\uDDBD](?:\u200D\u27A1\uFE0F?)?|[\uDDB0-\uDDB3\uDE70]|\uDD1D\u200D\uD83E\uDDD1\uD83C[\uDFFB-\uDFFF]|\uDEEF\u200D\uD83E\uDDD1\uD83C[\uDFFB\uDFFC\uDFFE\uDFFF])))?|\uDFFE(?:\u200D(?:[\u2695\u2696\u2708]\uFE0F?|\u2764\uFE0F?\u200D(?:\uD83D\uDC8B\u200D)?\uD83E\uDDD1\uD83C[\uDFFB-\uDFFD\uDFFF]|\uD83C[\uDF3E\uDF73\uDF7C\uDF84\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D(?:[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uDC30\u200D\uD83E\uDDD1\uD83C[\uDFFB-\uDFFD\uDFFF])|\uD83E(?:[\uDDAF\uDDBC\uDDBD](?:\u200D\u27A1\uFE0F?)?|[\uDDB0-\uDDB3\uDE70]|\uDD1D\u200D\uD83E\uDDD1\uD83C[\uDFFB-\uDFFF]|\uDEEF\u200D\uD83E\uDDD1\uD83C[\uDFFB-\uDFFD\uDFFF])))?|\uDFFF(?:\u200D(?:[\u2695\u2696\u2708]\uFE0F?|\u2764\uFE0F?\u200D(?:\uD83D\uDC8B\u200D)?\uD83E\uDDD1\uD83C[\uDFFB-\uDFFE]|\uD83C[\uDF3E\uDF73\uDF7C\uDF84\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D(?:[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uDC30\u200D\uD83E\uDDD1\uD83C[\uDFFB-\uDFFE])|\uD83E(?:[\uDDAF\uDDBC\uDDBD](?:\u200D\u27A1\uFE0F?)?|[\uDDB0-\uDDB3\uDE70]|\uDD1D\u200D\uD83E\uDDD1\uD83C[\uDFFB-\uDFFF]|\uDEEF\u200D\uD83E\uDDD1\uD83C[\uDFFB-\uDFFE])))?))?|\uDEF1(?:\uD83C(?:\uDFFB(?:\u200D\uD83E\uDEF2\uD83C[\uDFFC-\uDFFF])?|\uDFFC(?:\u200D\uD83E\uDEF2\uD83C[\uDFFB\uDFFD-\uDFFF])?|\uDFFD(?:\u200D\uD83E\uDEF2\uD83C[\uDFFB\uDFFC\uDFFE\uDFFF])?|\uDFFE(?:\u200D\uD83E\uDEF2\uD83C[\uDFFB-\uDFFD\uDFFF])?|\uDFFF(?:\u200D\uD83E\uDEF2\uD83C[\uDFFB-\uDFFE])?))?)/g;
function Kt$1(e2) {
  return e2 === 12288 || e2 >= 65281 && e2 <= 65376 || e2 >= 65504 && e2 <= 65510;
}
function Jt$1(e2) {
  return e2 >= 4352 && e2 <= 4447 || e2 === 8986 || e2 === 8987 || e2 === 9001 || e2 === 9002 || e2 >= 9193 && e2 <= 9196 || e2 === 9200 || e2 === 9203 || e2 === 9725 || e2 === 9726 || e2 === 9748 || e2 === 9749 || e2 >= 9776 && e2 <= 9783 || e2 >= 9800 && e2 <= 9811 || e2 === 9855 || e2 >= 9866 && e2 <= 9871 || e2 === 9875 || e2 === 9889 || e2 === 9898 || e2 === 9899 || e2 === 9917 || e2 === 9918 || e2 === 9924 || e2 === 9925 || e2 === 9934 || e2 === 9940 || e2 === 9962 || e2 === 9970 || e2 === 9971 || e2 === 9973 || e2 === 9978 || e2 === 9981 || e2 === 9989 || e2 === 9994 || e2 === 9995 || e2 === 10024 || e2 === 10060 || e2 === 10062 || e2 >= 10067 && e2 <= 10069 || e2 === 10071 || e2 >= 10133 && e2 <= 10135 || e2 === 10160 || e2 === 10175 || e2 === 11035 || e2 === 11036 || e2 === 11088 || e2 === 11093 || e2 >= 11904 && e2 <= 11929 || e2 >= 11931 && e2 <= 12019 || e2 >= 12032 && e2 <= 12245 || e2 >= 12272 && e2 <= 12287 || e2 >= 12289 && e2 <= 12350 || e2 >= 12353 && e2 <= 12438 || e2 >= 12441 && e2 <= 12543 || e2 >= 12549 && e2 <= 12591 || e2 >= 12593 && e2 <= 12686 || e2 >= 12688 && e2 <= 12773 || e2 >= 12783 && e2 <= 12830 || e2 >= 12832 && e2 <= 12871 || e2 >= 12880 && e2 <= 42124 || e2 >= 42128 && e2 <= 42182 || e2 >= 43360 && e2 <= 43388 || e2 >= 44032 && e2 <= 55203 || e2 >= 63744 && e2 <= 64255 || e2 >= 65040 && e2 <= 65049 || e2 >= 65072 && e2 <= 65106 || e2 >= 65108 && e2 <= 65126 || e2 >= 65128 && e2 <= 65131 || e2 >= 94176 && e2 <= 94180 || e2 >= 94192 && e2 <= 94198 || e2 >= 94208 && e2 <= 101589 || e2 >= 101631 && e2 <= 101662 || e2 >= 101760 && e2 <= 101874 || e2 >= 110576 && e2 <= 110579 || e2 >= 110581 && e2 <= 110587 || e2 === 110589 || e2 === 110590 || e2 >= 110592 && e2 <= 110882 || e2 === 110898 || e2 >= 110928 && e2 <= 110930 || e2 === 110933 || e2 >= 110948 && e2 <= 110951 || e2 >= 110960 && e2 <= 111355 || e2 >= 119552 && e2 <= 119638 || e2 >= 119648 && e2 <= 119670 || e2 === 126980 || e2 === 127183 || e2 === 127374 || e2 >= 127377 && e2 <= 127386 || e2 >= 127488 && e2 <= 127490 || e2 >= 127504 && e2 <= 127547 || e2 >= 127552 && e2 <= 127560 || e2 === 127568 || e2 === 127569 || e2 >= 127584 && e2 <= 127589 || e2 >= 127744 && e2 <= 127776 || e2 >= 127789 && e2 <= 127797 || e2 >= 127799 && e2 <= 127868 || e2 >= 127870 && e2 <= 127891 || e2 >= 127904 && e2 <= 127946 || e2 >= 127951 && e2 <= 127955 || e2 >= 127968 && e2 <= 127984 || e2 === 127988 || e2 >= 127992 && e2 <= 128062 || e2 === 128064 || e2 >= 128066 && e2 <= 128252 || e2 >= 128255 && e2 <= 128317 || e2 >= 128331 && e2 <= 128334 || e2 >= 128336 && e2 <= 128359 || e2 === 128378 || e2 === 128405 || e2 === 128406 || e2 === 128420 || e2 >= 128507 && e2 <= 128591 || e2 >= 128640 && e2 <= 128709 || e2 === 128716 || e2 >= 128720 && e2 <= 128722 || e2 >= 128725 && e2 <= 128728 || e2 >= 128732 && e2 <= 128735 || e2 === 128747 || e2 === 128748 || e2 >= 128756 && e2 <= 128764 || e2 >= 128992 && e2 <= 129003 || e2 === 129008 || e2 >= 129292 && e2 <= 129338 || e2 >= 129340 && e2 <= 129349 || e2 >= 129351 && e2 <= 129535 || e2 >= 129648 && e2 <= 129660 || e2 >= 129664 && e2 <= 129674 || e2 >= 129678 && e2 <= 129734 || e2 === 129736 || e2 >= 129741 && e2 <= 129756 || e2 >= 129759 && e2 <= 129770 || e2 >= 129775 && e2 <= 129784 || e2 >= 131072 && e2 <= 196605 || e2 >= 196608 && e2 <= 262141;
}
var nr = "©®‼⁉™ℹ↔↕↖↗↘↙↩↪⌨⏏⏱⏲⏸⏹⏺▪▫▶◀◻◼☀☁☂☃☄☎☑☘☝☠☢☣☦☪☮☯☸☹☺♀♂♟♠♣♥♦♨♻♾⚒⚔⚕⚖⚗⚙⚛⚜⚠⚧⚰⚱⛈⛏⛑⛓⛩⛱⛷⛸⛹✂✈✉✌✍✏✒✔✖✝✡✳✴❄❇❣❤➡⤴⤵⬅⬆⬇";
var Fo = /[^\x20-\x7F]/u, mo = new Set(nr);
function Eo(e2) {
  if (!e2) return 0;
  if (!Fo.test(e2)) return e2.length;
  e2 = e2.replace(rr(), (u2) => mo.has(u2) ? " " : "  ");
  let t2 = 0;
  for (let u2 of e2) {
    let r2 = u2.codePointAt(0);
    r2 <= 31 || r2 >= 127 && r2 <= 159 || r2 >= 768 && r2 <= 879 || r2 >= 65024 && r2 <= 65039 || (t2 += Kt$1(r2) || Jt$1(r2) ? 2 : 1);
  }
  return t2;
}
var ve$1 = Eo;
var Co = { type: 0 }, ho = { type: 1 }, Gt$1 = { value: "", length: 0, queue: [], get root() {
  return Gt$1;
} };
function or(e2, t2, u2) {
  let r2 = t2.type === 1 ? e2.queue.slice(0, -1) : [...e2.queue, t2], o2 = "", n3 = 0, a2 = 0, s2 = 0;
  for (let p2 of r2) switch (p2.type) {
    case 0:
      f3(), u2.useTabs ? i(1) : D2(u2.tabWidth);
      break;
    case 3: {
      let { string: F2 } = p2;
      f3(), o2 += F2, n3 += F2.length;
      break;
    }
    case 2: {
      let { width: F2 } = p2;
      a2 += 1, s2 += F2;
      break;
    }
    default:
      throw new Error(`Unexpected indent comment '${p2.type}'.`);
  }
  return d(), { ...e2, value: o2, length: n3, queue: r2 };
  function i(p2) {
    o2 += "	".repeat(p2), n3 += u2.tabWidth * p2;
  }
  function D2(p2) {
    o2 += " ".repeat(p2), n3 += p2;
  }
  function f3() {
    u2.useTabs ? l2() : d();
  }
  function l2() {
    a2 > 0 && i(a2), c2();
  }
  function d() {
    s2 > 0 && D2(s2), c2();
  }
  function c2() {
    a2 = 0, s2 = 0;
  }
}
function ar(e2, t2, u2) {
  if (!t2) return e2;
  if (t2.type === "root") return { ...e2, root: e2 };
  if (t2 === Number.NEGATIVE_INFINITY) return e2.root;
  let r2;
  return typeof t2 == "number" ? t2 < 0 ? r2 = ho : r2 = { type: 2, width: t2 } : r2 = { type: 3, string: t2 }, or(e2, r2, u2);
}
function ir(e2, t2) {
  return or(e2, Co, t2);
}
function go(e2) {
  let t2 = 0;
  for (let u2 = e2.length - 1; u2 >= 0; u2--) {
    let r2 = e2[u2];
    if (r2 === " " || r2 === "	") t2++;
    else break;
  }
  return t2;
}
function zt$1(e2) {
  let t2 = go(e2);
  return { text: t2 === 0 ? e2 : e2.slice(0, e2.length - t2), count: t2 };
}
var W$1 = Symbol("MODE_BREAK"), q$1 = Symbol("MODE_FLAT"), Ht$1 = Symbol("DOC_FILL_PRINTED_LENGTH");
function tt$1(e2, t2, u2, r2, o2, n3) {
  if (u2 === Number.POSITIVE_INFINITY) return true;
  let a2 = t2.length, s2 = false, i = [e2], D2 = "";
  for (; u2 >= 0; ) {
    if (i.length === 0) {
      if (a2 === 0) return true;
      i.push(t2[--a2]);
      continue;
    }
    let { mode: f3, doc: l2 } = i.pop(), d = H$2(l2);
    switch (d) {
      case J$1:
        l2 && (s2 && (D2 += " ", u2 -= 1, s2 = false), D2 += l2, u2 -= ve$1(l2));
        break;
      case j$1:
      case w$3: {
        let c2 = d === j$1 ? l2 : l2.parts, p2 = l2[Ht$1] ?? 0;
        for (let F2 = c2.length - 1; F2 >= p2; F2--) i.push({ mode: f3, doc: c2[F2] });
        break;
      }
      case I$1:
      case k$2:
      case R$2:
      case O$2:
        i.push({ mode: f3, doc: l2.contents });
        break;
      case v$2: {
        let { text: c2, count: p2 } = zt$1(D2);
        D2 = c2, u2 += p2;
        break;
      }
      case x$2: {
        if (n3 && l2.break) return false;
        let c2 = l2.break ? W$1 : f3, p2 = l2.expandedStates && c2 === W$1 ? b$2(0, l2.expandedStates, -1) : l2.contents;
        i.push({ mode: c2, doc: p2 });
        break;
      }
      case B$2: {
        let p2 = (l2.groupId ? o2[l2.groupId] || q$1 : f3) === W$1 ? l2.breakContents : l2.flatContents;
        p2 && i.push({ mode: f3, doc: p2 });
        break;
      }
      case _$1:
        if (f3 === W$1 || l2.hard) return true;
        l2.soft || (s2 = true);
        break;
      case L$2:
        r2 = true;
        break;
      case M$2:
        if (r2) return false;
        break;
    }
  }
  return false;
}
function Ce(e2, t2) {
  let u2 = /* @__PURE__ */ Object.create(null), r2 = t2.printWidth, o2 = Ne(t2.endOfLine), n3 = 0, a2 = [{ indent: Gt$1, mode: W$1, doc: e2 }], s2 = "", i = false, D2 = [], f3 = [], l2 = [], d = [], c2 = 0;
  for (ju(e2); a2.length > 0; ) {
    let { indent: m3, mode: h2, doc: E2 } = a2.pop();
    switch (H$2(E2)) {
      case J$1: {
        let g2 = o2 !== `
` ? oe$1(0, E2, `
`, o2) : E2;
        g2 && (s2 += g2, a2.length > 0 && (n3 += ve$1(g2)));
        break;
      }
      case j$1:
        for (let g2 = E2.length - 1; g2 >= 0; g2--) a2.push({ indent: m3, mode: h2, doc: E2[g2] });
        break;
      case U$1:
        if (f3.length >= 2) throw new Error("There are too many 'cursor' in doc.");
        f3.push(c2 + s2.length);
        break;
      case I$1:
        a2.push({ indent: ir(m3, t2), mode: h2, doc: E2.contents });
        break;
      case k$2:
        a2.push({ indent: ar(m3, E2.n, t2), mode: h2, doc: E2.contents });
        break;
      case v$2:
        y2();
        break;
      case x$2:
        switch (h2) {
          case q$1:
            if (!i) {
              a2.push({ indent: m3, mode: E2.break ? W$1 : q$1, doc: E2.contents });
              break;
            }
          case W$1: {
            i = false;
            let g2 = { indent: m3, mode: q$1, doc: E2.contents }, A2 = r2 - n3, z2 = D2.length > 0;
            if (!E2.break && tt$1(g2, a2, A2, z2, u2)) a2.push(g2);
            else if (E2.expandedStates) {
              let Q2 = b$2(0, E2.expandedStates, -1);
              if (E2.break) {
                a2.push({ indent: m3, mode: W$1, doc: Q2 });
                break;
              } else for (let re2 = 1; re2 < E2.expandedStates.length + 1; re2++) if (re2 >= E2.expandedStates.length) {
                a2.push({ indent: m3, mode: W$1, doc: Q2 });
                break;
              } else {
                let Be = E2.expandedStates[re2], ne2 = { indent: m3, mode: q$1, doc: Be };
                if (tt$1(ne2, a2, A2, z2, u2)) {
                  a2.push(ne2);
                  break;
                }
              }
            } else a2.push({ indent: m3, mode: W$1, doc: E2.contents });
            break;
          }
        }
        E2.id && (u2[E2.id] = b$2(0, a2, -1).mode);
        break;
      case w$3: {
        let g2 = r2 - n3, A2 = E2[Ht$1] ?? 0, { parts: z2 } = E2, Q2 = z2.length - A2;
        if (Q2 === 0) break;
        let re2 = z2[A2 + 0], Be = z2[A2 + 1], ne2 = { indent: m3, mode: q$1, doc: re2 }, kt2 = { indent: m3, mode: W$1, doc: re2 }, vt2 = tt$1(ne2, [], g2, D2.length > 0, u2, true);
        if (Q2 === 1) {
          vt2 ? a2.push(ne2) : a2.push(kt2);
          break;
        }
        let Ou = { indent: m3, mode: q$1, doc: Be }, Rt2 = { indent: m3, mode: W$1, doc: Be };
        if (Q2 === 2) {
          vt2 ? a2.push(Ou, ne2) : a2.push(Rt2, kt2);
          break;
        }
        let $n = z2[A2 + 2], Vn = { indent: m3, mode: h2, doc: { ...E2, [Ht$1]: A2 + 2 } }, Kn = tt$1({ indent: m3, mode: q$1, doc: [re2, Be, $n] }, [], g2, D2.length > 0, u2, true);
        a2.push(Vn), Kn ? a2.push(Ou, ne2) : vt2 ? a2.push(Rt2, ne2) : a2.push(Rt2, kt2);
        break;
      }
      case B$2:
      case R$2: {
        let g2 = E2.groupId ? u2[E2.groupId] : h2;
        if (g2 === W$1) {
          let A2 = E2.type === B$2 ? E2.breakContents : E2.negate ? E2.contents : ae$1(E2.contents);
          A2 && a2.push({ indent: m3, mode: h2, doc: A2 });
        }
        if (g2 === q$1) {
          let A2 = E2.type === B$2 ? E2.flatContents : E2.negate ? ae$1(E2.contents) : E2.contents;
          A2 && a2.push({ indent: m3, mode: h2, doc: A2 });
        }
        break;
      }
      case L$2:
        D2.push({ indent: m3, mode: h2, doc: E2.contents });
        break;
      case M$2:
        D2.length > 0 && a2.push({ indent: m3, mode: h2, doc: Ie });
        break;
      case _$1:
        switch (h2) {
          case q$1:
            if (E2.hard) i = true;
            else {
              E2.soft || (s2 += " ", n3 += 1);
              break;
            }
          case W$1:
            if (D2.length > 0) {
              a2.push({ indent: m3, mode: h2, doc: E2 }, ...D2.reverse()), D2.length = 0;
              break;
            }
            E2.literal ? (s2 += o2, n3 = 0, m3.root && (m3.root.value && (s2 += m3.root.value), n3 = m3.root.length)) : (y2(), s2 += o2 + m3.value, n3 = m3.length);
            break;
        }
        break;
      case O$2:
        a2.push({ indent: m3, mode: h2, doc: E2.contents });
        break;
      case T$2:
        break;
      default:
        throw new Z$1(E2);
    }
    a2.length === 0 && D2.length > 0 && (a2.push(...D2.reverse()), D2.length = 0);
  }
  let p2 = l2.join("") + s2, F2 = [...d, ...f3];
  if (F2.length !== 2) return { formatted: p2 };
  let C2 = F2[0];
  return { formatted: p2, cursorNodeStart: C2, cursorNodeText: p2.slice(C2, b$2(0, F2, -1)) };
  function y2() {
    let { text: m3, count: h2 } = zt$1(s2);
    m3 && (l2.push(m3), c2 += m3.length), s2 = "", n3 -= h2, f3.length > 0 && (d.push(...f3.map((E2) => Math.min(E2, c2))), f3.length = 0);
  }
}
function yo(e2, t2, u2 = 0) {
  let r2 = 0;
  for (let o2 = u2; o2 < e2.length; ++o2) e2[o2] === "	" ? r2 = r2 + t2 - r2 % t2 : r2++;
  return r2;
}
var he$4 = yo;
var Xt$1 = class Xt {
  constructor(t2) {
    this.stack = [t2];
  }
  get key() {
    let { stack: t2, siblings: u2 } = this;
    return b$2(0, t2, u2 === null ? -2 : -4) ?? null;
  }
  get index() {
    return this.siblings === null ? null : b$2(0, this.stack, -2);
  }
  get node() {
    return b$2(0, this.stack, -1);
  }
  get parent() {
    return this.getNode(1);
  }
  get grandparent() {
    return this.getNode(2);
  }
  get isInArray() {
    return this.siblings !== null;
  }
  get siblings() {
    let { stack: t2 } = this, u2 = b$2(0, t2, -3);
    return Array.isArray(u2) ? u2 : null;
  }
  get next() {
    let { siblings: t2 } = this;
    return t2 === null ? null : t2[this.index + 1];
  }
  get previous() {
    let { siblings: t2 } = this;
    return t2 === null ? null : t2[this.index - 1];
  }
  get isFirst() {
    return this.index === 0;
  }
  get isLast() {
    let { siblings: t2, index: u2 } = this;
    return t2 !== null && u2 === t2.length - 1;
  }
  get isRoot() {
    return this.stack.length === 1;
  }
  get root() {
    return this.stack[0];
  }
  get ancestors() {
    return [...this.#e()];
  }
  getName() {
    let { stack: t2 } = this, { length: u2 } = t2;
    return u2 > 1 ? b$2(0, t2, -2) : null;
  }
  getValue() {
    return b$2(0, this.stack, -1);
  }
  getNode(t2 = 0) {
    let u2 = this.#t(t2);
    return u2 === -1 ? null : this.stack[u2];
  }
  getParentNode(t2 = 0) {
    return this.getNode(t2 + 1);
  }
  #t(t2) {
    let { stack: u2 } = this;
    for (let r2 = u2.length - 1; r2 >= 0; r2 -= 2) if (!Array.isArray(u2[r2]) && --t2 < 0) return r2;
    return -1;
  }
  call(t2, ...u2) {
    let { stack: r2 } = this, { length: o2 } = r2, n3 = b$2(0, r2, -1);
    for (let a2 of u2) n3 = n3?.[a2], r2.push(a2, n3);
    try {
      return t2(this);
    } finally {
      r2.length = o2;
    }
  }
  callParent(t2, u2 = 0) {
    let r2 = this.#t(u2 + 1), o2 = this.stack.splice(r2 + 1);
    try {
      return t2(this);
    } finally {
      this.stack.push(...o2);
    }
  }
  each(t2, ...u2) {
    let { stack: r2 } = this, { length: o2 } = r2, n3 = b$2(0, r2, -1);
    for (let a2 of u2) n3 = n3[a2], r2.push(a2, n3);
    try {
      for (let a2 = 0; a2 < n3.length; ++a2) r2.push(a2, n3[a2]), t2(this, a2, n3), r2.length -= 2;
    } finally {
      r2.length = o2;
    }
  }
  map(t2, ...u2) {
    let r2 = [];
    return this.each((o2, n3, a2) => {
      r2[n3] = t2(o2, n3, a2);
    }, ...u2), r2;
  }
  match(...t2) {
    let u2 = this.stack.length - 1, r2 = null, o2 = this.stack[u2--];
    for (let n3 of t2) {
      if (o2 === void 0) return false;
      let a2 = null;
      if (typeof r2 == "number" && (a2 = r2, r2 = this.stack[u2--], o2 = this.stack[u2--]), n3 && !n3(o2, r2, a2)) return false;
      r2 = this.stack[u2--], o2 = this.stack[u2--];
    }
    return true;
  }
  findAncestor(t2) {
    for (let u2 of this.#e()) if (t2(u2)) return u2;
  }
  hasAncestor(t2) {
    for (let u2 of this.#e()) if (t2(u2)) return true;
    return false;
  }
  *#e() {
    let { stack: t2 } = this;
    for (let u2 = t2.length - 3; u2 >= 0; u2 -= 2) {
      let r2 = t2[u2];
      Array.isArray(r2) || (yield r2);
    }
  }
}, sr = Xt$1;
function ge$1(e2) {
  return (t2, u2, r2) => {
    let o2 = !!r2?.backwards;
    if (u2 === false) return false;
    let { length: n3 } = t2, a2 = u2;
    for (; a2 >= 0 && a2 < n3; ) {
      let s2 = t2.charAt(a2);
      if (e2 instanceof RegExp) {
        if (!e2.test(s2)) return a2;
      } else if (!e2.includes(s2)) return a2;
      o2 ? a2-- : a2++;
    }
    return a2 === -1 || a2 === n3 ? a2 : false;
  };
}
var cr = ge$1(/\s/u), Y$1 = ge$1(" 	"), ut$1 = ge$1(",; 	"), rt$1 = ge$1(/[^\n\r]/u);
var fr = (e2) => e2 === `
` || e2 === "\r" || e2 === "\u2028" || e2 === "\u2029";
function bo(e2, t2, u2) {
  let r2 = !!u2?.backwards;
  if (t2 === false) return false;
  let o2 = e2.charAt(t2);
  if (r2) {
    if (e2.charAt(t2 - 1) === "\r" && o2 === `
`) return t2 - 2;
    if (fr(o2)) return t2 - 1;
  } else {
    if (o2 === "\r" && e2.charAt(t2 + 1) === `
`) return t2 + 2;
    if (fr(o2)) return t2 + 1;
  }
  return t2;
}
var K$1 = bo;
function Ao(e2, t2, u2 = {}) {
  let r2 = Y$1(e2, u2.backwards ? t2 - 1 : t2, u2), o2 = K$1(e2, r2, u2);
  return r2 !== o2;
}
var G$1 = Ao;
function _o(e2) {
  return Array.isArray(e2) && e2.length > 0;
}
var lr = _o;
function xo(e2) {
  return e2 !== null && typeof e2 == "object";
}
var nt$1 = xo;
function* ye$1(e2, t2) {
  let { getVisitorKeys: u2, filter: r2 = () => true } = t2, o2 = (n3) => nt$1(n3) && r2(n3);
  for (let n3 of u2(e2)) {
    let a2 = e2[n3];
    if (Array.isArray(a2)) for (let s2 of a2) o2(s2) && (yield s2);
    else o2(a2) && (yield a2);
  }
}
function* dr(e2, t2) {
  let u2 = [e2];
  for (let r2 = 0; r2 < u2.length; r2++) {
    let o2 = u2[r2];
    for (let n3 of ye$1(o2, t2)) yield n3, u2.push(n3);
  }
}
function pr(e2, t2) {
  return ye$1(e2, t2).next().done;
}
function Fr(e2, t2, u2) {
  let { cache: r2 } = u2;
  if (r2.has(e2)) return r2.get(e2);
  let { filter: o2 } = u2;
  if (!o2) return [];
  let n3, a2 = (u2.getChildren?.(e2, u2) ?? [...ye$1(e2, { getVisitorKeys: u2.getVisitorKeys })]).flatMap((D2) => (n3 ?? (n3 = [e2, ...t2]), o2(D2, n3) ? [D2] : Fr(D2, n3, u2))), { locStart: s2, locEnd: i } = u2;
  return a2.sort((D2, f3) => s2(D2) - s2(f3) || i(D2) - i(f3)), r2.set(e2, a2), a2;
}
var ot$1 = Fr;
function Bo(e2) {
  let t2 = e2.type || e2.kind || "(unknown type)", u2 = String(e2.name || e2.id && (typeof e2.id == "object" ? e2.id.name : e2.id) || e2.key && (typeof e2.key == "object" ? e2.key.name : e2.key) || e2.value && (typeof e2.value == "object" ? "" : String(e2.value)) || e2.operator || "");
  return u2.length > 20 && (u2 = u2.slice(0, 19) + "…"), t2 + (u2 ? " " + u2 : "");
}
function qt$1(e2, t2) {
  (e2.comments ?? (e2.comments = [])).push(t2), t2.printed = false, t2.nodeDescription = Bo(e2);
}
function fe$1(e2, t2) {
  t2.leading = true, t2.trailing = false, qt$1(e2, t2);
}
function ue$1(e2, t2, u2) {
  t2.leading = false, t2.trailing = false, u2 && (t2.marker = u2), qt$1(e2, t2);
}
function le$1(e2, t2) {
  t2.leading = false, t2.trailing = true, qt$1(e2, t2);
}
var eu = /* @__PURE__ */ new WeakMap();
function Er(e2, t2, u2, r2, o2 = []) {
  let { locStart: n3, locEnd: a2 } = u2, s2 = n3(t2), i = a2(t2), D2 = ot$1(e2, o2, { cache: eu, locStart: n3, locEnd: a2, getVisitorKeys: u2.getVisitorKeys, filter: u2.printer.canAttachComment, getChildren: u2.printer.getCommentChildNodes }), f3, l2, d = 0, c2 = D2.length;
  for (; d < c2; ) {
    let p2 = d + c2 >> 1, F2 = D2[p2], C2 = n3(F2), y2 = a2(F2);
    if (C2 <= s2 && i <= y2) return Er(F2, t2, u2, F2, [F2, ...o2]);
    if (y2 <= s2) {
      f3 = F2, d = p2 + 1;
      continue;
    }
    if (i <= C2) {
      l2 = F2, c2 = p2;
      continue;
    }
    throw new Error("Comment location overlaps with node location");
  }
  if (r2?.type === "TemplateLiteral") {
    let { quasis: p2 } = r2, F2 = Zt$1(p2, t2, u2);
    f3 && Zt$1(p2, f3, u2) !== F2 && (f3 = null), l2 && Zt$1(p2, l2, u2) !== F2 && (l2 = null);
  }
  return { enclosingNode: r2, precedingNode: f3, followingNode: l2 };
}
var Qt$1 = () => false;
function Cr(e2, t2) {
  let { comments: u2 } = e2;
  if (delete e2.comments, !lr(u2) || !t2.printer.canAttachComment) return;
  let r2 = [], { printer: { features: { experimental_avoidAstMutation: o2 }, handleComments: n3 = {} }, originalText: a2 } = t2, { ownLine: s2 = Qt$1, endOfLine: i = Qt$1, remaining: D2 = Qt$1 } = n3, f3 = u2.map((l2, d) => ({ ...Er(e2, l2, t2), comment: l2, text: a2, options: t2, ast: e2, isLastComment: u2.length - 1 === d }));
  for (let [l2, d] of f3.entries()) {
    let { comment: c2, precedingNode: p2, enclosingNode: F2, followingNode: C2, text: y2, options: m3, ast: h2, isLastComment: E2 } = d, g2;
    if (o2 ? g2 = [d] : (c2.enclosingNode = F2, c2.precedingNode = p2, c2.followingNode = C2, g2 = [c2, y2, m3, h2, E2]), To(y2, m3, f3, l2)) c2.placement = "ownLine", s2(...g2) || (C2 ? fe$1(C2, c2) : p2 ? le$1(p2, c2) : F2 ? ue$1(F2, c2) : ue$1(h2, c2));
    else if (No(y2, m3, f3, l2)) c2.placement = "endOfLine", i(...g2) || (p2 ? le$1(p2, c2) : C2 ? fe$1(C2, c2) : F2 ? ue$1(F2, c2) : ue$1(h2, c2));
    else if (c2.placement = "remaining", !D2(...g2)) if (p2 && C2) {
      let A2 = r2.length;
      A2 > 0 && r2[A2 - 1].followingNode !== C2 && mr(r2, m3), r2.push(d);
    } else p2 ? le$1(p2, c2) : C2 ? fe$1(C2, c2) : F2 ? ue$1(F2, c2) : ue$1(h2, c2);
  }
  if (mr(r2, t2), !o2) for (let l2 of u2) delete l2.precedingNode, delete l2.enclosingNode, delete l2.followingNode;
}
var hr = (e2) => !/[\S\n\u2028\u2029]/u.test(e2);
function To(e2, t2, u2, r2) {
  let { comment: o2, precedingNode: n3 } = u2[r2], { locStart: a2, locEnd: s2 } = t2, i = a2(o2);
  if (n3) for (let D2 = r2 - 1; D2 >= 0; D2--) {
    let { comment: f3, precedingNode: l2 } = u2[D2];
    if (l2 !== n3 || !hr(e2.slice(s2(f3), i))) break;
    i = a2(f3);
  }
  return G$1(e2, i, { backwards: true });
}
function No(e2, t2, u2, r2) {
  let { comment: o2, followingNode: n3 } = u2[r2], { locStart: a2, locEnd: s2 } = t2, i = s2(o2);
  if (n3) for (let D2 = r2 + 1; D2 < u2.length; D2++) {
    let { comment: f3, followingNode: l2 } = u2[D2];
    if (l2 !== n3 || !hr(e2.slice(i, a2(f3)))) break;
    i = s2(f3);
  }
  return G$1(e2, i);
}
function mr(e2, t2) {
  let u2 = e2.length;
  if (u2 === 0) return;
  let { precedingNode: r2, followingNode: o2 } = e2[0], n3 = t2.locStart(o2), a2;
  for (a2 = u2; a2 > 0; --a2) {
    let { comment: s2, precedingNode: i, followingNode: D2 } = e2[a2 - 1];
    let f3 = t2.originalText.slice(t2.locEnd(s2), n3);
    if (t2.printer.isGap?.(f3, t2) ?? /^[\s(]*$/u.test(f3)) n3 = t2.locStart(s2);
    else break;
  }
  for (let [s2, { comment: i }] of e2.entries()) s2 < a2 ? le$1(r2, i) : fe$1(o2, i);
  for (let s2 of [r2, o2]) s2.comments && s2.comments.length > 1 && s2.comments.sort((i, D2) => t2.locStart(i) - t2.locStart(D2));
  e2.length = 0;
}
function Zt$1(e2, t2, u2) {
  let r2 = u2.locStart(t2) - 1;
  for (let o2 = 1; o2 < e2.length; ++o2) if (r2 < u2.locStart(e2[o2])) return o2 - 1;
  return 0;
}
function So(e2, t2) {
  let u2 = t2 - 1;
  u2 = Y$1(e2, u2, { backwards: true }), u2 = K$1(e2, u2, { backwards: true }), u2 = Y$1(e2, u2, { backwards: true });
  let r2 = K$1(e2, u2, { backwards: true });
  return u2 !== r2;
}
var Re$1 = So;
function gr(e2, t2) {
  let u2 = e2.node;
  return u2.printed = true, t2.printer.printComment(e2, t2);
}
function wo(e2, t2) {
  let u2 = e2.node, r2 = [gr(e2, t2)], { printer: o2, originalText: n3, locStart: a2, locEnd: s2 } = t2;
  if (o2.isBlockComment?.(u2)) {
    let f3 = G$1(n3, s2(u2)) ? G$1(n3, a2(u2), { backwards: true }) ? V$1 : et$1 : " ";
    r2.push(f3);
  } else r2.push(V$1);
  let D2 = K$1(n3, Y$1(n3, s2(u2)));
  return D2 !== false && G$1(n3, D2) && r2.push(V$1), r2;
}
function Oo(e2, t2, u2) {
  let r2 = e2.node, o2 = gr(e2, t2), { printer: n3, originalText: a2, locStart: s2 } = t2, i = n3.isBlockComment?.(r2);
  if (u2?.hasLineSuffix && !u2?.isBlock || G$1(a2, s2(r2), { backwards: true })) {
    let D2 = Re$1(a2, s2(r2));
    return { doc: ke([V$1, D2 ? V$1 : "", o2]), isBlock: i, hasLineSuffix: true };
  }
  return !i || u2?.hasLineSuffix ? { doc: [ke([" ", o2]), ce$1], isBlock: i, hasLineSuffix: true } : { doc: [" ", o2], isBlock: i, hasLineSuffix: false };
}
function Po(e2, t2) {
  let u2 = e2.node;
  if (!u2) return {};
  let r2 = t2[Symbol.for("printedComments")];
  if ((u2.comments || []).filter((i) => !r2.has(i)).length === 0) return { leading: "", trailing: "" };
  let n3 = [], a2 = [], s2;
  return e2.each(() => {
    let i = e2.node;
    if (r2?.has(i)) return;
    let { leading: D2, trailing: f3 } = i;
    D2 ? n3.push(wo(e2, t2)) : f3 && (s2 = Oo(e2, t2, s2), a2.push(s2.doc));
  }, "comments"), { leading: n3, trailing: a2 };
}
function yr(e2, t2, u2) {
  let { leading: r2, trailing: o2 } = Po(e2, u2);
  return !r2 && !o2 ? t2 : Ee$1(t2, (n3) => [r2, n3, o2]);
}
function br(e2) {
  let { [Symbol.for("comments")]: t2, [Symbol.for("printedComments")]: u2 } = e2;
  for (let r2 of t2) {
    if (!r2.printed && !u2.has(r2)) throw new Error('Comment "' + r2.value.trim() + '" was not printed. Please report this error!');
    delete r2.printed;
  }
}
var Le = class extends Error {
  name = "ConfigError";
}, Me = class extends Error {
  name = "UndefinedParserError";
};
var _r = { checkIgnorePragma: { category: "Special", type: "boolean", default: false, description: "Check whether the file's first docblock comment contains '@noprettier' or '@noformat' to determine if it should be formatted.", cliCategory: "Other" }, cursorOffset: { category: "Special", type: "int", default: -1, range: { start: -1, end: 1 / 0, step: 1 }, description: "Print (to stderr) where a cursor at the given position would move to after formatting.", cliCategory: "Editor" }, endOfLine: { category: "Global", type: "choice", default: "lf", description: "Which end of line characters to apply.", choices: [{ value: "lf", description: "Line Feed only (\\n), common on Linux and macOS as well as inside git repos" }, { value: "crlf", description: "Carriage Return + Line Feed characters (\\r\\n), common on Windows" }, { value: "cr", description: "Carriage Return character only (\\r), used very rarely" }, { value: "auto", description: `Maintain existing
(mixed values within one file are normalised by looking at what's used after the first line)` }] }, filepath: { category: "Special", type: "path", description: "Specify the input filepath. This will be used to do parser inference.", cliName: "stdin-filepath", cliCategory: "Other", cliDescription: "Path to the file to pretend that stdin comes from." }, insertPragma: { category: "Special", type: "boolean", default: false, description: "Insert @format pragma into file's first docblock comment.", cliCategory: "Other" }, parser: { category: "Global", type: "choice", default: void 0, description: "Which parser to use.", exception: (e2) => typeof e2 == "string" || typeof e2 == "function", choices: [{ value: "flow", description: "Flow" }, { value: "babel", description: "JavaScript" }, { value: "babel-flow", description: "Flow" }, { value: "babel-ts", description: "TypeScript" }, { value: "typescript", description: "TypeScript" }, { value: "acorn", description: "JavaScript" }, { value: "espree", description: "JavaScript" }, { value: "meriyah", description: "JavaScript" }, { value: "css", description: "CSS" }, { value: "less", description: "Less" }, { value: "scss", description: "SCSS" }, { value: "json", description: "JSON" }, { value: "json5", description: "JSON5" }, { value: "jsonc", description: "JSON with Comments" }, { value: "json-stringify", description: "JSON.stringify" }, { value: "graphql", description: "GraphQL" }, { value: "markdown", description: "Markdown" }, { value: "mdx", description: "MDX" }, { value: "vue", description: "Vue" }, { value: "yaml", description: "YAML" }, { value: "glimmer", description: "Ember / Handlebars" }, { value: "html", description: "HTML" }, { value: "angular", description: "Angular" }, { value: "lwc", description: "Lightning Web Components" }, { value: "mjml", description: "MJML" }] }, plugins: { type: "path", array: true, default: [{ value: [] }], category: "Global", description: "Add a plugin. Multiple plugins can be passed as separate `--plugin`s.", exception: (e2) => typeof e2 == "string" || typeof e2 == "object", cliName: "plugin", cliCategory: "Config" }, printWidth: { category: "Global", type: "int", default: 80, description: "The line length where Prettier will try wrap.", range: { start: 0, end: 1 / 0, step: 1 } }, rangeEnd: { category: "Special", type: "int", default: 1 / 0, range: { start: 0, end: 1 / 0, step: 1 }, description: `Format code ending at a given character offset (exclusive).
The range will extend forwards to the end of the selected statement.`, cliCategory: "Editor" }, rangeStart: { category: "Special", type: "int", default: 0, range: { start: 0, end: 1 / 0, step: 1 }, description: `Format code starting at a given character offset.
The range will extend backwards to the start of the first line containing the selected statement.`, cliCategory: "Editor" }, requirePragma: { category: "Special", type: "boolean", default: false, description: "Require either '@prettier' or '@format' to be present in the file's first docblock comment in order for it to be formatted.", cliCategory: "Other" }, tabWidth: { type: "int", category: "Global", default: 2, description: "Number of spaces per indentation level.", range: { start: 0, end: 1 / 0, step: 1 } }, useTabs: { category: "Global", type: "boolean", default: false, description: "Indent with tabs instead of spaces." }, embeddedLanguageFormatting: { category: "Global", type: "choice", default: "auto", description: "Control how Prettier formats quoted code embedded in the file.", choices: [{ value: "auto", description: "Format embedded code if Prettier can automatically identify it." }, { value: "off", description: "Never automatically format embedded code." }] } };
function at$1({ plugins: e2 = [], showDeprecated: t2 = false } = {}) {
  let u2 = e2.flatMap((o2) => o2.languages ?? []), r2 = [];
  for (let o2 of ko(Object.assign({}, ...e2.map(({ options: n3 }) => n3), _r))) !t2 && o2.deprecated || (Array.isArray(o2.choices) && (t2 || (o2.choices = o2.choices.filter((n3) => !n3.deprecated)), o2.name === "parser" && (o2.choices = [...o2.choices, ...Io(o2.choices, u2, e2)])), o2.pluginDefaults = Object.fromEntries(e2.filter((n3) => n3.defaultOptions?.[o2.name] !== void 0).map((n3) => [n3.name, n3.defaultOptions[o2.name]])), r2.push(o2));
  return { languages: u2, options: r2 };
}
function* Io(e2, t2, u2) {
  let r2 = new Set(e2.map((o2) => o2.value));
  for (let o2 of t2) if (o2.parsers) {
    for (let n3 of o2.parsers) if (!r2.has(n3)) {
      r2.add(n3);
      let a2 = u2.find((i) => i.parsers && Object.prototype.hasOwnProperty.call(i.parsers, n3)), s2 = o2.name;
      a2?.name && (s2 += ` (plugin: ${a2.name})`), yield { value: n3, description: s2 };
    }
  }
}
function ko(e2) {
  let t2 = [];
  for (let [u2, r2] of Object.entries(e2)) {
    let o2 = { name: u2, ...r2 };
    Array.isArray(o2.default) && (o2.default = b$2(0, o2.default, -1).value), t2.push(o2);
  }
  return t2;
}
var vo = Array.prototype.toReversed ?? function() {
  return [...this].reverse();
}, Ro = X$1("toReversed", function() {
  if (Array.isArray(this)) return vo;
}), xr = Ro;
function Lo() {
  let e2 = globalThis, t2 = e2.Deno?.build?.os;
  return typeof t2 == "string" ? t2 === "windows" : e2.navigator?.platform?.startsWith("Win") ?? e2.process?.platform?.startsWith("win") ?? false;
}
var Mo = Lo();
function Br(e2) {
  if (e2 = e2 instanceof URL ? e2 : new URL(e2), e2.protocol !== "file:") throw new TypeError(`URL must be a file URL: received "${e2.protocol}"`);
  return e2;
}
function Yo(e2) {
  return e2 = Br(e2), decodeURIComponent(e2.pathname.replace(/%(?![0-9A-Fa-f]{2})/g, "%25"));
}
function jo(e2) {
  e2 = Br(e2);
  let t2 = decodeURIComponent(e2.pathname.replace(/\//g, "\\").replace(/%(?![0-9A-Fa-f]{2})/g, "%25")).replace(/^\\*([A-Za-z]:)(\\|$)/, "$1\\");
  return e2.hostname !== "" && (t2 = `\\\\${e2.hostname}${t2}`), t2;
}
function tu(e2) {
  return Mo ? jo(e2) : Yo(e2);
}
var Tr = (e2) => String(e2).split(/[/\\]/u).pop(), Nr = (e2) => String(e2).startsWith("file:");
function Sr(e2, t2) {
  if (!t2) return;
  let u2 = Tr(t2).toLowerCase();
  return e2.find(({ filenames: r2 }) => r2?.some((o2) => o2.toLowerCase() === u2)) ?? e2.find(({ extensions: r2 }) => r2?.some((o2) => u2.endsWith(o2)));
}
function Uo(e2, t2) {
  if (t2) return e2.find(({ name: u2 }) => u2.toLowerCase() === t2) ?? e2.find(({ aliases: u2 }) => u2?.includes(t2)) ?? e2.find(({ extensions: u2 }) => u2?.includes(`.${t2}`));
}
var Wo = void 0;
function wr(e2, t2) {
  if (t2) {
    if (Nr(t2)) try {
      t2 = tu(t2);
    } catch {
      return;
    }
    if (typeof t2 == "string") return e2.find(({ isSupported: u2 }) => u2?.({ filepath: t2 }));
  }
}
function $o(e2, t2) {
  let u2 = xr(0, e2.plugins).flatMap((o2) => o2.languages ?? []);
  return (Uo(u2, t2.language) ?? Sr(u2, t2.physicalFile) ?? Sr(u2, t2.file) ?? wr(u2, t2.physicalFile) ?? wr(u2, t2.file) ?? Wo?.(u2, t2.physicalFile))?.parsers[0];
}
var it$1 = $o;
var ie$1 = { key: (e2) => /^[$_a-zA-Z][$_a-zA-Z0-9]*$/.test(e2) ? e2 : JSON.stringify(e2), value(e2) {
  if (e2 === null || typeof e2 != "object") return JSON.stringify(e2);
  if (Array.isArray(e2)) return `[${e2.map((u2) => ie$1.value(u2)).join(", ")}]`;
  let t2 = Object.keys(e2);
  return t2.length === 0 ? "{}" : `{ ${t2.map((u2) => `${ie$1.key(u2)}: ${ie$1.value(e2[u2])}`).join(", ")} }`;
}, pair: ({ key: e2, value: t2 }) => ie$1.value({ [e2]: t2 }) };
var uu = new Proxy(String, { get: () => uu }), $$1 = uu, ru = () => uu;
var Or = (e2, t2, { descriptor: u2 }) => {
  let r2 = [`${$$1.yellow(typeof e2 == "string" ? u2.key(e2) : u2.pair(e2))} is deprecated`];
  return t2 && r2.push(`we now treat it as ${$$1.blue(typeof t2 == "string" ? u2.key(t2) : u2.pair(t2))}`), r2.join("; ") + ".";
};
var st$1 = Symbol.for("vnopts.VALUE_NOT_EXIST"), be$1 = Symbol.for("vnopts.VALUE_UNCHANGED");
var Pr = " ".repeat(2), kr = (e2, t2, u2) => {
  let { text: r2, list: o2 } = u2.normalizeExpectedResult(u2.schemas[e2].expected(u2)), n3 = [];
  return r2 && n3.push(Ir(e2, t2, r2, u2.descriptor)), o2 && n3.push([Ir(e2, t2, o2.title, u2.descriptor)].concat(o2.values.map((a2) => vr(a2, u2.loggerPrintWidth))).join(`
`)), Rr(n3, u2.loggerPrintWidth);
};
function Ir(e2, t2, u2, r2) {
  return [`Invalid ${$$1.red(r2.key(e2))} value.`, `Expected ${$$1.blue(u2)},`, `but received ${t2 === st$1 ? $$1.gray("nothing") : $$1.red(r2.value(t2))}.`].join(" ");
}
function vr({ text: e2, list: t2 }, u2) {
  let r2 = [];
  return e2 && r2.push(`- ${$$1.blue(e2)}`), t2 && r2.push([`- ${$$1.blue(t2.title)}:`].concat(t2.values.map((o2) => vr(o2, u2 - Pr.length).replace(/^|\n/g, `$&${Pr}`))).join(`
`)), Rr(r2, u2);
}
function Rr(e2, t2) {
  if (e2.length === 1) return e2[0];
  let [u2, r2] = e2, [o2, n3] = e2.map((a2) => a2.split(`
`, 1)[0].length);
  return o2 > t2 && o2 > n3 ? r2 : u2;
}
var Ae = [], nu = [];
function Dt$1(e2, t2, u2) {
  if (e2 === t2) return 0;
  let r2 = u2?.maxDistance, o2 = e2;
  e2.length > t2.length && (e2 = t2, t2 = o2);
  let n3 = e2.length, a2 = t2.length;
  for (; n3 > 0 && e2.charCodeAt(~-n3) === t2.charCodeAt(~-a2); ) n3--, a2--;
  let s2 = 0;
  for (; s2 < n3 && e2.charCodeAt(s2) === t2.charCodeAt(s2); ) s2++;
  if (n3 -= s2, a2 -= s2, r2 !== void 0 && a2 - n3 > r2) return r2;
  if (n3 === 0) return r2 !== void 0 && a2 > r2 ? r2 : a2;
  let i, D2, f3, l2, d = 0, c2 = 0;
  for (; d < n3; ) nu[d] = e2.charCodeAt(s2 + d), Ae[d] = ++d;
  for (; c2 < a2; ) {
    for (i = t2.charCodeAt(s2 + c2), f3 = c2++, D2 = c2, d = 0; d < n3; d++) l2 = i === nu[d] ? f3 : f3 + 1, f3 = Ae[d], D2 = Ae[d] = f3 > D2 ? l2 > D2 ? D2 + 1 : l2 : l2 > f3 ? f3 + 1 : l2;
    if (r2 !== void 0) {
      let p2 = D2;
      for (d = 0; d < n3; d++) Ae[d] < p2 && (p2 = Ae[d]);
      if (p2 > r2) return r2;
    }
  }
  return Ae.length = n3, nu.length = n3, r2 !== void 0 && D2 > r2 ? r2 : D2;
}
function Lr(e2, t2, u2) {
  if (!Array.isArray(t2) || t2.length === 0) return;
  let r2 = u2?.maxDistance, o2 = e2.length;
  for (let i of t2) if (i === e2) return i;
  let n3, a2 = Number.POSITIVE_INFINITY, s2 = /* @__PURE__ */ new Set();
  for (let i of t2) {
    if (s2.has(i)) continue;
    s2.add(i);
    let D2 = Math.abs(i.length - o2);
    if (D2 >= a2 || D2 > r2) continue;
    let f3 = Number.isFinite(a2) ? Math.min(a2, r2) : r2, l2 = f3 === void 0 ? Dt$1(e2, i) : Dt$1(e2, i, { maxDistance: f3 });
    if (l2 > r2) continue;
    let d = l2;
    if (f3 !== void 0 && l2 === f3 && f3 === r2 && (d = Dt$1(e2, i)), d < a2 && (a2 = d, n3 = i, a2 === 0)) break;
  }
  if (!(a2 > r2)) return n3;
}
var ct$1 = (e2, t2, { descriptor: u2, logger: r2, schemas: o2 }) => {
  let n3 = [`Ignored unknown option ${$$1.yellow(u2.pair({ key: e2, value: t2 }))}.`], a2 = Lr(e2, Object.keys(o2), { maxDistance: 3 });
  a2 && n3.push(`Did you mean ${$$1.blue(u2.key(a2))}?`), r2.warn(n3.join(" "));
};
var Vo = ["default", "expected", "validate", "deprecated", "forward", "redirect", "overlap", "preprocess", "postprocess"];
function Ko(e2, t2) {
  let u2 = new e2(t2), r2 = Object.create(u2);
  for (let o2 of Vo) o2 in t2 && (r2[o2] = Jo(t2[o2], u2, S$3.prototype[o2].length));
  return r2;
}
var S$3 = class S {
  static create(t2) {
    return Ko(this, t2);
  }
  constructor(t2) {
    this.name = t2.name;
  }
  default(t2) {
  }
  expected(t2) {
    return "nothing";
  }
  validate(t2, u2) {
    return false;
  }
  deprecated(t2, u2) {
    return false;
  }
  forward(t2, u2) {
  }
  redirect(t2, u2) {
  }
  overlap(t2, u2, r2) {
    return t2;
  }
  preprocess(t2, u2) {
    return t2;
  }
  postprocess(t2, u2) {
    return be$1;
  }
};
function Jo(e2, t2, u2) {
  return typeof e2 == "function" ? (...r2) => e2(...r2.slice(0, u2 - 1), t2, ...r2.slice(u2 - 1)) : () => e2;
}
var ft$1 = class ft extends S$3 {
  constructor(t2) {
    super(t2), this._sourceName = t2.sourceName;
  }
  expected(t2) {
    return t2.schemas[this._sourceName].expected(t2);
  }
  validate(t2, u2) {
    return u2.schemas[this._sourceName].validate(t2, u2);
  }
  redirect(t2, u2) {
    return this._sourceName;
  }
};
var lt$1 = class lt extends S$3 {
  expected() {
    return "anything";
  }
  validate() {
    return true;
  }
};
var dt$1 = class dt extends S$3 {
  constructor({ valueSchema: t2, name: u2 = t2.name, ...r2 }) {
    super({ ...r2, name: u2 }), this._valueSchema = t2;
  }
  expected(t2) {
    let { text: u2, list: r2 } = t2.normalizeExpectedResult(this._valueSchema.expected(t2));
    return { text: u2 && `an array of ${u2}`, list: r2 && { title: "an array of the following values", values: [{ list: r2 }] } };
  }
  validate(t2, u2) {
    if (!Array.isArray(t2)) return false;
    let r2 = [];
    for (let o2 of t2) {
      let n3 = u2.normalizeValidateResult(this._valueSchema.validate(o2, u2), o2);
      n3 !== true && r2.push(n3.value);
    }
    return r2.length === 0 ? true : { value: r2 };
  }
  deprecated(t2, u2) {
    let r2 = [];
    for (let o2 of t2) {
      let n3 = u2.normalizeDeprecatedResult(this._valueSchema.deprecated(o2, u2), o2);
      n3 !== false && r2.push(...n3.map(({ value: a2 }) => ({ value: [a2] })));
    }
    return r2;
  }
  forward(t2, u2) {
    let r2 = [];
    for (let o2 of t2) {
      let n3 = u2.normalizeForwardResult(this._valueSchema.forward(o2, u2), o2);
      r2.push(...n3.map(Mr));
    }
    return r2;
  }
  redirect(t2, u2) {
    let r2 = [], o2 = [];
    for (let n3 of t2) {
      let a2 = u2.normalizeRedirectResult(this._valueSchema.redirect(n3, u2), n3);
      "remain" in a2 && r2.push(a2.remain), o2.push(...a2.redirect.map(Mr));
    }
    return r2.length === 0 ? { redirect: o2 } : { redirect: o2, remain: r2 };
  }
  overlap(t2, u2) {
    return t2.concat(u2);
  }
};
function Mr({ from: e2, to: t2 }) {
  return { from: [e2], to: t2 };
}
var pt$1 = class pt extends S$3 {
  expected() {
    return "true or false";
  }
  validate(t2) {
    return typeof t2 == "boolean";
  }
};
function jr(e2, t2) {
  let u2 = /* @__PURE__ */ Object.create(null);
  for (let r2 of e2) {
    let o2 = r2[t2];
    if (u2[o2]) throw new Error(`Duplicate ${t2} ${JSON.stringify(o2)}`);
    u2[o2] = r2;
  }
  return u2;
}
function Ur(e2, t2) {
  let u2 = /* @__PURE__ */ new Map();
  for (let r2 of e2) {
    let o2 = r2[t2];
    if (u2.has(o2)) throw new Error(`Duplicate ${t2} ${JSON.stringify(o2)}`);
    u2.set(o2, r2);
  }
  return u2;
}
function Wr() {
  let e2 = /* @__PURE__ */ Object.create(null);
  return (t2) => {
    let u2 = JSON.stringify(t2);
    return e2[u2] ? true : (e2[u2] = true, false);
  };
}
function $r(e2, t2) {
  let u2 = [], r2 = [];
  for (let o2 of e2) t2(o2) ? u2.push(o2) : r2.push(o2);
  return [u2, r2];
}
function Vr(e2) {
  return e2 === Math.floor(e2);
}
function Kr(e2, t2) {
  if (e2 === t2) return 0;
  let u2 = typeof e2, r2 = typeof t2, o2 = ["undefined", "object", "boolean", "number", "string"];
  return u2 !== r2 ? o2.indexOf(u2) - o2.indexOf(r2) : u2 !== "string" ? Number(e2) - Number(t2) : e2.localeCompare(t2);
}
function Jr(e2) {
  return (...t2) => {
    let u2 = e2(...t2);
    return typeof u2 == "string" ? new Error(u2) : u2;
  };
}
function ou(e2) {
  return e2 === void 0 ? {} : e2;
}
function au(e2) {
  if (typeof e2 == "string") return { text: e2 };
  let { text: t2, list: u2 } = e2;
  return Go((t2 || u2) !== void 0, "Unexpected `expected` result, there should be at least one field."), u2 ? { text: t2, list: { title: u2.title, values: u2.values.map(au) } } : { text: t2 };
}
function iu(e2, t2) {
  return e2 === true ? true : e2 === false ? { value: t2 } : e2;
}
function su(e2, t2, u2 = false) {
  return e2 === false ? false : e2 === true ? u2 ? true : [{ value: t2 }] : "value" in e2 ? [e2] : e2.length === 0 ? false : e2;
}
function Yr(e2, t2) {
  return typeof e2 == "string" || "key" in e2 ? { from: t2, to: e2 } : "from" in e2 ? { from: e2.from, to: e2.to } : { from: t2, to: e2.to };
}
function Ft$1(e2, t2) {
  return e2 === void 0 ? [] : Array.isArray(e2) ? e2.map((u2) => Yr(u2, t2)) : [Yr(e2, t2)];
}
function Du(e2, t2) {
  let u2 = Ft$1(typeof e2 == "object" && "redirect" in e2 ? e2.redirect : e2, t2);
  return u2.length === 0 ? { remain: t2, redirect: u2 } : typeof e2 == "object" && "remain" in e2 ? { remain: e2.remain, redirect: u2 } : { redirect: u2 };
}
function Go(e2, t2) {
  if (!e2) throw new Error(t2);
}
var mt$1 = class mt extends S$3 {
  constructor(t2) {
    super(t2), this._choices = Ur(t2.choices.map((u2) => u2 && typeof u2 == "object" ? u2 : { value: u2 }), "value");
  }
  expected({ descriptor: t2 }) {
    let u2 = Array.from(this._choices.keys()).map((a2) => this._choices.get(a2)).filter(({ hidden: a2 }) => !a2).map((a2) => a2.value).sort(Kr).map(t2.value), r2 = u2.slice(0, -2), o2 = u2.slice(-2);
    return { text: r2.concat(o2.join(" or ")).join(", "), list: { title: "one of the following values", values: u2 } };
  }
  validate(t2) {
    return this._choices.has(t2);
  }
  deprecated(t2) {
    let u2 = this._choices.get(t2);
    return u2 && u2.deprecated ? { value: t2 } : false;
  }
  forward(t2) {
    let u2 = this._choices.get(t2);
    return u2 ? u2.forward : void 0;
  }
  redirect(t2) {
    let u2 = this._choices.get(t2);
    return u2 ? u2.redirect : void 0;
  }
};
var Et$1 = class Et extends S$3 {
  expected() {
    return "a number";
  }
  validate(t2, u2) {
    return typeof t2 == "number";
  }
};
var Ct$1 = class Ct extends Et$1 {
  expected() {
    return "an integer";
  }
  validate(t2, u2) {
    return u2.normalizeValidateResult(super.validate(t2, u2), t2) === true && Vr(t2);
  }
};
var Ye$1 = class Ye extends S$3 {
  expected() {
    return "a string";
  }
  validate(t2) {
    return typeof t2 == "string";
  }
};
var Gr = ie$1, zr = ct$1, Hr = kr, Xr = Or;
var ht$1 = class ht {
  constructor(t2, u2) {
    let { logger: r2 = console, loggerPrintWidth: o2 = 80, descriptor: n3 = Gr, unknown: a2 = zr, invalid: s2 = Hr, deprecated: i = Xr, missing: D2 = () => false, required: f3 = () => false, preprocess: l2 = (c2) => c2, postprocess: d = () => be$1 } = u2 || {};
    this._utils = { descriptor: n3, logger: r2 || { warn: () => {
    } }, loggerPrintWidth: o2, schemas: jr(t2, "name"), normalizeDefaultResult: ou, normalizeExpectedResult: au, normalizeDeprecatedResult: su, normalizeForwardResult: Ft$1, normalizeRedirectResult: Du, normalizeValidateResult: iu }, this._unknownHandler = a2, this._invalidHandler = Jr(s2), this._deprecatedHandler = i, this._identifyMissing = (c2, p2) => !(c2 in p2) || D2(c2, p2), this._identifyRequired = f3, this._preprocess = l2, this._postprocess = d, this.cleanHistory();
  }
  cleanHistory() {
    this._hasDeprecationWarned = Wr();
  }
  normalize(t2) {
    let u2 = {}, o2 = [this._preprocess(t2, this._utils)], n3 = () => {
      for (; o2.length !== 0; ) {
        let a2 = o2.shift(), s2 = this._applyNormalization(a2, u2);
        o2.push(...s2);
      }
    };
    n3();
    for (let a2 of Object.keys(this._utils.schemas)) {
      let s2 = this._utils.schemas[a2];
      if (!(a2 in u2)) {
        let i = ou(s2.default(this._utils));
        "value" in i && o2.push({ [a2]: i.value });
      }
    }
    n3();
    for (let a2 of Object.keys(this._utils.schemas)) {
      if (!(a2 in u2)) continue;
      let s2 = this._utils.schemas[a2], i = u2[a2], D2 = s2.postprocess(i, this._utils);
      D2 !== be$1 && (this._applyValidation(D2, a2, s2), u2[a2] = D2);
    }
    return this._applyPostprocess(u2), this._applyRequiredCheck(u2), u2;
  }
  _applyNormalization(t2, u2) {
    let r2 = [], { knownKeys: o2, unknownKeys: n3 } = this._partitionOptionKeys(t2);
    for (let a2 of o2) {
      let s2 = this._utils.schemas[a2], i = s2.preprocess(t2[a2], this._utils);
      this._applyValidation(i, a2, s2);
      let D2 = ({ from: c2, to: p2 }) => {
        r2.push(typeof p2 == "string" ? { [p2]: c2 } : { [p2.key]: p2.value });
      }, f3 = ({ value: c2, redirectTo: p2 }) => {
        let F2 = su(s2.deprecated(c2, this._utils), i, true);
        if (F2 !== false) if (F2 === true) this._hasDeprecationWarned(a2) || this._utils.logger.warn(this._deprecatedHandler(a2, p2, this._utils));
        else for (let { value: C2 } of F2) {
          let y2 = { key: a2, value: C2 };
          if (!this._hasDeprecationWarned(y2)) {
            let m3 = typeof p2 == "string" ? { key: p2, value: C2 } : p2;
            this._utils.logger.warn(this._deprecatedHandler(y2, m3, this._utils));
          }
        }
      };
      Ft$1(s2.forward(i, this._utils), i).forEach(D2);
      let d = Du(s2.redirect(i, this._utils), i);
      if (d.redirect.forEach(D2), "remain" in d) {
        let c2 = d.remain;
        u2[a2] = a2 in u2 ? s2.overlap(u2[a2], c2, this._utils) : c2, f3({ value: c2 });
      }
      for (let { from: c2, to: p2 } of d.redirect) f3({ value: c2, redirectTo: p2 });
    }
    for (let a2 of n3) {
      let s2 = t2[a2];
      this._applyUnknownHandler(a2, s2, u2, (i, D2) => {
        r2.push({ [i]: D2 });
      });
    }
    return r2;
  }
  _applyRequiredCheck(t2) {
    for (let u2 of Object.keys(this._utils.schemas)) if (this._identifyMissing(u2, t2) && this._identifyRequired(u2)) throw this._invalidHandler(u2, st$1, this._utils);
  }
  _partitionOptionKeys(t2) {
    let [u2, r2] = $r(Object.keys(t2).filter((o2) => !this._identifyMissing(o2, t2)), (o2) => o2 in this._utils.schemas);
    return { knownKeys: u2, unknownKeys: r2 };
  }
  _applyValidation(t2, u2, r2) {
    let o2 = iu(r2.validate(t2, this._utils), t2);
    if (o2 !== true) throw this._invalidHandler(u2, o2.value, this._utils);
  }
  _applyUnknownHandler(t2, u2, r2, o2) {
    let n3 = this._unknownHandler(t2, u2, this._utils);
    if (n3) for (let a2 of Object.keys(n3)) {
      if (this._identifyMissing(a2, n3)) continue;
      let s2 = n3[a2];
      a2 in this._utils.schemas ? o2(a2, s2) : r2[a2] = s2;
    }
  }
  _applyPostprocess(t2) {
    let u2 = this._postprocess(t2, this._utils);
    if (u2 !== be$1) {
      if (u2.delete) for (let r2 of u2.delete) delete t2[r2];
      if (u2.override) {
        let { knownKeys: r2, unknownKeys: o2 } = this._partitionOptionKeys(u2.override);
        for (let n3 of r2) {
          let a2 = u2.override[n3];
          this._applyValidation(a2, n3, this._utils.schemas[n3]), t2[n3] = a2;
        }
        for (let n3 of o2) {
          let a2 = u2.override[n3];
          this._applyUnknownHandler(n3, a2, t2, (s2, i) => {
            let D2 = this._utils.schemas[s2];
            this._applyValidation(i, s2, D2), t2[s2] = i;
          });
        }
      }
    }
  }
};
var cu;
function Ho(e2, t2, { logger: u2 = false, isCLI: r2 = false, passThrough: o2 = false, FlagSchema: n3, descriptor: a2 } = {}) {
  if (r2) {
    if (!n3) throw new Error("'FlagSchema' option is required.");
    if (!a2) throw new Error("'descriptor' option is required.");
  } else a2 = ie$1;
  let s2 = o2 ? Array.isArray(o2) ? (d, c2) => o2.includes(d) ? { [d]: c2 } : void 0 : (d, c2) => ({ [d]: c2 }) : (d, c2, p2) => {
    let { _: F2, ...C2 } = p2.schemas;
    return ct$1(d, c2, { ...p2, schemas: C2 });
  }, i = Xo(t2, { isCLI: r2, FlagSchema: n3 }), D2 = new ht$1(i, { logger: u2, unknown: s2, descriptor: a2 }), f3 = u2 !== false;
  f3 && cu && (D2._hasDeprecationWarned = cu);
  let l2 = D2.normalize(e2);
  return f3 && (cu = D2._hasDeprecationWarned), l2;
}
function Xo(e2, { isCLI: t2, FlagSchema: u2 }) {
  let r2 = [];
  t2 && r2.push(lt$1.create({ name: "_" }));
  for (let o2 of e2) r2.push(qo(o2, { isCLI: t2, optionInfos: e2, FlagSchema: u2 })), o2.alias && t2 && r2.push(ft$1.create({ name: o2.alias, sourceName: o2.name }));
  return r2;
}
function qo(e2, { isCLI: t2, optionInfos: u2, FlagSchema: r2 }) {
  let { name: o2 } = e2, n3 = { name: o2 }, a2, s2 = {};
  switch (e2.type) {
    case "int":
      a2 = Ct$1, t2 && (n3.preprocess = Number);
      break;
    case "string":
      a2 = Ye$1;
      break;
    case "choice":
      a2 = mt$1, n3.choices = e2.choices.map((i) => i?.redirect ? { ...i, redirect: { to: { key: e2.name, value: i.redirect } } } : i);
      break;
    case "boolean":
      a2 = pt$1;
      break;
    case "flag":
      a2 = r2, n3.flags = u2.flatMap((i) => [i.alias, i.description && i.name, i.oppositeDescription && `no-${i.name}`].filter(Boolean));
      break;
    case "path":
      a2 = Ye$1;
      break;
    default:
      throw new Error(`Unexpected type ${e2.type}`);
  }
  if (e2.exception ? n3.validate = (i, D2, f3) => e2.exception(i) || D2.validate(i, f3) : n3.validate = (i, D2, f3) => i === void 0 || D2.validate(i, f3), e2.redirect && (s2.redirect = (i) => i ? { to: typeof e2.redirect == "string" ? e2.redirect : { key: e2.redirect.option, value: e2.redirect.value } } : void 0), e2.deprecated && (s2.deprecated = true), t2 && !e2.array) {
    let i = n3.preprocess || ((D2) => D2);
    n3.preprocess = (D2, f3, l2) => f3.preprocess(i(Array.isArray(D2) ? b$2(0, D2, -1) : D2), l2);
  }
  return e2.array ? dt$1.create({ ...t2 ? { preprocess: (i) => Array.isArray(i) ? i : [i] } : {}, ...s2, valueSchema: a2.create(n3) }) : a2.create({ ...n3, ...s2 });
}
var qr = Ho;
var Qo = Array.prototype.findLast ?? function(e2) {
  for (let t2 = this.length - 1; t2 >= 0; t2--) {
    let u2 = this[t2];
    if (e2(u2, t2, this)) return u2;
  }
}, Zo = X$1("findLast", function() {
  if (Array.isArray(this)) return Qo;
}), fu = Zo;
var Qr = Symbol.for("PRETTIER_IS_FRONT_MATTER"), lu = [];
function ea(e2) {
  return !!e2?.[Qr];
}
var de$1 = ea;
var Zr = /* @__PURE__ */ new Set(["yaml", "toml"]), je = ({ node: e2 }) => de$1(e2) && Zr.has(e2.language);
async function du(e2, t2, u2, r2) {
  let { node: o2 } = u2, { language: n3 } = o2;
  if (!Zr.has(n3)) return;
  let a2 = o2.value.trim(), s2;
  if (a2) {
    let i = n3 === "yaml" ? n3 : it$1(r2, { language: n3 });
    if (!i) return;
    s2 = a2 ? await e2(a2, { parser: i }) : "";
  } else s2 = a2;
  return Qe$1([o2.startDelimiter, o2.explicitLanguage ?? "", V$1, s2, s2 ? V$1 : "", o2.endDelimiter]);
}
function ta(e2, t2) {
  return je({ node: e2 }) && (delete t2.end, delete t2.raw, delete t2.value), t2;
}
var pu = ta;
function ua({ node: e2 }) {
  return e2.raw;
}
var Fu = ua;
var en = /* @__PURE__ */ new Set(["tokens", "comments", "parent", "enclosingNode", "precedingNode", "followingNode"]), ra = (e2) => Object.keys(e2).filter((t2) => !en.has(t2));
function na(e2, t2) {
  let u2 = e2 ? (r2) => e2(r2, en) : ra;
  return t2 ? new Proxy(u2, { apply: (r2, o2, n3) => de$1(n3[0]) ? lu : Reflect.apply(r2, o2, n3) }) : u2;
}
var mu = na;
function Cu(e2, t2) {
  if (!t2) throw new Error("parserName is required.");
  let u2 = fu(0, e2, (o2) => o2.parsers && Object.prototype.hasOwnProperty.call(o2.parsers, t2));
  if (u2) return u2;
  let r2 = `Couldn't resolve parser "${t2}".`;
  throw r2 += " Plugins must be explicitly added to the standalone bundle.", new Le(r2);
}
function tn(e2, t2) {
  if (!t2) throw new Error("astFormat is required.");
  let u2 = fu(0, e2, (o2) => o2.printers && Object.prototype.hasOwnProperty.call(o2.printers, t2));
  if (u2) return u2;
  let r2 = `Couldn't find plugin for AST format "${t2}".`;
  throw r2 += " Plugins must be explicitly added to the standalone bundle.", new Le(r2);
}
function Ue$1({ plugins: e2, parser: t2 }) {
  let u2 = Cu(e2, t2);
  return hu(u2, t2);
}
function hu(e2, t2) {
  let u2 = e2.parsers[t2];
  return typeof u2 == "function" ? u2() : u2;
}
async function un(e2, t2) {
  let u2 = e2.printers[t2], r2 = typeof u2 == "function" ? await u2() : u2;
  return oa(r2);
}
var Eu = /* @__PURE__ */ new WeakMap();
function oa(e2) {
  if (Eu.has(e2)) return Eu.get(e2);
  let { features: t2, getVisitorKeys: u2, embed: r2, massageAstNode: o2, print: n3, ...a2 } = e2;
  t2 = Da(t2);
  let s2 = t2.experimental_frontMatterSupport;
  u2 = mu(u2, s2.massageAstNode || s2.embed || s2.print);
  let i = o2;
  o2 && s2.massageAstNode && (i = new Proxy(o2, { apply(d, c2, p2) {
    return pu(...p2), Reflect.apply(d, c2, p2);
  } }));
  let D2 = r2;
  if (r2) {
    let d;
    D2 = new Proxy(r2, { get(c2, p2, F2) {
      return p2 === "getVisitorKeys" ? (d ?? (d = r2.getVisitorKeys ? mu(r2.getVisitorKeys, s2.massageAstNode || s2.embed) : u2), d) : Reflect.get(c2, p2, F2);
    }, apply: (c2, p2, F2) => s2.embed && je(...F2) ? du : Reflect.apply(c2, p2, F2) });
  }
  let f3 = n3;
  s2.print && (f3 = new Proxy(n3, { apply(d, c2, p2) {
    let [F2] = p2;
    return de$1(F2.node) ? Fu(F2) : Reflect.apply(d, c2, p2);
  } }));
  let l2 = { features: t2, getVisitorKeys: u2, embed: D2, massageAstNode: i, print: f3, ...a2 };
  return Eu.set(e2, l2), l2;
}
var aa = ["clean", "embed", "print"], ia = Object.fromEntries(aa.map((e2) => [e2, false]));
function sa(e2) {
  return { ...ia, ...e2 };
}
function Da(e2) {
  return { experimental_avoidAstMutation: false, ...e2, experimental_frontMatterSupport: sa(e2?.experimental_frontMatterSupport) };
}
var rn = { astFormat: "estree", printer: {}, originalText: void 0, locStart: null, locEnd: null, getVisitorKeys: null };
async function ca(e2, t2 = {}) {
  let u2 = { ...e2 };
  if (!u2.parser) if (u2.filepath) {
    if (u2.parser = it$1(u2, { physicalFile: u2.filepath }), !u2.parser) throw new Me(`No parser could be inferred for file "${u2.filepath}".`);
  } else throw new Me("No parser and no file path given, couldn't infer a parser.");
  let r2 = at$1({ plugins: e2.plugins, showDeprecated: true }).options, o2 = { ...rn, ...Object.fromEntries(r2.filter((l2) => l2.default !== void 0).map((l2) => [l2.name, l2.default])) }, n3 = Cu(u2.plugins, u2.parser), a2 = await hu(n3, u2.parser);
  u2.astFormat = a2.astFormat, u2.locEnd = a2.locEnd, u2.locStart = a2.locStart;
  let s2 = n3.printers?.[a2.astFormat] ? n3 : tn(u2.plugins, a2.astFormat), i = await un(s2, a2.astFormat);
  u2.printer = i, u2.getVisitorKeys = i.getVisitorKeys;
  let D2 = s2.defaultOptions ? Object.fromEntries(Object.entries(s2.defaultOptions).filter(([, l2]) => l2 !== void 0)) : {}, f3 = { ...o2, ...D2 };
  for (let [l2, d] of Object.entries(f3)) (u2[l2] === null || u2[l2] === void 0) && (u2[l2] = d);
  return u2.parser === "json" && (u2.trailingComma = "none"), qr(u2, r2, { passThrough: Object.keys(rn), ...t2 });
}
var se$1 = ca;
Zn(sn());
var bu = { keyword: ["break", "case", "catch", "continue", "debugger", "default", "do", "else", "finally", "for", "function", "if", "return", "switch", "throw", "try", "var", "const", "while", "with", "new", "this", "super", "class", "extends", "export", "import", "null", "true", "false", "in", "instanceof", "typeof", "void", "delete"], strict: ["implements", "interface", "let", "package", "private", "protected", "public", "static", "yield"], strictBind: ["eval", "arguments"] };
new Set(bu.keyword);
new Set(bu.strict);
new Set(bu.strictBind);
var Pt$1 = (e2, t2) => (u2) => e2(t2(u2));
function fn(e2) {
  return { keyword: e2.cyan, capitalized: e2.yellow, jsxIdentifier: e2.yellow, punctuator: e2.yellow, number: e2.magenta, string: e2.green, regex: e2.magenta, comment: e2.gray, invalid: Pt$1(Pt$1(e2.white, e2.bgRed), e2.bold), gutter: e2.gray, marker: Pt$1(e2.red, e2.bold), message: Pt$1(e2.red, e2.bold), reset: e2.reset };
}
fn(ru());
fn(ru());
function la() {
  return new Proxy({}, { get: () => (e2) => e2 });
}
var cn = /\r\n|[\n\r\u2028\u2029]/;
function da(e2, t2, u2) {
  let r2 = Object.assign({ column: 0, line: -1 }, e2.start), o2 = Object.assign({}, r2, e2.end), { linesAbove: n3 = 2, linesBelow: a2 = 3 } = u2 || {}, s2 = r2.line, i = r2.column, D2 = o2.line, f3 = o2.column, l2 = Math.max(s2 - (n3 + 1), 0), d = Math.min(t2.length, D2 + a2);
  s2 === -1 && (l2 = 0), D2 === -1 && (d = t2.length);
  let c2 = D2 - s2, p2 = {};
  if (c2) for (let F2 = 0; F2 <= c2; F2++) {
    let C2 = F2 + s2;
    if (!i) p2[C2] = true;
    else if (F2 === 0) {
      let y2 = t2[C2 - 1].length;
      p2[C2] = [i, y2 - i + 1];
    } else if (F2 === c2) p2[C2] = [0, f3];
    else {
      let y2 = t2[C2 - F2].length;
      p2[C2] = [0, y2];
    }
  }
  else i === f3 ? i ? p2[s2] = [i, 0] : p2[s2] = true : p2[s2] = [i, f3 - i];
  return { start: l2, end: d, markerLines: p2 };
}
function ln(e2, t2, u2 = {}) {
  let o2 = la(), n3 = e2.split(cn), { start: a2, end: s2, markerLines: i } = da(t2, n3, u2), D2 = t2.start && typeof t2.start.column == "number", f3 = String(s2).length, d = e2.split(cn, s2).slice(a2, s2).map((c2, p2) => {
    let F2 = a2 + 1 + p2, y2 = ` ${` ${F2}`.slice(-f3)} |`, m3 = i[F2], h2 = !i[F2 + 1];
    if (m3) {
      let E2 = "";
      if (Array.isArray(m3)) {
        let g2 = c2.slice(0, Math.max(m3[0] - 1, 0)).replace(/[^\t]/g, " "), A2 = m3[1] || 1;
        E2 = [`
 `, o2.gutter(y2.replace(/\d/g, " ")), " ", g2, o2.marker("^").repeat(A2)].join(""), h2 && u2.message && (E2 += " " + o2.message(u2.message));
      }
      return [o2.marker(">"), o2.gutter(y2), c2.length > 0 ? ` ${c2}` : "", E2].join("");
    } else return ` ${o2.gutter(y2)}${c2.length > 0 ? ` ${c2}` : ""}`;
  }).join(`
`);
  return u2.message && !D2 && (d = `${" ".repeat(f3 + 1)}${u2.message}
${d}`), d;
}
async function pa(e2, t2) {
  let u2 = await Ue$1(t2), r2 = u2.preprocess ? await u2.preprocess(e2, t2) : e2;
  t2.originalText = r2;
  let o2;
  try {
    o2 = await u2.parse(r2, t2, t2);
  } catch (n3) {
    Fa(n3, e2);
  }
  return { text: r2, ast: o2 };
}
function Fa(e2, t2) {
  let { loc: u2 } = e2;
  if (u2) {
    let r2 = ln(t2, u2, {});
    throw e2.message += `
` + r2, e2.codeFrame = r2, e2;
  }
  throw e2;
}
var Fe$1 = pa;
async function dn(e2, t2, u2, r2, o2) {
  if (u2.embeddedLanguageFormatting !== "auto") return;
  let { printer: n3 } = u2, { embed: a2 } = n3;
  if (!a2) return;
  if (a2.length > 2) throw new Error("printer.embed has too many parameters. The API changed in Prettier v3. Please update your plugin. See https://prettier.io/docs/plugins#optional-embed");
  let { hasPrettierIgnore: s2 } = n3, { getVisitorKeys: i } = a2, D2 = [];
  d();
  let f3 = e2.stack;
  for (let { print: c2, node: p2, pathStack: F2 } of D2) try {
    e2.stack = F2;
    let C2 = await c2(l2, t2, e2, u2);
    C2 && o2.set(p2, C2);
  } catch (C2) {
    if (globalThis.PRETTIER_DEBUG) throw C2;
  }
  e2.stack = f3;
  function l2(c2, p2) {
    return ma(c2, p2, u2, r2);
  }
  function d() {
    let { node: c2 } = e2;
    if (c2 === null || typeof c2 != "object" || s2?.(e2)) return;
    for (let F2 of i(c2)) Array.isArray(c2[F2]) ? e2.each(d, F2) : e2.call(d, F2);
    let p2 = a2(e2, u2);
    if (p2) {
      if (typeof p2 == "function") {
        D2.push({ print: p2, node: c2, pathStack: [...e2.stack] });
        return;
      }
      o2.set(c2, p2);
    }
  }
}
async function ma(e2, t2, u2, r2) {
  let o2 = await se$1({ ...u2, ...t2, parentParser: u2.parser, originalText: e2, cursorOffset: void 0, rangeStart: void 0, rangeEnd: void 0 }, { passThrough: true }), { ast: n3 } = await Fe$1(e2, o2), a2 = await r2(n3, o2);
  return He$1(a2);
}
function Ea(e2, t2, u2, r2) {
  let { originalText: o2, [Symbol.for("comments")]: n3, locStart: a2, locEnd: s2, [Symbol.for("printedComments")]: i } = t2, { node: D2 } = e2, f3 = a2(D2), l2 = s2(D2);
  for (let c2 of n3) a2(c2) >= f3 && s2(c2) <= l2 && i.add(c2);
  let { printPrettierIgnored: d } = t2.printer;
  return d ? d(e2, t2, u2, r2) : o2.slice(f3, l2);
}
var pn = Ea;
async function Ke$1(e2, t2) {
  ({ ast: e2 } = await Au(e2, t2));
  let u2 = /* @__PURE__ */ new Map(), r2 = new sr(e2), n3 = /* @__PURE__ */ new Map();
  await dn(r2, s2, t2, Ke$1, n3);
  let a2 = await Fn(r2, t2, s2, void 0, n3);
  if (br(t2), t2.cursorOffset >= 0) {
    if (t2.nodeAfterCursor && !t2.nodeBeforeCursor) return [ee$1, a2];
    if (t2.nodeBeforeCursor && !t2.nodeAfterCursor) return [a2, ee$1];
  }
  return a2;
  function s2(D2, f3) {
    return D2 === void 0 || D2 === r2 ? i(f3) : Array.isArray(D2) ? r2.call(() => i(f3), ...D2) : r2.call(() => i(f3), D2);
  }
  function i(D2) {
    let f3 = r2.node;
    if (f3 == null) return "";
    let l2 = f3 && typeof f3 == "object" && D2 === void 0;
    if (l2 && u2.has(f3)) return u2.get(f3);
    let d = Fn(r2, t2, s2, D2, n3);
    return l2 && u2.set(f3, d), d;
  }
}
function Fn(e2, t2, u2, r2, o2) {
  let { node: n3 } = e2, { printer: a2 } = t2, s2;
  switch (a2.hasPrettierIgnore?.(e2) ? s2 = pn(e2, t2, u2, r2) : o2.has(n3) ? s2 = o2.get(n3) : s2 = a2.print(e2, t2, u2, r2), n3) {
    case t2.cursorNode:
      s2 = Ee$1(s2, (i) => [ee$1, i, ee$1]);
      break;
    case t2.nodeBeforeCursor:
      s2 = Ee$1(s2, (i) => [i, ee$1]);
      break;
    case t2.nodeAfterCursor:
      s2 = Ee$1(s2, (i) => [ee$1, i]);
      break;
  }
  return a2.printComment && !a2.willPrintOwnComments?.(e2, t2) && (s2 = yr(e2, s2, t2)), s2;
}
async function Au(e2, t2) {
  let u2 = e2.comments ?? [];
  t2[Symbol.for("comments")] = u2, t2[Symbol.for("printedComments")] = /* @__PURE__ */ new Set(), Cr(e2, t2);
  let { printer: { preprocess: r2 } } = t2;
  return e2 = r2 ? await r2(e2, t2) : e2, { ast: e2, comments: u2 };
}
function Ca(e2, t2) {
  let { cursorOffset: u2, locStart: r2, locEnd: o2, getVisitorKeys: n3 } = t2, a2 = (c2) => r2(c2) <= u2 && o2(c2) >= u2, s2 = e2, i = [e2];
  for (let c2 of dr(e2, { getVisitorKeys: n3, filter: a2 })) i.push(c2), s2 = c2;
  if (pr(s2, { getVisitorKeys: n3 })) return { cursorNode: s2 };
  let D2, f3, l2 = -1, d = Number.POSITIVE_INFINITY;
  for (; i.length > 0 && (D2 === void 0 || f3 === void 0); ) {
    s2 = i.pop();
    let c2 = D2 !== void 0, p2 = f3 !== void 0;
    for (let F2 of ye$1(s2, { getVisitorKeys: n3 })) {
      if (!c2) {
        let C2 = o2(F2);
        C2 <= u2 && C2 > l2 && (D2 = F2, l2 = C2);
      }
      if (!p2) {
        let C2 = r2(F2);
        C2 >= u2 && C2 < d && (f3 = F2, d = C2);
      }
    }
  }
  return { nodeBeforeCursor: D2, nodeAfterCursor: f3 };
}
var _u = Ca;
function ha(e2, t2) {
  let { printer: u2 } = t2, r2 = u2.massageAstNode;
  if (!r2) return e2;
  let { getVisitorKeys: o2 } = u2, { ignoredProperties: n3 } = r2;
  return a2(e2);
  function a2(s2, i) {
    if (!nt$1(s2)) return s2;
    if (Array.isArray(s2)) return s2.map((d) => a2(d, i)).filter(Boolean);
    let D2 = {}, f3 = new Set(o2(s2));
    for (let d in s2) !Object.prototype.hasOwnProperty.call(s2, d) || n3?.has(d) || (f3.has(d) ? D2[d] = a2(s2[d], s2) : D2[d] = s2[d]);
    let l2 = r2(s2, D2, i);
    if (l2 !== null) return l2 ?? D2;
  }
}
var mn = ha;
var ga = Array.prototype.findLastIndex ?? function(e2) {
  for (let t2 = this.length - 1; t2 >= 0; t2--) {
    let u2 = this[t2];
    if (e2(u2, t2, this)) return t2;
  }
  return -1;
}, ya = X$1("findLastIndex", function() {
  if (Array.isArray(this)) return ga;
}), En = ya;
var ba = ({ parser: e2 }) => e2 === "json" || e2 === "json5" || e2 === "jsonc" || e2 === "json-stringify";
function Aa(e2, t2) {
  return t2 = new Set(t2), e2.find((u2) => gn.has(u2.type) && t2.has(u2));
}
function Cn(e2) {
  let t2 = En(0, e2, (u2) => u2.type !== "Program" && u2.type !== "File");
  return t2 === -1 ? e2 : e2.slice(0, t2 + 1);
}
function _a(e2, t2, { locStart: u2, locEnd: r2 }) {
  let [o2, ...n3] = e2, [a2, ...s2] = t2;
  if (o2 === a2) return [o2, a2];
  let i = u2(o2);
  for (let f3 of Cn(s2)) if (u2(f3) >= i) a2 = f3;
  else break;
  let D2 = r2(a2);
  for (let f3 of Cn(n3)) {
    if (r2(f3) <= D2) o2 = f3;
    else break;
    if (o2 === a2) break;
  }
  return [o2, a2];
}
function xu(e2, t2, u2, r2, o2 = [], n3) {
  let { locStart: a2, locEnd: s2 } = u2, i = a2(e2), D2 = s2(e2);
  if (t2 > D2 || t2 < i || n3 === "rangeEnd" && t2 === i || n3 === "rangeStart" && t2 === D2) return;
  let f3 = [e2, ...o2], l2 = ot$1(e2, f3, { cache: eu, locStart: a2, locEnd: s2, getVisitorKeys: u2.getVisitorKeys, filter: u2.printer.canAttachComment, getChildren: u2.printer.getCommentChildNodes });
  for (let d of l2) {
    let c2 = xu(d, t2, u2, r2, f3, n3);
    if (c2) return c2;
  }
  if (r2(e2, o2[0])) return f3;
}
function xa(e2, t2) {
  return t2 !== "DeclareExportDeclaration" && e2 !== "TypeParameterDeclaration" && (e2 === "Directive" || e2 === "TypeAlias" || e2 === "TSExportAssignment" || e2.startsWith("Declare") || e2.startsWith("TSDeclare") || e2.endsWith("Statement") || e2.endsWith("Declaration"));
}
var gn = /* @__PURE__ */ new Set(["JsonRoot", "ObjectExpression", "ArrayExpression", "StringLiteral", "NumericLiteral", "BooleanLiteral", "NullLiteral", "UnaryExpression", "TemplateLiteral"]), Ba = /* @__PURE__ */ new Set(["OperationDefinition", "FragmentDefinition", "VariableDefinition", "TypeExtensionDefinition", "ObjectTypeDefinition", "FieldDefinition", "DirectiveDefinition", "EnumTypeDefinition", "EnumValueDefinition", "InputValueDefinition", "InputObjectTypeDefinition", "SchemaDefinition", "OperationTypeDefinition", "InterfaceTypeDefinition", "UnionTypeDefinition", "ScalarTypeDefinition"]);
function hn(e2, t2, u2) {
  if (!t2) return false;
  switch (e2.parser) {
    case "flow":
    case "hermes":
    case "babel":
    case "babel-flow":
    case "babel-ts":
    case "typescript":
    case "acorn":
    case "espree":
    case "meriyah":
    case "oxc":
    case "oxc-ts":
    case "__babel_estree":
      return xa(t2.type, u2?.type);
    case "json":
    case "json5":
    case "jsonc":
    case "json-stringify":
      return gn.has(t2.type);
    case "graphql":
      return Ba.has(t2.kind);
    case "vue":
      return t2.tag !== "root";
  }
  return false;
}
function yn(e2, t2, u2) {
  let { rangeStart: r2, rangeEnd: o2, locStart: n3, locEnd: a2 } = t2;
  let s2 = e2.slice(r2, o2).search(/\S/u), i = s2 === -1;
  if (!i) for (r2 += s2; o2 > r2 && !/\S/u.test(e2[o2 - 1]); --o2) ;
  let D2 = xu(u2, r2, t2, (c2, p2) => hn(t2, c2, p2), [], "rangeStart");
  if (!D2) return;
  let f3 = i ? D2 : xu(u2, o2, t2, (c2) => hn(t2, c2), [], "rangeEnd");
  if (!f3) return;
  let l2, d;
  if (ba(t2)) {
    let c2 = Aa(D2, f3);
    l2 = c2, d = c2;
  } else [l2, d] = _a(D2, f3, t2);
  return [Math.min(n3(l2), n3(d)), Math.max(a2(l2), a2(d))];
}
var xn = "\uFEFF", bn = Symbol("cursor");
async function Bn(e2, t2, u2 = 0) {
  if (!e2 || e2.trim().length === 0) return { formatted: "", cursorOffset: -1, comments: [] };
  let { ast: r2, text: o2 } = await Fe$1(e2, t2);
  t2.cursorOffset >= 0 && (t2 = { ...t2, ..._u(r2, t2) });
  let n3 = await Ke$1(r2, t2);
  u2 > 0 && (n3 = Ze$1([V$1, n3], u2, t2.tabWidth));
  let a2 = Ce(n3, t2);
  if (u2 > 0) {
    let i = a2.formatted.trim();
    a2.cursorNodeStart !== void 0 && (a2.cursorNodeStart -= a2.formatted.indexOf(i), a2.cursorNodeStart < 0 && (a2.cursorNodeStart = 0, a2.cursorNodeText = a2.cursorNodeText.trimStart()), a2.cursorNodeStart + a2.cursorNodeText.length > i.length && (a2.cursorNodeText = a2.cursorNodeText.trimEnd())), a2.formatted = i + Ne(t2.endOfLine);
  }
  let s2 = t2[Symbol.for("comments")];
  if (t2.cursorOffset >= 0) {
    let i, D2, f3, l2;
    if ((t2.cursorNode || t2.nodeBeforeCursor || t2.nodeAfterCursor) && a2.cursorNodeText) if (f3 = a2.cursorNodeStart, l2 = a2.cursorNodeText, t2.cursorNode) i = t2.locStart(t2.cursorNode), D2 = o2.slice(i, t2.locEnd(t2.cursorNode));
    else {
      if (!t2.nodeBeforeCursor && !t2.nodeAfterCursor) throw new Error("Cursor location must contain at least one of cursorNode, nodeBeforeCursor, nodeAfterCursor");
      i = t2.nodeBeforeCursor ? t2.locEnd(t2.nodeBeforeCursor) : 0;
      let y2 = t2.nodeAfterCursor ? t2.locStart(t2.nodeAfterCursor) : o2.length;
      D2 = o2.slice(i, y2);
    }
    else i = 0, D2 = o2, f3 = 0, l2 = a2.formatted;
    let d = t2.cursorOffset - i;
    if (D2 === l2) return { formatted: a2.formatted, cursorOffset: f3 + d, comments: s2 };
    let c2 = D2.split("");
    c2.splice(d, 0, bn);
    let p2 = l2.split(""), F2 = jt$1(c2, p2), C2 = f3;
    for (let y2 of F2) if (y2.removed) {
      if (y2.value.includes(bn)) break;
    } else C2 += y2.count;
    return { formatted: a2.formatted, cursorOffset: C2, comments: s2 };
  }
  return { formatted: a2.formatted, cursorOffset: -1, comments: s2 };
}
async function Ta(e2, t2) {
  let { ast: u2, text: r2 } = await Fe$1(e2, t2), [o2, n3] = yn(r2, t2, u2) ?? [0, 0], a2 = r2.slice(o2, n3), s2 = Math.min(o2, r2.lastIndexOf(`
`, o2) + 1), i = r2.slice(s2, o2).match(/^\s*/u)[0], D2 = he$4(i, t2.tabWidth), f3 = await Bn(a2, { ...t2, rangeStart: 0, rangeEnd: Number.POSITIVE_INFINITY, cursorOffset: t2.cursorOffset > o2 && t2.cursorOffset <= n3 ? t2.cursorOffset - o2 : -1, endOfLine: "lf" }, D2), l2 = f3.formatted.trimEnd(), { cursorOffset: d } = t2;
  d > n3 ? d += l2.length - a2.length : f3.cursorOffset >= 0 && (d = f3.cursorOffset + o2);
  let c2 = r2.slice(0, o2) + l2 + r2.slice(n3);
  if (t2.endOfLine !== "lf") {
    let p2 = Ne(t2.endOfLine);
    d >= 0 && p2 === `\r
` && (d += Ut$1(c2.slice(0, d), `
`)), c2 = oe$1(0, c2, `
`, p2);
  }
  return { formatted: c2, cursorOffset: d, comments: f3.comments };
}
function Bu(e2, t2, u2) {
  return typeof t2 != "number" || Number.isNaN(t2) || t2 < 0 || t2 > e2.length ? u2 : t2;
}
function An(e2, t2) {
  let { cursorOffset: u2, rangeStart: r2, rangeEnd: o2 } = t2;
  return u2 = Bu(e2, u2, -1), r2 = Bu(e2, r2, 0), o2 = Bu(e2, o2, e2.length), { ...t2, cursorOffset: u2, rangeStart: r2, rangeEnd: o2 };
}
function Tn(e2, t2) {
  let { cursorOffset: u2, rangeStart: r2, rangeEnd: o2, endOfLine: n3 } = An(e2, t2), a2 = e2.charAt(0) === xn;
  if (a2 && (e2 = e2.slice(1), u2--, r2--, o2--), n3 === "auto" && (n3 = Iu(e2)), e2.includes("\r")) {
    let s2 = (i) => Ut$1(e2.slice(0, Math.max(i, 0)), `\r
`);
    u2 -= s2(u2), r2 -= s2(r2), o2 -= s2(o2), e2 = ku(e2);
  }
  return { hasBOM: a2, text: e2, options: An(e2, { ...t2, cursorOffset: u2, rangeStart: r2, rangeEnd: o2, endOfLine: n3 }) };
}
async function _n(e2, t2) {
  let u2 = await Ue$1(t2);
  return !u2.hasPragma || u2.hasPragma(e2);
}
async function Na(e2, t2) {
  return (await Ue$1(t2)).hasIgnorePragma?.(e2);
}
async function Tu(e2, t2) {
  let { hasBOM: u2, text: r2, options: o2 } = Tn(e2, await se$1(t2));
  if (o2.rangeStart >= o2.rangeEnd && r2 !== "" || o2.requirePragma && !await _n(r2, o2) || o2.checkIgnorePragma && await Na(r2, o2)) return { formatted: e2, cursorOffset: t2.cursorOffset, comments: [] };
  let n3;
  return o2.rangeStart > 0 || o2.rangeEnd < r2.length ? n3 = await Ta(r2, o2) : (!o2.requirePragma && o2.insertPragma && o2.printer.insertPragma && !await _n(r2, o2) && (r2 = o2.printer.insertPragma(r2)), n3 = await Bn(r2, o2)), u2 && (n3.formatted = xn + n3.formatted, n3.cursorOffset >= 0 && n3.cursorOffset++), n3;
}
async function Nn(e2, t2, u2) {
  let { text: r2, options: o2 } = Tn(e2, await se$1(t2)), n3 = await Fe$1(r2, o2);
  return u2 && (u2.preprocessForPrint && (n3.ast = await Au(n3.ast, o2)), u2.massage && (n3.ast = mn(n3.ast, o2))), n3;
}
async function Sn(e2, t2) {
  t2 = await se$1(t2);
  let u2 = await Ke$1(e2, t2);
  return Ce(u2, t2);
}
async function wn(e2, t2) {
  let u2 = ur(e2), { formatted: r2 } = await Tu(u2, { ...t2, parser: "__js_expression" });
  return r2;
}
async function On(e2, t2) {
  t2 = await se$1(t2);
  let { ast: u2 } = await Fe$1(e2, t2);
  return t2.cursorOffset >= 0 && (t2 = { ...t2, ..._u(u2, t2) }), Ke$1(u2, t2);
}
async function Pn(e2, t2) {
  return Ce(e2, await se$1(t2));
}
var Nu = {};
Mt$1(Nu, { builders: () => wa, printer: () => Oa, utils: () => Pa });
var wa = { join: Pe, line: et$1, softline: Zu, hardline: V$1, literalline: Xe$1, group: $t$1, conditionalGroup: Hu, fill: zu, lineSuffix: ke, lineSuffixBoundary: er, cursor: ee$1, breakParent: ce$1, ifBreak: Xu, trim: tr, indent: ae$1, indentIfBreak: qu, align: De$1, addAlignmentToDoc: Ze$1, markAsRoot: Qe$1, dedentToRoot: Ju, dedent: Gu, hardlineWithoutBreakParent: Ie, literallineWithoutBreakParent: Vt$1, label: Qu, concat: (e2) => e2 }, Oa = { printDocToString: Ce }, Pa = { willBreak: Yu, traverseDoc: Se, findInDoc: ze$1, mapDoc: Oe, removeLines: Uu, stripTrailingHardline: He$1, replaceEndOfLine: Wu, canBreak: $u };
var In = "3.7.3";
var wu = {};
Mt$1(wu, { addDanglingComment: () => ue$1, addLeadingComment: () => fe$1, addTrailingComment: () => le$1, getAlignmentSize: () => he$4, getIndentSize: () => kn, getMaxContinuousCount: () => vn, getNextNonSpaceNonCommentCharacter: () => Rn, getNextNonSpaceNonCommentCharacterIndex: () => Va, getPreferredQuote: () => Ln, getStringWidth: () => ve$1, hasNewline: () => G$1, hasNewlineInRange: () => Mn, hasSpaces: () => Yn, isNextLineEmpty: () => Ha, isNextLineEmptyAfterIndex: () => It$1, isPreviousLineEmpty: () => Ja, makeString: () => za, skip: () => ge$1, skipEverythingButNewLine: () => rt$1, skipInlineComment: () => _e$1, skipNewline: () => K$1, skipSpaces: () => Y$1, skipToLineEnd: () => ut$1, skipTrailingComment: () => xe$1, skipWhitespace: () => cr });
function Ia(e2, t2) {
  if (t2 === false) return false;
  if (e2.charAt(t2) === "/" && e2.charAt(t2 + 1) === "*") {
    for (let u2 = t2 + 2; u2 < e2.length; ++u2) if (e2.charAt(u2) === "*" && e2.charAt(u2 + 1) === "/") return u2 + 2;
  }
  return t2;
}
var _e$1 = Ia;
function ka(e2, t2) {
  return t2 === false ? false : e2.charAt(t2) === "/" && e2.charAt(t2 + 1) === "/" ? rt$1(e2, t2) : t2;
}
var xe$1 = ka;
function va(e2, t2) {
  let u2 = null, r2 = t2;
  for (; r2 !== u2; ) u2 = r2, r2 = Y$1(e2, r2), r2 = _e$1(e2, r2), r2 = xe$1(e2, r2), r2 = K$1(e2, r2);
  return r2;
}
var Je$1 = va;
function Ra(e2, t2) {
  let u2 = null, r2 = t2;
  for (; r2 !== u2; ) u2 = r2, r2 = ut$1(e2, r2), r2 = _e$1(e2, r2), r2 = Y$1(e2, r2);
  return r2 = xe$1(e2, r2), r2 = K$1(e2, r2), r2 !== false && G$1(e2, r2);
}
var It$1 = Ra;
function La(e2, t2) {
  let u2 = e2.lastIndexOf(`
`);
  return u2 === -1 ? 0 : he$4(e2.slice(u2 + 1).match(/^[\t ]*/u)[0], t2);
}
var kn = La;
function Su(e2) {
  if (typeof e2 != "string") throw new TypeError("Expected a string");
  return e2.replace(/[|\\{}()[\]^$+*?.]/g, "\\$&").replace(/-/g, "\\x2d");
}
function Ma(e2, t2) {
  let u2 = e2.matchAll(new RegExp(`(?:${Su(t2)})+`, "gu"));
  return u2.reduce || (u2 = [...u2]), u2.reduce((r2, [o2]) => Math.max(r2, o2.length), 0) / t2.length;
}
var vn = Ma;
function Ya(e2, t2) {
  let u2 = Je$1(e2, t2);
  return u2 === false ? "" : e2.charAt(u2);
}
var Rn = Ya;
function ja(e2, t2) {
  let u2 = t2 === true || t2 === "'" ? "'" : '"', r2 = u2 === "'" ? '"' : "'", o2 = 0, n3 = 0;
  for (let a2 of e2) a2 === u2 ? o2++ : a2 === r2 && n3++;
  return o2 > n3 ? r2 : u2;
}
var Ln = ja;
function Ua(e2, t2, u2) {
  for (let r2 = t2; r2 < u2; ++r2) if (e2.charAt(r2) === `
`) return true;
  return false;
}
var Mn = Ua;
function Wa(e2, t2, u2 = {}) {
  return Y$1(e2, u2.backwards ? t2 - 1 : t2, u2) !== t2;
}
var Yn = Wa;
function $a(e2, t2, u2) {
  return Je$1(e2, u2(t2));
}
function Va(e2, t2) {
  return arguments.length === 2 || typeof t2 == "number" ? Je$1(e2, t2) : $a(...arguments);
}
function Ka(e2, t2, u2) {
  return Re$1(e2, u2(t2));
}
function Ja(e2, t2) {
  return arguments.length === 2 || typeof t2 == "number" ? Re$1(e2, t2) : Ka(...arguments);
}
function Ga(e2, t2, u2) {
  return It$1(e2, u2(t2));
}
function za(e2, t2, u2) {
  let r2 = t2 === '"' ? "'" : '"', n3 = oe$1(0, e2, /\\(.)|(["'])/gsu, (a2, s2, i) => s2 === r2 ? s2 : i === t2 ? "\\" + i : i || (u2 && /^[^\n\r"'0-7\\bfnrt-vx\u2028\u2029]$/u.test(s2) ? s2 : "\\" + s2));
  return t2 + n3 + t2;
}
function Ha(e2, t2) {
  return arguments.length === 2 || typeof t2 == "number" ? It$1(e2, t2) : Ga(...arguments);
}
function me$1(e2, t2 = 1) {
  return async (...u2) => {
    let r2 = u2[t2] ?? {}, o2 = r2.plugins ?? [];
    return u2[t2] = { ...r2, plugins: Array.isArray(o2) ? o2 : Object.values(o2) }, e2(...u2);
  };
}
var jn = me$1(Tu);
async function Un(e2, t2) {
  let { formatted: u2 } = await jn(e2, { ...t2, cursorOffset: -1 });
  return u2;
}
async function Xa(e2, t2) {
  return await Un(e2, t2) === e2;
}
var qa = me$1(at$1, 0), Qa = { parse: me$1(Nn), formatAST: me$1(Sn), formatDoc: me$1(wn), printToDoc: me$1(On), printDocToString: me$1(Pn) };
const UnicodeConstants = {
  NO_BREAK_SPACE: " ",
  LAQUO: "«",
  RAQUO: "»",
  LDQUO: "“",
  RDQUO: "”",
  BDQUO: "„"
};
const QUOTE_STYLES = {
  [
    "doubleQuotes"
    /* DoubleQuotes */
  ]: {
    opening: UnicodeConstants.LDQUO,
    openingSuffix: "",
    closing: UnicodeConstants.RDQUO,
    closingPrefix: ""
  },
  [
    "guillemets"
    /* Guillemets */
  ]: {
    opening: UnicodeConstants.LAQUO,
    openingSuffix: "",
    closing: UnicodeConstants.RAQUO,
    closingPrefix: ""
  },
  [
    "guillemetsFr"
    /* GuillemetsFr */
  ]: {
    opening: UnicodeConstants.LAQUO,
    openingSuffix: UnicodeConstants.NO_BREAK_SPACE,
    closing: UnicodeConstants.RAQUO,
    closingPrefix: UnicodeConstants.NO_BREAK_SPACE
  },
  [
    "germanQuotes"
    /* GermanQuotes */
  ]: {
    opening: UnicodeConstants.BDQUO,
    openingSuffix: "",
    closing: UnicodeConstants.LDQUO,
    closingPrefix: ""
  },
  [
    "finnishQuotes"
    /* FinnishQuotes */
  ]: {
    opening: UnicodeConstants.RDQUO,
    openingSuffix: "",
    closing: UnicodeConstants.RDQUO,
    closingPrefix: ""
  }
};
const STYLE_TO_LOCALES_MAP = {
  [
    "doubleQuotes"
    /* DoubleQuotes */
  ]: [
    "pt-br",
    "en",
    "us",
    "gb",
    "af",
    "ar",
    "eo",
    "id",
    "ga",
    "ko",
    "br",
    "th",
    "tr",
    "vi"
  ],
  [
    "guillemets"
    /* Guillemets */
  ]: [
    "de-ch",
    "hy",
    "az",
    "hz",
    "eu",
    "be",
    "ca",
    "el",
    "it",
    "no",
    "fa",
    "lv",
    "pt",
    "ru",
    "es",
    "uk"
  ],
  [
    "guillemetsFr"
    /* GuillemetsFr */
  ]: ["fr"],
  [
    "germanQuotes"
    /* GermanQuotes */
  ]: [
    "de",
    "ka",
    "cs",
    "et",
    "is",
    "lt",
    "mk",
    "ro",
    "sk",
    "sl",
    "wen"
  ],
  [
    "finnishQuotes"
    /* FinnishQuotes */
  ]: ["fi", "sv", "bs"]
};
const LOCALE_QUOTES = /* @__PURE__ */ new Map();
for (const [style, locales] of Object.entries(STYLE_TO_LOCALES_MAP)) {
  const quoteStyle = QUOTE_STYLES[style];
  for (const locale of locales) {
    LOCALE_QUOTES.set(locale, quoteStyle);
  }
}
function SmartQuotes(content, locale = "en") {
  if (content.includes("{{") || content.includes("{%")) {
    return content;
  }
  const lowerCaseLocale = locale.toLowerCase();
  const localeParts = lowerCaseLocale.split("-");
  const fallbackLocales = [
    lowerCaseLocale,
    ...localeParts.length > 1 ? [localeParts[0]] : []
  ];
  let config = QUOTE_STYLES[
    "doubleQuotes"
    /* DoubleQuotes */
  ];
  for (const loc of fallbackLocales) {
    const foundConfig = loc ? LOCALE_QUOTES.get(loc) : null;
    if (foundConfig) {
      config = foundConfig;
      break;
    }
  }
  const { opening, openingSuffix, closing, closingPrefix } = config;
  const quoteRegex = /(?<prefix>^|\s|\()"(?<content>[^"]+)"/gim;
  return content.replace(
    quoteRegex,
    `$<prefix>${opening}${openingSuffix}$<content>${closingPrefix}${closing}`
  );
}
var commonjsGlobal = typeof globalThis !== "undefined" ? globalThis : typeof globalThis.window !== "undefined" ? globalThis.window : typeof global !== "undefined" ? global : typeof self !== "undefined" ? self : {};
function getDefaultExportFromCjs(x2) {
  return x2 && x2.__esModule && Object.prototype.hasOwnProperty.call(x2, "default") ? x2["default"] : x2;
}
var he$3 = { exports: {} };
var he$2 = he$3.exports;
var hasRequiredHe;
function requireHe() {
  if (hasRequiredHe) return he$3.exports;
  hasRequiredHe = 1;
  (function(module, exports$1) {
    (function(root) {
      var freeExports = exports$1;
      var freeModule = module && module.exports == freeExports && module;
      var freeGlobal = typeof commonjsGlobal == "object" && commonjsGlobal;
      if (freeGlobal.global === freeGlobal || freeGlobal.window === freeGlobal) {
        root = freeGlobal;
      }
      var regexAstralSymbols = /[\uD800-\uDBFF][\uDC00-\uDFFF]/g;
      var regexAsciiWhitelist = /[\x01-\x7F]/g;
      var regexBmpWhitelist = /[\x01-\t\x0B\f\x0E-\x1F\x7F\x81\x8D\x8F\x90\x9D\xA0-\uFFFF]/g;
      var regexEncodeNonAscii = /<\u20D2|=\u20E5|>\u20D2|\u205F\u200A|\u219D\u0338|\u2202\u0338|\u2220\u20D2|\u2229\uFE00|\u222A\uFE00|\u223C\u20D2|\u223D\u0331|\u223E\u0333|\u2242\u0338|\u224B\u0338|\u224D\u20D2|\u224E\u0338|\u224F\u0338|\u2250\u0338|\u2261\u20E5|\u2264\u20D2|\u2265\u20D2|\u2266\u0338|\u2267\u0338|\u2268\uFE00|\u2269\uFE00|\u226A\u0338|\u226A\u20D2|\u226B\u0338|\u226B\u20D2|\u227F\u0338|\u2282\u20D2|\u2283\u20D2|\u228A\uFE00|\u228B\uFE00|\u228F\u0338|\u2290\u0338|\u2293\uFE00|\u2294\uFE00|\u22B4\u20D2|\u22B5\u20D2|\u22D8\u0338|\u22D9\u0338|\u22DA\uFE00|\u22DB\uFE00|\u22F5\u0338|\u22F9\u0338|\u2933\u0338|\u29CF\u0338|\u29D0\u0338|\u2A6D\u0338|\u2A70\u0338|\u2A7D\u0338|\u2A7E\u0338|\u2AA1\u0338|\u2AA2\u0338|\u2AAC\uFE00|\u2AAD\uFE00|\u2AAF\u0338|\u2AB0\u0338|\u2AC5\u0338|\u2AC6\u0338|\u2ACB\uFE00|\u2ACC\uFE00|\u2AFD\u20E5|[\xA0-\u0113\u0116-\u0122\u0124-\u012B\u012E-\u014D\u0150-\u017E\u0192\u01B5\u01F5\u0237\u02C6\u02C7\u02D8-\u02DD\u0311\u0391-\u03A1\u03A3-\u03A9\u03B1-\u03C9\u03D1\u03D2\u03D5\u03D6\u03DC\u03DD\u03F0\u03F1\u03F5\u03F6\u0401-\u040C\u040E-\u044F\u0451-\u045C\u045E\u045F\u2002-\u2005\u2007-\u2010\u2013-\u2016\u2018-\u201A\u201C-\u201E\u2020-\u2022\u2025\u2026\u2030-\u2035\u2039\u203A\u203E\u2041\u2043\u2044\u204F\u2057\u205F-\u2063\u20AC\u20DB\u20DC\u2102\u2105\u210A-\u2113\u2115-\u211E\u2122\u2124\u2127-\u2129\u212C\u212D\u212F-\u2131\u2133-\u2138\u2145-\u2148\u2153-\u215E\u2190-\u219B\u219D-\u21A7\u21A9-\u21AE\u21B0-\u21B3\u21B5-\u21B7\u21BA-\u21DB\u21DD\u21E4\u21E5\u21F5\u21FD-\u2205\u2207-\u2209\u220B\u220C\u220F-\u2214\u2216-\u2218\u221A\u221D-\u2238\u223A-\u2257\u2259\u225A\u225C\u225F-\u2262\u2264-\u228B\u228D-\u229B\u229D-\u22A5\u22A7-\u22B0\u22B2-\u22BB\u22BD-\u22DB\u22DE-\u22E3\u22E6-\u22F7\u22F9-\u22FE\u2305\u2306\u2308-\u2310\u2312\u2313\u2315\u2316\u231C-\u231F\u2322\u2323\u232D\u232E\u2336\u233D\u233F\u237C\u23B0\u23B1\u23B4-\u23B6\u23DC-\u23DF\u23E2\u23E7\u2423\u24C8\u2500\u2502\u250C\u2510\u2514\u2518\u251C\u2524\u252C\u2534\u253C\u2550-\u256C\u2580\u2584\u2588\u2591-\u2593\u25A1\u25AA\u25AB\u25AD\u25AE\u25B1\u25B3-\u25B5\u25B8\u25B9\u25BD-\u25BF\u25C2\u25C3\u25CA\u25CB\u25EC\u25EF\u25F8-\u25FC\u2605\u2606\u260E\u2640\u2642\u2660\u2663\u2665\u2666\u266A\u266D-\u266F\u2713\u2717\u2720\u2736\u2758\u2772\u2773\u27C8\u27C9\u27E6-\u27ED\u27F5-\u27FA\u27FC\u27FF\u2902-\u2905\u290C-\u2913\u2916\u2919-\u2920\u2923-\u292A\u2933\u2935-\u2939\u293C\u293D\u2945\u2948-\u294B\u294E-\u2976\u2978\u2979\u297B-\u297F\u2985\u2986\u298B-\u2996\u299A\u299C\u299D\u29A4-\u29B7\u29B9\u29BB\u29BC\u29BE-\u29C5\u29C9\u29CD-\u29D0\u29DC-\u29DE\u29E3-\u29E5\u29EB\u29F4\u29F6\u2A00-\u2A02\u2A04\u2A06\u2A0C\u2A0D\u2A10-\u2A17\u2A22-\u2A27\u2A29\u2A2A\u2A2D-\u2A31\u2A33-\u2A3C\u2A3F\u2A40\u2A42-\u2A4D\u2A50\u2A53-\u2A58\u2A5A-\u2A5D\u2A5F\u2A66\u2A6A\u2A6D-\u2A75\u2A77-\u2A9A\u2A9D-\u2AA2\u2AA4-\u2AB0\u2AB3-\u2AC8\u2ACB\u2ACC\u2ACF-\u2ADB\u2AE4\u2AE6-\u2AE9\u2AEB-\u2AF3\u2AFD\uFB00-\uFB04]|\uD835[\uDC9C\uDC9E\uDC9F\uDCA2\uDCA5\uDCA6\uDCA9-\uDCAC\uDCAE-\uDCB9\uDCBB\uDCBD-\uDCC3\uDCC5-\uDCCF\uDD04\uDD05\uDD07-\uDD0A\uDD0D-\uDD14\uDD16-\uDD1C\uDD1E-\uDD39\uDD3B-\uDD3E\uDD40-\uDD44\uDD46\uDD4A-\uDD50\uDD52-\uDD6B]/g;
      var encodeMap = { "­": "shy", "‌": "zwnj", "‍": "zwj", "‎": "lrm", "⁣": "ic", "⁢": "it", "⁡": "af", "‏": "rlm", "​": "ZeroWidthSpace", "⁠": "NoBreak", "̑": "DownBreve", "⃛": "tdot", "⃜": "DotDot", "	": "Tab", "\n": "NewLine", " ": "puncsp", " ": "MediumSpace", " ": "thinsp", " ": "hairsp", " ": "emsp13", " ": "ensp", " ": "emsp14", " ": "emsp", " ": "numsp", " ": "nbsp", "  ": "ThickSpace", "‾": "oline", "_": "lowbar", "‐": "dash", "–": "ndash", "—": "mdash", "―": "horbar", ",": "comma", ";": "semi", "⁏": "bsemi", ":": "colon", "⩴": "Colone", "!": "excl", "¡": "iexcl", "?": "quest", "¿": "iquest", ".": "period", "‥": "nldr", "…": "mldr", "·": "middot", "'": "apos", "‘": "lsquo", "’": "rsquo", "‚": "sbquo", "‹": "lsaquo", "›": "rsaquo", '"': "quot", "“": "ldquo", "”": "rdquo", "„": "bdquo", "«": "laquo", "»": "raquo", "(": "lpar", ")": "rpar", "[": "lsqb", "]": "rsqb", "{": "lcub", "}": "rcub", "⌈": "lceil", "⌉": "rceil", "⌊": "lfloor", "⌋": "rfloor", "⦅": "lopar", "⦆": "ropar", "⦋": "lbrke", "⦌": "rbrke", "⦍": "lbrkslu", "⦎": "rbrksld", "⦏": "lbrksld", "⦐": "rbrkslu", "⦑": "langd", "⦒": "rangd", "⦓": "lparlt", "⦔": "rpargt", "⦕": "gtlPar", "⦖": "ltrPar", "⟦": "lobrk", "⟧": "robrk", "⟨": "lang", "⟩": "rang", "⟪": "Lang", "⟫": "Rang", "⟬": "loang", "⟭": "roang", "❲": "lbbrk", "❳": "rbbrk", "‖": "Vert", "§": "sect", "¶": "para", "@": "commat", "*": "ast", "/": "sol", "undefined": null, "&": "amp", "#": "num", "%": "percnt", "‰": "permil", "‱": "pertenk", "†": "dagger", "‡": "Dagger", "•": "bull", "⁃": "hybull", "′": "prime", "″": "Prime", "‴": "tprime", "⁗": "qprime", "‵": "bprime", "⁁": "caret", "`": "grave", "´": "acute", "˜": "tilde", "^": "Hat", "¯": "macr", "˘": "breve", "˙": "dot", "¨": "die", "˚": "ring", "˝": "dblac", "¸": "cedil", "˛": "ogon", "ˆ": "circ", "ˇ": "caron", "°": "deg", "©": "copy", "®": "reg", "℗": "copysr", "℘": "wp", "℞": "rx", "℧": "mho", "℩": "iiota", "←": "larr", "↚": "nlarr", "→": "rarr", "↛": "nrarr", "↑": "uarr", "↓": "darr", "↔": "harr", "↮": "nharr", "↕": "varr", "↖": "nwarr", "↗": "nearr", "↘": "searr", "↙": "swarr", "↝": "rarrw", "↝̸": "nrarrw", "↞": "Larr", "↟": "Uarr", "↠": "Rarr", "↡": "Darr", "↢": "larrtl", "↣": "rarrtl", "↤": "mapstoleft", "↥": "mapstoup", "↦": "map", "↧": "mapstodown", "↩": "larrhk", "↪": "rarrhk", "↫": "larrlp", "↬": "rarrlp", "↭": "harrw", "↰": "lsh", "↱": "rsh", "↲": "ldsh", "↳": "rdsh", "↵": "crarr", "↶": "cularr", "↷": "curarr", "↺": "olarr", "↻": "orarr", "↼": "lharu", "↽": "lhard", "↾": "uharr", "↿": "uharl", "⇀": "rharu", "⇁": "rhard", "⇂": "dharr", "⇃": "dharl", "⇄": "rlarr", "⇅": "udarr", "⇆": "lrarr", "⇇": "llarr", "⇈": "uuarr", "⇉": "rrarr", "⇊": "ddarr", "⇋": "lrhar", "⇌": "rlhar", "⇐": "lArr", "⇍": "nlArr", "⇑": "uArr", "⇒": "rArr", "⇏": "nrArr", "⇓": "dArr", "⇔": "iff", "⇎": "nhArr", "⇕": "vArr", "⇖": "nwArr", "⇗": "neArr", "⇘": "seArr", "⇙": "swArr", "⇚": "lAarr", "⇛": "rAarr", "⇝": "zigrarr", "⇤": "larrb", "⇥": "rarrb", "⇵": "duarr", "⇽": "loarr", "⇾": "roarr", "⇿": "hoarr", "∀": "forall", "∁": "comp", "∂": "part", "∂̸": "npart", "∃": "exist", "∄": "nexist", "∅": "empty", "∇": "Del", "∈": "in", "∉": "notin", "∋": "ni", "∌": "notni", "϶": "bepsi", "∏": "prod", "∐": "coprod", "∑": "sum", "+": "plus", "±": "pm", "÷": "div", "×": "times", "<": "lt", "≮": "nlt", "<⃒": "nvlt", "=": "equals", "≠": "ne", "=⃥": "bne", "⩵": "Equal", ">": "gt", "≯": "ngt", ">⃒": "nvgt", "¬": "not", "|": "vert", "¦": "brvbar", "−": "minus", "∓": "mp", "∔": "plusdo", "⁄": "frasl", "∖": "setmn", "∗": "lowast", "∘": "compfn", "√": "Sqrt", "∝": "prop", "∞": "infin", "∟": "angrt", "∠": "ang", "∠⃒": "nang", "∡": "angmsd", "∢": "angsph", "∣": "mid", "∤": "nmid", "∥": "par", "∦": "npar", "∧": "and", "∨": "or", "∩": "cap", "∩︀": "caps", "∪": "cup", "∪︀": "cups", "∫": "int", "∬": "Int", "∭": "tint", "⨌": "qint", "∮": "oint", "∯": "Conint", "∰": "Cconint", "∱": "cwint", "∲": "cwconint", "∳": "awconint", "∴": "there4", "∵": "becaus", "∶": "ratio", "∷": "Colon", "∸": "minusd", "∺": "mDDot", "∻": "homtht", "∼": "sim", "≁": "nsim", "∼⃒": "nvsim", "∽": "bsim", "∽̱": "race", "∾": "ac", "∾̳": "acE", "∿": "acd", "≀": "wr", "≂": "esim", "≂̸": "nesim", "≃": "sime", "≄": "nsime", "≅": "cong", "≇": "ncong", "≆": "simne", "≈": "ap", "≉": "nap", "≊": "ape", "≋": "apid", "≋̸": "napid", "≌": "bcong", "≍": "CupCap", "≭": "NotCupCap", "≍⃒": "nvap", "≎": "bump", "≎̸": "nbump", "≏": "bumpe", "≏̸": "nbumpe", "≐": "doteq", "≐̸": "nedot", "≑": "eDot", "≒": "efDot", "≓": "erDot", "≔": "colone", "≕": "ecolon", "≖": "ecir", "≗": "cire", "≙": "wedgeq", "≚": "veeeq", "≜": "trie", "≟": "equest", "≡": "equiv", "≢": "nequiv", "≡⃥": "bnequiv", "≤": "le", "≰": "nle", "≤⃒": "nvle", "≥": "ge", "≱": "nge", "≥⃒": "nvge", "≦": "lE", "≦̸": "nlE", "≧": "gE", "≧̸": "ngE", "≨︀": "lvnE", "≨": "lnE", "≩": "gnE", "≩︀": "gvnE", "≪": "ll", "≪̸": "nLtv", "≪⃒": "nLt", "≫": "gg", "≫̸": "nGtv", "≫⃒": "nGt", "≬": "twixt", "≲": "lsim", "≴": "nlsim", "≳": "gsim", "≵": "ngsim", "≶": "lg", "≸": "ntlg", "≷": "gl", "≹": "ntgl", "≺": "pr", "⊀": "npr", "≻": "sc", "⊁": "nsc", "≼": "prcue", "⋠": "nprcue", "≽": "sccue", "⋡": "nsccue", "≾": "prsim", "≿": "scsim", "≿̸": "NotSucceedsTilde", "⊂": "sub", "⊄": "nsub", "⊂⃒": "vnsub", "⊃": "sup", "⊅": "nsup", "⊃⃒": "vnsup", "⊆": "sube", "⊈": "nsube", "⊇": "supe", "⊉": "nsupe", "⊊︀": "vsubne", "⊊": "subne", "⊋︀": "vsupne", "⊋": "supne", "⊍": "cupdot", "⊎": "uplus", "⊏": "sqsub", "⊏̸": "NotSquareSubset", "⊐": "sqsup", "⊐̸": "NotSquareSuperset", "⊑": "sqsube", "⋢": "nsqsube", "⊒": "sqsupe", "⋣": "nsqsupe", "⊓": "sqcap", "⊓︀": "sqcaps", "⊔": "sqcup", "⊔︀": "sqcups", "⊕": "oplus", "⊖": "ominus", "⊗": "otimes", "⊘": "osol", "⊙": "odot", "⊚": "ocir", "⊛": "oast", "⊝": "odash", "⊞": "plusb", "⊟": "minusb", "⊠": "timesb", "⊡": "sdotb", "⊢": "vdash", "⊬": "nvdash", "⊣": "dashv", "⊤": "top", "⊥": "bot", "⊧": "models", "⊨": "vDash", "⊭": "nvDash", "⊩": "Vdash", "⊮": "nVdash", "⊪": "Vvdash", "⊫": "VDash", "⊯": "nVDash", "⊰": "prurel", "⊲": "vltri", "⋪": "nltri", "⊳": "vrtri", "⋫": "nrtri", "⊴": "ltrie", "⋬": "nltrie", "⊴⃒": "nvltrie", "⊵": "rtrie", "⋭": "nrtrie", "⊵⃒": "nvrtrie", "⊶": "origof", "⊷": "imof", "⊸": "mumap", "⊹": "hercon", "⊺": "intcal", "⊻": "veebar", "⊽": "barvee", "⊾": "angrtvb", "⊿": "lrtri", "⋀": "Wedge", "⋁": "Vee", "⋂": "xcap", "⋃": "xcup", "⋄": "diam", "⋅": "sdot", "⋆": "Star", "⋇": "divonx", "⋈": "bowtie", "⋉": "ltimes", "⋊": "rtimes", "⋋": "lthree", "⋌": "rthree", "⋍": "bsime", "⋎": "cuvee", "⋏": "cuwed", "⋐": "Sub", "⋑": "Sup", "⋒": "Cap", "⋓": "Cup", "⋔": "fork", "⋕": "epar", "⋖": "ltdot", "⋗": "gtdot", "⋘": "Ll", "⋘̸": "nLl", "⋙": "Gg", "⋙̸": "nGg", "⋚︀": "lesg", "⋚": "leg", "⋛": "gel", "⋛︀": "gesl", "⋞": "cuepr", "⋟": "cuesc", "⋦": "lnsim", "⋧": "gnsim", "⋨": "prnsim", "⋩": "scnsim", "⋮": "vellip", "⋯": "ctdot", "⋰": "utdot", "⋱": "dtdot", "⋲": "disin", "⋳": "isinsv", "⋴": "isins", "⋵": "isindot", "⋵̸": "notindot", "⋶": "notinvc", "⋷": "notinvb", "⋹": "isinE", "⋹̸": "notinE", "⋺": "nisd", "⋻": "xnis", "⋼": "nis", "⋽": "notnivc", "⋾": "notnivb", "⌅": "barwed", "⌆": "Barwed", "⌌": "drcrop", "⌍": "dlcrop", "⌎": "urcrop", "⌏": "ulcrop", "⌐": "bnot", "⌒": "profline", "⌓": "profsurf", "⌕": "telrec", "⌖": "target", "⌜": "ulcorn", "⌝": "urcorn", "⌞": "dlcorn", "⌟": "drcorn", "⌢": "frown", "⌣": "smile", "⌭": "cylcty", "⌮": "profalar", "⌶": "topbot", "⌽": "ovbar", "⌿": "solbar", "⍼": "angzarr", "⎰": "lmoust", "⎱": "rmoust", "⎴": "tbrk", "⎵": "bbrk", "⎶": "bbrktbrk", "⏜": "OverParenthesis", "⏝": "UnderParenthesis", "⏞": "OverBrace", "⏟": "UnderBrace", "⏢": "trpezium", "⏧": "elinters", "␣": "blank", "─": "boxh", "│": "boxv", "┌": "boxdr", "┐": "boxdl", "└": "boxur", "┘": "boxul", "├": "boxvr", "┤": "boxvl", "┬": "boxhd", "┴": "boxhu", "┼": "boxvh", "═": "boxH", "║": "boxV", "╒": "boxdR", "╓": "boxDr", "╔": "boxDR", "╕": "boxdL", "╖": "boxDl", "╗": "boxDL", "╘": "boxuR", "╙": "boxUr", "╚": "boxUR", "╛": "boxuL", "╜": "boxUl", "╝": "boxUL", "╞": "boxvR", "╟": "boxVr", "╠": "boxVR", "╡": "boxvL", "╢": "boxVl", "╣": "boxVL", "╤": "boxHd", "╥": "boxhD", "╦": "boxHD", "╧": "boxHu", "╨": "boxhU", "╩": "boxHU", "╪": "boxvH", "╫": "boxVh", "╬": "boxVH", "▀": "uhblk", "▄": "lhblk", "█": "block", "░": "blk14", "▒": "blk12", "▓": "blk34", "□": "squ", "▪": "squf", "▫": "EmptyVerySmallSquare", "▭": "rect", "▮": "marker", "▱": "fltns", "△": "xutri", "▴": "utrif", "▵": "utri", "▸": "rtrif", "▹": "rtri", "▽": "xdtri", "▾": "dtrif", "▿": "dtri", "◂": "ltrif", "◃": "ltri", "◊": "loz", "○": "cir", "◬": "tridot", "◯": "xcirc", "◸": "ultri", "◹": "urtri", "◺": "lltri", "◻": "EmptySmallSquare", "◼": "FilledSmallSquare", "★": "starf", "☆": "star", "☎": "phone", "♀": "female", "♂": "male", "♠": "spades", "♣": "clubs", "♥": "hearts", "♦": "diams", "♪": "sung", "✓": "check", "✗": "cross", "✠": "malt", "✶": "sext", "❘": "VerticalSeparator", "⟈": "bsolhsub", "⟉": "suphsol", "⟵": "xlarr", "⟶": "xrarr", "⟷": "xharr", "⟸": "xlArr", "⟹": "xrArr", "⟺": "xhArr", "⟼": "xmap", "⟿": "dzigrarr", "⤂": "nvlArr", "⤃": "nvrArr", "⤄": "nvHarr", "⤅": "Map", "⤌": "lbarr", "⤍": "rbarr", "⤎": "lBarr", "⤏": "rBarr", "⤐": "RBarr", "⤑": "DDotrahd", "⤒": "UpArrowBar", "⤓": "DownArrowBar", "⤖": "Rarrtl", "⤙": "latail", "⤚": "ratail", "⤛": "lAtail", "⤜": "rAtail", "⤝": "larrfs", "⤞": "rarrfs", "⤟": "larrbfs", "⤠": "rarrbfs", "⤣": "nwarhk", "⤤": "nearhk", "⤥": "searhk", "⤦": "swarhk", "⤧": "nwnear", "⤨": "toea", "⤩": "tosa", "⤪": "swnwar", "⤳": "rarrc", "⤳̸": "nrarrc", "⤵": "cudarrr", "⤶": "ldca", "⤷": "rdca", "⤸": "cudarrl", "⤹": "larrpl", "⤼": "curarrm", "⤽": "cularrp", "⥅": "rarrpl", "⥈": "harrcir", "⥉": "Uarrocir", "⥊": "lurdshar", "⥋": "ldrushar", "⥎": "LeftRightVector", "⥏": "RightUpDownVector", "⥐": "DownLeftRightVector", "⥑": "LeftUpDownVector", "⥒": "LeftVectorBar", "⥓": "RightVectorBar", "⥔": "RightUpVectorBar", "⥕": "RightDownVectorBar", "⥖": "DownLeftVectorBar", "⥗": "DownRightVectorBar", "⥘": "LeftUpVectorBar", "⥙": "LeftDownVectorBar", "⥚": "LeftTeeVector", "⥛": "RightTeeVector", "⥜": "RightUpTeeVector", "⥝": "RightDownTeeVector", "⥞": "DownLeftTeeVector", "⥟": "DownRightTeeVector", "⥠": "LeftUpTeeVector", "⥡": "LeftDownTeeVector", "⥢": "lHar", "⥣": "uHar", "⥤": "rHar", "⥥": "dHar", "⥦": "luruhar", "⥧": "ldrdhar", "⥨": "ruluhar", "⥩": "rdldhar", "⥪": "lharul", "⥫": "llhard", "⥬": "rharul", "⥭": "lrhard", "⥮": "udhar", "⥯": "duhar", "⥰": "RoundImplies", "⥱": "erarr", "⥲": "simrarr", "⥳": "larrsim", "⥴": "rarrsim", "⥵": "rarrap", "⥶": "ltlarr", "⥸": "gtrarr", "⥹": "subrarr", "⥻": "suplarr", "⥼": "lfisht", "⥽": "rfisht", "⥾": "ufisht", "⥿": "dfisht", "⦚": "vzigzag", "⦜": "vangrt", "⦝": "angrtvbd", "⦤": "ange", "⦥": "range", "⦦": "dwangle", "⦧": "uwangle", "⦨": "angmsdaa", "⦩": "angmsdab", "⦪": "angmsdac", "⦫": "angmsdad", "⦬": "angmsdae", "⦭": "angmsdaf", "⦮": "angmsdag", "⦯": "angmsdah", "⦰": "bemptyv", "⦱": "demptyv", "⦲": "cemptyv", "⦳": "raemptyv", "⦴": "laemptyv", "⦵": "ohbar", "⦶": "omid", "⦷": "opar", "⦹": "operp", "⦻": "olcross", "⦼": "odsold", "⦾": "olcir", "⦿": "ofcir", "⧀": "olt", "⧁": "ogt", "⧂": "cirscir", "⧃": "cirE", "⧄": "solb", "⧅": "bsolb", "⧉": "boxbox", "⧍": "trisb", "⧎": "rtriltri", "⧏": "LeftTriangleBar", "⧏̸": "NotLeftTriangleBar", "⧐": "RightTriangleBar", "⧐̸": "NotRightTriangleBar", "⧜": "iinfin", "⧝": "infintie", "⧞": "nvinfin", "⧣": "eparsl", "⧤": "smeparsl", "⧥": "eqvparsl", "⧫": "lozf", "⧴": "RuleDelayed", "⧶": "dsol", "⨀": "xodot", "⨁": "xoplus", "⨂": "xotime", "⨄": "xuplus", "⨆": "xsqcup", "⨍": "fpartint", "⨐": "cirfnint", "⨑": "awint", "⨒": "rppolint", "⨓": "scpolint", "⨔": "npolint", "⨕": "pointint", "⨖": "quatint", "⨗": "intlarhk", "⨢": "pluscir", "⨣": "plusacir", "⨤": "simplus", "⨥": "plusdu", "⨦": "plussim", "⨧": "plustwo", "⨩": "mcomma", "⨪": "minusdu", "⨭": "loplus", "⨮": "roplus", "⨯": "Cross", "⨰": "timesd", "⨱": "timesbar", "⨳": "smashp", "⨴": "lotimes", "⨵": "rotimes", "⨶": "otimesas", "⨷": "Otimes", "⨸": "odiv", "⨹": "triplus", "⨺": "triminus", "⨻": "tritime", "⨼": "iprod", "⨿": "amalg", "⩀": "capdot", "⩂": "ncup", "⩃": "ncap", "⩄": "capand", "⩅": "cupor", "⩆": "cupcap", "⩇": "capcup", "⩈": "cupbrcap", "⩉": "capbrcup", "⩊": "cupcup", "⩋": "capcap", "⩌": "ccups", "⩍": "ccaps", "⩐": "ccupssm", "⩓": "And", "⩔": "Or", "⩕": "andand", "⩖": "oror", "⩗": "orslope", "⩘": "andslope", "⩚": "andv", "⩛": "orv", "⩜": "andd", "⩝": "ord", "⩟": "wedbar", "⩦": "sdote", "⩪": "simdot", "⩭": "congdot", "⩭̸": "ncongdot", "⩮": "easter", "⩯": "apacir", "⩰": "apE", "⩰̸": "napE", "⩱": "eplus", "⩲": "pluse", "⩳": "Esim", "⩷": "eDDot", "⩸": "equivDD", "⩹": "ltcir", "⩺": "gtcir", "⩻": "ltquest", "⩼": "gtquest", "⩽": "les", "⩽̸": "nles", "⩾": "ges", "⩾̸": "nges", "⩿": "lesdot", "⪀": "gesdot", "⪁": "lesdoto", "⪂": "gesdoto", "⪃": "lesdotor", "⪄": "gesdotol", "⪅": "lap", "⪆": "gap", "⪇": "lne", "⪈": "gne", "⪉": "lnap", "⪊": "gnap", "⪋": "lEg", "⪌": "gEl", "⪍": "lsime", "⪎": "gsime", "⪏": "lsimg", "⪐": "gsiml", "⪑": "lgE", "⪒": "glE", "⪓": "lesges", "⪔": "gesles", "⪕": "els", "⪖": "egs", "⪗": "elsdot", "⪘": "egsdot", "⪙": "el", "⪚": "eg", "⪝": "siml", "⪞": "simg", "⪟": "simlE", "⪠": "simgE", "⪡": "LessLess", "⪡̸": "NotNestedLessLess", "⪢": "GreaterGreater", "⪢̸": "NotNestedGreaterGreater", "⪤": "glj", "⪥": "gla", "⪦": "ltcc", "⪧": "gtcc", "⪨": "lescc", "⪩": "gescc", "⪪": "smt", "⪫": "lat", "⪬": "smte", "⪬︀": "smtes", "⪭": "late", "⪭︀": "lates", "⪮": "bumpE", "⪯": "pre", "⪯̸": "npre", "⪰": "sce", "⪰̸": "nsce", "⪳": "prE", "⪴": "scE", "⪵": "prnE", "⪶": "scnE", "⪷": "prap", "⪸": "scap", "⪹": "prnap", "⪺": "scnap", "⪻": "Pr", "⪼": "Sc", "⪽": "subdot", "⪾": "supdot", "⪿": "subplus", "⫀": "supplus", "⫁": "submult", "⫂": "supmult", "⫃": "subedot", "⫄": "supedot", "⫅": "subE", "⫅̸": "nsubE", "⫆": "supE", "⫆̸": "nsupE", "⫇": "subsim", "⫈": "supsim", "⫋︀": "vsubnE", "⫋": "subnE", "⫌︀": "vsupnE", "⫌": "supnE", "⫏": "csub", "⫐": "csup", "⫑": "csube", "⫒": "csupe", "⫓": "subsup", "⫔": "supsub", "⫕": "subsub", "⫖": "supsup", "⫗": "suphsub", "⫘": "supdsub", "⫙": "forkv", "⫚": "topfork", "⫛": "mlcp", "⫤": "Dashv", "⫦": "Vdashl", "⫧": "Barv", "⫨": "vBar", "⫩": "vBarv", "⫫": "Vbar", "⫬": "Not", "⫭": "bNot", "⫮": "rnmid", "⫯": "cirmid", "⫰": "midcir", "⫱": "topcir", "⫲": "nhpar", "⫳": "parsim", "⫽": "parsl", "⫽⃥": "nparsl", "♭": "flat", "♮": "natur", "♯": "sharp", "¤": "curren", "¢": "cent", "$": "dollar", "£": "pound", "¥": "yen", "€": "euro", "¹": "sup1", "½": "half", "⅓": "frac13", "¼": "frac14", "⅕": "frac15", "⅙": "frac16", "⅛": "frac18", "²": "sup2", "⅔": "frac23", "⅖": "frac25", "³": "sup3", "¾": "frac34", "⅗": "frac35", "⅜": "frac38", "⅘": "frac45", "⅚": "frac56", "⅝": "frac58", "⅞": "frac78", "𝒶": "ascr", "𝕒": "aopf", "𝔞": "afr", "𝔸": "Aopf", "𝔄": "Afr", "𝒜": "Ascr", "ª": "ordf", "á": "aacute", "Á": "Aacute", "à": "agrave", "À": "Agrave", "ă": "abreve", "Ă": "Abreve", "â": "acirc", "Â": "Acirc", "å": "aring", "Å": "angst", "ä": "auml", "Ä": "Auml", "ã": "atilde", "Ã": "Atilde", "ą": "aogon", "Ą": "Aogon", "ā": "amacr", "Ā": "Amacr", "æ": "aelig", "Æ": "AElig", "𝒷": "bscr", "𝕓": "bopf", "𝔟": "bfr", "𝔹": "Bopf", "ℬ": "Bscr", "𝔅": "Bfr", "𝔠": "cfr", "𝒸": "cscr", "𝕔": "copf", "ℭ": "Cfr", "𝒞": "Cscr", "ℂ": "Copf", "ć": "cacute", "Ć": "Cacute", "ĉ": "ccirc", "Ĉ": "Ccirc", "č": "ccaron", "Č": "Ccaron", "ċ": "cdot", "Ċ": "Cdot", "ç": "ccedil", "Ç": "Ccedil", "℅": "incare", "𝔡": "dfr", "ⅆ": "dd", "𝕕": "dopf", "𝒹": "dscr", "𝒟": "Dscr", "𝔇": "Dfr", "ⅅ": "DD", "𝔻": "Dopf", "ď": "dcaron", "Ď": "Dcaron", "đ": "dstrok", "Đ": "Dstrok", "ð": "eth", "Ð": "ETH", "ⅇ": "ee", "ℯ": "escr", "𝔢": "efr", "𝕖": "eopf", "ℰ": "Escr", "𝔈": "Efr", "𝔼": "Eopf", "é": "eacute", "É": "Eacute", "è": "egrave", "È": "Egrave", "ê": "ecirc", "Ê": "Ecirc", "ě": "ecaron", "Ě": "Ecaron", "ë": "euml", "Ë": "Euml", "ė": "edot", "Ė": "Edot", "ę": "eogon", "Ę": "Eogon", "ē": "emacr", "Ē": "Emacr", "𝔣": "ffr", "𝕗": "fopf", "𝒻": "fscr", "𝔉": "Ffr", "𝔽": "Fopf", "ℱ": "Fscr", "ﬀ": "fflig", "ﬃ": "ffilig", "ﬄ": "ffllig", "ﬁ": "filig", "fj": "fjlig", "ﬂ": "fllig", "ƒ": "fnof", "ℊ": "gscr", "𝕘": "gopf", "𝔤": "gfr", "𝒢": "Gscr", "𝔾": "Gopf", "𝔊": "Gfr", "ǵ": "gacute", "ğ": "gbreve", "Ğ": "Gbreve", "ĝ": "gcirc", "Ĝ": "Gcirc", "ġ": "gdot", "Ġ": "Gdot", "Ģ": "Gcedil", "𝔥": "hfr", "ℎ": "planckh", "𝒽": "hscr", "𝕙": "hopf", "ℋ": "Hscr", "ℌ": "Hfr", "ℍ": "Hopf", "ĥ": "hcirc", "Ĥ": "Hcirc", "ℏ": "hbar", "ħ": "hstrok", "Ħ": "Hstrok", "𝕚": "iopf", "𝔦": "ifr", "𝒾": "iscr", "ⅈ": "ii", "𝕀": "Iopf", "ℐ": "Iscr", "ℑ": "Im", "í": "iacute", "Í": "Iacute", "ì": "igrave", "Ì": "Igrave", "î": "icirc", "Î": "Icirc", "ï": "iuml", "Ï": "Iuml", "ĩ": "itilde", "Ĩ": "Itilde", "İ": "Idot", "į": "iogon", "Į": "Iogon", "ī": "imacr", "Ī": "Imacr", "ĳ": "ijlig", "Ĳ": "IJlig", "ı": "imath", "𝒿": "jscr", "𝕛": "jopf", "𝔧": "jfr", "𝒥": "Jscr", "𝔍": "Jfr", "𝕁": "Jopf", "ĵ": "jcirc", "Ĵ": "Jcirc", "ȷ": "jmath", "𝕜": "kopf", "𝓀": "kscr", "𝔨": "kfr", "𝒦": "Kscr", "𝕂": "Kopf", "𝔎": "Kfr", "ķ": "kcedil", "Ķ": "Kcedil", "𝔩": "lfr", "𝓁": "lscr", "ℓ": "ell", "𝕝": "lopf", "ℒ": "Lscr", "𝔏": "Lfr", "𝕃": "Lopf", "ĺ": "lacute", "Ĺ": "Lacute", "ľ": "lcaron", "Ľ": "Lcaron", "ļ": "lcedil", "Ļ": "Lcedil", "ł": "lstrok", "Ł": "Lstrok", "ŀ": "lmidot", "Ŀ": "Lmidot", "𝔪": "mfr", "𝕞": "mopf", "𝓂": "mscr", "𝔐": "Mfr", "𝕄": "Mopf", "ℳ": "Mscr", "𝔫": "nfr", "𝕟": "nopf", "𝓃": "nscr", "ℕ": "Nopf", "𝒩": "Nscr", "𝔑": "Nfr", "ń": "nacute", "Ń": "Nacute", "ň": "ncaron", "Ň": "Ncaron", "ñ": "ntilde", "Ñ": "Ntilde", "ņ": "ncedil", "Ņ": "Ncedil", "№": "numero", "ŋ": "eng", "Ŋ": "ENG", "𝕠": "oopf", "𝔬": "ofr", "ℴ": "oscr", "𝒪": "Oscr", "𝔒": "Ofr", "𝕆": "Oopf", "º": "ordm", "ó": "oacute", "Ó": "Oacute", "ò": "ograve", "Ò": "Ograve", "ô": "ocirc", "Ô": "Ocirc", "ö": "ouml", "Ö": "Ouml", "ő": "odblac", "Ő": "Odblac", "õ": "otilde", "Õ": "Otilde", "ø": "oslash", "Ø": "Oslash", "ō": "omacr", "Ō": "Omacr", "œ": "oelig", "Œ": "OElig", "𝔭": "pfr", "𝓅": "pscr", "𝕡": "popf", "ℙ": "Popf", "𝔓": "Pfr", "𝒫": "Pscr", "𝕢": "qopf", "𝔮": "qfr", "𝓆": "qscr", "𝒬": "Qscr", "𝔔": "Qfr", "ℚ": "Qopf", "ĸ": "kgreen", "𝔯": "rfr", "𝕣": "ropf", "𝓇": "rscr", "ℛ": "Rscr", "ℜ": "Re", "ℝ": "Ropf", "ŕ": "racute", "Ŕ": "Racute", "ř": "rcaron", "Ř": "Rcaron", "ŗ": "rcedil", "Ŗ": "Rcedil", "𝕤": "sopf", "𝓈": "sscr", "𝔰": "sfr", "𝕊": "Sopf", "𝔖": "Sfr", "𝒮": "Sscr", "Ⓢ": "oS", "ś": "sacute", "Ś": "Sacute", "ŝ": "scirc", "Ŝ": "Scirc", "š": "scaron", "Š": "Scaron", "ş": "scedil", "Ş": "Scedil", "ß": "szlig", "𝔱": "tfr", "𝓉": "tscr", "𝕥": "topf", "𝒯": "Tscr", "𝔗": "Tfr", "𝕋": "Topf", "ť": "tcaron", "Ť": "Tcaron", "ţ": "tcedil", "Ţ": "Tcedil", "™": "trade", "ŧ": "tstrok", "Ŧ": "Tstrok", "𝓊": "uscr", "𝕦": "uopf", "𝔲": "ufr", "𝕌": "Uopf", "𝔘": "Ufr", "𝒰": "Uscr", "ú": "uacute", "Ú": "Uacute", "ù": "ugrave", "Ù": "Ugrave", "ŭ": "ubreve", "Ŭ": "Ubreve", "û": "ucirc", "Û": "Ucirc", "ů": "uring", "Ů": "Uring", "ü": "uuml", "Ü": "Uuml", "ű": "udblac", "Ű": "Udblac", "ũ": "utilde", "Ũ": "Utilde", "ų": "uogon", "Ų": "Uogon", "ū": "umacr", "Ū": "Umacr", "𝔳": "vfr", "𝕧": "vopf", "𝓋": "vscr", "𝔙": "Vfr", "𝕍": "Vopf", "𝒱": "Vscr", "𝕨": "wopf", "𝓌": "wscr", "𝔴": "wfr", "𝒲": "Wscr", "𝕎": "Wopf", "𝔚": "Wfr", "ŵ": "wcirc", "Ŵ": "Wcirc", "𝔵": "xfr", "𝓍": "xscr", "𝕩": "xopf", "𝕏": "Xopf", "𝔛": "Xfr", "𝒳": "Xscr", "𝔶": "yfr", "𝓎": "yscr", "𝕪": "yopf", "𝒴": "Yscr", "𝔜": "Yfr", "𝕐": "Yopf", "ý": "yacute", "Ý": "Yacute", "ŷ": "ycirc", "Ŷ": "Ycirc", "ÿ": "yuml", "Ÿ": "Yuml", "𝓏": "zscr", "𝔷": "zfr", "𝕫": "zopf", "ℨ": "Zfr", "ℤ": "Zopf", "𝒵": "Zscr", "ź": "zacute", "Ź": "Zacute", "ž": "zcaron", "Ž": "Zcaron", "ż": "zdot", "Ż": "Zdot", "Ƶ": "imped", "þ": "thorn", "Þ": "THORN", "ŉ": "napos", "α": "alpha", "Α": "Alpha", "β": "beta", "Β": "Beta", "γ": "gamma", "Γ": "Gamma", "δ": "delta", "Δ": "Delta", "ε": "epsi", "ϵ": "epsiv", "Ε": "Epsilon", "ϝ": "gammad", "Ϝ": "Gammad", "ζ": "zeta", "Ζ": "Zeta", "η": "eta", "Η": "Eta", "θ": "theta", "ϑ": "thetav", "Θ": "Theta", "ι": "iota", "Ι": "Iota", "κ": "kappa", "ϰ": "kappav", "Κ": "Kappa", "λ": "lambda", "Λ": "Lambda", "μ": "mu", "µ": "micro", "Μ": "Mu", "ν": "nu", "Ν": "Nu", "ξ": "xi", "Ξ": "Xi", "ο": "omicron", "Ο": "Omicron", "π": "pi", "ϖ": "piv", "Π": "Pi", "ρ": "rho", "ϱ": "rhov", "Ρ": "Rho", "σ": "sigma", "Σ": "Sigma", "ς": "sigmaf", "τ": "tau", "Τ": "Tau", "υ": "upsi", "Υ": "Upsilon", "ϒ": "Upsi", "φ": "phi", "ϕ": "phiv", "Φ": "Phi", "χ": "chi", "Χ": "Chi", "ψ": "psi", "Ψ": "Psi", "ω": "omega", "Ω": "ohm", "а": "acy", "А": "Acy", "б": "bcy", "Б": "Bcy", "в": "vcy", "В": "Vcy", "г": "gcy", "Г": "Gcy", "ѓ": "gjcy", "Ѓ": "GJcy", "д": "dcy", "Д": "Dcy", "ђ": "djcy", "Ђ": "DJcy", "е": "iecy", "Е": "IEcy", "ё": "iocy", "Ё": "IOcy", "є": "jukcy", "Є": "Jukcy", "ж": "zhcy", "Ж": "ZHcy", "з": "zcy", "З": "Zcy", "ѕ": "dscy", "Ѕ": "DScy", "и": "icy", "И": "Icy", "і": "iukcy", "І": "Iukcy", "ї": "yicy", "Ї": "YIcy", "й": "jcy", "Й": "Jcy", "ј": "jsercy", "Ј": "Jsercy", "к": "kcy", "К": "Kcy", "ќ": "kjcy", "Ќ": "KJcy", "л": "lcy", "Л": "Lcy", "љ": "ljcy", "Љ": "LJcy", "м": "mcy", "М": "Mcy", "н": "ncy", "Н": "Ncy", "њ": "njcy", "Њ": "NJcy", "о": "ocy", "О": "Ocy", "п": "pcy", "П": "Pcy", "р": "rcy", "Р": "Rcy", "с": "scy", "С": "Scy", "т": "tcy", "Т": "Tcy", "ћ": "tshcy", "Ћ": "TSHcy", "у": "ucy", "У": "Ucy", "ў": "ubrcy", "Ў": "Ubrcy", "ф": "fcy", "Ф": "Fcy", "х": "khcy", "Х": "KHcy", "ц": "tscy", "Ц": "TScy", "ч": "chcy", "Ч": "CHcy", "џ": "dzcy", "Џ": "DZcy", "ш": "shcy", "Ш": "SHcy", "щ": "shchcy", "Щ": "SHCHcy", "ъ": "hardcy", "Ъ": "HARDcy", "ы": "ycy", "Ы": "Ycy", "ь": "softcy", "Ь": "SOFTcy", "э": "ecy", "Э": "Ecy", "ю": "yucy", "Ю": "YUcy", "я": "yacy", "Я": "YAcy", "ℵ": "aleph", "ℶ": "beth", "ℷ": "gimel", "ℸ": "daleth" };
      var regexEscape = /["&'<>`]/g;
      var escapeMap = {
        '"': "&quot;",
        "&": "&amp;",
        "'": "&#x27;",
        "<": "&lt;",
        // See https://mathiasbynens.be/notes/ambiguous-ampersands: in HTML, the
        // following is not strictly necessary unless it’s part of a tag or an
        // unquoted attribute value. We’re only escaping it to support those
        // situations, and for XML support.
        ">": "&gt;",
        // In Internet Explorer ≤ 8, the backtick character can be used
        // to break out of (un)quoted attribute values or HTML comments.
        // See http://html5sec.org/#102, http://html5sec.org/#108, and
        // http://html5sec.org/#133.
        "`": "&#x60;"
      };
      var regexInvalidEntity = /&#(?:[xX][^a-fA-F0-9]|[^0-9xX])/;
      var regexInvalidRawCodePoint = /[\0-\x08\x0B\x0E-\x1F\x7F-\x9F\uFDD0-\uFDEF\uFFFE\uFFFF]|[\uD83F\uD87F\uD8BF\uD8FF\uD93F\uD97F\uD9BF\uD9FF\uDA3F\uDA7F\uDABF\uDAFF\uDB3F\uDB7F\uDBBF\uDBFF][\uDFFE\uDFFF]|[\uD800-\uDBFF](?![\uDC00-\uDFFF])|(?:[^\uD800-\uDBFF]|^)[\uDC00-\uDFFF]/;
      var regexDecode = /&(CounterClockwiseContourIntegral|DoubleLongLeftRightArrow|ClockwiseContourIntegral|NotNestedGreaterGreater|NotSquareSupersetEqual|DiacriticalDoubleAcute|NotRightTriangleEqual|NotSucceedsSlantEqual|NotPrecedesSlantEqual|CloseCurlyDoubleQuote|NegativeVeryThinSpace|DoubleContourIntegral|FilledVerySmallSquare|CapitalDifferentialD|OpenCurlyDoubleQuote|EmptyVerySmallSquare|NestedGreaterGreater|DoubleLongRightArrow|NotLeftTriangleEqual|NotGreaterSlantEqual|ReverseUpEquilibrium|DoubleLeftRightArrow|NotSquareSubsetEqual|NotDoubleVerticalBar|RightArrowLeftArrow|NotGreaterFullEqual|NotRightTriangleBar|SquareSupersetEqual|DownLeftRightVector|DoubleLongLeftArrow|leftrightsquigarrow|LeftArrowRightArrow|NegativeMediumSpace|blacktriangleright|RightDownVectorBar|PrecedesSlantEqual|RightDoubleBracket|SucceedsSlantEqual|NotLeftTriangleBar|RightTriangleEqual|SquareIntersection|RightDownTeeVector|ReverseEquilibrium|NegativeThickSpace|longleftrightarrow|Longleftrightarrow|LongLeftRightArrow|DownRightTeeVector|DownRightVectorBar|GreaterSlantEqual|SquareSubsetEqual|LeftDownVectorBar|LeftDoubleBracket|VerticalSeparator|rightleftharpoons|NotGreaterGreater|NotSquareSuperset|blacktriangleleft|blacktriangledown|NegativeThinSpace|LeftDownTeeVector|NotLessSlantEqual|leftrightharpoons|DoubleUpDownArrow|DoubleVerticalBar|LeftTriangleEqual|FilledSmallSquare|twoheadrightarrow|NotNestedLessLess|DownLeftTeeVector|DownLeftVectorBar|RightAngleBracket|NotTildeFullEqual|NotReverseElement|RightUpDownVector|DiacriticalTilde|NotSucceedsTilde|circlearrowright|NotPrecedesEqual|rightharpoondown|DoubleRightArrow|NotSucceedsEqual|NonBreakingSpace|NotRightTriangle|LessEqualGreater|RightUpTeeVector|LeftAngleBracket|GreaterFullEqual|DownArrowUpArrow|RightUpVectorBar|twoheadleftarrow|GreaterEqualLess|downharpoonright|RightTriangleBar|ntrianglerighteq|NotSupersetEqual|LeftUpDownVector|DiacriticalAcute|rightrightarrows|vartriangleright|UpArrowDownArrow|DiacriticalGrave|UnderParenthesis|EmptySmallSquare|LeftUpVectorBar|leftrightarrows|DownRightVector|downharpoonleft|trianglerighteq|ShortRightArrow|OverParenthesis|DoubleLeftArrow|DoubleDownArrow|NotSquareSubset|bigtriangledown|ntrianglelefteq|UpperRightArrow|curvearrowright|vartriangleleft|NotLeftTriangle|nleftrightarrow|LowerRightArrow|NotHumpDownHump|NotGreaterTilde|rightthreetimes|LeftUpTeeVector|NotGreaterEqual|straightepsilon|LeftTriangleBar|rightsquigarrow|ContourIntegral|rightleftarrows|CloseCurlyQuote|RightDownVector|LeftRightVector|nLeftrightarrow|leftharpoondown|circlearrowleft|SquareSuperset|OpenCurlyQuote|hookrightarrow|HorizontalLine|DiacriticalDot|NotLessGreater|ntriangleright|DoubleRightTee|InvisibleComma|InvisibleTimes|LowerLeftArrow|DownLeftVector|NotSubsetEqual|curvearrowleft|trianglelefteq|NotVerticalBar|TildeFullEqual|downdownarrows|NotGreaterLess|RightTeeVector|ZeroWidthSpace|looparrowright|LongRightArrow|doublebarwedge|ShortLeftArrow|ShortDownArrow|RightVectorBar|GreaterGreater|ReverseElement|rightharpoonup|LessSlantEqual|leftthreetimes|upharpoonright|rightarrowtail|LeftDownVector|Longrightarrow|NestedLessLess|UpperLeftArrow|nshortparallel|leftleftarrows|leftrightarrow|Leftrightarrow|LeftRightArrow|longrightarrow|upharpoonleft|RightArrowBar|ApplyFunction|LeftTeeVector|leftarrowtail|NotEqualTilde|varsubsetneqq|varsupsetneqq|RightTeeArrow|SucceedsEqual|SucceedsTilde|LeftVectorBar|SupersetEqual|hookleftarrow|DifferentialD|VerticalTilde|VeryThinSpace|blacktriangle|bigtriangleup|LessFullEqual|divideontimes|leftharpoonup|UpEquilibrium|ntriangleleft|RightTriangle|measuredangle|shortparallel|longleftarrow|Longleftarrow|LongLeftArrow|DoubleLeftTee|Poincareplane|PrecedesEqual|triangleright|DoubleUpArrow|RightUpVector|fallingdotseq|looparrowleft|PrecedesTilde|NotTildeEqual|NotTildeTilde|smallsetminus|Proportional|triangleleft|triangledown|UnderBracket|NotHumpEqual|exponentiale|ExponentialE|NotLessTilde|HilbertSpace|RightCeiling|blacklozenge|varsupsetneq|HumpDownHump|GreaterEqual|VerticalLine|LeftTeeArrow|NotLessEqual|DownTeeArrow|LeftTriangle|varsubsetneq|Intersection|NotCongruent|DownArrowBar|LeftUpVector|LeftArrowBar|risingdotseq|GreaterTilde|RoundImplies|SquareSubset|ShortUpArrow|NotSuperset|quaternions|precnapprox|backepsilon|preccurlyeq|OverBracket|blacksquare|MediumSpace|VerticalBar|circledcirc|circleddash|CircleMinus|CircleTimes|LessGreater|curlyeqprec|curlyeqsucc|diamondsuit|UpDownArrow|Updownarrow|RuleDelayed|Rrightarrow|updownarrow|RightVector|nRightarrow|nrightarrow|eqslantless|LeftCeiling|Equilibrium|SmallCircle|expectation|NotSucceeds|thickapprox|GreaterLess|SquareUnion|NotPrecedes|NotLessLess|straightphi|succnapprox|succcurlyeq|SubsetEqual|sqsupseteq|Proportion|Laplacetrf|ImaginaryI|supsetneqq|NotGreater|gtreqqless|NotElement|ThickSpace|TildeEqual|TildeTilde|Fouriertrf|rmoustache|EqualTilde|eqslantgtr|UnderBrace|LeftVector|UpArrowBar|nLeftarrow|nsubseteqq|subsetneqq|nsupseteqq|nleftarrow|succapprox|lessapprox|UpTeeArrow|upuparrows|curlywedge|lesseqqgtr|varepsilon|varnothing|RightFloor|complement|CirclePlus|sqsubseteq|Lleftarrow|circledast|RightArrow|Rightarrow|rightarrow|lmoustache|Bernoullis|precapprox|mapstoleft|mapstodown|longmapsto|dotsquare|downarrow|DoubleDot|nsubseteq|supsetneq|leftarrow|nsupseteq|subsetneq|ThinSpace|ngeqslant|subseteqq|HumpEqual|NotSubset|triangleq|NotCupCap|lesseqgtr|heartsuit|TripleDot|Leftarrow|Coproduct|Congruent|varpropto|complexes|gvertneqq|LeftArrow|LessTilde|supseteqq|MinusPlus|CircleDot|nleqslant|NotExists|gtreqless|nparallel|UnionPlus|LeftFloor|checkmark|CenterDot|centerdot|Mellintrf|gtrapprox|bigotimes|OverBrace|spadesuit|therefore|pitchfork|rationals|PlusMinus|Backslash|Therefore|DownBreve|backsimeq|backprime|DownArrow|nshortmid|Downarrow|lvertneqq|eqvparsl|imagline|imagpart|infintie|integers|Integral|intercal|LessLess|Uarrocir|intlarhk|sqsupset|angmsdaf|sqsubset|llcorner|vartheta|cupbrcap|lnapprox|Superset|SuchThat|succnsim|succneqq|angmsdag|biguplus|curlyvee|trpezium|Succeeds|NotTilde|bigwedge|angmsdah|angrtvbd|triminus|cwconint|fpartint|lrcorner|smeparsl|subseteq|urcorner|lurdshar|laemptyv|DDotrahd|approxeq|ldrushar|awconint|mapstoup|backcong|shortmid|triangle|geqslant|gesdotol|timesbar|circledR|circledS|setminus|multimap|naturals|scpolint|ncongdot|RightTee|boxminus|gnapprox|boxtimes|andslope|thicksim|angmsdaa|varsigma|cirfnint|rtriltri|angmsdab|rppolint|angmsdac|barwedge|drbkarow|clubsuit|thetasym|bsolhsub|capbrcup|dzigrarr|doteqdot|DotEqual|dotminus|UnderBar|NotEqual|realpart|otimesas|ulcorner|hksearow|hkswarow|parallel|PartialD|elinters|emptyset|plusacir|bbrktbrk|angmsdad|pointint|bigoplus|angmsdae|Precedes|bigsqcup|varkappa|notindot|supseteq|precneqq|precnsim|profalar|profline|profsurf|leqslant|lesdotor|raemptyv|subplus|notnivb|notnivc|subrarr|zigrarr|vzigzag|submult|subedot|Element|between|cirscir|larrbfs|larrsim|lotimes|lbrksld|lbrkslu|lozenge|ldrdhar|dbkarow|bigcirc|epsilon|simrarr|simplus|ltquest|Epsilon|luruhar|gtquest|maltese|npolint|eqcolon|npreceq|bigodot|ddagger|gtrless|bnequiv|harrcir|ddotseq|equivDD|backsim|demptyv|nsqsube|nsqsupe|Upsilon|nsubset|upsilon|minusdu|nsucceq|swarrow|nsupset|coloneq|searrow|boxplus|napprox|natural|asympeq|alefsym|congdot|nearrow|bigstar|diamond|supplus|tritime|LeftTee|nvinfin|triplus|NewLine|nvltrie|nvrtrie|nwarrow|nexists|Diamond|ruluhar|Implies|supmult|angzarr|suplarr|suphsub|questeq|because|digamma|Because|olcross|bemptyv|omicron|Omicron|rotimes|NoBreak|intprod|angrtvb|orderof|uwangle|suphsol|lesdoto|orslope|DownTee|realine|cudarrl|rdldhar|OverBar|supedot|lessdot|supdsub|topfork|succsim|rbrkslu|rbrksld|pertenk|cudarrr|isindot|planckh|lessgtr|pluscir|gesdoto|plussim|plustwo|lesssim|cularrp|rarrsim|Cayleys|notinva|notinvb|notinvc|UpArrow|Uparrow|uparrow|NotLess|dwangle|precsim|Product|curarrm|Cconint|dotplus|rarrbfs|ccupssm|Cedilla|cemptyv|notniva|quatint|frac35|frac38|frac45|frac56|frac58|frac78|tridot|xoplus|gacute|gammad|Gammad|lfisht|lfloor|bigcup|sqsupe|gbreve|Gbreve|lharul|sqsube|sqcups|Gcedil|apacir|llhard|lmidot|Lmidot|lmoust|andand|sqcaps|approx|Abreve|spades|circeq|tprime|divide|topcir|Assign|topbot|gesdot|divonx|xuplus|timesd|gesles|atilde|solbar|SOFTcy|loplus|timesb|lowast|lowbar|dlcorn|dlcrop|softcy|dollar|lparlt|thksim|lrhard|Atilde|lsaquo|smashp|bigvee|thinsp|wreath|bkarow|lsquor|lstrok|Lstrok|lthree|ltimes|ltlarr|DotDot|simdot|ltrPar|weierp|xsqcup|angmsd|sigmav|sigmaf|zeetrf|Zcaron|zcaron|mapsto|vsupne|thetav|cirmid|marker|mcomma|Zacute|vsubnE|there4|gtlPar|vsubne|bottom|gtrarr|SHCHcy|shchcy|midast|midcir|middot|minusb|minusd|gtrdot|bowtie|sfrown|mnplus|models|colone|seswar|Colone|mstpos|searhk|gtrsim|nacute|Nacute|boxbox|telrec|hairsp|Tcedil|nbumpe|scnsim|ncaron|Ncaron|ncedil|Ncedil|hamilt|Scedil|nearhk|hardcy|HARDcy|tcedil|Tcaron|commat|nequiv|nesear|tcaron|target|hearts|nexist|varrho|scedil|Scaron|scaron|hellip|Sacute|sacute|hercon|swnwar|compfn|rtimes|rthree|rsquor|rsaquo|zacute|wedgeq|homtht|barvee|barwed|Barwed|rpargt|horbar|conint|swarhk|roplus|nltrie|hslash|hstrok|Hstrok|rmoust|Conint|bprime|hybull|hyphen|iacute|Iacute|supsup|supsub|supsim|varphi|coprod|brvbar|agrave|Supset|supset|igrave|Igrave|notinE|Agrave|iiiint|iinfin|copysr|wedbar|Verbar|vangrt|becaus|incare|verbar|inodot|bullet|drcorn|intcal|drcrop|cularr|vellip|Utilde|bumpeq|cupcap|dstrok|Dstrok|CupCap|cupcup|cupdot|eacute|Eacute|supdot|iquest|easter|ecaron|Ecaron|ecolon|isinsv|utilde|itilde|Itilde|curarr|succeq|Bumpeq|cacute|ulcrop|nparsl|Cacute|nprcue|egrave|Egrave|nrarrc|nrarrw|subsup|subsub|nrtrie|jsercy|nsccue|Jsercy|kappav|kcedil|Kcedil|subsim|ulcorn|nsimeq|egsdot|veebar|kgreen|capand|elsdot|Subset|subset|curren|aacute|lacute|Lacute|emptyv|ntilde|Ntilde|lagran|lambda|Lambda|capcap|Ugrave|langle|subdot|emsp13|numero|emsp14|nvdash|nvDash|nVdash|nVDash|ugrave|ufisht|nvHarr|larrfs|nvlArr|larrhk|larrlp|larrpl|nvrArr|Udblac|nwarhk|larrtl|nwnear|oacute|Oacute|latail|lAtail|sstarf|lbrace|odblac|Odblac|lbrack|udblac|odsold|eparsl|lcaron|Lcaron|ograve|Ograve|lcedil|Lcedil|Aacute|ssmile|ssetmn|squarf|ldquor|capcup|ominus|cylcty|rharul|eqcirc|dagger|rfloor|rfisht|Dagger|daleth|equals|origof|capdot|equest|dcaron|Dcaron|rdquor|oslash|Oslash|otilde|Otilde|otimes|Otimes|urcrop|Ubreve|ubreve|Yacute|Uacute|uacute|Rcedil|rcedil|urcorn|parsim|Rcaron|Vdashl|rcaron|Tstrok|percnt|period|permil|Exists|yacute|rbrack|rbrace|phmmat|ccaron|Ccaron|planck|ccedil|plankv|tstrok|female|plusdo|plusdu|ffilig|plusmn|ffllig|Ccedil|rAtail|dfisht|bernou|ratail|Rarrtl|rarrtl|angsph|rarrpl|rarrlp|rarrhk|xwedge|xotime|forall|ForAll|Vvdash|vsupnE|preceq|bigcap|frac12|frac13|frac14|primes|rarrfs|prnsim|frac15|Square|frac16|square|lesdot|frac18|frac23|propto|prurel|rarrap|rangle|puncsp|frac25|Racute|qprime|racute|lesges|frac34|abreve|AElig|eqsim|utdot|setmn|urtri|Equal|Uring|seArr|uring|searr|dashv|Dashv|mumap|nabla|iogon|Iogon|sdote|sdotb|scsim|napid|napos|equiv|natur|Acirc|dblac|erarr|nbump|iprod|erDot|ucirc|awint|esdot|angrt|ncong|isinE|scnap|Scirc|scirc|ndash|isins|Ubrcy|nearr|neArr|isinv|nedot|ubrcy|acute|Ycirc|iukcy|Iukcy|xutri|nesim|caret|jcirc|Jcirc|caron|twixt|ddarr|sccue|exist|jmath|sbquo|ngeqq|angst|ccaps|lceil|ngsim|UpTee|delta|Delta|rtrif|nharr|nhArr|nhpar|rtrie|jukcy|Jukcy|kappa|rsquo|Kappa|nlarr|nlArr|TSHcy|rrarr|aogon|Aogon|fflig|xrarr|tshcy|ccirc|nleqq|filig|upsih|nless|dharl|nlsim|fjlig|ropar|nltri|dharr|robrk|roarr|fllig|fltns|roang|rnmid|subnE|subne|lAarr|trisb|Ccirc|acirc|ccups|blank|VDash|forkv|Vdash|langd|cedil|blk12|blk14|laquo|strns|diams|notin|vDash|larrb|blk34|block|disin|uplus|vdash|vBarv|aelig|starf|Wedge|check|xrArr|lates|lbarr|lBarr|notni|lbbrk|bcong|frasl|lbrke|frown|vrtri|vprop|vnsup|gamma|Gamma|wedge|xodot|bdquo|srarr|doteq|ldquo|boxdl|boxdL|gcirc|Gcirc|boxDl|boxDL|boxdr|boxdR|boxDr|TRADE|trade|rlhar|boxDR|vnsub|npart|vltri|rlarr|boxhd|boxhD|nprec|gescc|nrarr|nrArr|boxHd|boxHD|boxhu|boxhU|nrtri|boxHu|clubs|boxHU|times|colon|Colon|gimel|xlArr|Tilde|nsime|tilde|nsmid|nspar|THORN|thorn|xlarr|nsube|nsubE|thkap|xhArr|comma|nsucc|boxul|boxuL|nsupe|nsupE|gneqq|gnsim|boxUl|boxUL|grave|boxur|boxuR|boxUr|boxUR|lescc|angle|bepsi|boxvh|varpi|boxvH|numsp|Theta|gsime|gsiml|theta|boxVh|boxVH|boxvl|gtcir|gtdot|boxvL|boxVl|boxVL|crarr|cross|Cross|nvsim|boxvr|nwarr|nwArr|sqsup|dtdot|Uogon|lhard|lharu|dtrif|ocirc|Ocirc|lhblk|duarr|odash|sqsub|Hacek|sqcup|llarr|duhar|oelig|OElig|ofcir|boxvR|uogon|lltri|boxVr|csube|uuarr|ohbar|csupe|ctdot|olarr|olcir|harrw|oline|sqcap|omacr|Omacr|omega|Omega|boxVR|aleph|lneqq|lnsim|loang|loarr|rharu|lobrk|hcirc|operp|oplus|rhard|Hcirc|orarr|Union|order|ecirc|Ecirc|cuepr|szlig|cuesc|breve|reals|eDDot|Breve|hoarr|lopar|utrif|rdquo|Umacr|umacr|efDot|swArr|ultri|alpha|rceil|ovbar|swarr|Wcirc|wcirc|smtes|smile|bsemi|lrarr|aring|parsl|lrhar|bsime|uhblk|lrtri|cupor|Aring|uharr|uharl|slarr|rbrke|bsolb|lsime|rbbrk|RBarr|lsimg|phone|rBarr|rbarr|icirc|lsquo|Icirc|emacr|Emacr|ratio|simne|plusb|simlE|simgE|simeq|pluse|ltcir|ltdot|empty|xharr|xdtri|iexcl|Alpha|ltrie|rarrw|pound|ltrif|xcirc|bumpe|prcue|bumpE|asymp|amacr|cuvee|Sigma|sigma|iiint|udhar|iiota|ijlig|IJlig|supnE|imacr|Imacr|prime|Prime|image|prnap|eogon|Eogon|rarrc|mdash|mDDot|cuwed|imath|supne|imped|Amacr|udarr|prsim|micro|rarrb|cwint|raquo|infin|eplus|range|rangd|Ucirc|radic|minus|amalg|veeeq|rAarr|epsiv|ycirc|quest|sharp|quot|zwnj|Qscr|race|qscr|Qopf|qopf|qint|rang|Rang|Zscr|zscr|Zopf|zopf|rarr|rArr|Rarr|Pscr|pscr|prop|prod|prnE|prec|ZHcy|zhcy|prap|Zeta|zeta|Popf|popf|Zdot|plus|zdot|Yuml|yuml|phiv|YUcy|yucy|Yscr|yscr|perp|Yopf|yopf|part|para|YIcy|Ouml|rcub|yicy|YAcy|rdca|ouml|osol|Oscr|rdsh|yacy|real|oscr|xvee|andd|rect|andv|Xscr|oror|ordm|ordf|xscr|ange|aopf|Aopf|rHar|Xopf|opar|Oopf|xopf|xnis|rhov|oopf|omid|xmap|oint|apid|apos|ogon|ascr|Ascr|odot|odiv|xcup|xcap|ocir|oast|nvlt|nvle|nvgt|nvge|nvap|Wscr|wscr|auml|ntlg|ntgl|nsup|nsub|nsim|Nscr|nscr|nsce|Wopf|ring|npre|wopf|npar|Auml|Barv|bbrk|Nopf|nopf|nmid|nLtv|beta|ropf|Ropf|Beta|beth|nles|rpar|nleq|bnot|bNot|nldr|NJcy|rscr|Rscr|Vscr|vscr|rsqb|njcy|bopf|nisd|Bopf|rtri|Vopf|nGtv|ngtr|vopf|boxh|boxH|boxv|nges|ngeq|boxV|bscr|scap|Bscr|bsim|Vert|vert|bsol|bull|bump|caps|cdot|ncup|scnE|ncap|nbsp|napE|Cdot|cent|sdot|Vbar|nang|vBar|chcy|Mscr|mscr|sect|semi|CHcy|Mopf|mopf|sext|circ|cire|mldr|mlcp|cirE|comp|shcy|SHcy|vArr|varr|cong|copf|Copf|copy|COPY|malt|male|macr|lvnE|cscr|ltri|sime|ltcc|simg|Cscr|siml|csub|Uuml|lsqb|lsim|uuml|csup|Lscr|lscr|utri|smid|lpar|cups|smte|lozf|darr|Lopf|Uscr|solb|lopf|sopf|Sopf|lneq|uscr|spar|dArr|lnap|Darr|dash|Sqrt|LJcy|ljcy|lHar|dHar|Upsi|upsi|diam|lesg|djcy|DJcy|leqq|dopf|Dopf|dscr|Dscr|dscy|ldsh|ldca|squf|DScy|sscr|Sscr|dsol|lcub|late|star|Star|Uopf|Larr|lArr|larr|uopf|dtri|dzcy|sube|subE|Lang|lang|Kscr|kscr|Kopf|kopf|KJcy|kjcy|KHcy|khcy|DZcy|ecir|edot|eDot|Jscr|jscr|succ|Jopf|jopf|Edot|uHar|emsp|ensp|Iuml|iuml|eopf|isin|Iscr|iscr|Eopf|epar|sung|epsi|escr|sup1|sup2|sup3|Iota|iota|supe|supE|Iopf|iopf|IOcy|iocy|Escr|esim|Esim|imof|Uarr|QUOT|uArr|uarr|euml|IEcy|iecy|Idot|Euml|euro|excl|Hscr|hscr|Hopf|hopf|TScy|tscy|Tscr|hbar|tscr|flat|tbrk|fnof|hArr|harr|half|fopf|Fopf|tdot|gvnE|fork|trie|gtcc|fscr|Fscr|gdot|gsim|Gscr|gscr|Gopf|gopf|gneq|Gdot|tosa|gnap|Topf|topf|geqq|toea|GJcy|gjcy|tint|gesl|mid|Sfr|ggg|top|ges|gla|glE|glj|geq|gne|gEl|gel|gnE|Gcy|gcy|gap|Tfr|tfr|Tcy|tcy|Hat|Tau|Ffr|tau|Tab|hfr|Hfr|ffr|Fcy|fcy|icy|Icy|iff|ETH|eth|ifr|Ifr|Eta|eta|int|Int|Sup|sup|ucy|Ucy|Sum|sum|jcy|ENG|ufr|Ufr|eng|Jcy|jfr|els|ell|egs|Efr|efr|Jfr|uml|kcy|Kcy|Ecy|ecy|kfr|Kfr|lap|Sub|sub|lat|lcy|Lcy|leg|Dot|dot|lEg|leq|les|squ|div|die|lfr|Lfr|lgE|Dfr|dfr|Del|deg|Dcy|dcy|lne|lnE|sol|loz|smt|Cup|lrm|cup|lsh|Lsh|sim|shy|map|Map|mcy|Mcy|mfr|Mfr|mho|gfr|Gfr|sfr|cir|Chi|chi|nap|Cfr|vcy|Vcy|cfr|Scy|scy|ncy|Ncy|vee|Vee|Cap|cap|nfr|scE|sce|Nfr|nge|ngE|nGg|vfr|Vfr|ngt|bot|nGt|nis|niv|Rsh|rsh|nle|nlE|bne|Bfr|bfr|nLl|nlt|nLt|Bcy|bcy|not|Not|rlm|wfr|Wfr|npr|nsc|num|ocy|ast|Ocy|ofr|xfr|Xfr|Ofr|ogt|ohm|apE|olt|Rho|ape|rho|Rfr|rfr|ord|REG|ang|reg|orv|And|and|AMP|Rcy|amp|Afr|ycy|Ycy|yen|yfr|Yfr|rcy|par|pcy|Pcy|pfr|Pfr|phi|Phi|afr|Acy|acy|zcy|Zcy|piv|acE|acd|zfr|Zfr|pre|prE|psi|Psi|qfr|Qfr|zwj|Or|ge|Gg|gt|gg|el|oS|lt|Lt|LT|Re|lg|gl|eg|ne|Im|it|le|DD|wp|wr|nu|Nu|dd|lE|Sc|sc|pi|Pi|ee|af|ll|Ll|rx|gE|xi|pm|Xi|ic|pr|Pr|in|ni|mp|mu|ac|Mu|or|ap|Gt|GT|ii);|&(Aacute|Agrave|Atilde|Ccedil|Eacute|Egrave|Iacute|Igrave|Ntilde|Oacute|Ograve|Oslash|Otilde|Uacute|Ugrave|Yacute|aacute|agrave|atilde|brvbar|ccedil|curren|divide|eacute|egrave|frac12|frac14|frac34|iacute|igrave|iquest|middot|ntilde|oacute|ograve|oslash|otilde|plusmn|uacute|ugrave|yacute|AElig|Acirc|Aring|Ecirc|Icirc|Ocirc|THORN|Ucirc|acirc|acute|aelig|aring|cedil|ecirc|icirc|iexcl|laquo|micro|ocirc|pound|raquo|szlig|thorn|times|ucirc|Auml|COPY|Euml|Iuml|Ouml|QUOT|Uuml|auml|cent|copy|euml|iuml|macr|nbsp|ordf|ordm|ouml|para|quot|sect|sup1|sup2|sup3|uuml|yuml|AMP|ETH|REG|amp|deg|eth|not|reg|shy|uml|yen|GT|LT|gt|lt)(?!;)([=a-zA-Z0-9]?)|&#([0-9]+)(;?)|&#[xX]([a-fA-F0-9]+)(;?)|&([0-9a-zA-Z]+)/g;
      var decodeMap = { "aacute": "á", "Aacute": "Á", "abreve": "ă", "Abreve": "Ă", "ac": "∾", "acd": "∿", "acE": "∾̳", "acirc": "â", "Acirc": "Â", "acute": "´", "acy": "а", "Acy": "А", "aelig": "æ", "AElig": "Æ", "af": "⁡", "afr": "𝔞", "Afr": "𝔄", "agrave": "à", "Agrave": "À", "alefsym": "ℵ", "aleph": "ℵ", "alpha": "α", "Alpha": "Α", "amacr": "ā", "Amacr": "Ā", "amalg": "⨿", "amp": "&", "AMP": "&", "and": "∧", "And": "⩓", "andand": "⩕", "andd": "⩜", "andslope": "⩘", "andv": "⩚", "ang": "∠", "ange": "⦤", "angle": "∠", "angmsd": "∡", "angmsdaa": "⦨", "angmsdab": "⦩", "angmsdac": "⦪", "angmsdad": "⦫", "angmsdae": "⦬", "angmsdaf": "⦭", "angmsdag": "⦮", "angmsdah": "⦯", "angrt": "∟", "angrtvb": "⊾", "angrtvbd": "⦝", "angsph": "∢", "angst": "Å", "angzarr": "⍼", "aogon": "ą", "Aogon": "Ą", "aopf": "𝕒", "Aopf": "𝔸", "ap": "≈", "apacir": "⩯", "ape": "≊", "apE": "⩰", "apid": "≋", "apos": "'", "ApplyFunction": "⁡", "approx": "≈", "approxeq": "≊", "aring": "å", "Aring": "Å", "ascr": "𝒶", "Ascr": "𝒜", "Assign": "≔", "ast": "*", "asymp": "≈", "asympeq": "≍", "atilde": "ã", "Atilde": "Ã", "auml": "ä", "Auml": "Ä", "awconint": "∳", "awint": "⨑", "backcong": "≌", "backepsilon": "϶", "backprime": "‵", "backsim": "∽", "backsimeq": "⋍", "Backslash": "∖", "Barv": "⫧", "barvee": "⊽", "barwed": "⌅", "Barwed": "⌆", "barwedge": "⌅", "bbrk": "⎵", "bbrktbrk": "⎶", "bcong": "≌", "bcy": "б", "Bcy": "Б", "bdquo": "„", "becaus": "∵", "because": "∵", "Because": "∵", "bemptyv": "⦰", "bepsi": "϶", "bernou": "ℬ", "Bernoullis": "ℬ", "beta": "β", "Beta": "Β", "beth": "ℶ", "between": "≬", "bfr": "𝔟", "Bfr": "𝔅", "bigcap": "⋂", "bigcirc": "◯", "bigcup": "⋃", "bigodot": "⨀", "bigoplus": "⨁", "bigotimes": "⨂", "bigsqcup": "⨆", "bigstar": "★", "bigtriangledown": "▽", "bigtriangleup": "△", "biguplus": "⨄", "bigvee": "⋁", "bigwedge": "⋀", "bkarow": "⤍", "blacklozenge": "⧫", "blacksquare": "▪", "blacktriangle": "▴", "blacktriangledown": "▾", "blacktriangleleft": "◂", "blacktriangleright": "▸", "blank": "␣", "blk12": "▒", "blk14": "░", "blk34": "▓", "block": "█", "bne": "=⃥", "bnequiv": "≡⃥", "bnot": "⌐", "bNot": "⫭", "bopf": "𝕓", "Bopf": "𝔹", "bot": "⊥", "bottom": "⊥", "bowtie": "⋈", "boxbox": "⧉", "boxdl": "┐", "boxdL": "╕", "boxDl": "╖", "boxDL": "╗", "boxdr": "┌", "boxdR": "╒", "boxDr": "╓", "boxDR": "╔", "boxh": "─", "boxH": "═", "boxhd": "┬", "boxhD": "╥", "boxHd": "╤", "boxHD": "╦", "boxhu": "┴", "boxhU": "╨", "boxHu": "╧", "boxHU": "╩", "boxminus": "⊟", "boxplus": "⊞", "boxtimes": "⊠", "boxul": "┘", "boxuL": "╛", "boxUl": "╜", "boxUL": "╝", "boxur": "└", "boxuR": "╘", "boxUr": "╙", "boxUR": "╚", "boxv": "│", "boxV": "║", "boxvh": "┼", "boxvH": "╪", "boxVh": "╫", "boxVH": "╬", "boxvl": "┤", "boxvL": "╡", "boxVl": "╢", "boxVL": "╣", "boxvr": "├", "boxvR": "╞", "boxVr": "╟", "boxVR": "╠", "bprime": "‵", "breve": "˘", "Breve": "˘", "brvbar": "¦", "bscr": "𝒷", "Bscr": "ℬ", "bsemi": "⁏", "bsim": "∽", "bsime": "⋍", "bsol": "\\", "bsolb": "⧅", "bsolhsub": "⟈", "bull": "•", "bullet": "•", "bump": "≎", "bumpe": "≏", "bumpE": "⪮", "bumpeq": "≏", "Bumpeq": "≎", "cacute": "ć", "Cacute": "Ć", "cap": "∩", "Cap": "⋒", "capand": "⩄", "capbrcup": "⩉", "capcap": "⩋", "capcup": "⩇", "capdot": "⩀", "CapitalDifferentialD": "ⅅ", "caps": "∩︀", "caret": "⁁", "caron": "ˇ", "Cayleys": "ℭ", "ccaps": "⩍", "ccaron": "č", "Ccaron": "Č", "ccedil": "ç", "Ccedil": "Ç", "ccirc": "ĉ", "Ccirc": "Ĉ", "Cconint": "∰", "ccups": "⩌", "ccupssm": "⩐", "cdot": "ċ", "Cdot": "Ċ", "cedil": "¸", "Cedilla": "¸", "cemptyv": "⦲", "cent": "¢", "centerdot": "·", "CenterDot": "·", "cfr": "𝔠", "Cfr": "ℭ", "chcy": "ч", "CHcy": "Ч", "check": "✓", "checkmark": "✓", "chi": "χ", "Chi": "Χ", "cir": "○", "circ": "ˆ", "circeq": "≗", "circlearrowleft": "↺", "circlearrowright": "↻", "circledast": "⊛", "circledcirc": "⊚", "circleddash": "⊝", "CircleDot": "⊙", "circledR": "®", "circledS": "Ⓢ", "CircleMinus": "⊖", "CirclePlus": "⊕", "CircleTimes": "⊗", "cire": "≗", "cirE": "⧃", "cirfnint": "⨐", "cirmid": "⫯", "cirscir": "⧂", "ClockwiseContourIntegral": "∲", "CloseCurlyDoubleQuote": "”", "CloseCurlyQuote": "’", "clubs": "♣", "clubsuit": "♣", "colon": ":", "Colon": "∷", "colone": "≔", "Colone": "⩴", "coloneq": "≔", "comma": ",", "commat": "@", "comp": "∁", "compfn": "∘", "complement": "∁", "complexes": "ℂ", "cong": "≅", "congdot": "⩭", "Congruent": "≡", "conint": "∮", "Conint": "∯", "ContourIntegral": "∮", "copf": "𝕔", "Copf": "ℂ", "coprod": "∐", "Coproduct": "∐", "copy": "©", "COPY": "©", "copysr": "℗", "CounterClockwiseContourIntegral": "∳", "crarr": "↵", "cross": "✗", "Cross": "⨯", "cscr": "𝒸", "Cscr": "𝒞", "csub": "⫏", "csube": "⫑", "csup": "⫐", "csupe": "⫒", "ctdot": "⋯", "cudarrl": "⤸", "cudarrr": "⤵", "cuepr": "⋞", "cuesc": "⋟", "cularr": "↶", "cularrp": "⤽", "cup": "∪", "Cup": "⋓", "cupbrcap": "⩈", "cupcap": "⩆", "CupCap": "≍", "cupcup": "⩊", "cupdot": "⊍", "cupor": "⩅", "cups": "∪︀", "curarr": "↷", "curarrm": "⤼", "curlyeqprec": "⋞", "curlyeqsucc": "⋟", "curlyvee": "⋎", "curlywedge": "⋏", "curren": "¤", "curvearrowleft": "↶", "curvearrowright": "↷", "cuvee": "⋎", "cuwed": "⋏", "cwconint": "∲", "cwint": "∱", "cylcty": "⌭", "dagger": "†", "Dagger": "‡", "daleth": "ℸ", "darr": "↓", "dArr": "⇓", "Darr": "↡", "dash": "‐", "dashv": "⊣", "Dashv": "⫤", "dbkarow": "⤏", "dblac": "˝", "dcaron": "ď", "Dcaron": "Ď", "dcy": "д", "Dcy": "Д", "dd": "ⅆ", "DD": "ⅅ", "ddagger": "‡", "ddarr": "⇊", "DDotrahd": "⤑", "ddotseq": "⩷", "deg": "°", "Del": "∇", "delta": "δ", "Delta": "Δ", "demptyv": "⦱", "dfisht": "⥿", "dfr": "𝔡", "Dfr": "𝔇", "dHar": "⥥", "dharl": "⇃", "dharr": "⇂", "DiacriticalAcute": "´", "DiacriticalDot": "˙", "DiacriticalDoubleAcute": "˝", "DiacriticalGrave": "`", "DiacriticalTilde": "˜", "diam": "⋄", "diamond": "⋄", "Diamond": "⋄", "diamondsuit": "♦", "diams": "♦", "die": "¨", "DifferentialD": "ⅆ", "digamma": "ϝ", "disin": "⋲", "div": "÷", "divide": "÷", "divideontimes": "⋇", "divonx": "⋇", "djcy": "ђ", "DJcy": "Ђ", "dlcorn": "⌞", "dlcrop": "⌍", "dollar": "$", "dopf": "𝕕", "Dopf": "𝔻", "dot": "˙", "Dot": "¨", "DotDot": "⃜", "doteq": "≐", "doteqdot": "≑", "DotEqual": "≐", "dotminus": "∸", "dotplus": "∔", "dotsquare": "⊡", "doublebarwedge": "⌆", "DoubleContourIntegral": "∯", "DoubleDot": "¨", "DoubleDownArrow": "⇓", "DoubleLeftArrow": "⇐", "DoubleLeftRightArrow": "⇔", "DoubleLeftTee": "⫤", "DoubleLongLeftArrow": "⟸", "DoubleLongLeftRightArrow": "⟺", "DoubleLongRightArrow": "⟹", "DoubleRightArrow": "⇒", "DoubleRightTee": "⊨", "DoubleUpArrow": "⇑", "DoubleUpDownArrow": "⇕", "DoubleVerticalBar": "∥", "downarrow": "↓", "Downarrow": "⇓", "DownArrow": "↓", "DownArrowBar": "⤓", "DownArrowUpArrow": "⇵", "DownBreve": "̑", "downdownarrows": "⇊", "downharpoonleft": "⇃", "downharpoonright": "⇂", "DownLeftRightVector": "⥐", "DownLeftTeeVector": "⥞", "DownLeftVector": "↽", "DownLeftVectorBar": "⥖", "DownRightTeeVector": "⥟", "DownRightVector": "⇁", "DownRightVectorBar": "⥗", "DownTee": "⊤", "DownTeeArrow": "↧", "drbkarow": "⤐", "drcorn": "⌟", "drcrop": "⌌", "dscr": "𝒹", "Dscr": "𝒟", "dscy": "ѕ", "DScy": "Ѕ", "dsol": "⧶", "dstrok": "đ", "Dstrok": "Đ", "dtdot": "⋱", "dtri": "▿", "dtrif": "▾", "duarr": "⇵", "duhar": "⥯", "dwangle": "⦦", "dzcy": "џ", "DZcy": "Џ", "dzigrarr": "⟿", "eacute": "é", "Eacute": "É", "easter": "⩮", "ecaron": "ě", "Ecaron": "Ě", "ecir": "≖", "ecirc": "ê", "Ecirc": "Ê", "ecolon": "≕", "ecy": "э", "Ecy": "Э", "eDDot": "⩷", "edot": "ė", "eDot": "≑", "Edot": "Ė", "ee": "ⅇ", "efDot": "≒", "efr": "𝔢", "Efr": "𝔈", "eg": "⪚", "egrave": "è", "Egrave": "È", "egs": "⪖", "egsdot": "⪘", "el": "⪙", "Element": "∈", "elinters": "⏧", "ell": "ℓ", "els": "⪕", "elsdot": "⪗", "emacr": "ē", "Emacr": "Ē", "empty": "∅", "emptyset": "∅", "EmptySmallSquare": "◻", "emptyv": "∅", "EmptyVerySmallSquare": "▫", "emsp": " ", "emsp13": " ", "emsp14": " ", "eng": "ŋ", "ENG": "Ŋ", "ensp": " ", "eogon": "ę", "Eogon": "Ę", "eopf": "𝕖", "Eopf": "𝔼", "epar": "⋕", "eparsl": "⧣", "eplus": "⩱", "epsi": "ε", "epsilon": "ε", "Epsilon": "Ε", "epsiv": "ϵ", "eqcirc": "≖", "eqcolon": "≕", "eqsim": "≂", "eqslantgtr": "⪖", "eqslantless": "⪕", "Equal": "⩵", "equals": "=", "EqualTilde": "≂", "equest": "≟", "Equilibrium": "⇌", "equiv": "≡", "equivDD": "⩸", "eqvparsl": "⧥", "erarr": "⥱", "erDot": "≓", "escr": "ℯ", "Escr": "ℰ", "esdot": "≐", "esim": "≂", "Esim": "⩳", "eta": "η", "Eta": "Η", "eth": "ð", "ETH": "Ð", "euml": "ë", "Euml": "Ë", "euro": "€", "excl": "!", "exist": "∃", "Exists": "∃", "expectation": "ℰ", "exponentiale": "ⅇ", "ExponentialE": "ⅇ", "fallingdotseq": "≒", "fcy": "ф", "Fcy": "Ф", "female": "♀", "ffilig": "ﬃ", "fflig": "ﬀ", "ffllig": "ﬄ", "ffr": "𝔣", "Ffr": "𝔉", "filig": "ﬁ", "FilledSmallSquare": "◼", "FilledVerySmallSquare": "▪", "fjlig": "fj", "flat": "♭", "fllig": "ﬂ", "fltns": "▱", "fnof": "ƒ", "fopf": "𝕗", "Fopf": "𝔽", "forall": "∀", "ForAll": "∀", "fork": "⋔", "forkv": "⫙", "Fouriertrf": "ℱ", "fpartint": "⨍", "frac12": "½", "frac13": "⅓", "frac14": "¼", "frac15": "⅕", "frac16": "⅙", "frac18": "⅛", "frac23": "⅔", "frac25": "⅖", "frac34": "¾", "frac35": "⅗", "frac38": "⅜", "frac45": "⅘", "frac56": "⅚", "frac58": "⅝", "frac78": "⅞", "frasl": "⁄", "frown": "⌢", "fscr": "𝒻", "Fscr": "ℱ", "gacute": "ǵ", "gamma": "γ", "Gamma": "Γ", "gammad": "ϝ", "Gammad": "Ϝ", "gap": "⪆", "gbreve": "ğ", "Gbreve": "Ğ", "Gcedil": "Ģ", "gcirc": "ĝ", "Gcirc": "Ĝ", "gcy": "г", "Gcy": "Г", "gdot": "ġ", "Gdot": "Ġ", "ge": "≥", "gE": "≧", "gel": "⋛", "gEl": "⪌", "geq": "≥", "geqq": "≧", "geqslant": "⩾", "ges": "⩾", "gescc": "⪩", "gesdot": "⪀", "gesdoto": "⪂", "gesdotol": "⪄", "gesl": "⋛︀", "gesles": "⪔", "gfr": "𝔤", "Gfr": "𝔊", "gg": "≫", "Gg": "⋙", "ggg": "⋙", "gimel": "ℷ", "gjcy": "ѓ", "GJcy": "Ѓ", "gl": "≷", "gla": "⪥", "glE": "⪒", "glj": "⪤", "gnap": "⪊", "gnapprox": "⪊", "gne": "⪈", "gnE": "≩", "gneq": "⪈", "gneqq": "≩", "gnsim": "⋧", "gopf": "𝕘", "Gopf": "𝔾", "grave": "`", "GreaterEqual": "≥", "GreaterEqualLess": "⋛", "GreaterFullEqual": "≧", "GreaterGreater": "⪢", "GreaterLess": "≷", "GreaterSlantEqual": "⩾", "GreaterTilde": "≳", "gscr": "ℊ", "Gscr": "𝒢", "gsim": "≳", "gsime": "⪎", "gsiml": "⪐", "gt": ">", "Gt": "≫", "GT": ">", "gtcc": "⪧", "gtcir": "⩺", "gtdot": "⋗", "gtlPar": "⦕", "gtquest": "⩼", "gtrapprox": "⪆", "gtrarr": "⥸", "gtrdot": "⋗", "gtreqless": "⋛", "gtreqqless": "⪌", "gtrless": "≷", "gtrsim": "≳", "gvertneqq": "≩︀", "gvnE": "≩︀", "Hacek": "ˇ", "hairsp": " ", "half": "½", "hamilt": "ℋ", "hardcy": "ъ", "HARDcy": "Ъ", "harr": "↔", "hArr": "⇔", "harrcir": "⥈", "harrw": "↭", "Hat": "^", "hbar": "ℏ", "hcirc": "ĥ", "Hcirc": "Ĥ", "hearts": "♥", "heartsuit": "♥", "hellip": "…", "hercon": "⊹", "hfr": "𝔥", "Hfr": "ℌ", "HilbertSpace": "ℋ", "hksearow": "⤥", "hkswarow": "⤦", "hoarr": "⇿", "homtht": "∻", "hookleftarrow": "↩", "hookrightarrow": "↪", "hopf": "𝕙", "Hopf": "ℍ", "horbar": "―", "HorizontalLine": "─", "hscr": "𝒽", "Hscr": "ℋ", "hslash": "ℏ", "hstrok": "ħ", "Hstrok": "Ħ", "HumpDownHump": "≎", "HumpEqual": "≏", "hybull": "⁃", "hyphen": "‐", "iacute": "í", "Iacute": "Í", "ic": "⁣", "icirc": "î", "Icirc": "Î", "icy": "и", "Icy": "И", "Idot": "İ", "iecy": "е", "IEcy": "Е", "iexcl": "¡", "iff": "⇔", "ifr": "𝔦", "Ifr": "ℑ", "igrave": "ì", "Igrave": "Ì", "ii": "ⅈ", "iiiint": "⨌", "iiint": "∭", "iinfin": "⧜", "iiota": "℩", "ijlig": "ĳ", "IJlig": "Ĳ", "Im": "ℑ", "imacr": "ī", "Imacr": "Ī", "image": "ℑ", "ImaginaryI": "ⅈ", "imagline": "ℐ", "imagpart": "ℑ", "imath": "ı", "imof": "⊷", "imped": "Ƶ", "Implies": "⇒", "in": "∈", "incare": "℅", "infin": "∞", "infintie": "⧝", "inodot": "ı", "int": "∫", "Int": "∬", "intcal": "⊺", "integers": "ℤ", "Integral": "∫", "intercal": "⊺", "Intersection": "⋂", "intlarhk": "⨗", "intprod": "⨼", "InvisibleComma": "⁣", "InvisibleTimes": "⁢", "iocy": "ё", "IOcy": "Ё", "iogon": "į", "Iogon": "Į", "iopf": "𝕚", "Iopf": "𝕀", "iota": "ι", "Iota": "Ι", "iprod": "⨼", "iquest": "¿", "iscr": "𝒾", "Iscr": "ℐ", "isin": "∈", "isindot": "⋵", "isinE": "⋹", "isins": "⋴", "isinsv": "⋳", "isinv": "∈", "it": "⁢", "itilde": "ĩ", "Itilde": "Ĩ", "iukcy": "і", "Iukcy": "І", "iuml": "ï", "Iuml": "Ï", "jcirc": "ĵ", "Jcirc": "Ĵ", "jcy": "й", "Jcy": "Й", "jfr": "𝔧", "Jfr": "𝔍", "jmath": "ȷ", "jopf": "𝕛", "Jopf": "𝕁", "jscr": "𝒿", "Jscr": "𝒥", "jsercy": "ј", "Jsercy": "Ј", "jukcy": "є", "Jukcy": "Є", "kappa": "κ", "Kappa": "Κ", "kappav": "ϰ", "kcedil": "ķ", "Kcedil": "Ķ", "kcy": "к", "Kcy": "К", "kfr": "𝔨", "Kfr": "𝔎", "kgreen": "ĸ", "khcy": "х", "KHcy": "Х", "kjcy": "ќ", "KJcy": "Ќ", "kopf": "𝕜", "Kopf": "𝕂", "kscr": "𝓀", "Kscr": "𝒦", "lAarr": "⇚", "lacute": "ĺ", "Lacute": "Ĺ", "laemptyv": "⦴", "lagran": "ℒ", "lambda": "λ", "Lambda": "Λ", "lang": "⟨", "Lang": "⟪", "langd": "⦑", "langle": "⟨", "lap": "⪅", "Laplacetrf": "ℒ", "laquo": "«", "larr": "←", "lArr": "⇐", "Larr": "↞", "larrb": "⇤", "larrbfs": "⤟", "larrfs": "⤝", "larrhk": "↩", "larrlp": "↫", "larrpl": "⤹", "larrsim": "⥳", "larrtl": "↢", "lat": "⪫", "latail": "⤙", "lAtail": "⤛", "late": "⪭", "lates": "⪭︀", "lbarr": "⤌", "lBarr": "⤎", "lbbrk": "❲", "lbrace": "{", "lbrack": "[", "lbrke": "⦋", "lbrksld": "⦏", "lbrkslu": "⦍", "lcaron": "ľ", "Lcaron": "Ľ", "lcedil": "ļ", "Lcedil": "Ļ", "lceil": "⌈", "lcub": "{", "lcy": "л", "Lcy": "Л", "ldca": "⤶", "ldquo": "“", "ldquor": "„", "ldrdhar": "⥧", "ldrushar": "⥋", "ldsh": "↲", "le": "≤", "lE": "≦", "LeftAngleBracket": "⟨", "leftarrow": "←", "Leftarrow": "⇐", "LeftArrow": "←", "LeftArrowBar": "⇤", "LeftArrowRightArrow": "⇆", "leftarrowtail": "↢", "LeftCeiling": "⌈", "LeftDoubleBracket": "⟦", "LeftDownTeeVector": "⥡", "LeftDownVector": "⇃", "LeftDownVectorBar": "⥙", "LeftFloor": "⌊", "leftharpoondown": "↽", "leftharpoonup": "↼", "leftleftarrows": "⇇", "leftrightarrow": "↔", "Leftrightarrow": "⇔", "LeftRightArrow": "↔", "leftrightarrows": "⇆", "leftrightharpoons": "⇋", "leftrightsquigarrow": "↭", "LeftRightVector": "⥎", "LeftTee": "⊣", "LeftTeeArrow": "↤", "LeftTeeVector": "⥚", "leftthreetimes": "⋋", "LeftTriangle": "⊲", "LeftTriangleBar": "⧏", "LeftTriangleEqual": "⊴", "LeftUpDownVector": "⥑", "LeftUpTeeVector": "⥠", "LeftUpVector": "↿", "LeftUpVectorBar": "⥘", "LeftVector": "↼", "LeftVectorBar": "⥒", "leg": "⋚", "lEg": "⪋", "leq": "≤", "leqq": "≦", "leqslant": "⩽", "les": "⩽", "lescc": "⪨", "lesdot": "⩿", "lesdoto": "⪁", "lesdotor": "⪃", "lesg": "⋚︀", "lesges": "⪓", "lessapprox": "⪅", "lessdot": "⋖", "lesseqgtr": "⋚", "lesseqqgtr": "⪋", "LessEqualGreater": "⋚", "LessFullEqual": "≦", "LessGreater": "≶", "lessgtr": "≶", "LessLess": "⪡", "lesssim": "≲", "LessSlantEqual": "⩽", "LessTilde": "≲", "lfisht": "⥼", "lfloor": "⌊", "lfr": "𝔩", "Lfr": "𝔏", "lg": "≶", "lgE": "⪑", "lHar": "⥢", "lhard": "↽", "lharu": "↼", "lharul": "⥪", "lhblk": "▄", "ljcy": "љ", "LJcy": "Љ", "ll": "≪", "Ll": "⋘", "llarr": "⇇", "llcorner": "⌞", "Lleftarrow": "⇚", "llhard": "⥫", "lltri": "◺", "lmidot": "ŀ", "Lmidot": "Ŀ", "lmoust": "⎰", "lmoustache": "⎰", "lnap": "⪉", "lnapprox": "⪉", "lne": "⪇", "lnE": "≨", "lneq": "⪇", "lneqq": "≨", "lnsim": "⋦", "loang": "⟬", "loarr": "⇽", "lobrk": "⟦", "longleftarrow": "⟵", "Longleftarrow": "⟸", "LongLeftArrow": "⟵", "longleftrightarrow": "⟷", "Longleftrightarrow": "⟺", "LongLeftRightArrow": "⟷", "longmapsto": "⟼", "longrightarrow": "⟶", "Longrightarrow": "⟹", "LongRightArrow": "⟶", "looparrowleft": "↫", "looparrowright": "↬", "lopar": "⦅", "lopf": "𝕝", "Lopf": "𝕃", "loplus": "⨭", "lotimes": "⨴", "lowast": "∗", "lowbar": "_", "LowerLeftArrow": "↙", "LowerRightArrow": "↘", "loz": "◊", "lozenge": "◊", "lozf": "⧫", "lpar": "(", "lparlt": "⦓", "lrarr": "⇆", "lrcorner": "⌟", "lrhar": "⇋", "lrhard": "⥭", "lrm": "‎", "lrtri": "⊿", "lsaquo": "‹", "lscr": "𝓁", "Lscr": "ℒ", "lsh": "↰", "Lsh": "↰", "lsim": "≲", "lsime": "⪍", "lsimg": "⪏", "lsqb": "[", "lsquo": "‘", "lsquor": "‚", "lstrok": "ł", "Lstrok": "Ł", "lt": "<", "Lt": "≪", "LT": "<", "ltcc": "⪦", "ltcir": "⩹", "ltdot": "⋖", "lthree": "⋋", "ltimes": "⋉", "ltlarr": "⥶", "ltquest": "⩻", "ltri": "◃", "ltrie": "⊴", "ltrif": "◂", "ltrPar": "⦖", "lurdshar": "⥊", "luruhar": "⥦", "lvertneqq": "≨︀", "lvnE": "≨︀", "macr": "¯", "male": "♂", "malt": "✠", "maltese": "✠", "map": "↦", "Map": "⤅", "mapsto": "↦", "mapstodown": "↧", "mapstoleft": "↤", "mapstoup": "↥", "marker": "▮", "mcomma": "⨩", "mcy": "м", "Mcy": "М", "mdash": "—", "mDDot": "∺", "measuredangle": "∡", "MediumSpace": " ", "Mellintrf": "ℳ", "mfr": "𝔪", "Mfr": "𝔐", "mho": "℧", "micro": "µ", "mid": "∣", "midast": "*", "midcir": "⫰", "middot": "·", "minus": "−", "minusb": "⊟", "minusd": "∸", "minusdu": "⨪", "MinusPlus": "∓", "mlcp": "⫛", "mldr": "…", "mnplus": "∓", "models": "⊧", "mopf": "𝕞", "Mopf": "𝕄", "mp": "∓", "mscr": "𝓂", "Mscr": "ℳ", "mstpos": "∾", "mu": "μ", "Mu": "Μ", "multimap": "⊸", "mumap": "⊸", "nabla": "∇", "nacute": "ń", "Nacute": "Ń", "nang": "∠⃒", "nap": "≉", "napE": "⩰̸", "napid": "≋̸", "napos": "ŉ", "napprox": "≉", "natur": "♮", "natural": "♮", "naturals": "ℕ", "nbsp": " ", "nbump": "≎̸", "nbumpe": "≏̸", "ncap": "⩃", "ncaron": "ň", "Ncaron": "Ň", "ncedil": "ņ", "Ncedil": "Ņ", "ncong": "≇", "ncongdot": "⩭̸", "ncup": "⩂", "ncy": "н", "Ncy": "Н", "ndash": "–", "ne": "≠", "nearhk": "⤤", "nearr": "↗", "neArr": "⇗", "nearrow": "↗", "nedot": "≐̸", "NegativeMediumSpace": "​", "NegativeThickSpace": "​", "NegativeThinSpace": "​", "NegativeVeryThinSpace": "​", "nequiv": "≢", "nesear": "⤨", "nesim": "≂̸", "NestedGreaterGreater": "≫", "NestedLessLess": "≪", "NewLine": "\n", "nexist": "∄", "nexists": "∄", "nfr": "𝔫", "Nfr": "𝔑", "nge": "≱", "ngE": "≧̸", "ngeq": "≱", "ngeqq": "≧̸", "ngeqslant": "⩾̸", "nges": "⩾̸", "nGg": "⋙̸", "ngsim": "≵", "ngt": "≯", "nGt": "≫⃒", "ngtr": "≯", "nGtv": "≫̸", "nharr": "↮", "nhArr": "⇎", "nhpar": "⫲", "ni": "∋", "nis": "⋼", "nisd": "⋺", "niv": "∋", "njcy": "њ", "NJcy": "Њ", "nlarr": "↚", "nlArr": "⇍", "nldr": "‥", "nle": "≰", "nlE": "≦̸", "nleftarrow": "↚", "nLeftarrow": "⇍", "nleftrightarrow": "↮", "nLeftrightarrow": "⇎", "nleq": "≰", "nleqq": "≦̸", "nleqslant": "⩽̸", "nles": "⩽̸", "nless": "≮", "nLl": "⋘̸", "nlsim": "≴", "nlt": "≮", "nLt": "≪⃒", "nltri": "⋪", "nltrie": "⋬", "nLtv": "≪̸", "nmid": "∤", "NoBreak": "⁠", "NonBreakingSpace": " ", "nopf": "𝕟", "Nopf": "ℕ", "not": "¬", "Not": "⫬", "NotCongruent": "≢", "NotCupCap": "≭", "NotDoubleVerticalBar": "∦", "NotElement": "∉", "NotEqual": "≠", "NotEqualTilde": "≂̸", "NotExists": "∄", "NotGreater": "≯", "NotGreaterEqual": "≱", "NotGreaterFullEqual": "≧̸", "NotGreaterGreater": "≫̸", "NotGreaterLess": "≹", "NotGreaterSlantEqual": "⩾̸", "NotGreaterTilde": "≵", "NotHumpDownHump": "≎̸", "NotHumpEqual": "≏̸", "notin": "∉", "notindot": "⋵̸", "notinE": "⋹̸", "notinva": "∉", "notinvb": "⋷", "notinvc": "⋶", "NotLeftTriangle": "⋪", "NotLeftTriangleBar": "⧏̸", "NotLeftTriangleEqual": "⋬", "NotLess": "≮", "NotLessEqual": "≰", "NotLessGreater": "≸", "NotLessLess": "≪̸", "NotLessSlantEqual": "⩽̸", "NotLessTilde": "≴", "NotNestedGreaterGreater": "⪢̸", "NotNestedLessLess": "⪡̸", "notni": "∌", "notniva": "∌", "notnivb": "⋾", "notnivc": "⋽", "NotPrecedes": "⊀", "NotPrecedesEqual": "⪯̸", "NotPrecedesSlantEqual": "⋠", "NotReverseElement": "∌", "NotRightTriangle": "⋫", "NotRightTriangleBar": "⧐̸", "NotRightTriangleEqual": "⋭", "NotSquareSubset": "⊏̸", "NotSquareSubsetEqual": "⋢", "NotSquareSuperset": "⊐̸", "NotSquareSupersetEqual": "⋣", "NotSubset": "⊂⃒", "NotSubsetEqual": "⊈", "NotSucceeds": "⊁", "NotSucceedsEqual": "⪰̸", "NotSucceedsSlantEqual": "⋡", "NotSucceedsTilde": "≿̸", "NotSuperset": "⊃⃒", "NotSupersetEqual": "⊉", "NotTilde": "≁", "NotTildeEqual": "≄", "NotTildeFullEqual": "≇", "NotTildeTilde": "≉", "NotVerticalBar": "∤", "npar": "∦", "nparallel": "∦", "nparsl": "⫽⃥", "npart": "∂̸", "npolint": "⨔", "npr": "⊀", "nprcue": "⋠", "npre": "⪯̸", "nprec": "⊀", "npreceq": "⪯̸", "nrarr": "↛", "nrArr": "⇏", "nrarrc": "⤳̸", "nrarrw": "↝̸", "nrightarrow": "↛", "nRightarrow": "⇏", "nrtri": "⋫", "nrtrie": "⋭", "nsc": "⊁", "nsccue": "⋡", "nsce": "⪰̸", "nscr": "𝓃", "Nscr": "𝒩", "nshortmid": "∤", "nshortparallel": "∦", "nsim": "≁", "nsime": "≄", "nsimeq": "≄", "nsmid": "∤", "nspar": "∦", "nsqsube": "⋢", "nsqsupe": "⋣", "nsub": "⊄", "nsube": "⊈", "nsubE": "⫅̸", "nsubset": "⊂⃒", "nsubseteq": "⊈", "nsubseteqq": "⫅̸", "nsucc": "⊁", "nsucceq": "⪰̸", "nsup": "⊅", "nsupe": "⊉", "nsupE": "⫆̸", "nsupset": "⊃⃒", "nsupseteq": "⊉", "nsupseteqq": "⫆̸", "ntgl": "≹", "ntilde": "ñ", "Ntilde": "Ñ", "ntlg": "≸", "ntriangleleft": "⋪", "ntrianglelefteq": "⋬", "ntriangleright": "⋫", "ntrianglerighteq": "⋭", "nu": "ν", "Nu": "Ν", "num": "#", "numero": "№", "numsp": " ", "nvap": "≍⃒", "nvdash": "⊬", "nvDash": "⊭", "nVdash": "⊮", "nVDash": "⊯", "nvge": "≥⃒", "nvgt": ">⃒", "nvHarr": "⤄", "nvinfin": "⧞", "nvlArr": "⤂", "nvle": "≤⃒", "nvlt": "<⃒", "nvltrie": "⊴⃒", "nvrArr": "⤃", "nvrtrie": "⊵⃒", "nvsim": "∼⃒", "nwarhk": "⤣", "nwarr": "↖", "nwArr": "⇖", "nwarrow": "↖", "nwnear": "⤧", "oacute": "ó", "Oacute": "Ó", "oast": "⊛", "ocir": "⊚", "ocirc": "ô", "Ocirc": "Ô", "ocy": "о", "Ocy": "О", "odash": "⊝", "odblac": "ő", "Odblac": "Ő", "odiv": "⨸", "odot": "⊙", "odsold": "⦼", "oelig": "œ", "OElig": "Œ", "ofcir": "⦿", "ofr": "𝔬", "Ofr": "𝔒", "ogon": "˛", "ograve": "ò", "Ograve": "Ò", "ogt": "⧁", "ohbar": "⦵", "ohm": "Ω", "oint": "∮", "olarr": "↺", "olcir": "⦾", "olcross": "⦻", "oline": "‾", "olt": "⧀", "omacr": "ō", "Omacr": "Ō", "omega": "ω", "Omega": "Ω", "omicron": "ο", "Omicron": "Ο", "omid": "⦶", "ominus": "⊖", "oopf": "𝕠", "Oopf": "𝕆", "opar": "⦷", "OpenCurlyDoubleQuote": "“", "OpenCurlyQuote": "‘", "operp": "⦹", "oplus": "⊕", "or": "∨", "Or": "⩔", "orarr": "↻", "ord": "⩝", "order": "ℴ", "orderof": "ℴ", "ordf": "ª", "ordm": "º", "origof": "⊶", "oror": "⩖", "orslope": "⩗", "orv": "⩛", "oS": "Ⓢ", "oscr": "ℴ", "Oscr": "𝒪", "oslash": "ø", "Oslash": "Ø", "osol": "⊘", "otilde": "õ", "Otilde": "Õ", "otimes": "⊗", "Otimes": "⨷", "otimesas": "⨶", "ouml": "ö", "Ouml": "Ö", "ovbar": "⌽", "OverBar": "‾", "OverBrace": "⏞", "OverBracket": "⎴", "OverParenthesis": "⏜", "par": "∥", "para": "¶", "parallel": "∥", "parsim": "⫳", "parsl": "⫽", "part": "∂", "PartialD": "∂", "pcy": "п", "Pcy": "П", "percnt": "%", "period": ".", "permil": "‰", "perp": "⊥", "pertenk": "‱", "pfr": "𝔭", "Pfr": "𝔓", "phi": "φ", "Phi": "Φ", "phiv": "ϕ", "phmmat": "ℳ", "phone": "☎", "pi": "π", "Pi": "Π", "pitchfork": "⋔", "piv": "ϖ", "planck": "ℏ", "planckh": "ℎ", "plankv": "ℏ", "plus": "+", "plusacir": "⨣", "plusb": "⊞", "pluscir": "⨢", "plusdo": "∔", "plusdu": "⨥", "pluse": "⩲", "PlusMinus": "±", "plusmn": "±", "plussim": "⨦", "plustwo": "⨧", "pm": "±", "Poincareplane": "ℌ", "pointint": "⨕", "popf": "𝕡", "Popf": "ℙ", "pound": "£", "pr": "≺", "Pr": "⪻", "prap": "⪷", "prcue": "≼", "pre": "⪯", "prE": "⪳", "prec": "≺", "precapprox": "⪷", "preccurlyeq": "≼", "Precedes": "≺", "PrecedesEqual": "⪯", "PrecedesSlantEqual": "≼", "PrecedesTilde": "≾", "preceq": "⪯", "precnapprox": "⪹", "precneqq": "⪵", "precnsim": "⋨", "precsim": "≾", "prime": "′", "Prime": "″", "primes": "ℙ", "prnap": "⪹", "prnE": "⪵", "prnsim": "⋨", "prod": "∏", "Product": "∏", "profalar": "⌮", "profline": "⌒", "profsurf": "⌓", "prop": "∝", "Proportion": "∷", "Proportional": "∝", "propto": "∝", "prsim": "≾", "prurel": "⊰", "pscr": "𝓅", "Pscr": "𝒫", "psi": "ψ", "Psi": "Ψ", "puncsp": " ", "qfr": "𝔮", "Qfr": "𝔔", "qint": "⨌", "qopf": "𝕢", "Qopf": "ℚ", "qprime": "⁗", "qscr": "𝓆", "Qscr": "𝒬", "quaternions": "ℍ", "quatint": "⨖", "quest": "?", "questeq": "≟", "quot": '"', "QUOT": '"', "rAarr": "⇛", "race": "∽̱", "racute": "ŕ", "Racute": "Ŕ", "radic": "√", "raemptyv": "⦳", "rang": "⟩", "Rang": "⟫", "rangd": "⦒", "range": "⦥", "rangle": "⟩", "raquo": "»", "rarr": "→", "rArr": "⇒", "Rarr": "↠", "rarrap": "⥵", "rarrb": "⇥", "rarrbfs": "⤠", "rarrc": "⤳", "rarrfs": "⤞", "rarrhk": "↪", "rarrlp": "↬", "rarrpl": "⥅", "rarrsim": "⥴", "rarrtl": "↣", "Rarrtl": "⤖", "rarrw": "↝", "ratail": "⤚", "rAtail": "⤜", "ratio": "∶", "rationals": "ℚ", "rbarr": "⤍", "rBarr": "⤏", "RBarr": "⤐", "rbbrk": "❳", "rbrace": "}", "rbrack": "]", "rbrke": "⦌", "rbrksld": "⦎", "rbrkslu": "⦐", "rcaron": "ř", "Rcaron": "Ř", "rcedil": "ŗ", "Rcedil": "Ŗ", "rceil": "⌉", "rcub": "}", "rcy": "р", "Rcy": "Р", "rdca": "⤷", "rdldhar": "⥩", "rdquo": "”", "rdquor": "”", "rdsh": "↳", "Re": "ℜ", "real": "ℜ", "realine": "ℛ", "realpart": "ℜ", "reals": "ℝ", "rect": "▭", "reg": "®", "REG": "®", "ReverseElement": "∋", "ReverseEquilibrium": "⇋", "ReverseUpEquilibrium": "⥯", "rfisht": "⥽", "rfloor": "⌋", "rfr": "𝔯", "Rfr": "ℜ", "rHar": "⥤", "rhard": "⇁", "rharu": "⇀", "rharul": "⥬", "rho": "ρ", "Rho": "Ρ", "rhov": "ϱ", "RightAngleBracket": "⟩", "rightarrow": "→", "Rightarrow": "⇒", "RightArrow": "→", "RightArrowBar": "⇥", "RightArrowLeftArrow": "⇄", "rightarrowtail": "↣", "RightCeiling": "⌉", "RightDoubleBracket": "⟧", "RightDownTeeVector": "⥝", "RightDownVector": "⇂", "RightDownVectorBar": "⥕", "RightFloor": "⌋", "rightharpoondown": "⇁", "rightharpoonup": "⇀", "rightleftarrows": "⇄", "rightleftharpoons": "⇌", "rightrightarrows": "⇉", "rightsquigarrow": "↝", "RightTee": "⊢", "RightTeeArrow": "↦", "RightTeeVector": "⥛", "rightthreetimes": "⋌", "RightTriangle": "⊳", "RightTriangleBar": "⧐", "RightTriangleEqual": "⊵", "RightUpDownVector": "⥏", "RightUpTeeVector": "⥜", "RightUpVector": "↾", "RightUpVectorBar": "⥔", "RightVector": "⇀", "RightVectorBar": "⥓", "ring": "˚", "risingdotseq": "≓", "rlarr": "⇄", "rlhar": "⇌", "rlm": "‏", "rmoust": "⎱", "rmoustache": "⎱", "rnmid": "⫮", "roang": "⟭", "roarr": "⇾", "robrk": "⟧", "ropar": "⦆", "ropf": "𝕣", "Ropf": "ℝ", "roplus": "⨮", "rotimes": "⨵", "RoundImplies": "⥰", "rpar": ")", "rpargt": "⦔", "rppolint": "⨒", "rrarr": "⇉", "Rrightarrow": "⇛", "rsaquo": "›", "rscr": "𝓇", "Rscr": "ℛ", "rsh": "↱", "Rsh": "↱", "rsqb": "]", "rsquo": "’", "rsquor": "’", "rthree": "⋌", "rtimes": "⋊", "rtri": "▹", "rtrie": "⊵", "rtrif": "▸", "rtriltri": "⧎", "RuleDelayed": "⧴", "ruluhar": "⥨", "rx": "℞", "sacute": "ś", "Sacute": "Ś", "sbquo": "‚", "sc": "≻", "Sc": "⪼", "scap": "⪸", "scaron": "š", "Scaron": "Š", "sccue": "≽", "sce": "⪰", "scE": "⪴", "scedil": "ş", "Scedil": "Ş", "scirc": "ŝ", "Scirc": "Ŝ", "scnap": "⪺", "scnE": "⪶", "scnsim": "⋩", "scpolint": "⨓", "scsim": "≿", "scy": "с", "Scy": "С", "sdot": "⋅", "sdotb": "⊡", "sdote": "⩦", "searhk": "⤥", "searr": "↘", "seArr": "⇘", "searrow": "↘", "sect": "§", "semi": ";", "seswar": "⤩", "setminus": "∖", "setmn": "∖", "sext": "✶", "sfr": "𝔰", "Sfr": "𝔖", "sfrown": "⌢", "sharp": "♯", "shchcy": "щ", "SHCHcy": "Щ", "shcy": "ш", "SHcy": "Ш", "ShortDownArrow": "↓", "ShortLeftArrow": "←", "shortmid": "∣", "shortparallel": "∥", "ShortRightArrow": "→", "ShortUpArrow": "↑", "shy": "­", "sigma": "σ", "Sigma": "Σ", "sigmaf": "ς", "sigmav": "ς", "sim": "∼", "simdot": "⩪", "sime": "≃", "simeq": "≃", "simg": "⪞", "simgE": "⪠", "siml": "⪝", "simlE": "⪟", "simne": "≆", "simplus": "⨤", "simrarr": "⥲", "slarr": "←", "SmallCircle": "∘", "smallsetminus": "∖", "smashp": "⨳", "smeparsl": "⧤", "smid": "∣", "smile": "⌣", "smt": "⪪", "smte": "⪬", "smtes": "⪬︀", "softcy": "ь", "SOFTcy": "Ь", "sol": "/", "solb": "⧄", "solbar": "⌿", "sopf": "𝕤", "Sopf": "𝕊", "spades": "♠", "spadesuit": "♠", "spar": "∥", "sqcap": "⊓", "sqcaps": "⊓︀", "sqcup": "⊔", "sqcups": "⊔︀", "Sqrt": "√", "sqsub": "⊏", "sqsube": "⊑", "sqsubset": "⊏", "sqsubseteq": "⊑", "sqsup": "⊐", "sqsupe": "⊒", "sqsupset": "⊐", "sqsupseteq": "⊒", "squ": "□", "square": "□", "Square": "□", "SquareIntersection": "⊓", "SquareSubset": "⊏", "SquareSubsetEqual": "⊑", "SquareSuperset": "⊐", "SquareSupersetEqual": "⊒", "SquareUnion": "⊔", "squarf": "▪", "squf": "▪", "srarr": "→", "sscr": "𝓈", "Sscr": "𝒮", "ssetmn": "∖", "ssmile": "⌣", "sstarf": "⋆", "star": "☆", "Star": "⋆", "starf": "★", "straightepsilon": "ϵ", "straightphi": "ϕ", "strns": "¯", "sub": "⊂", "Sub": "⋐", "subdot": "⪽", "sube": "⊆", "subE": "⫅", "subedot": "⫃", "submult": "⫁", "subne": "⊊", "subnE": "⫋", "subplus": "⪿", "subrarr": "⥹", "subset": "⊂", "Subset": "⋐", "subseteq": "⊆", "subseteqq": "⫅", "SubsetEqual": "⊆", "subsetneq": "⊊", "subsetneqq": "⫋", "subsim": "⫇", "subsub": "⫕", "subsup": "⫓", "succ": "≻", "succapprox": "⪸", "succcurlyeq": "≽", "Succeeds": "≻", "SucceedsEqual": "⪰", "SucceedsSlantEqual": "≽", "SucceedsTilde": "≿", "succeq": "⪰", "succnapprox": "⪺", "succneqq": "⪶", "succnsim": "⋩", "succsim": "≿", "SuchThat": "∋", "sum": "∑", "Sum": "∑", "sung": "♪", "sup": "⊃", "Sup": "⋑", "sup1": "¹", "sup2": "²", "sup3": "³", "supdot": "⪾", "supdsub": "⫘", "supe": "⊇", "supE": "⫆", "supedot": "⫄", "Superset": "⊃", "SupersetEqual": "⊇", "suphsol": "⟉", "suphsub": "⫗", "suplarr": "⥻", "supmult": "⫂", "supne": "⊋", "supnE": "⫌", "supplus": "⫀", "supset": "⊃", "Supset": "⋑", "supseteq": "⊇", "supseteqq": "⫆", "supsetneq": "⊋", "supsetneqq": "⫌", "supsim": "⫈", "supsub": "⫔", "supsup": "⫖", "swarhk": "⤦", "swarr": "↙", "swArr": "⇙", "swarrow": "↙", "swnwar": "⤪", "szlig": "ß", "Tab": "	", "target": "⌖", "tau": "τ", "Tau": "Τ", "tbrk": "⎴", "tcaron": "ť", "Tcaron": "Ť", "tcedil": "ţ", "Tcedil": "Ţ", "tcy": "т", "Tcy": "Т", "tdot": "⃛", "telrec": "⌕", "tfr": "𝔱", "Tfr": "𝔗", "there4": "∴", "therefore": "∴", "Therefore": "∴", "theta": "θ", "Theta": "Θ", "thetasym": "ϑ", "thetav": "ϑ", "thickapprox": "≈", "thicksim": "∼", "ThickSpace": "  ", "thinsp": " ", "ThinSpace": " ", "thkap": "≈", "thksim": "∼", "thorn": "þ", "THORN": "Þ", "tilde": "˜", "Tilde": "∼", "TildeEqual": "≃", "TildeFullEqual": "≅", "TildeTilde": "≈", "times": "×", "timesb": "⊠", "timesbar": "⨱", "timesd": "⨰", "tint": "∭", "toea": "⤨", "top": "⊤", "topbot": "⌶", "topcir": "⫱", "topf": "𝕥", "Topf": "𝕋", "topfork": "⫚", "tosa": "⤩", "tprime": "‴", "trade": "™", "TRADE": "™", "triangle": "▵", "triangledown": "▿", "triangleleft": "◃", "trianglelefteq": "⊴", "triangleq": "≜", "triangleright": "▹", "trianglerighteq": "⊵", "tridot": "◬", "trie": "≜", "triminus": "⨺", "TripleDot": "⃛", "triplus": "⨹", "trisb": "⧍", "tritime": "⨻", "trpezium": "⏢", "tscr": "𝓉", "Tscr": "𝒯", "tscy": "ц", "TScy": "Ц", "tshcy": "ћ", "TSHcy": "Ћ", "tstrok": "ŧ", "Tstrok": "Ŧ", "twixt": "≬", "twoheadleftarrow": "↞", "twoheadrightarrow": "↠", "uacute": "ú", "Uacute": "Ú", "uarr": "↑", "uArr": "⇑", "Uarr": "↟", "Uarrocir": "⥉", "ubrcy": "ў", "Ubrcy": "Ў", "ubreve": "ŭ", "Ubreve": "Ŭ", "ucirc": "û", "Ucirc": "Û", "ucy": "у", "Ucy": "У", "udarr": "⇅", "udblac": "ű", "Udblac": "Ű", "udhar": "⥮", "ufisht": "⥾", "ufr": "𝔲", "Ufr": "𝔘", "ugrave": "ù", "Ugrave": "Ù", "uHar": "⥣", "uharl": "↿", "uharr": "↾", "uhblk": "▀", "ulcorn": "⌜", "ulcorner": "⌜", "ulcrop": "⌏", "ultri": "◸", "umacr": "ū", "Umacr": "Ū", "uml": "¨", "UnderBar": "_", "UnderBrace": "⏟", "UnderBracket": "⎵", "UnderParenthesis": "⏝", "Union": "⋃", "UnionPlus": "⊎", "uogon": "ų", "Uogon": "Ų", "uopf": "𝕦", "Uopf": "𝕌", "uparrow": "↑", "Uparrow": "⇑", "UpArrow": "↑", "UpArrowBar": "⤒", "UpArrowDownArrow": "⇅", "updownarrow": "↕", "Updownarrow": "⇕", "UpDownArrow": "↕", "UpEquilibrium": "⥮", "upharpoonleft": "↿", "upharpoonright": "↾", "uplus": "⊎", "UpperLeftArrow": "↖", "UpperRightArrow": "↗", "upsi": "υ", "Upsi": "ϒ", "upsih": "ϒ", "upsilon": "υ", "Upsilon": "Υ", "UpTee": "⊥", "UpTeeArrow": "↥", "upuparrows": "⇈", "urcorn": "⌝", "urcorner": "⌝", "urcrop": "⌎", "uring": "ů", "Uring": "Ů", "urtri": "◹", "uscr": "𝓊", "Uscr": "𝒰", "utdot": "⋰", "utilde": "ũ", "Utilde": "Ũ", "utri": "▵", "utrif": "▴", "uuarr": "⇈", "uuml": "ü", "Uuml": "Ü", "uwangle": "⦧", "vangrt": "⦜", "varepsilon": "ϵ", "varkappa": "ϰ", "varnothing": "∅", "varphi": "ϕ", "varpi": "ϖ", "varpropto": "∝", "varr": "↕", "vArr": "⇕", "varrho": "ϱ", "varsigma": "ς", "varsubsetneq": "⊊︀", "varsubsetneqq": "⫋︀", "varsupsetneq": "⊋︀", "varsupsetneqq": "⫌︀", "vartheta": "ϑ", "vartriangleleft": "⊲", "vartriangleright": "⊳", "vBar": "⫨", "Vbar": "⫫", "vBarv": "⫩", "vcy": "в", "Vcy": "В", "vdash": "⊢", "vDash": "⊨", "Vdash": "⊩", "VDash": "⊫", "Vdashl": "⫦", "vee": "∨", "Vee": "⋁", "veebar": "⊻", "veeeq": "≚", "vellip": "⋮", "verbar": "|", "Verbar": "‖", "vert": "|", "Vert": "‖", "VerticalBar": "∣", "VerticalLine": "|", "VerticalSeparator": "❘", "VerticalTilde": "≀", "VeryThinSpace": " ", "vfr": "𝔳", "Vfr": "𝔙", "vltri": "⊲", "vnsub": "⊂⃒", "vnsup": "⊃⃒", "vopf": "𝕧", "Vopf": "𝕍", "vprop": "∝", "vrtri": "⊳", "vscr": "𝓋", "Vscr": "𝒱", "vsubne": "⊊︀", "vsubnE": "⫋︀", "vsupne": "⊋︀", "vsupnE": "⫌︀", "Vvdash": "⊪", "vzigzag": "⦚", "wcirc": "ŵ", "Wcirc": "Ŵ", "wedbar": "⩟", "wedge": "∧", "Wedge": "⋀", "wedgeq": "≙", "weierp": "℘", "wfr": "𝔴", "Wfr": "𝔚", "wopf": "𝕨", "Wopf": "𝕎", "wp": "℘", "wr": "≀", "wreath": "≀", "wscr": "𝓌", "Wscr": "𝒲", "xcap": "⋂", "xcirc": "◯", "xcup": "⋃", "xdtri": "▽", "xfr": "𝔵", "Xfr": "𝔛", "xharr": "⟷", "xhArr": "⟺", "xi": "ξ", "Xi": "Ξ", "xlarr": "⟵", "xlArr": "⟸", "xmap": "⟼", "xnis": "⋻", "xodot": "⨀", "xopf": "𝕩", "Xopf": "𝕏", "xoplus": "⨁", "xotime": "⨂", "xrarr": "⟶", "xrArr": "⟹", "xscr": "𝓍", "Xscr": "𝒳", "xsqcup": "⨆", "xuplus": "⨄", "xutri": "△", "xvee": "⋁", "xwedge": "⋀", "yacute": "ý", "Yacute": "Ý", "yacy": "я", "YAcy": "Я", "ycirc": "ŷ", "Ycirc": "Ŷ", "ycy": "ы", "Ycy": "Ы", "yen": "¥", "yfr": "𝔶", "Yfr": "𝔜", "yicy": "ї", "YIcy": "Ї", "yopf": "𝕪", "Yopf": "𝕐", "yscr": "𝓎", "Yscr": "𝒴", "yucy": "ю", "YUcy": "Ю", "yuml": "ÿ", "Yuml": "Ÿ", "zacute": "ź", "Zacute": "Ź", "zcaron": "ž", "Zcaron": "Ž", "zcy": "з", "Zcy": "З", "zdot": "ż", "Zdot": "Ż", "zeetrf": "ℨ", "ZeroWidthSpace": "​", "zeta": "ζ", "Zeta": "Ζ", "zfr": "𝔷", "Zfr": "ℨ", "zhcy": "ж", "ZHcy": "Ж", "zigrarr": "⇝", "zopf": "𝕫", "Zopf": "ℤ", "zscr": "𝓏", "Zscr": "𝒵", "zwj": "‍", "zwnj": "‌" };
      var decodeMapLegacy = { "aacute": "á", "Aacute": "Á", "acirc": "â", "Acirc": "Â", "acute": "´", "aelig": "æ", "AElig": "Æ", "agrave": "à", "Agrave": "À", "amp": "&", "AMP": "&", "aring": "å", "Aring": "Å", "atilde": "ã", "Atilde": "Ã", "auml": "ä", "Auml": "Ä", "brvbar": "¦", "ccedil": "ç", "Ccedil": "Ç", "cedil": "¸", "cent": "¢", "copy": "©", "COPY": "©", "curren": "¤", "deg": "°", "divide": "÷", "eacute": "é", "Eacute": "É", "ecirc": "ê", "Ecirc": "Ê", "egrave": "è", "Egrave": "È", "eth": "ð", "ETH": "Ð", "euml": "ë", "Euml": "Ë", "frac12": "½", "frac14": "¼", "frac34": "¾", "gt": ">", "GT": ">", "iacute": "í", "Iacute": "Í", "icirc": "î", "Icirc": "Î", "iexcl": "¡", "igrave": "ì", "Igrave": "Ì", "iquest": "¿", "iuml": "ï", "Iuml": "Ï", "laquo": "«", "lt": "<", "LT": "<", "macr": "¯", "micro": "µ", "middot": "·", "nbsp": " ", "not": "¬", "ntilde": "ñ", "Ntilde": "Ñ", "oacute": "ó", "Oacute": "Ó", "ocirc": "ô", "Ocirc": "Ô", "ograve": "ò", "Ograve": "Ò", "ordf": "ª", "ordm": "º", "oslash": "ø", "Oslash": "Ø", "otilde": "õ", "Otilde": "Õ", "ouml": "ö", "Ouml": "Ö", "para": "¶", "plusmn": "±", "pound": "£", "quot": '"', "QUOT": '"', "raquo": "»", "reg": "®", "REG": "®", "sect": "§", "shy": "­", "sup1": "¹", "sup2": "²", "sup3": "³", "szlig": "ß", "thorn": "þ", "THORN": "Þ", "times": "×", "uacute": "ú", "Uacute": "Ú", "ucirc": "û", "Ucirc": "Û", "ugrave": "ù", "Ugrave": "Ù", "uml": "¨", "uuml": "ü", "Uuml": "Ü", "yacute": "ý", "Yacute": "Ý", "yen": "¥", "yuml": "ÿ" };
      var decodeMapNumeric = { "0": "�", "128": "€", "130": "‚", "131": "ƒ", "132": "„", "133": "…", "134": "†", "135": "‡", "136": "ˆ", "137": "‰", "138": "Š", "139": "‹", "140": "Œ", "142": "Ž", "145": "‘", "146": "’", "147": "“", "148": "”", "149": "•", "150": "–", "151": "—", "152": "˜", "153": "™", "154": "š", "155": "›", "156": "œ", "158": "ž", "159": "Ÿ" };
      var invalidReferenceCodePoints = [1, 2, 3, 4, 5, 6, 7, 8, 11, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 127, 128, 129, 130, 131, 132, 133, 134, 135, 136, 137, 138, 139, 140, 141, 142, 143, 144, 145, 146, 147, 148, 149, 150, 151, 152, 153, 154, 155, 156, 157, 158, 159, 64976, 64977, 64978, 64979, 64980, 64981, 64982, 64983, 64984, 64985, 64986, 64987, 64988, 64989, 64990, 64991, 64992, 64993, 64994, 64995, 64996, 64997, 64998, 64999, 65e3, 65001, 65002, 65003, 65004, 65005, 65006, 65007, 65534, 65535, 131070, 131071, 196606, 196607, 262142, 262143, 327678, 327679, 393214, 393215, 458750, 458751, 524286, 524287, 589822, 589823, 655358, 655359, 720894, 720895, 786430, 786431, 851966, 851967, 917502, 917503, 983038, 983039, 1048574, 1048575, 1114110, 1114111];
      var stringFromCharCode = String.fromCharCode;
      var object = {};
      var hasOwnProperty = object.hasOwnProperty;
      var has = function(object2, propertyName) {
        return hasOwnProperty.call(object2, propertyName);
      };
      var contains = function(array, value) {
        var index = -1;
        var length = array.length;
        while (++index < length) {
          if (array[index] == value) {
            return true;
          }
        }
        return false;
      };
      var merge = function(options, defaults) {
        if (!options) {
          return defaults;
        }
        var result = {};
        var key2;
        for (key2 in defaults) {
          result[key2] = has(options, key2) ? options[key2] : defaults[key2];
        }
        return result;
      };
      var codePointToSymbol = function(codePoint, strict) {
        var output = "";
        if (codePoint >= 55296 && codePoint <= 57343 || codePoint > 1114111) {
          if (strict) {
            parseError("character reference outside the permissible Unicode range");
          }
          return "�";
        }
        if (has(decodeMapNumeric, codePoint)) {
          if (strict) {
            parseError("disallowed character reference");
          }
          return decodeMapNumeric[codePoint];
        }
        if (strict && contains(invalidReferenceCodePoints, codePoint)) {
          parseError("disallowed character reference");
        }
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
        var strict = options.strict;
        if (strict && regexInvalidRawCodePoint.test(string)) {
          parseError("forbidden code point");
        }
        var encodeEverything = options.encodeEverything;
        var useNamedReferences = options.useNamedReferences;
        var allowUnsafeSymbols = options.allowUnsafeSymbols;
        var escapeCodePoint = options.decimal ? decEscape : hexEscape;
        var escapeBmpSymbol = function(symbol) {
          return escapeCodePoint(symbol.charCodeAt(0));
        };
        if (encodeEverything) {
          string = string.replace(regexAsciiWhitelist, function(symbol) {
            if (useNamedReferences && has(encodeMap, symbol)) {
              return "&" + encodeMap[symbol] + ";";
            }
            return escapeBmpSymbol(symbol);
          });
          if (useNamedReferences) {
            string = string.replace(/&gt;\u20D2/g, "&nvgt;").replace(/&lt;\u20D2/g, "&nvlt;").replace(/&#x66;&#x6A;/g, "&fjlig;");
          }
          if (useNamedReferences) {
            string = string.replace(regexEncodeNonAscii, function(string2) {
              return "&" + encodeMap[string2] + ";";
            });
          }
        } else if (useNamedReferences) {
          if (!allowUnsafeSymbols) {
            string = string.replace(regexEscape, function(string2) {
              return "&" + encodeMap[string2] + ";";
            });
          }
          string = string.replace(/&gt;\u20D2/g, "&nvgt;").replace(/&lt;\u20D2/g, "&nvlt;");
          string = string.replace(regexEncodeNonAscii, function(string2) {
            return "&" + encodeMap[string2] + ";";
          });
        } else if (!allowUnsafeSymbols) {
          string = string.replace(regexEscape, escapeBmpSymbol);
        }
        return string.replace(regexAstralSymbols, function($0) {
          var high = $0.charCodeAt(0);
          var low = $0.charCodeAt(1);
          var codePoint = (high - 55296) * 1024 + low - 56320 + 65536;
          return escapeCodePoint(codePoint);
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
        if (strict && regexInvalidEntity.test(html)) {
          parseError("malformed character reference");
        }
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
              if (strict && next == "=") {
                parseError("`&` did not start a character reference");
              }
              return $0;
            } else {
              if (strict) {
                parseError(
                  "named character reference was not terminated by a semicolon"
                );
              }
              return decodeMapLegacy[reference] + (next || "");
            }
          }
          if ($4) {
            decDigits = $4;
            semicolon = $5;
            if (strict && !semicolon) {
              parseError("character reference was not terminated by a semicolon");
            }
            codePoint = parseInt(decDigits, 10);
            return codePointToSymbol(codePoint, strict);
          }
          if ($6) {
            hexDigits = $6;
            semicolon = $7;
            if (strict && !semicolon) {
              parseError("character reference was not terminated by a semicolon");
            }
            codePoint = parseInt(hexDigits, 16);
            return codePointToSymbol(codePoint, strict);
          }
          if (strict) {
            parseError(
              "named character reference was not terminated by a semicolon"
            );
          }
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
      var he2 = {
        "version": "1.2.0",
        "encode": encode,
        "decode": decode,
        "escape": escape,
        "unescape": decode
      };
      if (freeExports && !freeExports.nodeType) {
        if (freeModule) {
          freeModule.exports = he2;
        } else {
          for (var key in he2) {
            has(he2, key) && (freeExports[key] = he2[key]);
          }
        }
      } else {
        root.he = he2;
      }
    })(he$2);
  })(he$3, he$3.exports);
  return he$3.exports;
}
var heExports = requireHe();
const he$1 = /* @__PURE__ */ getDefaultExportFromCjs(heExports);
class MarkdownUtils {
  static wrapWithLink(markdown, tunes) {
    if (!tunes.linkTune) {
      return MarkdownUtils.addAttributes(markdown, tunes);
    }
    const linkTune = tunes.linkTune;
    if (!linkTune.url) {
      return markdown;
    }
    let link = `[${markdown}](${linkTune.url}){`;
    if (linkTune.targetBlank) {
      link += `target="_blank"`;
    }
    link += `}`;
    link = link.replace(/{}/g, "");
    if (linkTune.hideForBot) {
      link = "#" + link;
    }
    return link;
  }
  static getAttributes(tunes) {
    let result = "";
    const anchor = tunes?.anchor;
    if (anchor && anchor !== "") {
      result += `#${anchor}`;
    }
    const alignment = tunes?.textAlign;
    if (alignment && alignment !== "left") {
      const alignmentClass = alignment === "center" ? "text-center" : alignment === "right" ? "text-right" : "";
      if (alignmentClass) {
        result += `.${alignmentClass}`;
      }
    }
    const className = tunes?.class;
    if (className && className !== "") {
      result += `.${className}`;
    }
    return result;
  }
  static addAttributes(markdown, tunes) {
    let result = MarkdownUtils.getAttributes(tunes);
    result = result.replace(/\s+/g, " ").trim();
    if (result !== "") {
      return "{" + result + "}\n" + markdown;
    }
    return markdown;
  }
  static startWithAttribute(firstLine) {
    const line = firstLine.trim();
    if (line.startsWith("{#") && (line.endsWith("#}") || !line.endsWith("}")))
      return false;
    return line.startsWith("{") && line.endsWith("}") && !line.startsWith("{{") && !line.startsWith("{%");
  }
  static parseAttributes(attributeLine) {
    const tunes = {};
    const anchorMatch = attributeLine.match(/#([a-zA-Z0-9_-]+)/);
    if (anchorMatch) {
      tunes.anchor = anchorMatch[1];
    }
    const alignmentMatch = attributeLine.match(/\.text-(left|center|right)/);
    if (alignmentMatch) {
      tunes.textAlign = alignmentMatch[1];
      attributeLine = attributeLine.replace(alignmentMatch[0], "");
    }
    const classMatch = attributeLine.match(/\.([a-zA-Z0-9_-]+)/g);
    if (classMatch) {
      tunes.class = classMatch.join(" ");
    }
    return tunes;
  }
  static retrieveMarkdownWithoutTunes(markdown) {
    markdown = markdown.trim();
    let lines = markdown.split("\n");
    const firstLine = lines[0] ?? "";
    if (MarkdownUtils.startWithAttribute(firstLine)) {
      lines[0] = "";
      return lines.join("\n").trim();
    }
    return markdown;
  }
  static parseTunesFromMarkdown(markdown) {
    markdown = markdown.trim();
    let lines = markdown.split("\n");
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
  // TODO : manage "ex" ~ "ample" or variable ?
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
      if (char === ")" && !inQuote) {
        break;
      }
      if (escaped) {
        current += char === quoteChar ? char : "\\" + char;
        escaped = false;
        continue;
      }
      if (char === "\\") {
        escaped = true;
        continue;
      }
      if (['"', "'"].includes(char) && !inQuote) {
        inQuote = true;
        quoteChar = char;
        continue;
      }
      if (char === quoteChar && inQuote) {
        inQuote = false;
        quoteChar = "";
        continue;
      }
      if (!inQuote && ![" ", ","].includes(char)) {
        return null;
      }
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
   * Parse HTML attributes from a string and return them as a typed record
   */
  static parseHtmlAttributes(attrString) {
    const attrs = {};
    attrString.replace(
      /(\w+)\s*=\s*"([^"]*?)"/gi,
      (_match, key, value) => {
        attrs[key.toLowerCase()] = value;
        return "";
      }
    );
    return attrs;
  }
  static convertAnchorToMarkdown(attrString, text) {
    const attrs = MarkdownUtils.parseHtmlAttributes(attrString);
    const href = attrs.href || "#";
    const extras = [];
    let obfuscate = false;
    if (attrs.rel && attrs.rel === "obfuscate") {
      obfuscate = true;
    } else if (attrs.rel) extras.push(`rel="${attrs.rel}"`);
    if (attrs.target) extras.push(`target="${attrs.target}"`);
    if (attrs.class) extras.push(`class="${attrs.class}"`);
    return (obfuscate ? "#" : "") + (extras.length ? `[${text}](${href}){${extras.join(" ")}}` : `[${text}](${href})`);
  }
  static fixDash(text) {
    text = text.replace(new RegExp("(?<=[0-9 ])-(?=[0-9 ]|$)", "g"), "—");
    return text.replace(/ ?-- ?([^-]|$)/gs, "—$1");
  }
  static makeUrlRelative(text) {
    const host = globalThis.window.pageHost;
    const baseUrl = globalThis.window.location.origin;
    if (host === "") return text;
    const toReplace = [
      `"${baseUrl}/${host}/`,
      `"${baseUrl}/`,
      `"https://${host}/`,
      `"http://${host}/`,
      `"://${host}/`
    ];
    toReplace.forEach((replaceStr) => {
      text = text.split(replaceStr).join('"/');
    });
    return text;
  }
  static fixer(text) {
    const noBreakSpace = " ";
    const spaces = "â¯|Â­|Â | |\\s";
    text = MarkdownUtils.fixDash(text);
    text = MarkdownUtils.makeUrlRelative(text);
    if (globalThis.window.pageLocale) {
      text = SmartQuotes(text, globalThis.window.pageLocale);
    }
    text = text.replace(
      new RegExp(`([\\dº])(${spaces})+([º°%Ω฿₵¢₡$₫֏€ƒ₲₴₭£₤₺₦₨₱៛₹$₪৳₸₮₩¥]{1})`, "g"),
      // \\w
      `$1${noBreakSpace}$3`
    ).replace(/&nbsp;/gi, " ").replace(/([a-z])'([a-z])/gim, `$1’$2`).replace(/ <\/([a-z]+)>/gi, "</$1> ").replace(/ ?<(b|i|strong|em|span)> ?<\/(b|i|strong|em|span)> ?/gi, " ").replace(/<(b|i|strong|em|span|a)[^>]*><\/(b|i|strong|em|span|a)>/gi, "").replace(new RegExp(`([^\\d\\s]+)[${spaces}]{1,},[${spaces}]{1,}`, "gmu"), "$1, ").replace(new RegExp(`([^\\d\\s]+)[${spaces}]{1,}\\.[${spaces}]{1,}`, "gmu"), "$1. ").replace(/\.{3,}/g, "…").replace(/ &amp; /gi, " & ").replace(/&shy;/g, "").replace(new RegExp(`[${spaces}]{2,}`, "gmu"), " ").replace(
      new RegExp(`(\\d+["']?)([${spaces}])?x([${spaces}])?(?=\\d)`, "g"),
      "$1$2×$2"
    ).replace(/\(tm\)/gi, "™").replace(/\(r\)/gi, "®").replace(/\(c\)/gi, "©");
    return text;
  }
  static convertInlineHtmlToMarkdown(html) {
    html = MarkdownUtils.fixer(html);
    html = he$1.decode(html);
    return html.replace(/<(b|strong|em|i|a[^>]*)> /gi, " <$1>").replace(/ <\/(b|strong|em|i|a[^>]*)>/gi, "<$1> ").replace(/<b>(.*?)<\/b>/gi, "**$1**").replace(/<i>(.*?)<\/i>/gi, "_$1_").replace(/<code( class="inline-code")?>(.*?)<\/code>/gi, "`$2`").replace(/<s( class="cdx-strikethrough")?>(.*?)<\/s>/gi, "~~$2~~").replace(/ class="cdx-marker"/gi, "").replace(
      /<a\s+([^>]+)>(.*?)<\/a>/gi,
      (_match, attrString, text) => MarkdownUtils.convertAnchorToMarkdown(attrString, text)
    ).replace(/<br\s*\/?>/gi, "\n").replace(/<div>/gi, "\n").replace(/<\/div>/gi, "");
  }
  static convertMarkdownToAnchor(markdown) {
    const isObfuscated = markdown.startsWith("#");
    const linkText = isObfuscated ? markdown.substring(1) : markdown;
    const linkWithAttrsRegex = /\[([^\]]+)\]\(([^){]+)\)\{([^}]+)\}/;
    const simpleLinkRegex = /\[([^\]]+)\]\(([^)]+)\)/;
    let match = linkText.match(linkWithAttrsRegex);
    let text;
    let href;
    let attrsString = "";
    if (match) {
      text = match[1] ?? "";
      href = match[2] ?? "";
      attrsString = match[3] ?? "";
    } else {
      match = linkText.match(simpleLinkRegex);
      if (!match) return markdown;
      text = match[1] ?? "";
      href = match[2] ?? "";
    }
    if (isObfuscated) {
      attrsString = attrsString ? `rel="obfuscate" ${attrsString}` : 'rel="obfuscate"';
    }
    const attrs = attrsString ? " " + attrsString : "";
    return `<a href="${href}"${attrs}>${text}</a>`;
  }
  static convertInlineMarkdownToHtml(markdown) {
    return markdown.replace(/\*\*(.+?)\*\*/g, "<b>$1</b>").replace(/_(.+?)_/g, "<i>$1</i>").replace(/`(.+?)`/g, '<code class="inline-code">$1</code>').replace(/~~(.+?)~~/g, '<s class="cdx-strikethrough">$1</s>').replace(
      /#?\[([^\]]+)\]\(([^){]+)\)(?:\{([^}]+)\})?/g,
      (match) => MarkdownUtils.convertMarkdownToAnchor(match)
    ).replace(/\n/g, "<br>");
  }
  /**
   * Formate le contenu Markdown avec Prettier
   */
  static async formatMarkdownWithPrettier(markdownContent) {
    try {
      const prettierMarkdown = await import("./markdown-DwZsRjr0.mjs");
      const formatted = await Un(markdownContent, {
        parser: "markdown",
        plugins: [prettierMarkdown],
        //printWidth: 80,
        proseWrap: "preserve",
        tabWidth: 2,
        useTabs: false
      });
      return formatted.trim();
    } catch (error) {
      console.log("Erreur lors du formatage Prettier du Markdown", {
        content: markdownContent
      });
      return markdownContent;
    }
  }
  static wrapInQuotes(text) {
    if (!text.includes("'")) return "'" + text + "'";
    const escaped = text.replace('"', '\\"');
    return `"${escaped}"`;
  }
}
function e(text) {
  return MarkdownUtils.wrapInQuotes(text);
}
class Header {
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
    if (this._levelSelect) {
      this._levelSelect.value = level.toString();
    }
  }
  merge(data) {
    const headerElement = this.getHeaderElement();
    if (headerElement) {
      headerElement.insertAdjacentHTML("beforeend", data.text);
    }
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
    if (!headerElement) {
      return this._data;
    }
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
      if (newHeaderElement && oldHeaderElement) {
        newHeaderElement.innerHTML = oldHeaderElement.innerHTML;
      }
      this._element.parentNode.replaceChild(newHeader, this._element);
      this._element = newHeader;
      this._levelSelect = this._element.querySelector(".ce-header-level-select");
    }
    if (data.text !== void 0) {
      const headerElement = this.getHeaderElement();
      if (headerElement) {
        headerElement.innerHTML = data.text || "";
      }
    }
  }
  getHeaderElement(element) {
    const target = element || this._element;
    if (!target) return null;
    const header = target.querySelector("h1, h2, h3, h4, h5, h6");
    if (header) return header;
    if (target.tagName.match(/^H[1-6]$/)) {
      return target;
    }
    return null;
  }
  getTag() {
    const container = globalThis.document.createElement("div");
    container.classList.add("ce-header-container");
    const levelSelect = globalThis.document.createElement("select");
    levelSelect.classList.add("ce-header-level-select");
    levelSelect.contentEditable = "false";
    levelSelect.title = "Select heading level";
    this.levels.forEach((level) => {
      const option = globalThis.document.createElement("option");
      option.value = level.number.toString();
      option.textContent = `H${level.number}`;
      option.selected = level.number === this._data.level;
      levelSelect.appendChild(option);
    });
    levelSelect.addEventListener("change", (e2) => {
      e2.preventDefault();
      e2.stopPropagation();
      const newLevel = parseInt(e2.target.value);
      this.setLevel(newLevel);
    });
    this._levelSelect = levelSelect;
    const tag = globalThis.document.createElement(this.currentLevel.tag);
    tag.innerHTML = this._data.text || "";
    tag.classList.add("ce-header");
    tag.contentEditable = "true";
    tag.dataset.placeholder = this.api.i18n.t("");
    container.appendChild(levelSelect);
    container.appendChild(tag);
    return container;
  }
  get currentLevel() {
    return this.levels.find((levelItem) => levelItem.number === this._data.level) || this.defaultLevel;
  }
  get defaultLevel() {
    const defaultLevel = this.levels[0];
    if (!defaultLevel) {
      throw new Error("Default level not found");
    }
    return defaultLevel;
  }
  get levels() {
    return [
      { number: 2, tag: "H2", svg: t },
      { number: 3, tag: "H3", svg: r$1 },
      { number: 4, tag: "H4", svg: e$1 },
      { number: 5, tag: "H5", svg: n$2 },
      { number: 6, tag: "H6", svg: s$1 }
    ];
  }
  onPaste(event) {
    const detail = event.detail;
    if ("data" in detail) {
      const content = detail.data;
      const tagToLevel = {
        H2: 2,
        H3: 3,
        H4: 4,
        H5: 5,
        H6: 6
      };
      const level = tagToLevel[content.tagName] || 2;
      this.data = {
        level,
        text: content.innerHTML
      };
    }
  }
  static get pasteConfig() {
    return {
      tags: ["H1", "H2", "H3", "H4", "H5", "H6"]
    };
  }
  static get toolbox() {
    return {
      icon: G$2,
      title: "Heading"
    };
  }
  static async exportToMarkdown(data, tunes) {
    if (!data || !data.text) {
      return "";
    }
    const level = data.level || 2;
    const hashes = "#".repeat(level);
    let markdown = `${hashes} ${data.text}`;
    markdown = MarkdownUtils.convertInlineHtmlToMarkdown(markdown);
    const formattedMarkdown = await MarkdownUtils.formatMarkdownWithPrettier(markdown);
    return MarkdownUtils.addAttributes(formattedMarkdown, tunes);
  }
  static importFromMarkdown(editor, markdown) {
    const result = MarkdownUtils.parseTunesFromMarkdown(markdown);
    const tunes = result.tunes;
    let markdownWithoutTunes = result.markdown;
    markdownWithoutTunes = MarkdownUtils.convertInlineMarkdownToHtml(markdownWithoutTunes);
    const levelMatch = markdownWithoutTunes.trim().match(/^#{2,6}\s/);
    if (!levelMatch) {
      throw new Error("Invalid markdown format for header");
    }
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
}
(function() {
  try {
    if (typeof globalThis.document < "u") {
      var e2 = globalThis.document.createElement("style");
      e2.appendChild(globalThis.document.createTextNode(".ce-paragraph{line-height:1.6em;outline:none}.ce-block:only-of-type .ce-paragraph[data-placeholder-active]:empty:before,.ce-block:only-of-type .ce-paragraph[data-placeholder-active][data-empty=true]:before{content:attr(data-placeholder-active)}.ce-paragraph p:first-of-type{margin-top:0}.ce-paragraph p:last-of-type{margin-bottom:0}")), globalThis.document.head.appendChild(e2);
    }
  } catch (a2) {
    console.error("vite-plugin-css-injected-by-js", a2);
  }
})();
const a$1 = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M8 9V7.2C8 7.08954 8.08954 7 8.2 7L12 7M16 9V7.2C16 7.08954 15.9105 7 15.8 7L12 7M12 7L12 17M12 17H10M12 17H14"/></svg>';
function l(r2) {
  const t2 = globalThis.document.createElement("div");
  t2.innerHTML = r2.trim();
  const e2 = globalThis.document.createDocumentFragment();
  return e2.append(...Array.from(t2.childNodes)), e2;
}
let n$1 = class n {
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
  constructor({ data: t2, config: e2, api: i, readOnly: s2 }) {
    this.api = i, this.readOnly = s2, this._CSS = {
      block: this.api.styles.block,
      wrapper: "ce-paragraph"
    }, this.readOnly || (this.onKeyUp = this.onKeyUp.bind(this)), this._placeholder = e2.placeholder ? e2.placeholder : n.DEFAULT_PLACEHOLDER, this._data = t2 ?? {}, this._element = null, this._preserveBlank = e2.preserveBlank ?? false;
  }
  /**
   * Check if text content is empty and set empty string to inner html.
   * We need this because some browsers (e.g. Safari) insert <br> into empty contenteditanle elements
   *
   * @param {KeyboardEvent} e - key up event
   */
  onKeyUp(t2) {
    if (t2.code !== "Backspace" && t2.code !== "Delete" || !this._element)
      return;
    const { textContent: e2 } = this._element;
    e2 === "" && (this._element.innerHTML = "");
  }
  /**
   * Create Tool's view
   *
   * @returns {HTMLDivElement}
   * @private
   */
  drawView() {
    const t2 = globalThis.document.createElement("DIV");
    return t2.classList.add(this._CSS.wrapper, this._CSS.block), t2.contentEditable = "false", t2.dataset.placeholderActive = this.api.i18n.t(this._placeholder), this._data.text && (t2.innerHTML = this._data.text), this.readOnly || (t2.contentEditable = "true", t2.addEventListener("keyup", this.onKeyUp)), t2;
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
  merge(t2) {
    if (!this._element)
      return;
    this._data.text += t2.text;
    const e2 = l(t2.text);
    this._element.appendChild(e2), this._element.normalize();
  }
  /**
   * Validate Paragraph block data:
   * - check for emptiness
   *
   * @param {ParagraphData} savedData — data received after saving
   * @returns {boolean} false if saved data is not correct, otherwise true
   * @public
   */
  validate(t2) {
    return !(t2.text.trim() === "" && !this._preserveBlank);
  }
  /**
   * Extract Tool's data from the view
   *
   * @param {HTMLDivElement} toolsContent - Paragraph tools rendered view
   * @returns {ParagraphData} - saved data
   * @public
   */
  save(t2) {
    return {
      text: t2.innerHTML
    };
  }
  /**
   * On paste callback fired from Editor.
   *
   * @param {HTMLPasteEvent} event - event with pasted data
   */
  onPaste(t2) {
    const e2 = {
      text: t2.detail.data.innerHTML
    };
    this._data = e2, globalThis.window.requestAnimationFrame(() => {
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
      // to convert Paragraph to other block, use 'text' property of saved data
      import: "text"
      // to covert other block's exported string to Paragraph, fill 'text' property of tool data
    };
  }
  /**
   * Sanitizer rules
   * @returns {SanitizerConfig} - Edtior.js sanitizer config
   */
  static get sanitize() {
    return {
      text: {
        br: true
      }
    };
  }
  /**
   * Returns true to notify the core that read-only mode is supported
   *
   * @returns {boolean}
   */
  static get isReadOnlySupported() {
    return true;
  }
  /**
   * Used by Editor paste handling API.
   * Provides configuration to handle P tags.
   *
   * @returns {PasteConfig} - Paragraph Paste Setting
   */
  static get pasteConfig() {
    return {
      tags: ["P"]
    };
  }
  /**
   * Icon and title for displaying at the Toolbox
   *
   * @returns {ToolboxConfig} - Paragraph Toolbox Setting
   */
  static get toolbox() {
    return {
      icon: a$1,
      title: "Text"
    };
  }
};
class Paragraph extends n$1 {
  static async exportToMarkdown(data, tunes) {
    if (!data || !data.text) {
      return "";
    }
    let markdown = data.text.replace(/(&nbsp;| |\u00A0)+ */g, " ").split("<br>").join("  \n");
    markdown = MarkdownUtils.convertInlineHtmlToMarkdown(markdown);
    const formattedMarkdown = await MarkdownUtils.formatMarkdownWithPrettier(markdown);
    return MarkdownUtils.addAttributes(formattedMarkdown, tunes);
  }
  static importFromMarkdown(editor, markdown) {
    const result = MarkdownUtils.parseTunesFromMarkdown(markdown);
    let tunes = result.tunes;
    let markdownWithoutTunes = result.markdown;
    markdownWithoutTunes = markdownWithoutTunes.split("\n").join("<br>").replace(/<br>$/, "");
    markdownWithoutTunes = MarkdownUtils.convertInlineMarkdownToHtml(markdownWithoutTunes);
    const block = editor.blocks.insert("paragraph");
    editor.blocks.update(
      block.id,
      {
        text: markdownWithoutTunes
      },
      tunes
    );
  }
  // TODO : à revoir pour voir qui est le défault, raw ou paragraph
  static isItMarkdownExported(markdown) {
    const trimmed = markdown.trim();
    const isProbablyNotMarkdown = /^(<|{|-->|#})/.test(trimmed);
    return !isProbablyNotMarkdown;
  }
}
(function() {
  try {
    if (typeof globalThis.document < "u") {
      var e2 = globalThis.document.createElement("style");
      e2.appendChild(globalThis.document.createTextNode('.cdx-nested-list{margin:0;padding:0;outline:none;counter-reset:item;list-style:none}.cdx-nested-list__item{line-height:1.6em;display:flex;margin:2px 0}.cdx-nested-list__item [contenteditable]{outline:none}.cdx-nested-list__item-body{flex-grow:2}.cdx-nested-list__item-content,.cdx-nested-list__item-children{flex-basis:100%}.cdx-nested-list__item-content{word-break:break-word;white-space:pre-wrap}.cdx-nested-list__item:before{counter-increment:item;margin-right:5px;white-space:nowrap}.cdx-nested-list--ordered>.cdx-nested-list__item:before{content:counters(item,".") ". "}.cdx-nested-list--unordered>.cdx-nested-list__item:before{content:"•"}.cdx-nested-list__settings{display:flex}.cdx-nested-list__settings .cdx-settings-button{width:50%}')), globalThis.document.head.appendChild(e2);
    }
  } catch (t2) {
    console.error("vite-plugin-css-injected-by-js", t2);
  }
})();
function c$2(d) {
  return d.nodeType === Node.ELEMENT_NODE;
}
function p$1(d, e2 = null, t2) {
  const r2 = globalThis.document.createElement(d);
  Array.isArray(e2) ? r2.classList.add(...e2) : e2 && r2.classList.add(e2);
  for (const n3 in t2)
    r2[n3] = t2[n3];
  return r2;
}
function g$2(d) {
  const e2 = p$1("div");
  return e2.appendChild(d), e2.innerHTML;
}
function C$2(d) {
  let e2;
  return d.nodeType !== Node.ELEMENT_NODE ? e2 = d.textContent : (e2 = d.innerHTML, e2 = e2.replaceAll("<br>", "")), (e2 == null ? void 0 : e2.trim().length) === 0;
}
class u {
  /**
   * Store internal properties
   */
  constructor() {
    this.savedFakeCaret = void 0;
  }
  /**
   * Saves caret position using hidden <span>
   *
   * @returns {void}
   */
  save() {
    const e2 = u.range, t2 = p$1("span");
    t2.hidden = true, e2 && (e2.insertNode(t2), this.savedFakeCaret = t2);
  }
  /**
   * Restores the caret position saved by the save() method
   *
   * @returns {void}
   */
  restore() {
    if (!this.savedFakeCaret)
      return;
    const e2 = globalThis.window.getSelection();
    if (!e2)
      return;
    const t2 = new Range();
    t2.setStartAfter(this.savedFakeCaret), t2.setEndAfter(this.savedFakeCaret), e2.removeAllRanges(), e2.addRange(t2), setTimeout(() => {
      var r2;
      (r2 = this.savedFakeCaret) == null || r2.remove();
    }, 150);
  }
  /**
   * Returns the first range
   *
   * @returns {Range|null}
   */
  static get range() {
    const e2 = globalThis.window.getSelection();
    return e2 && e2.rangeCount ? e2.getRangeAt(0) : null;
  }
  /**
   * Extract content fragment from Caret position to the end of contenteditable element
   *
   * @returns {DocumentFragment|void}
   */
  static extractFragmentFromCaretPositionTillTheEnd() {
    const e2 = globalThis.window.getSelection();
    if (!e2 || !e2.rangeCount)
      return;
    const t2 = e2.getRangeAt(0);
    let r2 = t2.startContainer;
    if (r2.nodeType !== Node.ELEMENT_NODE) {
      if (!r2.parentNode)
        return;
      r2 = r2.parentNode;
    }
    if (!c$2(r2))
      return;
    const n3 = r2.closest("[contenteditable]");
    if (!n3)
      return;
    t2.deleteContents();
    const s2 = t2.cloneRange();
    return s2.selectNodeContents(n3), s2.setStart(t2.endContainer, t2.endOffset), s2.extractContents();
  }
  /**
   * Set focus to contenteditable or native input element
   *
   * @param {HTMLElement} element - element where to set focus
   * @param {boolean} atStart - where to set focus: at the start or at the end
   * @returns {void}
   */
  static focus(e2, t2 = true) {
    const r2 = globalThis.document.createRange(), n3 = globalThis.window.getSelection();
    n3 && (r2.selectNodeContents(e2), r2.collapse(t2), n3.removeAllRanges(), n3.addRange(r2));
  }
  /**
   * Check if the caret placed at the start of the contenteditable element
   *
   * @returns {boolean}
   */
  static isAtStart() {
    const e2 = globalThis.window.getSelection();
    if (!e2 || e2.focusOffset > 0)
      return false;
    const t2 = e2.focusNode;
    return !t2 || !c$2(t2) ? false : u.getHigherLevelSiblings(t2, "left").every((s2) => C$2(s2));
  }
  /**
   * Get all first-level (first child of [contenteditabel]) siblings from passed node
   * Then you can check it for emptiness
   *
   * @example
   * <div contenteditable>
   * <p></p>                            |
   * <p></p>                            | left first-level siblings
   * <p></p>                            |
   * <blockquote><a><b>adaddad</b><a><blockquote>       <-- passed node for example <b>
   * <p></p>                            |
   * <p></p>                            | right first-level siblings
   * <p></p>                            |
   * </div>
   * @param {HTMLElement} from - element from which siblings should be searched
   * @param {'left' | 'right'} direction - direction of search
   * @returns {HTMLElement[]}
   */
  static getHigherLevelSiblings(e2, t2 = "left") {
    let r2 = e2;
    const n3 = [];
    for (; r2.parentNode && r2.parentNode.contentEditable !== "true"; )
      r2 = r2.parentNode;
    const s2 = t2 === "left" ? "previousSibling" : "nextSibling";
    for (; r2[s2]; )
      r2 = r2[s2], n3.push(r2);
    return n3;
  }
}
const w$2 = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><line x1="9" x2="19" y1="7" y2="7" stroke="currentColor" stroke-linecap="round" stroke-width="2"/><line x1="9" x2="19" y1="12" y2="12" stroke="currentColor" stroke-linecap="round" stroke-width="2"/><line x1="9" x2="19" y1="17" y2="17" stroke="currentColor" stroke-linecap="round" stroke-width="2"/><path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M5.00001 17H4.99002"/><path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M5.00001 12H4.99002"/><path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M5.00001 7H4.99002"/></svg>', S$2 = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><line x1="12" x2="19" y1="7" y2="7" stroke="currentColor" stroke-linecap="round" stroke-width="2"/><line x1="12" x2="19" y1="12" y2="12" stroke="currentColor" stroke-linecap="round" stroke-width="2"/><line x1="12" x2="19" y1="17" y2="17" stroke="currentColor" stroke-linecap="round" stroke-width="2"/><path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M7.79999 14L7.79999 7.2135C7.79999 7.12872 7.7011 7.0824 7.63597 7.13668L4.79999 9.5"/></svg>';
let f$1 = class f {
  /**
   * Notify core that read-only mode is supported
   *
   * @returns {boolean}
   */
  static get isReadOnlySupported() {
    return true;
  }
  /**
   * Allow to use native Enter behaviour
   *
   * @returns {boolean}
   * @public
   */
  static get enableLineBreaks() {
    return true;
  }
  /**
   * Get Tool toolbox settings
   * icon - Tool icon's SVG
   * title - title to show in toolbox
   *
   * @returns {ToolboxConfig}
   */
  static get toolbox() {
    return {
      icon: S$2,
      title: "List"
    };
  }
  /**
   * Render plugin`s main Element and fill it with saved data
   *
   * @param {object} params - tool constructor options
   * @param {ListData} params.data - previously saved data
   * @param {object} params.config - user config for Tool
   * @param {object} params.api - Editor.js API
   * @param {boolean} params.readOnly - read-only mode flag
   */
  constructor({ data: e2, config: t2, api: r2, readOnly: n3 }) {
    var i;
    this.nodes = {
      wrapper: null
    }, this.api = r2, this.readOnly = n3, this.config = t2, this.defaultListStyle = ((i = this.config) == null ? void 0 : i.defaultStyle) === "ordered" ? "ordered" : "unordered";
    const s2 = {
      style: this.defaultListStyle,
      items: []
    };
    this.data = e2 && Object.keys(e2).length ? e2 : s2, this.caret = new u();
  }
  /**
   * Returns list tag with items
   *
   * @returns {Element}
   * @public
   */
  render() {
    return this.nodes.wrapper = this.makeListWrapper(this.data.style, [
      this.CSS.baseBlock
    ]), this.data.items.length ? this.appendItems(this.data.items, this.nodes.wrapper) : this.appendItems(
      [
        {
          content: "",
          items: []
        }
      ],
      this.nodes.wrapper
    ), this.readOnly || this.nodes.wrapper.addEventListener(
      "keydown",
      (e2) => {
        switch (e2.key) {
          case "Enter":
            this.enterPressed(e2);
            break;
          case "Backspace":
            this.backspace(e2);
            break;
          case "Tab":
            e2.shiftKey ? this.shiftTab(e2) : this.addTab(e2);
            break;
        }
      },
      false
    ), this.nodes.wrapper;
  }
  /**
   * Creates Block Tune allowing to change the list style
   *
   * @public
   * @returns {Array}
   */
  renderSettings() {
    return [
      {
        name: "unordered",
        label: this.api.i18n.t("Unordered"),
        icon: w$2
      },
      {
        name: "ordered",
        label: this.api.i18n.t("Ordered"),
        icon: S$2
      }
    ].map((t2) => ({
      name: t2.name,
      icon: t2.icon,
      label: t2.label,
      isActive: this.data.style === t2.name,
      closeOnActivate: true,
      onActivate: () => {
        this.listStyle = t2.name;
      }
    }));
  }
  /**
   * On paste sanitzation config. Allow only tags that are allowed in the Tool.
   *
   * @returns {PasteConfig} - paste config.
   */
  static get pasteConfig() {
    return {
      tags: ["OL", "UL", "LI"]
    };
  }
  /**
   * On paste callback that is fired from Editor.
   *
   * @param {PasteEvent} event - event with pasted data
   */
  onPaste(e2) {
    const t2 = e2.detail.data;
    this.data = this.pasteHandler(t2);
    const r2 = this.nodes.wrapper;
    r2 && r2.parentNode && r2.parentNode.replaceChild(this.render(), r2);
  }
  /**
   * Handle UL, OL and LI tags paste and returns List data
   *
   * @param {HTMLUListElement|HTMLOListElement|HTMLLIElement} element
   * @returns {ListData}
   */
  pasteHandler(e2) {
    const { tagName: t2 } = e2;
    let r2 = "unordered", n3;
    switch (t2) {
      case "OL":
        r2 = "ordered", n3 = "ol";
        break;
      case "UL":
      case "LI":
        r2 = "unordered", n3 = "ul";
    }
    const s2 = {
      style: r2,
      items: []
    }, i = (l2) => Array.from(l2.querySelectorAll(":scope > li")).map((o2) => {
      var m3;
      const a2 = o2.querySelector(`:scope > ${n3}`), y2 = a2 ? i(a2) : [];
      return {
        content: ((m3 = o2 == null ? void 0 : o2.firstChild) == null ? void 0 : m3.textContent) || "",
        items: y2
      };
    });
    return s2.items = i(e2), s2;
  }
  /**
   * Renders children list
   *
   * @param {ListItem[]} items - items data to append
   * @param {Element} parentItem - where to append
   * @returns {void}
   */
  appendItems(e2, t2) {
    e2.forEach((r2) => {
      const n3 = this.createItem(r2.content, r2.items);
      t2.appendChild(n3);
    });
  }
  /**
   * Renders the single item
   *
   * @param {string} content - item content to render
   * @param {ListItem[]} [items] - children
   * @returns {Element}
   */
  createItem(e2, t2 = []) {
    const r2 = p$1("li", this.CSS.item), n3 = p$1("div", this.CSS.itemBody), s2 = p$1("div", this.CSS.itemContent, {
      innerHTML: e2,
      contentEditable: (!this.readOnly).toString()
    });
    return n3.appendChild(s2), r2.appendChild(n3), t2 && t2.length > 0 && this.addChildrenList(r2, t2), r2;
  }
  /**
   * Extracts tool's data from the DOM
   *
   * @returns {ListData}
   */
  save() {
    const e2 = (t2) => Array.from(
      t2.querySelectorAll(`:scope > .${this.CSS.item}`)
    ).map((n3) => {
      const s2 = n3.querySelector(`.${this.CSS.itemChildren}`), i = this.getItemContent(n3), l2 = s2 ? e2(s2) : [];
      return {
        content: i,
        items: l2
      };
    });
    return {
      style: this.data.style,
      items: this.nodes.wrapper ? e2(this.nodes.wrapper) : []
    };
  }
  /**
   * Append children list to passed item
   *
   * @param {Element} parentItem - item that should contain passed sub-items
   * @param {ListItem[]} items - sub items to append
   */
  addChildrenList(e2, t2) {
    const r2 = e2.querySelector(`.${this.CSS.itemBody}`), n3 = this.makeListWrapper(void 0, [
      this.CSS.itemChildren
    ]);
    this.appendItems(t2, n3), r2 && r2.appendChild(n3);
  }
  /**
   * Creates main <ul> or <ol> tag depended on style
   *
   * @param {string} [style] - 'ordered' or 'unordered'
   * @param {string[]} [classes] - additional classes to append
   * @returns {HTMLOListElement|HTMLUListElement}
   */
  makeListWrapper(e2 = this.listStyle, t2 = []) {
    const r2 = e2 === "ordered" ? "ol" : "ul", n3 = e2 === "ordered" ? this.CSS.wrapperOrdered : this.CSS.wrapperUnordered;
    return t2.push(n3), p$1(r2, [this.CSS.wrapper, ...t2]);
  }
  /**
   * Styles
   *
   * @returns {NestedListCssClasses} - CSS classes names by keys
   * @private
   */
  get CSS() {
    return {
      baseBlock: this.api.styles.block,
      wrapper: "cdx-nested-list",
      wrapperOrdered: "cdx-nested-list--ordered",
      wrapperUnordered: "cdx-nested-list--unordered",
      item: "cdx-nested-list__item",
      itemBody: "cdx-nested-list__item-body",
      itemContent: "cdx-nested-list__item-content",
      itemChildren: "cdx-nested-list__item-children",
      settingsWrapper: "cdx-nested-list__settings",
      settingsButton: this.api.styles.settingsButton,
      settingsButtonActive: this.api.styles.settingsButtonActive
    };
  }
  /**
   * Get list style name
   *
   * @returns {string}
   */
  get listStyle() {
    return this.data.style || this.defaultListStyle;
  }
  /**
   * Set list style
   *
   * @param {ListDataStyle} style - new style to set
   */
  set listStyle(e2) {
    if (!this.nodes || !this.nodes.wrapper)
      return;
    const t2 = Array.from(
      this.nodes.wrapper.querySelectorAll(`.${this.CSS.wrapper}`)
    );
    t2.push(this.nodes.wrapper), t2.forEach((r2) => {
      r2.classList.toggle(this.CSS.wrapperUnordered, e2 === "unordered"), r2.classList.toggle(this.CSS.wrapperOrdered, e2 === "ordered");
    }), this.data.style = e2;
  }
  /**
   * Returns current List item by the caret position
   *
   * @returns {Element}
   */
  get currentItem() {
    const e2 = globalThis.window.getSelection();
    if (!e2)
      return null;
    let t2 = e2.anchorNode;
    return !t2 || (c$2(t2) || (t2 = t2.parentNode), !t2) || !c$2(t2) ? null : t2.closest(`.${this.CSS.item}`);
  }
  /**
   * Handles Enter keypress
   *
   * @param {KeyboardEvent} event - keydown
   * @returns {void}
   */
  enterPressed(e2) {
    const t2 = this.currentItem;
    if (e2.stopPropagation(), e2.preventDefault(), e2.isComposing)
      return;
    const r2 = t2 ? this.getItemContent(t2).trim().length === 0 : true, n3 = (t2 == null ? void 0 : t2.parentNode) === this.nodes.wrapper, s2 = (t2 == null ? void 0 : t2.nextElementSibling) === null;
    if (n3 && s2 && r2) {
      this.getOutOfList();
      return;
    } else if (s2 && r2) {
      this.unshiftItem();
      return;
    }
    const i = u.extractFragmentFromCaretPositionTillTheEnd();
    if (!i)
      return;
    const l2 = g$2(i), h2 = t2 == null ? void 0 : t2.querySelector(
      `.${this.CSS.itemChildren}`
    ), o2 = this.createItem(l2, void 0);
    h2 && Array.from(h2.querySelectorAll(`.${this.CSS.item}`)).length > 0 ? h2.prepend(o2) : t2 == null || t2.after(o2), this.focusItem(o2);
  }
  /**
   * Decrease indentation of the current item
   *
   * @returns {void}
   */
  unshiftItem() {
    const e2 = this.currentItem;
    if (!e2 || !e2.parentNode || !c$2(e2.parentNode))
      return;
    const t2 = e2.parentNode.closest(`.${this.CSS.item}`);
    if (!t2)
      return;
    this.caret.save(), t2.after(e2), this.caret.restore();
    const r2 = t2.querySelector(
      `.${this.CSS.itemChildren}`
    );
    if (!r2)
      return;
    r2.children.length === 0 && r2.remove();
  }
  /**
   * Return the item content
   *
   * @param {Element} item - item wrapper (<li>)
   * @returns {string}
   */
  getItemContent(e2) {
    const t2 = e2.querySelector(`.${this.CSS.itemContent}`);
    return !t2 || C$2(t2) ? "" : t2.innerHTML;
  }
  /**
   * Sets focus to the item's content
   *
   * @param {Element} item - item (<li>) to select
   * @param {boolean} atStart - where to set focus: at the start or at the end
   * @returns {void}
   */
  focusItem(e2, t2 = true) {
    const r2 = e2.querySelector(
      `.${this.CSS.itemContent}`
    );
    r2 && u.focus(r2, t2);
  }
  /**
   * Get out from List Tool by Enter on the empty last item
   *
   * @returns {void}
   */
  getOutOfList() {
    var e2;
    (e2 = this.currentItem) == null || e2.remove(), this.api.blocks.insert(), this.api.caret.setToBlock(this.api.blocks.getCurrentBlockIndex());
  }
  /**
   * Handle backspace
   *
   * @param {KeyboardEvent} event - keydown
   */
  backspace(e2) {
    if (!u.isAtStart())
      return;
    e2.preventDefault();
    const t2 = this.currentItem;
    if (!t2)
      return;
    const r2 = t2.previousSibling;
    if (!t2.parentNode || !c$2(t2.parentNode))
      return;
    const n3 = t2.parentNode.closest(`.${this.CSS.item}`);
    if (!r2 && !n3 || r2 && !c$2(r2))
      return;
    e2.stopPropagation();
    let s2;
    if (r2) {
      const a2 = r2.querySelectorAll(
        `.${this.CSS.item}`
      );
      s2 = Array.from(a2).pop() || r2;
    } else
      s2 = n3;
    const i = u.extractFragmentFromCaretPositionTillTheEnd();
    if (!i)
      return;
    const l2 = g$2(i);
    if (!s2)
      return;
    const h2 = s2.querySelector(
      `.${this.CSS.itemContent}`
    );
    if (!h2)
      return;
    u.focus(h2, false), this.caret.save(), h2.insertAdjacentHTML("beforeend", l2);
    let o2 = t2.querySelectorAll(
      `.${this.CSS.itemChildren} > .${this.CSS.item}`
    );
    o2 = Array.from(o2), o2 = o2.filter((a2) => !a2.parentNode || !c$2(a2.parentNode) ? false : a2.parentNode.closest(`.${this.CSS.item}`) === t2), o2.reverse().forEach((a2) => {
      r2 ? s2.after(a2) : t2.after(a2);
    }), t2.remove(), this.caret.restore();
  }
  /**
   * Add indentation to current item
   *
   * @param {KeyboardEvent} event - keydown
   */
  addTab(e2) {
    e2.stopPropagation(), e2.preventDefault();
    const t2 = this.currentItem;
    if (!t2)
      return;
    const r2 = t2.previousSibling;
    if (!r2 || !c$2(r2) || !r2)
      return;
    const s2 = r2.querySelector(
      `.${this.CSS.itemChildren}`
    );
    if (this.caret.save(), s2)
      s2.appendChild(t2);
    else {
      const i = this.makeListWrapper(void 0, [
        this.CSS.itemChildren
      ]), l2 = r2.querySelector(`.${this.CSS.itemBody}`);
      i.appendChild(t2), l2 == null || l2.appendChild(i);
    }
    this.caret.restore();
  }
  /**
   * Reduce indentation for current item
   *
   * @param {KeyboardEvent} event - keydown
   * @returns {void}
   */
  shiftTab(e2) {
    e2.stopPropagation(), e2.preventDefault(), this.unshiftItem();
  }
  /**
   * Convert from list to text for conversionConfig
   *
   * @param {ListData} data
   * @returns {string}
   */
  static joinRecursive(e2) {
    return e2.items.map((t2) => `${t2.content} ${f.joinRecursive(t2)}`).join("");
  }
  /**
   * Convert from text to list with import and export list to text
   */
  static get conversionConfig() {
    return {
      export: (e2) => f.joinRecursive(e2),
      import: (e2) => ({
        items: [
          {
            content: e2,
            items: []
          }
        ],
        style: "unordered"
      })
    };
  }
};
const Icon$1 = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-code-square" viewBox="0 0 16 16">\n    <path d="M14 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1zM2 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2z"/>\n    <path d="M6.854 4.646a.5.5 0 0 1 0 .708L4.207 8l2.647 2.646a.5.5 0 0 1-.708.708l-3-3a.5.5 0 0 1 0-.708l3-3a.5.5 0 0 1 .708 0m2.292 0a.5.5 0 0 0 0 .708L11.793 8l-2.647 2.646a.5.5 0 0 0 .708.708l3-3a.5.5 0 0 0 0-.708l-3-3a.5.5 0 0 0-.708 0"/>\n</svg>';
class Logger {
  constructor() {
    this.level = 0;
    const isBrowser = typeof globalThis.window !== "undefined";
    if (isBrowser) {
      this.isProduction = false;
      this.level = 0;
    } else {
      this.isProduction = true;
      if (this.isProduction) {
        this.level = 3;
      } else {
        this.level = 0;
      }
    }
  }
  setLevel(level) {
    this.level = level;
  }
  debug(message, ...args) {
    if (this.level <= 0) {
      console.debug(`[DEBUG] ${message}`, ...args);
    }
  }
  info(message, ...args) {
    if (this.level <= 1) {
      console.info(`[INFO] ${message}`, ...args);
    }
  }
  warn(message, ...args) {
    if (this.level <= 2) {
      console.warn(`[WARN] ${message}`, ...args);
    }
  }
  error(message, ...args) {
    if (this.level <= 3) {
      console.error(`[ERROR] ${message}`, ...args);
    }
  }
  // Méthode pour logger les erreurs avec contexte
  logError(error, context, additionalInfo) {
    this.error(`Error in ${context}: ${error.message}`, {
      stack: error.stack,
      ...additionalInfo
    });
  }
}
const logger = new Logger();
class BaseTool {
  //protected nodes: Record<string, HTMLElement | null> = {}
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
  // abstract validate(): boolean
  //abstract exportToMarkdown(): string
  //abstract importFromMarkdown(editor: API, markdown: string): void
  //abstract isItMarkdownExported(markdown: string): boolean
}
const _Raw = class _Raw extends BaseTool {
  constructor({ data, api, readOnly }) {
    super({ data, api, readOnly });
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
        if (this.editorInstance && this.editorInstance.getValue() !== htmlValue) {
          this.editorInstance.setValue(htmlValue);
        }
      },
      configurable: true,
      enumerable: true
    });
  }
  static get toolbox() {
    return {
      icon: Icon$1,
      title: "Raw"
    };
  }
  instantiateEditor(editorElem) {
    const monaco = globalThis.window.monaco;
    const monacoHelper = globalThis.window.monacoHelper;
    if (!monaco || !monacoHelper) {
      throw new Error("monaco is not defined");
    }
    const htmlValue = this.initialHtmlValue || this.data.html || "";
    return monaco.editor.create(
      editorElem,
      // @ts-ignore
      {
        value: htmlValue,
        language: "twig",
        ...monacoHelper.defaultSettings
      }
    );
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
      if (!ready || !this.wrapper) {
        return;
      }
      try {
        this.editorInstance = this.instantiateEditor(editorElem);
        const monacoHelperInstance = new globalThis.window.monacoHelper(this.editorInstance);
        monacoHelperInstance.updateHeight(this.wrapper);
        this.editorInstance.onDidChangeModelContent(() => {
          monacoHelperInstance.updateHeight(this.wrapper);
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
    if (globalThis.window.monaco && globalThis.window.monacoHelper) {
      return true;
    }
    if (!_Raw.monacoLoaderPromise) {
      _Raw.monacoLoaderPromise = new Promise((resolve, reject) => {
        const script = globalThis.document.createElement("script");
        script.src = `${_Raw.MONACO_SCRIPT_URL}?v=${Date.now()}`;
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
    }
    try {
      await _Raw.monacoLoaderPromise;
    } catch (error) {
      _Raw.monacoLoaderPromise = null;
      console.error("Error loading Monaco script", error);
      return false;
    }
    return typeof globalThis.window.monaco !== "undefined" && typeof globalThis.window.monacoHelper !== "undefined";
  }
  save() {
    this.data.html = this.editorInstance?.getValue() || "";
    return this.data;
  }
  static get conversionConfig() {
    return {
      export: "html",
      // this property of tool data will be used as string to pass to other tool
      import: "html"
      // to this property imported string will be passed
    };
  }
  // @ts-ignore
  static exportToMarkdown(data, tunes) {
    if (!data || !data.html) {
      return "";
    }
    return data.html.replace(/\r\n/g, "\n").replace(/\n[ \t]+\n/g, "\n").replace(/\n{2,}/g, "\n").trim();
  }
  static importFromMarkdown(editor, markdown) {
    const block = editor.blocks.insert("raw");
    editor.blocks.update(
      block.id,
      {
        html: markdown
      },
      {}
    );
  }
  // @ts-ignore
  static isItMarkdownExported(markdown) {
    return true;
  }
};
_Raw.monacoLoaderPromise = null;
_Raw.MONACO_SCRIPT_URL = "/bundles/pushwordadmin/monaco/app.js";
_Raw.enableLineBreaks = true;
let Raw = _Raw;
class List extends f$1 {
  static async exportToMarkdown(data, tunes) {
    if (!data || !data.items) {
      return "";
    }
    const isOrdered = data.style === "ordered";
    let markdown = List._itemsToMarkdown(data.items, isOrdered, 0);
    const formattedMarkdown = await MarkdownUtils.formatMarkdownWithPrettier(markdown);
    return MarkdownUtils.addAttributes(formattedMarkdown, tunes);
  }
  static _itemsToMarkdown(items, isOrdered, depth) {
    if (!items || items.length === 0) {
      return "";
    }
    const indent = "  ".repeat(depth);
    let markdown = "";
    items.forEach((item, index) => {
      if (isOrdered) {
        markdown += `${indent}${index + 1}. ${item.content || item}
`;
      } else {
        markdown += `${indent}- ${item.content || item}
`;
      }
      if (item.items && item.items.length > 0) {
        markdown += List._itemsToMarkdown(item.items, isOrdered, depth + 1);
      }
    });
    markdown = MarkdownUtils.convertInlineHtmlToMarkdown(markdown);
    return markdown;
  }
  static importFromMarkdown(editor, markdown) {
    const result = MarkdownUtils.parseTunesFromMarkdown(markdown);
    let tunes = result.tunes;
    let markdownWithoutTunes = result.markdown;
    markdownWithoutTunes = MarkdownUtils.convertInlineMarkdownToHtml(markdownWithoutTunes);
    const lines = markdownWithoutTunes.split("\n");
    const rootItems = [];
    const stack = [
      { items: rootItems, depth: -1 }
    ];
    let currentItem = null;
    let isOrdered = null;
    for (const line of lines) {
      const trimmedLine = line.trim();
      if (!trimmedLine) {
        if (currentItem !== null) {
          currentItem.content += "<br>";
        }
        continue;
      }
      const orderedMatch = trimmedLine.match(/^(\d+)\.\s+(.*)/);
      const unorderedMatch = trimmedLine.match(/^[-*+]\s+(.*)/);
      if (!orderedMatch && !unorderedMatch) {
        if (currentItem === null) {
          throw new Error("isItMarkdownExported not worked as expected");
        }
        currentItem.content += "<br>" + trimmedLine;
        continue;
      }
      const isCurrentOrdered = orderedMatch !== null;
      const content = orderedMatch ? orderedMatch[2] : unorderedMatch[1];
      if (isOrdered === null) {
        isOrdered = isCurrentOrdered;
      } else if (isOrdered !== isCurrentOrdered) {
        return Raw.importFromMarkdown(editor, markdown);
      }
      const leadingSpaces = line.length - line.trimStart().length;
      const currentDepth = Math.floor(leadingSpaces / 2);
      currentItem = { content, items: [] };
      while (stack.length > 1 && stack[stack.length - 1].depth >= currentDepth) {
        stack.pop();
      }
      const parent = stack[stack.length - 1];
      if (!parent) {
        throw new Error("parent not found");
      }
      parent.items.push(currentItem);
      stack.push({ items: currentItem.items, depth: currentDepth });
    }
    const block = editor.blocks.insert("list");
    editor.blocks.update(
      block.id,
      {
        style: isOrdered ? "ordered" : "unordered",
        items: rootItems
      },
      tunes
    );
  }
  static isItMarkdownExported(markdown) {
    return markdown.trim().match(/^[-*+]\s/) !== null || markdown.trim().match(/^\d+\.\s/) !== null;
  }
}
(function() {
  try {
    if (typeof globalThis.document < "u") {
      var t2 = globalThis.document.createElement("style");
      t2.appendChild(globalThis.document.createTextNode(".cdx-quote-icon svg{transform:rotate(180deg)}.cdx-quote{margin:0}.cdx-quote__text{min-height:158px;margin-bottom:10px}.cdx-quote [contentEditable=true][data-placeholder]:before{position:absolute;content:attr(data-placeholder);color:#707684;font-weight:400;opacity:0}.cdx-quote [contentEditable=true][data-placeholder]:empty:before{opacity:1}.cdx-quote [contentEditable=true][data-placeholder]:empty:focus:before{opacity:0}.cdx-quote-settings{display:flex}.cdx-quote-settings .cdx-settings-button{width:50%}")), globalThis.document.head.appendChild(t2);
    }
  } catch (e2) {
    console.error("vite-plugin-css-injected-by-js", e2);
  }
})();
const De = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M18 7L6 7"/><path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M18 17H6"/><path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M16 12L8 12"/></svg>', He = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M17 7L5 7"/><path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M17 17H5"/><path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M13 12L5 12"/></svg>', Re = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 10.8182L9 10.8182C8.80222 10.8182 8.60888 10.7649 8.44443 10.665C8.27998 10.5651 8.15181 10.4231 8.07612 10.257C8.00043 10.0909 7.98063 9.90808 8.01922 9.73174C8.0578 9.55539 8.15304 9.39341 8.29289 9.26627C8.43275 9.13913 8.61093 9.05255 8.80491 9.01747C8.99889 8.98239 9.19996 9.00039 9.38268 9.0692C9.56541 9.13801 9.72159 9.25453 9.83147 9.40403C9.94135 9.55353 10 9.72929 10 9.90909L10 12.1818C10 12.664 9.78929 13.1265 9.41421 13.4675C9.03914 13.8084 8.53043 14 8 14"/><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 10.8182L15 10.8182C14.8022 10.8182 14.6089 10.7649 14.4444 10.665C14.28 10.5651 14.1518 10.4231 14.0761 10.257C14.0004 10.0909 13.9806 9.90808 14.0192 9.73174C14.0578 9.55539 14.153 9.39341 14.2929 9.26627C14.4327 9.13913 14.6109 9.05255 14.8049 9.01747C14.9989 8.98239 15.2 9.00039 15.3827 9.0692C15.5654 9.13801 15.7216 9.25453 15.8315 9.40403C15.9414 9.55353 16 9.72929 16 9.90909L16 12.1818C16 12.664 15.7893 13.1265 15.4142 13.4675C15.0391 13.8084 14.5304 14 14 14"/></svg>';
var b$1 = typeof globalThis < "u" ? globalThis : typeof globalThis.window < "u" ? globalThis.window : typeof global < "u" ? global : typeof self < "u" ? self : {};
function Fe(e2) {
  if (e2.__esModule)
    return e2;
  var t2 = e2.default;
  if (typeof t2 == "function") {
    var n3 = function r2() {
      return this instanceof r2 ? Reflect.construct(t2, arguments, this.constructor) : t2.apply(this, arguments);
    };
    n3.prototype = t2.prototype;
  } else
    n3 = {};
  return Object.defineProperty(n3, "__esModule", { value: true }), Object.keys(e2).forEach(function(r2) {
    var i = Object.getOwnPropertyDescriptor(e2, r2);
    Object.defineProperty(n3, r2, i.get ? i : {
      enumerable: true,
      get: function() {
        return e2[r2];
      }
    });
  }), n3;
}
var v$1 = {}, P = {}, j = {};
Object.defineProperty(j, "__esModule", { value: true });
j.allInputsSelector = We;
function We() {
  var e2 = ["text", "password", "email", "number", "search", "tel", "url"];
  return "[contenteditable=true], textarea, input:not([type]), " + e2.map(function(t2) {
    return 'input[type="'.concat(t2, '"]');
  }).join(", ");
}
(function(e2) {
  Object.defineProperty(e2, "__esModule", { value: true }), e2.allInputsSelector = void 0;
  var t2 = j;
  Object.defineProperty(e2, "allInputsSelector", { enumerable: true, get: function() {
    return t2.allInputsSelector;
  } });
})(P);
var c$1 = {}, T$1 = {};
Object.defineProperty(T$1, "__esModule", { value: true });
T$1.isNativeInput = Ue;
function Ue(e2) {
  var t2 = [
    "INPUT",
    "TEXTAREA"
  ];
  return e2 && e2.tagName ? t2.includes(e2.tagName) : false;
}
(function(e2) {
  Object.defineProperty(e2, "__esModule", { value: true }), e2.isNativeInput = void 0;
  var t2 = T$1;
  Object.defineProperty(e2, "isNativeInput", { enumerable: true, get: function() {
    return t2.isNativeInput;
  } });
})(c$1);
var ie = {}, C$1 = {};
Object.defineProperty(C$1, "__esModule", { value: true });
C$1.append = qe;
function qe(e2, t2) {
  Array.isArray(t2) ? t2.forEach(function(n3) {
    e2.appendChild(n3);
  }) : e2.appendChild(t2);
}
(function(e2) {
  Object.defineProperty(e2, "__esModule", { value: true }), e2.append = void 0;
  var t2 = C$1;
  Object.defineProperty(e2, "append", { enumerable: true, get: function() {
    return t2.append;
  } });
})(ie);
var L$1 = {}, S$1 = {};
Object.defineProperty(S$1, "__esModule", { value: true });
S$1.blockElements = ze;
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
(function(e2) {
  Object.defineProperty(e2, "__esModule", { value: true }), e2.blockElements = void 0;
  var t2 = S$1;
  Object.defineProperty(e2, "blockElements", { enumerable: true, get: function() {
    return t2.blockElements;
  } });
})(L$1);
var ae = {}, M$1 = {};
Object.defineProperty(M$1, "__esModule", { value: true });
M$1.calculateBaseline = Ge;
function Ge(e2) {
  var t2 = globalThis.window.getComputedStyle(e2), n3 = parseFloat(t2.fontSize), r2 = parseFloat(t2.lineHeight) || n3 * 1.2, i = parseFloat(t2.paddingTop), a2 = parseFloat(t2.borderTopWidth), l2 = parseFloat(t2.marginTop), u2 = n3 * 0.8, d = (r2 - n3) / 2, s2 = l2 + a2 + i + d + u2;
  return s2;
}
(function(e2) {
  Object.defineProperty(e2, "__esModule", { value: true }), e2.calculateBaseline = void 0;
  var t2 = M$1;
  Object.defineProperty(e2, "calculateBaseline", { enumerable: true, get: function() {
    return t2.calculateBaseline;
  } });
})(ae);
var le = {}, k$1 = {}, w$1 = {}, N = {};
Object.defineProperty(N, "__esModule", { value: true });
N.isContentEditable = Ke;
function Ke(e2) {
  return e2.contentEditable === "true";
}
(function(e2) {
  Object.defineProperty(e2, "__esModule", { value: true }), e2.isContentEditable = void 0;
  var t2 = N;
  Object.defineProperty(e2, "isContentEditable", { enumerable: true, get: function() {
    return t2.isContentEditable;
  } });
})(w$1);
Object.defineProperty(k$1, "__esModule", { value: true });
k$1.canSetCaret = Qe;
var Xe = c$1, Ye2 = w$1;
function Qe(e2) {
  var t2 = true;
  if ((0, Xe.isNativeInput)(e2))
    switch (e2.type) {
      case "file":
      case "checkbox":
      case "radio":
      case "hidden":
      case "submit":
      case "button":
      case "image":
      case "reset":
        t2 = false;
        break;
    }
  else
    t2 = (0, Ye2.isContentEditable)(e2);
  return t2;
}
(function(e2) {
  Object.defineProperty(e2, "__esModule", { value: true }), e2.canSetCaret = void 0;
  var t2 = k$1;
  Object.defineProperty(e2, "canSetCaret", { enumerable: true, get: function() {
    return t2.canSetCaret;
  } });
})(le);
var y$1 = {}, I = {};
function Ve(e2, t2, n3) {
  const r2 = n3.value !== void 0 ? "value" : "get", i = n3[r2], a2 = `#${t2}Cache`;
  if (n3[r2] = function(...l2) {
    return this[a2] === void 0 && (this[a2] = i.apply(this, l2)), this[a2];
  }, r2 === "get" && n3.set) {
    const l2 = n3.set;
    n3.set = function(u2) {
      delete e2[a2], l2.apply(this, u2);
    };
  }
  return n3;
}
function ue() {
  const e2 = {
    win: false,
    mac: false,
    x11: false,
    linux: false
  }, t2 = Object.keys(e2).find((n3) => globalThis.window.navigator.appVersion.toLowerCase().indexOf(n3) !== -1);
  return t2 !== void 0 && (e2[t2] = true), e2;
}
function A$1(e2) {
  return e2 != null && e2 !== "" && (typeof e2 != "object" || Object.keys(e2).length > 0);
}
function Ze(e2) {
  return !A$1(e2);
}
const Je = () => typeof globalThis.window < "u" && globalThis.window.navigator !== null && A$1(globalThis.window.navigator.platform) && (/iP(ad|hone|od)/.test(globalThis.window.navigator.platform) || globalThis.window.navigator.platform === "MacIntel" && globalThis.window.navigator.maxTouchPoints > 1);
function xe(e2) {
  const t2 = ue();
  return e2 = e2.replace(/shift/gi, "⇧").replace(/backspace/gi, "⌫").replace(/enter/gi, "⏎").replace(/up/gi, "↑").replace(/left/gi, "→").replace(/down/gi, "↓").replace(/right/gi, "←").replace(/escape/gi, "⎋").replace(/insert/gi, "Ins").replace(/delete/gi, "␡").replace(/\+/gi, "+"), t2.mac ? e2 = e2.replace(/ctrl|cmd/gi, "⌘").replace(/alt/gi, "⌥") : e2 = e2.replace(/cmd/gi, "Ctrl").replace(/windows/gi, "WIN"), e2;
}
function et(e2) {
  return e2[0].toUpperCase() + e2.slice(1);
}
function tt(e2) {
  const t2 = globalThis.document.createElement("div");
  t2.style.position = "absolute", t2.style.left = "-999px", t2.style.bottom = "-999px", t2.innerHTML = e2, globalThis.document.body.appendChild(t2);
  const n3 = globalThis.window.getSelection(), r2 = globalThis.document.createRange();
  if (r2.selectNode(t2), n3 === null)
    throw new Error("Cannot copy text to clipboard");
  n3.removeAllRanges(), n3.addRange(r2), globalThis.document.execCommand("copy"), globalThis.document.body.removeChild(t2);
}
function nt(e2, t2, n3) {
  let r2;
  return (...i) => {
    const a2 = this, l2 = () => {
      r2 = void 0, n3 !== true && e2.apply(a2, i);
    }, u2 = n3 === true && r2 !== void 0;
    globalThis.window.clearTimeout(r2), r2 = globalThis.window.setTimeout(l2, t2), u2 && e2.apply(a2, i);
  };
}
function o(e2) {
  return Object.prototype.toString.call(e2).match(/\s([a-zA-Z]+)/)[1].toLowerCase();
}
function rt(e2) {
  return o(e2) === "boolean";
}
function oe(e2) {
  return o(e2) === "function" || o(e2) === "asyncfunction";
}
function it(e2) {
  return oe(e2) && /^\s*class\s+/.test(e2.toString());
}
function at(e2) {
  return o(e2) === "number";
}
function g$1(e2) {
  return o(e2) === "object";
}
function lt2(e2) {
  return Promise.resolve(e2) === e2;
}
function ut(e2) {
  return o(e2) === "string";
}
function ot(e2) {
  return o(e2) === "undefined";
}
function O$1(e2, ...t2) {
  if (!t2.length)
    return e2;
  const n3 = t2.shift();
  if (g$1(e2) && g$1(n3))
    for (const r2 in n3)
      g$1(n3[r2]) ? (e2[r2] === void 0 && Object.assign(e2, { [r2]: {} }), O$1(e2[r2], n3[r2])) : Object.assign(e2, { [r2]: n3[r2] });
  return O$1(e2, ...t2);
}
function st(e2, t2, n3) {
  const r2 = `«${t2}» is deprecated and will be removed in the next major release. Please use the «${n3}» instead.`;
  e2 && console.warn(r2);
}
function ct(e2) {
  try {
    return new URL(e2).href;
  } catch {
  }
  return e2.substring(0, 2) === "//" ? globalThis.window.location.protocol + e2 : globalThis.window.location.origin + e2;
}
function dt2(e2) {
  return e2 > 47 && e2 < 58 || e2 === 32 || e2 === 13 || e2 === 229 || e2 > 64 && e2 < 91 || e2 > 95 && e2 < 112 || e2 > 185 && e2 < 193 || e2 > 218 && e2 < 223;
}
const ft2 = {
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
}, pt2 = {
  LEFT: 0,
  WHEEL: 1,
  RIGHT: 2,
  BACKWARD: 3,
  FORWARD: 4
};
class vt {
  constructor() {
    this.completed = Promise.resolve();
  }
  /**
   * Add new promise to queue
   * @param operation - promise should be added to queue
   */
  add(t2) {
    return new Promise((n3, r2) => {
      this.completed = this.completed.then(t2).then(n3).catch(r2);
    });
  }
}
function gt(e2, t2, n3 = void 0) {
  let r2, i, a2, l2 = null, u2 = 0;
  n3 || (n3 = {});
  const d = function() {
    u2 = n3.leading === false ? 0 : Date.now(), l2 = null, a2 = e2.apply(r2, i), l2 === null && (r2 = i = null);
  };
  return function() {
    const s2 = Date.now();
    !u2 && n3.leading === false && (u2 = s2);
    const f3 = t2 - (s2 - u2);
    return r2 = this, i = arguments, f3 <= 0 || f3 > t2 ? (l2 && (clearTimeout(l2), l2 = null), u2 = s2, a2 = e2.apply(r2, i), l2 === null && (r2 = i = null)) : !l2 && n3.trailing !== false && (l2 = setTimeout(d, f3)), a2;
  };
}
const mt2 = /* @__PURE__ */ Object.freeze(/* @__PURE__ */ Object.defineProperty({
  __proto__: null,
  PromiseQueue: vt,
  beautifyShortcut: xe,
  cacheable: Ve,
  capitalize: et,
  copyTextToClipboard: tt,
  debounce: nt,
  deepMerge: O$1,
  deprecationAssert: st,
  getUserOS: ue,
  getValidUrl: ct,
  isBoolean: rt,
  isClass: it,
  isEmpty: Ze,
  isFunction: oe,
  isIosDevice: Je,
  isNumber: at,
  isObject: g$1,
  isPrintableKey: dt2,
  isPromise: lt2,
  isString: ut,
  isUndefined: ot,
  keyCodes: ft2,
  mouseButtons: pt2,
  notEmpty: A$1,
  throttle: gt,
  typeOf: o
}, Symbol.toStringTag, { value: "Module" })), $ = /* @__PURE__ */ Fe(mt2);
Object.defineProperty(I, "__esModule", { value: true });
I.containsOnlyInlineElements = _t;
var bt = $, yt = L$1;
function _t(e2) {
  var t2;
  (0, bt.isString)(e2) ? (t2 = globalThis.document.createElement("div"), t2.innerHTML = e2) : t2 = e2;
  var n3 = function(r2) {
    return !(0, yt.blockElements)().includes(r2.tagName.toLowerCase()) && Array.from(r2.children).every(n3);
  };
  return Array.from(t2.children).every(n3);
}
(function(e2) {
  Object.defineProperty(e2, "__esModule", { value: true }), e2.containsOnlyInlineElements = void 0;
  var t2 = I;
  Object.defineProperty(e2, "containsOnlyInlineElements", { enumerable: true, get: function() {
    return t2.containsOnlyInlineElements;
  } });
})(y$1);
var se = {}, B$1 = {}, _ = {}, D = {};
Object.defineProperty(D, "__esModule", { value: true });
D.make = ht2;
function ht2(e2, t2, n3) {
  var r2;
  t2 === void 0 && (t2 = null), n3 === void 0 && (n3 = {});
  var i = globalThis.document.createElement(e2);
  if (Array.isArray(t2)) {
    var a2 = t2.filter(function(u2) {
      return u2 !== void 0;
    });
    (r2 = i.classList).add.apply(r2, a2);
  } else
    t2 !== null && i.classList.add(t2);
  for (var l2 in n3)
    Object.prototype.hasOwnProperty.call(n3, l2) && (i[l2] = n3[l2]);
  return i;
}
(function(e2) {
  Object.defineProperty(e2, "__esModule", { value: true }), e2.make = void 0;
  var t2 = D;
  Object.defineProperty(e2, "make", { enumerable: true, get: function() {
    return t2.make;
  } });
})(_);
Object.defineProperty(B$1, "__esModule", { value: true });
B$1.fragmentToString = Ot;
var Et2 = _;
function Ot(e2) {
  var t2 = (0, Et2.make)("div");
  return t2.appendChild(e2), t2.innerHTML;
}
(function(e2) {
  Object.defineProperty(e2, "__esModule", { value: true }), e2.fragmentToString = void 0;
  var t2 = B$1;
  Object.defineProperty(e2, "fragmentToString", { enumerable: true, get: function() {
    return t2.fragmentToString;
  } });
})(se);
var ce = {}, H$1 = {};
Object.defineProperty(H$1, "__esModule", { value: true });
H$1.getContentLength = jt;
var Pt = c$1;
function jt(e2) {
  var t2, n3;
  return (0, Pt.isNativeInput)(e2) ? e2.value.length : e2.nodeType === Node.TEXT_NODE ? e2.length : (n3 = (t2 = e2.textContent) === null || t2 === void 0 ? void 0 : t2.length) !== null && n3 !== void 0 ? n3 : 0;
}
(function(e2) {
  Object.defineProperty(e2, "__esModule", { value: true }), e2.getContentLength = void 0;
  var t2 = H$1;
  Object.defineProperty(e2, "getContentLength", { enumerable: true, get: function() {
    return t2.getContentLength;
  } });
})(ce);
var R$1 = {}, F$1 = {}, re = b$1 && b$1.__spreadArray || function(e2, t2, n3) {
  if (n3 || arguments.length === 2)
    for (var r2 = 0, i = t2.length, a2; r2 < i; r2++)
      (a2 || !(r2 in t2)) && (a2 || (a2 = Array.prototype.slice.call(t2, 0, r2)), a2[r2] = t2[r2]);
  return e2.concat(a2 || Array.prototype.slice.call(t2));
};
Object.defineProperty(F$1, "__esModule", { value: true });
F$1.getDeepestBlockElements = de;
var Tt = y$1;
function de(e2) {
  return (0, Tt.containsOnlyInlineElements)(e2) ? [e2] : Array.from(e2.children).reduce(function(t2, n3) {
    return re(re([], t2, true), de(n3), true);
  }, []);
}
(function(e2) {
  Object.defineProperty(e2, "__esModule", { value: true }), e2.getDeepestBlockElements = void 0;
  var t2 = F$1;
  Object.defineProperty(e2, "getDeepestBlockElements", { enumerable: true, get: function() {
    return t2.getDeepestBlockElements;
  } });
})(R$1);
var fe = {}, W = {}, h = {}, U = {};
Object.defineProperty(U, "__esModule", { value: true });
U.isLineBreakTag = Ct2;
function Ct2(e2) {
  return [
    "BR",
    "WBR"
  ].includes(e2.tagName);
}
(function(e2) {
  Object.defineProperty(e2, "__esModule", { value: true }), e2.isLineBreakTag = void 0;
  var t2 = U;
  Object.defineProperty(e2, "isLineBreakTag", { enumerable: true, get: function() {
    return t2.isLineBreakTag;
  } });
})(h);
var E$1 = {}, q = {};
Object.defineProperty(q, "__esModule", { value: true });
q.isSingleTag = Lt;
function Lt(e2) {
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
  ].includes(e2.tagName);
}
(function(e2) {
  Object.defineProperty(e2, "__esModule", { value: true }), e2.isSingleTag = void 0;
  var t2 = q;
  Object.defineProperty(e2, "isSingleTag", { enumerable: true, get: function() {
    return t2.isSingleTag;
  } });
})(E$1);
Object.defineProperty(W, "__esModule", { value: true });
W.getDeepestNode = pe;
var St = c$1, Mt = h, kt = E$1;
function pe(e2, t2) {
  t2 === void 0 && (t2 = false);
  var n3 = t2 ? "lastChild" : "firstChild", r2 = t2 ? "previousSibling" : "nextSibling";
  if (e2.nodeType === Node.ELEMENT_NODE && e2[n3]) {
    var i = e2[n3];
    if ((0, kt.isSingleTag)(i) && !(0, St.isNativeInput)(i) && !(0, Mt.isLineBreakTag)(i))
      if (i[r2])
        i = i[r2];
      else if (i.parentNode !== null && i.parentNode[r2])
        i = i.parentNode[r2];
      else
        return i.parentNode;
    return pe(i, t2);
  }
  return e2;
}
(function(e2) {
  Object.defineProperty(e2, "__esModule", { value: true }), e2.getDeepestNode = void 0;
  var t2 = W;
  Object.defineProperty(e2, "getDeepestNode", { enumerable: true, get: function() {
    return t2.getDeepestNode;
  } });
})(fe);
var ve = {}, z = {}, p = b$1 && b$1.__spreadArray || function(e2, t2, n3) {
  if (n3 || arguments.length === 2)
    for (var r2 = 0, i = t2.length, a2; r2 < i; r2++)
      (a2 || !(r2 in t2)) && (a2 || (a2 = Array.prototype.slice.call(t2, 0, r2)), a2[r2] = t2[r2]);
  return e2.concat(a2 || Array.prototype.slice.call(t2));
};
Object.defineProperty(z, "__esModule", { value: true });
z.findAllInputs = $t;
var wt = y$1, Nt = R$1, It = P, At = c$1;
function $t(e2) {
  return Array.from(e2.querySelectorAll((0, It.allInputsSelector)())).reduce(function(t2, n3) {
    return (0, At.isNativeInput)(n3) || (0, wt.containsOnlyInlineElements)(n3) ? p(p([], t2, true), [n3], false) : p(p([], t2, true), (0, Nt.getDeepestBlockElements)(n3), true);
  }, []);
}
(function(e2) {
  Object.defineProperty(e2, "__esModule", { value: true }), e2.findAllInputs = void 0;
  var t2 = z;
  Object.defineProperty(e2, "findAllInputs", { enumerable: true, get: function() {
    return t2.findAllInputs;
  } });
})(ve);
var ge = {}, G = {};
Object.defineProperty(G, "__esModule", { value: true });
G.isCollapsedWhitespaces = Bt;
function Bt(e2) {
  return !/[^\t\n\r ]/.test(e2);
}
(function(e2) {
  Object.defineProperty(e2, "__esModule", { value: true }), e2.isCollapsedWhitespaces = void 0;
  var t2 = G;
  Object.defineProperty(e2, "isCollapsedWhitespaces", { enumerable: true, get: function() {
    return t2.isCollapsedWhitespaces;
  } });
})(ge);
var K = {}, X = {};
Object.defineProperty(X, "__esModule", { value: true });
X.isElement = Ht;
var Dt = $;
function Ht(e2) {
  return (0, Dt.isNumber)(e2) ? false : !!e2 && !!e2.nodeType && e2.nodeType === Node.ELEMENT_NODE;
}
(function(e2) {
  Object.defineProperty(e2, "__esModule", { value: true }), e2.isElement = void 0;
  var t2 = X;
  Object.defineProperty(e2, "isElement", { enumerable: true, get: function() {
    return t2.isElement;
  } });
})(K);
var me = {}, Y = {}, Q = {}, V = {};
Object.defineProperty(V, "__esModule", { value: true });
V.isLeaf = Rt;
function Rt(e2) {
  return e2 === null ? false : e2.childNodes.length === 0;
}
(function(e2) {
  Object.defineProperty(e2, "__esModule", { value: true }), e2.isLeaf = void 0;
  var t2 = V;
  Object.defineProperty(e2, "isLeaf", { enumerable: true, get: function() {
    return t2.isLeaf;
  } });
})(Q);
var Z = {}, J = {};
Object.defineProperty(J, "__esModule", { value: true });
J.isNodeEmpty = zt;
var Ft = h, Wt2 = K, Ut = c$1, qt = E$1;
function zt(e2, t2) {
  var n3 = "";
  return (0, qt.isSingleTag)(e2) && !(0, Ft.isLineBreakTag)(e2) ? false : ((0, Wt2.isElement)(e2) && (0, Ut.isNativeInput)(e2) ? n3 = e2.value : e2.textContent !== null && (n3 = e2.textContent.replace("​", "")), t2 !== void 0 && (n3 = n3.replace(new RegExp(t2, "g"), "")), n3.trim().length === 0);
}
(function(e2) {
  Object.defineProperty(e2, "__esModule", { value: true }), e2.isNodeEmpty = void 0;
  var t2 = J;
  Object.defineProperty(e2, "isNodeEmpty", { enumerable: true, get: function() {
    return t2.isNodeEmpty;
  } });
})(Z);
Object.defineProperty(Y, "__esModule", { value: true });
Y.isEmpty = Xt2;
var Gt = Q, Kt = Z;
function Xt2(e2, t2) {
  e2.normalize();
  for (var n3 = [e2]; n3.length > 0; ) {
    var r2 = n3.shift();
    if (r2) {
      if (e2 = r2, (0, Gt.isLeaf)(e2) && !(0, Kt.isNodeEmpty)(e2, t2))
        return false;
      n3.push.apply(n3, Array.from(e2.childNodes));
    }
  }
  return true;
}
(function(e2) {
  Object.defineProperty(e2, "__esModule", { value: true }), e2.isEmpty = void 0;
  var t2 = Y;
  Object.defineProperty(e2, "isEmpty", { enumerable: true, get: function() {
    return t2.isEmpty;
  } });
})(me);
var be = {}, x$1 = {};
Object.defineProperty(x$1, "__esModule", { value: true });
x$1.isFragment = Qt;
var Yt2 = $;
function Qt(e2) {
  return (0, Yt2.isNumber)(e2) ? false : !!e2 && !!e2.nodeType && e2.nodeType === Node.DOCUMENT_FRAGMENT_NODE;
}
(function(e2) {
  Object.defineProperty(e2, "__esModule", { value: true }), e2.isFragment = void 0;
  var t2 = x$1;
  Object.defineProperty(e2, "isFragment", { enumerable: true, get: function() {
    return t2.isFragment;
  } });
})(be);
var ye = {}, ee = {};
Object.defineProperty(ee, "__esModule", { value: true });
ee.isHTMLString = Zt;
var Vt = _;
function Zt(e2) {
  var t2 = (0, Vt.make)("div");
  return t2.innerHTML = e2, t2.childElementCount > 0;
}
(function(e2) {
  Object.defineProperty(e2, "__esModule", { value: true }), e2.isHTMLString = void 0;
  var t2 = ee;
  Object.defineProperty(e2, "isHTMLString", { enumerable: true, get: function() {
    return t2.isHTMLString;
  } });
})(ye);
var _e = {}, te = {};
Object.defineProperty(te, "__esModule", { value: true });
te.offset = Jt;
function Jt(e2) {
  var t2 = e2.getBoundingClientRect(), n3 = globalThis.window.pageXOffset || globalThis.document.documentElement.scrollLeft, r2 = globalThis.window.pageYOffset || globalThis.document.documentElement.scrollTop, i = t2.top + r2, a2 = t2.left + n3;
  return {
    top: i,
    left: a2,
    bottom: i + t2.height,
    right: a2 + t2.width
  };
}
(function(e2) {
  Object.defineProperty(e2, "__esModule", { value: true }), e2.offset = void 0;
  var t2 = te;
  Object.defineProperty(e2, "offset", { enumerable: true, get: function() {
    return t2.offset;
  } });
})(_e);
var he = {}, ne = {};
Object.defineProperty(ne, "__esModule", { value: true });
ne.prepend = xt;
function xt(e2, t2) {
  Array.isArray(t2) ? (t2 = t2.reverse(), t2.forEach(function(n3) {
    return e2.prepend(n3);
  })) : e2.prepend(t2);
}
(function(e2) {
  Object.defineProperty(e2, "__esModule", { value: true }), e2.prepend = void 0;
  var t2 = ne;
  Object.defineProperty(e2, "prepend", { enumerable: true, get: function() {
    return t2.prepend;
  } });
})(he);
(function(e2) {
  Object.defineProperty(e2, "__esModule", { value: true }), e2.prepend = e2.offset = e2.make = e2.isLineBreakTag = e2.isSingleTag = e2.isNodeEmpty = e2.isLeaf = e2.isHTMLString = e2.isFragment = e2.isEmpty = e2.isElement = e2.isContentEditable = e2.isCollapsedWhitespaces = e2.findAllInputs = e2.isNativeInput = e2.allInputsSelector = e2.getDeepestNode = e2.getDeepestBlockElements = e2.getContentLength = e2.fragmentToString = e2.containsOnlyInlineElements = e2.canSetCaret = e2.calculateBaseline = e2.blockElements = e2.append = void 0;
  var t2 = P;
  Object.defineProperty(e2, "allInputsSelector", { enumerable: true, get: function() {
    return t2.allInputsSelector;
  } });
  var n3 = c$1;
  Object.defineProperty(e2, "isNativeInput", { enumerable: true, get: function() {
    return n3.isNativeInput;
  } });
  var r2 = ie;
  Object.defineProperty(e2, "append", { enumerable: true, get: function() {
    return r2.append;
  } });
  var i = L$1;
  Object.defineProperty(e2, "blockElements", { enumerable: true, get: function() {
    return i.blockElements;
  } });
  var a2 = ae;
  Object.defineProperty(e2, "calculateBaseline", { enumerable: true, get: function() {
    return a2.calculateBaseline;
  } });
  var l2 = le;
  Object.defineProperty(e2, "canSetCaret", { enumerable: true, get: function() {
    return l2.canSetCaret;
  } });
  var u2 = y$1;
  Object.defineProperty(e2, "containsOnlyInlineElements", { enumerable: true, get: function() {
    return u2.containsOnlyInlineElements;
  } });
  var d = se;
  Object.defineProperty(e2, "fragmentToString", { enumerable: true, get: function() {
    return d.fragmentToString;
  } });
  var s2 = ce;
  Object.defineProperty(e2, "getContentLength", { enumerable: true, get: function() {
    return s2.getContentLength;
  } });
  var f3 = R$1;
  Object.defineProperty(e2, "getDeepestBlockElements", { enumerable: true, get: function() {
    return f3.getDeepestBlockElements;
  } });
  var Oe2 = fe;
  Object.defineProperty(e2, "getDeepestNode", { enumerable: true, get: function() {
    return Oe2.getDeepestNode;
  } });
  var Pe2 = ve;
  Object.defineProperty(e2, "findAllInputs", { enumerable: true, get: function() {
    return Pe2.findAllInputs;
  } });
  var je2 = ge;
  Object.defineProperty(e2, "isCollapsedWhitespaces", { enumerable: true, get: function() {
    return je2.isCollapsedWhitespaces;
  } });
  var Te2 = w$1;
  Object.defineProperty(e2, "isContentEditable", { enumerable: true, get: function() {
    return Te2.isContentEditable;
  } });
  var Ce2 = K;
  Object.defineProperty(e2, "isElement", { enumerable: true, get: function() {
    return Ce2.isElement;
  } });
  var Le2 = me;
  Object.defineProperty(e2, "isEmpty", { enumerable: true, get: function() {
    return Le2.isEmpty;
  } });
  var Se2 = be;
  Object.defineProperty(e2, "isFragment", { enumerable: true, get: function() {
    return Se2.isFragment;
  } });
  var Me2 = ye;
  Object.defineProperty(e2, "isHTMLString", { enumerable: true, get: function() {
    return Me2.isHTMLString;
  } });
  var ke2 = Q;
  Object.defineProperty(e2, "isLeaf", { enumerable: true, get: function() {
    return ke2.isLeaf;
  } });
  var we2 = Z;
  Object.defineProperty(e2, "isNodeEmpty", { enumerable: true, get: function() {
    return we2.isNodeEmpty;
  } });
  var Ne2 = h;
  Object.defineProperty(e2, "isLineBreakTag", { enumerable: true, get: function() {
    return Ne2.isLineBreakTag;
  } });
  var Ie2 = E$1;
  Object.defineProperty(e2, "isSingleTag", { enumerable: true, get: function() {
    return Ie2.isSingleTag;
  } });
  var Ae2 = _;
  Object.defineProperty(e2, "make", { enumerable: true, get: function() {
    return Ae2.make;
  } });
  var $e = _e;
  Object.defineProperty(e2, "offset", { enumerable: true, get: function() {
    return $e.offset;
  } });
  var Be = he;
  Object.defineProperty(e2, "prepend", { enumerable: true, get: function() {
    return Be.prepend;
  } });
})(v$1);
var Ee = /* @__PURE__ */ ((e2) => (e2.Left = "left", e2.Center = "center", e2))(Ee || {});
let m$1 = class m {
  /**
   * Render plugin`s main Element and fill it with saved data
   * @param params - Quote Tool constructor params
   * @param params.data - previously saved data
   * @param params.config - user config for Tool
   * @param params.api - editor.js api
   * @param params.readOnly - read only mode flag
   */
  constructor({ data: t2, config: n3, api: r2, readOnly: i, block: a2 }) {
    const { DEFAULT_ALIGNMENT: l2 } = m;
    this.api = r2, this.readOnly = i, this.quotePlaceholder = r2.i18n.t((n3 == null ? void 0 : n3.quotePlaceholder) ?? m.DEFAULT_QUOTE_PLACEHOLDER), this.captionPlaceholder = r2.i18n.t((n3 == null ? void 0 : n3.captionPlaceholder) ?? m.DEFAULT_CAPTION_PLACEHOLDER), this.data = {
      text: t2.text || "",
      caption: t2.caption || "",
      alignment: Object.values(Ee).includes(t2.alignment) ? t2.alignment : (n3 == null ? void 0 : n3.defaultAlignment) ?? l2
    }, this.css = {
      baseClass: this.api.styles.block,
      wrapper: "cdx-quote",
      text: "cdx-quote__text",
      input: this.api.styles.input,
      caption: "cdx-quote__caption"
    }, this.block = a2;
  }
  /**
   * Notify core that read-only mode is supported
   * @returns true
   */
  static get isReadOnlySupported() {
    return true;
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
    return true;
  }
  /**
   * Allow to press Enter inside the Quote
   * @returns true
   */
  static get enableLineBreaks() {
    return true;
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
      export: function(t2) {
        return t2.caption ? `${t2.text} — ${t2.caption}` : t2.text;
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
    return [
      {
        name: "left",
        icon: He
      },
      {
        name: "center",
        icon: De
      }
    ];
  }
  /**
   * Create Quote Tool container with inputs
   * @returns blockquote DOM element - Quote Tool container
   */
  render() {
    const t2 = v$1.make("blockquote", [
      this.css.baseClass,
      this.css.wrapper
    ]), n3 = v$1.make("div", [this.css.input, this.css.text], {
      contentEditable: !this.readOnly,
      innerHTML: this.data.text
    }), r2 = v$1.make("div", [this.css.input, this.css.caption], {
      contentEditable: !this.readOnly,
      innerHTML: this.data.caption
    });
    return n3.dataset.placeholder = this.quotePlaceholder, r2.dataset.placeholder = this.captionPlaceholder, t2.appendChild(n3), t2.appendChild(r2), t2;
  }
  /**
   * Extract Quote data from Quote Tool element
   * @param quoteElement - Quote DOM element to save
   * @returns Quote data object
   */
  save(t2) {
    const n3 = t2.querySelector(`.${this.css.text}`), r2 = t2.querySelector(`.${this.css.caption}`);
    return Object.assign(this.data, {
      text: (n3 == null ? void 0 : n3.innerHTML) ?? "",
      caption: (r2 == null ? void 0 : r2.innerHTML) ?? ""
    });
  }
  /**
   * Sanitizer rules
   * @returns sanitizer rules
   */
  static get sanitize() {
    return {
      text: {
        br: true
      },
      caption: {
        br: true
      },
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
    const t2 = (n3) => n3 && n3[0].toUpperCase() + n3.slice(1);
    return this.settings.map((n3) => ({
      icon: n3.icon,
      label: this.api.i18n.t(`Align ${t2(n3.name)}`),
      onActivate: () => this._toggleTune(n3.name),
      isActive: this.data.alignment === n3.name,
      closeOnActivate: true
    }));
  }
  /**
   * Toggle quote`s alignment
   * @param tune - alignment
   */
  _toggleTune(t2) {
    this.data.alignment = t2, this.block.dispatchChange();
  }
};
class Quote extends m$1 {
  static exportToMarkdown(data, tunes) {
    if (!data || !data.text) {
      return "";
    }
    let markdown = "";
    const lines = data.text.split(/<br\s*\/?>/gi);
    for (const line of lines) {
      markdown += `> ${line.trim()}
`;
    }
    if (data.caption) {
      markdown += `> — <cite>${data.caption}</cite>`;
    }
    return MarkdownUtils.addAttributes(markdown, tunes);
  }
  static importFromMarkdown(editor, markdown) {
    const result = MarkdownUtils.parseTunesFromMarkdown(markdown);
    let tunes = result.tunes;
    let markdownWithoutTunes = result.markdown;
    const lines = markdownWithoutTunes.split("\n");
    let caption = "";
    let quoteText = "";
    let inQuote = true;
    for (const line of lines) {
      if (line.trim().match(/^>\s*(—|-)/) || !inQuote) {
        inQuote = false;
        caption += line.trim().replace(/^>\s*(—|-)\s*(<cite>)?/, "").replace(/<\/cite>\s*$/, "");
        continue;
      }
      if (line.trim().startsWith(">")) {
        quoteText += line.trim().replace(/^>\s?/, "") + "<br>";
      }
    }
    caption = caption.trim();
    quoteText = quoteText.replace(/<br>$/, "").trim();
    const block = editor.blocks.insert("quote");
    editor.blocks.update(
      block.id,
      {
        text: quoteText,
        caption
      },
      tunes
    );
  }
  static isItMarkdownExported(markdown) {
    return markdown.startsWith("> ");
  }
}
const Icon = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-braces" viewBox="0 0 16 16">\n    <path d="M2.114 8.063V7.9c1.005-.102 1.497-.615 1.497-1.6V4.503c0-1.094.39-1.538 1.354-1.538h.273V2h-.376C3.25 2 2.49 2.759 2.49 4.352v1.524c0 1.094-.376 1.456-1.49 1.456v1.299c1.114 0 1.49.362 1.49 1.456v1.524c0 1.593.759 2.352 2.372 2.352h.376v-.964h-.273c-.964 0-1.354-.444-1.354-1.538V9.663c0-.984-.492-1.497-1.497-1.6M13.886 7.9v.163c-1.005.103-1.497.616-1.497 1.6v1.798c0 1.094-.39 1.538-1.354 1.538h-.273v.964h.376c1.613 0 2.372-.759 2.372-2.352v-1.524c0-1.094.376-1.456 1.49-1.456V7.332c-1.114 0-1.49-.362-1.49-1.456V4.352C13.51 2.759 12.75 2 11.138 2h-.376v.964h.273c.964 0 1.354.444 1.354 1.538V6.3c0 .984.492 1.497 1.497 1.6"/>\n</svg>\n\n';
class make {
  static element(tagName, classNames = null, attributes = {}, innerHTML = "", onclick = null) {
    const el = globalThis.document.createElement(tagName);
    if (Array.isArray(classNames)) {
      el.classList.add(...classNames);
    } else if (classNames) {
      el.classList.add(classNames);
    }
    for (const attrName in attributes) {
      el.setAttribute(attrName, attributes[attrName]);
    }
    if (innerHTML !== "") {
      el.innerHTML = innerHTML;
    }
    if (onclick) {
      el.addEventListener("click", onclick);
    }
    return el;
  }
  static input(Tool, classNames, placeholder, value = "") {
    const input = make.element("div", classNames, {
      contentEditable: !Tool.readOnly
    });
    input.dataset.placeholder = Tool.api.i18n.t(placeholder);
    if (value) {
      input.textContent = value;
    }
    return input;
  }
  static option(select, key, value = null, attributes = {}, selectedValue = null) {
    const option = globalThis.document.createElement("option");
    option.text = value || key;
    option.value = key;
    for (const attrName in attributes) {
      option.setAttribute(attrName, attributes[attrName]);
    }
    if (selectedValue !== null && selectedValue === value) {
      option.selected = true;
    }
    select.add(option);
  }
  static options(select, options, selectedValue = null) {
    options.forEach((option) => make.option(select, option, null, {}, selectedValue));
  }
  static switchInput(name, labelText, checked = false) {
    const wrapper = make.element("div", "editor-switch");
    const checkbox = make.element("input", null, {
      type: "checkbox",
      id: name
    });
    const switchElement = make.element("label", "label-default", {
      for: name
    });
    const label = make.element("label", "", { for: name });
    label.innerHTML = labelText;
    wrapper.append(checkbox, switchElement, label);
    if (checked) {
      checkbox.checked = checked;
    }
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
}
class CodeBlock extends Raw {
  //public static readonly toolName = 'codeBlock'
  constructor({
    data,
    api,
    readOnly
  }) {
    super({ data, api, readOnly });
    this._codeBlockData = { html: "", language: "html" };
    this._codeBlockData = {
      html: data?.html || "",
      language: data?.language || "html"
    };
    Object.defineProperty(this, "data", {
      get: () => this._codeBlockData,
      set: (newData) => {
        const html = newData?.html || "";
        const language = newData?.language || this._codeBlockData.language || "html";
        this._codeBlockData = { html, language };
        if (this.editorInstance && this.editorInstance.getValue() !== html) {
          this.editorInstance.setValue(html);
        }
      },
      configurable: true,
      enumerable: true
    });
  }
  render() {
    const wrapper = super.render();
    const select = make.element("select", this.api.styles.input, {
      style: "max-width: 100px;padding: 5px 6px;margin: auto; position: absolute; right: 5px; z-index: 5; background: white"
    });
    make.options(select, ["html", "twig", "javascript", "php", "json", "yaml"]);
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
    let html = "";
    try {
      html = this.editorInstance.getValue();
    } catch (error) {
      console.error(error);
    }
    this._codeBlockData.html = html;
    return this._codeBlockData;
  }
  static get toolbox() {
    return {
      icon: Icon,
      title: "Code"
    };
  }
  /**
   * Export block data to Markdown
   * @param {CodeBlockData} data - Block data
   * @param {BlockTuneData} tunes - Block tunes
   * @returns {string} Markdown representation
   */
  // @ts-ignore
  static exportToMarkdown(data, tunes) {
    if (!data || !data.html) {
      return "";
    }
    const language = data.language || "";
    return `\`\`\`${language}
${data.html}
\`\`\``;
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
      if (i === lines.length - 1) {
        break;
      }
      html += lines[i] + "\n";
      i++;
    }
    const block = editor.blocks.insert("codeBlock");
    editor.blocks.update(
      block.id,
      {
        html: html.trim(),
        language: language || "html"
      },
      tunes
    );
  }
  static isItMarkdownExported(markdown) {
    return markdown.trim().startsWith("```") && markdown.trim().endsWith("```");
  }
}
const SelectIcon = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">  <path d="M1 3.5A1.5 1.5 0 0 1 2.5 2h2.764c.958 0 1.76.56 2.311 1.184C7.985 3.648 8.48 4 9 4h4.5A1.5 1.5 0 0 1 15 5.5v.64c.57.265.94.876.856 1.546l-.64 5.124A2.5 2.5 0 0 1 12.733 15H3.266a2.5 2.5 0 0 1-2.481-2.19l-.64-5.124A1.5 1.5 0 0 1 1 6.14V3.5zM2 6h12v-.5a.5.5 0 0 0-.5-.5H9c-.964 0-1.71-.629-2.174-1.154C6.374 3.334 5.82 3 5.264 3H2.5a.5.5 0 0 0-.5.5V6zm-.367 1a.5.5 0 0 0-.496.562l.64 5.124A1.5 1.5 0 0 0 3.266 14h9.468a1.5 1.5 0 0 0 1.489-1.314l.64-5.124A.5.5 0 0 0 14.367 7H1.633z"/></svg>\n';
const UploadIcon = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">  <path d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z"/>  <path d="M7.646 1.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1-.708.708L8.5 2.707V11.5a.5.5 0 0 1-1 0V2.707L5.354 4.854a.5.5 0 1 1-.708-.708l3-3z"/></svg>\n';
const STATUS = {
  EMPTY: "empty",
  UPLOADING: "loading",
  FILLED: "filled"
};
class AbstractMediaTool extends BaseTool {
  // protected uploader: Uploader
  constructor({
    api,
    config,
    readOnly,
    data
  }) {
    super({ data, api, readOnly });
    this.config = config;
    this.onSelectFile = config.onSelectFile;
    this.onUploadFile = config.onUploadFile;
    this.nodes = {
      wrapper: make.element("div", [
        this.api.styles.block,
        "image-tool"
      ]),
      fileButton: this.createFileButton(),
      preloader: make.element("div", "image-tool__image-preloader")
    };
  }
  responsIsValid(response) {
    return response.success && response.file && response.file.media;
  }
  onFileLoading() {
    this.toggleStatus(STATUS.UPLOADING);
  }
  handleUploadError(error) {
    const toolName = this.constructor.name;
    logger.error(`${toolName}: uploading failed`, error);
    this.hidePreloader();
    this.api.notifier.show({
      message: this.api.i18n.t("Échec du téléchargement de l'image. Veuillez réessayer."),
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
    if (status === STATUS.UPLOADING) {
      wrapperElement.classList.add(this.api.styles.loader);
    } else {
      wrapperElement.classList.remove(this.api.styles.loader);
    }
    for (const statusValue of Object.values(STATUS)) {
      wrapperElement.classList.toggle(
        `${baseClass}--${statusValue}`,
        status === statusValue
      );
    }
  }
  createFileButton() {
    const buttonWrapper = make.element("div", [
      "flex",
      "cdx-input-labeled-preview",
      "cdx-input-labeled",
      "cdx-input",
      "cdx-input-editable",
      "cdx-input-gallery"
    ]);
    const selectButton = make.element("div", [this.api.styles.button]);
    selectButton.innerHTML = SelectIcon + " " + this.api.i18n.t("Select");
    selectButton.addEventListener("click", (event) => {
      console.log("Select button clicked");
      this.onSelectFile(this, event);
    });
    buttonWrapper.appendChild(selectButton);
    const uploadButton = make.element("div", [this.api.styles.button]);
    uploadButton.innerHTML = `${UploadIcon} ${this.api.i18n.t("Upload")}`;
    uploadButton.style.marginLeft = "-2px";
    uploadButton.addEventListener("click", (event) => {
      console.log("Upload button clicked");
      this.onUploadFile(this, event);
    });
    buttonWrapper.appendChild(uploadButton);
    return buttonWrapper;
  }
}
class MediaUtils {
  /**
   * Extrait le nom du fichier média depuis une URL
   * @param url - URL complète du média
   * @returns Le nom du fichier (dernière partie de l'URL après /)
   */
  static extractMediaName(url) {
    if (!url) return "";
    const urlParts = url.split("/");
    return urlParts[urlParts.length - 1] || "";
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
  static buildFullUrl(mediaNameOrUrl, basePath = "/media/") {
    if (this.isFullUrl(mediaNameOrUrl)) {
      return mediaNameOrUrl;
    }
    return `${basePath}${mediaNameOrUrl}`;
  }
  /**
   * Extrait le nom du média depuis un objet de données
   * @param dataItem - Objet de données qui peut contenir media, url, ou être une string
   * @returns Le nom du média
   */
  static getMediaNameFromData(dataItem) {
    if (typeof dataItem === "string") {
      return this.isFullUrl(dataItem) ? this.extractMediaName(dataItem) : dataItem;
    } else if (dataItem && typeof dataItem === "object" && dataItem.media) {
      return dataItem.media;
    } else if (dataItem && typeof dataItem === "object" && dataItem.fileName) {
      return dataItem.fileName;
    }
    return "";
  }
  /**
   * Construit l'URL complète depuis un objet de données
   * @param dataItem - Objet de données qui peut contenir media, fileName, url, ou être une string
   * @param basePath - Chemin de base pour les médias
   * @returns URL complète
   */
  static buildFullUrlFromData(dataItem, basePath = "/media/") {
    if (typeof dataItem === "string") {
      return this.buildFullUrl(dataItem, basePath);
    } else if (dataItem && typeof dataItem === "object" && dataItem.url) {
      return dataItem.url;
    } else if (dataItem && typeof dataItem === "object" && dataItem.fileName) {
      const mediaName = dataItem.fileName;
      return this.buildFullUrl(mediaName, basePath);
    } else if (dataItem && typeof dataItem === "object" && dataItem.media) {
      const mediaName = dataItem.media;
      return this.buildFullUrl(mediaName, basePath);
    }
    return "";
  }
}
class Image extends AbstractMediaTool {
  static get toolbox() {
    return {
      title: "Image",
      icon: _$2
    };
  }
  get media() {
    return this.data.media || this.data.file?.url || "";
  }
  constructor({
    data,
    config,
    api,
    readOnly = false
  }) {
    super({ api, config, readOnly, data });
    this.data = Image.normalizeData(data);
    this.nodes = {
      // @ts-ignore
      ...this.nodes,
      imageContainer: make.element("div", "image-tool__image"),
      caption: make.element("div", [this.api.styles.input, "image-tool__caption"], {
        contentEditable: !this.readOnly
      })
    };
  }
  static normalizeData(data) {
    return {
      media: data.media || MediaUtils.extractMediaName(data.file?.url || ""),
      caption: data.caption || data.file?.name || ""
    };
  }
  onUpload(response) {
    if (!this.responsIsValid(response)) {
      return this.handleUploadError("incorrect response: " + JSON.stringify(response));
    }
    this.data.media = response.file.media;
    if (!response.file.name) return;
    this.data.caption = response.file.name;
    this.fillImage();
  }
  fillImage() {
    if (this.nodes.imageEl) {
      this.nodes.imageEl.remove();
    }
    const img = make.element("img", "image-tool__image-picture");
    img.src = MediaUtils.buildFullUrl(this.media);
    img.addEventListener("load", () => {
      this.hidePreloader(STATUS.FILLED);
    });
    this.nodes.imageEl = img;
    this.nodes.imageContainer.appendChild(img);
    this.fillCaption();
  }
  fillCaption() {
    this.nodes.caption.textContent = this.data.caption || "";
  }
  createImageInput() {
    this.nodes.caption.dataset.placeholder = this.api.i18n.t("Caption");
    this.nodes.imageContainer.appendChild(this.nodes.preloader);
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
    if (!this.media) {
      return { media: "", caption: "" };
    }
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
    if (!data.media) {
      return "";
    }
    const imgSrc = MediaUtils.buildFullUrl(data.media);
    let markdown = `![${data.caption || ""}](${imgSrc})`;
    if (tunes?.linkTune) {
      markdown = MarkdownUtils.wrapWithLink(markdown, tunes);
    }
    return tunes ? MarkdownUtils.addAttributes(markdown, tunes) : markdown;
  }
  static isItMarkdownExported(markdown) {
    return markdown.trim().match(/!\[.*\]\(.+\)/) !== null || markdown.trim().match(/#?\[!\[.*\]\(.+\)\]\(.+\)/) !== null;
  }
  static importFromMarkdown(editor, markdown) {
    let media = "";
    let caption = "";
    const result = MarkdownUtils.parseTunesFromMarkdown(markdown);
    let tunes = result.tunes;
    markdown = result.markdown;
    if (markdown.match(/#?\[!\[.*\]\(.+\)\]\(.+\)/)) {
      console.log("image with link");
      const imageAndLinkMatch = markdown.match(
        /(#?)\[!\[(.*)\]\((.*)\)]\((.*)\)({target="_blank"})?/
      );
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
    if (media.startsWith("/media/")) {
      media = MediaUtils.extractMediaName(media);
    }
    const block = editor.blocks.insert("image");
    editor.blocks.update(
      block.id,
      {
        media,
        caption
      },
      tunes
    );
  }
  static get pasteConfig() {
    return {
      tags: ["img"],
      patterns: {
        image: /(https?:\/\/|\/media\/)\S+\.(gif|jpe?g|png|webp)$/i
      }
      // not supported
      // files: {
      //   mimeTypes: ['image/*'],
      // },
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
    if (event.type === "file") ;
  }
}
const ToolboxIcon$1 = '<svg width="38" height="18" viewBox="0 0 38 18" xmlns="http://www.w3.org/2000/svg">\n    <mask id="mask0" mask-type="alpha" maskUnits="userSpaceOnUse" x="10" y="0" width="18" height="18">\n        <path fill-rule="evenodd" clip-rule="evenodd" d="M28 16V2C28 0.9 27.1 0 26 0H12C10.9 0 10 0.9 10 2V16C10 17.1 10.9 18 12 18H26C27.1 18 28 17.1 28 16V16ZM15.5 10.5L18 13.51L21.5 9L26 15H12L15.5 10.5V10.5Z" />\n    </mask>\n    <g mask="url(#mask0)">\n        <rect x="10" width="18" height="18" />\n    </g>\n    <mask id="mask1" mask-type="alpha" maskUnits="userSpaceOnUse" x="0" y="3" width="7" height="12">\n        <path fill-rule="evenodd" clip-rule="evenodd" d="M7 13.59L2.67341 9L7 4.41L5.66802 3L0 9L5.66802 15L7 13.59Z" fill="white" />\n    </mask>\n    <g mask="url(#mask1)">\n        <rect y="3" width="7.55735" height="12" />\n    </g>\n    <mask id="mask2" mask-type="alpha" maskUnits="userSpaceOnUse" x="31" y="3" width="7" height="12">\n        <path fill-rule="evenodd" clip-rule="evenodd" d="M31 13.59L35.3266 9L31 4.41L32.332 3L38 9L32.332 15L31 13.59Z" fill="white" />\n    </mask>\n    <g mask="url(#mask2)">\n        <rect x="30.4426" y="2.25" width="7.55735" height="13" />\n    </g>\n</svg>';
const CloseIcon = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"\n    class="bi bi-x-lg" viewBox="0 0 16 16">\n    <path\n        d="M2.146 2.854a.5.5 0 1 1 .708-.708L8 7.293l5.146-5.147a.5.5 0 0 1 .708.708L8.707 8l5.147 5.146a.5.5 0 0 1-.708.708L8 8.707l-5.146 5.147a.5.5 0 0 1-.708-.708L7.293 8 2.146 2.854Z" />\n</svg>';
const MoveLeftIcon = '<svg class="icon " viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg">\n    <path\n        d="M351,9a15,15 0 01 19,0l29,29a15,15 0 01 0,19l-199,199l199,199a15,15 0 01 0,19l-29,29a15,15 0 01-19,0l-236-235a16,16 0 01 0-24z" />\n</svg>';
const MoveRightIcon = '<svg viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg"><path d="M312,256l-199-199a15,15 0 01 0-19l29-29a15,15 0 01 19,0l236,235a16,16 0 01 0,24l-236,235a15,15 0 01-19,0l-29-29a15,15 0 01 0-19z" /></svg>\n';
class JSONRepairError extends Error {
  constructor(message, position) {
    super(`${message} at position ${position}`);
    this.position = position;
  }
}
const codeSpace = 32;
const codeNewline = 10;
const codeTab = 9;
const codeReturn = 13;
const codeNonBreakingSpace = 160;
const codeEnQuad = 8192;
const codeHairSpace = 8202;
const codeNarrowNoBreakSpace = 8239;
const codeMediumMathematicalSpace = 8287;
const codeIdeographicSpace = 12288;
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
const regexUrlStart = /^(http|https|ftp|mailto|file|data|irc):\/\/$/;
const regexUrlChar = /^[A-Za-z0-9-._~:/?#@!$&'()*+;=]$/;
function isUnquotedStringDelimiter(char) {
  return ",[]/{}\n+".includes(char);
}
function isStartOfValue(char) {
  return isQuote(char) || regexStartOfValue.test(char);
}
const regexStartOfValue = /^[[{\w-]$/;
function isControlCharacter(char) {
  return char === "\n" || char === "\r" || char === "	" || char === "\b" || char === "\f";
}
function isWhitespace(text, index) {
  const code = text.charCodeAt(index);
  return code === codeSpace || code === codeNewline || code === codeTab || code === codeReturn;
}
function isWhitespaceExceptNewline(text, index) {
  const code = text.charCodeAt(index);
  return code === codeSpace || code === codeTab || code === codeReturn;
}
function isSpecialWhitespace(text, index) {
  const code = text.charCodeAt(index);
  return code === codeNonBreakingSpace || code >= codeEnQuad && code <= codeHairSpace || code === codeNarrowNoBreakSpace || code === codeMediumMathematicalSpace || code === codeIdeographicSpace;
}
function isQuote(char) {
  return isDoubleQuoteLike(char) || isSingleQuoteLike(char);
}
function isDoubleQuoteLike(char) {
  return char === '"' || char === "“" || char === "”";
}
function isDoubleQuote(char) {
  return char === '"';
}
function isSingleQuoteLike(char) {
  return char === "'" || char === "‘" || char === "’" || char === "`" || char === "´";
}
function isSingleQuote(char) {
  return char === "'";
}
function stripLastOccurrence(text, textToStrip) {
  let stripRemainingText = arguments.length > 2 && arguments[2] !== void 0 ? arguments[2] : false;
  const index = text.lastIndexOf(textToStrip);
  return index !== -1 ? text.substring(0, index) + (stripRemainingText ? "" : text.substring(index + 1)) : text;
}
function insertBeforeLastWhitespace(text, textToInsert) {
  let index = text.length;
  if (!isWhitespace(text, index - 1)) {
    return text + textToInsert;
  }
  while (isWhitespace(text, index - 1)) {
    index--;
  }
  return text.substring(0, index) + textToInsert + text.substring(index);
}
function removeAtIndex(text, start, count) {
  return text.substring(0, start) + text.substring(start + count);
}
function endsWithCommaOrNewline(text) {
  return /[,\n][ \t\r]*$/.test(text);
}
const controlCharacters = {
  "\b": "\\b",
  "\f": "\\f",
  "\n": "\\n",
  "\r": "\\r",
  "	": "\\t"
};
const escapeCharacters = {
  '"': '"',
  "\\": "\\",
  "/": "/",
  b: "\b",
  f: "\f",
  n: "\n",
  r: "\r",
  t: "	"
  // note that \u is handled separately in parseString()
};
function jsonrepair(text) {
  let i = 0;
  let output = "";
  parseMarkdownCodeBlock(["```", "[```", "{```"]);
  const processed = parseValue();
  if (!processed) {
    throwUnexpectedEnd();
  }
  parseMarkdownCodeBlock(["```", "```]", "```}"]);
  const processedComma = parseCharacter(",");
  if (processedComma) {
    parseWhitespaceAndSkipComments();
  }
  if (isStartOfValue(text[i]) && endsWithCommaOrNewline(output)) {
    if (!processedComma) {
      output = insertBeforeLastWhitespace(output, ",");
    }
    parseNewlineDelimitedJSON();
  } else if (processedComma) {
    output = stripLastOccurrence(output, ",");
  }
  while (text[i] === "}" || text[i] === "]") {
    i++;
    parseWhitespaceAndSkipComments();
  }
  if (i >= text.length) {
    return output;
  }
  throwUnexpectedCharacter();
  function parseValue() {
    parseWhitespaceAndSkipComments();
    const processed2 = parseObject() || parseArray() || parseString() || parseNumber() || parseKeywords() || parseUnquotedString(false) || parseRegex();
    parseWhitespaceAndSkipComments();
    return processed2;
  }
  function parseWhitespaceAndSkipComments() {
    let skipNewline = arguments.length > 0 && arguments[0] !== void 0 ? arguments[0] : true;
    const start = i;
    let changed = parseWhitespace(skipNewline);
    do {
      changed = parseComment();
      if (changed) {
        changed = parseWhitespace(skipNewline);
      }
    } while (changed);
    return i > start;
  }
  function parseWhitespace(skipNewline) {
    const _isWhiteSpace = skipNewline ? isWhitespace : isWhitespaceExceptNewline;
    let whitespace = "";
    while (true) {
      if (_isWhiteSpace(text, i)) {
        whitespace += text[i];
        i++;
      } else if (isSpecialWhitespace(text, i)) {
        whitespace += " ";
        i++;
      } else {
        break;
      }
    }
    if (whitespace.length > 0) {
      output += whitespace;
      return true;
    }
    return false;
  }
  function parseComment() {
    if (text[i] === "/" && text[i + 1] === "*") {
      while (i < text.length && !atEndOfBlockComment(text, i)) {
        i++;
      }
      i += 2;
      return true;
    }
    if (text[i] === "/" && text[i + 1] === "/") {
      while (i < text.length && text[i] !== "\n") {
        i++;
      }
      return true;
    }
    return false;
  }
  function parseMarkdownCodeBlock(blocks) {
    if (skipMarkdownCodeBlock(blocks)) {
      if (isFunctionNameCharStart(text[i])) {
        while (i < text.length && isFunctionNameChar(text[i])) {
          i++;
        }
      }
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
  function parseObject() {
    if (text[i] === "{") {
      output += "{";
      i++;
      parseWhitespaceAndSkipComments();
      if (skipCharacter(",")) {
        parseWhitespaceAndSkipComments();
      }
      let initial = true;
      while (i < text.length && text[i] !== "}") {
        let processedComma2;
        if (!initial) {
          processedComma2 = parseCharacter(",");
          if (!processedComma2) {
            output = insertBeforeLastWhitespace(output, ",");
          }
          parseWhitespaceAndSkipComments();
        } else {
          processedComma2 = true;
          initial = false;
        }
        skipEllipsis();
        const processedKey = parseString() || parseUnquotedString(true);
        if (!processedKey) {
          if (text[i] === "}" || text[i] === "{" || text[i] === "]" || text[i] === "[" || text[i] === void 0) {
            output = stripLastOccurrence(output, ",");
          } else {
            throwObjectKeyExpected();
          }
          break;
        }
        parseWhitespaceAndSkipComments();
        const processedColon = parseCharacter(":");
        const truncatedText = i >= text.length;
        if (!processedColon) {
          if (isStartOfValue(text[i]) || truncatedText) {
            output = insertBeforeLastWhitespace(output, ":");
          } else {
            throwColonExpected();
          }
        }
        const processedValue = parseValue();
        if (!processedValue) {
          if (processedColon || truncatedText) {
            output += "null";
          } else {
            throwColonExpected();
          }
        }
      }
      if (text[i] === "}") {
        output += "}";
        i++;
      } else {
        output = insertBeforeLastWhitespace(output, "}");
      }
      return true;
    }
    return false;
  }
  function parseArray() {
    if (text[i] === "[") {
      output += "[";
      i++;
      parseWhitespaceAndSkipComments();
      if (skipCharacter(",")) {
        parseWhitespaceAndSkipComments();
      }
      let initial = true;
      while (i < text.length && text[i] !== "]") {
        if (!initial) {
          const processedComma2 = parseCharacter(",");
          if (!processedComma2) {
            output = insertBeforeLastWhitespace(output, ",");
          }
        } else {
          initial = false;
        }
        skipEllipsis();
        const processedValue = parseValue();
        if (!processedValue) {
          output = stripLastOccurrence(output, ",");
          break;
        }
      }
      if (text[i] === "]") {
        output += "]";
        i++;
      } else {
        output = insertBeforeLastWhitespace(output, "]");
      }
      return true;
    }
    return false;
  }
  function parseNewlineDelimitedJSON() {
    let initial = true;
    let processedValue = true;
    while (processedValue) {
      if (!initial) {
        const processedComma2 = parseCharacter(",");
        if (!processedComma2) {
          output = insertBeforeLastWhitespace(output, ",");
        }
      } else {
        initial = false;
      }
      processedValue = parseValue();
    }
    if (!processedValue) {
      output = stripLastOccurrence(output, ",");
    }
    output = `[
${output}
]`;
  }
  function parseString() {
    let stopAtDelimiter = arguments.length > 0 && arguments[0] !== void 0 ? arguments[0] : false;
    let stopAtIndex = arguments.length > 1 && arguments[1] !== void 0 ? arguments[1] : -1;
    let skipEscapeChars = text[i] === "\\";
    if (skipEscapeChars) {
      i++;
      skipEscapeChars = true;
    }
    if (isQuote(text[i])) {
      const isEndQuote = isDoubleQuote(text[i]) ? isDoubleQuote : isSingleQuote(text[i]) ? isSingleQuote : isSingleQuoteLike(text[i]) ? isSingleQuoteLike : isDoubleQuoteLike;
      const iBefore = i;
      const oBefore = output.length;
      let str = '"';
      i++;
      while (true) {
        if (i >= text.length) {
          const iPrev = prevNonWhitespaceIndex(i - 1);
          if (!stopAtDelimiter && isDelimiter(text.charAt(iPrev))) {
            i = iBefore;
            output = output.substring(0, oBefore);
            return parseString(true);
          }
          str = insertBeforeLastWhitespace(str, '"');
          output += str;
          return true;
        }
        if (i === stopAtIndex) {
          str = insertBeforeLastWhitespace(str, '"');
          output += str;
          return true;
        }
        if (isEndQuote(text[i])) {
          const iQuote = i;
          const oQuote = str.length;
          str += '"';
          i++;
          output += str;
          parseWhitespaceAndSkipComments(false);
          if (stopAtDelimiter || i >= text.length || isDelimiter(text[i]) || isQuote(text[i]) || isDigit(text[i])) {
            parseConcatenatedString();
            return true;
          }
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
          i = iQuote + 1;
          str = `${str.substring(0, oQuote)}\\${str.substring(oQuote)}`;
        } else if (stopAtDelimiter && isUnquotedStringDelimiter(text[i])) {
          if (text[i - 1] === ":" && regexUrlStart.test(text.substring(iBefore + 1, i + 2))) {
            while (i < text.length && regexUrlChar.test(text[i])) {
              str += text[i];
              i++;
            }
          }
          str = insertBeforeLastWhitespace(str, '"');
          output += str;
          parseConcatenatedString();
          return true;
        } else if (text[i] === "\\") {
          const char = text.charAt(i + 1);
          const escapeChar = escapeCharacters[char];
          if (escapeChar !== void 0) {
            str += text.slice(i, i + 2);
            i += 2;
          } else if (char === "u") {
            let j2 = 2;
            while (j2 < 6 && isHex(text[i + j2])) {
              j2++;
            }
            if (j2 === 6) {
              str += text.slice(i, i + 6);
              i += 6;
            } else if (i + j2 >= text.length) {
              i = text.length;
            } else {
              throwInvalidUnicodeCharacter();
            }
          } else {
            str += char;
            i += 2;
          }
        } else {
          const char = text.charAt(i);
          if (char === '"' && text[i - 1] !== "\\") {
            str += `\\${char}`;
            i++;
          } else if (isControlCharacter(char)) {
            str += controlCharacters[char];
            i++;
          } else {
            if (!isValidStringCharacter(char)) {
              throwInvalidCharacter(char);
            }
            str += char;
            i++;
          }
        }
        if (skipEscapeChars) {
          skipEscapeCharacter();
        }
      }
    }
    return false;
  }
  function parseConcatenatedString() {
    let processed2 = false;
    parseWhitespaceAndSkipComments();
    while (text[i] === "+") {
      processed2 = true;
      i++;
      parseWhitespaceAndSkipComments();
      output = stripLastOccurrence(output, '"', true);
      const start = output.length;
      const parsedStr = parseString();
      if (parsedStr) {
        output = removeAtIndex(output, start, 1);
      } else {
        output = insertBeforeLastWhitespace(output, '"');
      }
    }
    return processed2;
  }
  function parseNumber() {
    const start = i;
    if (text[i] === "-") {
      i++;
      if (atEndOfNumber()) {
        repairNumberEndingWithNumericSymbol(start);
        return true;
      }
      if (!isDigit(text[i])) {
        i = start;
        return false;
      }
    }
    while (isDigit(text[i])) {
      i++;
    }
    if (text[i] === ".") {
      i++;
      if (atEndOfNumber()) {
        repairNumberEndingWithNumericSymbol(start);
        return true;
      }
      if (!isDigit(text[i])) {
        i = start;
        return false;
      }
      while (isDigit(text[i])) {
        i++;
      }
    }
    if (text[i] === "e" || text[i] === "E") {
      i++;
      if (text[i] === "-" || text[i] === "+") {
        i++;
      }
      if (atEndOfNumber()) {
        repairNumberEndingWithNumericSymbol(start);
        return true;
      }
      if (!isDigit(text[i])) {
        i = start;
        return false;
      }
      while (isDigit(text[i])) {
        i++;
      }
    }
    if (!atEndOfNumber()) {
      i = start;
      return false;
    }
    if (i > start) {
      const num = text.slice(start, i);
      const hasInvalidLeadingZero = /^0\d/.test(num);
      output += hasInvalidLeadingZero ? `"${num}"` : num;
      return true;
    }
    return false;
  }
  function parseKeywords() {
    return parseKeyword("true", "true") || parseKeyword("false", "false") || parseKeyword("null", "null") || // repair Python keywords True, False, None
    parseKeyword("True", "true") || parseKeyword("False", "false") || parseKeyword("None", "null");
  }
  function parseKeyword(name, value) {
    if (text.slice(i, i + name.length) === name) {
      output += value;
      i += name.length;
      return true;
    }
    return false;
  }
  function parseUnquotedString(isKey) {
    const start = i;
    if (isFunctionNameCharStart(text[i])) {
      while (i < text.length && isFunctionNameChar(text[i])) {
        i++;
      }
      let j2 = i;
      while (isWhitespace(text, j2)) {
        j2++;
      }
      if (text[j2] === "(") {
        i = j2 + 1;
        parseValue();
        if (text[i] === ")") {
          i++;
          if (text[i] === ";") {
            i++;
          }
        }
        return true;
      }
    }
    while (i < text.length && !isUnquotedStringDelimiter(text[i]) && !isQuote(text[i]) && (!isKey || text[i] !== ":")) {
      i++;
    }
    if (text[i - 1] === ":" && regexUrlStart.test(text.substring(start, i + 2))) {
      while (i < text.length && regexUrlChar.test(text[i])) {
        i++;
      }
    }
    if (i > start) {
      while (isWhitespace(text, i - 1) && i > 0) {
        i--;
      }
      const symbol = text.slice(start, i);
      output += symbol === "undefined" ? "null" : JSON.stringify(symbol);
      if (text[i] === '"') {
        i++;
      }
      return true;
    }
  }
  function parseRegex() {
    if (text[i] === "/") {
      const start = i;
      i++;
      while (i < text.length && (text[i] !== "/" || text[i - 1] === "\\")) {
        i++;
      }
      i++;
      output += `"${text.substring(start, i)}"`;
      return true;
    }
  }
  function prevNonWhitespaceIndex(start) {
    let prev = start;
    while (prev > 0 && isWhitespace(text, prev)) {
      prev--;
    }
    return prev;
  }
  function atEndOfNumber() {
    return i >= text.length || isDelimiter(text[i]) || isWhitespace(text, i);
  }
  function repairNumberEndingWithNumericSymbol(start) {
    output += `${text.slice(start, i)}0`;
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
    const chars = text.slice(i, i + 6);
    throw new JSONRepairError(`Invalid unicode character "${chars}"`, i);
  }
}
function atEndOfBlockComment(text, i) {
  return text[i] === "*" && text[i + 1] === "/";
}
class Gallery extends AbstractMediaTool {
  static get toolbox() {
    return {
      title: "Gallery",
      icon: ToolboxIcon$1
    };
  }
  constructor({
    data,
    config,
    api,
    readOnly
  }) {
    super({ api, config, readOnly, data });
    this.data = Gallery.normalizeData(data);
  }
  static normalizeData(data) {
    const normalizedItems = [];
    if (data && typeof data === "object" && "items" in data && Array.isArray(data.items)) {
      for (const item of data.items) {
        if (typeof item !== "object") continue;
        let media = item.media || (item.url ? MediaUtils.extractMediaName(item.url) : null) || item.file?.media;
        if (!media) continue;
        normalizedItems.push({ media, caption: item.caption || "" });
      }
      return { items: normalizedItems };
    }
    if (!data || !Array.isArray(data)) {
      return { items: [] };
    }
    for (const item of data) {
      if (typeof item === "string") {
        normalizedItems.push({ media: item, caption: "" });
      } else if (typeof item === "object" && item !== null) {
        let media = null;
        if ("media" in item && item.media) {
          media = item.media;
        } else if ("url" in item && item.url) {
          media = MediaUtils.extractMediaName(item.url);
        } else if ("file" in item && item.file && "media" in item.file) {
          media = item.file.media;
        }
        if (media) {
          normalizedItems.push({ media, caption: item.caption || "" });
        }
      }
    }
    return { items: normalizedItems };
  }
  onUpload(response) {
    if (!this.responsIsValid(response)) {
      return this.handleUploadError("incorrect response: " + JSON.stringify(response));
    }
    const mediaName = response.file.media || MediaUtils.extractMediaName(response.file.url);
    if (this.isMediaAlreadyInGallery(mediaName)) {
      this.handleDuplicateMediaError();
      return;
    }
    const itemElement = this.getLastGalleryItem();
    this._createImage(response.file.url, itemElement, response.file.name || "");
    this.data.items.push({
      media: mediaName,
      caption: response.file.name || ""
    });
    itemElement.classList.add("cdxcarousel-item--empty");
  }
  getLastGalleryItem() {
    if (!this.nodeList) {
      throw new Error("nodeLis must be defined (render)");
    }
    const lastItemIndex = this.nodeList.childNodes.length - 2;
    const lastItem = this.nodeList.childNodes[lastItemIndex];
    return lastItem.firstChild;
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
    const lastItem = this.getLastGalleryItem();
    const block = lastItem.closest(".cdxcarousel-block");
    if (block) {
      block.remove();
    }
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
    this.nodeList = make.element("div", ["cdxcarousel-list"]);
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
    const block = make.element("div", "cdxcarousel-block");
    const item = make.element("div", "cdxcarousel-item");
    const leftBtn = make.element(
      "div",
      "cdxcarousel-leftBtn",
      { style: "padding: 8px" },
      MoveLeftIcon,
      () => {
        const parent = block.parentNode;
        if (!parent) return;
        const index = Array.from(parent.children).indexOf(block);
        if (index !== 0) {
          const previousSibling = parent.children[index - 1];
          if (previousSibling) {
            parent.insertBefore(block, previousSibling);
          }
        }
      }
    );
    const rightBtn = make.element(
      "div",
      "cdxcarousel-rightBtn",
      { style: "padding: 8px" },
      MoveRightIcon,
      () => {
        const parent = block.parentNode;
        if (!parent) return;
        const index = Array.from(parent.children).indexOf(block);
        if (index !== parent.children.length - 2) {
          const nextNextSibling = parent.children[index + 2];
          if (nextNextSibling) {
            parent.insertBefore(block, nextNextSibling);
          }
        }
      }
    );
    const removeBtn = make.element(
      "div",
      "cdxcarousel-removeBtn",
      { display: "none" },
      CloseIcon,
      () => {
        block.remove();
      }
    );
    item.appendChild(removeBtn);
    item.appendChild(leftBtn);
    item.appendChild(rightBtn);
    block.appendChild(item);
    if (url) {
      this._createImage(url, item, caption);
    } else {
      const imagePreloader = make.element("div", "image-tool__image-preloader");
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
    const caption = make.element("div", ["image-tool__caption", this.api.styles.input], {
      contentEditable: true
    });
    if (captionText) {
      caption.textContent = captionText;
    }
    const placeholderText = this.api.i18n.t("Alternative text");
    caption.dataset.placeholder = placeholderText;
    const removeBtn = item.querySelector(".cdxcarousel-removeBtn");
    removeBtn.style.display = "flex";
    item.appendChild(image);
    item.appendChild(caption);
    item.style.setProperty("--bg-image-url", `url('${url}')`);
  }
  save() {
    if (!this.nodeList) {
      return this.data;
    }
    const newItems = [];
    const items = this.nodeList.querySelectorAll(".cdxcarousel-block");
    items.forEach((item) => {
      const image = item.querySelector("img");
      const caption = item.querySelector(".image-tool__caption");
      if (image && image.src) {
        const mediaName = MediaUtils.extractMediaName(image.src);
        const captionText = caption?.textContent?.trim() || "";
        newItems.push({ media: mediaName, caption: captionText });
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
    if (!data.items || data.items.length === 0) {
      return "";
    }
    const imagesObject = data.items.reduce(
      (acc, item) => {
        acc[item.media] = item.caption || "";
        return acc;
      },
      {}
    );
    const imagesArray = JSON.stringify(imagesObject);
    let markdown = `{{ gallery(${imagesArray}`;
    if (tunes?.clickableTune?.value) markdown += `, clickable: true`;
    markdown += `) }}`;
    return MarkdownUtils.addAttributes(markdown, tunes);
  }
  static importFromMarkdown(editor, markdown) {
    const result = MarkdownUtils.parseTunesFromMarkdown(markdown);
    let tunes = result.tunes;
    const markdownWithoutTunes = result.markdown;
    let galleryMatch = markdownWithoutTunes.match(
      /{{ gallery\(\s*(images:\s*)?(?<medias>\{.*?\})\s*(,\s*clickable:\s*(?<clickable>true|false))?\)\ }}/s
    );
    tunes.clickableTune = {
      value: [true, "true", "1"].includes(galleryMatch?.groups?.clickable || false) ? true : false
    };
    if (!galleryMatch || !Gallery.importGalleryFromJsonString(
      galleryMatch.groups?.medias || "{}",
      editor,
      tunes
    )) {
      return Raw.importFromMarkdown(editor, markdown);
    }
  }
  static parseGalleryData(jsonString) {
    try {
      return JSON.parse(jsonrepair(jsonString));
    } catch (e2) {
      return false;
    }
  }
  static importGalleryFromJsonString(jsonString, editor, tunes) {
    const galleryData = Gallery.parseGalleryData(jsonString);
    if (galleryData === false) {
      return false;
    }
    const galleryItems = Object.entries(galleryData).map(
      ([media, caption]) => ({
        caption: String(caption),
        media: String(media)
      })
    );
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
    return markdown.trim().match(
      /{{ gallery\(\s*(images:\s*)?\{.*?\}\s*(,\s*clickable:\s*(true|false|0|1))?\)\ }}/s
    ) !== null;
  }
}
(function() {
  var r2;
  try {
    if (typeof globalThis.document < "u") {
      var o2 = globalThis.document.createElement("style");
      o2.nonce = (r2 = globalThis.document.head.querySelector("meta[property=csp-nonce]")) == null ? void 0 : r2.content, o2.appendChild(globalThis.document.createTextNode('.tc-wrap{--color-background:#f9f9fb;--color-text-secondary:#7b7e89;--color-border:#e8e8eb;--cell-size:34px;--toolbox-icon-size:18px;--toolbox-padding:6px;--toolbox-aiming-field-size:calc(var(--toolbox-icon-size) + var(--toolbox-padding)*2);border-left:0;position:relative;height:100%;width:100%;margin-top:var(--toolbox-icon-size);box-sizing:border-box;display:grid;grid-template-columns:calc(100% - var(--cell-size)) var(--cell-size);z-index:0}.tc-wrap--readonly{grid-template-columns:100% var(--cell-size)}.tc-wrap svg{vertical-align:top}@media print{.tc-wrap{border-left-color:var(--color-border);border-left-style:solid;border-left-width:1px;grid-template-columns:100% var(--cell-size)}}@media print{.tc-wrap .tc-row:after{display:none}}.tc-table{position:relative;width:100%;height:100%;display:grid;font-size:14px;border-top:1px solid var(--color-border);line-height:1.4}.tc-table:after{width:calc(var(--cell-size));height:100%;left:calc(var(--cell-size)*-1);top:0}.tc-table:after,.tc-table:before{position:absolute;content:""}.tc-table:before{width:100%;height:var(--toolbox-aiming-field-size);top:calc(var(--toolbox-aiming-field-size)*-1);left:0}.tc-table--heading .tc-row:first-child{font-weight:600;border-bottom:2px solid var(--color-border);position:sticky;top:0;z-index:2;background:var(--color-background)}.tc-table--heading .tc-row:first-child [contenteditable]:empty:before{content:attr(heading);color:var(--color-text-secondary)}.tc-table--heading .tc-row:first-child:after{bottom:-2px;border-bottom:2px solid var(--color-border)}.tc-add-column,.tc-add-row{display:flex;color:var(--color-text-secondary)}@media print{.tc-add{display:none}}.tc-add-column{display:grid;border-top:1px solid var(--color-border);grid-template-columns:var(--cell-size);grid-auto-rows:var(--cell-size);place-items:center}.tc-add-column svg{padding:5px;position:sticky;top:0;background-color:var(--color-background)}.tc-add-column--disabled{visibility:hidden}@media print{.tc-add-column{display:none}}.tc-add-row{height:var(--cell-size);align-items:center;padding-left:4px;position:relative}.tc-add-row--disabled{display:none}.tc-add-row:before{content:"";position:absolute;right:calc(var(--cell-size)*-1);width:var(--cell-size);height:100%}@media print{.tc-add-row{display:none}}.tc-add-column,.tc-add-row{transition:0s;cursor:pointer;will-change:background-color}.tc-add-column:hover,.tc-add-row:hover{transition:background-color .1s ease;background-color:var(--color-background)}.tc-add-row{margin-top:1px}.tc-add-row:hover:before{transition:.1s;background-color:var(--color-background)}.tc-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(10px,1fr));position:relative;border-bottom:1px solid var(--color-border)}.tc-row:after{content:"";pointer-events:none;position:absolute;width:var(--cell-size);height:100%;bottom:-1px;right:calc(var(--cell-size)*-1);border-bottom:1px solid var(--color-border)}.tc-row--selected{background:var(--color-background)}.tc-row--selected:after{background:var(--color-background)}.tc-cell{border-right:1px solid var(--color-border);padding:6px 12px;overflow:hidden;outline:none;line-break:normal}.tc-cell--selected{background:var(--color-background)}.tc-wrap--readonly .tc-row:after{display:none}.tc-toolbox{--toolbox-padding:6px;--popover-margin:30px;--toggler-click-zone-size:30px;--toggler-dots-color:#7b7e89;--toggler-dots-color-hovered:#1d202b;position:absolute;cursor:pointer;z-index:1;opacity:0;transition:opacity .1s;will-change:left,opacity}.tc-toolbox--column{top:calc(var(--toggler-click-zone-size)*-1);transform:translate(calc(var(--toggler-click-zone-size)*-1/2));will-change:left,opacity}.tc-toolbox--row{left:calc(var(--popover-margin)*-1);transform:translateY(calc(var(--toggler-click-zone-size)*-1/2));margin-top:-1px;will-change:top,opacity}.tc-toolbox--showed{opacity:1}.tc-toolbox .tc-popover{position:absolute;top:0;left:var(--popover-margin)}.tc-toolbox__toggler{display:flex;align-items:center;justify-content:center;width:var(--toggler-click-zone-size);height:var(--toggler-click-zone-size);color:var(--toggler-dots-color);opacity:0;transition:opacity .15s ease;will-change:opacity}.tc-toolbox__toggler:hover{color:var(--toggler-dots-color-hovered)}.tc-toolbox__toggler svg{fill:currentColor}.tc-wrap:hover .tc-toolbox__toggler{opacity:1}.tc-settings .cdx-settings-button{width:50%;margin:0}.tc-popover{--color-border:#eaeaea;--color-background:#fff;--color-background-hover:rgba(232,232,235,.49);--color-background-confirm:#e24a4a;--color-background-confirm-hover:#d54040;--color-text-confirm:#fff;background:var(--color-background);border:1px solid var(--color-border);box-shadow:0 3px 15px -3px #0d142121;border-radius:6px;padding:6px;display:none;will-change:opacity,transform}.tc-popover--opened{display:block;animation:menuShowing .1s cubic-bezier(.215,.61,.355,1) forwards}.tc-popover__item{display:flex;align-items:center;padding:2px 14px 2px 2px;border-radius:5px;cursor:pointer;white-space:nowrap;-webkit-user-select:none;-moz-user-select:none;user-select:none}.tc-popover__item:hover{background:var(--color-background-hover)}.tc-popover__item:not(:last-of-type){margin-bottom:2px}.tc-popover__item-icon{display:inline-flex;width:26px;height:26px;align-items:center;justify-content:center;background:var(--color-background);border-radius:5px;border:1px solid var(--color-border);margin-right:8px}.tc-popover__item-label{line-height:22px;font-size:14px;font-weight:500}.tc-popover__item--confirm{background:var(--color-background-confirm);color:var(--color-text-confirm)}.tc-popover__item--confirm:hover{background-color:var(--color-background-confirm-hover)}.tc-popover__item--confirm .tc-popover__item-icon{background:var(--color-background-confirm);border-color:#0000001a}.tc-popover__item--confirm .tc-popover__item-icon svg{transition:transform .2s ease-in;transform:rotate(90deg) scale(1.2)}.tc-popover__item--hidden{display:none}@keyframes menuShowing{0%{opacity:0;transform:translateY(-8px) scale(.9)}70%{opacity:1;transform:translateY(2px)}to{transform:translateY(0)}}')), globalThis.document.head.appendChild(o2);
    }
  } catch (e2) {
    console.error("vite-plugin-css-injected-by-js", e2);
  }
})();
function c(d, t2, e2 = {}) {
  const o2 = globalThis.document.createElement(d);
  Array.isArray(t2) ? o2.classList.add(...t2) : t2 && o2.classList.add(t2);
  for (const i in e2)
    Object.prototype.hasOwnProperty.call(e2, i) && (o2[i] = e2[i]);
  return o2;
}
function f2(d) {
  const t2 = d.getBoundingClientRect();
  return {
    y1: Math.floor(t2.top + globalThis.window.pageYOffset),
    x1: Math.floor(t2.left + globalThis.window.pageXOffset),
    x2: Math.floor(t2.right + globalThis.window.pageXOffset),
    y2: Math.floor(t2.bottom + globalThis.window.pageYOffset)
  };
}
function g(d, t2) {
  const e2 = f2(d), o2 = f2(t2);
  return {
    fromTopBorder: o2.y1 - e2.y1,
    fromLeftBorder: o2.x1 - e2.x1,
    fromRightBorder: e2.x2 - o2.x2,
    fromBottomBorder: e2.y2 - o2.y2
  };
}
function k(d, t2) {
  const e2 = d.getBoundingClientRect(), { width: o2, height: i, x: n3, y: r2 } = e2, { clientX: h2, clientY: l2 } = t2;
  return {
    width: o2,
    height: i,
    x: h2 - n3,
    y: l2 - r2
  };
}
function m2(d, t2) {
  return t2.parentNode.insertBefore(d, t2);
}
function C(d, t2 = true) {
  const e2 = globalThis.document.createRange(), o2 = globalThis.window.getSelection();
  e2.selectNodeContents(d), e2.collapse(t2), o2.removeAllRanges(), o2.addRange(e2);
}
class a {
  /**
   * @param {object} options - constructor options
   * @param {PopoverItem[]} options.items - constructor options
   */
  constructor({ items: t2 }) {
    this.items = t2, this.wrapper = void 0, this.itemEls = [];
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
    return this.wrapper = c("div", a.CSS.popover), this.items.forEach((t2, e2) => {
      const o2 = c("div", a.CSS.item), i = c("div", a.CSS.itemIcon, {
        innerHTML: t2.icon
      }), n3 = c("div", a.CSS.itemLabel, {
        textContent: t2.label
      });
      o2.dataset.index = e2, o2.appendChild(i), o2.appendChild(n3), this.wrapper.appendChild(o2), this.itemEls.push(o2);
    }), this.wrapper.addEventListener("click", (t2) => {
      this.popoverClicked(t2);
    }), this.wrapper;
  }
  /**
   * Popover wrapper click listener
   * Used to delegate clicks in items
   *
   * @returns {void}
   */
  popoverClicked(t2) {
    const e2 = t2.target.closest(`.${a.CSS.item}`);
    if (!e2)
      return;
    const o2 = e2.dataset.index, i = this.items[o2];
    if (i.confirmationRequired && !this.hasConfirmationState(e2)) {
      this.setConfirmationState(e2);
      return;
    }
    i.onClick();
  }
  /**
   * Enable the confirmation state on passed item
   *
   * @returns {void}
   */
  setConfirmationState(t2) {
    t2.classList.add(a.CSS.itemConfirmState);
  }
  /**
   * Disable the confirmation state on passed item
   *
   * @returns {void}
   */
  clearConfirmationState(t2) {
    t2.classList.remove(a.CSS.itemConfirmState);
  }
  /**
   * Check if passed item has the confirmation state
   *
   * @returns {boolean}
   */
  hasConfirmationState(t2) {
    return t2.classList.contains(a.CSS.itemConfirmState);
  }
  /**
   * Return an opening state
   *
   * @returns {boolean}
   */
  get opened() {
    return this.wrapper.classList.contains(a.CSS.popoverOpened);
  }
  /**
   * Opens the popover
   *
   * @returns {void}
   */
  open() {
    this.items.forEach((t2, e2) => {
      typeof t2.hideIf == "function" && this.itemEls[e2].classList.toggle(a.CSS.itemHidden, t2.hideIf());
    }), this.wrapper.classList.add(a.CSS.popoverOpened);
  }
  /**
   * Closes the popover
   *
   * @returns {void}
   */
  close() {
    this.wrapper.classList.remove(a.CSS.popoverOpened), this.itemEls.forEach((t2) => {
      this.clearConfirmationState(t2);
    });
  }
}
const R = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 9L10 12M10 12L7 15M10 12H4"/><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9L14 12M14 12L17 15M14 12H20"/></svg>', b = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M8 8L12 12M12 12L16 16M12 12L16 8M12 12L8 16"/></svg>', x = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.8833 9.16666L18.2167 12.5M18.2167 12.5L14.8833 15.8333M18.2167 12.5H10.05C9.16594 12.5 8.31809 12.1488 7.69297 11.5237C7.06785 10.8986 6.71666 10.0507 6.71666 9.16666"/></svg>', S2 = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.9167 14.9167L11.5833 18.25M11.5833 18.25L8.25 14.9167M11.5833 18.25L11.5833 10.0833C11.5833 9.19928 11.9345 8.35143 12.5596 7.72631C13.1848 7.10119 14.0326 6.75 14.9167 6.75"/></svg>', y = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.13333 14.9167L12.4667 18.25M12.4667 18.25L15.8 14.9167M12.4667 18.25L12.4667 10.0833C12.4667 9.19928 12.1155 8.35143 11.4904 7.72631C10.8652 7.10119 10.0174 6.75 9.13333 6.75"/></svg>', L = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.8833 15.8333L18.2167 12.5M18.2167 12.5L14.8833 9.16667M18.2167 12.5L10.05 12.5C9.16595 12.5 8.31811 12.8512 7.69299 13.4763C7.06787 14.1014 6.71667 14.9493 6.71667 15.8333"/></svg>', M = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-width="2.6" d="M9.41 9.66H9.4"/><path stroke="currentColor" stroke-linecap="round" stroke-width="2.6" d="M14.6 9.66H14.59"/><path stroke="currentColor" stroke-linecap="round" stroke-width="2.6" d="M9.31 14.36H9.3"/><path stroke="currentColor" stroke-linecap="round" stroke-width="2.6" d="M14.6 14.36H14.59"/></svg>', v = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M12 7V12M12 17V12M17 12H12M12 12H7"/></svg>', O = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9L20 12L17 15"/><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 12H20"/><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 9L4 12L7 15"/><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 12H10"/></svg>', T = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-width="2" d="M5 10H19"/><rect width="14" height="14" x="5" y="5" stroke="currentColor" stroke-width="2" rx="4"/></svg>', H = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-width="2" d="M10 5V18.5"/><path stroke="currentColor" stroke-width="2" d="M14 5V18.5"/><path stroke="currentColor" stroke-width="2" d="M5 10H19"/><path stroke="currentColor" stroke-width="2" d="M5 14H19"/><rect width="14" height="14" x="5" y="5" stroke="currentColor" stroke-width="2" rx="4"/></svg>', A = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-width="2" d="M10 5V18.5"/><path stroke="currentColor" stroke-width="2" d="M5 10H19"/><rect width="14" height="14" x="5" y="5" stroke="currentColor" stroke-width="2" rx="4"/></svg>';
class w {
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
  constructor({ api: t2, items: e2, onOpen: o2, onClose: i, cssModifier: n3 = "" }) {
    this.api = t2, this.items = e2, this.onOpen = o2, this.onClose = i, this.cssModifier = n3, this.popover = null, this.wrapper = this.createToolbox();
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
    const t2 = c("div", [
      w.CSS.toolbox,
      this.cssModifier ? `${w.CSS.toolbox}--${this.cssModifier}` : ""
    ]);
    t2.dataset.mutationFree = "true";
    const e2 = this.createPopover(), o2 = this.createToggler();
    return t2.appendChild(o2), t2.appendChild(e2), t2;
  }
  /**
   * Creates the Toggler
   *
   * @returns {Element}
   */
  createToggler() {
    const t2 = c("div", w.CSS.toggler, {
      innerHTML: M
    });
    return t2.addEventListener("click", () => {
      this.togglerClicked();
    }), t2;
  }
  /**
   * Creates the Popover instance and render it
   *
   * @returns {Element}
   */
  createPopover() {
    return this.popover = new a({
      items: this.items
    }), this.popover.render();
  }
  /**
   * Toggler click handler. Opens/Closes the popover
   *
   * @returns {void}
   */
  togglerClicked() {
    this.popover.opened ? (this.popover.close(), this.onClose()) : (this.popover.open(), this.onOpen());
  }
  /**
   * Shows the Toolbox
   *
   * @param {function} computePositionMethod - method that returns the position coordinate
   * @returns {void}
   */
  show(t2) {
    const e2 = t2();
    Object.entries(e2).forEach(([o2, i]) => {
      this.wrapper.style[o2] = i;
    }), this.wrapper.classList.add(w.CSS.toolboxShowed);
  }
  /**
   * Hides the Toolbox
   *
   * @returns {void}
   */
  hide() {
    this.popover.close(), this.wrapper.classList.remove(w.CSS.toolboxShowed);
  }
}
function B(d, t2) {
  let e2 = 0;
  return function(...o2) {
    const i = (/* @__PURE__ */ new Date()).getTime();
    if (!(i - e2 < d))
      return e2 = i, t2(...o2);
  };
}
const s = {
  wrapper: "tc-wrap",
  wrapperReadOnly: "tc-wrap--readonly",
  table: "tc-table",
  row: "tc-row",
  withHeadings: "tc-table--heading",
  rowSelected: "tc-row--selected",
  cell: "tc-cell",
  cellSelected: "tc-cell--selected",
  addRow: "tc-add-row",
  addRowDisabled: "tc-add-row--disabled",
  addColumn: "tc-add-column",
  addColumnDisabled: "tc-add-column--disabled"
};
class E {
  /**
   * Creates
   *
   * @constructor
   * @param {boolean} readOnly - read-only mode flag
   * @param {object} api - Editor.js API
   * @param {TableData} data - Editor.js API
   * @param {TableConfig} config - Editor.js API
   */
  constructor(t2, e2, o2, i) {
    this.readOnly = t2, this.api = e2, this.data = o2, this.config = i, this.wrapper = null, this.table = null, this.toolboxColumn = this.createColumnToolbox(), this.toolboxRow = this.createRowToolbox(), this.createTableWrapper(), this.hoveredRow = 0, this.hoveredColumn = 0, this.selectedRow = 0, this.selectedColumn = 0, this.tunes = {
      withHeadings: false
    }, this.resize(), this.fill(), this.focusedCell = {
      row: 0,
      column: 0
    }, this.documentClicked = (n3) => {
      const r2 = n3.target.closest(`.${s.table}`) !== null, h2 = n3.target.closest(`.${s.wrapper}`) === null;
      (r2 || h2) && this.hideToolboxes();
      const u2 = n3.target.closest(`.${s.addRow}`), p2 = n3.target.closest(`.${s.addColumn}`);
      u2 && u2.parentNode === this.wrapper ? (this.addRow(void 0, true), this.hideToolboxes()) : p2 && p2.parentNode === this.wrapper && (this.addColumn(void 0, true), this.hideToolboxes());
    }, this.readOnly || this.bindEvents();
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
    globalThis.document.addEventListener("click", this.documentClicked), this.table.addEventListener("mousemove", B(150, (t2) => this.onMouseMoveInTable(t2)), { passive: true }), this.table.onkeypress = (t2) => this.onKeyPressListener(t2), this.table.addEventListener("keydown", (t2) => this.onKeyDownListener(t2)), this.table.addEventListener("focusin", (t2) => this.focusInTableListener(t2));
  }
  /**
   * Configures and creates the toolbox for manipulating with columns
   *
   * @returns {Toolbox}
   */
  createColumnToolbox() {
    return new w({
      api: this.api,
      cssModifier: "column",
      items: [
        {
          label: this.api.i18n.t("Add column to left"),
          icon: S2,
          hideIf: () => this.numberOfColumns === this.config.maxcols,
          onClick: () => {
            this.addColumn(this.selectedColumn, true), this.hideToolboxes();
          }
        },
        {
          label: this.api.i18n.t("Add column to right"),
          icon: y,
          hideIf: () => this.numberOfColumns === this.config.maxcols,
          onClick: () => {
            this.addColumn(this.selectedColumn + 1, true), this.hideToolboxes();
          }
        },
        {
          label: this.api.i18n.t("Delete column"),
          icon: b,
          hideIf: () => this.numberOfColumns === 1,
          confirmationRequired: true,
          onClick: () => {
            this.deleteColumn(this.selectedColumn), this.hideToolboxes();
          }
        }
      ],
      onOpen: () => {
        this.selectColumn(this.hoveredColumn), this.hideRowToolbox();
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
    return new w({
      api: this.api,
      cssModifier: "row",
      items: [
        {
          label: this.api.i18n.t("Add row above"),
          icon: L,
          hideIf: () => this.numberOfRows === this.config.maxrows,
          onClick: () => {
            this.addRow(this.selectedRow, true), this.hideToolboxes();
          }
        },
        {
          label: this.api.i18n.t("Add row below"),
          icon: x,
          hideIf: () => this.numberOfRows === this.config.maxrows,
          onClick: () => {
            this.addRow(this.selectedRow + 1, true), this.hideToolboxes();
          }
        },
        {
          label: this.api.i18n.t("Delete row"),
          icon: b,
          hideIf: () => this.numberOfRows === 1,
          confirmationRequired: true,
          onClick: () => {
            this.deleteRow(this.selectedRow), this.hideToolboxes();
          }
        }
      ],
      onOpen: () => {
        this.selectRow(this.hoveredRow), this.hideColumnToolbox();
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
    this.focusedCell.row !== this.numberOfRows ? (this.focusedCell.row += 1, this.focusCell(this.focusedCell)) : (this.addRow(), this.focusedCell.row += 1, this.focusCell(this.focusedCell), this.updateToolboxesPosition(0, 0));
  }
  /**
   * Get table cell by row and col index
   *
   * @param {number} row - cell row coordinate
   * @param {number} column - cell column coordinate
   * @returns {HTMLElement}
   */
  getCell(t2, e2) {
    return this.table.querySelectorAll(`.${s.row}:nth-child(${t2}) .${s.cell}`)[e2 - 1];
  }
  /**
   * Get table row by index
   *
   * @param {number} row - row coordinate
   * @returns {HTMLElement}
   */
  getRow(t2) {
    return this.table.querySelector(`.${s.row}:nth-child(${t2})`);
  }
  /**
   * The parent of the cell which is the row
   *
   * @param {HTMLElement} cell - cell element
   * @returns {HTMLElement}
   */
  getRowByCell(t2) {
    return t2.parentElement;
  }
  /**
   * Ger row's first cell
   *
   * @param {Element} row - row to find its first cell
   * @returns {Element}
   */
  getRowFirstCell(t2) {
    return t2.querySelector(`.${s.cell}:first-child`);
  }
  /**
   * Set the sell's content by row and column numbers
   *
   * @param {number} row - cell row coordinate
   * @param {number} column - cell column coordinate
   * @param {string} content - cell HTML content
   */
  setCellContent(t2, e2, o2) {
    const i = this.getCell(t2, e2);
    i.innerHTML = o2;
  }
  /**
   * Add column in table on index place
   * Add cells in each row
   *
   * @param {number} columnIndex - number in the array of columns, where new column to insert, -1 if insert at the end
   * @param {boolean} [setFocus] - pass true to focus the first cell
   */
  addColumn(t2 = -1, e2 = false) {
    var n3;
    let o2 = this.numberOfColumns;
    if (this.config && this.config.maxcols && this.numberOfColumns >= this.config.maxcols)
      return;
    for (let r2 = 1; r2 <= this.numberOfRows; r2++) {
      let h2;
      const l2 = this.createCell();
      if (t2 > 0 && t2 <= o2 ? (h2 = this.getCell(r2, t2), m2(l2, h2)) : h2 = this.getRow(r2).appendChild(l2), r2 === 1) {
        const u2 = this.getCell(r2, t2 > 0 ? t2 : o2 + 1);
        u2 && e2 && C(u2);
      }
    }
    const i = this.wrapper.querySelector(`.${s.addColumn}`);
    (n3 = this.config) != null && n3.maxcols && this.numberOfColumns > this.config.maxcols - 1 && i && i.classList.add(s.addColumnDisabled), this.addHeadingAttrToFirstRow();
  }
  /**
   * Add row in table on index place
   *
   * @param {number} index - number in the array of rows, where new column to insert, -1 if insert at the end
   * @param {boolean} [setFocus] - pass true to focus the inserted row
   * @returns {HTMLElement} row
   */
  addRow(t2 = -1, e2 = false) {
    let o2, i = c("div", s.row);
    this.tunes.withHeadings && this.removeHeadingAttrFromFirstRow();
    let n3 = this.numberOfColumns;
    if (this.config && this.config.maxrows && this.numberOfRows >= this.config.maxrows && h2)
      return;
    if (t2 > 0 && t2 <= this.numberOfRows) {
      let l2 = this.getRow(t2);
      o2 = m2(i, l2);
    } else
      o2 = this.table.appendChild(i);
    this.fillRow(o2, n3), this.tunes.withHeadings && this.addHeadingAttrToFirstRow();
    const r2 = this.getRowFirstCell(o2);
    r2 && e2 && C(r2);
    const h2 = this.wrapper.querySelector(`.${s.addRow}`);
    return this.config && this.config.maxrows && this.numberOfRows >= this.config.maxrows && h2 && h2.classList.add(s.addRowDisabled), o2;
  }
  /**
   * Delete a column by index
   *
   * @param {number} index
   */
  deleteColumn(t2) {
    for (let o2 = 1; o2 <= this.numberOfRows; o2++) {
      const i = this.getCell(o2, t2);
      if (!i)
        return;
      i.remove();
    }
    const e2 = this.wrapper.querySelector(`.${s.addColumn}`);
    e2 && e2.classList.remove(s.addColumnDisabled);
  }
  /**
   * Delete a row by index
   *
   * @param {number} index
   */
  deleteRow(t2) {
    this.getRow(t2).remove();
    const e2 = this.wrapper.querySelector(`.${s.addRow}`);
    e2 && e2.classList.remove(s.addRowDisabled), this.addHeadingAttrToFirstRow();
  }
  /**
   * Create a wrapper containing a table, toolboxes
   * and buttons for adding rows and columns
   *
   * @returns {HTMLElement} wrapper - where all buttons for a table and the table itself will be
   */
  createTableWrapper() {
    if (this.wrapper = c("div", s.wrapper), this.table = c("div", s.table), this.readOnly && this.wrapper.classList.add(s.wrapperReadOnly), this.wrapper.appendChild(this.toolboxRow.element), this.wrapper.appendChild(this.toolboxColumn.element), this.wrapper.appendChild(this.table), !this.readOnly) {
      const t2 = c("div", s.addColumn, {
        innerHTML: v
      }), e2 = c("div", s.addRow, {
        innerHTML: v
      });
      this.wrapper.appendChild(t2), this.wrapper.appendChild(e2);
    }
  }
  /**
   * Returns the size of the table based on initial data or config "size" property
   *
   * @return {{rows: number, cols: number}} - number of cols and rows
   */
  computeInitialSize() {
    const t2 = this.data && this.data.content, e2 = Array.isArray(t2), o2 = e2 ? t2.length : false, i = e2 ? t2.length : void 0, n3 = o2 ? t2[0].length : void 0, r2 = Number.parseInt(this.config && this.config.rows), h2 = Number.parseInt(this.config && this.config.cols), l2 = !isNaN(r2) && r2 > 0 ? r2 : void 0, u2 = !isNaN(h2) && h2 > 0 ? h2 : void 0;
    return {
      rows: i || l2 || 2,
      cols: n3 || u2 || 2
    };
  }
  /**
   * Resize table to match config size or transmitted data size
   *
   * @return {{rows: number, cols: number}} - number of cols and rows
   */
  resize() {
    const { rows: t2, cols: e2 } = this.computeInitialSize();
    for (let o2 = 0; o2 < t2; o2++)
      this.addRow();
    for (let o2 = 0; o2 < e2; o2++)
      this.addColumn();
  }
  /**
   * Fills the table with data passed to the constructor
   *
   * @returns {void}
   */
  fill() {
    const t2 = this.data;
    if (t2 && t2.content)
      for (let e2 = 0; e2 < t2.content.length; e2++)
        for (let o2 = 0; o2 < t2.content[e2].length; o2++)
          this.setCellContent(e2 + 1, o2 + 1, t2.content[e2][o2]);
  }
  /**
   * Fills a row with cells
   *
   * @param {HTMLElement} row - row to fill
   * @param {number} numberOfColumns - how many cells should be in a row
   */
  fillRow(t2, e2) {
    for (let o2 = 1; o2 <= e2; o2++) {
      const i = this.createCell();
      t2.appendChild(i);
    }
  }
  /**
   * Creating a cell element
   *
   * @return {Element}
   */
  createCell() {
    return c("div", s.cell, {
      contentEditable: !this.readOnly
    });
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
    return this.numberOfRows ? this.table.querySelectorAll(`.${s.row}:first-child .${s.cell}`).length : 0;
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
  onMouseMoveInTable(t2) {
    const { row: e2, column: o2 } = this.getHoveredCell(t2);
    this.hoveredColumn = o2, this.hoveredRow = e2, this.updateToolboxesPosition();
  }
  /**
   * Prevents default Enter behaviors
   * Adds Shift+Enter processing
   *
   * @param {KeyboardEvent} event - keypress event
   */
  onKeyPressListener(t2) {
    if (t2.key === "Enter") {
      if (t2.shiftKey)
        return true;
      this.moveCursorToNextRow();
    }
    return t2.key !== "Enter";
  }
  /**
   * Prevents tab keydown event from bubbling
   * so that it only works inside the table
   *
   * @param {KeyboardEvent} event - keydown event
   */
  onKeyDownListener(t2) {
    t2.key === "Tab" && t2.stopPropagation();
  }
  /**
   * Set the coordinates of the cell that the focus has moved to
   *
   * @param {FocusEvent} event - focusin event
   */
  focusInTableListener(t2) {
    const e2 = t2.target, o2 = this.getRowByCell(e2);
    this.focusedCell = {
      row: Array.from(this.table.querySelectorAll(`.${s.row}`)).indexOf(o2) + 1,
      column: Array.from(o2.querySelectorAll(`.${s.cell}`)).indexOf(e2) + 1
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
    this.hideRowToolbox(), this.hideColumnToolbox(), this.updateToolboxesPosition();
  }
  /**
   * Unselect row, close toolbox
   *
   * @returns {void}
   */
  hideRowToolbox() {
    this.unselectRow(), this.toolboxRow.hide();
  }
  /**
   * Unselect column, close toolbox
   *
   * @returns {void}
   */
  hideColumnToolbox() {
    this.unselectColumn(), this.toolboxColumn.hide();
  }
  /**
   * Set the cursor focus to the focused cell
   *
   * @returns {void}
   */
  focusCell() {
    this.focusedCellElem.focus();
  }
  /**
   * Get current focused element
   *
   * @returns {HTMLElement} - focused cell
   */
  get focusedCellElem() {
    const { row: t2, column: e2 } = this.focusedCell;
    return this.getCell(t2, e2);
  }
  /**
   * Update toolboxes position
   *
   * @param {number} row - hovered row
   * @param {number} column - hovered column
   */
  updateToolboxesPosition(t2 = this.hoveredRow, e2 = this.hoveredColumn) {
    this.isColumnMenuShowing || e2 > 0 && e2 <= this.numberOfColumns && this.toolboxColumn.show(() => ({
      left: `calc((100% - var(--cell-size)) / (${this.numberOfColumns} * 2) * (1 + (${e2} - 1) * 2))`
    })), this.isRowMenuShowing || t2 > 0 && t2 <= this.numberOfRows && this.toolboxRow.show(() => {
      const o2 = this.getRow(t2), { fromTopBorder: i } = g(this.table, o2), { height: n3 } = o2.getBoundingClientRect();
      return {
        top: `${Math.ceil(i + n3 / 2)}px`
      };
    });
  }
  /**
   * Makes the first row headings
   *
   * @param {boolean} withHeadings - use headings row or not
   */
  setHeadingsSetting(t2) {
    this.tunes.withHeadings = t2, t2 ? (this.table.classList.add(s.withHeadings), this.addHeadingAttrToFirstRow()) : (this.table.classList.remove(s.withHeadings), this.removeHeadingAttrFromFirstRow());
  }
  /**
   * Adds an attribute for displaying the placeholder in the cell
   */
  addHeadingAttrToFirstRow() {
    for (let t2 = 1; t2 <= this.numberOfColumns; t2++) {
      let e2 = this.getCell(1, t2);
      e2 && e2.setAttribute("heading", this.api.i18n.t("Heading"));
    }
  }
  /**
   * Removes an attribute for displaying the placeholder in the cell
   */
  removeHeadingAttrFromFirstRow() {
    for (let t2 = 1; t2 <= this.numberOfColumns; t2++) {
      let e2 = this.getCell(1, t2);
      e2 && e2.removeAttribute("heading");
    }
  }
  /**
   * Add effect of a selected row
   *
   * @param {number} index
   */
  selectRow(t2) {
    const e2 = this.getRow(t2);
    e2 && (this.selectedRow = t2, e2.classList.add(s.rowSelected));
  }
  /**
   * Remove effect of a selected row
   */
  unselectRow() {
    if (this.selectedRow <= 0)
      return;
    const t2 = this.table.querySelector(`.${s.rowSelected}`);
    t2 && t2.classList.remove(s.rowSelected), this.selectedRow = 0;
  }
  /**
   * Add effect of a selected column
   *
   * @param {number} index
   */
  selectColumn(t2) {
    for (let e2 = 1; e2 <= this.numberOfRows; e2++) {
      const o2 = this.getCell(e2, t2);
      o2 && o2.classList.add(s.cellSelected);
    }
    this.selectedColumn = t2;
  }
  /**
   * Remove effect of a selected column
   */
  unselectColumn() {
    if (this.selectedColumn <= 0)
      return;
    let t2 = this.table.querySelectorAll(`.${s.cellSelected}`);
    Array.from(t2).forEach((e2) => {
      e2.classList.remove(s.cellSelected);
    }), this.selectedColumn = 0;
  }
  /**
   * Calculates the row and column that the cursor is currently hovering over
   * The search was optimized from O(n) to O (log n) via bin search to reduce the number of calculations
   *
   * @param {Event} event - mousemove event
   * @returns hovered cell coordinates as an integer row and column
   */
  getHoveredCell(t2) {
    let e2 = this.hoveredRow, o2 = this.hoveredColumn;
    const { width: i, height: n3, x: r2, y: h2 } = k(this.table, t2);
    return r2 >= 0 && (o2 = this.binSearch(
      this.numberOfColumns,
      (l2) => this.getCell(1, l2),
      ({ fromLeftBorder: l2 }) => r2 < l2,
      ({ fromRightBorder: l2 }) => r2 > i - l2
    )), h2 >= 0 && (e2 = this.binSearch(
      this.numberOfRows,
      (l2) => this.getCell(l2, 1),
      ({ fromTopBorder: l2 }) => h2 < l2,
      ({ fromBottomBorder: l2 }) => h2 > n3 - l2
    )), {
      row: e2 || this.hoveredRow,
      column: o2 || this.hoveredColumn
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
  binSearch(t2, e2, o2, i) {
    let n3 = 0, r2 = t2 + 1, h2 = 0, l2;
    for (; n3 < r2 - 1 && h2 < 10; ) {
      l2 = Math.ceil((n3 + r2) / 2);
      const u2 = e2(l2), p2 = g(this.table, u2);
      if (o2(p2))
        r2 = l2;
      else if (i(p2))
        n3 = l2;
      else
        break;
      h2++;
    }
    return l2;
  }
  /**
   * Collects data from cells into a two-dimensional array
   *
   * @returns {string[][]}
   */
  getData() {
    const t2 = [];
    for (let e2 = 1; e2 <= this.numberOfRows; e2++) {
      const o2 = this.table.querySelector(`.${s.row}:nth-child(${e2})`), i = Array.from(o2.querySelectorAll(`.${s.cell}`));
      i.every((r2) => !r2.textContent.trim()) || t2.push(i.map((r2) => r2.innerHTML));
    }
    return t2;
  }
  /**
   * Remove listeners on the document
   */
  destroy() {
    globalThis.document.removeEventListener("click", this.documentClicked);
  }
}
class F {
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
   * Render plugin`s main Element and fill it with saved data
   *
   * @param {TableConstructor} init
   */
  constructor({ data: t2, config: e2, api: o2, readOnly: i, block: n3 }) {
    this.api = o2, this.readOnly = i, this.config = e2, this.data = {
      withHeadings: this.getConfig("withHeadings", false, t2),
      stretched: this.getConfig("stretched", false, t2),
      content: t2 && t2.content ? t2.content : []
    }, this.table = null, this.block = n3;
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
      icon: A,
      title: "Table"
    };
  }
  /**
   * Return Tool's view
   *
   * @returns {HTMLDivElement}
   */
  render() {
    return this.table = new E(this.readOnly, this.api, this.data, this.config), this.container = c("div", this.api.styles.block), this.container.appendChild(this.table.getWrapper()), this.table.setHeadingsSetting(this.data.withHeadings), this.container;
  }
  /**
   * Returns plugin settings
   *
   * @returns {Array}
   */
  renderSettings() {
    return [
      {
        label: this.api.i18n.t("With headings"),
        icon: T,
        isActive: this.data.withHeadings,
        closeOnActivate: true,
        toggle: true,
        onActivate: () => {
          this.data.withHeadings = true, this.table.setHeadingsSetting(this.data.withHeadings);
        }
      },
      {
        label: this.api.i18n.t("Without headings"),
        icon: H,
        isActive: !this.data.withHeadings,
        closeOnActivate: true,
        toggle: true,
        onActivate: () => {
          this.data.withHeadings = false, this.table.setHeadingsSetting(this.data.withHeadings);
        }
      },
      {
        label: this.data.stretched ? this.api.i18n.t("Collapse") : this.api.i18n.t("Stretch"),
        icon: this.data.stretched ? R : O,
        closeOnActivate: true,
        toggle: true,
        onActivate: () => {
          this.data.stretched = !this.data.stretched, this.block.stretched = this.data.stretched;
        }
      }
    ];
  }
  /**
   * Extract table data from the view
   *
   * @returns {TableData} - saved data
   */
  save() {
    const t2 = this.table.getData();
    return {
      withHeadings: this.data.withHeadings,
      stretched: this.data.stretched,
      content: t2
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
  getConfig(t2, e2 = void 0, o2 = void 0) {
    const i = this.data || o2;
    return i ? i[t2] ? i[t2] : e2 : this.config && this.config[t2] ? this.config[t2] : e2;
  }
  /**
   * Table onPaste configuration
   *
   * @public
   */
  static get pasteConfig() {
    return { tags: ["TABLE", "TR", "TH", "TD"] };
  }
  /**
   * On paste callback that is fired from Editor
   *
   * @param {PasteEvent} event - event with pasted data
   */
  onPaste(t2) {
    const e2 = t2.detail.data, o2 = e2.querySelector(":scope > thead, tr:first-of-type th"), n3 = Array.from(e2.querySelectorAll("tr")).map((r2) => Array.from(r2.querySelectorAll("th, td")).map((l2) => l2.innerHTML));
    this.data = {
      withHeadings: o2 !== null,
      content: n3
    }, this.table.wrapper && this.table.wrapper.replaceWith(this.render());
  }
}
class Table extends F {
  /**
   * Export block data to Markdown
   * @param {TableData} data - Block data
   * @param {BlockTuneData} tunes - Block tunes
   * @returns {string} Markdown representation
   */
  static async exportToMarkdown(data, tunes) {
    if (!data || !data.content) {
      return "";
    }
    const rows = data.content;
    if (rows.length === 0) {
      return "";
    }
    let markdown = "";
    const withHeadings = data.withHeadings || false;
    rows.forEach((row, rowIndex) => {
      const isHeaderRow = withHeadings && rowIndex === 0;
      markdown += "| " + row.join(" | ") + " |\n";
      if (isHeaderRow) {
        const separator = row.map(() => "---").join(" | ");
        markdown += "| " + separator + " |\n";
      }
    });
    const formattedMarkdown = await MarkdownUtils.formatMarkdownWithPrettier(markdown);
    return MarkdownUtils.addAttributes(formattedMarkdown, tunes);
  }
  static importFromMarkdown(editor, markdown) {
    const lines = markdown.split("\n");
    let i = 0;
    let tunes = {};
    const content = [];
    let withHeadings = false;
    while (i < lines.length) {
      if (!lines[i]) {
        break;
      }
      const line = lines[i] || "";
      if (i === 0 && MarkdownUtils.startWithAttribute(line)) {
        tunes = MarkdownUtils.parseAttributes(line);
        i++;
        continue;
      }
      if (line.includes("|")) {
        const cells = line.split("|").map((cell) => cell.trim()).filter((cell) => cell !== "");
        content.push(cells);
        if (i + 1 < lines.length && lines[i + 1]?.trim().match(/^\|[\|\s\-:]+\|$/)) {
          withHeadings = true;
          i++;
        }
      } else {
        break;
      }
      i++;
    }
    const block = editor.blocks.insert("table");
    editor.blocks.update(
      block.id,
      {
        content,
        withHeadings
      },
      tunes
    );
    return block;
  }
  static isItMarkdownExported(markdown) {
    return markdown.startsWith("|");
  }
}
(function() {
  try {
    if (typeof globalThis.document < "u") {
      var e2 = globalThis.document.createElement("style");
      e2.appendChild(globalThis.document.createTextNode('.ce-delimiter{line-height:1.6em;width:100%;text-align:center}.ce-delimiter:before{display:inline-block;content:"***";font-size:30px;line-height:65px;height:30px;letter-spacing:.2em}')), globalThis.document.head.appendChild(e2);
    }
  } catch (t2) {
    console.error("vite-plugin-css-injected-by-js", t2);
  }
})();
const r = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><line x1="6" x2="10" y1="12" y2="12" stroke="currentColor" stroke-linecap="round" stroke-width="2"/><line x1="14" x2="18" y1="12" y2="12" stroke="currentColor" stroke-linecap="round" stroke-width="2"/></svg>';
class n2 {
  /**
   * Notify core that read-only mode is supported
   * @return {boolean}
   */
  static get isReadOnlySupported() {
    return true;
  }
  /**
   * Allow Tool to have no content
   * @return {boolean}
   */
  static get contentless() {
    return true;
  }
  /**
   * Render plugin`s main Element and fill it with saved data
   *
   * @param {{data: DelimiterData, config: object, api: object}}
   *   data — previously saved data
   *   config - user config for Tool
   *   api - Editor.js API
   */
  constructor({ data: t2, config: s2, api: e2 }) {
    this.api = e2, this._CSS = {
      block: this.api.styles.block,
      wrapper: "ce-delimiter"
    }, this._element = this.drawView(), this.data = t2;
  }
  /**
   * Create Tool's view
   * @return {HTMLDivElement}
   * @private
   */
  drawView() {
    let t2 = globalThis.document.createElement("div");
    return t2.classList.add(this._CSS.wrapper, this._CSS.block), t2;
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
  save(t2) {
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
  onPaste(t2) {
    this.data = {};
  }
}
class Delimiter extends n2 {
  /**
   * Export block data to Markdown
   * @param {BlockToolData} data - Block data
   * @param {BlockTuneData} tunes - Block tunes
   * @returns {string} Markdown representation
   */
  // @ts-ignore
  static exportToMarkdown(data, tunes) {
    return "<!--break-->";
  }
  static importFromMarkdown(editor) {
    editor.blocks.insert("delimiter");
  }
  static isItMarkdownExported(markdown) {
    return markdown.trim().match(/^-{3,}$/) !== null && markdown.split("\n").length === 1 || markdown.trim() === "<!--break-->";
  }
}
const ToolboxIcon = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-play-fill" viewBox="0 0 16 16">\n    <path d="m11.596 8.697-6.363 3.692c-.54.313-1.233-.066-1.233-.697V4.308c0-.63.692-1.01 1.233-.696l6.363 3.692a.802.802 0 0 1 0 1.393"/>\n</svg>';
const BLOCK_STATE = {
  EDIT: 0,
  VIEW: 1
};
class StateBlock {
  static showEditBtn(BlockTool, state = BLOCK_STATE.VIEW) {
    if (BlockTool.nodes.editBtn === void 0) {
      throw new Error("must createEditBtn before");
    }
    BlockTool.nodes.editInput.checked = state === BLOCK_STATE.VIEW ? true : false;
  }
  static createEditBtn(BlockTool) {
    const toggleId = StateBlock.generateRandomId("toggle");
    BlockTool.nodes.editBtn = make.element("div", "toggle-wrapper");
    BlockTool.nodes.editInput = make.element("input", ["toggle-input"], {
      type: "checkbox",
      id: toggleId
    });
    const label = make.element("label", ["toggle-label"], {
      for: toggleId
    });
    BlockTool.nodes.editBtn.appendChild(BlockTool.nodes.editInput);
    BlockTool.nodes.editBtn.appendChild(label);
    return BlockTool.nodes.editBtn;
  }
  static generateRandomId(prefix = "id") {
    const randomString = Math.random().toString(36).substring(2, 9);
    return `${prefix}_${randomString}`;
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
    BlockTool.nodes.wrapper = make.element("div", BlockTool.api.styles.block);
    BlockTool.nodes.preview = StateBlock.createPreview(BlockTool);
    BlockTool.updatePreview();
    BlockTool.nodes.wrapper.appendChild(BlockTool.nodes.preview);
    BlockTool.nodes.wrapper.appendChild(BlockTool.nodes.editBtn);
    BlockTool.nodes.inputs = BlockTool.createInputs();
    if (BlockTool.nodes.inputs !== BlockTool.nodes.wrapper) {
      BlockTool.nodes.wrapper.appendChild(BlockTool.nodes.inputs);
    }
    BlockTool.validate() ? (BlockTool.save(), StateBlock.show(BlockTool, BLOCK_STATE.VIEW)) : StateBlock.show(BlockTool, BLOCK_STATE.EDIT);
    BlockTool.nodes.editInput.addEventListener(
      "change",
      () => StateBlock.onEditInputChange(BlockTool)
    );
    return BlockTool.nodes.wrapper;
  }
  static onEditInputChange(BlockTool) {
    BlockTool.nodes.editInput.checked ? (BlockTool.save(), StateBlock.show(BlockTool, BLOCK_STATE.VIEW)) : StateBlock.show(BlockTool, BLOCK_STATE.EDIT);
  }
  static createPreview(BlockTool) {
    const previewWrapper = make.element("div", ["hidden", "preview-wrapper"]);
    previewWrapper.onclick = () => {
      BlockTool.nodes.editInput.checked = false;
      StateBlock.show(BlockTool, BLOCK_STATE.EDIT);
    };
    return previewWrapper;
  }
}
class Embed extends AbstractMediaTool {
  static get toolbox() {
    return { title: "Embed", icon: ToolboxIcon };
  }
  constructor({
    data,
    config,
    api,
    readOnly
  }) {
    super({ data, config, api, readOnly });
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
    if (!this.responsIsValid(response)) {
      return this.handleUploadError("incorrect response: " + JSON.stringify(response));
    }
    this.data.media = response.file.media;
    if (!response.file.name) return;
    this.data.alternativeText = response.file.name;
    this.nodes.inputAlternativeText.textContent = response.file.name;
    this.fillImage();
  }
  createInputs() {
    this.nodes.inputAlternativeText = make.input(
      this,
      ["image-tool__caption", this.api.styles.input],
      "Alternative Text",
      this.data.alternativeText
    );
    this.nodes.inputServiceUrl = make.input(
      this,
      ["cdx-input-labeled", "cdx-input-labeled-embed-service-url", this.api.styles.input],
      "Service URL (eg: https://youtube.com/watch?v=...",
      this.data.serviceUrl
    );
    const wrapper = make.element("div", ["cdx-embed"]);
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
    if (!this.nodes.preview) {
      throw new Error("must createPreview before");
    }
    this.nodes.preview.innerHTML = `<div style="display:block;--aspect-ratio:16/9;background: center / cover no-repeat url('/media/md/` + this.data.media + `');"><div style="display: flex;justify-content: center;align-items: center; width:100%;height:100%;color:#c4302b">` + ToolboxIcon.replace('width="16"', 'width="100"').replace(
      'height="16"',
      'height="100"'
    ) + "</div></div>";
  }
  show(state) {
    this.updatePreview();
    if (state !== BLOCK_STATE.VIEW) return StateBlock.show(this, state);
    if (!this.validate()) {
      this.api.notifier.show({
        message: this.api.i18n.t(
          "Something is missing to properly render the embeded video."
        ),
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
    if (this.nodes.imageEl) {
      this.nodes.imageEl.remove();
    }
    const src = this.data.media;
    if (!src) return;
    this.nodes.imageEl = make.element("img", "image-tool__image-picture", {
      src: MediaUtils.buildFullUrl(src),
      style: "max-height:47px;padding-left:1em"
    });
    this.showPreloader(src);
    const self2 = this;
    this.nodes.imageEl.addEventListener("load", function() {
      self2.hidePreloader(STATUS.EMPTY);
    });
    this.nodes.fileButton.appendChild(this.nodes.imageEl);
    if (this.validate() && this.nodes.inputs) {
      this.show(BLOCK_STATE.VIEW);
    }
  }
  static exportToMarkdown(dataToNormalize, tunes) {
    const data = Embed.normalizeData(dataToNormalize);
    if (!data.media || !data.serviceUrl) {
      return "";
    }
    const markdown = `{{ video(${e(data.serviceUrl)}, ${e(data.media)}, ${e(data.alternativeText)}) }}`;
    return MarkdownUtils.addAttributes(markdown, tunes);
  }
  static importFromMarkdown(editor, markdown) {
    const result = MarkdownUtils.parseTunesFromMarkdown(markdown);
    let tunes = result.tunes;
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
    const properties = MarkdownUtils.extractTwigFunctionProperties("video", markdown);
    return properties !== null;
  }
}
class Attaches extends AbstractMediaTool {
  static get toolbox() {
    return {
      icon: U$2,
      title: "Attachment"
    };
  }
  constructor({
    data,
    config,
    api,
    readOnly,
    block
  }) {
    super({ api, config, readOnly, data });
    this.block = block;
    this.nodes = {
      // @ts-ignore
      ...this.nodes
      // ...
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
    const holder = make.element("div", this.api.styles.block);
    this.nodes.wrapper.classList.add("cdx-attaches");
    if (this.pluginHasData()) {
      this.showFileData();
    } else {
      this.nodes.wrapper.appendChild(this.nodes.fileButton);
    }
    holder.appendChild(this.nodes.wrapper);
    return holder;
  }
  pluginHasData() {
    return this.data.title !== "" || this.data.file.media !== "";
  }
  onUpload(response) {
    if (!this.responsIsValid(response)) {
      return this.handleUploadError("incorrect response: " + JSON.stringify(response));
    }
    this.data.file.media = response.file.media;
    this.data.title = response.file.name || response.file.title || "";
    this.data.file.size = response.file.size;
    this.showFileData();
    this.block.dispatchChange();
  }
  appendFileIcon() {
    const wrapper = make.element("a", "cdx-attaches__file-icon", {
      href: MediaUtils.buildFullUrlFromData(this.data.file),
      target: "_blank"
    });
    const background = make.element("div", "cdx-attaches__file-icon-background");
    wrapper.appendChild(background);
    background.title = this.extension || "";
    this.nodes.wrapper.appendChild(wrapper);
  }
  get media() {
    return this.data.file.media;
  }
  showFileData() {
    this.nodes.wrapper.classList.add("cdx-attaches--with-file");
    const { file, title } = this.data;
    if (!this.media) {
      this.hidePreloader(STATUS.EMPTY);
      return;
    }
    this.appendFileIcon();
    const fileInfo = make.element("div", "cdx-attaches__file-info");
    this.nodes.title = make.element("div", "cdx-attaches__title", {
      contentEditable: this.readOnly === false
    });
    this.nodes.title.dataset.placeholder = this.api.i18n?.t("File title");
    this.nodes.title.textContent = title;
    fileInfo.appendChild(this.nodes.title);
    if (file?.size) {
      const fileSize = make.element("div", "cdx-attaches__size");
      const formattedSize = this.fileConvertSize(file.size);
      fileSize.textContent = formattedSize;
      fileInfo.appendChild(fileSize);
    }
    this.nodes.wrapper.appendChild(fileInfo);
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
    for (let n3 = 0; n3 < units.length; n3++) {
      const currentUnit = units[n3];
      const previousUnit = units[n3 - 1];
      if (currentUnit && previousUnit && sizeNum < currentUnit[0] && n3 > 0) {
        return (sizeNum / previousUnit[0]).toFixed(2) + " " + previousUnit[1];
      }
    }
    return sizeNum.toString();
  }
  static exportToMarkdown(data, tunes) {
    data = Attaches.normalizeData(data);
    if (!data || !data.file.media) {
      return "";
    }
    const fileUrl = MediaUtils.buildFullUrlFromData(data.file);
    const title = data.title || "";
    const markdown = `{{ attaches(${e(he$1.decode(title))}, ${e(fileUrl)}, '${data.file.size || 0}' ${tunes?.anchor ? ", " + e(tunes.anchor) : ""}) }}`;
    return markdown;
  }
  static importFromMarkdown(editor, markdown) {
    const result = MarkdownUtils.parseTunesFromMarkdown(markdown);
    let tunes = result.tunes;
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
    if (properties[4] && properties[4] !== "") {
      tunes.anchor = properties[4];
    }
    const block = editor.blocks.insert("attaches");
    editor.blocks.update(block.id, data, tunes);
  }
  static isItMarkdownExported(markdown) {
    const properties = MarkdownUtils.extractTwigFunctionProperties("attaches", markdown);
    return properties !== null;
  }
}
function exportPagesListToMarkdown(data, tunes) {
  if (!data || !data.kw) {
    return "";
  }
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
function exportCardListToMarkdown(data, _tunes) {
  if (!data || !data.items || data.items.length === 0) {
    return "";
  }
  const items = data.items.map((item) => {
    const obj = {};
    if (item.page) obj.page = item.page;
    if (item.title) obj.title = item.title;
    if (item.image) obj.image = item.image;
    if (item.link) obj.link = item.link;
    if (item.obfuscateLink) obj.obfuscateLink = item.obfuscateLink;
    if (item.description) obj.description = item.description;
    if (item.buttonLink) obj.buttonLink = item.buttonLink;
    if (item.buttonLinkLabel) obj.buttonLinkLabel = item.buttonLinkLabel;
    return obj;
  });
  const itemsJson = JSON.stringify(items);
  return `{{ card_list(${itemsJson}) }}`;
}
globalThis.window = {
  pageHost: process.env.PAGE_HOST || "",
  pageLocale: process.env.PAGE_LOCALE || "en",
  // @ts-ignore
  location: {
    origin: process.env.PAGE_ORIGIN || ""
  },
  pagesUriList: [],
  // @ts-ignore
  Promise,
  // Ajouter d'autres propriétés globales nécessaires
  ...globalThis
};
globalThis.document = {
  querySelector: () => null,
  // @ts-ignore
  createElement: () => ({})
};
const TOOL_MAP = {
  header: Header,
  paragraph: Paragraph,
  list: List,
  quote: Quote,
  code: CodeBlock,
  codeBlock: CodeBlock,
  image: Image,
  gallery: Gallery,
  table: Table,
  delimiter: Delimiter,
  raw: Raw,
  embed: Embed,
  attaches: Attaches,
  pages_list: { exportToMarkdown: exportPagesListToMarkdown },
  card_list: { exportToMarkdown: exportCardListToMarkdown }
};
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
    const markdown = await ToolClass.exportToMarkdown(block.data || {}, block.tunes);
    return markdown || "";
  } catch (error) {
    console.error(`Error converting block type "${block.type}":`, error);
    return "";
  }
}
async function main() {
  let jsonContent = "";
  if (process.argv[2]) {
    jsonContent = process.argv[2];
  } else {
    const chunks = [];
    for await (const chunk of process.stdin) {
      chunks.push(chunk);
    }
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
    const markdownBlocks = await Promise.all(
      editorData.blocks.map((block) => convertBlock(block))
    );
    const filteredBlocks = markdownBlocks.filter((content) => content !== "");
    const markdown = filteredBlocks.join("\n\n");
    console.log(markdown);
    process.exit(0);
  } catch (error) {
    console.error("Erreur lors de la conversion:", error.message);
    if (error.stack) {
      console.error(error.stack);
    }
    process.exit(1);
  }
}
main();
