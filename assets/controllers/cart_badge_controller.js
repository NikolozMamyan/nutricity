import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
  static targets = ["badge", "sidebarCount"];
  static values = { url: String };

  connect() {
    this._onCount = (e) => this.update(e?.detail?.count ?? 0);
    window.addEventListener("cart:count", this._onCount);
    this.fetchCount();
  }

  disconnect() {
    window.removeEventListener("cart:count", this._onCount);
  }

  async fetchCount() {
    if (!this.urlValue) return;
    try {
      const res = await fetch(this.urlValue, {
        headers: { "X-Requested-With": "XMLHttpRequest" },
      });
      const data = await res.json();
      this.update(data.count || 0);
    } catch (_) {}
  }

  update(count) {
    if (this.hasBadgeTarget) this.apply(this.badgeTarget, count);
    if (this.hasSidebarCountTarget) this.apply(this.sidebarCountTarget, count);
  }

apply(el, count) {
  if (!el) return; // 🔥 PROTECTION ANTI-NULL

  if (count > 0) {
    el.textContent = count > 99 ? "99+" : String(count);
    el.style.display = "flex";
  } else {
    el.style.display = "none";
  }
}
}