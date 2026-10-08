<?php include 'assets/script/departments-script.php'?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Department Management - CVSU Naic</title>
    <link rel="icon" type="image/png" href="../../img/cvsu-logo.png">
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/base.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/sidebar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/navbar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?= time() ?>">
 <style>
        /* Department Management Redesigned CSS - Following Modern Design System */

:root {
    --poppins: 'Plus Jakarta Sans', 'Poppins', sans-serif;
    
    /* CVSU brand palette (mirrors assets/css/cvsu-theme.css) */
    --primary-color: #28a745;
    --secondary-color: #1e7e34;
    --success-color: #28a745;
    --warning-color: #e0a000;
    --danger-color: #dc2626;
    --info-color: #1e7e34;
    --green: #28a745;
    --warning-orange: #e0a000;
    --danger-red: #dc2626;
    --info-cyan: #166534;

    
    /* Overview Card Colors */
    --total-color: #b8860b;
    --total-bg: linear-gradient(90deg, var(--total-color), #f0c000);

    --active-color: #1e7e34;
    --active-bg: linear-gradient(90deg, var(--active-color), #28a745);
    
    --inactive-color: #dc2626;
    --inactive-bg: linear-gradient(90deg, var(--inactive-color), #b91c1c);
    
    /* Neutrals */
    --gray-50: #f8fafc;
    --gray-100: #f1f5f9;
    --gray-200: #e2e8f0;
    --gray-300: #cbd5e1;
    --gray-400: #94a3b8;
    --gray-500: #64748b;
    --gray-600: #475569;
    --gray-700: #334155;
    --gray-800: #1e293b;
    --gray-900: #0f172a;
    
    /* Legacy Colors for Compatibility */
    --light: #F9F9F9;
    --light-green: #cfffef;
    --grey: #eee;
    --dark-grey: #AAAAAA;
    --dark: #342E37;
    --red: #DB504A;
    --yellow: #FFCE26;
    --light-yellow: #FFF2C6;
    --orange: #FD7238;
    --light-orange: #FFE0D3;
    --light-blue: #87CEEB;
}

.dept-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(100%, 380px), 1fr));
    gap: 25px;
    margin-top: 30px;
}

.dept-card {
    background: white;
    border-radius: 16px;
    padding: 26px;
    box-shadow: var(--cvsu-shadow, 0 2px 8px rgba(11, 61, 30, .07));
    transition: box-shadow .18s ease, border-color .18s ease;
    border: 1px solid var(--cvsu-line, #e6ebe7);
    position: relative;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

/* Default department card styling */
.dept-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--cvsu-green-600, #1e7e34), var(--cvsu-green-400, #43c76a));
}


/* Container holds still - the contents carry the feedback. */
.dept-card:hover {
    transform: none;
    box-shadow: var(--cvsu-shadow-md, 0 6px 20px rgba(11, 61, 30, .10));
}

.dept-card.inactive {
    opacity: 0.8;
    filter: grayscale(0.3);
}

.dept-card.inactive::before {
    background: linear-gradient(90deg, var(--gray-400), var(--gray-500));
}

.dept-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 18px;
    position: relative;
    z-index: 2;
    gap: 12px;
}

.dept-icon {
    width: 56px;
    height: 56px;
    min-width: 56px;
    border-radius: 14px;
    background: linear-gradient(135deg, rgba(30, 126, 52, 0.12), rgba(20, 83, 45, 0.06));
    border: 1px solid rgba(30, 126, 52, 0.18);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--cvsu-green-700, #1e7e34);
    font-size: 26px;
    position: relative;
    overflow: hidden;
}

.dept-status {
    padding: 8px 16px;
    border-radius: 25px;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    backdrop-filter: blur(10px);
    border: 2px solid rgba(255,255,255,0.3);
}

.status-active {
    background: linear-gradient(135deg, rgba(40, 167, 69, 0.2), rgba(40, 167, 69, 0.1));
    color: var(--success-color);
    border-color: rgba(40, 167, 69, 0.3);
}

.status-inactive {
    background: linear-gradient(135deg, rgba(220, 53, 69, 0.2), rgba(220, 53, 69, 0.1));
    color: var(--danger-color);
    border-color: rgba(220, 53, 69, 0.3);
}

.dept-info h3 {
    margin: 20px 0 8px 0;
    font-size: 22px;
    font-weight: 700;
    color: var(--gray-800);
    line-height: 1.2;
}

