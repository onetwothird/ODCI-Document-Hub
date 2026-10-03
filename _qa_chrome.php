<?php
// TEMPORARY QA - iframe probe for sidebar/navbar computed styles.
// Sets a session for ?role= then loads ?p= in an iframe and, on load, extracts
// computed styles and writes them into #out as JSON.
// Delete before finishing.
require_once __DIR__ . '/includes/config.php';

$role = isset($_GET['role']) ? $_GET['role'] : 'user';
$uids = ['super_admin' => 1, 'admin' => 27, 'user' => 28];

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (isset($uids[$role])) {
    $uid = $uids[$role];
    $st = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $st->execute([$uid]);
    $u = $st->fetch(PDO::FETCH_ASSOC);
    if ($u) {
        $_SESSION['user_id']       = $u['id'];
        $_SESSION['username']      = $u['username'];
        $_SESSION['user_role']     = $u['role'];
        $_SESSION['user_name']     = $u['name'] . ' ' . ($u['mi'] ? $u['mi'] . '. ' : '') . $u['surname'];
        $_SESSION['department_id'] = $u['department_id'];
        $_SESSION['login_time']    = time();
        $_SESSION['last_activity'] = time();
    }
}
$page = isset($_GET['p']) ? $_GET['p'] : 'roles/superadmin/dashboard.php';
$w = isset($_GET['w']) ? (int)$_GET['w'] : 1440;
$h = isset($_GET['h']) ? (int)$_GET['h'] : 900;
?>
<!DOCTYPE html>
<html><head><meta charset="utf-8">
<style>html,body{margin:0}#f{width:<?= $w ?>px;height:<?= $h ?>px;border:0}</style>
</head><body>
<iframe id="f" src="/ODCI/<?= htmlspecialchars($page) ?>"></iframe>
<pre id="out">PENDING</pre>
<script>
document.getElementById('f').addEventListener('load', function () {
  var w = this.contentWindow, d = this.contentDocument;
  function cs(el, props) {
    if (!el) return '(missing)';
    var s = w.getComputedStyle(el), out = [];
    props.forEach(function (p) { out.push(p + '=' + s.getPropertyValue(p)); });
    return out.join('; ');
  }
  var side = d.getElementById('sidebar');
  var nav  = d.querySelector('#content nav');
  var cont = d.getElementById('content');
  var burger = d.querySelector('#content nav .bx.bx-menu');
  var name = d.querySelector('.admin-name, .user-name');
  var role_ = d.querySelector('.admin-role, .user-role, .user-department');
  var sys = d.querySelector('.system-status');
  var quick = d.querySelector('.quick-link, .nav-action-btn, .notification-btn');
  var act = d.querySelector('#sidebar .side-menu li.active a');
  var mi = d.querySelector('#sidebar .side-menu li a');
  var tip = d.querySelector('#sidebar .tooltip');

  var res = {
    viewport: w.innerWidth + 'x' + w.innerHeight,
    sheets: Array.prototype.map.call(d.styleSheets, function (s) {
      return s.href ? s.href.split('/').pop() : '(inline)';
    }),
    themeRuleCount: (function () {
      for (var i = 0; i < d.styleSheets.length; i++) {
        var s = d.styleSheets[i];
        if (s.href && s.href.indexOf('cvsu-theme') > -1) {
          try { return s.cssRules.length; } catch (e) { return 'BLOCKED'; }
        }
      }
      return 'NOT-LOADED';
    })(),
    burgerHTML: burger ? burger.outerHTML.slice(0, 160) : '(missing)',
    burgerParent: burger && burger.parentNode ? burger.parentNode.className || burger.parentNode.tagName : '-',
    nameHTML: name ? name.outerHTML.slice(0, 160) : '(missing)',
    sysHTML: sys ? sys.outerHTML.slice(0, 160) : '(missing)',
    quickHTML: quick ? quick.outerHTML.slice(0, 160) : '(missing)',
    navHTML: nav ? nav.outerHTML.slice(0, 120) : '(missing)',
    sidebar_bg: cs(side, ['background-color','background-image','border-top-width','border-top-color']),
    sidebar_geom: cs(side, ['width','height','position','z-index','overflow-y']),
    sidebar_item: cs(mi, ['width','color','font-family','border-radius','padding-left']),
    sidebar_active: act ? cs(act, ['background-color','color']) : '(no active)',
    sidebar_tooltip: tip ? cs(tip, ['background-image','color','border-radius']) : '(none)',
    navbar_bg: cs(nav, ['background-color','height','padding-left','padding-right','box-shadow','position']),
    navbar_font: cs(nav, ['font-family']),
    content_geom: cs(cont, ['left','width','margin-left']),
    burger: cs(burger, ['color','background-color','width','height','border-radius','font-size']),
    username: cs(name, ['color','font-weight','font-size']),
    rolechip: cs(role_, ['color','font-size','text-transform']),
    sysstatus: cs(sys, ['background-color','color']),
    quicklink: cs(quick, ['color','background-color','border-radius','width']),
    horizScroll: (d.documentElement.scrollWidth > d.documentElement.clientWidth + 2)
                 ? 'YES(' + d.documentElement.scrollWidth + '>' + d.documentElement.clientWidth + ')'
                 : 'no',
    contentScrollW: cont ? cont.scrollWidth : '?',
    bodyFont: cs(d.body, ['font-family','font-size','background-color'])
  };
  // base64 so the JSON survives DOM text extraction intact
  var json = JSON.stringify(res);
  document.getElementById('out').textContent =
    btoa(unescape(encodeURIComponent(json)));
});
</script>
</body></html>
