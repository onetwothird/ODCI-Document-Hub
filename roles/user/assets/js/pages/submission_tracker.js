// Enhanced Submission Tracker JavaScript

document.addEventListener('DOMContentLoaded', function() {
    initializeSubmissionTracker();
});

function initializeSubmissionTracker() {
    setupFilterTabs();
    setupSearch();
    setupFilters();
    updateCategoryStats();
    animateStats();
    animateProgressCircle();
    setupPeriodSelector();
    setupEnhancedFilters();
    setupInteractionEffects();
    setupKeyboardShortcuts();
}

// Period Selector functionality
function setupPeriodSelector() {
    const yearSelect = document.getElementById('year-select');
    const semesterSelect = document.getElementById('semester-select');
    
    if (yearSelect) {
        yearSelect.addEventListener('change', updatePeriod);
    }
    
    if (semesterSelect) {
        semesterSelect.addEventListener('change', updatePeriod);
    }
}

function updatePeriod() {
    const year = document.getElementById('year-select').value;
    const semester = document.getElementById('semester-select').value;
    
    // Show loading state
    showNotification('Loading data for ' + year + '-' + (parseInt(year) + 1) + ' ' + semester + '...', 'info');
    
    // Redirect to reload page with new parameters
    const url = new URL(window.location.href);
    url.searchParams.set('year', year);
    url.searchParams.set('semester', semester);
    
    window.location.href = url.toString();
}

function selectPeriod(year, semester) {
    const url = new URL(window.location.href);
    url.searchParams.set('year', year);
    url.searchParams.set('semester', semester);
    
    window.location.href = url.toString();
}

// Filter tab functionality - FIXED
function setupFilterTabs() {
    const tabs = document.querySelectorAll('.filter-tab');
    
    tabs.forEach(tab => {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Get the onclick attribute and extract the section name
            const onclickAttr = this.getAttribute('onclick');
            if (onclickAttr) {
                const match = onclickAttr.match(/showSection\('([^']+)'\)/);
                if (match) {
                    showSection(match[1]);
                }
            }
        });
    });
}

function showSection(sectionName) {
    console.log('Showing section:', sectionName);
    
    // Remove active class from all tabs and sections
    document.querySelectorAll('.filter-tab').forEach(tab => {
        tab.classList.remove('active');
    });
    
    document.querySelectorAll('.content-section').forEach(section => {
        section.classList.remove('active');
    });
    
    // Add active class to clicked tab
    const clickedTab = document.querySelector(`.filter-tab[onclick*="${sectionName}"]`);
    if (clickedTab) {
        clickedTab.classList.add('active');
    }
    
    // Show corresponding section
    const targetSection = document.getElementById(`${sectionName}-section`);
    if (targetSection) {
        targetSection.classList.add('active');
        
        // Add entrance animation
        targetSection.style.opacity = '0';
        targetSection.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            targetSection.style.transition = 'all 0.3s ease';
            targetSection.style.opacity = '1';
            targetSection.style.transform = 'translateY(0)';
        }, 50);
    }
    
    // Update stats based on section
    updateSectionStats(sectionName);
    
    // Special handling for sections
    if (sectionName === 'recent') {
        animateActivityItems();
    }
}

// Search functionality
function setupSearch() {
    const searchInput = document.getElementById('submissionSearch');
    
    if (searchInput) {
        searchInput.addEventListener('input', debounce(handleAdvancedSearch, 300));
    }
}

