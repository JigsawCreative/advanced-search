# Advanced Search (ESAS) Plugin

**Contributors:** Neil Williams  
**Tags:** tiles, filtering, live search, pagination, grouped products, WooCommerce, API  
**Requires at least:** 6.0  
**Tested up to:** 6.5  
**Requires PHP:** 7.4  
**License:** GPLv2 or later  
**Version:** 1.0.0  

---

## Description

Advanced Search (ESAS) is a WordPress plugin that enhances product grids with dynamic filtering, live search, pagination, and grouped product support.  

It consists of two main parts:

1. **Backend API** – provides a custom REST endpoint at `/custom/v1/es-advanced-search/` returning JSON data for tiles. Supports optional filtering by `category` or `effect`. Uses caching via WordPress transients for performance. Automatically clears cache when batch posts are saved, trashed, or deleted.

2. **Frontend JS** – the `TileFilter` class handles live filtering, search, pagination, grouped products, and URL state management. Tiles update in real-time with counts for grouped products.

**Key Features:**

- Multi-filter group support with live search  
- Pagination that adapts to device width  
- Supports grouped products / child batch tiles  
- Maintains filter and pagination state in URL hash  
- Reset button fully resets filters, pagination, and tile counts  
- Backend API caching ensures fast responses and reduces server load  
- WooCommerce and ACF integrated for batch/product data  

---

## Installation

1. Upload the `advanced-search` plugin folder to `/wp-content/plugins/`.  
2. Activate the plugin via the WordPress **Plugins** menu.  
3. Ensure your product grid page includes the required HTML structure and classes for filters and tiles.  

---

## Usage

- Use the `[tilefilter_grid]` shortcode (replace with your actual shortcode) to display a product tile grid.  
- Frontend filter buttons require `.control` class with `data-toggle` and `data-filter-group`.  
- Live search input requires `.live-filter`.  
- Reset button requires `#reset-filters`.  

The frontend automatically initializes the `TileFilter` JS class. Filters, pagination, and grouped product counts update dynamically.  

---

## Backend API

### ESAS_Batches_API

Provides a REST API endpoint for fetching batch products.

- **Endpoint:** `/custom/v1/es-advanced-search/`  
- **Method:** `GET`  
- **Optional query parameters:**  
  - `category` – filter by category  
  - `effect` – filter by effect  

**Caching:**

- API results are stored in a transient (`esas_products_json`) for speed.  
- Cache is cleared automatically when relevant batch posts are saved, trashed, or deleted.  
- Development: 10-minute cache; Production: 30-day cache.  

**Data Returned (per batch/product):**

- `id`, `title`, `price`, `quantity`, `image`  
- `effects`, `colour`, `finish`, `thickness`, `sizes`  
- `factory`, `menu_order`  

All string fields are normalized to lowercase for frontend filtering.

---

### Initialization & Hooks

- `init()` – registers REST routes and hooks into `save_post_batch`, `trashed_post`, `deleted_post` to clear cache.  
- `get_all_batches(WP_REST_Request $request)` – main callback, returns cached or freshly built JSON.  
- `build_batches($category = null, $effect = null)` – prepares batch data for API.  
- `get_query_args($category, $effect)` – constructs WP_Query arguments.  
- `get_batch_data($batch_id)` – fetches product + ACF data, prepares normalized JSON.  

---

## Frontend JS (`TileFilter`)

- Initializes on page load.  
- Filters and paginates tiles dynamically based on `activeFilters` and `activeItems`.  
- Live text search updates filter state in real-time.  
- Child tiles of grouped products are automatically shown/hidden.  
- URL hash reflects current filter and pagination state for sharing/bookmarking.  
- Reset button clears filters and search, updates all tile visibility and counts.  

---

## Dependencies

- Vanilla JavaScript (ES6 compatible)  
- WooCommerce + ACF (for batch/product data)  

---

## Changelog

### 1.0.0
- Initial release with backend API, caching, dynamic tile filtering, live search, grouped product support, and pagination.

---

## License

GPLv2 or later
