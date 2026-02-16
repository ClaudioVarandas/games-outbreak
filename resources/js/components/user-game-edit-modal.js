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

    decrementTime() {
        const current = parseFloat(this.timePlayed) || 0;
        this.timePlayed = Math.max(0, Math.round((current - 0.5) * 10) / 10);
    },

    incrementTime() {
        const current = parseFloat(this.timePlayed) || 0;
        this.timePlayed = Math.round((current + 0.5) * 10) / 10;
    },

    decrementRating() {
        const current = parseInt(this.rating) || 0;
        this.rating = Math.max(0, current - 1);
    },

    incrementRating() {
        const current = parseInt(this.rating) || 0;
        this.rating = Math.min(100, current + 1);
    },

    async saveAll() {
        if (this.saving) return;
        this.saving = true;

        try {
            const timeValue = this.timePlayed === '' || this.timePlayed === null ? null : parseFloat(this.timePlayed);
            const ratingValue = this.rating === '' || this.rating === null || this.rating === 0 || this.rating === '0' ? null : parseInt(this.rating);

            const response = await this._patch({
                status: this.status,
                is_wishlisted: this.isWishlisted,
                time_played: timeValue,
                rating: ratingValue,
            });

            if (response.success) {
                this.status = response.user_game.status;
                this.isWishlisted = response.user_game.is_wishlisted;
                this.timePlayed = response.user_game.time_played;
                this.rating = response.user_game.rating;
                this._updateCard(response.user_game);
                this._maybeRemoveCard(response.user_game);
                this._showNotification('Saved');
            }
        } catch (err) {
            this._showNotification('Failed to save', true);
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
                const colors = {playing: 'bg-green-600', played: 'bg-purple-600', backlog: 'bg-orange-600'};
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

        // Update wishlist indicator (grid view)
        const wishlistIcon = this.cardElement.querySelector('[data-wishlist-icon]');
        if (wishlistIcon) {
            wishlistIcon.style.display = userGame.is_wishlisted ? '' : 'none';
        }

        // Update wishlist cell (list view)
        const wishlistCell = this.cardElement.querySelector('[data-wishlist-cell]');
        if (wishlistCell) {
            if (userGame.is_wishlisted) {
                wishlistCell.innerHTML = '<svg data-wishlist-icon class="w-4 h-4 text-red-400 mx-auto" fill="currentColor" viewBox="0 0 24 24"><path d="M11.645 20.91l-.007-.003-.022-.012a15.247 15.247 0 01-.383-.218 25.18 25.18 0 01-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0112 5.052 5.5 5.5 0 0116.313 3c2.973 0 5.437 2.322 5.437 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 01-4.244 3.17 15.247 15.247 0 01-.383.219l-.022.012-.007.004-.003.001a.752.752 0 01-.704 0l-.003-.001z"/></svg>';
            } else {
                wishlistCell.innerHTML = '';
            }
        }

        // Update list view cells
        const statusCell = this.cardElement.querySelector('[data-status-cell]');
        if (statusCell) {
            if (userGame.status_label) {
                const badge = statusCell.querySelector('span');
                if (badge) {
                    const colors = {playing: 'bg-green-600', played: 'bg-purple-600', backlog: 'bg-orange-600'};
                    badge.className = `px-2 py-0.5 rounded-full text-xs font-semibold text-white ${colors[userGame.status] || 'bg-gray-600'}`;
                    badge.textContent = userGame.status_label;
                }
            }
        }

        const timeCell = this.cardElement.querySelector('[data-time-cell]');
        if (timeCell) {
            if (userGame.time_played_formatted) {
                timeCell.innerHTML = `<span class="flex items-center gap-1"><svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>${userGame.time_played_formatted}</span>`;
            } else {
                timeCell.textContent = '-';
            }
        }

        const ratingCell = this.cardElement.querySelector('[data-rating-cell]');
        if (ratingCell) {
            if (userGame.rating) {
                ratingCell.innerHTML = `<span class="flex items-center gap-1 text-orange-400 font-medium"><svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.006 5.404.434c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.434 2.082-5.005z"/></svg>${userGame.rating}/100</span>`;
            } else {
                ratingCell.innerHTML = '<span class="text-gray-500">-</span>';
            }
        }
    },

    _maybeRemoveCard(userGame) {
        if (!this.cardElement) return;

        let shouldRemove = false;

        // If filtering by status and the game's status no longer matches
        if (this.statusFilter && this.statusFilter !== 'all' && userGame.status !== this.statusFilter) {
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
