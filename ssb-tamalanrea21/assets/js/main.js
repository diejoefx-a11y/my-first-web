// SSB Tamalanrea - Main Interactive JS
document.addEventListener('DOMContentLoaded', () => {
    // 1. Mobile Sidebar Toggle & Dynamic Backdrop Overlay
    const menuToggle = document.getElementById('menuToggle');
    const sidebarClose = document.getElementById('sidebarClose');
    const sidebar = document.querySelector('.sidebar');
    
    // Dynamic overlay element
    let overlay = document.querySelector('.sidebar-overlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        document.body.appendChild(overlay);
    }

    function openSidebar() {
        if (sidebar) sidebar.classList.add('open');
        if (overlay) overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        if (sidebar) sidebar.classList.remove('open');
        if (overlay) overlay.classList.remove('active');
        document.body.style.overflow = 'auto';
    }

    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            if (sidebar.classList.contains('open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });
    }

    if (sidebarClose) {
        sidebarClose.addEventListener('click', closeSidebar);
    }

    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }

    // Auto close sidebar when a navigation link is clicked on mobile
    const navLinks = document.querySelectorAll('.sidebar .nav-link');
    navLinks.forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= 992) {
                closeSidebar();
            }
        });
    });

    // 2. Image Upload Live Preview
    const fotoInput = document.getElementById('fotoInput');
    const fotoPreview = document.getElementById('fotoPreview');

    if (fotoInput && fotoPreview) {
        fotoInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    fotoPreview.src = e.target.result;
                }
                reader.readAsDataURL(file);
            }
        });
    }

    // 3. Quick Table Live Search Filter
    const searchInput = document.getElementById('tableSearch');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            const rows = document.querySelectorAll('.data-table tbody tr');

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        });
    }
});

// 4. Print ID Card Function
function printCard(cardId) {
    const cardElement = document.getElementById(cardId);
    if (!cardElement) return;

    const printWindow = window.open('', '', 'width=600,height=700');
    printWindow.document.write(`
        <html>
        <head>
            <title>Cetak Kartu Atlet - SSB Tamalanrea</title>
            <link rel="stylesheet" href="../assets/css/style.css">
            <style>
                body { background: #fff; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; }
                .id-card { border: 2px solid #6366f1; box-shadow: none; }
            </style>
        </head>
        <body onload="window.print(); window.close();">
            ${cardElement.outerHTML}
        </body>
        </html>
    `);
    printWindow.document.close();
}
