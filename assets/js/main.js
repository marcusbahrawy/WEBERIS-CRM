// assets/js/main.js - Main JavaScript file for WEBERIS CRM

// DOM Ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize UI components
    initializeUI();
    
    // Set up form validations
    setupFormValidations();
    
    // Handle data tables
    setupDataTables();
    
    // Initialize notifications
    initializeNotifications();
});

/**
 * Initialize UI components and interactions
 */
function initializeUI() {
    // Mobile menu toggle
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    if (mobileMenuToggle) {
        mobileMenuToggle.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });
    }

    // Sidebar toggle functionality
    const sidebarToggle = document.createElement('button');
    sidebarToggle.className = 'sidebar-toggle';
    sidebarToggle.innerHTML = '<span class="material-icons">chevron_left</span>';
    document.querySelector('.sidebar-header').appendChild(sidebarToggle);

    sidebarToggle.addEventListener('click', function() {
        document.querySelector('.sidebar').classList.toggle('sidebar-collapsed');
        document.querySelector('.main-content').classList.toggle('expanded');
        
        if (document.querySelector('.sidebar').classList.contains('sidebar-collapsed')) {
            this.innerHTML = '<span class="material-icons">chevron_right</span>';
        } else {
            this.innerHTML = '<span class="material-icons">chevron_left</span>';
        }
    });

    // Enhanced alerts
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        // Add icon based on alert type
        const alertType = Array.from(alert.classList)
            .find(cls => cls.startsWith('alert-'))
            ?.replace('alert-', '');
            
        if (alertType) {
            let iconName = 'info';
            if (alertType === 'success') iconName = 'check_circle';
            if (alertType === 'warning') iconName = 'warning';
            if (alertType === 'danger') iconName = 'error';
            
            const icon = document.createElement('span');
            icon.className = 'material-icons alert-icon';
            icon.textContent = iconName;
            
            const content = document.createElement('div');
            content.className = 'alert-content';
            
            // Move all child nodes to the content div
            while (alert.firstChild) {
                content.appendChild(alert.firstChild);
            }
            
            alert.appendChild(icon);
            alert.appendChild(content);
        }
    });
    
    // Setup alert dismissal
    setupAlertDismissal();
}

/**
 * Initialize notifications dropdown and polling
 */
function initializeNotifications() {
    const notificationsToggle = document.getElementById('notificationsToggle');
    const notificationsDropdown = document.getElementById('notificationsDropdown');
    const notificationsContent = document.getElementById('notificationsContent');
    
    if (!notificationsToggle || !notificationsDropdown || !notificationsContent) {
        return;
    }
    
    // Toggle dropdown
    notificationsToggle.addEventListener('click', function(e) {
        e.stopPropagation();
        notificationsDropdown.classList.toggle('show');
        
        if (notificationsDropdown.classList.contains('show')) {
            loadNotifications();
        }
    });
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!notificationsDropdown.contains(e.target) && !notificationsToggle.contains(e.target)) {
            notificationsDropdown.classList.remove('show');
        }
    });
    
    // Prevent dropdown from closing when clicking inside it
    notificationsDropdown.addEventListener('click', function(e) {
        e.stopPropagation();
    });
    
    // Load notifications function
    function loadNotifications() {
        notificationsContent.innerHTML = '<div class="loading-spinner"><div class="spinner"></div></div>';
        
        fetch(window.location.origin + '/modules/notifications/get_ajax.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    renderNotifications(data);
                    updateNotificationCount(data.unread_count);
                } else {
                    notificationsContent.innerHTML = '<div class="notification-error">Failed to load notifications</div>';
                }
            })
            .catch(error => {
                notificationsContent.innerHTML = '<div class="notification-error">Error loading notifications</div>';
                console.error('Notification error:', error);
            });
    }
    
    // Render notifications in dropdown
    function renderNotifications(data) {
        if (data.notifications.length === 0) {
            notificationsContent.innerHTML = '<div class="no-notifications">No notifications yet</div>';
            return;
        }
        
        let html = '';
        
        data.notifications.forEach(notification => {
            // Choose icon based on notification type
            let icon = 'notifications';
            switch (notification.type) {
                case 'task_assigned':
                    icon = 'assignment_ind';
                    break;
                case 'task_completed':
                    icon = 'task_alt';
                    break;
                case 'task_comment':
                    icon = 'comment';
                    break;
                case 'task_update':
                    icon = 'update';
                    break;
                case 'task_deleted':
                    icon = 'delete';
                    break;
            }
            
            html += `
                <div class="dropdown-notification ${notification.is_read == 0 ? 'unread' : ''}">
                    <div class="notification-icon">
                        <span class="material-icons">${icon}</span>
                    </div>
                    <div class="notification-content">
                        <div class="notification-title">${notification.title}</div>
                        <div class="notification-message">${notification.message}</div>
                        <div class="notification-time">${notification.time_ago}</div>
                    </div>
                    ${notification.link ? `<a href="${window.location.origin}${notification.link}" class="notification-link">
                        <span class="material-icons">arrow_forward</span>
                    </a>` : ''}
                </div>
            `;
        });
        
        notificationsContent.innerHTML = html;
    }
    
    // Update notification badge count
    function updateNotificationCount(count) {
        const badge = notificationsToggle.querySelector('.notifications-badge');
        if (count > 0) {
            if (badge) {
                badge.textContent = count;
            } else {
                const newBadge = document.createElement('span');
                newBadge.className = 'notifications-badge';
                newBadge.textContent = count;
                notificationsToggle.appendChild(newBadge);
            }
        } else if (badge) {
            badge.remove();
        }
    }
    
    // Poll for new notifications every 30 seconds
    setInterval(function() {
        if (!notificationsDropdown.classList.contains('show')) {
            // Only update the count if dropdown is closed
            fetch(window.location.origin + '/modules/notifications/get_ajax.php?count_only=1')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateNotificationCount(data.unread_count);
                    }
                })
                .catch(error => {
                    console.error('Notification count error:', error);
                });
        }
    }, 30000);
}