.dept-code {
    color: var(--primary-color);
    font-weight: 700;
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 1px;
    background: linear-gradient(135deg, rgba(40, 167, 69, 0.12), rgba(220, 252, 231, 0.6));
    padding: 4px 12px;
    border-radius: 12px;
    display: inline-block;
    margin-bottom: 8px;
}

.dept-description {
    color: var(--gray-600);
    font-size: 14px;
    line-height: 1.6;
    margin: 15px 0;
    font-weight: 500;
}

.dept-stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 8px;
    margin: 18px 0;
    padding: 14px 8px;
    background: var(--gray-50);
    border-radius: 14px;
    border: 1px solid var(--gray-200);
}

/* The shared theme (loaded later) paints a rail + padding on generic
   .stat-item tiles; these live inside a compact stat strip, so undo it. */
.superadmin-departments-page .dept-stats .stat-item {
    background: transparent !important;
    border: none !important;
    border-right: 1px solid var(--gray-200) !important;
    border-radius: 0 !important;
    box-shadow: none !important;
    padding: 4px 2px !important;
    display: block !important;
    gap: 0 !important;
}

.superadmin-departments-page .dept-stats .stat-item:last-child {
    border-right: none !important;
}

.stat-item {
    text-align: center;
    padding: 4px 2px;
    border-right: 1px solid var(--gray-200);
    min-width: 0;
}

.stat-item:last-child {
    border-right: none;
}

.stat-item:hover {
    transform: none;
}

