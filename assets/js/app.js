(() => {
    const baseUrl = document.body?.dataset.baseUrl || window.location.origin;
    const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    const apiUrl = (path) => new URL(path, baseUrl.endsWith('/') ? baseUrl : `${baseUrl}/`).href;

    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');

    const requestJson = async (url, options = {}) => {
        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: {
                'X-CSRF-Token': csrfToken(),
                ...(options.headers || {}),
            },
            ...options,
        });

        let payload = {};
        try {
            payload = await response.json();
        } catch (error) {
            payload = { success: false, message: 'Invalid server response.' };
        }

        if (!response.ok && !payload.message) {
            payload.message = 'Request failed.';
        }

        return payload;
    };

    const showMessage = (target, message, isError = false) => {
        if (!target) {
            return;
        }

        target.textContent = message;
        target.classList.toggle('is-error', isError);
        target.classList.toggle('is-success', !isError);
    };

    const loadDashboardStats = async () => {
        const statsContainer = document.getElementById('dashboardStats');
        if (!statsContainer) {
            return;
        }

        const endpoint = statsContainer.dataset.endpoint;
        if (!endpoint) {
            return;
        }

        const result = await requestJson(endpoint);
        if (!result.success) {
            return;
        }

        statsContainer.querySelectorAll('[data-stat]').forEach((card) => {
            const key = card.dataset.stat;
            const value = result[key];
            const strong = card.querySelector('strong');
            if (strong && typeof value !== 'undefined') {
                strong.textContent = Number(value).toLocaleString();
            }
        });
    };

    document.querySelectorAll('.ajax-form').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            const confirmMessage = form.querySelector('[data-confirm]')?.dataset.confirm;
            if (confirmMessage && !window.confirm(confirmMessage)) {
                return;
            }

            const formData = new FormData(form);
            const endpoint = form.getAttribute('action') || window.location.href;
            const messageTarget = form.querySelector('.form-message') || document.getElementById(form.dataset.successTarget || '');

            try {
                const result = await requestJson(endpoint, {
                    method: 'POST',
                    body: formData,
                });

                if (result.success) {
                    showMessage(messageTarget, result.message || 'Saved successfully.');
                    if (form.dataset.redirectAdmin && form.dataset.redirectRider && result.role) {
                        window.location.href = result.role === 'admin' ? form.dataset.redirectAdmin : form.dataset.redirectRider;
                        return;
                    }

                    if (form.dataset.reloadOnSuccess === 'true') {
                        window.location.reload();
                    }
                } else {
                    showMessage(messageTarget, result.message || 'Save failed.', true);
                }
            } catch (error) {
                showMessage(messageTarget, 'Network error. Please try again.', true);
            }
        });
    });

    const renderSearchResults = (container, type, results) => {
        if (!container) {
            return;
        }

        if (type === 'riders') {
            container.innerHTML = `
                <table class="data-table compact-table">
                    <thead><tr><th>Name</th><th>Code</th><th>Email</th><th>Status</th><th>Last Update</th></tr></thead>
                    <tbody>
                        ${results.map((item) => `
                            <tr>
                                <td>${escapeHtml(item.full_name)}</td>
                                <td>${escapeHtml(item.employee_code)}</td>
                                <td>${escapeHtml(item.email)}</td>
                                <td>${escapeHtml(item.status)}</td>
                                <td>${escapeHtml(item.last_location_update)}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `;
            return;
        }

        if (type === 'parcels') {
            container.innerHTML = `
                <table class="data-table compact-table">
                    <thead><tr><th>Tracking</th><th>Recipient</th><th>Address</th><th>Status</th><th>Updated</th></tr></thead>
                    <tbody>
                        ${results.map((item) => `
                            <tr>
                                <td>${escapeHtml(item.tracking_number)}</td>
                                <td>${escapeHtml(item.recipient_name)}</td>
                                <td>${escapeHtml(item.delivery_address)}</td>
                                <td>${escapeHtml(item.status)}</td>
                                <td>${escapeHtml(item.updated_at)}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `;
        }
    };

    let searchTimer = null;
    document.querySelectorAll('.live-search').forEach((input) => {
        input.addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = window.setTimeout(async () => {
                const type = input.dataset.searchType;
                const query = input.value.trim();
                const container = document.getElementById('searchResults') || document.getElementById(`${type}SearchResults`);

                if (!type || !container) {
                    return;
                }

                const result = await requestJson(apiUrl(`api/search.php?type=${encodeURIComponent(type)}&q=${encodeURIComponent(query)}`));
                if (result.success) {
                    renderSearchResults(container, type, result.results || []);
                }
            }, 300);
        });
    });

    if (document.getElementById('dashboardStats')) {
        loadDashboardStats();
        window.setInterval(loadDashboardStats, 15000);
    }

    const riderControl = document.querySelector('.rider-control');
    if (riderControl) {
        const toggleButton = riderControl.querySelector('.rider-status-toggle');
        const statusEndpoint = riderControl.dataset.statusEndpoint;
        const locationEndpoint = riderControl.dataset.locationEndpoint;
        const riderId = riderControl.dataset.riderId;
        let isOnline = riderControl.dataset.currentStatus === 'online';
        let trackingTimer = null;

        const setStatus = (status) => {
            isOnline = status === 'online';
            toggleButton.textContent = status.toUpperCase();
            document.querySelector('.rider-status-label').textContent = status.charAt(0).toUpperCase() + status.slice(1);
        };

        const sendLocation = async (position) => {
            if (!isOnline || !position) {
                return;
            }

            const data = new FormData();
            data.append('rider_id', riderId);
            data.append('latitude', position.coords.latitude);
            data.append('longitude', position.coords.longitude);
            data.append('accuracy', position.coords.accuracy || '');
            data.append('speed', position.coords.speed || '');
            data.append('heading', position.coords.heading || '');

            await requestJson(locationEndpoint, {
                method: 'POST',
                body: data,
            });
        };

        const startTracking = () => {
            if (!navigator.geolocation || trackingTimer) {
                return;
            }

            trackingTimer = window.setInterval(() => {
                navigator.geolocation.getCurrentPosition(sendLocation, () => {}, { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 });
            }, 15000);
        };

        const stopTracking = () => {
            if (trackingTimer) {
                window.clearInterval(trackingTimer);
                trackingTimer = null;
            }
        };

        toggleButton?.addEventListener('click', async () => {
            const nextStatus = isOnline ? 'offline' : 'online';
            const data = new FormData();
            data.append('status', nextStatus);

            const result = await requestJson(statusEndpoint, {
                method: 'POST',
                body: data,
            });

            if (result.success) {
                setStatus(result.status);
                if (result.status === 'online') {
                    startTracking();
                } else {
                    stopTracking();
                }
            }
        });

        if (isOnline) {
            startTracking();
            navigator.geolocation?.getCurrentPosition(sendLocation, () => {}, { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 });
        }
    }

    const initMap = (containerId, endpoint, riderId, mode) => {
        const container = document.getElementById(containerId);
        if (!container || typeof L === 'undefined') {
            return;
        }

        const map = L.map(container).setView([14.5995, 120.9842], 11);
        const overlayLayer = L.layerGroup().addTo(map);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors',
        }).addTo(map);

        const redraw = () => {
            overlayLayer.clearLayers();

            fetch(`${endpoint}?rider_id=${encodeURIComponent(riderId || '')}`, { credentials: 'same-origin' })
                .then((response) => response.json())
                .then((payload) => {
                    if (!payload.success) {
                        return;
                    }

                    const markers = [];

                    if (mode === 'riders') {
                        payload.riders.forEach((rider) => {
                            const marker = L.marker([rider.current_latitude, rider.current_longitude]);
                            marker.bindPopup(`<strong>${escapeHtml(rider.full_name)}</strong><br>${escapeHtml(rider.last_location_update)}`);
                            marker.addTo(overlayLayer);
                            markers.push([rider.current_latitude, rider.current_longitude]);
                        });
                    } else {
                        payload.locations.forEach((point) => {
                            markers.push([point.latitude, point.longitude]);
                        });

                        if (markers.length > 0) {
                            const polyline = L.polyline(markers, { color: '#0f766e', weight: 4 }).addTo(overlayLayer);
                            map.fitBounds(polyline.getBounds(), { padding: [20, 20] });
                            L.marker(markers[markers.length - 1]).addTo(overlayLayer);
                        }
                    }

                    if (markers.length > 0 && mode === 'riders') {
                        map.fitBounds(markers, { padding: [20, 20] });
                    }
                });
        };

        redraw();
        window.setInterval(redraw, 15000);
    };

    document.querySelectorAll('[data-map]').forEach((element) => {
        const mode = element.dataset.map;
        if (mode === 'riders') {
            initMap(element.id, element.dataset.endpoint, '', 'riders');
        } else if (mode === 'route') {
            initMap(element.id, element.dataset.endpoint, element.dataset.riderId || '', 'route');
        } else if (mode === 'rider') {
            initMap(element.id, element.dataset.endpoint, element.dataset.riderId || '', 'route');
        }
    });
})();
