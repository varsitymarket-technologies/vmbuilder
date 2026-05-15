<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/Database.php';
Database::migrate();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Website Builder</title>
    <link rel="stylesheet" href="./assets/css/editor.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
</head>

<body class="editor-body">
    <div id="app">
        <!-- Site Selector Screen -->
        <div v-if="!currentSite" class="site-selector">
            <div style="max-width:800px;width:100%;padding:24px">
                <img src="./assets/vm.png" style="margin:auto;">
                <h1 style="font-size:28px;font-weight:700;text-align:center;margin-bottom:8px">Website Builder</h1>
                <p style="text-align:center;color:#64748b;margin-bottom:32px">Create and manage your websites</p>

                <div style="display:flex;gap:12px;margin-bottom:24px;justify-content:center">
                    <button class="btn btn-primary" @click="createBlankSite">+ New Blank Site</button>
                    <button class="btn btn-secondary" @click="showTemplates = true">From Template</button>
                    <button class="btn btn-secondary" @click="showExtensions = true">From Extension</button>
                </div>

                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px">
                    <div class="site-card" v-for="site in sites" :key="site.id" @click="openSite(site)">
                        <div style="font-weight:600;margin-bottom:4px">{{ site.name }}</div>
                        <div style="font-size:12px;color:#94a3b8">/{{ site.slug }}</div>
                        <button class="btn btn-danger btn-sm" style="margin-top:12px"
                            @click.stop="deleteSite(site.id)">Delete</button>
                    </div>
                </div>

                <!-- Template Modal -->
                <div class="modal-overlay" :class="{ active: showTemplates }">
                    <div class="modal-content">
                        <h2 style="font-size:20px;font-weight:700;margin-bottom:16px">Choose a Template</h2>
                        <div style="display:grid;gap:12px">
                            <div class="site-card" v-for="t in templates" :key="t.id" @click="createFromTemplate(t.id)">
                                <div style="font-weight:600">{{ t.name }}</div>
                                <div style="font-size:13px;color:#64748b;margin-top:4px">{{ t.description }}</div>
                            </div>
                        </div>
                        <button class="btn btn-secondary" style="margin-top:16px"
                            @click="showTemplates = false">Cancel</button>
                    </div>
                </div>
            </div>
            <!-- Extensions Modal -->
            <div class="modal-overlay" :class="{ active: showExtensions }">
                <div class="modal-content">
                    <h2 style="font-size:20px;font-weight:700;margin-bottom:16px">Choose an Extension</h2>
                    <div style="display:grid;gap:12px">
                        <div class="site-card" v-for="(info, id) in extensions" :key="id"
                            @click="createFromExtension(id)">
                            <div style="font-weight:600">{{ id }}</div>
                            <div style="font-size:13px;color:#64748b;margin-top:4px">Version: {{ info.hash }}</div>
                        </div>
                    </div>
                    <button class="btn btn-secondary" style="margin-top:16px"
                        @click="showExtensions = false">Cancel</button>
                </div>
            </div>
        </div>

        <!-- Editor -->
        <div v-else class="editor-layout">
            <!-- Top Bar -->
            <div class="top-bar">
                <button class="btn btn-secondary btn-sm" @click="backToSites" title="Back to sites">&larr;</button>
                <input type="text" v-model="currentSite.name" @change="saveSiteSettings"
                    style="background:transparent;border:1px solid #475569;color:white;padding:6px 12px;border-radius:6px;font-size:14px;width:200px">
                <select v-model="currentPageId" @change="loadPage"
                    style="background:#334155;color:white;border:1px solid #475569;padding:6px 10px;border-radius:6px;font-size:13px">
                    <option v-for="p in pages" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
                <button class="btn btn-secondary btn-sm" @click="addPage">+ Page</button>
                <button class="btn btn-secondary btn-sm" @click="showPageSettings = true" v-if="currentPage">Page
                    Settings</button>

                <div style="flex:1"></div>

                <div class="preview-btns" style="display:flex;gap:4px">
                    <button :class="{ active: previewMode === 'desktop' }"
                        @click="previewMode='desktop'">Desktop</button>
                    <button :class="{ active: previewMode === 'tablet' }" @click="previewMode='tablet'">Tablet</button>
                    <button :class="{ active: previewMode === 'mobile' }" @click="previewMode='mobile'">Mobile</button>
                </div>

                <button class="btn btn-secondary btn-sm" @click="openSeoModal">SEO</button>
                <button class="btn btn-secondary btn-sm" @click="previewSite">Preview</button>
                <button class="btn btn-success btn-sm" @click="publishSite">Publish</button>
                <span class="save-status" :class="saveStatus">{{ saveStatusText }}</span>
            </div>

            <!-- Left Sidebar -->
            <div class="left-sidebar">
                <div class="tab-bar">
                    <button class="tab-btn" :class="{ active: leftTab === 'components' }"
                        @click="leftTab='components'">Components</button>
                    <button class="tab-btn" :class="{ active: leftTab === 'pages' }"
                        @click="leftTab='pages'">Pages</button>
                    <button class="tab-btn" :class="{ active: leftTab === 'media' }"
                        @click="leftTab='media'">Media</button>
                </div>

                <!-- Components tab -->
                <div v-if="leftTab === 'components'">
                    <template v-for="(items, category) in componentDefs" :key="category">
                        <div class="sidebar-section">{{ category }}</div>
                        <div v-for="(def, type) in items" :key="type" class="component-item" draggable="true"
                            @dragstart="onDragStartNew($event, type)">
                            <span>{{ def.label }}</span>
                        </div>
                    </template>
                </div>

                <!-- Pages tab -->
                <div v-if="leftTab === 'pages'" style="padding:12px">
                    <div v-for="p in pages" :key="p.id"
                        style="padding:10px;border-radius:6px;margin-bottom:4px;cursor:pointer;display:flex;justify-content:space-between;align-items:center"
                        :style="{ background: p.id === currentPageId ? '#e2e8f0' : 'transparent' }"
                        @click="currentPageId = p.id; loadPage()">
                        <span>{{ p.name }}</span>
                        <button v-if="pages.length > 1" class="btn btn-danger btn-sm"
                            @click.stop="deletePageConfirm(p.id)" style="padding:2px 6px;font-size:11px">x</button>
                    </div>
                </div>

                <!-- Media tab -->
                <div v-if="leftTab === 'media'" style="padding:12px">
                    <div style="margin-bottom:12px">
                        <input type="file" accept="image/*" @change="uploadMedia" style="font-size:12px">
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                        <div v-for="m in mediaItems" :key="m.id" style="position:relative">
                            <img :src="'/storage/uploads/' + m.filename"
                                style="width:100%;height:80px;object-fit:cover;border-radius:6px;cursor:pointer"
                                @click="copyMediaUrl(m)">
                            <button @click="deleteMedia(m.id)"
                                style="position:absolute;top:2px;right:2px;background:#ef4444;color:white;border:none;border-radius:3px;width:18px;height:18px;font-size:10px;cursor:pointer">x</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Canvas -->
            <div class="canvas-area">
                <div class="canvas-frame" :class="previewMode" ref="canvasFrame" @dragover.prevent="onCanvasDragOver"
                    @drop.prevent="onCanvasDrop">

                    <div v-if="!currentPage || currentPage.components.length === 0"
                        style="display:flex;align-items:center;justify-content:center;min-height:400px;color:#94a3b8;font-size:16px">
                        Drag components here to start building
                    </div>

                    <template v-for="(comp, index) in (currentPage ? currentPage.components : [])" :key="comp.id">
                        <div class="drop-zone" :class="{ active: dropIndex === index }"
                            @dragover.prevent="dropIndex = index" @dragleave="dropIndex = -1"></div>
                        <div class="canvas-component" :class="{ selected: selectedComponentId === comp.id }"
                            @click.stop="selectComponent(comp.id)" draggable="true"
                            @dragstart="onDragStartExisting($event, index)">
                            <div class="component-toolbar">
                                <button @click.stop="moveComponent(index, -1)" title="Move up">&#8593;</button>
                                <button @click.stop="moveComponent(index, 1)" title="Move down">&#8595;</button>
                                <button @click.stop="duplicateComponent(index)" title="Duplicate">&#9776;</button>
                                <button @click.stop="deleteComponent(index)" title="Delete">&#10005;</button>
                            </div>
                            <div v-html="renderComponentPreview(comp)"></div>
                        </div>
                    </template>
                    <div class="drop-zone"
                        :class="{ active: dropIndex === (currentPage ? currentPage.components.length : 0) }"
                        @dragover.prevent="dropIndex = (currentPage ? currentPage.components.length : 0)"
                        @dragleave="dropIndex = -1" style="min-height:40px"></div>
                </div>
            </div>

            <!-- Right Sidebar -->
            <div class="right-sidebar">
                <div v-if="!selectedComponent" style="color:#94a3b8;text-align:center;margin-top:40px;font-size:14px">
                    Select a component to edit its properties
                </div>
                <div v-else>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
                        <h3 style="font-weight:700;font-size:16px">{{ getComponentLabel(selectedComponent.type) }}</h3>
                        <button class="btn btn-secondary btn-sm" @click="selectedComponentId = null">Close</button>
                    </div>

                    <div class="tab-bar" style="margin-bottom: 16px;">
                        <button class="tab-btn" :class="{ active: rightTab === 'content' }" @click="rightTab='content'">Content</button>
                        <button class="tab-btn" :class="{ active: rightTab === 'styles' }" @click="rightTab='styles'">Styles</button>
                    </div>

                    <div v-show="rightTab === 'content'">
                        <div v-for="field in getComponentSchema(selectedComponent)" :key="field.key" class="prop-field">
                        <label>{{ field.label }}</label>

                        <input v-if="field.type === 'text'" type="text" :value="selectedComponent.props[field.key]"
                            @input="updateProp(field.key, $event.target.value)">

                        <textarea v-else-if="field.type === 'textarea'" rows="3"
                            :value="selectedComponent.props[field.key]"
                            @input="updateProp(field.key, $event.target.value)"></textarea>

                        <textarea v-else-if="field.type === 'richtext'" rows="6"
                            :value="selectedComponent.props[field.key]"
                            @input="updateProp(field.key, $event.target.value)"></textarea>

                        <input v-else-if="field.type === 'number'" type="number"
                            :value="selectedComponent.props[field.key]"
                            @input="updateProp(field.key, parseInt($event.target.value) || 0)">

                        <input v-else-if="field.type === 'color'" type="color"
                            :value="selectedComponent.props[field.key]"
                            @input="updateProp(field.key, $event.target.value)">

                        <select v-else-if="field.type === 'select'" :value="selectedComponent.props[field.key]"
                            @change="updateProp(field.key, $event.target.value)">
                            <option v-for="opt in field.options" :key="opt" :value="opt">{{ opt }}</option>
                        </select>

                        <label v-else-if="field.type === 'toggle'"
                            style="display:flex;align-items:center;gap:8px;cursor:pointer">
                            <input type="checkbox" :checked="selectedComponent.props[field.key]"
                                @change="updateProp(field.key, $event.target.checked)">
                            <span style="font-weight:400">{{ selectedComponent.props[field.key] ? 'On' : 'Off' }}</span>
                        </label>

                        <div v-else-if="field.type === 'image'" style="display:flex;gap:8px;align-items:center">
                            <input type="text" :value="selectedComponent.props[field.key]"
                                @input="updateProp(field.key, $event.target.value)" placeholder="Image URL or upload">
                            <button class="btn btn-secondary btn-sm" @click="openMediaPicker(field.key)">Browse</button>
                        </div>

                        <!-- Repeater -->
                        <div v-else-if="field.type === 'repeater'">
                            <div class="repeater-item" v-for="(item, ri) in (selectedComponent.props[field.key] || [])"
                                :key="ri">
                                <button class="remove-btn" @click="removeRepeaterItem(field.key, ri)">x</button>
                                <div v-for="subfield in field.fields" :key="subfield.key" class="prop-field"
                                    style="margin-bottom:8px">
                                    <label style="font-size:11px">{{ subfield.label }}</label>
                                    <input
                                        v-if="subfield.type === 'text' || subfield.type === 'email' || subfield.type === 'tel'"
                                        type="text" :value="item[subfield.key]"
                                        @input="updateRepeaterItem(field.key, ri, subfield.key, $event.target.value)">
                                    <textarea v-else-if="subfield.type === 'textarea'" rows="2"
                                        :value="item[subfield.key]"
                                        @input="updateRepeaterItem(field.key, ri, subfield.key, $event.target.value)"></textarea>
                                    <select v-else-if="subfield.type === 'select'" :value="item[subfield.key]"
                                        @change="updateRepeaterItem(field.key, ri, subfield.key, $event.target.value)">
                                        <option v-for="o in subfield.options" :key="o" :value="o">{{ o }}</option>
                                    </select>
                                    <label v-else-if="subfield.type === 'toggle'"
                                        style="display:flex;align-items:center;gap:6px;cursor:pointer">
                                        <input type="checkbox" :checked="item[subfield.key]"
                                            @change="updateRepeaterItem(field.key, ri, subfield.key, $event.target.checked)">
                                        {{ item[subfield.key] ? 'Yes' : 'No' }}
                                    </label>
                                    <div v-else-if="subfield.type === 'image'" style="display:flex;gap:4px">
                                        <input type="text" :value="item[subfield.key]"
                                            @input="updateRepeaterItem(field.key, ri, subfield.key, $event.target.value)">
                                    </div>
                                </div>
                            </div>
                            <button class="btn btn-secondary btn-sm" @click="addRepeaterItem(field.key, field.fields)">+
                                Add</button>
                        </div>
                    </div>

                    <div v-show="rightTab === 'styles'">
                        <div class="prop-field">
                            <label>Padding</label>
                            <select :value="getTwProp('padding')" @change="updateTwProp('padding', $event.target.value)">
                                <option value="">None</option>
                                <option value="p-2">Small (p-2)</option>
                                <option value="p-4">Medium (p-4)</option>
                                <option value="p-8">Large (p-8)</option>
                                <option value="p-16">Extra Large (p-16)</option>
                            </select>
                        </div>
                        <div class="prop-field">
                            <label>Margin</label>
                            <select :value="getTwProp('margin')" @change="updateTwProp('margin', $event.target.value)">
                                <option value="">None</option>
                                <option value="m-2">Small (m-2)</option>
                                <option value="m-4">Medium (m-4)</option>
                                <option value="m-8">Large (m-8)</option>
                            </select>
                        </div>
                        <div class="prop-field">
                            <label>Background Color</label>
                            <input type="text" placeholder="e.g. bg-blue-500" :value="getTwProp('bg')" @input="updateTwProp('bg', $event.target.value)">
                        </div>
                        <div class="prop-field">
                            <label>Text Size</label>
                            <select :value="getTwProp('text_size')" @change="updateTwProp('text_size', $event.target.value)">
                                <option value="">Default</option>
                                <option value="text-sm">Small (text-sm)</option>
                                <option value="text-base">Base (text-base)</option>
                                <option value="text-lg">Large (text-lg)</option>
                                <option value="text-xl">Extra Large (text-xl)</option>
                                <option value="text-2xl">2x Large (text-2xl)</option>
                                <option value="text-4xl">4x Large (text-4xl)</option>
                            </select>
                        </div>
                        <div class="prop-field">
                            <label>Font Weight</label>
                            <select :value="getTwProp('font_weight')" @change="updateTwProp('font_weight', $event.target.value)">
                                <option value="">Default</option>
                                <option value="font-normal">Normal</option>
                                <option value="font-medium">Medium</option>
                                <option value="font-semibold">Semibold</option>
                                <option value="font-bold">Bold</option>
                            </select>
                        </div>
                        <div class="prop-field">
                            <label>Text Color</label>
                            <input type="text" placeholder="e.g. text-white" :value="getTwProp('text_color')" @input="updateTwProp('text_color', $event.target.value)">
                        </div>
                        <div class="prop-field">
                            <label>Rounded</label>
                            <select :value="getTwProp('rounded')" @change="updateTwProp('rounded', $event.target.value)">
                                <option value="">None</option>
                                <option value="rounded">Small</option>
                                <option value="rounded-md">Medium</option>
                                <option value="rounded-lg">Large</option>
                                <option value="rounded-xl">Extra Large</option>
                                <option value="rounded-full">Full</option>
                            </select>
                        </div>
                        <div class="prop-field">
                            <label>Box Shadow</label>
                            <select :value="getTwProp('shadow')" @change="updateTwProp('shadow', $event.target.value)">
                                <option value="">None</option>
                                <option value="shadow-sm">Small</option>
                                <option value="shadow">Medium</option>
                                <option value="shadow-md">Large</option>
                                <option value="shadow-lg">Extra Large</option>
                                <option value="shadow-xl">2x Large</option>
                            </select>
                        </div>
                        <div class="prop-field">
                            <label>Border Width & Color</label>
                            <div style="display:flex;gap:8px">
                                <select :value="getTwProp('border_width')" @change="updateTwProp('border_width', $event.target.value)" style="flex:1">
                                    <option value="">None</option>
                                    <option value="border">1px</option>
                                    <option value="border-2">2px</option>
                                    <option value="border-4">4px</option>
                                    <option value="border-8">8px</option>
                                </select>
                                <input type="text" placeholder="border-gray-200" :value="getTwProp('border_color')" @input="updateTwProp('border_color', $event.target.value)" style="flex:1">
                            </div>
                        </div>
                        <div class="prop-field">
                            <label>Custom Classes</label>
                            <textarea rows="3" :value="selectedComponent.props.classes || ''" placeholder="e.g. hover:bg-red-500 shadow-md" @input="updateProp('classes', $event.target.value)"></textarea>
                        </div>
                    </div>

                </div>
            </div>

            <!-- SEO Modal -->
            <div class="modal-overlay" :class="{ active: showSeoModal }">
                <div class="modal-content">
                    <h2 style="font-size:20px;font-weight:700;margin-bottom:16px">SEO Settings</h2>
                    <div class="prop-field">
                        <label>Page Title</label>
                        <input type="text" v-model="seoData.title">
                    </div>
                    <div class="prop-field">
                        <label>Meta Description</label>
                        <textarea rows="3" v-model="seoData.description"></textarea>
                    </div>
                    <div class="prop-field">
                        <label>Keywords</label>
                        <input type="text" v-model="seoData.keywords" placeholder="keyword1, keyword2, ...">
                    </div>
                    <div style="display:flex;gap:8px;margin-top:16px">
                        <button class="btn btn-primary" @click="saveSeo">Save</button>
                        <button class="btn btn-secondary" @click="showSeoModal = false">Cancel</button>
                    </div>
                </div>
            </div>

            <!-- Page Settings Modal -->
            <div class="modal-overlay" :class="{ active: showPageSettings }">
                <div class="modal-content" v-if="currentPage">
                    <h2 style="font-size:20px;font-weight:700;margin-bottom:16px">Page Settings</h2>
                    <div class="prop-field">
                        <label>Page Name</label>
                        <input type="text" v-model="currentPage.name">
                    </div>
                    <div class="prop-field">
                        <label>URL Slug</label>
                        <input type="text" v-model="currentPage.slug">
                    </div>
                    <div style="display:flex;gap:8px;margin-top:16px">
                        <button class="btn btn-primary" @click="showPageSettings = false; triggerSave()">Save</button>
                        <button class="btn btn-secondary" @click="showPageSettings = false">Cancel</button>
                    </div>
                </div>
            </div>

            <!-- Media Picker Modal -->
            <div class="modal-overlay" :class="{ active: showMediaPicker }">
                <div class="modal-content">
                    <h2 style="font-size:20px;font-weight:700;margin-bottom:16px">Select Image</h2>
                    <div style="margin-bottom:12px">
                        <input type="file" accept="image/*" @change="uploadMediaForPicker">
                    </div>
                    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px">
                        <img v-for="m in mediaItems" :key="m.id" :src="'/storage/uploads/' + m.filename"
                            style="width:100%;height:100px;object-fit:cover;border-radius:6px;cursor:pointer;border:2px solid transparent"
                            @click="pickMedia(m)">
                    </div>
                    <button class="btn btn-secondary" style="margin-top:16px"
                        @click="showMediaPicker = false">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <script src="./assets/js/api.js"></script>
    <script src="./assets/js/app.js"></script>
</body>

</html>