// ../../../../../localhost/Pushword/packages/newsletter/node_modules/preact/dist/preact.module.js
var e, t, n, r, i, a, o, s, c, l, u, d, f, p, m, h = {}, g = [], _ = /acit|ex(?:s|g|n|p|$)|rph|grid|ows|mnc|ntw|ine[ch]|zoo|^ord|itera/i, v = Array.isArray;
function y(e, t) {
	for (var n in t) e[n] = t[n];
	return e;
}
function b(e) {
	e && e.parentNode && e.parentNode.removeChild(e);
}
function x(t, n, r) {
	var i, a, o, s = {};
	for (o in n) o == "key" ? i = n[o] : o == "ref" ? a = n[o] : s[o] = n[o];
	if (arguments.length > 2 && (s.children = arguments.length > 3 ? e.call(arguments, 2) : r), typeof t == "function" && t.defaultProps != null) for (o in t.defaultProps) s[o] === void 0 && (s[o] = t.defaultProps[o]);
	return S(t, s, i, a, null);
}
function S(e, r, i, a, o) {
	var s = {
		type: e,
		props: r,
		key: i,
		ref: a,
		__k: null,
		__: null,
		__b: 0,
		__e: null,
		__c: null,
		constructor: void 0,
		__v: o ?? ++n,
		__i: -1,
		__u: 0
	};
	return o == null && t.vnode != null && t.vnode(s), s;
}
function C() {
	return { current: null };
}
function w(e) {
	return e.children;
}
function T(e, t) {
	this.props = e, this.context = t;
}
function ee(e, t) {
	if (t == null) return e.__ ? ee(e.__, e.__i + 1) : null;
	for (var n; t < e.__k.length; t++) if ((n = e.__k[t]) != null && n.__e != null) return n.__e;
	return typeof e.type == "function" ? ee(e) : null;
}
function te(e) {
	if (e.__P && e.__d) {
		var n = e.__v, r = n.__e, i = [], a = [], o = y({}, n);
		o.__v = n.__v + 1, t.vnode && t.vnode(o), fe(e.__P, o, n, e.__n, e.__P.namespaceURI, 32 & n.__u ? [r] : null, i, r ?? ee(n), !!(32 & n.__u), a), o.__v = n.__v, o.__.__k[o.__i] = o, me(i, o, a), n.__e = n.__ = null, o.__e != r && E(o);
	}
}
function E(e) {
	if ((e = e.__) != null && e.__c != null) return e.__e = e.__c.base = null, e.__k.some(function(t) {
		if (t != null && t.__e != null) return e.__e = e.__c.base = t.__e;
	}), E(e);
}
function ne(e) {
	(!e.__d && (e.__d = !0) && r.push(e) && !re.__r++ || i != t.debounceRendering) && ((i = t.debounceRendering) || a)(re);
}
function re() {
	try {
		for (var e, t = 1; r.length;) r.length > t && r.sort(o), e = r.shift(), t = r.length, te(e);
	} finally {
		r.length = re.__r = 0;
	}
}
function ie(e, t, n, r, i, a, o, s, c, l, u) {
	var d, f, p, m, _, v, y = r && r.__k || g, b = t.length;
	for (c = ae(n, t, y, c, b), d = 0; d < b; d++) (p = n.__k[d]) != null && (f = p.__i != -1 && y[p.__i] || h, p.__i = d, v = fe(e, p, f, i, a, o, s, c, l, u), m = p.__e, p.ref && f.ref != p.ref && (f.ref && _e(f.ref, null, p), u.push(p.ref, p.__c || m, p)), _ == null && m != null && (_ = m), 4 & p.__u ? (c = oe(p, c, e), f.__e && (f.__e = null)) : typeof p.type == "function" && v !== void 0 ? c = v : m && (c = m.nextSibling), p.__u &= -7);
	return n.__e = _, c;
}
function ae(e, t, n, r, i) {
	var a, o, s, c, l, u = n.length, d = u, f = 0;
	for (e.__k = Array(i), a = 0; a < i; a++) (o = t[a]) != null && typeof o != "boolean" && typeof o != "function" ? (typeof o == "string" || typeof o == "number" || typeof o == "bigint" || o.constructor == String ? o = e.__k[a] = S(null, o, null, null, null) : v(o) ? o = e.__k[a] = S(w, { children: o }, null, null, null) : o.constructor === void 0 && o.__b > 0 ? o = e.__k[a] = S(o.type, o.props, o.key, o.ref ? o.ref : null, o.__v) : e.__k[a] = o, c = a + f, o.__ = e, o.__b = e.__b + 1, s = null, (l = o.__i = ce(o, n, c, d)) != -1 && (d--, (s = n[l]) && (s.__u |= 2)), s == null || s.__v == null ? (l == -1 && (i > u ? f-- : i < u && f++), typeof o.type != "function" && (o.__u |= 4)) : l != c && (l == c - 1 ? f-- : l == c + 1 ? f++ : (l > c ? f-- : f++, o.__u |= 4))) : e.__k[a] = null;
	if (d) for (a = 0; a < u; a++) (s = n[a]) != null && !(2 & s.__u) && (s.__e == r && (r = ee(s)), ve(s, s));
	return r;
}
function oe(e, t, n) {
	var r, i;
	if (typeof e.type == "function") {
		for (r = e.__k, i = 0; r && i < r.length; i++) r[i] && (r[i].__ = e, t = oe(r[i], t, n));
		return t;
	}
	e.__e != t && (t && e.type && !t.parentNode && (t = ee(e)), t = n.insertBefore(e.__e, t || null));
	do
		t &&= t.nextSibling;
	while (t != null && t.nodeType == 8);
	return t;
}
function se(e, t) {
	return t ||= [], e == null || typeof e == "boolean" || (v(e) ? e.some(function(e) {
		se(e, t);
	}) : t.push(e)), t;
}
function ce(e, t, n, r) {
	var i, a, o, s = e.key, c = e.type, l = t[n], u = l != null && !(2 & l.__u);
	if (l === null && s == null || u && s == l.key && c == l.type) return n;
	if (r > +!!u) {
		for (i = n - 1, a = n + 1; i >= 0 || a < t.length;) if ((l = t[o = i >= 0 ? i-- : a++]) != null && !(2 & l.__u) && s == l.key && c == l.type) return o;
	}
	return -1;
}
function le(e, t, n) {
	t[0] == "-" ? e.setProperty(t, n ?? "") : e[t] = n == null ? "" : typeof n != "number" || _.test(t) ? n : n + "px";
}
function ue(e, t, n, r, i) {
	var a, o;
	n: if (t == "style") {
		if (typeof n == "string") e.style.cssText = n;
		else {
			if (typeof r == "string" && (e.style.cssText = r = ""), r) for (t in r) n && t in n || le(e.style, t, "");
			if (n) for (t in n) r && n[t] == r[t] || le(e.style, t, n[t]);
		}
	} else if (t[0] == "o" && t[1] == "n") a = t != (t = t.replace(u, "$1")), o = t.toLowerCase(), t = o in e || t == "onFocusOut" || t == "onFocusIn" ? o.slice(2) : t.slice(2), e.l ||= {}, e.l[t + a] = n, n ? r ? n[l] = r[l] : (n[l] = d, e.addEventListener(t, a ? p : f, a)) : e.removeEventListener(t, a ? p : f, a);
	else {
		if (i == "http://www.w3.org/2000/svg") t = t.replace(/xlink(H|:h)/, "h").replace(/sName$/, "s");
		else if (t != "width" && t != "height" && t != "href" && t != "list" && t != "form" && t != "tabIndex" && t != "download" && t != "rowSpan" && t != "colSpan" && t != "role" && t != "popover" && t in e) try {
			e[t] = n ?? "";
			break n;
		} catch {}
		typeof n == "function" || (n == null || !1 === n && t[4] != "-" ? e.removeAttribute(t) : e.setAttribute(t, t == "popover" && n == 1 ? "" : n));
	}
}
function de(e) {
	return function(n) {
		if (this.l) {
			var r = this.l[n.type + e];
			if (n[c] == null) n[c] = d++;
			else if (n[c] < r[l]) return;
			return r(t.event ? t.event(n) : n);
		}
	};
}
function fe(e, n, r, i, a, o, s, c, l, u) {
	var d, f, p, m, h, _, x, S, C, te, E, ne, re, ae, oe, se, ce = n.type;
	if (n.constructor !== void 0) return null;
	128 & r.__u && (l = !!(32 & r.__u), o = [c = n.__e = r.__e]), (d = t.__b) && d(n);
	n: if (typeof ce == "function") {
		f = s.length;
		try {
			if (C = n.props, te = ce.prototype && ce.prototype.render, E = (d = ce.contextType) && i[d.__c], ne = d ? E ? E.props.value : d.__ : i, r.__c ? S = (p = n.__c = r.__c).__ = p.__E : (te ? n.__c = p = new ce(C, ne) : (n.__c = p = new T(C, ne), p.constructor = ce, p.render = ye), E && E.sub(p), p.state || (p.state = {}), p.__n = i, m = p.__d = !0, p.__h = [], p._sb = []), te && p.__s == null && (p.__s = p.state), te && ce.getDerivedStateFromProps != null && (p.__s == p.state && (p.__s = y({}, p.__s)), y(p.__s, ce.getDerivedStateFromProps(C, p.__s))), h = p.props, _ = p.state, p.__v = n, m) te && ce.getDerivedStateFromProps == null && p.componentWillMount != null && p.componentWillMount(), te && p.componentDidMount != null && p.__h.push(p.componentDidMount);
			else {
				if (te && ce.getDerivedStateFromProps == null && C !== h && p.componentWillReceiveProps != null && p.componentWillReceiveProps(C, ne), n.__v == r.__v || !p.__e && p.shouldComponentUpdate != null && !1 === p.shouldComponentUpdate(C, p.__s, ne)) {
					n.__v != r.__v && (p.props = C, p.state = p.__s, p.__d = !1), n.__e = r.__e, n.__k = r.__k, n.__k.some(function(e) {
						e && (e.__ = n);
					}), g.push.apply(p.__h, p._sb), p._sb = [], p.__h.length && s.push(p), c = ee(r);
					break n;
				}
				p.componentWillUpdate != null && p.componentWillUpdate(C, p.__s, ne), te && p.componentDidUpdate != null && p.__h.push(function() {
					p.componentDidUpdate(h, _, x);
				});
			}
			if (p.context = ne, p.props = C, p.__P = e, p.__e = !1, re = t.__r, ae = 0, te) p.state = p.__s, p.__d = !1, re && re(n), d = p.render(p.props, p.state, p.context), g.push.apply(p.__h, p._sb), p._sb = [];
			else do
				p.__d = !1, re && re(n), d = p.render(p.props, p.state, p.context), p.state = p.__s;
			while (p.__d && ++ae < 25);
			p.state = p.__s, p.getChildContext != null && (i = y(y({}, i), p.getChildContext())), te && !m && p.getSnapshotBeforeUpdate != null && (x = p.getSnapshotBeforeUpdate(h, _)), oe = d != null && d.type === w && d.key == null ? he(d.props.children) : d, c = ie(e, v(oe) ? oe : [oe], n, r, i, a, o, s, c, l, u), p.base = n.__e, n.__u &= -161, p.__h.length && s.push(p), S && (p.__E = p.__ = null);
		} catch (e) {
			if (s.length = f, n.__v = null, l || o != null) {
				if (e.then) {
					for (n.__u |= l ? 160 : 128; c && c.nodeType == 8 && c.nextSibling;) c = c.nextSibling;
					o != null && (o[o.indexOf(c)] = null), n.__e = c;
				} else if (o != null) for (se = o.length; se--;) b(o[se]);
			} else n.__e = r.__e;
			n.__k ??= r.__k || [], e.then || pe(n), t.__e(e, n, r);
		}
	} else o == null && n.__v == r.__v ? (n.__k = r.__k, n.__e = r.__e) : c = n.__e = ge(r.__e, n, r, i, a, o, s, l, u);
	return (d = t.diffed) && d(n), 128 & n.__u ? void 0 : c;
}
function pe(e) {
	e && (e.__c && (e.__c.__e = !0), e.__k && e.__k.some(pe));
}
function me(e, n, r) {
	for (var i = 0; i < r.length; i++) _e(r[i], r[++i], r[++i]);
	t.__c && t.__c(n, e), e.some(function(n) {
		try {
			e = n.__h, n.__h = [], e.some(function(e) {
				e.call(n);
			});
		} catch (e) {
			t.__e(e, n.__v);
		}
	});
}
function he(e) {
	return typeof e != "object" || !e || e.__b > 0 ? e : v(e) ? e.map(he) : e.constructor === void 0 ? y({}, e) : null;
}
function ge(n, r, i, a, o, s, c, l, u) {
	var d, f, p, m, g, _, y, x = i.props || h, S = r.props, C = r.type;
	if (C == "svg" ? o = "http://www.w3.org/2000/svg" : C == "math" ? o = "http://www.w3.org/1998/Math/MathML" : o ||= "http://www.w3.org/1999/xhtml", s != null) {
		for (d = 0; d < s.length; d++) if ((g = s[d]) && "setAttribute" in g == !!C && (C ? g.localName == C : g.nodeType == 3)) {
			n = g, s[d] = null;
			break;
		}
	}
	if (n == null) {
		if (C == null) return document.createTextNode(S);
		n = document.createElementNS(o, C, S.is && S), l &&= (t.__m && t.__m(r, s), !1), s = null;
	}
	if (C == null) x === S || l && n.data == S || (n.data = S);
	else {
		if (s = C == "textarea" && S.defaultValue != null ? null : s && e.call(n.childNodes), !l && s != null) for (x = {}, d = 0; d < n.attributes.length; d++) x[(g = n.attributes[d]).name] = g.value;
		for (d in x) g = x[d], d == "dangerouslySetInnerHTML" ? p = g : d == "children" || d in S || d == "value" && "defaultValue" in S || d == "checked" && "defaultChecked" in S || ue(n, d, null, g, o);
		for (d in S) g = S[d], d == "children" ? m = g : d == "dangerouslySetInnerHTML" ? f = g : d == "value" ? _ = g : d == "checked" ? y = g : l && typeof g != "function" || x[d] === g || ue(n, d, g, x[d], o);
		if (f) l || p && (f.__html == p.__html || f.__html == n.innerHTML) || (n.innerHTML = f.__html), r.__k = [];
		else if (p && (n.innerHTML = ""), ie(r.type == "template" ? n.content : n, v(m) ? m : [m], r, i, a, C == "foreignObject" ? "http://www.w3.org/1999/xhtml" : o, s, c, s ? s[0] : i.__k && ee(i, 0), l, u), s != null) for (d = s.length; d--;) b(s[d]);
		l && C != "textarea" || (d = "value", C == "progress" && _ == null ? n.removeAttribute("value") : _ != null && (_ !== n[d] || C == "progress" && !_ || C == "option" && _ != x[d]) && ue(n, d, _, x[d], o), d = "checked", y != null && y != n[d] && ue(n, d, y, x[d], o));
	}
	return n;
}
function _e(e, n, r) {
	try {
		if (typeof e == "function") {
			var i = typeof e.__u == "function";
			i && e.__u(), i && n == null || (e.__u = e(n));
		} else e.current = n;
	} catch (e) {
		t.__e(e, r);
	}
}
function ve(e, n, r) {
	var i, a;
	if (t.unmount && t.unmount(e), (i = e.ref) && (i.current && i.current != e.__e || _e(i, null, n)), (i = e.__c) != null) {
		if (i.componentWillUnmount) try {
			i.componentWillUnmount();
		} catch (e) {
			t.__e(e, n);
		}
		i.base = i.__P = i.__n = null;
	}
	if (i = e.__k) for (a = 0; a < i.length; a++) i[a] && ve(i[a], n, r || typeof e.type != "function");
	r || b(e.__e), e.__c = e.__ = e.__e = void 0;
}
function ye(e, t, n) {
	return this.constructor(e, n);
}
function be(n, r, i) {
	var a, o, s, c;
	r == document && (r = document.documentElement), t.__ && t.__(n, r), o = (a = typeof i == "function") ? null : i && i.__k || r.__k, s = [], c = [], fe(r, n = (!a && i || r).__k = x(w, null, [n]), o || h, h, r.namespaceURI, !a && i ? [i] : o ? null : r.firstChild ? e.call(r.childNodes) : null, s, !a && i ? i : o ? o.__e : r.firstChild, a, c), me(s, n, c), n.props.children = null;
}
function xe(e) {
	function t(e) {
		var n, r;
		return this.getChildContext || (n = /* @__PURE__ */ new Set(), (r = {})[t.__c] = this, this.getChildContext = function() {
			return r;
		}, this.componentWillUnmount = function() {
			n = null;
		}, this.shouldComponentUpdate = function(e) {
			this.props.value != e.value && n.forEach(function(e) {
				e.__e = !0, ne(e);
			});
		}, this.sub = function(e) {
			n.add(e);
			var t = e.componentWillUnmount;
			e.componentWillUnmount = function() {
				n && n.delete(e), t && t.call(e);
			};
		}), e.children;
	}
	return t.__c = "__cC" + m++, t.__ = e, t.Provider = t.__l = (t.Consumer = function(e, t) {
		return e.children(t);
	}).contextType = t, t;
}
e = g.slice, t = { __e: function(e, t, n, r) {
	for (var i, a, o; t = t.__;) if ((i = t.__c) && !i.__) try {
		if ((a = i.constructor) && a.getDerivedStateFromError != null && (i.setState(a.getDerivedStateFromError(e)), o = i.__d), i.componentDidCatch != null && (i.componentDidCatch(e, r || {}), o = i.__d), o) return i.__E = i;
	} catch (t) {
		e = t;
	}
	throw e;
} }, n = 0, T.prototype.setState = function(e, t) {
	var n = this.__s != null && this.__s != this.state ? this.__s : this.__s = y({}, this.state);
	typeof e == "function" && (e = e(y({}, n), this.props)), e && y(n, e), e != null && this.__v && (t && this._sb.push(t), ne(this));
}, T.prototype.forceUpdate = function(e) {
	this.__v && (this.__e = !0, e && this.__h.push(e), ne(this));
}, T.prototype.render = w, r = [], a = typeof Promise == "function" ? Promise.prototype.then.bind(Promise.resolve()) : setTimeout, o = function(e, t) {
	return e.__v.__b - t.__v.__b;
}, re.__r = 0, s = Math.random().toString(8), c = "__d" + s, l = "__a" + s, u = /(PointerCapture)$|Capture$/i, d = 0, f = de(!1), p = de(!0), m = 0;
// ../../../../../localhost/Pushword/packages/newsletter/node_modules/preact/jsx-runtime/dist/jsxRuntime.module.js
var Se = 0;
Array.isArray;
function D(e, n, r, i, a, o) {
	n ||= {};
	var s, c, l = n;
	if ("ref" in l) for (c in l = {}, n) c == "ref" ? s = n[c] : l[c] = n[c];
	var u = {
		type: e,
		props: l,
		key: r,
		ref: s,
		__k: null,
		__: null,
		__b: 0,
		__e: null,
		__c: null,
		constructor: void 0,
		__v: --Se,
		__i: -1,
		__u: 0,
		__source: a,
		__self: o
	};
	if (typeof e == "function" && (s = e.defaultProps)) for (c in s) l[c] === void 0 && (l[c] = s[c]);
	return t.vnode && t.vnode(u), u;
}
// ../../../../../localhost/Pushword/packages/newsletter/node_modules/preact/hooks/dist/hooks.module.js
var Ce, we, Te, Ee = [], De = t, Oe = De.__b, ke = De.__r, Ae = De.diffed, je = De.__c, Me = De.unmount, Ne = De.__;
function Pe() {
	for (var e; e = Ee.shift();) {
		var t = e.__H;
		if (e.__P && t) try {
			t.__h.some(Le), t.__h.some(Re), t.__h = [];
		} catch (n) {
			t.__h = [], De.__e(n, e.__v);
		}
	}
}
De.__b = function(e) {
	Ce = null, Oe && Oe(e);
}, De.__ = function(e, t) {
	e && t.__k && t.__k.__m && (e.__m = t.__k.__m), Ne && Ne(e, t);
}, De.__r = function(e) {
	ke && ke(e);
	var t = (Ce = e.__c).__H;
	t && (we === Ce ? (t.__h = [], Ce.__h = [], t.__.some(function(e) {
		e.__N && (e.__ = e.__N), e.u = e.__N = void 0;
	})) : (t.__h.some(Le), t.__h.some(Re), t.__h = [])), we = Ce;
}, De.diffed = function(e) {
	Ae && Ae(e);
	var t = e.__c;
	t && t.__H && (t.__H.__h.length && (Ee.push(t) !== 1 && Te === De.requestAnimationFrame || ((Te = De.requestAnimationFrame) || Ie)(Pe)), t.__H.__.some(function(e) {
		e.u &&= (e.__H = e.u, void 0);
	})), we = Ce = null;
}, De.__c = function(e, t) {
	t.some(function(e) {
		try {
			e.__h.some(Le), e.__h = e.__h.filter(function(e) {
				return !e.__ || Re(e);
			});
		} catch (n) {
			t.some(function(e) {
				e.__h &&= [];
			}), t = [], De.__e(n, e.__v);
		}
	}), je && je(e, t);
}, De.unmount = function(e) {
	Me && Me(e);
	var t, n = e.__c;
	n && n.__H && (n.__H.__.some(function(e) {
		try {
			Le(e);
		} catch (e) {
			t = e;
		}
	}), n.__H = void 0, t && De.__e(t, n.__v));
};
var Fe = typeof requestAnimationFrame == "function";
function Ie(e) {
	var t, n = function() {
		clearTimeout(r), Fe && cancelAnimationFrame(t), setTimeout(e);
	}, r = setTimeout(n, 35);
	Fe && (t = requestAnimationFrame(n));
}
function Le(e) {
	var t = Ce, n = e.__c;
	typeof n == "function" && (e.__c = void 0, n()), Ce = t;
}
function Re(e) {
	var t = Ce;
	e.__c = e.__(), Ce = t;
}
// ../../../../../localhost/Pushword/packages/newsletter/node_modules/preact/compat/dist/compat.module.js
function ze(e, t) {
	for (var n in t) e[n] = t[n];
	return e;
}
function Be(e, t) {
	for (var n in e) if (n !== "__source" && !(n in t)) return !0;
	for (var r in t) if (r !== "__source" && e[r] !== t[r]) return !0;
	return !1;
}
function Ve(e, t) {
	this.props = e, this.context = t;
}
(Ve.prototype = new T()).isPureReactComponent = !0, Ve.prototype.shouldComponentUpdate = function(e, t) {
	return Be(this.props, e) || Be(this.state, t);
};
var He = t.__b;
t.__b = function(e) {
	e.type && e.type.__f && e.ref && (e.props.ref = e.ref, e.ref = null), He && He(e);
}, typeof Symbol < "u" && Symbol.for;
var Ue = t.__e;
t.__e = function(e, t, n, r) {
	if (e.then) {
		for (var i, a = t; a = a.__;) if ((i = a.__c) && i.__c) return t.__e ?? (t.__e = n.__e, t.__k = n.__k || []), i.__c(e, t);
	}
	Ue(e, t, n, r);
};
var We = t.unmount;
function Ge(e, t, n) {
	return e && (e.__c && e.__c.__H && (e.__c.__H.__.forEach(function(e) {
		typeof e.__c == "function" && e.__c();
	}), e.__c.__H = null), (e = ze({}, e)).__c != null && (e.__c.__P === n && (e.__c.__P = t), e.__c.__e = !0, e.__c = null), e.__k = e.__k && e.__k.map(function(e) {
		return Ge(e, t, n);
	})), e;
}
function Ke(e, t, n) {
	return e && n && (e.__v = null, e.__k = e.__k && e.__k.map(function(e) {
		return Ke(e, t, n);
	}), e.__c && e.__c.__P === t && (e.__e && n.appendChild(e.__e), e.__c.__e = !0, e.__c.__P = n)), e;
}
function qe() {
	this.__u = 0, this.o = null, this.__b = null;
}
function Je(e) {
	var t = e.__ && e.__.__c;
	return t && t.__a && t.__a(e);
}
function Ye() {
	this.i = null, this.l = null;
}
t.unmount = function(e) {
	var t = e.__c;
	t && (t.__z = !0), t && t.__R && t.__R(), t && 32 & e.__u && (e.type = null), We && We(e);
}, (qe.prototype = new T()).__c = function(e, t) {
	var n = t.__c, r = this;
	r.o ??= [], r.o.push(n);
	var i = Je(r.__v), a = !1, o = function() {
		a || r.__z || (a = !0, n.__R = null, i ? i(c) : c());
	};
	n.__R = o;
	var s = n.__P;
	n.__P = null;
	var c = function() {
		if (!--r.__u) {
			if (r.state.__a) {
				var e = r.state.__a;
				r.__v.__k[0] = Ke(e, e.__c.__P, e.__c.__O);
			}
			var t;
			for (r.setState({ __a: r.__b = null }); t = r.o.pop();) t.__P = s, t.forceUpdate();
		}
	};
	r.__u++ || 32 & t.__u || r.setState({ __a: r.__b = r.__v.__k[0] }), e.then(o, o);
}, qe.prototype.componentWillUnmount = function() {
	this.o = [];
}, qe.prototype.render = function(e, t) {
	if (this.__b) {
		if (this.__v.__k) {
			var n = document.createElement("div"), r = this.__v.__k[0].__c;
			this.__v.__k[0] = Ge(this.__b, n, r.__O = r.__P);
		}
		this.__b = null;
	}
	var i = t.__a && x(w, null, e.fallback);
	return i && (i.__u &= -33), [x(w, null, t.__a ? null : e.children), i];
};
var Xe = function(e, t, n) {
	if (++n[1] === n[0] && e.l.delete(t), e.props.revealOrder && (e.props.revealOrder[0] !== "t" || !e.l.size)) for (n = e.i; n;) {
		for (; n.length > 3;) n.pop()();
		if (n[1] < n[0]) break;
		e.i = n = n[2];
	}
};
function Ze(e) {
	return this.getChildContext = function() {
		return e.context;
	}, e.children;
}
function Qe(e) {
	var t = this, n = e.h;
	if (t.componentWillUnmount = function() {
		be(null, t.v), t.v = null, t.h = null;
	}, t.h && t.h !== n && t.componentWillUnmount(), !t.v) {
		for (var r = t.__v; r !== null && !r.__m && r.__ !== null;) r = r.__;
		t.h = n, t.v = {
			nodeType: 1,
			parentNode: n,
			childNodes: [],
			__k: { __m: r.__m },
			contains: function() {
				return !0;
			},
			namespaceURI: n.namespaceURI,
			insertBefore: function(e, n) {
				this.childNodes.push(e), t.h.insertBefore(e, n);
			},
			removeChild: function(e) {
				this.childNodes.splice(this.childNodes.indexOf(e) >>> 1, 1), t.h.removeChild(e);
			}
		};
	}
	be(x(Ze, { context: t.context }, e.__v), t.v);
}
function $e(e, t) {
	var n = x(Qe, {
		__v: e,
		h: t
	});
	return n.containerInfo = t, n;
}
(Ye.prototype = new T()).__a = function(e) {
	var t = this, n = Je(t.__v), r = t.l.get(e);
	return r[0]++, function(i) {
		var a = function() {
			t.props.revealOrder ? (r.push(i), Xe(t, e, r)) : i();
		};
		n ? n(a) : a();
	};
}, Ye.prototype.render = function(e) {
	this.i = null, this.l = /* @__PURE__ */ new Map();
	var t = se(e.children);
	e.revealOrder && e.revealOrder[0] === "b" && t.reverse();
	for (var n = t.length; n--;) this.l.set(t[n], this.i = [
		1,
		0,
		this.i
	]);
	return e.children;
}, Ye.prototype.componentDidUpdate = Ye.prototype.componentDidMount = function() {
	var e = this;
	this.l.forEach(function(t, n) {
		Xe(e, n, t);
	});
};
var et = typeof Symbol < "u" && Symbol.for && Symbol.for("react.element") || 60103, tt = /^(?:accent|alignment|arabic|baseline|cap|clip(?!PathU)|color|dominant|fill|flood|font|glyph(?!R)|horiz|image(!S)|letter|lighting|marker(?!H|W|U)|overline|paint|pointer|shape|stop|strikethrough|stroke|text(?!L)|transform|underline|unicode|units|v|vector|vert|word|writing|x(?!C))[A-Z]/, nt = /^on(Ani|Tra|Tou|BeforeInp|Compo)/, rt = /[A-Z0-9]/g, it = typeof document < "u", at = function(e) {
	return (typeof Symbol < "u" && typeof Symbol() == "symbol" ? /fil|che|rad/ : /fil|che|ra/).test(e);
};
function ot(e, t, n) {
	return t.__k ?? (t.textContent = ""), be(e, t), typeof n == "function" && n(), e ? e.__c : null;
}
T.prototype.isReactComponent = !0, [
	"componentWillMount",
	"componentWillReceiveProps",
	"componentWillUpdate"
].forEach(function(e) {
	Object.defineProperty(T.prototype, e, {
		configurable: !0,
		get: function() {
			return this["UNSAFE_" + e];
		},
		set: function(t) {
			Object.defineProperty(this, e, {
				configurable: !0,
				writable: !0,
				value: t
			});
		}
	});
});
var st = t.event;
t.event = function(e) {
	return st && (e = st(e)), e.persist = function() {}, e.isPropagationStopped = function() {
		return this.cancelBubble;
	}, e.isDefaultPrevented = function() {
		return this.defaultPrevented;
	}, e.nativeEvent = e;
};
var ct = {
	configurable: !0,
	get: function() {
		return this.class;
	}
}, lt = t.vnode;
t.vnode = function(e) {
	typeof e.type == "string" && function(e) {
		var t = e.props, n = e.type, r = {}, i = n.indexOf("-") == -1;
		for (var a in t) {
			var o = t[a];
			if (!(a === "value" && "defaultValue" in t && o == null || it && a === "children" && n === "noscript" || a === "class" || a === "className")) {
				var s = a.toLowerCase();
				a === "defaultValue" && "value" in t && t.value == null ? a = "value" : a === "download" && !0 === o ? o = "" : s === "translate" && o === "no" ? o = !1 : s[0] === "o" && s[1] === "n" ? s === "ondoubleclick" ? a = "ondblclick" : s !== "onchange" || n !== "input" && n !== "textarea" || at(t.type) ? s === "onfocus" ? a = "onfocusin" : s === "onblur" ? a = "onfocusout" : nt.test(a) && (a = s) : s = a = "oninput" : i && tt.test(a) ? a = a.replace(rt, "-$&").toLowerCase() : o === null && (o = void 0), s === "oninput" && r[a = s] && (a = "oninputCapture"), r[a] = o;
			}
		}
		n == "select" && (r.multiple && Array.isArray(r.value) && (r.value = se(t.children).forEach(function(e) {
			e.props.selected = r.value.indexOf(e.props.value) != -1;
		})), r.defaultValue != null && (r.value = se(t.children).forEach(function(e) {
			e.props.selected = r.multiple ? r.defaultValue.indexOf(e.props.value) != -1 : r.defaultValue == e.props.value;
		}))), t.class && !t.className ? (r.class = t.class, Object.defineProperty(r, "className", ct)) : t.className && (r.class = r.className = t.className), e.props = r;
	}(e), e.$$typeof = et, lt && lt(e);
};
var ut = t.__r;
t.__r = function(e) {
	ut && ut(e), e.__c;
};
var dt = t.diffed;
t.diffed = function(e) {
	dt && dt(e);
	var t = e.props, n = e.__e;
	n != null && e.type === "textarea" && "value" in t && t.value !== n.value && (n.value = t.value == null ? "" : t.value);
};
function ft(e) {
	return !!e && e.$$typeof === et;
}
function pt(e) {
	return !!e.__k && (be(null, e), !0);
}
var mt = function(e, n) {
	var r, i = t.debounceRendering;
	t.debounceRendering = function(e) {
		r = e;
	};
	try {
		var a = e(n);
		return r && r(), a;
	} finally {
		t.debounceRendering = i;
	}
};
// ../../../../../localhost/Pushword/packages/newsletter/node_modules/preact/compat/client.mjs
function ht(e) {
	return {
		render: function(t) {
			ot(t, e);
		},
		unmount: function() {
			pt(e);
		}
	};
}
// ../../../../../localhost/Pushword/packages/newsletter/node_modules/temporal-polyfill/chunks/root.js
var gt = globalThis.Temporal, _t = (e, t) => `Non-positive ${e}: ${t}`, vt = (e, t) => `Non-finite ${e}: ${t}`, yt = (e) => `Cannot convert bigint to ${e}`, bt = "Invalid object", xt = (e, t, n, r) => St(e, t) + `; must be between ${n}-${r}`, St = (e, t) => `Invalid ${e}: ${t}`, Ct = 1e3, wt = 1e6, Tt = 1e9, Et = 6e10, Dt = 36e11;
function Ot(e) {
	return e === void 0 ? Object.create(null) : Ft(e);
}
function kt(e, t = "number") {
	if (typeof e == "bigint") throw TypeError(yt(t));
	if (e = Number(e), !Number.isFinite(e)) throw RangeError(vt(t, e));
	return e;
}
function At(e, t) {
	return Math.trunc(kt(e, t)) || 0;
}
function jt(e, t) {
	return Mt(At(e, t), t);
}
function Mt(e, t = "number") {
	if (e <= 0) throw RangeError(_t(t, e));
	return e;
}
function Nt(e, t, n) {
	return Math.min(Math.max(e, t), n);
}
function Pt(e) {
	return e !== null && (typeof e == "object" || typeof e == "function");
}
function Ft(e) {
	if (!Pt(e)) throw TypeError(bt);
	return e;
}
// ../../../../../localhost/Pushword/packages/newsletter/node_modules/temporal-polyfill/chunks/internal.js
var It = St, Lt = (e) => `Missing ${e}`, Rt = (e, t, n) => St(e, t) + "; must be " + Object.keys(n).join(), zt = "Cannot use valueOf", Bt = "Invalid calling context", Vt = (e) => "Missing year" + (e ? "/era/eraYear" : ""), Ht = "Invalid leap month", Ut = (e) => St("Calendar", e), Wt = (e, t) => `Unknown calendar ${e}; might need ${t}`, Gt = (e) => St("TimeZone", e), Kt = (e) => `Cannot parse: ${e}`, qt = Nt, Jt = Pt;
function O(e) {
	throw RangeError(e);
}
function k(e) {
	throw TypeError(e);
}
function Yt(e, t, n, r, i) {
	return Xt(t, ((e, t) => {
		let n = e[t];
		return n === void 0 && k(Lt(t)), n;
	})(e, t), n, r, i);
}
function Xt(e, t, n, r, i, a) {
	let o = qt(t, n, r);
	return i && t !== o && O(((e, t, n, r, i) => i ? xt(e, i[t], i[n], i[r]) : xt(e, t, n, r))(e, t, n, r, a)), o;
}
function Zt(e, t = Map, n = 1e4) {
	let r = new t();
	return (t, ...i) => {
		if (r.has(t)) return r.get(t);
		r.size >= n && r.clear();
		let a = e(t, ...i);
		return r.set(t, a), a;
	};
}
var Qt = (e) => $t({ name: e }, 1), $t = (e, t) => tn((e) => ({
	value: e,
	configurable: 1,
	writable: !t
}), e), en = (e) => ({ [Symbol.toStringTag]: {
	value: e,
	configurable: 1
} });
function tn(e, t) {
	let n = {};
	for (let r in t) n[r] = e(t[r], r);
	return n;
}
function nn(e, t) {
	let n = {};
	for (let r of e) n[r] = t;
	return n;
}
function rn(e) {
	let t = {};
	for (let n of e) t[n] = (e) => e[n];
	return t;
}
function an(e, t, n = Object.create(null)) {
	for (let r of e) n[r] = t[r];
	return n;
}
function on(e, t, n) {
	for (let r of e) if (t[r] !== n[r]) return 0;
	return 1;
}
function sn(e, t, n) {
	let r = { ...n };
	for (let n = 0; n < t; n++) r[e[n]] = 0;
	return r;
}
function cn(e, ...t) {
	return (...n) => e(...t, ...n);
}
function ln() {}
function un(e) {
	return e[0].toUpperCase() + e.substring(1);
}
function dn(...e) {
	return [].concat(...e).sort();
}
function fn(e) {
	return RegExp(`^${e}$`);
}
function pn(e) {
	return parseInt(e.padEnd(9, "0"));
}
function mn(e) {
	return e === void 0 ? 0 : parseInt(e);
}
function hn(e, t) {
	return String(t).padStart(e, "0");
}
var gn = /*@__PURE__*/ cn(hn, 2);
function _n(e, t) {
	return Math.sign(e - t);
}
function vn(e, t) {
	return e < t ? -1 : +(e > t);
}
function yn(e, t) {
	let n = e / t;
	return e % t < 0n ? n - 1n : n;
}
function bn(e, t) {
	let n = yn(e, t);
	return [n, e - n * t];
}
function xn(e, t) {
	return [Math.floor(e / t), Sn(e, t)];
}
function Sn(e, t) {
	return (e % t + t) % t;
}
function Cn(e, t) {
	return Math.trunc(e / t) || 0;
}
function wn(e, t) {
	return e % t || 0;
}
function Tn(e, t = 1) {
	return t * (.5 + e / 5);
}
function En(e) {
	return Math.abs(e % 1) === .5;
}
var Dn = "iso8601", On = {
	bce: -1,
	ce: 0
};
function kn(e) {
	let t = e.normalize("NFD").toLowerCase().replace(/[^a-z0-9]/g, "");
	return t === "bc" || t === "b" ? "bce" : t === "ad" || t === "a" ? "ce" : t;
}
function An(e) {
	return e === void 0 ? "iso8601" : e === 0 ? "gregory" : e.id;
}
var jn = /^M(\d{2})(L?)$/;
function Mn(e) {
	let t = jn.exec(e);
	return t || O(((e) => `Invalid monthCode: ${e}`)(e)), [parseInt(t[1]), !!t[2]];
}
function Nn(e, t) {
	return "M" + gn(e) + (t ? "L" : "");
}
function Pn(e, t, n) {
	return e + (t || n && e >= n ? 1 : 0);
}
var Fn = {
	nanosecond: 0,
	microsecond: 1,
	millisecond: 2,
	second: 3,
	minute: 4,
	hour: 5,
	day: 6,
	week: 7,
	month: 8,
	year: 9
}, In = /*@__PURE__*/ Object.keys(Fn), Ln = Ct, Rn = wt, zn = Tt, Bn = Et, Vn = Dt, Hn = 864e11, Un = [
	1,
	Ln,
	Rn,
	zn,
	Bn,
	Vn,
	Hn
], Wn = /*@__PURE__*/ BigInt(Ln), Gn = /*@__PURE__*/ BigInt(Rn), Kn = /*@__PURE__*/ BigInt(zn), qn = /*@__PURE__*/ BigInt(Bn), Jn = /*@__PURE__*/ BigInt(Vn), A = /*@__PURE__*/ BigInt(Hn);
function Yn(e, t) {
	let n = Number(e / A), r = Number(e % A);
	return Hn / t * n + (Math.trunc(r / t) + r % t / t);
}
var Xn = /*@__PURE__*/ In.slice(0, 6), Zn = /*@__PURE__*/ rn(Xn), Qn = ["year"], $n = ["day"], er = [
	"day",
	"month",
	"year"
], tr = ["offset"], nr = ["timeZone"], rr = ["era", "eraYear"], ir = [
	"era",
	"eraYear",
	"year"
], ar = ["month", "monthCode"], or = [
	"day",
	"month",
	"monthCode"
], sr = /*@__PURE__*/ dn(Xn), cr = /*@__PURE__*/ dn(rr, Qn), lr = /*@__PURE__*/ dn(ar, Qn), ur = /*@__PURE__*/ dn(rr, lr), dr = /*@__PURE__*/ dn(["monthCode"], Qn), fr = /*@__PURE__*/ dn(rr, dr), pr = /*@__PURE__*/ dn($n, ["monthCode"]), mr = /*@__PURE__*/ dn($n, lr), hr = /*@__PURE__*/ dn($n, rr, lr), gr = /*@__PURE__*/ dn(mr, Xn), _r = /*@__PURE__*/ dn(hr, Xn), vr = /*@__PURE__*/ dn(mr, Xn, tr), yr = /*@__PURE__*/ dn(hr, Xn, tr), br = /*@__PURE__*/ dn(mr, Xn, tr, nr), xr = /*@__PURE__*/ dn(hr, Xn, tr, nr), Sr = /*@__PURE__*/ dn($n, dr), Cr = /*@__PURE__*/ dn($n, rr, dr), wr = /*@__PURE__*/ nn(Xn, 0);
function Tr(e) {
	return Dr(e, 1), e;
}
var Er = {
	hour: 23,
	minute: 59,
	second: 59
};
function Dr(e, t) {
	let n = {};
	for (let r of Xn) n[r] = Xt(r, e[r], 0, Er[r] || 999, t);
	return n;
}
function Or(e) {
	return Ar(e) * zn + jr(e);
}
function kr(e) {
	return 1e3 * Ar(e) + e.millisecond;
}
function Ar(e) {
	return 3600 * e.hour + 60 * e.minute + e.second;
}
function jr(e) {
	return e.millisecond * Rn + e.microsecond * Ln + e.nanosecond;
}
function Mr(e) {
	let [t, n] = xn(e, Hn);
	return [Nr(n), t];
}
function Nr(e) {
	let [t, n] = xn(e, Rn), [r, i] = xn(n, Ln);
	return Pr(t, r, i);
}
function Pr(e, t = 0, n = 0) {
	let [r, i] = xn(e, 36e5), [a, o] = xn(i, 6e4), [s, c] = xn(o, 1e3);
	return {
		hour: r,
		minute: a,
		second: s,
		millisecond: c,
		microsecond: t,
		nanosecond: n
	};
}
function Fr(e) {
	let [t, n] = bn(e, Kn);
	return [Number(t), Number(n)];
}
function Ir(e) {
	return Number(yn(e, Gn));
}
function Lr(e) {
	return zr(e) + BigInt(Or(e));
}
function Rr(e) {
	return Br(e) + kr(e);
}
function zr(e) {
	return BigInt(Vr(e)) * A;
}
function Br(e) {
	return 864e5 * Vr(e);
}
function Vr(e) {
	return Hr(e.year, e.month, e.day);
}
function Hr(e, t = 1, n = 1) {
	let r = t - 1;
	return e += Math.floor(r / 12), t = Sn(r, 12), Date.UTC(e % 400 - 400, t, 0) / 864e5 + 146097 * (Cn(e, 400) + 1) + n;
}
function Ur(e) {
	let [t, n] = bn(e, A);
	return {
		...Wr(Number(t)),
		...Nr(Number(n))
	};
}
function Wr(e) {
	let t = /* @__PURE__ */ new Date(864e5 * Sn(e, 146097));
	return {
		year: t.getUTCFullYear() + 400 * Math.floor(e / 146097),
		month: t.getUTCMonth() + 1,
		day: t.getUTCDate()
	};
}
function Gr(e) {
	return [e, 0];
}
function Kr(e, t) {
	if (!t) return {
		year: 1972,
		month: e
	};
}
function qr(e, t, n) {
	return {
		year: e,
		month: t,
		day: n
	};
}
function Jr(e, t) {
	switch (t) {
		case 2: return Xr(e) ? 29 : 28;
		case 4:
		case 6:
		case 9:
		case 11: return 30;
	}
	return 31;
}
function Yr(e) {
	return Xr(e) ? 366 : 365;
}
function Xr(e) {
	return e % 4 == 0 && (e % 100 != 0 || e % 400 == 0);
}
function Zr(e, t, n) {
	return e += Cn(n, 12), (t += wn(n, 12)) < 1 ? (e--, t += 12) : t > 12 && (e++, t -= 12), {
		year: e,
		month: t
	};
}
function Qr(e, t, n, r) {
	return 12 * (n - e) + r - t;
}
function $r(e) {
	return Sn(Hr(e.year, e.month, e.day) + 4, 7) || 7;
}
function ei(e) {
	return Hr(e.year, e.month, e.day) - Hr(e.year) + 1;
}
function ti(e) {
	let t = e.year, n = Math.floor((ei(e) - $r(e) + 10) / 7), r = ni(t);
	return n < 1 ? n = r = ni(--t) : n > r && (n = 1, r = ni(++t)), {
		weekOfYear: n,
		yearOfWeek: t,
		De: r
	};
}
function ni(e) {
	let t = $r({
		year: e,
		month: 1,
		day: 1
	});
	return t === 4 || t === 3 && Xr(e) ? 53 : 52;
}
function ri({ year: e }) {
	return e < 1 ? {
		era: "bce",
		eraYear: 1 - e
	} : {
		era: "ce",
		eraYear: e
	};
}
function ii(e) {
	return ai(e), Tr(e);
}
function ai(e) {
	return oi(e, 1), e;
}
function oi(e, t) {
	let { year: n } = e, r = Yt(e, "month", 1, 12, t);
	return {
		year: n,
		month: r,
		day: Yt(e, "day", 1, Jr(n, r), t)
	};
}
function j(e, t) {
	return e ? e.de(t) : t;
}
function si(e, t, n) {
	return e ? e.L(t, n) : Gr(n);
}
function ci(e, t) {
	return e === 0 ? ri(t) : e && e.h?.(t) || {};
}
function li(e, t, n, r) {
	return e ? e.fe(t, n, r) : qr(t, n, r);
}
function ui(e, t) {
	return e ? e.j(t) : 12;
}
function di(e, t, n) {
	return e ? e.o(t, n) : Jr(t, n);
}
function fi(e, t) {
	let { year: n, month: r } = j(e, t), [i, a] = si(e, n, r);
	return Nn(i, a);
}
function pi(e, t) {
	let { year: n } = j(e, t);
	return e ? e.q(n) : Xr(n);
}
function mi(e, t) {
	let { year: n } = j(e, t);
	return ui(e, n);
}
function hi(e, t) {
	let { year: n, month: r } = j(e, t);
	return di(e, n, r);
}
function gi(e, t) {
	let { year: n } = j(e, t);
	return e ? e.i(n) : Yr(n);
}
function _i(e, t) {
	if (!e) return ei(t);
	let { year: n } = j(e, t), r = li(e, n, 1, 1);
	return Vr(t) - Vr(r) + 1;
}
function vi(e, t) {
	return e === void 0 ? ti(t).weekOfYear : void 0;
}
function yi(e, t) {
	return e === void 0 ? ti(t).yearOfWeek : void 0;
}
var M = /*@__PURE__*/ In.map((e) => e + "s"), bi = /*@__PURE__*/ rn(M), xi = /*@__PURE__*/ dn(M), Si = /*@__PURE__*/ M.slice(0, 6), Ci = /*@__PURE__*/ M.slice(6), wi = /*@__PURE__*/ Ci.slice(1), Ti = /*@__PURE__*/ nn(M, 0), Ei = /*@__PURE__*/ nn(Si, 0), Di = /*@__PURE__*/ cn(sn, M);
function Oi(e, t) {
	return t ?? O(Lt(e)), t;
}
var ki = /*@__PURE__*/ cn(Ai, "string");
function Ai(e, t, n = e) {
	return typeof t !== e && k(It(n, t)), t;
}
function ji(e, t = "number") {
	return Number.isInteger(e) || O(((e, t) => `Non-integer ${e}: ${t}`)(t, e)), e || 0;
}
function Mi(e) {
	return typeof e == "symbol" && k("Cannot convert Symbol to string"), String(e);
}
function Ni(e) {
	if (!Pt(e)) return e;
	let t = e[Symbol.toPrimitive];
	if (t != null) {
		typeof t != "function" && k();
		let n = Reflect.apply(t, e, ["string"]);
		return Pt(n) && k(), n;
	}
	return Date.prototype[Symbol.toPrimitive].call(e, "string");
}
function Pi(e) {
	return typeof e == "boolean" ? BigInt(+!!e) : typeof e == "string" ? BigInt(e) : (typeof e != "bigint" && k(`Invalid bigint: ${e}`), e);
}
function Fi(e, t) {
	return ji(kt(e, t), t);
}
var Ii = "smallestUnit", Li = {
	constrain: 0,
	reject: 1
}, Ri = {
	compatible: 0,
	reject: 1,
	earlier: 2,
	later: 3
}, zi = {
	reject: 0,
	use: 1,
	prefer: 2,
	ignore: 3
}, Bi = {
	auto: 0,
	never: 1,
	critical: 2,
	always: 3
}, Vi = {
	auto: 0,
	never: 1,
	critical: 2
}, Hi = {
	auto: 0,
	never: 1
}, Ui = {
	floor: 0,
	halfFloor: 1,
	ceil: 2,
	halfCeil: 3,
	trunc: 4,
	halfTrunc: 5,
	expand: 6,
	halfExpand: 7,
	halfEven: 8
}, Wi = [
	Math.floor,
	(e) => En(e) ? Math.floor(e) : Math.round(e),
	Math.ceil,
	(e) => En(e) ? Math.ceil(e) : Math.round(e),
	Math.trunc,
	(e) => En(e) ? Math.trunc(e) || 0 : Math.round(e),
	(e) => e < 0 ? Math.floor(e) : Math.ceil(e),
	(e) => Math.sign(e) * Math.round(Math.abs(e)) || 0,
	(e) => En(e) ? (e = Math.trunc(e) || 0) + e % 2 : Math.round(e)
], Gi = {
	previous: -1,
	next: 1
};
function Ki(e) {
	let t = e.roundingIncrement;
	if (t === void 0) return 1;
	let n = At(t, "roundingIncrement");
	return (n < 1 || n > 10 ** 9) && O(It("roundingIncrement", n)), n;
}
function qi(e) {
	let t = e.fractionalSecondDigits;
	if (t !== void 0) {
		if (typeof t != "number") {
			if (Mi(t) === "auto") return;
			O(It("fractionalSecondDigits", t));
		}
		t = Xt("fractionalSecondDigits", Math.floor(t), 0, 9, 1);
	}
	return t;
}
function Ji(e, t, n = 0, r) {
	let i = t[e];
	if (i === void 0) return r ? n : void 0;
	if (i = Mi(i), i === "auto") return r ? n : null;
	let a = Fn[i];
	return a === void 0 && (a = M.indexOf(i)), a < 0 && O(Rt(e, i, Fn)), a;
}
function Yi(e, t, n, r = 0) {
	let i = n[e];
	if (i === void 0) return r;
	let a = Mi(i), o = t[a];
	return o === void 0 && O(Rt(e, a, t)), o;
}
var Xi = /*@__PURE__*/ cn(Ji, Ii), Zi = /*@__PURE__*/ cn(Ji, "largestUnit"), Qi = /*@__PURE__*/ cn(Ji, "unit"), $i = /*@__PURE__*/ cn(Yi, "overflow", Li), ea = /*@__PURE__*/ cn(Yi, "disambiguation", Ri), ta = /*@__PURE__*/ cn(Yi, "offset", zi), na = /*@__PURE__*/ cn(Yi, "calendarName", Bi), ra = /*@__PURE__*/ cn(Yi, "timeZoneName", Vi), ia = /*@__PURE__*/ cn(Yi, "offset", Hi), aa = /*@__PURE__*/ cn(Yi, "roundingMode", Ui), oa = /*@__PURE__*/ cn(Yi, "direction", Gi);
function sa(e, t) {
	return typeof e == "string" ? ((e, t) => {
		let n = Object.create(null);
		return n[e] = t, n;
	})(t, e) : Ft(e);
}
function ca(e, t, n, r) {
	let i = r ? Hn : Un[t + 1];
	if (i) {
		let n = e * Un[t];
		(i % n || !r && n === i) && O(It("roundingIncrement", e));
	} else !n && e > 1 && O(It("roundingIncrement", e));
	return e;
}
function la(e, t, n, r) {
	return t != null && Xt(e, t, n, r, 1, In), t;
}
function ua(e, t) {
	t > e && O("smallestUnit > largestUnit");
}
function da(e, t, n, r = 9, i = 0, a = 4) {
	t = Ot(t);
	let o = Zi(t, i), s = Ki(t), c = aa(t, a), l = Xi(t, i, 1);
	return o = la("largestUnit", o, i, r), l = la(Ii, l, i, r), o == null ? o = Math.max(n, l) : ua(o, l), s = ca(s, l, 1), e && (c = ((e) => e < 4 ? (e + 2) % 4 : e)(c)), [
		o,
		l,
		s,
		c
	];
}
function fa(e, t = 6, n) {
	let r = Ki(e = sa(e, Ii)), i = aa(e, 7), a = Xi(e);
	return a = Oi(Ii, a), a = la(Ii, a, 0, t), r = ca(r, a, void 0, n), [
		a,
		r,
		i
	];
}
function N(e, t) {
	return an(er, e, an(Xn, t));
}
var pa = /*@__PURE__*/ BigInt(1e8) * A, ma = /*@__PURE__*/ BigInt(-1e8) * A, ha = ma - A;
function ga(e) {
	let t = 12 * e.year + e.month;
	return (t < -3261848 || t > 3309129) && O("Out-of-bounds date"), e;
}
function _a(e, t = 1) {
	let n = zr(e);
	return (n < (t ? ha : ma) || n > pa) && O("Out-of-bounds date"), e;
}
function va(e) {
	return ya(Lr(e)), e;
}
function ya(e) {
	return (e <= ha || e >= pa + A) && O("Out-of-bounds date"), e;
}
function ba(e) {
	return (e < ma || e > pa) && O("Out-of-bounds date"), e;
}
function xa(e, t) {
	return ba(zr(e) + BigInt(Or(e) - t));
}
function Sa(e, t, n) {
	return Ca(e / t, n) * t || 0;
}
function Ca(e, t) {
	return Wi[t](e);
}
function wa(e, t, n, r) {
	let i = Ea(e, t, r, n);
	return Oa(i, Number(n - i.N) / Number(i.te - i.N));
}
function Ta(e, t, n, r) {
	return Sa(Oa(e, ka(t, e.N, e.te)), n, r);
}
function Ea(e, t, n, r) {
	let i = Da(e, t, n);
	return ((e, t, n, r) => r > 0 ? t <= e && e <= n : n <= e && e <= t)(r, i.N, i.te, Math.sign(t)) || (i = Da(e + t, t, n)), i;
}
function Da(e, t, n) {
	let r = e + t;
	return {
		ve: e,
		pe: r,
		N: n(e),
		te: n(r)
	};
}
function Oa(e, t) {
	return e.ve + t * (e.pe - e.ve);
}
function ka(e, t, n) {
	let r = n - t, i = e - t;
	if (!i) return 0;
	let a = i < 0n ? -i : i, o = r < 0n ? -r : r, s = vn(i, 0n) === vn(r, 0n) ? 1 : -1;
	return vn(a, o) <= 0 ? a === o ? s : Tn(vn(2n * a, o), s) : Number(i) / Number(r);
}
function Aa(e) {
	return { epochNanoseconds: e };
}
function ja(e, t, n) {
	return {
		calendar: n,
		timeZone: t,
		epochNanoseconds: e
	};
}
function Ma(e, t) {
	return an(Xn, e, P(e, t));
}
function P(e, t) {
	return an(er, e, { calendar: t });
}
function Na(e) {
	return an(Xn, e);
}
function Pa(e) {
	return an(M, e, { sign: es(e) });
}
function Fa(e, t, n, r) {
	return ja(t === 6 ? Ba(e, r) : Va(e, t, n, r), e.timeZone, e.calendar);
}
function Ia(e) {
	let [t, n] = Ya(e);
	return Yn(n - t, Vn);
}
function La(e) {
	let { timeZone: t, calendar: n } = e;
	return ja(co(t, N(ro(e), wr)), t, n);
}
function Ra(e, t, n, r, i, a, o, s) {
	if (r === 0 && i === 1) return e;
	let c = es(e) || 1, [l, u, d] = (Ko(r, s) ? s && r < 6 && n >= 6 ? qa : Ja : Ka)(c, e, t, n, r, i, a, o);
	return d && r !== 7 && (l = ((e, t, n, r, i, a) => {
		for (let o = r + 1; o <= n; o++) {
			if (o === 7 && n !== 7) continue;
			let r = Di(o, e);
			r[M[o]] += i;
			let s = vn(t, Ho(a, r));
			if (s && s !== i) break;
			e = r;
		}
		return e;
	})(l, u, n, Math.max(6, r), c, o)), l;
}
function za(e, t, n) {
	let r = Math.max(Math.min(us(e), 6), 3);
	return ss(Qa(is(e), BigInt(t), n), r);
}
function Ba(e, t) {
	let [n, r] = Ya(e);
	return Xa(e.epochNanoseconds, n, r, t);
}
function Va(e, t, n, r) {
	if (t === 0 && n === 1) return e.epochNanoseconds;
	let i = ro(e);
	return ao(e.timeZone, Ha(i, t, n, r), i.offsetNanoseconds);
}
function Ha(e, t, n, r) {
	return Wa(e, to(t, n), r, Un[t + 1]);
}
function Ua(e, t, n, r) {
	return Ga(e, to(t, n), r, Un[t + 1]);
}
function Wa(e, t, n, r) {
	let [i, a] = Ga(e, t, n, r), o = N(So(e, a), i);
	return va(o), o;
}
function Ga(e, t, n, r = Hn) {
	let i = Or(e), a = i % r;
	return Mr(i - a + Sa(a, t, n));
}
function Ka(e, t, n, r, i, a, o, s) {
	let c = M[i], l = Di(i, t);
	i === 7 && (t = {
		...t,
		weeks: t.weeks + Math.trunc(t.days / 7)
	});
	let u = Cn(t[c], a) * a, d = Ea(u, a * e, (e) => (l[c] = e, Ho(s, l)), n), f = Ta(d, n, a, o);
	return l[c] = f, [
		l,
		f === d.pe ? d.te : d.N,
		f !== u
	];
}
function qa(e, t, n, r, i, a, o, s) {
	let c = Number(as(t)), l = to(i, a), u = Sa(c, l, o), d = {
		...t,
		...Ei
	}, f = s.X(d), p = s.X({
		...d,
		days: d.days + e
	}), m = Number(p - f);
	Math.sign(m) !== e && O("Out-of-bounds TimeZone gap");
	let h = u - m, g = 0, _;
	h && Math.sign(h) !== e ? _ = f + BigInt(u) : (g += e, u = Sa(h, l, o), _ = p + BigInt(u));
	let v = cs(u);
	return [
		{
			...t,
			...v,
			days: t.days + g
		},
		_,
		!!g
	];
}
function Ja(e, t, n, r, i, a, o) {
	let s = is(t), c = Qa(s, no(i, a), o), l = c - s, u = Math.sign(Number(c / A) - Number(s / A)) === e, d = ss(c, Math.min(r, 6));
	return [
		{
			...t,
			...d
		},
		n + l,
		u
	];
}
function Ya(e) {
	let { timeZone: t } = e, n = N(ro(e), wr), r = N(So(n, 1), wr);
	return [co(t, n), co(t, r)];
}
function Xa(e, t, n, r) {
	return Ca(ka(e < n ? e : n - 1n, t, n), r) ? n : t;
}
function Za(e) {
	return Sa(e, Bn, 7);
}
function Qa(e, t, n) {
	return eo(e, t, n, e / t % 2n);
}
function $a(e, t, n) {
	let [r, i] = bn(e, A), a = r * A;
	return a + eo(i, t, n, a / t + i / t & 1n);
}
function eo(e, t, n, r) {
	let i = e / t, a = e % t, o = 0;
	a && (o = Tn(vn(2n * (a < 0n ? -a : a), t), Math.sign(Number(a))));
	let s = Ca(Number(r) + o, n);
	return (i - r + BigInt(s)) * t;
}
function to(e, t) {
	return Un[e] * t;
}
function no(e, t) {
	return BigInt(Un[e]) * BigInt(t);
}
var ro = /*@__PURE__*/ Zt(io, WeakMap);
function io(e) {
	let { epochNanoseconds: t, timeZone: n } = e, r = n.B(t);
	return {
		...Ur(t + BigInt(r)),
		offsetNanoseconds: r
	};
}
function ao(e, t, n) {
	return oo(e, t, n, 2, 0);
}
function oo(e, t, n, r = 0, i = 0, a, o) {
	if (o || n !== void 0 && r === 1) return xa(t, n || 0);
	let s = e.O(t);
	if (n !== void 0 && r !== 3) {
		_a(t, 0);
		let e = ((e, t, n, r) => {
			let i = Lr(t);
			r && (n = Za(n));
			for (let t of e) {
				let e = Number(i - t);
				if (r && (e = Za(e)), e === n) return t;
			}
		})(s, t, n, a);
		if (e !== void 0) return e;
		r === 0 && O("Invalid TimeZone offset");
	}
	return so(e, t, i, s);
}
function so(e, t, n = 0, r = e.O(t)) {
	if (r.length === 1) return r[0];
	if (n === 1 && O("Ambiguous offset"), r.length) return r[+(n === 3)];
	let i = Lr(t), a = ((e, t) => {
		let n = e.B(t - A);
		return ((e) => (e > Hn && O("Out-of-bounds TimeZone gap"), e))(e.B(t + A) - n);
	})(e, i), o = Ur(i + BigInt(a * (n === 2 ? -1 : 1)));
	return (r = e.O(o))[n === 2 ? 0 : r.length - 1];
}
function co(e, t) {
	let n = e.O(t);
	if (n.length) return n[0];
	let r = Lr(t) - A;
	return e.C(r, 1);
}
function lo(e, t, n, r = 0) {
	return es(n) && Xt("unit", us(n), 8, 9, 1, M), go(e, ho(e, _a(go(e, t)), n, r));
}
function uo(e, t, n = 0) {
	let { calendar: r, epochNanoseconds: i, timeZone: a } = e, o = as(t), s = i;
	if (ls(t)) {
		let i = ro(e);
		s = so(a, N(ho(r, i, {
			...t,
			...Ei
		}, n), i)) + o;
	} else s += o;
	return {
		...e,
		epochNanoseconds: ba(s)
	};
}
function fo(e, t, n, r = 0) {
	let [i, a] = po(t, n);
	return va(N(ho(e, t, {
		...n,
		...Ei,
		days: n.days + a
	}, r), i));
}
function po(e, t) {
	return bo(e, as(t));
}
function mo(e, t) {
	return xo(e, (Xt("unit", us(n = t), 0, 5, 1, M), as(n)));
	var n;
}
function ho(e, t, n, r = 0) {
	let { years: i, months: a, weeks: o, days: s } = n, c;
	if (s += Number(as(n) / A), i || a) c = _o(e, t, i, a, r);
	else {
		if (!o && !s) return t;
		c = t;
	}
	return (o || s) && (c = So(c, 7 * o + s)), _a(c);
}
function go(e, t) {
	return So(t, 1 - j(e, t).day);
}
function _o(e, t, n, r, i) {
	let { year: a, month: o, day: s } = j(e, t);
	if (n) {
		let [t, r] = si(e, a, o);
		a += n, o = yo(e, t, r, e ? e.p(a) : void 0, i), o = Xt("month", o, 1, ui(e, a), i);
	}
	if (r) {
		let t = vo(e, a, o, r);
		({year: a, month: o} = t);
	}
	return s = Xt("day", s, 1, di(e, a, o), i), li(e, a, o, s);
}
function vo(e, t, n, r) {
	return e ? e.ae(t, n, r) : Zr(t, n, r);
}
function yo(e, t, n, r, i) {
	if (n) {
		let n = e ? e.l : void 0;
		if (r !== void 0 && (n < 0 || r === t + 1)) return r;
		if (i === 1 && O(Ht), n < 0) return -n;
	}
	return Pn(t, 0, r);
}
function bo(e, t) {
	let n = Number(t / A), r = Number(t % A), [i, a] = Mr(Or(e) + r);
	return [i, n + a];
}
function xo(e, t) {
	return ba(e + t);
}
function So(e, t) {
	return t ? Wr(Vr(e) + t) : e;
}
function Co(e, t) {
	return An(e) !== An(t) && O("Mismatching Calendars"), e;
}
function wo(e, t) {
	return e.m !== t.m && O("Mismatching TimeZones"), e;
}
function To(e) {
	return e.timeZone.id;
}
function Eo(e, t, n, r, i, a, o) {
	return r < 6 ? Mo(t.epochNanoseconds, n.epochNanoseconds, r, i, a, o) : ((e, t, n, r, i, a, o) => {
		let s = wo(t.timeZone, n.timeZone), c = n.epochNanoseconds;
		if (c === t.epochNanoseconds) return Ti;
		let l;
		return l = No(s, e, t, n, r), l = Ra(l, c, r, i, a, o, Uo(e, s, t), 1), l;
	})(e, t, n, r, i, a, o);
}
function Do(e, t, n, r, i, a, o) {
	return r <= 6 ? Mo(Lr(t), Lr(n), r, i, a, o) : ((e, t, n, r, i, a, o) => {
		let s = Lr(n), c = vn(s, Lr(t));
		if (!c) return Ti;
		let l;
		return l = Fo(e, t, n, c, r), l = Ra(l, s, r, i, a, o, ((e, t) => ({
			ne: Lr(t),
			X: (n) => Lr(N(ho(e, t, n), t))
		}))(e, t)), l;
	})(e, t, n, r, i, a, o);
}
function Oo(e, t, n, r, i, a, o) {
	let s = go(e, t), c = go(e, n);
	return Ro(s, c) ? jo(e, _a(s), _a(c), r, i, a, o, 8) : Ti;
}
function ko(e, t, n, r, i, a, o, s = 6) {
	return r === 6 ? Mo(zr(t), zr(n), r, i, a, o) : jo(e, t, n, r, i, a, o, s);
}
function Ao(e, t, n, r, i, a) {
	let o = Sa(Or(t) - Or(e), to(r, i), a);
	return {
		...Ti,
		...cs(o, n)
	};
}
function jo(e, t, n, r, i, a, o, s = 6) {
	let c = zr(n);
	if (!Ro(t, n)) return Ti;
	let l;
	return l = Lo(e, t, n, r), i === s && a === 1 || (l = Ra(l, c, r, i, a, o, Wo(e, t))), l;
}
function Mo(e, t, n, r, i, a) {
	return {
		...Ti,
		...ss(Qa(t - e, no(r, i), a), n)
	};
}
function No(e, t, n, r, i) {
	return i < 6 ? {
		...Ti,
		...ss(r.epochNanoseconds - n.epochNanoseconds, i)
	} : ((e, n, r) => {
		let a = vn(r.epochNanoseconds, n.epochNanoseconds);
		if (!a) return Ti;
		if (!Ro(ro(n), ro(r))) return {
			...Ti,
			...ss(r.epochNanoseconds - n.epochNanoseconds, 5)
		};
		let [o, s, c] = Io(e, n, r, a);
		return {
			...(l = o, u = s, Lo(t, l, u, i)),
			...cs(c)
		};
		var l, u;
	})(e, n, r);
}
function Po(e, t, n, r) {
	let i = Lr(t), a = Lr(n), o = vn(a, i);
	return o ? r <= 6 ? {
		...Ti,
		...ss(a - i, r)
	} : Fo(e, t, n, o, r) : Ti;
}
function Fo(e, t, n, r, i) {
	let a = n, o = Or(n) - Or(t);
	return Math.sign(o) === -r && (a = So(n, -r), o += Hn * r), {
		...Lo(e, t, a, i),
		...cs(o)
	};
}
function Io(e, t, n, r) {
	let i = ro(t), a = ro(n), o = n.epochNanoseconds, s = 0, c = Or(a) - Or(i);
	Math.sign(c) === -r && s++;
	let l = r > 0 ? 2 : 1;
	for (;; s++) {
		let t = So(a, s * -r), n = so(e, N(t, i));
		if (s === l || vn(o, n) !== -r) return [
			i,
			t,
			Number(o - n)
		];
	}
}
function Lo(e, t, n, r) {
	if (r <= 7) return ((e, t, n) => {
		let r = zo(t, n);
		return e ? {
			...Ti,
			weeks: Cn(r, 7),
			days: wn(r, 7)
		} : {
			...Ti,
			days: r
		};
	})(r === 7, t, n);
	let i = j(e, t), a = j(e, n);
	if (r === 8) {
		let { year: r, month: o, day: s } = i, { year: c, month: l, day: u } = a, d = Math.sign(_n(c, r) || _n(l, o) || zo(t, n)), f = 0, p = 0;
		if (d) {
			f = e ? e.ee(r, o, c, l) : Qr(r, o, c, l);
			let i = _o(e, t, 0, f, 0);
			d * _n(s, u) > 0 && (f -= d, i = _o(e, t, 0, f, 0)), p = zo(i, n);
		}
		return {
			...Ti,
			months: f,
			days: p
		};
	}
	let { year: o, month: s, day: c } = i, { year: l, month: u, day: d } = a, f = l - o, p = u - s, m = d - c;
	if (f || p) {
		let t = Math.sign(f || p), n = di(e, l, u), r = 0;
		if (Math.sign(d - c) === -t) {
			let i = n, a = vo(e, l, u, -t);
			({year: l, month: u} = a), f = l - o, p = u - s, n = di(e, l, u), r = t < 0 ? -i : n;
		}
		if (m = d - Math.min(c, n) + r, f) {
			let [n, r] = si(e, o, s), [i, a] = si(e, l, u), c = e ? e.l : void 0;
			if (p = c !== void 0 && r && !a && (c < 0 ? t > 0 && i === -c : t < 0 && i === n) ? 0 : i - n || Number(a) - Number(r), Math.sign(p) === -t) {
				let i = t < 0 && -ui(e, l);
				l -= t, f = l - o, p = u - yo(e, n, r, e ? e.p(l) : void 0, 0) + (i || ui(e, l));
			} else if (e) {
				let t = yo(e, n, r, e.p(l), 0);
				p = e.ee(l, t, l, u);
			}
		}
	}
	return {
		...Ti,
		years: f,
		months: p,
		days: m
	};
}
function Ro(e, t) {
	return _n(e.year, t.year) || _n(e.month, t.month) || _n(e.day, t.day);
}
function zo(e, t) {
	return Vr(t) - Vr(e);
}
function Bo(e, t, n) {
	return Go(e) ? ((e, t, n) => {
		let { calendar: r, timeZone: i } = e, a = uo(e, t);
		return [
			No(i, r, e, a, n),
			a.epochNanoseconds,
			Uo(r, i, e)
		];
	})(e, t, n) : ((e, t, n) => {
		let { calendar: r } = e, i = va(N(e, wr)), a = fo(r, i, t);
		return [
			Po(r, i, a, n),
			Lr(a),
			Wo(r, e)
		];
	})(e, t, n);
}
function Vo(e, t) {
	return Go(e) ? uo(e, t).epochNanoseconds : Lr(fo(e.calendar, N(e, wr), t));
}
function Ho(e, t) {
	return ls(t) ? e.X(t) : e.ne;
}
function Uo(e, t, n) {
	let r = ro(n);
	return {
		ne: n.epochNanoseconds,
		X: (n) => so(t, N(ho(e, r, n), r))
	};
}
function Wo(e, t) {
	return {
		ne: zr(t),
		X: (n) => zr(ho(e, t, n))
	};
}
function Go(e) {
	return "timeZone" in e;
}
function Ko(e, t) {
	return e <= 6 - !!t;
}
function qo(e, t, n) {
	let r = {};
	for (let i = t; i >= 0; i--) {
		let t = Un[i];
		r[n[i]] = Cn(e, t), e = wn(e, t);
	}
	return r;
}
var Jo = 2 ** 53;
function Yo(e, t, n) {
	let r = Math.max(us(t), us(n));
	return Xt("unit", r, 0, 6, 1, M), ((e, t, n, r) => Pa(ts(((e, t, n, r) => {
		let i = is(e) + is(t) * BigInt(r ? -1 : 1);
		return Number.isFinite(Number(i / A)) || O("Out-of-bounds date"), {
			...Ti,
			...ss(i, n)
		};
	})(t, n, r, e))))(e, t, n, r);
}
function Xo(e, t, n) {
	let r = us(t), [i, a, o, s, c] = ((e, t, n) => {
		e = sa(e, Ii);
		let r = Zi(e), i = n(e.relativeTo), a = Ki(e), o = aa(e, 7), s = Xi(e);
		return r === void 0 && s === void 0 && O("Required smallestUnit or largestUnit"), s ??= 0, r ??= Math.max(s, t), ua(r, s), a = ca(a, s, 1), a > 1 && s > 5 && r !== s && O("For calendar units with roundingIncrement > 1, use largestUnit = smallestUnit"), [
			r,
			s,
			a,
			o,
			i
		];
	})(n, r, e);
	if (!c && Math.max(r, i) <= 6) return Pa(ts(((e, t, n, r, i) => {
		let a = Qa(is(e), no(n, r), i);
		return {
			...Ti,
			...ss(a, t)
		};
	})(t, i, a, o, s)));
	c || O("Missing relativeTo");
	let l = Go(c);
	if (!t.sign && (!l || i < 6)) return t;
	let [u, d, f] = Bo(c, t, i);
	return Pa(Ra(u, d, i, a, o, s, f, l));
}
function Zo(e) {
	return e.sign === -1 ? Qo(e) : e;
}
function Qo(e) {
	return Pa($o(e));
}
function $o(e) {
	let t = {};
	for (let n of M) t[n] = -1 * e[n] || 0;
	return t;
}
function es(e, t = M) {
	let n = 0;
	for (let r of t) {
		let t = Math.sign(e[r]);
		t && (n && n !== t && O("Cannot mix duration signs"), n = t);
	}
	return n;
}
function ts(e) {
	for (let t of wi) ns(t, e[t]);
	let t = is(e);
	return rs(Number(t / Kn)), e;
}
function ns(e, t) {
	return Xt(e, t, -4294967295, 4294967295, 1);
}
function rs(e) {
	Number.isSafeInteger(e) || O("Out-of-bounds duration");
}
function is(e) {
	return BigInt(e.days) * A + as(e);
}
function as(e) {
	return BigInt(e.hours) * Jn + BigInt(e.minutes) * qn + os(e);
}
function os(e) {
	return BigInt(e.seconds) * Kn + BigInt(e.milliseconds) * Gn + BigInt(e.microseconds) * Wn + BigInt(e.nanoseconds);
}
function ss(e, t = 6) {
	let n = Number(e / A), r = Number(e % A), i = Un[t], a = t <= 3 ? Number(e / BigInt(i)) : Hn / i * n + Cn(r, i);
	Number.isFinite(a) || O("Out-of-bounds date"), t <= 3 && Math.abs(a) / (zn / Un[t]) >= Jo && O("Out-of-bounds date");
	let o = qo(r, t, M);
	return o[M[t]] = a, o;
}
function cs(e, t = 5) {
	return qo(e, t, M);
}
function ls(e) {
	return !!es(e, Ci);
}
function us(e) {
	let t = 9;
	for (; t > 0 && !e[M[t]]; t--);
	return t;
}
function ds(e, t) {
	return vn(e.epochNanoseconds, t.epochNanoseconds);
}
function fs(e, t, n, r) {
	let i = e(Ot(r).relativeTo), a = Math.max(us(t), us(n));
	return on(M, t, n) ? 0 : Ko(a, i && Go(i)) ? vn(is(t), is(n)) : (i || O("Missing relativeTo"), vn(Vo(i, t), Vo(i, n)));
}
function ps(e, t) {
	return ms(e, t) || hs(e, t);
}
function ms(e, t) {
	return _n(Vr(e), Vr(t));
}
function hs(e, t) {
	return _n(Or(e), Or(t));
}
function gs(e, t) {
	return !ds(e, t);
}
function _s(e, t) {
	return !ds(e, t) && e.timeZone.m === t.timeZone.m && e.calendar === t.calendar;
}
function vs(e, t) {
	return !ps(e, t) && e.calendar === t.calendar;
}
function ys(e, t) {
	return !ms(e, t) && e.calendar === t.calendar;
}
function bs(e, t) {
	return !ms(e, t) && e.calendar === t.calendar;
}
function xs(e, t) {
	return !ms(e, t) && e.calendar === t.calendar;
}
function Ss(e, t) {
	return !hs(e, t);
}
function Cs(e) {
	return e === 0 ? On : e ? e.k : void 0;
}
function ws(e, t, n = t) {
	return Cs(e) ? n : t;
}
function Ts(e, t) {
	let n = t || void 0, r = Cs(t), { era: i, eraYear: a, year: o } = e;
	if (o !== void 0 && (o = At(o, "year")), a !== void 0 && (a = At(a, "eraYear")), i !== void 0 || a !== void 0) {
		i !== void 0 && a !== void 0 || k("Mismatching era/eraYear"), r || O("Forbidden era/eraYear");
		let e = kn(i), t = r[e];
		t === void 0 && O(((e) => `Invalid era: ${e}`)(i));
		let s = n?._ ? n._(a, e, t) : Os(a, t);
		o !== void 0 && o !== s && O("Mismatching year/eraYear"), o = s;
	} else o === void 0 && k(Vt(r));
	return o;
}
function Es(e, t, n, r, i) {
	let { month: a, monthCode: o } = e;
	if (o !== void 0) {
		let e = ((e, t, n, r, i = Mn(t)) => {
			let a = e ? e.p(n) : void 0, [o, s] = i, c = Pn(o, s, a);
			if (s) {
				let t = e ? e.l : void 0;
				t === void 0 && O(Ht), t > 0 ? (c > t && O(Ht), a !== c && (r === 1 && O(Ht), c = Pn(o, 0, a))) : (c !== -t && O(Ht), a === void 0 && r === 1 && O(Ht));
			}
			return c;
		})(t, o, n, i, r);
		a !== void 0 && a !== e && O("Mismatching month/monthCode"), a = e, i = 1;
	} else a === void 0 && k("Missing month/monthCode");
	return Xt("month", a, 1, ui(t, n), i);
}
function Ds(e, t, n, r, i) {
	return Yt(e, "day", 1, di(t, n, r), i);
}
function Os(e, t) {
	return (t + e) * (Math.sign(t) || 1) || 0;
}
function ks(e, t) {
	return Dr(an(Xn, {
		...wr,
		...e
	}), t);
}
var As = /^([+-])([01]\d|2[0-3])(?:(:?)([0-5]\d)(?:\3([0-5]\d)(?:[.,](\d{1,9}))?)?)?$/;
function js(e) {
	let t = Ms(e);
	return t === void 0 && O(Kt(e)), t;
}
function Ms(e, t) {
	let n = As.exec(e);
	if (n) return t && n[5] && O(Kt(e)), ((e) => e && e !== "+" ? -1 : 1)(n[1]) * (parseInt(n[2]) * Vn + mn(n[4]) * Bn + mn(n[5]) * zn + pn(n[6] || ""));
}
var Ns = {
	era: Mi,
	month: jt,
	monthCode(e, t) {
		return ki(Ni(e), t);
	},
	day: jt
}, Ps = /*@__PURE__*/ nn(Xn, At), Fs = /*@__PURE__*/ nn(M, Fi), Is = /*@__PURE__*/ Object.assign({}, Ns, Ps), Ls = {
	offset(e) {
		return js(ki(Ni(e)));
	},
	...Is
};
function F(e, t, n, r, i = !r) {
	let a = {}, o = 0;
	for (let i of t) {
		let t = e[i];
		if (t !== void 0) {
			o = 1;
			let e = n[i];
			e && (t = e(t, i)), a[i] = t;
		} else r && r.includes(i) && k(Lt(i));
	}
	return i && !o && k("No valid fields: " + t.join()), a;
}
function I(e) {
	return e === void 0 ? 0 : $i(Ft(e));
}
function Rs(e, t = 0) {
	e = Ot(e);
	let n = ea(e), r = ta(e, t);
	return [
		$i(e),
		r,
		n
	];
}
function zs(e) {
	return ea(Ot(e));
}
function Bs(e, t, n) {
	let r = Cs(t);
	e.year !== void 0 || e.era !== void 0 && e.eraYear !== void 0 || k(Vt(r)), e.monthCode === void 0 && e.month === void 0 && k("Missing month/monthCode"), n || e.day !== void 0 || k(Lt("day"));
	let i = Js(e);
	return [Ts(e, t), i];
}
function Vs(e, t) {
	let n = t === void 0, r = Cs(t);
	e.day === void 0 && k(Lt("day")), n || e.month === void 0 || e.year !== void 0 || e.era !== void 0 && e.eraYear !== void 0 || k(Vt(r));
	let i = Js(e);
	return [e.eraYear !== void 0 || e.year !== void 0 ? Ts(e, t) : void 0, i];
}
function Hs(e, t = wr, n) {
	let r = N(e, t);
	return va(r), Ma(r, n);
}
function Us(e, t, n, r, i) {
	let a = Es(e, t, n, r, i);
	return P(_a(li(t, n, a, Ds(e, t, n, a, i))), t);
}
function Ws(e, t, n, r, i) {
	return Ks(t, n, Es(e, t, n, r, i));
}
function Gs(e, t, n, r, i) {
	let a = t === void 0, o = n, s, c, l;
	if (o === void 0 && a && (o = 1972), o !== void 0) {
		a || _a(li(t, o, 1, 1));
		let n = Es(e, t, o, r, i);
		s = Ds(e, t, o, n, i), [c, l] = si(t, o, n);
	} else {
		e.monthCode === void 0 && k("Missing month/monthCode"), [c, l] = r;
		let n = t ? t.re : 1972;
		if (n !== void 0) s = Ds(e, t, n, Es(e, t, n, r, i), i);
		else {
			let n = i === 0 && t ? t.ge?.(c, l, e.day) : void 0;
			s = n === void 0 ? e.day : n;
		}
	}
	return qs(t, c, l, s, e.day, i);
}
function Ks(e, t, n) {
	return P(ga(li(e, t, n, 1)), e);
}
function qs(e, t, n, r, i, a) {
	let o = n;
	o && ((e && e.U?.[t]) ?? 1 / 0) < i && (a === 1 && O(Ht), o = 0, r = qt(i, 1, (e && e.R) ?? 1 / 0));
	let s = e ? e.u(t, !!o, r) : Kr(t, !!o);
	for (; !s && a === 0 && r > 1;) r--, s = e ? e.u(t, !!o, r) : Kr(t, !!o);
	s || O("Cannot guess year");
	let { year: c, month: l } = s;
	return P(_a(li(e, c, l, r)), e);
}
function Js(e) {
	if (e.monthCode !== void 0) return Mn(e.monthCode);
}
var Ys = Intl.DateTimeFormat;
function Xs(e, t) {
	t < -864e13 && O("Out-of-bounds date");
	let n = e.formatToParts(t), r = {};
	for (let e of n) r[e.type] = e.value;
	return r;
}
var Zs = {
	El_Aaiun: 16,
	Tucuman: 12,
	Tirane: 11,
	Riga: 10,
	Simferopol: 9,
	Vienna: 9,
	Tunis: 8,
	Boa_Vista: 6,
	Fortaleza: 6,
	Maceio: 6,
	Noronha: 6,
	Recife: 6,
	Gaza: 6,
	Hebron: 6,
	DeNoronha: 6
}, Qs = -4e9;
function $s(e, t = 4) {
	let n = qi(e);
	return [aa(e, 4), ...nc(la(Ii, Xi(e), 0, t), n)];
}
function ec(e) {
	return na(Ot(e));
}
function tc(e, t) {
	return $s(Ot(e), t);
}
function nc(e, t) {
	return e == null ? [t === void 0 ? 1 : 10 ** (9 - t), t] : [Un[e], e < 4 ? 9 - 3 * e : -1];
}
function rc(e, t, n) {
	let [r, i, a, o] = ((e) => {
		let t = qi(e = Ot(e)), n = aa(e, 4), r = Xi(e);
		return [
			e.timeZone,
			n,
			...nc(la(Ii, r, 0, 4), t)
		];
	})(n), s = r !== void 0;
	return ((e, t, n, r, i, a) => {
		n = $a(n, BigInt(i), r);
		let o = t.B(n);
		return vc(Ur(n + BigInt(o)), a) + (e ? Cc(Za(o)) : "Z");
	})(s, Ic(s ? e(r) : "UTC"), t.epochNanoseconds, i, a, o);
}
function ic(e) {
	return vc(Ur(e.epochNanoseconds), void 0) + "Z";
}
function ac(e, t) {
	let n = ((e) => {
		e = Ot(e);
		let t = na(e), n = qi(e), r = ia(e), i = aa(e, 4), a = Xi(e);
		return [
			t,
			ra(e),
			r,
			i,
			...nc(la(Ii, a, 0, 4), n)
		];
	})(t);
	return ((e, t, n, r, i, a, o, s, c, l) => {
		r = $a(r, BigInt(c), s);
		let u = n.B(r);
		return vc(Ur(r + BigInt(u)), l) + Cc(Za(u), o) + wc(t, a) + Tc(e, i);
	})(e.calendar, e.timeZone.id, e.timeZone, e.epochNanoseconds, ...n);
}
function oc(e) {
	let t = e.calendar, n = e.timeZone, r = n.B(e.epochNanoseconds);
	return vc(Ur(e.epochNanoseconds + BigInt(r)), void 0) + Cc(Za(r)) + wc(n.id, 0) + (t === void 0 ? "" : Ec(An(t), 0));
}
function sc(e, t) {
	let n = ((e) => (e = Ot(e), [na(e), ...$s(e)]))(t);
	return ((e, t, n, r, i, a) => vc(Wa(t, i, r), a) + Tc(e, n))(e.calendar, e, ...n);
}
function cc(e) {
	let t = e.calendar;
	return vc(e, void 0) + (t === void 0 ? "" : Ec(An(t), 0));
}
function lc(e, t) {
	return n = e.calendar, r = e, i = ec(t), yc(r) + Tc(n, i);
	var n, r, i;
}
function uc(e, t) {
	return fc(e.calendar, bc, e, ec(t));
}
function dc(e, t) {
	return fc(e.calendar, xc, e, ec(t));
}
function fc(e, t, n, r) {
	return r === 1 ? e === void 0 ? t(n) : yc(n) : r > 1 || r === 0 && e !== void 0 ? yc(n) + Ec(An(e), r === 2) : t(n);
}
function pc(e, t) {
	return ((e, t, n, r) => Sc(Ga(e, n, t)[0], r))(e, ...tc(t));
}
function mc(e, t) {
	let [n, r, i] = tc(t, 3);
	return r > 1 && ((e = {
		...e,
		...za(e, r, n)
	}).sign = es(e), ts(e)), hc(e, i);
}
function hc(e, t) {
	let { sign: n } = e, r = n === -1 ? $o(e) : e, { hours: i, minutes: a } = r, o = os(r), s = Number(o / Kn), c = Number(o % Kn);
	rs(s);
	let l = Oc(c, t), u = t >= 0 || !n || l;
	return (n < 0 ? "-" : "") + "P" + gc({
		Y: _c(r.years),
		M: _c(r.months),
		W: _c(r.weeks),
		D: _c(r.days)
	}) + (i || a || s || u ? "T" + gc({
		H: _c(i),
		M: _c(a),
		S: _c(s, u) + l
	}) : "");
}
function gc(e) {
	let t = [];
	for (let n in e) {
		let r = e[n];
		r && t.push(r, n);
	}
	return t.join("");
}
function _c(e, t) {
	if (!e && !t) return "";
	let n = Object.create(null);
	return n.useGrouping = 0, e.toLocaleString("fullwide", n);
}
function vc(e, t) {
	return yc(e) + "T" + Sc(e, t);
}
function yc(e) {
	return bc(e) + "-" + gn(e.day);
}
function bc(e) {
	let { year: t } = e;
	return (t < 0 || t > 9999 ? kc(t) + hn(6, Math.abs(t)) : hn(4, t)) + "-" + gn(e.month);
}
function xc(e) {
	return gn(e.month) + "-" + gn(e.day);
}
function Sc(e, t) {
	let n = [gn(e.hour), gn(e.minute)];
	return t !== -1 && n.push(gn(e.second) + ((e, t, n, r) => Oc(e * Rn + t * Ln + n, r))(e.millisecond, e.microsecond, e.nanosecond, t)), n.join(":");
}
function Cc(e, t = 0) {
	if (t === 1) return "";
	let [n, r] = xn(Math.abs(e), Vn), [i, a] = xn(r, Bn), [o, s] = xn(a, zn);
	return kc(e) + gn(n) + ":" + gn(i) + (o || s ? ":" + gn(o) + Oc(s) : "");
}
function wc(e, t) {
	return t === 1 ? "" : "[" + (t === 2 ? "!" : "") + e + "]";
}
function Tc(e, t) {
	return t > 1 || t === 0 && e !== void 0 ? Ec(An(e), t === 2) : "";
}
function Ec(e, t) {
	return "[" + (t ? "!" : "") + "u-ca=" + e + "]";
}
var Dc = /0+$/;
function Oc(e, t) {
	let n = hn(9, e);
	return n = t === void 0 ? n.replace(Dc, "") : n.slice(0, t), n ? "." + n : "";
}
function kc(e) {
	return e < 0 ? "-" : "+";
}
var Ac = /^(AC|AE|AG|AR|AS|BE|BS|CA|CN|CS|CT|EA|EC|IE|IS|JS|MI|NE|NS|PL|PN|PR|PS|SS|VS)T$/, jc = /[^\w\/:+-]+/;
function Mc(e) {
	return Nc(ki(e));
}
function Nc(e) {
	return Pc(e).id;
}
var Pc = /*@__PURE__*/ Zt((e) => {
	let t = e.toUpperCase(), n = ((e) => {
		let t = Ms(e, 1);
		if (t !== void 0) return {
			id: Cc(t),
			Z: t,
			m: t
		};
	})(t);
	return n ? {
		kind: "fixed",
		...n
	} : Fc(t === "UTC" ? "UTC" : ((e) => (jc.test(e) && O(Gt(e)), Ac.test(e) && O("Forbidden ICU TimeZone"), e.toLowerCase().split("/").map((e, t) => (e.length <= 3 || /\d/.test(e)) && !/etc|yap/.test(e) ? e.toUpperCase() : e.replace(/baja|dumont|[a-z]+/g, (e, n) => e.length <= 2 && !t || e === "in" || e === "chat" ? e.toUpperCase() : e.length > 2 || !n ? un(e).replace(/island|noronha|murdo|rivadavia|urville/, un) : e)).join("/")))(e));
}), Fc = /*@__PURE__*/ Zt((e) => {
	if (e === "UTC") return {
		kind: "utc",
		id: e,
		m: e
	};
	let t = (n = e.toUpperCase(), new Ys("en-u-hc-h23", {
		calendar: "iso8601",
		timeZone: n,
		era: "short",
		year: "numeric",
		month: "numeric",
		day: "numeric",
		hour: "numeric",
		minute: "numeric",
		second: "numeric"
	}));
	var n;
	return {
		kind: "named",
		id: e,
		format: t,
		m: t.resolvedOptions().timeZone
	};
});
function Ic(e) {
	let t = Pc(e);
	return Lc(t.id, t);
}
var Lc = /*@__PURE__*/ Zt((e, t) => t.kind === "named" ? new zc(e, t.m, t.format) : new Rc(e, t.m, t.kind === "fixed" ? t.Z : 0)), Rc = class {
	constructor(e, t, n) {
		this.id = e, this.m = t, this.Z = n;
	}
	B() {
		return this.Z;
	}
	O(e) {
		return [xa(e, this.Z)];
	}
	C() {}
}, zc = class {
	constructor(e, t, n) {
		this.id = e, this.m = t, this.me = ((e, t) => {
			let n = Zt(e), r = Zt(Bc), i = 86400 * t;
			function a(e) {
				let [t, a] = Vc(e, i), o = Hc(t), c = Hc(a), l = n(o), u = n(c);
				return l === u ? l : s(r(o, c), l, u, e);
			}
			function o(e, t, a, o = e) {
				let [c, l] = Vc(e, i), u = i * t, d = () => t < 0 ? l > a : c < a;
				for (; d();) {
					let e = Hc(c), i = Hc(l), a = n(e), d = n(i);
					if (a !== d) {
						let n = r(e, i);
						s(n, a, d);
						let c = n[0];
						if ((_n(c, o) || 1) === t) return c;
					}
					c += u, l += u;
				}
			}
			function s(t, n, r, i) {
				let a, o;
				for (; (i === void 0 || (a = i < t[0] ? n : i >= t[1] ? r : void 0) === void 0) && (o = t[1] - t[0]);) {
					let n = t[0] + Math.floor(o / 2);
					e(n) === r ? t[1] = n : t[0] = n + 1;
				}
				return a;
			}
			return {
				Ae(e) {
					let t = a(e - 86400), n = a(e + 86400), r = e - t, i = e - n;
					if (t === n) return [r];
					let o = a(r);
					return o === a(i) ? [e - o] : t > n ? [r, i] : [];
				},
				ze: a,
				C(e, t) {
					if (!(t > 0 && e >= 864e10)) return t < 0 ? e <= Qs ? void 0 : e > 4e9 ? o(e, -1, e - 94867200) ?? o(4e9, -1, Qs) : o(e, -1, Qs) : o(Math.max(e, Qs) - 1, 1, Math.max(e, Math.floor(Date.now() / 1e3)) + 94867200, e);
				}
			};
		})(((e) => (t) => {
			let n = Xs(e, 1e3 * t);
			return 86400 * Hr(((e) => {
				let t = e.relatedYear;
				if (t !== void 0) return parseInt(t);
				let n = parseInt(e.year);
				return e.era !== void 0 && kn(e.era) === "bce" ? 1 - n : n;
			})(n), parseInt(n.month), parseInt(n.day)) + 3600 * parseInt(n.hour) + 60 * parseInt(n.minute) + parseInt(n.second) - t;
		})(n), ((e) => Zs[e.split("/").pop()] || 19)(e));
	}
	B(e) {
		return this.me.ze(((e) => Fr(e)[0])(e)) * zn;
	}
	O(e) {
		let t = 86400 * Vr(e) + Ar(e), n = jr(e);
		return this.me.Ae(t).map((e) => ba(BigInt(e) * Kn + BigInt(n)));
	}
	C(e, t) {
		let [n, r] = Fr(e), i = this.me.C(n + (t > 0 || r ? 1 : 0), t);
		if (i !== void 0) return BigInt(i) * Kn;
	}
};
function Bc(e, t) {
	return [e, t];
}
function Vc(e, t) {
	let n = Math.floor(e / t) * t;
	return [n, n + t];
}
function Hc(e) {
	return qt(e, -1e10, 864e10);
}
function Uc(e, t, n) {
	let r = F(n, ws(t, br, xr), Ls, [], 0), i = r.timeZone !== void 0, [a, o] = Bs(r, t), s = Us(r, t, a, o, 0);
	if (i) {
		let n = ks(r), i = Ic(e(r.timeZone));
		return {
			epochNanoseconds: oo(i, N(s, n), r.offset),
			timeZone: i,
			calendar: t
		};
	}
	return s;
}
function Wc(e, t, n, r) {
	let i = F(n, ws(t, br, xr), Ls, nr, 0), a = e(i.timeZone), [o, s] = Bs(i, t), [c, l, u] = Rs(r), d = Us(i, t, o, s, c), f = ks(i, c), p = Ic(a);
	return ja(oo(p, N(d, f), i.offset, l, u), p, t);
}
function Gc(e, t, n) {
	let r = F(t, ws(e, gr, _r), Is, [], 0), [i, a] = Bs(r, e), o = I(n);
	return Hs(Us(r, e, i, a, o), ks(r, o), e);
}
function Kc(e, t, n, r = []) {
	let i = F(t, ws(e, mr, hr), Ns, r), [a, o] = Bs(i, e);
	return Us(i, e, a, o, I(n));
}
function qc(e, t, n, r) {
	let i = F(t, ws(e, lr, ur), Ns, r), [a, o] = Bs(i, e, 1);
	return Ws(i, e, a, o, I(n));
}
function Jc(e, t, n, r) {
	let i = F(n, ws(e, mr, hr), Ns, $n, 0);
	t && i.month !== void 0 && i.monthCode === void 0 && i.year === void 0 && (i.year = 1972);
	let [a, o] = Vs(i, e);
	return Gs(i, e, a, o, I(r));
}
function Yc(e, t) {
	return ks(F(e, sr, Ps, [], 1), I(t));
}
function Xc(e) {
	let t = F(e, xi, Fs);
	return Pa(ts({
		...Ti,
		...t
	}));
}
function Zc(e) {
	O(Kt(e));
}
function Qc(e) {
	let t = bl(e = ki(Ni(e))), n;
	return t || Zc(e), t.I ? n = 0 : t.offset ? n = js(t.offset) : Zc(e), Aa(xa(t, n));
}
function $c(e, t) {
	let n = bl(ki(e));
	return n && n.timeZoneId ? ul(n, t, void 0) : dl(ll(e), t);
}
function el(e, t, n) {
	let r = bl(ki(e));
	return r && r.timeZoneId || Zc(e), ul(r, t, n);
}
function tl(e, t) {
	let n = ll(e);
	return va(n), {
		...N(n, n),
		calendar: t(n.calendarId)
	};
}
function nl(e, t) {
	return dl(ll(e), t);
}
function rl(e, t) {
	let n = xl(ki(e)) || ll(e), r = t(n.calendarId);
	return P(ga(go(r, ga(n))), r);
}
function il(e, t) {
	let n = Sl(ki(e)) || ll(e);
	if (n.calendarId === "iso8601") return P({
		...n,
		year: 1972
	}, t("iso8601"));
	let { calendar: r } = dl(n, t), { year: i, month: a, day: o } = j(r, n), [s, c] = si(r, i, a), l = r ? r.u(s, c, o) : Kr(s, c);
	return l || O("Cannot guess year"), P(_a(li(r, l.year, l.month, o)), r);
}
function al(e) {
	let t = bl(e = ki(e)) || wl(e);
	return (!t || !t.qe || t.I || xl(e) || Sl(e)) && Zc(e), Na(t);
}
function ol(e) {
	let t = vl.exec(ki(e));
	t && !yl.test(e) || Zc(e);
	let n = qo(3600 * pn(t[7] || "") + 60 * pn(t[9] || "") + pn(t[11] || ""), 4, M), r = {
		...n,
		years: mn(t[2]),
		months: mn(t[3]),
		weeks: mn(t[4]),
		days: mn(t[5]),
		hours: mn(t[6]),
		minutes: mn(t[8]) + n.minutes,
		seconds: mn(t[10]) + n.seconds
	};
	return Pa(ts(t[1] === "-" ? $o(r) : r));
}
function sl(e) {
	let t = bl(e) || xl(e) || Sl(e) || wl(e);
	return t ? t.calendarId : e;
}
function cl(e) {
	let t = bl(e);
	return t && (t.timeZoneId || t.I && "UTC" || t.offset) || e;
}
function ll(e) {
	let t = bl(ki(e));
	return t && !t.I || Zc(e), t;
}
function ul(e, t, n) {
	let r = Ic(Nc(e.timeZoneId)), i;
	if (e.qe) {
		let t = e.offset ? js(e.offset) : void 0, [, o, s] = Rs(n);
		i = oo(r, e, t, o, s, !(r.Z || e.offset === void 0 || (a = e.offset, a.length > 6)), e.I);
	} else Rs(n), i = co(r, e);
	var a;
	return ba(i), ja(i, r, t(e.calendarId));
}
function dl(e, t) {
	return P(_a(e), t(e.calendarId));
}
function fl(e, t = "") {
	return `([01]\\d|2[0-3])(?:(:?)([0-5]\\d)(?:\\${e}([0-5]\\d${t})(?:[.,](\\d{1,9}))?)?)?`;
}
var pl = "((?!-0{6})[+-]\\d{6}|\\d{4})(-?)(0[1-9]|1[0-2])\\2(0[1-9]|[12]\\d|3[01])(?:[Tt ]" + fl(6, "|60") + "(?:([Zz])|(([+-])" + fl(14) + "))?)?", ml = /*@__PURE__*/ fn("((?!-0{6})[+-]\\d{6}|\\d{4})-?(0[1-9]|1[0-2])(?:\\[!?([+-](?:[01]\\d|2[0-3])(?::?[0-5]\\d)?|[a-zA-Z._][\\w.+-]*(?:/[a-zA-Z._][\\w.+-]*)*)\\])?((?:\\[!?[a-z_][a-z\\d_-]*=[a-zA-Z\\d]+(?:-[a-zA-Z\\d]+)*\\])*)"), hl = /*@__PURE__*/ fn("(?:--)?(0[1-9]|1[0-2])-?(0[1-9]|[12]\\d|3[01])(?:\\[!?([+-](?:[01]\\d|2[0-3])(?::?[0-5]\\d)?|[a-zA-Z._][\\w.+-]*(?:/[a-zA-Z._][\\w.+-]*)*)\\])?((?:\\[!?[a-z_][a-z\\d_-]*=[a-zA-Z\\d]+(?:-[a-zA-Z\\d]+)*\\])*)"), gl = /*@__PURE__*/ fn(pl + "(?:\\[!?([+-](?:[01]\\d|2[0-3])(?::?[0-5]\\d)?|[a-zA-Z._][\\w.+-]*(?:/[a-zA-Z._][\\w.+-]*)*)\\])?((?:\\[!?[a-z_][a-z\\d_-]*=[a-zA-Z\\d]+(?:-[a-zA-Z\\d]+)*\\])*)"), _l = /\[(!?)([^=]+)=([^\]]+)/g, vl = /^([+-])?P(\d+Y)?(\d+M)?(\d+W)?(\d+D)?(?:T(?:(\d+)(?:[.,](\d{1,9}))?H)?(?:(\d+)(?:[.,](\d{1,9}))?M)?(?:(\d+)(?:[.,](\d{1,9}))?S)?)?$/i, yl = /[PT]$|[.,]\d+[HMS]./i;
function bl(e) {
	let t = gl.exec(e);
	if (t) return ai(((e) => ({
		year: parseInt(e[1]),
		month: parseInt(e[3]),
		day: parseInt(e[4]),
		...Mr(pn(e[9] || ""))[0],
		hour: mn(e[5]),
		minute: mn(e[7]),
		second: Math.min(mn(e[8]), 59),
		...Tl(e[18], e[19]),
		qe: !!e[5],
		I: !!e[10],
		offset: e[11]
	}))(t));
}
function xl(e) {
	let t = ml.exec(e);
	return t ? Cl(((e) => ({
		year: parseInt(e[1]),
		month: parseInt(e[2]),
		day: 1,
		...Tl(e[3], e[4])
	}))(t)) : void 0;
}
function Sl(e) {
	let t = hl.exec(e);
	if (t) {
		let e = ((e) => ({
			year: 1972,
			month: parseInt(e[1]),
			day: parseInt(e[2]),
			...Tl(e[3], e[4])
		}))(t);
		if (on(er, n = e, oi(n))) return Cl(e);
	}
	var n;
}
function Cl(e) {
	return e.calendarId !== "iso8601" && Zc(e.calendarId), e;
}
function wl(e) {
	let t = bl(e.replace(/^t?/i, "0000-01-01T"));
	return t && !t.I ? t : void 0;
}
function Tl(e, t) {
	let n, r = [];
	return t.replace(_l, (e, t, i, a) => (i === "u-ca" ? (r.push(a.toLowerCase()), n ||= t) : t && Zc(e), "")), r.length > 1 && n && Zc(t), {
		timeZoneId: e,
		calendarId: r[0] || "iso8601"
	};
}
function El(e, t, n) {
	let r = Object.assign(Object.create(null), t);
	return Dl(r, n, ar), Cs(e) && (Dl(r, n, ir), e && e.je && Dl(r, n, or, rr)), r;
}
function Dl(e, t, n, r) {
	let i = 0, a = [];
	for (let e of n) t[e] === void 0 ? a.push(e) : i = 1;
	if (Object.assign(e, t), i) for (let t of r || a) delete e[t];
}
function Ol(e, t, n) {
	let r = ws(e, vr, yr), i = ro(t), { year: a, month: o, day: s } = j(e, i), c = {
		year: a,
		monthCode: Ll(e, a, o),
		day: s,
		hour: i.hour,
		minute: i.minute,
		second: i.second,
		millisecond: i.millisecond,
		microsecond: i.microsecond,
		nanosecond: i.nanosecond,
		offset: i.offsetNanoseconds
	}, l = F(n, r, Ls), u = El(e, c, l);
	return [{
		...c,
		...l
	}, u];
}
function kl(e, t, n) {
	let r = ws(e, gr, _r), { year: i, month: a, day: o } = j(e, t), s = {
		year: i,
		monthCode: Ll(e, i, a),
		day: o,
		hour: t.hour,
		minute: t.minute,
		second: t.second,
		millisecond: t.millisecond,
		microsecond: t.microsecond,
		nanosecond: t.nanosecond
	}, c = F(n, r, Is), l = El(e, s, c);
	return [{
		...s,
		...c
	}, l];
}
function Al(e, t, n) {
	let r = ws(e, mr, hr), { year: i, month: a, day: o } = j(e, t);
	return El(e, {
		year: i,
		monthCode: Ll(e, i, a),
		day: o
	}, F(n, r, Ns));
}
function jl(e, t, n) {
	let r = ws(e, lr, ur), { year: i, month: a } = j(e, t);
	return El(e, {
		year: i,
		monthCode: Ll(e, i, a)
	}, F(n, r, Ns));
}
function Ml(e, t, n) {
	let r = ws(e, mr, hr), { year: i, month: a, day: o } = j(e, t);
	return El(e, {
		monthCode: Ll(e, i, a),
		day: o
	}, F(n, r, Ns));
}
function Nl(e, t, n, r, i, a, o, s) {
	let { calendar: c, timeZone: l } = e;
	return ja(oo(l, N(Us(n, c, r, i, a), Dr(t, a)), t.offset, o, s), l, c);
}
function Pl(e, t, n, r, i, a) {
	return Hs(Us(t, n, r, i, a), Dr(e, a), n);
}
function Fl(e, t) {
	return {
		...an(sr, e),
		...F(t, sr, Ps)
	};
}
function Il(e, t) {
	return Pa((n = e, r = t, ts({
		...n,
		...F(r, xi, Fs)
	})));
	var n, r;
}
function Ll(e, t, n) {
	let [r, i] = si(e, t, n);
	return Nn(r, i);
}
function Rl(e, t, n) {
	let [r, i] = ((e, t) => {
		let n = t((e = sa(e, "unit")).relativeTo), r = Qi(e);
		return r = Oi("unit", r), [r, n];
	})(n, e), a = us(t), o = Math.max(r, a), s = i && Go(i);
	if (!i && Ko(o, s)) return zl(t, r);
	if (i || O("Missing relativeTo"), !t.sign && (!s || r < 6)) return 0;
	let [c, l, u] = Bo(i, t, r);
	return Ko(r, s) ? zl(c, r) : ((e, t, n, r) => {
		let i = M[n], a = Di(n, e);
		return wa(e[i], es(e) || 1, t, (e) => (a[i] = e, Ho(r, a)));
	})(c, l, r, u);
}
function zl(e, t) {
	return Yn(is(e), Un[t]);
}
function Bl(e, t, n) {
	return ja(e.epochNanoseconds, t, n);
}
function Vl(e) {
	return Aa(e.epochNanoseconds);
}
function Hl(e) {
	return Ma(ro(e), e.calendar);
}
function Ul(e) {
	return P(ro(e), e.calendar);
}
function Wl(e) {
	return Na(ro(e));
}
function Gl(e, t, n) {
	return ja(ba(so(t, e, n)), t, e.calendar);
}
function Kl(e, t, n) {
	let r;
	return r = n ? so(t, N(e, n)) : co(t, N(e, wr)), ja(r, t, e.calendar);
}
function ql(e, t, n) {
	return Zl(e, an(ws(e, dr, fr), t), F(Ft(n), $n, Ns, []));
}
function Jl(e, t, n) {
	let r = ws(e, Qn, cr);
	return Zl(e, an(pr, t), F(Ft(n), r, Ns, []));
}
function Yl(e, t) {
	let n = F(t, pr, Ns), [r, i] = Vs(n, e);
	return Gs(n, e, r, i, 0);
}
function Xl(e, t) {
	let n = F(t, ws(e, dr, fr), Ns), [r, i] = Bs(n, e, 1);
	return Ws(n, e, r, i, 0);
}
function Zl(e, t, n) {
	let r = ws(e, Sr, Cr), i = El(e, t, n);
	i = F(i, r, Ns, []);
	let [a, o] = Bs(i, e);
	return Us(i, e, a, o, 0);
}
function Ql(e) {
	return Aa(ba(BigInt(Fi(e)) * Gn));
}
function $l(e) {
	return Aa(ba(Pi(e)));
}
function eu(e) {
	return e.timeZone = "UTC", ["full", "long"].includes(e.timeStyle) && (e.timeStyle = "medium"), e;
}
function tu(e, t) {
	return e.timeZone !== void 0 && k("Cannot specify TimeZone"), e.timeZone = t, e;
}
function nu(e, t, n) {
	let r = e.resolvedOptions().calendar;
	!n && t.calendar === void 0 || An(t.calendar) === r || O("Mismatching Calendars");
}
function ru(e, t, n, r, i) {
	let a = new Set(e), o = new Set(t), s = new Set(n);
	return (e, t) => {
		let n, c, l = {}, u = {}, d = {}, f = 0, p = 0;
		for (let t of Object.keys(e)) {
			let r = e[t];
			r === void 0 || s.has(t) || (a.has(t) ? t === "dateStyle" ? n = r : t === "timeStyle" ? c = r : l[t] = r : t === "era" ? u[t] = r : o.has(t) ? t === "dateStyle" || t === "timeStyle" ? p = 1 : f = 1 : d[t] = r);
		}
		let m = n !== void 0, h = c !== void 0, g = m || h, _ = Object.keys(l).length > 0, v = f || p, y = _ || m || h, b = Object.keys(u).length > 0;
		(!t && v || t && v && !y || g && (_ || b || f)) && k("Invalid formatting options");
		let x = {};
		return g || y || Object.assign(x, r), Object.assign(x, l, u, d), m && (i ? Object.assign(x, i[n]) : x.dateStyle = n), h && (x.timeStyle = c), x;
	};
}
var iu = {
	year: "numeric",
	month: "numeric",
	day: "numeric"
}, au = {
	hour: "numeric",
	minute: "numeric",
	second: "numeric"
}, ou = /*@__PURE__*/ Object.assign({}, iu, au), su = [
	"weekday",
	"year",
	"month",
	"day",
	"dateStyle"
], cu = [
	"dayPeriod",
	"hour",
	"minute",
	"second",
	"fractionalSecondDigits",
	"timeStyle"
], lu = /*@__PURE__*/ su.concat(cu), uu = /*@__PURE__*/ ["weekday", "day"].concat(cu), du = /*@__PURE__*/ ["weekday", "year"].concat(cu), fu = /*@__PURE__*/ ru(lu, [], [], ou), pu = /*@__PURE__*/ ru(lu, [], [], {
	...ou,
	timeZoneName: "short"
}), mu = /*@__PURE__*/ ru(lu, [], ["timeZoneName"], ou), hu = /*@__PURE__*/ ru(su, cu, ["timeZoneName"], iu), gu = /*@__PURE__*/ ru(cu, su, ["timeZoneName", "era"], au), _u = /*@__PURE__*/ ru([
	"year",
	"month",
	"dateStyle"
], uu, ["timeZoneName"], {
	year: "numeric",
	month: "numeric"
}, {
	full: {
		year: "numeric",
		month: "long"
	},
	long: {
		year: "numeric",
		month: "long"
	},
	medium: {
		year: "numeric",
		month: "short"
	},
	short: {
		year: "2-digit",
		month: "numeric"
	}
}), vu = /*@__PURE__*/ ru([
	"month",
	"day",
	"dateStyle"
], du, ["timeZoneName", "era"], {
	month: "numeric",
	day: "numeric"
}, {
	full: {
		month: "long",
		day: "numeric"
	},
	long: {
		month: "long",
		day: "numeric"
	},
	medium: {
		month: "short",
		day: "numeric"
	},
	short: {
		month: "numeric",
		day: "numeric"
	}
});
function yu(e, t) {
	let { timeZone: n } = e, r = N(ro(e), t || wr);
	return ja(t ? so(n, r) : co(n, r), n, e.calendar);
}
function bu(e) {
	let t = oa(sa(e, "direction"), 0);
	return t || O(It("direction", t)), t;
}
function xu(e) {
	let t = Su(), n = e.B(t);
	return Ur(t + BigInt(n));
}
function Su() {
	return BigInt(Date.now()) * Gn;
}
function Cu() {
	return new Ys().resolvedOptions().timeZone || "UTC";
}
// ../../../../../localhost/Pushword/packages/newsletter/node_modules/temporal-polyfill/chunks/apiHelpers.js
var wu = "PlainYearMonth", Tu = "PlainMonthDay", Eu = "PlainDate", Du = "PlainDateTime", Ou = "PlainTime", ku = "ZonedDateTime", Au = "Instant", ju = "Duration";
function Mu(e, t, n, ...r) {
	return Object.defineProperties(t, Qt(e)), Object.defineProperties(t.prototype, en("Temporal." + e)), Object.defineProperties(t.prototype, tn((e) => ({
		get() {
			return e(n(this));
		},
		configurable: 1
	}), Object.assign({}, ...r))), t;
}
var Nu = ln.name !== "noop" || globalThis.process ? ln : (e) => {
	Object.defineProperty(e, "_str_", { value: e.toJSON() });
};
function Pu() {
	k(Bt);
}
function Fu() {
	k(zt);
}
var Iu = {
	era(e) {
		return ci(e.calendar, e).era;
	},
	eraYear(e) {
		return ci(e.calendar, e).eraYear;
	},
	year(e) {
		return j(e.calendar, e).year;
	},
	month(e) {
		return j(e.calendar, e).month;
	},
	monthCode(e) {
		return fi(e.calendar, e);
	}
}, Lu = {
	era(e) {
		return ci(e.calendar, e).era;
	},
	eraYear(e) {
		return ci(e.calendar, e).eraYear;
	},
	year(e) {
		return j(e.calendar, e).year;
	},
	month(e) {
		return j(e.calendar, e).month;
	},
	monthCode(e) {
		return fi(e.calendar, e);
	},
	day(e) {
		return j(e.calendar, e).day;
	}
}, Ru = {
	monthCode(e) {
		return fi(e.calendar, e);
	},
	day(e) {
		return j(e.calendar, e).day;
	}
}, zu = {
	daysInMonth(e) {
		return hi(e.calendar, e);
	},
	daysInYear(e) {
		return gi(e.calendar, e);
	},
	monthsInYear(e) {
		return mi(e.calendar, e);
	},
	inLeapYear(e) {
		return pi(e.calendar, e);
	}
}, Bu = {
	dayOfWeek(e) {
		return $r(e);
	},
	dayOfYear(e) {
		return _i(e.calendar, e);
	},
	weekOfYear(e) {
		return vi(e.calendar, e);
	},
	yearOfWeek(e) {
		return yi(e.calendar, e);
	},
	daysInWeek() {
		return 7;
	},
	daysInMonth(e) {
		return hi(e.calendar, e);
	},
	daysInYear(e) {
		return gi(e.calendar, e);
	},
	monthsInYear(e) {
		return mi(e.calendar, e);
	},
	inLeapYear(e) {
		return pi(e.calendar, e);
	}
};
function Vu(e, t, n, r) {
	let [i, a, o, s] = da(e, r, 3, 5);
	return qu(e, Mo(t.epochNanoseconds, n.epochNanoseconds, i, a, o, s));
}
function Hu(e, t, n, r) {
	let i = Co(t.calendar, n.calendar), [a, o, s, c] = da(e, r, 5);
	return qu(e, Eo(i, t, n, a, o, s, c));
}
function Uu(e, t, n, r) {
	let i = Co(t.calendar, n.calendar), [a, o, s, c] = da(e, r, 6);
	return qu(e, Do(i, t, n, a, o, s, c));
}
function Wu(e, t, n, r) {
	let i = Co(t.calendar, n.calendar), [a, o, s, c] = da(e, r, 6, 9, 6);
	return qu(e, ko(i, t, n, a, o, s, c));
}
function Gu(e, t, n, r) {
	let i = Co(t.calendar, n.calendar), [a, o, s, c] = da(e, r, 9, 9, 8);
	return qu(e, Oo(i, t, n, a, o, s, c));
}
function Ku(e, t, n, r) {
	let [i, a, o, s] = da(e, r, 5, 5);
	return qu(e, Ao(t, n, i, a, o, s));
}
function qu(e, t) {
	return Pa(e ? $o(t) : t);
}
function Ju(e, t, n) {
	let { calendar: r } = e, i = Al(r, e, t), [a, o] = Bs(i, r);
	return Us(i, r, a, o, I(n));
}
function Yu(e, t, n) {
	let { calendar: r } = e, [i, a] = kl(r, e, t), [o, s] = Bs(a, r);
	return Pl(i, a, r, o, s, I(n));
}
function Xu(e, t, n) {
	let { calendar: r } = e, i = jl(r, e, t), [a, o] = Bs(i, r, 1);
	return Ws(i, r, a, o, I(n));
}
function Zu(e, t, n) {
	let { calendar: r } = e, i = Ml(r, e, t), [a, o] = Vs(i, r);
	return Gs(i, r, a, o, I(n));
}
function Qu(e, t, n) {
	return ks(Fl(e, t), I(n));
}
function $u(e, t, n) {
	let { calendar: r } = e, [i, a] = Ol(r, e, t), [o, s] = Bs(a, r), [c, l, u] = Rs(n, 2);
	return Nl(e, i, a, o, s, c, l, u);
}
function ed(e) {
	return rn(Object.keys(e));
}
var td = /*@__PURE__*/ ed(Zn), nd = /*@__PURE__*/ ed(Lu), rd = `${Du}Record`, id = `${ku}Record`, ad = `${Au}Record`, od = /*@__PURE__*/ new WeakMap(), sd = /*@__PURE__*/ new WeakMap(), cd = /*@__PURE__*/ new WeakMap(), ld = /*@__PURE__*/ new WeakMap();
function ud(e) {
	return dd(e) || Pu();
}
function dd(e) {
	return od.get(e);
}
function fd(e) {
	return pd(e) || Pu();
}
function pd(e) {
	return sd.get(e);
}
function md(e, t) {
	sd.set(e, t);
}
function hd(e) {
	return gd(e) || Pu();
}
function gd(e) {
	return cd.get(e);
}
function _d(e, t) {
	cd.set(e, t);
}
function vd(e) {
	return yd(e) || Pu();
}
function yd(e) {
	return ld.get(e);
}
function bd(e, t) {
	ld.set(e, t);
}
function xd(e) {
	return ud(e).id;
}
function Sd(e) {
	let t = ud(e).xe;
	return t || O(Wt(xd(e), "getExotic or getAny")), t;
}
// ../../../../../localhost/Pushword/packages/newsletter/node_modules/temporal-polyfill/chunks/funcApi-native.js
function Cd(e) {
	return typeof e == "string" && e.includes("[") && O(Gt(e)), e;
}
function wd(e) {
	if (e !== void 0) return Td(e);
}
function Td(e) {
	return Sd(e), xd(e);
}
var Ed = vd, Dd = /*@__PURE__*/ Mu(rd, class {
	get calendarId() {
		return Ed(this).calendarId;
	}
	toJSON() {
		return Ed(this).toJSON();
	}
	valueOf() {
		return Ed(this).valueOf();
	}
}, Ed, nd, td);
function Od(e) {
	let t = Object.create(Dd.prototype);
	return bd(t, e), Nu(t), t;
}
function kd(e, t, n, r, i, a, o, s, c, l) {
	return Od(new gt.PlainDateTime(e, t, n, r, i, a, o, s, c, wd(l)));
}
function Ad(e, t, n) {
	return Nd(Ed(e).toZonedDateTime(Cd(t), n));
}
var jd = hd, Md = /*@__PURE__*/ Mu(id, class {
	get calendarId() {
		return jd(this).calendarId;
	}
	get timeZoneId() {
		return jd(this).timeZoneId;
	}
	get epochMilliseconds() {
		return jd(this).epochMilliseconds;
	}
	get epochNanoseconds() {
		return jd(this).epochNanoseconds;
	}
	toJSON() {
		return jd(this).toJSON();
	}
	valueOf() {
		return jd(this).valueOf();
	}
}, jd, nd, td);
function Nd(e) {
	let t = Object.create(Md.prototype);
	return _d(t, e), Nu(t), t;
}
function Pd(e) {
	return jd(e).offsetNanoseconds;
}
var Fd = fd, Id = /*@__PURE__*/ Mu(ad, class {
	get epochMilliseconds() {
		return Fd(this).epochMilliseconds;
	}
	get epochNanoseconds() {
		return Fd(this).epochNanoseconds;
	}
	toJSON() {
		return Fd(this).toJSON();
	}
	valueOf() {
		return Fd(this).valueOf();
	}
});
function Ld(e) {
	let t = Object.create(Id.prototype);
	return md(t, e), Nu(t), t;
}
function Rd(e) {
	return Ld(gt.Instant.fromEpochMilliseconds(e));
}
function zd(e, t) {
	return Nd(Fd(e).toZonedDateTimeISO(Cd(t)));
}
// ../../../../../localhost/Pushword/packages/newsletter/node_modules/temporal-polyfill/chunks/funcApi-shim.js
function Bd(e) {
	return e === void 0 ? void 0 : Vd(e);
}
function Vd(e) {
	return Sd(e)();
}
var Hd = vd, Ud = /*@__PURE__*/ Mu(rd, class {
	get calendarId() {
		return An(Hd(this).calendar);
	}
	toJSON() {
		return cc(Hd(this));
	}
	valueOf() {
		return Fu();
	}
}, Hd, Lu, Zn);
function Wd(e) {
	let t = Object.create(Ud.prototype);
	return bd(t, e), Nu(t), t;
}
function Gd(e, t, n, r = 0, i = 0, a = 0, o = 0, s = 0, c = 0, l) {
	return Wd(Ma(va(ii(tn(At, {
		year: e,
		month: t,
		day: n,
		hour: r,
		minute: i,
		second: a,
		millisecond: o,
		microsecond: s,
		nanosecond: c
	}))), Bd(l)));
}
function Kd(e, t, n) {
	return Yd(Gl(Hd(e), Ic(Mc(t)), zs(n)));
}
var qd = hd, Jd = /*@__PURE__*/ Mu(id, class {
	get calendarId() {
		return An(qd(this).calendar);
	}
	get timeZoneId() {
		return qd(this).timeZone.id;
	}
	get epochMilliseconds() {
		return Ir(qd(this).epochNanoseconds);
	}
	get epochNanoseconds() {
		return qd(this).epochNanoseconds;
	}
	toJSON() {
		return oc(qd(this));
	}
	valueOf() {
		return Fu();
	}
}, Xd, Lu, Zn);
function Yd(e) {
	let t = Object.create(Jd.prototype);
	return _d(t, e), Nu(t), t;
}
function Xd(e) {
	let t = qd(e);
	return {
		...ro(t),
		calendar: t.calendar
	};
}
function Zd(e) {
	return ro(qd(e)).offsetNanoseconds;
}
Vn - 1, Bn - 1, zn - 1, Rn - 1, Ln - 1;
var Qd = fd, $d = /*@__PURE__*/ Mu(ad, class {
	get epochMilliseconds() {
		return Ir(Qd(this).epochNanoseconds);
	}
	get epochNanoseconds() {
		return Qd(this).epochNanoseconds;
	}
	toJSON() {
		return ic(Qd(this));
	}
	valueOf() {
		return Fu();
	}
});
function ef(e) {
	let t = Object.create($d.prototype);
	return md(t, e), Nu(t), t;
}
function tf(e) {
	return ef(Ql(e));
}
function nf(e, t) {
	return Yd(Bl(Qd(e), Ic(Mc(t))));
}
// ../../../../../localhost/Pushword/packages/newsletter/node_modules/temporal-polyfill/fns/ZonedDateTime.js
var rf = gt ? Pd : Zd, af = gt ? kd : Gd, of = gt ? Ad : Kd, sf = gt ? Rd : tf, cf = gt ? zd : nf;
// ../../../../../localhost/Pushword/packages/newsletter/node_modules/@full-ui/headless-calendar/index.js
function lf(e, t) {
	let n = Df(e);
	return n[2] += t * 7, Of(n);
}
function L(e, t) {
	let n = Df(e);
	return n[2] += t, Of(n);
}
function uf(e, t) {
	let n = Df(e);
	return n[6] += t, Of(n);
}
function df(e, t) {
	return ff(e, t) / 7;
}
function ff(e, t) {
	return (t.valueOf() - e.valueOf()) / 864e5;
}
function pf(e, t) {
	return (t.valueOf() - e.valueOf()) / 36e5;
}
function mf(e, t) {
	return (t.valueOf() - e.valueOf()) / 6e4;
}
function hf(e, t) {
	return (t.valueOf() - e.valueOf()) / 1e3;
}
function gf(e, t) {
	let n = R(e), r = R(t);
	return {
		years: 0,
		months: 0,
		days: Math.round(ff(n, r)),
		milliseconds: t.valueOf() - r.valueOf() - (e.valueOf() - n.valueOf())
	};
}
function _f(e, t) {
	let n = vf(e, t);
	return n !== null && n % 7 == 0 ? n / 7 : null;
}
function vf(e, t) {
	return Af(e) === Af(t) ? Math.round(ff(e, t)) : null;
}
function R(e) {
	return Of([
		e.getUTCFullYear(),
		e.getUTCMonth(),
		e.getUTCDate()
	]);
}
function yf(e) {
	return Of([
		e.getUTCFullYear(),
		e.getUTCMonth(),
		e.getUTCDate(),
		e.getUTCHours()
	]);
}
function bf(e) {
	return Of([
		e.getUTCFullYear(),
		e.getUTCMonth(),
		e.getUTCDate(),
		e.getUTCHours(),
		e.getUTCMinutes()
	]);
}
function xf(e) {
	return Of([
		e.getUTCFullYear(),
		e.getUTCMonth(),
		e.getUTCDate(),
		e.getUTCHours(),
		e.getUTCMinutes(),
		e.getUTCSeconds()
	]);
}
function Sf(e, t, n) {
	let r = e.getUTCFullYear(), i = Cf(e, r, t, n);
	if (i < 1) return Cf(e, r - 1, t, n);
	let a = Cf(e, r + 1, t, n);
	return a >= 1 ? Math.min(i, a) : i;
}
function Cf(e, t, n, r) {
	let i = Of([
		t,
		0,
		1 + wf(t, n, r)
	]), a = R(e), o = Math.round(ff(i, a));
	return Math.floor(o / 7) + 1;
}
function wf(e, t, n) {
	let r = 7 + t - n;
	return -((7 + Of([
		e,
		0,
		r
	]).getUTCDay() - t) % 7) + r - 1;
}
function Tf(e) {
	return [
		e.getFullYear(),
		e.getMonth(),
		e.getDate(),
		e.getHours(),
		e.getMinutes(),
		e.getSeconds(),
		e.getMilliseconds()
	];
}
function Ef(e) {
	return new Date(e[0], e[1] || 0, e[2] == null ? 1 : e[2], e[3] || 0, e[4] || 0, e[5] || 0);
}
function Df(e) {
	return [
		e.getUTCFullYear(),
		e.getUTCMonth(),
		e.getUTCDate(),
		e.getUTCHours(),
		e.getUTCMinutes(),
		e.getUTCSeconds(),
		e.getUTCMilliseconds()
	];
}
function Of(e) {
	return e.length === 1 && (e = e.concat([0])), new Date(Date.UTC(...e));
}
function kf(e) {
	return !isNaN(e.valueOf());
}
function Af(e) {
	return e.getUTCHours() * 1e3 * 60 * 60 + e.getUTCMinutes() * 1e3 * 60 + e.getUTCSeconds() * 1e3 + e.getUTCMilliseconds();
}
var jf = {};
function Mf(e, t) {
	jf[e] = t;
}
function Nf(e) {
	return new jf[e]();
}
Mf("gregory", class {
	getMarkerYear(e) {
		return e.getUTCFullYear();
	}
	getMarkerMonth(e) {
		return e.getUTCMonth();
	}
	getMarkerDay(e) {
		return e.getUTCDate();
	}
	arrayToMarker(e) {
		return Of(e);
	}
	markerToArray(e) {
		return Df(e);
	}
});
function Pf(e, t) {
	let n = null, r = null;
	return e.start && (n = t.createMarker(e.start)), e.end && (r = t.createMarker(e.end)), !n && !r || n && r && r < n ? null : {
		start: n,
		end: r
	};
}
function Ff(e, t) {
	let n = [], { start: r } = t, i, a;
	for (e.sort(If), i = 0; i < e.length; i += 1) a = e[i], a.start > r && n.push({
		start: r,
		end: a.start
	}), a.end > r && (r = a.end);
	return r < t.end && n.push({
		start: r,
		end: t.end
	}), n;
}
function If(e, t) {
	return e.start.valueOf() - t.start.valueOf();
}
function Lf(e, t) {
	let { start: n, end: r } = e, i = null;
	return t.start !== null && (n = n === null ? t.start : new Date(Math.max(n.valueOf(), t.start.valueOf()))), t.end != null && (r = r === null ? t.end : new Date(Math.min(r.valueOf(), t.end.valueOf()))), (n === null || r === null || n < r) && (i = {
		start: n,
		end: r
	}), i;
}
function Rf(e, t) {
	return (e.start === null ? null : e.start.valueOf()) === (t.start === null ? null : t.start.valueOf()) && (e.end === null ? null : e.end.valueOf()) === (t.end === null ? null : t.end.valueOf());
}
function zf(e, t) {
	return (e.end === null || t.start === null || e.end > t.start) && (e.start === null || t.end === null || e.start < t.end);
}
function Bf(e, t) {
	return (e.start === null || t.start !== null && t.start >= e.start) && (e.end === null || t.end !== null && t.end <= e.end);
}
function Vf(e, t) {
	return (e.start === null || t >= e.start) && (e.end === null || t < e.end);
}
function Hf(e, t) {
	return t.start != null && e < t.start ? t.start : t.end != null && e >= t.end ? /* @__PURE__ */ new Date(t.end.valueOf() - 1) : e;
}
function Uf(e, t) {
	let n = t.markerToArray(e.marker);
	return {
		marker: e.marker,
		instantMs: e.instantMs,
		timeZoneOffset: (e.marker.valueOf() - e.instantMs) / 6e4,
		array: n,
		year: n[0],
		month: n[1],
		day: n[2],
		hour: n[3],
		minute: n[4],
		second: n[5],
		millisecond: n[6]
	};
}
function Wf(e, t, n) {
	let r = Uf(e, n.calendarSystem);
	return {
		date: r,
		start: r,
		end: t ? Uf(t, n.calendarSystem) : null,
		timeZone: n.timeZone,
		localeCodes: n.locale.codes
	};
}
function Gf(e) {
	return e % 1 == 0;
}
function Kf(e, t) {
	let n = String(e);
	return "000".substr(0, t - n.length) + n;
}
var qf = [
	"years",
	"months",
	"days",
	"milliseconds"
], Jf = /^(-?)(?:(\d+)\.)?(\d+):(\d\d)(?::(\d\d)(?:\.(\d\d\d))?)?/;
function z(e, t) {
	return typeof e == "string" ? Yf(e) : typeof e == "object" && e ? Xf(e) : typeof e == "number" ? Xf({ [t || "milliseconds"]: e }) : null;
}
function Yf(e) {
	let t = Jf.exec(e);
	if (t) {
		let e = t[1] ? -1 : 1;
		return {
			years: 0,
			months: 0,
			days: e * (t[2] ? parseInt(t[2], 10) : 0),
			milliseconds: e * ((t[3] ? parseInt(t[3], 10) : 0) * 60 * 60 * 1e3 + (t[4] ? parseInt(t[4], 10) : 0) * 60 * 1e3 + (t[5] ? parseInt(t[5], 10) : 0) * 1e3 + (t[6] ? parseInt(t[6], 10) : 0))
		};
	}
	return null;
}
function Xf(e) {
	let t = {
		years: e.years || e.year || 0,
		months: e.months || e.month || 0,
		days: e.days || e.day || 0,
		milliseconds: (e.hours || e.hour || 0) * 60 * 60 * 1e3 + (e.minutes || e.minute || 0) * 60 * 1e3 + (e.seconds || e.second || 0) * 1e3 + (e.milliseconds || e.millisecond || e.ms || 0)
	}, n = e.weeks || e.week;
	return n && (t.days += n * 7, t.specifiedWeeks = !0), t;
}
function Zf(e, t) {
	return e.years === t.years && e.months === t.months && e.days === t.days && e.milliseconds === t.milliseconds;
}
function Qf(e, t) {
	return {
		years: e.years + t.years,
		months: e.months + t.months,
		days: e.days + t.days,
		milliseconds: e.milliseconds + t.milliseconds
	};
}
function $f(e, t) {
	return {
		years: e.years - t.years,
		months: e.months - t.months,
		days: e.days - t.days,
		milliseconds: e.milliseconds - t.milliseconds
	};
}
function ep(e, t) {
	return {
		years: e.years * t,
		months: e.months * t,
		days: e.days * t,
		milliseconds: e.milliseconds * t
	};
}
function tp(e) {
	return rp(e) / 365;
}
function np(e) {
	return rp(e) / 30;
}
function rp(e) {
	return ip(e) / 864e5;
}
function ip(e) {
	return e.years * 31536e6 + e.months * 2592e6 + e.days * 864e5 + e.milliseconds;
}
function ap(e, t) {
	let n = null;
	for (let r = 0; r < qf.length; r += 1) {
		let i = qf[r];
		if (t[i]) {
			let r = e[i] / t[i];
			if (!Gf(r) || n !== null && n !== r) return null;
			n = r;
		} else if (e[i]) return null;
	}
	return n;
}
function op(e) {
	let t = e.milliseconds;
	if (t) {
		if (t % 1e3 != 0) return {
			unit: "millisecond",
			value: t
		};
		if (t % 6e4 != 0) return {
			unit: "second",
			value: t / 1e3
		};
		if (t % 36e5 != 0) return {
			unit: "minute",
			value: t / 6e4
		};
		if (t) return {
			unit: "hour",
			value: t / 36e5
		};
	}
	return e.days ? e.specifiedWeeks && e.days % 7 == 0 ? {
		unit: "week",
		value: e.days / 7
	} : {
		unit: "day",
		value: e.days
	} : e.months ? {
		unit: "month",
		value: e.months
	} : e.years ? {
		unit: "year",
		value: e.years
	} : {
		unit: "millisecond",
		value: 0
	};
}
function sp(e, t, n = !1) {
	let r = e.toISOString();
	return r = r.replace(".000", ""), n && (r = r.replace("T00:00:00Z", "")), r.length > 10 && (t == null ? r = r.replace("Z", "") : t !== 0 && (r = r.replace("Z", dp(t, !0)))), r;
}
function cp(e) {
	return e.toISOString().replace(/T.*$/, "");
}
function lp(e) {
	return e.toISOString().match(/^\d{4}-\d{2}/)[0];
}
function up(e) {
	return Kf(e.getUTCHours(), 2) + ":" + Kf(e.getUTCMinutes(), 2) + ":" + Kf(e.getUTCSeconds(), 2);
}
function dp(e, t = !1) {
	let n = e < 0 ? "-" : "+", r = Math.abs(e), i = Math.floor(r / 60), a = Math.round(r % 60);
	return t ? `${n + Kf(i, 2)}:${Kf(a, 2)}` : `GMT${n}${i}${a ? `:${Kf(a, 2)}` : ""}`;
}
function fp(e) {
	let t = "";
	for (let n of e) t += n.value;
	return t;
}
var pp = /^\s*(\d{4})(-?(\d{2})(-?(\d{2})([T ](\d{2}):?(\d{2})(:?(\d{2})(\.(\d+))?)?(Z|(([-+])(\d{2})(:?(\d{2}))?))?)?)?)?$/;
function mp(e) {
	let t = pp.exec(e);
	if (t) {
		let e = new Date(Date.UTC(Number(t[1]), t[3] ? Number(t[3]) - 1 : 0, Number(t[5] || 1), Number(t[7] || 0), Number(t[8] || 0), Number(t[10] || 0), t[12] ? Number(`0.${t[12]}`) * 1e3 : 0));
		if (kf(e)) {
			let n = null;
			return t[13] && (n = (t[15] === "-" ? -1 : 1) * (Number(t[16] || 0) * 60 + Number(t[18] || 0))), {
				marker: e,
				isTimeUnspecified: !t[6],
				timeZoneOffset: n
			};
		}
	}
	return null;
}
var hp = class {
	constructor(e) {
		this.timeZone = e.timeZone, this.calendarSystem = Nf(e.calendarSystem), this.locale = e.locale, this.weekDow = e.locale.week.dow, this.weekDoy = e.locale.week.doy, e.weekNumberCalculation === "ISO" && (this.weekDow = 1, this.weekDoy = 4), typeof e.firstDay == "number" && (this.weekDow = e.firstDay), typeof e.weekNumberCalculation == "function" && (this.weekNumberFunc = e.weekNumberCalculation), this.weekTextLong = e.weekTextLong, this.weekTextShort = e.weekTextShort ?? e.weekTextLong, this.cmdFormatter = e.cmdFormatter;
	}
	createMarker(e) {
		let t = this.createMarkerMeta(e);
		return t === null ? null : t.marker;
	}
	createNowMarker() {
		return this.timestampToMarker((/* @__PURE__ */ new Date()).valueOf());
	}
	createMarkerMeta(e) {
		if (typeof e == "string") return this.parse(e);
		let t = null, n;
		return typeof e == "number" ? (t = this.timestampToMarker(e), n = e) : e instanceof Date ? (e = e.valueOf(), isNaN(e) || (t = this.timestampToMarker(e), n = e)) : Array.isArray(e) && (t = Of(e)), t === null || !kf(t) ? null : {
			marker: t,
			isTimeUnspecified: !1,
			instantMs: n
		};
	}
	parse(e) {
		let t = mp(e);
		if (t === null) return null;
		let { marker: n } = t, r;
		return t.timeZoneOffset !== null && (r = n.valueOf() - t.timeZoneOffset * 60 * 1e3, n = this.timestampToMarker(r)), {
			marker: n,
			isTimeUnspecified: t.isTimeUnspecified,
			instantMs: r
		};
	}
	getYear(e) {
		return this.calendarSystem.getMarkerYear(e);
	}
	getMonth(e) {
		return this.calendarSystem.getMarkerMonth(e);
	}
	getDay(e) {
		return this.calendarSystem.getMarkerDay(e);
	}
	add(e, t) {
		let n = this.calendarSystem.markerToArray(e);
		return n[0] += t.years, n[1] += t.months, n[2] += t.days, n[6] += t.milliseconds, this.calendarSystem.arrayToMarker(n);
	}
	subtract(e, t) {
		let n = this.calendarSystem.markerToArray(e);
		return n[0] -= t.years, n[1] -= t.months, n[2] -= t.days, n[6] -= t.milliseconds, this.calendarSystem.arrayToMarker(n);
	}
	addYears(e, t) {
		let n = this.calendarSystem.markerToArray(e);
		return n[0] += t, this.calendarSystem.arrayToMarker(n);
	}
	addMonths(e, t) {
		let n = this.calendarSystem.markerToArray(e);
		return n[1] += t, this.calendarSystem.arrayToMarker(n);
	}
	diffWholeYears(e, t) {
		let { calendarSystem: n } = this;
		return Af(e) === Af(t) && n.getMarkerDay(e) === n.getMarkerDay(t) && n.getMarkerMonth(e) === n.getMarkerMonth(t) ? n.getMarkerYear(t) - n.getMarkerYear(e) : null;
	}
	diffWholeMonths(e, t) {
		let { calendarSystem: n } = this;
		return Af(e) === Af(t) && n.getMarkerDay(e) === n.getMarkerDay(t) ? n.getMarkerMonth(t) - n.getMarkerMonth(e) + (n.getMarkerYear(t) - n.getMarkerYear(e)) * 12 : null;
	}
	greatestWholeUnit(e, t) {
		let n = this.diffWholeYears(e, t);
		return n === null ? (n = this.diffWholeMonths(e, t), n === null ? (n = _f(e, t), n === null ? (n = vf(e, t), n === null ? (n = pf(e, t), Gf(n) ? {
			unit: "hour",
			value: n
		} : (n = mf(e, t), Gf(n) ? {
			unit: "minute",
			value: n
		} : (n = hf(e, t), Gf(n) ? {
			unit: "second",
			value: n
		} : {
			unit: "millisecond",
			value: t.valueOf() - e.valueOf()
		}))) : {
			unit: "day",
			value: n
		}) : {
			unit: "week",
			value: n
		}) : {
			unit: "month",
			value: n
		}) : {
			unit: "year",
			value: n
		};
	}
	countDurationsBetween(e, t, n) {
		let r;
		return n.years && (r = this.diffWholeYears(e, t), r !== null) ? r / tp(n) : n.months && (r = this.diffWholeMonths(e, t), r !== null) ? r / np(n) : n.days && (r = vf(e, t), r !== null) ? r / rp(n) : (t.valueOf() - e.valueOf()) / ip(n);
	}
	startOf(e, t) {
		return t === "year" ? this.startOfYear(e) : t === "month" ? this.startOfMonth(e) : t === "week" ? this.startOfWeek(e) : t === "day" ? R(e) : t === "hour" ? yf(e) : t === "minute" ? bf(e) : t === "second" ? xf(e) : null;
	}
	startOfYear(e) {
		return this.calendarSystem.arrayToMarker([this.calendarSystem.getMarkerYear(e)]);
	}
	startOfMonth(e) {
		return this.calendarSystem.arrayToMarker([this.calendarSystem.getMarkerYear(e), this.calendarSystem.getMarkerMonth(e)]);
	}
	startOfWeek(e) {
		return this.calendarSystem.arrayToMarker([
			this.calendarSystem.getMarkerYear(e),
			this.calendarSystem.getMarkerMonth(e),
			e.getUTCDate() - (e.getUTCDay() - this.weekDow + 7) % 7
		]);
	}
	computeWeekNumber(e) {
		return this.weekNumberFunc ? this.weekNumberFunc(this.toDate(e)) : Sf(e, this.weekDow, this.weekDoy);
	}
	formatToParts(e, t, n = {}) {
		return t.formatToParts(this.toZonedInstant(e, n.instantMs), this);
	}
	formatRangeToParts(e, t, n, r = {}) {
		let { endInstantMs: i } = r;
		return r.isEndExclusive && (t = uf(t, -1), i != null && --i), n.formatRangeToParts(this.toZonedInstant(e, r.startInstantMs), this.toZonedInstant(t, i), this);
	}
	toZonedInstant(e, t) {
		return t ??= this.toDate(e).valueOf(), {
			marker: this.timestampToMarker(t),
			instantMs: t
		};
	}
	formatIso(e, t = {}) {
		let n = null;
		return t.omitTimeZoneOffset || (n = this.offsetForMarker(e)), sp(e, n, t.omitTime);
	}
	timestampToMarker(e) {
		if (this.timeZone === "local") return Of(Tf(new Date(e)));
		if (this.timeZone === "UTC") return new Date(e);
		let t = cf(sf(e), this.timeZone);
		return new Date(Date.UTC(t.year, t.month - 1, t.day, t.hour, t.minute, t.second, t.millisecond));
	}
	offsetForMarker(e) {
		return this.timeZone === "local" ? -Ef(Df(e)).getTimezoneOffset() : this.timeZone === "UTC" ? 0 : rf(of(af(e.getUTCFullYear(), e.getUTCMonth() + 1, e.getUTCDate(), e.getUTCHours(), e.getUTCMinutes(), e.getUTCSeconds(), e.getUTCMilliseconds()), this.timeZone)) / 6e10;
	}
	toDate(e) {
		return this.timeZone === "local" ? Ef(Df(e)) : this.timeZone === "UTC" ? new Date(e.valueOf()) : new Date(of(af(e.getUTCFullYear(), e.getUTCMonth() + 1, e.getUTCDate(), e.getUTCHours(), e.getUTCMinutes(), e.getUTCSeconds(), e.getUTCMilliseconds()), this.timeZone).epochMilliseconds);
	}
}, gp = /* @__PURE__ */ new Set([
	"week",
	"meridiem",
	"omitZeroMinute",
	"omitCommas",
	"forceCommas",
	"omitTrailing",
	"weekdayJustify"
]), _p = /([ap])\.?m\.?/i, vp = /,/g, yp = /\u200e/g, bp = /[\s.,]+$/, xp = /^\s+$/, Sp = class {
	constructor(e) {
		let t = {}, n = {};
		for (let r in e) gp.has(r) ? n[r] = e[r] : t[r] = e[r];
		t.timeZoneName &&= "shortOffset", this.timeZoneOnly = Object.keys(t).length === 1 && !!t.timeZoneName, this.weekOnly = !(Object.keys(t).length || !n.week), this.timeZoneOnly || (t.timeZoneName && (t.hour ||= "2-digit", t.minute ||= "2-digit"), n.omitZeroMinute && (t.second || t.fractionalSecondDigits) && delete n.omitZeroMinute), this.standardOptions = t, this.extendedOptions = n;
	}
	formatToParts(e, t) {
		let { extendedOptions: n } = this;
		if (this.timeZoneOnly) return this.getFormats(t).normalFormat.formatToParts(e.instantMs).filter((e) => e.type === "timeZoneName");
		if (this.weekOnly) return Dp(t.computeWeekNumber(e.marker), t.weekTextLong, t.weekTextShort, t.locale, n.week);
		let { normalFormat: r, zeroFormat: i } = this.getFormats(t);
		return wp((i && !e.marker.getUTCMinutes() ? i : r).formatToParts(e.instantMs), n);
	}
	formatRangeToParts(e, t, n) {
		let { extendedOptions: r } = this;
		if (this.timeZoneOnly || this.weekOnly) return this.formatToParts(e, n).map((e) => ({
			source: e.type === "literal" ? "shared" : "startRange",
			...e
		}));
		let { normalFormat: i, zeroFormat: a } = this.getFormats(n);
		return Tp((a && !e.marker.getUTCMinutes() && !t.marker.getUTCMinutes() ? a : i).formatRangeToParts(e.instantMs, t.instantMs), r);
	}
	getFormats(e) {
		if (this.cachedContext !== e) {
			let { extendedOptions: t } = this, { codes: n } = e.locale, r = {
				...this.standardOptions,
				timeZone: e.timeZone === "local" ? void 0 : e.timeZone
			}, i = new Intl.DateTimeFormat(n, r), a;
			if (t.omitZeroMinute) {
				let e = { ...r };
				delete e.minute, a = new Intl.DateTimeFormat(n, e);
			}
			this.cachedContext = e, this.cachedFormats = {
				normalFormat: i,
				zeroFormat: a
			};
		}
		return this.cachedFormats;
	}
};
function Cp(e, t) {
	let n;
	for (let r of e) {
		let e = r.type === "literal";
		if (e || r.type === "dayPeriod") {
			let i = r.value;
			if (i = i.replace(yp, ""), t.omitCommas && (i = i.replace(vp, "")), !e) {
				let { meridiem: e } = t;
				e === !1 ? i = i.replace(_p, "") : e === "narrow" ? i = i.replace(_p, (e, t) => t.toLocaleLowerCase()) : e === "short" ? i = i.replace(_p, (e, t) => `${t.toLocaleLowerCase()}m`) : e === "lowercase" && (i = i.replace(_p, (e) => e.toLocaleLowerCase())), n && (n.value = n.value.trimEnd());
			}
			r.value = i;
		}
		n = e ? r : void 0;
	}
}
function wp(e, t) {
	if (Cp(e, t), t.weekdayJustify && e.length === 3 && xp.test(e[1].value) && e[t.weekdayJustify === "start" ? 2 : 0].type === "weekday" && e.reverse(), t.forceCommas) for (let t of e) t.type === "literal" && xp.test(t.value) && (t.value = `,${t.value}`);
	return t.omitTrailing && Ep(e), e.filter((e) => e.value);
}
function Tp(e, t) {
	if (Cp(e, t), t.forceCommas) for (let t of e) t.type === "literal" && xp.test(t.value) && (t.value = `,${t.value}`);
	return t.omitTrailing && Ep(e), e.filter((e) => e.value);
}
function Ep(e) {
	let t = e[e.length - 1];
	t?.type === "literal" && (t.value = t.value.replace(bp, ""), t.value || e.pop());
}
function Dp(e, t, n, r, i) {
	let a = [];
	return i === "long" ? a.push({
		type: "literal",
		value: t
	}) : (i === "short" || i === "narrow") && a.push({
		type: "literal",
		value: n
	}), (i === "long" || i === "short") && a.push({
		type: "literal",
		value: " "
	}), a.push({
		type: "week",
		value: r.simpleNumberFormat.format(e)
	}), r.options.direction === "rtl" && a.reverse(), a;
}
var Op = class {
	constructor(e) {
		this.cmdStr = e;
	}
	formatToParts(e, t) {
		let n = t.cmdFormatter(this.cmdStr, Wf(e, null, t));
		return Array.isArray(n) ? n : [{
			type: "literal",
			value: n
		}];
	}
	formatRangeToParts(e, t, n) {
		let r = n.cmdFormatter(this.cmdStr, Wf(e, t, n));
		return Array.isArray(r) ? r.map((e) => ({
			source: "shared",
			...e
		})) : [{
			source: "shared",
			type: "literal",
			value: r
		}];
	}
}, kp = class {
	constructor(e) {
		this.func = e;
	}
	formatToParts(e, t) {
		return [{
			type: "literal",
			value: this.func(Wf(e, null, t))
		}];
	}
	formatRangeToParts(e, t, n) {
		return [{
			source: "shared",
			type: "literal",
			value: this.func(Wf(e, t, n))
		}];
	}
}, B = {
	z0: "fc-5r",
	z1: "fc-de",
	z2: "fc-h9",
	z3: "fc-Ox",
	z4: "fc-4g",
	z5: "fc-zk",
	z1000: "fc-a0",
	z9999: "fc-ql",
	focusZ2: "fc-RJ",
	popoverZ: "fc-7k",
	isolate: "fc-tX",
	borderBoxRoot: "fc-Wz",
	notAllowed: "fc-NA",
	noScrollbars: "fc-vt",
	noShrink: "fc-YA",
	calendarScreenRoot: "fc-uw",
	safeTiles: "fc-De",
	calendarPrintRoot: "fc-ZA",
	cursorPointer: "fc-jF",
	cursorResizeT: "fc-TD",
	cursorResizeB: "fc-3Z",
	cursorResizeS: "fc-dx",
	cursorResizeE: "fc-ec",
	cursorColResizer: "fc-AA",
	hit: "fc-ai",
	hitX: "fc-bx",
	hitY: "fc-i9",
	hitXSkinny: "fc-hx",
	selectNone: "fc-fg",
	borderless: "fc-4z",
	borderlessX: "fc-ca",
	borderlessY: "fc-Zq",
	borderlessTop: "fc-Im",
	borderlessBottom: "fc-67",
	borderlessStart: "fc-MR",
	borderlessEnd: "fc-Y4",
	flexRow: "fc-lJ",
	flexCol: "fc-Xt",
	grow: "fc-0U",
	liquid: "fc-6W",
	minHeight0: "fc-jx",
	liquidX: "fc-p5",
	printTable: "fc-wU",
	noPadding: "fc-jj",
	noPaddingY: "fc-jf",
	noMargin: "fc-Kp",
	noMarginY: "fc-G5",
	noMarginX: "fc-Ut",
	whiteSpaceNoWrap: "fc-Eu",
	whiteSpacePre: "fc-Ex",
	overflowAnchorNone: "fc-FS",
	pointerEventsNone: "fc-A2",
	crop: "fc-ip",
	cropNowrap: "fc-i0",
	rel: "fc-hC",
	abs: "fc-My",
	start0: "fc-X7",
	end0: "fc-WB",
	fill: "fc-YR",
	fillTop: "fc-dz",
	fillX: "fc-Db",
	fillY: "fc-QB",
	fillStart: "fc-3q",
	sticky: "fc-Jx",
	stickyT: "fc-Gm",
	stickyS: "fc-HK",
	tableHeaderSticky: "fc-cF",
	contentBox: "fc-KX",
	offscreen: "fc-Gb",
	srOnly: "fc-8Z",
	alignCenter: "fc-Id",
	alignStart: "fc-PS",
	alignEnd: "fc-Ot",
	footerScrollbarSticky: "fc-Sm",
	footerScrollbar: "fc-Rw",
	breakInsideAvoid: "fc-AH",
	printCellContentMinHeight: "fc-Xq",
	flowRoot: "fc-sr",
	internalTimelineSlot: "fc-Lg",
	internalEvent: "fc-Bq",
	internalEventMirror: "fc-Nb",
	internalEventDraggable: "fc-Wg",
	internalEventSelected: "fc-nT",
	internalEventResizable: "fc-sK",
	internalEventResizer: "fc-LZ",
	internalEventResizerStart: "fc-k1",
	internalEventResizerEnd: "fc-CC",
	internalBgEvent: "fc-Wh",
	internalMoreLink: "fc-ju",
	internalNavLink: "fc-2b",
	internalPopover: "fc-bK",
	internalView: "fc-wB",
	internalScroller: "fc-TW"
};
// ../../../../../localhost/Pushword/packages/newsletter/node_modules/fullcalendar/chunks/03367050.js
function V(...e) {
	return e.filter(Boolean).join(" ");
}
function Ap(e) {
	return e * 100 + "%";
}
// ../../../../../localhost/Pushword/packages/newsletter/node_modules/fullcalendar/chunks/3954681c.js
function H(e) {
	return typeof e == "object" && e ? new Sp(e) : typeof e == "string" ? new Op(e) : typeof e == "function" ? new kp(e) : null;
}
function jp(...e) {
	console.warn("FullCalendar:", ...e);
}
var Mp = {};
function U(e, t) {
	return !e || typeof e == "string" ? e : (Np(t), "");
}
function W(e, t) {
	return typeof e == "function" ? (n) => U(e(n), t) : U(e, t);
}
function Np(e) {
	Mp[e] || (jp(`Invalid option \`${e}\`: expected a className string or a falsy value.`), Mp[e] = !0);
}
function Pp(e) {
	e.preventDefault();
}
function Fp(e, t) {
	return (n) => {
		let r = n.target.closest(e);
		r && t.call(r, n, r);
	};
}
function Ip(e, t, n, r) {
	let i = Fp(n, r);
	return e.addEventListener(t, i), () => {
		e.removeEventListener(t, i);
	};
}
function Lp(e, t, n, r) {
	let i;
	return Ip(e, "mouseover", t, (e, t) => {
		if (t !== i) {
			i = t, n(e, t);
			let a = (e) => {
				i = null, r(e, t), t.removeEventListener("mouseleave", a);
			};
			t.addEventListener("mouseleave", a);
		}
	});
}
var Rp = [
	"webkitTransitionEnd",
	"otransitionend",
	"oTransitionEnd",
	"msTransitionEnd",
	"transitionend"
];
function zp(e, t) {
	let n = (r) => {
		t(r), Rp.forEach((t) => {
			e.removeEventListener(t, n);
		});
	};
	Rp.forEach((t) => {
		e.addEventListener(t, n);
	});
}
function Bp(e) {
	return {
		onClick: e,
		...Vp(e)
	};
}
function Vp(e) {
	return {
		tabIndex: 0,
		onKeyDown(t) {
			(t.key === "Enter" || t.key === " ") && (e(t), t.preventDefault());
		}
	};
}
var Hp = 0;
function Up() {
	return Hp += 1, String(Hp);
}
function Wp() {
	document.body.classList.add(B.notAllowed);
}
function Gp() {
	document.body.classList.remove(B.notAllowed);
}
function Kp(e) {
	e.style.userSelect = "none", e.style.webkitUserSelect = "none", e.addEventListener("selectstart", Pp);
}
function qp(e) {
	e.style.userSelect = "", e.style.webkitUserSelect = "", e.removeEventListener("selectstart", Pp);
}
function Jp(e) {
	e.addEventListener("contextmenu", Pp);
}
function Yp(e) {
	e.removeEventListener("contextmenu", Pp);
}
function Xp(e) {
	let t = [], n = [], r, i;
	for (typeof e == "string" ? n = e.split(/\s*,\s*/) : typeof e == "function" ? n = [e] : Array.isArray(e) && (n = e), r = 0; r < n.length; r += 1) i = n[r], typeof i == "string" ? t.push(i.charAt(0) === "-" ? {
		field: i.substring(1),
		order: -1
	} : {
		field: i,
		order: 1
	}) : typeof i == "function" && t.push({ func: i });
	return t;
}
function Zp(e, t, n) {
	let r, i;
	for (r = 0; r < n.length; r += 1) if (i = Qp(e, t, n[r]), i) return i;
	return 0;
}
function Qp(e, t, n) {
	return n.func ? n.func(e, t) : $p(e[n.field], t[n.field]) * (n.order || 1);
}
function $p(e, t) {
	return !e && !t ? 0 : t == null ? -1 : e == null ? 1 : typeof e == "string" || typeof t == "string" ? String(e).localeCompare(String(t)) : e - t;
}
function em(e, t, n) {
	return typeof e == "function" ? e(...t) : typeof e == "string" ? t.reduce((e, t, n) => e.replace("$" + n, t || ""), e) : n;
}
function tm(e, t) {
	return e - t;
}
function nm(e, t) {
	return e === t;
}
function rm(e) {
	let t = e.borderless;
	return {
		borderlessX: !!(e.borderlessX ?? t),
		borderlessTop: !!(e.borderlessTop ?? t),
		borderlessBottom: !!(e.borderlessBottom ?? t)
	};
}
function im(e) {
	let t = [];
	for (let n of e) if (Array.isArray(n)) for (let e of n) t.push(e);
	else t.push(n);
	return t;
}
function am(e, t) {
	let n = [];
	for (let r = 0; r < e.length; r += 1) {
		let i = t(e[r], r);
		if (Array.isArray(i)) for (let e of i) n.push(e);
		else n.push(i);
	}
	return n;
}
function om(e, t) {
	return Array.isArray(e) && Array.isArray(t) ? sm(e, t) : e === t;
}
function sm(e, t, n = nm) {
	if (e === t) return !0;
	let r = e.length, i;
	if (r !== t.length) return !1;
	for (i = 0; i < r; i += 1) if (!n(e[i], t[i])) return !1;
	return !0;
}
var { hasOwnProperty: cm } = Object.prototype;
function lm(e, t) {
	let n = {};
	for (let r in e) t(e[r], r) && (n[r] = e[r]);
	return n;
}
function um(e, t) {
	let n = {};
	for (let r in e) n[r] = t(e[r], r);
	return n;
}
function dm(e) {
	let t = [];
	for (let n in e) t.push(e[n]);
	return t;
}
function fm(e) {
	let t = {};
	for (let n of e) t[n] = !0;
	return t;
}
function pm(e, t) {
	return typeof e == "object" && e && typeof t == "object" && t ? mm(e, t, gm) : e === t;
}
function mm(e, t, n) {
	if (e === t) return !0;
	for (let n in e) if (cm.call(e, n) && !(n in t)) return !1;
	for (let r in t) if (cm.call(t, r) && (!(r in e) || !n(e[r], t[r], r))) return !1;
	return !0;
}
function hm(e, t) {
	return typeof e == "object" && typeof t == "object" && e && t ? gm(e, t) : e === t;
}
function gm(e, t) {
	return mm(e, t, nm);
}
function _m(e, t) {
	return typeof e == "object" && typeof t == "object" && e && t ? mm(e, t, vm) : e === t;
}
function vm(e, t) {
	return e instanceof Date && t instanceof Date ? e.valueOf() === t.valueOf() : Array.isArray(e) && Array.isArray(t) ? sm(e, t) : e === t;
}
function ym(e, t, n) {
	return mm(e, t, (e, t, r) => {
		let i = n[r];
		return i ? i(e, t) : e === t;
	});
}
function bm(e, t) {
	let n = [];
	for (let r in e) cm.call(e, r) && (r in t || n.push(r));
	for (let r in t) cm.call(t, r) && e[r] !== t[r] && n.push(r);
	return n;
}
function xm(e, t) {
	return e ? Sm(e, t, Cm) : t;
}
function Sm(e, t, n) {
	let r = {};
	for (let n in e) cm.call(e, n) && (n in t || (r[n] = e[n]));
	for (let i in t) cm.call(t, i) && (r[i] = i in e ? n(e[i], t[i]) : t[i]);
	return r;
}
function Cm(e, t) {
	return Object.assign({}, e, t);
}
var wm = {
	navLinkDayClick: G,
	navLinkWeekClick: G,
	duration: z,
	buttons: G,
	toolbarElements: G,
	prevText: String,
	nextText: String,
	prevYearText: String,
	nextYearText: String,
	todayText: String,
	yearText: String,
	monthText: String,
	weekTextLong: String,
	weekTextShort: String,
	dayText: String,
	listText: G,
	todayHint: G,
	prevHint: G,
	nextHint: G,
	buttonDisplay: G,
	buttonGroupClass: W,
	buttonClass: W,
	defaultAllDayEventDuration: z,
	defaultTimedEventDuration: z,
	nextDayThreshold: z,
	scrollTime: z,
	scrollTimeReset: Boolean,
	slotMinTime: z,
	slotMaxTime: z,
	popoverFormat: H,
	slotDuration: z,
	snapDuration: z,
	headerToolbar: G,
	footerToolbar: G,
	forceEventDuration: Boolean,
	dayLaneClass: W,
	dayLaneInnerClass: W,
	dayLaneDidMount: G,
	dayLaneWillUnmount: G,
	initialView: String,
	aspectRatio: Number,
	weekends: Boolean,
	weekNumberCalculation: G,
	weekNumbers: Boolean,
	weekNumberHeaderClass: W,
	weekNumberHeaderInnerClass: W,
	weekNumberHeaderContent: G,
	weekNumberHeaderDidMount: G,
	weekNumberHeaderWillUnmount: G,
	inlineWeekNumberClass: W,
	inlineWeekNumberContent: G,
	inlineWeekNumberDidMount: G,
	inlineWeekNumberWillUnmount: G,
	editable: Boolean,
	controller: G,
	nowIndicator: Boolean,
	nowIndicatorSnap: G,
	nowIndicatorHeaderClass: W,
	nowIndicatorHeaderContent: G,
	nowIndicatorHeaderDidMount: G,
	nowIndicatorHeaderWillUnmount: G,
	nowIndicatorDotClass: U,
	nowIndicatorLineClass: W,
	nowIndicatorLineContent: G,
	nowIndicatorLineDidMount: G,
	nowIndicatorLineWillUnmount: G,
	showNonCurrentDates: Boolean,
	lazyFetching: Boolean,
	startParam: String,
	endParam: String,
	timeZoneParam: String,
	timeZone: String,
	locales: G,
	locale: G,
	dragRevertDuration: Number,
	dragScroll: Boolean,
	allDayMaintainDuration: Boolean,
	unselectAuto: Boolean,
	dropAccept: G,
	eventOrder: Xp,
	eventOrderStrict: Boolean,
	eventSlicing: Boolean,
	eventPrintLayout: String,
	longPressDelay: Number,
	eventDragMinDistance: Number,
	expandRows: Boolean,
	height: G,
	contentHeight: G,
	direction: String,
	colorScheme: String,
	weekNumberFormat: H,
	eventResizableFromStart: Boolean,
	displayEventTime: Boolean,
	displayEventEnd: Boolean,
	progressiveEventRendering: Boolean,
	businessHours: G,
	initialDate: G,
	now: G,
	eventDataTransform: G,
	tableHeaderSticky: G,
	footerScrollbarSticky: G,
	defaultAllDay: Boolean,
	eventSourceFailure: G,
	eventSourceSuccess: G,
	eventDisplay: String,
	eventStartEditable: Boolean,
	eventDurationEditable: Boolean,
	eventOverlap: G,
	eventConstraint: G,
	eventAllow: G,
	eventColor: String,
	eventContrastColor: String,
	eventDidMount: G,
	eventWillUnmount: G,
	eventContent: G,
	eventClass: W,
	eventInnerClass: W,
	eventTimeClass: W,
	eventTitleClass: W,
	eventBeforeClass: W,
	eventAfterClass: W,
	listItemEventClass: W,
	listItemEventInnerClass: W,
	listItemEventTimeClass: W,
	listItemEventTitleClass: W,
	listItemEventBeforeClass: W,
	listItemEventAfterClass: W,
	blockEventClass: W,
	blockEventInnerClass: W,
	blockEventTimeClass: W,
	blockEventTitleClass: W,
	blockEventBeforeClass: W,
	blockEventAfterClass: W,
	rowEventClass: W,
	rowEventInnerClass: W,
	rowEventTimeClass: W,
	rowEventTitleClass: W,
	rowEventTitleSticky: Boolean,
	rowEventBeforeClass: W,
	rowEventBeforeContent: G,
	rowEventAfterClass: W,
	rowEventAfterContent: G,
	columnEventClass: W,
	columnEventInnerClass: W,
	columnEventTimeClass: W,
	columnEventTitleClass: W,
	columnEventTitleSticky: Boolean,
	columnEventBeforeClass: W,
	columnEventAfterClass: W,
	backgroundEventClass: W,
	backgroundEventDidMount: G,
	backgroundEventWillUnmount: G,
	backgroundEventContent: G,
	backgroundEventInnerClass: W,
	backgroundEventTitleClass: W,
	backgroundEventColor: String,
	selectConstraint: G,
	selectOverlap: G,
	selectAllow: G,
	droppable: Boolean,
	unselectCancel: String,
	slotHeaderFormat: G,
	slotLaneClass: W,
	slotLaneDidMount: G,
	slotLaneWillUnmount: G,
	slotHeaderClass: W,
	slotHeaderInnerClass: W,
	slotHeaderContent: G,
	slotHeaderDidMount: G,
	slotHeaderWillUnmount: G,
	slotHeaderAlign: G,
	slotHeaderSticky: G,
	slotHeaderRowClass: U,
	slotHeaderDividerClass: W,
	dayMaxEvents: G,
	dayMaxEventRows: G,
	dayMinWidth: Number,
	slotHeaderInterval: z,
	dayHeaderClass: W,
	dayHeaderInnerClass: W,
	dayHeaderContent: G,
	dayHeaderDidMount: G,
	dayHeaderWillUnmount: G,
	dayHeaderAlign: G,
	_dayHeaderSticky: G,
	dayHeaderRowClass: U,
	dayHeaderDividerClass: W,
	dayRowClass: U,
	dayCellDidMount: G,
	dayCellWillUnmount: G,
	dayCellClass: W,
	dayCellInnerClass: W,
	dayCellTopContent: G,
	dayCellTopClass: W,
	dayCellTopInnerClass: W,
	dayCellBottomClass: W,
	allDaySlot: Boolean,
	allDayText: String,
	allDayHeaderClass: W,
	allDayHeaderInnerClass: W,
	allDayHeaderContent: G,
	allDayHeaderDidMount: G,
	allDayHeaderWillUnmount: G,
	timedText: String,
	slotMinWidth: Number,
	slotMinHeight: Number,
	navLinks: Boolean,
	eventTimeFormat: H,
	rerenderDelay: Number,
	moreLinkText: G,
	moreLinkHint: G,
	selectMinDistance: Number,
	selectable: Boolean,
	selectLongPressDelay: Number,
	eventLongPressDelay: Number,
	selectMirror: Boolean,
	eventMaxStack: Number,
	eventMinHeight: Number,
	eventMinWidth: Number,
	eventShortHeight: Number,
	slotEventOverlap: Boolean,
	firstDay: Number,
	dayCount: Number,
	dateAlignment: String,
	dateIncrement: z,
	hiddenDays: G,
	fixedWeekCount: Boolean,
	validRange: G,
	visibleRange: G,
	titleFormat: G,
	eventInteractive: Boolean,
	noEventsText: String,
	viewHint: G,
	viewChangeHint: String,
	navLinkHint: G,
	closeHint: String,
	eventsHint: String,
	headingLevel: Number,
	moreLinkClick: G,
	moreLinkContent: G,
	moreLinkDidMount: G,
	moreLinkWillUnmount: G,
	moreLinkClass: W,
	moreLinkInnerClass: W,
	rowMoreLinkClass: W,
	rowMoreLinkInnerClass: W,
	columnMoreLinkClass: W,
	columnMoreLinkInnerClass: W,
	navLinkClass: U,
	monthStartFormat: H,
	dayCellFormat: H,
	handleCustomRendering: G,
	customRenderingMetaMap: G,
	popoverClass: U,
	popoverCloseClass: U,
	popoverCloseContent: G,
	dayNarrowWidth: Number,
	borderless: Boolean,
	borderlessX: Boolean,
	borderlessTop: Boolean,
	borderlessBottom: Boolean,
	fillerClass: W,
	headerToolbarClass: W,
	footerToolbarClass: W,
	toolbarClass: W,
	toolbarSectionClass: W,
	toolbarTitleClass: U,
	tableClass: W,
	tableHeaderClass: W,
	tableBodyClass: W,
	nonBusinessHoursClass: U,
	highlightClass: U,
	dayHeaders: Boolean,
	dayHeaderFormat: H,
	allDayDividerClass: U,
	listDaysClass: U,
	listDayClass: W,
	listDayFormat: jm,
	listDayAltFormat: jm,
	listDayHeaderDidMount: G,
	listDayHeaderWillUnmount: G,
	listDayHeaderClass: W,
	listDayHeaderInnerClass: W,
	listDayHeaderContent: G,
	listDayBodyClass: W,
	noEventsClass: W,
	noEventsInnerClass: W,
	noEventsContent: G,
	noEventsDidMount: G,
	noEventsWillUnmount: G,
	multiMonthMaxColumns: Number,
	singleMonthMinWidth: Number,
	singleMonthTitleFormat: H,
	singleMonthDidMount: G,
	singleMonthWillUnmount: G,
	singleMonthClass: W,
	singleMonthHeaderClass: W,
	singleMonthHeaderInnerClass: W
}, Tm = {
	buttonDisplay: "auto",
	eventDisplay: "auto",
	defaultTimedEventDuration: "01:00:00",
	defaultAllDayEventDuration: { day: 1 },
	forceEventDuration: !1,
	nextDayThreshold: "00:00:00",
	initialView: "",
	aspectRatio: 1.35,
	weekends: !0,
	weekNumbers: !1,
	weekNumberCalculation: "local",
	editable: !1,
	nowIndicator: !1,
	scrollTime: "06:00:00",
	scrollTimeReset: !0,
	slotMinTime: "00:00:00",
	slotMaxTime: "24:00:00",
	showNonCurrentDates: !0,
	lazyFetching: !0,
	startParam: "start",
	endParam: "end",
	timeZoneParam: "timeZone",
	timeZone: "local",
	locales: [],
	locale: "",
	dragRevertDuration: 500,
	dragScroll: !0,
	allDayMaintainDuration: !1,
	unselectAuto: !0,
	dropAccept: "*",
	eventOrder: "start,-duration,allDay,title",
	eventSlicing: !0,
	eventPrintLayout: "auto",
	popoverFormat: {
		month: "long",
		day: "numeric",
		year: "numeric"
	},
	longPressDelay: 1e3,
	eventDragMinDistance: 5,
	expandRows: !1,
	navLinks: !1,
	selectable: !1,
	eventMinHeight: 15,
	eventMinWidth: 30,
	eventShortHeight: 30,
	monthStartFormat: {
		month: "long",
		day: "numeric"
	},
	dayCellFormat: {
		day: "numeric",
		omitTrailing: !0
	},
	headingLevel: 2,
	outerBorder: !0,
	dayNarrowWidth: 80,
	eventOverlap: !0,
	slotHeaderAlign: "start",
	slotHeaderSticky: !0,
	dayHeaderAlign: "start",
	_dayHeaderSticky: !0,
	rowEventTitleSticky: !0,
	columnEventTitleSticky: !0,
	nowIndicatorSnap: "auto",
	dayHeaders: !0
}, Em = {
	datesSet: G,
	eventsSet: G,
	eventAdd: G,
	eventChange: G,
	eventRemove: G,
	eventClick: G,
	eventMouseEnter: G,
	eventMouseLeave: G,
	select: G,
	unselect: G,
	loading: G,
	_unmount: G,
	_beforeprint: G,
	_afterprint: G,
	_noDateSelect: G,
	_noEventDrop: G,
	_noEventResize: G,
	_timeScrollRequest: G,
	dateClick: G,
	eventDragStart: G,
	eventDragStop: G,
	eventDrop: G,
	eventResizeStart: G,
	eventResizeStop: G,
	eventResize: G,
	drop: G,
	eventReceive: G,
	eventLeave: G
}, Dm = {
	class: W,
	className: W,
	viewClass: W,
	viewDidMount: G,
	viewWillUnmount: G,
	views: G,
	plugins: G,
	initialEvents: G,
	events: G,
	eventSources: G
}, Om = {
	type: String,
	component: G,
	class: W,
	className: W,
	content: G,
	didMount: G,
	willUnmount: G,
	buttonTextKey: String,
	dateProfileGeneratorClass: G,
	usesMinMaxTime: Boolean,
	disallowAmbigTitle: Boolean
}, km = {
	dateIncrement: hm,
	headerToolbar: hm,
	footerToolbar: hm,
	buttons: pm,
	plugins: om,
	events: om,
	eventSources: om,
	resources: om,
	validRange: _m,
	visibleRange: _m,
	hiddenDays: om
};
function Am(e, t) {
	let n = {}, r = {};
	for (let r in t) r in e && (n[r] = t[r](e[r], r));
	for (let n in e) n in t || (r[n] = e[n]);
	return {
		refined: n,
		extra: r
	};
}
function G(e) {
	return e;
}
function jm(e) {
	return e === !1 ? null : H(e) ?? void 0;
}
function Mm(e, t, n, r) {
	let i = {
		start: e,
		end: t
	};
	return n != null && (i.instantStartMs = n), r != null && (i.instantEndMs = r), i;
}
function Nm(e, t, n) {
	return t ?? n.toDate(e).valueOf();
}
function Pm(e, t, n, r) {
	let i = t == null ? e : n.timestampToMarker(t);
	if (!r && t != null) {
		let e = Math.round((i.valueOf() - t) / 6e4);
		return {
			marker: i,
			date: new Date(t),
			dateStr: sp(i, e)
		};
	}
	let a = n.toDate(e), o = r ? e : n.timestampToMarker(a.valueOf());
	return {
		marker: i,
		date: a,
		dateStr: n.formatIso(o, { omitTime: r })
	};
}
function Fm(e, t) {
	return Nm(e.start, e.instantStartMs, t);
}
function Im(e, t) {
	return Nm(e.end, e.instantEndMs, t);
}
function Lm(e, t) {
	return e.instantEndMs == null ? e.end : t.timestampToMarker(e.instantEndMs);
}
function Rm(e) {
	return e.instantStartMs != null || e.instantEndMs != null;
}
function zm(e, t, n) {
	return Rm(e) || Rm(t) ? Fm(e, n) < Im(t, n) && Im(e, n) > Fm(t, n) : zf(e, t);
}
function Bm(e, t, n) {
	if (Rm(e) || Rm(t)) {
		let r = e.start == null ? -Infinity : Nm(e.start, e.instantStartMs, n), i = e.end == null ? Infinity : Nm(e.end, e.instantEndMs, n);
		return r <= Fm(t, n) && i >= Im(t, n);
	}
	return Bf(e, t);
}
function Vm(e, t, n) {
	if (e.instantMs != null && !t.years && !t.months && !t.days) {
		let r = ip(t), i = e.instantMs + r, a = n.timestampToMarker(i);
		return a > e.marker ? {
			marker: a,
			instantMs: i
		} : {
			marker: uf(e.marker, r),
			instantMs: i
		};
	}
	return { marker: n.add(e.marker, t) };
}
function Hm(e, t, n) {
	if (e.instantMs == null && t.instantMs == null) return t.marker > e.marker ? Mm(e.marker, t.marker) : null;
	let r = Nm(e.marker, e.instantMs, n), i = Nm(t.marker, t.instantMs, n);
	return i <= r ? null : Mm(e.marker, t.marker > e.marker ? t.marker : uf(e.marker, i - r), e.instantMs, t.instantMs);
}
function Um(e, t) {
	return {
		instanceId: Up(),
		defId: e,
		range: t
	};
}
function Wm(e) {
	let t = Math.floor(ff(e.start, e.end)) || 1, n = R(e.start);
	return {
		start: n,
		end: L(n, t)
	};
}
function Gm(e, t = z(0)) {
	let n = null, r = null;
	if (e.end) {
		r = R(e.end);
		let n = e.end.valueOf() - r.valueOf();
		n && n >= ip(t) && (r = L(r, 1));
	}
	return e.start && (n = R(e.start), r && r <= n && (r = L(n, 1))), {
		start: n,
		end: r
	};
}
function Km(e, t, n, r) {
	return r === "year" ? z(n.diffWholeYears(e, t), "year") : r === "month" ? z(n.diffWholeMonths(e, t), "month") : gf(e, t);
}
function qm(e, t, n, r) {
	for (let i = 0; i < r.length; i += 1) {
		let a = r[i].parse(e, n);
		if (a) {
			let { allDay: n } = e;
			return n ?? (n = t, n ?? (n = a.allDayGuess, n ??= !1)), {
				allDay: n,
				duration: a.duration,
				typeData: a.typeData,
				typeId: i
			};
		}
	}
	return null;
}
function Jm(e, t, n) {
	let { dateEnv: r, pluginHooks: i, options: a } = n, { defs: o, instances: s } = e;
	s = lm(s, (e) => !o[e.defId].recurringDef);
	for (let e in o) {
		let n = o[e];
		if (n.recurringDef) {
			let { duration: o } = n.recurringDef;
			o ||= n.allDay ? a.defaultAllDayEventDuration : a.defaultTimedEventDuration;
			let c = Ym(n, o, t, r, i.recurringTypes);
			for (let t of c) {
				let n = Um(e, {
					start: t,
					end: r.add(t, o)
				});
				s[n.instanceId] = n;
			}
		}
	}
	return {
		defs: o,
		instances: s
	};
}
function Ym(e, t, n, r, i) {
	let a = i[e.recurringDef.typeId].expand(e.recurringDef.typeData, {
		start: r.subtract(n.start, t),
		end: n.end
	}, r);
	return e.allDay && (a = a.map(R)), a;
}
function Xm(e, t, n, r, i, a) {
	let o = eh(), s = gh(n);
	for (let c of e) {
		let e = mh(c, t, n, r, s, i, a);
		e && Zm(e, o);
	}
	return o;
}
function Zm(e, t = eh()) {
	return t.defs[e.def.defId] = e.def, e.instance && (t.instances[e.instance.instanceId] = e.instance), t;
}
function Qm(e, t) {
	let n = e.instances[t];
	if (n) {
		let t = e.defs[n.defId], r = nh(e, (e) => $m(t, e));
		return r.defs[t.defId] = t, r.instances[n.instanceId] = n, r;
	}
	return eh();
}
function $m(e, t) {
	return !!(e.groupId && e.groupId === t.groupId);
}
function eh() {
	return {
		defs: {},
		instances: {}
	};
}
function th(e, t) {
	return {
		defs: {
			...e.defs,
			...t.defs
		},
		instances: {
			...e.instances,
			...t.instances
		}
	};
}
function nh(e, t) {
	let n = lm(e.defs, t);
	return {
		defs: n,
		instances: lm(e.instances, (e) => n[e.defId])
	};
}
function rh(e, t) {
	let { defs: n, instances: r } = e, i = {}, a = {};
	for (let e in n) t.defs[e] || (i[e] = n[e]);
	for (let e in r) !t.instances[e] && i[r[e].defId] && (a[e] = r[e]);
	return {
		defs: i,
		instances: a
	};
}
function ih(e, t) {
	return Array.isArray(e) ? Xm(e, null, t, !0) : typeof e == "object" && e ? Xm([e], null, t, !0) : e == null ? null : String(e);
}
var ah = {
	display: String,
	editable: Boolean,
	startEditable: Boolean,
	durationEditable: Boolean,
	constraint: G,
	overlap: G,
	allow: G,
	class: U,
	className: U,
	color: String,
	contrastColor: String
}, oh = {
	display: null,
	startEditable: null,
	durationEditable: null,
	constraints: [],
	overlap: null,
	allows: [],
	color: "",
	contrastColor: "",
	className: ""
};
function sh(e, t) {
	let n = ih(e.constraint, t);
	return {
		display: e.display || null,
		startEditable: e.startEditable == null ? e.editable : e.startEditable,
		durationEditable: e.durationEditable == null ? e.editable : e.durationEditable,
		constraints: n == null ? [] : [n],
		overlap: e.overlap == null ? null : e.overlap,
		allows: e.allow == null ? [] : [e.allow],
		color: e.color || "",
		contrastColor: e.contrastColor || "",
		className: (e.class ?? e.className) || ""
	};
}
function ch(e) {
	return e.reduce(lh, oh);
}
function lh(e, t) {
	return {
		display: t.display == null ? e.display : t.display,
		startEditable: t.startEditable == null ? e.startEditable : t.startEditable,
		durationEditable: t.durationEditable == null ? e.durationEditable : t.durationEditable,
		constraints: e.constraints.concat(t.constraints),
		overlap: typeof t.overlap == "boolean" ? t.overlap : e.overlap,
		allows: e.allows.concat(t.allows),
		color: t.color || e.color,
		contrastColor: t.contrastColor || e.contrastColor,
		className: V(e.className, t.className)
	};
}
var uh = {
	base: oh,
	bySource: {}
}, dh = {
	id: String,
	groupId: String,
	title: String,
	url: String,
	interactive: Boolean
}, fh = {
	start: G,
	end: G,
	date: G,
	allDay: Boolean
}, ph = {
	...dh,
	...fh,
	extendedProps: G
};
function mh(e, t, n, r, i = gh(n), a, o) {
	let { refined: s, extra: c } = hh(e, n, i), l = yh(t, n), u = qm(s, l, n.dateEnv, n.pluginHooks.recurringTypes);
	if (u) {
		let e = _h(s, c, t ? t.sourceId : "", u.allDay, !!u.duration, n, a);
		return e.recurringDef = {
			typeId: u.typeId,
			typeData: u.typeData,
			duration: u.duration
		}, {
			def: e,
			instance: null
		};
	}
	let d = vh(s, l, n, r);
	if (d) {
		let e = _h(s, c, t ? t.sourceId : "", d.allDay, d.hasEnd, n, a), r = Um(e.defId, d.range);
		return o && e.publicId && o[e.publicId] && (r.instanceId = o[e.publicId]), {
			def: e,
			instance: r
		};
	}
	return null;
}
function hh(e, t, n = gh(t)) {
	return Am(e, n);
}
function gh(e) {
	return {
		...ah,
		...ph,
		...e.pluginHooks.eventRefiners
	};
}
function _h(e, t, n, r, i, a, o) {
	let s = {
		title: e.title || "",
		groupId: e.groupId || "",
		publicId: e.id || "",
		url: e.url || "",
		recurringDef: null,
		defId: (o && e.id ? o[e.id] : "") || Up(),
		sourceId: n,
		allDay: r,
		hasEnd: i,
		interactive: e.interactive,
		ui: sh(e, a),
		extendedProps: {
			...e.extendedProps || {},
			...t
		}
	};
	for (let t of a.pluginHooks.eventDefMemberAdders) Object.assign(s, t(e));
	return Object.freeze(s.ui.className), Object.freeze(s.extendedProps), s;
}
function vh(e, t, n, r) {
	let { allDay: i } = e, a, o = null, s = !1, c, l = null, u = e.start == null ? e.date : e.start;
	if (a = n.dateEnv.createMarkerMeta(u), a) o = a.marker;
	else if (!r) return null;
	e.end != null && (c = n.dateEnv.createMarkerMeta(e.end)), i ??= t ?? ((!a || a.isTimeUnspecified) && (!c || c.isTimeUnspecified)), i && o && (o = R(o));
	let d = !i && a ? a.instantMs : void 0, f = null;
	if (c && (l = i ? R(c.marker) : c.marker, o ? i ? l > o && (f = Mm(o, l)) : f = Hm({
		marker: o,
		instantMs: d
	}, {
		marker: l,
		instantMs: c.instantMs
	}, n.dateEnv) : f = Mm(o, l, void 0, i ? void 0 : c.instantMs)), f) s = !0;
	else if (r) f = Mm(o, null, d);
	else {
		s = n.options.forceEventDuration || !1;
		let e = Vm({
			marker: o,
			instantMs: d
		}, i ? n.options.defaultAllDayEventDuration : n.options.defaultTimedEventDuration, n.dateEnv);
		f = Mm(o, e.marker, d, e.instantMs);
	}
	return {
		allDay: i,
		hasEnd: s,
		range: f
	};
}
function yh(e, t) {
	let n = null;
	return e && (n = e.defaultAllDay), n ??= t.options.defaultAllDay, n;
}
var bh = {
	start: G,
	end: G,
	allDay: Boolean
};
function xh(e, t, n) {
	let r = Sh(e, t);
	if (!r) return null;
	let { range: i } = r;
	if (!i.start) return null;
	if (!i.end) {
		if (n == null) return null;
		let e = Vm({
			marker: i.start,
			instantMs: r.instantStartMs
		}, n, t);
		i.end = e.marker, e.instantMs != null && (r.instantEndMs = e.instantMs);
	}
	return r;
}
function Sh(e, t) {
	let { refined: n, extra: r } = Am(e, bh), i = n.start ? t.createMarkerMeta(n.start) : null, a = n.end ? t.createMarkerMeta(n.end) : null, { allDay: o } = n;
	o ??= i && i.isTimeUnspecified && (!a || a.isTimeUnspecified);
	let s = {
		start: i ? i.marker : null,
		end: a ? a.marker : null
	};
	if (!o && i && a && (i.instantMs != null || a.instantMs != null)) {
		let e = Hm({
			marker: i.marker,
			instantMs: i.instantMs
		}, {
			marker: a.marker,
			instantMs: a.instantMs
		}, t);
		if (!e && i.instantMs != null && a.instantMs != null) return null;
		e && (s = {
			start: e.start,
			end: e.end
		});
	}
	let c = {
		range: s,
		allDay: o,
		...r
	};
	return o ? (delete c.instantStartMs, delete c.instantEndMs) : (i?.instantMs != null && (c.instantStartMs = i.instantMs), a?.instantMs != null && (c.instantEndMs = a.instantMs)), c;
}
function Ch(e, t) {
	return Rf(e.range, t.range) && e.allDay === t.allDay && wh(e, t);
}
function wh(e, t) {
	for (let n in t) if (n !== "range" && n !== "allDay" && e[n] !== t[n]) return !1;
	for (let n in e) if (!(n in t)) return !1;
	return !0;
}
function Th(e, t) {
	return {
		...Dh(e.range, t, e.allDay, e),
		allDay: e.allDay
	};
}
function Eh(e, t, n) {
	return {
		...Dh(e, t, n),
		timeZone: t.timeZone
	};
}
function Dh(e, t, n, r) {
	let i = r?.instantStartMs ?? e.instantStartMs, a = r?.instantEndMs ?? e.instantEndMs, o = Pm(e.start, i, t, n), s = Pm(e.end, a, t, n);
	return {
		start: o.date,
		end: s.date,
		startStr: o.dateStr,
		endStr: s.dateStr
	};
}
function Oh(e, t) {
	return Nm(e.range.start, e.instantStartMs, t);
}
function kh(e, t) {
	return Nm(e.range.end, e.instantEndMs, t);
}
function Ah(e, t, n) {
	let r = hh({ editable: !1 }, n), i = _h(r.refined, r.extra, "", e.allDay, !0, n);
	return {
		def: i,
		ui: Zh(i, t),
		instance: Um(i.defId, e.range),
		range: e.range,
		isStart: !0,
		isEnd: !0
	};
}
function jh(e, t, n) {
	n.emitter.trigger("select", {
		...Nh(e, n),
		jsEvent: t ? t.origEvent : null,
		view: n.viewApi || n.calendarApi.view
	});
}
function Mh(e, t) {
	t.emitter.trigger("unselect", {
		jsEvent: e ? e.origEvent : null,
		view: t.viewApi || t.calendarApi.view
	});
}
function Nh(e, t) {
	let n = {};
	for (let r of t.pluginHooks.dateSpanTransforms) Object.assign(n, r(e, t));
	return Object.assign(n, Th(e, t.dateEnv)), n;
}
function Ph(e, t, n) {
	let { dateEnv: r, options: i } = n, a = t;
	return e ? (a = R(a), a = r.add(a, i.defaultAllDayEventDuration)) : a = r.add(a, i.defaultTimedEventDuration), a;
}
function Fh(e, t, n) {
	return e ? { marker: Ph(!0, t.marker, n) } : Vm(t, n.options.defaultTimedEventDuration, n.dateEnv);
}
function Ih(e, t, n, r) {
	let i = Xh(e.defs, t), a = eh();
	for (let t in e.defs) {
		let o = e.defs[t];
		a.defs[t] = Lh(o, i[t], n, r);
	}
	for (let t in e.instances) {
		let o = e.instances[t], s = a.defs[o.defId];
		a.instances[t] = Rh(o, s, i[o.defId], n, r);
	}
	return a;
}
function Lh(e, t, n, r) {
	let i = n.standardProps || {};
	i.hasEnd == null && t.durationEditable && (n.startDelta || n.endDelta) && (i.hasEnd = !0);
	let a = {
		...e,
		...i,
		ui: {
			...e.ui,
			...i.ui
		}
	};
	n.extendedProps && (a.extendedProps = {
		...a.extendedProps,
		...n.extendedProps
	});
	for (let e of r.pluginHooks.eventDefMutationAppliers) e(a, n, r);
	return !a.hasEnd && r.options.forceEventDuration && (a.hasEnd = !0), a;
}
function Rh(e, t, n, r, i) {
	let a = r.standardProps && r.standardProps.allDay === !0, o = r.standardProps && r.standardProps.hasEnd === !1, s = { ...e };
	if (a && (s.range = Wm(s.range)), r.datesDelta && n.startEditable && (s.range = Bh(zh(s.range.start, s.range.instantStartMs, r.datesDelta, r.instantDatesDeltaMs, i), zh(s.range.end, s.range.instantEndMs, r.datesDelta, r.instantDatesDeltaMs, i))), r.startDelta && n.durationEditable && (s.range = Bh(zh(s.range.start, s.range.instantStartMs, r.startDelta, r.instantStartDeltaMs, i), {
		marker: s.range.end,
		instantMs: s.range.instantEndMs
	})), r.endDelta && n.durationEditable && (s.range = Bh({
		marker: s.range.start,
		instantMs: s.range.instantStartMs
	}, zh(s.range.end, s.range.instantEndMs, r.endDelta, r.instantEndDeltaMs, i))), o) {
		let e = {
			marker: s.range.start,
			instantMs: s.range.instantStartMs
		};
		s.range = Bh(e, Fh(t.allDay, e, i));
	}
	if (t.allDay && (s.range = {
		start: R(s.range.start),
		end: R(s.range.end)
	}), t.allDay) {
		if (s.range.end <= s.range.start) {
			let e = { marker: s.range.start };
			s.range = Bh(e, Fh(!0, e, i));
		}
	} else {
		let e = {
			marker: s.range.start,
			instantMs: s.range.instantStartMs
		};
		s.range = Hm(e, {
			marker: s.range.end,
			instantMs: s.range.instantEndMs
		}, i.dateEnv) ?? Bh(e, Fh(!1, e, i));
	}
	return s;
}
function zh(e, t, n, r, i) {
	if (r != null) {
		let n = Nm(e, t, i.dateEnv) + r;
		return {
			marker: i.dateEnv.timestampToMarker(n),
			instantMs: n
		};
	}
	return { marker: i.dateEnv.add(t == null ? e : i.dateEnv.timestampToMarker(t), n) };
}
function Bh(e, t) {
	return Mm(e.marker, t.marker, e.instantMs, t.instantMs);
}
var Vh = class {
	constructor(e, t) {
		this.context = e, this.internalEventSource = t;
	}
	remove() {
		this.context.dispatch({
			type: "REMOVE_EVENT_SOURCE",
			sourceId: this.internalEventSource.sourceId
		});
	}
	refetch() {
		this.context.dispatch({
			type: "FETCH_EVENT_SOURCES",
			sourceIds: [this.internalEventSource.sourceId],
			isRefetch: !0
		});
	}
	get id() {
		return this.internalEventSource.publicId;
	}
	get url() {
		return this.internalEventSource.meta.url;
	}
	get format() {
		return this.internalEventSource.meta.format;
	}
}, K = class e {
	constructor(e, t, n) {
		this._context = e, this._def = t, this._instance = n || null;
	}
	setProp(e, t) {
		if (e in fh) jp(`Cannot set date-related event property \`${e}\`. Use a method instead.`);
		else if (e === "id") t = dh[e](t), this.mutate({ standardProps: { publicId: t } });
		else if (e in dh) t = dh[e](t), this.mutate({ standardProps: { [e]: t } });
		else if (e in ah) {
			let n = ah[e](t);
			n = e === "editable" ? {
				startEditable: t,
				durationEditable: t
			} : { [e]: t }, this.mutate({ standardProps: { ui: n } });
		} else jp(`Cannot set event property \`${e}\`. Use setExtendedProp instead.`);
	}
	setExtendedProp(e, t) {
		this.mutate({ extendedProps: { [e]: t } });
	}
	setStart(e, t = {}) {
		let { dateEnv: n } = this._context, r = n.createMarkerMeta(e);
		if (r && this._instance) {
			let e = this._instance.range, i = Km(e.start, r.marker, n, t.granularity), a = Hh(r, Fm(e, n), t.granularity);
			t.maintainDuration ? this.mutate({
				datesDelta: i,
				instantDatesDeltaMs: a
			}) : this.mutate({
				startDelta: i,
				instantStartDeltaMs: a
			});
		}
	}
	setEnd(e, t = {}) {
		let { dateEnv: n } = this._context, r = null;
		if (!(e != null && (r = n.createMarkerMeta(e), !r)) && this._instance) {
			if (r) {
				let e = this._instance.range, i = Km(Lm(e, n), r.marker, n, t.granularity), a = Hh(r, Im(e, n), t.granularity);
				this.mutate({
					endDelta: i,
					instantEndDeltaMs: a
				});
			} else this.mutate({ standardProps: { hasEnd: !1 } });
		}
	}
	setDates(e, t, n = {}) {
		let { dateEnv: r } = this._context, i = { allDay: n.allDay }, a = r.createMarkerMeta(e), o = null;
		if (a && !(t != null && (o = r.createMarkerMeta(t), !o)) && this._instance) {
			let e = this._instance.range, t = n.allDay === !0, s = t ? void 0 : Hh(a, Fm(e, r), n.granularity), c = t || !o ? void 0 : Hh(o, Im(e, r), n.granularity);
			n.allDay === !0 && (e = Wm(e));
			let l = Km(e.start, a.marker, r, n.granularity);
			if (o) {
				let t = Km(Lm(e, r), o.marker, r, n.granularity);
				Zf(l, t) && s === c ? this.mutate({
					datesDelta: l,
					instantDatesDeltaMs: s,
					standardProps: i
				}) : this.mutate({
					startDelta: l,
					endDelta: t,
					instantStartDeltaMs: s,
					instantEndDeltaMs: c,
					standardProps: i
				});
			} else i.hasEnd = !1, this.mutate({
				datesDelta: l,
				instantDatesDeltaMs: s,
				standardProps: i
			});
		}
	}
	moveStart(e) {
		let t = z(e);
		t && this.mutate({ startDelta: t });
	}
	moveEnd(e) {
		let t = z(e);
		t && this.mutate({ endDelta: t });
	}
	moveDates(e) {
		let t = z(e);
		t && this.mutate({ datesDelta: t });
	}
	setAllDay(e, t = {}) {
		let n = { allDay: e }, { maintainDuration: r } = t;
		r ??= this._context.options.allDayMaintainDuration, this._def.allDay !== e && (n.hasEnd = r), this.mutate({ standardProps: n });
	}
	formatRange(e) {
		let { dateEnv: t } = this._context, n = this._instance, r = H(e), i = Pm(n.range.start, n.range.instantStartMs, t);
		if (this._def.hasEnd) {
			let e = Pm(n.range.end, n.range.instantEndMs, t);
			return fp(t.formatRangeToParts(i.marker, e.marker, r, {
				startInstantMs: i.date.valueOf(),
				endInstantMs: e.date.valueOf()
			}));
		}
		return fp(t.formatToParts(i.marker, r, { instantMs: i.date.valueOf() }));
	}
	mutate(t) {
		let n = this._instance;
		if (n) {
			let r = this._def, i = this._context, { eventStore: a } = i.getCurrentData(), o = Qm(a, n.instanceId);
			o = Ih(o, {
				base: {
					display: "",
					startEditable: !0,
					durationEditable: !0,
					constraints: [],
					overlap: null,
					allows: [],
					color: "",
					contrastColor: "",
					className: ""
				},
				bySource: {}
			}, t, i);
			let s = new e(i, r, n);
			this._def = o.defs[r.defId], this._instance = o.instances[n.instanceId], i.dispatch({
				type: "MERGE_EVENTS",
				eventStore: o
			}), i.emitter.trigger("eventChange", {
				oldEvent: s,
				event: this,
				relatedEvents: Wh(o, i, n),
				revert() {
					i.dispatch({
						type: "RESET_EVENTS",
						eventStore: a
					});
				}
			});
		}
	}
	remove() {
		let e = this._context, t = Uh(this);
		e.dispatch({
			type: "REMOVE_EVENTS",
			eventStore: t
		}), e.emitter.trigger("eventRemove", {
			event: this,
			relatedEvents: [],
			revert() {
				e.dispatch({
					type: "MERGE_EVENTS",
					eventStore: t
				});
			}
		});
	}
	get source() {
		let { sourceId: e } = this._def;
		return e ? new Vh(this._context, this._context.getCurrentData().eventSources[e]) : null;
	}
	get start() {
		let e = this._instance;
		return e ? Pm(e.range.start, e.range.instantStartMs, this._context.dateEnv, this._def.allDay).date : null;
	}
	get end() {
		let e = this._instance;
		return e && this._def.hasEnd ? Pm(e.range.end, e.range.instantEndMs, this._context.dateEnv, this._def.allDay).date : null;
	}
	get startStr() {
		let e = this._instance;
		return e ? Pm(e.range.start, e.range.instantStartMs, this._context.dateEnv, this._def.allDay).dateStr : "";
	}
	get endStr() {
		let e = this._instance;
		return e && this._def.hasEnd ? Pm(e.range.end, e.range.instantEndMs, this._context.dateEnv, this._def.allDay).dateStr : "";
	}
	get id() {
		return this._def.publicId;
	}
	get groupId() {
		return this._def.groupId;
	}
	get allDay() {
		return this._def.allDay;
	}
	get title() {
		return this._def.title;
	}
	get url() {
		return this._def.url;
	}
	get display() {
		return this._def.ui.display || "auto";
	}
	get startEditable() {
		return this._def.ui.startEditable;
	}
	get durationEditable() {
		return this._def.ui.durationEditable;
	}
	get constraint() {
		return this._def.ui.constraints[0] || null;
	}
	get overlap() {
		return this._def.ui.overlap;
	}
	get allow() {
		return this._def.ui.allows[0] || null;
	}
	get color() {
		return this._def.ui.color;
	}
	get contrastColor() {
		return this._def.ui.contrastColor;
	}
	get className() {
		return this._def.ui.className;
	}
	get extendedProps() {
		return this._def.extendedProps;
	}
	toPlainObject(e = {}) {
		let t = this._def, { ui: n } = t, { startStr: r, endStr: i } = this, a = { allDay: t.allDay };
		return t.title && (a.title = t.title), r && (a.start = r), i && (a.end = i), t.publicId && (a.id = t.publicId), t.groupId && (a.groupId = t.groupId), t.url && (a.url = t.url), n.display && n.display !== "auto" && (a.display = n.display), n.color && (a.color = n.color), n.contrastColor && (a.contrastColor = n.contrastColor), n.className && (a.className = n.className), Object.keys(t.extendedProps).length && (e.collapseExtendedProps ? Object.assign(a, t.extendedProps) : a.extendedProps = t.extendedProps), a;
	}
	toJSON() {
		return this.toPlainObject();
	}
};
function Hh(e, t, n) {
	return e.instantMs != null && !n ? e.instantMs - t : void 0;
}
function Uh(e) {
	let t = e._def, n = e._instance;
	return {
		defs: { [t.defId]: t },
		instances: n ? { [n.instanceId]: n } : {}
	};
}
function Wh(e, t, n) {
	let { defs: r, instances: i } = e, a = [], o = n ? n.instanceId : "";
	for (let e in i) {
		let n = i[e], s = r[n.defId];
		n.instanceId !== o && a.push(new K(t, s, n));
	}
	return a;
}
function Gh(e, t, n, r) {
	let i = {}, a = {}, o = {}, s = [], c = [], l = Xh(e.defs, t);
	for (let t in e.defs) {
		let n = e.defs[t];
		l[n.defId].display === "inverse-background" && (n.groupId ? (i[n.groupId] = [], o[n.groupId] || (o[n.groupId] = n)) : a[t] = []);
	}
	for (let t in e.instances) {
		let o = e.instances[t], u = e.defs[o.defId], d = l[u.defId], f = o.range, p = !u.allDay && r ? Gm(f, r) : f, m = Lf(p, n);
		m && (d.display === "inverse-background" ? u.groupId ? i[u.groupId].push(m) : a[o.defId].push(m) : d.display !== "none" && (d.display === "background" ? s : c).push({
			def: u,
			ui: d,
			instance: o,
			range: Kh(f, p, m),
			isStart: p.start && p.start.valueOf() === m.start.valueOf(),
			isEnd: p.end && p.end.valueOf() === m.end.valueOf()
		}));
	}
	for (let e in i) {
		let t = i[e], r = Ff(t, n);
		for (let t of r) {
			let n = o[e], r = l[n.defId];
			s.push({
				def: n,
				ui: r,
				instance: null,
				range: t,
				isStart: !1,
				isEnd: !1
			});
		}
	}
	for (let t in a) {
		let r = a[t], i = Ff(r, n);
		for (let n of i) s.push({
			def: e.defs[t],
			ui: l[t],
			instance: null,
			range: n,
			isStart: !1,
			isEnd: !1
		});
	}
	return {
		bg: s,
		fg: c
	};
}
function Kh(e, t, n) {
	return t === e ? Mm(n.start, n.end, n.start.valueOf() === e.start.valueOf() ? e.instantStartMs : void 0, n.end.valueOf() === e.end.valueOf() ? e.instantEndMs : void 0) : n;
}
function qh(e) {
	return e.ui.display === "background" || e.ui.display === "inverse-background";
}
function Jh(e, t) {
	e.fcEventRange = t;
}
function Yh(e) {
	return e.fcEventRange || e.parentNode.fcEventRange || null;
}
function Xh(e, t) {
	return um(e, (e) => Zh(e, t));
}
function Zh(e, t) {
	let n = [t.base], r = t.bySource[e.sourceId], i = t.getExtraUi?.(e);
	return t.sectionUi && n.push(t.sectionUi), r && n.push(r), i && n.push(i), n.push(e.ui), ch(n);
}
function Qh(e, t) {
	let n = e.map($h);
	return n.sort((e, n) => Zp(e, n, t)), n.map((e) => e._seg);
}
function $h(e) {
	let { eventRange: t } = e, n = t.def, r = t.instance ? t.instance.range : t.range, i = r.start ? r.start.valueOf() : 0, a = r.end ? r.end.valueOf() : 0;
	return {
		...n.extendedProps,
		...n,
		id: n.publicId,
		start: i,
		end: a,
		duration: a - i,
		allDay: Number(n.allDay),
		_seg: e
	};
}
function eg(e, t) {
	let { pluginHooks: n } = t, r = n.isDraggableTransformers, { def: i, ui: a } = e, o = a.startEditable;
	for (let e of r) o = e(o, i, a, t);
	return o;
}
function tg(e, t, n, r, i, a, o, s = !0, c = !0) {
	let { dateEnv: l, options: u } = o, { def: d } = t, { range: f } = t.instance, p = Pm(f.start, f.instantStartMs, l), m = Pm(f.end, f.instantEndMs, l), { displayEventTime: h, displayEventEnd: g } = u;
	h ??= s !== !1, g ??= c !== !1;
	let _ = !i && n && R(n).valueOf() !== R(p.marker).valueOf() ? n : p.marker, v = !a && r && R(uf(r, -1)).valueOf() !== R(uf(m.marker, -1)).valueOf() ? r : m.marker, y = _ === p.marker ? p.date.valueOf() : void 0, b = v === m.marker ? m.date.valueOf() : void 0;
	if (h && !d.allDay) {
		if (g && (i || a) && d.hasEnd) {
			let t = l.formatRangeToParts(_, v, e, {
				startInstantMs: y,
				endInstantMs: b
			}), n = rg(t);
			return n == null ? fp(t) : fp(l.formatToParts(_, e, { instantMs: y })) + n + fp(l.formatToParts(v, e, { instantMs: b }));
		}
		if (i) return fp(l.formatToParts(_, e, { instantMs: y }));
	}
	return "";
}
var ng = /* @__PURE__ */ new Set([
	"year",
	"month",
	"day"
]);
function rg(e) {
	let t, n = !1;
	for (let r of e) r.source === "shared" && (t = r), ng.has(r.type) && (n = !0);
	return n ? t.value : void 0;
}
function ig(e, t, n, r) {
	let i = e.range;
	return {
		isPast: i.instantEndMs != null && r != null ? i.instantEndMs <= r : i.end <= (n || t.start),
		isFuture: i.instantStartMs != null && r != null ? i.instantStartMs >= r : i.start >= (n || t.end),
		isToday: t && Vf(t, i.start)
	};
}
function ag(e) {
	return e.instance ? e.instance.instanceId : `${e.def.defId}:${e.range.start.toISOString()}`;
}
function og(e, t) {
	let { def: n, instance: r } = e, { url: i } = n;
	if (i) return [
		"a",
		{ href: i },
		!0
	];
	let { emitter: a, options: o } = t, { eventInteractive: s } = o;
	s ?? (s = n.interactive, s ??= !!a.hasHandlers("eventClick"));
	let c;
	return s && (c = Vp((e) => {
		a.trigger("eventClick", {
			el: e.target,
			event: new K(t, n, r),
			jsEvent: e,
			view: t.viewApi
		});
	}), c = {
		role: "button",
		...c
	}), [
		"div",
		c,
		s
	];
}
// ../../../../../localhost/Pushword/packages/newsletter/node_modules/fullcalendar/chunks/6ef0f038.js
var sg = /(^c|C)lass(Name)?$/, cg = /Content$/, lg = /(DidMount|WillUnmount)$/, ug = /^on[A-Z]/, dg = { buttons: xm };
function fg(...e) {
	let t = {};
	for (let n of e) for (let e in n) {
		let r = n[e];
		t[e] = t[e] ? pg(t[e], r) : r;
	}
	return t;
}
function pg(...e) {
	let t = {};
	for (let n of e) for (let e in n) if (e in t) {
		let r = dg[e] || (sg.test(e) ? mg : cg.test(e) ? hg : lg.test(e) ? gg : void 0);
		t[e] = r ? r(t[e], n[e], e) : n[e];
	} else t[e] = n[e];
	return t;
}
function mg(e, t, n) {
	let r = typeof e == "function", i = typeof t == "function";
	if (r || i) {
		let a = (a) => V(U(r ? e(a) : e, n), U(i ? t(a) : t, n));
		return a.parts = [e, t], a;
	}
	return V(U(e, n), U(t, n));
}
function hg(e, t) {
	if (typeof t == "function") {
		let n = (n) => {
			let r = t(n);
			return r === !0 ? typeof e == "function" ? e(n) : e : r;
		};
		return n.parts = [e, t], n;
	}
	return t ?? e;
}
function gg(e, t) {
	if (e && t) {
		let n = (...n) => {
			e(...n), t(...n);
		};
		return n.parts = [e, t], n;
	}
	return e || t;
}
function _g(e, t) {
	let n = bm(e, t);
	for (let e of n) if (!ug.test(e)) return !1;
	return !0;
}
function vg(e, t) {
	let n = e && e.parts, r = t && t.parts;
	if (n && r) {
		let e = n.length;
		if (e !== r.length) return !1;
		for (let t = 0; t < e; t++) if (!(n[t] === r[t] || vg(n[t], r[t]))) return !1;
		return !0;
	}
	return !1;
}
// ../../../../../localhost/Pushword/packages/newsletter/node_modules/fullcalendar/chunks/36d3f54a.js
var yg = [], bg = {
	code: "en",
	week: {
		dow: 0,
		doy: 4
	},
	direction: "ltr",
	todayText: "Today",
	prevText: "Prev",
	nextText: "Next",
	prevYearText: "Prev year",
	nextYearText: "Next year",
	yearText: "Year",
	monthText: "Month",
	weekTextLong: "Week",
	dayText: "Day",
	listText: "List",
	closeHint: "Close",
	eventsHint: "Events",
	allDayText: "All-day",
	timedText: "Timed",
	moreLinkText: "more",
	noEventsText: "No events to display"
}, xg = {
	...bg,
	weekTextShort: "W",
	todayHint: (e, t) => t === "day" ? "Today" : `This ${e}`,
	prevHint: "Previous $0",
	nextHint: "Next $0",
	viewHint: "$0 view",
	viewChangeHint: "Change view",
	navLinkHint: "Go to $0",
	moreLinkHint(e) {
		return `Show ${e} more event${e === 1 ? "" : "s"}`;
	}
};
function Sg(e) {
	let t = e.length > 0 ? e[0].code : "en", n = yg.concat(e), r = { en: xg };
	for (let e of n) r[e.code] = e;
	return {
		map: r,
		defaultCode: t
	};
}
function Cg(e, t) {
	return typeof e == "object" && !Array.isArray(e) ? Eg(e.code, [e.code], e) : wg(e, t);
}
function wg(e, t) {
	let n = [].concat(e || []);
	return Eg(e, n, Tg(n, t) || xg);
}
function Tg(e, t) {
	for (let n = 0; n < e.length; n += 1) {
		let r = e[n].toLocaleLowerCase().split("-");
		for (let e = r.length; e > 0; --e) {
			let n = r.slice(0, e).join("-");
			if (t[n]) return t[n];
		}
	}
	return null;
}
function Eg(e, t, n) {
	let r = pg(bg, n);
	delete r.code;
	let { week: i } = r;
	return delete r.week, {
		codeArg: e,
		codes: t,
		week: i,
		simpleNumberFormat: new Intl.NumberFormat(e),
		options: r
	};
}
var Dg = class extends Error {
	constructor(e, t) {
		super(e), this.response = t;
	}
};
function Og(e, t, n) {
	e = e.toUpperCase();
	let r = { method: e };
	return e === "GET" ? t += (t.indexOf("?") === -1 ? "?" : "&") + new URLSearchParams(n) : (r.body = new URLSearchParams(n), r.headers = { "Content-Type": "application/x-www-form-urlencoded" }), fetch(t, r).then((e) => {
		if (e.ok) return e.json().then((t) => [t, e], () => {
			throw new Dg("Failure parsing JSON", e);
		});
		throw new Dg("Request failed", e);
	});
}
function kg(e, t) {
	t.emitter.trigger("datesSet", {
		...Eh(e.activeRange, t.dateEnv),
		view: t.viewApi
	});
}
function Ag(e, t) {
	let { emitter: n } = t;
	n.hasHandlers("eventsSet") && n.trigger("eventsSet", Wh(e, t));
}
var jg = {
	name: "array-event-source",
	eventSourceDefs: [{
		ignoreRange: !0,
		parseMeta(e) {
			return Array.isArray(e.events) ? e.events : null;
		},
		fetch(e, t) {
			t({ rawEvents: e.eventSource.meta });
		}
	}]
};
function Mg(e, t, n) {
	let r = !1, i = function(e) {
		r || (r = !0, t(e));
	}, a = function(e) {
		r || (r = !0, n(e));
	}, o = e(i, a);
	o && typeof o.then == "function" && o.then(i, a);
}
var Ng = {
	name: "func-event-source",
	eventSourceDefs: [{
		parseMeta(e) {
			return typeof e.events == "function" ? e.events : null;
		},
		fetch(e, t, n) {
			let { dateEnv: r } = e.context, i = e.eventSource.meta;
			Mg(i.bind(null, Eh(e.range, r)), (e) => t({ rawEvents: e }), n);
		}
	}]
}, Pg = {
	name: "json-event-source",
	eventSourceRefiners: {
		method: String,
		extraParams: G,
		startParam: String,
		endParam: String,
		timeZoneParam: String
	},
	eventSourceDefs: [{
		parseMeta(e) {
			return e.url && (e.format === "json" || !e.format) ? {
				url: e.url,
				format: "json",
				method: (e.method || "GET").toUpperCase(),
				extraParams: e.extraParams,
				startParam: e.startParam,
				endParam: e.endParam,
				timeZoneParam: e.timeZoneParam
			} : null;
		},
		fetch(e, t, n) {
			let { meta: r } = e.eventSource, i = Fg(r, e.range, e.context);
			Og(r.method, r.url, i).then(([e, n]) => {
				t({
					rawEvents: e,
					response: n
				});
			}, n);
		}
	}]
};
function Fg(e, t, n) {
	let { dateEnv: r, options: i } = n, a, o, s, c, l = {};
	return a = e.startParam, a ??= i.startParam, o = e.endParam, o ??= i.endParam, s = e.timeZoneParam, s ??= i.timeZoneParam, c = typeof e.extraParams == "function" ? e.extraParams() : e.extraParams || {}, Object.assign(l, c), l[a] = r.formatIso(t.start), l[o] = r.formatIso(t.end), r.timeZone !== "local" && (l[s] = r.timeZone), l;
}
var Ig = {
	name: "change-handler",
	optionChangeHandlers: {
		controller(e, t) {
			e._setApi(t.calendarApi);
		},
		events(e, t) {
			Lg([e], t);
		},
		eventSources: Lg
	}
};
function Lg(e, t) {
	let n = dm(t.getCurrentData().eventSources);
	if (n.length === 1 && e.length === 1 && Array.isArray(n[0]._raw) && Array.isArray(e[0])) {
		t.dispatch({
			type: "RESET_RAW_EVENTS",
			sourceId: n[0].sourceId,
			rawEvents: e[0]
		});
		return;
	}
	let r = [];
	for (let t of e) {
		let e = !1;
		for (let r = 0; r < n.length; r += 1) if (n[r]._raw === t) {
			n.splice(r, 1), e = !0;
			break;
		}
		e || r.push(t);
	}
	for (let e of n) t.dispatch({
		type: "REMOVE_EVENT_SOURCE",
		sourceId: e.sourceId
	});
	for (let e of r) t.calendarApi.addEventSource(e);
}
var Rg = {
	id: String,
	defaultAllDay: Boolean,
	url: String,
	format: String,
	events: G,
	eventDataTransform: G,
	success: G,
	failure: G
};
function zg(e, t, n = Bg(t)) {
	let r;
	if (typeof e == "string" ? r = { url: e } : typeof e == "function" || Array.isArray(e) ? r = { events: e } : typeof e == "object" && e && (r = e), r) {
		let { refined: i, extra: a } = Am(r, n), o = Vg(i, t);
		if (o) return {
			_raw: e,
			isFetching: !1,
			latestFetchId: "",
			fetchRange: null,
			defaultAllDay: i.defaultAllDay,
			eventDataTransform: i.eventDataTransform,
			success: i.success,
			failure: i.failure,
			publicId: i.id || "",
			sourceId: Up(),
			sourceDefId: o.sourceDefId,
			meta: o.meta,
			ui: sh(i, t),
			extendedProps: a
		};
	}
	return null;
}
function Bg(e) {
	return {
		...ah,
		...Rg,
		...e.pluginHooks.eventSourceRefiners
	};
}
function Vg(e, t) {
	let n = t.pluginHooks.eventSourceDefs;
	for (let t = n.length - 1; t >= 0; --t) {
		let r = n[t].parseMeta(e);
		if (r) return {
			sourceDefId: t,
			meta: r
		};
	}
	return null;
}
function Hg(e, t, n) {
	let r = t ? t.activeRange : null;
	return Kg({}, e_(e, n), r, n);
}
function Ug(e, t, n, r) {
	let i = n ? n.activeRange : null;
	switch (t.type) {
		case "ADD_EVENT_SOURCES": return Kg(e, t.sources, i, r);
		case "REMOVE_EVENT_SOURCE": return qg(e, t.sourceId);
		case "PREV":
		case "NEXT":
		case "CHANGE_DATE":
		case "CHANGE_VIEW_TYPE": return n ? Jg(e, i, r) : e;
		case "FETCH_EVENT_SOURCES": return Xg(e, t.sourceIds ? fm(t.sourceIds) : $g(e, r), i, t.isRefetch || !1, r);
		case "RECEIVE_EVENTS":
		case "RECEIVE_EVENT_ERROR": return Qg(e, t.sourceId, t.fetchId, t.fetchRange);
		case "REMOVE_ALL_EVENT_SOURCES": return {};
		default: return e;
	}
}
function Wg(e, t, n) {
	let r = t ? t.activeRange : null;
	return Xg(e, $g(e, n), r, !0, n);
}
function Gg(e) {
	for (let t in e) if (e[t].isFetching) return !0;
	return !1;
}
function Kg(e, t, n, r) {
	let i = {};
	for (let e of t) i[e.sourceId] = e;
	return n && (i = Jg(i, n, r)), {
		...e,
		...i
	};
}
function qg(e, t) {
	return lm(e, (e) => e.sourceId !== t);
}
function Jg(e, t, n) {
	return Xg(e, lm(e, (e) => Yg(e, t, n)), t, !1, n);
}
function Yg(e, t, n) {
	return t_(e, n) ? !n.options.lazyFetching || !e.fetchRange || e.isFetching || t.start < e.fetchRange.start || t.end > e.fetchRange.end : !e.latestFetchId;
}
function Xg(e, t, n, r, i) {
	let a = {};
	for (let o in e) {
		let s = e[o];
		a[o] = t[o] ? Zg(s, n, r, i) : s;
	}
	return a;
}
function Zg(e, t, n, r) {
	let { options: i, calendarApi: a } = r, o = r.pluginHooks.eventSourceDefs[e.sourceDefId], s = Up();
	return o.fetch({
		eventSource: e,
		range: t,
		isRefetch: n,
		context: r
	}, (n) => {
		let { rawEvents: o } = n;
		i.eventSourceSuccess && (o = i.eventSourceSuccess.call(a, o, n.response) || o), e.success && (o = e.success.call(a, o, n.response) || o), r.dispatch({
			type: "RECEIVE_EVENTS",
			sourceId: e.sourceId,
			fetchId: s,
			fetchRange: t,
			rawEvents: o
		});
	}, (n) => {
		let o = !1;
		i.eventSourceFailure && (i.eventSourceFailure.call(a, n), o = !0), e.failure && (e.failure(n), o = !0), o || jp(`Unhandled event source error: ${n.message}`, n), r.dispatch({
			type: "RECEIVE_EVENT_ERROR",
			sourceId: e.sourceId,
			fetchId: s,
			fetchRange: t,
			error: n
		});
	}), {
		...e,
		isFetching: !0,
		latestFetchId: s
	};
}
function Qg(e, t, n, r) {
	let i = e[t];
	return i && n === i.latestFetchId ? {
		...e,
		[t]: {
			...i,
			isFetching: !1,
			fetchRange: r
		}
	} : e;
}
function $g(e, t) {
	return lm(e, (e) => t_(e, t));
}
function e_(e, t) {
	let n = Bg(t), r = [].concat(e.eventSources || []), i = [];
	e.initialEvents && r.unshift(e.initialEvents), e.events && r.unshift(e.events);
	for (let e of r) {
		let r = zg(e, t, n);
		r && i.push(r);
	}
	return i;
}
function t_(e, t) {
	return !t.pluginHooks.eventSourceDefs[e.sourceDefId].ignoreRange;
}
var n_ = {
	name: "simple-recurring-event",
	recurringTypes: [{
		parse(e, t) {
			if (e.daysOfWeek || e.startTime || e.endTime || e.startRecur || e.endRecur) {
				let n = {
					daysOfWeek: e.daysOfWeek || null,
					startTime: e.startTime || null,
					endTime: e.endTime || null,
					startRecur: e.startRecur ? t.createMarker(e.startRecur) : null,
					endRecur: e.endRecur ? t.createMarker(e.endRecur) : null,
					dateEnv: t
				}, r;
				return e.duration && (r = e.duration), !r && e.startTime && e.endTime && (r = $f(e.endTime, e.startTime)), {
					allDayGuess: !e.startTime && !e.endTime,
					duration: r,
					typeData: n
				};
			}
			return null;
		},
		expand(e, t, n) {
			let r = Lf(t, {
				start: e.startRecur,
				end: e.endRecur
			});
			return r ? r_(e.daysOfWeek, e.startTime, e.dateEnv, n, r) : [];
		}
	}],
	eventRefiners: {
		daysOfWeek: G,
		startTime: z,
		endTime: z,
		duration: z,
		startRecur: G,
		endRecur: G
	}
};
function r_(e, t, n, r, i) {
	let a = e ? fm(e) : null, o = R(i.start), s = i.end, c = [];
	for (t && (t.milliseconds < 0 ? s = L(s, 1) : t.milliseconds >= 864e5 && (o = L(o, -1))); o < s;) {
		let e;
		(!a || a[o.getUTCDay()]) && (e = t ? r.add(o, t) : o, c.push(r.createMarker(n.toDate(e)))), o = L(o, 1);
	}
	return c;
}
var i_ = [
	jg,
	Ng,
	Pg,
	n_,
	Ig,
	{
		name: "misc",
		isLoadingFuncs: [(e) => Gg(e.eventSources)],
		propSetHandlers: {
			dateProfile: kg,
			eventStore: Ag
		}
	}
], a_ = class {
	constructor() {
		this.handlers = {}, this.thisContext = null;
	}
	setThisContext(e) {
		this.thisContext = e;
	}
	setOptions(e) {
		this.options = e;
	}
	on(e, t) {
		o_(this.handlers, e, t);
	}
	off(e, t) {
		s_(this.handlers, e, t);
	}
	trigger(e, ...t) {
		let n = this.handlers[e] || [], r = this.options && this.options[e], i = [].concat(r || [], n);
		for (let e of i) e.apply(this.thisContext, t);
	}
	hasHandlers(e) {
		return !!(this.handlers[e] && this.handlers[e].length || this.options && this.options[e]);
	}
};
function o_(e, t, n) {
	(e[t] || (e[t] = [])).push(n);
}
function s_(e, t, n) {
	n ? e[t] && (e[t] = e[t].filter((e) => e !== n)) : delete e[t];
}
var c_ = 0, l_ = 0;
function u_() {
	let e = !1;
	return c_++, () => {
		e || (e = !0, c_--, l_++);
	};
}
function d_() {
	l_++;
}
function f_(e) {
	let t = u_();
	requestAnimationFrame(() => {
		try {
			e();
		} finally {
			t();
		}
	});
}
var p_ = /* @__PURE__ */ new Map();
function m_(e, t) {
	let n = u_(), r = setTimeout(() => {
		p_.delete(r);
		try {
			e();
		} finally {
			n();
		}
	}, t);
	return p_.set(r, n), r;
}
function h_(e) {
	let t = p_.get(e);
	t && (p_.delete(e), clearTimeout(e), t());
}
var g_ = {};
function __(e) {
	let t = e.getRootNode();
	return t instanceof Document ? t.body || t.documentElement : t;
}
function v_(e) {
	return getComputedStyle(e).direction === "rtl";
}
var y_ = /(top|left|right|bottom|width|height)$/i;
function b_(e, t) {
	for (let n in t) x_(e, n, t[n]);
}
function x_(e, t, n) {
	n == null ? e.style[t] = "" : typeof n == "number" && y_.test(t) ? e.style[t] = `${n}px` : e.style[t] = n;
}
function S_(e) {
	return e.composedPath?.()[0] ?? e.target;
}
// ../../../../../localhost/Pushword/packages/newsletter/node_modules/fullcalendar/chunks/8c472da4.js
function q(e, t, n) {
	let r, i;
	return function(...a) {
		if (!r) i = e.apply(this, a);
		else if (!sm(r, a)) {
			n && n(i);
			let r = e.apply(this, a);
			t && t(r, i) || (i = r);
		}
		return r = a, i;
	};
}
function C_(e, t, n) {
	let r, i;
	return (a) => {
		if (!r) i = e.call(this, a);
		else if (!gm(r, a)) {
			n && n(i);
			let r = e.call(this, a);
			t && t(r, i) || (i = r);
		}
		return r = a, i;
	};
}
var w_ = xe({});
function T_(e, t, n, r, i, a, o, s, c, l, u, d, f, p) {
	return {
		dateEnv: i,
		nowManager: a,
		options: n,
		pluginHooks: o,
		emitter: l,
		dispatch: s,
		getCurrentData: c,
		calendarApi: u,
		viewSpec: e,
		viewApi: t,
		dateProfileGenerator: r,
		baseId: d,
		registerInteractiveComponent: f,
		unregisterInteractiveComponent: p
	};
}
var E_ = class extends T {
	shouldComponentUpdate(e, t) {
		return !ym(this.props, e, this.propEquality) || !ym(this.state, t, this.stateEquality);
	}
};
E_.addPropsEquality = D_, E_.addStateEquality = O_, E_.contextType = w_, E_.prototype.propEquality = {}, E_.prototype.stateEquality = {};
var J = class extends E_ {};
J.contextType = w_;
function D_(e) {
	let t = Object.create(this.prototype.propEquality);
	Object.assign(t, e), this.prototype.propEquality = t;
}
function O_(e) {
	let t = Object.create(this.prototype.stateEquality);
	Object.assign(t, e), this.prototype.stateEquality = t;
}
function Y(e, t) {
	typeof e == "function" ? e(t) : e && (e.current = t);
}
var k_ = class extends J {
	constructor() {
		super(...arguments), this.id = Up(), this.queuedDomNodes = [], this.currentDomNodes = [], this.handleEl = (e) => {
			this.el = e, this.props.elRef && Y(this.props.elRef, e);
		};
	}
	render() {
		let { props: e, context: t } = this, { options: n } = t, { customGenerator: r, defaultGenerator: i, renderProps: a } = e, o = j_(e, "", this.handleEl), s = !1, c, l = [], u;
		if (r != null) {
			let e = typeof r == "function" ? r(a) : r;
			if (e === !0) s = !0;
			else {
				let t = e && typeof e == "object";
				t && "html" in e ? o.dangerouslySetInnerHTML = { __html: e.html } : t && "domNodes" in e ? l = Array.prototype.slice.call(e.domNodes) : (t ? ft(e) : typeof e != "function") ? c = e : u = e;
			}
		} else s = !A_(e.generatorName, n);
		return s && i && (c = i(a)), this.queuedDomNodes = l, this.currentGeneratorMeta = u, x(e.tag, o, c);
	}
	componentDidMount() {
		this.applyQueueudDomNodes(), this.triggerCustomRendering(!0);
	}
	componentDidUpdate() {
		this.applyQueueudDomNodes(), this.triggerCustomRendering(!0);
	}
	componentWillUnmount() {
		this.triggerCustomRendering(!1);
	}
	triggerCustomRendering(e) {
		let { props: t, context: n } = this, { handleCustomRendering: r, customRenderingMetaMap: i } = n.options;
		if (r) {
			let n = this.currentGeneratorMeta ?? i?.[t.generatorName];
			n && r({
				id: this.id,
				isActive: e,
				containerEl: this.el,
				generatorMeta: n,
				renderProps: t.renderProps
			});
		}
	}
	applyQueueudDomNodes() {
		let { queuedDomNodes: e, currentDomNodes: t } = this, { el: n } = this;
		if (!sm(e, t)) {
			for (let e of t) e.remove();
			for (let t of e) n.appendChild(t);
			this.currentDomNodes = e;
		}
	}
};
k_.addPropsEquality({
	renderProps: gm,
	attrs: _g,
	style: gm
});
function A_(e, t) {
	return !!(t.handleCustomRendering && e && t.customRenderingMetaMap?.[e]);
}
function j_(e, t, n) {
	let r = {
		...e.attrs,
		ref: n
	};
	return (e.className || t) && (r.className = V(t, e.className, r.className)), e.style && (r.style = e.style), r;
}
var M_ = xe(0), X = class extends T {
	constructor() {
		super(...arguments), this.InnerContent = N_.bind(void 0, this), this.handleEl = (e) => {
			this.el = e, this.props.elRef && (Y(this.props.elRef, e), e && this.didMountMisfire && this.componentDidMount());
		};
	}
	render() {
		let { props: e } = this, t = Z(e.classNameGenerator, e.renderProps);
		if (e.children) {
			let n = j_(e, t, this.handleEl), r = e.children(this.InnerContent, e.renderProps, n);
			return e.tag ? x(e.tag, n, r) : r;
		}
		return x(k_, {
			...e,
			elRef: this.handleEl,
			tag: e.tag || "div",
			className: V(e.className, t),
			renderId: this.context
		});
	}
	componentDidMount() {
		this.el ? this.props.didMount?.({
			...this.props.renderProps,
			el: this.el
		}) : this.didMountMisfire = !0;
	}
	componentWillUnmount() {
		this.props.willUnmount?.({
			...this.props.renderProps,
			el: this.el
		});
	}
};
X.contextType = M_;
function N_(e, t) {
	let n = e.props;
	return x(k_, {
		renderProps: n.renderProps,
		generatorName: n.generatorName,
		customGenerator: n.customGenerator,
		defaultGenerator: n.defaultGenerator,
		renderId: e.context,
		...t
	});
}
function Z(e, t) {
	return (typeof e == "function" ? e(t) : e) || "";
}
function P_(e) {
	return e.text;
}
function F_(e) {
	return e.height === "auto" || e.contentHeight === "auto";
}
function I_(e) {
	let { tableHeaderSticky: t } = e;
	return (t == null || t === "auto") && (t = F_(e)), t;
}
function L_(e) {
	let t = F_(e), { footerScrollbarSticky: n } = e;
	return (n == null || n === "auto") && (n = t), !!n && t;
}
function R_(e) {
	let t = e.scrollerSyncerClass;
	if (!t) throw RangeError("dayMinWidth requires the scrollgrid plugin, from your scheduler package, such as @fullcalendar/react-scheduler/scrollgrid");
	return t;
}
var z_ = class {
	constructor(e) {
		this.handleChange = e, this.isStarted = !1, this.handleRefresh = () => {
			let e = this.computeTiming();
			this.nextChangeMs = e.nextChangeMs, (e.nowDate.valueOf() !== this.nowDate.valueOf() || e.nowMs !== this.nowMs) && (this.nowDate = e.nowDate, this.nowMs = e.nowMs, this.todayRange = e.todayRange, this.handleChange()), this.clearTimeout(), this.setTimeout(e.waitMs);
		}, this.handleVisibilityChange = () => {
			document.hidden || this.handleRefresh();
		};
	}
	update(e) {
		if (!this.nowManager) {
			this.unit = e.unit, this.unitValue = e.unitValue, this.nowIndicatorSnap = e.nowIndicatorSnap, this.nowManager = e.nowManager, this.dateEnv = e.dateEnv;
			let t = this.computeTiming();
			this.nowDate = t.nowDate, this.nowMs = t.nowMs, this.todayRange = t.todayRange, this.nextChangeMs = t.nextChangeMs;
		} else if (e.unit !== this.unit || e.unitValue !== this.unitValue || e.nowIndicatorSnap !== this.nowIndicatorSnap || e.nowManager !== this.nowManager || e.dateEnv !== this.dateEnv) {
			this.unit = e.unit, this.unitValue = e.unitValue, this.nowIndicatorSnap = e.nowIndicatorSnap, this.nowManager = e.nowManager, this.dateEnv = e.dateEnv;
			let t = this.computeTiming();
			this.nowDate = t.nowDate, this.nowMs = t.nowMs, this.todayRange = t.todayRange, this.nextChangeMs = t.nextChangeMs, this.isStarted && (this.clearTimeout(), this.setTimeout(t.waitMs));
		}
		return {
			nowDate: this.nowDate,
			nowMs: this.nowMs,
			todayRange: this.todayRange
		};
	}
	start() {
		if (!this.isStarted && this.nowManager) {
			this.isStarted = !0, this.nowManager.addResetListener(this.handleRefresh), typeof document < "u" && document.addEventListener("visibilitychange", this.handleVisibilityChange);
			let e = this.nextChangeMs - this.nowManager.getEpochMs();
			e > 0 ? this.setTimeout(e) : this.handleRefresh();
		}
	}
	stop() {
		this.isStarted && (this.isStarted = !1, this.clearTimeout(), this.nowManager.removeResetListener(this.handleRefresh), typeof document < "u" && document.removeEventListener("visibilitychange", this.handleVisibilityChange));
	}
	computeTiming() {
		let { unit: e, unitValue: t, nowIndicatorSnap: n, dateEnv: r } = this, i = this.nowManager.getEpochMs(), a = r.timestampToMarker(i);
		n === "auto" && (n = /year|month|week|day/.test(e) || (t || 1) === 1);
		let o, s, c;
		return n ? (o = r.startOf(a, e), s = B_(o, i, r), c = V_(r.add(o, z(1, e)), i, r) - i) : (o = a, s = i, c = 6e4), c = Math.min(864e5, c), {
			nowDate: o,
			nowMs: s,
			todayRange: W_(o),
			waitMs: c,
			nextChangeMs: i + c
		};
	}
	setTimeout(e = this.computeTiming().waitMs) {
		this.timeoutId = setTimeout(() => {
			let e = this.computeTiming();
			this.nowDate = e.nowDate, this.nowMs = e.nowMs, this.todayRange = e.todayRange, this.nextChangeMs = e.nextChangeMs, this.handleChange(), this.setTimeout(e.waitMs);
		}, e);
	}
	clearTimeout() {
		this.timeoutId && clearTimeout(this.timeoutId);
	}
};
function B_(e, t, n) {
	let r = n.timestampToMarker(t).valueOf() - t, i = e.valueOf() - r;
	return n.timestampToMarker(i).valueOf() === e.valueOf() ? i : n.toDate(e).valueOf();
}
function V_(e, t, n) {
	let r = B_(e, t, n), i = H_(t, n, Math.min(r - t, 1728e5));
	return i == null ? r : Math.min(r, i);
}
function H_(e, t, n) {
	if (n <= 0) return;
	let r = U_(e, t), i = e, a = e + n;
	if (U_(a, t) !== r) {
		for (; a - i > 1;) {
			let e = Math.floor((i + a) / 2);
			U_(e, t) === r ? i = e : a = e;
		}
		return a > e ? a : void 0;
	}
}
function U_(e, t) {
	return t.timestampToMarker(e).valueOf() - e;
}
function W_(e) {
	let t = R(e);
	return {
		start: t,
		end: L(t, 1)
	};
}
function G_(e, t) {
	return e != null && (e === t || Math.abs(e - t) < .01);
}
var K_ = !0, q_ = /* @__PURE__ */ new Map(), J_ = /* @__PURE__ */ new Set(), Y_ = !1, X_ = !1, Z_ = !1;
function Q(e) {
	J_.add(e), !Y_ && !X_ && (X_ = !0, f_(() => {
		X_ = !1, Q_();
	}));
}
function Q_() {
	for (let e of J_.values()) J_.delete(e), e();
}
function $_(e) {
	let t = Y_;
	Y_ = !0, Z_ = !0;
	try {
		mt(e), t || mt(() => {
			Q_(), Y_ = !1;
		});
	} finally {
		Y_ = t, Z_ = !1;
	}
}
var ev = typeof ResizeObserver < "u" && new ResizeObserver((e) => {
	Y_ = !0, d_();
	for (let t of e) {
		let e = t.target, n = q_.get(e), r, i;
		if (t.borderBoxSize && K_) {
			let e = t.borderBoxSize[0] || t.borderBoxSize;
			r = e.inlineSize, i = e.blockSize;
		} else ({width: r, height: i} = e.getBoundingClientRect());
		let a = !1;
		G_(n.width, r) || (n.width = r, a = n.watchWidth), G_(n.height, i) || (n.height = i, a ||= n.watchHeight), a && n.callback(r, i);
	}
	mt(() => {
		Q_(), Y_ = !1;
	});
});
function tv(e, t, n = !0, r = !0) {
	let i = {
		callback: t,
		watchWidth: n,
		watchHeight: r
	};
	if (q_.set(e, i), Z_) {
		let { width: n, height: r } = e.getBoundingClientRect();
		i.width = n, i.height = r, t(n, r);
	}
	return ev && ev.observe(e, { box: "border-box" }), () => {
		q_.delete(e), ev && ev.unobserve(e);
	};
}
function nv(e, t) {
	return tv(e, t, !0);
}
function rv(e, t) {
	return tv(e, (e, n) => t(n), !1, !0);
}
var iv = class extends T {
	constructor(e, t) {
		super(e, t), this.handleChange = () => {
			this.forceUpdate();
		}, this.runner = new z_(this.handleChange);
	}
	render() {
		let { props: e, context: t } = this, { nowDate: n, nowMs: r, todayRange: i } = this.runner.update({
			nowManager: t.nowManager,
			unit: e.unit,
			unitValue: e.unitValue,
			nowIndicatorSnap: t.options.nowIndicatorSnap,
			dateEnv: t.dateEnv
		});
		return e.children(n, i, r);
	}
	componentDidMount() {
		this.runner.start();
	}
	componentWillUnmount() {
		this.runner.stop();
	}
};
iv.contextType = w_;
var av = H({
	year: "numeric",
	month: "long",
	day: "numeric"
}), ov = H({ week: "long" }), sv = H({ weekday: "long" });
function cv(e) {
	for (let t of e) if (t.type === "weekday") return t.value;
	return "";
}
function lv(e) {
	for (let t of e) if (t.type === "day") return t.value;
	return "";
}
function uv(e) {
	for (let t of e) if (t.type === "month") return t.value;
	return "";
}
function dv(e, t, n = "day") {
	return fp(e.dateEnv.formatToParts(t, n === "week" ? ov : av));
}
function fv(e, t, n = "day", r = dv(e, t, n), i = !0, a = !1) {
	let { dateEnv: o, options: s, calendarApi: c } = e, l = o.toDate(t), u = (e) => {
		let r = n === "day" ? s.navLinkDayClick : n === "week" ? s.navLinkWeekClick : null;
		typeof r == "function" ? r.call(c, o.toDate(t), e) : (typeof r == "string" && (n = r), c.zoomTo(t, n));
	};
	return {
		role: "link",
		"aria-label": a ? r : em(s.navLinkHint, [r, l], r),
		className: V(s.navLinkClass, B.cursorPointer, B.internalNavLink),
		...i ? Bp(u) : { onClick: u }
	};
}
function pv(e, t, n, r, i) {
	let a = !(!n || n.activeRange && Vf(n.activeRange, e));
	return {
		date: t.toDate(e),
		dow: e.getUTCDay(),
		isDisabled: a,
		isOther: !a && !(!n || Vf(n.currentRange, e)),
		isToday: !a && !!(r && Vf(r, e)),
		isPast: !a && !!(i ? e < i : r && e < r.start),
		isFuture: !a && !!(i ? e > i : r && e >= r.end)
	};
}
var mv = class extends J {
	constructor() {
		super(...arguments), this.refineRenderProps = C_(hv);
	}
	render() {
		let { props: e, context: t } = this, { options: n, viewSpec: r } = t, i = this.refineRenderProps({
			...rm(n),
			options: {
				headerToolbar: n.headerToolbar,
				footerToolbar: n.footerToolbar
			},
			isHeightAuto: F_(n),
			viewApi: t.viewApi
		});
		return D(X, {
			elRef: e.elRef,
			tag: e.tag || "div",
			attrs: e.attrs,
			style: e.style,
			className: V(e.className, Z(n.viewClass, i), Z(r.optionDefaults.class, i), Z(r.optionDefaults.className, i), Z(r.optionOverrides.class, i), Z(r.optionOverrides.className, i)),
			renderProps: i,
			generatorName: void 0,
			didMount: n.didMount || n.viewDidMount,
			willUnmount: n.willUnmount || n.viewWillUnmount,
			children: () => e.children
		});
	}
};
function hv(e) {
	return {
		view: e.viewApi,
		borderlessX: e.borderlessX,
		borderlessTop: e.borderlessTop,
		borderlessBottom: e.borderlessBottom,
		options: e.options,
		isHeightAuto: e.isHeightAuto
	};
}
var gv = class extends J {
	constructor() {
		super(...arguments), this.uid = Up();
	}
	prepareHits() {}
	queryHit(e, t, n, r, i) {
		return null;
	}
	isValidSegDownEl(e) {
		return !this.props.eventDrag && !this.props.eventResize && !e.closest(`.${B.internalEventMirror}`);
	}
	isValidDateDownEl(e) {
		return !e.closest(`.${B.internalEvent}:not(.${B.internalBgEvent})`) && !e.closest(`.${B.internalMoreLink}`) && !e.closest(`.${B.internalNavLink}`) && !e.closest(`.${B.internalPopover}`);
	}
}, _v = class {
	constructor(e, t = !1) {
		this.drainedOption = e, this.isSettleUntracked = t, this.isRunning = !1, this.isDirty = !1, this.pauseDepths = {}, this.timeoutId = 0;
	}
	request(e) {
		if (this.isDirty = !0, !this.isPaused()) {
			if (this.clearTimeout(), e == null) this.tryDrain();
			else {
				let t = this.tryDrain.bind(this);
				this.timeoutId = this.isSettleUntracked ? setTimeout(t, e) : m_(t, e);
			}
		}
	}
	pause(e = "") {
		let { pauseDepths: t } = this;
		t[e] = (t[e] || 0) + 1, this.clearTimeout();
	}
	resume(e = "", t) {
		let { pauseDepths: n } = this;
		e in n && (t ? delete n[e] : (--n[e], n[e] <= 0 && delete n[e]), this.tryDrain());
	}
	isPaused() {
		return Object.keys(this.pauseDepths).length;
	}
	tryDrain() {
		if (!this.isRunning && !this.isPaused()) {
			for (this.isRunning = !0; this.isDirty;) this.isDirty = !1, this.drained();
			this.isRunning = !1;
		}
	}
	clear() {
		this.clearTimeout(), this.isDirty = !1, this.pauseDepths = {};
	}
	clearTimeout() {
		this.timeoutId &&= (this.isSettleUntracked ? clearTimeout(this.timeoutId) : h_(this.timeoutId), 0);
	}
	drained() {
		this.drainedOption && this.drainedOption();
	}
};
g_.scrollEndWait = 500;
var vv = class {
	constructor(e) {
		this.el = e, this.emitter = new a_(), this.isScroll = !1, this.isScrollRecent = !1, this.isWheelRecent = !1, this.isMouseDown = !1, this.isTouchDown = !1, this.isMouse = !1, this.isTouch = !1, this.isWheel = !1, this.handleScroll = () => {
			this.isScrollRecent = !0, this.isMouseDown && (this.isMouse = !0), this.isTouchDown && (this.isTouch = !0), this.isWheelRecent && (this.isWheel = !0), this.startScroll(), this.emitter.trigger("scroll", this.getIsDevice()), this.scrollWaiter.request(g_.scrollEndWait);
		}, this.handleScrollWait = () => {
			this.isScrollRecent = !1, this.isTouchDown || this.endScroll();
		}, this.handleWheel = () => {
			this.isWheelRecent = !0, this.wheelWaiter.request(500);
		}, this.handleWheelWait = () => {
			this.isWheelRecent = !1;
		}, this.handleMouseDown = () => {
			this.isMouseDown = !0;
		}, this.handleMouseUp = () => {
			this.isMouseDown = !1;
		}, this.handleTouchStart = () => {
			this.isTouchDown = !0;
		}, this.handleTouchEnd = () => {
			this.isTouchDown = !1, this.isScrollRecent || this.endScroll();
		}, this.wheelWaiter = new _v(this.handleWheelWait, !0), this.scrollWaiter = new _v(this.handleScrollWait, !0), e.addEventListener("scroll", this.handleScroll, { passive: !0 }), e.addEventListener("wheel", this.handleWheel, { passive: !0 }), e.addEventListener("mousedown", this.handleMouseDown), e.addEventListener("mouseup", this.handleMouseUp), e.addEventListener("touchstart", this.handleTouchStart, { passive: !0 }), e.addEventListener("touchend", this.handleTouchEnd);
	}
	destroy() {
		let { el: e } = this;
		e.removeEventListener("scroll", this.handleScroll, { passive: !0 }), e.removeEventListener("wheel", this.handleWheel, { passive: !0 }), e.removeEventListener("mousedown", this.handleMouseDown), e.removeEventListener("mouseup", this.handleMouseUp), e.removeEventListener("touchstart", this.handleTouchStart, { passive: !0 }), e.removeEventListener("touchend", this.handleTouchEnd);
	}
	startScroll() {
		this.isScroll || (this.isScroll = !0, this.emitter.trigger("scrollStart", this.getIsDevice()));
	}
	endScroll() {
		this.isScroll && (this.scrollWaiter.clear(), this.wheelWaiter.clear(), this.isScroll = !1, this.isWheelRecent = !1, this.emitter.trigger("scrollEnd", this.getIsDevice()), this.isMouse = !1, this.isTouch = !1, this.isWheel = !1);
	}
	getIsDevice() {
		return this.isWheel || this.isMouse || this.isTouch;
	}
}, yv = class extends gv {
	constructor() {
		super(...arguments), this.handleEl = (e) => {
			this.el && (this.el = null, this._isUnmounting = !0, this.listener.destroy()), e && (this.el = e, this._isUnmounting = !1, this.listener = new vv(e));
		}, this.handleHRuler = (e) => {
			this.disconnectHRuler && (this.disconnectHRuler(), this.disconnectHRuler = void 0, this.clientWidth !== void 0 && (this.clientWidth = void 0, Y(this.props.clientWidthRef, null))), e && (this.disconnectHRuler = nv(e, (e) => {
				this._isUnmounting || e !== this.clientWidth && (this.clientWidth = e, Y(this.props.clientWidthRef, e));
			}));
		}, this.handleVRuler = (e) => {
			this.disconnectVRuler && (this.disconnectVRuler(), this.disconnectVRuler = void 0, this.clientHeight !== void 0 && (this.clientHeight = void 0, Y(this.props.clientHeightRef, null))), e && (this.disconnectVRuler = rv(e, (e) => {
				if (this._isUnmounting) return;
				e !== this.clientHeight && (this.clientHeight = e, Y(this.props.clientHeightRef, e));
				let t = Math.round(this.el.getBoundingClientRect().height - e);
				t !== this.bottomScrollbarWidth && (this.bottomScrollbarWidth = t, Y(this.props.bottomScrollbarWidthRef, t));
			}));
		};
	}
	render() {
		let { props: e } = this, t = e.horizontal || e.vertical ? "hidden" : "";
		return D("div", {
			ref: this.handleEl,
			className: V(e.className, B.noPadding, B.rel, e.hideScrollbars && B.noScrollbars, B.internalScroller),
			style: {
				...e.style,
				overflowX: e.horizontal ? "auto" : t,
				overflowY: e.vertical ? "auto" : t
			},
			children: [
				e.children,
				!!e.clientWidthRef && D("div", {
					ref: this.handleHRuler,
					className: B.fillTop
				}),
				!!(e.clientHeightRef || e.bottomScrollbarWidthRef) && D("div", {
					ref: this.handleVRuler,
					className: B.fillStart
				})
			]
		});
	}
	endScroll() {
		this.listener.endScroll();
	}
	get x() {
		let { el: e } = this;
		return e ? bv(e) : 0;
	}
	get y() {
		let { el: e } = this;
		return e ? e.scrollTop : 0;
	}
	scrollTo({ x: e, y: t }) {
		let { el: n } = this;
		n && (t != null && (n.scrollTop = t), e != null && xv(n, e));
	}
	addScrollStartListener(e) {
		this.listener.emitter.on("scrollStart", e);
	}
	removeScrollStartListener(e) {
		this.listener.emitter.off("scrollStart", e);
	}
	addScrollEndListener(e) {
		this.listener.emitter.on("scrollEnd", e);
	}
	removeScrollEndListener(e) {
		this.listener.emitter.off("scrollEnd", e);
	}
};
function bv(e) {
	let { scrollLeft: t } = e;
	return v_(e) ? Sv(t, e) : t;
}
function xv(e, t) {
	e.scrollLeft = v_(e) ? Cv(t, e) : t;
}
function Sv(e, t) {
	switch (Tv()) {
		case "positive": return t.scrollWidth - t.clientWidth - e;
		case "negative": return -e;
	}
	return e;
}
function Cv(e, t) {
	switch (Tv()) {
		case "positive": return t.scrollWidth - t.clientWidth - e;
		case "negative": return -e;
	}
	return e;
}
var wv;
function Tv() {
	return wv ||= Ev();
}
function Ev() {
	let e = document.createElement("div");
	e.style.position = "absolute", e.style.top = "-1000px", e.style.width = "100px", e.style.height = "100px", e.style.overflow = "scroll", e.style.direction = "rtl";
	let t = document.createElement("div");
	t.style.width = "200px", t.style.height = "200px", e.appendChild(t), document.body.appendChild(e);
	let n;
	return e.scrollLeft > 0 ? n = "positive" : (e.scrollLeft = 50, n = e.scrollLeft > 0 ? "reverse" : "negative"), e.remove(), n;
}
var Dv = class extends J {
	constructor() {
		super(...arguments), this.buildPublicEvent = q((e, t, n) => new K(e, t, n)), this.handleEl = (e) => {
			this.el = e, Y(this.props.elRef, e), e && Jh(e, this.props.eventRange);
		};
	}
	render() {
		let { props: e, context: t } = this, { options: n } = t, { eventRange: r } = e, i = r.ui, a = n.eventTimeFormat || e.defaultTimeFormat, o = e.forcedTimeText ?? tg(a, r, e.slicedStart, e.slicedEnd, e.isStart, e.isEnd, t, e.defaultDisplayEventTime, e.defaultDisplayEventEnd), [s, c, l] = og(r, t), u = this.buildPublicEvent(t, r.def, r.instance), d = !e.disableDragging && eg(r, t), f = /row|column/.test(e.display), p = {
			...e.renderProps,
			event: u,
			isNarrow: e.isNarrow || !1,
			isShort: e.isShort || !1,
			timeText: o
		}, m = {
			...e.renderProps,
			event: u,
			view: t.viewApi,
			timeText: o,
			color: i.color || n.eventColor,
			contrastColor: i.contrastColor || n.eventContrastColor,
			isDraggable: d,
			isStartResizable: !e.disableResizing && e.isStart && i.durationEditable && n.eventResizableFromStart,
			isEndResizable: !e.disableResizing && e.isEnd && i.durationEditable,
			isMirror: e.isMirror,
			isStart: !!e.isStart,
			isEnd: !!e.isEnd,
			isFirst: !!e.isFirst,
			isLast: !!e.isLast,
			isPast: !!e.isPast,
			isFuture: !!e.isFuture,
			isToday: !!e.isToday,
			isSelected: !!e.isSelected,
			isDragging: !!e.isDragging,
			isResizing: !!e.isResizing,
			isInteractive: l,
			isNarrow: e.isNarrow || !1,
			isShort: e.isShort || !1,
			level: e.level || 0,
			timeClass: V(Z(n.eventTimeClass, p), f && Z(n.blockEventTimeClass, p), e.display === "row" && Z(n.rowEventTimeClass, p), e.display === "column" && Z(n.columnEventTimeClass, p), e.display === "list-item" && Z(n.listItemEventTimeClass, p)),
			titleClass: V(Z(n.eventTitleClass, p), f && Z(n.blockEventTitleClass, p), e.display === "row" && Z(n.rowEventTitleClass, p), e.display === "column" && Z(n.columnEventTitleClass, p), e.display === "list-item" && Z(n.listItemEventTitleClass, p), e.display === "row" && n.rowEventTitleSticky && B.stickyS, e.display === "column" && n.columnEventTitleSticky && B.stickyT),
			options: { eventOverlap: !!n.eventOverlap }
		}, h = V(f && Z(n.blockEventClass, m), e.display === "row" && Z(n.rowEventClass, m), e.display === "column" && Z(n.columnEventClass, m), e.display === "list-item" && Z(n.listItemEventClass, m), i.className, e.className, e.display === "column" ? B.flexCol : B.flexRow, (r.def.url || d) && B.cursorPointer, B.internalEvent, e.isMirror && B.internalEventMirror, d && B.internalEventDraggable, m.isSelected && B.internalEventSelected, (m.isStartResizable || m.isEndResizable) && B.internalEventResizable), g = V(Z(n.eventBeforeClass, m), f && Z(n.blockEventBeforeClass, m), e.display === "row" && Z(n.rowEventBeforeClass, m), e.display === "column" && Z(n.columnEventBeforeClass, m), e.display === "list-item" && Z(n.listItemEventBeforeClass, m)), _ = V(Z(n.eventAfterClass, m), f && Z(n.blockEventAfterClass, m), e.display === "row" && Z(n.rowEventAfterClass, m), e.display === "column" && Z(n.columnEventAfterClass, m), e.display === "list-item" && Z(n.listItemEventAfterClass, m)), v = V(Z(n.eventInnerClass, m), f && Z(n.blockEventInnerClass, m), e.display === "row" && Z(n.rowEventInnerClass, m), e.display === "column" && Z(n.columnEventInnerClass, m), e.display === "list-item" && Z(n.listItemEventInnerClass, m), !e.disableLiquid && B.liquid), y = e.display === "row" && n.rowEventBeforeContent, b = e.display === "row" && n.rowEventAfterContent;
		return D(X, {
			tag: s,
			attrs: {
				...e.attrs,
				...c,
				dir: e.isDragging && n.direction === "rtl" ? "rtl" : void 0
			},
			className: h,
			style: {
				"--fc-event-color": m.color,
				"--fc-event-contrast-color": m.contrastColor
			},
			elRef: this.handleEl,
			renderProps: m,
			generatorName: "eventContent",
			customGenerator: n.eventContent,
			defaultGenerator: Ov,
			classNameGenerator: n.eventClass,
			didMount: n.eventDidMount,
			willUnmount: n.eventWillUnmount,
			children: (t) => D(w, { children: [
				!!(m.isSelected && f) && D("div", { className: e.display === "column" ? B.hitX : B.hitY }),
				(g || y) && D("div", {
					className: V(g, !e.disableZindexes && B.z1, m.isStartResizable && V(e.display === "column" ? B.cursorResizeT : B.cursorResizeS, B.internalEventResizer, B.internalEventResizerStart)),
					children: [y && D(X, {
						tag: "div",
						style: { display: "contents" },
						attrs: { "aria-hidden": !0 },
						renderProps: m,
						generatorName: void 0,
						customGenerator: y
					}), !!(m.isStartResizable && m.isSelected) && D("div", { className: B.hit })]
				}),
				D(t, {
					tag: "div",
					className: V(v, !e.disableZindexes && B.z0)
				}),
				(_ || b) && D("div", {
					className: V(_, !e.disableZindexes && B.z1, m.isEndResizable && V(e.display === "column" ? B.cursorResizeB : B.cursorResizeE, B.internalEventResizer, B.internalEventResizerEnd)),
					children: [b && D(X, {
						tag: "div",
						style: { display: "contents" },
						attrs: { "aria-hidden": !0 },
						renderProps: m,
						generatorName: void 0,
						customGenerator: b
					}), !!(m.isEndResizable && m.isSelected) && D("div", { className: B.hit })]
				})
			] })
		});
	}
	componentDidUpdate(e) {
		this.el && this.props.eventRange !== e.eventRange && Jh(this.el, this.props.eventRange);
	}
};
Dv.addPropsEquality({ seg: gm });
function Ov(e) {
	return D(w, { children: [e.timeText && D("div", {
		className: e.timeClass,
		children: e.timeText
	}), D("div", {
		className: e.titleClass,
		children: e.event.title || D(w, { children: "\xA0" })
	})] });
}
// ../../../../../localhost/Pushword/packages/newsletter/node_modules/fullcalendar/chunks/269d7ef4.js
var kv = class {
	constructor(e) {
		this.props = e, this.initHiddenDays();
	}
	buildPrev(e, t, n, r) {
		let { dateEnv: i } = this.props, a = i.subtract(i.startOf(t, e.currentRangeUnit), e.dateIncrement);
		return this.build(a, n, -1, r);
	}
	buildNext(e, t, n, r) {
		let { dateEnv: i } = this.props, a = i.add(i.startOf(t, e.currentRangeUnit), e.dateIncrement);
		return this.build(a, n, 1, r);
	}
	build(e, t, n, r = !0) {
		let { props: i } = this, a, o, s, c, l, u;
		return a = this.buildValidRange(t), a = this.trimHiddenDays(a), r && (e = Hf(e, a)), o = this.buildCurrentRangeInfo(e, n), s = /^(year|month|week|day)$/.test(o.unit), c = this.buildRenderRange(this.trimHiddenDays(o.range), o.unit, s), c = this.trimHiddenDays(c), l = c, i.showNonCurrentDates || (l = Lf(l, o.range)), l = this.adjustActiveRange(l), l = Lf(l, a), u = zf(o.range, a), Vf(c, e) || (e = c.start), {
			currentDate: e,
			validRange: a,
			currentRange: o.range,
			currentRangeUnit: o.unit,
			isRangeAllDay: s,
			activeRange: l,
			renderRange: c,
			slotMinTime: i.slotMinTime,
			slotMaxTime: i.slotMaxTime,
			isValid: u,
			dateIncrement: this.buildDateIncrement(o.duration)
		};
	}
	buildValidRange(e) {
		let t = this.props.validRangeInput, n = typeof t == "function" ? t.call(this.props.calendarApi, this.props.dateEnv.toDate(e)) : t;
		return this.refineRange(n) || {
			start: null,
			end: null
		};
	}
	buildCurrentRangeInfo(e, t) {
		let { props: n } = this, r = null, i = null, a = null, o;
		return n.duration ? (r = n.duration, i = n.durationUnit, a = this.buildRangeFromDuration(e, t, r, i)) : (o = this.props.dayCount) ? (i = "day", a = this.buildRangeFromDayCount(e, t, o)) : (a = this.buildCustomVisibleRange(e)) ? i = n.dateEnv.greatestWholeUnit(a.start, a.end).unit : (r = this.getFallbackDuration(), i = op(r).unit, a = this.buildRangeFromDuration(e, t, r, i)), {
			duration: r,
			unit: i,
			range: a
		};
	}
	getFallbackDuration() {
		return z({ day: 1 });
	}
	adjustActiveRange(e) {
		let { dateEnv: t, usesMinMaxTime: n, slotMinTime: r, slotMaxTime: i } = this.props, { start: a, end: o } = e;
		return n && (rp(r) < 0 && (a = R(a), a = t.add(a, r)), rp(i) > 1 && (o = R(o), o = L(o, -1), o = t.add(o, i))), {
			start: a,
			end: o
		};
	}
	buildRangeFromDuration(e, t, n, r) {
		let { dateEnv: i, dateAlignment: a } = this.props, o, s, c;
		if (!a) {
			let { dateIncrement: e } = this.props;
			a = e && ip(e) < ip(n) ? op(e).unit : r;
		}
		rp(n) <= 1 && this.isHiddenDay(o) && (o = this.skipHiddenDays(o, t), o = R(o));
		function l() {
			o = i.startOf(e, a), s = i.add(o, n), c = {
				start: o,
				end: s
			};
		}
		return l(), this.trimHiddenDays(c) || (e = this.skipHiddenDays(e, t), l()), c;
	}
	buildRangeFromDayCount(e, t, n) {
		let { dateEnv: r, dateAlignment: i } = this.props, a = 0, o = e, s;
		i && (o = r.startOf(o, i)), o = R(o), o = this.skipHiddenDays(o, t), s = o;
		do
			s = L(s, 1), this.isHiddenDay(s) || (a += 1);
		while (a < n);
		return {
			start: o,
			end: s
		};
	}
	buildCustomVisibleRange(e) {
		let { props: t } = this, n = t.visibleRangeInput, r = typeof n == "function" ? n.call(t.calendarApi, t.dateEnv.toDate(e)) : n, i = this.refineRange(r);
		return i && (i.start == null || i.end == null) ? null : i;
	}
	buildRenderRange(e, t, n) {
		return e;
	}
	buildDateIncrement(e) {
		let { dateIncrement: t } = this.props, n;
		return t || ((n = this.props.dateAlignment) ? z(1, n) : e || z({ days: 1 }));
	}
	refineRange(e) {
		if (e) {
			let t = Pf(e, this.props.dateEnv);
			return t &&= Gm(t), t;
		}
		return null;
	}
	initHiddenDays() {
		let e = this.props.hiddenDays || [], t = [], n = 0, r;
		for (this.props.weekends === !1 && (e = e.concat([0, 6])), r = 0; r < 7; r += 1) (t[r] = e.indexOf(r) !== -1) || (n += 1);
		if (!n) throw Error("invalid hiddenDays");
		this.isHiddenDayHash = t;
	}
	trimHiddenDays(e) {
		let { start: t, end: n } = e;
		return t &&= this.skipHiddenDays(t), n &&= this.skipHiddenDays(n, -1, !0), t == null || n == null || t < n ? {
			start: t,
			end: n
		} : null;
	}
	isHiddenDay(e) {
		return e instanceof Date && (e = e.getUTCDay()), this.isHiddenDayHash[e];
	}
	skipHiddenDays(e, t = 1, n = !1) {
		for (; this.isHiddenDayHash[(e.getUTCDay() + (n ? t : 0) + 7) % 7];) e = L(e, t);
		return e;
	}
};
function Av(e, t) {
	let { currentRange: n } = e;
	if (e.currentRangeUnit === "year") return t.diffWholeYears(n.start, n.end) > 1 ? "year" : "month";
	if (e.currentRangeUnit === "month") {
		if (t.diffWholeMonths(n.start, n.end) > 1) return "month";
	} else if (e.currentRangeUnit === "week") {
		if (_f(n.start, n.end) > 1) return "week";
	} else if (e.currentRangeUnit === "day" && vf(n.start, n.end) > 1) return "day";
}
function jv(e, t, n) {
	if (e.valueOf() === R(e).valueOf()) {
		if (t === "year") return !n.getMonth(e) && n.getDay(e) === 1;
		if (t === "month") return n.getDay(e) === 1;
		if (t === "week") return e.getUTCDay() === n.weekDow;
		if (t === "day") return !0;
	}
	return !1;
}
// ../../../../../localhost/Pushword/packages/newsletter/node_modules/fullcalendar/chunks/10d52df6.js
function Mv(e, t, n, r, i) {
	switch (t.type) {
		case "RECEIVE_EVENTS": return Nv(e, n[t.sourceId], t.fetchId, t.fetchRange, t.rawEvents, i);
		case "RESET_RAW_EVENTS": return Pv(e, n[t.sourceId], t.rawEvents, r.activeRange, i);
		case "ADD_EVENTS": return Lv(e, t.eventStore, r ? r.activeRange : null, i);
		case "RESET_EVENTS": return t.eventStore;
		case "MERGE_EVENTS": return th(e, t.eventStore);
		case "PREV":
		case "NEXT":
		case "CHANGE_DATE":
		case "CHANGE_VIEW_TYPE": return r ? Jm(e, r.activeRange, i) : e;
		case "REMOVE_EVENTS": return rh(e, t.eventStore);
		case "REMOVE_EVENT_SOURCE": return zv(e, t.sourceId);
		case "REMOVE_ALL_EVENT_SOURCES": return nh(e, (e) => !e.sourceId);
		case "REMOVE_ALL_EVENTS": return eh();
		default: return e;
	}
}
function Nv(e, t, n, r, i, a) {
	if (t && n === t.latestFetchId) {
		let n = Xm(Fv(i, t, a), t, a);
		return r && (n = Jm(n, r, a)), th(zv(e, t.sourceId), n);
	}
	return e;
}
function Pv(e, t, n, r, i) {
	let { defIdMap: a, instanceIdMap: o } = Vv(e);
	return Jm(Xm(Fv(n, t, i), t, i, !1, a, o), r, i);
}
function Fv(e, t, n) {
	let r = n.options.eventDataTransform, i = t ? t.eventDataTransform : null;
	return i && (e = Iv(e, i)), r && (e = Iv(e, r)), e;
}
function Iv(e, t) {
	let n;
	if (!t) n = e;
	else {
		n = [];
		for (let r of e) {
			let e = t(r);
			e ? n.push(e) : e ?? n.push(r);
		}
	}
	return n;
}
function Lv(e, t, n, r) {
	return n && (t = Jm(t, n, r)), th(e, t);
}
function Rv(e, t, n) {
	let { defs: r } = e;
	return {
		defs: r,
		instances: um(e.instances, (e) => {
			if (r[e.defId].allDay) return e;
			let { instantStartMs: i, instantEndMs: a } = e.range, o = i == null ? n.createMarker(t.toDate(e.range.start)) : n.timestampToMarker(i), s = a == null ? n.createMarker(t.toDate(e.range.end)) : n.timestampToMarker(a);
			return {
				...e,
				range: Hm({
					marker: o,
					instantMs: i
				}, {
					marker: s,
					instantMs: a
				}, n) ?? Mm(o, uf(o, Im(e.range, t) - Fm(e.range, t)), i)
			};
		})
	};
}
function zv(e, t) {
	return nh(e, (e) => e.sourceId !== t);
}
function Bv(e, t) {
	return {
		defs: e.defs,
		instances: lm(e.instances, (e) => !t[e.instanceId])
	};
}
function Vv(e) {
	let { defs: t, instances: n } = e, r = {}, i = {};
	for (let e in t) {
		let { publicId: n } = t[e];
		n && (r[n] = e);
	}
	for (let e in n) {
		let { publicId: r } = t[n[e].defId];
		r && (i[r] = e);
	}
	return {
		defIdMap: r,
		instanceIdMap: i
	};
}
var Hv = class {
	constructor(e) {
		this.component = e.component, this.isHitComboAllowed = e.isHitComboAllowed || null;
	}
	destroy() {}
};
function Uv(e, t) {
	return {
		component: e,
		el: t.el,
		useEventCenter: t.useEventCenter == null || t.useEventCenter,
		isHitComboAllowed: t.isHitComboAllowed || null
	};
}
function Wv(e) {
	return { [e.component.uid]: e };
}
var Gv = {};
// ../../../../../localhost/Pushword/packages/newsletter/node_modules/fullcalendar/chunks/c2f96bef.js
function Kv(e) {
	return {
		name: e.name,
		premiumReleaseDate: e.premiumReleaseDate ? new Date(e.premiumReleaseDate) : void 0,
		reducers: e.reducers || [],
		isLoadingFuncs: e.isLoadingFuncs || [],
		contextInit: [].concat(e.contextInit || []),
		eventRefiners: e.eventRefiners || {},
		eventDefMemberAdders: e.eventDefMemberAdders || [],
		eventSourceRefiners: e.eventSourceRefiners || {},
		isDraggableTransformers: e.isDraggableTransformers || [],
		eventDragMutationMassagers: e.eventDragMutationMassagers || [],
		eventDefMutationAppliers: e.eventDefMutationAppliers || [],
		dateSelectionTransformers: e.dateSelectionTransformers || [],
		datePointTransforms: e.datePointTransforms || [],
		dateSpanTransforms: e.dateSpanTransforms || [],
		views: e.views || {},
		viewPropsTransformers: e.viewPropsTransformers || [],
		isPropsValid: e.isPropsValid || null,
		externalDefTransforms: e.externalDefTransforms || [],
		viewContainerAppends: e.viewContainerAppends || [],
		eventDropTransformers: e.eventDropTransformers || [],
		componentInteractions: e.componentInteractions || [],
		calendarInteractions: e.calendarInteractions || [],
		eventSourceDefs: e.eventSourceDefs || [],
		cmdFormatter: e.cmdFormatter,
		recurringTypes: e.recurringTypes || [],
		initialView: e.initialView || "",
		elementDraggingImpl: e.elementDraggingImpl,
		optionChangeHandlers: e.optionChangeHandlers || {},
		scrollerSyncerClass: e.scrollerSyncerClass || null,
		listenerRefiners: e.listenerRefiners || {},
		optionRefiners: e.optionRefiners || {},
		optionDefaults: e.optionDefaults ? [e.optionDefaults] : [],
		propSetHandlers: e.propSetHandlers || {}
	};
}
function qv(e, t) {
	let n = {}, r = {
		premiumReleaseDate: void 0,
		reducers: [],
		isLoadingFuncs: [],
		contextInit: [],
		eventRefiners: {},
		eventDefMemberAdders: [],
		eventSourceRefiners: {},
		isDraggableTransformers: [],
		eventDragMutationMassagers: [],
		eventDefMutationAppliers: [],
		dateSelectionTransformers: [],
		datePointTransforms: [],
		dateSpanTransforms: [],
		views: {},
		viewPropsTransformers: [],
		isPropsValid: null,
		externalDefTransforms: [],
		viewContainerAppends: [],
		eventDropTransformers: [],
		componentInteractions: [],
		calendarInteractions: [],
		eventSourceDefs: [],
		cmdFormatter: null,
		recurringTypes: [],
		initialView: "",
		elementDraggingImpl: null,
		optionChangeHandlers: {},
		scrollerSyncerClass: null,
		listenerRefiners: {},
		optionRefiners: {},
		optionDefaults: [],
		propSetHandlers: {}
	};
	function i(e) {
		for (let t of e) {
			let { name: e } = t;
			if (!e) throw Error("Plugin must specify a name");
			if (t.majorVersionError) throw Error(t.majorVersionError);
			if (!n[e]) {
				let a = n[e] = Kv(t);
				r = Yv(r, a), i(t.deps || []);
			}
		}
	}
	return e && i(e), i(t), r;
}
function Jv() {
	let e = [], t = [], n;
	return (r, i) => (n && sm(r, e) && sm(i, t) || (n = qv(r, i)), e = r, t = i, n);
}
function Yv(e, t) {
	return {
		premiumReleaseDate: Xv(e.premiumReleaseDate, t.premiumReleaseDate),
		reducers: e.reducers.concat(t.reducers),
		isLoadingFuncs: e.isLoadingFuncs.concat(t.isLoadingFuncs),
		contextInit: e.contextInit.concat(t.contextInit),
		eventRefiners: {
			...e.eventRefiners,
			...t.eventRefiners
		},
		eventDefMemberAdders: e.eventDefMemberAdders.concat(t.eventDefMemberAdders),
		eventSourceRefiners: {
			...e.eventSourceRefiners,
			...t.eventSourceRefiners
		},
		isDraggableTransformers: e.isDraggableTransformers.concat(t.isDraggableTransformers),
		eventDragMutationMassagers: e.eventDragMutationMassagers.concat(t.eventDragMutationMassagers),
		eventDefMutationAppliers: e.eventDefMutationAppliers.concat(t.eventDefMutationAppliers),
		dateSelectionTransformers: e.dateSelectionTransformers.concat(t.dateSelectionTransformers),
		datePointTransforms: e.datePointTransforms.concat(t.datePointTransforms),
		dateSpanTransforms: e.dateSpanTransforms.concat(t.dateSpanTransforms),
		views: fg(e.views, t.views),
		viewPropsTransformers: e.viewPropsTransformers.concat(t.viewPropsTransformers),
		isPropsValid: t.isPropsValid || e.isPropsValid,
		externalDefTransforms: e.externalDefTransforms.concat(t.externalDefTransforms),
		viewContainerAppends: e.viewContainerAppends.concat(t.viewContainerAppends),
		eventDropTransformers: e.eventDropTransformers.concat(t.eventDropTransformers),
		calendarInteractions: e.calendarInteractions.concat(t.calendarInteractions),
		componentInteractions: e.componentInteractions.concat(t.componentInteractions),
		eventSourceDefs: e.eventSourceDefs.concat(t.eventSourceDefs),
		cmdFormatter: t.cmdFormatter || e.cmdFormatter,
		recurringTypes: e.recurringTypes.concat(t.recurringTypes),
		initialView: e.initialView || t.initialView,
		elementDraggingImpl: e.elementDraggingImpl || t.elementDraggingImpl,
		optionChangeHandlers: {
			...e.optionChangeHandlers,
			...t.optionChangeHandlers
		},
		scrollerSyncerClass: e.scrollerSyncerClass || t.scrollerSyncerClass,
		listenerRefiners: {
			...e.listenerRefiners,
			...t.listenerRefiners
		},
		optionRefiners: {
			...e.optionRefiners,
			...t.optionRefiners
		},
		optionDefaults: e.optionDefaults.concat(t.optionDefaults),
		propSetHandlers: {
			...e.propSetHandlers,
			...t.propSetHandlers
		}
	};
}
function Xv(e, t) {
	return e === void 0 ? t : t === void 0 ? e : new Date(Math.max(e.valueOf(), t.valueOf()));
}
function Zv(e, t) {
	let n = {}, r;
	for (r in e) Qv(r, n, e, t);
	for (r in t) Qv(r, n, e, t);
	return n;
}
function Qv(e, t, n, r) {
	if (t[e]) return t[e];
	let i = $v(e, t, n, r);
	return i && (t[e] = i), i;
}
function $v(e, t, n, r) {
	let i = n[e], a = r[e], o = (e) => i && i[e] !== null ? i[e] : a && a[e] !== null ? a[e] : null, s = o("component"), c = o("superType"), l = null;
	if (c) {
		if (c === e) throw Error("Can't have a custom view type that references itself");
		l = Qv(c, t, n, r);
	}
	return !s && l && (s = l.component), s ? {
		type: e,
		component: s,
		defaults: pg(l ? l.defaults : {}, i ? i.rawOptions : {}),
		overrides: pg(l ? l.overrides : {}, a ? a.rawOptions : {})
	} : null;
}
function ey(e) {
	return um(e, ty);
}
function ty(e) {
	let t = typeof e == "function" ? { component: e } : e, { component: n } = t;
	return t.content ? n = ny(t.content) : n && !(n.prototype instanceof J) && (n = ny(n)), {
		superType: t.type,
		component: n,
		rawOptions: t
	};
}
function ny(e) {
	return (t) => D(w_.Consumer, { children: (n) => {
		let { options: r, viewSpec: i } = n, a = {
			...t,
			nextDayThreshold: r.nextDayThreshold,
			...rm(r),
			options: {
				headerToolbar: r.headerToolbar,
				footerToolbar: r.footerToolbar
			},
			isHeightAuto: F_(r),
			view: n.viewApi
		};
		return D(X, {
			tag: "div",
			className: V(Z(r.viewClass, a), Z(i.optionDefaults.class, a), Z(i.optionDefaults.className, a), Z(i.optionOverrides.class, a), Z(i.optionOverrides.className, a)),
			renderProps: a,
			generatorName: void 0,
			customGenerator: e,
			didMount: r.didMount || r.viewDidMount,
			willUnmount: r.willUnmount || r.viewWillUnmount
		});
	} });
}
function ry(e, t, n) {
	let r = ey(e), i = ey(t.views);
	return um(Zv(r, i), (e) => iy(e, i, t, n));
}
function iy(e, t, n, r) {
	let i = e.overrides.duration || e.defaults.duration || r.duration || n.duration, a = null, o = "", s = "", c = {};
	if (i && (a = oy(i), a)) {
		let e = op(a);
		o = e.unit, e.value === 1 && (s = o, c = t[o] ? t[o].rawOptions : {});
	}
	return {
		type: e.type,
		component: e.component,
		duration: a,
		durationUnit: o,
		singleUnit: s,
		optionDefaults: e.defaults,
		optionOverrides: {
			...c,
			...e.overrides
		}
	};
}
var ay = {};
function oy(e) {
	let t = JSON.stringify(e), n = ay[t];
	return n === void 0 && (n = z(e), ay[t] = n), n;
}
function sy(e, t) {
	return t.type === "CHANGE_VIEW_TYPE" && (e = t.viewType), e;
}
function cy(e, t) {
	switch (t.type) {
		case "CHANGE_DATE": return t.dateMarker;
		default: return e;
	}
}
function ly(e, t, n) {
	let r = e.initialDate;
	return r == null ? n.getDateMarker() : t.createMarker(r);
}
function uy(e, t) {
	switch (t.type) {
		case "SET_OPTION": return {
			...e,
			[t.optionName]: t.rawOptionValue
		};
		default: return e;
	}
}
function dy(e, t, n, r, i) {
	let a;
	switch (t.type) {
		case "CHANGE_VIEW_TYPE": return i.build(t.dateMarker || n, r);
		case "CHANGE_DATE": return i.build(t.dateMarker, r);
		case "PREV":
			if (a = i.buildPrev(e, n, r), a.isValid) return a;
			break;
		case "NEXT": if (a = i.buildNext(e, n, r), a.isValid) return a;
	}
	return e;
}
function fy(e, t) {
	switch (t.type) {
		case "UNSELECT_DATES": return null;
		case "SELECT_DATES": return t.selection;
		default: return e;
	}
}
function py(e, t) {
	switch (t.type) {
		case "UNSELECT_EVENT": return "";
		case "SELECT_EVENT": return t.eventInstanceId;
		default: return e;
	}
}
function my(e, t) {
	let n;
	switch (t.type) {
		case "UNSET_EVENT_DRAG": return null;
		case "SET_EVENT_DRAG": return n = t.state, {
			affectedEvents: n.affectedEvents,
			mutatedEvents: n.mutatedEvents,
			isEvent: n.isEvent
		};
		default: return e;
	}
}
function hy(e, t) {
	let n;
	switch (t.type) {
		case "UNSET_EVENT_RESIZE": return null;
		case "SET_EVENT_RESIZE": return n = t.state, {
			affectedEvents: n.affectedEvents,
			mutatedEvents: n.mutatedEvents,
			isEvent: n.isEvent
		};
		default: return e;
	}
}
function gy(e, t, n) {
	return {
		header: e.headerToolbar ? _y(e.headerToolbar, e, t, n) : null,
		footer: e.footerToolbar ? _y(e.footerToolbar, e, t, n) : null
	};
}
function _y(e, t, n, r) {
	let i = t.direction === "rtl", a = [], o = !1;
	function s(e) {
		let i = vy(e, t, n, r);
		return a.push(...i.viewsWithButtons), o ||= i.hasTitle, i.widgets;
	}
	return {
		sectionWidgets: {
			start: s(e[i ? "right" : "left"] || e.start || ""),
			center: s(e.center || ""),
			end: s(e[i ? "left" : "right"] || e.end || "")
		},
		viewsWithButtons: a,
		hasTitle: o
	};
}
function vy(e, t, n, r) {
	let i = t.buttons || {}, a = t.toolbarElements || {}, o = e ? e.split(" ") : [], s = [], c = !1;
	return {
		widgets: o.map((e) => e.split(",").map((e) => {
			if (e === "title") return c = !0, { name: e };
			if (a[e]) return {
				name: e,
				customElement: a[e]
			};
			let o, l = i[e] || {}, u, d, f;
			if (o = n[e]) {
				s.push(e);
				let n = o.optionDefaults.buttonTextKey;
				u = l.text || (n ? t[n] : "") || (o.singleUnit ? t[o.singleUnit + "TextLong"] || t[o.singleUnit + "Text"] : "") || e, d = em(l.hint || t.viewHint, [u, e], u), f = (t) => {
					l?.click?.(t), t.defaultPrevented || r.changeView(e);
				};
			} else u = l.text || t[e + "TextLong"] || t[e + "Text"] || e, d = e === "prevYear" ? em(l.hint || t.prevHint, [t.yearText, "year"], u) : e === "nextYear" ? em(l.hint || t.nextHint, [t.yearText, "year"], u) : (n) => em(l.hint || t[e + "Hint"], [t[n + "TextLong"] || t[n + "Text"], n], u), f = (t) => {
				l?.click?.(t), t.defaultPrevented || r[e]?.();
			};
			return {
				name: e,
				isView: !!o,
				buttonText: u,
				buttonHint: d,
				buttonDisplay: l.display,
				buttonIconClass: l.iconClass,
				buttonIconContent: l.iconContent,
				buttonClick: f,
				buttonIsPrimary: l.isPrimary || !1,
				buttonClass: l.class ?? l.className,
				buttonDidMount: l.didMount,
				buttonWillUnmount: l.willUnmount
			};
		})),
		viewsWithButtons: s,
		hasTitle: c
	};
}
var yy = class {
	constructor(e, t, n) {
		this.type = e, this.getCurrentData = t, this.dateEnv = n;
	}
	get calendar() {
		return this.getCurrentData().calendarApi;
	}
	get title() {
		return this.getCurrentData().viewTitle;
	}
	get titleParts() {
		return this.getCurrentData().viewTitleParts;
	}
	get activeStart() {
		return this.dateEnv.toDate(this.getCurrentData().dateProfile.activeRange.start);
	}
	get activeEnd() {
		return this.dateEnv.toDate(this.getCurrentData().dateProfile.activeRange.end);
	}
	get currentStart() {
		return this.dateEnv.toDate(this.getCurrentData().dateProfile.currentRange.start);
	}
	get currentEnd() {
		return this.dateEnv.toDate(this.getCurrentData().dateProfile.currentRange.end);
	}
	getOption(e) {
		return this.getCurrentData().options[e];
	}
}, by = {
	startTime: "09:00",
	endTime: "17:00",
	daysOfWeek: [
		1,
		2,
		3,
		4,
		5
	],
	display: "inverse-background",
	className: "",
	groupId: "_businessHours"
};
function xy(e, t) {
	return Xm(Sy(e), null, t);
}
function Sy(e) {
	let t;
	return t = e === !0 ? [{}] : Array.isArray(e) ? e.filter((e) => e.daysOfWeek) : typeof e == "object" && e ? [e] : [], t = t.map((e) => ({
		...by,
		...e
	})), t;
}
function Cy(e, t, n) {
	let r;
	r = /^(year|month)$/.test(e.currentRangeUnit) ? e.currentRange : e.activeRange;
	let i, a = { isEndExclusive: e.isRangeAllDay };
	return t.titleFormat ? i = n.formatRangeToParts(r.start, r.end, H(t.titleFormat), a) : (i = n.formatRangeToParts(r.start, r.end, H(wy(e, t.disallowAmbigTitle, "long")), a), Ty(i) && (i = n.formatRangeToParts(r.start, r.end, H(wy(e, t.disallowAmbigTitle, "short")), a))), i;
}
function wy(e, t, n) {
	let { currentRangeUnit: r } = e;
	if (r === "year") return { year: "numeric" };
	if (r === "month") return {
		year: "numeric",
		month: n
	};
	if (!t) {
		let t = vf(e.currentRange.start, e.currentRange.end);
		if (t !== null && t > 1) return {
			year: "numeric",
			month: n
		};
	}
	return {
		year: "numeric",
		month: "long",
		day: "numeric"
	};
}
function Ty(e) {
	let t = !1, n = !1;
	for (let r of e) r.type === "month" && (r.source === "startRange" && (t = !0), r.source === "endRange" && (n = !0));
	return t && n;
}
var Ey = class {
	constructor() {
		this.resetListeners = /* @__PURE__ */ new Set();
	}
	handleInput(e, t) {
		let n = this.dateEnv;
		if (e !== n && (typeof t == "function" ? this.nowFn = t : n || (this.nowAnchorDate = t ? Dy(t, e) : /* @__PURE__ */ new Date(), this.nowAnchorQueried = Date.now()), this.dateEnv = e, n)) for (let e of this.resetListeners.values()) e();
	}
	getDateMarker() {
		return this.dateEnv.timestampToMarker(this.getEpochMs());
	}
	getEpochMs() {
		return this.nowAnchorDate ? this.nowAnchorDate.valueOf() + (Date.now() - this.nowAnchorQueried) : Dy(this.nowFn(), this.dateEnv).valueOf();
	}
	addResetListener(e) {
		this.resetListeners.add(e);
	}
	removeResetListener(e) {
		this.resetListeners.delete(e);
	}
};
function Dy(e, t) {
	let n = t.createMarkerMeta(e);
	return n.instantMs == null ? t.toDate(n.marker) : new Date(n.instantMs);
}
var Oy = class {
	constructor(e) {
		this.computeCurrentViewData = q(this._computeCurrentViewData), this.organizeRawLocales = q(Sg), this.buildLocale = q(Cg), this.buildPluginHooks = Jv(), this.buildDateEnv = q(ky), this.parseToolbars = q(gy), this.buildViewSpecs = q(ry), this.buildDateProfileGenerator = C_(Ay), this.buildViewApi = q(jy), this.buildViewUiProps = C_(Py), this.buildEventUiBySource = q(My, gm), this.buildEventUiBases = q(Ny), this.parseContextBusinessHours = C_(Iy), this.buildToolbarProps = q(zy), this.buildTitleParts = q(Cy), this.joinTitleParts = q(fp), this.nowManager = new Ey(), this.isDrainingActionQueue = !1, this.actionQueue = [], this.optionOverrides = {}, this.emitter = new a_(), this.currentCalendarOptionsRefiners = {}, this.currentCalendarOptionsInput = {}, this.currentCalendarOptionsRefined = {}, this.currentViewOptionsInput = {}, this.currentViewOptionsRefined = {}, this.optionsForRefining = [], this.optionsForHandling = [], this.getCurrentData = () => this.data, this.handleNowChange = () => {
			this.dispatch({ type: "UPDATE_NOW" });
		}, this.dispatch = (e) => {
			this.actionQueue.push(e), this.isDrainingActionQueue || this.drainActionQueue();
		}, this.config = e, this.nowManager = new Ey(), this.nowTimer = new z_(this.handleNowChange);
	}
	mount() {
		this.nowTimer.start();
	}
	destroy() {
		this.nowTimer.stop();
	}
	update(e) {
		return this.optionOverrides = e, this.actionQueue.push({ type: "IDLE" }), this.drainActionQueue(), this.data;
	}
	resetOptions(e, t) {
		t === void 0 ? this.optionOverrides = e : (this.optionOverrides = {
			...this.optionOverrides,
			...e
		}, this.optionsForRefining.push(...t)), this.dispatch({ type: "RESET_OPTIONS" });
	}
	drainActionQueue() {
		let e, { state: t, data: n } = this, r = !t, { actionQueue: i } = this, a = [];
		for (this.isDrainingActionQueue = !0; i.length;) {
			let r = i.shift();
			({state: t, data: n, calendarContext: e} = this.reduce(t, n, r)), this.state = t, this.data = n, r.type !== "IDLE" && a.push(r);
		}
		if (this.isDrainingActionQueue = !1, r) {
			let t = e.options.controller;
			t && t._setApi(this.config.calendarApi);
		}
		if (!r && a.length) {
			let { onDataChange: e } = this.config;
			e && e(this.data, a);
		}
	}
	reduce(e, t, n) {
		let { config: r } = this, i = !e, a = i ? {} : uy(e.dynamicOptionOverrides, n), o = this.computeOptionsData(this.optionOverrides, a, r.calendarApi), s = i ? o.calendarOptions.initialView || o.pluginHooks.initialView : sy(e.currentViewType, n), c = this.computeCurrentViewData(s, o, this.optionOverrides, a);
		r.calendarApi.currentDataManager = this, this.emitter.setThisContext(r.calendarApi), this.emitter.setOptions(c.options);
		let l = {
			nowManager: this.nowManager,
			dateEnv: o.dateEnv,
			options: o.calendarOptions,
			pluginHooks: o.pluginHooks,
			calendarApi: r.calendarApi,
			dispatch: this.dispatch,
			emitter: this.emitter,
			getCurrentData: this.getCurrentData
		}, { nowDate: u } = this.nowTimer.update({
			unit: "day",
			unitValue: 1,
			nowIndicatorSnap: "auto",
			nowManager: this.nowManager,
			dateEnv: o.dateEnv
		}), d = i ? ly(o.calendarOptions, o.dateEnv, this.nowManager) : cy(e.currentDate, n), f;
		i ? f = c.dateProfileGenerator.build(d, u) : (f = e.dateProfile, t && t.dateProfileGenerator !== c.dateProfileGenerator && (f = c.dateProfileGenerator.build(d, u)), f = dy(f, n, d, u, c.dateProfileGenerator)), (n && (n.type === "PREV" || n.type === "NEXT") || !Vf(f.activeRange, d)) && (d = f.currentRange.start);
		let p = i ? Hg(o.calendarOptions, f, l) : Ug(e.eventSources, n, f, l), m = i ? eh() : Mv(e.eventStore, n, p, f, l), h = Gg(p), g = i ? eh() : h && !c.options.progressiveEventRendering && e.renderableEventStore || m, { eventUiSingleBase: _, selectionConfig: v } = this.buildViewUiProps(l), y = this.buildEventUiBySource(p), b = this.buildEventUiBases(_, y), x = {
			dynamicOptionOverrides: a,
			currentViewType: s,
			currentDate: d,
			dateProfile: f,
			eventSources: p,
			eventStore: m,
			renderableEventStore: g,
			selectionConfig: v,
			eventUiBases: b,
			businessHours: this.parseContextBusinessHours(l),
			dateSelection: i ? null : fy(e.dateSelection, n),
			eventSelection: i ? "" : py(e.eventSelection, n),
			eventDrag: i ? null : my(e.eventDrag, n),
			eventResize: i ? null : hy(e.eventResize, n),
			nowDate: u
		}, S = {
			...l,
			...x
		};
		for (let t of o.pluginHooks.reducers) Object.assign(x, t(e, n, S));
		let C = e ? Fy(e, l) : !1, w = Fy(x, l);
		!C && w ? this.emitter.trigger("loading", !0) : C && !w && this.emitter.trigger("loading", !1);
		let T = this.buildTitleParts(f, c.options, o.dateEnv), ee = this.joinTitleParts(T), te = this.buildToolbarProps(c.viewSpec, f, c.dateProfileGenerator, d, u, ee), E = {
			viewTitle: ee,
			viewTitleParts: T,
			nowManager: this.nowManager,
			calendarApi: r.calendarApi,
			dispatch: this.dispatch,
			emitter: this.emitter,
			getCurrentData: this.getCurrentData,
			toolbarProps: te,
			...o,
			...c,
			...x
		}, ne = o.pluginHooks.optionChangeHandlers, re = t && t.calendarOptions, ie = o.calendarOptions;
		if (re && re !== ie) {
			re.timeZone !== ie.timeZone && (x.eventSources = E.eventSources = Wg(E.eventSources, f, E), x.eventStore = E.eventStore = Rv(E.eventStore, t.dateEnv, E.dateEnv), x.renderableEventStore = E.renderableEventStore = Rv(E.renderableEventStore, t.dateEnv, E.dateEnv));
			for (let e in ne) (this.optionsForHandling.indexOf(e) !== -1 || re[e] !== ie[e]) && ne[e](ie[e], E);
		}
		return this.optionsForHandling = [], {
			state: x,
			data: E,
			calendarContext: l
		};
	}
	computeOptionsData(e, t, n) {
		if (!this.optionsForRefining.length && e === this.stableOptionOverrides && t === this.stableDynamicOptionOverrides) return this.stableCalendarOptionsData;
		let { refinedOptions: r, pluginHooks: i, localeDefaults: a, availableLocaleData: o } = this.processRawCalendarOptions(e, t), s = this.buildDateEnv(r.timeZone, r.locale, r.weekNumberCalculation, r.firstDay, r.weekTextLong, r.weekTextShort, i, o), c = this.buildViewSpecs(i.views, this.stableOptionOverrides, this.stableDynamicOptionOverrides), l = this.parseToolbars(r, c, n);
		return this.stableCalendarOptionsData = {
			calendarOptions: r,
			pluginHooks: i,
			dateEnv: s,
			viewSpecs: c,
			toolbarConfig: l,
			localeDefaults: a,
			availableRawLocales: o.map
		};
	}
	processRawCalendarOptions(e, t) {
		let { locales: n, locale: r } = pg(Tm, e, t), i = this.organizeRawLocales(n), a = i.map, o = this.buildLocale(r || i.defaultCode, a).options, s = this.buildPluginHooks(e.plugins || [], i_), c = this.currentCalendarOptionsRefiners = {
			...wm,
			...Em,
			...Dm,
			...s.listenerRefiners,
			...s.optionRefiners
		}, l = pg(Tm, ...s.optionDefaults, o, Ry(pg(e, t), c)), u = {}, d = this.currentCalendarOptionsInput, f = this.currentCalendarOptionsRefined, p = !1;
		for (let e in l) this.optionsForRefining.indexOf(e) === -1 && (l[e] === d[e] || km[e] && e in d && km[e](d[e], l[e]) || vg(d[e], l[e])) ? u[e] = f[e] : c[e] && (u[e] = c[e](l[e], e), p = !0);
		return p && (this.currentCalendarOptionsInput = l, this.currentCalendarOptionsRefined = u, this.stableOptionOverrides = e, this.stableDynamicOptionOverrides = t), this.optionsForHandling.push(...this.optionsForRefining), this.optionsForRefining = [], {
			rawOptions: this.currentCalendarOptionsInput,
			refinedOptions: this.currentCalendarOptionsRefined,
			pluginHooks: s,
			availableLocaleData: i,
			localeDefaults: o
		};
	}
	_computeCurrentViewData(e, t, n, r) {
		let i = t.viewSpecs[e];
		if (!i) throw Error(`viewType "${e}" is not available. Please make sure you've loaded all neccessary plugins`);
		let { refinedOptions: a } = this.processRawViewOptions(i, t.pluginHooks, t.localeDefaults, n, r);
		return this.nowManager.handleInput(t.dateEnv, a.now), {
			viewSpec: i,
			options: a,
			dateProfileGenerator: this.buildDateProfileGenerator({
				dateProfileGeneratorClass: i.optionDefaults.dateProfileGeneratorClass,
				duration: i.duration,
				durationUnit: i.durationUnit,
				usesMinMaxTime: i.optionDefaults.usesMinMaxTime,
				dateEnv: t.dateEnv,
				calendarApi: this.config.calendarApi,
				slotMinTime: a.slotMinTime,
				slotMaxTime: a.slotMaxTime,
				showNonCurrentDates: a.showNonCurrentDates,
				dayCount: a.dayCount,
				dateAlignment: a.dateAlignment,
				dateIncrement: a.dateIncrement,
				hiddenDays: a.hiddenDays,
				weekends: a.weekends,
				validRangeInput: a.validRange,
				visibleRangeInput: a.visibleRange,
				fixedWeekCount: a.fixedWeekCount
			}),
			viewApi: this.buildViewApi(e, this.getCurrentData, t.dateEnv)
		};
	}
	processRawViewOptions(e, t, n, r, i) {
		let a = {
			...wm,
			...Em,
			...Dm,
			...Om,
			...t.listenerRefiners,
			...t.optionRefiners
		}, o = pg(Tm, ...t.optionDefaults, e.optionDefaults, n, Ry(pg(r, e.optionOverrides, i), a)), s = {}, c = this.currentViewOptionsInput, l = this.currentViewOptionsRefined, u = !1;
		for (let e in o) o[e] === c[e] || km[e] && km[e](o[e], c[e]) || vg(c[e], o[e]) ? s[e] = l[e] : (o[e] === this.currentCalendarOptionsInput[e] || km[e] && km[e](o[e], this.currentCalendarOptionsInput[e]) ? e in this.currentCalendarOptionsRefined && (s[e] = this.currentCalendarOptionsRefined[e]) : a[e] && (s[e] = a[e](o[e], e)), u = !0);
		return u && (this.currentViewOptionsInput = o, this.currentViewOptionsRefined = s), {
			rawOptions: this.currentViewOptionsInput,
			refinedOptions: this.currentViewOptionsRefined
		};
	}
};
function ky(e, t, n, r, i, a, o, s) {
	return new hp({
		calendarSystem: "gregory",
		timeZone: e,
		locale: Cg(t || s.defaultCode, s.map),
		weekNumberCalculation: n,
		firstDay: r,
		weekTextLong: i,
		weekTextShort: a,
		cmdFormatter: o.cmdFormatter
	});
}
function Ay(e) {
	return new (e.dateProfileGeneratorClass || kv)(e);
}
function jy(e, t, n) {
	return new yy(e, t, n);
}
function My(e) {
	return um(e, (e) => e.ui);
}
function Ny(e, t) {
	return {
		base: e,
		bySource: t
	};
}
function Py(e) {
	let { options: t } = e;
	return {
		eventUiSingleBase: sh({
			display: t.eventDisplay,
			editable: t.editable,
			startEditable: t.eventStartEditable,
			durationEditable: t.eventDurationEditable,
			constraint: t.eventConstraint,
			overlap: typeof t.eventOverlap == "boolean" ? t.eventOverlap : void 0,
			allow: t.eventAllow
		}, e),
		selectionConfig: sh({
			constraint: t.selectConstraint,
			overlap: typeof t.selectOverlap == "boolean" ? t.selectOverlap : void 0,
			allow: t.selectAllow
		}, e)
	};
}
function Fy(e, t) {
	for (let n of t.pluginHooks.isLoadingFuncs) if (n(e)) return !0;
	return !1;
}
function Iy(e) {
	return xy(e.options.businessHours, e);
}
var Ly = {};
function Ry(e, t) {
	let n = {};
	for (let r in e) t[r] ? n[r] = e[r] : Ly[r] || (jp(`Unknown option \`${r}\`.`), Ly[r] = !0);
	return n;
}
function zy(e, t, n, r, i, a) {
	let o = n.build(i, i, void 0, !1), s = n.buildPrev(t, r, i, !1), c = n.buildNext(t, r, i, !1);
	return {
		title: a,
		selectedButton: e.type,
		navUnit: e.singleUnit,
		isTodayEnabled: o.isValid && !Vf(t.currentRange, i),
		isPrevEnabled: s.isValid,
		isNextEnabled: c.isValid
	};
}
var By = class {
	getCurrentData() {
		return this.currentDataManager.getCurrentData();
	}
	dispatch(e) {
		this.currentDataManager.dispatch(e);
	}
	get view() {
		return this.getCurrentData().viewApi;
	}
	batchRendering(e) {
		e();
	}
	setOption(e, t) {
		this.dispatch({
			type: "SET_OPTION",
			optionName: e,
			rawOptionValue: t
		});
	}
	getOption(e) {
		return this.currentDataManager.currentCalendarOptionsInput[e];
	}
	getAvailableLocaleCodes() {
		return Object.keys(this.getCurrentData().availableRawLocales);
	}
	on(e, t) {
		let { currentDataManager: n } = this;
		n.currentCalendarOptionsRefiners[e] ? n.emitter.on(e, t) : jp(`Unknown listener \`${e}\`.`);
	}
	off(e, t) {
		this.currentDataManager.emitter.off(e, t);
	}
	trigger(e, ...t) {
		this.currentDataManager.emitter.trigger(e, ...t);
	}
	changeView(e, t) {
		this.batchRendering(() => {
			if (this.unselect(), t) {
				if (t.start && t.end) this.dispatch({
					type: "CHANGE_VIEW_TYPE",
					viewType: e
				}), this.dispatch({
					type: "SET_OPTION",
					optionName: "visibleRange",
					rawOptionValue: t
				});
				else {
					let { dateEnv: n } = this.getCurrentData();
					this.dispatch({
						type: "CHANGE_VIEW_TYPE",
						viewType: e,
						dateMarker: n.createMarker(t)
					});
				}
			} else this.dispatch({
				type: "CHANGE_VIEW_TYPE",
				viewType: e
			});
		});
	}
	zoomTo(e, t) {
		let n = this.getCurrentData(), r;
		t ||= "day", r = n.viewSpecs[t] || this.getUnitViewSpec(t), this.unselect(), r ? this.dispatch({
			type: "CHANGE_VIEW_TYPE",
			viewType: r.type,
			dateMarker: e
		}) : this.dispatch({
			type: "CHANGE_DATE",
			dateMarker: e
		});
	}
	getUnitViewSpec(e) {
		let { viewSpecs: t, toolbarConfig: n } = this.getCurrentData(), r = [].concat(n.header ? n.header.viewsWithButtons : [], n.footer ? n.footer.viewsWithButtons : []), i, a;
		for (let e in t) r.push(e);
		for (i = 0; i < r.length; i += 1) if (a = t[r[i]], a && a.singleUnit === e) return a;
		return null;
	}
	prev() {
		this.unselect(), this.dispatch({ type: "PREV" });
	}
	next() {
		this.unselect(), this.dispatch({ type: "NEXT" });
	}
	prevYear() {
		let e = this.getCurrentData();
		this.unselect(), this.dispatch({
			type: "CHANGE_DATE",
			dateMarker: e.dateEnv.addYears(e.currentDate, -1)
		});
	}
	nextYear() {
		let e = this.getCurrentData();
		this.unselect(), this.dispatch({
			type: "CHANGE_DATE",
			dateMarker: e.dateEnv.addYears(e.currentDate, 1)
		});
	}
	today() {
		let e = this.getCurrentData();
		this.unselect(), this.dispatch({
			type: "CHANGE_DATE",
			dateMarker: e.nowManager.getDateMarker()
		});
	}
	gotoDate(e) {
		let t = this.getCurrentData();
		this.unselect(), this.dispatch({
			type: "CHANGE_DATE",
			dateMarker: t.dateEnv.createMarker(e)
		});
	}
	incrementDate(e) {
		let t = this.getCurrentData(), n = z(e);
		n && (this.unselect(), this.dispatch({
			type: "CHANGE_DATE",
			dateMarker: t.dateEnv.add(t.currentDate, n)
		}));
	}
	getDate() {
		let e = this.getCurrentData();
		return e.dateEnv.toDate(e.currentDate);
	}
	formatDate(e, t) {
		let { dateEnv: n } = this.getCurrentData(), r = n.createMarkerMeta(e);
		return fp(n.formatToParts(r.marker, H(t), { instantMs: r.instantMs }));
	}
	formatRange(e, t, n) {
		let { dateEnv: r } = this.getCurrentData(), i = r.createMarkerMeta(e), a = r.createMarkerMeta(t);
		return fp(r.formatRangeToParts(i.marker, a.marker, H(n), {
			isEndExclusive: n.isEndExclusive,
			startInstantMs: i.instantMs,
			endInstantMs: a.instantMs
		}));
	}
	formatIso(e, t) {
		let { dateEnv: n } = this.getCurrentData();
		return n.formatIso(n.createMarker(e), { omitTime: t });
	}
	select(e, t) {
		let n;
		n = t == null ? e.start == null ? {
			start: e,
			end: null
		} : e : {
			start: e,
			end: t
		};
		let r = this.getCurrentData(), i = xh(n, r.dateEnv, z({ days: 1 }));
		i && (this.dispatch({
			type: "SELECT_DATES",
			selection: i
		}), jh(i, null, r));
	}
	unselect(e) {
		let t = this.getCurrentData();
		t.dateSelection && (this.dispatch({ type: "UNSELECT_DATES" }), Mh(e, t));
	}
	addEvent(e, t) {
		if (e instanceof K) {
			let t = e._def, n = e._instance;
			return this.getCurrentData().eventStore.defs[t.defId] || (this.dispatch({
				type: "ADD_EVENTS",
				eventStore: Zm({
					def: t,
					instance: n
				})
			}), this.triggerEventAdd(e)), e;
		}
		let n = this.getCurrentData(), r;
		if (t instanceof Vh) r = t.internalEventSource;
		else if (typeof t == "boolean") t && ([r] = dm(n.eventSources));
		else if (t != null) {
			let e = this.getEventSourceById(t);
			if (!e) return jp(`Unknown event source ID \`${t}\`.`), null;
			r = e.internalEventSource;
		}
		let i = mh(e, r, n, !1);
		if (i) {
			let e = new K(n, i.def, i.def.recurringDef ? null : i.instance);
			return this.dispatch({
				type: "ADD_EVENTS",
				eventStore: Zm(i)
			}), this.triggerEventAdd(e), e;
		}
		return null;
	}
	triggerEventAdd(e) {
		let { emitter: t } = this.getCurrentData();
		t.trigger("eventAdd", {
			event: e,
			relatedEvents: [],
			revert: () => {
				this.dispatch({
					type: "REMOVE_EVENTS",
					eventStore: Uh(e)
				});
			}
		});
	}
	getEventById(e) {
		let t = this.getCurrentData(), { defs: n, instances: r } = t.eventStore;
		e = String(e);
		for (let i in n) {
			let a = n[i];
			if (a.publicId === e) {
				if (a.recurringDef) return new K(t, a, null);
				for (let e in r) {
					let n = r[e];
					if (n.defId === a.defId) return new K(t, a, n);
				}
			}
		}
		return null;
	}
	getEvents() {
		let e = this.getCurrentData();
		return Wh(e.eventStore, e);
	}
	removeAllEvents() {
		this.dispatch({ type: "REMOVE_ALL_EVENTS" });
	}
	getEventSources() {
		let e = this.getCurrentData(), t = e.eventSources, n = [];
		for (let r in t) n.push(new Vh(e, t[r]));
		return n;
	}
	getEventSourceById(e) {
		let t = this.getCurrentData(), n = t.eventSources;
		e = String(e);
		for (let r in n) if (n[r].publicId === e) return new Vh(t, n[r]);
		return null;
	}
	addEventSource(e) {
		let t = this.getCurrentData();
		if (e instanceof Vh) return t.eventSources[e.internalEventSource.sourceId] || this.dispatch({
			type: "ADD_EVENT_SOURCES",
			sources: [e.internalEventSource]
		}), e;
		let n = zg(e, t);
		return n ? (this.dispatch({
			type: "ADD_EVENT_SOURCES",
			sources: [n]
		}), new Vh(t, n)) : null;
	}
	removeAllEventSources() {
		this.dispatch({ type: "REMOVE_ALL_EVENT_SOURCES" });
	}
	refetchEvents() {
		this.dispatch({
			type: "FETCH_EVENT_SOURCES",
			isRefetch: !0
		});
	}
	scrollToTime(e) {
		let t = z(e);
		t && this.trigger("_timeScrollRequest", t);
	}
	getButtonState() {
		let e = this.getCurrentData(), { toolbarProps: t } = e, n = e.calendarOptions, r = n.buttons || {}, i = e.viewSpecs, a = e.viewSpec.singleUnit, o = [a ? Vy(a, n) : "", a], s = {
			today: {
				text: n.todayText,
				hint: em(n.todayHint, o, n.todayText),
				isDisabled: !t.isTodayEnabled
			},
			prev: {
				text: n.prevText,
				hint: em(n.prevHint, o, n.prevText),
				isDisabled: !t.isPrevEnabled
			},
			next: {
				text: n.nextText,
				hint: em(n.nextHint, o, n.nextText),
				isDisabled: !t.isNextEnabled
			},
			prevYear: {
				text: n.prevYearText,
				hint: em(n.prevHint, [n.yearText, "year"], n.prevYearText),
				isDisabled: !1
			},
			nextYear: {
				text: n.prevYearText,
				hint: em(n.nextHint, [n.yearText, "year"], n.nextYearText),
				isDisabled: !1
			}
		};
		for (let e in i) {
			let t = i[e], { singleUnit: a } = t, o = t.optionDefaults.buttonTextKey, c = r[e]?.text || (o ? n[o] : "") || (a ? Vy(a, n) : "") || e;
			s[e] = {
				text: c,
				hint: em(n.viewHint, [c, e], c)
			};
		}
		return s;
	}
};
function Vy(e, t) {
	return t[e + "TextLong"] || t[e + "Text"];
}
var Hy = class extends T {
	constructor() {
		super(...arguments), this.state = { forPrint: !1 }, this.handleBeforePrint = () => {
			$_(() => {
				this.setState({ forPrint: !0 });
			});
		}, this.handleAfterPrint = () => {
			this.setState({ forPrint: !1 });
		};
	}
	render() {
		return this.props?.children(this.state.forPrint);
	}
	componentDidMount() {
		let { props: e } = this, { emitter: t } = e;
		t.on("_beforeprint", this.handleBeforePrint), t.on("_afterprint", this.handleAfterPrint);
	}
	componentWillUnmount() {
		let { props: e } = this, { emitter: t } = e;
		t.off("_beforeprint", this.handleBeforePrint), t.off("_afterprint", this.handleAfterPrint);
	}
};
function Uy(e, t) {
	let n = e.borderlessX ?? e.borderless, r = e.borderlessTop ?? e.borderless, i = e.borderlessBottom ?? e.borderless, a = {
		borderlessX: !!n,
		borderlessTop: !!r,
		borderlessBottom: !!i
	};
	return V(Z(e.class, a), Z(e.className, a), B.borderBoxRoot, B.isolate, B.flexCol, t ? B.calendarPrintRoot : B.calendarScreenRoot);
}
var Wy = class extends J {
	render() {
		let { contentGenerator: e, className: t } = this.props;
		if (e) return D(X, {
			tag: "span",
			style: { display: "contents" },
			attrs: { "aria-hidden": !0 },
			renderProps: {},
			generatorName: void 0,
			customGenerator: e
		});
		if (t !== void 0) return D("span", {
			"aria-hidden": !0,
			className: t
		});
	}
}, Gy = class extends J {
	render() {
		let { props: e } = this, { options: t } = this.context, n = e.widgetGroups.map((e) => this.renderWidgetGroup(e));
		return x("div", { className: Z(t.toolbarSectionClass, { name: e.name }) }, ...n);
	}
	renderWidgetGroup(e) {
		let { props: t, context: n } = this, { options: r } = n, i = [], a = !0, o = !0;
		for (let t of e) {
			let { name: e, isView: n } = t;
			e === "title" ? a = !1 : n || (o = !1);
		}
		let s = a && o && e.length > 1;
		for (let n of e) {
			let { name: c, customElement: l, buttonHint: u } = n;
			if (c === "title") i.push(D("div", {
				role: "heading",
				"aria-level": r.headingLevel,
				id: t.titleId,
				className: V(r.toolbarTitleClass),
				children: t.title
			}));
			else if (l) i.push(D(X, {
				tag: "span",
				style: { display: "contents" },
				renderProps: {},
				generatorName: void 0,
				customGenerator: l
			}));
			else {
				let l = c === t.selectedButton, d = !t.isTodayEnabled && c === "today" || !t.isPrevEnabled && c === "prev" || !t.isNextEnabled && c === "next", f = n.buttonDisplay ?? r.buttonDisplay;
				f === "auto" && (f = n.buttonIconContent || n.buttonIconClass ? "icon" : "text");
				let p;
				f !== "text" && (p = D(Wy, {
					className: n.buttonIconClass,
					contentGenerator: n.buttonIconContent
				}));
				let m = e.length > 1 && a, h = m ? { hasSelection: o } : null, g = {
					name: c,
					text: n.buttonText,
					isPrimary: n.buttonIsPrimary,
					isSelected: l,
					isDisabled: d,
					isIconOnly: f === "icon",
					buttonGroup: h
				};
				i.push(D(X, {
					tag: "button",
					attrs: {
						type: "button",
						disabled: d,
						...s ? {
							role: "tab",
							"aria-selected": l
						} : n.isView ? { "aria-pressed": l } : {},
						"aria-label": typeof u == "function" ? u(t.navUnit) : u,
						onClick: n.buttonClick
					},
					className: V(Z(r.buttonClass, g), !d && B.cursorPointer, m && V(l ? B.z1 : B.z0, B.focusZ2)),
					renderProps: g,
					generatorName: void 0,
					classNameGenerator: n.buttonClass,
					didMount: n.buttonDidMount,
					willUnmount: n.buttonWillUnmount,
					children: () => f === "text" ? n.buttonText : f === "icon" ? p : f === "icon-text" ? D(w, { children: [p, n.buttonText] }) : D(w, { children: [n.buttonText, p] })
				}));
			}
		}
		return i.length > 1 ? x("div", {
			role: s ? "tablist" : void 0,
			"aria-label": s ? r.viewChangeHint : void 0,
			className: V(Z(r.buttonGroupClass, { hasSelection: o }), B.isolate)
		}, ...i) : i[0];
	}
}, Ky = class extends J {
	render() {
		let { props: e } = this, t = this.context.options, { sectionWidgets: n } = e.model, { borderlessX: r, borderlessTop: i, borderlessBottom: a } = rm(t);
		return D("div", {
			className: V(Z(e.isHeader ? t.headerToolbarClass : t.footerToolbarClass, {
				borderlessX: r,
				borderlessTop: i,
				borderlessBottom: a
			}), Z(t.toolbarClass, {
				borderlessX: r,
				borderlessTop: i,
				borderlessBottom: a
			})),
			children: [
				this.renderSection("start", n.start),
				this.renderSection("center", n.center),
				this.renderSection("end", n.end)
			]
		});
	}
	renderSection(e, t) {
		let { props: n } = this;
		return D(Gy, {
			name: e,
			widgetGroups: t,
			title: n.title,
			titleId: n.titleId,
			navUnit: n.navUnit,
			selectedButton: n.selectedButton,
			isTodayEnabled: n.isTodayEnabled,
			isPrevEnabled: n.isPrevEnabled,
			isNextEnabled: n.isNextEnabled
		}, e);
	}
}, qy = class extends Hv {
	constructor(e) {
		super(e), this.handleSegClick = (e, t) => {
			let { component: n } = this, { context: r } = n, i = Yh(t);
			i && n.isValidSegDownEl(e.target) && r.emitter.trigger("eventClick", {
				el: t,
				event: new K(n.context, i.def, i.instance),
				jsEvent: e,
				view: r.viewApi
			});
		}, this.destroy = Ip(e.el, "click", `.${B.internalEvent}`, this.handleSegClick);
	}
}, Jy = class extends Hv {
	constructor(e) {
		super(e), this.handleEventElRemove = (e) => {
			e === this.currentSegEl && this.handleSegLeave(null, this.currentSegEl);
		}, this.handleSegEnter = (e, t) => {
			Yh(t) && (this.currentSegEl = t, this.triggerEvent("eventMouseEnter", e, t));
		}, this.handleSegLeave = (e, t) => {
			this.currentSegEl && (this.currentSegEl = null, this.triggerEvent("eventMouseLeave", e, t));
		}, this.removeHoverListeners = Lp(e.el, `.${B.internalEvent}`, this.handleSegEnter, this.handleSegLeave);
	}
	destroy() {
		this.removeHoverListeners();
	}
	triggerEvent(e, t, n) {
		let { component: r } = this, { context: i } = r, a = Yh(n);
		(!t || r.isValidSegDownEl(t.target)) && i.emitter.trigger(e, {
			el: n,
			event: new K(i, a.def, a.instance),
			jsEvent: t,
			view: i.viewApi
		});
	}
}, Yy = class extends E_ {
	constructor() {
		super(...arguments), this.buildViewContext = q(T_), this.buildViewPropTransformers = q(Xy), this.interactionsStore = {}, this.calendarInteractions = [], this.registerInteractiveComponent = (e, t) => {
			let n = Uv(e, t), r = [qy, Jy];
			t.disableHits || (r = r.concat(this.props.pluginHooks.componentInteractions));
			let i = r.map((e) => new e(n));
			this.interactionsStore[e.uid] = i, Gv[e.uid] = n;
		}, this.unregisterInteractiveComponent = (e) => {
			let t = this.interactionsStore[e.uid];
			if (t) {
				for (let e of t) e.destroy();
				delete this.interactionsStore[e.uid];
			}
			delete Gv[e.uid];
		};
	}
	get viewTitleId() {
		return this.props.baseId + "title";
	}
	render() {
		let { props: e } = this, { toolbarConfig: t, options: n } = e, r, i = !1, a;
		e.forPrint || F_(n) || (n.height == null ? n.contentHeight == null ? a = Math.max(n.aspectRatio, .5) : r = n.contentHeight : i = !0);
		let o = this.buildViewContext(e.viewSpec, e.viewApi, e.options, e.dateProfileGenerator, e.dateEnv, e.nowManager, e.pluginHooks, e.dispatch, e.getCurrentData, e.emitter, e.calendarApi, e.baseId, this.registerInteractiveComponent, this.unregisterInteractiveComponent);
		return D(w_.Provider, {
			value: o,
			children: [
				t.header && D(Ky, {
					model: t.header,
					isHeader: !0,
					titleId: this.viewTitleId,
					...e.toolbarProps
				}),
				D("div", {
					className: V(B.flexCol, B.rel, B.overflowAnchorNone, B.minHeight0, i && B.liquid),
					style: {
						height: r,
						aspectRatio: a == null ? void 0 : String(a)
					},
					children: [this.renderView(V((i || r) && B.liquid, a != null && B.fill, B.internalView)), this.buildAppendContent()]
				}),
				t.footer && D(Ky, {
					model: t.footer,
					isHeader: !1,
					...e.toolbarProps
				})
			]
		});
	}
	renderView(e) {
		let { props: t } = this, { pluginHooks: n, viewSpec: r, toolbarConfig: i, toolbarProps: a } = t, o = {
			className: e,
			dateProfile: t.dateProfile,
			businessHours: t.businessHours,
			eventStore: t.renderableEventStore,
			eventUiBases: t.eventUiBases,
			dateSelection: t.dateSelection,
			eventSelection: t.eventSelection,
			eventDrag: t.eventDrag,
			eventResize: t.eventResize,
			forPrint: t.forPrint,
			labelId: i.header && i.header.hasTitle ? this.viewTitleId : void 0,
			labelStr: i.header && i.header.hasTitle ? void 0 : a.title
		}, s = this.buildViewPropTransformers(n.viewPropsTransformers), c = {
			...t,
			toolbarProps: a,
			forPrint: t.forPrint
		};
		for (let e of s) Object.assign(o, e.transform(o, c));
		let l = r.component;
		return D(l, { ...o });
	}
	buildAppendContent() {
		let { props: e } = this;
		return D(w, { children: e.pluginHooks.viewContainerAppends.map((t, n) => D(w, { children: t(e) }, n)) });
	}
	componentDidMount() {
		let { props: e } = this;
		this.calendarInteractions = e.pluginHooks.calendarInteractions.map((t) => new t(e));
		let { propSetHandlers: t } = e.pluginHooks;
		for (let n in t) t[n](e[n], e);
		for (let t of e.pluginHooks.contextInit) t(e);
	}
	componentDidUpdate(e) {
		let { props: t } = this, { propSetHandlers: n } = t.pluginHooks;
		for (let r in n) t[r] !== e[r] && n[r](t[r], t);
	}
	componentWillUnmount() {
		let { props: e } = this;
		for (let e of this.calendarInteractions) e.destroy();
		this.calendarInteractions = [], e.emitter.trigger("_unmount");
	}
};
function Xy(e) {
	return e.map((e) => new e());
}
// ../../../../../localhost/Pushword/packages/newsletter/node_modules/fullcalendar/chunks/599bf98e.js
var Zy = class extends By {
	constructor(e, t = {}) {
		super(), this.baseId = `fc:${Up()}:`, this.isRendering = !1, this.isRendered = !1, this.customContentRenderId = 0, this.currentClassName = "", this.currentColorScheme = "", this.handleDataChange = (e, t) => {
			this.currentData = e;
			let n = !1;
			for (let e of t) if (e.type === "SET_EVENT_DRAG" || e.type === "UNSET_EVENT_DRAG" || e.type === "SET_EVENT_RESIZE" || e.type === "UNSET_EVENT_RESIZE" || e.type === "MERGE_EVENTS") {
				n = !0;
				break;
			}
			this.renderRunner.request(n ? void 0 : e.calendarOptions.rerenderDelay);
		}, this.handleRenderRequest = () => {
			if (this.isRendering) {
				let { currentData: e } = this;
				this.isRendered = !0, mt(() => {
					this.vdomRoot.render(D(w, { children: D(M_.Provider, {
						value: this.customContentRenderId,
						children: D(Hy, {
							emitter: e.emitter,
							children: (t) => {
								let n = e.calendarOptions, r = n.direction === "rtl", i = Uy(n, t);
								return this.setIsRtl(r), this.setClassName(i), this.setHeight(n.height), this.setColorScheme(n.colorScheme || ""), D(Yy, {
									...e,
									forPrint: t,
									baseId: this.baseId
								});
							}
						})
					}) }));
				});
			} else this.isRendered && (this.isRendered = !1, this.vdomRoot.unmount(), this.setIsRtl(!1), this.setClassName(""), this.setHeight(""), this.setColorScheme(""));
		}, this.el = e, this.vdomRoot = ht(e), this.renderRunner = new _v(this.handleRenderRequest), this.dataManager = new Oy({
			calendarApi: this,
			onDataChange: this.handleDataChange
		}), this.currentData = this.dataManager.update(t), this.dataManager.mount();
	}
	render() {
		let e = this.isRendering;
		this.dataManager.mount(), e ? this.customContentRenderId += 1 : this.isRendering = !0, this.renderRunner.request();
	}
	destroy() {
		this.isRendering && (this.isRendering = !1, this.renderRunner.request()), this.dataManager.destroy();
	}
	batchRendering(e) {
		this.renderRunner.pause("batchRendering"), e(), this.renderRunner.resume("batchRendering");
	}
	pauseRendering() {
		this.renderRunner.pause("pauseRendering");
	}
	resumeRendering() {
		this.renderRunner.resume("pauseRendering", !0);
	}
	resetOptions(e, t) {
		this.currentDataManager.resetOptions(e, t);
	}
	setClassName(e) {
		if (e !== this.currentClassName) {
			let { classList: t } = this.el;
			for (let e of this.currentClassName.split(" ")) e && t.remove(e);
			for (let n of e.split(" ")) n && t.add(n);
			this.currentClassName = e;
		}
	}
	setHeight(e) {
		x_(this.el, "height", e);
	}
	setColorScheme(e) {
		e !== this.currentColorScheme && (e ? this.el.dataset.colorScheme = e : delete this.el.dataset.colorScheme, this.currentColorScheme = e);
	}
	setIsRtl(e) {
		e ? this.el.dir = "rtl" : this.el.removeAttribute("dir");
	}
}, Qy = class {
	constructor(e, t) {
		this.emitter = new a_();
	}
	destroy() {}
	setMirrorIsVisible(e) {}
	setMirrorNeedsRevert(e) {}
	setAutoScrollEnabled(e) {}
};
function $y(e, t, n) {
	let { instances: r } = e.mutatedEvents;
	for (let e in r) if (!Bf(t.validRange, r[e].range)) return !1;
	return tb({ eventDrag: e }, n);
}
function eb(e, t, n) {
	return Bf(t.validRange, e.range) ? tb({ dateSelection: e }, n) : !1;
}
function tb(e, t) {
	let n = t.getCurrentData(), r = {
		businessHours: n.businessHours,
		dateSelection: "",
		eventStore: n.eventStore,
		eventUiBases: n.eventUiBases,
		eventSelection: "",
		eventDrag: null,
		eventResize: null,
		...e
	};
	return (t.pluginHooks.isPropsValid || nb)(r, t);
}
function nb(e, t, n = {}, r) {
	return !(e.eventDrag && !rb(e, t, n, r) || e.dateSelection && !ib(e, t, n, r));
}
function rb(e, t, n, r) {
	let i = t.getCurrentData(), a = e.eventDrag, o = a.mutatedEvents, s = o.defs, c = o.instances, l = Xh(s, a.isEvent ? e.eventUiBases : {
		base: i.selectionConfig,
		bySource: {}
	});
	r && (l = um(l, r));
	let u = Bv(e.eventStore, a.affectedEvents.instances), d = u.defs, f = u.instances, p = Xh(d, e.eventUiBases);
	for (let r in c) {
		let o = c[r], m = o.range, h = l[o.defId], g = s[o.defId];
		if (!ab(h.constraints, m, u, e.businessHours, t)) return !1;
		let { eventOverlap: _ } = t.options, v = typeof _ == "function" ? _ : null;
		for (let e in f) {
			let n = f[e];
			if (zm(m, n.range, t.dateEnv) && (p[n.defId].overlap === !1 && a.isEvent || h.overlap === !1 || v && !v(new K(t, d[n.defId], n), new K(t, g, o)))) return !1;
		}
		let y = i.eventStore;
		for (let e of h.allows) {
			let i = {
				...n,
				range: o.range,
				allDay: g.allDay
			}, a = y.defs[g.defId], s = y.instances[r], c;
			if (c = a ? new K(t, a, s) : new K(t, g), !e(Nh(i, t), c)) return !1;
		}
	}
	return !0;
}
function ib(e, t, n, r) {
	let i = e.eventStore, a = i.defs, o = i.instances, s = e.dateSelection, c = Mm(s.range.start, s.range.end, s.instantStartMs, s.instantEndMs), { selectionConfig: l } = t.getCurrentData();
	if (r && (l = r(l)), !ab(l.constraints, c, i, e.businessHours, t)) return !1;
	let { selectOverlap: u } = t.options, d = typeof u == "function" ? u : null;
	for (let e in o) {
		let n = o[e];
		if (zm(c, n.range, t.dateEnv) && (l.overlap === !1 || d && !d(new K(t, a[n.defId], n), null))) return !1;
	}
	for (let e of l.allows) if (!e(Nh({
		...n,
		...s
	}, t), null)) return !1;
	return !0;
}
function ab(e, t, n, r, i) {
	for (let a of e) if (!cb(ob(a, t, n, r, i), t, i)) return !1;
	return !0;
}
function ob(e, t, n, r, i) {
	return e === "businessHours" ? sb(Jm(r, t, i)) : typeof e == "string" ? sb(nh(n, (t) => t.groupId === e)) : typeof e == "object" && e ? sb(Jm(e, t, i)) : [];
}
function sb(e) {
	let { instances: t } = e, n = [];
	for (let e in t) n.push(t[e].range);
	return n;
}
function cb(e, t, n) {
	for (let r of e) if (Bm(r, t, n.dateEnv)) return !0;
	return !1;
}
// ../../../../../localhost/Pushword/packages/newsletter/node_modules/fullcalendar/chunks/8d512140.js
function lb(e, t) {
	return e.left >= t.left && e.left < t.right && e.top >= t.top && e.top < t.bottom;
}
function ub(e, t) {
	let n = {
		left: Math.max(e.left, t.left),
		right: Math.min(e.right, t.right),
		top: Math.max(e.top, t.top),
		bottom: Math.min(e.bottom, t.bottom)
	};
	return n.left < n.right && n.top < n.bottom && n;
}
function db(e, t) {
	return {
		left: Math.min(Math.max(e.left, t.left), t.right),
		top: Math.min(Math.max(e.top, t.top), t.bottom)
	};
}
function fb(e) {
	return {
		left: (e.left + e.right) / 2,
		top: (e.top + e.bottom) / 2
	};
}
function pb(e, t) {
	return {
		left: e.left - t.left,
		top: e.top - t.top
	};
}
function mb(e, t = !1) {
	let n = window.getComputedStyle(e), r = parseInt(n.borderLeftWidth, 10) || 0, i = parseInt(n.borderRightWidth, 10) || 0, a = parseInt(n.borderTopWidth, 10) || 0, o = parseInt(n.borderBottomWidth, 10) || 0, s = yb(e), c = s.y - r - i, l = {
		borderLeft: r,
		borderRight: i,
		borderTop: a,
		borderBottom: o,
		scrollbarBottom: s.x - a - o,
		scrollbarLeft: 0,
		scrollbarRight: 0
	};
	return n.direction === "rtl" ? l.scrollbarLeft = c : l.scrollbarRight = c, t && (l.paddingLeft = parseInt(n.paddingLeft, 10) || 0, l.paddingRight = parseInt(n.paddingRight, 10) || 0, l.paddingTop = parseInt(n.paddingTop, 10) || 0, l.paddingBottom = parseInt(n.paddingBottom, 10) || 0), l;
}
function hb(e, t = !1, n) {
	let r = n ? e.getBoundingClientRect() : gb(e), i = mb(e, t), a = {
		left: r.left + i.borderLeft + i.scrollbarLeft,
		right: r.right - i.borderRight - i.scrollbarRight,
		top: r.top + i.borderTop,
		bottom: r.bottom - i.borderBottom - i.scrollbarBottom
	};
	return t && (a.left += i.paddingLeft, a.right -= i.paddingRight, a.top += i.paddingTop, a.bottom -= i.paddingBottom), a;
}
function gb(e) {
	let t = e.getBoundingClientRect();
	return {
		left: t.left + window.scrollX,
		top: t.top + window.scrollY,
		right: t.right + window.scrollX,
		bottom: t.bottom + window.scrollY
	};
}
function _b(e) {
	let t = vb(e), n = e.getBoundingClientRect();
	for (let e of t) {
		let t = ub(n, e.getBoundingClientRect());
		if (t) n = t;
		else return null;
	}
	return n;
}
function vb(e) {
	let t = [];
	for (; e instanceof HTMLElement;) {
		let n = window.getComputedStyle(e);
		if (n.position === "fixed") break;
		/(auto|scroll)/.test(n.overflow + n.overflowY + n.overflowX) && t.push(e), e = e.parentNode;
	}
	return t;
}
function yb(e) {
	return {
		x: e.offsetHeight - e.clientHeight,
		y: e.offsetWidth - e.clientWidth
	};
}
// ../../../../../localhost/Pushword/packages/newsletter/node_modules/fullcalendar/chunks/b6cf94a9.js
g_.touchMouseIgnoreWait = 500;
var bb = 0, xb = 0, Sb = !1, Cb = class {
	constructor(e) {
		this.subjectEl = null, this.selector = "", this.handleSelector = "", this.shouldIgnoreMove = !1, this.shouldWatchScroll = !0, this.isDragging = !1, this.isTouchDragging = !1, this.wasTouchScroll = !1, this.handleMouseDown = (e) => {
			if (!this.shouldIgnoreMouse() && wb(e) && this.tryStart(e)) {
				let t = this.createEventFromMouse(e, !0);
				this.emitter.trigger("pointerdown", t), this.initScrollWatch(t), this.shouldIgnoreMove || document.addEventListener("mousemove", this.handleMouseMove), document.addEventListener("mouseup", this.handleMouseUp);
			}
		}, this.handleMouseMove = (e) => {
			let t = this.createEventFromMouse(e);
			this.recordCoords(t), this.emitter.trigger("pointermove", t);
		}, this.handleMouseUp = (e) => {
			document.removeEventListener("mousemove", this.handleMouseMove), document.removeEventListener("mouseup", this.handleMouseUp), this.emitter.trigger("pointerup", this.createEventFromMouse(e)), this.cleanup();
		}, this.handleTouchStart = (e) => {
			if (this.tryStart(e)) {
				this.isTouchDragging = !0;
				let t = this.createEventFromTouch(e, !0);
				this.emitter.trigger("pointerdown", t), this.initScrollWatch(t);
				let n = e.target;
				this.shouldIgnoreMove || n.addEventListener("touchmove", this.handleTouchMove), n.addEventListener("touchend", this.handleTouchEnd), n.addEventListener("touchcancel", this.handleTouchEnd), window.addEventListener("scroll", this.handleTouchScroll, !0);
			}
		}, this.handleTouchMove = (e) => {
			if (this.isDragging) {
				let t = this.createEventFromTouch(e);
				this.recordCoords(t), this.emitter.trigger("pointermove", t);
			}
		}, this.handleTouchEnd = (e) => {
			if (this.isDragging) {
				let t = e.target;
				t.removeEventListener("touchmove", this.handleTouchMove), t.removeEventListener("touchend", this.handleTouchEnd), t.removeEventListener("touchcancel", this.handleTouchEnd), window.removeEventListener("scroll", this.handleTouchScroll, !0), this.emitter.trigger("pointerup", this.createEventFromTouch(e)), this.cleanup(), this.isTouchDragging = !1, Tb();
			}
		}, this.handleTouchScroll = () => {
			this.wasTouchScroll = !0;
		}, this.handleScroll = (e) => {
			if (!this.shouldIgnoreMove) {
				let t = window.scrollX - this.prevScrollX + this.prevPageX, n = window.scrollY - this.prevScrollY + this.prevPageY;
				this.emitter.trigger("pointermove", {
					origEvent: e,
					isTouch: this.isTouchDragging,
					subjectEl: this.subjectEl,
					pageX: t,
					pageY: n,
					deltaX: t - this.origPageX,
					deltaY: n - this.origPageY
				});
			}
		}, this.containerEl = e, this.emitter = new a_(), e.addEventListener("mousedown", this.handleMouseDown), e.addEventListener("touchstart", this.handleTouchStart, { passive: !0 }), Eb();
	}
	destroy() {
		this.containerEl.removeEventListener("mousedown", this.handleMouseDown), this.containerEl.removeEventListener("touchstart", this.handleTouchStart, { passive: !0 }), Db();
	}
	cancel() {
		this.isDragging && this.cleanup();
	}
	tryStart(e) {
		let t = this.querySubjectEl(e), n = e.target;
		return t && (!this.handleSelector || n.closest(this.handleSelector)) ? (this.subjectEl = t, this.isDragging = !0, this.wasTouchScroll = !1, !0) : !1;
	}
	cleanup() {
		Sb = !1, this.isDragging = !1, this.subjectEl = null, this.destroyScrollWatch();
	}
	querySubjectEl(e) {
		return this.selector ? e.target.closest(this.selector) : this.containerEl;
	}
	shouldIgnoreMouse() {
		return bb || this.isTouchDragging;
	}
	cancelTouchScroll() {
		this.isDragging && (Sb = !0);
	}
	initScrollWatch(e) {
		this.shouldWatchScroll && (this.recordCoords(e), window.addEventListener("scroll", this.handleScroll, !0));
	}
	recordCoords(e) {
		this.shouldWatchScroll && (this.prevPageX = e.pageX, this.prevPageY = e.pageY, this.prevScrollX = window.scrollX, this.prevScrollY = window.scrollY);
	}
	destroyScrollWatch() {
		this.shouldWatchScroll && window.removeEventListener("scroll", this.handleScroll, !0);
	}
	createEventFromMouse(e, t) {
		let n = 0, r = 0;
		return t ? (this.origPageX = e.pageX, this.origPageY = e.pageY) : (n = e.pageX - this.origPageX, r = e.pageY - this.origPageY), {
			origEvent: e,
			isTouch: !1,
			subjectEl: this.subjectEl,
			pageX: e.pageX,
			pageY: e.pageY,
			deltaX: n,
			deltaY: r
		};
	}
	createEventFromTouch(e, t) {
		let n = e.touches, r, i, a = 0, o = 0;
		return n && n.length ? (r = n[0].pageX, i = n[0].pageY) : (r = e.pageX, i = e.pageY), t ? (this.origPageX = r, this.origPageY = i) : (a = r - this.origPageX, o = i - this.origPageY), {
			origEvent: e,
			isTouch: !0,
			subjectEl: this.subjectEl,
			pageX: r,
			pageY: i,
			deltaX: a,
			deltaY: o
		};
	}
};
function wb(e) {
	return e.button === 0 && !e.ctrlKey;
}
function Tb() {
	bb += 1, setTimeout(() => {
		--bb;
	}, g_.touchMouseIgnoreWait);
}
function Eb() {
	xb += 1, xb === 1 && window.addEventListener("touchmove", Ob, { passive: !1 });
}
function Db() {
	--xb, xb || window.removeEventListener("touchmove", Ob, { passive: !1 });
}
function Ob(e) {
	Sb && e.preventDefault();
}
var kb = 1e3, Ab = class {
	constructor() {
		this.isVisible = !1, this.sourceEl = null, this.mirrorEl = null, this.sourceElRect = null, this.parentNode = document.body, this.zIndex = 9999, this.revertDuration = 0, this.releaseRevertSettle = () => {}, this.colorScheme = "";
	}
	start(e, t, n) {
		this.sourceEl = e, this.sourceElRect = this.sourceEl.getBoundingClientRect(), this.origScreenX = t - window.scrollX, this.origScreenY = n - window.scrollY, this.deltaX = 0, this.deltaY = 0, this.updateElPosition();
	}
	handleMove(e, t) {
		this.deltaX = e - window.scrollX - this.origScreenX, this.deltaY = t - window.scrollY - this.origScreenY, this.updateElPosition();
	}
	setIsVisible(e) {
		e ? this.isVisible || (this.mirrorEl && this.mirrorEl.style.setProperty("display", "", "important"), this.isVisible = e, this.updateElPosition()) : this.isVisible &&= (this.mirrorEl && this.mirrorEl.style.setProperty("display", "none", "important"), e);
	}
	stop(e, t) {
		let n = () => {
			this.cleanup(), t();
		};
		e && this.mirrorEl && this.isVisible && this.revertDuration && (this.deltaX || this.deltaY) ? this.doRevertAnimation(n, this.revertDuration) : m_(n, 0);
	}
	doRevertAnimation(e, t) {
		let n = this.mirrorEl, r = this.sourceEl.getBoundingClientRect();
		n.style.transition = "top " + t + "ms,left " + t + "ms", b_(n, {
			left: r.left,
			top: r.top
		});
		let i = u_(), a = setTimeout(i, t + kb);
		this.releaseRevertSettle = () => {
			clearTimeout(a), i();
		}, zp(n, () => {
			n.style.transition = "";
			try {
				e();
			} finally {
				this.releaseRevertSettle();
			}
		});
	}
	cleanup() {
		this.releaseRevertSettle(), this.mirrorEl &&= (this.mirrorEl.remove(), null), this.sourceEl = null;
	}
	updateElPosition() {
		this.sourceEl && this.isVisible && b_(this.getMirrorEl(), {
			left: this.sourceElRect.left + this.deltaX,
			top: this.sourceElRect.top + this.deltaY
		});
	}
	getMirrorEl() {
		let e = this.sourceElRect, t = this.mirrorEl;
		return t || (t = this.mirrorEl = this.sourceEl.cloneNode(!0), t.style.userSelect = "none", t.style.webkitUserSelect = "none", t.style.pointerEvents = "none", this.colorScheme && t.setAttribute("data-color-scheme", this.colorScheme), t.classList.add(B.borderBoxRoot), b_(t, {
			position: "fixed",
			zIndex: this.zIndex,
			visibility: "",
			width: e.right - e.left,
			height: e.bottom - e.top,
			right: "auto",
			bottom: "auto",
			margin: 0
		}), this.parentNode.appendChild(t)), t;
	}
}, jb = class {
	getMaxScrollTop() {
		return this.getScrollHeight() - this.getClientHeight();
	}
	getMaxScrollLeft() {
		return this.getScrollWidth() - this.getClientWidth();
	}
	canScrollVertically() {
		return this.getMaxScrollTop() > 0;
	}
	canScrollHorizontally() {
		return this.getMaxScrollLeft() > 0;
	}
	canScrollUp() {
		return this.getScrollTop() > 0;
	}
	canScrollDown() {
		return this.getScrollTop() < this.getMaxScrollTop();
	}
	canScrollLeft() {
		return this.getScrollLeft() > 0;
	}
	canScrollRight() {
		return this.getScrollLeft() < this.getMaxScrollLeft();
	}
}, Mb = class extends jb {
	constructor(e) {
		super(), this.el = e;
	}
	getScrollTop() {
		return this.el.scrollTop;
	}
	getScrollLeft() {
		return this.el.scrollLeft;
	}
	setScrollTop(e) {
		this.el.scrollTop = e;
	}
	setScrollLeft(e) {
		this.el.scrollLeft = e;
	}
	getScrollWidth() {
		return this.el.scrollWidth;
	}
	getScrollHeight() {
		return this.el.scrollHeight;
	}
	getClientHeight() {
		return this.el.clientHeight;
	}
	getClientWidth() {
		return this.el.clientWidth;
	}
}, Nb = class extends jb {
	getScrollTop() {
		return window.scrollY;
	}
	getScrollLeft() {
		return window.scrollX;
	}
	setScrollTop(e) {
		window.scroll(window.scrollX, e);
	}
	setScrollLeft(e) {
		window.scroll(e, window.scrollY);
	}
	getScrollWidth() {
		return document.documentElement.scrollWidth;
	}
	getScrollHeight() {
		return document.documentElement.scrollHeight;
	}
	getClientHeight() {
		return document.documentElement.clientHeight;
	}
	getClientWidth() {
		return document.documentElement.clientWidth;
	}
}, Pb = class extends jb {
	constructor(e, t) {
		super(), this.handleScroll = () => {
			this.scrollTop = this.scrollController.getScrollTop(), this.scrollLeft = this.scrollController.getScrollLeft(), this.handleScrollChange();
		}, this.scrollController = e, this.doesListening = t, this.scrollTop = this.origScrollTop = e.getScrollTop(), this.scrollLeft = this.origScrollLeft = e.getScrollLeft(), this.scrollWidth = e.getScrollWidth(), this.scrollHeight = e.getScrollHeight(), this.clientWidth = e.getClientWidth(), this.clientHeight = e.getClientHeight(), this.clientRect = this.computeClientRect(), this.doesListening && this.getEventTarget().addEventListener("scroll", this.handleScroll);
	}
	destroy() {
		this.doesListening && this.getEventTarget().removeEventListener("scroll", this.handleScroll);
	}
	getScrollTop() {
		return this.scrollTop;
	}
	getScrollLeft() {
		return this.scrollLeft;
	}
	setScrollTop(e) {
		this.scrollController.setScrollTop(e), this.doesListening || (this.scrollTop = Math.max(Math.min(e, this.getMaxScrollTop()), 0), this.handleScrollChange());
	}
	setScrollLeft(e) {
		this.scrollController.setScrollLeft(e), this.doesListening || (this.scrollLeft = Math.max(Math.min(e, this.getMaxScrollLeft()), 0), this.handleScrollChange());
	}
	getClientWidth() {
		return this.clientWidth;
	}
	getClientHeight() {
		return this.clientHeight;
	}
	getScrollWidth() {
		return this.scrollWidth;
	}
	getScrollHeight() {
		return this.scrollHeight;
	}
	handleScrollChange() {}
}, Fb = class extends Pb {
	constructor(e, t) {
		super(new Mb(e), t);
	}
	getEventTarget() {
		return this.scrollController.el;
	}
	computeClientRect() {
		return hb(this.scrollController.el);
	}
}, Ib = class extends Pb {
	constructor(e) {
		super(new Nb(), e);
	}
	getEventTarget() {
		return window;
	}
	computeClientRect() {
		return {
			left: this.scrollLeft,
			right: this.scrollLeft + this.clientWidth,
			top: this.scrollTop,
			bottom: this.scrollTop + this.clientHeight
		};
	}
	handleScrollChange() {
		this.clientRect = this.computeClientRect();
	}
}, Lb = typeof performance == "function" ? performance.now : Date.now, Rb = class {
	constructor() {
		this.isEnabled = !0, this.scrollQuery = [window, `.${B.internalScroller}`], this.edgeThreshold = 50, this.maxVelocity = 300, this.pointerScreenX = null, this.pointerScreenY = null, this.isAnimating = !1, this.scrollCaches = null, this.everMovedUp = !1, this.everMovedDown = !1, this.everMovedLeft = !1, this.everMovedRight = !1, this.animate = () => {
			if (this.isAnimating) {
				let e = this.computeBestEdge(this.pointerScreenX + window.scrollX, this.pointerScreenY + window.scrollY);
				if (e) {
					let t = Lb();
					this.handleSide(e, (t - this.msSinceRequest) / 1e3), this.requestAnimation(t);
				} else this.isAnimating = !1;
			}
		};
	}
	start(e, t, n) {
		this.isEnabled && (this.scrollCaches = this.buildCaches(n), this.pointerScreenX = null, this.pointerScreenY = null, this.everMovedUp = !1, this.everMovedDown = !1, this.everMovedLeft = !1, this.everMovedRight = !1, this.handleMove(e, t));
	}
	handleMove(e, t) {
		if (this.isEnabled) {
			let n = e - window.scrollX, r = t - window.scrollY, i = this.pointerScreenY === null ? 0 : r - this.pointerScreenY, a = this.pointerScreenX === null ? 0 : n - this.pointerScreenX;
			i < 0 ? this.everMovedUp = !0 : i > 0 && (this.everMovedDown = !0), a < 0 ? this.everMovedLeft = !0 : a > 0 && (this.everMovedRight = !0), this.pointerScreenX = n, this.pointerScreenY = r, this.isAnimating || (this.isAnimating = !0, this.requestAnimation(Lb()));
		}
	}
	stop() {
		if (this.isEnabled) {
			this.isAnimating = !1;
			for (let e of this.scrollCaches) e.destroy();
			this.scrollCaches = null;
		}
	}
	requestAnimation(e) {
		this.msSinceRequest = e, requestAnimationFrame(this.animate);
	}
	handleSide(e, t) {
		let { scrollCache: n } = e, { edgeThreshold: r } = this, i = r - e.distance, a = i * i / (r * r) * this.maxVelocity * t, o = 1;
		switch (e.name) {
			case "left": o = -1;
			case "right":
				n.setScrollLeft(n.getScrollLeft() + a * o);
				break;
			case "top": o = -1;
			case "bottom": n.setScrollTop(n.getScrollTop() + a * o);
		}
	}
	computeBestEdge(e, t) {
		let { edgeThreshold: n } = this, r = null, i = this.scrollCaches || [];
		for (let a of i) {
			let i = a.clientRect, o = e - i.left, s = i.right - e, c = t - i.top, l = i.bottom - t;
			o >= 0 && s >= 0 && c >= 0 && l >= 0 && (c <= n && this.everMovedUp && a.canScrollUp() && (!r || r.distance > c) && (r = {
				scrollCache: a,
				name: "top",
				distance: c
			}), l <= n && this.everMovedDown && a.canScrollDown() && (!r || r.distance > l) && (r = {
				scrollCache: a,
				name: "bottom",
				distance: l
			}), o <= n && this.everMovedLeft && a.canScrollLeft() && (!r || r.distance > o) && (r = {
				scrollCache: a,
				name: "left",
				distance: o
			}), s <= n && this.everMovedRight && a.canScrollRight() && (!r || r.distance > s) && (r = {
				scrollCache: a,
				name: "right",
				distance: s
			}));
		}
		return r;
	}
	buildCaches(e) {
		return this.queryScrollEls(e).map((e) => e === window ? new Ib(!1) : new Fb(e, !1));
	}
	queryScrollEls(e) {
		let t = [];
		for (let n of this.scrollQuery) typeof n == "object" ? t.push(n) : t.push(...Array.prototype.slice.call(e.getRootNode().querySelectorAll(n)));
		return t;
	}
}, zb = class extends Qy {
	constructor(e, t) {
		super(e), this.containerEl = e, this.delay = null, this.minDistance = 0, this.touchScrollAllowed = !0, this.mirrorNeedsRevert = !1, this.isInteracting = !1, this.isDragging = !1, this.isDelayEnded = !1, this.isDistanceSurpassed = !1, this.delayTimeoutId = null, this.onPointerDown = (e) => {
			this.isDragging || (this.isInteracting = !0, this.isDelayEnded = !1, this.isDistanceSurpassed = !1, this.emitter.trigger("pointerdown", e), this.isInteracting && (Kp(document.body), Jp(document.body), e.isTouch || e.origEvent.preventDefault(), this.mirror.setIsVisible(!1), this.mirror.start(e.subjectEl, e.pageX, e.pageY), this.startDelay(e), this.minDistance || this.handleDistanceSurpassed(e)));
		}, this.onPointerMove = (e) => {
			if (this.isInteracting) {
				if (this.emitter.trigger("pointermove", e), !this.isDistanceSurpassed) {
					let t = this.minDistance, n, { deltaX: r, deltaY: i } = e;
					n = r * r + i * i, n >= t * t && this.handleDistanceSurpassed(e);
				}
				this.isDragging && (e.origEvent.type !== "scroll" && (this.mirror.handleMove(e.pageX, e.pageY), this.autoScroller.handleMove(e.pageX, e.pageY)), this.emitter.trigger("dragmove", e));
			}
		}, this.onPointerUp = (e) => {
			this.isInteracting && (this.isInteracting = !1, qp(document.body), Yp(document.body), this.emitter.trigger("pointerup", e), this.isDragging && (this.autoScroller.stop(), this.tryStopDrag(e)), this.delayTimeoutId &&= (clearTimeout(this.delayTimeoutId), null));
		};
		let n = this.pointer = new Cb(e);
		n.emitter.on("pointerdown", this.onPointerDown), n.emitter.on("pointermove", this.onPointerMove), n.emitter.on("pointerup", this.onPointerUp), t && (n.selector = t), this.mirror = new Ab(), this.autoScroller = new Rb();
	}
	destroy() {
		this.pointer.destroy(), this.onPointerUp({});
	}
	startDelay(e) {
		typeof this.delay == "number" ? this.delayTimeoutId = setTimeout(() => {
			this.delayTimeoutId = null, this.handleDelayEnd(e);
		}, this.delay) : this.handleDelayEnd(e);
	}
	handleDelayEnd(e) {
		this.isDelayEnded = !0, this.tryStartDrag(e);
	}
	handleDistanceSurpassed(e) {
		this.isDistanceSurpassed = !0, this.tryStartDrag(e);
	}
	tryStartDrag(e) {
		this.isDelayEnded && this.isDistanceSurpassed && (!this.pointer.wasTouchScroll || this.touchScrollAllowed) && (this.isDragging = !0, this.mirrorNeedsRevert = !1, this.autoScroller.start(e.pageX, e.pageY, this.containerEl), this.emitter.trigger("dragstart", e), this.touchScrollAllowed === !1 && this.pointer.cancelTouchScroll());
	}
	tryStopDrag(e) {
		this.mirror.stop(this.mirrorNeedsRevert, this.stopDrag.bind(this, e));
	}
	stopDrag(e) {
		this.isDragging = !1, this.emitter.trigger("dragend", e);
	}
	cancel() {
		this.isInteracting && (this.isInteracting = !1, this.pointer.cancel());
	}
	setMirrorIsVisible(e) {
		this.mirror.setIsVisible(e);
	}
	setMirrorNeedsRevert(e) {
		this.mirrorNeedsRevert = e;
	}
	setAutoScrollEnabled(e) {
		this.autoScroller.isEnabled = e;
	}
}, Bb = class {
	constructor(e) {
		this.el = e, this.origRect = gb(e), this.isRtl = v_(e), this.scrollCaches = vb(e).map((e) => new Fb(e, !0));
	}
	destroy() {
		for (let e of this.scrollCaches) e.destroy();
	}
	computeLeft() {
		let e = this.origRect.left;
		for (let t of this.scrollCaches) e += t.origScrollLeft - t.getScrollLeft();
		return e;
	}
	computeTop() {
		let e = this.origRect.top;
		for (let t of this.scrollCaches) e += t.origScrollTop - t.getScrollTop();
		return e;
	}
	isWithinClipping(e, t) {
		let n = {
			left: e,
			top: t
		};
		for (let e of this.scrollCaches) if (!Vb(e.getEventTarget()) && !lb(n, e.clientRect)) return !1;
		return !0;
	}
};
function Vb(e) {
	let t = e.tagName;
	return t === "HTML" || t === "BODY";
}
var Hb = class {
	constructor(e, t) {
		this.useSubjectCenter = !1, this.requireInitial = !0, this.disablePointCheck = !1, this.initialHit = null, this.movingHit = null, this.finalHit = null, this.handlePointerDown = (e) => {
			let { dragging: t } = this;
			this.initialHit = null, this.movingHit = null, this.finalHit = null, this.prepareHits(), this.processFirstCoord(e), this.initialHit || !this.requireInitial ? this.emitter.trigger("pointerdown", e) : t.cancel();
		}, this.handleDragStart = (e) => {
			this.emitter.trigger("dragstart", e), this.handleMove(e, !0);
		}, this.handleDragMove = (e) => {
			this.emitter.trigger("dragmove", e), this.handleMove(e);
		}, this.handlePointerUp = (e) => {
			this.releaseHits(), this.emitter.trigger("pointerup", e);
		}, this.handleDragEnd = (e) => {
			this.movingHit && this.emitter.trigger("hitupdate", null, !0, e), this.finalHit = this.movingHit, this.movingHit = null, this.emitter.trigger("dragend", e);
		}, this.droppableStore = t, e.emitter.on("pointerdown", this.handlePointerDown), e.emitter.on("dragstart", this.handleDragStart), e.emitter.on("dragmove", this.handleDragMove), e.emitter.on("pointerup", this.handlePointerUp), e.emitter.on("dragend", this.handleDragEnd), this.dragging = e, this.emitter = new a_();
	}
	processFirstCoord(e) {
		let t = {
			left: e.pageX,
			top: e.pageY
		}, n = t, r = e.subjectEl, i;
		r instanceof HTMLElement && (i = gb(r), n = db(n, i));
		let a = this.initialHit = this.queryHitForOffset(n.left, n.top);
		if (a) {
			if (this.useSubjectCenter && i) {
				let e = ub(i, a.rect);
				e && (n = fb(e));
			}
			this.coordAdjust = pb(n, t);
		} else this.coordAdjust = {
			left: 0,
			top: 0
		};
	}
	handleMove(e, t) {
		let n = this.queryHitForOffset(e.pageX + this.coordAdjust.left, e.pageY + this.coordAdjust.top);
		(t || !Ub(this.movingHit, n)) && (this.movingHit = n, this.emitter.trigger("hitupdate", n, !1, e));
	}
	prepareHits() {
		this.offsetTrackers = um(this.droppableStore, (e) => (e.component.prepareHits(), new Bb(e.el)));
	}
	releaseHits() {
		let { offsetTrackers: e } = this;
		for (let t in e) e[t].destroy();
		this.offsetTrackers = {};
	}
	queryHitForOffset(e, t) {
		let { droppableStore: n, offsetTrackers: r } = this, i = null;
		for (let a in n) {
			let o = n[a].component, s = r[a];
			if (s && s.isWithinClipping(e, t)) {
				let n = s.computeLeft(), r = s.computeTop(), c = e - n, l = t - r, { origRect: u } = s, d = u.right - u.left, f = u.bottom - u.top;
				if (c >= 0 && c < d && l >= 0 && l < f) {
					let e = o.queryHit(s.isRtl, c, l, d, f);
					e && Bf(e.dateProfile.activeRange, e.dateSpan.range) && (this.disablePointCheck || s.el.contains(s.el.getRootNode().elementFromPoint(c + n - window.scrollX, l + r - window.scrollY))) && (!i || e.layer > i.layer) && (e.componentId = a, e.context = o.context, e.rect.left += n, e.rect.right += n, e.rect.top += r, e.rect.bottom += r, i = e);
				}
			}
		}
		return i;
	}
};
function Ub(e, t) {
	return !e && !t || !!e == !!t && Ch(e.dateSpan, t.dateSpan);
}
function Wb(e, t) {
	let n = {};
	for (let r of t.pluginHooks.datePointTransforms) Object.assign(n, r(e, t));
	return Object.assign(n, Gb(e, t.dateEnv)), n;
}
function Gb(e, t) {
	let n = Pm(e.range.start, e.instantStartMs, t, e.allDay);
	return {
		date: n.date,
		dateStr: n.dateStr,
		allDay: e.allDay
	};
}
var Kb = class extends Hv {
	constructor(e) {
		super(e), this.handlePointerDown = (e) => {
			let { dragging: t } = this, n = e.origEvent.target;
			this.component.context.emitter.hasHandlers("dateClick") && this.component.isValidDateDownEl(n) || t.cancel();
		}, this.handleDragEnd = (e) => {
			let { component: t } = this, { pointer: n } = this.dragging;
			if (!n.wasTouchScroll) {
				let { initialHit: n, finalHit: r } = this.hitDragging;
				if (n && r && Ub(n, r)) {
					let { context: r } = t, i = {
						...Wb(n.dateSpan, r),
						dayEl: n.getDayEl(),
						jsEvent: e.origEvent,
						view: r.viewApi || r.calendarApi.view
					};
					r.emitter.trigger("dateClick", i);
				}
			}
		}, this.dragging = new zb(e.el), this.dragging.autoScroller.isEnabled = !1;
		let t = this.hitDragging = new Hb(this.dragging, Wv(e));
		t.emitter.on("pointerdown", this.handlePointerDown), t.emitter.on("dragend", this.handleDragEnd);
	}
	destroy() {
		this.dragging.destroy();
	}
}, qb = class extends Hv {
	constructor(e) {
		super(e), this.dragSelection = null, this.handlePointerDown = (e) => {
			let { component: t, dragging: n } = this, { options: r } = t.context;
			r.selectable && t.isValidDateDownEl(e.origEvent.target) ? n.delay = e.isTouch ? Jb(t) : null : n.cancel();
		}, this.handleDragStart = (e) => {
			this.component.context.calendarApi.unselect(e);
		}, this.handleHitUpdate = (e, t) => {
			let { context: n } = this.component, r = null, i = !1;
			if (e) {
				let t = this.hitDragging.initialHit;
				(e.componentId !== t.componentId || !this.isHitComboAllowed || this.isHitComboAllowed(t, e)) && (r = Yb(t, e, n.pluginHooks.dateSelectionTransformers)), r && eb(r, e.dateProfile, n) || (i = !0, r = null);
			}
			r ? n.dispatch({
				type: "SELECT_DATES",
				selection: r
			}) : t || n.dispatch({ type: "UNSELECT_DATES" }), i ? Wp() : Gp(), t || (this.dragSelection = r);
		}, this.handlePointerUp = (e) => {
			this.dragSelection ? (jh(this.dragSelection, e, this.component.context), this.dragSelection = null) : this.component.context.emitter.trigger("_noDateSelect");
		};
		let { component: t } = e, { options: n } = t.context, r = this.dragging = new zb(e.el);
		r.touchScrollAllowed = !1, r.minDistance = n.selectMinDistance || 0, r.autoScroller.isEnabled = n.dragScroll;
		let i = this.hitDragging = new Hb(this.dragging, Wv(e));
		i.emitter.on("pointerdown", this.handlePointerDown), i.emitter.on("dragstart", this.handleDragStart), i.emitter.on("hitupdate", this.handleHitUpdate), i.emitter.on("pointerup", this.handlePointerUp);
	}
	destroy() {
		this.dragging.destroy();
	}
};
function Jb(e) {
	let { options: t } = e.context, n = t.selectLongPressDelay;
	return n ??= t.longPressDelay, n;
}
function Yb(e, t, n) {
	let r = e.dateSpan, i = t.dateSpan, a = r.instantStartMs != null || i.instantStartMs != null, o = [
		{
			date: r.range.start,
			ms: Oh(r, e.context.dateEnv)
		},
		{
			date: r.range.end,
			ms: kh(r, e.context.dateEnv)
		},
		{
			date: i.range.start,
			ms: Oh(i, t.context.dateEnv)
		},
		{
			date: i.range.end,
			ms: kh(i, t.context.dateEnv)
		}
	];
	o.sort(a ? (e, t) => tm(e.ms, t.ms) : (e, t) => tm(e.date.valueOf(), t.date.valueOf()));
	let s = {};
	for (let r of n) {
		let n = r(e, t);
		if (n === !1) return null;
		n && Object.assign(s, n);
	}
	if (a) {
		let t = Hm({
			marker: o[0].date,
			instantMs: o[0].ms
		}, {
			marker: o[3].date,
			instantMs: o[3].ms
		}, e.context.dateEnv);
		if (!t) return null;
		s.range = {
			start: t.start,
			end: t.end
		};
	} else s.range = {
		start: o[0].date,
		end: o[3].date
	};
	return s.allDay = r.allDay, a && (s.instantStartMs = o[0].ms, s.instantEndMs = o[3].ms), s;
}
function Xb(e, t) {
	let n = e.dateSpan.allDay ? null : e.dateSpan.instantStartMs, r = t.dateSpan.allDay ? null : t.dateSpan.instantStartMs;
	return n != null && r != null ? r - n : null;
}
function Zb(e, t, n = {}) {
	let r = Xb(e, t), i = n.date0 || e.dateSpan.range.start, a = n.date1 || t.dateSpan.range.start;
	return {
		delta: r == null ? Km(i, a, e.context.dateEnv, n.largeUnit) : z(r),
		instantDeltaMs: r
	};
}
var Qb = class e extends Hv {
	constructor(t) {
		super(t), this.subjectEl = null, this.isDragging = !1, this.eventRange = null, this.relevantEvents = null, this.receivingContext = null, this.validMutation = null, this.mutatedRelevantEvents = null, this.handlePointerDown = (e) => {
			let t = e.origEvent.target, { component: n, dragging: r } = this, { mirror: i } = r, { options: a } = n.context, o = n.context;
			this.subjectEl = e.subjectEl;
			let s = (this.eventRange = Yh(e.subjectEl)).instance.instanceId;
			this.relevantEvents = Qm(o.getCurrentData().eventStore, s), r.minDistance = e.isTouch ? 0 : a.eventDragMinDistance, r.delay = e.isTouch && s !== n.props.eventSelection ? ex(n) : null, i.parentNode = __(t), i.revertDuration = a.dragRevertDuration, i.colorScheme = a.colorScheme || "", !n.isValidSegDownEl(t) || t.closest(`.${B.internalEventResizer}`) ? r.cancel() : this.isDragging = e.subjectEl.classList.contains(B.internalEventDraggable);
		}, this.handleDragStart = (e) => {
			let t = this.component.context, n = this.eventRange, r = n.instance.instanceId;
			e.isTouch ? r !== this.component.props.eventSelection && t.dispatch({
				type: "SELECT_EVENT",
				eventInstanceId: r
			}) : t.dispatch({ type: "UNSELECT_EVENT" }), this.isDragging && (t.calendarApi.unselect(e), t.emitter.trigger("eventDragStart", {
				el: this.subjectEl,
				event: new K(t, n.def, n.instance),
				jsEvent: e.origEvent,
				view: t.viewApi
			}));
		}, this.handleHitUpdate = (e, t) => {
			if (!this.isDragging) return;
			let n = this.relevantEvents, r = this.hitDragging.initialHit, i = this.component.context, a = null, o = null, s = null, c = !1, l = {
				affectedEvents: n,
				mutatedEvents: eh(),
				isEvent: !0
			};
			if (e) {
				a = e.context;
				let t = a.options;
				i === a || t.editable && t.droppable ? (o = $b(r, e, this.eventRange.instance.range.start, a.getCurrentData().pluginHooks.eventDragMutationMassagers), o && (s = Ih(n, a.getCurrentData().eventUiBases, o, a), l.mutatedEvents = s, $y(l, e.dateProfile, a) || (c = !0, o = null, s = null, l.mutatedEvents = eh()))) : a = null;
			}
			this.displayDrag(a, l), c ? Wp() : Gp(), t || (i === a && Ub(r, e) && (o = null), this.dragging.setMirrorNeedsRevert(!o), this.dragging.setMirrorIsVisible(!e || !this.subjectEl.getRootNode().querySelector(`.${B.internalEventMirror}`)), this.receivingContext = a, this.validMutation = o, this.mutatedRelevantEvents = s);
		}, this.handlePointerUp = () => {
			this.isDragging || this.cleanup();
		}, this.handleDragEnd = (e) => {
			if (this.isDragging) {
				let t = this.component.context, n = t.viewApi, { receivingContext: r, validMutation: i } = this, a = this.eventRange.def, o = this.eventRange.instance, s = new K(t, a, o), c = this.relevantEvents, l = this.mutatedRelevantEvents, { finalHit: u } = this.hitDragging;
				if (this.clearDrag(), t.emitter.trigger("eventDragStop", {
					el: this.subjectEl,
					event: s,
					jsEvent: e.origEvent,
					view: n
				}), i) {
					if (r === t) {
						let r = new K(t, l.defs[a.defId], o ? l.instances[o.instanceId] : null);
						t.dispatch({
							type: "MERGE_EVENTS",
							eventStore: l
						});
						let u = {
							oldEvent: s,
							event: r,
							relatedEvents: Wh(l, t, o),
							revert() {
								t.dispatch({
									type: "MERGE_EVENTS",
									eventStore: c
								});
							}
						}, d = {};
						for (let e of t.getCurrentData().pluginHooks.eventDropTransformers) Object.assign(d, e(i, t, a));
						t.emitter.trigger("eventDrop", {
							...u,
							...d,
							el: e.subjectEl,
							delta: i.datesDelta,
							jsEvent: e.origEvent,
							view: n
						}), t.emitter.trigger("eventChange", u);
					} else if (r) {
						let i = {
							event: s,
							relatedEvents: Wh(c, t, o),
							revert() {
								t.dispatch({
									type: "MERGE_EVENTS",
									eventStore: c
								});
							}
						};
						t.emitter.trigger("eventLeave", {
							...i,
							draggedEl: e.subjectEl,
							view: n
						}), t.dispatch({
							type: "REMOVE_EVENTS",
							eventStore: c
						}), t.emitter.trigger("eventRemove", i);
						let d = l.defs[a.defId], f = l.instances[o.instanceId], p = new K(r, d, f);
						r.dispatch({
							type: "MERGE_EVENTS",
							eventStore: l
						});
						let m = {
							event: p,
							relatedEvents: Wh(l, r, f),
							revert() {
								r.dispatch({
									type: "REMOVE_EVENTS",
									eventStore: l
								});
							}
						};
						r.emitter.trigger("eventAdd", m), e.isTouch && r.dispatch({
							type: "SELECT_EVENT",
							eventInstanceId: o.instanceId
						}), r.emitter.trigger("drop", {
							...Wb(u.dateSpan, r),
							draggedEl: e.subjectEl,
							jsEvent: e.origEvent,
							view: u.context.viewApi
						}), r.emitter.trigger("eventReceive", {
							...m,
							draggedEl: e.subjectEl,
							view: u.context.viewApi
						});
					}
				} else t.emitter.trigger("_noEventDrop");
			}
			this.cleanup();
		};
		let { component: n } = this, { options: r } = n.context, i = this.dragging = new zb(t.el);
		i.pointer.selector = e.SELECTOR, i.touchScrollAllowed = !1, i.autoScroller.isEnabled = r.dragScroll;
		let a = this.hitDragging = new Hb(this.dragging, Gv);
		a.useSubjectCenter = t.useEventCenter, a.emitter.on("pointerdown", this.handlePointerDown), a.emitter.on("dragstart", this.handleDragStart), a.emitter.on("hitupdate", this.handleHitUpdate), a.emitter.on("pointerup", this.handlePointerUp), a.emitter.on("dragend", this.handleDragEnd);
	}
	destroy() {
		this.dragging.destroy();
	}
	displayDrag(e, t) {
		let n = this.component.context, r = this.receivingContext;
		r && r !== e && (r === n ? r.dispatch({
			type: "SET_EVENT_DRAG",
			state: {
				affectedEvents: t.affectedEvents,
				mutatedEvents: eh(),
				isEvent: !0
			}
		}) : r.dispatch({ type: "UNSET_EVENT_DRAG" })), e && e.dispatch({
			type: "SET_EVENT_DRAG",
			state: t
		});
	}
	clearDrag() {
		let e = this.component.context, { receivingContext: t } = this;
		t && t.dispatch({ type: "UNSET_EVENT_DRAG" }), e !== t && e.dispatch({ type: "UNSET_EVENT_DRAG" });
	}
	cleanup() {
		this.isDragging = !1, this.eventRange = null, this.relevantEvents = null, this.receivingContext = null, this.validMutation = null, this.mutatedRelevantEvents = null;
	}
};
Qb.SELECTOR = `.${B.internalEventDraggable}, .${B.internalEventResizable}`;
function $b(e, t, n, r) {
	let i = e.dateSpan, a = t.dateSpan, o = i.range.start, s = a.range.start, c = {};
	i.allDay !== a.allDay && (c.allDay = a.allDay, c.hasEnd = t.context.options.allDayMaintainDuration, o = a.allDay ? R(n) : n);
	let { delta: l, instantDeltaMs: u } = Zb(e, t, {
		date0: o,
		date1: s,
		largeUnit: e.componentId === t.componentId ? e.largeUnit : null
	});
	l.milliseconds && (c.allDay = !1);
	let d = {
		datesDelta: l,
		...u == null ? {} : { instantDatesDeltaMs: u },
		standardProps: c
	};
	for (let n of r) n(d, e, t);
	return d;
}
function ex(e) {
	let { options: t } = e.context, n = t.eventLongPressDelay;
	return n ??= t.longPressDelay, n;
}
var tx = class extends Hv {
	constructor(e) {
		super(e), this.draggingSegEl = null, this.draggingEventRange = null, this.eventRange = null, this.relevantEvents = null, this.validMutation = null, this.mutatedRelevantEvents = null, this.handlePointerDown = (e) => {
			let { component: t } = this, n = this.querySegEl(e), r = this.eventRange = Yh(n);
			this.dragging.minDistance = t.context.options.eventDragMinDistance, (!this.component.isValidSegDownEl(e.origEvent.target) || e.isTouch && this.component.props.eventSelection !== r.instance.instanceId) && this.dragging.cancel();
		}, this.handleDragStart = (e) => {
			let { context: t } = this.component, n = this.eventRange;
			this.relevantEvents = Qm(t.getCurrentData().eventStore, this.eventRange.instance.instanceId);
			let r = this.querySegEl(e);
			this.draggingSegEl = r, this.draggingEventRange = Yh(r), t.calendarApi.unselect(), t.emitter.trigger("eventResizeStart", {
				el: r,
				event: new K(t, n.def, n.instance),
				jsEvent: e.origEvent,
				view: t.viewApi
			});
		}, this.handleHitUpdate = (e, t, n) => {
			let { context: r } = this.component, i = this.relevantEvents, a = this.hitDragging.initialHit, o = this.eventRange.instance, s = null, c = null, l = !1, u = {
				affectedEvents: i,
				mutatedEvents: eh(),
				isEvent: !0
			};
			e && (e.componentId !== a.componentId || !this.isHitComboAllowed || this.isHitComboAllowed(a, e)) && (s = nx(a, e, n.subjectEl.classList.contains(B.internalEventResizerStart), o.range)), s && (c = Ih(i, r.getCurrentData().eventUiBases, s, r), u.mutatedEvents = c, $y(u, e.dateProfile, r) || (l = !0, s = null, c = null, u.mutatedEvents = null)), c ? r.dispatch({
				type: "SET_EVENT_RESIZE",
				state: u
			}) : r.dispatch({ type: "UNSET_EVENT_RESIZE" }), l ? Wp() : Gp(), t || (s && Ub(a, e) && (s = null), this.validMutation = s, this.mutatedRelevantEvents = c);
		}, this.handleDragEnd = (e) => {
			let { context: t } = this.component, n = this.eventRange.def, r = this.eventRange.instance, i = new K(t, n, r), a = this.relevantEvents, o = this.mutatedRelevantEvents;
			if (t.emitter.trigger("eventResizeStop", {
				el: this.draggingSegEl,
				event: i,
				jsEvent: e.origEvent,
				view: t.viewApi
			}), this.validMutation) {
				let s = new K(t, o.defs[n.defId], r ? o.instances[r.instanceId] : null);
				t.dispatch({
					type: "MERGE_EVENTS",
					eventStore: o
				});
				let c = {
					oldEvent: i,
					event: s,
					relatedEvents: Wh(o, t, r),
					revert() {
						t.dispatch({
							type: "MERGE_EVENTS",
							eventStore: a
						});
					}
				};
				t.emitter.trigger("eventResize", {
					...c,
					el: this.draggingSegEl,
					startDelta: this.validMutation.startDelta || z(0),
					endDelta: this.validMutation.endDelta || z(0),
					jsEvent: e.origEvent,
					view: t.viewApi
				}), t.emitter.trigger("eventChange", c);
			} else t.emitter.trigger("_noEventResize");
			this.draggingEventRange = null, this.relevantEvents = null, this.validMutation = null;
		};
		let { component: t } = e, n = this.dragging = new zb(e.el);
		n.pointer.selector = `.${B.internalEventResizer}`, n.touchScrollAllowed = !1, n.autoScroller.isEnabled = t.context.options.dragScroll;
		let r = this.hitDragging = new Hb(this.dragging, Wv(e));
		r.emitter.on("pointerdown", this.handlePointerDown), r.emitter.on("dragstart", this.handleDragStart), r.emitter.on("hitupdate", this.handleHitUpdate), r.emitter.on("dragend", this.handleDragEnd);
	}
	destroy() {
		this.dragging.destroy();
	}
	querySegEl(e) {
		return e.subjectEl.closest(`.${B.internalEvent}`);
	}
};
function nx(e, t, n, r) {
	let { context: i } = e, a = e.dateSpan.range.start, o = t.dateSpan.range.start, { delta: s, instantDeltaMs: c } = Zb(e, t, {
		date0: a,
		date1: o,
		largeUnit: e.largeUnit
	});
	if (n) {
		let e = zh(r.start, r.instantStartMs, s, c ?? void 0, i);
		if (e.instantMs == null ? e.marker < r.end : e.instantMs < Im(r, i.dateEnv)) return {
			startDelta: s,
			...c == null ? {} : { instantStartDeltaMs: c }
		};
	} else {
		let e = zh(r.end, r.instantEndMs, s, c ?? void 0, i);
		if (e.instantMs == null ? e.marker > r.start : e.instantMs > Fm(r, i.dateEnv)) return {
			endDelta: s,
			...c == null ? {} : { instantEndDeltaMs: c }
		};
	}
	return null;
}
var rx = {
	name: "interaction",
	componentInteractions: [
		Kb,
		qb,
		Qb,
		tx
	],
	calendarInteractions: [class {
		constructor(e) {
			this.context = e, this.isRecentPointerDateSelect = !1, this.matchesCancel = !1, this.matchesEvent = !1, this.onSelect = (e) => {
				e.jsEvent && (this.isRecentPointerDateSelect = !0);
			}, this.onDocumentPointerDown = (e) => {
				let t = this.context.options.unselectCancel, n = S_(e.origEvent);
				this.matchesCancel = !!n.closest(t), this.matchesEvent = !!n.closest(Qb.SELECTOR);
			}, this.onDocumentPointerUp = (e) => {
				let { context: t } = this, { documentPointer: n } = this, r = t.getCurrentData();
				if (!n.wasTouchScroll) {
					if (r.dateSelection && !this.isRecentPointerDateSelect) {
						let n = t.options.unselectAuto;
						!n || n && this.matchesCancel || t.calendarApi.unselect(e);
					}
					r.eventSelection && !this.matchesEvent && t.dispatch({ type: "UNSELECT_EVENT" });
				}
				this.isRecentPointerDateSelect = !1;
			};
			let t = this.documentPointer = new Cb(document);
			t.shouldIgnoreMove = !0, t.shouldWatchScroll = !1, t.emitter.on("pointerdown", this.onDocumentPointerDown), t.emitter.on("pointerup", this.onDocumentPointerUp), e.emitter.on("select", this.onSelect);
		}
		destroy() {
			this.context.emitter.off("select", this.onSelect), this.documentPointer.destroy();
		}
	}],
	elementDraggingImpl: zb
}, ix = class {
	constructor() {
		this.sliceBusinessHours = q(this._sliceBusinessHours), this.sliceDateSelection = q(this._sliceDateSpan), this.sliceEventStore = q(this._sliceEventStore), this.sliceEventDrag = q(this._sliceInteraction), this.sliceEventResize = q(this._sliceInteraction), this.forceDayIfListItem = !1;
	}
	intersectDateSpan(e, t, ...n) {
		let r = Lf(e.range, t);
		if (r) {
			let t = {
				...e,
				range: r
			};
			return r.start.valueOf() !== e.range.start.valueOf() && delete t.instantStartMs, r.end.valueOf() !== e.range.end.valueOf() && delete t.instantEndMs, t;
		}
		return null;
	}
	sliceDateSpan(e, ...t) {
		return this.sliceRange(e.range, ...t);
	}
	sliceProps(e, t, n, r, ...i) {
		let { eventUiBases: a } = e, o = this.sliceEventStore(e.eventStore, a, t, n, ...i);
		return {
			dateSelectionSegs: this.sliceDateSelection(e.dateSelection, t, n, a, r, ...i),
			businessHourSegs: this.sliceBusinessHours(e.businessHours, t, n, r, ...i),
			fgEventSegs: o.fg,
			bgEventSegs: o.bg,
			eventDrag: this.sliceEventDrag(e.eventDrag, a, t, n, ...i),
			eventResize: this.sliceEventResize(e.eventResize, a, t, n, ...i),
			eventSelection: e.eventSelection
		};
	}
	sliceNowDate(e, t, n, r, ...i) {
		return this._sliceDateSpan({
			range: {
				start: e,
				end: uf(e, 1)
			},
			allDay: !1
		}, t, n, uh, r, ...i);
	}
	_sliceBusinessHours(e, t, n, r, ...i) {
		return e ? this._sliceEventStore(Jm(e, ax(t, !!n), r), uh, t, n, ...i).bg : [];
	}
	_sliceEventStore(e, t, n, r, ...i) {
		if (e) {
			let a = Gh(e, t, ax(n, !!r), r);
			return {
				bg: this.sliceEventRanges(a.bg, i),
				fg: this.sliceEventRanges(a.fg, i)
			};
		}
		return {
			bg: [],
			fg: []
		};
	}
	_sliceInteraction(e, t, n, r, ...i) {
		if (!e) return null;
		let a = Gh(e.mutatedEvents, t, ax(n, !!r), r);
		return {
			segs: this.sliceEventRanges(a.fg, i),
			affectedInstances: e.affectedEvents.instances,
			isEvent: e.isEvent
		};
	}
	_sliceDateSpan(e, t, n, r, i, ...a) {
		if (!e) return [];
		let o = ax(t, !!n), s = this.intersectDateSpan(e, o, ...a);
		if (s) {
			e = s;
			let t = Ah(e, r, i), n = this.sliceDateSpan(e, ...a);
			for (let e of n) e.eventRange = t;
			return n;
		}
		return [];
	}
	sliceEventRanges(e, t) {
		let n = [];
		for (let r of e) n.push(...this.sliceEventRange(r, t));
		return n;
	}
	sliceEventRange(e, t) {
		let n = e.range;
		this.forceDayIfListItem && e.ui.display === "list-item" && (n = {
			start: n.start,
			end: L(n.start, 1)
		});
		let r = this.sliceRange(n, ...t);
		for (let t of r) t.eventRange = e, t.isStart = e.isStart && t.isStart, t.isEnd = e.isEnd && t.isEnd;
		return r;
	}
};
function ax(e, t) {
	let n = e.activeRange;
	return t ? n : {
		start: uf(n.start, e.slotMinTime.milliseconds),
		end: uf(n.end, e.slotMaxTime.milliseconds - 864e5)
	};
}
var ox = class {
	constructor(e, t, n, r = "", i) {
		this.daySeries = e, this.dateEnv = n, this.majorUnit = r, this.activeRange = i;
		let { dates: a } = e, o, s, c;
		if (t) {
			for (s = a[0].getUTCDay(), o = 1; o < a.length && a[o].getUTCDay() !== s; o += 1);
			c = Math.ceil(a.length / o);
		} else c = 1, o = a.length;
		this.rowCount = c, this.colCount = o, this.cellRows = this.buildCells(), this.headerDates = this.buildHeaderDates();
	}
	buildCells() {
		let e = [];
		for (let t = 0; t < this.rowCount; t += 1) {
			let n = [];
			for (let e = 0; e < this.colCount; e += 1) n.push(this.buildCell(t, e));
			e.push(n);
		}
		return e;
	}
	buildCell(e, t) {
		let n = this.daySeries.dates[e * this.colCount + t];
		return {
			key: n.toISOString(),
			date: n,
			isMajor: this.cellIsMajor(n),
			isDisabled: this.activeRange === null || this.activeRange !== void 0 && !Vf(this.activeRange, n)
		};
	}
	cellIsMajor(e) {
		return this.majorUnit ? jv(e, this.majorUnit, this.dateEnv) : !1;
	}
	buildHeaderDates() {
		let e = [];
		for (let t = 0; t < this.colCount; t += 1) e.push(this.cellRows[0][t].date);
		return e;
	}
};
function sx(e, t) {
	let n = [];
	if (e) {
		let { start: r, end: i } = e, a = r;
		for (; a < i;) {
			let o = Math.floor(a / t), s = Math.min((o + 1) * t, i);
			n.push({
				row: o,
				start: a % t,
				end: (s - 1) % t + 1,
				isStart: e.isStart && a === r,
				isEnd: e.isEnd && s === i
			}), a = s;
		}
	}
	return n;
}
var cx = class extends ix {
	constructor() {
		super(...arguments), this.forceDayIfListItem = !0;
	}
	sliceRange(e, t) {
		return sx(t.daySeries.sliceRange(e), t.colCount);
	}
}, lx = class extends ix {
	constructor() {
		super(...arguments), this.forceDayIfListItem = !0;
	}
	sliceRange(e, t) {
		return sx(t.sliceRange(e), t.cnt);
	}
}, ux = /* @__PURE__ */ new Date(2592e5);
function dx(e, t, n, r, i, a) {
	let o = fx(e, t, n, r, i, a), s = Av(n, a.dateEnv);
	if (t && s !== "day") for (let e of o.dataConfigs) jv(e.dateMarker, s, a.dateEnv) && (e.renderProps.isMajor = !0);
	return [o];
}
function fx(e, t, n, r, i, a, o, s, c) {
	return {
		isDateRow: !0,
		renderConfig: px(i, t, a),
		dataConfigs: mx(e, t, n, r, i, a, o, void 0, void 0, void 0, void 0, s, c)
	};
}
function px(e, t, n) {
	let { options: r } = n;
	return {
		generatorName: "dayHeaderContent",
		customGenerator: r.dayHeaderContent,
		classNameGenerator: r.dayHeaderClass,
		innerClassNameGenerator: r.dayHeaderInnerClass,
		didMount: r.dayHeaderDidMount,
		willUnmount: r.dayHeaderWillUnmount,
		align: r.dayHeaderAlign,
		sticky: r._dayHeaderSticky,
		dayHeaderFormat: e,
		datesRepDistinctDays: t
	};
}
function mx(e, t, n, r, i, a, o = 1, s = "", c = {}, l = {}, u = "", d, f = e.length) {
	let { dateEnv: p, viewApi: m, options: h } = a;
	return t ? e.map((e, t) => {
		let i = pv(e, p, n, r), g = d != null && !(t % d), _ = h.navLinks && !i.isDisabled && f > 1, v = {
			...i,
			...c,
			isMajor: g,
			isSticky: !1,
			inPopover: !1,
			hasNavLink: _,
			view: m
		}, y = dv(a, e);
		return {
			key: s + e.toUTCString(),
			dateMarker: e,
			renderProps: v,
			attrs: {
				...i.isToday ? { "aria-current": "date" } : {},
				"data-date": cp(e),
				...l
			},
			innerAttrs: _ ? fv(a, e, void 0, y, !0, !0) : void 0,
			a11yText: _ ? void 0 : y,
			colSpan: o,
			hasNavLink: _,
			className: u
		};
	}) : e.map((e, t) => {
		let n = e.getUTCDay(), r = L(ux, n), i = {
			date: p.toDate(e),
			dow: n,
			isDisabled: !1,
			isFuture: !1,
			isPast: !1,
			isToday: !1,
			isOther: !1
		}, a = d != null && !(t % d), f = {
			...i,
			isMajor: a,
			isSticky: !1,
			inPopover: !1,
			hasNavLink: !1,
			view: m,
			...c
		}, h = fp(p.formatToParts(r, sv));
		return {
			key: s + String(n),
			dateMarker: e,
			renderProps: f,
			attrs: l,
			a11yText: h,
			colSpan: o,
			className: u
		};
	});
}
var hx = class {
	constructor(e, t = !1) {
		this.masterCallback = e, this.ignoreDeletes = t, this.rev = "", this.current = /* @__PURE__ */ new Map(), this.callbacks = /* @__PURE__ */ new Map(), this.handleValue = (e, t) => {
			let { current: n, callbacks: r } = this, i = n.has(t), a = i ? n.get(t) : null, o = !1;
			e === null ? i && !this.ignoreDeletes && (n.delete(t), r.delete(t), o = !0) : (o = a !== e, n.set(t, e)), o && (this.rev = Up(), this.masterCallback && this.masterCallback(e, t, a));
		};
	}
	createRef(e) {
		let t = this.callbacks.get(e);
		return t || (t = (t) => {
			this.handleValue(t, e);
		}, this.callbacks.set(e, t)), t;
	}
}, gx = class extends J {
	constructor() {
		super(...arguments), this.elRef = C();
	}
	render() {
		return D("div", { ref: this.elRef });
	}
	componentDidMount() {
		this._isUnmounting = !1;
		let { props: e } = this, t = this.elRef.current;
		this.disconnectWidth = nv(t, (t) => {
			this._isUnmounting || Y(e.widthRef, t);
		});
	}
	componentWillUnmount() {
		this._isUnmounting = !0, this.disconnectWidth();
		let { props: e } = this;
		e.widthRef && Y(e.widthRef, null);
	}
};
function _x(e) {
	return `${e.eventRange.instance.instanceId}:${e.start}`;
}
function vx(e, t) {
	let n = [];
	for (let e = 0; e < t; e++) n[e] = [];
	for (let t of e) n[t.row].push(t);
	return n;
}
function yx(e, t) {
	let n = [];
	if (e) {
		for (let r = 0; r < t; r++) n[r] = {
			affectedInstances: e.affectedInstances,
			isEvent: e.isEvent,
			segs: []
		};
		for (let t of e.segs) n[t.row].segs.push(t);
	} else for (let e = 0; e < t; e++) n[e] = null;
	return n;
}
var bx = class extends J {
	constructor() {
		super(...arguments), this.buildPublicEvent = q((e, t, n) => new K(e, t, n)), this.handleEl = (e) => {
			this.el = e, e && Jh(e, this.props.eventRange);
		};
	}
	render() {
		let { props: e, context: t } = this, { eventRange: n } = e, { options: r } = t, i = n.ui, a = this.buildPublicEvent(t, n.def, n.instance), o = {
			...e.renderProps,
			event: a,
			timeText: "",
			isNarrow: e.isNarrow || !1,
			isShort: e.isShort || !1
		}, s = {
			...e.renderProps,
			event: a,
			view: t.viewApi,
			timeText: "",
			color: i.color || r.backgroundEventColor,
			contrastColor: i.contrastColor,
			isDraggable: !1,
			isStartResizable: !1,
			isEndResizable: !1,
			isMirror: !1,
			isStart: e.isStart,
			isEnd: e.isEnd,
			isFirst: !1,
			isLast: !1,
			isPast: e.isPast,
			isFuture: e.isFuture,
			isToday: e.isToday,
			isSelected: !1,
			isDragging: !1,
			isResizing: !1,
			isInteractive: !1,
			level: 0,
			isNarrow: e.isNarrow || !1,
			isShort: e.isShort || !1,
			timeClass: "",
			titleClass: Z(r.backgroundEventTitleClass, o),
			options: { eventOverlap: !!r.eventOverlap }
		}, c = V(i.className, B.fill, B.internalEvent, B.internalBgEvent, e.isVertical ? B.flexCol : B.flexRow), l = V(Z(r.backgroundEventInnerClass, s), B.liquid);
		return D(X, {
			tag: "div",
			className: c,
			style: {
				"--fc-event-color": s.color,
				"--fc-event-contrast-color": s.contrastColor
			},
			defaultGenerator: xx,
			elRef: this.handleEl,
			renderProps: s,
			generatorName: "backgroundEventContent",
			customGenerator: r.backgroundEventContent,
			classNameGenerator: r.backgroundEventClass,
			didMount: r.backgroundEventDidMount,
			willUnmount: r.backgroundEventWillUnmount,
			children: (e) => D(e, {
				tag: "div",
				className: l
			})
		});
	}
	componentDidUpdate(e) {
		this.el && this.props.eventRange !== e.eventRange && Jh(this.el, this.props.eventRange);
	}
};
function xx(e) {
	let { title: t } = e.event;
	return t && D("div", {
		className: e.titleClass,
		children: e.event.title
	});
}
function Sx(e, t) {
	return D("div", { className: V(e === "non-business" ? t.nonBusinessHoursClass : e === "highlight" ? t.highlightClass : void 0, B.fill) });
}
var Cx = 1, wx = 1, Tx = 10, Ex = class extends gv {
	constructor() {
		super(...arguments), this.getDateMeta = q(pv), this.closeRef = C(), this.focusStartRef = C(), this.focusEndRef = C(), this.handleRootEl = (e) => {
			this.rootEl = e, e ? this.context.registerInteractiveComponent(this, {
				el: e,
				useEventCenter: !1
			}) : this.context.unregisterInteractiveComponent(this);
		}, this.handleDocumentMouseDown = (e) => {
			let t = S_(e);
			this.rootEl.contains(t) || this.handleClose();
		}, this.handleDocumentKeyDown = (e) => {
			e.key === "Escape" && this.handleClose();
		}, this.handleClose = () => {
			let { onClose: e } = this.props;
			e && e();
		};
	}
	render() {
		let { props: e, context: t } = this, { options: n, dateEnv: r, viewApi: i } = t, { startDate: a, todayRange: o, dateProfile: s } = e, c = this.getDateMeta(a, r, s, o), l = r.formatToParts(a, n.popoverFormat), u = fp(l), d = {
			...e.renderProps,
			...c,
			isMajor: !1,
			isNarrow: !1,
			isSticky: !1,
			inPopover: !0,
			level: 0,
			hasNavLink: !1,
			text: u,
			textParts: l,
			get weekdayText() {
				return cv(l);
			},
			get dayNumberText() {
				return lv(l);
			},
			get monthText() {
				return uv(l);
			},
			view: i
		}, f = {
			...e.renderProps,
			...c,
			isMajor: !1,
			isNarrow: !1,
			inPopover: !0,
			hasNavLink: !1,
			view: i,
			text: "",
			textParts: [],
			weekdayText: "",
			dayNumberText: "",
			monthText: "",
			options: { businessHours: !!n.businessHours }
		}, p = cp(a), { dayHeaderAlign: m } = n, h = typeof m == "function" ? m({
			level: 0,
			inPopover: !0,
			isNarrow: !1
		}) : m, g = v_(e.alignEl);
		return $e(D("div", {
			"data-date": p,
			id: e.id,
			role: "dialog",
			"aria-labelledby": e.titleId,
			className: V(n.popoverClass, B.flexCol, B.popoverZ, B.abs, B.borderBoxRoot, B.internalPopover),
			style: {
				top: 0,
				left: 0
			},
			dir: g ? "rtl" : void 0,
			"data-color-scheme": n.colorScheme || void 0,
			ref: this.handleRootEl,
			children: [
				D("div", {
					tabIndex: 0,
					style: { outline: "none" },
					ref: this.focusStartRef
				}),
				D("div", {
					className: V(Z(n.dayHeaderClass, d), B.flexCol, B.borderlessX, B.borderlessTop, h === "center" ? B.alignCenter : h === "end" ? B.alignEnd : B.alignStart),
					children: [D("div", { children: D(X, {
						tag: "div",
						attrs: { id: e.titleId },
						generatorName: "dayHeaderContent",
						renderProps: d,
						customGenerator: n.dayHeaderContent,
						defaultGenerator: Dx,
						classNameGenerator: n.dayHeaderInnerClass,
						didMount: n.dayHeaderDidMount,
						willUnmount: n.dayHeaderWillUnmount
					}) }), D(X, {
						tag: "button",
						attrs: {
							"aria-label": n.closeHint,
							...Bp(this.handleClose)
						},
						elRef: this.closeRef,
						className: V(n.popoverCloseClass, B.flexRow, B.cursorPointer),
						renderProps: {},
						customGenerator: n.popoverCloseContent,
						generatorName: "popoverCloseContent"
					})]
				}),
				D("div", {
					className: V(Z(n.dayCellClass, f), B.flexCol, B.borderless),
					children: D("div", {
						role: "list",
						className: Z(n.dayCellInnerClass, f),
						children: e.children
					})
				}),
				D("div", {
					tabIndex: 0,
					style: { outline: "none" },
					ref: this.focusEndRef
				})
			]
		}), __(e.alignEl));
	}
	queryHit(e, t, n, r, i) {
		let { rootEl: a, props: o } = this;
		return t >= 0 && t < r && n >= 0 && n < i ? {
			dateProfile: o.dateProfile,
			dateSpan: {
				allDay: !o.forceTimed,
				range: {
					start: o.startDate,
					end: o.endDate
				},
				...o.dateSpanProps
			},
			getDayEl: () => a,
			rect: {
				left: 0,
				top: 0,
				right: r,
				bottom: i
			},
			layer: 1
		} : null;
	}
	componentDidMount() {
		document.addEventListener("mousedown", this.handleDocumentMouseDown), document.addEventListener("keydown", this.handleDocumentKeyDown), this.focusStartRef.current.addEventListener("focus", this.handleClose), this.focusEndRef.current.addEventListener("focus", this.handleClose), this.closeRef.current.focus({ preventScroll: !0 }), this.updateSize();
	}
	componentWillUnmount() {
		document.removeEventListener("mousedown", this.handleDocumentMouseDown), document.removeEventListener("keydown", this.handleDocumentKeyDown), this.focusStartRef.current.removeEventListener("focus", this.handleClose), this.focusEndRef.current.removeEventListener("focus", this.handleClose);
	}
	updateSize() {
		let { alignEl: e, alignParentTop: t } = this.props, { rootEl: n } = this, r = v_(e), i = _b(e);
		if (i) {
			let a = n.getBoundingClientRect(), o = t ? e.closest(t).getBoundingClientRect().top - wx : i.top, s = r ? i.right - a.width : i.left;
			o = Math.max(o, Tx), s = Math.min(s, document.documentElement.clientWidth - Tx - a.width), s = Math.max(s, Tx);
			let { offsetParent: c } = n, l, u;
			if (!c || c === document.body) l = o + window.scrollY, u = s + window.scrollX;
			else {
				let e = c.getBoundingClientRect();
				l = o - e.top + c.scrollTop, u = s - e.left + c.scrollLeft;
			}
			b_(n, {
				top: l,
				left: u
			});
		}
	}
};
function Dx(e) {
	return e.text;
}
function Ox(e) {
	return e.reduce(Ax).eventRange.range.start;
}
function kx(e) {
	return e.reduce(jx).eventRange.range.end;
}
function Ax(e, t) {
	return e.eventRange.range.start < t.eventRange.range.start ? e : t;
}
function jx(e, t) {
	return e.eventRange.range.end > t.eventRange.range.end ? e : t;
}
var Mx = class extends J {
	render() {
		let { props: e, context: t } = this, { options: n } = t, r = Fx(e.num, e.isNarrow, e.isMicro, e.display, e.renderProps, t);
		return D(X, {
			tag: "div",
			elRef: e.elRef,
			className: V(Z(e.display === "row" ? n.rowMoreLinkClass : n.columnMoreLinkClass, r), e.className, e.display === "row" ? B.flexRow : B.flexCol, B.internalMoreLink, B.cursorPointer),
			style: e.style,
			attrs: e.attrs,
			renderProps: r,
			generatorName: "moreLinkContent",
			customGenerator: n.moreLinkContent,
			defaultGenerator: Px,
			classNameGenerator: n.moreLinkClass,
			didMount: e.didMount,
			willUnmount: e.willUnmount,
			children: (t) => D(t, {
				tag: "div",
				className: V(Z(n.moreLinkInnerClass, r), Z(e.display === "row" ? n.rowMoreLinkInnerClass : n.columnMoreLinkInnerClass, r), e.display === "row" ? B.stickyS : B.stickyT)
			})
		});
	}
}, Nx = class extends J {
	constructor() {
		super(...arguments), this.state = { isPopoverOpen: !1 }, this.handleLinkEl = (e) => {
			this.linkEl = e, this.props.elRef && Y(this.props.elRef, e);
		}, this.handleClick = (e) => {
			let { props: t, context: n } = this, { dateEnv: r, options: i } = n, { moreLinkClick: a } = i, o = Ix(t).start;
			function s(e) {
				let { def: t, instance: i, range: a } = e.eventRange, o = Pm(a.start, a.instantStartMs, r), s = Pm(a.end, a.instantEndMs, r);
				return {
					event: new K(n, t, i),
					start: o.date,
					end: s.date,
					isStart: e.isStart,
					isEnd: e.isEnd
				};
			}
			typeof a == "function" && (a = a({
				date: r.toDate(o),
				allDay: !!t.allDayDate,
				allSegs: t.segs.map(s),
				hiddenSegs: t.hiddenSegs.map(s),
				jsEvent: e,
				view: n.viewApi
			})), !a || a === "popover" ? this.setState({ isPopoverOpen: !0 }) : typeof a == "string" && n.calendarApi.zoomTo(o, a);
		}, this.handlePopoverClose = () => {
			this.linkEl && this.linkEl.focus(), this.setState({ isPopoverOpen: !1 });
		};
	}
	render() {
		let { props: e, state: t, context: n } = this, { options: r, baseId: i } = n, a = e.hiddenSegs.length, o = Ix(e), s = i + "popover-" + o.start.toISOString(), c = Fx(a, e.isNarrow, e.isMicro, e.display, e.renderProps, n), l = em(r.moreLinkHint, [a], c.longText);
		return D(w, { children: [!!a && D(Mx, {
			num: a,
			display: e.display,
			isNarrow: e.isNarrow,
			isMicro: e.isMicro,
			renderProps: e.renderProps,
			elRef: this.handleLinkEl,
			className: e.className,
			style: e.style,
			attrs: {
				...e.attrs,
				...Bp(this.handleClick),
				title: l,
				role: "button",
				"aria-haspopup": "dialog",
				"aria-expanded": t.isPopoverOpen,
				"aria-controls": t.isPopoverOpen ? s : void 0
			},
			didMount: r.moreLinkDidMount,
			willUnmount: r.moreLinkWillUnmount
		}), t.isPopoverOpen && D(Ex, {
			id: s,
			titleId: s + "-title",
			startDate: o.start,
			endDate: o.end,
			dateProfile: e.dateProfile,
			todayRange: e.todayRange,
			dateSpanProps: e.dateSpanProps,
			renderProps: e.renderProps,
			alignEl: e.alignElRef ? e.alignElRef.current : this.linkEl,
			alignParentTop: e.alignParentTop,
			forceTimed: e.forceTimed,
			onClose: this.handlePopoverClose,
			children: e.popoverContent()
		})] });
	}
};
function Px(e) {
	return e.text;
}
function Fx(e, t, n, r, i, a) {
	let { viewApi: o, options: s, calendarApi: c } = a, l = `+${e}`, u = typeof s.moreLinkText == "function" ? s.moreLinkText.call(c, e) : `${l} ${s.moreLinkText}`;
	return {
		...i,
		num: e,
		numericText: l,
		longText: u,
		text: n || r === "column" ? l : u,
		isNarrow: t,
		view: o
	};
}
function Ix(e) {
	return e.allDayDate ? {
		start: e.allDayDate,
		end: L(e.allDayDate, 1)
	} : {
		start: Ox(e.hiddenSegs),
		end: kx(e.hiddenSegs)
	};
}
var Lx = H({
	hour: "numeric",
	minute: "2-digit",
	omitZeroMinute: !0,
	meridiem: "narrow"
});
function Rx(e, t) {
	let { display: n } = t.ui;
	return n === "list-item" || n === "auto" && !t.def.allDay && e.end - e.start === 1 && e.isStart && e.isEnd;
}
var zx = B.z1, Bx = B.z2, Vx = B.z3, Hx = B.z4, Ux = B.z5, Wx = B.z1000, Gx = class extends J {
	render() {
		let { props: e } = this;
		return D(Nx, {
			display: "row",
			className: V(e.className, Hx),
			isNarrow: e.isNarrow,
			isMicro: e.isMicro,
			dateProfile: e.dateProfile,
			todayRange: e.todayRange,
			allDayDate: e.allDayDate,
			segs: e.segs,
			hiddenSegs: e.hiddenSegs,
			alignElRef: e.alignElRef,
			alignParentTop: e.alignParentTop,
			dateSpanProps: e.dateSpanProps,
			renderProps: e.renderProps,
			popoverContent: () => D(w, { children: e.segs.map((t) => {
				let { eventRange: n } = t, { instanceId: r } = n.instance, i = !!(e.eventDrag && e.eventDrag.affectedInstances[r]), a = !!(e.eventResize && e.eventResize.affectedInstances[r]);
				return D("div", {
					role: "listitem",
					style: { visibility: i || a ? "hidden" : void 0 },
					children: D(Dv, {
						display: Rx(t, n) ? "list-item" : "row",
						eventRange: n,
						isStart: t.isStart,
						isEnd: t.isEnd,
						isDragging: i,
						isResizing: a,
						isMirror: !1,
						isSelected: r === e.eventSelection,
						defaultTimeFormat: Lx,
						defaultDisplayEventEnd: !1,
						renderProps: e.renderProps,
						...ig(n, e.todayRange)
					})
				}, r);
			}) })
		});
	}
}, Kx = class extends gv {
	constructor() {
		super(...arguments), this.getDateMeta = q(qx), this.refineRenderProps = C_(Xx), this.rootElRef = C(), this.handleBodyEl = (e) => {
			this.disconnectBodyHeight && (this.disconnectBodyHeight(), this.disconnectBodyHeight = void 0, this.headerHeight = void 0, Y(this.props.headerHeightRef, null), Y(this.props.mainHeightRef, null)), e && (this.props.headerHeightRef || this.props.mainHeightRef) && (this.disconnectBodyHeight = tv(e, (t, n) => {
				if (this._isUnmounting) return;
				let { props: r } = this, i = this.rootElRef.current;
				if (!i) return;
				let a = e.getBoundingClientRect(), o = i.getBoundingClientRect(), s = a.top - o.top;
				G_(this.headerHeight, s) || (this.headerHeight = s, Y(r.headerHeightRef, s)), Y(r.mainHeightRef, n);
			}));
		};
	}
	render() {
		let { props: e, context: t } = this, { options: n, dateEnv: r } = t, { tableMode: i } = e, a = e.showDayNumber && Yx(e.date, e.dateProfile.currentRange, r), o = this.getDateMeta(e.date, r, e.dateProfile, e.todayRange, e.isDisabled), s = V(B.borderlessTop, B.borderlessEnd, !e.borderStart && B.borderlessStart, !(i && e.borderBottom) && B.borderlessBottom, !i && e.width == null && B.liquid, !i && B.flexCol, B.rel, B.noMargin, B.noPadding), c = i ? "td" : "div", l = i ? void 0 : { width: e.width }, u = n.navLinks, d = this.refineRenderProps({
			date: e.date,
			isMajor: e.isMajor,
			isNarrow: e.isNarrow,
			dateMeta: o,
			hasLabel: e.showDayNumber,
			hasMonthLabel: a,
			hasNavLink: u,
			renderProps: e.renderProps,
			viewApi: t.viewApi,
			dateEnv: t.dateEnv,
			monthStartFormat: n.monthStartFormat,
			dayCellFormat: n.dayCellFormat,
			businessHours: !!n.businessHours
		});
		if (o.isDisabled) return D(c, {
			role: "cell",
			"aria-disabled": !0,
			className: V(Z(n.dayCellClass, d), e.className, s),
			style: l,
			children: e.fills
		});
		let f = dv(t, e.date), p = e.showDayNumber && !u;
		return D(X, {
			tag: c,
			elRef: this.rootElRef,
			className: V(e.className, s),
			attrs: {
				...e.attrs,
				role: "cell",
				...d.isToday ? { "aria-current": "date" } : {},
				"data-date": cp(e.date)
			},
			style: l,
			renderProps: d,
			generatorName: "dayCellTopContent",
			customGenerator: n.dayCellTopContent,
			defaultGenerator: Jx,
			classNameGenerator: n.dayCellClass,
			didMount: n.dayCellDidMount,
			willUnmount: n.dayCellWillUnmount,
			children: (r) => D(w, { children: [
				e.fills,
				D("div", {
					role: "presentation",
					className: V(B.rel, Hx, Z(n.dayCellTopClass, d)),
					children: [p && D("div", {
						className: B.srOnly,
						children: f
					}), e.showDayNumber && D(r, {
						tag: "div",
						attrs: u ? fv(t, e.date, void 0, f) : { "aria-hidden": !0 },
						className: Z(n.dayCellTopInnerClass, d)
					})]
				}),
				D("div", {
					className: V(!i && B.flexCol, !i && (e.fgLiquidHeight ? B.liquid : B.grow), i && B.printCellContentMinHeight),
					ref: this.handleBodyEl,
					children: [D("div", {
						role: e.fgIsList ? "list" : "presentation",
						className: V(B.rel, Z(n.dayCellInnerClass, d)),
						style: { minHeight: e.fgHeight },
						children: e.fg
					}), D(Gx, {
						className: B.rel,
						allDayDate: e.date,
						segs: e.segs,
						hiddenSegs: e.hiddenSegs,
						alignElRef: this.rootElRef,
						alignParentTop: e.showDayNumber ? "[role=row]" : `.${B.internalView}`,
						dateSpanProps: e.dateSpanProps,
						renderProps: e.renderProps,
						dateProfile: e.dateProfile,
						eventSelection: e.eventSelection,
						eventDrag: e.eventDrag,
						eventResize: e.eventResize,
						todayRange: e.todayRange,
						isNarrow: e.isNarrow,
						isMicro: e.isMicro
					})]
				}),
				D("div", {
					role: "presentation",
					className: V(B.rel, Hx, Z(n.dayCellBottomClass, d))
				})
			] })
		});
	}
	componentDidMount() {
		this._isUnmounting = !1;
	}
	componentWillUnmount() {
		this._isUnmounting = !0;
	}
};
function qx(e, t, n, r, i) {
	return {
		...pv(e, t, n, r),
		isDisabled: i
	};
}
function Jx(e) {
	return e.text || D(w, { children: "\xA0" });
}
function Yx(e, t, n) {
	let { start: r, end: i } = t, a = uf(i, -1), o = n.getYear(r), s = n.getMonth(r), c = n.getYear(a), l = n.getMonth(a);
	return (o !== c || s !== l) && (e.valueOf() === r.valueOf() || n.getDay(e) === 1 && e.valueOf() < i.valueOf());
}
function Xx(e) {
	let { date: t, dateEnv: n, hasLabel: r, hasMonthLabel: i, hasNavLink: a, businessHours: o } = e, s = [], c = "";
	return r && (s = n.formatToParts(t, i ? e.monthStartFormat : e.dayCellFormat), c = fp(s)), {
		...e.dateMeta,
		...e.renderProps,
		text: c,
		textParts: s,
		isMajor: e.isMajor,
		isNarrow: e.isNarrow,
		inPopover: !1,
		hasNavLink: a,
		get weekdayText() {
			return cv(s);
		},
		get dayNumberText() {
			return lv(s);
		},
		get monthText() {
			return uv(s);
		},
		options: { businessHours: o },
		view: e.viewApi
	};
}
var Zx = class extends T {
	constructor() {
		super(...arguments), this.rootElRef = C(), this._isUnmounting = !1;
	}
	render() {
		let { props: e } = this;
		return D("div", {
			...e.attrs,
			className: e.className,
			style: e.style,
			ref: this.rootElRef,
			children: e.children
		});
	}
	componentDidMount() {
		this._isUnmounting = !1;
		let e = this.rootElRef.current;
		this.disconnectHeight = rv(e, (e) => {
			this._isUnmounting || (this.height = e, Y(this.props.heightRef, e));
		});
	}
	componentDidUpdate(e) {
		let { heightRef: t } = this.props;
		e.heightRef !== t && (Y(e.heightRef, null), this.height != null && Y(t, this.height));
	}
	componentWillUnmount() {
		this._isUnmounting = !0, this.disconnectHeight?.(), Y(this.props.heightRef, null);
	}
};
function Qx(e, t) {
	return e.start < t.end && t.start < e.end;
}
function $x(e, t) {
	let n = Math.max(e.start, t.start), r = Math.min(e.end, t.end);
	return n < r ? {
		start: n,
		end: r
	} : null;
}
function eS(e) {
	return e.end - e.start;
}
function tS(e, t) {
	let n = aS(e, t.start);
	n > 0 && n--;
	let r = [];
	for (; n < e.length; n++) {
		let i = e[n];
		if (i.start >= t.end) break;
		Qx(i, t) && r.push(i);
	}
	return r;
}
function nS(e, t) {
	let n = [], r = e.start;
	for (let i of t) if (!(i.end <= r) && (i.start >= e.end || (i.start > r && n.push({
		start: r,
		end: Math.min(i.start, e.end)
	}), r = Math.max(r, i.end), r >= e.end))) break;
	return r < e.end && n.push({
		start: r,
		end: e.end
	}), n;
}
function rS(e, t) {
	let n = [], r = { ...t }, i = !1;
	for (let t of e) t.end <= r.start ? n.push(t) : r.end <= t.start ? (i ||= (n.push(r), !0), n.push(t)) : r = {
		start: Math.min(r.start, t.start),
		end: Math.max(r.end, t.end)
	};
	i || n.push(r), e.splice(0, e.length, ...n);
}
function iS(e, t) {
	e.splice(aS(e, t.start), 0, t);
}
function aS(e, t) {
	let n = 0, r = e.length;
	for (; n < r;) {
		let i = n + r >>> 1;
		e[i].start < t ? n = i + 1 : r = i;
	}
	return n;
}
var oS = 1e-6;
function sS(e, t, n, r, i, a) {
	let { segLevels: o, excludedSegs: s } = lS(e, t, r), c = yS(dS(o), fS(s), t, n, i ? vS : void 0), l = pS(c.sliceLevels, a);
	return {
		renderSlices: im(c.sliceLevels),
		hiddenSlices: c.hiddenSlices,
		sliceLevels: c.sliceLevels,
		sliceCoords: l.sliceCoords,
		isSettled: l.isSettled
	};
}
function cS(e, t, n, r, i, a, o) {
	let { segLevels: s, excludedSegs: c } = lS(e, t, a), l = dS(s), u = fS(c), d = pS(l, r, i);
	if (i == null || o == null) return {
		renderSlices: im(l),
		hiddenSlices: u,
		sliceLevels: l,
		sliceCoords: d.sliceCoords,
		isSettled: d.isSettled
	};
	let f = d.excludedSlices.concat(u);
	f.sort(MS);
	let p = Math.max(0, i - o);
	function m(e, t) {
		for (let n = t.length - 1; n >= 0; n--) {
			let r = tS(t[n], e);
			if (r.length) return r.every(h) ? n + 1 : n;
		}
		return 0;
	}
	function h(e) {
		let t = gS(e, d.sliceCoords, r);
		return t !== void 0 && t <= p + oS;
	}
	let g = yS(d.placementSliceLevels, f, t, n, n ? m : void 0), _ = pS(g.sliceLevels, r), v = _S(g.sliceLevels, g.hiddenSlices, _.sliceCoords, r, i, p), y = [.../* @__PURE__ */ new Set([...im(l), ...im(g.sliceLevels)])], b = d.isSettled && _.isSettled;
	return {
		renderSlices: y,
		hiddenSlices: v.concat(g.hiddenSlices),
		sliceLevels: g.sliceLevels,
		sliceCoords: _.sliceCoords,
		isSettled: b
	};
}
function lS(e, t, n = Infinity) {
	let r = [], i = [];
	for (let a of e) {
		let e = uS(r, a, t);
		if (e >= n) i.push(a);
		else {
			for (; r.length <= e;) r.push([]);
			iS(r[e], a);
		}
	}
	return {
		segLevels: r,
		excludedSegs: i
	};
}
function uS(e, t, n) {
	let r = 0;
	if (n) for (let n = 0; n < e.length; n++) tS(e[n], t).length && (r = n + 1);
	else for (; r < e.length && tS(e[r], t).length;) r++;
	return r;
}
function dS(e) {
	return e.map((e) => fS(e));
}
function fS(e) {
	return e.map(zS);
}
function pS(e, t, n = Infinity) {
	let r = [], i = /* @__PURE__ */ new Map(), a = !0, o = [];
	for (let s = 0; s < e.length; s++) for (let c of e[s]) {
		let e = t.get(AS(c));
		if (e === void 0) {
			a = !1;
			continue;
		}
		let { bottom: s, levelIndex: l } = mS(r, c, i, t);
		if (s + e <= n + oS) {
			for (; r.length <= l;) r.push([]);
			iS(r[l], c), i.set(AS(c), s);
		} else o.push(c);
	}
	return {
		placementSliceLevels: r,
		sliceCoords: i,
		isSettled: a,
		excludedSlices: o
	};
}
function mS(e, t, n, r) {
	let i = 0, a = 0;
	for (let o = 0; o < e.length; o++) {
		let s = e[o];
		for (let e of tS(s, t)) {
			let t = AS(e), s = n.get(t), c = r.get(t);
			s !== void 0 && c !== void 0 && (i = Math.max(i, s + c), a = o + 1);
		}
	}
	return {
		bottom: i,
		levelIndex: a
	};
}
function hS(e, t, n) {
	let r = pS(e.map((e) => e.filter((e) => n.has(AS(e)))), t).sliceCoords;
	for (let [e, t] of r) n.set(e, t);
}
function gS(e, t, n) {
	let r = AS(e), i = t.get(r), a = n.get(r);
	return i === void 0 || a === void 0 ? void 0 : i + a;
}
function _S(e, t, n, r, i, a) {
	let o = [], s = [], c = [], l = 0;
	for (let e of t) OS(o, e);
	for (d(); l < c.length;) {
		let t = c[l++], i = gS(t, n, r);
		if (i === void 0 || !u(t, i)) continue;
		n.delete(AS(t)), s.push(t);
		let a = OS(o, t);
		hS(e, r, n);
		for (let e of a) d(e);
	}
	return s;
	function u(e, t) {
		return t > i + oS || t > a + oS && tS(o, e).length > 0;
	}
	function d(t) {
		for (let i of e) {
			let e = t ? tS(i, t) : i;
			for (let t of e) {
				let e = gS(t, n, r);
				e !== void 0 && u(t, e) && c.push(t);
			}
		}
	}
}
function vS(e, t) {
	return t.length - 1;
}
function yS(e, t, n, r, i) {
	let a = [], o = [], s = [], c = {
		levels: e,
		moreLinkReservations: s,
		eventOrderStrict: n
	}, l = [];
	for (p(t); l.length;) {
		let e = l.pop();
		e.type === "fire" ? u(e.slice) : f(e.span);
	}
	return {
		sliceLevels: e,
		hiddenSlices: a
	};
	function u(t) {
		let n = bS(t, c);
		if (n !== null) {
			iS(e[n], t);
			return;
		}
		if (!r) {
			d(t);
			return;
		}
		let i = wS(t, c);
		if (!i) d(t);
		else {
			for (let t of i.slices) iS(e[i.levelIndex], t);
			for (let e of LS(t, i.slices)) d(e);
		}
	}
	function d(e) {
		a.push(e);
		let t = OS(o, e);
		if (i) for (let e = t.length - 1; e >= 0; e--) l.push({
			type: "moreLink",
			span: t[e]
		});
	}
	function f(t) {
		if (!e.length) return;
		let n = i(t, e);
		iS(s, {
			...t,
			levelIndex: n
		});
		for (let i = n; i < e.length; i++) {
			let n = e[i];
			for (let e of tS(n, t)) n.splice(n.indexOf(e), 1), r ? (d(RS(e, t)), p(LS(e, [t]))) : d(e);
		}
	}
	function p(e) {
		for (let t = e.length - 1; t >= 0; t--) l.push({
			type: "fire",
			slice: e[t]
		});
	}
}
function bS(e, t) {
	let n = xS(e, t);
	for (let r = n.min; r < n.maxExclusive; r++) if (!tS(t.levels[r], e).length) return r;
	return null;
}
function xS(e, t) {
	let { levels: n } = t, r = 0, i = n.length;
	for (let n of tS(t.moreLinkReservations, e)) i = Math.min(i, n.levelIndex);
	if (t.eventOrderStrict) for (let t = 0; t < n.length; t++) for (let a of tS(n[t], e)) a.sourceSeg.orderIndex < e.sourceSeg.orderIndex ? r = Math.max(r, t + 1) : a.sourceSeg.orderIndex > e.sourceSeg.orderIndex && (i = Math.min(i, t));
	return {
		min: r,
		maxExclusive: i
	};
}
var SS = 3, CS = .15;
function wS(e, t) {
	let n = null, r = eS(e);
	for (let i = 0; i < t.levels.length; i++) {
		let a = tS(t.levels[i], e);
		for (let e of t.moreLinkReservations) i >= e.levelIndex && rS(a, e);
		let o = LS(e, a).filter((e) => TS(e, i, t)).sort((e, t) => eS(t) - eS(e) || e.start - t.start), s = 0;
		for (let e = 1; e <= Math.min(SS, o.length) && (s += eS(o[e - 1]), !(s >= r - oS)); e++) {
			let t = {
				levelIndex: i,
				slices: o.slice(0, e),
				score: s / r - CS * (e - 1)
			};
			ES(t, n) && (n = t);
		}
	}
	return n && n.slices.sort(MS), n;
}
function TS(e, t, n) {
	let r = xS(e, n);
	return t >= r.min && t < r.maxExclusive;
}
function ES(e, t) {
	return !t || e.score > t.score ? !0 : e.score < t.score ? !1 : e.slices.length === t.slices.length ? e.levelIndex < t.levelIndex : e.slices.length < t.slices.length;
}
function DS(e) {
	let t = [];
	for (let n of e) OS(t, n);
	return kS(t);
}
function OS(e, t) {
	let n = nS(t, e), r = [], i = [t], a = t.start, o = t.end;
	for (let n of e) $x(n, t) ? (i.push(...n.hiddenSlices), a = Math.min(a, n.start), o = Math.max(o, n.end)) : r.push(n);
	return i.sort(MS), iS(r, {
		start: a,
		end: o,
		hiddenSlices: i
	}), e.splice(0, e.length, ...r), n;
}
function kS(e) {
	return e.map((e) => {
		let t = IS(e.hiddenSlices);
		return {
			key: AS(t[0]),
			start: e.start,
			end: e.end,
			hiddenSlices: t
		};
	});
}
function AS(e) {
	return jS(e) ? `${e.sourceSeg.key}:${e.start}:slice` : e.sourceSeg.key;
}
function jS(e) {
	return e.start !== e.sourceSeg.start || e.end !== e.sourceSeg.end;
}
function MS(e, t) {
	return e.sourceSeg.orderIndex - t.sourceSeg.orderIndex || e.start - t.start || t.end - e.end;
}
function NS(e) {
	return [...e].sort(MS);
}
function PS(e, t) {
	return e.start - t.start || e.sourceSeg.orderIndex - t.sourceSeg.orderIndex;
}
function FS(e) {
	return [...e].sort(PS);
}
function IS(e) {
	let t = [];
	for (let n of e) {
		let e = t[t.length - 1];
		e && e.sourceSeg === n.sourceSeg ? t[t.length - 1] = BS(zS(e.sourceSeg), e.start, Math.max(e.end, n.end)) : t.push(n);
	}
	return t;
}
function LS(e, t) {
	return nS(e, t).map((t) => BS(e, t.start, t.end));
}
function RS(e, t) {
	let n = $x(e, t);
	return n ? BS(e, n.start, n.end) : null;
}
function zS(e) {
	return {
		sourceSeg: e,
		start: e.start,
		end: e.end,
		isStart: e.isStart,
		isEnd: e.isEnd
	};
}
function BS(e, t, n) {
	return {
		sourceSeg: e.sourceSeg,
		start: t,
		end: n,
		isStart: e.isStart && t === e.start,
		isEnd: e.isEnd && n === e.end
	};
}
var VS = QS(150, 20);
function HS(e) {
	return e.map((e, t) => ({
		...e,
		key: _x(e),
		orderIndex: t
	}));
}
function US(e, t, n, r, i, a, o) {
	let s = HS(e);
	return XS(s, sS(s, r, i, t, n, o), o, a);
}
function WS(e, t, n, r, i, a, o, s) {
	let c = HS(e);
	return XS(c, cS(c, t, n, s, i, o, a), s, r);
}
function GS(e, t, n) {
	return {
		segs: am(e, (e) => KS(e, n) ?? []),
		hiddenSegs: am(t, (e) => KS(e.sourceSeg, n, e) ?? [])
	};
}
function KS(e, t, n = e) {
	if (n.start >= t + 1 || t >= n.end) return null;
	let { key: r, orderIndex: i, ...a } = e;
	return {
		...a,
		start: t,
		end: t + 1,
		isStart: a.isStart && e.start === t,
		isEnd: a.isEnd && e.end - 1 === t
	};
}
function qS(e, t) {
	return e === !0 || t === !0 ? "auto" : typeof e == "number" ? "maxEvents" : typeof t == "number" ? "maxEventRows" : "unlimited";
}
function JS(e, t, n, r) {
	switch (e) {
		case "auto": return r;
		case "maxEvents": return t;
		case "maxEventRows": return n;
		default: return Infinity;
	}
}
function YS(e) {
	return e === "maxEventRows";
}
function XS(e, t, n, r) {
	let { hiddenSlices: i, renderSlices: a, sliceCoords: o } = t, s = NS(i), c = ZS(a, r), l = Array.from({ length: r }, (t, n) => ({
		renderSlices: c[n],
		contentHeight: 0,
		...GS(e, s, n)
	}));
	for (let e of a) {
		let t = AS(e), r = o.get(t);
		if (r === void 0) continue;
		let i = r + n.get(t);
		for (let t = e.start; t < e.end; t += 1) l[t].contentHeight = Math.max(l[t].contentHeight, i);
	}
	return {
		columns: l,
		sliceCoords: o
	};
}
function ZS(e, t) {
	let n = Array.from({ length: t }, () => []);
	for (let t of e) n[t.start].push(t);
	for (let e of n) e.sort(MS);
	return n;
}
function QS(e, t) {
	return Math.max(1, Math.ceil(e / t));
}
var $S = 200;
function eC(e, t, n) {
	let { segLevels: r, excludedSegs: i } = lS(e, t, $S), a = yS(dS(r), fS(i), t, n);
	return {
		sliceLevels: a.sliceLevels,
		hiddenSlices: a.hiddenSlices
	};
}
function tC(e, t, n = (e) => e.sourceSeg.key, r = 20) {
	let i = [];
	for (let a = 0; a < e.length; a++) {
		let o = e[a];
		if (!o?.length) continue;
		let s = 0, c = o.map((e) => (s = Math.max(s, t.get(n(e)) ?? r), e));
		i.push({
			levelIndex: a,
			slices: c,
			thickness: s
		});
	}
	return i;
}
function nC(e, t, n, r) {
	let i = HS(e), a = eC(i, t, n);
	return {
		...a,
		hiddenSlices: NS(a.hiddenSlices),
		sourceSegs: i,
		columnCount: r
	};
}
function rC(e, t) {
	let n = Array.from({ length: e.columnCount }, () => []);
	for (let r of tC(e.sliceLevels, t, iC)) {
		let t = Array(e.columnCount).fill(null);
		for (let e of r.slices) t[e.start] = e;
		for (let i = 0; i < e.columnCount; i++) n[i].push({
			levelIndex: r.levelIndex,
			thickness: r.thickness,
			slice: t[i]
		});
	}
	return n;
}
function iC(e) {
	return `${e.sourceSeg.key}:${e.start}:${e.end}`;
}
var aC = H({ week: "narrow" }), oC = class extends J {
	constructor() {
		super(...arguments), this.headerHeightRefMap = new hx(() => {
			Q(this.handleSegPositioning);
		}), this.mainHeightRefMap = new hx(() => {
			(this.props.dayMaxEvents === !0 || this.props.dayMaxEventRows === !0) && Q(this.handleSegPositioning);
		}), this.sliceHeightRefMap = new hx(() => {
			Q(this.handleSegPositioning);
		}), this.handlePrintSegHeightChange = () => {
			Q(this.handlePrintSegHeights);
		}, this.printSegHeightRefMap = new hx(this.handlePrintSegHeightChange), this.buildWeekNumberRenderProps = q(sC), this.buildPrintPlan = q(nC), this.sortEventSegs = q(Qh), this.levelCapacity = VS, this.handleRootEl = (e) => {
			this.disconnectHeight?.(), this.disconnectHeight = void 0, Y(this.props.rootElRef, e), e ? this.disconnectHeight = rv(e, (e) => {
				Y(this.props.heightRef, e);
			}) : Y(this.props.heightRef, null);
		}, this.handleSegPositioning = () => {
			this._isUnmounting || this.props.forPrint || (this.updateAutoPlacementRatchets(), this.forceUpdate());
		}, this.handlePrintSegHeights = () => {
			!this._isUnmounting && this.props.forPrint && this.forceUpdate();
		};
	}
	render() {
		let { props: e, context: t, headerHeightRefMap: n, mainHeightRefMap: r } = this, { cells: i, tableMode: a } = e, { options: o } = t, s = e.cells[0].date, c = this.sortEventSegs(e.fgEventSegs, o.eventOrder), l = e.dayMaxEvents === !0 || e.dayMaxEventRows === !0, u = null, d = null, f = null, p = /* @__PURE__ */ new Map(), m = [], h = [];
		if (e.forPrint) u = this.buildPrintPlan(c, o.eventOrderStrict, o.eventSlicing, i.length), d = rC(u, this.printSegHeightRefMap.current);
		else {
			let t = qS(e.dayMaxEvents, e.dayMaxEventRows), [r, a] = this.computeFgDims(), s = t === "auto" ? WS(c, o.eventOrderStrict, o.eventSlicing, i.length, a, e.moreLinkHeight, this.levelCapacity, this.sliceHeightRefMap.current) : US(c, JS(t, e.dayMaxEvents, e.dayMaxEventRows, Infinity), YS(t), o.eventOrderStrict, o.eventSlicing, i.length, this.sliceHeightRefMap.current);
			if (f = s.columns, p = s.sliceCoords, r != null) for (let e = 0; e < i.length; e++) {
				let t = n.current.get(i[e].key), a = t == null ? void 0 : r - t;
				m.push(a), h.push(a == null ? void 0 : f[e].contentHeight + a);
			}
		}
		let g = this.getHighlightSegs(), _ = o.navLinks, v = dv(t, s, "week"), y = this.buildWeekNumberRenderProps(s, t, e.cellIsNarrow, _), b = i.map(() => []), x = e.showWeekNumbers && !e.cellIsMicro ? D(X, {
			tag: "div",
			attrs: {
				..._ ? fv(t, s, "week", v, !1) : {},
				role: void 0,
				"aria-hidden": !0
			},
			className: Ux,
			renderProps: y,
			generatorName: "inlineWeekNumberContent",
			customGenerator: o.inlineWeekNumberContent,
			defaultGenerator: P_,
			classNameGenerator: o.inlineWeekNumberClass,
			didMount: o.inlineWeekNumberDidMount,
			willUnmount: o.inlineWeekNumberWillUnmount
		}) : null;
		return a && x && b[0].push(D("div", {
			className: V(B.fillY, B.start0, B.pointerEventsNone),
			style: { width: this.computeSpanWidth(0, i.length) },
			children: x
		}, "week-number")), this.appendFillSegs(b, e.businessHourSegs, "non-business", zx), this.appendFillSegs(b, e.bgEventSegs, "bg-event", Bx), this.appendFillSegs(b, g, "highlight", Vx), D(a ? "tr" : "div", {
			role: e.role,
			"aria-label": e.role === "row" ? v : void 0,
			className: V(o.dayRowClass, e.className, a && B.borderless, !a && B.flexRow, !a && B.rel, !a && B.borderlessX, !a && B.borderlessTop, !a && !e.borderBottom && B.borderlessBottom, B.isolate),
			style: { flexBasis: a ? void 0 : e.basis },
			ref: this.handleRootEl,
			children: [!a && x, e.cells.map((t, i) => {
				let o = u ? GS(u.sourceSegs, u.hiddenSlices, i) : null, s = u ? null : f[i].renderSlices, c;
				return c = u ? this.renderPrintBandSlots(d[i]) : [...this.renderLevelFgSegs(m[i], s, p), ...this.renderMirrorFgSegs(i, m[i], p)], D(Kx, {
					dateProfile: e.dateProfile,
					todayRange: e.todayRange,
					date: t.date,
					isMajor: t.isMajor,
					isDisabled: t.isDisabled,
					showDayNumber: e.showDayNumbers,
					isNarrow: e.cellIsNarrow,
					isMicro: e.cellIsMicro,
					borderStart: !!i,
					borderBottom: e.borderBottom,
					tableMode: a,
					fills: b[i],
					segs: o ? o.segs : f[i].segs,
					hiddenSegs: o ? o.hiddenSegs : f[i].hiddenSegs,
					fgLiquidHeight: !u && l,
					fg: c,
					fgIsList: !!s?.length,
					eventDrag: u ? null : e.eventDrag,
					eventResize: u ? null : e.eventResize,
					eventSelection: e.eventSelection,
					renderProps: t.renderProps,
					dateSpanProps: t.dateSpanProps,
					attrs: t.attrs,
					className: t.className,
					fgHeight: u ? void 0 : h[i],
					width: e.colWidth,
					headerHeightRef: u ? void 0 : n.createRef(t.key),
					mainHeightRef: u ? void 0 : r.createRef(t.key)
				}, t.key);
			})]
		});
	}
	renderMirrorFgSegs(e, t, n) {
		let { props: r } = this, { eventSelection: i } = r, a = [];
		for (let o of this.getMirrorSegs()) {
			if (o.start !== e) continue;
			let s = _x(o), { eventRange: c } = o, { instanceId: l } = c.instance, u = t == null ? void 0 : t + (n.get(s) ?? 0), d = !!(r.eventDrag && r.eventDrag.affectedInstances[l]), f = !!(r.eventResize && r.eventResize.affectedInstances[l]), p = l === i;
			a.push(D(Zx, {
				attrs: { "aria-hidden": !0 },
				className: V(B.abs, B.start0, Wx),
				style: {
					top: u,
					width: this.computeSpanWidth(o.start, o.end)
				},
				heightRef: null,
				children: this.renderEventContent(o, c, {
					isDragging: d,
					isResizing: f,
					isMirror: !0,
					isSelected: p
				})
			}, `mirror:${s}`));
		}
		return a;
	}
	renderLevelFgSegs(e, t, n) {
		let { props: r } = this, { eventSelection: i } = r, a = [];
		for (let o of t) {
			let t = AS(o), s = n.get(t), { eventRange: c } = o.sourceSeg, { instanceId: l } = c.instance, u = e != null && s != null ? e + s : void 0, d = !!(r.eventDrag && r.eventDrag.affectedInstances[l]), f = !!(r.eventResize && r.eventResize.affectedInstances[l]), p = d || f || u == null, m = l === i;
			a.push(D(Zx, {
				attrs: { role: "listitem" },
				className: V(B.abs, B.start0, m ? Wx : Ux),
				style: {
					visibility: p ? "hidden" : void 0,
					top: u,
					bottom: u == null ? 0 : void 0,
					width: this.computeSpanWidth(o.start, o.end)
				},
				heightRef: this.sliceHeightRefMap.createRef(t),
				children: this.renderEventContent(o, c, {
					isDragging: d,
					isResizing: f,
					isSelected: m
				})
			}, t));
		}
		return a;
	}
	renderEventContent(e, t, n) {
		let { props: r } = this, i = Rx(e, t), a = r.cells[e.start]?.renderProps;
		return D(Dv, {
			display: i ? "list-item" : "row",
			eventRange: t,
			isStart: e.isStart,
			isEnd: e.isEnd,
			isDragging: !!n.isDragging,
			isResizing: !!n.isResizing,
			isMirror: !!n.isMirror,
			isSelected: !!n.isSelected,
			isNarrow: r.cellIsNarrow,
			defaultTimeFormat: Lx,
			defaultDisplayEventEnd: r.cells.length === 1,
			disableResizing: i,
			forcedTimeText: r.cellIsMicro ? "" : void 0,
			renderProps: a,
			...ig(t, r.todayRange)
		});
	}
	renderPrintBandSlots(e) {
		let { printSegHeightRefMap: t } = this;
		return e.map((e) => {
			let { slice: n } = e, r = null;
			if (n) {
				let e = iC(n);
				r = D(Zx, {
					className: V(B.rel, B.flowRoot, Ux),
					style: { width: this.computeSpanWidth(n.start, n.end) },
					heightRef: t.createRef(e),
					children: this.renderEventContent(n, n.sourceSeg.eventRange, {})
				}, e);
			}
			return D("div", {
				className: B.breakInsideAvoid,
				style: { height: e.thickness },
				children: r
			}, e.levelIndex);
		});
	}
	computeSpanWidth(e, t) {
		let n = t - e, r = `${n * 100}%`, i = this.props.tableMode && e === 0 ? 0 : Math.max(0, n - 1) * Cx;
		return i ? `calc(${r} + ${i}px)` : r;
	}
	appendFillSegs(e, t, n, r) {
		let { props: i, context: a } = this, { todayRange: o } = i;
		for (let s of t) e[s.start].push(D("div", {
			"aria-hidden": !0,
			className: V(B.fillY, B.start0, r),
			style: { width: this.computeSpanWidth(s.start, s.end) },
			children: n === "bg-event" ? D(bx, {
				eventRange: s.eventRange,
				isStart: s.isStart,
				isEnd: s.isEnd,
				isNarrow: i.cellIsNarrow,
				isVertical: !1,
				renderProps: i.cells[s.start]?.renderProps,
				...ig(s.eventRange, o)
			}) : Sx(n, a.options)
		}, `${n}:${ag(s.eventRange)}:${s.start}:${s.end}`));
	}
	componentDidMount() {
		this._isUnmounting = !1;
	}
	componentDidUpdate(e) {
		e.forPrint && !this.props.forPrint && (this.printSegHeightRefMap = new hx(this.handlePrintSegHeightChange));
	}
	componentWillUnmount() {
		this._isUnmounting = !0;
	}
	computeFgDims() {
		let { cells: e } = this.props, t = this.headerHeightRefMap.current, n = this.mainHeightRefMap.current, r, i, a = !0;
		for (let o of e) {
			if (o.isDisabled) continue;
			let e = t.get(o.key), s = n.get(o.key);
			if ((e == null || s == null) && (a = !1), e != null && ((r === void 0 || e > r) && (r = e), s != null)) {
				let t = e + s;
				(i === void 0 || t < i) && (i = t);
			}
		}
		return [r, a && i != null && r != null ? i - r : void 0];
	}
	updateAutoPlacementRatchets() {
		if (qS(this.props.dayMaxEvents, this.props.dayMaxEventRows) !== "auto") return;
		let [, e] = this.computeFgDims();
		if (e != null) {
			let t = Math.min(...this.sliceHeightRefMap.current.values());
			this.levelCapacity = Math.max(this.levelCapacity, QS(e, t));
		}
	}
	getMirrorSegs() {
		let { props: e } = this;
		return e.eventResize && e.eventResize.segs.length ? e.eventResize.segs : [];
	}
	getHighlightSegs() {
		let { props: e } = this;
		return e.eventDrag && e.eventDrag.segs.length ? e.eventDrag.segs : e.eventResize && e.eventResize.segs.length ? e.eventResize.segs : e.dateSelectionSegs;
	}
};
function sC(e, t, n, r) {
	let { dateEnv: i, options: a } = t, o = i.computeWeekNumber(e), s = i.formatToParts(e, a.weekNumberFormat || aC);
	return {
		num: o,
		text: fp(s),
		textParts: s,
		date: i.toDate(e),
		isNarrow: n,
		hasNavLink: r
	};
}
var cC = class {
	constructor(e, t) {
		let n = e.start, { end: r } = e, i = [], a = [], o = -1;
		for (; n < r;) t.isHiddenDay(n) ? i.push({
			kind: "hidden",
			previousIndex: o,
			nextIndex: o + 1
		}) : (o += 1, i.push({
			kind: "visible",
			index: o
		}), a.push(n)), n = L(n, 1);
		this.rangeStart = e.start, this.dates = a, this.entries = i, this.cnt = a.length;
	}
	sliceRange(e) {
		let t = this.getDateIndex(e.start), n = this.getDateIndex(L(e.end, -1)), r = lC(t), i = uC(n), a = Math.max(0, r), o = Math.min(this.cnt - 1, i);
		return a <= o ? {
			start: a,
			end: o + 1,
			isStart: t.kind === "visible" && r === a,
			isEnd: n.kind === "visible" && i === o
		} : null;
	}
	getDateIndex(e) {
		let t = Math.floor(ff(this.rangeStart, e));
		return t < 0 ? {
			kind: "before",
			index: -1
		} : t >= this.entries.length ? {
			kind: "after",
			index: this.cnt
		} : this.entries[t];
	}
};
function lC(e) {
	return e.kind === "hidden" ? e.nextIndex : e.index;
}
function uC(e) {
	return e.kind === "hidden" ? e.previousIndex : e.index;
}
function dC(e, t, n) {
	let r = new cC(e.renderRange, t), i = /year|month|week/.test(e.currentRangeUnit), a = !i && Av(e, n);
	return new ox(r, i, n, a === "day" ? void 0 : a, e.activeRange);
}
function fC(e, t, n) {
	return n == null ? [void 0, void 0] : n / e < t ? [t * e, t] : [n, void 0];
}
function pC(e, t, n) {
	let r = 0;
	for (let i of t) {
		let t = i[0].key, a = i[0].date, o = i[i.length - 1].date;
		if (e >= a && e <= o) return r;
		let s = n.get(t);
		if (s == null) return;
		r += s;
	}
	return r;
}
function mC(e, t, n, r, i) {
	let a = n ?? t / r, o = Math.floor(e / a), s = i ? r - o - 1 : o, c = o * a;
	return {
		col: s,
		left: c,
		right: c + a
	};
}
function hC(e, t, n) {
	let r = 0, i = 0, a = 0;
	for (let o of t) {
		let t = o[0].key;
		if (i = a, a = i + n.get(t), e < a) break;
		r++;
	}
	return {
		row: r,
		top: i,
		bottom: a
	};
}
function gC(e, t) {
	return e.querySelectorAll("[role=row]")[t];
}
function _C(e, t) {
	return e.querySelectorAll("[role=cell]")[t];
}
var vC = H({ weekday: "narrow" });
function yC(e, t, n) {
	return e || bC(t, n);
}
function bC(e, t) {
	return H(e ? t > 1 ? {
		weekday: "short",
		weekdayJustify: "start",
		day: "numeric",
		omitCommas: !0,
		omitTrailing: !0
	} : {
		weekday: "long",
		weekdayJustify: "start",
		day: "numeric",
		omitCommas: !0,
		omitTrailing: !0
	} : { weekday: "short" });
}
var xC = class extends gv {
	constructor() {
		super(...arguments), this.state = {}, this.splitBusinessHourSegs = q(vx), this.splitBgEventSegs = q(CC), this.splitFgEventSegs = q(vx), this.splitDateSelectionSegs = q(vx), this.splitEventDrag = q(yx), this.splitEventResize = q(yx), this.rowHeightRefMap = new hx((e, t) => {
			let { rowHeightRefMap: n } = this.props;
			n && n.handleValue(e, t);
		}), this.handleMoreLinkEl = (e) => {
			this.disconnectMoreLinkHeight?.(), this.disconnectMoreLinkHeight = void 0, e && (this.disconnectMoreLinkHeight = rv(e, (e) => {
				this._isUnmounting || this.setState({ moreLinkHeight: e });
			}));
		}, this.handleRootEl = (e) => {
			this.rootEl = e, e ? this.context.registerInteractiveComponent(this, {
				el: e,
				isHitComboAllowed: this.props.isHitComboAllowed
			}) : this.context.unregisterInteractiveComponent(this);
		};
	}
	render() {
		let { props: e, state: t, context: n, rowHeightRefMap: r } = this, { options: i } = n, { cellRows: a, tableMode: o } = e, s = a.length, c = a[0]?.[0]?.key || "", l = this.splitFgEventSegs(e.fgEventSegs, s), u = this.splitBgEventSegs(e.bgEventSegs, s), d = this.splitBusinessHourSegs(e.businessHourSegs, s), f = this.splitDateSelectionSegs(e.dateSelectionSegs, s), p = this.splitEventDrag(e.eventDrag, s), m = this.splitEventResize(e.eventResize, s), h = F_(i), g = !e.forPrint && !h, _ = wC(e.visibleWidth, s, h, i), v = !e.forPrint && qS(e.dayMaxEvents, e.dayMaxEventRows) === "auto";
		return D(w, { children: [D(o ? "tbody" : "div", {
			role: "rowgroup",
			className: V(e.className, !o && !e.forPrint && B.flexCol),
			style: o ? void 0 : { width: e.width },
			ref: this.handleRootEl,
			children: a.map((n, a) => D(oC, {
				role: "row",
				dateProfile: e.dateProfile,
				todayRange: e.todayRange,
				cells: n,
				cellIsNarrow: e.cellIsNarrow,
				cellIsMicro: e.cellIsMicro,
				showDayNumbers: s > 1,
				showWeekNumbers: s > 1 && i.weekNumbers,
				forPrint: e.forPrint,
				tableMode: o,
				borderBottom: a < s - 1,
				className: g ? B.grow : void 0,
				fgEventSegs: l[a],
				bgEventSegs: u[a],
				businessHourSegs: d[a],
				dateSelectionSegs: f[a],
				eventSelection: e.eventSelection,
				eventDrag: p[a],
				eventResize: m[a],
				dayMaxEvents: e.dayMaxEvents,
				dayMaxEventRows: e.dayMaxEventRows,
				colWidth: e.colWidth,
				basis: _,
				moreLinkHeight: t.moreLinkHeight,
				heightRef: r.createRef(n[0].key)
			}, c + ":" + n[0].key))
		}), v && D(Mx, {
			num: 1,
			display: "row",
			isNarrow: e.cellIsNarrow,
			isMicro: e.cellIsMicro,
			elRef: this.handleMoreLinkEl,
			className: B.offscreen,
			attrs: { "aria-hidden": !0 }
		})] });
	}
	componentDidMount() {
		this._isUnmounting = !1;
	}
	componentWillUnmount() {
		this._isUnmounting = !0;
	}
	queryHit(e, t, n, r) {
		let { props: i } = this, a = i.cellRows[0].length, { col: o, left: s, right: c } = mC(t, r, i.colWidth, a, e), { row: l, top: u, bottom: d } = hC(n, i.cellRows, this.rowHeightRefMap.current), f = i.cellRows[l][o];
		if (f.isInert) return null;
		let p = f.date, m = L(p, 1);
		return {
			dateProfile: i.dateProfile,
			dateSpan: {
				range: {
					start: p,
					end: m
				},
				allDay: !0,
				...f.dateSpanProps
			},
			getDayEl: () => _C(gC(this.rootEl, l), o),
			rect: {
				left: s,
				right: c,
				top: u,
				bottom: d
			},
			layer: 0
		};
	}
};
function SC(e) {
	return e.eventRange.def.allDay;
}
function CC(e, t) {
	return vx(e.filter(SC), t);
}
function wC(e, t, n, r) {
	if (e != null) {
		let i = e / r.aspectRatio / 6;
		return t > 6 || n ? i : 0;
	}
	return 0;
}
var TC = class extends J {
	constructor() {
		super(...arguments), this.state = {}, this.buildDayHeaderText = q(EC), this.handleInnerEl = (e) => {
			this.disconnectSize &&= (this.disconnectSize(), void 0), e ? this.disconnectSize = tv(e, (e, t) => {
				this._isUnmounting || (Y(this.props.innerHeightRef, t), this.setState({ innerWidth: e }));
			}) : Y(this.props.innerHeightRef, null);
		};
	}
	render() {
		let { props: e, state: t, context: n } = this, { renderConfig: r, dataConfig: i, tableMode: a } = e, o = i.colSpan || 1, s = e.colWidth == null ? void 0 : e.colWidth * o, c = !a && s == null, l = c && o > 1, u = a ? void 0 : l ? {
			flexGrow: o,
			flexBasis: (o - 1) * Cx + (e.borderStart ? Cx : 0),
			minWidth: 0
		} : { width: s }, d = a ? "th" : "div", f = V(i.className, B.noMargin, B.noPadding, !a && B.flexCol, B.borderlessTop, B.borderlessEnd, !e.borderStart && B.borderlessStart, !(a && e.borderBottom) && B.borderlessBottom, c && !l && B.liquid);
		if (i.blank) return D(d, {
			role: "cell",
			"aria-colspan": i.colSpan,
			colSpan: a ? o : void 0,
			...i.attrs,
			className: V(f, Z(i.blank.classNameGenerator, {
				...i.renderProps,
				isNarrow: e.cellIsNarrow
			})),
			style: u
		});
		let p = i.renderProps.isDisabled, m = r.dayHeaderFormat ? this.buildDayHeaderRenderProps(i.renderProps, e.cellIsNarrow, e.rowLevel, e.cellIsMicro, i.dateMarker, r.dayHeaderFormat, !!r.datesRepDistinctDays, n.dateEnv) : {
			...i.renderProps,
			isNarrow: e.cellIsNarrow,
			level: e.rowLevel
		}, h = r.align, g = typeof h == "function" ? h({
			level: e.rowLevel,
			inPopover: i.renderProps.inPopover,
			isNarrow: e.cellIsNarrow
		}) : h, _ = r.sticky, v = !a && e.rowLevel > 0 && _ !== !1 && (g !== "center" || s != null && e.viewportWidth != null && s > e.viewportWidth * .75), y;
		v && (g === "center" ? t.innerWidth != null && (y = `calc(50% - ${t.innerWidth / 2}px)`) : y = typeof _ == "number" || typeof _ == "string" ? _ : 0);
		let b = g === "center" ? B.alignCenter : g === "end" ? B.alignEnd : B.alignStart;
		return D(X, {
			tag: d,
			attrs: {
				role: "columnheader",
				"aria-colspan": i.colSpan,
				colSpan: a ? o : void 0,
				...i.attrs
			},
			className: V(f, !a && b, !v && B.crop),
			style: u,
			renderProps: m,
			generatorName: r.generatorName,
			customGenerator: r.customGenerator,
			defaultGenerator: P_,
			classNameGenerator: p ? void 0 : r.classNameGenerator,
			didMount: r.didMount,
			willUnmount: r.willUnmount,
			children: (e) => D(w, { children: [i.a11yText && D("div", {
				className: B.srOnly,
				children: i.a11yText
			}), D("div", {
				"aria-hidden": i.a11yText ? !0 : void 0,
				ref: this.handleInnerEl,
				className: V(B.flexCol, B.noShrink, B.whiteSpaceNoWrap, a && b, v && B.sticky),
				style: {
					left: y,
					right: y
				},
				children: D(e, {
					tag: "div",
					attrs: i.innerAttrs,
					className: Z(r.innerClassNameGenerator, m)
				})
			})] })
		});
	}
	componentDidMount() {
		this._isUnmounting = !1;
	}
	componentWillUnmount() {
		this._isUnmounting = !0;
	}
	buildDayHeaderRenderProps(e, t, n, r, i, a, o, s) {
		let c = this.buildDayHeaderText(i, a, o, s), l = r ? this.buildDayHeaderText(i, vC, !1, s) : c;
		return {
			...e,
			isNarrow: t,
			level: n,
			text: l.text,
			textParts: l.textParts,
			weekdayText: r ? l.text : c.weekdayText,
			dayNumberText: c.dayNumberText,
			monthText: c.monthText
		};
	}
};
function EC(e, t, n, r) {
	let i = r.formatToParts(e, t);
	return {
		text: fp(i),
		textParts: i,
		weekdayText: cv(i),
		dayNumberText: n ? lv(i) : "",
		monthText: n ? uv(i) : ""
	};
}
var DC = class extends J {
	constructor() {
		super(...arguments), this.innerHeightRefMap = new hx(() => {
			Q(this.handleInnerHeights);
		}), this.handleInnerHeights = () => {
			if (this._isUnmounting) return;
			let e = this.innerHeightRefMap.current, t = 0;
			for (let n of e.values()) t = Math.max(t, n);
			this.currentInnerHeight !== t && (this.currentInnerHeight = t, Y(this.props.innerHeightRef, t));
		};
	}
	render() {
		let { props: e, context: t } = this, { tableMode: n } = e, { options: r } = t;
		return D(n ? "tr" : "div", {
			role: e.role,
			"aria-rowindex": e.rowIndex == null ? void 0 : 1 + e.rowIndex,
			className: V(r.dayHeaderRowClass, e.className, n && B.borderless, !n && B.flexRow, !n && B.contentBox, !n && B.borderlessX, !n && B.borderlessTop, !n && !e.borderBottom && B.borderlessBottom),
			style: { height: e.height },
			children: e.dataConfigs.map((t, r) => D(TC, {
				renderConfig: e.renderConfig,
				dataConfig: t,
				borderStart: !!r,
				colWidth: e.colWidth,
				viewportWidth: e.viewportWidth,
				innerHeightRef: this.innerHeightRefMap.createRef(t.key),
				cellIsNarrow: e.cellIsNarrow,
				cellIsMicro: e.cellIsMicro,
				rowLevel: e.rowLevel,
				tableMode: n,
				borderBottom: e.borderBottom
			}, t.key))
		});
	}
	componentDidMount() {
		this._isUnmounting = !1;
	}
	componentWillUnmount() {
		this._isUnmounting = !0, this.currentInnerHeight = void 0, Y(this.props.innerHeightRef, null);
	}
}, OC = class extends J {
	render() {
		let { props: e } = this, { headerTiers: t, tableMode: n } = e;
		return t.map((r, i) => x(DC, {
			...r,
			key: i,
			role: "row",
			borderBottom: i < t.length - 1,
			colWidth: e.colWidth,
			viewportWidth: e.viewportWidth,
			cellIsNarrow: e.cellIsNarrow,
			cellIsMicro: e.cellIsMicro,
			rowLevel: t.length - i - 1,
			tableMode: n
		}));
	}
}, kC = class extends J {
	render() {
		let { props: e, context: t } = this, { options: n } = t, r = {
			borderlessX: e.borderlessX,
			borderlessTop: e.borderlessTop,
			borderlessBottom: e.borderlessBottom,
			multiMonthColumns: e.multiMonthColumns
		};
		return D("table", {
			role: "presentation",
			className: V(Z(n.tableClass, r), B.printTable),
			style: e.style,
			children: [
				D("colgroup", { children: e.cellRows[0].map((e) => D("col", {}, e.key)) }),
				e.showHeader && D("thead", {
					ref: e.headerElRef,
					role: "rowgroup",
					className: Z(n.tableHeaderClass, {
						...r,
						isSticky: !1
					}),
					children: [D(OC, {
						tableMode: !0,
						headerTiers: e.headerTiers,
						cellIsNarrow: e.cellIsNarrow,
						cellIsMicro: e.cellIsMicro
					}), D("tr", {
						role: "presentation",
						children: D("th", {
							role: "presentation",
							colSpan: e.cellRows[0].length,
							className: V(B.noPadding, Z(n.dayHeaderDividerClass, {
								isSticky: !1,
								multiMonthColumns: e.multiMonthColumns,
								options: { allDaySlot: !!n.allDaySlot }
							}))
						})
					})]
				}),
				D(xC, {
					dateProfile: e.dateProfile,
					todayRange: e.todayRange,
					cellRows: e.cellRows,
					forPrint: !0,
					tableMode: !0,
					className: Z(n.tableBodyClass, r),
					dayMaxEvents: void 0,
					dayMaxEventRows: e.dayMaxEventRows,
					fgEventSegs: e.fgEventSegs,
					bgEventSegs: e.bgEventSegs,
					businessHourSegs: e.businessHourSegs,
					dateSelectionSegs: [],
					eventDrag: null,
					eventResize: null,
					eventSelection: e.eventSelection,
					visibleWidth: e.visibleWidth,
					cellIsNarrow: e.cellIsNarrow,
					cellIsMicro: e.cellIsMicro,
					rowHeightRefMap: e.rowHeightRefMap
				})
			]
		});
	}
}, AC = class extends J {
	render() {
		let { props: e } = this;
		return D("div", {
			role: "rowgroup",
			className: V(e.className, B.flexCol, e.width == null && B.liquid),
			style: { width: e.width },
			children: D(OC, {
				headerTiers: e.headerTiers,
				colWidth: e.colWidth,
				viewportWidth: e.viewportWidth,
				cellIsNarrow: e.cellIsNarrow,
				cellIsMicro: e.cellIsMicro
			})
		});
	}
}, jC = class extends J {
	constructor() {
		super(...arguments), this.state = {}, this.handleScroller = (e) => {
			Y(this.props.scrollerRef, e);
		}, this.handleTotalWidth = (e) => {
			this._isUnmounting || this.setState({ totalWidth: e });
		}, this.handleClientWidth = (e) => {
			this._isUnmounting || this.setState({ clientWidth: e });
		};
	}
	render() {
		let { props: e, state: t, context: n } = this, { options: r } = n, { borderlessX: i, borderlessTop: a, borderlessBottom: o } = rm(r), { totalWidth: s, clientWidth: c } = t, l = s != null && c != null ? s - c : void 0;
		l < 3 && (l = 0);
		let u = !e.forPrint && !F_(r), d = !e.forPrint && I_(r), f = e.cellRows[0].length, p = c == null ? void 0 : c / f, m = p != null && p <= 60, h = m || p != null && p <= r.dayNarrowWidth;
		return e.forPrint ? D(kC, {
			dateProfile: e.dateProfile,
			todayRange: e.todayRange,
			cellRows: e.cellRows,
			headerTiers: e.headerTiers,
			showHeader: !!r.dayHeaders,
			fgEventSegs: e.fgEventSegs,
			bgEventSegs: e.bgEventSegs,
			businessHourSegs: e.businessHourSegs,
			eventSelection: e.eventSelection,
			dayMaxEventRows: r.dayMaxEventRows,
			borderlessX: i,
			borderlessTop: a,
			borderlessBottom: o,
			multiMonthColumns: 0,
			visibleWidth: s,
			cellIsNarrow: h,
			cellIsMicro: m,
			rowHeightRefMap: e.rowHeightRefMap
		}) : D(w, { children: [
			r.dayHeaders && D("div", {
				className: V(Z(r.tableHeaderClass, {
					isSticky: d,
					borderlessX: i,
					borderlessTop: a,
					borderlessBottom: o,
					multiMonthColumns: 0
				}), B.flexCol, d && B.tableHeaderSticky),
				children: [D("div", {
					className: B.flexRow,
					children: [D(AC, {
						headerTiers: e.headerTiers,
						cellIsNarrow: h,
						cellIsMicro: m
					}), !!l && D("div", {
						className: V(Z(r.fillerClass, { inTableHeader: !0 }), B.borderlessY, B.borderlessEnd),
						style: { minWidth: l }
					})]
				}), D("div", { className: Z(r.dayHeaderDividerClass, {
					isSticky: d,
					multiMonthColumns: 0,
					options: { allDaySlot: !!r.allDaySlot }
				}) })]
			}),
			D(yv, {
				vertical: u,
				className: V(Z(r.tableBodyClass, {
					borderlessX: i,
					borderlessTop: a,
					borderlessBottom: o,
					multiMonthColumns: 0
				}), !e.forPrint && B.flexCol, u && B.liquid),
				ref: this.handleScroller,
				clientWidthRef: this.handleClientWidth,
				children: D(xC, {
					dateProfile: e.dateProfile,
					todayRange: e.todayRange,
					cellRows: e.cellRows,
					forPrint: e.forPrint,
					isHitComboAllowed: e.isHitComboAllowed,
					className: B.grow,
					dayMaxEvents: e.forPrint ? void 0 : r.dayMaxEvents,
					dayMaxEventRows: r.dayMaxEventRows,
					fgEventSegs: e.fgEventSegs,
					bgEventSegs: e.bgEventSegs,
					businessHourSegs: e.businessHourSegs,
					dateSelectionSegs: e.dateSelectionSegs,
					eventDrag: e.eventDrag,
					eventResize: e.eventResize,
					eventSelection: e.eventSelection,
					visibleWidth: s,
					cellIsNarrow: h,
					cellIsMicro: m,
					rowHeightRefMap: e.rowHeightRefMap
				})
			}),
			D(gx, { widthRef: this.handleTotalWidth })
		] });
	}
	componentDidMount() {
		this._isUnmounting = !1;
	}
	componentWillUnmount() {
		this._isUnmounting = !0;
	}
}, MC = class extends J {
	constructor() {
		super(...arguments), this.rootElRef = C();
	}
	render() {
		let { props: e } = this;
		return D("div", {
			ref: this.rootElRef,
			className: V(B.footerScrollbar, e.isSticky && B.footerScrollbarSticky),
			children: D(yv, {
				horizontal: !0,
				ref: e.scrollerRef,
				children: D("div", { style: { minWidth: e.canvasWidth } })
			})
		});
	}
	componentDidMount() {
		this._isUnmounting = !1, this.disconnectHeight = rv(this.rootElRef.current, (e) => {
			this._isUnmounting || Y(this.props.scrollbarWidthRef, e);
		});
	}
	componentWillUnmount() {
		this._isUnmounting = !0, this.disconnectHeight(), Y(this.props.scrollbarWidthRef, null);
	}
}, NC = class extends J {
	constructor() {
		super(...arguments), this.state = {}, this.headerScrollerRef = C(), this.bodyScrollerRef = C(), this.footerScrollerRef = C(), this.handleTotalWidth = (e) => {
			this._isUnmounting || this.setState({ totalWidth: e });
		}, this.handleClientWidth = (e) => {
			this._isUnmounting || this.setState({ clientWidth: e });
		};
	}
	render() {
		let { props: e, state: t, context: n } = this, { options: r } = n, { borderlessX: i, borderlessTop: a, borderlessBottom: o } = rm(r), { totalWidth: s, clientWidth: c } = t, l = s != null && c != null ? s - c : void 0, u = !e.forPrint && !F_(r), d = !e.forPrint && I_(r), f = !e.forPrint && L_(r), p = e.cellRows[0].length, [m, h] = fC(p, e.dayMinWidth, c), g = h ?? (c == null ? void 0 : c / p), _ = g != null && g <= 60, v = _ || g != null && g <= r.dayNarrowWidth;
		return e.forPrint ? D(kC, {
			dateProfile: e.dateProfile,
			todayRange: e.todayRange,
			cellRows: e.cellRows,
			headerTiers: e.headerTiers,
			showHeader: !!r.dayHeaders,
			fgEventSegs: e.fgEventSegs,
			bgEventSegs: e.bgEventSegs,
			businessHourSegs: e.businessHourSegs,
			eventSelection: e.eventSelection,
			dayMaxEventRows: r.dayMaxEventRows,
			borderlessX: i,
			borderlessTop: a,
			borderlessBottom: o,
			multiMonthColumns: 0,
			visibleWidth: s,
			cellIsNarrow: v,
			cellIsMicro: _,
			rowHeightRefMap: e.rowHeightRefMap
		}) : D(w, { children: [
			r.dayHeaders && D("div", {
				className: V(Z(r.tableHeaderClass, {
					isSticky: d,
					borderlessX: i,
					borderlessTop: a,
					borderlessBottom: o,
					multiMonthColumns: 0
				}), B.flexCol, d && B.tableHeaderSticky),
				children: [D(yv, {
					horizontal: !0,
					hideScrollbars: !0,
					className: B.flexRow,
					ref: this.headerScrollerRef,
					children: [D(AC, {
						headerTiers: e.headerTiers,
						colWidth: h,
						viewportWidth: c,
						width: m,
						cellIsNarrow: v,
						cellIsMicro: _
					}), !!l && D("div", {
						className: V(Z(r.fillerClass, { inTableHeader: !0 }), B.borderlessY, B.borderlessEnd),
						style: { minWidth: l }
					})]
				}), D("div", { className: Z(r.dayHeaderDividerClass, {
					isSticky: d,
					multiMonthColumns: 0,
					options: { allDaySlot: !!r.allDaySlot }
				}) })]
			}),
			D(yv, {
				vertical: u,
				horizontal: !0,
				hideScrollbars: f || e.forPrint,
				className: V(Z(r.tableBodyClass, {
					borderlessX: i,
					borderlessTop: a,
					borderlessBottom: o,
					multiMonthColumns: 0
				}), !e.forPrint && B.flexCol, u && B.liquid),
				ref: this.bodyScrollerRef,
				clientWidthRef: this.handleClientWidth,
				children: D(xC, {
					dateProfile: e.dateProfile,
					todayRange: e.todayRange,
					cellRows: e.cellRows,
					forPrint: e.forPrint,
					isHitComboAllowed: e.isHitComboAllowed,
					className: B.grow,
					dayMaxEvents: e.forPrint ? void 0 : r.dayMaxEvents,
					dayMaxEventRows: r.dayMaxEventRows,
					fgEventSegs: e.fgEventSegs,
					bgEventSegs: e.bgEventSegs,
					businessHourSegs: e.businessHourSegs,
					dateSelectionSegs: e.dateSelectionSegs,
					eventDrag: e.eventDrag,
					eventResize: e.eventResize,
					eventSelection: e.eventSelection,
					colWidth: h,
					width: m,
					visibleWidth: s,
					cellIsNarrow: v,
					cellIsMicro: _,
					rowHeightRefMap: e.rowHeightRefMap
				})
			}),
			!!f && D(MC, {
				isSticky: !0,
				canvasWidth: m,
				scrollerRef: this.footerScrollerRef
			}),
			D(gx, { widthRef: this.handleTotalWidth })
		] });
	}
	componentDidMount() {
		this._isUnmounting = !1;
		let e = R_(this.context.pluginHooks);
		this.syncedScroller = new e(!0), Y(this.props.scrollerRef, this.syncedScroller), this.updateSyncedScroller();
	}
	componentDidUpdate() {
		this.updateSyncedScroller();
	}
	componentWillUnmount() {
		this._isUnmounting = !0, this.syncedScroller.destroy();
	}
	updateSyncedScroller() {
		this.syncedScroller.handleChildren([
			this.headerScrollerRef.current,
			this.bodyScrollerRef.current,
			this.footerScrollerRef.current
		]);
	}
}, PC = class extends J {
	constructor() {
		super(...arguments), this.scrollerRef = C(), this.rowHeightRefMap = new hx(() => {
			Q(this.updateScrollY);
		}), this.scrollDate = null, this.scrollY = null, this.updateScrollY = () => {
			if (this._isUnmounting) return;
			let e = this.rowHeightRefMap.current, t = this.scrollerRef.current;
			if (t && this.scrollDate) {
				let n = pC(this.scrollDate, this.props.cellRows, e);
				n != null && (n && n++, t.scrollTo({ y: n }));
			}
		}, this.handleScrollEnd = (e) => {
			e && (this.scrollDate = null, this.scrollY = this.scrollerRef.current?.y ?? null);
		};
	}
	render() {
		let { props: e, context: t } = this, { options: n } = t, { borderlessX: r, borderlessTop: i, borderlessBottom: a } = rm(n), o = e.forPrint ? [] : e.dateSelectionSegs, s = e.forPrint ? null : e.eventDrag, c = e.forPrint ? null : e.eventResize, l = {
			...e,
			dateSelectionSegs: o,
			eventDrag: s,
			eventResize: c,
			scrollerRef: this.scrollerRef,
			rowHeightRefMap: this.rowHeightRefMap
		};
		return D(mv, {
			viewSpec: t.viewSpec,
			attrs: {
				role: "table",
				"aria-rowcount": e.headerTiers.length + e.cellRows.length,
				"aria-colcount": e.cellRows[0].length,
				"aria-labelledby": e.labelId,
				"aria-label": e.labelStr
			},
			className: V(e.className, !e.forPrint && B.flexCol, !e.forPrint && Z(n.tableClass, {
				borderlessX: r,
				borderlessTop: i,
				borderlessBottom: a,
				multiMonthColumns: 0
			})),
			children: n.dayMinWidth ? D(NC, {
				...l,
				dayMinWidth: n.dayMinWidth
			}) : D(jC, { ...l })
		});
	}
	componentDidMount() {
		this._isUnmounting = !1, this.props.forPrint || (!this.scrollDateProfile || this.props.dateProfile !== this.scrollDateProfile && this.context.options.scrollTimeReset ? this.resetScroll() : this.scrollDate ? this.updateScrollY() : this.scrollY != null && this.scrollerRef.current?.scrollTo({ y: this.scrollY }), this.scrollDateProfile = this.props.dateProfile, this.scrollerRef.current?.addScrollEndListener(this.handleScrollEnd));
	}
	componentDidUpdate(e) {
		this.scrollDateProfile = this.props.dateProfile, e.forPrint && !this.props.forPrint && (this.scrollerRef.current?.addScrollEndListener(this.handleScrollEnd), this.resetScroll()), e.dateProfile !== this.props.dateProfile && this.context.options.scrollTimeReset && this.resetScroll();
	}
	componentWillUnmount() {
		this._isUnmounting = !0, this.scrollerRef.current?.removeScrollEndListener(this.handleScrollEnd);
	}
	resetScroll() {
		this.scrollDate = this.props.dateProfile.currentDate, this.updateScrollY(), this.scrollerRef.current?.scrollTo({ x: 0 });
	}
}, FC = class extends kv {
	buildRenderRange(e, t, n) {
		let r = super.buildRenderRange(e, t, n), { props: i } = this;
		return IC({
			currentRange: r,
			snapToWeek: /^(year|month)$/.test(t),
			fixedWeekCount: i.fixedWeekCount,
			dateEnv: i.dateEnv
		});
	}
};
function IC(e) {
	let { dateEnv: t, currentRange: n } = e, { start: r, end: i } = n, a;
	if (e.snapToWeek && (r = t.startOfWeek(r), a = t.startOfWeek(i), a.valueOf() !== i.valueOf() && (i = lf(a, 1))), e.fixedWeekCount) {
		let e = t.startOfWeek(t.startOfMonth(L(n.end, -1))), r = Math.ceil(df(e, i));
		i = lf(i, 6 - r);
	}
	return {
		start: r,
		end: i
	};
}
var LC = {
	name: "daygrid",
	initialView: "dayGridMonth",
	views: {
		dayGrid: {
			component: class extends J {
				constructor() {
					super(...arguments), this.buildDayTableModel = q(dC), this.buildDateRowConfigs = q(dx), this.createDayHeaderFormatter = q(yC), this.slicer = new cx();
				}
				render() {
					let { props: e, context: t } = this, { dateProfile: n } = e, { options: r, dateEnv: i } = t, a = this.buildDayTableModel(n, t.dateProfileGenerator, i), o = a.rowCount === 1, s = this.createDayHeaderFormatter(t.options.dayHeaderFormat, o, a.colCount), c = this.slicer.sliceProps(e, n, r.nextDayThreshold, t, a);
					return D(iv, {
						unit: "day",
						children: (r, i) => {
							let l = this.buildDateRowConfigs(a.headerDates, o, n, i, s, t);
							return D(PC, {
								labelId: e.labelId,
								labelStr: e.labelStr,
								dateProfile: n,
								todayRange: i,
								cellRows: a.cellRows,
								forPrint: e.forPrint,
								className: e.className,
								headerTiers: l,
								fgEventSegs: c.fgEventSegs,
								bgEventSegs: c.bgEventSegs,
								businessHourSegs: c.businessHourSegs,
								dateSelectionSegs: c.dateSelectionSegs,
								eventDrag: c.eventDrag,
								eventResize: c.eventResize,
								eventSelection: c.eventSelection
							});
						}
					});
				}
			},
			dateProfileGeneratorClass: FC
		},
		dayGridDay: {
			type: "dayGrid",
			duration: { days: 1 }
		},
		dayGridWeek: {
			type: "dayGrid",
			duration: { weeks: 1 }
		},
		dayGridMonth: {
			type: "dayGrid",
			duration: { months: 1 },
			fixedWeekCount: !0
		},
		dayGridYear: {
			type: "dayGrid",
			duration: { years: 1 }
		}
	}
};
// ../../../../../localhost/Pushword/packages/newsletter/node_modules/fullcalendar/chunks/53f7335e.js
function RC(e, t, n = {}) {
	let { slotRange: r, majorUnit: i = "", activeRange: a } = n;
	return e.dates.map((e) => ({
		key: e.toISOString(),
		date: e,
		range: r ? {
			start: t.add(e, r.slotMinTime),
			end: t.add(e, r.slotMaxTime)
		} : {
			start: e,
			end: L(e, 1)
		},
		isMajor: i ? jv(e, i, t) : !1,
		isDisabled: a === null || a !== void 0 && !Vf(a, e)
	}));
}
var zC = eh(), BC = class {
	constructor() {
		this.getKeysForEventDefs = q(this._getKeysForEventDefs), this.splitDateSelection = q(this._splitDateSpan), this.splitEventStore = q(this._splitEventStore), this.splitEventDrag = q(this._splitInteraction), this.splitEventResize = q(this._splitInteraction), this.eventUiBuilders = {};
	}
	splitProps(e) {
		let t = this.getKeyInfo(e), n = this.getKeysForEventDefs(e.eventStore), r = this.splitDateSelection(e.dateSelection), i = this.splitEventStore(e.eventStore, n), a = this.splitEventDrag(e.eventDrag), o = this.splitEventResize(e.eventResize), s = {};
		this.eventUiBuilders = um(t, (e, t) => this.eventUiBuilders[t] || q(VC));
		for (let n in t) {
			let c = t[n], l = i[n] || zC, u = this.eventUiBuilders[n];
			s[n] = {
				businessHours: c.businessHours || e.businessHours,
				dateSelection: r[n] || null,
				eventStore: l,
				eventUiBases: u(e.eventUiBases, c.ui),
				eventDrag: a[n] || null,
				eventResize: o[n] || null,
				eventSelection: l.instances[e.eventSelection] ? e.eventSelection : ""
			};
		}
		return s;
	}
	_splitDateSpan(e) {
		let t = {};
		if (e) {
			let n = this.getKeysForDateSpan(e);
			for (let r of n) t[r] = e;
		}
		return t;
	}
	_getKeysForEventDefs(e) {
		return um(e.defs, (e) => this.getKeysForEventDef(e));
	}
	_splitEventStore(e, t) {
		let { defs: n, instances: r } = e, i = {};
		for (let e in n) for (let r of t[e]) i[r] || (i[r] = eh()), i[r].defs[e] = n[e];
		for (let e in r) {
			let n = r[e];
			for (let r of t[n.defId]) i[r] && (i[r].instances[e] = n);
		}
		return i;
	}
	_splitInteraction(e) {
		let t = {};
		if (e) {
			let n = this._splitEventStore(e.affectedEvents, this._getKeysForEventDefs(e.affectedEvents)), r = this._getKeysForEventDefs(e.mutatedEvents), i = this._splitEventStore(e.mutatedEvents, r), a = (r) => {
				t[r] || (t[r] = {
					affectedEvents: n[r] || zC,
					mutatedEvents: i[r] || zC,
					isEvent: e.isEvent
				});
			};
			for (let e in n) a(e);
			for (let e in i) a(e);
		}
		return t;
	}
};
function VC(e, t) {
	return t ? {
		...e,
		sectionUi: e.sectionUi ? ch([e.sectionUi, t]) : t
	} : e;
}
var HC = class extends BC {
	getKeyInfo() {
		return {
			allDay: {},
			timed: {}
		};
	}
	getKeysForDateSpan(e) {
		return e.allDay ? ["allDay"] : ["timed"];
	}
	getKeysForEventDef(e) {
		return e.allDay ? qh(e) ? ["timed", "allDay"] : ["allDay"] : ["timed"];
	}
}, UC = class extends ix {
	sliceRange(e, t) {
		let n = [];
		for (let r = 0; r < t.length; r += 1) {
			let i = Lf(e, t[r]);
			i && n.push({
				startDate: i.start,
				endDate: i.end,
				isStart: i.start.valueOf() === e.start.valueOf(),
				isEnd: i.end.valueOf() === e.end.valueOf(),
				col: r
			});
		}
		return n;
	}
};
function WC(e, t) {
	let n = [], r = 0;
	for (; r < t; r += 1) n.push([]);
	if (e) for (r = 0; r < e.length; r += 1) n[e[r].col].push(e[r]);
	return n;
}
function GC(e, t) {
	let n = [];
	if (e) {
		for (let r = 0; r < t; r += 1) n[r] = {
			affectedInstances: e.affectedInstances,
			isEvent: e.isEvent,
			segs: []
		};
		for (let t of e.segs) n[t.col].segs.push(t);
	} else for (let e = 0; e < t; e += 1) n[e] = null;
	return n;
}
var KC = [
	{ hours: 1 },
	{ minutes: 30 },
	{ minutes: 15 },
	{ seconds: 30 },
	{ seconds: 15 }
];
function qC(e, t, n, r, i) {
	let a = /* @__PURE__ */ new Date(0), o = e, s = z(0), c = n || JC(r), l = [], u = 0;
	for (; ip(o) < ip(t);) {
		let e = i.add(a, o), t = ap(s, c) !== null;
		l.push({
			date: e,
			time: o,
			key: e.toISOString(),
			isoTimeStr: up(e),
			isLabeled: t,
			isFirst: u === 0
		}), o = Qf(o, r), s = Qf(s, r), u += 1;
	}
	return l;
}
function JC(e) {
	let t, n, r;
	for (t = KC.length - 1; t >= 0; --t) if (n = z(KC[t]), r = ap(n, e), r !== null && r > 1) return n;
	return e;
}
var YC = class extends J {
	constructor() {
		super(...arguments), this.innerElRef = C();
	}
	render() {
		let { props: e } = this, { options: t, viewApi: n } = this.context, r = {
			text: t.allDayText,
			view: n,
			isNarrow: e.isNarrow
		};
		return D(X, {
			tag: "div",
			attrs: { role: "rowheader" },
			className: V(B.flexRow, B.noMargin, B.noPadding, B.contentBox),
			style: { width: e.width },
			renderProps: r,
			generatorName: "allDayHeaderContent",
			customGenerator: t.allDayHeaderContent,
			defaultGenerator: XC,
			classNameGenerator: t.allDayHeaderClass,
			didMount: t.allDayHeaderDidMount,
			willUnmount: t.allDayHeaderWillUnmount,
			children: (e) => D("div", {
				className: V(B.flexRow, B.noShrink, B.whiteSpacePre),
				ref: this.innerElRef,
				children: D(e, {
					tag: "div",
					className: Z(t.allDayHeaderInnerClass, r)
				})
			})
		});
	}
	componentDidMount() {
		this._isUnmounting = !1;
		let { props: e } = this, t = this.innerElRef.current;
		this.disconnectInnerWidth = nv(t, (t) => {
			this._isUnmounting || Y(e.innerWidthRef, t);
		});
	}
	componentWillUnmount() {
		this._isUnmounting = !0, this.disconnectInnerWidth(), Y(this.props.innerWidthRef, null);
	}
};
function XC(e) {
	return e.text;
}
var ZC = class extends gv {
	constructor() {
		super(...arguments), this.state = {}, this.heightRef = C(), this.handleMoreLinkEl = (e) => {
			this.disconnectMoreLinkHeight?.(), this.disconnectMoreLinkHeight = void 0, e && (this.disconnectMoreLinkHeight = rv(e, (e) => {
				this._isUnmounting || this.setState({ moreLinkHeight: e });
			}));
		}, this.handleRootEl = (e) => {
			this.rootEl = e, e ? this.context.registerInteractiveComponent(this, { el: e }) : this.context.unregisterInteractiveComponent(this);
		};
	}
	render() {
		let { props: e, state: t } = this, n = !e.forPrint && qS(e.dayMaxEvents, e.dayMaxEventRows) === "auto";
		return D(w, { children: [D(oC, {
			...e,
			moreLinkHeight: t.moreLinkHeight,
			rootElRef: this.handleRootEl,
			heightRef: this.heightRef
		}), n && D(Mx, {
			num: 1,
			display: "row",
			isNarrow: e.cellIsNarrow,
			isMicro: e.cellIsMicro,
			elRef: this.handleMoreLinkEl,
			className: B.offscreen,
			attrs: { "aria-hidden": !0 }
		})] });
	}
	componentDidMount() {
		this._isUnmounting = !1;
	}
	componentWillUnmount() {
		this._isUnmounting = !0;
	}
	queryHit(e, t, n, r) {
		let { props: i, heightRef: a } = this, o = i.cells.length, { col: s, left: c, right: l } = mC(t, r, i.colWidth, o, e), u = i.cells[s];
		if (u.isInert) return null;
		let d = u.date, f = L(d, 1);
		return {
			dateProfile: i.dateProfile,
			dateSpan: {
				range: {
					start: d,
					end: f
				},
				allDay: !0,
				...u.dateSpanProps
			},
			getDayEl: () => _C(this.rootEl, s),
			rect: {
				left: c,
				right: l,
				top: 0,
				bottom: a.current
			},
			layer: 0
		};
	}
};
function QC(e, t, n = 0, r, i) {
	if (!r || !i) return [void 0, !1];
	let a = Math.max(r + 1, n), o = i / t, s, c;
	return e && o >= a ? (s = !0, c = o) : (s = !1, c = a), [c, s];
}
function $C(e, t, n) {
	let r = R(e).valueOf(), i;
	for (let e = 0; e < n.length; e++) if (t[e]?.length) {
		let { date: t } = n[e];
		if (t.valueOf() === r) return t;
		i ||= t;
	}
	return i;
}
function ew(e, t, n) {
	return n ||= R(e), tw(z(e.valueOf() - n.valueOf()), t);
}
function tw(e, t) {
	let n = ip(t.slotMinTime), r = ip(t.slotMaxTime), i = (e.milliseconds - n) / (r - n);
	return i = Math.max(0, i), i = Math.min(1, i), i;
}
function nw(e, t, n, r, i, a, o) {
	let s = [];
	if (i != null) {
		let c = i * r;
		for (let r of e) {
			let e = ew(r.startDate, t, n), i = ew(r.endDate, t, n), l = e * c, u = i * c, d = u - l;
			a != null && d < a && (d = a, u = l + d), s.push({
				start: l,
				end: u,
				size: d,
				isShort: d <= o
			});
		}
	}
	return s;
}
function rw(e, t, n, r) {
	let i = [], a = /* @__PURE__ */ new Map();
	for (let n = 0; n < e.length; n += 1) {
		let r = e[n], o = t[n];
		if (o) {
			let e = {
				...r,
				key: r.eventRange.instance.instanceId,
				start: o.start,
				end: o.end,
				orderIndex: n
			};
			i.push(e), a.set(e, o);
		}
	}
	let o = iw(i, r ?? Infinity, { orderStrict: n ?? !1 });
	return {
		placements: o.domOrderedPlacements.map((e) => {
			let t = e.sourceSeg;
			return {
				seg: t,
				segVertical: a.get(t),
				levelCoord: e.levelCoord,
				thickness: e.thickness,
				stackDepth: e.backwardDepth,
				stackForward: e.forwardDepth
			};
		}),
		hiddenGroups: o.moreLinkGroups.map((e) => {
			let t = e.hiddenSlices.map((e) => e.sourceSeg);
			return {
				key: e.key,
				start: e.start,
				end: e.end,
				segs: t
			};
		})
	};
}
function iw(e, t, n) {
	let { segLevels: r, excludedSegs: i } = lS(e, n.orderStrict, t), a = aw(r), o = DS(fS(i));
	return {
		domOrderedPlacements: FS(a),
		moreLinkGroups: o
	};
}
function aw(e) {
	let t = e.map((e, t) => e.map((e) => ({
		sourceSeg: e,
		start: e.start,
		end: e.end,
		isStart: e.isStart,
		isEnd: e.isEnd,
		levelIndex: t
	}))), n = im(t), r = /* @__PURE__ */ new Map(), i = new Map(n.map((e) => [e.sourceSeg.key, e.sourceSeg.key]));
	for (let a of n) {
		let n = [];
		for (let r = a.levelIndex + 1; r < e.length; r += 1) n.push(...tS(t[r], a));
		r.set(a.sourceSeg.key, n);
		for (let e of n) sw(i, a.sourceSeg.key, e.sourceSeg.key);
	}
	let a = /* @__PURE__ */ new Map();
	for (let e of n) {
		let t = ow(i, e.sourceSeg.key);
		a.set(t, Math.max(a.get(t) ?? 0, e.levelIndex));
	}
	let o = new Map(n.map((e) => [e.sourceSeg.key, 0])), s = new Map(n.map((e) => [e.sourceSeg.key, 0]));
	for (let e of n) {
		let t = o.get(e.sourceSeg.key) + 1;
		for (let n of r.get(e.sourceSeg.key)) o.set(n.sourceSeg.key, Math.max(o.get(n.sourceSeg.key), t));
	}
	for (let e = n.length - 1; e >= 0; --e) {
		let t = n[e], i = 0;
		for (let e of r.get(t.sourceSeg.key)) i = Math.max(i, s.get(e.sourceSeg.key) + 1);
		s.set(t.sourceSeg.key, i);
	}
	return n.map((e) => {
		let t = e.sourceSeg.key, n = a.get(ow(i, t)) + 1, c = n;
		for (let e of r.get(t)) c = Math.min(c, e.levelIndex);
		let l = e.levelIndex / n, u = (c - e.levelIndex) / n;
		return {
			...e,
			levelCoord: l,
			thickness: u,
			levelEndCoord: l + u,
			backwardDepth: o.get(t),
			forwardDepth: s.get(t)
		};
	});
}
function ow(e, t) {
	let n = e.get(t);
	if (n === t) return t;
	let r = ow(e, n);
	return e.set(t, r), r;
}
function sw(e, t, n) {
	let r = ow(e, t), i = ow(e, n);
	r !== i && e.set(i, r);
}
var cw = 50, lw = typeof navigator < "u" && navigator.userAgent.toLowerCase().includes("firefox");
function uw(e, t) {
	return e && (t === "stack" || t !== "grid" && lw) ? "stack" : "positioned";
}
var dw = H({
	hour: "numeric",
	minute: "2-digit",
	meridiem: !1
}), fw = class extends J {
	render() {
		let { props: e } = this;
		return D(Dv, {
			...e,
			display: "column",
			level: e.level,
			isNarrow: e.isNarrow,
			isShort: e.isShort,
			className: e.isLiquid ? B.liquid : "",
			disableLiquid: !e.isLiquid,
			defaultTimeFormat: dw
		});
	}
}, pw = class extends J {
	render() {
		let { props: e } = this;
		return D("div", {
			role: "listitem",
			className: V(B.abs, B.flexCol, B.end0, B.z9999),
			style: {
				top: e.top,
				height: e.height
			},
			children: D(Nx, {
				className: B.liquid,
				display: "column",
				allDayDate: null,
				segs: e.hiddenSegs,
				hiddenSegs: e.hiddenSegs,
				dateSpanProps: e.dateSpanProps,
				renderProps: e.renderProps,
				dateProfile: e.dateProfile,
				todayRange: e.todayRange,
				popoverContent: () => vw(e.hiddenSegs, e, !1),
				forceTimed: !0,
				isNarrow: e.isNarrow,
				isMicro: e.isMicro
			})
		});
	}
}, mw = (e) => D(w_.Consumer, { children: (t) => {
	let { options: n } = t;
	return D("div", {
		className: V(e.className, n.nowIndicatorDotClass),
		style: e.style
	});
} }), hw = (e) => D(w_.Consumer, { children: (t) => {
	let { options: n } = t, r = {
		date: t.dateEnv.toDate(e.date),
		view: t.viewApi
	};
	return D(X, {
		elRef: e.elRef,
		tag: e.tag || "div",
		attrs: e.attrs,
		className: e.className,
		style: e.style,
		renderProps: r,
		generatorName: "nowIndicatorLineContent",
		customGenerator: n.nowIndicatorLineContent,
		classNameGenerator: n.nowIndicatorLineClass,
		didMount: n.nowIndicatorLineDidMount,
		willUnmount: n.nowIndicatorLineWillUnmount,
		children: e.children
	});
} });
function gw(e) {
	let t = e.totalHeight == null ? void 0 : e.totalHeight * ew(e.nowDate, e.dateProfile, e.dayDate);
	return D("div", {
		className: V(B.fill, B.pointerEventsNone, B.z2),
		children: [D(hw, {
			className: V(B.fillX, B.noMarginX, B.borderlessX),
			style: { top: t },
			date: e.nowDate
		}), (e.showDot ?? !0) && D(mw, {
			className: V(B.abs, B.start0),
			style: { top: t }
		})]
	});
}
var _w = class extends J {
	constructor() {
		super(...arguments), this.sortEventSegs = q(Qh), this.getDateMeta = q(pv);
	}
	render() {
		let { props: e, context: t } = this, { options: n, dateEnv: r } = t, i = n.selectMirror, a = e.eventDrag && e.eventDrag.segs || e.eventResize && e.eventResize.segs || i && e.dateSelectionSegs || [], o = this.getDateMeta(e.date, r, e.dateProfile, e.todayRange), s = V(B.borderlessY, B.borderlessEnd, !e.borderStart && B.borderlessStart, e.width == null && B.liquid, B.rel, B.z1), c = { width: e.width }, l = this.getIsStack(), u = {
			...o,
			...e.renderProps,
			isStack: l,
			isNarrow: e.isNarrow,
			isMajor: e.isMajor,
			view: t.viewApi
		};
		if (o.isDisabled) return D("div", {
			role: "cell",
			"aria-disabled": !0,
			className: V(Z(n.dayLaneClass, u), s),
			style: c
		});
		let d = V(Z(n.dayLaneInnerClass, u), !l && B.fill, B.z1), f = this.sortEventSegs(e.fgEventSegs, n.eventOrder);
		return D(X, {
			tag: "div",
			attrs: {
				...e.attrs,
				role: "cell",
				...o.isToday ? { "aria-current": "date" } : {},
				"data-date": cp(e.date)
			},
			className: s,
			style: c,
			renderProps: u,
			generatorName: void 0,
			classNameGenerator: n.dayLaneClass,
			didMount: n.dayLaneDidMount,
			willUnmount: n.dayLaneWillUnmount,
			children: () => D(w, { children: [
				this.renderFillSegs(e.businessHourSegs, "non-business"),
				this.renderFillSegs(e.bgEventSegs, "bg-event"),
				this.renderFillSegs(e.dateSelectionSegs, "highlight"),
				D("div", {
					role: f.length ? "list" : void 0,
					className: d,
					children: this.renderFgSegs(f, !1)
				}),
				!!a.length && D("div", {
					className: d,
					children: this.renderFgSegs(a, !0)
				}),
				this.renderNowIndicator(e.nowIndicatorSegs)
			] })
		});
	}
	renderFgSegs(e, t) {
		let { props: n } = this;
		return this.getIsStack() ? vw(e, n, t) : t ? this.renderPositionedMirrorSegs(e) : this.renderPositionedFgSegs(e);
	}
	renderPositionedFgSegs(e) {
		let { eventMaxStack: t, eventOrderStrict: n } = this.context.options, { placements: r, hiddenGroups: i } = rw(e, this.computeSegVerticals(e), n, t);
		return D(w, { children: [r.map((e) => this.renderPositionedSeg(e.seg, e.segVertical, this.computeSegHStyle(e), e.stackDepth, !1)), this.renderHiddenGroups(i)] });
	}
	renderPositionedMirrorSegs(e) {
		let t = this.computeSegVerticals(e);
		return e.map((e, n) => this.renderPositionedSeg(e, t[n] || {}, {
			left: 0,
			right: 0,
			zIndex: 0
		}, 0, !0));
	}
	renderPositionedSeg(e, t, n, r, i) {
		let { props: a } = this, { eventRange: o } = e, { instanceId: s } = o.instance, c = s === a.eventSelection;
		c && (n.zIndex += 1e3);
		let l = !!(a.eventDrag && a.eventDrag.affectedInstances[s]), u = !!(a.eventResize && a.eventResize.affectedInstances[s]), d = !i && (l || u);
		return D("div", {
			role: i ? void 0 : "listitem",
			className: V(B.abs, B.flexCol),
			style: {
				visibility: d ? "hidden" : void 0,
				top: t.start,
				height: t.size,
				...n
			},
			children: D(fw, {
				eventRange: o,
				slicedStart: e.startDate,
				slicedEnd: e.endDate,
				isStart: e.isStart,
				isEnd: e.isEnd,
				isDragging: l,
				isResizing: u,
				isMirror: i,
				isSelected: c,
				level: r,
				isNarrow: a.isNarrow,
				isShort: t.isShort || !1,
				isLiquid: !0,
				renderProps: a.renderProps,
				...ig(o, a.todayRange, a.nowDate, a.nowMs)
			})
		}, s);
	}
	computeSegVerticals(e) {
		let { props: t, context: n } = this, r = t.slatHeight != null;
		return nw(e, t.dateProfile, t.date, t.slatCnt, t.slatHeight ?? cw, r ? n.options.eventMinHeight : void 0, n.options.eventShortHeight);
	}
	renderHiddenGroups(e) {
		let { dateSpanProps: t, renderProps: n, dateProfile: r, todayRange: i, nowDate: a, nowMs: o, eventSelection: s, eventDrag: c, eventResize: l, isNarrow: u, isMicro: d } = this.props;
		return D(w, { children: e.map((e) => D(pw, {
			hiddenSegs: e.segs,
			top: e.start,
			height: e.end - e.start,
			isNarrow: u,
			isMicro: d,
			dateSpanProps: t,
			renderProps: n,
			dateProfile: r,
			todayRange: i,
			nowDate: a,
			nowMs: o,
			eventSelection: s,
			eventDrag: c,
			eventResize: l
		}, e.key)) });
	}
	renderFillSegs(e, t) {
		let { props: n, context: r } = this, i = this.computeSegVerticals(e);
		return D(w, { children: e.map((e, a) => {
			let { eventRange: o } = e, s = i[a] || {};
			return D("div", {
				"aria-hidden": !0,
				className: B.fillX,
				style: {
					top: s.start,
					height: s.size,
					marginInlineStart: -1
				},
				children: t === "bg-event" ? D(bx, {
					eventRange: o,
					isStart: e.isStart,
					isEnd: e.isEnd,
					isNarrow: n.isNarrow,
					isShort: s.isShort || !1,
					isVertical: !0,
					renderProps: n.renderProps,
					...ig(o, n.todayRange, n.nowDate, n.nowMs)
				}) : Sx(t, r.options)
			}, ag(o));
		}) });
	}
	renderNowIndicator(e) {
		let { props: t } = this;
		if (!(t.forPrint || this.getIsStack())) return e.map((e, n) => D(gw, {
			nowDate: e.startDate,
			dayDate: t.date,
			dateProfile: t.dateProfile,
			totalHeight: t.slatHeight == null ? void 0 : t.slatHeight * t.slatCnt,
			showDot: e.showDot ?? !0
		}, n));
	}
	computeSegHStyle(e) {
		let { options: t } = this.context, n = t.slotEventOverlap, r = e.levelCoord, i = e.levelCoord + e.thickness;
		n && (i = Math.min(1, r + (i - r) * 2));
		let a = {
			zIndex: e.stackDepth + 1,
			insetInlineStart: Ap(r),
			insetInlineEnd: Ap(1 - i),
			marginInlineEnd: void 0
		};
		return n && e.stackForward && (a.marginInlineEnd = 20), a;
	}
	getIsStack() {
		let { eventPrintLayout: e } = this.context.options;
		return uw(this.props.forPrint, e) === "stack";
	}
};
function vw(e, { todayRange: t, nowDate: n, nowMs: r, eventSelection: i, eventDrag: a, eventResize: o, renderProps: s }, c) {
	return D(w, { children: e.map((e) => {
		let { eventRange: l } = e, { instanceId: u } = l.instance, d = !!(a && a.affectedInstances[u]), f = !!(o && o.affectedInstances[u]), p = d || f;
		return D("div", {
			role: c ? void 0 : "listitem",
			className: B.breakInsideAvoid,
			style: { visibility: p ? "hidden" : void 0 },
			children: D(fw, {
				eventRange: l,
				slicedStart: e.startDate,
				slicedEnd: e.endDate,
				isStart: e.isStart,
				isEnd: e.isEnd,
				isDragging: d,
				isResizing: f,
				isMirror: c,
				isSelected: u === i,
				level: 0,
				isShort: !1,
				isNarrow: !1,
				disableResizing: !0,
				renderProps: s,
				...ig(l, t, n, r)
			})
		}, u);
	}) });
}
var yw = class extends gv {
	constructor() {
		super(...arguments), this.processSlotOptions = q(bw), this.handleRootEl = (e) => {
			this.rootEl = e, e ? this.context.registerInteractiveComponent(this, {
				el: e,
				isHitComboAllowed: this.props.isHitComboAllowed
			}) : this.context.unregisterInteractiveComponent(this);
		};
	}
	render() {
		let { props: e } = this;
		return D("div", {
			role: e.role,
			className: V(e.className, B.flexRow),
			ref: this.handleRootEl,
			children: e.cells.map((t, n) => D(_w, {
				dateProfile: e.dateProfile,
				nowDate: e.nowDate,
				nowMs: e.nowMs,
				todayRange: e.todayRange,
				date: t.date,
				isMajor: t.isMajor,
				slatCnt: e.slatCnt,
				renderProps: t.renderProps,
				attrs: t.attrs,
				dateSpanProps: t.dateSpanProps,
				forPrint: e.forPrint,
				borderStart: !!n,
				isNarrow: e.cellIsNarrow,
				isMicro: e.cellIsMicro,
				fgEventSegs: e.fgEventSegsByCol[n],
				bgEventSegs: e.bgEventSegsByCol[n],
				businessHourSegs: e.businessHourSegsByCol[n],
				nowIndicatorSegs: e.nowIndicatorSegsByCol[n],
				dateSelectionSegs: e.dateSelectionSegsByCol[n],
				eventDrag: e.eventDragByCol[n],
				eventResize: e.eventResizeByCol[n],
				eventSelection: e.eventSelection,
				width: e.colWidth,
				slatHeight: e.slatHeight
			}, t.key))
		});
	}
	queryHit(e, t, n, r) {
		let { dateProfile: i, cells: a, colWidth: o, slatHeight: s } = this.props, { dateEnv: c, options: l } = this.context, { snapDuration: u, snapsPerSlot: d } = this.processSlotOptions(l.slotDuration, l.snapDuration), f = a.length, { col: p, left: m, right: h } = mC(t, r, o, f, e), g = a[p];
		if (g.isInert) return null;
		let _ = Math.floor(n / s), v = _ * s, y = (n - v) / s, b = Math.floor(y * d), x = _ * d + b, S = Qf(i.slotMinTime, ep(u, x)), C = c.add(g.date, S);
		return {
			dateProfile: i,
			dateSpan: {
				range: {
					start: C,
					end: c.add(C, u)
				},
				allDay: !1,
				...g.dateSpanProps
			},
			getDayEl: () => _C(this.rootEl, p),
			rect: {
				left: m,
				right: h,
				top: v,
				bottom: v + s
			},
			layer: 0
		};
	}
};
yw.addPropsEquality({ style: gm });
function bw(e, t) {
	let n = t || e, r = ap(e, n);
	return r === null && (n = e, r = 1), {
		snapDuration: n,
		snapsPerSlot: r
	};
}
var xw = (e) => D(w_.Consumer, { children: (t) => {
	let { options: n } = t, r = {
		date: t.dateEnv.toDate(e.date),
		view: t.viewApi
	};
	return D(X, {
		elRef: e.elRef,
		tag: e.tag || "div",
		attrs: e.attrs,
		className: e.className,
		style: e.style,
		renderProps: r,
		generatorName: "nowIndicatorHeaderContent",
		customGenerator: n.nowIndicatorHeaderContent,
		classNameGenerator: n.nowIndicatorHeaderClass,
		didMount: n.nowIndicatorHeaderDidMount,
		willUnmount: n.nowIndicatorHeaderWillUnmount,
		children: e.children
	});
} });
function Sw(e) {
	return D("div", {
		className: V(B.fill, B.crop, B.pointerEventsNone, B.z2),
		children: D(xw, {
			className: B.abs,
			style: { top: e.totalHeight == null ? void 0 : e.totalHeight * ew(e.nowDate, e.dateProfile, e.dayDate) },
			date: e.nowDate
		})
	});
}
var Cw = H({
	hour: "numeric",
	minute: "2-digit",
	omitZeroMinute: !0,
	meridiem: "short"
}), ww = class extends J {
	constructor() {
		super(...arguments), this.createRenderProps = q(Tw), this.innerElRef = C();
	}
	render() {
		let { props: e, context: t } = this, { options: n } = t, r = n.slotHeaderFormat == null ? Cw : Array.isArray(n.slotHeaderFormat) ? H(n.slotHeaderFormat[0]) : H(n.slotHeaderFormat), i = this.createRenderProps(e.date, e.time, !e.isLabeled, e.isNarrow, e.isFirst, r, t), a = V(e.liquidHeight && B.liquid, B.flexRow, B.alignStart, B.noMargin, B.noPadding, B.borderlessX, B.borderlessBottom, !e.borderTop && B.borderlessTop);
		return e.isLabeled ? D(X, {
			tag: "div",
			attrs: { "data-time": e.isoTimeStr },
			style: { height: e.height },
			className: a,
			renderProps: i,
			generatorName: "slotHeaderContent",
			customGenerator: n.slotHeaderContent,
			defaultGenerator: Ew,
			classNameGenerator: n.slotHeaderClass,
			didMount: n.slotHeaderDidMount,
			willUnmount: n.slotHeaderWillUnmount,
			children: (e) => D("div", {
				ref: this.innerElRef,
				className: V(B.noShrink, B.whiteSpaceNoWrap, B.flexRow),
				children: D(e, {
					tag: "div",
					className: Z(n.slotHeaderInnerClass, i)
				})
			})
		}) : D("div", {
			className: V(Z(n.slotHeaderClass, i), a),
			"data-time": e.isoTimeStr,
			style: { height: e.height }
		});
	}
	componentDidMount() {
		this._isUnmounting = !1;
		let { props: e } = this, t = this.innerElRef.current;
		t && (this.disconnectInnerSize = tv(t, (t, n) => {
			this._isUnmounting || (Y(e.innerWidthRef, t), Y(e.innerHeightRef, n));
		}));
	}
	componentWillUnmount() {
		let { props: e } = this;
		this._isUnmounting = !0, this.disconnectInnerSize && (this.disconnectInnerSize(), Y(e.innerWidthRef, null), Y(e.innerHeightRef, null));
	}
};
function Tw(e, t, n, r, i, a, o) {
	let s = o.dateEnv.formatToParts(e, a);
	return {
		...pv(e, o.dateEnv),
		level: 0,
		text: fp(s),
		textParts: s,
		weekdayText: "",
		dayNumberText: "",
		monthText: "",
		time: t,
		isMajor: !1,
		isMinor: n,
		isTime: !0,
		isNarrow: r,
		hasNavLink: !1,
		isFirst: i,
		view: o.viewApi
	};
}
function Ew(e) {
	return e.text;
}
var Dw = class extends J {
	constructor() {
		super(...arguments), this.getDateMeta = q(pv);
	}
	render() {
		let { props: e, context: t } = this, { options: n } = t, r = {
			...this.getDateMeta(e.date, t.dateEnv),
			time: e.time,
			isMajor: !1,
			isMinor: !e.isLabeled,
			view: t.viewApi
		};
		return D(X, {
			tag: "div",
			attrs: { "data-time": e.isoTimeStr },
			className: V(B.noMargin, B.noPadding, B.liquid, B.borderlessX, B.borderlessBottom, !e.borderTop && B.borderlessTop),
			renderProps: r,
			generatorName: void 0,
			classNameGenerator: n.slotLaneClass,
			didMount: n.slotLaneDidMount,
			willUnmount: n.slotLaneWillUnmount
		});
	}
}, Ow = H({ week: "short" }), kw = class extends J {
	constructor() {
		super(...arguments), this.innerElRef = C();
	}
	render() {
		let { props: e, context: t } = this, { options: n, dateEnv: r } = t, i = e.dateProfile.renderRange, a = ff(i.start, i.end) === 1 && n.navLinks, o = i.start, s = dv(t, o, "week"), c = r.computeWeekNumber(o), l = r.formatToParts(o, n.weekNumberFormat || Ow), u = {
			num: c,
			text: fp(l),
			textParts: l,
			date: r.toDate(o),
			isNarrow: e.isNarrow,
			hasNavLink: a,
			options: { dayMinWidth: n.dayMinWidth }
		};
		return D(X, {
			tag: "div",
			attrs: { role: "columnheader" },
			className: V(B.flexRow, B.noMargin, B.noPadding, e.isLiquid ? B.liquid : B.contentBox),
			style: { width: e.width },
			renderProps: u,
			generatorName: "weekNumberHeaderContent",
			customGenerator: n.weekNumberHeaderContent,
			defaultGenerator: P_,
			classNameGenerator: n.weekNumberHeaderClass,
			didMount: n.weekNumberHeaderDidMount,
			willUnmount: n.weekNumberHeaderWillUnmount,
			children: (e) => D(w, { children: [!a && D("div", {
				className: B.srOnly,
				children: s
			}), D("div", {
				"aria-hidden": !a || void 0,
				ref: this.innerElRef,
				className: V(B.flexRow, B.noShrink, B.whiteSpaceNoWrap),
				children: D(e, {
					tag: "div",
					attrs: a ? fv(t, i.start, "week", s, !0, !0) : void 0,
					className: Z(n.weekNumberHeaderInnerClass, u)
				})
			})] })
		});
	}
	componentDidMount() {
		this._isUnmounting = !1;
		let { props: e } = this, t = this.innerElRef.current;
		this.disconnectInnerSize = tv(t, (t, n) => {
			this._isUnmounting || (Y(e.innerWidthRef, t), Y(e.innerHeightRef, n));
		});
	}
	componentWillUnmount() {
		let { props: e } = this;
		this._isUnmounting = !0, this.disconnectInnerSize(), Y(e.innerWidthRef, null), Y(e.innerHeightRef, null);
	}
};
function Aw(e) {
	return D("div", {
		role: "cell",
		className: e.isLiquid ? B.liquid : B.contentBox,
		style: { width: e.width }
	});
}
var jw = class extends J {
	constructor() {
		super(...arguments), this.state = { headerTierHeights: [] }, this.headerLabelInnerWidthRefMap = new hx(() => {
			Q(this.handleAxisWidths);
		}), this.headerLabelInnerHeightRefMap = new hx(() => {
			Q(this.handleHeaderHeights);
		}), this.headerMainInnerHeightRefMap = new hx(() => {
			Q(this.handleHeaderHeights);
		}), this.handleAllDayLabelInnerWidth = (e) => {
			this.allDayLabelInnerWidth = e, Q(this.handleAxisWidths);
		}, this.slatLabelInnerWidthRefMap = new hx(() => {
			Q(this.handleAxisWidths);
		}), this.slatLabelInnerHeightRefMap = new hx(() => {
			Q(this.handleSlatInnerHeights);
		}), this.headerScrollerRef = C(), this.allDayScrollerRef = C(), this.mainScrollerRef = C(), this.footScrollerRef = C(), this.axisScrollerRef = C(), this.handleTotalWidth = (e) => {
			this._isUnmounting || this.setState({ totalWidth: e });
		}, this.handleBodyHeight = (e) => {
			this._isUnmounting || this.setState({ bodyHeight: e });
		}, this.handleClientWidth = (e) => {
			this._isUnmounting || this.setState({ clientWidth: e });
		}, this.handleClientHeight = (e) => {
			this._isUnmounting || this.setState({ clientHeight: e });
		}, this.handleStickyBottomScrollbarWidth = (e) => {
			this._isUnmounting || this.setState({ sticykBottomScrollbarWidth: e });
		}, this.handleHeaderHeights = () => {
			if (this._isUnmounting) return;
			let e = this.headerLabelInnerHeightRefMap.current, t = this.headerMainInnerHeightRefMap.current, n = [];
			for (let [r, i] of t.entries()) n[r] = Math.max(e.get(r) || 0, i);
			this.setState({ headerTierHeights: n });
		}, this.handleSlatInnerHeights = () => {
			if (this._isUnmounting) return;
			let e = this.slatLabelInnerHeightRefMap.current, t = 0;
			for (let n of e.values()) t = Math.max(t, n);
			this.state.slatInnerHeight !== t && this.setState({ slatInnerHeight: t });
		}, this.handleAxisWidths = () => {
			if (this._isUnmounting) return;
			let e = this.headerLabelInnerWidthRefMap.current, t = this.slatLabelInnerWidthRefMap.current, n = this.allDayLabelInnerWidth || 0;
			for (let t of e.values()) n = Math.max(n, t);
			for (let e of t.values()) n = Math.max(n, e);
			this.state.axisWidth !== n && this.setState({ axisWidth: n });
		};
	}
	render() {
		let { props: e, state: t, context: n, headerLabelInnerWidthRefMap: r, headerLabelInnerHeightRefMap: i, headerMainInnerHeightRefMap: a, slatLabelInnerWidthRefMap: o, slatLabelInnerHeightRefMap: s } = this, { nowDate: c, headerTiers: l, forPrint: u } = e, d = !u && n.options.nowIndicator ? $C(c, e.nowIndicatorSegsByCol, e.cells) : void 0, { axisWidth: f, totalWidth: p, clientWidth: m, clientHeight: h, bodyHeight: g, sticykBottomScrollbarWidth: _ } = t, { options: v } = n, { borderlessX: y, borderlessTop: b, borderlessBottom: S } = rm(v), C = p != null && m != null && f != null ? p - m - (f + 1) : void 0, T = !u && !F_(v), ee = !u && I_(v), te = !u && L_(v), E = uw(u, v.eventPrintLayout) === "stack", ne = u && !E, re = u && E, ie = e.cells.length, [ae, oe] = fC(ie, e.dayMinWidth, m), se = oe ?? (m == null ? void 0 : m / ie), ce = se != null && se <= 60, le = ce || se != null && se <= v.dayNarrowWidth, ue = e.slatMetas.length, [de, fe] = QC(T && v.expandRows, ue, v.slotMinHeight, t.slatInnerHeight, h);
		this.slatHeight = de;
		let pe = (de || 0) * ue, me = ne ? pe : void 0, he = T && !v.expandRows && h != null && h > pe, ge = v.dayHeaders ? l.length + 1 : 1, _e = te ? _ : g != null && h != null ? g - h : void 0;
		return D(w, { children: [
			v.dayHeaders && D("div", {
				className: V(Z(v.tableHeaderClass, {
					isSticky: ee,
					borderlessX: y,
					borderlessTop: b,
					borderlessBottom: S,
					multiMonthColumns: 0
				}), B.flexCol, ee && B.tableHeaderSticky, B.z1),
				children: [D("div", {
					className: B.flexRow,
					children: [
						D("div", {
							role: "rowgroup",
							className: B.contentBox,
							style: { width: f },
							children: l.map((n, a) => D("div", {
								role: "row",
								"aria-rowindex": a + 1,
								className: V(v.dayHeaderRowClass, B.flexRow, B.contentBox, B.borderlessX, B.borderlessTop, a === e.headerTiers.length - 1 && B.borderlessBottom),
								style: { height: t.headerTierHeights[a] },
								children: v.weekNumbers && n.isDateRow ? D(kw, {
									dateProfile: e.dateProfile,
									innerWidthRef: r.createRef(a),
									innerHeightRef: i.createRef(a),
									width: void 0,
									isLiquid: !0,
									isNarrow: le
								}) : D(Aw, {
									width: void 0,
									isLiquid: !0
								})
							}, a))
						}),
						D("div", { className: Z(v.slotHeaderDividerClass, {
							inTableHeader: !0,
							options: { dayMinWidth: v.dayMinWidth }
						}) }),
						D(yv, {
							horizontal: !0,
							hideScrollbars: !0,
							className: V(B.flexRow, B.liquid),
							ref: this.headerScrollerRef,
							children: [D("div", {
								role: "rowgroup",
								className: ae == null ? B.liquid : "",
								style: { width: ae },
								children: e.headerTiers.map((n, r) => x(DC, {
									...n,
									key: r,
									role: "row",
									rowIndex: r,
									borderBottom: r < e.headerTiers.length - 1,
									height: t.headerTierHeights[r],
									colWidth: oe,
									viewportWidth: m,
									innerHeightRef: a.createRef(r),
									cellIsNarrow: le,
									cellIsMicro: ce,
									rowLevel: e.headerTiers.length - r - 1
								}))
							}), !!C && D("div", {
								className: V(Z(v.fillerClass, { inTableHeader: !0 }), B.borderlessY, B.borderlessEnd),
								style: { minWidth: C }
							})]
						})
					]
				}), D("div", { className: Z(v.dayHeaderDividerClass, {
					isSticky: ee,
					multiMonthColumns: 0,
					options: { allDaySlot: !!v.allDaySlot }
				}) })]
			}),
			D("div", {
				role: "rowgroup",
				className: V(Z(v.tableBodyClass, {
					borderlessX: y,
					borderlessTop: b,
					borderlessBottom: S,
					multiMonthColumns: 0
				}), B.flexCol, T && B.liquid, B.isolate, B.z0),
				children: [v.allDaySlot && D(w, { children: [D("div", {
					role: "row",
					"aria-rowindex": ge,
					className: V(B.flexRow, B.z1),
					children: [
						D(YC, {
							width: f,
							innerWidthRef: this.handleAllDayLabelInnerWidth,
							isNarrow: le
						}),
						D("div", { className: Z(v.slotHeaderDividerClass, {
							inTableHeader: !1,
							options: { dayMinWidth: v.dayMinWidth }
						}) }),
						D(yv, {
							horizontal: !0,
							hideScrollbars: !0,
							className: V(B.flexRow, B.liquidX),
							ref: this.allDayScrollerRef,
							children: [D("div", {
								className: B.flexRow,
								style: { width: ae },
								children: D(ZC, {
									dateProfile: e.dateProfile,
									todayRange: e.todayRange,
									cells: e.cells,
									showDayNumbers: !1,
									forPrint: u,
									isHitComboAllowed: e.isHitComboAllowed,
									className: V(B.borderless, B.liquidX),
									cellIsNarrow: le,
									cellIsMicro: ce,
									fgEventSegs: e.fgEventSegs,
									bgEventSegs: e.bgEventSegs,
									businessHourSegs: e.businessHourSegs,
									dateSelectionSegs: e.dateSelectionSegs,
									eventSelection: e.eventSelection,
									eventDrag: e.eventDrag,
									eventResize: e.eventResize,
									dayMaxEvents: e.dayMaxEvents,
									dayMaxEventRows: e.dayMaxEventRows,
									colWidth: oe
								})
							}), !!C && D("div", {
								className: V(Z(v.fillerClass, { inTableHeader: !1 }), B.borderlessY, B.borderlessEnd),
								style: { minWidth: C }
							})]
						})
					]
				}), D("div", { className: V(v.allDayDividerClass, B.z2) })] }), D("div", {
					role: "row",
					"aria-rowindex": ge + +!!v.allDaySlot,
					className: V(B.flexRow, B.rel, T && B.liquid, B.z0),
					children: [
						D(yv, {
							vertical: T,
							hideScrollbars: !0,
							className: V(B.flexCol, B.contentBox),
							style: { width: f },
							ref: this.axisScrollerRef,
							clientHeightRef: this.handleBodyHeight,
							children: !re && D(w, { children: D("div", {
								role: "rowheader",
								className: V(B.flexCol, B.grow, B.rel),
								style: { height: me },
								children: [
									D("div", {
										className: B.srOnly,
										children: v.timedText
									}),
									D("div", {
										"aria-hidden": !0,
										className: V(B.flexCol, T && v.expandRows && B.grow, ne && B.fillX),
										children: e.slatMetas.map((e, t) => x(ww, {
											...e,
											key: e.key,
											innerWidthRef: o.createRef(e.key),
											innerHeightRef: s.createRef(e.key),
											borderTop: !!t,
											isNarrow: le,
											height: fe ? void 0 : de,
											liquidHeight: fe
										}))
									}),
									d && D(Sw, {
										nowDate: c,
										dayDate: d,
										dateProfile: e.dateProfile,
										totalHeight: de == null ? void 0 : de * ue
									}),
									!!(he || _e) && D("div", {
										className: V(Z(v.fillerClass, { inTableHeader: !1 }), B.borderlessX, B.borderlessBottom, he && B.liquid),
										style: { minHeight: _e }
									})
								]
							}) })
						}),
						D("div", { className: Z(v.slotHeaderDividerClass, {
							inTableHeader: !1,
							options: { dayMinWidth: v.dayMinWidth }
						}) }),
						D("div", {
							className: V(B.flexCol, B.liquid),
							children: [D(yv, {
								vertical: T,
								horizontal: !0,
								hideScrollbars: te || u,
								className: V(B.flexCol, B.rel, T && B.liquid),
								ref: this.mainScrollerRef,
								clientWidthRef: this.handleClientWidth,
								clientHeightRef: this.handleClientHeight,
								children: D("div", {
									className: V(B.flexCol, B.grow, B.rel),
									style: {
										width: ae,
										height: me
									},
									children: [D(yw, {
										dateProfile: e.dateProfile,
										nowDate: e.nowDate,
										nowMs: e.nowMs,
										todayRange: e.todayRange,
										cells: e.cells,
										slatCnt: ue,
										forPrint: u,
										isHitComboAllowed: e.isHitComboAllowed,
										className: re ? "" : B.fill,
										fgEventSegsByCol: e.fgEventSegsByCol,
										bgEventSegsByCol: e.bgEventSegsByCol,
										businessHourSegsByCol: e.businessHourSegsByCol,
										nowIndicatorSegsByCol: e.nowIndicatorSegsByCol,
										dateSelectionSegsByCol: e.dateSelectionSegsByCol,
										eventDragByCol: e.eventDragByCol,
										eventResizeByCol: e.eventResizeByCol,
										eventSelection: e.eventSelection,
										colWidth: oe,
										slatHeight: de,
										cellIsNarrow: le,
										cellIsMicro: ce
									}), !re && D(w, { children: [D("div", {
										"aria-hidden": !0,
										className: V(B.flexCol, T && v.expandRows && B.grow, ne ? B.fillX : B.rel),
										children: e.slatMetas.map((e, t) => D("div", {
											className: V(B.flexRow, fe && B.liquid),
											style: { height: fe ? "" : de },
											children: x(Dw, {
												...e,
												key: e.key,
												borderTop: !!t
											})
										}, e.key))
									}), he && D("div", { className: V(Z(v.fillerClass, { inTableHeader: !1 }), B.borderlessX, B.borderlessBottom, B.liquid) })] })]
								})
							}), !!te && D(MC, {
								isSticky: !0,
								canvasWidth: ae,
								scrollerRef: this.footScrollerRef,
								scrollbarWidthRef: this.handleStickyBottomScrollbarWidth
							})]
						})
					]
				})]
			}),
			D(gx, { widthRef: this.handleTotalWidth })
		] });
	}
	componentDidMount() {
		this._isUnmounting = !1, this.initScrollers(), this.updateSlatHeight();
	}
	componentDidUpdate() {
		this.updateScrollers(), this.updateSlatHeight();
	}
	componentWillUnmount() {
		this._isUnmounting = !0, this.destroyScrollers(), this.prevSlatHeight = void 0, Y(this.props.slatHeightRef, null);
	}
	updateSlatHeight() {
		this.prevSlatHeight !== this.slatHeight && Y(this.props.slatHeightRef, this.prevSlatHeight = this.slatHeight);
	}
	initScrollers() {
		let e = R_(this.context.pluginHooks);
		this.dayScroller = new e(!0), this.timeScroller = new e(), Y(this.props.dayScrollerRef, this.dayScroller), Y(this.props.timeScrollerRef, this.timeScroller), this.updateScrollers();
	}
	updateScrollers() {
		this.dayScroller.handleChildren([
			this.headerScrollerRef.current,
			this.allDayScrollerRef.current,
			this.mainScrollerRef.current,
			this.footScrollerRef.current
		]), this.timeScroller.handleChildren([this.axisScrollerRef.current, this.mainScrollerRef.current]);
	}
	destroyScrollers() {
		Y(this.props.dayScrollerRef, null), Y(this.props.timeScrollerRef, null);
	}
};
jw.addPropsEquality({ headerTierHeights: sm });
var Mw = class extends J {
	constructor() {
		super(...arguments), this.state = {}, this.headerLabelInnerWidthRefMap = new hx(() => {
			Q(this.handleAxisInnerWidths);
		}), this.handleAllDayLabelInnerWidth = (e) => {
			this.allDayLabelInnerWidth = e, Q(this.handleAxisInnerWidths);
		}, this.handleWeekNumberInnerWidth = (e) => {
			this.weekNumberInnerWidth = e, Q(this.handleAxisInnerWidths);
		}, this.slatLabelInnerWidthRefMap = new hx(() => {
			Q(this.handleAxisInnerWidths);
		}), this.slatLabelInnerHeightRefMap = new hx(() => {
			Q(this.handleSlatInnerHeights);
		}), this.handleTotalWidth = (e) => {
			this._isUnmounting || f_(() => {
				this._isUnmounting || this.setState({ totalWidth: e });
			});
		}, this.handleClientWidth = (e) => {
			this._isUnmounting || this.setState({ clientWidth: e });
		}, this.handleClientHeight = (e) => {
			this._isUnmounting || this.setState({ clientHeight: e });
		}, this.handleAxisInnerWidths = () => {
			if (this._isUnmounting) return;
			let e = this.headerLabelInnerWidthRefMap.current, t = this.slatLabelInnerWidthRefMap.current, n = Math.max(this.weekNumberInnerWidth || 0, this.allDayLabelInnerWidth || 0);
			for (let t of e.values()) n = Math.max(n, t);
			for (let e of t.values()) n = Math.max(n, e);
			this.state.axisWidth !== n && this.setState({ axisWidth: n });
		}, this.handleSlatInnerHeights = () => {
			if (this._isUnmounting) return;
			let e = this.slatLabelInnerHeightRefMap.current, t = 0;
			for (let n of e.values()) t = Math.max(t, n);
			this.state.slatInnerHeight !== t && this.setState({ slatInnerHeight: t });
		};
	}
	render() {
		let { props: e, state: t, context: n, slatLabelInnerWidthRefMap: r, slatLabelInnerHeightRefMap: i, headerLabelInnerWidthRefMap: a } = this, { nowDate: o, forPrint: s } = e, c = !s && n.options.nowIndicator ? $C(o, e.nowIndicatorSegsByCol, e.cells) : void 0, { axisWidth: l, clientWidth: u, totalWidth: d } = t, { options: f } = n, { borderlessX: p, borderlessTop: m, borderlessBottom: h } = rm(f), g = d != null && u != null && !s ? d - u : void 0, _ = !s && !F_(f), v = !s && I_(f), y = e.slatMetas.length, [b, S] = QC(_ && f.expandRows, y, f.slotMinHeight, t.slatInnerHeight, t.clientHeight);
		this.slatHeight = b;
		let C = (b || 0) * y, T = _ && !f.expandRows && t.clientHeight != null && t.clientHeight > C, ee = uw(s, f.eventPrintLayout) === "stack", te = s && !ee, E = s && ee, ne = te ? C : void 0, re = e.cells.length, ie = u == null ? void 0 : u / re, ae = ie != null && ie <= 60, oe = ae || ie != null && ie <= f.dayNarrowWidth;
		return D(w, { children: [
			f.dayHeaders && D("div", {
				role: "rowgroup",
				className: V(Z(f.tableHeaderClass, {
					isSticky: v,
					borderlessX: p,
					borderlessTop: m,
					borderlessBottom: h,
					multiMonthColumns: 0
				}), B.flexCol, v && B.tableHeaderSticky, B.z1),
				children: [e.headerTiers.map((t, n) => D("div", {
					role: "row",
					className: B.flexRow,
					children: [
						D("div", {
							className: V(f.dayHeaderRowClass, B.flexRow, B.borderlessX, B.borderlessTop, n === e.headerTiers.length - 1 && B.borderlessBottom),
							children: f.weekNumbers && t.isDateRow ? D(kw, {
								dateProfile: e.dateProfile,
								innerWidthRef: this.handleWeekNumberInnerWidth,
								innerHeightRef: a.createRef(n),
								width: l,
								isLiquid: !1,
								isNarrow: oe
							}) : D(Aw, {
								width: l,
								isLiquid: !1
							})
						}),
						D("div", { className: Z(f.slotHeaderDividerClass, {
							inTableHeader: !0,
							options: { dayMinWidth: f.dayMinWidth }
						}) }),
						D(DC, {
							...t,
							className: B.liquid,
							borderBottom: n < e.headerTiers.length - 1,
							viewportWidth: u,
							cellIsNarrow: oe,
							cellIsMicro: ae,
							rowLevel: e.headerTiers.length - n - 1
						}),
						!!g && D("div", {
							className: V(Z(f.fillerClass, { inTableHeader: !0 }), B.borderlessY, B.borderlessEnd),
							style: { minWidth: g }
						})
					]
				}, n)), D("div", { className: Z(f.dayHeaderDividerClass, {
					isSticky: v,
					multiMonthColumns: 0,
					options: { allDaySlot: !!f.allDaySlot }
				}) })]
			}),
			D("div", {
				role: "rowgroup",
				className: V(Z(f.tableBodyClass, {
					borderlessX: p,
					borderlessTop: m,
					borderlessBottom: h,
					multiMonthColumns: 0
				}), B.flexCol, _ && B.liquid, B.isolate, B.z0),
				children: [f.allDaySlot && D(w, { children: [D("div", {
					role: "row",
					className: V(B.flexRow, B.z1),
					children: [
						D(YC, {
							width: l,
							innerWidthRef: this.handleAllDayLabelInnerWidth,
							isNarrow: oe
						}),
						D("div", { className: Z(f.slotHeaderDividerClass, {
							inTableHeader: !1,
							options: { dayMinWidth: f.dayMinWidth }
						}) }),
						D(ZC, {
							dateProfile: e.dateProfile,
							todayRange: e.todayRange,
							cells: e.cells,
							showDayNumbers: !1,
							forPrint: s,
							isHitComboAllowed: e.isHitComboAllowed,
							className: V(B.liquidX, B.borderless),
							cellIsNarrow: oe,
							cellIsMicro: ae,
							fgEventSegs: e.fgEventSegs,
							bgEventSegs: e.bgEventSegs,
							businessHourSegs: e.businessHourSegs,
							dateSelectionSegs: e.dateSelectionSegs,
							eventDrag: e.eventDrag,
							eventResize: e.eventResize,
							eventSelection: e.eventSelection,
							dayMaxEvents: e.dayMaxEvents,
							dayMaxEventRows: e.dayMaxEventRows
						}),
						!!g && D("div", {
							className: V(Z(f.fillerClass, { inTableHeader: !1 }), B.borderlessY, B.borderlessEnd),
							style: { minWidth: g }
						})
					]
				}), D("div", { className: V(f.allDayDividerClass, B.z2) })] }), D(yv, {
					vertical: _,
					className: V(B.flexCol, B.rel, _ && B.liquid, B.z0),
					ref: e.timeScrollerRef,
					clientWidthRef: this.handleClientWidth,
					clientHeightRef: this.handleClientHeight,
					children: D("div", {
						className: V(B.flexCol, B.grow, B.rel),
						style: { height: ne },
						children: [D("div", {
							role: "row",
							className: V(B.flexRow, !E && B.fill),
							children: [
								D("div", {
									role: "rowheader",
									className: B.contentBox,
									style: { width: l },
									children: D("div", {
										className: B.srOnly,
										children: f.timedText
									})
								}),
								D("div", { className: Z(f.slotHeaderDividerClass, {
									inTableHeader: !1,
									options: { dayMinWidth: f.dayMinWidth }
								}) }),
								D(yw, {
									dateProfile: e.dateProfile,
									nowDate: e.nowDate,
									nowMs: e.nowMs,
									todayRange: e.todayRange,
									cells: e.cells,
									slatCnt: y,
									forPrint: s,
									isHitComboAllowed: e.isHitComboAllowed,
									className: B.liquid,
									fgEventSegsByCol: e.fgEventSegsByCol,
									bgEventSegsByCol: e.bgEventSegsByCol,
									businessHourSegsByCol: e.businessHourSegsByCol,
									nowIndicatorSegsByCol: e.nowIndicatorSegsByCol,
									dateSelectionSegsByCol: e.dateSelectionSegsByCol,
									eventDragByCol: e.eventDragByCol,
									eventResizeByCol: e.eventResizeByCol,
									eventSelection: e.eventSelection,
									slatHeight: b,
									cellIsNarrow: oe,
									cellIsMicro: ae
								})
							]
						}), !E && D(w, { children: [
							D("div", {
								"aria-hidden": !0,
								className: V(B.flexCol, _ && f.expandRows && B.grow, te ? B.fillX : B.rel),
								children: e.slatMetas.map((e, t) => D("div", {
									className: V(S && B.liquid, B.flexRow),
									style: { height: S ? void 0 : b },
									children: [
										D("div", {
											className: B.flexCol,
											style: { width: l },
											children: x(ww, {
												...e,
												key: e.key,
												innerWidthRef: r.createRef(e.key),
												innerHeightRef: i.createRef(e.key),
												borderTop: !!t,
												isNarrow: oe,
												height: S ? void 0 : b,
												liquidHeight: S
											})
										}),
										D("div", {
											className: Z(f.slotHeaderDividerClass, {
												inTableHeader: !1,
												options: { dayMinWidth: f.dayMinWidth }
											}),
											style: { visibility: "hidden" }
										}),
										x(Dw, {
											...e,
											key: e.key,
											borderTop: !!t
										})
									]
								}, e.key))
							}),
							T && D("div", { className: V(Z(f.fillerClass, { inTableHeader: !1 }), B.borderlessX, B.borderlessBottom, B.liquid) }),
							c && D(Sw, {
								nowDate: o,
								dayDate: c,
								dateProfile: e.dateProfile,
								totalHeight: b == null ? void 0 : b * y
							})
						] })]
					})
				})]
			}),
			D(gx, { widthRef: this.handleTotalWidth })
		] });
	}
	componentDidMount() {
		this._isUnmounting = !1, this.updateSlatHeight();
	}
	componentDidUpdate() {
		this.updateSlatHeight();
	}
	componentWillUnmount() {
		this._isUnmounting = !0, this.prevSlatHeight = void 0, Y(this.props.slatHeightRef, null);
	}
	updateSlatHeight() {
		this.prevSlatHeight !== this.slatHeight && Y(this.props.slatHeightRef, this.prevSlatHeight = this.slatHeight);
	}
};
function Nw(e) {
	return e.map(() => []);
}
function Pw(e) {
	return e.map(() => null);
}
var Fw = class extends J {
	constructor() {
		super(...arguments), this.buildSlatMetas = q(qC), this.dayScrollerRef = C(), this.timeScrollerRef = C(), this.scrollState = {}, this.handleSlatHeight = (e) => {
			this._isUnmounting || (this.slatHeight = e, e != null && Q(this.applyTimeScroll));
		}, this.handleTimeScrollRequest = (e) => {
			this.scrollState.time = e, this.scrollState.y = void 0, this.applyTimeScroll();
		}, this.handleTimeScrollEnd = (e) => {
			if (e) {
				let e = this.timeScrollerRef.current.y;
				this.props.forPrint || (this.scrollState.y = e, this.scrollState.time = void 0);
			}
		}, this.applyTimeScroll = () => {
			let e = this.timeScrollerRef.current, { slatHeight: t, scrollState: n } = this, { y: r, time: i } = n;
			r == null && i && t != null && e && (r = tw(i, this.props.dateProfile) * (t * this.currentSlatCnt), r && r++, n.y = r), r != null && e.scrollTo({ y: r });
		};
	}
	render() {
		let { props: e, context: t } = this, { dateProfile: n } = e, { options: r, dateEnv: i } = t, { dayMinWidth: a } = r, { borderlessX: o, borderlessTop: s, borderlessBottom: c } = rm(r), l = this.buildSlatMetas(n.slotMinTime, n.slotMaxTime, r.slotHeaderInterval, r.slotDuration, i);
		this.currentSlatCnt = l.length;
		let u = e.forPrint ? [] : e.dateSelectionSegs, d = e.forPrint ? null : e.eventDrag, f = e.forPrint ? null : e.eventResize, p = e.forPrint ? Nw(e.dateSelectionSegsByCol) : e.dateSelectionSegsByCol, m = e.forPrint ? Pw(e.eventDragByCol) : e.eventDragByCol, h = e.forPrint ? Pw(e.eventResizeByCol) : e.eventResizeByCol, g = {
			dateProfile: n,
			nowDate: e.nowDate,
			nowMs: e.nowMs,
			todayRange: e.todayRange,
			cells: e.cells,
			slatMetas: l,
			forPrint: e.forPrint,
			isHitComboAllowed: e.isHitComboAllowed,
			headerTiers: e.headerTiers,
			fgEventSegs: e.fgEventSegs,
			bgEventSegs: e.bgEventSegs,
			businessHourSegs: e.businessHourSegs,
			dateSelectionSegs: u,
			eventDrag: d,
			eventResize: f,
			...Lw(r),
			fgEventSegsByCol: e.fgEventSegsByCol,
			bgEventSegsByCol: e.bgEventSegsByCol,
			businessHourSegsByCol: e.businessHourSegsByCol,
			nowIndicatorSegsByCol: e.nowIndicatorSegsByCol,
			dateSelectionSegsByCol: p,
			eventDragByCol: m,
			eventResizeByCol: h,
			eventSelection: e.eventSelection,
			timeScrollerRef: this.timeScrollerRef,
			timeScrollState: this.scrollState,
			slatHeightRef: this.handleSlatHeight,
			borderlessX: o,
			borderlessBottom: c
		};
		return D(mv, {
			attrs: {
				role: "table",
				"aria-colcount": e.cells.length + 1,
				"aria-labelledby": e.labelId,
				"aria-label": e.labelStr
			},
			className: V(e.className, Z(r.tableClass, {
				borderlessX: o,
				borderlessTop: s,
				borderlessBottom: c,
				multiMonthColumns: 0
			}), !e.forPrint && B.flexCol, B.isolate),
			viewSpec: t.viewSpec,
			children: a ? D(jw, {
				...g,
				dayMinWidth: a,
				dayScrollerRef: this.dayScrollerRef
			}) : D(Mw, { ...g })
		});
	}
	componentDidMount() {
		this._isUnmounting = !1, !this.scrollDateProfile || this.props.dateProfile !== this.scrollDateProfile && this.context.options.scrollTimeReset ? this.resetScroll() : this.applyTimeScroll(), this.scrollDateProfile = this.props.dateProfile, this.context.emitter.on("_timeScrollRequest", this.handleTimeScrollRequest);
		let e = this.timeScrollerRef.current;
		e && e.addScrollEndListener(this.handleTimeScrollEnd);
	}
	componentDidUpdate(e) {
		this.scrollDateProfile = this.props.dateProfile, e.dateProfile !== this.props.dateProfile && this.context.options.scrollTimeReset ? this.resetScroll() : e.forPrint && !this.props.forPrint && this.applyTimeScroll();
	}
	componentWillUnmount() {
		this._isUnmounting = !0, this.context.emitter.off("_timeScrollRequest", this.handleTimeScrollRequest);
		let e = this.timeScrollerRef.current;
		e && e.removeScrollEndListener(this.handleTimeScrollEnd);
	}
	resetScroll() {
		this.handleTimeScrollRequest(this.context.options.scrollTime);
		let e = this.dayScrollerRef.current;
		e && e.scrollTo({ x: 0 });
	}
}, Iw = 5;
function Lw(e) {
	let { dayMaxEvents: t, dayMaxEventRows: n } = e;
	return (t === !0 || n === !0) && (t = void 0, n = Iw), {
		dayMaxEvents: t,
		dayMaxEventRows: n
	};
}
var Rw = {
	name: "timegrid",
	initialView: "timeGridWeek",
	deps: [LC],
	views: {
		timeGrid: {
			component: class extends gv {
				constructor() {
					super(...arguments), this.createDayHeaderFormatter = q(yC), this.buildDaySeries = q((e, t) => new cC(e.renderRange, t)), this.buildDayCols = q(RC), this.extractColDates = q((e) => e.map((e) => e.date)), this.extractColRanges = q((e) => e.map((e) => e.range)), this.buildDateRowConfigs = q(dx), this.splitFgEventSegs = q(WC), this.splitBgEventSegs = q(WC), this.splitBusinessHourSegs = q(WC), this.splitNowIndicatorSegs = q(WC), this.splitDateSelectionSegs = q(WC), this.splitEventDrag = q(GC), this.splitEventResize = q(GC), this.allDaySplitter = new HC(), this.daySeriesSlicer = new lx(), this.dayTimeColsSlicer = new UC();
				}
				render() {
					let { props: e, context: t } = this, { dateProfile: n } = e, { options: r, dateProfileGenerator: i } = t, a = this.buildDaySeries(n, i), o = this.buildDayCols(a, t.dateEnv, {
						slotRange: n,
						activeRange: n.activeRange
					}), s = this.extractColDates(o), c = this.extractColRanges(o), l = this.allDaySplitter.splitProps(e), u = this.daySeriesSlicer.sliceProps(l.allDay, n, r.nextDayThreshold, t, a), d = this.dayTimeColsSlicer.sliceProps(l.timed, n, null, t, c), f = this.createDayHeaderFormatter(t.options.dayHeaderFormat, !0, o.length);
					return D(iv, {
						unit: r.nowIndicator ? "minute" : "day",
						children: (i, a, l) => {
							let p = o.length, m = !e.forPrint && r.nowIndicator && this.dayTimeColsSlicer.sliceNowDate(i, n, r.nextDayThreshold, t, c), h = this.splitFgEventSegs(d.fgEventSegs, p), g = this.splitBgEventSegs(d.bgEventSegs, p), _ = this.splitBusinessHourSegs(d.businessHourSegs, p), v = this.splitNowIndicatorSegs(m, p), y = this.splitDateSelectionSegs(d.dateSelectionSegs, p), b = this.splitEventDrag(d.eventDrag, p), x = this.splitEventResize(d.eventResize, p), S = this.buildDateRowConfigs(s, !0, e.dateProfile, a, f, t);
							return D(Fw, {
								labelId: e.labelId,
								labelStr: e.labelStr,
								dateProfile: n,
								nowDate: i,
								nowMs: l,
								todayRange: a,
								cells: o,
								forPrint: e.forPrint,
								className: e.className,
								headerTiers: S,
								fgEventSegs: u.fgEventSegs,
								bgEventSegs: u.bgEventSegs,
								businessHourSegs: u.businessHourSegs,
								dateSelectionSegs: u.dateSelectionSegs,
								eventDrag: u.eventDrag,
								eventResize: u.eventResize,
								fgEventSegsByCol: h,
								bgEventSegsByCol: g,
								businessHourSegsByCol: _,
								nowIndicatorSegsByCol: v,
								dateSelectionSegsByCol: y,
								eventDragByCol: b,
								eventResizeByCol: x,
								eventSelection: e.eventSelection
							});
						}
					});
				}
			},
			usesMinMaxTime: !0,
			allDaySlot: !0,
			slotDuration: "00:30:00",
			slotEventOverlap: !0
		},
		timeGridDay: {
			type: "timeGrid",
			duration: { days: 1 }
		},
		timeGridWeek: {
			type: "timeGrid",
			duration: { weeks: 1 }
		}
	}
}, zw = class extends J {
	render() {
		let { props: e, context: t } = this, { options: n } = t, r = t.dateEnv.formatToParts(e.dayDate, e.dayFormat), i = fp(r), a = n.navLinks, o = {
			...e.dateMeta,
			view: t.viewApi,
			text: i,
			textParts: r,
			get weekdayText() {
				return cv(r);
			},
			get dayNumberText() {
				return lv(r);
			},
			get monthText() {
				return uv(r);
			},
			hasNavLink: a,
			level: e.level
		};
		return D(X, {
			tag: "div",
			attrs: a ? fv(this.context, e.dayDate, void 0, i, this.props.isTabbable) : {},
			renderProps: o,
			generatorName: "listDayHeaderContent",
			customGenerator: n.listDayHeaderContent,
			defaultGenerator: P_,
			classNameGenerator: n.listDayHeaderInnerClass
		});
	}
}, Bw = class extends J {
	render() {
		let { options: e, viewApi: t, viewSpec: n } = this.context, { dayDate: r, dateMeta: i } = this.props, a = !this.props.forPrint, o = e.listDayFormat === void 0 ? Vw(n) : e.listDayFormat, s = e.listDayAltFormat === void 0 ? Hw(n) : e.listDayAltFormat, c = {
			...i,
			view: t
		};
		return D(X, {
			tag: "div",
			attrs: {
				"data-date": cp(r),
				...i.isToday ? { "aria-current": "date" } : {}
			},
			className: a ? B.stickyT : "",
			renderProps: c,
			generatorName: void 0,
			classNameGenerator: e.listDayHeaderClass,
			didMount: e.listDayHeaderDidMount,
			willUnmount: e.listDayHeaderWillUnmount,
			children: () => D(w, { children: [!!o && D(zw, {
				dayDate: r,
				dayFormat: o,
				isTabbable: !0,
				dateMeta: i,
				level: 0
			}), !!s && D(zw, {
				dayDate: r,
				dayFormat: s,
				isTabbable: !1,
				dateMeta: i,
				level: 1
			})] })
		});
	}
};
function Vw({ durationUnit: e, singleUnit: t }) {
	return t === "day" || e === "day" || t === "week" ? sv : av;
}
function Hw({ durationUnit: e, singleUnit: t }) {
	if (t !== "day") return e === "day" || t === "week" ? av : sv;
}
var Uw = H({
	hour: "numeric",
	minute: "2-digit",
	meridiem: "short"
}), Ww = class extends J {
	render() {
		let { props: e, context: t } = this, { eventRange: n } = e, { displayEventTime: r } = t.options, i = r !== !1 && (n.def.allDay || !e.isStart && !e.isEnd) ? t.options.allDayText : void 0;
		return D(Dv, {
			...e,
			forcedTimeText: i,
			defaultTimeFormat: Uw,
			disableDragging: !0,
			disableResizing: !0,
			disableZindexes: !0,
			display: "list-item"
		});
	}
}, Gw = class extends J {
	constructor() {
		super(...arguments), this.getDateMeta = q(pv), this.sortEventSegs = q(Qh);
	}
	render() {
		let { props: e, context: t } = this, { nowDate: n, todayRange: r } = e, { options: i } = t, a = this.getDateMeta(e.dayDate, t.dateEnv, void 0, r), o = this.sortEventSegs(e.segs, i.eventOrder), s = dv(this.context, e.dayDate), c = {
			...a,
			isFirst: e.isFirst,
			isLast: e.isLast,
			view: t.viewApi
		}, l = {
			...a,
			view: t.viewApi
		};
		return D("div", {
			role: "listitem",
			"aria-label": s,
			className: Z(i.listDayClass, c),
			children: [D(Bw, {
				dayDate: e.dayDate,
				dateMeta: a,
				forPrint: e.forPrint
			}), D("div", {
				role: "list",
				"aria-label": i.eventsHint,
				className: V(Z(i.listDayBodyClass, l), B.flexCol),
				children: o.map((t, i) => {
					let a = t.eventRange.instance.instanceId, s = i === 0, c = i === o.length - 1;
					return D("div", {
						role: "listitem",
						className: B.flexCol,
						children: D(Ww, {
							eventRange: t.eventRange,
							slicedStart: t.slicedStart,
							slicedEnd: t.slicedEnd,
							isStart: t.isStart,
							isEnd: t.isEnd,
							isFirst: s,
							isLast: c,
							isDragging: !1,
							isResizing: !1,
							isMirror: !1,
							isSelected: !1,
							...ig(t.eventRange, r, n, e.nowMs)
						})
					}, a);
				})
			})]
		});
	}
}, Kw = class extends gv {
	constructor() {
		super(...arguments), this.computeDateVars = q(Jw), this.eventStoreToSegs = q(this._eventStoreToSegs), this.setRootEl = (e) => {
			e ? this.context.registerInteractiveComponent(this, {
				el: e,
				disableHits: !0
			}) : this.context.unregisterInteractiveComponent(this);
		};
	}
	render() {
		let { props: e, context: t } = this, { options: n } = t, { dayDates: r, dayRanges: i } = this.computeDateVars(e.dateProfile), a = this.eventStoreToSegs(e.eventStore, e.eventUiBases, i), o = !e.forPrint && !F_(n);
		return D(mv, {
			viewSpec: t.viewSpec,
			className: V(e.className, B.flexCol),
			elRef: this.setRootEl,
			children: a.length ? D(yv, {
				vertical: o,
				className: V(B.flexCol, o ? B.liquid : ""),
				children: this.renderSegList(a, r)
			}) : this.renderEmptyMessage()
		});
	}
	renderEmptyMessage() {
		let { options: e, viewApi: t } = this.context, n = {
			text: e.noEventsText,
			view: t
		};
		return D(X, {
			tag: "div",
			attrs: { role: "status" },
			renderProps: n,
			generatorName: "noEventsContent",
			customGenerator: e.noEventsContent,
			defaultGenerator: qw,
			classNameGenerator: e.noEventsClass,
			className: B.grow,
			didMount: e.noEventsDidMount,
			willUnmount: e.noEventsWillUnmount,
			children: (t) => D(t, {
				tag: "div",
				className: Z(e.noEventsInnerClass, n)
			})
		});
	}
	renderSegList(e, t) {
		let { options: n } = this.context, r = Yw(e);
		return D("div", {
			role: "list",
			"aria-labelledby": this.props.labelId,
			"aria-label": this.props.labelStr,
			className: V(B.flexCol, V(n.listDaysClass)),
			children: D(iv, {
				unit: "day",
				children: (e, n, i) => {
					let a = [], o = r.reduce((e, t) => e + +!!t, 0), s = 0;
					for (let c = 0; c < r.length; c += 1) {
						let l = r[c];
						if (l) {
							let r = t[c], u = cp(r), d = s === 0, f = s === o - 1;
							a.push(D(Gw, {
								dayDate: r,
								nowDate: e,
								nowMs: i,
								todayRange: n,
								segs: l,
								isFirst: d,
								isLast: f,
								forPrint: this.props.forPrint
							}, u)), s += 1;
						}
					}
					return D(w, { children: a });
				}
			})
		});
	}
	_eventStoreToSegs(e, t, n) {
		return this.eventRangesToSegs(Gh(e, t, this.props.dateProfile.activeRange, this.context.options.nextDayThreshold).fg, n);
	}
	eventRangesToSegs(e, t) {
		let n = [];
		for (let r of e) n.push(...this.eventRangeToSegs(r, t));
		return n;
	}
	eventRangeToSegs(e, t) {
		let n = e.range, r, i = [];
		for (r = 0; r < t.length; r += 1) {
			let a = Lf(n, t[r]);
			a && i.push({
				eventRange: e,
				slicedStart: a.start,
				slicedEnd: a.end,
				isStart: e.isStart && n.start.valueOf() === a.start.valueOf(),
				isEnd: e.isEnd && n.end.valueOf() === a.end.valueOf(),
				dayIndex: r
			});
		}
		return i;
	}
};
function qw(e) {
	return e.text;
}
function Jw(e) {
	let t = R(e.renderRange.start), n = e.renderRange.end, r = [], i = [];
	for (; t < n;) r.push(t), i.push({
		start: t,
		end: L(t, 1)
	}), t = L(t, 1);
	return {
		dayDates: r,
		dayRanges: i
	};
}
function Yw(e) {
	let t = [], n, r;
	for (n = 0; n < e.length; n += 1) r = e[n], (t[r.dayIndex] || (t[r.dayIndex] = [])).push(r);
	return t;
}
var Xw = {
	name: "list",
	views: {
		list: {
			component: Kw,
			buttonTextKey: "listText",
			disallowAmbigTitle: !0
		},
		listDay: {
			type: "list",
			duration: { days: 1 }
		},
		listWeek: {
			type: "list",
			duration: { weeks: 1 }
		},
		listMonth: {
			type: "list",
			duration: { month: 1 }
		},
		listYear: {
			type: "list",
			duration: { year: 1 }
		}
	}
}, Zw = class extends gv {
	constructor() {
		super(...arguments), this.state = {}, this.buildDayTableModel = q(dC), this.createDayHeaderFormatter = q(yC), this.buildDateRowConfig = q(fx), this.titleElRef = C(), this.rowHeightRefMap = new hx(() => {
			Q(this.handleHeights);
		}), this.slicer = new cx(), this.handleEl = (e) => {
			let { options: t } = this.context;
			e && (this.rootEl = e, t.singleMonthDidMount?.({
				el: this.rootEl,
				...this.renderProps
			}));
		}, this.handleTableHeaderEl = (e) => {
			this.disconnectTableHeaderHeight?.(), this.disconnectTableHeaderHeight = void 0, e && (this.disconnectTableHeaderHeight = rv(e, (e) => {
				this.setState({ tableHeaderHeight: this.tableHeaderHeight = e }), Q(this.handleHeights);
			}));
		}, this.handleGridWidth = (e) => {
			this._isUnmounting || this.setState({ gridWidth: e });
		}, this.handleHeights = () => {
			this._isUnmounting || Y(this.props.heightsRef, {
				titleHeight: this.titleHeight,
				tableHeaderHeight: this.tableHeaderHeight,
				rowHeightMap: this.rowHeightRefMap.current,
				cellRows: this.cellRows
			});
		};
	}
	get titleId() {
		return this.context.baseId + "month-" + this.props.isoDateStr;
	}
	render() {
		let { props: e, state: t, context: n } = this, { dateProfile: r, forPrint: i } = e, { options: a, dateEnv: o } = n, { borderlessX: s, borderlessTop: c, borderlessBottom: l } = rm(a), u = this.buildDayTableModel(r, n.dateProfileGenerator, o), d = this.slicer.sliceProps(e, r, a.nextDayThreshold, n, u), f = this.createDayHeaderFormatter(a.dayHeaderFormat, !1, u.colCount), p = this.buildDateRowConfig(u.headerDates, !1, r, e.todayRange, f, n);
		this.cellRows = u.cellRows;
		let m = !i && e.colCount === 1, h = !i || e.hasLateralSiblings, g = u.cellRows[0].length, _ = t.gridWidth == null ? void 0 : t.gridWidth / g, v = _ != null && _ <= 60, y = v || _ != null && _ <= a.dayNarrowWidth, b = t.gridWidth == null ? void 0 : 1 / a.aspectRatio * t.gridWidth / 6, x = m ? b : void 0, S = m && b != null && t.tableHeaderHeight != null ? b + t.tableHeaderHeight + 1 : void 0, C = d.businessHourSegs, w = i ? [] : d.dateSelectionSegs, T = i ? null : d.eventDrag, ee = i ? null : d.eventResize, te = i && !e.hasLateralSiblings, E = a.navLinks && e.colCount > 1, ne = {
			multiMonthColumns: e.colCount || 0,
			isSticky: m,
			isNarrow: y,
			hasNavLink: E
		}, re = e.dateProfile.currentRange.start, ie = E ? fv(n, re, "month", e.isoDateStr) : {};
		return D("div", {
			role: "listitem",
			style: { width: e.width },
			children: D("div", {
				role: "table",
				"aria-labelledby": this.titleId,
				"data-date": e.isoDateStr,
				className: V(Z(a.singleMonthClass, {
					isFirst: e.isFirst,
					isLast: e.isLast,
					multiMonthColumns: e.colCount || 0
				}), B.flexCol, e.hasLateralSiblings && B.breakInsideAvoid),
				children: [
					D(gx, { widthRef: this.handleGridWidth }),
					D("div", {
						id: this.titleId,
						ref: this.titleElRef,
						className: V(Z(a.singleMonthHeaderClass, ne), m && B.stickyT, m && B.z3, B.flexCol),
						style: { marginBottom: S },
						children: D("div", {
							...ie,
							className: V(Z(a.singleMonthHeaderInnerClass, ne), ie.className),
							children: fp(o.formatToParts(re, e.titleFormat))
						})
					}),
					te ? D(kC, {
						dateProfile: e.dateProfile,
						todayRange: e.todayRange,
						cellRows: u.cellRows,
						headerTiers: [p],
						showHeader: !0,
						headerElRef: this.handleTableHeaderEl,
						fgEventSegs: d.fgEventSegs,
						bgEventSegs: d.bgEventSegs,
						businessHourSegs: C,
						eventSelection: d.eventSelection,
						dayMaxEventRows: !0,
						borderlessX: s,
						borderlessTop: c,
						borderlessBottom: l,
						multiMonthColumns: e.colCount || 0,
						visibleWidth: t.gridWidth,
						cellIsNarrow: y,
						cellIsMicro: v,
						rowHeightRefMap: this.rowHeightRefMap,
						style: { marginTop: S == null ? void 0 : -S }
					}) : D("div", {
						className: V(Z(a.tableClass, {
							borderlessX: s,
							borderlessTop: c,
							borderlessBottom: l,
							multiMonthColumns: e.colCount || 0
						}), B.flexCol),
						style: { marginTop: S == null ? void 0 : -S },
						children: [D("div", {
							ref: this.handleTableHeaderEl,
							className: V(Z(a.tableHeaderClass, {
								isSticky: m,
								borderlessX: s,
								borderlessTop: c,
								borderlessBottom: l,
								multiMonthColumns: e.colCount || 0
							}), B.flexCol, m && B.sticky, m && B.z2),
							style: {
								top: m ? t.titleHeight : 0,
								marginBottom: x
							},
							children: [D(DC, {
								...p,
								role: "row",
								borderBottom: !1,
								cellIsNarrow: y,
								cellIsMicro: v,
								rowLevel: 0
							}), D("div", { className: Z(a.dayHeaderDividerClass, {
								isSticky: m,
								multiMonthColumns: e.colCount || 0,
								options: { allDaySlot: !!a.allDaySlot }
							}) })]
						}), D("div", {
							className: V(Z(a.tableBodyClass, {
								borderlessX: s,
								borderlessTop: c,
								borderlessBottom: l,
								multiMonthColumns: e.colCount || 0
							}), B.flexCol, h && B.rel, m && B.z1),
							style: {
								marginTop: x == null ? void 0 : -x,
								aspectRatio: h ? String(a.aspectRatio) : void 0
							},
							children: D(xC, {
								dateProfile: e.dateProfile,
								todayRange: e.todayRange,
								cellRows: u.cellRows,
								className: h ? B.fill : "",
								forPrint: i && !e.hasLateralSiblings,
								dayMaxEventRows: i && e.hasLateralSiblings ? 1 : !0,
								fgEventSegs: d.fgEventSegs,
								bgEventSegs: d.bgEventSegs,
								businessHourSegs: C,
								dateSelectionSegs: w,
								eventDrag: T,
								eventResize: ee,
								eventSelection: d.eventSelection,
								visibleWidth: t.gridWidth,
								cellIsNarrow: y,
								cellIsMicro: v,
								rowHeightRefMap: this.rowHeightRefMap
							})
						})]
					})
				]
			})
		});
	}
	componentDidMount() {
		this._isUnmounting = !1, this.disconnectTitleHeight = rv(this.titleElRef.current, (e) => {
			this.setState({ titleHeight: this.titleHeight = e }), Q(this.handleHeights);
		});
	}
	componentWillUnmount() {
		let { options: e } = this.context;
		this._isUnmounting = !0, this.disconnectTitleHeight(), e.singleMonthWillUnmount?.({
			el: this.rootEl,
			...this.renderProps
		});
	}
}, Qw = class extends gv {
	constructor() {
		super(...arguments), this.state = {}, this.splitDateProfileByMonth = q(eT), this.buildMonthFormat = q(rT), this.scrollerRef = C(), this.tilesElRef = C(), this.scrollState = {}, this.handleInnerWidth = (e) => {
			this._isUnmounting || this.setState({ innerWidth: e });
		}, this.handleScrollStart = () => {
			this.scrollState.date = void 0, this.scrollState.top = void 0;
		}, this.handleScrollEnd = (e) => {
			let t = this.scrollerRef.current;
			e && t && (this.scrollState.top = t.y, this.scrollState.date = void 0);
		};
	}
	render() {
		let { context: e, props: t, state: n } = this, { options: r } = e, i = !t.forPrint && !F_(r), a = this.splitDateProfileByMonth(e.dateProfileGenerator, t.dateProfile, e.dateEnv, r.fixedWeekCount, r.showNonCurrentDates), o = this.buildMonthFormat(r.singleMonthTitleFormat, a), { multiMonthMaxColumns: s, singleMonthMinWidth: c } = r, { innerWidth: l } = n, u, d, f = !1;
		return l != null && (u = Math.max(1, Math.min(s, Math.floor(l / c))), t.forPrint && (u = Math.min(u, 2)), d = Ap(1 / u), f = u > 1), D(iv, {
			unit: "day",
			children: (n, r) => D(mv, {
				viewSpec: e.viewSpec,
				className: V(!t.forPrint && B.flexCol, t.className),
				children: [D(yv, {
					vertical: i,
					className: i ? B.liquid : "",
					ref: this.scrollerRef,
					children: D("div", {
						role: "list",
						ref: this.tilesElRef,
						"aria-labelledby": t.labelId,
						"aria-label": t.labelStr,
						className: B.safeTiles,
						children: a.map((e, n) => {
							let i = lp(e.currentRange.start);
							return x(Zw, {
								...t,
								key: i,
								todayRange: r,
								isoDateStr: i,
								titleFormat: o,
								dateProfile: e,
								width: d,
								colCount: u,
								isFirst: !n,
								isLast: n === a.length - 1,
								hasLateralSiblings: f
							});
						})
					})
				}), D(gx, { widthRef: this.handleInnerWidth })]
			})
		});
	}
	componentDidMount() {
		this._isUnmounting = !1, this.scrollState.date = this.props.dateProfile.currentDate, this.scrollerRef.current.addScrollStartListener(this.handleScrollStart), this.scrollerRef.current.addScrollEndListener(this.handleScrollEnd), m_(() => {
			this.applyScroll();
		}, 0);
	}
	componentDidUpdate(e, t) {
		e.dateProfile === this.props.dateProfile ? t.innerWidth !== this.state.innerWidth && this.applyScroll() : this.context.options.scrollTimeReset ? this.resetScroll() : this.applyScroll();
	}
	componentWillUnmount() {
		this._isUnmounting = !0, this.scrollerRef.current.removeScrollStartListener(this.handleScrollStart), this.scrollerRef.current.removeScrollEndListener(this.handleScrollEnd);
	}
	resetScroll() {
		this.scrollState.date = this.props.dateProfile.currentDate, this.scrollState.top = void 0, this.applyScroll();
	}
	applyScroll() {
		let e = this.scrollerRef.current, t = this.computeScrollTop();
		e && t != null && e.scrollTo({ y: t });
	}
	computeScrollTop() {
		let { scrollState: e } = this;
		if (e.top != null) return e.top;
		if (e.date != null) {
			let t = this.tilesElRef.current, n = t?.querySelector(`[data-date="${lp(e.date)}"]`)?.parentElement;
			if (t && n) return Math.round(n.getBoundingClientRect().top) - Math.round(t.getBoundingClientRect().top);
		}
	}
}, $w = z(1, "month");
function eT(e, t, n, r, i) {
	let { start: a, end: o } = t.currentRange, s = a, c = [];
	for (; s.valueOf() < o.valueOf();) {
		let a = n.add(s, $w), o = {
			start: e.skipHiddenDays(s),
			end: e.skipHiddenDays(a, -1, !0)
		}, l = IC({
			currentRange: o,
			snapToWeek: !0,
			fixedWeekCount: r,
			dateEnv: n
		});
		l = {
			start: e.skipHiddenDays(l.start),
			end: e.skipHiddenDays(l.end, -1, !0)
		};
		let u = t.activeRange ? Lf(t.activeRange, i ? l : o) : null;
		c.push({
			currentDate: t.currentDate,
			isValid: t.isValid,
			validRange: t.validRange,
			renderRange: l,
			activeRange: u,
			currentRange: o,
			currentRangeUnit: "month",
			isRangeAllDay: !0,
			dateIncrement: t.dateIncrement,
			slotMinTime: t.slotMaxTime,
			slotMaxTime: t.slotMinTime
		}), s = a;
	}
	return c;
}
var tT = H({
	year: "numeric",
	month: "long"
}), nT = H({ month: "long" });
function rT(e, t) {
	return e || (t[0].currentRange.start.getUTCFullYear() === t[t.length - 1].currentRange.start.getUTCFullYear() ? nT : tT);
}
// ../../../../../localhost/Pushword/packages/newsletter/node_modules/fullcalendar/all/index.js
var iT = [
	rx,
	LC,
	Rw,
	Xw,
	{
		name: "multimonth",
		initialView: "multiMonthYear",
		views: {
			multiMonth: {
				component: Qw,
				dateProfileGeneratorClass: FC,
				multiMonthMaxColumns: 3,
				singleMonthMinWidth: 350
			},
			multiMonthYear: {
				type: "multiMonth",
				duration: { years: 1 },
				fixedWeekCount: !0,
				showNonCurrentDates: !1
			}
		}
	}
], aT = class extends Zy {
	constructor(e, t = {}) {
		super(e, {
			...t,
			plugins: [...iT, ...t.plugins || []]
		});
	}
}, oT = "fc-classic-vQz", sT = "fc-classic-0Bj", cT = "fc-classic-uqo", lT = "fc-classic-3Xj", uT = "fc-classic-fFh", dT = "fc-classic-zIi", fT = "fc-classic-BaR", pT = "fc-classic-4yP", mT = `${pT} fc-classic-tCP fc-classic-8gz`, hT = "fc-classic-Ubk", gT = `${hT} fc-classic-OIx fc-classic-28F`, _T = "fc-classic-XUJ", vT = "fc-classic-1EY fc-classic-pps fc-classic-vs6", yT = `${vT} fc-classic-AWB fc-classic-hza`, bT = `${vT} fc-classic-MaV fc-classic-uuA`, xT = "fc-classic-1EY fc-classic-3wQ fc-classic-wsy fc-classic-lNM fc-classic-Jk3 fc-classic-AAA", ST = `${xT} fc-classic-ERR fc-classic-Dq8`, CT = `${xT} fc-classic-1V6 fc-classic-F99`, wT = (e) => V("fc-classic-wsy", e.isMajor ? "fc-classic-C0k" : "fc-classic-C1x", e.isDisabled ? "fc-classic-iYS" : e.isToday && "fc-classic-hbn"), TT = (e) => V("fc-classic-wsy fc-classic-C1x", e.isMinor && "fc-classic-TN2"), ET = {
	listItemEventClass: (e) => V("fc-classic-Ika fc-classic-7A6 fc-classic-Fvv", e.isNarrow ? "fc-classic-148" : "fc-classic-cKZ", e.isSelected ? V("fc-classic-k3f", e.isDragging && "fc-classic-qNs") : e.isInteractive ? mT : pT),
	listItemEventBeforeClass: (e) => V("fc-classic-Mjo", e.isNarrow ? "fc-classic-148" : "fc-classic-rVY"),
	listItemEventInnerClass: (e) => V("fc-classic-dl1 fc-classic-1sP fc-classic-XpK fc-classic-z5u fc-classic-aTF", e.isNarrow ? oT : "fc-classic-a3B"),
	listItemEventTimeClass: "fc-classic-F1o fc-classic-TZ4 fc-classic-pKG fc-classic-1Zl",
	listItemEventTitleClass: "fc-classic-F1o fc-classic-DIS fc-classic-TZ4 fc-classic-pKG fc-classic-OLq",
	rowEventClass: (e) => V(e.isStart && V("fc-classic-kmj", e.isNarrow ? "fc-classic-qvL" : "fc-classic-Jzj"), e.isEnd && V("fc-classic-Skl", e.isNarrow ? "fc-classic-9hC" : "fc-classic-3e1")),
	rowEventInnerClass: "fc-classic-z5u fc-classic-aTF",
	rowEventTimeClass: "fc-classic-F1o",
	rowEventTitleClass: "fc-classic-F1o",
	rowMoreLinkClass: (e) => V("fc-classic-Ika fc-classic-wsy fc-classic-Fvv", e.isNarrow ? "fc-classic-148 fc-classic-0Pr" : "fc-classic-sI7 fc-classic-cKZ fc-classic-d0j", mT),
	rowMoreLinkInnerClass: (e) => V("fc-classic-7A6", e.isNarrow ? oT : "fc-classic-a3B")
}, DT = "fc-classic-vnf fc-classic-mAY", OT = "fc-classic-rVY fc-classic-XM3 fc-classic-rif fc-classic-lMo", kT = {
	name: "theme-classic",
	optionDefaults: {
		className: "fc-classic-yth fc-classic-n5m",
		viewClass: (e) => {
			let t = e.options.headerToolbar || !e.borderlessTop, n = e.options.footerToolbar || !e.borderlessBottom, r = !e.borderlessX;
			return V("fc-classic-Jk3 fc-classic-GAX fc-classic-C1x", t && "fc-classic-ku3", n && "fc-classic-zi1", r && "fc-classic-1Wx");
		},
		toolbarClass: (e) => V("fc-classic-dl1 fc-classic-1sP fc-classic-dNl fc-classic-XpK fc-classic-N2M fc-classic-wwb", e.borderlessX && "fc-classic-Apf"),
		toolbarSectionClass: "fc-classic-yi0 fc-classic-dl1 fc-classic-1sP fc-classic-XpK fc-classic-wwb",
		toolbarTitleClass: "fc-classic-AVD fc-classic-DIS",
		buttonGroupClass: "fc-classic-dl1 fc-classic-1sP fc-classic-XpK",
		buttonClass: (e) => V("fc-classic-dl6 fc-classic-1Wx fc-classic-dl1 fc-classic-1sP fc-classic-XpK fc-classic-sOR fc-classic-lYz fc-classic-vwH fc-classic-9yp fc-classic-RnT fc-classic-cfp fc-classic-Z9U", e.isIconOnly ? "fc-classic-Eaq" : "fc-classic-Apf", e.buttonGroup ? "fc-classic-uk6 fc-classic-Tuc" : "fc-classic-Ig4", e.isSelected ? "fc-classic-rQI fc-classic-Adi" : "fc-classic-vXO fc-classic-bqK fc-classic-aIH fc-classic-nQ5 fc-classic-JWq fc-classic-9Rj fc-classic-5ky", e.isDisabled && "fc-classic-Q3Z fc-classic-3Lc"),
		buttons: {
			prev: { iconContent: () => AT(`${_T} fc-classic-asP`) },
			next: { iconContent: () => AT(`${_T} fc-classic-jmT fc-classic-jY6`) },
			prevYear: { iconContent: () => jT(`${_T} fc-classic-asP`) },
			nextYear: { iconContent: () => jT(`${_T} fc-classic-jmT fc-classic-jY6`) }
		},
		eventColor: "var(--fc-classic-event)",
		eventContrastColor: "var(--fc-classic-event-contrast)",
		eventClass: (e) => V(e.isDragging && "fc-classic-n5m", e.event.url && "fc-classic-JiE", e.isSelected ? V(sT, e.isDragging ? "fc-classic-1kP" : "fc-classic-tkw") : cT, dT),
		backgroundEventColor: "var(--fc-classic-background-event)",
		backgroundEventClass: "fc-classic-hsC fc-classic-jsy fc-classic-DO7",
		backgroundEventTitleClass: (e) => V("fc-classic-MGT fc-classic-L1Y", e.isNarrow ? `fc-classic-KUX ${oT}` : "fc-classic-XJa fc-classic-a3B"),
		listItemEventClass: "fc-classic-XpK",
		listItemEventBeforeClass: "fc-classic-lNM fc-classic-AAA",
		listItemEventInnerClass: "fc-classic-GAX",
		blockEventClass: (e) => V("fc-classic-bCs fc-classic-eYX fc-classic-d0j fc-classic-DO7 fc-classic-YjJ fc-classic-vwH", e.isDragging && !e.isSelected && "fc-classic-iTG", lT),
		blockEventInnerClass: "fc-classic-i9F fc-classic-cfp",
		blockEventTimeClass: "fc-classic-TZ4 fc-classic-pKG fc-classic-1Zl",
		blockEventTitleClass: "fc-classic-TZ4 fc-classic-pKG fc-classic-OLq",
		rowEventClass: (e) => V("fc-classic-Ika fc-classic-JIC", e.isStart && "fc-classic-3J4", e.isEnd && "fc-classic-USt"),
		rowEventBeforeClass: (e) => V(e.isStartResizable && V(e.isSelected ? ST : yT, "fc-classic-11a")),
		rowEventAfterClass: (e) => V(e.isEndResizable && V(e.isSelected ? ST : yT, "fc-classic-bEw")),
		rowEventInnerClass: (e) => V("fc-classic-dl1 fc-classic-1sP fc-classic-XpK", e.isNarrow ? oT : "fc-classic-a3B"),
		rowEventTimeClass: "fc-classic-DIS",
		columnEventClass: (e) => V("fc-classic-1Wx fc-classic-A3h fc-classic-yKG", e.isStart && "fc-classic-ku3 fc-classic-Z7Q", e.isEnd && "fc-classic-Ika fc-classic-zi1 fc-classic-2qh"),
		columnEventBeforeClass: (e) => V(e.isStartResizable && V(e.isSelected ? CT : bT, "fc-classic-YDC")),
		columnEventAfterClass: (e) => V(e.isEndResizable && V(e.isSelected ? CT : bT, "fc-classic-fJL")),
		columnEventInnerClass: (e) => V("fc-classic-dl1", e.isShort ? "fc-classic-KUX fc-classic-1sP fc-classic-XpK fc-classic-NWN" : "fc-classic-oQ2 fc-classic-sgX"),
		columnEventTimeClass: (e) => V(!e.isShort && "fc-classic-166", oT),
		columnEventTitleClass: (e) => V(!e.isShort && "fc-classic-2rx", e.isShort || e.isNarrow ? oT : "fc-classic-a3B"),
		moreLinkClass: `${cT} ${dT}`,
		moreLinkInnerClass: "fc-classic-TZ4 fc-classic-pKG",
		columnMoreLinkClass: `fc-classic-Ika fc-classic-Fvv fc-classic-wsy fc-classic-d0j fc-classic-4MR ${fT} fc-classic-vwH fc-classic-A3h fc-classic-yKG ${lT}`,
		columnMoreLinkInnerClass: (e) => V("fc-classic-KUX", e.isNarrow ? oT : "fc-classic-a3B"),
		dayHeaderAlign: (e) => e.inPopover ? "start" : "center",
		dayHeaderClass: (e) => V("fc-classic-E9P", e.isDisabled && "fc-classic-iYS", e.inPopover ? "fc-classic-zi1 fc-classic-C1x fc-classic-k3f" : V("fc-classic-wsy", e.isMajor ? "fc-classic-C0k" : "fc-classic-C1x")),
		dayHeaderInnerClass: (e) => V("fc-classic-rVY fc-classic-cJ3 fc-classic-dl1 fc-classic-sgX", e.isNarrow ? oT : "fc-classic-9yp"),
		dayHeaderDividerClass: "fc-classic-zi1 fc-classic-C1x",
		dayCellClass: wT,
		dayCellTopClass: (e) => V(e.isNarrow ? "fc-classic-toR" : "fc-classic-84e", "fc-classic-dl1 fc-classic-1sP fc-classic-LMv"),
		dayCellTopInnerClass: (e) => V("fc-classic-rVY fc-classic-TZ4", e.isNarrow ? `fc-classic-cJ3 ${oT}` : "fc-classic-V9v fc-classic-9yp", e.isOther && "fc-classic-taq", e.monthText && "fc-classic-DIS"),
		dayCellInnerClass: (e) => V(e.inPopover && "fc-classic-3N5"),
		popoverClass: "fc-classic-Jk3 fc-classic-GAX fc-classic-wsy fc-classic-C1x fc-classic-tkw fc-classic-aNc fc-classic-n5m",
		popoverCloseClass: `fc-classic-bCs fc-classic-1EY fc-classic-2ik fc-classic-2w8 ${cT} ${dT} fc-classic-Z9U`,
		popoverCloseContent: () => MT("fc-classic-XUJ fc-classic-9yp fc-classic-mAY"),
		dayLaneClass: wT,
		dayLaneInnerClass: (e) => e.isStack ? "fc-classic-gMS" : e.isNarrow ? "fc-classic-148" : "fc-classic-Jzj fc-classic-B3G",
		slotLaneClass: TT,
		listDayHeaderClass: "fc-classic-zi1 fc-classic-C1x fc-classic-SDU fc-classic-nHS fc-classic-dl1 fc-classic-1sP fc-classic-XpK fc-classic-N2M",
		listDayHeaderInnerClass: "fc-classic-Apf fc-classic-dl6 fc-classic-9yp fc-classic-DIS",
		singleMonthClass: (e) => V(e.multiMonthColumns > 1 && "fc-classic-jD5", e.multiMonthColumns === 1 && !e.isLast && "fc-classic-zi1 fc-classic-C1x"),
		singleMonthHeaderClass: (e) => V(e.multiMonthColumns > 1 ? "fc-classic-cM0" : "fc-classic-dl6 fc-classic-zi1 fc-classic-C1x fc-classic-Jk3", "fc-classic-XpK"),
		singleMonthHeaderInnerClass: "fc-classic-1Po fc-classic-DIS",
		tableHeaderClass: "fc-classic-Jk3",
		fillerClass: "fc-classic-wsy fc-classic-C1x fc-classic-lMo",
		dayHeaderRowClass: "fc-classic-wsy fc-classic-C1x",
		dayRowClass: "fc-classic-wsy fc-classic-C1x",
		slotHeaderRowClass: "fc-classic-wsy fc-classic-C1x",
		slotHeaderClass: TT,
		navLinkClass: `fc-classic-Eu0 ${cT} ${uT} ${dT}`,
		inlineWeekNumberClass: (e) => V("fc-classic-1EY fc-classic-n9G fc-classic-rbS fc-classic-C2g fc-classic-KUX fc-classic-HXA fc-classic-m9h fc-classic-k3f", e.isNarrow ? oT : "fc-classic-9yp"),
		nonBusinessHoursClass: "fc-classic-iYS",
		highlightClass: "fc-classic-hLU",
		resourceDayHeaderAlign: "center",
		resourceDayHeaderClass: (e) => V("fc-classic-wsy", e.isMajor ? "fc-classic-C0k" : "fc-classic-C1x"),
		resourceDayHeaderInnerClass: (e) => V("fc-classic-rVY fc-classic-cJ3 fc-classic-dl1 fc-classic-sgX", e.isNarrow ? oT : "fc-classic-9yp"),
		resourceColumnHeaderClass: "fc-classic-wsy fc-classic-C1x fc-classic-E9P",
		resourceColumnHeaderInnerClass: "fc-classic-bvX fc-classic-9yp",
		resourceColumnResizerClass: "fc-classic-1EY fc-classic-AWB fc-classic-4Tv fc-classic-dnf",
		resourceGroupHeaderClass: "fc-classic-wsy fc-classic-C1x fc-classic-k3f",
		resourceGroupHeaderInnerClass: "fc-classic-bvX fc-classic-9yp",
		resourceCellClass: "fc-classic-wsy fc-classic-C1x",
		resourceCellInnerClass: "fc-classic-bvX fc-classic-9yp",
		resourceIndentClass: "fc-classic-Mde fc-classic-kp0 fc-classic-E9P",
		resourceExpanderClass: `fc-classic-bCs ${cT} ${dT}`,
		resourceExpanderContent: (e) => e.isExpanded ? PT(DT) : NT(DT),
		resourceHeaderRowClass: "fc-classic-wsy fc-classic-C1x",
		resourceRowClass: "fc-classic-wsy fc-classic-C1x",
		resourceColumnDividerClass: "fc-classic-1Wx fc-classic-C1x fc-classic-a7i fc-classic-k3f",
		resourceGroupLaneClass: "fc-classic-wsy fc-classic-C1x fc-classic-k3f",
		resourceLaneClass: "fc-classic-wsy fc-classic-C1x",
		resourceLaneBottomClass: (e) => e.options.eventOverlap && "fc-classic-zrJ",
		timelineBottomClass: "fc-classic-zrJ"
	},
	views: {
		dayGrid: {
			...ET,
			dayCellBottomClass: "fc-classic-toR"
		},
		multiMonth: {
			...ET,
			dayCellBottomClass: "fc-classic-toR",
			tableClass: (e) => V(e.multiMonthColumns > 1 && "fc-classic-C1x fc-classic-wsy")
		},
		timeGrid: {
			...ET,
			dayCellBottomClass: "fc-classic-mhE",
			weekNumberHeaderClass: "fc-classic-XpK fc-classic-LMv",
			weekNumberHeaderInnerClass: (e) => V("fc-classic-rVY fc-classic-cJ3", e.isNarrow ? oT : "fc-classic-9yp"),
			allDayHeaderClass: "fc-classic-XpK fc-classic-LMv",
			allDayHeaderInnerClass: (e) => V("fc-classic-rVY fc-classic-2tF fc-classic-2HE", e.isNarrow ? oT : "fc-classic-9yp"),
			allDayDividerClass: "fc-classic-JIC fc-classic-C1x fc-classic-8ub fc-classic-k3f",
			slotHeaderClass: "fc-classic-LMv",
			slotHeaderInnerClass: (e) => V("fc-classic-rVY fc-classic-cJ3", e.isNarrow ? oT : "fc-classic-9yp"),
			slotHeaderDividerClass: "fc-classic-USt fc-classic-C1x",
			nowIndicatorHeaderClass: "fc-classic-rbS fc-classic-a10 fc-classic-XM3 fc-classic-rif fc-classic-jIH fc-classic-0qY",
			nowIndicatorLineClass: "fc-classic-ku3 fc-classic-sYT"
		},
		list: {
			listDayClass: (e) => V(!e.isLast && "fc-classic-zi1 fc-classic-C1x"),
			listItemEventClass: (e) => V("fc-classic-bCs fc-classic-Apf fc-classic-dl6 fc-classic-wwb fc-classic-ku3 fc-classic-C1x", e.isInteractive ? V(gT, uT) : hT),
			listItemEventBeforeClass: "fc-classic-GOm",
			listItemEventInnerClass: "fc-classic-eF2",
			listItemEventTimeClass: "fc-classic-88I fc-classic-yi0 fc-classic-roZ fc-classic-kMV fc-classic-TZ4 fc-classic-pKG fc-classic-IPx fc-classic-9yp",
			listItemEventTitleClass: (e) => V("fc-classic-1El fc-classic-2KU fc-classic-TZ4 fc-classic-pKG fc-classic-9yp", e.event.url && "fc-classic-Ogp"),
			noEventsClass: "fc-classic-k3f fc-classic-dl1 fc-classic-sgX fc-classic-XpK fc-classic-E9P",
			noEventsInnerClass: "fc-classic-OUe fc-classic-jGI fc-classic-P9h"
		},
		timeline: {
			rowEventClass: (e) => V(e.isEnd && "fc-classic-9hC", "fc-classic-XpK"),
			rowEventBeforeClass: (e) => !e.isStart && `${OT} fc-classic-Bda fc-classic-5JV`,
			rowEventAfterClass: (e) => !e.isEnd && `${OT} fc-classic-hhi fc-classic-LaM`,
			rowEventInnerClass: (e) => e.options.eventOverlap ? "fc-classic-2rx" : "fc-classic-End",
			rowEventTimeClass: "fc-classic-oQ2",
			rowEventTitleClass: "fc-classic-oQ2",
			rowMoreLinkClass: `fc-classic-9hC fc-classic-Ika fc-classic-wsy fc-classic-d0j fc-classic-4MR ${fT} fc-classic-vwH`,
			rowMoreLinkInnerClass: "fc-classic-KUX fc-classic-a3B",
			slotHeaderAlign: (e) => e.isTime ? "start" : "center",
			slotHeaderClass: (e) => V("fc-classic-E9P", !e.level && "fc-classic-pKG"),
			slotHeaderInnerClass: (e) => V("fc-classic-fn8 fc-classic-V9v fc-classic-9yp", e.hasNavLink && "fc-classic-Eu0"),
			slotHeaderDividerClass: "fc-classic-zi1 fc-classic-C1x",
			nowIndicatorHeaderClass: "fc-classic-n9G fc-classic-J04 fc-classic-ybF fc-classic-Pqk fc-classic-bLA fc-classic-sYT",
			nowIndicatorLineClass: "fc-classic-3J4 fc-classic-sYT"
		}
	}
};
function AT(e) {
	return D("svg", {
		xmlns: "http://www.w3.org/2000/svg",
		className: e,
		width: "20",
		height: "20",
		viewBox: "0 0 24 24",
		fill: "none",
		stroke: "currentColor",
		strokeWidth: "2",
		strokeLinecap: "round",
		strokeLinejoin: "round",
		children: D("polyline", { points: "15 18 9 12 15 6" })
	});
}
function jT(e) {
	return D("svg", {
		xmlns: "http://www.w3.org/2000/svg",
		className: e,
		width: "20",
		height: "20",
		viewBox: "0 0 24 24",
		fill: "none",
		stroke: "currentColor",
		strokeWidth: "2",
		strokeLinecap: "round",
		strokeLinejoin: "round",
		children: [D("polyline", { points: "11 17 6 12 11 7" }), D("polyline", { points: "18 17 13 12 18 7" })]
	});
}
function MT(e) {
	return D("svg", {
		xmlns: "http://www.w3.org/2000/svg",
		className: e,
		width: "20",
		height: "20",
		viewBox: "0 0 24 24",
		fill: "none",
		stroke: "currentColor",
		strokeWidth: "2",
		strokeLinecap: "round",
		strokeLinejoin: "round",
		children: [D("line", {
			x1: "18",
			y1: "6",
			x2: "6",
			y2: "18"
		}), D("line", {
			x1: "6",
			y1: "6",
			x2: "18",
			y2: "18"
		})]
	});
}
function NT(e) {
	return D("svg", {
		xmlns: "http://www.w3.org/2000/svg",
		className: e,
		width: "20",
		height: "20",
		viewBox: "0 0 24 24",
		fill: "none",
		stroke: "currentColor",
		strokeWidth: "2",
		strokeLinecap: "round",
		strokeLinejoin: "round",
		children: [
			D("rect", {
				x: "3",
				y: "3",
				width: "18",
				height: "18",
				rx: "2",
				ry: "2"
			}),
			D("line", {
				x1: "12",
				y1: "8",
				x2: "12",
				y2: "16"
			}),
			D("line", {
				x1: "8",
				y1: "12",
				x2: "16",
				y2: "12"
			})
		]
	});
}
function PT(e) {
	return D("svg", {
		xmlns: "http://www.w3.org/2000/svg",
		className: e,
		width: "20",
		height: "20",
		viewBox: "0 0 24 24",
		fill: "none",
		stroke: "currentColor",
		strokeWidth: "2",
		strokeLinecap: "round",
		strokeLinejoin: "round",
		children: [D("rect", {
			x: "3",
			y: "3",
			width: "18",
			height: "18",
			rx: "2",
			ry: "2"
		}), D("line", {
			x1: "8",
			y1: "12",
			x2: "16",
			y2: "12"
		})]
	});
}
// ../../../../../localhost/Pushword/packages/newsletter/node_modules/fullcalendar/chunks/4cb239c3.js
var FT = {
	code: "fr",
	week: {
		dow: 1,
		doy: 4
	},
	prevText: "Précédent",
	nextText: "Suivant",
	todayText: "Aujourd'hui",
	yearText: "Année",
	monthText: "Mois",
	weekTextLong: "Semaine",
	weekTextShort: "Sem.",
	dayText: "Jour",
	listText: "Planning",
	allDayText: "Toute la\njournée",
	moreLinkText: "en plus",
	noEventsText: "Aucun évènement à afficher"
};
// ../../../../../localhost/Pushword/packages/newsletter/node_modules/fullcalendar/chunks/8acff3e8.js
function IT(e) {
	return e === "Tag" || e === "Monat" ? "r" : e === "Jahr" ? "s" : "";
}
var LT = {
	Tag: "Tagesansicht",
	Woche: "Wochenansicht",
	Monat: "Monatsansicht",
	Jahr: "Jahresansicht"
}, RT = {
	code: "de",
	week: {
		dow: 1,
		doy: 4
	},
	prevText: "Zurück",
	nextText: "Vor",
	todayText: "Heute",
	yearText: "Jahr",
	monthText: "Monat",
	weekTextLong: "Woche",
	weekTextShort: "KW",
	dayText: "Tag",
	listText: "Terminübersicht",
	allDayText: "Ganztägig",
	moreLinkText(e) {
		return "+ weitere " + e;
	},
	noEventsText: "Keine Ereignisse anzuzeigen",
	prevHint(e) {
		return `Vorherige${IT(e)} ${e}`;
	},
	nextHint(e) {
		return `Nächste${IT(e)} ${e}`;
	},
	todayHint(e) {
		return e === "Tag" ? "Heute" : `Diese${IT(e)} ${e}`;
	},
	viewHint(e) {
		return LT[e] || e;
	},
	navLinkHint: "Gehe zu $0",
	moreLinkHint(e) {
		return "Zeige " + (e === 1 ? "ein weiteres Ereignis" : e + " weitere Ereignisse");
	},
	closeHint: "Schließen",
	eventsHint: "Ereignisse"
}, zT = {
	code: "it",
	week: {
		dow: 1,
		doy: 4
	},
	prevText: "Prec",
	nextText: "Succ",
	todayText: "Oggi",
	yearText: "Anno",
	monthText: "Mese",
	weekTextLong: "Settimana",
	weekTextShort: "Sm",
	dayText: "Giorno",
	listText: "Agenda",
	allDayText: "Tutto\nil giorno",
	moreLinkText(e) {
		return "+altri " + e;
	},
	noEventsText: "Non ci sono eventi da visualizzare"
}, BT = {
	code: "nl",
	week: {
		dow: 1,
		doy: 4
	},
	prevText: "Vorige",
	nextText: "Volgende",
	todayText: "Vandaag",
	yearText: "Jaar",
	monthText: "Maand",
	weekTextLong: "Week",
	dayText: "Dag",
	listText: "Lijst",
	allDayText: "Hele dag",
	moreLinkText: "extra",
	noEventsText: "Geen evenementen om te laten zien"
};
// ../../../../../localhost/Pushword/packages/newsletter/node_modules/temporal-polyfill/chunks/classApi-basic.js
function VT(e) {
	let t = ki(e).toLowerCase();
	return t === "iso8601" ? void 0 : t === "gregory" ? 0 : void O(Wt(e, "temporal-polyfill/full"));
}
function HT(e = Dn) {
	return VT(e);
}
var UT = /*@__PURE__*/ new WeakMap(), WT = /*@__PURE__*/ Mu(ku, class {
	constructor(e, t, n = void 0) {
		let r = ba(Pi(e)), i = Ic(Mc(t)), a = HT(n);
		YT(this, ja(r, i, a));
	}
	static from(e, t = void 0) {
		return GT(JT(e, t));
	}
	static compare(e, t) {
		return ds(JT(e), JT(t));
	}
	get calendarId() {
		return An($(this).calendar);
	}
	get timeZoneId() {
		return $(this).timeZone.id;
	}
	get epochMilliseconds() {
		return Ir($(this).epochNanoseconds);
	}
	get epochNanoseconds() {
		return $(this).epochNanoseconds;
	}
	get offset() {
		return Cc(ro($(this)).offsetNanoseconds);
	}
	get offsetNanoseconds() {
		return ro($(this)).offsetNanoseconds;
	}
	get hoursInDay() {
		return Ia($(this));
	}
	with(e, t = void 0) {
		return GT($u($(this), bE(e), t));
	}
	withCalendar(e) {
		return GT({
			...$(this),
			calendar: GE(e)
		});
	}
	withTimeZone(e) {
		return GT({
			...$(this),
			timeZone: Ic(XT(e))
		});
	}
	withPlainTime(e = void 0) {
		return GT(yu($(this), DE(e)));
	}
	add(e, t = void 0) {
		return GT(uo($(this), ZE(e), I(t)));
	}
	subtract(e, t = void 0) {
		return GT(uo($(this), $o(ZE(e)), I(t)));
	}
	until(e, t = void 0) {
		return JE(Hu(0, $(this), JT(e), t));
	}
	since(e, t = void 0) {
		return JE(Hu(1, $(this), JT(e), t));
	}
	round(e) {
		let t = $(this), [n, r, i] = fa(e);
		return GT(Fa(t, n, r, i));
	}
	startOfDay() {
		return GT(La($(this)));
	}
	equals(e) {
		return _s($(this), JT(e));
	}
	toInstant() {
		return $T(Vl($(this)));
	}
	toPlainDateTime() {
		return jE(Hl($(this)));
	}
	toPlainDate() {
		return RE(Ul($(this)));
	}
	toPlainTime() {
		return CE(Wl($(this)));
	}
	toLocaleString(e = void 0, t = {}) {
		let n = $(this), r = new Ys(e, tu(pu(t), To(n)));
		return nu(r, n), r.format(Ir(n.epochNanoseconds));
	}
	toString(e = void 0) {
		return ac($(this), e);
	}
	toJSON() {
		return ac($(this));
	}
	getTimeZoneTransition(e) {
		let t = $(this), n = t.timeZone.C(t.epochNanoseconds, bu(e));
		return n === void 0 ? null : GT({
			...t,
			epochNanoseconds: n
		});
	}
	valueOf() {
		return Fu();
	}
}, KT, Lu, Bu, Zn);
function GT(e) {
	return YT(Object.create(WT.prototype), e);
}
function $(e) {
	return qT(e) || Pu();
}
function KT(e) {
	let t = $(e);
	return {
		...ro(t),
		calendar: t.calendar
	};
}
function qT(e) {
	return UT.get(e);
}
function JT(e, t) {
	if (Jt(e)) {
		let n = qT(e);
		return n ? (Rs(t), n) : Wc(XT, UE(e), e, t);
	}
	return el(e, VT, t);
}
function YT(e, t) {
	return UT.set(e, t), Nu(e), e;
}
function XT(e) {
	if (Jt(e)) {
		let t = qT(e);
		return t || k(Gt(e)), t.timeZone.id;
	}
	return ((e) => Nc(cl(ki(e))))(e);
}
var ZT = /*@__PURE__*/ new WeakMap(), QT = /*@__PURE__*/ Mu(Au, class {
	constructor(e) {
		let t = ba(Pi(e));
		iE(this, Aa(t));
	}
	static from(e) {
		return $T(nE(e));
	}
	static fromEpochMilliseconds(e) {
		return $T(Ql(e));
	}
	static fromEpochNanoseconds(e) {
		return $T($l(e));
	}
	static compare(e, t) {
		return ds(nE(e), nE(t));
	}
	get epochMilliseconds() {
		return Ir(eE(this).epochNanoseconds);
	}
	get epochNanoseconds() {
		return eE(this).epochNanoseconds;
	}
	add(e) {
		return $T(Aa(mo(eE(this).epochNanoseconds, ZE(e))));
	}
	subtract(e) {
		return $T(Aa(mo(eE(this).epochNanoseconds, $o(ZE(e)))));
	}
	until(e, t = void 0) {
		return JE(Vu(0, eE(this), nE(e), t));
	}
	since(e, t = void 0) {
		return JE(Vu(1, eE(this), nE(e), t));
	}
	round(e) {
		let t = eE(this), [n, r, i] = fa(e, 5, 1);
		return $T(Aa($a(t.epochNanoseconds, no(n, r), i)));
	}
	equals(e) {
		return gs(eE(this), nE(e));
	}
	toZonedDateTimeISO(e) {
		return GT(Bl(eE(this), Ic(XT(e))));
	}
	toLocaleString(e = void 0, t = {}) {
		let n = eE(this);
		return new Ys(e, fu(t)).format(Ir(n.epochNanoseconds));
	}
	toString(e = void 0) {
		return rc(XT, eE(this), e);
	}
	toJSON() {
		return rc(XT, eE(this));
	}
	valueOf() {
		return Fu();
	}
});
function $T(e) {
	return iE(Object.create(QT.prototype), e);
}
function eE(e) {
	return tE(e) || Pu();
}
function tE(e) {
	return ZT.get(e);
}
function nE(e) {
	if (Jt(e)) {
		let t = tE(e);
		if (t) return t;
		let n = qT(e);
		if (n) return Aa(n.epochNanoseconds);
	}
	return Qc(e);
}
var { toTemporalInstant: rE } = { toTemporalInstant() {
	let e = Date.prototype.valueOf.call(this);
	return $T(Aa(BigInt(ji(e)) * Gn));
} };
function iE(e, t) {
	return ZT.set(e, t), Nu(e), e;
}
var aE = /*@__PURE__*/ new WeakMap(), oE = /*@__PURE__*/ Mu(Tu, class {
	constructor(e, t, n = void 0, r) {
		let i = At(e), a = At(t), o = HT(n), s = _a(ai({
			year: At(r ?? 1972),
			month: i,
			day: a
		}));
		dE(this, P(s, o));
	}
	static from(e, t = void 0) {
		return sE(uE(e, t));
	}
	get calendarId() {
		return An(cE(this).calendar);
	}
	with(e, t = void 0) {
		return sE(Zu(cE(this), bE(e), t));
	}
	equals(e) {
		return xs(cE(this), uE(e));
	}
	toPlainDate(e) {
		return RE(Jl(cE(this).calendar, this, e));
	}
	toLocaleString(e = void 0, t = {}) {
		let n = cE(this), r = new Ys(e, eu(vu(t)));
		return nu(r, n, 1), r.format(Br(n));
	}
	toString(e = void 0) {
		return dc(cE(this), e);
	}
	toJSON() {
		return dc(cE(this));
	}
	valueOf() {
		return Fu();
	}
}, cE, Ru);
function sE(e) {
	return dE(Object.create(oE.prototype), e);
}
function cE(e) {
	return lE(e) || Pu();
}
function lE(e) {
	return aE.get(e);
}
function uE(e, t) {
	if (Jt(e)) {
		let n = lE(e);
		if (n) return I(t), n;
		let r = WE(e);
		return Jc(r === void 0 ? void 0 : r, r === void 0, e, t);
	}
	let n = il(e, VT);
	return I(t), n;
}
function dE(e, t) {
	return aE.set(e, t), Nu(e), e;
}
var fE = /*@__PURE__*/ new WeakMap(), pE = /*@__PURE__*/ Mu(wu, class {
	constructor(e, t, n = void 0, r) {
		let i = At(e), a = At(t), o = HT(n), s = ga(ai({
			year: i,
			month: a,
			day: At(r ?? 1)
		}));
		vE(this, P(s, o));
	}
	static from(e, t = void 0) {
		return mE(_E(e, t));
	}
	static compare(e, t) {
		return ms(_E(e), _E(t));
	}
	get calendarId() {
		return An(hE(this).calendar);
	}
	with(e, t = void 0) {
		return mE(Xu(hE(this), bE(e), t));
	}
	add(e, t = void 0) {
		let n = hE(this);
		return mE(P(lo(n.calendar, n, ZE(e), I(t)), n.calendar));
	}
	subtract(e, t = void 0) {
		let n = hE(this);
		return mE(P(lo(n.calendar, n, $o(ZE(e)), I(t)), n.calendar));
	}
	until(e, t = void 0) {
		return JE(Gu(0, hE(this), _E(e), t));
	}
	since(e, t = void 0) {
		return JE(Gu(1, hE(this), _E(e), t));
	}
	equals(e) {
		return bs(hE(this), _E(e));
	}
	toPlainDate(e) {
		return RE(ql(hE(this).calendar, this, e));
	}
	toLocaleString(e = void 0, t = {}) {
		let n = hE(this), r = new Ys(e, eu(_u(t)));
		return nu(r, n, 1), r.format(Br(n));
	}
	toString(e = void 0) {
		return uc(hE(this), e);
	}
	toJSON() {
		return uc(hE(this));
	}
	valueOf() {
		return Fu();
	}
}, hE, Iu, zu);
function mE(e) {
	return vE(Object.create(pE.prototype), e);
}
function hE(e) {
	return gE(e) || Pu();
}
function gE(e) {
	return fE.get(e);
}
function _E(e, t) {
	if (Jt(e)) {
		let n = gE(e);
		return n ? (I(t), n) : qc(UE(e), e, t);
	}
	let n = rl(e, VT);
	return I(t), n;
}
function vE(e, t) {
	return fE.set(e, t), Nu(e), e;
}
function yE(e) {
	if (!Jt(e)) return;
	let t = tE(e);
	return t ? [Au, t] : (t = qT(e), t ? [ku, t] : (t = NE(e), t ? [Du, t] : (t = BE(e), t ? [Eu, t] : (t = TE(e), t ? [Ou, t] : (t = gE(e), t ? [wu, t] : (t = lE(e), t ? [Tu, t] : (t = XE(e), t ? [ju, t] : void 0)))))));
}
function bE(e) {
	return (yE(e) || e.calendar !== void 0 || e.timeZone !== void 0) && k("Invalid bag"), e;
}
var xE = /*@__PURE__*/ new WeakMap(), SE = /*@__PURE__*/ Mu(Ou, class {
	constructor(e = 0, t = 0, n = 0, r = 0, i = 0, a = 0) {
		let o = Tr(tn(At, {
			hour: e,
			minute: t,
			second: n,
			millisecond: r,
			microsecond: i,
			nanosecond: a
		}));
		OE(this, Na(o));
	}
	static from(e, t = void 0) {
		return CE(EE(e, t));
	}
	static compare(e, t) {
		return hs(EE(e), EE(t));
	}
	with(e, t = void 0) {
		return CE(Qu(wE(this), bE(e), t));
	}
	add(e) {
		return CE(po(wE(this), ZE(e))[0]);
	}
	subtract(e) {
		return CE(po(wE(this), $o(ZE(e)))[0]);
	}
	until(e, t = void 0) {
		return JE(Ku(0, wE(this), EE(e), t));
	}
	since(e, t = void 0) {
		return JE(Ku(1, wE(this), EE(e), t));
	}
	round(e) {
		let t = wE(this), [n, r, i] = fa(e, 5);
		return CE(Ua(t, n, r, i)[0]);
	}
	equals(e) {
		return Ss(wE(this), EE(e));
	}
	toLocaleString(e = void 0, t = {}) {
		let n = wE(this);
		return new Ys(e, eu(gu(t))).format(kr(n));
	}
	toString(e = void 0) {
		return pc(wE(this), e);
	}
	toJSON() {
		return pc(wE(this));
	}
	valueOf() {
		return Fu();
	}
}, wE, Zn);
function CE(e) {
	return OE(Object.create(SE.prototype), e);
}
function wE(e) {
	return TE(e) || Pu();
}
function TE(e) {
	return xE.get(e);
}
function EE(e, t) {
	if (Jt(e)) {
		let n = TE(e);
		if (n) return I(t), n;
		let r = NE(e);
		if (r) return I(t), Na(r);
		let i = qT(e);
		return i ? (I(t), Wl(i)) : Yc(e, t);
	}
	let n = al(e);
	return I(t), n;
}
function DE(e) {
	return e === void 0 ? void 0 : EE(e);
}
function OE(e, t) {
	return xE.set(e, t), Nu(e), e;
}
var kE = /*@__PURE__*/ new WeakMap(), AE = /*@__PURE__*/ Mu(Du, class {
	constructor(e, t, n, r = 0, i = 0, a = 0, o = 0, s = 0, c = 0, l = void 0) {
		let u = va(ii(tn(At, {
			year: e,
			month: t,
			day: n,
			hour: r,
			minute: i,
			second: a,
			millisecond: o,
			microsecond: s,
			nanosecond: c
		}))), d = HT(l);
		FE(this, Ma(u, d));
	}
	static from(e, t = void 0) {
		return jE(PE(e, t));
	}
	static compare(e, t) {
		return ps(PE(e), PE(t));
	}
	get calendarId() {
		return An(ME(this).calendar);
	}
	with(e, t = void 0) {
		return jE(Yu(ME(this), bE(e), t));
	}
	withCalendar(e) {
		return jE(Ma(ME(this), GE(e)));
	}
	withPlainTime(e = void 0) {
		let t = ME(this);
		return jE(Hs(t, DE(e), t.calendar));
	}
	add(e, t = void 0) {
		let n = ME(this);
		return jE(Ma(fo(n.calendar, n, ZE(e), I(t)), n.calendar));
	}
	subtract(e, t = void 0) {
		let n = ME(this);
		return jE(Ma(fo(n.calendar, n, $o(ZE(e)), I(t)), n.calendar));
	}
	until(e, t = void 0) {
		return JE(Uu(0, ME(this), PE(e), t));
	}
	since(e, t = void 0) {
		return JE(Uu(1, ME(this), PE(e), t));
	}
	round(e) {
		let t = ME(this), [n, r, i] = fa(e);
		return jE(Ma(Ha(t, n, r, i), t.calendar));
	}
	equals(e) {
		return vs(ME(this), PE(e));
	}
	toZonedDateTime(e, t = void 0) {
		return GT(Gl(ME(this), Ic(XT(e)), zs(t)));
	}
	toPlainDate() {
		let e = ME(this);
		return RE(P(e, e.calendar));
	}
	toPlainTime() {
		return CE(Na(ME(this)));
	}
	toLocaleString(e = void 0, t = {}) {
		let n = ME(this), r = new Ys(e, eu(mu(t)));
		return nu(r, n), r.format(Rr(n));
	}
	toString(e = void 0) {
		return sc(ME(this), e);
	}
	toJSON() {
		return sc(ME(this));
	}
	valueOf() {
		return Fu();
	}
}, ME, Lu, Bu, Zn);
function jE(e) {
	return FE(Object.create(AE.prototype), e);
}
function ME(e) {
	return NE(e) || Pu();
}
function NE(e) {
	return kE.get(e);
}
function PE(e, t) {
	if (Jt(e)) {
		let n = NE(e);
		if (n) return I(t), n;
		let r = BE(e);
		if (r) return I(t), Ma(N(r, wr), r.calendar);
		let i = qT(e);
		return i ? (I(t), Hl(i)) : Gc(UE(e), e, t);
	}
	let n = tl(e, VT);
	return I(t), n;
}
function FE(e, t) {
	return kE.set(e, t), Nu(e), e;
}
var IE = /*@__PURE__*/ new WeakMap(), LE = /*@__PURE__*/ Mu(Eu, class {
	constructor(e, t, n, r = void 0) {
		let i = _a(ai(tn(At, {
			year: e,
			month: t,
			day: n
		}))), a = HT(r);
		HE(this, P(i, a));
	}
	static from(e, t = void 0) {
		return RE(VE(e, t));
	}
	static compare(e, t) {
		return ms(VE(e), VE(t));
	}
	get calendarId() {
		return An(zE(this).calendar);
	}
	with(e, t = void 0) {
		return RE(Ju(zE(this), bE(e), t));
	}
	withCalendar(e) {
		return RE(P(zE(this), GE(e)));
	}
	add(e, t = void 0) {
		let n = zE(this);
		return RE(P(ho(n.calendar, n, ZE(e), I(t)), n.calendar));
	}
	subtract(e, t = void 0) {
		let n = zE(this);
		return RE(P(ho(n.calendar, n, $o(ZE(e)), I(t)), n.calendar));
	}
	until(e, t = void 0) {
		return JE(Wu(0, zE(this), VE(e), t));
	}
	since(e, t = void 0) {
		return JE(Wu(1, zE(this), VE(e), t));
	}
	equals(e) {
		return ys(zE(this), VE(e));
	}
	toZonedDateTime(e) {
		let t = Jt(e) ? {
			timeZone: e.timeZone,
			plainTime: e.plainTime
		} : { timeZone: e }, n = zE(this), r = XT(t.timeZone), i = t.plainTime, a = i === void 0 ? void 0 : EE(i);
		return GT(Kl(n, Ic(r), a));
	}
	toPlainDateTime(e = void 0) {
		let t = zE(this);
		return jE(Hs(t, DE(e), t.calendar));
	}
	toPlainYearMonth() {
		return mE(Xl(zE(this).calendar, this));
	}
	toPlainMonthDay() {
		return sE(Yl(zE(this).calendar, this));
	}
	toLocaleString(e = void 0, t = {}) {
		let n = zE(this), r = new Ys(e, eu(hu(t)));
		return nu(r, n), r.format(Br(n));
	}
	toString(e = void 0) {
		return lc(zE(this), e);
	}
	toJSON() {
		return lc(zE(this));
	}
	valueOf() {
		return Fu();
	}
}, zE, Lu, Bu);
function RE(e) {
	return HE(Object.create(LE.prototype), e);
}
function zE(e) {
	return BE(e) || Pu();
}
function BE(e) {
	return IE.get(e);
}
function VE(e, t) {
	if (Jt(e)) {
		let n = BE(e);
		if (n) return I(t), n;
		let r = NE(e);
		if (r) return I(t), P(r, r.calendar);
		let i = qT(e);
		return i ? (I(t), Ul(i)) : Kc(UE(e), e, t);
	}
	let n = nl(e, VT);
	return I(t), n;
}
function HE(e, t) {
	return IE.set(e, t), Nu(e), e;
}
function UE(e) {
	let t = WE(e);
	return t === void 0 ? void 0 : t;
}
function WE(e) {
	let { calendar: t } = e;
	if (t !== void 0) return GE(t);
}
function GE(e) {
	if (Jt(e)) {
		let t = BE(e) || NE(e) || qT(e) || lE(e) || gE(e);
		return t || k(Ut(e)), t.calendar;
	}
	return ((e) => VT(sl(ki(e))))(e);
}
var KE = /*@__PURE__*/ new WeakMap(), qE = /*@__PURE__*/ Mu(ju, class {
	constructor(e = 0, t = 0, n = 0, r = 0, i = 0, a = 0, o = 0, s = 0, c = 0, l = 0) {
		let u = ts(tn(Fi, {
			years: e,
			months: t,
			weeks: n,
			days: r,
			hours: i,
			minutes: a,
			seconds: o,
			milliseconds: s,
			microseconds: c,
			nanoseconds: l
		}));
		$E(this, Pa(u));
	}
	static from(e) {
		return JE(ZE(e));
	}
	static compare(e, t, n = void 0) {
		return fs(QE, ZE(e), ZE(t), n);
	}
	get sign() {
		return YE(this).sign;
	}
	get blank() {
		return !YE(this).sign;
	}
	with(e) {
		return JE(Il(YE(this), e));
	}
	negated() {
		return JE(Qo(YE(this)));
	}
	abs() {
		return JE(Zo(YE(this)));
	}
	add(e) {
		return JE(Yo(0, YE(this), ZE(e)));
	}
	subtract(e) {
		return JE(Yo(1, YE(this), ZE(e)));
	}
	round(e) {
		return JE(Xo(QE, YE(this), e));
	}
	total(e) {
		return Rl(QE, YE(this), e);
	}
	toLocaleString(e = void 0, t) {
		let n = YE(this);
		return Intl.DurationFormat ? new Intl.DurationFormat(e, t).format(n) : mc(n, t);
	}
	toString(e = void 0) {
		return mc(YE(this), e);
	}
	toJSON() {
		return mc(YE(this));
	}
	valueOf() {
		return Fu();
	}
}, YE, bi);
function JE(e) {
	return $E(Object.create(qE.prototype), e);
}
function YE(e) {
	return XE(e) || Pu();
}
function XE(e) {
	return KE.get(e);
}
function ZE(e) {
	return Jt(e) ? XE(e) || Xc(e) : ol(e);
}
function QE(e) {
	if (e !== void 0) {
		if (Jt(e)) {
			let t = qT(e);
			if (t) return t;
			let n = BE(e);
			if (n) return n;
			let r = NE(e);
			return r ? P(r, r.calendar) : Uc(XT, UE(e), e);
		}
		return $c(e, VT);
	}
}
function $E(e, t) {
	return KE.set(e, t), Nu(e), e;
}
var eD = /*@__PURE__*/ Object.defineProperties({}, {
	...en("Temporal.Now"),
	...$t({
		timeZoneId() {
			return Cu();
		},
		instant() {
			return $T(Aa(Su()));
		},
		zonedDateTimeISO(e = Cu()) {
			let t = Ic(XT(e));
			return GT(ja(Su(), t));
		},
		plainDateTimeISO(e = Cu()) {
			return jE(Ma(xu(Ic(XT(e)))));
		},
		plainDateISO(e = Cu()) {
			return RE(P(xu(Ic(XT(e)))));
		},
		plainTimeISO(e = Cu()) {
			return CE(Na(xu(Ic(XT(e)))));
		}
	})
}), tD = /*@__PURE__*/ Object.defineProperties({}, {
	...en("Temporal"),
	...$t({
		PlainYearMonth: pE,
		PlainMonthDay: oE,
		PlainDate: LE,
		PlainTime: SE,
		PlainDateTime: AE,
		ZonedDateTime: WT,
		Instant: QT,
		Duration: qE,
		Now: eD
	})
}), nD = gt || tD;
gt && Date.prototype.toTemporalInstant;
// src/Resources/assets/calendar-move.js
function rD(e, t, n) {
	if (!e.allDay) return e.start.toISOString().replace(/\.\d{3}Z$/, "+00:00");
	let r = nD.Instant.from(t.start.toISOString()).toZonedDateTimeISO(n), i = nD.PlainDate.from(e.startStr.slice(0, 10));
	return r.with({
		year: i.year,
		month: i.month,
		day: i.day
	}).toString({
		timeZoneName: "never",
		fractionalSecondDigits: 0
	});
}
async function iD(e, t, n, r = fetch) {
	let i = await r(e.event.extendedProps.moveUrl, {
		method: "POST",
		headers: { "Content-Type": "application/x-www-form-urlencoded" },
		body: new URLSearchParams({
			_token: n,
			version: String(e.oldEvent.extendedProps.version),
			date: rD(e.event, e.oldEvent, t)
		})
	});
	if (!i.ok) throw Error(i.status === 409 ? "conflict" : "error");
	let a = await i.json();
	e.event.setExtendedProp("version", a.version), e.event.setStart(a.date, { allDay: !1 });
}
// src/Resources/assets/calendar.js
var aD = document.querySelector("[data-newsletter-calendar]");
if (aD) {
	let e = aD.dataset, t = document.querySelector("[data-calendar-feedback]"), n = JSON.parse(e.events), r = [
		"#365d88",
		"#645388",
		"#386b59",
		"#86563c",
		"#786420"
	], i = (e) => {
		let t = 0;
		for (let n of e || "") t = t * 31 + n.codePointAt(0) >>> 0;
		return r[t % r.length];
	};
	for (let e of n) e.color = i(e.extendedProps.audience);
	let a = !1, o = (t) => {
		let n = new URL(e.createUrl, window.location.href);
		n.searchParams.set("scheduledAt", t), window.location.assign(n.href);
	}, s = new aT(aD, {
		plugins: [kT],
		locales: [
			FT,
			RT,
			zT,
			BT
		],
		locale: e.locale.replace("_", "-"),
		timeZone: e.timezone,
		initialView: "dayGridMonth",
		monthText: e.month,
		weekTextLong: e.week,
		yearText: e.year,
		listText: e.agenda,
		height: "auto",
		eventInteractive: !0,
		eventDurationEditable: !1,
		eventAllow: () => !a,
		dayMaxEvents: 3,
		nowIndicator: !0,
		noEventsContent: e.empty,
		dayCellClass: "pw-calendar-day",
		navLinks: !0,
		navLinkHint: (t) => e.createHint.replace("%date%", t),
		navLinkDayClick: (e) => o(s.formatIso(e, !0)),
		dateClick: ({ dateStr: e }) => o(e),
		events: n,
		eventDidMount({ event: e, el: t }) {
			let { audience: n, source: r, state: i } = e.extendedProps, a = [
				e.title,
				n,
				i,
				r
			].filter(Boolean).join(" · ");
			t.title = a, t.setAttribute("aria-label", a);
		},
		eventContent({ event: e, timeText: t, view: n }) {
			let r = document.createElement("span");
			r.className = "pw-calendar-event" + (n.type.startsWith("list") ? " pw-calendar-event-agenda" : "");
			let i = document.createElement("strong");
			i.textContent = [t, e.title].filter(Boolean).join(" ");
			let a = document.createElement("small");
			return a.textContent = [
				e.extendedProps.state,
				e.extendedProps.audience,
				e.extendedProps.source
			].filter(Boolean).join(" · "), r.append(i, a), { domNodes: [r] };
		},
		async eventDrop(n) {
			a = !0, aD.setAttribute("aria-busy", "true");
			try {
				await iD(n, e.timezone, e.token), t.textContent = e.saved, t.className = "pw-calendar-feedback alert alert-success";
			} catch (r) {
				n.revert(), t.textContent = r.message === "conflict" ? e.conflict : e.error, t.className = "pw-calendar-feedback alert alert-danger";
			} finally {
				a = !1, aD.removeAttribute("aria-busy"), t.hidden = !1;
			}
		}
	}), c = () => {
		let e = aD.clientWidth < 650;
		s.setOption("headerToolbar", e ? {
			start: "prev,next today",
			end: "title"
		} : {
			start: "prev,next today",
			center: "title",
			end: "dayGridMonth,timeGridWeek,multiMonthYear,listMonth"
		}), s.setOption("footerToolbar", e ? { start: "dayGridMonth,timeGridWeek,multiMonthYear,listMonth" } : !1);
	};
	s.setOption("windowResize", c), c(), s.render();
}
