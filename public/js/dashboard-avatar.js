document.addEventListener('DOMContentLoaded', function () {
    const trigger = document.querySelector('[data-avatar-picker-trigger]');
    const input = document.getElementById('dashboardAvatarInput');
    const form = document.getElementById('dashboardAvatarForm');
    const cropModal = $('#dashboardAvatarCropModal');
    const confirmModal = $('#dashboardAvatarConfirmModal');
    const cropImage = document.getElementById('dashboardCropImage');
    const confirmPreview = document.getElementById('dashboardAvatarConfirmPreview');
    let cropper = null;
    let croppedFile = null;
    let previewUrl = null;

    if (!trigger || !input || !form || typeof Cropper === 'undefined') return;

    trigger.addEventListener('click', function () {
        input.click();
    });

    input.addEventListener('change', function () {
        if (!input.files || !input.files.length) return;
        const reader = new FileReader();
        reader.onload = function (event) {
            cropImage.src = event.target.result;
            cropModal.modal('show');
        };
        reader.readAsDataURL(input.files[0]);
    });

    cropModal.on('shown.bs.modal', function () {
        if (cropper) cropper.destroy();
        cropper = new Cropper(cropImage, {
            aspectRatio: 1,
            viewMode: 1,
            dragMode: 'move',
            autoCropArea: .85,
            guides: false,
            center: true,
            highlight: false,
            cropBoxMovable: true,
            cropBoxResizable: true,
            toggleDragModeOnDblclick: false,
        });
        if (window.lucide) window.lucide.createIcons();
    });

    cropModal.on('hidden.bs.modal', function () {
        if (cropper) {
            cropper.destroy();
            cropper = null;
        }
        if (!croppedFile) {
            input.value = '';
        } else {
            previewUrl = URL.createObjectURL(croppedFile);
            confirmPreview.src = previewUrl;
            confirmModal.modal('show');
        }
    });

    document.getElementById('dashboardCropRotateLeft').addEventListener('click', function () {
        if (cropper) cropper.rotate(-90);
    });
    document.getElementById('dashboardCropRotateRight').addEventListener('click', function () {
        if (cropper) cropper.rotate(90);
    });
    document.getElementById('dashboardCropApply').addEventListener('click', function () {
        if (!cropper) return;
        cropper.getCroppedCanvas({
            width: 400,
            height: 400,
            imageSmoothingEnabled: true,
            imageSmoothingQuality: 'high',
        }).toBlob(function (blob) {
            if (!blob) return;
            croppedFile = new File([blob], 'avatar.jpg', { type: 'image/jpeg' });
            cropModal.modal('hide');
        }, 'image/jpeg', .92);
    });

    confirmModal.on('hidden.bs.modal', function () {
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        previewUrl = null;
        confirmPreview.removeAttribute('src');
        croppedFile = null;
        input.value = '';
    });
    document.getElementById('dashboardAvatarConfirmApply').addEventListener('click', function () {
        if (!croppedFile) return;
        const transfer = new DataTransfer();
        transfer.items.add(croppedFile);
        input.files = transfer.files;
        if (form.requestSubmit) form.requestSubmit();
        else form.submit();
    });
});
