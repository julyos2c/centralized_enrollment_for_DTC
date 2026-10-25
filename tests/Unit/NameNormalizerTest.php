<?php

namespace Tests\Unit;

use App\Models\Application;
use App\Support\NameNormalizer;
use PHPUnit\Framework\TestCase;

class NameNormalizerTest extends TestCase
{
    public function test_affixes_add_periods_only_for_known_abbreviations(): void
    {
        foreach ([['Mr', 'honorific', 'Mr.'], ['mrs.', 'honorific', 'Mrs.'],
            ['Dr Emerita', 'honorific', 'Dr. Emerita'], ['Professor', 'honorific', 'Professor'],
            ['Miss', 'honorific', 'Miss'], ['jr', 'suffix', 'Jr.'], ['Sr.', 'suffix', 'Sr.'],
            ['iii', 'suffix', 'III'], ['', 'suffix', ''], [null, 'honorific', null]] as [$value, $field, $expected]) {
            $this->assertSame($expected, NameNormalizer::normalizeAffix($value, $field));
            $this->assertSame($expected, NameNormalizer::normalizeAffix($expected, $field));
        }
        $application = new Application(['honorific' => 'Mr', 'suffix' => 'jr']);
        $this->assertSame('Mr.', $application->getAttributes()['honorific']);
        $this->assertSame('Jr.', $application->getAttributes()['suffix']);
        $application->setRawAttributes(['honorific' => 'Dr', 'suffix' => 'III']);
        $this->assertSame('Dr.', $application->honorific);
        $this->assertSame('Dr', $application->getAttributes()['honorific']);
        $this->assertSame('III', $application->suffix);
    }

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