/**
 * Set up form validations
 */
function setupFormValidations() {
    const forms = document.querySelectorAll('form[data-validate="true"]');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            let isValid = true;
            
            // Required fields
            const requiredFields = form.querySelectorAll('[required]');
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('is-invalid');
                    
                    // Add or update error message
                    let errorMsg = field.parentNode.querySelector('.error-message');
                    if (!errorMsg) {
                        errorMsg = document.createElement('div');
                        errorMsg.className = 'error-message';
                        field.parentNode.appendChild(errorMsg);
                    }
                    errorMsg.textContent = 'This field is required';
                } else {
                    field.classList.remove('is-invalid');
                    const errorMsg = field.parentNode.querySelector('.error-message');
                    if (errorMsg) {
                        errorMsg.remove();
                    }
                }
            });
            
            // Email validations
            const emailFields = form.querySelectorAll('input[type="email"]');
            emailFields.forEach(field => {
                if (field.value.trim() && !validateEmail(field.value)) {
                    isValid = false;
                    field.classList.add('is-invalid');
                    
                    // Add or update error message
                    let errorMsg = field.parentNode.querySelector('.error-message');
                    if (!errorMsg) {
                        errorMsg = document.createElement('div');
                        errorMsg.className = 'error-message';
                        field.parentNode.appendChild(errorMsg);
                    }
                    errorMsg.textContent = 'Please enter a valid email address';
                }
            });
            
            // Password match validation
            const password = form.querySelector('input[name="password"]');
            const confirmPassword = form.querySelector('input[name="confirm_password"]');
            
            if (password && confirmPassword && password.value !== confirmPassword.value) {
                isValid = false;
                confirmPassword.classList.add('is-invalid');
                
                // Add or update error message
                let errorMsg = confirmPassword.parentNode.querySelector('.error-message');
                if (!errorMsg) {
                    errorMsg = document.createElement('div');
                    errorMsg.className = 'error-message';
                    confirmPassword.parentNode.appendChild(errorMsg);
                }
                errorMsg.textContent = 'Passwords do not match';
            }
            
            if (!isValid) {
                e.preventDefault();
            }
        });
        
        // Clear validation errors on input
        const formInputs = form.querySelectorAll('input, select, textarea');
        formInputs.forEach(input => {
            input.addEventListener('input', function() {
                this.classList.remove('is-invalid');
                const errorMsg = this.parentNode.querySelector('.error-message');
                if (errorMsg) {
                    errorMsg.remove();
                }
            });
        });
    });
}

/**
 * Validate email format
 * @param {string} email - Email to validate
 * @returns {boolean} - True if valid, false otherwise
 */
function validateEmail(email) {
    const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
    return re.test(String(email).toLowerCase());
}

/**
 * Set up dismissable alerts
 */
function setupAlertDismissal() {
    const alerts = document.querySelectorAll('.alert[data-dismissible="true"]');
    
    alerts.forEach(alert => {
        // Create dismiss button
        const dismissBtn = document.createElement('button');
        dismissBtn.type = 'button';
        dismissBtn.className = 'alert-dismiss';
        dismissBtn.innerHTML = '&times;';
        
        // Add dismiss button to alert
        alert.appendChild(dismissBtn);
        
        // Set up dismiss handler
        dismissBtn.addEventListener('click', function() {
            alert.remove();
        });
        
        // Auto dismiss after certain time if specified
        const autoDismiss = alert.getAttribute('data-auto-dismiss');
        if (autoDismiss && !isNaN(autoDismiss)) {
            setTimeout(() => {
                alert.remove();
            }, parseInt(autoDismiss) * 1000);
        }
    });
}

/**
 * Set up data tables functionality
 */
