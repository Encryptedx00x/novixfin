document.addEventListener('DOMContentLoaded', () => {
    // Sidebar mobile toggle e recolhível
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const sidebarCollapse = document.getElementById('sidebarCollapse');
    const mainContent = document.getElementById('mainContent');
    
    // Lê estado salvo
    const loadCollapseState = () => localStorage.getItem('sidebarCollapsed') === 'true';
    const saveCollapseState = (state) => localStorage.setItem('sidebarCollapsed', state ? 'true' : 'false');
    
    // Aplica estado recolhido/expandido (desktop)
    const applyCollapse = (collapsed) => {
        if (!sidebar) return;
        
        // Adiciona/remove classe com pequeno delay para garantir que os estilos CSS sejam aplicados
        requestAnimationFrame(() => {
            if (collapsed) {
                sidebar.classList.add('sidebar-collapsed');
            } else {
                sidebar.classList.remove('sidebar-collapsed');
            }
            
            // Ajusta margem do conteúdo após a transição da sidebar (apenas desktop)
            setTimeout(() => {
                if (mainContent && window.innerWidth >= 1024) {
                    if (collapsed) {
                        mainContent.style.marginLeft = '4.5rem';
                    } else {
                        mainContent.style.marginLeft = '16rem';
                    }
                } else if (mainContent && window.innerWidth < 1024) {
                    mainContent.style.marginLeft = '0';
                }
            }, 50);
        });
        
        if (sidebarCollapse) {
            const icon = sidebarCollapse.querySelector('svg');
            if (icon) {
                icon.style.transform = collapsed ? 'rotate(180deg)' : 'rotate(0deg)';
            }
        }
    };
    
    // Inicializa estado no desktop
    const initCollapse = () => {
        const isDesktop = window.innerWidth >= 1024;
        const collapsed = loadCollapseState();
        if (isDesktop) {
            applyCollapse(collapsed);
        } else {
            applyCollapse(false);
        }
    };
    
    if (sidebarToggle && sidebar && sidebarOverlay) {
        const openSidebar = () => {
            sidebar.classList.remove('-translate-x-full');
            sidebarOverlay.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        };
        
        const closeSidebar = () => {
            sidebar.classList.add('-translate-x-full');
            sidebarOverlay.classList.add('hidden');
            document.body.style.overflow = '';
        };
        
        sidebarToggle.addEventListener('click', openSidebar);
        sidebarOverlay.addEventListener('click', closeSidebar);
        
        // Fecha ao clicar em link na sidebar (mobile)
        if (window.innerWidth < 1024) {
            sidebar.querySelectorAll('a').forEach(link => {
                link.addEventListener('click', closeSidebar);
            });
        }
    }
    
    if (sidebarCollapse) {
        sidebarCollapse.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const isCurrentlyCollapsed = sidebar.classList.contains('sidebar-collapsed');
            const willCollapse = !isCurrentlyCollapsed;
            applyCollapse(willCollapse);
            saveCollapseState(willCollapse);
        });
    }
    
    window.addEventListener('resize', () => {
        initCollapse();
    });
    
    // Aplica estado inicial
    initCollapse();
    
    // Previne arraste horizontal na sidebar
    if (sidebar) {
        let isDragging = false;
        let startX = 0;
        let currentX = 0;
        
        sidebar.addEventListener('mousedown', (e) => {
            if (e.target.closest('nav a, .sidebar-logout-btn')) {
                isDragging = false;
                return;
            }
            isDragging = true;
            startX = e.clientX;
            currentX = startX;
        });
        
        sidebar.addEventListener('mousemove', (e) => {
            if (!isDragging) return;
            currentX = e.clientX;
            const diffX = currentX - startX;
            // Previne movimento se tentar arrastar lateralmente
            if (Math.abs(diffX) > 5) {
                e.preventDefault();
                isDragging = false;
            }
        });
        
        sidebar.addEventListener('mouseup', () => {
            isDragging = false;
        });
        
        sidebar.addEventListener('mouseleave', () => {
            isDragging = false;
        });
        
        // Previne touch drag em mobile
        let touchStartX = 0;
        sidebar.addEventListener('touchstart', (e) => {
            if (e.target.closest('nav a, .sidebar-logout-btn')) {
                touchStartX = e.touches[0].clientX;
            }
        }, { passive: true });
        
        sidebar.addEventListener('touchmove', (e) => {
            if (!touchStartX) return;
            const touchCurrentX = e.touches[0].clientX;
            const diffX = touchCurrentX - touchStartX;
            // Previne scroll horizontal
            if (Math.abs(diffX) > Math.abs(e.touches[0].clientY - (e.target.getBoundingClientRect().top + e.target.getBoundingClientRect().height / 2))) {
                e.preventDefault();
            }
        }, { passive: false });
    }
    
    const menuToggle = document.querySelector('[data-menu-toggle]');
    const menu = document.querySelector('[data-menu]');

    if (menuToggle && menu) {
        menuToggle.addEventListener('click', () => {
            menu.classList.toggle('hidden');
        });
    }

    const aiCards = document.querySelectorAll('[data-ai-card]');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-fade-in');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.3 });

    aiCards.forEach((card) => observer.observe(card));
});

