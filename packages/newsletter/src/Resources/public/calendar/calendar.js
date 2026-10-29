// node_modules/preact/dist/preact.module.js
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
function E(e) {
	if (e.__P && e.__d) {
		var n = e.__v, r = n.__e, i = [], a = [], o = y({}, n);
		o.__v = n.__v + 1, t.vnode && t.vnode(o), de(e.__P, o, n, e.__n, e.__P.namespaceURI, 32 & n.__u ? [r] : null, i, r ?? ee(n), !!(32 & n.__u), a), o.__v = n.__v, o.__.__k[o.__i] = o, pe(i, o, a), n.__e = n.__ = null, o.__e != r && te(o);
	}
}
function te(e) {
	if ((e = e.__) != null && e.__c != null) return e.__e = e.__c.base = null, e.__k.some(function(t) {
		if (t != null && t.__e != null) return e.__e = e.__c.base = t.__e;
	}), te(e);
}
function D(e) {
	(!e.__d && (e.__d = !0) && r.push(e) && !ne.__r++ || i != t.debounceRendering) && ((i = t.debounceRendering) || a)(ne);
}
function ne() {
	try {
		for (var e, t = 1; r.length;) r.length > t && r.sort(o), e = r.shift(), t = r.length, E(e);
	} finally {
		r.length = ne.__r = 0;
	}
}
function re(e, t, n, r, i, a, o, s, c, l, u) {
	var d, f, p, m, _, v, y = r && r.__k || g, b = t.length;
	for (c = ie(n, t, y, c, b), d = 0; d < b; d++) (p = n.__k[d]) != null && (f = p.__i != -1 && y[p.__i] || h, p.__i = d, v = de(e, p, f, i, a, o, s, c, l, u), m = p.__e, p.ref && f.ref != p.ref && (f.ref && ge(f.ref, null, p), u.push(p.ref, p.__c || m, p)), _ == null && m != null && (_ = m), 4 & p.__u ? (c = ae(p, c, e), f.__e && (f.__e = null)) : typeof p.type == "function" && v !== void 0 ? c = v : m && (c = m.nextSibling), p.__u &= -7);
	return n.__e = _, c;
}
function ie(e, t, n, r, i) {
	var a, o, s, c, l, u = n.length, d = u, f = 0;
	for (e.__k = Array(i), a = 0; a < i; a++) (o = t[a]) != null && typeof o != "boolean" && typeof o != "function" ? (typeof o == "string" || typeof o == "number" || typeof o == "bigint" || o.constructor == String ? o = e.__k[a] = S(null, o, null, null, null) : v(o) ? o = e.__k[a] = S(w, { children: o }, null, null, null) : o.constructor === void 0 && o.__b > 0 ? o = e.__k[a] = S(o.type, o.props, o.key, o.ref ? o.ref : null, o.__v) : e.__k[a] = o, c = a + f, o.__ = e, o.__b = e.__b + 1, s = null, (l = o.__i = se(o, n, c, d)) != -1 && (d--, (s = n[l]) && (s.__u |= 2)), s == null || s.__v == null ? (l == -1 && (i > u ? f-- : i < u && f++), typeof o.type != "function" && (o.__u |= 4)) : l != c && (l == c - 1 ? f-- : l == c + 1 ? f++ : (l > c ? f-- : f++, o.__u |= 4))) : e.__k[a] = null;
	if (d) for (a = 0; a < u; a++) (s = n[a]) != null && !(2 & s.__u) && (s.__e == r && (r = ee(s)), _e(s, s));
	return r;
}
function ae(e, t, n) {
	var r, i;
	if (typeof e.type == "function") {
		for (r = e.__k, i = 0; r && i < r.length; i++) r[i] && (r[i].__ = e, t = ae(r[i], t, n));
		return t;
	}
	e.__e != t && (t && e.type && !t.parentNode && (t = ee(e)), t = n.insertBefore(e.__e, t || null));
	do
		t &&= t.nextSibling;
	while (t != null && t.nodeType == 8);
	return t;
}
function oe(e, t) {
	return t ||= [], e == null || typeof e == "boolean" || (v(e) ? e.some(function(e) {
		oe(e, t);
	}) : t.push(e)), t;
}
function se(e, t, n, r) {
	var i, a, o, s = e.key, c = e.type, l = t[n], u = l != null && !(2 & l.__u);
	if (l === null && s == null || u && s == l.key && c == l.type) return n;
	if (r > +!!u) {
		for (i = n - 1, a = n + 1; i >= 0 || a < t.length;) if ((l = t[o = i >= 0 ? i-- : a++]) != null && !(2 & l.__u) && s == l.key && c == l.type) return o;
	}
	return -1;
}
function ce(e, t, n) {
	t[0] == "-" ? e.setProperty(t, n ?? "") : e[t] = n == null ? "" : typeof n != "number" || _.test(t) ? n : n + "px";
}
function le(e, t, n, r, i) {
	var a, o;
	n: if (t == "style") {
		if (typeof n == "string") e.style.cssText = n;
		else {
			if (typeof r == "string" && (e.style.cssText = r = ""), r) for (t in r) n && t in n || ce(e.style, t, "");
			if (n) for (t in n) r && n[t] == r[t] || ce(e.style, t, n[t]);
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
function ue(e) {
	return function(n) {
		if (this.l) {
			var r = this.l[n.type + e];
			if (n[c] == null) n[c] = d++;
			else if (n[c] < r[l]) return;
			return r(t.event ? t.event(n) : n);
		}
	};
}
function de(e, n, r, i, a, o, s, c, l, u) {
	var d, f, p, m, h, _, x, S, C, E, te, D, ne, ie, ae, oe, se = n.type;
	if (n.constructor !== void 0) return null;
	128 & r.__u && (l = !!(32 & r.__u), o = [c = n.__e = r.__e]), (d = t.__b) && d(n);
	n: if (typeof se == "function") {
		f = s.length;
		try {
			if (C = n.props, E = se.prototype && se.prototype.render, te = (d = se.contextType) && i[d.__c], D = d ? te ? te.props.value : d.__ : i, r.__c ? S = (p = n.__c = r.__c).__ = p.__E : (E ? n.__c = p = new se(C, D) : (n.__c = p = new T(C, D), p.constructor = se, p.render = ve), te && te.sub(p), p.state || (p.state = {}), p.__n = i, m = p.__d = !0, p.__h = [], p._sb = []), E && p.__s == null && (p.__s = p.state), E && se.getDerivedStateFromProps != null && (p.__s == p.state && (p.__s = y({}, p.__s)), y(p.__s, se.getDerivedStateFromProps(C, p.__s))), h = p.props, _ = p.state, p.__v = n, m) E && se.getDerivedStateFromProps == null && p.componentWillMount != null && p.componentWillMount(), E && p.componentDidMount != null && p.__h.push(p.componentDidMount);
			else {
				if (E && se.getDerivedStateFromProps == null && C !== h && p.componentWillReceiveProps != null && p.componentWillReceiveProps(C, D), n.__v == r.__v || !p.__e && p.shouldComponentUpdate != null && !1 === p.shouldComponentUpdate(C, p.__s, D)) {
					n.__v != r.__v && (p.props = C, p.state = p.__s, p.__d = !1), n.__e = r.__e, n.__k = r.__k, n.__k.some(function(e) {
						e && (e.__ = n);
					}), g.push.apply(p.__h, p._sb), p._sb = [], p.__h.length && s.push(p), c = ee(r);
					break n;
				}
				p.componentWillUpdate != null && p.componentWillUpdate(C, p.__s, D), E && p.componentDidUpdate != null && p.__h.push(function() {
					p.componentDidUpdate(h, _, x);
				});
			}
			if (p.context = D, p.props = C, p.__P = e, p.__e = !1, ne = t.__r, ie = 0, E) p.state = p.__s, p.__d = !1, ne && ne(n), d = p.render(p.props, p.state, p.context), g.push.apply(p.__h, p._sb), p._sb = [];
			else do
				p.__d = !1, ne && ne(n), d = p.render(p.props, p.state, p.context), p.state = p.__s;
			while (p.__d && ++ie < 25);
			p.state = p.__s, p.getChildContext != null && (i = y(y({}, i), p.getChildContext())), E && !m && p.getSnapshotBeforeUpdate != null && (x = p.getSnapshotBeforeUpdate(h, _)), ae = d != null && d.type === w && d.key == null ? me(d.props.children) : d, c = re(e, v(ae) ? ae : [ae], n, r, i, a, o, s, c, l, u), p.base = n.__e, n.__u &= -161, p.__h.length && s.push(p), S && (p.__E = p.__ = null);
		} catch (e) {
			if (s.length = f, n.__v = null, l || o != null) {
				if (e.then) {
					for (n.__u |= l ? 160 : 128; c && c.nodeType == 8 && c.nextSibling;) c = c.nextSibling;
					o != null && (o[o.indexOf(c)] = null), n.__e = c;
				} else if (o != null) for (oe = o.length; oe--;) b(o[oe]);
			} else n.__e = r.__e;
			n.__k ??= r.__k || [], e.then || fe(n), t.__e(e, n, r);
		}
	} else o == null && n.__v == r.__v ? (n.__k = r.__k, n.__e = r.__e) : c = n.__e = he(r.__e, n, r, i, a, o, s, l, u);
	return (d = t.diffed) && d(n), 128 & n.__u ? void 0 : c;
}
function fe(e) {
	e && (e.__c && (e.__c.__e = !0), e.__k && e.__k.some(fe));
}
function pe(e, n, r) {
	for (var i = 0; i < r.length; i++) ge(r[i], r[++i], r[++i]);
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
function me(e) {
	return typeof e != "object" || !e || e.__b > 0 ? e : v(e) ? e.map(me) : e.constructor === void 0 ? y({}, e) : null;
}
function he(n, r, i, a, o, s, c, l, u) {
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
		for (d in x) g = x[d], d == "dangerouslySetInnerHTML" ? p = g : d == "children" || d in S || d == "value" && "defaultValue" in S || d == "checked" && "defaultChecked" in S || le(n, d, null, g, o);
		for (d in S) g = S[d], d == "children" ? m = g : d == "dangerouslySetInnerHTML" ? f = g : d == "value" ? _ = g : d == "checked" ? y = g : l && typeof g != "function" || x[d] === g || le(n, d, g, x[d], o);
		if (f) l || p && (f.__html == p.__html || f.__html == n.innerHTML) || (n.innerHTML = f.__html), r.__k = [];
		else if (p && (n.innerHTML = ""), re(r.type == "template" ? n.content : n, v(m) ? m : [m], r, i, a, C == "foreignObject" ? "http://www.w3.org/1999/xhtml" : o, s, c, s ? s[0] : i.__k && ee(i, 0), l, u), s != null) for (d = s.length; d--;) b(s[d]);
		l && C != "textarea" || (d = "value", C == "progress" && _ == null ? n.removeAttribute("value") : _ != null && (_ !== n[d] || C == "progress" && !_ || C == "option" && _ != x[d]) && le(n, d, _, x[d], o), d = "checked", y != null && y != n[d] && le(n, d, y, x[d], o));
	}
	return n;
}
function ge(e, n, r) {
	try {
		if (typeof e == "function") {
			var i = typeof e.__u == "function";
			i && e.__u(), i && n == null || (e.__u = e(n));
		} else e.current = n;
	} catch (e) {
		t.__e(e, r);
	}
}
function _e(e, n, r) {
	var i, a;
	if (t.unmount && t.unmount(e), (i = e.ref) && (i.current && i.current != e.__e || ge(i, null, n)), (i = e.__c) != null) {
		if (i.componentWillUnmount) try {
			i.componentWillUnmount();
		} catch (e) {
			t.__e(e, n);
		}
		i.base = i.__P = i.__n = null;
	}
	if (i = e.__k) for (a = 0; a < i.length; a++) i[a] && _e(i[a], n, r || typeof e.type != "function");
	r || b(e.__e), e.__c = e.__ = e.__e = void 0;
}
function ve(e, t, n) {
	return this.constructor(e, n);
}
function ye(n, r, i) {
	var a, o, s, c;
	r == document && (r = document.documentElement), t.__ && t.__(n, r), o = (a = typeof i == "function") ? null : i && i.__k || r.__k, s = [], c = [], de(r, n = (!a && i || r).__k = x(w, null, [n]), o || h, h, r.namespaceURI, !a && i ? [i] : o ? null : r.firstChild ? e.call(r.childNodes) : null, s, !a && i ? i : o ? o.__e : r.firstChild, a, c), pe(s, n, c), n.props.children = null;
}
function be(e) {
	function t(e) {
		var n, r;
		return this.getChildContext || (n = /* @__PURE__ */ new Set(), (r = {})[t.__c] = this, this.getChildContext = function() {
			return r;
		}, this.componentWillUnmount = function() {
			n = null;
		}, this.shouldComponentUpdate = function(e) {
			this.props.value != e.value && n.forEach(function(e) {
				e.__e = !0, D(e);
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
	typeof e == "function" && (e = e(y({}, n), this.props)), e && y(n, e), e != null && this.__v && (t && this._sb.push(t), D(this));
}, T.prototype.forceUpdate = function(e) {
	this.__v && (this.__e = !0, e && this.__h.push(e), D(this));
}, T.prototype.render = w, r = [], a = typeof Promise == "function" ? Promise.prototype.then.bind(Promise.resolve()) : setTimeout, o = function(e, t) {
	return e.__v.__b - t.__v.__b;
}, ne.__r = 0, s = Math.random().toString(8), c = "__d" + s, l = "__a" + s, u = /(PointerCapture)$|Capture$/i, d = 0, f = ue(!1), p = ue(!0), m = 0;
// node_modules/preact/jsx-runtime/dist/jsxRuntime.module.js
var xe = 0;
Array.isArray;
function O(e, n, r, i, a, o) {
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
		__v: --xe,
		__i: -1,
		__u: 0,
		__source: a,
		__self: o
	};
	if (typeof e == "function" && (s = e.defaultProps)) for (c in s) l[c] === void 0 && (l[c] = s[c]);
	return t.vnode && t.vnode(u), u;
}
// node_modules/preact/hooks/dist/hooks.module.js
var Se, Ce, we, Te = [], Ee = t, De = Ee.__b, Oe = Ee.__r, ke = Ee.diffed, Ae = Ee.__c, je = Ee.unmount, Me = Ee.__;
function Ne() {
	for (var e; e = Te.shift();) {
		var t = e.__H;
		if (e.__P && t) try {
			t.__h.some(Ie), t.__h.some(Le), t.__h = [];
		} catch (n) {
			t.__h = [], Ee.__e(n, e.__v);
		}
	}
}
Ee.__b = function(e) {
	Se = null, De && De(e);
}, Ee.__ = function(e, t) {
	e && t.__k && t.__k.__m && (e.__m = t.__k.__m), Me && Me(e, t);
}, Ee.__r = function(e) {
	Oe && Oe(e);
	var t = (Se = e.__c).__H;
	t && (Ce === Se ? (t.__h = [], Se.__h = [], t.__.some(function(e) {
		e.__N && (e.__ = e.__N), e.u = e.__N = void 0;
	})) : (t.__h.some(Ie), t.__h.some(Le), t.__h = [])), Ce = Se;
}, Ee.diffed = function(e) {
	ke && ke(e);
	var t = e.__c;
	t && t.__H && (t.__H.__h.length && (Te.push(t) !== 1 && we === Ee.requestAnimationFrame || ((we = Ee.requestAnimationFrame) || Fe)(Ne)), t.__H.__.some(function(e) {
		e.u &&= (e.__H = e.u, void 0);
	})), Ce = Se = null;
}, Ee.__c = function(e, t) {
	t.some(function(e) {
		try {
			e.__h.some(Ie), e.__h = e.__h.filter(function(e) {
				return !e.__ || Le(e);
			});
		} catch (n) {
			t.some(function(e) {
				e.__h &&= [];
			}), t = [], Ee.__e(n, e.__v);
		}
	}), Ae && Ae(e, t);
}, Ee.unmount = function(e) {
	je && je(e);
	var t, n = e.__c;
	n && n.__H && (n.__H.__.some(function(e) {
		try {
			Ie(e);
		} catch (e) {
			t = e;
		}
	}), n.__H = void 0, t && Ee.__e(t, n.__v));
};
var Pe = typeof requestAnimationFrame == "function";
function Fe(e) {
	var t, n = function() {
		clearTimeout(r), Pe && cancelAnimationFrame(t), setTimeout(e);
	}, r = setTimeout(n, 35);
	Pe && (t = requestAnimationFrame(n));
}
function Ie(e) {
	var t = Se, n = e.__c;
	typeof n == "function" && (e.__c = void 0, n()), Se = t;
}
function Le(e) {
	var t = Se;
	e.__c = e.__(), Se = t;
}
// node_modules/preact/compat/dist/compat.module.js
function Re(e, t) {
	for (var n in t) e[n] = t[n];
	return e;
}
function ze(e, t) {
	for (var n in e) if (n !== "__source" && !(n in t)) return !0;
	for (var r in t) if (r !== "__source" && e[r] !== t[r]) return !0;
	return !1;
}
function Be(e, t) {
	this.props = e, this.context = t;
}
(Be.prototype = new T()).isPureReactComponent = !0, Be.prototype.shouldComponentUpdate = function(e, t) {
	return ze(this.props, e) || ze(this.state, t);
};
var Ve = t.__b;
t.__b = function(e) {
	e.type && e.type.__f && e.ref && (e.props.ref = e.ref, e.ref = null), Ve && Ve(e);
}, typeof Symbol < "u" && Symbol.for;
var He = t.__e;
t.__e = function(e, t, n, r) {
	if (e.then) {
		for (var i, a = t; a = a.__;) if ((i = a.__c) && i.__c) return t.__e ?? (t.__e = n.__e, t.__k = n.__k || []), i.__c(e, t);
	}
	He(e, t, n, r);
};
var Ue = t.unmount;
function We(e, t, n) {
	return e && (e.__c && e.__c.__H && (e.__c.__H.__.forEach(function(e) {
		typeof e.__c == "function" && e.__c();
	}), e.__c.__H = null), (e = Re({}, e)).__c != null && (e.__c.__P === n && (e.__c.__P = t), e.__c.__e = !0, e.__c = null), e.__k = e.__k && e.__k.map(function(e) {
		return We(e, t, n);
	})), e;
}
function Ge(e, t, n) {
	return e && n && (e.__v = null, e.__k = e.__k && e.__k.map(function(e) {
		return Ge(e, t, n);
	}), e.__c && e.__c.__P === t && (e.__e && n.appendChild(e.__e), e.__c.__e = !0, e.__c.__P = n)), e;
}
function Ke() {
	this.__u = 0, this.o = null, this.__b = null;
}
function qe(e) {
	var t = e.__ && e.__.__c;
	return t && t.__a && t.__a(e);
}
function Je() {
	this.i = null, this.l = null;
}
t.unmount = function(e) {
	var t = e.__c;
	t && (t.__z = !0), t && t.__R && t.__R(), t && 32 & e.__u && (e.type = null), Ue && Ue(e);
}, (Ke.prototype = new T()).__c = function(e, t) {
	var n = t.__c, r = this;
	r.o ??= [], r.o.push(n);
	var i = qe(r.__v), a = !1, o = function() {
		a || r.__z || (a = !0, n.__R = null, i ? i(c) : c());
	};
	n.__R = o;
	var s = n.__P;
	n.__P = null;
	var c = function() {
		if (!--r.__u) {
			if (r.state.__a) {
				var e = r.state.__a;
				r.__v.__k[0] = Ge(e, e.__c.__P, e.__c.__O);
			}
			var t;
			for (r.setState({ __a: r.__b = null }); t = r.o.pop();) t.__P = s, t.forceUpdate();
		}
	};
	r.__u++ || 32 & t.__u || r.setState({ __a: r.__b = r.__v.__k[0] }), e.then(o, o);
}, Ke.prototype.componentWillUnmount = function() {
	this.o = [];
}, Ke.prototype.render = function(e, t) {
	if (this.__b) {
		if (this.__v.__k) {
			var n = document.createElement("div"), r = this.__v.__k[0].__c;
			this.__v.__k[0] = We(this.__b, n, r.__O = r.__P);
		}
		this.__b = null;
	}
	var i = t.__a && x(w, null, e.fallback);
	return i && (i.__u &= -33), [x(w, null, t.__a ? null : e.children), i];
};
var Ye = function(e, t, n) {
	if (++n[1] === n[0] && e.l.delete(t), e.props.revealOrder && (e.props.revealOrder[0] !== "t" || !e.l.size)) for (n = e.i; n;) {
		for (; n.length > 3;) n.pop()();
		if (n[1] < n[0]) break;
		e.i = n = n[2];
	}
};
function Xe(e) {
	return this.getChildContext = function() {
		return e.context;
	}, e.children;
}
function Ze(e) {
	var t = this, n = e.h;
	if (t.componentWillUnmount = function() {
		ye(null, t.v), t.v = null, t.h = null;
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
	ye(x(Xe, { context: t.context }, e.__v), t.v);
}
function Qe(e, t) {
	var n = x(Ze, {
		__v: e,
		h: t
	});
	return n.containerInfo = t, n;
}
(Je.prototype = new T()).__a = function(e) {
	var t = this, n = qe(t.__v), r = t.l.get(e);
	return r[0]++, function(i) {
		var a = function() {
			t.props.revealOrder ? (r.push(i), Ye(t, e, r)) : i();
		};
		n ? n(a) : a();
	};
}, Je.prototype.render = function(e) {
	this.i = null, this.l = /* @__PURE__ */ new Map();
	var t = oe(e.children);
	e.revealOrder && e.revealOrder[0] === "b" && t.reverse();
	for (var n = t.length; n--;) this.l.set(t[n], this.i = [
		1,
		0,
		this.i
	]);
	return e.children;
}, Je.prototype.componentDidUpdate = Je.prototype.componentDidMount = function() {
	var e = this;
	this.l.forEach(function(t, n) {
		Ye(e, n, t);
	});
};
var $e = typeof Symbol < "u" && Symbol.for && Symbol.for("react.element") || 60103, et = /^(?:accent|alignment|arabic|baseline|cap|clip(?!PathU)|color|dominant|fill|flood|font|glyph(?!R)|horiz|image(!S)|letter|lighting|marker(?!H|W|U)|overline|paint|pointer|shape|stop|strikethrough|stroke|text(?!L)|transform|underline|unicode|units|v|vector|vert|word|writing|x(?!C))[A-Z]/, tt = /^on(Ani|Tra|Tou|BeforeInp|Compo)/, nt = /[A-Z0-9]/g, rt = typeof document < "u", it = function(e) {
	return (typeof Symbol < "u" && typeof Symbol() == "symbol" ? /fil|che|rad/ : /fil|che|ra/).test(e);
};
function at(e, t, n) {
	return t.__k ?? (t.textContent = ""), ye(e, t), typeof n == "function" && n(), e ? e.__c : null;
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
var ot = t.event;
t.event = function(e) {
	return ot && (e = ot(e)), e.persist = function() {}, e.isPropagationStopped = function() {
		return this.cancelBubble;
	}, e.isDefaultPrevented = function() {
		return this.defaultPrevented;
	}, e.nativeEvent = e;
};
var st = {
	configurable: !0,
	get: function() {
		return this.class;
	}
}, ct = t.vnode;
t.vnode = function(e) {
	typeof e.type == "string" && function(e) {
		var t = e.props, n = e.type, r = {}, i = n.indexOf("-") == -1;
		for (var a in t) {
			var o = t[a];
			if (!(a === "value" && "defaultValue" in t && o == null || rt && a === "children" && n === "noscript" || a === "class" || a === "className")) {
				var s = a.toLowerCase();
				a === "defaultValue" && "value" in t && t.value == null ? a = "value" : a === "download" && !0 === o ? o = "" : s === "translate" && o === "no" ? o = !1 : s[0] === "o" && s[1] === "n" ? s === "ondoubleclick" ? a = "ondblclick" : s !== "onchange" || n !== "input" && n !== "textarea" || it(t.type) ? s === "onfocus" ? a = "onfocusin" : s === "onblur" ? a = "onfocusout" : tt.test(a) && (a = s) : s = a = "oninput" : i && et.test(a) ? a = a.replace(nt, "-$&").toLowerCase() : o === null && (o = void 0), s === "oninput" && r[a = s] && (a = "oninputCapture"), r[a] = o;
			}
		}
		n == "select" && (r.multiple && Array.isArray(r.value) && (r.value = oe(t.children).forEach(function(e) {
			e.props.selected = r.value.indexOf(e.props.value) != -1;
		})), r.defaultValue != null && (r.value = oe(t.children).forEach(function(e) {
			e.props.selected = r.multiple ? r.defaultValue.indexOf(e.props.value) != -1 : r.defaultValue == e.props.value;
		}))), t.class && !t.className ? (r.class = t.class, Object.defineProperty(r, "className", st)) : t.className && (r.class = r.className = t.className), e.props = r;
	}(e), e.$$typeof = $e, ct && ct(e);
};
var lt = t.__r;
t.__r = function(e) {
	lt && lt(e), e.__c;
};
var ut = t.diffed;
t.diffed = function(e) {
	ut && ut(e);
	var t = e.props, n = e.__e;
	n != null && e.type === "textarea" && "value" in t && t.value !== n.value && (n.value = t.value == null ? "" : t.value);
};
function dt(e) {
	return !!e && e.$$typeof === $e;
}
function ft(e) {
	return !!e.__k && (ye(null, e), !0);
}
var pt = function(e, n) {
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
// node_modules/preact/compat/client.mjs
function mt(e) {
	return {
		render: function(t) {
			at(t, e);
		},
		unmount: function() {
			ft(e);
		}
	};
}
// node_modules/temporal-polyfill/chunks/root.js
var ht = globalThis.Temporal, gt = (e, t) => `Non-positive ${e}: ${t}`, _t = (e, t) => `Non-finite ${e}: ${t}`, vt = (e) => `Cannot convert bigint to ${e}`, yt = "Invalid object", bt = (e, t, n, r) => xt(e, t) + `; must be between ${n}-${r}`, xt = (e, t) => `Invalid ${e}: ${t}`, St = 1e3, Ct = 1e6, wt = 1e9, Tt = 6e10, Et = 36e11;
function Dt(e) {
	return e === void 0 ? Object.create(null) : Pt(e);
}
function Ot(e, t = "number") {
	if (typeof e == "bigint") throw TypeError(vt(t));
	if (e = Number(e), !Number.isFinite(e)) throw RangeError(_t(t, e));
	return e;
}
function kt(e, t) {
	return Math.trunc(Ot(e, t)) || 0;
}
function At(e, t) {
	return jt(kt(e, t), t);
}
function jt(e, t = "number") {
	if (e <= 0) throw RangeError(gt(t, e));
	return e;
}
function Mt(e, t, n) {
	return Math.min(Math.max(e, t), n);
}
function Nt(e) {
	return e !== null && (typeof e == "object" || typeof e == "function");
}
function Pt(e) {
	if (!Nt(e)) throw TypeError(yt);
	return e;
}
// node_modules/temporal-polyfill/chunks/internal.js
var Ft = xt, It = (e) => `Missing ${e}`, Lt = (e) => "No valid fields: " + e.join(), Rt = (e, t, n) => xt(e, t) + "; must be " + Object.keys(n).join(), zt = "Cannot use valueOf", Bt = "Invalid calling context", Vt = (e) => "Missing year" + (e ? "/era/eraYear" : ""), Ht = "Invalid leap month", Ut = (e) => xt("Calendar", e), Wt = (e, t) => `Unknown calendar ${e}; might need ${t}`, Gt = (e) => xt("TimeZone", e), Kt = (e) => `Cannot parse: ${e}`, qt = (e) => `Invalid substring: ${e}`, Jt = Mt, Yt = Nt;
function k(e) {
	throw RangeError(e);
}
function A(e) {
	throw TypeError(e);
}
function Xt(e, t, n, r, i) {
	return Zt(t, ((e, t) => {
		let n = e[t];
		return n === void 0 && A(It(t)), n;
	})(e, t), n, r, i);
}
function Zt(e, t, n, r, i, a) {
	let o = Jt(t, n, r);
	return i && t !== o && k(((e, t, n, r, i) => i ? bt(e, i[t], i[n], i[r]) : bt(e, t, n, r))(e, t, n, r, a)), o;
}
function Qt(e, t = Map) {
	let n = new t();
	return (t, ...r) => {
		if (n.has(t)) return n.get(t);
		let i = e(t, ...r);
		return n.set(t, i), i;
	};
}
var $t = (e) => en({ name: e }, 1), en = (e, t) => nn((e) => ({
	value: e,
	configurable: 1,
	writable: !t
}), e), tn = (e) => ({ [Symbol.toStringTag]: {
	value: e,
	configurable: 1
} });
function nn(e, t) {
	let n = {};
	for (let r in t) n[r] = e(t[r], r);
	return n;
}
function rn(e, t) {
	let n = {};
	for (let r of e) n[r] = t;
	return n;
}
function an(e) {
	let t = {};
	for (let n of e) t[n] = (e) => e[n];
	return t;
}
function on(e, t, n = Object.create(null)) {
	for (let r of e) n[r] = t[r];
	return n;
}
function sn(e, t, n) {
	for (let r of e) if (t[r] !== n[r]) return 0;
	return 1;
}
function cn(e, t, n) {
	let r = { ...n };
	for (let n = 0; n < t; n++) r[e[n]] = 0;
	return r;
}
function ln(e, ...t) {
	return (...n) => e(...t, ...n);
}
function un() {}
function dn(e) {
	return e[0].toUpperCase() + e.substring(1);
}
function fn(...e) {
	return [].concat(...e).sort();
}
function pn(e) {
	return RegExp(`^${e}$`, "i");
}
function mn(e) {
	return parseInt(e.padEnd(9, "0"));
}
function hn(e) {
	return e && e !== "+" ? -1 : 1;
}
function gn(e) {
	return e === void 0 ? 0 : parseInt(e);
}
function _n(e, t) {
	return String(t).padStart(e, "0");
}
var vn = /*@__PURE__*/ ln(_n, 2);
function yn(e, t) {
	return Math.sign(e - t);
}
function bn(e, t) {
	return e < t ? -1 : +(e > t);
}
function xn(e, t) {
	let n = e / t;
	return e % t < 0n ? n - 1n : n;
}
function Sn(e, t) {
	let n = xn(e, t);
	return [n, e - n * t];
}
function Cn(e, t) {
	return [Math.floor(e / t), wn(e, t)];
}
function wn(e, t) {
	return (e % t + t) % t;
}
function Tn(e, t) {
	return Math.trunc(e / t) || 0;
}
function En(e, t) {
	return e % t || 0;
}
function Dn(e, t = 1) {
	return t * (.5 + e / 5);
}
function On(e) {
	return Math.abs(e % 1) === .5;
}
var kn = "iso8601", An = {
	bce: -1,
	ce: 0
};
function jn(e) {
	let t = e.normalize("NFD").toLowerCase().replace(/[^a-z0-9]/g, "");
	return t === "bc" || t === "b" ? "bce" : t === "ad" || t === "a" ? "ce" : t;
}
function Mn(e) {
	return e === void 0 ? "iso8601" : e === 0 ? "gregory" : e.id;
}
var Nn = /^M(\d{2})(L?)$/;
function Pn(e) {
	let t = Nn.exec(e);
	return t || k(((e) => `Invalid monthCode: ${e}`)(e)), [parseInt(t[1]), !!t[2]];
}
function Fn(e, t) {
	return "M" + vn(e) + (t ? "L" : "");
}
function In(e, t, n) {
	return e + (t || n && e >= n ? 1 : 0);
}
var Ln = {
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
}, Rn = /*@__PURE__*/ Object.keys(Ln), zn = St, Bn = Ct, Vn = wt, Hn = Tt, Un = Et, Wn = 864e11, Gn = [
	1,
	zn,
	Bn,
	Vn,
	Hn,
	Un,
	Wn
], Kn = /*@__PURE__*/ BigInt(zn), qn = /*@__PURE__*/ BigInt(Bn), Jn = /*@__PURE__*/ BigInt(Vn), Yn = /*@__PURE__*/ BigInt(Hn), Xn = /*@__PURE__*/ BigInt(Un), j = /*@__PURE__*/ BigInt(Wn);
function Zn(e, t) {
	let n = Number(e / j), r = Number(e % j);
	return Wn / t * n + (Math.trunc(r / t) + r % t / t);
}
var Qn = /*@__PURE__*/ Rn.slice(0, 6), $n = /*@__PURE__*/ an(Qn), er = ["year"], tr = ["day"], nr = [
	"day",
	"month",
	"year"
], rr = ["offset"], ir = ["timeZone"], ar = ["era", "eraYear"], or = [
	"era",
	"eraYear",
	"year"
], sr = ["month", "monthCode"], cr = [
	"day",
	"month",
	"monthCode"
], lr = /*@__PURE__*/ fn(Qn), ur = /*@__PURE__*/ fn(ar, er), dr = /*@__PURE__*/ fn(sr, er), fr = /*@__PURE__*/ fn(ar, dr), pr = /*@__PURE__*/ fn(["monthCode"], er), mr = /*@__PURE__*/ fn(ar, pr), hr = /*@__PURE__*/ fn(tr, ["monthCode"]), gr = /*@__PURE__*/ fn(tr, dr), _r = /*@__PURE__*/ fn(tr, ar, dr), vr = /*@__PURE__*/ fn(gr, Qn), yr = /*@__PURE__*/ fn(_r, Qn), br = /*@__PURE__*/ fn(gr, Qn, rr), xr = /*@__PURE__*/ fn(_r, Qn, rr), Sr = /*@__PURE__*/ fn(gr, Qn, rr, ir), Cr = /*@__PURE__*/ fn(_r, Qn, rr, ir), wr = /*@__PURE__*/ fn(tr, pr), Tr = /*@__PURE__*/ fn(tr, ar, pr), Er = /*@__PURE__*/ rn(Qn, 0);
function Dr(e) {
	return kr(e, 1), e;
}
var Or = {
	hour: 23,
	minute: 59,
	second: 59
};
function kr(e, t) {
	let n = {};
	for (let r of Qn) n[r] = Zt(r, e[r], 0, Or[r] || 999, t);
	return n;
}
function Ar(e) {
	return Mr(e) * Vn + Nr(e);
}
function jr(e) {
	return 1e3 * Mr(e) + e.millisecond;
}
function Mr(e) {
	return 3600 * e.hour + 60 * e.minute + e.second;
}
function Nr(e) {
	return e.millisecond * Bn + e.microsecond * zn + e.nanosecond;
}
function Pr(e) {
	let [t, n] = Cn(e, Wn);
	return [Fr(n), t];
}
function Fr(e) {
	let [t, n] = Cn(e, Bn), [r, i] = Cn(n, zn);
	return Ir(t, r, i);
}
function Ir(e, t = 0, n = 0) {
	let [r, i] = Cn(e, 36e5), [a, o] = Cn(i, 6e4), [s, c] = Cn(o, 1e3);
	return {
		hour: r,
		minute: a,
		second: s,
		millisecond: c,
		microsecond: t,
		nanosecond: n
	};
}
function Lr(e) {
	let [t, n] = Sn(e, Jn);
	return [Number(t), Number(n)];
}
function Rr(e) {
	return Number(xn(e, qn));
}
function zr(e) {
	return Vr(e) + BigInt(Ar(e));
}
function Br(e) {
	return Hr(e) + jr(e);
}
function Vr(e) {
	return BigInt(Ur(e)) * j;
}
function Hr(e) {
	return 864e5 * Ur(e);
}
function Ur(e) {
	return Wr(e.year, e.month, e.day);
}
function Wr(e, t = 1, n = 1) {
	let r = t - 1;
	return e += Math.floor(r / 12), t = wn(r, 12), Date.UTC(e % 400 - 400, t, 0) / 864e5 + 146097 * (Tn(e, 400) + 1) + n;
}
function Gr(e) {
	let [t, n] = Sn(e, j);
	return {
		...Kr(Number(t)),
		...Fr(Number(n))
	};
}
function Kr(e) {
	let t = /* @__PURE__ */ new Date(864e5 * wn(e, 146097));
	return {
		year: t.getUTCFullYear() + 400 * Math.floor(e / 146097),
		month: t.getUTCMonth() + 1,
		day: t.getUTCDate()
	};
}
function qr(e) {
	return [e, 0];
}
function Jr(e, t) {
	if (!t) return {
		year: 1972,
		month: e
	};
}
function Yr(e, t, n) {
	return {
		year: e,
		month: t,
		day: n
	};
}
function Xr(e, t) {
	switch (t) {
		case 2: return Qr(e) ? 29 : 28;
		case 4:
		case 6:
		case 9:
		case 11: return 30;
	}
	return 31;
}
function Zr(e) {
	return Qr(e) ? 366 : 365;
}
function Qr(e) {
	return e % 4 == 0 && (e % 100 != 0 || e % 400 == 0);
}
function $r(e, t, n) {
	return e += Tn(n, 12), (t += En(n, 12)) < 1 ? (e--, t += 12) : t > 12 && (e++, t -= 12), {
		year: e,
		month: t
	};
}
function ei(e, t, n, r) {
	return 12 * (n - e) + r - t;
}
function ti(e) {
	return wn(Wr(e.year, e.month, e.day) + 4, 7) || 7;
}
function ni(e) {
	return Wr(e.year, e.month, e.day) - Wr(e.year) + 1;
}
function ri(e) {
	let t = e.year, n = Math.floor((ni(e) - ti(e) + 10) / 7), r = ii(t);
	return n < 1 ? n = r = ii(--t) : n > r && (n = 1, r = ii(++t)), {
		weekOfYear: n,
		yearOfWeek: t,
		De: r
	};
}
function ii(e) {
	let t = ti({
		year: e,
		month: 1,
		day: 1
	});
	return t === 4 || t === 3 && Qr(e) ? 53 : 52;
}
function ai({ year: e }) {
	return e < 1 ? {
		era: "bce",
		eraYear: 1 - e
	} : {
		era: "ce",
		eraYear: e
	};
}
function oi(e) {
	return si(e), Dr(e);
}
function si(e) {
	return li(e, 1), e;
}
function ci(e) {
	return sn(nr, e, li(e));
}
function li(e, t) {
	let { year: n } = e, r = Xt(e, "month", 1, 12, t);
	return {
		year: n,
		month: r,
		day: Xt(e, "day", 1, Xr(n, r), t)
	};
}
function M(e, t) {
	return e ? e.de(t) : t;
}
function ui(e, t, n) {
	return e ? e.N(t, n) : qr(n);
}
function di(e, t) {
	return e === 0 ? ai(t) : e && e.h?.(t) || {};
}
function fi(e, t, n, r) {
	return e ? e.fe(t, n, r) : Yr(t, n, r);
}
function pi(e, t) {
	return e ? e.j(t) : 12;
}
function mi(e, t, n) {
	return e ? e.o(t, n) : Xr(t, n);
}
function hi(e, t) {
	let { year: n, month: r } = M(e, t), [i, a] = ui(e, n, r);
	return Fn(i, a);
}
function gi(e, t) {
	let { year: n } = M(e, t);
	return e ? e.q(n) : Qr(n);
}
function _i(e, t) {
	let { year: n } = M(e, t);
	return pi(e, n);
}
function vi(e, t) {
	let { year: n, month: r } = M(e, t);
	return mi(e, n, r);
}
function yi(e, t) {
	let { year: n } = M(e, t);
	return e ? e.i(n) : Zr(n);
}
function bi(e, t) {
	if (!e) return ni(t);
	let { year: n } = M(e, t), r = fi(e, n, 1, 1);
	return Ur(t) - Ur(r) + 1;
}
function xi(e, t) {
	return e === void 0 ? ri(t).weekOfYear : void 0;
}
function Si(e, t) {
	return e === void 0 ? ri(t).yearOfWeek : void 0;
}
var N = /*@__PURE__*/ Rn.map((e) => e + "s"), Ci = /*@__PURE__*/ an(N), wi = /*@__PURE__*/ fn(N), Ti = /*@__PURE__*/ N.slice(0, 6), Ei = /*@__PURE__*/ N.slice(6), Di = /*@__PURE__*/ Ei.slice(1), Oi = /*@__PURE__*/ rn(N, 0), ki = /*@__PURE__*/ rn(Ti, 0), Ai = /*@__PURE__*/ ln(cn, N);
function ji(e, t) {
	return t ?? k(It(e)), t;
}
var Mi = /*@__PURE__*/ ln(Ni, "string");
function Ni(e, t, n = e) {
	return typeof t !== e && A(Ft(n, t)), t;
}
function Pi(e, t = "number") {
	return Number.isInteger(e) || k(((e, t) => `Non-integer ${e}: ${t}`)(t, e)), e || 0;
}
function Fi(e) {
	return typeof e == "symbol" && A("Cannot convert Symbol to string"), String(e);
}
function Ii(e) {
	if (!Nt(e)) return e;
	let t = e[Symbol.toPrimitive];
	if (t != null) {
		typeof t != "function" && A();
		let n = Reflect.apply(t, e, ["string"]);
		return Nt(n) && A(), n;
	}
	return Date.prototype[Symbol.toPrimitive].call(e, "string");
}
function Li(e) {
	return typeof e == "boolean" ? BigInt(+!!e) : typeof e == "string" ? BigInt(e) : (typeof e != "bigint" && A(`Invalid bigint: ${e}`), e);
}
function Ri(e, t) {
	return Pi(Ot(e, t), t);
}
var zi = "smallestUnit", Bi = {
	constrain: 0,
	reject: 1
}, Vi = {
	compatible: 0,
	reject: 1,
	earlier: 2,
	later: 3
}, Hi = {
	reject: 0,
	use: 1,
	prefer: 2,
	ignore: 3
}, Ui = {
	auto: 0,
	never: 1,
	critical: 2,
	always: 3
}, Wi = {
	auto: 0,
	never: 1,
	critical: 2
}, Gi = {
	auto: 0,
	never: 1
}, Ki = {
	floor: 0,
	halfFloor: 1,
	ceil: 2,
	halfCeil: 3,
	trunc: 4,
	halfTrunc: 5,
	expand: 6,
	halfExpand: 7,
	halfEven: 8
}, qi = [
	Math.floor,
	(e) => On(e) ? Math.floor(e) : Math.round(e),
	Math.ceil,
	(e) => On(e) ? Math.ceil(e) : Math.round(e),
	Math.trunc,
	(e) => On(e) ? Math.trunc(e) || 0 : Math.round(e),
	(e) => e < 0 ? Math.floor(e) : Math.ceil(e),
	(e) => Math.sign(e) * Math.round(Math.abs(e)) || 0,
	(e) => On(e) ? (e = Math.trunc(e) || 0) + e % 2 : Math.round(e)
], Ji = {
	previous: -1,
	next: 1
};
function Yi(e) {
	let t = e.roundingIncrement;
	return t === void 0 ? 1 : kt(t, "roundingIncrement");
}
function Xi(e) {
	let t = e.fractionalSecondDigits;
	if (t !== void 0) {
		if (typeof t != "number") {
			if (Fi(t) === "auto") return;
			k(Ft("fractionalSecondDigits", t));
		}
		t = Zt("fractionalSecondDigits", Math.floor(t), 0, 9, 1);
	}
	return t;
}
function Zi(e, t, n = 0, r) {
	let i = t[e];
	if (i === void 0) return r ? n : void 0;
	if (i = Fi(i), i === "auto") return r ? n : null;
	let a = Ln[i];
	return a === void 0 && (a = N.indexOf(i)), a < 0 && k(Rt(e, i, Ln)), a;
}
function Qi(e, t, n, r = 0) {
	let i = n[e];
	if (i === void 0) return r;
	let a = Fi(i), o = t[a];
	return o === void 0 && k(Rt(e, a, t)), o;
}
var $i = /*@__PURE__*/ ln(Zi, zi), ea = /*@__PURE__*/ ln(Zi, "largestUnit"), ta = /*@__PURE__*/ ln(Zi, "unit"), na = /*@__PURE__*/ ln(Qi, "overflow", Bi), ra = /*@__PURE__*/ ln(Qi, "disambiguation", Vi), ia = /*@__PURE__*/ ln(Qi, "offset", Hi), aa = /*@__PURE__*/ ln(Qi, "calendarName", Ui), oa = /*@__PURE__*/ ln(Qi, "timeZoneName", Wi), sa = /*@__PURE__*/ ln(Qi, "offset", Gi), ca = /*@__PURE__*/ ln(Qi, "roundingMode", Ki), la = /*@__PURE__*/ ln(Qi, "direction", Ji);
function ua(e, t) {
	return typeof e == "string" ? ((e, t) => {
		let n = Object.create(null);
		return n[e] = t, n;
	})(t, e) : Pt(e);
}
function da(e, t, n, r) {
	let i = r ? Wn : Gn[t + 1];
	if (i) {
		let n = Gn[t];
		i % ((e = Zt("roundingIncrement", e, 1, i / n - +!r, 1)) * n) && k(Ft("roundingIncrement", e));
	} else e = Zt("roundingIncrement", e, 1, n ? 10 ** 9 : 1, 1);
	return e;
}
function fa(e, t, n, r) {
	return t != null && Zt(e, t, n, r, 1, Rn), t;
}
function pa(e, t) {
	t > e && k("smallestUnit > largestUnit");
}
function ma(e, t, n, r = 9, i = 0, a = 4) {
	t = Dt(t);
	let o = ea(t, i), s = Yi(t), c = ca(t, a), l = $i(t, i, 1);
	return o = fa("largestUnit", o, i, r), l = fa(zi, l, i, r), o == null ? o = Math.max(n, l) : pa(o, l), s = da(s, l, 1), e && (c = ((e) => e < 4 ? (e + 2) % 4 : e)(c)), [
		o,
		l,
		s,
		c
	];
}
function ha(e, t = 6, n) {
	let r = Yi(e = ua(e, zi)), i = ca(e, 7), a = $i(e);
	return a = ji(zi, a), a = fa(zi, a, 0, t), r = da(r, a, void 0, n), [
		a,
		r,
		i
	];
}
function P(e, t) {
	return on(nr, e, on(Qn, t));
}
var ga = /*@__PURE__*/ BigInt(1e8) * j, _a = /*@__PURE__*/ BigInt(-1e8) * j, va = _a - j, ya = -3261848;
function ba(e) {
	let t = 12 * e.year + e.month;
	return (t < ya || t > 3309129) && k("Out-of-bounds date"), e;
}
function xa(e, t = 1) {
	let n = Vr(e);
	return (n < (t ? va : _a) || n > ga) && k("Out-of-bounds date"), e;
}
function Sa(e) {
	return Ca(zr(e)), e;
}
function Ca(e) {
	return (e <= va || e >= ga + j) && k("Out-of-bounds date"), e;
}
function wa(e) {
	return (e < _a || e > ga) && k("Out-of-bounds date"), e;
}
function Ta(e, t) {
	return wa(Vr(e) + BigInt(Ar(e) - t));
}
function Ea(e, t, n) {
	return Da(e / t, n) * t;
}
function Da(e, t) {
	return qi[t](e);
}
function Oa(e, t, n, r) {
	let i = Aa(e, t, r, n);
	return Ma(i, Number(n - i.I) / Number(i.O - i.I));
}
function ka(e, t, n, r) {
	return Ea(Ma(e, Na(t, e.I, e.O)), n, r);
}
function Aa(e, t, n, r) {
	let i = ja(e, t, n);
	return ((e, t, n, r) => r > 0 ? t <= e && e <= n : n <= e && e <= t)(r, i.I, i.O, Math.sign(t)) || (i = ja(e + t, t, n)), i;
}
function ja(e, t, n) {
	let r = e + t;
	return {
		ve: e,
		qe: r,
		I: n(e),
		O: n(r)
	};
}
function Ma(e, t) {
	return e.ve + t * (e.qe - e.ve);
}
function Na(e, t, n) {
	let r = n - t, i = e - t;
	if (!i) return 0;
	let a = i < 0n ? -i : i, o = r < 0n ? -r : r, s = bn(i, 0n) === bn(r, 0n) ? 1 : -1;
	return bn(a, o) <= 0 ? a === o ? s : Dn(bn(2n * a, o), s) : Number(i) / Number(r);
}
function Pa(e) {
	return { epochNanoseconds: e };
}
function Fa(e, t, n) {
	return {
		calendar: n,
		timeZone: t,
		epochNanoseconds: e
	};
}
function Ia(e, t) {
	return on(Qn, e, F(e, t));
}
function F(e, t) {
	return on(nr, e, { calendar: t });
}
function La(e) {
	return on(Qn, e);
}
function Ra(e) {
	return on(N, e, { sign: ts(e) });
}
function za(e, t, n, r) {
	return Fa(t === 6 ? Wa(e, r) : Ga(e, t, n, r), e.timeZone, e.calendar);
}
function Ba(e) {
	let [t, n] = Za(e);
	return Zn(n - t, Un);
}
function Va(e) {
	let { timeZone: t, calendar: n } = e;
	return Fa(lo(t, P(ao(e), Er)), t, n);
}
function Ha(e, t, n, r, i, a, o, s) {
	if (r === 0 && i === 1) return e;
	let c = ts(e) || 1, [l, u, d] = (qo(r, s) ? s && r < 6 && n >= 6 ? Ya : Xa : Ja)(c, e, t, n, r, i, a, o);
	return d && r !== 7 && (l = ((e, t, n, r, i, a) => {
		for (let o = r + 1; o <= n; o++) {
			if (o === 7 && n !== 7) continue;
			let r = Ai(o, e);
			r[N[o]] += i;
			let s = bn(t, Uo(a, r));
			if (s && s !== i) break;
			e = r;
		}
		return e;
	})(l, u, n, Math.max(6, r), c, o)), l;
}
function Ua(e, t, n) {
	let r = Math.min(ds(e), 6);
	return cs(eo(as(e), BigInt(t), n), r);
}
function Wa(e, t) {
	let [n, r] = Za(e);
	return Qa(e.epochNanoseconds, n, r, t);
}
function Ga(e, t, n, r) {
	if (t === 0 && n === 1) return e.epochNanoseconds;
	let i = ao(e);
	return so(e.timeZone, Ka(i, ro(t, n), r), i.offsetNanoseconds, 2, 0, 1);
}
function Ka(e, t, n) {
	let [r, i] = qa(e, t, n), a = P(Co(e, i), r);
	return Sa(a), a;
}
function qa(e, t, n) {
	return Pr(Ea(Ar(e), t, n));
}
function Ja(e, t, n, r, i, a, o, s) {
	let c = N[i], l = Ai(i, t);
	i === 7 && (t = {
		...t,
		weeks: t.weeks + Math.trunc(t.days / 7)
	});
	let u = Tn(t[c], a) * a, d = Aa(u, a * e, (e) => (l[c] = e, Uo(s, l)), n), f = ka(d, n, a, o);
	return l[c] = f, [
		l,
		f === d.qe ? d.O : d.I,
		f !== u
	];
}
function Ya(e, t, n, r, i, a, o, s) {
	let c = Number(os(t)), l = ro(i, a), u = Ea(c, l, o), d = {
		...t,
		...ki
	}, f = Aa(d.days, e, (e) => (d.days = e, Uo(s, d)), n), p = f.I, m = f.O, h = u - Number(m - p), g = 0;
	h && Math.sign(h) !== e ? n = p + BigInt(u) : (g += e, u = Ea(h, l, o), n = m + BigInt(u));
	let _ = ls(u);
	return [
		{
			...t,
			..._,
			days: t.days + g
		},
		n,
		!!g
	];
}
function Xa(e, t, n, r, i, a, o) {
	let s = as(t), c = eo(s, io(i, a), o), l = c - s, u = Math.sign(Number(c / j) - Number(s / j)) === e, d = cs(c, Math.min(r, 6));
	return [
		{
			...t,
			...d
		},
		n + l,
		u
	];
}
function Za(e) {
	let { timeZone: t } = e, n = P(ao(e), Er), r = P(Co(n, 1), Er);
	return [lo(t, n), lo(t, r)];
}
function Qa(e, t, n, r) {
	return Da(Na(e < n ? e : n - 1n, t, n), r) ? n : t;
}
function $a(e) {
	return Ea(e, Hn, 7);
}
function eo(e, t, n) {
	return no(e, t, n, e / t % 2n);
}
function to(e, t, n) {
	let [r, i] = Sn(e, j), a = r * j;
	return a + no(i, t, n, (a / t + i / t) % 2n);
}
function no(e, t, n, r) {
	let i = e / t, a = e % t, o = 0;
	a && (o = Dn(bn(2n * (a < 0n ? -a : a), t), Math.sign(Number(a))));
	let s = Da(Number(r) + o, n);
	return (i - r + BigInt(s)) * t;
}
function ro(e, t) {
	return Gn[e] * t;
}
function io(e, t) {
	return BigInt(Gn[e]) * BigInt(t);
}
var ao = /*@__PURE__*/ Qt(oo, WeakMap);
function oo(e) {
	let { epochNanoseconds: t, timeZone: n } = e, r = n.B(t);
	return {
		...Gr(t + BigInt(r)),
		offsetNanoseconds: r
	};
}
function so(e, t, n, r = 0, i = 0, a, o) {
	if (n !== void 0 && r === 1 && (r === 1 || o)) return Ta(t, n);
	r !== 2 && r !== 0 || xa(t, 0);
	let s = e.R(t);
	if (n !== void 0 && r !== 3) {
		let e = ((e, t, n, r) => {
			let i = zr(t);
			r && (n = $a(n));
			for (let t of e) {
				let e = Number(i - t);
				if (r && (e = $a(e)), e === n) return t;
			}
		})(s, t, n, a);
		if (e !== void 0) return e;
		r === 0 && k("Invalid TimeZone offset");
	}
	return o ? zr(t) : co(e, t, i, s);
}
function co(e, t, n = 0, r = e.R(t)) {
	if (r.length === 1) return r[0];
	if (n === 1 && k("Ambiguous offset"), r.length) return r[+(n === 3)];
	let i = zr(t), a = ((e, t) => {
		let n = e.B(t - j);
		return ((e) => (e > Wn && k("Out-of-bounds TimeZone gap"), e))(e.B(t + j) - n);
	})(e, i), o = Gr(i + BigInt(a * (n === 2 ? -1 : 1)));
	return (r = e.R(o))[n === 2 ? 0 : r.length - 1];
}
function lo(e, t) {
	let n = e.R(t);
	if (n.length) return n[0];
	let r = zr(t) - j;
	return e.C(r, 1);
}
function uo(e, t, n, r = 0) {
	return ts(n) && Zt("unit", ds(n), 8, 9, 1, N), _o(e, go(e, xa(_o(e, t)), n, r));
}
function fo(e, t, n = 0) {
	let { calendar: r, epochNanoseconds: i, timeZone: a } = e, o = os(t), s = i;
	if (us(t)) {
		let i = ao(e);
		s = co(a, P(go(r, i, {
			...t,
			...ki
		}, n), i)) + o;
	} else s += o;
	return {
		...e,
		epochNanoseconds: wa(s)
	};
}
function po(e, t, n, r = 0) {
	let [i, a] = mo(t, n);
	return Sa(P(go(e, t, {
		...n,
		...ki,
		days: n.days + a
	}, r), i));
}
function mo(e, t) {
	return xo(e, os(t));
}
function ho(e, t) {
	return So(e, (Zt("unit", ds(n = t), 0, 5, 1, N), os(n)));
	var n;
}
function go(e, t, n, r = 0) {
	let { years: i, months: a, weeks: o, days: s } = n, c;
	if (s += Number(os(n) / j), i || a) c = vo(e, t, i, a, r);
	else {
		if (!o && !s) return t;
		c = t;
	}
	return (o || s) && (c = Co(c, 7 * o + s)), xa(c);
}
function _o(e, t) {
	return Co(t, 1 - M(e, t).day);
}
function vo(e, t, n, r, i) {
	let { year: a, month: o, day: s } = M(e, t);
	if (n) {
		let [t, r] = ui(e, a, o);
		a += n, o = bo(e, t, r, e ? e.p(a) : void 0, i), o = Zt("month", o, 1, pi(e, a), i);
	}
	if (r) {
		let t = yo(e, a, o, r);
		({year: a, month: o} = t);
	}
	return s = Zt("day", s, 1, mi(e, a, o), i), fi(e, a, o, s);
}
function yo(e, t, n, r) {
	return e ? e.ae(t, n, r) : $r(t, n, r);
}
function bo(e, t, n, r, i) {
	if (n) {
		let n = e ? e.l : void 0;
		return r !== void 0 && (n < 0 || r === t + 1) ? r : (i === 1 && k(Ht), n < 0 ? -n : t);
	}
	return In(t, 0, r);
}
function xo(e, t) {
	let n = Number(t / j), r = Number(t % j), [i, a] = Pr(Ar(e) + r);
	return [i, n + a];
}
function So(e, t) {
	return wa(e + t);
}
function Co(e, t) {
	return t ? Kr(Ur(e) + t) : e;
}
function wo(e, t) {
	return Mn(e) !== Mn(t) && k("Mismatching Calendars"), e;
}
function To(e, t) {
	return e.m !== t.m && k("Mismatching TimeZones"), e;
}
function Eo(e) {
	return e.timeZone.id;
}
function Do(e, t, n, r, i, a, o) {
	return r < 6 ? No(t.epochNanoseconds, n.epochNanoseconds, r, i, a, o) : ((e, t, n, r, i, a, o) => {
		let s = n.epochNanoseconds;
		if (s === t.epochNanoseconds) return Oi;
		let c, l = To(t.timeZone, n.timeZone);
		return c = Po(l, e, t, n, r), c = Ha(c, s, r, i, a, o, Wo(e, l, t), 1), c;
	})(e, t, n, r, i, a, o);
}
function Oo(e, t, n, r, i, a, o) {
	return r <= 6 ? No(zr(t), zr(n), r, i, a, o) : ((e, t, n, r, i, a, o) => {
		let s = zr(n), c = bn(s, zr(t));
		if (!c) return Oi;
		let l;
		return l = Io(e, t, n, c, r), l = Ha(l, s, r, i, a, o, ((e, t) => ({
			ne: zr(t),
			ke: (n) => zr(P(go(e, t, n), t))
		}))(e, t)), l;
	})(e, t, n, r, i, a, o);
}
function ko(e, t, n, r, i, a, o) {
	let s = _o(e, t), c = _o(e, n);
	return zo(s, c) ? Mo(e, xa(s), xa(c), r, i, a, o, 8) : Oi;
}
function Ao(e, t, n, r, i, a, o, s = 6) {
	return r === 6 ? No(Vr(t), Vr(n), r, i, a, o) : Mo(e, t, n, r, i, a, o, s);
}
function jo(e, t, n, r, i, a) {
	let o = Ea(Ar(t) - Ar(e), ro(r, i), a);
	return {
		...Oi,
		...ls(o, n)
	};
}
function Mo(e, t, n, r, i, a, o, s = 6) {
	let c = Vr(n);
	if (!zo(t, n)) return Oi;
	let l;
	return l = Ro(e, t, n, r), i === s && a === 1 || (l = Ha(l, c, r, i, a, o, Go(e, t))), l;
}
function No(e, t, n, r, i, a) {
	return {
		...Oi,
		...cs(eo(t - e, io(r, i), a), n)
	};
}
function Po(e, t, n, r, i) {
	return i < 6 ? {
		...Oi,
		...cs(r.epochNanoseconds - n.epochNanoseconds, i)
	} : ((e, n, r) => {
		let a = bn(r.epochNanoseconds, n.epochNanoseconds);
		if (!a) return Oi;
		if (!zo(ao(n), ao(r))) return {
			...Oi,
			...cs(r.epochNanoseconds - n.epochNanoseconds, 5)
		};
		let [o, s, c] = Lo(e, n, r, a);
		return {
			...(l = o, u = s, Ro(t, l, u, i)),
			...ls(c)
		};
		var l, u;
	})(e, n, r);
}
function Fo(e, t, n, r) {
	let i = zr(t), a = zr(n), o = bn(a, i);
	return o ? r <= 6 ? {
		...Oi,
		...cs(a - i, r)
	} : Io(e, t, n, o, r) : Oi;
}
function Io(e, t, n, r, i) {
	let a = n, o = Ar(n) - Ar(t);
	return Math.sign(o) === -r && (a = Co(n, -r), o += Wn * r), {
		...Ro(e, t, a, i),
		...ls(o)
	};
}
function Lo(e, t, n, r) {
	let i = ao(t), a = ao(n), o = n.epochNanoseconds, s = 0, c = Ar(a) - Ar(i);
	Math.sign(c) === -r && s++;
	let l = s + +(r > 0);
	for (;; s++) {
		let t = Co(a, s * -r), n = co(e, P(t, i));
		if (s === l || bn(o, n) !== -r) return [
			i,
			t,
			Number(o - n)
		];
	}
}
function Ro(e, t, n, r) {
	if (r <= 7) return ((e, t, n) => {
		let r = Bo(t, n);
		return e ? {
			...Oi,
			weeks: Tn(r, 7),
			days: En(r, 7)
		} : {
			...Oi,
			days: r
		};
	})(r === 7, t, n);
	let i = M(e, t), a = M(e, n);
	if (r === 8) {
		let { year: r, month: o, day: s } = i, { year: c, month: l, day: u } = a, d = Math.sign(yn(c, r) || yn(l, o) || Bo(t, n)), f = 0, p = 0;
		if (d) {
			f = e ? e.ee(r, o, c, l) : ei(r, o, c, l);
			let i = vo(e, t, 0, f, 0);
			d * yn(s, u) > 0 && (f -= d, i = vo(e, t, 0, f, 0)), p = Bo(i, n);
		}
		return {
			...Oi,
			months: f,
			days: p
		};
	}
	let { year: o, month: s, day: c } = i, { year: l, month: u, day: d } = a, f = l - o, p = u - s, m = d - c;
	if (f || p) {
		let t = Math.sign(f || p), n = mi(e, l, u), r = 0;
		if (Math.sign(d - c) === -t) {
			let i = n, a = yo(e, l, u, -t);
			({year: l, month: u} = a), f = l - o, p = u - s, n = mi(e, l, u), r = t < 0 ? -i : n;
		}
		if (m = d - Math.min(c, n) + r, f) {
			let [n, r] = ui(e, o, s), [i, a] = ui(e, l, u), c = e ? e.l : void 0;
			if (p = c !== void 0 && r && !a && (c < 0 ? t > 0 && i === -c : t < 0 && i === n) ? 0 : i - n || Number(a) - Number(r), Math.sign(p) === -t) {
				let i = t < 0 && -pi(e, l);
				l -= t, f = l - o, p = u - bo(e, n, r, e ? e.p(l) : void 0, 0) + (i || pi(e, l));
			} else if (e) {
				let t = bo(e, n, r, e.p(l), 0);
				p = e.ee(l, t, l, u);
			}
		}
	}
	return {
		...Oi,
		years: f,
		months: p,
		days: m
	};
}
function zo(e, t) {
	return yn(e.year, t.year) || yn(e.month, t.month) || yn(e.day, t.day);
}
function Bo(e, t) {
	return Ur(t) - Ur(e);
}
function Vo(e, t, n) {
	return Ko(e) ? ((e, t, n) => {
		let { calendar: r, timeZone: i } = e, a = fo(e, t);
		return [
			Po(i, r, e, a, n),
			a.epochNanoseconds,
			Wo(r, i, e)
		];
	})(e, t, n) : ((e, t, n) => {
		let { calendar: r } = e, i = Sa(P(e, Er)), a = po(r, i, t);
		return [
			Fo(r, i, a, n),
			zr(a),
			Go(r, e)
		];
	})(e, t, n);
}
function Ho(e, t) {
	return Ko(e) ? fo(e, t).epochNanoseconds : zr(po(e.calendar, P(e, Er), t));
}
function Uo(e, t) {
	return us(t) ? e.ke(t) : e.ne;
}
function Wo(e, t, n) {
	let r = ao(n);
	return {
		ne: n.epochNanoseconds,
		ke: (n) => co(t, P(go(e, r, n), r))
	};
}
function Go(e, t) {
	return {
		ne: Vr(t),
		ke: (n) => Vr(go(e, t, n))
	};
}
function Ko(e) {
	return "timeZone" in e;
}
function qo(e, t) {
	return e <= 6 - !!t;
}
function Jo(e, t, n) {
	let r = {};
	for (let i = t; i >= 0; i--) {
		let t = Gn[i];
		r[n[i]] = Tn(e, t), e = En(e, t);
	}
	return r;
}
var Yo = 2 ** 53;
function Xo(e, t, n) {
	let r = Math.max(ds(t), ds(n));
	return Zt("unit", r, 0, 6, 1, N), ((e, t, n, r) => Ra(ns(((e, t, n, r) => {
		let i = as(e) + as(t) * BigInt(r ? -1 : 1);
		return Number.isFinite(Number(i / j)) || k("Out-of-bounds date"), {
			...Oi,
			...cs(i, n)
		};
	})(t, n, r, e))))(e, t, n, r);
}
function Zo(e, t, n) {
	let r = ds(t), [i, a, o, s, c] = ((e, t, n) => {
		e = ua(e, zi);
		let r = ea(e), i = n(e.relativeTo), a = Yi(e), o = ca(e, 7), s = $i(e);
		return r === void 0 && s === void 0 && k("Required smallestUnit or largestUnit"), s ??= 0, r ??= Math.max(s, t), pa(r, s), a = da(a, s, 1), a > 1 && s > 5 && r !== s && k("For calendar units with roundingIncrement > 1, use largestUnit = smallestUnit"), [
			r,
			s,
			a,
			o,
			i
		];
	})(n, r, e);
	if (!c && Math.max(r, i) <= 6) return Ra(ns(((e, t, n, r, i) => {
		let a = eo(as(e), io(n, r), i);
		return {
			...Oi,
			...cs(a, t)
		};
	})(t, i, a, o, s)));
	c || k("Missing relativeTo");
	let l = Ko(c);
	if (!t.sign && (!l || i < 6)) return t;
	let [u, d, f] = Vo(c, t, i);
	return Ra(Ha(u, d, i, a, o, s, f, l));
}
function Qo(e) {
	return e.sign === -1 ? $o(e) : e;
}
function $o(e) {
	return Ra(es(e));
}
function es(e) {
	let t = {};
	for (let n of N) t[n] = -1 * e[n] || 0;
	return t;
}
function ts(e, t = N) {
	let n = 0;
	for (let r of t) {
		let t = Math.sign(e[r]);
		t && (n && n !== t && k("Cannot mix duration signs"), n = t);
	}
	return n;
}
function ns(e) {
	for (let t of Di) rs(t, e[t]);
	let t = as(e);
	return is(Number(t / Jn)), e;
}
function rs(e, t) {
	return Zt(e, t, -4294967295, 4294967295, 1);
}
function is(e) {
	Number.isSafeInteger(e) || k("Out-of-bounds duration");
}
function as(e) {
	return BigInt(e.days) * j + os(e);
}
function os(e) {
	return BigInt(e.hours) * Xn + BigInt(e.minutes) * Yn + ss(e);
}
function ss(e) {
	return BigInt(e.seconds) * Jn + BigInt(e.milliseconds) * qn + BigInt(e.microseconds) * Kn + BigInt(e.nanoseconds);
}
function cs(e, t = 6) {
	let n = Number(e / j), r = Number(e % j), i = Gn[t], a = t <= 3 ? Number(e / BigInt(i)) : Wn / i * n + Tn(r, i);
	Number.isFinite(a) || k("Out-of-bounds date"), t <= 3 && Math.abs(a) / (Vn / Gn[t]) >= Yo && k("Out-of-bounds date");
	let o = Jo(r, t, N);
	return o[N[t]] = a, o;
}
function ls(e, t = 5) {
	return Jo(e, t, N);
}
function us(e) {
	return !!ts(e, Ei);
}
function ds(e) {
	let t = 9;
	for (; t > 0 && !e[N[t]]; t--);
	return t;
}
function fs(e, t) {
	return bn(e.epochNanoseconds, t.epochNanoseconds);
}
function ps(e, t, n, r) {
	let i = e(Dt(r).relativeTo), a = Math.max(ds(t), ds(n));
	return sn(N, t, n) ? 0 : qo(a, i && Ko(i)) ? bn(as(t), as(n)) : (i || k("Missing relativeTo"), bn(Ho(i, t), Ho(i, n)));
}
function ms(e, t) {
	return hs(e, t) || gs(e, t);
}
function hs(e, t) {
	return yn(Ur(e), Ur(t));
}
function gs(e, t) {
	return yn(Ar(e), Ar(t));
}
function _s(e, t) {
	return !fs(e, t);
}
function vs(e, t) {
	return !fs(e, t) && e.timeZone.m === t.timeZone.m && e.calendar === t.calendar;
}
function ys(e, t) {
	return !ms(e, t) && e.calendar === t.calendar;
}
function bs(e, t) {
	return !hs(e, t) && e.calendar === t.calendar;
}
function xs(e, t) {
	return !hs(e, t) && e.calendar === t.calendar;
}
function Ss(e, t) {
	return !hs(e, t) && e.calendar === t.calendar;
}
function Cs(e, t) {
	return !gs(e, t);
}
function ws(e) {
	return e === 0 ? An : e ? e.k : void 0;
}
function Ts(e, t, n = t) {
	return ws(e) ? n : t;
}
function Es(e, t) {
	let n = t || void 0, r = ws(t), { era: i, eraYear: a, year: o } = e;
	if (o !== void 0 && (o = kt(o, "year")), a !== void 0 && (a = kt(a, "eraYear")), i !== void 0 || a !== void 0) {
		i !== void 0 && a !== void 0 || A("Mismatching era/eraYear"), r || k("Forbidden era/eraYear");
		let e = jn(i), t = r[e];
		t === void 0 && k(((e) => `Invalid era: ${e}`)(i));
		let s = n?._ ? n._(a, e, t) : ks(a, t);
		o !== void 0 && o !== s && k("Mismatching year/eraYear"), o = s;
	} else o === void 0 && A(Vt(r));
	return o;
}
function Ds(e, t, n, r, i) {
	let { month: a, monthCode: o } = e;
	if (o !== void 0) {
		let e = ((e, t, n, r, i = Pn(t)) => {
			let a = e ? e.p(n) : void 0, [o, s] = i, c = In(o, s, a);
			if (s) {
				let t = e ? e.l : void 0;
				t === void 0 && k(Ht), t > 0 ? (c > t && k(Ht), a !== c && (r === 1 && k(Ht), c = In(o, 0, a))) : (c !== -t && k(Ht), a === void 0 && r === 1 && k(Ht));
			}
			return c;
		})(t, o, n, i, r);
		a !== void 0 && a !== e && k("Mismatching month/monthCode"), a = e, i = 1;
	} else a === void 0 && A("Missing month/monthCode");
	return Zt("month", a, 1, pi(t, n), i);
}
function Os(e, t, n, r, i) {
	return Xt(e, "day", 1, mi(t, n, r), i);
}
function ks(e, t) {
	return (t + e) * (Math.sign(t) || 1) || 0;
}
function As(e, t) {
	return kr(on(Qn, {
		...Er,
		...e
	}), t);
}
var js = /*@__PURE__*/ pn("([+-])(\\d{2})(?::?(\\d{2})(?::?(\\d{2})(?:[.,](\\d{1,9}))?)?)?");
function Ms(e) {
	let t = Ns(e);
	return t === void 0 && k(Kt(e)), t;
}
function Ns(e, t) {
	let n = js.exec(e);
	if (n && ((e) => ((e) => {
		e[0] !== "T" && e[0] !== "t" || (e = e.slice(1));
		let t = e.search(/[.,]/), n = t < 0 ? e : e.slice(0, t), r = n.split(":");
		return r.length === 1 ? /^(?:\d{2}|\d{4}|\d{6})$/i.test(n) : (r.length === 2 || r.length === 3) && r.every((e) => e.length === 2 && /^\d{2}$/i.test(e));
	})(e.slice(1)))(n[0])) return ((e, t) => {
		let n = e[4] || e[5];
		return t && n && k(qt(n)), r = (gn(e[2]) * Un + gn(e[3]) * Hn + gn(e[4]) * Vn + mn(e[5] || "")) * hn(e[1]), Math.abs(r) >= Wn && k("Out-of-bounds offset"), r;
		var r;
	})(n, t);
}
var Ps = {
	era: Fi,
	month: At,
	monthCode(e, t) {
		return Mi(Ii(e), t);
	},
	day: At
}, Fs = /*@__PURE__*/ rn(Qn, kt), Is = /*@__PURE__*/ rn(N, Ri), Ls = /*@__PURE__*/ Object.assign({}, Ps, Fs), Rs = {
	offset(e) {
		return Ms(Mi(Ii(e)));
	},
	...Ls
};
function I(e, t, n, r, i = !r) {
	let a = {}, o = 0;
	for (let i of t) {
		let t = e[i];
		if (t !== void 0) {
			o = 1;
			let e = n[i];
			e && (t = e(t, i)), a[i] = t;
		} else r && r.includes(i) && A(It(i));
	}
	return i && !o && A(Lt(t)), a;
}
function L(e) {
	return e === void 0 ? 0 : na(Pt(e));
}
function zs(e, t = 0) {
	e = Dt(e);
	let n = ra(e), r = ia(e, t);
	return [
		na(e),
		r,
		n
	];
}
function Bs(e) {
	return ra(Dt(e));
}
function Vs(e, t, n) {
	let r = ws(t);
	e.year !== void 0 || e.era !== void 0 && e.eraYear !== void 0 || A(Vt(r)), e.monthCode === void 0 && e.month === void 0 && A("Missing month/monthCode"), n || e.day !== void 0 || A(It("day"));
	let i = Ys(e);
	return [Es(e, t), i];
}
function Hs(e, t) {
	let n = t === void 0, r = ws(t);
	e.day === void 0 && A(It("day")), n || e.month === void 0 || e.year !== void 0 || e.era !== void 0 && e.eraYear !== void 0 || A(Vt(r));
	let i = Ys(e);
	return [e.eraYear !== void 0 || e.year !== void 0 ? Es(e, t) : void 0, i];
}
function Us(e, t = Er, n) {
	let r = P(e, t);
	return Sa(r), Ia(r, n);
}
function Ws(e, t, n, r, i) {
	let a = Ds(e, t, n, r, i);
	return F(xa(fi(t, n, a, Os(e, t, n, a, i))), t);
}
function Gs(e, t, n, r, i) {
	return qs(t, n, Ds(e, t, n, r, i));
}
function Ks(e, t, n, r, i) {
	let a = t === void 0, o = n, s, c, l;
	if (o === void 0 && a && (o = 1972), o !== void 0) {
		a || xa(fi(t, o, 1, 1));
		let n = Ds(e, t, o, r, i);
		s = Os(e, t, o, n, i), [c, l] = ui(t, o, n);
	} else {
		e.monthCode === void 0 && A("Missing month/monthCode"), [c, l] = r;
		let n = t ? t.re : 1972;
		if (n !== void 0) s = Os(e, t, n, Ds(e, t, n, r, i), i);
		else {
			let n = i === 0 && t ? t.ge?.(c, l, e.day) : void 0;
			s = n === void 0 ? e.day : n;
		}
	}
	return Js(t, c, l, s, e.day, i);
}
function qs(e, t, n) {
	return F(ba(fi(e, t, n, 1)), e);
}
function Js(e, t, n, r, i, a) {
	let o = n;
	o && ((e && e.V?.[t]) ?? 1 / 0) < i && (a === 1 && k(Ht), o = 0, r = Jt(i, 1, (e && e.U) ?? 1 / 0));
	let s = e ? e.u(t, !!o, r) : Jr(t, !!o);
	for (; !s && a === 0 && r > 1;) r--, s = e ? e.u(t, !!o, r) : Jr(t, !!o);
	s || k("Cannot guess year");
	let { year: c, month: l } = s;
	return F(xa(fi(e, c, l, r)), e);
}
function Ys(e) {
	if (e.monthCode !== void 0) return Pn(e.monthCode);
}
var Xs = Intl.DateTimeFormat;
function Zs(e, t) {
	t < -864e13 && k("Out-of-bounds date");
	let n = e.formatToParts(t), r = {};
	for (let e of n) r[e.type] = e.value;
	return r;
}
var Qs = {
	El_Aaiun: 17,
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
}, $s = -388152e4;
function ec(e, t = 4) {
	let n = Xi(e);
	return [ca(e, 4), ...rc(fa(zi, $i(e), 0, t), n)];
}
function tc(e) {
	return aa(Dt(e));
}
function nc(e, t) {
	return ec(Dt(e), t);
}
function rc(e, t) {
	return e == null ? [t === void 0 ? 1 : 10 ** (9 - t), t] : [Gn[e], e < 4 ? 9 - 3 * e : -1];
}
function ic(e, t, n) {
	let [r, i, a, o] = ((e) => {
		let t = Xi(e = Dt(e)), n = ca(e, 4), r = $i(e);
		return [
			e.timeZone,
			n,
			...rc(fa(zi, r, 0, 4), t)
		];
	})(n), s = r !== void 0;
	return ((e, t, n, r, i, a) => {
		n = to(n, BigInt(i), r);
		let o = t.B(n);
		return yc(Gr(n + BigInt(o)), a) + (e ? wc($a(o)) : "Z");
	})(s, Rc(s ? e(r) : "UTC"), t.epochNanoseconds, i, a, o);
}
function ac(e) {
	return yc(Gr(e.epochNanoseconds), void 0) + "Z";
}
function oc(e, t) {
	let n = ((e) => {
		e = Dt(e);
		let t = aa(e), n = Xi(e), r = sa(e), i = ca(e, 4), a = $i(e);
		return [
			t,
			oa(e),
			r,
			i,
			...rc(fa(zi, a, 0, 4), n)
		];
	})(t);
	return ((e, t, n, r, i, a, o, s, c, l) => {
		r = to(r, BigInt(c), s);
		let u = n.B(r);
		return yc(Gr(r + BigInt(u)), l) + wc($a(u), o) + Tc(t, a) + Ec(e, i);
	})(e.calendar, e.timeZone.id, e.timeZone, e.epochNanoseconds, ...n);
}
function sc(e) {
	let t = e.calendar, n = e.timeZone, r = n.B(e.epochNanoseconds);
	return yc(Gr(e.epochNanoseconds + BigInt(r)), void 0) + wc($a(r)) + Tc(n.id, 0) + (t === void 0 ? "" : Dc(Mn(t), 0));
}
function cc(e, t) {
	let n = ((e) => (e = Dt(e), [aa(e), ...ec(e)]))(t);
	return ((e, t, n, r, i, a) => yc(Ka(t, i, r), a) + Ec(e, n))(e.calendar, e, ...n);
}
function lc(e) {
	let t = e.calendar;
	return yc(e, void 0) + (t === void 0 ? "" : Dc(Mn(t), 0));
}
function uc(e, t) {
	return n = e.calendar, r = e, i = tc(t), bc(r) + Ec(n, i);
	var n, r, i;
}
function dc(e, t) {
	return pc(e.calendar, xc, e, tc(t));
}
function fc(e, t) {
	return pc(e.calendar, Sc, e, tc(t));
}
function pc(e, t, n, r) {
	return r === 1 ? e === void 0 ? t(n) : bc(n) : r > 1 || r === 0 && e !== void 0 ? bc(n) + Dc(Mn(e), r === 2) : t(n);
}
function mc(e, t) {
	return ((e, t, n, r) => Cc(qa(e, n, t)[0], r))(e, ...nc(t));
}
function hc(e, t) {
	let [n, r, i] = nc(t, 3);
	return r > 1 && ns(e = {
		...e,
		...Ua(e, r, n)
	}), gc(e, i);
}
function gc(e, t) {
	let { sign: n } = e, r = n === -1 ? es(e) : e, { hours: i, minutes: a } = r, o = ss(r), s = Number(o / Jn), c = Number(o % Jn);
	is(s);
	let l = kc(c, t), u = t >= 0 || !n || l;
	return (n < 0 ? "-" : "") + "P" + _c({
		Y: vc(r.years),
		M: vc(r.months),
		W: vc(r.weeks),
		D: vc(r.days)
	}) + (i || a || s || u ? "T" + _c({
		H: vc(i),
		M: vc(a),
		S: vc(s, u) + l
	}) : "");
}
function _c(e) {
	let t = [];
	for (let n in e) {
		let r = e[n];
		r && t.push(r, n);
	}
	return t.join("");
}
function vc(e, t) {
	if (!e && !t) return "";
	let n = Object.create(null);
	return n.useGrouping = 0, e.toLocaleString("fullwide", n);
}
function yc(e, t) {
	return bc(e) + "T" + Cc(e, t);
}
function bc(e) {
	return xc(e) + "-" + vn(e.day);
}
function xc(e) {
	let { year: t } = e;
	return (t < 0 || t > 9999 ? Ac(t) + _n(6, Math.abs(t)) : _n(4, t)) + "-" + vn(e.month);
}
function Sc(e) {
	return vn(e.month) + "-" + vn(e.day);
}
function Cc(e, t) {
	let n = [vn(e.hour), vn(e.minute)];
	return t !== -1 && n.push(vn(e.second) + ((e, t, n, r) => kc(e * Bn + t * zn + n, r))(e.millisecond, e.microsecond, e.nanosecond, t)), n.join(":");
}
function wc(e, t = 0) {
	if (t === 1) return "";
	let [n, r] = Cn(Math.abs(e), Un), [i, a] = Cn(r, Hn), [o, s] = Cn(a, Vn);
	return Ac(e) + vn(n) + ":" + vn(i) + (o || s ? ":" + vn(o) + kc(s) : "");
}
function Tc(e, t) {
	return t === 1 ? "" : "[" + (t === 2 ? "!" : "") + e + "]";
}
function Ec(e, t) {
	return t > 1 || t === 0 && e !== void 0 ? Dc(Mn(e), t === 2) : "";
}
function Dc(e, t) {
	return "[" + (t ? "!" : "") + "u-ca=" + e + "]";
}
var Oc = /0+$/;
function kc(e, t) {
	let n = _n(9, e);
	return n = t === void 0 ? n.replace(Oc, "") : n.slice(0, t), n ? "." + n : "";
}
function Ac(e) {
	return e < 0 ? "-" : "+";
}
var jc = /^(AC|AE|AG|AR|AS|BE|BS|CA|CN|CS|CT|EA|EC|IE|IS|JS|MI|NE|NS|PL|PN|PR|PS|SS|VS)T$/, Mc = /[^\w\/:+-]+/;
function Nc(e) {
	return Pc(Mi(e));
}
function Pc(e) {
	return Fc(e).id;
}
function Fc(e) {
	let t = e.toUpperCase(), n = ((e) => {
		let t = Ns(e, 1);
		if (t !== void 0) return {
			id: wc(t),
			Z: t,
			m: t
		};
	})(t);
	return n ? {
		kind: "fixed",
		...n
	} : Ic(t === "UTC" ? "UTC" : ((e) => (Mc.test(e) && k(Gt(e)), jc.test(e) && k("Forbidden ICU TimeZone"), e.toLowerCase().split("/").map((e, t) => (e.length <= 3 || /\d/.test(e)) && !/etc|yap/.test(e) ? e.toUpperCase() : e.replace(/baja|dumont|[a-z]+/g, (e, n) => e.length <= 2 && !t || e === "in" || e === "chat" ? e.toUpperCase() : e.length > 2 || !n ? dn(e).replace(/island|noronha|murdo|rivadavia|urville/, dn) : e)).join("/")))(e));
}
var Ic = /*@__PURE__*/ Qt((e) => {
	if (e === "UTC") return {
		kind: "utc",
		id: e,
		m: e
	};
	let t = Lc(e.toUpperCase());
	return {
		kind: "named",
		id: e,
		format: t,
		m: t.resolvedOptions().timeZone
	};
}), Lc = /*@__PURE__*/ Qt((e) => new Xs("en-u-hc-h23", {
	calendar: "iso8601",
	timeZone: e,
	era: "short",
	year: "numeric",
	month: "numeric",
	day: "numeric",
	hour: "numeric",
	minute: "numeric",
	second: "numeric"
}));
function Rc(e) {
	let t = Fc(e);
	return zc(t.id, t);
}
var zc = /*@__PURE__*/ Qt((e, t) => t.kind === "named" ? new Vc(e, t.m, t.format) : new Bc(e, t.m, t.kind === "fixed" ? t.Z : 0)), Bc = class {
	constructor(e, t, n) {
		this.id = e, this.m = t, this.Z = n;
	}
	B() {
		return this.Z;
	}
	R(e) {
		return [Ta(e, this.Z)];
	}
	C() {}
}, Vc = class {
	constructor(e, t, n) {
		this.id = e, this.m = t, this.oe = ((e, t) => {
			let n = Qt(e), r = Qt(Uc), i = 86400 * t;
			function a(e) {
				let [t, a] = Wc(e, i), s = Gc(t), c = Gc(a), l = n(s), u = n(c);
				return l === u ? l : o(r(s, c), l, u, e);
			}
			function o(t, n, r, i) {
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
				C: function e(t, a) {
					if (a > 0 && t >= 864e10) return;
					if (a < 0) {
						if (t <= $s) return;
						let n = Hc() + 94867200;
						if (t > n) return e(n, -1);
					}
					let [s, c] = Wc(a > 0 ? Math.max(t, $s) : t, i), l = i * a, u = a > 0 ? Math.max(t, Hc()) + 94867200 : $s, d = () => a < 0 ? c > u : s < u;
					for (; d();) {
						let e = Gc(s), i = Gc(c), u = n(e), d = n(i);
						if (u !== d) {
							let n = r(e, i);
							o(n, u, d);
							let s = n[0];
							if ((yn(s, t) || 1) === a) return s;
						}
						s += l, c += l;
					}
				}
			};
		})(((e) => (t) => {
			let n = Zs(e, 1e3 * t);
			return 86400 * Wr(((e) => {
				let t = e.relatedYear;
				if (t !== void 0) return parseInt(t);
				let n = parseInt(e.year);
				return e.era !== void 0 && jn(e.era) === "bce" ? 1 - n : n;
			})(n), parseInt(n.month), parseInt(n.day)) + 3600 * parseInt(n.hour) + 60 * parseInt(n.minute) + parseInt(n.second) - t;
		})(n), ((e) => Qs[e.split("/").pop()] || 60)(e));
	}
	B(e) {
		return this.oe.ze(((e) => Lr(e)[0])(e)) * Vn;
	}
	R(e) {
		let t = 86400 * Ur(e) + Mr(e), n = Nr(e);
		return this.oe.Ae(t).map((e) => wa(BigInt(e) * Jn + BigInt(n)));
	}
	C(e, t) {
		let [n, r] = Lr(e), i = this.oe.C(n + (t > 0 || r ? 1 : 0), t);
		if (i !== void 0) return BigInt(i) * Jn;
	}
};
function Hc() {
	return Math.floor(Date.now() / 1e3);
}
function Uc(e, t) {
	return [e, t];
}
function Wc(e, t) {
	let n = Math.floor(e / t) * t;
	return [n, n + t];
}
function Gc(e) {
	return Jt(e, -1e10, 864e10);
}
function Kc(e, t, n) {
	let r = I(n, Ts(t, Sr, Cr), Rs, [], 0), i = r.timeZone !== void 0, [a, o] = Vs(r, t), s = Ws(r, t, a, o, 0);
	if (i) {
		let n = As(r), i = Rc(e(r.timeZone));
		return {
			epochNanoseconds: so(i, P(s, n), r.offset),
			timeZone: i,
			calendar: t
		};
	}
	return s;
}
function qc(e, t, n, r) {
	let i = I(n, Ts(t, Sr, Cr), Rs, ir, 0), a = e(i.timeZone), [o, s] = Vs(i, t), [c, l, u] = zs(r), d = Ws(i, t, o, s, c), f = As(i, c), p = Rc(a);
	return Fa(so(p, P(d, f), i.offset, l, u), p, t);
}
function Jc(e, t, n) {
	let r = I(t, Ts(e, vr, yr), Ls, [], 0), [i, a] = Vs(r, e), o = L(n);
	return Us(Ws(r, e, i, a, o), As(r, o), e);
}
function Yc(e, t, n, r = []) {
	let i = I(t, Ts(e, gr, _r), Ps, r), [a, o] = Vs(i, e);
	return Ws(i, e, a, o, L(n));
}
function Xc(e, t, n, r) {
	let i = I(t, Ts(e, dr, fr), Ps, r), [a, o] = Vs(i, e, 1);
	return Gs(i, e, a, o, L(n));
}
function Zc(e, t, n, r) {
	let i = I(n, Ts(e, gr, _r), Ps, tr, 0);
	t && i.month !== void 0 && i.monthCode === void 0 && i.year === void 0 && (i.year = 1972);
	let [a, o] = Hs(i, e);
	return Ks(i, e, a, o, L(r));
}
function Qc(e, t) {
	return As(I(e, lr, Fs, [], 1), L(t));
}
function $c(e) {
	let t = I(e, wi, Is);
	return Ra(ns({
		...Oi,
		...t
	}));
}
function el(e) {
	k(Kt(e));
}
function tl(e) {
	let t = Ol(e = Mi(Ii(e))), n;
	return t || el(e), t.F ? n = 0 : t.offset ? n = Ms(t.offset) : el(e), t.timeZoneId && Ns(t.timeZoneId, 1), oi(t), Pa(Ta(t, n));
}
function nl(e, t) {
	let n = Ol(Mi(e));
	return n || el(e), n.timeZoneId ? _l(n, t, void 0) : (n.F && el(e), yl(n, t));
}
function rl(e, t, n) {
	let r = Ol(Mi(e));
	return r && r.timeZoneId || el(e), _l(r, t, n);
}
function il(e, t) {
	let n = Ol(Mi(e));
	return n && !n.F || el(e), vl(n, t);
}
function al(e, t) {
	let n = ml(pl(Mi(e)), void 0, t);
	return F(n, n.calendar);
}
function ol(e, t) {
	let n = kl(Mi(e));
	if (n) return sl(n), F(ba(si(n)), t(n.calendarId));
	let r = ml(pl(e), hl, t), { calendar: i } = r;
	return F(_o(i, r), i);
}
function sl(e) {
	e.calendarId !== "iso8601" && k(qt(e.calendarId));
}
function cl(e, t) {
	let n = Al(Mi(e));
	if (n) return sl(n), F(si(n), t(n.calendarId));
	let r = ml(pl(e), gl, t), { calendar: i } = r, { year: a, month: o, day: s } = M(i, r), [c, l] = ui(i, a, o), { year: u, month: d } = ((e, t, n, r) => {
		let i = e ? e.u(t, n, r) : Jr(t, n);
		return i || k("Cannot guess year"), i;
	})(i, c, l, s);
	return F(xa(fi(i, u, d, s)), i);
}
function ll(e) {
	let t = ((e) => {
		let t = jl(e);
		return t ? (Pl(t[13]), Ml(t, 1)) : void 0;
	})(e = Mi(e));
	if (!t) {
		let n = Ol(e);
		n && n.te || el(e), n.F && k(qt("Z")), sl(n), t = n;
	}
	let n;
	return (n = kl(e)) && ci(n) && el(e), (n = Al(e)) && ci(n) && el(e), La(Dr(t));
}
function ul(e) {
	let t = Dl.exec(Mi(e));
	return t || el(e), Ra(ns(((e) => {
		let t = 0, n = 0, r = 0, i = {
			years: a(e[2]),
			months: a(e[3]),
			weeks: a(e[4]),
			days: a(e[5]),
			hours: a(e[6], e[7], 5),
			minutes: a(e[8], e[9], 4),
			seconds: a(e[10], e[11], 3),
			...Jo(r, 2, N)
		};
		return t || k(Lt(N)), hn(e[1]) < 0 && (i = es(i)), i;
		function a(e, i, a) {
			let o = 0, s = 0;
			return a && ([o, r] = Cn(r, Gn[a])), e !== void 0 && (n && k(qt(e)), s = ((e) => {
				let t = parseInt(e);
				return Number.isFinite(t) || k(qt(e)), t;
			})(e), t = 1, i && (r = mn(i) * (Gn[a] / Vn), n = 1)), o + s;
		}
	})(t)));
}
function dl(e) {
	let t = Ol(e) || kl(e) || Al(e);
	if (t) return t.calendarId;
	let n = jl(e);
	return n ? Pl(n[13]).calendarId : e;
}
function fl(e) {
	let t = Ol(e);
	return t && (t.timeZoneId || t.F && "UTC" || t.offset) || e;
}
function pl(e) {
	let t = Ol(e);
	return t && !t.F || el(e), t;
}
function ml(e, t, n) {
	return t && e.calendarId === "iso8601" ? (si(e), e.te && Dr(e), yl(t(e), n)) : e.te ? vl(e, n) : yl(e, n);
}
function hl(e) {
	let t = 12 * e.year + e.month === ya ? 20 : 1;
	return {
		...e,
		day: t
	};
}
function gl(e) {
	return {
		...e,
		year: 1972
	};
}
function _l(e, t, n) {
	let r = Rc(Pc(e.timeZoneId)), i;
	if (oi(e), e.te) {
		let t = e.offset ? Ms(e.offset) : void 0, [, o, s] = zs(n);
		i = so(r, e, t, o, s, !(r.Z || e.offset === void 0 || (a = e.offset, a.replace(/\D/g, "").length > 4)), e.F);
	} else zs(n), i = lo(r, e);
	var a;
	return wa(i), Fa(i, r, t(e.calendarId));
}
function vl(e, t) {
	return oi(e), Sa(e), {
		...P(e, e),
		calendar: t(e.calendarId)
	};
}
function yl(e, t) {
	return si(e), xa(e), {
		calendar: t(e.calendarId),
		year: e.year,
		month: e.month,
		day: e.day
	};
}
function bl(e) {
	return `(\\d{2})(?:(:?)(\\d{2})(?:\\${e}(\\d{2})(?:[.,](\\d{1,9}))?)?)?`;
}
var xl = "(?:(?:([+-])(\\d{6}))|(\\d{4}))(-?)(\\d{2})\\4(\\d{2})(?:[T ]" + bl(8) + "(Z|([+-])" + bl(15) + ")?)?", Sl = /*@__PURE__*/ pn("(?:(?:([+-])(\\d{6}))|(\\d{4}))-?(\\d{2})((?:\\[(!?)([^\\]]*)\\]){0,9})"), Cl = /*@__PURE__*/ pn("(?:--)?(\\d{2})-?(\\d{2})((?:\\[(!?)([^\\]]*)\\]){0,9})"), wl = /*@__PURE__*/ pn(xl + "((?:\\[(!?)([^\\]]*)\\]){0,9})"), Tl = /*@__PURE__*/ pn("T?" + bl(2) + `(([+-])${bl(9)})?((?:\\[(!?)([^\\]]*)\\]){0,9})`), El = /*@__PURE__*/ RegExp("\\[(!?)([^\\]]*)\\]", "g"), Dl = /*@__PURE__*/ pn("([+-])?P(\\d+Y)?(\\d+M)?(\\d+W)?(\\d+D)?(?:T(?!$)(?:(\\d+)(?:[.,](\\d{1,9}))?H)?(?:(\\d+)(?:[.,](\\d{1,9}))?M)?(?:(\\d+)(?:[.,](\\d{1,9}))?S)?)?");
function Ol(e) {
	let t = wl.exec(e);
	return t ? ((e) => {
		let t = e[12], n = (t || "").toUpperCase() === "Z";
		return {
			year: Nl(e),
			month: parseInt(e[5]),
			day: parseInt(e[6]),
			...Ml(e, 7),
			...Pl(e[19]),
			te: !!e[7],
			F: n,
			offset: n ? void 0 : t
		};
	})(t) : void 0;
}
function kl(e) {
	let t = Sl.exec(e);
	if (t) return ((e) => ({
		year: Nl(e),
		month: parseInt(e[4]),
		day: 1,
		...Pl(e[5])
	}))(t);
}
function Al(e) {
	let t = Cl.exec(e);
	return t ? ((e) => ({
		year: 1972,
		month: parseInt(e[1]),
		day: parseInt(e[2]),
		...Pl(e[3])
	}))(t) : void 0;
}
function jl(e) {
	let t = Tl.exec(e);
	if (t) return t[6] && Ms(t[6]), t;
}
function Ml(e, t) {
	let n = gn(e[t + 3]);
	return {
		...Pr(mn(e[t + 4] || ""))[0],
		hour: gn(e[t]),
		minute: gn(e[t + 2]),
		second: n === 60 ? 59 : n
	};
}
function Nl(e) {
	let t = hn(e[1]), n = parseInt(e[2] || e[3]);
	return t < 0 && !n && k(qt(-0)), t * n;
}
function Pl(e) {
	let t, n, r = [];
	return e.replace(El, (e, i, a) => {
		let o = !!i, [s, c] = a.split("=").reverse();
		return c ? c === "u-ca" ? (r.push(s.toLowerCase()), t ||= o) : (o || /[A-Z]/.test(c)) && k(qt(e)) : (n && k(qt(e)), n = s), "";
	}), r.length > 1 && t && k(qt(e)), {
		timeZoneId: n,
		calendarId: r[0] || "iso8601"
	};
}
function Fl(e, t, n) {
	let r = Object.assign(Object.create(null), t);
	return Il(r, n, sr), ws(e) && (Il(r, n, or), e && e.je && Il(r, n, cr, ar)), r;
}
function Il(e, t, n, r) {
	let i = 0, a = [];
	for (let e of n) t[e] === void 0 ? a.push(e) : i = 1;
	if (Object.assign(e, t), i) for (let t of r || a) delete e[t];
}
function Ll(e, t, n) {
	let r = Ts(e, br, xr), i = ao(t), { year: a, month: o, day: s } = M(e, i), c = {
		year: a,
		monthCode: Kl(e, a, o),
		day: s,
		hour: i.hour,
		minute: i.minute,
		second: i.second,
		millisecond: i.millisecond,
		microsecond: i.microsecond,
		nanosecond: i.nanosecond,
		offset: i.offsetNanoseconds
	}, l = I(n, r, Rs), u = Fl(e, c, l);
	return [{
		...c,
		...l
	}, u];
}
function Rl(e, t, n) {
	let r = Ts(e, vr, yr), { year: i, month: a, day: o } = M(e, t), s = {
		year: i,
		monthCode: Kl(e, i, a),
		day: o,
		hour: t.hour,
		minute: t.minute,
		second: t.second,
		millisecond: t.millisecond,
		microsecond: t.microsecond,
		nanosecond: t.nanosecond
	}, c = I(n, r, Ls), l = Fl(e, s, c);
	return [{
		...s,
		...c
	}, l];
}
function zl(e, t, n) {
	let r = Ts(e, gr, _r), { year: i, month: a, day: o } = M(e, t);
	return Fl(e, {
		year: i,
		monthCode: Kl(e, i, a),
		day: o
	}, I(n, r, Ps));
}
function Bl(e, t, n) {
	let r = Ts(e, dr, fr), { year: i, month: a } = M(e, t);
	return Fl(e, {
		year: i,
		monthCode: Kl(e, i, a)
	}, I(n, r, Ps));
}
function Vl(e, t, n) {
	let r = Ts(e, gr, _r), { year: i, month: a, day: o } = M(e, t);
	return Fl(e, {
		monthCode: Kl(e, i, a),
		day: o
	}, I(n, r, Ps));
}
function Hl(e, t, n, r, i, a, o, s) {
	let { calendar: c, timeZone: l } = e;
	return Fa(so(l, P(Ws(n, c, r, i, a), kr(t, a)), t.offset, o, s), l, c);
}
function Ul(e, t, n, r, i, a) {
	return Us(Ws(t, n, r, i, a), kr(e, a), n);
}
function Wl(e, t) {
	return {
		...on(lr, e),
		...I(t, lr, Fs)
	};
}
function Gl(e, t) {
	return Ra((n = e, r = t, ns({
		...n,
		...I(r, wi, Is)
	})));
	var n, r;
}
function Kl(e, t, n) {
	let [r, i] = ui(e, t, n);
	return Fn(r, i);
}
function ql(e, t, n) {
	let [r, i] = ((e, t) => {
		let n = t((e = ua(e, "unit")).relativeTo), r = ta(e);
		return r = ji("unit", r), [r, n];
	})(n, e), a = ds(t), o = Math.max(r, a), s = i && Ko(i);
	if (!i && qo(o, s)) return Jl(t, r);
	if (i || k("Missing relativeTo"), !t.sign && (!s || r < 6)) return 0;
	let [c, l, u] = Vo(i, t, r);
	return qo(r, s) ? Jl(c, r) : ((e, t, n, r) => {
		let i = N[n], a = Ai(n, e);
		return Oa(e[i], ts(e) || 1, t, (e) => (a[i] = e, Uo(r, a)));
	})(c, l, r, u);
}
function Jl(e, t) {
	return Zn(as(e), Gn[t]);
}
function Yl(e, t, n) {
	return Fa(e.epochNanoseconds, t, n);
}
function Xl(e) {
	return Pa(e.epochNanoseconds);
}
function Zl(e) {
	return Ia(ao(e), e.calendar);
}
function Ql(e) {
	return F(ao(e), e.calendar);
}
function $l(e) {
	return La(ao(e));
}
function eu(e, t, n) {
	return Fa(wa(co(t, e, n)), t, e.calendar);
}
function tu(e, t, n) {
	let r;
	return r = n ? co(t, P(e, n)) : lo(t, P(e, Er)), Fa(r, t, e.calendar);
}
function nu(e, t, n) {
	return ou(e, on(Ts(e, pr, mr), t), I(Pt(n), tr, Ps, []));
}
function ru(e, t, n) {
	let r = Ts(e, er, ur);
	return ou(e, on(hr, t), I(Pt(n), r, Ps, []));
}
function iu(e, t) {
	let n = I(t, hr, Ps), [r, i] = Hs(n, e);
	return Ks(n, e, r, i, 0);
}
function au(e, t) {
	let n = I(t, Ts(e, pr, mr), Ps), [r, i] = Vs(n, e, 1);
	return Gs(n, e, r, i, 0);
}
function ou(e, t, n) {
	let r = Ts(e, wr, Tr), i = Fl(e, t, n);
	i = I(i, r, Ps, []);
	let [a, o] = Vs(i, e);
	return Ws(i, e, a, o, 0);
}
function su(e) {
	return Pa(wa(BigInt(Ri(e)) * qn));
}
function cu(e) {
	return Pa(wa(Li(e)));
}
function lu(e) {
	return e.timeZone = "UTC", ["full", "long"].includes(e.timeStyle) && (e.timeStyle = "medium"), e;
}
function uu(e, t) {
	return e.timeZone !== void 0 && A("Cannot specify TimeZone"), e.timeZone = t, e;
}
function du(e, t, n) {
	let r = e.resolvedOptions().calendar;
	!n && t.calendar === void 0 || Mn(t.calendar) === r || k("Mismatching Calendars");
}
function fu(e, t, n, r, i) {
	let a = new Set(e), o = new Set(t), s = new Set(n);
	return (e, t) => {
		let n, c, l = {}, u = {}, d = {}, f = 0, p = 0;
		for (let t of Object.keys(e)) {
			let r = e[t];
			r === void 0 || s.has(t) || (a.has(t) ? t === "dateStyle" ? n = r : t === "timeStyle" ? c = r : l[t] = r : t === "era" ? u[t] = r : o.has(t) ? t === "dateStyle" || t === "timeStyle" ? p = 1 : f = 1 : d[t] = r);
		}
		let m = n !== void 0, h = c !== void 0, g = m || h, _ = Object.keys(l).length > 0, v = f || p, y = _ || m || h, b = Object.keys(u).length > 0;
		(!t && v || t && v && !y || g && (_ || b || f)) && A("Invalid formatting options");
		let x = {};
		return g || y || Object.assign(x, r), Object.assign(x, l, u, d), m && (i ? Object.assign(x, i[n]) : x.dateStyle = n), h && (x.timeStyle = c), x;
	};
}
var pu = {
	year: "numeric",
	month: "numeric",
	day: "numeric"
}, mu = {
	hour: "numeric",
	minute: "numeric",
	second: "numeric"
}, hu = /*@__PURE__*/ Object.assign({}, pu, mu), gu = [
	"weekday",
	"year",
	"month",
	"day",
	"dateStyle"
], _u = [
	"dayPeriod",
	"hour",
	"minute",
	"second",
	"fractionalSecondDigits",
	"timeStyle"
], vu = /*@__PURE__*/ gu.concat(_u), yu = /*@__PURE__*/ ["weekday", "day"].concat(_u), bu = /*@__PURE__*/ ["weekday", "year"].concat(_u), xu = /*@__PURE__*/ fu(vu, [], [], hu), Su = /*@__PURE__*/ fu(vu, [], [], {
	...hu,
	timeZoneName: "short"
}), Cu = /*@__PURE__*/ fu(vu, [], ["timeZoneName"], hu), wu = /*@__PURE__*/ fu(gu, _u, ["timeZoneName"], pu), Tu = /*@__PURE__*/ fu(_u, gu, ["timeZoneName", "era"], mu), Eu = /*@__PURE__*/ fu([
	"year",
	"month",
	"dateStyle"
], yu, ["timeZoneName"], {
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
}), Du = /*@__PURE__*/ fu([
	"month",
	"day",
	"dateStyle"
], bu, ["timeZoneName", "era"], {
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
function Ou(e, t) {
	let { timeZone: n } = e, r = ao(e), { offsetNanoseconds: i } = r, a = t || Er, o;
	return o = t ? so(n, P(r, a), i, 2) : lo(n, P(r, a)), Fa(o, n, e.calendar);
}
function ku(e) {
	let t = la(ua(e, "direction"), 0);
	return t || k(Ft("direction", t)), t;
}
function Au(e) {
	let t = ju(), n = e.B(t);
	return Gr(t + BigInt(n));
}
function ju() {
	return BigInt(Date.now()) * qn;
}
function Mu() {
	return new Xs().resolvedOptions().timeZone;
}
// node_modules/temporal-polyfill/chunks/apiHelpers.js
var Nu = "PlainYearMonth", Pu = "PlainMonthDay", Fu = "PlainDate", Iu = "PlainDateTime", Lu = "PlainTime", Ru = "ZonedDateTime", zu = "Instant", Bu = "Duration";
function Vu(e, t, n, ...r) {
	return Object.defineProperties(t, $t(e)), Object.defineProperties(t.prototype, tn("Temporal." + e)), Object.defineProperties(t.prototype, nn((e) => ({
		get() {
			return e(n(this));
		},
		configurable: 1
	}), Object.assign({}, ...r))), t;
}
var Hu = un.name === "noop" ? (e) => {
	Object.defineProperty(e, "_str_", { value: e.toJSON() });
} : un;
function Uu() {
	A(Bt);
}
function Wu() {
	A(zt);
}
var Gu = {
	era(e) {
		return di(e.calendar, e).era;
	},
	eraYear(e) {
		return di(e.calendar, e).eraYear;
	},
	year(e) {
		return M(e.calendar, e).year;
	},
	month(e) {
		return M(e.calendar, e).month;
	},
	monthCode(e) {
		return hi(e.calendar, e);
	}
}, Ku = {
	era(e) {
		return di(e.calendar, e).era;
	},
	eraYear(e) {
		return di(e.calendar, e).eraYear;
	},
	year(e) {
		return M(e.calendar, e).year;
	},
	month(e) {
		return M(e.calendar, e).month;
	},
	monthCode(e) {
		return hi(e.calendar, e);
	},
	day(e) {
		return M(e.calendar, e).day;
	}
}, qu = {
	monthCode(e) {
		return hi(e.calendar, e);
	},
	day(e) {
		return M(e.calendar, e).day;
	}
}, Ju = {
	daysInMonth(e) {
		return vi(e.calendar, e);
	},
	daysInYear(e) {
		return yi(e.calendar, e);
	},
	monthsInYear(e) {
		return _i(e.calendar, e);
	},
	inLeapYear(e) {
		return gi(e.calendar, e);
	}
}, Yu = {
	dayOfWeek(e) {
		return ti(e);
	},
	dayOfYear(e) {
		return bi(e.calendar, e);
	},
	weekOfYear(e) {
		return xi(e.calendar, e);
	},
	yearOfWeek(e) {
		return Si(e.calendar, e);
	},
	daysInWeek() {
		return 7;
	},
	daysInMonth(e) {
		return vi(e.calendar, e);
	},
	daysInYear(e) {
		return yi(e.calendar, e);
	},
	monthsInYear(e) {
		return _i(e.calendar, e);
	},
	inLeapYear(e) {
		return gi(e.calendar, e);
	}
};
function Xu(e, t, n, r) {
	let [i, a, o, s] = ma(e, r, 3, 5);
	return nd(e, No(t.epochNanoseconds, n.epochNanoseconds, i, a, o, s));
}
function Zu(e, t, n, r) {
	let i = wo(t.calendar, n.calendar), [a, o, s, c] = ma(e, r, 5);
	return nd(e, Do(i, t, n, a, o, s, c));
}
function Qu(e, t, n, r) {
	let i = wo(t.calendar, n.calendar), [a, o, s, c] = ma(e, r, 6);
	return nd(e, Oo(i, t, n, a, o, s, c));
}
function $u(e, t, n, r) {
	let i = wo(t.calendar, n.calendar), [a, o, s, c] = ma(e, r, 6, 9, 6);
	return nd(e, Ao(i, t, n, a, o, s, c));
}
function ed(e, t, n, r) {
	let i = wo(t.calendar, n.calendar), [a, o, s, c] = ma(e, r, 9, 9, 8);
	return nd(e, ko(i, t, n, a, o, s, c));
}
function td(e, t, n, r) {
	let [i, a, o, s] = ma(e, r, 5, 5);
	return nd(e, jo(t, n, i, a, o, s));
}
function nd(e, t) {
	return Ra(e ? es(t) : t);
}
function rd(e, t, n) {
	let { calendar: r } = e, i = zl(r, e, t), [a, o] = Vs(i, r);
	return Ws(i, r, a, o, L(n));
}
function id(e, t, n) {
	let { calendar: r } = e, [i, a] = Rl(r, e, t), [o, s] = Vs(a, r);
	return Ul(i, a, r, o, s, L(n));
}
function ad(e, t, n) {
	let { calendar: r } = e, i = Bl(r, e, t), [a, o] = Vs(i, r, 1);
	return Gs(i, r, a, o, L(n));
}
function od(e, t, n) {
	let { calendar: r } = e, i = Vl(r, e, t), [a, o] = Hs(i, r);
	return Ks(i, r, a, o, L(n));
}
function sd(e, t, n) {
	return As(Wl(e, t), L(n));
}
function cd(e, t, n) {
	let { calendar: r } = e, [i, a] = Ll(r, e, t), [o, s] = Vs(a, r), [c, l, u] = zs(n, 2);
	return Hl(e, i, a, o, s, c, l, u);
}
function ld(e) {
	return an(Object.keys(e));
}
var ud = /*@__PURE__*/ ld($n), dd = /*@__PURE__*/ ld(Ku), fd = `${Iu}Record`, pd = `${Ru}Record`, md = `${zu}Record`, hd = /*@__PURE__*/ new WeakMap(), gd = /*@__PURE__*/ new WeakMap(), _d = /*@__PURE__*/ new WeakMap(), vd = /*@__PURE__*/ new WeakMap();
function yd(e) {
	return bd(e) || Uu();
}
function bd(e) {
	return hd.get(e);
}
function xd(e) {
	return Sd(e) || Uu();
}
function Sd(e) {
	return gd.get(e);
}
function Cd(e, t) {
	gd.set(e, t);
}
function wd(e) {
	return Td(e) || Uu();
}
function Td(e) {
	return _d.get(e);
}
function Ed(e, t) {
	_d.set(e, t);
}
function Dd(e) {
	return Od(e) || Uu();
}
function Od(e) {
	return vd.get(e);
}
function kd(e, t) {
	vd.set(e, t);
}
function Ad(e) {
	return yd(e).id;
}
function jd(e) {
	let t = yd(e).xe;
	return t || k(Wt(Ad(e), "getExotic or getAny")), t;
}
// node_modules/temporal-polyfill/chunks/funcApi-native.js
function Md(e) {
	if (e !== void 0) return Nd(e);
}
function Nd(e) {
	return jd(e), Ad(e);
}
var Pd = Dd, Fd = /*@__PURE__*/ Vu(fd, class {
	get calendarId() {
		return Pd(this).calendarId;
	}
	toJSON() {
		return Pd(this).toJSON();
	}
	valueOf() {
		return Pd(this).valueOf();
	}
}, Pd, dd, ud);
function Id(e) {
	let t = Object.create(Fd.prototype);
	return kd(t, e), Hu(t), t;
}
function Ld(e, t, n, r, i, a, o, s, c, l) {
	return Id(new ht.PlainDateTime(e, t, n, r, i, a, o, s, c, Md(l)));
}
function Rd(e, t, n) {
	return Vd(Pd(e).toZonedDateTime(t, n));
}
var zd = wd, Bd = /*@__PURE__*/ Vu(pd, class {
	get calendarId() {
		return zd(this).calendarId;
	}
	get timeZoneId() {
		return zd(this).timeZoneId;
	}
	get epochMilliseconds() {
		return zd(this).epochMilliseconds;
	}
	get epochNanoseconds() {
		return zd(this).epochNanoseconds;
	}
	toJSON() {
		return zd(this).toJSON();
	}
	valueOf() {
		return zd(this).valueOf();
	}
}, zd, dd, ud);
function Vd(e) {
	let t = Object.create(Bd.prototype);
	return Ed(t, e), Hu(t), t;
}
function Hd(e) {
	return zd(e).offsetNanoseconds;
}
var Ud = xd, Wd = /*@__PURE__*/ Vu(md, class {
	get epochMilliseconds() {
		return Ud(this).epochMilliseconds;
	}
	get epochNanoseconds() {
		return Ud(this).epochNanoseconds;
	}
	toJSON() {
		return Ud(this).toJSON();
	}
	valueOf() {
		return Ud(this).valueOf();
	}
});
function Gd(e) {
	let t = Object.create(Wd.prototype);
	return Cd(t, e), Hu(t), t;
}
function Kd(e) {
	return Gd(ht.Instant.fromEpochMilliseconds(e));
}
function qd(e, t) {
	return Vd(Ud(e).toZonedDateTimeISO(t));
}
// node_modules/temporal-polyfill/chunks/funcApi-shim.js
function Jd(e) {
	return e === void 0 ? void 0 : Yd(e);
}
function Yd(e) {
	return jd(e)();
}
var Xd = Dd, Zd = /*@__PURE__*/ Vu(fd, class {
	get calendarId() {
		return Mn(Xd(this).calendar);
	}
	toJSON() {
		return lc(Xd(this));
	}
	valueOf() {
		return Wu();
	}
}, Xd, Ku, $n);
function Qd(e) {
	let t = Object.create(Zd.prototype);
	return kd(t, e), Hu(t), t;
}
function $d(e, t, n, r = 0, i = 0, a = 0, o = 0, s = 0, c = 0, l) {
	return Qd(Ia(Sa(oi(nn(kt, {
		year: e,
		month: t,
		day: n,
		hour: r,
		minute: i,
		second: a,
		millisecond: o,
		microsecond: s,
		nanosecond: c
	}))), Jd(l)));
}
function ef(e, t, n) {
	return rf(eu(Xd(e), Rc(Nc(t)), Bs(n)));
}
var tf = wd, nf = /*@__PURE__*/ Vu(pd, class {
	get calendarId() {
		return Mn(tf(this).calendar);
	}
	get timeZoneId() {
		return tf(this).timeZone.id;
	}
	get epochMilliseconds() {
		return Rr(tf(this).epochNanoseconds);
	}
	get epochNanoseconds() {
		return tf(this).epochNanoseconds;
	}
	toJSON() {
		return sc(tf(this));
	}
	valueOf() {
		return Wu();
	}
}, af, Ku, $n);
function rf(e) {
	let t = Object.create(nf.prototype);
	return Ed(t, e), Hu(t), t;
}
function af(e) {
	let t = tf(e);
	return {
		...ao(t),
		calendar: t.calendar
	};
}
function of(e) {
	return ao(tf(e)).offsetNanoseconds;
}
Un - 1, Hn - 1, Vn - 1, Bn - 1, zn - 1;
var sf = xd, cf = /*@__PURE__*/ Vu(md, class {
	get epochMilliseconds() {
		return Rr(sf(this).epochNanoseconds);
	}
	get epochNanoseconds() {
		return sf(this).epochNanoseconds;
	}
	toJSON() {
		return ac(sf(this));
	}
	valueOf() {
		return Wu();
	}
});
function lf(e) {
	let t = Object.create(cf.prototype);
	return Cd(t, e), Hu(t), t;
}
function uf(e) {
	return lf(su(e));
}
function df(e, t) {
	return rf(Yl(sf(e), Rc(Nc(t))));
}
// node_modules/temporal-polyfill/fns/ZonedDateTime.js
var ff = ht ? Hd : of, pf = ht ? Ld : $d, mf = ht ? Rd : ef, hf = ht ? Kd : uf, gf = ht ? qd : df;
// node_modules/@full-ui/headless-calendar/index.js
function _f(e, t) {
	let n = Ff(e);
	return n[2] += t * 7, If(n);
}
function R(e, t) {
	let n = Ff(e);
	return n[2] += t, If(n);
}
function vf(e, t) {
	let n = Ff(e);
	return n[6] += t, If(n);
}
function yf(e, t) {
	return bf(e, t) / 7;
}
function bf(e, t) {
	return (t.valueOf() - e.valueOf()) / 864e5;
}
function xf(e, t) {
	return (t.valueOf() - e.valueOf()) / 36e5;
}
function Sf(e, t) {
	return (t.valueOf() - e.valueOf()) / 6e4;
}
function Cf(e, t) {
	return (t.valueOf() - e.valueOf()) / 1e3;
}
function wf(e, t) {
	let n = z(e), r = z(t);
	return {
		years: 0,
		months: 0,
		days: Math.round(bf(n, r)),
		milliseconds: t.valueOf() - r.valueOf() - (e.valueOf() - n.valueOf())
	};
}
function Tf(e, t) {
	let n = Ef(e, t);
	return n !== null && n % 7 == 0 ? n / 7 : null;
}
function Ef(e, t) {
	return Rf(e) === Rf(t) ? Math.round(bf(e, t)) : null;
}
function z(e) {
	return If([
		e.getUTCFullYear(),
		e.getUTCMonth(),
		e.getUTCDate()
	]);
}
function Df(e) {
	return If([
		e.getUTCFullYear(),
		e.getUTCMonth(),
		e.getUTCDate(),
		e.getUTCHours()
	]);
}
function Of(e) {
	return If([
		e.getUTCFullYear(),
		e.getUTCMonth(),
		e.getUTCDate(),
		e.getUTCHours(),
		e.getUTCMinutes()
	]);
}
function kf(e) {
	return If([
		e.getUTCFullYear(),
		e.getUTCMonth(),
		e.getUTCDate(),
		e.getUTCHours(),
		e.getUTCMinutes(),
		e.getUTCSeconds()
	]);
}
function Af(e, t, n) {
	let r = e.getUTCFullYear(), i = jf(e, r, t, n);
	if (i < 1) return jf(e, r - 1, t, n);
	let a = jf(e, r + 1, t, n);
	return a >= 1 ? Math.min(i, a) : i;
}
function jf(e, t, n, r) {
	let i = If([
		t,
		0,
		1 + Mf(t, n, r)
	]), a = z(e), o = Math.round(bf(i, a));
	return Math.floor(o / 7) + 1;
}
function Mf(e, t, n) {
	let r = 7 + t - n;
	return -((7 + If([
		e,
		0,
		r
	]).getUTCDay() - t) % 7) + r - 1;
}
function Nf(e) {
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
function Pf(e) {
	return new Date(e[0], e[1] || 0, e[2] == null ? 1 : e[2], e[3] || 0, e[4] || 0, e[5] || 0);
}
function Ff(e) {
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
function If(e) {
	return e.length === 1 && (e = e.concat([0])), new Date(Date.UTC(...e));
}
function Lf(e) {
	return !isNaN(e.valueOf());
}
function Rf(e) {
	return e.getUTCHours() * 1e3 * 60 * 60 + e.getUTCMinutes() * 1e3 * 60 + e.getUTCSeconds() * 1e3 + e.getUTCMilliseconds();
}
var zf = {};
function Bf(e, t) {
	zf[e] = t;
}
function Vf(e) {
	return new zf[e]();
}
Bf("gregory", class {
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
		return If(e);
	}
	markerToArray(e) {
		return Ff(e);
	}
});
function Hf(e, t) {
	let n = null, r = null;
	return e.start && (n = t.createMarker(e.start)), e.end && (r = t.createMarker(e.end)), !n && !r || n && r && r < n ? null : {
		start: n,
		end: r
	};
}
function Uf(e, t) {
	let n = [], { start: r } = t, i, a;
	for (e.sort(Wf), i = 0; i < e.length; i += 1) a = e[i], a.start > r && n.push({
		start: r,
		end: a.start
	}), a.end > r && (r = a.end);
	return r < t.end && n.push({
		start: r,
		end: t.end
	}), n;
}
function Wf(e, t) {
	return e.start.valueOf() - t.start.valueOf();
}
function Gf(e, t) {
	let { start: n, end: r } = e, i = null;
	return t.start !== null && (n = n === null ? t.start : new Date(Math.max(n.valueOf(), t.start.valueOf()))), t.end != null && (r = r === null ? t.end : new Date(Math.min(r.valueOf(), t.end.valueOf()))), (n === null || r === null || n < r) && (i = {
		start: n,
		end: r
	}), i;
}
function Kf(e, t) {
	return (e.start === null ? null : e.start.valueOf()) === (t.start === null ? null : t.start.valueOf()) && (e.end === null ? null : e.end.valueOf()) === (t.end === null ? null : t.end.valueOf());
}
function qf(e, t) {
	return (e.end === null || t.start === null || e.end > t.start) && (e.start === null || t.end === null || e.start < t.end);
}
function Jf(e, t) {
	return (e.start === null || t.start !== null && t.start >= e.start) && (e.end === null || t.end !== null && t.end <= e.end);
}
function Yf(e, t) {
	return (e.start === null || t >= e.start) && (e.end === null || t < e.end);
}
function Xf(e, t) {
	return t.start != null && e < t.start ? t.start : t.end != null && e >= t.end ? /* @__PURE__ */ new Date(t.end.valueOf() - 1) : e;
}
function Zf(e, t) {
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
function Qf(e, t, n) {
	let r = Zf(e, n.calendarSystem);
	return {
		date: r,
		start: r,
		end: t ? Zf(t, n.calendarSystem) : null,
		timeZone: n.timeZone,
		localeCodes: n.locale.codes
	};
}
function $f(e) {
	return e % 1 == 0;
}
function ep(e, t) {
	let n = String(e);
	return "000".substr(0, t - n.length) + n;
}
var tp = [
	"years",
	"months",
	"days",
	"milliseconds"
], np = /^(-?)(?:(\d+)\.)?(\d+):(\d\d)(?::(\d\d)(?:\.(\d\d\d))?)?/;
function B(e, t) {
	return typeof e == "string" ? rp(e) : typeof e == "object" && e ? ip(e) : typeof e == "number" ? ip({ [t || "milliseconds"]: e }) : null;
}
function rp(e) {
	let t = np.exec(e);
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
function ip(e) {
	let t = {
		years: e.years || e.year || 0,
		months: e.months || e.month || 0,
		days: e.days || e.day || 0,
		milliseconds: (e.hours || e.hour || 0) * 60 * 60 * 1e3 + (e.minutes || e.minute || 0) * 60 * 1e3 + (e.seconds || e.second || 0) * 1e3 + (e.milliseconds || e.millisecond || e.ms || 0)
	}, n = e.weeks || e.week;
	return n && (t.days += n * 7, t.specifiedWeeks = !0), t;
}
function ap(e, t) {
	return e.years === t.years && e.months === t.months && e.days === t.days && e.milliseconds === t.milliseconds;
}
function op(e, t) {
	return {
		years: e.years + t.years,
		months: e.months + t.months,
		days: e.days + t.days,
		milliseconds: e.milliseconds + t.milliseconds
	};
}
function sp(e, t) {
	return {
		years: e.years - t.years,
		months: e.months - t.months,
		days: e.days - t.days,
		milliseconds: e.milliseconds - t.milliseconds
	};
}
function cp(e, t) {
	return {
		years: e.years * t,
		months: e.months * t,
		days: e.days * t,
		milliseconds: e.milliseconds * t
	};
}
function lp(e) {
	return dp(e) / 365;
}
function up(e) {
	return dp(e) / 30;
}
function dp(e) {
	return fp(e) / 864e5;
}
function fp(e) {
	return e.years * 31536e6 + e.months * 2592e6 + e.days * 864e5 + e.milliseconds;
}
function pp(e, t) {
	let n = null;
	for (let r = 0; r < tp.length; r += 1) {
		let i = tp[r];
		if (t[i]) {
			let r = e[i] / t[i];
			if (!$f(r) || n !== null && n !== r) return null;
			n = r;
		} else if (e[i]) return null;
	}
	return n;
}
function mp(e) {
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
function hp(e, t, n = !1) {
	let r = e.toISOString();
	return r = r.replace(".000", ""), n && (r = r.replace("T00:00:00Z", "")), r.length > 10 && (t == null ? r = r.replace("Z", "") : t !== 0 && (r = r.replace("Z", yp(t, !0)))), r;
}
function gp(e) {
	return e.toISOString().replace(/T.*$/, "");
}
function _p(e) {
	return e.toISOString().match(/^\d{4}-\d{2}/)[0];
}
function vp(e) {
	return ep(e.getUTCHours(), 2) + ":" + ep(e.getUTCMinutes(), 2) + ":" + ep(e.getUTCSeconds(), 2);
}
function yp(e, t = !1) {
	let n = e < 0 ? "-" : "+", r = Math.abs(e), i = Math.floor(r / 60), a = Math.round(r % 60);
	return t ? `${n + ep(i, 2)}:${ep(a, 2)}` : `GMT${n}${i}${a ? `:${ep(a, 2)}` : ""}`;
}
function bp(e) {
	let t = "";
	for (let n of e) t += n.value;
	return t;
}
var xp = /^\s*(\d{4})(-?(\d{2})(-?(\d{2})([T ](\d{2}):?(\d{2})(:?(\d{2})(\.(\d+))?)?(Z|(([-+])(\d{2})(:?(\d{2}))?))?)?)?)?$/;
function Sp(e) {
	let t = xp.exec(e);
	if (t) {
		let e = new Date(Date.UTC(Number(t[1]), t[3] ? Number(t[3]) - 1 : 0, Number(t[5] || 1), Number(t[7] || 0), Number(t[8] || 0), Number(t[10] || 0), t[12] ? Number(`0.${t[12]}`) * 1e3 : 0));
		if (Lf(e)) {
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
var Cp = class {
	constructor(e) {
		this.timeZone = e.timeZone, this.calendarSystem = Vf(e.calendarSystem), this.locale = e.locale, this.weekDow = e.locale.week.dow, this.weekDoy = e.locale.week.doy, e.weekNumberCalculation === "ISO" && (this.weekDow = 1, this.weekDoy = 4), typeof e.firstDay == "number" && (this.weekDow = e.firstDay), typeof e.weekNumberCalculation == "function" && (this.weekNumberFunc = e.weekNumberCalculation), this.weekTextLong = e.weekTextLong, this.weekTextShort = e.weekTextShort ?? e.weekTextLong, this.cmdFormatter = e.cmdFormatter;
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
		return typeof e == "number" ? (t = this.timestampToMarker(e), n = e) : e instanceof Date ? (e = e.valueOf(), isNaN(e) || (t = this.timestampToMarker(e), n = e)) : Array.isArray(e) && (t = If(e)), t === null || !Lf(t) ? null : {
			marker: t,
			isTimeUnspecified: !1,
			instantMs: n
		};
	}
	parse(e) {
		let t = Sp(e);
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
		return Rf(e) === Rf(t) && n.getMarkerDay(e) === n.getMarkerDay(t) && n.getMarkerMonth(e) === n.getMarkerMonth(t) ? n.getMarkerYear(t) - n.getMarkerYear(e) : null;
	}
	diffWholeMonths(e, t) {
		let { calendarSystem: n } = this;
		return Rf(e) === Rf(t) && n.getMarkerDay(e) === n.getMarkerDay(t) ? n.getMarkerMonth(t) - n.getMarkerMonth(e) + (n.getMarkerYear(t) - n.getMarkerYear(e)) * 12 : null;
	}
	greatestWholeUnit(e, t) {
		let n = this.diffWholeYears(e, t);
		return n === null ? (n = this.diffWholeMonths(e, t), n === null ? (n = Tf(e, t), n === null ? (n = Ef(e, t), n === null ? (n = xf(e, t), $f(n) ? {
			unit: "hour",
			value: n
		} : (n = Sf(e, t), $f(n) ? {
			unit: "minute",
			value: n
		} : (n = Cf(e, t), $f(n) ? {
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
		return n.years && (r = this.diffWholeYears(e, t), r !== null) ? r / lp(n) : n.months && (r = this.diffWholeMonths(e, t), r !== null) ? r / up(n) : n.days && (r = Ef(e, t), r !== null) ? r / dp(n) : (t.valueOf() - e.valueOf()) / fp(n);
	}
	startOf(e, t) {
		return t === "year" ? this.startOfYear(e) : t === "month" ? this.startOfMonth(e) : t === "week" ? this.startOfWeek(e) : t === "day" ? z(e) : t === "hour" ? Df(e) : t === "minute" ? Of(e) : t === "second" ? kf(e) : null;
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
		return this.weekNumberFunc ? this.weekNumberFunc(this.toDate(e)) : Af(e, this.weekDow, this.weekDoy);
	}
	formatToParts(e, t, n = {}) {
		return t.formatToParts(this.toZonedInstant(e, n.instantMs), this);
	}
	formatRangeToParts(e, t, n, r = {}) {
		let { endInstantMs: i } = r;
		return r.isEndExclusive && (t = vf(t, -1), i != null && --i), n.formatRangeToParts(this.toZonedInstant(e, r.startInstantMs), this.toZonedInstant(t, i), this);
	}
	toZonedInstant(e, t) {
		return t ??= this.toDate(e).valueOf(), {
			marker: this.timestampToMarker(t),
			instantMs: t
		};
	}
	formatIso(e, t = {}) {
		let n = null;
		return t.omitTimeZoneOffset || (n = this.offsetForMarker(e)), hp(e, n, t.omitTime);
	}
	timestampToMarker(e) {
		if (this.timeZone === "local") return If(Nf(new Date(e)));
		if (this.timeZone === "UTC") return new Date(e);
		let t = gf(hf(e), this.timeZone);
		return new Date(Date.UTC(t.year, t.month - 1, t.day, t.hour, t.minute, t.second, t.millisecond));
	}
	offsetForMarker(e) {
		return this.timeZone === "local" ? -Pf(Ff(e)).getTimezoneOffset() : this.timeZone === "UTC" ? 0 : ff(mf(pf(e.getUTCFullYear(), e.getUTCMonth() + 1, e.getUTCDate(), e.getUTCHours(), e.getUTCMinutes(), e.getUTCSeconds(), e.getUTCMilliseconds()), this.timeZone)) / 6e10;
	}
	toDate(e) {
		return this.timeZone === "local" ? Pf(Ff(e)) : this.timeZone === "UTC" ? new Date(e.valueOf()) : new Date(mf(pf(e.getUTCFullYear(), e.getUTCMonth() + 1, e.getUTCDate(), e.getUTCHours(), e.getUTCMinutes(), e.getUTCSeconds(), e.getUTCMilliseconds()), this.timeZone).epochMilliseconds);
	}
}, wp = /* @__PURE__ */ new Set([
	"week",
	"meridiem",
	"omitZeroMinute",
	"omitCommas",
	"forceCommas",
	"omitTrailing",
	"weekdayJustify"
]), Tp = /([ap])\.?m\.?/i, Ep = /,/g, Dp = /\u200e/g, Op = /[\s.,]+$/, kp = /^\s+$/, Ap = class {
	constructor(e) {
		let t = {}, n = {};
		for (let r in e) wp.has(r) ? n[r] = e[r] : t[r] = e[r];
		t.timeZoneName &&= "shortOffset", this.timeZoneOnly = Object.keys(t).length === 1 && !!t.timeZoneName, this.weekOnly = !(Object.keys(t).length || !n.week), this.timeZoneOnly || (t.timeZoneName && (t.hour ||= "2-digit", t.minute ||= "2-digit"), n.omitZeroMinute && (t.second || t.fractionalSecondDigits) && delete n.omitZeroMinute), this.standardOptions = t, this.extendedOptions = n;
	}
	formatToParts(e, t) {
		let { extendedOptions: n } = this;
		if (this.timeZoneOnly) return this.getFormats(t).normalFormat.formatToParts(e.instantMs).filter((e) => e.type === "timeZoneName");
		if (this.weekOnly) return Fp(t.computeWeekNumber(e.marker), t.weekTextLong, t.weekTextShort, t.locale, n.week);
		let { normalFormat: r, zeroFormat: i } = this.getFormats(t);
		return Mp((i && !e.marker.getUTCMinutes() ? i : r).formatToParts(e.instantMs), n);
	}
	formatRangeToParts(e, t, n) {
		let { extendedOptions: r } = this;
		if (this.timeZoneOnly || this.weekOnly) return this.formatToParts(e, n).map((e) => ({
			source: e.type === "literal" ? "shared" : "startRange",
			...e
		}));
		let { normalFormat: i, zeroFormat: a } = this.getFormats(n);
		return Np((a && !e.marker.getUTCMinutes() && !t.marker.getUTCMinutes() ? a : i).formatRangeToParts(e.instantMs, t.instantMs), r);
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
function jp(e, t) {
	let n;
	for (let r of e) {
		let e = r.type === "literal";
		if (e || r.type === "dayPeriod") {
			let i = r.value;
			if (i = i.replace(Dp, ""), t.omitCommas && (i = i.replace(Ep, "")), !e) {
				let { meridiem: e } = t;
				e === !1 ? i = i.replace(Tp, "") : e === "narrow" ? i = i.replace(Tp, (e, t) => t.toLocaleLowerCase()) : e === "short" ? i = i.replace(Tp, (e, t) => `${t.toLocaleLowerCase()}m`) : e === "lowercase" && (i = i.replace(Tp, (e) => e.toLocaleLowerCase())), n && (n.value = n.value.trimEnd());
			}
			r.value = i;
		}
		n = e ? r : void 0;
	}
}
function Mp(e, t) {
	if (jp(e, t), t.weekdayJustify && e.length === 3 && kp.test(e[1].value) && e[t.weekdayJustify === "start" ? 2 : 0].type === "weekday" && e.reverse(), t.forceCommas) for (let t of e) t.type === "literal" && kp.test(t.value) && (t.value = `,${t.value}`);
	return t.omitTrailing && Pp(e), e.filter((e) => e.value);
}
function Np(e, t) {
	if (jp(e, t), t.forceCommas) for (let t of e) t.type === "literal" && kp.test(t.value) && (t.value = `,${t.value}`);
	return t.omitTrailing && Pp(e), e.filter((e) => e.value);
}
function Pp(e) {
	let t = e[e.length - 1];
	t?.type === "literal" && (t.value = t.value.replace(Op, ""), t.value || e.pop());
}
function Fp(e, t, n, r, i) {
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
var Ip = class {
	constructor(e) {
		this.cmdStr = e;
	}
	formatToParts(e, t) {
		let n = t.cmdFormatter(this.cmdStr, Qf(e, null, t));
		return Array.isArray(n) ? n : [{
			type: "literal",
			value: n
		}];
	}
	formatRangeToParts(e, t, n) {
		let r = n.cmdFormatter(this.cmdStr, Qf(e, t, n));
		return Array.isArray(r) ? r.map((e) => ({
			source: "shared",
			...e
		})) : [{
			source: "shared",
			type: "literal",
			value: r
		}];
	}
}, Lp = class {
	constructor(e) {
		this.func = e;
	}
	formatToParts(e, t) {
		return [{
			type: "literal",
			value: this.func(Qf(e, null, t))
		}];
	}
	formatRangeToParts(e, t, n) {
		return [{
			source: "shared",
			type: "literal",
			value: this.func(Qf(e, t, n))
		}];
	}
}, V = {
	popoverZ: "fc-pj",
	isolate: "fc-L2",
	borderBoxRoot: "fc-oK",
	notAllowed: "fc-Nr",
	noScrollbars: "fc-h3",
	noShrink: "fc-s2",
	calendarScreenRoot: "fc-Qd",
	safeTiles: "fc-j1",
	calendarPrintRoot: "fc-Fn",
	cursorPointer: "fc-3A",
	cursorResizeT: "fc-3Z",
	cursorResizeB: "fc-Bn",
	cursorResizeS: "fc-Bs",
	cursorResizeE: "fc-mJ",
	cursorColResizer: "fc-xg",
	hit: "fc-B6",
	hitX: "fc-If",
	hitY: "fc-uS",
	hitXSkinny: "fc-gw",
	selectNone: "fc-Qx",
	invisible: "fc-DP",
	borderless: "fc-m0",
	borderlessX: "fc-lH",
	borderlessY: "fc-T4",
	borderlessTop: "fc-7v",
	borderlessBottom: "fc-C1",
	borderlessStart: "fc-QG",
	borderlessEnd: "fc-N5",
	flexRow: "fc-4L",
	flexCol: "fc-Ir",
	grow: "fc-o7",
	liquid: "fc-bx",
	minHeight0: "fc-PC",
	liquidX: "fc-rJ",
	printTable: "fc-2n",
	noPadding: "fc-Ib",
	noPaddingY: "fc-ho",
	noMargin: "fc-Yg",
	noMarginY: "fc-4U",
	noMarginX: "fc-mC",
	whiteSpaceNoWrap: "fc-XZ",
	whiteSpacePre: "fc-5Z",
	overflowAnchorNone: "fc-0T",
	pointerEventsNone: "fc-8Y",
	crop: "fc-Zz",
	cropNowrap: "fc-Sr",
	rel: "fc-7Q",
	abs: "fc-Rs",
	start0: "fc-Hv",
	end0: "fc-97",
	fill: "fc-ou",
	fillTop: "fc-b1",
	fillX: "fc-7k",
	fillY: "fc-5V",
	fillStart: "fc-Z6",
	sticky: "fc-TI",
	stickyT: "fc-G7",
	stickyS: "fc-lC",
	tableHeaderSticky: "fc-hw",
	contentBox: "fc-Ap",
	offscreen: "fc-hI",
	srOnly: "fc-GR",
	alignCenter: "fc-Hi",
	alignStart: "fc-Yj",
	alignEnd: "fc-io",
	footerScrollbarSticky: "fc-4I",
	footerScrollbar: "fc-Nd",
	breakInsideAvoid: "fc-Gu",
	printCellContentMinHeight: "fc-2s",
	flowRoot: "fc-es",
	z0: "fc-QB",
	z1: "fc-eY",
	z2: "fc-qd",
	z3: "fc-9p",
	z4: "fc-65",
	z5: "fc-Hy",
	z1000: "fc-T7",
	z9999: "fc-mO",
	focusZ2: "fc-zL",
	internalTimelineSlot: "fc-7y",
	internalEvent: "fc-Rq",
	internalEventMirror: "fc-wf",
	internalEventDraggable: "fc-QY",
	internalEventSelected: "fc-1d",
	internalEventResizable: "fc-3d",
	internalEventResizer: "fc-bM",
	internalEventResizerStart: "fc-ik",
	internalEventResizerEnd: "fc-lw",
	internalBgEvent: "fc-Sz",
	internalMoreLink: "fc-4D",
	internalNavLink: "fc-Wa",
	internalPopover: "fc-JJ",
	internalView: "fc-Ix",
	internalScroller: "fc-0s"
};
// node_modules/fullcalendar/chunks/d74440d5.js
function H(...e) {
	return e.filter(Boolean).join(" ");
}
function Rp(e) {
	return e * 100 + "%";
}
// node_modules/fullcalendar/chunks/d7fd1889.js
function U(e) {
	return typeof e == "object" && e ? new Ap(e) : typeof e == "string" ? new Ip(e) : typeof e == "function" ? new Lp(e) : null;
}
function zp(...e) {
	console.warn("FullCalendar:", ...e);
}
var Bp = {};
function Vp(e, t) {
	return !e || typeof e == "string" ? e : (Hp(t), "");
}
function W(e, t) {
	return typeof e == "function" ? (n) => Vp(e(n), t) : Vp(e, t);
}
function Hp(e) {
	Bp[e] || (zp(`Invalid option \`${e}\`: expected a className string or a falsy value.`), Bp[e] = !0);
}
function Up(e) {
	e.preventDefault();
}
function Wp(e, t) {
	return (n) => {
		let r = n.target.closest(e);
		r && t.call(r, n, r);
	};
}
function Gp(e, t, n, r) {
	let i = Wp(n, r);
	return e.addEventListener(t, i), () => {
		e.removeEventListener(t, i);
	};
}
function Kp(e, t, n, r) {
	let i;
	return Gp(e, "mouseover", t, (e, t) => {
		if (t !== i) {
			i = t, n(e, t);
			let a = (e) => {
				i = null, r(e, t), t.removeEventListener("mouseleave", a);
			};
			t.addEventListener("mouseleave", a);
		}
	});
}
var qp = [
	"webkitTransitionEnd",
	"otransitionend",
	"oTransitionEnd",
	"msTransitionEnd",
	"transitionend"
];
function Jp(e, t) {
	let n = (r) => {
		t(r), qp.forEach((t) => {
			e.removeEventListener(t, n);
		});
	};
	qp.forEach((t) => {
		e.addEventListener(t, n);
	});
}
function Yp(e) {
	return {
		onClick: e,
		...Xp(e)
	};
}
function Xp(e) {
	return {
		tabIndex: 0,
		onKeyDown(t) {
			(t.key === "Enter" || t.key === " ") && (e(t), t.preventDefault());
		}
	};
}
var Zp = 0;
function Qp() {
	return Zp += 1, String(Zp);
}
function $p() {
	document.body.classList.add(V.notAllowed);
}
function em() {
	document.body.classList.remove(V.notAllowed);
}
function tm(e) {
	e.style.userSelect = "none", e.style.webkitUserSelect = "none", e.addEventListener("selectstart", Up);
}
function nm(e) {
	e.style.userSelect = "", e.style.webkitUserSelect = "", e.removeEventListener("selectstart", Up);
}
function rm(e) {
	e.addEventListener("contextmenu", Up);
}
function im(e) {
	e.removeEventListener("contextmenu", Up);
}
function am(e) {
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
function om(e, t, n) {
	let r, i;
	for (r = 0; r < n.length; r += 1) if (i = sm(e, t, n[r]), i) return i;
	return 0;
}
function sm(e, t, n) {
	return n.func ? n.func(e, t) : cm(e[n.field], t[n.field]) * (n.order || 1);
}
function cm(e, t) {
	return !e && !t ? 0 : t == null ? -1 : e == null ? 1 : typeof e == "string" || typeof t == "string" ? String(e).localeCompare(String(t)) : e - t;
}
function lm(e, t, n) {
	return typeof e == "function" ? e(...t) : typeof e == "string" ? t.reduce((e, t, n) => e.replace("$" + n, t || ""), e) : n;
}
function um(e, t) {
	return e - t;
}
function dm(e, t) {
	return e === t;
}
function fm(e) {
	let t = e.borderless;
	return {
		borderlessX: !!(e.borderlessX ?? t),
		borderlessTop: !!(e.borderlessTop ?? t),
		borderlessBottom: !!(e.borderlessBottom ?? t)
	};
}
function pm(e) {
	let t = [];
	for (let n of e) if (Array.isArray(n)) for (let e of n) t.push(e);
	else t.push(n);
	return t;
}
function mm(e, t) {
	let n = [];
	for (let r = 0; r < e.length; r += 1) {
		let i = t(e[r], r);
		if (Array.isArray(i)) for (let e of i) n.push(e);
		else n.push(i);
	}
	return n;
}
function hm(e, t) {
	return Array.isArray(e) && Array.isArray(t) ? gm(e, t) : e === t;
}
function gm(e, t, n = dm) {
	if (e === t) return !0;
	let r = e.length, i;
	if (r !== t.length) return !1;
	for (i = 0; i < r; i += 1) if (!n(e[i], t[i])) return !1;
	return !0;
}
var { hasOwnProperty: _m } = Object.prototype;
function vm(e, t) {
	let n = {};
	for (let r in e) t(e[r], r) && (n[r] = e[r]);
	return n;
}
function ym(e, t) {
	let n = {};
	for (let r in e) n[r] = t(e[r], r);
	return n;
}
function bm(e) {
	let t = [];
	for (let n in e) t.push(e[n]);
	return t;
}
function xm(e) {
	let t = {};
	for (let n of e) t[n] = !0;
	return t;
}
function Sm(e, t) {
	return typeof e == "object" && e && typeof t == "object" && t ? Cm(e, t, Tm) : e === t;
}
function Cm(e, t, n) {
	if (e === t) return !0;
	for (let n in e) if (_m.call(e, n) && !(n in t)) return !1;
	for (let r in t) if (_m.call(t, r) && (!(r in e) || !n(e[r], t[r], r))) return !1;
	return !0;
}
function wm(e, t) {
	return typeof e == "object" && typeof t == "object" && e && t ? Tm(e, t) : e === t;
}
function Tm(e, t) {
	return Cm(e, t, dm);
}
function Em(e, t) {
	return typeof e == "object" && typeof t == "object" && e && t ? Cm(e, t, Dm) : e === t;
}
function Dm(e, t) {
	return e instanceof Date && t instanceof Date ? e.valueOf() === t.valueOf() : Array.isArray(e) && Array.isArray(t) ? gm(e, t) : e === t;
}
function Om(e, t, n) {
	return Cm(e, t, (e, t, r) => {
		let i = n[r];
		return i ? i(e, t) : e === t;
	});
}
function km(e, t) {
	let n = [];
	for (let r in e) _m.call(e, r) && (r in t || n.push(r));
	for (let r in t) _m.call(t, r) && e[r] !== t[r] && n.push(r);
	return n;
}
function Am(e, t) {
	return e ? jm(e, t, Mm) : t;
}
function jm(e, t, n) {
	let r = {};
	for (let n in e) _m.call(e, n) && (n in t || (r[n] = e[n]));
	for (let i in t) _m.call(t, i) && (r[i] = i in e ? n(e[i], t[i]) : t[i]);
	return r;
}
function Mm(e, t) {
	return Object.assign({}, e, t);
}
var Nm = {
	navLinkDayClick: G,
	navLinkWeekClick: G,
	duration: B,
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
	defaultAllDayEventDuration: B,
	defaultTimedEventDuration: B,
	nextDayThreshold: B,
	scrollTime: B,
	scrollTimeReset: Boolean,
	slotMinTime: B,
	slotMaxTime: B,
	popoverFormat: U,
	slotDuration: B,
	snapDuration: B,
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
	nowIndicatorDotClass: Vp,
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
	eventOrder: am,
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
	weekNumberFormat: U,
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
	slotHeaderRowClass: Vp,
	slotHeaderDividerClass: W,
	dayMaxEvents: G,
	dayMaxEventRows: G,
	dayMinWidth: Number,
	slotHeaderInterval: B,
	dayHeaderClass: W,
	dayHeaderInnerClass: W,
	dayHeaderContent: G,
	dayHeaderDidMount: G,
	dayHeaderWillUnmount: G,
	dayHeaderAlign: G,
	_dayHeaderSticky: G,
	dayHeaderRowClass: Vp,
	dayHeaderDividerClass: W,
	dayRowClass: Vp,
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
	eventTimeFormat: U,
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
	dateIncrement: B,
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
	navLinkClass: Vp,
	monthStartFormat: U,
	dayCellFormat: U,
	handleCustomRendering: G,
	customRenderingMetaMap: G,
	popoverClass: Vp,
	popoverCloseClass: Vp,
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
	toolbarTitleClass: Vp,
	tableClass: W,
	tableHeaderClass: W,
	tableBodyClass: W,
	nonBusinessHoursClass: Vp,
	highlightClass: Vp,
	dayHeaders: Boolean,
	dayHeaderFormat: U,
	allDayDividerClass: Vp,
	listDaysClass: Vp,
	listDayClass: W,
	listDayFormat: Bm,
	listDayAltFormat: Bm,
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
	singleMonthTitleFormat: U,
	singleMonthDidMount: G,
	singleMonthWillUnmount: G,
	singleMonthClass: W,
	singleMonthHeaderClass: W,
	singleMonthHeaderInnerClass: W
}, Pm = {
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
}, Fm = {
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
}, Im = {
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
}, Lm = {
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
}, Rm = {
	dateIncrement: wm,
	headerToolbar: wm,
	footerToolbar: wm,
	buttons: Sm,
	plugins: hm,
	events: hm,
	eventSources: hm,
	resources: hm,
	validRange: Em,
	visibleRange: Em,
	hiddenDays: hm
};
function zm(e, t) {
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
function Bm(e) {
	return e === !1 ? null : U(e);
}
function Vm(e, t, n, r) {
	let i = {
		start: e,
		end: t
	};
	return n != null && (i.instantStartMs = n), r != null && (i.instantEndMs = r), i;
}
function Hm(e, t, n) {
	return t ?? n.toDate(e).valueOf();
}
function Um(e, t, n, r) {
	let i = t == null ? e : n.timestampToMarker(t), a = t == null ? n.offsetForMarker(e) : Math.round((i.valueOf() - t) / 6e4);
	return !r && t != null ? {
		marker: i,
		date: new Date(t),
		dateStr: hp(i, a)
	} : {
		marker: i,
		date: n.toDate(e),
		dateStr: r ? n.formatIso(e, { omitTime: r }) : hp(e, a)
	};
}
function Wm(e, t) {
	return Hm(e.start, e.instantStartMs, t);
}
function Gm(e, t) {
	return Hm(e.end, e.instantEndMs, t);
}
function Km(e, t) {
	return e.instantEndMs == null ? e.end : t.timestampToMarker(e.instantEndMs);
}
function qm(e) {
	return e.instantStartMs != null || e.instantEndMs != null;
}
function Jm(e, t, n) {
	return qm(e) || qm(t) ? Wm(e, n) < Gm(t, n) && Gm(e, n) > Wm(t, n) : qf(e, t);
}
function Ym(e, t, n) {
	if (qm(e) || qm(t)) {
		let r = e.start == null ? -Infinity : Hm(e.start, e.instantStartMs, n), i = e.end == null ? Infinity : Hm(e.end, e.instantEndMs, n);
		return r <= Wm(t, n) && i >= Gm(t, n);
	}
	return Jf(e, t);
}
function Xm(e, t, n) {
	if (e.instantMs != null && !t.years && !t.months && !t.days) {
		let r = fp(t), i = e.instantMs + r, a = n.timestampToMarker(i);
		return a > e.marker ? {
			marker: a,
			instantMs: i
		} : {
			marker: vf(e.marker, r),
			instantMs: i
		};
	}
	return { marker: n.add(e.marker, t) };
}
function Zm(e, t, n) {
	if (e.instantMs == null && t.instantMs == null) return t.marker > e.marker ? Vm(e.marker, t.marker) : null;
	let r = Hm(e.marker, e.instantMs, n), i = Hm(t.marker, t.instantMs, n);
	return i <= r ? null : Vm(e.marker, t.marker > e.marker ? t.marker : vf(e.marker, i - r), e.instantMs, t.instantMs);
}
function Qm(e, t) {
	return {
		instanceId: Qp(),
		defId: e,
		range: t
	};
}
function $m(e) {
	let t = Math.floor(bf(e.start, e.end)) || 1, n = z(e.start);
	return {
		start: n,
		end: R(n, t)
	};
}
function eh(e, t = B(0)) {
	let n = null, r = null;
	if (e.end) {
		r = z(e.end);
		let n = e.end.valueOf() - r.valueOf();
		n && n >= fp(t) && (r = R(r, 1));
	}
	return e.start && (n = z(e.start), r && r <= n && (r = R(n, 1))), {
		start: n,
		end: r
	};
}
function th(e, t, n, r) {
	return r === "year" ? B(n.diffWholeYears(e, t), "year") : r === "month" ? B(n.diffWholeMonths(e, t), "month") : wf(e, t);
}
function nh(e, t, n, r) {
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
function rh(e, t, n) {
	let { dateEnv: r, pluginHooks: i, options: a } = n, { defs: o, instances: s } = e;
	s = vm(s, (e) => !o[e.defId].recurringDef);
	for (let e in o) {
		let n = o[e];
		if (n.recurringDef) {
			let { duration: o } = n.recurringDef;
			o ||= n.allDay ? a.defaultAllDayEventDuration : a.defaultTimedEventDuration;
			let c = ih(n, o, t, r, i.recurringTypes);
			for (let t of c) {
				let n = Qm(e, {
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
function ih(e, t, n, r, i) {
	let a = i[e.recurringDef.typeId].expand(e.recurringDef.typeData, {
		start: r.subtract(n.start, t),
		end: n.end
	}, r);
	return e.allDay && (a = a.map(z)), a;
}
function ah(e, t, n, r, i, a) {
	let o = lh(), s = wh(n);
	for (let c of e) {
		let e = Sh(c, t, n, r, s, i, a);
		e && oh(e, o);
	}
	return o;
}
function oh(e, t = lh()) {
	return t.defs[e.def.defId] = e.def, e.instance && (t.instances[e.instance.instanceId] = e.instance), t;
}
function sh(e, t) {
	let n = e.instances[t];
	if (n) {
		let t = e.defs[n.defId], r = dh(e, (e) => ch(t, e));
		return r.defs[t.defId] = t, r.instances[n.instanceId] = n, r;
	}
	return lh();
}
function ch(e, t) {
	return !!(e.groupId && e.groupId === t.groupId);
}
function lh() {
	return {
		defs: {},
		instances: {}
	};
}
function uh(e, t) {
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
function dh(e, t) {
	let n = vm(e.defs, t);
	return {
		defs: n,
		instances: vm(e.instances, (e) => n[e.defId])
	};
}
function fh(e, t) {
	let { defs: n, instances: r } = e, i = {}, a = {};
	for (let e in n) t.defs[e] || (i[e] = n[e]);
	for (let e in r) !t.instances[e] && i[r[e].defId] && (a[e] = r[e]);
	return {
		defs: i,
		instances: a
	};
}
function ph(e, t) {
	return Array.isArray(e) ? ah(e, null, t, !0) : typeof e == "object" && e ? ah([e], null, t, !0) : e == null ? null : String(e);
}
var mh = {
	display: String,
	editable: Boolean,
	startEditable: Boolean,
	durationEditable: Boolean,
	constraint: G,
	overlap: G,
	allow: G,
	class: Vp,
	className: Vp,
	color: String,
	contrastColor: String
}, hh = {
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
function gh(e, t) {
	let n = ph(e.constraint, t);
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
function _h(e) {
	return e.reduce(vh, hh);
}
function vh(e, t) {
	return {
		display: t.display == null ? e.display : t.display,
		startEditable: t.startEditable == null ? e.startEditable : t.startEditable,
		durationEditable: t.durationEditable == null ? e.durationEditable : t.durationEditable,
		constraints: e.constraints.concat(t.constraints),
		overlap: typeof t.overlap == "boolean" ? t.overlap : e.overlap,
		allows: e.allows.concat(t.allows),
		color: t.color || e.color,
		contrastColor: t.contrastColor || e.contrastColor,
		className: H(e.className, t.className)
	};
}
var yh = {
	id: String,
	groupId: String,
	title: String,
	url: String,
	interactive: Boolean
}, bh = {
	start: G,
	end: G,
	date: G,
	allDay: Boolean
}, xh = {
	...yh,
	...bh,
	extendedProps: G
};
function Sh(e, t, n, r, i = wh(n), a, o) {
	let { refined: s, extra: c } = Ch(e, n, i), l = Dh(t, n), u = nh(s, l, n.dateEnv, n.pluginHooks.recurringTypes);
	if (u) {
		let e = Th(s, c, t ? t.sourceId : "", u.allDay, !!u.duration, n, a);
		return e.recurringDef = {
			typeId: u.typeId,
			typeData: u.typeData,
			duration: u.duration
		}, {
			def: e,
			instance: null
		};
	}
	let d = Eh(s, l, n, r);
	if (d) {
		let e = Th(s, c, t ? t.sourceId : "", d.allDay, d.hasEnd, n, a), r = Qm(e.defId, d.range);
		return o && e.publicId && o[e.publicId] && (r.instanceId = o[e.publicId]), {
			def: e,
			instance: r
		};
	}
	return null;
}
function Ch(e, t, n = wh(t)) {
	return zm(e, n);
}
function wh(e) {
	return {
		...mh,
		...xh,
		...e.pluginHooks.eventRefiners
	};
}
function Th(e, t, n, r, i, a, o) {
	let s = {
		title: e.title || "",
		groupId: e.groupId || "",
		publicId: e.id || "",
		url: e.url || "",
		recurringDef: null,
		defId: (o && e.id ? o[e.id] : "") || Qp(),
		sourceId: n,
		allDay: r,
		hasEnd: i,
		interactive: e.interactive,
		ui: gh(e, a),
		extendedProps: {
			...e.extendedProps || {},
			...t
		}
	};
	for (let t of a.pluginHooks.eventDefMemberAdders) Object.assign(s, t(e));
	return Object.freeze(s.ui.className), Object.freeze(s.extendedProps), s;
}
function Eh(e, t, n, r) {
	let { allDay: i } = e, a, o = null, s = !1, c, l = null, u = e.start == null ? e.date : e.start;
	if (a = n.dateEnv.createMarkerMeta(u), a) o = a.marker;
	else if (!r) return null;
	e.end != null && (c = n.dateEnv.createMarkerMeta(e.end)), i ??= t ?? ((!a || a.isTimeUnspecified) && (!c || c.isTimeUnspecified)), i && o && (o = z(o));
	let d = !i && a ? a.instantMs : void 0, f = null;
	if (c && (l = i ? z(c.marker) : c.marker, o ? i ? l > o && (f = Vm(o, l)) : f = Zm({
		marker: o,
		instantMs: d
	}, {
		marker: l,
		instantMs: c.instantMs
	}, n.dateEnv) : f = Vm(o, l, void 0, i ? void 0 : c.instantMs)), f) s = !0;
	else if (r) f = Vm(o, null, d);
	else {
		s = n.options.forceEventDuration || !1;
		let e = Xm({
			marker: o,
			instantMs: d
		}, i ? n.options.defaultAllDayEventDuration : n.options.defaultTimedEventDuration, n.dateEnv);
		f = Vm(o, e.marker, d, e.instantMs);
	}
	return {
		allDay: i,
		hasEnd: s,
		range: f
	};
}
function Dh(e, t) {
	let n = null;
	return e && (n = e.defaultAllDay), n ??= t.options.defaultAllDay, n;
}
var Oh = {
	start: G,
	end: G,
	allDay: Boolean
};
function kh(e, t, n) {
	let r = Ah(e, t);
	if (!r) return null;
	let { range: i } = r;
	if (!i.start) return null;
	if (!i.end) {
		if (n == null) return null;
		let e = Xm({
			marker: i.start,
			instantMs: r.instantStartMs
		}, n, t);
		i.end = e.marker, e.instantMs != null && (r.instantEndMs = e.instantMs);
	}
	return r;
}
function Ah(e, t) {
	let { refined: n, extra: r } = zm(e, Oh), i = n.start ? t.createMarkerMeta(n.start) : null, a = n.end ? t.createMarkerMeta(n.end) : null, { allDay: o } = n;
	o ??= i && i.isTimeUnspecified && (!a || a.isTimeUnspecified);
	let s = {
		start: i ? i.marker : null,
		end: a ? a.marker : null
	};
	if (!o && i && a && (i.instantMs != null || a.instantMs != null)) {
		let e = Zm({
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
function jh(e, t) {
	return Kf(e.range, t.range) && e.allDay === t.allDay && Mh(e, t);
}
function Mh(e, t) {
	for (let n in t) if (n !== "range" && n !== "allDay" && e[n] !== t[n]) return !1;
	for (let n in e) if (!(n in t)) return !1;
	return !0;
}
function Nh(e, t) {
	return {
		...Fh(e.range, t, e.allDay, e),
		allDay: e.allDay
	};
}
function Ph(e, t, n) {
	return {
		...Fh(e, t, n),
		timeZone: t.timeZone
	};
}
function Fh(e, t, n, r) {
	let i = r?.instantStartMs ?? e.instantStartMs, a = r?.instantEndMs ?? e.instantEndMs, o = Um(e.start, i, t, n), s = Um(e.end, a, t, n);
	return {
		start: o.date,
		end: s.date,
		startStr: o.dateStr,
		endStr: s.dateStr
	};
}
function Ih(e, t) {
	return Hm(e.range.start, e.instantStartMs, t);
}
function Lh(e, t) {
	return Hm(e.range.end, e.instantEndMs, t);
}
function Rh(e, t, n) {
	let r = Ch({ editable: !1 }, n), i = Th(r.refined, r.extra, "", e.allDay, !0, n);
	return {
		def: i,
		ui: ag(i, t),
		instance: Qm(i.defId, e.range),
		range: e.range,
		isStart: !0,
		isEnd: !0
	};
}
function zh(e, t, n) {
	n.emitter.trigger("select", {
		...Vh(e, n),
		jsEvent: t ? t.origEvent : null,
		view: n.viewApi || n.calendarApi.view
	});
}
function Bh(e, t) {
	t.emitter.trigger("unselect", {
		jsEvent: e ? e.origEvent : null,
		view: t.viewApi || t.calendarApi.view
	});
}
function Vh(e, t) {
	let n = {};
	for (let r of t.pluginHooks.dateSpanTransforms) Object.assign(n, r(e, t));
	return Object.assign(n, Nh(e, t.dateEnv)), n;
}
function Hh(e, t, n) {
	let { dateEnv: r, options: i } = n, a = t;
	return e ? (a = z(a), a = r.add(a, i.defaultAllDayEventDuration)) : a = r.add(a, i.defaultTimedEventDuration), a;
}
function Uh(e, t, n) {
	return e ? { marker: Hh(!0, t.marker, n) } : Xm(t, n.options.defaultTimedEventDuration, n.dateEnv);
}
function Wh(e, t, n, r) {
	let i = ig(e.defs, t), a = lh();
	for (let t in e.defs) {
		let o = e.defs[t];
		a.defs[t] = Gh(o, i[t], n, r);
	}
	for (let t in e.instances) {
		let o = e.instances[t], s = a.defs[o.defId];
		a.instances[t] = Kh(o, s, i[o.defId], n, r);
	}
	return a;
}
function Gh(e, t, n, r) {
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
function Kh(e, t, n, r, i) {
	let a = r.standardProps && r.standardProps.allDay === !0, o = r.standardProps && r.standardProps.hasEnd === !1, s = { ...e };
	if (a && (s.range = $m(s.range)), r.datesDelta && n.startEditable && (s.range = Jh(qh(s.range.start, s.range.instantStartMs, r.datesDelta, r.instantDatesDeltaMs, i), qh(s.range.end, s.range.instantEndMs, r.datesDelta, r.instantDatesDeltaMs, i))), r.startDelta && n.durationEditable && (s.range = Jh(qh(s.range.start, s.range.instantStartMs, r.startDelta, r.instantStartDeltaMs, i), {
		marker: s.range.end,
		instantMs: s.range.instantEndMs
	})), r.endDelta && n.durationEditable && (s.range = Jh({
		marker: s.range.start,
		instantMs: s.range.instantStartMs
	}, qh(s.range.end, s.range.instantEndMs, r.endDelta, r.instantEndDeltaMs, i))), o) {
		let e = {
			marker: s.range.start,
			instantMs: s.range.instantStartMs
		};
		s.range = Jh(e, Uh(t.allDay, e, i));
	}
	if (t.allDay && (s.range = {
		start: z(s.range.start),
		end: z(s.range.end)
	}), t.allDay) {
		if (s.range.end <= s.range.start) {
			let e = { marker: s.range.start };
			s.range = Jh(e, Uh(!0, e, i));
		}
	} else {
		let e = {
			marker: s.range.start,
			instantMs: s.range.instantStartMs
		};
		s.range = Zm(e, {
			marker: s.range.end,
			instantMs: s.range.instantEndMs
		}, i.dateEnv) ?? Jh(e, Uh(!1, e, i));
	}
	return s;
}
function qh(e, t, n, r, i) {
	if (r != null) {
		let n = Hm(e, t, i.dateEnv) + r;
		return {
			marker: i.dateEnv.timestampToMarker(n),
			instantMs: n
		};
	}
	return { marker: i.dateEnv.add(t == null ? e : i.dateEnv.timestampToMarker(t), n) };
}
function Jh(e, t) {
	return Vm(e.marker, t.marker, e.instantMs, t.instantMs);
}
var Yh = class {
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
		if (e in bh) zp(`Cannot set date-related event property \`${e}\`. Use a method instead.`);
		else if (e === "id") t = yh[e](t), this.mutate({ standardProps: { publicId: t } });
		else if (e in yh) t = yh[e](t), this.mutate({ standardProps: { [e]: t } });
		else if (e in mh) {
			let n = mh[e](t);
			n = e === "editable" ? {
				startEditable: t,
				durationEditable: t
			} : { [e]: t }, this.mutate({ standardProps: { ui: n } });
		} else zp(`Cannot set event property \`${e}\`. Use setExtendedProp instead.`);
	}
	setExtendedProp(e, t) {
		this.mutate({ extendedProps: { [e]: t } });
	}
	setStart(e, t = {}) {
		let { dateEnv: n } = this._context, r = n.createMarkerMeta(e);
		if (r && this._instance) {
			let e = this._instance.range, i = th(e.start, r.marker, n, t.granularity), a = Xh(r, Wm(e, n), t.granularity);
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
				let e = this._instance.range, i = th(Km(e, n), r.marker, n, t.granularity), a = Xh(r, Gm(e, n), t.granularity);
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
			let e = this._instance.range, t = n.allDay === !0, s = t ? void 0 : Xh(a, Wm(e, r), n.granularity), c = t || !o ? void 0 : Xh(o, Gm(e, r), n.granularity);
			n.allDay === !0 && (e = $m(e));
			let l = th(e.start, a.marker, r, n.granularity);
			if (o) {
				let t = th(Km(e, r), o.marker, r, n.granularity);
				ap(l, t) && s === c ? this.mutate({
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
		let t = B(e);
		t && this.mutate({ startDelta: t });
	}
	moveEnd(e) {
		let t = B(e);
		t && this.mutate({ endDelta: t });
	}
	moveDates(e) {
		let t = B(e);
		t && this.mutate({ datesDelta: t });
	}
	setAllDay(e, t = {}) {
		let n = { allDay: e }, { maintainDuration: r } = t;
		r ??= this._context.options.allDayMaintainDuration, this._def.allDay !== e && (n.hasEnd = r), this.mutate({ standardProps: n });
	}
	formatRange(e) {
		let { dateEnv: t } = this._context, n = this._instance, r = U(e), i = Um(n.range.start, n.range.instantStartMs, t);
		if (this._def.hasEnd) {
			let e = Um(n.range.end, n.range.instantEndMs, t);
			return bp(t.formatRangeToParts(i.marker, e.marker, r, {
				startInstantMs: i.date.valueOf(),
				endInstantMs: e.date.valueOf()
			}));
		}
		return bp(t.formatToParts(i.marker, r, { instantMs: i.date.valueOf() }));
	}
	mutate(t) {
		let n = this._instance;
		if (n) {
			let r = this._def, i = this._context, { eventStore: a } = i.getCurrentData(), o = sh(a, n.instanceId);
			o = Wh(o, { "": {
				display: "",
				startEditable: !0,
				durationEditable: !0,
				constraints: [],
				overlap: null,
				allows: [],
				color: "",
				contrastColor: "",
				className: ""
			} }, t, i);
			let s = new e(i, r, n);
			this._def = o.defs[r.defId], this._instance = o.instances[n.instanceId], i.dispatch({
				type: "MERGE_EVENTS",
				eventStore: o
			}), i.emitter.trigger("eventChange", {
				oldEvent: s,
				event: this,
				relatedEvents: Qh(o, i, n),
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
		let e = this._context, t = Zh(this);
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
		return e ? new Yh(this._context, this._context.getCurrentData().eventSources[e]) : null;
	}
	get start() {
		let e = this._instance;
		return e ? Um(e.range.start, e.range.instantStartMs, this._context.dateEnv, this._def.allDay).date : null;
	}
	get end() {
		let e = this._instance;
		return e && this._def.hasEnd ? Um(e.range.end, e.range.instantEndMs, this._context.dateEnv, this._def.allDay).date : null;
	}
	get startStr() {
		let e = this._instance;
		return e ? Um(e.range.start, e.range.instantStartMs, this._context.dateEnv, this._def.allDay).dateStr : "";
	}
	get endStr() {
		let e = this._instance;
		return e && this._def.hasEnd ? Um(e.range.end, e.range.instantEndMs, this._context.dateEnv, this._def.allDay).dateStr : "";
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
function Xh(e, t, n) {
	return e.instantMs != null && !n ? e.instantMs - t : void 0;
}
function Zh(e) {
	let t = e._def, n = e._instance;
	return {
		defs: { [t.defId]: t },
		instances: n ? { [n.instanceId]: n } : {}
	};
}
function Qh(e, t, n) {
	let { defs: r, instances: i } = e, a = [], o = n ? n.instanceId : "";
	for (let e in i) {
		let n = i[e], s = r[n.defId];
		n.instanceId !== o && a.push(new K(t, s, n));
	}
	return a;
}
function $h(e, t, n, r) {
	let i = {}, a = {}, o = {}, s = [], c = [], l = ig(e.defs, t);
	for (let t in e.defs) {
		let n = e.defs[t];
		l[n.defId].display === "inverse-background" && (n.groupId ? (i[n.groupId] = [], o[n.groupId] || (o[n.groupId] = n)) : a[t] = []);
	}
	for (let t in e.instances) {
		let o = e.instances[t], u = e.defs[o.defId], d = l[u.defId], f = o.range, p = !u.allDay && r ? eh(f, r) : f, m = Gf(p, n);
		m && (d.display === "inverse-background" ? u.groupId ? i[u.groupId].push(m) : a[o.defId].push(m) : d.display !== "none" && (d.display === "background" ? s : c).push({
			def: u,
			ui: d,
			instance: o,
			range: eg(f, p, m),
			isStart: p.start && p.start.valueOf() === m.start.valueOf(),
			isEnd: p.end && p.end.valueOf() === m.end.valueOf()
		}));
	}
	for (let e in i) {
		let t = i[e], r = Uf(t, n);
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
		let r = a[t], i = Uf(r, n);
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
function eg(e, t, n) {
	return t === e ? Vm(n.start, n.end, n.start.valueOf() === e.start.valueOf() ? e.instantStartMs : void 0, n.end.valueOf() === e.end.valueOf() ? e.instantEndMs : void 0) : n;
}
function tg(e) {
	return e.ui.display === "background" || e.ui.display === "inverse-background";
}
function ng(e, t) {
	e.fcEventRange = t;
}
function rg(e) {
	return e.fcEventRange || e.parentNode.fcEventRange || null;
}
function ig(e, t) {
	return ym(e, (e) => ag(e, t));
}
function ag(e, t) {
	let n = [], r = t[""], i = t[e.defId];
	return r && n.push(r), i && n.push(i), n.push(e.ui), _h(n);
}
function og(e, t) {
	let n = e.map(sg);
	return n.sort((e, n) => om(e, n, t)), n.map((e) => e._seg);
}
function sg(e) {
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
function cg(e, t) {
	let { pluginHooks: n } = t, r = n.isDraggableTransformers, { def: i, ui: a } = e, o = a.startEditable;
	for (let e of r) o = e(o, i, a, t);
	return o;
}
function lg(e, t, n, r, i, a, o, s = !0, c = !0) {
	let { dateEnv: l, options: u } = o, { def: d } = t, { range: f } = t.instance, p = Um(f.start, f.instantStartMs, l), m = Um(f.end, f.instantEndMs, l), { displayEventTime: h, displayEventEnd: g } = u;
	h ??= s !== !1, g ??= c !== !1;
	let _ = !i && n && z(n).valueOf() !== z(p.marker).valueOf() ? n : p.marker, v = !a && r && z(vf(r, -1)).valueOf() !== z(vf(m.marker, -1)).valueOf() ? r : m.marker, y = _ === p.marker ? p.date.valueOf() : void 0, b = v === m.marker ? m.date.valueOf() : void 0;
	if (h && !d.allDay) {
		if (g && (i || a) && d.hasEnd) {
			let t = l.formatRangeToParts(_, v, e, {
				startInstantMs: y,
				endInstantMs: b
			}), n = dg(t);
			return n == null ? bp(t) : bp(l.formatToParts(_, e, { instantMs: y })) + n + bp(l.formatToParts(v, e, { instantMs: b }));
		}
		if (i) return bp(l.formatToParts(_, e, { instantMs: y }));
	}
	return "";
}
var ug = /* @__PURE__ */ new Set([
	"year",
	"month",
	"day"
]);
function dg(e) {
	let t, n = !1;
	for (let r of e) r.source === "shared" && (t = r), ug.has(r.type) && (n = !0);
	return n ? t.value : void 0;
}
function fg(e, t, n, r) {
	let i = e.range;
	return {
		isPast: i.instantEndMs != null && r != null ? i.instantEndMs <= r : i.end <= (n || t.start),
		isFuture: i.instantStartMs != null && r != null ? i.instantStartMs >= r : i.start >= (n || t.end),
		isToday: t && Yf(t, i.start)
	};
}
function pg(e) {
	return e.instance ? e.instance.instanceId : `${e.def.defId}:${e.range.start.toISOString()}`;
}
function mg(e, t) {
	let { def: n, instance: r } = e, { url: i } = n;
	if (i) return [
		"a",
		{ href: i },
		!0
	];
	let { emitter: a, options: o } = t, { eventInteractive: s } = o;
	s ?? (s = n.interactive, s ??= !!a.hasHandlers("eventClick"));
	let c;
	return s && (c = Xp((e) => {
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
// node_modules/fullcalendar/chunks/167cc14f.js
var hg = /(^c|C)lass(Name)?$/, gg = /Content$/, _g = /(DidMount|WillUnmount)$/, vg = /^on[A-Z]/, yg = { buttons: Am };
function bg(...e) {
	let t = {};
	for (let n of e) for (let e in n) {
		let r = n[e];
		t[e] = t[e] ? xg(t[e], r) : r;
	}
	return t;
}
function xg(...e) {
	let t = {};
	for (let n of e) for (let e in n) if (e in t) {
		let r = yg[e] || (hg.test(e) ? Sg : gg.test(e) ? Cg : _g.test(e) ? wg : void 0);
		t[e] = r ? r(t[e], n[e], e) : n[e];
	} else t[e] = n[e];
	return t;
}
function Sg(e, t, n) {
	let r = typeof e == "function", i = typeof t == "function";
	if (r || i) {
		let a = (a) => H(Vp(r ? e(a) : e, n), Vp(i ? t(a) : t, n));
		return a.parts = [e, t], a;
	}
	return H(Vp(e, n), Vp(t, n));
}
function Cg(e, t) {
	if (typeof t == "function") {
		let n = (n) => {
			let r = t(n);
			return r === !0 ? typeof e == "function" ? e(n) : e : r;
		};
		return n.parts = [e, t], n;
	}
	return t ?? e;
}
function wg(e, t) {
	if (e && t) {
		let n = (...n) => {
			e(...n), t(...n);
		};
		return n.parts = [e, t], n;
	}
	return e || t;
}
function Tg(e, t) {
	let n = km(e, t);
	for (let e of n) if (!vg.test(e)) return !1;
	return !0;
}
function Eg(e, t) {
	let n = e && e.parts, r = t && t.parts;
	if (n && r) {
		let e = n.length;
		if (e !== r.length) return !1;
		for (let t = 0; t < e; t++) if (!(n[t] === r[t] || Eg(n[t], r[t]))) return !1;
		return !0;
	}
	return !1;
}
// node_modules/fullcalendar/chunks/2308e481.js
var Dg = [], Og = {
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
}, kg = {
	...Og,
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
function Ag(e) {
	let t = e.length > 0 ? e[0].code : "en", n = Dg.concat(e), r = { en: kg };
	for (let e of n) r[e.code] = e;
	return {
		map: r,
		defaultCode: t
	};
}
function jg(e, t) {
	return typeof e == "object" && !Array.isArray(e) ? Pg(e.code, [e.code], e) : Mg(e, t);
}
function Mg(e, t) {
	let n = [].concat(e || []);
	return Pg(e, n, Ng(n, t) || kg);
}
function Ng(e, t) {
	for (let n = 0; n < e.length; n += 1) {
		let r = e[n].toLocaleLowerCase().split("-");
		for (let e = r.length; e > 0; --e) {
			let n = r.slice(0, e).join("-");
			if (t[n]) return t[n];
		}
	}
	return null;
}
function Pg(e, t, n) {
	let r = xg(Og, n);
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
var Fg = class extends Error {
	constructor(e, t) {
		super(e), this.response = t;
	}
};
function Ig(e, t, n) {
	e = e.toUpperCase();
	let r = { method: e };
	return e === "GET" ? t += (t.indexOf("?") === -1 ? "?" : "&") + new URLSearchParams(n) : (r.body = new URLSearchParams(n), r.headers = { "Content-Type": "application/x-www-form-urlencoded" }), fetch(t, r).then((e) => {
		if (e.ok) return e.json().then((t) => [t, e], () => {
			throw new Fg("Failure parsing JSON", e);
		});
		throw new Fg("Request failed", e);
	});
}
function Lg(e, t) {
	t.emitter.trigger("datesSet", {
		...Ph(e.activeRange, t.dateEnv),
		view: t.viewApi
	});
}
function Rg(e, t) {
	let { emitter: n } = t;
	n.hasHandlers("eventsSet") && n.trigger("eventsSet", Qh(e, t));
}
var zg = {
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
function Bg(e, t, n) {
	let r = !1, i = function(e) {
		r || (r = !0, t(e));
	}, a = function(e) {
		r || (r = !0, n(e));
	}, o = e(i, a);
	o && typeof o.then == "function" && o.then(i, a);
}
var Vg = {
	name: "func-event-source",
	eventSourceDefs: [{
		parseMeta(e) {
			return typeof e.events == "function" ? e.events : null;
		},
		fetch(e, t, n) {
			let { dateEnv: r } = e.context, i = e.eventSource.meta;
			Bg(i.bind(null, Ph(e.range, r)), (e) => t({ rawEvents: e }), n);
		}
	}]
}, Hg = {
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
			let { meta: r } = e.eventSource, i = Ug(r, e.range, e.context);
			Ig(r.method, r.url, i).then(([e, n]) => {
				t({
					rawEvents: e,
					response: n
				});
			}, n);
		}
	}]
};
function Ug(e, t, n) {
	let { dateEnv: r, options: i } = n, a, o, s, c, l = {};
	return a = e.startParam, a ??= i.startParam, o = e.endParam, o ??= i.endParam, s = e.timeZoneParam, s ??= i.timeZoneParam, c = typeof e.extraParams == "function" ? e.extraParams() : e.extraParams || {}, Object.assign(l, c), l[a] = r.formatIso(t.start), l[o] = r.formatIso(t.end), r.timeZone !== "local" && (l[s] = r.timeZone), l;
}
var Wg = {
	name: "change-handler",
	optionChangeHandlers: {
		controller(e, t) {
			e._setApi(t.calendarApi);
		},
		events(e, t) {
			Gg([e], t);
		},
		eventSources: Gg
	}
};
function Gg(e, t) {
	let n = bm(t.getCurrentData().eventSources);
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
var Kg = {
	id: String,
	defaultAllDay: Boolean,
	url: String,
	format: String,
	events: G,
	eventDataTransform: G,
	success: G,
	failure: G
};
function qg(e, t, n = Jg(t)) {
	let r;
	if (typeof e == "string" ? r = { url: e } : typeof e == "function" || Array.isArray(e) ? r = { events: e } : typeof e == "object" && e && (r = e), r) {
		let { refined: i, extra: a } = zm(r, n), o = Yg(i, t);
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
			sourceId: Qp(),
			sourceDefId: o.sourceDefId,
			meta: o.meta,
			ui: gh(i, t),
			extendedProps: a
		};
	}
	return null;
}
function Jg(e) {
	return {
		...mh,
		...Kg,
		...e.pluginHooks.eventSourceRefiners
	};
}
function Yg(e, t) {
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
function Xg(e, t, n) {
	let r = t ? t.activeRange : null;
	return e_({}, c_(e, n), r, n);
}
function Zg(e, t, n, r) {
	let i = n ? n.activeRange : null;
	switch (t.type) {
		case "ADD_EVENT_SOURCES": return e_(e, t.sources, i, r);
		case "REMOVE_EVENT_SOURCE": return t_(e, t.sourceId);
		case "PREV":
		case "NEXT":
		case "CHANGE_DATE":
		case "CHANGE_VIEW_TYPE": return n ? n_(e, i, r) : e;
		case "FETCH_EVENT_SOURCES": return i_(e, t.sourceIds ? xm(t.sourceIds) : s_(e, r), i, t.isRefetch || !1, r);
		case "RECEIVE_EVENTS":
		case "RECEIVE_EVENT_ERROR": return o_(e, t.sourceId, t.fetchId, t.fetchRange);
		case "REMOVE_ALL_EVENT_SOURCES": return {};
		default: return e;
	}
}
function Qg(e, t, n) {
	let r = t ? t.activeRange : null;
	return i_(e, s_(e, n), r, !0, n);
}
function $g(e) {
	for (let t in e) if (e[t].isFetching) return !0;
	return !1;
}
function e_(e, t, n, r) {
	let i = {};
	for (let e of t) i[e.sourceId] = e;
	return n && (i = n_(i, n, r)), {
		...e,
		...i
	};
}
function t_(e, t) {
	return vm(e, (e) => e.sourceId !== t);
}
function n_(e, t, n) {
	return i_(e, vm(e, (e) => r_(e, t, n)), t, !1, n);
}
function r_(e, t, n) {
	return l_(e, n) ? !n.options.lazyFetching || !e.fetchRange || e.isFetching || t.start < e.fetchRange.start || t.end > e.fetchRange.end : !e.latestFetchId;
}
function i_(e, t, n, r, i) {
	let a = {};
	for (let o in e) {
		let s = e[o];
		a[o] = t[o] ? a_(s, n, r, i) : s;
	}
	return a;
}
function a_(e, t, n, r) {
	let { options: i, calendarApi: a } = r, o = r.pluginHooks.eventSourceDefs[e.sourceDefId], s = Qp();
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
		i.eventSourceFailure && (i.eventSourceFailure.call(a, n), o = !0), e.failure && (e.failure(n), o = !0), o || zp(`Unhandled event source error: ${n.message}`, n), r.dispatch({
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
function o_(e, t, n, r) {
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
function s_(e, t) {
	return vm(e, (e) => l_(e, t));
}
function c_(e, t) {
	let n = Jg(t), r = [].concat(e.eventSources || []), i = [];
	e.initialEvents && r.unshift(e.initialEvents), e.events && r.unshift(e.events);
	for (let e of r) {
		let r = qg(e, t, n);
		r && i.push(r);
	}
	return i;
}
function l_(e, t) {
	return !t.pluginHooks.eventSourceDefs[e.sourceDefId].ignoreRange;
}
var u_ = {
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
				return e.duration && (r = e.duration), !r && e.startTime && e.endTime && (r = sp(e.endTime, e.startTime)), {
					allDayGuess: !e.startTime && !e.endTime,
					duration: r,
					typeData: n
				};
			}
			return null;
		},
		expand(e, t, n) {
			let r = Gf(t, {
				start: e.startRecur,
				end: e.endRecur
			});
			return r ? d_(e.daysOfWeek, e.startTime, e.dateEnv, n, r) : [];
		}
	}],
	eventRefiners: {
		daysOfWeek: G,
		startTime: B,
		endTime: B,
		duration: B,
		startRecur: G,
		endRecur: G
	}
};
function d_(e, t, n, r, i) {
	let a = e ? xm(e) : null, o = z(i.start), s = i.end, c = [];
	for (t && (t.milliseconds < 0 ? s = R(s, 1) : t.milliseconds >= 864e5 && (o = R(o, -1))); o < s;) {
		let e;
		(!a || a[o.getUTCDay()]) && (e = t ? r.add(o, t) : o, c.push(r.createMarker(n.toDate(e)))), o = R(o, 1);
	}
	return c;
}
var f_ = [
	zg,
	Vg,
	Hg,
	u_,
	Wg,
	{
		name: "misc",
		isLoadingFuncs: [(e) => $g(e.eventSources)],
		propSetHandlers: {
			dateProfile: Lg,
			eventStore: Rg
		}
	}
], p_ = class {
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
		m_(this.handlers, e, t);
	}
	off(e, t) {
		h_(this.handlers, e, t);
	}
	trigger(e, ...t) {
		let n = this.handlers[e] || [], r = this.options && this.options[e], i = [].concat(r || [], n);
		for (let e of i) e.apply(this.thisContext, t);
	}
	hasHandlers(e) {
		return !!(this.handlers[e] && this.handlers[e].length || this.options && this.options[e]);
	}
};
function m_(e, t, n) {
	(e[t] || (e[t] = [])).push(n);
}
function h_(e, t, n) {
	n ? e[t] && (e[t] = e[t].filter((e) => e !== n)) : delete e[t];
}
function g_(e) {
	let t = e.getRootNode();
	return t instanceof Document ? t.body || t.documentElement : t;
}
function __(e) {
	return getComputedStyle(e).direction === "rtl";
}
var v_ = /(top|left|right|bottom|width|height)$/i;
function y_(e, t) {
	for (let n in t) b_(e, n, t[n]);
}
function b_(e, t, n) {
	n == null ? e.style[t] = "" : typeof n == "number" && v_.test(t) ? e.style[t] = `${n}px` : e.style[t] = n;
}
function x_(e) {
	return e.composedPath?.()[0] ?? e.target;
}
// node_modules/fullcalendar/chunks/2f45deb4.js
function q(e, t, n) {
	let r, i;
	return function(...a) {
		if (!r) i = e.apply(this, a);
		else if (!gm(r, a)) {
			n && n(i);
			let r = e.apply(this, a);
			t && t(r, i) || (i = r);
		}
		return r = a, i;
	};
}
function S_(e, t, n) {
	let r, i;
	return (a) => {
		if (!r) i = e.call(this, a);
		else if (!Tm(r, a)) {
			n && n(i);
			let r = e.call(this, a);
			t && t(r, i) || (i = r);
		}
		return r = a, i;
	};
}
var C_ = be({});
function w_(e, t, n, r, i, a, o, s, c, l, u, d, f, p) {
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
var T_ = class extends T {
	shouldComponentUpdate(e, t) {
		return !Om(this.props, e, this.propEquality) || !Om(this.state, t, this.stateEquality);
	}
};
T_.addPropsEquality = E_, T_.addStateEquality = D_, T_.contextType = C_, T_.prototype.propEquality = {}, T_.prototype.stateEquality = {};
var J = class extends T_ {};
J.contextType = C_;
function E_(e) {
	let t = Object.create(this.prototype.propEquality);
	Object.assign(t, e), this.prototype.propEquality = t;
}
function D_(e) {
	let t = Object.create(this.prototype.stateEquality);
	Object.assign(t, e), this.prototype.stateEquality = t;
}
function Y(e, t) {
	typeof e == "function" ? e(t) : e && (e.current = t);
}
var O_ = class extends J {
	constructor() {
		super(...arguments), this.id = Qp(), this.queuedDomNodes = [], this.currentDomNodes = [], this.handleEl = (e) => {
			this.el = e, this.props.elRef && Y(this.props.elRef, e);
		};
	}
	render() {
		let { props: e, context: t } = this, { options: n } = t, { customGenerator: r, defaultGenerator: i, renderProps: a } = e, o = A_(e, "", this.handleEl), s = !1, c, l = [], u;
		if (r != null) {
			let e = typeof r == "function" ? r(a) : r;
			if (e === !0) s = !0;
			else {
				let t = e && typeof e == "object";
				t && "html" in e ? o.dangerouslySetInnerHTML = { __html: e.html } : t && "domNodes" in e ? l = Array.prototype.slice.call(e.domNodes) : (t ? dt(e) : typeof e != "function") ? c = e : u = e;
			}
		} else s = !k_(e.generatorName, n);
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
		if (!gm(e, t)) {
			for (let e of t) e.remove();
			for (let t of e) n.appendChild(t);
			this.currentDomNodes = e;
		}
	}
};
O_.addPropsEquality({
	renderProps: Tm,
	attrs: Tg,
	style: Tm
});
function k_(e, t) {
	return !!(t.handleCustomRendering && e && t.customRenderingMetaMap?.[e]);
}
function A_(e, t, n) {
	let r = {
		...e.attrs,
		ref: n
	};
	return (e.className || t) && (r.className = H(t, e.className, r.className)), e.style && (r.style = e.style), r;
}
var j_ = be(0), X = class extends T {
	constructor() {
		super(...arguments), this.InnerContent = M_.bind(void 0, this), this.handleEl = (e) => {
			this.el = e, this.props.elRef && (Y(this.props.elRef, e), e && this.didMountMisfire && this.componentDidMount());
		};
	}
	render() {
		let { props: e } = this, t = Z(e.classNameGenerator, e.renderProps);
		if (e.children) {
			let n = A_(e, t, this.handleEl), r = e.children(this.InnerContent, e.renderProps, n);
			return e.tag ? x(e.tag, n, r) : r;
		}
		return x(O_, {
			...e,
			elRef: this.handleEl,
			tag: e.tag || "div",
			className: H(e.className, t),
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
X.contextType = j_;
function M_(e, t) {
	let n = e.props;
	return x(O_, {
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
function N_(e) {
	return e.text;
}
function P_(e) {
	return e.height === "auto" || e.contentHeight === "auto";
}
function F_(e) {
	let { tableHeaderSticky: t } = e;
	return (t == null || t === "auto") && (t = P_(e)), t;
}
function I_(e) {
	let t = P_(e), { footerScrollbarSticky: n } = e;
	return (n == null || n === "auto") && (n = t), !!n && t;
}
function L_(e) {
	let t = e.scrollerSyncerClass;
	if (!t) throw RangeError("Must import @fullcalendar/scrollgrid");
	return t;
}
var R_ = class {
	constructor(e) {
		this.handleChange = e, this.isMounted = !1, this.handleRefresh = () => {
			let e = this.computeTiming();
			(e.nowDate.valueOf() !== this.nowDate.valueOf() || e.nowMs !== this.nowMs) && (this.nowDate = e.nowDate, this.nowMs = e.nowMs, this.todayRange = e.todayRange, this.handleChange()), this.clearTimeout(), this.setTimeout(e.waitMs);
		}, this.handleVisibilityChange = () => {
			document.hidden || this.handleRefresh();
		};
	}
	update(e) {
		if (!this.isMounted) {
			this.isMounted = !0, this.unit = e.unit, this.unitValue = e.unitValue, this.nowIndicatorSnap = e.nowIndicatorSnap, this.nowManager = e.nowManager, this.dateEnv = e.dateEnv;
			let t = this.computeTiming();
			this.nowDate = t.nowDate, this.nowMs = t.nowMs, this.todayRange = t.todayRange, this.setTimeout(t.waitMs), this.nowManager.addResetListener(this.handleRefresh), typeof document < "u" && document.addEventListener("visibilitychange", this.handleVisibilityChange);
		} else if (e.unit !== this.unit || e.unitValue !== this.unitValue || e.nowIndicatorSnap !== this.nowIndicatorSnap || e.nowManager !== this.nowManager || e.dateEnv !== this.dateEnv) {
			this.unit = e.unit, this.unitValue = e.unitValue, this.nowIndicatorSnap = e.nowIndicatorSnap, this.nowManager = e.nowManager, this.dateEnv = e.dateEnv;
			let t = this.computeTiming();
			this.nowDate = t.nowDate, this.nowMs = t.nowMs, this.todayRange = t.todayRange, this.clearTimeout(), this.setTimeout(t.waitMs);
		}
		return {
			nowDate: this.nowDate,
			nowMs: this.nowMs,
			todayRange: this.todayRange
		};
	}
	destroy() {
		this.isMounted && (this.isMounted = !1, this.clearTimeout(), this.nowManager.removeResetListener(this.handleRefresh), typeof document < "u" && document.removeEventListener("visibilitychange", this.handleVisibilityChange));
	}
	computeTiming() {
		let { unit: e, unitValue: t, nowIndicatorSnap: n, dateEnv: r } = this, i = this.nowManager.getEpochMs(), a = r.timestampToMarker(i);
		n === "auto" && (n = /year|month|week|day/.test(e) || (t || 1) === 1);
		let o, s, c;
		return n ? (o = r.startOf(a, e), s = z_(o, i, r), c = B_(r.add(o, B(1, e)), i, r) - i) : (o = a, s = i, c = 6e4), c = Math.min(864e5, c), {
			nowDate: o,
			nowMs: s,
			todayRange: U_(o),
			waitMs: c
		};
	}
	setTimeout(e = this.computeTiming().waitMs) {
		this.timeoutId = setTimeout(() => {
			let e = this.computeTiming();
			this.nowDate = e.nowDate, this.nowMs = e.nowMs, this.todayRange = e.todayRange, this.handleChange(), this.setTimeout(e.waitMs);
		}, e);
	}
	clearTimeout() {
		this.timeoutId && clearTimeout(this.timeoutId);
	}
};
function z_(e, t, n) {
	let r = n.timestampToMarker(t).valueOf() - t, i = e.valueOf() - r;
	return n.timestampToMarker(i).valueOf() === e.valueOf() ? i : n.toDate(e).valueOf();
}
function B_(e, t, n) {
	let r = z_(e, t, n), i = V_(t, n, Math.min(r - t, 1728e5));
	return i == null ? r : Math.min(r, i);
}
function V_(e, t, n) {
	if (n <= 0) return;
	let r = H_(e, t), i = e, a = e + n;
	if (H_(a, t) !== r) {
		for (; a - i > 1;) {
			let e = Math.floor((i + a) / 2);
			H_(e, t) === r ? i = e : a = e;
		}
		return a > e ? a : void 0;
	}
}
function H_(e, t) {
	return t.timestampToMarker(e).valueOf() - e;
}
function U_(e) {
	let t = z(e);
	return {
		start: t,
		end: R(t, 1)
	};
}
function W_(e, t) {
	return e != null && (e === t || Math.abs(e - t) < .01);
}
var G_ = !0, K_ = /* @__PURE__ */ new Map(), q_ = /* @__PURE__ */ new Set(), J_ = !1, Y_ = !1, X_ = !1;
function Q(e) {
	q_.add(e), !J_ && !Y_ && (Y_ = !0, requestAnimationFrame(() => {
		Y_ = !1, Z_();
	}));
}
function Z_() {
	for (let e of q_.values()) q_.delete(e), e();
}
function Q_(e) {
	let t = J_;
	J_ = !0, X_ = !0;
	try {
		pt(e), t || pt(() => {
			Z_(), J_ = !1;
		});
	} finally {
		J_ = t, X_ = !1;
	}
}
var $_ = typeof ResizeObserver < "u" && new ResizeObserver((e) => {
	J_ = !0;
	for (let t of e) {
		let e = t.target, n = K_.get(e), r, i;
		if (t.borderBoxSize && G_) {
			let e = t.borderBoxSize[0] || t.borderBoxSize;
			r = e.inlineSize, i = e.blockSize;
		} else ({width: r, height: i} = e.getBoundingClientRect());
		let a = !1;
		W_(n.width, r) || (n.width = r, a = n.watchWidth), W_(n.height, i) || (n.height = i, a ||= n.watchHeight), a && n.callback(r, i);
	}
	pt(() => {
		Z_(), J_ = !1;
	});
});
function ev(e, t, n = !0, r = !0) {
	let i = {
		callback: t,
		watchWidth: n,
		watchHeight: r
	};
	if (K_.set(e, i), X_) {
		let { width: n, height: r } = e.getBoundingClientRect();
		i.width = n, i.height = r, t(n, r);
	}
	return $_ && $_.observe(e, { box: "border-box" }), () => {
		K_.delete(e), $_ && $_.unobserve(e);
	};
}
function tv(e, t) {
	return ev(e, t, !0);
}
function nv(e, t) {
	return ev(e, (e, n) => t(n), !1, !0);
}
var rv = class extends T {
	constructor(e, t) {
		super(e, t), this.handleChange = () => {
			this.forceUpdate();
		}, this.runner = new R_(this.handleChange);
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
	componentWillUnmount() {
		this.runner.destroy();
	}
};
rv.contextType = C_;
var iv = U({
	year: "numeric",
	month: "long",
	day: "numeric"
}), av = U({ week: "long" }), ov = U({ weekday: "long" });
function sv(e) {
	for (let t of e) if (t.type === "weekday") return t.value;
	return "";
}
function cv(e) {
	for (let t of e) if (t.type === "day") return t.value;
	return "";
}
function lv(e) {
	for (let t of e) if (t.type === "month") return t.value;
	return "";
}
function uv(e, t, n = "day") {
	return bp(e.dateEnv.formatToParts(t, n === "week" ? av : iv));
}
function dv(e, t, n = "day", r = uv(e, t, n), i = !0, a = !1) {
	let { dateEnv: o, options: s, calendarApi: c } = e, l = o.toDate(t), u = (e) => {
		let r = n === "day" ? s.navLinkDayClick : n === "week" ? s.navLinkWeekClick : null;
		typeof r == "function" ? r.call(c, o.toDate(t), e) : (typeof r == "string" && (n = r), c.zoomTo(t, n));
	};
	return {
		role: "link",
		"aria-label": a ? r : lm(s.navLinkHint, [r, l], r),
		className: H(s.navLinkClass, V.cursorPointer, V.internalNavLink),
		...i ? Yp(u) : { onClick: u }
	};
}
function fv(e, t, n, r, i) {
	let a = !(!n || n.activeRange && Yf(n.activeRange, e));
	return {
		date: t.toDate(e),
		dow: e.getUTCDay(),
		isDisabled: a,
		isOther: !a && !(!n || Yf(n.currentRange, e)),
		isToday: !a && !!(r && Yf(r, e)),
		isPast: !a && !!(i ? e < i : r && e < r.start),
		isFuture: !a && !!(i ? e > i : r && e >= r.end)
	};
}
var pv = class extends J {
	constructor() {
		super(...arguments), this.refineRenderProps = S_(mv);
	}
	render() {
		let { props: e, context: t } = this, { options: n, viewSpec: r } = t, i = this.refineRenderProps({
			...fm(n),
			options: {
				headerToolbar: n.headerToolbar,
				footerToolbar: n.footerToolbar
			},
			isHeightAuto: P_(n),
			viewApi: t.viewApi
		});
		return O(X, {
			elRef: e.elRef,
			tag: e.tag || "div",
			attrs: e.attrs,
			style: e.style,
			className: H(e.className, Z(n.viewClass, i), Z(r.optionDefaults.class, i), Z(r.optionDefaults.className, i), Z(r.optionOverrides.class, i), Z(r.optionOverrides.className, i)),
			renderProps: i,
			generatorName: void 0,
			didMount: n.didMount || n.viewDidMount,
			willUnmount: n.willUnmount || n.viewWillUnmount,
			children: () => e.children
		});
	}
};
function mv(e) {
	return {
		view: e.viewApi,
		borderlessX: e.borderlessX,
		borderlessTop: e.borderlessTop,
		borderlessBottom: e.borderlessBottom,
		options: e.options,
		isHeightAuto: e.isHeightAuto
	};
}
var hv = class extends J {
	constructor() {
		super(...arguments), this.uid = Qp();
	}
	prepareHits() {}
	queryHit(e, t, n, r, i) {
		return null;
	}
	isValidSegDownEl(e) {
		return !this.props.eventDrag && !this.props.eventResize && !e.closest(`.${V.internalEventMirror}`);
	}
	isValidDateDownEl(e) {
		return !e.closest(`.${V.internalEvent}:not(.${V.internalBgEvent})`) && !e.closest(`.${V.internalMoreLink}`) && !e.closest(`.${V.internalNavLink}`) && !e.closest(`.${V.internalPopover}`);
	}
}, gv = class {
	constructor(e) {
		this.drainedOption = e, this.isRunning = !1, this.isDirty = !1, this.pauseDepths = {}, this.timeoutId = 0;
	}
	request(e) {
		this.isDirty = !0, this.isPaused() || (this.clearTimeout(), e == null ? this.tryDrain() : this.timeoutId = setTimeout(this.tryDrain.bind(this), e));
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
		this.timeoutId &&= (clearTimeout(this.timeoutId), 0);
	}
	drained() {
		this.drainedOption && this.drainedOption();
	}
}, _v = class {
	constructor(e) {
		this.el = e, this.emitter = new p_(), this.isScroll = !1, this.isScrollRecent = !1, this.isWheelRecent = !1, this.isMouseDown = !1, this.isTouchDown = !1, this.isMouse = !1, this.isTouch = !1, this.isWheel = !1, this.handleScroll = () => {
			this.isScrollRecent = !0, this.isMouseDown && (this.isMouse = !0), this.isTouchDown && (this.isTouch = !0), this.isWheelRecent && (this.isWheel = !0), this.startScroll(), this.emitter.trigger("scroll", this.getIsDevice()), this.scrollWaiter.request(500);
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
		}, this.wheelWaiter = new gv(this.handleWheelWait), this.scrollWaiter = new gv(this.handleScrollWait), e.addEventListener("scroll", this.handleScroll, { passive: !0 }), e.addEventListener("wheel", this.handleWheel, { passive: !0 }), e.addEventListener("mousedown", this.handleMouseDown), e.addEventListener("mouseup", this.handleMouseUp), e.addEventListener("touchstart", this.handleTouchStart, { passive: !0 }), e.addEventListener("touchend", this.handleTouchEnd);
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
}, vv = class extends hv {
	constructor() {
		super(...arguments), this.handleEl = (e) => {
			this.el && (this.el = null, this._isUnmounting = !0, this.listener.destroy()), e && (this.el = e, this._isUnmounting = !1, this.listener = new _v(e));
		}, this.handleHRuler = (e) => {
			this.disconnectHRuler && (this.disconnectHRuler(), this.disconnectHRuler = void 0, this.clientWidth !== void 0 && (this.clientWidth = void 0, Y(this.props.clientWidthRef, null))), e && (this.disconnectHRuler = tv(e, (e) => {
				this._isUnmounting || e !== this.clientWidth && (this.clientWidth = e, Y(this.props.clientWidthRef, e));
			}));
		}, this.handleVRuler = (e) => {
			this.disconnectVRuler && (this.disconnectVRuler(), this.disconnectVRuler = void 0, this.clientHeight !== void 0 && (this.clientHeight = void 0, Y(this.props.clientHeightRef, null))), e && (this.disconnectVRuler = nv(e, (e) => {
				if (this._isUnmounting) return;
				e !== this.clientHeight && (this.clientHeight = e, Y(this.props.clientHeightRef, e));
				let t = Math.round(this.el.getBoundingClientRect().height - e);
				t !== this.bottomScrollbarWidth && (this.bottomScrollbarWidth = t, Y(this.props.bottomScrollbarWidthRef, t));
			}));
		};
	}
	render() {
		let { props: e } = this, t = e.horizontal || e.vertical ? "hidden" : "";
		return O("div", {
			ref: this.handleEl,
			className: H(e.className, V.noPadding, V.rel, e.hideScrollbars && V.noScrollbars, V.internalScroller),
			style: {
				...e.style,
				overflowX: e.horizontal ? "auto" : t,
				overflowY: e.vertical ? "auto" : t
			},
			children: [
				e.children,
				!!e.clientWidthRef && O("div", {
					ref: this.handleHRuler,
					className: V.fillTop
				}),
				!!(e.clientHeightRef || e.bottomScrollbarWidthRef) && O("div", {
					ref: this.handleVRuler,
					className: V.fillStart
				})
			]
		});
	}
	endScroll() {
		this.listener.endScroll();
	}
	get x() {
		let { el: e } = this;
		return e ? yv(e) : 0;
	}
	get y() {
		let { el: e } = this;
		return e ? e.scrollTop : 0;
	}
	scrollTo({ x: e, y: t }) {
		let { el: n } = this;
		n && (t != null && (n.scrollTop = t), e != null && bv(n, e));
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
function yv(e) {
	let { scrollLeft: t } = e;
	return __(e) ? xv(t, e) : t;
}
function bv(e, t) {
	e.scrollLeft = __(e) ? Sv(t, e) : t;
}
function xv(e, t) {
	switch (wv()) {
		case "positive": return t.scrollWidth - t.clientWidth - e;
		case "negative": return -e;
	}
	return e;
}
function Sv(e, t) {
	switch (wv()) {
		case "positive": return t.scrollWidth - t.clientWidth - e;
		case "negative": return -e;
	}
	return e;
}
var Cv;
function wv() {
	return Cv ||= Tv();
}
function Tv() {
	let e = document.createElement("div");
	e.style.position = "absolute", e.style.top = "-1000px", e.style.width = "100px", e.style.height = "100px", e.style.overflow = "scroll", e.style.direction = "rtl";
	let t = document.createElement("div");
	t.style.width = "200px", t.style.height = "200px", e.appendChild(t), document.body.appendChild(e);
	let n;
	return e.scrollLeft > 0 ? n = "positive" : (e.scrollLeft = 50, n = e.scrollLeft > 0 ? "reverse" : "negative"), e.remove(), n;
}
var Ev = class extends J {
	constructor() {
		super(...arguments), this.buildPublicEvent = q((e, t, n) => new K(e, t, n)), this.handleEl = (e) => {
			this.el = e, Y(this.props.elRef, e), e && ng(e, this.props.eventRange);
		};
	}
	render() {
		let { props: e, context: t } = this, { options: n } = t, { eventRange: r } = e, i = r.ui, a = n.eventTimeFormat || e.defaultTimeFormat, o = e.forcedTimeText ?? lg(a, r, e.slicedStart, e.slicedEnd, e.isStart, e.isEnd, t, e.defaultDisplayEventTime, e.defaultDisplayEventEnd), [s, c, l] = mg(r, t), u = this.buildPublicEvent(t, r.def, r.instance), d = !e.disableDragging && cg(r, t), f = /row|column/.test(e.display), p = {
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
			timeClass: H(Z(n.eventTimeClass, p), f && Z(n.blockEventTimeClass, p), e.display === "row" && Z(n.rowEventTimeClass, p), e.display === "column" && Z(n.columnEventTimeClass, p), e.display === "list-item" && Z(n.listItemEventTimeClass, p)),
			titleClass: H(Z(n.eventTitleClass, p), f && Z(n.blockEventTitleClass, p), e.display === "row" && Z(n.rowEventTitleClass, p), e.display === "column" && Z(n.columnEventTitleClass, p), e.display === "list-item" && Z(n.listItemEventTitleClass, p), e.display === "row" && n.rowEventTitleSticky && V.stickyS, e.display === "column" && n.columnEventTitleSticky && V.stickyT),
			options: { eventOverlap: !!n.eventOverlap }
		}, h = H(f && Z(n.blockEventClass, m), e.display === "row" && Z(n.rowEventClass, m), e.display === "column" && Z(n.columnEventClass, m), e.display === "list-item" && Z(n.listItemEventClass, m), i.className, e.className, e.display === "column" ? V.flexCol : V.flexRow, (r.def.url || d) && V.cursorPointer, V.internalEvent, e.isMirror && V.internalEventMirror, d && V.internalEventDraggable, m.isSelected && V.internalEventSelected, (m.isStartResizable || m.isEndResizable) && V.internalEventResizable), g = H(Z(n.eventBeforeClass, m), f && Z(n.blockEventBeforeClass, m), e.display === "row" && Z(n.rowEventBeforeClass, m), e.display === "column" && Z(n.columnEventBeforeClass, m), e.display === "list-item" && Z(n.listItemEventBeforeClass, m)), _ = H(Z(n.eventAfterClass, m), f && Z(n.blockEventAfterClass, m), e.display === "row" && Z(n.rowEventAfterClass, m), e.display === "column" && Z(n.columnEventAfterClass, m), e.display === "list-item" && Z(n.listItemEventAfterClass, m)), v = H(Z(n.eventInnerClass, m), f && Z(n.blockEventInnerClass, m), e.display === "row" && Z(n.rowEventInnerClass, m), e.display === "column" && Z(n.columnEventInnerClass, m), e.display === "list-item" && Z(n.listItemEventInnerClass, m), !e.disableLiquid && V.liquid), y = e.display === "row" && n.rowEventBeforeContent, b = e.display === "row" && n.rowEventAfterContent;
		return O(X, {
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
			defaultGenerator: Dv,
			classNameGenerator: n.eventClass,
			didMount: n.eventDidMount,
			willUnmount: n.eventWillUnmount,
			children: (t) => O(w, { children: [
				!!(m.isSelected && f) && O("div", { className: e.display === "column" ? V.hitX : V.hitY }),
				(g || y) && O("div", {
					className: H(g, !e.disableZindexes && V.z1, m.isStartResizable && H(e.display === "column" ? V.cursorResizeT : V.cursorResizeS, V.internalEventResizer, V.internalEventResizerStart)),
					children: [y && O(X, {
						tag: "div",
						style: { display: "contents" },
						attrs: { "aria-hidden": !0 },
						renderProps: m,
						generatorName: void 0,
						customGenerator: y
					}), !!(m.isStartResizable && m.isSelected) && O("div", { className: V.hit })]
				}),
				O(t, {
					tag: "div",
					className: H(v, !e.disableZindexes && V.z0)
				}),
				(_ || b) && O("div", {
					className: H(_, !e.disableZindexes && V.z1, m.isEndResizable && H(e.display === "column" ? V.cursorResizeB : V.cursorResizeE, V.internalEventResizer, V.internalEventResizerEnd)),
					children: [b && O(X, {
						tag: "div",
						style: { display: "contents" },
						attrs: { "aria-hidden": !0 },
						renderProps: m,
						generatorName: void 0,
						customGenerator: b
					}), !!(m.isEndResizable && m.isSelected) && O("div", { className: V.hit })]
				})
			] })
		});
	}
	componentDidUpdate(e) {
		this.el && this.props.eventRange !== e.eventRange && ng(this.el, this.props.eventRange);
	}
};
Ev.addPropsEquality({ seg: Tm });
function Dv(e) {
	return O(w, { children: [e.timeText && O("div", {
		className: e.timeClass,
		children: e.timeText
	}), O("div", {
		className: e.titleClass,
		children: e.event.title || O(w, { children: "\xA0" })
	})] });
}
// node_modules/fullcalendar/chunks/4ddf07a5.js
var Ov = class {
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
		return a = this.buildValidRange(t), a = this.trimHiddenDays(a), r && (e = Xf(e, a)), o = this.buildCurrentRangeInfo(e, n), s = /^(year|month|week|day)$/.test(o.unit), c = this.buildRenderRange(this.trimHiddenDays(o.range), o.unit, s), c = this.trimHiddenDays(c), l = c, i.showNonCurrentDates || (l = Gf(l, o.range)), l = this.adjustActiveRange(l), l = Gf(l, a), u = qf(o.range, a), Yf(c, e) || (e = c.start), {
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
		return n.duration ? (r = n.duration, i = n.durationUnit, a = this.buildRangeFromDuration(e, t, r, i)) : (o = this.props.dayCount) ? (i = "day", a = this.buildRangeFromDayCount(e, t, o)) : (a = this.buildCustomVisibleRange(e)) ? i = n.dateEnv.greatestWholeUnit(a.start, a.end).unit : (r = this.getFallbackDuration(), i = mp(r).unit, a = this.buildRangeFromDuration(e, t, r, i)), {
			duration: r,
			unit: i,
			range: a
		};
	}
	getFallbackDuration() {
		return B({ day: 1 });
	}
	adjustActiveRange(e) {
		let { dateEnv: t, usesMinMaxTime: n, slotMinTime: r, slotMaxTime: i } = this.props, { start: a, end: o } = e;
		return n && (dp(r) < 0 && (a = z(a), a = t.add(a, r)), dp(i) > 1 && (o = z(o), o = R(o, -1), o = t.add(o, i))), {
			start: a,
			end: o
		};
	}
	buildRangeFromDuration(e, t, n, r) {
		let { dateEnv: i, dateAlignment: a } = this.props, o, s, c;
		if (!a) {
			let { dateIncrement: e } = this.props;
			a = e && fp(e) < fp(n) ? mp(e).unit : r;
		}
		dp(n) <= 1 && this.isHiddenDay(o) && (o = this.skipHiddenDays(o, t), o = z(o));
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
		i && (o = r.startOf(o, i)), o = z(o), o = this.skipHiddenDays(o, t), s = o;
		do
			s = R(s, 1), this.isHiddenDay(s) || (a += 1);
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
		return t || ((n = this.props.dateAlignment) ? B(1, n) : e || B({ days: 1 }));
	}
	refineRange(e) {
		if (e) {
			let t = Hf(e, this.props.dateEnv);
			return t &&= eh(t), t;
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
		for (; this.isHiddenDayHash[(e.getUTCDay() + (n ? t : 0) + 7) % 7];) e = R(e, t);
		return e;
	}
};
function kv(e, t) {
	let { currentRange: n } = e;
	if (e.currentRangeUnit === "year") return t.diffWholeYears(n.start, n.end) > 1 ? "year" : "month";
	if (e.currentRangeUnit === "month") {
		if (t.diffWholeMonths(n.start, n.end) > 1) return "month";
	} else if (e.currentRangeUnit === "week") {
		if (Tf(n.start, n.end) > 1) return "week";
	} else if (e.currentRangeUnit === "day" && Ef(n.start, n.end) > 1) return "day";
}
function Av(e, t, n) {
	if (e.valueOf() === z(e).valueOf()) {
		if (t === "year") return !n.getMonth(e) && n.getDay(e) === 1;
		if (t === "month") return n.getDay(e) === 1;
		if (t === "week") return e.getUTCDay() === n.weekDow;
		if (t === "day") return !0;
	}
	return !1;
}
// node_modules/fullcalendar/chunks/8719026f.js
function jv(e, t, n, r, i) {
	switch (t.type) {
		case "RECEIVE_EVENTS": return Mv(e, n[t.sourceId], t.fetchId, t.fetchRange, t.rawEvents, i);
		case "RESET_RAW_EVENTS": return Nv(e, n[t.sourceId], t.rawEvents, r.activeRange, i);
		case "ADD_EVENTS": return Iv(e, t.eventStore, r ? r.activeRange : null, i);
		case "RESET_EVENTS": return t.eventStore;
		case "MERGE_EVENTS": return uh(e, t.eventStore);
		case "PREV":
		case "NEXT":
		case "CHANGE_DATE":
		case "CHANGE_VIEW_TYPE": return r ? rh(e, r.activeRange, i) : e;
		case "REMOVE_EVENTS": return fh(e, t.eventStore);
		case "REMOVE_EVENT_SOURCE": return Rv(e, t.sourceId);
		case "REMOVE_ALL_EVENT_SOURCES": return dh(e, (e) => !e.sourceId);
		case "REMOVE_ALL_EVENTS": return lh();
		default: return e;
	}
}
function Mv(e, t, n, r, i, a) {
	if (t && n === t.latestFetchId) {
		let n = ah(Pv(i, t, a), t, a);
		return r && (n = rh(n, r, a)), uh(Rv(e, t.sourceId), n);
	}
	return e;
}
function Nv(e, t, n, r, i) {
	let { defIdMap: a, instanceIdMap: o } = Bv(e);
	return rh(ah(Pv(n, t, i), t, i, !1, a, o), r, i);
}
function Pv(e, t, n) {
	let r = n.options.eventDataTransform, i = t ? t.eventDataTransform : null;
	return i && (e = Fv(e, i)), r && (e = Fv(e, r)), e;
}
function Fv(e, t) {
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
function Iv(e, t, n, r) {
	return n && (t = rh(t, n, r)), uh(e, t);
}
function Lv(e, t, n) {
	let { defs: r } = e;
	return {
		defs: r,
		instances: ym(e.instances, (e) => {
			if (r[e.defId].allDay) return e;
			let { instantStartMs: i, instantEndMs: a } = e.range, o = i == null ? n.createMarker(t.toDate(e.range.start)) : n.timestampToMarker(i), s = a == null ? n.createMarker(t.toDate(e.range.end)) : n.timestampToMarker(a);
			return {
				...e,
				range: Zm({
					marker: o,
					instantMs: i
				}, {
					marker: s,
					instantMs: a
				}, n) ?? Vm(o, vf(o, Gm(e.range, t) - Wm(e.range, t)), i)
			};
		})
	};
}
function Rv(e, t) {
	return dh(e, (e) => e.sourceId !== t);
}
function zv(e, t) {
	return {
		defs: e.defs,
		instances: vm(e.instances, (e) => !t[e.instanceId])
	};
}
function Bv(e) {
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
var Vv = class {
	constructor(e) {
		this.component = e.component, this.isHitComboAllowed = e.isHitComboAllowed || null;
	}
	destroy() {}
};
function Hv(e, t) {
	return {
		component: e,
		el: t.el,
		useEventCenter: t.useEventCenter == null || t.useEventCenter,
		isHitComboAllowed: t.isHitComboAllowed || null
	};
}
function Uv(e) {
	return { [e.component.uid]: e };
}
var Wv = {};
// node_modules/fullcalendar/chunks/0b82b05c.js
function Gv(e) {
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
function Kv(e, t) {
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
				let a = n[e] = Gv(t);
				r = Jv(r, a), i(t.deps || []);
			}
		}
	}
	return e && i(e), i(t), r;
}
function qv() {
	let e = [], t = [], n;
	return (r, i) => (n && gm(r, e) && gm(i, t) || (n = Kv(r, i)), e = r, t = i, n);
}
function Jv(e, t) {
	return {
		premiumReleaseDate: Yv(e.premiumReleaseDate, t.premiumReleaseDate),
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
		views: bg(e.views, t.views),
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
function Yv(e, t) {
	return e === void 0 ? t : t === void 0 ? e : new Date(Math.max(e.valueOf(), t.valueOf()));
}
function Xv(e, t) {
	let n = {}, r;
	for (r in e) Zv(r, n, e, t);
	for (r in t) Zv(r, n, e, t);
	return n;
}
function Zv(e, t, n, r) {
	if (t[e]) return t[e];
	let i = Qv(e, t, n, r);
	return i && (t[e] = i), i;
}
function Qv(e, t, n, r) {
	let i = n[e], a = r[e], o = (e) => i && i[e] !== null ? i[e] : a && a[e] !== null ? a[e] : null, s = o("component"), c = o("superType"), l = null;
	if (c) {
		if (c === e) throw Error("Can't have a custom view type that references itself");
		l = Zv(c, t, n, r);
	}
	return !s && l && (s = l.component), s ? {
		type: e,
		component: s,
		defaults: xg(l ? l.defaults : {}, i ? i.rawOptions : {}),
		overrides: xg(l ? l.overrides : {}, a ? a.rawOptions : {})
	} : null;
}
function $v(e) {
	return ym(e, ey);
}
function ey(e) {
	let t = typeof e == "function" ? { component: e } : e, { component: n } = t;
	return t.content ? n = ty(t.content) : n && !(n.prototype instanceof J) && (n = ty(n)), {
		superType: t.type,
		component: n,
		rawOptions: t
	};
}
function ty(e) {
	return (t) => O(C_.Consumer, { children: (n) => {
		let { options: r, viewSpec: i } = n, a = {
			...t,
			nextDayThreshold: r.nextDayThreshold,
			...fm(r),
			options: {
				headerToolbar: r.headerToolbar,
				footerToolbar: r.footerToolbar
			},
			isHeightAuto: P_(r),
			view: n.viewApi
		};
		return O(X, {
			tag: "div",
			className: H(Z(r.viewClass, a), Z(i.optionDefaults.class, a), Z(i.optionDefaults.className, a), Z(i.optionOverrides.class, a), Z(i.optionOverrides.className, a)),
			renderProps: a,
			generatorName: void 0,
			customGenerator: e,
			didMount: r.didMount || r.viewDidMount,
			willUnmount: r.willUnmount || r.viewWillUnmount
		});
	} });
}
function ny(e, t, n) {
	let r = $v(e), i = $v(t.views);
	return ym(Xv(r, i), (e) => ry(e, i, t, n));
}
function ry(e, t, n, r) {
	let i = e.overrides.duration || e.defaults.duration || r.duration || n.duration, a = null, o = "", s = "", c = {};
	if (i && (a = ay(i), a)) {
		let e = mp(a);
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
var iy = {};
function ay(e) {
	let t = JSON.stringify(e), n = iy[t];
	return n === void 0 && (n = B(e), iy[t] = n), n;
}
function oy(e, t) {
	return t.type === "CHANGE_VIEW_TYPE" && (e = t.viewType), e;
}
function sy(e, t) {
	switch (t.type) {
		case "CHANGE_DATE": return t.dateMarker;
		default: return e;
	}
}
function cy(e, t, n) {
	let r = e.initialDate;
	return r == null ? n.getDateMarker() : t.createMarker(r);
}
function ly(e, t) {
	switch (t.type) {
		case "SET_OPTION": return {
			...e,
			[t.optionName]: t.rawOptionValue
		};
		default: return e;
	}
}
function uy(e, t, n, r, i) {
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
function dy(e, t) {
	switch (t.type) {
		case "UNSELECT_DATES": return null;
		case "SELECT_DATES": return t.selection;
		default: return e;
	}
}
function fy(e, t) {
	switch (t.type) {
		case "UNSELECT_EVENT": return "";
		case "SELECT_EVENT": return t.eventInstanceId;
		default: return e;
	}
}
function py(e, t) {
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
function my(e, t) {
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
function hy(e, t, n) {
	return {
		header: e.headerToolbar ? gy(e.headerToolbar, e, t, n) : null,
		footer: e.footerToolbar ? gy(e.footerToolbar, e, t, n) : null
	};
}
function gy(e, t, n, r) {
	let i = t.direction === "rtl", a = [], o = !1;
	function s(e) {
		let i = _y(e, t, n, r);
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
function _y(e, t, n, r) {
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
				u = l.text || (n ? t[n] : "") || (o.singleUnit ? t[o.singleUnit + "TextLong"] || t[o.singleUnit + "Text"] : "") || e, d = lm(l.hint || t.viewHint, [u, e], u), f = (t) => {
					l?.click?.(t), t.defaultPrevented || r.changeView(e);
				};
			} else u = l.text || t[e + "TextLong"] || t[e + "Text"] || e, d = e === "prevYear" ? lm(l.hint || t.prevHint, [t.yearText, "year"], u) : e === "nextYear" ? lm(l.hint || t.nextHint, [t.yearText, "year"], u) : (n) => lm(l.hint || t[e + "Hint"], [t[n + "TextLong"] || t[n + "Text"], n], u), f = (t) => {
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
var vy = class {
	constructor(e, t, n) {
		this.type = e, this.getCurrentData = t, this.dateEnv = n;
	}
	get calendar() {
		return this.getCurrentData().calendarApi;
	}
	get title() {
		return this.getCurrentData().viewTitle;
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
}, yy = {
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
function by(e, t) {
	return ah(xy(e), null, t);
}
function xy(e) {
	let t;
	return t = e === !0 ? [{}] : Array.isArray(e) ? e.filter((e) => e.daysOfWeek) : typeof e == "object" && e ? [e] : [], t = t.map((e) => ({
		...yy,
		...e
	})), t;
}
function Sy(e, t, n) {
	let r;
	r = /^(year|month)$/.test(e.currentRangeUnit) ? e.currentRange : e.activeRange;
	let i, a = { isEndExclusive: e.isRangeAllDay };
	return t.titleFormat ? i = n.formatRangeToParts(r.start, r.end, U(t.titleFormat), a) : (i = n.formatRangeToParts(r.start, r.end, U(Cy(e, t.disallowAmbigTitle, "long")), a), wy(i) && (i = n.formatRangeToParts(r.start, r.end, U(Cy(e, t.disallowAmbigTitle, "short")), a))), bp(i);
}
function Cy(e, t, n) {
	let { currentRangeUnit: r } = e;
	if (r === "year") return { year: "numeric" };
	if (r === "month") return {
		year: "numeric",
		month: n
	};
	if (!t) {
		let t = Ef(e.currentRange.start, e.currentRange.end);
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
function wy(e) {
	let t = !1, n = !1;
	for (let r of e) r.type === "month" && (r.source === "startRange" && (t = !0), r.source === "endRange" && (n = !0));
	return t && n;
}
var Ty = class {
	constructor() {
		this.resetListeners = /* @__PURE__ */ new Set();
	}
	handleInput(e, t) {
		let n = this.dateEnv;
		if (e !== n && (typeof t == "function" ? this.nowFn = t : n || (this.nowAnchorDate = t ? Ey(t, e) : /* @__PURE__ */ new Date(), this.nowAnchorQueried = Date.now()), this.dateEnv = e, n)) for (let e of this.resetListeners.values()) e();
	}
	getDateMarker() {
		return this.dateEnv.timestampToMarker(this.getEpochMs());
	}
	getEpochMs() {
		return this.nowAnchorDate ? this.nowAnchorDate.valueOf() + (Date.now() - this.nowAnchorQueried) : Ey(this.nowFn(), this.dateEnv).valueOf();
	}
	addResetListener(e) {
		this.resetListeners.add(e);
	}
	removeResetListener(e) {
		this.resetListeners.delete(e);
	}
};
function Ey(e, t) {
	let n = t.createMarkerMeta(e);
	return n.instantMs == null ? t.toDate(n.marker) : new Date(n.instantMs);
}
var Dy = class {
	constructor(e) {
		this.computeCurrentViewData = q(this._computeCurrentViewData), this.organizeRawLocales = q(Ag), this.buildLocale = q(jg), this.buildPluginHooks = qv(), this.buildDateEnv = q(Oy), this.parseToolbars = q(hy), this.buildViewSpecs = q(ny), this.buildDateProfileGenerator = S_(ky), this.buildViewApi = q(Ay), this.buildViewUiProps = S_(Ny), this.buildEventUiBySource = q(jy, Tm), this.buildEventUiBases = q(My), this.parseContextBusinessHours = S_(Fy), this.buildToolbarProps = q(Ry), this.buildTitle = q(Sy), this.nowManager = new Ty(), this.isDrainingActionQueue = !1, this.actionQueue = [], this.optionOverrides = {}, this.emitter = new p_(), this.currentCalendarOptionsRefiners = {}, this.currentCalendarOptionsInput = {}, this.currentCalendarOptionsRefined = {}, this.currentViewOptionsInput = {}, this.currentViewOptionsRefined = {}, this.optionsForRefining = [], this.optionsForHandling = [], this.getCurrentData = () => this.data, this.handleNowChange = () => {
			this.dispatch({ type: "UPDATE_NOW" });
		}, this.dispatch = (e) => {
			this.actionQueue.push(e), this.isDrainingActionQueue || this.drainActionQueue();
		}, this.config = e, this.nowManager = new Ty(), this.nowTimer = new R_(this.handleNowChange);
	}
	destroy() {
		this.nowTimer.destroy();
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
		let { config: r } = this, i = !e, a = i ? {} : ly(e.dynamicOptionOverrides, n), o = this.computeOptionsData(this.optionOverrides, a, r.calendarApi), s = i ? o.calendarOptions.initialView || o.pluginHooks.initialView : oy(e.currentViewType, n), c = this.computeCurrentViewData(s, o, this.optionOverrides, a);
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
		}), d = i ? cy(o.calendarOptions, o.dateEnv, this.nowManager) : sy(e.currentDate, n), f;
		i ? f = c.dateProfileGenerator.build(d, u) : (f = e.dateProfile, t && t.dateProfileGenerator !== c.dateProfileGenerator && (f = c.dateProfileGenerator.build(d, u)), f = uy(f, n, d, u, c.dateProfileGenerator)), (n && (n.type === "PREV" || n.type === "NEXT") || !Yf(f.activeRange, d)) && (d = f.currentRange.start);
		let p = i ? Xg(o.calendarOptions, f, l) : Zg(e.eventSources, n, f, l), m = i ? lh() : jv(e.eventStore, n, p, f, l), h = $g(p), g = i ? lh() : h && !c.options.progressiveEventRendering && e.renderableEventStore || m, { eventUiSingleBase: _, selectionConfig: v } = this.buildViewUiProps(l), y = this.buildEventUiBySource(p), b = i ? {} : this.buildEventUiBases(g.defs, _, y), x = {
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
			dateSelection: i ? null : dy(e.dateSelection, n),
			eventSelection: i ? "" : fy(e.eventSelection, n),
			eventDrag: i ? null : py(e.eventDrag, n),
			eventResize: i ? null : my(e.eventResize, n),
			nowDate: u
		}, S = {
			...l,
			...x
		};
		for (let t of o.pluginHooks.reducers) Object.assign(x, t(e, n, S));
		let C = e ? Py(e, l) : !1, w = Py(x, l);
		!C && w ? this.emitter.trigger("loading", !0) : C && !w && this.emitter.trigger("loading", !1);
		let T = this.buildTitle(f, c.options, o.dateEnv), ee = this.buildToolbarProps(c.viewSpec, f, c.dateProfileGenerator, d, u, T), E = {
			viewTitle: T,
			nowManager: this.nowManager,
			calendarApi: r.calendarApi,
			dispatch: this.dispatch,
			emitter: this.emitter,
			getCurrentData: this.getCurrentData,
			toolbarProps: ee,
			...o,
			...c,
			...x
		}, te = o.pluginHooks.optionChangeHandlers, D = t && t.calendarOptions, ne = o.calendarOptions;
		if (D && D !== ne) {
			D.timeZone !== ne.timeZone && (x.eventSources = E.eventSources = Qg(E.eventSources, f, E), x.eventStore = E.eventStore = Lv(E.eventStore, t.dateEnv, E.dateEnv), x.renderableEventStore = E.renderableEventStore = Lv(E.renderableEventStore, t.dateEnv, E.dateEnv));
			for (let e in te) (this.optionsForHandling.indexOf(e) !== -1 || D[e] !== ne[e]) && te[e](ne[e], E);
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
		let { locales: n, locale: r } = xg(Pm, e, t), i = this.organizeRawLocales(n), a = i.map, o = this.buildLocale(r || i.defaultCode, a).options, s = this.buildPluginHooks(e.plugins || [], f_), c = this.currentCalendarOptionsRefiners = {
			...Nm,
			...Fm,
			...Im,
			...s.listenerRefiners,
			...s.optionRefiners
		}, l = xg(Pm, ...s.optionDefaults, o, Ly(xg(e, t), c)), u = {}, d = this.currentCalendarOptionsInput, f = this.currentCalendarOptionsRefined, p = !1;
		for (let e in l) this.optionsForRefining.indexOf(e) === -1 && (l[e] === d[e] || Rm[e] && e in d && Rm[e](d[e], l[e]) || Eg(d[e], l[e])) ? u[e] = f[e] : c[e] && (u[e] = c[e](l[e], e), p = !0);
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
			...Nm,
			...Fm,
			...Im,
			...Lm,
			...t.listenerRefiners,
			...t.optionRefiners
		}, o = xg(Pm, ...t.optionDefaults, e.optionDefaults, n, Ly(xg(r, e.optionOverrides, i), a)), s = {}, c = this.currentViewOptionsInput, l = this.currentViewOptionsRefined, u = !1;
		for (let e in o) o[e] === c[e] || Rm[e] && Rm[e](o[e], c[e]) || Eg(c[e], o[e]) ? s[e] = l[e] : (o[e] === this.currentCalendarOptionsInput[e] || Rm[e] && Rm[e](o[e], this.currentCalendarOptionsInput[e]) ? e in this.currentCalendarOptionsRefined && (s[e] = this.currentCalendarOptionsRefined[e]) : a[e] && (s[e] = a[e](o[e], e)), u = !0);
		return u && (this.currentViewOptionsInput = o, this.currentViewOptionsRefined = s), {
			rawOptions: this.currentViewOptionsInput,
			refinedOptions: this.currentViewOptionsRefined
		};
	}
};
function Oy(e, t, n, r, i, a, o, s) {
	return new Cp({
		calendarSystem: "gregory",
		timeZone: e,
		locale: jg(t || s.defaultCode, s.map),
		weekNumberCalculation: n,
		firstDay: r,
		weekTextLong: i,
		weekTextShort: a,
		cmdFormatter: o.cmdFormatter
	});
}
function ky(e) {
	return new (e.dateProfileGeneratorClass || Ov)(e);
}
function Ay(e, t, n) {
	return new vy(e, t, n);
}
function jy(e) {
	return ym(e, (e) => e.ui);
}
function My(e, t, n) {
	let r = { "": t };
	for (let t in e) {
		let i = e[t];
		i.sourceId && n[i.sourceId] && (r[t] = n[i.sourceId]);
	}
	return r;
}
function Ny(e) {
	let { options: t } = e;
	return {
		eventUiSingleBase: gh({
			display: t.eventDisplay,
			editable: t.editable,
			startEditable: t.eventStartEditable,
			durationEditable: t.eventDurationEditable,
			constraint: t.eventConstraint,
			overlap: typeof t.eventOverlap == "boolean" ? t.eventOverlap : void 0,
			allow: t.eventAllow
		}, e),
		selectionConfig: gh({
			constraint: t.selectConstraint,
			overlap: typeof t.selectOverlap == "boolean" ? t.selectOverlap : void 0,
			allow: t.selectAllow
		}, e)
	};
}
function Py(e, t) {
	for (let n of t.pluginHooks.isLoadingFuncs) if (n(e)) return !0;
	return !1;
}
function Fy(e) {
	return by(e.options.businessHours, e);
}
var Iy = {};
function Ly(e, t) {
	let n = {};
	for (let r in e) t[r] ? n[r] = e[r] : Iy[r] || (zp(`Unknown option \`${r}\`.`), Iy[r] = !0);
	return n;
}
function Ry(e, t, n, r, i, a) {
	let o = n.build(i, i, void 0, !1), s = n.buildPrev(t, r, i, !1), c = n.buildNext(t, r, i, !1);
	return {
		title: a,
		selectedButton: e.type,
		navUnit: e.singleUnit,
		isTodayEnabled: o.isValid && !Yf(t.currentRange, i),
		isPrevEnabled: s.isValid,
		isNextEnabled: c.isValid
	};
}
var zy = class {
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
		n.currentCalendarOptionsRefiners[e] ? n.emitter.on(e, t) : zp(`Unknown listener \`${e}\`.`);
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
		let t = this.getCurrentData(), n = B(e);
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
		return bp(n.formatToParts(r.marker, U(t), { instantMs: r.instantMs }));
	}
	formatRange(e, t, n) {
		let { dateEnv: r } = this.getCurrentData(), i = r.createMarkerMeta(e), a = r.createMarkerMeta(t);
		return bp(r.formatRangeToParts(i.marker, a.marker, U(n), {
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
		let r = this.getCurrentData(), i = kh(n, r.dateEnv, B({ days: 1 }));
		i && (this.dispatch({
			type: "SELECT_DATES",
			selection: i
		}), zh(i, null, r));
	}
	unselect(e) {
		let t = this.getCurrentData();
		t.dateSelection && (this.dispatch({ type: "UNSELECT_DATES" }), Bh(e, t));
	}
	addEvent(e, t) {
		if (e instanceof K) {
			let t = e._def, n = e._instance;
			return this.getCurrentData().eventStore.defs[t.defId] || (this.dispatch({
				type: "ADD_EVENTS",
				eventStore: oh({
					def: t,
					instance: n
				})
			}), this.triggerEventAdd(e)), e;
		}
		let n = this.getCurrentData(), r;
		if (t instanceof Yh) r = t.internalEventSource;
		else if (typeof t == "boolean") t && ([r] = bm(n.eventSources));
		else if (t != null) {
			let e = this.getEventSourceById(t);
			if (!e) return zp(`Unknown event source ID \`${t}\`.`), null;
			r = e.internalEventSource;
		}
		let i = Sh(e, r, n, !1);
		if (i) {
			let e = new K(n, i.def, i.def.recurringDef ? null : i.instance);
			return this.dispatch({
				type: "ADD_EVENTS",
				eventStore: oh(i)
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
					eventStore: Zh(e)
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
		return Qh(e.eventStore, e);
	}
	removeAllEvents() {
		this.dispatch({ type: "REMOVE_ALL_EVENTS" });
	}
	getEventSources() {
		let e = this.getCurrentData(), t = e.eventSources, n = [];
		for (let r in t) n.push(new Yh(e, t[r]));
		return n;
	}
	getEventSourceById(e) {
		let t = this.getCurrentData(), n = t.eventSources;
		e = String(e);
		for (let r in n) if (n[r].publicId === e) return new Yh(t, n[r]);
		return null;
	}
	addEventSource(e) {
		let t = this.getCurrentData();
		if (e instanceof Yh) return t.eventSources[e.internalEventSource.sourceId] || this.dispatch({
			type: "ADD_EVENT_SOURCES",
			sources: [e.internalEventSource]
		}), e;
		let n = qg(e, t);
		return n ? (this.dispatch({
			type: "ADD_EVENT_SOURCES",
			sources: [n]
		}), new Yh(t, n)) : null;
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
		let t = B(e);
		t && this.trigger("_timeScrollRequest", t);
	}
	getButtonState() {
		let e = this.getCurrentData(), { toolbarProps: t } = e, n = e.calendarOptions, r = n.buttons || {}, i = e.viewSpecs, a = e.viewSpec.singleUnit, o = [a ? By(a, n) : "", a], s = {
			today: {
				text: n.todayText,
				hint: lm(n.todayHint, o, n.todayText),
				isDisabled: !t.isTodayEnabled
			},
			prev: {
				text: n.prevText,
				hint: lm(n.prevHint, o, n.prevText),
				isDisabled: !t.isPrevEnabled
			},
			next: {
				text: n.nextText,
				hint: lm(n.nextHint, o, n.nextText),
				isDisabled: !t.isNextEnabled
			},
			prevYear: {
				text: n.prevYearText,
				hint: lm(n.prevHint, [n.yearText, "year"], n.prevYearText),
				isDisabled: !1
			},
			nextYear: {
				text: n.prevYearText,
				hint: lm(n.nextHint, [n.yearText, "year"], n.nextYearText),
				isDisabled: !1
			}
		};
		for (let e in i) {
			let t = i[e], { singleUnit: a } = t, o = t.optionDefaults.buttonTextKey, c = r[e]?.text || (o ? n[o] : "") || (a ? By(a, n) : "") || e;
			s[e] = {
				text: c,
				hint: lm(n.viewHint, [c, e], c)
			};
		}
		return s;
	}
};
function By(e, t) {
	return t[e + "TextLong"] || t[e + "Text"];
}
var Vy = class extends T {
	constructor() {
		super(...arguments), this.state = { forPrint: !1 }, this.handleBeforePrint = () => {
			Q_(() => {
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
function Hy(e, t) {
	let n = e.borderlessX ?? e.borderless, r = e.borderlessTop ?? e.borderless, i = e.borderlessBottom ?? e.borderless, a = {
		borderlessX: !!n,
		borderlessTop: !!r,
		borderlessBottom: !!i
	};
	return H(Z(e.class, a), Z(e.className, a), V.borderBoxRoot, V.isolate, V.flexCol, t ? V.calendarPrintRoot : V.calendarScreenRoot);
}
var Uy = class extends J {
	render() {
		let { contentGenerator: e, className: t } = this.props;
		if (e) return O(X, {
			tag: "span",
			style: { display: "contents" },
			attrs: { "aria-hidden": !0 },
			renderProps: {},
			generatorName: void 0,
			customGenerator: e
		});
		if (t !== void 0) return O("span", {
			"aria-hidden": !0,
			className: t
		});
	}
}, Wy = class extends J {
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
		for (let n of e) {
			let { name: s, customElement: c, buttonHint: l } = n;
			if (s === "title") i.push(O("div", {
				role: "heading",
				"aria-level": r.headingLevel,
				id: t.titleId,
				className: H(r.toolbarTitleClass),
				children: t.title
			}));
			else if (c) i.push(O(X, {
				tag: "span",
				style: { display: "contents" },
				renderProps: {},
				generatorName: void 0,
				customGenerator: c
			}));
			else {
				let c = s === t.selectedButton, u = !t.isTodayEnabled && s === "today" || !t.isPrevEnabled && s === "prev" || !t.isNextEnabled && s === "next", d = n.buttonDisplay ?? r.buttonDisplay;
				d === "auto" && (d = n.buttonIconContent || n.buttonIconClass ? "icon" : "text");
				let f;
				d !== "text" && (f = O(Uy, {
					className: n.buttonIconClass,
					contentGenerator: n.buttonIconContent
				}));
				let p = e.length > 1 && a, m = p ? { hasSelection: o } : null, h = {
					name: s,
					text: n.buttonText,
					isPrimary: n.buttonIsPrimary,
					isSelected: c,
					isDisabled: u,
					isIconOnly: d === "icon",
					buttonGroup: m
				};
				i.push(O(X, {
					tag: "button",
					attrs: {
						type: "button",
						disabled: u,
						...a && o ? {
							role: "tab",
							"aria-selected": c
						} : { "aria-pressed": c },
						"aria-label": typeof l == "function" ? l(t.navUnit) : l,
						onClick: n.buttonClick
					},
					className: H(Z(r.buttonClass, h), !u && V.cursorPointer, p && H(c ? V.z1 : V.z0, V.focusZ2)),
					renderProps: h,
					generatorName: void 0,
					classNameGenerator: n.buttonClass,
					didMount: n.buttonDidMount,
					willUnmount: n.buttonWillUnmount,
					children: () => d === "text" ? n.buttonText : d === "icon" ? f : d === "icon-text" ? O(w, { children: [f, n.buttonText] }) : O(w, { children: [n.buttonText, f] })
				}));
			}
		}
		return i.length > 1 ? x("div", {
			role: a && o ? "tablist" : void 0,
			"aria-label": a && o ? r.viewChangeHint : void 0,
			className: H(Z(r.buttonGroupClass, { hasSelection: o }), V.isolate)
		}, ...i) : i[0];
	}
}, Gy = class extends J {
	render() {
		let { props: e } = this, t = this.context.options, { sectionWidgets: n } = e.model, { borderlessX: r, borderlessTop: i, borderlessBottom: a } = fm(t);
		return O("div", {
			className: H(Z(e.isHeader ? t.headerToolbarClass : t.footerToolbarClass, {
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
		return O(Wy, {
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
}, Ky = class extends Vv {
	constructor(e) {
		super(e), this.handleSegClick = (e, t) => {
			let { component: n } = this, { context: r } = n, i = rg(t);
			i && n.isValidSegDownEl(e.target) && r.emitter.trigger("eventClick", {
				el: t,
				event: new K(n.context, i.def, i.instance),
				jsEvent: e,
				view: r.viewApi
			});
		}, this.destroy = Gp(e.el, "click", `.${V.internalEvent}`, this.handleSegClick);
	}
}, qy = class extends Vv {
	constructor(e) {
		super(e), this.handleEventElRemove = (e) => {
			e === this.currentSegEl && this.handleSegLeave(null, this.currentSegEl);
		}, this.handleSegEnter = (e, t) => {
			rg(t) && (this.currentSegEl = t, this.triggerEvent("eventMouseEnter", e, t));
		}, this.handleSegLeave = (e, t) => {
			this.currentSegEl && (this.currentSegEl = null, this.triggerEvent("eventMouseLeave", e, t));
		}, this.removeHoverListeners = Kp(e.el, `.${V.internalEvent}`, this.handleSegEnter, this.handleSegLeave);
	}
	destroy() {
		this.removeHoverListeners();
	}
	triggerEvent(e, t, n) {
		let { component: r } = this, { context: i } = r, a = rg(n);
		(!t || r.isValidSegDownEl(t.target)) && i.emitter.trigger(e, {
			el: n,
			event: new K(i, a.def, a.instance),
			jsEvent: t,
			view: i.viewApi
		});
	}
}, Jy = class extends T_ {
	constructor() {
		super(...arguments), this.buildViewContext = q(w_), this.buildViewPropTransformers = q(Yy), this.interactionsStore = {}, this.calendarInteractions = [], this.registerInteractiveComponent = (e, t) => {
			let n = Hv(e, t), r = [Ky, qy];
			t.disableHits || (r = r.concat(this.props.pluginHooks.componentInteractions));
			let i = r.map((e) => new e(n));
			this.interactionsStore[e.uid] = i, Wv[e.uid] = n;
		}, this.unregisterInteractiveComponent = (e) => {
			let t = this.interactionsStore[e.uid];
			if (t) {
				for (let e of t) e.destroy();
				delete this.interactionsStore[e.uid];
			}
			delete Wv[e.uid];
		};
	}
	get viewTitleId() {
		return this.props.baseId + "title";
	}
	render() {
		let { props: e } = this, { toolbarConfig: t, options: n } = e, r, i = !1, a;
		e.forPrint || P_(n) || (n.height == null ? n.contentHeight == null ? a = Math.max(n.aspectRatio, .5) : r = n.contentHeight : i = !0);
		let o = this.buildViewContext(e.viewSpec, e.viewApi, e.options, e.dateProfileGenerator, e.dateEnv, e.nowManager, e.pluginHooks, e.dispatch, e.getCurrentData, e.emitter, e.calendarApi, e.baseId, this.registerInteractiveComponent, this.unregisterInteractiveComponent);
		return O(C_.Provider, {
			value: o,
			children: [
				t.header && O(Gy, {
					model: t.header,
					isHeader: !0,
					titleId: this.viewTitleId,
					...e.toolbarProps
				}),
				O("div", {
					className: H(V.flexCol, V.rel, V.overflowAnchorNone, V.minHeight0, i && V.liquid),
					style: {
						height: r,
						aspectRatio: a == null ? void 0 : String(a)
					},
					children: [this.renderView(H((i || r) && V.liquid, a != null && V.fill, V.internalView)), this.buildAppendContent()]
				}),
				t.footer && O(Gy, {
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
		return O(l, { ...o });
	}
	buildAppendContent() {
		let { props: e } = this;
		return O(w, { children: e.pluginHooks.viewContainerAppends.map((t, n) => O(w, { children: t(e) }, n)) });
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
function Yy(e) {
	return e.map((e) => new e());
}
// node_modules/fullcalendar/chunks/bcb7abc1.js
var Xy = class extends zy {
	constructor(e, t = {}) {
		super(), this.baseId = `fc:${Qp()}:`, this.isRendering = !1, this.isRendered = !1, this.customContentRenderId = 0, this.currentClassName = "", this.currentColorScheme = "", this.handleDataChange = (e, t) => {
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
				this.isRendered = !0, pt(() => {
					this.vdomRoot.render(O(w, { children: O(j_.Provider, {
						value: this.customContentRenderId,
						children: O(Vy, {
							emitter: e.emitter,
							children: (t) => {
								let n = e.calendarOptions, r = n.direction === "rtl", i = Hy(n, t);
								return this.setIsRtl(r), this.setClassName(i), this.setHeight(n.height), this.setColorScheme(n.colorScheme || ""), O(Jy, {
									...e,
									forPrint: t,
									baseId: this.baseId
								});
							}
						})
					}) }));
				});
			} else this.isRendered && (this.isRendered = !1, this.vdomRoot.unmount(), this.setIsRtl(!1), this.setClassName(""), this.setHeight(""), this.setColorScheme(""));
		}, this.el = e, this.vdomRoot = mt(e), this.renderRunner = new gv(this.handleRenderRequest), this.dataManager = new Dy({
			calendarApi: this,
			onDataChange: this.handleDataChange
		}), this.currentData = this.dataManager.update(t);
	}
	render() {
		this.isRendering ? this.customContentRenderId += 1 : this.isRendering = !0, this.renderRunner.request();
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
		b_(this.el, "height", e);
	}
	setColorScheme(e) {
		e !== this.currentColorScheme && (e ? this.el.dataset.colorScheme = e : delete this.el.dataset.colorScheme, this.currentColorScheme = e);
	}
	setIsRtl(e) {
		e ? this.el.dir = "rtl" : this.el.removeAttribute("dir");
	}
}, Zy = class {
	constructor(e, t) {
		this.emitter = new p_();
	}
	destroy() {}
	setMirrorIsVisible(e) {}
	setMirrorNeedsRevert(e) {}
	setAutoScrollEnabled(e) {}
}, Qy = {};
function $y(e, t, n) {
	let { instances: r } = e.mutatedEvents;
	for (let e in r) if (!Jf(t.validRange, r[e].range)) return !1;
	return tb({ eventDrag: e }, n);
}
function eb(e, t, n) {
	return Jf(t.validRange, e.range) ? tb({ dateSelection: e }, n) : !1;
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
	let i = t.getCurrentData(), a = e.eventDrag, o = a.mutatedEvents, s = o.defs, c = o.instances, l = ig(s, a.isEvent ? e.eventUiBases : { "": i.selectionConfig });
	r && (l = ym(l, r));
	let u = zv(e.eventStore, a.affectedEvents.instances), d = u.defs, f = u.instances, p = ig(d, e.eventUiBases);
	for (let r in c) {
		let o = c[r], m = o.range, h = l[o.defId], g = s[o.defId];
		if (!ab(h.constraints, m, u, e.businessHours, t)) return !1;
		let { eventOverlap: _ } = t.options, v = typeof _ == "function" ? _ : null;
		for (let e in f) {
			let n = f[e];
			if (Jm(m, n.range, t.dateEnv) && (p[n.defId].overlap === !1 && a.isEvent || h.overlap === !1 || v && !v(new K(t, d[n.defId], n), new K(t, g, o)))) return !1;
		}
		let y = i.eventStore;
		for (let e of h.allows) {
			let i = {
				...n,
				range: o.range,
				allDay: g.allDay
			}, a = y.defs[g.defId], s = y.instances[r], c;
			if (c = a ? new K(t, a, s) : new K(t, g), !e(Vh(i, t), c)) return !1;
		}
	}
	return !0;
}
function ib(e, t, n, r) {
	let i = e.eventStore, a = i.defs, o = i.instances, s = e.dateSelection, c = Vm(s.range.start, s.range.end, s.instantStartMs, s.instantEndMs), { selectionConfig: l } = t.getCurrentData();
	if (r && (l = r(l)), !ab(l.constraints, c, i, e.businessHours, t)) return !1;
	let { selectOverlap: u } = t.options, d = typeof u == "function" ? u : null;
	for (let e in o) {
		let n = o[e];
		if (Jm(c, n.range, t.dateEnv) && (l.overlap === !1 || d && !d(new K(t, a[n.defId], n), null))) return !1;
	}
	for (let e of l.allows) if (!e(Vh({
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
	return e === "businessHours" ? sb(rh(r, t, i)) : typeof e == "string" ? sb(dh(n, (t) => t.groupId === e)) : typeof e == "object" && e ? sb(rh(e, t, i)) : [];
}
function sb(e) {
	let { instances: t } = e, n = [];
	for (let e in t) n.push(t[e].range);
	return n;
}
function cb(e, t, n) {
	for (let r of e) if (Ym(r, t, n.dateEnv)) return !0;
	return !1;
}
// node_modules/fullcalendar/chunks/72e3cc3d.js
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
// node_modules/fullcalendar/chunks/62ea4145.js
Qy.touchMouseIgnoreWait = 500;
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
		}, this.containerEl = e, this.emitter = new p_(), e.addEventListener("mousedown", this.handleMouseDown), e.addEventListener("touchstart", this.handleTouchStart, { passive: !0 }), Eb();
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
	}, Qy.touchMouseIgnoreWait);
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
var kb = class {
	constructor() {
		this.isVisible = !1, this.sourceEl = null, this.mirrorEl = null, this.sourceElRect = null, this.parentNode = document.body, this.zIndex = 9999, this.revertDuration = 0, this.colorScheme = "";
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
		e && this.mirrorEl && this.isVisible && this.revertDuration && (this.deltaX || this.deltaY) ? this.doRevertAnimation(n, this.revertDuration) : setTimeout(n, 0);
	}
	doRevertAnimation(e, t) {
		let n = this.mirrorEl, r = this.sourceEl.getBoundingClientRect();
		n.style.transition = "top " + t + "ms,left " + t + "ms", y_(n, {
			left: r.left,
			top: r.top
		}), Jp(n, () => {
			n.style.transition = "", e();
		});
	}
	cleanup() {
		this.mirrorEl &&= (this.mirrorEl.remove(), null), this.sourceEl = null;
	}
	updateElPosition() {
		this.sourceEl && this.isVisible && y_(this.getMirrorEl(), {
			left: this.sourceElRect.left + this.deltaX,
			top: this.sourceElRect.top + this.deltaY
		});
	}
	getMirrorEl() {
		let e = this.sourceElRect, t = this.mirrorEl;
		return t || (t = this.mirrorEl = this.sourceEl.cloneNode(!0), t.style.userSelect = "none", t.style.webkitUserSelect = "none", t.style.pointerEvents = "none", this.colorScheme && t.setAttribute("data-color-scheme", this.colorScheme), t.classList.add(V.borderBoxRoot), y_(t, {
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
}, Ab = class {
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
}, jb = class extends Ab {
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
}, Mb = class extends Ab {
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
}, Nb = class extends Ab {
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
}, Pb = class extends Nb {
	constructor(e, t) {
		super(new jb(e), t);
	}
	getEventTarget() {
		return this.scrollController.el;
	}
	computeClientRect() {
		return hb(this.scrollController.el);
	}
}, Fb = class extends Nb {
	constructor(e) {
		super(new Mb(), e);
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
}, Ib = typeof performance == "function" ? performance.now : Date.now, Lb = class {
	constructor() {
		this.isEnabled = !0, this.scrollQuery = [window, `.${V.internalScroller}`], this.edgeThreshold = 50, this.maxVelocity = 300, this.pointerScreenX = null, this.pointerScreenY = null, this.isAnimating = !1, this.scrollCaches = null, this.everMovedUp = !1, this.everMovedDown = !1, this.everMovedLeft = !1, this.everMovedRight = !1, this.animate = () => {
			if (this.isAnimating) {
				let e = this.computeBestEdge(this.pointerScreenX + window.scrollX, this.pointerScreenY + window.scrollY);
				if (e) {
					let t = Ib();
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
			i < 0 ? this.everMovedUp = !0 : i > 0 && (this.everMovedDown = !0), a < 0 ? this.everMovedLeft = !0 : a > 0 && (this.everMovedRight = !0), this.pointerScreenX = n, this.pointerScreenY = r, this.isAnimating || (this.isAnimating = !0, this.requestAnimation(Ib()));
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
		return this.queryScrollEls(e).map((e) => e === window ? new Fb(!1) : new Pb(e, !1));
	}
	queryScrollEls(e) {
		let t = [];
		for (let n of this.scrollQuery) typeof n == "object" ? t.push(n) : t.push(...Array.prototype.slice.call(e.getRootNode().querySelectorAll(n)));
		return t;
	}
}, Rb = class extends Zy {
	constructor(e, t) {
		super(e), this.containerEl = e, this.delay = null, this.minDistance = 0, this.touchScrollAllowed = !0, this.mirrorNeedsRevert = !1, this.isInteracting = !1, this.isDragging = !1, this.isDelayEnded = !1, this.isDistanceSurpassed = !1, this.delayTimeoutId = null, this.onPointerDown = (e) => {
			this.isDragging || (this.isInteracting = !0, this.isDelayEnded = !1, this.isDistanceSurpassed = !1, this.emitter.trigger("pointerdown", e), this.isInteracting && (tm(document.body), rm(document.body), e.isTouch || e.origEvent.preventDefault(), this.mirror.setIsVisible(!1), this.mirror.start(e.subjectEl, e.pageX, e.pageY), this.startDelay(e), this.minDistance || this.handleDistanceSurpassed(e)));
		}, this.onPointerMove = (e) => {
			if (this.isInteracting) {
				if (this.emitter.trigger("pointermove", e), !this.isDistanceSurpassed) {
					let t = this.minDistance, n, { deltaX: r, deltaY: i } = e;
					n = r * r + i * i, n >= t * t && this.handleDistanceSurpassed(e);
				}
				this.isDragging && (e.origEvent.type !== "scroll" && (this.mirror.handleMove(e.pageX, e.pageY), this.autoScroller.handleMove(e.pageX, e.pageY)), this.emitter.trigger("dragmove", e));
			}
		}, this.onPointerUp = (e) => {
			this.isInteracting && (this.isInteracting = !1, nm(document.body), im(document.body), this.emitter.trigger("pointerup", e), this.isDragging && (this.autoScroller.stop(), this.tryStopDrag(e)), this.delayTimeoutId &&= (clearTimeout(this.delayTimeoutId), null));
		};
		let n = this.pointer = new Cb(e);
		n.emitter.on("pointerdown", this.onPointerDown), n.emitter.on("pointermove", this.onPointerMove), n.emitter.on("pointerup", this.onPointerUp), t && (n.selector = t), this.mirror = new kb(), this.autoScroller = new Lb();
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
}, zb = class {
	constructor(e) {
		this.el = e, this.origRect = gb(e), this.isRtl = __(e), this.scrollCaches = vb(e).map((e) => new Pb(e, !0));
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
		for (let e of this.scrollCaches) if (!Bb(e.getEventTarget()) && !lb(n, e.clientRect)) return !1;
		return !0;
	}
};
function Bb(e) {
	let t = e.tagName;
	return t === "HTML" || t === "BODY";
}
var Vb = class {
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
		}, this.droppableStore = t, e.emitter.on("pointerdown", this.handlePointerDown), e.emitter.on("dragstart", this.handleDragStart), e.emitter.on("dragmove", this.handleDragMove), e.emitter.on("pointerup", this.handlePointerUp), e.emitter.on("dragend", this.handleDragEnd), this.dragging = e, this.emitter = new p_();
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
		(t || !Hb(this.movingHit, n)) && (this.movingHit = n, this.emitter.trigger("hitupdate", n, !1, e));
	}
	prepareHits() {
		this.offsetTrackers = ym(this.droppableStore, (e) => (e.component.prepareHits(), new zb(e.el)));
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
					e && Jf(e.dateProfile.activeRange, e.dateSpan.range) && (this.disablePointCheck || s.el.contains(s.el.getRootNode().elementFromPoint(c + n - window.scrollX, l + r - window.scrollY))) && (!i || e.layer > i.layer) && (e.componentId = a, e.context = o.context, e.rect.left += n, e.rect.right += n, e.rect.top += r, e.rect.bottom += r, i = e);
				}
			}
		}
		return i;
	}
};
function Hb(e, t) {
	return !e && !t || !!e == !!t && jh(e.dateSpan, t.dateSpan);
}
function Ub(e, t) {
	let n = {};
	for (let r of t.pluginHooks.datePointTransforms) Object.assign(n, r(e, t));
	return Object.assign(n, Wb(e, t.dateEnv)), n;
}
function Wb(e, t) {
	let n = Um(e.range.start, e.instantStartMs, t, e.allDay);
	return {
		date: n.date,
		dateStr: n.dateStr,
		allDay: e.allDay
	};
}
var Gb = class extends Vv {
	constructor(e) {
		super(e), this.handlePointerDown = (e) => {
			let { dragging: t } = this, n = e.origEvent.target;
			this.component.context.emitter.hasHandlers("dateClick") && this.component.isValidDateDownEl(n) || t.cancel();
		}, this.handleDragEnd = (e) => {
			let { component: t } = this, { pointer: n } = this.dragging;
			if (!n.wasTouchScroll) {
				let { initialHit: n, finalHit: r } = this.hitDragging;
				if (n && r && Hb(n, r)) {
					let { context: r } = t, i = {
						...Ub(n.dateSpan, r),
						dayEl: n.getDayEl(),
						jsEvent: e.origEvent,
						view: r.viewApi || r.calendarApi.view
					};
					r.emitter.trigger("dateClick", i);
				}
			}
		}, this.dragging = new Rb(e.el), this.dragging.autoScroller.isEnabled = !1;
		let t = this.hitDragging = new Vb(this.dragging, Uv(e));
		t.emitter.on("pointerdown", this.handlePointerDown), t.emitter.on("dragend", this.handleDragEnd);
	}
	destroy() {
		this.dragging.destroy();
	}
}, Kb = class extends Vv {
	constructor(e) {
		super(e), this.dragSelection = null, this.handlePointerDown = (e) => {
			let { component: t, dragging: n } = this, { options: r } = t.context;
			r.selectable && t.isValidDateDownEl(e.origEvent.target) ? n.delay = e.isTouch ? qb(t) : null : n.cancel();
		}, this.handleDragStart = (e) => {
			this.component.context.calendarApi.unselect(e);
		}, this.handleHitUpdate = (e, t) => {
			let { context: n } = this.component, r = null, i = !1;
			if (e) {
				let t = this.hitDragging.initialHit;
				(e.componentId !== t.componentId || !this.isHitComboAllowed || this.isHitComboAllowed(t, e)) && (r = Jb(t, e, n.pluginHooks.dateSelectionTransformers)), r && eb(r, e.dateProfile, n) || (i = !0, r = null);
			}
			r ? n.dispatch({
				type: "SELECT_DATES",
				selection: r
			}) : t || n.dispatch({ type: "UNSELECT_DATES" }), i ? $p() : em(), t || (this.dragSelection = r);
		}, this.handlePointerUp = (e) => {
			this.dragSelection ? (zh(this.dragSelection, e, this.component.context), this.dragSelection = null) : this.component.context.emitter.trigger("_noDateSelect");
		};
		let { component: t } = e, { options: n } = t.context, r = this.dragging = new Rb(e.el);
		r.touchScrollAllowed = !1, r.minDistance = n.selectMinDistance || 0, r.autoScroller.isEnabled = n.dragScroll;
		let i = this.hitDragging = new Vb(this.dragging, Uv(e));
		i.emitter.on("pointerdown", this.handlePointerDown), i.emitter.on("dragstart", this.handleDragStart), i.emitter.on("hitupdate", this.handleHitUpdate), i.emitter.on("pointerup", this.handlePointerUp);
	}
	destroy() {
		this.dragging.destroy();
	}
};
function qb(e) {
	let { options: t } = e.context, n = t.selectLongPressDelay;
	return n ??= t.longPressDelay, n;
}
function Jb(e, t, n) {
	let r = e.dateSpan, i = t.dateSpan, a = r.instantStartMs != null || i.instantStartMs != null, o = [
		{
			date: r.range.start,
			ms: Ih(r, e.context.dateEnv)
		},
		{
			date: r.range.end,
			ms: Lh(r, e.context.dateEnv)
		},
		{
			date: i.range.start,
			ms: Ih(i, t.context.dateEnv)
		},
		{
			date: i.range.end,
			ms: Lh(i, t.context.dateEnv)
		}
	];
	o.sort(a ? (e, t) => um(e.ms, t.ms) : (e, t) => um(e.date.valueOf(), t.date.valueOf()));
	let s = {};
	for (let r of n) {
		let n = r(e, t);
		if (n === !1) return null;
		n && Object.assign(s, n);
	}
	if (a) {
		let t = Zm({
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
function Yb(e, t) {
	let n = e.dateSpan.allDay ? null : e.dateSpan.instantStartMs, r = t.dateSpan.allDay ? null : t.dateSpan.instantStartMs;
	return n != null && r != null ? r - n : null;
}
function Xb(e, t, n = {}) {
	let r = Yb(e, t), i = n.date0 || e.dateSpan.range.start, a = n.date1 || t.dateSpan.range.start;
	return {
		delta: r == null ? th(i, a, e.context.dateEnv, n.largeUnit) : B(r),
		instantDeltaMs: r
	};
}
var Zb = class e extends Vv {
	constructor(t) {
		super(t), this.subjectEl = null, this.isDragging = !1, this.eventRange = null, this.relevantEvents = null, this.receivingContext = null, this.validMutation = null, this.mutatedRelevantEvents = null, this.handlePointerDown = (e) => {
			let t = e.origEvent.target, { component: n, dragging: r } = this, { mirror: i } = r, { options: a } = n.context, o = n.context;
			this.subjectEl = e.subjectEl;
			let s = (this.eventRange = rg(e.subjectEl)).instance.instanceId;
			this.relevantEvents = sh(o.getCurrentData().eventStore, s), r.minDistance = e.isTouch ? 0 : a.eventDragMinDistance, r.delay = e.isTouch && s !== n.props.eventSelection ? $b(n) : null, i.parentNode = g_(t), i.revertDuration = a.dragRevertDuration, i.colorScheme = a.colorScheme || "", !n.isValidSegDownEl(t) || t.closest(`.${V.internalEventResizer}`) ? r.cancel() : this.isDragging = e.subjectEl.classList.contains(V.internalEventDraggable);
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
				mutatedEvents: lh(),
				isEvent: !0
			};
			if (e) {
				a = e.context;
				let t = a.options;
				i === a || t.editable && t.droppable ? (o = Qb(r, e, this.eventRange.instance.range.start, a.getCurrentData().pluginHooks.eventDragMutationMassagers), o && (s = Wh(n, a.getCurrentData().eventUiBases, o, a), l.mutatedEvents = s, $y(l, e.dateProfile, a) || (c = !0, o = null, s = null, l.mutatedEvents = lh()))) : a = null;
			}
			this.displayDrag(a, l), c ? $p() : em(), t || (i === a && Hb(r, e) && (o = null), this.dragging.setMirrorNeedsRevert(!o), this.dragging.setMirrorIsVisible(!e || !this.subjectEl.getRootNode().querySelector(`.${V.internalEventMirror}`)), this.receivingContext = a, this.validMutation = o, this.mutatedRelevantEvents = s);
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
							relatedEvents: Qh(l, t, o),
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
							relatedEvents: Qh(c, t, o),
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
							relatedEvents: Qh(l, r, f),
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
							...Ub(u.dateSpan, r),
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
		let { component: n } = this, { options: r } = n.context, i = this.dragging = new Rb(t.el);
		i.pointer.selector = e.SELECTOR, i.touchScrollAllowed = !1, i.autoScroller.isEnabled = r.dragScroll;
		let a = this.hitDragging = new Vb(this.dragging, Wv);
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
				mutatedEvents: lh(),
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
Zb.SELECTOR = `.${V.internalEventDraggable}, .${V.internalEventResizable}`;
function Qb(e, t, n, r) {
	let i = e.dateSpan, a = t.dateSpan, o = i.range.start, s = a.range.start, c = {};
	i.allDay !== a.allDay && (c.allDay = a.allDay, c.hasEnd = t.context.options.allDayMaintainDuration, o = a.allDay ? z(n) : n);
	let { delta: l, instantDeltaMs: u } = Xb(e, t, {
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
function $b(e) {
	let { options: t } = e.context, n = t.eventLongPressDelay;
	return n ??= t.longPressDelay, n;
}
var ex = class extends Vv {
	constructor(e) {
		super(e), this.draggingSegEl = null, this.draggingEventRange = null, this.eventRange = null, this.relevantEvents = null, this.validMutation = null, this.mutatedRelevantEvents = null, this.handlePointerDown = (e) => {
			let { component: t } = this, n = this.querySegEl(e), r = this.eventRange = rg(n);
			this.dragging.minDistance = t.context.options.eventDragMinDistance, (!this.component.isValidSegDownEl(e.origEvent.target) || e.isTouch && this.component.props.eventSelection !== r.instance.instanceId) && this.dragging.cancel();
		}, this.handleDragStart = (e) => {
			let { context: t } = this.component, n = this.eventRange;
			this.relevantEvents = sh(t.getCurrentData().eventStore, this.eventRange.instance.instanceId);
			let r = this.querySegEl(e);
			this.draggingSegEl = r, this.draggingEventRange = rg(r), t.calendarApi.unselect(), t.emitter.trigger("eventResizeStart", {
				el: r,
				event: new K(t, n.def, n.instance),
				jsEvent: e.origEvent,
				view: t.viewApi
			});
		}, this.handleHitUpdate = (e, t, n) => {
			let { context: r } = this.component, i = this.relevantEvents, a = this.hitDragging.initialHit, o = this.eventRange.instance, s = null, c = null, l = !1, u = {
				affectedEvents: i,
				mutatedEvents: lh(),
				isEvent: !0
			};
			e && (e.componentId !== a.componentId || !this.isHitComboAllowed || this.isHitComboAllowed(a, e)) && (s = tx(a, e, n.subjectEl.classList.contains(V.internalEventResizerStart), o.range)), s && (c = Wh(i, r.getCurrentData().eventUiBases, s, r), u.mutatedEvents = c, $y(u, e.dateProfile, r) || (l = !0, s = null, c = null, u.mutatedEvents = null)), c ? r.dispatch({
				type: "SET_EVENT_RESIZE",
				state: u
			}) : r.dispatch({ type: "UNSET_EVENT_RESIZE" }), l ? $p() : em(), t || (s && Hb(a, e) && (s = null), this.validMutation = s, this.mutatedRelevantEvents = c);
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
					relatedEvents: Qh(o, t, r),
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
					startDelta: this.validMutation.startDelta || B(0),
					endDelta: this.validMutation.endDelta || B(0),
					jsEvent: e.origEvent,
					view: t.viewApi
				}), t.emitter.trigger("eventChange", c);
			} else t.emitter.trigger("_noEventResize");
			this.draggingEventRange = null, this.relevantEvents = null, this.validMutation = null;
		};
		let { component: t } = e, n = this.dragging = new Rb(e.el);
		n.pointer.selector = `.${V.internalEventResizer}`, n.touchScrollAllowed = !1, n.autoScroller.isEnabled = t.context.options.dragScroll;
		let r = this.hitDragging = new Vb(this.dragging, Uv(e));
		r.emitter.on("pointerdown", this.handlePointerDown), r.emitter.on("dragstart", this.handleDragStart), r.emitter.on("hitupdate", this.handleHitUpdate), r.emitter.on("dragend", this.handleDragEnd);
	}
	destroy() {
		this.dragging.destroy();
	}
	querySegEl(e) {
		return e.subjectEl.closest(`.${V.internalEvent}`);
	}
};
function tx(e, t, n, r) {
	let { context: i } = e, a = e.dateSpan.range.start, o = t.dateSpan.range.start, { delta: s, instantDeltaMs: c } = Xb(e, t, {
		date0: a,
		date1: o,
		largeUnit: e.largeUnit
	});
	if (n) {
		let e = qh(r.start, r.instantStartMs, s, c ?? void 0, i);
		if (e.instantMs == null ? e.marker < r.end : e.instantMs < Gm(r, i.dateEnv)) return {
			startDelta: s,
			...c == null ? {} : { instantStartDeltaMs: c }
		};
	} else {
		let e = qh(r.end, r.instantEndMs, s, c ?? void 0, i);
		if (e.instantMs == null ? e.marker > r.start : e.instantMs > Wm(r, i.dateEnv)) return {
			endDelta: s,
			...c == null ? {} : { instantEndDeltaMs: c }
		};
	}
	return null;
}
var nx = {
	name: "interaction",
	componentInteractions: [
		Gb,
		Kb,
		Zb,
		ex
	],
	calendarInteractions: [class {
		constructor(e) {
			this.context = e, this.isRecentPointerDateSelect = !1, this.matchesCancel = !1, this.matchesEvent = !1, this.onSelect = (e) => {
				e.jsEvent && (this.isRecentPointerDateSelect = !0);
			}, this.onDocumentPointerDown = (e) => {
				let t = this.context.options.unselectCancel, n = x_(e.origEvent);
				this.matchesCancel = !!n.closest(t), this.matchesEvent = !!n.closest(Zb.SELECTOR);
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
	elementDraggingImpl: Rb
}, rx = class {
	constructor() {
		this.sliceBusinessHours = q(this._sliceBusinessHours), this.sliceDateSelection = q(this._sliceDateSpan), this.sliceEventStore = q(this._sliceEventStore), this.sliceEventDrag = q(this._sliceInteraction), this.sliceEventResize = q(this._sliceInteraction), this.forceDayIfListItem = !1;
	}
	intersectDateSpan(e, t, ...n) {
		let r = Gf(e.range, t);
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
				end: vf(e, 1)
			},
			allDay: !1
		}, t, n, {}, r, ...i);
	}
	_sliceBusinessHours(e, t, n, r, ...i) {
		return e ? this._sliceEventStore(rh(e, ix(t, !!n), r), {}, t, n, ...i).bg : [];
	}
	_sliceEventStore(e, t, n, r, ...i) {
		if (e) {
			let a = $h(e, t, ix(n, !!r), r);
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
		let a = $h(e.mutatedEvents, t, ix(n, !!r), r);
		return {
			segs: this.sliceEventRanges(a.fg, i),
			affectedInstances: e.affectedEvents.instances,
			isEvent: e.isEvent
		};
	}
	_sliceDateSpan(e, t, n, r, i, ...a) {
		if (!e) return [];
		let o = ix(t, !!n), s = this.intersectDateSpan(e, o, ...a);
		if (s) {
			e = s;
			let t = Rh(e, r, i), n = this.sliceDateSpan(e, ...a);
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
			end: R(n.start, 1)
		});
		let r = this.sliceRange(n, ...t);
		for (let t of r) t.eventRange = e, t.isStart = e.isStart && t.isStart, t.isEnd = e.isEnd && t.isEnd;
		return r;
	}
};
function ix(e, t) {
	let n = e.activeRange;
	return t ? n : {
		start: vf(n.start, e.slotMinTime.milliseconds),
		end: vf(n.end, e.slotMaxTime.milliseconds - 864e5)
	};
}
var ax = class {
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
			isDisabled: this.activeRange === null || this.activeRange !== void 0 && !Yf(this.activeRange, n)
		};
	}
	cellIsMajor(e) {
		return this.majorUnit ? Av(e, this.majorUnit, this.dateEnv) : !1;
	}
	buildHeaderDates() {
		let e = [];
		for (let t = 0; t < this.colCount; t += 1) e.push(this.cellRows[0][t].date);
		return e;
	}
};
function ox(e, t) {
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
var sx = class extends rx {
	constructor() {
		super(...arguments), this.forceDayIfListItem = !0;
	}
	sliceRange(e, t) {
		return ox(t.daySeries.sliceRange(e), t.colCount);
	}
}, cx = class extends rx {
	constructor() {
		super(...arguments), this.forceDayIfListItem = !0;
	}
	sliceRange(e, t) {
		return ox(t.sliceRange(e), t.cnt);
	}
}, lx = /* @__PURE__ */ new Date(2592e5);
function ux(e, t, n, r, i, a) {
	let o = dx(e, t, n, r, i, a), s = kv(n, a.dateEnv);
	if (t && s !== "day") for (let e of o.dataConfigs) Av(e.dateMarker, s, a.dateEnv) && (e.renderProps.isMajor = !0);
	return [o];
}
function dx(e, t, n, r, i, a, o, s, c) {
	return {
		isDateRow: !0,
		renderConfig: fx(i, t, a),
		dataConfigs: mx(e, t, n, r, i, a, o, void 0, void 0, void 0, void 0, s, c)
	};
}
function fx(e, t, n) {
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
var px = [];
for (let e = 0; e < 7; e++) px.push(R(/* @__PURE__ */ new Date(2592e5), e));
function mx(e, t, n, r, i, a, o = 1, s = "", c = {}, l = {}, u = "", d, f = e.length) {
	let { dateEnv: p, viewApi: m, options: h } = a;
	return t ? e.map((e, t) => {
		let i = fv(e, p, n, r), g = d != null && !(t % d), _ = h.navLinks && !i.isDisabled && f > 1, v = {
			...i,
			...c,
			isMajor: g,
			isSticky: !1,
			inPopover: !1,
			hasNavLink: _,
			view: m
		}, y = uv(a, e);
		return {
			key: s + e.toUTCString(),
			dateMarker: e,
			renderProps: v,
			attrs: {
				...i.isToday ? { "aria-current": "date" } : {},
				"data-date": gp(e),
				...l
			},
			innerAttrs: _ ? dv(a, e, void 0, y, !0, !0) : void 0,
			a11yText: _ ? void 0 : y,
			colSpan: o,
			hasNavLink: _,
			className: u
		};
	}) : e.map((e, t) => {
		let n = e.getUTCDay(), r = R(lx, n), i = {
			date: p.toDate(e),
			dow: n,
			isDisabled: !1,
			isFuture: !1,
			isPast: !1,
			isToday: !1,
			isOther: !1
		}, a = d != null && !(t % d), f = {
			...i,
			date: px[n],
			isMajor: a,
			isSticky: !1,
			inPopover: !1,
			hasNavLink: !1,
			view: m,
			...c
		}, h = bp(p.formatToParts(r, ov));
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
			e === null ? i && !this.ignoreDeletes && (n.delete(t), r.delete(t), o = !0) : (o = a !== e, n.set(t, e)), o && (this.rev = Qp(), this.masterCallback && this.masterCallback(e, t, a));
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
		return O("div", { ref: this.elRef });
	}
	componentDidMount() {
		this._isUnmounting = !1;
		let { props: e } = this, t = this.elRef.current;
		this.disconnectWidth = tv(t, (t) => {
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
			this.el = e, e && ng(e, this.props.eventRange);
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
		}, c = H(i.className, V.fill, V.internalEvent, V.internalBgEvent, e.isVertical ? V.flexCol : V.flexRow), l = H(Z(r.backgroundEventInnerClass, s), V.liquid);
		return O(X, {
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
			children: (e) => O(e, {
				tag: "div",
				className: l
			})
		});
	}
	componentDidUpdate(e) {
		this.el && this.props.eventRange !== e.eventRange && ng(this.el, this.props.eventRange);
	}
};
function xx(e) {
	let { title: t } = e.event;
	return t && O("div", {
		className: e.titleClass,
		children: e.event.title
	});
}
function Sx(e, t) {
	return O("div", { className: H(e === "non-business" ? t.nonBusinessHoursClass : e === "highlight" ? t.highlightClass : void 0, V.fill) });
}
var Cx = 1, wx = 1, Tx = 10, Ex = class extends hv {
	constructor() {
		super(...arguments), this.getDateMeta = q(fv), this.closeRef = C(), this.focusStartRef = C(), this.focusEndRef = C(), this.handleRootEl = (e) => {
			this.rootEl = e, e ? this.context.registerInteractiveComponent(this, {
				el: e,
				useEventCenter: !1
			}) : this.context.unregisterInteractiveComponent(this);
		}, this.handleDocumentMouseDown = (e) => {
			let t = x_(e);
			this.rootEl.contains(t) || this.handleClose();
		}, this.handleDocumentKeyDown = (e) => {
			e.key === "Escape" && this.handleClose();
		}, this.handleClose = () => {
			let { onClose: e } = this.props;
			e && e();
		};
	}
	render() {
		let { props: e, context: t } = this, { options: n, dateEnv: r, viewApi: i } = t, { startDate: a, todayRange: o, dateProfile: s } = e, c = this.getDateMeta(a, r, s, o), l = r.formatToParts(a, n.popoverFormat), u = bp(l), d = {
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
				return sv(l);
			},
			get dayNumberText() {
				return cv(l);
			},
			view: i
		}, f = {
			...e.renderProps,
			...c,
			isMajor: !1,
			isNarrow: !1,
			inPopover: !0,
			hasNavLink: !1,
			get weekdayText() {
				return sv(l);
			},
			get dayNumberText() {
				return cv(l);
			},
			get monthText() {
				return lv(l);
			},
			view: i,
			text: "",
			textParts: [],
			options: { businessHours: !!n.businessHours }
		}, p = gp(a), { dayHeaderAlign: m } = n, h = typeof m == "function" ? m({
			level: 0,
			inPopover: !0,
			isNarrow: !1
		}) : m, g = __(e.alignEl);
		return Qe(O("div", {
			"data-date": p,
			id: e.id,
			role: "dialog",
			"aria-labelledby": e.titleId,
			className: H(n.popoverClass, V.flexCol, V.popoverZ, V.abs, V.borderBoxRoot, V.internalPopover),
			style: {
				top: 0,
				left: 0
			},
			dir: g ? "rtl" : void 0,
			"data-color-scheme": n.colorScheme || void 0,
			ref: this.handleRootEl,
			children: [
				O("div", {
					tabIndex: 0,
					style: { outline: "none" },
					ref: this.focusStartRef
				}),
				O("div", {
					className: H(Z(n.dayHeaderClass, d), V.flexCol, V.borderlessX, V.borderlessTop, h === "center" ? V.alignCenter : h === "end" ? V.alignEnd : V.alignStart),
					children: [O("div", { children: O(X, {
						tag: "div",
						attrs: { id: e.titleId },
						generatorName: "dayHeaderContent",
						renderProps: d,
						customGenerator: n.dayHeaderContent,
						defaultGenerator: Dx,
						classNameGenerator: n.dayHeaderInnerClass,
						didMount: n.dayHeaderDidMount,
						willUnmount: n.dayHeaderWillUnmount
					}) }), O(X, {
						tag: "button",
						attrs: {
							"aria-label": n.closeHint,
							...Yp(this.handleClose)
						},
						elRef: this.closeRef,
						className: H(n.popoverCloseClass, V.flexRow, V.cursorPointer),
						renderProps: {},
						customGenerator: n.popoverCloseContent,
						generatorName: "popoverCloseContent"
					})]
				}),
				O("div", {
					className: H(Z(n.dayCellClass, f), V.flexCol, V.borderless),
					children: O("div", {
						role: "list",
						className: Z(n.dayCellInnerClass, f),
						children: e.children
					})
				}),
				O("div", {
					tabIndex: 0,
					style: { outline: "none" },
					ref: this.focusEndRef
				})
			]
		}), g_(e.alignEl));
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
		let { alignEl: e, alignParentTop: t } = this.props, { rootEl: n } = this, r = __(e), i = _b(e);
		if (i) {
			let a = n.getBoundingClientRect(), o = t ? e.closest(t).getBoundingClientRect().top - wx : i.top, s = r ? i.right - a.width : i.left;
			o = Math.max(o, Tx), s = Math.min(s, document.documentElement.clientWidth - Tx - a.width), s = Math.max(s, Tx);
			let { offsetParent: c } = n, l, u;
			if (!c || c === document.body) l = o + window.scrollY, u = s + window.scrollX;
			else {
				let e = c.getBoundingClientRect();
				l = o - e.top + c.scrollTop, u = s - e.left + c.scrollLeft;
			}
			y_(n, {
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
		return O(X, {
			tag: "div",
			elRef: e.elRef,
			className: H(Z(e.display === "row" ? n.rowMoreLinkClass : n.columnMoreLinkClass, r), e.className, e.display === "row" ? V.flexRow : V.flexCol, V.internalMoreLink, V.cursorPointer),
			style: e.style,
			attrs: e.attrs,
			renderProps: r,
			generatorName: "moreLinkContent",
			customGenerator: n.moreLinkContent,
			defaultGenerator: Px,
			classNameGenerator: n.moreLinkClass,
			didMount: e.didMount,
			willUnmount: e.willUnmount,
			children: (t) => O(t, {
				tag: "div",
				className: H(Z(n.moreLinkInnerClass, r), Z(e.display === "row" ? n.rowMoreLinkInnerClass : n.columnMoreLinkInnerClass, r), e.display === "row" ? V.stickyS : V.stickyT)
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
				let { def: t, instance: i, range: a } = e.eventRange, o = Um(a.start, a.instantStartMs, r), s = Um(a.end, a.instantEndMs, r);
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
		let { props: e, state: t, context: n } = this, { options: r, baseId: i } = n, a = e.hiddenSegs.length, o = Ix(e), s = i + "popover-" + o.start.toISOString(), c = Fx(a, e.isNarrow, e.isMicro, e.display, e.renderProps, n), l = lm(r.moreLinkHint, [a], c.longText);
		return O(w, { children: [!!a && O(Mx, {
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
				...Yp(this.handleClick),
				title: l,
				role: "button",
				"aria-haspopup": "dialog",
				"aria-expanded": t.isPopoverOpen,
				"aria-controls": t.isPopoverOpen ? s : void 0
			},
			didMount: r.moreLinkDidMount,
			willUnmount: r.moreLinkWillUnmount
		}), t.isPopoverOpen && O(Ex, {
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
		end: R(e.allDayDate, 1)
	} : {
		start: Ox(e.hiddenSegs),
		end: kx(e.hiddenSegs)
	};
}
var Lx = U({
	hour: "numeric",
	minute: "2-digit",
	omitZeroMinute: !0,
	meridiem: "narrow"
});
function Rx(e, t) {
	let { display: n } = t.ui;
	return n === "list-item" || n === "auto" && !t.def.allDay && e.end - e.start === 1 && e.isStart && e.isEnd;
}
var zx = V.z1, Bx = V.z2, Vx = V.z3, Hx = V.z4, Ux = V.z5, Wx = V.z1000, Gx = class extends J {
	render() {
		let { props: e } = this;
		return O(Nx, {
			display: "row",
			className: H(e.className, Hx),
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
			popoverContent: () => O(w, { children: e.segs.map((t) => {
				let { eventRange: n } = t, { instanceId: r } = n.instance, i = !!(e.eventDrag && e.eventDrag.affectedInstances[r]), a = !!(e.eventResize && e.eventResize.affectedInstances[r]);
				return O("div", {
					role: "listitem",
					style: { visibility: i || a ? "hidden" : void 0 },
					children: O(Ev, {
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
						...fg(n, e.todayRange)
					})
				}, r);
			}) })
		});
	}
}, Kx = class extends hv {
	constructor() {
		super(...arguments), this.getDateMeta = q(qx), this.refineRenderProps = S_(Xx), this.rootElRef = C(), this.handleBodyEl = (e) => {
			this.disconnectBodyHeight && (this.disconnectBodyHeight(), this.disconnectBodyHeight = void 0, this.headerHeight = void 0, Y(this.props.headerHeightRef, null), Y(this.props.mainHeightRef, null)), e && (this.props.headerHeightRef || this.props.mainHeightRef) && (this.disconnectBodyHeight = ev(e, (t, n) => {
				if (this._isUnmounting) return;
				let { props: r } = this, i = this.rootElRef.current;
				if (!i) return;
				let a = e.getBoundingClientRect(), o = i.getBoundingClientRect(), s = a.top - o.top;
				W_(this.headerHeight, s) || (this.headerHeight = s, Y(r.headerHeightRef, s)), Y(r.mainHeightRef, n);
			}));
		};
	}
	render() {
		let { props: e, context: t } = this, { options: n, dateEnv: r } = t, { tableMode: i } = e, a = e.showDayNumber && Yx(e.date, e.dateProfile.currentRange, r), o = this.getDateMeta(e.date, r, e.dateProfile, e.todayRange, e.isDisabled), s = H(V.borderlessTop, V.borderlessEnd, !e.borderStart && V.borderlessStart, !(i && e.borderBottom) && V.borderlessBottom, !i && e.width == null && V.liquid, !i && V.flexCol, V.rel, V.noMargin, V.noPadding), c = i ? "td" : "div", l = i ? void 0 : { width: e.width }, u = n.navLinks, d = this.refineRenderProps({
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
		if (o.isDisabled) return O(c, {
			role: "cell",
			"aria-disabled": !0,
			className: H(Z(n.dayCellClass, d), e.className, s),
			style: l,
			children: e.fills
		});
		let f = uv(t, e.date), p = e.showDayNumber && !u;
		return O(X, {
			tag: c,
			elRef: this.rootElRef,
			className: H(e.className, s),
			attrs: {
				...e.attrs,
				role: "cell",
				...d.isToday ? { "aria-current": "date" } : {},
				"data-date": gp(e.date)
			},
			style: l,
			renderProps: d,
			generatorName: "dayCellTopContent",
			customGenerator: n.dayCellTopContent,
			defaultGenerator: Jx,
			classNameGenerator: n.dayCellClass,
			didMount: n.dayCellDidMount,
			willUnmount: n.dayCellWillUnmount,
			children: (r) => O(w, { children: [
				e.fills,
				O("div", {
					role: "presentation",
					className: H(V.rel, Hx, Z(n.dayCellTopClass, d)),
					children: [p && O("div", {
						className: V.srOnly,
						children: f
					}), e.showDayNumber && O(r, {
						tag: "div",
						attrs: u ? dv(t, e.date, void 0, f) : { "aria-hidden": !0 },
						className: Z(n.dayCellTopInnerClass, d)
					})]
				}),
				O("div", {
					className: H(!i && V.flexCol, !i && (e.fgLiquidHeight ? V.liquid : V.grow), i && V.printCellContentMinHeight),
					ref: this.handleBodyEl,
					children: [O("div", {
						role: e.fgIsList ? "list" : "presentation",
						className: H(V.rel, Z(n.dayCellInnerClass, d)),
						style: { minHeight: e.fgHeight },
						children: e.fg
					}), O(Gx, {
						className: V.rel,
						allDayDate: e.date,
						segs: e.segs,
						hiddenSegs: e.hiddenSegs,
						alignElRef: this.rootElRef,
						alignParentTop: e.showDayNumber ? "[role=row]" : `.${V.internalView}`,
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
				O("div", {
					role: "presentation",
					className: H(V.rel, Hx, Z(n.dayCellBottomClass, d))
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
		...fv(e, t, n, r),
		isDisabled: i
	};
}
function Jx(e) {
	return e.text || O(w, { children: "\xA0" });
}
function Yx(e, t, n) {
	let { start: r, end: i } = t, a = vf(i, -1), o = n.getYear(r), s = n.getMonth(r), c = n.getYear(a), l = n.getMonth(a);
	return (o !== c || s !== l) && (e.valueOf() === r.valueOf() || n.getDay(e) === 1 && e.valueOf() < i.valueOf());
}
function Xx(e) {
	let { date: t, dateEnv: n, hasLabel: r, hasMonthLabel: i, hasNavLink: a, businessHours: o } = e, s = [], c = "";
	return r && (s = n.formatToParts(t, i ? e.monthStartFormat : e.dayCellFormat), c = bp(s)), {
		...e.dateMeta,
		...e.renderProps,
		text: c,
		textParts: s,
		isMajor: e.isMajor,
		isNarrow: e.isNarrow,
		inPopover: !1,
		hasNavLink: a,
		get weekdayText() {
			return sv(s);
		},
		get dayNumberText() {
			return cv(s);
		},
		get monthText() {
			return lv(s);
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
		return O("div", {
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
		this.disconnectHeight = nv(e, (e) => {
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
	let { segLevels: o, excludedSegs: s } = lS(e, t, r), c = vS(dS(o), fS(s), t, n, i), l = pS(c.sliceLevels, a);
	return {
		renderSlices: pm(c.sliceLevels),
		hiddenSlices: c.hiddenSlices,
		sliceLevels: c.sliceLevels,
		sliceCoords: l.sliceCoords,
		isSettled: l.isSettled
	};
}
function cS(e, t, n, r, i, a, o) {
	let { segLevels: s, excludedSegs: c } = lS(e, t, a), l = dS(s), u = fS(c), d = pS(l, r, i);
	if (i == null || o == null) return {
		renderSlices: pm(l),
		hiddenSlices: u,
		sliceLevels: l,
		sliceCoords: d.sliceCoords,
		isSettled: d.isSettled
	};
	let f = d.excludedSlices.concat(u);
	f.sort(jS);
	let p = vS(d.placementSliceLevels, f, t, n, +!!n, !0, !0), m = pS(p.sliceLevels, r), h = Math.max(0, i - o), g = _S(p.sliceLevels, p.hiddenSlices, m.sliceCoords, r, i, h), _ = pm(l).concat(p.addedSlices), v = d.isSettled && m.isSettled;
	return {
		renderSlices: _,
		hiddenSlices: g.concat(p.hiddenSlices),
		sliceLevels: p.sliceLevels,
		sliceCoords: m.sliceCoords,
		isSettled: v
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
	return e.map(RS);
}
function pS(e, t, n = Infinity) {
	let r = [], i = /* @__PURE__ */ new Map(), a = !0, o = [];
	for (let s = 0; s < e.length; s++) for (let c of e[s]) {
		let e = t.get(kS(c));
		if (e === void 0) {
			a = !1;
			continue;
		}
		let { bottom: s, levelIndex: l } = mS(r, c, i, t);
		if (s + e <= n + oS) {
			for (; r.length <= l;) r.push([]);
			iS(r[l], c), i.set(kS(c), s);
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
			let t = kS(e), s = n.get(t), c = r.get(t);
			s !== void 0 && c !== void 0 && (i = Math.max(i, s + c), a = o + 1);
		}
	}
	return {
		bottom: i,
		levelIndex: a
	};
}
function hS(e, t, n) {
	let r = pS(e.map((e) => e.filter((e) => n.has(kS(e)))), t).sliceCoords;
	for (let [e, t] of r) n.set(e, t);
}
function gS(e, t, n) {
	let r = kS(e), i = t.get(r), a = n.get(r);
	return i === void 0 || a === void 0 ? void 0 : i + a;
}
function _S(e, t, n, r, i, a) {
	let o = [], s = [], c = [], l = 0;
	for (let e of t) DS(o, e);
	for (d(); l < c.length;) {
		let t = c[l++], i = gS(t, n, r);
		if (i === void 0 || !u(t, i)) continue;
		n.delete(kS(t)), s.push(t);
		let a = DS(o, t);
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
function vS(e, t, n, r, i, a = !1, o = !1) {
	let s = /* @__PURE__ */ new Set(), c = [], l = [], u = [], d = {
		levels: e,
		moreLinkReservations: u,
		eventOrderStrict: n
	}, f = [];
	for (g(t, a); f.length;) {
		let e = f.pop();
		e.type === "fire" ? p(e.slice, e.requiresSlicing) : h(e.span);
	}
	return {
		sliceLevels: e,
		hiddenSlices: c,
		addedSlices: [...s]
	};
	function p(t, n) {
		if (!n) {
			let n = yS(t, d);
			if (n !== null) {
				iS(e[n], t), s.add(t);
				return;
			}
		}
		if (!r) {
			m(t);
			return;
		}
		let i = CS(t, d, n);
		if (!i) m(t);
		else {
			for (let t of i.slices) iS(e[i.levelIndex], t), s.add(t);
			for (let e of IS(t, i.slices)) m(e);
		}
	}
	function m(e) {
		c.push(e);
		let t = DS(l, e);
		if (i) for (let e = t.length - 1; e >= 0; e--) f.push({
			type: "moreLink",
			span: t[e]
		});
	}
	function h(t) {
		if (!e.length) return;
		let n = e.length - 1, i = tS(e[n], t);
		if (o) for (; !i.length && n > 0;) n--, i = tS(e[n], t);
		iS(u, {
			...t,
			levelIndex: n
		});
		let a = e[n];
		for (let e of i) a.splice(a.indexOf(e), 1), s.delete(e), r ? (m(LS(e, t)), g(IS(e, [t]), !1)) : m(e);
	}
	function g(e, t) {
		for (let n = e.length - 1; n >= 0; n--) f.push({
			type: "fire",
			slice: e[n],
			requiresSlicing: t
		});
	}
}
function yS(e, t) {
	let n = bS(e, t);
	for (let r = n.min; r < n.maxExclusive; r++) if (!tS(t.levels[r], e).length) return r;
	return null;
}
function bS(e, t) {
	let { levels: n } = t, r = 0, i = n.length;
	for (let n of tS(t.moreLinkReservations, e)) i = Math.min(i, n.levelIndex);
	if (t.eventOrderStrict) for (let t = 0; t < n.length; t++) for (let a of tS(n[t], e)) a.sourceSeg.orderIndex < e.sourceSeg.orderIndex ? r = Math.max(r, t + 1) : a.sourceSeg.orderIndex > e.sourceSeg.orderIndex && (i = Math.min(i, t));
	return {
		min: r,
		maxExclusive: i
	};
}
var xS = 3, SS = .15;
function CS(e, t, n) {
	let r = null, i = eS(e);
	for (let a = 0; a < t.levels.length; a++) {
		let o = tS(t.levels[a], e);
		for (let e of t.moreLinkReservations) a >= e.levelIndex && rS(o, e);
		let s = IS(e, o).filter((e) => wS(e, a, t)).sort((e, t) => eS(t) - eS(e) || e.start - t.start), c = 0;
		for (let e = 1; e <= Math.min(xS, s.length) && (c += eS(s[e - 1]), !(n && c >= i - oS)); e++) {
			let t = {
				levelIndex: a,
				slices: s.slice(0, e),
				score: c / i - SS * (e - 1)
			};
			TS(t, r) && (r = t);
		}
	}
	return r && r.slices.sort(jS), r;
}
function wS(e, t, n) {
	let r = bS(e, n);
	return t >= r.min && t < r.maxExclusive;
}
function TS(e, t) {
	return !t || e.score > t.score ? !0 : e.score < t.score ? !1 : e.slices.length === t.slices.length ? e.levelIndex < t.levelIndex : e.slices.length < t.slices.length;
}
function ES(e) {
	let t = [];
	for (let n of e) DS(t, n);
	return OS(t);
}
function DS(e, t) {
	let n = nS(t, e), r = [], i = [t], a = t.start, o = t.end;
	for (let n of e) $x(n, t) ? (i.push(...n.hiddenSlices), a = Math.min(a, n.start), o = Math.max(o, n.end)) : r.push(n);
	return i.sort(jS), iS(r, {
		start: a,
		end: o,
		hiddenSlices: i
	}), e.splice(0, e.length, ...r), n;
}
function OS(e) {
	return e.map((e) => {
		let t = FS(e.hiddenSlices);
		return {
			key: kS(t[0]),
			start: e.start,
			end: e.end,
			hiddenSlices: t
		};
	});
}
function kS(e) {
	return AS(e) ? `${e.sourceSeg.key}:${e.start}:slice` : e.sourceSeg.key;
}
function AS(e) {
	return e.start !== e.sourceSeg.start || e.end !== e.sourceSeg.end;
}
function jS(e, t) {
	return e.sourceSeg.orderIndex - t.sourceSeg.orderIndex || e.start - t.start || t.end - e.end;
}
function MS(e) {
	return [...e].sort(jS);
}
function NS(e, t) {
	return e.start - t.start || e.sourceSeg.orderIndex - t.sourceSeg.orderIndex;
}
function PS(e) {
	return [...e].sort(NS);
}
function FS(e) {
	let t = [];
	for (let n of e) {
		let e = t[t.length - 1];
		e && e.sourceSeg === n.sourceSeg ? t[t.length - 1] = zS(RS(e.sourceSeg), e.start, Math.max(e.end, n.end)) : t.push(n);
	}
	return t;
}
function IS(e, t) {
	return nS(e, t).map((t) => zS(e, t.start, t.end));
}
function LS(e, t) {
	let n = $x(e, t);
	return n ? zS(e, n.start, n.end) : null;
}
function RS(e) {
	return {
		sourceSeg: e,
		start: e.start,
		end: e.end,
		isStart: e.isStart,
		isEnd: e.isEnd
	};
}
function zS(e, t, n) {
	return {
		sourceSeg: e.sourceSeg,
		start: t,
		end: n,
		isStart: e.isStart && t === e.start,
		isEnd: e.isEnd && n === e.end
	};
}
var BS = ZS(150, 20);
function VS(e) {
	return e.map((e, t) => ({
		...e,
		key: _x(e),
		orderIndex: t
	}));
}
function HS(e, t, n, r, i, a, o) {
	let s = VS(e);
	return YS(s, sS(s, r, i, t, n, o), o, a);
}
function US(e, t, n, r, i, a, o, s) {
	let c = VS(e);
	return YS(c, cS(c, t, n, s, i, o, a), s, r);
}
function WS(e, t, n) {
	return {
		segs: mm(e, (e) => GS(e, n) ?? []),
		hiddenSegs: mm(t, (e) => GS(e.sourceSeg, n, e) ?? [])
	};
}
function GS(e, t, n = e) {
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
function KS(e, t) {
	return e === !0 || t === !0 ? "auto" : typeof e == "number" ? "maxEvents" : typeof t == "number" ? "maxEventRows" : "unlimited";
}
function qS(e, t, n, r) {
	switch (e) {
		case "auto": return r;
		case "maxEvents": return t;
		case "maxEventRows": return n;
		default: return Infinity;
	}
}
function JS(e) {
	return +(e === "maxEventRows");
}
function YS(e, t, n, r) {
	let { hiddenSlices: i, renderSlices: a, sliceCoords: o } = t, s = MS(i), c = XS(a, r), l = Array.from({ length: r }, (t, n) => ({
		renderSlices: c[n],
		contentHeight: 0,
		...WS(e, s, n)
	}));
	for (let e of a) {
		let t = kS(e), r = o.get(t);
		if (r === void 0) continue;
		let i = r + n.get(t);
		for (let t = e.start; t < e.end; t += 1) l[t].contentHeight = Math.max(l[t].contentHeight, i);
	}
	return {
		columns: l,
		sliceCoords: o
	};
}
function XS(e, t) {
	let n = Array.from({ length: t }, () => []);
	for (let t of e) n[t.start].push(t);
	for (let e of n) e.sort(jS);
	return n;
}
function ZS(e, t) {
	return Math.max(1, Math.ceil(e / t));
}
var QS = 200;
function $S(e, t, n) {
	let { segLevels: r, excludedSegs: i } = lS(e, t, QS), a = vS(dS(r), fS(i), t, n, 0);
	return {
		sliceLevels: a.sliceLevels,
		hiddenSlices: a.hiddenSlices
	};
}
function eC(e, t, n = (e) => e.sourceSeg.key, r = 20) {
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
function tC(e, t, n, r) {
	let i = VS(e), a = $S(i, t, n);
	return {
		...a,
		hiddenSlices: MS(a.hiddenSlices),
		sourceSegs: i,
		columnCount: r
	};
}
function nC(e, t) {
	let n = Array.from({ length: e.columnCount }, () => []);
	for (let r of eC(e.sliceLevels, t, rC)) {
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
function rC(e) {
	return `${e.sourceSeg.key}:${e.start}:${e.end}`;
}
var iC = U({ week: "narrow" }), aC = class extends J {
	constructor() {
		super(...arguments), this.headerHeightRefMap = new hx(() => {
			Q(this.handleSegPositioning);
		}), this.mainHeightRefMap = new hx(() => {
			(this.props.dayMaxEvents === !0 || this.props.dayMaxEventRows === !0) && Q(this.handleSegPositioning);
		}), this.sliceHeightRefMap = new hx(() => {
			Q(this.handleSegPositioning);
		}), this.handlePrintSegHeightChange = () => {
			Q(this.handlePrintSegHeights);
		}, this.printSegHeightRefMap = new hx(this.handlePrintSegHeightChange), this.buildWeekNumberRenderProps = q(oC), this.buildPrintPlan = q(tC), this.sortEventSegs = q(og), this.levelCapacity = BS, this.handleRootEl = (e) => {
			this.disconnectHeight?.(), this.disconnectHeight = void 0, Y(this.props.rootElRef, e), e ? this.disconnectHeight = nv(e, (e) => {
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
		if (e.forPrint) u = this.buildPrintPlan(c, o.eventOrderStrict, o.eventSlicing, i.length), d = nC(u, this.printSegHeightRefMap.current);
		else {
			let t = KS(e.dayMaxEvents, e.dayMaxEventRows), [r, a] = this.computeFgDims(), s = t === "auto" ? US(c, o.eventOrderStrict, o.eventSlicing, i.length, a, e.moreLinkHeight, this.levelCapacity, this.sliceHeightRefMap.current) : HS(c, qS(t, e.dayMaxEvents, e.dayMaxEventRows, Infinity), JS(t), o.eventOrderStrict, o.eventSlicing, i.length, this.sliceHeightRefMap.current);
			if (f = s.columns, p = s.sliceCoords, r != null) for (let e = 0; e < i.length; e++) {
				let t = n.current.get(i[e].key), a = t == null ? void 0 : r - t;
				m.push(a), h.push(a == null ? void 0 : f[e].contentHeight + a);
			}
		}
		let g = this.getHighlightSegs(), _ = o.navLinks, v = uv(t, s, "week"), y = this.buildWeekNumberRenderProps(s, t, e.cellIsNarrow, _), b = i.map(() => []), x = e.showWeekNumbers && !e.cellIsMicro ? O(X, {
			tag: "div",
			attrs: {
				..._ ? dv(t, s, "week", v, !1) : {},
				role: void 0,
				"aria-hidden": !0
			},
			className: Ux,
			renderProps: y,
			generatorName: "inlineWeekNumberContent",
			customGenerator: o.inlineWeekNumberContent,
			defaultGenerator: N_,
			classNameGenerator: o.inlineWeekNumberClass,
			didMount: o.inlineWeekNumberDidMount,
			willUnmount: o.inlineWeekNumberWillUnmount
		}) : null;
		return a && x && b[0].push(O("div", {
			className: H(V.fillY, V.start0, V.pointerEventsNone),
			style: { width: this.computeSpanWidth(0, i.length) },
			children: x
		}, "week-number")), this.appendFillSegs(b, e.businessHourSegs, "non-business", zx), this.appendFillSegs(b, e.bgEventSegs, "bg-event", Bx), this.appendFillSegs(b, g, "highlight", Vx), O(a ? "tr" : "div", {
			role: e.role,
			"aria-label": e.role === "row" ? v : void 0,
			className: H(o.dayRowClass, e.className, a && V.borderless, !a && V.flexRow, !a && V.rel, !a && V.borderlessX, !a && V.borderlessTop, !a && !e.borderBottom && V.borderlessBottom, V.isolate),
			style: { flexBasis: a ? void 0 : e.basis },
			ref: this.handleRootEl,
			children: [!a && x, e.cells.map((t, i) => {
				let o = u ? WS(u.sourceSegs, u.hiddenSlices, i) : null, s = u ? null : f[i].renderSlices, c;
				return c = u ? this.renderPrintBandSlots(d[i]) : [...this.renderLevelFgSegs(m[i], s, p), ...this.renderMirrorFgSegs(i, m[i], p)], O(Kx, {
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
			a.push(O(Zx, {
				attrs: { "aria-hidden": !0 },
				className: H(V.abs, V.start0, Wx),
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
			let t = kS(o), s = n.get(t), { eventRange: c } = o.sourceSeg, { instanceId: l } = c.instance, u = e != null && s != null ? e + s : void 0, d = !!(r.eventDrag && r.eventDrag.affectedInstances[l]), f = !!(r.eventResize && r.eventResize.affectedInstances[l]), p = d || f || u == null, m = l === i;
			a.push(O(Zx, {
				attrs: { role: "listitem" },
				className: H(V.abs, V.start0, m ? Wx : Ux),
				style: {
					visibility: p ? "hidden" : void 0,
					top: u,
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
		return O(Ev, {
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
			...fg(t, r.todayRange)
		});
	}
	renderPrintBandSlots(e) {
		let { printSegHeightRefMap: t } = this;
		return e.map((e) => {
			let { slice: n } = e, r = null;
			if (n) {
				let e = rC(n);
				r = O(Zx, {
					className: H(V.rel, V.flowRoot, Ux),
					style: { width: this.computeSpanWidth(n.start, n.end) },
					heightRef: t.createRef(e),
					children: this.renderEventContent(n, n.sourceSeg.eventRange, {})
				}, e);
			}
			return O("div", {
				className: V.breakInsideAvoid,
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
		for (let s of t) e[s.start].push(O("div", {
			"aria-hidden": !0,
			className: H(V.fillY, V.start0, r),
			style: { width: this.computeSpanWidth(s.start, s.end) },
			children: n === "bg-event" ? O(bx, {
				eventRange: s.eventRange,
				isStart: s.isStart,
				isEnd: s.isEnd,
				isNarrow: i.cellIsNarrow,
				isVertical: !1,
				renderProps: i.cells[s.start]?.renderProps,
				...fg(s.eventRange, o)
			}) : Sx(n, a.options)
		}, `${n}:${pg(s.eventRange)}:${s.start}:${s.end}`));
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
		if (KS(this.props.dayMaxEvents, this.props.dayMaxEventRows) !== "auto") return;
		let [, e] = this.computeFgDims();
		if (e != null) {
			let t = Math.min(...this.sliceHeightRefMap.current.values());
			this.levelCapacity = Math.max(this.levelCapacity, ZS(e, t));
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
function oC(e, t, n, r) {
	let { dateEnv: i, options: a } = t, o = i.computeWeekNumber(e), s = i.formatToParts(e, a.weekNumberFormat || iC);
	return {
		num: o,
		text: bp(s),
		textParts: s,
		date: i.toDate(e),
		isNarrow: n,
		hasNavLink: r
	};
}
var sC = class {
	constructor(e, t) {
		let n = e.start, { end: r } = e, i = [], a = [], o = -1;
		for (; n < r;) t.isHiddenDay(n) ? i.push({
			kind: "hidden",
			previousIndex: o,
			nextIndex: o + 1
		}) : (o += 1, i.push({
			kind: "visible",
			index: o
		}), a.push(n)), n = R(n, 1);
		this.rangeStart = e.start, this.dates = a, this.entries = i, this.cnt = a.length;
	}
	sliceRange(e) {
		let t = this.getDateIndex(e.start), n = this.getDateIndex(R(e.end, -1)), r = cC(t), i = lC(n), a = Math.max(0, r), o = Math.min(this.cnt - 1, i);
		return a <= o ? {
			start: a,
			end: o + 1,
			isStart: t.kind === "visible" && r === a,
			isEnd: n.kind === "visible" && i === o
		} : null;
	}
	getDateIndex(e) {
		let t = Math.floor(bf(this.rangeStart, e));
		return t < 0 ? {
			kind: "before",
			index: -1
		} : t >= this.entries.length ? {
			kind: "after",
			index: this.cnt
		} : this.entries[t];
	}
};
function cC(e) {
	return e.kind === "hidden" ? e.nextIndex : e.index;
}
function lC(e) {
	return e.kind === "hidden" ? e.previousIndex : e.index;
}
function uC(e, t, n) {
	let r = new sC(e.renderRange, t), i = /year|month|week/.test(e.currentRangeUnit), a = !i && kv(e, n);
	return new ax(r, i, n, a === "day" ? void 0 : a, e.activeRange);
}
function dC(e, t, n) {
	return n == null ? [void 0, void 0] : n / e < t ? [t * e, t] : [n, void 0];
}
function fC(e, t, n) {
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
function pC(e, t, n, r, i) {
	let a = n ?? t / r, o = Math.floor(e / a), s = i ? r - o - 1 : o, c = o * a;
	return {
		col: s,
		left: c,
		right: c + a
	};
}
function mC(e, t, n) {
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
function hC(e, t) {
	return e.querySelectorAll("[role=row]")[t];
}
function gC(e, t) {
	return e.querySelectorAll("[role=cell]")[t];
}
var _C = U({ weekday: "narrow" });
function vC(e, t, n) {
	return e || yC(t, n);
}
function yC(e, t) {
	return U(e ? t > 1 ? {
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
var bC = class extends hv {
	constructor() {
		super(...arguments), this.state = {}, this.splitBusinessHourSegs = q(vx), this.splitBgEventSegs = q(SC), this.splitFgEventSegs = q(vx), this.splitDateSelectionSegs = q(vx), this.splitEventDrag = q(yx), this.splitEventResize = q(yx), this.rowHeightRefMap = new hx((e, t) => {
			let { rowHeightRefMap: n } = this.props;
			n && n.handleValue(e, t);
		}), this.handleMoreLinkEl = (e) => {
			this.disconnectMoreLinkHeight?.(), this.disconnectMoreLinkHeight = void 0, e && (this.disconnectMoreLinkHeight = nv(e, (e) => {
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
		let { props: e, state: t, context: n, rowHeightRefMap: r } = this, { options: i } = n, { cellRows: a, tableMode: o } = e, s = a.length, c = a[0]?.[0]?.key || "", l = this.splitFgEventSegs(e.fgEventSegs, s), u = this.splitBgEventSegs(e.bgEventSegs, s), d = this.splitBusinessHourSegs(e.businessHourSegs, s), f = this.splitDateSelectionSegs(e.dateSelectionSegs, s), p = this.splitEventDrag(e.eventDrag, s), m = this.splitEventResize(e.eventResize, s), h = P_(i), g = !e.forPrint && !h, _ = CC(e.visibleWidth, s, h, i), v = !e.forPrint && KS(e.dayMaxEvents, e.dayMaxEventRows) === "auto";
		return O(w, { children: [O(o ? "tbody" : "div", {
			role: "rowgroup",
			className: H(e.className, !o && !e.forPrint && V.flexCol),
			style: o ? void 0 : { width: e.width },
			ref: this.handleRootEl,
			children: a.map((n, a) => O(aC, {
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
				className: g ? V.grow : void 0,
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
		}), v && O(Mx, {
			num: 1,
			display: "row",
			isNarrow: e.cellIsNarrow,
			isMicro: e.cellIsMicro,
			elRef: this.handleMoreLinkEl,
			className: V.offscreen,
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
		let { props: i } = this, a = i.cellRows[0].length, { col: o, left: s, right: c } = pC(t, r, i.colWidth, a, e), { row: l, top: u, bottom: d } = mC(n, i.cellRows, this.rowHeightRefMap.current), f = i.cellRows[l][o];
		if (f.isInert) return null;
		let p = f.date, m = R(p, 1);
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
			getDayEl: () => gC(hC(this.rootEl, l), o),
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
function xC(e) {
	return e.eventRange.def.allDay;
}
function SC(e, t) {
	return vx(e.filter(xC), t);
}
function CC(e, t, n, r) {
	if (e != null) {
		let i = e / r.aspectRatio / 6;
		return t > 6 || n ? i : 0;
	}
	return 0;
}
var wC = class extends J {
	constructor() {
		super(...arguments), this.state = {}, this.buildDayHeaderText = q(TC), this.handleInnerEl = (e) => {
			this.disconnectSize &&= (this.disconnectSize(), void 0), e ? this.disconnectSize = ev(e, (e, t) => {
				this._isUnmounting || (Y(this.props.innerHeightRef, t), this.setState({ innerWidth: e }));
			}) : Y(this.props.innerHeightRef, null);
		};
	}
	render() {
		let { props: e, state: t, context: n } = this, { renderConfig: r, dataConfig: i, tableMode: a } = e, o = i.colSpan || 1, s = e.colWidth == null ? void 0 : e.colWidth * o, c = !a && s == null, l = c && o > 1, u = a ? void 0 : l ? {
			flexGrow: o,
			flexBasis: (o - 1) * Cx + (e.borderStart ? Cx : 0),
			minWidth: 0
		} : { width: s }, d = a ? "th" : "div", f = H(i.className, V.noMargin, V.noPadding, !a && V.flexCol, V.borderlessTop, V.borderlessEnd, !e.borderStart && V.borderlessStart, !(a && e.borderBottom) && V.borderlessBottom, c && !l && V.liquid);
		if (i.blank) return O(d, {
			role: "cell",
			"aria-colspan": i.colSpan,
			colSpan: a ? o : void 0,
			...i.attrs,
			className: H(f, Z(i.blank.classNameGenerator, {
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
		let b = g === "center" ? V.alignCenter : g === "end" ? V.alignEnd : V.alignStart;
		return O(X, {
			tag: d,
			attrs: {
				role: "columnheader",
				"aria-colspan": i.colSpan,
				colSpan: a ? o : void 0,
				...i.attrs
			},
			className: H(f, !a && b, !v && V.crop),
			style: u,
			renderProps: m,
			generatorName: r.generatorName,
			customGenerator: r.customGenerator,
			defaultGenerator: N_,
			classNameGenerator: p ? void 0 : r.classNameGenerator,
			didMount: r.didMount,
			willUnmount: r.willUnmount,
			children: (e) => O(w, { children: [i.a11yText && O("div", {
				className: V.srOnly,
				children: i.a11yText
			}), O("div", {
				"aria-hidden": i.a11yText ? !0 : void 0,
				ref: this.handleInnerEl,
				className: H(V.flexCol, V.noShrink, V.whiteSpaceNoWrap, a && b, v && V.sticky),
				style: {
					left: y,
					right: y
				},
				children: O(e, {
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
		let c = this.buildDayHeaderText(o ? i : e.date, a, o, s), l = r ? this.buildDayHeaderText(i, _C, !1, s) : c;
		return {
			...e,
			isNarrow: t,
			level: n,
			text: l.text,
			textParts: l.textParts,
			weekdayText: r ? l.text : c.weekdayText,
			dayNumberText: c.dayNumberText
		};
	}
};
function TC(e, t, n, r) {
	let i = r.formatToParts(e, t);
	return {
		text: bp(i),
		textParts: i,
		weekdayText: sv(i),
		dayNumberText: n ? cv(i) : ""
	};
}
var EC = class extends J {
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
		return O(n ? "tr" : "div", {
			role: e.role,
			"aria-rowindex": e.rowIndex == null ? void 0 : 1 + e.rowIndex,
			className: H(r.dayHeaderRowClass, e.className, n && V.borderless, !n && V.flexRow, !n && V.contentBox, !n && V.borderlessX, !n && V.borderlessTop, !n && !e.borderBottom && V.borderlessBottom),
			style: { height: e.height },
			children: e.dataConfigs.map((t, r) => O(wC, {
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
}, DC = class extends J {
	render() {
		let { props: e } = this, { headerTiers: t, tableMode: n } = e;
		return t.map((r, i) => x(EC, {
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
}, OC = class extends J {
	render() {
		let { props: e, context: t } = this, { options: n } = t, r = {
			borderlessX: e.borderlessX,
			borderlessTop: e.borderlessTop,
			borderlessBottom: e.borderlessBottom,
			multiMonthColumns: e.multiMonthColumns
		};
		return O("table", {
			role: "presentation",
			className: H(Z(n.tableClass, r), V.printTable),
			style: e.style,
			children: [
				O("colgroup", { children: e.cellRows[0].map((e) => O("col", {}, e.key)) }),
				e.showHeader && O("thead", {
					ref: e.headerElRef,
					role: "rowgroup",
					className: Z(n.tableHeaderClass, {
						...r,
						isSticky: !1
					}),
					children: [O(DC, {
						tableMode: !0,
						headerTiers: e.headerTiers,
						cellIsNarrow: e.cellIsNarrow,
						cellIsMicro: e.cellIsMicro
					}), O("tr", {
						role: "presentation",
						children: O("th", {
							role: "presentation",
							colSpan: e.cellRows[0].length,
							className: H(V.noPadding, Z(n.dayHeaderDividerClass, {
								isSticky: !1,
								multiMonthColumns: e.multiMonthColumns,
								options: { allDaySlot: !!n.allDaySlot }
							}))
						})
					})]
				}),
				O(bC, {
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
}, kC = class extends J {
	render() {
		let { props: e } = this;
		return O("div", {
			role: "rowgroup",
			className: H(e.className, V.flexCol, e.width == null && V.liquid),
			style: { width: e.width },
			children: O(DC, {
				headerTiers: e.headerTiers,
				colWidth: e.colWidth,
				viewportWidth: e.viewportWidth,
				cellIsNarrow: e.cellIsNarrow,
				cellIsMicro: e.cellIsMicro
			})
		});
	}
}, AC = class extends J {
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
		let { props: e, state: t, context: n } = this, { options: r } = n, { borderlessX: i, borderlessTop: a, borderlessBottom: o } = fm(r), { totalWidth: s, clientWidth: c } = t, l = s != null && c != null ? s - c : void 0;
		l < 3 && (l = 0);
		let u = !e.forPrint && !P_(r), d = !e.forPrint && F_(r), f = e.cellRows[0].length, p = c == null ? void 0 : c / f, m = p != null && p <= 60, h = m || p != null && p <= r.dayNarrowWidth;
		return e.forPrint ? O(OC, {
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
		}) : O(w, { children: [
			r.dayHeaders && O("div", {
				className: H(Z(r.tableHeaderClass, {
					isSticky: d,
					borderlessX: i,
					borderlessTop: a,
					borderlessBottom: o,
					multiMonthColumns: 0
				}), V.flexCol, d && V.tableHeaderSticky),
				children: [O("div", {
					className: V.flexRow,
					children: [O(kC, {
						headerTiers: e.headerTiers,
						cellIsNarrow: h,
						cellIsMicro: m
					}), !!l && O("div", {
						className: H(Z(r.fillerClass, { inTableHeader: !0 }), V.borderlessY, V.borderlessEnd),
						style: { minWidth: l }
					})]
				}), O("div", { className: Z(r.dayHeaderDividerClass, {
					isSticky: d,
					multiMonthColumns: 0,
					options: { allDaySlot: !!r.allDaySlot }
				}) })]
			}),
			O(vv, {
				vertical: u,
				className: H(Z(r.tableBodyClass, {
					borderlessX: i,
					borderlessTop: a,
					borderlessBottom: o,
					multiMonthColumns: 0
				}), !e.forPrint && V.flexCol, u && V.liquid),
				ref: this.handleScroller,
				clientWidthRef: this.handleClientWidth,
				children: O(bC, {
					dateProfile: e.dateProfile,
					todayRange: e.todayRange,
					cellRows: e.cellRows,
					forPrint: e.forPrint,
					isHitComboAllowed: e.isHitComboAllowed,
					className: V.grow,
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
			O(gx, { widthRef: this.handleTotalWidth })
		] });
	}
	componentDidMount() {
		this._isUnmounting = !1;
	}
	componentWillUnmount() {
		this._isUnmounting = !0;
	}
}, jC = class extends J {
	constructor() {
		super(...arguments), this.rootElRef = C();
	}
	render() {
		let { props: e } = this;
		return O("div", {
			ref: this.rootElRef,
			className: H(V.footerScrollbar, e.isSticky && V.footerScrollbarSticky),
			children: O(vv, {
				horizontal: !0,
				ref: e.scrollerRef,
				children: O("div", { style: { minWidth: e.canvasWidth } })
			})
		});
	}
	componentDidMount() {
		this._isUnmounting = !1, this.disconnectHeight = nv(this.rootElRef.current, (e) => {
			this._isUnmounting || Y(this.props.scrollbarWidthRef, e);
		});
	}
	componentWillUnmount() {
		this._isUnmounting = !0, this.disconnectHeight(), Y(this.props.scrollbarWidthRef, null);
	}
}, MC = class extends J {
	constructor() {
		super(...arguments), this.state = {}, this.headerScrollerRef = C(), this.bodyScrollerRef = C(), this.footerScrollerRef = C(), this.handleTotalWidth = (e) => {
			this._isUnmounting || this.setState({ totalWidth: e });
		}, this.handleClientWidth = (e) => {
			this._isUnmounting || this.setState({ clientWidth: e });
		};
	}
	render() {
		let { props: e, state: t, context: n } = this, { options: r } = n, { borderlessX: i, borderlessTop: a, borderlessBottom: o } = fm(r), { totalWidth: s, clientWidth: c } = t, l = s != null && c != null ? s - c : void 0, u = !e.forPrint && !P_(r), d = !e.forPrint && F_(r), f = !e.forPrint && I_(r), p = e.cellRows[0].length, [m, h] = dC(p, e.dayMinWidth, c), g = h ?? (c == null ? void 0 : c / p), _ = g != null && g <= 60, v = _ || g != null && g <= r.dayNarrowWidth;
		return e.forPrint ? O(OC, {
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
		}) : O(w, { children: [
			r.dayHeaders && O("div", {
				className: H(Z(r.tableHeaderClass, {
					isSticky: d,
					borderlessX: i,
					borderlessTop: a,
					borderlessBottom: o,
					multiMonthColumns: 0
				}), V.flexCol, d && V.tableHeaderSticky),
				children: [O(vv, {
					horizontal: !0,
					hideScrollbars: !0,
					className: V.flexRow,
					ref: this.headerScrollerRef,
					children: [O(kC, {
						headerTiers: e.headerTiers,
						colWidth: h,
						viewportWidth: c,
						width: m,
						cellIsNarrow: v,
						cellIsMicro: _
					}), !!l && O("div", {
						className: H(Z(r.fillerClass, { inTableHeader: !0 }), V.borderlessY, V.borderlessEnd),
						style: { minWidth: l }
					})]
				}), O("div", { className: Z(r.dayHeaderDividerClass, {
					isSticky: d,
					multiMonthColumns: 0,
					options: { allDaySlot: !!r.allDaySlot }
				}) })]
			}),
			O(vv, {
				vertical: u,
				horizontal: !0,
				hideScrollbars: f || e.forPrint,
				className: H(Z(r.tableBodyClass, {
					borderlessX: i,
					borderlessTop: a,
					borderlessBottom: o,
					multiMonthColumns: 0
				}), !e.forPrint && V.flexCol, u && V.liquid),
				ref: this.bodyScrollerRef,
				clientWidthRef: this.handleClientWidth,
				children: O(bC, {
					dateProfile: e.dateProfile,
					todayRange: e.todayRange,
					cellRows: e.cellRows,
					forPrint: e.forPrint,
					isHitComboAllowed: e.isHitComboAllowed,
					className: V.grow,
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
			!!f && O(jC, {
				isSticky: !0,
				canvasWidth: m,
				scrollerRef: this.footerScrollerRef
			}),
			O(gx, { widthRef: this.handleTotalWidth })
		] });
	}
	componentDidMount() {
		this._isUnmounting = !1;
		let e = L_(this.context.pluginHooks);
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
}, NC = class extends J {
	constructor() {
		super(...arguments), this.scrollerRef = C(), this.rowHeightRefMap = new hx(() => {
			Q(this.updateScrollY);
		}), this.scrollDate = null, this.updateScrollY = () => {
			if (this._isUnmounting) return;
			let e = this.rowHeightRefMap.current, t = this.scrollerRef.current;
			if (t && this.scrollDate) {
				let n = fC(this.scrollDate, this.props.cellRows, e);
				n != null && (n && n++, t.scrollTo({ y: n }));
			}
		}, this.handleScrollEnd = (e) => {
			e && (this.scrollDate = null);
		};
	}
	render() {
		let { props: e, context: t } = this, { options: n } = t, { borderlessX: r, borderlessTop: i, borderlessBottom: a } = fm(n), o = e.forPrint ? [] : e.dateSelectionSegs, s = e.forPrint ? null : e.eventDrag, c = e.forPrint ? null : e.eventResize, l = {
			...e,
			dateSelectionSegs: o,
			eventDrag: s,
			eventResize: c,
			scrollerRef: this.scrollerRef,
			rowHeightRefMap: this.rowHeightRefMap
		};
		return O(pv, {
			viewSpec: t.viewSpec,
			attrs: {
				role: "table",
				"aria-rowcount": e.headerTiers.length + e.cellRows.length,
				"aria-colcount": e.cellRows[0].length,
				"aria-labelledby": e.labelId,
				"aria-label": e.labelStr
			},
			className: H(e.className, !e.forPrint && V.flexCol, !e.forPrint && Z(n.tableClass, {
				borderlessX: r,
				borderlessTop: i,
				borderlessBottom: a,
				multiMonthColumns: 0
			})),
			children: n.dayMinWidth ? O(MC, {
				...l,
				dayMinWidth: n.dayMinWidth
			}) : O(AC, { ...l })
		});
	}
	componentDidMount() {
		this._isUnmounting = !1, this.props.forPrint || (this.resetScroll(), this.scrollerRef.current?.addScrollEndListener(this.handleScrollEnd));
	}
	componentDidUpdate(e) {
		e.forPrint && !this.props.forPrint && (this.scrollerRef.current?.addScrollEndListener(this.handleScrollEnd), this.resetScroll()), e.dateProfile !== this.props.dateProfile && this.context.options.scrollTimeReset && this.resetScroll();
	}
	componentWillUnmount() {
		this._isUnmounting = !0, this.scrollerRef.current?.removeScrollEndListener(this.handleScrollEnd);
	}
	resetScroll() {
		this.scrollDate = this.props.dateProfile.currentDate, this.updateScrollY(), this.scrollerRef.current?.scrollTo({ x: 0 });
	}
}, PC = class extends Ov {
	buildRenderRange(e, t, n) {
		let r = super.buildRenderRange(e, t, n), { props: i } = this;
		return FC({
			currentRange: r,
			snapToWeek: /^(year|month)$/.test(t),
			fixedWeekCount: i.fixedWeekCount,
			dateEnv: i.dateEnv
		});
	}
};
function FC(e) {
	let { dateEnv: t, currentRange: n } = e, { start: r, end: i } = n, a;
	if (e.snapToWeek && (r = t.startOfWeek(r), a = t.startOfWeek(i), a.valueOf() !== i.valueOf() && (i = _f(a, 1))), e.fixedWeekCount) {
		let e = t.startOfWeek(t.startOfMonth(R(n.end, -1))), r = Math.ceil(yf(e, i));
		i = _f(i, 6 - r);
	}
	return {
		start: r,
		end: i
	};
}
var IC = {
	name: "daygrid",
	initialView: "dayGridMonth",
	views: {
		dayGrid: {
			component: class extends J {
				constructor() {
					super(...arguments), this.buildDayTableModel = q(uC), this.buildDateRowConfigs = q(ux), this.createDayHeaderFormatter = q(vC), this.slicer = new sx();
				}
				render() {
					let { props: e, context: t } = this, { dateProfile: n } = e, { options: r, dateEnv: i } = t, a = this.buildDayTableModel(n, t.dateProfileGenerator, i), o = a.rowCount === 1, s = this.createDayHeaderFormatter(t.options.dayHeaderFormat, o, a.colCount), c = this.slicer.sliceProps(e, n, r.nextDayThreshold, t, a);
					return O(rv, {
						unit: "day",
						children: (r, i) => {
							let l = this.buildDateRowConfigs(a.headerDates, o, n, i, s, t);
							return O(NC, {
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
			dateProfileGeneratorClass: PC
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
// node_modules/fullcalendar/chunks/08b6f884.js
function LC(e, t, n = {}) {
	let { slotRange: r, majorUnit: i = "", activeRange: a } = n;
	return e.dates.map((e) => ({
		key: e.toISOString(),
		date: e,
		range: r ? {
			start: t.add(e, r.slotMinTime),
			end: t.add(e, r.slotMaxTime)
		} : {
			start: e,
			end: R(e, 1)
		},
		isMajor: i ? Av(e, i, t) : !1,
		isDisabled: a === null || a !== void 0 && !Yf(a, e)
	}));
}
var RC = lh(), zC = class {
	constructor() {
		this.getKeysForEventDefs = q(this._getKeysForEventDefs), this.splitDateSelection = q(this._splitDateSpan), this.splitEventStore = q(this._splitEventStore), this.splitIndividualUi = q(this._splitIndividualUi), this.splitEventDrag = q(this._splitInteraction), this.splitEventResize = q(this._splitInteraction), this.eventUiBuilders = {};
	}
	splitProps(e) {
		let t = this.getKeyInfo(e), n = this.getKeysForEventDefs(e.eventStore), r = this.splitDateSelection(e.dateSelection), i = this.splitIndividualUi(e.eventUiBases, n), a = this.splitEventStore(e.eventStore, n), o = this.splitEventDrag(e.eventDrag), s = this.splitEventResize(e.eventResize), c = {};
		this.eventUiBuilders = ym(t, (e, t) => this.eventUiBuilders[t] || q(BC));
		for (let n in t) {
			let l = t[n], u = a[n] || RC, d = this.eventUiBuilders[n];
			c[n] = {
				businessHours: l.businessHours || e.businessHours,
				dateSelection: r[n] || null,
				eventStore: u,
				eventUiBases: d(e.eventUiBases[""], l.ui, i[n]),
				eventDrag: o[n] || null,
				eventResize: s[n] || null,
				eventSelection: u.instances[e.eventSelection] ? e.eventSelection : ""
			};
		}
		return c;
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
		return ym(e.defs, (e) => this.getKeysForEventDef(e));
	}
	_splitEventStore(e, t) {
		let { defs: n, instances: r } = e, i = {};
		for (let e in n) for (let r of t[e]) i[r] || (i[r] = lh()), i[r].defs[e] = n[e];
		for (let e in r) {
			let n = r[e];
			for (let r of t[n.defId]) i[r] && (i[r].instances[e] = n);
		}
		return i;
	}
	_splitIndividualUi(e, t) {
		let n = {};
		for (let r in e) if (r) for (let i of t[r]) n[i] || (n[i] = {}), n[i][r] = e[r];
		return n;
	}
	_splitInteraction(e) {
		let t = {};
		if (e) {
			let n = this._splitEventStore(e.affectedEvents, this._getKeysForEventDefs(e.affectedEvents)), r = this._getKeysForEventDefs(e.mutatedEvents), i = this._splitEventStore(e.mutatedEvents, r), a = (r) => {
				t[r] || (t[r] = {
					affectedEvents: n[r] || RC,
					mutatedEvents: i[r] || RC,
					isEvent: e.isEvent
				});
			};
			for (let e in n) a(e);
			for (let e in i) a(e);
		}
		return t;
	}
};
function BC(e, t, n) {
	let r = [];
	e && r.push(e), t && r.push(t);
	let i = { "": _h(r) };
	return n && Object.assign(i, n), i;
}
var VC = class extends zC {
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
		return e.allDay ? tg(e) ? ["timed", "allDay"] : ["allDay"] : ["timed"];
	}
}, HC = class extends rx {
	sliceRange(e, t) {
		let n = [];
		for (let r = 0; r < t.length; r += 1) {
			let i = Gf(e, t[r]);
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
function UC(e, t) {
	let n = [], r = 0;
	for (; r < t; r += 1) n.push([]);
	if (e) for (r = 0; r < e.length; r += 1) n[e[r].col].push(e[r]);
	return n;
}
function WC(e, t) {
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
var GC = [
	{ hours: 1 },
	{ minutes: 30 },
	{ minutes: 15 },
	{ seconds: 30 },
	{ seconds: 15 }
];
function KC(e, t, n, r, i) {
	let a = /* @__PURE__ */ new Date(0), o = e, s = B(0), c = n || qC(r), l = [], u = 0;
	for (; fp(o) < fp(t);) {
		let e = i.add(a, o), t = pp(s, c) !== null;
		l.push({
			date: e,
			time: o,
			key: e.toISOString(),
			isoTimeStr: vp(e),
			isLabeled: t,
			isFirst: u === 0
		}), o = op(o, r), s = op(s, r), u += 1;
	}
	return l;
}
function qC(e) {
	let t, n, r;
	for (t = GC.length - 1; t >= 0; --t) if (n = B(GC[t]), r = pp(n, e), r !== null && r > 1) return n;
	return e;
}
var JC = class extends J {
	constructor() {
		super(...arguments), this.innerElRef = C();
	}
	render() {
		let { props: e } = this, { options: t, viewApi: n } = this.context, r = {
			text: t.allDayText,
			view: n,
			isNarrow: e.isNarrow
		};
		return O(X, {
			tag: "div",
			attrs: { role: "rowheader" },
			className: H(V.flexRow, V.noMargin, V.noPadding, V.contentBox),
			style: { width: e.width },
			renderProps: r,
			generatorName: "allDayHeaderContent",
			customGenerator: t.allDayHeaderContent,
			defaultGenerator: YC,
			classNameGenerator: t.allDayHeaderClass,
			didMount: t.allDayHeaderDidMount,
			willUnmount: t.allDayHeaderWillUnmount,
			children: (e) => O("div", {
				className: H(V.flexRow, V.noShrink, V.whiteSpacePre),
				ref: this.innerElRef,
				children: O(e, {
					tag: "div",
					className: Z(t.allDayHeaderInnerClass, r)
				})
			})
		});
	}
	componentDidMount() {
		this._isUnmounting = !1;
		let { props: e } = this, t = this.innerElRef.current;
		this.disconnectInnerWidth = tv(t, (t) => {
			this._isUnmounting || Y(e.innerWidthRef, t);
		});
	}
	componentWillUnmount() {
		this._isUnmounting = !0, this.disconnectInnerWidth(), Y(this.props.innerWidthRef, null);
	}
};
function YC(e) {
	return e.text;
}
var XC = class extends hv {
	constructor() {
		super(...arguments), this.state = {}, this.heightRef = C(), this.handleMoreLinkEl = (e) => {
			this.disconnectMoreLinkHeight?.(), this.disconnectMoreLinkHeight = void 0, e && (this.disconnectMoreLinkHeight = nv(e, (e) => {
				this._isUnmounting || this.setState({ moreLinkHeight: e });
			}));
		}, this.handleRootEl = (e) => {
			this.rootEl = e, e ? this.context.registerInteractiveComponent(this, { el: e }) : this.context.unregisterInteractiveComponent(this);
		};
	}
	render() {
		let { props: e, state: t } = this, n = !e.forPrint && KS(e.dayMaxEvents, e.dayMaxEventRows) === "auto";
		return O(w, { children: [O(aC, {
			...e,
			moreLinkHeight: t.moreLinkHeight,
			rootElRef: this.handleRootEl,
			heightRef: this.heightRef
		}), n && O(Mx, {
			num: 1,
			display: "row",
			isNarrow: e.cellIsNarrow,
			isMicro: e.cellIsMicro,
			elRef: this.handleMoreLinkEl,
			className: V.offscreen,
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
		let { props: i, heightRef: a } = this, o = i.cells.length, { col: s, left: c, right: l } = pC(t, r, i.colWidth, o, e), u = i.cells[s];
		if (u.isInert) return null;
		let d = u.date, f = R(d, 1);
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
			getDayEl: () => gC(this.rootEl, s),
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
function ZC(e, t, n = 0, r, i) {
	if (!r || !i) return [void 0, !1];
	let a = Math.max(r + 1, n), o = i / t, s, c;
	return e && o >= a ? (s = !0, c = o) : (s = !1, c = a), [c, s];
}
function QC(e, t, n) {
	let r = z(e).valueOf(), i;
	for (let e = 0; e < n.length; e++) if (t[e]?.length) {
		let { date: t } = n[e];
		if (t.valueOf() === r) return t;
		i ||= t;
	}
	return i;
}
function $C(e, t, n) {
	return n ||= z(e), ew(B(e.valueOf() - n.valueOf()), t);
}
function ew(e, t) {
	let n = fp(t.slotMinTime), r = fp(t.slotMaxTime), i = (e.milliseconds - n) / (r - n);
	return i = Math.max(0, i), i = Math.min(1, i), i;
}
function tw(e, t, n, r, i, a, o) {
	let s = [];
	if (i != null) {
		let c = i * r;
		for (let r of e) {
			let e = $C(r.startDate, t, n), i = $C(r.endDate, t, n), l = e * c, u = i * c, d = u - l;
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
function nw(e, t, n, r) {
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
	let o = rw(i, r ?? Infinity, { orderStrict: n ?? !1 });
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
function rw(e, t, n) {
	let { segLevels: r, excludedSegs: i } = lS(e, n.orderStrict, t), a = iw(r), o = ES(fS(i));
	return {
		domOrderedPlacements: PS(a),
		moreLinkGroups: o
	};
}
function iw(e) {
	let t = e.map((e, t) => e.map((e) => ({
		sourceSeg: e,
		start: e.start,
		end: e.end,
		isStart: e.isStart,
		isEnd: e.isEnd,
		levelIndex: t
	}))), n = pm(t), r = /* @__PURE__ */ new Map(), i = new Map(n.map((e) => [e.sourceSeg.key, e.sourceSeg.key]));
	for (let a of n) {
		let n = [];
		for (let r = a.levelIndex + 1; r < e.length; r += 1) n.push(...tS(t[r], a));
		r.set(a.sourceSeg.key, n);
		for (let e of n) ow(i, a.sourceSeg.key, e.sourceSeg.key);
	}
	let a = /* @__PURE__ */ new Map();
	for (let e of n) {
		let t = aw(i, e.sourceSeg.key);
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
		let t = e.sourceSeg.key, n = a.get(aw(i, t)) + 1, c = n;
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
function aw(e, t) {
	let n = e.get(t);
	if (n === t) return t;
	let r = aw(e, n);
	return e.set(t, r), r;
}
function ow(e, t, n) {
	let r = aw(e, t), i = aw(e, n);
	r !== i && e.set(i, r);
}
var sw = 50, cw = typeof navigator < "u" && navigator.userAgent.toLowerCase().includes("firefox");
function lw(e, t) {
	return e && (t === "stack" || t !== "grid" && cw) ? "stack" : "positioned";
}
var uw = U({
	hour: "numeric",
	minute: "2-digit",
	meridiem: !1
}), dw = class extends J {
	render() {
		let { props: e } = this;
		return O(Ev, {
			...e,
			display: "column",
			level: e.level,
			isNarrow: e.isNarrow,
			isShort: e.isShort,
			className: e.isLiquid ? V.liquid : "",
			disableLiquid: !e.isLiquid,
			defaultTimeFormat: uw
		});
	}
}, fw = class extends J {
	render() {
		let { props: e } = this;
		return O("div", {
			role: "listitem",
			className: H(V.abs, V.flexCol, V.end0, V.z9999),
			style: {
				top: e.top,
				height: e.height
			},
			children: O(Nx, {
				className: V.liquid,
				display: "column",
				allDayDate: null,
				segs: e.hiddenSegs,
				hiddenSegs: e.hiddenSegs,
				dateSpanProps: e.dateSpanProps,
				renderProps: e.renderProps,
				dateProfile: e.dateProfile,
				todayRange: e.todayRange,
				popoverContent: () => _w(e.hiddenSegs, e, !1),
				forceTimed: !0,
				isNarrow: e.isNarrow,
				isMicro: e.isMicro
			})
		});
	}
}, pw = (e) => O(C_.Consumer, { children: (t) => {
	let { options: n } = t;
	return O("div", {
		className: H(e.className, n.nowIndicatorDotClass),
		style: e.style
	});
} }), mw = (e) => O(C_.Consumer, { children: (t) => {
	let { options: n } = t, r = {
		date: t.dateEnv.toDate(e.date),
		view: t.viewApi
	};
	return O(X, {
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
function hw(e) {
	let t = e.totalHeight == null ? void 0 : e.totalHeight * $C(e.nowDate, e.dateProfile, e.dayDate);
	return O("div", {
		className: H(V.fill, V.pointerEventsNone, V.z2),
		children: [O(mw, {
			className: H(V.fillX, V.noMarginX, V.borderlessX),
			style: { top: t },
			date: e.nowDate
		}), (e.showDot ?? !0) && O(pw, {
			className: H(V.abs, V.start0),
			style: { top: t }
		})]
	});
}
var gw = class extends J {
	constructor() {
		super(...arguments), this.sortEventSegs = q(og), this.getDateMeta = q(fv);
	}
	render() {
		let { props: e, context: t } = this, { options: n, dateEnv: r } = t, i = n.selectMirror, a = e.eventDrag && e.eventDrag.segs || e.eventResize && e.eventResize.segs || i && e.dateSelectionSegs || [], o = this.getDateMeta(e.date, r, e.dateProfile, e.todayRange), s = H(V.borderlessY, V.borderlessEnd, !e.borderStart && V.borderlessStart, e.width == null && V.liquid, V.rel, V.z1), c = { width: e.width }, l = this.getIsStack(), u = {
			...o,
			...e.renderProps,
			isStack: l,
			isNarrow: e.isNarrow,
			isMajor: e.isMajor,
			view: t.viewApi
		};
		if (o.isDisabled) return O("div", {
			role: "cell",
			"aria-disabled": !0,
			className: H(Z(n.dayLaneClass, u), s),
			style: c
		});
		let d = H(Z(n.dayLaneInnerClass, u), !l && V.fill, V.z1), f = this.sortEventSegs(e.fgEventSegs, n.eventOrder);
		return O(X, {
			tag: "div",
			attrs: {
				...e.attrs,
				role: "cell",
				...o.isToday ? { "aria-current": "date" } : {},
				"data-date": gp(e.date)
			},
			className: s,
			style: c,
			renderProps: u,
			generatorName: void 0,
			classNameGenerator: n.dayLaneClass,
			didMount: n.dayLaneDidMount,
			willUnmount: n.dayLaneWillUnmount,
			children: () => O(w, { children: [
				this.renderFillSegs(e.businessHourSegs, "non-business"),
				this.renderFillSegs(e.bgEventSegs, "bg-event"),
				this.renderFillSegs(e.dateSelectionSegs, "highlight"),
				O("div", {
					role: f.length ? "list" : void 0,
					className: d,
					children: this.renderFgSegs(f, !1)
				}),
				!!a.length && O("div", {
					className: d,
					children: this.renderFgSegs(a, !0)
				}),
				this.renderNowIndicator(e.nowIndicatorSegs)
			] })
		});
	}
	renderFgSegs(e, t) {
		let { props: n } = this;
		return this.getIsStack() ? _w(e, n, t) : t ? this.renderPositionedMirrorSegs(e) : this.renderPositionedFgSegs(e);
	}
	renderPositionedFgSegs(e) {
		let { eventMaxStack: t, eventOrderStrict: n } = this.context.options, { placements: r, hiddenGroups: i } = nw(e, this.computeSegVerticals(e), n, t);
		return O(w, { children: [r.map((e) => this.renderPositionedSeg(e.seg, e.segVertical, this.computeSegHStyle(e), e.stackDepth, !1)), this.renderHiddenGroups(i)] });
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
		return O("div", {
			role: i ? void 0 : "listitem",
			className: H(V.abs, V.flexCol),
			style: {
				visibility: d ? "hidden" : void 0,
				top: t.start,
				height: t.size,
				...n
			},
			children: O(dw, {
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
				...fg(o, a.todayRange, a.nowDate, a.nowMs)
			})
		}, s);
	}
	computeSegVerticals(e) {
		let { props: t, context: n } = this, r = t.slatHeight != null;
		return tw(e, t.dateProfile, t.date, t.slatCnt, t.slatHeight ?? sw, r ? n.options.eventMinHeight : void 0, n.options.eventShortHeight);
	}
	renderHiddenGroups(e) {
		let { dateSpanProps: t, renderProps: n, dateProfile: r, todayRange: i, nowDate: a, nowMs: o, eventSelection: s, eventDrag: c, eventResize: l, isNarrow: u, isMicro: d } = this.props;
		return O(w, { children: e.map((e) => O(fw, {
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
		return O(w, { children: e.map((e, a) => {
			let { eventRange: o } = e, s = i[a] || {};
			return O("div", {
				"aria-hidden": !0,
				className: V.fillX,
				style: {
					top: s.start,
					height: s.size,
					marginInlineStart: -1
				},
				children: t === "bg-event" ? O(bx, {
					eventRange: o,
					isStart: e.isStart,
					isEnd: e.isEnd,
					isNarrow: n.isNarrow,
					isShort: s.isShort || !1,
					isVertical: !0,
					renderProps: n.renderProps,
					...fg(o, n.todayRange, n.nowDate, n.nowMs)
				}) : Sx(t, r.options)
			}, pg(o));
		}) });
	}
	renderNowIndicator(e) {
		let { props: t } = this;
		if (!(t.forPrint || this.getIsStack())) return e.map((e, n) => O(hw, {
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
			insetInlineStart: Rp(r),
			insetInlineEnd: Rp(1 - i),
			marginInlineEnd: void 0
		};
		return n && e.stackForward && (a.marginInlineEnd = 20), a;
	}
	getIsStack() {
		let { eventPrintLayout: e } = this.context.options;
		return lw(this.props.forPrint, e) === "stack";
	}
};
function _w(e, { todayRange: t, nowDate: n, nowMs: r, eventSelection: i, eventDrag: a, eventResize: o, renderProps: s }, c) {
	return O(w, { children: e.map((e) => {
		let { eventRange: l } = e, { instanceId: u } = l.instance, d = !!(a && a.affectedInstances[u]), f = !!(o && o.affectedInstances[u]), p = d || f;
		return O("div", {
			role: c ? void 0 : "listitem",
			className: V.breakInsideAvoid,
			style: { visibility: p ? "hidden" : void 0 },
			children: O(dw, {
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
				...fg(l, t, n, r)
			})
		}, u);
	}) });
}
var vw = class extends hv {
	constructor() {
		super(...arguments), this.processSlotOptions = q(yw), this.handleRootEl = (e) => {
			this.rootEl = e, e ? this.context.registerInteractiveComponent(this, {
				el: e,
				isHitComboAllowed: this.props.isHitComboAllowed
			}) : this.context.unregisterInteractiveComponent(this);
		};
	}
	render() {
		let { props: e } = this;
		return O("div", {
			role: e.role,
			className: H(e.className, V.flexRow),
			ref: this.handleRootEl,
			children: e.cells.map((t, n) => O(gw, {
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
		let { dateProfile: i, cells: a, colWidth: o, slatHeight: s } = this.props, { dateEnv: c, options: l } = this.context, { snapDuration: u, snapsPerSlot: d } = this.processSlotOptions(l.slotDuration, l.snapDuration), f = a.length, { col: p, left: m, right: h } = pC(t, r, o, f, e), g = a[p];
		if (g.isInert) return null;
		let _ = Math.floor(n / s), v = _ * s, y = (n - v) / s, b = Math.floor(y * d), x = _ * d + b, S = op(i.slotMinTime, cp(u, x)), C = c.add(g.date, S);
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
			getDayEl: () => gC(this.rootEl, p),
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
vw.addPropsEquality({ style: Tm });
function yw(e, t) {
	let n = t || e, r = pp(e, n);
	return r === null && (n = e, r = 1), {
		snapDuration: n,
		snapsPerSlot: r
	};
}
var bw = (e) => O(C_.Consumer, { children: (t) => {
	let { options: n } = t, r = {
		date: t.dateEnv.toDate(e.date),
		view: t.viewApi
	};
	return O(X, {
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
function xw(e) {
	return O("div", {
		className: H(V.fill, V.crop, V.pointerEventsNone, V.z2),
		children: O(bw, {
			className: V.abs,
			style: { top: e.totalHeight == null ? void 0 : e.totalHeight * $C(e.nowDate, e.dateProfile, e.dayDate) },
			date: e.nowDate
		})
	});
}
var Sw = U({
	hour: "numeric",
	minute: "2-digit",
	omitZeroMinute: !0,
	meridiem: "short"
}), Cw = class extends J {
	constructor() {
		super(...arguments), this.createRenderProps = q(ww), this.innerElRef = C();
	}
	render() {
		let { props: e, context: t } = this, { options: n } = t, r = n.slotHeaderFormat == null ? Sw : Array.isArray(n.slotHeaderFormat) ? U(n.slotHeaderFormat[0]) : U(n.slotHeaderFormat), i = this.createRenderProps(e.date, e.time, !e.isLabeled, e.isNarrow, e.isFirst, r, t), a = H(e.liquidHeight && V.liquid, V.flexRow, V.alignStart, V.noMargin, V.noPadding, V.borderlessX, V.borderlessBottom, !e.borderTop && V.borderlessTop);
		return e.isLabeled ? O(X, {
			tag: "div",
			attrs: { "data-time": e.isoTimeStr },
			style: { height: e.height },
			className: a,
			renderProps: i,
			generatorName: "slotHeaderContent",
			customGenerator: n.slotHeaderContent,
			defaultGenerator: Tw,
			classNameGenerator: n.slotHeaderClass,
			didMount: n.slotHeaderDidMount,
			willUnmount: n.slotHeaderWillUnmount,
			children: (e) => O("div", {
				ref: this.innerElRef,
				className: H(V.noShrink, V.whiteSpaceNoWrap, V.flexRow),
				children: O(e, {
					tag: "div",
					className: Z(n.slotHeaderInnerClass, i)
				})
			})
		}) : O("div", {
			className: H(Z(n.slotHeaderClass, i), a),
			style: { height: e.height }
		});
	}
	componentDidMount() {
		this._isUnmounting = !1;
		let { props: e } = this, t = this.innerElRef.current;
		t && (this.disconnectInnerSize = ev(t, (t, n) => {
			this._isUnmounting || (Y(e.innerWidthRef, t), Y(e.innerHeightRef, n));
		}));
	}
	componentWillUnmount() {
		let { props: e } = this;
		this._isUnmounting = !0, this.disconnectInnerSize && (this.disconnectInnerSize(), Y(e.innerWidthRef, null), Y(e.innerHeightRef, null));
	}
};
function ww(e, t, n, r, i, a, o) {
	return {
		...fv(e, o.dateEnv),
		level: 0,
		text: bp(o.dateEnv.formatToParts(e, a)),
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
function Tw(e) {
	return e.text;
}
var Ew = class extends J {
	constructor() {
		super(...arguments), this.getDateMeta = q(fv);
	}
	render() {
		let { props: e, context: t } = this, { options: n } = t, r = {
			...this.getDateMeta(e.date, t.dateEnv),
			time: e.time,
			isMajor: !1,
			isMinor: !e.isLabeled,
			view: t.viewApi
		};
		return O(X, {
			tag: "div",
			attrs: { "data-time": e.isoTimeStr },
			className: H(V.noMargin, V.noPadding, V.liquid, V.borderlessX, V.borderlessBottom, !e.borderTop && V.borderlessTop),
			renderProps: r,
			generatorName: void 0,
			classNameGenerator: n.slotLaneClass,
			didMount: n.slotLaneDidMount,
			willUnmount: n.slotLaneWillUnmount
		});
	}
}, Dw = U({ week: "short" }), Ow = class extends J {
	constructor() {
		super(...arguments), this.innerElRef = C();
	}
	render() {
		let { props: e, context: t } = this, { options: n, dateEnv: r } = t, i = e.dateProfile.renderRange, a = bf(i.start, i.end) === 1 && n.navLinks, o = i.start, s = uv(t, o, "week"), c = r.computeWeekNumber(o), l = r.formatToParts(o, n.weekNumberFormat || Dw), u = {
			num: c,
			text: bp(l),
			textParts: l,
			date: r.toDate(o),
			isNarrow: e.isNarrow,
			hasNavLink: a,
			options: { dayMinWidth: n.dayMinWidth }
		};
		return O(X, {
			tag: "div",
			attrs: { role: "columnheader" },
			className: H(V.flexRow, V.noMargin, V.noPadding, e.isLiquid ? V.liquid : V.contentBox),
			style: { width: e.width },
			renderProps: u,
			generatorName: "weekNumberHeaderContent",
			customGenerator: n.weekNumberHeaderContent,
			defaultGenerator: N_,
			classNameGenerator: n.weekNumberHeaderClass,
			didMount: n.weekNumberHeaderDidMount,
			willUnmount: n.weekNumberHeaderWillUnmount,
			children: (e) => O(w, { children: [!a && O("div", {
				className: V.srOnly,
				children: s
			}), O("div", {
				"aria-hidden": !a || void 0,
				ref: this.innerElRef,
				className: H(V.flexRow, V.noShrink, V.whiteSpaceNoWrap),
				children: O(e, {
					tag: "div",
					attrs: a ? dv(t, i.start, "week", s, !0, !0) : void 0,
					className: Z(n.weekNumberHeaderInnerClass, u)
				})
			})] })
		});
	}
	componentDidMount() {
		this._isUnmounting = !1;
		let { props: e } = this, t = this.innerElRef.current;
		this.disconnectInnerSize = ev(t, (t, n) => {
			this._isUnmounting || (Y(e.innerWidthRef, t), Y(e.innerHeightRef, n));
		});
	}
	componentWillUnmount() {
		let { props: e } = this;
		this._isUnmounting = !0, this.disconnectInnerSize(), Y(e.innerWidthRef, null), Y(e.innerHeightRef, null);
	}
};
function kw(e) {
	return O("div", {
		role: "cell",
		className: e.isLiquid ? V.liquid : V.contentBox,
		style: { width: e.width }
	});
}
var Aw = class extends J {
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
		let { props: e, state: t, context: n, headerLabelInnerWidthRefMap: r, headerLabelInnerHeightRefMap: i, headerMainInnerHeightRefMap: a, slatLabelInnerWidthRefMap: o, slatLabelInnerHeightRefMap: s } = this, { nowDate: c, headerTiers: l, forPrint: u } = e, d = !u && n.options.nowIndicator ? QC(c, e.nowIndicatorSegsByCol, e.cells) : void 0, { axisWidth: f, totalWidth: p, clientWidth: m, clientHeight: h, bodyHeight: g, sticykBottomScrollbarWidth: _ } = t, { options: v } = n, { borderlessX: y, borderlessTop: b, borderlessBottom: S } = fm(v), C = p != null && m != null && f != null ? p - m - (f + 1) : void 0, T = !u && !P_(v), ee = !u && F_(v), E = !u && I_(v), te = lw(u, v.eventPrintLayout) === "stack", D = u && !te, ne = u && te, re = e.cells.length, [ie, ae] = dC(re, e.dayMinWidth, m), oe = ae ?? (m == null ? void 0 : m / re), se = oe != null && oe <= 60, ce = se || oe != null && oe <= v.dayNarrowWidth, le = e.slatMetas.length, [ue, de] = ZC(T && v.expandRows, le, v.slotMinHeight, t.slatInnerHeight, h);
		this.slatHeight = ue;
		let fe = (ue || 0) * le, pe = D ? fe : void 0, me = T && !v.expandRows && h != null && h > fe, he = v.dayHeaders ? l.length + 1 : 1, ge = E ? _ : g != null && h != null ? g - h : void 0;
		return O(w, { children: [
			v.dayHeaders && O("div", {
				className: H(Z(v.tableHeaderClass, {
					isSticky: ee,
					borderlessX: y,
					borderlessTop: b,
					borderlessBottom: S,
					multiMonthColumns: 0
				}), V.flexCol, ee && V.tableHeaderSticky, V.z1),
				children: [O("div", {
					className: V.flexRow,
					children: [
						O("div", {
							role: "rowgroup",
							className: V.contentBox,
							style: { width: f },
							children: l.map((n, a) => O("div", {
								role: "row",
								"aria-rowindex": a + 1,
								className: H(v.dayHeaderRowClass, V.flexRow, V.contentBox, V.borderlessX, V.borderlessTop, a === e.headerTiers.length - 1 && V.borderlessBottom),
								style: { height: t.headerTierHeights[a] },
								children: v.weekNumbers && n.isDateRow ? O(Ow, {
									dateProfile: e.dateProfile,
									innerWidthRef: r.createRef(a),
									innerHeightRef: i.createRef(a),
									width: void 0,
									isLiquid: !0,
									isNarrow: ce
								}) : O(kw, {
									width: void 0,
									isLiquid: !0
								})
							}, a))
						}),
						O("div", { className: Z(v.slotHeaderDividerClass, {
							inTableHeader: !0,
							options: { dayMinWidth: v.dayMinWidth }
						}) }),
						O(vv, {
							horizontal: !0,
							hideScrollbars: !0,
							className: H(V.flexRow, V.liquid),
							ref: this.headerScrollerRef,
							children: [O("div", {
								role: "rowgroup",
								className: ie == null ? V.liquid : "",
								style: { width: ie },
								children: e.headerTiers.map((n, r) => x(EC, {
									...n,
									key: r,
									role: "row",
									rowIndex: r,
									borderBottom: r < e.headerTiers.length - 1,
									height: t.headerTierHeights[r],
									colWidth: ae,
									viewportWidth: m,
									innerHeightRef: a.createRef(r),
									cellIsNarrow: ce,
									cellIsMicro: se,
									rowLevel: e.headerTiers.length - r - 1
								}))
							}), !!C && O("div", {
								className: H(Z(v.fillerClass, { inTableHeader: !0 }), V.borderlessY, V.borderlessEnd),
								style: { minWidth: C }
							})]
						})
					]
				}), O("div", { className: Z(v.dayHeaderDividerClass, {
					isSticky: ee,
					multiMonthColumns: 0,
					options: { allDaySlot: !!v.allDaySlot }
				}) })]
			}),
			O("div", {
				role: "rowgroup",
				className: H(Z(v.tableBodyClass, {
					borderlessX: y,
					borderlessTop: b,
					borderlessBottom: S,
					multiMonthColumns: 0
				}), V.flexCol, T && V.liquid, V.isolate, V.z0),
				children: [v.allDaySlot && O(w, { children: [O("div", {
					role: "row",
					"aria-rowindex": he,
					className: H(V.flexRow, V.z1),
					children: [
						O(JC, {
							width: f,
							innerWidthRef: this.handleAllDayLabelInnerWidth,
							isNarrow: ce
						}),
						O("div", { className: Z(v.slotHeaderDividerClass, {
							inTableHeader: !1,
							options: { dayMinWidth: v.dayMinWidth }
						}) }),
						O(vv, {
							horizontal: !0,
							hideScrollbars: !0,
							className: H(V.flexRow, V.liquidX),
							ref: this.allDayScrollerRef,
							children: [O("div", {
								className: V.flexRow,
								style: { width: ie },
								children: O(XC, {
									dateProfile: e.dateProfile,
									todayRange: e.todayRange,
									cells: e.cells,
									showDayNumbers: !1,
									forPrint: u,
									isHitComboAllowed: e.isHitComboAllowed,
									className: H(V.borderless, V.liquidX),
									cellIsNarrow: ce,
									cellIsMicro: se,
									fgEventSegs: e.fgEventSegs,
									bgEventSegs: e.bgEventSegs,
									businessHourSegs: e.businessHourSegs,
									dateSelectionSegs: e.dateSelectionSegs,
									eventSelection: e.eventSelection,
									eventDrag: e.eventDrag,
									eventResize: e.eventResize,
									dayMaxEvents: e.dayMaxEvents,
									dayMaxEventRows: e.dayMaxEventRows,
									colWidth: ae
								})
							}), !!C && O("div", {
								className: H(Z(v.fillerClass, { inTableHeader: !1 }), V.borderlessY, V.borderlessEnd),
								style: { minWidth: C }
							})]
						})
					]
				}), O("div", { className: H(v.allDayDividerClass, V.z2) })] }), O("div", {
					role: "row",
					"aria-rowindex": he + +!!v.allDaySlot,
					className: H(V.flexRow, V.rel, T && V.liquid, V.z0),
					children: [
						O(vv, {
							vertical: T,
							hideScrollbars: !0,
							className: H(V.flexCol, V.contentBox),
							style: { width: f },
							ref: this.axisScrollerRef,
							clientHeightRef: this.handleBodyHeight,
							children: !ne && O(w, { children: O("div", {
								role: "rowheader",
								className: H(V.flexCol, V.grow, V.rel),
								style: { height: pe },
								children: [
									O("div", {
										className: V.srOnly,
										children: v.timedText
									}),
									O("div", {
										"aria-hidden": !0,
										className: H(V.flexCol, T && v.expandRows && V.grow, D && V.fillX),
										children: e.slatMetas.map((e, t) => x(Cw, {
											...e,
											key: e.key,
											innerWidthRef: o.createRef(e.key),
											innerHeightRef: s.createRef(e.key),
											borderTop: !!t,
											isNarrow: ce,
											height: de ? void 0 : ue,
											liquidHeight: de
										}))
									}),
									d && O(xw, {
										nowDate: c,
										dayDate: d,
										dateProfile: e.dateProfile,
										totalHeight: ue == null ? void 0 : ue * le
									}),
									!!(me || ge) && O("div", {
										className: H(Z(v.fillerClass, { inTableHeader: !1 }), V.borderlessX, V.borderlessBottom, me && V.liquid),
										style: { minHeight: ge }
									})
								]
							}) })
						}),
						O("div", { className: Z(v.slotHeaderDividerClass, {
							inTableHeader: !1,
							options: { dayMinWidth: v.dayMinWidth }
						}) }),
						O("div", {
							className: H(V.flexCol, V.liquid),
							children: [O(vv, {
								vertical: T,
								horizontal: !0,
								hideScrollbars: E || u,
								className: H(V.flexCol, V.rel, T && V.liquid),
								ref: this.mainScrollerRef,
								clientWidthRef: this.handleClientWidth,
								clientHeightRef: this.handleClientHeight,
								children: O("div", {
									className: H(V.flexCol, V.grow, V.rel),
									style: {
										width: ie,
										height: pe
									},
									children: [O(vw, {
										dateProfile: e.dateProfile,
										nowDate: e.nowDate,
										nowMs: e.nowMs,
										todayRange: e.todayRange,
										cells: e.cells,
										slatCnt: le,
										forPrint: u,
										isHitComboAllowed: e.isHitComboAllowed,
										className: ne ? "" : V.fill,
										fgEventSegsByCol: e.fgEventSegsByCol,
										bgEventSegsByCol: e.bgEventSegsByCol,
										businessHourSegsByCol: e.businessHourSegsByCol,
										nowIndicatorSegsByCol: e.nowIndicatorSegsByCol,
										dateSelectionSegsByCol: e.dateSelectionSegsByCol,
										eventDragByCol: e.eventDragByCol,
										eventResizeByCol: e.eventResizeByCol,
										eventSelection: e.eventSelection,
										colWidth: ae,
										slatHeight: ue,
										cellIsNarrow: ce,
										cellIsMicro: se
									}), !ne && O(w, { children: [O("div", {
										"aria-hidden": !0,
										className: H(V.flexCol, T && v.expandRows && V.grow, D ? V.fillX : V.rel),
										children: e.slatMetas.map((e, t) => O("div", {
											className: H(V.flexRow, de && V.liquid),
											style: { height: de ? "" : ue },
											children: x(Ew, {
												...e,
												key: e.key,
												borderTop: !!t
											})
										}, e.key))
									}), me && O("div", { className: H(Z(v.fillerClass, { inTableHeader: !1 }), V.borderlessX, V.borderlessBottom, V.liquid) })] })]
								})
							}), !!E && O(jC, {
								isSticky: !0,
								canvasWidth: ie,
								scrollerRef: this.footScrollerRef,
								scrollbarWidthRef: this.handleStickyBottomScrollbarWidth
							})]
						})
					]
				})]
			}),
			O(gx, { widthRef: this.handleTotalWidth })
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
		let e = L_(this.context.pluginHooks);
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
Aw.addPropsEquality({ headerTierHeights: gm });
var jw = class extends J {
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
			this._isUnmounting || requestAnimationFrame(() => {
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
		let { props: e, state: t, context: n, slatLabelInnerWidthRefMap: r, slatLabelInnerHeightRefMap: i, headerLabelInnerWidthRefMap: a } = this, { nowDate: o, forPrint: s } = e, c = !s && n.options.nowIndicator ? QC(o, e.nowIndicatorSegsByCol, e.cells) : void 0, { axisWidth: l, clientWidth: u, totalWidth: d } = t, { options: f } = n, { borderlessX: p, borderlessTop: m, borderlessBottom: h } = fm(f), g = d != null && u != null && !s ? d - u : void 0, _ = !s && !P_(f), v = !s && F_(f), y = e.slatMetas.length, [b, S] = ZC(_ && f.expandRows, y, f.slotMinHeight, t.slatInnerHeight, t.clientHeight);
		this.slatHeight = b;
		let C = (b || 0) * y, T = _ && !f.expandRows && t.clientHeight != null && t.clientHeight > C, ee = lw(s, f.eventPrintLayout) === "stack", E = s && !ee, te = s && ee, D = E ? C : void 0, ne = e.cells.length, re = u == null ? void 0 : u / ne, ie = re != null && re <= 60, ae = ie || re != null && re <= f.dayNarrowWidth;
		return O(w, { children: [
			f.dayHeaders && O("div", {
				role: "rowgroup",
				className: H(Z(f.tableHeaderClass, {
					isSticky: v,
					borderlessX: p,
					borderlessTop: m,
					borderlessBottom: h,
					multiMonthColumns: 0
				}), V.flexCol, v && V.tableHeaderSticky, V.z1),
				children: [e.headerTiers.map((t, n) => O("div", {
					role: "row",
					className: V.flexRow,
					children: [
						O("div", {
							className: H(f.dayHeaderRowClass, V.flexRow, V.borderlessX, V.borderlessTop, n === e.headerTiers.length - 1 && V.borderlessBottom),
							children: f.weekNumbers && t.isDateRow ? O(Ow, {
								dateProfile: e.dateProfile,
								innerWidthRef: this.handleWeekNumberInnerWidth,
								innerHeightRef: a.createRef(n),
								width: l,
								isLiquid: !1,
								isNarrow: ae
							}) : O(kw, {
								width: l,
								isLiquid: !1
							})
						}),
						O("div", { className: Z(f.slotHeaderDividerClass, {
							inTableHeader: !0,
							options: { dayMinWidth: f.dayMinWidth }
						}) }),
						O(EC, {
							...t,
							className: V.liquid,
							borderBottom: n < e.headerTiers.length - 1,
							viewportWidth: u,
							cellIsNarrow: ae,
							cellIsMicro: ie,
							rowLevel: e.headerTiers.length - n - 1
						}),
						!!g && O("div", {
							className: H(Z(f.fillerClass, { inTableHeader: !0 }), V.borderlessY, V.borderlessEnd),
							style: { minWidth: g }
						})
					]
				}, n)), O("div", { className: Z(f.dayHeaderDividerClass, {
					isSticky: v,
					multiMonthColumns: 0,
					options: { allDaySlot: !!f.allDaySlot }
				}) })]
			}),
			O("div", {
				role: "rowgroup",
				className: H(Z(f.tableBodyClass, {
					borderlessX: p,
					borderlessTop: m,
					borderlessBottom: h,
					multiMonthColumns: 0
				}), V.flexCol, _ && V.liquid, V.isolate, V.z0),
				children: [f.allDaySlot && O(w, { children: [O("div", {
					role: "row",
					className: H(V.flexRow, V.z1),
					children: [
						O(JC, {
							width: l,
							innerWidthRef: this.handleAllDayLabelInnerWidth,
							isNarrow: ae
						}),
						O("div", { className: Z(f.slotHeaderDividerClass, {
							inTableHeader: !1,
							options: { dayMinWidth: f.dayMinWidth }
						}) }),
						O(XC, {
							dateProfile: e.dateProfile,
							todayRange: e.todayRange,
							cells: e.cells,
							showDayNumbers: !1,
							forPrint: s,
							isHitComboAllowed: e.isHitComboAllowed,
							className: H(V.liquidX, V.borderless),
							cellIsNarrow: ae,
							cellIsMicro: ie,
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
						!!g && O("div", {
							className: H(Z(f.fillerClass, { inTableHeader: !1 }), V.borderlessY, V.borderlessEnd),
							style: { minWidth: g }
						})
					]
				}), O("div", { className: H(f.allDayDividerClass, V.z2) })] }), O(vv, {
					vertical: _,
					className: H(V.flexCol, V.rel, _ && V.liquid, V.z0),
					ref: e.timeScrollerRef,
					clientWidthRef: this.handleClientWidth,
					clientHeightRef: this.handleClientHeight,
					children: O("div", {
						className: H(V.flexCol, V.grow, V.rel),
						style: { height: D },
						children: [O("div", {
							role: "row",
							className: H(V.flexRow, !te && V.fill),
							children: [
								O("div", {
									role: "rowheader",
									className: V.contentBox,
									style: { width: l },
									children: O("div", {
										className: V.srOnly,
										children: f.timedText
									})
								}),
								O("div", { className: Z(f.slotHeaderDividerClass, {
									inTableHeader: !1,
									options: { dayMinWidth: f.dayMinWidth }
								}) }),
								O(vw, {
									dateProfile: e.dateProfile,
									nowDate: e.nowDate,
									nowMs: e.nowMs,
									todayRange: e.todayRange,
									cells: e.cells,
									slatCnt: y,
									forPrint: s,
									isHitComboAllowed: e.isHitComboAllowed,
									className: V.liquid,
									fgEventSegsByCol: e.fgEventSegsByCol,
									bgEventSegsByCol: e.bgEventSegsByCol,
									businessHourSegsByCol: e.businessHourSegsByCol,
									nowIndicatorSegsByCol: e.nowIndicatorSegsByCol,
									dateSelectionSegsByCol: e.dateSelectionSegsByCol,
									eventDragByCol: e.eventDragByCol,
									eventResizeByCol: e.eventResizeByCol,
									eventSelection: e.eventSelection,
									slatHeight: b,
									cellIsNarrow: ae,
									cellIsMicro: ie
								})
							]
						}), !te && O(w, { children: [
							O("div", {
								"aria-hidden": !0,
								className: H(V.flexCol, _ && f.expandRows && V.grow, E ? V.fillX : V.rel),
								children: e.slatMetas.map((e, t) => O("div", {
									className: H(S && V.liquid, V.flexRow),
									style: { height: S ? void 0 : b },
									children: [
										O("div", {
											className: V.flexCol,
											style: { width: l },
											children: x(Cw, {
												...e,
												key: e.key,
												innerWidthRef: r.createRef(e.key),
												innerHeightRef: i.createRef(e.key),
												borderTop: !!t,
												isNarrow: ae
											})
										}),
										O("div", {
											className: Z(f.slotHeaderDividerClass, {
												inTableHeader: !1,
												options: { dayMinWidth: f.dayMinWidth }
											}),
											style: { visibility: "hidden" }
										}),
										x(Ew, {
											...e,
											key: e.key,
											borderTop: !!t
										})
									]
								}, e.key))
							}),
							T && O("div", { className: H(Z(f.fillerClass, { inTableHeader: !1 }), V.borderlessX, V.borderlessBottom, V.liquid) }),
							c && O(xw, {
								nowDate: o,
								dayDate: c,
								dateProfile: e.dateProfile,
								totalHeight: b == null ? void 0 : b * y
							})
						] })]
					})
				})]
			}),
			O(gx, { widthRef: this.handleTotalWidth })
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
function Mw(e) {
	return e.map(() => []);
}
function Nw(e) {
	return e.map(() => null);
}
var Pw = class extends J {
	constructor() {
		super(...arguments), this.buildSlatMetas = q(KC), this.dayScrollerRef = C(), this.timeScrollerRef = C(), this.scrollState = {}, this.handleSlatHeight = (e) => {
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
			r == null && i && t != null && e && (r = ew(i, this.props.dateProfile) * (t * this.currentSlatCnt), r && r++, n.y = r), r != null && e.scrollTo({ y: r });
		};
	}
	render() {
		let { props: e, context: t } = this, { dateProfile: n } = e, { options: r, dateEnv: i } = t, { dayMinWidth: a } = r, { borderlessX: o, borderlessTop: s, borderlessBottom: c } = fm(r), l = this.buildSlatMetas(n.slotMinTime, n.slotMaxTime, r.slotHeaderInterval, r.slotDuration, i);
		this.currentSlatCnt = l.length;
		let u = e.forPrint ? [] : e.dateSelectionSegs, d = e.forPrint ? null : e.eventDrag, f = e.forPrint ? null : e.eventResize, p = e.forPrint ? Mw(e.dateSelectionSegsByCol) : e.dateSelectionSegsByCol, m = e.forPrint ? Nw(e.eventDragByCol) : e.eventDragByCol, h = e.forPrint ? Nw(e.eventResizeByCol) : e.eventResizeByCol, g = {
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
			...Iw(r),
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
		return O(pv, {
			attrs: {
				role: "table",
				"aria-colcount": e.cells.length,
				"aria-labelledby": e.labelId,
				"aria-label": e.labelStr
			},
			className: H(e.className, Z(r.tableClass, {
				borderlessX: o,
				borderlessTop: s,
				borderlessBottom: c,
				multiMonthColumns: 0
			}), !e.forPrint && V.flexCol, V.isolate),
			viewSpec: t.viewSpec,
			children: a ? O(Aw, {
				...g,
				dayMinWidth: a,
				dayScrollerRef: this.dayScrollerRef
			}) : O(jw, { ...g })
		});
	}
	componentDidMount() {
		this._isUnmounting = !1, this.resetScroll(), this.context.emitter.on("_timeScrollRequest", this.handleTimeScrollRequest);
		let e = this.timeScrollerRef.current;
		e && e.addScrollEndListener(this.handleTimeScrollEnd);
	}
	componentDidUpdate(e) {
		e.dateProfile !== this.props.dateProfile && this.context.options.scrollTimeReset ? this.resetScroll() : e.forPrint && !this.props.forPrint && this.applyTimeScroll();
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
}, Fw = 5;
function Iw(e) {
	let { dayMaxEvents: t, dayMaxEventRows: n } = e;
	return (t === !0 || n === !0) && (t = void 0, n = Fw), {
		dayMaxEvents: t,
		dayMaxEventRows: n
	};
}
var Lw = {
	name: "timegrid",
	initialView: "timeGridWeek",
	deps: [IC],
	views: {
		timeGrid: {
			component: class extends hv {
				constructor() {
					super(...arguments), this.createDayHeaderFormatter = q(vC), this.buildDaySeries = q((e, t) => new sC(e.renderRange, t)), this.buildDayCols = q(LC), this.extractColDates = q((e) => e.map((e) => e.date)), this.extractColRanges = q((e) => e.map((e) => e.range)), this.buildDateRowConfigs = q(ux), this.splitFgEventSegs = q(UC), this.splitBgEventSegs = q(UC), this.splitBusinessHourSegs = q(UC), this.splitNowIndicatorSegs = q(UC), this.splitDateSelectionSegs = q(UC), this.splitEventDrag = q(WC), this.splitEventResize = q(WC), this.allDaySplitter = new VC(), this.daySeriesSlicer = new cx(), this.dayTimeColsSlicer = new HC();
				}
				render() {
					let { props: e, context: t } = this, { dateProfile: n } = e, { options: r, dateProfileGenerator: i } = t, a = this.buildDaySeries(n, i), o = this.buildDayCols(a, t.dateEnv, {
						slotRange: n,
						activeRange: n.activeRange
					}), s = this.extractColDates(o), c = this.extractColRanges(o), l = this.allDaySplitter.splitProps(e), u = this.daySeriesSlicer.sliceProps(l.allDay, n, r.nextDayThreshold, t, a), d = this.dayTimeColsSlicer.sliceProps(l.timed, n, null, t, c), f = this.createDayHeaderFormatter(t.options.dayHeaderFormat, !0, o.length);
					return O(rv, {
						unit: r.nowIndicator ? "minute" : "day",
						children: (i, a, l) => {
							let p = o.length, m = !e.forPrint && r.nowIndicator && this.dayTimeColsSlicer.sliceNowDate(i, n, r.nextDayThreshold, t, c), h = this.splitFgEventSegs(d.fgEventSegs, p), g = this.splitBgEventSegs(d.bgEventSegs, p), _ = this.splitBusinessHourSegs(d.businessHourSegs, p), v = this.splitNowIndicatorSegs(m, p), y = this.splitDateSelectionSegs(d.dateSelectionSegs, p), b = this.splitEventDrag(d.eventDrag, p), x = this.splitEventResize(d.eventResize, p), S = this.buildDateRowConfigs(s, !0, e.dateProfile, a, f, t);
							return O(Pw, {
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
}, Rw = class extends J {
	render() {
		let { props: e, context: t } = this, { options: n } = t, r = t.dateEnv.formatToParts(e.dayDate, e.dayFormat), i = bp(r), a = n.navLinks, o = {
			...e.dateMeta,
			view: t.viewApi,
			text: i,
			textParts: r,
			get weekdayText() {
				return sv(r);
			},
			get dayNumberText() {
				return cv(r);
			},
			hasNavLink: a,
			level: e.level
		};
		return O(X, {
			tag: "div",
			attrs: a ? dv(this.context, e.dayDate, void 0, i, this.props.isTabbable) : {},
			renderProps: o,
			generatorName: "listDayHeaderContent",
			customGenerator: n.listDayHeaderContent,
			defaultGenerator: N_,
			classNameGenerator: n.listDayHeaderInnerClass
		});
	}
}, zw = class extends J {
	render() {
		let { options: e, viewApi: t, viewSpec: n } = this.context, { dayDate: r, dateMeta: i } = this.props, a = !this.props.forPrint, o = e.listDayFormat ?? Bw(n), s = e.listDayAltFormat ?? Vw(n), c = {
			...i,
			view: t
		};
		return O(X, {
			tag: "div",
			attrs: {
				"data-date": gp(r),
				...i.isToday ? { "aria-current": "date" } : {}
			},
			className: a ? V.stickyT : "",
			renderProps: c,
			generatorName: void 0,
			classNameGenerator: e.listDayHeaderClass,
			didMount: e.listDayHeaderDidMount,
			willUnmount: e.listDayHeaderWillUnmount,
			children: () => O(w, { children: [!!o && O(Rw, {
				dayDate: r,
				dayFormat: o,
				isTabbable: !0,
				dateMeta: i,
				level: 0
			}), !!s && O(Rw, {
				dayDate: r,
				dayFormat: s,
				isTabbable: !1,
				dateMeta: i,
				level: 1
			})] })
		});
	}
};
function Bw({ durationUnit: e, singleUnit: t }) {
	return t === "day" || e === "day" || t === "week" ? ov : iv;
}
function Vw({ durationUnit: e, singleUnit: t }) {
	if (t !== "day") return e === "day" || t === "week" ? iv : ov;
}
var Hw = U({
	hour: "numeric",
	minute: "2-digit",
	meridiem: "short"
}), Uw = class extends J {
	render() {
		let { props: e, context: t } = this, { eventRange: n } = e, { displayEventTime: r } = t.options, i = r !== !1 && (n.def.allDay || !e.isStart && !e.isEnd) ? t.options.allDayText : void 0;
		return O(Ev, {
			...e,
			forcedTimeText: i,
			defaultTimeFormat: Hw,
			disableDragging: !0,
			disableResizing: !0,
			disableZindexes: !0,
			display: "list-item"
		});
	}
}, Ww = class extends J {
	constructor() {
		super(...arguments), this.getDateMeta = q(fv), this.sortEventSegs = q(og);
	}
	render() {
		let { props: e, context: t } = this, { nowDate: n, todayRange: r } = e, { options: i } = t, a = this.getDateMeta(e.dayDate, t.dateEnv, void 0, r), o = this.sortEventSegs(e.segs, i.eventOrder), s = uv(this.context, e.dayDate), c = {
			...a,
			isFirst: e.isFirst,
			isLast: e.isLast,
			view: t.viewApi
		}, l = {
			...a,
			view: t.viewApi
		};
		return O("div", {
			role: "listitem",
			"aria-label": s,
			className: Z(i.listDayClass, c),
			children: [O(zw, {
				dayDate: e.dayDate,
				dateMeta: a,
				forPrint: e.forPrint
			}), O("div", {
				role: "list",
				"aria-label": i.eventsHint,
				className: H(Z(i.listDayBodyClass, l), V.flexCol),
				children: o.map((t, i) => {
					let a = t.eventRange.instance.instanceId, s = i === 0, c = i === o.length - 1;
					return O("div", {
						role: "listitem",
						className: V.flexCol,
						children: O(Uw, {
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
							...fg(t.eventRange, r, n, e.nowMs)
						})
					}, a);
				})
			})]
		});
	}
}, Gw = class extends hv {
	constructor() {
		super(...arguments), this.computeDateVars = q(qw), this.eventStoreToSegs = q(this._eventStoreToSegs), this.setRootEl = (e) => {
			e ? this.context.registerInteractiveComponent(this, {
				el: e,
				disableHits: !0
			}) : this.context.unregisterInteractiveComponent(this);
		};
	}
	render() {
		let { props: e, context: t } = this, { options: n } = t, { dayDates: r, dayRanges: i } = this.computeDateVars(e.dateProfile), a = this.eventStoreToSegs(e.eventStore, e.eventUiBases, i), o = !e.forPrint && !P_(n);
		return O(pv, {
			viewSpec: t.viewSpec,
			className: H(e.className, V.flexCol),
			elRef: this.setRootEl,
			children: a.length ? O(vv, {
				vertical: o,
				className: H(V.flexCol, o ? V.liquid : ""),
				children: this.renderSegList(a, r)
			}) : this.renderEmptyMessage()
		});
	}
	renderEmptyMessage() {
		let { options: e, viewApi: t } = this.context, n = {
			text: e.noEventsText,
			view: t
		};
		return O(X, {
			tag: "div",
			attrs: { role: "status" },
			renderProps: n,
			generatorName: "noEventsContent",
			customGenerator: e.noEventsContent,
			defaultGenerator: Kw,
			classNameGenerator: e.noEventsClass,
			className: V.grow,
			didMount: e.noEventsDidMount,
			willUnmount: e.noEventsWillUnmount,
			children: (t) => O(t, {
				tag: "div",
				className: Z(e.noEventsInnerClass, n)
			})
		});
	}
	renderSegList(e, t) {
		let { options: n } = this.context, r = Jw(e);
		return O("div", {
			role: "list",
			"aria-labelledby": this.props.labelId,
			"aria-label": this.props.labelStr,
			className: H(V.flexCol, H(n.listDaysClass)),
			children: O(rv, {
				unit: "day",
				children: (e, n, i) => {
					let a = [], o = r.reduce((e, t) => e + +!!t, 0), s = 0;
					for (let c = 0; c < r.length; c += 1) {
						let l = r[c];
						if (l) {
							let r = t[c], u = gp(r), d = s === 0, f = s === o - 1;
							a.push(O(Ww, {
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
					return O(w, { children: a });
				}
			})
		});
	}
	_eventStoreToSegs(e, t, n) {
		return this.eventRangesToSegs($h(e, t, this.props.dateProfile.activeRange, this.context.options.nextDayThreshold).fg, n);
	}
	eventRangesToSegs(e, t) {
		let n = [];
		for (let r of e) n.push(...this.eventRangeToSegs(r, t));
		return n;
	}
	eventRangeToSegs(e, t) {
		let n = e.range, r, i = [];
		for (r = 0; r < t.length; r += 1) {
			let a = Gf(n, t[r]);
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
function Kw(e) {
	return e.text;
}
function qw(e) {
	let t = z(e.renderRange.start), n = e.renderRange.end, r = [], i = [];
	for (; t < n;) r.push(t), i.push({
		start: t,
		end: R(t, 1)
	}), t = R(t, 1);
	return {
		dayDates: r,
		dayRanges: i
	};
}
function Jw(e) {
	let t = [], n, r;
	for (n = 0; n < e.length; n += 1) r = e[n], (t[r.dayIndex] || (t[r.dayIndex] = [])).push(r);
	return t;
}
var Yw = {
	name: "list",
	views: {
		list: {
			component: Gw,
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
}, Xw = class extends hv {
	constructor() {
		super(...arguments), this.state = {}, this.buildDayTableModel = q(uC), this.createDayHeaderFormatter = q(vC), this.buildDateRowConfig = q(dx), this.titleElRef = C(), this.rowHeightRefMap = new hx(() => {
			Q(this.handleHeights);
		}), this.slicer = new sx(), this.handleEl = (e) => {
			let { options: t } = this.context;
			e && (this.rootEl = e, t.singleMonthDidMount?.({
				el: this.rootEl,
				...this.renderProps
			}));
		}, this.handleTableHeaderEl = (e) => {
			this.disconnectTableHeaderHeight?.(), this.disconnectTableHeaderHeight = void 0, e && (this.disconnectTableHeaderHeight = nv(e, (e) => {
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
		let { props: e, state: t, context: n } = this, { dateProfile: r, forPrint: i } = e, { options: a, dateEnv: o } = n, { borderlessX: s, borderlessTop: c, borderlessBottom: l } = fm(a), u = this.buildDayTableModel(r, n.dateProfileGenerator, o), d = this.slicer.sliceProps(e, r, a.nextDayThreshold, n, u), f = this.createDayHeaderFormatter(a.dayHeaderFormat, !1, u.colCount), p = this.buildDateRowConfig(u.headerDates, !1, r, e.todayRange, f, n);
		this.cellRows = u.cellRows;
		let m = !i && e.colCount === 1, h = !i || e.hasLateralSiblings, g = u.cellRows[0].length, _ = t.gridWidth == null ? void 0 : t.gridWidth / g, v = _ != null && _ <= 60, y = v || _ != null && _ <= a.dayNarrowWidth, b = t.gridWidth == null ? void 0 : 1 / a.aspectRatio * t.gridWidth / 6, x = m ? b : void 0, S = m && b != null && t.tableHeaderHeight != null ? b + t.tableHeaderHeight + 1 : void 0, C = d.businessHourSegs, w = i ? [] : d.dateSelectionSegs, T = i ? null : d.eventDrag, ee = i ? null : d.eventResize, E = i && !e.hasLateralSiblings, te = a.navLinks && e.colCount > 1, D = {
			multiMonthColumns: e.colCount || 0,
			isSticky: m,
			isNarrow: y,
			hasNavLink: te
		}, ne = e.dateProfile.currentRange.start, re = te ? dv(n, ne, "month", e.isoDateStr) : {};
		return O("div", {
			role: "listitem",
			style: { width: e.width },
			children: O("div", {
				role: "table",
				"aria-labelledby": this.titleId,
				"data-date": e.isoDateStr,
				className: H(Z(a.singleMonthClass, {
					isFirst: e.isFirst,
					isLast: e.isLast,
					multiMonthColumns: e.colCount || 0
				}), V.flexCol, e.hasLateralSiblings && V.breakInsideAvoid),
				children: [
					O(gx, { widthRef: this.handleGridWidth }),
					O("div", {
						id: this.titleId,
						ref: this.titleElRef,
						className: H(Z(a.singleMonthHeaderClass, D), m && V.stickyT, m && V.z3, V.flexCol),
						style: { marginBottom: S },
						children: O("div", {
							...re,
							className: H(Z(a.singleMonthHeaderInnerClass, D), re.className),
							children: bp(o.formatToParts(ne, e.titleFormat))
						})
					}),
					E ? O(OC, {
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
					}) : O("div", {
						className: H(Z(a.tableClass, {
							borderlessX: s,
							borderlessTop: c,
							borderlessBottom: l,
							multiMonthColumns: e.colCount || 0
						}), V.flexCol),
						style: { marginTop: S == null ? void 0 : -S },
						children: [O("div", {
							ref: this.handleTableHeaderEl,
							className: H(Z(a.tableHeaderClass, {
								isSticky: m,
								borderlessX: s,
								borderlessTop: c,
								borderlessBottom: l,
								multiMonthColumns: e.colCount || 0
							}), V.flexCol, m && V.sticky, m && V.z2),
							style: {
								top: m ? t.titleHeight : 0,
								marginBottom: x
							},
							children: [O(EC, {
								...p,
								role: "row",
								borderBottom: !1,
								cellIsNarrow: y,
								cellIsMicro: v,
								rowLevel: 0
							}), O("div", { className: Z(a.dayHeaderDividerClass, {
								isSticky: m,
								multiMonthColumns: e.colCount || 0,
								options: { allDaySlot: !!a.allDaySlot }
							}) })]
						}), O("div", {
							className: H(Z(a.tableBodyClass, {
								borderlessX: s,
								borderlessTop: c,
								borderlessBottom: l,
								multiMonthColumns: e.colCount || 0
							}), V.flexCol, h && V.rel, m && V.z1),
							style: {
								marginTop: x == null ? void 0 : -x,
								aspectRatio: h ? String(a.aspectRatio) : void 0
							},
							children: O(bC, {
								dateProfile: e.dateProfile,
								todayRange: e.todayRange,
								cellRows: u.cellRows,
								className: h ? V.fill : "",
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
		this._isUnmounting = !1, this.disconnectTitleHeight = nv(this.titleElRef.current, (e) => {
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
}, Zw = class extends hv {
	constructor() {
		super(...arguments), this.state = {}, this.splitDateProfileByMonth = q($w), this.buildMonthFormat = q(nT), this.scrollerRef = C(), this.tilesElRef = C(), this.scrollState = {}, this.handleInnerWidth = (e) => {
			this._isUnmounting || this.setState({ innerWidth: e });
		}, this.handleScrollStart = () => {
			this.scrollState.date = void 0, this.scrollState.top = void 0;
		}, this.handleScrollEnd = (e) => {
			let t = this.scrollerRef.current;
			e && t && (this.scrollState.top = t.y, this.scrollState.date = void 0);
		};
	}
	render() {
		let { context: e, props: t, state: n } = this, { options: r } = e, i = !t.forPrint && !P_(r), a = this.splitDateProfileByMonth(e.dateProfileGenerator, t.dateProfile, e.dateEnv, r.fixedWeekCount, r.showNonCurrentDates), o = this.buildMonthFormat(r.singleMonthTitleFormat, a), { multiMonthMaxColumns: s, singleMonthMinWidth: c } = r, { innerWidth: l } = n, u, d, f = !1;
		return l != null && (u = Math.max(1, Math.min(s, Math.floor(l / c))), t.forPrint && (u = Math.min(u, 2)), d = Rp(1 / u), f = u > 1), O(rv, {
			unit: "day",
			children: (n, r) => O(pv, {
				viewSpec: e.viewSpec,
				className: H(!t.forPrint && V.flexCol, t.className),
				children: [O(vv, {
					vertical: i,
					className: i ? V.liquid : "",
					ref: this.scrollerRef,
					children: O("div", {
						role: "list",
						ref: this.tilesElRef,
						"aria-labelledby": t.labelId,
						"aria-label": t.labelStr,
						className: V.safeTiles,
						children: a.map((e, n) => {
							let i = _p(e.currentRange.start);
							return x(Xw, {
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
				}), O(gx, { widthRef: this.handleInnerWidth })]
			})
		});
	}
	componentDidMount() {
		this._isUnmounting = !1, this.scrollState.date = this.props.dateProfile.currentDate, this.scrollerRef.current.addScrollStartListener(this.handleScrollStart), this.scrollerRef.current.addScrollEndListener(this.handleScrollEnd), setTimeout(() => {
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
			let t = this.tilesElRef.current, n = t?.querySelector(`[data-date="${_p(e.date)}"]`)?.parentElement;
			if (t && n) return Math.round(n.getBoundingClientRect().top) - Math.round(t.getBoundingClientRect().top);
		}
	}
}, Qw = B(1, "month");
function $w(e, t, n, r, i) {
	let { start: a, end: o } = t.currentRange, s = a, c = [];
	for (; s.valueOf() < o.valueOf();) {
		let a = n.add(s, Qw), o = {
			start: e.skipHiddenDays(s),
			end: e.skipHiddenDays(a, -1, !0)
		}, l = FC({
			currentRange: o,
			snapToWeek: !0,
			fixedWeekCount: r,
			dateEnv: n
		});
		l = {
			start: e.skipHiddenDays(l.start),
			end: e.skipHiddenDays(l.end, -1, !0)
		};
		let u = t.activeRange ? Gf(t.activeRange, i ? l : o) : null;
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
var eT = U({
	year: "numeric",
	month: "long"
}), tT = U({ month: "long" });
function nT(e, t) {
	return e || (t[0].currentRange.start.getUTCFullYear() === t[t.length - 1].currentRange.start.getUTCFullYear() ? tT : eT);
}
// node_modules/fullcalendar/all/index.js
var rT = [
	nx,
	IC,
	Lw,
	Yw,
	{
		name: "multimonth",
		initialView: "multiMonthYear",
		views: {
			multiMonth: {
				component: Zw,
				dateProfileGeneratorClass: PC,
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
], iT = class extends Xy {
	constructor(e, t = {}) {
		super(e, {
			...t,
			plugins: [...rT, ...t.plugins || []]
		});
	}
}, aT = "fc-classic-vQz", oT = "fc-classic-0Bj", sT = "fc-classic-uqo", cT = "fc-classic-3Xj", lT = "fc-classic-fFh", uT = "fc-classic-zIi", dT = "fc-classic-BaR", fT = "fc-classic-4yP", pT = `${fT} fc-classic-tCP fc-classic-8gz`, mT = "fc-classic-Ubk", hT = `${mT} fc-classic-OIx fc-classic-28F`, gT = "fc-classic-XUJ", _T = "fc-classic-1EY fc-classic-pps fc-classic-vs6", vT = `${_T} fc-classic-AWB fc-classic-hza`, yT = `${_T} fc-classic-MaV fc-classic-uuA`, bT = "fc-classic-1EY fc-classic-3wQ fc-classic-wsy fc-classic-lNM fc-classic-Jk3 fc-classic-AAA", xT = `${bT} fc-classic-ERR fc-classic-Dq8`, ST = `${bT} fc-classic-1V6 fc-classic-F99`, CT = (e) => H("fc-classic-wsy", e.isMajor ? "fc-classic-C0k" : "fc-classic-C1x", e.isDisabled ? "fc-classic-iYS" : e.isToday && "fc-classic-hbn"), wT = (e) => H("fc-classic-wsy fc-classic-C1x", e.isMinor && "fc-classic-TN2"), TT = {
	listItemEventClass: (e) => H("fc-classic-Ika fc-classic-7A6 fc-classic-Fvv", e.isNarrow ? "fc-classic-148" : "fc-classic-cKZ", e.isSelected ? H("fc-classic-k3f", e.isDragging && "fc-classic-qNs") : e.isInteractive ? pT : fT),
	listItemEventBeforeClass: (e) => H("fc-classic-Mjo", e.isNarrow ? "fc-classic-148" : "fc-classic-rVY"),
	listItemEventInnerClass: (e) => H("fc-classic-dl1 fc-classic-1sP fc-classic-XpK fc-classic-z5u fc-classic-aTF", e.isNarrow ? aT : "fc-classic-a3B"),
	listItemEventTimeClass: "fc-classic-F1o fc-classic-TZ4 fc-classic-pKG fc-classic-1Zl",
	listItemEventTitleClass: "fc-classic-F1o fc-classic-DIS fc-classic-TZ4 fc-classic-pKG fc-classic-OLq",
	rowEventClass: (e) => H(e.isStart && H("fc-classic-kmj", e.isNarrow ? "fc-classic-qvL" : "fc-classic-Jzj"), e.isEnd && H("fc-classic-Skl", e.isNarrow ? "fc-classic-9hC" : "fc-classic-3e1")),
	rowEventInnerClass: "fc-classic-z5u fc-classic-aTF",
	rowEventTimeClass: "fc-classic-F1o",
	rowEventTitleClass: "fc-classic-F1o",
	rowMoreLinkClass: (e) => H("fc-classic-Ika fc-classic-wsy fc-classic-Fvv", e.isNarrow ? "fc-classic-148 fc-classic-0Pr" : "fc-classic-sI7 fc-classic-cKZ fc-classic-d0j", pT),
	rowMoreLinkInnerClass: (e) => H("fc-classic-7A6", e.isNarrow ? aT : "fc-classic-a3B")
}, ET = "fc-classic-vnf fc-classic-mAY", DT = "fc-classic-rVY fc-classic-XM3 fc-classic-rif fc-classic-lMo", OT = {
	name: "theme-classic",
	optionDefaults: {
		className: "fc-classic-yth fc-classic-n5m",
		viewClass: (e) => {
			let t = e.options.headerToolbar || !e.borderlessTop, n = e.options.footerToolbar || !e.borderlessBottom, r = !e.borderlessX;
			return H("fc-classic-Jk3 fc-classic-GAX fc-classic-C1x", t && "fc-classic-ku3", n && "fc-classic-zi1", r && "fc-classic-1Wx");
		},
		toolbarClass: (e) => H("fc-classic-dl1 fc-classic-1sP fc-classic-dNl fc-classic-XpK fc-classic-N2M fc-classic-wwb", e.borderlessX && "fc-classic-Apf"),
		toolbarSectionClass: "fc-classic-yi0 fc-classic-dl1 fc-classic-1sP fc-classic-XpK fc-classic-wwb",
		toolbarTitleClass: "fc-classic-AVD fc-classic-DIS",
		buttonGroupClass: "fc-classic-dl1 fc-classic-1sP fc-classic-XpK",
		buttonClass: (e) => H("fc-classic-dl6 fc-classic-1Wx fc-classic-dl1 fc-classic-1sP fc-classic-XpK fc-classic-sOR fc-classic-lYz fc-classic-vwH fc-classic-9yp fc-classic-RnT fc-classic-cfp fc-classic-Z9U", e.isIconOnly ? "fc-classic-Eaq" : "fc-classic-Apf", e.buttonGroup ? "fc-classic-uk6 fc-classic-Tuc" : "fc-classic-Ig4", e.isSelected ? "fc-classic-rQI fc-classic-Adi" : "fc-classic-vXO fc-classic-bqK fc-classic-aIH fc-classic-nQ5 fc-classic-JWq fc-classic-9Rj fc-classic-5ky", e.isDisabled && "fc-classic-Q3Z fc-classic-3Lc"),
		buttons: {
			prev: { iconContent: () => kT(`${gT} fc-classic-asP`) },
			next: { iconContent: () => kT(`${gT} fc-classic-jmT fc-classic-jY6`) },
			prevYear: { iconContent: () => AT(`${gT} fc-classic-asP`) },
			nextYear: { iconContent: () => AT(`${gT} fc-classic-jmT fc-classic-jY6`) }
		},
		eventColor: "var(--fc-classic-event)",
		eventContrastColor: "var(--fc-classic-event-contrast)",
		eventClass: (e) => H(e.isDragging && "fc-classic-n5m", e.event.url && "fc-classic-JiE", e.isSelected ? H(oT, e.isDragging ? "fc-classic-1kP" : "fc-classic-tkw") : sT, uT),
		backgroundEventColor: "var(--fc-classic-background-event)",
		backgroundEventClass: "fc-classic-hsC fc-classic-jsy fc-classic-DO7",
		backgroundEventTitleClass: (e) => H("fc-classic-MGT fc-classic-L1Y", e.isNarrow ? `fc-classic-KUX ${aT}` : "fc-classic-XJa fc-classic-a3B"),
		listItemEventClass: "fc-classic-XpK",
		listItemEventBeforeClass: "fc-classic-lNM fc-classic-AAA",
		listItemEventInnerClass: "fc-classic-GAX",
		blockEventClass: (e) => H("fc-classic-bCs fc-classic-eYX fc-classic-d0j fc-classic-DO7 fc-classic-YjJ fc-classic-vwH", e.isDragging && !e.isSelected && "fc-classic-iTG", cT),
		blockEventInnerClass: "fc-classic-i9F fc-classic-cfp",
		blockEventTimeClass: "fc-classic-TZ4 fc-classic-pKG fc-classic-1Zl",
		blockEventTitleClass: "fc-classic-TZ4 fc-classic-pKG fc-classic-OLq",
		rowEventClass: (e) => H("fc-classic-Ika fc-classic-JIC", e.isStart && "fc-classic-3J4", e.isEnd && "fc-classic-USt"),
		rowEventBeforeClass: (e) => H(e.isStartResizable && H(e.isSelected ? xT : vT, "fc-classic-11a")),
		rowEventAfterClass: (e) => H(e.isEndResizable && H(e.isSelected ? xT : vT, "fc-classic-bEw")),
		rowEventInnerClass: (e) => H("fc-classic-dl1 fc-classic-1sP fc-classic-XpK", e.isNarrow ? aT : "fc-classic-a3B"),
		rowEventTimeClass: "fc-classic-DIS",
		columnEventClass: (e) => H("fc-classic-1Wx fc-classic-A3h fc-classic-yKG", e.isStart && "fc-classic-ku3 fc-classic-Z7Q", e.isEnd && "fc-classic-Ika fc-classic-zi1 fc-classic-2qh"),
		columnEventBeforeClass: (e) => H(e.isStartResizable && H(e.isSelected ? ST : yT, "fc-classic-YDC")),
		columnEventAfterClass: (e) => H(e.isEndResizable && H(e.isSelected ? ST : yT, "fc-classic-fJL")),
		columnEventInnerClass: (e) => H("fc-classic-dl1", e.isShort ? "fc-classic-KUX fc-classic-1sP fc-classic-XpK fc-classic-NWN" : "fc-classic-oQ2 fc-classic-sgX"),
		columnEventTimeClass: (e) => H(!e.isShort && "fc-classic-166", aT),
		columnEventTitleClass: (e) => H(!e.isShort && "fc-classic-2rx", e.isShort || e.isNarrow ? aT : "fc-classic-a3B"),
		moreLinkClass: `${sT} ${uT}`,
		moreLinkInnerClass: "fc-classic-TZ4 fc-classic-pKG",
		columnMoreLinkClass: `fc-classic-Ika fc-classic-Fvv fc-classic-wsy fc-classic-d0j fc-classic-4MR ${dT} fc-classic-vwH fc-classic-A3h fc-classic-yKG ${cT}`,
		columnMoreLinkInnerClass: (e) => H("fc-classic-KUX", e.isNarrow ? aT : "fc-classic-a3B"),
		dayHeaderAlign: (e) => e.inPopover ? "start" : "center",
		dayHeaderClass: (e) => H("fc-classic-E9P", e.isDisabled && "fc-classic-iYS", e.inPopover ? "fc-classic-zi1 fc-classic-C1x fc-classic-k3f" : H("fc-classic-wsy", e.isMajor ? "fc-classic-C0k" : "fc-classic-C1x")),
		dayHeaderInnerClass: (e) => H("fc-classic-rVY fc-classic-cJ3 fc-classic-dl1 fc-classic-sgX", e.isNarrow ? aT : "fc-classic-9yp"),
		dayHeaderDividerClass: "fc-classic-zi1 fc-classic-C1x",
		dayCellClass: CT,
		dayCellTopClass: (e) => H(e.isNarrow ? "fc-classic-toR" : "fc-classic-84e", "fc-classic-dl1 fc-classic-1sP fc-classic-LMv"),
		dayCellTopInnerClass: (e) => H("fc-classic-rVY fc-classic-TZ4", e.isNarrow ? `fc-classic-cJ3 ${aT}` : "fc-classic-V9v fc-classic-9yp", e.isOther && "fc-classic-taq", e.monthText && "fc-classic-DIS"),
		dayCellInnerClass: (e) => H(e.inPopover && "fc-classic-3N5"),
		popoverClass: "fc-classic-Jk3 fc-classic-GAX fc-classic-wsy fc-classic-C1x fc-classic-tkw fc-classic-aNc fc-classic-n5m",
		popoverCloseClass: `fc-classic-bCs fc-classic-1EY fc-classic-2ik fc-classic-2w8 ${sT} ${uT} fc-classic-Z9U`,
		popoverCloseContent: () => jT("fc-classic-XUJ fc-classic-9yp fc-classic-mAY"),
		dayLaneClass: CT,
		dayLaneInnerClass: (e) => e.isStack ? "fc-classic-gMS" : e.isNarrow ? "fc-classic-148" : "fc-classic-Jzj fc-classic-B3G",
		slotLaneClass: wT,
		listDayHeaderClass: "fc-classic-zi1 fc-classic-C1x fc-classic-SDU fc-classic-nHS fc-classic-dl1 fc-classic-1sP fc-classic-XpK fc-classic-N2M",
		listDayHeaderInnerClass: "fc-classic-Apf fc-classic-dl6 fc-classic-9yp fc-classic-DIS",
		singleMonthClass: (e) => H(e.multiMonthColumns > 1 && "fc-classic-jD5", e.multiMonthColumns === 1 && !e.isLast && "fc-classic-zi1 fc-classic-C1x"),
		singleMonthHeaderClass: (e) => H(e.multiMonthColumns > 1 ? "fc-classic-cM0" : "fc-classic-dl6 fc-classic-zi1 fc-classic-C1x fc-classic-Jk3", "fc-classic-XpK"),
		singleMonthHeaderInnerClass: "fc-classic-1Po fc-classic-DIS",
		tableHeaderClass: "fc-classic-Jk3",
		fillerClass: "fc-classic-wsy fc-classic-C1x fc-classic-lMo",
		dayHeaderRowClass: "fc-classic-wsy fc-classic-C1x",
		dayRowClass: "fc-classic-wsy fc-classic-C1x",
		slotHeaderRowClass: "fc-classic-wsy fc-classic-C1x",
		slotHeaderClass: wT,
		navLinkClass: `fc-classic-Eu0 ${sT} ${lT} ${uT}`,
		inlineWeekNumberClass: (e) => H("fc-classic-1EY fc-classic-n9G fc-classic-rbS fc-classic-C2g fc-classic-KUX fc-classic-HXA fc-classic-m9h fc-classic-k3f", e.isNarrow ? aT : "fc-classic-9yp"),
		nonBusinessHoursClass: "fc-classic-iYS",
		highlightClass: "fc-classic-hLU",
		resourceDayHeaderAlign: "center",
		resourceDayHeaderClass: (e) => H("fc-classic-wsy", e.isMajor ? "fc-classic-C0k" : "fc-classic-C1x"),
		resourceDayHeaderInnerClass: (e) => H("fc-classic-rVY fc-classic-cJ3 fc-classic-dl1 fc-classic-sgX", e.isNarrow ? aT : "fc-classic-9yp"),
		resourceColumnHeaderClass: "fc-classic-wsy fc-classic-C1x fc-classic-E9P",
		resourceColumnHeaderInnerClass: "fc-classic-bvX fc-classic-9yp",
		resourceColumnResizerClass: "fc-classic-1EY fc-classic-AWB fc-classic-4Tv fc-classic-dnf",
		resourceGroupHeaderClass: "fc-classic-wsy fc-classic-C1x fc-classic-k3f",
		resourceGroupHeaderInnerClass: "fc-classic-bvX fc-classic-9yp",
		resourceCellClass: "fc-classic-wsy fc-classic-C1x",
		resourceCellInnerClass: "fc-classic-bvX fc-classic-9yp",
		resourceIndentClass: "fc-classic-Mde fc-classic-kp0 fc-classic-E9P",
		resourceExpanderClass: `fc-classic-bCs ${sT} ${uT}`,
		resourceExpanderContent: (e) => e.isExpanded ? NT(ET) : MT(ET),
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
			...TT,
			dayCellBottomClass: "fc-classic-toR"
		},
		multiMonth: {
			...TT,
			dayCellBottomClass: "fc-classic-toR",
			tableClass: (e) => H(e.multiMonthColumns > 1 && "fc-classic-C1x fc-classic-wsy")
		},
		timeGrid: {
			...TT,
			dayCellBottomClass: "fc-classic-mhE",
			weekNumberHeaderClass: "fc-classic-XpK fc-classic-LMv",
			weekNumberHeaderInnerClass: (e) => H("fc-classic-rVY fc-classic-cJ3", e.isNarrow ? aT : "fc-classic-9yp"),
			allDayHeaderClass: "fc-classic-XpK fc-classic-LMv",
			allDayHeaderInnerClass: (e) => H("fc-classic-rVY fc-classic-2tF fc-classic-2HE", e.isNarrow ? aT : "fc-classic-9yp"),
			allDayDividerClass: "fc-classic-JIC fc-classic-C1x fc-classic-8ub fc-classic-k3f",
			slotHeaderClass: "fc-classic-LMv",
			slotHeaderInnerClass: (e) => H("fc-classic-rVY fc-classic-cJ3", e.isNarrow ? aT : "fc-classic-9yp"),
			slotHeaderDividerClass: "fc-classic-USt fc-classic-C1x",
			nowIndicatorHeaderClass: "fc-classic-rbS fc-classic-a10 fc-classic-XM3 fc-classic-rif fc-classic-jIH fc-classic-0qY",
			nowIndicatorLineClass: "fc-classic-ku3 fc-classic-sYT"
		},
		list: {
			listDayClass: (e) => H(!e.isLast && "fc-classic-zi1 fc-classic-C1x"),
			listItemEventClass: (e) => H("fc-classic-bCs fc-classic-Apf fc-classic-dl6 fc-classic-wwb fc-classic-ku3 fc-classic-C1x", e.isInteractive ? H(hT, lT) : mT),
			listItemEventBeforeClass: "fc-classic-GOm",
			listItemEventInnerClass: "fc-classic-eF2",
			listItemEventTimeClass: "fc-classic-88I fc-classic-yi0 fc-classic-roZ fc-classic-kMV fc-classic-TZ4 fc-classic-pKG fc-classic-IPx fc-classic-9yp",
			listItemEventTitleClass: (e) => H("fc-classic-1El fc-classic-2KU fc-classic-TZ4 fc-classic-pKG fc-classic-9yp", e.event.url && "fc-classic-Ogp"),
			noEventsClass: "fc-classic-k3f fc-classic-dl1 fc-classic-sgX fc-classic-XpK fc-classic-E9P",
			noEventsInnerClass: "fc-classic-OUe fc-classic-jGI fc-classic-P9h"
		},
		timeline: {
			rowEventClass: (e) => H(e.isEnd && "fc-classic-9hC", "fc-classic-XpK"),
			rowEventBeforeClass: (e) => !e.isStart && `${DT} fc-classic-Bda fc-classic-5JV`,
			rowEventAfterClass: (e) => !e.isEnd && `${DT} fc-classic-hhi fc-classic-LaM`,
			rowEventInnerClass: (e) => e.options.eventOverlap ? "fc-classic-2rx" : "fc-classic-End",
			rowEventTimeClass: "fc-classic-oQ2",
			rowEventTitleClass: "fc-classic-oQ2",
			rowMoreLinkClass: `fc-classic-9hC fc-classic-Ika fc-classic-wsy fc-classic-d0j fc-classic-4MR ${dT} fc-classic-vwH`,
			rowMoreLinkInnerClass: "fc-classic-KUX fc-classic-a3B",
			slotHeaderAlign: (e) => e.isTime ? "start" : "center",
			slotHeaderClass: (e) => H("fc-classic-E9P", !e.level && "fc-classic-pKG"),
			slotHeaderInnerClass: (e) => H("fc-classic-fn8 fc-classic-V9v fc-classic-9yp", e.hasNavLink && "fc-classic-Eu0"),
			slotHeaderDividerClass: "fc-classic-zi1 fc-classic-C1x",
			nowIndicatorHeaderClass: "fc-classic-n9G fc-classic-J04 fc-classic-ybF fc-classic-Pqk fc-classic-bLA fc-classic-sYT",
			nowIndicatorLineClass: "fc-classic-3J4 fc-classic-sYT"
		}
	}
};
function kT(e) {
	return O("svg", {
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
		children: O("polyline", { points: "15 18 9 12 15 6" })
	});
}
function AT(e) {
	return O("svg", {
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
		children: [O("polyline", { points: "11 17 6 12 11 7" }), O("polyline", { points: "18 17 13 12 18 7" })]
	});
}
function jT(e) {
	return O("svg", {
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
		children: [O("line", {
			x1: "18",
			y1: "6",
			x2: "6",
			y2: "18"
		}), O("line", {
			x1: "6",
			y1: "6",
			x2: "18",
			y2: "18"
		})]
	});
}
function MT(e) {
	return O("svg", {
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
			O("rect", {
				x: "3",
				y: "3",
				width: "18",
				height: "18",
				rx: "2",
				ry: "2"
			}),
			O("line", {
				x1: "12",
				y1: "8",
				x2: "12",
				y2: "16"
			}),
			O("line", {
				x1: "8",
				y1: "12",
				x2: "16",
				y2: "12"
			})
		]
	});
}
function NT(e) {
	return O("svg", {
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
		children: [O("rect", {
			x: "3",
			y: "3",
			width: "18",
			height: "18",
			rx: "2",
			ry: "2"
		}), O("line", {
			x1: "8",
			y1: "12",
			x2: "16",
			y2: "12"
		})]
	});
}
// node_modules/fullcalendar/chunks/4cb239c3.js
var PT = {
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
// node_modules/fullcalendar/chunks/8acff3e8.js
function FT(e) {
	return e === "Tag" || e === "Monat" ? "r" : e === "Jahr" ? "s" : "";
}
var IT = {
	Tag: "Tagesansicht",
	Woche: "Wochenansicht",
	Monat: "Monatsansicht",
	Jahr: "Jahresansicht"
}, LT = {
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
		return `Vorherige${FT(e)} ${e}`;
	},
	nextHint(e) {
		return `Nächste${FT(e)} ${e}`;
	},
	todayHint(e) {
		return e === "Tag" ? "Heute" : `Diese${FT(e)} ${e}`;
	},
	viewHint(e) {
		return IT[e] || e;
	},
	navLinkHint: "Gehe zu $0",
	moreLinkHint(e) {
		return "Zeige " + (e === 1 ? "ein weiteres Ereignis" : e + " weitere Ereignisse");
	},
	closeHint: "Schließen",
	eventsHint: "Ereignisse"
}, RT = {
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
}, zT = {
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
// node_modules/temporal-polyfill/chunks/classApi-basic.js
function BT(e) {
	let t = Mi(e).toLowerCase();
	return t === "iso8601" ? void 0 : t === "gregory" ? 0 : void k(Wt(e, "temporal-polyfill/full"));
}
function VT(e = kn) {
	return BT(e);
}
var HT = /*@__PURE__*/ new WeakMap(), UT = /*@__PURE__*/ Vu(Ru, class {
	constructor(e, t, n = void 0) {
		let r = wa(Li(e)), i = Rc(Nc(t)), a = VT(n);
		JT(this, Fa(r, i, a));
	}
	static from(e, t = void 0) {
		return WT(qT(e, t));
	}
	static compare(e, t) {
		return fs(qT(e), qT(t));
	}
	get calendarId() {
		return Mn($(this).calendar);
	}
	get timeZoneId() {
		return $(this).timeZone.id;
	}
	get epochMilliseconds() {
		return Rr($(this).epochNanoseconds);
	}
	get epochNanoseconds() {
		return $(this).epochNanoseconds;
	}
	get offset() {
		return wc(ao($(this)).offsetNanoseconds);
	}
	get offsetNanoseconds() {
		return ao($(this)).offsetNanoseconds;
	}
	get hoursInDay() {
		return Ba($(this));
	}
	with(e, t = void 0) {
		return WT(cd($(this), yE(e), t));
	}
	withCalendar(e) {
		return WT({
			...$(this),
			calendar: WE(e)
		});
	}
	withTimeZone(e) {
		return WT({
			...$(this),
			timeZone: Rc(YT(e))
		});
	}
	withPlainTime(e = void 0) {
		return WT(Ou($(this), EE(e)));
	}
	add(e, t = void 0) {
		return WT(fo($(this), XE(e), L(t)));
	}
	subtract(e, t = void 0) {
		return WT(fo($(this), es(XE(e)), L(t)));
	}
	until(e, t = void 0) {
		return qE(Zu(0, $(this), qT(e), t));
	}
	since(e, t = void 0) {
		return qE(Zu(1, $(this), qT(e), t));
	}
	round(e) {
		let t = $(this), [n, r, i] = ha(e);
		return WT(za(t, n, r, i));
	}
	startOfDay() {
		return WT(Va($(this)));
	}
	equals(e) {
		return vs($(this), qT(e));
	}
	toInstant() {
		return QT(Xl($(this)));
	}
	toPlainDateTime() {
		return AE(Zl($(this)));
	}
	toPlainDate() {
		return LE(Ql($(this)));
	}
	toPlainTime() {
		return SE($l($(this)));
	}
	toLocaleString(e = void 0, t = {}) {
		let n = $(this), r = new Xs(e, uu(Su(t), Eo(n)));
		return du(r, n), r.format(Rr(n.epochNanoseconds));
	}
	toString(e = void 0) {
		return oc($(this), e);
	}
	toJSON() {
		return oc($(this));
	}
	getTimeZoneTransition(e) {
		let t = $(this), n = t.timeZone.C(t.epochNanoseconds, ku(e));
		return n === void 0 ? null : WT({
			...t,
			epochNanoseconds: n
		});
	}
	valueOf() {
		return Wu();
	}
}, GT, Ku, Yu, $n);
function WT(e) {
	return JT(Object.create(UT.prototype), e);
}
function $(e) {
	return KT(e) || Uu();
}
function GT(e) {
	let t = $(e);
	return {
		...ao(t),
		calendar: t.calendar
	};
}
function KT(e) {
	return HT.get(e);
}
function qT(e, t) {
	if (Yt(e)) {
		let n = KT(e);
		return n ? (zs(t), n) : qc(YT, HE(e), e, t);
	}
	return rl(e, BT, t);
}
function JT(e, t) {
	return HT.set(e, t), Hu(e), e;
}
function YT(e) {
	if (Yt(e)) {
		let t = KT(e);
		return t || A(Gt(e)), t.timeZone.id;
	}
	return ((e) => Pc(fl(Mi(e))))(e);
}
var XT = /*@__PURE__*/ new WeakMap(), ZT = /*@__PURE__*/ Vu(zu, class {
	constructor(e) {
		let t = wa(Li(e));
		rE(this, Pa(t));
	}
	static from(e) {
		return QT(tE(e));
	}
	static fromEpochMilliseconds(e) {
		return QT(su(e));
	}
	static fromEpochNanoseconds(e) {
		return QT(cu(e));
	}
	static compare(e, t) {
		return fs(tE(e), tE(t));
	}
	get epochMilliseconds() {
		return Rr($T(this).epochNanoseconds);
	}
	get epochNanoseconds() {
		return $T(this).epochNanoseconds;
	}
	add(e) {
		return QT(Pa(ho($T(this).epochNanoseconds, XE(e))));
	}
	subtract(e) {
		return QT(Pa(ho($T(this).epochNanoseconds, es(XE(e)))));
	}
	until(e, t = void 0) {
		return qE(Xu(0, $T(this), tE(e), t));
	}
	since(e, t = void 0) {
		return qE(Xu(1, $T(this), tE(e), t));
	}
	round(e) {
		let t = $T(this), [n, r, i] = ha(e, 5, 1);
		return QT(Pa(to(t.epochNanoseconds, io(n, r), i)));
	}
	equals(e) {
		return _s($T(this), tE(e));
	}
	toZonedDateTimeISO(e) {
		return WT(Yl($T(this), Rc(YT(e))));
	}
	toLocaleString(e = void 0, t = {}) {
		let n = $T(this);
		return new Xs(e, xu(t)).format(Rr(n.epochNanoseconds));
	}
	toString(e = void 0) {
		return ic(YT, $T(this), e);
	}
	toJSON() {
		return ic(YT, $T(this));
	}
	valueOf() {
		return Wu();
	}
});
function QT(e) {
	return rE(Object.create(ZT.prototype), e);
}
function $T(e) {
	return eE(e) || Uu();
}
function eE(e) {
	return XT.get(e);
}
function tE(e) {
	if (Yt(e)) {
		let t = eE(e);
		if (t) return t;
		let n = KT(e);
		if (n) return Pa(n.epochNanoseconds);
	}
	return tl(e);
}
var { toTemporalInstant: nE } = { toTemporalInstant() {
	let e = Date.prototype.valueOf.call(this);
	return QT(Pa(BigInt(Pi(e)) * qn));
} };
function rE(e, t) {
	return XT.set(e, t), Hu(e), e;
}
var iE = /*@__PURE__*/ new WeakMap(), aE = /*@__PURE__*/ Vu(Pu, class {
	constructor(e, t, n = void 0, r) {
		let i = kt(e), a = kt(t), o = VT(n), s = xa(si({
			year: kt(r ?? 1972),
			month: i,
			day: a
		}));
		uE(this, F(s, o));
	}
	static from(e, t = void 0) {
		return oE(lE(e, t));
	}
	get calendarId() {
		return Mn(sE(this).calendar);
	}
	with(e, t = void 0) {
		return oE(od(sE(this), yE(e), t));
	}
	equals(e) {
		return Ss(sE(this), lE(e));
	}
	toPlainDate(e) {
		return LE(ru(sE(this).calendar, this, e));
	}
	toLocaleString(e = void 0, t = {}) {
		let n = sE(this), r = new Xs(e, lu(Du(t)));
		return du(r, n, 1), r.format(Hr(n));
	}
	toString(e = void 0) {
		return fc(sE(this), e);
	}
	toJSON() {
		return fc(sE(this));
	}
	valueOf() {
		return Wu();
	}
}, sE, qu);
function oE(e) {
	return uE(Object.create(aE.prototype), e);
}
function sE(e) {
	return cE(e) || Uu();
}
function cE(e) {
	return iE.get(e);
}
function lE(e, t) {
	if (Yt(e)) {
		let n = cE(e);
		if (n) return L(t), n;
		let r = UE(e);
		return Zc(r === void 0 ? void 0 : r, r === void 0, e, t);
	}
	let n = cl(e, BT);
	return L(t), n;
}
function uE(e, t) {
	return iE.set(e, t), Hu(e), e;
}
var dE = /*@__PURE__*/ new WeakMap(), fE = /*@__PURE__*/ Vu(Nu, class {
	constructor(e, t, n = void 0, r) {
		let i = kt(e), a = kt(t), o = VT(n), s = ba(si({
			year: i,
			month: a,
			day: kt(r ?? 1)
		}));
		_E(this, F(s, o));
	}
	static from(e, t = void 0) {
		return pE(gE(e, t));
	}
	static compare(e, t) {
		return hs(gE(e), gE(t));
	}
	get calendarId() {
		return Mn(mE(this).calendar);
	}
	with(e, t = void 0) {
		return pE(ad(mE(this), yE(e), t));
	}
	add(e, t = void 0) {
		let n = mE(this);
		return pE(F(uo(n.calendar, n, XE(e), L(t)), n.calendar));
	}
	subtract(e, t = void 0) {
		let n = mE(this);
		return pE(F(uo(n.calendar, n, es(XE(e)), L(t)), n.calendar));
	}
	until(e, t = void 0) {
		return qE(ed(0, mE(this), gE(e), t));
	}
	since(e, t = void 0) {
		return qE(ed(1, mE(this), gE(e), t));
	}
	equals(e) {
		return xs(mE(this), gE(e));
	}
	toPlainDate(e) {
		return LE(nu(mE(this).calendar, this, e));
	}
	toLocaleString(e = void 0, t = {}) {
		let n = mE(this), r = new Xs(e, lu(Eu(t)));
		return du(r, n, 1), r.format(Hr(n));
	}
	toString(e = void 0) {
		return dc(mE(this), e);
	}
	toJSON() {
		return dc(mE(this));
	}
	valueOf() {
		return Wu();
	}
}, mE, Gu, Ju);
function pE(e) {
	return _E(Object.create(fE.prototype), e);
}
function mE(e) {
	return hE(e) || Uu();
}
function hE(e) {
	return dE.get(e);
}
function gE(e, t) {
	if (Yt(e)) {
		let n = hE(e);
		return n ? (L(t), n) : Xc(HE(e), e, t);
	}
	let n = ol(e, BT);
	return L(t), n;
}
function _E(e, t) {
	return dE.set(e, t), Hu(e), e;
}
function vE(e) {
	if (!Yt(e)) return;
	let t = eE(e);
	return t ? [zu, t] : (t = KT(e), t ? [Ru, t] : (t = ME(e), t ? [Iu, t] : (t = zE(e), t ? [Fu, t] : (t = wE(e), t ? [Lu, t] : (t = hE(e), t ? [Nu, t] : (t = cE(e), t ? [Pu, t] : (t = YE(e), t ? [Bu, t] : void 0)))))));
}
function yE(e) {
	return (vE(e) || e.calendar !== void 0 || e.timeZone !== void 0) && A("Invalid bag"), e;
}
var bE = /*@__PURE__*/ new WeakMap(), xE = /*@__PURE__*/ Vu(Lu, class {
	constructor(e = 0, t = 0, n = 0, r = 0, i = 0, a = 0) {
		let o = Dr(nn(kt, {
			hour: e,
			minute: t,
			second: n,
			millisecond: r,
			microsecond: i,
			nanosecond: a
		}));
		DE(this, La(o));
	}
	static from(e, t = void 0) {
		return SE(TE(e, t));
	}
	static compare(e, t) {
		return gs(TE(e), TE(t));
	}
	with(e, t = void 0) {
		return SE(sd(CE(this), yE(e), t));
	}
	add(e) {
		return SE(mo(CE(this), XE(e))[0]);
	}
	subtract(e) {
		return SE(mo(CE(this), es(XE(e)))[0]);
	}
	until(e, t = void 0) {
		return qE(td(0, CE(this), TE(e), t));
	}
	since(e, t = void 0) {
		return qE(td(1, CE(this), TE(e), t));
	}
	round(e) {
		let t = CE(this), [n, r, i] = ha(e, 5);
		return SE(qa(t, ro(n, r), i)[0]);
	}
	equals(e) {
		return Cs(CE(this), TE(e));
	}
	toLocaleString(e = void 0, t = {}) {
		let n = CE(this);
		return new Xs(e, lu(Tu(t))).format(jr(n));
	}
	toString(e = void 0) {
		return mc(CE(this), e);
	}
	toJSON() {
		return mc(CE(this));
	}
	valueOf() {
		return Wu();
	}
}, CE, $n);
function SE(e) {
	return DE(Object.create(xE.prototype), e);
}
function CE(e) {
	return wE(e) || Uu();
}
function wE(e) {
	return bE.get(e);
}
function TE(e, t) {
	if (Yt(e)) {
		let n = wE(e);
		if (n) return L(t), n;
		let r = ME(e);
		if (r) return L(t), La(r);
		let i = KT(e);
		return i ? (L(t), $l(i)) : Qc(e, t);
	}
	let n = ll(e);
	return L(t), n;
}
function EE(e) {
	return e === void 0 ? void 0 : TE(e);
}
function DE(e, t) {
	return bE.set(e, t), Hu(e), e;
}
var OE = /*@__PURE__*/ new WeakMap(), kE = /*@__PURE__*/ Vu(Iu, class {
	constructor(e, t, n, r = 0, i = 0, a = 0, o = 0, s = 0, c = 0, l = void 0) {
		let u = Sa(oi(nn(kt, {
			year: e,
			month: t,
			day: n,
			hour: r,
			minute: i,
			second: a,
			millisecond: o,
			microsecond: s,
			nanosecond: c
		}))), d = VT(l);
		PE(this, Ia(u, d));
	}
	static from(e, t = void 0) {
		return AE(NE(e, t));
	}
	static compare(e, t) {
		return ms(NE(e), NE(t));
	}
	get calendarId() {
		return Mn(jE(this).calendar);
	}
	with(e, t = void 0) {
		return AE(id(jE(this), yE(e), t));
	}
	withCalendar(e) {
		return AE(Ia(jE(this), WE(e)));
	}
	withPlainTime(e = void 0) {
		let t = jE(this);
		return AE(Us(t, EE(e), t.calendar));
	}
	add(e, t = void 0) {
		let n = jE(this);
		return AE(Ia(po(n.calendar, n, XE(e), L(t)), n.calendar));
	}
	subtract(e, t = void 0) {
		let n = jE(this);
		return AE(Ia(po(n.calendar, n, es(XE(e)), L(t)), n.calendar));
	}
	until(e, t = void 0) {
		return qE(Qu(0, jE(this), NE(e), t));
	}
	since(e, t = void 0) {
		return qE(Qu(1, jE(this), NE(e), t));
	}
	round(e) {
		let t = jE(this), [n, r, i] = ha(e);
		return AE(Ia(Ka(t, ro(n, r), i), t.calendar));
	}
	equals(e) {
		return ys(jE(this), NE(e));
	}
	toZonedDateTime(e, t = void 0) {
		return WT(eu(jE(this), Rc(YT(e)), Bs(t)));
	}
	toPlainDate() {
		let e = jE(this);
		return LE(F(e, e.calendar));
	}
	toPlainTime() {
		return SE(La(jE(this)));
	}
	toLocaleString(e = void 0, t = {}) {
		let n = jE(this), r = new Xs(e, lu(Cu(t)));
		return du(r, n), r.format(Br(n));
	}
	toString(e = void 0) {
		return cc(jE(this), e);
	}
	toJSON() {
		return cc(jE(this));
	}
	valueOf() {
		return Wu();
	}
}, jE, Ku, Yu, $n);
function AE(e) {
	return PE(Object.create(kE.prototype), e);
}
function jE(e) {
	return ME(e) || Uu();
}
function ME(e) {
	return OE.get(e);
}
function NE(e, t) {
	if (Yt(e)) {
		let n = ME(e);
		if (n) return L(t), n;
		let r = zE(e);
		if (r) return L(t), Ia(P(r, Er), r.calendar);
		let i = KT(e);
		return i ? (L(t), Zl(i)) : Jc(HE(e), e, t);
	}
	let n = il(e, BT);
	return L(t), n;
}
function PE(e, t) {
	return OE.set(e, t), Hu(e), e;
}
var FE = /*@__PURE__*/ new WeakMap(), IE = /*@__PURE__*/ Vu(Fu, class {
	constructor(e, t, n, r = void 0) {
		let i = xa(si(nn(kt, {
			year: e,
			month: t,
			day: n
		}))), a = VT(r);
		VE(this, F(i, a));
	}
	static from(e, t = void 0) {
		return LE(BE(e, t));
	}
	static compare(e, t) {
		return hs(BE(e), BE(t));
	}
	get calendarId() {
		return Mn(RE(this).calendar);
	}
	with(e, t = void 0) {
		return LE(rd(RE(this), yE(e), t));
	}
	withCalendar(e) {
		return LE(F(RE(this), WE(e)));
	}
	add(e, t = void 0) {
		let n = RE(this);
		return LE(F(go(n.calendar, n, XE(e), L(t)), n.calendar));
	}
	subtract(e, t = void 0) {
		let n = RE(this);
		return LE(F(go(n.calendar, n, es(XE(e)), L(t)), n.calendar));
	}
	until(e, t = void 0) {
		return qE($u(0, RE(this), BE(e), t));
	}
	since(e, t = void 0) {
		return qE($u(1, RE(this), BE(e), t));
	}
	equals(e) {
		return bs(RE(this), BE(e));
	}
	toZonedDateTime(e) {
		let t = Yt(e) ? {
			timeZone: e.timeZone,
			plainTime: e.plainTime
		} : { timeZone: e }, n = RE(this), r = YT(t.timeZone), i = t.plainTime, a = i === void 0 ? void 0 : TE(i);
		return WT(tu(n, Rc(r), a));
	}
	toPlainDateTime(e = void 0) {
		let t = RE(this);
		return AE(Us(t, EE(e), t.calendar));
	}
	toPlainYearMonth() {
		return pE(au(RE(this).calendar, this));
	}
	toPlainMonthDay() {
		return oE(iu(RE(this).calendar, this));
	}
	toLocaleString(e = void 0, t = {}) {
		let n = RE(this), r = new Xs(e, lu(wu(t)));
		return du(r, n), r.format(Hr(n));
	}
	toString(e = void 0) {
		return uc(RE(this), e);
	}
	toJSON() {
		return uc(RE(this));
	}
	valueOf() {
		return Wu();
	}
}, RE, Ku, Yu);
function LE(e) {
	return VE(Object.create(IE.prototype), e);
}
function RE(e) {
	return zE(e) || Uu();
}
function zE(e) {
	return FE.get(e);
}
function BE(e, t) {
	if (Yt(e)) {
		let n = zE(e);
		if (n) return L(t), n;
		let r = ME(e);
		if (r) return L(t), F(r, r.calendar);
		let i = KT(e);
		return i ? (L(t), Ql(i)) : Yc(HE(e), e, t);
	}
	let n = al(e, BT);
	return L(t), n;
}
function VE(e, t) {
	return FE.set(e, t), Hu(e), e;
}
function HE(e) {
	let t = UE(e);
	return t === void 0 ? void 0 : t;
}
function UE(e) {
	let { calendar: t } = e;
	if (t !== void 0) return WE(t);
}
function WE(e) {
	if (Yt(e)) {
		let t = zE(e) || ME(e) || KT(e) || cE(e) || hE(e);
		return t || A(Ut(e)), t.calendar;
	}
	return ((e) => BT(dl(Mi(e))))(e);
}
var GE = /*@__PURE__*/ new WeakMap(), KE = /*@__PURE__*/ Vu(Bu, class {
	constructor(e = 0, t = 0, n = 0, r = 0, i = 0, a = 0, o = 0, s = 0, c = 0, l = 0) {
		let u = ns(nn(Ri, {
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
		QE(this, Ra(u));
	}
	static from(e) {
		return qE(XE(e));
	}
	static compare(e, t, n = void 0) {
		return ps(ZE, XE(e), XE(t), n);
	}
	get sign() {
		return JE(this).sign;
	}
	get blank() {
		return !JE(this).sign;
	}
	with(e) {
		return qE(Gl(JE(this), e));
	}
	negated() {
		return qE($o(JE(this)));
	}
	abs() {
		return qE(Qo(JE(this)));
	}
	add(e) {
		return qE(Xo(0, JE(this), XE(e)));
	}
	subtract(e) {
		return qE(Xo(1, JE(this), XE(e)));
	}
	round(e) {
		return qE(Zo(ZE, JE(this), e));
	}
	total(e) {
		return ql(ZE, JE(this), e);
	}
	toLocaleString(e = void 0, t) {
		let n = JE(this);
		return Intl.DurationFormat ? new Intl.DurationFormat(e, t).format(n) : hc(n, t);
	}
	toString(e = void 0) {
		return hc(JE(this), e);
	}
	toJSON() {
		return hc(JE(this));
	}
	valueOf() {
		return Wu();
	}
}, JE, Ci);
function qE(e) {
	return QE(Object.create(KE.prototype), e);
}
function JE(e) {
	return YE(e) || Uu();
}
function YE(e) {
	return GE.get(e);
}
function XE(e) {
	return Yt(e) ? YE(e) || $c(e) : ul(e);
}
function ZE(e) {
	if (e !== void 0) {
		if (Yt(e)) {
			let t = KT(e);
			if (t) return t;
			let n = zE(e);
			if (n) return n;
			let r = ME(e);
			return r ? F(r, r.calendar) : Kc(YT, HE(e), e);
		}
		return nl(e, BT);
	}
}
function QE(e, t) {
	return GE.set(e, t), Hu(e), e;
}
var $E = /*@__PURE__*/ Object.defineProperties({}, {
	...tn("Temporal.Now"),
	...en({
		timeZoneId() {
			return Mu();
		},
		instant() {
			return QT(Pa(ju()));
		},
		zonedDateTimeISO(e = Mu()) {
			let t = Rc(YT(e));
			return WT(Fa(ju(), t));
		},
		plainDateTimeISO(e = Mu()) {
			return AE(Ia(Au(Rc(YT(e)))));
		},
		plainDateISO(e = Mu()) {
			return LE(F(Au(Rc(YT(e)))));
		},
		plainTimeISO(e = Mu()) {
			return SE(La(Au(Rc(YT(e)))));
		}
	})
}), eD = /*@__PURE__*/ Object.defineProperties({}, {
	...tn("Temporal"),
	...en({
		PlainYearMonth: fE,
		PlainMonthDay: aE,
		PlainDate: IE,
		PlainTime: xE,
		PlainDateTime: kE,
		ZonedDateTime: UT,
		Instant: ZT,
		Duration: KE,
		Now: $E
	})
}), tD = ht || eD;
ht && Date.prototype.toTemporalInstant;
// src/Resources/assets/calendar-move.js
function nD(e, t, n) {
	if (!e.allDay) return e.start.toISOString().replace(/\.\d{3}Z$/, "+00:00");
	let r = tD.Instant.from(t.start.toISOString()).toZonedDateTimeISO(n), i = tD.PlainDate.from(e.startStr.slice(0, 10));
	return r.with({
		year: i.year,
		month: i.month,
		day: i.day
	}).toString({
		timeZoneName: "never",
		fractionalSecondDigits: 0
	});
}
async function rD(e, t, n, r = fetch) {
	let i = await r(e.event.extendedProps.moveUrl, {
		method: "POST",
		headers: { "Content-Type": "application/x-www-form-urlencoded" },
		body: new URLSearchParams({
			_token: n,
			version: String(e.oldEvent.extendedProps.version),
			date: nD(e.event, e.oldEvent, t)
		})
	});
	if (!i.ok) throw Error(i.status === 409 ? "conflict" : "error");
	let a = await i.json();
	e.event.setExtendedProp("version", a.version), e.event.setStart(a.date, { allDay: !1 });
}
// src/Resources/assets/calendar.js
var iD = document.querySelector("[data-newsletter-calendar]");
if (iD) {
	let e = iD.dataset, t = document.querySelector("[data-calendar-feedback]"), n = JSON.parse(e.events), r = [
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
	}, s = new iT(iD, {
		plugins: [OT],
		locales: [
			PT,
			LT,
			RT,
			zT
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
			a = !0, iD.setAttribute("aria-busy", "true");
			try {
				await rD(n, e.timezone, e.token), t.textContent = e.saved, t.className = "pw-calendar-feedback alert alert-success";
			} catch (r) {
				n.revert(), t.textContent = r.message === "conflict" ? e.conflict : e.error, t.className = "pw-calendar-feedback alert alert-danger";
			} finally {
				a = !1, iD.removeAttribute("aria-busy"), t.hidden = !1;
			}
		}
	}), c = () => {
		let e = iD.clientWidth < 650;
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
