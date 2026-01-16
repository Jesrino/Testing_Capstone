// Profile dropdown
function toggleDropdown() {
  const dropdown = document.getElementById('profile-dropdown');
  dropdown.classList.toggle('show');
}

// Close profile dropdown when clicking outside
document.addEventListener('click', function(event) {
  const profileMenu = document.querySelector('.profile-menu');
  const dropdown = document.getElementById('profile-dropdown');

  if (profileMenu && !profileMenu.contains(event.target)) {
    dropdown.classList.remove('show');
  }
});

// Toggle sidebar collapse (desktop)
function toggleSidebarCollapse() {
  const sidenav = document.querySelector('.sidenav');
  const main = document.querySelector('.site-main');
  const footer = document.querySelector('.footer');

  if (!sidenav || !main) return console.error("Sidebar or main wrapper missing");

  sidenav.classList.toggle('collapsed');
  main.classList.toggle('sidebar-collapsed');
  
  // Also toggle footer class so it shifts with the sidebar
  if (footer) {
    footer.classList.toggle('sidebar-collapsed');
  }
}

// Mobile sidebar open/close
function toggleSidebar() {
  const sidenav = document.querySelector('.sidenav');
  const overlay = document.querySelector('.sidenav-overlay');

  if (!sidenav || !overlay) return console.error("Sidebar or overlay missing");

  sidenav.classList.toggle('open');
  overlay.classList.toggle('active');
}

function closeSidebar() {
  const sidenav = document.querySelector('.sidenav');
  const overlay = document.querySelector('.sidenav-overlay');

  if (!sidenav || !overlay) return console.error("Sidebar or overlay missing");

  sidenav.classList.remove('open');
  overlay.classList.remove('active');
}
