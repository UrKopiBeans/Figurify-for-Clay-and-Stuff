// Collection page — filter pills, search, sort
(function () {

  const cards = document.querySelectorAll('.card');
  const blocks = document.querySelectorAll('.collection-block');
  const searchInput = document.getElementById('searchInput');
  const emptyState = document.getElementById('emptyState');

  // category pills
  const pills = document.querySelectorAll('#tagFilters .pill');

  // sort dropdown
  const sortDropdown = document.getElementById('sortDropdown');
  const sortButton = document.getElementById('sortButton');
  const sortLabel = document.getElementById('sortLabel');
  const sortMenuButtons = document.querySelectorAll('#sortMenu button');

  let activeFilter = 'all';
  let activeSort = 'random';

  // open / close ng sort dropdown
  if (sortButton && sortDropdown) {
    sortButton.addEventListener('click', (e) => {
      e.stopPropagation();
      sortDropdown.classList.toggle('open');
    });

    document.addEventListener('click', (e) => {
      if (!sortDropdown.contains(e.target)) {
        sortDropdown.classList.remove('open');
      }
    });
  }

  // size is always the last meta-chip, e.g. "3.5 in" -> 3.5
  function getCardSize(card) {
    const chip = card.querySelector('.meta-chip');
    if (!chip) return 0;
    const match = chip.textContent.match(/([\d.]+)/);
    return match ? parseFloat(match[1]) : 0;
  }

  // "₱1,500" -> 1500
  function getCardPrice(card) {
    const priceEl = card.querySelector('.price');
    if (!priceEl) return 0;
    const match = priceEl.textContent.match(/([\d,]+)/);
    return match ? parseFloat(match[1].replace(/,/g, '')) : 0;
  }

  // sort the cards inside each collection block
  function applySort() {
    blocks.forEach(block => {
      const grid = block.querySelector('.card-grid');
      const cardsInBlock = [...grid.querySelectorAll('.card')];

      cardsInBlock.sort((a, b) => {
        switch (activeSort) {
          case 'size-asc':   return getCardSize(a) - getCardSize(b);
          case 'size-desc':  return getCardSize(b) - getCardSize(a);
          case 'price-asc':  return getCardPrice(a) - getCardPrice(b);
          case 'price-desc': return getCardPrice(b) - getCardPrice(a);
          default:           return Math.random() - 0.5;
        }
      });

      cardsInBlock.forEach(card => grid.appendChild(card));
    });
  }

  function applyFilters() {
    const term = searchInput.value.trim().toLowerCase();
    let anyVisible = false;

    cards.forEach(card => {
      const tags = (card.dataset.filter || '').split(' ');
      const matchesFilter = activeFilter === 'all' || tags.includes(activeFilter);

      // search sa pangalan, category at size ng card
      const text = card.textContent.toLowerCase();
      const matchesSearch = text.includes(term);

      const visible = matchesFilter && matchesSearch;
      card.style.display = visible ? '' : 'none';
      if (visible) anyVisible = true;
    });

    blocks.forEach(block => {
      const hasVisible = [...block.querySelectorAll('.card')]
        .some(c => c.style.display !== 'none');
      block.style.display = hasVisible ? '' : 'none';
    });

    if (emptyState) emptyState.hidden = anyVisible;
  }

  pills.forEach(pill => pill.addEventListener('click', () => {
    pills.forEach(p => p.classList.remove('active'));
    pill.classList.add('active');
    activeFilter = pill.dataset.filter;
    applyFilters();
  }));

  sortMenuButtons.forEach(btn => btn.addEventListener('click', () => {
    sortMenuButtons.forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    activeSort = btn.dataset.sort;
    if (sortLabel) sortLabel.textContent = btn.textContent.trim();
    sortDropdown.classList.remove('open');
    applySort();
    applyFilters();
  }));

  searchInput.addEventListener('input', applyFilters);

  // unang load: random order, gaya ng dati
  applySort();

})();
