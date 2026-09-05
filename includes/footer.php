    </main>
</div><!-- .shell -->

<div class="toast" id="toast"></div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Sidebar Mobile Toggle
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('backdrop');
    const menuToggle = document.getElementById('menuToggle');
    const sidebarClose = document.getElementById('sidebarClose');

    const openSidebar = () => {
        if (sidebar && backdrop) {
            sidebar.classList.add('is-open');
            backdrop.classList.add('is-open');
        }
    };

    const closeSidebar = () => {
        if (sidebar && backdrop) {
            sidebar.classList.remove('is-open');
            backdrop.classList.remove('is-open');
        }
    };

    if (menuToggle) menuToggle.addEventListener('click', openSidebar);
    if (sidebarClose) sidebarClose.addEventListener('click', closeSidebar);
    if (backdrop) backdrop.addEventListener('click', closeSidebar);

    document.querySelectorAll('.nav a').forEach(a => {
        a.addEventListener('click', () => {
            if (window.innerWidth <= 900) closeSidebar();
        });
    });
});

function showToast(message, duration = 2800) {
    const toast = document.getElementById('toast');
    if (!toast) return;
    toast.textContent = message;
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), duration);
}
</script>
</body>
</html>
