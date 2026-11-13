<?php

namespace Tests\Unit;

use App\Support\DocumentContent;
use PHPUnit\Framework\TestCase;

class DocumentContentTest extends TestCase
{
    public function test_legacy_document_keeps_clauses_and_real_data_tables(): void
    {
        $html = <<<'HTML'
            <table><tr><td><a>До розділу</a></td><td><h1>Старий заголовок</h1></td></tr>
            <tr><td colspan="2"><blockquote><p><b><font>1. ЗАГАЛЬНІ ПОЛОЖЕННЯ</font></b></p>
            <p>1.1. Текст Статуту.</p><ul><li>Пункт переліку</li></ul>
            <table><tr><th>Назва</th><th>Дата</th></tr><tr><td colspan="2">Дані</td></tr></table>
            </blockquote></td></tr><tr><td><a>До розділу</a></td></tr></table>
            HTML;

        $clean = DocumentContent::clean($html);

        $this->assertStringNotContainsString('До розділу', $clean);
        $this->assertStringNotContainsString('Старий заголовок', $clean);
        $this->assertStringNotContainsString('<blockquote>', $clean);
        $this->assertStringContainsString('<h3><b>1. ЗАГАЛЬНІ ПОЛОЖЕННЯ</b></h3>', html_entity_decode($clean));
        $this->assertStringContainsString('1.1. Текст Статуту.', html_entity_decode($clean));
        $this->assertStringContainsString('<ul><li>Пункт переліку</li></ul>', html_entity_decode($clean));
        $this->assertSame(1, substr_count($clean, '<table'));
        $this->assertStringContainsString('<td colspan="2">', $clean);
    }
}
