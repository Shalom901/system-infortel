document.addEventListener('DOMContentLoaded', () => {
    // Sidebar toggle
    const toggleBtn = document.getElementById('toggleSidebar');
    const sidebar = document.getElementById('sidebar');
    
    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', () => {
            if (sidebar.classList.contains('-ml-64')) {
                sidebar.classList.remove('-ml-64');
            } else {
                sidebar.classList.add('-ml-64');
            }
        });
    }

    // Auto dismiss flash messages
    setTimeout(() => {
        const flashes = document.querySelectorAll('.px-6.pt-4');
        flashes.forEach(f => {
            f.style.transition = 'opacity 0.5s';
            f.style.opacity = '0';
            setTimeout(() => f.remove(), 500);
        });
    }, 5000);
});
