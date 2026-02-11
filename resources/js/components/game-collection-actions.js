import Alpine from 'alpinejs';

Alpine.data('gameCollectionActions', (gameId, gameUuid) => ({
    gameId,
    gameUuid,
    userGameId: null,
    currentStatus: null,
    isWishlisted: false,
    actionLoading: false,
    showPopover: false,
    loaded: false,

    async init() {
        // Fetch current status for this game
        try {
            const response = await fetch(`/api/user-games/status/${this.gameId}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            });
            if (response.ok) {
                const data = await response.json();
                if (data.user_game) {
                    this.userGameId = data.user_game.id;
                    this.currentStatus = data.user_game.status;
                    this.isWishlisted = data.user_game.is_wishlisted;
                }
            }
        } catch (err) {
            // Silently fail - icons will show default state
        }
        this.loaded = true;
    },

    async quickAction(action) {
        if (this.actionLoading) return;
        this.actionLoading = true;

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

        try {
            if (action === 'wishlist') {
                await this.toggleWishlist(csrfToken);
            } else {
                await this.setStatus(action, csrfToken);
            }
        } catch (err) {
            console.error('Collection action failed:', err);
        } finally {
            this.actionLoading = false;
        }
    },

    async setStatus(status, csrfToken) {
        if (!this.userGameId) {
            // Create new entry
            const response = await fetch('/api/user-games', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    game_id: this.gameId,
                    status: status,
                }),
            });
            const data = await response.json();
            if (data.user_game) {
                this.userGameId = data.user_game.id;
                this.currentStatus = data.user_game.status;
                this.isWishlisted = data.user_game.is_wishlisted;
            }
        } else {
            // Update existing
            const response = await fetch(`/api/user-games/${this.userGameId}`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    status: this.currentStatus === status ? null : status,
                }),
            });
            const data = await response.json();
            if (data.user_game) {
                this.currentStatus = data.user_game.status;
            }
        }
    },

    async toggleWishlist(csrfToken) {
        if (!this.userGameId) {
            // Create new entry with wishlist
            const response = await fetch('/api/user-games', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    game_id: this.gameId,
                    is_wishlisted: true,
                }),
            });
            const data = await response.json();
            if (data.user_game) {
                this.userGameId = data.user_game.id;
                this.currentStatus = data.user_game.status;
                this.isWishlisted = data.user_game.is_wishlisted;
            }
        } else {
            const response = await fetch(`/api/user-games/${this.userGameId}`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    is_wishlisted: !this.isWishlisted,
                }),
            });
            const data = await response.json();
            if (data.user_game) {
                this.isWishlisted = data.user_game.is_wishlisted;
            }
        }
    },
}));