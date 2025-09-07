// Global JavaScript utilities and components
class WorkScheduleApp {
    constructor() {
        this.apiBase = 'api/';
        this.init();
    }

    init() {
        this.initTheme();
        this.initEventListeners();
        this.initToast();
        this.initCSRF();
    }

    // Theme Management
    initTheme() {
        const savedTheme = localStorage.getItem('theme') || 'light';
        this.setTheme(savedTheme);
    }

    setTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('theme', theme);
        
        // Update theme toggle button
        const themeToggle = document.querySelector('.theme-toggle');
        if (themeToggle) {
            themeToggle.innerHTML = theme === 'dark' ? '☀️' : '🌙';
            themeToggle.setAttribute('title', theme === 'dark' ? 'Chế độ sáng' : 'Chế độ tối');
        }
    }

    toggleTheme() {
        const currentTheme = document.documentElement.getAttribute('data-theme');
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        this.setTheme(newTheme);
        
        // Save to server if user is logged in
        this.saveUserPreference('theme', newTheme);
    }

    // Event Listeners
    initEventListeners() {
        // Theme toggle
        document.addEventListener('click', (e) => {
            if (e.target.matches('.theme-toggle')) {
                this.toggleTheme();
            }
        });

        // Mobile menu toggle
        document.addEventListener('click', (e) => {
            if (e.target.matches('.mobile-menu-toggle')) {
                this.toggleMobileMenu();
            }
        });

        // Modal management
        document.addEventListener('click', (e) => {
            if (e.target.matches('.modal-overlay')) {
                this.closeModal();
            }
            if (e.target.matches('.modal-close')) {
                this.closeModal();
            }
        });

        // Form validation
        document.addEventListener('submit', (e) => {
            if (e.target.matches('form[data-validate]')) {
                if (!this.validateForm(e.target)) {
                    e.preventDefault();
                }
            }
        });

        // Auto-save forms
        document.addEventListener('input', (e) => {
            if (e.target.matches('input[data-autosave], select[data-autosave], textarea[data-autosave]')) {
                this.debounce(() => this.autoSave(e.target), 1000)();
            }
        });
    }

    // Mobile Menu
    toggleMobileMenu() {
        const nav = document.querySelector('.nav');
        if (nav) {
            nav.classList.toggle('active');
        }
    }

    // Modal Management
    openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    }

    closeModal() {
        const activeModal = document.querySelector('.modal-overlay.active');
        if (activeModal) {
            activeModal.classList.remove('active');
            document.body.style.overflow = '';
        }
    }

    // Toast Notifications
    initToast() {
        if (!document.querySelector('.toast-container')) {
            const container = document.createElement('div');
            container.className = 'toast-container';
            container.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 1001;
                max-width: 400px;
            `;
            document.body.appendChild(container);
        }
    }

    showToast(message, type = 'info', duration = 5000) {
        const container = document.querySelector('.toast-container');
        const toast = document.createElement('div');
        toast.className = `alert alert-${type}`;
        toast.style.cssText = `
            margin-bottom: 10px;
            opacity: 0;
            transform: translateX(100%);
            transition: all 0.3s ease;
        `;
        toast.innerHTML = `
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span>${message}</span>
                <button onclick="this.parentElement.parentElement.remove()" style="background: none; border: none; color: inherit; cursor: pointer; font-size: 18px;">&times;</button>
            </div>
        `;
        
        container.appendChild(toast);
        
        // Animate in
        setTimeout(() => {
            toast.style.opacity = '1';
            toast.style.transform = 'translateX(0)';
        }, 100);
        
        // Auto remove
        if (duration > 0) {
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.style.opacity = '0';
                    toast.style.transform = 'translateX(100%)';
                    setTimeout(() => toast.remove(), 300);
                }
            }, duration);
        }
    }

    // CSRF Protection
    initCSRF() {
        // Add CSRF token to all forms
        document.addEventListener('DOMContentLoaded', () => {
            const csrfToken = document.querySelector('meta[name="csrf-token"]');
            if (csrfToken) {
                const forms = document.querySelectorAll('form');
                forms.forEach(form => {
                    if (!form.querySelector('input[name="csrf_token"]')) {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'csrf_token';
                        input.value = csrfToken.getAttribute('content');
                        form.appendChild(input);
                    }
                });
            }
        });
    }

    // Form Validation
    validateForm(form) {
        let isValid = true;
        const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
        
        inputs.forEach(input => {
            const value = input.value.trim();
            const errorElement = input.parentNode.querySelector('.form-error');
            
            // Remove existing errors
            if (errorElement) {
                errorElement.remove();
            }
            
            // Validate required fields
            if (!value) {
                this.showFieldError(input, 'Trường này là bắt buộc');
                isValid = false;
                return;
            }
            
            // Email validation
            if (input.type === 'email' && !this.validateEmail(value)) {
                this.showFieldError(input, 'Email không hợp lệ');
                isValid = false;
                return;
            }
            
            // Password confirmation
            if (input.name === 'confirm_password') {
                const passwordField = form.querySelector('input[name="password"]');
                if (passwordField && value !== passwordField.value) {
                    this.showFieldError(input, 'Mật khẩu xác nhận không khớp');
                    isValid = false;
                    return;
                }
            }
            
            // Date validation
            if (input.type === 'date' || input.type === 'datetime-local') {
                const date = new Date(value);
                if (isNaN(date.getTime())) {
                    this.showFieldError(input, 'Ngày không hợp lệ');
                    isValid = false;
                    return;
                }
            }
            
            // Number validation
            if (input.type === 'number') {
                const num = parseFloat(value);
                if (isNaN(num)) {
                    this.showFieldError(input, 'Số không hợp lệ');
                    isValid = false;
                    return;
                }
                
                if (input.min && num < parseFloat(input.min)) {
                    this.showFieldError(input, `Giá trị phải lớn hơn hoặc bằng ${input.min}`);
                    isValid = false;
                    return;
                }
                
                if (input.max && num > parseFloat(input.max)) {
                    this.showFieldError(input, `Giá trị phải nhỏ hơn hoặc bằng ${input.max}`);
                    isValid = false;
                    return;
                }
            }
        });
        
        return isValid;
    }

    showFieldError(input, message) {
        const errorElement = document.createElement('div');
        errorElement.className = 'form-error';
        errorElement.textContent = message;
        input.parentNode.appendChild(errorElement);
        input.focus();
    }

    validateEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }

    // API Helper Methods
    async apiCall(endpoint, options = {}) {
        const defaultOptions = {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        };

        // Add CSRF token for POST requests
        if (options.method && options.method !== 'GET') {
            const csrfToken = document.querySelector('meta[name="csrf-token"]');
            if (csrfToken) {
                defaultOptions.headers['X-CSRF-Token'] = csrfToken.getAttribute('content');
            }
        }

        const finalOptions = { ...defaultOptions, ...options };
        
        try {
            const response = await fetch(this.apiBase + endpoint, finalOptions);
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.message || 'Đã có lỗi xảy ra');
            }
            
            return data;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    }

    // Utility Methods
    debounce(func, wait) {
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

    formatCurrency(amount) {
        return new Intl.NumberFormat('vi-VN', {
            style: 'currency',
            currency: 'VND'
        }).format(amount);
    }

    formatDate(dateString, options = {}) {
        const defaultOptions = {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit'
        };
        
        const finalOptions = { ...defaultOptions, ...options };
        return new Date(dateString).toLocaleDateString('vi-VN', finalOptions);
    }

    formatDateTime(dateString) {
        return new Date(dateString).toLocaleString('vi-VN');
    }

    formatDuration(hours) {
        const h = Math.floor(hours);
        const m = Math.round((hours - h) * 60);
        return `${h}h ${m}m`;
    }

    // Auto-save functionality
    async autoSave(input) {
        if (!input.dataset.autosave) return;
        
        try {
            const data = { [input.name]: input.value };
            await this.apiCall('user/update-preferences.php', {
                method: 'POST',
                body: JSON.stringify(data)
            });
        } catch (error) {
            console.error('Auto-save failed:', error);
        }
    }

    async saveUserPreference(key, value) {
        try {
            await this.apiCall('user/update-preferences.php', {
                method: 'POST',
                body: JSON.stringify({ [key]: value })
            });
        } catch (error) {
            console.error('Failed to save preference:', error);
        }
    }

    // Loading states
    showLoading(element) {
        if (typeof element === 'string') {
            element = document.querySelector(element);
        }
        
        if (element) {
            element.disabled = true;
            const originalText = element.textContent;
            element.dataset.originalText = originalText;
            element.innerHTML = '<span class="loading"></span> Đang xử lý...';
        }
    }

    hideLoading(element) {
        if (typeof element === 'string') {
            element = document.querySelector(element);
        }
        
        if (element && element.dataset.originalText) {
            element.disabled = false;
            element.textContent = element.dataset.originalText;
            delete element.dataset.originalText;
        }
    }

    // Data table helpers
    createDataTable(containerId, data, columns) {
        const container = document.getElementById(containerId);
        if (!container) return;

        const table = document.createElement('table');
        table.className = 'table';

        // Create header
        const thead = document.createElement('thead');
        const headerRow = document.createElement('tr');
        columns.forEach(col => {
            const th = document.createElement('th');
            th.textContent = col.title;
            if (col.width) th.style.width = col.width;
            headerRow.appendChild(th);
        });
        thead.appendChild(headerRow);
        table.appendChild(thead);

        // Create body
        const tbody = document.createElement('tbody');
        data.forEach(row => {
            const tr = document.createElement('tr');
            columns.forEach(col => {
                const td = document.createElement('td');
                if (col.render) {
                    td.innerHTML = col.render(row[col.key], row);
                } else {
                    td.textContent = row[col.key] || '';
                }
                tr.appendChild(td);
            });
            tbody.appendChild(tr);
        });
        table.appendChild(tbody);

        container.innerHTML = '';
        container.appendChild(table);
    }

    // Export functionality
    exportToCSV(data, filename) {
        const csv = this.arrayToCSV(data);
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        
        if (link.download !== undefined) {
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', filename);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    }

    arrayToCSV(data) {
        if (!data.length) return '';
        
        const headers = Object.keys(data[0]);
        const csvContent = [
            headers.join(','),
            ...data.map(row => 
                headers.map(header => {
                    const value = row[header];
                    return typeof value === 'string' && value.includes(',') 
                        ? `"${value.replace(/"/g, '""')}"` 
                        : value;
                }).join(',')
            )
        ].join('\n');
        
        return csvContent;
    }

    // Print functionality
    printElement(elementId) {
        const element = document.getElementById(elementId);
        if (!element) return;

        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>In bảng công</title>
                <link rel="stylesheet" href="assets/css/style.css">
                <style>
                    @media print {
                        body { font-size: 12px; }
                        .no-print { display: none !important; }
                    }
                </style>
            </head>
            <body>
                ${element.outerHTML}
            </body>
            </html>
        `);
        printWindow.document.close();
        printWindow.print();
    }
}

// Initialize app when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.app = new WorkScheduleApp();
});

// Global utility functions for backward compatibility
function showToast(message, type = 'info', duration = 5000) {
    if (window.app) {
        window.app.showToast(message, type, duration);
    }
}

function openModal(modalId) {
    if (window.app) {
        window.app.openModal(modalId);
    }
}

function closeModal() {
    if (window.app) {
        window.app.closeModal();
    }
}

function formatCurrency(amount) {
    return window.app ? window.app.formatCurrency(amount) : amount;
}

function formatDate(dateString) {
    return window.app ? window.app.formatDate(dateString) : dateString;
}

function formatDuration(hours) {
    return window.app ? window.app.formatDuration(hours) : hours;
}
