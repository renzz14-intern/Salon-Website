// Function to toggle password visibility - MUST BE DEFINED FIRST
function togglePassword(inputId, toggleElement) {
    const input = document.getElementById(inputId);
    if (!input || !toggleElement) {
        console.error('Password toggle elements not found');
        return;
    }
    
    const icon = toggleElement.querySelector('.eye-icon');
    
    if (input.type === 'password') {
        input.type = 'text';
        toggleElement.classList.add('visible');
        icon.innerHTML = `
            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
            <line x1="1" y1="1" x2="23" y2="23"></line>
        `;
    } else {
        input.type = 'password';
        toggleElement.classList.remove('visible');
        icon.innerHTML = `
            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
            <circle cx="12" cy="12" r="3"></circle>
        `;
    }
}

// Function to switch between login and register forms
function showForm(formId) {
    console.log('showForm called with:', formId);
    
    const forms = document.querySelectorAll('.form-box');
    forms.forEach(form => {
        form.classList.remove('active');
    });
    
    const targetForm = document.getElementById(formId);
    if (targetForm) {
        targetForm.classList.add('active');
        console.log('Form shown:', formId);
    } else {
        console.error('Form not found:', formId);
    }
    
    if (formId === 'register-form') {
        window.location.hash = '#register';
    } else {
        window.location.hash = '#login';
    }
    
    return false;
}

// Check URL hash on page load to show appropriate form
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded');
    
    const loginForm = document.getElementById('login-form');
    const registerForm = document.getElementById('register-form');
    
    if (loginForm && registerForm) {
        const loginIsActive = loginForm.classList.contains('active');
        const registerIsActive = registerForm.classList.contains('active');
        
        console.log('Login form active:', loginIsActive);
        console.log('Register form active:', registerIsActive);
        
        if (loginIsActive || registerIsActive) {
            console.log('PHP set active form, not overriding');
            if (registerIsActive) {
                window.location.hash = '#register';
            } else {
                window.location.hash = '#login';
            }
            return;
        }
        
        const hash = window.location.hash;
        console.log('Current hash:', hash);
        
        if (hash === '#register') {
            showForm('register-form');
        } else {
            showForm('login-form');
        }
    }
    
    initializeMainWebsite();
});

// Handle browser back/forward buttons
window.addEventListener('hashchange', function() {
    const hash = window.location.hash;
    if (hash === '#register') {
        showForm('register-form');
    } else {
        showForm('login-form');
    }
});

// Add event listeners to form switch links
document.addEventListener('DOMContentLoaded', function() {
    const formSwitchLinks = document.querySelectorAll('.form-switch a');
    formSwitchLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const linkText = this.textContent.toLowerCase();
            if (linkText.includes('create') || linkText.includes('register')) {
                showForm('register-form');
            } else if (linkText.includes('sign') || linkText.includes('login')) {
                showForm('login-form');
            }
            
            return false;
        });
    });
});

// Add smooth transitions and animations
document.addEventListener('DOMContentLoaded', function() {
    const buttons = document.querySelectorAll('button, .action-btn');
    buttons.forEach(button => {
        button.addEventListener('click', function(e) {
            const ripple = document.createElement('span');
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;
            
            ripple.style.cssText = `
                position: absolute;
                width: ${size}px;
                height: ${size}px;
                left: ${x}px;
                top: ${y}px;
                background: rgba(255, 255, 255, 0.3);
                border-radius: 50%;
                transform: scale(0);
                animation: ripple 0.6s ease-out;
                pointer-events: none;
            `;
            
            if (!document.getElementById('ripple-style')) {
                const style = document.createElement('style');
                style.id = 'ripple-style';
                style.textContent = `
                    @keyframes ripple {
                        to {
                            transform: scale(2);
                            opacity: 0;
                        }
                    }
                `;
                document.head.appendChild(style);
            }
            
            this.style.position = 'relative';
            this.style.overflow = 'hidden';
            this.appendChild(ripple);
            
            setTimeout(() => ripple.remove(), 600);
        });
    });
    
    const messages = document.querySelectorAll('.success-message, .error-message, .logout-message');
    messages.forEach(message => {
        setTimeout(() => {
            message.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            message.style.opacity = '0';
            message.style.transform = 'translateY(-10px)';
            setTimeout(() => message.remove(), 500);
        }, 10000);
    });
});

