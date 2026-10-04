/* Faculty Staff management - interactions for roles/admin/view-faculty-staff.php
 *
 * Fixes over the previous version:
 *  - every listener is bound from DOMContentLoaded instead of at parse time, so
 *    nothing depended on the script tag sitting last in the body;
 *  - message + bulk-message posting sets the JSON content type, adds the CSRF
 *    token (the single-message form omitted it and was rejected), and surfaces
 *    the server's actual error instead of a generic string;
 *  - "View Stats" renders a real modal instead of window.alert();
 *  - CSV export no longer throws when a card is missing an email meta row, and
 *    it exports every selected card rather than only the ones matching the
 *    current filter;
 *  - select-all state is computed against visible cards only.
 */

const facultyStaff = {
    selected: new Set(),
    statsFacultyId: null
};

document.addEventListener('DOMContentLoaded', function () {
    initializeFilters();
    initializeProgressAnimations();
    initializeBulkActions();
    initializeViewToggle();
    setupKeyboardShortcuts();
    bindModalDismissal();

    // Populate the result counter on first paint.
    filterFaculty();
});

function initializeFilters() {
    const facultySearch = document.getElementById('facultySearch');
    const completionFilter = document.getElementById('completionFilter');
    const activityFilter = document.getElementById('activityFilter');
    const verificationFilter = document.getElementById('verificationFilter');

    [facultySearch, completionFilter, activityFilter, verificationFilter].forEach(function (element) {
        if (element) {
            element.addEventListener('input', filterFaculty);
            element.addEventListener('change', filterFaculty);
        }
    });
}

function isCardVisible(card) {
    return card.style.display !== 'none';
}

function filterFaculty() {
    const searchInput = document.getElementById('facultySearch');
    const searchTerm = (searchInput?.value || '').toLowerCase().trim();
    const completionValue = document.getElementById('completionFilter')?.value || '';
    const activityValue = document.getElementById('activityFilter')?.value || '';
    const verificationValue = document.getElementById('verificationFilter')?.value || '';

    const facultyCards = Array.from(document.querySelectorAll('.faculty-card'));
    let visibleCount = 0;

    facultyCards.forEach(function (card) {
        const name = card.getAttribute('data-name') || '';
        const position = card.getAttribute('data-position') || '';
        const employeeId = card.getAttribute('data-employee-id') || '';
        const completion = card.getAttribute('data-completion') || '';
        const activity = card.getAttribute('data-activity') || '';
        const verification = card.getAttribute('data-verification') || '';

        let showCard = true;

        if (searchTerm && !name.includes(searchTerm) &&
            !position.includes(searchTerm) && !employeeId.includes(searchTerm)) {
            showCard = false;
        }
        if (completionValue && completion !== completionValue) {
            showCard = false;
        }
        if (activityValue && activity !== activityValue) {
            showCard = false;
        }
        if (verificationValue && verification !== verificationValue) {
            showCard = false;
        }

        card.style.display = showCard ? 'flex' : 'none';
        if (showCard) {
            visibleCount++;
        }
    });

    updateNoResultsMessage(visibleCount);
    updateFilterFooter(visibleCount, facultyCards.length);
    updateSelectAllState();
}

/** Result count + reset affordance under the filter row. */
function updateFilterFooter(visibleCount, totalCount) {
    const resultCount = document.getElementById('resultCount');
    const resetButton = document.getElementById('resetFilters');
    const searchInput = document.getElementById('facultySearch');
    const clearButton = document.getElementById('searchClear');

    if (clearButton) {
        clearButton.hidden = !searchInput?.value;
    }

    const filtersActive = Boolean(
        searchInput?.value ||
        document.getElementById('completionFilter')?.value ||
        document.getElementById('activityFilter')?.value ||
        document.getElementById('verificationFilter')?.value
    );

    if (resultCount) {
        resultCount.textContent = filtersActive
            ? `Showing ${visibleCount} of ${totalCount} faculty`
            : `${totalCount} faculty member${totalCount === 1 ? '' : 's'}`;
    }

    if (resetButton) {
        resetButton.hidden = !filtersActive;
    }
}

function clearSearch() {
    const searchInput = document.getElementById('facultySearch');
    if (!searchInput) {
        return;
    }
    searchInput.value = '';
    searchInput.focus();
    filterFaculty();
}

function resetFilters() {
    ['facultySearch', 'completionFilter', 'activityFilter', 'verificationFilter'].forEach(function (id) {
        const element = document.getElementById(id);
        if (element) {
            element.value = '';
        }
    });
    filterFaculty();
}

