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
    --poppins: 'Poppins';
    
    /* Modern Color Palette */
    --primary-color: #10b981;
    --secondary-color: #059669;
    --success-color: #28a745;
    --warning-color: #ffc107;
    --danger-color: #dc3545;
    --info-color: #17a2b8;
    --green: #10b981;
    --warning-orange: #f59e0b;
    --danger-red: #ef4444;
    --info-cyan: #06b6d4;

    
    /* Overview Card Colors */
    --total-color: #ffc107;
    --total-bg: linear-gradient(135deg, #f5ca4b, #ac944d);

    --active-color: #10b981;
    --active-bg: linear-gradient(135deg, #10b981, #059669);
    
    --inactive-color: #ef4444;
    --inactive-bg: linear-gradient(135deg, #ef4444, #dc2626);
    
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
    grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
    gap: 25px;
    margin-top: 30px;
}

.dept-card {
    background: white;
    border-radius: 20px;
    padding: 30px;
    box-shadow: 0 10px 35px rgba(0,0,0,0.1);
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    border: 1px solid rgba(255,255,255,0.2);
    position: relative;
    overflow: hidden;
}

/* Default department card styling */
.dept-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
}


.dept-card:hover {
    transform: translateY(-8px) scale(1.02);
    box-shadow: 0 20px 50px rgba(0,0,0,0.15);
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
    margin-bottom: 20px;
    position: relative;
    z-index: 2;
}

.dept-icon {
    width: 70px;
    height: 70px;
    border-radius: 18px;
    background: linear-gradient(135deg, #10b981, #06d485ff);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 28px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 8px 25px rgba(16, 185, 129, 0.3);
}

.dept-icon::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.2) 0%, transparent 70%);
    animation: shimmer 3s infinite;
}

@keyframes shimmer {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
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
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.05));
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
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    margin: 25px 0;
    padding: 25px;
    background: linear-gradient(135deg, var(--gray-50) 0%, #ffffff 100%);
    border-radius: 16px;
    border: 1px solid var(--gray-200);
}

.stat-item {
    text-align: center;
    transition: transform 0.2s ease;
}

.stat-item:hover {
    transform: translateY(-2px);
}

.stat-number {
    font-size: 24px;
    font-weight: 800;
    color: var(--primary-color);
    display: block;
    margin-bottom: 4px;
}

.stat-label {
    font-size: 11px;
    color: var(--gray-500);
    text-transform: uppercase;
    letter-spacing: 0.8px;
    font-weight: 600;
}

.dept-contact {
    font-size: 13px;
    color: var(--gray-600);
    margin: 20px 0;
    background: var(--gray-50);
    padding: 20px;
    border-radius: 12px;
    border-left: 4px solid var(--primary-color);
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
    margin-top: 25px;
    padding-top: 20px;
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
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white;
    box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
}

.btn-success { 
    background: linear-gradient(135deg, var(--success-color), #20c997);
    color: white;
    box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
}

.btn-warning { 
    background: linear-gradient(135deg, var(--warning-color), #fd7e14);
    color: var(--gray-800);
    box-shadow: 0 4px 15px rgba(255, 193, 7, 0.3);
}

.btn-danger { 
    background: linear-gradient(135deg, var(--danger-color), #e74c3c);
    color: white;
    box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
}

.btn-secondary { 
    background: linear-gradient(135deg, var(--gray-600), var(--gray-700));
    color: white;
    box-shadow: 0 4px 15px rgba(108, 117, 125, 0.3);
}

.btn:hover { 
    transform: translateY(-2px) scale(1.02);
    box-shadow: 0 6px 20px rgba(0,0,0,0.15);
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
    border-radius: 16px;
    padding: 6px;
    box-shadow: 0 8px 30px rgba(0,0,0,0.1);
    border: 1px solid var(--gray-200);
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
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white;
    box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
    transform: translateY(-2px);
}

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.6);
    z-index: 1000;
    backdrop-filter: blur(5px);
}

.modal-content {
    background: white;
    border-radius: 24px;
    padding: 40px;
    max-width: 600px;
    width: 90%;
    margin: 50px auto;
    max-height: 80vh;
    overflow-y: auto;
    box-shadow: 0 25px 60px rgba(0,0,0,0.3);
    border: 1px solid rgba(255,255,255,0.2);
    position: relative;
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
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
    transform: translateY(-2px);
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
    padding: 20px 25px;
    border-radius: 16px;
    margin-bottom: 25px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.alert i {
    font-size: 20px;
}

.alert-success {
    background: linear-gradient(135deg, rgba(40, 167, 69, 0.1), rgba(40, 167, 69, 0.05));
    border: 2px solid rgba(40, 167, 69, 0.2);
    color: var(--success-color);
}

.alert-error {
    background: linear-gradient(135deg, rgba(220, 53, 69, 0.1), rgba(220, 53, 69, 0.05));
    border: 2px solid rgba(220, 53, 69, 0.2);
    color: var(--danger-color);
}

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
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white;
    border-color: var(--primary-color);
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
}

.pagination .current {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white;
    border-color: var(--primary-color);
    box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
}

.stats-overview {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 25px;
    margin-bottom: 35px;
}

.overview-card {
    background: white;
    padding: 35px 30px;
    border-radius: 20px;
    text-align: center;
    box-shadow: 0 10px 35px rgba(0,0,0,0.1);
    position: relative;
    overflow: hidden;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    border: 1px solid rgba(255,255,255,0.2);
}

.overview-card:nth-child(1) {
    background: linear-gradient(135deg, rgba(245, 202, 75, 0.05) 0%, rgba(255, 255, 255, 0.95) 100%);
}

.overview-card:nth-child(1)::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--total-bg);
}

