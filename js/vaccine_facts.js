document.addEventListener('DOMContentLoaded', function() {
    // Mobile menu toggle
    const menuToggle = document.querySelector('.menu-toggle');
    const navMenu = document.getElementById('nav-menu');
    
    menuToggle.addEventListener('click', function() {
        navMenu.classList.toggle('active');
        this.classList.toggle('active');
    });
    
    // Filter vaccines
    const filterButtons = document.querySelectorAll('.filter-btn');
    const vaccineCards = document.querySelectorAll('.vaccine-card');
    
    filterButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Remove active class from all buttons
            filterButtons.forEach(btn => btn.classList.remove('active'));
            // Add active class to clicked button
            this.classList.add('active');
            
            const filterValue = this.dataset.filter;
            
            vaccineCards.forEach(card => {
                if (filterValue === 'all' || card.dataset.category === filterValue) {
                    card.style.display = 'block';
                    card.classList.add('animate__animated', 'animate__fadeIn');
                } else {
                    card.style.display = 'none';
                }
            });
        });
    });
    
    // Search functionality
    const searchInput = document.getElementById('vaccine-search');
    const searchBtn = document.getElementById('search-btn');
    
    searchBtn.addEventListener('click', performSearch);
    searchInput.addEventListener('keyup', function(e) {
        if (e.key === 'Enter') performSearch();
    });
    
    function performSearch() {
        const searchTerm = searchInput.value.toLowerCase();
        
        vaccineCards.forEach(card => {
            const title = card.querySelector('h3').textContent.toLowerCase();
            const description = card.querySelector('p').textContent.toLowerCase();
            
            if (title.includes(searchTerm) || description.includes(searchTerm)) {
                card.style.display = 'block';
                card.classList.add('animate__animated', 'animate__fadeIn');
            } else {
                card.style.display = 'none';
            }
        });
    }
    
    // Flip card functionality
    const vaccineCardsAll = document.querySelectorAll('.vaccine-card');
    
    vaccineCardsAll.forEach(card => {
        const infoBtn = card.querySelector('.info-btn');
        const backBtn = card.querySelector('.back-btn');
        
        if (infoBtn && backBtn) {
            infoBtn.addEventListener('click', function() {
                card.classList.add('flipped');
            });
            
            backBtn.addEventListener('click', function() {
                card.classList.remove('flipped');
            });
        }
    });
    
    // FAQ accordion
    const faqQuestions = document.querySelectorAll('.faq-question');
    
    faqQuestions.forEach(question => {
        question.addEventListener('click', function() {
            const faqItem = this.parentElement;
            const answer = this.nextElementSibling;
            const icon = this.querySelector('i');
            
            // Toggle active class
            faqItem.classList.toggle('active');
            
            // Toggle icon
            if (faqItem.classList.contains('active')) {
                icon.classList.remove('fa-plus');
                icon.classList.add('fa-times');
            } else {
                icon.classList.remove('fa-times');
                icon.classList.add('fa-plus');
            }
        });
    });
    
    // Modal functionality
    const modal = document.getElementById('vaccine-modal');
    const closeModal = document.querySelector('.close-modal');
    
    // Close modal when clicking X
    closeModal.addEventListener('click', function() {
        modal.classList.remove('show');
    });
    
    // Close modal when clicking outside
    window.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.classList.remove('show');
        }
    });
    
    // Smooth scrolling for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            
            const targetId = this.getAttribute('href');
            if (targetId === '#') return;
            
            const targetElement = document.querySelector(targetId);
            if (targetElement) {
                targetElement.scrollIntoView({
                    behavior: 'smooth'
                });
            }
        });
    });
    
    // Add animation to elements when they come into view
    const animateOnScroll = function() {
        const elements = document.querySelectorAll('.vaccine-card, .timeline-event, .faq-item');
        
        elements.forEach(element => {
            const elementPosition = element.getBoundingClientRect().top;
            const windowHeight = window.innerHeight;
            
            if (elementPosition < windowHeight - 100) {
                element.classList.add('animate__animated', 'animate__fadeInUp');
            }
        });
    };
    
    // Run once on load and then on scroll
    animateOnScroll();
    window.addEventListener('scroll', animateOnScroll);
});