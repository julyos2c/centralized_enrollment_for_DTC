<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicImportBatch extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'expires_at' => 'datetime',
        'previewed_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function importer()
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function studentProfiles()
    {
        return $this->belongsToMany(StudentProfile::class, 'academic_import_batch_profiles')
            ->withTimestamps();
    }
}
