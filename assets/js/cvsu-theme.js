/* ==========================================================================
   CVSU NAIC - ODCI shared UI behaviour
   --------------------------------------------------------------------------
   Responsibilities:
     * marks the current page active in the sidebar menu
     * drives the mobile drawer's backdrop / scroll-lock, MIRRORING the
       sidebar's `.expanded` class rather than owning the click
     * responsive tables (scroll container + card transformation)

   IMPORTANT - the sidebar and navbar are NOT restyled by the theme, and this
   file deliberately does not re-implement their toggle either.

   The app's own scripts already own that behaviour:
     roles/{admin,superadmin}/assets/js/script.js
     roles/user/assets/js/components/navbar.js
   both bind the hamburger and call `sidebar.classList.toggle('expanded')`,
   and the original CSS keys every sidebar width off `.expanded`. Binding a
   second handler here would toggle the class twice per click and leave the
   sidebar stuck, so this file only OBSERVES the class.

   Everything is guarded, so including it on a page that has no sidebar is
   harmless.
   ========================================================================== */
(function () {
    'use strict';

    var MOBILE_QUERY = '(max-width: 1024px)';

    function $(sel, ctx) { return (ctx || document).querySelector(sel); }
    function $$(sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }

    function isMobile() { return window.matchMedia(MOBILE_QUERY).matches; }

    /* ---------- focused sidebar labels ---------- */
    function initSidebarTooltips() {
        var sidebar = $('#sidebar');
        if (!sidebar) { return; }

        function hideTooltip(item) {
            var tooltip = $('.tooltip', item);
            var link = $('a', item);
            if (tooltip) { tooltip.classList.remove('show'); }
            if (link) { link.removeAttribute('aria-describedby'); }
        }

        function showTooltip(item) {
            var link = $('a', item);
            var tooltip = $('.tooltip', item);
            if (!link || !tooltip || isMobile() || sidebar.classList.contains('expanded')) {
                hideTooltip(item);
                return;
            }

            var label = $('.text', link);
            var name = (label ? label.textContent : link.textContent).trim();
            if (!name) { return; }

            if (!tooltip.id) {
                tooltip.id = 'cvsu-sidebar-tooltip-' + Math.random().toString(36).slice(2);
            }
            tooltip.textContent = name;
            tooltip.classList.add('show');
            link.setAttribute('aria-describedby', tooltip.id);

            var rect = item.getBoundingClientRect();
            var tooltipRect = tooltip.getBoundingClientRect();
            var left = Math.min(rect.right + 10, window.innerWidth - tooltipRect.width - 10);
            var top = Math.max(10, Math.min(
                rect.top + (rect.height - tooltipRect.height) / 2,
                window.innerHeight - tooltipRect.height - 10
            ));
            tooltip.style.left = Math.max(10, left) + 'px';
            tooltip.style.top = top + 'px';
        }

        $$('#sidebar .side-menu li').forEach(function (item) {
            item.addEventListener('mouseenter', function () { showTooltip(item); });
            item.addEventListener('mouseleave', function () {
                if (!item.contains(document.activeElement)) { hideTooltip(item); }
            });
            item.addEventListener('focusin', function () { showTooltip(item); });
            item.addEventListener('focusout', function () {
                window.setTimeout(function () {
                    if (!item.contains(document.activeElement)) { hideTooltip(item); }
                }, 0);
            });
        });

        window.addEventListener('resize', function () {
            $$('#sidebar .side-menu li .tooltip.show').forEach(function (tooltip) {
                var item = tooltip.closest('li');
                if (item) { showTooltip(item); }
            });
        });
    }

    /* ---------- accessible notifications ---------- */
    function initNotifications() {
        var dropdown = $('#notificationDropdown');
        var list = $('#notificationList');
        var container = $('.notification-container');
        var toggle = $('.notification-btn', container || document);
        if (!dropdown || !list || !toggle || !dropdown.dataset.endpoint) { return; }

        var endpoint = dropdown.dataset.endpoint;
        var feedUrl = dropdown.dataset.feedUrl || '#';
        var csrfToken = list.dataset.csrfToken || '';
        var unreadCount = 0;
        var loading = false;

        function setBadge(count) {
            unreadCount = Math.max(0, Number(count) || 0);
            var badge = $('.notification-badge', toggle);
            if (!badge) { return; }
            badge.textContent = unreadCount > 99 ? '99+' : String(unreadCount);
            badge.hidden = unreadCount === 0;
            toggle.setAttribute('aria-label', unreadCount ? 'Notifications, ' + unreadCount + ' unread' : 'Notifications');
        }

        function setLoading() {
            list.innerHTML = '<div class="cvsu-notification-loading" aria-label="Loading notifications"><span></span><span></span><span></span></div>';
        }

        function showMessage(text, isError) {
            list.replaceChildren();
            var panel = document.createElement('div');
            panel.className = isError ? 'cvsu-notification-error' : 'cvsu-notification-empty';
            panel.textContent = text;
            if (isError) {
                var retry = document.createElement('button');
                retry.type = 'button';
                retry.className = 'cvsu-notification-retry';
                retry.textContent = 'Try again';
                retry.addEventListener('click', function () { loadNotifications(false); });
                panel.appendChild(document.createElement('br'));
                panel.appendChild(retry);
            }
            list.appendChild(panel);
        }

        function formatTime(value) {
            var date = new Date(String(value || '').replace(' ', 'T'));
            if (Number.isNaN(date.getTime())) { return 'Recently'; }
            return new Intl.DateTimeFormat(undefined, {
                dateStyle: 'medium',
                timeStyle: 'short'
            }).format(date);
        }

        function renderItems(items) {
            list.replaceChildren();
            if (!items.length) {
                showMessage('You’re all caught up. No new notifications.', false);
                return;
            }

            items.forEach(function (item) {
                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'notification-item cvsu-notification' + (Number(item.is_read) ? '' : ' unread');
                button.setAttribute('aria-label', (item.title || 'Notification') + ': ' + (item.message || ''));

                var icon = document.createElement('i');
                icon.className = 'bx ' + (item.source === 'social' ? 'bx-message-rounded-dots' : 'bx-bell');
                icon.setAttribute('aria-hidden', 'true');

                var content = document.createElement('span');
                content.className = 'notification-content';
                var title = document.createElement('strong');
                title.textContent = item.title || 'Notification';
                var message = document.createElement('p');
                message.textContent = item.message || '';
                var time = document.createElement('span');
                time.className = 'notification-time';
                time.textContent = formatTime(item.created_at);
                content.appendChild(title);
                content.appendChild(message);
                content.appendChild(time);

                button.appendChild(icon);
                button.appendChild(content);
                button.addEventListener('click', function () {
                    updateNotification('mark_read', item).then(function () {
                        if (item.source === 'social') { window.location.assign(feedUrl); }
                    });
                });
                list.appendChild(button);
            });
        }

        function requestJson(url, options) {
            return fetch(url, options).then(function (response) {
                return response.json().then(function (payload) {
                    if (!response.ok || !payload.success) {
                        throw new Error(payload.message || 'Notifications could not be loaded.');
                    }
                    return payload;
                });
            });
        }

        function loadNotifications(silent) {
            if (loading) { return Promise.resolve(); }
            loading = true;
            if (!silent) { setLoading(); }
            return requestJson(endpoint, { credentials: 'same-origin' })
                .then(function (payload) {
                    setBadge(payload.unreadCount);
                    renderItems(Array.isArray(payload.items) ? payload.items : []);
                })
                .catch(function (error) {
                    showMessage(error.message || 'Notifications could not be loaded.', true);
                })
                .finally(function () { loading = false; });
        }

        function updateNotification(action, item) {
            var body = new URLSearchParams();
            body.set('action', action);
            body.set('csrf_token', csrfToken);
            if (item) {
                body.set('id', String(item.id));
                body.set('source', item.source);
            }
            return requestJson(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
                body: body.toString()
            }).then(function () {
                return loadNotifications(false);
            }).catch(function (error) {
                showMessage(error.message || 'Your notification could not be updated.', true);
                return false;
            });
        }

        toggle.addEventListener('click', function () {
            var open = dropdown.classList.toggle('show');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open) { loadNotifications(false); }
        });

        var markAllButton = $('.mark-all-read', dropdown);
        if (markAllButton) {
            markAllButton.addEventListener('click', function () {
                if (unreadCount) { updateNotification('mark_all_read'); }
            });
        }

        document.addEventListener('click', function (event) {
            if (!container.contains(event.target)) {
                dropdown.classList.remove('show');
                toggle.setAttribute('aria-expanded', 'false');
            }
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && dropdown.classList.contains('show')) {
                dropdown.classList.remove('show');
                toggle.setAttribute('aria-expanded', 'false');
                toggle.focus();
            }
        });

        loadNotifications(true);
        window.setInterval(function () {
            if (!document.hidden) { loadNotifications(dropdown.classList.contains('show')); }
        }, 60000);
    }

    /* ---------- non-blocking page progress ---------- */
    function initLoadingProgress() {
        var indicator = document.createElement('div');
        indicator.className = 'cvsu-loading-progress';
        indicator.setAttribute('role', 'progressbar');
        indicator.setAttribute('aria-label', 'Page loading');
        indicator.setAttribute('aria-valuetext', 'Loading');
        document.body.appendChild(indicator);

        var hideTimer;
        function show() {
            window.clearTimeout(hideTimer);
            indicator.classList.add('is-visible');
        }
        function hide() {
            indicator.classList.remove('is-visible');
        }

        if (document.readyState !== 'complete') {
            show();
            window.addEventListener('load', function () {
                hideTimer = window.setTimeout(hide, 220);
            }, { once: true });
        }

        document.addEventListener('click', function (event) {
            var link = event.target.closest && event.target.closest('a[href]');
            if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) { return; }
            if (link.target === '_blank' || link.hasAttribute('download')) { return; }
            var destination;
            try { destination = new URL(link.href, window.location.href); } catch (error) { return; }
            if (destination.origin !== window.location.origin || destination.href === window.location.href || destination.hash && destination.pathname === window.location.pathname) { return; }
            show();
            hideTimer = window.setTimeout(hide, 1800);
        });
        document.addEventListener('submit', function (event) {
            if (event.target && event.target.checkValidity && event.target.checkValidity()) {
                show();
                hideTimer = window.setTimeout(hide, 1800);
            }
        }, true);
    }

    /* ---------- drawer backdrop ----------
       The sidebar slides itself in and out via `.expanded` (see the note
       above). All this adds is the scrim behind it, plus the two ways a user
       expects to dismiss a drawer: tap the scrim, or press Escape. */
    var backdrop = null;

    function getBackdrop() {
        if (!backdrop) {
            backdrop = document.createElement('div');
            backdrop.className = 'cvsu-backdrop';
            backdrop.addEventListener('click', closeDrawer);
            document.body.appendChild(backdrop);
        }
        return backdrop;
    }

    function closeDrawer() {
        var sidebar = $('#sidebar');
        if (sidebar) { sidebar.classList.remove('expanded'); }
        syncDrawer();
    }

    /* Reflect the sidebar's real state onto the backdrop and the scroll lock. */
    function syncDrawer() {
        var sidebar = $('#sidebar');
        if (!sidebar) { return; }

        var expanded = sidebar.classList.contains('expanded');
        $$('#content .toggle-sidebar').forEach(function (button) {
            button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            button.setAttribute(
                'aria-label',
                isMobile()
                    ? (expanded ? 'Close navigation' : 'Open navigation')
                    : (expanded ? 'Collapse navigation' : 'Expand navigation')
            );
        });

        if (!backdrop) { return; }

        var open = isMobile() && expanded;
        document.body.classList.toggle('cvsu-drawer-open', open);
        backdrop.classList.toggle('show', open);
        document.body.style.overflow = open ? 'hidden' : '';
    }

    /* ---------- active menu item ---------- */
    function markActive() {
        var sidebar = $('#sidebar');
        if (!sidebar) { return; }

        var here = (window.location.pathname.split('/').pop() || '').toLowerCase();
        var links = $$('#sidebar .side-menu a[href]');

        links.forEach(function (link) {
            var target = (link.getAttribute('href') || '').split('/').pop().split('?')[0].toLowerCase();
            if (!target || target.indexOf('logout') === 0) { return; }

            var item = link.parentElement;
            var isActive = (target === here);

            // The folder pages highlight "Department Folders"/"All Folders".
            if (!isActive && here === 'folders.php' && /folder/.test(target)) { isActive = true; }
            if (!isActive && here === 'document-tracker.php' && /tracker/.test(target)) { isActive = true; }
            if (!isActive && here === 'social_feed.php' && /social/.test(target)) { isActive = true; }
            if (!isActive && here === 'social-feed.php' && /social/.test(target)) { isActive = true; }

            if (item) { item.classList.toggle('active', isActive); }
            link.classList.toggle('active', isActive);
        });
    }

    /* ---------- tables ----------
       Two jobs:
         1. wrap a bare <table> in a scroll container so a wide table can be
            swiped instead of breaking the page layout
         2. copy each <th> label onto the matching <td data-label>, which is what
            lets a .table-stack table turn into labelled cards on a phone
       Both are additive: no markup is removed and nothing is hidden by default. */
    function enhanceTables() {
        $$('table').forEach(function (table) {
            if (table.dataset.cvsuEnhanced === '1') { return; }
            table.dataset.cvsuEnhanced = '1';

            // --- data-label plumbing for the stacked card layout ---
            var heads = $$('thead th', table);
            $$('tbody tr', table).forEach(function (row) {
                $$('td', row).forEach(function (cell, i) {
                    var th = heads[i];
                    if (th && !cell.getAttribute('data-label')) {
                        cell.setAttribute('data-label', (th.textContent || '').trim());
                    }
                });
            });

            // Opt a table into the card transformation on small screens.
            if (table.getAttribute('data-responsive') === 'stack') {
                table.classList.add('table-stack');
            }

            // --- scroll container ---
            var parent = table.parentNode;
            if (!parent) { return; }
            var already = parent.classList.contains('table-scroll') ||
                          parent.classList.contains('table-wrapper') ||
                          parent.classList.contains('table-responsive') ||
                          parent.classList.contains('table-container');

            if (!already && parent.tagName !== 'BODY') {
                var wrap = document.createElement('div');
                wrap.className = 'table-scroll';
                parent.insertBefore(wrap, table);
                wrap.appendChild(table);
            }
        });
    }

    /* The edge shadow should only show when there is actually more table to see. */
    function updateScrollHints() {
        $$('.table-scroll').forEach(function (wrap) {
            wrap.classList.toggle('scrollable', wrap.scrollWidth - wrap.clientWidth > 2);
        });
    }

    /* ---------- init ---------- */
    function init() {
        if (document.body.dataset.cvsuTheme === '1') { return; }
        document.body.dataset.cvsuTheme = '1';

        var sidebar = $('#sidebar');

        initLoadingProgress();
        initSidebarTooltips();
        initNotifications();

        if (sidebar) {
            // Create the scrim up front so the first tap has something to show.
            getBackdrop();
            syncDrawer();
            var backButton = $('.cvsu-drawer-back', sidebar);
            if (backButton) {
                backButton.addEventListener('click', closeDrawer);
            }

            // Watch the class the APP's own script toggles. No click handler is
            // registered here - that would toggle `.expanded` a second time.
            if (window.MutationObserver) {
                new MutationObserver(syncDrawer).observe(sidebar, {
                    attributes: true,
                    attributeFilter: ['class']
                });
            }

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && isMobile() && sidebar.classList.contains('expanded')) {
                    closeDrawer();
                }
            });

            markActive();
        }

        // Tables may arrive later via fetch, so run on load and after updates.
        enhanceTables();
        updateScrollHints();
        window.addEventListener('resize', function () {
            // Leaving the mobile breakpoint must not strand an open scrim.
            if (!isMobile()) { document.body.style.overflow = ''; }
            syncDrawer();
            updateScrollHints();
        });

        // Re-run after any DOM swap that replaces a region wholesale.
        if (window.MutationObserver) {
            var pending = null;
            var obs = new MutationObserver(function () {
                clearTimeout(pending);
                pending = setTimeout(function () {
                    enhanceTables();
                    updateScrollHints();
                }, 120);
            });
            obs.observe(document.body, { childList: true, subtree: true });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();