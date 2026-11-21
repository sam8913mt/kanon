/** app.js — راه‌اندازی مشترک همه‌ی صفحات: Header/Footer، SEO پایه، تم */
document.addEventListener('DOMContentLoaded', layout);

/** ورود نرم بخش‌ها هنگام اسکرول (روی موبایل و دسکتاپ یکسان؛ با reduced-motion خاموش) */
const _io = 'IntersectionObserver' in window && !matchMedia('(prefers-reduced-motion:reduce)').matches
  ? new IntersectionObserver(es => es.forEach(e => { if (e.isIntersecting) { e.target.classList.add('in'); _io.unobserve(e.target); } }), { threshold: .08 }) : null;
function reveal(els) { [...els].forEach(x => { if (_io) { x.classList.add('rv'); _io.observe(x); } }); }
document.addEventListener('DOMContentLoaded', () => reveal(document.querySelectorAll('main > section, main > .band')));
