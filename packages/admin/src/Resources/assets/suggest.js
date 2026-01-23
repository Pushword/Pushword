/*
--------------------------------------------------------
suggest.js - Input Suggest
Version 2.3.1 (Update 2013/02/11)

Copyright (c) 2006-2013 onozaty (http://www.enjoyxstudy.com)

Released under an MIT-style license.

For details, see the web site:
 http://www.enjoyxstudy.com/javascript/suggest/

--------------------------------------------------------
*/

var Suggest = {}
Suggest.LocalMulti = function () {}
/*-- KeyCodes -----------------------------------------*/
Suggest.Key = {
  TAB: 9,
  RETURN: 13,
  ESC: 27,
  UP: 38,
  DOWN: 40,
}

/*-- Utils --------------------------------------------*/
Suggest.copyProperties = function (dest, src) {
  for (var property in src) {
    dest[property] = src[property]
  }
  return dest
}

/*-- Suggest.Local ------------------------------------*/
Suggest.Local = function () {
  this.initialize.apply(this, arguments)
}
Suggest.Local.prototype = {
  initialize: function (input, suggestArea, candidateList) {
    this.input = this._getElement(input)
    this.suggestArea = this._getElement(suggestArea)
    this.candidateList = candidateList
    this.oldText = this.getInputText()

    if (arguments[3]) this.setOptions(arguments[3])

    // reg event
    this._addEvent(this.input, 'focus', this._bind(this.checkLoop))
    this._addEvent(this.input, 'blur', this._bind(this.inputBlur))
    this._addEvent(this.suggestArea, 'blur', this._bind(this.inputBlur))

    this._addEvent(this.input, 'keydown', this._bindEvent(this.keyEvent))

    // init
    this.clearSuggestArea()
  },

  // options
  interval: 500,
  dispMax: 20,
  listTagName: 'div',
  prefix: false,
  ignoreCase: true,
  highlight: false,
  dispAllKey: false,
  classMouseOver: 'over',
  classSelect: 'select',
  delim: ' ',
  hookBeforeSearch: null, //function (Suggest, text) {},
  hookSearchResults: null, //function (Suggest, inputVale, currentSearch, searchResults, t) { return searchResults },

  setOptions: function (options) {
    Suggest.copyProperties(this, options)
  },

  inputBlur: function () {
    setTimeout(
      this._bind(function () {
        if (document.activeElement == this.suggestArea || document.activeElement == this.input) {
          // keep suggestion
          return
        }

        this.changeUnactive()
        this.oldText = this.getInputText()

        if (this.timerId) clearTimeout(this.timerId)
        this.timerId = null

        setTimeout(this._bind(this.clearSuggestArea), 500)
      }, 500),
    )
  },

  checkLoop: function () {
    var text = this.getInputText()
    if (text != this.oldText) {
      this.oldText = text
      this.search()
    }
    if (this.timerId) clearTimeout(this.timerId)
    this.timerId = setTimeout(this._bind(this.checkLoop), this.interval)
  },

  search: function () {
    // init
    this.clearSuggestArea()

    var text = this.getInputText()

    if (text == '' || text == null) return

    if (this.hookBeforeSearch) this.hookBeforeSearch(Suggest, text)
    var resultList = this._search(text)
    if (this.hookSearchResults) resultList = window[this.hookSearchResults](this, this.getInputValue(), text, resultList)
    if (resultList.length != 0) this.createSuggestArea(resultList)
  },

  _search: function (text) {
    var resultList = []
    var temp
    this.suggestIndexList = []

    for (var i = 0, length = this.candidateList.length; i < length; i++) {
      if ((temp = this.isMatch(this.candidateList[i], text)) != null) {
        if (this.getInputValue().includes(temp.replace(/(<([^>]+)>)/gi, ''))) continue
        resultList.push(temp)
        this.suggestIndexList.push(i)

        if (this.dispMax != 0 && resultList.length >= this.dispMax) break
      }
    }
    return resultList
  },

  isMatch: function (value, pattern) {
    if (value == null) return null

    var pos = this.ignoreCase ? value.toLowerCase().indexOf(pattern.toLowerCase()) : value.indexOf(pattern)

    if (pos == -1 || (this.prefix && pos != 0)) return null

    if (this.highlight) {
      return (
        this._escapeHTML(value.substr(0, pos)) +
        '<strong>' +
        this._escapeHTML(value.substr(pos, pattern.length)) +
        '</strong>' +
        this._escapeHTML(value.substr(pos + pattern.length))
      )
    } else {
      return this._escapeHTML(value)
    }
  },

  clearSuggestArea: function () {
    this.suggestArea.innerHTML = ''
    this.suggestArea.style.display = 'none'
    this.suggestList = null
    this.suggestIndexList = null
    this.activePosition = null
  },

  createSuggestArea: function (resultList) {
    this.suggestList = []
    this.inputValueBackup = this.getInputValue()

    for (var i = 0, length = resultList.length; i < length; i++) {
      var element = document.createElement(this.listTagName)
      element.innerHTML = resultList[i]
      this.suggestArea.appendChild(element)

      this._addEvent(element, 'click', this._bindEvent(this.listClick, i))
      this._addEvent(element, 'mouseover', this._bindEvent(this.listMouseOver, i))
      this._addEvent(element, 'mouseout', this._bindEvent(this.listMouseOut, i))

      this.suggestList.push(element)
    }

    this.suggestArea.style.display = ''
    this.suggestArea.scrollTop = 0
  },

  getInputValue: function () {
    return this.input instanceof HTMLInputElement ? this.input.value : this.input.innerText
  },
  getInputText: function () {
    return this.getInputValue()
  },

  /** @param {string} text */
  setInputValue: function (text) {
    if (this.input instanceof HTMLInputElement) this.input.value = text
    else this.input.innerText = text
  },

  setInputText: function (text) {
    this.setInputValue(text)
  },

  // key event
  keyEvent: function (event) {
    if (!this.timerId) {
      this.timerId = setTimeout(this._bind(this.checkLoop), this.interval)
    }

    if (this.dispAllKey && event.ctrlKey && this.getInputText() == '' && !this.suggestList && event.keyCode == Suggest.Key.DOWN) {
      // dispAll
      this._stopEvent(event)
      this.keyEventDispAll()
    } else if (event.keyCode == Suggest.Key.UP || event.keyCode == Suggest.Key.DOWN) {
      // key move
      if (this.suggestList && this.suggestList.length != 0) {
        this._stopEvent(event)
        this.keyEventMove(event.keyCode)
      }
    } else if (event.keyCode == Suggest.Key.RETURN) {
      // fix
      if (this.suggestList && this.suggestList.length != 0) {
        this._stopEvent(event)
        this.keyEventReturn()
      }
    } else if (event.keyCode == Suggest.Key.ESC) {
      // cancel
      if (this.suggestList && this.suggestList.length != 0) {
        this._stopEvent(event)
        this.keyEventEsc()
      }
    } else {
      this.keyEventOther(event)
    }
  },

  keyEventDispAll: function () {
    // init
    this.clearSuggestArea()

    this.oldText = this.getInputText()

    this.suggestIndexList = []
    for (var i = 0, length = this.candidateList.length; i < length; i++) {
      this.suggestIndexList.push(i)
    }

    this.createSuggestArea(this.candidateList)
  },

  keyEventMove: function (keyCode) {
    this.changeUnactive()

    if (keyCode == Suggest.Key.UP) {
      // up
      if (this.activePosition == null) {
        this.activePosition = this.suggestList.length - 1
      } else {
        this.activePosition--
        if (this.activePosition < 0) {
          this.activePosition = null
          this.setInputValue(this.inputValueBackup)
          this.suggestArea.scrollTop = 0
          return
        }
      }
    } else {
      // down
      if (this.activePosition == null) {
        this.activePosition = 0
      } else {
        this.activePosition++
      }

      if (this.activePosition >= this.suggestList.length) {
        this.activePosition = null
        this.setInputValue(this.inputValueBackup)
        this.suggestArea.scrollTop = 0
        return
      }
    }

    this.changeActive(this.activePosition)
  },

  keyEventReturn: function () {
    this.clearSuggestArea()
    this.moveEnd()
  },

  keyEventEsc: function () {
    this.clearSuggestArea()
    this.setInputValue(this.inputValueBackup)
    this.oldText = this.getInputText()

    if (window.opera) setTimeout(this._bind(this.moveEnd), 5)
  },

  keyEventOther: function (event) {},

  changeActive: function (index) {
    this.setStyleActive(this.suggestList[index])

    this.setInputText(this.candidateList[this.suggestIndexList[index]])

    this.oldText = this.getInputText()
    this.input.focus()
  },

  changeUnactive: function () {
    if (this.suggestList != null && this.suggestList.length > 0 && this.activePosition != null) {
      this.setStyleUnactive(this.suggestList[this.activePosition])
    }
  },

  listClick: function (event, index) {
    this.changeUnactive()
    this.activePosition = index
    this.changeActive(index)

    this.clearSuggestArea()
    this.moveEnd()
  },

  listMouseOver: function (event, index) {
    this.setStyleMouseOver(this._getEventElement(event))
  },

  listMouseOut: function (event, index) {
    if (!this.suggestList) return

    var element = this._getEventElement(event)

    if (index == this.activePosition) {
      this.setStyleActive(element)
    } else {
      this.setStyleUnactive(element)
    }
  },

  setStyleActive: function (element) {
    element.className = this.classSelect

    // auto scroll
    var offset = element.offsetTop
    var offsetWithHeight = offset + element.clientHeight

    if (this.suggestArea.scrollTop > offset) {
      this.suggestArea.scrollTop = offset
    } else if (this.suggestArea.scrollTop + this.suggestArea.clientHeight < offsetWithHeight) {
      this.suggestArea.scrollTop = offsetWithHeight - this.suggestArea.clientHeight
    }
  },

  setStyleUnactive: function (element) {
    element.className = ''
  },

  setStyleMouseOver: function (element) {
    element.className = this.classMouseOver
  },

  moveEnd: function () {
    if (this.input.createTextRange) {
      this.input.focus() // Opera
      var range = this.input.createTextRange()
      range.move('character', this.getInputValue().length)
      range.select()
    } else if (this.input.setSelectionRange) {
      this.input.setSelectionRange(this.getInputValue().length, this.getInputValue().length)
    } else {
      this.input.focus()
      var range = document.createRange()
      range.selectNodeContents(this.input)
      range.collapse(false)
      var selection = window.getSelection()
      selection.removeAllRanges()
      selection.addRange(range)
    }
  },

  // Utils
  _getElement: function (element) {
    return typeof element == 'string' ? document.getElementById(element) : element
  },
  _addEvent: window.addEventListener
    ? function (element, type, func) {
        element.addEventListener(type, func, false)
      }
    : function (element, type, func) {
        element.attachEvent('on' + type, func)
      },
  _stopEvent: function (event) {
    if (event.preventDefault) {
      event.preventDefault()
      event.stopPropagation()
    } else {
      event.returnValue = false
      event.cancelBubble = true
    }
  },
  _getEventElement: function (event) {
    return event.target || event.srcElement
  },
  _bind: function (func) {
    var self = this
    var args = Array.prototype.slice.call(arguments, 1)
    return function () {
      func.apply(self, args)
    }
  },
  _bindEvent: function (func) {
    var self = this
    var args = Array.prototype.slice.call(arguments, 1)
    return function (event) {
      event = event || window.event
      func.apply(self, [event].concat(args))
    }
  },
  _escapeHTML: function (value) {
    return value.replace(/\&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\"/g, '&quot;').replace(/\'/g, '&#39;')
  },
}

/*-- Suggest.LocalMulti ---------------------------------*/
Suggest.LocalMulti = function () {
  this.initialize.apply(this, arguments)
}
Suggest.copyProperties(Suggest.LocalMulti.prototype, Suggest.Local.prototype)

//Suggest.LocalMulti.prototype.delim = this.delim // delimiter

Suggest.LocalMulti.prototype.keyEventReturn = function () {
  this.clearSuggestArea()
  this.setInputValue(this.getInputValue() + this.delim)
  this.moveEnd()
}

Suggest.LocalMulti.prototype.keyEventOther = function (event) {
  if (event.keyCode == Suggest.Key.TAB) {
    // fix
    if (this.suggestList && this.suggestList.length != 0) {
      this._stopEvent(event)

      if (!this.activePosition) {
        this.activePosition = 0
        this.changeActive(this.activePosition)
      }

      this.clearSuggestArea()
      this.setInputValue(this.getInputValue() + this.delim)
      if (window.opera) {
        setTimeout(this._bind(this.moveEnd), 5)
      } else {
        this.moveEnd()
      }
    }
  }
}

Suggest.LocalMulti.prototype.listClick = function (event, index) {
  this.changeUnactive()
  this.activePosition = index
  this.changeActive(index)

  this.setInputValue(this.getInputValue() + this.delim)

  this.clearSuggestArea()
  this.moveEnd()
}

Suggest.LocalMulti.prototype.getInputText = function () {
  var pos = this.getLastTokenPos()

  if (pos == -1) {
    return this.getInputValue()
  } else {
    return this.getInputValue().substr(pos + this.delim.length)
  }
}

Suggest.LocalMulti.prototype.setInputText = function (text) {
  var pos = this.getLastTokenPos()

  if (pos == -1) {
    this.setInputValue(text)
  } else {
    this.setInputValue(this.getInputValue().substr(0, pos + this.delim.length) + text)
  }
}

Suggest.LocalMulti.prototype.getLastTokenPos = function () {
  return this.getInputValue().lastIndexOf(this.delim)
}

export { Suggest }
