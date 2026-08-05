<?php

namespace Tests\Unit\Support;

use App\Support\DocumentNumber;
use Tests\TestCase;

/**
 * Obs 4: el DNI/RUC/CE pierde ceros a la izquierda en la carga masiva porque
 * la columna "Número de documento" queda en formato General y el
 * DefaultValueBinder de PhpSpreadsheet numeriza la celda ("01234567" -> int
 * 1234567, o "12345678.0" cuando se lee como float). DocumentNumber::normalize
 * repone ese padding; estos tests cubren el helper de forma aislada, sin
 * pasar por el import completo.
 */
class DocumentNumberTest extends TestCase
{
    public function test_pads_short_numeric_dni_to_eight_digits(): void
    {
        $this->assertSame('01234567', DocumentNumber::normalize('dni', 1234567));
    }

    public function test_does_not_touch_dni_already_eight_digits(): void
    {
        $this->assertSame('00000000', DocumentNumber::normalize('dni', '00000000'));
    }

    public function test_strips_excel_float_artifact_before_padding(): void
    {
        $this->assertSame('12345678', DocumentNumber::normalize('dni', '12345678.0'));
    }

    public function test_pads_short_numeric_ruc_to_eleven_digits(): void
    {
        $this->assertSame('00123456789', DocumentNumber::normalize('ruc', '123456789'));
    }

    public function test_pads_short_numeric_ce_to_twelve_digits(): void
    {
        $this->assertSame('000001234567', DocumentNumber::normalize('ce', '1234567'));
    }

    public function test_does_not_pad_passport(): void
    {
        $this->assertSame('AB123', DocumentNumber::normalize('passport', 'AB123'));
    }

    public function test_returns_null_for_null_input(): void
    {
        $this->assertNull(DocumentNumber::normalize('dni', null));
    }

    public function test_returns_null_for_empty_string_input(): void
    {
        $this->assertNull(DocumentNumber::normalize('dni', ''));
    }

    public function test_never_truncates_a_longer_value(): void
    {
        $this->assertSame('123456789', DocumentNumber::normalize('dni', '123456789'));
    }

    public function test_is_case_insensitive_on_document_type(): void
    {
        $this->assertSame('01234567', DocumentNumber::normalize('DNI', '1234567'));
    }

    public function test_null_document_type_does_not_pad(): void
    {
        $this->assertSame('1234567', DocumentNumber::normalize(null, '1234567'));
    }
}
