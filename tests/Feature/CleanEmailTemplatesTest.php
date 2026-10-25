<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\DocumentRequest;
use App\Models\StudentProfile;
use App\Models\User;
use Tests\TestCase;

class CleanEmailTemplatesTest extends TestCase
{
    public function test_logo_is_embedded_as_an_inline_png_without_a_localhost_image_url(): void
    {
        $email = new \Symfony\Component\Mime\Email();
        $message = new \Illuminate\Mail\Message($email);
        $html = view('emails.workflow-update', ['recipientName' => 'Test', 'eventTitle' => 'Update',
            'eventMessage' => 'Recorded.', 'actionUrl' => null, 'actionLabel' => 'View', 'message' => $message])->render();
        $this->assertStringContainsString('<img src="cid:', $html);
        $this->assertStringContainsString('width="40" height="40" alt="DTC logo"', $html);
        $this->assertStringContainsString('Danao Technological College', $html);
        $this->assertStringNotContainsString('/images/DTC-LOGO', $html);
        $attachments = $email->getAttachments();
        $this->assertCount(1, $attachments);
        $this->assertStringStartsWith('inline', $attachments[0]->getPreparedHeaders()->get('Content-Disposition')->getBodyAsString());
        $this->assertSame('image', $attachments[0]->getMediaType());
        $this->assertSame('png', $attachments[0]->getMediaSubtype());
    }

    public function test_all_html_emails_render_with_their_essential_details_and_escaped_content(): void
    {
        $application = new Application(['first_name' => 'Test', 'last_name' => 'Applicant',
            'reference_no' => 'APP-EMAIL-123', 'email' => 'test@example.test', 'remarks' => "Correct the details.\n<script>alert(1)</script>"]);
        $profile = new StudentProfile(['first_name' => 'Test', 'student_number' => '2026-12345']);
        $document = new DocumentRequest(['copies' => 2, 'status' => 'ready', 'payment_status' => 'verified']);
        $document->setRelation('user', new User(['name' => 'Test User']));
        $url = 'https://example.test/password/token?email=test%40example.test';
        $cases = [
            ['workflow-update', ['recipientName' => 'Test User', 'eventTitle' => 'Payment recorded',
                'eventMessage' => '<script>untrusted</script>', 'actionUrl' => $url, 'actionLabel' => 'View payment'], ['Payment recorded', '&lt;script&gt;untrusted&lt;/script&gt;', $url]],
            ['application-approved', ['application' => $application, 'setupUrl' => $url, 'expiresInMinutes' => 60], ['APP-EMAIL-123', 'test@example.test', '60 minutes', $url]],
            ['reset-password', ['email' => 'test@example.test', 'resetUrl' => $url, 'expiresInMinutes' => 60], ['test@example.test', '60 minutes', 'Your password will stay unchanged.', $url]],
            ['application-submitted', ['referenceNo' => 'APP-EMAIL-123'], ['APP-EMAIL-123', 'Submission is not approval.']],
            ['application-revision-required', ['application' => $application], ['APP-EMAIL-123', 'Registrar', '&lt;script&gt;alert(1)&lt;/script&gt;']],
            ['application-rejected', ['application' => $application], ['APP-EMAIL-123', 'Reason', '&lt;script&gt;alert(1)&lt;/script&gt;']],
            ['portal-account-invitation', ['profile' => $profile, 'activationUrl' => $url, 'expiresInMinutes' => 61], ['2026-12345', '61 minutes', $url]],
            ['document-request-update', ['documentRequest' => $document, 'eventMessage' => 'Ready for collection.'], ['Ready for collection.', '2 copies', 'Verified']],
        ];
        foreach ($cases as [$template, $data, $expected]) {
            $html = view('emails.'.$template, $data)->render();
            $this->assertStringContainsString('<!doctype html>', $html, $template);
            $this->assertSame(1, substr_count($html, '<h1'), $template);
            foreach ($expected as $text) $this->assertStringContainsString($text, $html, $template);
            $this->assertStringNotContainsString('<script>', $html, $template);
        }
        $html = view('emails.workflow-update', ['recipientName' => 'Test', 'eventTitle' => 'Update', 'eventMessage' => 'Recorded.', 'actionUrl' => null, 'actionLabel' => 'View'])->render();
        $this->assertStringNotContainsString('Button not working?', $html);
        $reset = view('emails.reset-password', ['email' => 'test@example.test', 'resetUrl' => $url, 'expiresInMinutes' => 60])->render();
        $this->assertSame(1, substr_count($reset, 'Link expires in 60 minutes.'));
        $this->assertLessThan(strpos($reset, 'Button not working?'), strpos($reset, 'Your password will stay unchanged.'));
    }
}
