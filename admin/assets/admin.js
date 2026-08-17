(() => {
  const loader = document.querySelector('#pageLoader');
  window.addEventListener('load', () => setTimeout(() => loader?.classList.add('is-hidden'), 180));

  const toast = document.querySelector('#toast');
  let toastTimer;
  const notify = (message) => {
    if (!toast) return;
    toast.textContent = message;
    toast.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toast.classList.remove('show'), 2800);
  };
  document.querySelectorAll('[data-toast]').forEach((button) => button.addEventListener('click', () => notify(button.dataset.toast)));

  const header = document.querySelector('.main-header');
  const navButtons = document.querySelectorAll('[data-menu]');
  const megaMenus = document.querySelectorAll('.mega-menu');
  navButtons.forEach((button) => button.addEventListener('click', () => {
    const menu = document.getElementById(button.dataset.menu);
    if (!menu) return notify('Bu bölüm yakında aktif olacak.');
    const open = !menu.classList.contains('is-open');
    megaMenus.forEach((item) => item.classList.remove('is-open'));
    if (open) menu.classList.add('is-open');
  }));
  document.addEventListener('click', (event) => {
    if (!event.target.closest('.main-header')) megaMenus.forEach((menu) => menu.classList.remove('is-open'));
  });
  document.querySelector('.mobile-menu')?.addEventListener('click', (button) => {
    const open = !header.classList.contains('mobile-open');
    header.classList.toggle('mobile-open', open);
    button.currentTarget.setAttribute('aria-expanded', String(open));
  });

  const filterToggle = document.querySelector('.filter-toggle');
  const filterDetail = document.querySelector('#filterDetail');
  filterToggle?.addEventListener('click', () => {
    const open = !filterDetail.classList.contains('is-open');
    filterDetail.classList.toggle('is-open', open);
    filterToggle.setAttribute('aria-expanded', String(open));
  });
  const categoryFilter = document.querySelector('[data-category-filter]');
  const stockOnly = document.querySelector('[data-stock-only]');
  const rows = [...document.querySelectorAll('[data-product-row]')];
  const filterRows = () => rows.forEach((row) => {
    const categoryMatch = !categoryFilter?.value || row.dataset.category === categoryFilter.value;
    const stockMatch = !stockOnly?.checked || Number(row.dataset.stock) > 0;
    row.hidden = !(categoryMatch && stockMatch);
  });
  categoryFilter?.addEventListener('change', filterRows);
  stockOnly?.addEventListener('change', filterRows);
  document.querySelector('[data-select-all]')?.addEventListener('change', (event) => document.querySelectorAll('tbody input[type="checkbox"]').forEach((checkbox) => checkbox.checked = event.currentTarget.checked));

  const productModal = document.querySelector('#productModal');
  document.querySelectorAll('[data-open-product]').forEach((button) => button.addEventListener('click', (event) => {
    event.preventDefault();
    productModal?.classList.add('is-open');
    productModal?.setAttribute('aria-hidden', 'false');
    productModal?.querySelector('input[name="name"]')?.focus();
  }));
  productModal?.addEventListener('click', (event) => {
    if (event.target === productModal) window.location.href = 'index.php';
  });

  const deleteForm = document.querySelector('#deleteForm');
  document.querySelectorAll('[data-delete-product]').forEach((button) => button.addEventListener('click', () => {
    deleteForm.querySelector('#deleteId').value = button.dataset.deleteProduct;
    deleteForm.querySelector('#deleteText').textContent = `“${button.dataset.productName}” ürünü kalıcı olarak silinecek.`;
    deleteForm.classList.add('is-open');
    deleteForm.setAttribute('aria-hidden', 'false');
  }));
  document.querySelectorAll('[data-close-delete]').forEach((button) => button.addEventListener('click', () => {
    deleteForm?.classList.remove('is-open');
    deleteForm?.setAttribute('aria-hidden', 'true');
  }));
  document.querySelectorAll('.flash button').forEach((button) => button.addEventListener('click', () => button.parentElement.remove()));

  document.querySelectorAll('[data-confirm]').forEach((button) => button.addEventListener('click', (event) => {
    if (!window.confirm(button.dataset.confirm)) event.preventDefault();
  }));

  document.querySelectorAll('form[method="post"]').forEach((form) => form.addEventListener('submit', (event) => {
    if (event.defaultPrevented) return;
    const submitter = event.submitter;
    if (!submitter || submitter.classList.contains('danger-button')) return;
    submitter.classList.add('is-submitting');
    submitter.setAttribute('aria-busy', 'true');
  }));
})();
