const mapRoot = document.querySelector('[data-community-map]');

if (mapRoot) {
    const initializeMap = () => {
        if (!window.L || mapRoot.dataset.initialized === 'true') return;

        const dataElement = document.getElementById('community-map-data');
        const canvas = mapRoot.querySelector('[data-community-map-canvas]');
        const searchInput = mapRoot.querySelector('[data-community-map-search]');
        const circleSelect = mapRoot.querySelector('[data-community-map-circle]');
        const typeButtons = Array.from(mapRoot.querySelectorAll('[data-community-map-type]'));
        const locationElements = Array.from(mapRoot.querySelectorAll('[data-map-location]'));
        const countElement = mapRoot.querySelector('[data-community-map-count]');
        const emptyElement = mapRoot.querySelector('[data-community-map-empty]');

        if (!dataElement || !canvas) return;

        let locations;

        try {
            locations = JSON.parse(dataElement.textContent || '[]');
        } catch (error) {
            console.error('Не вдалося прочитати дані карти громад.', error);
            return;
        }

        if (!Array.isArray(locations) || locations.length === 0) return;

        mapRoot.dataset.initialized = 'true';

        const map = window.L.map(canvas, {
            zoomControl: true,
            scrollWheelZoom: false,
            minZoom: 5,
            maxZoom: 17,
        }).setView([49.05, 31.2], 6);

        window.L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
            attribution: '&copy; OpenStreetMap contributors &copy; CARTO',
            subdomains: 'abcd',
            maxZoom: 20,
        }).addTo(map);

        const markers = new Map();
        let activeType = 'all';

        const normalize = (value) => String(value || '')
            .toLocaleLowerCase('uk-UA')
            .replace(/[’ʼ`]/g, "'")
            .trim();

        const entryMatches = (entry, query, circle, type) => {
            if (type !== 'all' && entry.type !== type) return false;
            if (circle !== 'all' && entry.circle !== circle) return false;
            if (!query) return true;

            const searchable = normalize([
                entry.title,
                entry.person,
                entry.role,
                ...(entry.phones || []),
                ...(entry.emails || []),
            ].join(' '));

            return searchable.includes(query);
        };

        const locationMatches = (location, query, circle, type) => {
            const locationText = normalize(`${location.city} ${location.region}`);
            const locationQueryMatches = !query || locationText.includes(query);
            const matchingEntries = location.entries.filter((entry) => entryMatches(entry, query, circle, type));

            if (matchingEntries.length > 0) return matchingEntries;

            if (locationQueryMatches) {
                return location.entries.filter((entry) => entryMatches(entry, '', circle, type));
            }

            return [];
        };

        const phoneHref = (phone) => `tel:${String(phone).replace(/[^+\d]/g, '')}`;

        const createPopup = (location, entries) => {
            const wrapper = document.createElement('div');
            wrapper.className = 'map-popup';

            const head = document.createElement('div');
            head.className = 'map-popup__head';

            const city = document.createElement('strong');
            city.textContent = location.city;

            const meta = document.createElement('span');
            meta.textContent = `${location.region} · ${location.circleLabel}`;

            head.append(city, meta);
            wrapper.append(head);

            entries.forEach((entry) => {
                const block = document.createElement('div');
                block.className = 'map-popup__entry';

                const type = document.createElement('span');
                type.className = `map-popup__type${entry.type === 'representation' ? ' map-popup__type--representation' : ''}`;
                type.textContent = entry.type === 'community' ? 'Громада' : 'Представництво';

                const title = document.createElement('b');
                title.textContent = entry.title;

                const person = document.createElement('small');
                person.textContent = [entry.role, entry.person].filter(Boolean).join(' — ');

                block.append(type, title, person);

                if (entry.address) {
                    const address = document.createElement('small');
                    address.textContent = entry.address;
                    block.append(address);
                }

                const contacts = document.createElement('div');
                contacts.className = 'map-popup__contacts';

                (entry.phones || []).forEach((phone) => {
                    const link = document.createElement('a');
                    link.href = phoneHref(phone);
                    link.textContent = phone;
                    contacts.append(link);
                });

                (entry.emails || []).forEach((email) => {
                    const link = document.createElement('a');
                    link.href = `mailto:${email}`;
                    link.textContent = email;
                    contacts.append(link);
                });

                if (contacts.childElementCount > 0) block.append(contacts);
                wrapper.append(block);
            });

            return wrapper;
        };

        const markerClass = (entries) => {
            const types = new Set(entries.map((entry) => entry.type));
            if (types.size > 1) return 'mixed';
            return types.has('representation') ? 'representation' : 'community';
        };

        locations.forEach((location) => {
            const kind = markerClass(location.entries);
            const icon = window.L.divIcon({
                className: 'rv-map-icon',
                html: `<span class="rv-map-pin rv-map-pin--${kind}">${location.entries.length}</span>`,
                iconSize: [34, 34],
                iconAnchor: [17, 17],
                popupAnchor: [0, -16],
            });

            const marker = window.L.marker([location.lat, location.lng], { icon });
            marker.bindPopup(createPopup(location, location.entries), { maxWidth: 340 });
            markers.set(location.id, marker);
        });

        const fitVisibleMarkers = (visibleMarkers) => {
            if (visibleMarkers.length === 0) return;

            if (visibleMarkers.length === 1) {
                map.setView(visibleMarkers[0].getLatLng(), 9, { animate: true });
                return;
            }

            const group = window.L.featureGroup(visibleMarkers);
            map.fitBounds(group.getBounds().pad(0.16), { maxZoom: 8, animate: true });
        };

        const applyFilters = (fitMap = true) => {
            const query = normalize(searchInput?.value);
            const circle = circleSelect?.value || 'all';
            const visibleMarkers = [];
            let visibleEntryCount = 0;

            locations.forEach((location) => {
                const matchingEntries = locationMatches(location, query, circle, activeType);
                const marker = markers.get(location.id);
                const locationElement = locationElements.find((element) => element.dataset.mapLocation === location.id);
                const visible = matchingEntries.length > 0;

                if (locationElement) {
                    locationElement.hidden = !visible;
                }

                if (!marker) return;

                if (visible) {
                    if (!map.hasLayer(marker)) marker.addTo(map);
                    marker.setPopupContent(createPopup(location, matchingEntries));
                    visibleMarkers.push(marker);
                    visibleEntryCount += matchingEntries.length;
                } else if (map.hasLayer(marker)) {
                    marker.removeFrom(map);
                }
            });

            if (countElement) countElement.textContent = String(visibleEntryCount);
            if (emptyElement) emptyElement.hidden = visibleEntryCount !== 0;
            if (fitMap && visibleMarkers.length > 0) fitVisibleMarkers(visibleMarkers);
        };

        typeButtons.forEach((button) => {
            button.addEventListener('click', () => {
                activeType = button.dataset.communityMapType || 'all';
                typeButtons.forEach((item) => item.classList.toggle('is-active', item === button));
                applyFilters();
            });
        });

        searchInput?.addEventListener('input', () => applyFilters(false));
        searchInput?.addEventListener('change', () => applyFilters());
        circleSelect?.addEventListener('change', () => applyFilters());

        locationElements.forEach((element) => {
            const button = element.querySelector('[data-map-location-button]');
            const marker = markers.get(element.dataset.mapLocation);

            button?.addEventListener('click', () => {
                if (!marker || !map.hasLayer(marker)) return;
                map.setView(marker.getLatLng(), 10, { animate: true });
                marker.openPopup();

                if (window.innerWidth < 992) {
                    canvas.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            });
        });

        applyFilters();

        window.setTimeout(() => map.invalidateSize(), 250);
        window.addEventListener('resize', () => map.invalidateSize(), { passive: true });
    };

    if (window.L) {
        initializeMap();
    } else {
        document.addEventListener('leaflet:ready', initializeMap, { once: true });
        window.addEventListener('load', initializeMap, { once: true });
    }
}
