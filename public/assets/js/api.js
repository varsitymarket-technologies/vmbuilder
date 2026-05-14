const API = {
    base: '/api.php',

    async request(action, options = {}) {
        const params = new URLSearchParams({ action, ...options.params });
        const url = `${this.base}?${params}`;
        const fetchOptions = { method: options.method || 'GET', headers: {} };

        if (options.body) {
            fetchOptions.headers['Content-Type'] = 'application/json';
            fetchOptions.body = JSON.stringify(options.body);
        }
        if (options.formData) {
            fetchOptions.body = options.formData;
        }

        const res = await fetch(url, fetchOptions);
        const data = await res.json();
        if (!res.ok) throw new Error(data.error || 'Request failed');
        return data;
    },

    // Sites
    listSites() { return this.request('sites_list'); },
    createSite(data) { return this.request('sites_create', { method: 'POST', body: data }); },
    updateSite(id, data) { return this.request('sites_update', { method: 'PUT', params: { id }, body: data }); },
    deleteSite(id) { return this.request('sites_delete', { method: 'DELETE', params: { id } }); },

    // Pages
    listPages(siteId) { return this.request('pages_list', { params: { site_id: siteId } }); },
    getPage(id) { return this.request('pages_get', { params: { id } }); },
    savePage(siteId, data, pageId = null) {
        const params = { site_id: siteId };
        if (pageId) params.id = pageId;
        return this.request('pages_save', { method: 'POST', params, body: data });
    },
    deletePage(id) { return this.request('pages_delete', { method: 'DELETE', params: { id } }); },
    reorderPages(siteId, pageIds) { return this.request('pages_reorder', { method: 'PUT', params: { site_id: siteId }, body: { pageIds } }); },

    // Media
    listMedia() { return this.request('media_list'); },
    uploadMedia(file) {
        const fd = new FormData();
        fd.append('file', file);
        return this.request('media_upload', { method: 'POST', formData: fd });
    },
    deleteMedia(id) { return this.request('media_delete', { method: 'DELETE', params: { id } }); },

    // Publish
    publish(siteId) { return this.request('publish', { method: 'POST', params: { site_id: siteId } }); },

    // Templates
    listTemplates() { return this.request('templates_list'); },

    // Extensions
    listExtensions() { return this.request('extensions_list'); },
    installExtension(themeId) { return this.request('extensions_install', { method: 'POST', body: { theme_id: themeId } }); },

    // Components
    listComponents() { return this.request('components_list'); },
};
