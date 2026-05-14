const { createApp, ref, reactive, computed, watch, nextTick, onMounted } = Vue;

const app = createApp({
    setup() {
        // State
        const sites = ref([]);
        const templates = ref([]);
        const extensions = ref({});
        const currentSite = ref(null);
        const pages = ref([]);
        const currentPageId = ref(null);
        const currentPage = ref(null);
        const componentDefs = ref({});
        const selectedComponentId = ref(null);
        const leftTab = ref('components');
        const rightTab = ref('content');
        const previewMode = ref('desktop');
        const saveStatus = ref('saved');
        const saveStatusText = ref('Saved');
        const showTemplates = ref(false);
        const showExtensions = ref(false);
        const showSeoModal = ref(false);
        const showPageSettings = ref(false);
        const showMediaPicker = ref(false);
        const mediaItems = ref([]);
        const mediaPickerTarget = ref(null);
        const seoData = reactive({ title: '', description: '', keywords: '' });
        const dropIndex = ref(-1);
        const dragType = ref(null); // 'new' or 'existing'
        const dragData = ref(null);
        let saveTimer = null;

        // Computed
        const selectedComponent = computed(() => {
            if (!currentPage.value || !selectedComponentId.value) return null;
            return currentPage.value.components.find(c => c.id === selectedComponentId.value) || null;
        });

        const themeCategory = computed(() => {
            if (!currentPage.value) return null;
            const themeComponents = {};
            currentPage.value.components.forEach(c => {
                if (c.type === 'theme_section' && c.props._tpl_id) {
                    const id = c.props._tpl_id;
                    if (!themeComponents[id]) {
                        themeComponents[id] = {
                            label: 'Theme: ' + id.charAt(0).toUpperCase() + id.slice(1),
                            defaults: JSON.parse(JSON.stringify(c.props))
                        };
                    }
                }
            });
            return Object.keys(themeComponents).length > 0 ? themeComponents : null;
        });

        const combinedComponentDefs = computed(() => {
            const defs = { ...componentDefs.value };
            if (themeCategory.value) {
                defs['current theme'] = themeCategory.value;
            }
            return defs;
        });

        // Init
        onMounted(async () => {
            await loadSites();
            componentDefs.value = await API.listComponents();
            await loadMedia();
            templates.value = await API.listTemplates();
            try { extensions.value = await API.listExtensions(); } catch(e) {}
        });

        // Sites
        async function loadSites() {
            sites.value = await API.listSites();
        }

        async function createBlankSite() {
            const name = prompt('Site name:', 'My Website');
            if (!name) return;
            const slug = name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
            const site = await API.createSite({ name, slug });
            await loadSites();
            openSite(site);
        }

        async function createFromTemplate(templateId) {
            showTemplates.value = false;
            const site = await API.createSite({ template: templateId });
            await loadSites();
            openSite(site);
        }

        async function createFromExtension(themeId) {
            showExtensions.value = false;
            const site = await API.installExtension(themeId);
            await loadSites();
            openSite(site);
        }

        async function openSite(site) {
            currentSite.value = site;
            pages.value = await API.listPages(site.id);
            if (pages.value.length > 0) {
                currentPageId.value = pages.value[0].id;
                await loadPage();
            }
        }

        async function deleteSite(id) {
            if (!confirm('Delete this site?')) return;
            await API.deleteSite(id);
            await loadSites();
        }

        function backToSites() {
            currentSite.value = null;
            currentPage.value = null;
            selectedComponentId.value = null;
            loadSites();
        }

        async function saveSiteSettings() {
            if (!currentSite.value) return;
            await API.updateSite(currentSite.value.id, { name: currentSite.value.name });
        }

        // Pages
        async function loadPage() {
            if (!currentPageId.value) return;
            const page = await API.getPage(currentPageId.value);
            currentPage.value = page;
            selectedComponentId.value = null;
        }

        async function addPage() {
            const name = prompt('Page name:', 'New Page');
            if (!name) return;
            const slug = name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
            const page = await API.savePage(currentSite.value.id, {
                name, slug, components: [], seo: { title: name }, sort_order: pages.value.length
            });
            pages.value = await API.listPages(currentSite.value.id);
            currentPageId.value = page.id;
            await loadPage();
        }

        async function deletePageConfirm(id) {
            if (!confirm('Delete this page?')) return;
            await API.deletePage(id);
            pages.value = await API.listPages(currentSite.value.id);
            if (pages.value.length > 0) {
                currentPageId.value = pages.value[0].id;
                await loadPage();
            } else {
                currentPage.value = null;
            }
        }

        // Save
        function triggerSave() {
            saveStatus.value = 'saving';
            saveStatusText.value = 'Saving...';
            clearTimeout(saveTimer);
            saveTimer = setTimeout(doSave, 1500);
        }

        async function doSave() {
            if (!currentPage.value || !currentSite.value) return;
            try {
                await API.savePage(currentSite.value.id, {
                    name: currentPage.value.name,
                    slug: currentPage.value.slug,
                    components: currentPage.value.components,
                    seo: currentPage.value.seo || {},
                    sort_order: currentPage.value.sort_order || 0,
                }, currentPage.value.id);
                saveStatus.value = 'saved';
                saveStatusText.value = 'Saved';
            } catch (e) {
                saveStatus.value = '';
                saveStatusText.value = 'Error saving';
            }
        }

        // Components
        function generateId() {
            return 'c' + Math.random().toString(36).substr(2, 9);
        }

        function getDefaultProps(type) {
            const allDefs = componentDefs.value;
            for (const cat of Object.values(allDefs)) {
                if (cat[type]) return JSON.parse(JSON.stringify(cat[type].defaults));
            }
            return {};
        }

        function getComponentLabel(type) {
            const allDefs = componentDefs.value;
            for (const cat of Object.values(allDefs)) {
                if (cat[type]) return cat[type].label;
            }
            return type;
        }

        function getComponentSchema(compOrType) {
            if (typeof compOrType === 'object' && compOrType.type === 'theme_section') {
                try {
                    const schemaJson = JSON.parse(compOrType.props._schema || '{}');
                    const fields = [];
                    for (const key in schemaJson) {
                        fields.push({ key: key, type: 'text', label: key.replace(/__/g, '').replace(/_/g, ' ').trim() });
                    }
                    return fields;
                } catch(e) { return []; }
            }
            const type = typeof compOrType === 'object' ? compOrType.type : compOrType;
            const allDefs = componentDefs.value;
            for (const cat of Object.values(allDefs)) {
                if (cat[type]) return cat[type].schema;
            }
            return [];
        }

        function selectComponent(id) {
            selectedComponentId.value = id;
        }

        function updateProp(key, value) {
            if (!selectedComponent.value) return;
            
            // If it's a theme property, update all components of type theme_section
            if (key.startsWith('__')) {
                currentPage.value.components.forEach(c => {
                    if (c.type === 'theme_section' && c.props.hasOwnProperty(key)) {
                        c.props[key] = value;
                    }
                });
            } else {
                selectedComponent.value.props[key] = value;
            }
            
            triggerSave();
        }

        function moveComponent(index, direction) {
            const comps = currentPage.value.components;
            const newIndex = index + direction;
            if (newIndex < 0 || newIndex >= comps.length) return;
            const item = comps.splice(index, 1)[0];
            comps.splice(newIndex, 0, item);
            triggerSave();
        }

        function duplicateComponent(index) {
            const comp = currentPage.value.components[index];
            const clone = JSON.parse(JSON.stringify(comp));
            clone.id = generateId();
            currentPage.value.components.splice(index + 1, 0, clone);
            triggerSave();
        }

        function deleteComponent(index) {
            const comp = currentPage.value.components[index];
            if (selectedComponentId.value === comp.id) selectedComponentId.value = null;
            currentPage.value.components.splice(index, 1);
            triggerSave();
        }

        function getTwProp(key) {
            if (!selectedComponent.value) return '';
            if (!selectedComponent.value.props._tw) return '';
            return selectedComponent.value.props._tw[key] || '';
        }

        function updateTwProp(key, value) {
            if (!selectedComponent.value) return;
            if (!selectedComponent.value.props._tw) {
                selectedComponent.value.props._tw = {};
            }
            selectedComponent.value.props._tw[key] = value;
            
            // Re-build classes string from visual properties
            const tw = selectedComponent.value.props._tw;
            const classArr = [];
            for (const k in tw) {
                if (tw[k]) classArr.push(tw[k]);
            }
            // we will let the renderer merge this with props.classes
            triggerSave();
        }

        // Drag and Drop
        function onDragStartNew(event, type) {
            dragType.value = 'new';
            dragData.value = type;
            event.dataTransfer.effectAllowed = 'copy';
        }

        function onDragStartExisting(event, index) {
            dragType.value = 'existing';
            dragData.value = index;
            event.dataTransfer.effectAllowed = 'move';
        }

        function onCanvasDragOver(event) {
            event.dataTransfer.dropEffect = dragType.value === 'new' ? 'copy' : 'move';
        }

        function onCanvasDrop(event) {
            if (!currentPage.value) return;
            const targetIndex = dropIndex.value >= 0 ? dropIndex.value : currentPage.value.components.length;

            if (dragType.value === 'new') {
                const type = dragData.value;
                const newComp = {
                    id: generateId(),
                    type: type,
                    props: getDefaultProps(type),
                };
                currentPage.value.components.splice(targetIndex, 0, newComp);
                selectedComponentId.value = newComp.id;
            } else if (dragType.value === 'existing') {
                const fromIndex = dragData.value;
                const item = currentPage.value.components.splice(fromIndex, 1)[0];
                const adjustedIndex = targetIndex > fromIndex ? targetIndex - 1 : targetIndex;
                currentPage.value.components.splice(adjustedIndex, 0, item);
            }

            dropIndex.value = -1;
            dragType.value = null;
            dragData.value = null;
            triggerSave();
        }

        // Repeater
        function addRepeaterItem(key, fields) {
            if (!selectedComponent.value) return;
            const item = {};
            fields.forEach(f => { item[f.key] = f.type === 'toggle' ? false : ''; });
            if (!selectedComponent.value.props[key]) selectedComponent.value.props[key] = [];
            selectedComponent.value.props[key].push(item);
            triggerSave();
        }

        function removeRepeaterItem(key, index) {
            selectedComponent.value.props[key].splice(index, 1);
            triggerSave();
        }

        function updateRepeaterItem(key, index, subKey, value) {
            selectedComponent.value.props[key][index][subKey] = value;
            triggerSave();
        }

        // Media
        async function loadMedia() {
            try { mediaItems.value = await API.listMedia(); } catch(e) {}
        }

        async function uploadMedia(event) {
            const file = event.target.files[0];
            if (!file) return;
            await API.uploadMedia(file);
            await loadMedia();
            event.target.value = '';
        }

        async function deleteMedia(id) {
            if (!confirm('Delete this image?')) return;
            await API.deleteMedia(id);
            await loadMedia();
        }

        function copyMediaUrl(m) {
            const url = '/storage/uploads/' + m.filename;
            navigator.clipboard.writeText(url);
            alert('URL copied: ' + url);
        }

        function openMediaPicker(targetKey) {
            mediaPickerTarget.value = targetKey;
            showMediaPicker.value = true;
            loadMedia();
        }

        async function uploadMediaForPicker(event) {
            const file = event.target.files[0];
            if (!file) return;
            await API.uploadMedia(file);
            await loadMedia();
            event.target.value = '';
        }

        function pickMedia(m) {
            if (mediaPickerTarget.value && selectedComponent.value) {
                selectedComponent.value.props[mediaPickerTarget.value] = '/storage/uploads/' + m.filename;
                triggerSave();
            }
            showMediaPicker.value = false;
        }

        // SEO
        function openSeoModal() {
            if (!currentPage.value) return;
            const seo = currentPage.value.seo || {};
            seoData.title = seo.title || '';
            seoData.description = seo.description || '';
            seoData.keywords = seo.keywords || '';
            showSeoModal.value = true;
        }

        function saveSeo() {
            if (!currentPage.value) return;
            currentPage.value.seo = { ...seoData };
            showSeoModal.value = false;
            triggerSave();
        }

        // Publish & Preview
        async function publishSite() {
            if (!currentSite.value) return;
            try {
                const result = await API.publish(currentSite.value.id);
                alert('Published! URL: ' + result.url);
                window.open(result.url, '_blank');
            } catch (e) {
                alert('Publish failed: ' + e.message);
            }
        }

        function previewSite() {
            if (!currentSite.value) return;
            window.open('/published/' + currentSite.value.slug + '/index.html', '_blank');
        }

        // Component Preview Rendering
        function getTwClasses(comp) {
            if (!comp || !comp.props) return '';
            const p = comp.props;
            const tw = p._tw || {};
            const classArr = [];
            for (const k in tw) {
                if (tw[k]) classArr.push(tw[k]);
            }
            const custom = p.classes || '';
            if (custom) classArr.push(custom);
            return classArr.join(' ');
        }

        function renderComponentPreview(comp) {
            const p = comp.props || {};
            const twClasses = getTwClasses(comp);
            const renderers = {
                navbar: () => {
                    const bg = p.backgroundColor || '#fff';
                    const tc = p.textColor || '#111';
                    const logo = p.logoText || 'Site';
                    return `<div style="background:${bg};color:${tc};padding:12px 20px;display:flex;justify-content:space-between;align-items:center"><strong>${esc(logo)}</strong><span style="font-size:12px;color:#999">nav links</span></div>`;
                },
                hero: () => {
                    const bg = p.backgroundImage ? `url('${p.backgroundImage}')` : p.backgroundColor || '#1e3a5f';
                    const bgStyle = p.backgroundImage ? `background:linear-gradient(rgba(0,0,0,0.5),rgba(0,0,0,0.5)),${bg};background-size:cover;background-position:center` : `background:${bg}`;
                    return `<div style="${bgStyle};color:${p.textColor||'#fff'};padding:60px 20px;text-align:center;min-height:300px;display:flex;flex-direction:column;justify-content:center"><h1 style="font-size:28px;font-weight:bold;margin-bottom:8px">${esc(p.heading||'')}</h1><p style="opacity:0.9">${esc(p.subheading||'')}</p>${p.ctaText ? `<div style="margin-top:16px"><span style="background:white;color:#333;padding:8px 20px;border-radius:6px;font-weight:600">${esc(p.ctaText)}</span></div>` : ''}</div>`;
                },
                heading: () => {
                    const sizes = {h1:'text-4xl',h2:'text-3xl',h3:'text-2xl',h4:'text-xl',h5:'text-lg',h6:'text-base'};
                    const size = sizes[p.level||'h2']||'text-3xl';
                    // We only apply color/alignment via style if NOT overridden by TW, but simpler is to use Tailwind for those.
                    // If no explicit color is set in _tw, we use style.
                    return `<div class="${twClasses} text-${p.alignment||'left'}"><div class="${size} font-bold" style="${!twClasses.includes('text-') ? 'color:'+(p.color||'#111') : ''}">${esc(p.text||'')}</div></div>`;
                },
                text: () => `<div class="${twClasses} text-${p.alignment||'left'}">${p.content||'<p>Text block</p>'}</div>`,
                image: () => {
                    if (!p.src) return `<div class="${twClasses}" style="padding:20px;text-align:center;color:#999;background:#f1f5f9;margin:16px 20px;border-radius:8px;height:150px;display:flex;align-items:center;justify-content:center">Image placeholder</div>`;
                    return `<div class="${twClasses}" style="padding:16px 20px;text-align:center"><img src="${esc(p.src)}" alt="${esc(p.alt||'')}" style="max-width:100%;height:auto;border-radius:8px"></div>`;
                },
                video: () => `<div class="${twClasses}" style="padding:16px 20px;text-align:center;background:#0f172a;color:#94a3b8;height:200px;display:flex;align-items:center;justify-content:center;border-radius:8px;margin:0 20px">Video: ${esc(p.url||'No URL')}</div>`,
                button: () => {
                    const st = p.style === 'outline' ? `border:2px solid ${p.color||'#3b82f6'};color:${p.color||'#3b82f6'};background:transparent` : `background:${p.color||'#3b82f6'};color:white`;
                    return `<div class="text-${p.alignment||'left'} ${twClasses}"><span style="${st};padding:10px 24px;border-radius:6px;font-weight:600;display:inline-block">${esc(p.text||'Button')}</span></div>`;
                },
                section: () => {
                    const bgStyle = p.backgroundImage ? `background:url('${p.backgroundImage}');background-size:cover` : (!twClasses.includes('bg-') ? `background:${p.backgroundColor||'#fff'}` : '');
                    const pdTop = !twClasses.includes('p') && !twClasses.includes('py') && !twClasses.includes('pt') ? `padding-top:${p.paddingTop||60}px;` : '';
                    const pdBot = !twClasses.includes('p') && !twClasses.includes('py') && !twClasses.includes('pb') ? `padding-bottom:${p.paddingBottom||60}px;` : '';
                    return `<div class="${twClasses}" style="${bgStyle};${pdTop}${pdBot}"><div style="text-align:center;color:#999;font-size:13px;padding:20px 0">Section container</div></div>`;
                },
                columns: () => {
                    const cols = p.count || 2;
                    let colHtml = '';
                    for (let i = 0; i < cols; i++) colHtml += `<div style="background:#f1f5f9;padding:20px;border-radius:6px;text-align:center;color:#999;font-size:13px">Column ${i+1}</div>`;
                    return `<div style="display:grid;grid-template-columns:repeat(${cols},1fr);gap:${p.gap||24}px;padding:16px 20px">${colHtml}</div>`;
                },
                spacer: () => `<div style="height:${p.height||40}px;background:repeating-linear-gradient(45deg,transparent,transparent 5px,#f1f5f9 5px,#f1f5f9 10px)"></div>`,
                features: () => {
                    const items = (p.items||[]).map(i => `<div style="text-align:center;padding:16px"><div style="font-size:32px;margin-bottom:8px">${i.icon||''}</div><div style="font-weight:600;margin-bottom:4px">${esc(i.title||'')}</div><div style="font-size:13px;color:#666">${esc(i.description||'')}</div></div>`).join('');
                    return `<div style="background:#f8fafc;padding:40px 20px"><h2 style="text-align:center;font-size:24px;font-weight:bold;margin-bottom:24px">${esc(p.heading||'')}</h2><div style="display:grid;grid-template-columns:repeat(${p.columns||3},1fr);gap:16px">${items}</div></div>`;
                },
                testimonials: () => {
                    const items = (p.items||[]).map(i => `<div style="background:white;padding:20px;border-radius:8px;border:1px solid #e2e8f0"><p style="font-style:italic;color:#555;margin-bottom:12px">"${esc(i.quote||'')}"</p><div style="font-weight:600">${esc(i.name||'')}</div><div style="font-size:12px;color:#999">${esc(i.role||'')}</div></div>`).join('');
                    return `<div style="padding:40px 20px"><h2 style="text-align:center;font-size:24px;font-weight:bold;margin-bottom:24px">${esc(p.heading||'')}</h2><div style="display:grid;grid-template-columns:repeat(2,1fr);gap:16px">${items}</div></div>`;
                },
                pricing: () => {
                    const plans = (p.plans||[]).map(pl => {
                        const features = Array.isArray(pl.features) ? pl.features : (pl.features||'').split('\n');
                        const fl = features.filter(f=>f.trim()).map(f => `<div style="padding:6px 0;border-bottom:1px solid #f1f5f9;font-size:13px">${esc(f.trim())}</div>`).join('');
                        const hl = pl.highlighted ? 'border:2px solid #3b82f6' : 'border:1px solid #e2e8f0';
                        return `<div style="${hl};border-radius:12px;padding:24px;background:white"><h3 style="font-weight:600">${esc(pl.name||'')}</h3><div style="font-size:28px;font-weight:bold;margin:8px 0 16px">${esc(pl.price||'')}</div>${fl}<div style="margin-top:16px;text-align:center"><span style="background:#1e293b;color:white;padding:8px 20px;border-radius:6px;font-size:13px;font-weight:600">${esc(pl.ctaText||'Choose')}</span></div></div>`;
                    }).join('');
                    return `<div style="background:#f8fafc;padding:40px 20px"><h2 style="text-align:center;font-size:24px;font-weight:bold;margin-bottom:24px">${esc(p.heading||'')}</h2><div style="display:grid;grid-template-columns:repeat(${Math.min((p.plans||[]).length,3)},1fr);gap:16px">${plans}</div></div>`;
                },
                contact_form: () => {
                    const fields = (p.fields||[]).map(f => `<div style="margin-bottom:12px"><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px">${esc(f.label||'')}</label>${f.type==='textarea' ? '<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;height:60px"></div>' : '<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;height:36px"></div>'}</div>`).join('');
                    return `<div style="padding:40px 20px;max-width:500px;margin:0 auto"><h2 style="text-align:center;font-size:24px;font-weight:bold;margin-bottom:24px">${esc(p.heading||'')}</h2>${fields}<div style="text-align:center;margin-top:16px"><span style="background:#3b82f6;color:white;padding:10px 28px;border-radius:6px;font-weight:600">${esc(p.submitText||'Send')}</span></div></div>`;
                },
                map: () => `<div style="padding:16px 20px"><div style="background:#e2e8f0;height:${p.height||300}px;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#64748b">Map: ${esc(p.address||'')}</div></div>`,
                gallery: () => {
                    const images = (p.images||[]);
                    if (images.length === 0) return `<div style="padding:20px;text-align:center;color:#999">Gallery (no images yet)</div>`;
                    const imgs = images.map(i => `<img src="${esc(i.src||'')}" alt="${esc(i.alt||'')}" style="width:100%;height:120px;object-fit:cover;border-radius:6px">`).join('');
                    return `<div style="display:grid;grid-template-columns:repeat(${p.columns||3},1fr);gap:${p.gap||8}px;padding:16px 20px">${imgs}</div>`;
                },
                footer: () => {
                    const bg = p.backgroundColor || '#111827';
                    const tc = p.textColor || '#9ca3af';
                    return `<div style="background:${bg};color:${tc};padding:24px 20px;text-align:center;font-size:13px">${esc(p.text||'Footer')}</div>`;
                },
                theme_section: () => {
                    let html = p._html || '<div style="padding:40px;text-align:center">Empty Section</div>';
                    for (const key in p) {
                        if (key.startsWith('__')) {
                            const val = p[key];
                            const regex = new RegExp(key, 'g');
                            html = html.replace(regex, typeof val === 'string' ? esc(val) : val);
                        }
                    }
                    return `<div class="${twClasses}">${html}</div>`;
                },
            };

            return renderers[comp.type] ? renderers[comp.type]() : `<div class="${twClasses}" style="padding:20px;color:#999;text-align:center">Unknown: ${esc(comp.type)}</div>`;
        }

        function esc(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        return {
            sites, templates, extensions, currentSite, pages, currentPageId, currentPage,
            componentDefs: combinedComponentDefs, selectedComponentId, selectedComponent,
            leftTab, rightTab, previewMode, saveStatus, saveStatusText,
            showTemplates, showExtensions, showSeoModal, showPageSettings, showMediaPicker,
            mediaItems, seoData, dropIndex,
            loadSites, createBlankSite, createFromTemplate, createFromExtension, openSite, deleteSite, backToSites, saveSiteSettings,
            loadPage, addPage, deletePageConfirm,
            triggerSave,
            selectComponent, updateProp, moveComponent, duplicateComponent, deleteComponent,
            getTwProp, updateTwProp,
            getComponentLabel, getComponentSchema,
            onDragStartNew, onDragStartExisting, onCanvasDragOver, onCanvasDrop,
            addRepeaterItem, removeRepeaterItem, updateRepeaterItem,
            loadMedia, uploadMedia, deleteMedia, copyMediaUrl,
            openMediaPicker, uploadMediaForPicker, pickMedia,
            openSeoModal, saveSeo,
            publishSite, previewSite,
            renderComponentPreview,
        };
    },
});

app.mount('#app');
