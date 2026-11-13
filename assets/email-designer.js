/* global ddwTwcEmail, wp */
(() => {
    'use strict';
    const root = document.getElementById('ddw-twc-email-editor');
    if (!root) return;
    const de = ddwTwcEmail.german;
    const t = (en, german) => de ? german : en;
    const api = (route = '', data) => wp.apiFetch({
        path: ddwTwcEmail.path + route,
        ...(data ? { method: 'POST', data } : {})
    });
    const node = (type, label = '', parent = root) => {
        const element = document.createElement(type);
        element.textContent = label;
        parent.append(element);
        return element;
    };
    const button = (label, parent, action, style = 'button') => {
        const element = node('button', label, parent);
        element.type = 'button';
        element.className = style;
        element.onclick = action;
        return element;
    };
    const disclosure = (label, parent) => {
        const details = node('details', '', parent);
        node('summary', label, details);
        return details;
    };
    let design, emails, email, position = 0, timer, sequence = 0, openPicker, busy = false;
    let edits = 0, published = false, colorRoots = [];
    let nativeBranding = {}, revision, realOrder = false, externalImages = false;
    const styleNames = {
        studio: 'Studio', journal: 'Journal', atelier: 'Atelier', signal: 'Signal', ledger: 'Ledger',
        postcard: t('Postcard', 'Postkarte'), letter: t('Letter', 'Brief'),
        parcel: t('Parcel', 'Paket'), gallery: t('Gallery', 'Galerie')
    };
    const brandingKeys = ['style', 'accent', 'canvas', 'paper', 'ink', 'frame', 'header_title', 'header_slogan', 'footer_title', 'footer_copyright'];
    for (const part of ['header', 'footer']) {
        for (const suffix of ['enabled', 'align', 'logo', 'logo_width', 'link', 'alt', 'text']) brandingKeys.push(part + '_' + suffix);
    }
    const branding = () => Object.fromEntries(brandingKeys.map(key => [key, design[key]]));
    const jsonClone = object => JSON.parse(JSON.stringify(object));
    let tagCatalog = {}, stylePage = 0, editorTab = 'template';
    const names = {
        shop: t('Shop', 'Shop'), wagen: t('Cart', 'Warenkorb'), kasse: t('Checkout', 'Kasse'),
        konto: t('My account', 'Mein Konto'), agb: t('Terms and conditions', 'AGB'),
        bestellungen: t('Orders', 'Bestellungen'), downloads: 'Downloads', adressen: t('Addresses', 'Adressen')
    };
    const tagGroups = [
        [t('Customer', 'Kunde'), [['kunde:vorname', t('First name', 'Vorname')], ['kunde:vollname', t('Full name', 'Vollständiger Name')]]],
        [t('Order', 'Bestellung'), [['order_number', t('Order number', 'Bestellnummer')], ['order_total', t('Order total', 'Gesamtbetrag')], ['country', t('Billing country', 'Rechnungsland')], ['bestelldatum:kurz', t('Order date · short', 'Bestelldatum · kurz')], ['bestelldatum:lang', t('Order date · long', 'Bestelldatum · lang')]]],
        [t('Store', 'Shop'), [['shop_name', t('Store title', 'Shop-Titel')], ['website_slogan', t('Website tagline', 'Website-Slogan')], ['year', t('Year', 'Jahr')]]],
        [t('Symbols', 'Zeichen'), [['copyright', '© Copyright'], ['registered', t('® Registered trademark', '® Registrierte Marke')]]]
    ];
    const escapeText = text => text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    const isOrderTag = id => /^(order_|country$|bestelldatum:)/.test(id);

    function field(parent, label, object, key, options = {}) {
        const { multiline = false, tags = false, order = false, placeholder = '', type = 'text' } = options;
        const box = node('div', '', parent);
        box.className = 'ddw-twc-email-field';
        const row = node('div', '', box);
        row.className = 'ddw-twc-email-label';
        const caption = node('label', label, row);
        const input = node(multiline ? 'textarea' : 'input', '', box);
        input.id = 'ddw-twc-mail-field-' + (++field.counter);
        caption.htmlFor = input.id;
        if (multiline) input.rows = 3;
        else input.type = type;
        input.value = object[key] || '';
        input.placeholder = placeholder;
        input.oninput = () => { object[key] = input.value; schedule(); };
        if (!tags) return input;
        const trigger = button('{···}', row, toggle);
        trigger.classList.add('ddw-twc-tag-trigger');
        trigger.setAttribute('aria-label', t('Insert data in ', 'Daten einfügen in ') + label);
        trigger.setAttribute('aria-expanded', 'false');
        const panel = node('div', '', box);
        panel.id = input.id + '-tags';
        trigger.setAttribute('aria-controls', panel.id);
        panel.className = 'ddw-twc-email-picker';
        panel.hidden = true;
        const search = node('input', '', panel);
        search.type = 'search';
        search.placeholder = t('Search tags', 'Tags durchsuchen');
        search.setAttribute('aria-label', search.placeholder);
        const results = node('div', '', panel);
        function close(focus = false) {
            panel.hidden = true;
            trigger.setAttribute('aria-expanded', 'false');
            openPicker = null;
            if (focus) trigger.focus();
        }
        let lookup = 0;
        let lookupTimer;
        function insert(id, label, link = false, fallback = '') {
            const tag = '{' + id + (fallback ? '|' + fallback : '') + '}';
            if (options.url) input.value = tag;
            else input.setRangeText(link ? '<a href="' + tag + '">' + escapeText(label) + '</a>' : tag, input.selectionStart, input.selectionEnd, 'end');
            input.oninput();
            close();
            input.focus();
        }
        function filter() {
            const query = search.value.trim().toLowerCase();
            const version = ++lookup;
            clearTimeout(lookupTimer);
            results.replaceChildren();
            if (!options.url) {
                for (const [group, tags] of tagGroups) {
                    const matches = tags.filter(([id, name]) =>
                        (order || !isOrderTag(id)) &&
                        (!options.brand || !id.startsWith('kunde:') && !isOrderTag(id)) &&
                        [group, id, name].some(value => value.toLowerCase().includes(query))
                    );
                    if (!matches.length) continue;
                    const list = disclosure(group, results);
                    list.open = !!query || results.children.length === 1;
                    let fallback;
                    if (matches.some(([id]) => id.startsWith('kunde:'))) {
                        fallback = node('input', '', list);
                        fallback.placeholder = t('Fallback (optional)', 'Ersatztext (optional)');
                        fallback.setAttribute('aria-label', fallback.placeholder);
                        fallback.maxLength = 120;
                    }
                    for (const [id, name] of matches) {
                        button(name + '  {' + id + '}', list, () => insert(id, name, false, fallback?.value.replace(/[{}|\r\n]/g, '').trim()));
                    }
                }
            }
            if (options.links || options.url) {
                const links = [
                    ...(tagCatalog.pages || []).map(key => ['wc-seite:' + key, names[key]]),
                    ...(tagCatalog.account || []).map(key => ['wc-konto:' + key, t('Account: ', 'Konto: ') + names[key]]),
                    ...(tagCatalog.imprint || []).map(page => ['seite-impressum:' + page.id, page.title]),
                    ...(tagCatalog.privacy ? [['seite-datenschutz:' + tagCatalog.privacy, t('Privacy policy', 'Datenschutz')]] : []),
                    ...(order ? [['bestellung:ansehen', t('View order', 'Bestellung ansehen')], ['bestellung:bezahlen', t('Pay for order', 'Bestellung bezahlen')]] : [])
                ];
                const known = links.filter(pair => pair.some(value => value.toLowerCase().includes(query)));
                if (known.length) {
                    const list = disclosure(t('Link targets', 'Linkziele'), results);
                    list.open = !!query || !!options.url;
                    for (const [id, label] of known) button(label, list, () => insert(id, label, true));
                }
                const pages = disclosure(t('Find a published page', 'Veröffentlichte Seite suchen'), results);
                pages.open = !!query;
                const pageResults = node('div', '', pages);
                if (query.length < 2) node('p', t('Enter at least two characters above.', 'Gib oben mindestens zwei Zeichen ein.'), pageResults);
                else lookupTimer = setTimeout(async () => {
                    try {
                        const found = await api('/pages?q=' + encodeURIComponent(query));
                        if (version !== lookup || panel.hidden) return;
                        for (const page of found) button(page.title, pageResults, () => insert('seite:' + page.id, page.title, true));
                        if (!found.length) node('p', t('No matching pages.', 'Keine passenden Seiten.'), pageResults);
                    } catch (error) {
                        if (version === lookup && !panel.hidden) node('p', error.message, pageResults);
                    }
                }, 250);
            }
            if (!results.children.length) node('p', t('No matching tags.', 'Keine passenden Tags.'), results);
        }
        function toggle() {
            if (!panel.hidden) { close(); return; }
            if (openPicker) openPicker();
            openPicker = close;
            panel.hidden = false;
            trigger.setAttribute('aria-expanded', 'true');
            search.value = '';
            filter();
            search.focus();
        }
        search.oninput = filter;
        box.addEventListener('keydown', event => {
            if (event.key === 'Escape' && !panel.hidden) { event.preventDefault(); close(true); }
        });
        return input;
    }
    field.counter = 0;
    document.addEventListener('click', event => {
        if (openPicker && !event.target.closest('.ddw-twc-email-field')) openPicker();
    });

    function check(parent, label, object, key) {
        const row = node('label', '', parent);
        const input = node('input', '', row);
        input.type = 'checkbox';
        input.checked = object[key] === true;
        row.append(label);
        input.onchange = () => { object[key] = input.checked; schedule(); };
        return input;
    }

    const alerts = node('div');
    const feedback = node('p');
    feedback.setAttribute('role', 'status');
    feedback.textContent = t('Loading email designer…', 'E-Mail-Designer wird geladen …');
    const bar = node('div');
    bar.className = 'ddw-twc-email-toolbar';
    const chooser = node('div', '', bar);
    chooser.className = 'ddw-twc-email-toolbar-group';
    const label = node('label', t('Email template to edit', 'E-Mail-Vorlage bearbeiten'), chooser);
    const select = node('select', '', chooser);
    select.id = 'tfwc-email-template';
    label.htmlFor = select.id;
    const actions = node('div', '', bar);
    actions.className = 'ddw-twc-email-toolbar-group';
    const save = button(t('Save draft', 'Entwurf speichern'), actions, () => perform('/draft'));
    const publish = button(t('Publish design', 'Design veröffentlichen'), actions, () => perform('/publish'), 'button button-primary');
    const test = button(t('Send test email', 'Testmail senden'), actions, () => perform('/test'));
    [select, save, publish, test].forEach(element => { element.disabled = true; });
    const stateBar = node('div');
    stateBar.className = 'ddw-twc-email-state';
    const baseName = node('strong', '', stateBar);
    const saveStatus = node('span', '', stateBar);
    saveStatus.setAttribute('role', 'status');
    const liveStatus = node('span', '', stateBar);
    const status = (label, state) => {
        saveStatus.textContent = label;
        saveStatus.dataset.state = state;
    };
    function showBase() {
        const name = design.library[design.selected]?.name;
        baseName.textContent = (name ? name + ' · ' : '') + t('Base style: ', 'Grundstil: ') + styleNames[design.style];
        liveStatus.textContent = published ? t('A version is live', 'Eine Fassung ist veröffentlicht') : t('No design published', 'Kein Design veröffentlicht');
        if (email) showDelivery();
    }
    const grid = node('div');
    grid.className = 'ddw-twc-email-grid';
    const settings = node('section', '', grid);
    const preview = node('section', '', grid);
    preview.className = 'ddw-twc-email-preview';
    node('h2', t('Live preview', 'Live-Vorschau'), preview);
    const delivery = disclosure(t('Delivery check', 'Versandcheck'), preview);
    delivery.className = 'ddw-twc-email-delivery';
    const deliverySummary = delivery.querySelector('summary');
    const deliveryBody = node('div', '', delivery);
    let deliveryConflicts = [];
    function showDelivery() {
        const active = email.enabled === true;
        const html = email.type === 'html';
        const draft = !!design.templates[email.id]?.enabled;
        const live = email.live === true;
        const state = !active ? t('WooCommerce email disabled', 'WooCommerce-Mail deaktiviert') :
            !html ? t('TfWC design requires the HTML email type', 'Das TfWC-Design setzt den E-Mail-Typ HTML voraus') :
            live ? t('TfWC design active', 'TfWC-Design aktiv') : t('WooCommerce design active', 'WooCommerce-Design aktiv');
        deliverySummary.textContent = t('Delivery check', 'Versandcheck') + ' · ' + state;
        deliveryBody.replaceChildren();
        const list = node('ul', '', deliveryBody);
        node('li', t('WooCommerce notification: ', 'WooCommerce-Benachrichtigung: ') +
            (active ? t('enabled', 'aktiviert') : t('disabled', 'deaktiviert')) + ' · ' + email.type, list);
        node('li', t('TfWC design: ', 'TfWC-Design: ') +
            (live ? t('enabled in published version', 'im veröffentlichten Stand aktiviert') : t('disabled in published version', 'im veröffentlichten Stand deaktiviert')) +
            ' · ' + (draft ? t('selected in draft', 'im Entwurf ausgewählt') : t('not selected in draft', 'im Entwurf nicht ausgewählt')), list);
        if (email.admin) node('li', t('Configured admin recipient: ', 'Konfigurierter Admin-Empfänger: ') +
            (email.recipient || t('none', 'kein Empfänger')), list);
        if (deliveryConflicts.length) node('li', t('Other active email designers: ', 'Weitere aktive E-Mail-Designer: ') + deliveryConflicts.join(', '), list);
        node('li', t('Test email goes to: ', 'Testmail geht an: ') + address, list);
        const actions = node('p', '', deliveryBody);
        if (email.settingsUrl) {
            const link = node('a', t('Open WooCommerce email settings', 'WooCommerce-Mail-Einstellungen öffnen'), actions);
            link.href = email.settingsUrl;
        }
    }
    const orderBar = node('div', '', preview);
    orderBar.className = 'ddw-twc-email-bar';
    const context = node('strong', '', orderBar);
    async function enablePreviewOption(key, trigger, update) {
        trigger.disabled = true;
        try {
            await api('/preview-option', { option: key });
            update();
            loadPreview();
        } catch (error) {
            feedback.textContent = error.message;
        } finally {
            trigger.disabled = false;
        }
    }
    const realOrderButton = button(t('Use order data', 'Bestelldaten verwenden'), orderBar, () =>
        enablePreviewOption('preview_real_orders', realOrderButton, () => { realOrder = true; position = 0; }), 'button-link');
    realOrderButton.hidden = true;
    const realOrderNote = node('small', t('Order data enabled', 'Bestelldaten aktiviert'), orderBar);
    realOrderNote.hidden = true;
    context.setAttribute('aria-live', 'polite');
    const newer = button('←', orderBar, () => { position--; loadPreview(); });
    const older = button('→', orderBar, () => { position++; loadPreview(); });
    newer.setAttribute('aria-label', t('Newer order', 'Neuere Bestellung'));
    older.setAttribute('aria-label', t('Older order', 'Ältere Bestellung'));
    newer.disabled = older.disabled = true;
    const subject = node('p', '', preview);
    const senderLine = node('div', '', preview);
    senderLine.className = 'ddw-twc-preview-sender';
    senderLine.hidden = true;
    const from = node('div', '', senderLine);
    const replyTo = node('div', '', senderLine);
    const senderSettings = node('a', t('WooCommerce sender settings', 'WooCommerce-Absendereinstellungen'), senderLine);

    const linkStatus = disclosure(t('Dynamic link targets', 'Dynamische Linkziele'), preview);
    linkStatus.hidden = true;
    const linkList = node('ul', '', linkStatus);
    const frame = node('iframe', '', preview);
    frame.setAttribute('sandbox', '');
    frame.title = t('Rendered email', 'Gerenderte E-Mail');
    const devices = node('div', '', preview);
    devices.className = 'ddw-twc-email-devices';
    devices.setAttribute('role', 'group');
    devices.setAttribute('aria-label', t('Preview device', 'Vorschaugerät'));
    const desktop = button(t('Desktop / laptop', 'Desktop / Laptop'), devices, () => device(false));
    const phone = button(t('Smartphone', 'Smartphone'), devices, () => device(true));
    preview.insertBefore(devices, frame);
    function device(mobile) {
        frame.classList.toggle('is-narrow', mobile);
        desktop.setAttribute('aria-pressed', !mobile);
        phone.setAttribute('aria-pressed', mobile);
    }
    device(false);
    node('p', t('Display can vary by mail app and device. Send a test email to check your inbox.', 'Die Darstellung kann je nach E-Mail-App und Gerät abweichen. Sende eine Testmail, um sie in deinem Postfach zu prüfen.'), preview).className = 'description';
    const expanded = node('dialog');
    expanded.className = 'ddw-twc-email-dialog ddw-twc-email-expanded';
    const expandedToolbar = node('div', '', expanded);
    expandedToolbar.className = 'ddw-twc-email-bar';
    const expandedTitle = node('h2', t('Live preview', 'Live-Vorschau'), expandedToolbar);
    expandedTitle.id = 'ddw-twc-email-expanded-title';
    expanded.setAttribute('aria-labelledby', expandedTitle.id);
    const closeExpanded = button(t('Close preview', 'Vorschau schließen'), expandedToolbar, () => expanded.close());
    const expandedFrame = node('iframe', '', expanded);
    expandedFrame.setAttribute('sandbox', '');
    expandedFrame.title = frame.title;
    const expand = button(t('Expand preview', 'Vorschau vergrößern'), devices, () => {
        expandedFrame.srcdoc = frame.srcdoc;
        expandedFrame.classList.toggle('is-narrow', frame.classList.contains('is-narrow'));
        expanded.showModal();
        closeExpanded.focus();
    });
    expand.setAttribute('aria-haspopup', 'dialog');
    expanded.addEventListener('close', () => {
        expandedFrame.removeAttribute('srcdoc');
        expand.focus();
    });
    const dialog = node('dialog');
    dialog.className = 'ddw-twc-email-dialog';
    const dialogTitle = node('h2', '', dialog);
    dialogTitle.id = 'ddw-twc-email-dialog-title';
    dialog.setAttribute('aria-labelledby', dialogTitle.id);
    const dialogActions = node('div', '', dialog);
    dialogActions.className = 'ddw-twc-email-bar';
    const take = button(t('Apply to draft', 'In Entwurf übernehmen'), dialogActions, () => {}, 'button button-primary');
    const cancel = button(t('Close', 'Schließen'), dialogActions, () => dialog.close());
    const dialogStatus = node('p', '', dialog);
    dialogStatus.setAttribute('role', 'status');
    const styleFrame = node('iframe', '', dialog);
    styleFrame.setAttribute('sandbox', '');
    styleFrame.tabIndex = -1;
    styleFrame.title = t('Complete style preview', 'Stilvorschau von Kopf bis Fuß');
    const images = button(t('Load external preview images', 'Externe Vorschaubilder laden'), preview, () =>
        enablePreviewOption('preview_external_images', images, () => { externalImages = true; }), 'button-link ddw-twc-preview-option');
    images.hidden = true;
    const imageNote = node('small', t('External preview images enabled', 'Externe Vorschaubilder aktiviert'), preview);
    imageNote.hidden = true;

    function hasExternalImages(html) {
        const markup = document.createElement('template');
        markup.innerHTML = html;
        const isExternal = value => {
            try {
                const url = new URL(value.trim(), window.location.href);
                return ['http:', 'https:'].includes(url.protocol) && url.origin !== window.location.origin;
            } catch { return false; }
        };
        for (const element of markup.content.querySelectorAll('img[src], [srcset], [background], [style], style')) {
            for (const attribute of ['src', 'background']) {
                const value = element.getAttribute(attribute);
                if (value && isExternal(value)) return true;
            }
            const candidates = (element.getAttribute('srcset') || '').match(/(?:https?:)?\/\/[^\s,]+/gi) || [];
            if (candidates.some(isExternal)) return true;
            const css = element.getAttribute('style') || (element.tagName === 'STYLE' ? element.textContent : '');
            if ([...css.matchAll(/url\(\s*['"]?([^'")]+)['"]?\s*\)/gi)].some(match => isExternal(match[1]))) return true;
        }
        return false;
    }
    function showFrame(target, html) {
        const sources = externalImages && target === frame ? 'https: http: data:' : window.location.origin + ' data:';
        const policy = '<meta http-equiv="Content-Security-Policy" content="default-src \'none\'; img-src ' + sources + '; style-src \'unsafe-inline\'">';
        target.srcdoc = html.replace('<head>', '<head>' + policy);
    }

    const browse = node('div', '', dialog);
    browse.className = 'ddw-twc-email-dialog-browse';
    dialog.insertBefore(browse, dialogStatus);
    const previousStyle = button('←', browse, () => turnStyle(-1));
    const browseCount = node('span', '', browse);
    browseCount.setAttribute('aria-live', 'polite');
    const nextStyle = button('→', browse, () => turnStyle(1));
    previousStyle.setAttribute('aria-label', t('Previous design', 'Vorheriges Design'));
    nextStyle.setAttribute('aria-label', t('Next design', 'Nächstes Design'));
    const swipeHint = node('small', t('Swipe here to browse', 'Hier wischen zum Blättern'), browse);
    let choices = [], choiceIndex = 0, styleRequest = 0, startTouch;
    function turnStyle(step) {
        if (choices.length < 2) return;
        choiceIndex = (choiceIndex + step + choices.length) % choices.length;
        renderStyle();
    }
    dialog.addEventListener('keydown', event => {
        if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
        event.preventDefault();
        turnStyle(event.key === 'ArrowLeft' ? -1 : 1);
    });
    browse.addEventListener('touchstart', event => {
        if (event.touches.length !== 1) { startTouch = null; return; }
        const touch = event.touches[0];
        startTouch = [touch.clientX, touch.clientY];
    }, { passive: true });
    browse.addEventListener('touchend', event => {
        if (!startTouch || !event.changedTouches.length) return;
        const touch = event.changedTouches[0];
        const dx = touch.clientX - startTouch[0], dy = touch.clientY - startTouch[1];
        startTouch = null;
        if (Math.abs(dx) >= 45 && Math.abs(dx) > Math.abs(dy) * 1.5) turnStyle(dx < 0 ? 1 : -1);
    }, { passive: true });
    browse.addEventListener('touchcancel', () => { startTouch = null; }, { passive: true });

    function previewStyle(style, trigger, id = '', ids = []) {
        choices = id ? ids.map(key => ({ style: design.library[key].branding.style, id: key })) :
            Object.keys(styleNames).map(key => ({ style: key, id: '' }));
        choiceIndex = Math.max(0, choices.findIndex(choice => id ? choice.id === id : choice.style === style));
        previousStyle.disabled = nextStyle.disabled = choices.length < 2;
        swipeHint.hidden = choices.length < 2;
        dialog.showModal();
        cancel.focus();
        dialog.onclose = () => { styleRequest++; if (trigger.isConnected) trigger.focus(); };
        renderStyle();
    }

    async function renderStyle() {
        const { style, id } = choices[choiceIndex];
        const entry = design.library[id];
        const candidate = entry ? jsonClone(entry.branding) : { ...branding(), style, accent: '', canvas: '', paper: '', ink: '' };
        const sample = { ...candidate, templates: {} };
        const request = ++styleRequest;
        dialogTitle.textContent = entry ? entry.name + ' · ' + styleNames[candidate.style] : styleNames[style];
        browseCount.textContent = (choiceIndex + 1) + ' / ' + choices.length;
        dialogStatus.textContent = t('Loading sample email…', 'Beispielmail wird geladen …');
        styleFrame.srcdoc = '';
        take.disabled = true;
        try {
            const result = await api('/style-preview', { design: sample });
            if (!dialog.open || request !== styleRequest) return;
            showFrame(styleFrame, result.html);
            dialogStatus.textContent = t('Same sample order for every style.', 'Dieselbe Beispielbestellung für jeden Stil.');
            take.disabled = false;
            take.onclick = () => {
                Object.assign(design, candidate);
                design.selected = id;
                if (!id) stylePage = Math.floor(Object.keys(styleNames).indexOf(style) / 9);
                dialog.close();
                edit();
                const next = settings.querySelector('[aria-pressed=true]');
                if (next) next.focus();
                schedule();
            };
        } catch (error) {
            if (request === styleRequest) dialogStatus.textContent = error.message;
        }
    }

    function colorField(parent, key, label, fallback) {
        const group = disclosure('', parent);
        group.className = 'ddw-twc-color-control';
        group.name = 'ddw-twc-color';
        const summary = group.firstElementChild;
        const chip = node('span', '', summary);
        chip.className = 'ddw-twc-color-chip';
        chip.setAttribute('aria-hidden', 'true');
        node('span', label, summary);
        const hex = node('code', '', summary);
        const pickerHost = node('div', '', group);
        pickerHost.className = 'ddw-twc-color-host';
        const react = wp.element;
        let mounted;
        const updateSummary = () => {
            const color = design[key] || fallback;
            chip.style.backgroundColor = color;
            hex.textContent = color.toUpperCase();
        };
        const draw = () => {
            updateSummary();
            if (!mounted) return;
            mounted.render(react.createElement(wp.components.ColorPicker, {
                className: 'ddw-twc-wp-picker',
                color: design[key] || fallback,
                enableAlpha: false,
                onChange: value => { design[key] = value; updateSummary(); schedule(); }
            }));
        };
        group.addEventListener('toggle', () => {
            if (!group.open || mounted) return;
            mounted = react.createRoot(pickerHost);
            colorRoots.push(mounted);
            draw();
        });
        updateSummary();
        button(t('Use style color', 'Stilfarbe verwenden'), group, () => {
            design[key] = '';
            draw();
            schedule();
        }).classList.add('ddw-twc-color-reset');
        return draw;
    }

    function mediaField(parent, part) {
        const key = part + '_logo';
        const input = field(parent, t('Image URL (optional)', 'Bild-URL (optional)'), design, key, { type: 'url' });
        const toolbar = node('div', '', parent);
        toolbar.className = 'ddw-twc-email-bar';
        button(t('Choose from Media Library', 'Aus Mediathek wählen'), toolbar, () => {
            const media = wp.media({
                title: t('Choose a logo', 'Logo auswählen'),
                library: { type: 'image' },
                button: { text: t('Use logo', 'Logo übernehmen') },
                multiple: false
            });
            media.on('select', () => {
                const image = media.state().get('selection').first().toJSON();
                input.value = image.url;
                input.oninput();
                const alt = parent.querySelector('[data-brand-alt]');
                if (alt && !alt.value && image.alt) { alt.value = image.alt; alt.oninput(); }
            });
            media.open();
        });
        button(t('Remove logo', 'Logo entfernen'), toolbar, () => { input.value = ''; input.oninput(); });
    }

    function ownDesigns(parent) {
        const section = disclosure(t('My designs', 'Eigene Designs'), parent);
        section.open = Boolean(design.selected);
        const filters = node('div', '', section);
        filters.className = 'ddw-twc-email-bar';
        const search = node('input', '', filters);
        search.type = 'search';
        search.placeholder = t('Search designs', 'Designs suchen');
        search.setAttribute('aria-label', search.placeholder);
        const filter = node('select', '', filters);
        filter.setAttribute('aria-label', t('Filter by base style', 'Nach Grundstil filtern'));
        node('option', t('All base styles', 'Alle Grundstile'), filter).value = '';
        for (const [id, label] of Object.entries(styleNames)) node('option', label, filter).value = id;
        const list = node('div', '', section);
        const pages = node('div', '', section);
        let page = 0;
        const now = () => Math.floor(Date.now() / 1000);
        const newId = () => 'design-' + Array.from(crypto.getRandomValues(new Uint32Array(4)), part => part.toString(16)).join('-');
        function persist() { schedule(); perform('/draft'); }
        function canAdd() {
            if (Object.keys(design.library).length < 30) return true;
            feedback.textContent = t('The library is limited to 30 designs.', 'Die Bibliothek ist auf 30 Designs begrenzt.');
            return false;
        }
        function display() {
            const query = search.value.trim().toLocaleLowerCase();
            const entries = Object.entries(design.library)
                .filter(([, entry]) => (!filter.value || entry.branding.style === filter.value) && entry.name.toLocaleLowerCase().includes(query))
                .sort(([, a], [, b]) => (b.updated || 0) - (a.updated || 0) || a.name.localeCompare(b.name));
            const count = Math.max(1, Math.ceil(entries.length / 6));
            page = Math.min(page, count - 1);
            list.replaceChildren();
            if (!entries.length) node('p', t('No designs found.', 'Keine Designs gefunden.'), list);
            for (const [id, entry] of entries.slice(page * 6, (page + 1) * 6)) {
                const row = node('div', '', list);
                row.className = 'ddw-twc-email-library-row';
                const choice = button(entry.name, row, () => previewStyle(entry.branding.style, choice, id, entries.map(([key]) => key)), 'button-link');
                const date = entry.updated ? new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(entry.updated * 1000)) : '—';
                node('small', styleNames[entry.branding.style] + ' · ' + date, row);
                const actions = disclosure(t('Actions', 'Aktionen'), row);
                button(t('Rename', 'Umbenennen'), actions, () => {
                    const label = window.prompt(t('Design name', 'Designname'), entry.name)?.trim().slice(0, 100);
                    if (!label || label === entry.name || busy) return;
                    entry.name = label;
                    entry.updated = now();
                    display();
                    persist();
                });
                button(t('Duplicate', 'Duplizieren'), actions, () => {
                    if (busy || !canAdd()) return;
                    const name = entry.name.slice(0, 88) + t(' (copy)', ' (Kopie)');
                    design.library[newId()] = { name, branding: jsonClone(entry.branding), updated: now() };
                    page = 0;
                    display();
                    persist();
                });
                button(t('Delete', 'Löschen'), actions, () => {
                    if (busy || !window.confirm(t('Remove this saved design?', 'Dieses gespeicherte Design entfernen?'))) return;
                    delete design.library[id];
                    if (design.selected === id) design.selected = '';
                    edit();
                    persist();
                });
            }
            paginate(pages, page, count, next => { page = next; display(); });
        }
        search.oninput = filter.onchange = () => { page = 0; display(); };
        display();
        const name = field(section, t('Name for this design', 'Name für dieses Design'), {}, 'name');
        name.maxLength = 100;
        name.oninput = null;
        button(t('Save as new design', 'Als neues Design speichern'), section, () => {
            const label = name.value.trim();
            if (!label) { name.focus(); return; }
            if (busy || !canAdd()) return;
            const id = newId();
            design.library[id] = { name: label, branding: branding(), updated: now() };
            design.selected = id;
            edit();
            persist();
        });
        if (design.selected) {
            button(t('Update selected design', 'Gewähltes Design aktualisieren'), section, () => {
                if (busy) return;
                design.library[design.selected].branding = branding();
                design.library[design.selected].updated = now();
                display();
                persist();
            });
        }
    }

    function paginate(container, page, count, change) {
        container.replaceChildren();
        container.className = 'ddw-twc-email-bar';
        container.hidden = count <= 1;
        if (count <= 1) return;
        const go = next => {
            change(next);
            (container.querySelector('button:not(:disabled)') || container).focus();
        };
        button(t('Previous', 'Zurück'), container, () => go(page - 1)).disabled = page === 0;
        node('span', t('Page ', 'Seite ') + (page + 1) + t(' of ', ' von ') + count, container).setAttribute('aria-live', 'polite');
        button(t('Next', 'Weiter'), container, () => go(page + 1)).disabled = page + 1 >= count;
    }

    let nativeText, address, activation, refreshCoverage;

    let migrationSources, importedLive, migrationPanel;

    function loadTransferDraft(candidate) {
        design = JSON.parse(JSON.stringify(candidate));
        if (Array.isArray(design.templates)) design.templates = {};
        if (Array.isArray(design.library)) design.library = {};
        for (const template of Object.values(design.templates)) {
            template.copy = Object.assign(Object.create(null), template.copy);
        }
        edit();
        schedule();
    }

    function migrationHelp(parent) {
        if (migrationPanel) { parent.append(migrationPanel); return; }
        migrationPanel = disclosure(t('Transfer from another designer', 'Übernahme aus einem anderen Designer'), parent);
        const panel = migrationPanel;
        panel.id = 'tfwc-email-migration';
        node('p', t('Review the source texts, select a base style and check the result in the live preview. Before publishing, send a test email and deactivate the previous designer.', 'Prüfe die Quelltexte, wähle einen Grundstil und kontrolliere das Ergebnis in der Live-Vorschau. Sende vor dem Veröffentlichen eine Testmail und deaktiviere den bisherigen Designer.'), panel);
        const sources = Object.entries(migrationSources?.options || {});
        if (!sources.length) {
            node('p', t('For other designers, open their settings and copy custom content into the corresponding template fields. Use the Dynamic Tags picker to replace old placeholders.', 'Öffne bei anderen Designern deren Einstellungen und kopiere individuelle Inhalte in die passenden Vorlagenfelder. Ersetze alte Platzhalter über die Auswahl für dynamische Daten.'), panel);
            return;
        }

        const sourceLabel = node('label', 'cxThemes · ', panel);
        const source = node('select', '', sourceLabel);
        if (!migrationSources.preferred && sources.length > 1) {
            const placeholder = node('option', t('Select source design', 'Quelldesign auswählen'), source);
            placeholder.value = '';
        }
        for (const [key, name] of sources) { node('option', name, source).value = key; }
        if (migrationSources.preferred) {
            source.value = migrationSources.preferred;
            node('p', t('Previously used design: ', 'Zuletzt verwendetes Design: ') + migrationSources.options[migrationSources.preferred], panel);
        }
        const report = node('div', '', panel);
        const fields = {
            subject: t('Subject', 'Betreff'),
            heading: t('Heading', 'Überschrift'),
            main_text: t('Main text', 'Haupttext'),
            heading_pending: t('Heading for pending payment', 'Überschrift bei ausstehender Zahlung'),
            main_text_pending: t('Text for pending payment', 'Text bei ausstehender Zahlung'),
            main_text_generate_pass: t('Text for generated password', 'Text zum erzeugten Passwort'),
            header_logo: t('Header logo', 'Logo im Kopfbereich'),
            footer_text: t('Footer text', 'Fußzeilentext')
        };
        button(t('Review cxThemes content', 'cxThemes-Inhalte prüfen'), panel, async () => {
            if (!source.value) return;
            report.textContent = t('Reading source settings…', 'Quelle wird gelesen …');
            const requested = source.value;
            try {
                const result = await api('/migration?source=' + encodeURIComponent(requested));
                if (requested !== source.value) return;
                report.replaceChildren();
                const rows = Object.entries(result.rows || {});
                const selections = new Map();
                function include(parent, text, selected) {
                    const label = node('label', '', node('p', '', parent));
                    const checkbox = node('input', '', label);
                    checkbox.type = 'checkbox';
                    checkbox.checked = selected;
                    label.append(' ' + text);
                    return checkbox;
                }
                function original(parent, key, value) {
                    node('strong', fields[key] || key, parent);
                    const text = node('p', value, parent);
                    text.style.whiteSpace = 'pre-wrap';
                }
                const branding = Object.entries(result.branding || {});
                const brandingReview = Object.entries(result.manualBranding || {});
                let useBranding;
                if (branding.length || brandingReview.length) {
                    const section = disclosure('Branding', report);
                    for (const [key, value] of branding) original(section, key, value);
                    for (const [key, value] of brandingReview) original(section, key, value);
                    if (brandingReview.length) node('p', t('Some branding fields require manual review.', 'Einige Branding-Felder müssen manuell geprüft werden.'), section);
                    if (branding.length) useBranding = include(section, t('Include supported branding', 'Unterstütztes Branding übernehmen'), false);
                }
                for (const [id, row] of rows) {
                    const section = disclosure(row.title, report);
                    const item = emails.find(email => email.id === id);
                    const canImport = item?.type === 'html' && Object.keys(row.fields).length > 0;
                    if (canImport) {
                        const checkbox = include(section, t('Include this email', 'Diese E-Mail übernehmen'), true);
                        selections.set(id, checkbox);
                    }
                    for (const [key, value] of Object.entries(row.fields)) {
                        if (key === 'copy') continue;
                        original(section, key, value);
                    }
                    for (const [key, value] of Object.entries(row.review || {})) {
                        original(section, key, value);
                        node('p', t('Check placement, formatting and native links in the preview.', 'Prüfe Position, Formatierung und native Links in der Vorschau.'), section);
                    }
                    for (const [key, value] of Object.entries(row.manual)) {
                        node('p', t('Manual review required', 'Manuelle Prüfung erforderlich'), section);
                        original(section, key, value);
                    }
                    if (!canImport && Object.keys(row.fields).length) node('p', t('This template must be available in HTML to apply these fields.', 'Für die Übernahme muss diese Vorlage als HTML verfügbar sein.'), section);
                    if (item) button(t('Open template', 'Vorlage öffnen'), section, () => { editorTab = 'template'; select.value = id; select.onchange(); });
                }
                if (!selections.size && !useBranding) {
                    node('p', t('No supported fields can be applied automatically.', 'Es können keine unterstützten Felder automatisch übernommen werden.'), report);
                    return;
                }
                const styleLabel = node('label', t('Base style', 'Grundstil') + ' ', report);
                const base = node('select', '', styleLabel);
                for (const [key, name] of Object.entries(styleNames)) { node('option', name, base).value = key; }
                base.value = design.style;
                node('p', t('Selected fields replace matching fields in your draft. The active design and the source settings stay in place.', 'Ausgewählte Felder ersetzen die entsprechenden Felder im Entwurf. Der aktive Versandstand und die Quelle bleiben erhalten.'), report);
                button(t('Apply to draft', 'In Entwurf übernehmen'), report, () => {
                    if (busy || source.value !== requested) return;
                    const selected = rows.filter(([id]) => selections.get(id)?.checked);
                    if (!selected.length && !useBranding?.checked) return;
                    if (!window.confirm(t('Apply these fields to the current draft?', 'Diese Felder in den aktuellen Entwurf übernehmen?'))) return;
                    const candidate = JSON.parse(JSON.stringify(design));
                    if (Array.isArray(candidate.templates)) candidate.templates = {};
                    candidate.style = base.value;
                    if (useBranding?.checked) Object.assign(candidate, result.branding);
                    for (const [id, row] of selected) {
                        const previous = candidate.templates[id] || {};
                        candidate.templates[id] = { ...previous, ...row.fields, enabled: true, copy: { ...previous.copy, ...row.fields.copy } };
                    }
                    if (selected.length) {
                        editorTab = 'template';
                        select.value = selected[0][0];
                        email = emails.find(item => item.id === select.value);
                        position = 0;
                    }
                    loadTransferDraft(candidate);
                    void perform('/draft');
                });
            } catch (error) {
                report.textContent = error.message || t('Could not read the source settings.', 'Die Quelle konnte nicht gelesen werden.');
            }
        });
        source.addEventListener('change', () => report.replaceChildren());
    }

    function edit() {
        if (openPicker) openPicker();
        for (const mounted of colorRoots) mounted.unmount();
        colorRoots = [];
        settings.replaceChildren();
        showBase();
        const tabs = node('div', '', settings);
        tabs.className = 'ddw-twc-editor-tabs';
        tabs.setAttribute('role', 'tablist');
        tabs.setAttribute('aria-label', t('Editor sections', 'Editor-Bereiche'));
        const sections = [
            ['template', t('Selected email template', 'Gewählte E-Mail-Vorlage')],
            ['design', 'Design'],
            ['branding', 'Branding']
        ].map(([id, label]) => {
            const tab = button(label, tabs, () => selectTab(id));
            tab.id = 'ddw-twc-tab-' + id;
            tab.setAttribute('role', 'tab');
            const panel = node('div', '', settings);
            panel.id = 'ddw-twc-panel-' + id;
            panel.className = 'ddw-twc-editor-panel';
            panel.setAttribute('role', 'tabpanel');
            panel.setAttribute('aria-labelledby', tab.id);
            tab.setAttribute('aria-controls', panel.id);
            return { id, tab, panel };
        });
        function selectTab(id, focus = false) {
            if (openPicker) openPicker();
            editorTab = id;
            for (const section of sections) {
                const selected = section.id === id;
                section.tab.setAttribute('aria-selected', selected);
                section.tab.tabIndex = selected ? 0 : -1;
                section.panel.hidden = !selected;
                if (selected && focus) section.tab.focus();
            }
        }
        tabs.onkeydown = event => {
            const index = sections.findIndex(section => section.tab === event.target);
            if (index < 0) return;
            const step = event.key === 'ArrowRight' ? 1 : event.key === 'ArrowLeft' ? -1 : 0;
            if (!step && event.key !== 'Home' && event.key !== 'End') return;
            event.preventDefault();
            const next = step ? (index + step + sections.length) % sections.length : event.key === 'Home' ? 0 : sections.length - 1;
            selectTab(sections[next].id, true);
        };
        selectTab(editorTab);
        const [templatePanel, styleGroup, brandPanel] = sections.map(section => section.panel);
        const brandSection = label => {
            const section = disclosure(label, brandPanel);
            section.name = 'ddw-twc-branding';
            section.className = 'ddw-twc-brand-section';
            return section;
        };
        node('h2', t('Base styles', 'Grundstile'), styleGroup);
        const styleNotes = {
            studio: t('Dark masthead', 'Dunkler Markenheader'),
            journal: t('Editorial typography', 'Redaktioneller Satz'),
            atelier: t('Inset message panel', 'Grüne Statuskarte'),
            signal: t('Full-width status', 'Status auf Farbfläche'),
            ledger: t('Classic receipt grid', 'Klassisches Beleg­raster'),
            postcard: t('Centered greeting', 'Persönlicher Gruß'),
            letter: t('Letterhead and signature', 'Briefkopf und Signatur'),
            parcel: t('Order status panel', 'Paketstatus im Fokus'),
            gallery: t('Product cards', 'Produktkarten')
        };
        const styles = node('div', '', styleGroup);
        styles.className = 'ddw-twc-email-styles';
        const stylePages = node('div', '', styleGroup);
        const entries = Object.entries(styleNames);
        const count = Math.ceil(entries.length / 9);
        const drawStyles = () => {
            styles.replaceChildren();
            for (const [style, label] of entries.slice(stylePage * 9, (stylePage + 1) * 9)) {
                const choice = button(label, styles, () => previewStyle(style, choice));
                node('small', styleNotes[style] || '', choice);
                choice.dataset.style = style;
                choice.setAttribute('aria-pressed', design.style === style);
                choice.setAttribute('aria-haspopup', 'dialog');
            }
            paginate(stylePages, stylePage, count, next => { stylePage = next; drawStyles(); });
        };
        drawStyles();
        ownDesigns(styleGroup);
        migrationHelp(styleGroup);
        const coverage = node('section', '', styleGroup);
        coverage.className = 'ddw-twc-email-coverage';
        node('h2', t('Apply shared design', 'Gemeinsames Design anwenden'), coverage);
        const summary = node('p', '', coverage);
        summary.setAttribute('role', 'status');
        const compatible = emails.filter(item => item.type === 'html');
        const excluded = emails.filter(item => item.type !== 'html');
        if (excluded.length) {
            const list = disclosure(t('Excluded due to email format', 'Wegen E-Mail-Format ausgenommen') + ' (' + excluded.length + ')', coverage);
            const items = node('ul', '', list);
            for (const item of excluded) {
                const format = item.type === 'plain' ? t('Plain text', 'Reintext') : item.type;
                node('li', item.title + ' · ' + format, items);
            }
        }
        node('p', t('This changes the draft. Individual email text stays in place. Publish when ready.', 'Die Auswahl gilt zunächst im Entwurf. Individuelle E-Mail-Texte bleiben erhalten. Veröffentliche das Design, wenn alles passt.'), coverage).className = 'description';
        const applyAll = button(t('Apply to all available HTML templates', 'Auf alle verfügbaren HTML-Vorlagen anwenden'), coverage, () => {
            for (const item of compatible) {
                const settings = design.templates[item.id] ||= {};
                settings.enabled = true;
            }
            if (email.type === 'html') activation.checked = true;
            schedule();
        });
        refreshCoverage = () => {
            const enabled = compatible.filter(item => design.templates[item.id]?.enabled).length;
            summary.textContent = t('HTML templates selected in draft: ', 'HTML-Vorlagen im Entwurf ausgewählt: ') + enabled + ' / ' + compatible.length;
            applyAll.disabled = enabled === compatible.length;
        };
        refreshCoverage();

        const colorLabels = {
            accent: t('Accent', 'Akzentfarbe'),
            canvas: t('Background around email', 'Hintergrund um die E-Mail'),
            paper: t('Content background', 'Hintergrund für Inhalt'),
            ink: t('Heading and text', 'Überschrift und Text')
        };
        const importLabels = {
            header_logo: t('Header logo', 'Logo im Kopfbereich'),
            header_logo_width: t('Header logo width', 'Logobreite im Kopfbereich'),
            header_align: t('Header alignment', 'Ausrichtung des Kopfbereichs'),
            footer_text: t('Footer text', 'Fußzeilentext'),
            ...colorLabels
        };
        const options = Object.entries(nativeBranding).filter(([key]) => importLabels[key]);
        if (options.length) {
            const transfer = brandSection(t('Import from WooCommerce', 'Aus WooCommerce übernehmen'));
            node('p', t('Select what to copy to this draft, then review it in the live preview.', 'Wähle die Werte für diesen Entwurf und prüfe sie anschließend in der Live-Vorschau.'), transfer);
            const choices = new Map();
            const fields = node('ul', '', transfer);
            for (const [key, value] of options) {
                const row = node('li', '', fields);
                const label = node('label', '', row);
                const choice = node('input', '', label);
                choice.type = 'checkbox';
                choice.checked = true;
                node('span', ' ' + importLabels[key], label);
                const alignments = { start: t('Start', 'Textanfang'), center: t('Centered', 'Zentriert'), end: t('End', 'Textende') };
                node('small', key === 'header_align' ? alignments[value] : String(value), row);
                choices.set(key, choice);
            }
            const apply = button(t('Copy selection to draft', 'Auswahl in Entwurf übernehmen'), transfer, () => {
                const picked = options.filter(([key]) => choices.get(key).checked);
                if (!picked.length) return;
                Object.assign(design, Object.fromEntries(picked));
                if (picked.some(([key]) => key === 'header_logo')) design.header_enabled = true;
                if (picked.some(([key]) => key === 'footer_text')) design.footer_enabled = true;
                edit();
                schedule();
                document.getElementById('ddw-twc-tab-branding')?.focus();
            });
            transfer.classList.add('ddw-twc-native-branding');
            for (const choice of choices.values()) {
                choice.onchange = () => { apply.disabled = ![...choices.values()].some(item => item.checked); };
            }
        }
        const colors = brandSection(t('Brand colors', 'Markenfarben'));
        const defaults = ddwTwcEmail.palettes?.[design.style] || ['#33412a', '#e9e9e5', '#ffffff', '#20221d'];
        const controls = Object.entries(colorLabels).map(([key, label], index) => colorField(colors, key, label, defaults[index]));
        button(t('Reset palette to base style', 'Palette auf Grundstil zurücksetzen'), colors, () => {
            for (const key of Object.keys(colorLabels)) design[key] = '';
            for (const redraw of controls) redraw();
            schedule();
        });
        const framing = brandSection(t('Email frame', 'E-Mail-Rahmen'));
        const surround = node('select', '', framing);
        surround.setAttribute('aria-label', t('Email frame', 'E-Mail-Rahmen'));
        for (const [value, label] of [['', t('Use base style', 'Wie im Grundstil')], ['outline', t('Subtle outline', 'Dezenter Rahmen')], ['card', t('Soft card', 'Weiche Karte')]]) {
            node('option', label, surround).value = value;
        }
        surround.value = design.frame || '';
        surround.onchange = () => { design.frame = surround.value; schedule(); };
        for (const part of ['header', 'footer']) {
            const title = part === 'header' ? t('Header', 'Kopfbereich') : t('Footer', 'Fußbereich');
            const section = brandSection(title);
            check(section, t('Show section', 'Bereich anzeigen'), design, part + '_enabled');
            field(section, t('Title', 'Titel'), design, part + '_title', { tags: true, brand: true });
            if (part === 'header') field(section, t('Tagline', 'Slogan'), design, 'header_slogan', { tags: true, brand: true });
            const align = node('select', '', section);
            align.setAttribute('aria-label', title + ' · ' + t('Alignment', 'Ausrichtung'));
            for (const [value, text] of [['', t('Style default', 'Ausrichtung des Stils')], ['start', t('Align to start', 'Am Textanfang ausrichten')], ['center', t('Center', 'Zentrieren')], ['end', t('Align to end', 'Am Textende ausrichten')]]) {
                node('option', text, align).value = value;
            }
            align.value = design[part + '_align'] || '';
            align.onchange = () => { design[part + '_align'] = align.value; schedule(); };
            mediaField(section, part);
            const width = field(section, t('Logo width (px)', 'Logobreite (px)'), design, part + '_logo_width', { type: 'number', placeholder: '140' });
            width.min = '40';
            width.max = '400';
            width.step = '1';
            field(section, t('Logo link', 'Logo-Link'), design, part + '_link', { tags: true, url: true });
            field(section, t('Logo alternative text', 'Alternativtext des Logos'), design, part + '_alt').dataset.brandAlt = '1';
            field(section, t('Notes and links (basic HTML)', 'Hinweise und Links (einfaches HTML)'), design, part + '_text', { multiline: true, tags: true, brand: true, links: true });
            if (part === 'footer') field(section, t('Copyright line', 'Copyright-Zeile'), design, 'footer_copyright', { tags: true, brand: true, placeholder: '© {year} {shop_name}' });
        }
        const template = design.templates[email.id] ||= { enabled: false, copy: {} };
        const titleBlock = node('header', '', templatePanel);
        titleBlock.className = 'ddw-twc-email-template-title';
        node('span', t('Email template name', 'Name der E-Mail-Vorlage'), titleBlock);
        node('h2', email.title, titleBlock);
        activation = check(templatePanel, t('Use the shared design for this template', 'Gemeinsames Design für diese Vorlage verwenden'), template, 'enabled');
        if (email.type !== 'html') {
            activation.disabled = true;
            node('p', t('Set this email to HTML in WooCommerce to use the design.', 'Stelle diese E-Mail in WooCommerce auf HTML um, um das Design zu verwenden.'), templatePanel);
        }
        field(templatePanel, t('Subject', 'Betreff'), template, 'subject', { tags: true, order: email.order, placeholder: t('WooCommerce default', 'WooCommerce-Standard') });
        field(templatePanel, t('Heading inside the email', 'Überschrift in der E-Mail'), template, 'heading', { tags: true, order: email.order, placeholder: t('WooCommerce default', 'WooCommerce-Standard') });
        field(templatePanel, t('Additional text', 'Zusätzlicher Text'), template, 'extra', { tags: true, order: email.order, multiline: true, links: true });
        nativeText = disclosure(t('WooCommerce template text', 'WooCommerce-Vorlagentexte'), templatePanel);
        node('p', t('Leave fields empty to keep the default. Keep format markers such as %s.', 'Leere Felder verwenden den Standardtext. Formatmarker wie %s bleiben erhalten.'), nativeText);
        nativeText.append(document.createElement('div'));
        nativeText.seen = new Set();
    }

    function payload() { return { email: email.id, position, design, revision, realOrder }; }

    function schedule() {
        if (refreshCoverage) refreshCoverage();
        edits++;
        showBase();
        status(t('Unsaved changes', 'Ungespeicherte Änderungen'), 'dirty');
        sequence++;
        clearTimeout(timer);
        timer = setTimeout(loadPreview, 260);
    }

    async function loadPreview() {
        clearTimeout(timer);
        const current = ++sequence;
        newer.disabled = older.disabled = true;
        try {
            const result = await api('/preview', payload());
            if (current !== sequence) return;
            const sample = result.context;
            context.textContent = sample ? [sample.name, '#' + sample.number, sample.position + ' / ' + sample.count].filter(Boolean).join(' · ') : t('Sample data', 'Beispieldaten');
            newer.disabled = !sample || sample.position <= 1;
            older.disabled = !sample || sample.position >= sample.count;
            subject.textContent = t('Subject: ', 'Betreff: ') + result.subject.replace(/^(\[[^\]\r\n]+\]):[ \t]*/, '$1 ');
            const sender = result.sender;
            senderLine.hidden = !sender;
            if (sender) {
                const displayAddress = (name, address) => name && address ? name + ' <' + address + '>' : name || address;
                from.textContent = t('From: ', 'Absender: ') + displayAddress(sender.name, sender.address);
                replyTo.hidden = !sender.replyAddress;
                replyTo.textContent = sender.replyAddress ? t('Reply to: ', 'Antwort an: ') + displayAddress(sender.replyName, sender.replyAddress) : '';
            }
            const external = hasExternalImages(result.html);
            images.hidden = !external || externalImages;
            imageNote.hidden = !external || !externalImages;
            realOrderButton.hidden = !email.order || realOrder;
            realOrderNote.hidden = !email.order || !realOrder;
            showFrame(frame, result.html);
            linkList.replaceChildren();
            const missing = result.unresolved || [];
            const resolved = Object.entries(result.resolved || {});
            linkStatus.hidden = !missing.length && !resolved.length;
            linkStatus.open = Boolean(missing.length);
            for (const id of missing) node('li', id + ' · ' + t('No matching target. Link omitted.', 'Kein passendes Ziel. Der Link entfällt.'), linkList);
            for (const [id, url] of resolved) node('li', id + ' → ' + (url === true ? t('Order link available', 'Bestelllink verfügbar') : url), linkList);
            const template = design.templates[email.id];
            const list = nativeText.lastElementChild;
            for (const [key, value] of Object.entries(result.copy)) {
                if (!value || nativeText.seen.has(key)) continue;
                nativeText.seen.add(key);
                field(list, value, template.copy ||= Object.create(null), key, { placeholder: value, tags: true, order: email.order });
            }
        } catch (error) {
            if (current !== sequence) return;
            context.textContent = error.message || t('Preview unavailable.', 'Vorschau nicht verfügbar.');
            senderLine.hidden = true;
            images.hidden = imageNote.hidden = true;
            frame.srcdoc = '';
        }
    }

    async function perform(action) {
        if (busy || !email) return;
        if (action === '/publish' && !window.confirm(t('Publish the design for all selected templates?', 'Design für alle ausgewählten Vorlagen veröffentlichen?'))) return;
        if (action === '/test' && !window.confirm(t('Send a sample email to ', 'Testmail mit Beispieldaten senden an ') + address + '?')) return;
        busy = true;
        const editVersion = edits;
        const saving = action !== '/test';
        if (saving) status(t('Saving…', 'Wird gespeichert …'), 'pending');
        [save, publish, test].forEach(element => { element.disabled = true; });
        try {
            const result = await api(action, payload());
            if (result.revision) revision = result.revision;
            if (action === '/publish') {
                published = true;
                for (const item of emails) item.live = result.design?.templates?.[item.id]?.enabled === true;
                showBase();
            }
            if (saving && editVersion === edits) {
                const time = new Intl.DateTimeFormat(undefined, { hour: '2-digit', minute: '2-digit' }).format();
                status((action === '/publish' ? t('Published', 'Veröffentlicht') : t('Draft saved', 'Entwurf gespeichert')) + ' · ' + time, 'saved');
                // Keep the currently mounted controls bound to their original objects.
                if (result.design?.selected !== undefined) design.selected = result.design.selected;
            }
            feedback.textContent = {
                '/draft': t('Draft saved.', 'Entwurf gespeichert.'),
                '/publish': t('Design published.', 'Design veröffentlicht.'),
                '/test': t('WordPress accepted the test email for delivery.', 'WordPress hat die Testmail zum Versand angenommen.')
            }[action];
        } catch (error) {
            if (saving && editVersion === edits) status(t('Save failed', 'Speichern fehlgeschlagen'), 'dirty');
            feedback.textContent = error.message || t('The action failed.', 'Die Aktion ist fehlgeschlagen.');
        } finally {
            busy = false;
            [save, publish, test].forEach(element => { element.disabled = false; });
        }
    }

    api().then(result => {
        design = result.design;
        revision = result.revision;
        realOrder = result.previewOptions?.realOrder === true;
        externalImages = result.previewOptions?.externalImages === true;
        nativeBranding = result.woocommerceBranding || {};
        senderSettings.href = result.senderSettingsUrl;
        migrationSources = result.migration;
        deliveryConflicts = result.conflicts || [];
        importedLive = result.importedLive;
        tagCatalog = result.tags || {};
        if (!design.library || Array.isArray(design.library)) design.library = {};
        published = result.published;
        status(result.saved ? t('Draft saved', 'Entwurf gespeichert') : t('No draft yet', 'Noch kein Entwurf'), result.saved ? 'saved' : '');
        if (importedLive) {
            const transfer = node('div', '', alerts);
            transfer.className = 'notice notice-info inline';
            node('p', t('The source’s published design is available as a separate preview.', 'Der veröffentlichte Stand der Quellsite steht als separate Vorschau zur Verfügung.'), transfer);
            const action = node('p', '', transfer);
            button(t('Preview source’s published version', 'Veröffentlichten Quellstand ansehen'), action, () => {
                if (busy || !window.confirm(t('Load this version into the editor in place of the current draft?', 'Diesen Stand anstelle des aktuellen Entwurfs im Editor öffnen?'))) return;
                loadTransferDraft(importedLive);
            });
        }
        if (result.conflicts?.length) {
            const warning = node('div', '', alerts);
            warning.className = 'notice notice-warning inline';
            node('p', t('Active email designer detected: ', 'Aktiver E-Mail-Designer erkannt: ') + result.conflicts.join(', ') + '. ' + t('Multiple designers can cause duplicate headings or layouts. Use one designer for each email.', 'Mehrere Designer können doppelte Überschriften oder Layouts erzeugen. Pro E-Mail sollte ein Designer zuständig sein.'), warning);
            if (Object.keys(migrationSources?.options || {}).length) {
                button(t('Prepare switch', 'Wechsel vorbereiten'), warning, () => {
                    settings.querySelector('#ddw-twc-tab-design')?.click();
                    if (migrationPanel) {
                        migrationPanel.open = true;
                        migrationPanel.querySelector('summary')?.focus();
                        migrationPanel.scrollIntoView({ block: 'nearest' });
                    }
                });
            }
        }
        node('p', t('Other designers, theme templates and add-ons may affect email content and layout.', 'Andere Designer, Theme-Vorlagen und Add-ons können Inhalt und Layout der E-Mails beeinflussen.'), alerts).className = 'description';
        if (Array.isArray(design.templates)) design.templates = {};
        for (const template of Object.values(design.templates)) {
            template.copy = Object.assign(Object.create(null), template.copy);
        }
        emails = result.emails;
        address = result.address;
        if (!emails.length) throw new Error(t('No WooCommerce templates are available.', 'Es sind keine WooCommerce-Vorlagen verfügbar.'));
        for (const source of ['', 'Germanized', 'Germanized Pro']) {
            const items = emails.filter(item => (item.source || '') === source);
            if (!items.length) continue;
            const group = node('optgroup', '', select);
            group.label = source || 'WooCommerce';
            for (const item of items) node('option', item.title, group).value = item.id;
        }
        const requested = new URLSearchParams(window.location.search).get('template');
        select.value = (emails.find(item => item.id === requested) || emails.find(item => item.id === 'WC_Email_Customer_Completed_Order') || emails[0]).id;
        select.onchange = () => {
            email = emails.find(item => item.id === select.value);
            position = 0;
            realOrderButton.hidden = realOrderNote.hidden = true;
            images.hidden = imageNote.hidden = true;
            edit();
            loadPreview();
        };
        select.onchange();
        [select, save, publish, test].forEach(element => { element.disabled = false; });
        feedback.textContent = t('Test email uses your WordPress mail setup. SMTP plugins such as FluentSMTP or SureMail can handle delivery.', 'Der Testversand nutzt den WordPress-Mailversand. SMTP-Plugins wie FluentSMTP oder SureMail können die Zustellung übernehmen.');
    }).catch(error => { feedback.textContent = error.message; });
})();
