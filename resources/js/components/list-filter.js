import Alpine from 'alpinejs';

/**
 * Alpine.js component for system list filtering
 * Handles client-side filtering with URL sync
 */
Alpine.data('listFilter', (initialGames, initialFilters, filterOptions, quickActionsConfig = {}) => ({
        // All games in the list
        games: initialGames,

        // Current filter selections
        filters: {
            platforms: initialFilters.platforms || [],
            genres: initialFilters.genres || [],
            gameTypes: initialFilters.gameTypes || [],
            modes: initialFilters.modes || [],
            perspectives: initialFilters.perspectives || []
        },

        // Available filter options with counts
        filterOptions: filterOptions,

        // View mode: 'grid' or 'list'
        viewMode: 'grid',

        // Mobile filter panel state
        mobileFiltersOpen: false,

        // Loading state for transitions
        isFiltering: false,

        // Quick actions: backlog/wishlist state
        backlogGameIds: new Set(quickActionsConfig.backlogGameIds || []),
        wishlistGameIds: new Set(quickActionsConfig.wishlistGameIds || []),
        quickActionsEnabled: quickActionsConfig.enabled || false,
        csrfToken: quickActionsConfig.csrfToken || '',
        username: quickActionsConfig.username || '',
        loadingStates: {},

        init() {
            // Read view mode from hash
            const hash = window.location.hash;
            if (hash.includes('view=list')) {
                this.viewMode = 'list';
            }

            // Listen for popstate (back/forward navigation)
            window.addEventListener('popstate', () => {
                this.parseUrlFilters();
            });
        },

        /**
         * Get filtered games based on current filter selections
         * Uses OR logic within filter categories, AND across categories
         */
        get filteredGames() {
            return this.games.filter(game => {
                // Platform filter (OR logic)
                if (this.filters.platforms.length > 0) {
                    const gamePlatformIds = game.platforms.map(p => p.id);
                    if (!this.filters.platforms.some(id => gamePlatformIds.includes(id))) {
                        return false;
                    }
                }

                // Genre filter (OR logic)
                if (this.filters.genres.length > 0) {
                    const gameGenreIds = game.genres.map(g => g.id);
                    if (!this.filters.genres.some(id => gameGenreIds.includes(id))) {
                        return false;
                    }
                }

                // Game type filter (OR logic)
                if (this.filters.gameTypes.length > 0) {
                    if (!this.filters.gameTypes.includes(game.game_type.id)) {
                        return false;
                    }
                }

                // Mode filter (OR logic)
                if (this.filters.modes.length > 0) {
                    const gameModeIds = game.modes.map(m => m.id);
                    if (!this.filters.modes.some(id => gameModeIds.includes(id))) {
                        return false;
                    }
                }

                // Perspective filter (OR logic)
                if (this.filters.perspectives.length > 0) {
                    const gamePerspectiveIds = game.perspectives.map(p => p.id);
                    if (!this.filters.perspectives.some(id => gamePerspectiveIds.includes(id))) {
                        return false;
                    }
                }

                return true;
            });
        },

        /**
         * Get stats for the stats bar
         */
        get stats() {
            const filtered = this.filteredGames;
            const platformCounts = {};

            // Count platforms in filtered results
            filtered.forEach(game => {
                game.platforms.forEach(p => {
                    platformCounts[p.id] = (platformCounts[p.id] || 0) + 1;
                });
            });

            return {
                total: this.games.length,
                filtered: filtered.length,
                platformCounts: platformCounts
            };
        },

        /**
         * Check if any filters are active
         */
        get hasActiveFilters() {
            return this.filters.platforms.length > 0 ||
                   this.filters.genres.length > 0 ||
                   this.filters.gameTypes.length > 0 ||
                   this.filters.modes.length > 0 ||
                   this.filters.perspectives.length > 0;
        },

        /**
         * Get active filter pills for display
         */
        get activeFilterPills() {
            const pills = [];

            this.filters.platforms.forEach(id => {
                const option = this.filterOptions.platforms.find(p => p.id === id);
                if (option) {
                    pills.push({ type: 'platforms', id, name: option.name });
                }
            });

            this.filters.genres.forEach(id => {
                const option = this.filterOptions.genres.find(g => g.id === id);
                if (option) {
                    pills.push({ type: 'genres', id, name: option.name });
                }
            });

            this.filters.gameTypes.forEach(id => {
                const option = this.filterOptions.gameTypes.find(t => t.id === id);
                if (option) {
                    pills.push({ type: 'gameTypes', id, name: option.name });
                }
            });

            this.filters.modes.forEach(id => {
                const option = this.filterOptions.modes.find(m => m.id === id);
                if (option) {
                    pills.push({ type: 'modes', id, name: option.name });
                }
            });

            this.filters.perspectives.forEach(id => {
                const option = this.filterOptions.perspectives.find(p => p.id === id);
                if (option) {
                    pills.push({ type: 'perspectives', id, name: option.name });
                }
            });

            return pills;
        },

        /**
         * Toggle a filter value on/off
         */
        toggleFilter(type, value) {
            this.isFiltering = true;

            const index = this.filters[type].indexOf(value);
            if (index > -1) {
                this.filters[type].splice(index, 1);
            } else {
                this.filters[type].push(value);
            }

            this.updateUrl();

            // Brief delay for visual feedback
            setTimeout(() => {
                this.isFiltering = false;
            }, 150);
        },

        /**
         * Remove a specific filter (used by pills)
         */
        removeFilter(type, value) {
            const index = this.filters[type].indexOf(value);
            if (index > -1) {
                this.filters[type].splice(index, 1);
                this.updateUrl();
            }
        },

        /**
         * Clear all filters
         */
        clearAllFilters() {
            this.filters.platforms = [];
            this.filters.genres = [];
            this.filters.gameTypes = [];
            this.filters.modes = [];
            this.filters.perspectives = [];
            this.updateUrl();
        },

        /**
         * Toggle view mode between grid and list
         */
        toggleViewMode() {
            this.viewMode = this.viewMode === 'grid' ? 'list' : 'grid';
            this.updateUrl();
        },

        /**
         * Set view mode directly
         */
        setViewMode(mode) {
            this.viewMode = mode;
            this.updateUrl();
        },

        /**
         * Update URL with current filter state
         */
        updateUrl() {
            const params = new URLSearchParams();

            if (this.filters.platforms.length) {
                params.set('platform', this.filters.platforms.join(','));
            }
            if (this.filters.genres.length) {
                params.set('genre', this.filters.genres.join(','));
            }
            if (this.filters.gameTypes.length) {
                params.set('game_type', this.filters.gameTypes.join(','));
            }
            if (this.filters.modes.length) {
                params.set('mode', this.filters.modes.join(','));
            }
            if (this.filters.perspectives.length) {
                params.set('perspective', this.filters.perspectives.join(','));
            }

            const queryString = params.toString();
            const hash = `#view=${this.viewMode}`;
            const newUrl = queryString
                ? `${window.location.pathname}?${queryString}${hash}`
                : `${window.location.pathname}${hash}`;

            history.pushState({}, '', newUrl);
        },

        /**
         * Parse filters from URL (for back/forward navigation)
         */
        parseUrlFilters() {
            const params = new URLSearchParams(window.location.search);

            this.filters.platforms = params.get('platform')?.split(',').map(Number).filter(Boolean) || [];
            this.filters.genres = params.get('genre')?.split(',').map(Number).filter(Boolean) || [];
            this.filters.gameTypes = params.get('game_type')?.split(',').map(Number).filter(Boolean) || [];
            this.filters.modes = params.get('mode')?.split(',').map(Number).filter(Boolean) || [];
            this.filters.perspectives = params.get('perspective')?.split(',').map(Number).filter(Boolean) || [];

            const hash = window.location.hash;
            if (hash.includes('view=list')) {
                this.viewMode = 'list';
            } else if (hash.includes('view=grid')) {
                this.viewMode = 'grid';
            }
        },

        /**
         * Check if a filter option is selected
         */
        isSelected(type, id) {
            return this.filters[type].includes(id);
        },

        /**
         * Get filtered count for a specific filter option
         * Shows how many games would match if this filter is applied
         */
        getFilteredCount(type, id) {
            // For simplicity, return the base count from filterOptions
            // A more accurate count would require filtering with that option
            const option = this.filterOptions[type]?.find(o => o.id === id);
            return option?.count || 0;
        },

        /**
         * Close mobile filters
         */
        closeMobileFilters() {
            this.mobileFiltersOpen = false;
        },

        /**
         * Open mobile filters
         */
        openMobileFilters() {
            this.mobileFiltersOpen = true;
        },

        /**
         * Check if game is in backlog
         */
        isInBacklog(gameId) {
            return this.backlogGameIds.has(gameId);
        },

        /**
         * Check if game is in wishlist
         */
        isInWishlist(gameId) {
            return this.wishlistGameIds.has(gameId);
        },

        /**
         * Check if a specific action is loading
         */
        isLoading(gameId, type) {
            return this.loadingStates[`${gameId}-${type}`] || false;
        },

        /**
         * Toggle backlog status for a game
         */
        async toggleBacklog(game) {
            if (!this.quickActionsEnabled) return;

            const loadingKey = `${game.id}-backlog`;
            if (this.loadingStates[loadingKey]) return;

            this.loadingStates[loadingKey] = true;

            const isInBacklog = this.backlogGameIds.has(game.id);
            const url = isInBacklog
                ? `/u/${this.username}/backlog/games/${game.id}`
                : `/u/${this.username}/backlog/games`;

            try {
                const formData = new FormData();
                formData.append('_token', this.csrfToken);

                if (!isInBacklog) {
                    formData.append('game_uuid', game.uuid);
                } else {
                    formData.append('_method', 'DELETE');
                }

                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: formData,
                });

                const data = await response.json();
                if (data.success || data.info) {
                    if (isInBacklog) {
                        this.backlogGameIds.delete(game.id);
                    } else {
                        this.backlogGameIds.add(game.id);
                    }
                }
            } catch (error) {
                console.error('Error toggling backlog:', error);
            } finally {
                this.loadingStates[loadingKey] = false;
            }
        },

        /**
         * Toggle wishlist status for a game
         */
        async toggleWishlist(game) {
            if (!this.quickActionsEnabled) return;

            const loadingKey = `${game.id}-wishlist`;
            if (this.loadingStates[loadingKey]) return;

            this.loadingStates[loadingKey] = true;

            const isInWishlist = this.wishlistGameIds.has(game.id);
            const url = isInWishlist
                ? `/u/${this.username}/wishlist/games/${game.id}`
                : `/u/${this.username}/wishlist/games`;

            try {
                const formData = new FormData();
                formData.append('_token', this.csrfToken);

                if (!isInWishlist) {
                    formData.append('game_uuid', game.uuid);
                } else {
                    formData.append('_method', 'DELETE');
                }

                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: formData,
                });

                const data = await response.json();
                if (data.success || data.info) {
                    if (isInWishlist) {
                        this.wishlistGameIds.delete(game.id);
                    } else {
                        this.wishlistGameIds.add(game.id);
                    }
                }
            } catch (error) {
                console.error('Error toggling wishlist:', error);
            } finally {
                this.loadingStates[loadingKey] = false;
            }
        }
    }));
