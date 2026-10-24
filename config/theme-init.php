<?php
/* ── Inline script to apply saved dark mode before first paint ── */
?>
<script>
(function(){
  try {
    var dark = localStorage.getItem('gg_dark_mode');
    var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    if (dark === '1' || (dark === null && prefersDark)) {
      document.documentElement.setAttribute('data-theme','dark');
    }
  } catch(e){}
})();
</script>