function updateNoResultsMessage(visibleCount) {
    const list = document.getElementById('facultyList');
    if (!list) {
        return;
    }

    let noResultsMsg = list.querySelector('.no-results');

    if (visibleCount === 0 && document.querySelectorAll('.faculty-card').length > 0) {
        if (!noResultsMsg) {
            noResultsMsg = document.createElement('div');
            noResultsMsg.className = 'no-results';
            noResultsMsg.innerHTML = `
                <i class='bx bx-search'></i>
                <h3>No Results Found</h3>
                <p>Try adjusting your search criteria or filters to find faculty members.</p>
            `;
            list.appendChild(noResultsMsg);
        }
    } else if (noResultsMsg) {
        noResultsMsg.remove();
    }
}

function initializeProgressAnimations() {
    const progressBars = document.querySelectorAll('.progress-fill');

    if (!('IntersectionObserver' in window)) {
        return;
    }

    const observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                const bar = entry.target;
                const width = bar.style.width;
                bar.style.width = '0%';
                setTimeout(function () {
                    bar.style.width = width;
                }, 100);
                observer.unobserve(bar);
            }
        });
    });

    progressBars.forEach(function (bar) { observer.observe(bar); });
}

function initializeBulkActions() {
    const selectAllCheckbox = document.getElementById('selectAll');
    if (!selectAllCheckbox) {
        return;
    }

    selectAllCheckbox.addEventListener('change', function () {
        document.querySelectorAll('.faculty-checkbox').forEach(function (checkbox) {
            const card = checkbox.closest('.faculty-card');
            checkbox.checked = this.checked && isCardVisible(card);
            updateSelectedFaculty(checkbox);
        });
        updateSelectAllState();
    });

    document.querySelectorAll('.faculty-checkbox').forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
            updateSelectedFaculty(this);
            updateSelectAllState();
        });
    });
}

function updateSelectedFaculty(checkbox) {
    const facultyId = checkbox.value;

    if (checkbox.checked) {
        facultyStaff.selected.add(facultyId);
    } else {
        facultyStaff.selected.delete(facultyId);
    }

    const counter = document.getElementById('selectedCount');
    if (counter) {
        counter.textContent = facultyStaff.selected.size;
    }
    document.getElementById('bulkActions')?.classList.toggle('active', facultyStaff.selected.size > 0);
}

function updateSelectAllState() {
    const selectAllCheckbox = document.getElementById('selectAll');
    if (!selectAllCheckbox) {
        return;
    }

    const visibleBoxes = Array.from(document.querySelectorAll('.faculty-checkbox'))
        .filter(function (checkbox) { return isCardVisible(checkbox.closest('.faculty-card')); });
    const checkedBoxes = visibleBoxes.filter(function (checkbox) { return checkbox.checked; });

    if (visibleBoxes.length === 0) {
        selectAllCheckbox.indeterminate = false;
        selectAllCheckbox.checked = false;
    } else if (checkedBoxes.length === visibleBoxes.length) {
        selectAllCheckbox.indeterminate = false;
        selectAllCheckbox.checked = true;
    } else if (checkedBoxes.length > 0) {
        selectAllCheckbox.indeterminate = true;
        selectAllCheckbox.checked = false;
    } else {
        selectAllCheckbox.indeterminate = false;
        selectAllCheckbox.checked = false;
    }
}

function initializeViewToggle() {
    const viewButtons = document.querySelectorAll('.view-btn');
    const facultyList = document.getElementById('facultyList');

    viewButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            viewButtons.forEach(function (b) { b.classList.remove('active'); });
            this.classList.add('active');

            const view = this.getAttribute('data-view');
            if (facultyList) {
                facultyList.classList.toggle('compact-view', view === 'compact');
            }
            localStorage.setItem('facultyViewMode', view);
        });
    });

    if (localStorage.getItem('facultyViewMode') === 'compact') {
        document.querySelector('[data-view="compact"]')?.click();
    }
}

/* ---------------------------------------------------------------- messaging */

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

