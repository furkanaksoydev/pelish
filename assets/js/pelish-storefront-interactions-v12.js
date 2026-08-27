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
  slides.forEach((slide) => {
    $$("video", slide).forEach((video) => video.addEventListener("loadeddata", () => slide.classList.add("is-video-ready"), { once: true }));
  });

  $$(".filters [data-filter]").forEach((button) => button.addEventListener("click", () => {
    $$(".filters [data-filter]").forEach((item) => item.classList.toggle("active", item === button));
    $$(".db-product-card").forEach((card) => card.classList.toggle("hidden", button.dataset.filter !== "all" && card.dataset.category !== button.dataset.filter));
  }));

  $$('[data-product-preview-images]').forEach((card) => {
    const image = $('[data-product-preview-image]', card);
    if (!image) return;
    let images = [];
    try { images = JSON.parse(card.dataset.productPreviewImages || '[]'); } catch { images = []; }
    images = images.filter((url, position, list) => typeof url === 'string' && url && list.indexOf(url) === position).slice(0, 3);
    if (images.length < 2) return;
    let previewIndex = Math.max(0, images.indexOf(image.getAttribute('src') || ''));
    window.setInterval(() => {
      previewIndex = (previewIndex + 1) % images.length;
      const nextImage = new Image();
      nextImage.onload = () => { image.src = images[previewIndex]; };
      nextImage.src = images[previewIndex];
    }, 4000);
  });

  const thumbs = $$("[data-gallery-image]");
  const mainImage = $("#detailMainImage");
  const selectedImage = $("#selectedImageId");
  const selectedColor = $("#selectedColorId");
  const colorName = $("#selectedColorName");
  const sizeArea = $(".detail-sizes");
  const renderColorSizes = (colorId) => {
    if (!sizeArea) return;
    let stockMap = {};
    try { stockMap = JSON.parse(sizeArea.dataset.colorSizeStocks || "{}"); } catch (_) { return; }
    const sizes = stockMap[String(colorId)] || stockMap["0"] || [];
    sizeArea.replaceChildren();
    sizes.forEach((size) => {
      const label = document.createElement("label");
      const unavailable = Number(size.stock) < 1;
      label.className = unavailable ? "is-sold-out" : "";
      const input = document.createElement("input"); input.type = "radio"; input.name = "size"; input.value = size.size_code; input.disabled = unavailable;
      input.addEventListener("change", () => { const labelText = $("#selectedSizeLabel"); if (labelText) labelText.textContent = input.value; });
      const text = document.createElement("span"); text.textContent = size.size_code;
      label.append(input, text); sizeArea.append(label);
    });
    const labelText = $("#selectedSizeLabel"); if (labelText) labelText.textContent = "Beden seçin";
  };
  const setImage = (button) => {
    if (!button || !mainImage) return;
    mainImage.src = button.dataset.imageUrl;
    mainImage.alt = button.dataset.imageAlt || "Ürün görseli";
    if (selectedImage) selectedImage.value = button.dataset.imageId || "0";
    thumbs.forEach((item) => item.classList.toggle("active", item.dataset.imageId === button.dataset.imageId));
    const mappedColor = $$("[data-color-select]").find((item) => item.dataset.imageId === button.dataset.imageId);
    if (mappedColor) $$("[data-color-select]").forEach((item) => item.classList.toggle("active", item === mappedColor));
    if (colorName) colorName.textContent = button.dataset.colorName || "Varsayılan renk";
  };
  thumbs.forEach((button) => button.addEventListener("click", () => setImage(button)));
  $$("[data-color-select]").forEach((button) => button.addEventListener("click", () => {
    const related = thumbs.find((thumb) => thumb.dataset.imageId === button.dataset.imageId);
    if (related) {
      setImage(related);
      $$("[data-color-select]").forEach((item) => item.classList.toggle("active", item === button));
      if (colorName) colorName.textContent = button.dataset.colorName || "Varsayılan renk";
    } else { if (selectedImage) selectedImage.value = button.dataset.imageId || "0"; $$("[data-color-select]").forEach((item) => item.classList.toggle("active", item === button)); if (colorName) colorName.textContent = button.dataset.colorName || "Varsayılan renk"; }
    if (selectedColor) selectedColor.value = button.dataset.colorId || "0";
    renderColorSizes(button.dataset.colorId || "0");
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

  const showStoreFlash = (message, type = "success") => {
    if (!message) return;
    $("[data-flash]")?.remove();
    const flash = document.createElement("div");
    flash.className = `store-flash flash-${type}`;
    flash.dataset.flash = "";
    flash.innerHTML = `<i class="fa-solid fa-${type === "danger" ? "circle-exclamation" : "circle-check"}"></i><span></span><button type="button" aria-label="Kapat">×</button>`;
    $("span", flash).textContent = message;
    $("button", flash)?.addEventListener("click", () => flash.remove());
    document.body.append(flash);
    window.setTimeout(() => flash.remove(), 3600);
  };

  // FAVORİ MODÜLÜ
  // Formda `name="action"` alanı bulunduğu için `form.action` DOM özelliği
  // güvenilir değildir: alan, formun action URL'sini gölgeler. Bu modül
  // endpoint'i yalnızca HTML özniteliğinden alır ve hiçbir hata yolunda
  // sayfayı yenilemez ya da ana sayfaya göndermez.
  const favoriteForms = () => $$('form[data-favorite-form]');
  const favoriteEndpoint = (form) => new URL(
    form.getAttribute("action") || "store/action.php",
    window.location.href,
  ).href;
  const favoriteAction = (form) => form.querySelector('input[name="action"]')?.value || "";
  const isFavoriteForm = (form) => ["favorite", "favorite-remove"].includes(favoriteAction(form));

  const syncFavoriteState = (productId, isFavorite, count) => {
    favoriteForms()
      .filter((form) => form.querySelector('input[name="product_id"]')?.value === String(productId))
      .forEach((form) => {
        const actionInput = form.querySelector('input[name="action"]');
        const button = $("button", form);
        if (!actionInput || !button) return;

        actionInput.value = "favorite";
        button.classList.toggle("liked", isFavorite);
        button.setAttribute("aria-label", isFavorite ? "Favoriden çıkar" : "Favoriye ekle");

        if (button.classList.contains("favorite-outline")) {
          button.innerHTML = `<i class="${isFavorite ? "fa-solid" : "fa-regular"} fa-heart"></i> ${isFavorite ? "Favoriden çıkar" : "Favorile"}`;
        } else {
          const icon = $("span", button);
          if (icon) icon.textContent = isFavorite ? "♥" : "♡";
        }

        if (!isFavorite && form.closest(".favorite-product-grid")) {
          form.closest(".product-card")?.remove();
        }
      });

    const favoriteLink = $('.header-action[aria-label="Favorilerim"]');
    if (favoriteLink) {
      let badge = $("small", favoriteLink);
      if (count > 0 && !badge) {
        badge = document.createElement("small");
        favoriteLink.append(badge);
      }
      if (badge) {
        badge.textContent = String(count);
        if (count < 1) badge.remove();
      }
    }

    const grid = $(".favorite-product-grid");
    if (grid && !$(".product-card", grid)) {
      grid.innerHTML = '<div class="favorite-empty-inline"><strong>Favori listen güncellendi.</strong><span>Beğendiğin yeni parçalar burada yer alacak.</span><a href="indirimler.php">İndirimleri keşfet →</a></div>';
    }
  };

  document.addEventListener("submit", async (event) => {
    const form = event.target instanceof HTMLFormElement ? event.target : null;
    if (!form || !form.matches("form[data-favorite-form]") || !isFavoriteForm(form)) return;

    event.preventDefault();
    const button = $("button[type=submit]", form);
    if (button?.disabled) return;
    if (button) button.disabled = true;

    try {
      const formData = new FormData(form);
      formData.set("_response", "json");
      const response = await fetch(favoriteEndpoint(form), {
        method: "POST",
        body: formData,
        credentials: "same-origin",
        cache: "no-store",
        headers: {
          "X-Requested-With": "XMLHttpRequest",
          Accept: "application/json",
        },
      });
      const body = await response.text();
      let result;
      try {
        result = JSON.parse(body);
      } catch {
        const responseUrl = new URL(response.url || window.location.href, window.location.href);
        if (response.redirected && /\/giris\.php$/i.test(responseUrl.pathname)) {
          window.location.assign(responseUrl.href);
          return;
        }
        throw new Error("Favori işlemi için sunucudan JSON yanıtı alınamadı. Sayfan bulunduğun yerde kaldı; lütfen tekrar dene.");
      }

      if (result.requires_auth && result.redirect) {
        window.location.assign(result.redirect);
        return;
      }
      if (!response.ok || !result.ok) {
        throw new Error(result.message || "Favori işlemi tamamlanamadı.");
      }

      const productId = form.querySelector('input[name="product_id"]')?.value;
      if (!productId) throw new Error("Ürün bilgisi bulunamadı.");
      syncFavoriteState(productId, Boolean(result.is_favorite), Number(result.favorite_count || 0));
      showStoreFlash(result.message || (result.is_favorite ? "Favorilerine eklendi." : "Favorilerinden çıkarıldı."));
    } catch (error) {
      showStoreFlash(error instanceof Error ? error.message : "Favori işlemi tamamlanamadı.", "danger");
    } finally {
      if (button?.isConnected) button.disabled = false;
    }
  });

  const formatPhone = (raw) => {
    let digits = String(raw || "").replace(/\D/g, "");
    if (!digits.startsWith("0")) digits = `0${digits}`;
    digits = digits.slice(0, 11);
    return [digits.slice(0, 4), digits.slice(4, 7), digits.slice(7, 9), digits.slice(9, 11)].filter(Boolean).join(" ");
  };
  const installPhoneMask = (input) => {
    if (input.dataset.phoneMaskReady === "true") return;
    input.dataset.phoneMaskReady = "true";
    input.type = "tel";
    input.inputMode = "numeric";
    const form = input.closest("form");
    let hidden = form?.querySelector('input[type="hidden"][data-phone-clean]');
    if (!hidden && form) {
      hidden = document.createElement("input");
      hidden.type = "hidden";
      hidden.name = input.name || "phone";
      hidden.dataset.phoneClean = "";
      input.removeAttribute("name");
      form.append(hidden);
    }
    const sync = () => {
      const formatted = formatPhone(input.value);
      input.value = formatted;
      if (hidden) hidden.value = formatted.replace(/\D/g, "");
    };
    input.addEventListener("beforeinput", (event) => {
      if (event.inputType.startsWith("insert") && event.data && /\D/.test(event.data)) event.preventDefault();
    });
    input.addEventListener("input", sync);
    input.addEventListener("paste", () => window.setTimeout(sync, 0));
    input.addEventListener("keydown", (event) => {
      if (event.key === "Backspace" && input.value.replace(/\D/g, "").length <= 1) { event.preventDefault(); input.value = "0"; sync(); }
    });
    sync();
  };
  $$('input[data-phone-mask], input[name="phone"]').forEach(installPhoneMask);

  const registrationForm = $$("form").find((form) => form.querySelector('input[name="mode"][value="register"]'));
  if (registrationForm && !$("[data-registration-consents]", registrationForm)) {
    const consentBlock = document.createElement("div");
    consentBlock.className = "registration-consents";
    consentBlock.dataset.registrationConsents = "";
    consentBlock.innerHTML = '<label class="consent-line"><input type="checkbox" required name="kvkk_accepted"><span><a href="yasal.php?belge=kvkk" target="_blank" rel="noopener">KVKK Aydınlatma Metni</a>’ni okudum.</span></label><label class="consent-line optional"><input type="checkbox" name="marketing_consent"><span>Kampanya ve yeni sezon duyuruları için ticari elektronik ileti almak istiyorum. İznimi dilediğim zaman geri alabilirim.</span></label>';
    registrationForm.querySelector("button[type=submit]")?.before(consentBlock);
  }

  const cardNumber = $("[data-card-number]");
  cardNumber?.addEventListener("input", () => { cardNumber.value = cardNumber.value.replace(/\D/g, "").slice(0, 16).replace(/(.{4})/g, "$1 ").trim(); });
  const cardExpiry = $("[data-card-expiry]");
  cardExpiry?.addEventListener("input", () => { const digits = cardExpiry.value.replace(/\D/g, "").slice(0, 4); cardExpiry.value = digits.length > 2 ? `${digits.slice(0, 2)}/${digits.slice(2)}` : digits; });
  const cardCvv = $("[data-card-cvv]");
  cardCvv?.addEventListener("input", () => { cardCvv.value = cardCvv.value.replace(/\D/g, "").slice(0, 4); });
  const cardName = $("[data-card-name]");
  cardName?.addEventListener("input", () => { cardName.value = cardName.value.replace(/[^a-zA-ZçÇğĞıİöÖşŞüÜ\s]/g, "").replace(/\s{2,}/g, " "); });
  const cardFields = $("[data-card-fields]");
  const transferNote = $("[data-transfer-note]");
  $$('[data-payment-method]').forEach((input) => input.addEventListener("change", () => {
    const cardSelected = input.value === "card" && input.checked;
    if (cardFields) cardFields.hidden = !cardSelected;
    if (transferNote) transferNote.hidden = cardSelected;
  }));

  $(".cart-summary [data-store-toast]")?.addEventListener("click", (event) => { event.preventDefault(); window.location.assign("odeme.php"); });
  $$("[data-confirm]").forEach((button) => button.addEventListener("click", (event) => { if (!window.confirm(button.dataset.confirm || "Bu işlem uygulansın mı?")) event.preventDefault(); }));
  const profileAside = $(".profile-shell aside");
  if (profileAside && !$("[data-addresses-link]", profileAside)) {
    const addressLink = document.createElement("a");
    addressLink.href = "adresler.php";
    addressLink.dataset.addressesLink = "";
    addressLink.innerHTML = '<i class="fa-solid fa-location-dot"></i> Adreslerim';
    profileAside.querySelector('a[href="cikis.php"]')?.before(addressLink);
  }

  if (!localStorage.getItem("pelish_cookie_consent_v1")) {
    const banner = document.createElement("section");
    banner.className = "cookie-banner";
    banner.setAttribute("role", "dialog");
    banner.setAttribute("aria-label", "Çerez tercihleri");
    banner.innerHTML = '<div><p class="eyebrow">ÇEREZ TERCİHLERİ</p><strong>Tercihlerin sana ait.</strong><span>Zorunlu çerezler mağazanın çalışması için kullanılır. Analiz ve pazarlama çerezleri yalnızca açık seçiminle çalışır. <a href="yasal.php?belge=gizlilik-cerez">Çerez politikası</a></span></div><div class="cookie-actions"><button type="button" data-cookie-choice="necessary">Sadece gerekli</button><button type="button" data-cookie-choice="all">Tümünü kabul et</button></div>';
    banner.querySelectorAll("[data-cookie-choice]").forEach((button) => button.addEventListener("click", () => { localStorage.setItem("pelish_cookie_consent_v1", button.dataset.cookieChoice || "necessary"); banner.remove(); }));
    document.body.append(banner);
  }

  document.addEventListener("keydown", (event) => { if (event.key === "Escape") { setMenu(false); search?.classList.remove("open"); lightbox?.classList.remove("open"); } });
})();
