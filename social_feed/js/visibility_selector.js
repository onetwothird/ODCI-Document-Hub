class VisibilitySelector {
    constructor() {
        this.selectedDepartments = [];
        this.currentVisibility = 'public';
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.setupClickOutside();
    }

    setupEventListeners() {
        // Main visibility button
        const visibilityBtn = document.getElementById('visibilityBtn');
        if (visibilityBtn) {
            visibilityBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                this.toggleVisibilityDropdown();
            });
        }

        // Visibility options
        const visibilityOptions = document.querySelectorAll('.visibility-option');
        visibilityOptions.forEach(option => {
            option.addEventListener('click', (e) => {
                e.stopPropagation();
                const visibility = option.dataset.visibility;
                
                if (visibility === 'department') {
                    this.showDepartmentSelector();
                } else {
                    this.selectVisibility(visibility);
                }
            });
        });

        // Department selector actions
        this.setupDepartmentActions();
    }

    setupDepartmentActions() {
        // Select all button
        const selectAllBtn = document.querySelector('.select-all-btn');
        if (selectAllBtn) {
            selectAllBtn.addEventListener('click', () => {
                this.toggleSelectAllDepartments();
            });
        }

        // Confirm button
        const confirmBtn = document.querySelector('.department-confirm-btn');
        if (confirmBtn) {
            confirmBtn.addEventListener('click', () => {
                this.confirmDepartmentSelection();
            });
        }
    }

    setupClickOutside() {
        document.addEventListener('click', (e) => {
            const visibilitySelector = document.querySelector('.visibility-selector');
            if (visibilitySelector && !visibilitySelector.contains(e.target)) {
                this.hideAllDropdowns();
            }
        });
    }

    toggleVisibilityDropdown() {
        const dropdown = document.getElementById('visibilityDropdown');
        const departmentSelector = document.getElementById('departmentSelector');
        
        // Hide department selector if open
        if (departmentSelector) {
            departmentSelector.classList.remove('show');
        }
        
        // Toggle visibility dropdown
        if (dropdown) {
            dropdown.classList.toggle('show');
        }
    }

    showDepartmentSelector() {
        const visibilityDropdown = document.getElementById('visibilityDropdown');
        const departmentSelector = document.getElementById('departmentSelector');
        
        // Hide visibility dropdown
        if (visibilityDropdown) {
            visibilityDropdown.classList.remove('show');
        }
        
        // Show department selector
        if (departmentSelector) {
            departmentSelector.classList.add('show');
        }
    }

    showVisibilityDropdown() {
        const visibilityDropdown = document.getElementById('visibilityDropdown');
        const departmentSelector = document.getElementById('departmentSelector');
        
        // Hide department selector
        if (departmentSelector) {
            departmentSelector.classList.remove('show');
        }
        
        // Show visibility dropdown
        if (visibilityDropdown) {
            visibilityDropdown.classList.add('show');
        }
    }

    hideAllDropdowns() {
        const visibilityDropdown = document.getElementById('visibilityDropdown');
        const departmentSelector = document.getElementById('departmentSelector');
        
        if (visibilityDropdown) {
            visibilityDropdown.classList.remove('show');
        }
        
        if (departmentSelector) {
            departmentSelector.classList.remove('show');
        }
    }

    selectVisibility(visibility) {
        this.currentVisibility = visibility;
        
        // Update button display
        const visibilityBtn = document.getElementById('visibilityBtn');
        const visibilityText = document.getElementById('visibilityText');
        const visibilityInput = document.getElementById('postVisibility');
        
        if (visibilityBtn && visibilityText && visibilityInput) {
            const icon = visibilityBtn.querySelector('i');
            
            switch (visibility) {
                case 'public':
                    if (icon) icon.className = 'bx bx-globe';
                    visibilityText.textContent = 'Everyone';
                    break;
                case 'custom':
                    if (icon) icon.className = 'bx bx-group';
                    visibilityText.textContent = 'Specific Users';
                    break;
                default:
                    if (icon) icon.className = 'bx bx-globe';
                    visibilityText.textContent = 'Everyone';
            }
            
            visibilityInput.value = visibility;
        }
        
        // Clear department selection if not department visibility
        if (visibility !== 'department') {
            this.selectedDepartments = [];
            this.updateSelectedDepartmentsDisplay();
        }
        
        this.hideAllDropdowns();
    }

    toggleDepartment(deptCode, checkboxElement) {
        const index = this.selectedDepartments.indexOf(deptCode);
        const departmentOption = checkboxElement.closest('.department-option');
        
        if (index === -1) {
            // Add department
            this.selectedDepartments.push(deptCode);
            checkboxElement.classList.add('checked');
            departmentOption.classList.add('selected');
        } else {
            // Remove department
            this.selectedDepartments.splice(index, 1);
            checkboxElement.classList.remove('checked');
            departmentOption.classList.remove('selected');
        }
        
        this.updateDepartmentButtons();
        console.log('Selected departments:', this.selectedDepartments);
    }

    toggleSelectAllDepartments() {
        const allDepartments = ['TED', 'MD', 'ITD', 'FASD', 'ASD', 'NSTP', 'Others'];
        const selectAllBtn = document.querySelector('.select-all-btn');
        const selectAllText = document.getElementById('selectAllText');
        
        if (this.selectedDepartments.length === allDepartments.length) {
            // Unselect all
            this.selectedDepartments = [];
            this.updateAllCheckboxes(false);
            if (selectAllText) selectAllText.textContent = 'Select All';
        } else {
            // Select all
            this.selectedDepartments = [...allDepartments];
            this.updateAllCheckboxes(true);
            if (selectAllText) selectAllText.textContent = 'Unselect All';
        }
        
        this.updateDepartmentButtons();
    }

    updateAllCheckboxes(checked) {
        const checkboxes = document.querySelectorAll('.department-checkbox');
        const options = document.querySelectorAll('.department-option');
        
        checkboxes.forEach(checkbox => {
            if (checked) {
                checkbox.classList.add('checked');
            } else {
                checkbox.classList.remove('checked');
            }
        });
        
        options.forEach(option => {
            if (checked) {
                option.classList.add('selected');
            } else {
                option.classList.remove('selected');
            }
        });
    }

    updateDepartmentButtons() {
        const confirmBtn = document.querySelector('.department-confirm-btn');
        const selectAllBtn = document.querySelector('.select-all-btn');
        const selectAllText = document.getElementById('selectAllText');
        const allDepartments = ['TED', 'MD', 'ITD', 'FASD', 'ASD', 'NSTP', 'Others'];
        
        // Update confirm button
        if (confirmBtn) {
            confirmBtn.disabled = this.selectedDepartments.length === 0;
        }
        
        // Update select all button text
        if (selectAllText) {
            selectAllText.textContent = this.selectedDepartments.length === allDepartments.length ? 
                'Unselect All' : 'Select All';
        }
    }

    confirmDepartmentSelection() {
        if (this.selectedDepartments.length === 0) {
            window.utils?.showNotification('Please select at least one department', 'error');
            return;
        }
        
        this.currentVisibility = 'department';
        
        // Update button display
        const visibilityBtn = document.getElementById('visibilityBtn');
        const visibilityText = document.getElementById('visibilityText');
        const visibilityInput = document.getElementById('postVisibility');
        const departmentsInput = document.getElementById('selectedDepartments');
        
        if (visibilityBtn && visibilityText && visibilityInput) {
            const icon = visibilityBtn.querySelector('i');
            if (icon) icon.className = 'bx bx-buildings';
            
            const count = this.selectedDepartments.length;
            visibilityText.textContent = count === 1 ? 
                `${this.selectedDepartments[0]} Department` : 
                `${count} Departments`;
            
            visibilityInput.value = 'department';
        }
        
        // Store selected departments
        if (departmentsInput) {
            departmentsInput.value = JSON.stringify(this.selectedDepartments);
        }
        
        this.updateSelectedDepartmentsDisplay();
        this.hideAllDropdowns();
        
        window.utils?.showNotification(
            `Selected ${this.selectedDepartments.length} department(s)`, 
            'success'
        );
    }

    updateSelectedDepartmentsDisplay() {
        const display = document.getElementById('selectedDepartmentsDisplay');
        
        if (!display) return;
        
        if (this.currentVisibility === 'department' && this.selectedDepartments.length > 0) {
            display.textContent = `Posting to: ${this.selectedDepartments.join(', ')}`;
            display.classList.add('show');
        } else {
            display.classList.remove('show');
        }
    }

    // Method to get current selection for form submission
    getVisibilityData() {
        return {
            visibility: this.currentVisibility,
            selectedDepartments: this.currentVisibility === 'department' ? this.selectedDepartments : []
        };
    }

    // Reset to default state
    reset() {
        this.selectedDepartments = [];
        this.currentVisibility = 'public';
        this.selectVisibility('public');
        this.updateAllCheckboxes(false);
        this.updateSelectedDepartmentsDisplay();
    }
}

// Global functions for HTML onclick handlers
function toggleDepartment(deptCode, checkboxElement) {
    if (window.visibilitySelector) {
        window.visibilitySelector.toggleDepartment(deptCode, checkboxElement);
    }
}

function showVisibilityDropdown() {
    if (window.visibilitySelector) {
        window.visibilitySelector.showVisibilityDropdown();
    }
}

function toggleSelectAllDepartments() {
    if (window.visibilitySelector) {
        window.visibilitySelector.toggleSelectAllDepartments();
    }
}

function confirmDepartmentSelection() {
    if (window.visibilitySelector) {
        window.visibilitySelector.confirmDepartmentSelection();
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    window.visibilitySelector = new VisibilitySelector();
});
