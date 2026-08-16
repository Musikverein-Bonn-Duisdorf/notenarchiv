/**
 * ARCHIV-48/49: Quick-create composer/arranger/publisher from composition form.
 * Prefer this file when deployed; composition.php also embeds a full inline fallback
 * (archiv-dev had a 404 for this path).
 */
(function(global) {
  'use strict';

  if(global.ArchivQuickCreate && global.ArchivQuickCreate.__archivFull) {
    return;
  }

  var personChipsTarget = 'piece-composers';

  function el(id) {
    return document.getElementById(id);
  }

  function armGhostClickGuard(ms) {
    var until = Date.now() + (ms || 500);
    global.ArchivQuickCreateIgnoreUntil = until;
    return until;
  }

  function ghostClickArmed() {
    return !!(global.ArchivQuickCreateIgnoreUntil && Date.now() < global.ArchivQuickCreateIgnoreUntil);
  }

  function showModal(id) {
    var modal = el(id);
    if(!modal) return false;
    // Escape .app-main stacking (nav/titlebar) — must be direct body child
    if(modal.parentNode !== document.body) {
      document.body.appendChild(modal);
    }
    armGhostClickGuard(500);
    modal.style.display = 'block';
    return true;
  }

  function hideModal(id) {
    var modal = el(id);
    if(modal) modal.style.display = 'none';
  }

  function setError(node, msg) {
    if(!node) return;
    node.textContent = msg || '';
    node.hidden = !msg;
  }

  function insertOption(select, id, label) {
    if(!select) return;
    var value = String(id);
    var existing = null;
    for(var i = 0; i < select.options.length; i++) {
      if(select.options[i].value === value) {
        existing = select.options[i];
        break;
      }
    }
    if(existing) {
      existing.selected = true;
      select.value = value;
      return;
    }
    var opt = document.createElement('option');
    opt.value = value;
    opt.textContent = label;
    var inserted = false;
    for(var j = 0; j < select.options.length; j++) {
      var o = select.options[j];
      if(o.value === 'null' || o.value === '') continue;
      if(label.localeCompare(o.textContent || '', 'de', {sensitivity: 'base'}) < 0) {
        select.insertBefore(opt, o);
        inserted = true;
        break;
      }
    }
    if(!inserted) select.appendChild(opt);
    opt.selected = true;
    select.value = value;
  }

  function postForm(url, data) {
    var body = new URLSearchParams();
    Object.keys(data).forEach(function(k) {
      body.append(k, data[k]);
    });
    return fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {'Accept': 'application/json'},
      body: body
    }).then(function(res) {
      return res.json().then(function(json) {
        return {ok: res.ok, status: res.status, json: json || {}};
      }).catch(function() {
        return {ok: false, status: res.status, json: {error: 'invalid_json'}};
      });
    });
  }

  function addToPersonChips(prefix, id, label) {
    var map = global.ArchivPersonChips || {};
    var inst = map[prefix];
    if(!inst) return false;
    if(typeof inst.ensureCatalogEntry === 'function') {
      inst.ensureCatalogEntry(id, label);
    }
    inst.add(id);
    return true;
  }

  function openPerson(btn) {
    if(btn && btn.getAttribute) {
      personChipsTarget = btn.getAttribute('data-person-chips') || 'piece-composers';
      var title = btn.getAttribute('data-kind') || 'Komponist';
      var titleEl = el('quickPersonTitle');
      if(titleEl) titleEl.textContent = title;
    }
    setError(el('quickPersonError'), '');
    var first = el('quickPersonFirst');
    var last = el('quickPersonLast');
    if(first) first.value = '';
    if(last) last.value = '';
    if(!showModal('quickPersonModal')) return false;
    if(last) {
      try { last.focus(); } catch(e) {}
    }
    return false;
  }

  function openPublisher() {
    setError(el('quickPublisherError'), '');
    var name = el('quickPublisherName');
    if(name) name.value = '';
    if(!showModal('quickPublisherModal')) return false;
    if(name) {
      try { name.focus(); } catch(e) {}
    }
    return false;
  }

  function close(id) {
    hideModal(id);
    return false;
  }

  /**
   * Backdrop close only. Open is wired on the + buttons (onclick + bind script).
   * Must honor ArchivQuickCreateIgnoreUntil — otherwise the same click that opens
   * the modal (or a touch ghost-click) immediately closes it via this listener.
   */
  function onDocClick(ev) {
    var target = ev.target;
    if(!target || !target.id) return;
    if(target.id !== 'quickPersonModal' && target.id !== 'quickPublisherModal') return;
    if(ghostClickArmed()) return;
    hideModal(target.id);
  }

  function onDocSubmit(ev) {
    var form = ev.target;
    if(!form || !form.id) return;
    if(form.id === 'quickPersonForm') {
      ev.preventDefault();
      setError(el('quickPersonError'), '');
      var first = el('quickPersonFirst');
      var last = el('quickPersonLast');
      var firstVal = first ? first.value.trim() : '';
      var lastVal = last ? last.value.trim() : '';
      if(!lastVal) {
        setError(el('quickPersonError'), 'Nachname fehlt.');
        return;
      }
      var saveBtn = form.querySelector('[type="submit"]');
      if(saveBtn) saveBtn.disabled = true;
      postForm('createComposerAjax.php', {firstName: firstVal, lastName: lastVal})
        .then(function(r) {
          if(!r.json.ok || !(r.json.id > 0)) {
            setError(el('quickPersonError'), 'Anlegen fehlgeschlagen.');
            return;
          }
          var label = r.json.label || (lastVal + ', ' + firstVal);
          ['piece-composers', 'piece-arrangers'].forEach(function(prefix) {
            var inst = (global.ArchivPersonChips || {})[prefix];
            if(inst && typeof inst.ensureCatalogEntry === 'function') {
              inst.ensureCatalogEntry(r.json.id, label);
            }
          });
          addToPersonChips(personChipsTarget, r.json.id, label);
          hideModal('quickPersonModal');
        })
        .catch(function() {
          setError(el('quickPersonError'), 'Anlegen fehlgeschlagen.');
        })
        .finally(function() {
          if(saveBtn) saveBtn.disabled = false;
        });
    }
    else if(form.id === 'quickPublisherForm') {
      ev.preventDefault();
      setError(el('quickPublisherError'), '');
      var nameEl = el('quickPublisherName');
      var nameVal = nameEl ? nameEl.value.trim() : '';
      if(!nameVal) {
        setError(el('quickPublisherError'), 'Name fehlt.');
        return;
      }
      var savePub = form.querySelector('[type="submit"]');
      if(savePub) savePub.disabled = true;
      postForm('createPublisherAjax.php', {name: nameVal})
        .then(function(r) {
          if(!r.json.ok || !(r.json.id > 0)) {
            setError(el('quickPublisherError'), 'Anlegen fehlgeschlagen.');
            return;
          }
          insertOption(el('editPublisher'), r.json.id, r.json.label || nameVal);
          hideModal('quickPublisherModal');
        })
        .catch(function() {
          setError(el('quickPublisherError'), 'Anlegen fehlgeschlagen.');
        })
        .finally(function() {
          if(savePub) savePub.disabled = false;
        });
    }
  }

  if(!global.__archivQuickCreateBound) {
    document.addEventListener('click', onDocClick);
    document.addEventListener('submit', onDocSubmit);
    global.__archivQuickCreateBound = true;
  }

  global.ArchivQuickCreate = {
    __archivFull: true,
    openPerson: openPerson,
    openPublisher: openPublisher,
    close: close,
    armGhostClickGuard: armGhostClickGuard
  };
})(window);
