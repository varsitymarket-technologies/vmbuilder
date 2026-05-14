# Website Builder - Design Spec

## Overview

A PHP-based website builder combining drag-and-drop visual editing with pre-built templates. Targets non-technical users and small business owners. No authentication — open access.

## Tech Stack

- **Backend:** PHP 8+ with SQLite
- **Frontend:** Vue.js (canvas/editor), Alpine.js (panels/modals), Tailwind CSS
- **No build step required** — CDN-loaded dependencies
- **Published output:** Static HTML + Tailwind CDN

## Architecture

```
vm.builder/
├── public/                  # Web root
│   ├── index.php            # Entry point - serves the SPA
│   ├── api.php              # API router for all backend calls
│   ├── assets/
│   │   ├── css/             # Tailwind output, editor styles
│   │   ├── js/              # Vue app, Alpine components, drag-drop engine
│   │   └── img/             # Static assets (icons, default images)
│   └── published/           # Published sites output (static HTML per site)
├── src/
│   ├── Database.php         # SQLite connection & migrations
│   ├── SiteManager.php      # CRUD for sites & pages
│   ├── ComponentRegistry.php # Available components & their defaults
│   ├── TemplateEngine.php   # Pre-built templates loader
│   ├── MediaManager.php     # Image upload, resize, listing
│   ├── Publisher.php        # Renders site JSON to static HTML
│   └── FormHandler.php      # Contact form submission & email
├── templates/               # Pre-built site templates (JSON)
├── storage/
│   ├── database.sqlite      # SQLite database
│   └── uploads/             # User-uploaded media
└── config.php               # App configuration
```

**Data flow:** Editor (Vue.js) <-> API (PHP) <-> SQLite. Publishing renders JSON to static HTML files.

## Data Model

### Sites
| Column | Type | Description |
|--------|------|-------------|
| id | INTEGER PRIMARY KEY | Auto-increment |
| name | TEXT | Site display name |
| slug | TEXT UNIQUE | URL-safe identifier |
| settings | TEXT (JSON) | Colors, fonts, favicon, SEO defaults |
| created_at | DATETIME | |
| updated_at | DATETIME | |

### Pages
| Column | Type | Description |
|--------|------|-------------|
| id | INTEGER PRIMARY KEY | Auto-increment |
| site_id | INTEGER FK | References sites.id |
| name | TEXT | Page name (e.g. "Home") |
| slug | TEXT | URL path segment |
| components | TEXT (JSON) | Ordered array of component data |
| seo | TEXT (JSON) | Title, description, meta tags |
| sort_order | INTEGER | Page ordering |
| created_at | DATETIME | |
| updated_at | DATETIME | |

### Media
| Column | Type | Description |
|--------|------|-------------|
| id | INTEGER PRIMARY KEY | Auto-increment |
| filename | TEXT | Stored filename |
| original_name | TEXT | Uploaded filename |
| mime_type | TEXT | |
| file_size | INTEGER | |
| created_at | DATETIME | |

### Form Submissions
| Column | Type | Description |
|--------|------|-------------|
| id | INTEGER PRIMARY KEY | Auto-increment |
| site_id | INTEGER FK | |
| page_id | INTEGER FK | |
| data | TEXT (JSON) | Form field values |
| created_at | DATETIME | |

### Component JSON Format

```json
[
  {
    "id": "abc123",
    "type": "hero",
    "props": {
      "heading": "Welcome",
      "subheading": "Build something great",
      "backgroundImage": "/uploads/hero.jpg",
      "ctaText": "Get Started",
      "ctaUrl": "#contact"
    }
  }
]
```

Each component has: `id` (unique), `type` (string), `props` (object), optional `children` (array of components for layout containers).

## Component System

### Layout
- **Section** — Full-width container, background color/image, padding
- **Columns** — 2/3/4 column grid, adjustable ratios
- **Spacer** — Adjustable vertical spacing

### Content
- **Heading** — H1-H6, font/size/color/alignment
- **Text** — Rich text (bold, italic, links, lists)
- **Image** — Single image, alt text, sizing, optional link
- **Video** — YouTube/Vimeo embed via URL
- **Button** — Text, link, style (solid/outline), color