.overview-card:nth-child(1) i {
    color: var(--total-color);
    background: linear-gradient(135deg, rgba(245, 202, 75, 0.1), rgba(172, 148, 77, 0.1));
    padding: 15px;
    border-radius: 50%;
    box-shadow: 0 8px 25px rgba(245, 202, 75, 0.2);
}

.overview-card:nth-child(1) h3 {
    color: var(--total-color);
}

/* Alternative: If you want more vibrant yellow/gold styling */
.overview-card:nth-child(1) {
    background: linear-gradient(135deg, rgba(255, 193, 7, 0.05) 0%, rgba(255, 255, 255, 0.95) 100%);
    border: 1px solid rgba(255, 193, 7, 0.1);
}

.overview-card:nth-child(1)::before {
    background: linear-gradient(135deg, #ffc107, #e0a800);
}

.overview-card:nth-child(1) i {
    color: #ffc107;
    background: linear-gradient(135deg, #ffc107;), rgba(224, 168, 0, 0.1));
    box-shadow: 0 8px 25px rgba(255, 193, 7, 0.25);
}



/* Overview Card - Active Departments */
.overview-card:nth-child(2) {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.05) 0%, rgba(255, 255, 255, 0.95) 100%);
}

.overview-card:nth-child(2)::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--active-bg);
}

.overview-card:nth-child(2) i {
    color: var(--active-color);
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(5, 150, 105, 0.1));
    padding: 15px;
    border-radius: 50%;
    box-shadow: 0 8px 25px rgba(16, 185, 129, 0.2);
}

.overview-card:nth-child(2) h3 {
    color: var(--active-color);
}

/* Overview Card - Inactive Departments */
.overview-card:nth-child(3) {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.05) 0%, rgba(255, 255, 255, 0.95) 100%);
}

.overview-card:nth-child(3)::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--inactive-bg);
}

.overview-card:nth-child(3) i {
    color: var(--inactive-color);
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(220, 38, 38, 0.1));
    padding: 15px;
    border-radius: 50%;
    box-shadow: 0 8px 25px rgba(239, 68, 68, 0.2);
}

.overview-card:nth-child(3) h3 {
    color: var(--inactive-color);
}

.overview-card i {
    font-size: 3rem;
    margin-bottom: 15px;
    opacity: 0.9;
    transition: all 0.3s ease;
}

.overview-card:hover i {
    transform: scale(1.1) rotate(5deg);
}

.overview-card h3 {
    margin: 0;
    font-size: 2.5rem;
    font-weight: 800;
    margin-bottom: 8px;
    transition: all 0.3s ease;
}

.overview-card:hover h3 {
    transform: translateY(-3px);
}

.overview-card p {
    margin: 0;
    color: var(--gray-500);
    font-size: 14px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.overview-card:hover {
    transform: translateY(-8px) scale(1.02);
    box-shadow: 0 20px 50px rgba(0,0,0,0.15);
}

/* Empty State Styling */
.dept-grid + div[style*="text-align: center"] {
    background: white;
    border-radius: 24px;
    padding: 80px 40px;
    box-shadow: 0 10px 35px rgba(0,0,0,0.1);
    margin: 40px 0;
    border: 2px dashed var(--gray-300);
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

/* Add Department Button - Header Style */
.head-title .btn-download {
    height: auto;
    padding: 12px 24px;
    border-radius: 12px;
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white;
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
    text-decoration: none;
}

.head-title .btn-download:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
    background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
}

.head-title .btn-download i {
    font-size: 16px;
}

@media (max-width: 768px) {
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
        gap: 15px;
        padding: 20px;
    }
    
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
        margin: 20px auto;
        padding: 25px;
        max-height: 90vh;
    }
    
    .overview-card {
        padding: 25px 20px;
    }
    
    .stats-overview {
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
    }
}
    </style>

    <!-- Shared CVSU design system (green / gold / white) - loaded last on purpose -->
    <?php include __DIR__ . '/../../includes/theme.php'; ?>
</head>
<body>
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
                <button onclick="openModal('createDeptModal')" class="btn-download">
                    <i class='bx bx-buildings'></i>
                    <span class="text">Add Department</span>
                </button>
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
