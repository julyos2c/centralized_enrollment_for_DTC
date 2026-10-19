{{--
    DTC Nexus Confirmation Modal
    ─────────────────────────────────────────────────────────────────────────
    A reusable Bootstrap modal for destructive-action confirmation.
    Triggered via data-attributes on any button/link.

    Trigger attributes (add to any element, e.g. a <button> or <a>):
        data-dtc-confirm                   — activates the modal
        data-dtc-confirm-title             — modal heading (optional)
        data-dtc-confirm-message           — body text  (optional)
        data-dtc-confirm-ok                — confirm button label (optional)
        data-dtc-confirm-cancel            — cancel button label  (optional)
        data-dtc-confirm-type              — "danger" | "warning" (default: danger)
        data-dtc-confirm-form              — CSS selector of form to submit on confirm
        data-dtc-confirm-href              — URL to navigate to on confirm

    Example — delete form:
        <button type="submit"
                data-dtc-confirm
                data-dtc-confirm-title="Delete User?"
                data-dtc-confirm-message="This action cannot be undone."
                data-dtc-confirm-ok="Delete"
                data-dtc-confirm-form="#delete-form-{{ $user->id }}">
            Delete
        </button>
        <form id="delete-form-{{ $user->id }}" method="POST" ...>
            @csrf @method('DELETE')
        </form>

    Example — cancel appointment link:
        <a href="#"
           data-dtc-confirm
           data-dtc-confirm-title="Cancel Appointment?"
           data-dtc-confirm-message="Are you sure?"
           data-dtc-confirm-ok="Cancel Appointment"
           data-dtc-confirm-type="warning"
           data-dtc-confirm-href="{{ route('student.appointments.cancel', $appt) }}">
            Cancel
        </a>
--}}

<div class="modal fade dtc-confirm-modal"
     id="dtcConfirmModal"
     tabindex="-1"
     role="dialog"
     aria-labelledby="dtcConfirmTitle"
     aria-modal="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">

            {{-- Icon + Header --}}
            <div class="modal-header dtc-confirm-header border-0 pb-0">
                <div class="dtc-confirm-icon-wrap" id="dtcConfirmIconWrap">
                    <i data-lucide="alert-triangle" id="dtcConfirmIcon"></i>
                </div>
                <button type="button"
                        class="close dtc-confirm-close"
                        data-dismiss="modal"
                        aria-label="Close">
                    <i data-lucide="x" aria-hidden="true"></i>
                </button>
            </div>

            {{-- Body --}}
            <div class="modal-body text-center pt-1 px-4 pb-2">
                <h5 class="dtc-confirm-title" id="dtcConfirmTitle">Are you sure?</h5>
                <p class="dtc-confirm-message" id="dtcConfirmMessage">This action cannot be undone.</p>
            </div>

            {{-- Footer --}}
            <div class="modal-footer border-0 justify-content-center gap-2 pb-4">
                <button type="button"
                        class="dtc-btn dtc-btn-ghost"
                        id="dtcConfirmCancel"
                        data-dismiss="modal">
                    Keep
                </button>
                <button type="button"
                        class="dtc-btn"
                        id="dtcConfirmOk">
                    Confirm
                </button>
            </div>

        </div>
    </div>
</div>