function setupEnhancedFilters() {
    const searchInput = document.getElementById('submissionSearch');
    const categoryFilter = document.getElementById('categoryFilter');
    const semesterFilter = document.getElementById('semesterFilter');
    const academicYearFilter = document.getElementById('academicYearFilter');

    // Setup search functionality
    if (searchInput) {
        // Add clear button functionality
        const searchBox = searchInput.parentElement;
        searchBox.style.position = 'relative';
        
        let clearButton = searchBox.querySelector('.search-clear');
        if (!clearButton) {
            clearButton = document.createElement('button');
            clearButton.className = 'search-clear';
            clearButton.innerHTML = '<i class="bx bx-x"></i>';
            clearButton.style.cssText = `
                position: absolute;
                right: 10px;
                top: 50%;
                transform: translateY(-50%);
                background: none;
                border: none;
                color: #9ca3af;
                cursor: pointer;
                display: none;
                align-items: center;
                justify-content: center;
                width: 24px;
                height: 24px;
                border-radius: 50%;
                transition: all 0.2s ease;
            `;
            clearButton.addEventListener('click', clearAllFilters);
            searchBox.appendChild(clearButton);
        }
    }

    // Setup filter change handlers
    if (categoryFilter) {
        categoryFilter.addEventListener('change', applyAllFilters);
    }
    
    if (semesterFilter) {
        semesterFilter.addEventListener('change', applyAllFilters);
    }
    
    if (academicYearFilter) {
        academicYearFilter.addEventListener('change', applyAllFilters);
    }

    // Add filter indicators
    createFilterIndicators();
}

function handleAdvancedSearch() {
    const searchTerm = document.getElementById('submissionSearch').value.trim().toLowerCase();
    const clearButton = document.querySelector('.search-clear');
    
    // Show/hide clear button
    if (clearButton) {
        if (searchTerm.length > 0) {
            clearButton.style.display = 'flex';
        } else {
            clearButton.style.display = 'none';
        }
    }
    
    // Apply all filters including search
    applyAllFilters();
}

function applyAllFilters() {
    const searchTerm = document.getElementById('submissionSearch').value.trim().toLowerCase();
    const categoryFilter = document.getElementById('categoryFilter').value;
    const semesterFilter = document.getElementById('semesterFilter').value;
    const academicYearFilter = document.getElementById('academicYearFilter').value;
    
    // Get active section
    const activeSection = document.querySelector('.content-section.active');
    if (!activeSection) return;
    
    const sectionId = activeSection.id;
    
    // Apply filters based on active section
    switch(sectionId) {
        case 'overview-section':
            filterOverviewSection(searchTerm, categoryFilter);
            break;
        case 'pending-section':
            filterPendingSection(searchTerm, categoryFilter, semesterFilter, academicYearFilter);
            break;
        case 'completed-section':
            filterCompletedSection(searchTerm, categoryFilter, semesterFilter, academicYearFilter);
            break;
    }
    
    // Update filter indicators
    updateFilterIndicators();
    
    // Show results count
    showFilterResults();
}

function filterOverviewSection(searchTerm, categoryFilter) {
    const categoryCards = document.querySelectorAll('.category-overview-card');
    let visibleCount = 0;
    
    categoryCards.forEach((card, index) => {
        const cardCategory = card.dataset.category;
        const categoryName = card.querySelector('.category-info h4').textContent.toLowerCase();
        
        // Check if card matches filters
        const matchesSearch = !searchTerm || categoryName.includes(searchTerm);
        const matchesCategory = !categoryFilter || cardCategory === categoryFilter;
        
        const shouldShow = matchesSearch && matchesCategory;
        
        if (shouldShow) {
            visibleCount++;
            card.style.display = 'block';
            
            // Animate card appearance
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px) scale(0.9)';
            
            setTimeout(() => {
                card.style.transition = 'all 0.4s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0) scale(1)';
            }, index * 100);
            
            // Highlight search term
            if (searchTerm) {
                highlightSearchTerm(card.querySelector('.category-info h4'), searchTerm);
            } else {
                removeHighlight(card.querySelector('.category-info h4'));
            }
        } else {
            card.style.display = 'none';
        }
    });
    
    // Update section with results count
    updateSectionHeader('overview', visibleCount, categoryCards.length);
}

