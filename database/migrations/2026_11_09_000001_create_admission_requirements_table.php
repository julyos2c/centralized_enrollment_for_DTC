<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Configurable admission requirements. The initial rows reproduce the
     * upload slots that were previously hardcoded in the public application
     * controller, with the same applicant types and the same "optional"
     * behaviour, so existing applications keep matching their documents.
     */
    public function up(): void
    {
        Schema::create('admission_requirements', function (Blueprint $table) {
            $table->id();
            // Stable machine key; also the upload field name documents[{code}].
            $table->string('code', 60)->unique();
            $table->string('name');
            $table->string('description', 500)->nullable();
            // null = applies to every applicant type.
            $table->json('applicant_types')->nullable();
            // Optional applicability condition, e.g. "married".
            $table->string('condition', 30)->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('allowed_extensions', 100)->default('pdf,jpg,jpeg,png');
            $table->unsignedInteger('max_size_kb')->default(5120);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        $now = now();
        $rows = [
            ['form_138', 'Form 138 or Equivalent', 'Issued by your originating school.', ['new_student'], null, true, 10],
            ['transfer_credentials', 'Certificate of Transfer Credentials', 'Issued by your originating school.', ['transferee'], null, true, 20],
            ['transcript_of_records', 'Transcript of Records', 'For preliminary evaluation.', ['transferee'], null, true, 30],
            ['good_moral_certificate', 'Certificate of Good Moral Character', 'Issued by your originating school.', ['new_student', 'transferee'], null, true, 40],
            ['birth_certificate', 'PSA Birth Certificate', 'Digital copy for preliminary screening.', ['new_student', 'transferee'], null, true, 50],
            ['marriage_certificate', 'Marriage Certificate (PSA)', 'Applicable only to married applicants.', null, 'married', true, 60],
            // Offered but inactive until the school enables it.
            ['id_photo', '2x2 ID Picture', 'Recent photo with a plain white background.', ['new_student', 'transferee'], null, false, 70],
        ];

        foreach ($rows as [$code, $name, $description, $types, $condition, $active, $order]) {
            DB::table('admission_requirements')->insertOrIgnore([
                'code' => $code,
                'name' => $name,
                'description' => $description,
                'applicant_types' => $types === null ? null : json_encode($types),
                'condition' => $condition,
                'is_required' => false,
                'is_active' => $active,
                'allowed_extensions' => $code === 'id_photo' ? 'jpg,jpeg,png' : 'pdf,jpg,jpeg,png',
                'max_size_kb' => 5120,
                'sort_order' => $order,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_requirements');
    }
};
