/* ============================================================
   Profile Settings
   public/js/profile.js
   ============================================================ */

/* ═══════════════════════════════════════════════════════════
   AVATAR CROP — Facebook-style crop & preview modal
   ═══════════════════════════════════════════════════════════ */
(function () {
    const avatarInput = document.getElementById('avatarFileInput');
    const cropImg     = document.getElementById('cropImage');
    const applyBtn    = document.getElementById('cropApplyBtn');
    const rotLBtn     = document.getElementById('cropRotateLeft');
    const rotRBtn     = document.getElementById('cropRotateRight');
    const previewRow  = document.getElementById('avatarPreviewRow');
    const previewImg  = document.getElementById('avatarPreview');
    const clearBtn    = document.getElementById('clearAvatarBtn');

    let cropper      = null;
    let croppedBlob  = null;   // final blob injected into the form
    let originalName = 'avatar.jpg';

    /* ── Open modal when user picks a file ──────────────────── */
    if (avatarInput) {
        avatarInput.addEventListener('change', function () {
            const file = this.files[0];
            if (!file) return;
            originalName = file.name;

            const reader = new FileReader();
            reader.onload = function (e) {
                cropImg.src = e.target.result;

                // destroy previous instance if any
                if (cropper) { cropper.destroy(); cropper = null; }

                // open Bootstrap modal
                $('#avatarCropModal').modal('show');
            };
            reader.readAsDataURL(file);

            // reset so the same file can be re-selected
            this.value = '';
        });
    }

    /* ── Init Cropper.js when modal is fully shown ───────────── */
    $('#avatarCropModal').on('shown.bs.modal', function () {
        cropper = new Cropper(cropImg, {
            aspectRatio: 1,
            viewMode: 1,
            dragMode: 'move',
            autoCropArea: 0.85,
            restore: false,
            guides: false,
            center: true,
            highlight: false,
            cropBoxMovable: true,
            cropBoxResizable: true,
            toggleDragModeOnDblclick: false,
            // live preview into the three circles
            preview: [
                document.getElementById('previewLg'),
                document.getElementById('previewMd'),
                document.getElementById('previewSm'),
            ],
        });

        // re-render Lucide icons inside the freshly shown modal
        if (typeof lucide !== 'undefined') lucide.createIcons();
    });

    /* ── Destroy cropper when modal hides ────────────────────── */
    $('#avatarCropModal').on('hidden.bs.modal', function () {
        if (cropper) { cropper.destroy(); cropper = null; }
    });

    /* ── Rotate buttons ──────────────────────────────────────── */
    if (rotLBtn) rotLBtn.addEventListener('click', function () {
        if (cropper) cropper.rotate(-90);
    });
    if (rotRBtn) rotRBtn.addEventListener('click', function () {
        if (cropper) cropper.rotate(90);
    });

    /* ── Apply: export canvas → Blob → inject into form ─────── */
    if (applyBtn) {
        applyBtn.addEventListener('click', function () {
            if (!cropper) return;

            const canvas = cropper.getCroppedCanvas({
                width: 400,
                height: 400,
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high',
            });

            canvas.toBlob(function (blob) {
                croppedBlob = blob;

                // Inject blob as a File into the hidden <input type="file">
                const ext   = /\.png$/i.test(originalName) ? 'png' : 'jpeg';
                const mime  = ext === 'png' ? 'image/png' : 'image/jpeg';
                const fname = 'avatar.' + ext;

                try {
                    const dataTransfer = new DataTransfer();
                    dataTransfer.items.add(new File([blob], fname, { type: mime }));
                    avatarInput.files = dataTransfer.files;
                } catch (e) {
                    // Safari fallback — store blob and attach on submit
                    avatarInput._croppedBlob = blob;
                    avatarInput._croppedName = fname;
                    avatarInput._croppedMime = mime;
                }

                // Show the small preview strip below the form fields
                const blobUrl = URL.createObjectURL(blob);
                previewImg.onload = function () { URL.revokeObjectURL(blobUrl); };
                previewImg.src = blobUrl;
                previewRow.style.display = 'flex';

                // close modal
                $('#avatarCropModal').modal('hide');
            }, 'image/jpeg', 0.92);
        });
    }

    /* ── Clear avatar selection ──────────────────────────────── */
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            avatarInput.value = '';
            croppedBlob = null;
            avatarInput._croppedBlob = null;
            avatarInput._croppedName = null;
            avatarInput._croppedMime = null;
            previewImg.removeAttribute('src');
            previewRow.style.display = 'none';
        });
    }

    /* ── Safari fallback: append blob manually on form submit ─── */
    const profileForm = document.getElementById('profileForm');
    if (profileForm) {
        profileForm.addEventListener('submit', function (e) {
            if (avatarInput._croppedBlob && avatarInput.files.length === 0) {
                e.preventDefault();
                const fd = new FormData(profileForm);
                fd.set('avatar', avatarInput._croppedBlob, avatarInput._croppedName);
                fetch(profileForm.action, {
                    method: 'POST',
                    body: fd,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                }).then(function (res) {
                    if (res.redirected) { window.location.href = res.url; }
                });
            }
        });
    }
})();

/* ═══════════════════════════════════════════════════════════
   PASSWORD VISIBILITY TOGGLE
   ═══════════════════════════════════════════════════════════ */
document.querySelectorAll('.ps-pw-toggle').forEach(function (btn) {
    btn.addEventListener('click', function () {
        const input = document.getElementById(this.dataset.target);
        if (!input) return;
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        const icon = this.querySelector('[data-lucide]');
        if (icon) {
            icon.setAttribute('data-lucide', show ? 'eye-off' : 'eye');
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }
    });
});

/* ═══════════════════════════════════════════════════════════
   PASSWORD STRENGTH METER
   ═══════════════════════════════════════════════════════════ */
function updateStrength(value) {
    const fill  = document.getElementById('strengthFill');
    const label = document.getElementById('strengthLabel');
    if (!fill || !label) return;

    let score = 0;
    if (value.length >= 8)          score++;
    if (/[A-Z]/.test(value))        score++;
    if (/[0-9]/.test(value))        score++;
    if (/[^A-Za-z0-9]/.test(value)) score++;

    const levels = [
        { pct: '0%',   color: '#e5e9f0', text: 'Enter a new password', textColor: '#9ca3af' },
        { pct: '25%',  color: '#ef4444', text: 'Weak',                  textColor: '#dc2626' },
        { pct: '50%',  color: '#f59e0b', text: 'Fair',                  textColor: '#b45309' },
        { pct: '75%',  color: '#3b82f6', text: 'Good',                  textColor: '#1d4ed8' },
        { pct: '100%', color: '#22c55e', text: 'Strong',                textColor: '#15803d' },
    ];

    const lvl = value.length === 0 ? levels[0] : (levels[score] || levels[1]);
    fill.style.width      = lvl.pct;
    fill.style.background = lvl.color;
    label.textContent     = lvl.text;
    label.style.color     = lvl.textColor;
}