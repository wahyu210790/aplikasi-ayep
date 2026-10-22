/**
 * Modul Scanner Barcode Kamera & Pencarian Cepat
 * Menggunakan pustaka lokal /js/html5-qrcode.min.js (v2.3.8)
 */
(function () {
    'use strict';

    window.AyepBarcodeScanner = {
        instance: null,
        isScanning: false,
        scanLocked: false,

        /**
         * Memulai kamera pemindai barcode pada elemen containerId.
         * @param {Object} options
         * @param {string} options.containerId ID elemen div tempat video kamera dirender
         * @param {string} options.panelId ID elemen panel pembungkus scanner
         * @param {string} options.statusId ID elemen teks status/error kamera
         * @param {function(string): void} options.onDetected Callback saat barcode berhasil terbaca
         */
        start: function (options) {
            const self = this;
            const panelEl = document.getElementById(options.panelId);
            const statusEl = document.getElementById(options.statusId);
            const containerEl = document.getElementById(options.containerId);

            if (!panelEl || !containerEl) {
                return;
            }

            panelEl.style.display = 'block';
            self.scanLocked = false;

            const setStatus = function (msg, isError) {
                if (!statusEl) return;
                statusEl.textContent = msg;
                statusEl.style.backgroundColor = isError ? 'rgba(220, 38, 38, 0.25)' : 'rgba(255, 255, 255, 0.1)';
                statusEl.style.color = isError ? '#fecaca' : '#f8fafc';
            };

            if (typeof window.Html5Qrcode === 'undefined') {
                setStatus('Pustaka pemindai kamera belum termuat. Silakan muat ulang halaman atau ketik barcode secara manual.', true);
                return;
            }

            // Periksa apakah browser mendukung mediaDevices (HTTPS atau localhost)
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                const isInsecure = window.location.protocol !== 'https:' &&
                    window.location.hostname !== 'localhost' &&
                    window.location.hostname !== '127.0.0.1';

                if (isInsecure) {
                    setStatus(
                        'Akses kamera diblokir oleh browser karena halaman tidak dibuka melalui koneksi HTTPS (atau localhost). Gunakan HTTPS pada hosting atau ketik barcode secara manual di bawah.',
                        true
                    );
                } else {
                    setStatus(
                        'Browser atau perangkat ini tidak mendukung akses kamera langsung. Silakan ketik barcode secara manual.',
                        true
                    );
                }
                return;
            }

            if (self.isScanning && self.instance) {
                setStatus('Kamera sudah aktif. Arahkan kamera ke barcode barang.', false);
                return;
            }

            setStatus('Meminta izin kamera... Mohon klik "Izinkan" (Allow) jika diminta oleh browser.', false);

            try {
                self.instance = new window.Html5Qrcode(options.containerId);
            } catch (err) {
                setStatus('Gagal menyiapkan pemindai kamera. Silakan gunakan input barcode manual.', true);
                return;
            }

            const config = {
                fps: 10,
                qrbox: { width: 250, height: 140 },
                aspectRatio: 1.5
            };

            self.instance.start(
                { facingMode: 'environment' },
                config,
                function onScanSuccess(decodedText) {
                    if (self.scanLocked) {
                        return;
                    }
                    const cleaned = String(decodedText || '').trim();
                    if (!cleaned) {
                        return;
                    }

                    // Kunci pemindaian agar tidak memicu request berulang
                    self.scanLocked = true;
                    setStatus('Barcode terbaca: ' + cleaned + '. Memproses...', false);

                    self.stop(options.panelId, function () {
                        if (typeof options.onDetected === 'function') {
                            options.onDetected(cleaned);
                        }
                    });
                },
                function onScanFailure() {
                    // Abaikan frame yang belum mendeteksi barcode
                }
            ).then(function () {
                self.isScanning = true;
                setStatus('Kamera aktif. Arahkan ke barcode barang dengan pencahayaan cukup.', false);
            }).catch(function (err) {
                self.isScanning = false;
                const errStr = String(err || '');
                if (errStr.indexOf('NotAllowedError') !== -1 || errStr.indexOf('Permission') !== -1) {
                    setStatus('Izin kamera ditolak. Berikan izin kamera di pengaturan browser HP Anda, atau ketik barcode secara manual.', true);
                } else if (errStr.indexOf('NotFoundError') !== -1) {
                    setStatus('Kamera tidak ditemukan pada perangkat ini. Silakan ketik barcode secara manual.', true);
                } else {
                    setStatus('Tidak dapat membuka kamera (' + errStr + '). Silakan ketik barcode secara manual.', true);
                }
            });
        },

        /**
         * Menghentikan stream kamera dan menutup panel scanner.
         */
        stop: function (panelId, callback) {
            const self = this;
            const panelEl = panelId ? document.getElementById(panelId) : null;

            const finish = function () {
                self.isScanning = false;
                self.instance = null;
                if (panelEl) {
                    panelEl.style.display = 'none';
                }
                if (typeof callback === 'function') {
                    callback();
                }
            };

            if (self.instance && self.isScanning) {
                self.instance.stop().then(function () {
                    try {
                        self.instance.clear();
                    } catch (e) {}
                    finish();
                }).catch(function () {
                    finish();
                });
            } else {
                finish();
            }
        }
    };
})();
