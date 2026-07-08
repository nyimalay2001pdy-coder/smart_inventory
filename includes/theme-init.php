<script>
(function() {
  // Read saved theme from localStorage (client-side only)
  var savedTheme = localStorage.getItem('theme') || 'dark';

  // Apply theme immediately before body renders (prevents flash)
  if (savedTheme === 'dark') {
    document.documentElement.classList.add('dark');
  } else if (savedTheme === 'light') {
    document.documentElement.classList.remove('dark');
  } else {
    // System: follow OS preference
    if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
      document.documentElement.classList.add('dark');
    } else {
      document.documentElement.classList.remove('dark');
    }
  }

  // Listen for real-time OS preference changes (only when in 'system' mode)
  window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
    var currentTheme = localStorage.getItem('theme');
    if (currentTheme === 'system') {
      if (e.matches) {
        document.documentElement.classList.add('dark');
      } else {
        document.documentElement.classList.remove('dark');
      }
    }
  });

  // Enable smooth transitions after page load
  requestAnimationFrame(function() {
    requestAnimationFrame(function() {
      document.documentElement.classList.add('transition-enabled');
    });
  });
})();
</script>
<script>
  tailwind.config = {
    darkMode: 'class',
  }
</script>
