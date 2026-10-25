# Portal payment pages

New applicants, students and alumni open payment details as full pages:

- Enrollment: `portal.enrollment.payment-info`.
- Documents: `portal.payments.document`.
- Legacy appointment payments: `portal.payments.appointment`.

Dashboard, request-list and payment-notification links use these destinations. Old owned enrollment links with `?payment=` redirect to the enrollment payment page. Appointment payments linked to a document request redirect to that document's payment page.

Each page checks ownership. Existing submission validation, private proof access, verification and rejection rules remain in their original controllers. Pending and verified payments do not offer another proof form. Alumni document resubmission retains its rejected-GCash restriction; initial document request creation already has a dedicated page.

Enrollment uses a plain heading, fee summary and responsive payment sections with normal document scrolling. Colors inherit portal theme variables. Staff payment processing and booking-edit dialogs retain their existing behavior.

Coverage: `PortalPaymentPagesTest`, `NotificationExperienceTest`, and existing document/enrollment payment feature tests verify role access, ownership, payment states, uploads and redirects.
