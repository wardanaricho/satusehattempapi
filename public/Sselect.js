/* Sselect.js — unified (single & multiple)
   Fitur:
   - Single / Multiple mode
   - Mapping data (dataField: { value:'code', label:'display' })
   - Hanya HIDDEN yang submit (display input tanpa name)
   - Allow create (Enter)
   - Prefill
   - onSelect / onChange callback
   - Emit event: sselect:select, sselect:change
*/

(function () {
    const DEFAULTS = {
        name: null,                 // required (atau ambil dari input@name)
        url: null,                  // required untuk remote
        multiple: false,
        params: {},
        searchParam: "q",
        valueField: "value",        // fallback jika TIDAK pakai dataField
        labelField: "label",        // fallback jika TIDAK pakai dataField
        placeholder: "Ketik untuk mencari...",
        minChars: 1,
        debounce: 200,
        dropdownMaxHeight: 280,
        dataField: null,            // { value: 'code', label: 'display' }
        transform: null,            // (data) => items
        prefill: null,              // {value,label,...} | [{...},...]
        allowCreate: false,
        onSelect: null,             // (item, api) =>
        onChange: null,             // (selected|item) =>
        emitEvents: true
    };

    const debounce = (fn, wait) => {
        let t;
        return (...args) => {
            clearTimeout(t);
            t = setTimeout(() => fn(...args), wait);
        };
    };

    const toQuery = obj =>
        Object.entries(obj || {})
            .map(([k, v]) => `${encodeURIComponent(k)}=${encodeURIComponent(v)}`)
            .join("&");

    class Sselect {
        constructor(selector, options = {}) {
            this.input = typeof selector === "string" ? document.querySelector(selector) : selector;
            if (!this.input) throw new Error("Sselect: element not found");

            this.opts = Object.assign({}, DEFAULTS, options);
            if (!this.opts.url) throw new Error("Sselect: 'url' is required");

            // simpan original name kalau ada (untuk fallback)
            const originalName = this.input.getAttribute("name");

            this.selected = [];  // multiple
            this._item = null;   // single
            this.isSelected = false;
            this.hasLoadedOnce = false;
            this.preventNextOpen = false;

            this.setupElements(originalName);
            this.attachEvents();

            if (this.opts.prefill) this.setPrefill(this.opts.prefill);
        }

        // mini API untuk dipakai di callback
        api() {
            return {
                el: this.input,
                wrapper: this.wrap,
                getSelected: () => (this.opts.multiple ? this.selected.slice() : this._item),
                setValue: (item) => this.opts.multiple ? this.addTag(item) : this.setSelection(item),
                clear: () => this.opts.multiple ? this.clearTags() : this.clearSelection(),
                open: () => this.open(),
                close: () => this.close()
            };
        }

        setupElements(originalName) {
            // wrapper
            this.wrap = document.createElement("div");
            this.wrap.className = "form-control d-flex flex-wrap align-items-center gap-1 position-relative";
            this.wrap.style.cursor = "text";
            this.wrap.style.minHeight = "38px";
            this.wrap.tabIndex = 0;

            this.input.parentNode.insertBefore(this.wrap, this.input);
            this.wrap.appendChild(this.input);

            // style input display
            this.input.classList.remove("form-control");
            this.input.style.border = "none";
            this.input.style.outline = "none";
            this.input.style.flex = "1";
            this.input.style.minWidth = "80px";
            if (this.opts.placeholder) this.input.placeholder = this.opts.placeholder;

            // FIX: pastikan input display TIDAK ikut submit
            this.input.removeAttribute("name");

            // hidden field (yg akan disubmit)
            this.hidden = document.createElement("input");
            this.hidden.type = "hidden";
            const finalName = this.opts.name || originalName || "sselect_value";
            this.hidden.name = this.opts.multiple
                ? (finalName.endsWith("[]") ? finalName : finalName + "[]")
                : finalName;
            this.wrap.appendChild(this.hidden);

            // dropdown
            this.menu = document.createElement("div");
            this.menu.className = "dropdown-menu show w-100 shadow";
            Object.assign(this.menu.style, {
                display: "none",
                maxHeight: this.opts.dropdownMaxHeight + "px",
                overflowY: "auto",
                zIndex: 1060
            });
            this.wrap.appendChild(this.menu);
        }

        attachEvents() {
            this.wrap.addEventListener("click", () => {
                if (!this.preventNextOpen) this.input.focus();
                this.preventNextOpen = false;
            });

            this.input.addEventListener("focus", () => {
                if (!this.preventNextOpen) this.onClick();
                this.preventNextOpen = false;
            });

            this.input.addEventListener("input", debounce(() => this.onInput(), this.opts.debounce));

            this.input.addEventListener("keydown", e => {
                // allow create via Enter
                if (e.key === "Enter" && this.opts.allowCreate && this.input.value.trim() !== "") {
                    e.preventDefault();
                    const val = this.input.value.trim();
                    const item = {
                        [this.opts.valueField]: val,
                        [this.opts.labelField]: val,
                        value: val,
                        label: val
                    };

                    if (this.opts.multiple) {
                        this.addTag(item);
                        this.input.value = "";
                    } else {
                        this.setSelection(item);
                        this.preventNextOpen = true;
                        this.close();
                        this.input.blur();
                    }
                    this._emitChange();
                    return;
                }

                if (this.opts.multiple) {
                    if (e.key === "Backspace" && this.input.value === "" && this.selected.length) {
                        e.preventDefault();
                        this.removeTag(this.selected.length - 1);
                    }
                } else {
                    if (e.key === "Backspace" && this.isSelected) {
                        e.preventDefault();
                        this.clearSelection();
                    }
                }
            });

            document.addEventListener("click", e => {
                if (!this.wrap.contains(e.target)) this.close();
            });
        }

        onClick() {
            if (!this.hasLoadedOnce) {
                this.fetch("");
                this.hasLoadedOnce = true;
            } else {
                this.open();
            }
        }

        onInput() {
            const val = this.input.value.trim();
            if (val === "") {
                this.menu.innerHTML = "";
                this.close();
                return;
            }
            if (val.length >= this.opts.minChars && !this.isSelected) {
                this.fetch(val);
            }
        }

        async fetch(term) {
            const params = Object.assign({}, this.opts.params, { [this.opts.searchParam]: term });
            const url = this.opts.url + (this.opts.url.includes("?") ? "&" : "?") + toQuery(params);

            try {
                const res = await fetch(url, {
                    headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" }
                });
                const data = await res.json();

                let items = [];
                if (this.opts.transform) {
                    items = this.opts.transform(data) || [];
                } else {
                    const list = data?.data || data || [];
                    items = list.map((d) => {
                        if (this.opts.dataField) {
                            const v = d[this.opts.dataField.value];
                            const l = d[this.opts.dataField.label];
                            return Object.assign({}, d, { value: v, label: l });
                        }
                        const v = d?.[this.opts.valueField] ?? d?.value ?? "";
                        const l = d?.[this.opts.labelField] ?? d?.label ?? String(v ?? "");
                        return Object.assign({}, d, { value: v, label: l });
                    });
                }

                this.render(items);
            } catch (err) {
                console.error("Sselect fetch error:", err);
                this.render([{ label: "Gagal memuat data", value: "", __error: true }]);
            }
        }

        render(items) {
            this.menu.innerHTML = "";
            if (!items.length) {
                const empty = document.createElement("div");
                empty.className = "dropdown-item text-muted";
                empty.textContent = "Tidak ada hasil";
                this.menu.appendChild(empty);
            }

            items.forEach(item => {
                const btn = document.createElement("button");
                btn.type = "button";
                btn.className = "dropdown-item text-wrap";
                btn.textContent = item.label ?? "";
                btn.style.borderRadius = "6px";
                // simpan item utuh
                btn._item = item;

                if (this.opts.multiple && this.selected.find(s => s.value == item.value)) {
                    btn.disabled = true;
                    btn.style.opacity = "0.6";
                }

                btn.addEventListener("click", e => {
                    e.preventDefault();
                    e.stopPropagation();

                    if (this.opts.multiple) {
                        this.addTag(btn._item);
                        this.input.value = "";
                        this.fetch(""); // refresh list untuk disabled item terpilih
                    } else {
                        this.setSelection(btn._item, btn);
                        this.preventNextOpen = true;
                        this.close();
                        this.input.blur();
                    }
                    this._emitChange();
                });
                this.menu.appendChild(btn);
            });

            this.open();
        }

        setSelection(item, btn) {
            this._item = item;
            this.hidden.value = item.value;
            this.input.value = item.label;
            this.isSelected = true;

            if (btn) {
                btn.style.background = "#0d6efd";
                btn.style.color = "#fff";
                btn.style.borderRadius = "6px";
            }

            if (typeof this.opts.onSelect === "function") this.opts.onSelect(item, this.api());
            if (this.opts.emitEvents) {
                this.input.dispatchEvent(new CustomEvent("sselect:select", { bubbles: true, detail: item }));
            }
        }

        clearSelection() {
            this._item = null;
            this.hidden.value = "";
            this.input.value = "";
            this.isSelected = true && false; // force reset
            this.close();
            this._emitChange();
        }

        addTag(item) {
            if (this.selected.find(s => s.value == item.value)) return;
            this.selected.push(item);

            const tag = document.createElement("span");
            tag.className = "badge bg-primary rounded-pill d-flex align-items-center";
            tag.style.gap = "4px";
            tag.innerHTML = `<span>${item.label}</span>
        <button type="button" class="btn-close btn-close-white p-0" style="font-size:10px"></button>`;
            tag.querySelector("button").addEventListener("click", () => {
                this.removeTag(this.selected.findIndex(s => s.value == item.value));
            });

            this.wrap.insertBefore(tag, this.input);
            this.updateHidden();
        }

        removeTag(index) {
            if (index < 0 || index >= this.selected.length) return;
            const tags = this.wrap.querySelectorAll(".badge");
            if (tags[index]) tags[index].remove();
            this.selected.splice(index, 1);
            this.updateHidden();
            this._emitChange();
        }

        clearTags() {
            this.selected = [];
            this.wrap.querySelectorAll(".badge").forEach(b => b.remove());
            this.updateHidden();
            this._emitChange();
        }

        updateHidden() {
            if (this.opts.multiple) {
                // hapus semua hidden bernama sama
                this.wrap.querySelectorAll(`input[name="${this.hidden.name}"]`).forEach(el => el.remove());
                // buat ulang
                this.selected.forEach(item => {
                    const h = document.createElement("input");
                    h.type = "hidden";
                    h.name = this.hidden.name;
                    h.value = item.value;
                    this.wrap.appendChild(h);
                });
            } else {
                // single sudah tersimpan di this.hidden.value
            }
        }

        open() {
            this.menu.style.display = "block";
            this.menu.style.position = "absolute";
            this.menu.style.top = this.wrap.offsetHeight + "px";
            this.menu.style.left = 0;
            this.menu.style.right = 0;
        }

        close() {
            this.menu.style.display = "none";
        }

        setPrefill(prefill) {
            if (this.opts.multiple) {
                (prefill || []).forEach(p => {
                    const v = p.value ?? p[this.opts.valueField];
                    const l = p.label ?? p[this.opts.labelField] ?? String(v ?? "");
                    this.addTag(Object.assign({}, p, { value: v, label: l }));
                });
            } else if (prefill && (prefill.value || prefill[this.opts.valueField])) {
                const v = prefill.value ?? prefill[this.opts.valueField];
                const l = prefill.label ?? prefill[this.opts.labelField] ?? String(v ?? "");
                this._item = Object.assign({}, prefill, { value: v, label: l });
                this.hidden.value = v;
                this.input.value = l;
                this.isSelected = true;
            }
        }

        _emitChange() {
            if (typeof this.opts.onChange === "function") {
                this.opts.onChange(this.opts.multiple ? this.selected : this._item, this.api());
            }
            if (this.opts.emitEvents) {
                this.input.dispatchEvent(new CustomEvent("sselect:change", {
                    bubbles: true,
                    detail: this.opts.multiple ? this.selected.slice() : this._item
                }));
            }
        }
    }

    window.Sselect = Sselect;
})();