function openMessageModal(facultyId, facultyName) {
    document.getElementById('recipientId').value = facultyId;
    document.getElementById('recipientName').value = facultyName;
    document.getElementById('messageModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeMessageModal() {
    document.getElementById('messageModal').classList.remove('active');
    document.body.style.overflow = '';
    document.getElementById('messageForm').reset();
}

function bindModalDismissal() {
    document.getElementById('messageForm')?.addEventListener('submit', handleMessageSubmit);
    document.getElementById('bulkMessageForm')?.addEventListener('submit', handleBulkMessageSubmit);

    document.getElementById('messageModal')?.addEventListener('click', function (e) {
        if (e.target === this) { closeMessageModal(); }
    });
    document.getElementById('bulkMessageModal')?.addEventListener('click', function (e) {
        if (e.target === this) { closeBulkMessageModal(); }
    });
    document.getElementById('statsModal')?.addEventListener('click', function (e) {
        if (e.target === this) { closeStatsModal(); }
    });
}

async function handleMessageSubmit(e) {
    e.preventDefault();

    const submitButton = e.target.querySelector('button[type="submit"]');
    const formData = new FormData(e.target);
    formData.append('action', 'send_message');
    // The form carries a hidden csrf_token; make sure it survives a reset() race
    // and that the header the endpoint expects is always present.
    formData.set('csrf_token', csrfToken() || formData.get('csrf_token') || '');

    toggleButtonBusy(submitButton, true);

    try {
        const response = await fetch('script/messaging-system.php', {
            method: 'POST',
            headers: { 'X-CSRF-Token': csrfToken() },
            body: formData
        });
        const result = await readJson(response);

        if (result.success) {
            showNotification(result.message || 'Message sent successfully!', 'success');
            closeMessageModal();
        } else {
            showNotification(result.message || 'Failed to send message', 'error');
        }
    } catch (error) {
        console.error('Error sending message:', error);
        showNotification('Failed to send message. Please try again.', 'error');
    } finally {
        toggleButtonBusy(submitButton, false);
    }
}

function openBulkMessageModal() {
    if (facultyStaff.selected.size === 0) {
        showNotification('Please select faculty members first', 'error');
        return;
    }

    const count = facultyStaff.selected.size;
    const label = count === 1 ? 'faculty member' : 'faculty members';

    document.getElementById('bulkRecipientCount').textContent =
        `This message will be sent to ${count} ${label}.`;
    document.getElementById('bulkSubmitCount').textContent = count;
    document.getElementById('bulkMessageModal').classList.add('active');
    document.body.style.overflow = 'hidden';
    document.getElementById('bulkSubject').focus();
}

function closeBulkMessageModal() {
    document.getElementById('bulkMessageModal')?.classList.remove('active');
    document.body.style.overflow = '';
    document.getElementById('bulkMessageForm')?.reset();
}

async function handleBulkMessageSubmit(e) {
    e.preventDefault();

    const submitButton = e.target.querySelector('button[type="submit"]');
    const formData = new FormData(e.target);
    formData.append('action', 'send_bulk_message');
    formData.append('recipient_ids', JSON.stringify([...facultyStaff.selected]));
    formData.set('csrf_token', csrfToken() || formData.get('csrf_token') || '');

    toggleButtonBusy(submitButton, true);

    try {
        const response = await fetch('script/messaging-system.php', {
            method: 'POST',
            headers: { 'X-CSRF-Token': csrfToken() },
            body: formData
        });
        const result = await readJson(response);

        if (result.success) {
            showNotification(
                result.message || `Message sent to ${result.success_count} faculty members`,
                'success'
            );
            closeBulkMessageModal();
            clearSelection();
        } else {
            showNotification(result.message || 'Failed to send bulk message', 'error');
        }
    } catch (error) {
        console.error('Error sending bulk message:', error);
        showNotification('Failed to send bulk message', 'error');
    } finally {
        toggleButtonBusy(submitButton, false);
    }
}

/**
 * Read a JSON response without letting an HTML error page (a PHP fatal rendered
 * with a 200 status) surface as a generic "Unexpected token <" SyntaxError.
 */
async function readJson(response) {
    const text = await response.text();

    if (!text.trim()) {
        return { success: false, message: `Empty response from server (HTTP ${response.status})` };
    }

    try {
        return JSON.parse(text);
    } catch (error) {
        console.error('Non-JSON response:', text.slice(0, 500));
        return {
            success: false,
            message: response.ok
                ? 'The server returned an unexpected response. Check the error log.'
                : `Request failed (HTTP ${response.status}).`
        };
    }
}

/* -------------------------------------------------------------------- stats */

async function showDetailedStats(facultyId) {
    facultyStaff.statsFacultyId = facultyId;

    const modal = document.getElementById('statsModal');
    const body = document.getElementById('statsBody');
    const openTracker = document.getElementById('statsOpenTracker');

    body.innerHTML = '<div class="stats-loading"><span class="spinner"></span> Loading statistics...</div>';
    if (openTracker) {
        openTracker.href = '#';
    }
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';

    const formData = new FormData();
    formData.append('action', 'get_faculty_stats');
    formData.append('faculty_id', facultyId);
    formData.append('csrf_token', csrfToken());

    try {
        const response = await fetch('script/faculty-staff.php', {
            method: 'POST',
            headers: { 'X-CSRF-Token': csrfToken() },
            body: formData
        });
        const result = await readJson(response);

        if (result.success && result.data) {
            renderStats(result.data);
        } else {
            body.innerHTML = `<div class="stats-error"><i class='bx bx-error-circle'></i>
                <p>${escapeHtml(result.message || 'Failed to load statistics')}</p></div>`;
        }
    } catch (error) {
        console.error('Error loading stats:', error);
        body.innerHTML = `<div class="stats-error"><i class='bx bx-error-circle'></i>
            <p>Failed to load statistics. Please try again.</p></div>`;
    }
}

function renderStats(stats) {
    const body = document.getElementById('statsBody');
    const rate = Number(stats.completion_rate) || 0;
    const band = rate >= 80 ? 'high' : rate >= 50 ? 'medium' : 'low';
    const period = stats.academic_year
        ? `${stats.academic_year} · ${stats.semester === 'second' ? '2nd' : '1st'} Semester`
        : 'Current period';

    const tiles = [
        { value: `${stats.total_submitted}/${stats.total_required}`, label: 'Documents' },
        { value: `${rate}%`, label: 'Completion' },
        { value: stats.total_files ?? 0, label: 'Files Uploaded' },
        { value: stats.submission_streak ?? 0, label: 'Month Streak' },
        { value: stats.pending_count ?? 0, label: 'Pending' },
        { value: stats.overdue_count ?? 0, label: 'Overdue' }
    ];

    body.innerHTML = `
        <p class="stats-period"><i class='bx bx-calendar'></i> Reporting period: ${escapeHtml(period)}</p>
        <div class="stats-tiles">
            ${tiles.map(function (tile) {
                return `<div class="stats-tile">
                            <span class="stats-tile-value">${escapeHtml(String(tile.value))}</span>
                            <span class="stats-tile-label">${escapeHtml(tile.label)}</span>
                        </div>`;
            }).join('')}
        </div>
        <div class="progress-container">
            <div class="progress-header">
                <span class="progress-label">Completion Progress</span>
                <span class="progress-percentage">${rate}%</span>
            </div>
            <div class="progress-bar"><div class="progress-fill ${band}" style="width: ${Math.min(rate, 100)}%"></div></div>
        </div>
        ${stats.latest_submission ? `<p class="stats-updated"><i class='bx bx-time'></i>
            Last upload ${escapeHtml(formatDateTime(stats.latest_submission))}</p>` : ''}
    `;

    const card = document.querySelector(`[data-faculty-id="${facultyStaff.statsFacultyId}"]`);
    const surname = card?.getAttribute('data-name')?.split(' ').pop() || '';
    const openTracker = document.getElementById('statsOpenTracker');
    if (openTracker && surname) {
        openTracker.href = 'document-tracker.php?search=' + encodeURIComponent(surname);
    }
}

function closeStatsModal() {
    document.getElementById('statsModal')?.classList.remove('active');
    document.body.style.overflow = '';
}

function formatDateTime(value) {
    const date = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(date.getTime())) {
        return String(value);
    }
    return date.toLocaleString(undefined, {
        year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'
    });
}

