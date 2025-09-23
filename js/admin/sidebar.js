// ===== SIDEBAR TOGGLE FUNCTIONALITY =====
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.querySelector('.sidebar');
    const hamburgerBtn = document.querySelector('.hamburger-btn');
    const mainContent = document.querySelector('.main-content');
  
    // Toggle sidebar collapse/expand
    hamburgerBtn.addEventListener('click', function() {
      sidebar.classList.toggle('collapsed');
      hamburgerBtn.classList.toggle('active');
      
      // For mobile devices, we might want different behavior
      if (window.innerWidth <= 768) {
        if (sidebar.classList.contains('collapsed')) {
          sidebar.style.transform = 'translateX(-100%)';
        } else {
          sidebar.style.transform = 'translateX(0)';
        }
      }
    });
  
    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(event) {
      if (window.innerWidth <= 768 && !sidebar.contains(event.target) && event.target !== hamburgerBtn) {
        sidebar.classList.add('collapsed');
        hamburgerBtn.classList.remove('active');
        sidebar.style.transform = 'translateX(-100%)';
      }
    });
  
    // ===== DROPDOWN MENU FUNCTIONALITY =====
    const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
  
    dropdownToggles.forEach(toggle => {
      // Initialize dropdown state
      const dropdownMenu = toggle.nextElementSibling;
      let isOpen = false;
  
      toggle.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        // Close all other dropdowns first
        document.querySelectorAll('.dropdown-menu').forEach(menu => {
          if (menu !== dropdownMenu) {
            menu.style.maxHeight = '0';
            menu.previousElementSibling.classList.remove('active');
          }
        });
  
        // Toggle current dropdown
        if (isOpen) {
          dropdownMenu.style.maxHeight = '0';
          toggle.classList.remove('active');
        } else {
          dropdownMenu.style.maxHeight = dropdownMenu.scrollHeight + 'px';
          toggle.classList.add('active');
        }
        
        isOpen = !isOpen;
      });
  
      // Close dropdown when clicking outside
      document.addEventListener('click', function() {
        if (isOpen) {
          dropdownMenu.style.maxHeight = '0';
          toggle.classList.remove('active');
          isOpen = false;
        }
      });
    });
  
    // ===== ACTIVE LINK HIGHLIGHTING =====
    const navLinks = document.querySelectorAll('.sidebar a');
  
    navLinks.forEach(link => {
      // Check if the link's href matches the current URL
      if (link.href === window.location.href) {
        link.classList.add('active');
        
        // If this is a dropdown item, also expand its parent
        const parentDropdown = link.closest('.dropdown-menu');
        if (parentDropdown) {
          parentDropdown.style.maxHeight = parentDropdown.scrollHeight + 'px';
          parentDropdown.previousElementSibling.classList.add('active');
        }
      }
  
      // Add click handler to set active state
      link.addEventListener('click', function() {
        navLinks.forEach(l => l.classList.remove('active'));
        this.classList.add('active');
      });
    });
  
    // ===== RESPONSIVE BEHAVIOR =====
    function handleResponsive() {
      if (window.innerWidth <= 768) {
        sidebar.classList.add('collapsed');
        hamburgerBtn.classList.remove('active');
      } else {
        sidebar.classList.remove('collapsed');
      }
    }
  
    // Run on load and on resize
    handleResponsive();
    window.addEventListener('resize', handleResponsive);
  });// ===== SIDEBAR TOGGLE FUNCTIONALITY =====
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.querySelector('.sidebar');
    const hamburgerBtn = document.querySelector('.hamburger-btn');
    const mainContent = document.querySelector('.main-content');
  
    // Toggle sidebar collapse/expand
    hamburgerBtn.addEventListener('click', function() {
      sidebar.classList.toggle('collapsed');
      hamburgerBtn.classList.toggle('active');
      
      // For mobile devices, we might want different behavior
      if (window.innerWidth <= 768) {
        if (sidebar.classList.contains('collapsed')) {
          sidebar.style.transform = 'translateX(-100%)';
        } else {
          sidebar.style.transform = 'translateX(0)';
        }
      }
    });
  
    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(event) {
      if (window.innerWidth <= 768 && !sidebar.contains(event.target) && event.target !== hamburgerBtn) {
        sidebar.classList.add('collapsed');
        hamburgerBtn.classList.remove('active');
        sidebar.style.transform = 'translateX(-100%)';
      }
    });
  
    // ===== DROPDOWN MENU FUNCTIONALITY =====
    const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
  
    dropdownToggles.forEach(toggle => {
      // Initialize dropdown state
      const dropdownMenu = toggle.nextElementSibling;
      let isOpen = false;
  
      toggle.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        // Close all other dropdowns first
        document.querySelectorAll('.dropdown-menu').forEach(menu => {
          if (menu !== dropdownMenu) {
            menu.style.maxHeight = '0';
            menu.previousElementSibling.classList.remove('active');
          }
        });
  
        // Toggle current dropdown
        if (isOpen) {
          dropdownMenu.style.maxHeight = '0';
          toggle.classList.remove('active');
        } else {
          dropdownMenu.style.maxHeight = dropdownMenu.scrollHeight + 'px';
          toggle.classList.add('active');
        }
        
        isOpen = !isOpen;
      });
  
      // Close dropdown when clicking outside
      document.addEventListener('click', function() {
        if (isOpen) {
          dropdownMenu.style.maxHeight = '0';
          toggle.classList.remove('active');
          isOpen = false;
        }
      });
    });
  
    // ===== ACTIVE LINK HIGHLIGHTING =====
    const navLinks = document.querySelectorAll('.sidebar a');
  
    navLinks.forEach(link => {
      // Check if the link's href matches the current URL
      if (link.href === window.location.href) {
        link.classList.add('active');
        
        // If this is a dropdown item, also expand its parent
        const parentDropdown = link.closest('.dropdown-menu');
        if (parentDropdown) {
          parentDropdown.style.maxHeight = parentDropdown.scrollHeight + 'px';
          parentDropdown.previousElementSibling.classList.add('active');
        }
      }
  
      // Add click handler to set active state
      link.addEventListener('click', function() {
        navLinks.forEach(l => l.classList.remove('active'));
        this.classList.add('active');
      });
    });
  
    // ===== RESPONSIVE BEHAVIOR =====
    function handleResponsive() {
      if (window.innerWidth <= 768) {
        sidebar.classList.add('collapsed');
        hamburgerBtn.classList.remove('active');
      } else {
        sidebar.classList.remove('collapsed');
      }
    }
  
    // Run on load and on resize
    handleResponsive();
    window.addEventListener('resize', handleResponsive);
  });