### Business
- **Hero** — Full-width banner, heading, subtext, CTA, background image
- **Features** — Icon + title + description cards (3-4 columns)
- **Testimonials** — Carousel or grid of quotes
- **Pricing** — 2-3 tier pricing table with feature lists
- **Contact Form** — Configurable fields, email on submit
- **Map** — Google Maps embed via address/coordinates
- **Gallery** — Image grid with lightbox

### Global
- **Navbar** — Logo + nav links (auto-generated from pages)
- **Footer** — Configurable columns, text, links, social icons

Each component defines: default props, editor schema (field types for the properties panel), render template (HTML/Tailwind output).

## Editor UI

### Three-Zone Layout

**Left Sidebar — Component Panel:**
- Categorized draggable components
- Page list, template browser, media library tabs

**Center — Canvas:**
- Live preview, components rendered top to bottom
- Hover: blue outline highlight
- Click: selects component (grab handle, move up/down, duplicate, delete)
- Drag-and-drop reordering with blue drop-zone indicators
- Responsive preview: desktop (100%) / tablet (768px) / mobile (375px)

**Right Sidebar — Properties Panel:**
- Shows editable props for selected component
- Field types: text input, textarea, rich text, color picker, image selector, toggle, select, number
- Two-way binding: changes reflect immediately on canvas

**Top Bar:**
- Editable site name
- Page selector dropdown + add page
- SEO settings button (modal)
- Preview button (new tab)
- Publish button
- Auto-save indicator ("Saved" / "Saving...")

### Auto-Save
Debounced save via API every 2 seconds of inactivity. Publish is always explicit.

## Templates

Three pre-built templates:

1. **Business Landing Page** — Hero, features grid, testimonials, pricing, contact form, footer. Blue/gray scheme.
2. **Portfolio** — Navbar, hero image, gallery, about section, contact form, footer. Black/white + accent.
3. **Restaurant / Local Business** — Hero, menu/services columns, map, hours/contact, gallery, footer. Warm earthy tones.

Templates stored as JSON in `templates/`. Selecting one creates a new site pre-populated with that content.

## Publishing Pipeline

1. User clicks "Publish" -> API call to Publisher.php
2. Load site settings + all pages from SQLite
3. For each page, iterate components and render HTML with Tailwind classes
4. Wrap in full HTML document: `<head>` with SEO meta, Tailwind CDN, favicon; nav and footer
5. Output static files to `public/published/{site-slug}/` (index.html, about.html, etc.)
6. Copy referenced uploads alongside
7. Return published URL to editor

### Contact Form Handling
Published forms POST to `api.php?action=form_submit`. PHP validates, stores in submissions table, sends email via `mail()`.

## API Endpoints

All via `api.php` with `action` parameter:

| Action | Method | Description |
|--------|--------|-------------|
| `sites_list` | GET | List all sites |
| `sites_create` | POST | Create site (optionally from template) |
| `sites_update` | PUT | Update site settings |
| `sites_delete` | DELETE | Delete site and its pages |
| `pages_list` | GET | List pages for a site |
| `pages_get` | GET | Get single page with components |
| `pages_save` | POST/PUT | Create or update page + components |
| `pages_delete` | DELETE | Delete a page |
| `pages_reorder` | PUT | Update sort_order for pages |
| `media_list` | GET | List uploaded media |
| `media_upload` | POST | Upload image file |
| `media_delete` | DELETE | Delete media file |
| `publish` | POST | Publish site to static HTML |
| `form_submit` | POST | Handle contact form submission |
| `templates_list` | GET | List available templates |

## Key Decisions

- **No authentication** — open access, single-user focus
- **JSON components** — flexible schema, no migrations for new component types
- **Static publishing** — published sites need no PHP, fast and portable
- **CDN dependencies** — no build step, Vue/Alpine/Tailwind loaded via CDN
- **SQLite** — zero-config database, single file deployment
- **Auto-save** — no data loss, explicit publish action