/* ------------------------------------------------------------------- export */

function exportSelected() {
    if (facultyStaff.selected.size === 0) {
        showNotification('Please select faculty members first', 'error');
        return;
    }

    const rows = [];
    facultyStaff.selected.forEach(function (id) {
        const card = document.querySelector(`[data-faculty-id="${id}"]`);
        if (!card) {
            return;
        }

        const metaText = function (icon) {
            const node = card.querySelector(`.meta-item i.${icon}`);
            return node?.parentElement?.textContent.trim() || '';
        };

        rows.push({
            name: card.querySelector('.faculty-name')?.textContent.trim() || '',
            employeeId: card.getAttribute('data-employee-id') || '',
            position: card.querySelector('.faculty-position')?.textContent.trim() || '',
            email: metaText('bx-envelope'),
            phone: metaText('bx-phone'),
            lastLogin: metaText('bx-time'),
            documents: card.querySelector('.stat-item .stat-value')?.textContent.trim() || '',
            completion: card.querySelector('.progress-percentage')?.textContent.trim() || '',
            activity: card.getAttribute('data-activity') || '',
            verification: card.getAttribute('data-verification') || ''
        });
    });

    if (rows.length === 0) {
        showNotification('Nothing to export', 'error');
        return;
    }

    downloadCSV(rows, 'selected_faculty.csv');
}