function filterPendingSection(searchTerm, categoryFilter, semesterFilter, academicYearFilter) {
    const pendingCards = document.querySelectorAll('.pending-card');
    let visibleCount = 0;
    
    pendingCards.forEach((card, index) => {
        const cardTitle = card.querySelector('.pending-info h4').textContent.toLowerCase();
        const semesterElement = card.querySelector('.detail-item span');
        const cardSemester = semesterElement ? semesterElement.textContent.toLowerCase() : '';
        
        // Check if card matches filters
        const matchesSearch = !searchTerm || cardTitle.includes(searchTerm);
        const matchesSemester = !semesterFilter || 
            (semesterFilter === 'first' && cardSemester.includes('first')) ||
            (semesterFilter === 'second' && cardSemester.includes('second'));
        
        const shouldShow = matchesSearch && matchesSemester;
        
        if (shouldShow) {
            visibleCount++;
            card.style.display = 'block';
            
            // Animate card appearance
            card.style.opacity = '0';
            card.style.transform = 'translateX(-20px)';
            
            setTimeout(() => {
                card.style.transition = 'all 0.3s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateX(0)';
            }, index * 150);
            
            // Highlight search term
            if (searchTerm) {
                highlightSearchTerm(card.querySelector('.pending-info h4'), searchTerm);
            } else {
                removeHighlight(card.querySelector('.pending-info h4'));
            }
        } else {
            card.style.display = 'none';
        }
    });
    
    updateSectionHeader('pending', visibleCount, pendingCards.length);
}

function filterCompletedSection(searchTerm, categoryFilter, semesterFilter, academicYearFilter) {
    const completedCards = document.querySelectorAll('.completed-card');
    let visibleCount = 0;
    
    completedCards.forEach((card, index) => {
        const cardTitle = card.querySelector('.completed-info h4').textContent.toLowerCase();
        const semesterItems = card.querySelectorAll('.semester-item');
        
        // Check if any semester items match the filters
        let hasMatchingSemester = !semesterFilter && !academicYearFilter;
        
        semesterItems.forEach(item => {
            const folderName = item.querySelector('.semester-info span').textContent.toLowerCase();
            
            const matchesSemester = !semesterFilter ||
                (semesterFilter === 'first' && folderName.includes('first semester')) ||
                (semesterFilter === 'second' && folderName.includes('second semester'));
                
            const matchesAcademicYear = !academicYearFilter ||
                folderName.includes(academicYearFilter.toLowerCase());
            
            if (matchesSemester && matchesAcademicYear) {
                hasMatchingSemester = true;
            }
        });
        
        // Find category for this card
        let cardCategory = '';
        if (window.fileCategories) {
            for (const [key, category] of Object.entries(window.fileCategories)) {
                if (cardTitle.includes(category.name.toLowerCase())) {
                    cardCategory = key;
                    break;
                }
            }
        }
        
        // Check if card matches all filters
        const matchesSearch = !searchTerm || cardTitle.includes(searchTerm);
        const matchesCategory = !categoryFilter || cardCategory === categoryFilter;
        
        const shouldShow = matchesSearch && matchesCategory && hasMatchingSemester;
        
        if (shouldShow) {
            visibleCount++;
            card.style.display = 'block';
            
            // Animate card appearance
            card.style.opacity = '0';
            card.style.transform = 'scale(0.9)';
            
            setTimeout(() => {
                card.style.transition = 'all 0.4s ease';
                card.style.opacity = '1';
                card.style.transform = 'scale(1)';
            }, index * 120);
            
            // Highlight search term
            if (searchTerm) {
                highlightSearchTerm(card.querySelector('.completed-info h4'), searchTerm);
            } else {
                removeHighlight(card.querySelector('.completed-info h4'));
            }
            
            // Filter semester items within the card
            if (semesterFilter || academicYearFilter) {
                semesterItems.forEach(item => {
                    const folderName = item.querySelector('.semester-info span').textContent.toLowerCase();
                    
                    const matchesSemester = !semesterFilter ||
                        (semesterFilter === 'first' && folderName.includes('first semester')) ||
                        (semesterFilter === 'second' && folderName.includes('second semester'));
                        
                    const matchesAcademicYear = !academicYearFilter ||
                        folderName.includes(academicYearFilter.toLowerCase());
                    
                    if (matchesSemester && matchesAcademicYear) {
                        item.style.display = 'flex';
                    } else {
                        item.style.display = 'none';
                    }
                });
            } else {
                // Show all semester items if no specific filters
                semesterItems.forEach(item => {
                    item.style.display = 'flex';
                });
            }
        } else {
            card.style.display = 'none';
        }
    });
    
    updateSectionHeader('completed', visibleCount, completedCards.length);
}

