// Department tree functionality
function toggleDepartment(deptId) {
    const content = document.getElementById(`content-${deptId}`);
    const icon = document.getElementById(`icon-${deptId}`);
    const header = content ? content.previousElementSibling : null;
    
    if (!content || !icon) return;
    
    if (content.classList.contains('show')) {
        content.classList.remove('show');
        icon.classList.remove('rotated');
        if (header) header.classList.remove('active');
    } else {
        content.classList.add('show');
        icon.classList.add('rotated');
        if (header) header.classList.add('active');
        
        // Load category file counts for this department if not already loaded
        loadDepartmentCategories(deptId);
    }
}

function loadDepartmentCategories(deptId) {
    // Security check: Only load files for user's department
    if (deptId != userDepartmentId) {
        console.error('Access denied: Cannot load categories from different department');
        return;
    }
    
    // AJAX call to load category file counts
    fetch('handlers/category_counts.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ 
            department_id: deptId,
            user_department_id: userDepartmentId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Use the updated function from category.js that handles persistence
            if (typeof updateCategoryCounts === 'function') {
                updateCategoryCounts(deptId, data.counts || {});
            } else {
                // Fallback to the old method
                updateCategoryCountsLegacy(deptId, data.counts || {});
            }
        } else {
            console.error('Error loading category counts:', data.message);
            if (data.message.includes('Access denied')) {
                alert('Access denied: You can only view files from your department.');
            }
        }
    })
    .catch(error => {
        console.error('Error loading category counts:', error);
    });
}

// Legacy function for backwards compatibility
function updateCategoryCountsLegacy(deptId, counts) {
    Object.keys(fileCategories || {}).forEach(categoryKey => {
        const countElement = document.querySelector(`[data-category="${categoryKey}"] .category-count`);
        if (countElement) {
            const count = counts[categoryKey] || 0;
            countElement.textContent = `${count} file${count !== 1 ? 's' : ''}`;
        }
    });
}