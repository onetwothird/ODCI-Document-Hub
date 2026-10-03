document.addEventListener('DOMContentLoaded', function() {
    // Handle fund type selection
    const fundTypeSelect = document.getElementById('fund_type');
    const bankGroup = document.getElementById('bank_group');
    const officeGroup = document.getElementById('office_group');
    
    if (fundTypeSelect) {
        fundTypeSelect.addEventListener('change', function() {
            const fundType = this.value;
            
            if (fundType === 'General Fund') {
                bankGroup.style.display = 'block';
                officeGroup.style.display = 'block';
            } else {
                bankGroup.style.display = 'block';
                officeGroup.style.display = 'none';
            }
        });
    }
    
    // Handle office selection for sub-offices
    const officeSelect = document.getElementById('office_id');
    const subOfficeGroup = document.getElementById('sub_office_group');
    
    if (officeSelect) {
        officeSelect.addEventListener('change', function() {
            const officeId = this.value;
            
            if (officeId) {
                fetch(`includes/get_sub_offices.php?office_id=${officeId}`)
                    .then(response => response.json())
                    .then(data => {
                        const subOfficeSelect = document.getElementById('sub_office_id');
                        subOfficeSelect.innerHTML = '<option value="">Select Sub Office (Optional)</option>';
                        
                        if (data.length > 0) {
                            data.forEach(office => {
                                const option = document.createElement('option');
                                option.value = office.id;
                                option.textContent = office.name;
                                subOfficeSelect.appendChild(option);
                            });
                            subOfficeGroup.style.display = 'block';
                        } else {
                            subOfficeGroup.style.display = 'none';
                        }
                    });
            } else {
                subOfficeGroup.style.display = 'none';
            }
        });
    }
    
    // Handle expense type selection
    const expenseTypeSelect = document.getElementById('expense_type');
    const taxGroup = document.getElementById('tax_group');
    const totalGroup = document.getElementById('total_group');
    
    if (expenseTypeSelect) {
        expenseTypeSelect.addEventListener('change', function() {
            const expenseType = this.value;
            
            if (expenseType === 'Cash Advance') {
                taxGroup.style.display = 'none';
                totalGroup.style.display = 'none';
                document.getElementById('tax').value = '0';
                updateTotal();
            } else {
                taxGroup.style.display = 'block';
                totalGroup.style.display = 'block';
            }
        });
    }
    
    // Calculate total automatically
    const amountInput = document.getElementById('amount');
    const taxInput = document.getElementById('tax');
    
    if (amountInput && taxInput) {
        amountInput.addEventListener('input', updateTotal);
        taxInput.addEventListener('input', updateTotal);
    }
    
    function updateTotal() {
        const amount = parseFloat(amountInput.value) || 0;
        const tax = parseFloat(taxInput.value) || 0;
        const total = amount + tax;
        
        const totalInput = document.getElementById('total');
        if (totalInput) {
            totalInput.value = total.toFixed(2);
        }
    }
});





const allSideMenu = document.querySelectorAll('#sidebar .side-menu.top li a');

allSideMenu.forEach(item=> {
	const li = item.parentElement;

	item.addEventListener('click', function () {
		allSideMenu.forEach(i=> {
			i.parentElement.classList.remove('active');
		})
		li.classList.add('active');
	})
});




// TOGGLE SIDEBAR
const menuBar = document.querySelector('#content nav .bx.bx-menu');
const sidebar = document.getElementById('sidebar');

menuBar.addEventListener('click', function () {
	sidebar.classList.toggle('hide');
})







const searchButton = document.querySelector('#content nav form .form-input button');
const searchButtonIcon = document.querySelector('#content nav form .form-input button .bx');
const searchForm = document.querySelector('#content nav form');

searchButton.addEventListener('click', function (e) {
	if(window.innerWidth < 576) {
		e.preventDefault();
		searchForm.classList.toggle('show');
		if(searchForm.classList.contains('show')) {
			searchButtonIcon.classList.replace('bx-search', 'bx-x');
		} else {
			searchButtonIcon.classList.replace('bx-x', 'bx-search');
		}
	}
})





if(window.innerWidth < 768) {
	sidebar.classList.add('hide');
} else if(window.innerWidth > 576) {
	searchButtonIcon.classList.replace('bx-x', 'bx-search');
	searchForm.classList.remove('show');
}


window.addEventListener('resize', function () {
	if(this.innerWidth > 576) {
		searchButtonIcon.classList.replace('bx-x', 'bx-search');
		searchForm.classList.remove('show');
	}
})



const switchMode = document.getElementById('switch-mode');

switchMode.addEventListener('change', function () {
	if(this.checked) {
		document.body.classList.add('dark');
	} else {
		document.body.classList.remove('dark');
	}
})