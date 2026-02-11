import Alpine from 'alpinejs';

Alpine.data('userGameEditModal', () => ({
    open: false,
    saving: false,
    deleting: false,
    confirmingDelete: false,
    notification: null,

    // Game data (display)
    gameId: null,
    gameName: '',
    gameCover: '',
    gameSlug: '',

    // Editable fields
    userGameId: null,
    status: null,
    isWishlisted: false,
    timePlayed: null,
    rating: null,

    // Reference to the card element that opened the modal
    cardElement: null,

    // Current filter context (to know if card should be removed after status change)
    statusFilter: '',
    wishlistFilter: false,

    openModal(data) {
        this.gameId = data.gameId;
        this.gameName = data.gameName;
        this.gameCover = data.gameCover;
        this.gameSlug = data.gameSlug;
        this.userGameId = data.userGameId;
        this.status = data.status;
        this.isWishlisted = data.isWishlisted;
        this.timePlayed = data.timePlayed;
        this.rating = data.rating;
        this.cardElement = data.cardElement;
        this.statusFilter = data.statusFilter || '';
        this.wishlistFilter = data.wishlistFilter || false;
        this.confirmingDelete = false;
        this.notification = null;
        this.open = true;
        document.body.style.overflow = 'hidden';
    },

    closeModal() {
        this.open = false;
        this.confirmingDelete = false;
        document.body.style.overflow = '';
    },

    async setStatus(newStatus) {
        if (this.saving) return;
        // Toggle off if same status clicked
        const targetStatus = this.status === newStatus ? null : newStatus;
        this.saving = true;

        try {
            const response = await this._patch({status: targetStatus});
            if (response.success) {
                this.status = response.user_game.status;
                this._updateCard(response.user_game);
                this._maybeRemoveCard(response.user_game);
                this._showNotification('Status updated');
            }
        } catch (err) {
            this._showNotification('Failed to update status', true);
        } finally {
            this.saving = false;
        }
    },

    async toggleWishlist() {
        if (this.saving) return;
        this.saving = true;

        try {
            const response = await this._patch({is_wishlisted: !this.isWishlisted});
            if (response.success) {
                this.isWishlisted = response.user_game.is_wishlisted;
                this._updateCard(response.user_game);
                this._maybeRemoveCard(response.user_game);
                this._showNotification(this.isWishlisted ? 'Added to wishlist' : 'Removed from wishlist');
            }
        } catch (err) {
            this._showNotification('Failed to update wishlist', true);
        } finally {
            this.saving = false;
        }
    },

    async saveTimePlayed() {
        if (this.saving) return;
        this.saving = true;

        try {
            const value = this.timePlayed === '' || this.timePlayed === null ? null : parseFloat(this.timePlayed);
            const response = await this._patch({time_played: value});
            if (response.success) {
                this.timePlayed = response.user_game.time_played;
                this._updateCard(response.user_game);
                this._showNotification('Time saved');
            }
        } catch (err) {
            this._showNotification('Failed to save time', true);
        } finally {
            this.saving = false;
        }
    },

    addTime(hours) {
        const current = parseFloat(this.timePlayed) || 0;
        this.timePlayed = Math.round((current + hours) * 10) / 10;
        this.saveTimePlayed();
    },

    async saveRating() {
        if (this.saving) return;
        this.saving = true;

        try {
            const value = this.rating === '' || this.rating === null || this.rating === 0 ? null : parseInt(this.rating);
            const response = await this._patch({rating: value});
            if (response.success) {
                this.rating = response.user_game.rating;
                this._updateCard(response.user_game);
                this._showNotification('Rating saved');
            }
        } catch (err) {
            this._showNotification('Failed to save rating', true);
        } finally {
            this.saving = false;
        }
    },

    async removeGame() {
        if (this.deleting) return;
        this.deleting = true;

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            const response = await fetch(`/api/user-games/${this.userGameId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            });
            const data = await response.json();
            if (data.success) {
                // Remove card from DOM with animation
                if (this.cardElement) {
                    this.cardElement.style.transition = 'opacity 0.3s, transform 0.3s';
                    this.cardElement.style.opacity = '0';
                    this.cardElement.style.transform = 'scale(0.95)';
                    setTimeout(() => this.cardElement.remove(), 300);
                }
                this.closeModal();
            }
        } catch (err) {
            this._showNotification('Failed to remove game', true);
        } finally {
            this.deleting = false;
        }
    },

    getRatingColor() {
        if (!this.rating) return 'text-gray-400';
        if (this.rating >= 80) return 'text-green-400';
        if (this.rating >= 60) return 'text-yellow-400';
        if (this.rating >= 40) return 'text-orange-400';
        return 'text-red-400';
    },

    async _patch(data) {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        const response = await fetch(`/api/user-games/${this.userGameId}`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: JSON.stringify(data),
        });
        return await response.json();
    },

    _updateCard(userGame) {
        if (!this.cardElement) return;

        // Update status badge
        const statusBadge = this.cardElement.querySelector('[data-status-badge]');
        if (statusBadge) {
            if (userGame.status) {
                const colors = {playing: 'bg-green-600', played: 'bg-blue-600', backlog: 'bg-yellow-600'};
                const labels = {playing: 'Playing', played: 'Played', backlog: 'Backlog'};
                statusBadge.className = `px-2 py-0.5 rounded-full text-xs font-semibold text-white ${colors[userGame.status] || 'bg-gray-600'}`;
                statusBadge.textContent = labels[userGame.status] || userGame.status;
                statusBadge.style.display = '';
            } else {
                statusBadge.style.display = 'none';
            }
        }

        // Update time badge
        const timeBadge = this.cardElement.querySelector('[data-time-badge]');
        if (timeBadge) {
            if (userGame.time_played_formatted) {
                timeBadge.textContent = userGame.time_played_formatted;
                timeBadge.parentElement.style.display = '';
            } else {
                timeBadge.parentElement.style.display = 'none';
            }
        }

        // Update rating badge
        const ratingBadge = this.cardElement.querySelector('[data-rating-badge]');
        if (ratingBadge) {
            if (userGame.rating) {
                ratingBadge.textContent = userGame.rating + '/100';
                ratingBadge.parentElement.style.display = '';
            } else {
                ratingBadge.parentElement.style.display = 'none';
            }
        }

        // Update wishlist indicator
        const wishlistIcon = this.cardElement.querySelector('[data-wishlist-icon]');
        if (wishlistIcon) {
            wishlistIcon.style.display = userGame.is_wishlisted ? '' : 'none';
        }

        // Update list view cells
        const statusCell = this.cardElement.querySelector('[data-status-cell]');
        if (statusCell && userGame.status_label) {
            const badge = statusCell.querySelector('span');
            if (badge) {
                const colors = {playing: 'bg-green-600', played: 'bg-blue-600', backlog: 'bg-yellow-600'};
                badge.className = `px-2 py-0.5 rounded-full text-xs font-semibold text-white ${colors[userGame.status] || 'bg-gray-600'}`;
                badge.textContent = userGame.status_label;
            }
        }

        const timeCell = this.cardElement.querySelector('[data-time-cell]');
        if (timeCell) {
            timeCell.textContent = userGame.time_played_formatted || '-';
        }

        const ratingCell = this.cardElement.querySelector('[data-rating-cell]');
        if (ratingCell) {
            if (userGame.rating) {
                ratingCell.innerHTML = `<span class="text-orange-400 font-medium">${userGame.rating}/100</span>`;
            } else {
                ratingCell.innerHTML = '<span class="text-gray-500">-</span>';
            }
        }
    },

    _maybeRemoveCard(userGame) {
        if (!this.cardElement) return;

        let shouldRemove = false;

        // If filtering by status and the game's status no longer matches
        if (this.statusFilter && userGame.status !== this.statusFilter) {
            shouldRemove = true;
        }

        // If filtering by wishlist and game is no longer wishlisted
        if (this.wishlistFilter && !userGame.is_wishlisted) {
            shouldRemove = true;
        }

        if (shouldRemove) {
            setTimeout(() => {
                this.cardElement.style.transition = 'opacity 0.3s, transform 0.3s';
                this.cardElement.style.opacity = '0';
                this.cardElement.style.transform = 'scale(0.95)';
                setTimeout(() => this.cardElement.remove(), 300);
                this.closeModal();
            }, 500);
        }
    },

    _showNotification(message, isError = false) {
        this.notification = {message, isError};
        setTimeout(() => {
            this.notification = null;
        }, 2000);
    },
}));
