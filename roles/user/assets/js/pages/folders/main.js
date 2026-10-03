// main.js - Main initialization and global variables

// Global variables
let selectedFiles = [];
let selectedTags = [];
let isUploading = false; // FIX: Add upload state tracking
const userDepartmentId = window.userDepartmentId || null;
const fileCategories = window.fileCategories || {};

document.addEventListener('DOMContentLoaded', function() {
    initializeSidebar();
    initializeSearch();
    initializeDarkMode();
    initializeResponsive();
    initializeDepartmentSearch();
    initializeUploadForm();
    initializeModalEvents();
    
    // Initialize new academic period functionality
    initializeSemesterSelection();
    initializeAcademicYearSelection();
    showAcademicPeriodSummary();

    const pageUrl = new URL(window.location.href);
    if (pageUrl.searchParams.get('upload') === '1') {
        openUploadModal();
        pageUrl.searchParams.delete('upload');
        window.history.replaceState(null, '', pageUrl.pathname + pageUrl.search + pageUrl.hash);
    }

    if (userDepartmentId) {
        setTimeout(() => {
            toggleDepartment(userDepartmentId);
        }, 500);
    }
});