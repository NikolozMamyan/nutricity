import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
  static targets = ["toastContainer"];

  async addToCart(event) {
    event.preventDefault();

    const form = event.currentTarget;
    const button = form.querySelector('[data-role="add-button"]');
    const productName = form.dataset.productName || "Produit";

    if (!form.action || !button) {
      this.showToast("Erreur", "Action panier indisponible.", "error");
      return;
    }

    const originalHtml = button.innerHTML;
    button.disabled = true;
    button.classList.add("is-loading");
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Ajout...</span>';

    try {
      const response = await fetch(form.action, {
        method: "POST",
        headers: { "X-Requested-With": "XMLHttpRequest" },
        body: new FormData(form),
      });

      const data = await response.json().catch(() => ({}));

      if (!response.ok || data.success !== true) {
        throw new Error(data.message || "Impossible d'ajouter ce produit.");
      }

      window.dispatchEvent(
        new CustomEvent("cart:count", {
          detail: { count: Number(data.cartCount ?? 0) },
        }),
      );

      button.classList.remove("is-loading");
      button.classList.add("is-success");
      button.innerHTML = '<i class="fas fa-check"></i><span>Ajoute</span>';

      this.showToast("Ajoute au panier", `"${productName}" a bien ete ajoute.`, "success");

      window.setTimeout(() => {
        button.disabled = false;
        button.classList.remove("is-success");
        button.innerHTML = originalHtml;
      }, 1800);
    } catch (error) {
      button.disabled = false;
      button.classList.remove("is-loading");
      button.innerHTML = originalHtml;
      this.showToast("Erreur", error.message || "Veuillez reessayer.", "error");
    }
  }

  dismissToast(event) {
    this.hideToast(event.currentTarget.closest(".toast"));
  }

  showToast(title, message, type = "success") {
    if (!this.hasToastContainerTarget) return;

    const icons = {
      success: "fa-circle-check",
      error: "fa-circle-xmark",
      warning: "fa-triangle-exclamation",
    };

    const toast = document.createElement("div");
    toast.className = "toast";
    toast.innerHTML = `
      <div class="toast-inner">
        <div class="toast-icon ${type !== "success" ? `is-${type}` : ""}">
          <i class="fas ${icons[type] || icons.success}"></i>
        </div>
        <div class="toast-body">
          <div class="toast-title">${this.escape(title)}</div>
          <div class="toast-msg">${this.escape(message)}</div>
        </div>
        <button class="toast-close" type="button" data-action="click->category-page#dismissToast" aria-label="Fermer">
          <i class="fas fa-xmark"></i>
        </button>
      </div>
      <div class="toast-bar ${type !== "success" ? `is-${type}` : ""}"></div>
    `;

    this.toastContainerTarget.appendChild(toast);
    requestAnimationFrame(() => toast.classList.add("is-show"));
    window.setTimeout(() => this.hideToast(toast), 3200);
  }

  hideToast(toast) {
    if (!toast) return;
    toast.classList.add("is-hide");
    window.setTimeout(() => toast.remove(), 260);
  }

  escape(value) {
    const node = document.createElement("div");
    node.appendChild(document.createTextNode(value || ""));
    return node.innerHTML;
  }
}