.stat-number {
    font-size: 20px;
    font-weight: 800;
    color: var(--cvsu-green-700, #1e7e34);
    display: block;
    margin-bottom: 3px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.stat-label {
    font-size: 10px;
    color: var(--gray-500);
    text-transform: uppercase;
    letter-spacing: 0.7px;
    font-weight: 700;
}

.dept-contact {
    font-size: 13px;
    color: var(--gray-600);
    margin: 20px 0;
    background: var(--gray-50);
    padding: 16px 18px;
    border-radius: 12px;
    border: 1px solid var(--gray-200);
}

.dept-contact div {
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    font-weight: 500;
}

.dept-contact div:last-child {
    margin-bottom: 0;
}

.dept-contact i {
    width: 20px;
    margin-right: 10px;
    color: var(--primary-color);
    font-size: 14px;
}

.dept-actions {
    display: flex;
    gap: 8px;
    margin-top: auto;
    padding-top: 18px;
    border-top: 2px solid var(--gray-100);
    flex-wrap: wrap;
    justify-content: flex-start;
    align-items: center;
}

.btn {
    padding: 8px 16px;
    border: none;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.3s ease;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    position: relative;
    overflow: hidden;
    flex-shrink: 0;
}

.btn::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    background: rgba(255,255,255,0.2);
    border-radius: 50%;
    transition: all 0.3s ease;
    transform: translate(-50%, -50%);
}

.btn:hover::before {
    width: 300px;
    height: 300px;
}

.btn-primary { 
    background: var(--cvsu-green-500, #28a745);
    color: white;
    box-shadow: 0 1px 2px rgba(11, 61, 30, 0.14);
}

.btn-success { 
    background: var(--cvsu-green-600, #1e7e34);
    color: white;
    box-shadow: 0 1px 2px rgba(11, 61, 30, 0.14);
}

.btn-warning { 
    background: var(--cvsu-gold-400, #f0c000);
    color: var(--cvsu-green-900, #0b3d1e);
    box-shadow: 0 1px 2px rgba(11, 61, 30, 0.14);
}

.btn-danger { 
    background: var(--cvsu-danger, #dc2626);
    color: white;
    box-shadow: 0 1px 2px rgba(11, 61, 30, 0.14);
}

.btn-secondary { 
    background: white;
    color: var(--cvsu-green-700, #166534);
    border: 1px solid var(--cvsu-line, #e6ebe7);
    box-shadow: none;
}

.btn:hover { 
    transform: none;
    box-shadow: var(--cvsu-shadow-md, 0 6px 20px rgba(11, 61, 30, .10));
}

.filters {
    display: flex;
    gap: 20px;
    align-items: center;
    margin: 30px 0;
    flex-wrap: wrap;
}

.filter-tabs {
    display: flex;
    background: white;
    border-radius: 12px;
    padding: 6px;
    box-shadow: var(--cvsu-shadow, 0 2px 8px rgba(11, 61, 30, .07));
    border: 1px solid var(--cvsu-line, #e6ebe7);
}

.filter-tab {
    padding: 12px 24px;
    border-radius: 12px;
    text-decoration: none;
    color: var(--gray-600);
    font-size: 14px;
    font-weight: 600;
    transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    position: relative;
    overflow: hidden;
}

.filter-tab::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
    transition: left 0.5s;
}

.filter-tab:hover::before {
    left: 100%;
}

.filter-tab.active {
    background: var(--cvsu-green-500, #28a745);
    color: white;
    box-shadow: 0 1px 2px rgba(11, 61, 30, 0.14);
    transform: none;
}

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(15, 23, 42, 0.55);
    z-index: 2500;
    backdrop-filter: blur(5px);
    align-items: center;
    justify-content: center;
    padding: 32px;
    box-sizing: border-box;
    animation: fadeIn 0.25s ease;
}

/* Center the dialog within the content area (right of the sidebar) */
@media (min-width: 769px) {
    .modal { padding-left: calc(60px + 32px); }
    body:has(#sidebar.expanded) .modal { padding-left: calc(280px + 32px); }
}

@keyframes fadeIn {
    from { opacity: 0; }
    to   { opacity: 1; }
}

.modal-content {
    background: white;
    border-radius: 20px;
    padding: 40px;
    max-width: 600px;
    width: 100%;
    margin: 0;
    max-height: calc(100vh - 64px);
    overflow-y: auto;
    box-shadow: 0 25px 60px rgba(15, 23, 42, 0.3);
    border: 1px solid var(--gray-200);
    position: relative;
    animation: modalSlide 0.28s ease;
}

@keyframes modalSlide {
    from { opacity: 0; transform: translateY(14px) scale(0.98); }
    to   { opacity: 1; transform: translateY(0) scale(1); }
}

.modal-content::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
    border-radius: 24px 24px 0 0;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid var(--gray-100);
}

.modal-header h3 {
    margin: 0;
    font-size: 24px;
    font-weight: 700;
    color: var(--gray-800);
}

.close {
    background: var(--gray-100);
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: var(--gray-600);
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.close:hover {
    background: var(--danger-color);
    color: white;
    transform: rotate(90deg);
}

.form-group {
    margin-bottom: 25px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: var(--gray-700);
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 15px 20px;
    border: 2px solid var(--gray-200);
    border-radius: 12px;
    font-size: 15px;
    font-weight: 500;
    transition: all 0.3s ease;
    background: var(--gray-50);
    font-family: var(--poppins);
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--primary-color);
    background: white;
    box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.14);
    transform: none;
}

.form-group textarea {
    resize: vertical;
    min-height: 100px;
}

.form-group.checkbox {
    display: flex;
    align-items: center;
    gap: 12px;
}

.form-group.checkbox input {
    width: auto;
    transform: scale(1.2);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.alert {
    padding: 14px 18px;
    border-radius: 10px;
    margin-bottom: 24px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 12px;
    box-shadow: none;
    border: 1px solid transparent;
}

.alert i {
    font-size: 18px;
}

.alert-success { border-color: var(--cvsu-green-300, #6ee7a0); }
.alert-error   { border-color: #fca5a5; }

.pagination {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin-top: 40px;
}

.pagination a,
.pagination span {
    padding: 12px 18px;
    border-radius: 12px;
    text-decoration: none;
    color: var(--gray-700);
    border: 2px solid var(--gray-200);
    font-weight: 600;
    transition: all 0.3s ease;
    background: white;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.pagination a:hover {
    background: var(--cvsu-green-50, #f0fdf4);
    color: var(--cvsu-green-800, #14532d);
    border-color: var(--cvsu-green-300, #6ee7a0);
    transform: none;
    box-shadow: none;
}

.pagination .current {
    background: var(--cvsu-green-500, #28a745);
    color: white;
    border-color: var(--cvsu-green-500, #28a745);
    box-shadow: 0 1px 2px rgba(11, 61, 30, 0.14);
}

.stats-overview {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(100%, 200px), 1fr));
    gap: 18px;
    margin-bottom: 28px;
}

.overview-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
    background: #fff;
    padding: 24px 22px;
    border-radius: 12px;
    text-align: center;
    box-shadow: 0 1px 3px rgba(24, 47, 31, .05);
    transition: box-shadow .18s ease, border-color .18s ease;
    border: 1px solid var(--cvsu-line, #e6ebe7);
}

.overview-card i {
    width: 52px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    font-size: 1.6rem;
    background: var(--cvsu-green-50, #f0f7f1);
    color: var(--cvsu-green-600, #006b2e);
    transition: transform .18s ease;
}

/* Total Departments - gold accent */
.overview-card:nth-child(1) i {
    background: #fbf5e5;
    color: #b8860b;
}

/* Active Departments */
.overview-card:nth-child(2) i {
    background: #f0f7f1;
    color: #006b2e;
}

/* Inactive Departments */
.overview-card:nth-child(3) i {
    background: #fef2f2;
    color: #dc2626;
}

.overview-card h3 {
    margin: 0;
    font-size: 2rem;
    font-weight: 800;
    line-height: 1;
    color: #004d24;
    transition: color .18s ease;
}

.overview-card p {
    margin: 0;
    color: var(--gray-500, #647168);
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .04em;
}

/* Container stays put - only the icon inside responds. */
.overview-card:hover {
    transform: none;
    border-color: #c8dccb;
    box-shadow: 0 6px 20px rgba(24, 47, 31, .08);
}

.overview-card:hover i {
    transform: scale(1.06);
}

/* Empty State Styling */
.dept-grid + div[style*="text-align: center"] {
    background: white;
    border-radius: 12px;
    padding: 60px 32px;
    box-shadow: none;
    margin: 32px 0;
    border: 2px dashed var(--cvsu-line, #e6ebe7);
}

.dept-grid + div[style*="text-align: center"] i {
    color: var(--primary-color) !important;
    opacity: 0.6;
}

.dept-grid + div[style*="text-align: center"] h3 {
    color: var(--gray-700) !important;
    font-weight: 700;
    margin: 20px 0 10px 0;
}

.dept-grid + div[style*="text-align: center"] p {
    color: var(--gray-500) !important;
    font-size: 16px;
}

/* Header actions - layout only; cvsu-theme.css paints .btn-* colours. */
.head-title {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 24px;
    padding: 22px 24px;
    background: white;
    border: 1px solid var(--cvsu-line, #e6ebe7);
    border-radius: 12px;
    box-shadow: var(--cvsu-shadow, 0 2px 8px rgba(11, 61, 30, .07));
    position: relative;
    overflow: hidden;
}

.head-title::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--cvsu-green-500, #28a745), var(--cvsu-gold-400, #f0c000));
}

.head-title .left { min-width: 0; }

.head-title .right {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.head-title .left h1 {
    font-size: 26px;
    font-weight: 700;
    color: var(--cvsu-ink, #16241c);
    margin-bottom: 6px;
}

.head-title .btn {
    height: auto;
    padding: 10px 18px;
    border-radius: 8px;
    display: inline-flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    font-size: 14px;
    transition: background .18s ease, border-color .18s ease, color .18s ease;
    cursor: pointer;
    text-decoration: none;
}

.head-title .btn:hover {
    transform: none;
}

@media (max-width: 768px) {
    .head-title {
        flex-direction: column;
        align-items: stretch;
        gap: 14px;
        padding: 20px;
        margin-bottom: 18px;
    }

    .head-title .right {
        justify-content: flex-start;
    }

    .head-title .right .btn {
        flex: 1 1 auto;
        justify-content: center;
    }

    .dept-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }

    .filters {
        flex-direction: column;
        align-items: stretch;
    }
    
    .filter-tabs {
        width: 100%;
        justify-content: center;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .dept-stats {
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
        padding: 14px 10px;
    }

    .stat-item { border-right: none; }
    .stat-item:nth-child(odd) { border-right: 1px solid var(--gray-200); }

    .dept-actions {
        flex-direction: row;
        flex-wrap: wrap;
        justify-content: flex-start;
        gap: 8px;
    }

    .btn {
        flex: 0 0 auto;
        min-width: auto;
    }

    .modal-content {
        margin: 0;
        padding: 25px;
        max-height: calc(100vh - 40px);
        border-radius: 16px;
    }
    
    .overview-card {
        padding: 20px 16px;
    }
    
    .stats-overview {
        grid-template-columns: repeat(auto-fit, minmax(min(100%, 160px), 1fr));
        gap: 12px;
    }
}
    </style>

    <!-- Shared CVSU design system (green / gold / white) - loaded last on purpose -->
    <?php include __DIR__ . '/../../includes/theme.php'; ?>
</head>
<body class="superadmin-departments-page">
<!-- Sidebar Component -->
    <?php include 'components/sidebar.html'; ?>

    <!-- Content -->
    <section id="content">
        <!-- Navbar Component -->
        <?php include 'components/navbar.html'; ?>

        <!-- Main Content -->
        <main>
            <div class="head-title">
                <div class="left">
                    <h1>Department Management</h1>
                    <ul class="breadcrumb">
                        <li><a href="dashboard.php">Dashboard</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="#">Departments</a></li>
                    </ul>
                </div>
                <div class="right">
                    <button class="btn btn-secondary" type="button" title="Refresh" onclick="window.location.reload()">
                        <i class='bx bx-refresh'></i>
                        <span>Refresh</span>
                    </button>
                    <button onclick="openModal('createDeptModal')" class="btn btn-primary">
                        <i class='bx bx-buildings'></i>
                        <span class="text">Add Department</span>
                    </button>
                </div>
            </div>

            <!-- Alerts -->
            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class='bx bx-check-circle'></i> <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class='bx bx-error-circle'></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Statistics Overview -->
            <div class="stats-overview">
                <div class="overview-card">
                    <i class='bx bxs-buildings'></i>
                    <h3><?php echo $counts['total']; ?></h3>
                    <p>Total Departments</p>
                </div>
                <div class="overview-card">
                    <i class='bx bxs-check-circle'></i>
                    <h3><?php echo $counts['active']; ?></h3>
                    <p>Active Departments</p>
                </div>
                <div class="overview-card">
                    <i class='bx bxs-x-circle'></i>
                    <h3><?php echo $counts['inactive']; ?></h3>
                    <p>Inactive Departments</p>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters">
                <div class="filter-tabs">
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['status' => 'all', 'page' => 1])); ?>" 
                       class="filter-tab <?php echo $status_filter === 'all' ? 'active' : ''; ?>">
                        All (<?php echo $counts['total']; ?>)
                    </a>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['status' => 'active', 'page' => 1])); ?>" 
                       class="filter-tab <?php echo $status_filter === 'active' ? 'active' : ''; ?>">
                        Active (<?php echo $counts['active']; ?>)
                    </a>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['status' => 'inactive', 'page' => 1])); ?>" 
                       class="filter-tab <?php echo $status_filter === 'inactive' ? 'active' : ''; ?>">
                        Inactive (<?php echo $counts['inactive']; ?>)
                    </a>
                </div>
            </div>

            <!-- Department Grid -->
            <div class="dept-grid">
                <?php foreach ($departments as $dept): ?>
                    <div class="dept-card <?php echo $dept['is_active'] ? '' : 'inactive'; ?>">
                        <div class="dept-header">
                            <div class="dept-icon">
                                <i class='bx bxs-buildings'></i>
                            </div>
                            <div class="dept-status status-<?php echo $dept['is_active'] ? 'active' : 'inactive'; ?>">
                                <?php echo $dept['is_active'] ? 'Active' : 'Inactive'; ?>
                            </div>
                        </div>
                        
                        <div class="dept-info">
                            <div class="dept-code"><?php echo htmlspecialchars($dept['department_code']); ?></div>
                            <h3><?php echo htmlspecialchars($dept['department_name']); ?></h3>
                            <?php if ($dept['description']): ?>
                                <div class="dept-description"><?php echo htmlspecialchars($dept['description']); ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="dept-stats">
                            <div class="stat-item">
                                <span class="stat-number"><?php echo $dept['user_count']; ?></span>
                                <div class="stat-label">Users</div>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number"><?php echo $dept['folder_count']; ?></span>
                                <div class="stat-label">Folders</div>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number"><?php echo $dept['file_count']; ?></span>
                                <div class="stat-label">Files</div>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number"><?php echo formatFileSize($dept['total_size']); ?></span>
                                <div class="stat-label">Storage</div>
                            </div>
                        </div>

                        <?php if ($dept['head_of_department'] || $dept['contact_email'] || $dept['contact_phone']): ?>
                            <div class="dept-contact">
                                <?php if ($dept['head_of_department']): ?>
                                    <div><i class='bx bx-user'></i> <?php echo htmlspecialchars($dept['head_of_department']); ?></div>
                                <?php endif; ?>
                                <?php if ($dept['contact_email']): ?>
                                    <div><i class='bx bx-envelope'></i> <?php echo htmlspecialchars($dept['contact_email']); ?></div>
                                <?php endif; ?>
                                <?php if ($dept['contact_phone']): ?>
                                    <div><i class='bx bx-phone'></i> <?php echo htmlspecialchars($dept['contact_phone']); ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <div class="dept-actions">
                            <button onclick="editDepartment(<?php echo $dept['id']; ?>)" class="btn btn-primary">
                                <i class='bx bx-edit'></i> Edit
                            </button>
                            
                            <button onclick="toggleStatus(<?php echo $dept['id']; ?>)" 
                                    class="btn <?php echo $dept['is_active'] ? 'btn-warning' : 'btn-success'; ?>">
                                <i class='bx <?php echo $dept['is_active'] ? 'bx-pause' : 'bx-play'; ?>'></i>
                                <?php echo $dept['is_active'] ? 'Deactivate' : 'Activate'; ?>
                            </button>
                            
                            <?php if ($dept['user_count'] == 0 && $dept['folder_count'] == 0): ?>
                                <button onclick="deleteDepartment(<?php echo $dept['id']; ?>, '<?php echo htmlspecialchars($dept['department_name']); ?>')" 
                                        class="btn btn-danger">
                                    <i class='bx bx-trash'></i> Delete
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (empty($departments)): ?>
                <div style="text-align: center; padding: 60px; color: #666;">
                    <i class='bx bx-buildings' style="font-size: 64px; margin-bottom: 20px; display: block;"></i>
                    <h3>No departments found</h3>
                    <p>Try adjusting your search criteria or create a new department</p>
                    <button onclick="openModal('createDeptModal')" class="btn btn-primary" style="margin-top: 15px;">
                        <i class='bx bx-plus'></i> Add Department
                    </button>
                </div>
            <?php endif; ?>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">&laquo; Previous</a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next &raquo;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </section>

    <!-- Create Department Modal -->
    <div id="createDeptModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Department</h3>
                <button type="button" class="close" onclick="closeModal('createDeptModal')">&times;</button>
            </div>
            <form method="POST" action="?action=create">
                <div class="form-row">
                    <div class="form-group">
                        <label for="department_code">Department Code *</label>
                        <input type="text" id="department_code" name="department_code" maxlength="10" required 
                               style="text-transform: uppercase;" placeholder="e.g., ITD">
                        <small style="color: #666;">Max 10 characters, will be converted to uppercase</small>
                    </div>
                    <div class="form-group">
                        <label for="department_name">Department Name *</label>
                        <input type="text" id="department_name" name="department_name" required 
                               placeholder="e.g., Information Technology Department">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="3" 
                              placeholder="Brief description of the department..."></textarea>
                </div>
                
                <div class="form-group">
                    <label for="head_of_department">Head of Department</label>
                    <input type="text" id="head_of_department" name="head_of_department" 
                           placeholder="e.g., Dr. John Smith">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="contact_email">Contact Email</label>
                        <input type="email" id="contact_email" name="contact_email" 
                               placeholder="department@cvsu.edu.ph">
                    </div>
                    <div class="form-group">
                        <label for="contact_phone">Contact Phone</label>
                        <input type="tel" id="contact_phone" name="contact_phone" 
                               placeholder="+63-2-1234-5678">
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 30px;">
                    <button type="button" onclick="closeModal('createDeptModal')" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Department</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Department Modal -->
    <div id="editDeptModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Department</h3>
                <button type="button" class="close" onclick="closeModal('editDeptModal')">&times;</button>
            </div>
            <form method="POST" action="?action=update" id="editDeptForm">
                <input type="hidden" id="edit_department_id" name="department_id">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_department_code">Department Code *</label>
                        <input type="text" id="edit_department_code" name="department_code" maxlength="10" required 
                               style="text-transform: uppercase;">
                    </div>
                    <div class="form-group">
                        <label for="edit_department_name">Department Name *</label>
                        <input type="text" id="edit_department_name" name="department_name" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="edit_description">Description</label>
                    <textarea id="edit_description" name="description" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="edit_head_of_department">Head of Department</label>
                    <input type="text" id="edit_head_of_department" name="head_of_department">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_contact_email">Contact Email</label>
                        <input type="email" id="edit_contact_email" name="contact_email">
                    </div>
                    <div class="form-group">
                        <label for="edit_contact_phone">Contact Phone</label>
                        <input type="tel" id="edit_contact_phone" name="contact_phone">
                    </div>
                </div>
                
                <div class="form-group checkbox">
                    <input type="checkbox" id="edit_is_active" name="is_active">
                    <label for="edit_is_active">Department Active</label>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 30px;">
                    <button type="button" onclick="closeModal('editDeptModal')" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Department</button>
                </div>
            </form>
        </div>
    </div>

    <script src="assets/js/script.js?v=<?= time() ?>"></script>
    <script src="assets/js/departments.js"></script>
</body>
</html>
