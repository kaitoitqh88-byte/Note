/**
 * Tactical Gaming Interface - JavaScript Effects
 * RPG Shooter theme enhancements for CF Manager
 */

class TacticalInterface {
    constructor() {
        this.soundEnabled = localStorage.getItem('tacticalSounds') !== 'false';
        this.animationsEnabled = localStorage.getItem('tacticalAnimations') !== 'false';
        this.ammoCount = 100;
        this.healthCount = 100;
        
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.startSystemCheck();
        this.initializeAnimations();
        this.createParticles();
    }



    // Setup event listeners for tactical sounds
    setupEventListeners() {
        // Button click sounds
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('btn')) {
                this.playSound('button-click');
            }
        });

        // Hover sounds
        document.addEventListener('mouseover', (e) => {
            if (e.target.classList.contains('nav-link') || e.target.classList.contains('btn')) {
                this.playSound('hover');
            }
        });

        // Form submission sounds
        document.addEventListener('submit', (e) => {
            this.playSound('deploy');
        });

        // Page load sound
        window.addEventListener('load', () => {
    // (nếu cần, thêm hiệu ứng khi load trang ở đây)
});

// Toggle sound button
document.addEventListener('keydown', (e) => {
    if (e.key === 'F9') {
        tacticalInterface?.toggleSound?.();
    }
    if (e.key === 'F10') {
        tacticalInterface?.toggleAnimations?.();
    }
});
    }

    // Play tactical sounds
    playSound(soundType) {
        // playSound removed: all sound effects disabled
    }

    // Create button click effect
    createButtonEffect(button) {
        if (!this.animationsEnabled) return;

        const effect = document.createElement('div');
        effect.className = 'tactical-effect';
        effect.style.cssText = `
            position: absolute;
            width: 4px;
            height: 4px;
            background: var(--accent-green);
            border-radius: 50%;
            pointer-events: none;
            z-index: 9999;
            box-shadow: 0 0 10px var(--accent-green);
        `;

        const rect = button.getBoundingClientRect();
        effect.style.left = (rect.left + rect.width / 2) + 'px';
        effect.style.top = (rect.top + rect.height / 2) + 'px';

        document.body.appendChild(effect);

        // Animate explosion effect
        effect.animate([
            { transform: 'scale(1)', opacity: 1 },
            { transform: 'scale(8)', opacity: 0 }
        ], {
            duration: 300,
            easing: 'ease-out'
        }).onfinish = () => effect.remove();


    }

    // Create hover effect
    createHoverEffect(element) {
        if (!this.animationsEnabled) return;

        element.style.transform = 'translateY(-2px) scale(1.02)';
        element.style.transition = 'all 0.3s ease';
        
        setTimeout(() => {
            element.style.transform = '';
        }, 200);
    }

    // Show mission alerts
    showMissionAlert(message, type = 'info', duration = 3000) {
        const alert = document.createElement('div');
        alert.className = `tactical-alert alert-${type}`;
        alert.innerHTML = `
            <i class="fas fa-exclamation-triangle me-2"></i>
            <span>${message}</span>
            <div class="alert-progress"></div>
        `;

        alert.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(13, 16, 21, 0.9);
            border: 1px solid var(--accent-${type === 'success' ? 'green' : type === 'danger' ? 'red' : 'orange'});
            color: var(--accent-${type === 'success' ? 'green' : type === 'danger' ? 'red' : 'orange'});
            padding: 1rem;
            border-radius: 6px;
            font-family: var(--font-display);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            z-index: 10000;
            min-width: 300px;
            transform: translateX(100%);
            transition: transform 0.3s ease;
            box-shadow: 0 0 20px var(--accent-${type === 'success' ? 'green' : type === 'danger' ? 'red' : 'orange'});
        `;

        document.body.appendChild(alert);

        // Animate in
        setTimeout(() => {
            alert.style.transform = 'translateX(0)';
        }, 100);

        // Progress bar animation
        const progressBar = alert.querySelector('.alert-progress');
        progressBar.style.cssText = `
            position: absolute;
            bottom: 0;
            left: 0;
            height: 2px;
            background: var(--accent-${type === 'success' ? 'green' : type === 'danger' ? 'red' : 'orange'});
            transition: width ${duration}ms linear;
            width: 100%;
        `;
        
        setTimeout(() => {
            progressBar.style.width = '0%';
        }, 100);

        // Remove alert
        setTimeout(() => {
            alert.style.transform = 'translateX(100%)';
            setTimeout(() => alert.remove(), 300);
        }, duration);
    }



    // System check animation
    startSystemCheck() {
        const statusItems = document.querySelectorAll('.status-item');
        statusItems.forEach((item, index) => {
            setTimeout(() => {
                item.style.opacity = '1';
                item.style.transform = 'translateX(0)';
                this.playSound('hover');
            }, index * 500);
        });
    }

    // Initialize page animations
    initializeAnimations() {
        if (!this.animationsEnabled) return;

        // Animate cards on load
        const cards = document.querySelectorAll('.card');
        cards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(50px)';
            setTimeout(() => {
                card.style.transition = 'all 0.5s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 100);
        });

        // Animate buttons
        const buttons = document.querySelectorAll('.btn');
        buttons.forEach((btn, index) => {
            setTimeout(() => {
                btn.style.animation = 'pulse-glow 2s infinite';
            }, index * 50);
        });
    }

    // Create background particles
    createParticles() {
        if (!this.animationsEnabled) return;

        const particleContainer = document.createElement('div');
        particleContainer.className = 'particle-container';
        particleContainer.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: -1;
            overflow: hidden;
        `;

        document.body.appendChild(particleContainer);

        // Create floating particles
        for (let i = 0; i < 20; i++) {
            setTimeout(() => this.createParticle(particleContainer), i * 200);
        }
    }

    createParticle(container) {
        const particle = document.createElement('div');
        particle.style.cssText = `
            position: absolute;
            width: 2px;
            height: 2px;
            background: var(--accent-green);
            border-radius: 50%;
            box-shadow: 0 0 4px var(--accent-green);
            left: ${Math.random() * 100}%;
            animation: float-particle 10s linear infinite;
            opacity: ${Math.random() * 0.5 + 0.2};
        `;

        container.appendChild(particle);

        // Remove particle after animation
        setTimeout(() => {
            if (particle.parentNode) {
                particle.remove();
            }
        }, 10000);

        // Create new particle
        setTimeout(() => this.createParticle(container), 2000);
    }

    // Toggle functions
    toggleSound() {
        this.soundEnabled = !this.soundEnabled;
        localStorage.setItem('tacticalSounds', this.soundEnabled);
        this.showMissionAlert(`SOUND ${this.soundEnabled ? 'ENABLED' : 'DISABLED'}`, 'info');
    }

    toggleAnimations() {
        this.animationsEnabled = !this.animationsEnabled;
        localStorage.setItem('tacticalAnimations', this.animationsEnabled);
        this.showMissionAlert(`ANIMATIONS ${this.animationsEnabled ? 'ENABLED' : 'DISABLED'}`, 'info');
    }


}

// Add tactical CSS animations
const tacticalCSS = `
    .system-status .status-item {
        display: flex;
        align-items: center;
        font-size: 0.8rem;
        color: var(--accent-green);
        margin-bottom: 0.25rem;
        opacity: 0;
        transform: translateX(-20px);
        transition: all 0.5s ease;
    }

    @keyframes float-particle {
        0% {
            transform: translateY(100vh) translateX(0);
            opacity: 0;
        }
        10% {
            opacity: 1;
        }
        90% {
            opacity: 1;
        }
        100% {
            transform: translateY(-10px) translateX(20px);
            opacity: 0;
        }
    }

    .tactical-brand .brand-text {
        color: var(--accent-green);
    }

    .tactical-brand .brand-suffix {
        color: var(--accent-orange);
        font-size: 0.8em;
    }


`;

// Inject tactical styles
const styleSheet = document.createElement('style');
styleSheet.textContent = tacticalCSS;
document.head.appendChild(styleSheet);

// Initialize tactical interface when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.tacticalInterface = new TacticalInterface();
});

// Export for global access
window.TacticalInterface = TacticalInterface;