(() => {
  'use strict';
  // Rich-text editors may surround a pasted shortcode with code/pre tags.
  // HTML parsing reconstructs those tags inside the generated grids. Only
  // normalize live plugin roots; ordinary code examples elsewhere stay intact.
  const unwrap = node => node.replaceWith(...node.childNodes);
  document.querySelectorAll('.fwb-widget, .fwb-calendar').forEach(root => {
    let wrapper;
    while ((wrapper = root.closest('code, pre'))) unwrap(wrapper);
    root.querySelectorAll('code, pre').forEach(unwrap);
    // The HTML parser can leave empty formatting siblings before/after a block.
    // Remove only empty code wrappers directly adjacent to this live widget.
    const emptyCode = node => node && !node.textContent.trim() &&
      (node.matches('code, pre') || (node.matches('p') && node.querySelector('code, pre'))) &&
      !node.querySelector('img, input, button, iframe, video, svg');
    for (const side of ['previousElementSibling', 'nextElementSibling']) {
      while (emptyCode(root[side])) root[side].remove();
    }
  });
  const language = root => {const messages=JSON.parse(root.dataset.i18n||'{}');return (text,values={})=>Object.entries(values).reduce((out,[k,v])=>out.split('{'+k+'}').join(v),messages[text]||text);};
  const api = async (base, path, data) => {
    // Works with both pretty permalinks and WordPress' ?rest_route= fallback.
    const url = new URL(base, location.href);
    const [route, query] = path.split('?');
    if (url.searchParams.has('rest_route')) url.searchParams.set('rest_route', url.searchParams.get('rest_route').replace(/\/$/, '') + '/' + route);
    else url.pathname = url.pathname.replace(/\/$/, '') + '/' + route;
    if (query) new URLSearchParams(query).forEach((v, k) => url.searchParams.set(k, v));
    const response = await fetch(url, { cache: 'no-store', ...(data ? { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) } : {}) });
    const json = await response.json();
    if (!response.ok) throw new Error(json.message || '');
    return json;
  };
  const iso = d => d.toISOString().slice(0, 10);
  document.querySelectorAll('.fwb-calendar').forEach(cal => {
    const today = cal.dataset.today, t=language(cal), locale=cal.dataset.locale||'de-DE';
    const date=value=>new Intl.DateTimeFormat(locale,{timeZone:'UTC'}).format(new Date(value+'T12:00:00Z'));
    cal.querySelectorAll('.fwb-weekdays span').forEach((e,i)=>{e.textContent=new Intl.DateTimeFormat(locale,{weekday:'short',timeZone:'UTC'}).format(new Date(Date.UTC(2024,0,1+i)));});
    const form = cal.closest('.fwb-widget')?.querySelector('form') || cal.closest('.fwb-calendar-workspace')?.querySelector('#fwb-manual form');
    const selection = () => {
      const arrival = form?.elements.arrival.value || '';
      const departure = form?.elements.departure.value || '';
      const validRange = departure > arrival && arrival !== '';
      cal.querySelectorAll('.fwb-day').forEach(button => {
        const day = button.dataset.date;
        const start = day === arrival;
        const end = validRange && day === departure;
        const inside = validRange && day > arrival && day < departure;
        button.classList.toggle('fwb-selected-start', start);
        button.classList.toggle('fwb-selected-end', end);
        button.classList.toggle('fwb-in-range', inside);
        if (form) button.setAttribute('aria-pressed', String(start || end || inside));
        button.setAttribute('aria-label', button.dataset.label +
          (start ? t(", ausgewählte Anreise") : end ? t(", ausgewählte Abreise") : inside ? t(", ausgewählter Aufenthalt") : ''));
      });
      const summary = cal.querySelector('.fwb-selection');
      if (summary && form) summary.textContent = arrival
        ? (validRange ? t('Ausgewählt: {arrival} bis {departure} (Abreisetag).',{arrival:date(arrival),departure:date(departure)})
          : t('Anreise: {arrival}. Bitte die Abreise auswählen.',{arrival:date(arrival)}))
        : t("Wähle zuerst die Anreise, danach die Abreise.");
    };
    let month = new Date(today.slice(0, 7) + '-01T12:00:00Z'), version = 0;
    const draw = async () => {
      const request = ++version;
      const start = iso(month).slice(0, 7);
      cal.querySelector('.fwb-month').textContent = month.toLocaleDateString(locale, { month: 'long', year: 'numeric', timeZone: 'UTC' });
      const grid = cal.querySelector('.fwb-days'); grid.replaceChildren();
      cal.querySelector('.fwb-calendar-error').textContent = t("Verfügbarkeit wird geladen …");
      try {
        const { ranges } = await api(cal.dataset.api, 'calendar?month=' + start);
        if (request !== version) return;
        cal.querySelector('.fwb-calendar-error').textContent = '';
        const offset = (month.getUTCDay() + 6) % 7;
        for (let i = 0; i < offset; i++) grid.append(document.createElement('span'));
        const days = new Date(Date.UTC(month.getUTCFullYear(), month.getUTCMonth() + 1, 0)).getUTCDate();
        for (let i = 1; i <= days; i++) {
          const day = start + '-' + String(i).padStart(2, '0');
          const am = ranges.some(r => r.arrival < day && r.departure >= day);
          const pm = ranges.some(r => r.arrival <= day && r.departure > day);
          const b = document.createElement('button'); b.type = 'button'; b.textContent = i;
          b.className = 'fwb-day uk-button uk-button-default uk-button-small' + (am ? ' am' : '') + (pm ? ' pm' : '') + (day === today ? ' today' : '') + (day < today ? ' fwb-past' : '');
          b.dataset.date = day;
          b.dataset.label = day + ': ' + (day < today ? t("vergangen") : am && pm ? t("belegt") : am ? t("Vormittag belegt, Anreise möglich") : pm ? t("Nachmittag belegt, Abreise möglich") : t("frei"));
          b.setAttribute('aria-label', b.dataset.label);
          b.disabled = day < today || (am && pm);
          b.addEventListener('click', () => {
            if (!form) return;
            const a = form.elements.arrival, d = form.elements.departure;
            if (a.value && !d.value && day > a.value && !am) d.value = day;
            else if (!pm) { a.value = day; d.value = ''; }
            form.dispatchEvent(new Event('change', { bubbles: true }));
          });
          grid.append(b);
        }
        selection();
      } catch (e) { if (request === version) cal.querySelector('.fwb-calendar-error').textContent = e.message||t('Verbindung fehlgeschlagen.'); }
    };
    cal.querySelector('.fwb-prev').addEventListener('click', () => { month.setUTCMonth(month.getUTCMonth() - 1); draw(); });
    cal.querySelector('.fwb-next').addEventListener('click', () => { month.setUTCMonth(month.getUTCMonth() + 1); draw(); });
    cal.addEventListener('fwb-refresh', draw);
    if (form) {
      const syncDates = event => {
        selection();
        if (event.target.name === 'arrival' && /^\d{4}-\d{2}-\d{2}$/.test(form.elements.arrival.value)) {
          const selectedMonth = form.elements.arrival.value.slice(0, 7);
          if (iso(month).slice(0, 7) !== selectedMonth) {
            month = new Date(selectedMonth + '-01T12:00:00Z'); draw();
          }
        }
      };
      form.addEventListener('input', syncDates);
      form.addEventListener('change', syncDates);
      form.addEventListener('reset', () => queueMicrotask(selection));
    }
    draw();
  });
  document.querySelectorAll('.fwb-widget').forEach(widget => {
    const t=language(widget), money=n=>new Intl.NumberFormat(widget.dataset.locale||'de-DE',{style:'currency',currency:'EUR'}).format(n/100);
    const form = widget.querySelector('form'), result = widget.querySelector('.fwb-result'), quote = widget.querySelector('.fwb-quote');
    let revision = 0;
    const values = () => ({ arrival: form.elements.arrival.value, departure: form.elements.departure.value, guests: form.elements.guests.value, taxable_guests: form.elements.taxable_guests.value });
    const refreshQuote = async () => {
      const current = ++revision, v = values();
      form.elements.taxable_guests.max = form.elements.guests.value;
      if (!v.arrival || !v.departure) { quote.textContent = quote.dataset.empty || t("Wähle Anreise und Abreise."); return; }
      quote.textContent = t("Preis wird berechnet …");
      try {
        const q = await api(widget.dataset.api, 'quote', v);
        if (current !== revision) return;
        quote.replaceChildren();
        const tokens = { total: money(q.total), nights: q.nights, deposit: money(q.deposit), balance: money(q.balance), local_tax: money(q.local_tax) };
        const format = text => text.replace(/\{(total|nights|deposit|balance|local_tax)\}/g, (_, key) => tokens[key]);
        const strong = document.createElement('strong'); strong.textContent = format(quote.dataset.summary);
        const detail = document.createElement('p'); detail.textContent = format(quote.dataset.detail);
        quote.append(strong, detail);
      } catch (e) { if (current === revision) quote.textContent = e.message||t('Verbindung fehlgeschlagen.'); }
    };
    form.addEventListener('change', refreshQuote);
    form.addEventListener('input', e => {
      if (['arrival','departure','guests','taxable_guests'].includes(e.target.name)) refreshQuote();
    });
    form.addEventListener('submit', async event => {
      event.preventDefault();
      if (widget.dataset.preview) { result.textContent=t("Vorschau – es wird keine Anfrage versendet."); return; }
      result.classList.remove('is-error','is-success');
      if (!form.reportValidity()) return;
      const button = form.querySelector('[type=submit]'); button.disabled = true;
      result.textContent = t("Spam-Schutz wird berechnet …");
      const input = values(); input.fields = {};
      form.querySelectorAll('[data-field]').forEach(f => { input.fields[f.dataset.field] = f.type === 'checkbox' ? (f.checked ? '1' : '') : f.value; });
      input.consent = form.elements.consent.checked; input.website = form.elements.website.value;
      try {
        if (!window.isSecureContext || !window.Worker) throw new Error(t("Der Spam-Schutz benötigt HTTPS und einen aktuellen Browser. Bitte die sichere Website verwenden."));
        const c = await api(widget.dataset.api, 'challenge');
        const nonce = await new Promise((resolve, reject) => {
          const worker = new Worker(widget.dataset.worker);
          const timeout = setTimeout(() => { worker.terminate(); reject(new Error(t("Spam-Schutz hat zu lange gedauert. Bitte erneut versuchen."))); }, 60000);
          worker.onmessage = e => { clearTimeout(timeout); worker.terminate(); e.data.error ? reject(new Error(t(t("Spam-Schutz konnte nicht gestartet werden.")))) : resolve(e.data.nonce); };
          worker.onerror = () => { clearTimeout(timeout); worker.terminate(); reject(new Error(t("Spam-Schutz konnte nicht gestartet werden."))); };
          worker.postMessage(c);
        });
        result.textContent = t("Anfrage wird gesendet …");
        const response = await api(widget.dataset.api, 'request', { ...input, ...c, nonce });
        result.classList.add('is-success'); result.textContent = response.message + t(" Deine Referenz: ") + response.reference;
        form.reset(); quote.textContent = t("Deine Anfrage wurde gespeichert."); revision++;
        widget.querySelector('.fwb-calendar')?.dispatchEvent(new Event('fwb-refresh'));
      } catch (e) { result.classList.add('is-error'); result.textContent = e.message||t('Verbindung fehlgeschlagen.'); }
      finally { button.disabled = false; result.focus(); }
    });
  });
})();
