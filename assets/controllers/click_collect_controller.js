import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
  static values = { routes: Object };

  static targets = [
    "panel",
    "stepper",
    "customerForm",
    "firstName",
    "lastName",
    "phone",
    "email",
    "notes",
    "errFirstName",
    "errLastName",
    "errPhone",
    "errEmail",
    "cartItemsList",
    "cartEmptyMsg",
    "cartCountLabel",
    "btnToStep2",
    "summaryLines1",
    "summaryTotal1",
    "summaryLines2",
    "summaryTotal2",
    "selectedSlotDisplay",
    "selectedSlotText",
    "btnToStep3",
    "summaryLines3",
    "summaryTotal3",
    "slotDisplay3",
    "summaryLines4",
    "summaryTotal4",
    "recapCard",
    "confirmationScreen",
    "orderRef",
    "confirmDetails",
  ];

  connect() {
    this.state = {
      cart: [],
      total: 0,
      count: 0,
      selectedSlot: "",
      customer: {},
      currentStep: 1,
    };

    this.pending = new Map();
    this.debounceTimers = new Map();
    this.checkoutPending = false;

    this.loadCart();
    this.setStep(1);
    this.filterPastSlots();
  }

  async loadCart() {
    try {
      const res = await fetch(this.routesValue.data, {
        headers: { "X-Requested-With": "XMLHttpRequest" },
      });
      const data = await res.json();

      this.state.cart = data.items || [];
      this.state.total = Number(data.total || 0);
      this.state.count = Number(data.count || 0);

      this.renderCart();
      this.renderSummary();
      this.updateCartBadge(this.state.count);
    } catch (e) {
      console.error("Erreur chargement panier", e);
    }
  }

  validateStep3() {
    const firstName = this.hasFirstNameTarget ? this.firstNameTarget.value.trim() : "";
    const lastName = this.hasLastNameTarget ? this.lastNameTarget.value.trim() : "";
    const phone = this.hasPhoneTarget ? this.phoneTarget.value.trim() : "";
    const email = this.hasEmailTarget ? this.emailTarget.value.trim() : "";
    const notes = this.hasNotesTarget ? this.notesTarget.value.trim() : "";

    const phoneProvided = phone.length > 0;
    const phoneOk = !phoneProvided || this.isPhoneValid(phone);
    const emailOk = this.isEmailValid(email);

    this.toggleError(this.hasErrFirstNameTarget ? this.errFirstNameTarget : null, !firstName);
    this.toggleError(this.hasErrLastNameTarget ? this.errLastNameTarget : null, !lastName);
    this.toggleError(this.hasErrEmailTarget ? this.errEmailTarget : null, !emailOk);
    this.toggleError(this.hasErrPhoneTarget ? this.errPhoneTarget : null, phoneProvided && !phoneOk);

    if (!firstName || !lastName || !emailOk || !phoneOk) return;

    this.state.customer = { firstName, lastName, phone, email, notes };
    this.renderRecapCard();
    this.setStep(4);
    window.scrollTo({ top: 0, behavior: "smooth" });
  }

  toggleError(el, show) {
    if (!el) return;
    el.classList.toggle("show", !!show);
  }

  isPhoneValid(phone) {
    const digits = String(phone || "").replace(/\D/g, "");
    return digits.length >= 10;
  }

  isEmailValid(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(email || "").trim());
  }

  renderRecapCard() {
    if (!this.hasRecapCardTarget) return;

    const c = this.state.customer || {};
    const slotHuman = this.state.selectedSlot ? this.humanSlot(this.state.selectedSlot) : "-";

    const linesHtml = this.state.cart
      .map((i) => {
        const lineTotal = this.formatPrice(Number(i.price) * Number(i.quantity));
        return `
          <div class="summary-row">
            <span>${this.esc(i.name)} x${i.quantity}</span>
            <span>${lineTotal}</span>
          </div>
        `;
      })
      .join("");

    this.recapCardTarget.innerHTML = `
      <header class="cc-card-header">
        <h2 class="cc-card-title">
          <i class="fas fa-clipboard-check" aria-hidden="true"></i>
          Verifiez votre commande
        </h2>
      </header>

      <div class="cc-card-body" style="padding: 1rem 1.2rem 1.2rem;">
        <div style="margin-bottom: 1rem;">
          <div style="font-weight:900; color: var(--green-900); margin-bottom:.35rem;">Creneau</div>
          <div style="font-weight:800; color: rgba(0,0,0,0.65);">${this.esc(slotHuman)}</div>
        </div>

        <div style="margin-bottom: 1rem;">
          <div style="font-weight:900; color: var(--green-900); margin-bottom:.35rem;">Coordonnees</div>
          <div style="color: rgba(0,0,0,0.65); line-height:1.6;">
            <div><strong>${this.esc(c.firstName || "")} ${this.esc(c.lastName || "")}</strong></div>
            <div>${this.esc(c.phone || "")}</div>
            ${c.email ? `<div>${this.esc(c.email)}</div>` : ""}
            ${c.notes ? `<div style="margin-top:.5rem;"><em>${this.esc(c.notes)}</em></div>` : ""}
          </div>
        </div>

        <div>
          <div style="font-weight:900; color: var(--green-900); margin-bottom:.35rem;">Articles</div>
          ${linesHtml || `<div style="color: rgba(0,0,0,0.55);">Aucun article</div>`}
        </div>
      </div>
    `;
  }

  filterPastSlots() {
    const now = new Date();

    this.element.querySelectorAll(".cc-slot-card[data-slot-value]").forEach((card) => {
      const slotValue = card.dataset.slotValue;
      const { startDate } = this.parseSlotValue(slotValue);
      if (!startDate) return;

      if (startDate.getTime() <= now.getTime()) {
        card.classList.add("is-disabled");
        card.style.opacity = "0.5";
        card.style.pointerEvents = "none";
      }
    });
  }

  parseSlotValue(slotValue) {
    const [dateIso, range] = String(slotValue || "").split("|");
    if (!dateIso || !range) return { startDate: null, endDate: null };

    const [startStr, endStr] = range.split(/\s*[–-]\s*/).map((s) => s.trim());
    const start = this.parseHourMinute(startStr);
    const end = this.parseHourMinute(endStr);

    if (!start) return { startDate: null, endDate: null };

    const startDate = new Date(`${dateIso}T${start}:00`);
    const endDate = end ? new Date(`${dateIso}T${end}:00`) : null;

    return { startDate, endDate };
  }

  parseHourMinute(s) {
    const m = String(s || "").match(/^(\d{1,2})h(\d{2})$/i);
    if (!m) return null;
    return `${m[1].padStart(2, "0")}:${m[2]}`;
  }

  async updateQty(id, qty) {
    qty = Math.max(0, parseInt(qty || "0", 10));
    if (this.pending.get(id)) return;
    this.pending.set(id, true);

    try {
      const fd = new FormData();
      fd.append("quantity", String(qty));

      const res = await fetch(this.routesValue.update + id, {
        method: "POST",
        headers: { "X-Requested-With": "XMLHttpRequest" },
        body: fd,
      });
      const data = await res.json();
      if (!data.success) return;

      if (data.removed) {
        this.state.cart = this.state.cart.filter((i) => i.id !== id);
      } else {
        const item = this.state.cart.find((i) => i.id === id);
        if (item) item.quantity = qty;
      }

      this.state.total = Number(data.total || 0);
      this.state.count = Number(data.cartCount || 0);

      this.renderCart();
      this.renderSummary();
      this.updateCartBadge(this.state.count);
    } finally {
      this.pending.set(id, false);
    }
  }

  async removeItem(id) {
    if (this.pending.get(id)) return;
    this.pending.set(id, true);

    try {
      const res = await fetch(this.routesValue.remove + id, {
        method: "POST",
        headers: { "X-Requested-With": "XMLHttpRequest" },
        body: new FormData(),
      });
      const data = await res.json();
      if (!data.success) return;

      this.state.cart = this.state.cart.filter((i) => i.id !== id);
      this.state.total = Number(data.total || 0);
      this.state.count = Number(data.cartCount || 0);

      this.renderCart();
      this.renderSummary();
      this.updateCartBadge(this.state.count);
    } finally {
      this.pending.set(id, false);
    }
  }

  async clearCart() {
    if (!confirm("Vider tout le panier ?")) return;

    const res = await fetch(this.routesValue.clear, {
      method: "POST",
      headers: { "X-Requested-With": "XMLHttpRequest" },
      body: new FormData(),
    });
    const data = await res.json();
    if (!data.success) return;

    this.state.cart = [];
    this.state.total = 0;
    this.state.count = 0;

    this.renderCart();
    this.renderSummary();
    this.updateCartBadge(0);
    this.setStep(1);
  }

  goToStep(event) {
    const n = parseInt(event.currentTarget.dataset.step || "1", 10);

    if (n === 2 && this.state.cart.length === 0) return;
    if (n === 3 && !this.state.selectedSlot) return;

    if (n === 4) {
      const c = this.state.customer || {};
      const emailOk = this.isEmailValid(c.email || "");
      const phone = (c.phone || "").trim();
      const phoneOk = phone === "" || this.isPhoneValid(phone);

      if (!this.state.selectedSlot || !c.firstName || !c.lastName || !emailOk || !phoneOk) return;
    }

    if (n > this.state.currentStep + 1) return;

    this.setStep(n);
    window.scrollTo({ top: 0, behavior: "smooth" });
  }

  setStep(n) {
    this.panelTargets.forEach((p) => p.classList.remove("is-active"));
    const panel = this.element.querySelector(`#panel${n}`);
    if (panel) panel.classList.add("is-active");

    this.stepperTarget.querySelectorAll(".cc-step-item").forEach((btn) => {
      const s = parseInt(btn.dataset.step || "0", 10);
      btn.classList.remove("is-active", "is-done");
      if (s === n) btn.classList.add("is-active");
      else if (s < n) btn.classList.add("is-done");
    });

    this.state.currentStep = n;
    this.renderSummary();
  }

  selectSlot(event) {
    const card = event.currentTarget;
    const slotValue = card.dataset.slotValue || "";
    if (!slotValue) return;

    this.element.querySelectorAll(".cc-slot-card.selected").forEach((el) => {
      el.classList.remove("selected");
      const input = el.querySelector('input[type="radio"]');
      if (input) input.checked = false;
    });

    card.classList.add("selected");
    const input = card.querySelector('input[type="radio"]');
    if (input) input.checked = true;

    this.state.selectedSlot = slotValue;

    if (this.hasSelectedSlotDisplayTarget && this.hasSelectedSlotTextTarget) {
      this.selectedSlotDisplayTarget.style.display = "flex";
      this.selectedSlotTextTarget.textContent = this.humanSlot(slotValue);
    }

    if (this.hasSlotDisplay3Target) {
      this.slotDisplay3Target.textContent = this.humanSlot(slotValue);
    }

    if (this.hasBtnToStep3Target) {
      this.btnToStep3Target.disabled = false;
    }

    this.renderSummary();
  }

  humanSlot(slotValue) {
    const [dateIso, time] = String(slotValue || "").split("|");
    if (!dateIso || !time) return slotValue;

    const parts = dateIso.split("-");
    if (parts.length === 3) {
      return `${parts[2]}/${parts[1]} - ${time}`;
    }

    return `${dateIso} - ${time}`;
  }

  renderCart() {
    const list = this.cartItemsListTarget;
    const empty = this.cartEmptyMsgTarget;

    [...list.children].forEach((c) => {
      if (c === empty) return;
      c.remove();
    });

    if (this.state.cart.length === 0) {
      empty.style.display = "block";
      if (this.hasBtnToStep2Target) this.btnToStep2Target.disabled = true;
      if (this.hasCartCountLabelTarget) this.cartCountLabelTarget.textContent = "";
      this.state.selectedSlot = "";
      if (this.hasBtnToStep3Target) this.btnToStep3Target.disabled = true;
      if (this.hasSelectedSlotDisplayTarget) this.selectedSlotDisplayTarget.style.display = "none";
      if (this.hasSlotDisplay3Target) this.slotDisplay3Target.textContent = "";
      this.element.querySelectorAll(".cc-slot-card.selected").forEach((el) => el.classList.remove("selected"));
      return;
    }

    empty.style.display = "none";
    if (this.hasBtnToStep2Target) this.btnToStep2Target.disabled = false;

    if (this.hasCartCountLabelTarget) {
      const c = this.state.count;
      this.cartCountLabelTarget.textContent = `(${c} article${c > 1 ? "s" : ""})`;
    }

    this.state.cart.forEach((item) => {
      list.insertBefore(this.buildCartItemEl(item), empty);
    });
  }

  buildCartItemEl(item) {
    const imgSrc = item.image ? `/uploads/products/${item.image}` : null;
    const subtotal = this.formatPrice(Number(item.price) * Number(item.quantity));

    const div = document.createElement("div");
    div.className = "cart-item";
    div.id = `item-${item.id}`;
    div.dataset.itemId = String(item.id);

    div.innerHTML = `
      <div class="cart-item-img">
        ${
          imgSrc
            ? `<img src="${imgSrc}" alt="${this.esc(item.name)}" loading="lazy">`
            : `<i class="fas fa-apple-alt"></i>`
        }
      </div>

      <div class="cart-item-info">
        <div class="cart-item-name">${this.esc(item.name)}</div>
        <div class="cart-item-price">${this.formatPrice(item.price)}</div>
      </div>

      <div class="cart-item-controls">
        <div class="qty-control">
          <button class="qty-btn" type="button" data-action="click->click-collect#minus" data-id="${item.id}" aria-label="Diminuer">
            <i class="fas fa-minus fa-xs"></i>
          </button>

          <input class="qty-input" type="number" min="0" value="${item.quantity}"
            data-action="input->click-collect#qtyInput blur->click-collect#qtyBlur"
            data-id="${item.id}"
            aria-label="Quantite ${this.esc(item.name)}"
          >

          <button class="qty-btn" type="button" data-action="click->click-collect#plus" data-id="${item.id}" aria-label="Augmenter">
            <i class="fas fa-plus fa-xs"></i>
          </button>
        </div>

        <span class="cart-item-subtotal" id="subtotal-${item.id}">${subtotal}</span>

        <button class="btn-remove-item" type="button" data-action="click->click-collect#remove" data-id="${item.id}" aria-label="Supprimer ${this.esc(item.name)}">
          <i class="fas fa-trash-can fa-xs"></i>
        </button>
      </div>
    `;

    return div;
  }

  renderSummary() {
    const html = this.state.cart
      .map(
        (i) => `
          <div class="summary-row">
            <span>${this.esc(i.name)} x${i.quantity}</span>
            <span>${this.formatPrice(Number(i.price) * Number(i.quantity))}</span>
          </div>`,
      )
      .join("");

    if (this.hasSummaryLines1Target) this.summaryLines1Target.innerHTML = html;
    if (this.hasSummaryLines2Target) this.summaryLines2Target.innerHTML = html;
    if (this.hasSummaryLines3Target) this.summaryLines3Target.innerHTML = html;
    if (this.hasSummaryLines4Target) this.summaryLines4Target.innerHTML = html;

    const total = this.formatPrice(this.state.total);
    if (this.hasSummaryTotal1Target) this.summaryTotal1Target.textContent = total;
    if (this.hasSummaryTotal2Target) this.summaryTotal2Target.textContent = total;
    if (this.hasSummaryTotal3Target) this.summaryTotal3Target.textContent = total;
    if (this.hasSummaryTotal4Target) this.summaryTotal4Target.textContent = total;

    if (this.hasBtnToStep3Target) this.btnToStep3Target.disabled = !this.state.selectedSlot;
  }

  plus(e) {
    const id = parseInt(e.currentTarget.dataset.id, 10);
    const input = this.findQtyInput(id);
    const current = parseInt(input?.value || "0", 10);
    const next = current + 1;
    if (input) input.value = String(next);
    this.queueQtyUpdate(id, next);
  }

  minus(e) {
    const id = parseInt(e.currentTarget.dataset.id, 10);
    const input = this.findQtyInput(id);
    const current = parseInt(input?.value || "0", 10);
    const next = Math.max(0, current - 1);
    if (input) input.value = String(next);
    this.queueQtyUpdate(id, next);
  }

  qtyInput(e) {
    const id = parseInt(e.currentTarget.dataset.id, 10);
    const qty = parseInt(e.currentTarget.value || "0", 10);
    this.queueQtyUpdate(id, qty);
  }

  qtyBlur(e) {
    const id = parseInt(e.currentTarget.dataset.id, 10);
    const qty = parseInt(e.currentTarget.value || "0", 10);
    this.flushQtyUpdate(id, qty);
  }

  queueQtyUpdate(id, qty) {
    clearTimeout(this.debounceTimers.get(id));
    this.debounceTimers.set(id, setTimeout(() => this.updateQty(id, qty), 250));
  }

  flushQtyUpdate(id, qty) {
    clearTimeout(this.debounceTimers.get(id));
    this.debounceTimers.delete(id);
    this.updateQty(id, qty);
  }

  remove(e) {
    const id = parseInt(e.currentTarget.dataset.id, 10);
    this.removeItem(id);
  }

  findQtyInput(id) {
    return this.element.querySelector(`#item-${id} .qty-input`);
  }

  formatPrice(amount) {
    const n = Number(amount || 0);
    return `${n.toFixed(2).replace(".", ",")} EUR`;
  }

  esc(str) {
    const d = document.createElement("div");
    d.appendChild(document.createTextNode(str ?? ""));
    return d.innerHTML;
  }

  updateCartBadge(count) {
    const badge = document.getElementById("cartNavBadge");
    if (!badge) return;

    if (count > 0) {
      badge.textContent = count > 99 ? "99+" : String(count);
      badge.style.display = "inline-flex";
      badge.classList.remove("bump");
      void badge.offsetWidth;
      badge.classList.add("bump");
    } else {
      badge.style.display = "none";
    }
  }

  async confirmOrder() {
    if (this.checkoutPending) return;

    try {
      this.checkoutPending = true;

      const payload = {
        firstName: this.state.customer?.firstName || "",
        lastName: this.state.customer?.lastName || "",
        phone: this.state.customer?.phone || "",
        email: this.state.customer?.email || "",
        notes: this.state.customer?.notes || "",
        slot: this.state.selectedSlot || "",
      };

      const res = await fetch(this.routesValue.checkout, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-Requested-With": "XMLHttpRequest",
        },
        body: JSON.stringify(payload),
      });

      const contentType = res.headers.get("content-type") || "";
      const text = await res.text();

      if (!contentType.includes("application/json")) {
        console.error("Expected JSON, got:", contentType, text);
        alert("Reponse non JSON du serveur.");
        return;
      }

      const data = JSON.parse(text);

      if (!res.ok || !data.success) {
        alert(data.message || `Erreur serveur (${res.status}).`);
        return;
      }

      const ref = data.orderNumber || data.orderRef || `NC-${Math.random().toString(36).slice(2, 8).toUpperCase()}`;

      if (this.hasOrderRefTarget) {
        this.orderRefTarget.textContent = ref;
      }

      const c = this.state.customer || {};
      const slotHuman = this.state.selectedSlot ? this.humanSlot(this.state.selectedSlot) : "-";

      if (this.hasConfirmDetailsTarget) {
        const phoneTxt = c.phone?.trim() ? ` - ${c.phone.trim()}` : "";
        this.confirmDetailsTarget.textContent = `${c.firstName || ""} ${c.lastName || ""}${phoneTxt} - ${slotHuman}`;
      }

      this.state.cart = [];
      this.state.total = 0;
      this.state.count = 0;
      this.renderCart();
      this.renderSummary();
      this.updateCartBadge(0);

      this.panelTargets.forEach((p) => p.classList.remove("is-active"));
      if (this.hasConfirmationScreenTarget) {
        this.confirmationScreenTarget.style.display = "block";
      }

      window.scrollTo({ top: 0, behavior: "smooth" });
    } catch (e) {
      console.error(e);
      alert("Erreur reseau.");
    } finally {
      this.checkoutPending = false;
    }
  }
}
