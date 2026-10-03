<?php
/** TEMPORARY diagnostic. DELETE BEFORE FINISHING. */
require_once __DIR__ . '/includes/config.php';
$uid  = (int)($_GET['uid'] ?? 0);
$page = (string)($_GET['page'] ?? 'roles/admin/shared.php');
if ($uid > 0) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND is_approved = 1 LIMIT 1");
    $stmt->execute([$uid]);
    $u = $stmt->fetch();
    if ($u) { $_SESSION['user_id']=$u['id']; $_SESSION['username']=$u['username'];
              $_SESSION['user_role']=$u['role']; $_SESSION['login_time']=time();
              $_SESSION['last_activity']=time(); }
}
if (preg_match('~^/?[A-Za-z0-9_./-]+\.php$~', $page) !== 1) { $page = 'roles/admin/shared.php'; }
$page = ltrim($page, '/');
?>
<!DOCTYPE html><html><head><meta charset="utf-8"><style>iframe{border:0;width:1440px;height:1000px}</style></head><body>
<pre id="out">PENDING</pre>
<iframe id="f" src="<?php echo htmlspecialchars($page); ?>"></iframe>
<script>
document.getElementById('f').addEventListener('load', function () {
  setTimeout(function () {
    var d = this.contentDocument, w = this.contentWindow;
    var out = { page: '<?php echo $page; ?>', hits: [] };

    // For every element matching sel, list the background-bearing rules that
    // apply, in cascade order, flagging which one actually won.
    ['.btn-primary', '.status-badge', '#sidebar .side-menu li.active a'].forEach(function (sel) {
      var seen = {};
      Array.prototype.forEach.call(d.querySelectorAll(sel), function (el) {
        var r = el.getBoundingClientRect();
        if (r.width < 1 && r.height < 1) return;
        var cs = w.getComputedStyle(el);
        var key = sel + '||' + (el.className || '').toString();
        if (seen[key]) { seen[key].count++; return; }
        var rules = [];
        Array.prototype.forEach.call(d.styleSheets, function (sheet) {
          var rs; try { rs = sheet.cssRules; } catch (e) { return; }
          Array.prototype.forEach.call(rs, function (rr) {
            if (!rr.selectorText) return;
            try { if (!el.matches(rr.selectorText)) return; } catch (e) { return; }
            var bg = rr.style.background || rr.style.backgroundColor || '';
            if (!bg) return;
            rules.push({
              sel: rr.selectorText.replace(/\s+/g, ' ').slice(0, 62),
              bg: bg.slice(0, 42),
              imp: !!rr.style.getPropertyPriority('background-color'),
              spec: spec(rr.selectorText)
            });
          });
        });
        // cascade: !important beats normal; among equals, later wins
        var best = null;
        rules.forEach(function (r) {
          if (!best) { best = r; return; }
          if (r.imp && !best.imp) { best = r; return; }
          if (r.imp === best.imp && r.spec >= best.spec) best = r;
        });
        seen[key] = {
          count: 1,
          cls: (el.className || '').toString().slice(0, 54),
          computed: cs.backgroundColor,
          winner: best ? (best.sel + '  =>  ' + best.bg + (best.imp ? '  !important' : '')) : '(none)',
          rivals: rules.filter(function (r) { return best && r.sel !== best.sel; })
                      .map(function (r) { return r.sel + ' => ' + r.bg + (r.imp ? ' !imp' : ''); })
        };
      });
      out.hits.push({ sel: sel, items: seen });
    });

    function spec(s) {
      // crude but sufficient: count ids, classes/attrs, elements
      var ids = (s.match(/#[\w-]+/g) || []).length;
      var cls = (s.match(/\.[\w-]+|\[[^\]]+\]|:(?!:)[\w-]+/g) || []).length;
      var els = (s.match(/(^|[\s>+~])([a-z*][\w-]*)/gi) || []).length;
      return ids * 10000 + cls * 100 + els;
    }

    document.getElementById('out').textContent = 'QAJSON' + JSON.stringify(out, null, 1) + 'QAEND';
  }.bind(this), 1200);
});
</script></body></html>