// Filter functionality
function setupFilters() {
    const categoryFilter = document.getElementById('categoryFilter');
    
    if (categoryFilter) {
        categoryFilter.addEventListener('change', applyAllFilters);
    }
}

// Update category statistics
function updateCategoryStats() {
    const categoryCards = document.querySelectorAll('.category-overview-card');
    
    categoryCards.forEach(card => {
        const categoryKey = card.dataset.category;
        
        // Add click handlers for category cards
        card.addEventListener('click', function() {
            showCategoryDetails(categoryKey);
        });
        
        // Add hover effects
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-8px) scale(1.02)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });
}

function showCategoryDetails(categoryKey) {
    // Switch to completed section and filter by category
    showSection('completed');
    
    setTimeout(() => {
        document.getElementById('categoryFilter').value = categoryKey;
        applyAllFilters();
    }, 100);
}

// Animate statistics on load
function animateStats() {
    const statNumbers = document.querySelectorAll('.stat-number');
    
    statNumbers.forEach((stat, index) => {
        const finalValue = parseInt(stat.textContent);
        let currentValue = 0;
        const increment = Math.ceil(finalValue / 30);
        
        setTimeout(() => {
            const counter = setInterval(() => {
                currentValue += increment;
                
                if (currentValue >= finalValue) {
                    stat.textContent = finalValue;
                    clearInterval(counter);
                } else {
                    stat.textContent = currentValue;
                }
            }, 50);
        }, index * 200);
    });
    
    // Animate progress bars
    const progressBars = document.querySelectorAll('.progress-fill');
    progressBars.forEach((bar, index) => {
        const width = bar.style.width;
        bar.style.width = '0%';
        
        setTimeout(() => {
            bar.style.transition = 'width 1s ease-in-out';
            bar.style.width = width;
        }, index * 200 + 500);
    });
}

// Animate progress circle
function animateProgressCircle() {
    const progressCircles = document.querySelectorAll('.progress-circle svg circle:last-child');
    
    progressCircles.forEach((circle, index) => {
        const dashArray = circle.style.strokeDasharray;
        const dashOffset = circle.style.strokeDashoffset;
        
        // Reset to full circle
        circle.style.strokeDashoffset = dashArray;
        
        setTimeout(() => {
            circle.style.transition = 'stroke-dashoffset 1.5s ease-in-out';
            circle.style.strokeDashoffset = dashOffset;
        }, 1000);
    });
}

// Animate activity items
function animateActivityItems() {
    const items = document.querySelectorAll('.activity-item');
    
    items.forEach((item, index) => {
        item.style.opacity = '0';
        item.style.transform = 'translateX(-20px)';
        
        setTimeout(() => {
            item.style.transition = 'all 0.3s ease';
            item.style.opacity = '1';
            item.style.transform = 'translateX(0)';
        }, index * 100);
    });
}

// Update section statistics based on active section
function updateSectionStats(sectionName) {
    console.log(`Viewing ${sectionName} section`);
    
    // Update any section-specific stats here
    if (sectionName === 'overview') {
        updateOverviewStats();
    } else if (sectionName === 'pending') {
        updatePendingStats();
    } else if (sectionName === 'completed') {
        updateCompletedStats();
    } else if (sectionName === 'recent') {
        updateRecentStats();
    }
}

function updateOverviewStats() {
    // Update overview specific statistics
    const categoryCards = document.querySelectorAll('.category-overview-card');
    categoryCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            card.style.transition = 'all 0.4s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
}

function updatePendingStats() {
    // Update pending specific statistics
    const pendingCards = document.querySelectorAll('.pending-card');
    pendingCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateX(-20px)';
        
        setTimeout(() => {
            card.style.transition = 'all 0.3s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateX(0)';
        }, index * 150);
    });
}

function updateCompletedStats() {
    // Update completed specific statistics
    const completedCards = document.querySelectorAll('.completed-card');
    completedCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'scale(0.9)';
        
        setTimeout(() => {
            card.style.transition = 'all 0.4s ease';
            card.style.opacity = '1';
            card.style.transform = 'scale(1)';
        }, index * 120);
    });
}

