import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
  static targets = [
    "sidebar",
    "overlay",
    "toggleBtn",
    "toggleIcon",
    "mobileCartIcon",
  ];

  connect() {
    // fermer avec Escape
    this._onKeydown = (e) => {
      if (e.key === "Escape") this.close();
    };
    document.addEventListener("keydown", this._onKeydown);

    // Scroll reveal (remplace ton script)
    this.initReveal();

    // Écoute un event pour "shake" le panier (ex: après add-to-cart)
    this._onShake = () => this.shakeCart();
    window.addEventListener("cart:shake", this._onShake);
  }

  disconnect() {
    document.removeEventListener("keydown", this._onKeydown);
    window.removeEventListener("cart:shake", this._onShake);
    this.revealObserver?.disconnect();
  }

  // ===== Sidebar open/close =====
  toggle() {
    this.isOpen() ? this.close() : this.open();
  }

  open() {
    this.sidebarTarget.classList.add("is-open");
    this.overlayTarget?.classList.add("is-active");
    this.toggleBtnTarget?.setAttribute("aria-expanded", "true");
    document.body.style.overflow = "hidden";

    if (this.hasToggleIconTarget) {
      this.toggleIconTarget.classList.remove("fa-bars");
      this.toggleIconTarget.classList.add("fa-times");
    }
  }

  close() {
    this.sidebarTarget.classList.remove("is-open");
    this.overlayTarget?.classList.remove("is-active");
    this.toggleBtnTarget?.setAttribute("aria-expanded", "false");
    document.body.style.overflow = "";

    if (this.hasToggleIconTarget) {
      this.toggleIconTarget.classList.remove("fa-times");
      this.toggleIconTarget.classList.add("fa-bars");
    }
  }

  closeIfMobile(event) {
    // ferme uniquement sur mobile (match ton CSS)
    if (window.innerWidth <= 768) {
      // si clic sur un lien
      const link = event.target.closest("a");
      if (link) this.close();
    }
  }

  isOpen() {
    return this.sidebarTarget.classList.contains("is-open");
  }

  // ===== Shake panier =====
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

  // ===== Scroll reveal =====
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
      { threshold: 0.1 }
    );

    revealEls.forEach((el) => this.revealObserver.observe(el));
  }
}