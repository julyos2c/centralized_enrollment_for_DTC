# Help Center

Signed-in users open **System → Help Center**, at `/help`. The page uses the existing account/session middleware and allows the six existing roles. It is an initial, server-rendered help page; it does not include an AI assistant, public article publishing, or an article management database.

Guidance and destination routes live in `app/Http/Controllers/HelpCenterController.php`. Add questions within the appropriate role condition. Role filtering must happen before searching and rendering. Keep guidance aligned with implemented behavior; do not hardcode college fees, deadlines, office hours or requirements.

Maintain official registrar contact details in **Admin → Settings → General → Registrar contact**. Empty fields are omitted. Without a valid configured registrar email, the page directs users to visit the office. There are no sample contact details or migrations to apply.

Search accepts a maximum of 150 characters and searches only topics available to the signed-in user. The page works without JavaScript and uses native expandable questions, existing theme tokens and responsive layouts.

Run `php vendor/phpunit/phpunit/phpunit --filter HelpCenterTest` for access, role filtering, search validation and escaping coverage. Manual verification at phone, tablet and desktop widths remains required.
