#!/usr/bin/env node
var ml = Object.create;
var mt = Object.defineProperty;
var Fl = Object.getOwnPropertyDescriptor;
var gl = Object.getOwnPropertyNames;
var El = Object.getPrototypeOf, Cl = Object.prototype.hasOwnProperty;
var x = (e, r) => () => (r || e((r = { exports: {} }).exports, r), r.exports), zn = (e, r) => {
  for (var t in r) mt(e, t, { get: r[t], enumerable: true });
}, vl = (e, r, t, n) => {
  if (r && typeof r == "object" || typeof r == "function") for (let u of gl(r)) !Cl.call(e, u) && u !== t && mt(e, u, { get: () => r[u], enumerable: !(n = Fl(r, u)) || n.enumerable });
  return e;
};
var Le = (e, r, t) => (t = e != null ? ml(El(e)) : {}, vl(mt(t, "default", { value: e, enumerable: true }), e));
var kr = x((sF, Wn) => {
  Wn.exports = wl;
  function wl(e) {
    return String(e).replace(/\s+/g, " ");
  }
});
var Ki = x((MC, Hi) => {
  Hi.exports = Gf;
  var Dr = 9, zr = 10, We = 32, Rf = 33, Mf = 58, Ve = 91, Uf = 92, Nt = 93, pr = 94, Wr = 96, Vr = 4, Yf = 1024;
  function Gf(e) {
    var r = this.Parser, t = this.Compiler;
    zf(r) && Vf(r, e), Wf(t) && $f(t);
  }
  function zf(e) {
    return !!(e && e.prototype && e.prototype.blockTokenizers);
  }
  function Wf(e) {
    return !!(e && e.prototype && e.prototype.visitors);
  }
  function Vf(e, r) {
    for (var t = r || {}, n = e.prototype, u = n.blockTokenizers, i = n.inlineTokenizers, a = n.blockMethods, o = n.inlineMethods, s = u.definition, f = i.reference, c = [], l = -1, D = a.length, m; ++l < D; ) m = a[l], !(m === "newline" || m === "indentedCode" || m === "paragraph" || m === "footnoteDefinition") && c.push([m]);
    c.push(["footnoteDefinition"]), t.inlineNotes && (It(o, "reference", "inlineNote"), i.inlineNote = F), It(a, "definition", "footnoteDefinition"), It(o, "reference", "footnoteCall"), u.definition = E, u.footnoteDefinition = p, i.footnoteCall = h, i.reference = g, n.interruptFootnoteDefinition = c, g.locator = f.locator, h.locator = v, F.locator = A;
    function p(b, d, y) {
      for (var w = this, C = w.interruptFootnoteDefinition, k = w.offset, B = d.length + 1, T = 0, _ = [], I, S, O, q, N, ce, H, P, ne, Q, Ce, ve, Y; T < B && (q = d.charCodeAt(T), !(q !== Dr && q !== We)); ) T++;
      if (d.charCodeAt(T++) === Ve && d.charCodeAt(T++) === pr) {
        for (S = T; T < B; ) {
          if (q = d.charCodeAt(T), q !== q || q === zr || q === Dr || q === We) return;
          if (q === Nt) {
            O = T, T++;
            break;
          }
          T++;
        }
        if (!(O === void 0 || S === O || d.charCodeAt(T++) !== Mf)) {
          if (y) return true;
          for (I = d.slice(S, O), N = b.now(), ne = 0, Q = 0, Ce = T, ve = []; T < B; ) {
            if (q = d.charCodeAt(T), q !== q || q === zr) Y = { start: ne, contentStart: Ce || T, contentEnd: T, end: T }, ve.push(Y), q === zr && (ne = T + 1, Q = 0, Ce = void 0, Y.end = ne);
            else if (Q !== void 0) if (q === We || q === Dr) Q += q === We ? 1 : Vr - Q % Vr, Q > Vr && (Q = void 0, Ce = T);
            else {
              if (Q < Vr && Y && (Y.contentStart === Y.contentEnd || jf(C, u, w, [b, d.slice(T, Yf), true]))) break;
              Q = void 0, Ce = T;
            }
            T++;
          }
          for (T = -1, B = ve.length; B > 0 && (Y = ve[B - 1], Y.contentStart === Y.contentEnd); ) B--;
          for (ce = b(d.slice(0, Y.contentEnd)); ++T < B; ) Y = ve[T], k[N.line + T] = (k[N.line + T] || 0) + (Y.contentStart - Y.start), _.push(d.slice(Y.contentStart, Y.end));
          return H = w.enterBlock(), P = w.tokenizeBlock(_.join(""), N), H(), ce({ type: "footnoteDefinition", identifier: I.toLowerCase(), label: I, children: P });
        }
      }
    }
    function h(b, d, y) {
      var w = d.length + 1, C = 0, k, B, T, _;
      if (d.charCodeAt(C++) === Ve && d.charCodeAt(C++) === pr) {
        for (B = C; C < w; ) {
          if (_ = d.charCodeAt(C), _ !== _ || _ === zr || _ === Dr || _ === We) return;
          if (_ === Nt) {
            T = C, C++;
            break;
          }
          C++;
        }
        if (!(T === void 0 || B === T)) return y ? true : (k = d.slice(B, T), b(d.slice(0, C))({ type: "footnoteReference", identifier: k.toLowerCase(), label: k }));
      }
    }
    function F(b, d, y) {
      var w = this, C = d.length + 1, k = 0, B = 0, T, _, I, S, O, q, N;
      if (d.charCodeAt(k++) === pr && d.charCodeAt(k++) === Ve) {
        for (I = k; k < C; ) {
          if (_ = d.charCodeAt(k), _ !== _) return;
          if (q === void 0) if (_ === Uf) k += 2;
          else if (_ === Ve) B++, k++;
          else if (_ === Nt) if (B === 0) {
            S = k, k++;
            break;
          } else B--, k++;
          else if (_ === Wr) {
            for (O = k, q = 1; d.charCodeAt(O + q) === Wr; ) q++;
            k += q;
          } else k++;
          else if (_ === Wr) {
            for (O = k, N = 1; d.charCodeAt(O + N) === Wr; ) N++;
            k += N, q === N && (q = void 0), N = void 0;
          } else k++;
        }
        if (S !== void 0) return y ? true : (T = b.now(), T.column += 2, T.offset += 2, b(d.slice(0, k))({ type: "footnote", children: w.tokenizeInline(d.slice(I, S), T) }));
      }
    }
    function g(b, d, y) {
      var w = 0;
      if (d.charCodeAt(w) === Rf && w++, d.charCodeAt(w) === Ve && d.charCodeAt(w + 1) !== pr) return f.call(this, b, d, y);
    }
    function E(b, d, y) {
      for (var w = 0, C = d.charCodeAt(w); C === We || C === Dr; ) C = d.charCodeAt(++w);
      if (C === Ve && d.charCodeAt(w + 1) !== pr) return s.call(this, b, d, y);
    }
    function v(b, d) {
      return b.indexOf("[", d);
    }
    function A(b, d) {
      return b.indexOf("^[", d);
    }
  }
  function $f(e) {
    var r = e.prototype.visitors, t = "    ";
    r.footnote = n, r.footnoteReference = u, r.footnoteDefinition = i;
    function n(a) {
      return "^[" + this.all(a).join("") + "]";
    }
    function u(a) {
      return "[^" + (a.label || a.identifier) + "]";
    }
    function i(a) {
      for (var o = this.all(a).join(`

`).split(`
`), s = 0, f = o.length, c; ++s < f; ) c = o[s], c !== "" && (o[s] = t + c);
      return "[^" + (a.label || a.identifier) + "]: " + o.join(`
`);
    }
  }
  function It(e, r, t) {
    e.splice(e.indexOf(r), 0, t);
  }
  function jf(e, r, t, n) {
    for (var u = e.length, i = -1; ++i < u; ) if (r[e[i][0]].apply(t, n)) return true;
    return false;
  }
});
var Lt = x((Pt) => {
  Pt.isRemarkParser = Hf;
  Pt.isRemarkCompiler = Kf;
  function Hf(e) {
    return !!(e && e.prototype && e.prototype.blockTokenizers);
  }
  function Kf(e) {
    return !!(e && e.prototype && e.prototype.visitors);
  }
});
var tu = x((YC, ru) => {
  var Xi = Lt();
  ru.exports = Zf;
  var Ji = 9, Qi = 32, $r = 36, Xf = 48, Jf = 57, Zi = 92, Qf = ["math", "math-inline"], eu = "math-display";
  function Zf(e) {
    let r = this.Parser, t = this.Compiler;
    Xi.isRemarkParser(r) && eD(r, e), Xi.isRemarkCompiler(t) && rD(t);
  }
  function eD(e, r) {
    let t = e.prototype, n = t.inlineMethods;
    i.locator = u, t.inlineTokenizers.math = i, n.splice(n.indexOf("text"), 0, "math");
    function u(a, o) {
      return a.indexOf("$", o);
    }
    function i(a, o, s) {
      let f = o.length, c = false, l = false, D = 0, m, p, h, F, g, E, v;
      if (o.charCodeAt(D) === Zi && (l = true, D++), o.charCodeAt(D) === $r) {
        if (D++, l) return s ? true : a(o.slice(0, D))({ type: "text", value: "$" });
        if (o.charCodeAt(D) === $r && (c = true, D++), h = o.charCodeAt(D), !(h === Qi || h === Ji)) {
          for (F = D; D < f; ) {
            if (p = h, h = o.charCodeAt(D + 1), p === $r) {
              if (m = o.charCodeAt(D - 1), m !== Qi && m !== Ji && (h !== h || h < Xf || h > Jf) && (!c || h === $r)) {
                g = D - 1, D++, c && D++, E = D;
                break;
              }
            } else p === Zi && (D++, h = o.charCodeAt(D + 1));
            D++;
          }
          if (E !== void 0) return s ? true : (v = o.slice(F, g + 1), a(o.slice(0, E))({ type: "inlineMath", value: v, data: { hName: "span", hProperties: { className: Qf.concat(c && r.inlineMathDouble ? [eu] : []) }, hChildren: [{ type: "text", value: v }] } }));
        }
      }
    }
  }
  function rD(e) {
    let r = e.prototype;
    r.visitors.inlineMath = t;
    function t(n) {
      let u = "$";
      return (n.data && n.data.hProperties && n.data.hProperties.className || []).includes(eu) && (u = "$$"), u + n.value + u;
    }
  }
});
var ou = x((GC, au) => {
  var nu = Lt();
  au.exports = uD;
  var iu = 10, hr = 32, Rt = 36, uu = `
`, tD = "$", nD = 2, iD = ["math", "math-display"];
  function uD() {
    let e = this.Parser, r = this.Compiler;
    nu.isRemarkParser(e) && aD(e), nu.isRemarkCompiler(r) && oD(r);
  }
  function aD(e) {
    let r = e.prototype, t = r.blockMethods, n = r.interruptParagraph, u = r.interruptList, i = r.interruptBlockquote;
    r.blockTokenizers.math = a, t.splice(t.indexOf("fencedCode") + 1, 0, "math"), n.splice(n.indexOf("fencedCode") + 1, 0, ["math"]), u.splice(u.indexOf("fencedCode") + 1, 0, ["math"]), i.splice(i.indexOf("fencedCode") + 1, 0, ["math"]);
    function a(o, s, f) {
      var c = s.length, l = 0;
      let D, m, p, h, F, g, E, v, A, b, d;
      for (; l < c && s.charCodeAt(l) === hr; ) l++;
      for (F = l; l < c && s.charCodeAt(l) === Rt; ) l++;
      if (g = l - F, !(g < nD)) {
        for (; l < c && s.charCodeAt(l) === hr; ) l++;
        for (E = l; l < c; ) {
          if (D = s.charCodeAt(l), D === Rt) return;
          if (D === iu) break;
          l++;
        }
        if (s.charCodeAt(l) === iu) {
          if (f) return true;
          for (m = [], E !== l && m.push(s.slice(E, l)), l++, p = s.indexOf(uu, l + 1), p = p === -1 ? c : p; l < c; ) {
            for (v = false, b = l, d = p, h = p, A = 0; h > b && s.charCodeAt(h - 1) === hr; ) h--;
            for (; h > b && s.charCodeAt(h - 1) === Rt; ) A++, h--;
            for (g <= A && s.indexOf(tD, b) === h && (v = true, d = h); b <= d && b - l < F && s.charCodeAt(b) === hr; ) b++;
            if (v) for (; d > b && s.charCodeAt(d - 1) === hr; ) d--;
            if ((!v || b !== d) && m.push(s.slice(b, d)), v) break;
            l = p + 1, p = s.indexOf(uu, l + 1), p = p === -1 ? c : p;
          }
          return m = m.join(`
`), o(s.slice(0, p))({ type: "math", value: m, data: { hName: "div", hProperties: { className: iD.concat() }, hChildren: [{ type: "text", value: m }] } });
        }
      }
    }
  }
  function oD(e) {
    let r = e.prototype;
    r.visitors.math = t;
    function t(n) {
      return `$$
` + n.value + `
$$`;
    }
  }
});
var cu = x((zC, su) => {
  var sD = tu(), cD = ou();
  su.exports = lD;
  function lD(e) {
    var r = e || {};
    cD.call(this, r), sD.call(this, r);
  }
});
var Ne = x((WC, lu) => {
  lu.exports = DD;
  var fD = Object.prototype.hasOwnProperty;
  function DD() {
    for (var e = {}, r = 0; r < arguments.length; r++) {
      var t = arguments[r];
      for (var n in t) fD.call(t, n) && (e[n] = t[n]);
    }
    return e;
  }
});
var fu = x((VC, Mt) => {
  typeof Object.create == "function" ? Mt.exports = function(r, t) {
    t && (r.super_ = t, r.prototype = Object.create(t.prototype, { constructor: { value: r, enumerable: false, writable: true, configurable: true } }));
  } : Mt.exports = function(r, t) {
    if (t) {
      r.super_ = t;
      var n = function() {
      };
      n.prototype = t.prototype, r.prototype = new n(), r.prototype.constructor = r;
    }
  };
});
var hu = x(($C, pu) => {
  var pD = Ne(), Du = fu();
  pu.exports = hD;
  function hD(e) {
    var r, t, n;
    Du(i, e), Du(u, i), r = i.prototype;
    for (t in r) n = r[t], n && typeof n == "object" && (r[t] = "concat" in n ? n.concat() : pD(n));
    return i;
    function u(a) {
      return e.apply(this, a);
    }
    function i() {
      return this instanceof i ? e.apply(this, arguments) : new u(arguments);
    }
  }
});
var mu = x((jC, du) => {
  du.exports = dD;
  function dD(e, r, t) {
    return n;
    function n() {
      var u = t || this, i = u[e];
      return u[e] = !r, a;
      function a() {
        u[e] = i;
      }
    }
  }
});
var gu = x((HC, Fu) => {
  Fu.exports = mD;
  function mD(e) {
    for (var r = String(e), t = [], n = /\r?\n|\r/g; n.exec(r); ) t.push(n.lastIndex);
    return t.push(r.length + 1), { toPoint: u, toPosition: u, toOffset: i };
    function u(a) {
      var o = -1;
      if (a > -1 && a < t[t.length - 1]) {
        for (; ++o < t.length; ) if (t[o] > a) return { line: o + 1, column: a - (t[o - 1] || 0) + 1, offset: a };
      }
      return {};
    }
    function i(a) {
      var o = a && a.line, s = a && a.column, f;
      return !isNaN(o) && !isNaN(s) && o - 1 in t && (f = (t[o - 2] || 0) + s - 1 || 0), f > -1 && f < t[t.length - 1] ? f : -1;
    }
  }
});
var Cu = x((KC, Eu) => {
  Eu.exports = FD;
  var Ut = "\\";
  function FD(e, r) {
    return t;
    function t(n) {
      for (var u = 0, i = n.indexOf(Ut), a = e[r], o = [], s; i !== -1; ) o.push(n.slice(u, i)), u = i + 1, s = n.charAt(u), (!s || a.indexOf(s) === -1) && o.push(Ut), i = n.indexOf(Ut, u + 1);
      return o.push(n.slice(u)), o.join("");
    }
  }
});
var vu = x((XC, gD) => {
  gD.exports = { AElig: "Æ", AMP: "&", Aacute: "Á", Acirc: "Â", Agrave: "À", Aring: "Å", Atilde: "Ã", Auml: "Ä", COPY: "©", Ccedil: "Ç", ETH: "Ð", Eacute: "É", Ecirc: "Ê", Egrave: "È", Euml: "Ë", GT: ">", Iacute: "Í", Icirc: "Î", Igrave: "Ì", Iuml: "Ï", LT: "<", Ntilde: "Ñ", Oacute: "Ó", Ocirc: "Ô", Ograve: "Ò", Oslash: "Ø", Otilde: "Õ", Ouml: "Ö", QUOT: '"', REG: "®", THORN: "Þ", Uacute: "Ú", Ucirc: "Û", Ugrave: "Ù", Uuml: "Ü", Yacute: "Ý", aacute: "á", acirc: "â", acute: "´", aelig: "æ", agrave: "à", amp: "&", aring: "å", atilde: "ã", auml: "ä", brvbar: "¦", ccedil: "ç", cedil: "¸", cent: "¢", copy: "©", curren: "¤", deg: "°", divide: "÷", eacute: "é", ecirc: "ê", egrave: "è", eth: "ð", euml: "ë", frac12: "½", frac14: "¼", frac34: "¾", gt: ">", iacute: "í", icirc: "î", iexcl: "¡", igrave: "ì", iquest: "¿", iuml: "ï", laquo: "«", lt: "<", macr: "¯", micro: "µ", middot: "·", nbsp: " ", not: "¬", ntilde: "ñ", oacute: "ó", ocirc: "ô", ograve: "ò", ordf: "ª", ordm: "º", oslash: "ø", otilde: "õ", ouml: "ö", para: "¶", plusmn: "±", pound: "£", quot: '"', raquo: "»", reg: "®", sect: "§", shy: "­", sup1: "¹", sup2: "²", sup3: "³", szlig: "ß", thorn: "þ", times: "×", uacute: "ú", ucirc: "û", ugrave: "ù", uml: "¨", uuml: "ü", yacute: "ý", yen: "¥", yuml: "ÿ" };
});
var Au = x((JC, ED) => {
  ED.exports = { "0": "�", "128": "€", "130": "‚", "131": "ƒ", "132": "„", "133": "…", "134": "†", "135": "‡", "136": "ˆ", "137": "‰", "138": "Š", "139": "‹", "140": "Œ", "142": "Ž", "145": "‘", "146": "’", "147": "“", "148": "”", "149": "•", "150": "–", "151": "—", "152": "˜", "153": "™", "154": "š", "155": "›", "156": "œ", "158": "ž", "159": "Ÿ" };
});
var Ie = x((QC, bu) => {
  bu.exports = CD;
  function CD(e) {
    var r = typeof e == "string" ? e.charCodeAt(0) : e;
    return r >= 48 && r <= 57;
  }
});
var yu = x((ZC, xu) => {
  xu.exports = vD;
  function vD(e) {
    var r = typeof e == "string" ? e.charCodeAt(0) : e;
    return r >= 97 && r <= 102 || r >= 65 && r <= 70 || r >= 48 && r <= 57;
  }
});
var $e = x((ev, wu) => {
  wu.exports = AD;
  function AD(e) {
    var r = typeof e == "string" ? e.charCodeAt(0) : e;
    return r >= 97 && r <= 122 || r >= 65 && r <= 90;
  }
});
var Bu = x((rv, ku) => {
  var bD = $e(), xD = Ie();
  ku.exports = yD;
  function yD(e) {
    return bD(e) || xD(e);
  }
});
var Tu = x((tv, wD) => {
  wD.exports = { AEli: "Æ", AElig: "Æ", AM: "&", AMP: "&", Aacut: "Á", Aacute: "Á", Abreve: "Ă", Acir: "Â", Acirc: "Â", Acy: "А", Afr: "𝔄", Agrav: "À", Agrave: "À", Alpha: "Α", Amacr: "Ā", And: "⩓", Aogon: "Ą", Aopf: "𝔸", ApplyFunction: "⁡", Arin: "Å", Aring: "Å", Ascr: "𝒜", Assign: "≔", Atild: "Ã", Atilde: "Ã", Aum: "Ä", Auml: "Ä", Backslash: "∖", Barv: "⫧", Barwed: "⌆", Bcy: "Б", Because: "∵", Bernoullis: "ℬ", Beta: "Β", Bfr: "𝔅", Bopf: "𝔹", Breve: "˘", Bscr: "ℬ", Bumpeq: "≎", CHcy: "Ч", COP: "©", COPY: "©", Cacute: "Ć", Cap: "⋒", CapitalDifferentialD: "ⅅ", Cayleys: "ℭ", Ccaron: "Č", Ccedi: "Ç", Ccedil: "Ç", Ccirc: "Ĉ", Cconint: "∰", Cdot: "Ċ", Cedilla: "¸", CenterDot: "·", Cfr: "ℭ", Chi: "Χ", CircleDot: "⊙", CircleMinus: "⊖", CirclePlus: "⊕", CircleTimes: "⊗", ClockwiseContourIntegral: "∲", CloseCurlyDoubleQuote: "”", CloseCurlyQuote: "’", Colon: "∷", Colone: "⩴", Congruent: "≡", Conint: "∯", ContourIntegral: "∮", Copf: "ℂ", Coproduct: "∐", CounterClockwiseContourIntegral: "∳", Cross: "⨯", Cscr: "𝒞", Cup: "⋓", CupCap: "≍", DD: "ⅅ", DDotrahd: "⤑", DJcy: "Ђ", DScy: "Ѕ", DZcy: "Џ", Dagger: "‡", Darr: "↡", Dashv: "⫤", Dcaron: "Ď", Dcy: "Д", Del: "∇", Delta: "Δ", Dfr: "𝔇", DiacriticalAcute: "´", DiacriticalDot: "˙", DiacriticalDoubleAcute: "˝", DiacriticalGrave: "`", DiacriticalTilde: "˜", Diamond: "⋄", DifferentialD: "ⅆ", Dopf: "𝔻", Dot: "¨", DotDot: "⃜", DotEqual: "≐", DoubleContourIntegral: "∯", DoubleDot: "¨", DoubleDownArrow: "⇓", DoubleLeftArrow: "⇐", DoubleLeftRightArrow: "⇔", DoubleLeftTee: "⫤", DoubleLongLeftArrow: "⟸", DoubleLongLeftRightArrow: "⟺", DoubleLongRightArrow: "⟹", DoubleRightArrow: "⇒", DoubleRightTee: "⊨", DoubleUpArrow: "⇑", DoubleUpDownArrow: "⇕", DoubleVerticalBar: "∥", DownArrow: "↓", DownArrowBar: "⤓", DownArrowUpArrow: "⇵", DownBreve: "̑", DownLeftRightVector: "⥐", DownLeftTeeVector: "⥞", DownLeftVector: "↽", DownLeftVectorBar: "⥖", DownRightTeeVector: "⥟", DownRightVector: "⇁", DownRightVectorBar: "⥗", DownTee: "⊤", DownTeeArrow: "↧", Downarrow: "⇓", Dscr: "𝒟", Dstrok: "Đ", ENG: "Ŋ", ET: "Ð", ETH: "Ð", Eacut: "É", Eacute: "É", Ecaron: "Ě", Ecir: "Ê", Ecirc: "Ê", Ecy: "Э", Edot: "Ė", Efr: "𝔈", Egrav: "È", Egrave: "È", Element: "∈", Emacr: "Ē", EmptySmallSquare: "◻", EmptyVerySmallSquare: "▫", Eogon: "Ę", Eopf: "𝔼", Epsilon: "Ε", Equal: "⩵", EqualTilde: "≂", Equilibrium: "⇌", Escr: "ℰ", Esim: "⩳", Eta: "Η", Eum: "Ë", Euml: "Ë", Exists: "∃", ExponentialE: "ⅇ", Fcy: "Ф", Ffr: "𝔉", FilledSmallSquare: "◼", FilledVerySmallSquare: "▪", Fopf: "𝔽", ForAll: "∀", Fouriertrf: "ℱ", Fscr: "ℱ", GJcy: "Ѓ", G: ">", GT: ">", Gamma: "Γ", Gammad: "Ϝ", Gbreve: "Ğ", Gcedil: "Ģ", Gcirc: "Ĝ", Gcy: "Г", Gdot: "Ġ", Gfr: "𝔊", Gg: "⋙", Gopf: "𝔾", GreaterEqual: "≥", GreaterEqualLess: "⋛", GreaterFullEqual: "≧", GreaterGreater: "⪢", GreaterLess: "≷", GreaterSlantEqual: "⩾", GreaterTilde: "≳", Gscr: "𝒢", Gt: "≫", HARDcy: "Ъ", Hacek: "ˇ", Hat: "^", Hcirc: "Ĥ", Hfr: "ℌ", HilbertSpace: "ℋ", Hopf: "ℍ", HorizontalLine: "─", Hscr: "ℋ", Hstrok: "Ħ", HumpDownHump: "≎", HumpEqual: "≏", IEcy: "Е", IJlig: "Ĳ", IOcy: "Ё", Iacut: "Í", Iacute: "Í", Icir: "Î", Icirc: "Î", Icy: "И", Idot: "İ", Ifr: "ℑ", Igrav: "Ì", Igrave: "Ì", Im: "ℑ", Imacr: "Ī", ImaginaryI: "ⅈ", Implies: "⇒", Int: "∬", Integral: "∫", Intersection: "⋂", InvisibleComma: "⁣", InvisibleTimes: "⁢", Iogon: "Į", Iopf: "𝕀", Iota: "Ι", Iscr: "ℐ", Itilde: "Ĩ", Iukcy: "І", Ium: "Ï", Iuml: "Ï", Jcirc: "Ĵ", Jcy: "Й", Jfr: "𝔍", Jopf: "𝕁", Jscr: "𝒥", Jsercy: "Ј", Jukcy: "Є", KHcy: "Х", KJcy: "Ќ", Kappa: "Κ", Kcedil: "Ķ", Kcy: "К", Kfr: "𝔎", Kopf: "𝕂", Kscr: "𝒦", LJcy: "Љ", L: "<", LT: "<", Lacute: "Ĺ", Lambda: "Λ", Lang: "⟪", Laplacetrf: "ℒ", Larr: "↞", Lcaron: "Ľ", Lcedil: "Ļ", Lcy: "Л", LeftAngleBracket: "⟨", LeftArrow: "←", LeftArrowBar: "⇤", LeftArrowRightArrow: "⇆", LeftCeiling: "⌈", LeftDoubleBracket: "⟦", LeftDownTeeVector: "⥡", LeftDownVector: "⇃", LeftDownVectorBar: "⥙", LeftFloor: "⌊", LeftRightArrow: "↔", LeftRightVector: "⥎", LeftTee: "⊣", LeftTeeArrow: "↤", LeftTeeVector: "⥚", LeftTriangle: "⊲", LeftTriangleBar: "⧏", LeftTriangleEqual: "⊴", LeftUpDownVector: "⥑", LeftUpTeeVector: "⥠", LeftUpVector: "↿", LeftUpVectorBar: "⥘", LeftVector: "↼", LeftVectorBar: "⥒", Leftarrow: "⇐", Leftrightarrow: "⇔", LessEqualGreater: "⋚", LessFullEqual: "≦", LessGreater: "≶", LessLess: "⪡", LessSlantEqual: "⩽", LessTilde: "≲", Lfr: "𝔏", Ll: "⋘", Lleftarrow: "⇚", Lmidot: "Ŀ", LongLeftArrow: "⟵", LongLeftRightArrow: "⟷", LongRightArrow: "⟶", Longleftarrow: "⟸", Longleftrightarrow: "⟺", Longrightarrow: "⟹", Lopf: "𝕃", LowerLeftArrow: "↙", LowerRightArrow: "↘", Lscr: "ℒ", Lsh: "↰", Lstrok: "Ł", Lt: "≪", Map: "⤅", Mcy: "М", MediumSpace: " ", Mellintrf: "ℳ", Mfr: "𝔐", MinusPlus: "∓", Mopf: "𝕄", Mscr: "ℳ", Mu: "Μ", NJcy: "Њ", Nacute: "Ń", Ncaron: "Ň", Ncedil: "Ņ", Ncy: "Н", NegativeMediumSpace: "​", NegativeThickSpace: "​", NegativeThinSpace: "​", NegativeVeryThinSpace: "​", NestedGreaterGreater: "≫", NestedLessLess: "≪", NewLine: `
`, Nfr: "𝔑", NoBreak: "⁠", NonBreakingSpace: " ", Nopf: "ℕ", Not: "⫬", NotCongruent: "≢", NotCupCap: "≭", NotDoubleVerticalBar: "∦", NotElement: "∉", NotEqual: "≠", NotEqualTilde: "≂̸", NotExists: "∄", NotGreater: "≯", NotGreaterEqual: "≱", NotGreaterFullEqual: "≧̸", NotGreaterGreater: "≫̸", NotGreaterLess: "≹", NotGreaterSlantEqual: "⩾̸", NotGreaterTilde: "≵", NotHumpDownHump: "≎̸", NotHumpEqual: "≏̸", NotLeftTriangle: "⋪", NotLeftTriangleBar: "⧏̸", NotLeftTriangleEqual: "⋬", NotLess: "≮", NotLessEqual: "≰", NotLessGreater: "≸", NotLessLess: "≪̸", NotLessSlantEqual: "⩽̸", NotLessTilde: "≴", NotNestedGreaterGreater: "⪢̸", NotNestedLessLess: "⪡̸", NotPrecedes: "⊀", NotPrecedesEqual: "⪯̸", NotPrecedesSlantEqual: "⋠", NotReverseElement: "∌", NotRightTriangle: "⋫", NotRightTriangleBar: "⧐̸", NotRightTriangleEqual: "⋭", NotSquareSubset: "⊏̸", NotSquareSubsetEqual: "⋢", NotSquareSuperset: "⊐̸", NotSquareSupersetEqual: "⋣", NotSubset: "⊂⃒", NotSubsetEqual: "⊈", NotSucceeds: "⊁", NotSucceedsEqual: "⪰̸", NotSucceedsSlantEqual: "⋡", NotSucceedsTilde: "≿̸", NotSuperset: "⊃⃒", NotSupersetEqual: "⊉", NotTilde: "≁", NotTildeEqual: "≄", NotTildeFullEqual: "≇", NotTildeTilde: "≉", NotVerticalBar: "∤", Nscr: "𝒩", Ntild: "Ñ", Ntilde: "Ñ", Nu: "Ν", OElig: "Œ", Oacut: "Ó", Oacute: "Ó", Ocir: "Ô", Ocirc: "Ô", Ocy: "О", Odblac: "Ő", Ofr: "𝔒", Ograv: "Ò", Ograve: "Ò", Omacr: "Ō", Omega: "Ω", Omicron: "Ο", Oopf: "𝕆", OpenCurlyDoubleQuote: "“", OpenCurlyQuote: "‘", Or: "⩔", Oscr: "𝒪", Oslas: "Ø", Oslash: "Ø", Otild: "Õ", Otilde: "Õ", Otimes: "⨷", Oum: "Ö", Ouml: "Ö", OverBar: "‾", OverBrace: "⏞", OverBracket: "⎴", OverParenthesis: "⏜", PartialD: "∂", Pcy: "П", Pfr: "𝔓", Phi: "Φ", Pi: "Π", PlusMinus: "±", Poincareplane: "ℌ", Popf: "ℙ", Pr: "⪻", Precedes: "≺", PrecedesEqual: "⪯", PrecedesSlantEqual: "≼", PrecedesTilde: "≾", Prime: "″", Product: "∏", Proportion: "∷", Proportional: "∝", Pscr: "𝒫", Psi: "Ψ", QUO: '"', QUOT: '"', Qfr: "𝔔", Qopf: "ℚ", Qscr: "𝒬", RBarr: "⤐", RE: "®", REG: "®", Racute: "Ŕ", Rang: "⟫", Rarr: "↠", Rarrtl: "⤖", Rcaron: "Ř", Rcedil: "Ŗ", Rcy: "Р", Re: "ℜ", ReverseElement: "∋", ReverseEquilibrium: "⇋", ReverseUpEquilibrium: "⥯", Rfr: "ℜ", Rho: "Ρ", RightAngleBracket: "⟩", RightArrow: "→", RightArrowBar: "⇥", RightArrowLeftArrow: "⇄", RightCeiling: "⌉", RightDoubleBracket: "⟧", RightDownTeeVector: "⥝", RightDownVector: "⇂", RightDownVectorBar: "⥕", RightFloor: "⌋", RightTee: "⊢", RightTeeArrow: "↦", RightTeeVector: "⥛", RightTriangle: "⊳", RightTriangleBar: "⧐", RightTriangleEqual: "⊵", RightUpDownVector: "⥏", RightUpTeeVector: "⥜", RightUpVector: "↾", RightUpVectorBar: "⥔", RightVector: "⇀", RightVectorBar: "⥓", Rightarrow: "⇒", Ropf: "ℝ", RoundImplies: "⥰", Rrightarrow: "⇛", Rscr: "ℛ", Rsh: "↱", RuleDelayed: "⧴", SHCHcy: "Щ", SHcy: "Ш", SOFTcy: "Ь", Sacute: "Ś", Sc: "⪼", Scaron: "Š", Scedil: "Ş", Scirc: "Ŝ", Scy: "С", Sfr: "𝔖", ShortDownArrow: "↓", ShortLeftArrow: "←", ShortRightArrow: "→", ShortUpArrow: "↑", Sigma: "Σ", SmallCircle: "∘", Sopf: "𝕊", Sqrt: "√", Square: "□", SquareIntersection: "⊓", SquareSubset: "⊏", SquareSubsetEqual: "⊑", SquareSuperset: "⊐", SquareSupersetEqual: "⊒", SquareUnion: "⊔", Sscr: "𝒮", Star: "⋆", Sub: "⋐", Subset: "⋐", SubsetEqual: "⊆", Succeeds: "≻", SucceedsEqual: "⪰", SucceedsSlantEqual: "≽", SucceedsTilde: "≿", SuchThat: "∋", Sum: "∑", Sup: "⋑", Superset: "⊃", SupersetEqual: "⊇", Supset: "⋑", THOR: "Þ", THORN: "Þ", TRADE: "™", TSHcy: "Ћ", TScy: "Ц", Tab: "	", Tau: "Τ", Tcaron: "Ť", Tcedil: "Ţ", Tcy: "Т", Tfr: "𝔗", Therefore: "∴", Theta: "Θ", ThickSpace: "  ", ThinSpace: " ", Tilde: "∼", TildeEqual: "≃", TildeFullEqual: "≅", TildeTilde: "≈", Topf: "𝕋", TripleDot: "⃛", Tscr: "𝒯", Tstrok: "Ŧ", Uacut: "Ú", Uacute: "Ú", Uarr: "↟", Uarrocir: "⥉", Ubrcy: "Ў", Ubreve: "Ŭ", Ucir: "Û", Ucirc: "Û", Ucy: "У", Udblac: "Ű", Ufr: "𝔘", Ugrav: "Ù", Ugrave: "Ù", Umacr: "Ū", UnderBar: "_", UnderBrace: "⏟", UnderBracket: "⎵", UnderParenthesis: "⏝", Union: "⋃", UnionPlus: "⊎", Uogon: "Ų", Uopf: "𝕌", UpArrow: "↑", UpArrowBar: "⤒", UpArrowDownArrow: "⇅", UpDownArrow: "↕", UpEquilibrium: "⥮", UpTee: "⊥", UpTeeArrow: "↥", Uparrow: "⇑", Updownarrow: "⇕", UpperLeftArrow: "↖", UpperRightArrow: "↗", Upsi: "ϒ", Upsilon: "Υ", Uring: "Ů", Uscr: "𝒰", Utilde: "Ũ", Uum: "Ü", Uuml: "Ü", VDash: "⊫", Vbar: "⫫", Vcy: "В", Vdash: "⊩", Vdashl: "⫦", Vee: "⋁", Verbar: "‖", Vert: "‖", VerticalBar: "∣", VerticalLine: "|", VerticalSeparator: "❘", VerticalTilde: "≀", VeryThinSpace: " ", Vfr: "𝔙", Vopf: "𝕍", Vscr: "𝒱", Vvdash: "⊪", Wcirc: "Ŵ", Wedge: "⋀", Wfr: "𝔚", Wopf: "𝕎", Wscr: "𝒲", Xfr: "𝔛", Xi: "Ξ", Xopf: "𝕏", Xscr: "𝒳", YAcy: "Я", YIcy: "Ї", YUcy: "Ю", Yacut: "Ý", Yacute: "Ý", Ycirc: "Ŷ", Ycy: "Ы", Yfr: "𝔜", Yopf: "𝕐", Yscr: "𝒴", Yuml: "Ÿ", ZHcy: "Ж", Zacute: "Ź", Zcaron: "Ž", Zcy: "З", Zdot: "Ż", ZeroWidthSpace: "​", Zeta: "Ζ", Zfr: "ℨ", Zopf: "ℤ", Zscr: "𝒵", aacut: "á", aacute: "á", abreve: "ă", ac: "∾", acE: "∾̳", acd: "∿", acir: "â", acirc: "â", acut: "´", acute: "´", acy: "а", aeli: "æ", aelig: "æ", af: "⁡", afr: "𝔞", agrav: "à", agrave: "à", alefsym: "ℵ", aleph: "ℵ", alpha: "α", amacr: "ā", amalg: "⨿", am: "&", amp: "&", and: "∧", andand: "⩕", andd: "⩜", andslope: "⩘", andv: "⩚", ang: "∠", ange: "⦤", angle: "∠", angmsd: "∡", angmsdaa: "⦨", angmsdab: "⦩", angmsdac: "⦪", angmsdad: "⦫", angmsdae: "⦬", angmsdaf: "⦭", angmsdag: "⦮", angmsdah: "⦯", angrt: "∟", angrtvb: "⊾", angrtvbd: "⦝", angsph: "∢", angst: "Å", angzarr: "⍼", aogon: "ą", aopf: "𝕒", ap: "≈", apE: "⩰", apacir: "⩯", ape: "≊", apid: "≋", apos: "'", approx: "≈", approxeq: "≊", arin: "å", aring: "å", ascr: "𝒶", ast: "*", asymp: "≈", asympeq: "≍", atild: "ã", atilde: "ã", aum: "ä", auml: "ä", awconint: "∳", awint: "⨑", bNot: "⫭", backcong: "≌", backepsilon: "϶", backprime: "‵", backsim: "∽", backsimeq: "⋍", barvee: "⊽", barwed: "⌅", barwedge: "⌅", bbrk: "⎵", bbrktbrk: "⎶", bcong: "≌", bcy: "б", bdquo: "„", becaus: "∵", because: "∵", bemptyv: "⦰", bepsi: "϶", bernou: "ℬ", beta: "β", beth: "ℶ", between: "≬", bfr: "𝔟", bigcap: "⋂", bigcirc: "◯", bigcup: "⋃", bigodot: "⨀", bigoplus: "⨁", bigotimes: "⨂", bigsqcup: "⨆", bigstar: "★", bigtriangledown: "▽", bigtriangleup: "△", biguplus: "⨄", bigvee: "⋁", bigwedge: "⋀", bkarow: "⤍", blacklozenge: "⧫", blacksquare: "▪", blacktriangle: "▴", blacktriangledown: "▾", blacktriangleleft: "◂", blacktriangleright: "▸", blank: "␣", blk12: "▒", blk14: "░", blk34: "▓", block: "█", bne: "=⃥", bnequiv: "≡⃥", bnot: "⌐", bopf: "𝕓", bot: "⊥", bottom: "⊥", bowtie: "⋈", boxDL: "╗", boxDR: "╔", boxDl: "╖", boxDr: "╓", boxH: "═", boxHD: "╦", boxHU: "╩", boxHd: "╤", boxHu: "╧", boxUL: "╝", boxUR: "╚", boxUl: "╜", boxUr: "╙", boxV: "║", boxVH: "╬", boxVL: "╣", boxVR: "╠", boxVh: "╫", boxVl: "╢", boxVr: "╟", boxbox: "⧉", boxdL: "╕", boxdR: "╒", boxdl: "┐", boxdr: "┌", boxh: "─", boxhD: "╥", boxhU: "╨", boxhd: "┬", boxhu: "┴", boxminus: "⊟", boxplus: "⊞", boxtimes: "⊠", boxuL: "╛", boxuR: "╘", boxul: "┘", boxur: "└", boxv: "│", boxvH: "╪", boxvL: "╡", boxvR: "╞", boxvh: "┼", boxvl: "┤", boxvr: "├", bprime: "‵", breve: "˘", brvba: "¦", brvbar: "¦", bscr: "𝒷", bsemi: "⁏", bsim: "∽", bsime: "⋍", bsol: "\\", bsolb: "⧅", bsolhsub: "⟈", bull: "•", bullet: "•", bump: "≎", bumpE: "⪮", bumpe: "≏", bumpeq: "≏", cacute: "ć", cap: "∩", capand: "⩄", capbrcup: "⩉", capcap: "⩋", capcup: "⩇", capdot: "⩀", caps: "∩︀", caret: "⁁", caron: "ˇ", ccaps: "⩍", ccaron: "č", ccedi: "ç", ccedil: "ç", ccirc: "ĉ", ccups: "⩌", ccupssm: "⩐", cdot: "ċ", cedi: "¸", cedil: "¸", cemptyv: "⦲", cen: "¢", cent: "¢", centerdot: "·", cfr: "𝔠", chcy: "ч", check: "✓", checkmark: "✓", chi: "χ", cir: "○", cirE: "⧃", circ: "ˆ", circeq: "≗", circlearrowleft: "↺", circlearrowright: "↻", circledR: "®", circledS: "Ⓢ", circledast: "⊛", circledcirc: "⊚", circleddash: "⊝", cire: "≗", cirfnint: "⨐", cirmid: "⫯", cirscir: "⧂", clubs: "♣", clubsuit: "♣", colon: ":", colone: "≔", coloneq: "≔", comma: ",", commat: "@", comp: "∁", compfn: "∘", complement: "∁", complexes: "ℂ", cong: "≅", congdot: "⩭", conint: "∮", copf: "𝕔", coprod: "∐", cop: "©", copy: "©", copysr: "℗", crarr: "↵", cross: "✗", cscr: "𝒸", csub: "⫏", csube: "⫑", csup: "⫐", csupe: "⫒", ctdot: "⋯", cudarrl: "⤸", cudarrr: "⤵", cuepr: "⋞", cuesc: "⋟", cularr: "↶", cularrp: "⤽", cup: "∪", cupbrcap: "⩈", cupcap: "⩆", cupcup: "⩊", cupdot: "⊍", cupor: "⩅", cups: "∪︀", curarr: "↷", curarrm: "⤼", curlyeqprec: "⋞", curlyeqsucc: "⋟", curlyvee: "⋎", curlywedge: "⋏", curre: "¤", curren: "¤", curvearrowleft: "↶", curvearrowright: "↷", cuvee: "⋎", cuwed: "⋏", cwconint: "∲", cwint: "∱", cylcty: "⌭", dArr: "⇓", dHar: "⥥", dagger: "†", daleth: "ℸ", darr: "↓", dash: "‐", dashv: "⊣", dbkarow: "⤏", dblac: "˝", dcaron: "ď", dcy: "д", dd: "ⅆ", ddagger: "‡", ddarr: "⇊", ddotseq: "⩷", de: "°", deg: "°", delta: "δ", demptyv: "⦱", dfisht: "⥿", dfr: "𝔡", dharl: "⇃", dharr: "⇂", diam: "⋄", diamond: "⋄", diamondsuit: "♦", diams: "♦", die: "¨", digamma: "ϝ", disin: "⋲", div: "÷", divid: "÷", divide: "÷", divideontimes: "⋇", divonx: "⋇", djcy: "ђ", dlcorn: "⌞", dlcrop: "⌍", dollar: "$", dopf: "𝕕", dot: "˙", doteq: "≐", doteqdot: "≑", dotminus: "∸", dotplus: "∔", dotsquare: "⊡", doublebarwedge: "⌆", downarrow: "↓", downdownarrows: "⇊", downharpoonleft: "⇃", downharpoonright: "⇂", drbkarow: "⤐", drcorn: "⌟", drcrop: "⌌", dscr: "𝒹", dscy: "ѕ", dsol: "⧶", dstrok: "đ", dtdot: "⋱", dtri: "▿", dtrif: "▾", duarr: "⇵", duhar: "⥯", dwangle: "⦦", dzcy: "џ", dzigrarr: "⟿", eDDot: "⩷", eDot: "≑", eacut: "é", eacute: "é", easter: "⩮", ecaron: "ě", ecir: "ê", ecirc: "ê", ecolon: "≕", ecy: "э", edot: "ė", ee: "ⅇ", efDot: "≒", efr: "𝔢", eg: "⪚", egrav: "è", egrave: "è", egs: "⪖", egsdot: "⪘", el: "⪙", elinters: "⏧", ell: "ℓ", els: "⪕", elsdot: "⪗", emacr: "ē", empty: "∅", emptyset: "∅", emptyv: "∅", emsp13: " ", emsp14: " ", emsp: " ", eng: "ŋ", ensp: " ", eogon: "ę", eopf: "𝕖", epar: "⋕", eparsl: "⧣", eplus: "⩱", epsi: "ε", epsilon: "ε", epsiv: "ϵ", eqcirc: "≖", eqcolon: "≕", eqsim: "≂", eqslantgtr: "⪖", eqslantless: "⪕", equals: "=", equest: "≟", equiv: "≡", equivDD: "⩸", eqvparsl: "⧥", erDot: "≓", erarr: "⥱", escr: "ℯ", esdot: "≐", esim: "≂", eta: "η", et: "ð", eth: "ð", eum: "ë", euml: "ë", euro: "€", excl: "!", exist: "∃", expectation: "ℰ", exponentiale: "ⅇ", fallingdotseq: "≒", fcy: "ф", female: "♀", ffilig: "ﬃ", fflig: "ﬀ", ffllig: "ﬄ", ffr: "𝔣", filig: "ﬁ", fjlig: "fj", flat: "♭", fllig: "ﬂ", fltns: "▱", fnof: "ƒ", fopf: "𝕗", forall: "∀", fork: "⋔", forkv: "⫙", fpartint: "⨍", frac1: "¼", frac12: "½", frac13: "⅓", frac14: "¼", frac15: "⅕", frac16: "⅙", frac18: "⅛", frac23: "⅔", frac25: "⅖", frac3: "¾", frac34: "¾", frac35: "⅗", frac38: "⅜", frac45: "⅘", frac56: "⅚", frac58: "⅝", frac78: "⅞", frasl: "⁄", frown: "⌢", fscr: "𝒻", gE: "≧", gEl: "⪌", gacute: "ǵ", gamma: "γ", gammad: "ϝ", gap: "⪆", gbreve: "ğ", gcirc: "ĝ", gcy: "г", gdot: "ġ", ge: "≥", gel: "⋛", geq: "≥", geqq: "≧", geqslant: "⩾", ges: "⩾", gescc: "⪩", gesdot: "⪀", gesdoto: "⪂", gesdotol: "⪄", gesl: "⋛︀", gesles: "⪔", gfr: "𝔤", gg: "≫", ggg: "⋙", gimel: "ℷ", gjcy: "ѓ", gl: "≷", glE: "⪒", gla: "⪥", glj: "⪤", gnE: "≩", gnap: "⪊", gnapprox: "⪊", gne: "⪈", gneq: "⪈", gneqq: "≩", gnsim: "⋧", gopf: "𝕘", grave: "`", gscr: "ℊ", gsim: "≳", gsime: "⪎", gsiml: "⪐", g: ">", gt: ">", gtcc: "⪧", gtcir: "⩺", gtdot: "⋗", gtlPar: "⦕", gtquest: "⩼", gtrapprox: "⪆", gtrarr: "⥸", gtrdot: "⋗", gtreqless: "⋛", gtreqqless: "⪌", gtrless: "≷", gtrsim: "≳", gvertneqq: "≩︀", gvnE: "≩︀", hArr: "⇔", hairsp: " ", half: "½", hamilt: "ℋ", hardcy: "ъ", harr: "↔", harrcir: "⥈", harrw: "↭", hbar: "ℏ", hcirc: "ĥ", hearts: "♥", heartsuit: "♥", hellip: "…", hercon: "⊹", hfr: "𝔥", hksearow: "⤥", hkswarow: "⤦", hoarr: "⇿", homtht: "∻", hookleftarrow: "↩", hookrightarrow: "↪", hopf: "𝕙", horbar: "―", hscr: "𝒽", hslash: "ℏ", hstrok: "ħ", hybull: "⁃", hyphen: "‐", iacut: "í", iacute: "í", ic: "⁣", icir: "î", icirc: "î", icy: "и", iecy: "е", iexc: "¡", iexcl: "¡", iff: "⇔", ifr: "𝔦", igrav: "ì", igrave: "ì", ii: "ⅈ", iiiint: "⨌", iiint: "∭", iinfin: "⧜", iiota: "℩", ijlig: "ĳ", imacr: "ī", image: "ℑ", imagline: "ℐ", imagpart: "ℑ", imath: "ı", imof: "⊷", imped: "Ƶ", in: "∈", incare: "℅", infin: "∞", infintie: "⧝", inodot: "ı", int: "∫", intcal: "⊺", integers: "ℤ", intercal: "⊺", intlarhk: "⨗", intprod: "⨼", iocy: "ё", iogon: "į", iopf: "𝕚", iota: "ι", iprod: "⨼", iques: "¿", iquest: "¿", iscr: "𝒾", isin: "∈", isinE: "⋹", isindot: "⋵", isins: "⋴", isinsv: "⋳", isinv: "∈", it: "⁢", itilde: "ĩ", iukcy: "і", ium: "ï", iuml: "ï", jcirc: "ĵ", jcy: "й", jfr: "𝔧", jmath: "ȷ", jopf: "𝕛", jscr: "𝒿", jsercy: "ј", jukcy: "є", kappa: "κ", kappav: "ϰ", kcedil: "ķ", kcy: "к", kfr: "𝔨", kgreen: "ĸ", khcy: "х", kjcy: "ќ", kopf: "𝕜", kscr: "𝓀", lAarr: "⇚", lArr: "⇐", lAtail: "⤛", lBarr: "⤎", lE: "≦", lEg: "⪋", lHar: "⥢", lacute: "ĺ", laemptyv: "⦴", lagran: "ℒ", lambda: "λ", lang: "⟨", langd: "⦑", langle: "⟨", lap: "⪅", laqu: "«", laquo: "«", larr: "←", larrb: "⇤", larrbfs: "⤟", larrfs: "⤝", larrhk: "↩", larrlp: "↫", larrpl: "⤹", larrsim: "⥳", larrtl: "↢", lat: "⪫", latail: "⤙", late: "⪭", lates: "⪭︀", lbarr: "⤌", lbbrk: "❲", lbrace: "{", lbrack: "[", lbrke: "⦋", lbrksld: "⦏", lbrkslu: "⦍", lcaron: "ľ", lcedil: "ļ", lceil: "⌈", lcub: "{", lcy: "л", ldca: "⤶", ldquo: "“", ldquor: "„", ldrdhar: "⥧", ldrushar: "⥋", ldsh: "↲", le: "≤", leftarrow: "←", leftarrowtail: "↢", leftharpoondown: "↽", leftharpoonup: "↼", leftleftarrows: "⇇", leftrightarrow: "↔", leftrightarrows: "⇆", leftrightharpoons: "⇋", leftrightsquigarrow: "↭", leftthreetimes: "⋋", leg: "⋚", leq: "≤", leqq: "≦", leqslant: "⩽", les: "⩽", lescc: "⪨", lesdot: "⩿", lesdoto: "⪁", lesdotor: "⪃", lesg: "⋚︀", lesges: "⪓", lessapprox: "⪅", lessdot: "⋖", lesseqgtr: "⋚", lesseqqgtr: "⪋", lessgtr: "≶", lesssim: "≲", lfisht: "⥼", lfloor: "⌊", lfr: "𝔩", lg: "≶", lgE: "⪑", lhard: "↽", lharu: "↼", lharul: "⥪", lhblk: "▄", ljcy: "љ", ll: "≪", llarr: "⇇", llcorner: "⌞", llhard: "⥫", lltri: "◺", lmidot: "ŀ", lmoust: "⎰", lmoustache: "⎰", lnE: "≨", lnap: "⪉", lnapprox: "⪉", lne: "⪇", lneq: "⪇", lneqq: "≨", lnsim: "⋦", loang: "⟬", loarr: "⇽", lobrk: "⟦", longleftarrow: "⟵", longleftrightarrow: "⟷", longmapsto: "⟼", longrightarrow: "⟶", looparrowleft: "↫", looparrowright: "↬", lopar: "⦅", lopf: "𝕝", loplus: "⨭", lotimes: "⨴", lowast: "∗", lowbar: "_", loz: "◊", lozenge: "◊", lozf: "⧫", lpar: "(", lparlt: "⦓", lrarr: "⇆", lrcorner: "⌟", lrhar: "⇋", lrhard: "⥭", lrm: "‎", lrtri: "⊿", lsaquo: "‹", lscr: "𝓁", lsh: "↰", lsim: "≲", lsime: "⪍", lsimg: "⪏", lsqb: "[", lsquo: "‘", lsquor: "‚", lstrok: "ł", l: "<", lt: "<", ltcc: "⪦", ltcir: "⩹", ltdot: "⋖", lthree: "⋋", ltimes: "⋉", ltlarr: "⥶", ltquest: "⩻", ltrPar: "⦖", ltri: "◃", ltrie: "⊴", ltrif: "◂", lurdshar: "⥊", luruhar: "⥦", lvertneqq: "≨︀", lvnE: "≨︀", mDDot: "∺", mac: "¯", macr: "¯", male: "♂", malt: "✠", maltese: "✠", map: "↦", mapsto: "↦", mapstodown: "↧", mapstoleft: "↤", mapstoup: "↥", marker: "▮", mcomma: "⨩", mcy: "м", mdash: "—", measuredangle: "∡", mfr: "𝔪", mho: "℧", micr: "µ", micro: "µ", mid: "∣", midast: "*", midcir: "⫰", middo: "·", middot: "·", minus: "−", minusb: "⊟", minusd: "∸", minusdu: "⨪", mlcp: "⫛", mldr: "…", mnplus: "∓", models: "⊧", mopf: "𝕞", mp: "∓", mscr: "𝓂", mstpos: "∾", mu: "μ", multimap: "⊸", mumap: "⊸", nGg: "⋙̸", nGt: "≫⃒", nGtv: "≫̸", nLeftarrow: "⇍", nLeftrightarrow: "⇎", nLl: "⋘̸", nLt: "≪⃒", nLtv: "≪̸", nRightarrow: "⇏", nVDash: "⊯", nVdash: "⊮", nabla: "∇", nacute: "ń", nang: "∠⃒", nap: "≉", napE: "⩰̸", napid: "≋̸", napos: "ŉ", napprox: "≉", natur: "♮", natural: "♮", naturals: "ℕ", nbs: " ", nbsp: " ", nbump: "≎̸", nbumpe: "≏̸", ncap: "⩃", ncaron: "ň", ncedil: "ņ", ncong: "≇", ncongdot: "⩭̸", ncup: "⩂", ncy: "н", ndash: "–", ne: "≠", neArr: "⇗", nearhk: "⤤", nearr: "↗", nearrow: "↗", nedot: "≐̸", nequiv: "≢", nesear: "⤨", nesim: "≂̸", nexist: "∄", nexists: "∄", nfr: "𝔫", ngE: "≧̸", nge: "≱", ngeq: "≱", ngeqq: "≧̸", ngeqslant: "⩾̸", nges: "⩾̸", ngsim: "≵", ngt: "≯", ngtr: "≯", nhArr: "⇎", nharr: "↮", nhpar: "⫲", ni: "∋", nis: "⋼", nisd: "⋺", niv: "∋", njcy: "њ", nlArr: "⇍", nlE: "≦̸", nlarr: "↚", nldr: "‥", nle: "≰", nleftarrow: "↚", nleftrightarrow: "↮", nleq: "≰", nleqq: "≦̸", nleqslant: "⩽̸", nles: "⩽̸", nless: "≮", nlsim: "≴", nlt: "≮", nltri: "⋪", nltrie: "⋬", nmid: "∤", nopf: "𝕟", no: "¬", not: "¬", notin: "∉", notinE: "⋹̸", notindot: "⋵̸", notinva: "∉", notinvb: "⋷", notinvc: "⋶", notni: "∌", notniva: "∌", notnivb: "⋾", notnivc: "⋽", npar: "∦", nparallel: "∦", nparsl: "⫽⃥", npart: "∂̸", npolint: "⨔", npr: "⊀", nprcue: "⋠", npre: "⪯̸", nprec: "⊀", npreceq: "⪯̸", nrArr: "⇏", nrarr: "↛", nrarrc: "⤳̸", nrarrw: "↝̸", nrightarrow: "↛", nrtri: "⋫", nrtrie: "⋭", nsc: "⊁", nsccue: "⋡", nsce: "⪰̸", nscr: "𝓃", nshortmid: "∤", nshortparallel: "∦", nsim: "≁", nsime: "≄", nsimeq: "≄", nsmid: "∤", nspar: "∦", nsqsube: "⋢", nsqsupe: "⋣", nsub: "⊄", nsubE: "⫅̸", nsube: "⊈", nsubset: "⊂⃒", nsubseteq: "⊈", nsubseteqq: "⫅̸", nsucc: "⊁", nsucceq: "⪰̸", nsup: "⊅", nsupE: "⫆̸", nsupe: "⊉", nsupset: "⊃⃒", nsupseteq: "⊉", nsupseteqq: "⫆̸", ntgl: "≹", ntild: "ñ", ntilde: "ñ", ntlg: "≸", ntriangleleft: "⋪", ntrianglelefteq: "⋬", ntriangleright: "⋫", ntrianglerighteq: "⋭", nu: "ν", num: "#", numero: "№", numsp: " ", nvDash: "⊭", nvHarr: "⤄", nvap: "≍⃒", nvdash: "⊬", nvge: "≥⃒", nvgt: ">⃒", nvinfin: "⧞", nvlArr: "⤂", nvle: "≤⃒", nvlt: "<⃒", nvltrie: "⊴⃒", nvrArr: "⤃", nvrtrie: "⊵⃒", nvsim: "∼⃒", nwArr: "⇖", nwarhk: "⤣", nwarr: "↖", nwarrow: "↖", nwnear: "⤧", oS: "Ⓢ", oacut: "ó", oacute: "ó", oast: "⊛", ocir: "ô", ocirc: "ô", ocy: "о", odash: "⊝", odblac: "ő", odiv: "⨸", odot: "⊙", odsold: "⦼", oelig: "œ", ofcir: "⦿", ofr: "𝔬", ogon: "˛", ograv: "ò", ograve: "ò", ogt: "⧁", ohbar: "⦵", ohm: "Ω", oint: "∮", olarr: "↺", olcir: "⦾", olcross: "⦻", oline: "‾", olt: "⧀", omacr: "ō", omega: "ω", omicron: "ο", omid: "⦶", ominus: "⊖", oopf: "𝕠", opar: "⦷", operp: "⦹", oplus: "⊕", or: "∨", orarr: "↻", ord: "º", order: "ℴ", orderof: "ℴ", ordf: "ª", ordm: "º", origof: "⊶", oror: "⩖", orslope: "⩗", orv: "⩛", oscr: "ℴ", oslas: "ø", oslash: "ø", osol: "⊘", otild: "õ", otilde: "õ", otimes: "⊗", otimesas: "⨶", oum: "ö", ouml: "ö", ovbar: "⌽", par: "¶", para: "¶", parallel: "∥", parsim: "⫳", parsl: "⫽", part: "∂", pcy: "п", percnt: "%", period: ".", permil: "‰", perp: "⊥", pertenk: "‱", pfr: "𝔭", phi: "φ", phiv: "ϕ", phmmat: "ℳ", phone: "☎", pi: "π", pitchfork: "⋔", piv: "ϖ", planck: "ℏ", planckh: "ℎ", plankv: "ℏ", plus: "+", plusacir: "⨣", plusb: "⊞", pluscir: "⨢", plusdo: "∔", plusdu: "⨥", pluse: "⩲", plusm: "±", plusmn: "±", plussim: "⨦", plustwo: "⨧", pm: "±", pointint: "⨕", popf: "𝕡", poun: "£", pound: "£", pr: "≺", prE: "⪳", prap: "⪷", prcue: "≼", pre: "⪯", prec: "≺", precapprox: "⪷", preccurlyeq: "≼", preceq: "⪯", precnapprox: "⪹", precneqq: "⪵", precnsim: "⋨", precsim: "≾", prime: "′", primes: "ℙ", prnE: "⪵", prnap: "⪹", prnsim: "⋨", prod: "∏", profalar: "⌮", profline: "⌒", profsurf: "⌓", prop: "∝", propto: "∝", prsim: "≾", prurel: "⊰", pscr: "𝓅", psi: "ψ", puncsp: " ", qfr: "𝔮", qint: "⨌", qopf: "𝕢", qprime: "⁗", qscr: "𝓆", quaternions: "ℍ", quatint: "⨖", quest: "?", questeq: "≟", quo: '"', quot: '"', rAarr: "⇛", rArr: "⇒", rAtail: "⤜", rBarr: "⤏", rHar: "⥤", race: "∽̱", racute: "ŕ", radic: "√", raemptyv: "⦳", rang: "⟩", rangd: "⦒", range: "⦥", rangle: "⟩", raqu: "»", raquo: "»", rarr: "→", rarrap: "⥵", rarrb: "⇥", rarrbfs: "⤠", rarrc: "⤳", rarrfs: "⤞", rarrhk: "↪", rarrlp: "↬", rarrpl: "⥅", rarrsim: "⥴", rarrtl: "↣", rarrw: "↝", ratail: "⤚", ratio: "∶", rationals: "ℚ", rbarr: "⤍", rbbrk: "❳", rbrace: "}", rbrack: "]", rbrke: "⦌", rbrksld: "⦎", rbrkslu: "⦐", rcaron: "ř", rcedil: "ŗ", rceil: "⌉", rcub: "}", rcy: "р", rdca: "⤷", rdldhar: "⥩", rdquo: "”", rdquor: "”", rdsh: "↳", real: "ℜ", realine: "ℛ", realpart: "ℜ", reals: "ℝ", rect: "▭", re: "®", reg: "®", rfisht: "⥽", rfloor: "⌋", rfr: "𝔯", rhard: "⇁", rharu: "⇀", rharul: "⥬", rho: "ρ", rhov: "ϱ", rightarrow: "→", rightarrowtail: "↣", rightharpoondown: "⇁", rightharpoonup: "⇀", rightleftarrows: "⇄", rightleftharpoons: "⇌", rightrightarrows: "⇉", rightsquigarrow: "↝", rightthreetimes: "⋌", ring: "˚", risingdotseq: "≓", rlarr: "⇄", rlhar: "⇌", rlm: "‏", rmoust: "⎱", rmoustache: "⎱", rnmid: "⫮", roang: "⟭", roarr: "⇾", robrk: "⟧", ropar: "⦆", ropf: "𝕣", roplus: "⨮", rotimes: "⨵", rpar: ")", rpargt: "⦔", rppolint: "⨒", rrarr: "⇉", rsaquo: "›", rscr: "𝓇", rsh: "↱", rsqb: "]", rsquo: "’", rsquor: "’", rthree: "⋌", rtimes: "⋊", rtri: "▹", rtrie: "⊵", rtrif: "▸", rtriltri: "⧎", ruluhar: "⥨", rx: "℞", sacute: "ś", sbquo: "‚", sc: "≻", scE: "⪴", scap: "⪸", scaron: "š", sccue: "≽", sce: "⪰", scedil: "ş", scirc: "ŝ", scnE: "⪶", scnap: "⪺", scnsim: "⋩", scpolint: "⨓", scsim: "≿", scy: "с", sdot: "⋅", sdotb: "⊡", sdote: "⩦", seArr: "⇘", searhk: "⤥", searr: "↘", searrow: "↘", sec: "§", sect: "§", semi: ";", seswar: "⤩", setminus: "∖", setmn: "∖", sext: "✶", sfr: "𝔰", sfrown: "⌢", sharp: "♯", shchcy: "щ", shcy: "ш", shortmid: "∣", shortparallel: "∥", sh: "­", shy: "­", sigma: "σ", sigmaf: "ς", sigmav: "ς", sim: "∼", simdot: "⩪", sime: "≃", simeq: "≃", simg: "⪞", simgE: "⪠", siml: "⪝", simlE: "⪟", simne: "≆", simplus: "⨤", simrarr: "⥲", slarr: "←", smallsetminus: "∖", smashp: "⨳", smeparsl: "⧤", smid: "∣", smile: "⌣", smt: "⪪", smte: "⪬", smtes: "⪬︀", softcy: "ь", sol: "/", solb: "⧄", solbar: "⌿", sopf: "𝕤", spades: "♠", spadesuit: "♠", spar: "∥", sqcap: "⊓", sqcaps: "⊓︀", sqcup: "⊔", sqcups: "⊔︀", sqsub: "⊏", sqsube: "⊑", sqsubset: "⊏", sqsubseteq: "⊑", sqsup: "⊐", sqsupe: "⊒", sqsupset: "⊐", sqsupseteq: "⊒", squ: "□", square: "□", squarf: "▪", squf: "▪", srarr: "→", sscr: "𝓈", ssetmn: "∖", ssmile: "⌣", sstarf: "⋆", star: "☆", starf: "★", straightepsilon: "ϵ", straightphi: "ϕ", strns: "¯", sub: "⊂", subE: "⫅", subdot: "⪽", sube: "⊆", subedot: "⫃", submult: "⫁", subnE: "⫋", subne: "⊊", subplus: "⪿", subrarr: "⥹", subset: "⊂", subseteq: "⊆", subseteqq: "⫅", subsetneq: "⊊", subsetneqq: "⫋", subsim: "⫇", subsub: "⫕", subsup: "⫓", succ: "≻", succapprox: "⪸", succcurlyeq: "≽", succeq: "⪰", succnapprox: "⪺", succneqq: "⪶", succnsim: "⋩", succsim: "≿", sum: "∑", sung: "♪", sup: "⊃", sup1: "¹", sup2: "²", sup3: "³", supE: "⫆", supdot: "⪾", supdsub: "⫘", supe: "⊇", supedot: "⫄", suphsol: "⟉", suphsub: "⫗", suplarr: "⥻", supmult: "⫂", supnE: "⫌", supne: "⊋", supplus: "⫀", supset: "⊃", supseteq: "⊇", supseteqq: "⫆", supsetneq: "⊋", supsetneqq: "⫌", supsim: "⫈", supsub: "⫔", supsup: "⫖", swArr: "⇙", swarhk: "⤦", swarr: "↙", swarrow: "↙", swnwar: "⤪", szli: "ß", szlig: "ß", target: "⌖", tau: "τ", tbrk: "⎴", tcaron: "ť", tcedil: "ţ", tcy: "т", tdot: "⃛", telrec: "⌕", tfr: "𝔱", there4: "∴", therefore: "∴", theta: "θ", thetasym: "ϑ", thetav: "ϑ", thickapprox: "≈", thicksim: "∼", thinsp: " ", thkap: "≈", thksim: "∼", thor: "þ", thorn: "þ", tilde: "˜", time: "×", times: "×", timesb: "⊠", timesbar: "⨱", timesd: "⨰", tint: "∭", toea: "⤨", top: "⊤", topbot: "⌶", topcir: "⫱", topf: "𝕥", topfork: "⫚", tosa: "⤩", tprime: "‴", trade: "™", triangle: "▵", triangledown: "▿", triangleleft: "◃", trianglelefteq: "⊴", triangleq: "≜", triangleright: "▹", trianglerighteq: "⊵", tridot: "◬", trie: "≜", triminus: "⨺", triplus: "⨹", trisb: "⧍", tritime: "⨻", trpezium: "⏢", tscr: "𝓉", tscy: "ц", tshcy: "ћ", tstrok: "ŧ", twixt: "≬", twoheadleftarrow: "↞", twoheadrightarrow: "↠", uArr: "⇑", uHar: "⥣", uacut: "ú", uacute: "ú", uarr: "↑", ubrcy: "ў", ubreve: "ŭ", ucir: "û", ucirc: "û", ucy: "у", udarr: "⇅", udblac: "ű", udhar: "⥮", ufisht: "⥾", ufr: "𝔲", ugrav: "ù", ugrave: "ù", uharl: "↿", uharr: "↾", uhblk: "▀", ulcorn: "⌜", ulcorner: "⌜", ulcrop: "⌏", ultri: "◸", umacr: "ū", um: "¨", uml: "¨", uogon: "ų", uopf: "𝕦", uparrow: "↑", updownarrow: "↕", upharpoonleft: "↿", upharpoonright: "↾", uplus: "⊎", upsi: "υ", upsih: "ϒ", upsilon: "υ", upuparrows: "⇈", urcorn: "⌝", urcorner: "⌝", urcrop: "⌎", uring: "ů", urtri: "◹", uscr: "𝓊", utdot: "⋰", utilde: "ũ", utri: "▵", utrif: "▴", uuarr: "⇈", uum: "ü", uuml: "ü", uwangle: "⦧", vArr: "⇕", vBar: "⫨", vBarv: "⫩", vDash: "⊨", vangrt: "⦜", varepsilon: "ϵ", varkappa: "ϰ", varnothing: "∅", varphi: "ϕ", varpi: "ϖ", varpropto: "∝", varr: "↕", varrho: "ϱ", varsigma: "ς", varsubsetneq: "⊊︀", varsubsetneqq: "⫋︀", varsupsetneq: "⊋︀", varsupsetneqq: "⫌︀", vartheta: "ϑ", vartriangleleft: "⊲", vartriangleright: "⊳", vcy: "в", vdash: "⊢", vee: "∨", veebar: "⊻", veeeq: "≚", vellip: "⋮", verbar: "|", vert: "|", vfr: "𝔳", vltri: "⊲", vnsub: "⊂⃒", vnsup: "⊃⃒", vopf: "𝕧", vprop: "∝", vrtri: "⊳", vscr: "𝓋", vsubnE: "⫋︀", vsubne: "⊊︀", vsupnE: "⫌︀", vsupne: "⊋︀", vzigzag: "⦚", wcirc: "ŵ", wedbar: "⩟", wedge: "∧", wedgeq: "≙", weierp: "℘", wfr: "𝔴", wopf: "𝕨", wp: "℘", wr: "≀", wreath: "≀", wscr: "𝓌", xcap: "⋂", xcirc: "◯", xcup: "⋃", xdtri: "▽", xfr: "𝔵", xhArr: "⟺", xharr: "⟷", xi: "ξ", xlArr: "⟸", xlarr: "⟵", xmap: "⟼", xnis: "⋻", xodot: "⨀", xopf: "𝕩", xoplus: "⨁", xotime: "⨂", xrArr: "⟹", xrarr: "⟶", xscr: "𝓍", xsqcup: "⨆", xuplus: "⨄", xutri: "△", xvee: "⋁", xwedge: "⋀", yacut: "ý", yacute: "ý", yacy: "я", ycirc: "ŷ", ycy: "ы", ye: "¥", yen: "¥", yfr: "𝔶", yicy: "ї", yopf: "𝕪", yscr: "𝓎", yucy: "ю", yum: "ÿ", yuml: "ÿ", zacute: "ź", zcaron: "ž", zcy: "з", zdot: "ż", zeetrf: "ℨ", zeta: "ζ", zfr: "𝔷", zhcy: "ж", zigrarr: "⇝", zopf: "𝕫", zscr: "𝓏", zwj: "‍", zwnj: "‌" };
});
var Ou = x((nv, qu) => {
  var _u = Tu();
  qu.exports = BD;
  var kD = {}.hasOwnProperty;
  function BD(e) {
    return kD.call(_u, e) ? _u[e] : false;
  }
});
var dr = x((iv, Vu) => {
  var Su = vu(), Nu = Au(), TD = Ie(), _D = yu(), Ru = Bu(), qD = Ou();
  Vu.exports = WD;
  var OD = {}.hasOwnProperty, je = String.fromCharCode, SD = Function.prototype, Iu = { warning: null, reference: null, text: null, warningContext: null, referenceContext: null, textContext: null, position: {}, additional: null, attribute: false, nonTerminated: true }, ND = 9, Pu = 10, ID = 12, PD = 32, Lu = 38, LD = 59, RD = 60, MD = 61, UD = 35, YD = 88, GD = 120, zD = 65533, He = "named", Gt = "hexadecimal", zt = "decimal", Wt = {};
  Wt[Gt] = 16;
  Wt[zt] = 10;
  var jr = {};
  jr[He] = Ru;
  jr[zt] = TD;
  jr[Gt] = _D;
  var Mu = 1, Uu = 2, Yu = 3, Gu = 4, zu = 5, Yt = 6, Wu = 7, ye = {};
  ye[Mu] = "Named character references must be terminated by a semicolon";
  ye[Uu] = "Numeric character references must be terminated by a semicolon";
  ye[Yu] = "Named character references cannot be empty";
  ye[Gu] = "Numeric character references cannot be empty";
  ye[zu] = "Named character references must be known";
  ye[Yt] = "Numeric character references cannot be disallowed";
  ye[Wu] = "Numeric character references cannot be outside the permissible Unicode range";
  function WD(e, r) {
    var t = {}, n, u;
    r || (r = {});
    for (u in Iu) n = r[u], t[u] = n ?? Iu[u];
    return (t.position.indent || t.position.start) && (t.indent = t.position.indent || [], t.position = t.position.start), VD(e, t);
  }
  function VD(e, r) {
    var t = r.additional, n = r.nonTerminated, u = r.text, i = r.reference, a = r.warning, o = r.textContext, s = r.referenceContext, f = r.warningContext, c = r.position, l = r.indent || [], D = e.length, m = 0, p = -1, h = c.column || 1, F = c.line || 1, g = "", E = [], v, A, b, d, y, w, C, k, B, T, _, I, S, O, q, N, ce, H, P;
    for (typeof t == "string" && (t = t.charCodeAt(0)), N = ne(), k = a ? Q : SD, m--, D++; ++m < D; ) if (y === Pu && (h = l[p] || 1), y = e.charCodeAt(m), y === Lu) {
      if (C = e.charCodeAt(m + 1), C === ND || C === Pu || C === ID || C === PD || C === Lu || C === RD || C !== C || t && C === t) {
        g += je(y), h++;
        continue;
      }
      for (S = m + 1, I = S, P = S, C === UD ? (P = ++I, C = e.charCodeAt(P), C === YD || C === GD ? (O = Gt, P = ++I) : O = zt) : O = He, v = "", _ = "", d = "", q = jr[O], P--; ++P < D && (C = e.charCodeAt(P), !!q(C)); ) d += je(C), O === He && OD.call(Su, d) && (v = d, _ = Su[d]);
      b = e.charCodeAt(P) === LD, b && (P++, A = O === He ? qD(d) : false, A && (v = d, _ = A)), H = 1 + P - S, !b && !n || (d ? O === He ? (b && !_ ? k(zu, 1) : (v !== d && (P = I + v.length, H = 1 + P - I, b = false), b || (B = v ? Mu : Yu, r.attribute ? (C = e.charCodeAt(P), C === MD ? (k(B, H), _ = null) : Ru(C) ? _ = null : k(B, H)) : k(B, H))), w = _) : (b || k(Uu, H), w = parseInt(d, Wt[O]), $D(w) ? (k(Wu, H), w = je(zD)) : w in Nu ? (k(Yt, H), w = Nu[w]) : (T = "", jD(w) && k(Yt, H), w > 65535 && (w -= 65536, T += je(w >>> 10 | 55296), w = 56320 | w & 1023), w = T + je(w))) : O !== He && k(Gu, H)), w ? (Ce(), N = ne(), m = P - 1, h += P - S + 1, E.push(w), ce = ne(), ce.offset++, i && i.call(s, w, { start: N, end: ce }, e.slice(S - 1, P)), N = ce) : (d = e.slice(S - 1, P), g += d, h += d.length, m = P - 1);
    } else y === 10 && (F++, p++, h = 0), y === y ? (g += je(y), h++) : Ce();
    return E.join("");
    function ne() {
      return { line: F, column: h, offset: m + (c.offset || 0) };
    }
    function Q(ve, Y) {
      var dt = ne();
      dt.column += Y, dt.offset += Y, a.call(f, ye[ve], dt, ve);
    }
    function Ce() {
      g && (E.push(g), u && u.call(o, g, { start: N, end: ne() }), g = "");
    }
  }
  function $D(e) {
    return e >= 55296 && e <= 57343 || e > 1114111;
  }
  function jD(e) {
    return e >= 1 && e <= 8 || e === 11 || e >= 13 && e <= 31 || e >= 127 && e <= 159 || e >= 64976 && e <= 65007 || (e & 65535) === 65535 || (e & 65535) === 65534;
  }
});
var Hu = x((uv, ju) => {
  var HD = Ne(), $u = dr();
  ju.exports = KD;
  function KD(e) {
    return t.raw = n, t;
    function r(i) {
      for (var a = e.offset, o = i.line, s = []; ++o && o in a; ) s.push((a[o] || 0) + 1);
      return { start: i, indent: s };
    }
    function t(i, a, o) {
      $u(i, { position: r(a), warning: u, text: o, reference: o, textContext: e, referenceContext: e });
    }
    function n(i, a, o) {
      return $u(i, HD(o, { position: r(a), warning: u }));
    }
    function u(i, a, o) {
      o !== 3 && e.file.message(i, a);
    }
  }
});
var Ju = x((av, Xu) => {
  Xu.exports = XD;
  function XD(e) {
    return r;
    function r(t, n) {
      var u = this, i = u.offset, a = [], o = u[e + "Methods"], s = u[e + "Tokenizers"], f = n.line, c = n.column, l, D, m, p, h, F;
      if (!t) return a;
      for (w.now = v, w.file = u.file, g(""); t; ) {
        for (l = -1, D = o.length, h = false; ++l < D && (p = o[l], m = s[p], !(m && (!m.onlyAtStart || u.atStart) && (!m.notInList || !u.inList) && (!m.notInBlock || !u.inBlock) && (!m.notInLink || !u.inLink) && (F = t.length, m.apply(u, [w, t]), h = F !== t.length, h))); ) ;
        h || u.file.fail(new Error("Infinite loop"), w.now());
      }
      return u.eof = v(), a;
      function g(C) {
        for (var k = -1, B = C.indexOf(`
`); B !== -1; ) f++, k = B, B = C.indexOf(`
`, B + 1);
        k === -1 ? c += C.length : c = C.length - k, f in i && (k !== -1 ? c += i[f] : c <= i[f] && (c = i[f] + 1));
      }
      function E() {
        var C = [], k = f + 1;
        return function() {
          for (var B = f + 1; k < B; ) C.push((i[k] || 0) + 1), k++;
          return C;
        };
      }
      function v() {
        var C = { line: f, column: c };
        return C.offset = u.toOffset(C), C;
      }
      function A(C) {
        this.start = C, this.end = v();
      }
      function b(C) {
        t.slice(0, C.length) !== C && u.file.fail(new Error("Incorrectly eaten value: please report this warning on https://git.io/vg5Ft"), v());
      }
      function d() {
        var C = v();
        return k;
        function k(B, T) {
          var _ = B.position, I = _ ? _.start : C, S = [], O = _ && _.end.line, q = C.line;
          if (B.position = new A(I), _ && T && _.indent) {
            if (S = _.indent, O < q) {
              for (; ++O < q; ) S.push((i[O] || 0) + 1);
              S.push(C.column);
            }
            T = S.concat(T);
          }
          return B.position.indent = T || [], B;
        }
      }
      function y(C, k) {
        var B = k ? k.children : a, T = B[B.length - 1], _;
        return T && C.type === T.type && (C.type === "text" || C.type === "blockquote") && Ku(T) && Ku(C) && (_ = C.type === "text" ? JD : QD, C = _.call(u, T, C)), C !== T && B.push(C), u.atStart && a.length !== 0 && u.exitStart(), C;
      }
      function w(C) {
        var k = E(), B = d(), T = v();
        return b(C), _.reset = I, I.test = S, _.test = S, t = t.slice(C.length), g(C), k = k(), _;
        function _(O, q) {
          return B(y(B(O), q), k);
        }
        function I() {
          var O = _.apply(null, arguments);
          return f = T.line, c = T.column, t = C + t, O;
        }
        function S() {
          var O = B({});
          return f = T.line, c = T.column, t = C + t, O.position;
        }
      }
    }
  }
  function Ku(e) {
    var r, t;
    return e.type !== "text" || !e.position ? true : (r = e.position.start, t = e.position.end, r.line !== t.line || t.column - r.column === e.value.length);
  }
  function JD(e, r) {
    return e.value += r.value, e;
  }
  function QD(e, r) {
    return this.options.commonmark || this.options.gfm ? r : (e.children = e.children.concat(r.children), e);
  }
});
var ea = x((ov, Zu) => {
  Zu.exports = Hr;
  var Vt = ["\\", "`", "*", "{", "}", "[", "]", "(", ")", "#", "+", "-", ".", "!", "_", ">"], $t = Vt.concat(["~", "|"]), Qu = $t.concat([`
`, '"', "$", "%", "&", "'", ",", "/", ":", ";", "<", "=", "?", "@", "^"]);
  Hr.default = Vt;
  Hr.gfm = $t;
  Hr.commonmark = Qu;
  function Hr(e) {
    var r = e || {};
    return r.commonmark ? Qu : r.gfm ? $t : Vt;
  }
});
var ta = x((sv, ra) => {
  ra.exports = ["address", "article", "aside", "base", "basefont", "blockquote", "body", "caption", "center", "col", "colgroup", "dd", "details", "dialog", "dir", "div", "dl", "dt", "fieldset", "figcaption", "figure", "footer", "form", "frame", "frameset", "h1", "h2", "h3", "h4", "h5", "h6", "head", "header", "hgroup", "hr", "html", "iframe", "legend", "li", "link", "main", "menu", "menuitem", "meta", "nav", "noframes", "ol", "optgroup", "option", "p", "param", "pre", "section", "source", "title", "summary", "table", "tbody", "td", "tfoot", "th", "thead", "title", "tr", "track", "ul"];
});
var jt = x((cv, na) => {
  na.exports = { position: true, gfm: true, commonmark: false, pedantic: false, blocks: ta() };
});
var ua = x((lv, ia) => {
  var ZD = Ne(), ep = ea(), rp = jt();
  ia.exports = tp;
  function tp(e) {
    var r = this, t = r.options, n, u;
    if (e == null) e = {};
    else if (typeof e == "object") e = ZD(e);
    else throw new Error("Invalid value `" + e + "` for setting `options`");
    for (n in rp) {
      if (u = e[n], u == null && (u = t[n]), n !== "blocks" && typeof u != "boolean" || n === "blocks" && typeof u != "object") throw new Error("Invalid value `" + u + "` for setting `options." + n + "`");
      e[n] = u;
    }
    return r.options = e, r.escape = ep(e), r;
  }
});
var sa = x((fv, oa) => {
  oa.exports = aa;
  function aa(e) {
    if (e == null) return ap;
    if (typeof e == "string") return up(e);
    if (typeof e == "object") return "length" in e ? ip(e) : np(e);
    if (typeof e == "function") return e;
    throw new Error("Expected function, string, or object as test");
  }
  function np(e) {
    return r;
    function r(t) {
      var n;
      for (n in e) if (t[n] !== e[n]) return false;
      return true;
    }
  }
  function ip(e) {
    for (var r = [], t = -1; ++t < e.length; ) r[t] = aa(e[t]);
    return n;
    function n() {
      for (var u = -1; ++u < r.length; ) if (r[u].apply(this, arguments)) return true;
      return false;
    }
  }
  function up(e) {
    return r;
    function r(t) {
      return !!(t && t.type === e);
    }
  }
  function ap() {
    return true;
  }
});
var la = x((Dv, ca) => {
  ca.exports = op;
  function op(e) {
    return e;
  }
});
var ha = x((pv, pa) => {
  pa.exports = Kr;
  var sp = sa(), cp = la(), fa = true, Da = "skip", Ht = false;
  Kr.CONTINUE = fa;
  Kr.SKIP = Da;
  Kr.EXIT = Ht;
  function Kr(e, r, t, n) {
    var u, i;
    typeof r == "function" && typeof t != "function" && (n = t, t = r, r = null), i = sp(r), u = n ? -1 : 1, a(e, null, [])();
    function a(o, s, f) {
      var c = typeof o == "object" && o !== null ? o : {}, l;
      return typeof c.type == "string" && (l = typeof c.tagName == "string" ? c.tagName : typeof c.name == "string" ? c.name : void 0, D.displayName = "node (" + cp(c.type + (l ? "<" + l + ">" : "")) + ")"), D;
      function D() {
        var m = f.concat(o), p = [], h, F;
        if ((!r || i(o, s, f[f.length - 1] || null)) && (p = lp(t(o, f)), p[0] === Ht)) return p;
        if (o.children && p[0] !== Da) for (F = (n ? o.children.length : -1) + u; F > -1 && F < o.children.length; ) {
          if (h = a(o.children[F], F, m)(), h[0] === Ht) return h;
          F = typeof h[1] == "number" ? h[1] : F + u;
        }
        return p;
      }
    }
  }
  function lp(e) {
    return e !== null && typeof e == "object" && "length" in e ? e : typeof e == "number" ? [fa, e] : [e];
  }
});
var ma = x((hv, da) => {
  da.exports = Jr;
  var Xr = ha(), fp = Xr.CONTINUE, Dp = Xr.SKIP, pp = Xr.EXIT;
  Jr.CONTINUE = fp;
  Jr.SKIP = Dp;
  Jr.EXIT = pp;
  function Jr(e, r, t, n) {
    typeof r == "function" && typeof t != "function" && (n = t, t = r, r = null), Xr(e, r, u, n);
    function u(i, a) {
      var o = a[a.length - 1], s = o ? o.children.indexOf(i) : null;
      return t(i, s, o);
    }
  }
});
var ga = x((dv, Fa) => {
  var hp = ma();
  Fa.exports = dp;
  function dp(e, r) {
    return hp(e, r ? mp : Fp), e;
  }
  function mp(e) {
    delete e.position;
  }
  function Fp(e) {
    e.position = void 0;
  }
});
var va = x((mv, Ca) => {
  var Ea = Ne(), gp = ga();
  Ca.exports = vp;
  var Ep = `
`, Cp = /\r\n|\r/g;
  function vp() {
    var e = this, r = String(e.file), t = { line: 1, column: 1, offset: 0 }, n = Ea(t), u;
    return r = r.replace(Cp, Ep), r.charCodeAt(0) === 65279 && (r = r.slice(1), n.column++, n.offset++), u = { type: "root", children: e.tokenizeBlock(r, n), position: { start: t, end: e.eof || Ea(t) } }, e.options.position || gp(u, true), u;
  }
});
var ba = x((Fv, Aa) => {
  var Ap = /^[ \t]*(\n|$)/;
  Aa.exports = bp;
  function bp(e, r, t) {
    for (var n, u = "", i = 0, a = r.length; i < a && (n = Ap.exec(r.slice(i)), n != null); ) i += n[0].length, u += n[0];
    if (u !== "") {
      if (t) return true;
      e(u);
    }
  }
});
var Qr = x((gv, xa) => {
  var Fe = "", Kt;
  xa.exports = xp;
  function xp(e, r) {
    if (typeof e != "string") throw new TypeError("expected a string");
    if (r === 1) return e;
    if (r === 2) return e + e;
    var t = e.length * r;
    if (Kt !== e || typeof Kt > "u") Kt = e, Fe = "";
    else if (Fe.length >= t) return Fe.substr(0, t);
    for (; t > Fe.length && r > 1; ) r & 1 && (Fe += e), r >>= 1, e += e;
    return Fe += e, Fe = Fe.substr(0, t), Fe;
  }
});
var Xt = x((Ev, ya) => {
  ya.exports = yp;
  function yp(e) {
    return String(e).replace(/\n+$/, "");
  }
});
var Ba = x((Cv, ka) => {
  var wp = Qr(), kp = Xt();
  ka.exports = _p;
  var Jt = `
`, wa = "	", Qt = " ", Bp = 4, Tp = wp(Qt, Bp);
  function _p(e, r, t) {
    for (var n = -1, u = r.length, i = "", a = "", o = "", s = "", f, c, l; ++n < u; ) if (f = r.charAt(n), l) if (l = false, i += o, a += s, o = "", s = "", f === Jt) o = f, s = f;
    else for (i += f, a += f; ++n < u; ) {
      if (f = r.charAt(n), !f || f === Jt) {
        s = f, o = f;
        break;
      }
      i += f, a += f;
    }
    else if (f === Qt && r.charAt(n + 1) === f && r.charAt(n + 2) === f && r.charAt(n + 3) === f) o += Tp, n += 3, l = true;
    else if (f === wa) o += f, l = true;
    else {
      for (c = ""; f === wa || f === Qt; ) c += f, f = r.charAt(++n);
      if (f !== Jt) break;
      o += c + f, s += f;
    }
    if (a) return t ? true : e(i)({ type: "code", lang: null, meta: null, value: kp(a) });
  }
});
var qa = x((vv, _a) => {
  _a.exports = Np;
  var Zr = `
`, mr = "	", Ke = " ", qp = "~", Ta = "`", Op = 3, Sp = 4;
  function Np(e, r, t) {
    var n = this, u = n.options.gfm, i = r.length + 1, a = 0, o = "", s, f, c, l, D, m, p, h, F, g, E, v, A;
    if (u) {
      for (; a < i && (c = r.charAt(a), !(c !== Ke && c !== mr)); ) o += c, a++;
      if (v = a, c = r.charAt(a), !(c !== qp && c !== Ta)) {
        for (a++, f = c, s = 1, o += c; a < i && (c = r.charAt(a), c === f); ) o += c, s++, a++;
        if (!(s < Op)) {
          for (; a < i && (c = r.charAt(a), !(c !== Ke && c !== mr)); ) o += c, a++;
          for (l = "", p = ""; a < i && (c = r.charAt(a), !(c === Zr || f === Ta && c === f)); ) c === Ke || c === mr ? p += c : (l += p + c, p = ""), a++;
          if (c = r.charAt(a), !(c && c !== Zr)) {
            if (t) return true;
            A = e.now(), A.column += o.length, A.offset += o.length, o += l, l = n.decode.raw(n.unescape(l), A), p && (o += p), p = "", g = "", E = "", h = "", F = "";
            for (var b = true; a < i; ) {
              if (c = r.charAt(a), h += g, F += E, g = "", E = "", c !== Zr) {
                h += c, E += c, a++;
                continue;
              }
              for (b ? (o += c, b = false) : (g += c, E += c), p = "", a++; a < i && (c = r.charAt(a), c === Ke); ) p += c, a++;
              if (g += p, E += p.slice(v), !(p.length >= Sp)) {
                for (p = ""; a < i && (c = r.charAt(a), c === f); ) p += c, a++;
                if (g += p, E += p, !(p.length < s)) {
                  for (p = ""; a < i && (c = r.charAt(a), !(c !== Ke && c !== mr)); ) g += c, E += c, a++;
                  if (!c || c === Zr) break;
                }
              }
            }
            for (o += h + g, a = -1, i = l.length; ++a < i; ) if (c = l.charAt(a), c === Ke || c === mr) D || (D = l.slice(0, a));
            else if (D) {
              m = l.slice(a);
              break;
            }
            return e(o)({ type: "code", lang: D || l || null, meta: m || null, value: F });
          }
        }
      }
    }
  }
});
var Pe = x((Xe, Oa) => {
  Xe = Oa.exports = Ip;
  function Ip(e) {
    return e.trim ? e.trim() : Xe.right(Xe.left(e));
  }
  Xe.left = function(e) {
    return e.trimLeft ? e.trimLeft() : e.replace(/^\s\s*/, "");
  };
  Xe.right = function(e) {
    if (e.trimRight) return e.trimRight();
    for (var r = /\s/, t = e.length; r.test(e.charAt(--t)); ) ;
    return e.slice(0, t + 1);
  };
});
var et = x((Av, Sa) => {
  Sa.exports = Pp;
  function Pp(e, r, t, n) {
    for (var u = e.length, i = -1, a, o; ++i < u; ) if (a = e[i], o = a[1] || {}, !(o.pedantic !== void 0 && o.pedantic !== t.options.pedantic) && !(o.commonmark !== void 0 && o.commonmark !== t.options.commonmark) && r[a[0]].apply(t, n)) return true;
    return false;
  }
});
var La = x((bv, Pa) => {
  var Lp = Pe(), Rp = et();
  Pa.exports = Mp;
  var Zt = `
`, Na = "	", en = " ", Ia = ">";
  function Mp(e, r, t) {
    for (var n = this, u = n.offset, i = n.blockTokenizers, a = n.interruptBlockquote, o = e.now(), s = o.line, f = r.length, c = [], l = [], D = [], m, p = 0, h, F, g, E, v, A, b, d; p < f && (h = r.charAt(p), !(h !== en && h !== Na)); ) p++;
    if (r.charAt(p) === Ia) {
      if (t) return true;
      for (p = 0; p < f; ) {
        for (g = r.indexOf(Zt, p), A = p, b = false, g === -1 && (g = f); p < f && (h = r.charAt(p), !(h !== en && h !== Na)); ) p++;
        if (r.charAt(p) === Ia ? (p++, b = true, r.charAt(p) === en && p++) : p = A, E = r.slice(p, g), !b && !Lp(E)) {
          p = A;
          break;
        }
        if (!b && (F = r.slice(p), Rp(a, i, n, [e, F, true]))) break;
        v = A === p ? E : r.slice(A, g), D.push(p - A), c.push(v), l.push(E), p = g + 1;
      }
      for (p = -1, f = D.length, m = e(c.join(Zt)); ++p < f; ) u[s] = (u[s] || 0) + D[p], s++;
      return d = n.enterBlock(), l = n.tokenizeBlock(l.join(Zt), o), d(), m({ type: "blockquote", children: l });
    }
  }
});
var Ua = x((xv, Ma) => {
  Ma.exports = Yp;
  var Ra = `
`, Fr = "	", gr = " ", Er = "#", Up = 6;
  function Yp(e, r, t) {
    for (var n = this, u = n.options.pedantic, i = r.length + 1, a = -1, o = e.now(), s = "", f = "", c, l, D; ++a < i; ) {
      if (c = r.charAt(a), c !== gr && c !== Fr) {
        a--;
        break;
      }
      s += c;
    }
    for (D = 0; ++a <= i; ) {
      if (c = r.charAt(a), c !== Er) {
        a--;
        break;
      }
      s += c, D++;
    }
    if (!(D > Up) && !(!D || !u && r.charAt(a + 1) === Er)) {
      for (i = r.length + 1, l = ""; ++a < i; ) {
        if (c = r.charAt(a), c !== gr && c !== Fr) {
          a--;
          break;
        }
        l += c;
      }
      if (!(!u && l.length === 0 && c && c !== Ra)) {
        if (t) return true;
        for (s += l, l = "", f = ""; ++a < i && (c = r.charAt(a), !(!c || c === Ra)); ) {
          if (c !== gr && c !== Fr && c !== Er) {
            f += l + c, l = "";
            continue;
          }
          for (; c === gr || c === Fr; ) l += c, c = r.charAt(++a);
          if (!u && f && !l && c === Er) {
            f += c;
            continue;
          }
          for (; c === Er; ) l += c, c = r.charAt(++a);
          for (; c === gr || c === Fr; ) l += c, c = r.charAt(++a);
          a--;
        }
        return o.column += s.length, o.offset += s.length, s += f + l, e(s)({ type: "heading", depth: D, children: n.tokenizeInline(f, o) });
      }
    }
  }
});
var za = x((yv, Ga) => {
  Ga.exports = Hp;
  var Gp = "	", zp = `
`, Ya = " ", Wp = "*", Vp = "-", $p = "_", jp = 3;
  function Hp(e, r, t) {
    for (var n = -1, u = r.length + 1, i = "", a, o, s, f; ++n < u && (a = r.charAt(n), !(a !== Gp && a !== Ya)); ) i += a;
    if (!(a !== Wp && a !== Vp && a !== $p)) for (o = a, i += a, s = 1, f = ""; ++n < u; ) if (a = r.charAt(n), a === o) s++, i += f + o, f = "";
    else if (a === Ya) f += a;
    else return s >= jp && (!a || a === zp) ? (i += f, t ? true : e(i)({ type: "thematicBreak" })) : void 0;
  }
});
var rn = x((wv, Va) => {
  Va.exports = Qp;
  var Wa = "	", Kp = " ", Xp = 1, Jp = 4;
  function Qp(e) {
    for (var r = 0, t = 0, n = e.charAt(r), u = {}, i, a = 0; n === Wa || n === Kp; ) {
      for (i = n === Wa ? Jp : Xp, t += i, i > 1 && (t = Math.floor(t / i) * i); a < t; ) u[++a] = r;
      n = e.charAt(++r);
    }
    return { indent: t, stops: u };
  }
});
var Ha = x((kv, ja) => {
  var Zp = Pe(), eh = Qr(), rh = rn();
  ja.exports = ih;
  var $a = `
`, th = " ", nh = "!";
  function ih(e, r) {
    var t = e.split($a), n = t.length + 1, u = 1 / 0, i = [], a, o, s;
    for (t.unshift(eh(th, r) + nh); n--; ) if (o = rh(t[n]), i[n] = o.stops, Zp(t[n]).length !== 0) if (o.indent) o.indent > 0 && o.indent < u && (u = o.indent);
    else {
      u = 1 / 0;
      break;
    }
    if (u !== 1 / 0) for (n = t.length; n--; ) {
      for (s = i[n], a = u; a && !(a in s); ) a--;
      t[n] = t[n].slice(s[a] + 1);
    }
    return t.shift(), t.join($a);
  }
});
var eo = x((Bv, Za) => {
  var uh = Pe(), ah = Qr(), Ka = Ie(), oh = rn(), sh = Ha(), ch = et();
  Za.exports = Fh;
  var tn = "*", lh = "_", Xa = "+", nn = "-", Ja = ".", ge = " ", ae = `
`, rt = "	", Qa = ")", fh = "x", we = 4, Dh = /\n\n(?!\s*$)/, ph = /^\[([ X\tx])][ \t]/, hh = /^([ \t]*)([*+-]|\d+[.)])( {1,4}(?! )| |\t|$|(?=\n))([^\n]*)/, dh = /^([ \t]*)([*+-]|\d+[.)])([ \t]+)/, mh = /^( {1,4}|\t)?/gm;
  function Fh(e, r, t) {
    for (var n = this, u = n.options.commonmark, i = n.options.pedantic, a = n.blockTokenizers, o = n.interruptList, s = 0, f = r.length, c = null, l, D, m, p, h, F, g, E, v, A, b, d, y, w, C, k, B, T, _, I = false, S, O, q, N; s < f && (p = r.charAt(s), !(p !== rt && p !== ge)); ) s++;
    if (p = r.charAt(s), p === tn || p === Xa || p === nn) h = p, m = false;
    else {
      for (m = true, D = ""; s < f && (p = r.charAt(s), !!Ka(p)); ) D += p, s++;
      if (p = r.charAt(s), !D || !(p === Ja || u && p === Qa) || t && D !== "1") return;
      c = parseInt(D, 10), h = p;
    }
    if (p = r.charAt(++s), !(p !== ge && p !== rt && (i || p !== ae && p !== ""))) {
      if (t) return true;
      for (s = 0, w = [], C = [], k = []; s < f; ) {
        for (F = r.indexOf(ae, s), g = s, E = false, N = false, F === -1 && (F = f), l = 0; s < f; ) {
          if (p = r.charAt(s), p === rt) l += we - l % we;
          else if (p === ge) l++;
          else break;
          s++;
        }
        if (B && l >= B.indent && (N = true), p = r.charAt(s), v = null, !N) {
          if (p === tn || p === Xa || p === nn) v = p, s++, l++;
          else {
            for (D = ""; s < f && (p = r.charAt(s), !!Ka(p)); ) D += p, s++;
            p = r.charAt(s), s++, D && (p === Ja || u && p === Qa) && (v = p, l += D.length + 1);
          }
          if (v) if (p = r.charAt(s), p === rt) l += we - l % we, s++;
          else if (p === ge) {
            for (q = s + we; s < q && r.charAt(s) === ge; ) s++, l++;
            s === q && r.charAt(s) === ge && (s -= we - 1, l -= we - 1);
          } else p !== ae && p !== "" && (v = null);
        }
        if (v) {
          if (!i && h !== v) break;
          E = true;
        } else !u && !N && r.charAt(g) === ge ? N = true : u && B && (N = l >= B.indent || l > we), E = false, s = g;
        if (b = r.slice(g, F), A = g === s ? b : r.slice(s, F), (v === tn || v === lh || v === nn) && a.thematicBreak.call(n, e, b, true)) break;
        if (d = y, y = !E && !uh(A).length, N && B) B.value = B.value.concat(k, b), C = C.concat(k, b), k = [];
        else if (E) k.length !== 0 && (I = true, B.value.push(""), B.trail = k.concat()), B = { value: [b], indent: l, trail: [] }, w.push(B), C = C.concat(k, b), k = [];
        else if (y) {
          if (d && !u) break;
          k.push(b);
        } else {
          if (d || ch(o, a, n, [e, b, true])) break;
          B.value = B.value.concat(k, b), C = C.concat(k, b), k = [];
        }
        s = F + 1;
      }
      for (S = e(C.join(ae)).reset({ type: "list", ordered: m, start: c, spread: I, children: [] }), T = n.enterList(), _ = n.enterBlock(), s = -1, f = w.length; ++s < f; ) B = w[s].value.join(ae), O = e.now(), e(B)(gh(n, B, O), S), B = w[s].trail.join(ae), s !== f - 1 && (B += ae), e(B);
      return T(), _(), S;
    }
  }
  function gh(e, r, t) {
    var n = e.offset, u = e.options.pedantic ? Eh : Ch, i = null, a, o;
    return r = u.apply(null, arguments), e.options.gfm && (a = r.match(ph), a && (o = a[0].length, i = a[1].toLowerCase() === fh, n[t.line] += o, r = r.slice(o))), { type: "listItem", spread: Dh.test(r), checked: i, children: e.tokenizeBlock(r, t) };
  }
  function Eh(e, r, t) {
    var n = e.offset, u = t.line;
    return r = r.replace(dh, i), u = t.line, r.replace(mh, i);
    function i(a) {
      return n[u] = (n[u] || 0) + a.length, u++, "";
    }
  }
  function Ch(e, r, t) {
    var n = e.offset, u = t.line, i, a, o, s, f, c, l;
    for (r = r.replace(hh, D), s = r.split(ae), f = sh(r, oh(i).indent).split(ae), f[0] = o, n[u] = (n[u] || 0) + a.length, u++, c = 0, l = s.length; ++c < l; ) n[u] = (n[u] || 0) + s[c].length - f[c].length, u++;
    return f.join(ae);
    function D(m, p, h, F, g) {
      return a = p + h + F, o = g, Number(h) < 10 && a.length % 2 === 1 && (h = ge + h), i = p + ah(ge, h.length) + F, i + o;
    }
  }
});
var io = x((Tv, no) => {
  no.exports = wh;
  var un = `
`, vh = "	", ro = " ", to = "=", Ah = "-", bh = 3, xh = 1, yh = 2;
  function wh(e, r, t) {
    for (var n = this, u = e.now(), i = r.length, a = -1, o = "", s, f, c, l, D; ++a < i; ) {
      if (c = r.charAt(a), c !== ro || a >= bh) {
        a--;
        break;
      }
      o += c;
    }
    for (s = "", f = ""; ++a < i; ) {
      if (c = r.charAt(a), c === un) {
        a--;
        break;
      }
      c === ro || c === vh ? f += c : (s += f + c, f = "");
    }
    if (u.column += o.length, u.offset += o.length, o += s + f, c = r.charAt(++a), l = r.charAt(++a), !(c !== un || l !== to && l !== Ah)) {
      for (o += c, f = l, D = l === to ? xh : yh; ++a < i; ) {
        if (c = r.charAt(a), c !== l) {
          if (c !== un) return;
          a--;
          break;
        }
        f += c;
      }
      return t ? true : e(o + f)({ type: "heading", depth: D, children: n.tokenizeInline(s, u) });
    }
  }
});
var on = x((an) => {
  var kh = "[a-zA-Z_:][a-zA-Z0-9:._-]*", Bh = "[^\"'=<>`\\u0000-\\u0020]+", Th = "'[^']*'", _h = '"[^"]*"', qh = "(?:" + Bh + "|" + Th + "|" + _h + ")", Oh = "(?:\\s+" + kh + "(?:\\s*=\\s*" + qh + ")?)", uo = "<[A-Za-z][A-Za-z0-9\\-]*" + Oh + "*\\s*\\/?>", ao = "<\\/[A-Za-z][A-Za-z0-9\\-]*\\s*>", Sh = "<!---->|<!--(?:-?[^>-])(?:-?[^-])*-->", Nh = "<[?].*?[?]>", Ih = "<![A-Za-z]+\\s+[^>]*>", Ph = "<!\\[CDATA\\[[\\s\\S]*?\\]\\]>";
  an.openCloseTag = new RegExp("^(?:" + uo + "|" + ao + ")");
  an.tag = new RegExp("^(?:" + uo + "|" + ao + "|" + Sh + "|" + Nh + "|" + Ih + "|" + Ph + ")");
});
var lo = x((qv, co) => {
  var Lh = on().openCloseTag;
  co.exports = Qh;
  var Rh = "	", Mh = " ", oo = `
`, Uh = "<", Yh = /^<(script|pre|style)(?=(\s|>|$))/i, Gh = /<\/(script|pre|style)>/i, zh = /^<!--/, Wh = /-->/, Vh = /^<\?/, $h = /\?>/, jh = /^<![A-Za-z]/, Hh = />/, Kh = /^<!\[CDATA\[/, Xh = /]]>/, so = /^$/, Jh = new RegExp(Lh.source + "\\s*$");
  function Qh(e, r, t) {
    for (var n = this, u = n.options.blocks.join("|"), i = new RegExp("^</?(" + u + ")(?=(\\s|/?>|$))", "i"), a = r.length, o = 0, s, f, c, l, D, m, p, h = [[Yh, Gh, true], [zh, Wh, true], [Vh, $h, true], [jh, Hh, true], [Kh, Xh, true], [i, so, true], [Jh, so, false]]; o < a && (l = r.charAt(o), !(l !== Rh && l !== Mh)); ) o++;
    if (r.charAt(o) === Uh) {
      for (s = r.indexOf(oo, o + 1), s = s === -1 ? a : s, f = r.slice(o, s), c = -1, D = h.length; ++c < D; ) if (h[c][0].test(f)) {
        m = h[c];
        break;
      }
      if (m) {
        if (t) return m[2];
        if (o = s, !m[1].test(f)) for (; o < a; ) {
          if (s = r.indexOf(oo, o + 1), s = s === -1 ? a : s, f = r.slice(o + 1, s), m[1].test(f)) {
            f && (o = s);
            break;
          }
          o = s;
        }
        return p = r.slice(0, o), e(p)({ type: "html", value: p });
      }
    }
  }
});
var oe = x((Ov, fo) => {
  fo.exports = rd;
  var Zh = String.fromCharCode, ed = /\s/;
  function rd(e) {
    return ed.test(typeof e == "number" ? Zh(e) : e.charAt(0));
  }
});
var sn = x((Sv, Do) => {
  var td = kr();
  Do.exports = nd;
  function nd(e) {
    return td(e).toLowerCase();
  }
});
var Co = x((Nv, Eo) => {
  var id = oe(), ud = sn();
  Eo.exports = cd;
  var po = '"', ho = "'", ad = "\\", Je = `
`, tt = "	", nt = " ", ln = "[", Cr = "]", od = "(", sd = ")", mo = ":", Fo = "<", go = ">";
  function cd(e, r, t) {
    for (var n = this, u = n.options.commonmark, i = 0, a = r.length, o = "", s, f, c, l, D, m, p, h; i < a && (l = r.charAt(i), !(l !== nt && l !== tt)); ) o += l, i++;
    if (l = r.charAt(i), l === ln) {
      for (i++, o += l, c = ""; i < a && (l = r.charAt(i), l !== Cr); ) l === ad && (c += l, i++, l = r.charAt(i)), c += l, i++;
      if (!(!c || r.charAt(i) !== Cr || r.charAt(i + 1) !== mo)) {
        for (m = c, o += c + Cr + mo, i = o.length, c = ""; i < a && (l = r.charAt(i), !(l !== tt && l !== nt && l !== Je)); ) o += l, i++;
        if (l = r.charAt(i), c = "", s = o, l === Fo) {
          for (i++; i < a && (l = r.charAt(i), !!cn(l)); ) c += l, i++;
          if (l = r.charAt(i), l === cn.delimiter) o += Fo + c + l, i++;
          else {
            if (u) return;
            i -= c.length + 1, c = "";
          }
        }
        if (!c) {
          for (; i < a && (l = r.charAt(i), !!ld(l)); ) c += l, i++;
          o += c;
        }
        if (c) {
          for (p = c, c = ""; i < a && (l = r.charAt(i), !(l !== tt && l !== nt && l !== Je)); ) c += l, i++;
          if (l = r.charAt(i), D = null, l === po ? D = po : l === ho ? D = ho : l === od && (D = sd), !D) c = "", i = o.length;
          else if (c) {
            for (o += c + l, i = o.length, c = ""; i < a && (l = r.charAt(i), l !== D); ) {
              if (l === Je) {
                if (i++, l = r.charAt(i), l === Je || l === D) return;
                c += Je;
              }
              c += l, i++;
            }
            if (l = r.charAt(i), l !== D) return;
            f = o, o += c + l, i++, h = c, c = "";
          } else return;
          for (; i < a && (l = r.charAt(i), !(l !== tt && l !== nt)); ) o += l, i++;
          if (l = r.charAt(i), !l || l === Je) return t ? true : (s = e(s).test().end, p = n.decode.raw(n.unescape(p), s, { nonTerminated: false }), h && (f = e(f).test().end, h = n.decode.raw(n.unescape(h), f)), e(o)({ type: "definition", identifier: ud(m), label: m, title: h || null, url: p }));
        }
      }
    }
  }
  function cn(e) {
    return e !== go && e !== ln && e !== Cr;
  }
  cn.delimiter = go;
  function ld(e) {
    return e !== ln && e !== Cr && !id(e);
  }
});
var bo = x((Iv, Ao) => {
  var fd = oe();
  Ao.exports = vd;
  var Dd = "	", it = `
`, pd = " ", hd = "-", dd = ":", md = "\\", fn = "|", Fd = 1, gd = 2, vo = "left", Ed = "center", Cd = "right";
  function vd(e, r, t) {
    var n = this, u, i, a, o, s, f, c, l, D, m, p, h, F, g, E, v, A, b, d, y, w, C;
    if (n.options.gfm) {
      for (u = 0, v = 0, f = r.length + 1, c = []; u < f; ) {
        if (y = r.indexOf(it, u), w = r.indexOf(fn, u + 1), y === -1 && (y = r.length), w === -1 || w > y) {
          if (v < gd) return;
          break;
        }
        c.push(r.slice(u, y)), v++, u = y + 1;
      }
      for (o = c.join(it), i = c.splice(1, 1)[0] || [], u = 0, f = i.length, v--, a = false, p = []; u < f; ) {
        if (D = i.charAt(u), D === fn) {
          if (m = null, a === false) {
            if (C === false) return;
          } else p.push(a), a = false;
          C = false;
        } else if (D === hd) m = true, a = a || null;
        else if (D === dd) a === vo ? a = Ed : m && a === null ? a = Cd : a = vo;
        else if (!fd(D)) return;
        u++;
      }
      if (a !== false && p.push(a), !(p.length < Fd)) {
        if (t) return true;
        for (E = -1, b = [], d = e(o).reset({ type: "table", align: p, children: b }); ++E < v; ) {
          for (A = c[E], s = { type: "tableRow", children: [] }, E && e(it), e(A).reset(s, d), f = A.length + 1, u = 0, l = "", h = "", F = true; u < f; ) {
            if (D = A.charAt(u), D === Dd || D === pd) {
              h ? l += D : e(D), u++;
              continue;
            }
            D === "" || D === fn ? F ? e(D) : ((h || D) && !F && (o = h, l.length > 1 && (D ? (o += l.slice(0, -1), l = l.charAt(l.length - 1)) : (o += l, l = "")), g = e.now(), e(o)({ type: "tableCell", children: n.tokenizeInline(h, g) }, s)), e(l + D), l = "", h = "") : (l && (h += l, l = ""), h += D, D === md && u !== f - 2 && (h += A.charAt(u + 1), u++)), F = false, u++;
          }
          E || e(it + i);
        }
        return d;
      }
    }
  }
});
var wo = x((Pv, yo) => {
  var Ad = Pe(), bd = Xt(), xd = et();
  yo.exports = kd;
  var yd = "	", vr = `
`, wd = " ", xo = 4;
  function kd(e, r, t) {
    for (var n = this, u = n.options, i = u.commonmark, a = n.blockTokenizers, o = n.interruptParagraph, s = r.indexOf(vr), f = r.length, c, l, D, m, p; s < f; ) {
      if (s === -1) {
        s = f;
        break;
      }
      if (r.charAt(s + 1) === vr) break;
      if (i) {
        for (m = 0, c = s + 1; c < f; ) {
          if (D = r.charAt(c), D === yd) {
            m = xo;
            break;
          } else if (D === wd) m++;
          else break;
          c++;
        }
        if (m >= xo && D !== vr) {
          s = r.indexOf(vr, s + 1);
          continue;
        }
      }
      if (l = r.slice(s + 1), xd(o, a, n, [e, l, true])) break;
      if (c = s, s = r.indexOf(vr, s + 1), s !== -1 && Ad(r.slice(c, s)) === "") {
        s = c;
        break;
      }
    }
    return l = r.slice(0, s), t ? true : (p = e.now(), l = bd(l), e(l)({ type: "paragraph", children: n.tokenizeInline(l, p) }));
  }
});
var Bo = x((Lv, ko) => {
  ko.exports = Bd;
  function Bd(e, r) {
    return e.indexOf("\\", r);
  }
});
var Oo = x((Rv, qo) => {
  var Td = Bo();
  qo.exports = _o;
  _o.locator = Td;
  var _d = `
`, To = "\\";
  function _o(e, r, t) {
    var n = this, u, i;
    if (r.charAt(0) === To && (u = r.charAt(1), n.escape.indexOf(u) !== -1)) return t ? true : (u === _d ? i = { type: "break" } : i = { type: "text", value: u }, e(To + u)(i));
  }
});
var Dn = x((Mv, So) => {
  So.exports = qd;
  function qd(e, r) {
    return e.indexOf("<", r);
  }
});
var Ro = x((Uv, Lo) => {
  var No = oe(), Od = dr(), Sd = Dn();
  Lo.exports = mn;
  mn.locator = Sd;
  mn.notInLink = true;
  var Io = "<", pn = ">", Po = "@", hn = "/", dn = "mailto:", ut = dn.length;
  function mn(e, r, t) {
    var n = this, u = "", i = r.length, a = 0, o = "", s = false, f = "", c, l, D, m, p;
    if (r.charAt(0) === Io) {
      for (a++, u = Io; a < i && (c = r.charAt(a), !(No(c) || c === pn || c === Po || c === ":" && r.charAt(a + 1) === hn)); ) o += c, a++;
      if (o) {
        if (f += o, o = "", c = r.charAt(a), f += c, a++, c === Po) s = true;
        else {
          if (c !== ":" || r.charAt(a + 1) !== hn) return;
          f += hn, a++;
        }
        for (; a < i && (c = r.charAt(a), !(No(c) || c === pn)); ) o += c, a++;
        if (c = r.charAt(a), !(!o || c !== pn)) return t ? true : (f += o, D = f, u += f + c, l = e.now(), l.column++, l.offset++, s && (f.slice(0, ut).toLowerCase() === dn ? (D = D.slice(ut), l.column += ut, l.offset += ut) : f = dn + f), m = n.inlineTokenizers, n.inlineTokenizers = { text: m.text }, p = n.enterLink(), D = n.tokenizeInline(D, l), n.inlineTokenizers = m, p(), e(u)({ type: "link", title: null, url: Od(f, { nonTerminated: false }), children: D }));
      }
    }
  }
});
var Uo = x((Yv, Mo) => {
  Mo.exports = Nd;
  function Nd(e, r) {
    var t = String(e), n = 0, u;
    if (typeof r != "string") throw new Error("Expected character");
    for (u = t.indexOf(r); u !== -1; ) n++, u = t.indexOf(r, u + r.length);
    return n;
  }
});
var zo = x((Gv, Go) => {
  Go.exports = Id;
  var Yo = ["www.", "http://", "https://"];
  function Id(e, r) {
    var t = -1, n, u, i;
    if (!this.options.gfm) return t;
    for (u = Yo.length, n = -1; ++n < u; ) i = e.indexOf(Yo[n], r), i !== -1 && (t === -1 || i < t) && (t = i);
    return t;
  }
});
var Ho = x((zv, jo) => {
  var Wo = Uo(), Pd = dr(), Ld = Ie(), Fn = $e(), Rd = oe(), Md = zo();
  jo.exports = En;
  En.locator = Md;
  En.notInLink = true;
  var Ud = 33, Yd = 38, Gd = 41, zd = 42, Wd = 44, Vd = 45, gn = 46, $d = 58, jd = 59, Hd = 63, Kd = 60, Vo = 95, Xd = 126, Jd = "(", $o = ")";
  function En(e, r, t) {
    var n = this, u = n.options.gfm, i = n.inlineTokenizers, a = r.length, o = -1, s = false, f, c, l, D, m, p, h, F, g, E, v, A, b, d;
    if (u) {
      if (r.slice(0, 4) === "www.") s = true, D = 4;
      else if (r.slice(0, 7).toLowerCase() === "http://") D = 7;
      else if (r.slice(0, 8).toLowerCase() === "https://") D = 8;
      else return;
      for (o = D - 1, l = D, f = []; D < a; ) {
        if (h = r.charCodeAt(D), h === gn) {
          if (o === D - 1) break;
          f.push(D), o = D, D++;
          continue;
        }
        if (Ld(h) || Fn(h) || h === Vd || h === Vo) {
          D++;
          continue;
        }
        break;
      }
      if (h === gn && (f.pop(), D--), f[0] !== void 0 && (c = f.length < 2 ? l : f[f.length - 2] + 1, r.slice(c, D).indexOf("_") === -1)) {
        if (t) return true;
        for (F = D, m = D; D < a && (h = r.charCodeAt(D), !(Rd(h) || h === Kd)); ) D++, h === Ud || h === zd || h === Wd || h === gn || h === $d || h === Hd || h === Vo || h === Xd || (F = D);
        if (D = F, r.charCodeAt(D - 1) === Gd) for (p = r.slice(m, D), g = Wo(p, Jd), E = Wo(p, $o); E > g; ) D = m + p.lastIndexOf($o), p = r.slice(m, D), E--;
        if (r.charCodeAt(D - 1) === jd && (D--, Fn(r.charCodeAt(D - 1)))) {
          for (F = D - 2; Fn(r.charCodeAt(F)); ) F--;
          r.charCodeAt(F) === Yd && (D = F);
        }
        return v = r.slice(0, D), b = Pd(v, { nonTerminated: false }), s && (b = "http://" + b), d = n.enterLink(), n.inlineTokenizers = { text: i.text }, A = n.tokenizeInline(v, e.now()), n.inlineTokenizers = i, d(), e(v)({ type: "link", title: null, url: b, children: A });
      }
    }
  }
});
var Qo = x((Wv, Jo) => {
  var Qd = Ie(), Zd = $e(), e0 = 43, r0 = 45, t0 = 46, n0 = 95;
  Jo.exports = Xo;
  function Xo(e, r) {
    var t = this, n, u;
    if (!this.options.gfm || (n = e.indexOf("@", r), n === -1)) return -1;
    if (u = n, u === r || !Ko(e.charCodeAt(u - 1))) return Xo.call(t, e, n + 1);
    for (; u > r && Ko(e.charCodeAt(u - 1)); ) u--;
    return u;
  }
  function Ko(e) {
    return Qd(e) || Zd(e) || e === e0 || e === r0 || e === t0 || e === n0;
  }
});
var ts = x((Vv, rs) => {
  var i0 = dr(), Zo = Ie(), es = $e(), u0 = Qo();
  rs.exports = An;
  An.locator = u0;
  An.notInLink = true;
  var a0 = 43, Cn = 45, at = 46, o0 = 64, vn = 95;
  function An(e, r, t) {
    var n = this, u = n.options.gfm, i = n.inlineTokenizers, a = 0, o = r.length, s = -1, f, c, l, D;
    if (u) {
      for (f = r.charCodeAt(a); Zo(f) || es(f) || f === a0 || f === Cn || f === at || f === vn; ) f = r.charCodeAt(++a);
      if (a !== 0 && f === o0) {
        for (a++; a < o; ) {
          if (f = r.charCodeAt(a), Zo(f) || es(f) || f === Cn || f === at || f === vn) {
            a++, s === -1 && f === at && (s = a);
            continue;
          }
          break;
        }
        if (!(s === -1 || s === a || f === Cn || f === vn)) return f === at && a--, c = r.slice(0, a), t ? true : (D = n.enterLink(), n.inlineTokenizers = { text: i.text }, l = n.tokenizeInline(c, e.now()), n.inlineTokenizers = i, D(), e(c)({ type: "link", title: null, url: "mailto:" + i0(c, { nonTerminated: false }), children: l }));
      }
    }
  }
});
var us = x(($v, is) => {
  var s0 = $e(), c0 = Dn(), l0 = on().tag;
  is.exports = ns;
  ns.locator = c0;
  var f0 = "<", D0 = "?", p0 = "!", h0 = "/", d0 = /^<a /i, m0 = /^<\/a>/i;
  function ns(e, r, t) {
    var n = this, u = r.length, i, a;
    if (!(r.charAt(0) !== f0 || u < 3) && (i = r.charAt(1), !(!s0(i) && i !== D0 && i !== p0 && i !== h0) && (a = r.match(l0), !!a))) return t ? true : (a = a[0], !n.inLink && d0.test(a) ? n.inLink = true : n.inLink && m0.test(a) && (n.inLink = false), e(a)({ type: "html", value: a }));
  }
});
var bn = x((jv, as) => {
  as.exports = F0;
  function F0(e, r) {
    var t = e.indexOf("[", r), n = e.indexOf("![", r);
    return n === -1 || t < n ? t : n;
  }
});
var ps = x((Hv, Ds) => {
  var Ar = oe(), g0 = bn();
  Ds.exports = fs;
  fs.locator = g0;
  var E0 = `
`, C0 = "!", os = '"', ss = "'", Qe = "(", br = ")", xn = "<", yn = ">", cs = "[", xr = "\\", v0 = "]", ls = "`";
  function fs(e, r, t) {
    var n = this, u = "", i = 0, a = r.charAt(0), o = n.options.pedantic, s = n.options.commonmark, f = n.options.gfm, c, l, D, m, p, h, F, g, E, v, A, b, d, y, w, C, k, B;
    if (a === C0 && (g = true, u = a, a = r.charAt(++i)), a === cs && !(!g && n.inLink)) {
      for (u += a, y = "", i++, A = r.length, C = e.now(), d = 0, C.column += i, C.offset += i; i < A; ) {
        if (a = r.charAt(i), h = a, a === ls) {
          for (l = 1; r.charAt(i + 1) === ls; ) h += a, i++, l++;
          D ? l >= D && (D = 0) : D = l;
        } else if (a === xr) i++, h += r.charAt(i);
        else if ((!D || f) && a === cs) d++;
        else if ((!D || f) && a === v0) if (d) d--;
        else {
          if (r.charAt(i + 1) !== Qe) return;
          h += Qe, c = true, i++;
          break;
        }
        y += h, h = "", i++;
      }
      if (c) {
        for (E = y, u += y + h, i++; i < A && (a = r.charAt(i), !!Ar(a)); ) u += a, i++;
        if (a = r.charAt(i), y = "", m = u, a === xn) {
          for (i++, m += xn; i < A && (a = r.charAt(i), a !== yn); ) {
            if (s && a === E0) return;
            y += a, i++;
          }
          if (r.charAt(i) !== yn) return;
          u += xn + y + yn, w = y, i++;
        } else {
          for (a = null, h = ""; i < A && (a = r.charAt(i), !(h && (a === os || a === ss || s && a === Qe))); ) {
            if (Ar(a)) {
              if (!o) break;
              h += a;
            } else {
              if (a === Qe) d++;
              else if (a === br) {
                if (d === 0) break;
                d--;
              }
              y += h, h = "", a === xr && (y += xr, a = r.charAt(++i)), y += a;
            }
            i++;
          }
          u += y, w = y, i = u.length;
        }
        for (y = ""; i < A && (a = r.charAt(i), !!Ar(a)); ) y += a, i++;
        if (a = r.charAt(i), u += y, y && (a === os || a === ss || s && a === Qe)) if (i++, u += a, y = "", v = a === Qe ? br : a, p = u, s) {
          for (; i < A && (a = r.charAt(i), a !== v); ) a === xr && (y += xr, a = r.charAt(++i)), i++, y += a;
          if (a = r.charAt(i), a !== v) return;
          for (b = y, u += y + a, i++; i < A && (a = r.charAt(i), !!Ar(a)); ) u += a, i++;
        } else for (h = ""; i < A; ) {
          if (a = r.charAt(i), a === v) F && (y += v + h, h = ""), F = true;
          else if (!F) y += a;
          else if (a === br) {
            u += y + v + h, b = y;
            break;
          } else Ar(a) ? h += a : (y += v + h + a, h = "", F = false);
          i++;
        }
        if (r.charAt(i) === br) return t ? true : (u += br, w = n.decode.raw(n.unescape(w), e(m).test().end, { nonTerminated: false }), b && (p = e(p).test().end, b = n.decode.raw(n.unescape(b), p)), B = { type: g ? "image" : "link", title: b || null, url: w }, g ? B.alt = n.decode.raw(n.unescape(E), C) || null : (k = n.enterLink(), B.children = n.tokenizeInline(E, C), k()), e(u)(B));
      }
    }
  }
});
var ms = x((Kv, ds) => {
  var A0 = oe(), b0 = bn(), x0 = sn();
  ds.exports = hs;
  hs.locator = b0;
  var wn = "link", y0 = "image", w0 = "shortcut", k0 = "collapsed", kn = "full", B0 = "!", ot = "[", st = "\\", ct = "]";
  function hs(e, r, t) {
    var n = this, u = n.options.commonmark, i = r.charAt(0), a = 0, o = r.length, s = "", f = "", c = wn, l = w0, D, m, p, h, F, g, E, v;
    if (i === B0 && (c = y0, f = i, i = r.charAt(++a)), i === ot) {
      for (a++, f += i, g = "", v = 0; a < o; ) {
        if (i = r.charAt(a), i === ot) E = true, v++;
        else if (i === ct) {
          if (!v) break;
          v--;
        }
        i === st && (g += st, i = r.charAt(++a)), g += i, a++;
      }
      if (s = g, D = g, i = r.charAt(a), i === ct) {
        if (a++, s += i, g = "", !u) for (; a < o && (i = r.charAt(a), !!A0(i)); ) g += i, a++;
        if (i = r.charAt(a), i === ot) {
          for (m = "", g += i, a++; a < o && (i = r.charAt(a), !(i === ot || i === ct)); ) i === st && (m += st, i = r.charAt(++a)), m += i, a++;
          i = r.charAt(a), i === ct ? (l = m ? kn : k0, g += m + i, a++) : m = "", s += g, g = "";
        } else {
          if (!D) return;
          m = D;
        }
        if (!(l !== kn && E)) return s = f + s, c === wn && n.inLink ? null : t ? true : (p = e.now(), p.column += f.length, p.offset += f.length, m = l === kn ? m : D, h = { type: c + "Reference", identifier: x0(m), label: m, referenceType: l }, c === wn ? (F = n.enterLink(), h.children = n.tokenizeInline(D, p), F()) : h.alt = n.decode.raw(n.unescape(D), p) || null, e(s)(h));
      }
    }
  }
});
var gs = x((Xv, Fs) => {
  Fs.exports = T0;
  function T0(e, r) {
    var t = e.indexOf("**", r), n = e.indexOf("__", r);
    return n === -1 ? t : t === -1 || n < t ? n : t;
  }
});
var As = x((Jv, vs) => {
  var _0 = Pe(), Es = oe(), q0 = gs();
  vs.exports = Cs;
  Cs.locator = q0;
  var O0 = "\\", S0 = "*", N0 = "_";
  function Cs(e, r, t) {
    var n = this, u = 0, i = r.charAt(u), a, o, s, f, c, l, D;
    if (!(i !== S0 && i !== N0 || r.charAt(++u) !== i) && (o = n.options.pedantic, s = i, c = s + s, l = r.length, u++, f = "", i = "", !(o && Es(r.charAt(u))))) for (; u < l; ) {
      if (D = i, i = r.charAt(u), i === s && r.charAt(u + 1) === s && (!o || !Es(D)) && (i = r.charAt(u + 2), i !== s)) return _0(f) ? t ? true : (a = e.now(), a.column += 2, a.offset += 2, e(c + f + c)({ type: "strong", children: n.tokenizeInline(f, a) })) : void 0;
      !o && i === O0 && (f += i, i = r.charAt(++u)), f += i, u++;
    }
  }
});
var xs = x((Qv, bs) => {
  bs.exports = L0;
  var I0 = String.fromCharCode, P0 = /\w/;
  function L0(e) {
    return P0.test(typeof e == "number" ? I0(e) : e.charAt(0));
  }
});
var ws = x((Zv, ys) => {
  ys.exports = R0;
  function R0(e, r) {
    var t = e.indexOf("*", r), n = e.indexOf("_", r);
    return n === -1 ? t : t === -1 || n < t ? n : t;
  }
});
var qs = x((e2, _s) => {
  var M0 = Pe(), U0 = xs(), ks = oe(), Y0 = ws();
  _s.exports = Ts;
  Ts.locator = Y0;
  var G0 = "*", Bs = "_", z0 = "\\";
  function Ts(e, r, t) {
    var n = this, u = 0, i = r.charAt(u), a, o, s, f, c, l, D;
    if (!(i !== G0 && i !== Bs) && (o = n.options.pedantic, c = i, s = i, l = r.length, u++, f = "", i = "", !(o && ks(r.charAt(u))))) for (; u < l; ) {
      if (D = i, i = r.charAt(u), i === s && (!o || !ks(D))) {
        if (i = r.charAt(++u), i !== s) {
          if (!M0(f) || D === s) return;
          if (!o && s === Bs && U0(i)) {
            f += s;
            continue;
          }
          return t ? true : (a = e.now(), a.column++, a.offset++, e(c + f + s)({ type: "emphasis", children: n.tokenizeInline(f, a) }));
        }
        f += s;
      }
      !o && i === z0 && (f += i, i = r.charAt(++u)), f += i, u++;
    }
  }
});
var Ss = x((r2, Os) => {
  Os.exports = W0;
  function W0(e, r) {
    return e.indexOf("~~", r);
  }
});
var Rs = x((t2, Ls) => {
  var Ns = oe(), V0 = Ss();
  Ls.exports = Ps;
  Ps.locator = V0;
  var lt = "~", Is = "~~";
  function Ps(e, r, t) {
    var n = this, u = "", i = "", a = "", o = "", s, f, c;
    if (!(!n.options.gfm || r.charAt(0) !== lt || r.charAt(1) !== lt || Ns(r.charAt(2)))) for (s = 1, f = r.length, c = e.now(), c.column += 2, c.offset += 2; ++s < f; ) {
      if (u = r.charAt(s), u === lt && i === lt && (!a || !Ns(a))) return t ? true : e(Is + o + Is)({ type: "delete", children: n.tokenizeInline(o, c) });
      o += i, a = i, i = u;
    }
  }
});
var Us = x((n2, Ms) => {
  Ms.exports = $0;
  function $0(e, r) {
    return e.indexOf("`", r);
  }
});
var zs = x((i2, Gs) => {
  var j0 = Us();
  Gs.exports = Ys;
  Ys.locator = j0;
  var Bn = 10, Tn = 32, _n = 96;
  function Ys(e, r, t) {
    for (var n = r.length, u = 0, i, a, o, s, f, c; u < n && r.charCodeAt(u) === _n; ) u++;
    if (!(u === 0 || u === n)) {
      for (i = u, f = r.charCodeAt(u); u < n; ) {
        if (s = f, f = r.charCodeAt(u + 1), s === _n) {
          if (a === void 0 && (a = u), o = u + 1, f !== _n && o - a === i) {
            c = true;
            break;
          }
        } else a !== void 0 && (a = void 0, o = void 0);
        u++;
      }
      if (c) {
        if (t) return true;
        if (u = i, n = a, s = r.charCodeAt(u), f = r.charCodeAt(n - 1), c = false, n - u > 2 && (s === Tn || s === Bn) && (f === Tn || f === Bn)) {
          for (u++, n--; u < n; ) {
            if (s = r.charCodeAt(u), s !== Tn && s !== Bn) {
              c = true;
              break;
            }
            u++;
          }
          c === true && (i++, a--);
        }
        return e(r.slice(0, o))({ type: "inlineCode", value: r.slice(i, a) });
      }
    }
  }
});
var Vs = x((u2, Ws) => {
  Ws.exports = H0;
  function H0(e, r) {
    for (var t = e.indexOf(`
`, r); t > r && e.charAt(t - 1) === " "; ) t--;
    return t;
  }
});
var Hs = x((a2, js) => {
  var K0 = Vs();
  js.exports = $s;
  $s.locator = K0;
  var X0 = " ", J0 = `
`, Q0 = 2;
  function $s(e, r, t) {
    for (var n = r.length, u = -1, i = "", a; ++u < n; ) {
      if (a = r.charAt(u), a === J0) return u < Q0 ? void 0 : t ? true : (i += a, e(i)({ type: "break" }));
      if (a !== X0) return;
      i += a;
    }
  }
});
var Xs = x((o2, Ks) => {
  Ks.exports = Z0;
  function Z0(e, r, t) {
    var n = this, u, i, a, o, s, f, c, l, D, m;
    if (t) return true;
    for (u = n.inlineMethods, o = u.length, i = n.inlineTokenizers, a = -1, D = r.length; ++a < o; ) l = u[a], !(l === "text" || !i[l]) && (c = i[l].locator, c || e.file.fail("Missing locator: `" + l + "`"), f = c.call(n, r, 1), f !== -1 && f < D && (D = f));
    s = r.slice(0, D), m = e.now(), n.decode(s, m, p);
    function p(h, F, g) {
      e(g || h)({ type: "text", value: h });
    }
  }
});
var ec = x((s2, Zs) => {
  var em = Ne(), ft = mu(), rm = gu(), tm = Cu(), nm = Hu(), qn = Ju();
  Zs.exports = Js;
  function Js(e, r) {
    this.file = r, this.offset = {}, this.options = em(this.options), this.setOptions({}), this.inList = false, this.inBlock = false, this.inLink = false, this.atStart = true, this.toOffset = rm(r).toOffset, this.unescape = tm(this, "escape"), this.decode = nm(this);
  }
  var U = Js.prototype;
  U.setOptions = ua();
  U.parse = va();
  U.options = jt();
  U.exitStart = ft("atStart", true);
  U.enterList = ft("inList", false);
  U.enterLink = ft("inLink", false);
  U.enterBlock = ft("inBlock", false);
  U.interruptParagraph = [["thematicBreak"], ["list"], ["atxHeading"], ["fencedCode"], ["blockquote"], ["html"], ["setextHeading", { commonmark: false }], ["definition", { commonmark: false }]];
  U.interruptList = [["atxHeading", { pedantic: false }], ["fencedCode", { pedantic: false }], ["thematicBreak", { pedantic: false }], ["definition", { commonmark: false }]];
  U.interruptBlockquote = [["indentedCode", { commonmark: true }], ["fencedCode", { commonmark: true }], ["atxHeading", { commonmark: true }], ["setextHeading", { commonmark: true }], ["thematicBreak", { commonmark: true }], ["html", { commonmark: true }], ["list", { commonmark: true }], ["definition", { commonmark: false }]];
  U.blockTokenizers = { blankLine: ba(), indentedCode: Ba(), fencedCode: qa(), blockquote: La(), atxHeading: Ua(), thematicBreak: za(), list: eo(), setextHeading: io(), html: lo(), definition: Co(), table: bo(), paragraph: wo() };
  U.inlineTokenizers = { escape: Oo(), autoLink: Ro(), url: Ho(), email: ts(), html: us(), link: ps(), reference: ms(), strong: As(), emphasis: qs(), deletion: Rs(), code: zs(), break: Hs(), text: Xs() };
  U.blockMethods = Qs(U.blockTokenizers);
  U.inlineMethods = Qs(U.inlineTokenizers);
  U.tokenizeBlock = qn("block");
  U.tokenizeInline = qn("inline");
  U.tokenizeFactory = qn;
  function Qs(e) {
    var r = [], t;
    for (t in e) r.push(t);
    return r;
  }
});
var ic = x((c2, nc) => {
  var im = hu(), um = Ne(), rc = ec();
  nc.exports = tc;
  tc.Parser = rc;
  function tc(e) {
    var r = this.data("settings"), t = im(rc);
    t.prototype.options = um(t.prototype.options, r, e), this.Parser = t;
  }
});
var ac = x((l2, uc) => {
  uc.exports = am;
  function am(e) {
    if (e) throw e;
  }
});
var On = x((f2, oc) => {
  oc.exports = function(r) {
    return r != null && r.constructor != null && typeof r.constructor.isBuffer == "function" && r.constructor.isBuffer(r);
  };
});
var mc = x((D2, dc) => {
  var Dt = Object.prototype.hasOwnProperty, hc = Object.prototype.toString, sc = Object.defineProperty, cc = Object.getOwnPropertyDescriptor, lc = function(r) {
    return typeof Array.isArray == "function" ? Array.isArray(r) : hc.call(r) === "[object Array]";
  }, fc = function(r) {
    if (!r || hc.call(r) !== "[object Object]") return false;
    var t = Dt.call(r, "constructor"), n = r.constructor && r.constructor.prototype && Dt.call(r.constructor.prototype, "isPrototypeOf");
    if (r.constructor && !t && !n) return false;
    var u;
    for (u in r) ;
    return typeof u > "u" || Dt.call(r, u);
  }, Dc = function(r, t) {
    sc && t.name === "__proto__" ? sc(r, t.name, { enumerable: true, configurable: true, value: t.newValue, writable: true }) : r[t.name] = t.newValue;
  }, pc = function(r, t) {
    if (t === "__proto__") if (Dt.call(r, t)) {
      if (cc) return cc(r, t).value;
    } else return;
    return r[t];
  };
  dc.exports = function e() {
    var r, t, n, u, i, a, o = arguments[0], s = 1, f = arguments.length, c = false;
    for (typeof o == "boolean" && (c = o, o = arguments[1] || {}, s = 2), (o == null || typeof o != "object" && typeof o != "function") && (o = {}); s < f; ++s) if (r = arguments[s], r != null) for (t in r) n = pc(o, t), u = pc(r, t), o !== u && (c && u && (fc(u) || (i = lc(u))) ? (i ? (i = false, a = n && lc(n) ? n : []) : a = n && fc(n) ? n : {}, Dc(o, { name: t, newValue: e(c, a, u) })) : typeof u < "u" && Dc(o, { name: t, newValue: u }));
    return o;
  };
});
var gc = x((p2, Fc) => {
  Fc.exports = (e) => {
    if (Object.prototype.toString.call(e) !== "[object Object]") return false;
    let r = Object.getPrototypeOf(e);
    return r === null || r === Object.prototype;
  };
});
var Cc = x((h2, Ec) => {
  var om = [].slice;
  Ec.exports = sm;
  function sm(e, r) {
    var t;
    return n;
    function n() {
      var a = om.call(arguments, 0), o = e.length > a.length, s;
      o && a.push(u);
      try {
        s = e.apply(null, a);
      } catch (f) {
        if (o && t) throw f;
        return u(f);
      }
      o || (s && typeof s.then == "function" ? s.then(i, u) : s instanceof Error ? u(s) : i(s));
    }
    function u() {
      t || (t = true, r.apply(null, arguments));
    }
    function i(a) {
      u(null, a);
    }
  }
});
var yc = x((d2, xc) => {
  var Ac = Cc();
  xc.exports = bc;
  bc.wrap = Ac;
  var vc = [].slice;
  function bc() {
    var e = [], r = {};
    return r.run = t, r.use = n, r;
    function t() {
      var u = -1, i = vc.call(arguments, 0, -1), a = arguments[arguments.length - 1];
      if (typeof a != "function") throw new Error("Expected function as last argument, not " + a);
      o.apply(null, [null].concat(i));
      function o(s) {
        var f = e[++u], c = vc.call(arguments, 0), l = c.slice(1), D = i.length, m = -1;
        if (s) {
          a(s);
          return;
        }
        for (; ++m < D; ) (l[m] === null || l[m] === void 0) && (l[m] = i[m]);
        i = l, f ? Ac(f, o).apply(null, i) : a.apply(null, [null].concat(i));
      }
    }
    function n(u) {
      if (typeof u != "function") throw new Error("Expected `fn` to be a function, not " + u);
      return e.push(u), r;
    }
  }
});
var Tc = x((m2, Bc) => {
  var Ze = {}.hasOwnProperty;
  Bc.exports = cm;
  function cm(e) {
    return !e || typeof e != "object" ? "" : Ze.call(e, "position") || Ze.call(e, "type") ? wc(e.position) : Ze.call(e, "start") || Ze.call(e, "end") ? wc(e) : Ze.call(e, "line") || Ze.call(e, "column") ? Sn(e) : "";
  }
  function Sn(e) {
    return (!e || typeof e != "object") && (e = {}), kc(e.line) + ":" + kc(e.column);
  }
  function wc(e) {
    return (!e || typeof e != "object") && (e = {}), Sn(e.start) + "-" + Sn(e.end);
  }
  function kc(e) {
    return e && typeof e == "number" ? e : 1;
  }
});
var Oc = x((F2, qc) => {
  var lm = Tc();
  qc.exports = Nn;
  function _c() {
  }
  _c.prototype = Error.prototype;
  Nn.prototype = new _c();
  var ke = Nn.prototype;
  ke.file = "";
  ke.name = "";
  ke.reason = "";
  ke.message = "";
  ke.stack = "";
  ke.fatal = null;
  ke.column = null;
  ke.line = null;
  function Nn(e, r, t) {
    var n, u, i;
    typeof r == "string" && (t = r, r = null), n = fm(t), u = lm(r) || "1:1", i = { start: { line: null, column: null }, end: { line: null, column: null } }, r && r.position && (r = r.position), r && (r.start ? (i = r, r = r.start) : i.start = r), e.stack && (this.stack = e.stack, e = e.message), this.message = e, this.name = u, this.reason = e, this.line = r ? r.line : null, this.column = r ? r.column : null, this.location = i, this.source = n[0], this.ruleId = n[1];
  }
  function fm(e) {
    var r = [null, null], t;
    return typeof e == "string" && (t = e.indexOf(":"), t === -1 ? r[1] = e : (r[0] = e.slice(0, t), r[1] = e.slice(t + 1))), r;
  }
});
var Sc = x((er) => {
  er.basename = Dm;
  er.dirname = pm;
  er.extname = hm;
  er.join = dm;
  er.sep = "/";
  function Dm(e, r) {
    var t = 0, n = -1, u, i, a, o;
    if (r !== void 0 && typeof r != "string") throw new TypeError('"ext" argument must be a string');
    if (yr(e), u = e.length, r === void 0 || !r.length || r.length > e.length) {
      for (; u--; ) if (e.charCodeAt(u) === 47) {
        if (a) {
          t = u + 1;
          break;
        }
      } else n < 0 && (a = true, n = u + 1);
      return n < 0 ? "" : e.slice(t, n);
    }
    if (r === e) return "";
    for (i = -1, o = r.length - 1; u--; ) if (e.charCodeAt(u) === 47) {
      if (a) {
        t = u + 1;
        break;
      }
    } else i < 0 && (a = true, i = u + 1), o > -1 && (e.charCodeAt(u) === r.charCodeAt(o--) ? o < 0 && (n = u) : (o = -1, n = i));
    return t === n ? n = i : n < 0 && (n = e.length), e.slice(t, n);
  }
  function pm(e) {
    var r, t, n;
    if (yr(e), !e.length) return ".";
    for (r = -1, n = e.length; --n; ) if (e.charCodeAt(n) === 47) {
      if (t) {
        r = n;
        break;
      }
    } else t || (t = true);
    return r < 0 ? e.charCodeAt(0) === 47 ? "/" : "." : r === 1 && e.charCodeAt(0) === 47 ? "//" : e.slice(0, r);
  }
  function hm(e) {
    var r = -1, t = 0, n = -1, u = 0, i, a, o;
    for (yr(e), o = e.length; o--; ) {
      if (a = e.charCodeAt(o), a === 47) {
        if (i) {
          t = o + 1;
          break;
        }
        continue;
      }
      n < 0 && (i = true, n = o + 1), a === 46 ? r < 0 ? r = o : u !== 1 && (u = 1) : r > -1 && (u = -1);
    }
    return r < 0 || n < 0 || u === 0 || u === 1 && r === n - 1 && r === t + 1 ? "" : e.slice(r, n);
  }
  function dm() {
    for (var e = -1, r; ++e < arguments.length; ) yr(arguments[e]), arguments[e] && (r = r === void 0 ? arguments[e] : r + "/" + arguments[e]);
    return r === void 0 ? "." : mm(r);
  }
  function mm(e) {
    var r, t;
    return yr(e), r = e.charCodeAt(0) === 47, t = Fm(e, !r), !t.length && !r && (t = "."), t.length && e.charCodeAt(e.length - 1) === 47 && (t += "/"), r ? "/" + t : t;
  }
  function Fm(e, r) {
    for (var t = "", n = 0, u = -1, i = 0, a = -1, o, s; ++a <= e.length; ) {
      if (a < e.length) o = e.charCodeAt(a);
      else {
        if (o === 47) break;
        o = 47;
      }
      if (o === 47) {
        if (!(u === a - 1 || i === 1)) if (u !== a - 1 && i === 2) {
          if (t.length < 2 || n !== 2 || t.charCodeAt(t.length - 1) !== 46 || t.charCodeAt(t.length - 2) !== 46) {
            if (t.length > 2) {
              if (s = t.lastIndexOf("/"), s !== t.length - 1) {
                s < 0 ? (t = "", n = 0) : (t = t.slice(0, s), n = t.length - 1 - t.lastIndexOf("/")), u = a, i = 0;
                continue;
              }
            } else if (t.length) {
              t = "", n = 0, u = a, i = 0;
              continue;
            }
          }
          r && (t = t.length ? t + "/.." : "..", n = 2);
        } else t.length ? t += "/" + e.slice(u + 1, a) : t = e.slice(u + 1, a), n = a - u - 1;
        u = a, i = 0;
      } else o === 46 && i > -1 ? i++ : i = -1;
    }
    return t;
  }
  function yr(e) {
    if (typeof e != "string") throw new TypeError("Path must be a string. Received " + JSON.stringify(e));
  }
});
var Ic = x((Nc) => {
  Nc.cwd = gm;
  function gm() {
    return "/";
  }
});
var Rc = x((C2, Lc) => {
  var se = Sc(), Em = Ic(), Cm = On();
  Lc.exports = Ee;
  var vm = {}.hasOwnProperty, In = ["history", "path", "basename", "stem", "extname", "dirname"];
  Ee.prototype.toString = Om;
  Object.defineProperty(Ee.prototype, "path", { get: Am, set: bm });
  Object.defineProperty(Ee.prototype, "dirname", { get: xm, set: ym });
  Object.defineProperty(Ee.prototype, "basename", { get: wm, set: km });
  Object.defineProperty(Ee.prototype, "extname", { get: Bm, set: Tm });
  Object.defineProperty(Ee.prototype, "stem", { get: _m, set: qm });
  function Ee(e) {
    var r, t;
    if (!e) e = {};
    else if (typeof e == "string" || Cm(e)) e = { contents: e };
    else if ("message" in e && "messages" in e) return e;
    if (!(this instanceof Ee)) return new Ee(e);
    for (this.data = {}, this.messages = [], this.history = [], this.cwd = Em.cwd(), t = -1; ++t < In.length; ) r = In[t], vm.call(e, r) && (this[r] = e[r]);
    for (r in e) In.indexOf(r) < 0 && (this[r] = e[r]);
  }
  function Am() {
    return this.history[this.history.length - 1];
  }
  function bm(e) {
    Ln(e, "path"), this.path !== e && this.history.push(e);
  }
  function xm() {
    return typeof this.path == "string" ? se.dirname(this.path) : void 0;
  }
  function ym(e) {
    Pc(this.path, "dirname"), this.path = se.join(e || "", this.basename);
  }
  function wm() {
    return typeof this.path == "string" ? se.basename(this.path) : void 0;
  }
  function km(e) {
    Ln(e, "basename"), Pn(e, "basename"), this.path = se.join(this.dirname || "", e);
  }
  function Bm() {
    return typeof this.path == "string" ? se.extname(this.path) : void 0;
  }
  function Tm(e) {
    if (Pn(e, "extname"), Pc(this.path, "extname"), e) {
      if (e.charCodeAt(0) !== 46) throw new Error("`extname` must start with `.`");
      if (e.indexOf(".", 1) > -1) throw new Error("`extname` cannot contain multiple dots");
    }
    this.path = se.join(this.dirname, this.stem + (e || ""));
  }
  function _m() {
    return typeof this.path == "string" ? se.basename(this.path, this.extname) : void 0;
  }
  function qm(e) {
    Ln(e, "stem"), Pn(e, "stem"), this.path = se.join(this.dirname || "", e + (this.extname || ""));
  }
  function Om(e) {
    return (this.contents || "").toString(e);
  }
  function Pn(e, r) {
    if (e && e.indexOf(se.sep) > -1) throw new Error("`" + r + "` cannot be a path: did not expect `" + se.sep + "`");
  }
  function Ln(e, r) {
    if (!e) throw new Error("`" + r + "` cannot be empty");
  }
  function Pc(e, r) {
    if (!e) throw new Error("Setting `" + r + "` requires `path` to be set too");
  }
});
var Uc = x((v2, Mc) => {
  var Sm = Oc(), pt = Rc();
  Mc.exports = pt;
  pt.prototype.message = Nm;
  pt.prototype.info = Pm;
  pt.prototype.fail = Im;
  function Nm(e, r, t) {
    var n = new Sm(e, r, t);
    return this.path && (n.name = this.path + ":" + n.name, n.file = this.path), n.fatal = false, this.messages.push(n), n;
  }
  function Im() {
    var e = this.message.apply(this, arguments);
    throw e.fatal = true, e;
  }
  function Pm() {
    var e = this.message.apply(this, arguments);
    return e.fatal = null, e;
  }
});
var Gc = x((A2, Yc) => {
  Yc.exports = Uc();
});
var Jc = x((b2, Xc) => {
  var zc = ac(), Lm = On(), ht = mc(), Wc = gc(), Hc = yc(), wr = Gc();
  Xc.exports = Kc().freeze();
  var Rm = [].slice, Mm = {}.hasOwnProperty, Um = Hc().use(Ym).use(Gm).use(zm);
  function Ym(e, r) {
    r.tree = e.parse(r.file);
  }
  function Gm(e, r, t) {
    e.run(r.tree, r.file, n);
    function n(u, i, a) {
      u ? t(u) : (r.tree = i, r.file = a, t());
    }
  }
  function zm(e, r) {
    var t = e.stringify(r.tree, r.file);
    t == null || (typeof t == "string" || Lm(t) ? ("value" in r.file && (r.file.value = t), r.file.contents = t) : r.file.result = t);
  }
  function Kc() {
    var e = [], r = Hc(), t = {}, n = -1, u;
    return i.data = o, i.freeze = a, i.attachers = e, i.use = s, i.parse = c, i.stringify = m, i.run = l, i.runSync = D, i.process = p, i.processSync = h, i;
    function i() {
      for (var F = Kc(), g = -1; ++g < e.length; ) F.use.apply(null, e[g]);
      return F.data(ht(true, {}, t)), F;
    }
    function a() {
      var F, g;
      if (u) return i;
      for (; ++n < e.length; ) F = e[n], F[1] !== false && (F[1] === true && (F[1] = void 0), g = F[0].apply(i, F.slice(1)), typeof g == "function" && r.use(g));
      return u = true, n = 1 / 0, i;
    }
    function o(F, g) {
      return typeof F == "string" ? arguments.length === 2 ? (Un("data", u), t[F] = g, i) : Mm.call(t, F) && t[F] || null : F ? (Un("data", u), t = F, i) : t;
    }
    function s(F) {
      var g;
      if (Un("use", u), F != null) if (typeof F == "function") b.apply(null, arguments);
      else if (typeof F == "object") "length" in F ? A(F) : E(F);
      else throw new Error("Expected usable value, not `" + F + "`");
      return g && (t.settings = ht(t.settings || {}, g)), i;
      function E(d) {
        A(d.plugins), d.settings && (g = ht(g || {}, d.settings));
      }
      function v(d) {
        if (typeof d == "function") b(d);
        else if (typeof d == "object") "length" in d ? b.apply(null, d) : E(d);
        else throw new Error("Expected usable value, not `" + d + "`");
      }
      function A(d) {
        var y = -1;
        if (d != null) if (typeof d == "object" && "length" in d) for (; ++y < d.length; ) v(d[y]);
        else throw new Error("Expected a list of plugins, not `" + d + "`");
      }
      function b(d, y) {
        var w = f(d);
        w ? (Wc(w[1]) && Wc(y) && (y = ht(true, w[1], y)), w[1] = y) : e.push(Rm.call(arguments));
      }
    }
    function f(F) {
      for (var g = -1; ++g < e.length; ) if (e[g][0] === F) return e[g];
    }
    function c(F) {
      var g = wr(F), E;
      return a(), E = i.Parser, Rn("parse", E), Vc(E, "parse") ? new E(String(g), g).parse() : E(String(g), g);
    }
    function l(F, g, E) {
      if ($c(F), a(), !E && typeof g == "function" && (E = g, g = null), !E) return new Promise(v);
      v(null, E);
      function v(A, b) {
        r.run(F, wr(g), d);
        function d(y, w, C) {
          w = w || F, y ? b(y) : A ? A(w) : E(null, w, C);
        }
      }
    }
    function D(F, g) {
      var E, v;
      return l(F, g, A), jc("runSync", "run", v), E;
      function A(b, d) {
        v = true, E = d, zc(b);
      }
    }
    function m(F, g) {
      var E = wr(g), v;
      return a(), v = i.Compiler, Mn("stringify", v), $c(F), Vc(v, "compile") ? new v(F, E).compile() : v(F, E);
    }
    function p(F, g) {
      if (a(), Rn("process", i.Parser), Mn("process", i.Compiler), !g) return new Promise(E);
      E(null, g);
      function E(v, A) {
        var b = wr(F);
        Um.run(i, { file: b }, d);
        function d(y) {
          y ? A(y) : v ? v(b) : g(null, b);
        }
      }
    }
    function h(F) {
      var g, E;
      return a(), Rn("processSync", i.Parser), Mn("processSync", i.Compiler), g = wr(F), p(g, v), jc("processSync", "process", E), g;
      function v(A) {
        E = true, zc(A);
      }
    }
  }
  function Vc(e, r) {
    return typeof e == "function" && e.prototype && (Wm(e.prototype) || r in e.prototype);
  }
  function Wm(e) {
    var r;
    for (r in e) return true;
    return false;
  }
  function Rn(e, r) {
    if (typeof r != "function") throw new Error("Cannot `" + e + "` without `Parser`");
  }
  function Mn(e, r) {
    if (typeof r != "function") throw new Error("Cannot `" + e + "` without `Compiler`");
  }
  function Un(e, r) {
    if (r) throw new Error("Cannot invoke `" + e + "` on a frozen processor.\nCreate a new processor first, by invoking it: use `processor()` instead of `processor`.");
  }
  function $c(e) {
    if (!e || typeof e.type != "string") throw new Error("Expected node, got `" + e + "`");
  }
  function jc(e, r, t) {
    if (!t) throw new Error("`" + e + "` finished async. Use `" + r + "` instead");
  }
});
var dl = {};
zn(dl, { languages: () => $i, options: () => ji, parsers: () => Gn, printers: () => rF });
var Re = (e, r) => (t, n, ...u) => t | 1 && n == null ? void 0 : (r.call(n) ?? n[e]).apply(n, u);
function Al(e) {
  return this[e < 0 ? this.length + e : e];
}
var bl = Re("at", function() {
  if (Array.isArray(this) || typeof this == "string") return Al;
}), M = bl;
var xl = String.prototype.replaceAll ?? function(e, r) {
  return e.global ? this.replace(e, r) : this.split(e).join(r);
}, yl = Re("replaceAll", function() {
  if (typeof this == "string") return xl;
}), L = yl;
var Wi = Le(kr());
function le(e) {
  if (typeof e != "string") throw new TypeError("Expected a string");
  return e.replace(/[|\\{}()[\]^$+*?.]/g, "\\$&").replace(/-/g, "\\x2d");
}
var kl = () => {
}, rr = kl;
var W = "string", V = "array", Ae = "cursor", Z = "indent", ee = "align", fe = "trim", K = "group", X = "fill", J = "if-break", De = "indent-if-break", pe = "line-suffix", he = "line-suffix-boundary", $ = "line", de = "label", re = "break-parent", Br = /* @__PURE__ */ new Set([Ae, Z, ee, fe, K, X, J, De, pe, he, $, de, re]);
function Bl(e) {
  if (typeof e == "string") return W;
  if (Array.isArray(e)) return V;
  if (!e) return;
  let { type: r } = e;
  if (Br.has(r)) return r;
}
var z = Bl;
var Tl = (e) => new Intl.ListFormat("en-US", { type: "disjunction" }).format(e);
function _l(e) {
  let r = e === null ? "null" : typeof e;
  if (r !== "string" && r !== "object") return `Unexpected doc '${r}', 
Expected it to be 'string' or 'object'.`;
  if (z(e)) throw new Error("doc is valid.");
  let t = Object.prototype.toString.call(e);
  if (t !== "[object Object]") return `Unexpected doc '${t}'.`;
  let n = Tl([...Br].map((u) => `'${u}'`));
  return `Unexpected doc.type '${e.type}'.
Expected it to be ${n}.`;
}
var Ft = class extends Error {
  name = "InvalidDocError";
  constructor(r) {
    super(_l(r)), this.doc = r;
  }
}, Be = Ft;
var Vn = {};
function ql(e, r, t, n) {
  let u = [e];
  for (; u.length > 0; ) {
    let i = u.pop();
    if (i === Vn) {
      t(u.pop());
      continue;
    }
    t && u.push(i, Vn);
    let a = z(i);
    if (!a) throw new Be(i);
    if (r?.(i) !== false) switch (a) {
      case V:
      case X: {
        let o = a === V ? i : i.parts;
        for (let s = o.length, f = s - 1; f >= 0; --f) u.push(o[f]);
        break;
      }
      case J:
        u.push(i.flatContents, i.breakContents);
        break;
      case K:
        if (n && i.expandedStates) for (let o = i.expandedStates.length, s = o - 1; s >= 0; --s) u.push(i.expandedStates[s]);
        else u.push(i.contents);
        break;
      case ee:
      case Z:
      case De:
      case de:
      case pe:
        u.push(i.contents);
        break;
      case W:
      case Ae:
      case fe:
      case he:
      case $:
      case re:
        break;
      default:
        throw new Be(i);
    }
  }
}
var $n = ql;
function Ol(e, r) {
  if (typeof e == "string") return r(e);
  let t = /* @__PURE__ */ new Map();
  return n(e);
  function n(i) {
    if (t.has(i)) return t.get(i);
    let a = u(i);
    return t.set(i, a), a;
  }
  function u(i) {
    switch (z(i)) {
      case V:
        return r(i.map(n));
      case X:
        return r({ ...i, parts: i.parts.map(n) });
      case J:
        return r({ ...i, breakContents: n(i.breakContents), flatContents: n(i.flatContents) });
      case K: {
        let { expandedStates: a, contents: o } = i;
        return a ? (a = a.map(n), o = a[0]) : o = n(o), r({ ...i, contents: o, expandedStates: a });
      }
      case ee:
      case Z:
      case De:
      case de:
      case pe:
        return r({ ...i, contents: n(i.contents) });
      case W:
      case Ae:
      case fe:
      case he:
      case $:
      case re:
        return r(i);
      default:
        throw new Be(i);
    }
  }
}
function jn(e) {
  if (e.length > 0) {
    let r = M(0, e, -1);
    !r.expandedStates && !r.break && (r.break = "propagated");
  }
  return null;
}
function Hn(e) {
  let r = /* @__PURE__ */ new Set(), t = [];
  function n(i) {
    if (i.type === re && jn(t), i.type === K) {
      if (t.push(i), r.has(i)) return false;
      r.add(i);
    }
  }
  function u(i) {
    i.type === K && t.pop().break && jn(t);
  }
  $n(e, n, u, true);
}
function be(e, r = tr) {
  return Ol(e, (t) => typeof t == "string" ? Tr(r, t.split(`
`)) : t);
}
var _r = rr;
function nr(e) {
  return { type: Z, contents: e };
}
function me(e, r) {
  return { type: ee, contents: r, n: e };
}
function ir(e) {
  return me({ type: "root" }, e);
}
var Me = { type: re };
function Ue(e) {
  return { type: X, parts: e };
}
function Ye(e, r = {}) {
  return _r(r.expandedStates), { type: K, id: r.id, contents: e, break: !!r.shouldBreak, expandedStates: r.expandedStates };
}
function Jn(e, r = "", t = {}) {
  return { type: J, breakContents: e, flatContents: r, groupId: t.groupId };
}
function Tr(e, r) {
  let t = [];
  for (let n = 0; n < r.length; n++) n !== 0 && t.push(e), t.push(r[n]);
  return t;
}
var qr = { type: $ }, Or = { type: $, soft: true }, ur = { type: $, hard: true }, R = [ur, Me], Sl = { type: $, hard: true, literal: true }, tr = [Sl, Me];
function Qn(e) {
  switch (e) {
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
var Zn = () => /[#*0-9]\uFE0F?\u20E3|[\xA9\xAE\u203C\u2049\u2122\u2139\u2194-\u2199\u21A9\u21AA\u231A\u231B\u2328\u23CF\u23ED-\u23EF\u23F1\u23F2\u23F8-\u23FA\u24C2\u25AA\u25AB\u25B6\u25C0\u25FB\u25FC\u25FE\u2600-\u2604\u260E\u2611\u2614\u2615\u2618\u2620\u2622\u2623\u2626\u262A\u262E\u262F\u2638-\u263A\u2640\u2642\u2648-\u2653\u265F\u2660\u2663\u2665\u2666\u2668\u267B\u267E\u267F\u2692\u2694-\u2697\u2699\u269B\u269C\u26A0\u26A7\u26AA\u26B0\u26B1\u26BD\u26BE\u26C4\u26C8\u26CF\u26D1\u26E9\u26F0-\u26F5\u26F7\u26F8\u26FA\u2702\u2708\u2709\u270F\u2712\u2714\u2716\u271D\u2721\u2733\u2734\u2744\u2747\u2757\u2763\u27A1\u2934\u2935\u2B05-\u2B07\u2B1B\u2B1C\u2B55\u3030\u303D\u3297\u3299]\uFE0F?|[\u261D\u270C\u270D](?:\uD83C[\uDFFB-\uDFFF]|\uFE0F)?|[\u270A\u270B](?:\uD83C[\uDFFB-\uDFFF])?|[\u23E9-\u23EC\u23F0\u23F3\u25FD\u2693\u26A1\u26AB\u26C5\u26CE\u26D4\u26EA\u26FD\u2705\u2728\u274C\u274E\u2753-\u2755\u2795-\u2797\u27B0\u27BF\u2B50]|\u26D3\uFE0F?(?:\u200D\uD83D\uDCA5)?|\u26F9(?:\uD83C[\uDFFB-\uDFFF]|\uFE0F)?(?:\u200D[\u2640\u2642]\uFE0F?)?|\u2764\uFE0F?(?:\u200D(?:\uD83D\uDD25|\uD83E\uDE79))?|\uD83C(?:[\uDC04\uDD70\uDD71\uDD7E\uDD7F\uDE02\uDE37\uDF21\uDF24-\uDF2C\uDF36\uDF7D\uDF96\uDF97\uDF99-\uDF9B\uDF9E\uDF9F\uDFCD\uDFCE\uDFD4-\uDFDF\uDFF5\uDFF7]\uFE0F?|[\uDF85\uDFC2\uDFC7](?:\uD83C[\uDFFB-\uDFFF])?|[\uDFC4\uDFCA](?:\uD83C[\uDFFB-\uDFFF])?(?:\u200D[\u2640\u2642]\uFE0F?)?|[\uDFCB\uDFCC](?:\uD83C[\uDFFB-\uDFFF]|\uFE0F)?(?:\u200D[\u2640\u2642]\uFE0F?)?|[\uDCCF\uDD8E\uDD91-\uDD9A\uDE01\uDE1A\uDE2F\uDE32-\uDE36\uDE38-\uDE3A\uDE50\uDE51\uDF00-\uDF20\uDF2D-\uDF35\uDF37-\uDF43\uDF45-\uDF4A\uDF4C-\uDF7C\uDF7E-\uDF84\uDF86-\uDF93\uDFA0-\uDFC1\uDFC5\uDFC6\uDFC8\uDFC9\uDFCF-\uDFD3\uDFE0-\uDFF0\uDFF8-\uDFFF]|\uDDE6\uD83C[\uDDE8-\uDDEC\uDDEE\uDDF1\uDDF2\uDDF4\uDDF6-\uDDFA\uDDFC\uDDFD\uDDFF]|\uDDE7\uD83C[\uDDE6\uDDE7\uDDE9-\uDDEF\uDDF1-\uDDF4\uDDF6-\uDDF9\uDDFB\uDDFC\uDDFE\uDDFF]|\uDDE8\uD83C[\uDDE6\uDDE8\uDDE9\uDDEB-\uDDEE\uDDF0-\uDDF7\uDDFA-\uDDFF]|\uDDE9\uD83C[\uDDEA\uDDEC\uDDEF\uDDF0\uDDF2\uDDF4\uDDFF]|\uDDEA\uD83C[\uDDE6\uDDE8\uDDEA\uDDEC\uDDED\uDDF7-\uDDFA]|\uDDEB\uD83C[\uDDEE-\uDDF0\uDDF2\uDDF4\uDDF7]|\uDDEC\uD83C[\uDDE6\uDDE7\uDDE9-\uDDEE\uDDF1-\uDDF3\uDDF5-\uDDFA\uDDFC\uDDFE]|\uDDED\uD83C[\uDDF0\uDDF2\uDDF3\uDDF7\uDDF9\uDDFA]|\uDDEE\uD83C[\uDDE8-\uDDEA\uDDF1-\uDDF4\uDDF6-\uDDF9]|\uDDEF\uD83C[\uDDEA\uDDF2\uDDF4\uDDF5]|\uDDF0\uD83C[\uDDEA\uDDEC-\uDDEE\uDDF2\uDDF3\uDDF5\uDDF7\uDDFC\uDDFE\uDDFF]|\uDDF1\uD83C[\uDDE6-\uDDE8\uDDEE\uDDF0\uDDF7-\uDDFB\uDDFE]|\uDDF2\uD83C[\uDDE6\uDDE8-\uDDED\uDDF0-\uDDFF]|\uDDF3\uD83C[\uDDE6\uDDE8\uDDEA-\uDDEC\uDDEE\uDDF1\uDDF4\uDDF5\uDDF7\uDDFA\uDDFF]|\uDDF4\uD83C\uDDF2|\uDDF5\uD83C[\uDDE6\uDDEA-\uDDED\uDDF0-\uDDF3\uDDF7-\uDDF9\uDDFC\uDDFE]|\uDDF6\uD83C\uDDE6|\uDDF7\uD83C[\uDDEA\uDDF4\uDDF8\uDDFA\uDDFC]|\uDDF8\uD83C[\uDDE6-\uDDEA\uDDEC-\uDDF4\uDDF7-\uDDF9\uDDFB\uDDFD-\uDDFF]|\uDDF9\uD83C[\uDDE6\uDDE8\uDDE9\uDDEB-\uDDED\uDDEF-\uDDF4\uDDF7\uDDF9\uDDFB\uDDFC\uDDFF]|\uDDFA\uD83C[\uDDE6\uDDEC\uDDF2\uDDF3\uDDF8\uDDFE\uDDFF]|\uDDFB\uD83C[\uDDE6\uDDE8\uDDEA\uDDEC\uDDEE\uDDF3\uDDFA]|\uDDFC\uD83C[\uDDEB\uDDF8]|\uDDFD\uD83C\uDDF0|\uDDFE\uD83C[\uDDEA\uDDF9]|\uDDFF\uD83C[\uDDE6\uDDF2\uDDFC]|\uDF44(?:\u200D\uD83D\uDFEB)?|\uDF4B(?:\u200D\uD83D\uDFE9)?|\uDFC3(?:\uD83C[\uDFFB-\uDFFF])?(?:\u200D(?:[\u2640\u2642]\uFE0F?(?:\u200D\u27A1\uFE0F?)?|\u27A1\uFE0F?))?|\uDFF3\uFE0F?(?:\u200D(?:\u26A7\uFE0F?|\uD83C\uDF08))?|\uDFF4(?:\u200D\u2620\uFE0F?|\uDB40\uDC67\uDB40\uDC62\uDB40(?:\uDC65\uDB40\uDC6E\uDB40\uDC67|\uDC73\uDB40\uDC63\uDB40\uDC74|\uDC77\uDB40\uDC6C\uDB40\uDC73)\uDB40\uDC7F)?)|\uD83D(?:[\uDC3F\uDCFD\uDD49\uDD4A\uDD6F\uDD70\uDD73\uDD76-\uDD79\uDD87\uDD8A-\uDD8D\uDDA5\uDDA8\uDDB1\uDDB2\uDDBC\uDDC2-\uDDC4\uDDD1-\uDDD3\uDDDC-\uDDDE\uDDE1\uDDE3\uDDE8\uDDEF\uDDF3\uDDFA\uDECB\uDECD-\uDECF\uDEE0-\uDEE5\uDEE9\uDEF0\uDEF3]\uFE0F?|[\uDC42\uDC43\uDC46-\uDC50\uDC66\uDC67\uDC6B-\uDC6D\uDC72\uDC74-\uDC76\uDC78\uDC7C\uDC83\uDC85\uDC8F\uDC91\uDCAA\uDD7A\uDD95\uDD96\uDE4C\uDE4F\uDEC0\uDECC](?:\uD83C[\uDFFB-\uDFFF])?|[\uDC6E-\uDC71\uDC73\uDC77\uDC81\uDC82\uDC86\uDC87\uDE45-\uDE47\uDE4B\uDE4D\uDE4E\uDEA3\uDEB4\uDEB5](?:\uD83C[\uDFFB-\uDFFF])?(?:\u200D[\u2640\u2642]\uFE0F?)?|[\uDD74\uDD90](?:\uD83C[\uDFFB-\uDFFF]|\uFE0F)?|[\uDC00-\uDC07\uDC09-\uDC14\uDC16-\uDC25\uDC27-\uDC3A\uDC3C-\uDC3E\uDC40\uDC44\uDC45\uDC51-\uDC65\uDC6A\uDC79-\uDC7B\uDC7D-\uDC80\uDC84\uDC88-\uDC8E\uDC90\uDC92-\uDCA9\uDCAB-\uDCFC\uDCFF-\uDD3D\uDD4B-\uDD4E\uDD50-\uDD67\uDDA4\uDDFB-\uDE2D\uDE2F-\uDE34\uDE37-\uDE41\uDE43\uDE44\uDE48-\uDE4A\uDE80-\uDEA2\uDEA4-\uDEB3\uDEB7-\uDEBF\uDEC1-\uDEC5\uDED0-\uDED2\uDED5-\uDED8\uDEDC-\uDEDF\uDEEB\uDEEC\uDEF4-\uDEFC\uDFE0-\uDFEB\uDFF0]|\uDC08(?:\u200D\u2B1B)?|\uDC15(?:\u200D\uD83E\uDDBA)?|\uDC26(?:\u200D(?:\u2B1B|\uD83D\uDD25))?|\uDC3B(?:\u200D\u2744\uFE0F?)?|\uDC41\uFE0F?(?:\u200D\uD83D\uDDE8\uFE0F?)?|\uDC68(?:\u200D(?:[\u2695\u2696\u2708]\uFE0F?|\u2764\uFE0F?\u200D\uD83D(?:\uDC8B\u200D\uD83D)?\uDC68|\uD83C[\uDF3E\uDF73\uDF7C\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D(?:[\uDC68\uDC69]\u200D\uD83D(?:\uDC66(?:\u200D\uD83D\uDC66)?|\uDC67(?:\u200D\uD83D[\uDC66\uDC67])?)|[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uDC66(?:\u200D\uD83D\uDC66)?|\uDC67(?:\u200D\uD83D[\uDC66\uDC67])?)|\uD83E(?:[\uDDAF\uDDBC\uDDBD](?:\u200D\u27A1\uFE0F?)?|[\uDDB0-\uDDB3]))|\uD83C(?:\uDFFB(?:\u200D(?:[\u2695\u2696\u2708]\uFE0F?|\u2764\uFE0F?\u200D\uD83D(?:\uDC8B\u200D\uD83D)?\uDC68\uD83C[\uDFFB-\uDFFF]|\uD83C[\uDF3E\uDF73\uDF7C\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D(?:[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uDC30\u200D\uD83D\uDC68\uD83C[\uDFFC-\uDFFF])|\uD83E(?:[\uDD1D\uDEEF]\u200D\uD83D\uDC68\uD83C[\uDFFC-\uDFFF]|[\uDDAF\uDDBC\uDDBD](?:\u200D\u27A1\uFE0F?)?|[\uDDB0-\uDDB3])))?|\uDFFC(?:\u200D(?:[\u2695\u2696\u2708]\uFE0F?|\u2764\uFE0F?\u200D\uD83D(?:\uDC8B\u200D\uD83D)?\uDC68\uD83C[\uDFFB-\uDFFF]|\uD83C[\uDF3E\uDF73\uDF7C\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D(?:[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uDC30\u200D\uD83D\uDC68\uD83C[\uDFFB\uDFFD-\uDFFF])|\uD83E(?:[\uDD1D\uDEEF]\u200D\uD83D\uDC68\uD83C[\uDFFB\uDFFD-\uDFFF]|[\uDDAF\uDDBC\uDDBD](?:\u200D\u27A1\uFE0F?)?|[\uDDB0-\uDDB3])))?|\uDFFD(?:\u200D(?:[\u2695\u2696\u2708]\uFE0F?|\u2764\uFE0F?\u200D\uD83D(?:\uDC8B\u200D\uD83D)?\uDC68\uD83C[\uDFFB-\uDFFF]|\uD83C[\uDF3E\uDF73\uDF7C\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D(?:[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uDC30\u200D\uD83D\uDC68\uD83C[\uDFFB\uDFFC\uDFFE\uDFFF])|\uD83E(?:[\uDD1D\uDEEF]\u200D\uD83D\uDC68\uD83C[\uDFFB\uDFFC\uDFFE\uDFFF]|[\uDDAF\uDDBC\uDDBD](?:\u200D\u27A1\uFE0F?)?|[\uDDB0-\uDDB3])))?|\uDFFE(?:\u200D(?:[\u2695\u2696\u2708]\uFE0F?|\u2764\uFE0F?\u200D\uD83D(?:\uDC8B\u200D\uD83D)?\uDC68\uD83C[\uDFFB-\uDFFF]|\uD83C[\uDF3E\uDF73\uDF7C\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D(?:[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uDC30\u200D\uD83D\uDC68\uD83C[\uDFFB-\uDFFD\uDFFF])|\uD83E(?:[\uDD1D\uDEEF]\u200D\uD83D\uDC68\uD83C[\uDFFB-\uDFFD\uDFFF]|[\uDDAF\uDDBC\uDDBD](?:\u200D\u27A1\uFE0F?)?|[\uDDB0-\uDDB3])))?|\uDFFF(?:\u200D(?:[\u2695\u2696\u2708]\uFE0F?|\u2764\uFE0F?\u200D\uD83D(?:\uDC8B\u200D\uD83D)?\uDC68\uD83C[\uDFFB-\uDFFF]|\uD83C[\uDF3E\uDF73\uDF7C\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D(?:[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uDC30\u200D\uD83D\uDC68\uD83C[\uDFFB-\uDFFE])|\uD83E(?:[\uDD1D\uDEEF]\u200D\uD83D\uDC68\uD83C[\uDFFB-\uDFFE]|[\uDDAF\uDDBC\uDDBD](?:\u200D\u27A1\uFE0F?)?|[\uDDB0-\uDDB3])))?))?|\uDC69(?:\u200D(?:[\u2695\u2696\u2708]\uFE0F?|\u2764\uFE0F?\u200D\uD83D(?:\uDC8B\u200D\uD83D)?[\uDC68\uDC69]|\uD83C[\uDF3E\uDF73\uDF7C\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D(?:[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uDC66(?:\u200D\uD83D\uDC66)?|\uDC67(?:\u200D\uD83D[\uDC66\uDC67])?|\uDC69\u200D\uD83D(?:\uDC66(?:\u200D\uD83D\uDC66)?|\uDC67(?:\u200D\uD83D[\uDC66\uDC67])?))|\uD83E(?:[\uDDAF\uDDBC\uDDBD](?:\u200D\u27A1\uFE0F?)?|[\uDDB0-\uDDB3]))|\uD83C(?:\uDFFB(?:\u200D(?:[\u2695\u2696\u2708]\uFE0F?|\u2764\uFE0F?\u200D\uD83D(?:[\uDC68\uDC69]|\uDC8B\u200D\uD83D[\uDC68\uDC69])\uD83C[\uDFFB-\uDFFF]|\uD83C[\uDF3E\uDF73\uDF7C\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D(?:[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uDC30\u200D\uD83D\uDC69\uD83C[\uDFFC-\uDFFF])|\uD83E(?:[\uDDAF\uDDBC\uDDBD](?:\u200D\u27A1\uFE0F?)?|[\uDDB0-\uDDB3]|\uDD1D\u200D\uD83D[\uDC68\uDC69]\uD83C[\uDFFC-\uDFFF]|\uDEEF\u200D\uD83D\uDC69\uD83C[\uDFFC-\uDFFF])))?|\uDFFC(?:\u200D(?:[\u2695\u2696\u2708]\uFE0F?|\u2764\uFE0F?\u200D\uD83D(?:[\uDC68\uDC69]|\uDC8B\u200D\uD83D[\uDC68\uDC69])\uD83C[\uDFFB-\uDFFF]|\uD83C[\uDF3E\uDF73\uDF7C\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D(?:[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uDC30\u200D\uD83D\uDC69\uD83C[\uDFFB\uDFFD-\uDFFF])|\uD83E(?:[\uDDAF\uDDBC\uDDBD](?:\u200D\u27A1\uFE0F?)?|[\uDDB0-\uDDB3]|\uDD1D\u200D\uD83D[\uDC68\uDC69]\uD83C[\uDFFB\uDFFD-\uDFFF]|\uDEEF\u200D\uD83D\uDC69\uD83C[\uDFFB\uDFFD-\uDFFF])))?|\uDFFD(?:\u200D(?:[\u2695\u2696\u2708]\uFE0F?|\u2764\uFE0F?\u200D\uD83D(?:[\uDC68\uDC69]|\uDC8B\u200D\uD83D[\uDC68\uDC69])\uD83C[\uDFFB-\uDFFF]|\uD83C[\uDF3E\uDF73\uDF7C\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D(?:[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uDC30\u200D\uD83D\uDC69\uD83C[\uDFFB\uDFFC\uDFFE\uDFFF])|\uD83E(?:[\uDDAF\uDDBC\uDDBD](?:\u200D\u27A1\uFE0F?)?|[\uDDB0-\uDDB3]|\uDD1D\u200D\uD83D[\uDC68\uDC69]\uD83C[\uDFFB\uDFFC\uDFFE\uDFFF]|\uDEEF\u200D\uD83D\uDC69\uD83C[\uDFFB\uDFFC\uDFFE\uDFFF])))?|\uDFFE(?:\u200D(?:[\u2695\u2696\u2708]\uFE0F?|\u2764\uFE0F?\u200D\uD83D(?:[\uDC68\uDC69]|\uDC8B\u200D\uD83D[\uDC68\uDC69])\uD83C[\uDFFB-\uDFFF]|\uD83C[\uDF3E\uDF73\uDF7C\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D(?:[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uDC30\u200D\uD83D\uDC69\uD83C[\uDFFB-\uDFFD\uDFFF])|\uD83E(?:[\uDDAF\uDDBC\uDDBD](?:\u200D\u27A1\uFE0F?)?|[\uDDB0-\uDDB3]|\uDD1D\u200D\uD83D[\uDC68\uDC69]\uD83C[\uDFFB-\uDFFD\uDFFF]|\uDEEF\u200D\uD83D\uDC69\uD83C[\uDFFB-\uDFFD\uDFFF])))?|\uDFFF(?:\u200D(?:[\u2695\u2696\u2708]\uFE0F?|\u2764\uFE0F?\u200D\uD83D(?:[\uDC68\uDC69]|\uDC8B\u200D\uD83D[\uDC68\uDC69])\uD83C[\uDFFB-\uDFFF]|\uD83C[\uDF3E\uDF73\uDF7C\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D(?:[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uDC30\u200D\uD83D\uDC69\uD83C[\uDFFB-\uDFFE])|\uD83E(?:[\uDDAF\uDDBC\uDDBD](?:\u200D\u27A1\uFE0F?)?|[\uDDB0-\uDDB3]|\uDD1D\u200D\uD83D[\uDC68\uDC69]\uD83C[\uDFFB-\uDFFE]|\uDEEF\u200D\uD83D\uDC69\uD83C[\uDFFB-\uDFFE])))?))?|\uDD75(?:\uD83C[\uDFFB-\uDFFF]|\uFE0F)?(?:\u200D[\u2640\u2642]\uFE0F?)?|\uDE2E(?:\u200D\uD83D\uDCA8)?|\uDE35(?:\u200D\uD83D\uDCAB)?|\uDE36(?:\u200D\uD83C\uDF2B\uFE0F?)?|\uDE42(?:\u200D[\u2194\u2195]\uFE0F?)?|\uDEB6(?:\uD83C[\uDFFB-\uDFFF])?(?:\u200D(?:[\u2640\u2642]\uFE0F?(?:\u200D\u27A1\uFE0F?)?|\u27A1\uFE0F?))?)|\uD83E(?:[\uDD0C\uDD0F\uDD18-\uDD1F\uDD30-\uDD34\uDD36\uDD77\uDDB5\uDDB6\uDDBB\uDDD2\uDDD3\uDDD5\uDEC3-\uDEC5\uDEF0\uDEF2-\uDEF8](?:\uD83C[\uDFFB-\uDFFF])?|[\uDD26\uDD35\uDD37-\uDD39\uDD3C-\uDD3E\uDDB8\uDDB9\uDDCD\uDDCF\uDDD4\uDDD6-\uDDDD](?:\uD83C[\uDFFB-\uDFFF])?(?:\u200D[\u2640\u2642]\uFE0F?)?|[\uDDDE\uDDDF](?:\u200D[\u2640\u2642]\uFE0F?)?|[\uDD0D\uDD0E\uDD10-\uDD17\uDD20-\uDD25\uDD27-\uDD2F\uDD3A\uDD3F-\uDD45\uDD47-\uDD76\uDD78-\uDDB4\uDDB7\uDDBA\uDDBC-\uDDCC\uDDD0\uDDE0-\uDDFF\uDE70-\uDE7C\uDE80-\uDE8A\uDE8E-\uDEC2\uDEC6\uDEC8\uDECD-\uDEDC\uDEDF-\uDEEA\uDEEF]|\uDDCE(?:\uD83C[\uDFFB-\uDFFF])?(?:\u200D(?:[\u2640\u2642]\uFE0F?(?:\u200D\u27A1\uFE0F?)?|\u27A1\uFE0F?))?|\uDDD1(?:\u200D(?:[\u2695\u2696\u2708]\uFE0F?|\uD83C[\uDF3E\uDF73\uDF7C\uDF84\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uD83E(?:[\uDDAF\uDDBC\uDDBD](?:\u200D\u27A1\uFE0F?)?|[\uDDB0-\uDDB3\uDE70]|\uDD1D\u200D\uD83E\uDDD1|\uDDD1\u200D\uD83E\uDDD2(?:\u200D\uD83E\uDDD2)?|\uDDD2(?:\u200D\uD83E\uDDD2)?))|\uD83C(?:\uDFFB(?:\u200D(?:[\u2695\u2696\u2708]\uFE0F?|\u2764\uFE0F?\u200D(?:\uD83D\uDC8B\u200D)?\uD83E\uDDD1\uD83C[\uDFFC-\uDFFF]|\uD83C[\uDF3E\uDF73\uDF7C\uDF84\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D(?:[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uDC30\u200D\uD83E\uDDD1\uD83C[\uDFFC-\uDFFF])|\uD83E(?:[\uDDAF\uDDBC\uDDBD](?:\u200D\u27A1\uFE0F?)?|[\uDDB0-\uDDB3\uDE70]|\uDD1D\u200D\uD83E\uDDD1\uD83C[\uDFFB-\uDFFF]|\uDEEF\u200D\uD83E\uDDD1\uD83C[\uDFFC-\uDFFF])))?|\uDFFC(?:\u200D(?:[\u2695\u2696\u2708]\uFE0F?|\u2764\uFE0F?\u200D(?:\uD83D\uDC8B\u200D)?\uD83E\uDDD1\uD83C[\uDFFB\uDFFD-\uDFFF]|\uD83C[\uDF3E\uDF73\uDF7C\uDF84\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D(?:[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uDC30\u200D\uD83E\uDDD1\uD83C[\uDFFB\uDFFD-\uDFFF])|\uD83E(?:[\uDDAF\uDDBC\uDDBD](?:\u200D\u27A1\uFE0F?)?|[\uDDB0-\uDDB3\uDE70]|\uDD1D\u200D\uD83E\uDDD1\uD83C[\uDFFB-\uDFFF]|\uDEEF\u200D\uD83E\uDDD1\uD83C[\uDFFB\uDFFD-\uDFFF])))?|\uDFFD(?:\u200D(?:[\u2695\u2696\u2708]\uFE0F?|\u2764\uFE0F?\u200D(?:\uD83D\uDC8B\u200D)?\uD83E\uDDD1\uD83C[\uDFFB\uDFFC\uDFFE\uDFFF]|\uD83C[\uDF3E\uDF73\uDF7C\uDF84\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D(?:[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uDC30\u200D\uD83E\uDDD1\uD83C[\uDFFB\uDFFC\uDFFE\uDFFF])|\uD83E(?:[\uDDAF\uDDBC\uDDBD](?:\u200D\u27A1\uFE0F?)?|[\uDDB0-\uDDB3\uDE70]|\uDD1D\u200D\uD83E\uDDD1\uD83C[\uDFFB-\uDFFF]|\uDEEF\u200D\uD83E\uDDD1\uD83C[\uDFFB\uDFFC\uDFFE\uDFFF])))?|\uDFFE(?:\u200D(?:[\u2695\u2696\u2708]\uFE0F?|\u2764\uFE0F?\u200D(?:\uD83D\uDC8B\u200D)?\uD83E\uDDD1\uD83C[\uDFFB-\uDFFD\uDFFF]|\uD83C[\uDF3E\uDF73\uDF7C\uDF84\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D(?:[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uDC30\u200D\uD83E\uDDD1\uD83C[\uDFFB-\uDFFD\uDFFF])|\uD83E(?:[\uDDAF\uDDBC\uDDBD](?:\u200D\u27A1\uFE0F?)?|[\uDDB0-\uDDB3\uDE70]|\uDD1D\u200D\uD83E\uDDD1\uD83C[\uDFFB-\uDFFF]|\uDEEF\u200D\uD83E\uDDD1\uD83C[\uDFFB-\uDFFD\uDFFF])))?|\uDFFF(?:\u200D(?:[\u2695\u2696\u2708]\uFE0F?|\u2764\uFE0F?\u200D(?:\uD83D\uDC8B\u200D)?\uD83E\uDDD1\uD83C[\uDFFB-\uDFFE]|\uD83C[\uDF3E\uDF73\uDF7C\uDF84\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D(?:[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uDC30\u200D\uD83E\uDDD1\uD83C[\uDFFB-\uDFFE])|\uD83E(?:[\uDDAF\uDDBC\uDDBD](?:\u200D\u27A1\uFE0F?)?|[\uDDB0-\uDDB3\uDE70]|\uDD1D\u200D\uD83E\uDDD1\uD83C[\uDFFB-\uDFFF]|\uDEEF\u200D\uD83E\uDDD1\uD83C[\uDFFB-\uDFFE])))?))?|\uDEF1(?:\uD83C(?:\uDFFB(?:\u200D\uD83E\uDEF2\uD83C[\uDFFC-\uDFFF])?|\uDFFC(?:\u200D\uD83E\uDEF2\uD83C[\uDFFB\uDFFD-\uDFFF])?|\uDFFD(?:\u200D\uD83E\uDEF2\uD83C[\uDFFB\uDFFC\uDFFE\uDFFF])?|\uDFFE(?:\u200D\uD83E\uDEF2\uD83C[\uDFFB-\uDFFD\uDFFF])?|\uDFFF(?:\u200D\uD83E\uDEF2\uD83C[\uDFFB-\uDFFE])?))?)/g;
function gt(e) {
  return e === 12288 || e >= 65281 && e <= 65376 || e >= 65504 && e <= 65510;
}
function Et(e) {
  return e >= 4352 && e <= 4447 || e === 8986 || e === 8987 || e === 9001 || e === 9002 || e >= 9193 && e <= 9196 || e === 9200 || e === 9203 || e === 9725 || e === 9726 || e === 9748 || e === 9749 || e >= 9776 && e <= 9783 || e >= 9800 && e <= 9811 || e === 9855 || e >= 9866 && e <= 9871 || e === 9875 || e === 9889 || e === 9898 || e === 9899 || e === 9917 || e === 9918 || e === 9924 || e === 9925 || e === 9934 || e === 9940 || e === 9962 || e === 9970 || e === 9971 || e === 9973 || e === 9978 || e === 9981 || e === 9989 || e === 9994 || e === 9995 || e === 10024 || e === 10060 || e === 10062 || e >= 10067 && e <= 10069 || e === 10071 || e >= 10133 && e <= 10135 || e === 10160 || e === 10175 || e === 11035 || e === 11036 || e === 11088 || e === 11093 || e >= 11904 && e <= 11929 || e >= 11931 && e <= 12019 || e >= 12032 && e <= 12245 || e >= 12272 && e <= 12287 || e >= 12289 && e <= 12350 || e >= 12353 && e <= 12438 || e >= 12441 && e <= 12543 || e >= 12549 && e <= 12591 || e >= 12593 && e <= 12686 || e >= 12688 && e <= 12773 || e >= 12783 && e <= 12830 || e >= 12832 && e <= 12871 || e >= 12880 && e <= 42124 || e >= 42128 && e <= 42182 || e >= 43360 && e <= 43388 || e >= 44032 && e <= 55203 || e >= 63744 && e <= 64255 || e >= 65040 && e <= 65049 || e >= 65072 && e <= 65106 || e >= 65108 && e <= 65126 || e >= 65128 && e <= 65131 || e >= 94176 && e <= 94180 || e >= 94192 && e <= 94198 || e >= 94208 && e <= 101589 || e >= 101631 && e <= 101662 || e >= 101760 && e <= 101874 || e >= 110576 && e <= 110579 || e >= 110581 && e <= 110587 || e === 110589 || e === 110590 || e >= 110592 && e <= 110882 || e === 110898 || e >= 110928 && e <= 110930 || e === 110933 || e >= 110948 && e <= 110951 || e >= 110960 && e <= 111355 || e >= 119552 && e <= 119638 || e >= 119648 && e <= 119670 || e === 126980 || e === 127183 || e === 127374 || e >= 127377 && e <= 127386 || e >= 127488 && e <= 127490 || e >= 127504 && e <= 127547 || e >= 127552 && e <= 127560 || e === 127568 || e === 127569 || e >= 127584 && e <= 127589 || e >= 127744 && e <= 127776 || e >= 127789 && e <= 127797 || e >= 127799 && e <= 127868 || e >= 127870 && e <= 127891 || e >= 127904 && e <= 127946 || e >= 127951 && e <= 127955 || e >= 127968 && e <= 127984 || e === 127988 || e >= 127992 && e <= 128062 || e === 128064 || e >= 128066 && e <= 128252 || e >= 128255 && e <= 128317 || e >= 128331 && e <= 128334 || e >= 128336 && e <= 128359 || e === 128378 || e === 128405 || e === 128406 || e === 128420 || e >= 128507 && e <= 128591 || e >= 128640 && e <= 128709 || e === 128716 || e >= 128720 && e <= 128722 || e >= 128725 && e <= 128728 || e >= 128732 && e <= 128735 || e === 128747 || e === 128748 || e >= 128756 && e <= 128764 || e >= 128992 && e <= 129003 || e === 129008 || e >= 129292 && e <= 129338 || e >= 129340 && e <= 129349 || e >= 129351 && e <= 129535 || e >= 129648 && e <= 129660 || e >= 129664 && e <= 129674 || e >= 129678 && e <= 129734 || e === 129736 || e >= 129741 && e <= 129756 || e >= 129759 && e <= 129770 || e >= 129775 && e <= 129784 || e >= 131072 && e <= 196605 || e >= 196608 && e <= 262141;
}
var ei = "©®‼⁉™ℹ↔↕↖↗↘↙↩↪⌨⏏⏱⏲⏸⏹⏺▪▫▶◀◻◼☀☁☂☃☄☎☑☘☝☠☢☣☦☪☮☯☸☹☺♀♂♟♠♣♥♦♨♻♾⚒⚔⚕⚖⚗⚙⚛⚜⚠⚧⚰⚱⛈⛏⛑⛓⛩⛱⛷⛸⛹✂✈✉✌✍✏✒✔✖✝✡✳✴❄❇❣❤➡⤴⤵⬅⬆⬇";
var Nl = /[^\x20-\x7F]/u, Il = new Set(ei);
function Pl(e) {
  if (!e) return 0;
  if (!Nl.test(e)) return e.length;
  e = e.replace(Zn(), (t) => Il.has(t) ? " " : "  ");
  let r = 0;
  for (let t of e) {
    let n = t.codePointAt(0);
    n <= 31 || n >= 127 && n <= 159 || n >= 768 && n <= 879 || n >= 65024 && n <= 65039 || (r += gt(n) || Et(n) ? 2 : 1);
  }
  return r;
}
var ar = Pl;
var Ll = { type: 0 }, Rl = { type: 1 }, Ct = { value: "", length: 0, queue: [], get root() {
  return Ct;
} };
function ri(e, r, t) {
  let n = r.type === 1 ? e.queue.slice(0, -1) : [...e.queue, r], u = "", i = 0, a = 0, o = 0;
  for (let p of n) switch (p.type) {
    case 0:
      c(), t.useTabs ? s(1) : f(t.tabWidth);
      break;
    case 3: {
      let { string: h } = p;
      c(), u += h, i += h.length;
      break;
    }
    case 2: {
      let { width: h } = p;
      a += 1, o += h;
      break;
    }
    default:
      throw new Error(`Unexpected indent comment '${p.type}'.`);
  }
  return D(), { ...e, value: u, length: i, queue: n };
  function s(p) {
    u += "	".repeat(p), i += t.tabWidth * p;
  }
  function f(p) {
    u += " ".repeat(p), i += p;
  }
  function c() {
    t.useTabs ? l() : D();
  }
  function l() {
    a > 0 && s(a), m();
  }
  function D() {
    o > 0 && f(o), m();
  }
  function m() {
    a = 0, o = 0;
  }
}
function ti(e, r, t) {
  if (!r) return e;
  if (r.type === "root") return { ...e, root: e };
  if (r === Number.NEGATIVE_INFINITY) return e.root;
  let n;
  return typeof r == "number" ? r < 0 ? n = Rl : n = { type: 2, width: r } : n = { type: 3, string: r }, ri(e, n, t);
}
function ni(e, r) {
  return ri(e, Ll, r);
}
function Ml(e) {
  let r = 0;
  for (let t = e.length - 1; t >= 0; t--) {
    let n = e[t];
    if (n === " " || n === "	") r++;
    else break;
  }
  return r;
}
function vt(e) {
  let r = Ml(e);
  return { text: r === 0 ? e : e.slice(0, e.length - r), count: r };
}
var j = Symbol("MODE_BREAK"), ie = Symbol("MODE_FLAT"), At = Symbol("DOC_FILL_PRINTED_LENGTH");
function Sr(e, r, t, n, u, i) {
  if (t === Number.POSITIVE_INFINITY) return true;
  let a = r.length, o = false, s = [e], f = "";
  for (; t >= 0; ) {
    if (s.length === 0) {
      if (a === 0) return true;
      s.push(r[--a]);
      continue;
    }
    let { mode: c, doc: l } = s.pop(), D = z(l);
    switch (D) {
      case W:
        l && (o && (f += " ", t -= 1, o = false), f += l, t -= ar(l));
        break;
      case V:
      case X: {
        let m = D === V ? l : l.parts, p = l[At] ?? 0;
        for (let h = m.length - 1; h >= p; h--) s.push({ mode: c, doc: m[h] });
        break;
      }
      case Z:
      case ee:
      case De:
      case de:
        s.push({ mode: c, doc: l.contents });
        break;
      case fe: {
        let { text: m, count: p } = vt(f);
        f = m, t += p;
        break;
      }
      case K: {
        if (i && l.break) return false;
        let m = l.break ? j : c, p = l.expandedStates && m === j ? M(0, l.expandedStates, -1) : l.contents;
        s.push({ mode: m, doc: p });
        break;
      }
      case J: {
        let p = (l.groupId ? u[l.groupId] || ie : c) === j ? l.breakContents : l.flatContents;
        p && s.push({ mode: c, doc: p });
        break;
      }
      case $:
        if (c === j || l.hard) return true;
        l.soft || (o = true);
        break;
      case pe:
        n = true;
        break;
      case he:
        if (n) return false;
        break;
    }
  }
  return false;
}
function ii(e, r) {
  let t = /* @__PURE__ */ Object.create(null), n = r.printWidth, u = Qn(r.endOfLine), i = 0, a = [{ indent: Ct, mode: j, doc: e }], o = "", s = false, f = [], c = [], l = [], D = [], m = 0;
  for (Hn(e); a.length > 0; ) {
    let { indent: E, mode: v, doc: A } = a.pop();
    switch (z(A)) {
      case W: {
        let b = u !== `
` ? L(0, A, `
`, u) : A;
        b && (o += b, a.length > 0 && (i += ar(b)));
        break;
      }
      case V:
        for (let b = A.length - 1; b >= 0; b--) a.push({ indent: E, mode: v, doc: A[b] });
        break;
      case Ae:
        if (c.length >= 2) throw new Error("There are too many 'cursor' in doc.");
        c.push(m + o.length);
        break;
      case Z:
        a.push({ indent: ni(E, r), mode: v, doc: A.contents });
        break;
      case ee:
        a.push({ indent: ti(E, A.n, r), mode: v, doc: A.contents });
        break;
      case fe:
        g();
        break;
      case K:
        switch (v) {
          case ie:
            if (!s) {
              a.push({ indent: E, mode: A.break ? j : ie, doc: A.contents });
              break;
            }
          case j: {
            s = false;
            let b = { indent: E, mode: ie, doc: A.contents }, d = n - i, y = f.length > 0;
            if (!A.break && Sr(b, a, d, y, t)) a.push(b);
            else if (A.expandedStates) {
              let w = M(0, A.expandedStates, -1);
              if (A.break) {
                a.push({ indent: E, mode: j, doc: w });
                break;
              } else for (let C = 1; C < A.expandedStates.length + 1; C++) if (C >= A.expandedStates.length) {
                a.push({ indent: E, mode: j, doc: w });
                break;
              } else {
                let k = A.expandedStates[C], B = { indent: E, mode: ie, doc: k };
                if (Sr(B, a, d, y, t)) {
                  a.push(B);
                  break;
                }
              }
            } else a.push({ indent: E, mode: j, doc: A.contents });
            break;
          }
        }
        A.id && (t[A.id] = M(0, a, -1).mode);
        break;
      case X: {
        let b = n - i, d = A[At] ?? 0, { parts: y } = A, w = y.length - d;
        if (w === 0) break;
        let C = y[d + 0], k = y[d + 1], B = { indent: E, mode: ie, doc: C }, T = { indent: E, mode: j, doc: C }, _ = Sr(B, [], b, f.length > 0, t, true);
        if (w === 1) {
          _ ? a.push(B) : a.push(T);
          break;
        }
        let I = { indent: E, mode: ie, doc: k }, S = { indent: E, mode: j, doc: k };
        if (w === 2) {
          _ ? a.push(I, B) : a.push(S, T);
          break;
        }
        let O = y[d + 2], q = { indent: E, mode: v, doc: { ...A, [At]: d + 2 } }, ce = Sr({ indent: E, mode: ie, doc: [C, k, O] }, [], b, f.length > 0, t, true);
        a.push(q), ce ? a.push(I, B) : _ ? a.push(S, B) : a.push(S, T);
        break;
      }
      case J:
      case De: {
        let b = A.groupId ? t[A.groupId] : v;
        if (b === j) {
          let d = A.type === J ? A.breakContents : A.negate ? A.contents : nr(A.contents);
          d && a.push({ indent: E, mode: v, doc: d });
        }
        if (b === ie) {
          let d = A.type === J ? A.flatContents : A.negate ? nr(A.contents) : A.contents;
          d && a.push({ indent: E, mode: v, doc: d });
        }
        break;
      }
      case pe:
        f.push({ indent: E, mode: v, doc: A.contents });
        break;
      case he:
        f.length > 0 && a.push({ indent: E, mode: v, doc: ur });
        break;
      case $:
        switch (v) {
          case ie:
            if (A.hard) s = true;
            else {
              A.soft || (o += " ", i += 1);
              break;
            }
          case j:
            if (f.length > 0) {
              a.push({ indent: E, mode: v, doc: A }, ...f.reverse()), f.length = 0;
              break;
            }
            A.literal ? (o += u, i = 0, E.root && (E.root.value && (o += E.root.value), i = E.root.length)) : (g(), o += u + E.value, i = E.length);
            break;
        }
        break;
      case de:
        a.push({ indent: E, mode: v, doc: A.contents });
        break;
      case re:
        break;
      default:
        throw new Be(A);
    }
    a.length === 0 && f.length > 0 && (a.push(...f.reverse()), f.length = 0);
  }
  let p = l.join("") + o, h = [...D, ...c];
  if (h.length !== 2) return { formatted: p };
  let F = h[0];
  return { formatted: p, cursorNodeStart: F, cursorNodeText: p.slice(F, M(0, h, -1)) };
  function g() {
    let { text: E, count: v } = vt(o);
    E && (l.push(E), m += E.length), o = "", i -= v, c.length > 0 && (D.push(...c.map((A) => Math.min(A, m))), c.length = 0);
  }
}
function Ul(e, r) {
  let t = e.matchAll(new RegExp(`(?:${le(r)})+`, "gu"));
  return t.reduce || (t = [...t]), t.reduce((n, [u]) => Math.max(n, u.length), 0) / r.length;
}
var Nr = Ul;
function Yl(e, r) {
  let t = e.match(new RegExp(`(${le(r)})+`, "gu"));
  if (t === null) return 1;
  let n = /* @__PURE__ */ new Map(), u = 0;
  for (let i of t) {
    let a = i.length / r.length;
    n.set(a, true), a > u && (u = a);
  }
  for (let i = 1; i < u; i++) if (!n.get(i)) return i;
  return u + 1;
}
var ui = Yl;
function Gl(e, r) {
  let t = r === true || r === "'" ? "'" : '"', n = t === "'" ? '"' : "'", u = 0, i = 0;
  for (let a of e) a === t ? u++ : a === n && i++;
  return u > i ? n : t;
}
var ai = Gl;
var bt = class extends Error {
  name = "UnexpectedNodeError";
  constructor(r, t, n = "type") {
    super(`Unexpected ${t} node ${n}: ${JSON.stringify(r[n])}.`), this.node = r;
  }
}, oi = bt;
var Ci = Le(kr());
var zl = Array.prototype.toReversed ?? function() {
  return [...this].reverse();
}, Wl = Re("toReversed", function() {
  if (Array.isArray(this)) return zl;
}), si = Wl;
function Vl() {
  let e = globalThis, r = e.Deno?.build?.os;
  return typeof r == "string" ? r === "windows" : e.navigator?.platform?.startsWith("Win") ?? e.process?.platform?.startsWith("win") ?? false;
}
var $l = Vl();
function ci(e) {
  if (e = e instanceof URL ? e : new URL(e), e.protocol !== "file:") throw new TypeError(`URL must be a file URL: received "${e.protocol}"`);
  return e;
}
function jl(e) {
  return e = ci(e), decodeURIComponent(e.pathname.replace(/%(?![0-9A-Fa-f]{2})/g, "%25"));
}
function Hl(e) {
  e = ci(e);
  let r = decodeURIComponent(e.pathname.replace(/\//g, "\\").replace(/%(?![0-9A-Fa-f]{2})/g, "%25")).replace(/^\\*([A-Za-z]:)(\\|$)/, "$1\\");
  return e.hostname !== "" && (r = `\\\\${e.hostname}${r}`), r;
}
function xt(e) {
  return $l ? Hl(e) : jl(e);
}
var li = (e) => String(e).split(/[/\\]/u).pop(), fi = (e) => String(e).startsWith("file:");
function Di(e, r) {
  if (!r) return;
  let t = li(r).toLowerCase();
  return e.find(({ filenames: n }) => n?.some((u) => u.toLowerCase() === t)) ?? e.find(({ extensions: n }) => n?.some((u) => t.endsWith(u)));
}
function Kl(e, r) {
  if (r) return e.find(({ name: t }) => t.toLowerCase() === r) ?? e.find(({ aliases: t }) => t?.includes(r)) ?? e.find(({ extensions: t }) => t?.includes(`.${r}`));
}
var Xl = void 0;
function pi(e, r) {
  if (r) {
    if (fi(r)) try {
      r = xt(r);
    } catch {
      return;
    }
    if (typeof r == "string") return e.find(({ isSupported: t }) => t?.({ filepath: r }));
  }
}
function Jl(e, r) {
  let t = si(0, e.plugins).flatMap((u) => u.languages ?? []);
  return (Kl(t, r.language) ?? Di(t, r.physicalFile) ?? Di(t, r.file) ?? pi(t, r.physicalFile) ?? pi(t, r.file) ?? Xl?.(t, r.physicalFile))?.parsers[0];
}
var hi = Jl;
var Ir = Symbol.for("PRETTIER_IS_FRONT_MATTER");
function Ql(e) {
  return !!e?.[Ir];
}
var yt = Ql;
var or = 3;
function Zl(e) {
  let r = e.slice(0, or);
  if (r !== "---" && r !== "+++") return;
  let t = e.indexOf(`
`, or);
  if (t === -1) return;
  let n = e.slice(or, t).trim(), u = e.indexOf(`
${r}`, t), i = n;
  if (i || (i = r === "+++" ? "toml" : "yaml"), u === -1 && r === "---" && i === "yaml" && (u = e.indexOf(`
...`, t)), u === -1) return;
  let a = u + 1 + or, o = e.charAt(a + 1);
  if (!/\s?/u.test(o)) return;
  let s = e.slice(0, a), f;
  return { language: i, explicitLanguage: n || null, value: e.slice(t + 1, u), startDelimiter: r, endDelimiter: s.slice(-or), raw: s, start: { line: 1, column: 0, index: 0 }, end: { index: s.length, get line() {
    return f ?? (f = s.split(`
`)), f.length;
  }, get column() {
    return f ?? (f = s.split(`
`)), M(0, f, -1).length;
  } }, [Ir]: true };
}
function ef(e) {
  let r = Zl(e);
  return r ? { frontMatter: r, get content() {
    let { raw: t } = r;
    return L(0, t, /[^\n]/gu, " ") + e.slice(t.length);
  } } : { content: e };
}
var Te = ef;
var di = "format";
var mi = /<!--\s*@(?:noformat|noprettier)\s*-->|\{\s*\/\*\s*@(?:noformat|noprettier)\s*\*\/\s*\}|<!--.*\r?\n[\s\S]*(^|\n)[^\S\n]*@(?:noformat|noprettier)[^\S\n]*($|\n)[\s\S]*\n.*-->/mu, Fi = /<!--\s*@(?:format|prettier)\s*-->|\{\s*\/\*\s*@(?:format|prettier)\s*\*\/\s*\}|<!--.*\r?\n[\s\S]*(^|\n)[^\S\n]*@(?:format|prettier)[^\S\n]*($|\n)[\s\S]*\n.*-->/mu;
var Pr = (e) => Te(e).content.trimStart().match(Fi)?.index === 0, gi = (e) => Te(e).content.trimStart().match(mi)?.index === 0, Ei = (e) => {
  let { frontMatter: r } = Te(e), t = `<!-- @${di} -->`;
  return r ? `${r.raw}

${t}

${e.slice(r.end.index)}` : `${t}

${e}`;
};
var rf = /* @__PURE__ */ new Set(["position", "raw"]);
function vi(e, r, t) {
  if ((e.type === "code" || e.type === "yaml" || e.type === "import" || e.type === "export" || e.type === "jsx") && delete r.value, e.type === "list" && delete r.isAligned, (e.type === "list" || e.type === "listItem") && delete r.spread, e.type === "text") return null;
  if (e.type === "inlineCode" && (r.value = L(0, e.value, `
`, " ")), e.type === "wikiLink" && (r.value = L(0, e.value.trim(), /[\t\n]+/gu, " ")), (e.type === "definition" || e.type === "linkReference" || e.type === "imageReference") && (r.label = (0, Ci.default)(e.label)), (e.type === "link" || e.type === "image") && e.url && e.url.includes("(")) for (let n of "<>") r.url = L(0, e.url, n, encodeURIComponent(n));
  if ((e.type === "definition" || e.type === "link" || e.type === "image") && e.title && (r.title = L(0, e.title, /\\(?=["')])/gu, "")), t?.type === "root" && t.children.length > 0 && (t.children[0] === e || yt(t.children[0]) && t.children[1] === e) && e.type === "html" && Pr(e.value)) return null;
}
vi.ignoredProperties = rf;
var Ai = vi;
var bi = /(?:[\u{2c7}\u{2c9}-\u{2cb}\u{2d9}\u{2ea}-\u{2eb}\u{305}\u{323}\u{1100}-\u{11ff}\u{2e80}-\u{2e99}\u{2e9b}-\u{2ef3}\u{2f00}-\u{2fd5}\u{2ff0}-\u{303f}\u{3041}-\u{3096}\u{3099}-\u{30ff}\u{3105}-\u{312f}\u{3131}-\u{318e}\u{3190}-\u{4dbf}\u{4e00}-\u{9fff}\u{a700}-\u{a707}\u{a960}-\u{a97c}\u{ac00}-\u{d7a3}\u{d7b0}-\u{d7c6}\u{d7cb}-\u{d7fb}\u{f900}-\u{fa6d}\u{fa70}-\u{fad9}\u{fe10}-\u{fe1f}\u{fe30}-\u{fe6f}\u{ff00}-\u{ffef}\u{16fe3}\u{16ff2}-\u{16ff6}\u{1aff0}-\u{1aff3}\u{1aff5}-\u{1affb}\u{1affd}-\u{1affe}\u{1b000}-\u{1b122}\u{1b132}\u{1b150}-\u{1b152}\u{1b155}\u{1b164}-\u{1b167}\u{1f200}\u{1f250}-\u{1f251}\u{20000}-\u{2a6df}\u{2a700}-\u{2b81d}\u{2b820}-\u{2cead}\u{2ceb0}-\u{2ebe0}\u{2ebf0}-\u{2ee5d}\u{2f800}-\u{2fa1d}\u{30000}-\u{3134a}\u{31350}-\u{33479}])(?:[\u{fe00}-\u{fe0f}\u{e0100}-\u{e01ef}])?/u, _e = new RegExp("(?:[\\u{21}-\\u{2f}\\u{3a}-\\u{40}\\u{5b}-\\u{60}\\u{7b}-\\u{7e}]|\\p{General_Category=Connector_Punctuation}|\\p{General_Category=Dash_Punctuation}|\\p{General_Category=Close_Punctuation}|\\p{General_Category=Final_Punctuation}|\\p{General_Category=Initial_Punctuation}|\\p{General_Category=Other_Punctuation}|\\p{General_Category=Open_Punctuation})", "u");
var qe = (e) => e.position.start.offset, Oe = (e) => e.position.end.offset;
var wt = /* @__PURE__ */ new Set(["liquidNode", "inlineCode", "emphasis", "esComment", "strong", "delete", "wikiLink", "link", "linkReference", "image", "imageReference", "footnote", "footnoteReference", "sentence", "whitespace", "word", "break", "inlineMath"]), Lr = /* @__PURE__ */ new Set([...wt, "tableCell", "paragraph", "heading"]), ze = "non-cjk", ue = "cj-letter", Se = "k-letter", sr = "cjk-punctuation", tf = new RegExp("\\p{Script_Extensions=Hangul}", "u");
function Rr(e) {
  let r = [], t = e.split(/([\t\n ]+)/u);
  for (let [u, i] of t.entries()) {
    if (u % 2 === 1) {
      r.push({ type: "whitespace", value: /\n/u.test(i) ? `
` : " " });
      continue;
    }
    if ((u === 0 || u === t.length - 1) && i === "") continue;
    let a = i.split(new RegExp(`(${bi.source})`, "u"));
    for (let [o, s] of a.entries()) if (!((o === 0 || o === a.length - 1) && s === "")) {
      if (o % 2 === 0) {
        s !== "" && n({ type: "word", value: s, kind: ze, isCJ: false, hasLeadingPunctuation: _e.test(s[0]), hasTrailingPunctuation: _e.test(M(0, s, -1)) });
        continue;
      }
      if (_e.test(s)) {
        n({ type: "word", value: s, kind: sr, isCJ: true, hasLeadingPunctuation: true, hasTrailingPunctuation: true });
        continue;
      }
      if (tf.test(s)) {
        n({ type: "word", value: s, kind: Se, isCJ: false, hasLeadingPunctuation: false, hasTrailingPunctuation: false });
        continue;
      }
      n({ type: "word", value: s, kind: ue, isCJ: true, hasLeadingPunctuation: false, hasTrailingPunctuation: false });
    }
  }
  return r;
  function n(u) {
    let i = M(0, r, -1);
    i?.type === "word" && !a(ze, sr) && ![i.value, u.value].some((o) => /\u3000/u.test(o)) && r.push({ type: "whitespace", value: "" }), r.push(u);
    function a(o, s) {
      return i.kind === o && u.kind === s || i.kind === s && u.kind === o;
    }
  }
}
function Ge(e, r) {
  let t = r.originalText.slice(e.position.start.offset, e.position.end.offset), { numberText: n, leadingSpaces: u } = t.match(/^\s*(?<numberText>\d+)(\.|\))(?<leadingSpaces>\s*)/u).groups;
  return { number: Number(n), leadingSpaces: u };
}
function xi(e, r) {
  return !e.ordered || e.children.length < 2 || Ge(e.children[1], r).number !== 1 ? false : Ge(e.children[0], r).number !== 0 ? true : e.children.length > 2 && Ge(e.children[2], r).number === 1;
}
function Mr(e, r) {
  let { value: t } = e;
  return e.position.end.offset === r.length && t.endsWith(`
`) && r.endsWith(`
`) ? t.slice(0, -1) : t;
}
function xe(e, r) {
  return (function t(n, u, i) {
    let a = { ...r(n, u, i) };
    return a.children && (a.children = a.children.map((o, s) => t(o, s, [a, ...i]))), a;
  })(e, null, []);
}
function Ur(e) {
  if (e?.type !== "link" || e.children.length !== 1) return false;
  let [r] = e.children;
  return qe(e) === qe(r) && Oe(e) === Oe(r);
}
function cr(e) {
  let r;
  if (e.type === "html") r = e.value.match(/^<!--\s*prettier-ignore(?:-(start|end))?\s*-->$/u);
  else {
    let t;
    e.type === "esComment" ? t = e : e.type === "paragraph" && e.children.length === 1 && e.children[0].type === "esComment" && (t = e.children[0]), t && (r = t.value.match(/^prettier-ignore(?:-(start|end))?$/u));
  }
  return r ? r[1] || "next" : false;
}
function Yr(e, r) {
  return t(e, r, (n) => n.ordered === e.ordered);
  function t(n, u, i) {
    let a = -1;
    for (let o of u.children) if (o.type === n.type && i(o) ? a++ : a = -1, o === n) return a;
  }
}
function nf(e, r) {
  let { node: t } = e;
  if (t.type === "code" && t.lang !== null) {
    let n = hi(r, { language: t.lang });
    if (n) return async (u) => {
      let i = r.__inJsTemplate ? "~" : "`", a = i.repeat(Math.max(3, Nr(t.value, i) + 1)), o = { parser: n };
      t.lang === "ts" || t.lang === "typescript" ? o.filepath = "dummy.ts" : t.lang === "tsx" && (o.filepath = "dummy.tsx");
      let s = await u(Mr(t, r.originalText), o);
      return ir([a, t.lang, t.meta ? " " + t.meta : "", R, be(s), R, a]);
    };
  }
  switch (t.type) {
    case "import":
    case "export":
      return (n) => n(t.value, { __onHtmlBindingRoot: (u) => uf(u, t.type), parser: "babel" });
    case "jsx":
      return (n) => n(`<$>${t.value}</$>`, { parser: "__js_expression", rootMarker: "mdx" });
  }
  return null;
}
function uf(e, r) {
  let { program: { body: t } } = e;
  if (!t.every((n) => n.type === "ImportDeclaration" || n.type === "ExportDefaultDeclaration" || n.type === "ExportNamedDeclaration")) throw new Error(`Unexpected '${r}' in MDX.`);
}
var yi = nf;
var lr = null;
function fr(e) {
  if (lr !== null && typeof lr.property) {
    let r = lr;
    return lr = fr.prototype = null, r;
  }
  return lr = fr.prototype = e ?? /* @__PURE__ */ Object.create(null), new fr();
}
var af = 10;
for (let e = 0; e <= af; e++) fr();
function kt(e) {
  return fr(e);
}
function of(e, r = "type") {
  kt(e);
  function t(n) {
    let u = n[r], i = e[u];
    if (!Array.isArray(i)) throw Object.assign(new Error(`Missing visitor keys for '${u}'.`), { node: n });
    return i;
  }
  return t;
}
var wi = of;
var sf = { root: ["children"], paragraph: ["children"], sentence: ["children"], word: [], whitespace: [], emphasis: ["children"], strong: ["children"], delete: ["children"], inlineCode: [], wikiLink: [], link: ["children"], image: [], blockquote: ["children"], heading: ["children"], code: [], html: [], list: ["children"], thematicBreak: [], linkReference: ["children"], imageReference: [], definition: [], footnote: ["children"], footnoteReference: [], footnoteDefinition: ["children"], table: ["children"], tableCell: ["children"], break: [], liquidNode: [], import: [], export: [], esComment: [], jsx: [], math: [], inlineMath: [], tableRow: ["children"], listItem: ["children"], text: [] }, ki = sf;
var cf = wi(ki), Bi = cf;
function G(e, r, t, n = {}) {
  let { processor: u = t } = n, i = [];
  return e.each(() => {
    let a = u(e);
    a !== false && (i.length > 0 && lf(e) && (i.push(R), (Df(e, r) || Ti(e)) && i.push(R), Ti(e) && i.push(R)), i.push(a));
  }, "children"), i;
}
function lf({ node: e, parent: r }) {
  let t = wt.has(e.type), n = e.type === "html" && Lr.has(r.type);
  return !t && !n;
}
var ff = /* @__PURE__ */ new Set(["listItem", "definition"]);
function Df({ node: e, previous: r, parent: t }, n) {
  if (_i(r, n) || e.type === "list" && t.type === "listItem" && r.type === "code") return true;
  let i = r.type === e.type && ff.has(e.type), a = t.type === "listItem" && (e.type === "list" || !_i(t, n)), o = cr(r) === "next", s = e.type === "html" && r.type === "html" && r.position.end.line + 1 === e.position.start.line, f = e.type === "html" && t.type === "listItem" && r.type === "paragraph" && r.position.end.line + 1 === e.position.start.line;
  return !(i || a || o || s || f);
}
function Ti({ node: e, previous: r }) {
  let t = r.type === "list", n = e.type === "code" && e.isIndented;
  return t && n;
}
function _i(e, r) {
  return e.type === "listItem" && (e.spread || r.originalText.charAt(e.position.end.offset - 1) === `
`);
}
function Oi(e, r, t) {
  let { node: n } = e, u = Yr(n, e.parent), i = xi(n, r);
  return G(e, r, t, { processor() {
    let a = s(), { node: o } = e;
    if (o.children.length === 2 && o.children[1].type === "html" && o.children[0].position.start.column !== o.children[1].position.start.column) return [a, qi(e, r, t, a)];
    return [a, me(" ".repeat(a.length), qi(e, r, t, a))];
    function s() {
      let f = n.ordered ? (e.isFirst ? n.start : i ? 1 : n.start + e.index) + (u % 2 === 0 ? ". " : ") ") : u % 2 === 0 ? "- " : "* ";
      return (n.isAligned || n.hasIndentedCodeblock) && n.ordered ? pf(f, r) : f;
    }
  } });
}
function qi(e, r, t, n) {
  let { node: u } = e, i = u.checked === null ? "" : u.checked ? "[x] " : "[ ] ";
  return [i, G(e, r, t, { processor({ node: a, isFirst: o }) {
    if (o && a.type !== "list") return me(" ".repeat(i.length), t());
    let s = " ".repeat(hf(r.tabWidth - n.length, 0, 3));
    return [s, me(s, t())];
  } })];
}
function pf(e, r) {
  let t = n();
  return e + " ".repeat(t >= 4 ? 0 : t);
  function n() {
    let u = e.length % r.tabWidth;
    return u === 0 ? 0 : r.tabWidth - u;
  }
}
function hf(e, r, t) {
  return Math.max(r, Math.min(e, t));
}
function Si(e, r, t) {
  let { node: n } = e, u = [], i = e.map(() => e.map(({ index: l }) => {
    let D = ii(t(), r).formatted, m = ar(D);
    return u[l] = Math.max(u[l] ?? 3, m), { text: D, width: m };
  }, "children"), "children"), a = s(false);
  if (r.proseWrap !== "never") return [Me, a];
  let o = s(true);
  return [Me, Ye(Jn(o, a))];
  function s(l) {
    return Tr(ur, [c(i[0], l), f(l), ...i.slice(1).map((D) => c(D, l))].map((D) => `| ${D.join(" | ")} |`));
  }
  function f(l) {
    return u.map((D, m) => {
      let p = n.align[m], h = p === "center" || p === "left" ? ":" : "-", F = p === "center" || p === "right" ? ":" : "-", g = l ? "-" : "-".repeat(D - 2);
      return `${h}${g}${F}`;
    });
  }
  function c(l, D) {
    return l.map(({ text: m, width: p }, h) => {
      if (D) return m;
      let F = u[h] - p, g = n.align[h], E = 0;
      g === "right" ? E = F : g === "center" && (E = Math.floor(F / 2));
      let v = F - E;
      return `${" ".repeat(E)}${m}${" ".repeat(v)}`;
    });
  }
}
function Ni(e) {
  let { node: r } = e, t = L(0, L(0, r.value, "*", "\\*"), new RegExp([`(^|${_e.source})(_+)`, `(_+)(${_e.source}|$)`].join("|"), "gu"), (i, a, o, s, f) => L(0, o ? `${a}${o}` : `${s}${f}`, "_", "\\_")), n = (i, a, o) => i.type === "sentence" && o === 0, u = (i, a, o) => Ur(i.children[o - 1]);
  return t !== r.value && (e.match(void 0, n, u) || e.match(void 0, n, (i, a, o) => i.type === "emphasis" && o === 0, u)) && (t = t.replace(/^(\\?[*_])+/u, (i) => L(0, i, "\\", ""))), t;
}
function Ii(e, r, t) {
  let n = e.map(t, "children");
  return df(n);
}
function df(e) {
  let r = [""];
  return (function t(n) {
    for (let u of n) {
      let i = z(u);
      if (i === V) {
        t(u);
        continue;
      }
      let a = u, o = [];
      i === X && ([a, ...o] = u.parts), r.push([r.pop(), a], ...o);
    }
  })(e), Ue(r);
}
var Bt = class {
  #e;
  constructor(r) {
    this.#e = new Set(r);
  }
  getLeadingWhitespaceCount(r) {
    let t = this.#e, n = 0;
    for (let u = 0; u < r.length && t.has(r.charAt(u)); u++) n++;
    return n;
  }
  getTrailingWhitespaceCount(r) {
    let t = this.#e, n = 0;
    for (let u = r.length - 1; u >= 0 && t.has(r.charAt(u)); u--) n++;
    return n;
  }
  getLeadingWhitespace(r) {
    let t = this.getLeadingWhitespaceCount(r);
    return r.slice(0, t);
  }
  getTrailingWhitespace(r) {
    let t = this.getTrailingWhitespaceCount(r);
    return r.slice(r.length - t);
  }
  hasLeadingWhitespace(r) {
    return this.#e.has(r.charAt(0));
  }
  hasTrailingWhitespace(r) {
    return this.#e.has(M(0, r, -1));
  }
  trimStart(r) {
    let t = this.getLeadingWhitespaceCount(r);
    return r.slice(t);
  }
  trimEnd(r) {
    let t = this.getTrailingWhitespaceCount(r);
    return r.slice(0, r.length - t);
  }
  trim(r) {
    return this.trimEnd(this.trimStart(r));
  }
  split(r, t = false) {
    let n = `[${le([...this.#e].join(""))}]+`, u = new RegExp(t ? `(${n})` : n, "u");
    return r.split(u);
  }
  hasWhitespaceCharacter(r) {
    let t = this.#e;
    return Array.prototype.some.call(r, (n) => t.has(n));
  }
  hasNonWhitespaceCharacter(r) {
    let t = this.#e;
    return Array.prototype.some.call(r, (n) => !t.has(n));
  }
  isWhitespaceOnly(r) {
    let t = this.#e;
    return Array.prototype.every.call(r, (n) => t.has(n));
  }
  #r(r) {
    let t = Number.POSITIVE_INFINITY;
    for (let n of r.split(`
`)) {
      if (n.length === 0) continue;
      let u = this.getLeadingWhitespaceCount(n);
      if (u === 0) return 0;
      n.length !== u && u < t && (t = u);
    }
    return t === Number.POSITIVE_INFINITY ? 0 : t;
  }
  dedentString(r) {
    let t = this.#r(r);
    return t === 0 ? r : r.split(`
`).map((n) => n.slice(t)).join(`
`);
  }
}, Pi = Bt;
var mf = ["	", `
`, "\f", "\r", " "], Ff = new Pi(mf), Tt = Ff;
var gf = /^\\?.$/su, Ef = /^\n *>[ >]*$/u;
function Cf(e, r) {
  return e = vf(e, r), e = bf(e), e = yf(e, r), e = wf(e, r), e = xf(e), e;
}
function vf(e, r) {
  return xe(e, (t) => {
    if (t.type !== "text") return t;
    let { value: n } = t;
    if (n === "*" || n === "_" || !gf.test(n) || t.position.end.offset - t.position.start.offset === n.length) return t;
    let u = r.originalText.slice(t.position.start.offset, t.position.end.offset);
    return Ef.test(u) ? t : { ...t, value: u };
  });
}
function Af(e, r, t) {
  return xe(e, (n) => {
    if (!n.children) return n;
    let u = [], i, a;
    for (let o of n.children) i && r(i, o) ? (o = t(i, o), u.splice(-1, 1, o), a || (a = true)) : u.push(o), i = o;
    return a ? { ...n, children: u } : n;
  });
}
function bf(e) {
  return Af(e, (r, t) => r.type === "text" && t.type === "text", (r, t) => ({ type: "text", value: r.value + t.value, position: { start: r.position.start, end: t.position.end } }));
}
function xf(e) {
  return xe(e, (r, t, [n]) => {
    if (r.type !== "text") return r;
    let { value: u } = r;
    return n.type === "paragraph" && (t === 0 && (u = Tt.trimStart(u)), t === n.children.length - 1 && (u = Tt.trimEnd(u))), { type: "sentence", position: r.position, children: Rr(u) };
  });
}
function yf(e, r) {
  return xe(e, (t, n, u) => {
    if (t.type === "code") {
      let i = /^\n?(?: {4,}|\t)/u.test(r.originalText.slice(t.position.start.offset, t.position.end.offset));
      if (t.isIndented = i, i) for (let a = 0; a < u.length; a++) {
        let o = u[a];
        if (o.hasIndentedCodeblock) break;
        o.type === "list" && (o.hasIndentedCodeblock = true);
      }
    }
    return t;
  });
}
function wf(e, r) {
  return xe(e, (u, i, a) => {
    if (u.type === "list" && u.children.length > 0) {
      for (let o = 0; o < a.length; o++) {
        let s = a[o];
        if (s.type === "list" && !s.isAligned) return u.isAligned = false, u;
      }
      u.isAligned = n(u);
    }
    return u;
  });
  function t(u) {
    return u.children.length === 0 ? -1 : u.children[0].position.start.column - 1;
  }
  function n(u) {
    if (!u.ordered) return true;
    let [i, a] = u.children;
    if (Ge(i, r).leadingSpaces.length > 1) return true;
    let s = t(i);
    if (s === -1) return false;
    if (u.children.length === 1) return s % r.tabWidth === 0;
    let f = t(a);
    return s !== f ? false : s % r.tabWidth === 0 ? true : Ge(a, r).leadingSpaces.length > 1;
  }
}
var Li = Cf;
function Ri(e, r) {
  let t = [""];
  return e.each(() => {
    let { node: n } = e, u = r();
    switch (n.type) {
      case "whitespace":
        if (z(u) !== W) {
          t.push(u, "");
          break;
        }
      default:
        t.push([t.pop(), u]);
    }
  }, "children"), Ue(t);
}
var kf = /* @__PURE__ */ new Set(["heading", "tableCell", "link", "wikiLink"]), Mi = new Set("!\"#$%&'()*+,-./:;<=>?@[\\]^_`{|}~");
function Bf({ parent: e }) {
  if (e.usesCJSpaces === void 0) {
    let r = { " ": 0, "": 0 }, { children: t } = e;
    for (let n = 1; n < t.length - 1; ++n) {
      let u = t[n];
      if (u.type === "whitespace" && (u.value === " " || u.value === "")) {
        let i = t[n - 1].kind, a = t[n + 1].kind;
        (i === ue && a === ze || i === ze && a === ue) && ++r[u.value];
      }
    }
    e.usesCJSpaces = r[" "] > r[""];
  }
  return e.usesCJSpaces;
}
function Tf(e, r) {
  if (r) return true;
  let { previous: t, next: n } = e;
  if (!t || !n) return true;
  let u = t.kind, i = n.kind;
  return Ui(u) && Ui(i) || u === Se && i === ue || i === Se && u === ue ? true : u === sr || i === sr || u === ue && i === ue ? false : Mi.has(n.value[0]) || Mi.has(M(0, t.value, -1)) ? true : t.hasTrailingPunctuation || n.hasLeadingPunctuation ? false : Bf(e);
}
function Ui(e) {
  return e === ze || e === Se;
}
function _f(e, r, t, n) {
  if (t !== "always" || e.hasAncestor((a) => kf.has(a.type))) return false;
  if (n) return r !== "";
  let { previous: u, next: i } = e;
  return !u || !i ? true : r === "" ? false : u.kind === Se && i.kind === ue || i.kind === Se && u.kind === ue ? true : !(u.isCJ || i.isCJ);
}
function _t(e, r, t, n) {
  if (t === "preserve" && r === `
`) return R;
  let u = r === " " || r === `
` && Tf(e, n);
  return _f(e, r, t, n) ? u ? qr : Or : u ? " " : "";
}
function Yi(e) {
  let { previous: r, next: t } = e;
  return r?.type === "sentence" && M(0, r.children, -1)?.type === "word" && !M(0, r.children, -1).hasTrailingPunctuation || t?.type === "sentence" && t.children[0]?.type === "word" && !t.children[0].hasLeadingPunctuation;
}
function qf(e, r, t) {
  let { node: n } = e;
  if (Sf(e)) {
    let u = [""], i = Rr(r.originalText.slice(n.position.start.offset, n.position.end.offset));
    for (let a of i) {
      if (a.type === "word") {
        u.push([u.pop(), a.value]);
        continue;
      }
      let o = _t(e, a.value, r.proseWrap, true);
      if (z(o) === W) {
        u.push([u.pop(), o]);
        continue;
      }
      u.push(o, "");
    }
    return Ue(u);
  }
  switch (n.type) {
    case "root":
      return n.children.length === 0 ? "" : [Of(e, r, t), R];
    case "paragraph":
      return Ii(e, r, t);
    case "sentence":
      return Ri(e, t);
    case "word":
      return Ni(e);
    case "whitespace": {
      let { next: u } = e, i = u && /^>|^(?:[*+-]|#{1,6}|\d+[).])$/u.test(u.value) ? "never" : r.proseWrap;
      return _t(e, n.value, i);
    }
    case "emphasis": {
      let u;
      if (Ur(n.children[0])) u = r.originalText[n.position.start.offset];
      else {
        let i = Yi(e), a = e.callParent(({ node: o }) => o.type === "strong" && Yi(e));
        u = i || a || e.hasAncestor((o) => o.type === "emphasis") ? "*" : "_";
      }
      return [u, G(e, r, t), u];
    }
    case "strong":
      return ["**", G(e, r, t), "**"];
    case "delete":
      return ["~~", G(e, r, t), "~~"];
    case "inlineCode": {
      let u = r.proseWrap === "preserve" ? n.value : L(0, n.value, `
`, " "), i = ui(u, "`"), a = "`".repeat(i), o = u.startsWith("`") || u.endsWith("`") || /^[\n ]/u.test(u) && /[\n ]$/u.test(u) && /[^\n ]/u.test(u) ? " " : "";
      return [a, o, u, o, a];
    }
    case "wikiLink": {
      let u = "";
      return r.proseWrap === "preserve" ? u = n.value : u = L(0, n.value, /[\t\n]+/gu, " "), ["[[", u, "]]"];
    }
    case "link":
      switch (r.originalText[n.position.start.offset]) {
        case "<": {
          let u = "mailto:";
          return ["<", n.url.startsWith(u) && r.originalText.slice(n.position.start.offset + 1, n.position.start.offset + 1 + u.length) !== u ? n.url.slice(u.length) : n.url, ">"];
        }
        case "[":
          return ["[", G(e, r, t), "](", qt(n.url, ")"), Gr(n.title, r), ")"];
        default:
          return r.originalText.slice(n.position.start.offset, n.position.end.offset);
      }
    case "image":
      return ["![", n.alt || "", "](", qt(n.url, ")"), Gr(n.title, r), ")"];
    case "blockquote":
      return ["> ", me("> ", G(e, r, t))];
    case "heading":
      return ["#".repeat(n.depth) + " ", G(e, r, t)];
    case "code": {
      if (n.isIndented) {
        let a = " ".repeat(4);
        return me(a, [a, be(n.value, R)]);
      }
      let u = r.__inJsTemplate ? "~" : "`", i = u.repeat(Math.max(3, Nr(n.value, u) + 1));
      return [i, n.lang || "", n.meta ? " " + n.meta : "", R, be(Mr(n, r.originalText), R), R, i];
    }
    case "html": {
      let { parent: u, isLast: i } = e, a = u.type === "root" && i ? n.value.trimEnd() : n.value, o = /^<!--.*-->$/su.test(a);
      return be(a, o ? R : ir(tr));
    }
    case "list":
      return Oi(e, r, t);
    case "thematicBreak": {
      let { ancestors: u } = e, i = u.findIndex((o) => o.type === "list");
      return i === -1 ? "---" : Yr(u[i], u[i + 1]) % 2 === 0 ? "***" : "---";
    }
    case "linkReference":
      return ["[", G(e, r, t), "]", n.referenceType === "full" ? Ot(n) : n.referenceType === "collapsed" ? "[]" : ""];
    case "imageReference":
      switch (n.referenceType) {
        case "full":
          return ["![", n.alt || "", "]", Ot(n)];
        default:
          return ["![", n.alt, "]", n.referenceType === "collapsed" ? "[]" : ""];
      }
    case "definition": {
      let u = r.proseWrap === "always" ? qr : " ";
      return Ye([Ot(n), ":", nr([u, qt(n.url), n.title === null ? "" : [u, Gr(n.title, r, false)]])]);
    }
    case "footnote":
      return ["[^", G(e, r, t), "]"];
    case "footnoteReference":
      return zi(n);
    case "footnoteDefinition": {
      let u = n.children.length === 1 && n.children[0].type === "paragraph" && (r.proseWrap === "never" || r.proseWrap === "preserve" && n.children[0].position.start.line === n.children[0].position.end.line);
      return [zi(n), ": ", u ? G(e, r, t) : Ye([me(" ".repeat(4), G(e, r, t, { processor: ({ isFirst: i }) => i ? Ye([Or, t()]) : t() }))])];
    }
    case "table":
      return Si(e, r, t);
    case "tableCell":
      return G(e, r, t);
    case "break":
      return /\s/u.test(r.originalText[n.position.start.offset]) ? ["  ", ir(tr)] : ["\\", R];
    case "liquidNode":
      return be(n.value, R);
    case "import":
    case "export":
    case "jsx":
      return n.value.trimEnd();
    case "esComment":
      return ["{/* ", n.value, " */}"];
    case "math":
      return ["$$", R, n.value ? [be(n.value, R), R] : "", "$$"];
    case "inlineMath":
      return r.originalText.slice(qe(n), Oe(n));
    case "frontMatter":
    case "tableRow":
    case "listItem":
    case "text":
    default:
      throw new oi(n, "Markdown");
  }
}
function Of(e, r, t) {
  let n = [], u = null, { children: i } = e.node;
  for (let [a, o] of i.entries()) switch (cr(o)) {
    case "start":
      u === null && (u = { index: a, offset: o.position.end.offset });
      break;
    case "end":
      u !== null && (n.push({ start: u, end: { index: a, offset: o.position.start.offset } }), u = null);
      break;
  }
  return G(e, r, t, { processor({ index: a }) {
    if (n.length > 0) {
      let o = n[0];
      if (a === o.start.index) return [Gi(i[o.start.index]), r.originalText.slice(o.start.offset, o.end.offset), Gi(i[o.end.index])];
      if (o.start.index < a && a < o.end.index) return false;
      if (a === o.end.index) return n.shift(), false;
    }
    return t();
  } });
}
function Gi(e) {
  if (e.type === "html") return e.value;
  if (e.type === "paragraph" && Array.isArray(e.children) && e.children.length === 1 && e.children[0].type === "esComment") return ["{/* ", e.children[0].value, " */}"];
}
function Sf(e) {
  let r = e.findAncestor((t) => t.type === "linkReference" || t.type === "imageReference");
  return r && (r.type !== "linkReference" || r.referenceType !== "full");
}
var Nf = (e, r) => {
  for (let t of r) e = L(0, e, t, encodeURIComponent(t));
  return e;
};
function qt(e, r = []) {
  let t = [" ", ...Array.isArray(r) ? r : [r]];
  return new RegExp(t.map((n) => le(n)).join("|"), "u").test(e) ? `<${Nf(e, "<>")}>` : e;
}
function Gr(e, r, t = true) {
  if (!e) return "";
  if (t) return " " + Gr(e, r, false);
  if (e = L(0, e, /\\(?=["')])/gu, ""), e.includes('"') && e.includes("'") && !e.includes(")")) return `(${e})`;
  let n = ai(e, r.singleQuote);
  return e = L(0, e, "\\", "\\\\"), e = L(0, e, n, `\\${n}`), `${n}${e}${n}`;
}
function If(e) {
  return e.index > 0 && cr(e.previous) === "next";
}
function Ot(e) {
  return `[${(0, Wi.default)(e.label)}]`;
}
function zi(e) {
  return `[^${e.label}]`;
}
var Pf = { features: { experimental_frontMatterSupport: { massageAstNode: true, embed: true, print: true } }, preprocess: Li, print: qf, embed: yi, massageAstNode: Ai, hasPrettierIgnore: If, insertPragma: Ei, getVisitorKeys: Bi }, Vi = Pf;
var $i = [{ name: "Markdown", type: "prose", aceMode: "markdown", extensions: [".md", ".livemd", ".markdown", ".mdown", ".mdwn", ".mkd", ".mkdn", ".mkdown", ".ronn", ".scd", ".workbook"], filenames: ["contents.lr", "README"], tmScope: "text.md", aliases: ["md", "pandoc"], codemirrorMode: "gfm", codemirrorMimeType: "text/x-gfm", wrap: true, parsers: ["markdown"], vscodeLanguageIds: ["markdown"], linguistLanguageId: 222 }, { name: "MDX", type: "prose", aceMode: "markdown", extensions: [".mdx"], filenames: [], tmScope: "text.md", aliases: ["md", "pandoc"], codemirrorMode: "gfm", codemirrorMimeType: "text/x-gfm", wrap: true, parsers: ["mdx"], vscodeLanguageIds: ["mdx"], linguistLanguageId: 222 }];
var St = { singleQuote: { category: "Common", type: "boolean", default: false, description: "Use single quotes instead of double quotes." }, proseWrap: { category: "Common", type: "choice", default: "preserve", description: "How to wrap prose.", choices: [{ value: "always", description: "Wrap prose if it exceeds the print width." }, { value: "never", description: "Do not wrap prose." }, { value: "preserve", description: "Wrap prose as-is." }] } };
var Lf = { proseWrap: St.proseWrap, singleQuote: St.singleQuote }, ji = Lf;
var Gn = {};
zn(Gn, { markdown: () => Zm, mdx: () => eF, remark: () => Zm });
var cl = Le(Ki()), ll = Le(cu()), fl = Le(ic()), Dl = Le(Jc());
var Vm = /^import\s/u, $m = /^export\s/u, Qc = "[a-z][a-z0-9]*(\\.[a-z][a-z0-9]*)*|", Zc = /<!---->|<!---?[^>-](?:-?[^-])*-->/u, jm = /^\{\s*\/\*(.*)\*\/\s*\}/u;
var Hm = (e) => Vm.test(e), el = (e) => $m.test(e), rl = (e) => Hm(e) || el(e), Yn = (e, r) => {
  let t = r.indexOf(`

`), n = t === -1 ? r : r.slice(0, t);
  if (rl(n)) return e(n)({ type: el(n) ? "export" : "import", value: n });
};
Yn.notInBlock = true;
Yn.locator = (e) => rl(e) ? -1 : 1;
var tl = (e, r) => {
  let t = jm.exec(r);
  if (t) return e(t[0])({ type: "esComment", value: t[1].trim() });
};
tl.locator = (e, r) => e.indexOf("{", r);
var nl = function() {
  let { Parser: e } = this, { blockTokenizers: r, blockMethods: t, inlineTokenizers: n, inlineMethods: u } = e.prototype;
  r.esSyntax = Yn, n.esComment = tl, t.splice(t.indexOf("paragraph"), 0, "esSyntax"), u.splice(u.indexOf("text"), 0, "esComment");
};
var Km = function() {
  let e = this.Parser.prototype;
  e.blockMethods = ["frontMatter", ...e.blockMethods], e.blockTokenizers.frontMatter = r;
  function r(t, n) {
    let { frontMatter: u } = Te(n);
    if (u) return t(u.raw)({ ...u, type: "frontMatter" });
  }
  r.onlyAtStart = true;
}, il = Km;
function Xm() {
  return (e) => xe(e, (r, t, [n]) => r.type !== "html" || Zc.test(r.value) || Lr.has(n.type) ? r : { ...r, type: "jsx" });
}
var ul = Xm;
var Jm = function() {
  let e = this.Parser.prototype, r = e.inlineMethods;
  r.splice(r.indexOf("text"), 0, "liquid"), e.inlineTokenizers.liquid = t;
  function t(n, u) {
    let i = u.match(/^(\{%.*?%\}|\{\{.*?\}\})/su);
    if (i) return n(i[0])({ type: "liquidNode", value: i[0] });
  }
  t.locator = function(n, u) {
    return n.indexOf("{", u);
  };
}, al = Jm;
var Qm = function() {
  let e = "wikiLink", r = /^\[\[(?<linkContents>.+?)\]\]/su, t = this.Parser.prototype, n = t.inlineMethods;
  n.splice(n.indexOf("link"), 0, e), t.inlineTokenizers.wikiLink = u;
  function u(i, a) {
    let o = r.exec(a);
    if (o) {
      let s = o.groups.linkContents.trim();
      return i(o[0])({ type: e, value: s });
    }
  }
  u.locator = function(i, a) {
    return i.indexOf("[", a);
  };
}, ol = Qm;
function pl({ isMDX: e }) {
  return (r) => {
    let t = (0, Dl.default)().use(fl.default, { commonmark: true, ...e && { blocks: [Qc] } }).use(cl.default).use(il).use(ll.default).use(e ? nl : sl).use(al).use(e ? ul : sl).use(ol);
    return t.run(t.parse(r));
  };
}
function sl() {
}
var hl = { astFormat: "mdast", hasPragma: Pr, hasIgnorePragma: gi, locStart: qe, locEnd: Oe }, Zm = { ...hl, parse: pl({ isMDX: false }) }, eF = { ...hl, parse: pl({ isMDX: true }) };
var rF = { mdast: Vi };
export {
  dl as default,
  $i as languages,
  ji as options,
  Gn as parsers,
  rF as printers
};
