{{-- Shared DTC confirmation dialog. Configure actions with data-dtc-confirm-* attributes. --}}
<div class="modal dtc-confirm-modal is-neutral"
     id="dtcConfirmModal"
     tabindex="-1"
     role="dialog"
     aria-modal="true"
     aria-labelledby="dtcConfirmTitle"
     aria-describedby="dtcConfirmMessage"
     aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <button type="button" class="dtc-confirm-close" id="dtcConfirmClose" aria-label="Close">
                <i data-lucide="x" aria-hidden="true"></i>
            </button>
            <div class="dtc-confirm-icon-wrap" aria-hidden="true">
                <i data-lucide="info" id="dtcConfirmIcon"></i>
            </div>
            <div class="modal-body">
                <h2 class="dtc-confirm-title" id="dtcConfirmTitle">Confirm action?</h2>
                <p class="dtc-confirm-message" id="dtcConfirmMessage">Please confirm that you want to continue.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="dtc-confirm-cancel" id="dtcConfirmCancel">Cancel</button>
                <button type="button" class="dtc-confirm-ok" id="dtcConfirmOk">Confirm</button>
            </div>
        </div>
    </div>
</div>
