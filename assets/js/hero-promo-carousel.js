(function () {

  const root = document.getElementById('hero-promo-carousel');

  if (!root) return;



  const slides = Array.from(root.querySelectorAll('.hero-promo-slide'));

  if (slides.length <= 1) return;



  const dotsWrap = root.querySelector('.hero-promo-dots');

  const dots = dotsWrap ? Array.from(dotsWrap.querySelectorAll('[data-slide-dot]')) : [];

  const counter = dotsWrap?.querySelector('.hero-promo-counter');

  const interval = Math.max(2500, Math.min(20000, Number(root.dataset.interval) || 5000));



  let index = 0;

  let timer = null;



  function pad(n) {

    return String(n + 1).padStart(2, '0');

  }



  function setActive(next) {

    if (next === index || !slides[next]) return;

    slides[index].classList.remove('is-active');

    slides[index].setAttribute('aria-hidden', 'true');

    slides[index].setAttribute('tabindex', '-1');



    index = next;



    slides[index].classList.add('is-active');

    slides[index].removeAttribute('aria-hidden');

    slides[index].setAttribute('tabindex', '0');



    dots.forEach((dot, i) => dot.classList.toggle('is-active', i === index));

    if (counter) {
      const total = String(slides.length).padStart(2, '0');
      counter.textContent = pad(index) + ' / ' + total;
    }

  }



  function next() {

    setActive((index + 1) % slides.length);

  }



  function start() {

    stop();

    timer = window.setInterval(next, interval);

  }



  function stop() {

    if (timer !== null) {

      window.clearInterval(timer);

      timer = null;

    }

  }



  dots.forEach((dot) => {

    dot.addEventListener('click', (event) => {

      event.preventDefault();

      const target = Number(dot.dataset.slideDot);

      if (!Number.isNaN(target)) {

        setActive(target);

        start();

      }

    });

  });



  root.addEventListener('mouseenter', stop);

  root.addEventListener('mouseleave', start);

  root.addEventListener('focusin', stop);

  root.addEventListener('focusout', start);



  document.addEventListener('visibilitychange', () => {

    if (document.hidden) stop();

    else start();

  });



  start();

})();


