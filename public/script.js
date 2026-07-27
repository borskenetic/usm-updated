document.addEventListener('DOMContentLoaded', () => {
    const header = document.querySelector('header');
    const navToggle = document.querySelector('#navMenuToggle');
    const primaryNav = document.querySelector('#primaryNav');
    const loginBtn = document.querySelector('.login-btn');
    const aboutSection = document.querySelector('.about-section');
    const formBox = document.querySelector('.form-box');
    const contactInfo = document.querySelector('.contact-info-section');

    if (navToggle && primaryNav) {
        const setMenuState = (isOpen) => {
            primaryNav.classList.toggle('is-open', isOpen);
            navToggle.classList.toggle('is-open', isOpen);
            navToggle.setAttribute('aria-expanded', String(isOpen));
            navToggle.setAttribute('aria-label', isOpen ? 'Close menu' : 'Open menu');
        };

        navToggle.addEventListener('click', () => {
            setMenuState(!primaryNav.classList.contains('is-open'));
        });

        primaryNav.querySelectorAll('a').forEach((link) => {
            link.addEventListener('click', () => setMenuState(false));
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                setMenuState(false);
            }
        });

        window.addEventListener('resize', () => {
            if (window.innerWidth > 1100) {
                setMenuState(false);
            }
        });
    }

    if (loginBtn) {
        loginBtn.addEventListener('click', () => {
            window.location.href = '/login';
        });
    }

    if (header) {
        const hero = document.querySelector('.hero-section');
        const updateHeaderShadow = () => {
            if (header.classList.contains('navbar--hero') && hero) {
                const isOverHero = window.scrollY < hero.offsetTop + hero.offsetHeight - header.offsetHeight;
                header.classList.toggle('navbar--solid', !isOverHero);
                header.style.boxShadow = isOverHero ? 'none' : '0 2px 10px rgba(0,0,0,0.1)';
                return;
            }

            header.style.boxShadow = window.scrollY > 50 ? '0 2px 10px rgba(0,0,0,0.1)' : 'none';
        };

        updateHeaderShadow();
        window.addEventListener('scroll', updateHeaderShadow, { passive: true });
    }

    if (aboutSection && 'IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                }
            });
        }, { threshold: 0.2 });

        observer.observe(aboutSection);
    }

    if (contactInfo) {
        contactInfo.style.opacity = '0';
        contactInfo.style.transform = 'translateY(-20px)';

        setTimeout(() => {
            contactInfo.style.transition = 'all 0.8s ease-out';
            contactInfo.style.opacity = '1';
            contactInfo.style.transform = 'translateY(0)';
        }, 200);
    }

    if (formBox) {
        formBox.style.opacity = '0';
        formBox.style.transform = 'translateY(20px)';

        setTimeout(() => {
            formBox.style.transition = 'all 1s ease-out';
            formBox.style.opacity = '1';
            formBox.style.transform = 'translateY(0)';
        }, 500);
    }
});
