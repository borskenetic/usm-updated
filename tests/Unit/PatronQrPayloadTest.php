<?php

namespace Tests\Unit;

use App\Support\PatronQrPayload;
use PHPUnit\Framework\TestCase;

class PatronQrPayloadTest extends TestCase
{
    public function test_parses_tab_separated_payload(): void
    {
        $parsed = PatronQrPayload::parse("21-11048\tDECIE C. GANAO\tABPhilo\tDecember 5, 2002");

        $this->assertSame('21-11048', $parsed['student_no']);
        $this->assertSame('DECIE C. GANAO', $parsed['full_name']);
        $this->assertSame('ABPhilo', $parsed['course']);
        $this->assertSame('December 5, 2002', $parsed['birthday']);
    }

    public function test_parses_official_usm_id_comma_payload(): void
    {
        $parsed = PatronQrPayload::parse(
            '26-78714,KRISTINE MAE B. ONDOY,BSRT,1ST SEMESTER A.Y. 2026-2027'
        );

        $this->assertSame('26-78714', $parsed['student_no']);
        $this->assertSame('KRISTINE MAE B. ONDOY', $parsed['full_name']);
        $this->assertSame('BSRT', $parsed['course']);
    }

    public function test_parses_name_first_comma_payload_with_birthday(): void
    {
        $parsed = PatronQrPayload::parse('MERRYL JOANNA JOYCE T. GONZALES,BSN,,June 13, 2003');

        $this->assertNull($parsed['student_no']);
        $this->assertSame('MERRYL JOANNA JOYCE T. GONZALES', $parsed['full_name']);
        $this->assertSame('BSN', $parsed['course']);
        $this->assertSame('June 13, 2003', $parsed['birthday']);
    }

    public function test_parses_name_first_comma_payload_without_birthday(): void
    {
        $parsed = PatronQrPayload::parse('MOHAMMAD B. HASSAN,BEED,,');

        $this->assertNull($parsed['student_no']);
        $this->assertSame('MOHAMMAD B. HASSAN', $parsed['full_name']);
        $this->assertSame('BEED', $parsed['course']);
    }

    public function test_parses_url_payload(): void
    {
        $parsed = PatronQrPayload::parse('https://usm.edu.ph/21-3619');

        $this->assertSame('21-3619', $parsed['student_no']);
    }

    public function test_normalize_name_strips_middle_initial_and_suffix(): void
    {
        $this->assertSame(
            'MUHAIMAUGKA',
            PatronQrPayload::normalizeName('MUHAIMA L. UGKA SR.')
        );
        $this->assertSame(
            'KRISTINEMAEONDOY',
            PatronQrPayload::normalizeName('KRISTINE MAE B. ONDOY')
        );
    }

    public function test_courses_compatible_handles_prefix_variants(): void
    {
        $this->assertTrue(PatronQrPayload::coursesCompatible('ABPhilo', 'ABPhilos'));
        $this->assertTrue(PatronQrPayload::coursesCompatible('BIT', 'BIndTech'));
    }
}
