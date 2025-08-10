// Enhanced Search functionality with debounce
let searchTimeout;
document.getElementById('searchInput').addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        const searchValue = this.value.trim().toLowerCase();
        const rows = document.querySelectorAll('#adminTable tbody tr');
        
        rows.forEach(row => {
            const id = row.cells[0].textContent.toLowerCase();
            const email = row.cells[1].textContent.toLowerCase();
            const role = row.cells[2].textContent.toLowerCase();
            const created = row.cells[3].textContent.toLowerCase();
            
            if (id.includes(searchValue) || email.includes(searchValue) || 
                role.includes(searchValue) || created.includes(searchValue)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }, 300); // 300ms debounce delay
});

// Password strength indicator with visual feedback
function checkPasswordStrength(password, strengthElement) {
    let strength = 0;
    const messages = [];
    
    // Length checks
    if (password.length >= 8) strength++;
    if (password.length >= 12) strength++;
    
    // Complexity checks
    const hasUpper = /[A-Z]/.test(password);
    const hasLower = /[a-z]/.test(password);
    const hasNumber = /[0-9]/.test(password);
    const hasSpecial = /[^A-Za-z0-9]/.test(password);
    
    if (hasUpper) strength++;
    if (hasLower) strength++;
    if (hasNumber) strength++;
    if (hasSpecial) strength++;
    
    // Update strength meter and messages
    strengthElement.innerHTML = '';
    strengthElement.className = 'password-strength';
    
    if (password.length === 0) {
        strengthElement.style.width = '0%';
        return;
    }
    
    // Create strength bar
    const bar = document.createElement('div');
    bar.className = 'strength-bar';
    
    // Create message element
    const message = document.createElement('div');
    message.className = 'strength-message';
    
    // Determine strength level
    if (strength <= 2) {
        bar.classList.add('weak');
        message.textContent = 'Weak - Add more characters and complexity';
    } else if (strength <= 4) {
        bar.classList.add('medium');
        message.textContent = 'Medium - Could be stronger';
    } else if (strength <= 6) {
        bar.classList.add('strong');
        message.textContent = 'Strong - Good password';
    } else {
        bar.classList.add('very-strong');
        message.textContent = 'Very Strong - Excellent password';
    }
    
    // Calculate width based on strength (0-8 scale mapped to 0-100%)
    bar.style.width = `${(strength / 8) * 100}%`;
    strengthElement.appendChild(bar);
    strengthElement.appendChild(message);
    
    // Add specific feedback
    const feedbackList = document.createElement('ul');
    feedbackList.className = 'strength-feedback';
    
    if (password.length < 8) {
        feedbackList.innerHTML += '<li>Should be at least 8 characters</li>';
    }
    if (!hasUpper) {
        feedbackList.innerHTML += '<li>Add uppercase letters</li>';
    }
    if (!hasLower) {
        feedbackList.innerHTML += '<li>Add lowercase letters</li>';
    }
    if (!hasNumber) {
        feedbackList.innerHTML += '<li>Add numbers</li>';
    }
    if (!hasSpecial) {
        feedbackList.innerHTML += '<li>Add special characters</li>';
    }
    
    if (feedbackList.children.length > 0) {
        strengthElement.appendChild(feedbackList);
    }
}

// Initialize password strength indicators with event listeners
function initPasswordStrength() {
    const passwordFields = [
        { field: 'adminUserPassword', strength: 'passwordStrength' },
        { field: 'new_password', strength: 'resetPasswordStrength' }
    ];
    
    passwordFields.forEach(({field, strength}) => {
        const input = document.getElementById(field);
        const indicator = document.getElementById(strength);
        
        if (input && indicator) {
            input.addEventListener('input', function() {
                checkPasswordStrength(this.value, indicator);
                togglePasswordVisibility(this);
            });
            
            // Add show/hide password toggle
            const toggle = document.createElement('span');
            toggle.className = 'password-toggle';
            toggle.innerHTML = '👁️';
            toggle.title = 'Show/hide password';
            toggle.addEventListener('click', function() {
                const input = this.previousElementSibling;
                if (input.type === 'password') {
                    input.type = 'text';
                    this.innerHTML = '👁️‍🗨️';
                } else {
                    input.type = 'password';
                    this.innerHTML = '👁️';
                }
            });
            
            input.insertAdjacentElement('afterend', toggle);
        }
    });
}