function setupDataTables() {
    const dataTables = document.querySelectorAll('.data-table[data-sortable="true"]');
    
    dataTables.forEach(table => {
        const headers = table.querySelectorAll('th[data-sortable="true"]');
        
        headers.forEach(header => {
            header.style.cursor = 'pointer';
            
            // Add sort icon
            const sortIcon = document.createElement('span');
            sortIcon.className = 'sort-icon material-icons';
            sortIcon.textContent = 'unfold_more';
            sortIcon.style.fontSize = '16px';
            sortIcon.style.verticalAlign = 'middle';
            sortIcon.style.marginLeft = '4px';
            header.appendChild(sortIcon);
            
            // Set up sort event
            header.addEventListener('click', function() {
                const columnIndex = Array.from(header.parentNode.children).indexOf(header);
                const currentDirection = header.getAttribute('data-sort-direction') || 'none';
                
                // Reset all headers
                headers.forEach(h => {
                    h.setAttribute('data-sort-direction', 'none');
                    h.querySelector('.sort-icon').textContent = 'unfold_more';
                });
                
                let newDirection;
                if (currentDirection === 'none' || currentDirection === 'desc') {
                    newDirection = 'asc';
                    header.querySelector('.sort-icon').textContent = 'expand_less';
                } else {
                    newDirection = 'desc';
                    header.querySelector('.sort-icon').textContent = 'expand_more';
                }
                
                header.setAttribute('data-sort-direction', newDirection);
                
                // Sort table
                sortTable(table, columnIndex, newDirection);
            });
        });
    });
}

/**
 * Sort table by column
 * @param {HTMLElement} table - Table to sort
 * @param {number} columnIndex - Index of column to sort by
 * @param {string} direction - Sort direction ('asc' or 'desc')
 */
function sortTable(table, columnIndex, direction) {
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    // Sort the rows
    const sortedRows = rows.sort((a, b) => {
        const cellA = a.cells[columnIndex].textContent.trim();
        const cellB = b.cells[columnIndex].textContent.trim();
        
        // Check if content is number
        if (!isNaN(cellA) && !isNaN(cellB)) {
            return direction === 'asc' 
                ? parseFloat(cellA) - parseFloat(cellB)
                : parseFloat(cellB) - parseFloat(cellA);
        }
        
        // Check if content is date
        const dateA = new Date(cellA);
        const dateB = new Date(cellB);
        
        if (!isNaN(dateA) && !isNaN(dateB)) {
            return direction === 'asc'
                ? dateA - dateB
                : dateB - dateA;
        }
        
        // Text comparison
        return direction === 'asc'
            ? cellA.localeCompare(cellB)
            : cellB.localeCompare(cellA);
    });
    
    // Remove existing rows
    while (tbody.firstChild) {
        tbody.removeChild(tbody.firstChild);
    }
    
    // Add sorted rows
    sortedRows.forEach(row => {
        tbody.appendChild(row);
    });
}

/**
 * Format currency value
 * @param {number} value - Value to format
 * @param {string} currency - Currency code (default: '$')
 * @returns {string} - Formatted currency value
 */
function formatCurrency(value, currency = '$') {
    return currency + parseFloat(value).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

/**
 * Format date value
 * @param {string} dateString - Date string to format
 * @param {string} format - Format type ('short', 'medium', 'long')
 * @returns {string} - Formatted date
 */
function formatDate(dateString, format = 'medium') {
    const date = new Date(dateString);
    
    if (isNaN(date)) {
        return dateString;
    }
    
    switch (format) {
        case 'short':
            return `${date.getMonth() + 1}/${date.getDate()}/${date.getFullYear()}`;
        case 'long':
            return date.toLocaleDateString(undefined, {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        case 'medium':
        default:
            return date.toLocaleDateString(undefined, {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
    }
}

/**
 * Format time value
 * @param {string} timeString - Time string to format
 * @param {boolean} includeSeconds - Whether to include seconds
 * @returns {string} - Formatted time
 */
function formatTime(timeString, includeSeconds = false) {
    const time = new Date(timeString);
    
    if (isNaN(time)) {
        return timeString;
    }
    
    const options = {
        hour: '2-digit',
        minute: '2-digit'
    };
    
    if (includeSeconds) {
        options.second = '2-digit';
    }
    
    return time.toLocaleTimeString(undefined, options);
}

/**
 * Format date and time value
 * @param {string} dateTimeString - Date/time string to format
 * @param {boolean} includeSeconds - Whether to include seconds
 * @returns {string} - Formatted date and time
 */
function formatDateTime(dateTimeString, includeSeconds = false) {
    const dateTime = new Date(dateTimeString);
    
    if (isNaN(dateTime)) {
        return dateTimeString;
    }
    
    const dateOptions = {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    };
    
    const timeOptions = {
        hour: '2-digit',
        minute: '2-digit'
    };
    
    if (includeSeconds) {
        timeOptions.second = '2-digit';
    }
    
    return `${dateTime.toLocaleDateString(undefined, dateOptions)} ${dateTime.toLocaleTimeString(undefined, timeOptions)}`;
}