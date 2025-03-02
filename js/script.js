// Optional: Add more interactivity later (e.g., toggle sidebar)
document.querySelectorAll('.stat-card').forEach(card => {
    card.addEventListener('mouseenter', () => {
        card.style.background = '#e6f0ff';
    });
    card.addEventListener('mouseleave', () => {
        card.style.background = '#f9fbfc';
    });
});

// Check for saved theme preference
// Check saved theme preference
const savedTheme = localStorage.getItem('theme');
if (savedTheme === 'dark') {
    document.body.classList.add('dark-mode');
    document.querySelectorAll('.theme-toggle-btn .fa-sun').forEach(sun => sun.style.display = 'none');
    document.querySelectorAll('.theme-toggle-btn .fa-moon').forEach(moon => moon.style.display = 'block');
}

// Toggle dark mode
document.getElementById('theme-toggle').addEventListener('click', () => {
    document.body.classList.toggle('dark-mode');
    const isDark = document.body.classList.contains('dark-mode');
    localStorage.setItem('theme', isDark ? 'dark' : 'light');
    // Switch icons
    document.querySelectorAll('.theme-toggle-btn .fa-sun').forEach(sun => sun.style.display = isDark ? 'none' : 'block');
    document.querySelectorAll('.theme-toggle-btn .fa-moon').forEach(moon => moon.style.display = isDark ? 'block' : 'none');
});

// Stat card hover effect
document.querySelectorAll('.stat-card').forEach(card => {
    card.addEventListener('mouseenter', () => {
        card.style.background = document.body.classList.contains('dark-mode') ? '#1a1a2e' : '#e6f0ff';
    });
    card.addEventListener('mouseleave', () => {
        card.style.background = document.body.classList.contains('dark-mode') ? '#16213e' : '#f9fbfc';
    });
});

