import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
  static targets = ["toastContainer"];
  static values = {
    cartDataUrl: String,
    addUrlTemplate: String,
  };

  connect() {
    // juste au chargement si tu veux
    this.refreshCartCount();
  }

  async refreshCartCount() {
    if (!this.cartDataUrlValue) return;

    try {
      const res = await fetch(this.cartDataUrlValue, {
        headers: { "X-Requested-With": "XMLHttpRequest" },
      });
      const data = await res.json();

      // event global (écouté par cart-badge)
      window.dispatchEvent(new CustomEvent("cart:count", { detail: { count: data.count || 0 } }));
    } catch (_) {}
  }

  async addToCart(event) {
    event.preventDefault();
    event.stopPropagation();

    const btn = event.currentTarget;

    const id = btn.dataset.catalogueProductIdValue;
    const name = btn.dataset.catalogueProductNameValue;

    if (!id) {
      console.error("ID manquant sur le bouton", btn);
      this.showToast("Erreur", "ID produit manquant.", "error");
      return;
    }

    const orig = btn.innerHTML;
    btn.classList.add("is-loading");
    btn.innerHTML = '<i class="fas fa-spinner fa-spin" style="font-size:0.75rem;"></i>';

    try {
      const fd = new FormData();
      fd.append("quantity", 1);

      const url = (this.addUrlTemplateValue || "/panier/ajouter/__ID__").replace("__ID__", id);

      const res = await fetch(url, {
        method: "POST",
        headers: { "X-Requested-With": "XMLHttpRequest" },
        body: fd,
      });

      const data = await res.json();

      if (data.success) {
        btn.classList.remove("is-loading");
        btn.classList.add("is-success");
        btn.innerHTML = '<i class="fas fa-check" style="font-size:0.75rem;"></i> Ajouté !';

        window.dispatchEvent(new CustomEvent("cart:count", { detail: { count: data.cartCount ?? 0 } }));
        window.dispatchEvent(new CustomEvent("cart:shake"));

        this.showToast("Ajouté au panier !", `"${name}" a bien été ajouté.`, "success");

        setTimeout(() => {
          btn.classList.remove("is-success");
          btn.innerHTML = orig;
        }, 2000);
      } else {
        btn.innerHTML = orig;
        btn.classList.remove("is-loading");
        this.showToast("Erreur", data.message || "Impossible d'ajouter ce produit.", "error");
      }
    } catch (_) {
      btn.innerHTML = orig;
      btn.classList.remove("is-loading");
      this.showToast("Erreur réseau", "Veuillez réessayer.", "error");
    }
  }

  showToast(title, message, type = "success") {
    const container = this.toastContainerTarget;
    if (!container) return;

    const icons = { success: "fa-circle-check", error: "fa-circle-xmark", warning: "fa-triangle-exclamation" };

    const toast = document.createElement("div");
    toast.className = "toast";
    toast.innerHTML = `
      <div class="toast-inner">
        <div class="toast-icon ${type !== "success" ? "is-" + type : ""}">
          <i class="fas ${icons[type] || icons.success}"></i>
        </div>
        <div class="toast-body">
          <div class="toast-title">${this.esc(title)}</div>
          <div class="toast-msg">${this.esc(message)}</div>
        </div>
        <button class="toast-close" type="button" data-action="click->catalogue#dismissToast" aria-label="Fermer">
          <i class="fas fa-xmark"></i>
        </button>
      </div>
      <div class="toast-bar ${type !== "success" ? "is-" + type : ""}"></div>
    `;

    container.appendChild(toast);
    requestAnimationFrame(() => toast.classList.add("is-show"));
    setTimeout(() => this._dismiss(toast), 3500);
  }

  dismissToast(event) {
    this._dismiss(event.currentTarget.closest(".toast"));
  }

  _dismiss(toast) {
    if (!toast) return;
    toast.classList.add("is-hide");
    setTimeout(() => toast.remove(), 280);
  }

  esc(str) {
    const d = document.createElement("div");
    d.appendChild(document.createTextNode(str || ""));
    return d.innerHTML;
  }
}