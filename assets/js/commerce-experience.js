(() => {
  const catalog = {
    "luna-saten-elbise": {
      name: "Luna Saten Elbise",
      category: "Elbise",
      price: "₺2.490",
      oldPrice: "₺2.990",
      image:
        "https://images.unsplash.com/photo-1566174053879-31528523f8ae?auto=format&fit=crop&w=1200&q=85",
      description:
        "Akışkan saten dokusu ve duru silüetiyle günün ritmine hafifçe eşlik eden bir parça.",
    },
    "elan-keten-takim": {
      name: "Élan Keten Takım",
      category: "Takım",
      price: "₺3.250",
      oldPrice: "₺3.790",
      image:
        "https://images.unsplash.com/photo-1551028719-00167b16eac5?auto=format&fit=crop&w=1200&q=85",
      description:
        "Nefes alan keten dokusu ile az parçayla tamamlanan, zamansız bir takım.",
    },
    "muse-drapeli-bluz": {
      name: "Muse Drapeli Bluz",
      category: "Elbise",
      price: "₺1.390",
      oldPrice: "₺1.590",
      image:
        "https://images.unsplash.com/photo-1485968579580-b6d095142e6e?auto=format&fit=crop&w=1200&q=85",
      description:
        "Yumuşak drape detaylarıyla sade kombinleri daha karakterli hale getirir.",
    },
    "isla-midi-etek": {
      name: "Isla Midi Etek",
      category: "Takım",
      price: "₺1.790",
      oldPrice: "₺2.190",
      image:
        "https://images.unsplash.com/photo-1572804013427-4d7ca7268217?auto=format&fit=crop&w=1200&q=85",
      description:
        "Günlük hareketi kolaylaştıran midi boy ve dengeli bir form.",
    },
    "nora-ince-triko": {
      name: "Nora İnce Triko",
      category: "Triko",
      price: "₺1.690",
      oldPrice: "₺1.990",
      image:
        "https://images.unsplash.com/photo-1551488831-00ddcb6c6bd3?auto=format&fit=crop&w=1200&q=85",
      description:
        "Mevsim geçişleri için ince, hafif ve kolay katmanlanabilir bir triko.",
    },
    "soleil-sehir-cantasi": {
      name: "Soleil Şehir Çantası",
      category: "Aksesuar",
      price: "₺2.150",
      oldPrice: "₺2.490",
      image:
        "https://images.unsplash.com/photo-1584917865442-de89df76afd3?auto=format&fit=crop&w=1200&q=85",
      description:
        "Günlük ihtiyaçları zarifçe taşıyan, sade bir şehir çantası.",
    },
  };

  const getList = (key) =>
    JSON.parse(localStorage.getItem(`pelish-${key}`) || "[]");
  const setList = (key, value) =>
    localStorage.setItem(`pelish-${key}`, JSON.stringify(value));
  const getCart = () => {
    const stored = getList("cart");
    const legacySizes = JSON.parse(
      localStorage.getItem("pelish-cart-sizes") || "{}",
    );
    return stored
      .map((item) =>
        typeof item === "string"
          ? { id: item, size: legacySizes[item] || "", quantity: 1 }
          : item,
      )
      .filter((item) => item && catalog[item.id])
      .map((item) => ({
        id: item.id,
        size: item.size || "",
        color: item.color || "",
        colorName: item.colorName || "",
        quantity: Math.max(1, Number(item.quantity) || 1),
      }));
  };
  const setCart = (items) => setList("cart", items);
  const priceOf = (id) => Number(catalog[id].price.replace(/[^0-9]/g, ""));
  const toast = (message) => {
    const element = document.querySelector("#toast");
    const text = document.querySelector("#toastText");
    if (!element || !text) return;
    text.textContent = message;
    element.classList.add("show");
    clearTimeout(window.pelishToastTimer);
    window.pelishToastTimer = setTimeout(
      () => element.classList.remove("show"),
      2800,
    );
  };
  const updateCounts = () => {
    const favoriteCount = getList("favorites").length;
    const cartCount = getCart().reduce(
      (total, item) => total + item.quantity,
      0,
    );
    document
      .querySelectorAll("[data-favorite-count]")
      .forEach((element) => (element.textContent = favoriteCount || ""));
    document
      .querySelectorAll("[data-cart-count]")
      .forEach((element) => (element.textContent = cartCount || ""));
  };
  const addCart = (id, size = "", colorSelection = {}) => {
    if (!catalog[id]) return;
    const cart = getCart();
    const color = colorSelection.color || "";
    const colorName = colorSelection.colorName || "";
    const line = cart.find(
      (item) => item.id === id && item.size === size && item.color === color,
    );
    if (line) line.quantity += 1;
    else cart.push({ id, size, color, colorName, quantity: 1 });
    setCart(cart);
    updateCounts();
    toast(
      `${catalog[id].name}${size ? ` · ${size}` : ""}${colorName ? ` · ${colorName}` : ""} sepetine eklendi.`,
    );
  };
  const toggleFavorite = (id) => {
    const favorites = getList("favorites");
    const added = !favorites.includes(id);
    setList(
      "favorites",
      added ? [...favorites, id] : favorites.filter((item) => item !== id),
    );
    updateCounts();
    return added;
  };

  if (document.querySelector("#welcomeModal:not(.closed)"))
    document.body.classList.add("lock");
  const menu = document.querySelector("#sideMenu");
  const menuButton = document.querySelector("#menuButton");
  const setMenu = (open) => {
    if (!menu || !menuButton) return;
    menu.classList.toggle("open", open);
    menu.setAttribute("aria-hidden", String(!open));
    menuButton.setAttribute("aria-expanded", String(open));
    document.body.classList.toggle("lock", open);
  };
  menuButton?.addEventListener("click", () =>
    setMenu(!menu.classList.contains("open")),
  );
  document
    .querySelector("[data-menu-close]")
    ?.addEventListener("click", () => setMenu(false));
  document
    .querySelectorAll("#sideMenu a")
    .forEach((link) => link.addEventListener("click", () => setMenu(false)));

  const search = document.querySelector("#inlineSearch");
  const searchInput = document.querySelector("#inlineSearchInput");
  const searchResults = document.querySelector("#searchResults");
  document
    .querySelector("[data-inline-search-toggle]")
    ?.addEventListener("click", () => {
      const open = !search.classList.contains("open");
      search.classList.toggle("open", open);
      if (open) setTimeout(() => searchInput?.focus(), 30);
    });
  const renderSearch = (query) => {
    if (!searchResults) return;
    const matches = Object.entries(catalog).filter(([, item]) =>
      `${item.name} ${item.category}`
        .toLocaleLowerCase("tr")
        .includes(query.toLocaleLowerCase("tr")),
    );
    searchResults.innerHTML = matches
      .map(
        ([id, item]) =>
          `<a class="search-result" href="urun.html?id=${id}"><img src="${item.image}" alt="${item.name}"><span><small>${item.category}</small><strong>${item.name}</strong></span></a>`,
      )
      .join("");
    searchResults.classList.toggle(
      "has-results",
      Boolean(matches.length && query),
    );
  };
  searchInput?.addEventListener("input", (event) =>
    renderSearch(event.target.value.trim()),
  );
  document
    .querySelector("#inlineSearchForm")
    ?.addEventListener("submit", (event) => {
      event.preventDefault();
      const id = Object.keys(catalog).find((key) =>
        catalog[key].name
          .toLocaleLowerCase("tr")
          .includes(searchInput.value.toLocaleLowerCase("tr")),
      );
      if (id) window.location.href = `urun.html?id=${id}`;
      else toast("Aradığın ürünü şu an bulamadık.");
    });
  window.addEventListener("scroll", () =>
    document
      .querySelector("#siteHeader")
      ?.classList.toggle("scrolled", window.scrollY > 90),
  );

  const slides = [...document.querySelectorAll(".hero-image")];
  const dots = [...document.querySelectorAll("[data-hero-dot]")];
  let slideIndex = 0;
  let heroTimer;
  const setSlide = (next) => {
    if (!slides.length) return;
    slideIndex = (next + slides.length) % slides.length;
    slides.forEach((slide, index) =>
      slide.classList.toggle("active", index === slideIndex),
    );
    dots.forEach((dot, index) =>
      dot.classList.toggle("active", index === slideIndex),
    );
    const heroIndex = document.querySelector("#heroIndex");
    if (heroIndex) heroIndex.innerHTML = `0${slideIndex + 1} <i>/ 03</i>`;
  };
  document
    .querySelector("[data-hero-prev]")
    ?.addEventListener("click", () => setSlide(slideIndex - 1));
  document
    .querySelector("[data-hero-next]")
    ?.addEventListener("click", () => setSlide(slideIndex + 1));
  dots.forEach((dot) =>
    dot.addEventListener("click", () => setSlide(Number(dot.dataset.heroDot))),
  );
  const hero = document.querySelector("#hero");
  if (hero) {
    heroTimer = setInterval(() => setSlide(slideIndex + 1), 6200);
    hero.addEventListener("mouseenter", () => clearInterval(heroTimer));
    hero.addEventListener(
      "mouseleave",
      () => (heroTimer = setInterval(() => setSlide(slideIndex + 1), 6200)),
    );
  }
  if ("IntersectionObserver" in window) {
    const observer = new IntersectionObserver(
      (entries) =>
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            entry.target.classList.add("show");
            observer.unobserve(entry.target);
          }
        }),
      { threshold: 0.12 },
    );
    document
      .querySelectorAll(".reveal")
      .forEach((node) => observer.observe(node));
  }

  document.querySelectorAll(".product-card").forEach((card) => {
    card.tabIndex = 0;
    card.addEventListener("click", (event) => {
      if (event.target.closest(".heart")) return;
      const modal = document.querySelector("#quickModal");
      if (!modal) return;
      const data = card.dataset;
      document.querySelector("#quickImage").src = data.image;
      document.querySelector("#quickImage").alt = data.name;
      document.querySelector("#quickCategory").textContent = data.category;
      document.querySelector("#quickName").textContent = data.name;
      document.querySelector("#quickPrice").textContent = data.price;
      modal.dataset.productId =
        Object.keys(catalog).find((id) => catalog[id].name === data.name) || "";
      modal.classList.remove("closed");
      document.body.classList.add("lock");
    });
  });
  document.querySelectorAll(".heart").forEach((button) =>
    button.addEventListener("click", (event) => {
      event.stopPropagation();
      const card = button.closest(".product-card");
      const id = Object.keys(catalog).find(
        (key) => catalog[key].name === card?.dataset.name,
      );
      if (!id) return;
      const added = toggleFavorite(id);
      button.classList.toggle("liked", added);
      button.querySelector("span").textContent = added ? "♥" : "♡";
      toast(added ? "Favorilerine eklendi." : "Favorilerinden çıkarıldı.");
    }),
  );
  document.querySelectorAll(".filters button").forEach((button) =>
    button.addEventListener("click", () => {
      document
        .querySelectorAll(".filters button")
        .forEach((item) => item.classList.toggle("active", item === button));
      document
        .querySelectorAll(".product-card")
        .forEach((card) =>
          card.classList.toggle(
            "hidden",
            button.dataset.filter !== "all" &&
              card.dataset.category !== button.dataset.filter,
          ),
        );
    }),
  );
  document.querySelectorAll(".filter-bar [data-filter]").forEach((button) =>
    button.addEventListener("click", () => {
      document
        .querySelectorAll(".filter-bar [data-filter]")
        .forEach((item) => item.classList.toggle("active", item === button));
      document
        .querySelectorAll(".sale-card")
        .forEach((card) =>
          card.classList.toggle(
            "is-hidden",
            button.dataset.filter !== "all" &&
              card.dataset.category !== button.dataset.filter,
          ),
        );
    }),
  );

  document
    .querySelector("[data-quick-close]")
    ?.addEventListener("click", () => {
      document.querySelector("#quickModal")?.classList.add("closed");
      document.body.classList.remove("lock");
    });
  document.querySelector("#addToCart")?.addEventListener("click", () => {
    const id = document.querySelector("#quickModal")?.dataset.productId;
    const size = document
      .querySelector(".quick-card .sizes button.active")
      ?.textContent.trim();
    if (!id || !size) return;
    addCart(id, size);
    document.querySelector("#quickModal")?.classList.add("closed");
    document.body.classList.remove("lock");
  });

  const saleId = (card) =>
    Object.keys(catalog).find(
      (id) =>
        catalog[id].name ===
        card?.querySelector("[data-add-cart]")?.dataset.addCart,
    );
  document.querySelectorAll(".sale-card").forEach((card) => {
    card.addEventListener("click", (event) => {
      if (!event.target.closest("button"))
        window.location.href = `urun.html?id=${saleId(card)}`;
    });
    card.tabIndex = 0;
    card.addEventListener("keydown", (event) => {
      if (event.key === "Enter")
        window.location.href = `urun.html?id=${saleId(card)}`;
    });
  });
  document
    .querySelectorAll("[data-add-cart]")
    .forEach((button) =>
      button.addEventListener("click", () =>
        addCart(saleId(button.closest(".sale-card"))),
      ),
    );
  document.querySelectorAll("[data-favorite]").forEach((button) =>
    button.addEventListener("click", () => {
      const id = saleId(button.closest(".sale-card"));
      if (!id) return;
      const added = toggleFavorite(id);
      button.textContent = added ? "♥" : "♡";
      toast(added ? "Favorilerine eklendi." : "Favorilerinden çıkarıldı.");
    }),
  );

  const saved = document.querySelector("#savedProducts");
  const cartSummary = document.querySelector("#cartSummary");
  const renderCartSummary = () => {
    if (!cartSummary) return;
    const items = getCart();
    const total = items.reduce(
      (sum, item) => sum + priceOf(item.id) * item.quantity,
      0,
    );
    cartSummary.hidden = !items.length;
    cartSummary.innerHTML = items.length
      ? `<h2>Sipariş özeti</h2><p><span>Ürünler (${items.reduce((sum, item) => sum + item.quantity, 0)})</span><strong>₺${total.toLocaleString("tr-TR")}</strong></p><p><span>Kargo</span><strong>${total >= 3500 ? "Ücretsiz" : "₺79"}</strong></p><p class="cart-total"><span>Toplam</span><strong>₺${(total + (total >= 3500 ? 0 : 79)).toLocaleString("tr-TR")}</strong></p><button class="button button-dark" type="button" data-checkout>Ödemeye geç <span>→</span></button><small>Ödeme adımı çok yakında aktif olacak. Ürünlerin sepetinde saklı.</small>`
      : "";
    cartSummary
      .querySelector("[data-checkout]")
      ?.addEventListener("click", () =>
        toast("Ödeme adımı çok yakında aktif olacak."),
      );
  };
  const refreshCart = () => {
    updateCounts();
    renderSaved();
    renderCartSummary();
  };
  const renderSaved = () => {
    if (!saved) return;
    const mode = saved.dataset.mode;
    if (mode === "cart") {
      const items = getCart();
      if (!items.length) {
        saved.innerHTML = `<section class="commerce-empty"><p class="eyebrow">PELISH</p><h1>Sepetin henüz boş.</h1><p>İyi hissettiren bir parçayı seçtiğinde burada seni bekliyor olacak.</p><a class="button button-dark" href="indirimler.html">İndirimleri keşfet <span>→</span></a></section>`;
        return;
      }
      saved.innerHTML = items
        .map((item) => {
          const product = catalog[item.id];
          return `<article class="saved-card"><a href="urun.html?id=${item.id}"><img src="${product.image}" alt="${product.name}"></a><div class="saved-card-copy"><small>${product.category}</small><h2>${product.name}</h2><strong>${product.price}</strong><div class="cart-variants">${item.size ? `<small class="cart-size">Beden: ${item.size}</small>` : ""}${item.color ? `<small class="cart-color"><i style="--cart-color:${item.color}"></i>Renk: ${item.colorName || item.color}</small>` : ""}</div><div class="saved-card-actions cart-line-actions"><div class="quantity-control" aria-label="${product.name} adet seçimi"><button type="button" data-quantity-minus data-cart-id="${item.id}" data-cart-size="${item.size}" data-cart-color="${item.color}" ${item.quantity === 1 ? "disabled" : ""} aria-label="Adedi azalt">−</button><output>${item.quantity} adet</output><button type="button" data-quantity-plus data-cart-id="${item.id}" data-cart-size="${item.size}" data-cart-color="${item.color}" aria-label="Adedi artır">+</button></div><button type="button" data-cart-remove data-cart-id="${item.id}" data-cart-size="${item.size}" data-cart-color="${item.color}">Kaldır</button></div></div></article>`;
        })
        .join("");
      saved.querySelectorAll("[data-quantity-plus]").forEach((button) =>
        button.addEventListener("click", () => {
          const cart = getCart();
          const line = cart.find(
            (item) =>
              item.id === button.dataset.cartId &&
              item.size === button.dataset.cartSize &&
              item.color === button.dataset.cartColor,
          );
          if (line) line.quantity += 1;
          setCart(cart);
          refreshCart();
        }),
      );
      saved.querySelectorAll("[data-quantity-minus]").forEach((button) =>
        button.addEventListener("click", () => {
          const cart = getCart();
          const line = cart.find(
            (item) =>
              item.id === button.dataset.cartId &&
              item.size === button.dataset.cartSize &&
              item.color === button.dataset.cartColor,
          );
          if (!line || line.quantity <= 1) return;
          line.quantity -= 1;
          setCart(cart);
          refreshCart();
        }),
      );
      saved.querySelectorAll("[data-cart-remove]").forEach((button) =>
        button.addEventListener("click", () => {
          setCart(
            getCart().filter(
              (item) =>
                item.id !== button.dataset.cartId ||
                item.size !== button.dataset.cartSize ||
                item.color !== button.dataset.cartColor,
            ),
          );
          refreshCart();
        }),
      );
      return;
    }
    const favorites = getList("favorites").filter((id) => catalog[id]);
    if (!favorites.length) {
      saved.innerHTML = `<section class="commerce-empty"><p class="eyebrow">PELISH</p><h1>Henüz favori ürünün yok.</h1><p>İyi hissettiren bir parçayı seçtiğinde burada seni bekliyor olacak.</p><a class="button button-dark" href="indirimler.html">İndirimleri keşfet <span>→</span></a></section>`;
      return;
    }
    saved.innerHTML = favorites
      .map((id) => {
        const product = catalog[id];
        return `<article class="saved-card"><a href="urun.html?id=${id}"><img src="${product.image}" alt="${product.name}"></a><div class="saved-card-copy"><small>${product.category}</small><h2>${product.name}</h2><strong>${product.price}</strong><div class="saved-card-actions"><a href="urun.html?id=${id}">İncele</a><button type="button" data-favorite-remove="${id}">Kaldır</button></div></div></article>`;
      })
      .join("");
    saved.querySelectorAll("[data-favorite-remove]").forEach((button) =>
      button.addEventListener("click", () => {
        setList(
          "favorites",
          getList("favorites").filter(
            (id) => id !== button.dataset.favoriteRemove,
          ),
        );
        updateCounts();
        renderSaved();
      }),
    );
  };
  renderSaved();
  renderCartSummary();

  document.querySelectorAll(".faq-question").forEach((button) =>
    button.addEventListener("click", () => {
      const item = button.closest(".faq-item");
      const open = item.classList.toggle("open");
      button.setAttribute("aria-expanded", String(open));
    }),
  );
  document.querySelectorAll("[data-inquiry-form]").forEach((form) =>
    form.addEventListener("submit", (event) => {
      event.preventDefault();
      const status = form.querySelector(".form-status");
      if (status)
        status.textContent = "Mesajın alındı. En kısa sürede dönüş yapacağız.";
      form.reset();
    }),
  );
  document.querySelectorAll("[data-newsletter-form]").forEach((form) =>
    form.addEventListener("submit", (event) => {
      event.preventDefault();
      form.reset();
      toast("E-posta adresin listeye eklendi.");
    }),
  );
  document.querySelectorAll("[data-close]").forEach((button) =>
    button.addEventListener("click", () => {
      document
        .querySelector(`#${button.dataset.close}`)
        ?.classList.add("closed");
      document.body.classList.remove("lock");
    }),
  );
  document
    .querySelector("#welcomeModal .button[data-close]")
    ?.addEventListener("click", () =>
      document
        .querySelector("#sale")
        ?.scrollIntoView({ behavior: "smooth", block: "start" }),
    );
  document
    .querySelector("#newsletterForm")
    ?.addEventListener("submit", (event) => {
      event.preventDefault();
      event.currentTarget.reset();
      toast("Teşekkürler, listeye eklendin.");
    });
  document
    .querySelector("#moreProducts")
    ?.addEventListener("click", () =>
      toast("Yeni ürünler çok yakında burada olacak."),
    );
  document
    .querySelector("[data-toast-close]")
    ?.addEventListener("click", () =>
      document.querySelector("#toast")?.classList.remove("show"),
    );
  document.addEventListener("keydown", (event) => {
    if (event.key !== "Escape") return;
    setMenu(false);
    search?.classList.remove("open");
    document.querySelector("#quickModal")?.classList.add("closed");
    document.body.classList.remove("lock");
  });

  updateCounts();
  window.pelishCatalog = catalog;
  window.pelishStore = { addCart, getCart, setCart, updateCounts };
})();