// ==================================================
// MAIN WEBSITE FUNCTIONALITY
// ==================================================

let isLoggedIn = false;
let currentUser = null;

// Function to initialize main website functionality
function initializeMainWebsite() {
    if (!document.getElementById('auth-section')) {
        return;
    }
    
    checkAuthStatus();
    
    setTimeout(() => {
        checkPendingBooking();
    }, 500);
    
    initializePeekSlider();
    initializeDropdown();
    initializeSmoothScrolling();
    initializeCarousel();
    initializeScrollNavigation();
    initializeScrollSnapAnimations();
    initializeMobileMenu();
}

// Mobile Menu Toggle Functionality
function initializeMobileMenu() {
    const menuToggle = document.getElementById('menu-toggle');
    const navLinks = document.querySelector('.nav-links');
    const navLinksItems = document.querySelectorAll('.nav-links a');
    
    if (menuToggle && navLinks) {
        // Toggle menu on hamburger click
        menuToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            navLinks.classList.toggle('active');
            menuToggle.classList.toggle('active');
            
            // Animate hamburger to X
            if (menuToggle.classList.contains('active')) {
                menuToggle.innerHTML = `
                    <span style="transform: rotate(45deg) translateY(8px);"></span>
                    <span style="opacity: 0;"></span>
                    <span style="transform: rotate(-45deg) translateY(-8px);"></span>
                `;
            } else {
                menuToggle.innerHTML = `
                    <span></span>
                    <span></span>
                    <span></span>
                `;
            }
        });
        
        // Close menu when clicking a nav link
        navLinksItems.forEach(link => {
            link.addEventListener('click', function() {
                navLinks.classList.remove('active');
                menuToggle.classList.remove('active');
                menuToggle.innerHTML = `
                    <span></span>
                    <span></span>
                    <span></span>
                `;
            });
        });
        
        // Close menu when clicking outside
        document.addEventListener('click', function(e) {
            if (!navLinks.contains(e.target) && !menuToggle.contains(e.target)) {
                navLinks.classList.remove('active');
                menuToggle.classList.remove('active');
                menuToggle.innerHTML = `
                    <span></span>
                    <span></span>
                    <span></span>
                `;
            }
        });
    }
}

// Function to check login status when page loads
function checkAuthStatus() {
    const urlParams = new URLSearchParams(window.location.search);
    const loginSuccess = urlParams.get('login_success');
    const logoutSuccess = urlParams.get('logout_success');
    const userName = urlParams.get('user_name');
    
    if (loginSuccess === '1' && userName) {
        currentUser = {
            name: decodeURIComponent(userName),
            isLoggedIn: true
        };
        
        localStorage.setItem('userAuth', JSON.stringify(currentUser));
        
        const newUrl = window.location.pathname;
        window.history.replaceState({}, document.title, newUrl);
        
        showWelcomeMessage(`Welcome, ${currentUser.name}! You are now logged in.`);
        
        updateAuthDisplay();
    } else if (logoutSuccess === '1' && userName) {
        const loggedOutUserName = decodeURIComponent(userName);
        
        localStorage.removeItem('userAuth');
        currentUser = null;
        isLoggedIn = false;
        
        const newUrl = window.location.pathname;
        window.history.replaceState({}, document.title, newUrl);
        
        showWelcomeMessage(`Goodbye, ${loggedOutUserName}! You have been logged out successfully.`);
        
        updateAuthDisplay();
    } else {
        const storedAuth = localStorage.getItem('userAuth');
        if (storedAuth) {
            try {
                currentUser = JSON.parse(storedAuth);
                if (currentUser.isLoggedIn) {
                    updateAuthDisplay();
                }
            } catch (e) {
                localStorage.removeItem('userAuth');
            }
        }
    }
}

// Function to update the authentication display
function updateAuthDisplay() {
    const loginButton = document.getElementById('login-button');
    const userSection = document.getElementById('user-section');
    const userNameSpan = document.getElementById('user-name');
    
    if (currentUser && currentUser.isLoggedIn) {
        if (loginButton) loginButton.style.display = 'none';
        if (userSection) userSection.style.display = 'block';
        if (userNameSpan) userNameSpan.textContent = currentUser.name;
        isLoggedIn = true;
    } else {
        if (loginButton) loginButton.style.display = 'inline-block';
        if (userSection) userSection.style.display = 'none';
        isLoggedIn = false;
    }
}

