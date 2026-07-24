/**
 * ARCHIV-24: Melde-style chip picker with order numbers for collection items.
 * Spec JSON: [{id: number, number: number}, ...]
 */
(function(global) {
  'use strict';

  function parseJson(el, fallback) {
    if(!el) return fallback;
    try {
      var v = JSON.parse(el.value || el.textContent || 'null');
      return v == null ? fallback : v;
    } catch(e) {
      return fallback;
    }
  }

  function normalizeItems(list) {
    var out = [];
    var seen = {};
    if(!Array.isArray(list)) return out;
    list.forEach(function(row) {
      if(!row) return;
      var id = Number(row.id);
      if(!(id > 0) || seen[id]) return;
      seen[id] = true;
      var num = Number(row.number);
      if(!isFinite(num)) num = 0;
      out.push({id: id, number: num});
    });
    return out;
  }

  function CollectionChips(opts) {
    this.chipsEl = opts.chipsEl;
    this.inputEl = opts.inputEl;
    this.suggestEl = opts.suggestEl;
    this.hiddenEl = opts.hiddenEl;
    this.catalog = Array.isArray(opts.catalog) ? opts.catalog : [];
    this.chipClass = opts.chipClass || 'mail-recipient-chip--composition';
    this.inputBg = opts.inputBg || '';
    this.items = normalizeItems(opts.initial);
    this._active = -1;
    this._bound = false;
  }

  CollectionChips.prototype.catalogById = function(id) {
    id = Number(id);
    for(var i = 0; i < this.catalog.length; i++) {
      if(Number(this.catalog[i].id) === id) return this.catalog[i];
    }
    return null;
  };

  CollectionChips.prototype.labelFor = function(id) {
    var row = this.catalogById(id);
    if(!row) return '#' + id;
    var label = row.label || ('#' + id);
    if(row.meta) label += ' (' + row.meta + ')';
    return label;
  };

  CollectionChips.prototype.nextNumber = function() {
    var max = 0;
    this.items.forEach(function(it) {
      if(Number(it.number) > max) max = Number(it.number);
    });
    return max + 1;
  };

  CollectionChips.prototype.hasId = function(id) {
    id = Number(id);
    for(var i = 0; i < this.items.length; i++) {
      if(Number(this.items[i].id) === id) return true;
    }
    return false;
  };

  CollectionChips.prototype.add = function(id) {
    id = Number(id);
    if(!(id > 0) || this.hasId(id)) return;
    this.items.push({id: id, number: this.nextNumber()});
    this.notify();
  };

  CollectionChips.prototype.remove = function(id) {
    id = Number(id);
    this.items = this.items.filter(function(it) { return Number(it.id) !== id; });
    this.notify();
  };

  CollectionChips.prototype.setNumber = function(id, number) {
    id = Number(id);
    var num = Number(number);
    if(!isFinite(num)) num = 0;
    for(var i = 0; i < this.items.length; i++) {
      if(Number(this.items[i].id) === id) {
        this.items[i].number = num;
        break;
      }
    }
    this.syncHidden();
  };

  CollectionChips.prototype.syncHidden = function() {
    if(!this.hiddenEl) return;
    this.hiddenEl.value = JSON.stringify(this.items);
  };

  CollectionChips.prototype.notify = function() {
    this.render();
    this.syncHidden();
  };

  CollectionChips.prototype.render = function() {
    if(!this.chipsEl) return;
    var self = this;
    this.chipsEl.innerHTML = '';
    this.items.forEach(function(it) {
      var row = document.createElement('div');
      row.className = 'collection-chip-row';
      row.setAttribute('data-id', String(it.id));

      var nrWrap = document.createElement('label');
      nrWrap.className = 'collection-chip-nr';
      var nrLab = document.createElement('span');
      nrLab.className = 'collection-chip-nr-label';
      nrLab.textContent = 'Nr';
      var nrInput = document.createElement('input');
      nrInput.type = 'number';
      nrInput.className = 'w3-input w3-border profile-control collection-chip-nr-input' +
        (self.inputBg ? (' ' + self.inputBg) : '');
      nrInput.value = String(it.number);
      nrInput.setAttribute('aria-label', 'Nr');
      nrInput.addEventListener('change', function() {
        self.setNumber(it.id, nrInput.value);
      });
      nrInput.addEventListener('input', function() {
        self.setNumber(it.id, nrInput.value);
      });
      nrWrap.appendChild(nrLab);
      nrWrap.appendChild(nrInput);

      var chip = document.createElement('span');
      chip.className = 'mail-recipient-chip ' + self.chipClass;
      chip.setAttribute('role', 'listitem');
      var text = document.createElement('span');
      text.textContent = self.labelFor(it.id);
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'mail-recipient-chip-remove';
      btn.setAttribute('aria-label', 'Entfernen');
      btn.textContent = '\u00d7';
      btn.addEventListener('click', function(e) {
        e.preventDefault();
        self.remove(it.id);
      });
      chip.appendChild(text);
      chip.appendChild(btn);

      row.appendChild(nrWrap);
      row.appendChild(chip);
      self.chipsEl.appendChild(row);
    });
  };

  CollectionChips.prototype.filterCatalog = function(q) {
    q = String(q || '').trim().toLowerCase();
    var self = this;
    var out = [];
    this.catalog.forEach(function(row) {
      var id = Number(row.id);
      if(!(id > 0) || self.hasId(id)) return;
      var hay = ((row.label || '') + ' ' + (row.meta || '') + ' ' + id).toLowerCase();
      if(q === '' || hay.indexOf(q) !== -1) out.push(row);
    });
    return out.slice(0, 20);
  };

  CollectionChips.prototype.renderSuggest = function(rows) {
    if(!this.suggestEl) return;
    var self = this;
    this.suggestEl.innerHTML = '';
    if(!rows.length) {
      this.suggestEl.hidden = true;
      this._active = -1;
      return;
    }
    this.suggestEl.hidden = false;
    rows.forEach(function(row, idx) {
      var item = document.createElement('button');
      item.type = 'button';
      item.className = 'mail-recipient-suggest-item' + (idx === self._active ? ' mail-recipient-suggest-item--active' : '');
      item.setAttribute('data-id', String(row.id));
      var label = document.createElement('span');
      label.textContent = row.label || ('#' + row.id);
      item.appendChild(label);
      if(row.meta) {
        var meta = document.createElement('span');
        meta.className = 'mail-recipient-suggest-meta';
        meta.textContent = row.meta;
        item.appendChild(meta);
      }
      item.addEventListener('click', function(e) {
        e.preventDefault();
        self.add(row.id);
        if(self.inputEl) {
          self.inputEl.value = '';
          self.inputEl.focus();
        }
        self.renderSuggest([]);
      });
      self.suggestEl.appendChild(item);
    });
  };

  CollectionChips.prototype.bind = function() {
    if(this._bound) return;
    this._bound = true;
    var self = this;
    if(this.inputEl) {
      this.inputEl.addEventListener('input', function() {
        self._active = 0;
        self.renderSuggest(self.filterCatalog(self.inputEl.value));
      });
      this.inputEl.addEventListener('keydown', function(e) {
        var items = self.suggestEl ? self.suggestEl.querySelectorAll('.mail-recipient-suggest-item') : [];
        if(e.key === 'ArrowDown' && items.length) {
          e.preventDefault();
          self._active = Math.min(self._active + 1, items.length - 1);
          self.renderSuggest(self.filterCatalog(self.inputEl.value));
        }
        else if(e.key === 'ArrowUp' && items.length) {
          e.preventDefault();
          self._active = Math.max(self._active - 1, 0);
          self.renderSuggest(self.filterCatalog(self.inputEl.value));
        }
        else if(e.key === 'Enter') {
          var active = items[self._active];
          if(active) {
            e.preventDefault();
            self.add(Number(active.getAttribute('data-id')));
            self.inputEl.value = '';
            self.renderSuggest([]);
          }
        }
        else if(e.key === 'Escape') {
          self.renderSuggest([]);
        }
        else if(e.key === 'Backspace' && self.inputEl.value === '' && self.items.length) {
          e.preventDefault();
          self.remove(self.items[self.items.length - 1].id);
        }
      });
      this.inputEl.addEventListener('blur', function() {
        setTimeout(function() { self.renderSuggest([]); }, 150);
      });
    }
  };

  CollectionChips.init = function(opts) {
    var inst = new CollectionChips(opts);
    inst.bind();
    // Always render; only rewrite the hidden field when initial was usable.
    // Avoid wiping a server-provided itemsSpec if catalog/initial parse failed.
    if(opts && opts.writeHiddenOnInit === false) {
      inst.render();
    } else {
      inst.notify();
    }
    return inst;
  };

  global.CollectionChips = CollectionChips;
})(window);
