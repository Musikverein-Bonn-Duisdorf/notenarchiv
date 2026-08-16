/**
 * ARCHIV-48: Quick-create composer/arranger/publisher from composition form.
 */
(function() {
  'use strict';

  function showModal(id) {
    var el = document.getElementById(id);
    if(el) el.style.display = 'block';
  }

  function hideModal(id) {
    var el = document.getElementById(id);
    if(el) el.style.display = 'none';
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

  function setError(el, msg) {
    if(!el) return;
    el.textContent = msg || '';
    el.hidden = !msg;
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
    var map = window.ArchivPersonChips || {};
    var inst = map[prefix];
    if(!inst) return false;
    if(typeof inst.ensureCatalogEntry === 'function') {
      inst.ensureCatalogEntry(id, label);
    }
    inst.add(id);
    return true;
  }

  var personChipsTarget = 'piece-composers';
  var personTitle = null;
  var personError = null;
  var personFirst = null;
  var personLast = null;
  var pubError = null;
  var pubName = null;

  function cacheEls() {
    personTitle = document.getElementById('quickPersonTitle');
    personError = document.getElementById('quickPersonError');
    personFirst = document.getElementById('quickPersonFirst');
    personLast = document.getElementById('quickPersonLast');
    pubError = document.getElementById('quickPublisherError');
    pubName = document.getElementById('quickPublisherName');
  }

  document.addEventListener('click', function(ev) {
    var btn = ev.target.closest('[data-quick-create]');
    if(!btn) {
      var closeBtn = ev.target.closest('[data-quick-close]');
      if(closeBtn) {
        hideModal(closeBtn.getAttribute('data-quick-close'));
      }
      return;
    }
    cacheEls();
    var kind = btn.getAttribute('data-quick-create');
    if(kind === 'person') {
      personChipsTarget = btn.getAttribute('data-person-chips') || 'piece-composers';
      var title = btn.getAttribute('data-kind') || 'Komponist';
      if(personTitle) personTitle.textContent = title;
      setError(personError, '');
      if(personFirst) personFirst.value = '';
      if(personLast) personLast.value = '';
      showModal('quickPersonModal');
      if(personLast) personLast.focus();
    }
    else if(kind === 'publisher') {
      setError(pubError, '');
      if(pubName) pubName.value = '';
      showModal('quickPublisherModal');
      if(pubName) pubName.focus();
    }
  });

  ['quickPersonModal', 'quickPublisherModal'].forEach(function(id) {
    document.addEventListener('click', function(ev) {
      if(ev.target && ev.target.id === id) hideModal(id);
    });
  });

  document.addEventListener('submit', function(ev) {
    var form = ev.target;
    if(!form || !form.id) return;
    cacheEls();
    if(form.id === 'quickPersonForm') {
      ev.preventDefault();
      setError(personError, '');
      var first = personFirst ? personFirst.value.trim() : '';
      var last = personLast ? personLast.value.trim() : '';
      if(!last) {
        setError(personError, 'Nachname fehlt.');
        return;
      }
      var saveBtn = form.querySelector('[type="submit"]');
      if(saveBtn) saveBtn.disabled = true;
      postForm('createComposerAjax.php', {firstName: first, lastName: last})
        .then(function(r) {
          if(!r.json.ok || !(r.json.id > 0)) {
            setError(personError, 'Anlegen fehlgeschlagen.');
            return;
          }
          var label = r.json.label || (last + ', ' + first);
          ['piece-composers', 'piece-arrangers'].forEach(function(prefix) {
            var inst = (window.ArchivPersonChips || {})[prefix];
            if(inst && typeof inst.ensureCatalogEntry === 'function') {
              inst.ensureCatalogEntry(r.json.id, label);
            }
          });
          addToPersonChips(personChipsTarget, r.json.id, label);
          hideModal('quickPersonModal');
        })
        .catch(function() {
          setError(personError, 'Anlegen fehlgeschlagen.');
        })
        .finally(function() {
          if(saveBtn) saveBtn.disabled = false;
        });
    }
    else if(form.id === 'quickPublisherForm') {
      ev.preventDefault();
      setError(pubError, '');
      var name = pubName ? pubName.value.trim() : '';
      if(!name) {
        setError(pubError, 'Name fehlt.');
        return;
      }
      var savePub = form.querySelector('[type="submit"]');
      if(savePub) savePub.disabled = true;
      postForm('createPublisherAjax.php', {name: name})
        .then(function(r) {
          if(!r.json.ok || !(r.json.id > 0)) {
            setError(pubError, 'Anlegen fehlgeschlagen.');
            return;
          }
          insertOption(document.getElementById('editPublisher'), r.json.id, r.json.label || name);
          hideModal('quickPublisherModal');
        })
        .catch(function() {
          setError(pubError, 'Anlegen fehlgeschlagen.');
        })
        .finally(function() {
          if(savePub) savePub.disabled = false;
        });
    }
  });
})();