// Toggle password visibility eye icon
function togglePasswordVisibility(input) {
    const toggle = input.nextElementSibling;
    if (input.value.length > 0) {
        toggle.style.visibility = 'visible';
    } else {
        toggle.style.visibility = 'hidden';
    }
}

// Enhanced Modal functions with animation
function openResetModal(id, email) {
    const modal = document.getElementById('resetModal');
    document.getElementById('resetAdminId').value = id;
    document.getElementById('resetEmail').textContent = email;
    
    modal.style.display = 'block';
    setTimeout(() => {
        modal.querySelector('.modal-content').classList.add('show');
    }, 10);
    
    // Focus on password field when modal opens
    setTimeout(() => {
        document.getElementById('new_password')?.focus();
    }, 100);
}

function closeResetModal() {
    const modal = document.getElementById('resetModal');
    modal.querySelector('.modal-content').classList.remove('show');
    
    setTimeout(() => {
        modal.style.display = 'none';
        // Clear password field when closing
        document.getElementById('new_password').value = '';
        document.getElementById('resetPasswordStrength').innerHTML = '';
    }, 300);
}

// Close modal when clicking outside or pressing Escape
window.addEventListener('click', function(event) {
    const modal = document.getElementById('resetModal');
    if (event.target === modal) {
        closeResetModal();
    }
});

document.addEventListener('keydown', function(event) {
    const modal = document.getElementById('resetModal');
    if (event.key === 'Escape' && modal.style.display === 'block') {
        closeResetModal();
    }
});

// Form validation for admin user form
function validateAdminForm() {
    const form = document.getElementById('adminUserForm');
    if (!form) return;
    
    form.addEventListener('submit', function(e) {
        const email = document.getElementById('adminUserEmail').value.trim();
        const password = document.getElementById('adminUserPassword').value;
        const role = document.getElementById('adminUserRole').value;
        
        // Basic validation
        if (!email || !password) {
            e.preventDefault();
            alert('Please fill in all required fields');
            return;
        }
        
        // Additional checks can be added here
    });
}

// Prevent form autofill
function preventAutofill() {
    document.addEventListener('DOMContentLoaded', function() {
        // Clear password fields on page load
        document.querySelectorAll('input[type="password"]').forEach(input => {
            input.value = '';
        });
        
        // Disable browser autofill
        document.querySelectorAll('form').forEach(form => {
            form.setAttribute('autocomplete', 'off');
        });
    });
}

// Initialize all functionality
function init() {
    initPasswordStrength();
    validateAdminForm();
    preventAutofill();
    
    // Add any other initialization here
}

// Run initialization when DOM is fully loaded
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
// Enhanced stats cards animation
document.addEventListener('DOMContentLoaded', function() {
    // Animate stats cards on load
    const statsCards = document.querySelectorAll('.stat-card');
    
    statsCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = `all 0.5s ease ${index * 0.1}s`;
        
        setTimeout(() => {
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, 100);
    });

    // Add click animation
    statsCards.forEach(card => {
        card.addEventListener('click', function() {
            this.style.transform = 'scale(0.95)';
            setTimeout(() => {
                this.style.transform = 'scale(1)';
            }, 200);
        });
    });
    
    // Add hover effect for non-touch devices
    if (!('ontouchstart' in window)) {
        statsCards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
            });
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
    }
});
// Role change functionality
document.querySelectorAll('.role-select').forEach(select => {
    select.addEventListener('change', function() {
        const userId = this.dataset.userId;
        const newRole = this.value;
        
        if (confirm(`Are you sure you want to change this user's role to ${newRole}?`)) {
            fetch('/update-admin-role', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    user_id: userId,
                    new_role: newRole
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Role updated successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                    this.value = this.dataset.previousValue;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating the role');
                this.value = this.dataset.previousValue;
            });
        } else {
            this.value = this.dataset.previousValue;
        }
    });
    
    // Store initial value
    select.dataset.previousValue = select.value;
});