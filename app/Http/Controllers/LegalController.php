<?php

namespace App\Http\Controllers;

use App\Support\LegalDocuments;

/** Public pages for the Terms and Conditions and the Privacy Notice. */
class LegalController extends Controller
{
    public function terms()
    {
        return view('legal.page', ['document' => 'terms', 'legal' => LegalDocuments::context()]);
    }

    public function privacy()
    {
        return view('legal.page', ['document' => 'privacy', 'legal' => LegalDocuments::context()]);
    }
}
