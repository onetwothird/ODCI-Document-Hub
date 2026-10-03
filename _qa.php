<?php
/**
 * TEMPORARY visual QA helper. DELETE BEFORE FINISHING.
 *
 * Loads a real page in a same-origin iframe at a given viewport width, reads the
 * COMPUTED style of every shared component, and emits JSON so a headless
 * browser's --dump-dom can confirm the theme actually applied.
 *
 *   /_qa.php?uid=28&page=roles/user/dashboard.php&w=1440
 */
require_once __DIR__ . '/includes/config.php';

$uid   = (int)($_GET['uid'] ?? 0);
$page  = (string)($_GET['page'] ?? 'roles/user/dashboard.php');
$w     = (int)($_GET['w'] ?? 1440);

if ($uid > 0) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND is_approved = 1 LIMIT 1");
    $stmt->execute([$uid]);
    $user = $stmt->fetch();
    if ($user) {
        $_SESSION['user_id']       = $user['id'];
        $_SESSION['username']      = $user['username'];
        $_SESSION['user_role']     = $user['role'];
        $_SESSION['user_name']     = $user['name'] . ' ' . ($user['mi'] ? $user['mi'] . '. ' : '') . $user['surname'];
        $_SESSION['department_id'] = $user['department_id'];
        $_SESSION['login_time']    = time();
        $_SESSION['last_activity'] = time();
    }
}

if (preg_match('~^/?[A-Za-z0-9_./-]+\.php$~', $page) !== 1) { $page = 'roles/user/dashboard.php'; }
$page = ltrim($page, '/');
?>
<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>QA</title>
<style>html,body{margin:0}iframe{border:0;display:block}</style>
</head><body>
<pre id="out">PENDING</pre>
<iframe id="f" style="width:<?php echo $w; ?>px;height:1100px" src="<?php echo htmlspecialchars($page); ?>"></iframe>
<script>
var PROBES = [
  ['#sidebar',                ['width','background-image','border-top-color']],
  ['#sidebar .side-menu li a', ['color','border-radius','padding']],
  ['#sidebar .side-menu li.active a', ['background-color']],
  ['#content',                ['margin-left','background-color']],
  ['.app-navbar',             ['background-color','min-height','border-bottom-color']],
  ['.toggle-sidebar',         ['background-color','color','border-radius']],
  ['.profile-image img',      ['width','height','border-radius','border-top-color']],
  ['.admin-name, .user-name', ['font-size','font-weight','color']],
  ['.admin-role, .user-role', ['font-size','color']],
  ['.card',                   ['background-color','border-radius','border-top-color','box-shadow','padding']],
  ['.card-header',            ['padding','border-bottom-color','font-weight']],
  ['.stat-card',              ['background-color','border-left-color','border-radius','padding','display']],
  ['.stat-value',             ['font-size','font-weight','color']],
  ['.stat-label',             ['font-size','color','text-transform']],
  ['.stat-icon',              ['width','height','background-color','color','border-radius']],
  ['.btn',                    ['height','border-radius','font-size','font-weight','padding-left']],
  ['.btn-primary',            ['background-color','color','height']],
  ['.btn-secondary',          ['background-color','color','border-top-color']],
  ['.btn-warning',            ['background-color','color']],
  ['.btn-danger',             ['background-color','color']],
  ['.badge',                  ['background-color','color','border-radius','padding-left','font-size']],
  ['.status-badge',           ['background-color','color','border-radius']],
  ['table',                   ['background-color','font-size','border-radius']],
  ['table thead th',          ['background-color','color','font-size','text-transform','padding']],
  ['table tbody td',          ['padding','border-bottom-color','font-size']],
  ['input[type=text], input:not([type]), select, textarea', ['background-color','border-radius','padding','font-size','height']],
  ['label',                   ['font-size','font-weight','color']],
  ['.empty-state',            ['text-align','padding','color']],
  ['.modal-content',          ['border-radius','box-shadow']],
  ['.pagination',             ['display','gap']],
  ['body',                    ['background-color','font-family','color','font-size','line-height']],
  ['h1',                      ['font-size','font-weight','line-height']],
  ['h2',                      ['font-size','font-weight']],
  ['h3',                      ['font-size','font-weight']]
];

/* Pick the DOMINANT rendered style for a selector rather than the first match.
   Some pages have several variants of a component; comparing the most common
   one is what tells us whether the theme is consistent, and avoids false
   positives from grabbing an unusual instance. */
function pick(el, props) {
  var cs = getComputedStyle(el), o = {};
  props.forEach(function (p) { o[p] = cs.getPropertyValue(p); });
  return o;
}

function sig(o) {
  return props_of(o).join('|');
}
function props_of(o) {
  var a = [];
  for (var k in o) { if (Object.prototype.hasOwnProperty.call(o, k)) { a.push(k + '=' + o[k]); } }
  return a;
}

