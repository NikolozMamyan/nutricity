// assets/controllers/product_show_controller.js
import { Controller } from "@hotwired/stimulus";

/**
 * Controller: product-show
 * - Tabs (Description / Informations)
 * - Qty selector
 * - Add to cart (AJAX)
 * - Updates cart badge (works with your cart-badge controller targets if present)
 * - Optional toast dispatch (works if you have a toast controller listening)
 */
export default class extends Controller {
  static values = {
    cartDataUrl: String,
    addUrl: String,
    productId: Number,
    productName: String,
    maxQty: Number,
    cartUrl: String,
  };

  static targets = ["qtyInput", "btnAdd", "tabBtn", "tabPanel"];

  connect() {
    // Ensure qty is valid on load
    this.normalizeQty();

    // Optional: if you want to refresh badge on page load
    // (safe: does nothing if cartDataUrl not set)
    this.refreshBadgeFromServer().catch(() => {});
  }

  // -------------------------
  // Tabs
  // -------------------------
  switchTab(event) {
    const targetId = event.params?.tab;
    if (!targetId) return;

    // buttons
    this.tabBtnTargets.forEach((btn) => {
      const isActive = btn.getAttribute("aria-controls") === targetId;
      btn.classList.toggle("active", isActive);
      btn.setAttribute("aria-selected", isActive ? "true" : "false");
    });

    // panels
    this.tabPanelTargets.forEach((panel) => {
      panel.classList.toggle("active", panel.id === targetId);
    });
  }

  // -------------------------
  // Qty
  // -------------------------
  plus() {
    const cur = this.getQty();
    const max = this.getMaxQty();
    this.setQty(Math.min(max, cur + 1));
  }

  minus() {
    const cur = this.getQty();
    this.setQty(Math.max(1, cur - 1));
  }

  qtyInput() {
    // user typing: keep it permissive but clamp if crazy
    this.normalizeQty(false);
  }

  qtyChange() {
    // on change: clamp strictly
    this.normalizeQty(true);
  }

  normalizeQty(strict = true) {
    if (!this.hasQtyInputTarget) return;

    const max = this.getMaxQty();
    let v = parseInt(this.qtyInputTarget.value || "1", 10);

    if (Number.isNaN(v)) v = 1;

    if (strict) {
      if (v < 1) v = 1;
      if (v > max) v = max;
    } else {
      // soft clamp: just avoid negatives / 0
      if (v < 1) v = 1;
      if (v > max) v = max;
    }

    this.qtyInputTarget.value = String(v);
  }

  getQty() {
    if (!this.hasQtyInputTarget) return 1;
    const v = parseInt(this.qtyInputTarget.value || "1", 10);
    return Number.isNaN(v) ? 1 : v;
  }

  setQty(v) {
    if (!this.hasQtyInputTarget) return;
    this.qtyInputTarget.value = String(v);
  }

  getMaxQty() {
    const maxFromValue = Number(this.maxQtyValue || 99);
    return Number.isFinite(maxFromValue) && maxFromValue > 0 ? maxFromValue : 99;
  }

  // -------------------------
  // Add to cart
  // -------------------------
  async addToCart() {
    if (!this.hasBtnAddTarget) return;

    const qty = this.getQty();
    const name = this.productNameValue || "Produit";
    const url = this.addUrlValue;

    if (!url) {
      this.toast("Erreur", "URL d'ajout panier manquante.", "error");
      return;
    }

    const btn = this.btnAddTarget;
    const originalHTML = btn.innerHTML;

    this.setBtnState(btn, "loading", true, `<i class="fas fa-spinner fa-spin"></i> Ajout…`);

    try {
      const fd = new FormData();
      fd.append("quantity", String(qty));

      const res = await fetch(url, {
        method: "POST",
        headers: { "X-Requested-With": "XMLHttpRequest" },
        body: fd,
      });

      const data = await res.json().catch(() => ({}));

      if (!res.ok || !data || data.success !== true) {
        const msg = data?.message || "Impossible d'ajouter ce produit.";
        this.setBtnState(btn, "loading", false, originalHTML);
        btn.disabled = false;
        this.toast("Erreur", msg, "error");
        return;
      }

      // success
      const count = Number(data.cartCount ?? data.count ?? 0);
      this.applyBadgeEverywhere(count);

      this.setBtnState(
        btn,
        "success",
        true,
        `<i class="fas fa-check"></i> ${qty > 1 ? `${qty} ajoutés !` : "Ajouté !"}`,
      );

      const cartUrl = this.cartUrlValue || "/click-collect";
      this.toast(
        "Ajouté au panier",
        `${qty}× “${this.esc(name)}” – <a href="${cartUrl}" style="font-weight:700">Voir mon panier →</a>`,
        "success",
        true,
      );

      window.setTimeout(() => {
        btn.classList.remove("success");
        btn.innerHTML = originalHTML;
        btn.disabled = false;
      }, 2200);
    } catch (e) {
      console.error(e);
      this.setBtnState(btn, "loading", false, originalHTML);
      btn.disabled = false;
      this.toast("Erreur réseau", "Veuillez réessayer.", "error");
    }
  }

  setBtnState(btn, cls, disabled, html) {
    btn.classList.remove("loading", "success");
    if (cls) btn.classList.add(cls);
    btn.disabled = !!disabled;
    if (html) btn.innerHTML = html;
  }

  // -------------------------
  // Badge handling (safe, no null.style crash)
  // -------------------------
  async refreshBadgeFromServer() {
    const url = this.cartDataUrlValue;
    if (!url) return;

    const res = await fetch(url, {
      headers: { "X-Requested-With": "XMLHttpRequest" },
    });
    const data = await res.json().catch(() => ({}));
    const count = Number(data.count ?? 0);
    this.applyBadgeEverywhere(count);
  }

  applyBadgeEverywhere(count) {
    // 1) If you have a global cart-badge controller, trigger an event it can listen to
    //    (optional; safe)
    this.dispatch("cart:updated", { detail: { count } });

    // 2) Update legacy badge by id if you still use it somewhere
    //    (safe - checks existence)
    this.applyToElement(document.getElementById("cartNavBadge"), count);

    // 3) Update any element that matches common targets
    //    - mobile: data-cart-badge-target="badge"
    //    - sidebar: data-cart-badge-target="sidebarCount"
    document
      .querySelectorAll('[data-cart-badge-target="badge"], [data-cart-badge-target="sidebarCount"]')
      .forEach((el) => this.applyToElement(el, count));
  }

  applyToElement(el, count) {
    // ✅ Fix for your error: never access el.style if el is null
    if (!el) return;

    if (count > 0) {
      el.textContent = count > 99 ? "99+" : String(count);
      el.style.display = "inline-flex";
      el.classList.remove("bump");
      // reflow for animation
      void el.offsetWidth;
      el.classList.add("bump");
    } else {
      el.style.display = "none";
    }
  }

  // -------------------------
  // Toast helper
  // -------------------------
  toast(title, message, type = "success", allowHtml = false) {
    // If you have a dedicated toast controller, let it handle rendering:
    // dispatch a custom event "toast" that your toast controller can listen to
    this.dispatch("toast", { detail: { title, message, type, allowHtml } });

    // If you DON'T have toast controller, you can still do nothing here safely.
    // (No crash)
  }

  // -------------------------
  // Utils
  // -------------------------
  esc(str) {
    const d = document.createElement("div");
    d.appendChild(document.createTextNode(str ?? ""));
    return d.innerHTML;
  }
}