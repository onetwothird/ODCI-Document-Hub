<?php
/**
 * Shared CVSU theme loader.
 *
 * Every page in all three roles includes this once, immediately before
 * </head>. It emits the two shared assets:
 *
 *   assets/css/cvsu-theme.css  - the single CVSU design system (green/gold/white)
 *   assets/js/cvsu-theme.js   - shared sidebar / drawer / active-nav behaviour
 *
 * It is deliberately loaded LAST so its rules win over the legacy per-role
 * stylesheets, which is what makes the three role dashboards look identical.
 *
 * The helper measures how far the calling page sits below the project root, so
 * no per-page relative paths are needed:
 *
 *   <?php include __DIR__ . '/../../includes/theme.php'; ?>
 *
 * (This file intentionally has no opening <?php output guard: it must be able
 * to emit markup.)
 */
if (!defined('ODCI_THEME_LOADED')) {
    define('ODCI_THEME_LOADED', true);

    // Bump this to bust caches after editing the shared design-system assets.
    $themeVersion = '1.11.1';

    // Work out the relative URL from the calling page back to the project root.
    $themePrefix = '../../';
    $themeCaller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
    $themeCaller = isset($themeCaller[0]['file']) ? $themeCaller[0]['file'] : '';

    if ($themeCaller !== '') {
        $themeRootFs = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/');
        $themeDirFs  = rtrim(str_replace('\\', '/', dirname($themeCaller)), '/');

        if (strpos($themeDirFs, $themeRootFs) === 0) {
            $themeBelow = ltrim(substr($themeDirFs, strlen($themeRootFs)), '/');
            $themeDepth = ($themeBelow === '') ? 0 : (substr_count($themeBelow, '/') + 1);
            $themePrefix = str_repeat('../', $themeDepth);
        }
    }

    $themeCss = $themePrefix . 'assets/css/cvsu-theme.css?v=' . $themeVersion;
    $productCss = $themePrefix . 'assets/css/cvsu-product.css?v=' . $themeVersion;
    $themeJs  = $themePrefix . 'assets/js/cvsu-theme.js?v=' . $themeVersion;
    ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($themeCss); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($productCss); ?>">
    <script src="<?php echo htmlspecialchars($themeJs); ?>" defer></script>
<?php
}