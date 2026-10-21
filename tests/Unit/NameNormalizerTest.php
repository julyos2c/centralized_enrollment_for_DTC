<?php

namespace Tests\Unit;

use App\Models\Application;
use App\Support\NameNormalizer;
use PHPUnit\Framework\TestCase;

class NameNormalizerTest extends TestCase
{
    /** @dataProvider nameExamples */
    public function test_it_normalizes_case_spacing_and_compound_names(string $input, string $expected): void
    {
        $this->assertSame($expected, NameNormalizer::normalize($input));
    }

    public static function nameExamples(): array
    {
        return [
            ['DELA CRUZ', 'Dela Cruz'],
            ['dela cruz', 'Dela Cruz'],
            ['jUAN dELA cRUZ', 'Juan Dela Cruz'],
            ['  MARIA   ANGELICA SANTOS  ', 'Maria Angelica Santos'],
            ['DE LOS SANTOS', 'De Los Santos'],
            ['VAN DER MEER', 'Van Der Meer'],
            ['ANNE-MARIE', 'Anne-Marie'],
            ["O'CONNOR", "O'Connor"],
        ];
    }

    public function test_application_name_accessors_format_historical_values_without_rewriting_raw_attributes(): void
    {
        $application = new Application();
        $application->setRawAttributes([
            'first_name' => 'jUAN',
            'middle_name' => '  aNGEL  ',
            'last_name' => 'DELA CRUZ',
        ]);

        $this->assertSame('Juan', $application->first_name);
        $this->assertSame('Angel', $application->middle_name);
        $this->assertSame('Dela Cruz', $application->last_name);
        $this->assertSame('jUAN', $application->getAttributes()['first_name']);
    }
}
