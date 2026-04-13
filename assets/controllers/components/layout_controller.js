import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
  static targets = [
    "sidebar",
    "overlay",
    "toggleBtn",
    "toggleIcon",
    "mobileCartIcon",
    "megaMenu",
    "megaTrigger",
    "previewTitle",
    "previewLink",
    "previewSubcats",
    "previewProducts",
  ];

  connect() {
    this.previewCache = new Map();
    this._onKeydown = (e) => {
      if (e.key === "Escape") {
        this.closeMegaMenu();
        this.closeMobileMenu();
      }
    };

    this._onDocumentClick = (e) => {
      if (this.hasMegaMenuTarget && this.hasMegaTriggerTarget) {
        const clickedInsideMega = this.megaMenuTarget.contains(e.target);
        const clickedTrigger = this.megaTriggerTarget.contains(e.target);

        if (!clickedInsideMega && !clickedTrigger) {
          this.closeMegaMenu();
        }
      }
    };

    document.addEventListener("keydown", this._onKeydown);
    document.addEventListener("click", this._onDocumentClick);

    this.initReveal();

    this._onShake = () => this.shakeCart();
    window.addEventListener("cart:shake", this._onShake);
  }

  disconnect() {
    document.removeEventListener("keydown", this._onKeydown);
    document.removeEventListener("click", this._onDocumentClick);
    window.removeEventListener("cart:shake", this._onShake);
    this.revealObserver?.disconnect();
  }

  toggleMegaMenu(event) {
    event?.stopPropagation();

    if (this.isMegaMenuOpen()) {
      this.closeMegaMenu();
      return;
    }

    this.openMegaMenu();
    const firstCategory = this.element.querySelector(".mega-category-link[data-slug]");
    if (firstCategory) {
      this.previewCategory({ currentTarget: firstCategory });
    }
  }

  openMegaMenu() {
    if (!this.hasMegaMenuTarget || !this.hasMegaTriggerTarget) return;

    this.megaMenuTarget.classList.add("is-open");
    this.megaTriggerTarget.classList.add("is-open");
    this.megaTriggerTarget.setAttribute("aria-expanded", "true");
  }

  closeMegaMenu() {
    if (!this.hasMegaMenuTarget || !this.hasMegaTriggerTarget) return;

    this.megaMenuTarget.classList.remove("is-open");
    this.megaTriggerTarget.classList.remove("is-open");
    this.megaTriggerTarget.setAttribute("aria-expanded", "false");
  }

  isMegaMenuOpen() {
    return this.hasMegaMenuTarget && this.megaMenuTarget.classList.contains("is-open");
  }

  async previewCategory(event) {
    const link = event.currentTarget;
    const slug = link?.dataset?.slug;
    if (!slug) return;

    this.element.querySelectorAll(".mega-category-link.is-active").forEach((el) => el.classList.remove("is-active"));
    link.classList.add("is-active");

    if (this.previewCache.has(slug)) {
      this.renderPreview(this.previewCache.get(slug));
      return;
    }

    const urlTemplate = this.megaMenuTarget?.dataset?.layoutPreviewUrlTemplateValue;
    if (!urlTemplate) return;
    const url = urlTemplate.replace("__slug__", slug);

    this.renderLoading();

    try {
      const res = await fetch(url, {
        headers: { "X-Requested-With": "XMLHttpRequest" },
      });
      const data = await res.json();
      if (!res.ok || !data.success) return;

      this.previewCache.set(slug, data);
      this.renderPreview(data);
    } catch (error) {
      console.error("Mega menu preview error", error);
    }
  }

  async toggleMobileSubmenu(event) {
    event.preventDefault();

    const link = event.currentTarget;
    const slug = link?.dataset?.slug;
    if (!slug) return;

    const submenu = this.element.querySelector(`[data-layout-mobile-submenu="${slug}"]`);
    if (!submenu) return;

    const isOpen = submenu.classList.contains("is-open");

    this.element.querySelectorAll(".mobile-submenu.is-open").forEach((el) => {
      if (el !== submenu) el.classList.remove("is-open");
    });

    if (isOpen) {
      submenu.classList.remove("is-open");
      return;
    }

    submenu.classList.add("is-open");

    if (submenu.dataset.loaded === "true") return;

    submenu.innerHTML = `
      <div class="mega-loading">
        <i class="fas fa-spinner fa-spin" aria-hidden="true"></i>
        Chargement...
      </div>
    `;

    const urlTemplate = this.megaMenuTarget?.dataset?.layoutPreviewUrlTemplateValue;
    if (!urlTemplate) return;

    try {
      const res = await fetch(urlTemplate.replace("__slug__", slug), {
        headers: { "X-Requested-With": "XMLHttpRequest" },
      });
      const data = await res.json();
      if (!res.ok || !data.success) return;

      submenu.innerHTML = (data.items || [])
        .slice(0, 4)
        .map((item) => {
          const image = item.image ? `/uploads/products/${item.image}` : "";
          return `
            <a class="mobile-submenu-item" href="/produit/${item.slug}">
              <span class="mobile-submenu-thumb">
                ${image ? `<img src="${image}" alt="${this.esc(item.name)}" loading="lazy">` : `<i class="fas fa-apple-alt"></i>`}
              </span>
              <span class="mobile-submenu-name">${this.esc(item.name)}</span>
              <span class="mobile-submenu-price">${item.price} EUR</span>
            </a>
          `;
        })
        .join("");

      submenu.dataset.loaded = "true";
    } catch (error) {
      console.error("Mobile submenu error", error);
    }
  }

  renderLoading() {
    if (this.hasPreviewProductsTarget) {
      this.previewProductsTarget.innerHTML = `
        <div class="mega-loading">
          <i class="fas fa-spinner fa-spin" aria-hidden="true"></i>
          Chargement...
        </div>
      `;
    }
  }

  renderPreview(data) {
    const category = data.category || {};
    const items = Array.isArray(data.items) ? data.items : [];

    if (this.hasPreviewTitleTarget) {
      this.previewTitleTarget.textContent = category.name || "Produits";
    }

    if (this.hasPreviewLinkTarget) {
      this.previewLinkTarget.href = category.url || "/produits";
    }

    if (this.hasPreviewSubcatsTarget) {
      this.previewSubcatsTarget.innerHTML = items
        .slice(0, 3)
        .map((item, index) => {
          const image = item.image ? `/uploads/products/${item.image}` : "";
          return `
            <a class="mega-subcat-card" href="/produit/${item.slug}">
              ${image ? `<img src="${image}" alt="${this.esc(item.name)}" loading="lazy">` : ""}
              <div class="mega-subcat-body">
                <span class="mega-subcat-icon"><i class="fas ${this.subcatIcon(index)}" aria-hidden="true"></i></span>
                <span class="mega-subcat-name">${this.esc(item.name)}</span>
              </div>
            </a>
          `;
        })
        .join("");
    }

    if (this.hasPreviewProductsTarget) {
      this.previewProductsTarget.innerHTML = items
        .slice(0, 6)
        .map((item) => {
          const image = item.image ? `/uploads/products/${item.image}` : "";
          return `
            <a class="mega-product-card" href="/produit/${item.slug}">
              <div class="mega-product-image">
                ${image ? `<img src="${image}" alt="${this.esc(item.name)}" loading="lazy">` : `<i class="fas fa-apple-alt" aria-hidden="true"></i>`}
              </div>
              <div class="mega-product-name">${this.esc(item.name)}</div>
              <div class="mega-product-price">${item.price} EUR</div>
            </a>
          `;
        })
        .join("");
    }
  }

  subcatIcon(index) {
    return ["fa-fire", "fa-star", "fa-bolt"][index] || "fa-leaf";
  }

  toggle() {
    this.isMobileMenuOpen() ? this.closeMobileMenu() : this.openMobileMenu();
  }

  openMobileMenu() {
    if (!this.hasSidebarTarget) return;

    this.sidebarTarget.classList.add("is-open");
    this.overlayTarget?.classList.add("is-active");
    document.body.classList.add("sidebar-open");

    if (this.hasToggleIconTarget) {
      this.toggleIconTarget.classList.remove("fa-bars");
      this.toggleIconTarget.classList.add("fa-times");
    }
  }

  closeMobileMenu() {
    if (!this.hasSidebarTarget) return;

    this.sidebarTarget.classList.remove("is-open");
    this.overlayTarget?.classList.remove("is-active");
    document.body.classList.remove("sidebar-open");

    if (this.hasToggleIconTarget) {
      this.toggleIconTarget.classList.remove("fa-times");
      this.toggleIconTarget.classList.add("fa-bars");
    }
  }

  close() {
    this.closeMobileMenu();
  }

  closeIfMobile(event) {
    if (window.innerWidth <= 860) {
      const link = event.target.closest("a");
      if (link) this.closeMobileMenu();
    }
  }

  isMobileMenuOpen() {
    return this.hasSidebarTarget && this.sidebarTarget.classList.contains("is-open");
  }

  shakeCart() {
    const els = [];
    if (this.hasMobileCartIconTarget) els.push(this.mobileCartIconTarget);

    els.forEach((el) => {
      el.classList.remove("cart-shake");
      void el.offsetWidth;
      el.classList.add("cart-shake");
      setTimeout(() => el.classList.remove("cart-shake"), 450);
    });
  }

  initReveal() {
    const revealEls = document.querySelectorAll(".reveal");
    if (!revealEls.length) return;

    this.revealObserver = new IntersectionObserver(
      (entries) => {
        entries.forEach((e) => {
          if (e.isIntersecting) {
            e.target.classList.add("is-visible");
            this.revealObserver.unobserve(e.target);
          }
        });
      },
      { threshold: 0.1 },
    );

    revealEls.forEach((el) => this.revealObserver.observe(el));
  }

  esc(str) {
    const d = document.createElement("div");
    d.appendChild(document.createTextNode(str ?? ""));
    return d.innerHTML;
  }
}