function downloadCSV(rows, filename) {
    const headers = ['Name', 'Employee ID', 'Position', 'Email', 'Phone', 'Last Login', 'Documents', 'Completion', 'Activity', 'Verification'];
    const keys = ['name', 'employeeId', 'position', 'email', 'phone', 'lastLogin', 'documents', 'completion', 'activity', 'verification'];

    const csv = [headers.join(',')]
        .concat(rows.map(function (row) {
            return keys.map(function (key) {
                return `"${String(row[key] ?? '').replace(/"/g, '""')}"`;
            }).join(',');
        }))
        .join('\r\n');

    // BOM so Excel reads UTF-8 correctly.
    const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.setAttribute('hidden', '');
    a.setAttribute('href', url);
    a.setAttribute('download', filename);
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

function clearSelection() {
    facultyStaff.selected.clear();
    document.querySelectorAll('.faculty-checkbox').forEach(function (cb) { cb.checked = false; });
    const counter = document.getElementById('selectedCount');
    if (counter) {
        counter.textContent = '0';
    }
    document.getElementById('bulkActions')?.classList.remove('active');
    const selectAll = document.getElementById('selectAll');
    if (selectAll) {
        selectAll.checked = false;
        selectAll.indeterminate = false;
    }
}

/* ----------------------------------------------------------- notifications */

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.setAttribute('role', 'status');

    const icon = document.createElement('i');
    icon.className = 'bx ' + (type === 'success' ? 'bx-check-circle' : type === 'error' ? 'bx-error-circle' : 'bx-info-circle');
    const text = document.createElement('span');
    text.textContent = message;

    notification.appendChild(icon);
    notification.appendChild(text);
    document.body.appendChild(notification);

    requestAnimationFrame(function () {
        notification.classList.add('show');
    });

    setTimeout(function () {
        notification.classList.remove('show');
        setTimeout(function () { notification.remove(); }, 300);
    }, 3600);
}

function toggleButtonBusy(button, busy) {
    if (!button) {
        return;
    }
    button.disabled = busy;
    button.classList.toggle('is-busy', busy);
}

/* -------------------------------------------------------------- shortcuts */

function setupKeyboardShortcuts() {
    document.addEventListener('keydown', function (e) {
        if (e.ctrlKey || e.metaKey) {
            switch (e.key) {
                case 'f':
                    e.preventDefault();
                    document.getElementById('facultySearch')?.focus();
                    break;
                case 'a':
                    e.preventDefault();
                    document.getElementById('selectAll')?.click();
                    break;
                case 'e':
                    e.preventDefault();
                    if (facultyStaff.selected.size > 0) { exportSelected(); }
                    break;
            }
        }

        if (e.key === 'Escape') {
            if (document.getElementById('statsModal')?.classList.contains('active')) {
                closeStatsModal();
            } else if (document.getElementById('bulkMessageModal')?.classList.contains('active')) {
                closeBulkMessageModal();
            } else if (document.getElementById('messageModal')?.classList.contains('active')) {
                closeMessageModal();
            } else {
                clearSelection();
            }
        }
    });
}

function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value == null ? '' : String(value);
    return div.innerHTML;
}

/* ---------------------------------------------------------- avatar fallback */

/**
 * Replaces a broken avatar with initials drawn from the alt text.
 * The server already resolves a stored profile_image to a real file or to the
 * CVSU logo, so reaching this point means the file was removed after render -
 * an initials tile is a better result than the browser's broken-image glyph.
 */
function handleImageError(img) {
    if (img.dataset.fallbackApplied === '1') {
        return;
    }
    img.dataset.fallbackApplied = '1';

    const name = img.getAttribute('alt') || 'User';
    const initials = name
        .split(/\s+/)
        .filter(Boolean)
        .map(function (part) { return part.charAt(0); })
        .join('')
        .substring(0, 2)
        .toUpperCase() || '?';

    const placeholder = document.createElement('div');
    placeholder.className = 'faculty-avatar avatar-fallback';
    placeholder.setAttribute('aria-hidden', 'true');
    placeholder.textContent = initials;

    img.style.display = 'none';
    img.parentNode.insertBefore(placeholder, img);
}