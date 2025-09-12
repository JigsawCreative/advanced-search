class TileFilter {

    /** ---------------------------
     * Constructor & Initialization
     * --------------------------- */
    constructor() {

        // Initialise full state with safe defaults
        this.state = {
            activeFilters: {},
            pagination: {
                page: 1,
                perPage: 33,
                pages: {}
            },
            activeItems: [],   // after filters applied
            visibleItems: [],   // after pagination applied
            parentVisibleItems: []
        };

        // Array to hold tile data from reference JSON file
        this.tiles = [];

        // Activate methods required on page load
        this.init();

    }

    // Functions to initialise on DOMContentLoaded
    init = () => {
        this.loadBatches();
        this.activateFilters();
        this.liveTextSearch();
    }

    async loadBatches() {
        // Build endpoint URL dynamically
        const endpoint = ESAS.category 
            ? `${ESAS.endpoint}?category=${ESAS.category}`
            : ESAS.endpoint;

        try {
            const res = await fetch(endpoint, {
                method: 'GET',
                headers: {
                    'X-WP-Nonce': ESAS.nonce
                }
            });

            if (!res.ok) throw new Error('Network response was not ok');

            const data = await res.json();
            console.log(data);

            // Save returned batch data to class variable
            this.tiles = data;

            // Check initial state of filters
            this.loadInitialState();

        } catch (err) {
            console.error('Failed to load products:', err);
            return [];
        }
    } 

    loadInitialState = () => {

        // Build initial activeFilters from URL
        this.deserializeHash(); // no longer overwrites activeFilters

        // Check if there are active filters
        const isActive = Object.keys(this.state.activeFilters).length > 0 ? true : false;

        if(isActive) {

            // Activate active filters in panel
            for (const key in this.state.activeFilters) {
                    
                if (Object.prototype.hasOwnProperty.call(this.state.activeFilters, key)) {

                    // Extract active values for current filter group
                    const values = this.state.activeFilters[key];

                    // Loop over active values
                    values.forEach(value => {

                        if(key !== 'textsearch') {
                            document.querySelector(`[data-toggle="${value}"]`).classList.add('mixitup-control-active');
        
                        } 

                    })
                }
            }

            // Activate Reset Filters button
            this.setupResetButton();

        }

        // Update results regardless of whether there are active filters
        this.displayResults(true);

    }

    /** ---------------------------
     * UI Setup / Event Binding
     * --------------------------- */
    activateFilters = () => {

        // Handle filter button clicks
        document.querySelectorAll('#accordion .control').forEach(button => {

            button.addEventListener('click', () => {
        
                // Toggle the button's selected state
                button.classList.toggle('mixitup-control-active');
        
                // Extract value of currently selected filter
                const value = button.getAttribute('data-toggle');
        
                // Extract value of current filter group
                const group = button.closest('.control-group').getAttribute('data-filter-group');
        
                // Initialize array if group doesn't exist in activeFilters
                if (!this.state.activeFilters[group]) {
                    this.state.activeFilters[group] = [];
                }
        
                // Add or remove the filter value in the activeFilters object
                if (button.classList.contains('mixitup-control-active')) {
        
                    // If filter button is currently active, add value to the state object
                    this.state.activeFilters[group].push(value);
        
                } else {
        
                    // Check for value in current filter group in state object
                    const index = this.state.activeFilters[group].indexOf(value);
        
                    // If value is found, remove it from the filter group
                    if (index > -1) {
        
                        this.state.activeFilters[group].splice(index, 1);
        
                    }
        
                    // Check if filter group is empty so remove it from state object to ensure selectors are created correctly
                    // This logic controls the correct return of results after filters are unchecked but not when the rest button is clicked
                    if(Object.values(this.state.activeFilters[group]).length === 0) {
        
                        // Check the number of currently active properties
                        const stateProps = Object.keys(this.state.activeFilters).length;
        
                        // Check if there are other active properties on state object
                        if(stateProps > 1) {
        
                            // Delete empty property if there are
                            delete this.state.activeFilters[group];
        
                        } else if(stateProps <= 1) {
        
                            // If this is the only active property, reset activeFilters to an empty array
                            this.state.activeFilters = [];
        
                        }
                        
                    }
        
                }

                // show hide tiles based on results
                this.displayResults(true);

                // Update hash based on selection
                this.setHash();

            });
        
        });
    
    }

    liveTextSearch = () => {

        // Save search input to global variable
        this.filterInput = document.querySelector(".live-filter");
    
        // Debounce function to optimize performance
        const debounce = (func, delay) => {

            let timeout;

            return (...args) => {

                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(this, args), delay);

            };

        };
    
        // Main filtering logic
        const filterList = () => {

            // User search query lowercased and with whitespace trimmed
            const query = this.filterInput.value.trim().toLowerCase();

            if (query) {

                // Add search value to state
                this.state.activeFilters['textsearch'] = [query];
                
            } else {

                // Remove key if query is empty
                delete this.state.activeFilters['textsearch']; 
            }

            // Update hash based on selection
            this.setHash();

            // Filter tiles on page
            this.displayResults(true);

        };

        // Prevent Enter key from submitting the form
        this.filterInput.addEventListener("keydown", (event) => {
            if (event.key === "Enter") {
                event.preventDefault();
            }
        });
    
        // Attach debounced event listener
        this.filterInput.addEventListener("input", debounce(filterList, 200));

    }; 

    setupResetButton = () => {
        const resetBtn = document.getElementById('reset-filters');
        if (!resetBtn) return;

        // Attach click listener once
        resetBtn.addEventListener('click', this.reset);

        // Toggle "active" class based on whether filters are applied
        if (Object.keys(this.state.activeFilters).length > 0) {
            resetBtn.classList.add('active');
        } else {
            resetBtn.classList.remove('active');
        }
    };

    reset = (event) => {
        if (event) event.preventDefault();

        // Clear search input
        if (this.filterInput) this.filterInput.value = "";

        // Remove active classes from all filter buttons
        document.querySelectorAll('.ui-accordion button.mixitup-control-active')
            .forEach(btn => btn.classList.remove('mixitup-control-active'));

        // Reset state
        this.state.activeFilters = {};
        this.state.pagination.page = 1;

        // Update URL hash
        this.setHash();

        // Show all tiles
        document.querySelectorAll('.product-list > li').forEach(tile => {
            tile.classList.remove('hide', 'displaynoneclass');
            tile.classList.add('show');
        });

        // Refresh results and counts
        this.displayResults(true);
        this.countResults();

        // Update reset button state
        this.setupResetButton();
    };

    /** ---------------------------
     * Filtering Logic
     * --------------------------- */
    filterTiles = () => {
        if (!Object.keys(this.state.activeFilters).length) {
            return [...this.tiles].sort((a,b) => a.menu_order - b.menu_order);
        }

        const filteredParents = this.tiles.filter(tile =>
            Object.entries(this.state.activeFilters).every(([key, value]) => {
                if (!value?.length) return true;

                if (key === 'textsearch') {
                    const text = Object.values(tile).join(" ").toLowerCase();
                    const valueWords = value[0].split(/\s+/);
                    return valueWords.every(word => text.includes(word));
                } else if (key === 'decor') {
                    return tile[key].includes('bookmatch');
                } else {
                    return Array.isArray(tile[key])
                        ? tile[key].some(item => value.includes(item.toLowerCase()))
                        : value.includes(tile[key].toLowerCase());
                }
            })
        );

        // Include all children of filtered parents
        const filteredTiles = [];
        filteredParents.forEach(parent => {
            filteredTiles.push(parent);
            if (parent.children && parent.children.length) {
                filteredTiles.push(...parent.children);
            }
        });

        return filteredTiles.sort((a,b) => a.menu_order - b.menu_order);
    };

    getDOMIds() {

        const allDomIds = this.activeIDs();

        return this.state.activeItems.filter(item =>
            allDomIds.includes(item.id)
        );

    }

    setVisibleParentItems() {
    
        // Loop over all top-level parent <li> elements
        return Array.from(document.querySelectorAll('.product-list > li')).filter(parentEl => {

            const parentId = parseInt(parentEl.getAttribute('data-postid-order') || parentEl.getAttribute('data-id'), 10);

            // Check if parent itself matches any active/visible items
            const parentMatches = this.state.visibleItems.some(item => item.id === parentId);

            // Check if any child group-option matches active/visible items
            const childrenMatch = Array.from(parentEl.querySelectorAll('.group-option[data-id]')).some(childEl => {
                const childId = parseInt(childEl.getAttribute('data-id'), 10);
                return this.state.visibleItems.some(item => item.id === childId);
            });

            // Keep parent if parent itself OR any child matches
            return parentMatches || childrenMatch;

        }).map(el => {
            const id = parseInt(el.getAttribute('data-postid-order') || el.getAttribute('data-id'), 10);
            // Return the full API item object if available, otherwise a minimal object with just the ID
            return this.state.activeItems.find(item => item.id === id) || { id };
        });

    }

    /**
     * Collect unique ids of batches (parent and child) that are present in the DOM
     * @return array 
     */
    activeIDs() {

        // Collect IDs from single-item parents
        const parentIds = Array.from(document.querySelectorAll('.product-list > li[data-id]'))
            .map(el => parseInt(el.getAttribute('data-id'), 10));

        // Collect IDs from group-option children
        const childIds = Array.from(document.querySelectorAll('.product-list .group-option[data-id]'))
            .map(el => parseInt(el.getAttribute('data-id'), 10));

        // Merge & deduplicate
        return [...new Set([...parentIds, ...childIds])];

    }

     /** ---------------------------
     * Display & DOM Updates
     * --------------------------- */

    /**
     * Display results after filtering, pagination and DOM sync
     * 
     * @param {boolean} resetPage - Whether to reset pagination to the first page
     */
    displayResults = (resetPage = false) => {

        // All items from API that match filters (parents + children)
        this.state.activeItems = this.filterTiles(); 

        // Get all IDs that exist in the DOM
        this.state.visibleItems = this.getDOMIds();

        // Determine which parents should be visible
        this.state.parentVisibleItems = this.setVisibleParentItems();

        // Split parentVisibleItems into pages
        this.setPagination(resetPage);

        // Update pagination buttons
        this.addPaginationButtons();

        // Show tiles for current page
        this.showHideTiles();

        // Update total count
        this.countResults();
    };
    
    /**
     * Show and hide tiles (both parent products and child batch options)
     * based on pagination state and active filters.
     * 
     * Parents:
     * - Only visible if included in the current page.
     * 
     * Children:
     * - Visible if their parent is visible.
     * 
     * Single products:
     * - Treated like parents.
     * 
     * @return {void}
     */
    showHideTiles = () => {

        const currentPageParents = this.getCurrentPageParents();

        const tiles = document.querySelectorAll('.product-list > li');

        tiles.forEach(tile => {

            const parentId = parseInt(tile.getAttribute('data-postid-order') || tile.getAttribute('data-id'), 10);
            const isParentVisible = currentPageParents.some(item => item.id === parentId);

            if (!isParentVisible) {
                this.hideTile(tile);

                // skip children
                return; 
            }

            const groupOptions = tile.querySelectorAll('.group-option[data-id]');
            if (groupOptions.length > 0) {
                this.updateGroupOptions(tile, groupOptions);
            } else {

                this.showTile(tile);
                this.updateOptions(tile);
            }
        });
    };

    /** Display helper functions */

    /**
     * Returns the parent items on the current pagination page.
     * @return {Array} Array of parent items (objects with IDs)
     */
    getCurrentPageParents = () => {
        const currentPageNum = this.state.pagination.page;
        const currentPageKey = `page${currentPageNum}`;
        return this.state.pagination.pages[currentPageKey] || [];
    };

    /**
    * Hides a tile element completely.
    * Adds 'hide' and 'displaynoneclass', removes 'show'.
    * @param {HTMLElement} tile
    */
    hideTile = (tile) => {
        tile.classList.remove('show');
        tile.classList.add('hide', 'displaynoneclass');
    };

    /**
     * Shows a tile element.
     * Adds 'show', removes 'hide' and 'displaynoneclass'.
     * @param {HTMLElement} tile
     */
    showTile = (tile) => {
        tile.classList.remove('hide', 'displaynoneclass');
        tile.classList.add('show');
    };

    /**
     * Updates child options of a grouped product.
     * Shows/hides children based on `visibleItems` and parent visibility.
     * Updates parent tile visibility and option counts.
     * @param {HTMLElement} tile - Parent tile element
     * @param {NodeListOf<HTMLElement>} groupOptions - Child options
     */
    updateGroupOptions = (tile, groupOptions) => {
        let hasVisibleChild = false;

        groupOptions.forEach(opt => {
            const childId = parseInt(opt.getAttribute('data-id'), 10);
            const childVisible = this.state.visibleItems.some(item => item.id === childId);

            opt.classList.toggle('hide', !childVisible);
            opt.classList.toggle('displaynoneclass', !childVisible);
            opt.classList.toggle('show', childVisible);

            if (childVisible) hasVisibleChild = true;
        });

        hasVisibleChild ? this.showTile(tile) : this.hideTile(tile);

        if (hasVisibleChild) this.updateOptions(tile);
    };

    /**
     * Updates the display of available options and size/finish counts
     * for a given parent tile element.
     * Counts only children that are currently visible.
     * Works for single products as well.
     * 
     * @param {HTMLElement} tile - The parent tile element
     */
    updateOptions = (tile) => {
        if (!tile) return;

        // For grouped products: count only children that are currently visible
        const visibleChildren = tile.querySelectorAll('.modal-grouped-products .group-option.show');
        const optionsCount = visibleChildren.length || 0;

        const availableOptions = tile.querySelector('.options-count');
        const sizeFinishOptions = tile.querySelector('.size-finish-count');

        // Update DOM text
        if (availableOptions) {
            availableOptions.textContent = optionsCount === 1
                ? '1 option available'
                : `${optionsCount} options available`;
        }

        if (sizeFinishOptions) {
            sizeFinishOptions.textContent = optionsCount === 1
                ? '1 size & finish available'
                : `${optionsCount} sizes & finishes available`;
        }
    };

    /**
     * Updates the displayed text showing the range of visible parent items.
     * - Calculates start and end indices for the current page.
     * - Uses total number of parent items to display: "X to Y of Z".
     * - Updates all elements with class '.mixitup-page-stats' with this text.
     */
    countResults = () => {
        const { page, perPage } = this.state.pagination;
        const totalParents = this.state.parentVisibleItems.length;

        const startIndex = (page - 1) * perPage + 1;
        const endIndex = Math.min(page * perPage, totalParents);

        const displayText = totalParents > 0
            ? `${startIndex} to ${endIndex} of ${totalParents}`
            : `0 items`;

        document.querySelectorAll('.mixitup-page-stats')
            .forEach(resultBox => resultBox.textContent = displayText);
    };

    /** ---------------------------
     * Pagination
     * --------------------------- */

    /**
     * Set up pagination for visible parents
     * @param {boolean} resetPage
     */
    setPagination(resetPage = false) {

        // Items per page (responsive)
        const itemsNum = this.itemsPerPage();

        // Paginate only parent items
        this.state.pagination.pages = this.paginate(this.state.parentVisibleItems, itemsNum);

        const totalPages = Object.keys(this.state.pagination.pages).length;

        // Reset page if out of range
        if (resetPage || this.state.pagination.page > totalPages) {
            this.state.pagination.page = 1;
        }

        // Update URL hash
        this.setHash();

    }

    itemsPerPage() {

        // Device width
        const width = window.innerWidth;

        let itemsPerPage = "";

        // Determine device type and set number of items
        if (width <= 767) {
            itemsPerPage = 16;
        } else if (width <= 1024) {
            itemsPerPage = 24;
        } else {
            itemsPerPage = 33;
        }

        return itemsPerPage;

    }

    paginate(array, perPage = 5) {

        return array.reduce((acc, item, index) => {
            // Work out which page this item belongs to
            const pageNum = Math.floor(index / perPage) + 1;

            // Create the key name, e.g. "page1", "page2", ...
            const key = `page${pageNum}`;

            // If this page doesn’t exist yet, initialise it as an empty array
            acc[key] = acc[key] || [];

            // Push the current item into the right page array
            acc[key].push(item);

            // Return the accumulator for the next iteration
            return acc;
        }, {}); // Start with an empty object {}

    }

    addPaginationButtons = () => {

        const paginationContainer = document.querySelector('.mixitup-page-list');
        if (!paginationContainer) return;

        // Clear existing buttons except prev/next if desired
        paginationContainer.innerHTML = '';

        const totalPages = Object.keys(this.state.pagination.pages).length;
        const currentPage = this.state.pagination.page;

        // Helper to create button
        const createButton = (label, page, extraClasses = '') => {

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.textContent = label;
            btn.className = `mixitup-control ${extraClasses}`.trim();
            btn.dataset.page = page;

            btn.addEventListener('click', () => {

                let newPage = page;

                if (page === 'prev') {
                    newPage = Math.max(currentPage - 1, 1);
                } else if (page === 'next') {
                    newPage = Math.min(currentPage + 1, totalPages);
                } else {
                    newPage = parseInt(page, 10);
                }

                this.state.pagination.page = newPage;

                // Show correct tiles for this page
                this.displayResults();

                // Update active button classes
                this.updatePaginationActiveState();

            });

            return btn;
        };

        // Create prev button
        paginationContainer.appendChild(
            createButton('«', 'prev', 'mixitup-control-prev')
        );

        // Create page number buttons
        for (let i = 1; i <= totalPages; i++) {
            const extraClass = i === currentPage ? 'mixitup-control-active' : '';
            paginationContainer.appendChild(createButton(i, i, extraClass));
        }

        // Create next button
        paginationContainer.appendChild(
            createButton('»', 'next', 'mixitup-control-next')
        );
    };

    /**
     * Updates the pagination buttons to reflect the current active page.
     * - Finds all buttons in the pagination container.
     * - Toggles the 'mixitup-control-active' class based on whether
     *   the button's page number matches the current pagination page.
     */
    updatePaginationActiveState = () => {
        const paginationContainer = document.querySelector('.mixitup-page-list');
        const currentPage = this.state.pagination.page;

        paginationContainer.querySelectorAll('.mixitup-control').forEach(btn => {
            const page = btn.dataset.page;
            if (!isNaN(parseInt(page))) {
                btn.classList.toggle('mixitup-control-active', parseInt(page) === currentPage);
            }
        });
    };

    /** ---------------------------
     * State & URL Hash Helpers
     * --------------------------- */
    serializeUiState = () => {
        let output = '';

        // Serialize active filters
        for (const key in this.state.activeFilters) {
            const values = this.state.activeFilters[key];

            if (!values?.length) continue; // skip empty or undefined

            output += `${key}=${values.join(',')}&`;
        }

        // Serialize pagination page
        if (this.state.pagination?.page != null) {
            output += `page=${this.state.pagination.page}&`;
        }

        // Remove trailing '&'
        output = output.replace(/&$/g, '');

        return output;
    };

    deserializeHash = () => {
        
        // Ensure state objects exist
        this.state = this.state || {};
        this.state.activeFilters = this.state.activeFilters || {};
        this.state.pagination = this.state.pagination || { page: 1, perPage: 33 };

        // Extract hash string
        const hash = window.location.hash.replace(/^#/, '');
        if (!hash) return;

        // Split into key=value pairs
        const groups = hash.split('&');

        groups.forEach((group) => {
            const [key, value] = group.split('=').map(decodeURIComponent);
            if (!key || value === undefined) return;

            if (key === 'page') {
                // Update existing pagination.page
                this.state.pagination.page = parseInt(value, 10) || 1;
            } else if (key === 'textsearch') {
                // Update existing activeFilters
                this.state.activeFilters[key] = [value];

                // Set input value if exists
                if (this.filterInput) this.filterInput.value = value;
            } else {
                this.state.activeFilters[key] = value.split(',');
            }
        });
    };

    setHash = () => {

        // Serialized string of current ui state
        const currentState = this.serializeUiState();
        
        // Activate/Deactivate Reset Filters button functionality
        this.setupResetButton();
    
        // Create a URL hash string by serializing the uiState object
        const newHash = '#' + currentState;

        // If current state is empty but a hash exists in the url, remove it. All tiles are to be shown
        if (newHash === '#' && window.location.href.indexOf('#') > -1) {
    
            history.pushState(null, document.title, window.location.pathname);
    
        } else if (newHash !== '#' && newHash !== window.location.hash) {
            
            // Else if there are values in the new hash and it is different to the url hash fragment, update it
            history.pushState(null, document.title, window.location.pathname + newHash);
    
        }
    }

}

// Initialise class on page load
document.addEventListener('DOMContentLoaded', () => new TileFilter());