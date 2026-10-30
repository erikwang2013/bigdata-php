/* Standalone docs page behavior. Loaded by scripts/build-docs-page.py. */
(() => {
  const links = Array.from(document.querySelectorAll('.docs-sidebar a[href^="#"]'));
  const mobileLinks = Array.from(document.querySelectorAll('.docs-mobile-nav a[href^="#"]'));
  const headings = links.map((a) => document.getElementById(a.getAttribute('href').slice(1))).filter(Boolean);
  const setActive = (id) => {
    links.forEach((a) => a.classList.toggle('is-active', a.getAttribute('href') === '#' + id));
    mobileLinks.forEach((a) => a.classList.toggle('is-active', a.getAttribute('href') === '#' + id));
  };
  [...links, ...mobileLinks].forEach((a) => a.addEventListener('click', () => setActive(a.getAttribute('href').slice(1))));
  const header = document.querySelector('.site-header');
  const toTop = document.querySelector('.pet-top');
  const update = () => {
    const y = window.scrollY;
    if (header) header.classList.toggle('is-scrolled', y > 8);
    if (toTop) toTop.classList.toggle('is-visible', y > 480);
    if (!headings.length) return;
    const probe = y + (header ? header.offsetHeight : 64) + 80;
    let current = headings[0];
    for (const heading of headings) {
      if (heading.getBoundingClientRect().top + y <= probe) current = heading;
      else break;
    }
    setActive(current.id);
  };
  update();
  window.addEventListener('scroll', update, { passive: true });
})();
