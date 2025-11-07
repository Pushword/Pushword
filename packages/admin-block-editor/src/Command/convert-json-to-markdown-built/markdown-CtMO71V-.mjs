#!/usr/bin/env node
var yl = Object.create;
var ht = Object.defineProperty;
var xl = Object.getOwnPropertyDescriptor;
var wl = Object.getOwnPropertyNames;
var kl = Object.getPrototypeOf, Bl = Object.prototype.hasOwnProperty;
var Yn = (e) => {
  throw TypeError(e);
};
var C = (e, r) => () => (r || e((r = { exports: {} }).exports, r), r.exports), $n = (e, r) => {
  for (var t in r) ht(e, t, { get: r[t], enumerable: true });
}, Tl = (e, r, t, n) => {
  if (r && typeof r == "object" || typeof r == "function") for (let a of wl(r)) !Bl.call(e, a) && a !== t && ht(e, a, { get: () => r[a], enumerable: !(n = xl(r, a)) || n.enumerable });
  return e;
};
var Me = (e, r, t) => (t = e != null ? yl(kl(e)) : {}, Tl(ht(t, "default", { value: e, enumerable: true }), e));
var Vn = (e, r, t) => r.has(e) || Yn("Cannot " + t);
var ce = (e, r, t) => (Vn(e, r, "read from private field"), r.get(e)), jn = (e, r, t) => r.has(e) ? Yn("Cannot add the same private member more than once") : r instanceof WeakSet ? r.add(e) : r.set(e, t), Wn = (e, r, t, n) => (Vn(e, r, "write to private field"), r.set(e, t), t);
var kr = C((sF, Hn) => {
  Hn.exports = Sl;
  function Sl(e) {
    return String(e).replace(/\s+/g, " ");
  }
});
var iu = C((sv, nu) => {
  nu.exports = Vf;
  var Dr = 9, zr = 10, je = 32, zf = 33, Gf = 58, We = 91, Yf = 92, Pt = 93, pr = 94, Gr = 96, Yr = 4, $f = 1024;
  function Vf(e) {
    var r = this.Parser, t = this.Compiler;
    jf(r) && Hf(r, e), Wf(t) && Kf(t);
  }
  function jf(e) {
    return !!(e && e.prototype && e.prototype.blockTokenizers);
  }
  function Wf(e) {
    return !!(e && e.prototype && e.prototype.visitors);
  }
  function Hf(e, r) {
    for (var t = r || {}, n = e.prototype, a = n.blockTokenizers, i = n.inlineTokenizers, u = n.blockMethods, o = n.inlineMethods, s = a.definition, l = i.reference, c = [], f = -1, p = u.length, d; ++f < p; ) d = u[f], !(d === "newline" || d === "indentedCode" || d === "paragraph" || d === "footnoteDefinition") && c.push([d]);
    c.push(["footnoteDefinition"]), t.inlineNotes && (Ot(o, "reference", "inlineNote"), i.inlineNote = m), Ot(u, "definition", "footnoteDefinition"), Ot(o, "reference", "footnoteCall"), a.definition = A, a.footnoteDefinition = D, i.footnoteCall = h, i.reference = F, n.interruptFootnoteDefinition = c, F.locator = l.locator, h.locator = v, m.locator = B;
    function D(b, g, y) {
      for (var x = this, E = x.interruptFootnoteDefinition, w = x.offset, k = g.length + 1, T = 0, q = [], N, P, S, _, O, Be, W, I, ee, Z, Ee, ve, U; T < k && (_ = g.charCodeAt(T), !(_ !== Dr && _ !== je)); ) T++;
      if (g.charCodeAt(T++) === We && g.charCodeAt(T++) === pr) {
        for (P = T; T < k; ) {
          if (_ = g.charCodeAt(T), _ !== _ || _ === zr || _ === Dr || _ === je) return;
          if (_ === Pt) {
            S = T, T++;
            break;
          }
          T++;
        }
        if (!(S === void 0 || P === S || g.charCodeAt(T++) !== Gf)) {
          if (y) return true;
          for (N = g.slice(P, S), O = b.now(), ee = 0, Z = 0, Ee = T, ve = []; T < k; ) {
            if (_ = g.charCodeAt(T), _ !== _ || _ === zr) U = { start: ee, contentStart: Ee || T, contentEnd: T, end: T }, ve.push(U), _ === zr && (ee = T + 1, Z = 0, Ee = void 0, U.end = ee);
            else if (Z !== void 0) if (_ === je || _ === Dr) Z += _ === je ? 1 : Yr - Z % Yr, Z > Yr && (Z = void 0, Ee = T);
            else {
              if (Z < Yr && U && (U.contentStart === U.contentEnd || Xf(E, a, x, [b, g.slice(T, $f), true]))) break;
              Z = void 0, Ee = T;
            }
            T++;
          }
          for (T = -1, k = ve.length; k > 0 && (U = ve[k - 1], U.contentStart === U.contentEnd); ) k--;
          for (Be = b(g.slice(0, U.contentEnd)); ++T < k; ) U = ve[T], w[O.line + T] = (w[O.line + T] || 0) + (U.contentStart - U.start), q.push(g.slice(U.contentStart, U.end));
          return W = x.enterBlock(), I = x.tokenizeBlock(q.join(""), O), W(), Be({ type: "footnoteDefinition", identifier: N.toLowerCase(), label: N, children: I });
        }
      }
    }
    function h(b, g, y) {
      var x = g.length + 1, E = 0, w, k, T, q;
      if (g.charCodeAt(E++) === We && g.charCodeAt(E++) === pr) {
        for (k = E; E < x; ) {
          if (q = g.charCodeAt(E), q !== q || q === zr || q === Dr || q === je) return;
          if (q === Pt) {
            T = E, E++;
            break;
          }
          E++;
        }
        if (!(T === void 0 || k === T)) return y ? true : (w = g.slice(k, T), b(g.slice(0, E))({ type: "footnoteReference", identifier: w.toLowerCase(), label: w }));
      }
    }
    function m(b, g, y) {
      var x = this, E = g.length + 1, w = 0, k = 0, T, q, N, P, S, _, O;
      if (g.charCodeAt(w++) === pr && g.charCodeAt(w++) === We) {
        for (N = w; w < E; ) {
          if (q = g.charCodeAt(w), q !== q) return;
          if (_ === void 0) if (q === Yf) w += 2;
          else if (q === We) k++, w++;
          else if (q === Pt) if (k === 0) {
            P = w, w++;
            break;
          } else k--, w++;
          else if (q === Gr) {
            for (S = w, _ = 1; g.charCodeAt(S + _) === Gr; ) _++;
            w += _;
          } else w++;
          else if (q === Gr) {
            for (S = w, O = 1; g.charCodeAt(S + O) === Gr; ) O++;
            w += O, _ === O && (_ = void 0), O = void 0;
          } else w++;
        }
        if (P !== void 0) return y ? true : (T = b.now(), T.column += 2, T.offset += 2, b(g.slice(0, w))({ type: "footnote", children: x.tokenizeInline(g.slice(N, P), T) }));
      }
    }
    function F(b, g, y) {
      var x = 0;
      if (g.charCodeAt(x) === zf && x++, g.charCodeAt(x) === We && g.charCodeAt(x + 1) !== pr) return l.call(this, b, g, y);
    }
    function A(b, g, y) {
      for (var x = 0, E = g.charCodeAt(x); E === je || E === Dr; ) E = g.charCodeAt(++x);
      if (E === We && g.charCodeAt(x + 1) !== pr) return s.call(this, b, g, y);
    }
    function v(b, g) {
      return b.indexOf("[", g);
    }
    function B(b, g) {
      return b.indexOf("^[", g);
    }
  }
  function Kf(e) {
    var r = e.prototype.visitors, t = "    ";
    r.footnote = n, r.footnoteReference = a, r.footnoteDefinition = i;
    function n(u) {
      return "^[" + this.all(u).join("") + "]";
    }
    function a(u) {
      return "[^" + (u.label || u.identifier) + "]";
    }
    function i(u) {
      for (var o = this.all(u).join(`

`).split(`
`), s = 0, l = o.length, c; ++s < l; ) c = o[s], c !== "" && (o[s] = t + c);
      return "[^" + (u.label || u.identifier) + "]: " + o.join(`
`);
    }
  }
  function Ot(e, r, t) {
    e.splice(e.indexOf(r), 0, t);
  }
  function Xf(e, r, t, n) {
    for (var a = e.length, i = -1; ++i < a; ) if (r[e[i][0]].apply(t, n)) return true;
    return false;
  }
});
var It = C((Lt) => {
  Lt.isRemarkParser = Jf;
  Lt.isRemarkCompiler = Qf;
  function Jf(e) {
    return !!(e && e.prototype && e.prototype.blockTokenizers);
  }
  function Qf(e) {
    return !!(e && e.prototype && e.prototype.visitors);
  }
});
var fu = C((lv, lu) => {
  var uu = It();
  lu.exports = tD;
  var au = 9, ou = 32, $r = 36, Zf = 48, eD = 57, su = 92, rD = ["math", "math-inline"], cu = "math-display";
  function tD(e) {
    let r = this.Parser, t = this.Compiler;
    uu.isRemarkParser(r) && nD(r, e), uu.isRemarkCompiler(t) && iD(t);
  }
  function nD(e, r) {
    let t = e.prototype, n = t.inlineMethods;
    i.locator = a, t.inlineTokenizers.math = i, n.splice(n.indexOf("text"), 0, "math");
    function a(u, o) {
      return u.indexOf("$", o);
    }
    function i(u, o, s) {
      let l = o.length, c = false, f = false, p = 0, d, D, h, m, F, A, v;
      if (o.charCodeAt(p) === su && (f = true, p++), o.charCodeAt(p) === $r) {
        if (p++, f) return s ? true : u(o.slice(0, p))({ type: "text", value: "$" });
        if (o.charCodeAt(p) === $r && (c = true, p++), h = o.charCodeAt(p), !(h === ou || h === au)) {
          for (m = p; p < l; ) {
            if (D = h, h = o.charCodeAt(p + 1), D === $r) {
              if (d = o.charCodeAt(p - 1), d !== ou && d !== au && (h !== h || h < Zf || h > eD) && (!c || h === $r)) {
                F = p - 1, p++, c && p++, A = p;
                break;
              }
            } else D === su && (p++, h = o.charCodeAt(p + 1));
            p++;
          }
          if (A !== void 0) return s ? true : (v = o.slice(m, F + 1), u(o.slice(0, A))({ type: "inlineMath", value: v, data: { hName: "span", hProperties: { className: rD.concat(c && r.inlineMathDouble ? [cu] : []) }, hChildren: [{ type: "text", value: v }] } }));
        }
      }
    }
  }
  function iD(e) {
    let r = e.prototype;
    r.visitors.inlineMath = t;
    function t(n) {
      let a = "$";
      return (n.data && n.data.hProperties && n.data.hProperties.className || []).includes(cu) && (a = "$$"), a + n.value + a;
    }
  }
});
var mu = C((fv, du) => {
  var Du = It();
  du.exports = sD;
  var pu = 10, hr = 32, Rt = 36, hu = `
`, uD = "$", aD = 2, oD = ["math", "math-display"];
  function sD() {
    let e = this.Parser, r = this.Compiler;
    Du.isRemarkParser(e) && cD(e), Du.isRemarkCompiler(r) && lD(r);
  }
  function cD(e) {
    let r = e.prototype, t = r.blockMethods, n = r.interruptParagraph, a = r.interruptList, i = r.interruptBlockquote;
    r.blockTokenizers.math = u, t.splice(t.indexOf("fencedCode") + 1, 0, "math"), n.splice(n.indexOf("fencedCode") + 1, 0, ["math"]), a.splice(a.indexOf("fencedCode") + 1, 0, ["math"]), i.splice(i.indexOf("fencedCode") + 1, 0, ["math"]);
    function u(o, s, l) {
      var c = s.length, f = 0;
      let p, d, D, h, m, F, A, v, B, b, g;
      for (; f < c && s.charCodeAt(f) === hr; ) f++;
      for (m = f; f < c && s.charCodeAt(f) === Rt; ) f++;
      if (F = f - m, !(F < aD)) {
        for (; f < c && s.charCodeAt(f) === hr; ) f++;
        for (A = f; f < c; ) {
          if (p = s.charCodeAt(f), p === Rt) return;
          if (p === pu) break;
          f++;
        }
        if (s.charCodeAt(f) === pu) {
          if (l) return true;
          for (d = [], A !== f && d.push(s.slice(A, f)), f++, D = s.indexOf(hu, f + 1), D = D === -1 ? c : D; f < c; ) {
            for (v = false, b = f, g = D, h = D, B = 0; h > b && s.charCodeAt(h - 1) === hr; ) h--;
            for (; h > b && s.charCodeAt(h - 1) === Rt; ) B++, h--;
            for (F <= B && s.indexOf(uD, b) === h && (v = true, g = h); b <= g && b - f < m && s.charCodeAt(b) === hr; ) b++;
            if (v) for (; g > b && s.charCodeAt(g - 1) === hr; ) g--;
            if ((!v || b !== g) && d.push(s.slice(b, g)), v) break;
            f = D + 1, D = s.indexOf(hu, f + 1), D = D === -1 ? c : D;
          }
          return d = d.join(`
`), o(s.slice(0, D))({ type: "math", value: d, data: { hName: "div", hProperties: { className: oD.concat() }, hChildren: [{ type: "text", value: d }] } });
        }
      }
    }
  }
  function lD(e) {
    let r = e.prototype;
    r.visitors.math = t;
    function t(n) {
      return `$$
` + n.value + `
$$`;
    }
  }
});
var gu = C((Dv, Fu) => {
  var fD = fu(), DD = mu();
  Fu.exports = pD;
  function pD(e) {
    var r = e || {};
    DD.call(this, r), fD.call(this, r);
  }
});
var Ie = C((pv, Eu) => {
  Eu.exports = dD;
  var hD = Object.prototype.hasOwnProperty;
  function dD() {
    for (var e = {}, r = 0; r < arguments.length; r++) {
      var t = arguments[r];
      for (var n in t) hD.call(t, n) && (e[n] = t[n]);
    }
    return e;
  }
});
var vu = C((hv, Nt) => {
  typeof Object.create == "function" ? Nt.exports = function(r, t) {
    t && (r.super_ = t, r.prototype = Object.create(t.prototype, { constructor: { value: r, enumerable: false, writable: true, configurable: true } }));
  } : Nt.exports = function(r, t) {
    if (t) {
      r.super_ = t;
      var n = function() {
      };
      n.prototype = t.prototype, r.prototype = new n(), r.prototype.constructor = r;
    }
  };
});
var Au = C((dv, bu) => {
  var mD = Ie(), Cu = vu();
  bu.exports = FD;
  function FD(e) {
    var r, t, n;
    Cu(i, e), Cu(a, i), r = i.prototype;
    for (t in r) n = r[t], n && typeof n == "object" && (r[t] = "concat" in n ? n.concat() : mD(n));
    return i;
    function a(u) {
      return e.apply(this, u);
    }
    function i() {
      return this instanceof i ? e.apply(this, arguments) : new a(arguments);
    }
  }
});
var xu = C((mv, yu) => {
  yu.exports = gD;
  function gD(e, r, t) {
    return n;
    function n() {
      var a = t || this, i = a[e];
      return a[e] = !r, u;
      function u() {
        a[e] = i;
      }
    }
  }
});
var ku = C((Fv, wu) => {
  wu.exports = ED;
  function ED(e) {
    for (var r = String(e), t = [], n = /\r?\n|\r/g; n.exec(r); ) t.push(n.lastIndex);
    return t.push(r.length + 1), { toPoint: a, toPosition: a, toOffset: i };
    function a(u) {
      var o = -1;
      if (u > -1 && u < t[t.length - 1]) {
        for (; ++o < t.length; ) if (t[o] > u) return { line: o + 1, column: u - (t[o - 1] || 0) + 1, offset: u };
      }
      return {};
    }
    function i(u) {
      var o = u && u.line, s = u && u.column, l;
      return !isNaN(o) && !isNaN(s) && o - 1 in t && (l = (t[o - 2] || 0) + s - 1 || 0), l > -1 && l < t[t.length - 1] ? l : -1;
    }
  }
});
var Tu = C((gv, Bu) => {
  Bu.exports = vD;
  var Mt = "\\";
  function vD(e, r) {
    return t;
    function t(n) {
      for (var a = 0, i = n.indexOf(Mt), u = e[r], o = [], s; i !== -1; ) o.push(n.slice(a, i)), a = i + 1, s = n.charAt(a), (!s || u.indexOf(s) === -1) && o.push(Mt), i = n.indexOf(Mt, a + 1);
      return o.push(n.slice(a)), o.join("");
    }
  }
});
var qu = C((Ev, CD) => {
  CD.exports = { AElig: "Æ", AMP: "&", Aacute: "Á", Acirc: "Â", Agrave: "À", Aring: "Å", Atilde: "Ã", Auml: "Ä", COPY: "©", Ccedil: "Ç", ETH: "Ð", Eacute: "É", Ecirc: "Ê", Egrave: "È", Euml: "Ë", GT: ">", Iacute: "Í", Icirc: "Î", Igrave: "Ì", Iuml: "Ï", LT: "<", Ntilde: "Ñ", Oacute: "Ó", Ocirc: "Ô", Ograve: "Ò", Oslash: "Ø", Otilde: "Õ", Ouml: "Ö", QUOT: '"', REG: "®", THORN: "Þ", Uacute: "Ú", Ucirc: "Û", Ugrave: "Ù", Uuml: "Ü", Yacute: "Ý", aacute: "á", acirc: "â", acute: "´", aelig: "æ", agrave: "à", amp: "&", aring: "å", atilde: "ã", auml: "ä", brvbar: "¦", ccedil: "ç", cedil: "¸", cent: "¢", copy: "©", curren: "¤", deg: "°", divide: "÷", eacute: "é", ecirc: "ê", egrave: "è", eth: "ð", euml: "ë", frac12: "½", frac14: "¼", frac34: "¾", gt: ">", iacute: "í", icirc: "î", iexcl: "¡", igrave: "ì", iquest: "¿", iuml: "ï", laquo: "«", lt: "<", macr: "¯", micro: "µ", middot: "·", nbsp: " ", not: "¬", ntilde: "ñ", oacute: "ó", ocirc: "ô", ograve: "ò", ordf: "ª", ordm: "º", oslash: "ø", otilde: "õ", ouml: "ö", para: "¶", plusmn: "±", pound: "£", quot: '"', raquo: "»", reg: "®", sect: "§", shy: "­", sup1: "¹", sup2: "²", sup3: "³", szlig: "ß", thorn: "þ", times: "×", uacute: "ú", ucirc: "û", ugrave: "ù", uml: "¨", uuml: "ü", yacute: "ý", yen: "¥", yuml: "ÿ" };
});
var _u = C((vv, bD) => {
  bD.exports = { "0": "�", "128": "€", "130": "‚", "131": "ƒ", "132": "„", "133": "…", "134": "†", "135": "‡", "136": "ˆ", "137": "‰", "138": "Š", "139": "‹", "140": "Œ", "142": "Ž", "145": "‘", "146": "’", "147": "“", "148": "”", "149": "•", "150": "–", "151": "—", "152": "˜", "153": "™", "154": "š", "155": "›", "156": "œ", "158": "ž", "159": "Ÿ" };
});
var Re = C((Cv, Su) => {
  Su.exports = AD;
  function AD(e) {
    var r = typeof e == "string" ? e.charCodeAt(0) : e;
    return r >= 48 && r <= 57;
  }
});
var Ou = C((bv, Pu) => {
  Pu.exports = yD;
  function yD(e) {
    var r = typeof e == "string" ? e.charCodeAt(0) : e;
    return r >= 97 && r <= 102 || r >= 65 && r <= 70 || r >= 48 && r <= 57;
  }
});
var He = C((Av, Lu) => {
  Lu.exports = xD;
  function xD(e) {
    var r = typeof e == "string" ? e.charCodeAt(0) : e;
    return r >= 97 && r <= 122 || r >= 65 && r <= 90;
  }
});
var Ru = C((yv, Iu) => {
  var wD = He(), kD = Re();
  Iu.exports = BD;
  function BD(e) {
    return wD(e) || kD(e);
  }
});
var Nu = C((xv, TD) => {
  TD.exports = { AEli: "Æ", AElig: "Æ", AM: "&", AMP: "&", Aacut: "Á", Aacute: "Á", Abreve: "Ă", Acir: "Â", Acirc: "Â", Acy: "А", Afr: "𝔄", Agrav: "À", Agrave: "À", Alpha: "Α", Amacr: "Ā", And: "⩓", Aogon: "Ą", Aopf: "𝔸", ApplyFunction: "⁡", Arin: "Å", Aring: "Å", Ascr: "𝒜", Assign: "≔", Atild: "Ã", Atilde: "Ã", Aum: "Ä", Auml: "Ä", Backslash: "∖", Barv: "⫧", Barwed: "⌆", Bcy: "Б", Because: "∵", Bernoullis: "ℬ", Beta: "Β", Bfr: "𝔅", Bopf: "𝔹", Breve: "˘", Bscr: "ℬ", Bumpeq: "≎", CHcy: "Ч", COP: "©", COPY: "©", Cacute: "Ć", Cap: "⋒", CapitalDifferentialD: "ⅅ", Cayleys: "ℭ", Ccaron: "Č", Ccedi: "Ç", Ccedil: "Ç", Ccirc: "Ĉ", Cconint: "∰", Cdot: "Ċ", Cedilla: "¸", CenterDot: "·", Cfr: "ℭ", Chi: "Χ", CircleDot: "⊙", CircleMinus: "⊖", CirclePlus: "⊕", CircleTimes: "⊗", ClockwiseContourIntegral: "∲", CloseCurlyDoubleQuote: "”", CloseCurlyQuote: "’", Colon: "∷", Colone: "⩴", Congruent: "≡", Conint: "∯", ContourIntegral: "∮", Copf: "ℂ", Coproduct: "∐", CounterClockwiseContourIntegral: "∳", Cross: "⨯", Cscr: "𝒞", Cup: "⋓", CupCap: "≍", DD: "ⅅ", DDotrahd: "⤑", DJcy: "Ђ", DScy: "Ѕ", DZcy: "Џ", Dagger: "‡", Darr: "↡", Dashv: "⫤", Dcaron: "Ď", Dcy: "Д", Del: "∇", Delta: "Δ", Dfr: "𝔇", DiacriticalAcute: "´", DiacriticalDot: "˙", DiacriticalDoubleAcute: "˝", DiacriticalGrave: "`", DiacriticalTilde: "˜", Diamond: "⋄", DifferentialD: "ⅆ", Dopf: "𝔻", Dot: "¨", DotDot: "⃜", DotEqual: "≐", DoubleContourIntegral: "∯", DoubleDot: "¨", DoubleDownArrow: "⇓", DoubleLeftArrow: "⇐", DoubleLeftRightArrow: "⇔", DoubleLeftTee: "⫤", DoubleLongLeftArrow: "⟸", DoubleLongLeftRightArrow: "⟺", DoubleLongRightArrow: "⟹", DoubleRightArrow: "⇒", DoubleRightTee: "⊨", DoubleUpArrow: "⇑", DoubleUpDownArrow: "⇕", DoubleVerticalBar: "∥", DownArrow: "↓", DownArrowBar: "⤓", DownArrowUpArrow: "⇵", DownBreve: "̑", DownLeftRightVector: "⥐", DownLeftTeeVector: "⥞", DownLeftVector: "↽", DownLeftVectorBar: "⥖", DownRightTeeVector: "⥟", DownRightVector: "⇁", DownRightVectorBar: "⥗", DownTee: "⊤", DownTeeArrow: "↧", Downarrow: "⇓", Dscr: "𝒟", Dstrok: "Đ", ENG: "Ŋ", ET: "Ð", ETH: "Ð", Eacut: "É", Eacute: "É", Ecaron: "Ě", Ecir: "Ê", Ecirc: "Ê", Ecy: "Э", Edot: "Ė", Efr: "𝔈", Egrav: "È", Egrave: "È", Element: "∈", Emacr: "Ē", EmptySmallSquare: "◻", EmptyVerySmallSquare: "▫", Eogon: "Ę", Eopf: "𝔼", Epsilon: "Ε", Equal: "⩵", EqualTilde: "≂", Equilibrium: "⇌", Escr: "ℰ", Esim: "⩳", Eta: "Η", Eum: "Ë", Euml: "Ë", Exists: "∃", ExponentialE: "ⅇ", Fcy: "Ф", Ffr: "𝔉", FilledSmallSquare: "◼", FilledVerySmallSquare: "▪", Fopf: "𝔽", ForAll: "∀", Fouriertrf: "ℱ", Fscr: "ℱ", GJcy: "Ѓ", G: ">", GT: ">", Gamma: "Γ", Gammad: "Ϝ", Gbreve: "Ğ", Gcedil: "Ģ", Gcirc: "Ĝ", Gcy: "Г", Gdot: "Ġ", Gfr: "𝔊", Gg: "⋙", Gopf: "𝔾", GreaterEqual: "≥", GreaterEqualLess: "⋛", GreaterFullEqual: "≧", GreaterGreater: "⪢", GreaterLess: "≷", GreaterSlantEqual: "⩾", GreaterTilde: "≳", Gscr: "𝒢", Gt: "≫", HARDcy: "Ъ", Hacek: "ˇ", Hat: "^", Hcirc: "Ĥ", Hfr: "ℌ", HilbertSpace: "ℋ", Hopf: "ℍ", HorizontalLine: "─", Hscr: "ℋ", Hstrok: "Ħ", HumpDownHump: "≎", HumpEqual: "≏", IEcy: "Е", IJlig: "Ĳ", IOcy: "Ё", Iacut: "Í", Iacute: "Í", Icir: "Î", Icirc: "Î", Icy: "И", Idot: "İ", Ifr: "ℑ", Igrav: "Ì", Igrave: "Ì", Im: "ℑ", Imacr: "Ī", ImaginaryI: "ⅈ", Implies: "⇒", Int: "∬", Integral: "∫", Intersection: "⋂", InvisibleComma: "⁣", InvisibleTimes: "⁢", Iogon: "Į", Iopf: "𝕀", Iota: "Ι", Iscr: "ℐ", Itilde: "Ĩ", Iukcy: "І", Ium: "Ï", Iuml: "Ï", Jcirc: "Ĵ", Jcy: "Й", Jfr: "𝔍", Jopf: "𝕁", Jscr: "𝒥", Jsercy: "Ј", Jukcy: "Є", KHcy: "Х", KJcy: "Ќ", Kappa: "Κ", Kcedil: "Ķ", Kcy: "К", Kfr: "𝔎", Kopf: "𝕂", Kscr: "𝒦", LJcy: "Љ", L: "<", LT: "<", Lacute: "Ĺ", Lambda: "Λ", Lang: "⟪", Laplacetrf: "ℒ", Larr: "↞", Lcaron: "Ľ", Lcedil: "Ļ", Lcy: "Л", LeftAngleBracket: "⟨", LeftArrow: "←", LeftArrowBar: "⇤", LeftArrowRightArrow: "⇆", LeftCeiling: "⌈", LeftDoubleBracket: "⟦", LeftDownTeeVector: "⥡", LeftDownVector: "⇃", LeftDownVectorBar: "⥙", LeftFloor: "⌊", LeftRightArrow: "↔", LeftRightVector: "⥎", LeftTee: "⊣", LeftTeeArrow: "↤", LeftTeeVector: "⥚", LeftTriangle: "⊲", LeftTriangleBar: "⧏", LeftTriangleEqual: "⊴", LeftUpDownVector: "⥑", LeftUpTeeVector: "⥠", LeftUpVector: "↿", LeftUpVectorBar: "⥘", LeftVector: "↼", LeftVectorBar: "⥒", Leftarrow: "⇐", Leftrightarrow: "⇔", LessEqualGreater: "⋚", LessFullEqual: "≦", LessGreater: "≶", LessLess: "⪡", LessSlantEqual: "⩽", LessTilde: "≲", Lfr: "𝔏", Ll: "⋘", Lleftarrow: "⇚", Lmidot: "Ŀ", LongLeftArrow: "⟵", LongLeftRightArrow: "⟷", LongRightArrow: "⟶", Longleftarrow: "⟸", Longleftrightarrow: "⟺", Longrightarrow: "⟹", Lopf: "𝕃", LowerLeftArrow: "↙", LowerRightArrow: "↘", Lscr: "ℒ", Lsh: "↰", Lstrok: "Ł", Lt: "≪", Map: "⤅", Mcy: "М", MediumSpace: " ", Mellintrf: "ℳ", Mfr: "𝔐", MinusPlus: "∓", Mopf: "𝕄", Mscr: "ℳ", Mu: "Μ", NJcy: "Њ", Nacute: "Ń", Ncaron: "Ň", Ncedil: "Ņ", Ncy: "Н", NegativeMediumSpace: "​", NegativeThickSpace: "​", NegativeThinSpace: "​", NegativeVeryThinSpace: "​", NestedGreaterGreater: "≫", NestedLessLess: "≪", NewLine: `
`, Nfr: "𝔑", NoBreak: "⁠", NonBreakingSpace: " ", Nopf: "ℕ", Not: "⫬", NotCongruent: "≢", NotCupCap: "≭", NotDoubleVerticalBar: "∦", NotElement: "∉", NotEqual: "≠", NotEqualTilde: "≂̸", NotExists: "∄", NotGreater: "≯", NotGreaterEqual: "≱", NotGreaterFullEqual: "≧̸", NotGreaterGreater: "≫̸", NotGreaterLess: "≹", NotGreaterSlantEqual: "⩾̸", NotGreaterTilde: "≵", NotHumpDownHump: "≎̸", NotHumpEqual: "≏̸", NotLeftTriangle: "⋪", NotLeftTriangleBar: "⧏̸", NotLeftTriangleEqual: "⋬", NotLess: "≮", NotLessEqual: "≰", NotLessGreater: "≸", NotLessLess: "≪̸", NotLessSlantEqual: "⩽̸", NotLessTilde: "≴", NotNestedGreaterGreater: "⪢̸", NotNestedLessLess: "⪡̸", NotPrecedes: "⊀", NotPrecedesEqual: "⪯̸", NotPrecedesSlantEqual: "⋠", NotReverseElement: "∌", NotRightTriangle: "⋫", NotRightTriangleBar: "⧐̸", NotRightTriangleEqual: "⋭", NotSquareSubset: "⊏̸", NotSquareSubsetEqual: "⋢", NotSquareSuperset: "⊐̸", NotSquareSupersetEqual: "⋣", NotSubset: "⊂⃒", NotSubsetEqual: "⊈", NotSucceeds: "⊁", NotSucceedsEqual: "⪰̸", NotSucceedsSlantEqual: "⋡", NotSucceedsTilde: "≿̸", NotSuperset: "⊃⃒", NotSupersetEqual: "⊉", NotTilde: "≁", NotTildeEqual: "≄", NotTildeFullEqual: "≇", NotTildeTilde: "≉", NotVerticalBar: "∤", Nscr: "𝒩", Ntild: "Ñ", Ntilde: "Ñ", Nu: "Ν", OElig: "Œ", Oacut: "Ó", Oacute: "Ó", Ocir: "Ô", Ocirc: "Ô", Ocy: "О", Odblac: "Ő", Ofr: "𝔒", Ograv: "Ò", Ograve: "Ò", Omacr: "Ō", Omega: "Ω", Omicron: "Ο", Oopf: "𝕆", OpenCurlyDoubleQuote: "“", OpenCurlyQuote: "‘", Or: "⩔", Oscr: "𝒪", Oslas: "Ø", Oslash: "Ø", Otild: "Õ", Otilde: "Õ", Otimes: "⨷", Oum: "Ö", Ouml: "Ö", OverBar: "‾", OverBrace: "⏞", OverBracket: "⎴", OverParenthesis: "⏜", PartialD: "∂", Pcy: "П", Pfr: "𝔓", Phi: "Φ", Pi: "Π", PlusMinus: "±", Poincareplane: "ℌ", Popf: "ℙ", Pr: "⪻", Precedes: "≺", PrecedesEqual: "⪯", PrecedesSlantEqual: "≼", PrecedesTilde: "≾", Prime: "″", Product: "∏", Proportion: "∷", Proportional: "∝", Pscr: "𝒫", Psi: "Ψ", QUO: '"', QUOT: '"', Qfr: "𝔔", Qopf: "ℚ", Qscr: "𝒬", RBarr: "⤐", RE: "®", REG: "®", Racute: "Ŕ", Rang: "⟫", Rarr: "↠", Rarrtl: "⤖", Rcaron: "Ř", Rcedil: "Ŗ", Rcy: "Р", Re: "ℜ", ReverseElement: "∋", ReverseEquilibrium: "⇋", ReverseUpEquilibrium: "⥯", Rfr: "ℜ", Rho: "Ρ", RightAngleBracket: "⟩", RightArrow: "→", RightArrowBar: "⇥", RightArrowLeftArrow: "⇄", RightCeiling: "⌉", RightDoubleBracket: "⟧", RightDownTeeVector: "⥝", RightDownVector: "⇂", RightDownVectorBar: "⥕", RightFloor: "⌋", RightTee: "⊢", RightTeeArrow: "↦", RightTeeVector: "⥛", RightTriangle: "⊳", RightTriangleBar: "⧐", RightTriangleEqual: "⊵", RightUpDownVector: "⥏", RightUpTeeVector: "⥜", RightUpVector: "↾", RightUpVectorBar: "⥔", RightVector: "⇀", RightVectorBar: "⥓", Rightarrow: "⇒", Ropf: "ℝ", RoundImplies: "⥰", Rrightarrow: "⇛", Rscr: "ℛ", Rsh: "↱", RuleDelayed: "⧴", SHCHcy: "Щ", SHcy: "Ш", SOFTcy: "Ь", Sacute: "Ś", Sc: "⪼", Scaron: "Š", Scedil: "Ş", Scirc: "Ŝ", Scy: "С", Sfr: "𝔖", ShortDownArrow: "↓", ShortLeftArrow: "←", ShortRightArrow: "→", ShortUpArrow: "↑", Sigma: "Σ", SmallCircle: "∘", Sopf: "𝕊", Sqrt: "√", Square: "□", SquareIntersection: "⊓", SquareSubset: "⊏", SquareSubsetEqual: "⊑", SquareSuperset: "⊐", SquareSupersetEqual: "⊒", SquareUnion: "⊔", Sscr: "𝒮", Star: "⋆", Sub: "⋐", Subset: "⋐", SubsetEqual: "⊆", Succeeds: "≻", SucceedsEqual: "⪰", SucceedsSlantEqual: "≽", SucceedsTilde: "≿", SuchThat: "∋", Sum: "∑", Sup: "⋑", Superset: "⊃", SupersetEqual: "⊇", Supset: "⋑", THOR: "Þ", THORN: "Þ", TRADE: "™", TSHcy: "Ћ", TScy: "Ц", Tab: "	", Tau: "Τ", Tcaron: "Ť", Tcedil: "Ţ", Tcy: "Т", Tfr: "𝔗", Therefore: "∴", Theta: "Θ", ThickSpace: "  ", ThinSpace: " ", Tilde: "∼", TildeEqual: "≃", TildeFullEqual: "≅", TildeTilde: "≈", Topf: "𝕋", TripleDot: "⃛", Tscr: "𝒯", Tstrok: "Ŧ", Uacut: "Ú", Uacute: "Ú", Uarr: "↟", Uarrocir: "⥉", Ubrcy: "Ў", Ubreve: "Ŭ", Ucir: "Û", Ucirc: "Û", Ucy: "У", Udblac: "Ű", Ufr: "𝔘", Ugrav: "Ù", Ugrave: "Ù", Umacr: "Ū", UnderBar: "_", UnderBrace: "⏟", UnderBracket: "⎵", UnderParenthesis: "⏝", Union: "⋃", UnionPlus: "⊎", Uogon: "Ų", Uopf: "𝕌", UpArrow: "↑", UpArrowBar: "⤒", UpArrowDownArrow: "⇅", UpDownArrow: "↕", UpEquilibrium: "⥮", UpTee: "⊥", UpTeeArrow: "↥", Uparrow: "⇑", Updownarrow: "⇕", UpperLeftArrow: "↖", UpperRightArrow: "↗", Upsi: "ϒ", Upsilon: "Υ", Uring: "Ů", Uscr: "𝒰", Utilde: "Ũ", Uum: "Ü", Uuml: "Ü", VDash: "⊫", Vbar: "⫫", Vcy: "В", Vdash: "⊩", Vdashl: "⫦", Vee: "⋁", Verbar: "‖", Vert: "‖", VerticalBar: "∣", VerticalLine: "|", VerticalSeparator: "❘", VerticalTilde: "≀", VeryThinSpace: " ", Vfr: "𝔙", Vopf: "𝕍", Vscr: "𝒱", Vvdash: "⊪", Wcirc: "Ŵ", Wedge: "⋀", Wfr: "𝔚", Wopf: "𝕎", Wscr: "𝒲", Xfr: "𝔛", Xi: "Ξ", Xopf: "𝕏", Xscr: "𝒳", YAcy: "Я", YIcy: "Ї", YUcy: "Ю", Yacut: "Ý", Yacute: "Ý", Ycirc: "Ŷ", Ycy: "Ы", Yfr: "𝔜", Yopf: "𝕐", Yscr: "𝒴", Yuml: "Ÿ", ZHcy: "Ж", Zacute: "Ź", Zcaron: "Ž", Zcy: "З", Zdot: "Ż", ZeroWidthSpace: "​", Zeta: "Ζ", Zfr: "ℨ", Zopf: "ℤ", Zscr: "𝒵", aacut: "á", aacute: "á", abreve: "ă", ac: "∾", acE: "∾̳", acd: "∿", acir: "â", acirc: "â", acut: "´", acute: "´", acy: "а", aeli: "æ", aelig: "æ", af: "⁡", afr: "𝔞", agrav: "à", agrave: "à", alefsym: "ℵ", aleph: "ℵ", alpha: "α", amacr: "ā", amalg: "⨿", am: "&", amp: "&", and: "∧", andand: "⩕", andd: "⩜", andslope: "⩘", andv: "⩚", ang: "∠", ange: "⦤", angle: "∠", angmsd: "∡", angmsdaa: "⦨", angmsdab: "⦩", angmsdac: "⦪", angmsdad: "⦫", angmsdae: "⦬", angmsdaf: "⦭", angmsdag: "⦮", angmsdah: "⦯", angrt: "∟", angrtvb: "⊾", angrtvbd: "⦝", angsph: "∢", angst: "Å", angzarr: "⍼", aogon: "ą", aopf: "𝕒", ap: "≈", apE: "⩰", apacir: "⩯", ape: "≊", apid: "≋", apos: "'", approx: "≈", approxeq: "≊", arin: "å", aring: "å", ascr: "𝒶", ast: "*", asymp: "≈", asympeq: "≍", atild: "ã", atilde: "ã", aum: "ä", auml: "ä", awconint: "∳", awint: "⨑", bNot: "⫭", backcong: "≌", backepsilon: "϶", backprime: "‵", backsim: "∽", backsimeq: "⋍", barvee: "⊽", barwed: "⌅", barwedge: "⌅", bbrk: "⎵", bbrktbrk: "⎶", bcong: "≌", bcy: "б", bdquo: "„", becaus: "∵", because: "∵", bemptyv: "⦰", bepsi: "϶", bernou: "ℬ", beta: "β", beth: "ℶ", between: "≬", bfr: "𝔟", bigcap: "⋂", bigcirc: "◯", bigcup: "⋃", bigodot: "⨀", bigoplus: "⨁", bigotimes: "⨂", bigsqcup: "⨆", bigstar: "★", bigtriangledown: "▽", bigtriangleup: "△", biguplus: "⨄", bigvee: "⋁", bigwedge: "⋀", bkarow: "⤍", blacklozenge: "⧫", blacksquare: "▪", blacktriangle: "▴", blacktriangledown: "▾", blacktriangleleft: "◂", blacktriangleright: "▸", blank: "␣", blk12: "▒", blk14: "░", blk34: "▓", block: "█", bne: "=⃥", bnequiv: "≡⃥", bnot: "⌐", bopf: "𝕓", bot: "⊥", bottom: "⊥", bowtie: "⋈", boxDL: "╗", boxDR: "╔", boxDl: "╖", boxDr: "╓", boxH: "═", boxHD: "╦", boxHU: "╩", boxHd: "╤", boxHu: "╧", boxUL: "╝", boxUR: "╚", boxUl: "╜", boxUr: "╙", boxV: "║", boxVH: "╬", boxVL: "╣", boxVR: "╠", boxVh: "╫", boxVl: "╢", boxVr: "╟", boxbox: "⧉", boxdL: "╕", boxdR: "╒", boxdl: "┐", boxdr: "┌", boxh: "─", boxhD: "╥", boxhU: "╨", boxhd: "┬", boxhu: "┴", boxminus: "⊟", boxplus: "⊞", boxtimes: "⊠", boxuL: "╛", boxuR: "╘", boxul: "┘", boxur: "└", boxv: "│", boxvH: "╪", boxvL: "╡", boxvR: "╞", boxvh: "┼", boxvl: "┤", boxvr: "├", bprime: "‵", breve: "˘", brvba: "¦", brvbar: "¦", bscr: "𝒷", bsemi: "⁏", bsim: "∽", bsime: "⋍", bsol: "\\", bsolb: "⧅", bsolhsub: "⟈", bull: "•", bullet: "•", bump: "≎", bumpE: "⪮", bumpe: "≏", bumpeq: "≏", cacute: "ć", cap: "∩", capand: "⩄", capbrcup: "⩉", capcap: "⩋", capcup: "⩇", capdot: "⩀", caps: "∩︀", caret: "⁁", caron: "ˇ", ccaps: "⩍", ccaron: "č", ccedi: "ç", ccedil: "ç", ccirc: "ĉ", ccups: "⩌", ccupssm: "⩐", cdot: "ċ", cedi: "¸", cedil: "¸", cemptyv: "⦲", cen: "¢", cent: "¢", centerdot: "·", cfr: "𝔠", chcy: "ч", check: "✓", checkmark: "✓", chi: "χ", cir: "○", cirE: "⧃", circ: "ˆ", circeq: "≗", circlearrowleft: "↺", circlearrowright: "↻", circledR: "®", circledS: "Ⓢ", circledast: "⊛", circledcirc: "⊚", circleddash: "⊝", cire: "≗", cirfnint: "⨐", cirmid: "⫯", cirscir: "⧂", clubs: "♣", clubsuit: "♣", colon: ":", colone: "≔", coloneq: "≔", comma: ",", commat: "@", comp: "∁", compfn: "∘", complement: "∁", complexes: "ℂ", cong: "≅", congdot: "⩭", conint: "∮", copf: "𝕔", coprod: "∐", cop: "©", copy: "©", copysr: "℗", crarr: "↵", cross: "✗", cscr: "𝒸", csub: "⫏", csube: "⫑", csup: "⫐", csupe: "⫒", ctdot: "⋯", cudarrl: "⤸", cudarrr: "⤵", cuepr: "⋞", cuesc: "⋟", cularr: "↶", cularrp: "⤽", cup: "∪", cupbrcap: "⩈", cupcap: "⩆", cupcup: "⩊", cupdot: "⊍", cupor: "⩅", cups: "∪︀", curarr: "↷", curarrm: "⤼", curlyeqprec: "⋞", curlyeqsucc: "⋟", curlyvee: "⋎", curlywedge: "⋏", curre: "¤", curren: "¤", curvearrowleft: "↶", curvearrowright: "↷", cuvee: "⋎", cuwed: "⋏", cwconint: "∲", cwint: "∱", cylcty: "⌭", dArr: "⇓", dHar: "⥥", dagger: "†", daleth: "ℸ", darr: "↓", dash: "‐", dashv: "⊣", dbkarow: "⤏", dblac: "˝", dcaron: "ď", dcy: "д", dd: "ⅆ", ddagger: "‡", ddarr: "⇊", ddotseq: "⩷", de: "°", deg: "°", delta: "δ", demptyv: "⦱", dfisht: "⥿", dfr: "𝔡", dharl: "⇃", dharr: "⇂", diam: "⋄", diamond: "⋄", diamondsuit: "♦", diams: "♦", die: "¨", digamma: "ϝ", disin: "⋲", div: "÷", divid: "÷", divide: "÷", divideontimes: "⋇", divonx: "⋇", djcy: "ђ", dlcorn: "⌞", dlcrop: "⌍", dollar: "$", dopf: "𝕕", dot: "˙", doteq: "≐", doteqdot: "≑", dotminus: "∸", dotplus: "∔", dotsquare: "⊡", doublebarwedge: "⌆", downarrow: "↓", downdownarrows: "⇊", downharpoonleft: "⇃", downharpoonright: "⇂", drbkarow: "⤐", drcorn: "⌟", drcrop: "⌌", dscr: "𝒹", dscy: "ѕ", dsol: "⧶", dstrok: "đ", dtdot: "⋱", dtri: "▿", dtrif: "▾", duarr: "⇵", duhar: "⥯", dwangle: "⦦", dzcy: "џ", dzigrarr: "⟿", eDDot: "⩷", eDot: "≑", eacut: "é", eacute: "é", easter: "⩮", ecaron: "ě", ecir: "ê", ecirc: "ê", ecolon: "≕", ecy: "э", edot: "ė", ee: "ⅇ", efDot: "≒", efr: "𝔢", eg: "⪚", egrav: "è", egrave: "è", egs: "⪖", egsdot: "⪘", el: "⪙", elinters: "⏧", ell: "ℓ", els: "⪕", elsdot: "⪗", emacr: "ē", empty: "∅", emptyset: "∅", emptyv: "∅", emsp13: " ", emsp14: " ", emsp: " ", eng: "ŋ", ensp: " ", eogon: "ę", eopf: "𝕖", epar: "⋕", eparsl: "⧣", eplus: "⩱", epsi: "ε", epsilon: "ε", epsiv: "ϵ", eqcirc: "≖", eqcolon: "≕", eqsim: "≂", eqslantgtr: "⪖", eqslantless: "⪕", equals: "=", equest: "≟", equiv: "≡", equivDD: "⩸", eqvparsl: "⧥", erDot: "≓", erarr: "⥱", escr: "ℯ", esdot: "≐", esim: "≂", eta: "η", et: "ð", eth: "ð", eum: "ë", euml: "ë", euro: "€", excl: "!", exist: "∃", expectation: "ℰ", exponentiale: "ⅇ", fallingdotseq: "≒", fcy: "ф", female: "♀", ffilig: "ﬃ", fflig: "ﬀ", ffllig: "ﬄ", ffr: "𝔣", filig: "ﬁ", fjlig: "fj", flat: "♭", fllig: "ﬂ", fltns: "▱", fnof: "ƒ", fopf: "𝕗", forall: "∀", fork: "⋔", forkv: "⫙", fpartint: "⨍", frac1: "¼", frac12: "½", frac13: "⅓", frac14: "¼", frac15: "⅕", frac16: "⅙", frac18: "⅛", frac23: "⅔", frac25: "⅖", frac3: "¾", frac34: "¾", frac35: "⅗", frac38: "⅜", frac45: "⅘", frac56: "⅚", frac58: "⅝", frac78: "⅞", frasl: "⁄", frown: "⌢", fscr: "𝒻", gE: "≧", gEl: "⪌", gacute: "ǵ", gamma: "γ", gammad: "ϝ", gap: "⪆", gbreve: "ğ", gcirc: "ĝ", gcy: "г", gdot: "ġ", ge: "≥", gel: "⋛", geq: "≥", geqq: "≧", geqslant: "⩾", ges: "⩾", gescc: "⪩", gesdot: "⪀", gesdoto: "⪂", gesdotol: "⪄", gesl: "⋛︀", gesles: "⪔", gfr: "𝔤", gg: "≫", ggg: "⋙", gimel: "ℷ", gjcy: "ѓ", gl: "≷", glE: "⪒", gla: "⪥", glj: "⪤", gnE: "≩", gnap: "⪊", gnapprox: "⪊", gne: "⪈", gneq: "⪈", gneqq: "≩", gnsim: "⋧", gopf: "𝕘", grave: "`", gscr: "ℊ", gsim: "≳", gsime: "⪎", gsiml: "⪐", g: ">", gt: ">", gtcc: "⪧", gtcir: "⩺", gtdot: "⋗", gtlPar: "⦕", gtquest: "⩼", gtrapprox: "⪆", gtrarr: "⥸", gtrdot: "⋗", gtreqless: "⋛", gtreqqless: "⪌", gtrless: "≷", gtrsim: "≳", gvertneqq: "≩︀", gvnE: "≩︀", hArr: "⇔", hairsp: " ", half: "½", hamilt: "ℋ", hardcy: "ъ", harr: "↔", harrcir: "⥈", harrw: "↭", hbar: "ℏ", hcirc: "ĥ", hearts: "♥", heartsuit: "♥", hellip: "…", hercon: "⊹", hfr: "𝔥", hksearow: "⤥", hkswarow: "⤦", hoarr: "⇿", homtht: "∻", hookleftarrow: "↩", hookrightarrow: "↪", hopf: "𝕙", horbar: "―", hscr: "𝒽", hslash: "ℏ", hstrok: "ħ", hybull: "⁃", hyphen: "‐", iacut: "í", iacute: "í", ic: "⁣", icir: "î", icirc: "î", icy: "и", iecy: "е", iexc: "¡", iexcl: "¡", iff: "⇔", ifr: "𝔦", igrav: "ì", igrave: "ì", ii: "ⅈ", iiiint: "⨌", iiint: "∭", iinfin: "⧜", iiota: "℩", ijlig: "ĳ", imacr: "ī", image: "ℑ", imagline: "ℐ", imagpart: "ℑ", imath: "ı", imof: "⊷", imped: "Ƶ", in: "∈", incare: "℅", infin: "∞", infintie: "⧝", inodot: "ı", int: "∫", intcal: "⊺", integers: "ℤ", intercal: "⊺", intlarhk: "⨗", intprod: "⨼", iocy: "ё", iogon: "į", iopf: "𝕚", iota: "ι", iprod: "⨼", iques: "¿", iquest: "¿", iscr: "𝒾", isin: "∈", isinE: "⋹", isindot: "⋵", isins: "⋴", isinsv: "⋳", isinv: "∈", it: "⁢", itilde: "ĩ", iukcy: "і", ium: "ï", iuml: "ï", jcirc: "ĵ", jcy: "й", jfr: "𝔧", jmath: "ȷ", jopf: "𝕛", jscr: "𝒿", jsercy: "ј", jukcy: "є", kappa: "κ", kappav: "ϰ", kcedil: "ķ", kcy: "к", kfr: "𝔨", kgreen: "ĸ", khcy: "х", kjcy: "ќ", kopf: "𝕜", kscr: "𝓀", lAarr: "⇚", lArr: "⇐", lAtail: "⤛", lBarr: "⤎", lE: "≦", lEg: "⪋", lHar: "⥢", lacute: "ĺ", laemptyv: "⦴", lagran: "ℒ", lambda: "λ", lang: "⟨", langd: "⦑", langle: "⟨", lap: "⪅", laqu: "«", laquo: "«", larr: "←", larrb: "⇤", larrbfs: "⤟", larrfs: "⤝", larrhk: "↩", larrlp: "↫", larrpl: "⤹", larrsim: "⥳", larrtl: "↢", lat: "⪫", latail: "⤙", late: "⪭", lates: "⪭︀", lbarr: "⤌", lbbrk: "❲", lbrace: "{", lbrack: "[", lbrke: "⦋", lbrksld: "⦏", lbrkslu: "⦍", lcaron: "ľ", lcedil: "ļ", lceil: "⌈", lcub: "{", lcy: "л", ldca: "⤶", ldquo: "“", ldquor: "„", ldrdhar: "⥧", ldrushar: "⥋", ldsh: "↲", le: "≤", leftarrow: "←", leftarrowtail: "↢", leftharpoondown: "↽", leftharpoonup: "↼", leftleftarrows: "⇇", leftrightarrow: "↔", leftrightarrows: "⇆", leftrightharpoons: "⇋", leftrightsquigarrow: "↭", leftthreetimes: "⋋", leg: "⋚", leq: "≤", leqq: "≦", leqslant: "⩽", les: "⩽", lescc: "⪨", lesdot: "⩿", lesdoto: "⪁", lesdotor: "⪃", lesg: "⋚︀", lesges: "⪓", lessapprox: "⪅", lessdot: "⋖", lesseqgtr: "⋚", lesseqqgtr: "⪋", lessgtr: "≶", lesssim: "≲", lfisht: "⥼", lfloor: "⌊", lfr: "𝔩", lg: "≶", lgE: "⪑", lhard: "↽", lharu: "↼", lharul: "⥪", lhblk: "▄", ljcy: "љ", ll: "≪", llarr: "⇇", llcorner: "⌞", llhard: "⥫", lltri: "◺", lmidot: "ŀ", lmoust: "⎰", lmoustache: "⎰", lnE: "≨", lnap: "⪉", lnapprox: "⪉", lne: "⪇", lneq: "⪇", lneqq: "≨", lnsim: "⋦", loang: "⟬", loarr: "⇽", lobrk: "⟦", longleftarrow: "⟵", longleftrightarrow: "⟷", longmapsto: "⟼", longrightarrow: "⟶", looparrowleft: "↫", looparrowright: "↬", lopar: "⦅", lopf: "𝕝", loplus: "⨭", lotimes: "⨴", lowast: "∗", lowbar: "_", loz: "◊", lozenge: "◊", lozf: "⧫", lpar: "(", lparlt: "⦓", lrarr: "⇆", lrcorner: "⌟", lrhar: "⇋", lrhard: "⥭", lrm: "‎", lrtri: "⊿", lsaquo: "‹", lscr: "𝓁", lsh: "↰", lsim: "≲", lsime: "⪍", lsimg: "⪏", lsqb: "[", lsquo: "‘", lsquor: "‚", lstrok: "ł", l: "<", lt: "<", ltcc: "⪦", ltcir: "⩹", ltdot: "⋖", lthree: "⋋", ltimes: "⋉", ltlarr: "⥶", ltquest: "⩻", ltrPar: "⦖", ltri: "◃", ltrie: "⊴", ltrif: "◂", lurdshar: "⥊", luruhar: "⥦", lvertneqq: "≨︀", lvnE: "≨︀", mDDot: "∺", mac: "¯", macr: "¯", male: "♂", malt: "✠", maltese: "✠", map: "↦", mapsto: "↦", mapstodown: "↧", mapstoleft: "↤", mapstoup: "↥", marker: "▮", mcomma: "⨩", mcy: "м", mdash: "—", measuredangle: "∡", mfr: "𝔪", mho: "℧", micr: "µ", micro: "µ", mid: "∣", midast: "*", midcir: "⫰", middo: "·", middot: "·", minus: "−", minusb: "⊟", minusd: "∸", minusdu: "⨪", mlcp: "⫛", mldr: "…", mnplus: "∓", models: "⊧", mopf: "𝕞", mp: "∓", mscr: "𝓂", mstpos: "∾", mu: "μ", multimap: "⊸", mumap: "⊸", nGg: "⋙̸", nGt: "≫⃒", nGtv: "≫̸", nLeftarrow: "⇍", nLeftrightarrow: "⇎", nLl: "⋘̸", nLt: "≪⃒", nLtv: "≪̸", nRightarrow: "⇏", nVDash: "⊯", nVdash: "⊮", nabla: "∇", nacute: "ń", nang: "∠⃒", nap: "≉", napE: "⩰̸", napid: "≋̸", napos: "ŉ", napprox: "≉", natur: "♮", natural: "♮", naturals: "ℕ", nbs: " ", nbsp: " ", nbump: "≎̸", nbumpe: "≏̸", ncap: "⩃", ncaron: "ň", ncedil: "ņ", ncong: "≇", ncongdot: "⩭̸", ncup: "⩂", ncy: "н", ndash: "–", ne: "≠", neArr: "⇗", nearhk: "⤤", nearr: "↗", nearrow: "↗", nedot: "≐̸", nequiv: "≢", nesear: "⤨", nesim: "≂̸", nexist: "∄", nexists: "∄", nfr: "𝔫", ngE: "≧̸", nge: "≱", ngeq: "≱", ngeqq: "≧̸", ngeqslant: "⩾̸", nges: "⩾̸", ngsim: "≵", ngt: "≯", ngtr: "≯", nhArr: "⇎", nharr: "↮", nhpar: "⫲", ni: "∋", nis: "⋼", nisd: "⋺", niv: "∋", njcy: "њ", nlArr: "⇍", nlE: "≦̸", nlarr: "↚", nldr: "‥", nle: "≰", nleftarrow: "↚", nleftrightarrow: "↮", nleq: "≰", nleqq: "≦̸", nleqslant: "⩽̸", nles: "⩽̸", nless: "≮", nlsim: "≴", nlt: "≮", nltri: "⋪", nltrie: "⋬", nmid: "∤", nopf: "𝕟", no: "¬", not: "¬", notin: "∉", notinE: "⋹̸", notindot: "⋵̸", notinva: "∉", notinvb: "⋷", notinvc: "⋶", notni: "∌", notniva: "∌", notnivb: "⋾", notnivc: "⋽", npar: "∦", nparallel: "∦", nparsl: "⫽⃥", npart: "∂̸", npolint: "⨔", npr: "⊀", nprcue: "⋠", npre: "⪯̸", nprec: "⊀", npreceq: "⪯̸", nrArr: "⇏", nrarr: "↛", nrarrc: "⤳̸", nrarrw: "↝̸", nrightarrow: "↛", nrtri: "⋫", nrtrie: "⋭", nsc: "⊁", nsccue: "⋡", nsce: "⪰̸", nscr: "𝓃", nshortmid: "∤", nshortparallel: "∦", nsim: "≁", nsime: "≄", nsimeq: "≄", nsmid: "∤", nspar: "∦", nsqsube: "⋢", nsqsupe: "⋣", nsub: "⊄", nsubE: "⫅̸", nsube: "⊈", nsubset: "⊂⃒", nsubseteq: "⊈", nsubseteqq: "⫅̸", nsucc: "⊁", nsucceq: "⪰̸", nsup: "⊅", nsupE: "⫆̸", nsupe: "⊉", nsupset: "⊃⃒", nsupseteq: "⊉", nsupseteqq: "⫆̸", ntgl: "≹", ntild: "ñ", ntilde: "ñ", ntlg: "≸", ntriangleleft: "⋪", ntrianglelefteq: "⋬", ntriangleright: "⋫", ntrianglerighteq: "⋭", nu: "ν", num: "#", numero: "№", numsp: " ", nvDash: "⊭", nvHarr: "⤄", nvap: "≍⃒", nvdash: "⊬", nvge: "≥⃒", nvgt: ">⃒", nvinfin: "⧞", nvlArr: "⤂", nvle: "≤⃒", nvlt: "<⃒", nvltrie: "⊴⃒", nvrArr: "⤃", nvrtrie: "⊵⃒", nvsim: "∼⃒", nwArr: "⇖", nwarhk: "⤣", nwarr: "↖", nwarrow: "↖", nwnear: "⤧", oS: "Ⓢ", oacut: "ó", oacute: "ó", oast: "⊛", ocir: "ô", ocirc: "ô", ocy: "о", odash: "⊝", odblac: "ő", odiv: "⨸", odot: "⊙", odsold: "⦼", oelig: "œ", ofcir: "⦿", ofr: "𝔬", ogon: "˛", ograv: "ò", ograve: "ò", ogt: "⧁", ohbar: "⦵", ohm: "Ω", oint: "∮", olarr: "↺", olcir: "⦾", olcross: "⦻", oline: "‾", olt: "⧀", omacr: "ō", omega: "ω", omicron: "ο", omid: "⦶", ominus: "⊖", oopf: "𝕠", opar: "⦷", operp: "⦹", oplus: "⊕", or: "∨", orarr: "↻", ord: "º", order: "ℴ", orderof: "ℴ", ordf: "ª", ordm: "º", origof: "⊶", oror: "⩖", orslope: "⩗", orv: "⩛", oscr: "ℴ", oslas: "ø", oslash: "ø", osol: "⊘", otild: "õ", otilde: "õ", otimes: "⊗", otimesas: "⨶", oum: "ö", ouml: "ö", ovbar: "⌽", par: "¶", para: "¶", parallel: "∥", parsim: "⫳", parsl: "⫽", part: "∂", pcy: "п", percnt: "%", period: ".", permil: "‰", perp: "⊥", pertenk: "‱", pfr: "𝔭", phi: "φ", phiv: "ϕ", phmmat: "ℳ", phone: "☎", pi: "π", pitchfork: "⋔", piv: "ϖ", planck: "ℏ", planckh: "ℎ", plankv: "ℏ", plus: "+", plusacir: "⨣", plusb: "⊞", pluscir: "⨢", plusdo: "∔", plusdu: "⨥", pluse: "⩲", plusm: "±", plusmn: "±", plussim: "⨦", plustwo: "⨧", pm: "±", pointint: "⨕", popf: "𝕡", poun: "£", pound: "£", pr: "≺", prE: "⪳", prap: "⪷", prcue: "≼", pre: "⪯", prec: "≺", precapprox: "⪷", preccurlyeq: "≼", preceq: "⪯", precnapprox: "⪹", precneqq: "⪵", precnsim: "⋨", precsim: "≾", prime: "′", primes: "ℙ", prnE: "⪵", prnap: "⪹", prnsim: "⋨", prod: "∏", profalar: "⌮", profline: "⌒", profsurf: "⌓", prop: "∝", propto: "∝", prsim: "≾", prurel: "⊰", pscr: "𝓅", psi: "ψ", puncsp: " ", qfr: "𝔮", qint: "⨌", qopf: "𝕢", qprime: "⁗", qscr: "𝓆", quaternions: "ℍ", quatint: "⨖", quest: "?", questeq: "≟", quo: '"', quot: '"', rAarr: "⇛", rArr: "⇒", rAtail: "⤜", rBarr: "⤏", rHar: "⥤", race: "∽̱", racute: "ŕ", radic: "√", raemptyv: "⦳", rang: "⟩", rangd: "⦒", range: "⦥", rangle: "⟩", raqu: "»", raquo: "»", rarr: "→", rarrap: "⥵", rarrb: "⇥", rarrbfs: "⤠", rarrc: "⤳", rarrfs: "⤞", rarrhk: "↪", rarrlp: "↬", rarrpl: "⥅", rarrsim: "⥴", rarrtl: "↣", rarrw: "↝", ratail: "⤚", ratio: "∶", rationals: "ℚ", rbarr: "⤍", rbbrk: "❳", rbrace: "}", rbrack: "]", rbrke: "⦌", rbrksld: "⦎", rbrkslu: "⦐", rcaron: "ř", rcedil: "ŗ", rceil: "⌉", rcub: "}", rcy: "р", rdca: "⤷", rdldhar: "⥩", rdquo: "”", rdquor: "”", rdsh: "↳", real: "ℜ", realine: "ℛ", realpart: "ℜ", reals: "ℝ", rect: "▭", re: "®", reg: "®", rfisht: "⥽", rfloor: "⌋", rfr: "𝔯", rhard: "⇁", rharu: "⇀", rharul: "⥬", rho: "ρ", rhov: "ϱ", rightarrow: "→", rightarrowtail: "↣", rightharpoondown: "⇁", rightharpoonup: "⇀", rightleftarrows: "⇄", rightleftharpoons: "⇌", rightrightarrows: "⇉", rightsquigarrow: "↝", rightthreetimes: "⋌", ring: "˚", risingdotseq: "≓", rlarr: "⇄", rlhar: "⇌", rlm: "‏", rmoust: "⎱", rmoustache: "⎱", rnmid: "⫮", roang: "⟭", roarr: "⇾", robrk: "⟧", ropar: "⦆", ropf: "𝕣", roplus: "⨮", rotimes: "⨵", rpar: ")", rpargt: "⦔", rppolint: "⨒", rrarr: "⇉", rsaquo: "›", rscr: "𝓇", rsh: "↱", rsqb: "]", rsquo: "’", rsquor: "’", rthree: "⋌", rtimes: "⋊", rtri: "▹", rtrie: "⊵", rtrif: "▸", rtriltri: "⧎", ruluhar: "⥨", rx: "℞", sacute: "ś", sbquo: "‚", sc: "≻", scE: "⪴", scap: "⪸", scaron: "š", sccue: "≽", sce: "⪰", scedil: "ş", scirc: "ŝ", scnE: "⪶", scnap: "⪺", scnsim: "⋩", scpolint: "⨓", scsim: "≿", scy: "с", sdot: "⋅", sdotb: "⊡", sdote: "⩦", seArr: "⇘", searhk: "⤥", searr: "↘", searrow: "↘", sec: "§", sect: "§", semi: ";", seswar: "⤩", setminus: "∖", setmn: "∖", sext: "✶", sfr: "𝔰", sfrown: "⌢", sharp: "♯", shchcy: "щ", shcy: "ш", shortmid: "∣", shortparallel: "∥", sh: "­", shy: "­", sigma: "σ", sigmaf: "ς", sigmav: "ς", sim: "∼", simdot: "⩪", sime: "≃", simeq: "≃", simg: "⪞", simgE: "⪠", siml: "⪝", simlE: "⪟", simne: "≆", simplus: "⨤", simrarr: "⥲", slarr: "←", smallsetminus: "∖", smashp: "⨳", smeparsl: "⧤", smid: "∣", smile: "⌣", smt: "⪪", smte: "⪬", smtes: "⪬︀", softcy: "ь", sol: "/", solb: "⧄", solbar: "⌿", sopf: "𝕤", spades: "♠", spadesuit: "♠", spar: "∥", sqcap: "⊓", sqcaps: "⊓︀", sqcup: "⊔", sqcups: "⊔︀", sqsub: "⊏", sqsube: "⊑", sqsubset: "⊏", sqsubseteq: "⊑", sqsup: "⊐", sqsupe: "⊒", sqsupset: "⊐", sqsupseteq: "⊒", squ: "□", square: "□", squarf: "▪", squf: "▪", srarr: "→", sscr: "𝓈", ssetmn: "∖", ssmile: "⌣", sstarf: "⋆", star: "☆", starf: "★", straightepsilon: "ϵ", straightphi: "ϕ", strns: "¯", sub: "⊂", subE: "⫅", subdot: "⪽", sube: "⊆", subedot: "⫃", submult: "⫁", subnE: "⫋", subne: "⊊", subplus: "⪿", subrarr: "⥹", subset: "⊂", subseteq: "⊆", subseteqq: "⫅", subsetneq: "⊊", subsetneqq: "⫋", subsim: "⫇", subsub: "⫕", subsup: "⫓", succ: "≻", succapprox: "⪸", succcurlyeq: "≽", succeq: "⪰", succnapprox: "⪺", succneqq: "⪶", succnsim: "⋩", succsim: "≿", sum: "∑", sung: "♪", sup: "⊃", sup1: "¹", sup2: "²", sup3: "³", supE: "⫆", supdot: "⪾", supdsub: "⫘", supe: "⊇", supedot: "⫄", suphsol: "⟉", suphsub: "⫗", suplarr: "⥻", supmult: "⫂", supnE: "⫌", supne: "⊋", supplus: "⫀", supset: "⊃", supseteq: "⊇", supseteqq: "⫆", supsetneq: "⊋", supsetneqq: "⫌", supsim: "⫈", supsub: "⫔", supsup: "⫖", swArr: "⇙", swarhk: "⤦", swarr: "↙", swarrow: "↙", swnwar: "⤪", szli: "ß", szlig: "ß", target: "⌖", tau: "τ", tbrk: "⎴", tcaron: "ť", tcedil: "ţ", tcy: "т", tdot: "⃛", telrec: "⌕", tfr: "𝔱", there4: "∴", therefore: "∴", theta: "θ", thetasym: "ϑ", thetav: "ϑ", thickapprox: "≈", thicksim: "∼", thinsp: " ", thkap: "≈", thksim: "∼", thor: "þ", thorn: "þ", tilde: "˜", time: "×", times: "×", timesb: "⊠", timesbar: "⨱", timesd: "⨰", tint: "∭", toea: "⤨", top: "⊤", topbot: "⌶", topcir: "⫱", topf: "𝕥", topfork: "⫚", tosa: "⤩", tprime: "‴", trade: "™", triangle: "▵", triangledown: "▿", triangleleft: "◃", trianglelefteq: "⊴", triangleq: "≜", triangleright: "▹", trianglerighteq: "⊵", tridot: "◬", trie: "≜", triminus: "⨺", triplus: "⨹", trisb: "⧍", tritime: "⨻", trpezium: "⏢", tscr: "𝓉", tscy: "ц", tshcy: "ћ", tstrok: "ŧ", twixt: "≬", twoheadleftarrow: "↞", twoheadrightarrow: "↠", uArr: "⇑", uHar: "⥣", uacut: "ú", uacute: "ú", uarr: "↑", ubrcy: "ў", ubreve: "ŭ", ucir: "û", ucirc: "û", ucy: "у", udarr: "⇅", udblac: "ű", udhar: "⥮", ufisht: "⥾", ufr: "𝔲", ugrav: "ù", ugrave: "ù", uharl: "↿", uharr: "↾", uhblk: "▀", ulcorn: "⌜", ulcorner: "⌜", ulcrop: "⌏", ultri: "◸", umacr: "ū", um: "¨", uml: "¨", uogon: "ų", uopf: "𝕦", uparrow: "↑", updownarrow: "↕", upharpoonleft: "↿", upharpoonright: "↾", uplus: "⊎", upsi: "υ", upsih: "ϒ", upsilon: "υ", upuparrows: "⇈", urcorn: "⌝", urcorner: "⌝", urcrop: "⌎", uring: "ů", urtri: "◹", uscr: "𝓊", utdot: "⋰", utilde: "ũ", utri: "▵", utrif: "▴", uuarr: "⇈", uum: "ü", uuml: "ü", uwangle: "⦧", vArr: "⇕", vBar: "⫨", vBarv: "⫩", vDash: "⊨", vangrt: "⦜", varepsilon: "ϵ", varkappa: "ϰ", varnothing: "∅", varphi: "ϕ", varpi: "ϖ", varpropto: "∝", varr: "↕", varrho: "ϱ", varsigma: "ς", varsubsetneq: "⊊︀", varsubsetneqq: "⫋︀", varsupsetneq: "⊋︀", varsupsetneqq: "⫌︀", vartheta: "ϑ", vartriangleleft: "⊲", vartriangleright: "⊳", vcy: "в", vdash: "⊢", vee: "∨", veebar: "⊻", veeeq: "≚", vellip: "⋮", verbar: "|", vert: "|", vfr: "𝔳", vltri: "⊲", vnsub: "⊂⃒", vnsup: "⊃⃒", vopf: "𝕧", vprop: "∝", vrtri: "⊳", vscr: "𝓋", vsubnE: "⫋︀", vsubne: "⊊︀", vsupnE: "⫌︀", vsupne: "⊋︀", vzigzag: "⦚", wcirc: "ŵ", wedbar: "⩟", wedge: "∧", wedgeq: "≙", weierp: "℘", wfr: "𝔴", wopf: "𝕨", wp: "℘", wr: "≀", wreath: "≀", wscr: "𝓌", xcap: "⋂", xcirc: "◯", xcup: "⋃", xdtri: "▽", xfr: "𝔵", xhArr: "⟺", xharr: "⟷", xi: "ξ", xlArr: "⟸", xlarr: "⟵", xmap: "⟼", xnis: "⋻", xodot: "⨀", xopf: "𝕩", xoplus: "⨁", xotime: "⨂", xrArr: "⟹", xrarr: "⟶", xscr: "𝓍", xsqcup: "⨆", xuplus: "⨄", xutri: "△", xvee: "⋁", xwedge: "⋀", yacut: "ý", yacute: "ý", yacy: "я", ycirc: "ŷ", ycy: "ы", ye: "¥", yen: "¥", yfr: "𝔶", yicy: "ї", yopf: "𝕪", yscr: "𝓎", yucy: "ю", yum: "ÿ", yuml: "ÿ", zacute: "ź", zcaron: "ž", zcy: "з", zdot: "ż", zeetrf: "ℨ", zeta: "ζ", zfr: "𝔷", zhcy: "ж", zigrarr: "⇝", zopf: "𝕫", zscr: "𝓏", zwj: "‍", zwnj: "‌" };
});
var zu = C((wv, Uu) => {
  var Mu = Nu();
  Uu.exports = _D;
  var qD = {}.hasOwnProperty;
  function _D(e) {
    return qD.call(Mu, e) ? Mu[e] : false;
  }
});
var dr = C((kv, ea) => {
  var Gu = qu(), Yu = _u(), SD = Re(), PD = Ou(), Wu = Ru(), OD = zu();
  ea.exports = WD;
  var LD = {}.hasOwnProperty, Ke = String.fromCharCode, ID = Function.prototype, $u = { warning: null, reference: null, text: null, warningContext: null, referenceContext: null, textContext: null, position: {}, additional: null, attribute: false, nonTerminated: true }, RD = 9, Vu = 10, ND = 12, MD = 32, ju = 38, UD = 59, zD = 60, GD = 61, YD = 35, $D = 88, VD = 120, jD = 65533, Xe = "named", zt = "hexadecimal", Gt = "decimal", Yt = {};
  Yt[zt] = 16;
  Yt[Gt] = 10;
  var Vr = {};
  Vr[Xe] = Wu;
  Vr[Gt] = SD;
  Vr[zt] = PD;
  var Hu = 1, Ku = 2, Xu = 3, Ju = 4, Qu = 5, Ut = 6, Zu = 7, xe = {};
  xe[Hu] = "Named character references must be terminated by a semicolon";
  xe[Ku] = "Numeric character references must be terminated by a semicolon";
  xe[Xu] = "Named character references cannot be empty";
  xe[Ju] = "Numeric character references cannot be empty";
  xe[Qu] = "Named character references must be known";
  xe[Ut] = "Numeric character references cannot be disallowed";
  xe[Zu] = "Numeric character references cannot be outside the permissible Unicode range";
  function WD(e, r) {
    var t = {}, n, a;
    r || (r = {});
    for (a in $u) n = r[a], t[a] = n ?? $u[a];
    return (t.position.indent || t.position.start) && (t.indent = t.position.indent || [], t.position = t.position.start), HD(e, t);
  }
  function HD(e, r) {
    var t = r.additional, n = r.nonTerminated, a = r.text, i = r.reference, u = r.warning, o = r.textContext, s = r.referenceContext, l = r.warningContext, c = r.position, f = r.indent || [], p = e.length, d = 0, D = -1, h = c.column || 1, m = c.line || 1, F = "", A = [], v, B, b, g, y, x, E, w, k, T, q, N, P, S, _, O, Be, W, I;
    for (typeof t == "string" && (t = t.charCodeAt(0)), O = ee(), w = u ? Z : ID, d--, p++; ++d < p; ) if (y === Vu && (h = f[D] || 1), y = e.charCodeAt(d), y === ju) {
      if (E = e.charCodeAt(d + 1), E === RD || E === Vu || E === ND || E === MD || E === ju || E === zD || E !== E || t && E === t) {
        F += Ke(y), h++;
        continue;
      }
      for (P = d + 1, N = P, I = P, E === YD ? (I = ++N, E = e.charCodeAt(I), E === $D || E === VD ? (S = zt, I = ++N) : S = Gt) : S = Xe, v = "", q = "", g = "", _ = Vr[S], I--; ++I < p && (E = e.charCodeAt(I), !!_(E)); ) g += Ke(E), S === Xe && LD.call(Gu, g) && (v = g, q = Gu[g]);
      b = e.charCodeAt(I) === UD, b && (I++, B = S === Xe ? OD(g) : false, B && (v = g, q = B)), W = 1 + I - P, !b && !n || (g ? S === Xe ? (b && !q ? w(Qu, 1) : (v !== g && (I = N + v.length, W = 1 + I - N, b = false), b || (k = v ? Hu : Xu, r.attribute ? (E = e.charCodeAt(I), E === GD ? (w(k, W), q = null) : Wu(E) ? q = null : w(k, W)) : w(k, W))), x = q) : (b || w(Ku, W), x = parseInt(g, Yt[S]), KD(x) ? (w(Zu, W), x = Ke(jD)) : x in Yu ? (w(Ut, W), x = Yu[x]) : (T = "", XD(x) && w(Ut, W), x > 65535 && (x -= 65536, T += Ke(x >>> 10 | 55296), x = 56320 | x & 1023), x = T + Ke(x))) : S !== Xe && w(Ju, W)), x ? (Ee(), O = ee(), d = I - 1, h += I - P + 1, A.push(x), Be = ee(), Be.offset++, i && i.call(s, x, { start: O, end: Be }, e.slice(P - 1, I)), O = Be) : (g = e.slice(P - 1, I), F += g, h += g.length, d = I - 1);
    } else y === 10 && (m++, D++, h = 0), y === y ? (F += Ke(y), h++) : Ee();
    return A.join("");
    function ee() {
      return { line: m, column: h, offset: d + (c.offset || 0) };
    }
    function Z(ve, U) {
      var pt = ee();
      pt.column += U, pt.offset += U, u.call(l, xe[ve], pt, ve);
    }
    function Ee() {
      F && (A.push(F), a && a.call(o, F, { start: O, end: ee() }), F = "");
    }
  }
  function KD(e) {
    return e >= 55296 && e <= 57343 || e > 1114111;
  }
  function XD(e) {
    return e >= 1 && e <= 8 || e === 11 || e >= 13 && e <= 31 || e >= 127 && e <= 159 || e >= 64976 && e <= 65007 || (e & 65535) === 65535 || (e & 65535) === 65534;
  }
});
var na = C((Bv, ta) => {
  var JD = Ie(), ra = dr();
  ta.exports = QD;
  function QD(e) {
    return t.raw = n, t;
    function r(i) {
      for (var u = e.offset, o = i.line, s = []; ++o && o in u; ) s.push((u[o] || 0) + 1);
      return { start: i, indent: s };
    }
    function t(i, u, o) {
      ra(i, { position: r(u), warning: a, text: o, reference: o, textContext: e, referenceContext: e });
    }
    function n(i, u, o) {
      return ra(i, JD(o, { position: r(u), warning: a }));
    }
    function a(i, u, o) {
      o !== 3 && e.file.message(i, u);
    }
  }
});
var aa = C((Tv, ua) => {
  ua.exports = ZD;
  function ZD(e) {
    return r;
    function r(t, n) {
      var a = this, i = a.offset, u = [], o = a[e + "Methods"], s = a[e + "Tokenizers"], l = n.line, c = n.column, f, p, d, D, h, m;
      if (!t) return u;
      for (x.now = v, x.file = a.file, F(""); t; ) {
        for (f = -1, p = o.length, h = false; ++f < p && (D = o[f], d = s[D], !(d && (!d.onlyAtStart || a.atStart) && (!d.notInList || !a.inList) && (!d.notInBlock || !a.inBlock) && (!d.notInLink || !a.inLink) && (m = t.length, d.apply(a, [x, t]), h = m !== t.length, h))); ) ;
        h || a.file.fail(new Error("Infinite loop"), x.now());
      }
      return a.eof = v(), u;
      function F(E) {
        for (var w = -1, k = E.indexOf(`
`); k !== -1; ) l++, w = k, k = E.indexOf(`
`, k + 1);
        w === -1 ? c += E.length : c = E.length - w, l in i && (w !== -1 ? c += i[l] : c <= i[l] && (c = i[l] + 1));
      }
      function A() {
        var E = [], w = l + 1;
        return function() {
          for (var k = l + 1; w < k; ) E.push((i[w] || 0) + 1), w++;
          return E;
        };
      }
      function v() {
        var E = { line: l, column: c };
        return E.offset = a.toOffset(E), E;
      }
      function B(E) {
        this.start = E, this.end = v();
      }
      function b(E) {
        t.slice(0, E.length) !== E && a.file.fail(new Error("Incorrectly eaten value: please report this warning on https://git.io/vg5Ft"), v());
      }
      function g() {
        var E = v();
        return w;
        function w(k, T) {
          var q = k.position, N = q ? q.start : E, P = [], S = q && q.end.line, _ = E.line;
          if (k.position = new B(N), q && T && q.indent) {
            if (P = q.indent, S < _) {
              for (; ++S < _; ) P.push((i[S] || 0) + 1);
              P.push(E.column);
            }
            T = P.concat(T);
          }
          return k.position.indent = T || [], k;
        }
      }
      function y(E, w) {
        var k = w ? w.children : u, T = k[k.length - 1], q;
        return T && E.type === T.type && (E.type === "text" || E.type === "blockquote") && ia(T) && ia(E) && (q = E.type === "text" ? ep : rp, E = q.call(a, T, E)), E !== T && k.push(E), a.atStart && u.length !== 0 && a.exitStart(), E;
      }
      function x(E) {
        var w = A(), k = g(), T = v();
        return b(E), q.reset = N, N.test = P, q.test = P, t = t.slice(E.length), F(E), w = w(), q;
        function q(S, _) {
          return k(y(k(S), _), w);
        }
        function N() {
          var S = q.apply(null, arguments);
          return l = T.line, c = T.column, t = E + t, S;
        }
        function P() {
          var S = k({});
          return l = T.line, c = T.column, t = E + t, S.position;
        }
      }
    }
  }
  function ia(e) {
    var r, t;
    return e.type !== "text" || !e.position ? true : (r = e.position.start, t = e.position.end, r.line !== t.line || t.column - r.column === e.value.length);
  }
  function ep(e, r) {
    return e.value += r.value, e;
  }
  function rp(e, r) {
    return this.options.commonmark || this.options.gfm ? r : (e.children = e.children.concat(r.children), e);
  }
});
var ca = C((qv, sa) => {
  sa.exports = jr;
  var $t = ["\\", "`", "*", "{", "}", "[", "]", "(", ")", "#", "+", "-", ".", "!", "_", ">"], Vt = $t.concat(["~", "|"]), oa = Vt.concat([`
`, '"', "$", "%", "&", "'", ",", "/", ":", ";", "<", "=", "?", "@", "^"]);
  jr.default = $t;
  jr.gfm = Vt;
  jr.commonmark = oa;
  function jr(e) {
    var r = e || {};
    return r.commonmark ? oa : r.gfm ? Vt : $t;
  }
});
var fa = C((_v, la) => {
  la.exports = ["address", "article", "aside", "base", "basefont", "blockquote", "body", "caption", "center", "col", "colgroup", "dd", "details", "dialog", "dir", "div", "dl", "dt", "fieldset", "figcaption", "figure", "footer", "form", "frame", "frameset", "h1", "h2", "h3", "h4", "h5", "h6", "head", "header", "hgroup", "hr", "html", "iframe", "legend", "li", "link", "main", "menu", "menuitem", "meta", "nav", "noframes", "ol", "optgroup", "option", "p", "param", "pre", "section", "source", "title", "summary", "table", "tbody", "td", "tfoot", "th", "thead", "title", "tr", "track", "ul"];
});
var jt = C((Sv, Da) => {
  Da.exports = { position: true, gfm: true, commonmark: false, pedantic: false, blocks: fa() };
});
var ha = C((Pv, pa) => {
  var tp = Ie(), np = ca(), ip = jt();
  pa.exports = up;
  function up(e) {
    var r = this, t = r.options, n, a;
    if (e == null) e = {};
    else if (typeof e == "object") e = tp(e);
    else throw new Error("Invalid value `" + e + "` for setting `options`");
    for (n in ip) {
      if (a = e[n], a == null && (a = t[n]), n !== "blocks" && typeof a != "boolean" || n === "blocks" && typeof a != "object") throw new Error("Invalid value `" + a + "` for setting `options." + n + "`");
      e[n] = a;
    }
    return r.options = e, r.escape = np(e), r;
  }
});
var Fa = C((Ov, ma) => {
  ma.exports = da;
  function da(e) {
    if (e == null) return cp;
    if (typeof e == "string") return sp(e);
    if (typeof e == "object") return "length" in e ? op(e) : ap(e);
    if (typeof e == "function") return e;
    throw new Error("Expected function, string, or object as test");
  }
  function ap(e) {
    return r;
    function r(t) {
      var n;
      for (n in e) if (t[n] !== e[n]) return false;
      return true;
    }
  }
  function op(e) {
    for (var r = [], t = -1; ++t < e.length; ) r[t] = da(e[t]);
    return n;
    function n() {
      for (var a = -1; ++a < r.length; ) if (r[a].apply(this, arguments)) return true;
      return false;
    }
  }
  function sp(e) {
    return r;
    function r(t) {
      return !!(t && t.type === e);
    }
  }
  function cp() {
    return true;
  }
});
var Ea = C((Lv, ga) => {
  ga.exports = lp;
  function lp(e) {
    return e;
  }
});
var Aa = C((Iv, ba) => {
  ba.exports = Wr;
  var fp = Fa(), Dp = Ea(), va = true, Ca = "skip", Wt = false;
  Wr.CONTINUE = va;
  Wr.SKIP = Ca;
  Wr.EXIT = Wt;
  function Wr(e, r, t, n) {
    var a, i;
    typeof r == "function" && typeof t != "function" && (n = t, t = r, r = null), i = fp(r), a = n ? -1 : 1, u(e, null, [])();
    function u(o, s, l) {
      var c = typeof o == "object" && o !== null ? o : {}, f;
      return typeof c.type == "string" && (f = typeof c.tagName == "string" ? c.tagName : typeof c.name == "string" ? c.name : void 0, p.displayName = "node (" + Dp(c.type + (f ? "<" + f + ">" : "")) + ")"), p;
      function p() {
        var d = l.concat(o), D = [], h, m;
        if ((!r || i(o, s, l[l.length - 1] || null)) && (D = pp(t(o, l)), D[0] === Wt)) return D;
        if (o.children && D[0] !== Ca) for (m = (n ? o.children.length : -1) + a; m > -1 && m < o.children.length; ) {
          if (h = u(o.children[m], m, d)(), h[0] === Wt) return h;
          m = typeof h[1] == "number" ? h[1] : m + a;
        }
        return D;
      }
    }
  }
  function pp(e) {
    return e !== null && typeof e == "object" && "length" in e ? e : typeof e == "number" ? [va, e] : [e];
  }
});
var xa = C((Rv, ya) => {
  ya.exports = Kr;
  var Hr = Aa(), hp = Hr.CONTINUE, dp = Hr.SKIP, mp = Hr.EXIT;
  Kr.CONTINUE = hp;
  Kr.SKIP = dp;
  Kr.EXIT = mp;
  function Kr(e, r, t, n) {
    typeof r == "function" && typeof t != "function" && (n = t, t = r, r = null), Hr(e, r, a, n);
    function a(i, u) {
      var o = u[u.length - 1], s = o ? o.children.indexOf(i) : null;
      return t(i, s, o);
    }
  }
});
var ka = C((Nv, wa) => {
  var Fp = xa();
  wa.exports = gp;
  function gp(e, r) {
    return Fp(e, r ? Ep : vp), e;
  }
  function Ep(e) {
    delete e.position;
  }
  function vp(e) {
    e.position = void 0;
  }
});
var qa = C((Mv, Ta) => {
  var Ba = Ie(), Cp = ka();
  Ta.exports = yp;
  var bp = `
`, Ap = /\r\n|\r/g;
  function yp() {
    var e = this, r = String(e.file), t = { line: 1, column: 1, offset: 0 }, n = Ba(t), a;
    return r = r.replace(Ap, bp), r.charCodeAt(0) === 65279 && (r = r.slice(1), n.column++, n.offset++), a = { type: "root", children: e.tokenizeBlock(r, n), position: { start: t, end: e.eof || Ba(t) } }, e.options.position || Cp(a, true), a;
  }
});
var Sa = C((Uv, _a) => {
  var xp = /^[ \t]*(\n|$)/;
  _a.exports = wp;
  function wp(e, r, t) {
    for (var n, a = "", i = 0, u = r.length; i < u && (n = xp.exec(r.slice(i)), n != null); ) i += n[0].length, a += n[0];
    if (a !== "") {
      if (t) return true;
      e(a);
    }
  }
});
var Xr = C((zv, Pa) => {
  var me = "", Ht;
  Pa.exports = kp;
  function kp(e, r) {
    if (typeof e != "string") throw new TypeError("expected a string");
    if (r === 1) return e;
    if (r === 2) return e + e;
    var t = e.length * r;
    if (Ht !== e || typeof Ht > "u") Ht = e, me = "";
    else if (me.length >= t) return me.substr(0, t);
    for (; t > me.length && r > 1; ) r & 1 && (me += e), r >>= 1, e += e;
    return me += e, me = me.substr(0, t), me;
  }
});
var Kt = C((Gv, Oa) => {
  Oa.exports = Bp;
  function Bp(e) {
    return String(e).replace(/\n+$/, "");
  }
});
var Ra = C((Yv, Ia) => {
  var Tp = Xr(), qp = Kt();
  Ia.exports = Pp;
  var Xt = `
`, La = "	", Jt = " ", _p = 4, Sp = Tp(Jt, _p);
  function Pp(e, r, t) {
    for (var n = -1, a = r.length, i = "", u = "", o = "", s = "", l, c, f; ++n < a; ) if (l = r.charAt(n), f) if (f = false, i += o, u += s, o = "", s = "", l === Xt) o = l, s = l;
    else for (i += l, u += l; ++n < a; ) {
      if (l = r.charAt(n), !l || l === Xt) {
        s = l, o = l;
        break;
      }
      i += l, u += l;
    }
    else if (l === Jt && r.charAt(n + 1) === l && r.charAt(n + 2) === l && r.charAt(n + 3) === l) o += Sp, n += 3, f = true;
    else if (l === La) o += l, f = true;
    else {
      for (c = ""; l === La || l === Jt; ) c += l, l = r.charAt(++n);
      if (l !== Xt) break;
      o += c + l, s += l;
    }
    if (u) return t ? true : e(i)({ type: "code", lang: null, meta: null, value: qp(u) });
  }
});
var Ua = C(($v, Ma) => {
  Ma.exports = Rp;
  var Jr = `
`, mr = "	", Je = " ", Op = "~", Na = "`", Lp = 3, Ip = 4;
  function Rp(e, r, t) {
    var n = this, a = n.options.gfm, i = r.length + 1, u = 0, o = "", s, l, c, f, p, d, D, h, m, F, A, v, B;
    if (a) {
      for (; u < i && (c = r.charAt(u), !(c !== Je && c !== mr)); ) o += c, u++;
      if (v = u, c = r.charAt(u), !(c !== Op && c !== Na)) {
        for (u++, l = c, s = 1, o += c; u < i && (c = r.charAt(u), c === l); ) o += c, s++, u++;
        if (!(s < Lp)) {
          for (; u < i && (c = r.charAt(u), !(c !== Je && c !== mr)); ) o += c, u++;
          for (f = "", D = ""; u < i && (c = r.charAt(u), !(c === Jr || l === Na && c === l)); ) c === Je || c === mr ? D += c : (f += D + c, D = ""), u++;
          if (c = r.charAt(u), !(c && c !== Jr)) {
            if (t) return true;
            B = e.now(), B.column += o.length, B.offset += o.length, o += f, f = n.decode.raw(n.unescape(f), B), D && (o += D), D = "", F = "", A = "", h = "", m = "";
            for (var b = true; u < i; ) {
              if (c = r.charAt(u), h += F, m += A, F = "", A = "", c !== Jr) {
                h += c, A += c, u++;
                continue;
              }
              for (b ? (o += c, b = false) : (F += c, A += c), D = "", u++; u < i && (c = r.charAt(u), c === Je); ) D += c, u++;
              if (F += D, A += D.slice(v), !(D.length >= Ip)) {
                for (D = ""; u < i && (c = r.charAt(u), c === l); ) D += c, u++;
                if (F += D, A += D, !(D.length < s)) {
                  for (D = ""; u < i && (c = r.charAt(u), !(c !== Je && c !== mr)); ) F += c, A += c, u++;
                  if (!c || c === Jr) break;
                }
              }
            }
            for (o += h + F, u = -1, i = f.length; ++u < i; ) if (c = f.charAt(u), c === Je || c === mr) p || (p = f.slice(0, u));
            else if (p) {
              d = f.slice(u);
              break;
            }
            return e(o)({ type: "code", lang: p || f || null, meta: d || null, value: m });
          }
        }
      }
    }
  }
});
var Ne = C((Qe, za) => {
  Qe = za.exports = Np;
  function Np(e) {
    return e.trim ? e.trim() : Qe.right(Qe.left(e));
  }
  Qe.left = function(e) {
    return e.trimLeft ? e.trimLeft() : e.replace(/^\s\s*/, "");
  };
  Qe.right = function(e) {
    if (e.trimRight) return e.trimRight();
    for (var r = /\s/, t = e.length; r.test(e.charAt(--t)); ) ;
    return e.slice(0, t + 1);
  };
});
var Qr = C((Vv, Ga) => {
  Ga.exports = Mp;
  function Mp(e, r, t, n) {
    for (var a = e.length, i = -1, u, o; ++i < a; ) if (u = e[i], o = u[1] || {}, !(o.pedantic !== void 0 && o.pedantic !== t.options.pedantic) && !(o.commonmark !== void 0 && o.commonmark !== t.options.commonmark) && r[u[0]].apply(t, n)) return true;
    return false;
  }
});
var ja = C((jv, Va) => {
  var Up = Ne(), zp = Qr();
  Va.exports = Gp;
  var Qt = `
`, Ya = "	", Zt = " ", $a = ">";
  function Gp(e, r, t) {
    for (var n = this, a = n.offset, i = n.blockTokenizers, u = n.interruptBlockquote, o = e.now(), s = o.line, l = r.length, c = [], f = [], p = [], d, D = 0, h, m, F, A, v, B, b, g; D < l && (h = r.charAt(D), !(h !== Zt && h !== Ya)); ) D++;
    if (r.charAt(D) === $a) {
      if (t) return true;
      for (D = 0; D < l; ) {
        for (F = r.indexOf(Qt, D), B = D, b = false, F === -1 && (F = l); D < l && (h = r.charAt(D), !(h !== Zt && h !== Ya)); ) D++;
        if (r.charAt(D) === $a ? (D++, b = true, r.charAt(D) === Zt && D++) : D = B, A = r.slice(D, F), !b && !Up(A)) {
          D = B;
          break;
        }
        if (!b && (m = r.slice(D), zp(u, i, n, [e, m, true]))) break;
        v = B === D ? A : r.slice(B, F), p.push(D - B), c.push(v), f.push(A), D = F + 1;
      }
      for (D = -1, l = p.length, d = e(c.join(Qt)); ++D < l; ) a[s] = (a[s] || 0) + p[D], s++;
      return g = n.enterBlock(), f = n.tokenizeBlock(f.join(Qt), o), g(), d({ type: "blockquote", children: f });
    }
  }
});
var Ka = C((Wv, Ha) => {
  Ha.exports = $p;
  var Wa = `
`, Fr = "	", gr = " ", Er = "#", Yp = 6;
  function $p(e, r, t) {
    for (var n = this, a = n.options.pedantic, i = r.length + 1, u = -1, o = e.now(), s = "", l = "", c, f, p; ++u < i; ) {
      if (c = r.charAt(u), c !== gr && c !== Fr) {
        u--;
        break;
      }
      s += c;
    }
    for (p = 0; ++u <= i; ) {
      if (c = r.charAt(u), c !== Er) {
        u--;
        break;
      }
      s += c, p++;
    }
    if (!(p > Yp) && !(!p || !a && r.charAt(u + 1) === Er)) {
      for (i = r.length + 1, f = ""; ++u < i; ) {
        if (c = r.charAt(u), c !== gr && c !== Fr) {
          u--;
          break;
        }
        f += c;
      }
      if (!(!a && f.length === 0 && c && c !== Wa)) {
        if (t) return true;
        for (s += f, f = "", l = ""; ++u < i && (c = r.charAt(u), !(!c || c === Wa)); ) {
          if (c !== gr && c !== Fr && c !== Er) {
            l += f + c, f = "";
            continue;
          }
          for (; c === gr || c === Fr; ) f += c, c = r.charAt(++u);
          if (!a && l && !f && c === Er) {
            l += c;
            continue;
          }
          for (; c === Er; ) f += c, c = r.charAt(++u);
          for (; c === gr || c === Fr; ) f += c, c = r.charAt(++u);
          u--;
        }
        return o.column += s.length, o.offset += s.length, s += l + f, e(s)({ type: "heading", depth: p, children: n.tokenizeInline(l, o) });
      }
    }
  }
});
var Qa = C((Hv, Ja) => {
  Ja.exports = Jp;
  var Vp = "	", jp = `
`, Xa = " ", Wp = "*", Hp = "-", Kp = "_", Xp = 3;
  function Jp(e, r, t) {
    for (var n = -1, a = r.length + 1, i = "", u, o, s, l; ++n < a && (u = r.charAt(n), !(u !== Vp && u !== Xa)); ) i += u;
    if (!(u !== Wp && u !== Hp && u !== Kp)) for (o = u, i += u, s = 1, l = ""; ++n < a; ) if (u = r.charAt(n), u === o) s++, i += l + o, l = "";
    else if (u === Xa) l += u;
    else return s >= Xp && (!u || u === jp) ? (i += l, t ? true : e(i)({ type: "thematicBreak" })) : void 0;
  }
});
var en = C((Kv, eo) => {
  eo.exports = rh;
  var Za = "	", Qp = " ", Zp = 1, eh = 4;
  function rh(e) {
    for (var r = 0, t = 0, n = e.charAt(r), a = {}, i, u = 0; n === Za || n === Qp; ) {
      for (i = n === Za ? eh : Zp, t += i, i > 1 && (t = Math.floor(t / i) * i); u < t; ) a[++u] = r;
      n = e.charAt(++r);
    }
    return { indent: t, stops: a };
  }
});
var no = C((Xv, to) => {
  var th = Ne(), nh = Xr(), ih = en();
  to.exports = oh;
  var ro = `
`, uh = " ", ah = "!";
  function oh(e, r) {
    var t = e.split(ro), n = t.length + 1, a = 1 / 0, i = [], u, o, s;
    for (t.unshift(nh(uh, r) + ah); n--; ) if (o = ih(t[n]), i[n] = o.stops, th(t[n]).length !== 0) if (o.indent) o.indent > 0 && o.indent < a && (a = o.indent);
    else {
      a = 1 / 0;
      break;
    }
    if (a !== 1 / 0) for (n = t.length; n--; ) {
      for (s = i[n], u = a; u && !(u in s); ) u--;
      t[n] = t[n].slice(s[u] + 1);
    }
    return t.shift(), t.join(ro);
  }
});
var co = C((Jv, so) => {
  var sh = Ne(), ch = Xr(), io = Re(), lh = en(), fh = no(), Dh = Qr();
  so.exports = vh;
  var rn = "*", ph = "_", uo = "+", tn = "-", ao = ".", Fe = " ", ae = `
`, Zr = "	", oo = ")", hh = "x", we = 4, dh = /\n\n(?!\s*$)/, mh = /^\[([ X\tx])][ \t]/, Fh = /^([ \t]*)([*+-]|\d+[.)])( {1,4}(?! )| |\t|$|(?=\n))([^\n]*)/, gh = /^([ \t]*)([*+-]|\d+[.)])([ \t]+)/, Eh = /^( {1,4}|\t)?/gm;
  function vh(e, r, t) {
    for (var n = this, a = n.options.commonmark, i = n.options.pedantic, u = n.blockTokenizers, o = n.interruptList, s = 0, l = r.length, c = null, f, p, d, D, h, m, F, A, v, B, b, g, y, x, E, w, k, T, q, N = false, P, S, _, O; s < l && (D = r.charAt(s), !(D !== Zr && D !== Fe)); ) s++;
    if (D = r.charAt(s), D === rn || D === uo || D === tn) h = D, d = false;
    else {
      for (d = true, p = ""; s < l && (D = r.charAt(s), !!io(D)); ) p += D, s++;
      if (D = r.charAt(s), !p || !(D === ao || a && D === oo) || t && p !== "1") return;
      c = parseInt(p, 10), h = D;
    }
    if (D = r.charAt(++s), !(D !== Fe && D !== Zr && (i || D !== ae && D !== ""))) {
      if (t) return true;
      for (s = 0, x = [], E = [], w = []; s < l; ) {
        for (m = r.indexOf(ae, s), F = s, A = false, O = false, m === -1 && (m = l), f = 0; s < l; ) {
          if (D = r.charAt(s), D === Zr) f += we - f % we;
          else if (D === Fe) f++;
          else break;
          s++;
        }
        if (k && f >= k.indent && (O = true), D = r.charAt(s), v = null, !O) {
          if (D === rn || D === uo || D === tn) v = D, s++, f++;
          else {
            for (p = ""; s < l && (D = r.charAt(s), !!io(D)); ) p += D, s++;
            D = r.charAt(s), s++, p && (D === ao || a && D === oo) && (v = D, f += p.length + 1);
          }
          if (v) if (D = r.charAt(s), D === Zr) f += we - f % we, s++;
          else if (D === Fe) {
            for (_ = s + we; s < _ && r.charAt(s) === Fe; ) s++, f++;
            s === _ && r.charAt(s) === Fe && (s -= we - 1, f -= we - 1);
          } else D !== ae && D !== "" && (v = null);
        }
        if (v) {
          if (!i && h !== v) break;
          A = true;
        } else !a && !O && r.charAt(F) === Fe ? O = true : a && k && (O = f >= k.indent || f > we), A = false, s = F;
        if (b = r.slice(F, m), B = F === s ? b : r.slice(s, m), (v === rn || v === ph || v === tn) && u.thematicBreak.call(n, e, b, true)) break;
        if (g = y, y = !A && !sh(B).length, O && k) k.value = k.value.concat(w, b), E = E.concat(w, b), w = [];
        else if (A) w.length !== 0 && (N = true, k.value.push(""), k.trail = w.concat()), k = { value: [b], indent: f, trail: [] }, x.push(k), E = E.concat(w, b), w = [];
        else if (y) {
          if (g && !a) break;
          w.push(b);
        } else {
          if (g || Dh(o, u, n, [e, b, true])) break;
          k.value = k.value.concat(w, b), E = E.concat(w, b), w = [];
        }
        s = m + 1;
      }
      for (P = e(E.join(ae)).reset({ type: "list", ordered: d, start: c, spread: N, children: [] }), T = n.enterList(), q = n.enterBlock(), s = -1, l = x.length; ++s < l; ) k = x[s].value.join(ae), S = e.now(), e(k)(Ch(n, k, S), P), k = x[s].trail.join(ae), s !== l - 1 && (k += ae), e(k);
      return T(), q(), P;
    }
  }
  function Ch(e, r, t) {
    var n = e.offset, a = e.options.pedantic ? bh : Ah, i = null, u, o;
    return r = a.apply(null, arguments), e.options.gfm && (u = r.match(mh), u && (o = u[0].length, i = u[1].toLowerCase() === hh, n[t.line] += o, r = r.slice(o))), { type: "listItem", spread: dh.test(r), checked: i, children: e.tokenizeBlock(r, t) };
  }
  function bh(e, r, t) {
    var n = e.offset, a = t.line;
    return r = r.replace(gh, i), a = t.line, r.replace(Eh, i);
    function i(u) {
      return n[a] = (n[a] || 0) + u.length, a++, "";
    }
  }
  function Ah(e, r, t) {
    var n = e.offset, a = t.line, i, u, o, s, l, c, f;
    for (r = r.replace(Fh, p), s = r.split(ae), l = fh(r, lh(i).indent).split(ae), l[0] = o, n[a] = (n[a] || 0) + u.length, a++, c = 0, f = s.length; ++c < f; ) n[a] = (n[a] || 0) + s[c].length - l[c].length, a++;
    return l.join(ae);
    function p(d, D, h, m, F) {
      return u = D + h + m, o = F, Number(h) < 10 && u.length % 2 === 1 && (h = Fe + h), i = D + ch(Fe, h.length) + m, i + o;
    }
  }
});
var po = C((Qv, Do) => {
  Do.exports = Th;
  var nn = `
`, yh = "	", lo = " ", fo = "=", xh = "-", wh = 3, kh = 1, Bh = 2;
  function Th(e, r, t) {
    for (var n = this, a = e.now(), i = r.length, u = -1, o = "", s, l, c, f, p; ++u < i; ) {
      if (c = r.charAt(u), c !== lo || u >= wh) {
        u--;
        break;
      }
      o += c;
    }
    for (s = "", l = ""; ++u < i; ) {
      if (c = r.charAt(u), c === nn) {
        u--;
        break;
      }
      c === lo || c === yh ? l += c : (s += l + c, l = "");
    }
    if (a.column += o.length, a.offset += o.length, o += s + l, c = r.charAt(++u), f = r.charAt(++u), !(c !== nn || f !== fo && f !== xh)) {
      for (o += c, l = f, p = f === fo ? kh : Bh; ++u < i; ) {
        if (c = r.charAt(u), c !== f) {
          if (c !== nn) return;
          u--;
          break;
        }
        l += c;
      }
      return t ? true : e(o + l)({ type: "heading", depth: p, children: n.tokenizeInline(s, a) });
    }
  }
});
var an = C((un) => {
  var qh = "[a-zA-Z_:][a-zA-Z0-9:._-]*", _h = "[^\"'=<>`\\u0000-\\u0020]+", Sh = "'[^']*'", Ph = '"[^"]*"', Oh = "(?:" + _h + "|" + Sh + "|" + Ph + ")", Lh = "(?:\\s+" + qh + "(?:\\s*=\\s*" + Oh + ")?)", ho = "<[A-Za-z][A-Za-z0-9\\-]*" + Lh + "*\\s*\\/?>", mo = "<\\/[A-Za-z][A-Za-z0-9\\-]*\\s*>", Ih = "<!---->|<!--(?:-?[^>-])(?:-?[^-])*-->", Rh = "<[?].*?[?]>", Nh = "<![A-Za-z]+\\s+[^>]*>", Mh = "<!\\[CDATA\\[[\\s\\S]*?\\]\\]>";
  un.openCloseTag = new RegExp("^(?:" + ho + "|" + mo + ")");
  un.tag = new RegExp("^(?:" + ho + "|" + mo + "|" + Ih + "|" + Rh + "|" + Nh + "|" + Mh + ")");
});
var vo = C((eC, Eo) => {
  var Uh = an().openCloseTag;
  Eo.exports = rd;
  var zh = "	", Gh = " ", Fo = `
`, Yh = "<", $h = /^<(script|pre|style)(?=(\s|>|$))/i, Vh = /<\/(script|pre|style)>/i, jh = /^<!--/, Wh = /-->/, Hh = /^<\?/, Kh = /\?>/, Xh = /^<![A-Za-z]/, Jh = />/, Qh = /^<!\[CDATA\[/, Zh = /]]>/, go = /^$/, ed = new RegExp(Uh.source + "\\s*$");
  function rd(e, r, t) {
    for (var n = this, a = n.options.blocks.join("|"), i = new RegExp("^</?(" + a + ")(?=(\\s|/?>|$))", "i"), u = r.length, o = 0, s, l, c, f, p, d, D, h = [[$h, Vh, true], [jh, Wh, true], [Hh, Kh, true], [Xh, Jh, true], [Qh, Zh, true], [i, go, true], [ed, go, false]]; o < u && (f = r.charAt(o), !(f !== zh && f !== Gh)); ) o++;
    if (r.charAt(o) === Yh) {
      for (s = r.indexOf(Fo, o + 1), s = s === -1 ? u : s, l = r.slice(o, s), c = -1, p = h.length; ++c < p; ) if (h[c][0].test(l)) {
        d = h[c];
        break;
      }
      if (d) {
        if (t) return d[2];
        if (o = s, !d[1].test(l)) for (; o < u; ) {
          if (s = r.indexOf(Fo, o + 1), s = s === -1 ? u : s, l = r.slice(o + 1, s), d[1].test(l)) {
            l && (o = s);
            break;
          }
          o = s;
        }
        return D = r.slice(0, o), e(D)({ type: "html", value: D });
      }
    }
  }
});
var oe = C((rC, Co) => {
  Co.exports = id;
  var td = String.fromCharCode, nd = /\s/;
  function id(e) {
    return nd.test(typeof e == "number" ? td(e) : e.charAt(0));
  }
});
var on = C((tC, bo) => {
  var ud = kr();
  bo.exports = ad;
  function ad(e) {
    return ud(e).toLowerCase();
  }
});
var To = C((nC, Bo) => {
  var od = oe(), sd = on();
  Bo.exports = Dd;
  var Ao = '"', yo = "'", cd = "\\", Ze = `
`, et = "	", rt = " ", cn = "[", vr = "]", ld = "(", fd = ")", xo = ":", wo = "<", ko = ">";
  function Dd(e, r, t) {
    for (var n = this, a = n.options.commonmark, i = 0, u = r.length, o = "", s, l, c, f, p, d, D, h; i < u && (f = r.charAt(i), !(f !== rt && f !== et)); ) o += f, i++;
    if (f = r.charAt(i), f === cn) {
      for (i++, o += f, c = ""; i < u && (f = r.charAt(i), f !== vr); ) f === cd && (c += f, i++, f = r.charAt(i)), c += f, i++;
      if (!(!c || r.charAt(i) !== vr || r.charAt(i + 1) !== xo)) {
        for (d = c, o += c + vr + xo, i = o.length, c = ""; i < u && (f = r.charAt(i), !(f !== et && f !== rt && f !== Ze)); ) o += f, i++;
        if (f = r.charAt(i), c = "", s = o, f === wo) {
          for (i++; i < u && (f = r.charAt(i), !!sn(f)); ) c += f, i++;
          if (f = r.charAt(i), f === sn.delimiter) o += wo + c + f, i++;
          else {
            if (a) return;
            i -= c.length + 1, c = "";
          }
        }
        if (!c) {
          for (; i < u && (f = r.charAt(i), !!pd(f)); ) c += f, i++;
          o += c;
        }
        if (c) {
          for (D = c, c = ""; i < u && (f = r.charAt(i), !(f !== et && f !== rt && f !== Ze)); ) c += f, i++;
          if (f = r.charAt(i), p = null, f === Ao ? p = Ao : f === yo ? p = yo : f === ld && (p = fd), !p) c = "", i = o.length;
          else if (c) {
            for (o += c + f, i = o.length, c = ""; i < u && (f = r.charAt(i), f !== p); ) {
              if (f === Ze) {
                if (i++, f = r.charAt(i), f === Ze || f === p) return;
                c += Ze;
              }
              c += f, i++;
            }
            if (f = r.charAt(i), f !== p) return;
            l = o, o += c + f, i++, h = c, c = "";
          } else return;
          for (; i < u && (f = r.charAt(i), !(f !== et && f !== rt)); ) o += f, i++;
          if (f = r.charAt(i), !f || f === Ze) return t ? true : (s = e(s).test().end, D = n.decode.raw(n.unescape(D), s, { nonTerminated: false }), h && (l = e(l).test().end, h = n.decode.raw(n.unescape(h), l)), e(o)({ type: "definition", identifier: sd(d), label: d, title: h || null, url: D }));
        }
      }
    }
  }
  function sn(e) {
    return e !== ko && e !== cn && e !== vr;
  }
  sn.delimiter = ko;
  function pd(e) {
    return e !== cn && e !== vr && !od(e);
  }
});
var So = C((iC, _o) => {
  var hd = oe();
  _o.exports = yd;
  var dd = "	", tt = `
`, md = " ", Fd = "-", gd = ":", Ed = "\\", ln = "|", vd = 1, Cd = 2, qo = "left", bd = "center", Ad = "right";
  function yd(e, r, t) {
    var n = this, a, i, u, o, s, l, c, f, p, d, D, h, m, F, A, v, B, b, g, y, x, E;
    if (n.options.gfm) {
      for (a = 0, v = 0, l = r.length + 1, c = []; a < l; ) {
        if (y = r.indexOf(tt, a), x = r.indexOf(ln, a + 1), y === -1 && (y = r.length), x === -1 || x > y) {
          if (v < Cd) return;
          break;
        }
        c.push(r.slice(a, y)), v++, a = y + 1;
      }
      for (o = c.join(tt), i = c.splice(1, 1)[0] || [], a = 0, l = i.length, v--, u = false, D = []; a < l; ) {
        if (p = i.charAt(a), p === ln) {
          if (d = null, u === false) {
            if (E === false) return;
          } else D.push(u), u = false;
          E = false;
        } else if (p === Fd) d = true, u = u || null;
        else if (p === gd) u === qo ? u = bd : d && u === null ? u = Ad : u = qo;
        else if (!hd(p)) return;
        a++;
      }
      if (u !== false && D.push(u), !(D.length < vd)) {
        if (t) return true;
        for (A = -1, b = [], g = e(o).reset({ type: "table", align: D, children: b }); ++A < v; ) {
          for (B = c[A], s = { type: "tableRow", children: [] }, A && e(tt), e(B).reset(s, g), l = B.length + 1, a = 0, f = "", h = "", m = true; a < l; ) {
            if (p = B.charAt(a), p === dd || p === md) {
              h ? f += p : e(p), a++;
              continue;
            }
            p === "" || p === ln ? m ? e(p) : ((h || p) && !m && (o = h, f.length > 1 && (p ? (o += f.slice(0, -1), f = f.charAt(f.length - 1)) : (o += f, f = "")), F = e.now(), e(o)({ type: "tableCell", children: n.tokenizeInline(h, F) }, s)), e(f + p), f = "", h = "") : (f && (h += f, f = ""), h += p, p === Ed && a !== l - 2 && (h += B.charAt(a + 1), a++)), m = false, a++;
          }
          A || e(tt + i);
        }
        return g;
      }
    }
  }
});
var Lo = C((uC, Oo) => {
  var xd = Ne(), wd = Kt(), kd = Qr();
  Oo.exports = qd;
  var Bd = "	", Cr = `
`, Td = " ", Po = 4;
  function qd(e, r, t) {
    for (var n = this, a = n.options, i = a.commonmark, u = n.blockTokenizers, o = n.interruptParagraph, s = r.indexOf(Cr), l = r.length, c, f, p, d, D; s < l; ) {
      if (s === -1) {
        s = l;
        break;
      }
      if (r.charAt(s + 1) === Cr) break;
      if (i) {
        for (d = 0, c = s + 1; c < l; ) {
          if (p = r.charAt(c), p === Bd) {
            d = Po;
            break;
          } else if (p === Td) d++;
          else break;
          c++;
        }
        if (d >= Po && p !== Cr) {
          s = r.indexOf(Cr, s + 1);
          continue;
        }
      }
      if (f = r.slice(s + 1), kd(o, u, n, [e, f, true])) break;
      if (c = s, s = r.indexOf(Cr, s + 1), s !== -1 && xd(r.slice(c, s)) === "") {
        s = c;
        break;
      }
    }
    return f = r.slice(0, s), t ? true : (D = e.now(), f = wd(f), e(f)({ type: "paragraph", children: n.tokenizeInline(f, D) }));
  }
});
var Ro = C((aC, Io) => {
  Io.exports = _d;
  function _d(e, r) {
    return e.indexOf("\\", r);
  }
});
var zo = C((oC, Uo) => {
  var Sd = Ro();
  Uo.exports = Mo;
  Mo.locator = Sd;
  var Pd = `
`, No = "\\";
  function Mo(e, r, t) {
    var n = this, a, i;
    if (r.charAt(0) === No && (a = r.charAt(1), n.escape.indexOf(a) !== -1)) return t ? true : (a === Pd ? i = { type: "break" } : i = { type: "text", value: a }, e(No + a)(i));
  }
});
var fn = C((sC, Go) => {
  Go.exports = Od;
  function Od(e, r) {
    return e.indexOf("<", r);
  }
});
var Wo = C((cC, jo) => {
  var Yo = oe(), Ld = dr(), Id = fn();
  jo.exports = dn;
  dn.locator = Id;
  dn.notInLink = true;
  var $o = "<", Dn = ">", Vo = "@", pn = "/", hn = "mailto:", nt = hn.length;
  function dn(e, r, t) {
    var n = this, a = "", i = r.length, u = 0, o = "", s = false, l = "", c, f, p, d, D;
    if (r.charAt(0) === $o) {
      for (u++, a = $o; u < i && (c = r.charAt(u), !(Yo(c) || c === Dn || c === Vo || c === ":" && r.charAt(u + 1) === pn)); ) o += c, u++;
      if (o) {
        if (l += o, o = "", c = r.charAt(u), l += c, u++, c === Vo) s = true;
        else {
          if (c !== ":" || r.charAt(u + 1) !== pn) return;
          l += pn, u++;
        }
        for (; u < i && (c = r.charAt(u), !(Yo(c) || c === Dn)); ) o += c, u++;
        if (c = r.charAt(u), !(!o || c !== Dn)) return t ? true : (l += o, p = l, a += l + c, f = e.now(), f.column++, f.offset++, s && (l.slice(0, nt).toLowerCase() === hn ? (p = p.slice(nt), f.column += nt, f.offset += nt) : l = hn + l), d = n.inlineTokenizers, n.inlineTokenizers = { text: d.text }, D = n.enterLink(), p = n.tokenizeInline(p, f), n.inlineTokenizers = d, D(), e(a)({ type: "link", title: null, url: Ld(l, { nonTerminated: false }), children: p }));
      }
    }
  }
});
var Ko = C((lC, Ho) => {
  Ho.exports = Rd;
  function Rd(e, r) {
    var t = String(e), n = 0, a;
    if (typeof r != "string") throw new Error("Expected character");
    for (a = t.indexOf(r); a !== -1; ) n++, a = t.indexOf(r, a + r.length);
    return n;
  }
});
var Qo = C((fC, Jo) => {
  Jo.exports = Nd;
  var Xo = ["www.", "http://", "https://"];
  function Nd(e, r) {
    var t = -1, n, a, i;
    if (!this.options.gfm) return t;
    for (a = Xo.length, n = -1; ++n < a; ) i = e.indexOf(Xo[n], r), i !== -1 && (t === -1 || i < t) && (t = i);
    return t;
  }
});
var ns = C((DC, ts) => {
  var Zo = Ko(), Md = dr(), Ud = Re(), mn = He(), zd = oe(), Gd = Qo();
  ts.exports = gn;
  gn.locator = Gd;
  gn.notInLink = true;
  var Yd = 33, $d = 38, Vd = 41, jd = 42, Wd = 44, Hd = 45, Fn = 46, Kd = 58, Xd = 59, Jd = 63, Qd = 60, es = 95, Zd = 126, e0 = "(", rs = ")";
  function gn(e, r, t) {
    var n = this, a = n.options.gfm, i = n.inlineTokenizers, u = r.length, o = -1, s = false, l, c, f, p, d, D, h, m, F, A, v, B, b, g;
    if (a) {
      if (r.slice(0, 4) === "www.") s = true, p = 4;
      else if (r.slice(0, 7).toLowerCase() === "http://") p = 7;
      else if (r.slice(0, 8).toLowerCase() === "https://") p = 8;
      else return;
      for (o = p - 1, f = p, l = []; p < u; ) {
        if (h = r.charCodeAt(p), h === Fn) {
          if (o === p - 1) break;
          l.push(p), o = p, p++;
          continue;
        }
        if (Ud(h) || mn(h) || h === Hd || h === es) {
          p++;
          continue;
        }
        break;
      }
      if (h === Fn && (l.pop(), p--), l[0] !== void 0 && (c = l.length < 2 ? f : l[l.length - 2] + 1, r.slice(c, p).indexOf("_") === -1)) {
        if (t) return true;
        for (m = p, d = p; p < u && (h = r.charCodeAt(p), !(zd(h) || h === Qd)); ) p++, h === Yd || h === jd || h === Wd || h === Fn || h === Kd || h === Jd || h === es || h === Zd || (m = p);
        if (p = m, r.charCodeAt(p - 1) === Vd) for (D = r.slice(d, p), F = Zo(D, e0), A = Zo(D, rs); A > F; ) p = d + D.lastIndexOf(rs), D = r.slice(d, p), A--;
        if (r.charCodeAt(p - 1) === Xd && (p--, mn(r.charCodeAt(p - 1)))) {
          for (m = p - 2; mn(r.charCodeAt(m)); ) m--;
          r.charCodeAt(m) === $d && (p = m);
        }
        return v = r.slice(0, p), b = Md(v, { nonTerminated: false }), s && (b = "http://" + b), g = n.enterLink(), n.inlineTokenizers = { text: i.text }, B = n.tokenizeInline(v, e.now()), n.inlineTokenizers = i, g(), e(v)({ type: "link", title: null, url: b, children: B });
      }
    }
  }
});
var os = C((pC, as) => {
  var r0 = Re(), t0 = He(), n0 = 43, i0 = 45, u0 = 46, a0 = 95;
  as.exports = us;
  function us(e, r) {
    var t = this, n, a;
    if (!this.options.gfm || (n = e.indexOf("@", r), n === -1)) return -1;
    if (a = n, a === r || !is(e.charCodeAt(a - 1))) return us.call(t, e, n + 1);
    for (; a > r && is(e.charCodeAt(a - 1)); ) a--;
    return a;
  }
  function is(e) {
    return r0(e) || t0(e) || e === n0 || e === i0 || e === u0 || e === a0;
  }
});
var fs = C((hC, ls) => {
  var o0 = dr(), ss = Re(), cs = He(), s0 = os();
  ls.exports = Cn;
  Cn.locator = s0;
  Cn.notInLink = true;
  var c0 = 43, En = 45, it = 46, l0 = 64, vn = 95;
  function Cn(e, r, t) {
    var n = this, a = n.options.gfm, i = n.inlineTokenizers, u = 0, o = r.length, s = -1, l, c, f, p;
    if (a) {
      for (l = r.charCodeAt(u); ss(l) || cs(l) || l === c0 || l === En || l === it || l === vn; ) l = r.charCodeAt(++u);
      if (u !== 0 && l === l0) {
        for (u++; u < o; ) {
          if (l = r.charCodeAt(u), ss(l) || cs(l) || l === En || l === it || l === vn) {
            u++, s === -1 && l === it && (s = u);
            continue;
          }
          break;
        }
        if (!(s === -1 || s === u || l === En || l === vn)) return l === it && u--, c = r.slice(0, u), t ? true : (p = n.enterLink(), n.inlineTokenizers = { text: i.text }, f = n.tokenizeInline(c, e.now()), n.inlineTokenizers = i, p(), e(c)({ type: "link", title: null, url: "mailto:" + o0(c, { nonTerminated: false }), children: f }));
      }
    }
  }
});
var hs = C((dC, ps) => {
  var f0 = He(), D0 = fn(), p0 = an().tag;
  ps.exports = Ds;
  Ds.locator = D0;
  var h0 = "<", d0 = "?", m0 = "!", F0 = "/", g0 = /^<a /i, E0 = /^<\/a>/i;
  function Ds(e, r, t) {
    var n = this, a = r.length, i, u;
    if (!(r.charAt(0) !== h0 || a < 3) && (i = r.charAt(1), !(!f0(i) && i !== d0 && i !== m0 && i !== F0) && (u = r.match(p0), !!u))) return t ? true : (u = u[0], !n.inLink && g0.test(u) ? n.inLink = true : n.inLink && E0.test(u) && (n.inLink = false), e(u)({ type: "html", value: u }));
  }
});
var bn = C((mC, ds) => {
  ds.exports = v0;
  function v0(e, r) {
    var t = e.indexOf("[", r), n = e.indexOf("![", r);
    return n === -1 || t < n ? t : n;
  }
});
var bs = C((FC, Cs) => {
  var br = oe(), C0 = bn();
  Cs.exports = vs;
  vs.locator = C0;
  var b0 = `
`, A0 = "!", ms = '"', Fs = "'", er = "(", Ar = ")", An = "<", yn = ">", gs = "[", yr = "\\", y0 = "]", Es = "`";
  function vs(e, r, t) {
    var n = this, a = "", i = 0, u = r.charAt(0), o = n.options.pedantic, s = n.options.commonmark, l = n.options.gfm, c, f, p, d, D, h, m, F, A, v, B, b, g, y, x, E, w, k;
    if (u === A0 && (F = true, a = u, u = r.charAt(++i)), u === gs && !(!F && n.inLink)) {
      for (a += u, y = "", i++, B = r.length, E = e.now(), g = 0, E.column += i, E.offset += i; i < B; ) {
        if (u = r.charAt(i), h = u, u === Es) {
          for (f = 1; r.charAt(i + 1) === Es; ) h += u, i++, f++;
          p ? f >= p && (p = 0) : p = f;
        } else if (u === yr) i++, h += r.charAt(i);
        else if ((!p || l) && u === gs) g++;
        else if ((!p || l) && u === y0) if (g) g--;
        else {
          if (r.charAt(i + 1) !== er) return;
          h += er, c = true, i++;
          break;
        }
        y += h, h = "", i++;
      }
      if (c) {
        for (A = y, a += y + h, i++; i < B && (u = r.charAt(i), !!br(u)); ) a += u, i++;
        if (u = r.charAt(i), y = "", d = a, u === An) {
          for (i++, d += An; i < B && (u = r.charAt(i), u !== yn); ) {
            if (s && u === b0) return;
            y += u, i++;
          }
          if (r.charAt(i) !== yn) return;
          a += An + y + yn, x = y, i++;
        } else {
          for (u = null, h = ""; i < B && (u = r.charAt(i), !(h && (u === ms || u === Fs || s && u === er))); ) {
            if (br(u)) {
              if (!o) break;
              h += u;
            } else {
              if (u === er) g++;
              else if (u === Ar) {
                if (g === 0) break;
                g--;
              }
              y += h, h = "", u === yr && (y += yr, u = r.charAt(++i)), y += u;
            }
            i++;
          }
          a += y, x = y, i = a.length;
        }
        for (y = ""; i < B && (u = r.charAt(i), !!br(u)); ) y += u, i++;
        if (u = r.charAt(i), a += y, y && (u === ms || u === Fs || s && u === er)) if (i++, a += u, y = "", v = u === er ? Ar : u, D = a, s) {
          for (; i < B && (u = r.charAt(i), u !== v); ) u === yr && (y += yr, u = r.charAt(++i)), i++, y += u;
          if (u = r.charAt(i), u !== v) return;
          for (b = y, a += y + u, i++; i < B && (u = r.charAt(i), !!br(u)); ) a += u, i++;
        } else for (h = ""; i < B; ) {
          if (u = r.charAt(i), u === v) m && (y += v + h, h = ""), m = true;
          else if (!m) y += u;
          else if (u === Ar) {
            a += y + v + h, b = y;
            break;
          } else br(u) ? h += u : (y += v + h + u, h = "", m = false);
          i++;
        }
        if (r.charAt(i) === Ar) return t ? true : (a += Ar, x = n.decode.raw(n.unescape(x), e(d).test().end, { nonTerminated: false }), b && (D = e(D).test().end, b = n.decode.raw(n.unescape(b), D)), k = { type: F ? "image" : "link", title: b || null, url: x }, F ? k.alt = n.decode.raw(n.unescape(A), E) || null : (w = n.enterLink(), k.children = n.tokenizeInline(A, E), w()), e(a)(k));
      }
    }
  }
});
var xs = C((gC, ys) => {
  var x0 = oe(), w0 = bn(), k0 = on();
  ys.exports = As;
  As.locator = w0;
  var xn = "link", B0 = "image", T0 = "shortcut", q0 = "collapsed", wn = "full", _0 = "!", ut = "[", at = "\\", ot = "]";
  function As(e, r, t) {
    var n = this, a = n.options.commonmark, i = r.charAt(0), u = 0, o = r.length, s = "", l = "", c = xn, f = T0, p, d, D, h, m, F, A, v;
    if (i === _0 && (c = B0, l = i, i = r.charAt(++u)), i === ut) {
      for (u++, l += i, F = "", v = 0; u < o; ) {
        if (i = r.charAt(u), i === ut) A = true, v++;
        else if (i === ot) {
          if (!v) break;
          v--;
        }
        i === at && (F += at, i = r.charAt(++u)), F += i, u++;
      }
      if (s = F, p = F, i = r.charAt(u), i === ot) {
        if (u++, s += i, F = "", !a) for (; u < o && (i = r.charAt(u), !!x0(i)); ) F += i, u++;
        if (i = r.charAt(u), i === ut) {
          for (d = "", F += i, u++; u < o && (i = r.charAt(u), !(i === ut || i === ot)); ) i === at && (d += at, i = r.charAt(++u)), d += i, u++;
          i = r.charAt(u), i === ot ? (f = d ? wn : q0, F += d + i, u++) : d = "", s += F, F = "";
        } else {
          if (!p) return;
          d = p;
        }
        if (!(f !== wn && A)) return s = l + s, c === xn && n.inLink ? null : t ? true : (D = e.now(), D.column += l.length, D.offset += l.length, d = f === wn ? d : p, h = { type: c + "Reference", identifier: k0(d), label: d, referenceType: f }, c === xn ? (m = n.enterLink(), h.children = n.tokenizeInline(p, D), m()) : h.alt = n.decode.raw(n.unescape(p), D) || null, e(s)(h));
      }
    }
  }
});
var ks = C((EC, ws) => {
  ws.exports = S0;
  function S0(e, r) {
    var t = e.indexOf("**", r), n = e.indexOf("__", r);
    return n === -1 ? t : t === -1 || n < t ? n : t;
  }
});
var _s = C((vC, qs) => {
  var P0 = Ne(), Bs = oe(), O0 = ks();
  qs.exports = Ts;
  Ts.locator = O0;
  var L0 = "\\", I0 = "*", R0 = "_";
  function Ts(e, r, t) {
    var n = this, a = 0, i = r.charAt(a), u, o, s, l, c, f, p;
    if (!(i !== I0 && i !== R0 || r.charAt(++a) !== i) && (o = n.options.pedantic, s = i, c = s + s, f = r.length, a++, l = "", i = "", !(o && Bs(r.charAt(a))))) for (; a < f; ) {
      if (p = i, i = r.charAt(a), i === s && r.charAt(a + 1) === s && (!o || !Bs(p)) && (i = r.charAt(a + 2), i !== s)) return P0(l) ? t ? true : (u = e.now(), u.column += 2, u.offset += 2, e(c + l + c)({ type: "strong", children: n.tokenizeInline(l, u) })) : void 0;
      !o && i === L0 && (l += i, i = r.charAt(++a)), l += i, a++;
    }
  }
});
var Ps = C((CC, Ss) => {
  Ss.exports = U0;
  var N0 = String.fromCharCode, M0 = /\w/;
  function U0(e) {
    return M0.test(typeof e == "number" ? N0(e) : e.charAt(0));
  }
});
var Ls = C((bC, Os) => {
  Os.exports = z0;
  function z0(e, r) {
    var t = e.indexOf("*", r), n = e.indexOf("_", r);
    return n === -1 ? t : t === -1 || n < t ? n : t;
  }
});
var Us = C((AC, Ms) => {
  var G0 = Ne(), Y0 = Ps(), Is = oe(), $0 = Ls();
  Ms.exports = Ns;
  Ns.locator = $0;
  var V0 = "*", Rs = "_", j0 = "\\";
  function Ns(e, r, t) {
    var n = this, a = 0, i = r.charAt(a), u, o, s, l, c, f, p;
    if (!(i !== V0 && i !== Rs) && (o = n.options.pedantic, c = i, s = i, f = r.length, a++, l = "", i = "", !(o && Is(r.charAt(a))))) for (; a < f; ) {
      if (p = i, i = r.charAt(a), i === s && (!o || !Is(p))) {
        if (i = r.charAt(++a), i !== s) {
          if (!G0(l) || p === s) return;
          if (!o && s === Rs && Y0(i)) {
            l += s;
            continue;
          }
          return t ? true : (u = e.now(), u.column++, u.offset++, e(c + l + s)({ type: "emphasis", children: n.tokenizeInline(l, u) }));
        }
        l += s;
      }
      !o && i === j0 && (l += i, i = r.charAt(++a)), l += i, a++;
    }
  }
});
var Gs = C((yC, zs) => {
  zs.exports = W0;
  function W0(e, r) {
    return e.indexOf("~~", r);
  }
});
var Ws = C((xC, js) => {
  var Ys = oe(), H0 = Gs();
  js.exports = Vs;
  Vs.locator = H0;
  var st = "~", $s = "~~";
  function Vs(e, r, t) {
    var n = this, a = "", i = "", u = "", o = "", s, l, c;
    if (!(!n.options.gfm || r.charAt(0) !== st || r.charAt(1) !== st || Ys(r.charAt(2)))) for (s = 1, l = r.length, c = e.now(), c.column += 2, c.offset += 2; ++s < l; ) {
      if (a = r.charAt(s), a === st && i === st && (!u || !Ys(u))) return t ? true : e($s + o + $s)({ type: "delete", children: n.tokenizeInline(o, c) });
      o += i, u = i, i = a;
    }
  }
});
var Ks = C((wC, Hs) => {
  Hs.exports = K0;
  function K0(e, r) {
    return e.indexOf("`", r);
  }
});
var Qs = C((kC, Js) => {
  var X0 = Ks();
  Js.exports = Xs;
  Xs.locator = X0;
  var kn = 10, Bn = 32, Tn = 96;
  function Xs(e, r, t) {
    for (var n = r.length, a = 0, i, u, o, s, l, c; a < n && r.charCodeAt(a) === Tn; ) a++;
    if (!(a === 0 || a === n)) {
      for (i = a, l = r.charCodeAt(a); a < n; ) {
        if (s = l, l = r.charCodeAt(a + 1), s === Tn) {
          if (u === void 0 && (u = a), o = a + 1, l !== Tn && o - u === i) {
            c = true;
            break;
          }
        } else u !== void 0 && (u = void 0, o = void 0);
        a++;
      }
      if (c) {
        if (t) return true;
        if (a = i, n = u, s = r.charCodeAt(a), l = r.charCodeAt(n - 1), c = false, n - a > 2 && (s === Bn || s === kn) && (l === Bn || l === kn)) {
          for (a++, n--; a < n; ) {
            if (s = r.charCodeAt(a), s !== Bn && s !== kn) {
              c = true;
              break;
            }
            a++;
          }
          c === true && (i++, u--);
        }
        return e(r.slice(0, o))({ type: "inlineCode", value: r.slice(i, u) });
      }
    }
  }
});
var ec = C((BC, Zs) => {
  Zs.exports = J0;
  function J0(e, r) {
    for (var t = e.indexOf(`
`, r); t > r && e.charAt(t - 1) === " "; ) t--;
    return t;
  }
});
var nc = C((TC, tc) => {
  var Q0 = ec();
  tc.exports = rc;
  rc.locator = Q0;
  var Z0 = " ", em = `
`, rm = 2;
  function rc(e, r, t) {
    for (var n = r.length, a = -1, i = "", u; ++a < n; ) {
      if (u = r.charAt(a), u === em) return a < rm ? void 0 : t ? true : (i += u, e(i)({ type: "break" }));
      if (u !== Z0) return;
      i += u;
    }
  }
});
var uc = C((qC, ic) => {
  ic.exports = tm;
  function tm(e, r, t) {
    var n = this, a, i, u, o, s, l, c, f, p, d;
    if (t) return true;
    for (a = n.inlineMethods, o = a.length, i = n.inlineTokenizers, u = -1, p = r.length; ++u < o; ) f = a[u], !(f === "text" || !i[f]) && (c = i[f].locator, c || e.file.fail("Missing locator: `" + f + "`"), l = c.call(n, r, 1), l !== -1 && l < p && (p = l));
    s = r.slice(0, p), d = e.now(), n.decode(s, d, D);
    function D(h, m, F) {
      e(F || h)({ type: "text", value: h });
    }
  }
});
var cc = C((_C, sc) => {
  var nm = Ie(), ct = xu(), im = ku(), um = Tu(), am = na(), qn = aa();
  sc.exports = ac;
  function ac(e, r) {
    this.file = r, this.offset = {}, this.options = nm(this.options), this.setOptions({}), this.inList = false, this.inBlock = false, this.inLink = false, this.atStart = true, this.toOffset = im(r).toOffset, this.unescape = um(this, "escape"), this.decode = am(this);
  }
  var M = ac.prototype;
  M.setOptions = ha();
  M.parse = qa();
  M.options = jt();
  M.exitStart = ct("atStart", true);
  M.enterList = ct("inList", false);
  M.enterLink = ct("inLink", false);
  M.enterBlock = ct("inBlock", false);
  M.interruptParagraph = [["thematicBreak"], ["list"], ["atxHeading"], ["fencedCode"], ["blockquote"], ["html"], ["setextHeading", { commonmark: false }], ["definition", { commonmark: false }]];
  M.interruptList = [["atxHeading", { pedantic: false }], ["fencedCode", { pedantic: false }], ["thematicBreak", { pedantic: false }], ["definition", { commonmark: false }]];
  M.interruptBlockquote = [["indentedCode", { commonmark: true }], ["fencedCode", { commonmark: true }], ["atxHeading", { commonmark: true }], ["setextHeading", { commonmark: true }], ["thematicBreak", { commonmark: true }], ["html", { commonmark: true }], ["list", { commonmark: true }], ["definition", { commonmark: false }]];
  M.blockTokenizers = { blankLine: Sa(), indentedCode: Ra(), fencedCode: Ua(), blockquote: ja(), atxHeading: Ka(), thematicBreak: Qa(), list: co(), setextHeading: po(), html: vo(), definition: To(), table: So(), paragraph: Lo() };
  M.inlineTokenizers = { escape: zo(), autoLink: Wo(), url: ns(), email: fs(), html: hs(), link: bs(), reference: xs(), strong: _s(), emphasis: Us(), deletion: Ws(), code: Qs(), break: nc(), text: uc() };
  M.blockMethods = oc(M.blockTokenizers);
  M.inlineMethods = oc(M.inlineTokenizers);
  M.tokenizeBlock = qn("block");
  M.tokenizeInline = qn("inline");
  M.tokenizeFactory = qn;
  function oc(e) {
    var r = [], t;
    for (t in e) r.push(t);
    return r;
  }
});
var pc = C((SC, Dc) => {
  var om = Au(), sm = Ie(), lc = cc();
  Dc.exports = fc;
  fc.Parser = lc;
  function fc(e) {
    var r = this.data("settings"), t = om(lc);
    t.prototype.options = sm(t.prototype.options, r, e), this.Parser = t;
  }
});
var dc = C((PC, hc) => {
  hc.exports = cm;
  function cm(e) {
    if (e) throw e;
  }
});
var _n = C((OC, mc) => {
  mc.exports = function(r) {
    return r != null && r.constructor != null && typeof r.constructor.isBuffer == "function" && r.constructor.isBuffer(r);
  };
});
var xc = C((LC, yc) => {
  var lt = Object.prototype.hasOwnProperty, Ac = Object.prototype.toString, Fc = Object.defineProperty, gc = Object.getOwnPropertyDescriptor, Ec = function(r) {
    return typeof Array.isArray == "function" ? Array.isArray(r) : Ac.call(r) === "[object Array]";
  }, vc = function(r) {
    if (!r || Ac.call(r) !== "[object Object]") return false;
    var t = lt.call(r, "constructor"), n = r.constructor && r.constructor.prototype && lt.call(r.constructor.prototype, "isPrototypeOf");
    if (r.constructor && !t && !n) return false;
    var a;
    for (a in r) ;
    return typeof a > "u" || lt.call(r, a);
  }, Cc = function(r, t) {
    Fc && t.name === "__proto__" ? Fc(r, t.name, { enumerable: true, configurable: true, value: t.newValue, writable: true }) : r[t.name] = t.newValue;
  }, bc = function(r, t) {
    if (t === "__proto__") if (lt.call(r, t)) {
      if (gc) return gc(r, t).value;
    } else return;
    return r[t];
  };
  yc.exports = function e() {
    var r, t, n, a, i, u, o = arguments[0], s = 1, l = arguments.length, c = false;
    for (typeof o == "boolean" && (c = o, o = arguments[1] || {}, s = 2), (o == null || typeof o != "object" && typeof o != "function") && (o = {}); s < l; ++s) if (r = arguments[s], r != null) for (t in r) n = bc(o, t), a = bc(r, t), o !== a && (c && a && (vc(a) || (i = Ec(a))) ? (i ? (i = false, u = n && Ec(n) ? n : []) : u = n && vc(n) ? n : {}, Cc(o, { name: t, newValue: e(c, u, a) })) : typeof a < "u" && Cc(o, { name: t, newValue: a }));
    return o;
  };
});
var kc = C((IC, wc) => {
  wc.exports = (e) => {
    if (Object.prototype.toString.call(e) !== "[object Object]") return false;
    let r = Object.getPrototypeOf(e);
    return r === null || r === Object.prototype;
  };
});
var Tc = C((RC, Bc) => {
  var lm = [].slice;
  Bc.exports = fm;
  function fm(e, r) {
    var t;
    return n;
    function n() {
      var u = lm.call(arguments, 0), o = e.length > u.length, s;
      o && u.push(a);
      try {
        s = e.apply(null, u);
      } catch (l) {
        if (o && t) throw l;
        return a(l);
      }
      o || (s && typeof s.then == "function" ? s.then(i, a) : s instanceof Error ? a(s) : i(s));
    }
    function a() {
      t || (t = true, r.apply(null, arguments));
    }
    function i(u) {
      a(null, u);
    }
  }
});
var Oc = C((NC, Pc) => {
  var _c = Tc();
  Pc.exports = Sc;
  Sc.wrap = _c;
  var qc = [].slice;
  function Sc() {
    var e = [], r = {};
    return r.run = t, r.use = n, r;
    function t() {
      var a = -1, i = qc.call(arguments, 0, -1), u = arguments[arguments.length - 1];
      if (typeof u != "function") throw new Error("Expected function as last argument, not " + u);
      o.apply(null, [null].concat(i));
      function o(s) {
        var l = e[++a], c = qc.call(arguments, 0), f = c.slice(1), p = i.length, d = -1;
        if (s) {
          u(s);
          return;
        }
        for (; ++d < p; ) (f[d] === null || f[d] === void 0) && (f[d] = i[d]);
        i = f, l ? _c(l, o).apply(null, i) : u.apply(null, [null].concat(i));
      }
    }
    function n(a) {
      if (typeof a != "function") throw new Error("Expected `fn` to be a function, not " + a);
      return e.push(a), r;
    }
  }
});
var Nc = C((MC, Rc) => {
  var rr = {}.hasOwnProperty;
  Rc.exports = Dm;
  function Dm(e) {
    return !e || typeof e != "object" ? "" : rr.call(e, "position") || rr.call(e, "type") ? Lc(e.position) : rr.call(e, "start") || rr.call(e, "end") ? Lc(e) : rr.call(e, "line") || rr.call(e, "column") ? Sn(e) : "";
  }
  function Sn(e) {
    return (!e || typeof e != "object") && (e = {}), Ic(e.line) + ":" + Ic(e.column);
  }
  function Lc(e) {
    return (!e || typeof e != "object") && (e = {}), Sn(e.start) + "-" + Sn(e.end);
  }
  function Ic(e) {
    return e && typeof e == "number" ? e : 1;
  }
});
var zc = C((UC, Uc) => {
  var pm = Nc();
  Uc.exports = Pn;
  function Mc() {
  }
  Mc.prototype = Error.prototype;
  Pn.prototype = new Mc();
  var ke = Pn.prototype;
  ke.file = "";
  ke.name = "";
  ke.reason = "";
  ke.message = "";
  ke.stack = "";
  ke.fatal = null;
  ke.column = null;
  ke.line = null;
  function Pn(e, r, t) {
    var n, a, i;
    typeof r == "string" && (t = r, r = null), n = hm(t), a = pm(r) || "1:1", i = { start: { line: null, column: null }, end: { line: null, column: null } }, r && r.position && (r = r.position), r && (r.start ? (i = r, r = r.start) : i.start = r), e.stack && (this.stack = e.stack, e = e.message), this.message = e, this.name = a, this.reason = e, this.line = r ? r.line : null, this.column = r ? r.column : null, this.location = i, this.source = n[0], this.ruleId = n[1];
  }
  function hm(e) {
    var r = [null, null], t;
    return typeof e == "string" && (t = e.indexOf(":"), t === -1 ? r[1] = e : (r[0] = e.slice(0, t), r[1] = e.slice(t + 1))), r;
  }
});
var Gc = C((tr) => {
  tr.basename = dm;
  tr.dirname = mm;
  tr.extname = Fm;
  tr.join = gm;
  tr.sep = "/";
  function dm(e, r) {
    var t = 0, n = -1, a, i, u, o;
    if (r !== void 0 && typeof r != "string") throw new TypeError('"ext" argument must be a string');
    if (xr(e), a = e.length, r === void 0 || !r.length || r.length > e.length) {
      for (; a--; ) if (e.charCodeAt(a) === 47) {
        if (u) {
          t = a + 1;
          break;
        }
      } else n < 0 && (u = true, n = a + 1);
      return n < 0 ? "" : e.slice(t, n);
    }
    if (r === e) return "";
    for (i = -1, o = r.length - 1; a--; ) if (e.charCodeAt(a) === 47) {
      if (u) {
        t = a + 1;
        break;
      }
    } else i < 0 && (u = true, i = a + 1), o > -1 && (e.charCodeAt(a) === r.charCodeAt(o--) ? o < 0 && (n = a) : (o = -1, n = i));
    return t === n ? n = i : n < 0 && (n = e.length), e.slice(t, n);
  }
  function mm(e) {
    var r, t, n;
    if (xr(e), !e.length) return ".";
    for (r = -1, n = e.length; --n; ) if (e.charCodeAt(n) === 47) {
      if (t) {
        r = n;
        break;
      }
    } else t || (t = true);
    return r < 0 ? e.charCodeAt(0) === 47 ? "/" : "." : r === 1 && e.charCodeAt(0) === 47 ? "//" : e.slice(0, r);
  }
  function Fm(e) {
    var r = -1, t = 0, n = -1, a = 0, i, u, o;
    for (xr(e), o = e.length; o--; ) {
      if (u = e.charCodeAt(o), u === 47) {
        if (i) {
          t = o + 1;
          break;
        }
        continue;
      }
      n < 0 && (i = true, n = o + 1), u === 46 ? r < 0 ? r = o : a !== 1 && (a = 1) : r > -1 && (a = -1);
    }
    return r < 0 || n < 0 || a === 0 || a === 1 && r === n - 1 && r === t + 1 ? "" : e.slice(r, n);
  }
  function gm() {
    for (var e = -1, r; ++e < arguments.length; ) xr(arguments[e]), arguments[e] && (r = r === void 0 ? arguments[e] : r + "/" + arguments[e]);
    return r === void 0 ? "." : Em(r);
  }
  function Em(e) {
    var r, t;
    return xr(e), r = e.charCodeAt(0) === 47, t = vm(e, !r), !t.length && !r && (t = "."), t.length && e.charCodeAt(e.length - 1) === 47 && (t += "/"), r ? "/" + t : t;
  }
  function vm(e, r) {
    for (var t = "", n = 0, a = -1, i = 0, u = -1, o, s; ++u <= e.length; ) {
      if (u < e.length) o = e.charCodeAt(u);
      else {
        if (o === 47) break;
        o = 47;
      }
      if (o === 47) {
        if (!(a === u - 1 || i === 1)) if (a !== u - 1 && i === 2) {
          if (t.length < 2 || n !== 2 || t.charCodeAt(t.length - 1) !== 46 || t.charCodeAt(t.length - 2) !== 46) {
            if (t.length > 2) {
              if (s = t.lastIndexOf("/"), s !== t.length - 1) {
                s < 0 ? (t = "", n = 0) : (t = t.slice(0, s), n = t.length - 1 - t.lastIndexOf("/")), a = u, i = 0;
                continue;
              }
            } else if (t.length) {
              t = "", n = 0, a = u, i = 0;
              continue;
            }
          }
          r && (t = t.length ? t + "/.." : "..", n = 2);
        } else t.length ? t += "/" + e.slice(a + 1, u) : t = e.slice(a + 1, u), n = u - a - 1;
        a = u, i = 0;
      } else o === 46 && i > -1 ? i++ : i = -1;
    }
    return t;
  }
  function xr(e) {
    if (typeof e != "string") throw new TypeError("Path must be a string. Received " + JSON.stringify(e));
  }
});
var $c = C((Yc) => {
  Yc.cwd = Cm;
  function Cm() {
    return "/";
  }
});
var Wc = C((YC, jc) => {
  var se = Gc(), bm = $c(), Am = _n();
  jc.exports = ge;
  var ym = {}.hasOwnProperty, On = ["history", "path", "basename", "stem", "extname", "dirname"];
  ge.prototype.toString = Lm;
  Object.defineProperty(ge.prototype, "path", { get: xm, set: wm });
  Object.defineProperty(ge.prototype, "dirname", { get: km, set: Bm });
  Object.defineProperty(ge.prototype, "basename", { get: Tm, set: qm });
  Object.defineProperty(ge.prototype, "extname", { get: _m, set: Sm });
  Object.defineProperty(ge.prototype, "stem", { get: Pm, set: Om });
  function ge(e) {
    var r, t;
    if (!e) e = {};
    else if (typeof e == "string" || Am(e)) e = { contents: e };
    else if ("message" in e && "messages" in e) return e;
    if (!(this instanceof ge)) return new ge(e);
    for (this.data = {}, this.messages = [], this.history = [], this.cwd = bm.cwd(), t = -1; ++t < On.length; ) r = On[t], ym.call(e, r) && (this[r] = e[r]);
    for (r in e) On.indexOf(r) < 0 && (this[r] = e[r]);
  }
  function xm() {
    return this.history[this.history.length - 1];
  }
  function wm(e) {
    In(e, "path"), this.path !== e && this.history.push(e);
  }
  function km() {
    return typeof this.path == "string" ? se.dirname(this.path) : void 0;
  }
  function Bm(e) {
    Vc(this.path, "dirname"), this.path = se.join(e || "", this.basename);
  }
  function Tm() {
    return typeof this.path == "string" ? se.basename(this.path) : void 0;
  }
  function qm(e) {
    In(e, "basename"), Ln(e, "basename"), this.path = se.join(this.dirname || "", e);
  }
  function _m() {
    return typeof this.path == "string" ? se.extname(this.path) : void 0;
  }
  function Sm(e) {
    if (Ln(e, "extname"), Vc(this.path, "extname"), e) {
      if (e.charCodeAt(0) !== 46) throw new Error("`extname` must start with `.`");
      if (e.indexOf(".", 1) > -1) throw new Error("`extname` cannot contain multiple dots");
    }
    this.path = se.join(this.dirname, this.stem + (e || ""));
  }
  function Pm() {
    return typeof this.path == "string" ? se.basename(this.path, this.extname) : void 0;
  }
  function Om(e) {
    In(e, "stem"), Ln(e, "stem"), this.path = se.join(this.dirname || "", e + (this.extname || ""));
  }
  function Lm(e) {
    return (this.contents || "").toString(e);
  }
  function Ln(e, r) {
    if (e && e.indexOf(se.sep) > -1) throw new Error("`" + r + "` cannot be a path: did not expect `" + se.sep + "`");
  }
  function In(e, r) {
    if (!e) throw new Error("`" + r + "` cannot be empty");
  }
  function Vc(e, r) {
    if (!e) throw new Error("Setting `" + r + "` requires `path` to be set too");
  }
});
var Kc = C(($C, Hc) => {
  var Im = zc(), ft = Wc();
  Hc.exports = ft;
  ft.prototype.message = Rm;
  ft.prototype.info = Mm;
  ft.prototype.fail = Nm;
  function Rm(e, r, t) {
    var n = new Im(e, r, t);
    return this.path && (n.name = this.path + ":" + n.name, n.file = this.path), n.fatal = false, this.messages.push(n), n;
  }
  function Nm() {
    var e = this.message.apply(this, arguments);
    throw e.fatal = true, e;
  }
  function Mm() {
    var e = this.message.apply(this, arguments);
    return e.fatal = null, e;
  }
});
var Jc = C((VC, Xc) => {
  Xc.exports = Kc();
});
var al = C((jC, ul) => {
  var Qc = dc(), Um = _n(), Dt = xc(), Zc = kc(), nl = Oc(), wr = Jc();
  ul.exports = il().freeze();
  var zm = [].slice, Gm = {}.hasOwnProperty, Ym = nl().use($m).use(Vm).use(jm);
  function $m(e, r) {
    r.tree = e.parse(r.file);
  }
  function Vm(e, r, t) {
    e.run(r.tree, r.file, n);
    function n(a, i, u) {
      a ? t(a) : (r.tree = i, r.file = u, t());
    }
  }
  function jm(e, r) {
    var t = e.stringify(r.tree, r.file);
    t == null || (typeof t == "string" || Um(t) ? ("value" in r.file && (r.file.value = t), r.file.contents = t) : r.file.result = t);
  }
  function il() {
    var e = [], r = nl(), t = {}, n = -1, a;
    return i.data = o, i.freeze = u, i.attachers = e, i.use = s, i.parse = c, i.stringify = d, i.run = f, i.runSync = p, i.process = D, i.processSync = h, i;
    function i() {
      for (var m = il(), F = -1; ++F < e.length; ) m.use.apply(null, e[F]);
      return m.data(Dt(true, {}, t)), m;
    }
    function u() {
      var m, F;
      if (a) return i;
      for (; ++n < e.length; ) m = e[n], m[1] !== false && (m[1] === true && (m[1] = void 0), F = m[0].apply(i, m.slice(1)), typeof F == "function" && r.use(F));
      return a = true, n = 1 / 0, i;
    }
    function o(m, F) {
      return typeof m == "string" ? arguments.length === 2 ? (Mn("data", a), t[m] = F, i) : Gm.call(t, m) && t[m] || null : m ? (Mn("data", a), t = m, i) : t;
    }
    function s(m) {
      var F;
      if (Mn("use", a), m != null) if (typeof m == "function") b.apply(null, arguments);
      else if (typeof m == "object") "length" in m ? B(m) : A(m);
      else throw new Error("Expected usable value, not `" + m + "`");
      return F && (t.settings = Dt(t.settings || {}, F)), i;
      function A(g) {
        B(g.plugins), g.settings && (F = Dt(F || {}, g.settings));
      }
      function v(g) {
        if (typeof g == "function") b(g);
        else if (typeof g == "object") "length" in g ? b.apply(null, g) : A(g);
        else throw new Error("Expected usable value, not `" + g + "`");
      }
      function B(g) {
        var y = -1;
        if (g != null) if (typeof g == "object" && "length" in g) for (; ++y < g.length; ) v(g[y]);
        else throw new Error("Expected a list of plugins, not `" + g + "`");
      }
      function b(g, y) {
        var x = l(g);
        x ? (Zc(x[1]) && Zc(y) && (y = Dt(true, x[1], y)), x[1] = y) : e.push(zm.call(arguments));
      }
    }
    function l(m) {
      for (var F = -1; ++F < e.length; ) if (e[F][0] === m) return e[F];
    }
    function c(m) {
      var F = wr(m), A;
      return u(), A = i.Parser, Rn("parse", A), el(A, "parse") ? new A(String(F), F).parse() : A(String(F), F);
    }
    function f(m, F, A) {
      if (rl(m), u(), !A && typeof F == "function" && (A = F, F = null), !A) return new Promise(v);
      v(null, A);
      function v(B, b) {
        r.run(m, wr(F), g);
        function g(y, x, E) {
          x = x || m, y ? b(y) : B ? B(x) : A(null, x, E);
        }
      }
    }
    function p(m, F) {
      var A, v;
      return f(m, F, B), tl("runSync", "run", v), A;
      function B(b, g) {
        v = true, A = g, Qc(b);
      }
    }
    function d(m, F) {
      var A = wr(F), v;
      return u(), v = i.Compiler, Nn("stringify", v), rl(m), el(v, "compile") ? new v(m, A).compile() : v(m, A);
    }
    function D(m, F) {
      if (u(), Rn("process", i.Parser), Nn("process", i.Compiler), !F) return new Promise(A);
      A(null, F);
      function A(v, B) {
        var b = wr(m);
        Ym.run(i, { file: b }, g);
        function g(y) {
          y ? B(y) : v ? v(b) : F(null, b);
        }
      }
    }
    function h(m) {
      var F, A;
      return u(), Rn("processSync", i.Parser), Nn("processSync", i.Compiler), F = wr(m), D(F, v), tl("processSync", "process", A), F;
      function v(B) {
        A = true, Qc(B);
      }
    }
  }
  function el(e, r) {
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
  function Nn(e, r) {
    if (typeof r != "function") throw new Error("Cannot `" + e + "` without `Compiler`");
  }
  function Mn(e, r) {
    if (r) throw new Error("Cannot invoke `" + e + "` on a frozen processor.\nCreate a new processor first, by invoking it: use `processor()` instead of `processor`.");
  }
  function rl(e) {
    if (!e || typeof e.type != "string") throw new Error("Expected node, got `" + e + "`");
  }
  function tl(e, r, t) {
    if (!t) throw new Error("`" + e + "` finished async. Use `" + r + "` instead");
  }
});
var Gn = {};
$n(Gn, { languages: () => ru, options: () => tu, parsers: () => zn, printers: () => iF });
var ql = (e, r, t) => {
  if (!(e && r == null)) return Array.isArray(r) || typeof r == "string" ? r[t < 0 ? r.length + t : t] : r.at(t);
}, z = ql;
var _l = (e, r, t, n) => {
  if (!(e && r == null)) return r.replaceAll ? r.replaceAll(t, n) : t.global ? r.replace(t, n) : r.split(t).join(n);
}, R = _l;
var Zi = Me(kr());
function le(e) {
  if (typeof e != "string") throw new TypeError("Expected a string");
  return e.replace(/[|\\{}()[\]^$+*?.]/g, "\\$&").replace(/-/g, "\\x2d");
}
var Y = "string", H = "array", Ce = "cursor", re = "indent", te = "align", fe = "trim", X = "group", J = "fill", K = "if-break", De = "indent-if-break", pe = "line-suffix", he = "line-suffix-boundary", $ = "line", de = "label", ne = "break-parent", Br = /* @__PURE__ */ new Set([Ce, re, te, fe, X, J, K, De, pe, he, $, de, ne]);
function Pl(e) {
  if (typeof e == "string") return Y;
  if (Array.isArray(e)) return H;
  if (!e) return;
  let { type: r } = e;
  if (Br.has(r)) return r;
}
var G = Pl;
var Ol = (e) => new Intl.ListFormat("en-US", { type: "disjunction" }).format(e);
function Ll(e) {
  let r = e === null ? "null" : typeof e;
  if (r !== "string" && r !== "object") return `Unexpected doc '${r}', 
Expected it to be 'string' or 'object'.`;
  if (G(e)) throw new Error("doc is valid.");
  let t = Object.prototype.toString.call(e);
  if (t !== "[object Object]") return `Unexpected doc '${t}'.`;
  let n = Ol([...Br].map((a) => `'${a}'`));
  return `Unexpected doc.type '${e.type}'.
Expected it to be ${n}.`;
}
var dt = class extends Error {
  name = "InvalidDocError";
  constructor(r) {
    super(Ll(r)), this.doc = r;
  }
}, Te = dt;
var Kn = {};
function Il(e, r, t, n) {
  let a = [e];
  for (; a.length > 0; ) {
    let i = a.pop();
    if (i === Kn) {
      t(a.pop());
      continue;
    }
    t && a.push(i, Kn);
    let u = G(i);
    if (!u) throw new Te(i);
    if ((r == null ? void 0 : r(i)) !== false) switch (u) {
      case H:
      case J: {
        let o = u === H ? i : i.parts;
        for (let s = o.length, l = s - 1; l >= 0; --l) a.push(o[l]);
        break;
      }
      case K:
        a.push(i.flatContents, i.breakContents);
        break;
      case X:
        if (n && i.expandedStates) for (let o = i.expandedStates.length, s = o - 1; s >= 0; --s) a.push(i.expandedStates[s]);
        else a.push(i.contents);
        break;
      case te:
      case re:
      case De:
      case de:
      case pe:
        a.push(i.contents);
        break;
      case Y:
      case Ce:
      case fe:
      case he:
      case $:
      case ne:
        break;
      default:
        throw new Te(i);
    }
  }
}
var mt = Il;
function Rl(e, r) {
  if (typeof e == "string") return r(e);
  let t = /* @__PURE__ */ new Map();
  return n(e);
  function n(i) {
    if (t.has(i)) return t.get(i);
    let u = a(i);
    return t.set(i, u), u;
  }
  function a(i) {
    switch (G(i)) {
      case H:
        return r(i.map(n));
      case J:
        return r({ ...i, parts: i.parts.map(n) });
      case K:
        return r({ ...i, breakContents: n(i.breakContents), flatContents: n(i.flatContents) });
      case X: {
        let { expandedStates: u, contents: o } = i;
        return u ? (u = u.map(n), o = u[0]) : o = n(o), r({ ...i, contents: o, expandedStates: u });
      }
      case te:
      case re:
      case De:
      case de:
      case pe:
        return r({ ...i, contents: n(i.contents) });
      case Y:
      case Ce:
      case fe:
      case he:
      case $:
      case ne:
        return r(i);
      default:
        throw new Te(i);
    }
  }
}
function Xn(e) {
  if (e.length > 0) {
    let r = z(false, e, -1);
    !r.expandedStates && !r.break && (r.break = "propagated");
  }
  return null;
}
function Jn(e) {
  let r = /* @__PURE__ */ new Set(), t = [];
  function n(i) {
    if (i.type === ne && Xn(t), i.type === X) {
      if (t.push(i), r.has(i)) return false;
      r.add(i);
    }
  }
  function a(i) {
    i.type === X && t.pop().break && Xn(t);
  }
  mt(e, n, a, true);
}
function be(e, r = nr) {
  return Rl(e, (t) => typeof t == "string" ? Tr(r, t.split(`
`)) : t);
}
var Ft = () => {
}, gt = Ft;
function ir(e) {
  return { type: re, contents: e };
}
function Ae(e, r) {
  return { type: te, contents: r, n: e };
}
function Ue(e, r = {}) {
  return gt(r.expandedStates), { type: X, id: r.id, contents: e, break: !!r.shouldBreak, expandedStates: r.expandedStates };
}
function _e(e) {
  return Ae({ type: "root" }, e);
}
function ze(e) {
  return { type: J, parts: e };
}
function Zn(e, r = "", t = {}) {
  return { type: K, breakContents: e, flatContents: r, groupId: t.groupId };
}
var ur = { type: ne };
var ar = { type: $, hard: true }, Nl = { type: $, hard: true, literal: true }, qr = { type: $ }, _r = { type: $, soft: true }, L = [ar, ur], nr = [Nl, ur];
function Tr(e, r) {
  let t = [];
  for (let n = 0; n < r.length; n++) n !== 0 && t.push(e), t.push(r[n]);
  return t;
}
function Ml(e, r) {
  let t = e.match(new RegExp(`(${le(r)})+`, "gu"));
  return t === null ? 0 : t.reduce((n, a) => Math.max(n, a.length / r.length), 0);
}
var Sr = Ml;
function Ul(e, r) {
  let t = e.match(new RegExp(`(${le(r)})+`, "gu"));
  if (t === null) return 0;
  let n = /* @__PURE__ */ new Map(), a = 0;
  for (let i of t) {
    let u = i.length / r.length;
    n.set(u, true), u > a && (a = u);
  }
  for (let i = 1; i < a; i++) if (!n.get(i)) return i;
  return a + 1;
}
var ei = Ul;
var Pr = "'", ri = '"';
function zl(e, r) {
  let t = r === true || r === Pr ? Pr : ri, n = t === Pr ? ri : Pr, a = 0, i = 0;
  for (let u of e) u === t ? a++ : u === n && i++;
  return a > i ? n : t;
}
var ti = zl;
var Et = class extends Error {
  name = "UnexpectedNodeError";
  constructor(r, t, n = "type") {
    super(`Unexpected ${t} node ${n}: ${JSON.stringify(r[n])}.`), this.node = r;
  }
}, ni = Et;
var li = Me(kr());
function Gl(e) {
  return (e == null ? void 0 : e.type) === "front-matter";
}
var ii = Gl;
var ui = ["noformat", "noprettier"], Or = ["format", "prettier"], ai = "format";
var or = 3;
function Yl(e) {
  let r = e.slice(0, or);
  if (r !== "---" && r !== "+++") return;
  let t = e.indexOf(`
`, or);
  if (t === -1) return;
  let n = e.slice(or, t).trim(), a = e.indexOf(`
${r}`, t), i = n;
  if (i || (i = r === "+++" ? "toml" : "yaml"), a === -1 && r === "---" && i === "yaml" && (a = e.indexOf(`
...`, t)), a === -1) return;
  let u = a + 1 + or, o = e.charAt(u + 1);
  if (!/\s?/u.test(o)) return;
  let s = e.slice(0, u);
  return { type: "front-matter", language: i, explicitLanguage: n, value: e.slice(t + 1, a), startDelimiter: r, endDelimiter: s.slice(-or), raw: s };
}
function $l(e) {
  let r = Yl(e);
  if (!r) return { content: e };
  let { raw: t } = r;
  return { frontMatter: r, content: R(false, t, /[^\n]/gu, " ") + e.slice(t.length) };
}
var Ge = $l;
function Lr(e, r) {
  let t = `@(${r.join("|")})`, n = new RegExp([`<!--\\s*${t}\\s*-->`, `\\{\\s*\\/\\*\\s*${t}\\s*\\*\\/\\s*\\}`, `<!--.*\r?
[\\s\\S]*(^|
)[^\\S
]*${t}[^\\S
]*($|
)[\\s\\S]*
.*-->`].join("|"), "mu"), a = e.match(n);
  return (a == null ? void 0 : a.index) === 0;
}
var oi = (e) => Lr(Ge(e).content.trimStart(), Or), si = (e) => Lr(Ge(e).content.trimStart(), ui), ci = (e) => {
  let r = Ge(e), t = `<!-- @${ai} -->`;
  return r.frontMatter ? `${r.frontMatter.raw}

${t}

${r.content}` : `${t}

${r.content}`;
};
var Vl = /* @__PURE__ */ new Set(["position", "raw"]);
function fi(e, r, t) {
  if ((e.type === "front-matter" || e.type === "code" || e.type === "yaml" || e.type === "import" || e.type === "export" || e.type === "jsx") && delete r.value, e.type === "list" && delete r.isAligned, (e.type === "list" || e.type === "listItem") && delete r.spread, e.type === "text") return null;
  if (e.type === "inlineCode" && (r.value = R(false, e.value, `
`, " ")), e.type === "wikiLink" && (r.value = R(false, e.value.trim(), /[\t\n]+/gu, " ")), (e.type === "definition" || e.type === "linkReference" || e.type === "imageReference") && (r.label = (0, li.default)(e.label)), (e.type === "link" || e.type === "image") && e.url && e.url.includes("(")) for (let n of "<>") r.url = R(false, e.url, n, encodeURIComponent(n));
  if ((e.type === "definition" || e.type === "link" || e.type === "image") && e.title && (r.title = R(false, e.title, /\\(?=["')])/gu, "")), (t == null ? void 0 : t.type) === "root" && t.children.length > 0 && (t.children[0] === e || ii(t.children[0]) && t.children[1] === e) && e.type === "html" && Lr(e.value, Or)) return null;
}
fi.ignoredProperties = Vl;
var Di = fi;
var pi = /(?:[\u{2c7}\u{2c9}-\u{2cb}\u{2d9}\u{2ea}-\u{2eb}\u{305}\u{323}\u{1100}-\u{11ff}\u{2e80}-\u{2e99}\u{2e9b}-\u{2ef3}\u{2f00}-\u{2fd5}\u{2ff0}-\u{303f}\u{3041}-\u{3096}\u{3099}-\u{30ff}\u{3105}-\u{312f}\u{3131}-\u{318e}\u{3190}-\u{4dbf}\u{4e00}-\u{9fff}\u{a700}-\u{a707}\u{a960}-\u{a97c}\u{ac00}-\u{d7a3}\u{d7b0}-\u{d7c6}\u{d7cb}-\u{d7fb}\u{f900}-\u{fa6d}\u{fa70}-\u{fad9}\u{fe10}-\u{fe1f}\u{fe30}-\u{fe6f}\u{ff00}-\u{ffef}\u{16fe3}\u{1aff0}-\u{1aff3}\u{1aff5}-\u{1affb}\u{1affd}-\u{1affe}\u{1b000}-\u{1b122}\u{1b132}\u{1b150}-\u{1b152}\u{1b155}\u{1b164}-\u{1b167}\u{1f200}\u{1f250}-\u{1f251}\u{20000}-\u{2a6df}\u{2a700}-\u{2b739}\u{2b740}-\u{2b81d}\u{2b820}-\u{2cea1}\u{2ceb0}-\u{2ebe0}\u{2ebf0}-\u{2ee5d}\u{2f800}-\u{2fa1d}\u{30000}-\u{3134a}\u{31350}-\u{323af}])(?:[\u{fe00}-\u{fe0f}\u{e0100}-\u{e01ef}])?/u, Se = new RegExp("(?:[\\u{21}-\\u{2f}\\u{3a}-\\u{40}\\u{5b}-\\u{60}\\u{7b}-\\u{7e}]|\\p{General_Category=Connector_Punctuation}|\\p{General_Category=Dash_Punctuation}|\\p{General_Category=Close_Punctuation}|\\p{General_Category=Final_Punctuation}|\\p{General_Category=Initial_Punctuation}|\\p{General_Category=Other_Punctuation}|\\p{General_Category=Open_Punctuation})", "u");
async function jl(e, r) {
  if (e.language === "yaml") {
    let t = e.value.trim(), n = t ? await r(t, { parser: "yaml" }) : "";
    return _e([e.startDelimiter, e.explicitLanguage, L, n, n ? L : "", e.endDelimiter]);
  }
}
var hi = jl;
var Wl = (e, r) => {
  if (!(e && r == null)) return r.toReversed || !Array.isArray(r) ? r.toReversed() : [...r].reverse();
}, di = Wl;
var mi, Fi, gi, Ei, vi, Hl = ((mi = globalThis.Deno) == null ? void 0 : mi.build.os) === "windows" || ((gi = (Fi = globalThis.navigator) == null ? void 0 : Fi.platform) == null ? void 0 : gi.startsWith("Win")) || ((vi = (Ei = globalThis.process) == null ? void 0 : Ei.platform) == null ? void 0 : vi.startsWith("win")) || false;
function Ci(e) {
  if (e = e instanceof URL ? e : new URL(e), e.protocol !== "file:") throw new TypeError(`URL must be a file URL: received "${e.protocol}"`);
  return e;
}
function Kl(e) {
  return e = Ci(e), decodeURIComponent(e.pathname.replace(/%(?![0-9A-Fa-f]{2})/g, "%25"));
}
function Xl(e) {
  e = Ci(e);
  let r = decodeURIComponent(e.pathname.replace(/\//g, "\\").replace(/%(?![0-9A-Fa-f]{2})/g, "%25")).replace(/^\\*([A-Za-z]:)(\\|$)/, "$1\\");
  return e.hostname !== "" && (r = `\\\\${e.hostname}${r}`), r;
}
function bi(e) {
  return Hl ? Xl(e) : Kl(e);
}
var Ai = bi;
var Jl = (e) => String(e).split(/[/\\]/u).pop();
function yi(e, r) {
  if (!r) return;
  let t = Jl(r).toLowerCase();
  return e.find(({ filenames: n }) => n == null ? void 0 : n.some((a) => a.toLowerCase() === t)) ?? e.find(({ extensions: n }) => n == null ? void 0 : n.some((a) => t.endsWith(a)));
}
function Ql(e, r) {
  if (r) return e.find(({ name: t }) => t.toLowerCase() === r) ?? e.find(({ aliases: t }) => t == null ? void 0 : t.includes(r)) ?? e.find(({ extensions: t }) => t == null ? void 0 : t.includes(`.${r}`));
}
function xi(e, r) {
  if (r) {
    if (String(r).startsWith("file:")) try {
      r = Ai(r);
    } catch {
      return;
    }
    if (typeof r == "string") return e.find(({ isSupported: t }) => t == null ? void 0 : t({ filepath: r }));
  }
}
function Zl(e, r) {
  let t = di(false, e.plugins).flatMap((a) => a.languages ?? []), n = Ql(t, r.language) ?? yi(t, r.physicalFile) ?? yi(t, r.file) ?? xi(t, r.physicalFile) ?? xi(t, r.file) ?? (r.physicalFile, void 0);
  return n == null ? void 0 : n.parsers[0];
}
var wi = Zl;
var ef = new Proxy(() => {
}, { get: () => ef });
function Pe(e) {
  return e.position.start.offset;
}
function Oe(e) {
  return e.position.end.offset;
}
var vt = /* @__PURE__ */ new Set(["liquidNode", "inlineCode", "emphasis", "esComment", "strong", "delete", "wikiLink", "link", "linkReference", "image", "imageReference", "footnote", "footnoteReference", "sentence", "whitespace", "word", "break", "inlineMath"]), Ir = /* @__PURE__ */ new Set([...vt, "tableCell", "paragraph", "heading"]), $e = "non-cjk", ie = "cj-letter", Le = "k-letter", sr = "cjk-punctuation", rf = new RegExp("\\p{Script_Extensions=Hangul}", "u");
function Rr(e) {
  let r = [], t = e.split(/([\t\n ]+)/u);
  for (let [a, i] of t.entries()) {
    if (a % 2 === 1) {
      r.push({ type: "whitespace", value: /\n/u.test(i) ? `
` : " " });
      continue;
    }
    if ((a === 0 || a === t.length - 1) && i === "") continue;
    let u = i.split(new RegExp(`(${pi.source})`, "u"));
    for (let [o, s] of u.entries()) if (!((o === 0 || o === u.length - 1) && s === "")) {
      if (o % 2 === 0) {
        s !== "" && n({ type: "word", value: s, kind: $e, isCJ: false, hasLeadingPunctuation: Se.test(s[0]), hasTrailingPunctuation: Se.test(z(false, s, -1)) });
        continue;
      }
      if (Se.test(s)) {
        n({ type: "word", value: s, kind: sr, isCJ: true, hasLeadingPunctuation: true, hasTrailingPunctuation: true });
        continue;
      }
      if (rf.test(s)) {
        n({ type: "word", value: s, kind: Le, isCJ: false, hasLeadingPunctuation: false, hasTrailingPunctuation: false });
        continue;
      }
      n({ type: "word", value: s, kind: ie, isCJ: true, hasLeadingPunctuation: false, hasTrailingPunctuation: false });
    }
  }
  return r;
  function n(a) {
    let i = z(false, r, -1);
    (i == null ? void 0 : i.type) === "word" && !u($e, sr) && ![i.value, a.value].some((o) => /\u3000/u.test(o)) && r.push({ type: "whitespace", value: "" }), r.push(a);
    function u(o, s) {
      return i.kind === o && a.kind === s || i.kind === s && a.kind === o;
    }
  }
}
function Ye(e, r) {
  let t = r.originalText.slice(e.position.start.offset, e.position.end.offset), { numberText: n, leadingSpaces: a } = t.match(/^\s*(?<numberText>\d+)(\.|\))(?<leadingSpaces>\s*)/u).groups;
  return { number: Number(n), leadingSpaces: a };
}
function ki(e, r) {
  return !e.ordered || e.children.length < 2 || Ye(e.children[1], r).number !== 1 ? false : Ye(e.children[0], r).number !== 0 ? true : e.children.length > 2 && Ye(e.children[2], r).number === 1;
}
function Nr(e, r) {
  let { value: t } = e;
  return e.position.end.offset === r.length && t.endsWith(`
`) && r.endsWith(`
`) ? t.slice(0, -1) : t;
}
function ye(e, r) {
  return (function t(n, a, i) {
    let u = { ...r(n, a, i) };
    return u.children && (u.children = u.children.map((o, s) => t(o, s, [u, ...i]))), u;
  })(e, null, []);
}
function Ct(e) {
  if ((e == null ? void 0 : e.type) !== "link" || e.children.length !== 1) return false;
  let [r] = e.children;
  return Pe(e) === Pe(r) && Oe(e) === Oe(r);
}
function tf(e, r) {
  let { node: t } = e;
  if (t.type === "code" && t.lang !== null) {
    let n = wi(r, { language: t.lang });
    if (n) return async (a) => {
      let i = r.__inJsTemplate ? "~" : "`", u = i.repeat(Math.max(3, Sr(t.value, i) + 1)), o = { parser: n };
      t.lang === "ts" || t.lang === "typescript" ? o.filepath = "dummy.ts" : t.lang === "tsx" && (o.filepath = "dummy.tsx");
      let s = await a(Nr(t, r.originalText), o);
      return _e([u, t.lang, t.meta ? " " + t.meta : "", L, be(s), L, u]);
    };
  }
  switch (t.type) {
    case "front-matter":
      return (n) => hi(t, n);
    case "import":
    case "export":
      return (n) => n(t.value, { parser: "babel" });
    case "jsx":
      return (n) => n(`<$>${t.value}</$>`, { parser: "__js_expression", rootMarker: "mdx" });
  }
  return null;
}
var Bi = tf;
var cr = null;
function lr(e) {
  if (cr !== null && typeof cr.property) {
    let r = cr;
    return cr = lr.prototype = null, r;
  }
  return cr = lr.prototype = e ?? /* @__PURE__ */ Object.create(null), new lr();
}
var nf = 10;
for (let e = 0; e <= nf; e++) lr();
function bt(e) {
  return lr(e);
}
function uf(e, r = "type") {
  bt(e);
  function t(n) {
    let a = n[r], i = e[a];
    if (!Array.isArray(i)) throw Object.assign(new Error(`Missing visitor keys for '${a}'.`), { node: n });
    return i;
  }
  return t;
}
var Ti = uf;
var af = { "front-matter": [], root: ["children"], paragraph: ["children"], sentence: ["children"], word: [], whitespace: [], emphasis: ["children"], strong: ["children"], delete: ["children"], inlineCode: [], wikiLink: [], link: ["children"], image: [], blockquote: ["children"], heading: ["children"], code: [], html: [], list: ["children"], thematicBreak: [], linkReference: ["children"], imageReference: [], definition: [], footnote: ["children"], footnoteReference: [], footnoteDefinition: ["children"], table: ["children"], tableCell: ["children"], break: [], liquidNode: [], import: [], export: [], esComment: [], jsx: [], math: [], inlineMath: [], tableRow: ["children"], listItem: ["children"], text: [] }, qi = af;
var of = Ti(qi), _i = of;
function Si(e) {
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
var Pi = () => /[#*0-9]\uFE0F?\u20E3|[\xA9\xAE\u203C\u2049\u2122\u2139\u2194-\u2199\u21A9\u21AA\u231A\u231B\u2328\u23CF\u23ED-\u23EF\u23F1\u23F2\u23F8-\u23FA\u24C2\u25AA\u25AB\u25B6\u25C0\u25FB\u25FC\u25FE\u2600-\u2604\u260E\u2611\u2614\u2615\u2618\u2620\u2622\u2623\u2626\u262A\u262E\u262F\u2638-\u263A\u2640\u2642\u2648-\u2653\u265F\u2660\u2663\u2665\u2666\u2668\u267B\u267E\u267F\u2692\u2694-\u2697\u2699\u269B\u269C\u26A0\u26A7\u26AA\u26B0\u26B1\u26BD\u26BE\u26C4\u26C8\u26CF\u26D1\u26E9\u26F0-\u26F5\u26F7\u26F8\u26FA\u2702\u2708\u2709\u270F\u2712\u2714\u2716\u271D\u2721\u2733\u2734\u2744\u2747\u2757\u2763\u27A1\u2934\u2935\u2B05-\u2B07\u2B1B\u2B1C\u2B55\u3030\u303D\u3297\u3299]\uFE0F?|[\u261D\u270C\u270D](?:\uD83C[\uDFFB-\uDFFF]|\uFE0F)?|[\u270A\u270B](?:\uD83C[\uDFFB-\uDFFF])?|[\u23E9-\u23EC\u23F0\u23F3\u25FD\u2693\u26A1\u26AB\u26C5\u26CE\u26D4\u26EA\u26FD\u2705\u2728\u274C\u274E\u2753-\u2755\u2795-\u2797\u27B0\u27BF\u2B50]|\u26D3\uFE0F?(?:\u200D\uD83D\uDCA5)?|\u26F9(?:\uD83C[\uDFFB-\uDFFF]|\uFE0F)?(?:\u200D[\u2640\u2642]\uFE0F?)?|\u2764\uFE0F?(?:\u200D(?:\uD83D\uDD25|\uD83E\uDE79))?|\uD83C(?:[\uDC04\uDD70\uDD71\uDD7E\uDD7F\uDE02\uDE37\uDF21\uDF24-\uDF2C\uDF36\uDF7D\uDF96\uDF97\uDF99-\uDF9B\uDF9E\uDF9F\uDFCD\uDFCE\uDFD4-\uDFDF\uDFF5\uDFF7]\uFE0F?|[\uDF85\uDFC2\uDFC7](?:\uD83C[\uDFFB-\uDFFF])?|[\uDFC4\uDFCA](?:\uD83C[\uDFFB-\uDFFF])?(?:\u200D[\u2640\u2642]\uFE0F?)?|[\uDFCB\uDFCC](?:\uD83C[\uDFFB-\uDFFF]|\uFE0F)?(?:\u200D[\u2640\u2642]\uFE0F?)?|[\uDCCF\uDD8E\uDD91-\uDD9A\uDE01\uDE1A\uDE2F\uDE32-\uDE36\uDE38-\uDE3A\uDE50\uDE51\uDF00-\uDF20\uDF2D-\uDF35\uDF37-\uDF43\uDF45-\uDF4A\uDF4C-\uDF7C\uDF7E-\uDF84\uDF86-\uDF93\uDFA0-\uDFC1\uDFC5\uDFC6\uDFC8\uDFC9\uDFCF-\uDFD3\uDFE0-\uDFF0\uDFF8-\uDFFF]|\uDDE6\uD83C[\uDDE8-\uDDEC\uDDEE\uDDF1\uDDF2\uDDF4\uDDF6-\uDDFA\uDDFC\uDDFD\uDDFF]|\uDDE7\uD83C[\uDDE6\uDDE7\uDDE9-\uDDEF\uDDF1-\uDDF4\uDDF6-\uDDF9\uDDFB\uDDFC\uDDFE\uDDFF]|\uDDE8\uD83C[\uDDE6\uDDE8\uDDE9\uDDEB-\uDDEE\uDDF0-\uDDF7\uDDFA-\uDDFF]|\uDDE9\uD83C[\uDDEA\uDDEC\uDDEF\uDDF0\uDDF2\uDDF4\uDDFF]|\uDDEA\uD83C[\uDDE6\uDDE8\uDDEA\uDDEC\uDDED\uDDF7-\uDDFA]|\uDDEB\uD83C[\uDDEE-\uDDF0\uDDF2\uDDF4\uDDF7]|\uDDEC\uD83C[\uDDE6\uDDE7\uDDE9-\uDDEE\uDDF1-\uDDF3\uDDF5-\uDDFA\uDDFC\uDDFE]|\uDDED\uD83C[\uDDF0\uDDF2\uDDF3\uDDF7\uDDF9\uDDFA]|\uDDEE\uD83C[\uDDE8-\uDDEA\uDDF1-\uDDF4\uDDF6-\uDDF9]|\uDDEF\uD83C[\uDDEA\uDDF2\uDDF4\uDDF5]|\uDDF0\uD83C[\uDDEA\uDDEC-\uDDEE\uDDF2\uDDF3\uDDF5\uDDF7\uDDFC\uDDFE\uDDFF]|\uDDF1\uD83C[\uDDE6-\uDDE8\uDDEE\uDDF0\uDDF7-\uDDFB\uDDFE]|\uDDF2\uD83C[\uDDE6\uDDE8-\uDDED\uDDF0-\uDDFF]|\uDDF3\uD83C[\uDDE6\uDDE8\uDDEA-\uDDEC\uDDEE\uDDF1\uDDF4\uDDF5\uDDF7\uDDFA\uDDFF]|\uDDF4\uD83C\uDDF2|\uDDF5\uD83C[\uDDE6\uDDEA-\uDDED\uDDF0-\uDDF3\uDDF7-\uDDF9\uDDFC\uDDFE]|\uDDF6\uD83C\uDDE6|\uDDF7\uD83C[\uDDEA\uDDF4\uDDF8\uDDFA\uDDFC]|\uDDF8\uD83C[\uDDE6-\uDDEA\uDDEC-\uDDF4\uDDF7-\uDDF9\uDDFB\uDDFD-\uDDFF]|\uDDF9\uD83C[\uDDE6\uDDE8\uDDE9\uDDEB-\uDDED\uDDEF-\uDDF4\uDDF7\uDDF9\uDDFB\uDDFC\uDDFF]|\uDDFA\uD83C[\uDDE6\uDDEC\uDDF2\uDDF3\uDDF8\uDDFE\uDDFF]|\uDDFB\uD83C[\uDDE6\uDDE8\uDDEA\uDDEC\uDDEE\uDDF3\uDDFA]|\uDDFC\uD83C[\uDDEB\uDDF8]|\uDDFD\uD83C\uDDF0|\uDDFE\uD83C[\uDDEA\uDDF9]|\uDDFF\uD83C[\uDDE6\uDDF2\uDDFC]|\uDF44(?:\u200D\uD83D\uDFEB)?|\uDF4B(?:\u200D\uD83D\uDFE9)?|\uDFC3(?:\uD83C[\uDFFB-\uDFFF])?(?:\u200D(?:[\u2640\u2642]\uFE0F?(?:\u200D\u27A1\uFE0F?)?|\u27A1\uFE0F?))?|\uDFF3\uFE0F?(?:\u200D(?:\u26A7\uFE0F?|\uD83C\uDF08))?|\uDFF4(?:\u200D\u2620\uFE0F?|\uDB40\uDC67\uDB40\uDC62\uDB40(?:\uDC65\uDB40\uDC6E\uDB40\uDC67|\uDC73\uDB40\uDC63\uDB40\uDC74|\uDC77\uDB40\uDC6C\uDB40\uDC73)\uDB40\uDC7F)?)|\uD83D(?:[\uDC3F\uDCFD\uDD49\uDD4A\uDD6F\uDD70\uDD73\uDD76-\uDD79\uDD87\uDD8A-\uDD8D\uDDA5\uDDA8\uDDB1\uDDB2\uDDBC\uDDC2-\uDDC4\uDDD1-\uDDD3\uDDDC-\uDDDE\uDDE1\uDDE3\uDDE8\uDDEF\uDDF3\uDDFA\uDECB\uDECD-\uDECF\uDEE0-\uDEE5\uDEE9\uDEF0\uDEF3]\uFE0F?|[\uDC42\uDC43\uDC46-\uDC50\uDC66\uDC67\uDC6B-\uDC6D\uDC72\uDC74-\uDC76\uDC78\uDC7C\uDC83\uDC85\uDC8F\uDC91\uDCAA\uDD7A\uDD95\uDD96\uDE4C\uDE4F\uDEC0\uDECC](?:\uD83C[\uDFFB-\uDFFF])?|[\uDC6E\uDC70\uDC71\uDC73\uDC77\uDC81\uDC82\uDC86\uDC87\uDE45-\uDE47\uDE4B\uDE4D\uDE4E\uDEA3\uDEB4\uDEB5](?:\uD83C[\uDFFB-\uDFFF])?(?:\u200D[\u2640\u2642]\uFE0F?)?|[\uDD74\uDD90](?:\uD83C[\uDFFB-\uDFFF]|\uFE0F)?|[\uDC00-\uDC07\uDC09-\uDC14\uDC16-\uDC25\uDC27-\uDC3A\uDC3C-\uDC3E\uDC40\uDC44\uDC45\uDC51-\uDC65\uDC6A\uDC79-\uDC7B\uDC7D-\uDC80\uDC84\uDC88-\uDC8E\uDC90\uDC92-\uDCA9\uDCAB-\uDCFC\uDCFF-\uDD3D\uDD4B-\uDD4E\uDD50-\uDD67\uDDA4\uDDFB-\uDE2D\uDE2F-\uDE34\uDE37-\uDE41\uDE43\uDE44\uDE48-\uDE4A\uDE80-\uDEA2\uDEA4-\uDEB3\uDEB7-\uDEBF\uDEC1-\uDEC5\uDED0-\uDED2\uDED5-\uDED7\uDEDC-\uDEDF\uDEEB\uDEEC\uDEF4-\uDEFC\uDFE0-\uDFEB\uDFF0]|\uDC08(?:\u200D\u2B1B)?|\uDC15(?:\u200D\uD83E\uDDBA)?|\uDC26(?:\u200D(?:\u2B1B|\uD83D\uDD25))?|\uDC3B(?:\u200D\u2744\uFE0F?)?|\uDC41\uFE0F?(?:\u200D\uD83D\uDDE8\uFE0F?)?|\uDC68(?:\u200D(?:[\u2695\u2696\u2708]\uFE0F?|\u2764\uFE0F?\u200D\uD83D(?:\uDC8B\u200D\uD83D)?\uDC68|\uD83C[\uDF3E\uDF73\uDF7C\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D(?:[\uDC68\uDC69]\u200D\uD83D(?:\uDC66(?:\u200D\uD83D\uDC66)?|\uDC67(?:\u200D\uD83D[\uDC66\uDC67])?)|[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uDC66(?:\u200D\uD83D\uDC66)?|\uDC67(?:\u200D\uD83D[\uDC66\uDC67])?)|\uD83E(?:[\uDDAF\uDDBC\uDDBD](?:\u200D\u27A1\uFE0F?)?|[\uDDB0-\uDDB3]))|\uD83C(?:\uDFFB(?:\u200D(?:[\u2695\u2696\u2708]\uFE0F?|\u2764\uFE0F?\u200D\uD83D(?:\uDC8B\u200D\uD83D)?\uDC68\uD83C[\uDFFB-\uDFFF]|\uD83C[\uDF3E\uDF73\uDF7C\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uD83E(?:[\uDDAF\uDDBC\uDDBD](?:\u200D\u27A1\uFE0F?)?|[\uDDB0-\uDDB3]|\uDD1D\u200D\uD83D\uDC68\uD83C[\uDFFC-\uDFFF])))?|\uDFFC(?:\u200D(?:[\u2695\u2696\u2708]\uFE0F?|\u2764\uFE0F?\u200D\uD83D(?:\uDC8B\u200D\uD83D)?\uDC68\uD83C[\uDFFB-\uDFFF]|\uD83C[\uDF3E\uDF73\uDF7C\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uD83E(?:[\uDDAF\uDDBC\uDDBD](?:\u200D\u27A1\uFE0F?)?|[\uDDB0-\uDDB3]|\uDD1D\u200D\uD83D\uDC68\uD83C[\uDFFB\uDFFD-\uDFFF])))?|\uDFFD(?:\u200D(?:[\u2695\u2696\u2708]\uFE0F?|\u2764\uFE0F?\u200D\uD83D(?:\uDC8B\u200D\uD83D)?\uDC68\uD83C[\uDFFB-\uDFFF]|\uD83C[\uDF3E\uDF73\uDF7C\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uD83E(?:[\uDDAF\uDDBC\uDDBD](?:\u200D\u27A1\uFE0F?)?|[\uDDB0-\uDDB3]|\uDD1D\u200D\uD83D\uDC68\uD83C[\uDFFB\uDFFC\uDFFE\uDFFF])))?|\uDFFE(?:\u200D(?:[\u2695\u2696\u2708]\uFE0F?|\u2764\uFE0F?\u200D\uD83D(?:\uDC8B\u200D\uD83D)?\uDC68\uD83C[\uDFFB-\uDFFF]|\uD83C[\uDF3E\uDF73\uDF7C\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uD83E(?:[\uDDAF\uDDBC\uDDBD](?:\u200D\u27A1\uFE0F?)?|[\uDDB0-\uDDB3]|\uDD1D\u200D\uD83D\uDC68\uD83C[\uDFFB-\uDFFD\uDFFF])))?|\uDFFF(?:\u200D(?:[\u2695\u2696\u2708]\uFE0F?|\u2764\uFE0F?\u200D\uD83D(?:\uDC8B\u200D\uD83D)?\uDC68\uD83C[\uDFFB-\uDFFF]|\uD83C[\uDF3E\uDF73\uDF7C\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uD83E(?:[\uDDAF\uDDBC\uDDBD](?:\u200D\u27A1\uFE0F?)?|[\uDDB0-\uDDB3]|\uDD1D\u200D\uD83D\uDC68\uD83C[\uDFFB-\uDFFE])))?))?|\uDC69(?:\u200D(?:[\u2695\u2696\u2708]\uFE0F?|\u2764\uFE0F?\u200D\uD83D(?:\uDC8B\u200D\uD83D)?[\uDC68\uDC69]|\uD83C[\uDF3E\uDF73\uDF7C\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D(?:[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uDC66(?:\u200D\uD83D\uDC66)?|\uDC67(?:\u200D\uD83D[\uDC66\uDC67])?|\uDC69\u200D\uD83D(?:\uDC66(?:\u200D\uD83D\uDC66)?|\uDC67(?:\u200D\uD83D[\uDC66\uDC67])?))|\uD83E(?:[\uDDAF\uDDBC\uDDBD](?:\u200D\u27A1\uFE0F?)?|[\uDDB0-\uDDB3]))|\uD83C(?:\uDFFB(?:\u200D(?:[\u2695\u2696\u2708]\uFE0F?|\u2764\uFE0F?\u200D\uD83D(?:[\uDC68\uDC69]|\uDC8B\u200D\uD83D[\uDC68\uDC69])\uD83C[\uDFFB-\uDFFF]|\uD83C[\uDF3E\uDF73\uDF7C\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uD83E(?:[\uDDAF\uDDBC\uDDBD](?:\u200D\u27A1\uFE0F?)?|[\uDDB0-\uDDB3]|\uDD1D\u200D\uD83D[\uDC68\uDC69]\uD83C[\uDFFC-\uDFFF])))?|\uDFFC(?:\u200D(?:[\u2695\u2696\u2708]\uFE0F?|\u2764\uFE0F?\u200D\uD83D(?:[\uDC68\uDC69]|\uDC8B\u200D\uD83D[\uDC68\uDC69])\uD83C[\uDFFB-\uDFFF]|\uD83C[\uDF3E\uDF73\uDF7C\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uD83E(?:[\uDDAF\uDDBC\uDDBD](?:\u200D\u27A1\uFE0F?)?|[\uDDB0-\uDDB3]|\uDD1D\u200D\uD83D[\uDC68\uDC69]\uD83C[\uDFFB\uDFFD-\uDFFF])))?|\uDFFD(?:\u200D(?:[\u2695\u2696\u2708]\uFE0F?|\u2764\uFE0F?\u200D\uD83D(?:[\uDC68\uDC69]|\uDC8B\u200D\uD83D[\uDC68\uDC69])\uD83C[\uDFFB-\uDFFF]|\uD83C[\uDF3E\uDF73\uDF7C\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uD83E(?:[\uDDAF\uDDBC\uDDBD](?:\u200D\u27A1\uFE0F?)?|[\uDDB0-\uDDB3]|\uDD1D\u200D\uD83D[\uDC68\uDC69]\uD83C[\uDFFB\uDFFC\uDFFE\uDFFF])))?|\uDFFE(?:\u200D(?:[\u2695\u2696\u2708]\uFE0F?|\u2764\uFE0F?\u200D\uD83D(?:[\uDC68\uDC69]|\uDC8B\u200D\uD83D[\uDC68\uDC69])\uD83C[\uDFFB-\uDFFF]|\uD83C[\uDF3E\uDF73\uDF7C\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uD83E(?:[\uDDAF\uDDBC\uDDBD](?:\u200D\u27A1\uFE0F?)?|[\uDDB0-\uDDB3]|\uDD1D\u200D\uD83D[\uDC68\uDC69]\uD83C[\uDFFB-\uDFFD\uDFFF])))?|\uDFFF(?:\u200D(?:[\u2695\u2696\u2708]\uFE0F?|\u2764\uFE0F?\u200D\uD83D(?:[\uDC68\uDC69]|\uDC8B\u200D\uD83D[\uDC68\uDC69])\uD83C[\uDFFB-\uDFFF]|\uD83C[\uDF3E\uDF73\uDF7C\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uD83E(?:[\uDDAF\uDDBC\uDDBD](?:\u200D\u27A1\uFE0F?)?|[\uDDB0-\uDDB3]|\uDD1D\u200D\uD83D[\uDC68\uDC69]\uD83C[\uDFFB-\uDFFE])))?))?|\uDC6F(?:\u200D[\u2640\u2642]\uFE0F?)?|\uDD75(?:\uD83C[\uDFFB-\uDFFF]|\uFE0F)?(?:\u200D[\u2640\u2642]\uFE0F?)?|\uDE2E(?:\u200D\uD83D\uDCA8)?|\uDE35(?:\u200D\uD83D\uDCAB)?|\uDE36(?:\u200D\uD83C\uDF2B\uFE0F?)?|\uDE42(?:\u200D[\u2194\u2195]\uFE0F?)?|\uDEB6(?:\uD83C[\uDFFB-\uDFFF])?(?:\u200D(?:[\u2640\u2642]\uFE0F?(?:\u200D\u27A1\uFE0F?)?|\u27A1\uFE0F?))?)|\uD83E(?:[\uDD0C\uDD0F\uDD18-\uDD1F\uDD30-\uDD34\uDD36\uDD77\uDDB5\uDDB6\uDDBB\uDDD2\uDDD3\uDDD5\uDEC3-\uDEC5\uDEF0\uDEF2-\uDEF8](?:\uD83C[\uDFFB-\uDFFF])?|[\uDD26\uDD35\uDD37-\uDD39\uDD3D\uDD3E\uDDB8\uDDB9\uDDCD\uDDCF\uDDD4\uDDD6-\uDDDD](?:\uD83C[\uDFFB-\uDFFF])?(?:\u200D[\u2640\u2642]\uFE0F?)?|[\uDDDE\uDDDF](?:\u200D[\u2640\u2642]\uFE0F?)?|[\uDD0D\uDD0E\uDD10-\uDD17\uDD20-\uDD25\uDD27-\uDD2F\uDD3A\uDD3F-\uDD45\uDD47-\uDD76\uDD78-\uDDB4\uDDB7\uDDBA\uDDBC-\uDDCC\uDDD0\uDDE0-\uDDFF\uDE70-\uDE7C\uDE80-\uDE89\uDE8F-\uDEC2\uDEC6\uDECE-\uDEDC\uDEDF-\uDEE9]|\uDD3C(?:\u200D[\u2640\u2642]\uFE0F?|\uD83C[\uDFFB-\uDFFF])?|\uDDCE(?:\uD83C[\uDFFB-\uDFFF])?(?:\u200D(?:[\u2640\u2642]\uFE0F?(?:\u200D\u27A1\uFE0F?)?|\u27A1\uFE0F?))?|\uDDD1(?:\u200D(?:[\u2695\u2696\u2708]\uFE0F?|\uD83C[\uDF3E\uDF73\uDF7C\uDF84\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uD83E(?:[\uDDAF\uDDBC\uDDBD](?:\u200D\u27A1\uFE0F?)?|[\uDDB0-\uDDB3]|\uDD1D\u200D\uD83E\uDDD1|\uDDD1\u200D\uD83E\uDDD2(?:\u200D\uD83E\uDDD2)?|\uDDD2(?:\u200D\uD83E\uDDD2)?))|\uD83C(?:\uDFFB(?:\u200D(?:[\u2695\u2696\u2708]\uFE0F?|\u2764\uFE0F?\u200D(?:\uD83D\uDC8B\u200D)?\uD83E\uDDD1\uD83C[\uDFFC-\uDFFF]|\uD83C[\uDF3E\uDF73\uDF7C\uDF84\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uD83E(?:[\uDDAF\uDDBC\uDDBD](?:\u200D\u27A1\uFE0F?)?|[\uDDB0-\uDDB3]|\uDD1D\u200D\uD83E\uDDD1\uD83C[\uDFFB-\uDFFF])))?|\uDFFC(?:\u200D(?:[\u2695\u2696\u2708]\uFE0F?|\u2764\uFE0F?\u200D(?:\uD83D\uDC8B\u200D)?\uD83E\uDDD1\uD83C[\uDFFB\uDFFD-\uDFFF]|\uD83C[\uDF3E\uDF73\uDF7C\uDF84\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uD83E(?:[\uDDAF\uDDBC\uDDBD](?:\u200D\u27A1\uFE0F?)?|[\uDDB0-\uDDB3]|\uDD1D\u200D\uD83E\uDDD1\uD83C[\uDFFB-\uDFFF])))?|\uDFFD(?:\u200D(?:[\u2695\u2696\u2708]\uFE0F?|\u2764\uFE0F?\u200D(?:\uD83D\uDC8B\u200D)?\uD83E\uDDD1\uD83C[\uDFFB\uDFFC\uDFFE\uDFFF]|\uD83C[\uDF3E\uDF73\uDF7C\uDF84\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uD83E(?:[\uDDAF\uDDBC\uDDBD](?:\u200D\u27A1\uFE0F?)?|[\uDDB0-\uDDB3]|\uDD1D\u200D\uD83E\uDDD1\uD83C[\uDFFB-\uDFFF])))?|\uDFFE(?:\u200D(?:[\u2695\u2696\u2708]\uFE0F?|\u2764\uFE0F?\u200D(?:\uD83D\uDC8B\u200D)?\uD83E\uDDD1\uD83C[\uDFFB-\uDFFD\uDFFF]|\uD83C[\uDF3E\uDF73\uDF7C\uDF84\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uD83E(?:[\uDDAF\uDDBC\uDDBD](?:\u200D\u27A1\uFE0F?)?|[\uDDB0-\uDDB3]|\uDD1D\u200D\uD83E\uDDD1\uD83C[\uDFFB-\uDFFF])))?|\uDFFF(?:\u200D(?:[\u2695\u2696\u2708]\uFE0F?|\u2764\uFE0F?\u200D(?:\uD83D\uDC8B\u200D)?\uD83E\uDDD1\uD83C[\uDFFB-\uDFFE]|\uD83C[\uDF3E\uDF73\uDF7C\uDF84\uDF93\uDFA4\uDFA8\uDFEB\uDFED]|\uD83D[\uDCBB\uDCBC\uDD27\uDD2C\uDE80\uDE92]|\uD83E(?:[\uDDAF\uDDBC\uDDBD](?:\u200D\u27A1\uFE0F?)?|[\uDDB0-\uDDB3]|\uDD1D\u200D\uD83E\uDDD1\uD83C[\uDFFB-\uDFFF])))?))?|\uDEF1(?:\uD83C(?:\uDFFB(?:\u200D\uD83E\uDEF2\uD83C[\uDFFC-\uDFFF])?|\uDFFC(?:\u200D\uD83E\uDEF2\uD83C[\uDFFB\uDFFD-\uDFFF])?|\uDFFD(?:\u200D\uD83E\uDEF2\uD83C[\uDFFB\uDFFC\uDFFE\uDFFF])?|\uDFFE(?:\u200D\uD83E\uDEF2\uD83C[\uDFFB-\uDFFD\uDFFF])?|\uDFFF(?:\u200D\uD83E\uDEF2\uD83C[\uDFFB-\uDFFE])?))?)/g;
function Oi(e) {
  return e === 12288 || e >= 65281 && e <= 65376 || e >= 65504 && e <= 65510;
}
function Li(e) {
  return e >= 4352 && e <= 4447 || e === 8986 || e === 8987 || e === 9001 || e === 9002 || e >= 9193 && e <= 9196 || e === 9200 || e === 9203 || e === 9725 || e === 9726 || e === 9748 || e === 9749 || e >= 9776 && e <= 9783 || e >= 9800 && e <= 9811 || e === 9855 || e >= 9866 && e <= 9871 || e === 9875 || e === 9889 || e === 9898 || e === 9899 || e === 9917 || e === 9918 || e === 9924 || e === 9925 || e === 9934 || e === 9940 || e === 9962 || e === 9970 || e === 9971 || e === 9973 || e === 9978 || e === 9981 || e === 9989 || e === 9994 || e === 9995 || e === 10024 || e === 10060 || e === 10062 || e >= 10067 && e <= 10069 || e === 10071 || e >= 10133 && e <= 10135 || e === 10160 || e === 10175 || e === 11035 || e === 11036 || e === 11088 || e === 11093 || e >= 11904 && e <= 11929 || e >= 11931 && e <= 12019 || e >= 12032 && e <= 12245 || e >= 12272 && e <= 12287 || e >= 12289 && e <= 12350 || e >= 12353 && e <= 12438 || e >= 12441 && e <= 12543 || e >= 12549 && e <= 12591 || e >= 12593 && e <= 12686 || e >= 12688 && e <= 12773 || e >= 12783 && e <= 12830 || e >= 12832 && e <= 12871 || e >= 12880 && e <= 42124 || e >= 42128 && e <= 42182 || e >= 43360 && e <= 43388 || e >= 44032 && e <= 55203 || e >= 63744 && e <= 64255 || e >= 65040 && e <= 65049 || e >= 65072 && e <= 65106 || e >= 65108 && e <= 65126 || e >= 65128 && e <= 65131 || e >= 94176 && e <= 94180 || e === 94192 || e === 94193 || e >= 94208 && e <= 100343 || e >= 100352 && e <= 101589 || e >= 101631 && e <= 101640 || e >= 110576 && e <= 110579 || e >= 110581 && e <= 110587 || e === 110589 || e === 110590 || e >= 110592 && e <= 110882 || e === 110898 || e >= 110928 && e <= 110930 || e === 110933 || e >= 110948 && e <= 110951 || e >= 110960 && e <= 111355 || e >= 119552 && e <= 119638 || e >= 119648 && e <= 119670 || e === 126980 || e === 127183 || e === 127374 || e >= 127377 && e <= 127386 || e >= 127488 && e <= 127490 || e >= 127504 && e <= 127547 || e >= 127552 && e <= 127560 || e === 127568 || e === 127569 || e >= 127584 && e <= 127589 || e >= 127744 && e <= 127776 || e >= 127789 && e <= 127797 || e >= 127799 && e <= 127868 || e >= 127870 && e <= 127891 || e >= 127904 && e <= 127946 || e >= 127951 && e <= 127955 || e >= 127968 && e <= 127984 || e === 127988 || e >= 127992 && e <= 128062 || e === 128064 || e >= 128066 && e <= 128252 || e >= 128255 && e <= 128317 || e >= 128331 && e <= 128334 || e >= 128336 && e <= 128359 || e === 128378 || e === 128405 || e === 128406 || e === 128420 || e >= 128507 && e <= 128591 || e >= 128640 && e <= 128709 || e === 128716 || e >= 128720 && e <= 128722 || e >= 128725 && e <= 128727 || e >= 128732 && e <= 128735 || e === 128747 || e === 128748 || e >= 128756 && e <= 128764 || e >= 128992 && e <= 129003 || e === 129008 || e >= 129292 && e <= 129338 || e >= 129340 && e <= 129349 || e >= 129351 && e <= 129535 || e >= 129648 && e <= 129660 || e >= 129664 && e <= 129673 || e >= 129679 && e <= 129734 || e >= 129742 && e <= 129756 || e >= 129759 && e <= 129769 || e >= 129776 && e <= 129784 || e >= 131072 && e <= 196605 || e >= 196608 && e <= 262141;
}
var Ii = (e) => !(Oi(e) || Li(e));
var sf = /[^\x20-\x7F]/u;
function cf(e) {
  if (!e) return 0;
  if (!sf.test(e)) return e.length;
  e = e.replace(Pi(), "  ");
  let r = 0;
  for (let t of e) {
    let n = t.codePointAt(0);
    n <= 31 || n >= 127 && n <= 159 || n >= 768 && n <= 879 || (r += Ii(n) ? 1 : 2);
  }
  return r;
}
var fr = cf;
var V = Symbol("MODE_BREAK"), ue = Symbol("MODE_FLAT"), Ve = Symbol("cursor"), At = Symbol("DOC_FILL_PRINTED_LENGTH");
function Ri() {
  return { value: "", length: 0, queue: [] };
}
function lf(e, r) {
  return yt(e, { type: "indent" }, r);
}
function ff(e, r, t) {
  return r === Number.NEGATIVE_INFINITY ? e.root || Ri() : r < 0 ? yt(e, { type: "dedent" }, t) : r ? r.type === "root" ? { ...e, root: e } : yt(e, { type: typeof r == "string" ? "stringAlign" : "numberAlign", n: r }, t) : e;
}
function yt(e, r, t) {
  let n = r.type === "dedent" ? e.queue.slice(0, -1) : [...e.queue, r], a = "", i = 0, u = 0, o = 0;
  for (let D of n) switch (D.type) {
    case "indent":
      c(), t.useTabs ? s(1) : l(t.tabWidth);
      break;
    case "stringAlign":
      c(), a += D.n, i += D.n.length;
      break;
    case "numberAlign":
      u += 1, o += D.n;
      break;
    default:
      throw new Error(`Unexpected type '${D.type}'`);
  }
  return p(), { ...e, value: a, length: i, queue: n };
  function s(D) {
    a += "	".repeat(D), i += t.tabWidth * D;
  }
  function l(D) {
    a += " ".repeat(D), i += D;
  }
  function c() {
    t.useTabs ? f() : p();
  }
  function f() {
    u > 0 && s(u), d();
  }
  function p() {
    o > 0 && l(o), d();
  }
  function d() {
    u = 0, o = 0;
  }
}
function xt(e) {
  let r = 0, t = 0, n = e.length;
  e: for (; n--; ) {
    let a = e[n];
    if (a === Ve) {
      t++;
      continue;
    }
    for (let i = a.length - 1; i >= 0; i--) {
      let u = a[i];
      if (u === " " || u === "	") r++;
      else {
        e[n] = a.slice(0, i + 1);
        break e;
      }
    }
  }
  if (r > 0 || t > 0) for (e.length = n + 1; t-- > 0; ) e.push(Ve);
  return r;
}
function Mr(e, r, t, n, a, i) {
  if (t === Number.POSITIVE_INFINITY) return true;
  let u = r.length, o = [e], s = [];
  for (; t >= 0; ) {
    if (o.length === 0) {
      if (u === 0) return true;
      o.push(r[--u]);
      continue;
    }
    let { mode: l, doc: c } = o.pop(), f = G(c);
    switch (f) {
      case Y:
        s.push(c), t -= fr(c);
        break;
      case H:
      case J: {
        let p = f === H ? c : c.parts, d = c[At] ?? 0;
        for (let D = p.length - 1; D >= d; D--) o.push({ mode: l, doc: p[D] });
        break;
      }
      case re:
      case te:
      case De:
      case de:
        o.push({ mode: l, doc: c.contents });
        break;
      case fe:
        t += xt(s);
        break;
      case X: {
        if (i && c.break) return false;
        let p = c.break ? V : l, d = c.expandedStates && p === V ? z(false, c.expandedStates, -1) : c.contents;
        o.push({ mode: p, doc: d });
        break;
      }
      case K: {
        let d = (c.groupId ? a[c.groupId] || ue : l) === V ? c.breakContents : c.flatContents;
        d && o.push({ mode: l, doc: d });
        break;
      }
      case $:
        if (l === V || c.hard) return true;
        c.soft || (s.push(" "), t--);
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
function Ni(e, r) {
  let t = {}, n = r.printWidth, a = Si(r.endOfLine), i = 0, u = [{ ind: Ri(), mode: V, doc: e }], o = [], s = false, l = [], c = 0;
  for (Jn(e); u.length > 0; ) {
    let { ind: p, mode: d, doc: D } = u.pop();
    switch (G(D)) {
      case Y: {
        let h = a !== `
` ? R(false, D, `
`, a) : D;
        o.push(h), u.length > 0 && (i += fr(h));
        break;
      }
      case H:
        for (let h = D.length - 1; h >= 0; h--) u.push({ ind: p, mode: d, doc: D[h] });
        break;
      case Ce:
        if (c >= 2) throw new Error("There are too many 'cursor' in doc.");
        o.push(Ve), c++;
        break;
      case re:
        u.push({ ind: lf(p, r), mode: d, doc: D.contents });
        break;
      case te:
        u.push({ ind: ff(p, D.n, r), mode: d, doc: D.contents });
        break;
      case fe:
        i -= xt(o);
        break;
      case X:
        switch (d) {
          case ue:
            if (!s) {
              u.push({ ind: p, mode: D.break ? V : ue, doc: D.contents });
              break;
            }
          case V: {
            s = false;
            let h = { ind: p, mode: ue, doc: D.contents }, m = n - i, F = l.length > 0;
            if (!D.break && Mr(h, u, m, F, t)) u.push(h);
            else if (D.expandedStates) {
              let A = z(false, D.expandedStates, -1);
              if (D.break) {
                u.push({ ind: p, mode: V, doc: A });
                break;
              } else for (let v = 1; v < D.expandedStates.length + 1; v++) if (v >= D.expandedStates.length) {
                u.push({ ind: p, mode: V, doc: A });
                break;
              } else {
                let B = D.expandedStates[v], b = { ind: p, mode: ue, doc: B };
                if (Mr(b, u, m, F, t)) {
                  u.push(b);
                  break;
                }
              }
            } else u.push({ ind: p, mode: V, doc: D.contents });
            break;
          }
        }
        D.id && (t[D.id] = z(false, u, -1).mode);
        break;
      case J: {
        let h = n - i, m = D[At] ?? 0, { parts: F } = D, A = F.length - m;
        if (A === 0) break;
        let v = F[m + 0], B = F[m + 1], b = { ind: p, mode: ue, doc: v }, g = { ind: p, mode: V, doc: v }, y = Mr(b, [], h, l.length > 0, t, true);
        if (A === 1) {
          y ? u.push(b) : u.push(g);
          break;
        }
        let x = { ind: p, mode: ue, doc: B }, E = { ind: p, mode: V, doc: B };
        if (A === 2) {
          y ? u.push(x, b) : u.push(E, g);
          break;
        }
        let w = F[m + 2], k = { ind: p, mode: d, doc: { ...D, [At]: m + 2 } };
        Mr({ ind: p, mode: ue, doc: [v, B, w] }, [], h, l.length > 0, t, true) ? u.push(k, x, b) : y ? u.push(k, E, b) : u.push(k, E, g);
        break;
      }
      case K:
      case De: {
        let h = D.groupId ? t[D.groupId] : d;
        if (h === V) {
          let m = D.type === K ? D.breakContents : D.negate ? D.contents : ir(D.contents);
          m && u.push({ ind: p, mode: d, doc: m });
        }
        if (h === ue) {
          let m = D.type === K ? D.flatContents : D.negate ? ir(D.contents) : D.contents;
          m && u.push({ ind: p, mode: d, doc: m });
        }
        break;
      }
      case pe:
        l.push({ ind: p, mode: d, doc: D.contents });
        break;
      case he:
        l.length > 0 && u.push({ ind: p, mode: d, doc: ar });
        break;
      case $:
        switch (d) {
          case ue:
            if (D.hard) s = true;
            else {
              D.soft || (o.push(" "), i += 1);
              break;
            }
          case V:
            if (l.length > 0) {
              u.push({ ind: p, mode: d, doc: D }, ...l.reverse()), l.length = 0;
              break;
            }
            D.literal ? p.root ? (o.push(a, p.root.value), i = p.root.length) : (o.push(a), i = 0) : (i -= xt(o), o.push(a + p.value), i = p.length);
            break;
        }
        break;
      case de:
        u.push({ ind: p, mode: d, doc: D.contents });
        break;
      case ne:
        break;
      default:
        throw new Te(D);
    }
    u.length === 0 && l.length > 0 && (u.push(...l.reverse()), l.length = 0);
  }
  let f = o.indexOf(Ve);
  if (f !== -1) {
    let p = o.indexOf(Ve, f + 1);
    if (p === -1) return { formatted: o.filter((m) => m !== Ve).join("") };
    let d = o.slice(0, f).join(""), D = o.slice(f + 1, p).join(""), h = o.slice(p + 1).join("");
    return { formatted: d + D + h, cursorNodeStart: d.length, cursorNodeText: D };
  }
  return { formatted: o.join("") };
}
function Mi(e, r, t) {
  let { node: n } = e, a = [], i = e.map(() => e.map(({ index: f }) => {
    let p = Ni(t(), r).formatted, d = fr(p);
    return a[f] = Math.max(a[f] ?? 3, d), { text: p, width: d };
  }, "children"), "children"), u = s(false);
  if (r.proseWrap !== "never") return [ur, u];
  let o = s(true);
  return [ur, Ue(Zn(o, u))];
  function s(f) {
    return Tr(ar, [c(i[0], f), l(f), ...i.slice(1).map((p) => c(p, f))].map((p) => `| ${p.join(" | ")} |`));
  }
  function l(f) {
    return a.map((p, d) => {
      let D = n.align[d], h = D === "center" || D === "left" ? ":" : "-", m = D === "center" || D === "right" ? ":" : "-", F = f ? "-" : "-".repeat(p - 2);
      return `${h}${F}${m}`;
    });
  }
  function c(f, p) {
    return f.map(({ text: d, width: D }, h) => {
      if (p) return d;
      let m = a[h] - D, F = n.align[h], A = 0;
      F === "right" ? A = m : F === "center" && (A = Math.floor(m / 2));
      let v = m - A;
      return `${" ".repeat(A)}${d}${" ".repeat(v)}`;
    });
  }
}
function Ui(e, r, t) {
  let n = e.map(t, "children");
  return Df(n);
}
function Df(e) {
  let r = [""];
  return (function t(n) {
    for (let a of n) {
      let i = G(a);
      if (i === H) {
        t(a);
        continue;
      }
      let u = a, o = [];
      i === J && ([u, ...o] = a.parts), r.push([r.pop(), u], ...o);
    }
  })(e), ze(r);
}
var Q, wt = class {
  constructor(r) {
    jn(this, Q);
    Wn(this, Q, new Set(r));
  }
  getLeadingWhitespaceCount(r) {
    let t = ce(this, Q), n = 0;
    for (let a = 0; a < r.length && t.has(r.charAt(a)); a++) n++;
    return n;
  }
  getTrailingWhitespaceCount(r) {
    let t = ce(this, Q), n = 0;
    for (let a = r.length - 1; a >= 0 && t.has(r.charAt(a)); a--) n++;
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
    return ce(this, Q).has(r.charAt(0));
  }
  hasTrailingWhitespace(r) {
    return ce(this, Q).has(z(false, r, -1));
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
    let n = `[${le([...ce(this, Q)].join(""))}]+`, a = new RegExp(t ? `(${n})` : n, "u");
    return r.split(a);
  }
  hasWhitespaceCharacter(r) {
    let t = ce(this, Q);
    return Array.prototype.some.call(r, (n) => t.has(n));
  }
  hasNonWhitespaceCharacter(r) {
    let t = ce(this, Q);
    return Array.prototype.some.call(r, (n) => !t.has(n));
  }
  isWhitespaceOnly(r) {
    let t = ce(this, Q);
    return Array.prototype.every.call(r, (n) => t.has(n));
  }
};
Q = /* @__PURE__ */ new WeakMap();
var zi = wt;
var pf = ["	", `
`, "\f", "\r", " "], hf = new zi(pf), kt = hf;
var df = /^\\?.$/su, mf = /^\n *>[ >]*$/u;
function Ff(e, r) {
  return e = gf(e, r), e = vf(e), e = bf(e, r), e = Af(e, r), e = Cf(e), e;
}
function gf(e, r) {
  return ye(e, (t) => {
    if (t.type !== "text") return t;
    let { value: n } = t;
    if (n === "*" || n === "_" || !df.test(n) || t.position.end.offset - t.position.start.offset === n.length) return t;
    let a = r.originalText.slice(t.position.start.offset, t.position.end.offset);
    return mf.test(a) ? t : { ...t, value: a };
  });
}
function Ef(e, r, t) {
  return ye(e, (n) => {
    if (!n.children) return n;
    let a = n.children.reduce((i, u) => {
      let o = z(false, i, -1);
      return o && r(o, u) ? i.splice(-1, 1, t(o, u)) : i.push(u), i;
    }, []);
    return { ...n, children: a };
  });
}
function vf(e) {
  return Ef(e, (r, t) => r.type === "text" && t.type === "text", (r, t) => ({ type: "text", value: r.value + t.value, position: { start: r.position.start, end: t.position.end } }));
}
function Cf(e) {
  return ye(e, (r, t, [n]) => {
    if (r.type !== "text") return r;
    let { value: a } = r;
    return n.type === "paragraph" && (t === 0 && (a = kt.trimStart(a)), t === n.children.length - 1 && (a = kt.trimEnd(a))), { type: "sentence", position: r.position, children: Rr(a) };
  });
}
function bf(e, r) {
  return ye(e, (t, n, a) => {
    if (t.type === "code") {
      let i = /^\n?(?: {4,}|\t)/u.test(r.originalText.slice(t.position.start.offset, t.position.end.offset));
      if (t.isIndented = i, i) for (let u = 0; u < a.length; u++) {
        let o = a[u];
        if (o.hasIndentedCodeblock) break;
        o.type === "list" && (o.hasIndentedCodeblock = true);
      }
    }
    return t;
  });
}
function Af(e, r) {
  return ye(e, (a, i, u) => {
    if (a.type === "list" && a.children.length > 0) {
      for (let o = 0; o < u.length; o++) {
        let s = u[o];
        if (s.type === "list" && !s.isAligned) return a.isAligned = false, a;
      }
      a.isAligned = n(a);
    }
    return a;
  });
  function t(a) {
    return a.children.length === 0 ? -1 : a.children[0].position.start.column - 1;
  }
  function n(a) {
    if (!a.ordered) return true;
    let [i, u] = a.children;
    if (Ye(i, r).leadingSpaces.length > 1) return true;
    let s = t(i);
    if (s === -1) return false;
    if (a.children.length === 1) return s % r.tabWidth === 0;
    let l = t(u);
    return s !== l ? false : s % r.tabWidth === 0 ? true : Ye(u, r).leadingSpaces.length > 1;
  }
}
var Gi = Ff;
function Yi(e, r) {
  let t = [""];
  return e.each(() => {
    let { node: n } = e, a = r();
    switch (n.type) {
      case "whitespace":
        if (G(a) !== Y) {
          t.push(a, "");
          break;
        }
      default:
        t.push([t.pop(), a]);
    }
  }, "children"), ze(t);
}
var yf = /* @__PURE__ */ new Set(["heading", "tableCell", "link", "wikiLink"]), $i = new Set("!\"#$%&'()*+,-./:;<=>?@[\\]^_`{|}~");
function xf({ parent: e }) {
  if (e.usesCJSpaces === void 0) {
    let r = { " ": 0, "": 0 }, { children: t } = e;
    for (let n = 1; n < t.length - 1; ++n) {
      let a = t[n];
      if (a.type === "whitespace" && (a.value === " " || a.value === "")) {
        let i = t[n - 1].kind, u = t[n + 1].kind;
        (i === ie && u === $e || i === $e && u === ie) && ++r[a.value];
      }
    }
    e.usesCJSpaces = r[" "] > r[""];
  }
  return e.usesCJSpaces;
}
function wf(e, r) {
  if (r) return true;
  let { previous: t, next: n } = e;
  if (!t || !n) return true;
  let a = t.kind, i = n.kind;
  return Vi(a) && Vi(i) || a === Le && i === ie || i === Le && a === ie ? true : a === sr || i === sr || a === ie && i === ie ? false : $i.has(n.value[0]) || $i.has(z(false, t.value, -1)) ? true : t.hasTrailingPunctuation || n.hasLeadingPunctuation ? false : xf(e);
}
function Vi(e) {
  return e === $e || e === Le;
}
function kf(e, r, t, n) {
  if (t !== "always" || e.hasAncestor((u) => yf.has(u.type))) return false;
  if (n) return r !== "";
  let { previous: a, next: i } = e;
  return !a || !i ? true : r === "" ? false : a.kind === Le && i.kind === ie || i.kind === Le && a.kind === ie ? true : !(a.isCJ || i.isCJ);
}
function Bt(e, r, t, n) {
  if (t === "preserve" && r === `
`) return L;
  let a = r === " " || r === `
` && wf(e, n);
  return kf(e, r, t, n) ? a ? qr : _r : a ? " " : "";
}
var Bf = /* @__PURE__ */ new Set(["listItem", "definition"]);
function ji(e) {
  var a, i;
  let { previous: r, next: t } = e;
  return (r == null ? void 0 : r.type) === "sentence" && ((a = z(false, r.children, -1)) == null ? void 0 : a.type) === "word" && !z(false, r.children, -1).hasTrailingPunctuation || (t == null ? void 0 : t.type) === "sentence" && ((i = t.children[0]) == null ? void 0 : i.type) === "word" && !t.children[0].hasLeadingPunctuation;
}
function Tf(e, r, t) {
  var a;
  let { node: n } = e;
  if (Lf(e)) {
    let i = [""], u = Rr(r.originalText.slice(n.position.start.offset, n.position.end.offset));
    for (let o of u) {
      if (o.type === "word") {
        i.push([i.pop(), o.value]);
        continue;
      }
      let s = Bt(e, o.value, r.proseWrap, true);
      if (G(s) === Y) {
        i.push([i.pop(), s]);
        continue;
      }
      i.push(s, "");
    }
    return ze(i);
  }
  switch (n.type) {
    case "front-matter":
      return r.originalText.slice(n.position.start.offset, n.position.end.offset);
    case "root":
      return n.children.length === 0 ? "" : [Sf(e, r, t), L];
    case "paragraph":
      return Ui(e, r, t);
    case "sentence":
      return Yi(e, t);
    case "word": {
      let i = R(false, R(false, n.value, "*", String.raw`\*`), new RegExp([`(^|${Se.source})(_+)`, `(_+)(${Se.source}|$)`].join("|"), "gu"), (s, l, c, f, p) => R(false, c ? `${l}${c}` : `${f}${p}`, "_", String.raw`\_`)), u = (s, l, c) => s.type === "sentence" && c === 0, o = (s, l, c) => Ct(s.children[c - 1]);
      return i !== n.value && (e.match(void 0, u, o) || e.match(void 0, u, (s, l, c) => s.type === "emphasis" && c === 0, o)) && (i = i.replace(/^(\\?[*_])+/u, (s) => R(false, s, "\\", ""))), i;
    }
    case "whitespace": {
      let { next: i } = e, u = i && /^>|^(?:[*+-]|#{1,6}|\d+[).])$/u.test(i.value) ? "never" : r.proseWrap;
      return Bt(e, n.value, u);
    }
    case "emphasis": {
      let i;
      if (Ct(n.children[0])) i = r.originalText[n.position.start.offset];
      else {
        let u = ji(e), o = ((a = e.parent) == null ? void 0 : a.type) === "strong" && ji(e.ancestors);
        i = u || o || e.hasAncestor((s) => s.type === "emphasis") ? "*" : "_";
      }
      return [i, j(e, r, t), i];
    }
    case "strong":
      return ["**", j(e, r, t), "**"];
    case "delete":
      return ["~~", j(e, r, t), "~~"];
    case "inlineCode": {
      let i = r.proseWrap === "preserve" ? n.value : R(false, n.value, `
`, " "), u = ei(i, "`"), o = "`".repeat(u || 1), s = i.startsWith("`") || i.endsWith("`") || /^[\n ]/u.test(i) && /[\n ]$/u.test(i) && /[^\n ]/u.test(i) ? " " : "";
      return [o, s, i, s, o];
    }
    case "wikiLink": {
      let i = "";
      return r.proseWrap === "preserve" ? i = n.value : i = R(false, n.value, /[\t\n]+/gu, " "), ["[[", i, "]]"];
    }
    case "link":
      switch (r.originalText[n.position.start.offset]) {
        case "<": {
          let i = "mailto:";
          return ["<", n.url.startsWith(i) && r.originalText.slice(n.position.start.offset + 1, n.position.start.offset + 1 + i.length) !== i ? n.url.slice(i.length) : n.url, ">"];
        }
        case "[":
          return ["[", j(e, r, t), "](", Tt(n.url, ")"), Ur(n.title, r), ")"];
        default:
          return r.originalText.slice(n.position.start.offset, n.position.end.offset);
      }
    case "image":
      return ["![", n.alt || "", "](", Tt(n.url, ")"), Ur(n.title, r), ")"];
    case "blockquote":
      return ["> ", Ae("> ", j(e, r, t))];
    case "heading":
      return ["#".repeat(n.depth) + " ", j(e, r, t)];
    case "code": {
      if (n.isIndented) {
        let o = " ".repeat(4);
        return Ae(o, [o, be(n.value, L)]);
      }
      let i = r.__inJsTemplate ? "~" : "`", u = i.repeat(Math.max(3, Sr(n.value, i) + 1));
      return [u, n.lang || "", n.meta ? " " + n.meta : "", L, be(Nr(n, r.originalText), L), L, u];
    }
    case "html": {
      let { parent: i, isLast: u } = e, o = i.type === "root" && u ? n.value.trimEnd() : n.value, s = /^<!--.*-->$/su.test(o);
      return be(o, s ? L : _e(nr));
    }
    case "list": {
      let i = Hi(n, e.parent), u = ki(n, r);
      return j(e, r, t, { processor(o) {
        let s = c(), l = o.node;
        if (l.children.length === 2 && l.children[1].type === "html" && l.children[0].position.start.column !== l.children[1].position.start.column) return [s, Wi(o, r, t, s)];
        return [s, Ae(" ".repeat(s.length), Wi(o, r, t, s))];
        function c() {
          let f = n.ordered ? (o.isFirst ? n.start : u ? 1 : n.start + o.index) + (i % 2 === 0 ? ". " : ") ") : i % 2 === 0 ? "- " : "* ";
          return (n.isAligned || n.hasIndentedCodeblock) && n.ordered ? qf(f, r) : f;
        }
      } });
    }
    case "thematicBreak": {
      let { ancestors: i } = e, u = i.findIndex((s) => s.type === "list");
      return u === -1 ? "---" : Hi(i[u], i[u + 1]) % 2 === 0 ? "***" : "---";
    }
    case "linkReference":
      return ["[", j(e, r, t), "]", n.referenceType === "full" ? qt(n) : n.referenceType === "collapsed" ? "[]" : ""];
    case "imageReference":
      switch (n.referenceType) {
        case "full":
          return ["![", n.alt || "", "]", qt(n)];
        default:
          return ["![", n.alt, "]", n.referenceType === "collapsed" ? "[]" : ""];
      }
    case "definition": {
      let i = r.proseWrap === "always" ? qr : " ";
      return Ue([qt(n), ":", ir([i, Tt(n.url), n.title === null ? "" : [i, Ur(n.title, r, false)]])]);
    }
    case "footnote":
      return ["[^", j(e, r, t), "]"];
    case "footnoteReference":
      return Qi(n);
    case "footnoteDefinition": {
      let i = n.children.length === 1 && n.children[0].type === "paragraph" && (r.proseWrap === "never" || r.proseWrap === "preserve" && n.children[0].position.start.line === n.children[0].position.end.line);
      return [Qi(n), ": ", i ? j(e, r, t) : Ue([Ae(" ".repeat(4), j(e, r, t, { processor: ({ isFirst: u }) => u ? Ue([_r, t()]) : t() }))])];
    }
    case "table":
      return Mi(e, r, t);
    case "tableCell":
      return j(e, r, t);
    case "break":
      return /\s/u.test(r.originalText[n.position.start.offset]) ? ["  ", _e(nr)] : ["\\", L];
    case "liquidNode":
      return be(n.value, L);
    case "import":
    case "export":
    case "jsx":
      return n.value;
    case "esComment":
      return ["{/* ", n.value, " */}"];
    case "math":
      return ["$$", L, n.value ? [be(n.value, L), L] : "", "$$"];
    case "inlineMath":
      return r.originalText.slice(Pe(n), Oe(n));
    case "tableRow":
    case "listItem":
    case "text":
    default:
      throw new ni(n, "Markdown");
  }
}
function Wi(e, r, t, n) {
  let { node: a } = e, i = a.checked === null ? "" : a.checked ? "[x] " : "[ ] ";
  return [i, j(e, r, t, { processor({ node: u, isFirst: o }) {
    if (o && u.type !== "list") return Ae(" ".repeat(i.length), t());
    let s = " ".repeat(Rf(r.tabWidth - n.length, 0, 3));
    return [s, Ae(s, t())];
  } })];
}
function qf(e, r) {
  let t = n();
  return e + " ".repeat(t >= 4 ? 0 : t);
  function n() {
    let a = e.length % r.tabWidth;
    return a === 0 ? 0 : r.tabWidth - a;
  }
}
function Hi(e, r) {
  return _f(e, r, (t) => t.ordered === e.ordered);
}
function _f(e, r, t) {
  let n = -1;
  for (let a of r.children) if (a.type === e.type && t(a) ? n++ : n = -1, a === e) return n;
}
function Sf(e, r, t) {
  let n = [], a = null, { children: i } = e.node;
  for (let [u, o] of i.entries()) switch (_t(o)) {
    case "start":
      a === null && (a = { index: u, offset: o.position.end.offset });
      break;
    case "end":
      a !== null && (n.push({ start: a, end: { index: u, offset: o.position.start.offset } }), a = null);
      break;
  }
  return j(e, r, t, { processor({ index: u }) {
    if (n.length > 0) {
      let o = n[0];
      if (u === o.start.index) return [Ki(i[o.start.index]), r.originalText.slice(o.start.offset, o.end.offset), Ki(i[o.end.index])];
      if (o.start.index < u && u < o.end.index) return false;
      if (u === o.end.index) return n.shift(), false;
    }
    return t();
  } });
}
function j(e, r, t, n = {}) {
  let { processor: a = t } = n, i = [];
  return e.each(() => {
    let u = a(e);
    u !== false && (i.length > 0 && Pf(e) && (i.push(L), (Of(e, r) || Ji(e)) && i.push(L), Ji(e) && i.push(L)), i.push(u));
  }, "children"), i;
}
function Ki(e) {
  if (e.type === "html") return e.value;
  if (e.type === "paragraph" && Array.isArray(e.children) && e.children.length === 1 && e.children[0].type === "esComment") return ["{/* ", e.children[0].value, " */}"];
}
function _t(e) {
  let r;
  if (e.type === "html") r = e.value.match(/^<!--\s*prettier-ignore(?:-(start|end))?\s*-->$/u);
  else {
    let t;
    e.type === "esComment" ? t = e : e.type === "paragraph" && e.children.length === 1 && e.children[0].type === "esComment" && (t = e.children[0]), t && (r = t.value.match(/^prettier-ignore(?:-(start|end))?$/u));
  }
  return r ? r[1] || "next" : false;
}
function Pf({ node: e, parent: r }) {
  let t = vt.has(e.type), n = e.type === "html" && Ir.has(r.type);
  return !t && !n;
}
function Xi(e, r) {
  return e.type === "listItem" && (e.spread || r.originalText.charAt(e.position.end.offset - 1) === `
`);
}
function Of({ node: e, previous: r, parent: t }, n) {
  if (Xi(r, n) || e.type === "list" && t.type === "listItem" && r.type === "code") return true;
  let i = r.type === e.type && Bf.has(e.type), u = t.type === "listItem" && (e.type === "list" || !Xi(t, n)), o = _t(r) === "next", s = e.type === "html" && r.type === "html" && r.position.end.line + 1 === e.position.start.line, l = e.type === "html" && t.type === "listItem" && r.type === "paragraph" && r.position.end.line + 1 === e.position.start.line;
  return !(i || u || o || s || l);
}
function Ji({ node: e, previous: r }) {
  let t = r.type === "list", n = e.type === "code" && e.isIndented;
  return t && n;
}
function Lf(e) {
  let r = e.findAncestor((t) => t.type === "linkReference" || t.type === "imageReference");
  return r && (r.type !== "linkReference" || r.referenceType !== "full");
}
var If = (e, r) => {
  for (let t of r) e = R(false, e, t, encodeURIComponent(t));
  return e;
};
function Tt(e, r = []) {
  let t = [" ", ...Array.isArray(r) ? r : [r]];
  return new RegExp(t.map((n) => le(n)).join("|"), "u").test(e) ? `<${If(e, "<>")}>` : e;
}
function Ur(e, r, t = true) {
  if (!e) return "";
  if (t) return " " + Ur(e, r, false);
  if (e = R(false, e, /\\(?=["')])/gu, ""), e.includes('"') && e.includes("'") && !e.includes(")")) return `(${e})`;
  let n = ti(e, r.singleQuote);
  return e = R(false, e, "\\", "\\\\"), e = R(false, e, n, `\\${n}`), `${n}${e}${n}`;
}
function Rf(e, r, t) {
  return Math.max(r, Math.min(e, t));
}
function Nf(e) {
  return e.index > 0 && _t(e.previous) === "next";
}
function qt(e) {
  return `[${(0, Zi.default)(e.label)}]`;
}
function Qi(e) {
  return `[^${e.label}]`;
}
var Mf = { preprocess: Gi, print: Tf, embed: Bi, massageAstNode: Di, hasPrettierIgnore: Nf, insertPragma: ci, getVisitorKeys: _i }, eu = Mf;
var ru = [{ name: "Markdown", type: "prose", extensions: [".md", ".livemd", ".markdown", ".mdown", ".mdwn", ".mkd", ".mkdn", ".mkdown", ".ronn", ".scd", ".workbook"], tmScope: "text.md", aceMode: "markdown", aliases: ["md", "pandoc"], codemirrorMode: "gfm", codemirrorMimeType: "text/x-gfm", filenames: ["contents.lr", "README"], wrap: true, parsers: ["markdown"], vscodeLanguageIds: ["markdown"], linguistLanguageId: 222 }, { name: "MDX", type: "prose", extensions: [".mdx"], tmScope: "text.md", aceMode: "markdown", aliases: ["md", "pandoc"], codemirrorMode: "gfm", codemirrorMimeType: "text/x-gfm", filenames: [], wrap: true, parsers: ["mdx"], vscodeLanguageIds: ["mdx"], linguistLanguageId: 222 }];
var St = { singleQuote: { category: "Common", type: "boolean", default: false, description: "Use single quotes instead of double quotes." }, proseWrap: { category: "Common", type: "choice", default: "preserve", description: "How to wrap prose.", choices: [{ value: "always", description: "Wrap prose if it exceeds the print width." }, { value: "never", description: "Do not wrap prose." }, { value: "preserve", description: "Wrap prose as-is." }] } };
var Uf = { proseWrap: St.proseWrap, singleQuote: St.singleQuote }, tu = Uf;
var zn = {};
$n(zn, { markdown: () => tF, mdx: () => nF, remark: () => tF });
var gl = Me(iu()), El = Me(gu()), vl = Me(pc()), Cl = Me(al());
var Hm = /^import\s/u, Km = /^export\s/u, ol = String.raw`[a-z][a-z0-9]*(\.[a-z][a-z0-9]*)*|`, sl = /<!---->|<!---?[^>-](?:-?[^-])*-->/u, Xm = /^\{\s*\/\*(.*)\*\/\s*\}/u, Jm = `

`, cl = (e) => Hm.test(e), Un = (e) => Km.test(e), ll = (e, r) => {
  let t = r.indexOf(Jm), n = r.slice(0, t);
  if (Un(n) || cl(n)) return e(n)({ type: Un(n) ? "export" : "import", value: n });
}, fl = (e, r) => {
  let t = Xm.exec(r);
  if (t) return e(t[0])({ type: "esComment", value: t[1].trim() });
};
ll.locator = (e) => Un(e) || cl(e) ? -1 : 1;
fl.locator = (e, r) => e.indexOf("{", r);
var Dl = function() {
  let { Parser: e } = this, { blockTokenizers: r, blockMethods: t, inlineTokenizers: n, inlineMethods: a } = e.prototype;
  r.esSyntax = ll, n.esComment = fl, t.splice(t.indexOf("paragraph"), 0, "esSyntax"), a.splice(a.indexOf("text"), 0, "esComment");
};
var Qm = function() {
  let e = this.Parser.prototype;
  e.blockMethods = ["frontMatter", ...e.blockMethods], e.blockTokenizers.frontMatter = r;
  function r(t, n) {
    let a = Ge(n);
    if (a.frontMatter) return t(a.frontMatter.raw)(a.frontMatter);
  }
  r.onlyAtStart = true;
}, pl = Qm;
function Zm() {
  return (e) => ye(e, (r, t, [n]) => r.type !== "html" || sl.test(r.value) || Ir.has(n.type) ? r : { ...r, type: "jsx" });
}
var hl = Zm;
var eF = function() {
  let e = this.Parser.prototype, r = e.inlineMethods;
  r.splice(r.indexOf("text"), 0, "liquid"), e.inlineTokenizers.liquid = t;
  function t(n, a) {
    let i = a.match(/^(\{%.*?%\}|\{\{.*?\}\})/su);
    if (i) return n(i[0])({ type: "liquidNode", value: i[0] });
  }
  t.locator = function(n, a) {
    return n.indexOf("{", a);
  };
}, dl = eF;
var rF = function() {
  let e = "wikiLink", r = /^\[\[(?<linkContents>.+?)\]\]/su, t = this.Parser.prototype, n = t.inlineMethods;
  n.splice(n.indexOf("link"), 0, e), t.inlineTokenizers.wikiLink = a;
  function a(i, u) {
    let o = r.exec(u);
    if (o) {
      let s = o.groups.linkContents.trim();
      return i(o[0])({ type: e, value: s });
    }
  }
  a.locator = function(i, u) {
    return i.indexOf("[", u);
  };
}, ml = rF;
function bl({ isMDX: e }) {
  return (r) => {
    let t = (0, Cl.default)().use(vl.default, { commonmark: true, ...e && { blocks: [ol] } }).use(gl.default).use(pl).use(El.default).use(e ? Dl : Fl).use(dl).use(e ? hl : Fl).use(ml);
    return t.run(t.parse(r));
  };
}
function Fl() {
}
var Al = { astFormat: "mdast", hasPragma: oi, hasIgnorePragma: si, locStart: Pe, locEnd: Oe }, tF = { ...Al, parse: bl({ isMDX: false }) }, nF = { ...Al, parse: bl({ isMDX: true }) };
var iF = { mdast: eu };
var p2 = Gn;
export {
  p2 as default,
  ru as languages,
  tu as options,
  zn as parsers,
  iF as printers
};