/* The variant class is the element's own class list with the probe selector's
   tokens removed. Two `<span class="status-badge pending">` and
   `<span class="status-badge empty">` are DIFFERENT states of the same
   component, so they must be compared separately - otherwise a page that simply
   happens to show a different status reads as a styling inconsistency. */
function variantOf(el, sel) {
  var base = (sel.match(/\.[\w-]+/g) || []).map(function (t) { return t.slice(1); });
  return (el.className || '').toString().trim().split(/\s+/).filter(function (c) {
    return c && base.indexOf(c) === -1;
  }).sort().join('.');
}

function dominant(d, w, sel, props) {
  var list = Array.prototype.slice.call(d.querySelectorAll(sel)).filter(function (el) {
    var r = el.getBoundingClientRect();
    return r.width > 0 || r.height > 0;
  });
  if (!list.length) return 'ABSENT';

  // Group by variant first, then by rendered style within the variant.
  var byVariant = {};
  list.forEach(function (el) {
    var v = variantOf(el, sel);
    (byVariant[v] = byVariant[v] || []).push(el);
  });

  var bestVariant = null, bestN = -1;
  Object.keys(byVariant).forEach(function (v) {
    if (byVariant[v].length > bestN) { bestN = byVariant[v].length; bestVariant = v; }
  });

  var els = byVariant[bestVariant], tally = {}, total = els.length;
  els.forEach(function (el) {
    var s = sig(pick(el, props));
    tally[s] = (tally[s] || 0) + 1;
    if (!tally[s + '#el']) { tally[s + '#el'] = el; }
  });
  var best = null, bestCount = -1;
  Object.keys(tally).forEach(function (k) {
    if (k.slice(-3) === '#el') return;
    if (tally[k] > bestCount) { bestCount = tally[k]; best = k; }
  });

  var out = pick(tally[best + '#el'], props);
  out._variants = bestCount + '/' + total;
  out._variant = bestVariant || '(base)';
  var e = tally[best + '#el'];
  out._el = e.tagName.toLowerCase() + '.' + (e.className || '').toString().trim().split(/\s+/).join('.') +
            ' | "' + (e.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 26) + '"';
  return out;
}

document.getElementById('f').addEventListener('load', function () {
  setTimeout(function () {
    var d = this.contentDocument, w = this.contentWindow;
    var res = { page: '<?php echo $page; ?>', vw: this.contentWindow.innerWidth, probes: {} };
    PROBES.forEach(function (pair) {
      res.probes[pair[0]] = dominant(d, w, pair[0], pair[1]);
    });
    res.themeLoaded = Array.prototype.some.call(d.querySelectorAll('link[rel=stylesheet]'), function (l) { return /cvsu-theme/.test(l.href); });

    /* Order of every CSS carrier inside the page. The theme must come after all
       other stylesheets AND after every inline <style>, or it can lose. */
    res.cssOrder = [];
    Array.prototype.forEach.call(
      d.querySelectorAll('link[rel=stylesheet], style'),
      function (n) {
        res.cssOrder.push({
          what: n.tagName === 'LINK'
            ? 'CSS:' + n.getAttribute('href').split('/').pop().split('?')[0]
            : 'INLINE(' + n.textContent.length + ')',
          theme: /cvsu-theme/.test(n.getAttribute('href') || '')
        });
      });
    res.themeIsLast = res.cssOrder.length > 0 && res.cssOrder[res.cssOrder.length - 1].theme === true;
    res.activeNav    = d.querySelectorAll('#sidebar .side-menu li.active').length;
    res.tables       = d.querySelectorAll('table').length;
    res.scrollers    = d.querySelectorAll('.table-scroll').length;
    res.scrollable   = d.querySelectorAll('.table-scroll.scrollable').length;
    res.labelsAdded  = d.querySelectorAll('td[data-label]').length;
    res.h1           = d.querySelectorAll('h1').length;
    res.horizScroll  = d.documentElement.scrollWidth > d.documentElement.clientWidth + 1;
    var wide = [];
    Array.prototype.forEach.call(d.querySelectorAll('body *'), function (el) {
      var r = el.getBoundingClientRect();
      if (r.width > d.documentElement.clientWidth + 2 && wide.length < 6) {
        wide.push((el.tagName + '.' + (el.className || '').toString().split(' ').filter(Boolean).slice(0,2).join('.')).slice(0, 66) + ' w=' + Math.round(r.width));
      }
    });
    res.overflowing = wide;
    document.getElementById('out').textContent = 'QAJSON' + JSON.stringify(res, null, 1) + 'QAEND';
  }.bind(this), 1000);
});
</script>
</body></html>