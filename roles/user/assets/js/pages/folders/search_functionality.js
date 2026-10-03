// Department search functionality
function initializeDepartmentSearch() {
    const departmentSearch = document.getElementById('departmentSearch');
    if (!departmentSearch) return;
    if (departmentSearch.dataset.searchInitialized === 'true') return;

    const departments = Array.from(document.querySelectorAll('.department-card'));
    if (!departments.length) return;
    departmentSearch.dataset.searchInitialized = 'true';

    const emptyState = document.createElement('p');
    emptyState.className = 'department-search-empty';
    emptyState.setAttribute('role', 'status');
    emptyState.textContent = 'No departments match your search.';
    emptyState.hidden = true;
    departments[0].parentElement.insertBefore(emptyState, departments[0]);

    departmentSearch.addEventListener('input', function () {
        const searchTerm = departmentSearch.value.trim().toLocaleLowerCase();
        let visibleDepartments = 0;

        departments.forEach(department => {
            const name = department.querySelector('.department-name')?.textContent || '';
            const code = department.querySelector('.department-code')?.textContent || '';
            const matches = `${name} ${code}`.toLocaleLowerCase().includes(searchTerm);

            department.hidden = !matches;
            if (matches) visibleDepartments++;
        });

        emptyState.hidden = visibleDepartments > 0;
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeDepartmentSearch, { once: true });
} else {
    initializeDepartmentSearch();
}