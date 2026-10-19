<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Program;
use App\Models\CourseSubject;

class SubjectSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedBSIS();
        $this->seedBSCRIM();
        $this->seedBSTM();
        $this->seedBTVTEd();
    }

    // ─────────────────────────────────────────────────────────────
    // BS Information Systems
    // ─────────────────────────────────────────────────────────────
    private function seedBSIS(): void
    {
        $program = Program::firstOrCreate(
            ['code' => 'BSIS'],
            ['name' => 'BS Information Systems']
        );

        $subjects = [
            // Year 1 - Sem 1
            ['subject_code' => 'CC101',  'subject_name' => 'Introduction to Computing',               'units' => 3.0, 'year_level' => 1, 'semester' => 1],
            ['subject_code' => 'CC102',  'subject_name' => 'Computer Programming 1',                  'units' => 3.0, 'year_level' => 1, 'semester' => 1],
            ['subject_code' => 'MATH101','subject_name' => 'Mathematics in the Modern World',         'units' => 3.0, 'year_level' => 1, 'semester' => 1],
            ['subject_code' => 'GE101',  'subject_name' => 'Purposive Communication',                 'units' => 3.0, 'year_level' => 1, 'semester' => 1],
            ['subject_code' => 'GE102',  'subject_name' => 'Understanding the Self',                  'units' => 3.0, 'year_level' => 1, 'semester' => 1],
            ['subject_code' => 'PE101',  'subject_name' => 'Physical Education 1 (Movement Enhancement)', 'units' => 2.0, 'year_level' => 1, 'semester' => 1],
            ['subject_code' => 'NSTP101','subject_name' => 'National Service Training Program 1',    'units' => 3.0, 'year_level' => 1, 'semester' => 1],

            // Year 1 - Sem 2
            ['subject_code' => 'CC103',  'subject_name' => 'Computer Programming 2',                  'units' => 3.0, 'year_level' => 1, 'semester' => 2],
            ['subject_code' => 'CC104',  'subject_name' => 'Data Structures and Algorithms',          'units' => 3.0, 'year_level' => 1, 'semester' => 2],
            ['subject_code' => 'IS101',  'subject_name' => 'Information Management',                  'units' => 3.0, 'year_level' => 1, 'semester' => 2],
            ['subject_code' => 'MATH102','subject_name' => 'Discrete Mathematics',                    'units' => 3.0, 'year_level' => 1, 'semester' => 2],
            ['subject_code' => 'GE103',  'subject_name' => 'Readings in Philippine History',          'units' => 3.0, 'year_level' => 1, 'semester' => 2],
            ['subject_code' => 'PE102',  'subject_name' => 'Physical Education 2 (Fitness Exercises)','units' => 2.0, 'year_level' => 1, 'semester' => 2],
            ['subject_code' => 'NSTP102','subject_name' => 'National Service Training Program 2',    'units' => 3.0, 'year_level' => 1, 'semester' => 2],

            // Year 2 - Sem 1
            ['subject_code' => 'IS201',  'subject_name' => 'Object-Oriented Programming',             'units' => 3.0, 'year_level' => 2, 'semester' => 1],
            ['subject_code' => 'IS202',  'subject_name' => 'Database Management Systems',             'units' => 3.0, 'year_level' => 2, 'semester' => 1],
            ['subject_code' => 'IS203',  'subject_name' => 'Systems Analysis and Design',             'units' => 3.0, 'year_level' => 2, 'semester' => 1],
            ['subject_code' => 'IS204',  'subject_name' => 'Web Development 1',                       'units' => 3.0, 'year_level' => 2, 'semester' => 1],
            ['subject_code' => 'GE201',  'subject_name' => 'Ethics (Good Governance)',                'units' => 3.0, 'year_level' => 2, 'semester' => 1],
            ['subject_code' => 'PE201',  'subject_name' => 'Physical Education 3 (Team Sports)',      'units' => 2.0, 'year_level' => 2, 'semester' => 1],

            // Year 2 - Sem 2
            ['subject_code' => 'IS205',  'subject_name' => 'Web Development 2',                       'units' => 3.0, 'year_level' => 2, 'semester' => 2],
            ['subject_code' => 'IS206',  'subject_name' => 'Network Fundamentals',                    'units' => 3.0, 'year_level' => 2, 'semester' => 2],
            ['subject_code' => 'IS207',  'subject_name' => 'Human-Computer Interaction',              'units' => 3.0, 'year_level' => 2, 'semester' => 2],
            ['subject_code' => 'IS208',  'subject_name' => 'Quantitative Methods',                    'units' => 3.0, 'year_level' => 2, 'semester' => 2],
            ['subject_code' => 'GE202',  'subject_name' => 'Art Appreciation',                        'units' => 3.0, 'year_level' => 2, 'semester' => 2],
            ['subject_code' => 'PE202',  'subject_name' => 'Physical Education 4 (Individual Sports)','units' => 2.0, 'year_level' => 2, 'semester' => 2],

            // Year 3 - Sem 1
            ['subject_code' => 'IS301',  'subject_name' => 'Software Engineering',                    'units' => 3.0, 'year_level' => 3, 'semester' => 1],
            ['subject_code' => 'IS302',  'subject_name' => 'Information Assurance and Security',      'units' => 3.0, 'year_level' => 3, 'semester' => 1],
            ['subject_code' => 'IS303',  'subject_name' => 'Applications Development and Emerging Technologies', 'units' => 3.0, 'year_level' => 3, 'semester' => 1],
            ['subject_code' => 'IS304',  'subject_name' => 'Integrative Programming and Technologies','units' => 3.0, 'year_level' => 3, 'semester' => 1],
            ['subject_code' => 'IS305',  'subject_name' => 'Project Management',                      'units' => 3.0, 'year_level' => 3, 'semester' => 1],

            // Year 3 - Sem 2
            ['subject_code' => 'IS306',  'subject_name' => 'Advanced Database Systems',               'units' => 3.0, 'year_level' => 3, 'semester' => 2],
            ['subject_code' => 'IS307',  'subject_name' => 'IS Strategy, Management and Acquisition', 'units' => 3.0, 'year_level' => 3, 'semester' => 2],
            ['subject_code' => 'IS308',  'subject_name' => 'Social and Professional Issues',          'units' => 3.0, 'year_level' => 3, 'semester' => 2],
            ['subject_code' => 'IS309',  'subject_name' => 'Technopreneurship',                       'units' => 3.0, 'year_level' => 3, 'semester' => 2],
            ['subject_code' => 'IS310',  'subject_name' => 'Practicum (300 hours)',                   'units' => 6.0, 'year_level' => 3, 'semester' => 2],

            // Year 4 - Sem 1
            ['subject_code' => 'IS401',  'subject_name' => 'Capstone Project 1',                      'units' => 3.0, 'year_level' => 4, 'semester' => 1],
            ['subject_code' => 'IS402',  'subject_name' => 'IS Thesis Writing 1',                     'units' => 3.0, 'year_level' => 4, 'semester' => 1],
            ['subject_code' => 'IS403',  'subject_name' => 'Cloud Computing',                         'units' => 3.0, 'year_level' => 4, 'semester' => 1],
            ['subject_code' => 'IS404',  'subject_name' => 'Systems Administration and Maintenance',  'units' => 3.0, 'year_level' => 4, 'semester' => 1],

            // Year 4 - Sem 2
            ['subject_code' => 'IS405',  'subject_name' => 'Capstone Project 2',                      'units' => 3.0, 'year_level' => 4, 'semester' => 2],
            ['subject_code' => 'IS406',  'subject_name' => 'IS Thesis Writing 2',                     'units' => 3.0, 'year_level' => 4, 'semester' => 2],
            ['subject_code' => 'IS407',  'subject_name' => 'Intelligent Systems',                     'units' => 3.0, 'year_level' => 4, 'semester' => 2],
        ];

        $this->insertSubjects($program, $subjects);
    }

    // ─────────────────────────────────────────────────────────────
    // BS Criminology
    // ─────────────────────────────────────────────────────────────
    private function seedBSCRIM(): void
    {
        $program = Program::firstOrCreate(
            ['code' => 'BSCRIM'],
            ['name' => 'BS Criminology']
        );

        $subjects = [
            // Year 1 - Sem 1
            ['subject_code' => 'CRIM101', 'subject_name' => 'Introduction to Criminology',            'units' => 3.0, 'year_level' => 1, 'semester' => 1],
            ['subject_code' => 'CRIM102', 'subject_name' => 'Criminal Law Book 1',                    'units' => 3.0, 'year_level' => 1, 'semester' => 1],
            ['subject_code' => 'CRIM103', 'subject_name' => 'Law Enforcement Administration',         'units' => 3.0, 'year_level' => 1, 'semester' => 1],
            ['subject_code' => 'GE101',   'subject_name' => 'Purposive Communication',                'units' => 3.0, 'year_level' => 1, 'semester' => 1],
            ['subject_code' => 'GE102',   'subject_name' => 'Understanding the Self',                 'units' => 3.0, 'year_level' => 1, 'semester' => 1],
            ['subject_code' => 'PE101',   'subject_name' => 'Physical Education 1',                   'units' => 2.0, 'year_level' => 1, 'semester' => 1],
            ['subject_code' => 'NSTP101', 'subject_name' => 'National Service Training Program 1',    'units' => 3.0, 'year_level' => 1, 'semester' => 1],

            // Year 1 - Sem 2
            ['subject_code' => 'CRIM104', 'subject_name' => 'Criminal Law Book 2',                    'units' => 3.0, 'year_level' => 1, 'semester' => 2],
            ['subject_code' => 'CRIM105', 'subject_name' => 'Human Behavior and Crisis Management',   'units' => 3.0, 'year_level' => 1, 'semester' => 2],
            ['subject_code' => 'CRIM106', 'subject_name' => 'Sociology of Crimes and Ethics',         'units' => 3.0, 'year_level' => 1, 'semester' => 2],
            ['subject_code' => 'GE103',   'subject_name' => 'Readings in Philippine History',         'units' => 3.0, 'year_level' => 1, 'semester' => 2],
            ['subject_code' => 'MATH101', 'subject_name' => 'Mathematics in the Modern World',        'units' => 3.0, 'year_level' => 1, 'semester' => 2],
            ['subject_code' => 'PE102',   'subject_name' => 'Physical Education 2',                   'units' => 2.0, 'year_level' => 1, 'semester' => 2],
            ['subject_code' => 'NSTP102', 'subject_name' => 'National Service Training Program 2',    'units' => 3.0, 'year_level' => 1, 'semester' => 2],

            // Year 2 - Sem 1
            ['subject_code' => 'CRIM201', 'subject_name' => 'Criminalistics 1 (Dactyloscopy)',        'units' => 3.0, 'year_level' => 2, 'semester' => 1],
            ['subject_code' => 'CRIM202', 'subject_name' => 'Crime Detection and Investigation 1',    'units' => 3.0, 'year_level' => 2, 'semester' => 1],
            ['subject_code' => 'CRIM203', 'subject_name' => 'Correctional Administration',            'units' => 3.0, 'year_level' => 2, 'semester' => 1],
            ['subject_code' => 'CRIM204', 'subject_name' => 'Juvenile Delinquency and Juvenile Justice System', 'units' => 3.0, 'year_level' => 2, 'semester' => 1],
            ['subject_code' => 'GE201',   'subject_name' => 'Ethics (Good Governance)',               'units' => 3.0, 'year_level' => 2, 'semester' => 1],
            ['subject_code' => 'PE201',   'subject_name' => 'Physical Education 3',                   'units' => 2.0, 'year_level' => 2, 'semester' => 1],

            // Year 2 - Sem 2
            ['subject_code' => 'CRIM205', 'subject_name' => 'Criminalistics 2 (Questioned Documents)','units' => 3.0, 'year_level' => 2, 'semester' => 2],
            ['subject_code' => 'CRIM206', 'subject_name' => 'Crime Detection and Investigation 2',    'units' => 3.0, 'year_level' => 2, 'semester' => 2],
            ['subject_code' => 'CRIM207', 'subject_name' => 'Special Crime Investigation',            'units' => 3.0, 'year_level' => 2, 'semester' => 2],
            ['subject_code' => 'CRIM208', 'subject_name' => 'Police Organization and Administration', 'units' => 3.0, 'year_level' => 2, 'semester' => 2],
            ['subject_code' => 'GE202',   'subject_name' => 'Art Appreciation',                       'units' => 3.0, 'year_level' => 2, 'semester' => 2],
            ['subject_code' => 'PE202',   'subject_name' => 'Physical Education 4',                   'units' => 2.0, 'year_level' => 2, 'semester' => 2],

            // Year 3 - Sem 1
            ['subject_code' => 'CRIM301', 'subject_name' => 'Criminalistics 3 (Polygraphy)',          'units' => 3.0, 'year_level' => 3, 'semester' => 1],
            ['subject_code' => 'CRIM302', 'subject_name' => 'Legal Medicine',                         'units' => 3.0, 'year_level' => 3, 'semester' => 1],
            ['subject_code' => 'CRIM303', 'subject_name' => 'Criminal Justice System',                'units' => 3.0, 'year_level' => 3, 'semester' => 1],
            ['subject_code' => 'CRIM304', 'subject_name' => 'Traffic Management',                     'units' => 3.0, 'year_level' => 3, 'semester' => 1],
            ['subject_code' => 'CRIM305', 'subject_name' => 'Research Methods in Criminology',        'units' => 3.0, 'year_level' => 3, 'semester' => 1],

            // Year 3 - Sem 2
            ['subject_code' => 'CRIM306', 'subject_name' => 'Criminalistics 4 (Forensic Ballistics)', 'units' => 3.0, 'year_level' => 3, 'semester' => 2],
            ['subject_code' => 'CRIM307', 'subject_name' => 'Cyber Crime Investigation',              'units' => 3.0, 'year_level' => 3, 'semester' => 2],
            ['subject_code' => 'CRIM308', 'subject_name' => 'Drug Education and Case Management',     'units' => 3.0, 'year_level' => 3, 'semester' => 2],
            ['subject_code' => 'CRIM309', 'subject_name' => 'Practicum (300 hours)',                  'units' => 6.0, 'year_level' => 3, 'semester' => 2],

            // Year 4 - Sem 1
            ['subject_code' => 'CRIM401', 'subject_name' => 'Thesis Writing 1',                       'units' => 3.0, 'year_level' => 4, 'semester' => 1],
            ['subject_code' => 'CRIM402', 'subject_name' => 'Private Security Administration',        'units' => 3.0, 'year_level' => 4, 'semester' => 1],
            ['subject_code' => 'CRIM403', 'subject_name' => 'Comparative Police Systems',             'units' => 3.0, 'year_level' => 4, 'semester' => 1],
            ['subject_code' => 'CRIM404', 'subject_name' => 'Industrial Security Management',         'units' => 3.0, 'year_level' => 4, 'semester' => 1],

            // Year 4 - Sem 2
            ['subject_code' => 'CRIM405', 'subject_name' => 'Thesis Writing 2',                       'units' => 3.0, 'year_level' => 4, 'semester' => 2],
            ['subject_code' => 'CRIM406', 'subject_name' => 'Terrorism and Insurgency Management',    'units' => 3.0, 'year_level' => 4, 'semester' => 2],
            ['subject_code' => 'CRIM407', 'subject_name' => 'Board Examination Review in Criminology','units' => 3.0, 'year_level' => 4, 'semester' => 2],
        ];

        $this->insertSubjects($program, $subjects);
    }

    // ─────────────────────────────────────────────────────────────
    // BS Tourism Management
    // ─────────────────────────────────────────────────────────────
    private function seedBSTM(): void
    {
        $program = Program::firstOrCreate(
            ['code' => 'BSTM'],
            ['name' => 'BS Tourism Management']
        );

        $subjects = [
            // Year 1 - Sem 1
            ['subject_code' => 'TM101',  'subject_name' => 'Introduction to Tourism and Hospitality', 'units' => 3.0, 'year_level' => 1, 'semester' => 1],
            ['subject_code' => 'TM102',  'subject_name' => 'Philippine Tourism Geography',             'units' => 3.0, 'year_level' => 1, 'semester' => 1],
            ['subject_code' => 'TM103',  'subject_name' => 'Tourism Industry Culture and Ethics',      'units' => 3.0, 'year_level' => 1, 'semester' => 1],
            ['subject_code' => 'GE101',  'subject_name' => 'Purposive Communication',                  'units' => 3.0, 'year_level' => 1, 'semester' => 1],
            ['subject_code' => 'GE102',  'subject_name' => 'Understanding the Self',                   'units' => 3.0, 'year_level' => 1, 'semester' => 1],
            ['subject_code' => 'PE101',  'subject_name' => 'Physical Education 1',                     'units' => 2.0, 'year_level' => 1, 'semester' => 1],
            ['subject_code' => 'NSTP101','subject_name' => 'National Service Training Program 1',      'units' => 3.0, 'year_level' => 1, 'semester' => 1],

            // Year 1 - Sem 2
            ['subject_code' => 'TM104',  'subject_name' => 'World Tourism Geography',                  'units' => 3.0, 'year_level' => 1, 'semester' => 2],
            ['subject_code' => 'TM105',  'subject_name' => 'Front Office Operations',                  'units' => 3.0, 'year_level' => 1, 'semester' => 2],
            ['subject_code' => 'TM106',  'subject_name' => 'Food and Beverage Service Operations',     'units' => 3.0, 'year_level' => 1, 'semester' => 2],
            ['subject_code' => 'GE103',  'subject_name' => 'Readings in Philippine History',           'units' => 3.0, 'year_level' => 1, 'semester' => 2],
            ['subject_code' => 'MATH101','subject_name' => 'Mathematics in the Modern World',          'units' => 3.0, 'year_level' => 1, 'semester' => 2],
            ['subject_code' => 'PE102',  'subject_name' => 'Physical Education 2',                     'units' => 2.0, 'year_level' => 1, 'semester' => 2],
            ['subject_code' => 'NSTP102','subject_name' => 'National Service Training Program 2',      'units' => 3.0, 'year_level' => 1, 'semester' => 2],

            // Year 2 - Sem 1
            ['subject_code' => 'TM201',  'subject_name' => 'Travel Agency and Tour Operations Management', 'units' => 3.0, 'year_level' => 2, 'semester' => 1],
            ['subject_code' => 'TM202',  'subject_name' => 'Airline Reservations and Ticketing',       'units' => 3.0, 'year_level' => 2, 'semester' => 1],
            ['subject_code' => 'TM203',  'subject_name' => 'Tourism Marketing',                        'units' => 3.0, 'year_level' => 2, 'semester' => 1],
            ['subject_code' => 'TM204',  'subject_name' => 'Ecotourism',                               'units' => 3.0, 'year_level' => 2, 'semester' => 1],
            ['subject_code' => 'GE201',  'subject_name' => 'Ethics (Good Governance)',                 'units' => 3.0, 'year_level' => 2, 'semester' => 1],
            ['subject_code' => 'PE201',  'subject_name' => 'Physical Education 3',                     'units' => 2.0, 'year_level' => 2, 'semester' => 1],

            // Year 2 - Sem 2
            ['subject_code' => 'TM205',  'subject_name' => 'Hotel and Restaurant Management',          'units' => 3.0, 'year_level' => 2, 'semester' => 2],
            ['subject_code' => 'TM206',  'subject_name' => 'Events Management',                        'units' => 3.0, 'year_level' => 2, 'semester' => 2],
            ['subject_code' => 'TM207',  'subject_name' => 'Culinary Arts',                            'units' => 3.0, 'year_level' => 2, 'semester' => 2],
            ['subject_code' => 'TM208',  'subject_name' => 'Tourism Law and Taxation',                 'units' => 3.0, 'year_level' => 2, 'semester' => 2],
            ['subject_code' => 'GE202',  'subject_name' => 'Art Appreciation',                         'units' => 3.0, 'year_level' => 2, 'semester' => 2],
            ['subject_code' => 'PE202',  'subject_name' => 'Physical Education 4',                     'units' => 2.0, 'year_level' => 2, 'semester' => 2],

            // Year 3 - Sem 1
            ['subject_code' => 'TM301',  'subject_name' => 'Tourism Research Methods',                 'units' => 3.0, 'year_level' => 3, 'semester' => 1],
            ['subject_code' => 'TM302',  'subject_name' => 'Sustainable Tourism Development',          'units' => 3.0, 'year_level' => 3, 'semester' => 1],
            ['subject_code' => 'TM303',  'subject_name' => 'Tourism Planning and Development',         'units' => 3.0, 'year_level' => 3, 'semester' => 1],
            ['subject_code' => 'TM304',  'subject_name' => 'Tourism Entrepreneurship',                 'units' => 3.0, 'year_level' => 3, 'semester' => 1],
            ['subject_code' => 'TM305',  'subject_name' => 'Meetings, Incentives, Conferences and Exhibitions (MICE)', 'units' => 3.0, 'year_level' => 3, 'semester' => 1],

            // Year 3 - Sem 2
            ['subject_code' => 'TM306',  'subject_name' => 'Heritage Tourism',                         'units' => 3.0, 'year_level' => 3, 'semester' => 2],
            ['subject_code' => 'TM307',  'subject_name' => 'Tourism Information Technology',           'units' => 3.0, 'year_level' => 3, 'semester' => 2],
            ['subject_code' => 'TM308',  'subject_name' => 'Practicum (600 hours)',                    'units' => 6.0, 'year_level' => 3, 'semester' => 2],

            // Year 4 - Sem 1
            ['subject_code' => 'TM401',  'subject_name' => 'Thesis Writing 1',                        'units' => 3.0, 'year_level' => 4, 'semester' => 1],
            ['subject_code' => 'TM402',  'subject_name' => 'Strategic Management in Tourism',          'units' => 3.0, 'year_level' => 4, 'semester' => 1],
            ['subject_code' => 'TM403',  'subject_name' => 'International Tourism',                   'units' => 3.0, 'year_level' => 4, 'semester' => 1],

            // Year 4 - Sem 2
            ['subject_code' => 'TM404',  'subject_name' => 'Thesis Writing 2',                        'units' => 3.0, 'year_level' => 4, 'semester' => 2],
            ['subject_code' => 'TM405',  'subject_name' => 'Tourism Policy and Administration',        'units' => 3.0, 'year_level' => 4, 'semester' => 2],
            ['subject_code' => 'TM406',  'subject_name' => 'Rural and Community-Based Tourism',        'units' => 3.0, 'year_level' => 4, 'semester' => 2],
        ];

        $this->insertSubjects($program, $subjects);
    }

    // ─────────────────────────────────────────────────────────────
    // BTVTEd major in Food Service Management
    // ─────────────────────────────────────────────────────────────
    private function seedBTVTEd(): void
    {
        $program = Program::firstOrCreate(
            ['code' => 'BTVTEd-FSM'],
            ['name' => 'BTVTEd major in Food Service Management']
        );

        $subjects = [
            // Year 1 - Sem 1
            ['subject_code' => 'BTEd101', 'subject_name' => 'Introduction to Technical Vocational Education', 'units' => 3.0, 'year_level' => 1, 'semester' => 1],
            ['subject_code' => 'FSM101',  'subject_name' => 'Food Principles and Theory',              'units' => 3.0, 'year_level' => 1, 'semester' => 1],
            ['subject_code' => 'FSM102',  'subject_name' => 'Bread and Pastry Production',             'units' => 3.0, 'year_level' => 1, 'semester' => 1],
            ['subject_code' => 'GE101',   'subject_name' => 'Purposive Communication',                 'units' => 3.0, 'year_level' => 1, 'semester' => 1],
            ['subject_code' => 'GE102',   'subject_name' => 'Understanding the Self',                  'units' => 3.0, 'year_level' => 1, 'semester' => 1],
            ['subject_code' => 'PE101',   'subject_name' => 'Physical Education 1',                    'units' => 2.0, 'year_level' => 1, 'semester' => 1],
            ['subject_code' => 'NSTP101', 'subject_name' => 'National Service Training Program 1',     'units' => 3.0, 'year_level' => 1, 'semester' => 1],

            // Year 1 - Sem 2
            ['subject_code' => 'FSM103',  'subject_name' => 'Food and Beverage Service',               'units' => 3.0, 'year_level' => 1, 'semester' => 2],
            ['subject_code' => 'FSM104',  'subject_name' => 'Kitchen Essentials and Basic Cookery',    'units' => 3.0, 'year_level' => 1, 'semester' => 2],
            ['subject_code' => 'BTEd102', 'subject_name' => 'Educational Technology',                  'units' => 3.0, 'year_level' => 1, 'semester' => 2],
            ['subject_code' => 'GE103',   'subject_name' => 'Readings in Philippine History',          'units' => 3.0, 'year_level' => 1, 'semester' => 2],
            ['subject_code' => 'MATH101', 'subject_name' => 'Mathematics in the Modern World',         'units' => 3.0, 'year_level' => 1, 'semester' => 2],
            ['subject_code' => 'PE102',   'subject_name' => 'Physical Education 2',                    'units' => 2.0, 'year_level' => 1, 'semester' => 2],
            ['subject_code' => 'NSTP102', 'subject_name' => 'National Service Training Program 2',     'units' => 3.0, 'year_level' => 1, 'semester' => 2],

            // Year 2 - Sem 1
            ['subject_code' => 'FSM201',  'subject_name' => 'Commercial Cooking',                      'units' => 3.0, 'year_level' => 2, 'semester' => 1],
            ['subject_code' => 'FSM202',  'subject_name' => 'Nutrition and Food Safety',               'units' => 3.0, 'year_level' => 2, 'semester' => 1],
            ['subject_code' => 'FSM203',  'subject_name' => 'Housekeeping Services',                   'units' => 3.0, 'year_level' => 2, 'semester' => 1],
            ['subject_code' => 'BTEd201', 'subject_name' => 'Facilitating Learning',                   'units' => 3.0, 'year_level' => 2, 'semester' => 1],
            ['subject_code' => 'GE201',   'subject_name' => 'Ethics (Good Governance)',                'units' => 3.0, 'year_level' => 2, 'semester' => 1],
            ['subject_code' => 'PE201',   'subject_name' => 'Physical Education 3',                    'units' => 2.0, 'year_level' => 2, 'semester' => 1],

            // Year 2 - Sem 2
            ['subject_code' => 'FSM204',  'subject_name' => 'Food Processing and Preservation',        'units' => 3.0, 'year_level' => 2, 'semester' => 2],
            ['subject_code' => 'FSM205',  'subject_name' => 'Bartending and Sommelier',                'units' => 3.0, 'year_level' => 2, 'semester' => 2],
            ['subject_code' => 'FSM206',  'subject_name' => 'Food Entrepreneurship',                   'units' => 3.0, 'year_level' => 2, 'semester' => 2],
            ['subject_code' => 'BTEd202', 'subject_name' => 'The Child and Adolescent Learner',        'units' => 3.0, 'year_level' => 2, 'semester' => 2],
            ['subject_code' => 'GE202',   'subject_name' => 'Art Appreciation',                        'units' => 3.0, 'year_level' => 2, 'semester' => 2],
            ['subject_code' => 'PE202',   'subject_name' => 'Physical Education 4',                    'units' => 2.0, 'year_level' => 2, 'semester' => 2],

            // Year 3 - Sem 1
            ['subject_code' => 'FSM301',  'subject_name' => 'Food Service Management and Cost Control','units' => 3.0, 'year_level' => 3, 'semester' => 1],
            ['subject_code' => 'FSM302',  'subject_name' => 'Catering Management',                     'units' => 3.0, 'year_level' => 3, 'semester' => 1],
            ['subject_code' => 'BTEd301', 'subject_name' => 'Principles of Teaching 1',               'units' => 3.0, 'year_level' => 3, 'semester' => 1],
            ['subject_code' => 'BTEd302', 'subject_name' => 'Curriculum Development',                  'units' => 3.0, 'year_level' => 3, 'semester' => 1],
            ['subject_code' => 'FSM303',  'subject_name' => 'Research in Food Service',                'units' => 3.0, 'year_level' => 3, 'semester' => 1],

            // Year 3 - Sem 2
            ['subject_code' => 'FSM304',  'subject_name' => 'International Cuisine',                   'units' => 3.0, 'year_level' => 3, 'semester' => 2],
            ['subject_code' => 'BTEd303', 'subject_name' => 'Principles of Teaching 2',               'units' => 3.0, 'year_level' => 3, 'semester' => 2],
            ['subject_code' => 'BTEd304', 'subject_name' => 'Assessment of Learning',                  'units' => 3.0, 'year_level' => 3, 'semester' => 2],
            ['subject_code' => 'FSM305',  'subject_name' => 'Practicum (300 hours)',                   'units' => 6.0, 'year_level' => 3, 'semester' => 2],

            // Year 4 - Sem 1
            ['subject_code' => 'BTEd401', 'subject_name' => 'Practice Teaching (TVET) 1',             'units' => 6.0, 'year_level' => 4, 'semester' => 1],
            ['subject_code' => 'FSM401',  'subject_name' => 'Thesis Writing 1',                       'units' => 3.0, 'year_level' => 4, 'semester' => 1],
            ['subject_code' => 'FSM402',  'subject_name' => 'Special Topics in Food Service',         'units' => 3.0, 'year_level' => 4, 'semester' => 1],

            // Year 4 - Sem 2
            ['subject_code' => 'BTEd402', 'subject_name' => 'Practice Teaching (TVET) 2',             'units' => 6.0, 'year_level' => 4, 'semester' => 2],
            ['subject_code' => 'FSM403',  'subject_name' => 'Thesis Writing 2',                       'units' => 3.0, 'year_level' => 4, 'semester' => 2],
            ['subject_code' => 'FSM404',  'subject_name' => 'Food Safety and Sanitation Management',  'units' => 3.0, 'year_level' => 4, 'semester' => 2],
        ];

        $this->insertSubjects($program, $subjects);
    }

    // ─────────────────────────────────────────────────────────────
    // Helper
    // ─────────────────────────────────────────────────────────────
    private function insertSubjects(Program $program, array $subjects): void
    {
        foreach ($subjects as $s) {
            CourseSubject::firstOrCreate(
                ['program_id' => $program->id, 'subject_code' => $s['subject_code']],
                array_merge($s, ['program_id' => $program->id])
            );
        }
    }
}
