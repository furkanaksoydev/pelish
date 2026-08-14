(() => {
  const galleryImage = document.getElementById('productCarouselImage');
  const colorButtons = document.querySelectorAll('.color-swatch');
  const sizeButtons = document.querySelectorAll('.journal-sizes button');
  const addToCartButton = document.querySelector('.journal-add-cart');
  const toast = document.getElementById('toast');
  const toastText = document.getElementById('toastText');
  let toastTimer;

  const showToast = (message) => {
    if (!toast || !toastText) return;
    toastText.textContent = message;
    toast.classList.add('show');
    window.clearTimeout(toastTimer);
    toastTimer = window.setTimeout(() => toast.classList.remove('show'), 2600);
  };

  colorButtons.forEach((button) => {
    button.addEventListener('click', () => {
      if (!galleryImage) return;
      galleryImage.style.opacity = '0';
      window.setTimeout(() => {
        galleryImage.src = button.dataset.productImage;
        galleryImage.style.opacity = '1';
      }, 140);
      colorButtons.forEach((item) => {
        const selected = item === button;
        item.classList.toggle('active', selected);
        item.setAttribute('aria-pressed', String(selected));
      });
    });
  });

  sizeButtons.forEach((button) => {
    button.addEventListener('click', () => {
      sizeButtons.forEach((item) => {
        const selected = item === button;
        item.classList.toggle('active', selected);
        item.setAttribute('aria-pressed', String(selected));
      });
    });
  });

  addToCartButton?.addEventListener('click', () => showToast('Ürün sepete eklendi.'));
})();