function updateRecentStats() {
    // Update recent activity specific statistics
    animateActivityItems();
}

// Upload and view functionality
function uploadForCategory(category, semester) {
    // Show loading state
    showNotification('Redirecting to upload for ' + category + '...', 'info');
    
    // Redirect to folders page with pre-selected category and semester
    const url = new URL('folders.php', window.location.href);
    url.searchParams.set('category', category);
    if (semester) {
        url.searchParams.set('semester', semester);
    }
    url.searchParams.set('action', 'upload');
    
    setTimeout(() => {
        window.location.href = url.toString();
    }, 500);
}

function viewCategoryFiles(category) {
    // Show loading state
    showNotification('Loading files for ' + category + '...', 'info');
    
    // Redirect to folders page with pre-selected category
    const url = new URL('folders.php', window.location.href);
    url.searchParams.set('category', category);
    url.searchParams.set('action', 'view');
    
    setTimeout(() => {
        window.location.href = url.toString();
    }, 500);
}

// File actions
function viewFile(fileId) {
    window.open(`view_file.php?id=${fileId}`, '_blank');
}

function downloadFile(fileId) {
    showNotification('Starting download...', 'info');
    window.location.href = `download_file.php?id=${fileId}`;
}

// Utility functions
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function highlightSearchTerm(element, searchTerm) {
    if (!element || !searchTerm) return;
    
    const originalText = element.getAttribute('data-original-text') || element.textContent;
    if (!element.getAttribute('data-original-text')) {
        element.setAttribute('data-original-text', originalText);
    }
    
    const regex = new RegExp(`(${escapeRegex(searchTerm)})`, 'gi');
    const highlightedText = originalText.replace(regex, '<mark style="background: #fef3c7; color: #92400e; padding: 1px 3px; border-radius: 3px; font-weight: 500;">$1</mark>');
    
    element.innerHTML = highlightedText;
}

function removeHighlight(element) {
    if (!element) return;
    
    const originalText = element.getAttribute('data-original-text');
    if (originalText) {
        element.textContent = originalText;
    }
}

