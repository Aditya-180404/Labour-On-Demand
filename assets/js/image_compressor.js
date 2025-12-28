/**
 * Shared Image Compressor for Labour On Demand
 * Handles client-side resizing and quality adjustment before upload.
 */

const ImageCompressor = {
    /**
     * Compress a single File object
     * @param {File} file 
     * @param {number} max_size Max width/height in px
     * @param {number} quality 0 to 1
     * @returns {Promise<File>}
     */
    compress: async (file, max_size = 1024, quality = 0.85) => {
        if (!file.type.startsWith('image/') || file.type === 'image/gif') return file;

        return new Promise((resolve) => {
            const reader = new FileReader();
            reader.readAsDataURL(file);
            reader.onload = (event) => {
                const img = new Image();
                img.src = event.target.result;
                img.onload = () => {
                    const canvas = document.createElement('canvas');
                    let width = img.width;
                    let height = img.height;

                    if (width > height) {
                        if (width > max_size) {
                            height *= max_size / width;
                            width = max_size;
                        }
                    } else {
                        if (height > max_size) {
                            width *= max_size / height;
                            height = max_size;
                        }
                    }

                    canvas.width = width;
                    canvas.height = height;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, width, height);

                    canvas.toBlob((blob) => {
                        const compressedFile = new File([blob], file.name, {
                            type: 'image/jpeg',
                            lastModified: Date.now()
                        });
                        resolve(compressedFile);
                    }, 'image/jpeg', quality);
                };
            };
            reader.onerror = () => resolve(file);
        });
    },

    /**
     * Attach compression to a form or forms
     * @param {string} selector CSS selector for form(s)
     * @param {string} statusMsg Optional processing message
     */
    attach: (selector, statusMsg = "Optimizing your photos...") => {
        const forms = document.querySelectorAll(selector);

        forms.forEach(form => {
            form.addEventListener('submit', async function (e) {
                if (this.dataset.compressed === 'true') return;

                // Check if there are actually files to compress
                const fileInputs = this.querySelectorAll('input[type="file"]');
                let hasFiles = false;
                fileInputs.forEach(input => { if (input.files.length > 0) hasFiles = true; });

                if (!hasFiles) return;

                e.preventDefault();

                // Show Processing Overlay
                let overlay = document.getElementById('compressionOverlay');
                if (!overlay) {
                    overlay = document.createElement('div');
                    overlay.id = 'compressionOverlay';
                    overlay.style = "position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.8);z-index:9999;display:flex;flex-direction:column;justify-content:center;align-items:center;color:white;";
                    overlay.innerHTML = `
                    <div class="spinner-border text-warning mb-3" role="status" style="width: 3rem; height: 3rem;"></div>
                    <h4 class="mb-2">${statusMsg}</h4>
                    <p class="text-white-50 small">This makes your upload 90% faster and prevents timeouts.</p>
                `;
                    document.body.appendChild(overlay);
                }
                overlay.style.display = 'flex';

                for (const input of fileInputs) {
                    if (input.files.length > 0) {
                        const dt = new DataTransfer();
                        for (let i = 0; i < input.files.length; i++) {
                            const file = input.files[i];
                            if (file.type.startsWith('image/')) {
                                const compressed = await ImageCompressor.compress(file);
                                dt.items.add(compressed);
                            } else {
                                dt.items.add(file);
                            }
                        }
                        input.files = dt.files;
                    }
                }

                this.dataset.compressed = 'true';
                overlay.innerHTML = `
                <div class="spinner-border text-success mb-3" role="status" style="width: 3rem; height: 3rem;"></div>
                <h4>Uploading!</h4>
                <p class="text-white-50">Please wait, your data is being sent.</p>
            `;
                setTimeout(() => {
                    this.submit();
                }, 500);
            });
        });
    }
};
