<script>
  tailwind.config = { darkMode: 'class' };
</script>
<script>
(function() {
  var t = localStorage.getItem('theme') || 'dark';
  if (t === 'dark') {
    document.documentElement.classList.add('dark');
  } else {
    document.documentElement.classList.remove('dark');
  }
})();
</script>
