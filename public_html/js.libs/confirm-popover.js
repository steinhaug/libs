/* 
 * Confirm-popover: erstatter confirm() med en Ja/Nei-boble over knappen.
 * Bruk: <a href="/slett/5" data-confirm="Slette produktet?">Slett</a>
 * Fungerer med lenker, submit-knapper og eksisterende onclick-handlere,
 * fordi klikket stoppes i capture-fasen og spilles av på nytt ved "Ja".
 */
(() => {
  document.head.insertAdjacentHTML('beforeend', `<style>
    .cpop{position:absolute;z-index:1080;display:flex;gap:6px;align-items:center;
      background:#fff;border:1px solid rgba(0,0,0,.2);border-radius:6px;padding:6px 8px;
      box-shadow:0 4px 12px rgba(0,0,0,.15);font-size:14px;white-space:nowrap;
      flex-direction: column;}

    .cpop{max-width:min(280px, calc(100vw - 16px))}
    .cpop span{white-space:pre-line; overflow-wrap:anywhere}

  </style>`);

  let pop = null;
  const close = () => { pop?.remove(); pop = null; };

  document.addEventListener('click', e => {
    const el = e.target.closest('[data-confirm]');

    if (pop && !pop.contains(e.target) && el !== pop._trigger) close();
    if (!el) return;

    if (el._confirmed) { el._confirmed = false; return; }

    e.preventDefault();
    e.stopPropagation();

    if (pop?._trigger === el) return close();
    close();

    pop = document.createElement('div');
    pop.className = 'cpop';
    pop._trigger = el;
    pop.innerHTML = '<span></span>'
      + '<div>'
      + '<button type="button" class="btn btn-sm btn-danger" data-a="yes">Ja</button>'
      + '<button type="button" class="btn btn-sm btn-secondary" data-a="no">Nei</button>'
      + '</div>';
    pop.firstChild.textContent = el.dataset.confirm || 'Er du sikker?';
    document.body.append(pop);

    //const r = el.getBoundingClientRect();
    //let top = r.top + scrollY - pop.offsetHeight - 8;
    //if (top < scrollY) top = r.bottom + scrollY + 8;
    //pop.style.top = `${top}px`;
    //pop.style.left = `${Math.max(8, r.left + scrollX + r.width / 2 - pop.offsetWidth / 2)}px`;

    const gap = 8;
    const vw = document.documentElement.clientWidth;
    const r = el.getBoundingClientRect();
    const w = pop.offsetWidth, h = pop.offsetHeight;

    let top = r.top - h - gap;
    if (top < gap) top = r.bottom + gap;

    let left = r.left + r.width / 2 - w / 2;
    left = Math.min(Math.max(gap, left), vw - w - gap);

    pop.style.top = `${top + scrollY}px`;
    pop.style.left = `${left + scrollX}px`;
    // -

    pop.addEventListener('click', ev => {
      const a = ev.target.dataset.a;
      if (!a) return;
      close();
      if (a === 'yes') { el._confirmed = true; el.click(); }
    });

    pop.querySelector('[data-a="no"]').focus();
  }, true);

  document.addEventListener('keydown', e => e.key === 'Escape' && close());
})();
