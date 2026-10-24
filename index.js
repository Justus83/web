/* ── Hero Slider ─────────────────────────────────────────────────────── */
(function () {
  const slides = document.querySelectorAll('.slide');
  const dots   = document.querySelectorAll('.dot');
  let cur = 0, timer;

  function go(n) {
    slides[cur].classList.remove('active');
    dots[cur].classList.remove('active');
    cur = (n + slides.length) % slides.length;
    slides[cur].classList.add('active');
    dots[cur].classList.add('active');
  }

  function start() { timer = setInterval(() => go(cur + 1), 4500); }
  function reset() { clearInterval(timer); start(); }

  document.getElementById('snext').onclick = () => { go(cur + 1); reset(); };
  document.getElementById('sprev').onclick = () => { go(cur - 1); reset(); };
  dots.forEach(d => d.addEventListener('click', () => { go(+d.dataset.i); reset(); }));

  const sl = document.getElementById('slider');
  sl.addEventListener('mouseenter', () => clearInterval(timer));
  sl.addEventListener('mouseleave', start);

  let tx = 0;
  sl.addEventListener('touchstart', e => { tx = e.touches[0].clientX; }, { passive: true });
  sl.addEventListener('touchend', e => {
    const d = tx - e.changedTouches[0].clientX;
    if (Math.abs(d) > 40) { go(d > 0 ? cur + 1 : cur - 1); reset(); }
  });

  start();
})();

/* ── Trending Carousel ───────────────────────────────────────────────── */
(function () {
  const track = document.getElementById('ttrack');
  const prev  = document.getElementById('tprev');
  const next  = document.getElementById('tnext');
  if (!track) return;

  let off = 0;
  const gap   = () => { const c = track.querySelector('.pcard'); return c ? c.offsetWidth + 10 : 155; };
  const maxOff = () => Math.max(0, track.scrollWidth - track.parentElement.offsetWidth);

  function upd() {
    track.style.transform = `translateX(-${off}px)`;
    prev.style.opacity = off <= 0 ? '.4' : '1';
    next.style.opacity = off >= maxOff() ? '.4' : '1';
  }

  next.onclick = () => { off = Math.min(off + gap() * 2, maxOff()); upd(); };
  prev.onclick = () => { off = Math.max(off - gap() * 2, 0); upd(); };

  let tx = 0;
  track.addEventListener('touchstart', e => { tx = e.touches[0].clientX; }, { passive: true });
  track.addEventListener('touchend', e => {
    const d = tx - e.changedTouches[0].clientX;
    if (Math.abs(d) > 30) { off = d > 0 ? Math.min(off + gap(), maxOff()) : Math.max(off - gap(), 0); upd(); }
  });

  upd();
})();

/* ── Side category click ─────────────────────────────────────────────── */
document.querySelectorAll('.scat').forEach(c => {
  c.addEventListener('click', () => {
    document.querySelectorAll('.scat').forEach(x => x.classList.remove('active'));
    c.classList.add('active');
  });
});

/* ── Header shadow on scroll ─────────────────────────────────────────── */
window.addEventListener('scroll', () => {
  document.querySelector('.header').style.boxShadow =
    scrollY > 8 ? '0 2px 14px rgba(0,0,0,.1)' : '0 1px 3px rgba(0,0,0,.06)';
}, { passive: true });

/* ── Init icons & cart badge ─────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', function () {
  if (window.lucide) lucide.createIcons();
  if (window.updateBadge) updateBadge();
});
