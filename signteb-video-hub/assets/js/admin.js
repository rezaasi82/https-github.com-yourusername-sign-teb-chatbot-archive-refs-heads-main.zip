/**
 * SignTeb Video Hub — admin behaviour.
 *
 * Every dashboard action is one REST call with inline feedback, so an admin
 * never has to reload to find out whether a sync or an API test worked.
 */
(function () {
  'use strict';

  var config = window.STVH_ADMIN || {};
  var i18n = config.i18n || {};

  function post(endpoint, body) {
    return fetch(config.restUrl + endpoint, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': config.nonce || ''
      },
      body: JSON.stringify(body || {})
    }).then(function (response) {
      return response.json().catch(function () {
        return { ok: false, message: i18n.failed };
      });
    });
  }

  /**
   * Feedback must appear where the user is already looking.
   *
   * Scoping to the whole page found the first status element in the DOM,
   * which on the settings screen sits next to the submit button — so a
   * "تست اتصال" result rendered far below the fold and looked like nothing
   * had happened. Prefer a status element that is a direct sibling of the
   * button, and create one right after it when there is none.
   */
  function feedbackFor(element) {
    var parent = element.parentNode;

    if (parent) {
      var sibling = parent.querySelector(':scope > [data-stvh-feedback]');
      if (sibling) {
        return sibling;
      }
    }

    var created = document.createElement('span');
    created.className = 'stvh-feedback';
    created.setAttribute('data-stvh-feedback', '');
    created.setAttribute('role', 'status');
    created.setAttribute('aria-live', 'polite');
    element.insertAdjacentElement('afterend', created);

    return created;
  }

  function report(element, message, ok) {
    var target = feedbackFor(element);
    if (!target) {
      return;
    }
    target.textContent = message || '';
    target.className = 'stvh-feedback' + (ok ? ' is-ok' : ' is-error');
  }

  function run(button, endpoint, body) {
    var label = button.textContent;
    button.disabled = true;
    button.textContent = i18n.working || label;
    report(button, i18n.working || '', true);

    post(endpoint, body)
      .then(function (data) {
        report(button, data.message || (data.ok ? '' : i18n.failed), !!data.ok);
        if (data.ok && data.edit) {
          window.open(data.edit, '_blank', 'noopener');
        }
      })
      .catch(function () {
        report(button, i18n.failed || '', false);
      })
      .finally(function () {
        button.disabled = false;
        button.textContent = label;
      });
  }

  function videoIdFor(element) {
    var box = element.closest('[data-video-id]');
    return box ? parseInt(box.dataset.videoId, 10) : 0;
  }

  document.addEventListener('click', function (event) {
    var target = event.target.closest('[data-stvh-action], [data-stvh-test], [data-stvh-generate], [data-stvh-publish-article], [data-stvh-index-ping], [data-stvh-repair]');
    if (!target) {
      return;
    }

    event.preventDefault();

    if (target.dataset.stvhAction) {
      var routes = {
        sync: 'sync',
        'ai-queue': 'ai/queue',
        'purge-cache': 'cache/purge',
        repair: 'repair',
        'index-ping': 'indexing/ping'
      };
      var route = routes[target.dataset.stvhAction];
      if (route) {
        run(target, route, {});
      }
      return;
    }

    if (target.dataset.stvhTest) {
      run(target, 'test-connection', { target: target.dataset.stvhTest });
      return;
    }

    if (target.dataset.stvhGenerate) {
      run(target, 'ai/generate', {
        video_id: videoIdFor(target),
        task: target.dataset.stvhGenerate
      });
      return;
    }

    if (target.hasAttribute('data-stvh-publish-article')) {
      run(target, 'ai/article', { video_id: videoIdFor(target) });
      return;
    }

    if (target.hasAttribute('data-stvh-index-ping')) {
      run(target, 'indexing/ping', { video_id: videoIdFor(target) });
      return;
    }

    if (target.hasAttribute('data-stvh-repair')) {
      run(target, 'repair', { video_id: videoIdFor(target) });
    }
  });

  /**
   * A masked secret field should clear on focus rather than making the admin
   * select-all-and-delete the placeholder dots.
   */
  document.addEventListener(
    'focus',
    function (event) {
      var field = event.target;
      if (!field.matches || !field.matches('input[type="password"], #google_service_json')) {
        return;
      }
      if (field.value.indexOf('•') === 0) {
        field.dataset.stvhMask = field.value;
        field.dataset.stvhTouched = '0';
        field.value = '';
      }
    },
    true
  );

  document.addEventListener(
    'input',
    function (event) {
      if (event.target.dataset && event.target.dataset.stvhMask) {
        event.target.dataset.stvhTouched = '1';
      }
    },
    true
  );

  document.addEventListener(
    'blur',
    function (event) {
      var field = event.target;
      if (!field.dataset || !field.dataset.stvhMask) {
        return;
      }
      // Restore the mask only when the field was never edited — otherwise a
      // stray click into the box would silently delete a stored secret. An
      // admin who typed and then cleared it really does mean "remove".
      if (field.value === '' && field.dataset.stvhTouched !== '1') {
        field.value = field.dataset.stvhMask;
      }
    },
    true
  );
})();
