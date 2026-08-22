(() => {
  const $ = (selector, scope = document) => scope.querySelector(selector);
  const $$ = (selector, scope = document) => [...scope.querySelectorAll(selector)];

  const header = $("#siteHeader");
  const menu = $("#sideMenu");
  const menuButton = $("#menuButton");
  const setMenu = (open) => {
    if (!menu || !menuButton) return;
    menu.classList.toggle("open", open);
    menu.setAttribute("aria-hidden", String(!open));
    menuButton.setAttribute("aria-expanded", String(open));
    document.body.classList.toggle("lock", open);
  };
  menuButton?.addEventListener("click", () => setMenu(!menu.classList.contains("open")));
  $("[data-menu-close]")?.addEventListener("click", () => setMenu(false));
  $$("#sideMenu a").forEach((link) => link.addEventListener("click", () => setMenu(false)));

  const search = $("#inlineSearch");
  const searchInput = $("#inlineSearchInput");
  const searchResults = $("#searchResults");
  $("[data-inline-search-toggle]")?.addEventListener("click", () => {
    const open = !search?.classList.contains("open");
    search?.classList.toggle("open", open);
    if (open) setTimeout(() => searchInput?.focus(), 50);
  });
  let searchTimer;
  searchInput?.addEventListener("input", () => {
    const query = searchInput.value.trim();
    clearTimeout(searchTimer);
    if (query.length < 1) { if (searchResults) searchResults.innerHTML = ""; return; }
    searchTimer = setTimeout(async () => {
      try {
        const response = await fetch(`store/search.php?q=${encodeURIComponent(query)}`, { headers: { Accept: "application/json" } });
        const products = await response.json();
        if (!searchResults) return;
        searchResults.innerHTML = products.map((item) => `<a class="search-result" href="urun.php?id=${item.id}"><img src="${item.image_url || "https://images.unsplash.com/photo-1485968579580-b6d095142e6e?auto=format&fit=crop&w=200&q=75"}" alt=""><span><small>${item.category}</small><strong>${item.name}</strong></span></a>`).join("") || "<p class=\"search-empty\">Eşleşen ürün bulunamadı.</p>";
      } catch { if (searchResults) searchResults.innerHTML = ""; }
    }, 120);
  });
  window.addEventListener("scroll", () => header?.classList.toggle("scrolled", window.scrollY > 80), { passive: true });

  const slides = $$(".hero-image");
  const dots = $$("[data-hero-dot]");
  let index = 0; let timer;
  const renderSlide = (next) => {
    if (!slides.length) return;
    index = (next + slides.length) % slides.length;
    slides.forEach((slide, position) => {
      slide.classList.toggle("active", position === index);
      $$("video", slide).forEach((video) => position === index ? video.play().catch(() => {}) : video.pause());
    });
    dots.forEach((dot, position) => dot.classList.toggle("active", position === index));
    const counter = $("#heroIndex"); if (counter) counter.innerHTML = `0${index + 1} <i>/ 0${slides.length}</i>`;
  };
  const resetTimer = () => { clearInterval(timer); timer = setInterval(() => renderSlide(index + 1), 6500); };
  $("[data-hero-prev]")?.addEventListener("click", () => { renderSlide(index - 1); resetTimer(); });
  $("[data-hero-next]")?.addEventListener("click", () => { renderSlide(index + 1); resetTimer(); });
  dots.forEach((dot) => dot.addEventListener("click", () => { renderSlide(Number(dot.dataset.heroDot)); resetTimer(); }));
  if (slides.length) { renderSlide(0); resetTimer(); }

  $$(".filters [data-filter]").forEach((button) => button.addEventListener("click", () => {
    $$(".filters [data-filter]").forEach((item) => item.classList.toggle("active", item === button));
    $$(".db-product-card").forEach((card) => card.classList.toggle("hidden", button.dataset.filter !== "all" && card.dataset.category !== button.dataset.filter));
  }));

  const thumbs = $$("[data-gallery-image]");
  const mainImage = $("#detailMainImage");
  const selectedImage = $("#selectedImageId");
  const colorName = $("#selectedColorName");
  const setImage = (button) => {
    if (!button || !mainImage) return;
    mainImage.src = button.dataset.imageUrl;
    mainImage.alt = button.dataset.imageAlt || "Ürün görseli";
    if (selectedImage) selectedImage.value = button.dataset.imageId || "0";
    thumbs.forEach((item) => item.classList.toggle("active", item.dataset.imageId === button.dataset.imageId));
    $$("[data-color-select]").forEach((item) => item.classList.toggle("active", item.dataset.imageId === button.dataset.imageId));
    if (colorName) colorName.textContent = button.dataset.colorName || "Varsayılan renk";
  };
  thumbs.forEach((button) => button.addEventListener("click", () => setImage(button)));
  $$("[data-color-select]").forEach((button) => button.addEventListener("click", () => {
    const related = thumbs.find((thumb) => thumb.dataset.imageId === button.dataset.imageId);
    if (related) setImage(related);
    else { if (selectedImage) selectedImage.value = button.dataset.imageId || "0"; $$("[data-color-select]").forEach((item) => item.classList.toggle("active", item === button)); if (colorName) colorName.textContent = button.dataset.colorName || "Varsayılan renk"; }
  }));
  $$(".detail-sizes input").forEach((input) => input.addEventListener("change", () => { const label = $("#selectedSizeLabel"); if (label) label.textContent = input.value; }));

  const countdown = $("[data-verify-countdown]");
  const resendButton = $("[data-countdown-resend]");
  const verificationCode = $("[data-verification-code]");
  if (countdown) {
    const until = Number(countdown.dataset.countdownUntil || 0);
    const updateCountdown = () => {
      const remaining = Math.max(0, until - Date.now());
      if (remaining === 0) {
        countdown.textContent = "Kodun süresi doldu. Yeni kod isteyebilirsin.";
        countdown.classList.add("is-expired");
        if (resendButton) { resendButton.disabled = false; resendButton.removeAttribute("aria-disabled"); }
        return true;
      }
      const totalSeconds = Math.ceil(remaining / 1000);
      const minutes = Math.floor(totalSeconds / 60);
      const seconds = String(totalSeconds % 60).padStart(2, "0");
      countdown.textContent = `Kodun geçerlilik süresi: ${minutes}:${seconds}`;
      if (resendButton) { resendButton.disabled = true; resendButton.setAttribute("aria-disabled", "true"); }
      return false;
    };
    if (!updateCountdown()) {
      const timer = window.setInterval(() => { if (updateCountdown()) window.clearInterval(timer); }, 1000);
    }
    if (verificationCode?.value.length === 6) verificationCode.focus();
  }

  const lightbox = $("#galleryLightbox");
  let lightboxIndex = 0;
  const lightboxImage = $("#galleryLightbox img");
  const setLightbox = (next) => {
    if (!thumbs.length || !lightboxImage) return;
    lightboxIndex = (next + thumbs.length) % thumbs.length;
    lightboxImage.src = thumbs[lightboxIndex].dataset.imageUrl;
    lightboxImage.alt = thumbs[lightboxIndex].dataset.imageAlt || "Ürün görseli";
  };
  $("[data-gallery-expand]")?.addEventListener("click", () => { lightboxIndex = Math.max(0, thumbs.findIndex((thumb) => thumb.classList.contains("active"))); setLightbox(lightboxIndex); lightbox?.classList.add("open"); lightbox?.setAttribute("aria-hidden", "false"); });
  $("[data-lightbox-close]")?.addEventListener("click", () => { lightbox?.classList.remove("open"); lightbox?.setAttribute("aria-hidden", "true"); });
  $("[data-lightbox-prev]")?.addEventListener("click", () => setLightbox(lightboxIndex - 1));
  $("[data-lightbox-next]")?.addEventListener("click", () => setLightbox(lightboxIndex + 1));
  $("#galleryLightbox img")?.addEventListener("click", () => { const current = thumbs[lightboxIndex]; if (current) setImage(current); });

  $$('[data-store-toast]').forEach((button) => button.addEventListener("click", () => {
    const flash = document.createElement("div"); flash.className = "store-flash"; flash.innerHTML = `<i class="fa-solid fa-circle-info"></i><span>${button.dataset.storeToast}</span>`; document.body.append(flash); setTimeout(() => flash.remove(), 2800);
  }));
  $("[data-flash] button")?.addEventListener("click", (event) => event.currentTarget.closest("[data-flash]")?.remove());
  setTimeout(() => $("[data-flash]")?.remove(), 5200);
  document.addEventListener("keydown", (event) => { if (event.key === "Escape") { setMenu(false); search?.classList.remove("open"); lightbox?.classList.remove("open"); } });
})();