function escapeRegex(string) {
    return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function updateSectionHeader(sectionName, visibleCount, totalCount) {
    const section = document.getElementById(`${sectionName}-section`);
    if (!section) return;
    
    const header = section.querySelector('.section-header');
    if (!header) return;
    
    let existingCounter = header.querySelector('.results-counter');
    if (!existingCounter) {
        existingCounter = document.createElement('span');
        existingCounter.className = 'results-counter';
        existingCounter.style.cssText = `
            background: #f3f4f6;
            color: #6d806bff;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            margin-left: 10px;
        `;
        header.querySelector('h3').appendChild(existingCounter);
    }
    
    if (visibleCount === totalCount) {
        existingCounter.textContent = `${totalCount} items`;
        existingCounter.style.background = '#f3f4f6';
        existingCounter.style.color = '#6b806dff';
    } else {
        existingCounter.textContent = `${visibleCount} of ${totalCount} items`;
        existingCounter.style.background = '#dbeafe';
        existingCounter.style.color = '#1dd826ff';
    }
}

function createFilterIndicators() {
    const filterSection = document.querySelector('.filter-section');
    if (!filterSection) return;
    
    let indicatorContainer = filterSection.querySelector('.active-filters');
    if (!indicatorContainer) {
        indicatorContainer = document.createElement('div');
        indicatorContainer.className = 'active-filters';
        indicatorContainer.style.cssText = `
            display: flex;
            gap: 8px;
            align-items: center;
            margin-top: 15px;
            flex-wrap: wrap;
            min-height: 24px;
        `;
        filterSection.appendChild(indicatorContainer);
    }
}

function updateFilterIndicators() {
    const container = document.querySelector('.active-filters');
    if (!container) return;
    
    container.innerHTML = '';
    
    const searchTerm = document.getElementById('submissionSearch').value.trim();
    const categoryFilter = document.getElementById('categoryFilter').value;
    const semesterFilter = document.getElementById('semesterFilter').value;
    const academicYearFilter = document.getElementById('academicYearFilter').value;
    
    const activeFilters = [];
    
    if (searchTerm) {
        activeFilters.push({ type: 'search', value: searchTerm, label: `Search: "${searchTerm}"` });
    }
    
    if (categoryFilter && window.fileCategories) {
        const categoryName = window.fileCategories[categoryFilter]?.name || categoryFilter;
        activeFilters.push({ type: 'category', value: categoryFilter, label: `Category: ${categoryName}` });
    }
    
    if (semesterFilter) {
        const semesterLabel = semesterFilter === 'first' ? 'First Semester' : 'Second Semester';
        activeFilters.push({ type: 'semester', value: semesterFilter, label: `Semester: ${semesterLabel}` });
    }
    
    if (academicYearFilter) {
        activeFilters.push({ type: 'year', value: academicYearFilter, label: `Year: ${academicYearFilter}` });
    }
    
    if (activeFilters.length === 0) {
        container.innerHTML = '<span class="no-filters" style="color: #9ca3af; font-size: 14px;">No active filters</span>';
        return;
    }
    
    // Add "Active filters:" label
    const label = document.createElement('span');
    label.textContent = 'Active filters:';
    label.style.cssText = 'color: #6b7280; font-size: 14px; font-weight: 500; margin-right: 8px;';
    container.appendChild(label);
    
    activeFilters.forEach(filter => {
        const indicator = document.createElement('div');
        indicator.className = `filter-indicator filter-${filter.type}`;
        indicator.style.cssText = `
            background: #3bf654ff;
            color: white;
            padding: 4px 12px;
            border-radius: 16px;
            font-size: 12px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
        `;
        
        indicator.innerHTML = `
            <span>${filter.label}</span>
            <i class='bx bx-x' style="font-size: 14px; opacity: 0.8;"></i>
        `;
        
        indicator.addEventListener('click', () => {
            removeFilter(filter.type);
        });
        
        indicator.addEventListener('mouseenter', () => {
            indicator.style.background = '#25eb3fff';
        });
        
        indicator.addEventListener('mouseleave', () => {
            indicator.style.background = '#3bf65aff';
        });
        
        container.appendChild(indicator);
    });
    
    // Add clear all button if there are multiple filters
    if (activeFilters.length > 1) {
        const clearAllBtn = document.createElement('button');
        clearAllBtn.className = 'clear-all-filters';
        clearAllBtn.textContent = 'Clear All';
        clearAllBtn.style.cssText = `
            background: #ef4444;
            color: white;
            border: none;
            padding: 4px 12px;
            border-radius: 16px;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        `;
        
        clearAllBtn.addEventListener('click', clearAllFilters);
        clearAllBtn.addEventListener('mouseenter', () => {
            clearAllBtn.style.background = '#dc2626';
        });
        clearAllBtn.addEventListener('mouseleave', () => {
            clearAllBtn.style.background = '#ef4444';
        });
        
        container.appendChild(clearAllBtn);
    }
}

function removeFilter(filterType) {
    switch(filterType) {
        case 'search':
            document.getElementById('submissionSearch').value = '';
            const clearButton = document.querySelector('.search-clear');
            if (clearButton) {
                clearButton.style.display = 'none';
            }
            break;
        case 'category':
            document.getElementById('categoryFilter').value = '';
            break;
        case 'semester':
            document.getElementById('semesterFilter').value = '';
            break;
        case 'year':
            document.getElementById('academicYearFilter').value = '';
            break;
    }
    
    applyAllFilters();
}

function clearAllFilters() {
    document.getElementById('submissionSearch').value = '';
    document.getElementById('categoryFilter').value = '';
    document.getElementById('semesterFilter').value = '';
    document.getElementById('academicYearFilter').value = '';
    
    const clearButton = document.querySelector('.search-clear');
    if (clearButton) {
        clearButton.style.display = 'none';
    }
    
    applyAllFilters();
    showNotification('All filters cleared', 'info');
}

function showFilterResults() {
    const activeSection = document.querySelector('.content-section.active');
    if (!activeSection) return;
    
    const visibleCards = activeSection.querySelectorAll('[style*="display: block"], [style=""], [style*="display: flex"]').length;
    const totalCards = activeSection.querySelectorAll('.category-overview-card, .pending-card, .completed-card').length;
    
    if (visibleCards === 0 && totalCards > 0) {
        showNoResultsMessage(activeSection);
    } else {
        hideNoResultsMessage(activeSection);
    }
}

function showNoResultsMessage(section) {
    let noResultsElement = section.querySelector('.no-filter-results');
    
    if (!noResultsElement) {
        noResultsElement = document.createElement('div');
        noResultsElement.className = 'no-filter-results';
        noResultsElement.style.cssText = `
            text-align: center;
            padding: 60px 20px;
            color: #6b807bff;
            background: #f9fafb;
            border-radius: 12px;
            margin: 20px 0;
        `;
        
        noResultsElement.innerHTML = `
            <i class='bx bx-search-alt-2' style="font-size: 48px; opacity: 0.5; margin-bottom: 16px; display: block;"></i>
            <h4 style="margin: 0 0 8px 0; color: #375147ff;">No results found</h4>
            <p style="margin: 0 0 16px 0;">Try adjusting your filters to see more results.</p>
            <button onclick="clearAllFilters()" style="
                background: #28a82fff;
                color: white;
                border: none;
                padding: 8px 16px;
                border-radius: 8px;
                cursor: pointer;
                font-weight: 500;
            ">Clear Filters</button>
        `;
        
        section.appendChild(noResultsElement);
    }
    
    noResultsElement.style.display = 'block';
}

function hideNoResultsMessage(section) {
    const noResultsElement = section.querySelector('.no-filter-results');
    if (noResultsElement) {
        noResultsElement.style.display = 'none';
    }
}

// Enhanced interaction effects
function setupInteractionEffects() {
    // Add ripple effect to clickable elements
    const clickableElements = document.querySelectorAll('.btn-upload, .btn-view, .filter-tab, .semester-card');
    
    clickableElements.forEach(element => {
        element.addEventListener('click', function(e) {
            createRippleEffect(e, this);
        });
    });
}

function createRippleEffect(event, element) {
    const ripple = document.createElement('span');
    const rect = element.getBoundingClientRect();
    const size = Math.max(rect.width, rect.height);
    const x = event.clientX - rect.left - size / 2;
    const y = event.clientY - rect.top - size / 2;
    
    ripple.style.cssText = `
        position: absolute;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.6);
        transform: scale(0);
        animation: ripple 0.6s linear;
        width: ${size}px;
        height: ${size}px;
        left: ${x}px;
        top: ${y}px;
        pointer-events: none;
    `;
    
    // Add ripple animation keyframes if not already added
    if (!document.querySelector('#ripple-styles')) {
        const style = document.createElement('style');
        style.id = 'ripple-styles';
        style.textContent = `
            @keyframes ripple {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
    }
    
    element.style.position = 'relative';
    element.style.overflow = 'hidden';
    element.appendChild(ripple);
    
    setTimeout(() => {
        if (element.contains(ripple)) {
            element.removeChild(ripple);
        }
    }, 600);
}

// Keyboard shortcuts
function setupKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + K for search focus
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            const searchInput = document.getElementById('submissionSearch');
            if (searchInput) {
                searchInput.focus();
            }
        }
        
        // Escape to clear search
        if (e.key === 'Escape') {
            clearAllFilters();
            const searchInput = document.getElementById('submissionSearch');
            if (searchInput) {
                searchInput.blur();
            }
        }
        
        // Number keys 1-3 for switching sections
        if (e.key >= '1' && e.key <= '3') {
            const sections = ['overview', 'pending', 'completed'];
            const sectionIndex = parseInt(e.key) - 1;
            if (sections[sectionIndex]) {
                showSection(sections[sectionIndex]);
            }
        }
    });
}

// Notification system for user feedback
function showNotification(message, type = 'success') {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.notification-toast');
    existingNotifications.forEach(notification => {
        notification.remove();
    });
    
    const notification = document.createElement('div');
    notification.className = `notification-toast ${type}`;
    
    const iconMap = {
        success: 'bx-check-circle',
        error: 'bx-error-circle',
        info: 'bx-info-circle',
        warning: 'bx-error'
    };
    
    const colorMap = {
        success: '#12c78bff',
        error: '#ef4444',
        info: '#179432ff',
        warning: '#f59e0b'
    };
    
    notification.innerHTML = `
        <i class='bx ${iconMap[type] || 'bx-check-circle'}'></i>
        <span>${message}</span>
    `;
    
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${colorMap[type] || '#10b981'};
        color: white;
        padding: 16px 20px;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        z-index: 10000;
        font-family: 'Poppins', sans-serif;
        font-size: 14px;
        font-weight: 500;
        transform: translateX(400px);
        transition: transform 0.3s ease;
        display: flex;
        align-items: center;
        gap: 10px;
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.transform = 'translateX(0)';
    }, 100);
    
    setTimeout(() => {
        notification.style.transform = 'translateX(400px)';
        setTimeout(() => {
            if (document.body.contains(notification)) {
                document.body.removeChild(notification);
            }
        }, 300);
    }, 1800);
}

// Refresh data functionality
function refreshSubmissionData() {
    showNotification('Refreshing submission data...', 'info');
    
    // Add loading animation to stats
    const statCards = document.querySelectorAll('.stat-card');
    statCards.forEach(card => {
        card.style.opacity = '0.6';
        card.style.transform = 'scale(0.98)';
    });
    
    // Simulate data refresh
    setTimeout(() => {
        window.location.reload();
    }, 1000);
}

// Get file icon based on extension
function getFileIcon(filename) {
    const ext = strtolower(pathinfo(filename, 'PATHINFO_EXTENSION'));
    
    const iconMap = {
        'pdf': 'bxs-file-pdf',
        'doc': 'bxs-file-doc',
        'docx': 'bxs-file-doc',
        'xls': 'bxs-spreadsheet',
        'xlsx': 'bxs-spreadsheet',
        'ppt': 'bxs-file-blank',
        'pptx': 'bxs-file-blank',
        'jpg': 'bxs-file-image',
        'jpeg': 'bxs-file-image',
        'png': 'bxs-file-image',
        'gif': 'bxs-file-image',
        'txt': 'bxs-file-txt',
        'zip': 'bxs-file-archive',
        'rar': 'bxs-file-archive',
        'mp4': 'bxs-videos',
        'avi': 'bxs-videos',
        'mp3': 'bxs-music',
        'wav': 'bxs-music'
    };
    
    return iconMap[ext] || 'bxs-file';
}

// Format file size
function formatFileSize(bytes, precision = 2) {
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for (let i = 0; bytes > 1024 && i < units.length - 1; i++) {
        bytes /= 1024;
    }
    
    return Math.round(bytes * Math.pow(10, precision)) / Math.pow(10, precision) + ' ' + units[i || 0];
}

// Performance optimization
function optimizePerformance() {
    // Lazy load non-critical elements
    const lazyElements = document.querySelectorAll('.category-overview-card, .completed-card, .activity-item');
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
                observer.unobserve(entry.target);
            }
        });
    }, {
        threshold: 0.1,
        rootMargin: '50px'
    });
    
    lazyElements.forEach(element => {
        element.style.opacity = '0';
        element.style.transform = 'translateY(20px)';
        element.style.transition = 'all 0.3s ease';
        observer.observe(element);
    });
}

// Initialize performance optimizations
setTimeout(optimizePerformance, 1000);

// Export functions for global access
window.showSection = showSection;
window.uploadForCategory = uploadForCategory;
window.viewCategoryFiles = viewCategoryFiles;
window.selectPeriod = selectPeriod;
window.updatePeriod = updatePeriod;
window.applyAllFilters = applyAllFilters;
window.clearAllFilters = clearAllFilters;
window.removeFilter = removeFilter;
window.refreshSubmissionData = refreshSubmissionData;
window.showNotification = showNotification;
window.viewFile = viewFile;
window.downloadFile = downloadFile;
