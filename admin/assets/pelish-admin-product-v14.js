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

  const imageUpload = document.querySelector('[data-product-image-upload]');
  const pendingImageList = document.querySelector('[data-product-image-list]');
  const renderPendingImages = () => {
    if (!imageUpload || !pendingImageList) return;
    pendingImageList.replaceChildren();
    Array.from(imageUpload.files || []).forEach((file, index) => {
      const card = document.createElement('article');
      card.className = 'pending-image-card';
      const preview = document.createElement('img');
      preview.src = URL.createObjectURL(file);
      preview.alt = `${file.name} önizleme`;
      preview.addEventListener('load', () => URL.revokeObjectURL(preview.src), { once: true });
      const meta = document.createElement('div');
      const title = document.createElement('strong');
      title.textContent = file.name;
      const size = document.createElement('small');
      size.textContent = `${(file.size / 1024 / 1024).toFixed(2)} MB`;
      meta.append(title, size);
      const primary = document.createElement('label');
      primary.className = 'pending-primary';
      const primaryInput = document.createElement('input');
      primaryInput.type = 'radio';
      primaryInput.name = 'primary_upload_index';
      primaryInput.value = String(index);
      primaryInput.checked = index === 0;
      primary.append(primaryInput, document.createTextNode(' Ana görsel'));
      const colorName = document.createElement('label');
      colorName.textContent = 'Renk adı';
      const colorNameInput = document.createElement('input');
      colorNameInput.name = 'image_color_names[]';
      colorNameInput.placeholder = 'Örn. Kırmızı';
      colorName.append(colorNameInput);
      const colorHex = document.createElement('label');
      colorHex.textContent = 'Renk kodu';
      const colorHexInput = document.createElement('input');
      colorHexInput.type = 'color';
      colorHexInput.name = 'image_color_hexes[]';
      colorHexInput.value = '#111111';
      colorHex.append(colorHexInput);
      card.append(preview, meta, primary, colorName, colorHex);
      pendingImageList.append(card);
    });
  };
  imageUpload?.addEventListener('change', renderPendingImages);

  const colorList = document.querySelector('[data-product-color-list]');
  const addColorButton = document.querySelector('[data-add-product-color]');
  if (!document.querySelector('input[name="id"]')?.value) {
    colorList?.closest('.product-colors-editor')?.remove();
  }
  const imageChoices = () => [...document.querySelectorAll('.gallery-grid .variant-card')].map((card) => {
    const input = card.querySelector('input[name="primary_image_id"]');
    const label = card.querySelector('strong')?.textContent?.trim() || 'Görsel';
    const image = card.querySelector('img')?.src || '';
    return input?.value ? { id: input.value, label, image } : null;
  }).filter(Boolean);
  const upgradeColorImagePicker = (row) => {
    const select = row?.querySelector('select[name="color_image_ids[]"]');
    if (!select || select.dataset.previewReady === 'true') return;
    select.dataset.previewReady = 'true';
    const savedImages = imageChoices();
    const choices = savedImages.length ? [{ id: '0', label: 'Ana görsel', image: savedImages[0].image }, ...savedImages] : [];
    const picker = document.createElement('div');
    const pickerName = `color_image_picker_${row.querySelector('input[name="color_ids[]"]')?.value || Math.random().toString(36).slice(2)}`;
    picker.className = 'color-image-picker';
    picker.setAttribute('aria-label', 'Temsil eden görsel seçimi');
    choices.forEach((choice) => {
      const label = document.createElement('label');
      label.innerHTML = `<input type="radio" name="${pickerName}" value="${choice.id}"><img src="${choice.image}" alt="${choice.label}"><span>${choice.label}</span>`;
      const input = label.querySelector('input');
      input.checked = select.value === choice.id;
      input.addEventListener('change', () => { select.value = choice.id; });
      picker.append(label);
    });
    if (!choices.length) picker.textContent = 'Önce ürün görsellerini kaydet.';
    select.hidden = true;
    select.closest('label')?.append(picker);
  };
  const colorRow = (color = {}) => {
    const row = document.createElement('div');
    row.className = 'product-color-row';
    const choices = imageChoices();
    const options = ['<option value="0">Ana görseli kullan</option>', ...choices.map((choice) => `<option value="${choice.id}">#${choice.id} · ${choice.label}</option>`)].join('');
    row.innerHTML = `<input type="hidden" name="color_ids[]" value="${color.id || ''}">
      <label>Renk adı<input required name="color_names[]" value="${color.name || ''}" placeholder="Örn. Kırmızı"></label>
      <label>Renk kodu<input type="color" name="color_hexes[]" value="${color.hex || '#c7b6a3'}"></label>
      <label>Temsil eden görsel<select name="color_image_ids[]">${options}</select></label>
      <button type="button" class="remove-product-color" data-remove-product-color aria-label="Rengi kaldır">×</button>`;
    const select = row.querySelector('select');
    if (select && color.imageId) select.value = String(color.imageId);
    upgradeColorImagePicker(row);
    return row;
  };
  colorList?.querySelectorAll('.product-color-row').forEach(upgradeColorImagePicker);
  addColorButton?.addEventListener('click', () => {
    if (!colorList) return;
    colorList.append(colorRow());
    colorList.lastElementChild?.querySelector('input[name="color_names[]"]')?.focus();
  });
  colorList?.addEventListener('click', (event) => {
    const button = event.target.closest('[data-remove-product-color]');
    if (!button) return;
    const row = button.closest('.product-color-row');
    const colorId = row?.querySelector('input[name="color_ids[]"]')?.value;
    if (colorId) {
      const removed = document.createElement('input');
      removed.type = 'hidden';
      removed.name = 'remove_color_ids[]';
      removed.value = colorId;
      colorList.append(removed);
    }
    row?.remove();
  });

  // Yeni ürün eklenirken renk kimliği henüz oluşmamış olur. Bu küçük arayüz
  // rengi kaydetmeden önce bile renk bazlı beden stoklarının girilmesini sağlar.
  const variantStockGroups = document.querySelector('.variant-stock-groups');
  let newColorStockIndex = 0;
  const addNewColorStockGroup = (row) => {
    const idInput = row?.querySelector('input[name="color_ids[]"]');
    if (!row || !variantStockGroups || idInput?.value) return;
    if (!row.dataset.stockIndex) row.dataset.stockIndex = String(newColorStockIndex++);
    const index = row.dataset.stockIndex;
    let group = variantStockGroups.querySelector(`[data-new-color-stock="${index}"]`);
    if (!group) {
      group = document.createElement('fieldset');
      group.className = 'size-stock-group';
      group.dataset.newColorStock = index;
      group.innerHTML = `<legend><i></i><span>Yeni renk</span></legend><input type="hidden" name="variant_stock_names[${index}]" value=""><div class="size-stock-list">${['Standart', 'XS', 'S', 'M', 'L', 'XL', 'XXL'].map((size) => `<label><span>${size}</span><input type="number" min="0" inputmode="numeric" name="variant_stock_new[${index}][${size}]" placeholder="Stok gir"></label>`).join('')}</div>`;
      variantStockGroups.append(group);
    }
    const colorInput = row.querySelector('input[name="color_names[]"]');
    const sync = () => {
      const name = colorInput?.value.trim() || 'Yeni renk';
      group.querySelector('legend span').textContent = name;
      group.querySelector('input[type="hidden"]').value = colorInput?.value.trim() || '';
    };
    colorInput?.addEventListener('input', sync);
    sync();
  };
  addColorButton?.addEventListener('click', () => setTimeout(() => addNewColorStockGroup(colorList?.lastElementChild), 0));
  colorList?.addEventListener('click', (event) => {
    const button = event.target.closest('[data-remove-product-color]');
    const row = button?.closest('.product-color-row');
    const index = row?.dataset.stockIndex;
    if (index) variantStockGroups?.querySelector(`[data-new-color-stock="${index}"]`)?.remove();
  });

  const categorySelect = document.querySelector('[data-category-select]');
  const newCategoryField = document.querySelector('[data-new-category-field]');
  const syncCategoryField = () => {
    if (!categorySelect || !newCategoryField) return;
    const show = [...categorySelect.selectedOptions].some((option) => option.value === '__new__');
    newCategoryField.hidden = !show;
    newCategoryField.querySelector('input')?.toggleAttribute('required', show);
  };
  if (categorySelect) {
    categorySelect.multiple = true;
    categorySelect.name = 'categories[]';
    categorySelect.size = Math.min(6, Math.max(3, categorySelect.options.length));
    const productId = document.querySelector('input[name="id"]')?.value;
    if (productId) fetch(`index.php?page=product-category-data&id=${encodeURIComponent(productId)}`, { credentials: 'same-origin' })
      .then((response) => response.ok ? response.json() : null)
      .then((data) => (data?.categories || []).forEach((name) => [...categorySelect.options].forEach((option) => { if (option.value === name) option.selected = true; })))
      .catch(() => {});
  }
  categorySelect?.addEventListener('change', syncCategoryField);
  syncCategoryField();

  const gallery = document.querySelector('.gallery-grid');
  if (gallery) {
    const form = gallery.closest('form');
    let orderInput = form?.querySelector('input[name="image_order"]');
    if (form && !orderInput) {
      orderInput = document.createElement('input');
      orderInput.type = 'hidden';
      orderInput.name = 'image_order';
      form.append(orderInput);
    }
    const cards = () => [...gallery.querySelectorAll('.variant-card')];
    const syncImageOrder = () => {
      if (!orderInput) return;
      orderInput.value = cards().map((card) => card.dataset.imageId || '').filter(Boolean).join(',');
    };
    cards().forEach((card) => {
      const id = card.querySelector('input[name="primary_image_id"]')?.value;
      if (!id) return;
      card.dataset.imageId = id;
      card.draggable = true;
      card.setAttribute('aria-label', 'Görsel sıralama kartı');
      card.addEventListener('dragstart', () => card.classList.add('is-dragging'));
      card.addEventListener('dragend', () => { card.classList.remove('is-dragging'); syncImageOrder(); });
      card.addEventListener('dragover', (event) => {
        event.preventDefault();
        const dragging = gallery.querySelector('.is-dragging');
        if (!dragging || dragging === card) return;
        const box = card.getBoundingClientRect();
        gallery.insertBefore(dragging, event.clientY < box.top + box.height / 2 ? card : card.nextSibling);
      });
    });
    syncImageOrder();
  }

  const financeForm = document.querySelector('[data-finance-purchase]');
  if (financeForm) {
    const number = (input) => Number(String(input?.value || '').replace(',', '.')) || 0;
    const connectReceiptLine = (line) => {
      const quantity = line.querySelector('[data-finance-quantity]');
      const unitPrice = line.querySelector('[data-finance-unit-price]');
      const total = line.querySelector('[data-finance-total]');
      let totalTouched = false;
      const syncFinanceTotal = () => { if (!total || totalTouched) return; const amount = number(quantity) * number(unitPrice); total.value = amount > 0 ? amount.toFixed(2) : ''; };
      total?.addEventListener('input', () => { totalTouched = true; });
      quantity?.addEventListener('input', syncFinanceTotal); unitPrice?.addEventListener('input', syncFinanceTotal); syncFinanceTotal();
    };
    document.querySelectorAll('.finance-receipt-line').forEach(connectReceiptLine);
    document.querySelector('[data-add-finance-line]')?.addEventListener('click', () => {
      const list = document.querySelector('[data-finance-receipt-lines]'); const example = list?.querySelector('.finance-receipt-line'); if (!list || !example) return;
      const line = example.cloneNode(true); line.querySelectorAll('input').forEach((input) => { input.value = input.name.includes('quantity') ? '1' : ''; }); list.append(line); connectReceiptLine(line);
    });
    document.querySelector('[data-finance-receipt-lines]')?.addEventListener('click', (event) => { const button = event.target.closest('[data-remove-finance-line]'); if (!button) return; const lines = document.querySelectorAll('.finance-receipt-line'); if (lines.length > 1) button.closest('.finance-receipt-line')?.remove(); });
  }

  document.querySelector('[data-toggle-supplier-rename]')?.addEventListener('click', () => document.querySelector('[data-supplier-rename]')?.toggleAttribute('hidden'));

})();
