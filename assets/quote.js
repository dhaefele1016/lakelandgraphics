/* Lakeland Graphics — Request a Quote form behaviour
   File-upload previews (drag + click), validation, real submit to send-quote.php. */
(function () {
  var form = document.getElementById('quoteForm');
  if (!form) return;

  var wrap = document.getElementById('qform');

  /* ---------- file dropzones ---------- */
  function human(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(0) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
  }

  function setupDrop(dropId, inputId, listId) {
    var drop = document.getElementById(dropId);
    var input = document.getElementById(inputId);
    var list = document.getElementById(listId);
    if (!drop || !input || !list) return;
    var store = [];

    function render() {
      list.innerHTML = '';
      store.forEach(function (file, i) {
        var t = document.createElement('div');
        t.className = 'thumb';
        if (file.type && file.type.indexOf('image/') === 0) {
          var img = document.createElement('img');
          img.src = URL.createObjectURL(file);
          img.onload = function () { URL.revokeObjectURL(img.src); };
          img.alt = file.name;
          t.appendChild(img);
        } else {
          var doc = document.createElement('div');
          doc.className = 'thumb__doc';
          var ext = (file.name.split('.').pop() || 'file').toUpperCase();
          doc.textContent = ext + ' · ' + human(file.size);
          t.appendChild(doc);
        }
        var x = document.createElement('button');
        x.type = 'button';
        x.className = 'thumb__x';
        x.setAttribute('aria-label', 'Remove ' + file.name);
        x.innerHTML = '&times;';
        x.addEventListener('click', function (e) {
          e.preventDefault();
          e.stopPropagation();
          store.splice(i, 1);
          render();
        });
        t.appendChild(x);
        list.appendChild(t);
      });
      drop.dataset.count = store.length;
    }

    function add(files) {
      for (var i = 0; i < files.length; i++) store.push(files[i]);
      render();
    }

    input.addEventListener('change', function () { if (input.files) add(input.files); input.value = ''; });
    ['dragenter', 'dragover'].forEach(function (ev) {
      drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.add('is-drag'); });
    });
    ['dragleave', 'drop'].forEach(function (ev) {
      drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.remove('is-drag'); });
    });
    drop.addEventListener('drop', function (e) {
      if (e.dataTransfer && e.dataTransfer.files) add(e.dataTransfer.files);
    });

    return {
      count: function () { return store.length; },
      files: function () { return store.slice(); },
      clear: function () { store.length = 0; render(); }
    };
  }

  var photoDrop = setupDrop('dropPhoto', 'filePhoto', 'previewPhoto');
  var artDrop = setupDrop('dropArt', 'fileArt', 'previewArt');

  // Timestamp used server-side as a simple bot check.
  var loadedAt = Date.now();

  /* ---------- validation ---------- */
  var required = ['name', 'email', 'print'];
  function validate() {
    var ok = true;
    required.forEach(function (id) {
      var el = document.getElementById('f_' + id);
      var field = el.closest('.field');
      var valid = el.value.trim() !== '';
      if (id === 'email') valid = valid && /.+@.+\..+/.test(el.value.trim());
      field.classList.toggle('field--invalid', !valid);
      if (!valid && ok) { el.focus(); }
      if (!valid) ok = false;
    });
    return ok;
  }

  // clear error as the user types
  form.addEventListener('input', function (e) {
    var field = e.target.closest && e.target.closest('.field--invalid');
    if (field) field.classList.remove('field--invalid');
  });

  var submitBtn = form.querySelector('button[type="submit"]');
  var errBox = document.getElementById('formError');

  function showError(msg) {
    if (!errBox) { window.alert(msg); return; }
    errBox.textContent = msg;
    errBox.hidden = false;
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (errBox) errBox.hidden = true;
    if (!validate()) return;

    var name = document.getElementById('f_name').value.trim();
    var print = document.getElementById('f_print');
    var printLabel = print.options[print.selectedIndex].text;

    var data = new FormData();
    data.append('name', name);
    data.append('company', document.getElementById('f_company').value.trim());
    data.append('email', document.getElementById('f_email').value.trim());
    data.append('phone', document.getElementById('f_phone').value.trim());
    data.append('print', printLabel);
    data.append('use', document.getElementById('f_use').value.trim());
    data.append('website', (document.getElementById('f_website') || {}).value || '');
    data.append('started', String(loadedAt));
    photoDrop.files().forEach(function (f) { data.append('photo[]', f, f.name); });
    artDrop.files().forEach(function (f) { data.append('art[]', f, f.name); });

    var label = submitBtn ? submitBtn.innerHTML : '';
    if (submitBtn) { submitBtn.disabled = true; submitBtn.innerHTML = 'Sending…'; }

    fetch('send-quote.php', { method: 'POST', body: data })
      .then(function (res) {
        return res.json().catch(function () { throw new Error('Unexpected server response.'); })
          .then(function (json) {
            if (!res.ok || !json.ok) throw new Error(json.error || 'Something went wrong.');
            return json;
          });
      })
      .then(function () {
        document.getElementById('recapName').textContent = name;
        document.getElementById('recapPrint').textContent = printLabel;
        wrap.classList.add('is-sent');
        window.scrollTo({ top: (wrap.getBoundingClientRect().top + window.scrollY - 120), behavior: 'smooth' });
      })
      .catch(function (err) {
        showError(err.message || 'We could not send your request. Please call 800.495.8107.');
      })
      .finally(function () {
        if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = label; }
      });
  });

  var resetBtn = document.getElementById('qReset');
  if (resetBtn) resetBtn.addEventListener('click', function () {
    form.reset();
    photoDrop.clear();
    artDrop.clear();
    wrap.classList.remove('is-sent');
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });
})();
