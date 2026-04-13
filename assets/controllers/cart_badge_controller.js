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

    if (typeof window.__cartCountCache === "number") {
      this.update(window.__cartCountCache);
      return;
    }

    if (window.__cartCountPromise) {
      const count = await window.__cartCountPromise;
      this.update(count);
      return;
    }

    try {
      window.__cartCountPromise = fetch(this.urlValue, {
        headers: { "X-Requested-With": "XMLHttpRequest" },
      })
        .then((res) => res.json())
        .then((data) => Number(data.count || 0))
        .catch(() => 0);

      const count = await window.__cartCountPromise;
      window.__cartCountCache = count;
      this.update(count);
    } catch (_) {}
    finally {
      window.__cartCountPromise = null;
    }
  }

  update(count) {
    window.__cartCountCache = Number(count || 0);
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
