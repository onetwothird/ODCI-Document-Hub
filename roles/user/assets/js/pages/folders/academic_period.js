function populateAcademicYears() {
    const academicYearSelect = document.querySelector('select[name="academic_year"]');
    if (!academicYearSelect) return;

    // Clear existing options except the first one
    academicYearSelect.innerHTML = '<option value="">Select academic year...</option>';

    // Generate academic years starting from 2019-2020 up to current year + 3
    const currentYear = new Date().getFullYear();
    const currentMonth = new Date().getMonth() + 1; // 1-12

    // Determine the current academic year based on the month
    let currentAcademicStartYear;
    if (currentMonth >= 8) { // August or later
        currentAcademicStartYear = currentYear;
    } else {
        currentAcademicStartYear = currentYear - 1;
    }

    // Start from 2019 and go up to current year + 3
    const startYear = 2019;
    const endYear = currentYear + 3;

    // Generate academic year options
    for (let year = startYear; year <= endYear; year++) {
        const academicYear = `${year}-${year + 1}`;
        
        const option = document.createElement('option');
        option.value = academicYear;
        option.textContent = `${academicYear} Academic Year`;
        
        // Mark current academic year as selected by default
        if (year === currentAcademicStartYear) {
            option.selected = true;
        }
        
        academicYearSelect.appendChild(option);
    }
}

// Function to get current academic year automatically
function getCurrentAcademicYear() {
    const currentYear = new Date().getFullYear();
    const currentMonth = new Date().getMonth() + 1; // 1-12

    // If we're in August or later, it's the new academic year
    // Otherwise, we're still in the previous academic year
    if (currentMonth >= 8) {
        return `${currentYear}-${currentYear + 1}`;
    } else {
        return `${currentYear - 1}-${currentYear}`;
    }
}

// Function to get current semester automatically
function getCurrentSemester() {
    const currentMonth = new Date().getMonth() + 1; // 1-12

    // First semester typically runs from August to December
    // Second semester typically runs from January to May
    // June-July is usually summer/break
    if (currentMonth >= 8 && currentMonth <= 12) {
        return 'first';
    } else if (currentMonth >= 1 && currentMonth <= 5) {
        return 'second';
    } else {
        // Default to first semester during summer months
        return 'first';
    }
}

// Function to auto-select current semester
function autoSelectCurrentSemester() {
    const currentSemester = getCurrentSemester();
    const semesterRadio = document.querySelector(`input[name="semester"][value="${currentSemester}"]`);
    if (semesterRadio) {
        semesterRadio.checked = true;
    }
}

// Function to validate academic year and semester combination
function validateAcademicPeriod() {
    const academicYearSelect = document.querySelector('select[name="academic_year"]');
    const semesterInputs = document.querySelectorAll('input[name="semester"]');
    
    const academicYear = academicYearSelect?.value;
    const semester = Array.from(semesterInputs).find(input => input.checked)?.value;
    
    if (!academicYear) {
        alert('Please select an academic year.');
        return false;
    }
    
    if (!semester) {
        alert('Please select a semester.');
        return false;
    }
    
    // Validate academic year format
    const academicYearPattern = /^\d{4}-\d{4}$/;
    if (!academicYearPattern.test(academicYear)) {
        alert('Invalid academic year format. Please select a valid academic year.');
        return false;
    }
    
    // Additional validation: check if the years are consecutive
    const [startYear, endYear] = academicYear.split('-').map(Number);
    if (endYear - startYear !== 1) {
        alert('Invalid academic year. The end year should be exactly one year after the start year.');
        return false;
    }
    
    return true;
}

// Function to add visual feedback for semester selection
function initializeSemesterSelection() {
    const semesterInputs = document.querySelectorAll('input[name="semester"]');
    
    semesterInputs.forEach(input => {
        input.addEventListener('change', function() {
            // Remove active class from all labels
            semesterInputs.forEach(radio => {
                const label = radio.closest('label');
                if (label) {
                    label.style.borderColor = '#e5e7eb';
                    label.style.background = 'white';
                    label.style.transform = 'scale(1)';
                }
            });
            
            // Add active styling to selected option
            if (this.checked) {
                const label = this.closest('label');
                if (label) {
                    label.style.borderColor = '#10b981';
                    label.style.background = '#f0fdf4';
                    label.style.transform = 'scale(1.02)';
                }
            }
        });
    });
}

// Function to add visual feedback for academic year selection
function initializeAcademicYearSelection() {
    const academicYearSelect = document.querySelector('select[name="academic_year"]');
    
    if (academicYearSelect) {
        academicYearSelect.addEventListener('change', function() {
            // Visual feedback when academic year is selected
            if (this.value) {
                this.style.borderColor = '#10b981';
                this.style.boxShadow = '0 0 0 3px rgba(16, 185, 129, 0.1)';
            } else {
                this.style.borderColor = '#e5e7eb';
                this.style.boxShadow = 'none';
            }
        });
    }
}

// Function to show academic period summary
function showAcademicPeriodSummary() {
    const academicYearSelect = document.querySelector('select[name="academic_year"]');
    const semesterInputs = document.querySelectorAll('input[name="semester"]');
    
    function updateSummary() {
        const academicYear = academicYearSelect?.value;
        const semester = Array.from(semesterInputs).find(input => input.checked)?.value;
        
        // Find or create summary element
        let summaryElement = document.getElementById('academicPeriodSummary');
        if (!summaryElement) {
            summaryElement = document.createElement('div');
            summaryElement.id = 'academicPeriodSummary';
            summaryElement.style.cssText = `
                margin-top: 12px;
                padding: 12px;
                background: rgba(16, 185, 129, 0.1);
                border-radius: 8px;
                border-left: 4px solid #10b981;
                font-size: 12px;
                color: #065f46;
            `;
            
            // Insert after semester selection
            const semesterDiv = document.querySelector('input[name="semester"]').closest('div').closest('div');
            if (semesterDiv) {
                semesterDiv.appendChild(summaryElement);
            }
        }
        
        if (academicYear && semester) {
            const semesterText = semester === 'first' ? 'First Semester' : 'Second Semester';
            summaryElement.innerHTML = `
                <i class='bx bx-info-circle' style="margin-right: 6px;"></i>
                Files will be organized under: <strong>${academicYear} - ${semesterText}</strong>
            `;
            summaryElement.style.display = 'block';
        } else {
            summaryElement.style.display = 'none';
        }
    }
    
    // Add event listeners
    if (academicYearSelect) {
        academicYearSelect.addEventListener('change', updateSummary);
    }
    
    semesterInputs.forEach(input => {
        input.addEventListener('change', updateSummary);
    });
    
    // Initial update
    updateSummary();
}