// Function to handle service booking with authentication check
function handleServiceBooking(serviceName) {
    const storedAuth = localStorage.getItem('userAuth');
    let userIsAuthenticated = false;
    
    if (storedAuth) {
        try {
            const authData = JSON.parse(storedAuth);
            userIsAuthenticated = authData && authData.isLoggedIn;
        } catch (e) {
            localStorage.removeItem('userAuth');
        }
    }
    
    if (!userIsAuthenticated) {
        localStorage.setItem('pendingBooking', 'true');
        if (serviceName) {
            localStorage.setItem('selectedService', serviceName);
        }
        
        showWelcomeMessage('Please login or create an account to book a service.');
        
        window.location.href = 'registration.php';
        return;
    }
    
    try {
        if (serviceName) {
            localStorage.setItem('selectedService', serviceName);
        }
        window.location.href = 'booking.php';
    } catch (err) {
        console.error('Error navigating to booking page', err);
        showWelcomeMessage('Error accessing booking page. Please try again.');
    }
}

// Function to check for pending booking after login
function checkPendingBooking() {
    const pendingBooking = localStorage.getItem('pendingBooking');
    
    if (pendingBooking === 'true' && currentUser && currentUser.isLoggedIn) {
        localStorage.removeItem('pendingBooking');
        
        setTimeout(() => {
            window.location.href = 'booking.php';
        }, 2000);
    }
}

// Function to show welcome message
function showWelcomeMessage(message) {
    const welcomeDiv = document.getElementById('welcome-message');
    const welcomeText = document.getElementById('welcome-text');
    
    if (welcomeDiv && welcomeText) {
        welcomeText.textContent = message;
        welcomeDiv.style.display = 'block';
        
        setTimeout(() => {
            closeWelcomeMessage();
        }, 5000);
    }
}

// Function to close welcome message
function closeWelcomeMessage() {
    const welcomeDiv = document.getElementById('welcome-message');
    if (welcomeDiv) {
        welcomeDiv.style.display = 'none';
    }
}

// Initialize dropdown functionality
function initializeDropdown() {
    const userButton = document.getElementById('user-button');
    const dropdownContent = document.getElementById('dropdown-content');
    
    if (userButton && dropdownContent) {
        userButton.addEventListener('click', function(e) {
            e.preventDefault();
            dropdownContent.classList.toggle('show');
        });
        
        document.addEventListener('click', function(e) {
            if (!userButton.contains(e.target)) {
                dropdownContent.classList.remove('show');
            }
        });
    }
}

// Handle logout
function handleLogout() {
    localStorage.removeItem('userAuth');
    localStorage.removeItem('pendingBooking');
    localStorage.removeItem('selectedService');
    currentUser = null;
    isLoggedIn = false;
    updateAuthDisplay();
}

// Listen for logout events from other pages
window.addEventListener('storage', function(e) {
    if (e.key === 'userAuth' && !e.newValue) {
        handleLogout();
    }
});

// Initialize smooth scrolling for navigation links
function initializeSmoothScrolling() {
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
}

