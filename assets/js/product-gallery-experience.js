(() => {
  const toast = (message) => {
    const box = document.querySelector("#toast");
    const text = document.querySelector("#toastText");
    if (!box || !text) return;
    text.textContent = message;
    box.classList.add("show");
    clearTimeout(window.galleryToastTimer);
    window.galleryToastTimer = setTimeout(
      () => box.classList.remove("show"),
      2800,
    );
  };

  document.querySelectorAll(".quick-card .sizes button").forEach((button) => {
    button.addEventListener("click", () => {
      document.querySelectorAll(".quick-card .sizes button").forEach((item) => {
        item.classList.toggle("active", item === button);
      });
    });
  });

  document.addEventListener(
    "click",
    (event) => {
      const card = event.target.closest(".product-card");
      if (!card || event.target.closest(".heart")) return;
      document
        .querySelectorAll(".quick-card .sizes button")
        .forEach((button) => {
          button.classList.remove("active");
        });
    },
    true,
  );

  document.addEventListener(
    "click",
    (event) => {
      const target = event.target.closest("#addToCart");
      if (!target) return;
      const size = document
        .querySelector(".quick-card .sizes button.active")
        ?.textContent.trim();
      if (!size) {
        event.preventDefault();
        event.stopImmediatePropagation();
        toast("Sepete eklemek için beden seçmelisin.");
        return;
      }
    },
    true,
  );

  const detail = document.querySelector("#productDetail");
  const catalog = window.pelishCatalog;
  if (detail && catalog) {
    const requestedId = new URLSearchParams(location.search).get("id");
    const product = catalog[requestedId] || catalog["luna-saten-elbise"];
    const productId = catalog[requestedId] ? requestedId : "luna-saten-elbise";
    const gallery = [
      product.image,
      product.image.replace("w=1200", "w=1100") + "&sat=-18",
      product.image.replace("w=1200", "w=1300") + "&con=8",
    ];
    let active = 0;

    detail.innerHTML = `
      <div class="product-detail-media">
        <div class="product-main-media">
          <button class="gallery-arrow prev" type="button" aria-label="Önceki görsel">←</button>
          <img id="detailMainImage" src="${gallery[0]}" alt="${product.name}">
          <button class="gallery-arrow next" type="button" aria-label="Sonraki görsel">→</button>
        </div>
        <div class="product-thumbnails" role="tablist" aria-label="Ürün görselleri">
          ${gallery
            .map(
              (image, index) => `
                <button class="${index === 0 ? "active" : ""}" type="button" data-gallery-index="${index}" aria-label="${index + 1}. görsel">
                  <img src="${image}" alt="${product.name} görsel ${index + 1}">
                </button>`,
            )
            .join("")}
        </div>
      </div>
      <div class="product-detail-info">
        <p class="eyebrow">${product.category}</p>
        <h1>${product.name}</h1>
        <strong class="product-price"><del>${product.oldPrice}</del>${product.price}</strong>
        <p class="product-description">${product.description}</p>
        <p class="product-option-label">RENK SEÇİMİ</p>
        <div class="product-colors" role="radiogroup" aria-label="Renk seçimi">
          <button class="product-color active" type="button" style="--color:#FDEAB9" data-color="#FDEAB9" data-color-name="Krem" aria-label="Krem" aria-pressed="true"></button>
          <button class="product-color" type="button" style="--color:#E7A5AB" data-color="#E7A5AB" data-color-name="Pudra pembe" aria-label="Pudra pembe" aria-pressed="false"></button>
          <button class="product-color" type="button" style="--color:#A7CBDD" data-color="#A7CBDD" data-color-name="Mavi" aria-label="Mavi" aria-pressed="false"></button>
        </div>
        <p class="product-option-label">BEDEN SEÇİMİ</p>
        <div class="product-sizes" role="radiogroup" aria-label="Beden seçimi">
          <button type="button">S</button><button type="button">M</button><button type="button">L</button><button type="button">XL</button><button type="button">XXL</button>
        </div>
        <button class="button button-dark product-add" type="button">Sepete ekle <span>→</span></button>
      </div>
      <div class="product-lightbox" id="productLightbox" aria-hidden="true">
        <div class="lightbox-frame">
          <button class="lightbox-close" type="button" aria-label="Görseli kapat">×</button>
          <button class="lightbox-arrow prev" type="button" aria-label="Önceki görsel">←</button>
          <img id="lightboxImage" src="${gallery[0]}" alt="${product.name}">
          <button class="lightbox-arrow next" type="button" aria-label="Sonraki görsel">→</button>
        </div>
      </div>`;

    const mainImage = detail.querySelector("#detailMainImage");
    const lightbox = detail.querySelector("#productLightbox");
    const lightboxImage = detail.querySelector("#lightboxImage");
    const setImage = (index) => {
      active = (index + gallery.length) % gallery.length;
      mainImage.src = gallery[active];
      lightboxImage.src = gallery[active];
      detail.querySelectorAll("[data-gallery-index]").forEach((button) => {
        button.classList.toggle(
          "active",
          Number(button.dataset.galleryIndex) === active,
        );
      });
    };
    const closeLightbox = () => {
      lightbox.classList.remove("open");
      lightbox.setAttribute("aria-hidden", "true");
      document.body.classList.remove("lock");
    };

    detail.querySelectorAll("[data-gallery-index]").forEach((button) => {
      button.addEventListener("click", () =>
        setImage(Number(button.dataset.galleryIndex)),
      );
    });
    detail
      .querySelectorAll(".gallery-arrow.prev")
      .forEach((button) =>
        button.addEventListener("click", () => setImage(active - 1)),
      );
    detail
      .querySelectorAll(".gallery-arrow.next")
      .forEach((button) =>
        button.addEventListener("click", () => setImage(active + 1)),
      );
    mainImage.addEventListener("click", () => {
      lightbox.classList.add("open");
      lightbox.setAttribute("aria-hidden", "false");
      document.body.classList.add("lock");
    });
    lightbox
      .querySelector(".lightbox-close")
      .addEventListener("click", closeLightbox);
    lightbox.addEventListener("click", (event) => {
      if (event.target === lightbox) closeLightbox();
    });
    lightbox
      .querySelector(".lightbox-arrow.prev")
      .addEventListener("click", () => setImage(active - 1));
    lightbox
      .querySelector(".lightbox-arrow.next")
      .addEventListener("click", () => setImage(active + 1));
    document.addEventListener("keydown", (event) => {
      if (!lightbox.classList.contains("open")) return;
      if (event.key === "Escape") closeLightbox();
      if (event.key === "ArrowLeft") setImage(active - 1);
      if (event.key === "ArrowRight") setImage(active + 1);
    });

    detail.querySelectorAll(".product-color").forEach((button) => {
      button.addEventListener("click", () => {
        detail.querySelectorAll(".product-color").forEach((item) => {
          const selected = item === button;
          item.classList.toggle("active", selected);
          item.setAttribute("aria-pressed", String(selected));
        });
      });
    });
    detail.querySelectorAll(".product-sizes button").forEach((button) => {
      button.addEventListener("click", () => {
        detail.querySelectorAll(".product-sizes button").forEach((item) => {
          item.classList.toggle("active", item === button);
        });
      });
    });
    detail.querySelector(".product-add").addEventListener("click", () => {
      const size = detail
        .querySelector(".product-sizes button.active")
        ?.textContent.trim();
      if (!size) {
        toast("Sepete eklemek için beden seçmelisin.");
        return;
      }
      const color = detail.querySelector(".product-color.active");
      window.pelishStore?.addCart(productId, size, {
        color: color?.dataset.color,
        colorName: color?.dataset.colorName,
      });
    });
  }
})();
