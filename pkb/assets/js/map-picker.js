/**
 * Leaflet Map Picker & Android High-Precision GPS Engine
 * With Automatic Reverse Geocoding (Auto-Fill Alamat, Kelurahan, & Kecamatan)
 */
document.addEventListener('DOMContentLoaded', function () {
    const latInput = document.getElementById('latitude');
    const lngInput = document.getElementById('longitude');
    const displayCoords = document.getElementById('display-coords');
    const btnGps = document.getElementById('btn-gps');
    const searchInput = document.getElementById('map-search-input');
    const btnSearch = document.getElementById('btn-search-map');
    const gpsStatus = document.getElementById('gps-status');

    // Address form fields to auto-fill
    const inputAlamat = document.getElementById('alamat_lengkap');
    const inputKelurahan = document.getElementById('kelurahan');
    const inputKecamatan = document.getElementById('kecamatan');
    const inputRt = document.getElementById('rt');
    const inputRw = document.getElementById('rw');

    // Default center: Makassar, South Sulawesi (-5.147665, 119.432731)
    let initialLat = latInput && latInput.value && !isNaN(parseFloat(latInput.value)) ? parseFloat(latInput.value) : -5.147665;
    let initialLng = lngInput && lngInput.value && !isNaN(parseFloat(lngInput.value)) ? parseFloat(lngInput.value) : 119.432731;
    let initialZoom = latInput && latInput.value ? 18 : 14;

    const mapElement = document.getElementById('map');
    if (!mapElement) return;

    // Initialize Leaflet Map
    const map = L.map('map', {
        center: [initialLat, initialLng],
        zoom: initialZoom,
        zoomControl: true,
        attributionControl: true
    });

    // High quality OpenStreetMap tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 19
    }).addTo(map);

    // Custom Church Pin Icon
    const churchPinIcon = L.icon({
        iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
        shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
        iconSize: [26, 42],
        iconAnchor: [13, 42],
        popupAnchor: [1, -36],
        shadowSize: [41, 41]
    });

    // Create Draggable Marker
    let marker = L.marker([initialLat, initialLng], {
        draggable: true,
        icon: churchPinIcon
    }).addTo(map);

    let accuracyCircle = null;
    let reverseGeocodeTimer = null;

    // =========================================================================
    // AUTOMATIC REVERSE GEOCODING (Deteksi Nama Jalan, Kelurahan & Kecamatan)
    // =========================================================================
    function autoFillAddressFromCoordinates(lat, lng) {
        if (!inputAlamat) return;

        if (gpsStatus) {
            gpsStatus.innerHTML = '<span style="color:#0284c7; font-weight:600;">🔄 Mengambil data nama jalan & wilayah dari peta...</span>';
        }

        clearTimeout(reverseGeocodeTimer);
        reverseGeocodeTimer = setTimeout(() => {
            fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&addressdetails=1&accept-language=id`)
                .then(res => res.json())
                .then(data => {
                    if (data && data.address) {
                        const addr = data.address;

                        // 1. Detect Road / Street
                        const roadName = addr.road || addr.residential || addr.neighbourhood || addr.suburb || addr.pedestrian || addr.path || addr.footway || '';
                        
                        // 2. Detect Kelurahan / Desa
                        const kelurahanName = addr.village || addr.suburb || addr.neighbourhood || addr.quarter || '';
                        
                        // 3. Detect Kecamatan / District
                        const kecamatanName = addr.city_district || addr.subdistrict || addr.district || addr.county || '';
                        
                        // 4. City / Kota
                        const cityName = addr.city || addr.town || addr.municipality || 'Makassar';

                        // Build friendly full address string
                        let fullAddressParts = [];
                        if (roadName) fullAddressParts.push(roadName);
                        if (kelurahanName && !fullAddressParts.includes(kelurahanName)) fullAddressParts.push('Kel. ' + kelurahanName);
                        if (kecamatanName && !fullAddressParts.includes(kecamatanName)) fullAddressParts.push('Kec. ' + kecamatanName);
                        if (cityName) fullAddressParts.push(cityName);

                        const formattedAddress = fullAddressParts.join(', ');

                        // Auto-fill form inputs
                        if (inputAlamat) {
                            inputAlamat.value = formattedAddress || data.display_name;
                        }
                        if (inputKelurahan && kelurahanName) {
                            inputKelurahan.value = kelurahanName;
                        }
                        if (inputKecamatan && kecamatanName) {
                            inputKecamatan.value = kecamatanName;
                        }

                        // Notification feedback
                        if (gpsStatus) {
                            gpsStatus.innerHTML = `
                                <div style="background:#ecfdf5; border:1.5px solid #10b981; padding:8px 12px; border-radius:10px; margin-top:6px; color:#065f46; font-size:0.825rem; line-height:1.45;">
                                    <strong>✨ Alamat Otomatis Terisi:</strong><br>
                                    📍 <strong>${formattedAddress || data.display_name}</strong><br>
                                    <small style="color:#047857;">(Nama jalan, kelurahan, dan kecamatan telah terisi otomatis di formulir atas. Anda dapat menambahkan no. rumah/RT/RW jika perlu).</small>
                                </div>
                            `;
                        }

                        marker.bindPopup(`<b>📍 Lokasi Terpilih:</b><br><small style="color:#059669; font-weight:700;">${roadName ? roadName + ', ' : ''}Kel. ${kelurahanName || '-'}, Kec. ${kecamatanName || '-'}</small><br><small style="color:#64748b;">Data alamat otomatis terisi ke formulir.</small>`).openPopup();
                    }
                })
                .catch(err => {
                    console.warn('Reverse geocoding error:', err);
                    if (gpsStatus) {
                        gpsStatus.innerHTML = '<span style="color:#6d28d9; font-weight:600;">📍 Titik koordinat berhasil disimpan. Silakan lengkapi nomor rumah & nama jalan.</span>';
                    }
                });
        }, 300);
    }

    function updateCoordinates(lat, lng, zoomLevel = null, accuracy = null, triggerAutoFill = true) {
        lat = parseFloat(lat.toFixed(7));
        lng = parseFloat(lng.toFixed(7));

        if (latInput) latInput.value = lat;
        if (lngInput) lngInput.value = lng;

        if (displayCoords) {
            displayCoords.textContent = `${lat}, ${lng}`;
        }

        marker.setLatLng([lat, lng]);

        if (accuracy && accuracy > 0) {
            if (accuracyCircle) {
                map.removeLayer(accuracyCircle);
            }
            accuracyCircle = L.circle([lat, lng], {
                radius: Math.min(accuracy, 100),
                color: '#10b981',
                fillColor: '#6ee7b7',
                fillOpacity: 0.25,
                weight: 1.5
            }).addTo(map);
        }

        if (zoomLevel) {
            map.setView([lat, lng], zoomLevel);
        } else {
            map.panTo([lat, lng]);
        }

        // Trigger Auto-Fill Address
        if (triggerAutoFill) {
            autoFillAddressFromCoordinates(lat, lng);
        }
    }

    // Initialize display with default coordinates (without overwriting existing form text on edit)
    const hasExistingText = inputAlamat && inputAlamat.value.trim() !== '';
    updateCoordinates(initialLat, initialLng, null, null, !hasExistingText && (latInput && latInput.value !== ''));

    // Event: Marker Dragged
    marker.on('dragend', function (e) {
        const position = marker.getLatLng();
        updateCoordinates(position.lat, position.lng, null, null, true);
        if (accuracyCircle) {
            map.removeLayer(accuracyCircle);
            accuracyCircle = null;
        }
    });

    // Event: Map Clicked
    map.on('click', function (e) {
        updateCoordinates(e.latlng.lat, e.latlng.lng, null, null, true);
        if (accuracyCircle) {
            map.removeLayer(accuracyCircle);
            accuracyCircle = null;
        }
    });

    // =========================================================================
    // GPS PERMISSION MODAL TRIGGER
    // =========================================================================
    const modalGps = document.getElementById('modal-gps-guide');
    window.openGpsModal = function() {
        if (modalGps) modalGps.classList.add('active');
    };
    window.closeGpsModal = function() {
        if (modalGps) modalGps.classList.remove('active');
    };

    // =========================================================================
    // HIGH PRECISION GPS ENGINE
    // =========================================================================
    function getAndroidLocation() {
        if (!navigator.geolocation) {
            alert('Perangkat atau browser Anda tidak mendukung fitur GPS Geolocation.');
            return;
        }

        btnGps.disabled = true;
        btnGps.innerHTML = '<span>📡</span> <span>Mencari Sinyal GPS...</span>';
        if (gpsStatus) {
            gpsStatus.innerHTML = '<span style="color: #0284c7; font-weight: 600;">📡 Sedang mengunci titik koordinat GPS HP Android... Pastikan GPS HP Aktif.</span>';
        }

        // STAGE 1: High Accuracy Satellites (timeout 12s)
        navigator.geolocation.getCurrentPosition(
            function (position) {
                handleGpsSuccess(position);
            },
            function (errorHigh) {
                console.warn('High accuracy GPS timeout or denied, trying fallback...', errorHigh);

                if (errorHigh.code === errorHigh.PERMISSION_DENIED) {
                    handleGpsError(errorHigh);
                    return;
                }

                // STAGE 2: Fallback to Network/Cell Tower/WiFi
                navigator.geolocation.getCurrentPosition(
                    function (positionFallback) {
                        handleGpsSuccess(positionFallback);
                    },
                    function (errorFinal) {
                        handleGpsError(errorFinal);
                    },
                    {
                        enableHighAccuracy: false,
                        timeout: 8000,
                        maximumAge: 60000
                    }
                );
            },
            {
                enableHighAccuracy: true,
                timeout: 12000,
                maximumAge: 0
            }
        );
    }

    function handleGpsSuccess(position) {
        const userLat = position.coords.latitude;
        const userLng = position.coords.longitude;
        const accuracy = position.coords.accuracy || 10;

        updateCoordinates(userLat, userLng, 18, accuracy, true);

        btnGps.disabled = false;
        btnGps.innerHTML = '<span>📍</span> <span>Lokasi GPS Terkunci</span>';
        btnGps.style.background = '#059669';
    }

    function handleGpsError(error) {
        btnGps.disabled = false;
        btnGps.innerHTML = '<span>📍</span> <span>Gunakan Lokasi GPS Saya</span>';

        if (error.code === error.PERMISSION_DENIED) {
            if (gpsStatus) {
                gpsStatus.innerHTML = `
                    <div style="background:#fee2e2; border:1px solid #f87171; padding:10px 14px; border-radius:10px; margin-top:6px;">
                        <span style="color:#991b1b; font-weight:800;">🔒 Izin Akses Lokasi Ditolak oleh Browser.</span><br>
                        <span style="color:#b91c1c; font-size:0.8rem;">Anda tetap dapat <strong>menggeser pin merah di peta</strong> secara manual (alamat akan otomatis terisi) atau </span>
                        <a href="javascript:void(0)" onclick="openGpsModal()" style="color:#7c3aed; font-weight:800; text-decoration:underline;">Lihat Panduan Buka Izin GPS &rarr;</a>
                    </div>
                `;
            }
            openGpsModal();
        } else if (error.code === error.POSITION_UNAVAILABLE) {
            if (gpsStatus) {
                gpsStatus.innerHTML = '<span style="color:#dc2626; font-weight:700;">⚠️ Sinyal GPS tidak tersedia. Pastikan tombol Lokasi di HP sudah DINYALAKAN atau geser pin peta secara manual.</span>';
            }
            alert('Sinyal GPS tidak tersedia.\n\nPastikan tombol "Lokasi / GPS" pada pengaturan atas HP Anda sudah DINYALAKAN, atau geser pin merah pada peta secara manual.');
        } else if (error.code === error.TIMEOUT) {
            if (gpsStatus) {
                gpsStatus.innerHTML = '<span style="color:#d97706; font-weight:700;">⏳ Waktu deteksi GPS habis. Silakan ketuk tombol GPS lagi atau sentuh lokasi rumah langsung di peta.</span>';
            }
            alert('Waktu permintaan GPS habis.\n\nSinyal satelit butuh beberapa detik untuk mengunci. Silakan coba tekan tombol GPS lagi atau geser pin merah pada peta.');
        }
    }

    if (btnGps) {
        btnGps.addEventListener('click', getAndroidLocation);
    }

    // =========================================================================
    // SEARCH ADDRESS (NOMINATIM OSM GEOCODER)
    // =========================================================================
    function executeSearch() {
        const query = searchInput.value.trim();
        if (!query) return;

        btnSearch.disabled = true;
        btnSearch.innerHTML = 'Mencari...';

        fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&countrycodes=id&limit=1&accept-language=id&addressdetails=1`)
            .then(res => res.json())
            .then(data => {
                btnSearch.disabled = false;
                btnSearch.innerHTML = 'Cari';

                if (data && data.length > 0) {
                    const result = data[0];
                    const foundLat = parseFloat(result.lat);
                    const foundLng = parseFloat(result.lon);

                    updateCoordinates(foundLat, foundLng, 17, null, true);
                } else {
                    alert('Lokasi atau nama jalan tidak ditemukan. Coba ketik nama jalan, kelurahan, atau patokan terdekat.');
                }
            })
            .catch(err => {
                btnSearch.disabled = false;
                btnSearch.innerHTML = 'Cari';
                alert('Gagal melakukan pencarian online. Silakan arahkan peta secara manual.');
            });
    }

    if (btnSearch) {
        btnSearch.addEventListener('click', executeSearch);
    }
    if (searchInput) {
        searchInput.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                executeSearch();
            }
        });
    }
});
