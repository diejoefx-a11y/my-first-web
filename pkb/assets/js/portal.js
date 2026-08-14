/**
 * Portal Berita & Landing Page Dynamic Interactions (Mobile-First & Touch Optimized)
 */
document.addEventListener('DOMContentLoaded', function () {
    // 1. Mobile Off-Canvas Drawer Toggle
    const btnOpenDrawer = document.getElementById('btn-open-drawer');
    const btnCloseDrawer = document.getElementById('btn-close-drawer');
    const drawer = document.getElementById('mobile-drawer');
    const backdrop = document.getElementById('drawer-backdrop');
    const drawerLinks = document.querySelectorAll('.drawer-menu a');

    function openDrawer() {
        if (drawer && backdrop) {
            drawer.classList.add('open');
            backdrop.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    }

    function closeDrawer() {
        if (drawer && backdrop) {
            drawer.classList.remove('open');
            backdrop.classList.remove('active');
            document.body.style.overflow = '';
        }
    }

    if (btnOpenDrawer) btnOpenDrawer.addEventListener('click', openDrawer);
    if (btnCloseDrawer) btnCloseDrawer.addEventListener('click', closeDrawer);
    if (backdrop) backdrop.addEventListener('click', closeDrawer);
    drawerLinks.forEach(link => link.addEventListener('click', closeDrawer));

    // 2. Dynamic Live Date Widget (WITA / Makassar)
    const dateDisplay = document.getElementById('live-date-display');
    if (dateDisplay) {
        const options = {
            timeZone: 'Asia/Makassar',
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        };
        const today = new Date().toLocaleDateString('id-ID', options);
        dateDisplay.textContent = `🗓️ ${today}`;
    }

    // 3. Category Filter Tabs (Swipeable on Mobile)
    const catBtns = document.querySelectorAll('.cat-btn');
    const newsCards = document.querySelectorAll('.news-card-item');
    const searchInput = document.getElementById('portal-search-input');

    function filterArticles() {
        const activeBtn = document.querySelector('.cat-btn.active');
        const selectedCategory = activeBtn ? activeBtn.getAttribute('data-category').toLowerCase() : 'all';
        const searchQuery = searchInput ? searchInput.value.trim().toLowerCase() : '';

        newsCards.forEach(card => {
            const cardCategory = (card.getAttribute('data-category') || '').toLowerCase();
            const cardTitle = (card.querySelector('.news-item-title') ? card.querySelector('.news-item-title').textContent : '').toLowerCase();
            const cardExcerpt = (card.querySelector('.news-item-excerpt') ? card.querySelector('.news-item-excerpt').textContent : '').toLowerCase();

            const matchesCategory = (selectedCategory === 'all' || cardCategory === selectedCategory);
            const matchesSearch = (searchQuery === '' || cardTitle.includes(searchQuery) || cardExcerpt.includes(searchQuery));

            if (matchesCategory && matchesSearch) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        });
    }

    catBtns.forEach(btn => {
        btn.addEventListener('click', function () {
            catBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            filterArticles();
        });
    });

    if (searchInput) {
        searchInput.addEventListener('input', filterArticles);
    }

    // 4. Modal Article Reader
    const modal = document.getElementById('article-modal');
    const modalClose = document.getElementById('modal-close-btn');
    const modalImg = document.getElementById('modal-article-img');
    const modalCategory = document.getElementById('modal-article-category');
    const modalTitle = document.getElementById('modal-article-title');
    const modalMeta = document.getElementById('modal-article-meta');
    const modalContent = document.getElementById('modal-article-content');
    const modalShareWa = document.getElementById('modal-share-wa');

    function openArticleModal(articleData) {
        if (!modal) return;

        modalImg.src = articleData.image || 'https://images.unsplash.com/photo-1577495508048-b635879837f1?auto=format&fit=crop&w=800&q=80';
        modalCategory.textContent = articleData.category;
        modalCategory.className = 'badge-tag ' + getCategoryClass(articleData.category);
        modalTitle.textContent = articleData.title;

        let groupTag = articleData.group_name ? ` • 🏷️ <strong style="color:#7c3aed;">${articleData.group_name}</strong>` : '';
        modalMeta.innerHTML = `✍️ <strong>${articleData.author}</strong>${groupTag} • 📅 ${articleData.date} • 👁️ ${articleData.views}x dibaca`;
        modalContent.innerHTML = articleData.content;

        if (modalShareWa) {
            const shareText = encodeURIComponent(`*${articleData.title}*\n\nBaca selengkapnya di Portal Informasi PKB.`);
            modalShareWa.href = `https://api.whatsapp.com/send?text=${shareText}`;
        }

        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    }

    function getCategoryClass(cat) {
        cat = (cat || '').toLowerCase();
        if (cat.includes('pengumuman')) return 'tag-pengumuman';
        if (cat.includes('kesehatan')) return 'tag-kesehatan';
        if (cat.includes('bansos')) return 'tag-bansos';
        if (cat.includes('kegiatan')) return 'tag-kegiatan';
        return '';
    }

    if (modalClose) modalClose.addEventListener('click', closeModal);
    if (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === modal) closeModal();
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeModal();
            closeDrawer();
        }
    });

    // Delegate click to all article cards
    document.querySelectorAll('.open-article-trigger').forEach(trigger => {
        trigger.addEventListener('click', function (e) {
            e.preventDefault();
            const rawData = this.getAttribute('data-article');
            if (rawData) {
                try {
                    const parsed = JSON.parse(rawData);
                    openArticleModal(parsed);
                } catch (err) {
                    console.error('Error parsing article JSON:', err);
                }
            }
        });
    });
});