// Initialize service card auto-rotation
function initializePeekSlider() {
    const serviceCards = document.querySelectorAll('.service-card');
    const serviceDots = document.querySelectorAll('.service-dot');
    let currentServiceIndex = 0;
    let autoPlayInterval = null;
    let isAutoPlayActive = true;

    if (serviceCards.length > 0) {
        function showServiceCard(index) {
            serviceCards.forEach(card => {
                card.classList.remove('active');
            });

            serviceCards[index].classList.add('active');

            serviceDots.forEach(dot => dot.classList.remove('active'));
            if (serviceDots[index]) {
                serviceDots[index].classList.add('active');
            }
        }

        function nextServiceCard() {
            currentServiceIndex++;
            if (currentServiceIndex >= serviceCards.length) {
                currentServiceIndex = 0;
            }
            showServiceCard(currentServiceIndex);
        }

        function goToServiceCard(index) {
            currentServiceIndex = index;
            showServiceCard(currentServiceIndex);
            // Stop auto-play completely and restart with fresh timer
            stopAutoPlay();
            startAutoPlay();
        }

        function startAutoPlay() {
            // Clear any existing interval first
            if (autoPlayInterval) {
                clearInterval(autoPlayInterval);
                autoPlayInterval = null;
            }
            
            // Only start if auto-play is active
            if (isAutoPlayActive) {
                autoPlayInterval = setInterval(nextServiceCard, 4000);
            }
        }

        function stopAutoPlay() {
            if (autoPlayInterval) {
                clearInterval(autoPlayInterval);
                autoPlayInterval = null;
            }
        }

        // Event listeners for dots
        serviceDots.forEach((dot, index) => {
            dot.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                goToServiceCard(index);
            });
        });

        // Pause auto-play when hovering over the service container
        const serviceContainer = document.querySelector('.service-card-container');
        if (serviceContainer) {
            serviceContainer.addEventListener('mouseenter', () => {
                isAutoPlayActive = false;
                stopAutoPlay();
            });
            
            serviceContainer.addEventListener('mouseleave', () => {
                isAutoPlayActive = true;
                startAutoPlay();
            });
            
            // Also pause on touch devices when user interacts
            serviceContainer.addEventListener('touchstart', () => {
                isAutoPlayActive = false;
                stopAutoPlay();
            });
            
            serviceContainer.addEventListener('touchend', () => {
                // Resume auto-play after 5 seconds of no interaction
                setTimeout(() => {
                    isAutoPlayActive = true;
                    startAutoPlay();
                }, 5000);
            });
        }

        // Initialize - show first card and start auto-play
        showServiceCard(0);
        startAutoPlay();

        // Clean up on page unload
        window.addEventListener('beforeunload', () => {
            stopAutoPlay();
        });
    }
}

// Initialize carousel functionality
function initializeCarousel() {
    let slideIndex = 1;
    const totalSlides = 6;
    
    function showSlides(n) {
        const slides = document.querySelector('.carousel-wrapper');
        const dots = document.querySelectorAll('.dot');
        
        if (n > totalSlides) { slideIndex = 1; }
        if (n < 1) { slideIndex = totalSlides; }
        
        const translateX = -(slideIndex - 1) * 100;
        if (slides) {
            slides.style.transform = `translateX(${translateX}%)`;
        }
        
        dots.forEach(dot => dot.classList.remove('active'));
        if (dots[slideIndex - 1]) {
            dots[slideIndex - 1].classList.add('active');
        }
    }
    
    function moveSlide(n) {
        slideIndex += n;
        showSlides(slideIndex);
    }
    
    function currentSlide(n) {
        slideIndex = n;
        showSlides(slideIndex);
    }
    
    setInterval(() => {
        slideIndex++;
        showSlides(slideIndex);
    }, 5000);
    
    showSlides(slideIndex);
    
    window.moveSlide = moveSlide;
    window.currentSlide = currentSlide;
}

// Initialize scroll-based navigation highlighting
function initializeScrollNavigation() {
    window.addEventListener('scroll', () => {
        let current = '';
        const sections = document.querySelectorAll('section');
        
        sections.forEach(section => {
            const sectionTop = section.offsetTop;
            const sectionHeight = section.clientHeight;
            if (scrollY >= (sectionTop - 200)) {
                current = section.getAttribute('id');
            }
        });

        document.querySelectorAll('.nav-links a').forEach(link => {
            link.classList.remove('active');
            if (link.getAttribute('href').includes(current)) {
                link.classList.add('active');
            }
        });
    });
}

// Scroll fade-in animation for sections
function initializeScrollSnapAnimations() {
    const sections = document.querySelectorAll('.section-content-fade');
    
    function checkSections() {
        sections.forEach(section => {
            const rect = section.getBoundingClientRect();
            
            if (rect.top >= -200 && rect.top <= window.innerHeight - 200) {
                section.classList.add('show');
            }
        });
    }
    
    window.addEventListener('scroll', checkSections);
    checkSections();
}

// Make functions available globally
window.handleServiceBooking = handleServiceBooking;
window.closeWelcomeMessage = closeWelcomeMessage;

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    if (document.querySelector('.booking-container')) {
        console.log('Initializing booking system...');
        window.spaBooking = new SpaBookingSystem();
    }
});

// Keyboard navigation
document.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') {
        e.preventDefault();
        
        const activeStep = document.querySelector('.form-step.active');
        if (activeStep) {
            const nextButton = activeStep.querySelector('.next-step');
            const confirmButton = activeStep.querySelector('#confirm-booking-btn');
            
            if (confirmButton) {
                confirmButton.click();
            } else if (nextButton) {
                nextButton.click();
            }
        }
    }
});