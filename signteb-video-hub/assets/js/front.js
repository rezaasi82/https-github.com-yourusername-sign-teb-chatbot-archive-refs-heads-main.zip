/**
 * SignTeb Video Hub — front-end behaviour.
 *
 * Three jobs: Ajax topic filtering + live search, the click-to-load player
 * facade, and privacy-light analytics beacons. No dependencies.
 */
(function () {
  'use strict';

  var config = window.STVH || {};
  var restUrl = config.restUrl || '';
  var i18n = config.i18n || {};

  /* ------------------------------------------------------------------ *
   * Session id — random per browser session, never tied to an identity.
   * ------------------------------------------------------------------ */
  function sessionId() {
    try {
      var key = 'stvh_sid';
      var value = window.sessionStorage.getItem(key);
      if (!value) {
        value = Math.random().toString(36).slice(2) + Date.now().toString(36);
        window.sessionStorage.setItem(key, value);
      }
      return value;
    } catch (e) {
      return '';
    }
  }

  /* ------------------------------------------------------------------ *
   * Analytics
   * ------------------------------------------------------------------ */
  function track(videoId, event, seconds) {
    if (!config.analytics || !restUrl || !videoId) {
      return;
    }

    var payload = JSON.stringify({
      video_id: parseInt(videoId, 10),
      event: event,
      seconds: seconds || 0,
      session: sessionId()
    });

    // Heartbeats must survive the page being closed mid-watch.
    if (event === 'heartbeat' && navigator.sendBeacon) {
      navigator.sendBeacon(
        restUrl + 'track?_wpnonce=' + encodeURIComponent(config.nonce || ''),
        new Blob([payload], { type: 'application/json' })
      );
      return;
    }

    fetch(restUrl + 'track', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': config.nonce || ''
      },
      body: payload,
      keepalive: true
    }).catch(function () {
      /* Analytics must never break the page. */
    });
  }

  /**
   * Report a card as seen once it actually enters the viewport, so the CTR
   * denominator means "impressions" rather than "rendered in the DOM".
   */
  var observer = null;
  function observeImpressions(root) {
    if (!config.analytics || !('IntersectionObserver' in window)) {
      return;
    }

    if (!observer) {
      observer = new IntersectionObserver(
        function (entries) {
          entries.forEach(function (entry) {
            if (!entry.isIntersecting) {
              return;
            }
            var card = entry.target;
            observer.unobserve(card);
            if (card.dataset.stvhSeen === '1') {
              return;
            }
            card.dataset.stvhSeen = '1';
            track(card.dataset.videoId, 'impression');
          });
        },
        { threshold: 0.5 }
      );
    }

    root.querySelectorAll('[data-stvh-card]').forEach(function (card) {
      if (card.dataset.stvhSeen !== '1') {
        observer.observe(card);
      }
    });
  }

  /* ------------------------------------------------------------------ *
   * Broken posters
   *
   * Thumbnails come from a third-party CDN and can fail — hotlink rules, an
   * expired URL, a video pulled at the source. CSS cannot detect that, so a
   * failed image is flagged here and hidden, leaving the gradient placeholder
   * instead of the browser's broken-image glyph.
   * ------------------------------------------------------------------ */
  function guardImages(root) {
    root.querySelectorAll('.stvh-card__thumb, .stvh-player__facade img, .stvh-medhub__item img').forEach(function (img) {
      if (img.dataset.stvhGuarded === '1') {
        return;
      }
      img.dataset.stvhGuarded = '1';

      // A cached image may already have failed before this script ran.
      if (img.complete && img.naturalWidth === 0) {
        img.setAttribute('data-stvh-broken', '');
        return;
      }

      img.addEventListener('error', function () {
        img.setAttribute('data-stvh-broken', '');
      });
    });
  }

  /* ------------------------------------------------------------------ *
   * Hub: filters, live search, pagination
   * ------------------------------------------------------------------ */
  function initHub(hub) {
    var results = hub.querySelector('[data-stvh-results]');
    var status = hub.querySelector('[data-stvh-status]');
    var searchInput = hub.querySelector('[data-stvh-search]');
    var moreButton = hub.querySelector('[data-stvh-more]');
    var chips = hub.querySelectorAll('[data-stvh-filter]');

    if (!results) {
      return;
    }

    var state = {
      topic: hub.dataset.topic || '',
      search: '',
      page: 1,
      pages: parseInt(hub.dataset.pages || '1', 10),
      perPage: parseInt(hub.dataset.perPage || '12', 10),
      orderby: hub.dataset.orderby || 'date',
      style: hub.dataset.style || '',
      request: 0
    };

    function setStatus(message) {
      if (status) {
        status.textContent = message || '';
      }
    }

    function toggleMore() {
      if (!moreButton) {
        return;
      }
      moreButton.hidden = state.page >= state.pages;
      moreButton.disabled = false;
      moreButton.textContent = i18n.more || '';
    }

    function load(append) {
      var token = ++state.request;
      var params = new URLSearchParams({
        topic: state.topic,
        search: state.search,
        page: String(state.page),
        per_page: String(state.perPage),
        orderby: state.orderby,
        style: state.style
      });

      results.classList.add('is-loading');
      setStatus(i18n.loading || '');

      fetch(restUrl + 'videos?' + params.toString(), {
        headers: { 'X-WP-Nonce': config.nonce || '' }
      })
        .then(function (response) {
          if (!response.ok) {
            throw new Error('http_' + response.status);
          }
          return response.json();
        })
        .then(function (data) {
          // A slow earlier request must not overwrite a newer response.
          if (token !== state.request) {
            return;
          }

          state.pages = data.pages || 1;

          if (append) {
            results.insertAdjacentHTML('beforeend', data.html || '');
          } else {
            results.innerHTML = data.html || '';
          }

          setStatus(data.count === 0 && !append ? i18n.empty || '' : '');
          toggleMore();
          observeImpressions(results);
          bindCardClicks(results);
          guardImages(results);
        })
        .catch(function () {
          if (token === state.request) {
            setStatus(i18n.error || '');
          }
        })
        .finally(function () {
          if (token === state.request) {
            results.classList.remove('is-loading');
          }
        });
    }

    chips.forEach(function (chip) {
      chip.addEventListener('click', function () {
        chips.forEach(function (other) {
          other.classList.toggle('is-active', other === chip);
          other.setAttribute('aria-pressed', other === chip ? 'true' : 'false');
        });

        var topic = chip.dataset.stvhFilter || 'all';
        state.topic = topic === 'all' ? '' : topic;
        state.page = 1;
        load(false);
      });
    });

    if (searchInput) {
      var debounce = null;
      searchInput.addEventListener('input', function () {
        window.clearTimeout(debounce);
        debounce = window.setTimeout(function () {
          state.search = searchInput.value.trim();
          state.page = 1;
          load(false);
        }, 300);
      });

      // Enter must not submit a surrounding theme form.
      searchInput.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
          event.preventDefault();
        }
      });
    }

    if (moreButton) {
      moreButton.addEventListener('click', function () {
        if (state.page >= state.pages) {
          return;
        }
        state.page += 1;
        moreButton.disabled = true;
        moreButton.textContent = i18n.loading || '';
        load(true);
      });
    }

    observeImpressions(results);
    bindCardClicks(results);
    guardImages(results);
  }

  function bindCardClicks(root) {
    root.querySelectorAll('[data-stvh-click]').forEach(function (link) {
      if (link.dataset.stvhBound === '1') {
        return;
      }
      link.dataset.stvhBound = '1';
      link.addEventListener('click', function () {
        var card = link.closest('[data-stvh-card]');
        var videoId = (card && card.dataset.videoId) || link.dataset.videoId;
        track(videoId, 'click');
      });
    });
  }

  /* ------------------------------------------------------------------ *
   * Player facade — the provider iframe loads only on demand.
   * ------------------------------------------------------------------ */
  function initPlayer(player) {
    var button = player.querySelector('[data-stvh-play]');
    if (!button) {
      return;
    }

    button.addEventListener('click', function () {
      var embed = player.dataset.embed;
      if (!embed) {
        return;
      }

      var iframe = document.createElement('iframe');
      iframe.src = embed + (embed.indexOf('?') === -1 ? '?' : '&') + 'autoplay=1';
      iframe.title = player.dataset.title || '';
      iframe.allow = 'autoplay; fullscreen; picture-in-picture';
      iframe.setAttribute('allowfullscreen', '');
      iframe.setAttribute('loading', 'lazy');

      player.innerHTML = '';
      player.appendChild(iframe);

      var videoId = player.dataset.videoId;
      track(videoId, 'play');
      startHeartbeat(videoId);
    });
  }

  /**
   * Watch time is sampled rather than measured: cross-origin players expose
   * no playback API, so we count 15-second windows while the tab is visible
   * and stop as soon as it is hidden.
   */
  function startHeartbeat(videoId) {
    if (!config.analytics || !videoId) {
      return;
    }

    var interval = 15;
    var timer = window.setInterval(function () {
      if (document.visibilityState === 'visible') {
        track(videoId, 'heartbeat', interval);
      }
    }, interval * 1000);

    window.addEventListener('pagehide', function () {
      window.clearInterval(timer);
    });
  }

  /* ------------------------------------------------------------------ */
  function init() {
    guardImages(document);
    document.querySelectorAll('[data-stvh-hub]').forEach(initHub);
    document.querySelectorAll('[data-stvh-player]').forEach(initPlayer);
    document.querySelectorAll('[data-stvh-click]').forEach(function (link) {
      if (!link.closest('[data-stvh-hub]')) {
        bindCardClicks(link.parentNode || document);
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
