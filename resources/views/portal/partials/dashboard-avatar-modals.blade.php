<div class="modal fade" id="dashboardAvatarCropModal" tabindex="-1" role="dialog" aria-modal="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:520px;">
        <div class="modal-content ps-crop-modal">
            <div class="modal-header ps-crop-header">
                <div class="d-flex align-items-center gap-2">
                    <div class="ps-crop-header-icon"><i data-lucide="image" style="width:16px;height:16px;"></i></div>
                    <h6 class="modal-title mb-0">Crop Profile Photo</h6>
                </div>
                <button type="button" class="close ps-crop-close" data-dismiss="modal" aria-label="Close">
                    <i data-lucide="x" style="width:18px;height:18px;"></i>
                </button>
            </div>
            <div class="modal-body ps-crop-body">
                <div class="ps-crop-hint"><i data-lucide="move" style="width:13px;height:13px;"></i> Drag to reposition, scroll to zoom, or rotate the image.</div>
                <div class="ps-crop-container">
                    <img id="dashboardCropImage" src="" alt="Crop profile photo" style="max-width:100%;display:block;">
                </div>
            </div>
            <div class="modal-footer ps-crop-footer">
                <button type="button" class="btn ps-crop-btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn ps-crop-btn-rotate" id="dashboardCropRotateLeft" title="Rotate left"><i data-lucide="rotate-ccw" style="width:14px;height:14px;"></i></button>
                <button type="button" class="btn ps-crop-btn-rotate" id="dashboardCropRotateRight" title="Rotate right"><i data-lucide="rotate-cw" style="width:14px;height:14px;"></i></button>
                <button type="button" class="btn ps-crop-btn-primary" id="dashboardCropApply">Crop Photo</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="dashboardAvatarConfirmModal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="dashboardAvatarConfirmTitle">
    <div class="modal-dialog modal-dialog-centered dtc-avatar-confirm-dialog" role="document">
        <div class="modal-content dtc-avatar-confirm-card">
            <div class="modal-body text-center">
                <img id="dashboardAvatarConfirmPreview" class="dtc-avatar-confirm-preview" src="" alt="Cropped profile photo preview">
                <h5 id="dashboardAvatarConfirmTitle" class="dtc-avatar-confirm-title">Use this profile photo?</h5>
                <p class="dtc-avatar-confirm-copy">This will replace your current profile photo.</p>
                <div class="dtc-avatar-confirm-actions">
                    <button type="button" class="btn dtc-avatar-confirm-cancel" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn dtc-avatar-confirm-apply" id="dashboardAvatarConfirmApply">Save photo</button>
                </div>
            </div>
        </div>
    </div>
</div>
